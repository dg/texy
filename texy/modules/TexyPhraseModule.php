<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();



/**
 * Phrases module
 */
class TexyPhraseModule extends TexyModule
{
    protected $default = array(
        'phraseStrongEm' => TRUE,  // ***strong+emphasis***
        'phraseStrong' => TRUE,     // **strong**
        'phraseEm' => TRUE,         // //emphasis//
        'phraseEmAlt' => TRUE,      // *emphasis*
        'phraseSpan' => TRUE,       // "span"
        'phraseSpanAlt' => TRUE,    // ~span~
        'phraseAcronym' => TRUE,    // "acro nym"((...))
        'phraseAcronymAlt' => TRUE, // acronym((...))
        'phraseCode' => TRUE,       // `code`
        'phraseNoTexy' => TRUE,     // ''....''
        'phraseQuote' => TRUE,      // >>quote<<:...
        'phraseQuickLink' => TRUE,  // ....:LINK

        'phraseHasLink' => TRUE,    // are links allowed?

        'phraseIns' => FALSE,       // ++inserted++
        'phraseDel' => FALSE,       // --deleted--
        'phraseSup' => FALSE,       // ^^superscript^^
        'phraseSub' => FALSE,       // __subscript__
        'phraseCite' => FALSE,      // ~~cite~~

        // back compatibility
        'phraseCodeSwitch' => FALSE,// `=...
    );

    public $tags = array(
        'phraseStrong' => 'strong', // or 'b'
        'phraseEm' => 'em', // or 'i'
        'phraseEmAlt' => 'em',
        'phraseIns' => 'ins',
        'phraseDel' => 'del',
        'phraseSup' => 'sup',
        'phraseSub' => 'sub',
        'phraseSpan' => 'a',
        'phraseSpanAlt' => 'a',
        'phraseCite' => 'cite',
        'phraseAcronym' => 'acronym',
        'phraseAcronymAlt' => 'acronym',
        'phraseCode'  => 'code',
        'phraseQuote' => 'q',
        'phraseQuickLink' => 'a',
    );




