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
        }
        else
        {
            if ( this.entry ) {
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
            page_id: Contextly.Settings.getInstance().getPageId(),
            contextly_nonce: Contextly.Settings.getInstance().getAjaxNonce()
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
        Contextly.RESTClient.getInstance().call(
            'postsimport',
            'put',
            {
                url: Contextly.Settings.getInstance().getPageUrl()
            },
            function ( response ) {
            }
        );
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

        if ( type == 'tabs' ){
            type = 'blocks';
        }

        if ( type == 'default' ) {
            return new Contextly.SnippetWidgetTextFormatter( widget );
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
        this.widget_html_id = 'ctx-module';
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

            this.attachVideoLinkPopups();
        }
		else
		{
			// Hide content of the snippet placeholder (e.g. Loading...)
			this.getDisplayElement()
				.empty();
		}

        this.setResponsiveFunction();
    },

    attachVideoLinkPopups: function () {
        jQuery( "a[rel='ctx-video-dataurl']" ).click(function(e) {
            e.preventDefault();
            var self = jQuery( this );
            var videoUrl = getVideoUrl( self );
            var videoTitle = getVideoTitle( self );
            openVideoPopup( videoUrl, videoTitle);

            var contextly_url = getContextlyUrl( self );
            Contextly.MainServerAjaxClient.getInstance().call( contextly_url );
        });

        function getVideoClass() {
            return "ctx-video-modal";
        }
        function videoOverlay() {
            return "ctx-video-overlay";
        }
        function getCloseClass() {
            return "ctx-video-close";
        }
        function getVideoUrl( getEvent) {
            return getEvent.attr('href');
        }
        function getContextlyUrl( getEvent) {
            return getEvent.attr('contextly-url');
        }
        function getVideoTitle( getEvent) {
            return getEvent.attr('title');
        }
        function openVideoPopup( ctxVideoUrl, videoTitle ) {
            jQuery('body').append( createVideoTmp( ctxVideoUrl, videoTitle ) );
            showVideoPopup();
        }
        function showVideoPopup() {
            modalFadeIn( getVideoClass() );
            modalFadeIn( videoOverlay() );
            modalClose();
        }
        function modalFadeIn( elClass ) {
            jQuery( "." + elClass ).fadeIn("fast");
        }
        function modalClose() {
            closeVideoEvent();
            closeModalEsc();
            closeModalBg();
        }
        function createVideoTmp( ctxVideoUrl, videoTitle ) {
            var videoLink = getEmbedLink( ctxVideoUrl );
            var videoId = linkFormatter( 'v',ctxVideoUrl );
            var videoClass = getVideoClass();
            var videotmp = '<div class="' + videoClass + '">' +
                '<iframe src="' + videoLink + '" frameborder="no" class="ctx-video-frame"></iframe>' +
                '<div class="ctx-video-loading" ></div>' +
                '<p class="ctx-video-title">' + videoTitle + '</p>' +
                '<a  href="#" class="ctx-video-close">&#215;</a>' +
                '<div class="ctx-modal-social">' + facebookLike( ctxVideoUrl )+ twitterIframe( videoId, videoTitle ) + '</div>' +
                '</div>' +
                '<div class="' + videoOverlay() + '" /></div>';
            return videotmp;
        }
        function facebookLike( ctxVideoUrl ) {
            var script = '<iframe src="//www.facebook.com/plugins/like.php?href=' + ctxVideoUrl +
                '&amp;width=100&amp;layout=button&amp;action=like&amp;show_faces=true&amp;share=true&amp;height=20" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:100px; height:20px;" allowTransparency="true"></iframe>';
            return script;
        }
        function twitterIframe( videoId, videoTitle ) {
            var script = "<iframe allowtransparency='true' frameborder='0' scrolling='no' src='http://platform.twitter.com/widgets/tweet_button.html?text=" + videoTitle + "&url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3D" + videoId + "' style='width:98px; height:20px;'></iframe>";
            return script;
        }
        function getEmbedLink( ctxVideoUrl ) {
            return formattedYoutubeLink( ctxVideoUrl );
        }
        function formattedYoutubeLink( videoUrl ) {
            videoId = linkFormatter('v',videoUrl);
            fullLink = 'http://www.youtube.com/embed/' + videoId + "?rel=1&autoplay=1";
            return fullLink;
        }
        function linkFormatter(name,url){
            name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
            var regexS = "[\\?&]"+name+"=([^&#]*)";
            var regex = new RegExp( regexS );
            var results = regex.exec( url );
            return ( results == null ) ? "" : results[1];
        }
        function closeVideoModal( ) {
            modalFadeOut( getVideoClass() );
            modalFadeOut( videoOverlay() );
        }
        function modalFadeOut( elClass ) {
            var removeEle = function() {
                jQuery(this).remove();
            }
            jQuery( "." + elClass ).fadeOut( "fast", removeEle);
        }
        function closeVideoEvent() {
            jQuery( "." + getCloseClass() ).click(function(e) {
                e.preventDefault();
                closeVideoModal();
            });
        }
        function closeModalEsc() {
            jQuery('body').keyup(function(e) {
                if(e.which===27){ closeVideoModal(); }
            });
        }
        function closeModalBg() {
            jQuery( "." + videoOverlay() ).click(function(e) {
                closeVideoModal();
            });
        }
    },

    loadCss: function ( contextly_id ) {
        var css_url = this.getWidgetCSSUrl();

        Contextly.Utils.getInstance().loadCssFile( css_url, contextly_id );

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
            css_url = Contextly.Settings.getInstance().getCdnCssUrl() + "wp_plugin/"  + Contextly.Settings.getInstance().getPluginVersion() +  "/css-api/widget/" + settings.display_type + "/template-" + settings.tabs_style + ".css";
        }

        return css_url;
    },

    getCustomCssCode: function () {
        return Contextly.CssCustomBuilder.getInstance().buildCSS( '.ctx-module-container', this.getSettings() );
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

    getModuleType: function () {
        return this.getSettings().display_type;
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

    getVideoIcon: function ( is_video ) {
        if ( is_video ) {
            var videoIcon='<span class="ctx-video-icon"></span>';
        } else {
            var videoIcon = "";
        }

        return videoIcon;
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
        return "<a href=\"" +
            this.escape( link.native_url ) + "\" title=\"" +
            this.escape( link.title ) + "\" class='ctx-clearfix ctx-nodefs' onmousedown=\"this.href='" +
            this.escape( link.url ) + "'\" " + this.getOnclickHtml( link ) + ">" +
            content + "</a>";
    },

    getVideoLinkATag: function ( link, content ) {
        var moduleType = this.getModuleType();

        if( moduleType == "default" ) {
            var videoIcon = this.getVideoIcon( link.video );
        } else {
            var videoIcon = "";
        }

        return "<a rel=\"ctx-video-dataurl\" class='ctx-clearfix ctx-nodefs' href=\"" +
            this.escape( link.native_url ) + "\" title=\"" +
            this.escape( link.title ) + "\" contextly-url=\"" + link.url + "\" " +
            this.getOnclickHtml( link ) + ">" +
            videoIcon + " " + content + "</a>";
    },

    getTrackLinkJSHtml: function ( link ) {
        var widget_type = this.escape( this.getWidgetType() );
        var link_type = this.escape( link.type );
        var link_title = this.escape( link.title );

        return this.escape( "Contextly.PageEvents.getInstance().trackLink('" + widget_type + "','" + link_type + "','" + link_title + "');" );
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
        return "<div class='ctx-link'>" + this.getVideoLinkATag( link, this.getInnerLinkHTML( link ) ) + "</div>";
    },

    getLinkHTMLNormal: function ( link ) {
        return "<div class='ctx-link'><div class='ctx-link-title'>" + this.getLinkATag( link, this.getInnerLinkHTML( link ) ) + "</div></div>";
    },

    isDisplayContextlyLogo: function() {
        return Contextly.Settings.getInstance().isDisplayBranding();
    },

    setResponsiveFunction: function() {

        function ctxResponsiveResizeHandler() {
            // Blocks2
            var mobileModule = 400;
            var normalModule = 650;
            var wideModule = 790;

            // Float
            var mobileModuleFl = 240;
            var mediumModuleFl = 400;
            var normalModuleFl = 700;

            // Blocks
            var mobileModuleBl = 200;
            var tabletModuleBl = 450;
            var normalModuleBl = 650;
            var wideModuleBl = 790;

            // Text
            var mobileModuleTx = 450;

            // Sidebar
            var mobileModuleSb = 200;

            function getBlocks2Width() {
                var width = jQuery(".ctx-content-block2").width();
                return width;
            }

            function getFloatWidth() {
                var width = jQuery(".ctx-content-float").width();
                return width;
            }

            function getTextWidth() {
                var width = jQuery(".ctx-content-text").width();
                return width;
            }

            function getSidebarWidth() {
                var width = jQuery(".ctx-sidebar").width();
                return width;
            }

            function getScreenWidth() {
                var getwidth = jQuery(window).width();
                return getwidth;
            }

            function getWidgetType() {
                var widgetType = jQuery( "#ctx-module" ).attr( "widget-type" );
                return widgetType;
            }

            function extraLinksAction( linkNumberArray, linkCondition ) {

                if( linkCondition == "show" ) {
                    var dispCond = "block";
                    var visCond = "visible";
                } else if( linkCondition == "hide" ) {
                    var dispCond = "none";
                    var visCond = "hidden";
                }

                for( var keys in linkNumberArray ) {
                    jQuery( ".ctx-link-additional-" + linkNumberArray ).css( "display", dispCond );
                    jQuery( ".ctx-link-additional-" + linkNumberArray ).css( "visibility", visCond );
                }
            }

            function respClassChanger( respClass, baseClass ) {
                jQuery( "." + baseClass ).attr("class", baseClass + " ctx-nodefs " + respClass);
            }

            function respSbClassChanger( respClass, baseClass, removeClass ) {
                jQuery( "." + baseClass )
                    .addClass( "ctx-sb-clearfix" )
                    .addClass( respClass )
                    .removeClass( removeClass );
            }

            // Blocks
            if(getWidgetType() == 'blocks' ) {
                if(getBlocksWidth() < mobileModuleBl) {
                    respClassChanger( "ctx-module-mobile", "ctx-content-block" );
                    extraLinksAction( [5, 6], "hide" );
                } else if(getBlocksWidth() <= tabletModuleBl && getBlocksWidth() >= mobileModuleBl) {
                    respClassChanger( "ctx-module-tablet", "ctx-content-block" );
                    extraLinksAction( [5, 6], "hide" );
                } else if(getBlocksWidth() <= normalModuleBl && getBlocksWidth() >= tabletModuleBl) {
                    extraLinksAction( [5, 6], "hide" );
                    respClassChanger( "ctx-module-default", "ctx-content-block" );
                } else if(getBlocksWidth() > normalModuleBl && getBlocksWidth() <= wideModuleBl) {
                    respClassChanger( "ctx-module-sec5", "ctx-content-block" );
                    extraLinksAction( [6], "hide" );
                    extraLinksAction( [5], "show" );
                } else if(getBlocksWidth() > wideModuleBl) {
                    respClassChanger( "ctx-module-sec6", "ctx-content-block" );
                    extraLinksAction( [5, 6], "show" );
                }
            }

            // Blocks2
            if(getWidgetType() == 'blocks2' ) {
                if(getBlocks2Width() < mobileModule) {
                    extraLinksAction( [5, 6], "hide" );
                    respClassChanger( "ctx-module-mobile", "ctx-content-block2" );
                } else if(getBlocks2Width() <= normalModule && getBlocks2Width() >= mobileModule) {
                    extraLinksAction( [5, 6], "hide" );
                    respClassChanger( "ctx-module-default", "ctx-content-block2" );
                } else if(getBlocks2Width() > normalModule && getBlocks2Width() <= wideModule) {
                    extraLinksAction( [6], "hide" );
                    extraLinksAction( [5], "show" );
                    respClassChanger( "ctx-module-sec5", "ctx-content-block2" );
                } else if(getBlocks2Width() > wideModule) {
                    respClassChanger( "ctx-module-sec6", "ctx-content-block2" );
                    extraLinksAction( [5, 6], "show" );
                }
            }

            // Float
            if(getWidgetType() == 'float' ) {
                if(getFloatWidth() < mobileModuleFl) {
                    extraLinksAction( [4, 5, 6], "hide" );
                    respClassChanger( "ctx-module-mobile", "ctx-content-float" );
                } else if(getFloatWidth() <= mediumModuleFl && getFloatWidth() >= mobileModuleFl) {
                    extraLinksAction( [4, 5, 6], "hide" );
                    respClassChanger( "ctx-module-medium", "ctx-content-float" );
                } else if(getFloatWidth() > mediumModuleFl && getFloatWidth() <= normalModuleFl) {
                    extraLinksAction( [4, 5, 6], "hide" );
                    respClassChanger( "ctx-module-normal", "ctx-content-float" );
                } else if(getFloatWidth() > normalModuleFl) {
                    extraLinksAction( [5, 6], "hide" );
                    extraLinksAction( [4], "show" );
                    respClassChanger( "ctx-module-wide", "ctx-content-float" );
                }
            }

            // Text
            if(getTextWidth() < mobileModuleTx) {
                respClassChanger( "ctx-module-mobile", "ctx-content-text" );
            } else if(getTextWidth() >= mobileModuleTx) {
                respClassChanger( "ctx-module-default", "ctx-content-text" );
            }

            // Sidebar
            if(getSidebarWidth() < mobileModuleSb) {
                respSbClassChanger( "ctx-sidebar-mobile", "ctx-sidebar", "ctx-sidebar-default" );
            } else if(getSidebarWidth() >= mobileModuleSb) {
                respSbClassChanger( "ctx-sidebar-default", "ctx-sidebar", "ctx-sidebar-mobile" );
            }
        }

        var slideMinHeightBl = 54;

        function getBlocksWidth() {
            var width = jQuery(".ctx-content-block").width();
            return width;
        }

        function getSliderContentBl(classname) {
            return jQuery(".ctx-link .ctx-link-title");
        }

        if(getBlocksWidth() >= 450) {
            getSliderContentBl().css("height", slideMinHeightBl);

            jQuery('.ctx-links-content .ctx-link a').hover(
                function(){
                    jQuery(this).addClass('ctx-blocks-slider');
                    var getTextHeight = jQuery(".ctx-blocks-slider .ctx-link-title p").height();
                    if(getTextHeight>59) {
                        jQuery( ".ctx-blocks-slider .ctx-link-title" ).stop(true,true).animate({
                            height: getTextHeight + 10
                        }, 200 );
                    }
                },
                function(){
                    jQuery( ".ctx-blocks-slider .ctx-link-title" ).stop(true,true).animate({
                        height: slideMinHeightBl
                    }, 200 );
                    jQuery(this).removeClass('ctx-blocks-slider');
                }
            )
        }

        function getDocument() {
            var wdocument = jQuery(window);
            return wdocument;
        }

        function getBrdPopup() {
            var getpopup = jQuery( ".ctx-show-popup" );
            return getpopup;
        }

        function getBrdOnlyPopup() {
            var getpopup = jQuery( "#ctx-branding-content" );
            return getpopup;
        }

        function showBrdPopup() {
            getBrdPopup().fadeIn("fast");
        }

        function closeBrdPopup() {
            getBrdPopup().fadeOut("fast", function() {
                jQuery( this ).remove();
            });
        }

        /* Branding Popup */
        jQuery( "#ctx-branding-link" ).click( function( event ) {
            event.preventDefault();
            jQuery( "body" ).append( getBrandingHtml() );
            showBrdPopup();

            jQuery( "#ctx-brd-close" ).click( function( event ) {
                event.preventDefault();
                closeBrdPopup();
            });

            getDocument().mouseup(function (e)
            {
                if (!getBrdOnlyPopup().is(e.target) && getBrdOnlyPopup().has(e.target).length == 0)
                {
                    closeBrdPopup();
                }
            });

            getDocument().keyup(function(e) {
                if (e.keyCode == 27) { closeBrdPopup() }
            });
        });

        function getBrandingHtml() {
            var content = '<div class="ctx-brd-overlay ctx-show-popup" style="display:none"></div>';
            content += '<div id="ctx-branding-content" class="ctx-show-popup" style="display:none">';
            content += '<div id="ctx-brd-modal">';
            content += '<div id="ctx-brd-left-content">';
            content += '<div id="ctx-brd-logo"></div>';
            content += '<div id="ctx-brd-text-head"></div>';
            content += '<div id="ctx-brd-text"><p>Contextly recommends interesting and related stories using a unique combination of algorithms and editorial choices.<br><br>Publishers or advertisers who would like to learn more about Contextly can contact us&nbsp;<a href="http://contextly.com/sign-up/publishers/" target="_blank">here</a>.<br><br>We respect <a href="http://contextly.com/privacy/" target="_blank">readers&#8217; privacy </a>.&nbsp;</p></div>';
            content += '</div>';
            content += '<div id="ctx-brd-right-content"></div>';
            content += '</div>';
            content += '<a href="#" id="ctx-brd-close">X</a>';
            content += '</div>';

            return content;
        }

        ctxResponsiveResizeHandler();

        jQuery(window).resize(function() {
            ctxResponsiveResizeHandler();
        });

        var documentLoadInterval = null;
        var documentLoadCheckCount = 0;

        documentLoadInterval = self.setInterval(function () {
            documentLoadCheckCount++;

            ctxResponsiveResizeHandler();

            if ( documentLoadCheckCount > 5 ) {
                clearInterval( documentLoadInterval );
            }
        }, 500 );
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
    },

    oppositeColorGenerator: function( hexTripletColor ) {
        var color = hexTripletColor;
        color = color.substring(1);
        color = parseInt(color, 16);
        color = 0xFFFFFF ^ color;
        color = color.toString(16);
        color = ("000000" + color).slice(-6);
        color = "#" + color;
        return color;
    },

});

