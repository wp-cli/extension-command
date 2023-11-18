<?php

namespace WP_CLI;

final class ParseGithubUrlInput {

	/**
	 * The GitHub Releases public api endpoint.
	 *
	 * @var string
	 */
	private $github_releases_api_endpoint = 'https://api.github.com/repos/%s/releases';

	/**
	 * The GitHub latest release url format.
	 *
	 * @var string
	 */
	private $github_latest_release_url = '/^https:\/\/github\.com\/(.*)\/releases\/latest\/?$/';

	/**
	 * Get the latest package version based on a given repo slug.
	 *
	 * @param string $repo_slug
	 *
	 * @return array{ name: string, url: string }|\WP_Error
	 */
	public function get_the_latest_github_version( $repo_slug ) {
		$api_url = sprintf( $this->github_releases_api_endpoint, $repo_slug );
		$token   = getenv( 'GITHUB_TOKEN' );

		$request_arguments = $token ? [ 'headers' => 'Authorization: Bearer ' . getenv( 'GITHUB_TOKEN' ) ] : [];

		$response = \wp_remote_get( $api_url, $request_arguments );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$body         = \wp_remote_retrieve_body( $response );
		$decoded_body = json_decode( $body );

		if ( wp_remote_retrieve_response_code( $response ) === \WP_Http::FORBIDDEN ) {
			return new \WP_Error(
				\WP_Http::FORBIDDEN,
				$this->build_rate_limiting_error_message( $decoded_body )
			);
		}

		if ( null === $decoded_body ) {
			return new \WP_Error( 500, 'Empty response received from GitHub.com API' );
		}

		if ( ! isset( $decoded_body[0] ) ) {
			return new \WP_Error( '400', 'The given Github repository does not have any releases' );
		}

		$latest_release = $decoded_body[0];

		return [
			'name' => $latest_release->name,
			'url'  => $this->get_asset_url_from_release( $latest_release ),
		];
	}

	/**
	 * Get the asset URL from the release array. When the asset is not present, we fallback to the zipball_url (source code) property.
	 *
	 * @param array $release
	 *
	 * @return string|null
	 */
	private function get_asset_url_from_release( $release ) {
		if ( isset( $release->assets[0]->browser_download_url ) ) {
			return $release->assets[0]->browser_download_url;
		}

		if ( isset( $release->zipball_url ) ) {
			return $release->zipball_url;
		}

		return null;
	}

	/**
	 * Get the GitHub repo from the URL.
	 *
	 * @param string $url
	 *
	 * @return string|null
	 */
	public function get_github_repo_from_url( $url ) {
		preg_match( $this->github_latest_release_url, $url, $matches );

		return isset( $matches[1] ) ? $matches[1] : null;
	}

	/**
	 * Build the error message we display in WP-CLI for the API Rate limiting error response.
	 *
	 * @param $decoded_body
	 *
	 * @return string
	 */
	private function build_rate_limiting_error_message( $decoded_body ) {
		return $decoded_body->message . PHP_EOL . $decoded_body->documentation_url . PHP_EOL . 'In order to pass the token to WP-CLI, you need to use the GITHUB_TOKEN environment variable.';
	}
}
