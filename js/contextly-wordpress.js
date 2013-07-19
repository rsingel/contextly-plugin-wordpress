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
    SIDEBAR: 'sidebar'
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

    // Main method for load page widgets
    load: function () {
        if ( !this.isCallAvailable() ) return;

        Contextly.RESTClient.getInstance().call(
            'pagewidgets',
            'get',
            {},
            function ( response ) {
                var pageView = new Contextly.PageView( response );
                pageView.display();
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
            'event_date': new Date()
        };

        Contextly.RESTClient.getInstance().call(
            'siteevents',
            'put',
            event_data,
            function ( response ) {
            }
        );
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
            var url = 'http://contextly.com/contact-us/';

            if ( this.error.error ) {
                if ( this.error.error_code == Contextly.Errors.ERROR_FORBIDDEN ) {
                    message = this.error.error + " Please check your API settings on the Contextly plugin <a href='admin.php?page=contextly_options&tab=contextly_options_api'>Settings</a> page.";
                } else if ( this.error.error_code == Contextly.Errors.ERROR_SUSPENDED ) {
                    message = "Your account has been suspended. If this is an error, please contact us via <a href='http://contextly.com/contact-us/'>support@contextly.com</a>.";
                } else {
                    url = "admin.php?page=contextly_options&tab=contextly_options_api";
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
            this.displayWidgets( this.entry.snippets );
            this.displayWidgets( this.entry.sidebars );

            // Check if we need to update this post in our DB
            if ( !Contextly.Settings.getInstance().isAdmin() ) {
                this.checkPost();
            }
        }
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
            page_id: Contextly.Settings.getInstance().getPageId()
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

        if ( entry.type == 'sidebar' ) {
            return new Contextly.SidebarWidget( entry );
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

Contextly.SnippetWidgetFormatter = Contextly.createClass({
    abstracts: [ 'getWidgetHTML', 'getWidgetCssName' ],

    construct: function( widget ) {
        this.widget = widget;
        this.widget_type = Contextly.WidgetType.SNIPPET;
        this.widget_html_id = 'linker_widget';
    },

    getDisplayElement: function() {
        return jQuery( '#' + this.widget_html_id );
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
            controls = "<br><input type='button' class='button action' value='Edit See Also' onclick='Contextly.PopupHelper.getInstance().snippetPopup();' />";
            this.appendText( controls );
        } else {
            controls = "<input type='button' class='button action' value='Create See Also' onclick='Contextly.PopupHelper.getInstance().snippetPopup();' />";
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
            // Build widget html and display it
            var html = this.getWidgetHTML();
            this.displayText( html );
            this.loadCss();

            // Check if we need to change snippet position on page
            if ( !Contextly.Settings.getInstance().isAdmin() ) {
                this.fixSnippetPagePosition();
            }

            var settings = this.getSettings();
            if ( settings && settings.display_type ) {
                this.getDisplayElement().attr( 'widget-type', settings.display_type );
            }
        }

        if ( Contextly.Settings.getInstance().isAdmin() ) {
            this.displayAdminControls();
        }
    },

    loadCss: function () {
        var css_url = this.getWidgetCSSUrl();

        Contextly.Utils.getInstance().loadCssFile( css_url );

        if ( Contextly.Utils.getInstance().isIE7() ) {
            var css_ie7fix = Contextly.Settings.getInstance().getCdnCssUrl() + "_plugin/"  + Contextly.Settings.getInstance().getPluginVersion() +  "/css/template-ie-fix.css";
            Contextly.Utils.getInstance().loadCssFile( css_ie7fix );
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
        return Contextly.CssCustomBuilder.getInstance().buildCSS( '.contextly-widget', this.getSettings() );
    },

    fixSnippetPagePosition: function () {
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

    getDisplaySections: function () {
        return this.getSettings().display_sections;
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
    }
});

Contextly.SnippetWidgetTextFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetFormatter,

    getWidgetCssName: function () {
        return 'text-widget';
    },

    getWidgetHTML: function () {
        var div = "";
        var value;

        div += "<div class='contextly_see_also " + this.getWidgetCssName() + "'>";
        // Check for top links html
        if ( value = this.widget.settings.html_above ) {
            div += "<div class='contextly_html_above'>" + value + "</div>";
        }
        // Check for title
        if ( value = this.widget.settings.title ) {
            div += "<div class='contextly_title'>" + value + "</div>";
        }

        var sections = this.widget.settings.display_sections;

        for ( var section in sections ) {
            var section_name = sections[section];
            if ( this.isDisplaySection( section_name ) ) {
                var section_key = section_name + '_subhead';
                var section_header = this.widget.settings[ section_key ];

                div += "<div class='contextly_previous'>";
                div += "<div class='contextly_subhead'><span class='contextly_subhead_title'>" + section_header + "</span><span class='contextly_subhead_line'></span></div>";
                div += "<ul class='link'>" + this.getLinksHTMLOfType( section_name ) + "</ul>";
                div += "</div>";
            }
        }
        div += "</div>";

        return div;
    },

    getLinkHTML: function ( link )
    {
        return "<li><a class=\"title module-contextly\" href=\"" + link.native_url + "\" title=\"" + link.title + "\" onmousedown=\"this.href='" + link.url + "'\" onclick=\"javascript:return(true)\">" + link.title + "</a><!--[if lte ie 7]><b></b><![endif]--></li>";
    },

    getLinksHTMLOfType: function( type )
    {
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
        return Contextly.TextWidgetCssCustomBuilder.getInstance().buildCSS( '.contextly-widget', this.getSettings() );
    }

});

Contextly.SnippetWidgetTabsFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetFormatter,

    getWidgetCssName: function () {
        return 'tabs-widget';
    },

    getWidgetHTML: function () {
        var div = "<div class='contextly_see_also " + this.getWidgetCssName() + "'>";

        var sections = this.widget.settings.display_sections;

        if ( value = this.widget.settings.html_above ) {
            div += "<span class=\"contextly_above_related\">" + value + "</span>";
        }

        div += "<ul class=\"linker_tabs\">";
        var active_flag = false;

        for ( var section in sections ) {
            var section_name = sections[section];

            if ( this.isDisplaySection( section_name ) ) {
                var section_key = section_name + '_subhead';
                var section_header = this.widget.settings[ section_key ];

                div += "<li id='linker_tab_" + section_name + "' " + (!active_flag ? "class='active'" : "") + ">";
                div += "<a href='javascript:;' onclick='Contextly.PageEvents.getInstance().switchTab(\"" + this.widget.settings.id + "\", \"" + section_name + "\")'><span>" + section_header + "</span></a>";
                div += "</li>";
                active_flag = true;
            }
        }

        if ( this.isDisplayContextlyLogo() ) {
            div += "<li><span class='contextly_related'><a href='http://contextly.com'>Related Links by Contextly</a></span></li>";
        }

        div += "</ul>";

        active_flag = false;
        for (var section in sections) {
            var section_name = sections[section];
            if ( this.isDisplaySection( section_name ) ) {
                div += "<div id='linker_content_" + section_name + "' class='linker_content' " + (!active_flag ? "style='display: block;'" :"") + ">"
                    + "<ul class='link " + ( this.hasImagesForLinks( section_name ) ? 'linker_images' : '' ) + " '>"
                    + this.getLinksHTMLOfType( section_name )
                    + "</ul>"
                    + "</div>";
                active_flag = true;
            }
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
        var item_style = "padding-bottom: 5px;";

        if ( link.thumbnail_url ) {
            item_style += "height: " + this.getImagesHeight() + "px;";
        }

        var html = "<ul class='horizontal-line' style='" + item_style + "'>";
        var a_href = "<a class=\"title module-contextly\" href=\"" + link.native_url + "\" title=\"" + link.title + "\" onmousedown=\"this.href='" + link.url + "'\" onclick=\"javascript:return(true)\">";
        var ie_fix = "<!--[if lte ie 7]><b></b><![endif]-->";

        if ( link.thumbnail_url ) {
            var image_li_width = this.getImagesWidth() + 8;
            html += "<li style='width: " + image_li_width + "px;'>" + a_href + "<img src='" + link.thumbnail_url + "' /></a>" + ie_fix + "</li>";
        }

        html += "<li>" + a_href + link.title + "</a>";

        if ( this.widget.settings.display_link_dates && link.publish_date ) {
            html += " <span class='link-pub-date'>" + Contextly.Utils.getInstance().dateTextDiff( link.publish_date ) + "</span>";
        }

        html += ie_fix + "</li>";
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

    isDisplayContextlyLogo: function() {
        return !Contextly.Settings.getInstance().isAdmin() && !this.isMobileRequest();
    },

    isMobileRequest: function() {
        return false;
    }

});

Contextly.SnippetWidgetBlocksFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetTextFormatter,

    getLinksHTMLOfType: function( type ) {
        var html = "";
        var widget = this.widget;
        var links_limit = 4;

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
        return 'blocks-widget';
    },

    getWidgetHTML: function () {
        var div = "";
        var value;

        div += "<div class='contextly_see_also " + this.getWidgetCssName() +"'>";
        // Check for top links html
        if ( value = this.widget.settings.html_above ) {
            div += "<div class='contextly_html_above'>" + value + "</div>";
        }
        // Check for title
        if ( value = this.widget.settings.title ) {
            div += "<div class='contextly_title'>" + value + "</div>";
        }

        var sections = this.widget.settings.display_sections;

        div += "<div class='contextly_around_site'>";
        for ( var section in sections ) {
            var section_name = sections[section];

            if ( this.isDisplaySection( section_name ) ) {
                var section_key = section_name + '_subhead';
                var section_header = this.widget.settings[ section_key ];

                div += "<div class='contextly_previous'>";
                div += "<div class='contextly_subhead'><span class='contextly_subhead_title'>" + section_header + "</span><span class='contextly_subhead_line'></span></div>";
                div += "<ul class='link'>" + this.getLinksHTMLOfType( section_name ) + "</ul>";
                div += "</div>";
            }
        }
        div += "</div>";

        return div;
    },

    getLinkHTML: function ( link ) {
        if ( link.video ) {
            return this.getLinkHTMLVideo( link );
        } else {
            return this.getLinkHTMLNormal( link );
        }
    },

    getLinkHTMLVideo: function ( link ) {
        var html = "<li>";

        html += "<a href=\"" + link.native_url + "\" rel=\"contextly-video-link\" title=\"" + link.title + "\" contextly-url=\"" + link.url + "\" >";
        html += "<span class=\"vidpop-playbutton-big\"></span>";
        html += "<p class='link'><span>" + link.title + "<span></p>";
        if ( link.thumbnail_url ) {
            html += "<img src='" + link.thumbnail_url + "' />";
        }
        html += "</a><!--[if lte ie 7]><b></b><![endif]--></li>";

        return html;
    },

    getLinkHTMLNormal: function ( link ) {
        var html = "<li><a href=\"" + link.native_url + "\" onmousedown=\"this.href='" + link.url + "'\" onclick=\"javascript:return(true)\"><p class='link'><span>" + link.title + "</span></p>";

        if ( link.thumbnail_url ) {
            html += "<img src='" + link.thumbnail_url + "' />";
        }
        html += "</a><!--[if lte ie 7]><b></b><![endif]--></li>";

        return html;
    },

    getCustomCssCode: function () {
        return Contextly.BlocksWidgetCssCustomBuilder.getInstance().buildCSS( '.contextly-widget', this.getSettings() );
    },

    display: function () {
        Contextly.SnippetWidgetTextFormatter.fn.display.call( this );

        if ( this.hasWidgetData() ) {
            this.attachVideoPopups();
        }
    },

    attachVideoPopups: function () {
        jQuery("a[rel='contextly-video-link']").each(
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
    }

});

Contextly.SnippetWidgetBlocks2Formatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetBlocksFormatter,

    getWidgetCssName: function () {
        return 'blocks-widget2';
    },

    getLinkHTMLVideo: function ( link ) {
        var html = "<li>";

        html += "<a href=\"" + link.native_url + "\" rel=\"contextly-video-link\" title=\"" + link.title + "\" contextly-url=\"" + link.url + "\" >";

        if ( this.getLinkThumbnailUrl( link ) ) {
            html += "<div class='playbutton-wrapper'><img src='" + this.getLinkThumbnailUrl( link ) + "' />";
        }
        html += "<span class=\"vidpop-playbutton-big\"></span>";

        if ( this.getLinkThumbnailUrl( link ) ) {
            html += "</div>";
        }

        html += "<p class='link'><span>" + link.title + "<span></p>";
        html += "</a><!--[if lte ie 7]><b></b><![endif]--></li>";

        return html;
    },

    getLinkHTMLNormal: function ( link ) {
        var html = "<li><a href=\"" + link.native_url + "\" onmousedown=\"this.href='" + link.url + "'\" onclick=\"javascript:return(true)\">";

        if ( this.getLinkThumbnailUrl( link ) ) {
            html += "<img src='" + this.getLinkThumbnailUrl( link ) + "' />";
        }
        html += "<p class='link'><span>" + link.title + "</span></p></a><!--[if lte ie 7]><b></b><![endif]--></li>";

        return html;
    }

});


