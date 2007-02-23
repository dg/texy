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
    protected $allow = array(
        'PhraseStrongEm',   // ***strong+emphasis***
        'PhraseStrong',     // **strong**
        'PhraseEm',         // *emphasis*
        'PhraseIns',        // ++inserted++
        'PhraseDel',        // --deleted--
        'PhraseSup',        // ^^superscript^^
        'PhraseSub',        // __subscript__
        'PhraseSpan',       // "span"
        'PhraseSpanAlt',    // ~span~
        'PhraseCite',       // ~~cite~~
        'PhraseAcronym',    // "acro nym"()
        'PhraseAcronymAlt', // acronym()
        'PhraseCode',       // `code`
        'PhraseNoTexy',     // ``....``
        'PhraseQuote',      // >>quote<<
        'PhraseCodeSwitch', // `=...
    );

    public $codeTag = 'code';


    public function init()
    {
        $tx = $this->texy;
/*
        $tx->registerLinePattern(
            $this,
            'processPhrase2',
            '#((?>[*+^_"~-]+?))(\S.*)'.TEXY_MODIFIER.'?(?<!\ )\\1'.TEXY_LINK.'??()#U'
        );
*/

        // strong & em speciality *** ... *** !!! its not good!
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\*)\*\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*\*(?!\*)()'.TEXY_LINK.'??()#U',
            'PhraseStrongEm'
        );

        // **strong**
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\*)\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*(?!\*)'.TEXY_LINK.'??()#U',
            'PhraseStrong'
        );

        // *emphasis*
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\*)\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*(?!\*)'.TEXY_LINK.'??()#U',
            'PhraseEm'
        );

        // ++inserted++
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\+)\+\+(?!\ |\+)(.+)'.TEXY_MODIFIER.'?(?<!\ |\+)\+\+(?!\+)()#U',
            'PhraseIns'
        );

        // --deleted--
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\-)\-\-(?!\ |\-)(.+)'.TEXY_MODIFIER.'?(?<!\ |\-)\-\-(?!\-)()#U',
            'PhraseDel'
        );

        // ^^superscript^^
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\^)\^\^(?!\ |\^)(.+)'.TEXY_MODIFIER.'?(?<!\ |\^)\^\^(?!\^)()#U',
            'PhraseSup'
        );

        // __subscript__
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\_)\_\_(?!\ |\_)(.+)'.TEXY_MODIFIER.'?(?<!\ |\_)\_\_(?!\_)()#U',
            'PhraseSub'
        );

        // "span"
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")'.TEXY_LINK.'??()#U',
            'PhraseSpan'
        );
