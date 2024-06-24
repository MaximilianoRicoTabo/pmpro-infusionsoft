<?php 

    //Require API wrapper class in classes folder
	include_once( PMPRO_INFUSIONSOFT_DIR . '/classes/class-pmprokeap-api-wrapper.php' );

	/**
	 * Add the options page
	 *
	 * @return void
	 * @since TBD
	 *
	 */
	function pmprokeap_admin_add_page() {
		add_options_page( 'PMPro Keap Options', 'PMPro Keap', 'manage_options', 'pmprokeap_options', 'pmprokeap_options_page' );
	}

	add_action( 'admin_menu', 'pmprokeap_admin_add_page' );

	/**
	 * Get settings options for PMPro Keap and and render the markup to save the options
	 *
	 * @return array $options
	 */
	function pmprokeap_options_page() {
		global  $msg, $msgt;

		$options = get_option( 'pmprokeap_options' );

		if( ! empty( $options[ 'api_key' ] ) ) {
			$api_key = $options[ 'api_key' ];
		}

		if( ! empty( $options[ 'api_secret' ] ) ) {
			$api_secret = $options[ 'api_secret' ];
		}

		// Retrieve stored access token
		$accessToken = get_option('keap_access_token');

		?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br></div>
			<h2><?php esc_html_e( 'Keap Integration Options and Settings', 'pmpro-keap' );?></h2>

			<?php if ( ! empty( $msg ) ) { ?>
				<div class="message <?php echo esc_attr( $msgt ); ?>"><p><?php echo esc_html( $msg ); ?></p></div>
			<?php } ?>

			<form action="options.php" method="post">
		<?php
				settings_fields( 'pmprokeap_options' );
				do_settings_sections( 'pmprokeap_options' );
				?>
				<p class="submit topborder">
					<input name="submit" type="submit" class="button-primary" value="<?php esc_html_e('Save Settings', 'pmpro-mailchimp');?>" />
				</p>
			</form>


				<!-- Authorization button -->
				<?php if (!$accessToken): ?>
					<p>You need to authorize with Keap to fetch tags.</p>
					<form method="get" action="">
						<input type="hidden" name="page" value="pmprokeap_options">
						<input type="hidden" name="action" value="authorize_keap">
						<button type="submit" class="button button-primary">Authorize with Keap</button>
					</form>
				<?php else: ?>
					<p>You are authorized with Keap.</p>
					<?php
					// Fetch and display tags if authorized
					$keap = new PMProKeap_Api_Wrapper();
					$keap->set_token( $accessToken );

					$tags = $keap->get_tags();
					?>

					<?php if (!empty($tags['tags'])): ?>
						<h2>Tags</h2>
						<ul>
							<?php foreach ($tags['tags'] as $tag): ?>
								<li><?php echo esc_html($tag['name']); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php else: ?>
						<p>No tags found.</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>



				<!-- Display tags if available -->
			<?php if (!empty($tags)) : ?>
				<h2>Tags</h2>
				<ul>
					<?php foreach ($tags['tags'] as $tag) : ?>
						<li><?php echo esc_html($tag['name']); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Register setting page for PMPro Keap
	 *
	 * @return void
	 * @since TBD
	 */
	function pmprokeap_admin_init() {
		//setup settings
		register_setting( 'pmprokeap_options', 'pmprokeap_options', 'pmprokeap_options_validate' );
		add_settings_section( 'pmprokeap_section_general', 'General Settings', 'pmprokeap_section_general', 'pmprokeap_options' );
		add_settings_field('pmprokeap_api_key', 'Keap API Key', 'pmprokeap_api_key', 'pmprokeap_options', 'pmprokeap_section_general');
		add_settings_field('pmprokeap_api_secret', 'Keap Secret Key', 'pmprokeap_secret_key', 'pmprokeap_options', 'pmprokeap_section_general');
		add_settings_field('pmprokeap_users_tags', 'All Users Tags', 'pmprokeap_users_tags', 'pmprokeap_options', 'pmprois_section_general');

		if ( isset($_GET['action']) && $_GET['action'] == 'authorize_keap' ) {
			$keap = new PMProKeap_Api_Wrapper();
			$authUrl = $keap->getAuthorizationUrl();
			header("Location: $authUrl");
			exit;
		}

		// Handle the OAuth callback
		if (isset($_GET['page']) && $_GET['page'] == 'pmprokeap_options' && isset($_GET['code'])) {
			$keap = new PMProKeap_Api_Wrapper();
			$authorizationCode = $_GET['code'];
			$tokenResponse = $keap->requestToken($authorizationCode);

			if (isset($tokenResponse['access_token'])) {
				// Store the access token securely
				update_option('keap_access_token', $tokenResponse['access_token']);
			} else {
				// Handle token request error
				echo '<div class="error"><p>Error requesting access token: ' . esc_html($tokenResponse['error_description']) . '</p></div>';
			}

			// Redirect to the settings page after processing
			wp_redirect(admin_url('admin.php?page=pmprokeap_options'));
			exit;
		}

	}

	add_action("admin_init", "pmprokeap_admin_init");



	/**
	 * Add the settings title section for the PMPro Keap options page
	 *
	 * @since TBD
	 */
	function pmprokeap_section_general() {
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
	function pmprokeap_api_key() {
		$options = get_option('pmprokeap_options');
		if( !empty($options['api_key'] ) ) {
			$api_key = $options['api_key'];
		} else {
			$api_key = "";
		}
		?>
		<input id='pmprokeap_api_key' name='pmprokeap_options[api_key]' size='80' type='text' value='<?php echo esc_attr( $api_key ) ?>' />
	<?php
	}

	/**
	 * Add the Secret Key settings section for the PMPro Keap options page
	 * 
	 * @return void
	 * @since TBD
	 */
	function pmprokeap_secret_key() {
		$options = get_option('pmprokeap_options');
		if(!empty($options['api_secret'])) {
			$api_secret = $options['api_secret'];
		} else {
			$api_secret = "";
		}
		?>
		<input id='pmprokeap_api_secret' name='pmprokeap_options[api_secret]' size='80' type='text' value='<?php echo esc_attr( $api_secret ) ?>' />
	<?php 
	}
