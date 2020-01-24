<?php


namespace Rocket\Async\CSS\Integration;


use ComposePress\Core\Abstracts\Component;

/**
 * Class VisualComposer
 * @package Rocket\Async\CSS\Integration
 */
class VisualComposer extends Component {
	/**
	 * @var \Vc_Grid_Item
	 */
	private $vc_grid_item;

	/**
	 *
	 */
	public function init() {
		if ( class_exists( '\Vc_Manager' ) ) {
			require_once vc_path_dir( 'PARAMS_DIR', 'vc_grid_item/class-vc-grid-item.php' );
			$this->vc_grid_item = new \Vc_Grid_Item();
			add_filter( 'do_shortcode_tag', [ $this, 'add_remove_image_shortcode_filter' ], 10, 2 );
		}
	}

	/**
	 * @param $content
	 * @param $tag
	 *
	 * @return mixed
	 */
	public function add_remove_image_shortcode_filter( $content, $tag ) {
		if ( 0 === strpos( $tag, 'vc_' ) ) {
			$module = $this->parent->get_module( 'ResponsiveImages' );
			if ( $module ) {
				if ( ! preg_match( $this->vc_grid_item->templateVariablesRegex(), $content ) ) {
					add_filter( 'do_shortcode_tag', [ $module, 'process' ], 9999 );
					add_filter( 'do_shortcode_tag', [ $module, 'process' ], 10001 );
				} else {
					remove_filter( 'do_shortcode_tag', [ $module, 'process' ], 9999 );
					remove_filter( 'do_shortcode_tag', [ $module, 'process' ], 10001 );
				}
			}
		}

		return $content;
	}
}
