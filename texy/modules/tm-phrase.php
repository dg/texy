<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    1.1 for PHP4 & PHP5 $Date$ $Revision$
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
class TexyPhraseModule extends TexyModule {
     var $allowed = array('***' => 'strong em',
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
                          );
     var $codeHandler;  // function &myUserFunc(&$element)




    /**
     * Module initialization.
     */
    function init()
    {

        // strong & em speciality *** ... *** !!! its not good!
        if (@$this->allowed['***'] !== false)
            $this->registerLinePattern('processPhrase',  '#(?<!\*)\*\*\*(?!\ |\*)(.+)<MODIFIER>?(?<!\ |\*)\*\*\*(?!\*)()<LINK>??()#U',   $this->allowed['***']);

        // **strong**
        if (@$this->allowed['**'] !== false)
            $this->registerLinePattern('processPhrase',  '#(?<!\*)\*\*(?!\ |\*)(.+)<MODIFIER>?(?<!\ |\*)\*\*(?!\*)<LINK>??()#U', $this->allowed['**']);

        // *emphasis*
        if (@$this->allowed['*'] !== false)
            $this->registerLinePattern('processPhrase',  '#(?<!\*)\*(?!\ |\*)(.+)<MODIFIER>?(?<!\ |\*)\*(?!\*)<LINK>??()#U',     $this->allowed['*']);

        // ++inserted++
        if (@$this->allowed['++'] !== false)
            $this->registerLinePattern('processPhrase',  '#(?<!\+)\+\+(?!\ |\+)(.+)<MODIFIER>?(?<!\ |\+)\+\+(?!\+)()#U',       $this->allowed['++']);

        // --deleted--
        if (@$this->allowed['--'] !== false)
            $this->registerLinePattern('processPhrase',  '#(?<!\-)\-\-(?!\ |\-)(.+)<MODIFIER>?(?<!\ |\-)\-\-(?!\-)()#U',       $this->allowed['--']);

        // ^^superscript^^
        if (@$this->allowed['^^'] !== false)
            $this->registerLinePattern('processPhrase',  '#(?<!\^)\^\^(?!\ |\^)(.+)<MODIFIER>?(?<!\ |\^)\^\^(?!\^)()#U',       $this->allowed['^^']);

        // __subscript__
        if (@$this->allowed['__'] !== false)
            $this->registerLinePattern('processPhrase',  '#(?<!\_)\_\_(?!\ |\_)(.+)<MODIFIER>?(?<!\ |\_)\_\_(?!\_)()#U',       $this->allowed['__']);

        // "span"
        if (@$this->allowed['"'] !== false)
            $this->registerLinePattern('processPhrase',  '#(?<!\")\"(?!\ )([^\"]+)<MODIFIER>?(?<!\ )\"(?!\")<LINK>??()#U',       $this->allowed['"']);
//      $this->registerLinePattern('processPhrase',  '#()(?<!\")\"(?!\ )(?:.|(?R))+<MODIFIER>?(?<!\ )\"(?!\")<LINK>?()#',         $this->allowed['"']);

        // ~alternative span~
        if (@$this->allowed['~'] !== false)
            $this->registerLinePattern('processPhrase',  '#(?<!\~)\~(?!\ )([^\~]+)<MODIFIER>?(?<!\ )\~(?!\~)<LINK>??()#U',       $this->allowed['~']);

        // ~~cite~~
        if (@$this->allowed['~~'] !== false)
            $this->registerLinePattern('processPhrase',  '#(?<!\~)\~\~(?!\ |\~)(.+)<MODIFIER>?(?<!\ |\~)\~\~(?!\~)<LINK>??()#U', $this->allowed['~~']);

        if (@$this->allowed['""()'] !== false)
            // acronym/abbr "et al."((and others))
            $this->registerLinePattern('processPhrase',  '#(?<!\")\"(?!\ )([^\"]+)<MODIFIER>?(?<!\ )\"(?!\")\(\((.+)\)\)()#U', $this->allowed['""()']);

        if (@$this->allowed['()'] !== false)
            // acronym/abbr NATO((North Atlantic Treaty Organisation))
            $this->registerLinePattern('processPhrase',  '#(?<![:CHAR:])([:CHAR:]{2,})()()()\(\((.+)\)\)#U<UTF>',              $this->allowed['()']);


        // ``protected`` (experimental, dont use)
        if (@$this->allowed['``'] !== false)
            $this->registerLinePattern('processProtect', '#\`\`(\S[^:HASH:]*)(?<!\ )\`\`()#U',                               false);

        // `code`
        if (@$this->allowed['`'] !== false)
            $this->registerLinePattern('processCode',    '#\`(\S[^:HASH:]*)<MODIFIER>?(?<!\ )\`()#U');

        // `=samp
        $this->registerBlockPattern('processBlock',    '#^`=(none|code|kbd|samp|var|span)$#mUi');
    }




