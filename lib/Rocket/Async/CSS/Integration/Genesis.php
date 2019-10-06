<?php


namespace Rocket\Async\CSS\Integration;

use ComposePress\Core\Abstracts\Component;

class Genesis extends Component {
	public function init() {
		add_action( 'init', [ $this, 'is_theme_active' ] );
	}

	public function is_theme_active() {
		if ( function_exists( 'genesis' ) ) {
			$locations = array_map( 'sanitize_key', array_keys( get_registered_nav_menus() ) );
			foreach ( $locations as $location ) {
				add_filter( "genesis_attr_nav-{$location}", [ $this, 'modify_nav_class' ] );
				add_filter( "genesis_attr_nav-{$location}-toggle", [ $this, 'modify_hamburger_button_attr' ] );
				add_filter( 'genesis_do_nav', [ $this, 'add_hamburger_button' ], 10, 3 );
			}
			add_action( 'wp_enqueue_scripts', [ $this, 'assets' ], 11 );
		}
	}

	public function modify_hamburger_button_attr( $attributes ) {

		$attributes['class'] = $this->add_classes( $attributes['class'], [
			'menu-toggle',
			'dashicons-before',
			'dashicons-menu'
		] );
		$attributes['id']    = 'genesis-mobile-nav-primary';
		return $attributes;
	}

	private function add_classes( $classes, $class ) {
		$class   = (array) $class;
		$classes = explode( ' ', $classes );
		$classes = array_map( 'trim', $classes );
		$classes = array_filter( $classes );
		$classes = array_merge( $classes, $class );
		$classes = array_unique( $classes );
		$classes = implode( ' ', $classes );
		return $classes;
	}

	public function modify_nav_class( $attributes ) {
		$attributes['class'] = $attributes['class'] = $this->add_classes( $attributes['class'], 'genesis-responsive-menu' );
		return $attributes;
	}

	public function add_hamburger_button( $nav_output, $nav, $args ) {
		$nav_name         = "nav-{$args['theme_location']}";
		$hamburger_output = genesis_markup(
			[
				'open'    => '<button %s>',
				'close'   => '</button>',
				'context' => "{$nav_name}-toggle",
				'content' => apply_filters( 'rocket_async_css_genesis_mobile_menu_label', __( 'Menu', 'genesis' ), $args ),
				'echo'    => false,
			]
		);

		return $hamburger_output . $nav_output;
	}

	public function assets() {
		$css = '
		button.menu-toggle + button.menu-toggle {
			display:none;
		}';
		wp_add_inline_style( genesis_get_theme_handle(), $css );

		$theme = get_stylesheet();
		$theme = str_replace( '-pro', '', $theme );

		wp_add_inline_script( $theme . '-responsive-menu', '(function($) {
    $(function() {
        $("#genesis-mobile-nav-primary").remove()
    });
})(jQuery);', 'before' );
	}
}
