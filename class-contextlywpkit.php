<?php
/**
 * WordPress Kit implementation.
 *
 * @package Contextly Related Links
 * @link https://contextly.com
 */

/**
 * Contextly Kit overrides.
 *
 * @method ContextlyWpApiTransport newApiTransport()
 * @method ContextlyWpSharedSession newApiSession()
 * @method ContextlyWpWidgetsEditor newWidgetsEditor()
 * @method ContextlyWpOverlayPage newWpOverlayPage()
 */
class ContextlyWpKit extends ContextlyKit {

	/**
	 * Get Kit instance.
	 *
	 * @return ContextlyWpKit
	 */
	public static function getInstance() {
		static $instance;

		if ( ! isset( $instance ) ) {
			$config   = self::getDefaultSettings();
			$instance = new self( $config );
		}

		return $instance;
	}

	/**
	 * Get default Kit settings.
	 *
	 * @return ContextlyKitSettings Kit settings object.
	 */
	public static function getDefaultSettings() {
		$config            = new ContextlyKitSettings();
		$config->urlPrefix = plugins_url( 'kit', __FILE__ );

		if ( CONTEXTLY_MODE !== Urls::MODE_LIVE ) {
			$config->mode = CONTEXTLY_MODE;
		}

		$options           = Contextly::get_api_client_options();
		$config->appID     = $options['appID'];
		$config->appSecret = $options['appSecret'];
		$config->cdn       = CONTEXTLY_CDN_VERSION;

		return $config;
	}

	/**
	 * Check if this is HTTPS mode.
	 *
	 * @return bool true or false.
	 */
	public function isHttps() {
		return CONTEXTLY_HTTPS;
	}

	/**
	 * Get Kit classes map.
	 *
	 * @return array classes map.
	 */
	protected function getClassesMap() {
		$map = parent::getClassesMap();

		$map['ApiTransport']  = 'ContextlyWpApiTransport';
		$map['ApiSession']    = 'ContextlyWpSharedSession';
		$map['AssetsPackage'] = 'ContextlyWpAssetsPackage';
		$map['WidgetsEditor'] = 'ContextlyWpWidgetsEditor';
		$map['WpOverlayPage'] = 'ContextlyWpOverlayPage';

		return $map;
	}

	/**
	 * Get Kit loader.
	 *
	 * @return string loader name.
	 */
	public function getLoaderName() {
		// We only override the loader name in dev mode.
		// See ContextlyWpAssetsPackage::getJs() for live mode.
		if ( $this->isDevMode() && CONTEXTLY_MOD !== false ) {
			return 'mods/' . CONTEXTLY_MOD;
		} else {
			return 'loader';
		}
	}

}

/**
 * Assets package.
 *
 * Class ContextlyWpAssetsPackage
 */
class ContextlyWpAssetsPackage extends ContextlyKitAssetsPackage {

	/**
	 * Get JS files.
	 *
	 * @return array array of files.
	 */
	public function getJs() {
		$js = parent::getJs();

		// Since we don't ship aggregated mod configs and can't use the modified loader
		// package, have to replace the asset itself.
		if ( $this->kit->isLiveMode() && CONTEXTLY_MOD !== false ) {
			foreach ( $js as &$name ) {
				if ( 'loader' === $name ) {
					$name = 'mods--' . CONTEXTLY_MOD;
				}
			}
			unset( $name );
		}

		return $js;
	}

}

/**
 * API implementation in WordPress.
 *
 * Class ContextlyWpApiTransport
 */
class ContextlyWpApiTransport implements ContextlyKitApiTransportInterface {

	/**
	 * Performs the HTTP request.
	 *
	 * @param string $method "GET" or "POST".
	 * @param string $url  Request URL.
	 * @param array  $query GET query parameters.
	 * @param array  $data  POST data.
	 * @param array  $headers  List of headers.
	 *
	 * @return ContextlyKitApiResponse response object.
	 */
	public function request( $method, $url, $query = array(), $data = array(), $headers = array() ) {
		// Add query to the URL.
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$result = wp_remote_request(
			$url, array(
				'timeout' => 40,
				'method'  => $method,
				'body'    => $data,
				'headers' => $headers,
				'sslverify' => FALSE
			)
		);

		// Build the response for the kit.
		$response = new ContextlyKitApiResponse();
		if ( is_wp_error( $result ) ) {
			$response->code  = -1;
			$response->error = $result->get_error_message();
		} else {
			$response->code = $result['response']['code'];
			$response->body = wp_remote_retrieve_body( $result );
		}

		return $response;
	}

}

/**
 * Session implementation.
 *
 * Class ContextlyWpSharedSession
 */
class ContextlyWpSharedSession extends ContextlyKitBase implements ContextlyKitApiSessionInterface {

	const TOKEN_OPTION_NAME = 'contextly_access_token';

	/**
	 * Token.
	 *
	 * @var ContextlyKitApiTokenEmpty|mixed|void
	 */
	protected $token;

	/**
	 * Default constructor.
	 *
	 * ContextlyWpSharedSession constructor.
	 *
	 * @param ContextlyWpKit $kit Kit instance.
	 */
	public function __construct( $kit ) {
		parent::__construct( $kit );

		$this->token = $this->loadSharedToken();
	}

