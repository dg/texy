<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();



/**
 * Scripts module
 */
class TexyScriptModule extends TexyModule
{
    protected $allow = array('Script');

    /**
     * @var callback  handle script elements
     * function myUserFunc($element, string $identifier, array/NULL $args)
     */
    public $handler;


    public function init()
    {
        $this->texy->registerLinePattern(
            $this,
            'processLine',
            '#\{\{([^'.TEXY_MARK.']+)\}\}()#U',
            'Script'
        );
    }



    /**
     * Callback function: {{...}}
     * @return string
     */
    public function processLine($parser, $matches)
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

        $el = new TexyTextualElement($this->texy);

        do {
            if ($this->handler === NULL) break;

            if (is_object($this->handler)) {

                if ($args === NULL && isset($this->handler->$identifier)) {
                    $el->content = $this->handler->$identifier;
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

            if (is_callable($this->handler))
                call_user_func_array($this->handler, array($el, $identifier, $args));

        } while(0);

        return ''; // !!!
        return $this->texy->mark($el, Texy::CONTENT_TEXTUAL);
    }



    public function defaultHandler($element, $identifier, $args)
    {
        if ($args)
            $identifier .= '('.implode(',', $args).')';

        $element->content = $element->texy->mark('<texy:script content="'
            . htmlSpecialChars($identifier, ENT_COMPAT) . '" />', Texy::CONTENT_TEXTUAL);
    }

} // TexyScriptModule
