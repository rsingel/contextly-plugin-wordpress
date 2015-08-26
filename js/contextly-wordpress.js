/**
 * Main script or build Contextly widget, using REST api.
 */
Contextly = Contextly || {};

Contextly.Settings = Contextly.createClass({
    extend: Contextly.BaseSettings,

    statics: {
        getEditorUrl: function () {
            return Contextly.editor_url;
        },
        getAppId: function () {
            return Contextly.app_id;
        },
        getMode: function () {
            return Contextly.mode;
        },
        getWPSettings: function () {
            return Contextly.settings;
        },
        isHttps: function () {
            return Contextly.https;
        },
        getAjaxUrl: function () {
            return Contextly.ajax_url;
        },
        getAjaxNonce: function () {
            if ( Contextly.ajax_nonce ) {
                return Contextly.ajax_nonce;
            }
            return null;
        },
        isAdmin: function () {
            return Contextly.admin;
        },
        isBrandingDisplayed: function () {
            return !this.isAdmin();
        },
        getAssetUrl: function(path, ext) {
            if (this.getMode() == 'dev') {
                return Contextly.asset_url + '/' + path + '.' + ext;
            }
            else {
                return Contextly.BaseSettings.getAssetUrl.apply(this, arguments);
            }
        },
        getClientInfo: function() {
            return {
                client: 'wp',
                version: Contextly.version
            };
        }

    }

});

Contextly.SettingsAutoLogin = Contextly.createClass({
    statics: {
        doLogin: function ( settings_button_id, disabled_flag ) {
            if ( disabled_flag )
            {
                jQuery( '#' + settings_button_id ).attr( 'disabled', 'disabled' );
            }

            jQuery.ajax({
                url: Contextly.Settings.getAjaxUrl(),
                type: 'post',
                dataType: 'json',
                data: {
                    action: 'contextly_get_auth_token'
                },
                success: function ( response ) {
                    if ( response.success && response.contextly_access_token ) {
                        if ( response.key_different_domain ) {
                            Contextly.WPAdminMessages.waring( "We believe this API key has been used on a staging or development site. " +
                                "If this is true, please do not reuse this API key. " +
                                "Please get a <a href='#' onclick='open_contextly_registration_page();'>new</a> API key for your new site. "
                            );
                        }

                        jQuery( '#' + settings_button_id ).attr( 'contextly_access_token', response.contextly_access_token );

                        if ( disabled_flag )
                        {
                            jQuery( '#' + settings_button_id ).removeAttr( 'disabled' );
                        }

                        Contextly.EventsLogger.sendEvent('contextlySettingsAuthSuccess', response);
                    } else {
                        if ( response.message ) {
                            Contextly.WPAdminMessages.error( "You need a valid API key. Click the \"API Key\" tab above to get one." );
                        }

                        Contextly.EventsLogger.sendEvent('contextlySettingsAuthFailed', response);
                    }
                },
                error: function () {
                    jQuery( '#' + settings_button_id ).removeAttr( 'disabled' );
                    Contextly.EventsLogger.sendEvent('contextlySettingsAuthFailed');
                }
            });
        }

    }

});

/**
 * @class
 */
Contextly.WPAdminMessages = Contextly.createClass({
    statics: {
        error: function ( message ) {
            this.render( 'error', message )
        },

        waring: function ( message ) {
            this.render( 'error', message )
        },

        render: function ( message_class, message_text ) {
            jQuery( '#contextly_warnings').html(
                "<div class='fade " + message_class + "'><p>" + message_text + "</p></div>"
            );
        }
    }
});

/**
 * @class
 * @extends Contextly.PageView
 */
