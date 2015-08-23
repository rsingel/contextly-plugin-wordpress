(function( $ ) {

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
				registerContextlyQtEventHandlers();
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
                var sidebar_id = null, sidebar_type = Contextly.widget.types.SIDEBAR;

                // Try to extract existing sidebar ID from the editor.
                var selection_text = getSelectedText( c );
                var matches = selection_text.match(Contextly.PostEditor.buildSidebarRegexp());
                if (matches) {
					sidebar_id = matches[2];
					if (matches[1]) {
						sidebar_type = Contextly.widget.types.AUTO_SIDEBAR;
					}
                }

                Contextly.PostEditor.sidebarPopup(sidebar_id, sidebar_type, function (sidebar) {
                    var token = Contextly.PostEditor.buildSidebarToken(sidebar);
                    QTags.insertContent( token );
                });
            },
            null,
            null,
            'Contextly Sidebar',
            500
        );
    }

	// Adds unique namespace to the event type.
	function eventNamespace(type) {
		type = type || '';
		return type + '.contextlyQTags';
	}

	function updateEditorContent(callback)
	{
		if ( Contextly.PostEditor.isWysiwygActive() )
		{
			return;
		}

		var textarea = $('#content');
		if ( !textarea.length )
		{
			return;
		}

		var content = textarea.val() || '';
		var originalLength = content.length;
		content = callback( content );
		if ( content.length !== originalLength )
		{
			textarea.val(content);
		}
	}

	// The widget removal handler.
	function onWidgetRemoved(e, widgetType, id, widget) {
		switch (widgetType) {
			case Contextly.widget.types.AUTO_SIDEBAR:
				// Remove the auto-sidebar shortcode ignoring the ID.
				id = null;
				// no break;

			case Contextly.widget.types.SIDEBAR:
				updateEditorContent(function(content) {
					var regexp = Contextly.PostEditor.buildSidebarRegexp(id, widgetType, 'ig');
					return content.replace(regexp, '');
				});
				break;
		}
	}

	// The widget update handler.
	function onWidgetUpdated(e, widgetType, id, widget) {
		switch (widgetType) {
			case Contextly.widget.types.AUTO_SIDEBAR:
				updateEditorContent(function(content) {
					var regexp = Contextly.PostEditor.buildSidebarRegexp(null, widgetType, 'ig');
					var token = Contextly.PostEditor.buildSidebarToken(widget);
					return content.replace(regexp, token);
				});

				break;
		}
	}

	function registerContextlyQtEventHandlers()
	{
		var eventHandlers = {
			'contextlyWidgetRemoved': onWidgetRemoved,
			'contextlyWidgetUpdated': onWidgetUpdated
		};
		var $window = $(window);
		for (var key in eventHandlers) {
			$window.bind(eventNamespace(key), eventHandlers[key]);
		}
	}

})( jQuery );
