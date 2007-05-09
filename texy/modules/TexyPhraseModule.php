<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * @version  $Revision$ $Date$
 * @package  Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();



/**
 * Phrases module
 */
class TexyPhraseModule extends TexyModule
{
    protected $default = array(
        'phrase/strong+em' => TRUE,  // ***strong+emphasis***
        'phrase/strong' => TRUE,     // **strong**
        'phrase/em' => TRUE,         // //emphasis//
        'phrase/em-alt' => TRUE,     // *emphasis*
        'phrase/span' => TRUE,       // "span"
        'phrase/span-alt' => TRUE,   // ~span~
        'phrase/acronym' => TRUE,    // "acro nym"((...))
        'phrase/acronym-alt' => TRUE,// acronym((...))
        'phrase/code' => TRUE,       // `code`
        'phrase/notexy' => TRUE,     // ''....''
        'phrase/quote' => TRUE,      // >>quote<<:...
        'phrase/quicklink' => TRUE,  // ....:LINK
        'phrase/sup-alt' => TRUE,    // superscript^2
        'phrase/sub-alt' => TRUE,    // subscript_3

        'phrase/ins' => FALSE,       // ++inserted++
        'phrase/del' => FALSE,       // --deleted--
        'phrase/sup' => FALSE,       // ^^superscript^^
        'phrase/sub' => FALSE,       // __subscript__
        'phrase/cite' => FALSE,      // ~~cite~~

        // back compatibility
        'deprecated/codeswitch' => FALSE,// `=...
    );

    public $tags = array(
        'phrase/strong' => 'strong', // or 'b'
        'phrase/em' => 'em', // or 'i'
        'phrase/em-alt' => 'em',
        'phrase/ins' => 'ins',
        'phrase/del' => 'del',
        'phrase/sup' => 'sup',
        'phrase/sup-alt' => 'sup',
        'phrase/sub' => 'sub',
        'phrase/sub-alt' => 'sub',
        'phrase/span' => 'a',
        'phrase/span-alt' => 'a',
        'phrase/cite' => 'cite',
        'phrase/acronym' => 'acronym',
        'phrase/acronym-alt' => 'acronym',
        'phrase/code'  => 'code',
        'phrase/quote' => 'q',
        'phrase/quicklink' => 'a',
    );


    /** @var bool  are links allowed? */
    public $linksAllowed = TRUE;