Contextly.SnippetWidgetFloatFormatter = Contextly.createClass({
    extend: Contextly.SnippetWidgetBlocksFormatter,

    getWidgetCssName: function () {
        return 'float-widget';
    },

    getLinkHTMLNormal: function ( link ) {
        var html = "<li><a href=\"" + link.native_url + "\" onmousedown=\"this.href='" + link.url + "'\" onclick=\"javascript:return(true)\">";

        if ( link.thumbnail_url ) {
            html += "<img src='" + link.thumbnail_url + "' />";
        }

        var text_width = this.getImagesWidth() + 10;
        html += "<p class='link' style='width: " + text_width + "px;'><span>" + link.title + "</span></p>";
        html += "</a><!--[if lte ie 7]><b></b><![endif]--></li>";

        return html;
    },

    getLinkHTMLVideo: function ( link ) {
        var html = "<li>";

        html += "<a href=\"" + link.native_url + "\" rel=\"contextly-video-link\" title=\"" + link.title + "\" contextly-url=\"" + link.url + "\" >";

        if ( this.getLinkThumbnailUrl( link ) ) {
            html += "<div class='playbutton-wrapper'><img src='" + this.getLinkThumbnailUrl( link ) + "' />";
        }
        html += "<span class=\"vidpop-playbutton-big\"></span>";

        if ( this.getLinkThumbnailUrl( link ) ) {
            html += "</div>";
        }

        var text_width = this.getImagesWidth() + 10;
        html += "<p class='link' style='width: " + text_width + "px;'><span>" + link.title + "</span></p>";
        html += "</a><!--[if lte ie 7]><b></b><![endif]--></li>";

        return html;
    },

    getCustomCssCode: function () {
        return Contextly.FloatWidgetCssCustomBuilder.getInstance().buildCSS( '.contextly-widget', this.getSettings() );
    }

});

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
        return "<div class='linker_content'><ul class='link " + ( this.hasImagesForLinks( 'previous' ) ? 'linker_images' : '' ) + " '>"
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

        jQuery( document).ready(
            function () {
                if ( self.hasWidgetData() && !Contextly.Settings.getInstance().isAdmin() ) {
                    // Build widget html and display it
                    var html = self.getWidgetHTML();
                    self.displayText( html );

                    // Do some sidebar modifications
                    self.getDisplayElement().removeClass( 'contextly-sidebar-hidden' )
                        .addClass( 'contextly-sidebar' )
                        .addClass( 'contextly-sidebar-' + self.widget.layout )
                        .addClass( 'contextly-widget');

                    // Check if we need to add sidebar title and description
                    var title = self.widget.name;
                    var description = self.widget.description;
                    var sidebar_content = self.getDisplayElement().find( '.linker_content' );

                    if ( description ) sidebar_content.prepend( "<div class='description'>" + description + "</div>" );
                    if ( title ) sidebar_content.prepend( "<div class='title'>" + title + "</div>" );

                    self.loadCss();
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
        return Contextly.SidebarWidgetCssCustomBuilder.getInstance().buildCSS( '.contextly-sidebar', this.getSettings() );
    }

});

