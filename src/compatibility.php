<?php

spl_autoload_register(function ($class) {
	static $map = [
		'TexyPatterns' => 'Texy\Patterns',
		'TexyStrict' => 'Texy\Strict',
		'TexyHtml' => 'Texy\HtmlElement',
		'TexyModifier' => 'Texy\Modifier',
		'TexyModule' => 'Texy\Module',
		'TexyParser' => 'Texy\Parser',
		'TexyBlockParser' => 'Texy\BlockParser',
		'TexyLineParser' => 'Texy\LineParser',
		'TexyConfigurator' => 'Texy\Configurator',
		'TexyHandlerInvocation' => 'Texy\HandlerInvocation',
		'TexyRegexp' => 'Texy\Regexp',
		'Texy' => 'Texy\Texy',
		'TexyImage' => 'Texy\Modules\Image',
		'TexyLink' => 'Texy\Modules\Link',
		'TexyTableCellElement' => 'Texy\Modules\TableCellElement',
		'TexyParagraphModule' => 'Texy\Modules\ParagraphModule',
		'TexyBlockModule' => 'Texy\Modules\BlockModule',
		'TexyHeadingModule' => 'Texy\Modules\HeadingModule',
		'TexyHorizLineModule' => 'Texy\Modules\HorizLineModule',
		'TexyHtmlModule' => 'Texy\Modules\HtmlModule',
		'TexyFigureModule' => 'Texy\Modules\FigureModule',
		'TexyImageModule' => 'Texy\Modules\ImageModule',
		'TexyLinkModule' => 'Texy\Modules\LinkModule',
		'TexyListModule' => 'Texy\Modules\ListModule',
		'TexyLongWordsModule' => 'Texy\Modules\LongWordsModule',
		'TexyPhraseModule' => 'Texy\Modules\PhraseModule',
		'TexyBlockQuoteModule' => 'Texy\Modules\BlockQuoteModule',
		'TexyScriptModule' => 'Texy\Modules\ScriptModule',
		'TexyEmoticonModule' => 'Texy\Modules\EmoticonModule',
		'TexyTableModule' => 'Texy\Modules\TableModule',
		'TexyTypographyModule' => 'Texy\Modules\TypographyModule',
		'TexyHtmlOutputModule' => 'Texy\Modules\HtmlOutputModule',
	];
	if (isset($map[$class])) {
		class_alias($map[$class], $class);
	}
});
