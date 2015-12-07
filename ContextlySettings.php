<?php
/**
 * User: Andrew Nikolaenko
 * Date: 2/20/13
 * Time: 10:54 AM
 */

class ContextlySettings {

    const GENERAL_SETTINGS_KEY      = 'contextly_options_general';
    const API_SETTINGS_KEY          = 'contextly_options_api';
    const ADVANCED_SETTINGS_KEY     = 'contextly_options_advanced';
    const OPTIONS_KEY               = 'contextly_options';
    const PLUGIN_NAME               = 'contextly-linker';

	const MSG_ERROR_TYPE            = 'error';
	const MSG_SUCCESS_TYPE          = 'updated';

	const MSG_SETTINGS_SAVED        = 'Settings saved.';

    public $tabs                    = array();

    public function init() {
        $this->initPluginSettingsLink();
        $this->initWPSettings();
    }

    private function initPluginSettingsLink() {
        add_filter( 'plugin_action_links', array( $this, 'displaySettingsLink' ), 10, 2 );
	    add_action( 'admin_notices', array( $this, 'checkApiSettings' ) );
    }

    private function initWPSettings() {
        add_action( 'admin_menu', array( $this, 'addSettings' ) );
        add_action( 'admin_init', array( $this, 'registerSettings' ), 1 );
    }

    public function displaySettingsLink( $links, $file ) {
        if ( strpos( $file, self::PLUGIN_NAME ) !== false ) {
            $links[] = "<a href='admin.php?page=contextly_options'>" . __( 'Settings' ) . "</a>";
        }

        return $links;
    }

    public function addSettings() {
        add_options_page( 'Contextly', 'Contextly', 'manage_options', self::OPTIONS_KEY, array( $this, 'displaySettings' ) );
    }

    public function registerSettings() {
        register_setting( self::GENERAL_SETTINGS_KEY, self::GENERAL_SETTINGS_KEY, array( $this, 'validateGeneral' ) );
        register_setting( self::API_SETTINGS_KEY, self::API_SETTINGS_KEY, array( $this, 'validateApi' ) );
        register_setting( self::ADVANCED_SETTINGS_KEY, self::ADVANCED_SETTINGS_KEY, array( $this, 'validateAdvanced' ) );

        add_settings_section( 'api_section', 'API Settings', array( $this, 'apiLayoutSection' ), self::API_SETTINGS_KEY );
        add_settings_field( 'api_key', 'API Key', array( $this, 'apiKeyInput' ), self::API_SETTINGS_KEY, 'api_section');

        add_settings_section( 'main_section', 'Single Link Button', array(), self::ADVANCED_SETTINGS_KEY );
        add_settings_field( 'link_type_override', 'Override', array( $this, 'settingsOverride' ), self::ADVANCED_SETTINGS_KEY, 'main_section' );
        add_settings_field( 'link_type_default', 'Default', array( $this, 'settingsDefault' ), self::ADVANCED_SETTINGS_KEY, 'main_section' );

        add_settings_section( 'advanced_section', 'Layout Settings', array( $this, 'settingsLayoutSection' ), self::ADVANCED_SETTINGS_KEY );

        add_settings_section( 'display_section', 'Main Settings', array(), self::ADVANCED_SETTINGS_KEY );
	    add_settings_field( 'display_control', 'Display Contextly Widgets For Post Types:', array( $this, 'settingsDisplayFor' ), self::ADVANCED_SETTINGS_KEY, 'display_section' );
	    //add_settings_field( 'kit_cdn', 'Load Kit resources from CDN:', array( $this, 'settingsDisplayKitCdn' ), self::ADVANCED_SETTINGS_KEY, 'display_section' );
	    add_settings_field( 'publish_confirmation', 'Prompt to Choose Related Posts before publishing:', array( $this, 'settingsDisplayPublishConfirmation' ), self::ADVANCED_SETTINGS_KEY, 'display_section' );

	    $this->tabs[ self::GENERAL_SETTINGS_KEY ] = __( 'General' );
	    $this->tabs[ self::API_SETTINGS_KEY ] = __( 'API Key' );
        $this->tabs[ self::ADVANCED_SETTINGS_KEY ] = __( 'Advanced' );
    }

