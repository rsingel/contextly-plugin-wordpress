# Contextly plugin integration

You don't need any custom code for the most simple use case, it's usually enough to activate the plugin and set up an API key. But some special cases may require additional work. Such cases include:
 * Infinite scroll. Your website loads full post on scroll and you want Contextly modules to appear on that post as well.
 * Dynamic navigation. Internal links on your website are actually handled by AJAX requests and the page content is replaced on link click without actual navigation happening.

For simple customization, e.g. changing priority of the Contextly scripts or moving them from header to the footer, it may also worth checking the [Constants](constants.md) documentation page.

[Reference of actions and filters](hooks.md) provides you a more detailed information about all the hooks mentioned in this document.

## Basic requirements

In order for Contextly plugin to function properly there are 3 components that must be presented on the page. All these are added by default behavior of the Contextly plugin, so they are descrbed here just so that you understand how it works and be able to apply that to your special case.

### 1. Post metadata in the page header.

It is required for 2 reasons:

 * Our indexing bot reads it.
 * In case the metadata is not explicitly provided to `Contextly.ready(..)` JS call, it is extracted from the page header also.

Relevant [actions and filters](hooks.md):

 * `contextly_print_metatags`
 * `contextly_post_metatag_options`
 * `contextly_post_metadata`

### 2. Init script.

The scripts sets up `Contextly` global and asynchronous `Contextly.ready()` function. It must be presented on the page only once before any launch scripts.

Relevant [actions and filters](hooks.md):

 * `contextly_print_init_script`
 * `contextly_init_script_options`

### 3. Launch script for every post.

The script starts a request to the Contextly servers to retrieve all the data and start displaying the modules as soon as the data arrives. By default the metadata is extracted from the page header and modules are rendered on the whole page. Any of launch scripts must be executed after jQuery has been loaded.

Relevant [actions and filters](hooks.md):

 * `contextly_print_launch_script`
 * `contextly_launch_script_options`

## Infinite scroll.

For every AJAX-loaded post you have to generate and run the launch script of that post after the post has been added to the page DOM.

Usually, the only thing necessary is to append the launch script on AJAX requests loading the post (you may want to use [output buffering](http://php.net/manual/en/book.outcontrol.php) to get it as a string):

```php
global $post;
$params = array(
	'enabled' => TRUE,
	'metadata' => TRUE,
	'context' => '#post-' . $post->ID,
);
do_action( 'contextly_print_launch_script', $post, $params );
```

Relevant [actions and filters](hooks.md):

 * `contextly_print_launch_script`

## Dynamic navigation.

Since, visitor may get to the post page from any different kind of page, you may want to include the init script to all the pages on your website with pre-loading disabled and execute launch script only when visitor gets to the post page.

First, it would be nice to cancel the default init script:
```php
add_filter( 'contextly_init_script_options', 'my_plugin_init_script_options' );

function my_plugin_init_script_options( &$params )
{
	// Modify default call behavior only.
    if (!empty($params['source']) && $params['source'] === 'contextly-linker') {
		// Cancel on non-admin pages only.
		if (empty($params['editor'])) {
    		$params['enabled'] = FALSE;
	    }
    }

    return $params;
}

```

Put something like that into your header template for all the non-admin pages:
```php
$params = array(
	'enabled' => TRUE,
	'preload' => FALSE,
);
do_action( 'contextly_print_init_script', $params );
```

And append the launch script on AJAX requests loading the post (you may want to use [output buffering](http://php.net/manual/en/book.outcontrol.php) to get it as a string):
```php
global $post;
$params = array(
	'enabled' => TRUE,
	'metadata' => TRUE,
	'context' => '#post-' . $post->ID,
);
do_action( 'contextly_print_launch_script', $post, $params );
```

Before the visitor navigates from the post page, it would be nice to remove the Contextly modules and cleanup the memory. You need removal script for that. It's convenient to deliver it with the post itself, but without executing it until the visitor navigates from the post page.

```php
global $post;
$params = array(
	'enabled' => TRUE,
	'context' => '#post-' . $post->ID,
);
ob_start();
do_action( 'contextly_print_removal_script', $post, $params );
$removal_script = ob_get_clean();
```

Then transfer `$removal_script` contents to the client side and execute it right before the visitor navigates from the post page.

Relevant [actions and filters](hooks.md):

 * `contextly_print_init_script`
 * `contextly_init_script_options`
 * `contextly_print_launch_script`
 * `contextly_launch_script_options`
 * `contextly_print_removal_script`
 * `contextly_removal_script_options`
