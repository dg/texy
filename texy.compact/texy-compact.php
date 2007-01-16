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
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    1.1 for PHP4 & PHP5 $Date: 2006-06-07 01:28:09 +0200 (Wed, 07 Jun 2006) $ $Revision: 38 $
 */if(version_compare(PHP_VERSION,'4.3.3','<'))die('Texy!: too old version of PHP!');define('TEXY','Version 1.1 for PHP4 & PHP5 $Revision: 38 $');define('TEXY_DIR',dirname(__FILE__).'/');

define('TEXY_HALIGN_LEFT','left');define('TEXY_HALIGN_RIGHT','right');define('TEXY_HALIGN_CENTER','center');define('TEXY_HALIGN_JUSTIFY','justify');define('TEXY_VALIGN_TOP','top');define('TEXY_VALIGN_MIDDLE','middle');define('TEXY_VALIGN_BOTTOM','bottom');define('TEXY_URL_ABSOLUTE',1);define('TEXY_URL_RELATIVE',2);define('TEXY_URL_EMAIL',4);define('TEXY_URL_IMAGE_INLINE',1<<3);define('TEXY_URL_IMAGE_LINKED',4<<3);define('TEXY_CONTENT_NONE',1);define('TEXY_CONTENT_TEXTUAL',2);define('TEXY_CONTENT_BLOCK',3);define('TEXY_BLOCK_ELEMENTS',serialize(array_flip(array('address','blockquote','caption','col','colgroup','dd','div','dl','dt','fieldset','form','h1','h2','h3','h4','h5','h6','hr','iframe','legend','li','object','ol','p','param','pre','table','tbody','td','tfoot','th','thead','tr','ul'))));define('TEXY_INLINE_ELEMENTS',serialize(array_flip(array('a','abbr','acronym','area','b','big','br','button','cite','code','del','dfn','em','i','img','input','ins','kbd','label','map','noscript','optgroup','option','q','samp','script','select','small','span','strong','sub','sup','textarea','tt','var'))));define('TEXY_EMPTY_ELEMENTS',serialize(array_flip(array('img','hr','br','input','meta','area','base','col','link','param'))));define('TEXY_VALID_ELEMENTS',serialize(array_merge(unserialize(TEXY_BLOCK_ELEMENTS),unserialize(TEXY_INLINE_ELEMENTS))));define('TEXY_ACCEPTED_ATTRS',serialize(array_flip(array('abbr','accesskey','align','alt','archive','axis','bgcolor','cellpadding','cellspacing','char','charoff','charset','cite','classid','codebase','codetype','colspan','compact','coords','data','datetime','declare','dir','face','frame','headers','href','hreflang','hspace','ismap','lang','longdesc','name','noshade','nowrap','onblur','onclick','ondblclick','onkeydown','onkeypress','onkeyup','onmousedown','onmousemove','onmouseout','onmouseover','onmouseup','rel','rev','rowspan','rules','scope','shape','size','span','src','standby','start','summary','tabindex','target','title','type','usemap','valign','value','vspace'))));define('TEXY_EMPTY','/');define('TEXY_CLOSING','*');define('TEXY_ALL',true);define('TEXY_NONE',false);define('TEXY_CHAR','A-Za-z\x86-\xff');define('TEXY_CHAR_UTF','A-Za-z\x86-\x{ffff}');define('TEXY_NEWLINE',"\n");define('TEXY_HASH',"\x15-\x1F");define('TEXY_HASH_SPACES',"\x15-\x18");define('TEXY_HASH_NC',"\x19\x1B-\x1F");define('TEXY_HASH_WC',"\x1A-\x1F");define('TEXY_PATTERN_LINK_REF','\[[^\[\]\*\n'.TEXY_HASH.']+\]');define('TEXY_PATTERN_LINK_IMAGE','\[\*[^\n'.TEXY_HASH.']+\*\]');define('TEXY_PATTERN_LINK_URL','(?:\[[^\]\n]+\]|(?!\[)[^\s'.TEXY_HASH.']*?[^:);,.!?\s'.TEXY_HASH.'])');define('TEXY_PATTERN_LINK','(?::('.TEXY_PATTERN_LINK_URL.'))');define('TEXY_PATTERN_LINK_N','(?::('.TEXY_PATTERN_LINK_URL.'|:))');define('TEXY_PATTERN_EMAIL','[a-z0-9.+_-]+@[a-z0-9.+_-]{2,}\.[a-z]{2,}');define('TEXY_PATTERN_MODIFIER','(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??)');define('TEXY_PATTERN_MODIFIER_H','(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??)');define('TEXY_PATTERN_MODIFIER_HV','(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??)'); 

class
TexyModifier{var$texy;var$id;var$classes=array();var$unfilteredClasses=array();var$styles=array();var$unfilteredStyles=array();var$unfilteredAttrs=array();var$hAlign;var$vAlign;var$title;function
TexyModifier(&$texy){$this->texy=&$texy;}function
setProperties(){$classes='';$styles='';foreach(func_get_args()as$arg){if($arg=='')continue;$argX=trim(substr($arg,1,-1));switch($arg{0}){case'{':$styles.=$argX.';';break;case'(':$this->title=$argX;break;case'[':$classes.=' '.$argX;break;case'^':$this->vAlign=TEXY_VALIGN_TOP;break;case'-':$this->vAlign=TEXY_VALIGN_MIDDLE;break;case'_':$this->vAlign=TEXY_VALIGN_BOTTOM;break;case'=':$this->hAlign=TEXY_HALIGN_JUSTIFY;break;case'>':$this->hAlign=TEXY_HALIGN_RIGHT;break;case'<':$this->hAlign=$arg=='<>'?TEXY_HALIGN_CENTER:TEXY_HALIGN_LEFT;break;}}$this->parseStyles($styles);$this->parseClasses($classes);if(isset($this->classes['id'])){$this->id=$this->classes['id'];unset($this->classes['id']);}}function
getAttrs($tag){if($this->texy->allowedTags===TEXY_ALL)return$this->unfilteredAttrs;if(is_array($this->texy->allowedTags)&&isset($this->texy->allowedTags[$tag])){$allowedAttrs=$this->texy->allowedTags[$tag];if($allowedAttrs===TEXY_ALL)return$this->unfilteredAttrs;if(is_array($allowedAttrs)&&count($allowedAttrs)){$attrs=$this->unfilteredAttrs;foreach($attrs
as$key=>$foo)if(!in_array($key,$allowedAttrs))unset($attrs[$key]);return$attrs;}}return
array();}function
clear(){$this->id=null;$this->classes=array();$this->unfilteredClasses=array();$this->styles=array();$this->unfilteredStyles=array();$this->unfilteredAttrs=array();$this->hAlign=null;$this->vAlign=null;$this->title=null;}function
copyFrom(&$modifier){$this->classes=$modifier->classes;$this->unfilteredClasses=$modifier->unfilteredClasses;$this->styles=$modifier->styles;$this->unfilteredStyles=$modifier->unfilteredStyles;$this->unfilteredAttrs=$modifier->unfilteredAttrs;$this->id=$modifier->id;$this->hAlign=$modifier->hAlign;$this->vAlign=$modifier->vAlign;$this->title=$modifier->title;}function
parseClasses($str){if($str==null)return;$tmp=is_array($this->texy->allowedClasses)?array_flip($this->texy->allowedClasses):array();foreach(explode(' ',str_replace('#',' #',$str))as$value){if($value==='')continue;if($value{0}=='#'){$this->unfilteredClasses['id']=substr($value,1);if($this->texy->allowedClasses===TEXY_ALL||isset($tmp[$value]))$this->classes['id']=substr($value,1);}else{$this->unfilteredClasses[]=$value;if($this->texy->allowedClasses===TEXY_ALL||isset($tmp[$value]))$this->classes[]=$value;}}}function
parseStyles($str){if($str==null)return;$tmp=is_array($this->texy->allowedStyles)?array_flip($this->texy->allowedStyles):array();static$TEXY_ACCEPTED_ATTRS;if(!$TEXY_ACCEPTED_ATTRS)$TEXY_ACCEPTED_ATTRS=unserialize(TEXY_ACCEPTED_ATTRS);foreach(explode(';',$str)as$value){$pair=explode(':',$value.':');$property=strtolower(trim($pair[0]));$value=trim($pair[1]);if($property=='')continue;if(isset($TEXY_ACCEPTED_ATTRS[$property])){$this->unfilteredAttrs[$property]=$value;}else{$this->unfilteredStyles[$property]=$value;if($this->texy->allowedStyles===TEXY_ALL||isset($tmp[$property]))$this->styles[$property]=$value;}}}} 

class
TexyURL{var$texy;var$URL;var$type;var$text;var$root='';function
TexyURL(&$texy){$this->texy=&$texy;}function
set($text,$type=null){if($type!==null)$this->type=$type;$this->text=trim($text);$this->analyse();$this->toURL();}function
clear(){$this->text='';$this->type=0;$this->URL='';}function
copyFrom(&$obj){$this->text=$obj->text;$this->type=$obj->type;$this->URL=$obj->URL;$this->root=$obj->root;}function
analyse(){if(preg_match('#^'.TEXY_PATTERN_EMAIL.'$#i',$this->text))$this->type|=TEXY_URL_EMAIL;elseif(preg_match('#(https?://|ftp://|www\.|ftp\.|/)#Ai',$this->text))$this->type|=TEXY_URL_ABSOLUTE;else$this->type|=TEXY_URL_RELATIVE;}function
toURL(){if($this->text==null)return$this->URL='';if($this->type&TEXY_URL_EMAIL){if($this->texy->obfuscateEmail){$this->URL='mai';$s='lto:'.$this->text;for($i=0;$i<strlen($s);$i++)$this->URL.='&#'.ord($s{$i}).';';}else{$this->URL='mailto:'.$this->text;}return$this->URL;}if($this->type&TEXY_URL_ABSOLUTE){$textX=strtolower($this->text);if(substr($textX,0,4)=='www.'){return$this->URL='http://'.$this->text;}elseif(substr($textX,0,4)=='ftp.'){return$this->URL='ftp://'.$this->text;}return$this->URL=$this->text;}if($this->type&TEXY_URL_RELATIVE){return$this->URL=$this->root.$this->text;}}function
toString(){if($this->type&TEXY_URL_EMAIL){return$this->texy->obfuscateEmail?strtr($this->text,array('@'=>'&#160;(at)&#160;')):$this->text;}if($this->type&TEXY_URL_ABSOLUTE){$url=$this->text;$urlX=strtolower($url);if(substr($urlX,0,4)=='www.')$url='none://'.$url;elseif(substr($urlX,0,4)=='ftp.')$url='none://'.$url;$parts=@parse_url($url);if($parts===false)return$this->text;$res='';if(isset($parts['scheme'])&&$parts['scheme']!=='none')$res.=$parts['scheme'].'://';if(isset($parts['host']))$res.=$parts['host'];if(isset($parts['path']))$res.=(strlen($parts['path'])>16?('/...'.preg_replace('#^.*(.{0,12})$#U','$1',$parts['path'])):$parts['path']);if(isset($parts['query'])){$res.=strlen($parts['query'])>4?'?...':('?'.$parts['query']);}elseif(isset($parts['fragment'])){$res.=strlen($parts['fragment'])>4?'#...':('#'.$parts['fragment']);}return$res;}return$this->text;}} 

