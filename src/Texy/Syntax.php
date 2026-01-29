<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Texy syntax identifiers for use with $texy->allowed[].
 */
final class Syntax
{
	// Block elements - special blocks (/--xxx)

	/** Special blocks pattern (`/--xxx`) - matches all block types */
	public const Blocks = 'blocks';

	/** Preformatted text block (`/--`) */
	public const BlockDefault = 'block/default';

	/** Preformatted text block (`/--pre`) */
	public const BlockPre = 'block/pre';

	/** Code block with syntax highlighting (`/--code`) */
	public const BlockCode = 'block/code';

	/** Raw HTML block (`/--html`) */
	public const BlockHtml = 'block/html';

	/** Raw text block (`/--text`) */
	public const BlockText = 'block/text';

	/** Texy source demo (`/--texysource`) */
	public const BlockTexySource = 'block/texysource';

	/** Hidden comment (`/--comment`) */
	public const BlockComment = 'block/comment';

	/** Division wrapper (`/--div`) */
	public const BlockDiv = 'block/div';

	// Block elements - structures

	/** Block quote (`> text`) */
	public const Blockquote = 'blockquote';

	/** Image with caption (`[*image*] *** caption`) */
	public const Figure = 'figure';

	/** Heading - underlined style (`Title` followed by `###`) */
	public const HeadingUnderlined = 'heading/underlined';

	/** Heading - surrounded style (`### heading`) */
	public const HeadingSurrounded = 'heading/surrounded';

	/** Horizontal rule (`---` or `***`) */
	public const HorizontalRule = 'horizline';

	/** Ordered/unordered list (`-`, `*`, `1.`) */
	public const List = 'list';

	/** Definition list (`term:` followed by `- definition`) */
	public const DefinitionList = 'list/definition';

	/** Table (`| cell | cell |`) */
	public const Table = 'table';

	// Block elements - reference definitions

	/** Image definition (`[*name*]: url`) */
	public const ImageDefinition = 'image/definition';

	/** Link definition (`[name]: url`) */
	public const LinkDefinition = 'link/definition';

	// Inline - text formatting

	/** Strong text (`**text**`) */
	public const Strong = 'phrase/strong';

	/** Strong + emphasis (`***text***`) */
	public const StrongEmphasis = 'phrase/strong+em';

	/** Emphasis - double slash (`//text//`) */
	public const Emphasis = 'phrase/em';

	/** Emphasis - single asterisk (`*text*`) */
	public const EmphasisSingleAsterisk = 'phrase/em-alt';

	/** Emphasis - single asterisk contextual (`*text*`) */
	public const EmphasisSingleAsterisk2 = 'phrase/em-alt2';

	/** Inserted text (`++text++`) */
	public const Inserted = 'phrase/ins';

	/** Deleted text (`--text--`) */
	public const Deleted = 'phrase/del';

	/** Superscript (`^^text^^`) */
	public const Superscript = 'phrase/sup';

	/** Superscript short form (`m^2`) */
	public const SuperscriptShort = 'phrase/sup-alt';

	/** Subscript (`__text__`) */
	public const Subscript = 'phrase/sub';

	/** Subscript short form (`H_2O`) */
	public const SubscriptShort = 'phrase/sub-alt';

	/** Span with modifier - quotes (`"text".[class]`) */
	public const SpanQuotes = 'phrase/span';

	/** Span with modifier - tilde (`~text~.[class]`) */
	public const SpanTilde = 'phrase/span-alt';

	/** Abbreviation with quotes (`"NATO"((North Atlantic Treaty Organization))`) */
	public const AbbreviationQuotes = 'phrase/acronym';

	/** Abbreviation without quotes (`NATO((North Atlantic Treaty Organization))`) */
	public const Abbreviation = 'phrase/acronym-alt';

	/** Inline code (`` `code` ``) */
	public const Code = 'phrase/code';

	/** Inline quote (`>>text<<`) */
	public const Quote = 'phrase/quote';

	/** Raw unprocessed text (`''text''`) */
	public const Raw = 'phrase/notexy';

	/** Escaped asterisk (`\*`) */
	public const EscapedAsterisk = 'phrase/escaped-asterix';

	// Inline - links

	/** Quick link (`text:[url]`) */
	public const QuickLink = 'phrase/quicklink';

	/** Wiki-style link (`[text|url]`) */
	public const WikiLink = 'phrase/wikilink';

	/** Markdown-style link (`[text](url)`) */
	public const MarkdownLink = 'phrase/markdown';

	/** Auto-detected URL (`https://example.com`) */
	public const AutolinkUrl = 'link/url';

	/** Auto-detected email (`user@example.com`) */
	public const AutolinkEmail = 'link/email';

	// Inline - images

	/** Inline image (`[*image.png*]`) */
	public const Image = 'image';

	// Inline - special

	/** Emoticon (`:-)`) */
	public const Emoticon = 'emoticon';

	/** HTML tag (`<tag>`) */
	public const HtmlTag = 'html/tag';

	/** HTML comment (`<!-- comment -->`) */
	public const HtmlComment = 'html/comment';

	/** Directive (`{{command}}`) */
	public const Directive = 'script';

	// Post-processing

	/** Typography processing (quotes, dashes, ellipsis) */
	public const Typography = 'typography';

	/** Long words hyphenation */
	public const Hyphenation = 'longwords';
}
