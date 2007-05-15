<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * @version  $Revision: 118 $ $Date: 2007-04-11 20:36:18 +0200 (st, 11 IV 2007) $
 * @package  Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy', FALSE)) die();



/**
 * Section module
 */
class TexySectionModule extends TexyModule
{
    protected $default = array('section' => TRUE);

    /** @var string  prefix for sections */
    public $prefix = 'section:';



    public function begin()
    {
        $this->texy->registerBlockPattern(
            array($this, 'patternSurround'),
            '#^\-{3,}+(['.TEXY_CHAR.'0-9 \#:/-]+)\-{3,}$#mUu',
            'section'
        );
    }



    /**
     * Callback for
     *
     *   --- section ---
     *
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternSurround($parser, $matches)
    {
        list(, $mContent) = $matches;
        //    [1] => ...

        $mContent = rtrim($mContent);

        // event wrapper
        if (is_callable(array($this->texy->handler, 'section'))) {
            $res = $this->texy->handler->section($parser, $mContent);
            if ($res !== Texy::PROCEED) return $res;
        }

        return $this->solve($mContent);
    }


    /**
     * Finish invocation
     *
     * @param string
     * @return string
     */
    public function solve($content)
    {
        $content = '<!--section:' . $content . '-->';
        return $this->texy->protect($content, Texy::CONTENT_MARKUP);
    }


} // TexySectionModule
