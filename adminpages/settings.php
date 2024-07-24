<?php

// Only admins can access this page.
if( !function_exists( "current_user_can" ) || ( !current_user_can( "manage_options" ) ) ) {
	die( esc_html__( "You do not have permissions to perform this action.", 'p' ) );
}

global $msg, $msgt;

// Bail if nonce field isn't set.
if ( !empty( $_REQUEST['savesettings'] ) && ( empty( $_REQUEST[ 'pmprokeap_nonce' ] ) 
|| !check_admin_referer( 'savesettings', 'pmprokeap_nonce' ) ) ) {
$msg = -1;
$msgt = __( "Are you sure you want to do that? Try again.", 'paid-memberships-pro' );
unset( $_REQUEST[ 'savesettings' ] );
}

// Save settings.
if( !empty( $_REQUEST['savesettings'] ) ) {

	// Assume success.
	$msg = true;
	$msgt = __("Your security settings have been updated.", 'paid-memberships-pro' );
}
//Include admin header
	// Load the admin header.
	require_once PMPRO_DIR . '/adminpages/admin_header.php';
$options = get_option( 'pmprokeap_options' );

		if( ! empty( $options[ 'api_key' ] ) ) {
			$api_key = $options[ 'api_key' ];
		}

		if( ! empty( $options[ 'api_secret' ] ) ) {
			$api_secret = $options[ 'api_secret' ];
		}

		// Retrieve stored access token
		$accessToken = get_option( 'keap_access_token' );

		?>
	 	<div class="wrap">
	 		<div id="icon-options-general" class="icon32"><br></div>
			<h2><?php esc_html_e( 'Keap', 'pmpro-keap' );?></h2>

		<form action="" method="post" enctype="multipart/form-data">
	 	<?php
				wp_nonce_field( 'savesettings', 'pmprokeap_nonce' );
				do_settings_sections( 'pmprokeap_options' );
				?>
			<p class="submit topborder">
	 				<input name="savesettings" type="submit" class="button-primary" value="<?php esc_html_e('Save Settings', 'pmpro-keap');?>" />
	 			</p>
	 			<?php if ( !$accessToken ) { ?>
			<p><?php esc_html_e( 'You need to authorize with Keap to fetch tags.', 'pmpro-keap' ) ?></p>
	 			<?php } ?>
	 		</form>
	 	</div>
	 	<script>
	 		jQuery( document ).ready( function( $ ) {
	 			$( 'select' ).select2();
	 		});
	 	</script>

		<?php 
	 	require_once PMPRO_DIR . '/adminpages/admin_footer.php';