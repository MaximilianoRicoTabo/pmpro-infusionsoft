<?php

/**
 * PMPro_Keap API Wrapper
 */
class PMPro_Keap_Api_Wrapper {

	private $clientId;
	private $clientSecret;
	private $redirectUri;
	private $token;
	private static $instance = null;

	// Define constants for URLs 
	const AUTHORIZATION_URL = 'https://accounts.infusionsoft.com/app/oauth/authorize';
	const TOKEN_URL = 'https://api.infusionsoft.com/token';
	const BASE_API_URL = 'https://api.infusionsoft.com/crm/rest/v1/';
	const REDIRECT_URI = 'admin.php?page=pmpro-keap';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$options = get_option( 'pmpro_keap_options' );
		if ( ! empty( $options ) ) {
			$this->clientId = $options[ 'api_key' ];
			$this->clientSecret = $options[ 'api_secret' ];
			$this->redirectUri = admin_url( self::REDIRECT_URI );
			$this->token = get_option( 'pmpro_keap_access_token' );
		}
	}

	/**
	 * Singleton pattern, return a new instance only if not already created.
	 *
	 * @return PMPro_Keap_Api_Wrapper The instance. 
	 * @since TBD
	 */
	public static function get_instance() {
		if (self::$instance == null) {
			self::$instance = new PMPro_Keap_Api_Wrapper();
		}
		return self::$instance;
	}

	/**
	 * Get the authorization URL
	 *
	 * @return string The URL to request authorization.
	 * @since TBD
	 */
	public function pmpro_keap_get_authorization_url() {
		$query = build_query([
			'client_id' => $this->clientId,
			'redirect_uri' => urlencode( $this->redirectUri ),
			'response_type' => 'code',
			'scope' => 'full'
		]);
		
		return self::AUTHORIZATION_URL . "?$query";
	}

	/**
	 * Request the token.
	 *
	 * @param string $authorizationCode Keap authorization code, it comes in the response of the authorization request.
	 * @return array The response.
	 * @since TBD
	 */
	public function pmpro_keap_request_token( $authorizationCode ) {

		$postFields = build_query(
			array(
				'client_id' => $this->clientId,
				'client_secret' => $this->clientSecret,
				'code' => $authorizationCode,
				'grant_type' => 'authorization_code',
				'redirect_uri' => $this->redirectUri
			)
		);

		$headers = array(
			'Content-Type' => 'application/x-www-form-urlencoded',
			'Authorization' => 'Basic ' . base64_encode( $this->clientId . ':' . $this->clientSecret )
		);

		$response = $this->pmpro_keap_make_curl_request(self::TOKEN_URL, 'POST', $postFields, $headers);

		if ( isset( $response[ 'access_token' ] ) ) {
			$this->token = sanitize_text_field( $response[ 'access_token' ] );
			update_option( 'pmpro_keap_access_token', $this->token );
			update_option( 'pmpro_keap_refresh_token', sanitize_text_field( $response[ 'refresh_token' ] ) );
		}

		return $response;
	}

	/**
	 * Refresh the token.
	 *
	 * @param string $refresh_token The refresh token.
	 * @return array The response.
	 * @since TBD
	 */
	public function pmpro_keap_refresh_token( $refresh_token ) {
        $postFields = build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ]);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode( $this->clientId . ':' . $this->clientSecret )
        ];

        $response = $this->pmpro_keap_make_curl_request( self::TOKEN_URL, 'POST', $postFields, $headers );

        if ( isset( $response[ 'access_token' ] ) ) {
            $this->token = sanitize_text_field( $response[ 'access_token' ] );
            update_option( 'pmpro_keap_access_token', $this->token );
            update_option( 'pmpro_keap_refresh_token', sanitize_text_field( $response[ 'refresh_token' ] ) );
        } else {
			// Seems we lost authorization, clear the token
			update_option( 'pmpro_keap_access_token', '' );
		}

        return $response;
    }

	/**
	 * Perform a HTTP request.
	 *
	 * @param string $method The method.( GET, PUT, POST, DELETE ) 
	 * @param string $endpoint The endpoint.
	 * @param array $data The data.
	 * @return array The response.
	 * @since TBD
	 */
	private function pmpro_keap_make_request( $method, $endpoint, $data = null ) {
		$url = self::BASE_API_URL . $endpoint;

		$headers = [
			"Authorization" => "Bearer " . $this->token,
			"Content-Type" => "application/json"
		];

        $response = $this->pmpro_keap_make_curl_request( $url, $method, $data, $headers );
		$error_codes = array( 'keymanagement.service.access_token_expired', 'keymanagement.service.invalid_access_token', 'keymanagement.service.access_token_not_approved' );
		if ( isset($response['fault']) && 
		in_array( $response['fault'][ 'detail' ]['errorcode'], $error_codes ) ) {		
			// Token expired, refresh it
			$refresh_token = get_option( 'pmpro_keap_refresh_token' );
			$refresh_response = $this->pmpro_keap_refresh_token( $refresh_token );
			if ( isset($refresh_response[ 'access_token' ] ) ) {
				// Retry the original request with the new token
				$this->token = $refresh_response[ 'access_token' ];
				$headers = [
					"Authorization" => "Bearer " . $this->token,
					"Content-Type" => "application/json"
				];
	
				$response = $this->pmpro_keap_make_curl_request( $url, $method, $data, $headers );
			} else {
				//It seems that the refresh token is not valid anymore, we need to re-authenticate
				//empty the token from the options
				update_option( 'pmpro_keap_access_token', '' );
				return $refresh_response;
			}
		}

		return $response;
	}

	/**
	 * Get all tags.
	 * 
	 * @return array The tags.
	 * @since TBD
	 */
	public function pmpro_keap_get_tags() {
        return $this->pmpro_keap_make_request('GET', 'tags');
    }

	/**
	 * Get Keap contact given an email
	 * 
	 * @param string $email The email.
	 * @return array The contact.
	 * @since TBD
	 */
	public function pmpro_keap_get_contact_by_email( $email ) {
		return $this->pmpro_keap_make_request( 'GET', 'contacts', ['email' => $email ] );
	}

	/**
	 * Create a Keap contact.
	 *
	 * @param WP_User $user The user.
	 * @return array The response.
	 * @since TBD
	 */
	public function pmpro_keap_add_contact( $user ) {
		//get user by email
		$data = $this->pmpro_keap_format_contact_request( $user );
		return $this->pmpro_keap_make_request('POST', 'contacts', $data );
	}

	/**
	 * Update a Keap contact.
	 *
	 * @param string $contact_id The contact ID.
	 * @param array $email user's email
	 * @return array The response.
	 * @since TBD
	 */
	public function pmpro_keap_update_contact( $contact_id, $user ) {
		$data = $this->pmpro_keap_format_contact_request( $user );
		return $this->pmpro_keap_make_request( 'PATCH', 'contacts/' . $contact_id, $data );
	}

	/**
	 * Assign tags to a contact.
	 *
	 * @param string $contact_id The contact ID.
	 * @param array $tagIds The tag IDs.
	 * @return array The response.
	 * @since TBD
	 */
	public function pmpro_keap_assign_tags_to_contact( $contact_id, $tagIds ) {
		$data = [
			'tagIds' => $tagIds
		];

		return $this->pmpro_keap_make_request( 'POST', 'contacts/' . $contact_id . '/tags', $data );
	}

	//getters for private attributes
	public function pmpro_keap_get_token() {
		return $this->token;
	}
	//getters for private attributes
	public function pmpro_keap_get_clientId() {
		return $this->clientId;
	}

	//getters for private attributes
	public function pmpro_keap_get_clientSecret() {
		return $this->clientSecret;
	}

	//getters for private attributes
	public function pmpro_keap_get_redirectUri() {
		return $this->redirectUri;
	}

	//getters for private attributes
	public function pmpro_keap_set_token( $token ) {
		$this->token = $token;
	}

	/**
	 * Make a cURL request.
	 *
	 * @param string $url The URL.
	 * @param string $method The method.
	 * @param array $data The data.
	 * @param array $headers The headers.
	 * @return array The response.
	 * @since TBD
	 */

	private function pmpro_keap_make_curl_request( $url, $method, $data = null, $headers = [] ) {
		$args = [
			'method'  => $method,
			'headers' => $headers,
			'body'    => null,
		];

		/// Look into reworking this.
		// Set the body based on Content-Type
		if ( $data ) {
			foreach ( $headers as $header ) {
				if ( strpos( $header, 'Content-Type: application/x-www-form-urlencoded' ) !== false ) {
					if ( is_array( $data ) ) {
						$args['body'] = build_query( $data );
					} else {
						$args['body'] = $data;
					}
				} else { /// Do we have to use JSON?
					if ( $method === 'POST' || $method === 'PATCH' ) {
						$args['body'] = json_encode( $data );
					} else {
						$args['body'] = $data;
					}
				}
			}
		}
	
		// Make the request
		$response = wp_remote_request( $url, $args );
	
		// Check for errors
		if ( is_wp_error( $response ) ) {
			return $response;
		}
	
		// Get the response body
		$body = wp_remote_retrieve_body( $response );
	
		return json_decode( $body, true );
	}

	/**
	 *
	 * @param WP_User $user The WordPress user.
	 * @param array $data The additional data we would like to send to Keap.
	 * @return array The formatted contact.
	 * @since TBD
	 */
	private function pmpro_keap_format_contact_request( $user, $data = [] ) {
		/**
		 * Filter the contact request data before sending it to Keap.
		 * @param array $data The additional data we would like to send to Keap.
		 * @param WP_User $user The WordPress user.
		 */
		$data = apply_filters( 'pmpro_keap_format_contact_request', $data, $user );

		$ret = array (
			'email_addresses' => array(
				array(
					'email' => $user->user_email,
					'field' => 'EMAIL1'
				)
			),
			'given_name' => $user->first_name,
			'family_name' => $user->last_name,
		);
		if ( ! empty( $data ) ) {
			$ret = array_merge( $ret, $data );
		}
		return $ret;
	}
}
