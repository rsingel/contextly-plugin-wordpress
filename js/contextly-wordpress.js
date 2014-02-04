/**
 * Main script or build Contextly widget, using REST api.
 */
Contextly = Contextly || {};

Contextly.Errors = {
    ERROR_FORBIDDEN: 403,
    ERROR_SUSPENDED: 408
};

Contextly.WidgetType = {
    SNIPPET: 'snippet',
    SIDEBAR: 'sidebar',
    AUTO_SIDEBAR: 'auto-sidebar'
};

Contextly.LinkType = {
    PREVIOUS: 'previous',
    RECENT: 'recent',
    WEB: 'web',
    INTERESTING: 'interesting',
    CUSTOM: 'custom',
    PROMO: 'sticky'
};

Contextly.Singleton = Contextly.createClass({
    statics: {
        construct: function() {
            this._instance = null;
        },
        getInstance: function() {
            if(this._instance === null) {
                this._instance = new this();
            }
            return this._instance;
        }
    }
});

Contextly.Loader = Contextly.createClass({
    extend: Contextly.Singleton,

    isCallAvailable: function () {
        return Contextly.Settings.getInstance().isReadyToLoad();
    },

    getLastResponse: function () {
        return this.response;
    },

    isWidgetHasLinks: function () {
        var has_links = false;
        var response = this.getLastResponse();

        if ( response && response.entry ) {
            if ( response.entry.snippets ) {
                has_links = this.isEntryWidgetsHasLinks( response.entry.snippets );
            }
            if ( !has_links && response.entry.sidebars ) {
                has_links = this.isEntryWidgetsHasLinks( response.entry.sidebars );
            }
            if ( !has_links && response.entry.auto_sidebars ) {
                has_links = this.isEntryWidgetsHasLinks( response.entry.auto_sidebars );
            }
        }

        return has_links;
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
        Contextly.RESTClient.getInstance().call(
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

    trackPageEvent: function ( setting_id, event_name, event_key ) {
        if ( !this.isCallAvailable() ) return;

        var event_data = {
            'post_id'   : Contextly.Settings.getInstance().getPageId(),
            'setting_id': setting_id,
            'event_name': event_name,
            'event_key' : event_key,
            'event_date': new Date(),
            'cookie_id' : this.getCookieId()
        };

        Contextly.RESTClient.getInstance().call(
            'siteevents',
            'put',
            event_data,
            function ( response ) {
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
        if ( this.isError() && Contextly.Settings.getInstance().isAdmin() ) {
            var message = '';
            var url = 'admin.php?page=contextly_options&tab=contextly_options_api';

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

            var snippet_formatter = new Contextly.SnippetWidgetFormatter();
            snippet_formatter.displayText( message );

            Contextly.PopupHelper.getInstance().initWithUrl( url );
        } else if ( this.entry ) {
            // Init popup helper
            Contextly.PopupHelper.getInstance().initWithWidget( this.entry );

            // Display widgets
            if ( this.entry.snippets && this.entry.snippets.length > 0 ) {
                this.displayWidgets( this.entry.snippets );
            }
            if ( this.entry.sidebars && this.entry.sidebars.length > 0 ) {
                this.displayWidgets( this.entry.sidebars );
            }
            if ( this.entry.auto_sidebars && this.entry.auto_sidebars.length > 0 ) {
                this.displayWidgets( this.entry.auto_sidebars );
            }

            // Check if we need to update this post in our DB
            if ( !Contextly.Settings.getInstance().isAdmin() ) {
                this.checkPost();
            }
        }

        if ( Contextly.Settings.getInstance().isAdmin() ) {
            this.attachPublishConfirmation();
        }
    },

    attachPublishConfirmation: function () {
        jQuery( '#publish' ).click(
            function() {
                var wp_settings = Contextly.Settings.getInstance().getWPSettings();

                if ( wp_settings.publish_confirmation ) {
                    if ( Contextly.Loader.getInstance().isWidgetHasLinks() ) {
                        return true;
                    } else {
                        Contextly.PopupHelper.getInstance().showPublishConfirmation();
                        return false;
                    }
                }

                return true;
            }
        );
    },

    checkPost: function () {
        var update = false;

        // Now we can check last post publish date and probably we need to publish/update this post in our db
        if ( !this.entry.created_date ) {
            update = true;
        } else {
            var created_date_cmp = Contextly.Utils.getInstance().isPostNeedsToBeUpdated( this.entry.created_date, Contextly.Settings.getInstance().getPostCreatedDate() );
            var modified_date_cmp = Contextly.Utils.getInstance().isPostNeedsToBeUpdated( this.entry.modified_date, Contextly.Settings.getInstance().getPostModifiedDate() );

            if ( created_date_cmp || modified_date_cmp ) {
                update = true;
            }
        }

        if ( update ) {
            this.updatePost();
        }
    },

    updatePost: function () {
        var data = {
            action: 'contextly_publish_post',
            page_id: Contextly.Settings.getInstance().getPageId(),
            contextly_nonce: Contextly.Settings.getInstance().getAjaxNonce()
        };

        jQuery.ajax({
            url: Contextly.ajax_url,
            type: 'post',
            dataType: 'json',
            data: data,
            success: function() {}
        });
    },

    displayWidgets: function ( widgets ) {
        if ( widgets && widgets.length ) {
            for ( var idx = 0; idx < widgets.length; idx++ ) {
                var widget_object = Contextly.WidgetFactory.getInstance().getWidget( widgets[ idx ] );
                if ( widget_object ) {
                    widget_object.display();
                }
            }
        }
    }
});

Contextly.WidgetFactory = Contextly.createClass({
    extend: Contextly.Singleton,

    getWidget: function( entry ) {
        if ( !entry ) return null;

        if ( entry.type == Contextly.WidgetType.SIDEBAR ) {
            return new Contextly.SidebarWidget( entry );
        } else if ( entry.type == Contextly.WidgetType.AUTO_SIDEBAR ) {
                return new Contextly.AutoSidebarWidget( entry );
        } else {
            return new Contextly.SnippetWidget( entry );
        }
    }
});

Contextly.Widget = Contextly.createClass({
    construct: function( widget ) {
        this.widget = widget;
    },

    getWidgetFormatter: function () {
        return null;
    },

    display: function () {
        var widgetFormatter = this.getWidgetFormatter();
        if ( widgetFormatter && widgetFormatter.display ) {
            widgetFormatter.display();
        }
    }
});

Contextly.SnippetWidget = Contextly.createClass({
    extend: Contextly.Widget,

    getWidgetFormatter: function () {
        return Contextly.SnippetWidgetFormatterFactory.getInstance().getFormatter( this.widget );
    }

});

Contextly.SidebarWidget = Contextly.createClass({
    extend: Contextly.Widget,

    getWidgetFormatter: function () {
        return Contextly.SidebarWidgetFormatterFactory.getInstance().getFormatter( this.widget );
    }

});

Contextly.AutoSidebarWidget = Contextly.createClass({
    extend: Contextly.Widget,

    getWidgetFormatter: function () {
        return Contextly.SidebarWidgetFormatterFactory.getInstance().getFormatter( this.widget );
    }

});

Contextly.SnippetWidgetFormatterFactory = Contextly.createClass({
    extend: Contextly.Singleton,

    /**
     * @param widget Contextly.Widget
     * @return Contextly.SnippetWidgetFormatter
     */
    getFormatter: function( widget ) {
        var type = widget.settings.display_type;

        if ( type == 'default' ) {
            return new Contextly.SnippetWidgetTextFormatter( widget );
        } else if ( type == 'tabs' ) {
            return new Contextly.SnippetWidgetTabsFormatter( widget );
        } else if ( type == 'blocks' ) {
            return new Contextly.SnippetWidgetBlocksFormatter( widget );
        } else if ( type == 'blocks2' ) {
            return new Contextly.SnippetWidgetBlocks2Formatter( widget );
        } else if ( type == 'float' ) {
            return new Contextly.SnippetWidgetFloatFormatter( widget );
        }
    }
});

Contextly.SidebarWidgetFormatterFactory = Contextly.createClass({
    extend: Contextly.Singleton,

    /**
     * @param widget Contextly.Widget
     * @return Contextly.SidebarWidgetFormatter
     */
    getFormatter: function( widget ) {
        return new Contextly.SidebarWidgetFormatter( widget );
    }
});

//////////////////////////////////////////////////////////////
//                  Abstract Widget                         //
//////////////////////////////////////////////////////////////

Contextly.SnippetWidgetFormatter = Contextly.createClass({
    abstracts: [ 'getWidgetHTML', 'getWidgetCssName' ],

    construct: function( widget ) {
        this.widget = widget;
        this.widget_type = Contextly.WidgetType.SNIPPET;
        this.widget_html_id = 'ctx_linker';
    },

    getDisplayElement: function() {
        return jQuery( '#' + this.widget_html_id );
    },

    getMainWidgetShortCodeId: function ()
    {
        return '#ctx_main_module_short_code';
    },

    hasWidgetData: function () {
        return this.widget && this.widget.links;
    },

    displayText: function ( text ) {
        this.getDisplayElement().html( text );
    },

    appendText: function ( text ) {
        this.getDisplayElement().append( text );
    },

    displayAdminControls: function () {
        var controls = '';
        if ( this.hasWidgetData() ) {
            controls = "<br><input type='button' class='button action' value='Edit Related Posts' onclick='Contextly.PopupHelper.getInstance().snippetPopup();' />";
            this.appendText( controls );
        } else {
            controls = "<input type='button' class='button action' value='Choose Related Posts' onclick='Contextly.PopupHelper.getInstance().snippetPopup();' />";
            this.displayText( controls );
        }
    },

    isDisplaySection: function ( section ) {
        var display_section = jQuery.inArray( section, this.widget.settings.display_sections ) != -1;
        var have_to_display = this.widget.links && this.widget.links[ section ] && this.widget.links[ section ].length > 0;

        return display_section && have_to_display;
    },

    display: function () {
        if ( this.hasWidgetData() ) {
            this.displayText( this.getWidgetHTML() );
            this.loadCss( 'widget-css' );

            // Check if we need to change snippet position on page
            if ( !Contextly.Settings.getInstance().isAdmin() ) {
                this.fixSnippetPagePosition();
            }

            var settings = this.getSettings();
            if ( settings && settings.display_type ) {
                this.getDisplayElement().attr( 'widget-type', settings.display_type );
            }

            this.attachVideoPopups();
        }

        if ( Contextly.Settings.getInstance().isAdmin() ) {
            this.displayAdminControls();
        }

        this.setResponsiveFunction();
    },

    attachVideoPopups: function () {
        jQuery("a[rel='ctx_video_link']").each(
            function () {
                jQuery( this ).prettyPhoto({animation_speed:'fast',slideshow:10000, hideflash: true});
                jQuery( this ).click(
                    function () {
                        var contextly_url = jQuery( this).attr( 'contextly-url' );
                        Contextly.MainServerAjaxClient.getInstance().call( contextly_url );
                    }
                );
            }
        );
    },

    loadCss: function ( contextly_id ) {
        var css_url = this.getWidgetCSSUrl();

        Contextly.Utils.getInstance().loadCssFile( css_url, contextly_id );

        if ( Contextly.Utils.getInstance().isIE7() ) {
            var css_ie7fix = Contextly.Settings.getInstance().getCdnCssUrl() + "_plugin/"  + Contextly.Settings.getInstance().getPluginVersion() +  "/css/template-ie-fix.css";
            Contextly.Utils.getInstance().loadCssFile( css_ie7fix, 'widget-ie-fix' );
        }

        // Make needed css rules and load custom widget css
        var custom_css = this.getCustomCssCode();
        if ( custom_css ) {
            Contextly.Utils.getInstance().loadCustomCssCode( custom_css, this.getWidgetType() + '-custom' );
        }
    },

    getWidgetCSSUrl: function () {
        var css_url;
        var settings = this.getSettings();

        if ( Contextly.Settings.getInstance().getMode() == 'local' ) {
            css_url = "http://linker.site/resources/css/plugin/widget/" + settings.display_type + "/template-" + settings.tabs_style + ".css";
        } else if ( Contextly.Settings.getInstance().getMode() == 'dev' ) {
			css_url = "http://dev.contextly.com/resources/css/plugin/widget/" + settings.display_type + "/template-" + settings.tabs_style + ".css";
        } else {
            css_url = Contextly.Settings.getInstance().getCdnCssUrl() + "_plugin/"  + Contextly.Settings.getInstance().getPluginVersion() +  "/css-api/widget/" + settings.display_type + "/template-" + settings.tabs_style + ".css";
        }
        return css_url;
    },

    getCustomCssCode: function () {
        return Contextly.CssCustomBuilder.getInstance().buildCSS( '.ctx_widget', this.getSettings() );
    },

    fixSnippetPagePosition: function () {
        if ( jQuery( this.getMainWidgetShortCodeId() ).length )
        {
            this.getDisplayElement().appendTo(
                this.getMainWidgetShortCodeId()
            );
        }
        else
        {
            // We need to be sure that our control is last in content element
            if (!this.getDisplayElement().is(":last-child")) {
                this.getDisplayElement().parent().append(this.getDisplayElement());
            }

            // Check for a custom position on page
            var wp_settings = Contextly.Settings.getInstance().getWPSettings();
            if (typeof wp_settings != "undefined" && typeof wp_settings.target_id != "undefined" && wp_settings.target_id) {
                if (typeof wp_settings.block_position != "undefined" && wp_settings.block_position == "before") {
                    this.getDisplayElement().insertBefore(jQuery("#" + wp_settings.target_id));
                } else if (wp_settings.target_id) {
                    this.getDisplayElement().insertAfter(jQuery("#" + wp_settings.target_id));
                }
            }
        }
    },

    getImageDimension: function () {
        return this.getImageDimensionFor( this.getSettings().images_type );
    },

    getImageDimensionFor: function ( image_type ) {
        image_type = image_type.replace( 'square', '').replace( 'letter', '' );

        var dimensions = image_type.split( 'x' );
        var w = 0;
        var h = 0;

        if ( dimensions.length == 2 ) {
            w = parseInt( dimensions[0] );
            h = parseInt( dimensions[1] );
        }

        return {width: w, height: h};
    },

    getImagesHeight: function () {
        var image_dimension = this.getImageDimension();
        return image_dimension.height;
    },

    getImagesWidth: function () {
        var image_dimension = this.getImageDimension();
        return image_dimension.width;
    },

    getWidget: function () {
        return this.widget;
    },

    getWidgetType: function () {
        return this.widget_type;
    },

    getSettings: function () {
        return this.getWidget().settings;
    },

    getWidgetLinks: function () {
        if ( this.getWidget() && this.getWidget().links ) {
            return this.getWidget().links;
        }

        return null;
    },

    getWidgetSectionLinks: function ( section ) {
        var widget_links = this.getWidgetLinks();

        if ( widget_links && widget_links[ section ] ) {
            return widget_links[ section ];
        }

        return null;
    },

    getLinkThumbnailUrl: function ( link ) {
        if ( link.thumbnail_url ) {
            return link.thumbnail_url;
        }

        return null;
    },

    getWidgetCssName: function () {
        return 'default-widget';
    },

    escape: function ( text ) {
        return Contextly.Utils.getInstance().escape( text );
    },

    getOnclickHtml: function ( link ) {
        var settings = this.getSettings();

        if ( settings && settings.utm_enable ) {
            return " onclick=\"" + this.getTrackLinkJSHtml( link ) + "\"";
        }

        return "";
    },

    getLinkATag: function ( link, content ) {
        return "<a class=\"ctx_title ctx_module\" href=\"" +
            this.escape( link.native_url ) + "\" title=\"" +
            this.escape( link.title ) + "\" onmousedown=\"this.href='" +
            this.escape( link.url ) + "'\" " + this.getOnclickHtml( link ) + ">" +
            content + "</a>" + this.getLinkATagIE7Fix();
    },

    getVideoLinkATag: function ( link, content ) {
        return "<a class=\"ctx_title ctx_module\" rel=\"ctx_video_link\" href=\"" +
            this.escape( link.native_url ) + "\" title=\"" +
            this.escape( link.title ) + "\" contextly-url=\"" + link.url + "\" " +
            this.getOnclickHtml( link ) + ">" +
            content + "</a>" + this.getLinkATagIE7Fix();
    },

    getTrackLinkJSHtml: function ( link ) {
        var widget_type = this.escape( this.getWidgetType() );
        var link_type = this.escape( link.type );
        var link_title = this.escape( link.title );

        return this.escape( "Contextly.PageEvents.getInstance().trackLink('" + widget_type + "','" + link_type + "','" + link_title + "');" );
    },

    getLinkATagIE7Fix: function () {
        return "<!--[if lte ie 7]><b></b><![endif]-->";
    },

    getLinkHTML: function ( link ) {
        if ( link.video ) {
            return this.getLinkHTMLVideo( link );
        } else {
            return this.getLinkHTMLNormal( link );
        }
    },

    getInnerLinkHTML: function ( link ) {
        return link.title;
    },

    getLinkHTMLVideo: function ( link ) {
        return "<li>" + this.getVideoLinkATag( link, this.getInnerLinkHTML( link ) ) + "</li>";
    },

    getLinkHTMLNormal: function ( link ) {
        return "<li>" + this.getLinkATag( link, this.getInnerLinkHTML( link ) ) + "</li>";
    },

    getBrandingHtml: function() {
        var html = "<a href='#ctx_branding_content' id='ctx_branding_open' class=\"ctx_pluginauthor ctx_modal_open\"><span>Powered by</span></a>";
        html += "<div id='ctx_branding_content' style=\"display:none;margin:1em;\"><div id=\"ctx_modal\" class=\"ctx_well\">";
        html += "<div id='ctx_popupcontainer'>";
        html += "<span id=\"ctx_poplogo\"></span><span id='ctx_popupperbg'></span><div id='ctx_poptext'>";
        html += "Contextly recommends interesting and related stories using a unique combination of algorithms and editorial choices.<br><br>";
        html += "Publishers or advertisers who would like to learn more about Contextly can contact us&nbsp;";
        html += "<a href=\"http://contextly.com/sign-up/publishers/\" target=\"_blank\">here</a>.<br><br>";
        html += "We respect ";
        html += "<a href=\"http://contextly.com/privacy/\" target=\"_blank\">readers' privacy </a>.&nbsp;";
        html += "</div></div>";
        html += "<span id='ctx_popsymbol'></span>";
        html += "</div></div>";

        return html;
    },

    isDisplayContextlyLogo: function() {
        return Contextly.Settings.getInstance().isDisplayBranding();
    },

	setResponsiveFunction: function() {

		function ctxResponsiveResizeHandler() {

			function ctxGetWidget() {
				return jQuery( '.ctx_widget' );
			}

			function ctxGetWidgetType() {
				return ctxGetWidget().attr( 'widget-type' );
			}

			function is_touch_device() {
				return 'ontouchstart' in window;
			};

			function ctxDisplayWidth() {
				var getwidth = jQuery(window).width();
				return getwidth;
			}

			function ctxWidgetWidth() {
				var WidgetWidth = jQuery(ctxGetWidget()).width();
				return WidgetWidth;
			}

			function resizeMinLimit() {
				MinLimit = 480;
				return MinLimit;
			};

			function ctxClassChanger(className) {
				var fullClass = 'ctx_around_site ' + className;
				jQuery('.ctx_around_site').attr('class',fullClass);
			}

			function ctxTextClassChanger(className) {
				var fullClass = 'ctx_see_also ctx_text_widget ' + className;
				jQuery('.ctx_see_also').attr('class',fullClass);
			}

			function ctxSidebarClassChanger(className) {
				var fullClass = 'ctx_widget_hidden ctx_sidebar ctx_sidebar_left ' + className;
				jQuery('.ctx_sidebar_left').attr('class',fullClass);
			}

			if(ctxDisplayWidth() > 605) {
				var cxt_popup_width = 552; cxt_popup_height = 292;
			}
			else {
				var cxt_popup_width = 250; cxt_popup_height = 500;
			}

			jQuery("#ctx_branding_open").prettyPhoto({
				theme:'light_square',
				autoplay_slideshow: false,
				default_width: cxt_popup_width,
				default_height: cxt_popup_height,
				social_tools: false,
				show_title: false
			});

			// blocks2 widget
			if ( ctxGetWidgetType() == 'blocks2' ) {

				if(ctxWidgetWidth() < resizeMinLimit()) {
					ctxClassChanger('ctx_blocks2mobile');
				} else {
					ctxClassChanger('ctx_blocks2site');
				}
			}

			//float widget
			if ( ctxGetWidgetType() == 'float' ) {
				if(ctxWidgetWidth() < resizeMinLimit()) {
					ctxClassChanger('ctx_floatmobile');
				}
				else if(ctxWidgetWidth() < 550 && ctxWidgetWidth() > resizeMinLimit()) {
					ctxClassChanger('ctx_floattablet');
				}
				else {
					ctxClassChanger('ctx_floatsite');
				}
			}

			//blocks widget
			if ( ctxGetWidgetType() == 'blocks' ) {

				if( ctxWidgetWidth() <  500 ) {
					ctxClassChanger('ctx_blockmobile');
				} else {
					ctxClassChanger('ctx_blocksite');
				}

				if( is_touch_device() || ctxDisplayWidth() <  800 ) {
					jQuery(".ctx_blocks_widget li a p").css("height", "auto");
				} else {
					jQuery(".ctx_blocks_widget li a").on("mouseover", function(event){
						jQuery(this).toggleClass('ctx_blocksslider');
						var getTextHeight = jQuery('.ctx_blocksslider p span').height();
						if(getTextHeight>50) {
							jQuery(".ctx_blocksslider p").css("height", getTextHeight);
						}
					});

					jQuery(".ctx_blocks_widget li a").on("mouseout", function(event){
						jQuery(".ctx_blocksslider p").css("height", "46px");
						jQuery(this).removeClass('ctx_blocksslider');
					});
				}
			}

			//text widget
			if ( ctxGetWidgetType() == 'default' ) {
				if( ctxWidgetWidth() <  520 ) {
					ctxTextClassChanger('ctx_textmobile');
				} else {
					ctxTextClassChanger('ctx_textsite');
				}
			}

			//sidebar
			var getLeftSidebarWidth = jQuery('.ctx_sidebar_link').width();
			if(getLeftSidebarWidth < 256) {
				ctxSidebarClassChanger('ctx_sidebarmobile');
			} else {
				ctxSidebarClassChanger('ctx_sidebarsite');
			}

		}

		function ctxCheckIfWidgetLoadedAndResize() {
			documentLoadCheckCount++;

			ctxResponsiveResizeHandler();
			ctxClearIfWidgetLoadedInterval();

			if ( documentLoadCheckCount > 10 ) {
				ctxClearIfWidgetLoadedInterval();
			}
		}

		function ctxClearIfWidgetLoadedInterval() {
			if ( documentLoadInterval ) {
					clearInterval( documentLoadInterval );
				}
			}

		jQuery(window).resize(
			function() {
				ctxResponsiveResizeHandler();
			}
		);

		var documentLoadInterval = null;
		var documentLoadCheckCount = 0;

		jQuery(document).ready(
			function() {
				documentLoadInterval = self.setInterval(
					function () {
						ctxCheckIfWidgetLoadedAndResize();
					},
					500
				);
			}
		);
	}

});

Contextly.CssCustomBuilder = Contextly.createClass({
    extend: Contextly.Singleton,

    buildCSSRule: function( entry, prefix, property, value ) {
        if ( !value ) return "";
        return entry + " " + prefix + " {" + property + ": " + Contextly.Utils.getInstance().escape( value ) + "}";
    },

    hex2Vals: function( hex ) {
        if(hex.charAt(0) == "#") hex = hex.slice(1);
        hex = hex.toUpperCase();
        var hex_alphabets = "0123456789ABCDEF";
        var value = new Array(3);
        var k = 0;
        var int1,int2;

        for( var i=0;i<6;i+=2 ) {
            int1 = hex_alphabets.indexOf(hex.charAt(i));
            int2 = hex_alphabets.indexOf(hex.charAt(i+1));
            value[k] = (int1 * 16) + int2;
            k++;
        }

        return(value);
    }
});

//////////////////////////////////////////////////////////////
//                      Text Widget                         //
//////////////////////////////////////////////////////////////

Contextly.SnippetWidgetTextFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetFormatter,

    getWidgetCssName: function () {
        return 'ctx_text_widget';
    },

    getWidgetHTML: function () {
        var div = "";

        div += "<div class='ctx_see_also " + this.getWidgetCssName() + "'>";

        var sections = this.widget.settings.display_sections;

        for ( var section in sections ) {
            var section_name = sections[section];
            if ( this.isDisplaySection( section_name ) ) {
                var section_key = section_name + '_subhead';
                var section_header = this.widget.settings[ section_key ];

                div += "<div class='ctx_previous'>";
                div += "<div class='ctx_subhead'><span class='ctx_subhead_title'>" + this.escape( section_header ) + "</span></div>";
                div += "<ul class='ctx_link'>" + this.getLinksHTMLOfType( section_name ) + "</ul>";
                div += "</div>";
            }
        }
        div += "</div>";

		if ( this.isDisplayContextlyLogo() ) {
            div += "<div class='ctx_branding'>" + this.getBrandingHtml() + "</div>";
        }

        return div;
    },

    getLinksHTMLOfType: function( type ) {
        var html = "";
        var widget = this.widget;

        if ( widget.links && widget.links[ type ] ) {
            for ( var link_idx in widget.links[ type ] ) {
                var link = widget.links[ type ][ link_idx ];

                if ( link.id && link.title ) {
                    html += this.getLinkHTML( link );
                }
            }
        }

        return html;
    },

    getCustomCssCode: function () {
        return Contextly.TextWidgetCssCustomBuilder.getInstance().buildCSS( '.ctx_widget', this.getSettings() );
    }

});

Contextly.TextWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings ) {
        var css_code = "";

        if ( settings.css_code ) css_code += Contextly.Utils.getInstance().escape( settings.css_code );
        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".ctx_text_widget .ctx_link" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".ctx_text_widget .ctx_link" , "font-size", settings.font_size );
        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".ctx_text_widget a.ctx_title" , "color", settings.color_links );
        }
        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".ctx_text_widget .ctx_subhead" , "background-color", settings.color_background );
        }

        return css_code;
    }

});

