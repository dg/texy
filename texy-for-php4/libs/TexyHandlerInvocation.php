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
 * Around advice handlers
 */
class TexyHandlerInvocation
{
    /** @var array of callbacks */
    var $handlers;

    /** @var int  callback counter */
    var $pos;

    /** @var array */
    var $args;

    /** @var TexyParser */
    var $parser;



    /**
     * @param array    array of callbacks
     * @param TexyParser
     * @param array    arguments
     */
    function __construct($handlers, $parser, $args)
    {
        $this->handlers = $handlers;
        $this->pos = count($handlers);
        $this->parser = $parser;
        array_unshift($args, $this);
        $this->args = $args;
    }


    /**
     * @param mixed
     * @return mixed
     */
    function proceed()
    {
        if ($this->pos === 0) {
            trigger_error('No more handlers', E_USER_ERROR);
        }

        if (func_num_args()) {
            $this->args = func_get_args();
            array_unshift($this->args, $this);
        }

        $this->pos--;
        return call_user_func_array($this->handlers[$this->pos], $this->args);
    }



    /**
     * @return TexyParser
     */
    function getParser()
    {
        return $this->parser;
    }



    /**
     * @return Texy
     */
    function getTexy()
    {
        return $this->parser->getTexy();
    }



    /**
     * PHP garbage collector helper
     */
    function free()
    {
        $this->handlers = $this->parser = $this->args = NULL;
    }


    function TexyHandlerInvocation()  /* PHP 4 constructor */
    {
        // generate references (see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4)
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call php5 constructor
        if (method_exists($this, '__construct')) {
            $args = func_get_args();
            call_user_func_array(array(&$this, '__construct'), $args);
        }
    }

}
