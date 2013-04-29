<?php
/**
 *  API client class for Contextly
 *
 *  @author Meshin Dmitry <0x7ffec@gmail.com>
 */

class Contextly_Api {

    const ACCESS_TOKEN_NAME        = 'Contextly-Access-Token';
    const ACCESS_TOKEN_APP_ID_NAME = 'Contextly-App-ID';

    const SEARCH_TYPE_NOT_EQUAL         = '!=';
    const SEARCH_TYPE_EQUAL             = '=';
    const SEARCH_TYPE_GREATER           = '>';
    const SEARCH_TYPE_LESS              = '<';
    const SEARCH_TYPE_GREATER_EQUAL     = '>=';
    const SEARCH_TYPE_LESS_EQUAL        = '<=';
    const SEARCH_TYPE_LIKE              = '~';
    const SEARCH_TYPE_LIKE_LEFT         = '%~';
    const SEARCH_TYPE_LIKE_RIGHT        = '~%';
    const SEARCH_TYPE_LIKE_BOTH         = '%~%';
    const SEARCH_TYPE_REGEXP            = 'regexp';

    /**
     * @var array
     */
    protected $options = array(
        'server-url'    => '',
        'auth-api'      => '',
        'appID'         => '',
        'appSecret'     => '',
        'SSL_ONLY'      => false
    );

    /**
     * 	Headers to be sent
     *
     * 	@var Array
     */
    protected $headers = array(
        'User-Agent' => 'Contextly 1.0 Api Client'
    );

    /**
     * @var Resource
     */
    protected $connection = null;

    /**
     * @var string
     */
    protected $cookie_file = null;

    /**
     * @var string
     */
    protected $method_name = null;

    /**
     * @var string
     */
    protected $api_name = null;

    /**
     * @var Array
     */
    protected $_params = array();

    /**
     * @var array
     */
    protected $_extraParams = array();

    /**
     * @static
     * @return Contextly_Api
     */
    public static function getInstance() {
        static $instance = null;

        if ( null === $instance ) {
            $instance = new self;
        }

        return $instance;
    }

    /**
     * @return mixed
     */
    public function get() {
        $this->authorize();

        $url = rtrim($this->options['server-url'], "/");
        $url .= "/" . $this->api_name . "/" . $this->method_name . "/";

        $params_string = implode("/", $this->_params);
        if ( $params_string ) {
            $url .= $params_string . "/";
        }

        $headers = $this->headers;
        $headers[ 'Referer' ] = site_url();

        // setting Auth headers with token
        $access_token = Contextly_Session::getInstance()->getAccessToken();

        $headers[ self::ACCESS_TOKEN_NAME ] =  $access_token;
        $headers[ self::ACCESS_TOKEN_APP_ID_NAME ] =  $this->options['appID'];

        if ( $this->isDebug() )
        {
            echo "API Call: {$url}\r\n";
        }

        $response = wp_remote_retrieve_body(
            wp_remote_request(
                $url,
                array(
                    'method' => 'POST',
                    'body'  => $this->_extraParams,
                    'headers' => $headers,
                    'sslverify' => $this->options['SSL_ONLY']
                )
            )
        );

        if ( $this->isDebug() )
        {
            echo "API Response: " . print_r( $response, true ) . "\r\n";
        }
	    
        $response = json_decode($response);

        $this->method_name = null;
        $this->api_name = null;
        $this->_params = array();
        $this->_extraParams = array();

        return $response;
    }

