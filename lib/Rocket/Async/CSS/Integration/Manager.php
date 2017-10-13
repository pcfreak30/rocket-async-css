<?php

namespace Rocket\Async\CSS\Integration;


use pcfreak30\WordPress\Plugin\Framework\ManagerAbstract;

class Manager extends ManagerAbstract {
	protected $modules = [
		'Amp',
		'RevolutionSlider',
		'ThePreloader',
		'LayerSlider',
		'JuipterTheme',
		'MetaSlider',
		'ResponsiveImages',
		'WPCriticalCSS',
		'AvadaTheme',
		'GoogleWebFonts',
		'Woocommerce',
	];

}