	private function validateApiKeyRegexp( $api_key ) {
		return preg_match( "/^[a-zA-Z0-9_]+-[a-zA-Z0-9#*;]+$/", $api_key );
	}

	public function validateApi( $input ) {
		$input['api_key'] = sanitize_text_field($input['api_key']);

		if ( !$input['api_key'] ) {
			$this->showMessage( self::MSG_ERROR_TYPE, 'API Key can not be empty.' );
		} elseif ( !$this->validateApiKeyRegexp( $input['api_key'] ) ) {
			$this->showMessage( self::MSG_ERROR_TYPE, 'Invalid characters in API Key.' );
		} else {
			$this->showMessage( self::MSG_SUCCESS_TYPE, self::MSG_SETTINGS_SAVED );
		}

		return $input;
	}

	public function validateAdvanced( $input ) {
		$input['target_id'] = trim( wp_filter_nohtml_kses( $input['target_id'] ) );

		return $input;
	}

	public function validateGeneral( $input ) {
		if ( !is_array( $input['display_type'] ) || count( $input['display_type'] ) == 0 ) {
			$this->showMessage( self::MSG_ERROR_TYPE, 'At least one of post type need to be selected.' );
		} else {
			$this->showMessage( self::MSG_SUCCESS_TYPE, self::MSG_SETTINGS_SAVED );
		}

		return $input;
	}

	private function showMessage( $type, $message ) {
		add_settings_error(
	        'contextlyMessageId',
	        esc_attr( 'settings_updated' ),
			__( $message ),
	        $type
	    );
	}

	private function showAdminMessage( $message, $error = false ) {
		if ($error) {
			$class = 'error';
		} else {
			$class = 'updated';
		}

		echo '<div ' . ( $error ? 'id="contextly_warning" ' : '') . 'class="' . esc_attr( $class ) . ' fade' . '"><p>'. $message . '</p></div>';
	}

	private function getWPPluginSettingsUrl( $tab = 'contextly_options_api' ) {
		return admin_url( 'admin.php?page=contextly_options&tab=' . $tab );
	}

	private function getContextlyBaseUrl( $page_type ) {
		$url_params = array(
			'type'              => $page_type,
			'blog_url'          => site_url(),
			'blog_title'        => get_bloginfo("name"),
			'cms_settings_page' => $this->getWPPluginSettingsUrl(),
		);

		$options = get_option( self::API_SETTINGS_KEY );
		if ( isset( $options["api_key"] ) ) {
			$url_params['api_key'] = $options["api_key"];
		}

		// Get MAJOR.MINOR version for the Control Panel.
		$version = ContextlyWpKit::getInstance()
			->version();
		$verison_parsed = ContextlyWpKit::parseVersion( $version );
		if ( $verison_parsed ) {
			$url_params['kit_version'] = $verison_parsed[0] . '.' . $verison_parsed[1];
		}

		return Urls::getMainServerUrl() . 'cms-redirect/?' . http_build_query($url_params, NULL, '&');
	}