//////////////////////////////////////////////////////////////
//                      Tabs Widget                         //
//////////////////////////////////////////////////////////////

Contextly.SnippetWidgetTabsFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetFormatter,

	getTabsWidget: function () {
		return jQuery('.ctx_widget');
	},

	getWidgetCssName: function () {
        return 'ctx_tabs_widget';
    },

	getWidgetWidth: function () {
        return this.getTabsWidget().width();
	},

	WidgetIsChromeBlocks: function () {
		var width = this.getWidgetWidth();

        if( width < 400 && width > 0 ) {
			return true;
		} else {
            return false;
        }
	},

    getWidgetHTML: function () {
        var div = "<div class='ctx_see_also " + this.getWidgetCssName() + "'>";
        var sections = this.widget.settings.display_sections;

        div += "<ul class=\"ctx_tabs\">";

        if( this.WidgetIsChromeBlocks()==true ) {
            this.getTabsWidget().removeClass('ctx_tabs_site');
            this.getTabsWidget().addClass('ctx_tabs_block');
            for ( var section in sections ) {
                var section_name = sections[section];
                if ( this.isDisplaySection( section_name ) ) {

                    var section_key = section_name + '_subhead';
                    var section_header = this.widget.settings[ section_key ];

                    div += "<li id='ctx_linker_tab_" + section_name + "'>";
                    div += "<span style='width: 100%'>" + this.escape( section_header ) + "</span>";

                    div += "<div id='ctx_linker_content_" + section_name + "' class='ctx_content'  style='display: block;'>"
                        + "<ul class='ctx_link " + ( this.hasImagesForLinks( section_name ) ? 'linker_images' : 'ctx_chrome_noimages' ) + " '>"
                        + this.getLinksHTMLOfType( section_name )
                        + "</ul>"
                        + "</div>";

                    div += "</li>";
                }
            }
            div += "</ul>";
		} else {
            this.getTabsWidget().removeClass('ctx_tabs_block');
            this.getTabsWidget().addClass('ctx_tabs_site');
            var active_flag = false;

            for ( var section in sections ) {
                var section_name = sections[section];

                if ( this.isDisplaySection( section_name ) ) {
                    var section_key = section_name + '_subhead';
                    var section_header = this.widget.settings[ section_key ];

                    div += "<li id='ctx_linker_tab_" + section_name + "' " + (!active_flag ? "class='active'" : "") + ">";
                    div += "<a href='javascript:;' onclick='Contextly.PageEvents.getInstance().switchTab(\"" + this.widget.settings.id + "\", \"" + section_name + "\")'><span>" + this.escape( section_header ) + "</span></a>";
                    div += "</li>";
                    active_flag = true;
                }
            }

            div += "</ul>";

            active_flag = false;
            for (var section in sections) {
                var section_name = sections[section];
                if ( this.isDisplaySection( section_name ) ) {
                    div += "<div id='ctx_linker_content_" + section_name + "' class='ctx_content' " + (!active_flag ? "style='display: block;'" :"") + ">"
                        + "<ul class='ctx_link " + ( this.hasImagesForLinks( section_name ) ? 'linker_images' : 'ctx_chrome_noimages' ) + " '>"
                        + this.getLinksHTMLOfType( section_name )
                        + "</ul>"
                        + "</div>";
                    active_flag = true;
                }
            }
		}

        if ( this.isDisplayContextlyLogo() ) {
            div += "<div class='ctx_branding'>" + this.getBrandingHtml() + "</div>";
        }

        div += "</div>";

        return div;
    },

    getLinksHTMLOfType: function( type ) {
        var html = "";
        var links = this.getWidgetSectionLinks( type );

        if ( links ) {
            for ( var i = 0; i < links.length; i++ ) {
                var link = links[ i ];

                if ( link.id && link.title ) {
                    html += "<li " + ( ( parseInt( i ) + 1 ) > this.widget.settings.links_limit ? "class='li" + type +"'" : "" ) + ">";
                    html += this.getLinkHTML( link );
                    html += "</li>";
                }

            }

            if ( links.length > this.widget.settings.links_limit ) {
                html += "<p class=\"show-more\" id=\"pmore" + type + "\"><a href=\"javascript:;\"  onclick=\"Contextly.PageEvents.getInstance().showMore('" + this.widget.settings.id + "', '" + type + "');\" name=\"amore" + type + "\">Show More</a></p>";
            }
        }

        return html;
    },

    getLinkHTML: function ( link ) {
        var item_style='';

        if ( link.thumbnail_url ) {
			if( this.WidgetIsChromeBlocks()==true ) {
				item_style += "style='height: 70px'";
			} else {
				item_style += "style='height: " + this.getImagesHeight() + "px'";
			}
        }

        var html = "<ul class='ctx_horizontal_line' " + item_style + ">";

        if ( link.thumbnail_url ) {

			if( this.WidgetIsChromeBlocks()==true ) {
				var image_width = 70;
			} else { var image_width = this.getImagesWidth(); }

            var image_li_width = image_width + 8;
            var image_html = "<img src='" + link.thumbnail_url + "' style='width: " + image_width + "px !important;' />";
            var image_href;

            if ( link.video ) {
                image_href = this.getVideoLinkATag( link, image_html );
            } else {
                image_href = this.getLinkATag( link, image_html );
            }

            html += "<li style='width: " + image_li_width + "px;'>" + image_href + "</li>";
        }

        if ( link.video ) {
            html += "<li>" + this.getVideoLinkATag( link, link.title ) + "</a>";
        } else {
            html += "<li>" + this.getLinkATag( link, link.title ) + "</a>";
        }

        if ( this.widget.settings.display_link_dates && link.publish_date ) {
            html += " <span class='link-pub-date'>" + Contextly.Utils.getInstance().dateTextDiff( link.publish_date ) + "</span>";
        }

        html += this.getLinkATagIE7Fix() + "</li>";
        html += "</ul>";

        return html;
    },

    hasImagesForLinks: function( type ) {
        var img_count = 0;
        for ( var link_idx in this.widget.links[ type ] ) {
            if ( this.widget.links[ type ][ link_idx ].thumbnail_url ) img_count++;
        }
        if ( this.widget.links[ type ].length == img_count ) return true;
    },

    getCustomCssCode: function () {
        return Contextly.TabsWidgetCssCustomBuilder.getInstance().buildCSS( '.ctx_widget', this.getSettings() );

    }

});

