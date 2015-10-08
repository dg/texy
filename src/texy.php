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
		'Texy' => 'Texy.php',
		'TexyBlockParser' => 'TexyBlockParser.php',
		'TexyConfigurator' => 'TexyConfigurator.php',
		'TexyHandlerInvocation' => 'TexyHandlerInvocation.php',
		'TexyHelpers' => 'TexyHelpers.php',
		'TexyHtml' => 'TexyHtml.php',
		'TexyLineParser' => 'TexyLineParser.php',
		'TexyModifier' => 'TexyModifier.php',
		'TexyModule' => 'TexyModule.php',
		'TexyParser' => 'TexyParser.php',
		'TexyPatterns' => 'TexyPatterns.php',
		'TexyRegexp' => 'TexyRegexp.php',
		'TexyStrict' => 'TexyStrict.php',
		'TexyUtf' => 'TexyUtf.php',
		'TexyBlockModule' => 'modules/TexyBlockModule.php',
		'TexyBlockQuoteModule' => 'modules/TexyBlockQuoteModule.php',
		'TexyEmoticonModule' => 'modules/TexyEmoticonModule.php',
		'TexyFigureModule' => 'modules/TexyFigureModule.php',
		'TexyHeadingModule' => 'modules/TexyHeadingModule.php',
		'TexyHorizLineModule' => 'modules/TexyHorizLineModule.php',
		'TexyHtmlModule' => 'modules/TexyHtmlModule.php',
		'TexyHtmlOutputModule' => 'modules/TexyHtmlOutputModule.php',
		'TexyImage' => 'modules/TexyImage.php',
		'TexyImageModule' => 'modules/TexyImageModule.php',
		'TexyLink' => 'modules/TexyLink.php',
		'TexyLinkModule' => 'modules/TexyLinkModule.php',
		'TexyListModule' => 'modules/TexyListModule.php',
		'TexyLongWordsModule' => 'modules/TexyLongWordsModule.php',
		'TexyParagraphModule' => 'modules/TexyParagraphModule.php',
		'TexyPhraseModule' => 'modules/TexyPhraseModule.php',
		'TexyScriptModule' => 'modules/TexyScriptModule.php',
		'TexyTableCellElement' => 'modules/TexyTableCellElement.php',
		'TexyTableModule' => 'modules/TexyTableModule.php',
		'TexyTypographyModule' => 'modules/TexyTypographyModule.php',
	];
	if (isset($map[$class])) {
		require __DIR__ . '/Texy/' . $map[$class];
	}
});

require_once __DIR__ . '/compatibility.php';
