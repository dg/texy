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



/**
 * Texy! modules base class
 */
class TexyModule /* abstract  */
{
    /** @var Texy */
    var $texy; /* protected */


    function __construct()
    {}


    function TexyModule()  /* PHP 4 constructor */
    {
        // generate references (see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4)
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call php5 constructor
        $args = func_get_args();
        call_user_func_array(array(&$this, '__construct'), $args);
    }



    /**#@+
     * Access to undeclared property in PHP 5
     * @throws Exception
     */
    function __get($name) { throw (new Exception("Access to undeclared property: " . get_class($this) . "::$$name")); }
    function __set($name, $value) { throw (new Exception("Access to undeclared property: " . get_class($this) . "::$$name")); }
    function __unset($name) { throw (new Exception("Access to undeclared property: " . get_class($this) . "::$$name")); }
    /**#@-*/
}