Contextly.TabsWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings )
    {
        var css_code = "";

        if ( settings.css_code ) css_code += Contextly.Utils.getInstance().escape( settings.css_code );

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".ctx_tabs_widget .ctx_link" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".ctx_tabs_widget .ctx_link" , "font-size", settings.font_size );

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".ctx_tabs_widget .ctx_content" , "background-color", settings.color_background );
            css_code += this.buildCSSRule( entry, ".ctx_tabs_widget .ctx_images img" , "border-color", settings.color_background );
        }

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".ctx_tabs_widget .ctx_content li a" , "color", settings.color_links );
            css_code += this.buildCSSRule( entry, ".ctx_tabs_widget .ctx_content span" , "color", settings.color_links );
        }

        if ( settings.color_border ) {
            css_code += this.buildCSSRule( entry, ".ctx_tabs_widget .ctx_content" , "border-color", settings.color_border );
            css_code += this.buildCSSRule( entry, ".ctx_tabs_widget .ctx_tabs li.active span" , "border-color", settings.color_border );
            css_code += this.buildCSSRule( entry, ".ctx_tabs_widget .ctx_tabs span" , "border-color", settings.color_border );
        }

        if ( settings.color_active_tab ) css_code += this.buildCSSRule( entry, ".ctx_tabs_widget .ctx_tabs li.active span" , "background", settings.color_active_tab );

        if ( settings.color_inactive_tab ) css_code += this.buildCSSRule( entry, ".ctx_tabs_widget .ctx_tabs span" , "background", settings.color_inactive_tab );

        return css_code;
    }
});