Contextly.CssCustomBuilder = Contextly.createClass({
    extend: Contextly.Singleton,

    buildCSS: function ( entry, settings )
    {
        var css_code = "";

        if ( settings.css_code ) css_code += '#linker_widget ' + settings.css_code;

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".link" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".link" , "font-size", settings.font_size );

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".linker_content" , "background-color", settings.color_background );
            css_code += this.buildCSSRule( entry, ".linker_images img" , "border-color", settings.color_background );
        }

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".linker_content a" , "color", settings.color_links );
            css_code += this.buildCSSRule( entry, ".linker_content span" , "color", settings.color_links );
        }

        if ( settings.color_border ) {
            css_code += this.buildCSSRule( entry, ".linker_content" , "border-color", settings.color_border );
            css_code += this.buildCSSRule( entry, ".linker_tabs li.active span" , "border-color", settings.color_border );
            css_code += this.buildCSSRule( entry, ".linker_tabs span" , "border-color", settings.color_border );
        }

        if ( settings.color_active_tab ) css_code += this.buildCSSRule( entry, ".linker_tabs li.active span" , "background", settings.color_active_tab );

        if ( settings.color_inactive_tab ) css_code += this.buildCSSRule( entry, ".linker_tabs span" , "background", settings.color_inactive_tab );

        return css_code;
    },

    buildCSSRule: function( entry, prefix, property, value ) {
        if ( !value ) return "";
        return entry + " " + prefix + " {" + property + ": " + value + "}";
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

Contextly.TextWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings )
    {
        var css_code = "";

        if ( settings.css_code ) css_code += '#linker_widget ' + settings.css_code;

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".link" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".link" , "font-size", settings.font_size );

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, "a.title" , "color", settings.color_links );
        }

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".contextly_subhead" , "background-color", settings.color_background );
        }

        return css_code;
    }

});

