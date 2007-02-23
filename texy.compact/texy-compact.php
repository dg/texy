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
 * @version    2.0beta for PHP5 $Revision: 64 $ $Date: 2007-02-23 04:34:49 +0100 (pá, 23 II 2007) $
 */if(version_compare(PHP_VERSION,'5.0.0','<'))die('Texy! needs PHP version 5');define('TEXY','Version 2.0beta $Revision: 64 $');define('TEXY_DIR',dirname(__FILE__).'/');

define('TEXY_CHAR','A-Za-z\x86-\x{ffff}');define('TEXY_MARK',"\x01-\x04\x14-\x1F");define('TEXY_MARK_SPACES',"\x01-\x04");define('TEXY_MARK_N',"\x14\x18-\x1F");define('TEXY_MARK_I',"\x15\x18-\x1F");define('TEXY_MARK_T',"\x16\x18-\x1F");define('TEXY_MARK_B',"\x17\x18-\x1F");define('TEXY_MODIFIER','(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??)');define('TEXY_MODIFIER_H','(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??)');define('TEXY_MODIFIER_HV','(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??)');define('TEXY_IMAGE','\[\*([^\n'.TEXY_MARK.']+)'.TEXY_MODIFIER.'? *(\*|>|<)\]');define('TEXY_LINK_REF','\[[^\[\]\*\n'.TEXY_MARK.']+\]');define('TEXY_LINK_URL','(?:\[[^\]\n]+\]|(?!\[)[^\s'.TEXY_MARK.']*?[^:);,.!?\s'.TEXY_MARK.'])');define('TEXY_LINK','(?::('.TEXY_LINK_URL.'))');define('TEXY_LINK_N','(?::('.TEXY_LINK_URL.'|:))');define('TEXY_EMAIL','[a-z0-9.+_-]+@[a-z0-9.+_-]{2,}\.[a-z]{2,}'); 

abstract
class
TexyDomElement{public$texy;public$tags;public
function
__construct($texy){$this->texy=$texy;}abstract
protected
function
getContent();public
function
toHtml(){$start=$end='';if($this->tags)foreach($this->tags
as$el){$start.=$el->startTag();$end=$el->endTag().$end;}return$start.$this->getContent().$end;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}private
function
__unset($nm){$this->__get($nm);}private
function
__isset($nm){$this->__get($nm);}}class
TexyBlockElement
extends
TexyDomElement{public$children=array();protected
function
getContent(){$s='';foreach($this->children
as$child)$s.=$child->toHtml();return$s;}public
function
parse($text){$parser=new
TexyBlockParser($this);$parser->parse($text);}}class
TexyTextualElement
extends
TexyDomElement{public$content='';public$protect=FALSE;protected
function
getContent(){if($this->protect)return
preg_replace_callback('#&\#?[a-zA-Z0-9]+;#',array(__CLASS__,'entCb'),$this->content);else
return
htmlspecialChars($this->content,ENT_NOQUOTES);}static
private
function
entCb($m){return
htmlSpecialChars(html_entity_decode($m[0],ENT_QUOTES,'UTF-8'),ENT_QUOTES);}public
function
parse($text){$parser=new
TexyLineParser($this);$parser->parse($text);}}class
TexyParagraphElement
extends
TexyTextualElement{} 

class
TexyGenericBlock{public$texy;public
function
__construct($texy){$this->texy=$texy;}public
function
process($parser,$content){if($this->texy->_mergeMode)$parts=preg_split('#(\n{2,})#',$content);else$parts=preg_split('#(\n(?! )|\n{2,})#',$content);foreach($parts
as$content){$content=trim($content);if($content==='')continue;preg_match('#^(.*)'.TEXY_MODIFIER_H.'?(\n.*)?()$#sU',$content,$matches);list(,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mContent2)=$matches;$mContent=trim($mContent.$mContent2);if($this->texy->mergeLines){$mContent=preg_replace('#\n (\S)#'," \r\\1",$mContent);$mContent=strtr($mContent,"\n\r"," \n");}$el=new
TexyParagraphElement($this->texy);$el->parse($mContent);$contentType=Texy::CONTENT_NONE;if(strpos($el->content,"\x17")!==FALSE){$contentType=Texy::CONTENT_BLOCK;}elseif(strpos($el->content,"\x16")!==FALSE){$contentType=Texy::CONTENT_TEXTUAL;}else{if(strpos($el->content,"\x15")!==FALSE)$contentType=Texy::CONTENT_INLINE;$s=trim(preg_replace('#['.TEXY_MARK.']+#','',$el->content));if(strlen($s))$contentType=Texy::CONTENT_TEXTUAL;}if($contentType===Texy::CONTENT_TEXTUAL)$tag='p';elseif($mMod1||$mMod2||$mMod3||$mMod4)$tag='div';elseif($contentType===Texy::CONTENT_BLOCK)$tag='';else$tag='div';if($tag&&(strpos($el->content,"\n")!==FALSE)){$key=$this->texy->mark('<br />',Texy::CONTENT_INLINE);$el->content=strtr($el->content,array("\n"=>$key));}if($mMod1||$mMod2||$mMod3||$mMod4){$mod=new
TexyModifier($this->texy);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate($tag);}else{$el->tags[0]=TexyHtml::el($tag);}$parser->element->children[]=$el;}}} 

class
TexyHtml{public$_name;public$_empty;public$_childNodes;static
public
function
el($name=NULL,$attrs=NULL){return
new
self($name,$attrs);}private
function
__construct($name,$attrs){if(is_array($attrs)){foreach($attrs
as$key=>$value)$this->$key=$value;}$this->_name=$name;$this->_empty=isset(Texy::$emptyTags[$name]);$this->_childNodes=array();}public
function
setElement($name){$this->_name=$name;$this->_empty=isset(Texy::$emptyTags[$name]);return$this;}public
function
setContent($content){$this->_childNodes=array($content);return$this;}public
function
addChild($content){$this->_childNodes[]=$content;return$this;}public
function
__call($m,$args){$this->$m=$args[0];return$this;}public
function
toTexy($texy){$ct=$this->getContentType();$s=$texy->mark($this->startTag(),$ct);if($this->_empty)return$s;foreach($this->_childNodes
as$val)if($val
instanceof
self)$s.=$val->toTexy($texy);else$s.=$val;$s.=$texy->mark($this->endTag(),$ct);return$s;}public
function
startTag(){if(!$this->_name)return'';$s='<'.$this->_name;static$res=array('_name'=>1,'_childNodes'=>1,'_empty'=>1,);foreach($this
as$key=>$value){if(isset($res[$key]))continue;if($value===NULL||$value===FALSE)continue;if($value===TRUE){if(Texy::$xhtml)$s.=' '.$key.'="'.$key.'"';else$s.=' '.$key;continue;}elseif(is_array($value)){$tmp=NULL;foreach($value
as$k=>$v){if($v==NULL)continue;if(is_string($k))$tmp[]=$k.':'.$v;else$tmp[]=$v;}if(!$tmp)continue;$value=implode($key==='style'?';':' ',$tmp);}$s.=' '.$key.'="'.Texy::freezeSpaces(preg_replace('~&amp;([a-zA-Z0-9]+|#x[0-9a-fA-F]+|#[0-9]+);~','&$1;',htmlSpecialChars($value,ENT_COMPAT))).'"';}if(Texy::$xhtml&&$this->_empty)return$s.' />';return$s.'>';}public
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
process($text){$this->tagStack=array();$this->tagUsed=array();$text=preg_replace_callback('#<(/?)([a-z_:][a-z0-9._:-]*)(|\s.*)(/?)>()#Uis',array($this,'cb'),$text);if($this->tagStack){$pair=end($this->tagStack);while($pair!==FALSE){$text.='</'.$pair['tag'].'>';$pair=prev($this->tagStack);}}return$text;}private
function
cb($matches){list(,$mClosing,$mTag,$mAttr,$mEmpty)=$matches;if(isset(Texy::$emptyTags[$mTag])||$mEmpty)return$mClosing?'':'<'.$mTag.$mAttr.'/>';if($mClosing){$pair=end($this->tagStack);$s='';$i=1;while($pair!==FALSE){$s.='</'.$pair['tag'].'>';if($pair['tag']===$mTag)break;$this->tagUsed[$pair['tag']]--;$pair=prev($this->tagStack);$i++;}if($pair===FALSE)return'';if(isset(Texy::$blockTags[$mTag])){array_splice($this->tagStack,-$i);return$s;}unset($this->tagStack[key($this->tagStack)]);$pair=current($this->tagStack);while($pair!==FALSE){$s.='<'.$pair['tag'].$pair['attr'].'>';@$this->tagUsed[$pair['tag']]++;$pair=next($this->tagStack);}return$s;}else{$s='';$pair=end($this->tagStack);while($pair&&isset($this->autoClose[$pair['tag']])&&isset($this->autoClose[$pair['tag']][$mTag])){$s.='</'.$pair['tag'].'>';$this->tagUsed[$pair['tag']]--;unset($this->tagStack[key($this->tagStack)]);$pair=end($this->tagStack);}$pair=array('attr'=>$mAttr,'tag'=>$mTag,);$this->tagStack[]=$pair;@$this->tagUsed[$pair['tag']]++;$s.='<'.$mTag.$mAttr.'>';return$s;}}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}private
function
__unset($nm){$this->__get($nm);}private
function
__isset($nm){$this->__get($nm);}} 