//////////////////////////////////////////////////////////////
//                    Blocks Widget                         //
//////////////////////////////////////////////////////////////
Contextly.SnippetWidgetBlocksFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetTextFormatter,

    getNumberOfLinksPerSection: function () {
        return 4;
    },

    getLinksHTMLOfType: function( type ) {
        var html = "";
        var widget = this.widget;
        var links_limit = this.getNumberOfLinksPerSection();

        if ( widget.links && widget.links[ type ] ) {
            for ( var link_idx in widget.links[ type ] ) {
                if ( link_idx >= links_limit ) break;

                var link = widget.links[ type ][ link_idx ];

                if ( link.id && link.title ) {
                    html += this.getLinkHTML( link );
                }
            }
        }

        return html;
    },

    getWidgetCssName: function () {
        return 'ctx_blocks_widget';
    },

    getWidgetHTML: function () {
        var div = "";

        div += "<div class='ctx_see_also " + this.getWidgetCssName() +"'>";

        var sections = this.widget.settings.display_sections;

        div += "<div class='ctx_around_site'>";
        for ( var section in sections ) {
            var section_name = sections[section];

            if ( this.isDisplaySection( section_name ) ) {
                var section_key = section_name + '_subhead';
                var section_header = this.widget.settings[ section_key ];

                div += "<div class='ctx_previous'>";
                div += "<div class='ctx_subhead'><span class='ctx_subhead_title'>" + this.escape( section_header ) + "</span></div>";
                div += "<ul class='ctx_link'>" + this.getLinksHTMLOfType( section_name ) + "</ul>";
                div += "</div>";
            }
        }
        div += "</div>";

        if ( this.isDisplayContextlyLogo() ) {
            div += "<div class='ctx_branding'>" + this.getBrandingHtml() + "</div>";
        }

        return div;
    },

    getLinkHTML: function ( link ) {
        if ( link.video ) {
            return this.getLinkHTMLVideo( link );
        } else {
            return this.getLinkHTMLNormal( link );
        }
    },

    getInnerLinkHTML: function ( link ) {
        var inner_html = "<p class='ctx_link'><span>" + link.title + "</span></p>";
        if ( this.getLinkThumbnailUrl( link ) ) {
            inner_html += "<img src='" + link.thumbnail_url + "' />";
        }

        return inner_html;
    },

    getLinkHTMLVideo: function ( link ) {
        return "<li>" + this.getVideoLinkATag( link, this.getInnerLinkHTML( link ) ) + "</li>";
    },

    getLinkHTMLNormal: function ( link ) {
        return "<li>" + this.getLinkATag( link, this.getInnerLinkHTML( link ) ) + "</li>";
    },

    getCustomCssCode: function () {
        return Contextly.BlocksWidgetCssCustomBuilder.getInstance().buildCSS( '.ctx_widget', this.getSettings() );
    }

});

