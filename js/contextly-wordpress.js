/**
 * Main script or build Contextly widget, using REST api.
 */
Contextly = Contextly || {};

Contextly.Errors = {
    ERROR_FORBIDDEN: 403,
    ERROR_SUSPENDED: 408
};

// TODO: Replace use cases with the same dictionary from Kit and drop.
Contextly.WidgetType = {
    SNIPPET: 'snippet',
    SIDEBAR: 'sidebar',
    AUTO_SIDEBAR: 'auto-sidebar'
};

Contextly.Loader = Contextly.createClass({

	statics: {

		isCallAvailable: function () {
			return Contextly.Settings.isReadyToLoad();
		},

		getLastResponse: function () {
			return this.response;
		},

		isEntryWidgetsHasLinks: function ( entry_widgets ) {
			for ( var i = 0; i < entry_widgets.length; i++ ) {
				if ( entry_widgets[i].links ) {
					return true;
				}
			}
			return false;
		},

		// Main method for load page widgets
		load: function () {
			if ( !this.isCallAvailable() ) return;

			var self = this;
			Contextly.RESTClient.call(
				'pagewidgets',
				'get',
				{},
				function ( response ) {
					self.response = response;

					var pageView = new Contextly.PageView( response );
					pageView.display();

					self.initCookie( response );
				}
			);
		},

		getCookieName: function ()
		{
			return "contextly";
		},

		initCookie: function ( rest_response )
		{
			if ( rest_response && rest_response.cookie_id )
			{
				if ( !this.getCookieId() )
				{
					this.setCookieId( rest_response.cookie_id );
				}
			}
		},

		setCookieId: function ( cookie_id )
		{
			jQuery.cookie( this.getCookieName(), {id: cookie_id}, { expires: 1, path: '/' } );
		},

		getCookieId: function ()
		{
			jQuery.cookie.json = true;
			var cookie = jQuery.cookie( this.getCookieName() );

			if ( cookie && cookie.id && cookie.id != 'null' )
			{
				return cookie.id;
			}

			return null;
		}

	}

});

Contextly.PageView = Contextly.createClass({
    construct: function ( rest )
    {
        this.entry = null;
        this.error = null;

        if ( rest.entry ) {
            this.entry = rest.entry;
        } else if ( rest.error ) {
            this.error = rest;
        }
    },

    isError: function () {
        return this.error != null;
    },

    // Main method for process rest response and display widgets or some admin messages
    display: function () {
        // Check if we have error on page
        if ( this.isError() && Contextly.Settings.isAdmin() ) {
            var message = '';
            if ( this.error.error ) {
                if ( this.error.error_code == Contextly.Errors.ERROR_FORBIDDEN ) {
                    message = this.error.error + " Please check your API settings on the Contextly plugin <a href='admin.php?page=contextly_options&tab=contextly_options_api'>Settings</a> page.";
                } else if ( this.error.error_code == Contextly.Errors.ERROR_SUSPENDED ) {
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
        }
        else
        {
            if ( this.entry ) {
                // Display widgets
                this.displayWidgets( this.entry.snippets );
                this.displayWidgets( this.entry.sidebars );
                this.displayWidgets( this.entry.auto_sidebars );

                if ( this.entry.update )
                {
                    this.updatePost();
                }
            }
        }
    },

    updatePost: function () {
        var self = this;

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
                if ( response != true )
                {
                    self.updatePostWithRest();
                }
            },
            error: function ()
            {
                self.updatePostWithRest();
            }
        });
    },

    updatePostWithRest: function () {
        Contextly.RESTClient.call(
            'postsimport',
            'put',
            {
                url: Contextly.Settings.getPageUrl()
            },
            function ( response ) {
            }
        );
    },

    displayWidgets: function ( widgets ) {
        if ( widgets && widgets.length ) {
            for ( var idx = 0; idx < widgets.length; idx++ ) {
                var widget_object = Contextly.widget.Factory.getWidget( widgets[ idx ] );
                if ( widget_object ) {
                    widget_object.display();
					this.fixSnippetPagePosition(widget_object);
                }
            }
        }
    },

	getMainWidgetShortCodeId: function () {
		return '#ctx_main_module_short_code';
	},

	fixSnippetPagePosition: function(snippet) {
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
	}

});

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
		isAdmin: function () {
			return Contextly.admin;
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
		getCookieId: function() {
			return Contextly.Loader.getCookieId();
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
		isBrandingDisplayed: function () {
			return !this.isAdmin();
		},
		getPostData: function () {
			var data = jQuery("meta[name='contextly-page']").attr("content");
			var json = jQuery.parseJSON(data);

			return json;
		},
		getSnippetCssUrl: function(settings) {
			var css_url;
			if (this.getMode() == 'local') {
				css_url = "http://linker.site/resources/css/plugin/widget/" + settings.display_type + "/template-" + settings.tabs_style + ".css";
			}
			else if (this.getMode() == 'dev') {
				css_url = "http://dev.contextly.com/resources/css/plugin/widget/" + settings.display_type + "/template-" + settings.tabs_style + ".css";
			}
			else {
				css_url = Contextly.BaseSettings.getSnippetCssUrl.apply(this, arguments);
			}
			return css_url;
		},
		getSidebarCssUrl: function(settings) {
			var css_url;
			if (this.getMode() == 'local') {
				css_url = "http://linker.site/resources/css/plugin/sidebar/template-" + settings.theme + ".css";
			}
			else if (this.getMode() == 'dev') {
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
					} else if ( response.message && disabled_flag ) {
						jQuery( '#' + settings_button_id ).parent().append(
							jQuery( "<p style='color: red; font-weight: bold;'>* You need a valid API key. Click the \"API Key\" tab above to get one.</p>" )
						);
					}
				},
				error: function () {
					jQuery( '#' + settings_button_id ).removeAttr( 'disabled' );
				}
			});
		}

	}

});

// Load Contextly widgets for this page
Contextly.Loader.load();
