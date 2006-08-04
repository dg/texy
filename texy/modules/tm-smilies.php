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
 * AUTOMATIC REPLACEMENTS MODULE CLASS
 */
class TexySmiliesModule extends TexyModule {
    var $allowed   = false;
    var $icons     = array (
                        ':-)'  =>  'smile.gif',
                        ':-('  =>  'sad.gif',
                        ';-)'  =>  'wink.gif',
                        ':-D'  =>  'biggrin.gif',
                        '8-O'  =>  'eek.gif',
                        '8-)'  =>  'cool.gif',
                        ':-?'  =>  'confused.gif',
                        ':-x'  =>  'mad.gif',
                        ':-P'  =>  'razz.gif',
                        ':-|'  =>  'neutral.gif',
            );
    var $root      = 'images/smilies/';
    var $class     = '';



    /**
     * Module initialization.
     */
    function init()
    {
        Texy::adjustDir($this->root);

        if ($this->allowed) {
            krsort($this->icons);
            $pattern = array();
            foreach ($this->icons as $key => $value)
                $pattern[] = preg_quote($key) . '+';

            $crazyRE = '#(?<=^|[\\x00-\\x20])(' . implode('|', $pattern) . ')#';

            $this->registerLinePattern('processLine', $crazyRE);
        }
    }






    /**
     * Callback function: :-)
     * @return string
     */
    function processLine(&$lineParser, &$matches)
    {
        $match = &$matches[0];
        //    [1] => **
        //    [2] => ...
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => LINK

        $texy = & $this->texy;
        $el = &new TexyImageElement($texy);
        $el->modifier->title = $match;
        $el->modifier->classes[] = $this->class;
        $el->image->root = $this->root;

         // find the closest match
        foreach ($this->icons as $key => $value)
            if (substr($match, 0, strlen($key)) == $key) {
                $el->image->set($value);
                break;
            }

        return $lineParser->element->appendChild($el);
    }



} // TexySmiliesModule






?>