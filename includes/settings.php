<?php 

	//Require API wrapper class in classes folder
	include_once( PMPRO_KEAP_DIR . '/classes/class-pmpro-keap-api-wrapper.php' );

	/**
	 * Add the options page
	 *
	 * @return void
	 * @since TBD
	 *
	 */
	function pmpro_keap_admin_add_page() {
		$keap_integration_menu_text = __( 'Keap', 'pmpro-keap' );
		add_submenu_page( 'pmpro-dashboard', $keap_integration_menu_text, $keap_integration_menu_text, 'manage_options',
			'pmpro-keap', 'pmpro_keap_options_page' );
	}

	function pmpro_keap_admin_bar_menu_add_page() {
		//Bail if can't manage options
		if( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wp_admin_bar;
		$keap_integration_menu_text = __( 'Keap', 'pmpro-keap' );
		$wp_admin_bar->add_menu( array(
			'id' => 'pmpro-keap',
			'title' => $keap_integration_menu_text,
			'href' => admin_url( 'admin.php?page=pmpro-keap' ),
			'parent' => 'paid-memberships-pro',
			'meta' => array( 'class' => 'pmpro-keap' )
		) );
	}

	add_action( 'admin_menu', 'pmpro_keap_admin_add_page' );
	add_action( 'admin_bar_menu', 'pmpro_keap_admin_bar_menu_add_page', 1500 );

	/**
	 * Get settings options for PMPro Keap and and render the markup to save the options
	 *
	 * @return array $options
	 */
	function pmpro_keap_options_page() {
		require_once( PMPRO_KEAP_DIR . '/adminpages/settings.php' );
	
	}

	/**
	 * Register setting page for PMPro Keap
	 *
	 * @return void
	 * @since TBD
	 */
	function pmpro_keap_admin_init() {
		//setup settings
		register_setting( 'pmpro_keap_options', 'pmpro_keap_options', 'pmpro_keap_options_validate' );
		add_settings_section( 'pmpro_keap_section_general', 'General Settings', 'pmpro_keap_section_general', 'pmpro_keap_options' );
		add_settings_field( 'pmpro_keap_keap_authorized', 'Keap Authorized', 'pmpro_keap_keap_authorized', 'pmpro_keap_options', 'pmpro_keap_section_general' );
		add_settings_field( 'pmpro_keap_api_key', 'Keap API Key', 'pmpro_keap_api_key', 'pmpro_keap_options', 'pmpro_keap_section_general' );
		add_settings_field( 'pmpro_keap_api_secret', 'Keap Secret Key', 'pmpro_keap_secret_key', 'pmpro_keap_options', 'pmpro_keap_section_general' );
		add_settings_field( 'pmpro_keap_users_tags', 'All Users Tags', 'pmpro_keap_users_tags', 'pmpro_keap_options', 'pmprois_section_general' );
		if (  get_option( 'pmpro_keap_access_token' ) ) {
			add_settings_section( 'pmpro_keap_section_levels', 'Levels Tags', 'pmpro_keap_section_levels', 'pmpro_keap_options' );
		}
	
		if ( isset($_GET['action']) && $_GET['action'] == 'authorize_keap' ) {
			$keap = PMPro_Keap_Api_Wrapper::get_instance();
			$authUrl = $keap->pmpro_keap_get_authorization_url();
			header( "Location: $authUrl" );
			exit;
		}

		// Handle the OAuth callback
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'pmpro-keap' && isset( $_GET['code'] ) ) {
			$keap = PMPro_Keap_Api_Wrapper::get_instance();
			$authorization_code = $_GET['code'];
			$token_response = $keap->pmpro_keap_request_token( $authorization_code );

			if (isset($token_response['access_token'])) {
				// Store the access token securely
				update_option('pmpro_keap_access_token', $token_response['access_token']);
				update_option('pmpro_keap_refresh_token', $tokenResponse['refresh_token']);

			} else {
				// Handle token request error
				echo '<div class="error"><p>Error requesting access token: ' . esc_html($token_response['error_description']) . '</p></div>';
			}

			// Redirect to the settings page after processing
			wp_redirect( admin_url( 'admin.php?page=pmpro-keap' ) );
			exit;
		}
	}

	add_action("admin_init", "pmpro_keap_admin_init");



	/**
	 * Add the settings title section for the PMPro Keap options page
	 *
	 * @since TBD
	 */
	function pmpro_keap_section_general() {
		?>
		<p><?php esc_html_e('Settings for the Keap Integration.', 'pmpro-keap');?></p>
		<?php
	}

	/**
	 * Add the API Key settings section for the PMPro Keap options page
	 * 
	 * @return void
	 * @since TBD
	 */
	function pmpro_keap_api_key() {
		$options = get_option( 'pmpro_keap_options' );
		if( !empty($options['api_key'] ) ) {
			$api_key = $options['api_key'];
		} else {
			$api_key = "";
		}
		?>
		<input id='pmpro_keap_api_key' name='pmpro_keap_options[api_key]' size='80' type='text' value='<?php echo esc_attr( $api_key ) ?>' />
	<?php
	}

	/**
	 * Add the Secret Key settings section for the PMPro Keap options page
	 * 
	 * @return void
	 * @since TBD
	 */
	function pmpro_keap_secret_key() {
		$options = get_option( 'pmpro_keap_options' );
		if(!empty($options['api_secret'])) {
			$api_secret = $options['api_secret'];
		} else {
			$api_secret = "";
		}
		?>
		<input id='pmpro_keap_api_secret' name='pmpro_keap_options[api_secret]' size='80' type='text' value='<?php echo esc_attr( $api_secret ) ?>' />
	<?php
	}

	/**
	 * Add the Users Tags settings section for the PMPro Keap options page
	 * 
	 * @return void
	 * @since TBD
	 */
	function pmpro_keap_section_levels() {
		?>
		<p>
			<?php esc_html_e('For each level below, choose the tags which should be added 
			to the contact when a new user registers or switches levels.', 'pmpro-keap'); ?>
		</p>
		<table class="<?php echo esc_attr( 'form-table' ) ?>">
			<?php
				$levels = pmpro_getAllLevels( true, true );
				$all_tags = pmpro_keap_get_tags();
				foreach( $levels as $level ) {
					$tags = pmpro_keap_get_tags_for_level( $level->id );
			?>
					<tr>
						<th>
							<?php echo esc_html( $level->name );?>
						</th>
						<td>
							<?php
								if( empty( $all_tags ) ) {
									?>
									<p><?php esc_html_e( 'No tags found.', 'pmpro-keap' );?></p>
									<?php
								} else {
									?>
							<select name="pmpro_keap_options[levels][<?php echo esc_attr( $level->id );?>][]" multiple="yes">
								<?php
									foreach( $all_tags as $tag ) {
								?>
										<option value="<?php echo esc_attr( $tag[ 'id' ] );?>" 
											<?php if( in_array( $tag[ 'id' ], $tags ) ) { ?>
													selected="selected"
												<?php } ?>>
											<?php echo esc_html( $tag [ 'name' ] );?>
										</option>
									<?php
									}
								?>
							</select>
							<?php
								}
							?>
						</td>
					</tr>

		<?php
		}
		?>
		</tbody>
		</table>
		<?php
	}
	/**
	 * Get the tags for a specific level
	 *
	 * @param int $level_id The level ID
	 * @return array The tags for the level
	 * @since TBD
	 */
	function pmpro_keap_get_tags_for_level( $level_id ) {
		$options = get_option( 'pmpro_keap_options' );
		if( !empty( $options[ 'levels' ][ $level_id ] ) ) {
			return $options[ 'levels' ][ $level_id ];
		} else {
			return array();
		}
	}

	/**
	 * Get all Keap tags
	 *
	 * @return array The tags.
	 * @since TBD 
	 */
	function pmpro_keap_get_tags() {
		$keap = PMPro_Keap_Api_Wrapper::get_instance();
		$tags = $keap->pmpro_keap_get_tags();
		//bail if no tags
		if( empty( $tags[ 'tags' ] ) ) {
			return array();
		}
		return $tags['tags'];
	}

	/**
	 * Show either or not the user is authorized with Keap
	 *
	 * @since TBD
	 */
	function pmpro_keap_keap_authorized() {
		$accessToken = get_option( 'pmpro_keap_access_token' );
		if ( $accessToken ) {
			?>
			<span class="<?php echo esc_attr( 'pmpro_tag pmpro_tag-has_icon pmpro_tag-active pmpro-keap-tag' ) ?>">
				<?php esc_html_e( 'Authorized', 'pmpro-keap' ); ?>
			</span>
			<?php
		return;
		} 
		?>
		<span class="<?php echo esc_attr( 'pmpro_tag pmpro_tag-has_icon pmpro_tag-inactive pmpro-keap-tag' ) ?>">
			<?php esc_html_e( 'Not Authorized', 'pmpro-keap' ); ?>
		</span>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=pmpro-keap&action=authorize_keap' ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Authorize with Keap', 'pmpro-keap' ) ?>

		<?php
	}
?>
