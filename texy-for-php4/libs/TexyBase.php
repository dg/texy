<?php

/**
 * Texy! - web text markup-language (for PHP 4)
 * --------------------------------------------
 *
 * Copyright (c) 2004, 2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://texy.info/
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @category   Text
 * @package    Texy
 * @link       http://texy.info/
 */



/**
 * PHP 4 Clone emulation
 *
 * Example: $obj = clone ($dolly)
 */
if (PHP_VERSION < 5) {
    eval('
    function clone($obj)
    {
        foreach($obj as $key => $value) {
            $obj->$key = & $value;               // reference to new variable
            $GLOBALS[\'$$HIDDEN$$\'][] = & $value; // and generate reference
            unset($value);
        }

        // call $obj->__clone()
        if (is_callable(array(&$obj, \'__clone\'))) $obj->__clone();

        return $obj;
    }

    function throw($e)
    {
        trigger_error($e->message, E_USER_ERROR);
    }

    class Exception
    {
        var $message;
        var $code;
        var $trace;

        function Exception($message = NULL, $code=0)
        {
            $this->message = $message;
            $this->code = $code;
            $this->trace = debug_backtrace();
        }
    }
    ');
}


/**
 * Exception base class
 * @package Texy
 */
class TexyException extends Exception
{
}



/**
 * Texy base class for all classes except static helpers TexyConfigurator & TexyUtf
 * @package Texy
 * @version $Revision$ $Date$
 */
class TexyBase /* abstract  */
{

    function __construct()  /* PHP 5 constructor */
    {}



    function TexyBase()  /* PHP 4 constructor */
    {
        // generate references (see http://latrine.dgx.cz/how-to-emulate-php5-object-model-in-php4)
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        $args = func_get_args();
        call_user_func_array(array(&$this, '__construct'), $args);
    }



    /**#@+
     * Access to undeclared property in PHP 5
     * @throws Exception
     */
    function &__get($name)
    {
        throw (new Exception("Access to undeclared property: " . get_class($this) . "::$$name"));
    }

    function __set($name, $value)
    {
        throw (new Exception("Access to undeclared property: " . get_class($this) . "::$$name"));
    }

    function __unset($name)
    {
        throw (new Exception("Access to undeclared property: " . get_class($this) . "::$$name"));
    }
    /**#@-*/
}