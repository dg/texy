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
abstract class TexyModule
{
    /** @var Texy */
    protected $texy;



    /**#@+
     * Access to undeclared property
     * @throws Exception
     */
    private function __get($name) { throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name"); }
    private function __set($name, $value) { throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name"); }
    private function __unset($name) { throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name"); }
    /**#@-*/
}
