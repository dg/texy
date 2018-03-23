<?php

/**
 * Texy! is human-readable text to HTML converter (https://texy.info)
 *
 * Copyright (c) 2004, 2014 David Grudl (https://davidgrudl.com)
 */


if (PHP_VERSION_ID < 50404) {
	throw new Exception('Texy requires PHP 5.4.4 or newer.');
}

spl_autoload_register(function ($class) {
	static $old2new = [
		'Texy' => 'Texy\Texy',
		'TexyConfigurator' => 'Texy\Configurator',
		'TexyHtml' => 'Texy\HtmlElement',
		'TexyModifier' => 'Texy\Modifier',
		'TexyPatterns' => 'Texy\Patterns',
		'TexyHeadingModule' => 'Texy\Modules\HeadingModule',
		'TexyImage' => 'Texy\Image',
		'TexyLink' => 'Texy\Link',
		'TexyLinkModule' => 'Texy\Modules\LinkModule',
		'TexyLongWordsModule' => 'Texy\Modules\LongWordsModule',
		'TexyTypographyModule' => 'Texy\Modules\TypographyModule',
	];
	if (isset($old2new[$class])) {
		class_alias($old2new[$class], $class);
	}
});


// preload for compatiblity
array_map('class_exists', [
	'TexyHtml',
	'TexyImage',
	'TexyLink',
]);