class
TexyDOMElement{var$texy;var$hidden;var$contentType=TEXY_CONTENT_NONE;function
__construct(&$texy){$this->texy=&$texy;}function
TexyDOMElement(&$texy){call_user_func_array(array(&$this,'__construct'),array(&$texy));}function
toHTML(){}function
broadcast(){$this->texy->DOM->elements[]=&$this;}}class
TexyHTMLElement
extends
TexyDOMElement{var$modifier;var$tag;function
__construct(&$texy){$this->texy=&$texy;$this->modifier=&$texy->createModifier();}function
generateTags(&$tags,$defaultTag=null){$tags=(array)$tags;if($defaultTag==null){if($this->tag==null)return;$defaultTag=$this->tag;}$attrs=$this->modifier->getAttrs($defaultTag);$attrs['id']=$this->modifier->id;if($this->modifier->title!==null)$attrs['title']=$this->modifier->title;$attrs['class']=$this->modifier->classes;$attrs['style']=$this->modifier->styles;if($this->modifier->hAlign)$attrs['style']['text-align']=$this->modifier->hAlign;if($this->modifier->vAlign)$attrs['style']['vertical-align']=$this->modifier->vAlign;$tags[$defaultTag]=$attrs;}function
generateContent(){}function
toHTML(){$this->generateTags($tags);if($this->hidden)return;return
Texy::openingTags($tags).$this->generateContent().Texy::closingTags($tags);}function
broadcast(){parent::broadcast();if($this->modifier->id)$this->texy->DOM->elementsById[$this->modifier->id]=&$this;if($this->modifier->classes)foreach($this->modifier->classes
as$class)$this->texy->DOM->elementsByClass[$class][]=&$this;}}class
TexyBlockElement
extends
TexyHTMLElement{var$children=array();function
appendChild(&$child){$this->children[]=&$child;$this->contentType=max($this->contentType,$child->contentType);}function
generateContent(){$html='';foreach(array_keys($this->children)as$key)$html.=$this->children[$key]->toHTML();return$html;}function
parse($text){$blockParser=&new
TexyBlockParser($this);$blockParser->parse($text);}function
broadcast(){parent::broadcast();foreach(array_keys($this->children)as$key)$this->children[$key]->broadcast();}}class
TexyTextualElement
extends
TexyBlockElement{var$content;var$htmlSafe=false;function
setContent($text,$isHtmlSafe=false){$this->content=$text;$this->htmlSafe=$isHtmlSafe;}function
safeContent($onlyReturn=false){$safeContent=$this->htmlSafe?$this->content:Texy::htmlChars($this->content);if($onlyReturn)return$safeContent;else{$this->htmlSafe=true;return$this->content=$safeContent;}}function
generateContent(){$content=$this->safeContent(true);if($this->children){$table=array();foreach(array_keys($this->children)as$key)$table[$key]=$this->children[$key]->toHTML(Texy::isHashOpening($key));return
strtr($content,$table);}return$content;}function
parse($text,$postProcess=true){$lineParser=&new
TexyLineParser($this);$lineParser->parse($text,$postProcess);}function
appendChild(&$child,$innerText=NULL){$this->contentType=max($this->contentType,$child->contentType);if(is_a($child,'TexyInlineTagElement')){$keyOpen=Texy::hashKey($child->contentType,true);$keyClose=Texy::hashKey($child->contentType,false);$this->children[$keyOpen]=&$child;$this->children[$keyClose]=&$child;return$keyOpen.$innerText.$keyClose;}$key=Texy::hashKey($child->contentType);$this->children[$key]=&$child;return$key;}}class
TexyInlineTagElement
extends
TexyHTMLElement{var$_closingTag;function
toHTML($opening){if($opening){$this->generateTags($tags);if($this->hidden)return;$this->_closingTag=Texy::closingTags($tags);return
Texy::openingTags($tags);}else{return$this->_closingTag;}}}class
TexyDOM
extends
TexyBlockElement{var$elements;var$elementsById;var$elementsByClass;function
parse($text){$text=Texy::wash($text);$text=str_replace("\r\n",TEXY_NEWLINE,$text);$text=str_replace("\r",TEXY_NEWLINE,$text);$tabWidth=$this->texy->tabWidth;while(strpos($text,"\t")!==false)$text=preg_replace_callback('#^(.*)\t#mU',create_function('&$matches',"return \$matches[1] . str_repeat(' ', $tabWidth - strlen(\$matches[1]) % $tabWidth);"),$text);$commentChars=$this->texy->utf?"\xC2\xA7":"\xA7";$text=preg_replace('#'.$commentChars.'{2,}(?!'.$commentChars.').*('.$commentChars.'{2,}|$)(?!'.$commentChars.')#mU','',$text);$text=preg_replace("#[\t ]+$#m",'',$text);foreach($this->texy->modules
as$name=>$foo)$this->texy->modules->$name->preProcess($text);parent::parse($text);}function
toHTML(){$html=parent::toHTML();foreach($this->texy->modules
as$name=>$foo)$this->texy->modules->$name->postProcess($html);$html=Texy::unfreezeSpaces($html);$html=Texy::checkEntities($html);if(!defined('TEXY_NOTICE_SHOWED')){$html.="\n<!-- generated by Texy! -->";define('TEXY_NOTICE_SHOWED',true);}return$html;}function
buildLists(){$this->elements=array();$this->elementsById=array();$this->elementsByClass=array();$this->broadcast();}}class
TexyDOMLine
extends
TexyTextualElement{var$elements;var$elementsById;var$elementsByClass;function
parse($text){$text=Texy::wash($text);$text=rtrim(strtr($text,array("\n"=>' ',"\r"=>'')));parent::parse($text);}function
toHTML(){$html=parent::toHTML();$html=Texy::unfreezeSpaces($html);$html=Texy::checkEntities($html);return$html;}function
buildLists(){$this->elements=array();$this->elementsById=array();$this->elementsByClass=array();$this->broadcast();}} 

class
TexyModule{var$texy;var$allowed=TEXY_ALL;function
__construct(&$texy){$this->texy=&$texy;}function
TexyModule(&$texy){call_user_func_array(array(&$this,'__construct'),array(&$texy));}function
init(){}function
preProcess(&$text){}function
postProcess(&$text){}function
linePostProcess(&$line){}function
registerLinePattern($func,$pattern,$user_args=null){$this->texy->patternsLine[]=array('handler'=>array(&$this,$func),'pattern'=>$this->texy->translatePattern($pattern),'user'=>$user_args);}function
registerBlockPattern($func,$pattern,$user_args=null){$this->texy->patternsBlock[]=array('handler'=>array(&$this,$func),'pattern'=>$this->texy->translatePattern($pattern).'m','user'=>$user_args);}} 

class
TexyBlockModule
extends
TexyModule{var$allowed;var$codeHandler;var$divHandler;var$htmlHandler;function
TexyBlockModule(&$texy){parent::__construct($texy);$this->allowed->pre=true;$this->allowed->text=true;$this->allowed->html=true;$this->allowed->div=true;$this->allowed->form=true;$this->allowed->source=true;$this->allowed->comment=true;}function
init(){if(isset($this->userFunction))$this->codeHandler=$this->userFunction;$this->registerBlockPattern('processBlock','#^/--+ *(?:(code|samp|text|html|div|form|notexy|source|comment)( .*)?|) *<MODIFIER_H>?\n(.*\n)?\\\\--+ *\\1?()$#mUsi');}function
processBlock(&$blockParser,&$matches){list($match,$mType,$mSecond,$mMod1,$mMod2,$mMod3,$mMod4,$mContent)=$matches;$mType=trim(strtolower($mType));$mSecond=trim(strtolower($mSecond));$mContent=trim($mContent,"\n");if(!$mType)$mType='pre';if($mType=='notexy')$mType='html';if($mType=='html'&&!$this->allowed->html)$mType='text';if($mType=='code'||$mType=='samp')$mType=$this->allowed->pre?$mType:'none';elseif(!$this->allowed->$mType)$mType='none';switch($mType){case'none':case'div':$el=&new
TexyBlockElement($this->texy);$el->tag='div';$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);if($spaces=strspn($mContent,' '))$mContent=preg_replace("#^ {1,$spaces}#m",'',$mContent);if($this->divHandler)call_user_func_array($this->divHandler,array(&$el,&$mContent));$el->parse($mContent);$blockParser->element->appendChild($el);break;case'source':$el=&new
TexySourceBlockElement($this->texy);$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);if($spaces=strspn($mContent,' '))$mContent=preg_replace("#^ {1,$spaces}#m",'',$mContent);$el->parse($mContent);$blockParser->element->appendChild($el);break;case'form':$el=&new
TexyFormElement($this->texy);$el->action->set($mSecond);$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);if($spaces=strspn($mContent,' '))$mContent=preg_replace("#^ {1,$spaces}#m",'',$mContent);$el->parse($mContent);$blockParser->element->appendChild($el);break;case'comment':break;case'html':$el=&new
TexyTextualElement($this->texy);if($this->htmlHandler)call_user_func_array($this->htmlHandler,array(&$el,true));$old=$this->texy->patternsLine;$this->texy->patternsLine=array();$this->texy->htmlModule->init();$el->parse($mContent,false);$this->texy->patternsLine=$old;$blockParser->element->appendChild($el);break;case'text':$el=&new
TexyTextualElement($this->texy);$el->setContent((nl2br(Texy::htmlChars($mContent))),true);$blockParser->element->appendChild($el);if($this->htmlHandler)call_user_func_array($this->htmlHandler,array(&$el,false));break;default:$el=&new
TexyCodeBlockElement($this->texy);$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->type=$mType;$el->lang=$mSecond;if($spaces=strspn($mContent,' '))$mContent=preg_replace("#^ {1,$spaces}#m",'',$mContent);$el->setContent($mContent,false);$blockParser->element->appendChild($el);if($this->codeHandler)call_user_func_array($this->codeHandler,array(&$el));}}function
trustMode(){$this->allowed->form=true;}function
safeMode(){$this->allowed->form=false;}}class
TexyCodeBlockElement
extends
TexyTextualElement{var$tag='pre';var$lang;var$type;function
generateTags(&$tags){parent::generateTags($tags);if($this->tag){$tags[$this->tag]['class'][]=$this->lang;if($this->type)$tags[$this->type]=array();}}}class
TexySourceBlockElement
extends
TexyBlockElement{var$tag='pre';function
generateContent(){$html=parent::generateContent();if($this->texy->formatterModule)$this->texy->formatterModule->indent($html);$el=&new
TexyCodeBlockElement($this->texy);$el->lang='html';$el->type='code';$el->setContent($html,false);if($this->texy->blockModule->codeHandler)call_user_func_array($this->texy->blockModule->codeHandler,array(&$el));return$el->safeContent();}}class
TexyFormElement
extends
TexyBlockElement{var$tag='form';var$action;var$post=true;function
__construct(&$texy){parent::__construct($texy);$this->action=&$texy->createURL();}function
generateTags(&$tags){parent::generateTags($tags);$attrs=&$tags['form'];if($this->action->URL)$attrs['action']=$this->action->URL;$attrs['method']=$this->post?'post':'get';$attrs['enctype']=$this->post?'multipart/form-data':'';}} 



