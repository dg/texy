<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy')) die();



/**
 * Emoticon module
 */
class TexyEmoticonModule extends TexyModule
{
    var $syntax = array('emoticon' => FALSE);

    /** @var array  supported emoticons and image files */
    var $icons = array (
        ':-)'  =>  'smile.gif',
        ':-('  =>  'sad.gif',
        ';-)'  =>  'wink.gif',
        ':-D'  =>  'biggrin.gif',
        '8-O' => 'eek.gif',
        '8-)'  =>  'cool.gif',
        ':-?'  =>  'confused.gif',
        ':-x' => 'mad.gif',
        ':-P' => 'razz.gif',
        ':-|'  =>  'neutral.gif',
    );

    /** @var string  CSS class for emoticons */
    var $class;

    /** @var string  root of relative images (default value is $texy->imageModule->root) */
    var $root;

    /** @var string  physical location of images on server (default value is $texy->imageModule->fileRoot) */
    var $fileRoot;



    function begin()
    {
        if (empty($this->texy->allowed['emoticon'])) return;

        krsort($this->icons);

        $pattern = array();
        foreach ($this->icons as $key => $foo)
            $pattern[] = preg_quote($key, '#') . '+'; // last char can be repeated

        $this->texy->registerLinePattern(
            array($this, 'pattern'),
            '#(?<=^|[\\x00-\\x20])(' . implode('|', $pattern) . ')#',
            'emoticon'
        );
    }



    /**
     * Callback for: :-)))
     *
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    function pattern($parser, $matches)
    {
        $match = $matches[0];

        $tx = $this->texy;

        // find the closest match
        foreach ($this->icons as $emoticon => $foo)
        {
            if (strncmp($match, $emoticon, strlen($emoticon)) === 0)
            {
                // event wrapper
                if (is_callable(array($tx->handler, 'emoticon'))) {
                    $res = $tx->handler->emoticon($parser, $emoticon, $match);
                    if ($res !== TEXY_PROCEED) return $res;
                }

                return $this->solve($emoticon, $match);
            }
        }

        return FALSE; // tohle se nestane
    }



    /**
     * Finish invocation
     *
     * @param string
     * @param string
     * @return TexyHtml|FALSE
     */
    function solve($emoticon, $raw)
    {
        $tx = $this->texy;
        $file = $this->icons[$emoticon];
        $el = TexyHtml::el('img');
        $el->attrs['src'] = Texy::prependRoot($file, $this->root === NULL ?  $tx->imageModule->root : $this->root);
        $el->attrs['alt'] = $raw;
        $el->attrs['class'][] = $this->class;

        // file path
        $file = rtrim($this->fileRoot === NULL ?  $tx->imageModule->fileRoot : $this->fileRoot, '/\\') . '/' . $file;
        if (is_file($file)) {
            $size = getImageSize($file);
            if (is_array($size)) {
                $el->attrs['width'] = $size[0];
                $el->attrs['height'] = $size[1];
            }
        }
        $tx->summary['images'][] = $el->attrs['src'];
        return $el;
    }

}
