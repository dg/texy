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
	static $map = [
		'Texy\Texy' => 'Texy.php',
		'Texy\BlockParser' => 'BlockParser.php',
		'Texy\Configurator' => 'Configurator.php',
		'Texy\HandlerInvocation' => 'HandlerInvocation.php',
		'Texy\Helpers' => 'Helpers.php',
		'Texy\HtmlElement' => 'HtmlElement.php',
		'Texy\LineParser' => 'LineParser.php',
		'Texy\Modifier' => 'Modifier.php',
		'Texy\Module' => 'Module.php',
		'Texy\Parser' => 'Parser.php',
		'Texy\Patterns' => 'Patterns.php',
		'Texy\Regexp' => 'Regexp.php',
		'Texy\Strict' => 'Strict.php',
		'Texy\Utf' => 'Utf.php',
		'Texy\Modules\BlockModule' => 'Modules/BlockModule.php',
		'Texy\Modules\BlockQuoteModule' => 'Modules/BlockQuoteModule.php',
		'Texy\Modules\EmoticonModule' => 'Modules/EmoticonModule.php',
		'Texy\Modules\FigureModule' => 'Modules/FigureModule.php',
		'Texy\Modules\HeadingModule' => 'Modules/HeadingModule.php',
		'Texy\Modules\HorizLineModule' => 'Modules/HorizLineModule.php',
		'Texy\Modules\HtmlModule' => 'Modules/HtmlModule.php',
		'Texy\Modules\HtmlOutputModule' => 'Modules/HtmlOutputModule.php',
		'Texy\Image' => 'Image.php',
		'Texy\Modules\ImageModule' => 'Modules/ImageModule.php',
		'Texy\Link' => 'Link.php',
		'Texy\Modules\LinkModule' => 'Modules/LinkModule.php',
		'Texy\Modules\ListModule' => 'Modules/ListModule.php',
		'Texy\Modules\LongWordsModule' => 'Modules/LongWordsModule.php',
		'Texy\Modules\ParagraphModule' => 'Modules/ParagraphModule.php',
		'Texy\Modules\PhraseModule' => 'Modules/PhraseModule.php',
		'Texy\Modules\ScriptModule' => 'Modules/ScriptModule.php',
		'Texy\Modules\TableCellElement' => 'Modules/TableCellElement.php',
		'Texy\Modules\TableModule' => 'Modules/TableModule.php',
		'Texy\Modules\TypographyModule' => 'Modules/TypographyModule.php',
	], $old2new = [
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
	if (isset($map[$class])) {
		require __DIR__ . '/Texy/' . $map[$class];
	} elseif (isset($old2new[$class])) {
		class_alias($old2new[$class], $class);
	}
});


// preload for compatiblity
array_map('class_exists', [
	'TexyHtml',
	'TexyImage',
	'TexyLink',
]);

require_once __DIR__ . '/compatibility.php';
