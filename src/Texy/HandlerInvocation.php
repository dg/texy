<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

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
	 * @param  mixed
	 * @return mixed
	 */
	public function proceed()
	{
		if ($this->pos === 0) {
			throw new \RuntimeException('No more handlers.');
		}

		if (func_num_args()) {
			$this->args = func_get_args();
			array_unshift($this->args, $this);
		}

		$this->pos--;
		$res = call_user_func_array($this->handlers[$this->pos], $this->args);
		if ($res === null) {
			throw new \UnexpectedValueException("Invalid value returned from handler '" . print_r($this->handlers[$this->pos], true) . "'.");
		}
		return $res;
	}


	/**
	 * @return Parser
	 */
	public function getParser()
	{
		return $this->parser;
	}


	/**
	 * @return Texy
	 */
	public function getTexy()
	{
		return $this->parser->getTexy();
	}


	/** @deprecated */
	public function free()
	{
	}
}
