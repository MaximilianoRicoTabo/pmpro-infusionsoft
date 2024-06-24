<?php

class PMProKeap_Api_Wrapper {

	private $clientId;
	private $clientSecret;
	private $redirectUri;
	private $token;

	// Define constants for URLs 
	const AUTHORIZATION_URL = 'https://accounts.infusionsoft.com/app/oauth/authorize';
	const TOKEN_URL = 'https://api.infusionsoft.com/token';
	const BASE_API_URL = 'https://api.infusionsoft.com/crm/rest/v1/';
	const REDIRECT_URI = 'options-general.php?page=pmprokeap_options';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$options = get_option( 'pmprokeap_options' );
		$this->clientId = $options[ 'api_key' ];
		$this->clientSecret = $options[ 'api_secret' ];
		$this->redirectUri = admin_url( self::REDIRECT_URI );
	}

	/**
	 * Get the authorization URL
	 *
	 * @return string The URL to request authorization.
	 * @since TBD
	 */
	public function getAuthorizationUrl() {
		$query = http_build_query([
			'client_id' => $this->clientId,
			'redirect_uri' => $this->redirectUri,
			'response_type' => 'code',
			'scope' => 'full'
		]);

		return self::AUTHORIZATION_URL . "?$query";
	}

	public function requestToken( $authorizationCode ) {

		$postFields = http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $authorizationCode,
            'redirect_uri' => $this->redirectUri
        ]);

		$headers = [
			'Content-Type: application/x-www-form-urlencoded',
			'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret)
		];


		// Debugging: Dump the request details
		echo '<pre>';
		echo "Request URL: " . self::TOKEN_URL . "\n";
		echo "Post Fields: \n";
		var_dump($postFields);
		echo "Headers: \n";
		var_dump($headers);
		echo '</pre>';

        $response = $this->makeCurlRequest(self::TOKEN_URL, 'POST', $postFields, $headers);

        if (isset($response['access_token'])) {
            $this->token = $response['access_token'];
            update_option('keap_access_token', $this->token); // Store the token
        }

        return $response;
    }

	public function makeRequest($method, $endpoint, $data = null)
	{
		$url = self::BASE_API_URL . $endpoint;
		$headers = [
            "Authorization: Bearer $this->token",
            "Content-Type: application/json"
        ];

        return $this->makeCurlRequest($url, $method, $data ? json_encode($data) : null, $headers);
	}

	private function makeCurlRequest($url, $method, $data = null, $headers = [])
{
    $ch = curl_init();

    switch ($method) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, 1);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            break;
        default: // GET
            if ($data) {
                $url = sprintf("%s?%s", $url, http_build_query($data));
            }
            break;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignore SSL certificate verification
    curl_setopt($ch, CURLOPT_HEADER, false);

    $result = curl_exec($ch);

    if ($result === false) {
        echo 'Curl error: ' . curl_error($ch);
    } 

    curl_close($ch);

    return json_decode($result, true);
}

	/**
	 * Get all tags.
	 * 
	 * @return array The tags.
	 * @since TBD
	 */
	public function get_tags() {
        return $this->makeRequest('GET', 'tags');
    }

	//getters for private attributes
	public function get_token() {
		return $this->token;
	}

	public function get_clientId() {
		return $this->clientId;
	}

	public function get_clientSecret() {
		return $this->clientSecret;
	}

	public function get_redirectUri() {
		return $this->redirectUri;
	}

	public function set_token( $token ) {
		$this->token = $token;
	}

}
