<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * @version  $Revision$ $Date$
 * @package  Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();



/**
 * Scripts module
 */
class TexyScriptModule extends TexyModule
{
    protected $default = array('script' => TRUE);

    /**
     * @var callback|object  script elements handler
     * function myUserFunc($element, string $identifier, array/NULL $args)
     */
    public $handler;


    /** @var string  arguments separator */
    public $separator = ',';



    public function begin()
    {
        $this->texy->registerLinePattern(
            array($this, 'pattern'),
            '#\{\{([^'.TEXY_MARK.']+)\}\}()#U',
            'script'
        );
    }



    /**
     * Callback for: {{...}}
     *
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function pattern($parser, $matches)
    {
        list(, $mContent) = $matches;
        //    [1] => ...

        $func = trim($mContent);
        if ($func === '') return FALSE;

        $args = $raw = NULL;
        // function(arg, arg, ...)  or  function: arg, arg
        if (preg_match('#^([a-z_][a-z0-9_-]*)\s*(?:\(([^()]*)\)|:(.*))$#iu', $func, $matches)) {
            $func = $matches[1];
            $raw = isset($matches[3]) ? trim($matches[3]) : trim($matches[2]);
            if ($raw === '')
                $args = array();
            else
                $args = preg_split('#\s*' . preg_quote($this->separator, '#') . '\s*#u', $raw);
        }

        if (is_callable(array($this->handler, $func))) {
            array_unshift($args, $parser);
            return call_user_func_array(array($this->handler, $func), $args);
        }

        if (is_callable($this->handler))
            return call_user_func_array($this->handler, array($parser, $func, $args, $raw));

        if ($func==='texy')
            return $this->texyHandler($args);

        return FALSE;
    }



    public function texyHandler($args)
    {
        return '';
    }

} // TexyScriptModule
