<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;


/**
 * How far a document got in the transformation; it goes through it once.
 * @internal
 */
enum TransformState
{
	case Pending;
	case Running;
	case Done;
	case Failed;
}


enum HeadingType
{
	case Underlined;
	case Surrounded;
}


/**
 * What a phrase means, whatever notation it is written in.
 */
enum PhraseType: string
{
	case Strong = 'strong';
	case Emphasis = 'emphasis';
	case StrongEmphasis = 'strong-emphasis';
	case Inserted = 'inserted';
	case Deleted = 'deleted';
	case Superscript = 'superscript';
	case Subscript = 'subscript';
	case Abbreviation = 'abbreviation';
	case Quote = 'quote';

	/** text carrying only a modifier */
	case Span = 'span';
}


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
