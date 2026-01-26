<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;

use function array_unshift, count, is_string;


/**
 * Implements chain of responsibility for element handlers.
 */
final class HandlerInvocation
{
	private int $pos;

	/** @var mixed[] */
	private array $args;


	/** @param mixed[] $args */
	public function __construct(
		/** @var list<\Closure(mixed...): mixed> */
		private array $handlers,
		private readonly Parser $parser,
		array $args,
	) {
		$this->pos = count($this->handlers);
		array_unshift($args, $this);
		$this->args = $args;
	}


	/**
	 * Invokes next handler in chain, or throws if none remain.
	 */
	public function proceed(mixed ...$args): string|HtmlElement|null
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
			throw new \UnexpectedValueException('Invalid value returned from handler.');
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
