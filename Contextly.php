<?php

class Contextly
{
    var $general_settings_key   = 'contextly_options_general';
    var $api_settings_key       = 'contextly_options_api';
    var $advanced_settings_key  = 'contextly_options_advanced';
    var $plugin_options_key     = 'contextly_options';
    var $plugin_settings_tabs   = array();
    var $plugin_name            = 'contextly-linker';

    function __construct()
    {
        Contextly_Api::getInstance()->setOptions( $this->getAPIClientOptions() );
    }

    function addSettngsMenu() {
        add_options_page('Contextly', 'Contextly', 'manage_options', $this->plugin_options_key, array(&$this, 'showSettings'));
        add_filter('plugin_action_links', array(&$this, 'addSettingsLink'), 10, 2);
    }

    function initSettings() {
        // Register settings
        register_setting($this->general_settings_key, $this->general_settings_key, array(&$this, 'settingsValidate'));
        register_setting($this->advanced_settings_key, $this->advanced_settings_key, array(&$this, 'settingsValidate'));
        register_setting($this->api_settings_key, $this->api_settings_key, array(&$this, 'settingsValidate'));

        $this->plugin_settings_tabs[$this->general_settings_key] = 'General';

        add_settings_section('api_section', 'API Settings', array(&$this, 'apiLayoutSection'), $this->api_settings_key);
        add_settings_field('api_key', 'API Key', array(&$this, 'apiKeyInput'), $this->api_settings_key, 'api_section');

        $this->plugin_settings_tabs[$this->api_settings_key] = 'API';

        add_settings_section('main_section', 'Single Link Button', array(&$this, 'settingsMainSection'), $this->advanced_settings_key);
        add_settings_field('link_type_override', 'Override', array(&$this, 'settingsOverride'), $this->advanced_settings_key, 'main_section');
        add_settings_field('link_type_default', 'Default', array(&$this, 'settingsDefault'), $this->advanced_settings_key, 'main_section');

        add_settings_section('advanced_section', 'Layout Settings', array(&$this, 'settingsLayoutSection'), $this->advanced_settings_key);
        add_settings_field('linker_target_id', 'CSS Element ID', array(&$this, 'settingsTargetInput'), $this->advanced_settings_key, 'advanced_section');
        add_settings_field('linker_block_position', 'Position', array(&$this, 'settingsBlockPosition'), $this->advanced_settings_key, 'advanced_section');
        $this->plugin_settings_tabs[$this->advanced_settings_key] = 'Advanced';
    }

    function settingsValidate($input) {
        return $input;
    }

    function settingsMainSection() {}

    function apiLayoutSection()
    {
        $home_url = CONTEXTLY_MAIN_SERVER_URL ."redirect/?type=tour&blog_url=" . site_url() . "&blog_title=" . get_bloginfo("name");
        echo "<p>In order to communicate securely, we use a shared secret key. You can find your secret API key on <a target='_blank' href='{$home_url}'>{$home_url}</a>. Copy and paste it below.</p>";
    }

    function settingsLayoutSection() {
        echo "<p>
			By default, Contextly is set to show up as the very last object in your post template. For most sites, this is perfect. However, if you have other plugins that come after the body of the text, you can adjust where Contextly displays using this setting.
			</p>
			<p>
			To set the placement of Contextly related links relative to other elements, simply provide the other item's CSS element ID, and whether you prefer to be above or below that element.
			</p>";
    }

    function settingsOverride() {
        $options = get_option($this->advanced_settings_key);
        echo "<label>";
        echo "<input id='link_type_override' name='{$this->advanced_settings_key}[link_type]' type='radio' value='override' " . ($options['link_type'] == "override" ? "checked='checked'" : "") . "/>";
        echo " With this setting, the Wordpress link button in the Visual editor is changed to used Contextly to add links to the body of your posts. There is no dedicated button for adding single links through Contextly with this option.";
        echo "</label>";
    }

    function settingsDefault() {
        $options = get_option($this->advanced_settings_key);
        echo "<label>";
        echo "<input id='link_type_default' name='{$this->advanced_settings_key}[link_type]' type='radio' value='' " . (!$options['link_type'] ? "checked='checked'" : "") . "/>";
        echo " With this setting, Wordpress's single link button in the Visual editor works as it normally does. The Visual editor bar gets an additional single link button so you can add links to the body of your post using Contextly.";
        echo "</label>";
    }

    function settingsTargetInput() {
        $options = get_option($this->advanced_settings_key);
        echo "<input id='linker_target_id' name='{$this->advanced_settings_key}[target_id]' type='text' size='30' value='{$options["target_id"]}' />";
    }

    function settingsBlockPosition() {
        $options = get_option($this->advanced_settings_key);
        echo "
				<select id='linker_block_position' name='{$this->advanced_settings_key}[block_position]'>
					<option value='after' " . ($options["block_position"] == "after" ? "selected='selected'" : "") . ">Below</option>
					<option value='before' " . ($options["block_position"] == "before" ? "selected='selected'" : "") . ">Above</option>
				</select>
			";
    }