Contextly.BlocksWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings )
    {
        var css_code = "";

        if ( settings.css_code ) css_code += Contextly.Utils.getInstance().escape( settings.css_code );

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".ctx_blocks_widget p.ctx_link" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".ctx_blocks_widget p.ctx_link" , "font-size", settings.font_size );

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".ctx_blocks_widget .ctx_link span" , "color", settings.color_links );
        }

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".ctx_blocks_widget .ctx_subhead" , "background-color", settings.color_background );
        }

        if ( settings.color_border ) {
            var color_border = settings.color_border;
            var rgb = this.hex2Vals( color_border );

            if ( rgb.length == 3 ) {
                var r = rgb[0];
                var g = rgb[1];
                var b = rgb[2];

                css_code += this.buildCSSRule( entry, ".ctx_blocks_widget li p" , "background", "rgba("+r+","+g+","+b+",0.5)" );
            }
        }

        return css_code;
    }
});

//////////////////////////////////////////////////////////////
//                   Blocks2 Widget                         //
//////////////////////////////////////////////////////////////
Contextly.SnippetWidgetBlocks2Formatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetBlocksFormatter,

    getWidgetCssName: function () {
        return 'ctx_blocks_widget2';
    },

    getInnerLinkHTML: function ( link, is_video ) {
        var inner_html = "";
        if ( this.getLinkThumbnailUrl( link ) ) {
            if ( is_video ) {
                inner_html += "<div class='playbutton-wrapper'>";
            }
            inner_html += "<img src='" + link.thumbnail_url + "' />";
            if ( is_video ) {
                inner_html += "</div>";
            }
        }
        inner_html += "<p class='ctx_link'><span>" + link.title + "</span></p>";

        return inner_html;


    },

    getLinkHTMLVideo: function ( link ) {
        return "<li>" + this.getVideoLinkATag( link, this.getInnerLinkHTML( link, true ) ) + "</li>";
    },

    getCustomCssCode: function () {
        return Contextly.Blocks2WidgetCssCustomBuilder.getInstance().buildCSS( '.ctx_widget', this.getSettings() );
    }

});

