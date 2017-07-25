<?php

/* @var $container \Dice\Dice */

$container->addRule( '\\Rocket\\Async\\CSS', [
	'shared' => true,
] );
$container->addRule( '\\Rocket\\Async\\CSS\\DOMDocument', [
	'call' => [
		[ 'registerNodeClass', [ 'DOMElement', '\\Rocket\\Async\\CSS\\DOMElement' ] ],
	],
] );