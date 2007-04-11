<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * @version  $Revision$ $Date$
 * @package  Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();



/**
 * Texy! modules base class
 */
abstract class TexyModule
{
    /** @var Texy */
    protected $texy;

    /** @var array  list of syntax to allow */
    protected $default = array();



    public function __construct($texy)
    {
        $this->texy = $texy;
        $texy->registerModule($this);
        $texy->allowed = array_merge($texy->allowed, $this->default);
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

} // TexyModule




interface ITexyPreProcess
{
    /**
     * Full text pre-processing
     * @param string
     * @return string
     */
    public function preProcess($text);
}


interface ITexyPreBlock
{
    /**
     * Single block pre-processing
     * @param string
     * @return string
     */
    public function preBlock($block);
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
