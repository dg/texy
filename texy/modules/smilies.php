<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://www.texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * AUTOMATIC REPLACEMENTS MODULE CLASS
 */
class TexySmiliesModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    var $handler;

    var $allowed   = FALSE;
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
                $pattern[] = preg_quote($key, '#') . '+';

            $crazyRE = '#(?<=^|[\\x00-\\x20])(' . implode('|', $pattern) . ')#';

            $this->texy->registerLinePattern($this, 'processLine', $crazyRE);
        }
    }






    /**
     * Callback function: :-)
     * @return string
     */
    function processLine(&$parser, $matches)
    {
        $match = $matches[0];
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

         // find the closest match
        foreach ($this->icons as $key => $value)
            if (substr($match, 0, strlen($key)) == $key) {
                $el->image->set($value, $this->root, TRUE);
                break;
            }

        if ($this->handler)
            if (call_user_func_array($this->handler, array(&$el)) === FALSE) return '';

        return $parser->element->appendChild($el);
    }



} // TexySmiliesModule






?>