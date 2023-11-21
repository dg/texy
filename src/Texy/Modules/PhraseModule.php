<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Modules;

use Texy;
use Texy\LineParser;
use Texy\Modifier;
use Texy\Patterns;


/**
 * Phrases module.
 */
final class PhraseModule extends Texy\Module
{
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
		'phrase/span' => 'a',
		'phrase/span-alt' => 'a',
		'phrase/acronym' => 'abbr',
		'phrase/acronym-alt' => 'abbr',
		'phrase/code' => 'code',
		'phrase/quote' => 'q',
		'phrase/quicklink' => 'a',
	];

	public bool $linksAllowed = true;


	public function __construct(Texy\Texy $texy)
	{
		$this->texy = $texy;

		$texy->addHandler('phrase', $this->solve(...));

		/*
		// UNIVERSAL
		$texy->registerLinePattern(
			array($this, 'patternPhrase'),
			'#((?>([*+/^_"~`-])+?))(?!\s)(.*(?!\2).)'.Texy\Patterns::MODIFIER.'?(?<!\s)\1(?!\2)(?::('.Texy\Patterns::LINK_URL.'))??()#Uus',
			'phrase/strong'
		);
		*/

		// ***strong+emphasis***
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<![*\\\\])\*\*\*(?![\s*])((?:[^ *]++|[ *])+)' . Patterns::MODIFIER . '?(?<![\s*\\\\])\*\*\*(?!\*)(?::(' . Patterns::LINK_URL . '))??()#Uus',
			'phrase/strong+em',
		);

		// **strong**
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<![*\\\\])\*\*(?![\s*])((?:[^ *]++|[ *])+)' . Patterns::MODIFIER . '?(?<![\s*\\\\])\*\*(?!\*)(?::(' . Patterns::LINK_URL . '))??()#Uus',
			'phrase/strong',
		);

		// //emphasis//
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<![/:])\/\/(?![\s/])((?:[^ /]++|[ /])+)' . Patterns::MODIFIER . '?(?<![\s/:])\/\/(?!\/)(?::(' . Patterns::LINK_URL . '))??()#Uus',
			'phrase/em',
		);

		// *emphasisAlt*
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<![*\\\\])\*(?![\s*])((?:[^\s*]++|[*])+)' . Patterns::MODIFIER . '?(?<![\s*\\\\])\*(?!\*)(?::(' . Patterns::LINK_URL . '))??()#Uus',
			'phrase/em-alt',
		);

		// *emphasisAlt2*
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<![^\s.,;:<>()"\'' . Patterns::MARK . '-])\*(?![\s*])((?:[^ *]++|[ *])+)' . Patterns::MODIFIER . '?(?<![\s*\\\\])\*(?![^\s.,;:<>()"?!\'-])(?::(' . Patterns::LINK_URL . '))??()#Uus',
			'phrase/em-alt2',
		);

		// ++inserted++
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<!\+)\+\+(?![\s+])((?:[^\r\n +]++|[ +])+)' . Patterns::MODIFIER . '?(?<![\s+])\+\+(?!\+)()#Uu',
			'phrase/ins',
		);

		// --deleted--
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<![<-])\-\-(?![\s>-])((?:[^\r\n -]++|[ -])+)' . Patterns::MODIFIER . '?(?<![\s<-])\-\-(?![>-])()#Uu',
			'phrase/del',
		);

		// ^^superscript^^
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<!\^)\^\^(?![\s^])((?:[^\r\n ^]++|[ ^])+)' . Patterns::MODIFIER . '?(?<![\s^])\^\^(?!\^)()#Uu',
			'phrase/sup',
		);

		// m^2 alternative superscript
		$texy->registerLinePattern(
			$this->patternSupSub(...),
			'#(?<=[a-z0-9])\^([n0-9+-]{1,4}?)(?![a-z0-9])#Uui',
			'phrase/sup-alt',
		);

		// __subscript__
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<!\_)\_\_(?![\s_])((?:[^\r\n _]++|[ _])+)' . Patterns::MODIFIER . '?(?<![\s_])\_\_(?!\_)()#Uu',
			'phrase/sub',
		);

		// m_2 alternative subscript
		$texy->registerLinePattern(
			$this->patternSupSub(...),
			'#(?<=[a-z])\_([n0-9]{1,3})(?![a-z0-9])#Uui',
			'phrase/sub-alt',
		);

		// "span"
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<!\")\"(?!\s)((?:[^\r "]++|[ ])+)' . Patterns::MODIFIER . '?(?<!\s)\"(?!\")(?::(' . Patterns::LINK_URL . '))??()#Uu',
			'phrase/span',
		);

		// ~alternative span~
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<!\~)\~(?!\s)((?:[^\r ~]++|[ ])+)' . Patterns::MODIFIER . '?(?<!\s)\~(?!\~)(?::(' . Patterns::LINK_URL . '))??()#Uu',
			'phrase/span-alt',
		);

		// >>quote<<
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<!\>)\>\>(?![\s>])((?:[^\r\n <]++|[ <])+)' . Patterns::MODIFIER . '?(?<![\s<])\<\<(?!\<)(?::(' . Patterns::LINK_URL . '))??()#Uu',
			'phrase/quote',
		);

		// acronym/abbr "et al."((and others))
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<!\")\"(?!\s)((?:[^\r\n "]++|[ ])+)' . Patterns::MODIFIER . '?(?<!\s)\"(?!\")\(\((.+)\)\)()#Uu',
			'phrase/acronym',
		);

		// acronym/abbr NATO((North Atlantic Treaty Organisation))
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<![' . Patterns::CHAR . '])([' . Patterns::CHAR . ']{2,})()\(\(((?:[^\n )]++|[ )])+)\)\)#Uu',
			'phrase/acronym-alt',
		);

		// ''notexy''
		$texy->registerLinePattern(
			$this->patternNoTexy(...),
			'#(?<!\')\'\'(?![\s\'])((?:[^' . Patterns::MARK . '\r\n\']++|[\'])+)(?<![\s\'])\'\'(?!\')()#Uu',
			'phrase/notexy',
		);

		// `code`
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#\`(\S(?:[^' . Patterns::MARK . '\r\n `]++|[ `])*)' . Patterns::MODIFIER . '?(?<!\s)\`(?::(' . Patterns::LINK_URL . '))??()#Uu',
			'phrase/code',
		);

		// ....:LINK
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#([' . Patterns::CHAR . '0-9@\#$%&.,_-]++)()(?=:\[)(?::(' . Patterns::LINK_URL . '))()#Uu',
			'phrase/quicklink',
		);

		// [text |link]
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<!\[)\[(?![\s*])([^|\r\n\]]++)\|((?:[^' . Patterns::MARK . '|\r\n \]]++|[ ])+)' . Patterns::MODIFIER . '?(?<!\s)\](?!\])()#Uu',
			'phrase/wikilink',
		);

		// [text](link)
		$texy->registerLinePattern(
			$this->patternPhrase(...),
			'#(?<![[.])\[(?![\s*])((?:[^|\r\n \]]++|[ ])+)' . Patterns::MODIFIER . '?(?<!\s)\]\(((?:[^' . Patterns::MARK . '\r )]++|[ ])+)\)()#Uu',
			'phrase/markdown',
		);

		// \* escaped asterix
		$texy->registerLinePattern(
			fn() => '*',
			'#\\\\\*#',
			'phrase/escaped-asterix',
		);


		$texy->allowed['phrase/ins'] = false;
		$texy->allowed['phrase/del'] = false;
		$texy->allowed['phrase/sup'] = false;
		$texy->allowed['phrase/sub'] = false;
	}


	/**
	 * Callback for: **.... .(title)[class]{style}**:LINK.
	 */
	public function patternPhrase(LineParser $parser, array $matches, string $phrase): Texy\HtmlElement|string|null
	{
		[, $mContent, $mMod, $mLink] = $matches;
		// [1] => **
		// [2] => ...
		// [3] => .(title)[class]{style}
		// [4] => LINK

		if ($phrase === 'phrase/wikilink') {
			[$mLink, $mMod] = [$mMod, $mLink];
			$mContent = trim($mContent);
		}

		$texy = $this->texy;
		$mod = new Modifier($mMod);
		$link = null;

		$parser->again = $phrase !== 'phrase/code' && $phrase !== 'phrase/quicklink';

		if ($phrase === 'phrase/span' || $phrase === 'phrase/span-alt') {
			if ($mLink == null) {
				if (!$mMod) {
					return null; // means "..."
				}
			} else {
				$link = $texy->linkModule->factoryLink($mLink, $mMod, $mContent);
			}
		} elseif ($phrase === 'phrase/acronym' || $phrase === 'phrase/acronym-alt') {
			$mod->title = trim(Texy\Helpers::unescapeHtml($mLink));

		} elseif ($mLink != null) {
			$link = $texy->linkModule->factoryLink($mLink, null, $mContent);
		}

		return $texy->invokeAroundHandlers('phrase', $parser, [$phrase, $mContent, $mod, $link]);
	}


	/**
	 * Callback for: any^2 any_2.
	 */
	public function patternSupSub(LineParser $parser, array $matches, string $phrase): Texy\HtmlElement|string|null
	{
		[, $mContent] = $matches;
		$mod = new Modifier;
		$link = null;
		$mContent = str_replace('-', "\u{2212}", $mContent); // &minus;
		return $this->texy->invokeAroundHandlers('phrase', $parser, [$phrase, $mContent, $mod, $link]);
	}


	public function patternNoTexy(LineParser $parser, array $matches): string
	{
		[, $mContent] = $matches;
		return $this->texy->protect(htmlspecialchars($mContent, ENT_NOQUOTES, 'UTF-8'), Texy\Texy::CONTENT_TEXTUAL);
	}


	/**
	 * Finish invocation.
	 */
	private function solve(
		Texy\HandlerInvocation $invocation,
		string $phrase,
		string $content,
		Modifier $mod,
		?Texy\Link $link = null,
	): Texy\HtmlElement|string|null
	{
		$texy = $this->texy;
		$tag = $this->tags[$phrase] ?? null;

		if ($tag === 'a') {
			$tag = $link && $this->linksAllowed ? null : 'span';
		}

		if ($phrase === 'phrase/code') {
			$content = $texy->protect(htmlspecialchars($content, ENT_NOQUOTES, 'UTF-8'), $texy::CONTENT_TEXTUAL);
		}

		if ($phrase === 'phrase/strong+em') {
			$el = new Texy\HtmlElement($this->tags['phrase/strong']);
			$el->create($this->tags['phrase/em'], $content);
			$mod->decorate($texy, $el);

		} elseif ($tag) {
			$el = new Texy\HtmlElement($tag, $content);
			$mod->decorate($texy, $el);
		} else {
			$el = $content; // trick
		}

		if ($link && $this->linksAllowed) {
			return $texy->linkModule->solve(null, $link, $el);
		}

		return $el;
	}
}
