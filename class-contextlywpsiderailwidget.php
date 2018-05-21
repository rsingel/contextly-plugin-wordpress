<?php
/**
 * Siderail widget.
 *
 * @package Contextly Related Links
 * @link https://contextly.com
 */

/**
 * Adds Siderail widget.
 */
class ContextlyWpSiderailWidget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'contextly_siderail_widget',
			__( 'Contextly Siderail', 'contextly_linker_textdomain' ),
			array( 'description' => __( 'Displays links recommended by Contextly.', 'contextly_linker_textdomain' ) )
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
		if ( ! $GLOBALS['contextly']->is_load_widget() ) {
			return;
		}

		$classes = array(
			Contextly::WIDGET_SIDERAIL_CLASS,
			Contextly::WIDGET_PLACEMENT_CLASS,
			Contextly::CLEARFIX_CLASS,
		);

		echo $args['before_widget']; // WPCS: XSS ok.
		printf( '<div class="%s"></div>', esc_attr( Contextly::join_classes( $classes ) ) );
		echo $args['after_widget']; // WPCS: XSS ok.
	}

	/**
	 * Admin form for display widget form.
	 *
	 * @param array $instance widget instance.
	 * @return void
	 */
	public function form( $instance ) {
		?>
		<p>
			Options for the Siderail are in the Contextly Control Panel.
		</p>
		<?php
	}

} // class ContextlyWpSiderailWidget