    public function displaySettings() {
        $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : self::GENERAL_SETTINGS_KEY;
        ?>
        <script>
	        function open_contextly_page( open_page_url, button_id )
            {
	            var auth_token_attr = 'contextly_access_token';
	            var token_attr = jQuery( '#' + button_id ).attr( auth_token_attr );

	            if ( typeof token_attr !== 'undefined' && token_attr !== false ) {
		            open_page_url += "&" + auth_token_attr + "=" + encodeURIComponent( token_attr );
	            }

                window.open( open_page_url );

	            return false;
            }
            function open_contextly_settings()
            {
	            var open_url = <?php echo json_encode( $this->getContextlyBaseUrl('settings') ) ?>;
	            var button_id = 'contextly-settings-btn';

	            return open_contextly_page( open_url, button_id );
            }

	        function open_contextly_api_page()
	        {
		        var open_url = <?php echo json_encode( $this->getContextlyBaseUrl('') ) ?>;
		        var button_id = 'contextly-api-btn';

		        return open_contextly_page( open_url, button_id );
	        }

	        function open_contextly_registration_page()
	        {
		        var open_url = <?php echo json_encode( $this->getContextlyBaseUrl('') ) ?>;
		        window.open( open_url );

		        return false;
	        }
        </script>
        <div class="wrap">
            <?php $this->displaySettingsTabs(); ?>

	            <?php if ( $tab == self::GENERAL_SETTINGS_KEY ) { ?>
		            <div id='contextly_warnings'></div>
		            <h3>
					    Most of the controls for Contextly are hosted outside Wordpress. Press The Big Settings Button to securely login.
				    </h3>
				    <p>
					    <input type="button" value="The Big Settings Button" class="button button-hero button-primary" style="font-size: 18px;" id="contextly-settings-btn" onclick="open_contextly_settings();" />
				    </p><br />

			    <?php
		                $this->displaySettingsAutoloadStuff( 'contextly-settings-btn', true );
	                }
	            ?>

		        <form action="options.php" method="post">
                    <?php settings_fields( $tab ); ?>
                    <?php do_settings_sections( $tab ); ?>
                    <?php if ( $tab == self::API_SETTINGS_KEY ) { ?>
	                    <style>
		                    span.btn-step-number {
			                    font-size: 22px;
			                    margin-right: 10px;
		                    }
		                    span.btn-area {
			                    line-height: 2em;
		                    }
	                    </style>
	                    <div style="margin-top: 20px;">
		                    <span class="btn-step-number">1.</span>
		                    <span class="btn-area">
			                    <?php submit_button(
				                    'Customize Contextly and Get API Key',
				                    'primary large button-hero',
				                    'button',
				                    null,
				                    array(
					                    'style'     => 'font-size: 18px; background-color: #35b137; background-image: linear-gradient(to bottom, #36a739, #249b27); border-color: #36a739;',
					                    'onclick'   => 'return open_contextly_api_page();',
					                    'id'        => 'contextly-api-btn'
				                    )
			                    ); ?>
		                    <span>
		                </div>
	                    <div style="margin-top: 20px;">
							<span class="btn-step-number">2.</span>
							<span class="btn-area">
			                    <?php submit_button(
				                    'Save API Key',
				                    'primary large button-hero',
				                    'submit',
				                    null,
				                    array(
					                    'style' => 'font-size: 18px;'
				                    )
			                    ); ?>
							</span>
	                    </div>
	                <?php
	                    $this->displaySettingsAutoloadStuff( 'contextly-api-btn' );
                    }
                    elseif ( $tab == self::ADVANCED_SETTINGS_KEY ) { ?>
	                    <?php submit_button( null, 'primary' ); ?>
                    <?php } ?>
                </form>

        </div>
        <?php
    }

	private function displaySettingsAutoloadStuff( $button_id, $disabled_flag = false )
	{
		$options = get_option( self::API_SETTINGS_KEY );

		if ( is_admin() && isset( $options["api_key"] ) && $options["api_key"] )
		{
			$contextly_object = new Contextly();
			$contextly_object->loadContextlyAjaxJSScripts();
			?>
			<script>
				jQuery( document ).ready(
					function () {
						Contextly.SettingsAutoLogin.doLogin( <?php echo json_encode( $button_id ) ?>, <?php echo json_encode( $disabled_flag ) ?> );
					}
				);
			</script>
		<?php
		}
	}