//      $tx->registerLinePattern($this, 'processPhrase',  '#()(?<!\")\"(?!\ )(?:.|(?R))+'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")'.TEXY_LINK.'?()#', 'test');

        // ~alternative span~
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\~)\~(?!\ )([^\~]+)'.TEXY_MODIFIER.'?(?<!\ )\~(?!\~)'.TEXY_LINK.'??()#U',
            'PhraseSpanAlt'
        );

        // ~~cite~~
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\~)\~\~(?!\ |\~)(.+)'.TEXY_MODIFIER.'?(?<!\ |\~)\~\~(?!\~)'.TEXY_LINK.'??()#U',
            'PhraseCite'
        );

        // >>quote<<
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\>)\>\>(?!\ |\>)(.+)'.TEXY_MODIFIER.'?(?<!\ |\<)\<\<(?!\<)'.TEXY_LINK.'??()#U',
            'PhraseQuote'
        );

        // acronym/abbr "et al."((and others))
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")\(\((.+)\)\)()#U',
            'PhraseAcronym'
        );

        // acronym/abbr NATO((North Atlantic Treaty Organisation))
        $tx->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!['.TEXY_CHAR.'])(['.TEXY_CHAR.']{2,})()()()\(\((.+)\)\)#Uu',
            'PhraseAcronymAlt'
        );

        // ``protected`` (experimental, dont use)
        $tx->registerLinePattern(
            $this,
            'processProtect',
            '#\`\`(\S[^'.TEXY_MARK.']*)(?<!\ )\`\`()#U',
            'PhraseNoTexy'
        );

        // `code`
        $tx->registerLinePattern(
            $this,
            'processCode',
            '#\`(\S[^'.TEXY_MARK.']*)'.TEXY_MODIFIER.'?(?<!\ )\`()#U',
            'PhraseCode'
        );

        // `=samp
        $tx->registerBlockPattern(
            $this,
            'processBlock',
            '#^`=(none|code|kbd|samp|var|span)$#mUi',
            'PhraseCodeSwitch'
        );
    }



    /**
     * Callback function: **.... .(title)[class]{style}**:LINK
     * @return string
     */
    public function processPhrase($parser, $matches, $name)
    {
        list($match, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
        if ($mContent == NULL) {
            preg_match('#^(.)+(.+)'.TEXY_MODIFIER.'?\\1+()$#U', $match, $matches);
            list($match, , $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
        }
        //    [1] => **
        //    [2] => ...
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => LINK

        $tx = $this->texy;

        static $_tags = array(
            'PhraseStrongEm' => array('em', 'strong'),
            'PhraseStrong' => 'strong',
            'PhraseEm' => 'em',
            'PhraseIns' => 'ins',
            'PhraseDel' => 'del',
            'PhraseSup' => 'sup',
            'PhraseSub' => 'sub',
            'PhraseSpan' => 'span',
            'PhraseSpanAlt' => 'span',
            'PhraseCite' => 'cite',
            'PhraseAcronym' => 'acronym',
            'PhraseAcronymAlt' => 'acronym',
            'PhraseQuote' => 'q',
            'PhraseCode'  => 'code',
        );
        $tags = $_tags[$name];

        if (($tags === 'span') && $mLink) $tags = NULL; // eliminate wasted spans, use <a ..> instead
        if (($tags === 'span') && !$mMod1 && !$mMod2 && !$mMod3) return $match; // don't use wasted spans...
        $tags = (array) $tags;
        $tag = $el = NULL;

        $content = $mContent;
        $modifier = new TexyModifier($tx);
        $modifier->setProperties($mMod1, $mMod2, $mMod3);

        foreach ($tags as $tag) {
            if ($modifier) {
                if ($tag === 'acronym' || $tag === 'abbr') {
                    $modifier->title = $mLink;
                    $mMod1 = $mMod2 = $mMod3 = $mLink = NULL;
                }
                $el = $modifier->generate($tag);
                $modifier = NULL;
            } else {
                $el = TexyHtmlEl::el($tag);
            }

            if ($mLink && $tag === 'q') { // cite
                $el->cite = $tx->quoteModule->citeLink($mLink)->asURL();
            }

            $content = $el->startMark($tx) . $content . $el->endMark($tx);
        }

        if ($mLink && $tag !== 'q') {
            $el = $tx->linkModule->factory($mLink, $mMod1, $mMod2, $mMod3, $mContent);
            $content = $el->startMark($tx) . $content . $el->endMark($tx);
        }

        return $content;
    }



    /**
     * Callback function `=code
     */
    public function processBlock($parser, $matches)
    {
        list(, $mTag) = $matches;
        //    [1] => ...

        $mTag = strtolower($mTag);
        $this->codeTag = $mTag === 'none' ? '' : $mTag;
    }



    /**
     * Callback function: `.... .(title)[class]{style}`
     * @return string
     */
    public function processCode($parser, $matches)
    {
        list(, $mContent, $mMod1, $mMod2, $mMod3) = $matches;
        //    [1] => ...
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}

        if ($mMod1 || $mMod2 || $mMod3) {
            $mod = new TexyModifier($this->texy);
            $mod->setProperties($mMod1, $mMod2, $mMod3);
            $el = $mod->generate( $this->codeTag );
        } else {
            $el = TexyHtmlEl::el( $this->codeTag );
        }

        return $this->texy->mark(
            $el->startTag() . htmlSpecialChars($mContent, ENT_NOQUOTES) . $el->endTag(),
            Texy::CONTENT_TEXTUAL
        );
    }



    /**
     * User callback - PROTECT PHRASE
     * @return string
     */
    public function processProtect($parser, $matches)
    {
        list(, $mContent) = $matches;
        return $this->texy->mark(htmlSpecialChars($mContent, ENT_NOQUOTES), Texy::CONTENT_TEXTUAL);
    }

} // TexyPhraseModule