    function apiKeyInput() {
        $options = get_option($this->api_settings_key);
        echo "<label><input name='{$this->api_settings_key}[api_key]' type='text' size='40' value='{$options["api_key"]}' /></label>";
    }

    function showSettings()	{
        $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->general_settings_key;
        ?>
    <script type="text/javascript">
        function open_contextly_settings() {
            window.open("<?php echo CONTEXTLY_MAIN_SERVER_URL ?>redirect/?type=settings&blog_url=<?php echo site_url(); ?>&blog_title=<?php echo get_bloginfo("name"); ?>");
        }
    </script>
    <div class="wrap">
        <?php $this->showSettingsTabs(); ?>

        <?php if ($tab != $this->general_settings_key) { ?>
        <form action="options.php" method="post">
            <?php settings_fields($tab); ?>
            <?php do_settings_sections($tab); ?>
            <?php submit_button(); ?>
        </form>
        <?php } else { ?>
        <h3>
            The majority of  the settings for Contextly are handled outside Wordpress. Press the settings button to go to your settings panel. You will need your Twitter credentials to login.
        </h3>
        <p>
            <input type="button" value="Settings" onclick="open_contextly_settings();" style="font-size: 18px; padding: 5px;" />
        </p>
        <?php } ?>
    </div>
    <?php
    }

