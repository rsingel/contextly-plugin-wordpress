(function() {
		// Create a new plugin class
		tinymce.create('tinymce.plugins.ContextlyPluginLink', {
			init : function(ed, url) {
				var disabled = true;
				// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');
				ed.addCommand('WP_Contextly_Link', function() {
					if ( disabled ) return;
						// Open contextly window for select link
                        jQuery.fn.contextly.openTinyMceLinkPopup();
				});
			
				// Register an example button
				ed.addButton('contextlylink', {
					title : ed.getLang('advanced.link_desc'),
					image : url + '/img/contextly-link.png',
					cmd : 'WP_Contextly_Link'
				});
						
				ed.onNodeChange.add(function(ed, cm, n, co) {
					disabled = co && n.nodeName != 'A';
                    jQuery.contextly.tinymce_editor = ed;
                    jQuery.contextly.tinymce_text = ed.selection.getContent({format : 'text'});
				});
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