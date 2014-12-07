(function ($) {

	// Sidebar plug-in.
	tinymce.create('tinymce.plugins.Contextly', {
		init: function (editor, url) {
			// Register link command.
			editor.addCommand('WP_Contextly_Link', function () {
				var selection = editor.selection;
				if (selection.isCollapsed() && selection.getNode().nodeName != 'A') {
					alert('First highlight the word or phrase you want to link, then press this button.');
					return;
				}

				// Open contextly window for select text/link.
				var selectedText = selection.getContent({format: 'text'});
				Contextly.PostEditor.linkPopup(selectedText, function(link_url, link_title) {
					var attrs = {
						href: link_url,
						title: link_title
					}, e;
					e = editor.dom.getParent(editor.selection.getNode(), 'A');
					if (!attrs.href || attrs.href == 'http://') return;
					if (e == null) {
						editor.getDoc().execCommand("unlink", false, null);
						editor.getDoc().execCommand("CreateLink", false, "#mce_temp_url#", {skip_undo: 1});
						tinymce.each(editor.dom.select("a"), function (n) {
							if (editor.dom.getAttrib(n, 'href') == '#mce_temp_url#') {
								e = n;
								editor.dom.setAttribs(e, attrs);
							}
						});
						if (jQuery(e).text() == '#mce_temp_url#') {
							editor.dom.remove(e);
							e = null;
						}
					} else {
						editor.dom.setAttribs(e, attrs);
					}
					if (e && (e.childNodes.length != 1 || e.firstChild.nodeName != 'IMG')) {
						editor.focus();
						editor.selection.select(e);
						editor.selection.collapse(0);
					}
				});
			});

			// Register an example button
			editor.addButton('contextlylink', {
				title: editor.getLang('advanced.link_desc'),
				image: url + '/img/contextly-link.png',
				cmd: 'WP_Contextly_Link'
			});

			editor.addButton('contextlysidebar', {
				title: 'Add Contextly Sidebar into post',
				image: url + '/img/contextly-sidebar.png',
				onclick: function () {
					var sidebar_id = null;

					// Try to extract existing sidebar ID from the editor.
					var selection = editor.selection;
					if (!selection.isCollapsed()) {
						var content = selection.getContent({format: 'text'});
						var matches = content.match(buildSidebarRegexp());
						if (matches) {
							sidebar_id = matches[1];
						}
						else {
							selection.collapse();
						}
					}

					Contextly.PostEditor.sidebarPopup(sidebar_id, function (sidebar) {
						var token = buildSidebarToken(sidebar);
						editor.execCommand('mceInsertContent', false, token);
					});
				}
			});

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
				if (sidebar.type == Contextly.widget.types.AUTO_SIDEBAR) {
					token += '_auto';
				}
				token += '_sidebar id="' + sidebar.id + '"]';
				return token;
			};

			// The widget removal handler.
			var onWidgetRemoved = function (e, widgetType, id, widget) {
				switch (widgetType) {
					case 'sidebar':
						// Collapse selection and save bookmark to keep caret position.
						editor.selection.collapse();
						var bookmark = editor.selection.getBookmark();

						var content = editor.getContent({format: 'raw'});
						var originalLength = content.length;
						content = content.replace(buildSidebarRegexp(id, 'ig'), '');
						if (content.length !== originalLength) {
							editor.setContent(content);
							editor.selection.moveToBookmark(bookmark);
						}
						break;
				}
			};

			var setButtonsState = function(enabled) {
				var disabled = !enabled;
				editor.controlManager.setDisabled('contextlylink', disabled);
				editor.controlManager.setDisabled('contextlysidebar', disabled);
			};

			var onDataLoaded = function() {
				// Activate buttons on data loaded.
				setButtonsState(true);
			};

			// Adds unique namespace to the event type.
			var eventNamespace = function (type) {
				if (!type) {
					type = '';
				}
				return type + '.contextlyTinymce' + editor.id;
			};

			// Init buttons state right after the editor initialization has been
			// finished.
			editor.onInit.add( function () {
				// Set up event handlers to enable/disable buttons on the settings
				// loading events.
				var eventHandlers = {
					'contextlyWidgetRemoved': onWidgetRemoved,
					'contextlyDataLoaded': onDataLoaded
				};
				var $window = $(window);
				for (var key in eventHandlers) {
					$window.bind(eventNamespace(key), eventHandlers[key]);
				}

				// Initialize button state depending on data loading state.
				setButtonsState(Contextly.PostEditor.isLoaded);
			});

			// Unbind the events on editor removing.
			editor.onRemove.add(function () {
				$(window).unbind(eventNamespace());
			});
		},

		/**
		 * Returns information about the plugin as a name/value array.
		 * The current keys are longname, author, authorurl, infourl and version.
		 *
		 * @return {Object} Name/value array containing information about the plugin.
		 */
		getInfo: function () {
			return {
				longname: 'Contextly',
				author: 'Contextly',
				authorurl: 'http://contextly.com',
				infourl: '',
				version: "1.0"
			};
		}

	});

	// Register plugin with a short name
	tinymce.PluginManager.add('contextly', tinymce.plugins.Contextly);

})(jQuery);
