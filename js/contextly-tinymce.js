(function ($) {

	// Sidebar plug-in.
	tinymce.create('tinymce.plugins.Contextly', {
		init: function (editor, url) {
			this.url = url;
			this.editor = editor;

            function saveSelection() {
                // TinyMCE 3 requires us to save & restore bookmark, as IE 8-11 looses
                // selection.
                // TinyMCE 4 doesn't do its job well and looses cursor position, this is
                // critical to sidebar insertion.
                // We don't do it all the time, because there is a bug in TinyMCE 4 & IE:
                // https://github.com/tinymce/tinymce/issues/2027
                if (!tinymce.FocusManager || editor.selection.isCollapsed()) {
                    return editor.selection.getBookmark();
                }

                return null;
            }

            function restoreSelection(bookmark) {
                // Editor looses selection in IE 8-11 in case user does some input in the
                // overlay. TinyMCE 4 introduced FocusManager to solve it, but we must
                // focus the editor prior to any link actions for magic to happen.
                editor.focus();

                // Restore the bookmark for other cases, if it was created by saveSelection().
                if (bookmark) {
                    editor.selection.moveToBookmark(bookmark);
                }
            }

			// Register link command.
			editor.addCommand('WP_Contextly_Link', function () {
				var selection = editor.selection;
				if (selection.isCollapsed() && selection.getNode().nodeName != 'A') {
					alert('First highlight the word or phrase you want to link, then press this button.');
					return;
				}

				// Open contextly window for select text/link.
				var selectedText = selection.getContent({format: 'text'});
                var bookmark = saveSelection();
				Contextly.PostEditor.linkPopup(selectedText, function(link_url, link_title) {
                    restoreSelection(bookmark);

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
						editor.selection.select(e);
						editor.selection.collapse(0);
					}
				});
			});

			editor.addCommand('WP_Contextly_Sidebar', function () {
				var sidebar_id = null, sidebar_type = Contextly.widget.types.SIDEBAR;

				// Try to extract existing sidebar ID from the editor.
				var selection = editor.selection;
				if (!selection.isCollapsed()) {
					var content = selection.getContent({format: 'text'});
					var matches = content.match(Contextly.PostEditor.buildSidebarRegexp());
					if (matches) {
						sidebar_id = matches[2];
						if (matches[1]) {
							sidebar_type = Contextly.widget.types.AUTO_SIDEBAR;
						}
					}
					else {
						selection.collapse();
					}
				}

                var bookmark = saveSelection();
                Contextly.PostEditor.sidebarPopup(sidebar_id, sidebar_type, function (sidebar) {
                    restoreSelection(bookmark);

					var token = Contextly.PostEditor.buildSidebarToken(sidebar);
					editor.execCommand('mceInsertContent', false, token);
				});
			});

			editor.addCommand('WP_Contextly_AutoSidebar', function() {
				var data = Contextly.PostEditor.getData();
				if (!data.auto_sidebar) {
					return;
				}

				editor.selection.collapse();
				var token = Contextly.PostEditor.buildSidebarToken(data.auto_sidebar);
				editor.execCommand('mceInsertContent', false, token);
			});

			var buttons = [];
			editor.addButton('contextlylink', {
				title: this.editor.getLang('advanced.link_desc'),
				image: url + '/img/contextly-link.png',
				cmd: 'WP_Contextly_Link',

				// TinyMCE 4.
				onPostRender: function() {
					buttons.push(this);
				}
			});
			editor.addButton('contextlysidebar', {
				title: 'Create/edit Contextly Sidebar',
				image: url + '/img/contextly-sidebar.png',
				cmd: 'WP_Contextly_Sidebar',

				// TinyMCE 4.
				type: 'splitbutton',
				menu: [
					{
						text: 'Create/edit Contextly Sidebar',
						onclick: function() {
							editor.execCommand('WP_Contextly_Sidebar');
						}
					},
					{
						text: 'Insert Contextly Auto-Sidebar',
						onclick: function() {
							editor.execCommand('WP_Contextly_AutoSidebar');
						}
					}
				],
				onPostRender: function() {
					buttons.push(this);

					// TinyMCE 4 before 4.1.5 (WP 4.0.x).
					// The "image" setting is supported by SplitButton since TinyMCE 4.1.5,
					// so we have to add it here.
					var el;
					if (typeof this.getEl !== 'undefined' && (el = this.getEl())) {
						$(el)
							.find('.mce-ico')
							.css('background-image', 'url("' + url + '/img/contextly-sidebar.png")');
					}
				},

				// TinyMCE 4 before 4.1.5 (WP 4.0.x), see above note.
				icon: 'none'
			});

			var updateEditorContent = function(callback) {
				editor.selection.collapse();
				var bookmark = editor.selection.getBookmark();

				var content = editor.getContent({format: 'raw'});
				var originalLength = content.length;
				content = callback(content);
				if (content.length !== originalLength) {
					editor.setContent(content);
					editor.selection.moveToBookmark(bookmark);
				}
			};

			// The widget removal handler.
			var onWidgetRemoved = function (e, widgetType, id, widget) {
				switch (widgetType) {
					case Contextly.widget.types.AUTO_SIDEBAR:
						// Remove the auto-sidebar shortcode ignoring the ID.
						id = null;
						// no break;

					case Contextly.widget.types.SIDEBAR:
						updateEditorContent(function(content) {
							var regexp = Contextly.PostEditor.buildSidebarRegexp(id, widgetType, 'ig');
							return content.replace(regexp, '')
						});
						break;
				}
			};

			// The widget update handler.
			var onWidgetUpdated = function (e, widgetType, id, widget) {
				switch (widgetType) {
					case Contextly.widget.types.AUTO_SIDEBAR:
						updateEditorContent(function(content) {
							var regexp = Contextly.PostEditor.buildSidebarRegexp(null, widgetType, 'ig');
							var token = Contextly.PostEditor.buildSidebarToken(widget);
							return content.replace(regexp, token);
						});

						break;
				}
			};

			var setButtonsState = function(enabled) {
				var disabled = !enabled;

				// TinyMCE 4.
				if (buttons.length) {
					$.each(buttons, function() {
						if ($.isFunction(this.active)) {
							this.disabled(disabled);
						}
					});
				}
				// TinyMCE 3.
				else {
					editor.controlManager.setDisabled('contextlylink', disabled);
					editor.controlManager.setDisabled('contextlysidebar', disabled);
				}
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

			var onEditorInit = function () {
				// Set up event handlers to enable/disable buttons on the settings
				// loading events.
				var eventHandlers = {
					'contextlyWidgetRemoved': onWidgetRemoved,
					'contextlyWidgetUpdated': onWidgetUpdated,
					'contextlyDataLoaded': onDataLoaded
				};
				var $window = $(window);
				for (var key in eventHandlers) {
					$window.bind(eventNamespace(key), eventHandlers[key]);
				}

				// Initialize button state depending on data loading state.
				setButtonsState(Contextly.PostEditor && Contextly.PostEditor.isLoaded);
			};

			var onEditorRemove = function() {
				$(window).unbind(eventNamespace());
			};

			// Init buttons state right after the editor initialization has been
			// finished. Unbind the events on editor removing.
			// TinyMCE 4.
			if ($.isFunction(editor.on)) {
				editor.on('init', onEditorInit);
				editor.on('remove', onEditorRemove);
			}
			// TinyMCE 3.
			else {
				if (editor.onInit && $.isFunction(editor.onInit.add)) {
					editor.onInit.add(onEditorInit);
				}
				if (editor.onRemove && $.isFunction(editor.onRemove.add)) {
					editor.onRemove.add(onEditorRemove);
				}
			}
		},

		// TinyMCE 3 compatibility.
		createControl: function(name, controlManager) {
			switch (name) {
				case 'contextlysidebar':
					var control = controlManager.createSplitButton('contextlysidebar', {
						title: 'Create/edit Contextly Sidebar',
						image: this.url + '/img/contextly-sidebar.png',
						cmd: 'WP_Contextly_Sidebar'
					});

					control.onRenderMenu.add(function(control, menu) {
						menu.add({
							title: 'Create/edit Contextly Sidebar',
							cmd: 'WP_Contextly_Sidebar'
						});

						menu.add({
							title: 'Insert Contextly Auto-Sidebar',
							cmd: 'WP_Contextly_AutoSidebar'
						});
					});

					return control;
			}

			return null;
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
