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

    public $tabs                    = array();

    public function init() {
        $this->initPluginSettingsLink();
        $this->initWPSettings();
    }

    private function initPluginSettingsLink() {
        add_filter( 'plugin_action_links', array( $this, 'displaySettingsLink' ), 10, 2 );
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
        register_setting( self::GENERAL_SETTINGS_KEY, self::GENERAL_SETTINGS_KEY );
        register_setting( self::API_SETTINGS_KEY, self::API_SETTINGS_KEY, array( $this, 'validateApi' ) );
        register_setting( self::ADVANCED_SETTINGS_KEY, self::ADVANCED_SETTINGS_KEY, array( $this, 'validateAdvanced' ) );


        add_settings_section( 'api_section', 'API Settings', array( $this, 'apiLayoutSection' ), self::API_SETTINGS_KEY );
        add_settings_field( 'api_key', 'API Key', array( $this, 'apiKeyInput' ), self::API_SETTINGS_KEY, 'api_section');

        add_settings_section( 'main_section', 'Single Link Button', array(), self::ADVANCED_SETTINGS_KEY );
        add_settings_field( 'link_type_override', 'Override', array( $this, 'settingsOverride' ), self::ADVANCED_SETTINGS_KEY, 'main_section' );
        add_settings_field( 'link_type_default', 'Default', array( $this, 'settingsDefault' ), self::ADVANCED_SETTINGS_KEY, 'main_section' );

        add_settings_section( 'advanced_section', 'Layout Settings', array( $this, 'settingsLayoutSection' ), self::ADVANCED_SETTINGS_KEY );
        add_settings_field( 'linker_target_id', 'CSS Element ID', array( $this, 'settingsTargetInput' ), self::ADVANCED_SETTINGS_KEY, 'advanced_section' );
        add_settings_field( 'linker_block_position', 'Position', array( $this, 'settingsBlockPosition' ), self::ADVANCED_SETTINGS_KEY, 'advanced_section' );

        add_settings_section( 'display_section', 'Display Contextly Widgets For', array(), self::ADVANCED_SETTINGS_KEY );
	    add_settings_field( 'display_control', 'Post Types:', array( $this, 'settingsDisplayFor' ), self::ADVANCED_SETTINGS_KEY, 'display_section' );

	    $this->tabs[ self::GENERAL_SETTINGS_KEY ] = __( 'General' );
	    $this->tabs[ self::API_SETTINGS_KEY ] = __( 'API' );
        $this->tabs[ self::ADVANCED_SETTINGS_KEY ] = __( 'Advanced' );
    }

	public function validateApi( $input ) {
		$input['api_key'] = sanitize_text_field($input['api_key']);

		if ( !$input['api_key'] ) {
			$this->showMessage( self::MSG_ERROR_TYPE, 'API Key can not be empty.' );
		} else {
			if ( !preg_match( "/^[a-zA-Z0-9_]+-[a-zA-Z0-9#*;]+$/", $input['api_key'] ) ) {
				$this->showMessage( self::MSG_ERROR_TYPE, 'Invalid characters in API Key.' );
			}
		}

		return $input;
	}

	public function validateAdvanced( $input ) {
		$input['target_id'] = trim( wp_filter_nohtml_kses( $input['target_id'] ) );

		if ( !is_array( $input['display_type'] ) || count( $input['display_type'] ) == 0 ) {
			$this->showMessage( self::MSG_ERROR_TYPE, 'At least one of post type need to be selected.' );
		}

		return $input;
	}

	private function showMessage( $type, $message ) {
		add_settings_error(
	        'contextlyErrorId',
	        esc_attr( 'settings_updated' ),
			__( $message ),
	        $type
	    );
	}

	private function getContextlyPluginUrl( $tab = 'contextly_options_api' ) {
		return admin_url( 'admin.php?page=contextly_options&tab=' . $tab );
	}

	private function getContextlyBaseUrl( $page_type = 'settings' ) {
		return Urls::getMainServerUrl() . 'redirect/?type=' . $page_type;
	}

	private function getContextlyRegistrationUrl( $page_type = 'settings' ) {
		return $this->getContextlyBaseUrl( $page_type ) .
			"&blog_url=" . urlencode( site_url() ) .
			"&blog_title=" . urlencode( get_bloginfo("name") ) .
			"&cms_settings_page=" . urlencode( $this->getContextlyPluginUrl() );
	}

    public function displaySettings() {
        $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : self::GENERAL_SETTINGS_KEY;
        ?>
        <script>
            function open_contextly_settings() {
                var base_url = "<?php echo $this->getContextlyBaseUrl() ?>";
				var button_id = '#contextly-settings-btn';
	            var auth_token_attr = 'contextly_access_token';
	            var token_attr = jQuery( button_id ).attr( auth_token_attr );

	            if ( typeof token_attr !== 'undefined' && token_attr !== false ) {
		            base_url += "&" + auth_token_attr + "=" + encodeURIComponent( token_attr );
	            } else {
		            base_url = "<?php echo $this->getContextlyRegistrationUrl() ?>";
	            }

                window.open( base_url );
            }
        </script>
        <div class="wrap">
            <?php $this->displaySettingsTabs(); ?>

            <?php if ( $tab != self::GENERAL_SETTINGS_KEY ) { ?>
                <form action="options.php" method="post">
                    <?php settings_fields( $tab ); ?>
                    <?php do_settings_sections( $tab ); ?>
                    <?php submit_button(); ?>
                </form>
            <?php } else { ?>
                <h3>
	                Click the settings button to securely login to your settings. If that fails, you can still login via Twitter using this <a target="_blank" href="<?php echo $this->getContextlyRegistrationUrl() ?>">link</a>.
                </h3>
                <p>
                    <input type="button" value="Settings" class="button button-hero button-primary" style="font-size: 18px;" id="contextly-settings-btn" onclick="open_contextly_settings();" />
                </p>
	            <?php
	                if ( is_admin() ) {
						$this->displaySettingsAutoloadStuff();
	                }
	            ?>
            <?php } ?>
        </div>
        <?php
    }

	private function displaySettingsAutoloadStuff() {
		$contextly_object = new Contextly();
		$contextly_object->loadContextlyAjaxJSScripts();
		$contextly_object->makeContextlyJSObject(
			array(
				'disable_autoload' => true
			)
		);

		?>
		<script>
			jQuery( document ).ready(
				function () {
					Contextly.SettingsAutoLogin.getInstance().doLogin();
				}
			);
		</script>
		<?php
	}

    public function displaySettingsTabs() {
        $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : self::GENERAL_SETTINGS_KEY;

        screen_icon();
        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $this->tabs as $tab_key => $tab_caption ) {
            $active = $current_tab == $tab_key ? 'nav-tab-active' : '';
            echo '<a class="nav-tab ' . $active . '" href="?page=' . self::OPTIONS_KEY . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';
        }
        echo '</h2>';
    }

    public function apiLayoutSection() {
        echo "<p>In order to communicate securely, we use a shared secret key. You can find your secret API key on <a target='_blank' href='".$this->getContextlyRegistrationUrl('tour')."'>this page</a>. Copy and paste it below.</p>";
    }

    public function apiKeyInput() {
        $options = get_option( self::API_SETTINGS_KEY );

        echo "<label><input name='" . self::API_SETTINGS_KEY . "[api_key]' type='text' size='40' value='{$options["api_key"]}' /></label>";
    }

    public function settingsLayoutSection() {
        echo "<p>
			By default, Contextly is set to show up as the very last object in your post template. For most sites, this is perfect. However, if you have other plugins that come after the body of the text, you can adjust where Contextly displays using this setting.
			</p>
			<p>
			To set the placement of Contextly related links relative to other elements, simply provide the other item's CSS element ID, and whether you prefer to be above or below that element.
			</p>";
    }

    public function settingsOverride() {
        $options = get_option( self::ADVANCED_SETTINGS_KEY );

        echo "<label>";
        echo "<input id='link_type_override' name='" . self::ADVANCED_SETTINGS_KEY . "[link_type]' type='radio' value='override' " . ($options['link_type'] == "override" ? "checked='checked'" : "") . "/>";
        echo " With this setting, the WordPress link button in the Visual editor is changed to used Contextly to add links to the body of your posts. There is no dedicated button for adding single links through Contextly with this option.";
        echo "</label>";
    }

    public function settingsDefault() {
        $options = get_option( self::ADVANCED_SETTINGS_KEY );
        echo "<label>";
        echo "<input id='link_type_default' name='" . self::ADVANCED_SETTINGS_KEY . "[link_type]' type='radio' value='' " . (!$options['link_type'] ? "checked='checked'" : "") . "/>";
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
				</select>
			";
    }

    public function settingsDisplayFor() {
	    $values = $this->getWidgetDisplayType();
	    $post_types = get_post_types( '', 'objects' );

	    echo "<table cellpadding='0' cellspacing='0'>";
	    foreach ( $post_types as $post_type ) {
		    echo "<tr><td style='padding: 3px;'>";
		    echo "<input id='post-type-{$post_type->name}' name='" . self::ADVANCED_SETTINGS_KEY . "[display_type][]' type='checkbox' value='{$post_type->name}' " . (in_array( $post_type->name, ( array_values( $values ) ) ) ? "checked='checked'" : "" ) . " />";
		    echo "</td><td style='padding: 3px;'><label for='post-type-{$post_type->name}'>";
		    echo $post_type->labels->name;
		    echo "</label></td></tr>";
	    }
	    echo "</table>";
    }

    public function getOptions() {
        $advanced_options = get_option( self::ADVANCED_SETTINGS_KEY );
        if ( !is_array($advanced_options) ) $advanced_options = array();

        return $advanced_options;
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

    public function isPageDisplayDisabled( $page_id ) {
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
