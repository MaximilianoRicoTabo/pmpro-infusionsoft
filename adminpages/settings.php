<?php

// Only admins can access this page.
if( !function_exists( "current_user_can" ) || ( !current_user_can( "manage_options" ) ) ) {
	die( esc_html__( "You do not have permissions to perform this action.", 'p' ) );
}

global $msg, $msgt;

// Bail if nonce field isn't set.
if ( !empty( $_REQUEST['savesettings'] ) && ( empty( $_REQUEST[ 'pmpro_keap_nonce' ] ) 
|| !check_admin_referer( 'savesettings', 'pmpro_keap_nonce' ) ) ) {
$msg = -1;
$msgt = __( "Are you sure you want to do that? Try again.", 'paid-memberships-pro' );
unset( $_REQUEST[ 'savesettings' ] );
}

// Save settings.
if( !empty( $_REQUEST['savesettings'] ) ) {

	// Assume success.
	$msg = true;
	$msgt = __("Your security settings have been updated.", 'paid-memberships-pro' );
	//save options
	$options = get_option( 'pmpro_keap_options' );
	$options[ 'api_key' ] = sanitize_text_field( $_REQUEST[ 'pmpro_keap_options' ][ 'api_key' ] );
	$options[ 'api_secret' ] = sanitize_text_field( $_REQUEST[ 'pmpro_keap_options' ] [ 'api_secret' ] );
	//save level options. It must delete the old options and save the new ones

	if(  ! empty( $_REQUEST[ 'pmpro_keap_options' ][ 'levels' ] ) ) {
		foreach( $_REQUEST[ 'pmpro_keap_options' ][ 'levels' ] as $level_id => $level_tags ) {
			$options[ 'levels' ][ $level_id ] = $level_tags;
		}
	}
	//save the options	
	update_option( 'pmpro_keap_options', $options );

}
	// Include admin header
	require_once PMPRO_DIR . '/adminpages/admin_header.php';
	$options = get_option( 'pmpro_keap_options' );

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
				wp_nonce_field( 'savesettings', 'pmpro_keap_nonce' );
				do_settings_sections( 'pmpro_keap_options' );
				?>
			<p class="submit topborder">
	 				<input name="savesettings" type="submit" class="button-primary" value="<?php esc_html_e('Save Settings', 'pmpro-keap');?>" />
	 			</p>
	 			<?php if ( !$accessToken ) { ?>
			<p><?php esc_html_e( 'You need to authorize with Keap to fetch tags.', 'pmpro-keap' ) ?></p>
	 			<?php } ?>
	 		</form>
	 	</div>

		<?php 
	 	require_once PMPRO_DIR . '/adminpages/admin_footer.php';