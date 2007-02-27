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
 * @version    2.0beta for PHP5 $Revision: 68 $ $Date: 2007-02-27 03:10:08 +0100 (út, 27 II 2007) $
 */if(version_compare(PHP_VERSION,'5.0.0','<'))die('Texy! needs PHP version 5');define('TEXY','Version 2.0beta $Revision: 68 $');define('TEXY_DIR',dirname(__FILE__).'/');

define('TEXY_CHAR','A-Za-z\x{c0}-\x{1eff}');define('TEXY_MARK',"\x01-\x04\x14-\x1F");define('TEXY_MARK_SPACES',"\x01-\x04");define('TEXY_MARK_N',"\x14\x18-\x1F");define('TEXY_MARK_I',"\x15\x18-\x1F");define('TEXY_MARK_T',"\x16\x18-\x1F");define('TEXY_MARK_B',"\x17\x18-\x1F");define('TEXY_MODIFIER','(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??)');define('TEXY_MODIFIER_H','(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??)');define('TEXY_MODIFIER_HV','(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??)');define('TEXY_IMAGE','\[\*([^\n'.TEXY_MARK.']+)'.TEXY_MODIFIER.'? *(\*|>|<)\]');define('TEXY_LINK_REF','\[[^\[\]\*\n'.TEXY_MARK.']+\]');define('TEXY_LINK_URL','(?:\[[^\]\n]+\]|(?!\[)[^\s'.TEXY_MARK.']*?[^:);,.!?\s'.TEXY_MARK.'])');define('TEXY_LINK','(?::('.TEXY_LINK_URL.'))');define('TEXY_LINK_N','(?::('.TEXY_LINK_URL.'|:))');define('TEXY_EMAIL','[a-z0-9.+_-]+@[a-z0-9.+_-]{2,}\.[a-z]{2,}'); 

abstract
class
TexyDomElement{public$texy;public$tags;public
function
__construct($texy){$this->texy=$texy;}abstract
protected
function
contentToHtml();public
function
toHtml(){$start=$end='';if($this->tags)foreach($this->tags
as$el){$start.=$el->startTag();$end=$el->endTag().$end;}return$start.$this->contentToHtml().$end;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}}class
TexyBlockElement
extends
TexyDomElement{public$children=array();protected
function
contentToHtml(){$s='';foreach($this->children
as$child)$s.=$child->toHtml();return$s;}public
function
parse($text){$parser=new
TexyBlockParser($this->texy);$this->children=$parser->parse($text);}}class
TexyTextualElement
extends
TexyDomElement{public$content='';public$protect=FALSE;protected
function
contentToHtml(){if($this->protect){return$this->content;}else{$s=Texy::decode($this->content);foreach($this->texy->getLineModules()as$module)$s=$module->linePostProcess($s);return
Texy::encode($s);}}public
function
parse($text){$parser=new
TexyLineParser($this->texy);$this->content=$parser->parse($text);}}class
TexyParagraphElement
extends
TexyTextualElement{} 

class
TexyGenericBlock{public$texy;public
function
__construct($texy){$this->texy=$texy;}public
function
process($parser,$content){$tx=$this->texy;if($tx->_mergeMode)$parts=preg_split('#(\n{2,})#',$content);else$parts=preg_split('#(\n(?! )|\n{2,})#',$content);foreach($parts
as$content){$content=trim($content);if($content==='')continue;preg_match('#^(.*)'.TEXY_MODIFIER_H.'?(\n.*)?()$#sU',$content,$matches);list(,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mContent2)=$matches;$mContent=trim($mContent.$mContent2);if($tx->mergeLines){$mContent=preg_replace('#\n (\S)#'," \r\\1",$mContent);$mContent=strtr($mContent,"\n\r"," \n");}$el=new
TexyParagraphElement($tx);$el->parse($mContent);$contentType=Texy::CONTENT_NONE;if(strpos($el->content,"\x17")!==FALSE){$contentType=Texy::CONTENT_BLOCK;}elseif(strpos($el->content,"\x16")!==FALSE){$contentType=Texy::CONTENT_TEXTUAL;}else{if(strpos($el->content,"\x15")!==FALSE)$contentType=Texy::CONTENT_INLINE;$s=trim(preg_replace('#['.TEXY_MARK.']+#','',$el->content));if(strlen($s))$contentType=Texy::CONTENT_TEXTUAL;}if($contentType===Texy::CONTENT_TEXTUAL)$tag='p';elseif($mMod1||$mMod2||$mMod3||$mMod4)$tag='div';elseif($contentType===Texy::CONTENT_BLOCK)$tag='';else$tag='div';if($tag&&(strpos($el->content,"\n")!==FALSE)){$key=$tx->mark('<br />',Texy::CONTENT_INLINE);$el->content=strtr($el->content,array("\n"=>$key));}$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate($tx,$tag);$parser->children[]=$el;}}} 

class
TexyHtml{public$_name;public$_empty;public$_childNodes;static
public
function
el($name=NULL,$attrs=NULL){$el=new
self();if(is_array($attrs)){foreach($attrs
as$key=>$value)$el->$key=$value;}$el->_name=$name;$el->_empty=isset(Texy::$emptyTags[$name]);$el->_childNodes=array();return$el;}public
function
setElement($name){$this->_name=$name;$this->_empty=isset(Texy::$emptyTags[$name]);return$this;}public
function
addChild($content){$this->_childNodes[]=$content;return$this;}public
function
__call($m,$args){$this->$m=$args[0];return$this;}public
function
toText($texy){$ct=$this->getContentType();$s=$texy->mark($this->startTag(),$ct);if($this->_empty)return$s;foreach($this->_childNodes
as$val)if($val
instanceof
self)$s.=$val->toText($texy);else$s.=$val;$s.=$texy->mark($this->endTag(),$ct);return$s;}public
function
startTag(){if(!$this->_name)return'';$s='<'.$this->_name;$attrs=(array)$this;unset($attrs['_name'],$attrs['_childNodes'],$attrs['_empty']);foreach($attrs
as$key=>$value){if($value===NULL||$value===FALSE)continue;if($value===TRUE){if(Texy::$xhtml)$s.=' '.$key.'="'.$key.'"';else$s.=' '.$key;continue;}elseif(is_array($value)){$tmp=NULL;foreach($value
as$k=>$v){if($v==NULL)continue;if(is_string($k))$tmp[]=$k.':'.$v;else$tmp[]=$v;}if(!$tmp)continue;$value=implode($key==='style'?';':' ',$tmp);}$value=str_replace(array('&','"','<','>','@'),array('&amp;','&quot;','&lt;','&gt;','&#64;'),$value);$s.=' '.$key.'="'.Texy::freezeSpaces($value).'"';}if(Texy::$xhtml&&$this->_empty)return$s.' />';return$s.'>';}public
function
endTag(){if($this->_name&&!$this->_empty)return'</'.$this->_name.'>';return'';}public
function
getContentType(){if(isset(Texy::$inlineCont[$this->_name]))return
Texy::CONTENT_INLINE;if(isset(Texy::$inlineTags[$this->_name]))return
Texy::CONTENT_NONE;return
Texy::CONTENT_BLOCK;}} 

class
TexyHtmlFormatter{public$baseIndent=0;public$lineWrap=80;public$indent=TRUE;private$space;private$marks;public
function
process($text){$this->space=$this->baseIndent;$this->marks=array();$text=preg_replace_callback('#<(pre|textarea|script|style)(.*)</\\1>#Uis',array($this,'_freeze'),$text);$text=str_replace("\n",' ',$text);$text=preg_replace('# +#',' ',$text);$text=preg_replace_callback('# *<(/?)('.implode(array_keys(Texy::$blockTags),'|').'|br)(>| [^>]*>) *#i',array($this,'indent'),$text);$text=preg_replace("#[\t ]+(\n|\r|$)#",'$1',$text);$text=strtr($text,array("\r\r"=>"\n","\r"=>"\n"));$text=strtr($text,array("\t\x08"=>'',"\x08"=>''));if($this->lineWrap>0)$text=preg_replace_callback('#^(\t*)(.*)$#m',array($this,'wrap'),$text);if($this->marks){$text=str_replace(array_keys($this->marks),array_values($this->marks),$text);}return$text;}private
function
_freeze($matches){static$counter=0;$key='<'.$matches[1].'>'.strtr(base_convert(++$counter,10,8),'01234567',"\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F").'</'.$matches[1].'>';$this->marks[$key]=$matches[0];return$key;}private
function
indent($matches){list($match,$mClosing,$mTag)=$matches;$match=trim($match);$mTag=strtolower($mTag);if($mTag==='br')return"\n".str_repeat("\t",max(0,$this->space-1)).$match;if(isset(Texy::$emptyTags[$mTag]))return"\r".str_repeat("\t",$this->space).$match."\r".str_repeat("\t",$this->space);if($mClosing==='/'){return"\x08".$match."\n".str_repeat("\t",--$this->space);}return"\n".str_repeat("\t",$this->space++).$match;}private
function
wrap($matches){list(,$mSpace,$mContent)=$matches;return$mSpace.str_replace("\n","\n".$mSpace,wordwrap($mContent,$this->lineWrap));}} 

