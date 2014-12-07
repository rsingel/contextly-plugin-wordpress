(function( $ ) {

    var buildSidebarRegexp = function (id, modifiers) {
        var pattern = '\\[contextly(?:_auto)?_sidebar\\s+id="';
        if (id) {
            pattern += id;
        }
        else {
            pattern += '([^"\\]]+)';
        }
        pattern += '"\\s*\\]';
        modifiers = modifiers || 'i';
        return new RegExp(pattern, modifiers);
    };

    var buildSidebarToken = function (sidebar) {
        // Build the token code.
        var token = '[contextly';
        if (sidebar.type == Contextly.widget.types.AUTO_SIDEBAR)
        {
            token += '_auto';
        }
        token += '_sidebar id="' + sidebar.id + '"]';
        return token;
    };

    function getSelectedText( canvas )
    {
        if ( document.selection ) // IE
        {
            canvas.focus();
            return document.selection.createRange().text;
        }
        else
        {
            var selected_text = canvas.value.substring( canvas.selectionStart, canvas.selectionEnd );
            canvas.focus();

            return selected_text;
        }
    }

    jQuery( document).ready(
        function () {
            if ( typeof QTags != "undefined" )
            {
                registerContextlyQtLinkButton();
                registerContextlyQtSidebarButton();
            }
        }
    );

    function registerContextlyQtLinkButton()
    {
        QTags.addButton(
            'ctx_link',
            'Contextly Link',
            function(e, c, ed) {
                var selection_text = getSelectedText( c );

                if ( !selection_text ) {
                    alert('First highlight the word or phrase you want to link, then press this button.');
                    return;
                }

                Contextly.PostEditor.linkPopup(selection_text, function(link_url, link_title) {
                    var tag = '<a href="' + link_url + '">' + selection_text + '</a>';
                    QTags.insertContent( tag );
                });
            },
            null,
            null,
            'Contextly Link',
            29
        );
    }

    function registerContextlyQtSidebarButton()
    {
        QTags.addButton(
            'ctx_sidebar',
            'Contextly Sidebar',
            function(e, c, ed) {
                var sidebar_id = null;

                // Try to extract existing sidebar ID from the editor.
                var selection_text = getSelectedText( c );
                var matches = selection_text.match(buildSidebarRegexp());
                if (matches) {
                    sidebar_id = matches[1];
                }

                Contextly.PostEditor.sidebarPopup(sidebar_id, function (sidebar) {
                    var token = buildSidebarToken(sidebar);
                    QTags.insertContent( token );
                });
            },
            null,
            null,
            'Contextly Sidebar',
            500
        );
    }

})( jQuery );