//////////////////////////////////////////////////////////////
//                      Text Widget                         //
//////////////////////////////////////////////////////////////

Contextly.SnippetWidgetTextFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetFormatter,

    getWidgetCssName: function () {
        return 'ctx-content-text';
    },

    getWidgetHTML: function () {
        var div = "";

        var sections = this.widget.settings.display_sections;

        if( sections.length == 1 ) {
            this.addColumnCounterClass( "ctx-text-column-one" );
        } else {
            this.addColumnCounterClass( "ctx-text-column-multiple" );
        }

        div += "<div class='" + this.getWidgetCssName() + " ctx-nodefs'>";
        div += "<div class='ctx-sections-container ctx-clearfix'>";

        var sections = this.widget.settings.display_sections;

        for ( var section in sections ) {
            var section_name = sections[section];
            if ( this.isDisplaySection( section_name ) ) {
                var section_key = section_name + '_subhead';
                var section_header = this.widget.settings[ section_key ];

                div += "<div class='ctx-section'>";

                div += "<div class='ctx-links-header'><p class='ctx-nodefs'>" + this.escape( section_header ) + "</p></div>";

                div += "<div class='ctx-links-content'>" + this.getLinksHTMLOfType( section_name ) + "</div>";
                div += "</div>";
            }
        }
        div += "</div>";
        div += "</div>";

        if ( this.isDisplayContextlyLogo() ) {
            div += "<div class='ctx-branding ctx-clearfix'>";
            div += "<a href='#' id='ctx-branding-link' class='ctx-nodefs'>Powered by</a>";
            div += "</div>";
        }

        return div;
    },

    addColumnCounterClass: function( className ) {
        var getModuleId = this.widget_html_id;
        jQuery( "#" + getModuleId).addClass( className );
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
        return Contextly.TextWidgetCssCustomBuilder.getInstance().buildCSS( '.ctx-module-container', this.getSettings() );
    }

});