class
TexyUrl{protected$value;protected$type;protected$source;protected$root;protected$url;const
ABSOLUTE=1;const
RELATIVE=2;const
EMAIL=3;const
DIRECT=1;const
REFERENCE=2;const
IMAGE=3;public
function
__construct($value,$root,$source,$label=NULL){$value=trim($value);$this->source=$source;$this->value=$value;if($source!==self::IMAGE&&preg_match('#^'.TEXY_EMAIL.'$#i',$value)){$this->type=self::EMAIL;if(Texy::$obfuscateEmail){$s='mai';$s2='lto:'.$value;for($i=0;$i<strlen($s2);$i++)$s.='&#'.ord($s2{$i}).';';$this->url=$s;}else{$this->url='mailto:'.$value;}}elseif(preg_match('#^(https?://|ftp://|www\.|ftp\.|/)#i',$value)){$this->type=self::ABSOLUTE;$value=str_replace('%s',urlencode(Texy::wash($label)),$value);$lower=strtolower($value);if(substr($lower,0,4)==='www.'){$this->url='http://'.$value;}elseif(substr($lower,0,4)==='ftp.'){$this->url='ftp://'.$value;}else{$this->url=$value;}}else{$this->type=self::RELATIVE;$value=str_replace('%s',urlencode(Texy::wash($label)),$value);if($root==NULL)$this->url=$value;else$this->url=rtrim($root,'/\\').'/'.$value;}}public
function
isAbsolute(){return$this->type===self::ABSOLUTE;}public
function
isEmail(){return$this->type===self::EMAIL;}public
function
isImage(){return$this->source===self::IMAGE;}public
function
asURL(){return$this->url;}public
function
asTextual(){if($this->type===self::EMAIL){return
Texy::$obfuscateEmail?strtr($this->value,array('@'=>"&#160;(at)&#160;")):$this->value;}if($this->type===self::ABSOLUTE){$URL=$this->value;$lower=strtolower($URL);if(substr($lower,0,4)==='www.')$URL='none://'.$URL;elseif(substr($lower,0,4)==='ftp.')$URL='none://'.$URL;$parts=@parse_url($URL);if($parts===FALSE)return$this->value;$res='';if(isset($parts['scheme'])&&$parts['scheme']!=='none')$res.=$parts['scheme'].'://';if(isset($parts['host']))$res.=$parts['host'];if(isset($parts['path']))$res.=(strlen($parts['path'])>16?('/...'.preg_replace('#^.*(.{0,12})$#U','$1',$parts['path'])):$parts['path']);if(isset($parts['query'])){$res.=strlen($parts['query'])>4?'?...':('?'.$parts['query']);}elseif(isset($parts['fragment'])){$res.=strlen($parts['fragment'])>4?'#...':('#'.$parts['fragment']);}return$res;}return$this->value;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}private
function
__unset($nm){$this->__get($nm);}private
function
__isset($nm){$this->__get($nm);}} 

class
TexyModifier{const
HALIGN_LEFT='left';const
HALIGN_RIGHT='right';const
HALIGN_CENTER='center';const
HALIGN_JUSTIFY='justify';const
VALIGN_TOP='top';const
VALIGN_MIDDLE='middle';const
VALIGN_BOTTOM='bottom';protected$texy;public$id;public$classes=array();public$styles=array();public$attrs=array();public$hAlign;public$vAlign;public$title;public
function
__construct($texy){$this->texy=$texy;}public
function
setProperties(){$acc=Texy::$tagAttrs;foreach(func_get_args()as$arg){if($arg==NULL)continue;$argX=trim(substr($arg,1,-1));switch($arg{0}){case'(':if(strpos($argX,'&')!==FALSE)$argX=html_entity_decode($argX);$this->title=$argX;break;case'{':foreach(explode(';',$argX)as$value){$pair=explode(':',$value,2);$pair[]='';$prop=strtolower(trim($pair[0]));$value=trim($pair[1]);if($prop==='')continue;if(isset($acc[$prop]))$this->attrs[$prop]=$value;elseif($value!=='')$this->styles[$prop]=$value;}break;case'[':$argX=str_replace('#',' #',$argX);foreach(explode(' ',$argX)as$value){if($value==='')continue;if($value{0}==='#')$this->id=substr($value,1);else$this->classes[]=$value;}break;case'^':$this->vAlign=self::VALIGN_TOP;break;case'-':$this->vAlign=self::VALIGN_MIDDLE;break;case'_':$this->vAlign=self::VALIGN_BOTTOM;break;case'=':$this->hAlign=self::HALIGN_JUSTIFY;break;case'>':$this->hAlign=self::HALIGN_RIGHT;break;case'<':$this->hAlign=$arg==='<>'?self::HALIGN_CENTER:self::HALIGN_LEFT;break;}}}public
function
generate($tag){$tmp=$this->texy->allowedTags;if($tmp===Texy::ALL){$el=TexyHtml::el($tag,$this->attrs);}elseif(is_array($tmp)&&isset($tmp[$tag])){$tmp=$tmp[$tag];if($tmp===Texy::ALL){$el=TexyHtml::el($tag,$this->attrs);}else{$el=TexyHtml::el($tag);if(is_array($tmp)&&count($tmp)){$tmp=array_flip($tmp);foreach($this->attrs
as$key=>$val)if(isset($tmp[$key]))$el->$key=$val;}}}else{$el=TexyHtml::el($tag);}$el->href=NULL;$el->title=$this->title;$tmp=$this->texy->_classes;if($tmp===Texy::ALL){foreach($this->classes
as$val)$el->class[]=$val;$el->id=$this->id;}elseif(is_array($tmp)){foreach($this->classes
as$val)if(isset($tmp[$val]))$el->class[]=$val;if(isset($tmp['#'.$this->id]))$el->id=$this->id;}$tmp=$this->texy->_styles;if($tmp===Texy::ALL){foreach($this->styles
as$prop=>$val)$el->style[$prop]=$val;}elseif(is_array($tmp)){foreach($this->styles
as$prop=>$val)if(isset($tmp[$prop]))$el->style[$prop]=$val;}if($this->hAlign)$el->style['text-align']=$this->hAlign;if($this->vAlign)$el->style['vertical-align']=$this->vAlign;return$el;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}private
function
__unset($nm){$this->__get($nm);}private
function
__isset($nm){$this->__get($nm);}} 

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
__set($nm,$val){$this->__get($nm);}private
function
__unset($nm){$this->__get($nm);}private
function
__isset($nm){$this->__get($nm);}}interface
ITexyLineModule{public
function
linePostProcess($line);} 

abstract
class
TexyParser{public$element;public
function
__construct($element){$this->element=$element;}abstract
public
function
parse($text);function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}private
function
__unset($nm){$this->__get($nm);}private
function
__isset($nm){$this->__get($nm);}}class
TexyBlockParser
extends
TexyParser{private$text;private$offset;public
function
receiveNext($pattern,&$matches){$matches=NULL;$ok=preg_match($pattern.'Am',$this->text,$matches,PREG_OFFSET_CAPTURE,$this->offset);if($ok){$this->offset+=strlen($matches[0][0])+1;foreach($matches
as$key=>$value)$matches[$key]=$value[0];}return$ok;}public
function
moveBackward($linesCount=1){while(--$this->offset>0)if($this->text{$this->offset-1}==="\n")if(--$linesCount<1)break;$this->offset=max($this->offset,0);}public
function
parse($text){$tx=$this->element->texy;$this->text=$text;$this->offset=0;$pb=$tx->getBlockPatterns();$keys=array_keys($pb);$arrMatches=$arrPos=array();foreach($keys
as$key)$arrPos[$key]=-1;do{$minKey=-1;$minPos=strlen($text);if($this->offset>=$minPos)break;foreach($keys
as$index=>$key){if($arrPos[$key]<$this->offset){$delta=($arrPos[$key]===-2)?1:0;if(preg_match($pb[$key]['pattern'],$text,$arrMatches[$key],PREG_OFFSET_CAPTURE,$this->offset+$delta)){$m=&$arrMatches[$key];$arrPos[$key]=$m[0][1];foreach($m
as$keyX=>$valueX)$m[$keyX]=$valueX[0];}else{unset($keys[$index]);continue;}}if($arrPos[$key]===$this->offset){$minKey=$key;break;}if($arrPos[$key]<$minPos){$minPos=$arrPos[$key];$minKey=$key;}}$next=($minKey===-1)?strlen($text):$arrPos[$minKey];if($next>$this->offset){$str=substr($text,$this->offset,$next-$this->offset);$this->offset=$next;$tx->genericBlock->process($this,$str);continue;}$px=$pb[$minKey];$matches=$arrMatches[$minKey];$this->offset=$arrPos[$minKey]+strlen($matches[0])+1;$ok=call_user_func_array($px['handler'],array($this,$matches,$px['name']));if($ok===FALSE||($this->offset<=$arrPos[$minKey])){$this->offset=$arrPos[$minKey];$arrPos[$minKey]=-2;continue;}$arrPos[$minKey]=-1;}while(1);}}class
TexyLineParser
extends
TexyParser{public
function
parse($text){$element=$this->element;$tx=$element->texy;$offset=0;$pl=$tx->getLinePatterns();$keys=array_keys($pl);$arrMatches=$arrPos=array();foreach($keys
as$key)$arrPos[$key]=-1;do{$minKey=-1;$minPos=strlen($text);foreach($keys
as$index=>$key){if($arrPos[$key]<$offset){$delta=($arrPos[$key]===-2)?1:0;if(preg_match($pl[$key]['pattern'],$text,$arrMatches[$key],PREG_OFFSET_CAPTURE,$offset+$delta)){$m=&$arrMatches[$key];if(!strlen($m[0][0]))continue;$arrPos[$key]=$m[0][1];foreach($m
as$keyx=>$value)$m[$keyx]=$value[0];}else{unset($keys[$index]);continue;}}if($arrPos[$key]<$minPos){$minPos=$arrPos[$key];$minKey=$key;}}if($minKey===-1)break;$px=$pl[$minKey];$offset=$start=$arrPos[$minKey];$replacement=call_user_func_array($px['handler'],array($this,$arrMatches[$minKey],$px['name']));if($replacement
instanceof
TexyTextualElement){$replacement=$replacement->content;$offset+=strlen($replacement);}elseif($replacement
instanceof
TexyHtml){$replacement=$replacement->toTexy($tx);$offset+=strlen($replacement);}elseif($replacement===FALSE){$arrPos[$minKey]=-2;continue;}elseif(!is_string($replacement)){$replacement=(string)$replacement;}$len=strlen($arrMatches[$minKey][0]);$text=substr_replace($text,$replacement,$start,$len);$delta=strlen($replacement)-$len;foreach($keys
as$key){if($arrPos[$key]<$start+$len)$arrPos[$key]=-1;else$arrPos[$key]+=$delta;}$arrPos[$minKey]=-2;}while(1);if(strpos($text,'&')!==FALSE)$text=html_entity_decode($text,ENT_QUOTES,'UTF-8');foreach($tx->getLineModules()as$module)$text=$module->linePostProcess($text);$element->content=$text;}} 

