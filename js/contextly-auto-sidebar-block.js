( function( blocks, element, components ) {
    var el = element.createElement;
    var RawHTML = components.RawHTML;

    blocks.registerBlockType ( 'contextly-related-links-block/contextly-auto-sidebar', {
        title: 'Contextly Auto-Sidebar',
        description: 'Add a block of algorithmic recommendations to your post. The recommendations will change over time.',
        icon: 'media-document',
        category: 'widgets',

        supports: {
            customClassName: false,
            className: false,
            html: false,
            reusable: false
        },

        attributes: {
            id: {
                type: 'string'
            },
            text: {
                type: 'string'
            }
        },
        edit: function( props ) {
            var attributes = props.attributes;

            return (
                el( 'div', { className: "wp-block-shortcode" },
                    el( 'div', {
                            className: "ctx-autosidebar-container ctx-autosidebar-container--" + attributes.id
                        }
                    ),
                    el( 'p', {}, ' ',
                        el( components.Button, {
                                className: 'button button-large is-button is-default is-primary is-large',
                                onClick: function () {
                                    var sidebar_id = attributes.id;
                                    var sidebar_type = Contextly.widget.types.AUTO_SIDEBAR;

                                    Contextly.PostEditor.sidebarPopup(sidebar_id, sidebar_type, function (sidebar) {
                                        var token = Contextly.PostEditor.buildSidebarToken(sidebar);

                                        if (sidebar.id) {
                                            props.setAttributes({
                                                id: sidebar.id,
                                                text: token
                                            });
                                        }

                                        Contextly.PostEditor.loadData();
                                    });
                                }
                            },
                            'Edit Auto-Sidebar'
                        )
                    )
                )
            )
        },
        save: function( props ) {
            var attributes = props.attributes;
            return el( RawHTML, null, attributes.text);
        }

    } );

} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.components
);
