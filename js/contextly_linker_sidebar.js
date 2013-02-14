// Dummy JavaScript document
// Create a new plugin class
tinymce.create('tinymce.plugins.ContextlyPluginSidebar', {
    init : function(ed, url) {
        var sidebar_id = null;

        ed.addButton('contextlysidebar', {
            title : 'Add Contextly Sidebar into post',
            image : url + '/img/contextly-sidebar.png',
            onclick : function() {
                Contextly.PopupHelper.getInstance().sidebarPopup( sidebar_id );
            }
        });

        ed.onNodeChange.add(function(ed, cm, n, co) {
            sidebar_id = null;

            if ( !co )
            {
                var selected_content = jQuery( n ).html();
                var matches = selected_content.match( /\[contextly_sidebar id=\"([^&]*)\"\]/ );
                if ( matches && matches[1] ) {
                    sidebar_id = matches[1];
                }
            }
        });

    }
});

// Register plugin with a short name
tinymce.PluginManager.add('contextlysidebar', tinymce.plugins.ContextlyPluginSidebar);