class
TexyBlockModule
extends
TexyModule{protected$allow=array('blocks','blockPre','blockCode','blockHtml','blockText','blockSource','blockComment');public
function
init(){$this->texy->registerBlockPattern($this,'processBlock','#^/--+ *(?:(code|text|html|div|notexy|source|comment)( .*)?|) *'.TEXY_MODIFIER_H.'?\n(.*\n)?(?:\\\\--+ *\\1?|\z)()$#mUsi','blocks');}public
function
processBlock($parser,$matches,$name){list(,$mType,$mSecond,$mMod1,$mMod2,$mMod3,$mMod4,$mContent)=$matches;$tx=$this->texy;$mType=trim(strtolower($mType));$mSecond=trim(strtolower($mSecond));$mContent=trim($mContent,"\n");if(!$mType)$mType='pre';if($mType==='notexy')$mType='html';if($mType==='html'&&!$tx->allowed['blockHtml'])$mType='text';if($mType==='source'&&!$tx->allowed['blockSource'])$mType='pre';if($mType==='code'&&!$tx->allowed['blockCode'])$mType='pre';if($mType==='pre'&&!$tx->allowed['blockPre'])$mType='div';$type='block'.ucfirst($mType);if(empty($tx->allowed[$type])){$mType='div';$type='blockDiv';}$mod=new
TexyModifier($tx);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);switch($mType){case'div':$el=new
TexyBlockElement($tx);$el->tags[0]=$mod->generate('div');if($spaces=strspn($mContent,' '))$mContent=preg_replace("#^ {1,$spaces}#m",'',$mContent);$el->parse($mContent);$parser->element->children[]=$el;break;case'source':if($spaces=strspn($mContent,' '))$mContent=preg_replace("#^ {1,$spaces}#m",'',$mContent);$el=new
TexyBlockElement($tx);$el->parse($mContent);$html=$el->toHtml();$html=$tx->unMarks($html);$html=$tx->wellForm->process($html);$html=$tx->formatter->process($html);$el=new
TexyTextualElement($tx);$el->tags[0]=$mod->generate('pre');$el->tags[1]=TexyHtml::el('code')->class('html');$el->content=$html;$parser->element->children[]=$el;break;case'comment':break;case'html':preg_match_all('#<(/?)([a-z][a-z0-9_:-]*)((?:\s+[a-z0-9:-]+|=\s*"[^"'.TEXY_MARK.']*"|=\s*\'[^\''.TEXY_MARK.']*\'|=[^\s>'.TEXY_MARK.']+)*)\s*(/?)>|<!--([^'.TEXY_MARK.']*?)-->#is',$mContent,$matches,PREG_OFFSET_CAPTURE|PREG_SET_ORDER);foreach(array_reverse($matches)as$m){$offset=$m[0][1];foreach($m
as$key=>$val)$m[$key]=$val[0];$mContent=substr_replace($mContent,$tx->htmlModule->process($this,$m),$offset,strlen($m[0]));}$el=new
TexyTextualElement($tx);$el->content=html_entity_decode($mContent,ENT_QUOTES,'UTF-8');$parser->element->children[]=$el;break;case'text':$el=new
TexyTextualElement($tx);$mContent=nl2br(htmlSpecialChars($mContent,ENT_NOQUOTES));$el->content=$tx->mark($mContent,Texy::CONTENT_BLOCK);$parser->element->children[]=$el;break;default:$el=new
TexyTextualElement($tx);$el->tags[0]=$mod->generate('pre');$el->tags[0]->class[]=$mSecond;if($mType!=='pre'){$el->tags[1]=TexyHtml::el($mType);}if($spaces=strspn($mContent,' '))$mContent=preg_replace("#^ {1,$spaces}#m",'',$mContent);$el->content=$mContent;if(is_callable(array($tx->handler,$type)))$tx->handler->$type($tx,$el,$mSecond,$mod);$parser->element->children[]=$el;}}} 

