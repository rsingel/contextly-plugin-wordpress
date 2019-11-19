(function($) {

Contextly.WPSettings = Contextly.createClass({

    statics: /** @lends Contextly.WPSettings */ {

        getEditorUrl: function () {
            return Contextly.wpdata.editor_url;
        },

        getEditorPostId: function() {
            return Contextly.wpdata.editor_post_id;
        },

        getWPSettings: function () {
            return Contextly.wpdata.settings;
        },

        getAjaxUrl: function () {
            return Contextly.wpdata.ajax_url;
        },

        getPostNonce: function ( postId ) {
            if ( Contextly.wpdata.posts && Contextly.wpdata.posts[postId] ) {
                return Contextly.wpdata.posts[postId].ajax_nonce;
            }
            return null;
        },

        /**
         * Adds WP post related data to the storage.
         *
         * Must be called on new posts loaded through AJAX.
         *
         * @param postId
         * @param data
         */
        setPostData: function( postId, data ) {
            Contextly.wpdata.posts = Contextly.wpdata.posts || {};
            Contextly.wpdata.posts[postId] = data;
        }
    }

});

Contextly.WPSettingsAutoLogin = Contextly.createClass({
    statics: {
        doLogin: function ( settings_button_id, disabled_flag ) {
            if ( disabled_flag )
            {
                $( '#' + settings_button_id ).attr( 'disabled', 'disabled' );
            }

            $.ajax({
                url: Contextly.WPSettings.getAjaxUrl(),
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

                        $( '#' + settings_button_id ).attr( 'contextly_access_token', response.contextly_access_token );

                        if ( disabled_flag )
                        {
                            $( '#' + settings_button_id ).removeAttr( 'disabled' );
                        }
                    } else {
                        if ( response.message ) {
                            Contextly.WPAdminMessages.error( "You need a valid API key. Click the \"API Key\" tab above to get one." );
                        }
                    }
                },
                error: function () {
                    $( '#' + settings_button_id ).removeAttr( 'disabled' );
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
            $( '#contextly_warnings').html(
                "<div class='fade " + message_class + "'><p>" + message_text + "</p></div>"
            );
        }
    }
});

$(window)
    .on('contextlyWidgetsLoading', function(e, $context) {
        if (Contextly.Settings.isAdmin()) {
            return;
        }

        // Modules of many types may be placed using multiple methods, e.g.
        // shortcode, WP widget, default placement. And we should only display
        // the most preferred ones hiding the rest.
        var specs = {
            'ctx-module-container': {
                placements: [
                    'ctx_shortcode_placement',
                    'ctx_default_placement'
                ]
            },
            'ctx-subscribe-container': {
                placements: [
                    'ctx_shortcode_placement',
                    'ctx_default_placement'
                ],
                sharesWith: [
                    'ctx-personalization-container',
                    'ctx-channel-container'
                ]
            },
            'ctx-personalization-container': {
                placements: [
                    'ctx_shortcode_placement',
                    'ctx_default_placement'
                ],
                sharesWith: [
                    'ctx-subscribe-container',
                    'ctx-channel-container'
                ]
            },
            'ctx-channel-container': {
                placements: [
                    'ctx_shortcode_placement'
                ],
                sharesWith: [
                    'ctx-subscribe-container',
                    'ctx-personalization-container'
                ]
            },
            'ctx-siderail-container': {
                placements: [
                    'ctx_shortcode_placement',
                    'ctx_widget_placement'
                ]
            },
            'ctx-social-container': {
                placements: [
                    'ctx_shortcode_placement',
                    'ctx_widget_placement',
                    'ctx_default_placement'
                ]
            }
        };

        for (var containerClass in specs) {
            if (!specs.hasOwnProperty(containerClass)) continue;

            var $containers = $context.find('.' + containerClass);
            if (!$containers.length) {
                return;
            }

            var spec = specs[containerClass];
            $.each(spec.placements, function() {
                var preferred = '.' + this;
                var $preferred = $containers.filter(preferred);
                if (!$preferred.length) {
                    return;
                }

                // Some containers may be shared by different modules and every of
                // these modules should go to the most preferred container. The less
                // preferred containers should always loose the container class and,
                // if they aren't shared, be removed from the page.
                var $remove = $containers.not(preferred);
                $remove.removeClass(containerClass);
                if (spec.sharesWith) {
                    $remove = $remove.not('.' + spec.sharesWith.join(', .'))
                }
                $remove.remove();

                // Found the preferred one, stop iterating on the placements.
                return false;
            });
        };

        // Make sure that main module with default placement is last child.
        $context
            .find('.ctx-module-container.ctx_default_placement')
            .not(':last-child')
            .each(function() {
                $(this)
                    .parent()
                    .append(this);
            });
    })
    .on('contextlyWidgetsFailed', function(e, $context, response) {
        Contextly.widget.PageView.onWidgetsLoadingError.apply(this, arguments);
        if ( !Contextly.Settings.isAdmin() ) {
            return;
        }

        var message = '';
        if ( response.error ) {
            if ( response.error_code == Contextly.client.Rest.errors.FORBIDDEN ) {
                message = response.error + " Please check your API settings on the Contextly plugin <a href='admin.php?page=contextly_options&tab=contextly_options_api'>Settings</a> page.";
            } else if ( response.error_code == Contextly.client.Rest.errors.SUSPENDED ) {
                message = "Your account has been suspended. If this is an error, please contact us via <a href='http://contextly.com/contact-us/'>support@contextly.com</a>.";
            } else {
                message = "Please check your API settings on the Contextly plugin <a href='admin.php?page=contextly_options&tab=contextly_options_api'>Settings</a> page.";
            }
        } else {
            message = "Sorry, something seems to be broken. Please contact us via <a href='http://contextly.com/contact-us/'>support@contextly.com</a>.";
        }

        $context
            .find('.ctx-module-container')
            .html(message);
    })
    .on('contextlyPostUpdate', function(e, $context, metadataProvider, gate) {
        var pageId = metadataProvider.getPageId();
        var data = {
            action: 'contextly_publish_post',
            page_id: pageId,
            contextly_nonce: Contextly.WPSettings.getPostNonce(pageId)
        };

        var runUpdate = gate.addReason();
        $.ajax({
            url: Contextly.WPSettings.getAjaxUrl(),
            type: 'post',
            dataType: 'json',
            data: data,
            success: function(response) {
                if ( response != true ) {
                    runUpdate();
                }
            },
            error: function () {
                runUpdate();
            }
        });
    });

})(Contextly.DOM);
