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
 * SCRIPTS MODULE CLASS
 */
class TexyScriptModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    public $handler;             // function myUserFunc($element, string $identifier, array/NULL $args)


    /**
     * Module initialization.
     */
    public function init()
    {
        $this->texy->registerLinePattern($this, 'processLine', '#\{\{([^:HASH:]+)\}\}()#U');
    }



    /**
     * Callback function: {{...}}
     * @return string
     */
    public function processLine($parser, $matches, $tag)
    {
        list(, $mContent) = $matches;
        //    [1] => ...

        $identifier = trim($mContent);
        if ($identifier === '') return;

        $args = NULL;
        if (preg_match('#^([a-z_][a-z0-9_]*)\s*\(([^()]*)\)$#i', $identifier, $matches)) {
            $identifier = $matches[1];
            $args = explode(',', $matches[2]);
            array_walk($args, 'trim');
        }

        $el = new TexyScriptElement($this->texy);

        do {
            if ($this->handler === NULL) break;

            if (is_object($this->handler)) {

                if ($args === NULL && isset($this->handler->$identifier)) {
                    $el->setContent($this->handler->$identifier);
                    break;
                }

                if (is_array($args) && is_callable( array($this->handler, $identifier) ))  {
                    array_unshift($args, NULL);
                    $args[0] = $el;
                    call_user_func_array( array($this->handler, $identifier), $args);
                    break;
                }

                break;
            }

            if (is_callable( $this->handler) )
                call_user_func_array($this->handler, array($el, $identifier, $args));

        } while(0);

        return $parser->element->appendChild($el);
    }



    public function defaultHandler($element, $identifier, $args)
    {
        if ($args)
            $identifier .= '('.implode(',', $args).')';

        $element->setContent('<texy:script content="'.TexyHtml::htmlChars($identifier, TRUE).'" />', TRUE);
    }


} // TexyScriptModule



/**
 * Texy! ELEMENT SCRIPTS + VARIABLES
 */
class TexyScriptElement extends TexyTextualElement
{
}