Contextly.Blocks2WidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings )
    {
        var css_code = "";

        if ( settings.css_code ) css_code += Contextly.Utils.getInstance().escape( settings.css_code );

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".ctx_blocks_widget2 p.ctx_link" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".ctx_blocks_widget2 p.ctx_link" , "font-size", settings.font_size );

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".ctx_blocks_widget2 .ctx_link span" , "color", settings.color_links );
        }

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".ctx_blocks_widget2 .ctx_subhead" , "background-color", settings.color_background );
        }

        return css_code;
    }
});


//////////////////////////////////////////////////////////////
//                   Float Widget                           //
//////////////////////////////////////////////////////////////
Contextly.SnippetWidgetFloatFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetBlocksFormatter,

    getWidgetCssName: function () {
        return 'ctx_float_widget';
    },

    getNumberOfLinksPerSection: function () {
        return this.getSettings().links_limit;
    },

    getInnerLinkHTML: function ( link, is_video ) {
        var inner_html = "";
        if ( this.getLinkThumbnailUrl( link ) ) {
            if ( is_video ) {
                inner_html += "<div class='playbutton-wrapper'>";
            }
            inner_html += "<img src='" + link.thumbnail_url + "' />";
            if ( is_video ) {
                inner_html += "</div>";
            }
        }

        var text_width = this.getImagesWidth() + 10;
        inner_html += "<p class='ctx_link' style='width: " + text_width + "px;'><span>" + link.title + "</span></p>";

        return inner_html;
    },

    getLinkHTMLVideo: function ( link ) {
        return "<li>" + this.getVideoLinkATag( link, this.getInnerLinkHTML( link, true ) ) + "</li>";
    },

    getCustomCssCode: function () {
        return Contextly.FloatWidgetCssCustomBuilder.getInstance().buildCSS( '.ctx_widget', this.getSettings() );
    }

});

Contextly.FloatWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings ) {
        var css_code = "";
        if ( settings.css_code ) css_code += Contextly.Utils.getInstance().escape( settings.css_code );

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".ctx_float_widget .ctx_link" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".ctx_float_widget .ctx_link" , "font-size", settings.font_size );

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".ctx_float_widget .ctx_link span" , "color", settings.color_links );
        }

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".ctx_float_widget .ctx_subhead" , "background-color", settings.color_background );
        }

        return css_code;
    }

});

//////////////////////////////////////////////////////////////
//                   Sidebar Widget                         //
//////////////////////////////////////////////////////////////
Contextly.SidebarWidgetFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetTabsFormatter,

    construct: function( widget ) {
        if ( widget ) {
            this.widget = widget;
            this.widget_type = Contextly.WidgetType.SIDEBAR;
            this.widget_html_id = 'contextly-' + widget.id;
        }
    },

    getWidgetHTML: function()
    {
        return "<div class='ctx_content'><ul class='ctx_sidebar_link " + ( this.hasImagesForLinks( 'previous' ) ? 'ctx_images' : 'ctx_noimages' ) + "'>"
            + this.getLinksHTMLOfType( 'previous' )
            + "</ul></div>";
    },
    getLinksHTMLOfType: function( type )
    {
        var html = "";
        if ( this.widget && this.widget.links && this.widget.links[ type ] ) {
            for ( var link_idx in this.widget.links[ type ] ) {
                var link = this.widget.links[ type ][ link_idx ];

                if ( link.id && link.title ) {
                    html += "<li>" + this.getLinkHTML( link ) + "</li>";
                }
            }
        }

        return html;
    },

    display: function () {
        var self = this;

        jQuery( document ).ready(
            function () {
                if ( self.hasWidgetData() && !Contextly.Settings.getInstance().isAdmin() ) {
                    // Build widget html and display it
                    var html = self.getWidgetHTML();
                    self.displayText( html );

                    // Do some sidebar modifications
                    self.getDisplayElement().removeClass( 'ctx_sidebar_hidden' )
                        .addClass( 'ctx_sidebar' )
                        .addClass( 'ctx_sidebar_' + self.widget.layout );

                    // Check if we need to add sidebar title and description
                    var title = self.widget.name;
                    var description = self.widget.description;
                    var sidebar_content = self.getDisplayElement().find( '.ctx_content' );

                    if ( description ) sidebar_content.prepend( "<div class='ctx_sidebar_description'>" + self.escape( description ) + "</div>" );
                    if ( title ) sidebar_content.prepend( "<div class='ctx_sidebar_title'>" + self.escape( title ) + "</div>" );

                    self.loadCss( 'sidebar-css' );
                }
            }
        );
    },

    getWidgetCSSUrl: function () {
        var css_url;
        var settings = this.getSettings();

        if ( Contextly.Settings.getInstance().getMode() == 'local' ) {
            css_url = "http://linker.site/resources/css/plugin/sidebar/template-" + settings.theme + ".css";
        } else if ( Contextly.Settings.getInstance().getMode() == 'dev' ) {
			css_url = "http://dev.contextly.com/resources/css/plugin/sidebar/template-" + settings.theme + ".css";
        } else {
            css_url = Contextly.Settings.getInstance().getCdnCssUrl() + "_plugin/"  + Contextly.Settings.getInstance().getPluginVersion() +  "/css-api/sidebar/template-" + settings.theme + ".css";
        }
        return css_url;
    },

    getCustomCssCode: function () {
        return Contextly.SidebarWidgetCssCustomBuilder.getInstance().buildCSS( '.ctx_sidebar', this.getSettings() );
    }

});

Contextly.SidebarWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings ) {
        var css_code = "";

        if ( settings.css_code ) {
            var site_custom_code = Contextly.Utils.getInstance().escape( settings.css_code );
            if ( site_custom_code.indexOf( entry ) == -1 ) {
                site_custom_code += entry + site_custom_code;
            }

            css_code += site_custom_code;
        }

        if ( settings.font_family ) {
            css_code += this.buildCSSRule( entry, "a.ctx_title" , "font-family", settings.font_family );
            css_code += this.buildCSSRule( entry, ".ctx_sidebar_title" , "font-family", settings.font_family );
            css_code += this.buildCSSRule( entry, ".ctx_sidebar_description" , "font-family", settings.font_family );
        }
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, "a.ctx_title" , "font-size", settings.font_size );

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".ctx_content" , "background-color", settings.color_background );
            css_code += this.buildCSSRule( entry, ".ctx_images img" , "border-color", settings.color_background );
        }

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".ctx_content .ctx_title" , "color", settings.color_links );
            css_code += this.buildCSSRule( entry, ".ctx_content span" , "color", settings.color_links );
        }

        if ( settings.color_border ) {
            css_code += this.buildCSSRule( entry, ".ctx_content" , "border-color", settings.color_border + " !important;" );
        }

        if ( settings.title_font_size ) {
            css_code += this.buildCSSRule( entry, ".ctx_sidebar_title" , "font-size", settings.title_font_size );
        }

        if ( settings.description_font_size ) {
            css_code += this.buildCSSRule( entry, ".ctx_sidebar_description" , "font-size", settings.description_font_size );
        }

        return css_code;
    }

});

