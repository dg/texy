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
        'phraseStrongEm',   // ***strong+emphasis***
        'phraseStrong',     // **strong**
        'phraseEm',         // *emphasis*
        'phraseIns',        // ++inserted++
        'phraseDel',        // --deleted--
        'phraseSup',        // ^^superscript^^
        'phraseSub',        // __subscript__
        'phraseSpan',       // "span"
        'phraseSpanAlt',    // ~span~
        'phraseCite',       // ~~cite~~
        'phraseAcronym',    // "acro nym"()
        'phraseAcronymAlt', // acronym()
        'phraseCode',       // `code`
        'phraseNoTexy',     // ``....``
        'phraseQuote',      // >>quote<<
        'phraseCodeSwitch', // `=...
    );

    public $codeTag = 'code';


    public function init()
    {
        $tx = $this->texy;
/*
        $tx->registerLinePattern(
            array($this, 'processPhrase2'),
            '#((?>[*+^_"~-]+?))(\S.*)'.TEXY_MODIFIER.'?(?<!\ )\\1'.TEXY_LINK.'??()#U'
        );
*/

        // strong & em speciality *** ... *** !!! its not good!
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\*)\*\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*\*(?!\*)()'.TEXY_LINK.'??()#U',
            'phraseStrongEm'
        );

        // **strong**
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\*)\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*(?!\*)'.TEXY_LINK.'??()#U',
            'phraseStrong'
        );

        // *emphasis*
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\*)\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*(?!\*)'.TEXY_LINK.'??()#U',
            'phraseEm'
        );

        // ++inserted++
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\+)\+\+(?!\ |\+)(.+)'.TEXY_MODIFIER.'?(?<!\ |\+)\+\+(?!\+)()#U',
            'phraseIns'
        );

        // --deleted--
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\-)\-\-(?!\ |\-)(.+)'.TEXY_MODIFIER.'?(?<!\ |\-)\-\-(?!\-)()#U',
            'phraseDel'
        );

        // ^^superscript^^
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\^)\^\^(?!\ |\^)(.+)'.TEXY_MODIFIER.'?(?<!\ |\^)\^\^(?!\^)()#U',
            'phraseSup'
        );

        // __subscript__
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\_)\_\_(?!\ |\_)(.+)'.TEXY_MODIFIER.'?(?<!\ |\_)\_\_(?!\_)()#U',
            'phraseSub'
        );

        // "span"
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")'.TEXY_LINK.'??()#U',
            'phraseSpan'
        );
//      $tx->registerLinePattern($this, 'processPhrase',  '#()(?<!\")\"(?!\ )(?:.|(?R))+'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")'.TEXY_LINK.'?()#', 'test');

        // ~alternative span~
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\~)\~(?!\ )([^\~]+)'.TEXY_MODIFIER.'?(?<!\ )\~(?!\~)'.TEXY_LINK.'??()#U',
            'phraseSpanAlt'
        );

        // ~~cite~~
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\~)\~\~(?!\ |\~)(.+)'.TEXY_MODIFIER.'?(?<!\ |\~)\~\~(?!\~)'.TEXY_LINK.'??()#U',
            'phraseCite'
        );

        // >>quote<<
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\>)\>\>(?!\ |\>)(.+)'.TEXY_MODIFIER.'?(?<!\ |\<)\<\<(?!\<)'.TEXY_LINK.'??()#U',
            'phraseQuote'
        );

        // acronym/abbr "et al."((and others))
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")\(\((.+)\)\)()#U',
            'phraseAcronym'
        );

        // acronym/abbr NATO((North Atlantic Treaty Organisation))
        $tx->registerLinePattern(
            array($this, 'processPhrase'),
            '#(?<!['.TEXY_CHAR.'])(['.TEXY_CHAR.']{2,})()()()\(\((.+)\)\)#Uu',
            'phraseAcronymAlt'
        );

        // ``protected`` (experimental, dont use)
        $tx->registerLinePattern(
            array($this, 'processProtect'),
            '#\`\`(\S[^'.TEXY_MARK.']*)(?<!\ )\`\`()#U',
            'phraseNoTexy'
        );

        // `code`
        $tx->registerLinePattern(
            array($this, 'processCode'),
            '#\`(\S[^'.TEXY_MARK.']*)'.TEXY_MODIFIER.'?(?<!\ )\`()#U',
            'phraseCode'
        );

        // `=samp
        $tx->registerBlockPattern(
            array($this, 'processBlock'),
            '#^`=(none|code|kbd|samp|var|span)$#mUi',
            'phraseCodeSwitch'
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
            // ???
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
            'phraseStrongEm' => 'strong', // & em
            'phraseStrong' => 'strong',
            'phraseEm' => 'em',
            'phraseIns' => 'ins',
            'phraseDel' => 'del',
            'phraseSup' => 'sup',
            'phraseSub' => 'sub',
            'phraseSpan' => 'span',
            'phraseSpanAlt' => 'span',
            'phraseCite' => 'cite',
            'phraseAcronym' => 'acronym',
            'phraseAcronymAlt' => 'acronym',
            'phraseQuote' => 'q',
            'phraseCode'  => 'code',
        );

        $tag = $_tags[$name];
        if (($tag === 'span') && $mLink) $tag = NULL; // eliminate wasted spans, use <a ..> instead
        elseif (($tag === 'span') && !$mMod1 && !$mMod2 && !$mMod3) return FALSE; // don't use wasted spans...

        $content = $mContent;
        if ($name === 'phraseStrongEm') {
            $content = TexyHtml::el('em')->addChild($content)->toText($tx);
        }

        $modifier = new TexyModifier;
        $modifier->setProperties($mMod1, $mMod2, $mMod3);
        if ($tag === 'acronym' || $tag === 'abbr') {
            $modifier->title = trim(Texy::decode($mLink));
            $mLink = NULL;
        }
        $el = $modifier->generate($tx, $tag);

        if ($mLink && $tag === 'q') { // cite
            $el->cite = $tx->quoteModule->citeLink($mLink);
            $mLink = NULL;
        }

        $el->addChild($content);

        if (is_callable(array($tx->handler, 'phrase')))
            $tx->handler->phrase($tx, $name, $modifier, $mLink, $el);

        $content = $el->toText($tx);

        if ($mLink) {
            $req = $tx->linkModule->parse($mLink, $mMod1, $mMod2, $mMod3, $mContent);
            $el = $tx->linkModule->factory($req)->addChild($content);

            if (is_callable(array($tx->handler, 'link')))
                $tx->handler->link($tx, $req, $el);

            $content = $el->toText($tx);
        }

        $parser->again = TRUE;
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

        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3);
        $el = $mod->generate($this->texy,  $this->codeTag );

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