class
TexyHtmlWellForm{private$tagUsed;private$tagStack;private$autoClose=array('tbody'=>array('thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),'colgroup'=>array('thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),'dd'=>array('dt'=>1,'dd'=>1),'dt'=>array('dt'=>1,'dd'=>1),'li'=>array('li'=>1),'option'=>array('option'=>1),'p'=>array('address'=>1,'applet'=>1,'blockquote'=>1,'center'=>1,'dir'=>1,'div'=>1,'dl'=>1,'fieldset'=>1,'form'=>1,'h1'=>1,'h2'=>1,'h3'=>1,'h4'=>1,'h5'=>1,'h6'=>1,'hr'=>1,'isindex'=>1,'menu'=>1,'object'=>1,'ol'=>1,'p'=>1,'pre'=>1,'table'=>1,'ul'=>1),'td'=>array('th'=>1,'td'=>1,'tr'=>1,'thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),'tfoot'=>array('thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),'th'=>array('th'=>1,'td'=>1,'tr'=>1,'thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),'thead'=>array('thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),'tr'=>array('tr'=>1,'thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),);public
function
process($text){$this->tagStack=array();$this->tagUsed=array();$text=preg_replace_callback('#<(/?)([a-z][a-z0-9._:-]*)(|\s.*)(/?)>()#Uis',array($this,'cb'),$text);if($this->tagStack){$pair=end($this->tagStack);while($pair!==FALSE){$text.='</'.$pair['tag'].'>';$pair=prev($this->tagStack);}}return$text;}private
function
cb($matches){list(,$mClosing,$mTag,$mAttr,$mEmpty)=$matches;if(isset(Texy::$emptyTags[$mTag])||$mEmpty)return$mClosing?'':'<'.$mTag.$mAttr.$mEmpty.'>';if($mClosing){if(empty($this->tagUsed[$mTag]))return'';$pair=end($this->tagStack);$s='';$i=1;while($pair!==FALSE){$tag=$pair['tag'];$s.='</'.$tag.'>';$this->tagUsed[$tag]--;if($tag===$mTag)break;$pair=prev($this->tagStack);$i++;}if(isset(Texy::$blockTags[$mTag])){array_splice($this->tagStack,-$i);return$s;}unset($this->tagStack[key($this->tagStack)]);$pair=current($this->tagStack);while($pair!==FALSE){$s.='<'.$pair['tag'].$pair['attr'].'>';@$this->tagUsed[$pair['tag']]++;$pair=next($this->tagStack);}return$s;}else{$s='';$pair=end($this->tagStack);while($pair&&isset($this->autoClose[$pair['tag']])&&isset($this->autoClose[$pair['tag']][$mTag])){$tag=$pair['tag'];$s.='</'.$tag.'>';$this->tagUsed[$tag]--;unset($this->tagStack[key($this->tagStack)]);$pair=end($this->tagStack);}$pair=array('attr'=>$mAttr,'tag'=>$mTag,);$this->tagStack[]=$pair;@$this->tagUsed[$pair['tag']]++;$s.='<'.$mTag.$mAttr.'>';return$s;}}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}} 

class
TexyModifier{const
HALIGN_LEFT='left';const
HALIGN_RIGHT='right';const
HALIGN_CENTER='center';const
HALIGN_JUSTIFY='justify';const
VALIGN_TOP='top';const
VALIGN_MIDDLE='middle';const
VALIGN_BOTTOM='bottom';public$id;public$classes=array();public$styles=array();public$attrs=array();public$hAlign;public$vAlign;public$title;static
public$elAttrs=array('abbr'=>1,'accesskey'=>1,'align'=>1,'alt'=>1,'archive'=>1,'axis'=>1,'bgcolor'=>1,'cellpadding'=>1,'cellspacing'=>1,'char'=>1,'charoff'=>1,'charset'=>1,'cite'=>1,'classid'=>1,'codebase'=>1,'codetype'=>1,'colspan'=>1,'compact'=>1,'coords'=>1,'data'=>1,'datetime'=>1,'declare'=>1,'dir'=>1,'face'=>1,'frame'=>1,'headers'=>1,'href'=>1,'hreflang'=>1,'hspace'=>1,'ismap'=>1,'lang'=>1,'longdesc'=>1,'name'=>1,'noshade'=>1,'nowrap'=>1,'onblur'=>1,'onclick'=>1,'ondblclick'=>1,'onkeydown'=>1,'onkeypress'=>1,'onkeyup'=>1,'onmousedown'=>1,'onmousemove'=>1,'onmouseout'=>1,'onmouseover'=>1,'onmouseup'=>1,'rel'=>1,'rev'=>1,'rowspan'=>1,'rules'=>1,'scope'=>1,'shape'=>1,'size'=>1,'span'=>1,'src'=>1,'standby'=>1,'start'=>1,'summary'=>1,'tabindex'=>1,'target'=>1,'title'=>1,'type'=>1,'usemap'=>1,'valign'=>1,'value'=>1,'vspace'=>1,);public
function
setProperties(){foreach(func_get_args()as$arg){if($arg==NULL)continue;$arg0=$arg[0];if($arg0==='('){$this->title=Texy::decode(trim(substr($arg,1,-1)));}elseif($arg0==='{'){$arg=substr($arg,1,-1);foreach(explode(';',$arg)as$value){$pair=explode(':',$value,2);$pair[]='';$prop=strtolower(trim($pair[0]));$value=trim($pair[1]);if($prop==='')continue;if(isset(self::$elAttrs[$prop]))$this->attrs[$prop]=$value;elseif($value!=='')$this->styles[$prop]=$value;}}elseif($arg0==='['){$arg=str_replace('#',' #',substr($arg,1,-1));foreach(explode(' ',$arg)as$value){if($value==='')continue;if($value{0}==='#')$this->id=substr($value,1);else$this->classes[]=$value;}}elseif($arg==='^')$this->vAlign=self::VALIGN_TOP;elseif($arg==='-')$this->vAlign=self::VALIGN_MIDDLE;elseif($arg==='_')$this->vAlign=self::VALIGN_BOTTOM;elseif($arg==='=')$this->hAlign=self::HALIGN_JUSTIFY;elseif($arg==='>')$this->hAlign=self::HALIGN_RIGHT;elseif($arg==='<')$this->hAlign=self::HALIGN_LEFT;elseif($arg==='<>')$this->hAlign=self::HALIGN_CENTER;}}public
function
generate($texy,$tag){$tmp=$texy->allowedTags;if(!$this->attrs){$el=TexyHtml::el($tag);}elseif($tmp===Texy::ALL){$el=TexyHtml::el($tag,$this->attrs);}elseif(is_array($tmp)&&isset($tmp[$tag])){$tmp=$tmp[$tag];if($tmp===Texy::ALL){$el=TexyHtml::el($tag,$this->attrs);}else{$el=TexyHtml::el($tag);if(is_array($tmp)&&count($tmp)){$tmp=array_flip($tmp);foreach($this->attrs
as$key=>$val)if(isset($tmp[$key]))$el->$key=$val;}}}else{$el=TexyHtml::el($tag);}$el->href=$el->src=NULL;$el->title=$this->title;if($this->classes||$this->id!==NULL){$tmp=$texy->_classes;if($tmp===Texy::ALL){foreach($this->classes
as$val)$el->class[]=$val;$el->id=$this->id;}elseif(is_array($tmp)){foreach($this->classes
as$val)if(isset($tmp[$val]))$el->class[]=$val;if(isset($tmp['#'.$this->id]))$el->id=$this->id;}}if($this->styles){$tmp=$texy->_styles;if($tmp===Texy::ALL){foreach($this->styles
as$prop=>$val)$el->style[$prop]=$val;}elseif(is_array($tmp)){foreach($this->styles
as$prop=>$val)if(isset($tmp[$prop]))$el->style[$prop]=$val;}}if($this->hAlign)$el->style['text-align']=$this->hAlign;if($this->vAlign)$el->style['vertical-align']=$this->vAlign;return$el;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}} 

abstract
class
TexyModule{protected$texy;protected$allow=array();public
function
__construct($texy){$this->texy=$texy;$texy->registerModule($this);foreach($this->allow
as$item)$texy->allowed[$item]=TRUE;}public
function
init(){}public
function
preProcess($text){return$text;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}}interface
ITexyLineModule{public
function
linePostProcess($line);} 

class
TexyBlockParser{private$text;private$offset;private$texy;public$children=array();public
function
__construct($texy){$this->texy=$texy;}public
function
receiveNext($pattern,&$matches){$matches=NULL;$ok=preg_match($pattern.'Am',$this->text,$matches,PREG_OFFSET_CAPTURE,$this->offset);if($ok){$this->offset+=strlen($matches[0][0])+1;foreach($matches
as$key=>$value)$matches[$key]=$value[0];}return$ok;}public
function
moveBackward($linesCount=1){while(--$this->offset>0)if($this->text{$this->offset-1}==="\n")if(--$linesCount<1)break;$this->offset=max($this->offset,0);}public
function
parse($text){$tx=$this->texy;$this->text=$text;$this->offset=0;$pb=$tx->getBlockPatterns();if(!$pb)return
array();$keys=array_keys($pb);$arrMatches=$arrPos=array();foreach($keys
as$key)$arrPos[$key]=-1;do{$minKey=NULL;$minPos=strlen($text);if($this->offset>=$minPos)break;foreach($keys
as$index=>$key){if($arrPos[$key]<$this->offset){$delta=($arrPos[$key]===-2)?1:0;if(preg_match($pb[$key]['pattern'],$text,$arrMatches[$key],PREG_OFFSET_CAPTURE,$this->offset+$delta)){$m=&$arrMatches[$key];$arrPos[$key]=$m[0][1];foreach($m
as$keyX=>$valueX)$m[$keyX]=$valueX[0];}else{unset($keys[$index]);continue;}}if($arrPos[$key]===$this->offset){$minKey=$key;break;}if($arrPos[$key]<$minPos){$minPos=$arrPos[$key];$minKey=$key;}}$next=($minKey===NULL)?strlen($text):$arrPos[$minKey];if($next>$this->offset){$str=substr($text,$this->offset,$next-$this->offset);$this->offset=$next;$tx->genericBlock->process($this,$str);continue;}$px=$pb[$minKey];$matches=$arrMatches[$minKey];$this->offset=$arrPos[$minKey]+strlen($matches[0])+1;$ok=call_user_func_array($px['handler'],array($this,$matches,$minKey));if($ok===FALSE||($this->offset<=$arrPos[$minKey])){$this->offset=$arrPos[$minKey];$arrPos[$minKey]=-2;continue;}$arrPos[$minKey]=-1;}while(1);return$this->children;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}}class
TexyLineParser{public$again;private$texy;public
function
__construct($texy){$this->texy=$texy;}public
function
parse($text,$select=NULL){$tx=$this->texy;$pl=$tx->getLinePatterns();if($select){foreach($select
as$name)if(isset($pl[$name]))$plX[$name]=$pl[$name];$pl=$plX;unset($plX);}if(!$pl)return$text;$offset=0;$keys=array_keys($pl);$arrMatches=$arrPos=array();foreach($keys
as$key)$arrPos[$key]=-1;do{$minKey=NULL;$minPos=strlen($text);foreach($keys
as$index=>$key){if($arrPos[$key]<$offset){$delta=($arrPos[$key]===-2)?1:0;if(preg_match($pl[$key]['pattern'],$text,$arrMatches[$key],PREG_OFFSET_CAPTURE,$offset+$delta)){$m=&$arrMatches[$key];if(!strlen($m[0][0]))continue;$arrPos[$key]=$m[0][1];foreach($m
as$keyx=>$value)$m[$keyx]=$value[0];}else{unset($keys[$index]);continue;}}if($arrPos[$key]<$minPos){$minPos=$arrPos[$key];$minKey=$key;}}if($minKey===NULL)break;$px=$pl[$minKey];$offset=$start=$arrPos[$minKey];$this->again=FALSE;$replacement=call_user_func_array($px['handler'],array($this,$arrMatches[$minKey],$minKey));if($replacement
instanceof
TexyHtml){$replacement=$replacement->toText($tx);}elseif($replacement===FALSE){$arrPos[$minKey]=-2;continue;}elseif(!is_string($replacement)){$replacement=(string)$replacement;}$len=strlen($arrMatches[$minKey][0]);$text=substr_replace($text,$replacement,$start,$len);$delta=strlen($replacement)-$len;foreach($keys
as$key){if($arrPos[$key]<$start+$len)$arrPos[$key]=-1;else$arrPos[$key]+=$delta;}if($this->again){$arrPos[$minKey]=-2;}else{$arrPos[$minKey]=-1;$offset+=strlen($replacement);}}while(1);return$text;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}} 

class
TexyBlockModule
extends
TexyModule{protected$allow=array('blocks','blockPre','blockCode','blockHtml','blockText','blockTexysource','blockComment');public
function
init(){$this->texy->registerBlockPattern(array($this,'processBlock'),'#^/--+ *(?:(code|text|html|div|texysource|comment)( .*)?|) *'.TEXY_MODIFIER_H.'?\n(.*\n)?(?:\\\\--+ *\\1?|\z)()$#mUsi','blocks');}public
function
processBlock($parser,$matches,$name){list(,$mType,$mLang,$mMod1,$mMod2,$mMod3,$mMod4,$mContent)=$matches;$tx=$this->texy;$mType=trim(strtolower($mType));$mLang=trim(strtolower($mLang));$mContent=trim($mContent,"\n");if(!$mType)$mType='pre';if($mType==='html'&&empty($tx->allowed['blockHtml']))$mType='text';if($mType==='texysource'&&empty($tx->allowed['blockTexysource']))$mType='pre';if($mType==='code'&&empty($tx->allowed['blockCode']))$mType='pre';if($mType==='pre'&&empty($tx->allowed['blockPre']))$mType='div';$type='block'.ucfirst($mType);if(empty($tx->allowed[$type])){$mType='div';$type='blockDiv';}$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);switch($mType){case'div':$el=new
TexyBlockElement($tx);$el->tags[0]=$mod->generate($tx,'div');if($spaces=strspn($mContent,' '))$mContent=preg_replace("#^ {1,$spaces}#m",'',$mContent);$el->parse($mContent);$parser->children[]=$el;break;case'texysource':if($spaces=strspn($mContent,' '))$mContent=preg_replace("#^ {1,$spaces}#m",'',$mContent);$el=new
TexyBlockElement($tx);$el->parse($mContent);$html=$el->toHtml();$html=$tx->unMarks($html);$html=$tx->wellForm->process($html);$html=$tx->formatter->process($html);$html=Texy::encode($html);$el=new
TexyTextualElement($tx);$el->tags[0]=$mod->generate($tx,'pre');$el->tags[1]=TexyHtml::el('code')->class('html');$el->content=$html;$el->protect=TRUE;$parser->children[]=$el;break;case'comment':break;case'html':$el=new
TexyTextualElement($tx);$lineParser=new
TexyLineParser($this->texy);$mContent=$lineParser->parse($mContent,array('html'));$mContent=Texy::decode($mContent);$el->content=Texy::encode($mContent);$el->protect=TRUE;$parser->children[]=$el;break;case'text':$el=new
TexyTextualElement($tx);$el->content=nl2br(Texy::encode($mContent));$el->protect=TRUE;$parser->children[]=$el;break;case'pre':case'code':$el=new
TexyTextualElement($tx);$el->tags[0]=$mod->generate($tx,'pre');$el->tags[0]->class[]=$mLang;if($mType==='code'){$el->tags[1]=TexyHtml::el('code');}if($spaces=strspn($mContent,' '))$mContent=preg_replace("#^ {1,$spaces}#m",'',$mContent);$el->content=Texy::encode($mContent);$el->protect=TRUE;if(is_callable(array($tx->handler,$type)))$tx->handler->$type($tx,$mLang,$mod,$mContent,$el);$parser->children[]=$el;}}} 