Contextly.Utils = Contextly.createClass({
    extend: Contextly.Singleton,

    dateTextDiff: function ( date ) {
        if ( date && date.length > 4 )
        {
            var t = date.split(/[- :]/);
            var js_date = new Date(t[0], t[1]-1, t[2], t[3], t[4], t[5]);

            var timestamp = js_date.getTime() / 1000;
            var difference = new Date().getTime()/1000 - timestamp;

            var periods = new Array("sec", "min", "hour", "day", "week", "month", "year", "decade");
            var lengths = new Array("60","60","24","7","4.35","12","10");
            var ending;

            if (difference > 0) {
                // this was in the past
                ending = "ago";
            } else { // this was in the future
                return 'right now';
            }

            for (var j = 0; difference >= lengths[j]; j++) difference /= lengths[j];
            difference = Math.round(difference);

            if (difference != 1) periods[j] += "s";
            return difference + "&nbsp;" +periods[j] + "&nbsp;" + ending;
        }
    },

    stringToDate: function( mysql_date ) {
        var dateParts = mysql_date.split(' ')[0].split('-');
        var timeParts = mysql_date.split(' ')[1].split(':');
        var date = new Date(dateParts[0], dateParts[1], dateParts[2]);
        date.setHours(timeParts[0], timeParts[1], timeParts[2])

        return date;
    },

    isPostNeedsToBeUpdated: function ( db_date, cms_post_date ) {
        if ( db_date != cms_post_date ) {
            var post_date = Contextly.Utils.getInstance().stringToDate( cms_post_date );
            var cur_date = new Date().getTime();

            var diff = Math.abs( ( cur_date - post_date ) / 1000 );
            var allowed_diff = 60 * 60 * 24 * 365 * 3; // 3 years

            // Don't allow to update very old posts
            if ( diff <= allowed_diff ) {
                return true;
            }
        }

        return false;
    },

    isIE7: function () {
        if( navigator.userAgent.match( /MSIE ([0-9]+)\./ ) ) {
            if ( RegExp.$1 < 8 ) {
                return true;
            }
        }
        return false;
    },

    loadCssFile: function ( css_url, contextly_id ) {
        if ( contextly_id ) {
            // Remove previously loaded script
            jQuery( 'link[contextly_id="' + contextly_id + '"]').remove();
        }

        jQuery( "head" ).append( "<link>" );
        var css_node = jQuery( "head" ).children( ":last" );
        css_node.attr({
            rel:    "stylesheet",
            media:  "screen",
            type:   "text/css",
            href:   css_url,
            contextly_id:   contextly_id
        });
    },

    loadCustomCssCode: function ( custom_css, contextly_id ) {
        if ( contextly_id ) {
            // Remove previously loaded script
            jQuery( 'style[contextly_id="' + contextly_id + '"]').remove();
        }

        jQuery( "head" ).append( jQuery( "<style type='text/css' contextly_id='" + contextly_id + "'>" + custom_css + "</style>" ) );
    },

    escape: function ( text ) {
        if ( text ) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }


        return '';
    }

});

Contextly.PageEvents = Contextly.createClass({
    extend: Contextly.Singleton,

    switchTab: function ( setting_id, tab ) {
        jQuery("#ctx_linker_content_previous,#ctx_linker_content_web,#ctx_linker_content_interesting,#ctx_linker_content_custom").hide();
        jQuery("#ctx_linker_tab_previous,#ctx_linker_tab_web,#ctx_linker_tab_interesting,#ctx_linker_tab_custom").attr( "class", "" );
        jQuery("#ctx_linker_content_" + tab).show();
        jQuery("#ctx_linker_tab_" + tab).attr( "class", "active" );

        Contextly.Loader.getInstance().trackPageEvent( setting_id, "switch_tab", tab );
    },

    showMore: function ( setting_id, tab ) {
        jQuery( '.li' + tab ).toggleClass( "show" );
        var pmore = jQuery( '#pmore' + tab );
        pmore.toggleClass("show");

        if (pmore.hasClass('show')) {
            pmore.find('a').text('Show Fewer');
        } else {
            pmore.find('a').text('Show More');
        }

        Contextly.Loader.getInstance().trackPageEvent( setting_id, "show_more", tab );
    },

    trackLink: function ( widget_type, link_type, link_title ) {
        if ( !widget_type || !link_type || !link_title ) return;

        var label_limit = 30;
        var category = 'ContextlyWidget';
        var action = 'ClickedOutBound';
        var label = link_title;

        if ( widget_type == Contextly.WidgetType.SIDEBAR ) {
            category = 'ContextlySidebar';
        }

        if ( label.length > label_limit ) {
            label = label.substr( 0, label_limit );
        }

        if( widget_type == Contextly.WidgetType.SIDEBAR && ( link_type == Contextly.LinkType.WEB || link_type == Contextly.LinkType.PREVIOUS ) ) {
            action = 'ClickedRecentRelated';
        } else if ( link_type == Contextly.LinkType.PREVIOUS ) {
            action = 'ClickedPreviousRelated';
        } else if( link_type == Contextly.LinkType.RECENT ) {
            action = 'ClickedRecentRelated';
        } else if( link_type == Contextly.LinkType.PROMO ) {
            action = 'ClickedPromoLink';
        } else {
            action = 'Clicked' + link_type.charAt(0).toUpperCase() + link_type.slice(1);
        }

        if ( typeof pageTracker != 'undefined' ) {
            this.trackLinkOldStyle(category, action, label);
        } else if ( typeof _gaq != 'undefined' ) {
            this.trackLinkNewStyle(category, action, label);
        }
    },

    trackLinkOldStyle: function (category, action, label) {
        pageTracker._trackEvent(category, action, label);
    },

    trackLinkNewStyle: function (category, action, label) {
        _gaq.push(['_trackEvent', category, action, label]);
    }

});

