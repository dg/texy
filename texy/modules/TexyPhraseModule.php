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
        'Phrase.strongEm',   // ***strong+emphasis***
        'Phrase.strong',     // **strong**
        'Phrase.em',         // *emphasis*
        'Phrase.ins',        // ++inserted++
        'Phrase.del',        // --deleted--
        'Phrase.sup',        // ^^superscript^^
        'Phrase.sub',        // __subscript__
        'Phrase.span',       // "span"
        'Phrase.span',       // ~span~
        'Phrase.cite',       // ~~cite~~
        'Phrase.acronym',    // "acro nym"()
        'Phrase.acronymAlt', // acronym()
        'Phrase.code',       // `code`
        'Phrase.notexy',     // ``....``
        'Phrase.quote',      // >>quote<<
        'Phrase.codeSwitch', // `=...
    );

    protected $tags = array(
        'Phrase.strongEm' => array('em', 'strong'),
        'Phrase.strong' => 'strong',
        'Phrase.em' => 'em',
        'Phrase.ins' => 'ins',
        'Phrase.del' => 'del',
        'Phrase.sup' => 'sup',
        'Phrase.sub' => 'sub',
        'Phrase.span' => 'span',
        'Phrase.spanAlt' => 'span',
        'Phrase.cite' => 'cite',
        'Phrase.acronym' => 'acronym',
        'Phrase.acronymAlt' => 'acronym',
        'Phrase.quote' => 'q',
        'Phrase.code'  => 'code',
    );



    public function init()
    {
/*
        $this->texy->registerLinePattern(
            $this,
            'processPhrase2',
            '#((?>[*+^_"~-]+?))(\S.*)'.TEXY_MODIFIER.'?(?<!\ )\\1'.TEXY_LINK.'??()#U'
        );
*/

        // strong & em speciality *** ... *** !!! its not good!
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\*)\*\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*\*(?!\*)()'.TEXY_LINK.'??()#U',
            'Phrase.strongEm'
        );

        // **strong**
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\*)\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*(?!\*)'.TEXY_LINK.'??()#U',
            'Phrase.strong'
        );

        // *emphasis*
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\*)\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*(?!\*)'.TEXY_LINK.'??()#U',
            'Phrase.em'
        );

        // ++inserted++
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\+)\+\+(?!\ |\+)(.+)'.TEXY_MODIFIER.'?(?<!\ |\+)\+\+(?!\+)()#U',
            'Phrase.ins'
        );

        // --deleted--
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\-)\-\-(?!\ |\-)(.+)'.TEXY_MODIFIER.'?(?<!\ |\-)\-\-(?!\-)()#U',
            'Phrase.del'
        );

        // ^^superscript^^
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\^)\^\^(?!\ |\^)(.+)'.TEXY_MODIFIER.'?(?<!\ |\^)\^\^(?!\^)()#U',
            'Phrase.sup'
        );

        // __subscript__
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\_)\_\_(?!\ |\_)(.+)'.TEXY_MODIFIER.'?(?<!\ |\_)\_\_(?!\_)()#U',
            'Phrase.sub'
        );

        // "span"
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")'.TEXY_LINK.'??()#U',
            'Phrase.span'
        );