    /**
     * @param $api_name
     * @param $method_name
     * @return Contextly_Api
     */
    public function api( $api_name, $method_name ) {
        $this->method_name = $method_name;
        $this->api_name = $api_name;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getCookieFilename() {
        return $this->cookie_file;
    }

    /**
     * @param $name
     * @param $value
     * @return Contextly_Api
     */
    public function param( $name, $value ) {
        if ( $name && $value ) {
            $this->_params[] = (String)$name;
            $this->_params[] = (String)$value;
        }
        return $this;
    }

    /**
     * @param $name
     * @param $value
     * @return Contextly_Api
     */
    public function extraParam( $name, $value ) {
        if ( $name && $value ) {
            $this->_extraParams[(String)$name] = $value;
        }
        return $this;
    }

    /**
     * @param array $params
     * @return Contextly_Api
     */
    public function params( Array $params ) {
        foreach ( $params as $p_name => $p_value ) {
            $this->param($p_name, $p_value);
        }

        return $this;
    }

    /**
     * @param array $extraparams
     * @return Contextly_Api
     */
    public function extraParams( Array $extraparams ) {
        foreach ( $extraparams as $ep_name => $ep_value ) {
            $this->extraParam( $ep_name, $ep_value );
        }
        return $this;
    }

    /**
     * @param $column
     * @param $type
     * @param $value
     * @return Contextly_Api
     */
    public function searchParam( $column, $type, $value ) {

        if ( !$column || !$type || !$value ) {
            return $this;
        }

        $allowed_types = array(
            self::SEARCH_TYPE_NOT_EQUAL,
            self::SEARCH_TYPE_EQUAL,
            self::SEARCH_TYPE_GREATER,
            self::SEARCH_TYPE_LESS,
            self::SEARCH_TYPE_GREATER_EQUAL,
            self::SEARCH_TYPE_LESS_EQUAL,
            self::SEARCH_TYPE_LIKE,
            self::SEARCH_TYPE_LIKE_LEFT,
            self::SEARCH_TYPE_LIKE_RIGHT,
            self::SEARCH_TYPE_LIKE_BOTH,
            self::SEARCH_TYPE_REGEXP
        );

        if ( !in_array($type, $allowed_types) ) {
            return $this;
        }

        if ( !isset($this->_extraParams['filters']) ) {
            $this->_extraParams['filters'] = '';
        }

        $this->_extraParams['filters'] .= $column . ';' . $type . ';' . urlencode($value) . ';;';

        return $this;
    }

    public function __construct() {
    }

    public function isDebug()
    {
        return isset( $_REQUEST[ 'debug' ] ) && $_REQUEST[ 'debug' ] == 1;
    }

    /**
     * @param array $opts
     * @return Contextly_Api
     */
    public function setOptions( Array $opts ) {
        $this->options = array_merge($this->options, $opts);
        return $this;
    }

    /**
     * @throws Exception
     */
    protected function authorize() {
        $token_raw = null;
        $response = null;

        // getting access token first from session. and check expiration
        if ( !Contextly_Session::getInstance()->isAuthorized() ) {
            // request token
            $url = rtrim($this->options['server-url'], "/");
            $url .= "/" . $this->options['auth-api'] . "/";

            $auth_info = array(
                'appID'         => $this->options['appID'],
                'appSecret'     => $this->options['appSecret']
            );

            $headers = $this->headers;
            $headers[ 'Referer' ] = site_url();

            if ( $this->isDebug() )
            {
                echo "AUTH Call: {$url}\r\n";
                print_r( $auth_info );
            }

            $response = wp_remote_retrieve_body(
                wp_remote_request(
                    $url,
                    array(
                        'method' => 'POST',
                        'body'  => $auth_info,
                        'headers' => $headers,
                        'sslverify' => $this->options['SSL_ONLY']
                    )
                )
            );

            if ( $this->isDebug() )
            {
                echo "AUTH Response: " . print_r( $response, true ) . "\r\n";
            }

            $response = json_decode($response);

            if ( isset( $response->success ) && isset( $response->access_token ) ) {
                $token_raw  = $response->access_token;
                Contextly_Session::getInstance()->saveAccessToken( $token_raw );
            }
        } else {
            $token_raw = Contextly_Session::getInstance()->getAccessToken();
        }

        if ( null == $token_raw || !Contextly_Session::getInstance()->isAuthorized() ) {
            $exception_message = 'Can not authorize with provided API key';
            if ( isset($response->error) )
            {
                $exception_message .= ". " . $response->error;
            }

            $exception_code = 400;
            if ( isset( $response->error_code ) )
            {
                $exception_code = $response->error_code;
            }

            throw new Exception( $exception_message, $exception_code );
        }
    }
}


class Contextly_Session {

    /**
     * @static
     * @return Contextly_Session
     */
    public static  function getInstance() {
        session_start();

        static $i = null;

        if ( null === $i ) {
            $i = new self;
        }

        return $i;
    }

    /**
     * Checking if authorization success
     * @return bool
     */
    public function isAuthorized() {
        $access_token_exists = isset( $_SESSION[ 'AUTH' ][ 'ACCESS_TOKEN' ] );
        $access_token_expires_exists = isset( $_SESSION[ 'AUTH' ][ 'ACCESS_TOKEN_EXPIRES' ] );

        if ( !$access_token_exists || !$access_token_expires_exists ) {
            $this->clearTokenData();
            return false;
        }

        $access_token_expires = (int)$_SESSION[ 'AUTH' ][ 'ACCESS_TOKEN_EXPIRES' ];
        $current_timestamp = time();

        $is_authorized = ($access_token_exists && ( $access_token_expires > $current_timestamp ) );

        if ( !$is_authorized ) {
            $this->clearTokenData();
            return false;
        }

        return true;
    }

    public function clearTokenData() {
        unset( $_SESSION[ 'AUTH' ][ 'ACCESS_TOKEN' ] );
        unset( $_SESSION[ 'AUTH' ][ 'ACCESS_TOKEN_EXPIRES' ] );
        unset( $_SESSION[ 'AUTH' ][ 'ACCESS_TOKEN_RAW' ] );
    }

    /**
     * @return mixed
     */
    public function getAccessToken() {
        return $_SESSION[ 'AUTH' ][ 'ACCESS_TOKEN_RAW' ];
    }

    /**
     * @param $token string
     * @return bool
     */
    public function saveAccessToken( $token ) {
        if ( !$token ) {
            return false;
        }

        $token_parts = explode("-", $token);

        // token is like: asdfasdf;123123 - where first part is signature and the second is expiration timestamp
        if ( count($token_parts) < 2 ) {
            return false;
        }

        $_SESSION[ 'AUTH' ][ 'ACCESS_TOKEN' ] = $token;
        $_SESSION[ 'AUTH' ][ 'ACCESS_TOKEN_EXPIRES' ] = $token_parts[1];
        $_SESSION[ 'AUTH' ][ 'ACCESS_TOKEN_RAW' ] = $token;

        return true;
    }

}
