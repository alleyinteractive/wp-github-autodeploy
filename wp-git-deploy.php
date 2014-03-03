<?php

/*
	Plugin Name: Git Deploy
	Plugin URI: http://www.alleyinteractive.com/
	Description: Deploy updates through git with this plugin
	Version: 0.1
	Author: Matthew Boynes
	Author URI: http://www.alleyinteractive.com/
*/
/*  This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( !class_exists( 'WP_Git_Deploy' ) ) :

class WP_Git_Deploy {

	private static $instance;

	const SLUG = 'git-deploy-options';

	public $options = array();

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone WP_Git_Deploy" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup WP_Git_Deploy" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WP_Git_Deploy;
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * Load the options on demand
	 *
	 * @return void
	 */
	public function load_options() {
		if ( ! $this->options ) {
			$this->options = (array) get_option( self::SLUG );
			$this->options = wp_parse_args( $this->options, array(
				'auth_key' => '',
				'ips'      => array(),
				'git'      => 'git',
				'repos'    => array()
			) );
		}
	}

	public function setup() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'init', array( $this, 'rewrite_rule' ) );
		// add_action( 'admin_init', array( self::$instance, 'admin_init' ) );
		add_action( 'admin_print_scripts-tools_page_git_deploy', array( $this, 'enqueue_scripts' ) );
		add_action( 'parse_query', array( $this, 'deploy' ) );
		add_action( 'admin_post_git_deploy_save', array( $this, 'save_options' ) );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'underscore' );
	}

	public function menu() {
		add_management_page( __('Git Deploy'), __('Git Deploy'), 'manage_options', 'git_deploy', array( $this, 'admin_page' ) );
	}

	public function admin_page() {
		if ( !current_user_can( 'manage_options' ) ) wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		$this->load_options();
		$auth = $this->options['auth_key'] ? $this->options['auth_key'] . '/' : '';
		require_once( dirname( __FILE__ ) . '/admin_page.php' );
	}

	public function save_options() {
		if ( !isset( $_POST['git-deploy-nonce'] ) || ! wp_verify_nonce( $_POST['git-deploy-nonce'], 'git-deply-options' ) ) {
			wp_die( __( 'You are not authorized to perform that action', 'wp-git-deploy' ) );
		}

		$this->load_options();
		$options = wp_parse_args( $_POST['git_deploy'], array(
			'auth_key' => '',
			'ips'      => array(),
			'git'      => 'git',
			'repos'    => array()
		) );

		$repos = array();
		foreach ( $options['repos'] as $repo ) {
			$repos[] = array(
				'name' => sanitize_text_field( $repo['name'] ),
				'ref'  => sanitize_text_field( $repo['ref'] ),
				'path' => sanitize_text_field( $repo['path'] )
			);
		}

		$ips = array();
		$options['ips'] = preg_split( "/\s+/", $options['ips'] );
		foreach ( $options['ips'] as $ip ) {
			$ip = preg_replace( '/[^\d\.]/', '', $ip );
			if ( preg_match( '/^(\d{1,3}\.){3}\d{1,3}$/' ) ) {
				$ips[] = $ip;
			}
		}

		$this->options['auth_key'] = sanitize_text_field( $options['auth_key'] );
		$this->options['git']      = sanitize_text_field( $options['git'] );
		$this->options['repos']    = $repos;
		$this->options['ips']      = $ips;

		update_option( self::SLUG, $this->options );

		wp_redirect( admin_url( 'tools.php?page=git_deploy&save=1' ) );
		exit;
	}

	/**
	 * Add a repository to the array. This method exists to serve as a mini API so
	 * other plugins can add repositories
	 *
	 * @param array $repo
	 * @return void
	 */
	public function add_repo( $repo = array() ) {
		$this->load_options();
		$this->options['repos'][] = $repo;
		update_option( self::SLUG, $this->options );
	}

	public function rewrite_rule() {
		add_rewrite_tag( '%git-deploy%', 'git-deploy' );
		add_rewrite_tag( '%git-auth%', '(/.*)' );
		add_rewrite_rule( 'git-deploy(/.*)?/?', 'index.php?git-deploy=1&git-auth=$matches[1]', 'top' );
	}

	public function activate() {
		delete_option( 'rewrite_rules' );
		if ( false === get_option( self::SLUG ) ) {
			$git = `which git`;
			if ( ! strpos( $git, '/git' ) )
				$git = 'git';
			add_option( self::SLUG, array(
				'auth_key' => strtolower( wp_generate_password( 12, false ) ),
				'repos'    => array(),
				'ips'      => array(),
				'git'      => $git
			), '', 'no' );
		}
	}

	public function deactivate() {
		delete_option( 'rewrite_rules' );
	}

	public function deploy( $wp ) {
		if ( ! empty( $wp->query_vars['git-deploy'] ) ) {
			$this->load_options();
			$auth = get_query_var( 'git-auth' );

			if ( ( ! empty( $this->options['auth_key'] ) && empty( $auth ) )
				|| '/' . $this->options['auth_key'] != $auth
				|| 'post' != strtolower( $_SERVER['REQUEST_METHOD'] )
				|| ! isset( $_POST['payload'] )
				|| ! $this->verify_ip()
			) {
				wp_die( __( "You don't have permission to access this page.", 'wp-git-deploy' ) );
			}

			$payload = json_decode( stripslashes( $_POST['payload'] ) );

			foreach ( $this->options['repos'] as $repo ) {
				if ( $repo['name'] == $payload->repository->name && preg_match( "#{$repo['ref']}#i", $payload->ref ) ) {
					$this->pull( $repo['path'] );
					break;
				}
			}

			exit;
		}
	}

	public function pull( $path ) {
		$this->load_options();
		if ( ! empty( $path ) && file_exists( $path ) ) {
			chdir( ABSPATH . $path );
			$command = "{$this->options['git']} pull";
			$output = array( "{$path}> {$command}" );
			# If we have a commit to one of our branches, we can pull on it
			exec( "$command 2>&1", $output );
			// fwrite( $handle, "Commit to {$payload->repository->name}:{$payload->ref}, executing:". implode( "\n", $output ) . "\n" );
			// echo "Commit to {$payload->repository->name}:{$payload->ref}, executing:". implode( "\n", $output ) . "\n";
			error_log( "Commit to {$payload->repository->name}:{$payload->ref}, executing:". implode( "\n", $output ) );
		} else {
			// fwrite( $handle, "Commit to {$payload->repository->name}:{$payload->ref}, but no matching path found!\n" );
			// echo "Commit to {$payload->repository->name}:{$payload->ref}, but no matching path found!\n";
			error_log( "Commit to {$payload->repository->name}:{$payload->ref}, but no matching path found!" );
		}
	}

	public function git_clone( $path, $repo ) {
		$this->load_options();
		if ( ! empty( $path ) && ! file_exists( $path ) && preg_match( '#^(.*/)([^/]+)/?$#i', $path, $matches ) ) {
			$parent = $matches[1];

			if ( ! file_exists( $parent ) )
				@mkdir( $parent );

			if ( chdir( ABSPATH . $parent ) ) {
				$command = "{$this->options['git']} clone --recursive {$repo} {$matches[2]}";
				$output = array( "{$parent}> {$command}" );
				# If we have a commit to one of our branches, we can pull on it
				exec( "$command 2>&1", $output );
				// fwrite( $handle, "Cloning new repo; executing:". implode( "\n", $output ) . "\n" );
				// echo "Cloning new repo; executing:". implode( "\n", $output ) . "\n";
				error_log( "Cloning new repo; executing:" . implode( "\n", $output ) );
			}
		} else {
			// fwrite( $handle, "Cloning new repo; but no matching path found!\n" );
			// echo "Cloning new repo; but no matching path found!\n";
			error_log( "Cloning new repo; but no matching path found!" );
		}
	}

	public function verify_ip() {
		if ( empty( $this->options['ips'] ) )
			return true;

		return in_array( $_SERVER['REMOTE_ADDR'], $this->options['ips'] );
	}
}

function WP_Git_Deploy() {
	return WP_Git_Deploy::instance();
}
add_action( 'after_setup_theme', 'WP_Git_Deploy' );

# Plugin activation and deactivation stuff
register_activation_hook( __FILE__, array( WP_Git_Deploy(), 'activate' ) );
register_deactivation_hook( __FILE__, array( WP_Git_Deploy(), 'deactivate' ) );

endif;
