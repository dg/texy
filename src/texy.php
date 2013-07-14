<?php

/**
 * Texy! is human-readable text to HTML converter (http://texy.info)
 *
 * Copyright (c) 2004, 2014 David Grudl (https://davidgrudl.com)
 */


if (PHP_VERSION_ID < 50404) {
	throw new Exception('Texy requires PHP 5.4.4 or newer.');
}

// load libraries
spl_autoload_register(function ($class) {
	static $map = [
		'Texy\Patterns' => 'Patterns.php',
		'Texy\Strict' => 'Strict.php',
		'Texy\HtmlElement' => 'HtmlElement.php',
		'Texy\Modifier' => 'Modifier.php',
		'Texy\Module' => 'Module.php',
		'Texy\Parser' => 'Parser.php',
		'Texy\BlockParser' => 'BlockParser.php',
		'Texy\LineParser' => 'LineParser.php',
		'Texy\Configurator' => 'Configurator.php',
		'Texy\HandlerInvocation' => 'HandlerInvocation.php',
		'Texy\Regexp' => 'Regexp.php',
		'Texy\Texy' => 'Texy.php',
		'Texy\Modules\Image' => 'modules/Image.php',
		'Texy\Modules\Link' => 'modules/Link.php',
		'Texy\Modules\TableCellElement' => 'modules/TableCellElement.php',
		'Texy\Modules\ParagraphModule' => 'modules/ParagraphModule.php',
		'Texy\Modules\BlockModule' => 'modules/BlockModule.php',
		'Texy\Modules\HeadingModule' => 'modules/HeadingModule.php',
		'Texy\Modules\HorizLineModule' => 'modules/HorizLineModule.php',
		'Texy\Modules\HtmlModule' => 'modules/HtmlModule.php',
		'Texy\Modules\FigureModule' => 'modules/FigureModule.php',
		'Texy\Modules\ImageModule' => 'modules/ImageModule.php',
		'Texy\Modules\LinkModule' => 'modules/LinkModule.php',
		'Texy\Modules\ListModule' => 'modules/ListModule.php',
		'Texy\Modules\LongWordsModule' => 'modules/LongWordsModule.php',
		'Texy\Modules\PhraseModule' => 'modules/PhraseModule.php',
		'Texy\Modules\BlockQuoteModule' => 'modules/BlockQuoteModule.php',
		'Texy\Modules\ScriptModule' => 'modules/ScriptModule.php',
		'Texy\Modules\EmoticonModule' => 'modules/EmoticonModule.php',
		'Texy\Modules\TableModule' => 'modules/TableModule.php',
		'Texy\Modules\TypographyModule' => 'modules/TypographyModule.php',
		'Texy\Modules\HtmlOutputModule' => 'modules/HtmlOutputModule.php',
	];
	if (isset($map[$class])) {
		require __DIR__ . '/Texy/' . $map[$class];
	}
});

require_once __DIR__ . '/compatibility.php';
