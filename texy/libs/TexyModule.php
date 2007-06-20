<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * @version  $Revision$ $Date$
 * @package  Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy', FALSE)) die();



/**
 * Texy! modules base class
 */
abstract class TexyModule
{
    /** @var Texy */
    protected $texy;

    /** @var array  list of syntax to allow */
    protected $syntax = array();



    public function __construct($texy)
    {
        $this->texy = $texy;
        $texy->registerModule($this);
        $texy->allowed = array_merge($texy->allowed, $this->syntax);
    }


    /**
     * Called by $texy->parse
     */
    public function begin()
    {}


    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

}




interface ITexyPreBlock
{
    /**
     * Single block pre-processing
     * @param string
     * @param bool
     * @return string
     */
    public function preBlock($block, $topLevel);
}


interface ITexyPostLine
{
    /**
     * Single line post-processing
     * @param string
     * @return string
     */
    public function postLine($line);
}