//      $this->texy->registerLinePattern($this, 'processPhrase',  '#()(?<!\")\"(?!\ )(?:.|(?R))+'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")'.TEXY_LINK.'?()#', 'test');

        // ~alternative span~
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\~)\~(?!\ )([^\~]+)'.TEXY_MODIFIER.'?(?<!\ )\~(?!\~)'.TEXY_LINK.'??()#U',
            'Phrase.spanAlt'
        );

        // ~~cite~~
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\~)\~\~(?!\ |\~)(.+)'.TEXY_MODIFIER.'?(?<!\ |\~)\~\~(?!\~)'.TEXY_LINK.'??()#U',
            'Phrase.cite'
        );

        // >>quote<<
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\>)\>\>(?!\ |\>)(.+)'.TEXY_MODIFIER.'?(?<!\ |\<)\<\<(?!\<)'.TEXY_LINK.'??()#U',
            'Phrase.quote'
        );

        // acronym/abbr "et al."((and others))
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")\(\((.+)\)\)()#U',
            'Phrase.acronym'
        );

        // acronym/abbr NATO((North Atlantic Treaty Organisation))
        $this->texy->registerLinePattern(
            $this,
            'processPhrase',
            '#(?<!['.TEXY_CHAR.'])(['.TEXY_CHAR.']{2,})()()()\(\((.+)\)\)#Uu',
            'Phrase.acronymAlt'
        );

        // ``protected`` (experimental, dont use)
        $this->texy->registerLinePattern(
            $this,
            'processProtect',
            '#\`\`(\S[^'.TEXY_MARK.']*)(?<!\ )\`\`()#U',
            'Phrase.notexy'
        );

        // `code`
        $this->texy->registerLinePattern(
            $this,
            'processCode',
            '#\`(\S[^'.TEXY_MARK.']*)'.TEXY_MODIFIER.'?(?<!\ )\`()#U',
            'Phrase.code'
        );

        // `=samp
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^`=(none|code|kbd|samp|var|span)$#mUi',
            'Phrase.codeSwitch'
        );
    }




    /**
     * Callback function: **.... .(title)[class]{style}**
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

        $tags = $this->tags[$name];

        if (($tags === 'span') && $mLink) $tags = ''; // eliminate wasted spans, use <a ..> instead
        if (($tags === 'span') && !$mMod1 && !$mMod2 && !$mMod3) return $match; // don't use wasted spans...
        $tags = (array) $tags;
        $el = NULL;

        $modifier = new TexyModifier($this->texy);
        $modifier->setProperties($mMod1, $mMod2, $mMod3);

        foreach ($tags as $tag) {
            if ($modifier) {
                if ($tag === 'acronym' || $tag === 'abbr') { $modifier->title = $mLink; $mLink=''; }
                $el = $modifier->generate($tag);
                $modifier = NULL;
            } else {
                $el = TexyHtml::el($tag);
            }

            if ($mLink && $tag === 'q') { // cite
                $cite = new TexyLink($this->texy, $mLink, TexyLink::DIRECT); //!!!
                $el->cite = $cite->asURL();
            }

            $keyOpen  = $el->startMark($this->texy);
            $keyClose = $el->endMark($this->texy);
            $mContent = $keyOpen . $mContent . $keyClose;
        }


        if ($mLink && $tag !== 'q') { // cite
            $link = new TexyLink($this->texy, $mLink, TexyLink::DIRECT); //!!!
            $el = TexyHtml::el('a');
            $this->texy->summary['links'][] = $el->href = $link->asURL();

            // rel="nofollow"
            if ($link->isAbsolute() && $this->texy->linkModule->forceNoFollow) $el->rel = 'nofollow'; // TODO: append, not replace

            // email on click
            if ($link->isEmail()) $el->onclick = $this->texy->linkModule->emailOnClick;

            $keyOpen  = $el->startMark($this->texy);
            $keyClose = $el->endMark($this->texy);
            $mContent = $keyOpen . $mContent . $keyClose;
        }


        return $mContent;
    }





    /**
     * Callback function `=code
     */
    public function processBlock($parser, $matches)
    {
        list(, $mTag) = $matches;
        //    [1] => ...

        $mTag = strtolower($mTag);
        if ($mTag === 'none') $mTag = '';
        $this->tags['Phrase.code'] = $mTag;
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
            $el = $mod->generate( $this->tags['Phrase.code'] );
        } else {
            $el = TexyHtml::el( $this->tags['Phrase.code'] );
        }

        return $this->texy->mark(
            $el->startTag() . htmlSpecialChars($mContent) . $el->endTag(),
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
        return $this->texy->mark(htmlSpecialChars($mContent), Texy::CONTENT_TEXTUAL);
    }




} // TexyPhraseModule