class
TexyHeadingModule
extends
TexyModule{const
DYNAMIC=1,FIXED=2;protected$allow=array('headingSurrounded','headingUnderlined');public$top=1;public$title;public$balancing=TexyHeadingModule::DYNAMIC;public$levels=array('#'=>0,'*'=>1,'='=>2,'-'=>3,);private$_rangeUnderline;private$_deltaUnderline;private$_rangeSurround;private$_deltaSurround;public
function
init(){$this->texy->registerBlockPattern(array($this,'processBlockUnderline'),'#^(\S.*)'.TEXY_MODIFIER_H.'?\n'.'(\#|\*|\=|\-){3,}$#mU','headingUnderlined');$this->texy->registerBlockPattern(array($this,'processBlockSurround'),'#^((\#|\=){2,})(?!\\2)(.+)\\2*'.TEXY_MODIFIER_H.'?()$#mU','headingSurrounded');}public
function
preProcess($text){$this->_rangeUnderline=array(10,0);$this->_rangeSurround=array(10,0);$this->title=NULL;$foo=NULL;$this->_deltaUnderline=&$foo;$bar=NULL;$this->_deltaSurround=&$bar;return$text;}public
function
processBlockUnderline($parser,$matches){list(,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mLine)=$matches;$el=new
TexyHeadingElement($this->texy);$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate($this->texy,'hx');$el->level=$this->levels[$mLine];if($this->balancing===self::DYNAMIC)$el->deltaLevel=&$this->_deltaUnderline;$el->parse(trim($mContent));$parser->children[]=$el;if($this->title===NULL)$this->title=Texy::wash($el->content);$this->_rangeUnderline[0]=min($this->_rangeUnderline[0],$el->level);$this->_rangeUnderline[1]=max($this->_rangeUnderline[1],$el->level);$this->_deltaUnderline=-$this->_rangeUnderline[0];$this->_deltaSurround=-$this->_rangeSurround[0]+($this->_rangeUnderline[1]?($this->_rangeUnderline[1]-$this->_rangeUnderline[0]+1):0);}public
function
processBlockSurround($parser,$matches){list(,$mLine,,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$el=new
TexyHeadingElement($this->texy);$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate($this->texy,'hx');$el->level=7-min(7,max(2,strlen($mLine)));if($this->balancing===self::DYNAMIC)$el->deltaLevel=&$this->_deltaSurround;$el->parse(trim($mContent));$parser->children[]=$el;if($this->title===NULL)$this->title=Texy::wash($el->content);$this->_rangeSurround[0]=min($this->_rangeSurround[0],$el->level);$this->_rangeSurround[1]=max($this->_rangeSurround[1],$el->level);$this->_deltaSurround=-$this->_rangeSurround[0]+($this->_rangeUnderline[1]?($this->_rangeUnderline[1]-$this->_rangeUnderline[0]+1):0);}}class
TexyHeadingElement
extends
TexyTextualElement{public$level=0;public$deltaLevel=0;public
function
toHtml(){$level=min(6,max(1,$this->level+$this->deltaLevel+$this->texy->headingModule->top));$this->tags[0]->setElement('h'.$level);return
parent::toHtml();}} 

class
TexyHorizLineModule
extends
TexyModule{protected$allow=array('horizLine');public
function
init(){$this->texy->registerBlockPattern(array($this,'processBlock'),'#^(\- |\-|\* |\*){3,}\ *'.TEXY_MODIFIER_H.'?()$#mU','horizLine');}public
function
processBlock($parser,$matches){list(,,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$el=new
TexyBlockElement($this->texy);$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate($this->texy,'hr');$parser->children[]=$el;}} 

class
TexyHtmlModule
extends
TexyModule{protected$allow=array('html','htmlTag','htmlComment');public
function
init(){$this->texy->registerLinePattern(array($this,'process'),'#<(/?)([a-z][a-z0-9_:-]*)((?:\s+[a-z0-9:-]+|=\s*"[^"'.TEXY_MARK.']*"|=\s*\'[^\''.TEXY_MARK.']*\'|=[^\s>'.TEXY_MARK.']+)*)\s*(/?)>|<!--([^'.TEXY_MARK.']*?)-->#is','html');}public
function
process($parser,$matches){$matches[]=NULL;list($match,$mClosing,$mTag,$mAttr,$mEmpty,$mComment)=$matches;$tx=$this->texy;if($mTag==''){if(empty($tx->allowed['htmlComment']))return
substr($matches[5],0,1)==='['?$match:'';return$tx->mark($match,Texy::CONTENT_NONE);}if(empty($tx->allowed['htmlTag']))return
FALSE;$tag=strtolower($mTag);if(!isset(Texy::$blockTags[$tag])&&!isset(Texy::$inlineTags[$tag]))$tag=$mTag;$aTags=$tx->allowedTags;if(!$aTags)return
FALSE;if(is_array($aTags)){if(!isset($aTags[$tag]))return
FALSE;$aAttrs=$aTags[$tag];}else{$aAttrs=NULL;}$isEmpty=$mEmpty==='/';if(!$isEmpty&&substr($mAttr,-1)==='/'){$mAttr=substr($mAttr,0,-1);$isEmpty=TRUE;}$isOpening=$mClosing!=='/';if($isEmpty&&!$isOpening)return
FALSE;$el=TexyHtml::el($tag);if($aTags===Texy::ALL&&$isEmpty)$el->_empty=TRUE;if(!$isOpening)return$tx->mark($el->endTag(),$el->getContentType());if(is_array($aAttrs))$aAttrs=array_flip($aAttrs);else$aAttrs=NULL;preg_match_all('#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is',$mAttr,$matches2,PREG_SET_ORDER);foreach($matches2
as$m){$key=strtolower($m[1]);if($aAttrs!==NULL&&!isset($aAttrs[$key]))continue;$val=$m[2];if($val==NULL)$el->$key=TRUE;elseif($val{0}==='\''||$val{0}==='"')$el->$key=Texy::decode(substr($val,1,-1));else$el->$key=Texy::decode($val);}$modifier=new
TexyModifier;if(isset($el->class)){$tmp=$tx->_classes;if(is_array($tmp)){$el->class=explode(' ',$el->class);foreach($el->class
as$key=>$val)if(!isset($tmp[$val]))unset($el->class[$key]);if(!isset($tmp['#'.$el->id]))$el->id=NULL;}elseif($tmp!==Texy::ALL){$el->class=$el->id=NULL;}}if(isset($el->style)){$tmp=$tx->_styles;if(is_array($tmp)){$styles=explode(';',$el->style);$el->style=NULL;foreach($styles
as$value){$pair=explode(':',$value,2);$pair[]='';$prop=strtolower(trim($pair[0]));$value=trim($pair[1]);if($value!==''&&isset($tmp[$prop]))$el->style[$prop]=$value;}}elseif($tmp!==Texy::ALL){$el->style=NULL;}}if($tag==='img'){if(!isset($el->src))return
FALSE;$tx->summary['images'][]=$el->src;}elseif($tag==='a'){if(!isset($el->href)&&!isset($el->name)&&!isset($el->id))return
FALSE;if(isset($el->href)){$tx->summary['links'][]=$el->href;}}return$tx->mark($el->startTag(),$el->getContentType());}} 

class
TexyFigureModule
extends
TexyModule{protected$allow=array('figure');public$class='figure';public$leftClass='figure-left';public$rightClass='figure-right';public
function
init(){$this->texy->registerBlockPattern(array($this,'processBlock'),'#^'.TEXY_IMAGE.TEXY_LINK_N.'?? +\*\*\* +(.*)'.TEXY_MODIFIER_H.'?()$#mU','figure');}public
function
processBlock($parser,$matches){list(,$mURLs,$mImgMod1,$mImgMod2,$mImgMod3,$mImgMod4,$mLink,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$tx=$this->texy;$el=new
TexyBlockElement($tx);$req=$tx->imageModule->parse($mURLs,$mImgMod1,$mImgMod2,$mImgMod3,$mImgMod4);$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$hAlign=$req['modifier']->hAlign;$mod->hAlign=$req['modifier']->hAlign=NULL;$elImg=$tx->imageModule->factory($req,$mLink);if($mLink){if($mLink===':'){$reqL=array('URL'=>empty($req['linkedURL'])?$req['imageURL']:$req['linkedURL'],'image'=>TRUE,'modifier'=>new
TexyModifier,);}else{$reqL=$tx->linkModule->parse($mLink,NULL,NULL,NULL,NULL);}$elLink=$tx->linkModule->factory($reqL);$tx->summary['links'][]=$elLink->href;$elLink->addChild($elImg);$elImg=$elLink;}$el->tags[0]=$mod->generate($tx,'div');$el->children[0]=new
TexyTextualElement($tx);$el->children[0]->content=$elImg->toText($tx);$el->children[1]=new
TexyBlockElement($tx);$el->children[1]->parse(ltrim($mContent));if($hAlign===TexyModifier::HALIGN_LEFT){$el->tags[0]->class[]=$this->leftClass;}elseif($hAlign===TexyModifier::HALIGN_RIGHT){$el->tags[0]->class[]=$this->rightClass;}elseif($this->class)$el->tags[0]->class[]=$this->class;if(is_callable(array($tx->handler,'figure')))$tx->handler->figure($tx,$req,$el);$parser->children[]=$el;}} 

class
TexyImageModule
extends
TexyModule{protected$allow=array('image');public$root='images/';public$linkedRoot='images/';public$fileRoot;public$leftClass;public$rightClass;public$defaultAlt='';private$references=array();public$rootPrefix='';public
function
__construct($texy){parent::__construct($texy);$this->rootPrefix=&$this->fileRoot;if(isset($_SERVER['SCRIPT_NAME'])){$this->fileRoot=dirname($_SERVER['SCRIPT_NAME']);}}public
function
init(){$this->texy->registerLinePattern(array($this,'processLine'),'#'.TEXY_IMAGE.TEXY_LINK_N.'??()#U','image');}public
function
addReference($name,$URLs,$modifier=NULL){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}$ref=$this->parseContent($URLs);$ref['modifier']=$modifier;$ref['name']=$name;$this->references[$name]=$ref;}public
function
getReference($name){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}if(isset($this->references[$name])){$ref=$this->references[$name];$ref['modifier']=empty($ref['modifier'])?new
TexyModifier:clone$ref['modifier'];return$ref;}return
FALSE;}private
function
parseContent($content){$content=explode('|',$content);$req=array();if(preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U',$content[0],$matches)){$req['imageURL']=trim($matches[1]);$req['width']=(int)$matches[2];$req['height']=(int)$matches[3];}else{$req['imageURL']=trim($content[0]);$req['width']=$req['height']=NULL;}$req['overURL']=NULL;if(isset($content[1])){$tmp=trim($content[1]);if($tmp!=='')$req['overURL']=$tmp;}$req['linkedURL']=NULL;if(isset($content[2])){$tmp=trim($content[2]);if($tmp!=='')$req['linkedURL']=$tmp;}return$req;}public
function
parse($mURLs,$mMod1,$mMod2,$mMod3,$mMod4){$req=$this->getReference(trim($mURLs));if(!$req){$req=$this->parseContent($mURLs);$req['modifier']=new
TexyModifier;}$req['modifier']->setProperties($mMod1,$mMod2,$mMod3,$mMod4);return$req;}public
function
preProcess($text){return
preg_replace_callback('#^\[\*([^\n]+)\*\]:\ +(.+)\ *'.TEXY_MODIFIER.'?()$#mU',array($this,'processReferenceDefinition'),$text);}public
function
processReferenceDefinition($matches){list(,$mRef,$mURLs,$mMod1,$mMod2,$mMod3)=$matches;$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3);$this->addReference($mRef,$mURLs,$mod);return'';}public
function
processLine($parser,$matches){list(,$mURLs,$mMod1,$mMod2,$mMod3,$mMod4,$mLink)=$matches;$tx=$this->texy;$req=$this->parse($mURLs,$mMod1,$mMod2,$mMod3,$mMod4);$el=$this->factory($req);if(is_callable(array($tx->handler,'image')))$tx->handler->image($tx,$req,$el);$tx->summary['images'][]=$el->src;if($mLink){if($mLink===':'){$reqL=array('URL'=>empty($req['linkedURL'])?$req['imageURL']:$req['linkedURL'],'image'=>TRUE,'modifier'=>new
TexyModifier,);}else{$reqL=$tx->linkModule->parse($mLink,NULL,NULL,NULL,NULL);}$elLink=$tx->linkModule->factory($reqL);$tx->summary['links'][]=$elLink->href;$elLink->addChild($el);return$elLink;}return$el;}public
function
factory($req){extract($req);$tx=$this->texy;$src=Texy::completeURL($imageURL,$this->root);$file=Texy::completePath($imageURL,$this->fileRoot);if(substr($src,-4)==='.swf'){}$alt=$modifier->title!==NULL?$modifier->title:$this->defaultAlt;$modifier->title=NULL;$hAlign=$modifier->hAlign;$modifier->hAlign=NULL;$el=$modifier->generate($tx,'img');if($hAlign===TexyModifier::HALIGN_LEFT){if($this->leftClass!='')$el->class[]=$this->leftClass;else$el->style['float']='left';}elseif($hAlign===TexyModifier::HALIGN_RIGHT){if($this->rightClass!='')$el->class[]=$this->rightClass;else$el->style['float']='right';}if($width){$el->width=$width;$el->height=$height;}elseif(is_file($file)){$size=getImageSize($file);if(is_array($size)){$el->width=$size[0];$el->height=$size[1];}}$el->src=$src;$el->alt=(string)$alt;if($overURL!==NULL){$overSrc=Texy::completeURL($overURL,$this->root);$el->onmouseover='this.src=\''.addSlashes($overSrc).'\'';$el->onmouseout='this.src=\''.addSlashes($src).'\'';static$counter;$counter++;$attrs['onload']="preload_$counter=new Image();preload_$counter.src='".addSlashes($overSrc)."';this.onload=''";$tx->summary['preload'][]=$overSrc;}return$el;}} 

class
TexyLinkModule
extends
TexyModule{protected$allow=array('linkReference','linkEmail','linkURL','linkQuick','linkDefinition');public$root='';public$emailOnClick;public$imageOnClick='return !popupImage(this.href)';public$popupOnClick='return !popup(this.href)';public$forceNoFollow=FALSE;protected$references=array();static
private$deadlock;public
function
init(){self::$deadlock=array();$tx=$this->texy;$tx->registerLinePattern(array($this,'processLineQuick'),'#(['.TEXY_CHAR.'0-9@\#$%&.,_-]+)(?=:\[)'.TEXY_LINK.'()#Uu','linkQuick');$tx->registerLinePattern(array($this,'processLineReference'),'#('.TEXY_LINK_REF.')#U','linkReference');$tx->registerLinePattern(array($this,'processLineURL'),'#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#iu','linkURL');$tx->registerLinePattern(array($this,'processLineURL'),'#(?<=\s|^|\(|\[|\<|:)'.TEXY_EMAIL.'#i','linkEmail');}public
function
addReference($name,$URL,$label=NULL,$modifier=NULL){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}$this->references[$name]=array('URL'=>$URL,'label'=>$label,'modifier'=>$modifier,'name'=>$name,);}public
function
getReference($name){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}if(isset($this->references[$name])){$ref=$this->references[$name];}else{$pos=strpos($name,'?');if($pos===FALSE)$pos=strpos($name,'#');if($pos!==FALSE){$name2=substr($name,0,$pos);if(isset($this->references[$name2])){$ref=$this->references[$name2];$ref['URL'].=substr($name,$pos);}}}if(empty($ref))return
FALSE;$ref['modifier']=empty($ref['modifier'])?new
TexyModifier:clone$ref['modifier'];return$ref;}public
function
preProcess($text){if($this->texy->allowed['linkDefinition'])return
preg_replace_callback('#^\[([^\[\]\#\?\*\n]+)\]: +(\S+)(\ .+)?'.TEXY_MODIFIER.'?()$#mU',array($this,'processReferenceDefinition'),$text);return$text;}public
function
processReferenceDefinition($matches){list(,$mRef,$mLink,$mLabel,$mMod1,$mMod2,$mMod3)=$matches;$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3);$this->addReference($mRef,$mLink,trim($mLabel),$mod);return'';}public
function
processLineQuick($parser,$matches){list(,$mContent,$mLink)=$matches;$tx=$this->texy;$req=$this->parse($mLink,NULL,NULL,NULL,$mContent);$el=$this->factory($req);$el->addChild($mContent);if(is_callable(array($tx->handler,'link')))$tx->handler->link($tx,$req,$el);$tx->summary['links'][]=$el->href;return$el;}public
function
processLineReference($parser,$matches){list($match,$mRef)=$matches;$tx=$this->texy;$name=substr($mRef,1,-1);$ref=$this->getReference($name);if(!$ref){if(is_callable(array($tx->handler,'reference')))return$tx->handler->reference($tx,$name);return
FALSE;}if($ref['label']){if(isset(self::$deadlock[$mRef['name']])){$content=$ref['label'];}else{$label=new
TexyTextualElement($tx);self::$deadlock[$mRef['name']]=TRUE;$label->parse($ref['label']);$content=$label->content;unset(self::$deadlock[$mRef['name']]);}}else{$content=$this->textualURL($ref['URL']);}$el=$this->factory($ref);$el->addChild($content);if(is_callable(array($tx->handler,'reference2')))$tx->handler->reference2($tx,$ref,$el);$tx->summary['links'][]=$el->href;return$el;}public
function
processLineURL($parser,$matches,$name){list($mURL)=$matches;$tx=$this->texy;$req=array('URL'=>$mURL,);$el=$this->factory($req);$el->addChild($this->textualURL($mURL));if(is_callable(array($tx->handler,$name)))$tx->handler->$name($tx,$mURL,$el);$tx->summary['links'][]=$el->href;return$el;}public
function
parse($dest,$mMod1,$mMod2,$mMod3,$label){$tx=$this->texy;$image=FALSE;if(strlen($dest)>1&&$dest{0}==='['&&$dest{1}!=='*'){$dest=substr($dest,1,-1);$req=$this->getReference($dest);}elseif(strlen($dest)>1&&$dest{0}==='['&&$dest{1}==='*'){$image=TRUE;$dest=trim(substr($dest,2,-2));$reqI=$tx->imageModule->getReference($dest);if($reqI){$req['URL']=empty($reqI['linkedURL'])?$reqI['imageURL']:$reqI['linkedURL'];$req['modifier']=$reqI['modifier'];}}if(empty($req)){$req=array('URL'=>trim($dest),'label'=>$label,'modifier'=>new
TexyModifier,);}$req['URL']=str_replace('%s',urlencode(Texy::wash($label)),$req['URL']);$req['modifier']->setProperties($mMod1,$mMod2,$mMod3);$req['image']=$image;return$req;}public
function
factory($req){extract($req);$tx=$this->texy;if(empty($modifier)){$el=TexyHtml::el('a');$nofollow=$popup=FALSE;}else{$classes=array_flip($modifier->classes);$nofollow=isset($classes['nofollow']);$popup=isset($classes['popup']);unset($classes['nofollow'],$classes['popup']);$modifier->classes=array_flip($classes);$el=$modifier->generate($tx,'a');}if(empty($image)){if(preg_match('#^'.TEXY_EMAIL.'$#i',$URL)){$el->href='mailto:'.$URL;$el->onclick=$this->emailOnClick;}else{$el->href=Texy::completeURL($URL,$this->root,$isAbsolute);if($nofollow||($this->forceNoFollow&&$isAbsolute))$el->rel[]='nofollow';}}else{$el->href=Texy::completeURL($URL,$tx->imageModule->linkedRoot);$el->onclick=$this->imageOnClick;}if($popup)$el->onclick=$this->popupOnClick;return$el;}public
function
textualURL($URL){if(preg_match('#^'.TEXY_EMAIL.'$#i',$URL)){return$this->texy->obfuscateEmail?strtr($URL,array('@'=>"&#160;(at)&#160;")):$URL;}if(preg_match('#^(https?://|ftp://|www\.|ftp\.|/)#i',$URL)){$lower=strtolower($URL);if(substr($lower,0,4)==='www.')$URL='none://'.$URL;elseif(substr($lower,0,4)==='ftp.')$URL='none://'.$URL;$parts=@parse_url($URL);if($parts===FALSE)return$URL;$res='';if(isset($parts['scheme'])&&$parts['scheme']!=='none')$res.=$parts['scheme'].'://';if(isset($parts['host']))$res.=$parts['host'];if(isset($parts['path']))$res.=(strlen($parts['path'])>16?('/...'.preg_replace('#^.*(.{0,12})$#U','$1',$parts['path'])):$parts['path']);if(isset($parts['query'])){$res.=strlen($parts['query'])>4?'?...':('?'.$parts['query']);}elseif(isset($parts['fragment'])){$res.=strlen($parts['fragment'])>4?'#...':('#'.$parts['fragment']);}return$res;}return$URL;}} 

