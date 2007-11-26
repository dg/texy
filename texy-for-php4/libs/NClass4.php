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
 * @copyright  Copyright (c) 2004, 2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @link       http://texy.info/
 * @package    Texy
 */



/**
 * NClass (for PHP4) is the ultimate ancestor of all uninstantiable classes.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2007 David Grudl
 * @license    http://php7.org/nette/license  Nette license
 * @link       http://php7.org/nette/
 * @package    Nette
 */
class NClass4
{

    function __construct()
    {
        throw (new LogicException("Cannot instantiate static class " . get_class($this)));
    }

}
