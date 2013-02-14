(function() {
		// Create a new plugin class
		tinymce.create('tinymce.plugins.ContextlyPluginLink', {
            'static' : {
                selected_text: null,
                editor: null,

                setSelectedText: function( text ) {
                    this.selected_text = text;
                },

                getSelectedText: function () {
                    return this.selected_text;
                },

                setEditor: function( editor ) {
                    this.editor = editor;
                },

                getEditor: function () {
                    return this.editor;
                }
            },

            init : function(ed, url) {
                var disabled = true;

				// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');
				ed.addCommand('WP_Contextly_Link', function() {
					if ( disabled ) return;
						// Open contextly window for select link
                    Contextly.PopupHelper.getInstance().linkPopup();
				});

				// Register an example button
				ed.addButton('contextlylink', {
					title : ed.getLang('advanced.link_desc'),
					image : url + '/img/contextly-link.png',
					cmd : 'WP_Contextly_Link'
				});

				ed.onNodeChange.add(function(ed, cm, n, co) {
					disabled = co && n.nodeName != 'A';
                    var selected_text = ed.selection.getContent({format : 'text'});

                    tinymce.plugins.ContextlyPluginLink.setSelectedText(selected_text);
                    tinymce.plugins.ContextlyPluginLink.setEditor(ed);
				});
			},

            insertLink : function ( link_url, link_title ) {
                var ed = tinymce.plugins.ContextlyPluginLink.getEditor();

                var attrs = {
                    href : link_url,
                    title : link_title
                }, e;
                e = ed.dom.getParent(ed.selection.getNode(), 'A');
                if ( ! attrs.href || attrs.href == 'http://' ) return;
                if (e == null) {
                    ed.getDoc().execCommand("unlink", false, null);
                    ed.getDoc().execCommand("CreateLink", false, "#mce_temp_url#", {skip_undo : 1});
                    tinymce.each(ed.dom.select("a"), function(n) {
                        if (ed.dom.getAttrib(n, 'href') == '#mce_temp_url#') {
                            e = n;
                            ed.dom.setAttribs(e, attrs);
                        }
                    });
                    if ( jQuery(e).text() == '#mce_temp_url#' ) {
                        ed.dom.remove(e);
                        e = null;
                    }
                } else {
                    ed.dom.setAttribs(e, attrs);
                }
                if ( e && (e.childNodes.length != 1 || e.firstChild.nodeName != 'IMG') ) {
                    ed.focus();
                    ed.selection.select(e);
                    ed.selection.collapse(0);
                }
            },

			/**
			 * Returns information about the plugin as a name/value array.
			 * The current keys are longname, author, authorurl, infourl and version.
			 *
			 * @return {Object} Name/value array containing information about the plugin.
			 */
			getInfo : function() {
				return {
					longname : 'Contextly Link Dialog',
					author : 'Contextly',
					authorurl : 'http://contextly.com',
					infourl : '',
					version : "1.0"
				};
			}
		});

		// Register plugin with a short name
		tinymce.PluginManager.add('contextlylink', tinymce.plugins.ContextlyPluginLink);
	}
)();
