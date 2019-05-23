(function( $ ) {

	Contextly.PostEditor = Contextly.createClass({

		statics: {

			/**
			 * Static constructor is called immediately on class creation.
			 */
			construct: function() {
				this.isLoading = false;
				this.isLoaded = false;
				this.isUpdateQueued = false;
				this.data = null;
				this.error = false;

				this.loadingMessage = 'Loading...';
			},

			buildAjaxConfig: function (method, addon) {
				var settings = Contextly.WPSettings;

				var result = $.extend(true, {
					url     : settings.getAjaxUrl(),
					type    : 'POST',
					dataType: 'json'
				}, addon);

				var params = result.data || {};

				var postId = settings.getEditorPostId();
				result.data = {
					action: 'contextly_widgets_editor_request',
					nonce: settings.getPostNonce(postId),
					post_id: postId,
					method: method,
					params: params
				};

				return result;
			},

			loadData: function () {
				if (this.isLoading) {
					return;
				}
				this.isLoading = true;

				$.ajax(this.buildAjaxConfig('get-editor-data', {
					success: this.proxy(this.onDataLoadingSuccess),
					error  : this.proxy(this.onDataLoadingFailure)
				}));

				this.fireEvent('contextlyDataLoading');
			},

			onDataLoadingSuccess: function(data) {
				this.isLoading = false;
				this.isLoaded = true;
				this.data = data;

                this.attachPluginPostStatusInfo();

                this.updateAdminControls();
				this.attachPublishConfirmation();
				this.fireEvent('contextlyDataLoaded');
			},

			onDataLoadingFailure: function(response) {
				this.isLoading = false;
				this.error = true;

				this.updateAdminControls();
				this.fireEvent('contextlyDataFailed', response);
			},

			getData: function() {
				if (!this.isLoaded) {
					return null;
				}

				return this.data;
			},

            /**
             * Makes a safe clone of a variable created in an iframe.
             *
             * IE 8-11 doesn't allow to run methods on objects that have been created
             * in an iframe that doesn't exist anymore. To solve the problem, we clone
             * the object making sure no methods are copied.
             */
            cloneFrameObject: function(obj) {
                var copy;
                if (Contextly.Utils.isString(obj)) {
                    // Make sure we return a primitive.
                    copy = '' + obj;
                }
                else if (Contextly.Utils.isArray(obj)) {
                    copy = [];
                    for (var i = 0; i < obj.length; i++) {
                        copy[i] = this.cloneFrameObject(obj[i]);
                    }
                }
                else if (Contextly.Utils.isObject(obj)) {
                    copy = {};
                    for (var key in obj) {
                        if (obj.hasOwnProperty(key)) {
                            if (Contextly.Utils.isFunction(obj[key])) {
                                Contextly.Utils.error('Unable to clone function created in iframe');
                            }

                            copy[key] = this.cloneFrameObject(obj[key]);
                        }
                    }
                }
                else {
                    copy = obj;
                }
                return copy;
            },

			setSnippet: function (savedFrameSnippet) {
                var savedSnippet = this.cloneFrameObject(savedFrameSnippet);

				this.data.snippet = savedSnippet;
				this.updateAdminControls();
				this.updateWidgetsPreview();
				this.fireEvent('contextlyWidgetUpdated', 'snippet', savedSnippet.id, savedSnippet);
			},

			removeSnippet: function (id) {
				// Get removed snippet if any.
				var removedSnippet = null;
				if (this.data.snippet.id && this.data.snippet.id == id) {
					removedSnippet = this.data.snippet;
				}

				// On empty snippet we still need settings for editor to work properly.
				this.data.snippet = {
					settings: this.data.snippet.settings
				};

				this.updateAdminControls();
				this.updateWidgetsPreview();
				this.fireEvent('contextlyWidgetRemoved', 'snippet', id, removedSnippet);
			},

			setSidebar: function (savedFrameSidebar) {
                var savedSidebar = this.cloneFrameObject(savedFrameSidebar);

				this.data.sidebars[savedSidebar.id] = savedSidebar;
				this.fireEvent('contextlyWidgetUpdated', 'sidebar', savedSidebar.id, savedSidebar);
			},

			removeSidebar: function (id) {
				var removedSidebar = null;
				if (typeof this.data.sidebars[id] !== 'undefined') {
					removedSidebar = this.data.sidebars[id];
					delete this.data.sidebars[id];
				}

				this.fireEvent('contextlyWidgetRemoved', 'sidebar', id, removedSidebar);
			},

			setAutoSidebar: function (savedFrameAutoSidebar, frameRemovedId) {
                var savedAutoSidebar = this.cloneFrameObject(savedFrameAutoSidebar);
                var removedId = this.cloneFrameObject(frameRemovedId);

				this.data.auto_sidebar = savedAutoSidebar;
				this.fireEvent('contextlyWidgetUpdated', 'auto-sidebar', savedAutoSidebar.id || removedId, savedAutoSidebar);
			},

			removeAutoSidebar: function(defaultAutoSidebar) {
				var removedAutoSidebar = this.data.auto_sidebar;
				this.data.auto_sidebar = defaultAutoSidebar;
				this.fireEvent('contextlyWidgetRemoved', 'auto-sidebar', removedAutoSidebar.id || null, removedAutoSidebar);
			},

			fireEvent: function (type) {
				// Remove the type of event.
				var args = Array.prototype.slice.call(arguments, 1);

				$(window).triggerHandler(type, args);
			},

			proxy: function (func, context) {
				if (typeof context === 'undefined') {
					context = this;
				}

				return function () {
					return func.apply(context, arguments);
				};
			},

			buildEditorUrl: function (type) {
				var s = Contextly.WPSettings;

				var url = s.getEditorUrl();
				if (url.indexOf('?') === -1) {
					url += '?';
				}
				else {
					url += '&';
				}
				url += 'editor-type=' + encodeURIComponent(type);

				return url;
			},

			snippetPopup: function () {
				this.openEditor('snippet');
			},

			sidebarPopup: function (snippet_id, snippet_type, callback, context) {
				this.openEditor('sidebar', {
					getSidebarInfo: function () {
						return {
							id: snippet_id,
							type: snippet_type
						};
					},
					callback: this.proxy(function(savedSidebar) {
						if (callback) {
							context = context || window;
							callback.call(context, savedSidebar);
						}
						this.updateWidgetsPreview();
					})
				});
			},

			linkPopup: function (text, callback, context) {
				this.openEditor('link', {
					callback: this.proxy(function (result) {
						if (callback) {
							context = context || window;
							callback.call(context, result.link_url, result.link_title);
						}
						this.updateWidgetsPreview();
					}),
					getText: function () {
						return text;
					}
				});
			},

			openEditor: function(type, api) {
				if (!this.isLoaded) {
					return;
				}

				if (!this.data) {
					this.showStubPopup();
					return;
				}

				// Extend API with default methods (caller is able to overwrite them).
				api = api || {};
				api = $.extend({
					getSettings: this.proxy(this.getData),
					buildAjaxConfig: this.proxy(this.buildAjaxConfig),
					setSidebar: this.proxy(this.setSidebar),
					removeSidebar: this.proxy(this.removeSidebar),
					setAutoSidebar: this.proxy(this.setAutoSidebar),
					removeAutoSidebar: this.proxy(this.removeAutoSidebar),
					setSnippet: this.proxy(this.setSnippet),
					removeSnippet: this.proxy(this.removeSnippet),
					setOverlayCloseButtonHandler: function (callback) {
						Contextly.overlay.Editor.setOptions({
							extend: true,
							onClose: callback
						});
					},
					closeOverlay: function () {
						Contextly.overlay.Editor.close();
					}
				}, api);

				// Set up event handler to respond on overlay ready events with an API.
				$(window)
					.unbind('contextlyOverlayReady')
					.bind('contextlyOverlayReady', function () {
						return api;
					});

				// Load an iframe inside modal.
				var url = this.buildEditorUrl(type);
				Contextly.overlay.Editor.open(url);
			},

			showStubPopup: function () {
				this.url = this.url || 'http://contextly.com/contact-us/';
				window.open(this.url);
			},

			widgetHasLinks: function(widget) {
				for (var key in widget.links) {
					if (widget.links[key] && widget.links[key].length) {
						return true;
					}
				}

				return false;
			},

			attachPublishConfirmation: function () {
                $('#publish')
					.unbind('click.contextlyPublishConfirmation')
					.bind('click.contextlyPublishConfirmation', this.proxy(function () {
						var wp_settings = Contextly.WPSettings.getWPSettings();

						if (wp_settings.publish_confirmation && wp_settings.publish_confirmation == "1" && this.data !== null) {
							// Put snippet and sidebars together for a check.
							var widgets = $.extend({
								'0': this.data.snippet
							}, this.data.sidebars);
							if (this.data.auto_sidebar && this.data.auto_sidebar.id) {
								widgets[this.data.auto_sidebar.id] = this.data.auto_sidebar;
							}

							var linkExists = false;
							for (var key in widgets) {
								if (this.widgetHasLinks(widgets[key])) {
									linkExists = true;
									break;
								}
							}
							if (!linkExists) {
								this.showPublishConfirmation();
								return false;
							}
						}

						return true;
					}));
			},

			updateWidgetsPreview: function() {
				if (this.isUpdateQueued) {
					return;
				}
				this.isUpdateQueued = true;

				setTimeout(this.proxy(function() {
					this.isUpdateQueued = false;
					Contextly.ready('widgets');
				}), 1);
			},

			showPublishConfirmation: function () {
				$('.button-primary').removeClass('button-primary-disabled');
				$('#ajax-loading').css('visibility', 'hidden');

				var add_links_value = "Choose Related Posts";
				var publish_now_value = $('#publish').attr("value");
				var add_links_class = 'ctx_add_related_links_btn';
				var publish_now_class = 'ctx_publish_now_btn';

				var html = '<div class="ctx_publish_confirmation">' +
					'<h3 class="ctx_confirmation_title">Publish confirmation</h3>' +
					"<div style='float:left; padding-bottom:20px;'>This post doesn't have any chosen links to other posts. Would you like to do that now?<br /><br /> If you want to add a sidebar, close this window, put the cursor where you'd like the sidebar to be and click the sidebar button.</div>" +
					'<input type="button" value="' + add_links_value + '" class="button button-primary ' + add_links_class + '" />' +
					'<input type="button" value="' + publish_now_value + '" class="button ' + publish_now_class + '" style="margin-left: 20px; float: right;" />' +
					'</div>';
				var popupContent = $(html);
				Contextly.overlay.Default.open(popupContent, {
					width: 440,
					height: 'auto'
				});

				// Bind click handlers to buttons.
				popupContent
					.find('input.' + add_links_class)
					.bind('click.contextlyPublishConfirmation', this.proxy(function() {
						var broadcastType = Contextly.overlay.broadcastTypes.AFTER_CLOSE;
						jQuery(window).one(broadcastType, this.proxy(this.snippetPopup));

						Contextly.overlay.Default.close();
					}));
				popupContent
					.find('input.' + publish_now_class)
					.bind('click.contextlyPublishConfirmation', function() {
						var broadcastType = Contextly.overlay.broadcastTypes.AFTER_CLOSE;
						jQuery(window).one(broadcastType, function() {
							$('#publish')
								.unbind('.contextlyPublishConfirmation')
								.click();
						});

						Contextly.overlay.Default.close();
					});
			},

			showErrorDetails: function() {
				// TODO Provide more details about the reason if possible (suspended account, etc).
				var html = '<div class="ctx_data_error_details">'
					+ 'We were unable to load Contextly data for this post. Please check your API settings on the Contextly plugin <a href="admin.php?page=contextly_options&tab=contextly_options_api" target="_blank">settings page</a> or <a href="http://contextly.com/contact-us/" target="_blank">contact us</a>.'
					+ '</div>';
				var content = $(html);
				Contextly.overlay.Default.open(content, {
					width: 440,
					height: 'auto'
				});
			},

			updateAdminControls: function () {
				var label,
					callback;

				if (this.error) {
					label = 'Unable to load';
					callback = this.showErrorDetails;
				}
				else {
					callback = this.snippetPopup;
					if (this.widgetHasLinks(this.data.snippet)) {
						label = 'Edit Related Posts';
					} else {
						label = 'Choose Related Posts';
					}
				}

				$('.ctx_snippets_editor_btn')
					.html(label)
					.removeAttr('disabled')
					.unbind('.contextlySnippetEditor')
					.bind('click.contextlySnippetEditor', this.proxy(callback));
			},

			buildSidebarRegexp: function (id, type, modifiers) {
				var pattern = '\\[contextly';
				switch (type) {
					case Contextly.widget.types.AUTO_SIDEBAR:
						pattern += '(_auto)';
						break;

					case Contextly.widget.types.SIDEBAR:
						break;

					default:
						pattern += '(_auto)?';
						break;
				}
				pattern += '_sidebar(?:\\s+id="';
				if (id) {
					pattern += id;
				}
				else {
					pattern += '([^"\\]]+)';
				}
				pattern += '"\\s*)?\\]';
				modifiers = modifiers || 'i';
				return new RegExp(pattern, modifiers);
			},

			buildSidebarToken: function (sidebar) {
				// Build the token code.
				var token = '[contextly';
				if (sidebar.type == Contextly.widget.types.AUTO_SIDEBAR) {
					token += '_auto';
				}
				token += '_sidebar';
				if (typeof sidebar.id !== 'undefined') {
					token += ' id="' + sidebar.id + '"';
				}
				token += ']';
				return token;
			},

			isWysiwygActive: function() {
				var instance;
				if ( !tinymce || !( instance = tinymce.get('content') ) )
				{
					return false;
				}

				return ( typeof instance.isHidden === 'function' ) && !instance.isHidden();
			},

			contextlyPluginPostStatusInfo: function () {
                var PluginPostStatusInfo = window.wp.editPost.PluginPostStatusInfo;

                return wp.element.createElement(
                    PluginPostStatusInfo,
                    {
                        className: 'contextly-plugin-post-status-info',
                    },
                    wp.element.createElement('label', null, 'Contextly:'),
                    wp.element.createElement('div',
                        {
                            className: 'button action button-primary ctx_snippets_editor_btn alignright',
                        },
                        Contextly.PostEditor.loadingMessage
                    )
                )
            },

            attachPluginPostStatusInfo: function () {
                if (wp.plugins && typeof wp.plugins.registerPlugin !== "undefined") {
                    var registerPlugin = wp.plugins.registerPlugin;
                    registerPlugin( 'contextly-related-links', {
                        render: Contextly.PostEditor.contextlyPluginPostStatusInfo
                    } );

                    // watch for status update
                    $("body").on('DOMSubtreeModified', ".edit-post-sidebar", function() {
                        var contextly_button = $(this).find('.ctx_snippets_editor_btn');
                        if (contextly_button.length && contextly_button.text() == Contextly.PostEditor.loadingMessage) {
                            Contextly.PostEditor.loadData();
                        }
                    });
                }
			}

		}


	});

})( jQuery );