Contextly.Settings = Contextly.createClass({
    extend: Contextly.Singleton,

    getAPIServerUrl: function () {
        return Contextly.api_server;
    },
    getMainServerUrl: function () {
        return Contextly.main_server;
    },
    getPopupServerUrl: function () {
        return Contextly.popup_server;
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
    getPageId: function () {
        return Contextly.post.post_id;
    },
    getMode: function () {
        return Contextly.mode;
    },
    getPostModifiedDate: function () {
        return Contextly.post.post_modified;
    },
    getPostCreatedDate: function () {
        return Contextly.post.post_date;
    },
    getAuthorId: function () {
        return Contextly.post.author;
    },
    getWPSettings: function () {
        return Contextly.settings;
    },
    isHttps: function () {
        return Contextly.https;
    },
    getCdnCssUrl: function () {
        if ( this.isHttps() ) {
            return 'https://c713421.ssl.cf2.rackcdn.com/';
        } else {
            return 'http://contextlysiteimages.contextly.com/';
        }
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
    isDisplayBranding: function () {
        return !this.isAdmin();
    }
});

Contextly.RESTClient = Contextly.createClass({
    extend: Contextly.Singleton,

    getApiRPC: function () {
        if ( !this.api_rpc ) {
            var remote_url = Contextly.Settings.getInstance().getAPIServerUrl() + '/easy_xdm/cors/index.html';
            this.api_rpc = new easyXDM.Rpc({
                    remote: remote_url
                },
                {
                    remote: {
                        request: {}
                    }
                });
        }
        return this.api_rpc;
    },

    call: function ( api_name, api_method, params, callback ) {
        var contextly_settings = Contextly.Settings.getInstance();
        var api_url = contextly_settings.getAPIServerUrl() + api_name + '/' + api_method + '/';

        params = jQuery.extend(
            params,
            {
                version:    contextly_settings.getPluginVersion(),
                site_path:  contextly_settings.getAppId(),
                admin:      contextly_settings.isAdmin(),
                page_id:    contextly_settings.getPageId(),
                cookie_id:  Contextly.Loader.getInstance().getCookieId()
            }
        );

        var self = this;
        this.getApiRPC().request(
            {
                url: api_url,
                method: "POST",
                data: params
            },
            function ( response )
            {
                self.restCallback( response, callback )
            }
        );
    },

    restCallback: function ( response, callback )
    {
        if ( !response.data ) return;

        var data = easyXDM.getJSONObject().parse( response.data );

        this.restDebug( data )
        callback( data );
    },

    restDebug: function ( response )
    {
        if ( response.q ) {
            if ( response.t ) console.log( 'Time: ' + response.t );
            if ( response.m ) console.log( 'Memory: ' + response.m );
            for ( var i in response.q ) {
                console.log( response.q[ i ] );
            }
        }
    }
});

Contextly.MainServerAjaxClient = Contextly.createClass({
    extend: Contextly.Singleton,

    getXhr: function () {
        if ( !this.xhr ) {
            var remote_url = Contextly.Settings.getInstance().getMainServerUrl() + '/easy_xdm/cors/index.html';
            remote_url = remote_url.replace('https://', 'http://');

            this.xhr = new easyXDM.Rpc({
                    remote: remote_url
                },
                {
                    remote: {
                        request: {}
                    }
                });
        }
        return this.xhr;
    },

    call: function ( url, callback ) {
        this.getXhr().request(
            {
                url: url,
                method: "POST"
            },
            function ( response ) {
                if ( callback ) {
                    callback( response );
                }
            }
        );
    }

});

Contextly.PopupHelper = Contextly.createClass({
    extend: Contextly.Singleton,

    construct: function() {
        this.popup_socket   = null;
    },

    initWithWidget: function ( widget ) {
        this.widget         = widget;
        this.snippet        = null;

        if ( widget && widget.snippets && widget.snippets.length ) {
            var snippet = widget.snippets[0];

            if ( snippet.id ) {
                this.snippet = snippet;
            }
        }
    },

    initWithUrl: function ( url ) {
        this.url = url;
    },

    snippetPopup: function () {
        if ( this.url || !this.widget ) {
            this.showStubPopup();
            return;
        }

        var settings = Contextly.Settings.getInstance();
        var popup_url = settings.getPopupServerUrl()
            + 'sites/' + settings.getAppId() + '/'
            + '?page_id=' + settings.getPageId()
            + '&author=' + settings.getAuthorId()
            + '&edit_snippet_id=' + ( this.snippet ? this.snippet.id : '' );

        this.openPopupWithCallback(
            popup_url,
            function ( response ) {
                Contextly.Loader.getInstance().load();
            }
        );
    },

    sidebarPopup: function ( snippet_id ) {
        if ( this.url || !this.widget ) {
            this.showStubPopup();
            return;
        }

        var settings = Contextly.Settings.getInstance();
        var popup_url = settings.getPopupServerUrl()
            + 'sites/' + settings.getAppId() + '/sidebar/'
            + '?page_id=' + settings.getPageId()
            + '&sidebar_id=' + ( snippet_id ? snippet_id : '' );

        this.openPopupWithCallback(
            popup_url,
            function ( response ) {
                if ( response.status == 'ok' && response.snippet_id ) {
                    var api_response = response.data;

                    if ( api_response.entry && api_response.entry.type == Contextly.WidgetType.AUTO_SIDEBAR ) {
                        send_to_editor( '[contextly_auto_sidebar id="' + response.snippet_id + '"]' );
                    } else {
                        send_to_editor( '[contextly_sidebar id="' + response.snippet_id + '"]' );
                    }

                    Contextly.Loader.getInstance().load();
                }
            }
        );
    },

    linkPopup: function () {
        if ( this.url || !this.widget ) {
            this.showStubPopup();
            return;
        }

        var settings = Contextly.Settings.getInstance();
        var popup_url = settings.getPopupServerUrl()
            + 'sites/' + settings.getAppId() + '/'
            + '?page_id=' + settings.getPageId()
            + '&edit_snippet_id=' + ( this.snippet ? this.snippet.id : '' )
            + '&tinymce_link_text=' + tinymce.plugins.ContextlyPluginLink.getSelectedText();

        this.openPopupWithCallback(
            popup_url,
            function ( response ) {
                if ( response.status == 'ok' ) {
                    Contextly.Loader.getInstance().load();
                    tinymce.activeEditor.plugins.contextlylink.insertLink( response.link_url, response.link_title );
                }
            }
        );
    },

    openPopupWithCallback: function ( popup_url, callback ) {
        window.open(
            popup_url + '#easyXDM_linker_channel_provider',
            'contextlyapp',
            'width=1150,height=600,resizable=1,scrollbars=1,menubar=1'
        );

        if ( callback ) {
            if ( this.popup_socket != null ) {
                this.popup_socket.destroy();
            }

            this.popup_socket = new easyXDM.Socket({
                channel: 'linker_channel',
                remote: Contextly.Settings.getInstance().getPopupServerUrl() + '/resources/html/remote.html',
                onMessage: function( data, origin ) {
                    if ( data ) {
                        var json_data = easyXDM.getJSONObject().parse( data );
                        callback( json_data );
                    }
                }
            });
        }
    },

    showStubPopup: function () {
        this.url = this.url || 'http://contextly.com/contact-us/?type=undefined&key=' + Contextly.Settings.getInstance().getAppId();
        window.open( this.url );
    },

    showPublishConfirmation: function () {
        var popup_id = 'contextly_publish_confirmation';
        var contextly_add_related_links_btn = 'contextly_add_related_links_btn';
        var contextly_publish_now_btn = 'contextly_publish_now_btn';

        var popup_width = 420;
        var popup_height = 150;

        var title = 'Publish confirmation';
        var publish_button_value = jQuery( '#publish').attr( "value" );
        var add_related_button_value = "Choose Related Posts";

        jQuery( '.button-primary' ).removeClass( 'button-primary-disabled' );
        jQuery( 'span.spinner').hide();

        jQuery( "body" ).append(
            jQuery(
                '<div id="' + popup_id + '" style="display:none;">' +
                "<div style='float:left; padding:10px;'>This post doesn't have any chosen links to other posts. Would you like to do that now?<br /><br /> If you want to add a sidebar, close this window, put the cursor where you'd like the sidebar to be and click the sidebar button.</div>" +
                '<input id="contextly_add_related_links_btn" type="button" value="' + add_related_button_value + '" class="button button-primary" />' +
                '<input id="contextly_publish_now_btn" type="button" value="' + publish_button_value + '" class="button" style="margin-left: 20px; float: right;" />' +
                '</div>'
            )
        );

        jQuery( '#' + contextly_add_related_links_btn ).click(
            function () {
                tb_remove();
                Contextly.PopupHelper.getInstance().snippetPopup();
            }
        );

        jQuery( '#' + contextly_publish_now_btn ).click(
            function () {
                tb_remove();
                jQuery( '#publish').unbind( 'click' );
                jQuery( '#publish').click();
            }
        );

        tb_show( title, "#TB_inline?height=" + popup_height + "&amp;width=" + popup_width + "&amp;inlineId=" + popup_id );
        jQuery("#TB_window").width( popup_width + 30 );
        jQuery("#TB_window").height( popup_height + 20 );
    }

});

Contextly.SettingsAutoLogin = Contextly.createClass({
    extend: Contextly.Singleton,

    doLogin: function () {
        var settings_button_id = '#contextly-settings-btn';

        jQuery( settings_button_id ).attr( 'disabled', 'disabled' );

        jQuery.ajax({
            url: Contextly.Settings.getInstance().getAjaxUrl(),
            type: 'post',
            dataType: 'json',
            data: {
                action: 'contextly_get_auth_token'
            },
            success: function ( response ) {
                if ( response.success && response.contextly_access_token ) {
                    jQuery( settings_button_id ).attr( 'contextly_access_token', response.contextly_access_token );
                    jQuery( settings_button_id ).removeAttr( 'disabled' );
                } else if ( response.message ) {
                    jQuery( settings_button_id ).parent().append(
                        jQuery( "<p style='color: red; font-weight: bold;'>* You need a valid API key. Click the API tab above to get one.</p>" )
                    );
                }
            },
            error: function () {
                jQuery( settings_button_id ).removeAttr( 'disabled' );
            }
        });
    }

});

// Load Contextly widgets for this page
Contextly.Loader.getInstance().load();