Contextly.TextWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings ) {
        var css_code = "";

        if ( settings.css_code ) css_code += Contextly.Utils.getInstance().escape( settings.css_code );
        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".ctx-content-text .ctx-links-content a" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".ctx-content-text .ctx-links-content a" , "font-size", settings.font_size );
        if ( settings.color_links ) {

            var getOppositeColor = this.oppositeColorGenerator(settings.color_links);
            css_code += this.buildCSSRule( entry, ".ctx-link .ctx-video-icon" , "background-color", settings.color_links );
            css_code += this.buildCSSRule( entry, ".ctx-link .ctx-video-icon:after" , "border-left-color", getOppositeColor );

            css_code += this.buildCSSRule( entry, ".ctx-content-text .ctx-links-content a" , "color", settings.color_links );

            css_code += this.buildCSSRule( entry, ".ctx-links-content .ctx-link:before" , "background-color", settings.color_links );
        }
        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".ctx-content-text .ctx-links-header" , "background-color", settings.color_background );
        }

        return css_code;
    }

});

//////////////////////////////////////////////////////////////
//                    Blocks Widget                         //
//////////////////////////////////////////////////////////////
Contextly.SnippetWidgetBlocksFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetTextFormatter,

    getNumberOfLinksPerSection: function () {
        return 6;
    },

    getLinksHTMLOfType: function( type ) {
        var html = "";
        var linkCounter = 0;
        var widget = this.widget;
        var links_limit = this.getNumberOfLinksPerSection();

        if ( widget.links && widget.links[ type ] ) {
            for ( var link_idx in widget.links[ type ] ) {
                linkCounter++;
                if ( link_idx >= links_limit ) break;

                var link = widget.links[ type ][ link_idx ];

                if ( link.id && link.title ) {
                    html += this.getLinkHTML( link, linkCounter );
                }
            }
        }

        return html;
    },

    getWidgetCssName: function () {
        return 'ctx-content-block';
    },

    getWidgetHTML: function () {
        var div = "";

        div += "<div class='" + this.getWidgetCssName() + " ctx-nodefs'>";

        var sections = this.widget.settings.display_sections;

        div += "<div class='ctx-sections-container ctx-nomar'>";
        for ( var section in sections ) {
            var section_name = sections[section];

            if ( this.isDisplaySection( section_name ) ) {
                var section_key = section_name + '_subhead';
                var section_header = this.widget.settings[ section_key ];

                div += "<div class='ctx-section ctx-clearfix'>";
                div += "<div class='ctx-links-header ctx-clearfix'><p class='ctx-nodefs'>" + this.escape( section_header ) + "</p></div>";

                div += "<div class='ctx-links-content ctx-nodefs ctx-clearfix'>";
                div += this.getLinksHTMLOfType( section_name );
                div += "</div></div>";
            }
        }
        div += "</div>";

        if ( this.isDisplayContextlyLogo() ) {
            div += "<div class='ctx-branding ctx-clearfix'>";
            div += "<a href='#' id='ctx-branding-link' class='ctx-nodefs'>Powered by</a>";
            div += "</div>";
        }

        div += "</div>";

        return div;
    },

    getLinkHTML: function ( link, linkCounter ) {
        if ( link.video ) {
            return this.getLinkHTMLVideo( link, linkCounter );
        } else {
            return this.getLinkHTMLNormal( link, linkCounter );
        }
    },

    getInnerLinkHTML: function ( link ) {
        var inner_html = "<div class='ctx-link-title'>" +
            "<p class='ctx-nodefs'>" + this.getVideoIcon( link.video ) + " " + link.title + "</div>";
        if ( this.getLinkThumbnailUrl( link ) ) {
            inner_html += "<div class='ctx-link-image'><img src='" + link.thumbnail_url + "' class='ctx-nodefs' /></div>";
        }

        return inner_html;
    },

    getLinkHTMLVideo: function ( link, linkCounter ) {
        var linkClass = "";
        if( linkCounter > 3 ) { linkClass = " ctx-link-additional-" + linkCounter; }
        return "<div class='ctx-link" + linkClass + "'>" + this.getVideoLinkATag( link, this.getInnerLinkHTML( link ) ) + "</div>";
    },

    getLinkHTMLNormal: function ( link, linkCounter ) {
        var linkClass = "";
        if( linkCounter > 3 ) { linkClass = " ctx-link-additional-" + linkCounter; }
        return "<div class='ctx-link" + linkClass + "'>" + this.getLinkATag( link, this.getInnerLinkHTML( link ) ) + "</div>";
    },

    getCustomCssCode: function () {
        return Contextly.BlocksWidgetCssCustomBuilder.getInstance().buildCSS( '.ctx-module-container', this.getSettings() );
    }

});

