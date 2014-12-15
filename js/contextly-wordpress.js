/**
 * Main script or build Contextly widget, using REST api.
 */
Contextly = Contextly || {};

Contextly.WPLogPluginEventType = {
    LOG: 'log'
};

Contextly.WPLogPluginEventName = {
    MODULE_VIEW: 'module_view'
};

Contextly.Settings = Contextly.createClass({
    extend: Contextly.BaseSettings,

    statics: {
        getAPIServerUrl: function () {
            return Contextly.api_server;
        },
        getMainServerUrl: function () {
            return Contextly.main_server;
        },
        getEditorUrl: function () {
            return Contextly.editor_url;
        },
        getPluginVersion: function () {
            return Contextly.version;
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
        isReadyToLoad: function() {
            if ( Contextly.disable_autoload && Contextly.disable_autoload == true ) {
                return false;
            }
            return true;
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
        getSnippetCssUrl: function(settings) {
            var css_url;
            if (this.getMode() == 'dev') {
                css_url = "http://dev.contextly.com/resources/css/plugin/widget/" + settings.display_type + "/template-" + settings.tabs_style + ".css";
            }
            else {
                css_url = Contextly.BaseSettings.getSnippetCssUrl.apply(this, arguments);
            }
            return css_url;
        },
        getSidebarCssUrl: function(settings) {
            var css_url;
            if (this.getMode() == 'dev') {
                css_url = "http://dev.contextly.com/resources/css/plugin/sidebar/template-" + settings.theme + ".css";
            }
            else {
                css_url = Contextly.BaseSettings.getSidebarCssUrl.apply(this, arguments);
            }
            return css_url;
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
						jQuery( '#' + settings_button_id ).attr( 'contextly_access_token', response.contextly_access_token );

                        if ( disabled_flag )
                        {
                            jQuery( '#' + settings_button_id ).removeAttr( 'disabled' );
                        }

                        Contextly.LogPluginEvents.fireEvent('contextlySettingsAuthSuccess', response);
					} else {
                        if ( response.message && disabled_flag ) {
                            jQuery( '#' + settings_button_id ).parent().append(
                                jQuery( "<p style='color: red; font-weight: bold;'>* You need a valid API key. Click the \"API Key\" tab above to get one.</p>" )
                            );
                        }

                        Contextly.LogPluginEvents.fireEvent('contextlySettingsAuthFailed', response);
                    }
				},
				error: function () {
					jQuery( '#' + settings_button_id ).removeAttr( 'disabled' );

                    Contextly.LogPluginEvents.fireEvent('contextlySettingsAuthFailed');
				}
			});
		}

	}

});

/**
 * @class
 * @extends Contextly.PageView
 */
Contextly.WPPageView = Contextly.createClass({ /** @lends Contextly.PageView.prototype */
    extend: Contextly.PageView,

    display: function () {
        // Check if we have error on page
        if ( this.isError() && Contextly.Settings.isAdmin() ) {
            var message = '';
            if ( this.error.error ) {
                if ( this.error.error_code == Contextly.Loader.Errors.ERROR_FORBIDDEN ) {
                    message = this.error.error + " Please check your API settings on the Contextly plugin <a href='admin.php?page=contextly_options&tab=contextly_options_api'>Settings</a> page.";
                } else if ( this.error.error_code == Contextly.Loader.Errors.ERROR_SUSPENDED ) {
                    message = "Your account has been suspended. If this is an error, please contact us via <a href='http://contextly.com/contact-us/'>support@contextly.com</a>.";
                } else {
                    message = "Please check your API settings on the Contextly plugin <a href='admin.php?page=contextly_options&tab=contextly_options_api'>Settings</a> page.";
                }
            } else {
                message = "Sorry, something seems to be broken. Please contact us via <a href='http://contextly.com/contact-us/'>support@contextly.com</a>.";
            }

            // TODO Render error without creating base widget.
            var widget = new Contextly.widget.Base();
            widget.displayHTML( message );
        } else {
            Contextly.PageView.fn.display.call( this );

            if ( !Contextly.Settings.isAdmin() ) {
                this.attachModuleViewEvent();
            }
        }
    },

    updatePostAction: function () {
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
                    Contextly.PageView.fn.updatePostAction.call( this );
                }
            },
            error: function () {
                Contextly.PageView.fn.updatePostAction.call( this );
            }
        });
    },

    afterDisplayWidgetAction: function ( snippet ) {
        if (snippet.widget_type !== Contextly.widget.types.SNIPPET) {
            return;
        }

        if (Contextly.Settings.isAdmin() || !snippet.hasWidgetData()) {
            return;
        }

        if (jQuery(this.getMainWidgetShortCodeId()).length) {
            snippet.getDisplayElement().appendTo(
                this.getMainWidgetShortCodeId()
            );
        }
        else {
            // We need to be sure that our control is last in content element
            if (!snippet.getDisplayElement().is(":last-child")) {
                snippet.getDisplayElement().parent().append(snippet.getDisplayElement());
            }
        }
    },

    getMainWidgetShortCodeId: function () {
        return '#ctx_main_module_short_code';
    },

    attachModuleViewEvent: function () {
        var self = this;
        this.module_view_interval = window.setInterval(
            function () {
                var check_display_element = jQuery( '.ctx-section .ctx-link p.ctx-nodefs ' ).first();
                if ( check_display_element.length ) {
                    var is_visible = Contextly.WPUtils.isElementVisible( check_display_element );

                    if ( is_visible ) {
                        self.logModuleViewEvent();

                        if ( self.module_view_interval ) {
                            window.clearInterval( self.module_view_interval );
                        }
                    }
                }
            },
            300
        );
    },

    logModuleViewEvent: function () {
        if ( Contextly.WPLoader.response && Contextly.WPLoader.response.guid ) {
            Contextly.RESTClient.call(
                'events',
                'put',
                {
                    event_type: Contextly.WPLogPluginEventType.LOG,
                    event_name: Contextly.WPLogPluginEventName.MODULE_VIEW,
                    event_guid: Contextly.WPLoader.response.guid
                }
            );
        }
    }


});

/**
 * @class
 * @extends Contextly.Loader
 */
Contextly.WPLoader = Contextly.createClass({
    extend: Contextly.Loader,
    statics: {
        displayWidgets: function ( response ) {
            var pageView = new Contextly.WPPageView( response );
            pageView.display();
        }
    }

});

/**
 * @class
 * @extends Contextly.widget.Utils
 */
Contextly.WPUtils = Contextly.createClass({
    extend: Contextly.widget.Utils,
    statics: {

        isElementVisible: function ( $el ) {
            var win = jQuery(window);
            var viewport = {
                top : win.scrollTop(),
                left : win.scrollLeft()
            };

            viewport.right = viewport.left + win.width();
            viewport.bottom = viewport.top + win.height();

            var bounds = $el.offset();
            bounds.right = bounds.left + $el.outerWidth();
            bounds.bottom = bounds.top + $el.outerHeight();

            return (!(viewport.right < bounds.left || viewport.left > bounds.right || viewport.bottom < bounds.top || viewport.top > bounds.bottom));
        }

    }
});

Contextly.WPLoader.load();
