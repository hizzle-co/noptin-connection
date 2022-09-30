<?php

namespace Noptin\Connection;

/**
 * Connection API Client.
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Connection API Client.
 */
class API_Client {

	/**
	 * Whether the API processes JSON requests.
	 * @var bool
	 */
	protected $is_json = true;

	/**
	 * @var string
	 */
	protected $base_url;

	/**
	 * @var array
	 */
	protected $last_response;

	/**
	 * @var array
	 */
	protected $last_request;

	/**
	 * @param string $resource
	 * @param array $args
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function get( $resource, $args = array() ) {
		return $this->request( 'GET', $resource, $args );
	}

	/**
	 * @param string $resource
	 * @param array $data
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function post( $resource, $data ) {
		return $this->request( 'POST', $resource, $data );
	}

	/**
	 * @param string $resource
	 * @param array $data
	 * @return mixed
	 * @throws Exception
	 */
	public function put( $resource, $data ) {
		return $this->request( 'PUT', $resource, $data );
	}

	/**
	 * @param string $resource
	 * @param array $data
	 * @return mixed
	 * @throws Exception
	 */
	public function patch( $resource, $data ) {
		return $this->request( 'PATCH', $resource, $data );
	}

	/**
	 * @param string $resource
	 * @return mixed
	 * @throws Exception
	 */
	public function delete( $resource ) {
		return $this->request( 'DELETE', $resource );
	}

	/**
	 * @param string $method
	 * @param string $resource
	 * @param array $data
	 *
	 * @return mixed
	 *
	 * @throws \Exception
	 */
	protected function request( $method, $resource, $data = array() ) {
		$this->reset();

		if ( 'https://' !== substr( $resource, 0, 8 ) ) {
			$url = trailingslashit( $this->base_url ) . ltrim( $resource, '/' );
		} else {
			$url = $resource;
		}

		$data = apply_filters( 'noptin_connection_request_data', $this->prepare_data( $data, $method ), $method, $url );
		$args = array(
			'url'       => $url,
			'method'    => $method,
			'headers'   => $this->get_headers(),
			'timeout'   => 10,
			'sslverify' => apply_filters( 'noptin_connection_use_sslverify', true ),
		);

		// Add the data to the request.
		if ( ! empty( $data ) ) {
			if ( in_array( $method, array( 'GET', 'DELETE' ), true ) ) {
				$args['url'] = add_query_arg( $data, $args['url'] );
			} else {
				$args['body'] = $this->is_json ? wp_json_encode( $data ) : $data;
			}
		}

		// Filter the request args.
		$args = apply_filters( 'noptin_connection_request_args', $args, $method, $url );

		// Perform request.
		$response = wp_remote_request( $url, $args );

		// store request & response
		$this->last_request  = $args;
		$this->last_response = $response;

		// Parse Response
		return $this->parse_response( $response );
	}

	/**
	 * Prepares data for sending to the API.
	 *
	 * @param  array $data
	 * @param  string $method
	 * @return array
	 */
	protected function prepare_data( $data, $method ) {
		return $data;
	}

	/**
	* @return array
	*/
	protected function get_headers() {
		global $wp_version;

		$headers = array(
			'User-Agent'   => 'Noptin/' . noptin()->version . '; ' . get_bloginfo( 'url' ),
			'X-WP-VERSION' => $wp_version,
		);

		// Copy Accept-Language from browser headers
		if ( ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$headers['Accept-Language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
		}

		// JSON requests.
		if ( $this->is_json ) {
			$headers['Accept']        = 'application/json';
			$headers['Content-Type']  = 'application/json';
		}

		return $headers;
	}

	/**
	 * @param array|\WP_Error $response
	 *
	 * @return mixed
	 *
	 * @throws \Exception
	 */
	protected function parse_response( $response ) {

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );

		// Set body to "true" in case API returned No Content.
		if ( wp_remote_retrieve_response_code( $response ) < 300 && empty( $body ) ) {
			$body = 'true';
		}

		if ( $this->is_json ) {
			$body = json_decode( $body );
		}

		return $body;
	}

	/**
	 * Empties all data from previous response
	 */
	protected function reset() {
		$this->last_response = null;
		$this->last_request  = null;
	}

	/**
	 * Returns the last response body.
	 *
	 * @return string
	 */
	public function get_last_response_body() {
		return wp_remote_retrieve_body( $this->last_response );
	}

	/**
	 * Returns the last response headers.
	 * @return array
	 */
	public function get_last_response_headers() {
		return wp_remote_retrieve_headers( $this->last_response );
	}

	/**
	 * Returns the last response.
	 * @return array|WP_Error
	 */
	public function get_last_response() {
		return $this->last_response;
	}

	/**
	 * Returns the last request.
	 *
	 * @return array
	 */
	public function get_last_request() {
		return $this->last_request;
	}
}
