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
 * Smilies Module
 */
class TexySmiliesModule extends TexyModule
{
    //protected $allow = array('smilies');

    /** @var array  supported smilies and image files */
    public $icons = array (
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

    /** @var string  CSS class for smilies */
    public $class;

    /** @var string  images location= $texy->imageModule->webRoot (or fileRoot) + $iconPrefix + $icons[...] */
    public $iconPrefix;



    public function init()
    {
        if (empty($this->texy->allowed['smilies'])) return;

        krsort($this->icons);

        $pattern = array();
        foreach ($this->icons as $key => $foo)
            $pattern[] = preg_quote($key, '#') . '+'; // last char can be repeated

        $RE = '#(?<=^|[\\x00-\\x20])(' . implode('|', $pattern) . ')#';

        $this->texy->registerLinePattern($this, 'processLine', $RE, 'smilies');
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

        $tx = $this->texy;

        // find the closest match
        foreach ($this->icons as $key => $value)
        {
            if (substr($match, 0, strlen($key)) === $key)
            {
                $mod = new TexyModifier($tx);
                $mod->title = $match;
                $mod->classes[] = $this->class;
                $el = $tx->imageModule->factoryEl($this->iconPrefix . $value, NULL, NULL, NULL, $mod, NULL);
                return $el; // should be object
            }
        }
    }

} // TexySmiliesModule