    public function begin()
    {
        $tx = $this->texy;
/*
        // UNIVERSAL
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#((?>([*+/^_"~`-])+?))(?!\s)(.*(?!\\2).)'.TEXY_MODIFIER.'?(?<!\s)\\1(?!\\2)'.TEXY_LINK.'??()#Uus',
            'phrase/strong'
        );
*/

        // strong & em speciality
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!\*)\*\*\*(?![\s*])(.+)'.TEXY_MODIFIER.'?(?<![\s*])\*\*\*(?!\*)'.TEXY_LINK.'??()#Uus',
            'phrase/strong+em'
        );

        // **strong**
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!\*)\*\*(?![\s*])(.+)'.TEXY_MODIFIER.'?(?<![\s*])\*\*(?!\*)'.TEXY_LINK.'??()#Uus',
            'phrase/strong'
        );

        // //emphasis//
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<![/:])\/\/(?![\s/])(.+)'.TEXY_MODIFIER.'?(?<![\s/])\/\/(?!\/)'.TEXY_LINK.'??()#Uus',
            'phrase/em'
        );

        // *emphasisAlt*
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!\*)\*(?![\s*])(.+)'.TEXY_MODIFIER.'?(?<![\s*])\*(?!\*)'.TEXY_LINK.'??()#Uus',
            'phrase/em-alt'
        );

        // ++inserted++
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!\+)\+\+(?![\s+])([^\r\n]+)'.TEXY_MODIFIER.'?(?<![\s+])\+\+(?!\+)()#Uu',
            'phrase/ins'
        );

        // --deleted--
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<![<-])\-\-(?![\s>-])([^\r\n]+)'.TEXY_MODIFIER.'?(?<![\s<-])\-\-(?![>-])()#Uu',
            'phrase/del'
        );

        // ^^superscript^^
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!\^)\^\^(?![\s^])([^\r\n]+)'.TEXY_MODIFIER.'?(?<![\s^])\^\^(?!\^)()#Uu',
            'phrase/sup'
        );

        // m^2 alternative superscript
        $tx->registerLinePattern(
            array($this, 'patternSupSub'),
            '#(?<=[a-z0-9])\^([0-9]{1,4})(?![a-z0-9])#Uui',
            'phrase/sup-alt'
        );

        // __subscript__
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!\_)\_\_(?![\s_])([^\r\n]+)'.TEXY_MODIFIER.'?(?<![\s_])\_\_(?!\_)()#Uu',
            'phrase/sub'
        );

        // m_2 alternative subscript
        $tx->registerLinePattern(
            array($this, 'patternSupSub'),
            '#(?<=[a-z])\_([0-9]{1,3})(?![a-z0-9])#Uui',
            'phrase/sub-alt'
        );

        // "span"
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!\")\"(?!\s)([^\"\r]+)'.TEXY_MODIFIER.'?(?<!\s)\"(?!\")'.TEXY_LINK.'??()#Uu',
            'phrase/span'
        );

        // ~alternative span~
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!\~)\~(?!\s)([^\~\r]+)'.TEXY_MODIFIER.'?(?<!\s)\~(?!\~)'.TEXY_LINK.'??()#Uu',
            'phrase/span-alt'
        );

        // ~~cite~~
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!\~)\~\~(?![\s~])([^\r\n]+)'.TEXY_MODIFIER.'?(?<![\s~])\~\~(?!\~)'.TEXY_LINK.'??()#Uu',
            'phrase/cite'
        );

        // >>quote<<
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!\>)\>\>(?![\s>])([^\r\n]+)'.TEXY_MODIFIER.'?(?<![\s<])\<\<(?!\<)'.TEXY_LINK.'??()#Uu',
            'phrase/quote'
        );

        // acronym/abbr "et al."((and others))
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!\")\"(?!\s)([^\"\r\n]+)'.TEXY_MODIFIER.'?(?<!\s)\"(?!\")\(\((.+)\)\)()#Uu',
            'phrase/acronym'
        );

        // acronym/abbr NATO((North Atlantic Treaty Organisation))
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(?<!['.TEXY_CHAR.'])(['.TEXY_CHAR.']{2,})()\(\((.+)\)\)#Uu',
            'phrase/acronym-alt'
        );

        // ''notexy''
        $tx->registerLinePattern(
            array($this, 'patternNoTexy'),
            '#(?<!\')\'\'(?![\s\'])([^'.TEXY_MARK.'\r\n]*)(?<![\s\'])\'\'(?!\')()#Uu',
            'phrase/notexy'
        );

        // `code`
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#\`(\S[^'.TEXY_MARK.'\r\n]*)'.TEXY_MODIFIER.'?(?<!\s)\`'.TEXY_LINK.'??()#Uu',
            'phrase/code'
        );


        // ....:LINK
        $tx->registerLinePattern(
            array($this, 'patternPhrase'),
            '#(['.TEXY_CHAR.'0-9@\#$%&.,_-]+)()(?=:\[)'.TEXY_LINK.'()#Uu',
            'phrase/quicklink'
        );

        // `=samp (back compatibility)
        $tx->registerBlockPattern(
            array($this, 'patternCodeSwitch'),
            '#^`=(none|code|kbd|samp|var|span)$#mUi',
            'deprecated/codeswitch'
        );

    }



    /**
     * Callback for: **.... .(title)[class]{style}**:LINK
     *
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternPhrase($parser, $matches, $phrase)
    {
        list(, $mContent, $mMod, $mLink) = $matches;
        //    [1] => **
        //    [2] => ...
        //    [3] => .(title)[class]{style}
        //    [4] => LINK

        $tx = $this->texy;
        $mod = new TexyModifier($mMod);
        $link = NULL;

        $parser->again = $phrase !== 'phrase/code' && $phrase !== 'phrase/quicklink';

        if ($phrase === 'phrase/span' || $phrase === 'phrase/span-alt') {
            if ($mLink == NULL) {
                if (!$mMod) return FALSE; // means "..."
            } else {
                $link = $tx->linkModule->factoryLink($mLink, $mMod, $mContent);
            }

        } elseif ($phrase === 'phrase/acronym' || $phrase === 'phrase/acronym-alt') {
            $mod->title = trim(Texy::unescapeHtml($mLink));

        } elseif ($phrase === 'phrase/quote') {
            $mod->cite = $tx->quoteModule->citeLink($mLink);

        } elseif ($mLink != NULL) {
            $link = $tx->linkModule->factoryLink($mLink, NULL, $mContent);
        }

        // event wrapper
        if (is_callable(array($tx->handler, 'phrase'))) {
            $res = $tx->handler->phrase($parser, $phrase, $mContent, $mod, $link);
            if ($res !== Texy::PROCEED) return $res;
        }

        return $this->solve($phrase, $mContent, $mod, $link);
    }



    /**
     * Callback for: any^2  any_2
     *
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternSupSub($parser, $matches, $phrase)
    {
        list(, $mContent) = $matches;
        $mod = new TexyModifier();
        $link = NULL;

        // event wrapper
        if (is_callable(array($this->texy->handler, 'phrase'))) {
            $res = $this->texy->handler->phrase($parser, $phrase, $mContent, $mod, $link);
            if ($res !== Texy::PROCEED) return $res;
        }

        return $this->solve($phrase, $mContent, $mod, $link);
    }



    /**
     * Finish invocation
     *
     * @param string
     * @param string
     * @param TexyModifier
     * @param TexyLink
     * @return TexyHtml
     */
    public function solve($phrase, $content, $mod, $link)
    {
        $tx = $this->texy;

        $tag = isset($this->tags[$phrase]) ? $this->tags[$phrase] : NULL;

        if ($tag === 'a')
            $tag = $link && $this->linksAllowed ? NULL : 'span';

        if ($phrase === 'phrase/code')
            $el = $tx->protect(Texy::escapeHtml($content), Texy::CONTENT_TEXTUAL);
        else
            $el = $content;

        if ($phrase === 'phrase/strong+em') {
            $el = TexyHtml::el($this->tags['phrase/em'])->addChild($el);
            $tag = $this->tags['phrase/strong'];
        }

        if ($tag) {
            $el = TexyHtml::el($tag)->addChild($el);
            $mod->decorate($tx, $el);
        }

        if ($tag === 'q') $el['cite'] = $mod->cite;

        if ($link) return $tx->linkModule->solve($link, $el);

        return $el;
    }



    /**
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return string
     */
    public function patternNoTexy($parser, $matches)
    {
        list(, $mContent) = $matches;
        return $this->texy->protect(Texy::escapeHtml($mContent), Texy::CONTENT_TEXTUAL);
    }



    /**
     * Callback for: `=code
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return string
     */
    public function patternCodeSwitch($parser, $matches)
    {
        $this->tags['phrase/code'] = $matches[1];
        return "\n";
    }

} // TexyPhraseModule