Contextly.BlocksWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings )
    {
        var css_code = "";

        if ( settings.css_code ) css_code += '#linker_widget ' + settings.css_code;

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".link" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".link" , "font-size", settings.font_size );

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".link span" , "color", settings.color_links );
        }

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".contextly_subhead" , "background-color", settings.color_background );
        }

        if ( settings.color_border ) {
            var color_border = settings.color_border;
            var rgb = this.hex2Vals( color_border );

            if ( rgb.length == 3 ) {
                var r = rgb[0];
                var g = rgb[1];
                var b = rgb[2];

                css_code += this.buildCSSRule( entry, ".blocks-widget li p" , "background", color_border );
                css_code += this.buildCSSRule( entry, ".blocks-widget li p" , "background", "rgba("+r+","+g+","+b+",0.7)" );
                css_code += this.buildCSSRule( entry, ".blocks-widget li p" , "background", "-moz-linear-gradient(top,  rgba(255,255,255,0) 0%, rgba("+r+","+g+","+b+",0.19) 10%, rgba("+r+","+g+","+b+",0.5) 27%, rgba("+r+","+g+","+b+",0.9) 100%)" );
                css_code += this.buildCSSRule( entry, ".blocks-widget li p" , "background", "-webkit-linear-gradient(top,  rgba(255,255,255,0) 0%,rgba("+r+","+g+","+b+",0.19) 10%,rgba("+r+","+g+","+b+",0.5) 27%,rgba("+r+","+g+","+b+",0.9) 100%)" );
                css_code += this.buildCSSRule( entry, ".blocks-widget li p" , "background", "-o-linear-gradient(top,  rgba(255,255,255,0) 0%,rgba("+r+","+g+","+b+",0.19) 10%,rgba("+r+","+g+","+b+",0.5) 27%,rgba("+r+","+g+","+b+",0.9) 100%)" );
                css_code += this.buildCSSRule( entry, ".blocks-widget li p" , "background", "-ms-linear-gradient(top,  rgba(255,255,255,0) 0%,rgba("+r+","+g+","+b+",0.19) 10%,rgba("+r+","+g+","+b+",0.5) 27%,rgba("+r+","+g+","+b+",0.9) 100%)" );
                css_code += this.buildCSSRule( entry, ".blocks-widget li p" , "background", "linear-gradient(to bottom,  rgba(255,255,255,0) 0%,rgba("+r+","+g+","+b+",0.19) 10%,rgba("+r+","+g+","+b+",0.5) 27%,rgba("+r+","+g+","+b+",0.9) 100%)" );
            }
        }

        return css_code;
    }

});

