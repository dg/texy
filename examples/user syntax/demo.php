<?php

/**
 * TEXY! USER SYNTAX DEMO
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 */


// include Texy!
require_once dirname(__FILE__).'/../../texy/texy.php';


class MyTexy extends Texy
{

    protected function init()
    {
        // disable *** and ** and * phrases
        $this->phraseModule->allowed['***'] = false;
        $this->phraseModule->allowed['**'] = false;
        $this->phraseModule->allowed['*'] = false;

        parent::init();

        // add new syntax: *bold* _italic_
        $this->registerLinePattern($this->phraseModule, 'processPhrase', '#(?<!\*)\*(?!\ )([^\*]+)<MODIFIER>?(?<!\ )\*(?!\*)()#U', 'b');
        $this->registerLinePattern($this->phraseModule, 'processPhrase', '#(?<!\_)\_(?!\ )([^\_]+)<MODIFIER>?(?<!\ )\_(?!\_)()#U', 'i');
    }

}



$texy = new MyTexy();

// processing
$text = file_get_contents('syntax.texy');
$html = $texy->process($text);  // that's all folks!

// echo formated output
echo $html;

// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>