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
 * Exception base class
 */
class TexyException extends Exception
{
}



/**
 * Texy base class for all classes except static helpers TexyConfigurator & TexyUtf
 */
abstract class TexyBase
{

    /**#@+
     * Access to undeclared property
     * @throws Exception
     */
    public function &__get($name) { throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name"); }
    public function __set($name, $value) { throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name"); }
    public function __unset($name) { throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name"); }
    /**#@-*/
}
