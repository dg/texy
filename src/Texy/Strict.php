<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;


/**
 * Better OOP experience.
 */
trait Strict
{
	/**
	 * Call to undefined method.
	 * @throws \LogicException
	 */
	public function __call($name, $args)
	{
		$class = method_exists($this, $name) ? 'parent' : static::class;
		$items = (new ReflectionClass($this))->getMethods(ReflectionMethod::IS_PUBLIC);
		$hint = ($t = self::getSuggestion($items, $name))
			? ", did you mean $t()?"
			: '.';
		throw new \LogicException("Call to undefined method $class::$name()$hint");
	}


	/**
	 * Call to undefined static method.
	 * @throws \LogicException
	 */
	public static function __callStatic($name, $args)
	{
		$rc = new ReflectionClass(static::class);
		$items = array_filter($rc->getMethods(\ReflectionMethod::IS_STATIC), fn($m) => $m->isPublic());
		$hint = ($t = self::getSuggestion($items, $name))
			? ", did you mean $t()?"
			: '.';
		throw new \LogicException("Call to undefined static method {$rc->getName()}::$name()$hint");
	}


	/**
	 * Access to undeclared property.
	 * @throws \LogicException
	 */
	public function &__get($name)
	{
		if (method_exists($this, $m = 'get' . $name) && (new ReflectionMethod($this, $m))->isPublic()) {
			$ret = $this->$m();
			return $ret;
		}

		$rc = new ReflectionClass($this);
		$items = array_filter($rc->getProperties(ReflectionProperty::IS_PUBLIC), fn($p) => !$p->isStatic());
		$hint = ($t = self::getSuggestion($items, $name))
			? ", did you mean $$t?"
			: '.';
		throw new \LogicException("Attempt to read undeclared property {$rc->getName()}::$$name$hint");
	}


	/**
	 * Access to undeclared property.
	 * @throws \LogicException
	 */
	public function __set($name, $value)
	{
		$rc = new ReflectionClass($this);
		$items = array_filter($rc->getProperties(ReflectionProperty::IS_PUBLIC), fn($p) => !$p->isStatic());
		$hint = ($t = self::getSuggestion($items, $name))
			? ", did you mean $$t?"
			: '.';
		throw new \LogicException("Attempt to write to undeclared property {$rc->getName()}::$$name$hint");
	}


	public function __isset($name): bool
	{
		return false;
	}


	/**
	 * Access to undeclared property.
	 * @throws \LogicException
	 */
	public function __unset($name)
	{
		$class = static::class;
		throw new \LogicException("Attempt to unset undeclared property $class::$$name.");
	}


	/**
	 * Finds the best suggestion.
	 */
	private static function getSuggestion(array $items, $value): ?string
	{
		$best = null;
		$min = (strlen($value) / 4 + 1) * 10 + .1;
		foreach (array_unique($items, SORT_REGULAR) as $item) {
			$item = is_object($item) ? $item->getName() : $item;
			if (($len = levenshtein($item, $value, 10, 11, 10)) > 0 && $len < $min) {
				$min = $len;
				$best = $item;
			}
		}

		return $best;
	}
}
