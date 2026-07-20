<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Compat;

use Texy\Texy;


/**
 * Stands in for a v3 module that no longer exists, so that $texy->figureModule->class
 * and friends keep configuring their new home. Created lazily by Texy::__get().
 * @internal
 */
final class LegacyModuleProxy
{
	public function __construct(
		private Texy $texy,
		private string $module,
	) {
	}


	public function &__get(string $name): mixed
	{
		return Legacy::ref($this->texy, Legacy::OfModule[$this->module], "\$texy->{$this->module}", $name, 'read');
	}


	public function __set(string $name, mixed $value): void
	{
		Legacy::set($this->texy, Legacy::OfModule[$this->module], "\$texy->{$this->module}", $name, $value);
	}


	public function __isset(string $name): bool
	{
		return Legacy::isSet($this->texy, Legacy::OfModule[$this->module], $name);
	}
}
