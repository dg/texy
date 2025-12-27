<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function array_unshift, count, is_string;


/**
 * Around advice handlers.
 */
final class HandlerInvocation
{
	private int $pos;

	/** @var mixed[] */
	private array $args;


	public function __construct(
		/** @var array<int, callable> */
		private array $handlers,
		private readonly Parser $parser,
		array $args,
	) {
		$this->pos = count($this->handlers);
		array_unshift($args, $this);
		$this->args = $args;
	}


	/** @return mixed */
	public function proceed(...$args)
	{
		if ($this->pos === 0) {
			throw new \RuntimeException('No more handlers.');
		}

		if ($args) {
			$this->args = $args;
			array_unshift($this->args, $this);
		}

		$this->pos--;
		$res = $this->handlers[$this->pos](...$this->args);
		if ($res !== null && !is_string($res) && !$res instanceof HtmlElement) {
			throw new \UnexpectedValueException("Invalid value returned from handler '" . get_class($this->handlers[$this->pos][0]) . "'.");
		}

		return $res;
	}


	public function getParser(): Parser
	{
		return $this->parser;
	}


	public function getTexy(): Texy
	{
		return $this->parser->getTexy();
	}
}
