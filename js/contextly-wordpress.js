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
		areLinkWidgetsDisplayed: function() {
			return Contextly.render_link_widgets;
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
                        if ( response.key_different_domain ) {
                            Contextly.WPAdminMessages.waring( "This API key has been used in the past by another installation. DO NOT use the same API key for multiple installations. " +
                                "This will result in undesired behavior. Please get a new API key <a href='#' onclick='open_contextly_registration_page();'>here</a>." );
                        }

                        jQuery( '#' + settings_button_id ).attr( 'contextly_access_token', response.contextly_access_token );

                        if ( disabled_flag )
                        {
                            jQuery( '#' + settings_button_id ).removeAttr( 'disabled' );
                        }

                        Contextly.LogPluginEvents.fireEvent('contextlySettingsAuthSuccess', response);
					} else {
                        if ( response.message ) {
                            Contextly.WPAdminMessages.error( "You need a valid API key. Click the \"API Key\" tab above to get one." );
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

		construct: function() {
			var callback = this.proxy(this.afterDisplayWidgetAction, false, true);
			jQuery(window).bind(Contextly.widget.broadcastTypes.DISPLAYED, callback);
		},

		onWidgetsLoadingError: function(response) {
			Contextly.PageView.onWidgetsLoadingError.apply(this, arguments);
			if ( !Contextly.Setting.isAdmin() ) {
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
			var widget = new Contextly.widget.Base();
			widget.displayHTML( message );
		},

		onWidgetsLoadingSuccess: function(response) {
			Contextly.PageView.onWidgetsLoadingSuccess.apply(this, arguments);

			if ( !Contextly.Settings.isAdmin() ) {
				this.attachModuleViewEvent();
			}
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

		afterDisplayWidgetAction: function ( e, widgetType, snippet ) {
			if (widgetType !== Contextly.widget.types.SNIPPET) {
				return;
			}

			if (Contextly.Settings.isAdmin() || !snippet.hasWidgetData()) {
				return;
			}

			if (jQuery(this.getMainWidgetShortCodeId()).length) {
				if ( snippet.getDisplayElement().length ) {
                    snippet.getDisplayElement().appendTo( this.getMainWidgetShortCodeId() );
                } else {
                    jQuery( this.getMainWidgetShortCodeId() ).html( "<div id='ctx-module' class='ctx-module-container ctx-clearfix'></div>" );
                    snippet.display();
                }
			}
			else {
				// We need to be sure that our control is last in content element
				if (!snippet.getDisplayElement().is(":last-child")) {
                    snippet.getDisplayElement().parent().append(snippet.getDisplayElement());
				}
			}
		},

		getDisplayableWidgetCollections: function(response) {
			if ( Contextly.Settings.isAdmin() ) {
				if ( Contextly.Settings.areLinkWidgetsDisplayed() ) {
					return [ response.entry.snippets ];
				}
				else {
					return [];
				}
			}
			else {
				if ( Contextly.Settings.areLinkWidgetsDisplayed() ) {
					return Contextly.PageView.getDisplayableWidgetCollections.apply(this, arguments);
				}
				else {
					return [ response.entry.storyline_subscribe ];
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
			if ( this.lastWidgetsResponse && this.lastWidgetsResponse.guid ) {
				Contextly.RESTClient.call(
					'events',
					'put',
					{
						event_type: Contextly.WPLogPluginEventType.LOG,
						event_name: Contextly.WPLogPluginEventName.MODULE_VIEW,
						event_guid: this.lastWidgetsResponse.guid
					}
				);
			}
		}

	}
});

/**
 * @class
 * @extends Contextly.widget.Utils
 */
Contextly.WPUtils = Contextly.createClass({
    extend: Contextly.Utils,
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

if ( !Contextly.disable_autoload ) {
	Contextly.WPPageView.loadWidgets();
}
