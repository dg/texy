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
 * Compatibility with PHP < 5
 *
 * Example: $obj = clone ($dolly)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2008 David Grudl
 * @link       http://latrine.dgx.cz/how-to-emulate-php5-object-model-in-php4
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
        die();
    }
    ');

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
}


/**
 * NObject (for PHP4) is the ultimate ancestor of all instantiable classes.
 *
 * It defines some handful methods and enhances object core of PHP4.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2008 David Grudl
 * @license    http://nettephp.com/license  Nette license
 * @link       http://nettephp.com/
 * @package    Nette
 */
class NObject4 /* abstract  */
{

    function __construct()  /* PHP 5 constructor */
    {}



    function NObject4()  /* PHP 4 constructor */
    {
        // generate references (see http://latrine.dgx.cz/how-to-emulate-php5-object-model-in-php4)
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        $args = func_get_args();
        call_user_func_array(array(&$this, '__construct'), $args);
    }



    /**
     * Returns the name of the class of this object
     *
     * @return string
     */
    function getClass()
    {
        return get_class($this);
    }



    /**
     * Access to reflection
     *
     * @return ReflectionObject
     */
    function getReflection()
    {
        return new ReflectionObject($this);
    }

}