Contextly.FloatWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings )
    {
        var css_code = "";

        if ( settings.css_code ) css_code += '#linker_widget ' + settings.css_code;

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, ".link" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, ".link" , "font-size", settings.font_size );

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".link span" , "color", settings.color_links );
        }

        return css_code;
    }

});

Contextly.SidebarWidgetCssCustomBuilder = Contextly.createClass({
    extend: [ Contextly.CssCustomBuilder, Contextly.Singleton ],

    buildCSS: function ( entry, settings )
    {
        var css_code = "";

        if ( settings.css_code ) css_code += '.contextly-sidebar ' + settings.css_code;

        if ( settings.font_family ) css_code += this.buildCSSRule( entry, "a.title" , "font-family", settings.font_family );
        if ( settings.font_size ) css_code += this.buildCSSRule( entry, "a.title" , "font-size", settings.font_size );

        if ( settings.color_background ) {
            css_code += this.buildCSSRule( entry, ".linker_content" , "background-color", settings.color_background );
            css_code += this.buildCSSRule( entry, ".linker_images img" , "border-color", settings.color_background );
        }

        if ( settings.color_links ) {
            css_code += this.buildCSSRule( entry, ".linker_content a" , "color", settings.color_links );
            css_code += this.buildCSSRule( entry, ".linker_content span" , "color", settings.color_links );
        }

        if ( settings.color_border ) {
            css_code += this.buildCSSRule( entry, ".linker_content" , "border-color", settings.color_border );
            css_code += this.buildCSSRule( entry, ".linker_tabs li.active span" , "border-color", settings.color_border );
            css_code += this.buildCSSRule( entry, ".linker_tabs span" , "border-color", settings.color_border );
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
            var allowed_diff = 60 * 60 * 24 * 365; // 1 year

            // Don't allow to update very old posts
            if ( diff <= allowed_diff ) {
                return true;
            }
        }

        return false;
    },

    isIE7: function () {
        return jQuery.browser.msie && parseFloat( jQuery.browser.version ) < 8;
    },

    loadCssFile: function ( css_url ) {
        jQuery( "head" ).append( "<link>" );
        var css_node = jQuery( "head" ).children( ":last" );
        css_node.attr({
            rel:    "stylesheet",
            media:  "screen",
            type:   "text/css",
            href:   css_url
        });
    },

    loadCustomCssCode: function ( custom_css, contextly_id ) {
        if ( contextly_id ) {
            // Remove previously loaded script
            jQuery( 'style[contextly_id="' + contextly_id + '"]').remove();
        }

        jQuery( "head" ).append( jQuery( "<style type='text/css' contextly_id='" + contextly_id + "'>" + custom_css + "</style>" ) );
    }
});

Contextly.PageEvents = Contextly.createClass({
    extend: Contextly.Singleton,

    switchTab: function ( setting_id, tab ) {
        jQuery("#linker_content_previous,#linker_content_web,#linker_content_interesting,#linker_content_custom").hide();
        jQuery("#linker_tab_previous,#linker_tab_web,#linker_tab_interesting,#linker_tab_custom").attr( "class", "" );
        jQuery("#linker_content_" + tab).show();
        jQuery("#linker_tab_" + tab).attr( "class", "active" );

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
                page_id:    contextly_settings.getPageId()
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

            if ( snippet.links ) {
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
                    send_to_editor( '[contextly_sidebar id="' + response.snippet_id + '"]' );
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
        this.url = this.url || 'http://contextly.com/contact-us/';
        window.open( this.url );
    }

});

// Load Contextly widgets for this page
Contextly.Loader.getInstance().load();
