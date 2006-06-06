<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.1 for PHP4 & PHP5 $Date$ $Revision$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * SCRIPTS MODULE CLASS
 */
class TexyScriptModule extends TexyModule {
    var $handler;             // function &myUserFunc(&$element, string $identifier, array/null $args)


    /**
     * Module initialization.
     */
    function init()
    {
        $this->registerLinePattern('processLine', '#\{\{([^:HASH:]+)\}\}()#U');
    }



    /**
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

        return $lineParser->element->appendChild($el);
    }



    function defaultHandler(&$element, $identifier, $args)
    {
        if ($args)
            $identifier .= '('.implode(',', $args).')';

        $element->setContent('<texy:script content="'.Texy::htmlChars($identifier, true).'" />', true);
    }


} // TexyScriptModule







/***************************************************************************
                                                             TEXY! DOM ELEMENTS                          */




/**
 * Texy! ELEMENT SCRIPTS + VARIABLES
 */
class TexyScriptElement extends TexyTextualElement {


}  // TexyScriptElement





?>