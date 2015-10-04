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
		'TexyPatterns' => 'TexyPatterns.php',
		'TexyStrict' => 'TexyStrict.php',
		'TexyHtml' => 'TexyHtml.php',
		'TexyModifier' => 'TexyModifier.php',
		'TexyModule' => 'TexyModule.php',
		'TexyParser' => 'TexyParser.php',
		'TexyBlockParser' => 'TexyBlockParser.php',
		'TexyLineParser' => 'TexyLineParser.php',
		'TexyConfigurator' => 'TexyConfigurator.php',
		'TexyHandlerInvocation' => 'TexyHandlerInvocation.php',
		'TexyRegexp' => 'TexyRegexp.php',
		'Texy' => 'Texy.php',
		'TexyImage' => 'modules/TexyImage.php',
		'TexyLink' => 'modules/TexyLink.php',
		'TexyTableCellElement' => 'modules/TexyTableCellElement.php',
		'TexyParagraphModule' => 'modules/TexyParagraphModule.php',
		'TexyBlockModule' => 'modules/TexyBlockModule.php',
		'TexyHeadingModule' => 'modules/TexyHeadingModule.php',
		'TexyHorizLineModule' => 'modules/TexyHorizLineModule.php',
		'TexyHtmlModule' => 'modules/TexyHtmlModule.php',
		'TexyFigureModule' => 'modules/TexyFigureModule.php',
		'TexyImageModule' => 'modules/TexyImageModule.php',
		'TexyLinkModule' => 'modules/TexyLinkModule.php',
		'TexyListModule' => 'modules/TexyListModule.php',
		'TexyLongWordsModule' => 'modules/TexyLongWordsModule.php',
		'TexyPhraseModule' => 'modules/TexyPhraseModule.php',
		'TexyBlockQuoteModule' => 'modules/TexyBlockQuoteModule.php',
		'TexyScriptModule' => 'modules/TexyScriptModule.php',
		'TexyEmoticonModule' => 'modules/TexyEmoticonModule.php',
		'TexyTableModule' => 'modules/TexyTableModule.php',
		'TexyTypographyModule' => 'modules/TexyTypographyModule.php',
		'TexyHtmlOutputModule' => 'modules/TexyHtmlOutputModule.php',
	];
	if (isset($map[$class])) {
		require __DIR__ . '/Texy/' . $map[$class];
	}
});

require_once __DIR__ . '/compatibility.php';