Contextly.BlocksWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings )
    {
        var css_code = "";

        if ( settings.css_code ) css_code += Contextly.Utils.getInstance().escape( settings.css_code );

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".ctx-content-block .ctx-link-title p" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".ctx-content-block .ctx-link-title p" , "font-size", settings.font_size );

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".ctx-content-block .ctx-link-title p" , "color", settings.color_links );

            var getOppositeColor = this.oppositeColorGenerator(settings.color_links);
            css_code += this.buildCSSRule( entry, ".ctx-link-title .ctx-video-icon" , "background-color", settings.color_links );
            css_code += this.buildCSSRule( entry, ".ctx-link-title .ctx-video-icon:after" , "border-left-color", getOppositeColor );
        }

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".ctx-content-block .ctx-links-header" , "background-color", settings.color_background );
        }

        if ( settings.color_border ) {
            var color_border = settings.color_border;
            var rgb = this.hex2Vals( color_border );

            if ( rgb.length == 3 ) {
                var r = rgb[0];
                var g = rgb[1];
                var b = rgb[2];

                css_code += this.buildCSSRule( entry, ".ctx-content-block .ctx-link .ctx-link-title" , "background", "rgba("+r+","+g+","+b+",0.5)" );
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
        return 'ctx-content-block2';
    },

    getInnerLinkHTML: function ( link, is_video ) {
        var inner_html = "";

        if ( this.getLinkThumbnailUrl( link ) ) {
            inner_html += "<div class='ctx-link-image'><img src='" + link.thumbnail_url + "' class='ctx-nodefs' /></div>";
        }
        inner_html += "<div class='ctx-link-title'><p class='ctx-nodefs'>"
            + this.getVideoIcon( is_video ) + " " + link.title + "</p></div>";

        return inner_html;


    },

    getLinkHTMLVideo: function ( link, linkCounter ) {
        var linkClass = "";
        if( linkCounter > 3 ) { linkClass = " ctx-link-additional-" + linkCounter; }
        return "<div class='ctx-link" + linkClass + "'>" + this.getVideoLinkATag( link, this.getInnerLinkHTML( link, true ) ) + "</div>";
    },

    getCustomCssCode: function () {
        return Contextly.Blocks2WidgetCssCustomBuilder.getInstance().buildCSS( '.ctx-module-container', this.getSettings() );
    }

});

