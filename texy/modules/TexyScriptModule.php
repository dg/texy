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
     * @var callback  handle script elements
     * function myUserFunc($element, string $identifier, array/NULL $args)
     */
    public $handler;


    public function init()
    {
        $this->texy->registerLinePattern(
            array($this, 'processLine'),
            '#\{\{([^'.TEXY_MARK.']+)\}\}()#U',
            'script'
        );
    }



    /**
     * Callback function: {{...}}
     * @return string
     */
    public function processLine($parser, $matches)
    {
        list(, $mContent) = $matches;
        //    [1] => ...

        $identifier = trim($mContent);
        if ($identifier === '' || $this->handler === NULL) return FALSE;

        $args = NULL;
        if (preg_match('#^([a-z_][a-z0-9_]*)\s*\(([^()]*)\)$#i', $identifier, $matches)) {
            $identifier = $matches[1];
            $args = explode(',', $matches[2]);
            array_walk($args, 'trim');
        }

        if (is_callable(array($this->handler, $identifier))) {
            array_unshift($args, $this->texy);
            return call_user_func_array(array($this->handler, $identifier), $args);
        }

        if (is_callable($this->handler))
            return call_user_func_array($this->handler, array($this->texy, $identifier, $args));

        return FALSE;
    }



    public function defaultHandler($texy, $identifier, $args)
    {
        if ($args) $identifier .= '('.implode(',', $args).')';

        $el = TexyHtml::el('texy:script');
        $el->_empty = TRUE;
        $el->content = $identifier;
        return $el;
    }

} // TexyScriptModule
