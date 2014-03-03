<style type="text/css">
#git_repos { margin: 0; }
.git-deploy-repo { border-bottom: 1px solid #aaa; margin-bottom: 9px; padding-bottom: 9px; }
.git-deploy-repo label { display: inline-block; width: 200px; }
</style>

<script type="text/javascript">
jQuery(function($){
	var gd_count = 0;
	var gd_tpl = _.template( $('#git_deploy_repo').html() );
	var gd_repos = <?php echo json_encode( $this->options['repos'] ) ?>;
	$('#git_deploy_add_repo').click( function( event ) {
		event.preventDefault();
		$('#git_repos').append( gd_tpl( {
			i: ++gd_count,
			name: '',
			ref: '',
			path: ''
		} ) );
	} );

	$('#git_repos').on( 'click', '.git-deploy-remove-repo', function( event ){
		event.preventDefault();
		$(this).closest('.git-deploy-repo').hide( 'normal', function() { $(this).remove(); } );
	} );

	if ( gd_repos.length ) {
		_.each( gd_repos, function( repo, c ) {
			console.log( c );
			console.log( repo );
			$('#git_repos').append( gd_tpl( {
				i: ++gd_count,
				name: repo.name,
				ref: repo.ref,
				path: repo.path
			} ) );
		} );
	} else {
		$('#git_deploy_add_repo').click();
	}
});
</script>

<div class="wrap">
	<h2>Git Deployment</h2>

	<?php if ( '1' == $_GET['save'] ) : ?>
		<div class="updated success"><p><?php _e( 'Options saved successfully', 'wp-git-deploy' ); ?></p></div>
	<?php endif ?>

	<p>Git Deploy Path: <a href="<?php echo home_url( '/git-deploy/' . $auth ) ?>"><?php echo home_url( '/git-deploy/' . $auth ) ?></a></p>
	<form action="admin-post.php" method="post">
		<?php wp_nonce_field( 'git-deply-options', 'git-deploy-nonce' ) ?>
		<input type="hidden" name="action" value="git_deploy_save" />
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for=""><?php _e( 'Auth Key', 'wp-git-deploy' ); ?></label>
					</th>
					<td>
						<input id="git_deploy_auth_key" name="git_deploy[auth_key]" value="<?php echo esc_attr( $this->options['auth_key'] ); ?>" class="regular-text ltr" />
						<p class="description"><?php _e( '(Optional) For added security, add a special key here that will be a part of the deployment URL. Should be URL-friendly.', 'wp-git-deploy' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="git_deploy_ips"><?php _e( 'Whitelist IPs', 'wp-git-deploy' ); ?></label>
					</th>
					<td>
						<textarea name="git_deploy[ips]" id="git_deploy_ips" rows="5" cols="30"><?php echo esc_html( implode( "\n", $this->options['ips'] ) ) ?></textarea>
						<p class="description"><?php _e( '(Optional) For added security, you can whitelist IP addresses. If set, only deployments from these IPs will be allowed. One IP per line.', 'wp-git-deploy' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="git_deploy_bin"><?php _e( 'Path to git', 'wp-git-deploy' ); ?></label>
					</th>
					<td>
						<input name="git_deploy[git]" id="git_deploy_bin" class="regular-text ltr" value="<?php echo esc_attr( $this->options['git'] ); ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label><?php _e( 'Repositories', 'wp-git-deploy' ); ?></label>
					</th>
					<td>
						<script type="text/template" id="git_deploy_repo">
							<li class="git-deploy-repo">
								<p>
									<label for="git_repo_name_<%= i %>"><?php _e( 'Repository Name', 'wp-git-deploy' ); ?></label>
									<input name="git_deploy[repos][<%= i %>][name]" id="git_repo_name_<%= i %>" class="regular-text ltr" value="<%= name %>" />
									<p class="description"><?php _e( 'e.g. for https://github.com/alleyinteractive/wp-git-deploy, this would be wp-git-deploy', 'wp-git-deploy' ); ?></p>
								</p>
								<p>
									<label for="git_repo_ref_<%= i %>"><?php _e( 'Deploy Ref Regex', 'wp-git-deploy' ); ?></label>
									<input name="git_deploy[repos][<%= i %>][ref]" id="git_repo_ref_<%= i %>" class="regular-text ltr" value="<%= ref %>" /><br />
									<p class="description"><?php _e( 'e.g. ^refs/heads/master$', 'wp-git-deploy' ); ?></p>
								</p>
								<p>
									<label for="git_repo_path_<%= i %>"><?php _e( 'Local Path', 'wp-git-deploy' ); ?></label>
									<input name="git_deploy[repos][<%= i %>][path]" id="git_repo_path_<%= i %>" class="regular-text ltr" value="<%= path %>" /><br />
									<p class="description"><?php _e( 'Relative to WordPress root', 'wp-git-deploy' ); ?></p>
								</p>
								<p><a href="#" class="git-deploy-remove-repo"><?php _e( 'remove', 'wp-git-deploy' ) ?></a></p>
							</li>
						</script>
						<ul id="git_repos"></ul>
						<a href="#" id="git_deploy_add_repo" class="button-secondary">Add Repository</a>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button() ?>

	</form>
</div>
