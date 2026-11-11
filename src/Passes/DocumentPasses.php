<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Passes;

use Texy\{Config, HtmlPolicy, Stage};
use Texy\Parsing\Engine;


/**
 * The passes of a document in any language, registered in one place so that their order cannot
 * differ between languages. They go after the passes finishing the meaning of the language.
 */
final class DocumentPasses
{
	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	public static function register(Engine $engine, Config $config, HtmlPolicy $htmlPolicy): void
	{
		$engine->addPass((new HtmlPairingPass)->process(...));
		$engine->addPass(new HtmlSanitizePass($htmlPolicy)->process(...));
		$engine->addPass(new HeadingPass($config)->process(...));
		$engine->addPass(new TextRunPass($config)->process(...), Stage::Finalize);
	}
}