Contextly.WPPageView = Contextly.createClass( /** @lends Contextly.PageView.prototype */ {

    extend: Contextly.PageView,

    statics: {

        loadWidgets: function() {
            // Fix problem for some clients with few our widgets on page
            // remove all occurrences and leave only one last
            if ( Contextly.Settings.getAppId() == 'asoundeffect' ) {
                var modules = jQuery("div[id='ctx-module']");
                if (modules.length > 1) {
                    var modules_count = modules.length;
                    modules.each(function (index, element) {
                        if (index != modules_count - 1) {
                            jQuery(element).remove();
                        }
                    });
                }
            }

            if ( !Contextly.Settings.isAdmin() ) {
                // Change Main module and SL button positoon for short codes
                Contextly.WPPageView.shortCodeUpdates();
            }

            // Load page modules
            Contextly.PageView.loadWidgets.apply(this, arguments);
        },

        onWidgetsLoadingError: function(response) {
            Contextly.PageView.onWidgetsLoadingError.apply(this, arguments);
            if ( !Contextly.Settings.isAdmin() ) {
                return;
            }

            var message = '';
            if ( response.error ) {
                if ( response.error_code == Contextly.RESTClient.errors.FORBIDDEN ) {
                    message = response.error + " Please check your API settings on the Contextly plugin <a href='admin.php?page=contextly_options&tab=contextly_options_api'>Settings</a> page.";
                } else if ( response.error_code == Contextly.RESTClient.errors.SUSPENDED ) {
                    message = "Your account has been suspended. If this is an error, please contact us via <a href='http://contextly.com/contact-us/'>support@contextly.com</a>.";
                } else {
                    message = "Please check your API settings on the Contextly plugin <a href='admin.php?page=contextly_options&tab=contextly_options_api'>Settings</a> page.";
                }
            } else {
                message = "Sorry, something seems to be broken. Please contact us via <a href='http://contextly.com/contact-us/'>support@contextly.com</a>.";
            }

            // TODO Render error without creating base widget.
            var widget = new Contextly.widget.TextSnippet();
            widget.displayHTML( message );
        },

        updatePostAction: function (response) {
            if (!response.entry.update) {
                return;
            }

            var args = arguments;
            var parentUpdate = this.proxy(function() {
                Contextly.PageView.updatePostAction.apply( this, args );
            });

            var data = {
                action: 'contextly_publish_post',
                page_id: Contextly.Settings.getPageId(),
                contextly_nonce: Contextly.Settings.getAjaxNonce()
            };

            jQuery.ajax({
                url: Contextly.ajax_url,
                type: 'post',
                dataType: 'json',
                data: data,
                success: function(response) {
                    if ( response != true ) {
                        parentUpdate();
                    }
                },
                error: function () {
                    parentUpdate();
                }
            });
        },

        shortCodeUpdates: function() {
            var main_module_code_id = '#ctx_main_module_short_code';
            var main_module_id = '#ctx-module';

            if (jQuery(main_module_code_id).length) {
                jQuery(".ctx-module-container").remove();
                jQuery(main_module_code_id).html(
                    "<div id='ctx-module' class='ctx-module-container ctx-clearfix'></div>"
                );
            } else {
                // We need to be sure that our control is last in content element
                if (!jQuery(main_module_id).is(":last-child")) {
                    jQuery(main_module_id).parent().append(jQuery(main_module_id));
                }
            }

            var sl_button_code_id = '#ctx_sl_button_short_code';
            if (jQuery(sl_button_code_id).length) {
                jQuery('#ctx-sl-subscribe')
                    .appendTo(sl_button_code_id)
                    .removeClass( 'ctx_widget_hidden' );
            }

            var siderail_code_id = '#ctx_siderail_short_code';
            var $siderail_containers = jQuery('.ctx-siderail-container');
            if ( jQuery( siderail_code_id ).length ) {
                if ( !$siderail_containers.length ) {
                    jQuery( siderail_code_id).html( '<div class="ctx-siderail-container"></div>' );
                } else {
                    $siderail_containers
                        .appendTo(siderail_code_id)
                        .removeClass('ctx_widget_hidden');
                }
            }
        }

    }
});

if ( Contextly.Settings.getPageId() ) {
    Contextly.WPPageView.loadWidgets();
}
