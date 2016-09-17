# WordPress config constants

It is possible to customize behavior of the Contextly plugin through setting constants in `wp-config.php` above the following line:

```php
// Put constants ^^^ABOVE^^^ the last line:
require_once(ABSPATH . 'wp-settings.php');
```

### `CONTEXTLY_HEAD_SCRIPT_ACTION`

Default value: `wp_head`. The WordPress action that triggers printing of the default init script. May be also set to `FALSE` to turn it off.

Example:

```php
// Move init script to the footer.
define( 'CONTEXTLY_HEAD_SCRIPT_ACTION', 'wp_footer' );
```

### `CONTEXTLY_HEAD_SCRIPT_WEIGHT`

Default value: `10`. Weight of the handler printing the init script.

Example:
```php
// Output init script earlier.
define( 'CONTEXTLY_HEAD_SCRIPT_WEIGHT', -5 );
```

### `CONTEXTLY_FOOTER_SCRIPT_ACTION`

Default value: `wp_footer`. The WordPress action that triggers printing of the default launch script. May be also set to `FALSE` to turn it off.

Example:

```php
// Print launch script on a custom action.
define( 'CONTEXTLY_FOOTER_SCRIPT_ACTION', 'my_plugin_action' );
```

### `CONTEXTLY_FOOTER_SCRIPT_WEIGHT`

Default value: `0`. Weight of the handler printing the launch script.

Example:
```php
// Output launch script later.
define( 'CONTEXTLY_FOOTER_SCRIPT_WEIGHT', 99 );
```