Contextly.Blocks2WidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings )
    {
        var css_code = "";

        if ( settings.css_code ) css_code += Contextly.Utils.getInstance().escape( settings.css_code );

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".ctx-content-block2 .ctx-link-title p" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".ctx-content-block2 .ctx-link-title p" , "font-size", settings.font_size );

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".ctx-content-block2 .ctx-link-title p" , "color", settings.color_links );

            var getOppositeColor = this.oppositeColorGenerator(settings.color_links);
            css_code += this.buildCSSRule( entry, ".ctx-link-title .ctx-video-icon" , "background-color", settings.color_links );
            css_code += this.buildCSSRule( entry, ".ctx-link-title .ctx-video-icon:after" , "border-left-color", getOppositeColor );
        }

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".ctx-content-block2 .ctx-links-header" , "background-color", settings.color_background );
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
        return 'ctx-content-float';
    },

    getNumberOfLinksPerSection: function () {
        return this.getSettings().links_limit;
    },

    getInnerLinkHTML: function ( link, is_video ) {
        var inner_html = "";
        if ( this.getLinkThumbnailUrl( link ) ) {
            inner_html += "<div class='ctx-link-image'><img src='" + link.thumbnail_url + "' class='ctx-nodefs' /></div>";
        }

        inner_html += "<div class='ctx-link-title'><p class='ctx-nodefs'>"
            + this.getVideoIcon( is_video ) + " " + link.title + "</p></div>";

        return inner_html;
    },

    getLinkHTMLVideo: function ( link, linkCounter ) {
        var linkClass = "";
        if( linkCounter > 3 ) { linkClass = " ctx-link-additional-" + linkCounter; }
        return "<div class='ctx-link" + linkClass + "'>" + this.getVideoLinkATag( link, this.getInnerLinkHTML( link, true ) ) + "</div>";
    },

    getCustomCssCode: function () {
        return Contextly.FloatWidgetCssCustomBuilder.getInstance().buildCSS( '.ctx-module-container', this.getSettings() );
    }

});

