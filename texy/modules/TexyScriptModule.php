<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy', FALSE)) die();



/**
 * Scripts module
 */
class TexyScriptModule extends TexyModule
{
    protected $syntax = array('script' => TRUE);

    /**
     * @var callback|object  script elements handler
     * function myFunc($parser, $cmd, $args, $raw)
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

        $cmd = trim($mContent);
        if ($cmd === '') return FALSE;

        $args = $raw = NULL;
        // function(arg, arg, ...)  or  function: arg, arg
        if (preg_match('#^([a-z_][a-z0-9_-]*)\s*(?:\(([^()]*)\)|:(.*))$#iu', $cmd, $matches)) {
            $cmd = $matches[1];
            $raw = isset($matches[3]) ? trim($matches[3]) : trim($matches[2]);
            if ($raw === '')
                $args = array();
            else
                $args = preg_split('#\s*' . preg_quote($this->separator, '#') . '\s*#u', $raw);
        }

        if ($this->handler) {
            // Texy 1.x way
            if (is_callable(array($this->handler, $cmd))) {
                array_unshift($args, $parser);
                return call_user_func_array(array($this->handler, $cmd), $args);
            }

            if (is_callable($this->handler))
                return call_user_func_array($this->handler, array($parser, $cmd, $args, $raw));
        }

        // Texy 2 way
        // event wrapper
        if (is_callable(array($this->texy->handler, 'script'))) {
            $res = $this->texy->handler->script($parser, $cmd, $args, $raw);
            if ($res !== Texy::PROCEED) return $res;
        }

        if ($cmd==='texy')
            return $this->texyHandler($args);

        return FALSE;
    }


    public function texyHandler($args)
    {
        if (!$args) return FALSE;

        switch ($args[0]) {
        case 'nofollow':
            $this->texy->linkModule->forceNoFollow = TRUE;
            break;
        }

        return '';
    }

}
