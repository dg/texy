<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\Modifier;
use Texy\Nodes\AnnotationNode;
use Texy\Nodes\ContentNode;
use Texy\Nodes\LinkNode;
use Texy\Nodes\PhraseNode;
use Texy\Nodes\RawTextNode;
use Texy\Nodes\TextNode;
use Texy\Output\Html;
use Texy\ParseContext;
use Texy\Patterns;
use function htmlspecialchars, str_replace, trim;
use const ENT_HTML5, ENT_NOQUOTES;


/**
 * Processes inline text formatting (bold, italic, code, etc.).
 */
final class PhraseModule extends Texy\Module
{
	/** @var array<string, string> */
	public array $tags = [
		'phrase/strong' => 'strong', // or 'b'
		'phrase/em' => 'em', // or 'i'
		'phrase/em-alt' => 'em',
		'phrase/em-alt2' => 'em',
		'phrase/ins' => 'ins',
		'phrase/del' => 'del',
		'phrase/sup' => 'sup',
		'phrase/sup-alt' => 'sup',
		'phrase/sub' => 'sub',
		'phrase/sub-alt' => 'sub',
		'phrase/span' => 'span',
		'phrase/span-alt' => 'span',
		'phrase/acronym' => 'abbr',
		'phrase/acronym-alt' => 'abbr',
		'phrase/code' => 'code',
		'phrase/quote' => 'q',
		'phrase/quicklink' => 'a',
	];

	public bool $linksAllowed = true;


	public function __construct(
		private Texy\Texy $texy,
	) {
		$texy->allowed['phrase/ins'] = false;
		$texy->allowed['phrase/del'] = false;
		$texy->allowed['phrase/sup'] = false;
		$texy->allowed['phrase/sub'] = false;
		$texy->htmlGenerator->registerHandler($this->solvePhrase(...));
		$texy->htmlGenerator->registerHandler($this->solveAnnotation(...));
		$texy->htmlGenerator->registerHandler($this->solveRawText(...));
	}