    public function displaySettingsTabs() {
        $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : self::GENERAL_SETTINGS_KEY;

        echo '<h1 class="nav-tab-wrapper">';
        foreach ( $this->tabs as $tab_key => $tab_caption ) {
            $active = $current_tab == $tab_key ? 'nav-tab-active' : '';
	        echo '<a class="nav-tab ' . esc_attr( $active ) . '" href="' . esc_url( '?page=' . self::OPTIONS_KEY . '&tab=' . $tab_key ) . '">' . esc_html( $tab_caption ) . '</a>';
        }
        echo '</h1>';
    }

    public function apiLayoutSection() {
        echo "<div id='contextly_warnings'></div>";
	    echo "<p>In order to communicate securely, we use a shared secret key. You can find your secret API key with button \"Customize Contextly and Get API Key\". Copy and paste it below.</p>";
    }

    public function apiKeyInput() {
	    $options = get_option( self::API_SETTINGS_KEY );
	    $input_style = "";

	    if ( isset( $_GET[ 'api_key' ] ) )
	    {
			$get_api_key = urldecode( $_GET[ 'api_key' ] );

		    if ( !isset( $options["api_key"] ) || $options["api_key"] != $get_api_key )
		    {
			    Contextly::fireAPIEvent( 'contextlyApiInserted', $get_api_key );

			    $options["api_key"] = sanitize_text_field( $get_api_key );
			    $input_style = " style='background-color: #FFEBE8; border-color: #CC0000;'";

			    update_option( self::API_SETTINGS_KEY, $options );
		    }
	    }

	    echo "<label><input name='" . esc_attr( self::API_SETTINGS_KEY ) . "[api_key]' type='text' size='40' value='". esc_attr( $options["api_key"] ) . "' " . $input_style . "/></label>";
    }

	public function checkApiSettings() {
		$options = get_option( self::API_SETTINGS_KEY );

		if ( !$options || !isset( $options["api_key"] ) || !$options["api_key"] || !$this->validateApiKeyRegexp( $options['api_key'] ) ) {
			$this->showAdminMessage( sprintf( 'You need to get your %ssecret key%s before Contextly shows recommendations.', '<a href="' . esc_url( $this->getWPPluginSettingsUrl() ) . '">', '</a>' ), true );
		}
	}

    public function settingsLayoutSection() {
        echo "<p>
			By default, Contextly's main recommendation module is set to show up as the very last object in your post template. For most sites, this is perfect. However, if you have other plugins that come after the body of the text, you can adjust where the main module displays.
			</p>
			<p>
			To set the placement of Contextly main module, simply edit your templates by placing this shortcode where you would like the module to display: [contextly_main_module]
			</p>";
    }

    public function settingsOverride() {
        $options = get_option( self::ADVANCED_SETTINGS_KEY );

        echo "<label>";
	    echo "<input id='link_type_override' name='" . esc_attr( self::ADVANCED_SETTINGS_KEY ) . "[link_type]' type='radio' value='override' " . checked( $options['link_type'], "override", false ) . "/>";
        echo " With this setting, the WordPress link button in the Visual editor is changed to used Contextly to add links to the body of your posts. There is no dedicated button for adding single links through Contextly with this option.";
        echo "</label>";
    }

    public function settingsDefault() {
        $options = get_option( self::ADVANCED_SETTINGS_KEY );
        echo "<label>";
	    echo "<input id='link_type_default' name='" . esc_attr( self::ADVANCED_SETTINGS_KEY ) . "[link_type]' type='radio' value='' " . checked( empty( $options['link_type'] ), true, false ) . "/>";
        echo " With this setting, WordPress's single link button in the Visual editor works as it normally does. The Visual editor bar gets an additional single link button so you can add links to the body of your post using Contextly.";
        echo "</label>";
    }

    public function settingsTargetInput() {
        $options = get_option( self::ADVANCED_SETTINGS_KEY );
        echo "<input id='linker_target_id' name='" . self::ADVANCED_SETTINGS_KEY . "[target_id]' type='text' size='30' value='{$options["target_id"]}' />";
    }

