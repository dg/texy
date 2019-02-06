<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Around advice handlers.
 */
final class HandlerInvocation
{
	use Strict;

	/** @var array of callbacks */
	private $handlers;

	/** @var int  callback counter */
	private $pos;

	/** @var array */
	private $args;

	/** @var Parser */
	private $parser;


	public function __construct(array $handlers, Parser $parser, array $args)
	{
		$this->handlers = $handlers;
		$this->pos = count($handlers);
		$this->parser = $parser;
		array_unshift($args, $this);
		$this->args = $args;
	}


	/**
	 * @return mixed
	 */
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
		if ($res === false) {
			trigger_error('Return type of Texy handlers was changed from FALSE to NULL. (' . get_class($this->handlers[$this->pos][0]) . ')', E_USER_DEPRECATED);
			return;
		} elseif ($res !== null && !is_string($res) && !$res instanceof HtmlElement) {
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
