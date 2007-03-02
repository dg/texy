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
 * Emoticon module
 */
class TexyEmoticonModule extends TexyModule
{
    protected $default = array('emoticon' => FALSE);

    /** @var array  supported emoticons and image files */
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

    /** @var string  CSS class for emoticons */
    public $class;

    /** @var string  root of relative images (default value is $texy->imageModule->root) */
    public $root;

    /** @var string  physical location of images on server (default value is $texy->imageModule->fileRoot) */
    public $fileRoot;



    public function init()
    {
        if (empty($this->texy->allowed['emoticon'])) return;

        krsort($this->icons);

        $pattern = array();
        foreach ($this->icons as $key => $foo)
            $pattern[] = preg_quote($key, '#') . '+'; // last char can be repeated

        $this->texy->registerLinePattern(
            array($this, 'processLine'),
            '#(?<=^|[\\x00-\\x20])(' . implode('|', $pattern) . ')#',
            'emoticon'
        );
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
        $user = NULL;

        // find the closest match
        foreach ($this->icons as $emoticon => $file)
        {
            if (strncmp($match, $emoticon, strlen($emoticon)) === 0)
            {
                if (is_callable(array($tx->handler, 'emoticon')))
                    $tx->handler->emoticon($tx, $match, $file, $user);

                $el = TexyHtml::el('img');
                $el->src = Texy::completeURL($file, $this->root === NULL ?  $tx->imageModule->root : $this->root);
                $el->alt = $match;
                $el->class[] = $this->class;

                $file = Texy::completePath($file, $this->fileRoot === NULL ?  $tx->imageModule->fileRoot : $this->fileRoot);
                if (is_file($file)) {
                    $size = getImageSize($file);
                    if (is_array($size)) {
                        $el->width = $size[0];
                        $el->height = $size[1];
                    }
                }

                if (is_callable(array($tx->handler, 'emoticon2')))
                    $tx->handler->emoticon2($tx, $el, $user);

                $tx->summary['images'][] = $el->src;
                return $el;
            }
        }
    }

}
