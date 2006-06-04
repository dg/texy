<?php

/**
 * -----------------------------------
 *   SCRIPTS - TEXY! DEFAULT MODULES
 * -----------------------------------
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
 * SCRIPTS MODULE CLASS
 */
class TexyScriptModule extends TexyModule {
    var $handler;             // function &myUserFunc(&$element, string $identifier, array/null $args)


    /***
     * Module initialization.
     */
    function init()
    {
        $this->registerLinePattern('processLine', '#\{\{([^:HASH:]+)\}\}()#U');
    }



    /***
     * Callback function: ${...}
     * @return string
     */
    function processLine(&$lineParser, &$matches, $tag)
    {
        list($match, $mContent) = $matches;
        //    [1] => ...

        $identifier = trim($mContent);
        if ($identifier === '') return;

        $args = null;
        if (preg_match('#^([a-z_][a-z0-9_]*)\s*\(([^()]*)\)$#i', $identifier, $matches)) {
            $identifier = $matches[1];
            array_walk(
                 $args = explode(',', $matches[2]),
                 'trim'
            );
        }

        $el = &new TexyScriptElement($this->texy);

        do {
            if ($this->handler === null) break;

            if (is_object($this->handler)) {

                if ($args === null && isset($this->handler->$identifier)) {
                    $el->setContent($this->handler->$identifier);
                    break;
                }

                if (is_array($args) && is_callable( array(&$this->handler, $identifier) ))  {
                    array_unshift($args, null);
                    $args[0] = &$el;
                    call_user_func_array( array(&$this->handler, $identifier), $args);
                    break;
                }

                break;
            }

            if (is_callable( $this->handler) )
                call_user_func_array($this->handler, array(&$el, $identifier, $args));

        } while(0);

        return $el->addTo($lineParser->element);
    }



    function defaultHandler(&$element, $identifier, $args)
    {
        if ($args)
            $identifier .= '('.implode(',', $args).')';

        $element->setContent('<texy:script content="'.htmlSpecialChars($identifier).'" />', true);
    }


} // TexyScriptModule







/****************************************************************************
                                                             TEXY! DOM ELEMENTS                          */




/**
 * Texy! ELEMENT SCRIPTS + VARIABLES
 */
class TexyScriptElement extends TexyTextualElement {


}  // TexyScriptElement





?>