    public function settingsBlockPosition() {
        $options = get_option( self::ADVANCED_SETTINGS_KEY );
        echo "
			<select id='linker_block_position' name='" . self::ADVANCED_SETTINGS_KEY . "[block_position]'>
				<option value='after' " . ($options["block_position"] == "after" ? "selected='selected'" : "") . ">Below</option>
				<option value='before' " . ($options["block_position"] == "before" ? "selected='selected'" : "") . ">Above</option>
			</select>";
    }

    public function settingsDisplayFor() {
	    $values = $this->getWidgetDisplayType();
	    $post_types = get_post_types( '', 'objects' );

	    echo "<table cellpadding='0' cellspacing='0'>";
	    foreach ( $post_types as $post_type ) {
		    if ( $post_type->public ) {
			    echo "<tr><td style='padding: 3px;'>";
			    echo "<input id='post-type-" . esc_attr( $post_type->name ) . "' name='" . esc_attr( self::ADVANCED_SETTINGS_KEY ) . "[display_type][]' type='checkbox' value='" . esc_attr( $post_type->name ) . "' " . checked( in_array( $post_type->name, ( array_values( $values ) ) ), true, false ) . " />";
			    echo "</td><td style='padding: 3px;'><label for='post-type-{$post_type->name}'>";
			    echo esc_html( $post_type->labels->name );
			    echo "</label></td></tr>";
		    }
	    }
	    echo "</table>";
    }

	public function settingsDisplayKitCdn() {
		$checked = $this->getKitCdnValue();
		$this->settingsDisplayAdvancedCheckbox( 'kit_cdn', $checked );
	}

	public function settingsDisplayPublishConfirmation() {
		$checked = $this->getPublishConfirmationValue();
		$this->settingsDisplayAdvancedCheckbox( 'publish_confirmation', $checked );
	}

	protected function settingsDisplayAdvancedCheckbox( $id, $checked ) {
		$control_name = self::ADVANCED_SETTINGS_KEY . "[" . $id . "]";

		echo "
<input type='hidden' name='{$control_name}' value='0' />
<input name='{$control_name}' type='checkbox' value='1' " . ( $checked ? "checked='checked'" : "" ) . " style='margin-left: 3px;'/>";
	}

    public static function getPluginOptions() {
        $options = get_option( self::ADVANCED_SETTINGS_KEY );
        if ( !is_array( $options ) ) {
	        $options = array();
        }

        return $options;
    }

    public function getWidgetDisplayType() {
	    $options = get_option( self::ADVANCED_SETTINGS_KEY );

	    // Hack for previous plugin versions and selected values
	    $values = isset( $options['display_type'] ) ? $options['display_type'] : array();
	    if ( !is_array( $values ) ) {
		    if ( $values == 'all' ) {
			    $values = array( 'post', 'page' );
		    } else {
			    $values = array( $values );
		    }
	    }

	    // Default choice
	    if ( count( $values ) == 0 ) {
		    $values = array('post');
	    }

        return $values;
    }

	public function getPublishConfirmationValue() {
		$options = get_option( self::ADVANCED_SETTINGS_KEY );

		if ( isset( $options[ 'publish_confirmation' ] ) ) {
			return (bool)$options[ 'publish_confirmation' ];
		}

		return false;
	}

	public function getKitCdnValue() {
		return true;
	}

    public static function isPageDisplayDisabled( $page_id ) {
        $post_flag = get_post_meta( $page_id, '_contextly_display_widgets', true );

        if ( isset( $post_flag ) && $post_flag == 'on' ) {
            return true;
        }

        return false;
    }

    public function changePageDisplay( $post_id, $param ) {
        update_post_meta( $post_id, '_contextly_display_widgets', $param, get_post_meta( $post_id, '_contextly_display_widgets', true ) );
    }

}
