# Actions and filters

There is a number of actions and filters you may want to use from your plugin or theme in order to customize Contextly plugin behavior or implement some non-standard use case.

### `contextly_print_metatags`

The action prints Contextly metatag for the passed in post. This action is also called by the Contextly plugin itself to output the metadata on the post page, when enabled. You may want to cancel default call through `contextly_post_metatag_options` filter and call it manually at a different point, or get it for the AJAX result when rendering the post dynamically and replacing the previous one.

Arguments:

 * `$post`: the post to print metatags for.
 * `$params`: an array of the following optional parameters:
   * `enabled`: on/off flag.
   * `source`: string key identifying the caller.

Example:
```php
global $post;
// Just pass some defaults, this array can be empty or omitted.
$params = array(
	'enabled' => TRUE,
);
do_action( 'contextly_print_metatags', $post, $params );
```

### `contextly_post_metatag_options`

The filter allows you to adjust behavior of the `contextly_print_metatags` action.

Arguments:
 * `$params`: the following is passed by default call:
   * `enabled`: on/off flag. Set to `FALSE` and nothing will be printed.
   * `source`: set to `contextly-linker` on default calls, just to distinguish from custom calls.
   * `editor`: boolean flag saying whether this is for the post editor administration page or not.
 * `$post`: the post object.

Example:
```php
add_filter( 'contextly_post_metatag_options', 'my_plugin_metatag_options', 10, 2 );

function my_plugin_metatag_options( $params, $post )
{
	// Cancel default call on non-admin pages only.
	if (empty($params['editor']) && !empty($params['source']) && $params['source'] === 'contextly-linker') {
		$params['enabled'] = FALSE;
	}

	return $params;
}
```

### `contextly_print_init_script`

The action prints the script tag with initial page set up. The only requirement is that it must be executed on the page before any launch scripts. Default one is printed in `wp_head` action handler, but it may be customized or cancelled using WordPress config, see [Constants](constants.md) documentation page. Default call may also be cancelled through `contextly_init_script_options` filter. The init script may be executed many times on the same page, the actual code will only run once, so you don't have to check its existence manually in any edge case.

Arguments:

 * `$params`: an array of the following optional parameters, they will be joined with defaults, passed to `contextly_init_script_options` filter and then used:
   * `enabled`: on/off flag for the init script.
   * `source`: string key identifying the caller.
   * `preload`: on/off flag for pre-loading scripts from the CDN.
   * `editor`: boolean flag to be turned on for the post editor admin page.

Example:
```php
// Give some defaults.
$params = array(
	'enabled' => TRUE,
	'preload' => TRUE,
);
do_action( 'contextly_print_init_script', $params );
```

### `contextly_init_script_options`

Filter allows you to adjust behavior of the `contextly_print_init_script` action.

Arguments:
 * `$params`: the following is passed in by default call:
   * `enabled`: on/off flag. Set to `FALSE` and nothing will be printed.
   * `source`: is set to `contextly-linker` on default calls, just to distinguish from custom calls.
   * `preload`: on/off flag for pre-loading scripts from the CDN, enabled by default.
   * `editor`: boolean flag saying whether this is for the post editor administration page or not.

Example:
```php
add_filter( 'contextly_init_script_options', 'my_plugin_init_script_options' );

function my_plugin_init_script_options( $params )
{
	// Modify default call behavior only.
	if (!empty($params['source']) && $params['source'] === 'contextly-linker') {
		// Cancel on non-admin pages only.
		if (empty($params['editor'])) {
			$params['enabled'] = FALSE;
		}

		// Turn off pre-loading.
		$params['preload'] = FALSE;
	}

	return $params;
}
```

### `contextly_print_launch_script`

The action prints the script tag that launches rendering of the Contextly modules. Default one is printed in `wp_footer` action handler, but it may be customized or cancelled using WordPress config, see [Constants](constants.md) documentation page. Default call can also be cancelled using `contextly_launch_script_options` filter. The launch script must be executed once for each post on the page.

Arguments:

 * `$post`: the post object.
 * `$params`: an array of the following parameters, any of which may be omitted:
   * `enabled`: on/off flag for the launch script.
   * `source`: string key identifying the caller.
   * `metadata`: on/off flag to include metadata into the script, otherwise it is taken from the page header. Please note that metadata is still required in page header for the indexing robot.
   * `context`: jQuery selector of the post, whole page is used by default or when it's empty. It must give the same result in case the page has changed, so the most preferred is the HTML ID selector.
   * `editor`: boolean flag to be turned on for the post editor admin page.

