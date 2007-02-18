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
 * AUTOMATIC REPLACEMENTS MODULE CLASS
 */
class TexySmiliesModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    public $handler;

    public $icons     = array (
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
    public $root      = 'images/smilies/';
    public $class     = '';



    /**
     * Module initialization.
     */
    public function init()
    {
        if (!empty($this->texy->allowed['Image.smilies'])) {
            krsort($this->icons);
            $pattern = array();
            foreach ($this->icons as $key => $foo)
                $pattern[] = preg_quote($key, '#') . '+';

            $crazyRE = '#(?<=^|[\\x00-\\x20])(' . implode('|', $pattern) . ')#';

            $this->texy->registerLinePattern($this, 'processLine', $crazyRE);
        }
    }






    /**
     * Callback function: :-)
     * @return string
     */
    public function processLine($parser, $matches)
    {
        $match = $matches[0];
        //    [1] => **
        //    [2] => ...
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => LINK

        $texy =  $this->texy;
        $el = new TexyImageElement($texy);
        $el->modifier->title = $match;
        $el->modifier->classes[] = $this->class;

         // find the closest match
        foreach ($this->icons as $key => $value)
            if (substr($match, 0, strlen($key)) == $key) {
                $el->image->set($value, $this->root, TRUE);
                break;
            }

        if ($this->handler)
            if (call_user_func_array($this->handler, array($el)) === FALSE) return '';

        return $this->texy->hash($el->toHtml(), TexyDomElement::CONTENT_NONE); // !!!
    }



} // TexySmiliesModule
