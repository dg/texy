<?php

/**
 * Texy! - web text markup-language
 * --------------------------------
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
 * Exception base class
 * @package Texy
 * @version $Revision$ $Date$
 */
class TexyException extends Exception
{
}



/**
 * Texy base class for all classes except static helpers TexyConfigurator & TexyUtf
 * @package Texy
 */
abstract class TexyBase
{

    /**#@+
     * Access to undeclared property
     * @throws Exception
     */
    public function &__get($name)
    {
        throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name");
    }

    public function __set($name, $value)
    {
        throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name");
    }

    public function __unset($name)
    {
        throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name");
    }
    /**#@-*/
}