Example:
```php
// Print launch script with built-in metadata and giving the context.
global $post;
$params = array(
	'enabled' => TRUE,
	'metadata' => TRUE,
	'context' => '#post-123',
);
do_action( 'contextly_print_launch_script', $post, $params );
```

### `contextly_launch_script_options`

The filter allows you to adjust behavior of the `contextly_print_launch_script` action.

Arguments:
 * `$params`: the following is passed in by default call:
   * `enabled`: on/off flag. Set to `FALSE` and nothing will be printed.
   * `source`: is set to `contextly-linker` on default calls, just to distinguish from custom calls.
   * `metadata`: on/off flag to include metadata into the script.
   * `context`: jQuery selector of the post.
   * `editor`: boolean flag saying whether this is for the post editor administration page or not.
 * `$post`: the post object.

Example:
```php
add_filter( 'contextly_launch_script_options', 'my_plugin_launch_script_options', 10, 2 );

function my_plugin_launch_script_options( $params, $post )
{
	// Modify behavior of the default call only.
	if (!empty($params['source']) && $params['source'] === 'contextly-linker') {
		// Cancel on non-admin pages only.
		if (empty($params['editor'])) {
			$params['enabled'] = FALSE;
		}

		// Force built-in metadata
		$params['metadata'] = TRUE;
	}

	return $params;
}
```

### `contextly_print_removal_script`

The action prints the script tag that removes Contextly modules from the passed context or whole page. There is no default call of this action. Any call can also be cancelled or customized using `contextly_removal_script_options` filter.

Arguments:

 * `$post`: the post object.
 * `$params`: an array of the following parameters, any of which may be omitted:
   * `enabled`: on/off flag for the removal script.
   * `source`: string key identifying the caller.
   * `context`: jQuery selector of the post, whole page is used by default or when it's empty.

Example:
```php
// Print removal script with specified context.
global $post;
$params = array(
	'enabled' => TRUE,
	'context' => '#post-123',
);
do_action( 'contextly_print_removal_script', $post, $params );
```

### `contextly_removal_script_options`

The filter allows you to adjust behavior of the `contextly_print_removal_script` action.

Arguments:
 * `$params`: the following is passed in usually:
   * `enabled`: on/off flag. Set to `FALSE` and nothing will be printed.
   * `context`: jQuery selector of the post.
 * `$post`: the post object.

Example:
```php
add_filter( 'contextly_removal_script_options', 'my_plugin_removal_script_options', 10, 2 );

function my_plugin_removal_script_options( $params, $post )
{
	// Force different context.
	$params['context'] = '#custom-post-' . $post->ID;

	return $params;
}
```

### `contextly_post_metadata`

Allows you to retrieve post metadata suitable for JS calls or metatag content. You may also attach a filter with priority >10 to this hook and adjust metadata of the post that is passed to Contextly. You usually don't have to use it directly, take a look at `contextly_print_metatags` and `contextly_print_launch_script` for simple use cases.

Arguments:

 * `$metadata`: the metadata array that is filled in by default handler.
 * `$post`: the post object.

Example 1:
```php
// Retrieve metadata array.
$metadata = apply_filters( 'contextly_post_metadata', array(), $post );
```

Example 2:
```php
// Adjust metadata with a custom handler.
add_filter( 'contextly_post_metadata', 'my_plugin_post_metadata_mod', 15, 2 );
function my_plugin_post_metadata_mod($metadata, $post)
{
	// In case it's empty, we don't want to adjust anything, as it would result
	// in metadata printed where it shouldn't be.
	if (!empty($metadata)) {
		$metadata['title'] = 'My custom title of post #' . $post->ID;
	}

	return $metadata;
}
```

### `contextly_post_js_data`

Allows you to retrieve some post-specific data suitable for `Contextly.WPSettings.setPostData()` JS call. Usually you don't have to use it directly, take a look at `contextly_print_launch_script` for simple use cases.

Arguments:

 * `$post_data`: the array that is filled in by default handler.
 * `$post`: the post object.

Example:
```php
// Retrieve post JS array.
$post_data = apply_filters( 'contextly_post_js_data', array(), $post );
```



