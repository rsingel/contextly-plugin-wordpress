<?php

/**
 * Adds Siderail widget.
 */
class ContextlyWpSocialWidget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'contextly_social_widget', // Base ID
			__( 'Contextly Social', 'contextly_linker_textdomain' ), // Name
			array( 'description' => __( 'Displays tweets recommended by Contextly.', 'contextly_linker_textdomain' ), ) // Args
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

		$classes = Contextly::WIDGET_SOCIAL_CLASS . ' ' . Contextly::WIDGET_PLACEMENT_CLASS;

		print $args['before_widget'];
		print '<div class="' . esc_attr( $classes ) . '"></div>';
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
			Options for the Social widget are in the Contextly Control Panel.
		</p>
		<?php
	}

} // class ContextlyWpSiderailWidget