	public function beforeParse(string &$text): void
	{
		$texy = $this->texy;
		/*
		// UNIVERSAL
		$texy->registerLinePattern(
			array($this, 'patternPhrase'),
			'~((?>([*+/^_"\~`-])+?))(?!\s)(.*(?!\2).)'.Texy\Patterns::MODIFIER.'?(?<!\s)\1(?!\2)(?::('.Texy\Patterns::LINK_URL.'))??~Us',
			'phrase/strong'
		);
		*/

		// ***strong+emphasis***
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! [*\\\] )                     # not preceded by * or \
				\*\*\*
				(?! [\s*] )                       # not followed by space or *
				( (?: [^ *]++ | [ *] )+ )         # content (1)
				' . Patterns::MODIFIER . '?       # modifier (2)
				(?<! [\s*\\\] )                   # not preceded by space, * or \
				\*\*\*
				(?! \* )                          # not followed by *
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Us',
			'phrase/strong+em',
		);

		// **strong**
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! [*\\\] )                     # not preceded by * or \
				\*\*
				(?! [\s*] )                       # not followed by space or *
				( (?: [^ *]++ | [ *] )+ )         # content (1)
				' . Patterns::MODIFIER . '?       # modifier (2)
				(?<! [\s*\\\] )                   # not preceded by space, * or \
				\*\*
				(?! \* )                          # not followed by *
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Us',
			'phrase/strong',
		);

		// //emphasis//
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! [/:] )                       # not preceded by / or :
				//
				(?! [\s/] )                       # not followed by space or /
				( (?: [^ /]++ | [ /] )+ )         # content (1)
				' . Patterns::MODIFIER . '?       # modifier (2)
				(?<! [\s/:] )                     # not preceded by space, / or :
				//
				(?! / )                           # not followed by /
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Us',
			'phrase/em',
		);

		// *emphasisAlt*
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! [*\\\] )                    # not preceded by * or \
				\*
				(?! [\s*] )                      # not followed by space or *
				( (?: [^\s*]++ | [*] )+ )        # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s*\\\] )                  # not preceded by space, * or \
				\*
				(?! \* )                         # not followed by *
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Us',
			'phrase/em-alt',
		);

		// *emphasisAlt2*
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! [^\s.,;:<>()"\'' . Patterns::MARK . '-] )  # must be preceded by these chars
				\*
				(?! [\s*] )                      # not followed by space or *
				( (?: [^ *]++ | [ *] )+ )        # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s*\\\] )                  # not preceded by space, * or \
				\*
				(?! [^\s.,;:<>()"?!\'-] )        # must be followed by these chars
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~Us',
			'phrase/em-alt2',
		);

		// ++inserted++
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! \+ )                        # not preceded by +
				\+\+
				(?! [\s+] )                      # not followed by space or +
				( (?: [^\r\n +]++ | [ +] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s+] )                     # not preceded by space or +
				\+\+
				(?! \+ )                         # not followed by +
			~U',
			'phrase/ins',
		);

		// --deleted--
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! [<-] )                      # not preceded by < or -
				--
				(?! [\s>-] )                     # not followed by space, > or -
				( (?: [^\r\n -]++ | [ -] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s<-] )                    # not preceded by space, < or -
				--
				(?! [>-] )                       # not followed by > or -
			~U',
			'phrase/del',
		);

		// ^^superscript^^
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! \^ )                        # not preceded by ^
				\^\^
				(?! [\s^] )                      # not followed by space or ^
				( (?: [^\r\n ^]++ | [ ^] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s^] )                     # not preceded by space or ^
				\^\^
				(?! \^ )                         # not followed by ^
			~U',
			'phrase/sup',
		);

		// m^2 alternative superscript
		$texy->registerLinePattern(
			$this->parseSupSub(...),
			'~
				(?<= [a-z0-9] )                  # preceded by letter or number
				\^
				( [n0-9+-]{1,4}? )               # 1-4 digits, n, + or - (1)
				(?! [a-z0-9] )                   # not followed by letter or number
			~Ui',
			'phrase/sup-alt',
		);

		// __subscript__
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! _ )                         # not preceded by _
				__
				(?! [\s_] )                      # not followed by space or _
				( (?: [^\r\n _]++ | [ _] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s_] )                     # not preceded by space or _
				__
				(?! _ )                          # not followed by _
			~U',
			'phrase/sub',
		);

		// m_2 alternative subscript
		$texy->registerLinePattern(
			$this->parseSupSub(...),
			'~
				(?<= [a-z] )                     # preceded by letter
				_
				( [n0-9]{1,3} )                  # 1-3 digits or n (1)
				(?! [a-z0-9] )                   # not followed by letter or number
			~Ui',
			'phrase/sub-alt',
		);

		// "span"
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! " )                         # not preceded by "
				"
				(?! \s )                         # not followed by space
				( (?: [^\r "]++ | [ ] )+ )       # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! \s )                        # not preceded by space
				"
				(?! " )                          # not followed by "
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~U',
			'phrase/span',
		);

		// ~alternative span~
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! \~ )
				\~
				(?! \s )                         # not followed by space
				( (?: [^\r \~]++ | [ ] )+ )      # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! \s )                        # not preceded by space
				\~
				(?! \~ )
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~U',
			'phrase/span-alt',
		);

		// >>quote<<
		$texy->registerLinePattern(
			$this->parsePhrase(...),
			'~
				(?<! > )                         # not preceded by >
				>>
				(?! [\s>] )                      # not followed by space or >
				( (?: [^\r\n <]++ | [ <] )+ )    # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! [\s<] )                     # not preceded by space or
				<<
				(?! < )                          # not followed by <
				(?: :(' . Patterns::LINK_URL . ') )??  # optional link (3)
			~U',
			'phrase/quote',
		);

		// acronym/abbr "et al."((and others))
		$texy->registerLinePattern(
			$this->parseAcronym(...),
			'~
				(?<! " )                         # not preceded by "
				"
				(?! \s )                         # not followed by space
				( (?: [^\r\n "]++ | [ ] )+ )     # content (1)
				' . Patterns::MODIFIER . '?      # modifier (2)
				(?<! \s )                        # not preceded by space
				"
				(?! " )                          # not followed by "
				\(\(
				( .+ )                           # explanation (3)
				\)\)
			~U',
			'phrase/acronym',
		);

		// acronym/abbr NATO((North Atlantic Treaty Organisation))
		$texy->registerLinePattern(
			$this->parseAcronym(...),
			'~
				(?<! [' . Patterns::CHAR . '] )  # not preceded by char
				( [' . Patterns::CHAR . ']{2,} ) # at least 2 chars (1)
				()                               # modifier placeholder (2)
				\(\(
				( (?: [^\n )]++ | [ )] )+ )      # explanation (3)
				\)\)
			~U',
			'phrase/acronym-alt',
		);

		// ''notexy''
		$texy->registerLinePattern(
			$this->parseNoTexy(...),
			'~
				(?<! \' )                         # not preceded by quote
				\'\'
				(?! [\s\'] )                      # not followed by space or quote
				( (?: [^' . Patterns::MARK . '\r\n\']++ | \' )+ )  # content (1)
				(?<! [\s\'] )                     # not preceded by space or quote
				\'\'
				(?! \' )                          # not followed by quote
			~U',
			'phrase/notexy',
		);

		// `code`
		$texy->registerLinePattern(
			$this->parseCode(...),
			'~
				`
				( \S (?: [^' . Patterns::MARK . '\r\n `]++ | [ `] )* )  # content (1)
				' . Patterns::MODIFIER . '?             # modifier (2)
				(?<! \s )                               # not preceded by space
				`
				(?: : (' . Patterns::LINK_URL . ') )??  # optional link (3)
			~U',
			'phrase/code',
		);

		// ....:LINK
		$texy->registerLinePattern(
			$this->parseLink(...),
			'~
				( [' . Patterns::CHAR . '0-9@#$%&.,_-]++ )  # allowed chars (1)
				()                                    # modifier placeholder (2)
				: (?= \[ )                            # followed by :[
				(' . Patterns::LINK_URL . ')          # link (3)
			~U',
			'phrase/quicklink',
		);

		// [text |link]
		$texy->registerLinePattern(
			$this->parseWikilink(...),
			'~
				(?<! \[ )                        # not preceded by [
				\[
				(?! [\s*] )                      # not followed by space or *
				( [^|\r\n\]]++ )                 # text (1)
				\|
				( (?: [^' . Patterns::MARK . '|\r\n \]]++ | [ ] )+ )  # link (2)
				' . Patterns::MODIFIER . '?      # modifier (3)
				(?<! \s )                        # not preceded by space
				]
				(?! ] )                          # not followed by ]
			~U',
			'phrase/wikilink',
		);

		// [text](link)
		$texy->registerLinePattern(
			$this->parseLink(...),
			'~
				(?<! [[.] )                     # not preceded by [ or .
				\[
				(?! [\s*] )                     # not followed by space or *
				( (?: [^|\r\n \]]++ | [ ] )+ )  # text (1)
				' . Patterns::MODIFIER . '?     # modifier (2)
				(?<! \s )                       # not preceded by space
				]
				\(
				( (?: [^' . Patterns::MARK . '\r )]++ | [ ] )+ )  # link (3)
				\)
			~U',
			'phrase/markdown',
		);

		// \* escaped asterisk
		$texy->registerLinePattern(
			fn($context, $matches) => new TextNode('*'),
			'~\\\\\*~',
			'phrase/escaped-asterix',
		);
	}


	/**
	 * Parses phrase patterns.
	 * @param  array<?string>  $matches
	 */
	public function parsePhrase(
		ParseContext $context,
		array $matches,
		string $phrase,
	): PhraseNode|LinkNode|null
	{
		[, $mContent, $mMod, $mLink] = $matches + [2 => null, 3 => null];

		// For phrase/span and phrase/span-alt, URL makes it a link
		if (($phrase === 'phrase/span' || $phrase === 'phrase/span-alt') && $mLink !== null) {
			$content = $context->parseInline(trim($mContent));
			return new LinkNode($mLink, $content, Modifier::parse($mMod));
		}

		// For phrase/span without URL and without modifier, return null (means "...")
		if (($phrase === 'phrase/span' || $phrase === 'phrase/span-alt') && $mMod === null && $mLink === null) {
			return null;
		}

		$mod = Modifier::parse($mMod);
		$content = $context->parseInline(trim($mContent));

		// Other phrases with link
		if ($mLink !== null) {
			// Wrap phrase in link
			$phraseNode = new PhraseNode($content, $phrase, $mod);
			return new LinkNode($mLink, new ContentNode([$phraseNode]));
		}

		return new PhraseNode($content, $phrase, $mod);
	}


	/**
	 * Parses code phrase.
	 * @param  array<?string>  $matches
	 */
	public function parseCode(ParseContext $context, array $matches, string $phrase): PhraseNode
	{
		[, $mContent, $mMod] = $matches + [2 => null];
		// Code content is not parsed recursively
		$content = [new TextNode($mContent)];
		return new PhraseNode(
			new ContentNode($content),
			$phrase,
			Modifier::parse($mMod),
		);
	}


	/**
	 * Parses superscript/subscript alternative (m^2, H_2O).
	 * @param  array<?string>  $matches
	 */
	public function parseSupSub(ParseContext $context, array $matches, string $phrase): PhraseNode
	{
		[, $mContent] = $matches;
		$mContent = str_replace('-', "\u{2212}", $mContent); // &minus;
		$content = [new TextNode($mContent)];
		return new PhraseNode(
			new ContentNode($content),
			$phrase,
		);
	}


	/**
	 * Parses acronym/abbr.
	 * @param  array<?string>  $matches
	 */
	public function parseAcronym(ParseContext $context, array $matches, string $phrase): PhraseNode
	{
		[, $mContent, $mMod, $mTitle] = $matches + [2 => null, 3 => ''];

		$mod = Modifier::parse($mMod) ?? new Modifier;
		$mod->title = trim(Texy\Helpers::unescapeHtml($mTitle));
		$content = [new TextNode(trim($mContent))];

		return new PhraseNode(new ContentNode($content), $phrase, $mod);
	}


	/**
	 * Parses ''notexy''.
	 * @param  array<?string>  $matches
	 */
	public function parseNoTexy(ParseContext $context, array $matches): RawTextNode
	{
		[, $mContent] = $matches;
		return new RawTextNode($mContent);
	}


	/**
	 * Parses links (phrase/quicklink, phrase/markdown).
	 * Note: Markdown links don't support modifiers - modifier is intentionally ignored.
	 * @param  array<?string>  $matches
	 */
	public function parseLink(ParseContext $context, array $matches): LinkNode
	{
		[, $mContent, $mMod, $mLink] = $matches + [2 => null, 3 => null];
		return new LinkNode(
			trim($mLink ?? ''),
			$context->parseInline(trim($mContent)),
		);
	}


	/**
	 * Parses wikilink [text|link].
	 * Note: Wikilinks don't support modifiers - modifier is intentionally ignored.
	 * @param  array<?string>  $matches
	 */
	public function parseWikilink(ParseContext $context, array $matches): LinkNode
	{
		[, $mContent, $mLink] = $matches;
		return new LinkNode(
			trim($mLink),
			$context->parseInline(trim($mContent)),
		);
	}


	public function solvePhrase(PhraseNode $node, Html\Generator $generator): Html\Element
	{
		$tag = $this->tags[$node->type] ?? 'span';

		if ($node->type === 'phrase/strong+em') {
			$el = new Html\Element($this->tags['phrase/strong'] ?? 'strong');
			$node->modifier?->decorate($this->texy, $el);
			$inner = $el->create($this->tags['phrase/em'] ?? 'em');
			$inner->children = $generator->renderNodes($node->content->children);
			return $el;
		}

		// Code phrases - escape and protect content from typography
		if ($node->type === 'phrase/code') {
			$el = new Html\Element($tag);
			$node->modifier?->decorate($this->texy, $el);
			$content = $generator->serialize($generator->renderNodes($node->content->children));
			// Only escape <, >, & - not quotes (ENT_NOQUOTES)
			$content = htmlspecialchars($content, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
			$el->children = [$this->texy->protect($content, $this->texy::CONTENT_TEXTUAL)];
			return $el;
		}

		// Normal phrases
		$el = new Html\Element($tag);
		$node->modifier?->decorate($this->texy, $el);
		$el->children = $generator->renderNodes($node->content->children);
		return $el;
	}


	public function solveAnnotation(AnnotationNode $node, Html\Generator $generator): Html\Element
	{
		return (new Html\Element('abbr', ['title' => $node->annotation]))->setText($node->content);
	}


	public function solveRawText(RawTextNode $node): string
	{
		return htmlspecialchars($node->content, ENT_NOQUOTES, 'UTF-8');
	}
}