class
TexyListModule
extends
TexyModule{protected$allow=array('list');public$bullets=array('*'=>TRUE,'-'=>TRUE,'+'=>TRUE,'1.'=>TRUE,'1)'=>TRUE,'I.'=>TRUE,'I)'=>TRUE,'a)'=>TRUE,'A)'=>TRUE,);private$translate=array('*'=>array('\*','','ul'),'-'=>array('[\x{2013}-]','','ul'),'+'=>array('\+','','ul'),'1.'=>array('\d+\.\ ','','ol'),'1)'=>array('\d+\)','','ol'),'I.'=>array('[IVX]+\.\ ','upper-roman','ol'),'I)'=>array('[IVX]+\)','upper-roman','ol'),'a)'=>array('[a-z]\)','lower-alpha','ol'),'A)'=>array('[A-Z]\)','upper-alpha','ol'),);public
function
init(){$bullets=array();foreach($this->bullets
as$bullet=>$allowed)if($allowed)$bullets[]=$this->translate[$bullet][0];$this->texy->registerBlockPattern(array($this,'processBlock'),'#^(?:'.TEXY_MODIFIER_H.'\n)?'.'('.implode('|',$bullets).')(\n?)\ +\S.*$#mUu','list');}public
function
processBlock($parser,$matches){list(,$mMod1,$mMod2,$mMod3,$mMod4,$mBullet,$mNewLine)=$matches;$tx=$this->texy;$el=new
TexyBlockElement($tx);$bullet='';foreach($this->translate
as$type)if(preg_match('#'.$type[0].'#Au',$mBullet)){$bullet=$type[0];$tag=$type[2];$style=$type[1];break;}$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate($tx,$tag);$el->tags[0]->style['list-style-type']=$style;$parser->moveBackward($mNewLine?2:1);$count=0;while($elItem=$this->processItem($parser,$bullet,FALSE,'li')){$el->children[]=$elItem;$count++;}if(!$count)return
FALSE;$parser->children[]=$el;}public
function
processItem($parser,$bullet,$indented,$tag){$tx=$this->texy;$spacesBase=$indented?('\ {1,}'):'';$patternItem="#^\n?($spacesBase)$bullet(\n?)(\\ +)(\\S.*)?".TEXY_MODIFIER_H."?()$#mAUu";if(!$parser->receiveNext($patternItem,$matches)){return
FALSE;}list(,$mIndent,$mNewLine,$mSpace,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$elItem=new
TexyBlockElement($tx);$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$elItem->tags[0]=$mod->generate($tx,$tag);$spaces=$mNewLine?strlen($mSpace):'';$content=' '.$mContent;while($parser->receiveNext('#^(\n*)'.$mIndent.'(\ {1,'.$spaces.'})(.*)()$#Am',$matches)){list(,$mBlank,$mSpaces,$mContent)=$matches;if($spaces==='')$spaces=strlen($mSpaces);$content.="\n".$mBlank.$mContent;}$tmp=$tx->_mergeMode;$tx->_mergeMode=FALSE;$elItem->parse($content);$tx->_mergeMode=$tmp;if($elItem->children&&$elItem->children[0]instanceof
TexyParagraphElement)$elItem->children[0]->tags[0]->setElement(NULL);return$elItem;}} 

class
TexyDefinitionListModule
extends
TexyListModule{protected$allow=array('listDefinition');public$bullets=array('*'=>TRUE,'-'=>TRUE,'+'=>TRUE,);private$translate=array('*'=>array('\*'),'-'=>array('[\x{2013}-]'),'+'=>array('\+'),);public
function
init(){$bullets=array();foreach($this->bullets
as$bullet=>$allowed)if($allowed)$bullets[]=$this->translate[$bullet][0];$this->texy->registerBlockPattern(array($this,'processBlock'),'#^(?:'.TEXY_MODIFIER_H.'\n)?'.'(\S.*)\:\ *'.TEXY_MODIFIER_H.'?\n'.'(\ +)('.implode('|',$bullets).')\ +\S.*$#mUu','listDefinition');}public
function
processBlock($parser,$matches){list(,$mMod1,$mMod2,$mMod3,$mMod4,$mContentTerm,$mModTerm1,$mModTerm2,$mModTerm3,$mModTerm4,$mSpaces,$mBullet)=$matches;$tx=$this->texy;$el=new
TexyBlockElement($tx);$bullet='';foreach($this->translate
as$type)if(preg_match('#'.$type[0].'#Au',$mBullet)){$bullet=$type[0];break;}$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate($tx,'dl');$parser->moveBackward(2);$patternTerm='#^\n?(\S.*)\:\ *'.TEXY_MODIFIER_H.'?()$#mUA';$bullet=preg_quote($mBullet);while(TRUE){if($elItem=$this->processItem($parser,preg_quote($mBullet),TRUE,'dd')){$el->children[]=$elItem;continue;}if($parser->receiveNext($patternTerm,$matches)){list(,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$elItem=new
TexyTextualElement($tx);$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$elItem->tags[0]=$mod->generate($tx,'dt');$elItem->parse($mContent);$el->children[]=$elItem;continue;}break;}$parser->children[]=$el;}} 

class
TexyLongWordsModule
extends
TexyModule
implements
ITexyLineModule{protected$allow=array('longWords');public$wordLimit=20;const
DONT=0,HERE=1,AFTER=2;private$consonants=array('b','c','d','f','g','h','j','k','l','m','n','p','q','r','s','t','v','w','x','z','B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Z',"\xc4\x8d","\xc4\x8f","\xc5\x88","\xc5\x99","\xc5\xa1","\xc5\xa5","\xc5\xbe","\xc4\x8c","\xc4\x8e","\xc5\x87","\xc5\x98","\xc5\xa0","\xc5\xa4","\xc5\xbd");private$vowels=array('a','e','i','o','u','y','A','E','I','O','U','Y',"\xc3\xa1","\xc3\xa9","\xc4\x9b","\xc3\xad","\xc3\xb3","\xc3\xba","\xc5\xaf","\xc3\xbd","\xc3\x81","\xc3\x89","\xc4\x9a","\xc3\x8d","\xc3\x93","\xc3\x9a","\xc5\xae","\xc3\x9d");private$before_r=array('b','B','c','C','d','D','f','F','g','G','k','K','p','P','r','R','t','T','v','V',"\xc4\x8d","\xc4\x8c","\xc4\x8f","\xc4\x8e","\xc5\x99","\xc5\x98","\xc5\xa5","\xc5\xa4");private$before_l=array('b','B','c','C','d','D','f','F','g','G','k','K','l','L','p','P','t','T','v','V',"\xc4\x8d","\xc4\x8c","\xc4\x8f","\xc4\x8e","\xc5\xa5","\xc5\xa4");private$before_h=array('c','C','s','S');private$doubleVowels=array('a','A','o','O');public
function
__construct($texy){parent::__construct($texy);$this->consonants=array_flip($this->consonants);$this->vowels=array_flip($this->vowels);$this->before_r=array_flip($this->before_r);$this->before_l=array_flip($this->before_l);$this->before_h=array_flip($this->before_h);$this->doubleVowels=array_flip($this->doubleVowels);}public
function
linePostProcess($text){if(empty($this->texy->allowed['longWords']))return$text;return
preg_replace_callback('#[^\ \n\t\-\x{2013}\x{ad}\x15\x16\x17'.TEXY_MARK_SPACES.']{'.$this->wordLimit.',}#u',array($this,'_replace'),$text);}private
function
_replace($matches){list($mWord)=$matches;$chars=array();preg_match_all('#['.TEXY_MARK.']+|.#u',$mWord,$chars);$chars=$chars[0];if(count($chars)<$this->wordLimit)return$mWord;$consonants=$this->consonants;$vowels=$this->vowels;$before_r=$this->before_r;$before_l=$this->before_l;$before_h=$this->before_h;$doubleVowels=$this->doubleVowels;$s=array();$trans=array();$s[]='';$trans[]=-1;foreach($chars
as$key=>$char){if(ord($char{0})<32)continue;$s[]=$char;$trans[]=$key;}$s[]='';$len=count($s)-2;$positions=array();$a=1;$last=1;while($a<$len){$hyphen=self::DONT;do{if($s[$a]==='.'){$hyphen=self::HERE;break;}if(isset($consonants[$s[$a]])){if(isset($vowels[$s[$a+1]])){if(isset($vowels[$s[$a-1]]))$hyphen=self::HERE;break;}if(($s[$a]==='s')&&($s[$a-1]==='n')&&isset($consonants[$s[$a+1]])){$hyphen=self::AFTER;break;}if(isset($consonants[$s[$a+1]])&&isset($vowels[$s[$a-1]])){if($s[$a+1]==='r'){$hyphen=isset($before_r[$s[$a]])?self::HERE:self::AFTER;break;}if($s[$a+1]==='l'){$hyphen=isset($before_l[$s[$a]])?self::HERE:self::AFTER;break;}if($s[$a+1]==='h'){$hyphen=isset($before_h[$s[$a]])?self::DONT:self::AFTER;break;}$hyphen=self::AFTER;break;}break;}if(($s[$a]==='u')&&isset($doubleVowels[$s[$a-1]])){$hyphen=self::AFTER;break;}if(isset($vowels[$s[$a]])&&isset($vowels[$s[$a-1]])){$hyphen=self::HERE;break;}}while(0);if($hyphen===self::DONT&&($a-$last>$this->wordLimit*0.6))$positions[]=$last=$a-1;if($hyphen===self::HERE)$positions[]=$last=$a-1;if($hyphen===self::AFTER){$positions[]=$last=$a;$a++;}$a++;}$a=end($positions);if(($a===$len-1)&&isset($consonants[$s[$len]]))array_pop($positions);$syllables=array();$last=0;foreach($positions
as$pos){if($pos-$last>$this->wordLimit*0.6){$syllables[]=implode('',array_splice($chars,0,$trans[$pos]-$trans[$last]));$last=$pos;}}$syllables[]=implode('',$chars);return
implode("\xC2\xAD",$syllables);}} 

class
TexyPhraseModule
extends
TexyModule{protected$allow=array('phraseStrongEm','phraseStrong','phraseEm','phraseIns','phraseDel','phraseSup','phraseSub','phraseSpan','phraseSpanAlt','phraseCite','phraseAcronym','phraseAcronymAlt','phraseCode','phraseNoTexy','phraseQuote','phraseCodeSwitch',);public$codeTag='code';public
function
init(){$tx=$this->texy;$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\*)\*\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*\*(?!\*)()'.TEXY_LINK.'??()#U','phraseStrongEm');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\*)\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*(?!\*)'.TEXY_LINK.'??()#U','phraseStrong');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\*)\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*(?!\*)'.TEXY_LINK.'??()#U','phraseEm');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\+)\+\+(?!\ |\+)(.+)'.TEXY_MODIFIER.'?(?<!\ |\+)\+\+(?!\+)()#U','phraseIns');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\-)\-\-(?!\ |\-)(.+)'.TEXY_MODIFIER.'?(?<!\ |\-)\-\-(?!\-)()#U','phraseDel');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\^)\^\^(?!\ |\^)(.+)'.TEXY_MODIFIER.'?(?<!\ |\^)\^\^(?!\^)()#U','phraseSup');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\_)\_\_(?!\ |\_)(.+)'.TEXY_MODIFIER.'?(?<!\ |\_)\_\_(?!\_)()#U','phraseSub');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")'.TEXY_LINK.'??()#U','phraseSpan');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\~)\~(?!\ )([^\~]+)'.TEXY_MODIFIER.'?(?<!\ )\~(?!\~)'.TEXY_LINK.'??()#U','phraseSpanAlt');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\~)\~\~(?!\ |\~)(.+)'.TEXY_MODIFIER.'?(?<!\ |\~)\~\~(?!\~)'.TEXY_LINK.'??()#U','phraseCite');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\>)\>\>(?!\ |\>)(.+)'.TEXY_MODIFIER.'?(?<!\ |\<)\<\<(?!\<)'.TEXY_LINK.'??()#U','phraseQuote');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")\(\((.+)\)\)()#U','phraseAcronym');$tx->registerLinePattern(array($this,'processPhrase'),'#(?<!['.TEXY_CHAR.'])(['.TEXY_CHAR.']{2,})()()()\(\((.+)\)\)#Uu','phraseAcronymAlt');$tx->registerLinePattern(array($this,'processProtect'),'#\`\`(\S[^'.TEXY_MARK.']*)(?<!\ )\`\`()#U','phraseNoTexy');$tx->registerLinePattern(array($this,'processCode'),'#\`(\S[^'.TEXY_MARK.']*)'.TEXY_MODIFIER.'?(?<!\ )\`()#U','phraseCode');$tx->registerBlockPattern(array($this,'processBlock'),'#^`=(none|code|kbd|samp|var|span)$#mUi','phraseCodeSwitch');}public
function
processPhrase($parser,$matches,$name){list($match,$mContent,$mMod1,$mMod2,$mMod3,$mLink)=$matches;if($mContent==NULL){preg_match('#^(.)+(.+)'.TEXY_MODIFIER.'?\\1+()$#U',$match,$matches);list($match,,$mContent,$mMod1,$mMod2,$mMod3,$mLink)=$matches;}$tx=$this->texy;static$_tags=array('phraseStrongEm'=>'strong','phraseStrong'=>'strong','phraseEm'=>'em','phraseIns'=>'ins','phraseDel'=>'del','phraseSup'=>'sup','phraseSub'=>'sub','phraseSpan'=>'span','phraseSpanAlt'=>'span','phraseCite'=>'cite','phraseAcronym'=>'acronym','phraseAcronymAlt'=>'acronym','phraseQuote'=>'q','phraseCode'=>'code',);$tag=$_tags[$name];if(($tag==='span')&&$mLink)$tag=NULL;elseif(($tag==='span')&&!$mMod1&&!$mMod2&&!$mMod3)return
FALSE;$content=$mContent;if($name==='phraseStrongEm'){$content=TexyHtml::el('em')->addChild($content)->toText($tx);}$modifier=new
TexyModifier;$modifier->setProperties($mMod1,$mMod2,$mMod3);if($tag==='acronym'||$tag==='abbr'){$modifier->title=trim(Texy::decode($mLink));$mLink=NULL;}$el=$modifier->generate($tx,$tag);if($mLink&&$tag==='q'){$el->cite=$tx->quoteModule->citeLink($mLink);$mLink=NULL;}$el->addChild($content);if(is_callable(array($tx->handler,'phrase')))$tx->handler->phrase($tx,$name,$modifier,$mLink,$el);$content=$el->toText($tx);if($mLink){$req=$tx->linkModule->parse($mLink,$mMod1,$mMod2,$mMod3,$mContent);$el=$tx->linkModule->factory($req)->addChild($content);if(is_callable(array($tx->handler,'link')))$tx->handler->link($tx,$req,$el);$content=$el->toText($tx);}$parser->again=TRUE;return$content;}public
function
processBlock($parser,$matches){list(,$mTag)=$matches;$mTag=strtolower($mTag);$this->codeTag=$mTag==='none'?'':$mTag;}public
function
processCode($parser,$matches){list(,$mContent,$mMod1,$mMod2,$mMod3)=$matches;$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3);$el=$mod->generate($this->texy,$this->codeTag);return$this->texy->mark($el->startTag().htmlSpecialChars($mContent,ENT_NOQUOTES).$el->endTag(),Texy::CONTENT_TEXTUAL);}public
function
processProtect($parser,$matches){list(,$mContent)=$matches;return$this->texy->mark(htmlSpecialChars($mContent,ENT_NOQUOTES),Texy::CONTENT_TEXTUAL);}} 

class
TexyQuoteModule
extends
TexyModule{protected$allow=array('blockQuote');public
function
init(){$this->texy->registerBlockPattern(array($this,'processBlock'),'#^(?:'.TEXY_MODIFIER_H.'\n)?\>(\ +|:)(\S.*)$#mU','blockQuote');}public
function
processBlock($parser,$matches){list(,$mMod1,$mMod2,$mMod3,$mMod4,$mSpaces,$mContent)=$matches;$tx=$this->texy;$el=new
TexyBlockElement($tx);$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate($tx,'blockquote');$content='';$spaces='';do{if($mSpaces===':'){$el->tags[0]->cite=$tx->quoteModule->citeLink($mContent);$content.="\n";}else{if($spaces==='')$spaces=max(1,strlen($mSpaces));$content.=$mContent."\n";}if(!$parser->receiveNext("#^>(?:|(\\ {1,$spaces}|:)(.*))()$#mA",$matches))break;list(,$mSpaces,$mContent)=$matches;}while(TRUE);$el->parse($content);$parser->children[]=$el;}public
function
citeLink($link){$tx=$this->texy;$asReference=FALSE;if($link{0}==='['){$link=substr($link,1,-1);$ref=$this->getReference($link);if($ref){$res=Texy::completeURL($ref['URL'],$tx->linkModule->root);}else{$res=Texy::completeURL($link,$tx->linkModule->root);$asReference=TRUE;}}else{$res=Texy::completeURL($link,$tx->linkModule->root);}if(is_callable(array($tx->handler,'citeSource')))$tx->handler->citeSource($tx,$link,$asReference,$res);return$res;}} 

class
TexyScriptModule
extends
TexyModule{protected$allow=array('script');public$handler;public
function
init(){$this->texy->registerLinePattern(array($this,'processLine'),'#\{\{([^'.TEXY_MARK.']+)\}\}()#U','script');}public
function
processLine($parser,$matches){list(,$mContent)=$matches;$identifier=trim($mContent);if($identifier===''||$this->handler===NULL)return
FALSE;$args=NULL;if(preg_match('#^([a-z_][a-z0-9_]*)\s*\(([^()]*)\)$#i',$identifier,$matches)){$identifier=$matches[1];$args=explode(',',$matches[2]);array_walk($args,'trim');}if(is_callable(array($this->handler,$identifier))){array_unshift($args,$this->texy);return
call_user_func_array(array($this->handler,$identifier),$args);}if(is_callable($this->handler))return
call_user_func_array($this->handler,array($this->texy,$identifier,$args));return
FALSE;}public
function
defaultHandler($texy,$identifier,$args){if($args)$identifier.='('.implode(',',$args).')';$el=TexyHtml::el('texy:script');$el->_empty=TRUE;$el->content=$identifier;return$el;}} 

class
TexyEmoticonModule
extends
TexyModule{public$icons=array(':-)'=>'smile.gif',':-('=>'sad.gif',';-)'=>'wink.gif',':-D'=>'biggrin.gif','8-O'=>'eek.gif','8-)'=>'cool.gif',':-?'=>'confused.gif',':-x'=>'mad.gif',':-P'=>'razz.gif',':-|'=>'neutral.gif',);public$class;public$root;public$fileRoot;public
function
init(){if(empty($this->texy->allowed['emoticon']))return;krsort($this->icons);$pattern=array();foreach($this->icons
as$key=>$foo)$pattern[]=preg_quote($key,'#').'+';$this->texy->registerLinePattern(array($this,'processLine'),'#(?<=^|[\\x00-\\x20])('.implode('|',$pattern).')#','emoticon');}public
function
processLine($parser,$matches){$match=$matches[0];$tx=$this->texy;foreach($this->icons
as$emoticon=>$file){if(strncmp($match,$emoticon,strlen($emoticon))===0){$el=TexyHtml::el('img');$el->src=Texy::completeURL($file,$this->root===NULL?$tx->imageModule->root:$this->root);$el->alt=$match;$el->class[]=$this->class;$file=Texy::completePath($file,$this->fileRoot===NULL?$tx->imageModule->fileRoot:$this->fileRoot);if(is_file($file)){$size=getImageSize($file);if(is_array($size)){$el->width=$size[0];$el->height=$size[1];}}if(is_callable(array($tx->handler,'emoticon')))$tx->handler->emoticon($tx,$match,$el);$tx->summary['images'][]=$el->src;return$el;}}}} 

class
TexyTableModule
extends
TexyModule{protected$allow=array('table');public$oddClass;public$evenClass;private$isHead;private$colModifier;private$last;private$row;public
function
init(){$this->texy->registerBlockPattern(array($this,'processBlock'),'#^(?:'.TEXY_MODIFIER_HV.'\n)?'.'\|.*()$#mU','table');}public
function
processBlock($parser,$matches){list(,$mMod1,$mMod2,$mMod3,$mMod4,$mMod5)=$matches;$tx=$this->texy;$el=new
TexyBlockElement($tx);$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4,$mMod5);$el->tags[0]=$mod->generate($tx,'table');$parser->moveBackward();if($parser->receiveNext('#^\|(\#|\=){2,}(?!\\1)(.*)\\1*\|? *'.TEXY_MODIFIER_H.'?()$#Um',$matches)){list(,,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$caption=new
TexyTextualElement($tx);$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$caption->tags[0]=$mod->generate($tx,'caption');$caption->parse($mContent);$el->children[]=$caption;}$this->isHead=FALSE;$this->colModifier=array();$this->last=array();$this->row=0;while(TRUE){if($parser->receiveNext('#^\|\-{3,}$#Um',$matches)){$this->isHead=!$this->isHead;continue;}if($elRow=$this->processRow($parser)){$el->children[]=$elRow;$this->row++;continue;}break;}$parser->children[]=$el;}protected
function
processRow($parser){$tx=$this->texy;if(!$parser->receiveNext('#^\|(.*)(?:|\|\ *'.TEXY_MODIFIER_HV.'?)()$#U',$matches)){return
FALSE;}list(,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mMod5)=$matches;$elRow=new
TexyBlockElement($tx);$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4,$mMod5);$elRow->tags[0]=$mod->generate($tx,'tr');if($this->row
%
2===0){if($this->oddClass)$elRow->tags[0]->class[]=$this->oddClass;}else{if($this->evenClass)$elRow->tags[0]->class[]=$this->evenClass;}$col=0;$elField=NULL;foreach(explode('|',$mContent)as$field){if(($field=='')&&$elField){$elField->colSpan++;unset($this->last[$col]);$col++;continue;}$field=rtrim($field);if($field==='^'){if(isset($this->last[$col])){$this->last[$col]->rowSpan++;$col+=$this->last[$col]->colSpan;continue;}}if(!preg_match('#(\*??)\ *'.TEXY_MODIFIER_HV.'??(.*)'.TEXY_MODIFIER_HV.'?()$#AU',$field,$matches))continue;list(,$mHead,$mModCol1,$mModCol2,$mModCol3,$mModCol4,$mModCol5,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mMod5)=$matches;if($mModCol1||$mModCol2||$mModCol3||$mModCol4||$mModCol5){$this->colModifier[$col]=new
TexyModifier;$this->colModifier[$col]->setProperties($mModCol1,$mModCol2,$mModCol3,$mModCol4,$mModCol5);}$elField=new
TexyTableFieldElement($tx);if(isset($this->colModifier[$col]))$mod=clone$this->colModifier[$col];else$mod=new
TexyModifier;$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4,$mMod5);$elField->tags[0]=$mod->generate($tx,($this->isHead||($mHead==='*'))?'th':'td');$elField->parse($mContent);if($elField->content=='')$elField->content="\xC2\xA0";$elRow->children[]=$elField;$this->last[$col]=$elField;$col++;}return$elRow;}}class
TexyTableFieldElement
extends
TexyTextualElement{public$colSpan=1;public$rowSpan=1;public
function
toHtml(){if($this->colSpan<>1)$this->tags[0]->colspan=$this->colSpan;if($this->rowSpan<>1)$this->tags[0]->rowspan=$this->rowSpan;return
parent::toHtml();}} 