Contextly.FloatWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings ) {
        var css_code = "";
        if ( settings.css_code ) css_code += Contextly.Utils.getInstance().escape( settings.css_code );

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".ctx-content-float .ctx-link-title p" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".ctx-content-float .ctx-link-title p" , "font-size", settings.font_size );

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".ctx-content-float .ctx-link-title p" , "color", settings.color_links );

            var getOppositeColor = this.oppositeColorGenerator(settings.color_links);
            css_code += this.buildCSSRule( entry, ".ctx-link-title .ctx-video-icon" , "background-color", settings.color_links );
            css_code += this.buildCSSRule( entry, ".ctx-link-title .ctx-video-icon:after" , "border-left-color", getOppositeColor );
        }

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".ctx-content-float .ctx-links-header" , "background-color", settings.color_background );
        }

        return css_code;
    }

});

//////////////////////////////////////////////////////////////
//                   Sidebar Widget                         //
//////////////////////////////////////////////////////////////
Contextly.SidebarWidgetFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetFormatter,

    construct: function( widget ) {
        if ( widget ) {
            this.widget = widget;
            this.widget_type = Contextly.WidgetType.SIDEBAR;
            this.widget_html_id = 'contextly-' + widget.id;
        }
    },

    getWidgetHTML: function()
    {
        return "<div class='ctx-content-sidebar'><div class='ctx-sb-content'>"
            + this.getLinksHTMLOfType( 'previous' )
            + "</div></div>";
    },

    getLinkATag: function ( link, content ) {

        return "<a href=\"" +
            this.escape( link.native_url ) + "\" title=\"" +
            this.escape( link.title ) + "\" class='ctx-clearfix ctx-nodefs ctx-no-images' onmousedown=\"this.href='" +
            this.escape( link.url ) + "'\" " + this.getOnclickHtml( link ) + ">" + content + "</a>";
    },

    getLinkHTML: function ( link ) {
        var html = "<div class='ctx-sb-fotmater'>";

        if ( link.thumbnail_url ) {

            var image_html = "<img src='" + link.thumbnail_url + "' />";
            var image_href;

            if ( link.video ) {
                image_href = this.getVideoLinkATag( link, image_html );
            } else {
                image_href = this.getLinkATag( link, image_html );
            }

            html += "<div class='ctx-sb-img'>" + image_href + "</div>";
        }

        if ( link.video ) {
            html += "<div class='ctx-sb-text'><p>" + this.getVideoLinkATag( link, link.title ) + "</p></a>";
        } else {
            html += "<div class='ctx-sb-text'><p>" + this.getLinkATag( link, link.title ) + "</p></a>";
        }

        if ( this.widget.settings.display_link_dates && link.publish_date ) {
            html += " <span class='link-pub-date'>" + Contextly.Utils.getInstance().dateTextDiff( link.publish_date ) + "</span>";
        }

        html += "</div>";
        html += "</div>";

        return html;
    },

    getLinksHTMLOfType: function( type )
    {
        var html = "";
        if ( this.widget && this.widget.links && this.widget.links[ type ] ) {
            for ( var link_idx in this.widget.links[ type ] ) {
                var link = this.widget.links[ type ][ link_idx ];

                if ( link.id && link.title ) {
                    html += "<div class='ctx-sb-link'>" + this.getLinkHTML( link ) + "</div>";
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
                self.getDisplayElement().removeClass( 'ctx-sidebar-container' )
                    .addClass( 'ctx-sidebar' )
                    .addClass( 'ctx-sidebar-' + self.widget.layout )
                    .addClass( 'ctx-sb-clearfix' );

                // Check if we need to add sidebar title and description
                var title = self.widget.name;
                var description = self.widget.description;
                var sidebar_content = self.getDisplayElement().find( '.ctx-content-sidebar' );

                if ( description ) sidebar_content.prepend( "<div class='ctx-sb-description'><p>" + self.escape( description ) + "</p></div>" );
                if ( title ) sidebar_content.prepend( "<div class='ctx-sb-title'><p>" + self.escape( title ) + "</p></div>" );

                self.loadCss( 'sidebar-css' );
            }
        });
    },

    getWidgetCSSUrl: function () {
        var css_url;
        var settings = this.getSettings();

        if ( Contextly.Settings.getInstance().getMode() == 'local' ) {
            css_url = "http://linker.site/resources/css/plugin/sidebar/template-" + settings.theme + ".css";
        } else if ( Contextly.Settings.getInstance().getMode() == 'dev' ) {
            css_url = "http://dev.contextly.com/resources/css/plugin/sidebar/template-" + settings.theme + ".css";
        } else {
            css_url = Contextly.Settings.getInstance().getCdnCssUrl() + "wp_plugin/"  + Contextly.Settings.getInstance().getPluginVersion() +  "/css-api/sidebar/template-" + settings.theme + ".css";
        }

        return css_url;
    },

    getCustomCssCode: function () {
        return Contextly.SidebarWidgetCssCustomBuilder.getInstance().buildCSS( '.ctx-sidebar', this.getSettings() );
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
            css_code += this.buildCSSRule( entry, ".ctx-sb-link a" , "font-family", settings.font_family );
            css_code += this.buildCSSRule( entry, ".ctx-sb-description p" , "font-family", settings.font_family );
            css_code += this.buildCSSRule( entry, ".ctx-sb-title p" , "font-family", settings.font_family );
        }
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".ctx-sb-link a" , "font-size", settings.font_size );

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".ctx-content-sidebar" , "background-color", settings.color_background );
        }

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".ctx-sb-link a" , "color", settings.color_links );
        }

        if ( settings.color_border ) {
            css_code += this.buildCSSRule( entry, ".ctx-content-sidebar" , "border-color", settings.color_border + " !important;" );
        }

        if ( settings.title_font_size ) {
            css_code += this.buildCSSRule( entry, ".ctx-sb-title p" , "font-size", settings.title_font_size );
        }

        if ( settings.description_font_size ) {
            css_code += this.buildCSSRule( entry, ".ctx-sb-description p" , "font-size", settings.description_font_size );
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
    getPageId: function () {
        var post_data = this.getPostData();
        return post_data.post_id;
    },
    getMode: function () {
        return Contextly.mode;
    },
    getPostModifiedDate: function () {
        var post_data = this.getPostData();
        return post_data.mod_date;
    },
    getPostCreatedDate: function () {
        var post_data = this.getPostData();
        return post_data.pub_date;
    },
    getAuthorId: function () {
        var post_data = this.getPostData();
        return post_data.author_id;
    },
    getPageUrl: function () {
        var post_data = this.getPostData();
        return post_data.url;
    },
    getPostType: function () {
        var post_data = this.getPostData();
        return post_data.type;
    },
    getWPSettings: function () {
        return Contextly.settings;
    },
    isHttps: function () {
        return Contextly.https;
    },
    getPostCategories: function () {
        var post_data = this.getPostData();
        return post_data.categories;
    },
    getPostTags: function () {
        var post_data = this.getPostData();
        return post_data.tags;
    },
    getCdnCssUrl: function () {
        if ( this.isHttps() ) {
            return 'https://c714015.ssl.cf2.rackcdn.com/';
        } else {
            return 'http://contextlysitescripts.contextly.com/';
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
    },
    getPostData: function () {
        var data = jQuery("meta[name='contextly-page']").attr("content");
        var json = jQuery.parseJSON(data);

        return json;
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
                cookie_id:  Contextly.Loader.getInstance().getCookieId(),
                type:       contextly_settings.getPostType(),
                c_date:  Contextly.Settings.getInstance().getPostCreatedDate(),
                m_date:  Contextly.Settings.getInstance().getPostModifiedDate(),
                c_count:  Contextly.Settings.getInstance().getPostCategories().length,
                t_count:  Contextly.Settings.getInstance().getPostTags().length
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
