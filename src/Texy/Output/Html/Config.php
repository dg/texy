<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;


/**
 * Configuration of the HTML output: formatting options and user node
 * handlers, exposed as $texy->htmlOutput. Consumed by the per-render
 * Renderer and the WellFormer engine.
 */
class Config
{
	/** indent HTML code? */
	public bool $indent = true;

	/** @var string[]  tags whose content keeps its whitespace verbatim */
	public array $preserveSpaces = ['textarea', 'pre', 'script', 'code', 'samp', 'kbd'];

	/** base indent level */
	public int $baseIndent = 0;

	/** wrap width, doesn't include indent space */
	public int $lineWrap = 80;

	/** @var list<\Closure>  user node handlers (see registerHandler) */
	private array $userHandlers = [];


	/**
	 * Register a handler for a node class (determined by the type of its
	 * first parameter). Return null to delegate to the previous handler.
	 */
	public function registerHandler(\Closure $handler): void
	{
		$this->userHandlers[] = $handler;
	}


	/**
	 * @internal
	 * @return list<\Closure>
	 */
	public function getHandlers(): array
	{
		return $this->userHandlers;
	}
}