class
TexyTypographyModule
extends
TexyModule
implements
ITexyLineModule{protected$allow=array('typography');public$doubleQuotes=array("\xe2\x80\x9e","\xe2\x80\x9c");public$singleQuotes=array("\xe2\x80\x9a","\xe2\x80\x98");private$pattern,$replace;public
function
init(){$pairs=array('#(?<!"|\w)"(?!\ |")(.+)(?<!\ |")"(?!")()#U'=>$this->doubleQuotes[0].'$1'.$this->doubleQuotes[1],'#(?<!\'|\w)\'(?!\ |\')(.+)(?<!\ |\')\'(?!\')()#Uu'=>$this->singleQuotes[0].'$1'.$this->singleQuotes[1],'#(?<![.\x{2026}])\.{3,4}(?![.\x{2026}])#mu'=>"\xe2\x80\xa6",'#(\d| )-(\d| )#'=>"\$1\xe2\x80\x93\$2",'#,-#'=>",\xe2\x80\x93",'#(?<!\d)(\d{1,2}\.) (\d{1,2}\.) (\d\d)#'=>"\$1\xc2\xa0\$2\xc2\xa0\$3",'#(?<!\d)(\d{1,2}\.) (\d{1,2}\.)#'=>"\$1\xc2\xa0\$2",'# --- #'=>" \xe2\x80\x94 ",'# -- #'=>" \xe2\x80\x93 ",'# -> #'=>" \xe2\x86\x92 ",'# <- #'=>" \xe2\x86\x90 ",'# <-> #'=>" \xe2\x86\x94 ",'#(\d+)( ?)x\\2(\d+)\\2x\\2(\d+)#'=>"\$1\xc3\x97\$3\xc3\x97\$4",'#(\d+)( ?)x\\2(\d+)#'=>"\$1\xc3\x97\$3",'#(?<=\d)x(?= |,|.|$)#m'=>"\xc3\x97",'#(\S ?)\(TM\)#i'=>"\$1\xe2\x84\xa2",'#(\S ?)\(R\)#i'=>"\$1\xc2\xae",'#\(C\)( ?\S)#i'=>"\xc2\xa9\$1",'#(\d{1,3}) (\d{3}) (\d{3}) (\d{3})#'=>"\$1\xc2\xa0\$2\xc2\xa0\$3\xc2\xa0\$4",'#(\d{1,3}) (\d{3}) (\d{3})#'=>"\$1\xc2\xa0\$2\xc2\xa0\$3",'#(\d{1,3}) (\d{3})#'=>"\$1\xc2\xa0\$2",'#(\S{100,}) (\S+)$#'=>"\$1\xc2\xa0\$2",'#(?<=^| |\.|,|-|\+)(\d+)(['.TEXY_MARK_N.']*) (['.TEXY_MARK_N.']*)(['.TEXY_CHAR.'\x{b0}])#mu'=>"\$1\$2\xc2\xa0\$3\$4",'#(?<=^|[^0-9'.TEXY_CHAR.'])(['.TEXY_MARK_N.']*)([ksvzouiKSVZOUIA])(['.TEXY_MARK_N.']*) (['.TEXY_MARK_N.']*)([0-9'.TEXY_CHAR.'])#mu'=>"\$1\$2\$3\xc2\xa0\$4\$5",);$this->pattern=array_keys($pairs);$this->replace=array_values($pairs);}public
function
linePostProcess($text){if(empty($this->texy->allowed['typography']))return$text;return
preg_replace($this->pattern,$this->replace,$text);}} 
class
Texy{const
ALL=TRUE;const
NONE=FALSE;const
CONTENT_NONE="\x14";const
CONTENT_INLINE="\x15";const
CONTENT_TEXTUAL="\x16";const
CONTENT_BLOCK="\x17";static
public$xhtml=TRUE;public$encoding='utf-8';public$allowed=array();public$allowedTags;public$allowedClasses;public$allowedStyles;public$obfuscateEmail=TRUE;public$tabWidth=8;public$summary=array('images'=>array(),'links'=>array(),'preload'=>array(),);public$styleSheet='';public$mergeLines=TRUE;public$handler;public$scriptModule,$htmlModule,$imageModule,$linkModule,$phraseModule,$emoticonModule,$blockModule,$headingModule,$horizLineModule,$quoteModule,$listModule,$definitionListModule,$tableModule,$figureModule,$typographyModule,$longWordsModule;public$genericBlock,$formatter,$formatterModule,$wellForm;static
public$blockTags=array('address'=>1,'blockquote'=>1,'caption'=>1,'col'=>1,'colgroup'=>1,'dd'=>1,'div'=>1,'dl'=>1,'dt'=>1,'fieldset'=>1,'form'=>1,'h1'=>1,'h2'=>1,'h3'=>1,'h4'=>1,'h5'=>1,'h6'=>1,'hr'=>1,'iframe'=>1,'legend'=>1,'li'=>1,'object'=>1,'ol'=>1,'p'=>1,'param'=>1,'pre'=>1,'table'=>1,'tbody'=>1,'td'=>1,'tfoot'=>1,'th'=>1,'thead'=>1,'tr'=>1,'ul'=>1,);static
public$inlineTags=array('a'=>1,'abbr'=>1,'acronym'=>1,'area'=>1,'b'=>1,'big'=>1,'br'=>1,'button'=>1,'cite'=>1,'code'=>1,'del'=>1,'dfn'=>1,'em'=>1,'i'=>1,'img'=>1,'input'=>1,'ins'=>1,'kbd'=>1,'label'=>1,'map'=>1,'noscript'=>1,'optgroup'=>1,'option'=>1,'q'=>1,'samp'=>1,'script'=>1,'select'=>1,'small'=>1,'span'=>1,'strong'=>1,'sub'=>1,'sup'=>1,'textarea'=>1,'tt'=>1,'var'=>1,);static
public$inlineCont=array('br'=>1,'button'=>1,'iframe'=>1,'img'=>1,'input'=>1,'object'=>1,'script'=>1,'select'=>1,'textarea'=>1,'applet'=>1,'isindex'=>1,);static
public$emptyTags=array('img'=>1,'hr'=>1,'br'=>1,'input'=>1,'meta'=>1,'area'=>1,'base'=>1,'col'=>1,'link'=>1,'param'=>1,);private$linePatterns=array();private$blockPatterns=array();private$DOM;private$modules,$lineModules;private$marks=array();public$_mergeMode;public$_classes,$_styles;public
function
__construct(){$this->loadModules();$this->formatter=new
TexyHtmlFormatter();$this->wellForm=new
TexyHtmlWellForm();$this->genericBlock=new
TexyGenericBlock($this);$this->formatterModule=$this->formatter;$this->trustMode();$mod=new
TexyModifier;$mod->title='The best text -> HTML converter and formatter';$this->linkModule->addReference('texy','http://texy.info/','Texy!',$mod);$this->linkModule->addReference('google','http://www.google.com/search?q=%s');$this->linkModule->addReference('wikipedia','http://en.wikipedia.org/wiki/Special:Search?search=%s');}protected
function
loadModules(){$this->scriptModule=new
TexyScriptModule($this);$this->htmlModule=new
TexyHtmlModule($this);$this->imageModule=new
TexyImageModule($this);$this->linkModule=new
TexyLinkModule($this);$this->phraseModule=new
TexyPhraseModule($this);$this->emoticonModule=new
TexyEmoticonModule($this);$this->blockModule=new
TexyBlockModule($this);$this->headingModule=new
TexyHeadingModule($this);$this->horizLineModule=new
TexyHorizLineModule($this);$this->quoteModule=new
TexyQuoteModule($this);$this->listModule=new
TexyListModule($this);$this->definitionListModule=new
TexyDefinitionListModule($this);$this->tableModule=new
TexyTableModule($this);$this->figureModule=new
TexyFigureModule($this);$this->typographyModule=new
TexyTypographyModule($this);$this->longWordsModule=new
TexyLongWordsModule($this);}public
function
registerModule($module){$this->modules[]=$module;if($module
instanceof
ITexyLineModule)$this->lineModules[]=$module;}public
function
registerLinePattern($handler,$pattern,$name){if(empty($this->allowed[$name]))return;$this->linePatterns[$name]=array('handler'=>$handler,'pattern'=>$pattern,);}public
function
registerBlockPattern($handler,$pattern,$name){if(empty($this->allowed[$name]))return;$this->blockPatterns[$name]=array('handler'=>$handler,'pattern'=>$pattern.'m',);}protected
function
init(){if($this->handler&&!is_object($this->handler))throw
new
Exception('$texy->handler must be object. See documentation.');$this->_mergeMode=TRUE;$this->marks=array();if(is_array($this->allowedClasses))$this->_classes=array_flip($this->allowedClasses);else$this->_classes=$this->allowedClasses;if(is_array($this->allowedStyles))$this->_styles=array_flip($this->allowedStyles);else$this->_styles=$this->allowedStyles;$this->linePatterns=array();$this->blockPatterns=array();foreach($this->modules
as$module)$module->init();}public
function
process($text,$singleLine=FALSE){if($singleLine)$this->parseLine($text);else$this->parse($text);return$this->toHtml();}public
function
parse($text){$this->init();if(strcasecmp($this->encoding,'utf-8')!==0)$text=iconv($this->encoding,'utf-8',$text);$text=self::wash($text);$text=str_replace("\r\n","\n",$text);$text=strtr($text,"\r","\n");while(strpos($text,"\t")!==FALSE)$text=preg_replace_callback('#^(.*)\t#mU',create_function('$matches',"return \$matches[1] . str_repeat(' ', $this->tabWidth - strlen(\$matches[1]) % $this->tabWidth);"),$text);$text=preg_replace('#\xC2\xA7{2,}(?!\xC2\xA7).*(\xC2\xA7{2,}|$)(?!\xC2\xA7)#mU','',$text);$text=preg_replace("#[\t ]+$#m",'',$text);foreach($this->modules
as$module)$text=$module->preProcess($text);$this->DOM=new
TexyBlockElement($this);$this->DOM->parse($text);}public
function
parseLine($text){$this->init();if(strcasecmp($this->encoding,'utf-8')!==0)$text=iconv($this->encoding,'utf-8',$text);$text=self::wash($text);$text=rtrim(strtr($text,array("\n"=>' ',"\r"=>'')));$this->DOM=new
TexyTextualElement($this);$this->DOM->parse($text);}public
function
toHtml(){if(!$this->DOM)throw
new
Exception('Call $texy->parse() first.');$html=$this->DOM->toHtml();$html=$this->unMarks($html);$html=$this->wellForm->process($html);$html=$this->formatter->process($html);if(!self::$xhtml)$html=preg_replace('#\\s*</(colgroup|dd|dt|li|option|p|td|tfoot|th|thead|tr)>#','',$html);if(!defined('TEXY_NOTICE_SHOWED')){$html.="\n<!-- by Texy2! -->";define('TEXY_NOTICE_SHOWED',TRUE);}$html=self::unfreezeSpaces($html);if(strcasecmp($this->encoding,'utf-8')!==0){$this->_chars=&self::$charTables[strtolower($this->encoding)];if(!$this->_chars){for($i=128;$i<256;$i++){$ch=iconv($this->encoding,'UTF-8//IGNORE',chr($i));if($ch)$this->_chars[$ch]=chr($i);}}$html=preg_replace_callback('#[\x80-\x{FFFF}]#u',array($this,'utfconv'),$html);}return$html;}public
function
toText(){if(!$this->DOM)throw
new
Exception('Call $texy->parse() first.');$html=$this->DOM->toHtml();$html=$this->unMarks($html);$html=$this->wellForm->process($html);$saveLineWrap=$this->formatter->lineWrap;$this->formatter->lineWrap=FALSE;$html=$this->formatter->process($html);$this->formatter->lineWrap=$saveLineWrap;$html=self::unfreezeSpaces($html);$html=preg_replace('#<(script|style)(.*)</\\1>#Uis','',$html);$html=strip_tags($html);$html=preg_replace('#\n\s*\n\s*\n[\n\s]*\n#',"\n\n",$html);$html=Texy::decode($html);$html=strtr($html,array("\xC2\xAD"=>'',"\xC2\xA0"=>' ',));if(strcasecmp($this->encoding,'utf-8')!==0)$html=iconv('utf-8',$this->encoding.'//TRANSLIT',$html);return$html;}public
function
safeMode(){$this->allowedClasses=self::NONE;$this->allowedStyles=self::NONE;$this->allowedTags=array('a'=>array('href'),'acronym'=>array('title'),'b'=>array(),'br'=>array(),'cite'=>array(),'code'=>array(),'em'=>array(),'i'=>array(),'strong'=>array(),'sub'=>array(),'sup'=>array(),'q'=>array(),'small'=>array(),);$this->allowed['image']=FALSE;$this->allowed['linkDefinition']=FALSE;$this->linkModule->forceNoFollow=TRUE;}public
function
trustMode(){$this->allowedClasses=self::ALL;$this->allowedStyles=self::ALL;$this->allowedTags=array_merge(self::$blockTags,self::$inlineTags);$this->allowed['image']=TRUE;$this->allowed['linkDefinition']=TRUE;$this->linkModule->forceNoFollow=FALSE;$this->mergeLines=TRUE;}static
public
function
freezeSpaces($s){return
strtr($s," \t\r\n","\x01\x02\x03\x04");}static
public
function
unfreezeSpaces($s){return
strtr($s,"\x01\x02\x03\x04"," \t\r\n");}static
public
function
wash($s){return
preg_replace('#[\x01-\x04\x14-\x1F]+#','',$s);}static
public
function
encode($s){return
str_replace(array('&','<','>'),array('&amp;','&lt;','&gt;'),$s);}static
public
function
decode($s){if(strpos($s,'&')===FALSE)return$s;return
html_entity_decode($s,ENT_QUOTES,'UTF-8');}public
function
mark($child,$contentType){if($child==='')return'';$key=$contentType.strtr(base_convert(count($this->marks),10,8),'01234567',"\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F").$contentType;$this->marks[$key]=$child;return$key;}public
function
unMarks($html){return
strtr($html,$this->marks);}static
public
function
completeURL($URL,$root,&$isAbsolute=NULL){if(preg_match('#^(https?://|ftp://|www\\.|ftp\\.|/)#i',$URL)){$isAbsolute=TRUE;$URL=str_replace('&amp;','&',$URL);$lower=strtolower($URL);if(substr($lower,0,4)==='www.'){return'http://'.$URL;}elseif(substr($lower,0,4)==='ftp.'){return'ftp://'.$URL;}return$URL;}$isAbsolute=FALSE;if($root==NULL)return$URL;return
rtrim($root,'/\\').'/'.$URL;}static
public
function
completePath($path,$root){if(preg_match('#^(https?://|ftp://|www\\.|ftp\\.|/)#i',$path))return
FALSE;if(strpos($path,'..'))return
FALSE;if($root==NULL)return$path;return
rtrim($root,'/\\').'/'.$path;}public
function
getLinePatterns(){return$this->linePatterns;}public
function
getBlockPatterns(){return$this->blockPatterns;}public
function
getDOM(){return$this->DOM;}public
function
getLineModules(){return$this->lineModules;}static
private$charTables;private$_chars;private
function
utfconv($m){$m=$m[0];if(isset($this->_chars[$m]))return$this->_chars[$m];$ch1=ord($m[0]);$ch2=ord($m[1]);if(($ch2>>6)!==2)return'';if(($ch1&0xE0)===0xC0)return'&#'.((($ch1&0x1F)<<6)+($ch2&0x3F)).';';if(($ch1&0xF0)===0xE0){$ch3=ord($m[2]);if(($ch3>>6)!==2)return'';return'&#'.((($ch1&0xF)<<12)+(($ch2&0x3F)<<06)+(($ch3&0x3F))).';';}return'';}public
function
free(){foreach(array_keys(get_object_vars($this))as$key)$this->$key=NULL;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}}?>