    function showSettingsTabs() {
        $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->general_settings_key;

        screen_icon();
        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $this->plugin_settings_tabs as $tab_key => $tab_caption ) {
            $active = $current_tab == $tab_key ? 'nav-tab-active' : '';
            echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->plugin_options_key . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';
        }
        echo '</h2>';
    }

    function getSettingsOptions() {
        $advanced_options = get_option($this->advanced_settings_key);
        if (!is_array($advanced_options)) $advanced_options = array();

        return $advanced_options;
    }

    function getPostData()
    {
        global $post;

        return array(
            'post_id'       => $post->ID,
            'post_modified' => $post->post_modified,
            'author'        => $post->post_author
        );
    }

    // Add main js stuff for contextly api calls
    function loadScripts() {
        global $pagenow;

        $admin_mode = false;

        if ( $pagenow == "post.php" || $pagenow == "post-new.php" )
        {
            $admin_mode = true;
            //wp_enqueue_script( 'easy_xdm', 'http://contextlysitescripts.contextly.com/js/easyXDM.min.js' );
        }

        if ( is_single() || $admin_mode )
        {
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'contextly-create-class', plugins_url('js/contextly-create-class.js' , __FILE__ ), 'jquery' );
            wp_enqueue_script( 'easy_xdm', plugins_url('js/easyXDM.min.js', __FILE__ ) );
            wp_enqueue_script( 'contextly', contextly_get_plugin_url(), 'jquery', CONTEXTLY_PLUGIN_VERSION, false );

            $ajax_url = plugins_url( 'ajax.php' , __FILE__ );
            $home_url = home_url( '/' );

            $ajax_url_parts = parse_url( $ajax_url );
            $home_url_parts = parse_url( $home_url );

            // Fix in case if we have different url for site address
            if ( !$admin_mode )
            {
                if ( $ajax_url_parts[ 'host' ] != $home_url_parts[ 'host' ] )
                {
                    $ajax_url = rtrim( $home_url, '/' ) . $ajax_url_parts[ 'path' ];
                }
            }

            $data = array(
                'ajax_url'   => $ajax_url,
                'api_server' => CONTEXTLY_API_SERVER_URL,
                'popup_server' => CONTEXTLY_POPUP_SERVER_URL,
                'settings'   => $this->getSettingsOptions(),
                'post'       => $this->getPostData(),
                'admin'      => (int)$admin_mode,
                'mode'       => CONTEXTLY_MODE,
                'version'    => CONTEXTLY_PLUGIN_VERSION
            );

            $api_options = get_option( $this->api_settings_key );

            if ( is_array( $api_options ) && isset( $api_options[ 'api_key' ] ) )
            {
                $data[ 'api_key' ] = trim( $api_options[ 'api_key' ] );
            }

            wp_localize_script(
                'contextly',
                'Contextly',
                array( 'l10n_print_after' => 'Contextly = ' . json_encode( $data ) . ';' )
            );
        }
    }

    // Add settings link on plugins page
    function addSettingsLink($links, $file) {
        if ( strpos( $file, $this->plugin_name ) !== false )
        {
            $links[] = "<a href='admin.php?page=contextly_options'>" . __('Settings') . "</a>";
        }

        return $links;
    }

    function getAPIClientOptions()
    {
        $client_options = array(
            'server-url'    => CONTEXTLY_API_SERVER_URL,
            'auth-api'      => 'auth/auth',
            'appID'         => '',
            'appSecret'     => '',
            'fastAPI'       => true
        );

        $api_options = get_option( 'contextly_options_api' );

        if ( is_array( $api_options ) && isset( $api_options[ 'api_key' ] ) )
        {
            $api_key = explode( '-', trim( $api_options[ 'api_key' ] ) );

            if ( count( $api_key ) == 2 )
            {
                $client_options[ 'appID' ]      = $api_key[ 0 ];
                $client_options[ 'appSecret' ]  = $api_key[ 1 ];
            }
        }

        return $client_options;
    }

    function getAuthorName( $post )
    {
        // As last step we can check WP user
        $display_name = get_the_author_meta( "display_name", $post->post_author );
        $user_name = get_the_author_meta( "last_name", $post->post_author ) . ' ' . get_the_author_meta( "first_name", $post->post_author );

        return $display_name ? $display_name : $user_name;
    }

    // Publish post action
    function publishPostAction($post_ID, $post) {
        try {
            if ( $post_ID && $post->post_status == "publish" && $post->post_type == "post" ) {

                $client_options = $this->getAPIClientOptions();

                Contextly_Api::getInstance()->setOptions( $client_options );

                // Check if we have this post in our db
                $contextly_post = Contextly_Api::getInstance()
                    ->api( 'posts', 'get' )
                    ->param( 'page_id', $post->ID )
                    ->get();

                $post_data = array(
                    'post_id'       => $post->ID,
                    'post_title'    => $post->post_title,
                    'post_date'     => $post->post_modified,
                    'post_status'   => $post->post_status,
                    'post_type'     => $post->post_type,
                    'post_content'  => $post->post_content,
                    'url'           => get_permalink( $post->ID ),
                    'author_id'     => $post->post_author,
                    'post_author'   => $this->getAuthorName( $post )
                );

                // Lets publish this post in our DB
                $publish_post = Contextly_Api::getInstance()
                    ->api( 'posts', 'put' )
                    ->extraParams( $post_data );

                if ( isset( $contextly_post->entry ) )
                {
                    $publish_post->param( 'id', $contextly_post->entry->id );
                }

                $response = $publish_post->get();

                // Lets try to update some post related stuff
                $this->updatePostImages( $post );
                $this->updatePostTags( $post );

                return $response;
            }
        }
        catch ( Exception $e )
        {
            return $e;
        }

        return null;
    }

    function updatePostTags( $post )
    {
        $post_id = $post->ID;

        $client_options = $this->getAPIClientOptions();
        Contextly_Api::getInstance()->setOptions( $client_options );

        // First of all we need to get list of all page tags and remove
        $post_tags = Contextly_Api::getInstance()
            ->api( 'poststags', 'list' )
            ->searchParam( 'post_id', Contextly_Api::SEARCH_TYPE_EQUAL, $post_id )
            ->get();

        if ( isset( $post_tags->list ) && is_array( $post_tags->list ) )
        {
            foreach ( $post_tags->list as $tag )
            {
                Contextly_Api::getInstance()
                    ->api( 'poststags', 'delete' )
                    ->param( 'id', $tag->id )
                    ->get();
            }
        }

        // Save new post tags
        $post_tags = get_the_tags( $post_id );
        if (is_array($post_tags) && count($post_tags) > 0) {
            foreach (array_slice($post_tags, 0, 3) as $post_tag) {
                $tag_data = array(
                    'post_id'   => $post_id,
                    'name'      => $post_tag->name
                );

                Contextly_Api::getInstance()
                    ->api( 'poststags', 'put' )
                    ->extraParams( $tag_data )
                    ->get();
            }
        }
    }

    function updatePostImages( $post )
    {
        $post_id = $post->ID;

        $client_options = $this->getAPIClientOptions();
        Contextly_Api::getInstance()->setOptions( $client_options );

        // Try to get attached images
        $attachment_images = get_children(
            array(
                'post_parent' => $post_id,
                'post_type' => 'attachment',
                'numberposts' => 0,
                'post_mime_type' => 'image'
            )
        );
        if ($attachment_images && is_array($attachment_images)) {
            foreach($attachment_images as $image) {
                list($src, $width, $height) = wp_get_attachment_image_src($image->ID, 'full');

                $image_data = array(
                    'image_url' => $src,
                    'page_id'   => $post_id
                );

                // Lets post save images in contextly
                Contextly_Api::getInstance()
                    ->api( 'images', 'put' )
                    ->extraParams( $image_data )
                    ->get();
            }
        }
    }

    // In this method we will display hidden div. After page loading we will load it's content with javascript.
    // This will help to load page about loosing performance.
    function displaySidebar( $attrs, $content = null )
    {
        // We will display sidebar only if we have id for this sidebar
        if ( isset( $attrs[ 'id' ] ) )
        {
            return "<div class='contextly-sidebar-hidden' id='" . $attrs[ 'id' ] ."'></div>";
        }
        else
        {
            return "";
        }
    }


}
