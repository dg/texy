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
 * PHRASES MODULE CLASS
 *
 *   **strong**
 *   *emphasis*
 *   ***strong+emphasis***
 *   ^^superscript^^
 *   __subscript__
 *   ++inserted++
 *   --deleted--
 *   ~~cite~~
 *   "span"
 *   ~span~
 *   `....`
 *   ``....``
 */
class TexyPhraseModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    public $codeHandler;  // function myUserFunc($element)
    public $handler;

    public $allowed = array(
        '***'  => 'strong em',
        '**'  => 'strong',
        '*'   => 'em',
        '++'  => 'ins',
        '--'  => 'del',
        '^^'  => 'sup',
        '__'  => 'sub',
        '"'   => 'span',
        '~'   => 'span',
        '~~'  => 'cite',
        '""()'=> 'acronym',
        '()'  => 'acronym',
        '`'   => 'code',
        '``'  => '',
        '>>'  => 'q',
    );




    /**
     * Module initialization.
     */
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
        if (@$this->allowed['***'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\*)\*\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*\*(?!\*)()'.TEXY_LINK.'??()#U',
                $this->allowed['***']
            );

        // **strong**
        if (@$this->allowed['**'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\*)\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*(?!\*)'.TEXY_LINK.'??()#U',
                $this->allowed['**']
            );

        // *emphasis*
        if (@$this->allowed['*'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\*)\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*(?!\*)'.TEXY_LINK.'??()#U',
                $this->allowed['*']
            );

        // ++inserted++
        if (@$this->allowed['++'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\+)\+\+(?!\ |\+)(.+)'.TEXY_MODIFIER.'?(?<!\ |\+)\+\+(?!\+)()#U',
                $this->allowed['++']
            );

        // --deleted--
        if (@$this->allowed['--'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\-)\-\-(?!\ |\-)(.+)'.TEXY_MODIFIER.'?(?<!\ |\-)\-\-(?!\-)()#U',
                $this->allowed['--']
            );

        // ^^superscript^^
        if (@$this->allowed['^^'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\^)\^\^(?!\ |\^)(.+)'.TEXY_MODIFIER.'?(?<!\ |\^)\^\^(?!\^)()#U',
                $this->allowed['^^']
            );

        // __subscript__
        if (@$this->allowed['__'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\_)\_\_(?!\ |\_)(.+)'.TEXY_MODIFIER.'?(?<!\ |\_)\_\_(?!\_)()#U',
                $this->allowed['__']
            );

        // "span"
        if (@$this->allowed['"'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")'.TEXY_LINK.'??()#U',
                $this->allowed['"']
            );
//      $this->texy->registerLinePattern($this, 'processPhrase',  '#()(?<!\")\"(?!\ )(?:.|(?R))+'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")'.TEXY_LINK.'?()#',         $this->allowed['"']);

        // ~alternative span~
        if (@$this->allowed['~'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\~)\~(?!\ )([^\~]+)'.TEXY_MODIFIER.'?(?<!\ )\~(?!\~)'.TEXY_LINK.'??()#U',
                $this->allowed['~']
            );

        // ~~cite~~
        if (@$this->allowed['~~'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\~)\~\~(?!\ |\~)(.+)'.TEXY_MODIFIER.'?(?<!\ |\~)\~\~(?!\~)'.TEXY_LINK.'??()#U',
                $this->allowed['~~']
            );

        // >>quote<<
        if (@$this->allowed['>>'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\>)\>\>(?!\ |\>)(.+)'.TEXY_MODIFIER.'?(?<!\ |\<)\<\<(?!\<)'.TEXY_LINK.'??()#U',
                $this->allowed['>>']
            );

        if (@$this->allowed['""()'] !== FALSE)
            // acronym/abbr "et al."((and others))
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")\(\((.+)\)\)()#U',
                $this->allowed['""()']
            );

        if (@$this->allowed['()'] !== FALSE)
            // acronym/abbr NATO((North Atlantic Treaty Organisation))
            $this->texy->registerLinePattern(
                $this,
                'processPhrase',
                '#(?<!['.TEXY_CHAR.'])(['.TEXY_CHAR.']{2,})()()()\(\((.+)\)\)#Uu',
                $this->allowed['()']
            );

        // ``protected`` (experimental, dont use)
        if (@$this->allowed['``'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processProtect',
                '#\`\`(\S[^'.TEXY_HASH.']*)(?<!\ )\`\`()#U',
                FALSE
            );

        // `code`
        if (@$this->allowed['`'] !== FALSE)
            $this->texy->registerLinePattern(
                $this,
                'processCode',
                '#\`(\S[^'.TEXY_HASH.']*)'.TEXY_MODIFIER.'?(?<!\ )\`()#U'
            );

        // `=samp
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^`=(none|code|kbd|samp|var|span)$#mUi'
        );
    }




    /**
     * Callback function: **.... .(title)[class]{style}**
     * @return string
     */
    public function processPhrase($parser, $matches, $tags)
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

        //if (!isset($this->allowed[$mPhrase])) return $match;
        //$tags = $this->allowed[$mPhrase];

        if (($tags === 'span') && $mLink) $tags = ''; // eliminate wasted spans, use <a ..> instead
        if (($tags === 'span') && !$mMod1 && !$mMod2 && !$mMod3) return $match; // don't use wasted spans...
        $tags = array_reverse(explode(' ', $tags));
        $el = NULL;

        $modifier = new TexyModifier($this->texy);
        $modifier->setProperties($mMod1, $mMod2, $mMod3);

        foreach ($tags as $tag) {
            $el = TexyHtml::el($tag);
            if ($modifier) {
                if ($tag === 'acronym' || $tag === 'abbr') { $modifier->title = $mLink; $mLink=''; }
                $modifier->decorate($el);
                $modifier = NULL;
            }

            if ($mLink && $tag === 'q') { // cite !!!
                $link = new TexyUrl($this->texy);
                $link->set($mLink);
            	$el->cite = $link->asURL();
            }

            $keyOpen  = $this->texy->hash($el->startTag(), Texy::CONTENT_NONE);
            $keyClose = $this->texy->hash($el->endTag(), Texy::CONTENT_NONE);
            $mContent = $keyOpen . $mContent . $keyClose;
        }


        if ($mLink && $tag !== 'q') {
            $modifier = new TexyModifier($this->texy);
            //$link = $this->texy->linkModule->factoryLink();
            $el = $this->texy->linkModule->factory($mLink, $mContent, $modifier);

            $keyOpen  = $this->texy->hash($el->startTag(), Texy::CONTENT_NONE);
            $keyClose = $this->texy->hash($el->endTag(), Texy::CONTENT_NONE);
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

        $this->allowed['`'] = strtolower($mTag);
        if ($this->allowed['`'] === 'none') $this->allowed['`'] = '';
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

        $texy = $this->texy;
        $el = new TexyTextualElement($texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3);
        $el->content = $mContent;
        $el->tag = $this->allowed['`'];

        if ($this->codeHandler)
            if (call_user_func_array($this->codeHandler, array($el, 'code')) === FALSE) return '';

        return $this->texy->hash($el->__toString(), Texy::CONTENT_TEXTUAL);
    }








    /**
     * User callback - PROTECT PHRASE
     * @return string
     */
    public function processProtect($parser, $matches)
    {
        list(, $mContent) = $matches;

        $el = new TexyTextualElement($this->texy);
        $el->content = Texy::freezeSpaces($mContent);

        return $this->texy->hash($el->__toString(), Texy::CONTENT_TEXTUAL);
    }




} // TexyPhraseModule
