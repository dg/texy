<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Compat;


/**
 * Stands in for a module of Texy 3 in $texy->someModule.
 * @internal
 */
final class ModuleProxy
{
	public function __construct(
		private Texy3 $compat,
		private string $module,
	) {
	}


	public function &__get(string $name): mixed
	{
		return $this->compat->getModuleProperty($this->module, $name);
	}


	public function __set(string $name, mixed $value): void
	{
		$this->compat->setModuleProperty($this->module, $name, $value);
	}


	public function __isset(string $name): bool
	{
		return $this->compat->hasModuleProperty($this->module, $name);
	}


	/** @param  array<mixed>  $args */
	public function __call(string $name, array $args): never
	{
		$this->compat->callModuleMethod($this->module, $name);
	}
}
