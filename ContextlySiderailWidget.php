<?php

/**
 * Adds Siderail widget.
 */
class ContextlyWpSiderailWidget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'contextly_siderail_widget', // Base ID
			__( 'Contextly Siderail', 'contextly_linker_textdomain' ), // Name
			array( 'description' => __( 'Displays links recommended by Contextly.', 'contextly_linker_textdomain' ), ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		if ( !$GLOBALS['contextly']->isLoadWidget() )
		{
			return;
		}

		print $args['before_widget'];
		print '<div class="ctx-siderail-container"></div>';
		print $args['after_widget'];
	}

	/**
	 * Admin form for display widget form.
	 *
	 * @param array $instance
	 * @return void
	 */
	function form( $instance ) {
		?>
		<p>
			Options for the Siderail are in the Contextly Control Panel.
		</p>
		<?php
	}

} // class ContextlyWpSiderailWidget