    /**
     * Callback function: **.... .(title)[class]{style}**
     * @return string
     */
    function processPhrase(&$lineParser, &$matches, $tags)
    {
        list($match, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
        if ($mContent == null) {
            preg_match('#^(.)+(.+)'.TEXY_PATTERN_MODIFIER.'?\\1+()$#U', $match, $matches);
            list($match, $mDelim, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
        }
        //    [1] => ...
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}

        if (($tags == 'span') && $mLink) $tags = ''; // eliminate wasted spans, use <a ..> instead
        if (($tags == 'span') && !$mMod1 && !$mMod2 && !$mMod3) return $match; // don't use wasted spans...
        $tags = array_reverse(explode(' ', $tags));
        $el = null;

        foreach ($tags as $tag) {
            $el = &new TexyInlineTagElement($this->texy);
            $el->tag = $tag;
            if ($tag == 'acronym' || $tag == 'abbr') { $el->modifier->title = $mLink; $mLink=''; }

            $mContent = $lineParser->element->appendChild($el, $mContent);
        }

        if ($mLink) {
            $el = &new TexyLinkElement($this->texy);
            $el->setLinkRaw($mLink);
            $mContent = $lineParser->element->appendChild($el, $mContent);
        }

        if ($el)
            $el->modifier->setProperties($mMod1, $mMod2, $mMod3);


        return $mContent;
    }





    /**
     * Callback function `=code
     */
    function processBlock(&$blockParser, &$matches)
    {
        list($match, $mTag) = $matches;
        //    [1] => ...

        $this->allowed['`'] = strtolower($mTag);
        if ($this->allowed['`'] == 'none') $this->allowed['`'] = '';
    }






    /**
     * Callback function: `.... .(title)[class]{style}`
     * @return string
     */
    function processCode(&$lineParser, &$matches)
    {
        list($match, $mContent, $mMod1, $mMod2, $mMod3) = $matches;
        //    [1] => ...
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}

        $texy = &$this->texy;
        $el = &new TexyTextualElement($texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3);
        $el->contentType = TEXY_CONTENT_TEXTUAL;
        $el->setContent($mContent, false);  // content isn't html safe
        $el->tag = $this->allowed['`'];

        if ($this->codeHandler)
            call_user_func_array($this->codeHandler, array(&$el));

        $el->safeContent(); // ensure that content is HTML safe

        return $lineParser->element->appendChild($el);
    }








    /**
     * User callback - PROTECT PHRASE
     * @return string
     */
    function processProtect(&$lineParser, &$matches, $isHtmlSafe = false)
    {
        list($match, $mContent) = $matches;

        $el = &new TexyTextualElement($this->texy);
        $el->contentType = TEXY_CONTENT_TEXTUAL;
//    $el->contentType = TEXY_CONTENT_BLOCK;
        $el->setContent( Texy::freezeSpaces($mContent), $isHtmlSafe );

        return $lineParser->element->appendChild($el);
    }




} // TexyPhraseModule



?>