class
TexyListModule
extends
TexyModule{var$allowed=array('*'=>true,'-'=>true,'+'=>true,'1.'=>true,'1)'=>true,'I.'=>true,'I)'=>true,'a)'=>true,'A)'=>true,);var$translate=array('*'=>array('\*','','','ul'),'-'=>array('\-','','','ul'),'+'=>array('\+','','','ul'),'1.'=>array('\d+\.\ ','','','ol'),'1)'=>array('\d+\)','','','ol'),'I.'=>array('[IVX]+\.\ ','','upper-roman','ol'),'I)'=>array('[IVX]+\)','','upper-roman','ol'),'a)'=>array('[a-z]\)','','lower-alpha','ol'),'A)'=>array('[A-Z]\)','','upper-alpha','ol'),);function
init(){$bullets=array();foreach($this->allowed
as$bullet=>$allowed)if($allowed)$bullets[]=$this->translate[$bullet][0];$this->registerBlockPattern('processBlock','#^(?:<MODIFIER_H>\n)?'.'('.implode('|',$bullets).')(\n?)\ +\S.*$#mU');}function
processBlock(&$blockParser,&$matches){list($match,$mMod1,$mMod2,$mMod3,$mMod4,$mBullet,$mNewLine)=$matches;$texy=&$this->texy;$el=&new
TexyListElement($texy);$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$bullet='';foreach($this->translate
as$type)if(preg_match('#'.$type[0].'#A',$mBullet)){$bullet=$type[0];$el->tag=$type[3];$el->modifier->styles['list-style-type']=$type[2];$el->modifier->classes[]=$type[1];break;}$blockParser->moveBackward($mNewLine?2:1);$count=0;while($elItem=&$this->processItem($blockParser,$bullet)){$el->children[]=&$elItem;$count++;}if(!$count)return
false;else$blockParser->element->appendChild($el);}function&processItem(&$blockParser,$bullet,$indented=false){$texy=&$this->texy;$spacesBase=$indented?('\ {1,}'):'';$patternItem=$texy->translatePattern("#^\n?($spacesBase)$bullet(\n?)(\ +)(\S.*)?<MODIFIER_H>?()$#mAU");if(!$blockParser->receiveNext($patternItem,$matches)){$false=false;return$false;}list($match,$mIndent,$mNewLine,$mSpace,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$elItem=&new
TexyListItemElement($texy);$elItem->tag='li';$elItem->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$spaces=$mNewLine?strlen($mSpace):'';$content=' '.$mContent;while($blockParser->receiveNext('#^(\n*)'.$mIndent.'(\ {1,'.$spaces.'})(.*)()$#Am',$matches)){list($match,$mBlank,$mSpaces,$mContent)=$matches;if($spaces==='')$spaces=strlen($mSpaces);$content.=TEXY_NEWLINE.$mBlank.$mContent;}$mergeMode=&$texy->genericBlock[0]->mergeMode;$tmp=$mergeMode;$mergeMode=false;$elItem->parse($content);$mergeMode=$tmp;if(is_a($elItem->children[0],'TexyGenericBlockElement'))$elItem->children[0]->tag='';return$elItem;}}class
TexyListElement
extends
TexyBlockElement{}class
TexyListItemElement
extends
TexyBlockElement{} 
class
TexyDefinitionListModule
extends
TexyListModule{var$allowed=array('*'=>true,'-'=>true,'+'=>true,);var$translate=array('*'=>array('\*',''),'-'=>array('\-',''),'+'=>array('\+',''),);function
init(){$bullets=array();foreach($this->allowed
as$bullet=>$allowed)if($allowed)$bullets[]=$this->translate[$bullet][0];$this->registerBlockPattern('processBlock','#^(?:<MODIFIER_H>\n)?'.'(\S.*)\:\ *<MODIFIER_H>?\n'.'(\ +)('.implode('|',$bullets).')\ +\S.*$#mU');}function
processBlock(&$blockParser,&$matches){list($match,$mMod1,$mMod2,$mMod3,$mMod4,$mContentTerm,$mModTerm1,$mModTerm2,$mModTerm3,$mModTerm4,$mSpaces,$mBullet)=$matches;$texy=&$this->texy;$el=&new
TexyListElement($texy);$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tag='dl';$bullet='';foreach($this->translate
as$type)if(preg_match('#'.$type[0].'#A',$mBullet)){$bullet=$type[0];$el->modifier->classes[]=$type[1];break;}$blockParser->element->appendChild($el);$blockParser->moveBackward(2);$patternTerm=$texy->translatePattern('#^\n?(\S.*)\:\ *<MODIFIER_H>?()$#mUA');$bullet=preg_quote($mBullet);while(true){if($elItem=&$this->processItem($blockParser,preg_quote($mBullet),true)){$elItem->tag='dd';$el->children[]=&$elItem;continue;}if($blockParser->receiveNext($patternTerm,$matches)){list($match,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$elItem=&new
TexyTextualElement($texy);$elItem->tag='dt';$elItem->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$elItem->parse($mContent);$el->children[]=&$elItem;continue;}break;}}} 

class
TexyFormatterModule
extends
TexyModule{var$baseIndent=0;var$lineWrap=80;var$indent=true;var$wellForm=true;var$tagStack;var$tagUsed;var$dontNestElements=array('a'=>array('a'),'pre'=>array('img','object','big','small','sub','sup'),'button'=>array('input','select','textarea','label','button','form','fieldset','iframe','isindex'),'label'=>array('label'),'form'=>array('form'),);var$autoCloseElements=array('tbody'=>array('thead','tbody','tfoot','colgoup'),'colgroup'=>array('thead','tbody','tfoot','colgoup'),'dd'=>array('dt','dd'),'dt'=>array('dt','dd'),'li'=>array('li'),'option'=>array('option'),'p'=>array('address','blockquote','div','dl','fieldset','form','h1','h2','h3','h4','h5','h6','hr','legend','object','ol','p','pre','table','ul'),'td'=>array('th','td','tr','thead','tbody','tfoot','colgoup'),'tfoot'=>array('thead','tbody','tfoot','colgoup'),'th'=>array('th','td','tr','thead','tbody','tfoot','colgoup'),'thead'=>array('thead','tbody','tfoot','colgoup'),'tr'=>array('tr','thead','tbody','tfoot','colgoup'),);var$hashTable=array();function
TexyFormatterModule(&$texy){parent::__construct($texy);foreach($this->autoCloseElements
as$key=>$value)$this->autoCloseElements[$key]=array_flip($value);$this->TEXY_EMPTY_ELEMENTS=unserialize(TEXY_EMPTY_ELEMENTS);$this->TEXY_BLOCK_ELEMENTS=unserialize(TEXY_BLOCK_ELEMENTS);}function
postProcess(&$text){if($this->wellForm)$this->wellForm($text);if($this->indent)$this->indent($text);}function
wellForm(&$text){$this->tagStack=array();$this->tagUsed=array();$text=preg_replace_callback('#<(/?)([a-z_:][a-z0-9._:-]*)(|\s.*)(/?)>()#Uis',array(&$this,'_replaceWellForm'),$text);if($this->tagStack){$pair=end($this->tagStack);while($pair!==false){$text.='</'.$pair->tag.'>';$pair=prev($this->tagStack);}}}function
_replaceWellForm(&$matches){list($match,$mClosing,$mTag,$mAttr,$mEmpty)=$matches;if(isset($this->TEXY_EMPTY_ELEMENTS[$mTag])||$mEmpty)return$mClosing?'':'<'.$mTag.$mAttr.' />';if($mClosing){$pair=end($this->tagStack);$s='';$i=1;while($pair!==false){$s.='</'.$pair->tag.'>';if($pair->tag==$mTag)break;$this->tagUsed[$pair->tag]--;$pair=prev($this->tagStack);$i++;}if($pair===false)return'';if(isset($this->TEXY_BLOCK_ELEMENTS[$mTag])){array_splice($this->tagStack,-$i);return$s;}unset($this->tagStack[key($this->tagStack)]);$pair=current($this->tagStack);while($pair!==false){$s.='<'.$pair->tag.$pair->attr.'>';@$this->tagUsed[$pair->tag]++;$pair=next($this->tagStack);}return$s;}else{$s='';$pair=end($this->tagStack);while($pair&&isset($this->autoCloseElements[$pair->tag])&&isset($this->autoCloseElements[$pair->tag][$mTag])){$s.='</'.$pair->tag.'>';$this->tagUsed[$pair->tag]--;unset($this->tagStack[key($this->tagStack)]);$pair=end($this->tagStack);}unset($pair);$pair->attr=$mAttr;$pair->tag=$mTag;$this->tagStack[]=$pair;@$this->tagUsed[$pair->tag]++;$s.='<'.$mTag.$mAttr.'>';return$s;}}function
indent(&$text){$this->_indent=$this->baseIndent;$text=preg_replace_callback('#<(pre|textarea|script|style)(.*)</\\1>#Uis',array(&$this,'_freeze'),$text);$text=str_replace("\n",'',$text);$text=preg_replace('# +#',' ',$text);$text=preg_replace_callback('# *<(/?)('.implode(array_keys($this->TEXY_BLOCK_ELEMENTS),'|').'|br)(>| [^>]*>) *#i',array(&$this,'_replaceReformat'),$text);$text=preg_replace("#[\t ]+(\n|\r|$)#",'$1',$text);$text=strtr($text,array("\r\r"=>"\n","\r"=>"\n"));$text=strtr($text,array("\t\x08"=>'',"\x08"=>''));if($this->lineWrap>0)$text=preg_replace_callback('#^(\t*)(.*)$#m',array(&$this,'_replaceWrapLines'),$text);$text=strtr($text,$this->hashTable);}function
_freeze(&$matches){$key='<'.$matches[1].'>'.Texy::hashKey().'</'.$matches[1].'>';$this->hashTable[$key]=$matches[0];return$key;}function
_replaceReformat(&$matches){list($match,$mClosing,$mTag)=$matches;$match=trim($match);$mTag=strtolower($mTag);if($mTag==='br')return"\n".str_repeat("\t",max(0,$this->_indent-1)).$match;if(isset($this->TEXY_EMPTY_ELEMENTS[$mTag]))return"\r".str_repeat("\t",$this->_indent).$match."\r".str_repeat("\t",$this->_indent);if($mClosing==='/'){return"\x08".$match."\n".str_repeat("\t",--$this->_indent);}return"\n".str_repeat("\t",$this->_indent++).$match;}function
_replaceWrapLines(&$matches){list($match,$mSpace,$mContent)=$matches;return$mSpace.str_replace("\n","\n".$mSpace,wordwrap($mContent,$this->lineWrap));}} 

class
TexyGenericBlockModule
extends
TexyModule{var$mergeMode=true;function
init(){$this->texy->genericBlock=array(&$this,'processBlock');}function
processBlock(&$blockParser,$content){$str_blocks=$this->mergeMode?preg_split('#(\n{2,})#',$content):preg_split('#(\n(?! )|\n{2,})#',$content);foreach($str_blocks
as$str){$str=trim($str);if($str=='')continue;$this->processSingleBlock($blockParser,$str);}}function
processSingleBlock(&$blockParser,$content){preg_match($this->texy->translatePattern('#^(.*)<MODIFIER_H>?(\n.*)?()$#sU'),$content,$matches);list($match,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mContent2)=$matches;$mContent=trim($mContent.$mContent2);if($this->texy->mergeLines){$mContent=preg_replace('#\n (\S)#'," \r\\1",$mContent);$mContent=strtr($mContent,"\n\r"," \n");}$el=&new
TexyGenericBlockElement($this->texy);$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->parse($mContent);$blockParser->element->appendChild($el);if($el->contentType==TEXY_CONTENT_TEXTUAL)$el->tag='p';elseif($mMod1||$mMod2||$mMod3||$mMod4)$el->tag='div';elseif($el->contentType==TEXY_CONTENT_BLOCK)$el->tag='';else$el->tag='div';if($el->tag&&(strpos($el->content,"\n")!==false)){$elBr=&new
TexyLineBreakElement($this->texy);$el->content=strtr($el->content,array("\n"=>$el->appendChild($elBr)));}}}class
TexyLineBreakElement
extends
TexyTextualElement{var$tag='br';}class
TexyGenericBlockElement
extends
TexyTextualElement{var$tag='p';} 

define('TEXY_HEADING_DYNAMIC',1);define('TEXY_HEADING_FIXED',2);class
TexyHeadingModule
extends
TexyModule{var$allowed;var$top=1;var$title;var$balancing=TEXY_HEADING_DYNAMIC;var$levels=array('#'=>0,'*'=>1,'='=>2,'-'=>3,);var$_rangeUnderline;var$_deltaUnderline;var$_rangeSurround;var$_deltaSurround;function
TexyHeadingModule(&$texy){parent::__construct($texy);$this->allowed->surrounded=true;$this->allowed->underlined=true;}function
init(){if($this->allowed->underlined)$this->registerBlockPattern('processBlockUnderline','#^(\S.*)<MODIFIER_H>?\n'.'(\#|\*|\=|\-){3,}$#mU');if($this->allowed->surrounded)$this->registerBlockPattern('processBlockSurround','#^((\#|\=){2,})(?!\\2)(.+)\\2*<MODIFIER_H>?()$#mU');}function
preProcess(&$text){$this->_rangeUnderline=array(10,0);$this->_rangeSurround=array(10,0);$this->title=null;unset($this->_deltaUnderline);unset($this->_deltaSurround);}function
processBlockUnderline(&$blockParser,&$matches){list($match,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mLine)=$matches;$el=&new
TexyHeadingElement($this->texy);$el->level=$this->levels[$mLine];if($this->balancing==TEXY_HEADING_DYNAMIC)$el->deltaLevel=&$this->_deltaUnderline;$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->parse(trim($mContent));$blockParser->element->appendChild($el);if($this->title===null)$this->title=strip_tags($el->toHTML());$this->_rangeUnderline[0]=min($this->_rangeUnderline[0],$el->level);$this->_rangeUnderline[1]=max($this->_rangeUnderline[1],$el->level);$this->_deltaUnderline=-$this->_rangeUnderline[0];$this->_deltaSurround=-$this->_rangeSurround[0]+($this->_rangeUnderline[1]?($this->_rangeUnderline[1]-$this->_rangeUnderline[0]+1):0);}function
processBlockSurround(&$blockParser,&$matches){list($match,$mLine,$mChar,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$el=&new
TexyHeadingElement($this->texy);$el->level=7-min(7,max(2,strlen($mLine)));if($this->balancing==TEXY_HEADING_DYNAMIC)$el->deltaLevel=&$this->_deltaSurround;$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->parse(trim($mContent));$blockParser->element->appendChild($el);if($this->title===null)$this->title=strip_tags($el->toHTML());$this->_rangeSurround[0]=min($this->_rangeSurround[0],$el->level);$this->_rangeSurround[1]=max($this->_rangeSurround[1],$el->level);$this->_deltaSurround=-$this->_rangeSurround[0]+($this->_rangeUnderline[1]?($this->_rangeUnderline[1]-$this->_rangeUnderline[0]+1):0);}}class
TexyHeadingElement
extends
TexyTextualElement{var$level=0;var$deltaLevel=0;function
generateTags(&$tags){parent::generateTags($tags,'h'.min(6,max(1,$this->level+$this->deltaLevel+$this->texy->headingModule->top)));}} 

class
TexyHorizLineModule
extends
TexyModule{function
init(){$this->registerBlockPattern('processBlock','#^(\- |\-|\* |\*){3,}\ *<MODIFIER_H>?()$#mU');}function
processBlock(&$blockParser,&$matches){list($match,$mLine,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$el=&new
TexyHorizLineElement($this->texy);$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$blockParser->element->appendChild($el);}}class
TexyHorizLineElement
extends
TexyBlockElement{var$tag='hr';} 

class
TexyHtmlModule
extends
TexyModule{var$allowed;var$allowedComments=true;var$safeTags=array('a'=>array('href','rel','title','lang'),'abbr'=>array('title','lang'),'acronym'=>array('title','lang'),'b'=>array('title','lang'),'br'=>array(),'cite'=>array('title','lang'),'code'=>array('title','lang'),'dfn'=>array('title','lang'),'em'=>array('title','lang'),'i'=>array('title','lang'),'kbd'=>array('title','lang'),'q'=>array('cite','title','lang'),'samp'=>array('title','lang'),'small'=>array('title','lang'),'span'=>array('title','lang'),'strong'=>array('title','lang'),'sub'=>array('title','lang'),'sup'=>array('title','lang'),'var'=>array('title','lang'),);function
TexyHtmlModule(&$texy){parent::__construct($texy);$this->allowed=&$texy->allowedTags;}function
init(){$this->registerLinePattern('processTag','#<(/?)([a-z][a-z0-9_:-]*)(|\s(?:[\sa-z0-9:-]|=\s*"[^":HASH:]*"|=\s*\'[^\':HASH:]*\'|=[^>:HASH:]*)*)(/?)>#is');$this->registerLinePattern('processComment','#<!--([^:HASH:]*)-->#Uis');}function
processTag(&$lineParser,&$matches){list($match,$mClosing,$mTag,$mAttr,$mEmpty)=$matches;if(!$this->allowed)return$match;static$TEXY_INLINE_ELEMENTS,$TEXY_EMPTY_ELEMENTS,$TEXY_VALID_ELEMENTS;if(!$TEXY_INLINE_ELEMENTS)$TEXY_INLINE_ELEMENTS=unserialize(TEXY_INLINE_ELEMENTS);if(!$TEXY_EMPTY_ELEMENTS)$TEXY_EMPTY_ELEMENTS=unserialize(TEXY_EMPTY_ELEMENTS);if(!$TEXY_VALID_ELEMENTS)$TEXY_VALID_ELEMENTS=unserialize(TEXY_VALID_ELEMENTS);$tag=strtolower($mTag);if(!isset($TEXY_VALID_ELEMENTS[$tag]))$tag=$mTag;$empty=($mEmpty=='/')||isset($TEXY_EMPTY_ELEMENTS[$tag]);$closing=$mClosing=='/';if($empty&&$closing)return$match;if(is_array($this->allowed)&&!isset($this->allowed[$tag]))return$match;$el=&new
TexyHtmlTagElement($this->texy);$el->contentType=isset($TEXY_INLINE_ELEMENTS[$tag])?TEXY_CONTENT_NONE:TEXY_CONTENT_BLOCK;if(!$closing){$attrs=array();$allowedAttrs=is_array($this->allowed)?$this->allowed[$tag]:null;preg_match_all('#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is',$mAttr,$matchesAttr,PREG_SET_ORDER);foreach($matchesAttr
as$matchAttr){$key=strtolower($matchAttr[1]);if(is_array($allowedAttrs)&&!in_array($key,$allowedAttrs))continue;$value=$matchAttr[2];if($value==null)$value=$key;elseif($value{0}=='\''||$value{0}=='"')$value=substr($value,1,-1);$attrs[$key]=$value;}$modifier=&$this->texy->createModifier();if(isset($attrs['class'])){$modifier->parseClasses($attrs['class']);$attrs['class']=$modifier->classes;}if(isset($attrs['style'])){$modifier->parseStyles($attrs['style']);$attrs['style']=$modifier->styles;}if(isset($attrs['id'])){if(!$this->texy->allowedClasses)unset($attrs['id']);elseif(is_array($this->texy->allowedClasses)&&!in_array('#'.$attrs['id'],$this->texy->allowedClasses))unset($attrs['id']);}switch($tag){case'img':if(!isset($attrs['src']))return$match;$link=&$this->texy->createURL();$link->set($attrs['src'],TEXY_URL_IMAGE_INLINE);$this->texy->summary->images[]=$attrs['src']=$link->URL;break;case'a':if(!isset($attrs['href'])&&!isset($attrs['name'])&&!isset($attrs['id']))return$match;if(isset($attrs['href'])){$link=&$this->texy->createURL();$link->set($attrs['href']);$this->texy->summary->links[]=$attrs['href']=$link->URL;}}if($empty)$attrs[TEXY_EMPTY]=true;$el->tags[$tag]=$attrs;$el->closing=false;}else{$el->tags[$tag]=false;$el->closing=true;}return$lineParser->element->appendChild($el);}function
processComment(&$lineParser,&$matches){if(!$this->allowedComments)return'';$el=&new
TexyTextualElement($this->texy);$el->contentType=TEXY_CONTENT_NONE;$el->setContent($matches[0],TRUE);return$lineParser->element->appendChild($el);}function
trustMode($onlyValidTags=true){$this->allowed=$onlyValidTags?unserialize(TEXY_VALID_ELEMENTS):TEXY_ALL;}function
safeMode($allowSafeTags=true){$this->allowed=$allowSafeTags?$this->safeTags:TEXY_NONE;}}class
TexyHtmlTagElement
extends
TexyDOMElement{var$tags;var$closing;function
toHTML(){if($this->hidden)return;if($this->closing)return
Texy::closingTags($this->tags);else
return
Texy::openingTags($this->tags);}} 

define('TEXY_PATTERN_IMAGE','\[\*([^\n'.TEXY_HASH.']+)'.TEXY_PATTERN_MODIFIER.'? *(\*|>|<)\]');class
TexyImageModule
extends
TexyModule{var$root='images/';var$linkedRoot='images/';var$rootPrefix='';var$leftClass=null;var$rightClass=null;var$defaultAlt='';function
TexyImageModule(&$texy){parent::__construct($texy);}function
init(){Texy::adjustDir($this->root);Texy::adjustDir($this->linkedRoot);Texy::adjustDir($this->rootPrefix);$this->registerLinePattern('processLine','#'.TEXY_PATTERN_IMAGE.TEXY_PATTERN_LINK_N.'??()#U');}function
addReference($name,&$obj){$this->texy->addReference('*'.$name.'*',$obj);}function&getReference($name){$el=$this->texy->getReference('*'.$name.'*');if(is_a($el,'TexyImageReference'))return$el;else{$false=false;return$false;}}function
preProcess(&$text){$text=preg_replace_callback('#^\[\*([^\n]+)\*\]:\ +(.+)\ *'.TEXY_PATTERN_MODIFIER.'?()$#mU',array(&$this,'processReferenceDefinition'),$text);}function
processReferenceDefinition(&$matches){list($match,$mRef,$mUrls,$mMod1,$mMod2,$mMod3)=$matches;$elRef=&new
TexyImageReference($this->texy,$mUrls);$elRef->modifier->setProperties($mMod1,$mMod2,$mMod3);$this->addReference($mRef,$elRef);return'';}function
processLine(&$lineParser,&$matches){if(!$this->allowed)return'';list($match,$mURLs,$mMod1,$mMod2,$mMod3,$mMod4,$mLink)=$matches;$elImage=&new
TexyImageElement($this->texy);$elImage->setImagesRaw($mURLs);$elImage->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);if($mLink){$elLink=&new
TexyLinkElement($this->texy);if($mLink==':'){$elImage->requireLinkImage();$elLink->link->copyFrom($elImage->linkImage);}else{$elLink->setLinkRaw($mLink);}return$lineParser->element->appendChild($elLink,$lineParser->element->appendChild($elImage));}return$lineParser->element->appendChild($elImage);}}class
TexyImageReference{var$URLs;var$modifier;function
TexyImageReference(&$texy,$URLs=null){$this->modifier=&$texy->createModifier();$this->URLs=$URLs;}}class
TexyImageElement
extends
TexyHTMLElement{var$parentModule;var$image;var$overImage;var$linkImage;var$width,$height;function
__construct(&$texy){parent::__construct($texy);$this->parentModule=&$texy->imageModule;$this->image=&$texy->createURL();$this->image->root=$this->parentModule->root;$this->overImage=&$texy->createURL();$this->overImage->root=$this->parentModule->root;$this->linkImage=&$texy->createURL();$this->linkImage->root=$this->parentModule->linkedRoot;}function
setImages($URL=null,$URL_over=null,$URL_link=null){if($URL)$this->image->set($URL,TEXY_URL_IMAGE_INLINE);else$this->image->clear();if($URL_over)$this->overImage->set($URL_over,TEXY_URL_IMAGE_INLINE);else$this->overImage->clear();if($URL_link)$this->linkImage->set($URL_link,TEXY_URL_IMAGE_LINKED);else$this->linkImage->clear();}function
setSize($width,$height){$this->width=abs((int)$width);$this->height=abs((int)$height);}function
setImagesRaw($URLs){$elRef=&$this->parentModule->getReference(trim($URLs));if($elRef){$URLs=$elRef->URLs;$this->modifier->copyFrom($elRef->modifier);}$URLs=explode('|',$URLs.'||');if(preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U',$URLs[0],$matches)){$URLs[0]=$matches[1];$this->setSize($matches[2],$matches[3]);}$this->setImages($URLs[0],$URLs[1],$URLs[2]);}function
generateTags(&$tags){if($this->image->URL==null)return;$attrs=$this->modifier->getAttrs('img');$attrs['class']=$this->modifier->classes;$attrs['style']=$this->modifier->styles;$attrs['id']=$this->modifier->id;if($this->modifier->hAlign==TEXY_HALIGN_LEFT){if($this->parentModule->leftClass!='')$attrs['class'][]=$this->parentModule->leftClass;else$attrs['style']['float']='left';}elseif($this->modifier->hAlign==TEXY_HALIGN_RIGHT){if($this->parentModule->rightClass!='')$attrs['class'][]=$this->parentModule->rightClass;else$attrs['style']['float']='right';}if($this->modifier->vAlign)$attrs['style']['vertical-align']=$this->modifier->vAlign;$this->requireSize();if($this->width)$attrs['width']=$this->width;if($this->height)$attrs['height']=$this->height;$this->texy->summary->images[]=$attrs['src']=$this->image->URL;if($this->overImage->URL){$attrs['onmouseover']='this.src=\''.$this->overImage->URL.'\'';$attrs['onmouseout']='this.src=\''.$this->image->URL.'\'';$this->texy->summary->preload[]=$this->overImage->URL;}$attrs['alt']=$this->modifier->title?$this->modifier->title:$this->parentModule->defaultAlt;$tags['img']=$attrs;}function
requireSize(){if($this->width)return;$file=$this->parentModule->rootPrefix.$this->image->URL;if(!is_file($file))return
false;$size=getImageSize($file);if(!is_array($size))return
false;$this->setSize($size[0],$size[1]);}function
requireLinkImage(){if($this->linkImage->URL==null)$this->linkImage->set($this->image->text,TEXY_URL_IMAGE_LINKED);}} 

class
TexyImageDescModule
extends
TexyModule{var$boxClass='image';var$leftClass='image left';var$rightClass='image right';function
init(){if($this->texy->imageModule->allowed)$this->registerBlockPattern('processBlock','#^'.TEXY_PATTERN_IMAGE.TEXY_PATTERN_LINK_N.'?? +\*\*\* +(.*)<MODIFIER_H>?()$#mU');}function
processBlock(&$blockParser,&$matches){list($match,$mURLs,$mImgMod1,$mImgMod2,$mImgMod3,$mImgMod4,$mLink,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$el=&new
TexyImageDescElement($this->texy);$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$elImage=&new
TexyImageElement($this->texy);$elImage->setImagesRaw($mURLs);$elImage->modifier->setProperties($mImgMod1,$mImgMod2,$mImgMod3,$mImgMod4);$el->modifier->hAlign=$elImage->modifier->hAlign;$elImage->modifier->hAlign=null;$content=$el->appendChild($elImage);if($mLink){$elLink=&new
TexyLinkElement($this->texy);if($mLink==':'){$elImage->requireLinkImage();$elLink->link->copyFrom($elImage->linkImage);}else{$elLink->setLinkRaw($mLink);}$content=$el->appendChild($elLink,$content);}$elDesc=&new
TexyGenericBlockElement($this->texy);$elDesc->parse(ltrim($mContent));$content.=$el->appendChild($elDesc);$el->setContent($content,true);$blockParser->element->appendChild($el);}}class
TexyImageDescElement
extends
TexyTextualElement{function
generateTags(&$tags){$attrs=$this->modifier->getAttrs('div');$attrs['class']=$this->modifier->classes;$attrs['style']=$this->modifier->styles;$attrs['id']=$this->modifier->id;if($this->modifier->hAlign==TEXY_HALIGN_LEFT){$attrs['class'][]=$this->texy->imageDescModule->leftClass;}elseif($this->modifier->hAlign==TEXY_HALIGN_RIGHT){$attrs['class'][]=$this->texy->imageDescModule->rightClass;}elseif($this->texy->imageDescModule->boxClass)$attrs['class'][]=$this->texy->imageDescModule->boxClass;$tags['div']=$attrs;}} 

class
TexyLinkModule
extends
TexyModule{var$allowed;var$root='';var$emailOnClick=null;var$imageOnClick='return !popupImage(this.href)';var$popupOnClick='return !popup(this.href)';var$forceNoFollow=false;function
TexyLinkModule(&$texy){parent::__construct($texy);$this->allowed->link=true;$this->allowed->email=true;$this->allowed->url=true;$this->allowed->quickLink=true;$this->allowed->references=true;}function
init(){Texy::adjustDir($this->root);if($this->allowed->quickLink)$this->registerLinePattern('processLineQuick','#([:CHAR:0-9@\#$%&.,_-]+)(?=:\[)<LINK>()#U<UTF>');$this->registerLinePattern('processLineReference','#('.TEXY_PATTERN_LINK_REF.')#U');if($this->allowed->url)$this->registerLinePattern('processLineURL','#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#i<UTF>');if($this->allowed->email)$this->registerLinePattern('processLineURL','#(?<=\s|^|\(|\[|\<|:)'.TEXY_PATTERN_EMAIL.'#i');}function
addReference($name,&$obj){$this->texy->addReference($name,$obj);}function&getReference($refName){$el=&$this->texy->getReference($refName);$query='';if(!$el){$queryPos=strpos($refName,'?');if($queryPos===false)$queryPos=strpos($refName,'#');if($queryPos!==false){$el=&$this->texy->getReference(substr($refName,0,$queryPos));$query=substr($refName,$queryPos);}}$false=false;if(!is_a($el,'TexyLinkReference'))return$false;$el->query=$query;return$el;}function
preProcess(&$text){if($this->allowed->references)$text=preg_replace_callback('#^\[([^\[\]\#\?\*\n]+)\]: +('.TEXY_PATTERN_LINK_IMAGE.'|(?!\[)\S+)(\ .+)?'.TEXY_PATTERN_MODIFIER.'?()$#mU',array(&$this,'processReferenceDefinition'),$text);}function
processReferenceDefinition(&$matches){list($match,$mRef,$mLink,$mLabel,$mMod1,$mMod2,$mMod3)=$matches;$elRef=&new
TexyLinkReference($this->texy,$mLink,$mLabel);$elRef->modifier->setProperties($mMod1,$mMod2,$mMod3);$this->addReference($mRef,$elRef);return'';}function
processLineQuick(&$lineParser,&$matches){list($match,$mContent,$mLink)=$matches;if(!$this->allowed->quickLink)return$mContent;$elLink=&new
TexyLinkElement($this->texy);$elLink->setLinkRaw($mLink);return$lineParser->element->appendChild($elLink,$mContent);}function
processLineReference(&$lineParser,&$matches){list($match,$mRef)=$matches;if(!$this->allowed->link)return$match;$elLink=&new
TexyLinkRefElement($this->texy);if($elLink->setLink($mRef)===false)return$match;return$lineParser->element->appendChild($elLink);}function
processLineURL(&$lineParser,&$matches){list($mURL)=$matches;$elLink=&new
TexyLinkElement($this->texy);$elLink->setLinkRaw($mURL);return$lineParser->element->appendChild($elLink,$elLink->link->toString());}}class
TexyLinkReference{var$URL;var$query;var$label;var$modifier;function
TexyLinkReference(&$texy,$URL=null,$label=null){$this->modifier=&$texy->createModifier();if(strlen($URL)>1)if($URL{0}=='\''||$URL{0}=='"')$URL=substr($URL,1,-1);$this->URL=trim($URL);$this->label=trim($label);}}class
TexyLinkElement
extends
TexyInlineTagElement{var$link;var$nofollow=false;function
__construct(&$texy){parent::__construct($texy);$this->link=&$texy->createURL();$this->link->root=$texy->linkModule->root;}function
setLink($URL){$this->link->set($URL);}function
setLinkRaw($link){if(@$link{0}=='['&&@$link{1}!='*'){$elRef=&$this->texy->linkModule->getReference(substr($link,1,-1));if($elRef){$this->modifier->copyFrom($elRef->modifier);$link=$elRef->URL.$elRef->query;}else{$this->setLink(substr($link,1,-1));return;}}$l=strlen($link);if(@$link{0}=='['&&@$link{1}=='*'){$elImage=&new
TexyImageElement($this->texy);$elImage->setImagesRaw(substr($link,2,-2));$elImage->requireLinkImage();$this->link->copyFrom($elImage->linkImage);return;}$this->setLink($link);}function
generateTags(&$tags){if($this->link->URL==null)return;$attrs=$this->modifier->getAttrs('a');$this->texy->summary->links[]=$attrs['href']=$this->link->URL;$nofollowClass=in_array('nofollow',$this->modifier->unfilteredClasses);if(($this->link->type&TEXY_URL_ABSOLUTE)&&($nofollowClass||$this->nofollow||$this->texy->linkModule->forceNoFollow))$attrs['rel']='nofollow';$attrs['id']=$this->modifier->id;$attrs['title']=$this->modifier->title;$attrs['class']=$this->modifier->classes;$attrs['style']=$this->modifier->styles;if($nofollowClass){if(($pos=array_search('nofollow',$attrs['class']))!==false)unset($attrs['class'][$pos]);}$popup=in_array('popup',$this->modifier->unfilteredClasses);if($popup){if(($pos=array_search('popup',$attrs['class']))!==false)unset($attrs['class'][$pos]);$attrs['onclick']=$this->texy->linkModule->popupOnClick;}if($this->link->type&TEXY_URL_EMAIL)$attrs['onclick']=$this->texy->linkModule->emailOnClick;if($this->link->type&TEXY_URL_IMAGE_LINKED)$attrs['onclick']=$this->texy->linkModule->imageOnClick;$tags['a']=$attrs;}}class
TexyLinkRefElement
extends
TexyTextualElement{var$refName;var$contentType=TEXY_CONTENT_TEXTUAL;function
setLink($refRaw){$this->refName=substr($refRaw,1,-1);$elRef=&$this->texy->linkModule->getReference($this->refName);if(!$elRef)return
false;$this->texy->_preventCycling=true;$elLink=&new
TexyLinkElement($this->texy);$elLink->setLinkRaw($refRaw);if($elRef->label){$this->parse($elRef->label);}else{$this->setContent($elLink->link->toString(),true);}$this->content=$this->appendChild($elLink,$this->content);$this->texy->_preventCycling=false;}} 

class
TexyLongWordsModule
extends
TexyModule{var$wordLimit=20;var$shy='&#173;';var$nbsp='&#160;';function
linePostProcess(&$text){if(!$this->allowed)return;$charShy=$this->texy->utf?"\xC2\xAD":"\xAD";$charNbsp=$this->texy->utf?"\xC2\xA0":"\xA0";$text=strtr($text,array('&shy;'=>$charShy,'&#173;'=>$charShy,'&nbsp;'=>$charNbsp,'&#160;'=>$charNbsp,));$text=preg_replace_callback($this->texy->translatePattern('#[^\ \n\t\-\xAD'.TEXY_HASH_SPACES.']{'.$this->wordLimit.',}#<UTF>'),array(&$this,'_replace'),$text);$text=strtr($text,array($charShy=>$this->shy,$charNbsp=>$this->nbsp,));}function
_replace(&$matches){list($mWord)=$matches;$chars=array();preg_match_all($this->texy->translatePattern('#&\\#?[a-z0-9]+;|[:HASH:]+|.#<UTF>'),$mWord,$chars);$chars=$chars[0];if(count($chars)<$this->wordLimit)return$mWord;$consonants=array_flip(array('b','c','d','f','g','h','j','k','l','m','n','p','q','r','s','t','v','w','x','y','z','B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Y','Z','è','ï','ò','ø','š','','ý','ž','È','Ï','Ò','Ø','Š','','Ý','Ž','Ä','Ä','Åˆ','Å™','Å¡','Å¥','Ã½','Å¾','ÄŒ','ÄŽ','Å‡','Å˜','Å ','Å¤','Ã','Å½'));$vowels=array_flip(array('a','e','i','o','y','u','A','E','I','O','Y','U','á','é','ì','í','ó','ý','ú','ù','Á','É','Ì','Í','Ó','Ý','Ú','Ù','Ã¡','Ã©','Ä›','Ã­','Ã³','Ã½','Ãº','Å¯','Ã','Ã‰','Äš','Ã','Ã“','Ã','Ãš','Å®'));$before_r=array_flip(array('b','B','c','C','d','D','f','F','g','G','k','K','p','P','r','R','t','T','v','V','è','È','ï','Ï','ø','Ø','','','Ä','ÄŒ','Ä','ÄŽ','Åt','Å_','Å¥','Å¤',));$before_l=array_flip(array('b','B','c','C','d','D','f','F','g','G','k','K','l','L','p','P','t','T','v','V','è','È','ï','Ï','','','Ä','ÄŒ','Ä','ÄŽ','Å¥','Å¤',));$before_h=array_flip(array('c','C','s','S'));$doubleVowels=array_flip(array('a','A','o','O'));$DONT=0;$HERE=1;$AFTER=2;$s=array();$trans=array();$s[]='';$trans[]=-1;$hashCounter=$len=$counter=0;foreach($chars
as$key=>$char){if(ord($char{0})<32)continue;$s[]=$char;$trans[]=$key;}$s[]='';$len=count($s)-2;$positions=array();$a=1;$last=1;while($a<$len){$hyphen=$DONT;do{if($s[$a]=='.'){$hyphen=$HERE;break;}if(isset($consonants[$s[$a]])){if(isset($vowels[$s[$a+1]])){if(isset($vowels[$s[$a-1]]))$hyphen=$HERE;break;}if(($s[$a]=='s')&&($s[$a-1]=='n')&&isset($consonants[$s[$a+1]])){$hyphen=$AFTER;break;}if(isset($consonants[$s[$a+1]])&&isset($vowels[$s[$a-1]])){if($s[$a+1]=='r'){$hyphen=isset($before_r[$s[$a]])?$HERE:$AFTER;break;}if($s[$a+1]=='l'){$hyphen=isset($before_l[$s[$a]])?$HERE:$AFTER;break;}if($s[$a+1]=='h'){$hyphen=isset($before_h[$s[$a]])?$DONT:$AFTER;break;}$hyphen=$AFTER;break;}break;}if(($s[$a]=='u')&&isset($doubleVowels[$s[$a-1]])){$hyphen=$AFTER;break;}if(in_array($s[$a],$vowels)&&isset($vowels[$s[$a-1]])){$hyphen=$HERE;break;}}while(0);if($hyphen==$DONT&&($a-$last>$this->wordLimit*0.6))$positions[]=$last=$a-1;if($hyphen==$HERE)$positions[]=$last=$a-1;if($hyphen==$AFTER){$positions[]=$last=$a;$a++;}$a++;}$a=end($positions);if(($a==$len-1)&&isset($consonants[$s[$len]]))array_pop($positions);$syllables=array();$last=0;foreach($positions
as$pos){if($pos-$last>$this->wordLimit*0.6){$syllables[]=implode('',array_splice($chars,0,$trans[$pos]-$trans[$last]));$last=$pos;}}$syllables[]=implode('',$chars);$charShy=$this->texy->utf?"\xC2\xAD":"\xAD";$charNbsp=$this->texy->utf?"\xC2\xA0":"\xA0";$text=implode($charShy,$syllables);$text=strtr($text,array($charShy.$charNbsp=>' ',$charNbsp.$charShy=>' '));return$text;}} 

class
TexyPhraseModule
extends
TexyModule{var$allowed=array('***'=>'strong em','**'=>'strong','*'=>'em','++'=>'ins','--'=>'del','^^'=>'sup','__'=>'sub','"'=>'span','~'=>'span','~~'=>'cite','""()'=>'acronym','()'=>'acronym','`'=>'code','``'=>'',);var$codeHandler;function
init(){if(@$this->allowed['***']!==false)$this->registerLinePattern('processPhrase','#(?<!\*)\*\*\*(?!\ |\*)(.+)<MODIFIER>?(?<!\ |\*)\*\*\*(?!\*)()<LINK>??()#U',$this->allowed['***']);if(@$this->allowed['**']!==false)$this->registerLinePattern('processPhrase','#(?<!\*)\*\*(?!\ |\*)(.+)<MODIFIER>?(?<!\ |\*)\*\*(?!\*)<LINK>??()#U',$this->allowed['**']);if(@$this->allowed['*']!==false)$this->registerLinePattern('processPhrase','#(?<!\*)\*(?!\ |\*)(.+)<MODIFIER>?(?<!\ |\*)\*(?!\*)<LINK>??()#U',$this->allowed['*']);if(@$this->allowed['++']!==false)$this->registerLinePattern('processPhrase','#(?<!\+)\+\+(?!\ |\+)(.+)<MODIFIER>?(?<!\ |\+)\+\+(?!\+)()#U',$this->allowed['++']);if(@$this->allowed['--']!==false)$this->registerLinePattern('processPhrase','#(?<!\-)\-\-(?!\ |\-)(.+)<MODIFIER>?(?<!\ |\-)\-\-(?!\-)()#U',$this->allowed['--']);if(@$this->allowed['^^']!==false)$this->registerLinePattern('processPhrase','#(?<!\^)\^\^(?!\ |\^)(.+)<MODIFIER>?(?<!\ |\^)\^\^(?!\^)()#U',$this->allowed['^^']);if(@$this->allowed['__']!==false)$this->registerLinePattern('processPhrase','#(?<!\_)\_\_(?!\ |\_)(.+)<MODIFIER>?(?<!\ |\_)\_\_(?!\_)()#U',$this->allowed['__']);if(@$this->allowed['"']!==false)$this->registerLinePattern('processPhrase','#(?<!\")\"(?!\ )([^\"]+)<MODIFIER>?(?<!\ )\"(?!\")<LINK>??()#U',$this->allowed['"']);if(@$this->allowed['~']!==false)$this->registerLinePattern('processPhrase','#(?<!\~)\~(?!\ )([^\~]+)<MODIFIER>?(?<!\ )\~(?!\~)<LINK>??()#U',$this->allowed['~']);if(@$this->allowed['~~']!==false)$this->registerLinePattern('processPhrase','#(?<!\~)\~\~(?!\ |\~)(.+)<MODIFIER>?(?<!\ |\~)\~\~(?!\~)<LINK>??()#U',$this->allowed['~~']);if(@$this->allowed['""()']!==false)$this->registerLinePattern('processPhrase','#(?<!\")\"(?!\ )([^\"]+)<MODIFIER>?(?<!\ )\"(?!\")\(\((.+)\)\)()#U',$this->allowed['""()']);if(@$this->allowed['()']!==false)$this->registerLinePattern('processPhrase','#(?<![:CHAR:])([:CHAR:]{2,})()()()\(\((.+)\)\)#U<UTF>',$this->allowed['()']);if(@$this->allowed['``']!==false)$this->registerLinePattern('processProtect','#\`\`(\S[^:HASH:]*)(?<!\ )\`\`()#U',false);if(@$this->allowed['`']!==false)$this->registerLinePattern('processCode','#\`(\S[^:HASH:]*)<MODIFIER>?(?<!\ )\`()#U');$this->registerBlockPattern('processBlock','#^`=(none|code|kbd|samp|var|span)$#mUi');}function
processPhrase(&$lineParser,&$matches,$tags){list($match,$mContent,$mMod1,$mMod2,$mMod3,$mLink)=$matches;if($mContent==null){preg_match('#^(.)+(.+)'.TEXY_PATTERN_MODIFIER.'?\\1+()$#U',$match,$matches);list($match,$mDelim,$mContent,$mMod1,$mMod2,$mMod3,$mLink)=$matches;}if(($tags=='span')&&$mLink)$tags='';if(($tags=='span')&&!$mMod1&&!$mMod2&&!$mMod3)return$match;$tags=array_reverse(explode(' ',$tags));$el=null;foreach($tags
as$tag){$el=&new
TexyInlineTagElement($this->texy);$el->tag=$tag;if($tag=='acronym'||$tag=='abbr'){$el->modifier->title=$mLink;$mLink='';}$mContent=$lineParser->element->appendChild($el,$mContent);}if($mLink){$el=&new
TexyLinkElement($this->texy);$el->setLinkRaw($mLink);$mContent=$lineParser->element->appendChild($el,$mContent);}if($el)$el->modifier->setProperties($mMod1,$mMod2,$mMod3);return$mContent;}function
processBlock(&$blockParser,&$matches){list($match,$mTag)=$matches;$this->allowed['`']=strtolower($mTag);if($this->allowed['`']=='none')$this->allowed['`']='';}function
processCode(&$lineParser,&$matches){list($match,$mContent,$mMod1,$mMod2,$mMod3)=$matches;$texy=&$this->texy;$el=&new
TexyTextualElement($texy);$el->modifier->setProperties($mMod1,$mMod2,$mMod3);$el->contentType=TEXY_CONTENT_TEXTUAL;$el->setContent($mContent,false);$el->tag=$this->allowed['`'];if($this->codeHandler)call_user_func_array($this->codeHandler,array(&$el));$el->safeContent();return$lineParser->element->appendChild($el);}function
processProtect(&$lineParser,&$matches,$isHtmlSafe=false){list($match,$mContent)=$matches;$el=&new
TexyTextualElement($this->texy);$el->contentType=TEXY_CONTENT_TEXTUAL;$el->setContent(Texy::freezeSpaces($mContent),$isHtmlSafe);return$lineParser->element->appendChild($el);}} 

class
TexyQuickCorrectModule
extends
TexyModule{var$doubleQuotes=array('&#8222;','&#8220;');var$singleQuotes=array('&#8218;','&#8216;');var$dash='&#8211;';function
linePostProcess(&$text){if(!$this->allowed)return;$replaceTmp=array('#(?<!"|\w)"(?!\ |")(.+)(?<!\ |")"(?!")()#U'=>$this->doubleQuotes[0].'$1'.$this->doubleQuotes[1],'#(?<!\'|\w)\'(?!\ |\')(.+)(?<!\ |\')\'(?!\')()#U<UTF>'=>$this->singleQuotes[0].'$1'.$this->singleQuotes[1],'#(\S|^) ?\.{3}#m'=>'$1&#8230;','#(\d| )-(\d| )#'=>"\$1$this->dash\$2",'#,-#'=>",$this->dash",'#(?<!\d)(\d{1,2}\.) (\d{1,2}\.) (\d\d)#'=>'$1&#160;$2&#160;$3','#(?<!\d)(\d{1,2}\.) (\d{1,2}\.)#'=>'$1&#160;$2','# -- #'=>" $this->dash ",'# -&gt; #'=>' &#8594; ','# &lt;- #'=>' &#8592; ','# &lt;-&gt; #'=>' &#8596; ','#(\d+) ?x ?(\d+) ?x ?(\d+)#'=>'$1&#215;$2&#215;$3','#(\d+) ?x ?(\d+)#'=>'$1&#215;$2','#(?<=\d)x(?= |,|.|$)#m'=>'&#215;','#(\S ?)\(TM\)#i'=>'$1&#8482;','#(\S ?)\(R\)#i'=>'$1&#174;','#\(C\)( ?\S)#i'=>'&#169;$1','#(\d{1,3}) (\d{3}) (\d{3}) (\d{3})#'=>'$1&#160;$2&#160;$3&#160;$4','#(\d{1,3}) (\d{3}) (\d{3})#'=>'$1&#160;$2&#160;$3','#(\d{1,3}) (\d{3})#'=>'$1&#160;$2','#(?<=^| |\.|,|-|\+)(\d+)([:HASHSOFT:]*) ([:HASHSOFT:]*)([:CHAR:])#m<UTF>'=>'$1$2&#160;$3$4','#(?<=^|[^0-9:CHAR:])([:HASHSOFT:]*)([ksvzouiKSVZOUIA])([:HASHSOFT:]*) ([:HASHSOFT:]*)([0-9:CHAR:])#m<UTF>'=>'$1$2$3&#160;$4$5',);$replace=array();foreach($replaceTmp
as$pattern=>$replacement)$replace[$this->texy->translatePattern($pattern)]=$replacement;$text=preg_replace(array_keys($replace),array_values($replace),$text);}} 

class
TexyQuoteModule
extends
TexyModule{var$allowed;function
TexyQuoteModule(&$texy){parent::__construct($texy);$this->allowed->line=true;$this->allowed->block=true;}function
init(){if($this->allowed->block)$this->registerBlockPattern('processBlock','#^(?:<MODIFIER_H>\n)?>(\ +|:)(\S.*)$#mU');if($this->allowed->line)$this->registerLinePattern('processLine','#(?<!\>)(\>\>)(?!\ |\>)(.+)<MODIFIER>?(?<!\ |\<)\<\<(?!\<)<LINK>??()#U','q');}function
processLine(&$lineParser,&$matches,$tag){list($match,$mMark,$mContent,$mMod1,$mMod2,$mMod3,$mLink)=$matches;$texy=&$this->texy;$el=&new
TexyQuoteElement($texy);$el->modifier->setProperties($mMod1,$mMod2,$mMod3);if($mLink)$el->cite->set($mLink);return$lineParser->element->appendChild($el,$mContent);}function
processBlock(&$blockParser,&$matches){list($match,$mMod1,$mMod2,$mMod3,$mMod4,$mSpaces,$mContent)=$matches;$texy=&$this->texy;$el=&new
TexyBlockQuoteElement($texy);$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$blockParser->element->appendChild($el);$content='';$linkTarget='';$spaces='';do{if($mSpaces==':')$linkTarget=trim($mContent);else{if($spaces==='')$spaces=max(1,strlen($mSpaces));$content.=$mContent.TEXY_NEWLINE;}if(!$blockParser->receiveNext("#^>(?:|(\ {1,$spaces}|:)(.*))()$#mA",$matches))break;list($match,$mSpaces,$mContent)=$matches;}while(true);if($linkTarget){$elx=&new
TexyLinkElement($this->texy);$elx->setLinkRaw($linkTarget);$el->cite->set($elx->link->URL);}$el->parse($content);}}class
TexyBlockQuoteElement
extends
TexyBlockElement{var$cite;function
__construct(&$texy){parent::__construct($texy);$this->cite=&$texy->createURL();}function
generateTags(&$tags){parent::generateTags($tags,'blockquote');$tags['blockquote']['cite']=$this->cite->URL;}}class
TexyQuoteElement
extends
TexyInlineTagElement{var$cite;function
__construct(&$texy){parent::__construct($texy);$this->cite=&$texy->createURL();}function
generateTags(&$tags){parent::generateTags($tags,'q');$tags['q']['cite']=$this->cite->URL;}} 

class
TexyScriptModule
extends
TexyModule{var$handler;function
init(){$this->registerLinePattern('processLine','#\{\{([^:HASH:]+)\}\}()#U');}function
processLine(&$lineParser,&$matches,$tag){list($match,$mContent)=$matches;$identifier=trim($mContent);if($identifier==='')return;$args=null;if(preg_match('#^([a-z_][a-z0-9_]*)\s*\(([^()]*)\)$#i',$identifier,$matches)){$identifier=$matches[1];array_walk($args=explode(',',$matches[2]),'trim');}$el=&new
TexyScriptElement($this->texy);do{if($this->handler===null)break;if(is_object($this->handler)){if($args===null&&isset($this->handler->$identifier)){$el->setContent($this->handler->$identifier);break;}if(is_array($args)&&is_callable(array(&$this->handler,$identifier))){array_unshift($args,null);$args[0]=&$el;call_user_func_array(array(&$this->handler,$identifier),$args);break;}break;}if(is_callable($this->handler))call_user_func_array($this->handler,array(&$el,$identifier,$args));}while(0);return$lineParser->element->appendChild($el);}function
defaultHandler(&$element,$identifier,$args){if($args)$identifier.='('.implode(',',$args).')';$element->setContent('<texy:script content="'.Texy::htmlChars($identifier,true).'" />',true);}}class
TexyScriptElement
extends
TexyTextualElement{} 

class
TexyTableModule
extends
TexyModule{var$oddClass='';var$evenClass='';var$isHead;var$colModifier;var$last;var$row;function
init(){$this->registerBlockPattern('processBlock','#^(?:<MODIFIER_HV>\n)?'.'\|.*()$#mU');}function
processBlock(&$blockParser,&$matches){list($match,$mMod1,$mMod2,$mMod3,$mMod4,$mMod5)=$matches;$texy=&$this->texy;$el=&new
TexyTableElement($texy);$el->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4,$mMod5);$blockParser->element->appendChild($el);$blockParser->moveBackward();if($blockParser->receiveNext('#^\|(\#|\=){2,}(?!\\1)(.*)\\1*\|? *'.TEXY_PATTERN_MODIFIER_H.'?()$#Um',$matches)){list($match,$mChar,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$el->caption=&new
TexyTextualElement($texy);$el->caption->tag='caption';$el->caption->parse($mContent);$el->caption->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);}$this->isHead=false;$this->colModifier=array();$this->last=array();$this->row=0;while(true){if($blockParser->receiveNext('#^\|\-{3,}$#Um',$matches)){$this->isHead=!$this->isHead;continue;}if($elRow=&$this->processRow($blockParser)){$el->children[$this->row++]=&$elRow;continue;}break;}}function&processRow(&$blockParser){$texy=&$this->texy;if(!$blockParser->receiveNext('#^\|(.*)(?:|\|\ *'.TEXY_PATTERN_MODIFIER_HV.'?)()$#U',$matches)){$false=false;return$false;}list($match,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mMod5)=$matches;$elRow=&new
TexyTableRowElement($this->texy);$elRow->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4,$mMod5);if($this->row
%
2==0){if($this->oddClass)$elRow->modifier->classes[]=$this->oddClass;}else{if($this->evenClass)$elRow->modifier->classes[]=$this->evenClass;}$col=0;$elField=null;foreach(explode('|',$mContent)as$field){if(($field=='')&&$elField){$elField->colSpan++;unset($this->last[$col]);$col++;continue;}$field=rtrim($field);if($field=='^'){if(isset($this->last[$col])){$this->last[$col]->rowSpan++;$col+=$this->last[$col]->colSpan;continue;}}if(!preg_match('#(\*??)\ *'.TEXY_PATTERN_MODIFIER_HV.'??(.*)'.TEXY_PATTERN_MODIFIER_HV.'?()$#AU',$field,$matches))continue;list($match,$mHead,$mModCol1,$mModCol2,$mModCol3,$mModCol4,$mModCol5,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mMod5)=$matches;if($mModCol1||$mModCol2||$mModCol3||$mModCol4||$mModCol5){$this->colModifier[$col]=&$texy->createModifier();$this->colModifier[$col]->setProperties($mModCol1,$mModCol2,$mModCol3,$mModCol4,$mModCol5);}$elField=&new
TexyTableFieldElement($texy);$elField->isHead=($this->isHead||($mHead=='*'));if(isset($this->colModifier[$col]))$elField->modifier->copyFrom($this->colModifier[$col]);$elField->modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4,$mMod5);$elField->parse($mContent);$elRow->children[$col]=&$elField;$this->last[$col]=&$elField;$col++;}return$elRow;}}class
TexyTableElement
extends
TexyBlockElement{var$tag='table';var$caption;function
generateContent(){$html=parent::generateContent();if($this->caption)$html=$this->caption->toHTML().$html;return$html;}}class
TexyTableRowElement
extends
TexyBlockElement{var$tag='tr';}class
TexyTableFieldElement
extends
TexyTextualElement{var$colSpan=1;var$rowSpan=1;var$isHead;function
generateTags(&$tags){$tag=$this->isHead?'th':'td';parent::generateTags($tags,$tag);if($this->colSpan<>1)$tags[$tag]['colspan']=(int)$this->colSpan;if($this->rowSpan<>1)$tags[$tag]['rowspan']=(int)$this->rowSpan;}function
generateContent(){$html=parent::generateContent();return$html==''?'&#160;':$html;}} 

class
TexySmiliesModule
extends
TexyModule{var$allowed=false;var$icons=array(':-)'=>'smile.gif',':-('=>'sad.gif',';-)'=>'wink.gif',':-D'=>'biggrin.gif','8-O'=>'eek.gif','8-)'=>'cool.gif',':-?'=>'confused.gif',':-x'=>'mad.gif',':-P'=>'razz.gif',':-|'=>'neutral.gif',);var$root='images/smilies/';var$class='';function
init(){Texy::adjustDir($this->root);if($this->allowed){krsort($this->icons);$pattern=array();foreach($this->icons
as$key=>$value)$pattern[]=preg_quote($key).'+';$crazyRE='#(?<=^|[\\x00-\\x20])('.implode('|',$pattern).')#';$this->registerLinePattern('processLine',$crazyRE);}}function
processLine(&$lineParser,&$matches){$match=&$matches[0];$texy=&$this->texy;$el=&new
TexyImageElement($texy);$el->modifier->title=$match;$el->modifier->classes[]=$this->class;$el->image->root=$this->root;foreach($this->icons
as$key=>$value)if(substr($match,0,strlen($key))==$key){$el->image->set($value);break;}return$lineParser->element->appendChild($el);}} 
class
Texy{var$utf=false;var$tabWidth=8;var$allowedClasses;var$allowedStyles;var$allowedTags;var$obfuscateEmail=true;var$referenceHandler;var$modules;var$DOM;var$summary;var$styleSheet;var$mergeLines=true;var$inited;var$patternsLine=array();var$patternsBlock=array();var$genericBlock;var$references=array();var$_preventCycling=false;function
__construct(){$this->summary->images=array();$this->summary->links=array();$this->summary->preload=array();$this->styleSheet='';$this->allowedClasses=TEXY_ALL;$this->allowedStyles=TEXY_ALL;$this->allowedTags=unserialize(TEXY_VALID_ELEMENTS);$this->loadModules();$elRef=&new
TexyLinkReference($this,'http://www.texy.info/','Texy!');$elRef->modifier->title='Text to HTML converter and formatter';$this->addReference('texy',$elRef);}function
Texy(){$args=func_get_args();call_user_func_array(array(&$this,'__construct'),$args);}function
loadModules(){$this->registerModule('TexyScriptModule');$this->registerModule('TexyHtmlModule');$this->registerModule('TexyImageModule');$this->registerModule('TexyLinkModule');$this->registerModule('TexyPhraseModule');$this->registerModule('TexySmiliesModule');$this->registerModule('TexyBlockModule');$this->registerModule('TexyHeadingModule');$this->registerModule('TexyHorizLineModule');$this->registerModule('TexyQuoteModule');$this->registerModule('TexyListModule');$this->registerModule('TexyDefinitionListModule');$this->registerModule('TexyTableModule');$this->registerModule('TexyImageDescModule');$this->registerModule('TexyGenericBlockModule');$this->registerModule('TexyQuickCorrectModule');$this->registerModule('TexyLongWordsModule');$this->registerModule('TexyFormatterModule');}function
registerModule($className,$shortName=null){if(isset($this->modules->$className))return
false;$this->modules->$className=&new$className($this);if($shortName===null){$shortName=(substr($className,0,4)==='Texy')?substr($className,4):$className;$shortName{0}=strtolower($shortName{0});}if(!isset($this->$shortName))$this->$shortName=&$this->modules->$className;}function
init(){$GLOBALS['Texy__$hashCounter']=0;$this->refQueries=array();if($this->inited)return;if(!$this->modules)die('Texy: No modules installed');foreach($this->modules
as$name=>$foo)$this->modules->$name->init();$this->inited=true;}function
reinit(){$this->patternsLine=array();$this->patternsBlock=array();$this->genericBlock=null;$this->inited=false;$this->init();}function
process($text,$singleLine=false){if($singleLine)$this->parseLine($text);else$this->parse($text);return$this->DOM->toHTML();}function
parse($text){$this->init();$this->DOM=&new
TexyDOM($this);$this->DOM->parse($text);}function
parseLine($text){$this->init();$this->DOM=&new
TexyDOMLine($this);$this->DOM->parse($text);}function
toHTML(){return$this->DOM->toHTML();}function
toText(){$saveLineWrap=$this->formatterModule->lineWrap=false;$this->formatterModule->lineWrap=false;$text=$this->toHTML();$this->formatterModule->lineWrap=$saveLineWrap;$text=preg_replace('#<(script|style)(.*)</\\1>#Uis','',$text);$text=strip_tags($text);$text=preg_replace('#\n\s*\n\s*\n[\n\s]*\n#',"\n\n",$text);if((int)PHP_VERSION>4&&$this->utf){$text=html_entity_decode($text,ENT_QUOTES,'UTF-8');}else{$text=strtr($text,array('&amp;'=>'&#38;','&quot;'=>'&#34;','&lt;'=>'&#60;','&gt;'=>'&#62;'));$text=preg_replace_callback('#&(\\#x[0-9a-fA-F]+|\\#[0-9]+);#',array(&$this,'_entityCallback'),$text);}$text=strtr($text,array($this->utf?"\xC2\xAD":"\xAD"=>'',$this->utf?"\xC2\xA0":"\xA0"=>' ',));return$text;}function
_entityCallback($matches){list(,$entity)=$matches;$ord=($entity{1}=='x')?hexdec(substr($entity,2)):(int)substr($entity,1);if($ord<128)return
chr($ord);if($this->utf){if($ord<2048)return
chr(($ord>>6)+192).chr(($ord&63)+128);if($ord<65536)return
chr(($ord>>12)+224).chr((($ord>>6)&63)+128).chr(($ord&63)+128);if($ord<2097152)return
chr(($ord>>18)+240).chr((($ord>>12)&63)+128).chr((($ord>>6)&63)+128).chr(($ord&63)+128);return$match;}if(function_exists('iconv')){return(string)iconv('UCS-2','WINDOWS-1250//TRANSLIT',pack('n',$ord));}return'?';}function
safeMode(){$this->allowedClasses=TEXY_NONE;$this->allowedStyles=TEXY_NONE;$this->htmlModule->safeMode();$this->blockModule->safeMode();$this->imageModule->allowed=false;$this->linkModule->forceNoFollow=true;}function
trustMode(){$this->allowedClasses=TEXY_ALL;$this->allowedStyles=TEXY_ALL;$this->htmlModule->trustMode();$this->blockModule->trustMode();$this->imageModule->allowed=true;$this->linkModule->forceNoFollow=false;}function
htmlChars($s,$inQuotes=false,$entity=false){$s=htmlSpecialChars($s,$inQuotes?ENT_COMPAT:ENT_NOQUOTES);if($entity)return
preg_replace('~&amp;([a-zA-Z0-9]+|#x[0-9a-fA-F]+|#[0-9]+);~','&$1;',$s);else
return$s;}function
checkEntities($html){static$entity=array('&AElig;'=>'&#198;','&Aacute;'=>'&#193;','&Acirc;'=>'&#194;','&Agrave;'=>'&#192;','&Alpha;'=>'&#913;','&Aring;'=>'&#197;','&Atilde;'=>'&#195;','&Auml;'=>'&#196;','&Beta;'=>'&#914;','&Ccedil;'=>'&#199;','&Chi;'=>'&#935;','&Dagger;'=>'&#8225;','&Delta;'=>'&#916;','&ETH;'=>'&#208;','&Eacute;'=>'&#201;','&Ecirc;'=>'&#202;','&Egrave;'=>'&#200;','&Epsilon;'=>'&#917;','&Eta;'=>'&#919;','&Euml;'=>'&#203;','&Gamma;'=>'&#915;','&Iacute;'=>'&#205;','&Icirc;'=>'&#206;','&Igrave;'=>'&#204;','&Iota;'=>'&#921;','&Iuml;'=>'&#207;','&Kappa;'=>'&#922;','&Lambda;'=>'&#923;','&Mu;'=>'&#924;','&Ntilde;'=>'&#209;','&Nu;'=>'&#925;','&OElig;'=>'&#338;','&Oacute;'=>'&#211;','&Ocirc;'=>'&#212;','&Ograve;'=>'&#210;','&Omega;'=>'&#937;','&Omicron;'=>'&#927;','&Oslash;'=>'&#216;','&Otilde;'=>'&#213;','&Ouml;'=>'&#214;','&Phi;'=>'&#934;','&Pi;'=>'&#928;','&Prime;'=>'&#8243;','&Psi;'=>'&#936;','&Rho;'=>'&#929;','&Scaron;'=>'&#352;','&Sigma;'=>'&#931;','&THORN;'=>'&#222;','&Tau;'=>'&#932;','&Theta;'=>'&#920;','&Uacute;'=>'&#218;','&Ucirc;'=>'&#219;','&Ugrave;'=>'&#217;','&Upsilon;'=>'&#933;','&Uuml;'=>'&#220;','&Xi;'=>'&#926;','&Yacute;'=>'&#221;','&Yuml;'=>'&#376;','&Zeta;'=>'&#918;','&aacute;'=>'&#225;','&acirc;'=>'&#226;','&acute;'=>'&#180;','&aelig;'=>'&#230;','&agrave;'=>'&#224;','&alefsym;'=>'&#8501;','&alpha;'=>'&#945;','&amp;'=>'&#38;','&and;'=>'&#8743;','&ang;'=>'&#8736;','&apos;'=>'&#39;','&aring;'=>'&#229;','&asymp;'=>'&#8776;','&atilde;'=>'&#227;','&auml;'=>'&#228;','&bdquo;'=>'&#8222;','&beta;'=>'&#946;','&brvbar;'=>'&#166;','&bull;'=>'&#8226;','&cap;'=>'&#8745;','&ccedil;'=>'&#231;','&cedil;'=>'&#184;','&cent;'=>'&#162;','&chi;'=>'&#967;','&circ;'=>'&#710;','&clubs;'=>'&#9827;','&cong;'=>'&#8773;','&copy;'=>'&#169;','&crarr;'=>'&#8629;','&cup;'=>'&#8746;','&curren;'=>'&#164;','&dArr;'=>'&#8659;','&dagger;'=>'&#8224;','&darr;'=>'&#8595;','&deg;'=>'&#176;','&delta;'=>'&#948;','&diams;'=>'&#9830;','&divide;'=>'&#247;','&eacute;'=>'&#233;','&ecirc;'=>'&#234;','&egrave;'=>'&#232;','&empty;'=>'&#8709;','&emsp;'=>'&#8195;','&ensp;'=>'&#8194;','&epsilon;'=>'&#949;','&equiv;'=>'&#8801;','&eta;'=>'&#951;','&eth;'=>'&#240;','&euml;'=>'&#235;','&euro;'=>'&#8364;','&exist;'=>'&#8707;','&fnof;'=>'&#402;','&forall;'=>'&#8704;','&frac12;'=>'&#189;','&frac14;'=>'&#188;','&frac34;'=>'&#190;','&frasl;'=>'&#8260;','&gamma;'=>'&#947;','&ge;'=>'&#8805;','&gt;'=>'&#62;','&hArr;'=>'&#8660;','&harr;'=>'&#8596;','&hearts;'=>'&#9829;','&hellip;'=>'&#8230;','&iacute;'=>'&#237;','&icirc;'=>'&#238;','&iexcl;'=>'&#161;','&igrave;'=>'&#236;','&image;'=>'&#8465;','&infin;'=>'&#8734;','&int;'=>'&#8747;','&iota;'=>'&#953;','&iquest;'=>'&#191;','&isin;'=>'&#8712;','&iuml;'=>'&#239;','&kappa;'=>'&#954;','&lArr;'=>'&#8656;','&lambda;'=>'&#955;','&lang;'=>'&#9001;','&laquo;'=>'&#171;','&larr;'=>'&#8592;','&lceil;'=>'&#8968;','&ldquo;'=>'&#8220;','&le;'=>'&#8804;','&lfloor;'=>'&#8970;','&lowast;'=>'&#8727;','&loz;'=>'&#9674;','&lrm;'=>'&#8206;','&lsaquo;'=>'&#8249;','&lsquo;'=>'&#8216;','&lt;'=>'&#60;','&macr;'=>'&#175;','&mdash;'=>'&#8212;','&micro;'=>'&#181;','&middot;'=>'&#183;','&minus;'=>'&#8722;','&mu;'=>'&#956;','&nabla;'=>'&#8711;','&nbsp;'=>'&#160;','&ndash;'=>'&#8211;','&ne;'=>'&#8800;','&ni;'=>'&#8715;','&not;'=>'&#172;','&notin;'=>'&#8713;','&nsub;'=>'&#8836;','&ntilde;'=>'&#241;','&nu;'=>'&#957;','&oacute;'=>'&#243;','&ocirc;'=>'&#244;','&oelig;'=>'&#339;','&ograve;'=>'&#242;','&oline;'=>'&#8254;','&omega;'=>'&#969;','&omicron;'=>'&#959;','&oplus;'=>'&#8853;','&or;'=>'&#8744;','&ordf;'=>'&#170;','&ordm;'=>'&#186;','&oslash;'=>'&#248;','&otilde;'=>'&#245;','&otimes;'=>'&#8855;','&ouml;'=>'&#246;','&para;'=>'&#182;','&part;'=>'&#8706;','&permil;'=>'&#8240;','&perp;'=>'&#8869;','&phi;'=>'&#966;','&pi;'=>'&#960;','&piv;'=>'&#982;','&plusmn;'=>'&#177;','&pound;'=>'&#163;','&prime;'=>'&#8242;','&prod;'=>'&#8719;','&prop;'=>'&#8733;','&psi;'=>'&#968;','&quot;'=>'&#34;','&rArr;'=>'&#8658;','&radic;'=>'&#8730;','&rang;'=>'&#9002;','&raquo;'=>'&#187;','&rarr;'=>'&#8594;','&rceil;'=>'&#8969;','&rdquo;'=>'&#8221;','&real;'=>'&#8476;','&reg;'=>'&#174;','&rfloor;'=>'&#8971;','&rho;'=>'&#961;','&rlm;'=>'&#8207;','&rsaquo;'=>'&#8250;','&rsquo;'=>'&#8217;','&sbquo;'=>'&#8218;','&scaron;'=>'&#353;','&sdot;'=>'&#8901;','&sect;'=>'&#167;','&shy;'=>'&#173;','&sigma;'=>'&#963;','&sigmaf;'=>'&#962;','&sim;'=>'&#8764;','&spades;'=>'&#9824;','&sub;'=>'&#8834;','&sube;'=>'&#8838;','&sum;'=>'&#8721;','&sup1;'=>'&#185;','&sup2;'=>'&#178;','&sup3;'=>'&#179;','&sup;'=>'&#8835;','&supe;'=>'&#8839;','&szlig;'=>'&#223;','&tau;'=>'&#964;','&there4;'=>'&#8756;','&theta;'=>'&#952;','&thetasym;'=>'&#977;','&thinsp;'=>'&#8201;','&thorn;'=>'&#254;','&tilde;'=>'&#732;','&times;'=>'&#215;','&trade;'=>'&#8482;','&uArr;'=>'&#8657;','&uacute;'=>'&#250;','&uarr;'=>'&#8593;','&ucirc;'=>'&#251;','&ugrave;'=>'&#249;','&uml;'=>'&#168;','&upsih;'=>'&#978;','&upsilon;'=>'&#965;','&uuml;'=>'&#252;','&weierp;'=>'&#8472;','&xi;'=>'&#958;','&yacute;'=>'&#253;','&yen;'=>'&#165;','&yuml;'=>'&#255;','&zeta;'=>'&#950;','&zwj;'=>'&#8205;','&zwnj;'=>'&#8204;',);static$allowed=array('&#38;'=>'&amp;','&#34;'=>'&quot;','&#60;'=>'&lt;','&#62;'=>'&gt;');$html=strtr($html,$entity);$html=preg_replace('#&([a-zA-Z0-9]+);#','&amp;$1;',$html);return
strtr($html,$allowed);}function&createURL(){$php4_sucks=&new
TexyURL($this);return$php4_sucks;}function&createModifier(){$php4_sucks=&new
TexyModifier($this);return$php4_sucks;}function
openingTags($tags){static$TEXY_EMPTY_ELEMENTS;if(!$TEXY_EMPTY_ELEMENTS)$TEXY_EMPTY_ELEMENTS=unserialize(TEXY_EMPTY_ELEMENTS);$result='';foreach((array)$tags
as$tag=>$attrs){if($tag==null)continue;$empty=isset($TEXY_EMPTY_ELEMENTS[$tag])||isset($attrs[TEXY_EMPTY]);$attrStr='';if(is_array($attrs)){unset($attrs[TEXY_EMPTY]);foreach(array_change_key_case($attrs,CASE_LOWER)as$name=>$value){if(is_array($value)){if($name=='style'){$style=array();foreach(array_change_key_case($value,CASE_LOWER)as$keyS=>$valueS)if($keyS&&($valueS!=='')&&($valueS!==null))$style[]=$keyS.':'.$valueS;$value=implode(';',$style);}else$value=implode(' ',array_unique($value));if($value=='')continue;}if($value===null||$value===false)continue;$value=trim($value);$attrStr.=' '.Texy::htmlChars($name).'="'.Texy::freezeSpaces(Texy::htmlChars($value,true,true)).'"';}}$result.='<'.$tag.$attrStr.($empty?' /':'').'>';}return$result;}function
closingTags($tags){static$TEXY_EMPTY_ELEMENTS;if(!$TEXY_EMPTY_ELEMENTS)$TEXY_EMPTY_ELEMENTS=unserialize(TEXY_EMPTY_ELEMENTS);$result='';foreach(array_reverse((array)$tags,true)as$tag=>$attrs){if($tag=='')continue;if(isset($TEXY_EMPTY_ELEMENTS[$tag])||isset($attrs[TEXY_EMPTY]))continue;$result.='</'.$tag.'>';}return$result;}function
adjustDir(&$name){if($name)$name=rtrim($name,'/\\').'/';}function
freezeSpaces($s){return
strtr($s," \t\r\n","\x15\x16\x17\x18");}function
unfreezeSpaces($s){return
strtr($s,"\x15\x16\x17\x18"," \t\r\n");}function
wash($text){return
strtr($text,"\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F",'           ');}function
hashKey($contentType=null,$opening=null){$border=($contentType==TEXY_CONTENT_NONE)?"\x19":"\x1A";return$border.($opening?"\x1F":"").strtr(base_convert(++$GLOBALS['Texy__$hashCounter'],10,4),'0123',"\x1B\x1C\x1D\x1E").$border;}function
isHashOpening($hash){return$hash{1}=="\x1F";}function
addReference($name,&$obj){$name=strtolower($name);$this->references[$name]=&$obj;}var$refQueries;function&getReference($name){$name=strtolower($name);$false=false;if($this->_preventCycling){if(isset($this->refQueries[$name]))return$false;$this->refQueries[$name]=true;}else$this->refQueries=array();if(isset($this->references[$name]))return$this->references[$name];if($this->referenceHandler){$this->_disableReferences=true;$this->references[$name]=call_user_func_array($this->referenceHandler,array($name,&$this));$this->_disableReferences=false;return$this->references[$name];}return$false;}function
translatePattern($pattern){return
strtr($pattern,array('<MODIFIER_HV>'=>TEXY_PATTERN_MODIFIER_HV,'<MODIFIER_H>'=>TEXY_PATTERN_MODIFIER_H,'<MODIFIER>'=>TEXY_PATTERN_MODIFIER,'<LINK>'=>TEXY_PATTERN_LINK,'<UTF>'=>($this->utf?'u':''),':CHAR:'=>($this->utf?TEXY_CHAR_UTF:TEXY_CHAR),':HASH:'=>TEXY_HASH,':HASHSOFT:'=>TEXY_HASH_NC,));}function
free(){foreach(array_keys(get_object_vars($this))as$key)$this->$key=null;if(PHP_VERSION<5)${'this'.''}=null;}}class
TexyBlockParser{var$element;var$text;var$offset;function
TexyBlockParser(&$element){$this->element=&$element;}function
receiveNext($pattern,&$matches){$ok=preg_match($pattern.'Am',$this->text,$matches,PREG_OFFSET_CAPTURE,$this->offset);if($ok){$this->offset+=strlen($matches[0][0])+1;foreach($matches
as$key=>$value)$matches[$key]=$value[0];}return$ok;}function
moveBackward($linesCount=1){while(--$this->offset>0)if($this->text{$this->offset-1}==TEXY_NEWLINE)if(--$linesCount<1)break;$this->offset=max($this->offset,0);}function
parse($text){$texy=&$this->element->texy;$this->text=&$text;$this->offset=0;$this->element->children=array();$patternKeys=array_keys($texy->patternsBlock);$arrMatches=$arrPos=array();foreach($patternKeys
as$key)$arrPos[$key]=-1;do{$minKey=-1;$minPos=strlen($this->text);if($this->offset>=$minPos)break;foreach($patternKeys
as$index=>$key){if($arrPos[$key]===false)continue;if($arrPos[$key]<$this->offset){$delta=($arrPos[$key]==-2)?1:0;$matches=&$arrMatches[$key];if(preg_match($texy->patternsBlock[$key]['pattern'],$text,$matches,PREG_OFFSET_CAPTURE,$this->offset+$delta)){$arrPos[$key]=$matches[0][1];foreach($matches
as$keyX=>$valueX)$matches[$keyX]=$valueX[0];}else{unset($patternKeys[$index]);continue;}}if($arrPos[$key]===$this->offset){$minKey=$key;break;}if($arrPos[$key]<$minPos){$minPos=$arrPos[$key];$minKey=$key;}}$next=($minKey==-1)?strlen($text):$arrPos[$minKey];if($next>$this->offset){$str=substr($text,$this->offset,$next-$this->offset);$this->offset=$next;call_user_func_array($texy->genericBlock,array(&$this,$str));continue;}$px=&$texy->patternsBlock[$minKey];$matches=&$arrMatches[$minKey];$this->offset=$arrPos[$minKey]+strlen($matches[0])+1;$ok=call_user_func_array($px['handler'],array(&$this,$matches,$px['user']));if($ok===false||($this->offset<=$arrPos[$minKey])){$this->offset=$arrPos[$minKey];$arrPos[$minKey]=-2;continue;}$arrPos[$minKey]=-1;}while(1);}}class
TexyLineParser{var$element;function
TexyLineParser(&$element){$this->element=&$element;}function
parse($text,$postProcess=true){$element=&$this->element;$texy=&$element->texy;$offset=0;$hashStrLen=0;$patternKeys=array_keys($texy->patternsLine);$arrMatches=$arrPos=array();foreach($patternKeys
as$key)$arrPos[$key]=-1;do{$minKey=-1;$minPos=strlen($text);foreach($patternKeys
as$index=>$key){if($arrPos[$key]<$offset){$delta=($arrPos[$key]==-2)?1:0;$matches=&$arrMatches[$key];if(preg_match($texy->patternsLine[$key]['pattern'],$text,$matches,PREG_OFFSET_CAPTURE,$offset+$delta)){if(!strlen($matches[0][0]))continue;$arrPos[$key]=$matches[0][1];foreach($matches
as$keyx=>$value)$matches[$keyx]=$value[0];}else{unset($patternKeys[$index]);continue;}}if($arrPos[$key]==$offset){$minKey=$key;break;}if($arrPos[$key]<$minPos){$minPos=$arrPos[$key];$minKey=$key;}}if($minKey==-1)break;$px=&$texy->patternsLine[$minKey];$offset=$arrPos[$minKey];$replacement=call_user_func_array($px['handler'],array(&$this,$arrMatches[$minKey],$px['user']));$len=strlen($arrMatches[$minKey][0]);$text=substr_replace($text,$replacement,$offset,$len);$delta=strlen($replacement)-$len;foreach($patternKeys
as$key){if($arrPos[$key]<$offset+$len)$arrPos[$key]=-1;else$arrPos[$key]+=$delta;}$arrPos[$minKey]=-2;}while(1);$text=Texy::htmlChars($text,false,true);if($postProcess)foreach($texy->modules
as$name=>$foo)$texy->modules->$name->linePostProcess($text);$element->setContent($text,true);if($element->contentType==TEXY_CONTENT_NONE){$s=trim(preg_replace('#['.TEXY_HASH.']+#','',$text));if(strlen($s))$element->contentType=TEXY_CONTENT_TEXTUAL;}}}?>