class
TexyHeadingModule
extends
TexyModule{const
DYNAMIC=1,FIXED=2;protected$allow=array('headingSurrounded','headingUnderlined');public$top=1;public$title;public$balancing=TexyHeadingModule::DYNAMIC;public$levels=array('#'=>0,'*'=>1,'='=>2,'-'=>3,);private$_rangeUnderline;private$_deltaUnderline;private$_rangeSurround;private$_deltaSurround;public
function
init(){$this->texy->registerBlockPattern($this,'processBlockUnderline','#^(\S.*)'.TEXY_MODIFIER_H.'?\n'.'(\#|\*|\=|\-){3,}$#mU','headingUnderlined');$this->texy->registerBlockPattern($this,'processBlockSurround','#^((\#|\=){2,})(?!\\2)(.+)\\2*'.TEXY_MODIFIER_H.'?()$#mU','headingSurrounded');}public
function
preProcess($text){$this->_rangeUnderline=array(10,0);$this->_rangeSurround=array(10,0);$this->title=NULL;$foo=NULL;$this->_deltaUnderline=&$foo;$bar=NULL;$this->_deltaSurround=&$bar;return$text;}public
function
processBlockUnderline($parser,$matches){list(,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mLine)=$matches;$el=new
TexyHeadingElement($this->texy);if($mMod1||$mMod2||$mMod3||$mMod4){$mod=new
TexyModifier($this->texy);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate('hx');}else{$el->tags[0]=TexyHtml::el('hx');}$el->level=$this->levels[$mLine];if($this->balancing===self::DYNAMIC)$el->deltaLevel=&$this->_deltaUnderline;$el->parse(trim($mContent));$parser->element->children[]=$el;if($this->title===NULL)$this->title=Texy::wash($el->content);$this->_rangeUnderline[0]=min($this->_rangeUnderline[0],$el->level);$this->_rangeUnderline[1]=max($this->_rangeUnderline[1],$el->level);$this->_deltaUnderline=-$this->_rangeUnderline[0];$this->_deltaSurround=-$this->_rangeSurround[0]+($this->_rangeUnderline[1]?($this->_rangeUnderline[1]-$this->_rangeUnderline[0]+1):0);}public
function
processBlockSurround($parser,$matches){list(,$mLine,,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$el=new
TexyHeadingElement($this->texy);if($mMod1||$mMod2||$mMod3||$mMod4){$mod=new
TexyModifier($this->texy);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate('hx');}else{$el->tags[0]=TexyHtml::el('hx');}$el->level=7-min(7,max(2,strlen($mLine)));if($this->balancing===self::DYNAMIC)$el->deltaLevel=&$this->_deltaSurround;$el->parse(trim($mContent));$parser->element->children[]=$el;if($this->title===NULL)$this->title=Texy::wash($el->content);$this->_rangeSurround[0]=min($this->_rangeSurround[0],$el->level);$this->_rangeSurround[1]=max($this->_rangeSurround[1],$el->level);$this->_deltaSurround=-$this->_rangeSurround[0]+($this->_rangeUnderline[1]?($this->_rangeUnderline[1]-$this->_rangeUnderline[0]+1):0);}}class
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
init(){$this->texy->registerBlockPattern($this,'processBlock','#^(\- |\-|\* |\*){3,}\ *'.TEXY_MODIFIER_H.'?()$#mU','horizLine');}public
function
processBlock($parser,$matches){list(,,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$el=new
TexyBlockElement($this->texy);if($mMod1||$mMod2||$mMod3||$mMod4){$mod=new
TexyModifier($this->texy);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate('hr');}else{$el->tags[0]=TexyHtml::el('hr');}$parser->element->children[]=$el;}} 

class
TexyHtmlModule
extends
TexyModule{protected$allow=array('html','htmlTag','htmlComment');public$safeTags=array('a'=>array('href','rel','title','lang'),'abbr'=>array('title','lang'),'acronym'=>array('title','lang'),'b'=>array('title','lang'),'br'=>array(),'cite'=>array('title','lang'),'code'=>array('title','lang'),'dfn'=>array('title','lang'),'em'=>array('title','lang'),'i'=>array('title','lang'),'kbd'=>array('title','lang'),'q'=>array('cite','title','lang'),'samp'=>array('title','lang'),'small'=>array('title','lang'),'span'=>array('title','lang'),'strong'=>array('title','lang'),'sub'=>array('title','lang'),'sup'=>array('title','lang'),'var'=>array('title','lang'),);public
function
init(){$this->texy->registerLinePattern($this,'process','#<(/?)([a-z][a-z0-9_:-]*)((?:\s+[a-z0-9:-]+|=\s*"[^"'.TEXY_MARK.']*"|=\s*\'[^\''.TEXY_MARK.']*\'|=[^\s>'.TEXY_MARK.']+)*)\s*(/?)>|<!--([^'.TEXY_MARK.']*?)-->#is','html');}public
function
process($parser,$matches){$matches[]=NULL;list($match,$mClosing,$mTag,$mAttr,$mEmpty,$mComment)=$matches;$tx=$this->texy;if($mTag==''){if(empty($tx->allowed['htmlComment']))return
substr($matches[5],0,1)==='['?$match:'';return$tx->mark($match,Texy::CONTENT_NONE);}if(empty($tx->allowed['htmlTag']))return
FALSE;$tag=strtolower($mTag);if(!isset(Texy::$validTags[$tag]))$tag=$mTag;$aTags=$tx->allowedTags;if(!$aTags)return
FALSE;if(is_array($aTags)){if(!isset($aTags[$tag]))return
FALSE;$aAttrs=$aTags[$tag];}else{$aAttrs=NULL;}$isEmpty=$mEmpty==='/';if(!$isEmpty&&substr($mAttr,-1)==='/'){$mAttr=substr($mAttr,0,-1);$isEmpty=TRUE;}$isOpening=$mClosing!=='/';if($isEmpty&&!$isOpening)return
FALSE;$el=TexyHtml::el($tag);if($aTags===Texy::ALL&&$isEmpty)$el->_empty=TRUE;if(!$isOpening)return$tx->mark($el->endTag(),$el->getContentType());if(is_array($aAttrs))$aAttrs=array_flip($aAttrs);else$aAttrs=NULL;preg_match_all('#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is',$mAttr,$matches2,PREG_SET_ORDER);foreach($matches2
as$m){$key=strtolower($m[1]);if($aAttrs!==NULL&&!isset($aAttrs[$key]))continue;$val=$m[2];if($val==NULL)$el->$key=TRUE;elseif($val{0}==='\''||$val{0}==='"')$el->$key=substr($val,1,-1);else$el->$key=$val;}$modifier=new
TexyModifier($tx);if(isset($el->class)){$tmp=$tx->_classes;if(is_array($tmp)){$el->class=explode(' ',$el->class);foreach($el->class
as$key=>$val)if(!isset($tmp[$val]))unset($el->class[$key]);if(!isset($tmp['#'.$el->id]))$el->id=NULL;}elseif($tmp!==Texy::ALL){$el->class=$el->id=NULL;}}if(isset($el->style)){$tmp=$tx->_styles;if(is_array($tmp)){$styles=explode(';',$el->style);$el->style=NULL;foreach($styles
as$value){$pair=explode(':',$value,2);$pair[]='';$prop=strtolower(trim($pair[0]));$value=trim($pair[1]);if($value!==''&&isset($tmp[$prop]))$el->style[$prop]=$value;}}elseif($tmp!==Texy::ALL){$el->style=NULL;}}if($tag==='img'){if(!isset($el->src))return
FALSE;$tx->summary['images'][]=$el->src;}elseif($tag==='a'){if(!isset($el->href)&&!isset($el->name)&&!isset($el->id))return
FALSE;if(isset($el->href)){$tx->summary['links'][]=$el->href;}}return$tx->mark($el->startTag(),$el->getContentType());}public
function
trustMode($onlyValidTags=TRUE){$this->texy->allowedTags=$onlyValidTags?Texy::$validTags:Texy::ALL;}public
function
safeMode($allowSafeTags=TRUE){$this->texy->allowedTags=$allowSafeTags?$this->safeTags:Texy::NONE;}} 

class
TexyImageDescModule
extends
TexyModule{protected$allow=array('imageDesc');public$boxClass='image';public$leftClass='image left';public$rightClass='image right';public
function
init(){$this->texy->registerBlockPattern($this,'processBlock','#^'.TEXY_IMAGE.TEXY_LINK_N.'?? +\*\*\* +(.*)'.TEXY_MODIFIER_H.'?()$#mU','imageDesc');}public
function
processBlock($parser,$matches){list(,$mURLs,$mImgMod1,$mImgMod2,$mImgMod3,$mImgMod4,$mLink,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$tx=$this->texy;$el=new
TexyBlockElement($tx);list($URL,$overURL,$width,$height,$imgMod)=$tx->imageModule->factory1($mURLs,$mImgMod1,$mImgMod2,$mImgMod3,$mImgMod4);$mod=new
TexyModifier($tx);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$hAlign=$imgMod->hAlign;$mod->hAlign=$imgMod->hAlign=NULL;$elImage=$tx->imageModule->factoryEl($URL,$overURL,$width,$height,$imgMod,$mLink);$el->tags[0]=$mod->generate('div');if($hAlign===TexyModifier::HALIGN_LEFT){$el->tags[0]->class[]=$this->leftClass;}elseif($hAlign===TexyModifier::HALIGN_RIGHT){$el->tags[0]->class[]=$this->rightClass;}elseif($tx->imageDescModule->boxClass)$el->tags[0]->class[]=$this->boxClass;$elImg=new
TexyTextualElement($tx);$el->children[]=$elImg;$elDesc=new
TexyBlockElement($tx);$elDesc->parse(ltrim($mContent));$el->children[]=$elDesc;if($mLink){if($mLink===':'){$elLink=$tx->linkModule->factoryEl(new
TexyUrl($URL,$tx->imageModule->linkedRoot,TexyUrl::IMAGE),new
TexyModifier($tx));}else{$elLink=$tx->linkModule->factory($mLink,NULL,NULL,NULL,NULL);}$elLink->addChild($elImage);$elImg->content=$elLink->toTexy($tx);}else{$elImg->content=$elImage->toTexy($tx);}$parser->element->children[]=$el;}} 

class
TexyImageModule
extends
TexyModule{protected$allow=array('image');public$webRoot='images/';public$linkedRoot='images/';public$fileRoot;public$leftClass;public$rightClass;public$defaultAlt='';protected$references=array();public$root;public$rootPrefix='';public
function
__construct($texy){parent::__construct($texy);$this->root=&$this->webRoot;$this->rootPrefix=&$this->fileRoot;if(isset($_SERVER['SCRIPT_NAME'])){$this->fileRoot=dirname($_SERVER['SCRIPT_NAME']);}}public
function
init(){$this->texy->registerLinePattern($this,'processLine','#'.TEXY_IMAGE.TEXY_LINK_N.'??()#U','image');}public
function
addReference($name,$URLs,$modifier=NULL){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}if(!$modifier)$modifier=new
TexyModifier($this->texy);list($URL,$overURL,$width,$height)=self::parseContent($URLs);$this->references[$name]=array('URL'=>$URL,'overURL'=>$overURL,'modifier'=>$modifier,'width'=>$width,'height'=>$height,'name'=>$name,);}public
function
getReference($name){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}if(isset($this->references[$name]))return$this->references[$name];return
FALSE;}static
private
function
parseContent($content){$content=explode('|',$content);if(preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U',$content[0],$matches)){$URL=trim($matches[1]);$width=(int)$matches[2];$height=(int)$matches[3];}else{$URL=trim($content[0]);$width=$height=NULL;}$overURL=NULL;if(isset($content[1])){$content[1]=trim($content[1]);if($content[1]!=='')$overURL=$content[1];}return
array($URL,$overURL,$width,$height);}public
function
preProcess($text){return
preg_replace_callback('#^\[\*([^\n]+)\*\]:\ +(.+)\ *'.TEXY_MODIFIER.'?()$#mU',array($this,'processReferenceDefinition'),$text);}public
function
processReferenceDefinition($matches){list(,$mRef,$mURLs,$mMod1,$mMod2,$mMod3)=$matches;$mod=new
TexyModifier($this->texy);$mod->setProperties($mMod1,$mMod2,$mMod3);$this->addReference($mRef,$mURLs,$mod);return'';}public
function
processLine($parser,$matches){list(,$mURLs,$mMod1,$mMod2,$mMod3,$mMod4,$mLink)=$matches;$tx=$this->texy;list($URL,$overURL,$width,$height,$mod)=$this->factory1($mURLs,$mMod1,$mMod2,$mMod3,$mMod4);$el=$this->factoryEl($URL,$overURL,$width,$height,$mod,$mLink);if(is_callable(array($tx->handler,'image')))$tx->handler->image($tx,$el,$mod);if($mLink){if($mLink===':'){$elLink=$tx->linkModule->factoryEl(new
TexyUrl($URL,$this->linkedRoot,TexyUrl::IMAGE),new
TexyModifier($tx));}else{$elLink=$tx->linkModule->factory($mLink,NULL,NULL,NULL,NULL);}$elLink->addChild($el);return$elLink;}return$el;}public
function
factory1($mContent,$mMod1,$mMod2,$mMod3,$mMod4){$mContent=trim($mContent);$ref=$this->getReference($mContent);if($ref){$URL=$ref['URL'];$overURL=$ref['overURL'];$width=$ref['width'];$height=$ref['height'];$modifier=clone$ref['modifier'];}else{list($URL,$overURL,$width,$height)=self::parseContent($mContent);$modifier=new
TexyModifier($this->texy);}$modifier->setProperties($mMod1,$mMod2,$mMod3,$mMod4);return
array($URL,$overURL,$width,$height,$modifier);}public
function
factoryEl($URL,$overURL,$width,$height,$modifier,$link){$tx=$this->texy;$src=new
TexyUrl($URL,$this->webRoot,TexyUrl::IMAGE);$src=$src->asURL();$tx->summary['images'][]=$src;$alt=$modifier->title!==NULL?$modifier->title:$this->defaultAlt;$modifier->title=NULL;$hAlign=$modifier->hAlign;$modifier->hAlign=NULL;$el=$modifier->generate('img');if($hAlign===TexyModifier::HALIGN_LEFT){if($this->leftClass!='')$el->class[]=$this->leftClass;else$el->style['float']='left';}elseif($hAlign===TexyModifier::HALIGN_RIGHT){if($this->rightClass!='')$el->class[]=$this->rightClass;else$el->style['float']='right';}if($width){$el->width=$width;$el->height=$height;}elseif(is_file($this->fileRoot.'/'.$URL)){$size=getImageSize($this->fileRoot.'/'.$URL);if(is_array($size)){$el->width=$size[0];$el->height=$size[1];}}$el->src=$src;$el->alt=(string)$alt;if($overURL!==NULL){$overSrc=new
TexyUrl($overURL,$this->webRoot,TexyUrl::IMAGE);$overSrc=$overSrc->asURL();$el->onmouseover='this.src=\''.addSlashes($overSrc).'\'';$el->onmouseout='this.src=\''.addSlashes($src).'\'';$tx->summary['preload'][]=$overSrc;}$tmp=$el->alt;unset($el->alt);$el->alt=$tmp;return$el;}} 

class
TexyLinkModule
extends
TexyModule{protected$allow=array('linkReference','linkEmail','linkURL','linkQuick','linkDefinition');public$root='';public$emailOnClick;public$imageOnClick='return !popupImage(this.href)';public$popupOnClick='return !popup(this.href)';public$forceNoFollow=FALSE;protected$references=array();static
private$deadlock;public
function
init(){self::$deadlock=array();$tx=$this->texy;$tx->registerLinePattern($this,'processLineQuick','#(['.TEXY_CHAR.'0-9@\#$%&.,_-]+)(?=:\[)'.TEXY_LINK.'()#Uu','linkQuick');$tx->registerLinePattern($this,'processLineReference','#('.TEXY_LINK_REF.')#U','linkReference');$tx->registerLinePattern($this,'processLineURL','#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#iu','linkURL');$tx->registerLinePattern($this,'processLineURL','#(?<=\s|^|\(|\[|\<|:)'.TEXY_EMAIL.'#i','linkEmail');}public
function
addReference($name,$URL,$label=NULL,$modifier=NULL){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}if(!$modifier)$modifier=new
TexyModifier($this->texy);$this->references[$name]=array('URL'=>$URL,'label'=>$label,'modifier'=>$modifier,'name'=>$name,);}public
function
getReference($name){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}if(isset($this->references[$name]))return$this->references[$name];$pos=strpos($name,'?');if($pos===FALSE)$pos=strpos($name,'#');if($pos!==FALSE){$name2=substr($name,0,$pos);if(isset($this->references[$name2])){$ref=$this->references[$name2];$ref['URL'].=substr($name,$pos);return$ref;}}return
FALSE;}public
function
preProcess($text){if($this->texy->allowed['linkDefinition'])return
preg_replace_callback('#^\[([^\[\]\#\?\*\n]+)\]: +(\S+)(\ .+)?'.TEXY_MODIFIER.'?()$#mU',array($this,'processReferenceDefinition'),$text);return$text;}public
function
processReferenceDefinition($matches){list(,$mRef,$mLink,$mLabel,$mMod1,$mMod2,$mMod3)=$matches;$mod=new
TexyModifier($this->texy);$mod->setProperties($mMod1,$mMod2,$mMod3);$this->addReference($mRef,$mLink,trim($mLabel),$mod);return'';}public
function
processLineQuick($parser,$matches){list(,$mContent,$mLink)=$matches;$el=$this->factory($mLink,NULL,NULL,NULL,$mContent);$el->addChild($mContent);return$el->toTexy($this->texy);}public
function
processLineReference($parser,$matches){list($match,$mRef)=$matches;$tx=$this->texy;$name=substr($mRef,1,-1);$ref=$this->getReference($name);if(!$ref){if(is_callable(array($tx->handler,'reference')))return$tx->handler->reference($tx,$name);return
FALSE;}if($ref['label']){if(isset(self::$deadlock[$mRef['name']])){$content=$ref['label'];}else{$label=new
TexyTextualElement($tx);self::$deadlock[$mRef['name']]=TRUE;$label->parse($ref['label']);$content=$label->content;unset(self::$deadlock[$mRef['name']]);}}else{$link=new
TexyUrl($ref['URL'],$this->root,TexyUrl::DIRECT);$content=$link->asTextual();}$el=$this->factory($mRef,NULL,NULL,NULL,NULL);$el->addChild($content);return$el;}public
function
processLineURL($parser,$matches){list($mURL)=$matches;$link=new
TexyUrl($mURL,NULL,TexyUrl::DIRECT);$el=$this->factoryEl($link,new
TexyModifier($this->texy));$el->addChild($link->asTextual());return$el;}public
function
factory($dest,$mMod1,$mMod2,$mMod3,$label){$src=TexyUrl::DIRECT;$root=$this->root;$tx=$this->texy;if(strlen($dest)>1&&$dest{0}==='['&&$dest{1}!=='*'){$dest=substr($dest,1,-1);$ref=$this->getReference($dest);if($ref){$dest=$ref['URL'];$modifier=clone$ref['modifier'];}else{$src=TexyUrl::REFERENCE;$modifier=new
TexyModifier($tx);}}elseif(strlen($dest)>1&&$dest{0}==='['&&$dest{1}==='*'){$src=TexyUrl::IMAGE;$root=$tx->imageModule->linkedRoot;$dest=trim(substr($dest,2,-2));$ref=$tx->imageModule->getReference($dest);if($ref){$dest=$ref['URL'];$modifier=clone$ref['modifier'];}else{$modifier=new
TexyModifier($tx);}}else{$modifier=new
TexyModifier($tx);}$modifier->setProperties($mMod1,$mMod2,$mMod3);$link=new
TexyUrl($dest,$root,$src,$label);return$this->factoryEl($link,$modifier);}public
function
factoryEl($link,$modifier){$classes=array_flip($modifier->classes);$nofollow=isset($classes['nofollow']);$popup=isset($classes['popup']);unset($classes['nofollow'],$classes['popup']);$modifier->classes=array_flip($classes);$el=$modifier->generate('a');$this->texy->summary['links'][]=$el->href=$link->asURL();if($nofollow)$el->rel[]='nofollow';if($popup)$el->onclick=$this->popupOnClick;if(!$nofollow&&$this->forceNoFollow&&$link->isAbsolute())$el->rel[]='nofollow';if($link->isEmail())$el->onclick=$this->emailOnClick;if($link->isImage())$el->onclick=$this->imageOnClick;return$el;}} 