    public function init()
    {
        $tx = $this->texy;

        // strong & em speciality
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\*)\*\*\*(?!\s|\*)(.+)'.TEXY_MODIFIER.'?(?<!\s|\*)\*\*\*(?!\*)()'.TEXY_LINK.'??()#Us',
            'phraseStrongEm'
        );

        // **strong**
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\*)\*\*(?!\s|\*)(.+)'.TEXY_MODIFIER.'?(?<!\s|\*)\*\*(?!\*)'.TEXY_LINK.'??()#Us',
            'phraseStrong'
        );

        // //emphasis//
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<![/:])\/\/(?!\s|\/)(.+)'.TEXY_MODIFIER.'?(?<!\s|\/)\/\/(?!\/)'.TEXY_LINK.'??()#Us',
            'phraseEm'
        );

        // *emphasisAlt*
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\*)\*(?!\s|\*)(.+)'.TEXY_MODIFIER.'?(?<!\s|\*)\*(?!\*)'.TEXY_LINK.'??()#Us',
            'phraseEmAlt'
        );

        // ++inserted++
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\+)\+\+(?!\s|\+)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\+)\+\+(?!\+)()#U',
            'phraseIns'
        );

        // --deleted--
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\-)\-\-(?!\s|\-)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\-)\-\-(?!\-)()#U',
            'phraseDel'
        );

        // ^^superscript^^
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\^)\^\^(?!\s|\^)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\^)\^\^(?!\^)()#U',
            'phraseSup'
        );

        // __subscript__
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\_)\_\_(?!\s|\_)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\_)\_\_(?!\_)()#U',
            'phraseSub'
        );

        // "span"
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\")\"(?!\s)([^\"\r]+)'.TEXY_MODIFIER.'?(?<!\s)\"(?!\")'.TEXY_LINK.'??()#U',
            'phraseSpan'
        );

        // ~alternative span~
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\~)\~(?!\s)([^\~\r]+)'.TEXY_MODIFIER.'?(?<!\s)\~(?!\~)'.TEXY_LINK.'??()#U',
            'phraseSpanAlt'
        );

        // ~~cite~~
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\~)\~\~(?!\s|\~)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\~)\~\~(?!\~)'.TEXY_LINK.'??()#U',
            'phraseCite'
        );

        // >>quote<<
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\>)\>\>(?!\s|\>)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\<)\<\<(?!\<)'.TEXY_LINK.'??()#U',
            'phraseQuote'
        );

        // acronym/abbr "et al."((and others))
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\")\"(?!\s)([^\"\r\n]+)'.TEXY_MODIFIER.'?(?<!\s)\"(?!\")\(\((.+)\)\)()#U',
            'phraseAcronym'
        );

        // acronym/abbr NATO((North Atlantic Treaty Organisation))
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!['.TEXY_CHAR.'])(['.TEXY_CHAR.']{2,})()()()\(\((.+)\)\)#Uu',
            'phraseAcronymAlt'
        );

        // ''notexy''
        $tx->registerLinePattern(
            array($this, 'processNoTexy'),
            '#(?<!\')\'\'(?!\s|\')([^'.TEXY_MARK.'\r\n]*)(?<!\s|\')\'\'(?!\')()#U',
            'phraseNoTexy'
        );

        // `code`
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#\`(\S[^'.TEXY_MARK.'\r\n]*)'.TEXY_MODIFIER.'?(?<!\s)\`()#U',
            'phraseCode'
        );


        // ....:LINK
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(['.TEXY_CHAR.'0-9@\#$%&.,_-]+)()()()(?=:\[)'.TEXY_LINK.'()#Uu',
            'phraseQuickLink'
        );

        // `=samp (back compatibility)
        $tx->registerBlockPattern(
            array($this, 'processBlock'),
            '#^`=(none|code|kbd|samp|var|span)$#mUi',
            'phraseCodeSwitch'
        );

/*
        $tx->registerLinePattern(
            array($this, 'processPhrase2'),
            '#((?>[*+^_"~-]+?))(\S.*)'.TEXY_MODIFIER.'?(?<!\s)\\1'.TEXY_LINK.'??()#U'
        );
*/
    }



    /**
     * Callback function: **.... .(title)[class]{style}**:LINK
     * @return string
     */
    public function processPhrase($parser, $matches, $phrase)
    {
        list($match, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
        //    [1] => **
        //    [2] => ...
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => LINK

        $tx = $this->texy;
        $parser->again = $phrase !== 'phraseCode' && $phrase !== 'phraseQuickLink';

        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3);
        $link = $user = NULL;

        $tag = isset($this->tags[$phrase]) ? $this->tags[$phrase] : NULL;

        if ($tag === 'a') {
            if ($mLink == NULL) {
                if (!$mMod1 && !$mMod2 && !$mMod3) return FALSE; // means "..."
                $tag = 'span';
            } elseif (empty($tx->allowed['phraseHasLink'])) {
                return $mContent;
            } else {
                $link = $tx->linkModule->parse($mLink, $mMod1, $mMod2, $mMod3, $mContent);
                $tag = NULL;
            }

        } elseif ($tag === 'acronym') {
            $mod->title = trim(Texy::decode($mLink));

        } elseif ($tag === 'q') {
            $mod->cite = $tx->quoteModule->citeLink($mLink);

        } elseif ($mLink != NULL && !empty($tx->allowed['phraseHasLink'])) {
            $link = $tx->linkModule->parse($mLink, NULL, NULL, NULL, $mContent);
        }

        if (is_callable(array($tx->handler, 'phrase'))) {
            $el = $tx->handler->phrase($tx, $phrase, $mContent, $mod, $link, $user);
            if ($el) return $el;
        }

        if ($phrase === 'phraseCode')
            $el = $tx->protect(Texy::encode($mContent), Texy::CONTENT_TEXTUAL);
        else
            $el = $mContent;

        if ($phrase === 'phraseStrongEm') {
            $el = TexyHtml::el($this->tags['phraseEm'])->setContent($el);
            $tag = $this->tags['phraseStrong'];
        }

        if ($tag) {
            $el = TexyHtml::el($tag)->setContent($el);
            $mod->decorate($tx, $el);
        }

        if ($tag === 'q') $el->cite = $mod->cite;

        if ($link) $el = $tx->linkModule->factory($link)->setContent($el);

        if (is_callable(array($tx->handler, 'phrase2')))
            $tx->handler->phrase2($tx, $el, $phrase, $user);

        return $el;
    }



    /**
     */
    public function processNoTexy($parser, $matches)
    {
        list(, $mContent) = $matches;
        return $this->texy->protect(Texy::encode($mContent), Texy::CONTENT_TEXTUAL);
    }



    /**
     * Callback function `=code
     */
    public function processBlock($parser, $matches)
    {}

} // TexyPhraseModule
