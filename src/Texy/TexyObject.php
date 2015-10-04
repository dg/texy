<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */


/**
 * Object is the ultimate ancestor of all instantiable classes.
 */
abstract class TexyObject
{

	/**
	 * Call to undefined method.
	 * @throws LogicException
	 */
	public function __call($name, $args)
	{
		$class = method_exists($this, $name) ? 'parent' : get_class($this);
		throw new LogicException("Call to undefined method $class::$name().");
	}


	/**
	 * Call to undefined static method.
	 * @throws LogicException
	 */
	public static function __callStatic($name, $args)
	{
		$class = get_called_class();
		throw new LogicException("Call to undefined static method $class::$name().");
	}


	/**
	 * Access to undeclared property.
	 * @throws LogicException
	 */
	public function &__get($name)
	{
		if (method_exists($this, $m = 'get' . $name) && (new ReflectionMethod($this, $m))->isPublic()) {
			$ret = $this->$m();
			return $ret;
		}
		$class = get_class($this);
		throw new LogicException("Attempt to read undeclared property $class::\$$name.");
	}


	/**
	 * Access to undeclared property.
	 * @throws LogicException
	 */
	public function __set($name, $value)
	{
		$class = get_class($this);
		throw new LogicException("Attempt to write to undeclared property $class::\$$name.");
	}


	/**
	 * @return bool
	 */
	public function __isset($name)
	{
		return FALSE;
	}


	/**
	 * Access to undeclared property.
	 * @throws LogicException
	 */
	public function __unset($name)
	{
		$class = get_class($this);
		throw new LogicException("Attempt to unset undeclared property $class::\$$name.");
	}

}
