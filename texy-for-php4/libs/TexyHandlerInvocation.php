<?php

/**
 * Texy! - web text markup-language (for PHP 4)
 * --------------------------------------------
 *
 * Copyright (c) 2004, 2008 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://texy.info/
 *
 * @copyright  Copyright (c) 2004, 2008 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @link       http://texy.info/
 * @package    Texy
 */



/**
 * Around advice handlers.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2008 David Grudl
 * @package    Texy
 * @version    $Revision$ $Date$
 */
class TexyHandlerInvocation extends NObject4
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
     * @param  array    array of callbacks
     * @param  TexyParser
     * @param  array    arguments
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
     * @param  mixed
     * @return mixed
     */
    function proceed()
    {
        if ($this->pos === 0) {
            throw (new InvalidStateException('No more handlers'));
        }

        if (func_num_args()) {
            $this->args = func_get_args();
            array_unshift($this->args, $this);
        }

        $this->pos--;
        $res = call_user_func_array($this->handlers[$this->pos], $this->args);
        if ($res === NULL) {
            throw (new UnexpectedValueException("Invalid value returned from handler '" . print_r($this->handlers[$this->pos], TRUE) . "'"));
        }
        return $res;
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
     * PHP garbage collector helper.
     */
    function free()
    {
        $this->handlers = $this->parser = $this->args = NULL;
    }

}
