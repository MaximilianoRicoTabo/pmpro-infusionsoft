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
$msgt = __( "Are you sure you want to do that? Try again.", 'pmpro-infusionsoft' );
unset( $_REQUEST[ 'savesettings' ] );
}

// Save settings.
if( !empty( $_REQUEST['savesettings'] ) ) {

	// Assume success.
	$msg = true;
	$msgt = __("Your security settings have been updated.", 'pmpro-infusionsoft' );
	//save options
	$options = get_option( 'pmpro_keap_options' );
	$options[ 'api_key' ] = sanitize_text_field( $_REQUEST[ 'pmpro_keap_options' ][ 'api_key' ] );
	$options[ 'api_secret' ] = sanitize_text_field( $_REQUEST[ 'pmpro_keap_options' ] [ 'api_secret' ] );
	//save level options. It must delete the old options and save the new ones


	// Check if levels are submitted in the request
	if ( isset( $_REQUEST['pmpro_keap_options']['levels'] ) ) {
		$submitted_levels = $_REQUEST['pmpro_keap_options']['levels'];

		// Iterate over existing levels to check if they should be updated or deleted
		foreach ( $options['levels'] as $level_id => $level_tags ) {
			if ( isset( $submitted_levels[$level_id] ) ) {
				// Update existing levels with new tags
				$temp_level_tags = array();
				foreach ( $submitted_levels[$level_id] as $tag ) {
					$temp_level_tags[] = sanitize_text_field( $tag );
				}
				$options['levels'][$level_id] = $temp_level_tags;
			} else {
				// If a level is not in the request, it means all items were deleted, so remove the level
				unset( $options['levels'][$level_id] );
			}
		}

		// Add any new levels that were submitted
		foreach ( $submitted_levels as $level_id => $level_tags ) {
			if ( ! isset( $options['levels'][$level_id] ) ) {
				$temp_level_tags = array();
				foreach ( $level_tags as $tag ) {
					$temp_level_tags[] = sanitize_text_field( $tag );
				}
				$options['levels'][$level_id] = $temp_level_tags;
			}
		}
	} else {
		// If no levels are submitted, clear all levels
		$options['levels'] = array();
	}
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
		$accessToken = get_option( 'pmpro_keap_access_token' );

		?>
	 	<div class="wrap">
	 		<div id="icon-options-general" class="icon32"><br></div>
			<h2><?php esc_html_e( 'Keap', 'pmpro-infusionsoft' );?></h2>

		<form action="" method="post" enctype="multipart/form-data">
	 	<?php
				wp_nonce_field( 'savesettings', 'pmpro_keap_nonce' );
				do_settings_sections( 'pmpro_keap_options' );
				?>
			<p class="submit topborder">
	 				<input name="savesettings" type="submit" class="button-primary" value="<?php esc_html_e('Save Settings', 'pmpro-infusionsoft');?>" />
	 			</p>
	 			<?php if ( !$accessToken ) { ?>
			<p><?php esc_html_e( 'You need to authorize with Keap to fetch tags.', 'pmpro-infusionsoft' ) ?></p>
	 			<?php } ?>
	 		</form>
	 	</div>

		<?php 
	 	require_once PMPRO_DIR . '/adminpages/admin_footer.php';