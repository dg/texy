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
 * Scripts module
 */
class TexyScriptModule extends TexyModule
{
    protected $default = array('script' => FALSE);

    /**
     * @var callback|object  script elements handler
     * function myUserFunc($element, string $identifier, array/NULL $args)
     */
    public $handler;


    public function init()
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

        $args = NULL;
        if (preg_match('#^([a-z_][a-z0-9_]*)\s*\(([^()]*)\)$#i', $func, $matches)) {
            $func = $matches[1];
            $args = explode(',', $matches[2]);
            array_walk($args, 'trim');
        }

        if ($func==='texy')
            return $this->texyHandler($args);

        if (is_callable(array($this->handler, $func))) {
            array_unshift($args, $this->texy);
            return call_user_func_array(array($this->handler, $func), $args);
        }

        if (is_callable($this->handler))
            return call_user_func_array($this->handler, array($this->texy, $func, $args));

        return FALSE;
    }



    public function texyHandler($args)
    {
        return '';
    }

} // TexyScriptModule
