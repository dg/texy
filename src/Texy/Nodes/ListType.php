<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Nodes;


enum ListType: string
{
	case Unordered = 'ul';
	case Decimal = 'ol';
	case UpperRoman = 'upper-roman';
	case LowerRoman = 'lower-roman';
	case UpperAlpha = 'upper-alpha';
	case LowerAlpha = 'lower-alpha';


	public function isOrdered(): bool
	{
		return $this !== self::Unordered;
	}


	public function getStyleType(): ?string
	{
		return match ($this) {
			self::Unordered, self::Decimal => null,
			default => $this->value,
		};
	}
}
