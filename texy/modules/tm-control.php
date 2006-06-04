<?php

/**
 * ----------------------------------------
 *   FORM CONTROLS - TEXY! DEFAULT MODULE
 * ----------------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * For the full copyright and license information, please view the COPYRIGHT
 * file that was distributed with this source code. If the COPYRIGHT file is
 * missing, please visit the Texy! homepage: http://www.texy.info
 *
 * @package Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();








/**
 * CONTROL MODULE CLASS - EXPERIMENTAL !!!
 */
class TexyControlModule extends TexyModule {
    var $allowed;


    // constructor
    function TexyControlModule(&$texy)
    {
        parent::TexyModule($texy);
        $this->allowed->text = true;
        $this->allowed->select = true;
        $this->allowed->radio = true;
        $this->allowed->checkbox = true;
        $this->allowed->button = true;
    }


    /***
     * Module initialization.
     */
    function init()
    {
    }


    function trustMode()
    {
    }



    function safeMode()
    {
    }


} // TexyControlModule







/****************************************************************************
                                                             TEXY! DOM ELEMENTS                          */




?>