	/**
	 * Load API token.
	 *
	 * @return ContextlyKitApiTokenEmpty|mixed|void
	 */
	public function loadSharedToken() {
		$cached = get_option( self::TOKEN_OPTION_NAME );
		if ( ! $cached ) {
			return $this->kit->newApiTokenEmpty();
		} else {
			return $cached;
		}
	}

	/**
	 * Save token implementation.
	 *
	 * @param ContextlyKitApiTokenInterface $token token.
	 */
	public function saveSharedToken( $token ) {
		update_option( self::TOKEN_OPTION_NAME, $token );
	}

	/**
	 * Remove token.
	 */
	public function removeSharedToken() {
		delete_option( self::TOKEN_OPTION_NAME );
	}

	/**
	 * Clear token.
	 */
	public function cleanupToken() {
		$this->token = $this->kit->newApiTokenEmpty();
		$this->removeSharedToken();
	}

	/**
	 * Set token
	 *
	 * @param ContextlyKitApiTokenInterface $token token.
	 */
	public function setToken( $token ) {
		$this->token = $token;
		$this->saveSharedToken( $token );
	}

	/**
	 * Get token.
	 *
	 * @return ContextlyKitApiTokenEmpty|ContextlyKitApiTokenInterface|mixed|void
	 */
	public function getToken() {
		return $this->token;
	}

}

/**
 * Handles the page required for the overlay dialogs.
 *
 * @property ContextlyWpKit $kit
 */
class ContextlyWpOverlayPage extends ContextlyKitBase {

	const MENU_KEY = 'contextly_overlay_dialog';

	/**
	 * Add menu stuff.
	 */
	public function addMenuAction() {
		add_action( 'admin_menu', array( $this, 'registerPage' ) );
	}

	/**
	 * Register page.
	 */
	public function registerPage() {
		// Register back-end callback without adding it to the menu.
		add_submenu_page( '', '', '', 'edit_posts', self::MENU_KEY, array( $this, 'display' ) );
	}

	/**
	 * Display editor stuff.
	 */
	public function display() {
		global $contextly;

		if ( isset( $_GET['editor-type'] ) ) { // WPCS: CSRF ok. Input var okay.
			$type = sanitize_text_field( wp_unslash( $_GET['editor-type'] ) ); // WPCS: CSRF ok. Input var okay.

			if ( ! in_array( $type, array( 'link', 'snippet', 'sidebar' ), true ) ) {
				$contextly->return404();
			}

			$overrides = $this->kit->newOverridesManager( $contextly->get_kit_settings_overrides() )
				->compile();

			print $this->kit->newOverlayDialog( $type )
				->render(
					array(
						'loader' => $this->kit->getLoaderName(),
						'code'   => $overrides,
					)
				); // WPCS: XSS ok.
		}
		exit;
	}

}

/**
 * Widget editor stuff.
 *
 * Class ContextlyWpWidgetsEditor
 */
class ContextlyWpWidgetsEditor extends ContextlyKitWidgetsEditor {

	/**
	 * Register editor AJAX actions.
	 */
	public function addAjaxActions() {
		add_action( 'wp_ajax_contextly_widgets_editor_request', array( $this, 'handleAjaxAction' ) );
	}

	/**
	 * Handle editor AJAX action.
	 */
	public function handleAjaxAction() {
		$result = array();

		if ( isset( $_POST['post_id'] ) ) { // WPCS: CSRF ok.
			$post_id = sanitize_text_field( wp_unslash( $_POST['post_id'] ) ); // WPCS: CSRF ok. Input var okay.

			check_ajax_referer( "contextly-post-$post_id", 'nonce' );

			if ( ! isset( $_POST['method'] ) ) { // Input var okay.
				$GLOBALS['contextly']->return404();
			}
			$method = sanitize_text_field( wp_unslash( $_POST['method'] ) ); // Input var okay.

			if ( isset( $_POST['params'] ) ) { // WPCS: CSRF ok.
				$params = $this->sanitize_array( wp_unslash( $_POST['params'] ) ); // WPCS: CSRF ok. Input var okay.
			} else {
				$params = array();
			}
			$params['custom_id'] = $post_id;

			try {
				$result = $this->handleRequest( $method, $params );
			} catch ( ContextlyKitApiException $e ) {
				if ( isset( $e->getApiResult()->error_code ) && $e->getApiResult()->error_code === 413 ) {
					// Access token not exists, try to remove it.
					$this->kit->newApiSession()->cleanupToken();
				}

				if ( CONTEXTLY_MODE !== Urls::MODE_LIVE ) {
					$message = (string) $e;
				} else {
					$message = null;
				}
				$GLOBALS['contextly']->return500( $message );
			} catch ( Exception $e ) {
				Contextly::fire_api_event( 'handleAjaxAction', wp_json_encode( $e ) );

				if ( CONTEXTLY_MODE !== Urls::MODE_LIVE ) {
					$message = (string) $e;
				} else {
					$message = null;
				}
				$GLOBALS['contextly']->return500( $message );
			}
		}

		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( $result );

		exit;
	}

	/**
	 * Sanitize array data.
	 *
	 * @param array $array array for anitize.
	 * @return array sanitized array.
	 */
	private function sanitize_array( &$array ) {
		foreach ( $array as &$value ) {
			if ( ! is_array( $value ) ) {
				$value = sanitize_text_field( $value );
			} else {
				$this->sanitize_array( $value );
			}
		}

		return $array;
	}

}
