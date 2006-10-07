<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * Texy! MODULES BASE CLASS
 * ------------------------
 */
class TexyModule
{
    protected $texy;             // parent Texy! object
    public $allowed = Texy::ALL;   // module configuration


    public function __construct($texy)
    {
        $this->texy =  $texy;
        $texy->registerModule($this);
    }




    // register all line & block patterns a routines
    public function init()
    {
    }


    // block's pre-process
    public function preProcess(&$text)
    {
    }



    // block's post-process
    public function postProcess(&$text)
    {
    }


    // single line post-process
    public function linePostProcess(&$line)
    {
    }




    /**
     * Undefined property usage prevention
     */
    function __set($nm, $val)     { $c=get_class($this); die("Undefined property '$c::$$nm'"); }
    function __get($nm)           { $c=get_class($this); die("Undefined property '$c::$$nm'"); }
    private function __unset($nm) { $c=get_class($this); die("Cannot unset property '$c::$$nm'."); }
    private function __isset($nm) { return FALSE; }
} // TexyModule
