<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

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
		$class = method_exists($this, $name) ? 'parent' : get_class($this);
		$items = (new ReflectionClass($this))->getMethods(ReflectionMethod::IS_PUBLIC);
		$hint = ($t = self::getSuggestion($items, $name)) ? ", did you mean $t()?" : '.';
		throw new \LogicException("Call to undefined method $class::$name()$hint");
	}


	/**
	 * Call to undefined static method.
	 * @throws \LogicException
	 */
	public static function __callStatic($name, $args)
	{
		$rc = new ReflectionClass(get_called_class());
		$items = array_intersect($rc->getMethods(ReflectionMethod::IS_PUBLIC), $rc->getMethods(ReflectionMethod::IS_STATIC));
		$hint = ($t = self::getSuggestion($items, $name)) ? ", did you mean $t()?" : '.';
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
		$items = array_diff($rc->getProperties(ReflectionProperty::IS_PUBLIC), $rc->getProperties(ReflectionProperty::IS_STATIC));
		$hint = ($t = self::getSuggestion($items, $name)) ? ", did you mean $$t?" : '.';
		throw new \LogicException("Attempt to read undeclared property {$rc->getName()}::$$name$hint");
	}


	/**
	 * Access to undeclared property.
	 * @throws \LogicException
	 */
	public function __set($name, $value)
	{
		$rc = new ReflectionClass($this);
		$items = array_diff($rc->getProperties(ReflectionProperty::IS_PUBLIC), $rc->getProperties(ReflectionProperty::IS_STATIC));
		$hint = ($t = self::getSuggestion($items, $name)) ? ", did you mean $$t?" : '.';
		throw new \LogicException("Attempt to write to undeclared property {$rc->getName()}::$$name$hint");
	}


	/**
	 * @return bool
	 */
	public function __isset($name)
	{
		return false;
	}


	/**
	 * Access to undeclared property.
	 * @throws \LogicException
	 */
	public function __unset($name)
	{
		$class = get_class($this);
		throw new \LogicException("Attempt to unset undeclared property $class::$$name.");
	}


	/**
	 * Finds the best suggestion.
	 * @return string|null
	 */
	private static function getSuggestion(array $items, $value)
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
