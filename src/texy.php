<?php

/**
 * Texy! is human-readable text to HTML converter (http://texy.info)
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
		'Texy\Modules\BlockModule' => 'modules/BlockModule.php',
		'Texy\Modules\BlockQuoteModule' => 'modules/BlockQuoteModule.php',
		'Texy\Modules\EmoticonModule' => 'modules/EmoticonModule.php',
		'Texy\Modules\FigureModule' => 'modules/FigureModule.php',
		'Texy\Modules\HeadingModule' => 'modules/HeadingModule.php',
		'Texy\Modules\HorizLineModule' => 'modules/HorizLineModule.php',
		'Texy\Modules\HtmlModule' => 'modules/HtmlModule.php',
		'Texy\Modules\HtmlOutputModule' => 'modules/HtmlOutputModule.php',
		'Texy\Modules\Image' => 'modules/Image.php',
		'Texy\Modules\ImageModule' => 'modules/ImageModule.php',
		'Texy\Modules\Link' => 'modules/Link.php',
		'Texy\Modules\LinkModule' => 'modules/LinkModule.php',
		'Texy\Modules\ListModule' => 'modules/ListModule.php',
		'Texy\Modules\LongWordsModule' => 'modules/LongWordsModule.php',
		'Texy\Modules\ParagraphModule' => 'modules/ParagraphModule.php',
		'Texy\Modules\PhraseModule' => 'modules/PhraseModule.php',
		'Texy\Modules\ScriptModule' => 'modules/ScriptModule.php',
		'Texy\Modules\TableCellElement' => 'modules/TableCellElement.php',
		'Texy\Modules\TableModule' => 'modules/TableModule.php',
		'Texy\Modules\TypographyModule' => 'modules/TypographyModule.php',
	], $old2new = [
		'Texy' => 'Texy\Texy',
		'TexyConfigurator' => 'Texy\Configurator',
		'TexyHtml' => 'Texy\HtmlElement',
		'TexyModifier' => 'Texy\Modifier',
		'TexyPatterns' => 'Texy\Patterns',
		'TexyHeadingModule' => 'Texy\Modules\HeadingModule',
		'TexyImage' => 'Texy\Modules\Image',
		'TexyLink' => 'Texy\Modules\Link',
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

require_once __DIR__ . '/compatibility.php';
