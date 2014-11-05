<?php

/**
 * Contextly Kit overrides.
 *
 * @method ContextlyWpApiTransport newApiTransport()
 * @method ContextlyWpSharedSession newApiSession()
 * @method ContextlyWpWidgetsEditor newWidgetsEditor()
 * @method ContextlyWpAssetsRenderer newWpAssetsRenderer()
 * @method ContextlyWpOverlayPage newWpOverlayPage()
 */
class ContextlyWpKit extends ContextlyKit {

	/**
	 * @return ContextlyWpKit
	 */
	public static function getInstance() {
		static $instance;

		if ( !isset( $instance ) ) {
			$config   = self::getDefaultSettings();
			$instance = new self( $config );
		}

		return $instance;
	}

	public static function getDefaultSettings() {
		$config              = new ContextlyKitSettings();
		$config->urlPrefix   = plugins_url('kit', __FILE__);

		if ( CONTEXTLY_MODE !== Urls::MODE_LIVE ) {
			$config->mode = CONTEXTLY_MODE;
		}

		$options = Contextly::getAPIClientOptions();
		$config->appID = $options['appID'];
		$config->appSecret = $options['appSecret'];

		$settings = new ContextlySettings();
		$config->cdn = $settings->getKitCdnValue();

		return $config;
	}

	function isHttps() {
		return CONTEXTLY_HTTPS;
	}

	protected function getClassesMap() {
		$map = parent::getClassesMap();

		$map['ApiTransport'] = 'ContextlyWpApiTransport';
		$map['ApiSession'] = 'ContextlyWpSharedSession';
		$map['WidgetsEditor'] = 'ContextlyWpWidgetsEditor';
		$map['WpAssetsRenderer'] = 'ContextlyWpAssetsRenderer';
		$map['WpOverlayPage'] = 'ContextlyWpOverlayPage';

		return $map;
	}

}

class ContextlyWpApiTransport implements ContextlyKitApiTransportInterface {

	/**
	 * Performs the HTTP request.
	 *
	 * @param string $method
	 *   "GET" or "POST".
	 * @param string $url
	 *   Request URL.
	 * @param array  $query
	 *   GET query parameters.
	 * @param array  $data
	 *   POST data.
	 * @param array  $headers
	 *   List of headers.
	 *
	 * @return ContextlyKitApiResponse
	 */
	public function request( $method, $url, $query = array(), $data = array(), $headers = array() ) {
		// Add query to the URL.
		if (!empty($query)) {
			$url = add_query_arg( $query, $url );
		}

		$result = wp_remote_request( $url, array(
			'timeout'   => 40,
			'method'    => $method,
			'body'      => $data,
			'headers'   => $headers,
			'sslverify' => false,
		) );

		// Build the response for the kit.
		$response = new ContextlyKitApiResponse();
		if ( is_wp_error( $result ) ) {
			$response->code = -1;
			$response->error = $result->get_error_message();
		} else {
			$response->code = $result['response']['code'];
			$response->body = wp_remote_retrieve_body( $result );
		}

		return $response;
	}

}

class ContextlyWpSharedSession extends ContextlyKitBase implements ContextlyKitApiSessionInterface {

	const TOKEN_OPTION_NAME   = 'contextly_access_token';

	protected $token;

	public function __construct( $kit ) {
		parent::__construct( $kit );

		$this->token = $this->loadSharedToken();
	}

	public function loadSharedToken() {
		$cached = get_option(self::TOKEN_OPTION_NAME);
		if ( ! $cached ) {
			return $this->kit->newApiTokenEmpty();
		} else {
			return $cached;
		}
	}

	/**
	 * @param ContextlyKitApiTokenInterface $token
	 */
	public function saveSharedToken( $token ) {
		update_option(self::TOKEN_OPTION_NAME, $token);
	}

	public function removeSharedToken() {
		delete_option(self::TOKEN_OPTION_NAME);
	}

	public function cleanupToken() {
		$this->token = $this->kit->newApiTokenEmpty();
		$this->removeSharedToken();
	}

	public function setToken( $token ) {
		$this->token = $token;
		$this->saveSharedToken( $token );
	}

	public function getToken() {
		return $this->token;
	}

}

class ContextlyWpAssetsRenderer extends ContextlyKitAssetsRenderer {

	public function resourceHandle($key) {
		return 'contextly-kit-' . str_replace( '/', '-', $key );
	}

	/**
	 * Returns "version" parameter suitable for wp_enqueue_style() and
	 * wp_enqueue_script().
	 */
	protected function getAssetsVersion() {
		if ($this->kit->isCdnEnabled()) {
			return NULL;
		}
		else {
			return $this->kit->version();
		}
	}

	public function renderCss() {
		$version = $this->getAssetsVersion();
		foreach ($this->assets->buildCssUrls() as $key => $url) {
			wp_enqueue_style($this->resourceHandle( $key ), $url, array(), $version);
		}
	}

	public function renderJs() {
		$version = $this->getAssetsVersion();
		foreach ($this->assets->buildJsUrls() as $key => $url) {
			wp_enqueue_script( $this->resourceHandle( $key ), $url, array(), $version, true );
		}
	}

	public function renderTpl() {
		// TODO Implement for widgets rendering later.
	}

	public function renderAll() {
		$this->renderJs();
		$this->renderCss();
		$this->renderTpl();
	}

}

/**
 * Handles the page required for the overlay dialogs.
 */
class ContextlyWpOverlayPage extends ContextlyKitBase {

	const MENU_KEY = 'contextly_overlay_dialog';

	public function addMenuAction() {
		add_action( 'admin_menu', array( $this, 'registerPage' ) );
	}

	public function registerPage() {
		// Register back-end callback without adding it to the menu.
		add_submenu_page( '', '', '', 'edit_posts', self::MENU_KEY, array( $this, 'display' ) );
	}

	public function display() {
		$type = $_GET['editor-type'];
		if (!in_array( $type, array( 'link', 'snippet', 'sidebar' ), TRUE )) {
			$GLOBALS['contextly']->return404();
		}

		print $this->kit->newOverlayDialog($type)
				->render();
		exit;
	}

}

class ContextlyWpWidgetsEditor extends ContextlyKitWidgetsEditor {

	public function addAjaxActions() {
		add_action( 'wp_ajax_contextly_widgets_editor_request', array( $this, 'handleAjaxAction' ) );
	}

	public function handleAjaxAction() {
		$post_id = $_POST['post_id'];
		check_ajax_referer( "contextly-post-$post_id", 'nonce' );

		if (!isset($_POST['method'])) {
			$GLOBALS['contextly']->return404();
		}
		$method = $_POST['method'];

		if (isset($_POST['params'])) {
			$params = $_POST['params'];
		}
		else {
			$params = array();
		}
		$params['custom_id'] = $post_id;

		try {
			$result = $this->handleRequest( $method, $params );
		}
		catch (Exception $e) {
			Contextly::fireAPIEvent( 'handleAjaxAction', print_r( $e, true ) );

			if (CONTEXTLY_MODE !== Urls::MODE_LIVE) {
				$message = (string) $e;
			}
			else {
				$message = NULL;
			}
			$GLOBALS['contextly']->return500( $message );
		}

		@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo json_encode( $result );

		exit;
	}

}