class
TexyListModule
extends
TexyModule{protected$allow=array('list');public$bullets=array('*'=>TRUE,'-'=>TRUE,'+'=>TRUE,'1.'=>TRUE,'1)'=>TRUE,'I.'=>TRUE,'I)'=>TRUE,'a)'=>TRUE,'A)'=>TRUE,);private$translate=array('*'=>array('\*','','ul'),'-'=>array('[\x{2013}-]','','ul'),'+'=>array('\+','','ul'),'1.'=>array('\d+\.\ ','','ol'),'1)'=>array('\d+\)','','ol'),'I.'=>array('[IVX]+\.\ ','upper-roman','ol'),'I)'=>array('[IVX]+\)','upper-roman','ol'),'a)'=>array('[a-z]\)','lower-alpha','ol'),'A)'=>array('[A-Z]\)','upper-alpha','ol'),);public
function
init(){$bullets=array();foreach($this->bullets
as$bullet=>$allowed)if($allowed)$bullets[]=$this->translate[$bullet][0];$this->texy->registerBlockPattern($this,'processBlock','#^(?:'.TEXY_MODIFIER_H.'\n)?'.'('.implode('|',$bullets).')(\n?)\ +\S.*$#mUu','list');}public
function
processBlock($parser,$matches){list(,$mMod1,$mMod2,$mMod3,$mMod4,$mBullet,$mNewLine)=$matches;$tx=$this->texy;$el=new
TexyBlockElement($tx);$bullet='';foreach($this->translate
as$type)if(preg_match('#'.$type[0].'#Au',$mBullet)){$bullet=$type[0];$tag=$type[2];$style=$type[1];break;}if($mMod1||$mMod2||$mMod3||$mMod4){$mod=new
TexyModifier($tx);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate($tag);}else{$el->tags[0]=TexyHtml::el($tag);}$el->tags[0]->style['list-style-type']=$style;$parser->moveBackward($mNewLine?2:1);$count=0;while($elItem=$this->processItem($parser,$bullet,FALSE,'li')){$el->children[]=$elItem;$count++;}if(!$count)return
FALSE;$parser->element->children[]=$el;}public
function
processItem($parser,$bullet,$indented,$tag){$tx=$this->texy;$spacesBase=$indented?('\ {1,}'):'';$patternItem="#^\n?($spacesBase)$bullet(\n?)(\\ +)(\\S.*)?".TEXY_MODIFIER_H."?()$#mAUu";if(!$parser->receiveNext($patternItem,$matches)){return
FALSE;}list(,$mIndent,$mNewLine,$mSpace,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$elItem=new
TexyBlockElement($tx);if($mMod1||$mMod2||$mMod3||$mMod4){$mod=new
TexyModifier($tx);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$elItem->tags[0]=$mod->generate($tag);}else{$elItem->tags[0]=TexyHtml::el($tag);}$spaces=$mNewLine?strlen($mSpace):'';$content=' '.$mContent;while($parser->receiveNext('#^(\n*)'.$mIndent.'(\ {1,'.$spaces.'})(.*)()$#Am',$matches)){list(,$mBlank,$mSpaces,$mContent)=$matches;if($spaces==='')$spaces=strlen($mSpaces);$content.="\n".$mBlank.$mContent;}$tmp=$tx->_mergeMode;$tx->_mergeMode=FALSE;$elItem->parse($content);$tx->_mergeMode=$tmp;if($elItem->children&&$elItem->children[0]instanceof
TexyParagraphElement)$elItem->children[0]->tags[0]->setElement(NULL);return$elItem;}} 

class
TexyDefinitionListModule
extends
TexyListModule{protected$allow=array('listDefinition');public$bullets=array('*'=>TRUE,'-'=>TRUE,'+'=>TRUE,);private$translate=array('*'=>array('\*'),'-'=>array('[\x{2013}-]'),'+'=>array('\+'),);public
function
init(){$bullets=array();foreach($this->bullets
as$bullet=>$allowed)if($allowed)$bullets[]=$this->translate[$bullet][0];$this->texy->registerBlockPattern($this,'processBlock','#^(?:'.TEXY_MODIFIER_H.'\n)?'.'(\S.*)\:\ *'.TEXY_MODIFIER_H.'?\n'.'(\ +)('.implode('|',$bullets).')\ +\S.*$#mUu','listDefinition');}public
function
processBlock($parser,$matches){list(,$mMod1,$mMod2,$mMod3,$mMod4,$mContentTerm,$mModTerm1,$mModTerm2,$mModTerm3,$mModTerm4,$mSpaces,$mBullet)=$matches;$tx=$this->texy;$el=new
TexyBlockElement($tx);$bullet='';foreach($this->translate
as$type)if(preg_match('#'.$type[0].'#Au',$mBullet)){$bullet=$type[0];break;}if($mMod1||$mMod2||$mMod3||$mMod4){$mod=new
TexyModifier($tx);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate('dl');}else{$el->tags[0]=TexyHtml::el('dl');}$parser->moveBackward(2);$patternTerm='#^\n?(\S.*)\:\ *'.TEXY_MODIFIER_H.'?()$#mUA';$bullet=preg_quote($mBullet);while(TRUE){if($elItem=$this->processItem($parser,preg_quote($mBullet),TRUE,'dd')){$el->children[]=$elItem;continue;}if($parser->receiveNext($patternTerm,$matches)){list(,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$elItem=new
TexyTextualElement($tx);if($mMod1||$mMod2||$mMod3||$mMod4){$mod=new
TexyModifier($tx);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$elItem->tags[0]=$mod->generate('dt');}else{$elItem->tags[0]=TexyHtml::el('dt');}$elItem->parse($mContent);$el->children[]=$elItem;continue;}break;}$parser->element->children[]=$el;}} 

class
TexyLongWordsModule
extends
TexyModule
implements
ITexyLineModule{protected$allow=array('longWords');public$wordLimit=20;const
DONT=0,HERE=1,AFTER=2;private$consonants=array('b','c','d','f','g','h','j','k','l','m','n','p','q','r','s','t','v','w','x','z','B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Z',"\xc4\x8d","\xc4\x8f","\xc5\x88","\xc5\x99","\xc5\xa1","\xc5\xa5","\xc5\xbe","\xc4\x8c","\xc4\x8e","\xc5\x87","\xc5\x98","\xc5\xa0","\xc5\xa4","\xc5\xbd");private$vowels=array('a','e','i','o','u','y','A','E','I','O','U','Y',"\xc3\xa1","\xc3\xa9","\xc4\x9b","\xc3\xad","\xc3\xb3","\xc3\xba","\xc5\xaf","\xc3\xbd","\xc3\x81","\xc3\x89","\xc4\x9a","\xc3\x8d","\xc3\x93","\xc3\x9a","\xc5\xae","\xc3\x9d");private$before_r=array('b','B','c','C','d','D','f','F','g','G','k','K','p','P','r','R','t','T','v','V',"\xe8","\xc8","\xef","\xcf","\xf8","\xd8","\x9d","\x8d","\xc4\x8d","\xc4\x8c","\xc4\x8f","\xc4\x8e","\xc5\x99","\xc5\x98","\xc5\xa5","\xc5\xa4");private$before_l=array('b','B','c','C','d','D','f','F','g','G','k','K','l','L','p','P','t','T','v','V',"\xc4\x8d","\xc4\x8c","\xc4\x8f","\xc4\x8e","\xc5\xa5","\xc5\xa4");private$before_h=array('c','C','s','S');private$doubleVowels=array('a','A','o','O');public
function
__construct($texy){parent::__construct($texy);$this->consonants=array_flip($this->consonants);$this->vowels=array_flip($this->vowels);$this->before_r=array_flip($this->before_r);$this->before_l=array_flip($this->before_l);$this->before_h=array_flip($this->before_h);$this->doubleVowels=array_flip($this->doubleVowels);}public
function
linePostProcess($text){if(empty($this->texy->allowed['longWords']))return$text;return
preg_replace_callback('#[^\ \n\t\-\x{2013}\x{a0}\x{ad}\x15\x16\x17'.TEXY_MARK_SPACES.']{'.$this->wordLimit.',}#u',array($this,'_replace'),$text);}private
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
init(){$tx=$this->texy;$tx->registerLinePattern($this,'processPhrase','#(?<!\*)\*\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*\*(?!\*)()'.TEXY_LINK.'??()#U','phraseStrongEm');$tx->registerLinePattern($this,'processPhrase','#(?<!\*)\*\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*\*(?!\*)'.TEXY_LINK.'??()#U','phraseStrong');$tx->registerLinePattern($this,'processPhrase','#(?<!\*)\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*(?!\*)'.TEXY_LINK.'??()#U','phraseEm');$tx->registerLinePattern($this,'processPhrase','#(?<!\+)\+\+(?!\ |\+)(.+)'.TEXY_MODIFIER.'?(?<!\ |\+)\+\+(?!\+)()#U','phraseIns');$tx->registerLinePattern($this,'processPhrase','#(?<!\-)\-\-(?!\ |\-)(.+)'.TEXY_MODIFIER.'?(?<!\ |\-)\-\-(?!\-)()#U','phraseDel');$tx->registerLinePattern($this,'processPhrase','#(?<!\^)\^\^(?!\ |\^)(.+)'.TEXY_MODIFIER.'?(?<!\ |\^)\^\^(?!\^)()#U','phraseSup');$tx->registerLinePattern($this,'processPhrase','#(?<!\_)\_\_(?!\ |\_)(.+)'.TEXY_MODIFIER.'?(?<!\ |\_)\_\_(?!\_)()#U','phraseSub');$tx->registerLinePattern($this,'processPhrase','#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")'.TEXY_LINK.'??()#U','phraseSpan');$tx->registerLinePattern($this,'processPhrase','#(?<!\~)\~(?!\ )([^\~]+)'.TEXY_MODIFIER.'?(?<!\ )\~(?!\~)'.TEXY_LINK.'??()#U','phraseSpanAlt');$tx->registerLinePattern($this,'processPhrase','#(?<!\~)\~\~(?!\ |\~)(.+)'.TEXY_MODIFIER.'?(?<!\ |\~)\~\~(?!\~)'.TEXY_LINK.'??()#U','phraseCite');$tx->registerLinePattern($this,'processPhrase','#(?<!\>)\>\>(?!\ |\>)(.+)'.TEXY_MODIFIER.'?(?<!\ |\<)\<\<(?!\<)'.TEXY_LINK.'??()#U','phraseQuote');$tx->registerLinePattern($this,'processPhrase','#(?<!\")\"(?!\ )([^\"]+)'.TEXY_MODIFIER.'?(?<!\ )\"(?!\")\(\((.+)\)\)()#U','phraseAcronym');$tx->registerLinePattern($this,'processPhrase','#(?<!['.TEXY_CHAR.'])(['.TEXY_CHAR.']{2,})()()()\(\((.+)\)\)#Uu','phraseAcronymAlt');$tx->registerLinePattern($this,'processProtect','#\`\`(\S[^'.TEXY_MARK.']*)(?<!\ )\`\`()#U','phraseNoTexy');$tx->registerLinePattern($this,'processCode','#\`(\S[^'.TEXY_MARK.']*)'.TEXY_MODIFIER.'?(?<!\ )\`()#U','phraseCode');$tx->registerBlockPattern($this,'processBlock','#^`=(none|code|kbd|samp|var|span)$#mUi','phraseCodeSwitch');}public
function
processPhrase($parser,$matches,$name){list($match,$mContent,$mMod1,$mMod2,$mMod3,$mLink)=$matches;if($mContent==NULL){preg_match('#^(.)+(.+)'.TEXY_MODIFIER.'?\\1+()$#U',$match,$matches);list($match,,$mContent,$mMod1,$mMod2,$mMod3,$mLink)=$matches;}$tx=$this->texy;static$_tags=array('phraseStrongEm'=>'strong','phraseStrong'=>'strong','phraseEm'=>'em','phraseIns'=>'ins','phraseDel'=>'del','phraseSup'=>'sup','phraseSub'=>'sub','phraseSpan'=>'span','phraseSpanAlt'=>'span','phraseCite'=>'cite','phraseAcronym'=>'acronym','phraseAcronymAlt'=>'acronym','phraseQuote'=>'q','phraseCode'=>'code',);$tag=$_tags[$name];if(($tag==='span')&&$mLink)$tag=NULL;elseif(($tag==='span')&&!$mMod1&&!$mMod2&&!$mMod3)return
FALSE;$content=$mContent;if($name==='phraseStrongEm'){$content=TexyHtml::el('em')->addChild($content)->toTexy($tx);}$modifier=new
TexyModifier($tx);$modifier->setProperties($mMod1,$mMod2,$mMod3);if($tag==='acronym'||$tag==='abbr'){$modifier->title=$mLink;$mLink=NULL;}$el=$modifier->generate($tag);if($mLink&&$tag==='q'){$el->cite=$tx->quoteModule->citeLink($mLink)->asURL();$mLink=NULL;}$el->addChild($content);$content=$el->toTexy($tx);if($mLink){$el=$tx->linkModule->factory($mLink,$mMod1,$mMod2,$mMod3,$mContent);$el->addChild($content);$content=$el->toTexy($tx);}return$content;}public
function
processBlock($parser,$matches){list(,$mTag)=$matches;$mTag=strtolower($mTag);$this->codeTag=$mTag==='none'?'':$mTag;}public
function
processCode($parser,$matches){list(,$mContent,$mMod1,$mMod2,$mMod3)=$matches;if($mMod1||$mMod2||$mMod3){$mod=new
TexyModifier($this->texy);$mod->setProperties($mMod1,$mMod2,$mMod3);$el=$mod->generate($this->codeTag);}else{$el=TexyHtml::el($this->codeTag);}return$this->texy->mark($el->startTag().htmlSpecialChars($mContent,ENT_NOQUOTES).$el->endTag(),Texy::CONTENT_TEXTUAL);}public
function
processProtect($parser,$matches){list(,$mContent)=$matches;return$this->texy->mark(htmlSpecialChars($mContent,ENT_NOQUOTES),Texy::CONTENT_TEXTUAL);}} 

class
TexyQuoteModule
extends
TexyModule{protected$allow=array('blockQuote');public
function
init(){$this->texy->registerBlockPattern($this,'processBlock','#^(?:'.TEXY_MODIFIER_H.'\n)?\>(\ +|:)(\S.*)$#mU','blockQuote');}public
function
processBlock($parser,$matches){list(,$mMod1,$mMod2,$mMod3,$mMod4,$mSpaces,$mContent)=$matches;$tx=$this->texy;$el=new
TexyBlockElement($tx);if($mMod1||$mMod2||$mMod3||$mMod4){$mod=new
TexyModifier($tx);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$el->tags[0]=$mod->generate('blockquote');}else{$el->tags[0]=TexyHtml::el('blockquote');}$content='';$linkTarget='';$spaces='';do{if($mSpaces===':'){$el->tags[0]->cite=$tx->quoteModule->citeLink($mContent)->asURL();$content.="\n";}else{if($spaces==='')$spaces=max(1,strlen($mSpaces));$content.=$mContent."\n";}if(!$parser->receiveNext("#^>(?:|(\\ {1,$spaces}|:)(.*))()$#mA",$matches))break;list(,$mSpaces,$mContent)=$matches;}while(TRUE);$el->parse($content);$parser->element->children[]=$el;}public
function
citeLink($dest){$tx=$this->texy;if($dest{0}==='['){$dest=substr($dest,1,-1);$ref=$this->getReference($dest);if($ref)$link=new
TexyUrl($ref['URL'],$tx->linkModule->root,TexyUrl::DIRECT);else$link=new
TexyUrl($dest,$tx->linkModule->root,TexyUrl::REFERENCE);}else{$link=new
TexyUrl($dest,$tx->linkModule->root,TexyUrl::DIRECT);}if(is_callable(array($tx->handler,'cite')))$tx->handler->cite($tx,$link);return$link;}} 

class
TexyScriptModule
extends
TexyModule{protected$allow=array('script');public$handler;public
function
init(){$this->texy->registerLinePattern($this,'processLine','#\{\{([^'.TEXY_MARK.']+)\}\}()#U','script');}public
function
processLine($parser,$matches){list(,$mContent)=$matches;$identifier=trim($mContent);if($identifier===''||$this->handler===NULL)return
FALSE;$args=NULL;if(preg_match('#^([a-z_][a-z0-9_]*)\s*\(([^()]*)\)$#i',$identifier,$matches)){$identifier=$matches[1];$args=explode(',',$matches[2]);array_walk($args,'trim');}if(is_callable(array($this->handler,$identifier))){array_unshift($args,$this->texy);return
call_user_func_array(array($this->handler,$identifier),$args);}if(is_callable($this->handler))return
call_user_func_array($this->handler,array($this->texy,$identifier,$args));return
FALSE;}public
function
defaultHandler($texy,$identifier,$args){if($args)$identifier.='('.implode(',',$args).')';$el=TexyHtml::el('texy:script');$el->_empty=TRUE;$el->content=$identifier;return$el;}} 

class
TexySmiliesModule
extends
TexyModule{public$icons=array(':-)'=>'smile.gif',':-('=>'sad.gif',';-)'=>'wink.gif',':-D'=>'biggrin.gif','8-O'=>'eek.gif','8-)'=>'cool.gif',':-?'=>'confused.gif',':-x'=>'mad.gif',':-P'=>'razz.gif',':-|'=>'neutral.gif',);public$class;public$iconPrefix;public
function
init(){if(empty($this->texy->allowed['smilies']))return;krsort($this->icons);$pattern=array();foreach($this->icons
as$key=>$foo)$pattern[]=preg_quote($key,'#').'+';$RE='#(?<=^|[\\x00-\\x20])('.implode('|',$pattern).')#';$this->texy->registerLinePattern($this,'processLine',$RE,'smilies');}public
function
processLine($parser,$matches){$match=$matches[0];$tx=$this->texy;foreach($this->icons
as$key=>$value){if(substr($match,0,strlen($key))===$key){$mod=new
TexyModifier($tx);$mod->title=$match;$mod->classes[]=$this->class;$el=$tx->imageModule->factoryEl($this->iconPrefix.$value,NULL,NULL,NULL,$mod,NULL);return$el;}}}} 

class
TexyTableModule
extends
TexyModule{protected$allow=array('table');public$oddClass;public$evenClass;private$isHead;private$colModifier;private$last;private$row;public
function
init(){$this->texy->registerBlockPattern($this,'processBlock','#^(?:'.TEXY_MODIFIER_HV.'\n)?'.'\|.*()$#mU','table');}public
function
processBlock($parser,$matches){list(,$mMod1,$mMod2,$mMod3,$mMod4,$mMod5)=$matches;$tx=$this->texy;$el=new
TexyBlockElement($tx);if($mMod1||$mMod2||$mMod3||$mMod4||$mMod5){$mod=new
TexyModifier($tx);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4,$mMod5);$el->tags[0]=$mod->generate('table');}else{$el->tags[0]=TexyHtml::el('table');}$parser->moveBackward();if($parser->receiveNext('#^\|(\#|\=){2,}(?!\\1)(.*)\\1*\|? *'.TEXY_MODIFIER_H.'?()$#Um',$matches)){list(,,$mContent,$mMod1,$mMod2,$mMod3,$mMod4)=$matches;$caption=new
TexyTextualElement($tx);if($mMod1||$mMod2||$mMod3||$mMod4){$mod=new
TexyModifier($tx);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4);$caption->tags[0]=$mod->generate('caption');}else{$caption->tags[0]=TexyHtml::el('caption');}$caption->parse($mContent);$el->children[]=$caption;}$this->isHead=FALSE;$this->colModifier=array();$this->last=array();$this->row=0;while(TRUE){if($parser->receiveNext('#^\|\-{3,}$#Um',$matches)){$this->isHead=!$this->isHead;continue;}if($elRow=$this->processRow($parser)){$el->children[]=$elRow;$this->row++;continue;}break;}$parser->element->children[]=$el;}protected
function
processRow($parser){$tx=$this->texy;if(!$parser->receiveNext('#^\|(.*)(?:|\|\ *'.TEXY_MODIFIER_HV.'?)()$#U',$matches)){return
FALSE;}list(,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mMod5)=$matches;$elRow=new
TexyBlockElement($tx);if($mMod1||$mMod2||$mMod3||$mMod4||$mMod5){$mod=new
TexyModifier($tx);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4,$mMod5);$elRow->tags[0]=$mod->generate('tr');}else{$elRow->tags[0]=TexyHtml::el('tr');}if($this->row
%
2===0){if($this->oddClass)$elRow->tags[0]->class[]=$this->oddClass;}else{if($this->evenClass)$elRow->tags[0]->class[]=$this->evenClass;}$col=0;$elField=NULL;foreach(explode('|',$mContent)as$field){if(($field=='')&&$elField){$elField->colSpan++;unset($this->last[$col]);$col++;continue;}$field=rtrim($field);if($field==='^'){if(isset($this->last[$col])){$this->last[$col]->rowSpan++;$col+=$this->last[$col]->colSpan;continue;}}if(!preg_match('#(\*??)\ *'.TEXY_MODIFIER_HV.'??(.*)'.TEXY_MODIFIER_HV.'?()$#AU',$field,$matches))continue;list(,$mHead,$mModCol1,$mModCol2,$mModCol3,$mModCol4,$mModCol5,$mContent,$mMod1,$mMod2,$mMod3,$mMod4,$mMod5)=$matches;if($mModCol1||$mModCol2||$mModCol3||$mModCol4||$mModCol5){$this->colModifier[$col]=new
TexyModifier($tx);$this->colModifier[$col]->setProperties($mModCol1,$mModCol2,$mModCol3,$mModCol4,$mModCol5);}$elField=new
TexyTableFieldElement($tx);if(isset($this->colModifier[$col]))$mod=clone$this->colModifier[$col];else$mod=new
TexyModifier($tx);$mod->setProperties($mMod1,$mMod2,$mMod3,$mMod4,$mMod5);$elField->tags[0]=$mod->generate(($this->isHead||($mHead==='*'))?'th':'td');$elField->parse($mContent);if($elField->content=='')$elField->content="\xC2\xA0";$elRow->children[]=$elField;$this->last[$col]=$elField;$col++;}return$elRow;}}class
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
init(){$pairs=array('#(?<!"|\w)"(?!\ |")(.+)(?<!\ |")"(?!")()#U'=>$this->doubleQuotes[0].'$1'.$this->doubleQuotes[1],'#(?<!\'|\w)\'(?!\ |\')(.+)(?<!\ |\')\'(?!\')()#Uu'=>$this->singleQuotes[0].'$1'.$this->singleQuotes[1],'#(\S|^) ?\.{3}#m'=>"\$1\xe2\x80\xa6",'#(\d| )-(\d| )#'=>"\$1\xe2\x80\x93\$2",'#,-#'=>",\xe2\x80\x93",'#(?<!\d)(\d{1,2}\.) (\d{1,2}\.) (\d\d)#'=>"\$1\xc2\xa0\$2\xc2\xa0\$3",'#(?<!\d)(\d{1,2}\.) (\d{1,2}\.)#'=>"\$1\xc2\xa0\$2",'# --- #'=>" \xe2\x80\x94 ",'# -- #'=>" \xe2\x80\x93 ",'# -> #'=>" \xe2\x86\x92 ",'# <- #'=>" \xe2\x86\x90 ",'# <-> #'=>" \xe2\x86\x94 ",'#(\d+)( ?)x\\2(\d+)\\2x\\2(\d+)#'=>"\$1\xc3\x97\$3\xc3\x97\$4",'#(\d+)( ?)x\\2(\d+)#'=>"\$1\xc3\x97\$3",'#(?<=\d)x(?= |,|.|$)#m'=>"\xc3\x97",'#(\S ?)\(TM\)#i'=>"\$1\xe2\x84\xa2",'#(\S ?)\(R\)#i'=>"\$1\xc2\xae",'#\(C\)( ?\S)#i'=>"\xc2\xa9\$1",'#(\d{1,3}) (\d{3}) (\d{3}) (\d{3})#'=>"\$1\xc2\xa0\$2\xc2\xa0\$3\xc2\xa0\$4",'#(\d{1,3}) (\d{3}) (\d{3})#'=>"\$1\xc2\xa0\$2\xc2\xa0\$3",'#(\d{1,3}) (\d{3})#'=>"\$1\xc2\xa0\$2",'#(?<=^| |\.|,|-|\+)(\d+)(['.TEXY_MARK_N.']*) (['.TEXY_MARK_N.']*)(['.TEXY_CHAR.'])#mu'=>"\$1\$2\xc2\xa0\$3\$4",'#(?<=^|[^0-9'.TEXY_CHAR.'])(['.TEXY_MARK_N.']*)([ksvzouiKSVZOUIA])(['.TEXY_MARK_N.']*) (['.TEXY_MARK_N.']*)([0-9'.TEXY_CHAR.'])#mu'=>"\$1\$2\$3\xc2\xa0\$4\$5",);$this->pattern=array_keys($pairs);$this->replace=array_values($pairs);}public
function
linePostProcess($text){if(empty($this->texy->allowed['typography']))return$text;return
preg_replace($this->pattern,$this->replace,$text);}} 
class
Texy{const
ALL=TRUE;const
NONE=FALSE;const
CONTENT_NONE=1;const
CONTENT_INLINE=2;const
CONTENT_TEXTUAL=3;const
CONTENT_BLOCK=4;static
public$xhtml=TRUE;static
public$obfuscateEmail=TRUE;public$encoding='utf-8';public$allowed=array();public$allowedTags;public$allowedClasses=Texy::ALL;public$allowedStyles=Texy::ALL;public$tabWidth=8;public$summary=array('images'=>array(),'links'=>array(),'preload'=>array(),);public$styleSheet='';public$mergeLines=TRUE;public$handler;public$scriptModule,$htmlModule,$imageModule,$linkModule,$phraseModule,$smiliesModule,$blockModule,$headingModule,$horizLineModule,$quoteModule,$listModule,$definitionListModule,$tableModule,$imageDescModule,$typographyModule,$longWordsModule;public$genericBlock,$formatter,$formatterModule,$wellForm;static
public$blockTags=array('address'=>1,'blockquote'=>1,'caption'=>1,'col'=>1,'colgroup'=>1,'dd'=>1,'div'=>1,'dl'=>1,'dt'=>1,'fieldset'=>1,'form'=>1,'h1'=>1,'h2'=>1,'h3'=>1,'h4'=>1,'h5'=>1,'h6'=>1,'hr'=>1,'iframe'=>1,'legend'=>1,'li'=>1,'object'=>1,'ol'=>1,'p'=>1,'param'=>1,'pre'=>1,'table'=>1,'tbody'=>1,'td'=>1,'tfoot'=>1,'th'=>1,'thead'=>1,'tr'=>1,'ul'=>1,);static
public$inlineTags=array('a'=>1,'abbr'=>1,'acronym'=>1,'area'=>1,'b'=>1,'big'=>1,'br'=>1,'button'=>1,'cite'=>1,'code'=>1,'del'=>1,'dfn'=>1,'em'=>1,'i'=>1,'img'=>1,'input'=>1,'ins'=>1,'kbd'=>1,'label'=>1,'map'=>1,'noscript'=>1,'optgroup'=>1,'option'=>1,'q'=>1,'samp'=>1,'script'=>1,'select'=>1,'small'=>1,'span'=>1,'strong'=>1,'sub'=>1,'sup'=>1,'textarea'=>1,'tt'=>1,'var'=>1,);static
public$inlineCont=array('br'=>1,'button'=>1,'iframe'=>1,'img'=>1,'input'=>1,'object'=>1,'script'=>1,'select'=>1,'textarea'=>1,'applet'=>1,'isindex'=>1,);static
public$emptyTags=array('img'=>1,'hr'=>1,'br'=>1,'input'=>1,'meta'=>1,'area'=>1,'base'=>1,'col'=>1,'link'=>1,'param'=>1,);static
public$tagAttrs=array('abbr'=>1,'accesskey'=>1,'align'=>1,'alt'=>1,'archive'=>1,'axis'=>1,'bgcolor'=>1,'cellpadding'=>1,'cellspacing'=>1,'char'=>1,'charoff'=>1,'charset'=>1,'cite'=>1,'classid'=>1,'codebase'=>1,'codetype'=>1,'colspan'=>1,'compact'=>1,'coords'=>1,'data'=>1,'datetime'=>1,'declare'=>1,'dir'=>1,'face'=>1,'frame'=>1,'headers'=>1,'href'=>1,'hreflang'=>1,'hspace'=>1,'ismap'=>1,'lang'=>1,'longdesc'=>1,'name'=>1,'noshade'=>1,'nowrap'=>1,'onblur'=>1,'onclick'=>1,'ondblclick'=>1,'onkeydown'=>1,'onkeypress'=>1,'onkeyup'=>1,'onmousedown'=>1,'onmousemove'=>1,'onmouseout'=>1,'onmouseover'=>1,'onmouseup'=>1,'rel'=>1,'rev'=>1,'rowspan'=>1,'rules'=>1,'scope'=>1,'shape'=>1,'size'=>1,'span'=>1,'src'=>1,'standby'=>1,'start'=>1,'summary'=>1,'tabindex'=>1,'target'=>1,'title'=>1,'type'=>1,'usemap'=>1,'valign'=>1,'value'=>1,'vspace'=>1,);static
public$validTags;private$linePatterns=array();private$blockPatterns=array();private$DOM;private$modules,$lineModules;private$marks=array();public$_mergeMode;public$_classes,$_styles;public
function
__construct(){self::$validTags=array_merge(Texy::$blockTags,Texy::$inlineTags);$this->allowedTags=self::$validTags;$this->loadModules();$this->formatter=new
TexyHtmlFormatter();$this->wellForm=new
TexyHtmlWellForm();$this->genericBlock=new
TexyGenericBlock($this);$this->formatterModule=$this->formatter;$mod=new
TexyModifier($this);$mod->title='The best text -> HTML converter and formatter';$this->linkModule->addReference('texy','http://texy.info/','Texy!',$mod);$this->linkModule->addReference('google','http://www.google.com/search?q=%s');$this->linkModule->addReference('wikipedia','http://en.wikipedia.org/wiki/Special:Search?search=%s');}protected
function
loadModules(){$this->scriptModule=new
TexyScriptModule($this);$this->htmlModule=new
TexyHtmlModule($this);$this->imageModule=new
TexyImageModule($this);$this->linkModule=new
TexyLinkModule($this);$this->phraseModule=new
TexyPhraseModule($this);$this->smiliesModule=new
TexySmiliesModule($this);$this->blockModule=new
TexyBlockModule($this);$this->headingModule=new
TexyHeadingModule($this);$this->horizLineModule=new
TexyHorizLineModule($this);$this->quoteModule=new
TexyQuoteModule($this);$this->listModule=new
TexyListModule($this);$this->definitionListModule=new
TexyDefinitionListModule($this);$this->tableModule=new
TexyTableModule($this);$this->imageDescModule=new
TexyImageDescModule($this);$this->typographyModule=new
TexyTypographyModule($this);$this->longWordsModule=new
TexyLongWordsModule($this);}public
function
registerModule($module){$this->modules[]=$module;if($module
instanceof
ITexyLineModule)$this->lineModules[]=$module;}public
function
registerLinePattern($module,$method,$pattern,$name){if(empty($this->allowed[$name]))return;$this->linePatterns[]=array('handler'=>array($module,$method),'pattern'=>$pattern,'name'=>$name);}public
function
registerBlockPattern($module,$method,$pattern,$name){if(empty($this->allowed[$name]))return;$this->blockPatterns[]=array('handler'=>array($module,$method),'pattern'=>$pattern.'m','name'=>$name);}protected
function
init(){if($this->handler&&!is_object($this->handler))throw
new
Exception('$texy->handler must be object. See documentation.');$this->_mergeMode=TRUE;$this->marks=array();if(is_array($this->allowedClasses))$this->_classes=array_flip($this->allowedClasses);else$this->_classes=$this->allowedClasses;if(is_array($this->allowedStyles))$this->_styles=array_flip($this->allowedStyles);else$this->_styles=$this->allowedStyles;$this->linePatterns=array();$this->blockPatterns=array();foreach($this->modules
as$module)$module->init();}public
function
process($text,$singleLine=FALSE){if($singleLine)$this->parseLine($text);else$this->parse($text);return$this->toHtml();}public
function
parse($text){$this->init();if(strcasecmp($this->encoding,'utf-8')!==0)$text=iconv($this->encoding,'utf-8',$text);$text=Texy::wash($text);$text=str_replace("\r\n","\n",$text);$text=strtr($text,"\r","\n");while(strpos($text,"\t")!==FALSE)$text=preg_replace_callback('#^(.*)\t#mU',create_function('$matches',"return \$matches[1] . str_repeat(' ', $this->tabWidth - strlen(\$matches[1]) % $this->tabWidth);"),$text);$text=preg_replace('#\xC2\xA7{2,}(?!\xC2\xA7).*(\xC2\xA7{2,}|$)(?!\xC2\xA7)#mU','',$text);$text=preg_replace("#[\t ]+$#m",'',$text);foreach($this->modules
as$module)$text=$module->preProcess($text);$this->DOM=new
TexyBlockElement($this);$this->DOM->parse($text);}public
function
parseLine($text){$this->init();if(strcasecmp($this->encoding,'utf-8')!==0)$text=iconv($this->encoding,'utf-8',$text);$text=Texy::wash($text);$text=rtrim(strtr($text,array("\n"=>' ',"\r"=>'')));$this->DOM=new
TexyTextualElement($this);$this->DOM->parse($text);}public
function
toHtml(){if(!$this->DOM)throw
new
Exception('Call $texy->parse() first.');$html=$this->DOM->toHtml();$html=$this->unMarks($html);$html=$this->wellForm->process($html);$html=$this->formatter->process($html);if(!defined('TEXY_NOTICE_SHOWED')){$html.="\n<!-- by Texy2! -->";define('TEXY_NOTICE_SHOWED',TRUE);}$html=Texy::unfreezeSpaces($html);if(strcasecmp($this->encoding,'utf-8')!==0){$this->_chars=&self::$charTables[strtolower($this->encoding)];if(!$this->_chars){for($i=128;$i<256;$i++){$ch=iconv($this->encoding,'UTF-8//IGNORE',chr($i));if($ch)$this->_chars[$ch]=chr($i);}}$html=preg_replace_callback('#[\x80-\x{FFFF}]#u',array($this,'utfconv'),$html);}return$html;}public
function
toText(){if(!$this->DOM)throw
new
Exception('Call $texy->parse() first.');$html=$this->DOM->toHtml();$html=$this->unMarks($html);$html=$this->wellForm->process($html);$saveLineWrap=$this->formatter->lineWrap;$this->formatter->lineWrap=FALSE;$html=$this->formatter->process($html);$this->formatter->lineWrap=$saveLineWrap;$html=Texy::unfreezeSpaces($html);$html=preg_replace('#<(script|style)(.*)</\\1>#Uis','',$html);$html=strip_tags($html);$html=preg_replace('#\n\s*\n\s*\n[\n\s]*\n#',"\n\n",$html);$html=html_entity_decode($html,ENT_QUOTES,'UTF-8');$html=strtr($html,array("\xC2\xAD"=>'',"\xC2\xA0"=>' ',));if(strcasecmp($this->encoding,'utf-8')!==0)$html=iconv('utf-8',$this->encoding.'//TRANSLIT',$html);return$html;}public
function
safeMode(){$this->allowedClasses=Texy::NONE;$this->allowedStyles=Texy::NONE;$this->htmlModule->safeMode();$this->allowed['image']=FALSE;$this->allowed['linkDefinition']=FALSE;$this->linkModule->forceNoFollow=TRUE;}public
function
trustMode(){$this->allowedClasses=Texy::ALL;$this->allowedStyles=Texy::ALL;$this->htmlModule->trustMode();$this->allowed['image']=TRUE;$this->allowed['linkDefinition']=TRUE;$this->linkModule->forceNoFollow=FALSE;$this->mergeLines=TRUE;}static
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
wash($text){return
preg_replace('#[\x01-\x04\x14-\x1F]+#','',$text);}public
function
mark($child,$contentType){if($child==='')return'';static$borders=array(Texy::CONTENT_NONE=>"\x14",Texy::CONTENT_INLINE=>"\x15",Texy::CONTENT_TEXTUAL=>"\x16",Texy::CONTENT_BLOCK=>"\x17",);$key=$borders[$contentType].strtr(base_convert(count($this->marks),10,8),'01234567',"\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F").$borders[$contentType];$this->marks[$key]=$child;return$key;}public
function
unMarks($html){return
strtr($html,$this->marks);}public
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
__set($nm,$val){$this->__get($nm);}private
function
__unset($nm){$this->__get($nm);}private
function
__isset($nm){$this->__get($nm);}}?>