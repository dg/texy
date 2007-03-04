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
 * @version    2.0beta for PHP5 $Revision: 83 $ $Date: 2007-03-04 07:03:30 +0100 (ne, 04 III 2007) $
 */if(version_compare(PHP_VERSION,'5.0.0','<'))die('Texy! needs PHP version 5');define('TEXY','Version 2.0beta $Revision: 83 $');define('TEXY_DIR',dirname(__FILE__).'/');

define('TEXY_CHAR','A-Za-z\x{c0}-\x{02af}\x{0370}-\x{1eff}');define('TEXY_MARK',"\x14-\x1F");define('TEXY_MODIFIER','(?:\ *?(?<= |^)\\.((?:\\([^\\)]+\\)|\\[[^\\]]+\\]|\\{[^\\}]+\\}){1,3}?))');define('TEXY_MODIFIER_H','(?:\ *?(?<= |^)\\.((?:\\([^\\)]+\\)|\\[[^\\]]+\\]|\\{[^\\}]+\\}|<>|>|=|<){1,4}?))');define('TEXY_MODIFIER_HV','(?:\ *?(?<= |^)\\.((?:\\([^\\)]+\\)|\\[[^\\]]+\\]|\\{[^\\}]+\\}|<>|>|=|<|\\^|\\-|\\_){1,5}?))');define('TEXY_IMAGE','\[\*([^\n'.TEXY_MARK.']+)'.TEXY_MODIFIER.'? *(\*|>|<)\]');define('TEXY_LINK_REF','\[[^\[\]\*\n'.TEXY_MARK.']+\]');define('TEXY_LINK_URL','(?:\[[^\]\n]+\]|(?!\[)[^\s'.TEXY_MARK.']*?[^:);,.!?\s'.TEXY_MARK.'])');define('TEXY_LINK','(?::('.TEXY_LINK_URL.'))');define('TEXY_LINK_N','(?::('.TEXY_LINK_URL.'|:))');define('TEXY_EMAIL','[a-z0-9.+_-]+@[a-z0-9.+_-]+\.[a-z]{2,}'); 

class
TexyHtml{public$elName;public$childNodes=array();public$eXtra;static
public
function
el($name=NULL){$el=new
self();$el->elName=$name;if(isset(Texy::$emptyTags[$name]))$el->childNodes=FALSE;return$el;}public
function
setElement($name){$this->elName=$name;if(isset(Texy::$emptyTags[$name]))$el->childNodes=FALSE;return$this;}public
function
setAttrs($attrs){foreach($attrs
as$key=>$value)$el->$key=$value;return$this;}public
function
setContent($content){$this->childNodes=array($content);return$this;}public
function
getContent(){if(isset($this->childNodes[0]))return$this->childNodes[0];return
NULL;}public
function
__call($m,$args){$this->$m=$args[0];return$this;}public
function
export($texy){$ct=$this->getContentType();$s=$texy->protect($this->startTag(),$ct);if($this->childNodes===FALSE)return$s;if(!is_array($this->childNodes))throw
new
Exception('TexyHtml::childNodes bad usage.');foreach($this->childNodes
as$val)if($val
instanceof
self)$s.=$val->export($texy);else$s.=$val;$s.=$texy->protect($this->endTag(),$ct);return$s;}public
function
startTag(){if(!$this->elName)return'';$s='<'.$this->elName;$attrs=(array)$this;unset($attrs['elName'],$attrs['childNodes'],$attrs['eXtra']);foreach($attrs
as$key=>$value){if($value===NULL||$value===FALSE)continue;if($value===TRUE){if(Texy::$xhtml)$s.=' '.$key.'="'.$key.'"';else$s.=' '.$key;continue;}elseif(is_array($value)){$tmp=NULL;foreach($value
as$k=>$v){if($v==NULL)continue;if(is_string($k))$tmp[]=$k.':'.$v;else$tmp[]=$v;}if(!$tmp)continue;$value=implode($key==='style'?';':' ',$tmp);}$value=str_replace(array('&','"','<','>','@'),array('&amp;','&quot;','&lt;','&gt;','&#64;'),$value);$s.=' '.$key.'="'.Texy::freezeSpaces($value).'"';}if(Texy::$xhtml&&$this->childNodes===FALSE)return$s.' />';return$s.'>';}public
function
endTag(){if($this->elName&&$this->childNodes!==FALSE)return'</'.$this->elName.'>';return'';}public
function
getContentType(){if(isset(Texy::$inlineCont[$this->elName]))return
Texy::CONTENT_INLINE;if(isset(Texy::$inlineTags[$this->elName]))return
Texy::CONTENT_NONE;return
Texy::CONTENT_BLOCK;}public
function
parseLine($texy,$s){$parser=new
TexyLineParser($texy);$this->childNodes[]=$parser->parse($s);}public
function
parseBlock($texy,$s){$parser=new
TexyBlockParser($texy);$this->childNodes=$parser->parse($s);}public
function
parseDocument($texy,$s){$parser=new
TexyDocumentParser($texy);$this->childNodes=$parser->parse($s);}} 

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
VALIGN_BOTTOM='bottom';public$id;public$classes=array();public$styles=array();public$attrs=array();public$hAlign;public$vAlign;public$title;public$cite;static
public$elAttrs=array('abbr'=>1,'accesskey'=>1,'align'=>1,'alt'=>1,'archive'=>1,'axis'=>1,'bgcolor'=>1,'cellpadding'=>1,'cellspacing'=>1,'char'=>1,'charoff'=>1,'charset'=>1,'cite'=>1,'classid'=>1,'codebase'=>1,'codetype'=>1,'colspan'=>1,'compact'=>1,'coords'=>1,'data'=>1,'datetime'=>1,'declare'=>1,'dir'=>1,'face'=>1,'frame'=>1,'headers'=>1,'href'=>1,'hreflang'=>1,'hspace'=>1,'ismap'=>1,'lang'=>1,'longdesc'=>1,'name'=>1,'noshade'=>1,'nowrap'=>1,'onblur'=>1,'onclick'=>1,'ondblclick'=>1,'onkeydown'=>1,'onkeypress'=>1,'onkeyup'=>1,'onmousedown'=>1,'onmousemove'=>1,'onmouseout'=>1,'onmouseover'=>1,'onmouseup'=>1,'rel'=>1,'rev'=>1,'rowspan'=>1,'rules'=>1,'scope'=>1,'shape'=>1,'size'=>1,'span'=>1,'src'=>1,'standby'=>1,'start'=>1,'summary'=>1,'tabindex'=>1,'target'=>1,'title'=>1,'type'=>1,'usemap'=>1,'valign'=>1,'value'=>1,'vspace'=>1,);public
function
__construct($mod=NULL){$this->setProperties($mod);}public
function
setProperties($mod){if(!$mod)return;$mod=strtr($mod,"\n",' ');$p=0;$len=strlen($mod);while($p<$len){$ch=$mod[$p];if($ch==='('){$a=strpos($mod,')',$p)+1;$this->title=Texy::decode(trim(substr($mod,$p+1,$a-$p-2)));$p=$a;}elseif($ch==='{'){$a=strpos($mod,'}',$p)+1;foreach(explode(';',substr($mod,$p+1,$a-$p-2))as$value){$pair=explode(':',$value,2);$prop=strtolower(trim($pair[0]));if($prop===''||!isset($pair[1]))continue;$value=trim($pair[1]);if(isset(self::$elAttrs[$prop]))$this->attrs[$prop]=$value;elseif($value!=='')$this->styles[$prop]=$value;}$p=$a;}elseif($ch==='['){$a=strpos($mod,']',$p)+1;$s=str_replace('#',' #',substr($mod,$p+1,$a-$p-2));foreach(explode(' ',$s)as$value){if($value==='')continue;if($value{0}==='#')$this->id=substr($value,1);else$this->classes[]=$value;}$p=$a;}elseif($ch==='^'){$this->vAlign=self::VALIGN_TOP;$p++;}elseif($ch==='-'){$this->vAlign=self::VALIGN_MIDDLE;$p++;}elseif($ch==='_'){$this->vAlign=self::VALIGN_BOTTOM;$p++;}elseif($ch==='='){$this->hAlign=self::HALIGN_JUSTIFY;$p++;}elseif($ch==='>'){$this->hAlign=self::HALIGN_RIGHT;$p++;}elseif(substr($mod,$p,2)==='<>'){$this->hAlign=self::HALIGN_CENTER;$p+=2;}elseif($ch==='<'){$this->hAlign=self::HALIGN_LEFT;$p++;}else{break;}}}public
function
decorate($texy,$el){$tmp=$texy->allowedTags;if(!$this->attrs){}elseif($tmp===Texy::ALL){$el->setAttrs($this->attrs);}elseif(is_array($tmp)&&isset($tmp[$el->elName])){$tmp=$tmp[$el->elName];if($tmp===Texy::ALL){$el->setAttrs($this->attrs);}else{if(is_array($tmp)&&count($tmp)){$tmp=array_flip($tmp);foreach($this->attrs
as$key=>$val)if(isset($tmp[$key]))$el->$key=$val;}}}$el->href=$el->src=NULL;$el->title=$this->title;if($this->classes||$this->id!==NULL){$tmp=$texy->_classes;if($tmp===Texy::ALL){foreach($this->classes
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
TexyModule{protected$texy;protected$default=array();public
function
__construct($texy){$this->texy=$texy;$texy->registerModule($this);$texy->allowed=array_merge($texy->allowed,$this->default);}public
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
TexyParser{protected$texy;public
function
__construct($texy){$this->texy=$texy;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}}class
TexyDocumentParser
extends
TexyParser{public$defaultType='pre';public
function
parse($text){$tx=$this->texy;$dt=$tx->getDocTypes();preg_match_all('#^(?>([/\\\\])--+?)([^.({[<>]*)'.TEXY_MODIFIER_H.'?$#mU',$text,$matches,PREG_OFFSET_CAPTURE|PREG_SET_ORDER);$i=0;$docType=NULL;$docStack=array();$nodes=array();$param=NULL;$mod=new
TexyModifier;$offset=0;do{if(!$docType){$docType=$tx->defaultDocument;$flag='[';}if(isset($matches[$i])){$end=$matches[$i][0][1]-1;$flag.=$matches[$i][1][0]==='/'?'-':'>';}else{$end=strlen($text);$flag.=']';}if($end>$offset){$s=substr($text,$offset,$end-$offset);if(isset($dt[$docType])){$res=call_user_func_array($dt[$docType],array($this,$s,$docType,$param,$mod,$flag));if($res!=NULL)$nodes[]=$res;}else{$el=TexyHtml::el();$el->parseBlock($tx,$s);$nodes[]=$el;}}if($i==count($matches))break;$match=$matches[$i];$i++;$offset=$match[0][1]+strlen($match[0][0])+1;if($match[1][0]==='/'){$docStack[]=$docType;$words=preg_split('# +#',$match[2][0],2,PREG_SPLIT_NO_EMPTY);if(!isset($words[0]))$words[0]=$this->defaultType;$docType='document/'.$words[0];$param=isset($words[1])?$words[1]:NULL;$mod=isset($match[3])?new
TexyModifier($match[3][0]):new
TexyModifier;$flag='<';}else{$param=NULL;$mod=new
TexyModifier;if($match[2][0]!=NULL){$words=preg_split('# +#',$match[2][0],1,PREG_SPLIT_NO_EMPTY);$toClose='document/'.$words[0];while($docType&&$toClose!==$docType){$docType=array_pop($docStack);}}$docType=array_pop($docStack);$flag='-';}}while(1);return$nodes;}}class
TexyBlockParser
extends
TexyParser{private$text;private$offset;public
function
receiveNext($pattern,&$matches){$matches=NULL;$ok=preg_match($pattern.'Am',$this->text,$matches,PREG_OFFSET_CAPTURE,$this->offset);if($ok){$this->offset+=strlen($matches[0][0])+1;foreach($matches
as$key=>$value)$matches[$key]=$value[0];}return$ok;}public
function
moveBackward($linesCount=1){while(--$this->offset>0)if($this->text{$this->offset-1}==="\n")if(--$linesCount<1)break;$this->offset=max($this->offset,0);}private
function
genericBlock($content){$tx=$this->texy;preg_match('#^(.*)'.TEXY_MODIFIER_H.'?(\n.*)?()$#sU',$content,$matches);list(,$mContent,$mMod,$mContent2)=$matches;$mContent=trim($mContent.$mContent2);if($tx->mergeLines){$mContent=preg_replace('#\n (?=\S)#',"\r",$mContent);}$lineParser=new
TexyLineParser($tx);$content=$lineParser->parse($mContent);$contentType=Texy::CONTENT_NONE;if(strpos($content,Texy::CONTENT_BLOCK)!==FALSE){$contentType=Texy::CONTENT_BLOCK;}elseif(strpos($content,Texy::CONTENT_TEXTUAL)!==FALSE){$contentType=Texy::CONTENT_TEXTUAL;}else{if(strpos($content,Texy::CONTENT_INLINE)!==FALSE)$contentType=Texy::CONTENT_INLINE;$s=trim(preg_replace('#['.TEXY_MARK.']+#','',$content));if(strlen($s))$contentType=Texy::CONTENT_TEXTUAL;}if($contentType===Texy::CONTENT_TEXTUAL)$tag='p';elseif($mMod)$tag='div';elseif($contentType===Texy::CONTENT_BLOCK)$tag='';else$tag='div';if($tag&&(strpos($content,"\r")!==FALSE)){$key=$tx->protect('<br />',Texy::CONTENT_INLINE);$content=str_replace("\r",$key,$content);};$content=strtr($content,"\r\n",'  ');$mod=new
TexyModifier($mMod);$el=TexyHtml::el($tag);$mod->decorate($tx,$el);$el->childNodes[]=$content;return$el;}public
function
parse($text){$tx=$this->texy;$this->text=$text;$this->offset=0;$nodes=array();$pb=$tx->getBlockPatterns();if(!$pb)return
array();$keys=array_keys($pb);$arrMatches=$arrPos=array();foreach($keys
as$key)$arrPos[$key]=-1;do{$minKey=NULL;$minPos=strlen($text);if($this->offset>=$minPos)break;foreach($keys
as$index=>$key){if($arrPos[$key]<$this->offset){$delta=($arrPos[$key]===-2)?1:0;if(preg_match($pb[$key]['pattern'],$text,$arrMatches[$key],PREG_OFFSET_CAPTURE,$this->offset+$delta)){$m=&$arrMatches[$key];$arrPos[$key]=$m[0][1];foreach($m
as$keyX=>$valueX)$m[$keyX]=$valueX[0];}else{unset($keys[$index]);continue;}}if($arrPos[$key]===$this->offset){$minKey=$key;break;}if($arrPos[$key]<$minPos){$minPos=$arrPos[$key];$minKey=$key;}}$next=($minKey===NULL)?strlen($text):$arrPos[$minKey];if($next>$this->offset){$str=substr($text,$this->offset,$next-$this->offset);$this->offset=$next;if($tx->_paragraphMode)$parts=preg_split('#(\n{2,})#',$str);else$parts=preg_split('#(\n(?! )|\n{2,})#',$str);foreach($parts
as$str){$str=trim($str);if($str==='')continue;$nodes[]=$this->genericBlock($str);}continue;}$px=$pb[$minKey];$matches=$arrMatches[$minKey];$this->offset=$arrPos[$minKey]+strlen($arrMatches[$minKey][0])+1;$res=call_user_func_array($px['handler'],array($this,$arrMatches[$minKey],$minKey));if($res===FALSE||$this->offset<=$arrPos[$minKey]){$this->offset=$arrPos[$minKey];$arrPos[$minKey]=-2;continue;}elseif($res!=NULL){$nodes[]=$res;}$arrPos[$minKey]=-1;}while(1);return$nodes;}}class
TexyLineParser
extends
TexyParser{public$again;public$onlyHtml;public
function
parse($text){$tx=$this->texy;if($this->onlyHtml){$tmp=$tx->getLinePatterns();if(isset($tmp['html/tag']))$pl['html/tag']=$tmp['html/tag'];if(isset($tmp['html/comment']))$pl['html/comment']=$tmp['html/comment'];unset($tmp);}else{$pl=$tx->getLinePatterns();$text=str_replace(array('\)','\*'),array('&#x29;','&#x2A;'),$text);}if(!$pl)return$text;$offset=0;$keys=array_keys($pl);$arrMatches=$arrPos=array();foreach($keys
as$key)$arrPos[$key]=-1;do{$minKey=NULL;$minPos=strlen($text);foreach($keys
as$index=>$key){if($arrPos[$key]<$offset){$delta=($arrPos[$key]===-2)?1:0;if(preg_match($pl[$key]['pattern'],$text,$arrMatches[$key],PREG_OFFSET_CAPTURE,$offset+$delta)){$m=&$arrMatches[$key];if(!strlen($m[0][0]))continue;$arrPos[$key]=$m[0][1];foreach($m
as$keyx=>$value)$m[$keyx]=$value[0];}else{unset($keys[$index]);continue;}}if($arrPos[$key]<$minPos){$minPos=$arrPos[$key];$minKey=$key;}}if($minKey===NULL)break;$px=$pl[$minKey];$offset=$start=$arrPos[$minKey];$this->again=FALSE;$res=call_user_func_array($px['handler'],array($this,$arrMatches[$minKey],$minKey));if($res
instanceof
TexyHtml){$res=$res->export($tx);}elseif($res===FALSE){$arrPos[$minKey]=-2;continue;}elseif(!is_string($res)){$res=(string)$res;}$len=strlen($arrMatches[$minKey][0]);$text=substr_replace($text,$res,$start,$len);$delta=strlen($res)-$len;foreach($keys
as$key){if($arrPos[$key]<$start+$len)$arrPos[$key]=-1;else$arrPos[$key]+=$delta;}if($this->again){$arrPos[$minKey]=-2;}else{$arrPos[$minKey]=-1;$offset+=strlen($res);}}while(1);return$text;}} 

class
TexyDocumentModule
extends
TexyModule{protected$default=array('document/pre'=>TRUE,'document/code'=>TRUE,'document/html'=>TRUE,'document/text'=>TRUE,'document/texysource'=>TRUE,'document/comment'=>TRUE,'document/div'=>TRUE,);public
function
init(){$tx=$this->texy;$tx->registerDocType(array($this,'pattern'),'document/pre');$tx->registerDocType(array($this,'pattern'),'document/code');$tx->registerDocType(array($this,'pattern'),'document/html');$tx->registerDocType(array($this,'pattern'),'document/text');$tx->registerDocType(array($this,'pattern'),'document/texysource');$tx->registerDocType(array($this,'pattern'),'document/comment');$tx->registerDocType(array($this,'patternDiv'),'document/div');}public
function
patternDiv($parser,$s,$doctype,$param,$mod,$flag){$tx=$this->texy;$el=TexyHtml::el();$s=$this->outdent($s);$el->parseDocument($tx,$s);if($flag[0]==='<'){$elX=TexyHtml::el('div');$mod->decorate($tx,$elX);array_unshift($el->childNodes,$tx->protect($elX->startTag()));}if($flag[1]==='>'){$el->childNodes[]=$tx->protect('</div>');}return$el;}public
function
pattern($parser,$s,$doctype,$param,$mod,$flag){$method=str_replace('/','',$doctype);if(is_callable(array($this->texy->handler,$method))){$res=$this->texy->handler->$method($this->texy,$s,$param,$mod,$doctype);if($res!==NULL)return$res;}return$this->factory($s,$doctype,$param,$mod);}public
function
outdent($s){$s=trim($s,"\n");$spaces=strspn($s,' ');if($spaces)return
preg_replace("#^ {1,$spaces}#m",'',$s);return$s;}public
function
factory($s,$doctype,$param=NULL,$mod=NULL){$tx=$this->texy;if($doctype==='document/texysource'){$s=$this->outdent($s);$el=TexyHtml::el();$el->parseBlock($tx,$s);$s=$tx->export($el);$doctype='document/code';$param='html';}if($doctype==='document/code'){$el=TexyHtml::el('pre');$mod->decorate($tx,$el);$el->class[]=$param;$el->childNodes[0]=TexyHtml::el('code');$s=$this->outdent($s);$s=Texy::encode($s);$s=$tx->protect($s);$el->childNodes[0]->setContent($s);return$el;}if($doctype==='document/pre'){$el=TexyHtml::el('pre');$mod->decorate($tx,$el);$el->class[]=$param;$s=$this->outdent($s);$s=Texy::encode($s);$s=$tx->protect($s);$el->setContent($s);return$el;}if($doctype==='document/html'){$lineParser=new
TexyLineParser($tx);$lineParser->onlyHtml=TRUE;$s=trim($s,"\n");$s=$lineParser->parse($s);$s=Texy::decode($s);$s=Texy::encode($s);$s=$tx->unprotect($s);return$tx->protect($s)."\n";}if($doctype==='document/text'){$s=trim($s,"\n");$s=Texy::encode($s);$s=str_replace("\n",TexyHtml::el('br')->startTag(),$s);return$tx->protect($s)."\n";}if($doctype==='document/comment'){return"\n";}}} 

class
TexyHeadingModule
extends
TexyModule{const
DYNAMIC=1,FIXED=2;protected$default=array('heading/surrounded'=>TRUE,'heading/underlined'=>TRUE);public$title;public$TOC;public$generateID=FALSE;public$idPrefix='toc-';public$top=1;public$balancing=TexyHeadingModule::DYNAMIC;public$levels=array('#'=>0,'*'=>1,'='=>2,'-'=>3,);private$usedID;private$_rangeUnderline;private$_deltaUnderline;private$_rangeSurround;private$_deltaSurround;public
function
init(){$this->texy->registerBlockPattern(array($this,'patternUnderline'),'#^(\S.*)'.TEXY_MODIFIER_H.'?\n'.'(\#|\*|\=|\-){3,}$#mU','heading/underlined');$this->texy->registerBlockPattern(array($this,'patternSurround'),'#^((\#|\=){2,})(?!\\2)(.+)\\2*'.TEXY_MODIFIER_H.'?()$#mU','heading/surrounded');$this->_rangeUnderline=array(10,0);$this->_rangeSurround=array(10,0);$this->title=NULL;$this->TOC=$this->usedID=array();$foo=NULL;$this->_deltaUnderline=&$foo;$bar=NULL;$this->_deltaSurround=&$bar;}public
function
patternUnderline($parser,$matches){list(,$mContent,$mMod,$mLine)=$matches;$mod=new
TexyModifier($mMod);$level=$this->levels[$mLine];$this->_rangeUnderline[0]=min($this->_rangeUnderline[0],$level);$this->_rangeUnderline[1]=max($this->_rangeUnderline[1],$level);$this->_deltaUnderline=-$this->_rangeUnderline[0];$this->_deltaSurround=-$this->_rangeSurround[0]+($this->_rangeUnderline[1]?($this->_rangeUnderline[1]-$this->_rangeUnderline[0]+1):0);if(is_callable(array($this->texy->handler,'heading'))){$res=$this->texy->handler->heading($this->texy,$level,$mContent,$mod,FALSE);if($res!==NULL)return$res;}return$this->factory($level,$mContent,$mod,FALSE);}public
function
patternSurround($parser,$matches){list(,$mLine,,$mContent,$mMod)=$matches;$mod=new
TexyModifier($mMod);$level=7-min(7,max(2,strlen($mLine)));$this->_rangeSurround[0]=min($this->_rangeSurround[0],$level);$this->_rangeSurround[1]=max($this->_rangeSurround[1],$level);$this->_deltaSurround=-$this->_rangeSurround[0]+($this->_rangeUnderline[1]?($this->_rangeUnderline[1]-$this->_rangeUnderline[0]+1):0);if(is_callable(array($this->texy->handler,'heading'))){$res=$this->texy->handler->heading($this->texy,$level,$mContent,$mod,TRUE);if($res!==NULL)return$res;}return$this->factory($level,$mContent,$mod,TRUE);}public
function
factory($level,$content,$mod,$surround){$tx=$this->texy;$el=new
TexyHeadingElement;$mod->decorate($tx,$el);$el->eXtra['level']=$level;$el->eXtra['top']=$this->top;if($this->balancing===self::DYNAMIC){if($surround)$el->eXtra['deltaLevel']=&$this->_deltaSurround;else$el->eXtra['deltaLevel']=&$this->_deltaUnderline;}else{$el->eXtra['deltaLevel']=0;}$el->parseLine($tx,trim($content));$title=Texy::wash($el->getContent());if($this->title===NULL)$this->title=$title;if($this->generateID&&empty($el->id)){$el->id=strtolower(iconv('UTF-8','ASCII//TRANSLIT',$title));$el->id=str_replace(' ','-',$el->id);$el->id=$this->idPrefix.preg_replace('#[^a-z0-9_-]#i','',$el->id);$counter='';if(isset($this->usedID[$el->id.$counter])){$counter=2;while(isset($this->usedID[$el->id.'-'.$counter]))$counter++;$el->id.='-'.$counter;}$this->usedID[$el->id]=TRUE;}$TOC=array('id'=>isset($el->id)?$el->id:NULL,'title'=>$title,'level'=>0,);$this->TOC[]=&$TOC;$el->eXtra['TOC']=&$TOC;return$el;}}class
TexyHeadingElement
extends
TexyHtml{public
function
startTag(){$level=$this->eXtra['level']+$this->eXtra['deltaLevel']+$this->eXtra['top'];$this->elName='h'.min(6,max(1,$level));$this->eXtra['TOC']['level']=$level;return
parent::startTag();}} 

class
TexyHorizLineModule
extends
TexyModule{protected$default=array('horizline'=>TRUE);public
function
init(){$this->texy->registerBlockPattern(array($this,'pattern'),'#^(\- |\-|\* |\*){3,}\ *'.TEXY_MODIFIER_H.'?()$#mU','horizline');}public
function
pattern($parser,$matches){list(,,$mMod)=$matches;$el=TexyHtml::el('hr');$mod=new
TexyModifier($mMod);$mod->decorate($this->texy,$el);return$el;}} 

class
TexyHtmlModule
extends
TexyModule{protected$default=array('html/tag'=>TRUE,'html/comment'=>FALSE,);public
function
init(){$this->texy->registerLinePattern(array($this,'patternTag'),'#<(/?)([a-z][a-z0-9_:-]*)((?:\s+[a-z0-9:-]+|=\s*"[^"'.TEXY_MARK.']*"|=\s*\'[^\''.TEXY_MARK.']*\'|=[^\s>'.TEXY_MARK.']+)*)\s*(/?)>#is','html/tag');$this->texy->registerLinePattern(array($this,'patternComment'),'#<!--([^'.TEXY_MARK.']*?)-->#is','html/comment');}public
function
patternComment($parser,$matches){list($match)=$matches;$tx=$this->texy;if(is_callable(array($tx->handler,'htmlComment')))return$tx->handler->htmlComment($tx,$match);return$tx->protect($match,Texy::CONTENT_NONE);}public
function
patternTag($parser,$matches){list($match,$mClosing,$mTag,$mAttr,$mEmpty)=$matches;$tx=$this->texy;$tag=strtolower($mTag);if(!isset(Texy::$blockTags[$tag])&&!isset(Texy::$inlineTags[$tag]))$tag=$mTag;$aTags=$tx->allowedTags;if(!$aTags)return
FALSE;if(is_array($aTags)){if(!isset($aTags[$tag]))return
FALSE;$aAttrs=$aTags[$tag];}else{$aAttrs=NULL;}$isEmpty=$mEmpty==='/';if(!$isEmpty&&substr($mAttr,-1)==='/'){$mAttr=substr($mAttr,0,-1);$isEmpty=TRUE;}$isOpening=$mClosing!=='/';if($isEmpty&&!$isOpening)return
FALSE;$el=TexyHtml::el($tag);if($aTags===Texy::ALL&&$isEmpty)$el->_empty=TRUE;if(!$isOpening){if(is_callable(array($tx->handler,'htmlTag')))return$tx->handler->htmlTag($tx,$el,FALSE);return$tx->protect($el->endTag(),$el->getContentType());}if(is_array($aAttrs))$aAttrs=array_flip($aAttrs);else$aAttrs=NULL;$mAttr=strtr($mAttr,"\n",' ');preg_match_all('#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is',$mAttr,$matches2,PREG_SET_ORDER);foreach($matches2
as$m){$key=strtolower($m[1]);if($aAttrs!==NULL&&!isset($aAttrs[$key]))continue;$val=$m[2];if($val==NULL)$el->$key=TRUE;elseif($val{0}==='\''||$val{0}==='"')$el->$key=Texy::decode(substr($val,1,-1));else$el->$key=Texy::decode($val);}if(isset($el->class)){$tmp=$tx->_classes;if(is_array($tmp)){$el->class=explode(' ',$el->class);foreach($el->class
as$key=>$val)if(!isset($tmp[$val]))unset($el->class[$key]);if(!isset($tmp['#'.$el->id]))$el->id=NULL;}elseif($tmp!==Texy::ALL){$el->class=$el->id=NULL;}}if(isset($el->style)){$tmp=$tx->_styles;if(is_array($tmp)){$styles=explode(';',$el->style);$el->style=NULL;foreach($styles
as$value){$pair=explode(':',$value,2);$prop=trim($pair[0]);if(isset($pair[1])&&isset($tmp[strtolower($prop)]))$el->style[$prop]=$pair[1];}}elseif($tmp!==Texy::ALL){$el->style=NULL;}}if($tag==='img'){if(!isset($el->src))return
FALSE;$tx->summary['images'][]=$el->src;}elseif($tag==='a'){if(!isset($el->href)&&!isset($el->name)&&!isset($el->id))return
FALSE;if(isset($el->href)){$tx->summary['links'][]=$el->href;}}if(is_callable(array($tx->handler,'htmlTag')))return$tx->handler->htmlTag($tx,$el,TRUE);return$tx->protect($el->startTag(),$el->getContentType());}} 

class
TexyFigureModule
extends
TexyModule{protected$default=array('figure'=>TRUE);public$class='figure';public$leftClass='figure-left';public$rightClass='figure-right';public$widthDelta=10;public
function
init(){$this->texy->registerBlockPattern(array($this,'pattern'),'#^'.TEXY_IMAGE.TEXY_LINK_N.'?? +\*\*\* +(.*)'.TEXY_MODIFIER_H.'?()$#mU','figure');}public
function
pattern($parser,$matches){list(,$mURLs,$mImgMod,$mAlign,$mLink,$mContent,$mMod)=$matches;$tx=$this->texy;$image=$tx->imageModule->parse($mURLs,$mImgMod.$mAlign,TRUE);$mod=new
TexyModifier($mMod);$mContent=ltrim($mContent);if($mLink){if($mLink===':'){$link=new
TexyLink;$link->URL=$image->linkedURL===NULL?$image->imageURL:$image->linkedURL;$link->type=TexyLink::AUTOIMAGE;$link->modifier=new
TexyModifier;}else{$link=$tx->linkModule->parse($mLink,NULL,NULL);}}else$link=NULL;if(is_callable(array($tx->handler,'figure'))){$res=$tx->handler->figure($tx,$image,$link,$mContent,$mod);if($res!==NULL)return$res;}return$this->factory($image,$link,$mContent,$mod);}public
function
factory(TexyImage$image,$link,$content,$mod){$tx=$this->texy;$hAlign=$image->modifier->hAlign;$mod->hAlign=$image->modifier->hAlign=NULL;$elImg=$tx->imageModule->factory($image,$link);$el=TexyHtml::el('div');if(!empty($image->width))$el->style['width']=($image->width+$this->widthDelta).'px';$mod->decorate($tx,$el);$el->childNodes['img']=$elImg;$el->childNodes['caption']=TexyHtml::el('');$el->childNodes['caption']->parseBlock($tx,ltrim($content));if($hAlign===TexyModifier::HALIGN_LEFT){$el->class[]=$this->leftClass;}elseif($hAlign===TexyModifier::HALIGN_RIGHT){$el->class[]=$this->rightClass;}elseif($this->class)$el->class[]=$this->class;return$el;}} 

class
TexyImageModule
extends
TexyModule{protected$default=array('image'=>TRUE,);public$root='images/';public$linkedRoot='images/';public$fileRoot;public$leftClass;public$rightClass;public$defaultAlt='';private$references=array();public$rootPrefix='';public
function
__construct($texy){parent::__construct($texy);$this->rootPrefix=&$this->fileRoot;if(isset($_SERVER['SCRIPT_NAME'])){$this->fileRoot=dirname($_SERVER['SCRIPT_NAME']);}}public
function
init(){$this->texy->registerLinePattern(array($this,'patternImage'),'#'.TEXY_IMAGE.TEXY_LINK_N.'??()#U','image');}public
function
addReference($name,$URLs,$modifier=NULL){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}if(!$modifier)$modifier=new
TexyModifier;$image=$this->parse($URLs,NULL,FALSE);$image->modifier=$modifier;$image->name=$name;$this->references[$name]=$image;}public
function
getReference($name){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}if(isset($this->references[$name]))return
clone$this->references[$name];return
FALSE;}public
function
parse($content,$mod,$tryRef){$image=$tryRef?$this->getReference(trim($content)):FALSE;if(!$image){$content=explode('|',$content);$image=new
TexyImage;if(preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U',$content[0],$matches)){$image->imageURL=trim($matches[1]);$image->width=(int)$matches[2];$image->height=(int)$matches[3];}else{$image->imageURL=trim($content[0]);}if(isset($content[1])){$tmp=trim($content[1]);if($tmp!=='')$image->overURL=$tmp;}if(isset($content[2])){$tmp=trim($content[2]);if($tmp!=='')$image->linkedURL=$tmp;}$image->modifier=new
TexyModifier;}$image->modifier->setProperties($mod);return$image;}public
function
preProcess($text){return
preg_replace_callback('#^\[\*([^\n]+)\*\]:\ +(.+)\ *'.TEXY_MODIFIER.'?()$#mU',array($this,'patternReferenceDef'),$text);}public
function
patternReferenceDef($matches){list(,$mRef,$mURLs,$mMod)=$matches;$mod=new
TexyModifier($mMod);$this->addReference($mRef,$mURLs,$mod);return'';}public
function
patternImage($parser,$matches){list(,$mURLs,$mMod,$mAlign,$mLink)=$matches;$tx=$this->texy;$image=$this->parse($mURLs,$mMod.$mAlign,TRUE);if($mLink){if($mLink===':'){$link=new
TexyLink;$link->URL=$image->linkedURL===NULL?$image->imageURL:$image->linkedURL;$link->type=TexyLink::AUTOIMAGE;$link->modifier=new
TexyModifier;}else{$link=$tx->linkModule->parse($mLink,NULL,NULL);}}else$link=NULL;if(is_callable(array($tx->handler,'image'))){$res=$tx->handler->image($tx,$image,$link);if($res!==NULL)return$res;}return$this->factory($image,$link);}public
function
factory(TexyImage$image,$link){$tx=$this->texy;$src=Texy::completeURL($image->imageURL,$this->root);$file=Texy::completePath($image->imageURL,$this->fileRoot);if(substr($src,-4)==='.swf'){}$mod=$image->modifier;$alt=$mod->title!==NULL?$mod->title:$this->defaultAlt;$mod->title=NULL;$hAlign=$mod->hAlign;$mod->hAlign=NULL;$el=TexyHtml::el('img');$mod->decorate($tx,$el);if($hAlign===TexyModifier::HALIGN_LEFT){if($this->leftClass!='')$el->class[]=$this->leftClass;else$el->style['float']='left';}elseif($hAlign===TexyModifier::HALIGN_RIGHT){if($this->rightClass!='')$el->class[]=$this->rightClass;else$el->style['float']='right';}if($image->width||$image->height){$el->width=$image->width;$el->height=$image->height;}elseif(is_file($file)){$size=getImageSize($file);if(is_array($size)){$image->width=$el->width=$size[0];$image->height=$el->height=$size[1];}}$el->src=$src;$el->alt=(string)$alt;if($image->overURL!==NULL){$overSrc=Texy::completeURL($image->overURL,$this->root);$el->onmouseover='this.src=\''.addSlashes($overSrc).'\'';$el->onmouseout='this.src=\''.addSlashes($src).'\'';static$counter;$counter++;$attrs['onload']="preload_$counter=new Image();preload_$counter.src='".addSlashes($overSrc)."';this.onload=''";$tx->summary['preload'][]=$overSrc;}$tx->summary['images'][]=$el->src;if($link)return$tx->linkModule->factory($link,$el);return$el;}}class
TexyImage{public$imageURL;public$overURL;public$linkedURL;public$width;public$height;public$modifier;public$name;public
function
__clone(){if($this->modifier)$this->modifier=clone$this->modifier;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}} 

class
TexyLinkModule
extends
TexyModule{protected$default=array('link/reference'=>TRUE,'link/email'=>TRUE,'link/url'=>TRUE,'link/definition'=>TRUE,);public$root='';public$emailOnClick;public$imageOnClick='return !popupImage(this.href)';public$popupOnClick='return !popup(this.href)';public$forceNoFollow=FALSE;protected$references=array();static
private$deadlock;public
function
init(){self::$deadlock=array();$tx=$this->texy;$tx->registerLinePattern(array($this,'patternReference'),'#('.TEXY_LINK_REF.')#U','link/reference');$tx->registerLinePattern(array($this,'patternUrlEmail'),'#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#iu','link/url');$tx->registerLinePattern(array($this,'patternUrlEmail'),'#(?<=\s|^|\(|\[|\<|:)'.TEXY_EMAIL.'#i','link/email');}public
function
preProcess($text){if($this->texy->allowed['link/definition'])return
preg_replace_callback('#^\[([^\[\]\#\?\*\n]+)\]: +(\S+)(\ .+)?'.TEXY_MODIFIER.'?()$#mU',array($this,'patternReferenceDef'),$text);return$text;}private
function
patternReferenceDef($matches){list(,$mRef,$mLink,$mLabel,$mMod)=$matches;$mod=new
TexyModifier($mMod);$this->addReference($mRef,$mLink,trim($mLabel),$mod);return'';}public
function
patternReference($parser,$matches){list($match,$mRef)=$matches;$tx=$this->texy;$name=substr($mRef,1,-1);$link=$this->getReference($name);if(!$link){if(is_callable(array($tx->handler,'newReference')))return$tx->handler->newReference($tx,$name);return
FALSE;}$link->type=TexyLink::REF;if($link->label!=''){if(isset(self::$deadlock[$link->name])){$content=$link->label;}else{self::$deadlock[$link->name]=TRUE;$lineParser=new
TexyLineParser($tx);$content=$lineParser->parse($link->label);unset(self::$deadlock[$link->name]);}}else{$content=$this->textualURL($link->URL);}if(is_callable(array($tx->handler,'linkReference'))){$res=$tx->handler->linkReference($tx,$link,$content);if($res!==NULL)return$res;}return$this->factory($link,$content);}public
function
patternUrlEmail($parser,$matches,$name){list($mURL)=$matches;$link=new
TexyLink;$link->URL=$mURL;$content=$this->textualURL($mURL);$method=$name==='link/email'?'linkEmail':'linkURL';if(is_callable(array($this->texy->handler,$method))){$res=$this->texy->handler->$method($this->texy,$link,$content);if($res!==NULL)return$res;}return$this->factory($link,$content);}public
function
addReference($name,$URL,$label=NULL,$modifier=NULL){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}if(!$modifier)$modifier=new
TexyModifier;$link=new
TexyLink;$link->URL=$URL;$link->label=$label;$link->modifier=$modifier;$link->name=$name;$this->references[$name]=$link;}public
function
getReference($name){if(function_exists('mb_strtolower')){$name=mb_strtolower($name,'UTF-8');}else{$name=strtolower($name);}if(isset($this->references[$name])){return
clone$this->references[$name];}else{$pos=strpos($name,'?');if($pos===FALSE)$pos=strpos($name,'#');if($pos!==FALSE){$name2=substr($name,0,$pos);if(isset($this->references[$name2])){$link=clone$this->references[$name2];$link->URL.=substr($name,$pos);return$link;}}}return
FALSE;}public
function
parse($dest,$mMod,$label){$tx=$this->texy;$type=TexyLink::NORMAL;if(strlen($dest)>1&&$dest{0}==='['&&$dest{1}!=='*'){$type=TexyLink::REF;$dest=substr($dest,1,-1);$link=$this->getReference($dest);}elseif(strlen($dest)>1&&$dest{0}==='['&&$dest{1}==='*'){$type=TexyLink::IMAGE;$dest=trim(substr($dest,2,-2));$image=$tx->imageModule->getReference($dest);if($image){$link=new
TexyLink;$link->URL=$image->linkedURL===NULL?$image->imageURL:$image->linkedURL;$link->modifier=$image->modifier;}}if(empty($link)){$link=new
TexyLink;$link->URL=trim($dest);$link->modifier=new
TexyModifier;}$link->URL=str_replace('%s',urlencode(Texy::wash($label)),$link->URL);$link->modifier->setProperties($mMod);$link->type=$type;return$link;}public
function
factory($link,$content=NULL){$tx=$this->texy;$el=TexyHtml::el('a');if(empty($link->modifier)){$nofollow=$popup=FALSE;}else{$classes=array_flip($link->modifier->classes);$nofollow=isset($classes['nofollow']);$popup=isset($classes['popup']);unset($classes['nofollow'],$classes['popup']);$link->modifier->classes=array_flip($classes);$link->modifier->decorate($tx,$el);}if($link->type===TexyLink::IMAGE||$link->type===TexyLink::AUTOIMAGE){$el->href=Texy::completeURL($link->URL,$tx->imageModule->linkedRoot);$el->onclick=$this->imageOnClick;}else{if(preg_match('#^'.TEXY_EMAIL.'$#i',$link->URL)){$el->href='mailto:'.$link->URL;$el->onclick=$this->emailOnClick;}else{$el->href=Texy::completeURL($link->URL,$this->root,$isAbsolute);if($nofollow||($this->forceNoFollow&&$isAbsolute))$el->rel[]='nofollow';}}if($popup)$el->onclick=$this->popupOnClick;if($content!==NULL)$el->setContent($content);$tx->summary['links'][]=$el->href;return$el;}private
function
textualURL($URL){if(preg_match('#^'.TEXY_EMAIL.'$#i',$URL)){return$this->texy->obfuscateEmail?strtr($URL,array('@'=>"&#160;(at)&#160;")):$URL;}if(preg_match('#^(https?://|ftp://|www\.|ftp\.|/)#i',$URL)){if(strncasecmp($URL,'www.',4)===0)$parts=@parse_url('none://'.$URL);elseif(strncasecmp($URL,'ftp.',4)===0)$parts=@parse_url('none://'.$URL);else$parts=@parse_url($URL);if($parts===FALSE)return$URL;$res='';if(isset($parts['scheme'])&&$parts['scheme']!=='none')$res.=$parts['scheme'].'://';if(isset($parts['host']))$res.=$parts['host'];if(isset($parts['path']))$res.=(strlen($parts['path'])>16?('/...'.preg_replace('#^.*(.{0,12})$#U','$1',$parts['path'])):$parts['path']);if(isset($parts['query'])){$res.=strlen($parts['query'])>4?'?...':('?'.$parts['query']);}elseif(isset($parts['fragment'])){$res.=strlen($parts['fragment'])>4?'#...':('#'.$parts['fragment']);}return$res;}return$URL;}}class
TexyLink{const
NORMAL=1,REF=2,IMAGE=3,AUTOIMAGE=4;public$URL;public$label;public$modifier;public$name;public$type=self::NORMAL;public
function
__clone(){if($this->modifier)$this->modifier=clone$this->modifier;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}} 

class
TexyListModule
extends
TexyModule{protected$default=array('list'=>TRUE);public$bullets=array('*'=>array('\*','','ul'),'-'=>array('[\x{2013}-]','','ul'),'+'=>array('\+','','ul'),'1.'=>array('\d{1,3}\.\ ','','ol'),'1)'=>array('\d{1,3}\)','','ol'),'I.'=>array('[IVX]{1,4}\.\ ','upper-roman','ol'),'I)'=>array('[IVX]+\)','upper-roman','ol'),'a)'=>array('[a-z]\)','lower-alpha','ol'),'A)'=>array('[A-Z]\)','upper-alpha','ol'),);public
function
init(){$RE=array();foreach($this->bullets
as$desc)if(is_array($desc))$RE[]=$desc[0];$this->texy->registerBlockPattern(array($this,'patternList'),'#^(?:'.TEXY_MODIFIER_H.'\n)?'.'('.implode('|',$RE).')(\n?)\ +\S.*$#mUu','list');}public
function
patternList($parser,$matches){list(,$mMod,$mBullet,$mNewLine)=$matches;$tx=$this->texy;$el=TexyHtml::el();$bullet='';foreach($this->bullets
as$type=>$desc)if(preg_match('#'.$desc[0].'#Au',$mBullet)){$bullet=$desc[0];$el->elName=$desc[2];$el->style['list-style-type']=$desc[1];if($el->elName==='ol'){if($type[0]==='1'&&(int)$mBullet>1)$el->start=(int)$mBullet;elseif($type[0]==='a'&&$mBullet[0]>'a')$el->start=ord($mBullet[0])-96;elseif($type[0]==='A'&&$mBullet[0]>'A')$el->start=ord($mBullet[0])-64;}break;}$mod=new
TexyModifier($mMod);$mod->decorate($tx,$el);$parser->moveBackward($mNewLine?2:1);$count=0;while($elItem=$this->patternItem($parser,$bullet,FALSE,'li')){$el->childNodes[]=$elItem;$count++;}if(!$count)return
FALSE;return$el;}public
function
patternItem($parser,$bullet,$indented,$tag){$tx=$this->texy;$spacesBase=$indented?('\ {1,}'):'';$patternItem="#^\n?($spacesBase)$bullet(\n?)(\\ +)(\\S.*)?".TEXY_MODIFIER_H."?()$#mAUu";if(!$parser->receiveNext($patternItem,$matches)){return
FALSE;}list(,$mIndent,$mNewLine,$mSpace,$mContent,$mMod)=$matches;$elItem=TexyHtml::el($tag);$mod=new
TexyModifier($mMod);$mod->decorate($tx,$elItem);$spaces=$mNewLine?strlen($mSpace):'';$content=' '.$mContent;while($parser->receiveNext('#^(\n*)'.$mIndent.'(\ {1,'.$spaces.'})(.*)()$#Am',$matches)){list(,$mBlank,$mSpaces,$mContent)=$matches;if($spaces==='')$spaces=strlen($mSpaces);$content.="\n".$mBlank.$mContent;}$tmp=$tx->_paragraphMode;$tx->_paragraphMode=FALSE;$elItem->parseBlock($tx,$content);$tx->_paragraphMode=$tmp;if($elItem->childNodes[0]instanceof
TexyHtml){$elItem->childNodes[0]->elName='';}return$elItem;}} 

class
TexyDefinitionListModule
extends
TexyListModule{protected$default=array('list/definition'=>TRUE);public$bullets=array('*'=>array('\*'),'-'=>array('[\x{2013}-]'),'+'=>array('\+'),);public
function
init(){$RE=array();foreach($this->bullets
as$desc)if(is_array($desc))$RE[]=$desc[0];$this->texy->registerBlockPattern(array($this,'patternDefList'),'#^(?:'.TEXY_MODIFIER_H.'\n)?'.'(\S.*)\:\ *'.TEXY_MODIFIER_H.'?\n'.'(\ +)('.implode('|',$RE).')\ +\S.*$#mUu','list/definition');}public
function
patternDefList($parser,$matches){list(,$mMod,$mContentTerm,$mMod,$mSpaces,$mBullet)=$matches;$tx=$this->texy;$bullet='';foreach($this->bullets
as$desc)if(is_array($desc)&&preg_match('#'.$desc[0].'#Au',$mBullet)){$bullet=$desc[0];break;}$el=TexyHtml::el('dl');$mod=new
TexyModifier($mMod);$mod->decorate($tx,$el);$parser->moveBackward(2);$patternTerm='#^\n?(\S.*)\:\ *'.TEXY_MODIFIER_H.'?()$#mUA';$bullet=preg_quote($mBullet);while(TRUE){if($elItem=$this->patternItem($parser,preg_quote($mBullet),TRUE,'dd')){$el->childNodes[]=$elItem;continue;}if($parser->receiveNext($patternTerm,$matches)){list(,$mContent,$mMod)=$matches;$elItem=TexyHtml::el('dt');$mod=new
TexyModifier($mMod);$mod->decorate($tx,$elItem);$elItem->parseLine($tx,$mContent);$el->childNodes[]=$elItem;continue;}break;}return$el;}} 

class
TexyLongWordsModule
extends
TexyModule
implements
ITexyLineModule{protected$default=array('longwords'=>TRUE);public$wordLimit=20;const
DONT=0,HERE=1,AFTER=2;private$consonants=array('b','c','d','f','g','h','j','k','l','m','n','p','q','r','s','t','v','w','x','z','B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Z',"\xc4\x8d","\xc4\x8f","\xc5\x88","\xc5\x99","\xc5\xa1","\xc5\xa5","\xc5\xbe","\xc4\x8c","\xc4\x8e","\xc5\x87","\xc5\x98","\xc5\xa0","\xc5\xa4","\xc5\xbd");private$vowels=array('a','e','i','o','u','y','A','E','I','O','U','Y',"\xc3\xa1","\xc3\xa9","\xc4\x9b","\xc3\xad","\xc3\xb3","\xc3\xba","\xc5\xaf","\xc3\xbd","\xc3\x81","\xc3\x89","\xc4\x9a","\xc3\x8d","\xc3\x93","\xc3\x9a","\xc5\xae","\xc3\x9d");private$before_r=array('b','B','c','C','d','D','f','F','g','G','k','K','p','P','r','R','t','T','v','V',"\xc4\x8d","\xc4\x8c","\xc4\x8f","\xc4\x8e","\xc5\x99","\xc5\x98","\xc5\xa5","\xc5\xa4");private$before_l=array('b','B','c','C','d','D','f','F','g','G','k','K','l','L','p','P','t','T','v','V',"\xc4\x8d","\xc4\x8c","\xc4\x8f","\xc4\x8e","\xc5\xa5","\xc5\xa4");private$before_h=array('c','C','s','S');private$doubleVowels=array('a','A','o','O');public
function
__construct($texy){parent::__construct($texy);$this->consonants=array_flip($this->consonants);$this->vowels=array_flip($this->vowels);$this->before_r=array_flip($this->before_r);$this->before_l=array_flip($this->before_l);$this->before_h=array_flip($this->before_h);$this->doubleVowels=array_flip($this->doubleVowels);}public
function
linePostProcess($text){if(empty($this->texy->allowed['longwords']))return$text;return
preg_replace_callback('#[^\ \n\t\x14\x15\x16\x{2013}\x{2014}\x{ad}-]{'.$this->wordLimit.',}#u',array($this,'pattern'),$text);}private
function
pattern($matches){list($mWord)=$matches;$chars=array();preg_match_all('#['.TEXY_MARK.']+|.#u',$mWord,$chars);$chars=$chars[0];if(count($chars)<$this->wordLimit)return$mWord;$consonants=$this->consonants;$vowels=$this->vowels;$before_r=$this->before_r;$before_l=$this->before_l;$before_h=$this->before_h;$doubleVowels=$this->doubleVowels;$s=array();$trans=array();$s[]='';$trans[]=-1;foreach($chars
as$key=>$char){if(ord($char{0})<32)continue;$s[]=$char;$trans[]=$key;}$s[]='';$len=count($s)-2;$positions=array();$a=0;$last=1;while(++$a<$len){$hyphen=self::DONT;do{if($s[$a]==="\xC2\xA0"){$a++;continue
2;}if($s[$a]==='.'){$hyphen=self::HERE;break;}if(isset($consonants[$s[$a]])){if(isset($vowels[$s[$a+1]])){if(isset($vowels[$s[$a-1]]))$hyphen=self::HERE;break;}if(($s[$a]==='s')&&($s[$a-1]==='n')&&isset($consonants[$s[$a+1]])){$hyphen=self::AFTER;break;}if(isset($consonants[$s[$a+1]])&&isset($vowels[$s[$a-1]])){if($s[$a+1]==='r'){$hyphen=isset($before_r[$s[$a]])?self::HERE:self::AFTER;break;}if($s[$a+1]==='l'){$hyphen=isset($before_l[$s[$a]])?self::HERE:self::AFTER;break;}if($s[$a+1]==='h'){$hyphen=isset($before_h[$s[$a]])?self::DONT:self::AFTER;break;}$hyphen=self::AFTER;break;}break;}if(($s[$a]==='u')&&isset($doubleVowels[$s[$a-1]])){$hyphen=self::AFTER;break;}if(isset($vowels[$s[$a]])&&isset($vowels[$s[$a-1]])){$hyphen=self::HERE;break;}}while(0);if($hyphen===self::DONT&&($a-$last>$this->wordLimit*0.6))$positions[]=$last=$a-1;if($hyphen===self::HERE)$positions[]=$last=$a-1;if($hyphen===self::AFTER){$positions[]=$last=$a;$a++;}}$a=end($positions);if(($a===$len-1)&&isset($consonants[$s[$len]]))array_pop($positions);$syllables=array();$last=0;foreach($positions
as$pos){if($pos-$last>$this->wordLimit*0.6){$syllables[]=implode('',array_splice($chars,0,$trans[$pos]-$trans[$last]));$last=$pos;}}$syllables[]=implode('',$chars);return
implode("\xC2\xAD",$syllables);;}} 

class
TexyPhraseModule
extends
TexyModule{protected$default=array('phrase/strong+em'=>TRUE,'phrase/strong'=>TRUE,'phrase/em'=>TRUE,'phrase/em-alt'=>TRUE,'phrase/span'=>TRUE,'phrase/span-alt'=>TRUE,'phrase/acronym'=>TRUE,'phrase/acronym-alt'=>TRUE,'phrase/code'=>TRUE,'phrase/notexy'=>TRUE,'phrase/quote'=>TRUE,'phrase/quicklink'=>TRUE,'phrase/ins'=>FALSE,'phrase/del'=>FALSE,'phrase/sup'=>FALSE,'phrase/sub'=>FALSE,'phrase/cite'=>FALSE,'deprecated/codeswitch'=>FALSE,);public$tags=array('phrase/strong'=>'strong','phrase/em'=>'em','phrase/em-alt'=>'em','phrase/ins'=>'ins','phrase/del'=>'del','phrase/sup'=>'sup','phrase/sub'=>'sub','phrase/span'=>'a','phrase/span-alt'=>'a','phrase/cite'=>'cite','phrase/acronym'=>'acronym','phrase/acronym-alt'=>'acronym','phrase/code'=>'code','phrase/quote'=>'q','phrase/quicklink'=>'a',);public$linksAllowed=TRUE;public
function
init(){$tx=$this->texy;$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\*)\*\*\*(?!\s|\*)(.+)'.TEXY_MODIFIER.'?(?<!\s|\*)\*\*\*(?!\*)()'.TEXY_LINK.'??()#Us','phrase/strong+em');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\*)\*\*(?!\s|\*)(.+)'.TEXY_MODIFIER.'?(?<!\s|\*)\*\*(?!\*)'.TEXY_LINK.'??()#Us','phrase/strong');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<![/:])\/\/(?!\s|\/)(.+)'.TEXY_MODIFIER.'?(?<!\s|\/)\/\/(?!\/)'.TEXY_LINK.'??()#Us','phrase/em');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\*)\*(?!\s|\*)(.+)'.TEXY_MODIFIER.'?(?<!\s|\*)\*(?!\*)'.TEXY_LINK.'??()#Us','phrase/em-alt');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\+)\+\+(?!\s|\+)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\+)\+\+(?!\+)()#U','phrase/ins');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\-)\-\-(?!\s|\-)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\-)\-\-(?!\-)()#U','phrase/del');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\^)\^\^(?!\s|\^)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\^)\^\^(?!\^)()#U','phrase/sup');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\_)\_\_(?!\s|\_)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\_)\_\_(?!\_)()#U','phrase/sub');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\")\"(?!\s)([^\"\r]+)'.TEXY_MODIFIER.'?(?<!\s)\"(?!\")'.TEXY_LINK.'??()#U','phrase/span');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\~)\~(?!\s)([^\~\r]+)'.TEXY_MODIFIER.'?(?<!\s)\~(?!\~)'.TEXY_LINK.'??()#U','phrase/span-alt');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\~)\~\~(?!\s|\~)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\~)\~\~(?!\~)'.TEXY_LINK.'??()#U','phrase/cite');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\>)\>\>(?!\s|\>)([^\r\n]+)'.TEXY_MODIFIER.'?(?<!\s|\<)\<\<(?!\<)'.TEXY_LINK.'??()#U','phrase/quote');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!\")\"(?!\s)([^\"\r\n]+)'.TEXY_MODIFIER.'?(?<!\s)\"(?!\")\(\((.+)\)\)()#U','phrase/acronym');$tx->registerLinePattern(array($this,'patternPhrase'),'#(?<!['.TEXY_CHAR.'])(['.TEXY_CHAR.']{2,})()\(\((.+)\)\)#Uu','phrase/acronym-alt');$tx->registerLinePattern(array($this,'patternNoTexy'),'#(?<!\')\'\'(?!\s|\')([^'.TEXY_MARK.'\r\n]*)(?<!\s|\')\'\'(?!\')()#U','phrase/notexy');$tx->registerLinePattern(array($this,'patternPhrase'),'#\`(\S[^'.TEXY_MARK.'\r\n]*)'.TEXY_MODIFIER.'?(?<!\s)\`()#U','phrase/code');$tx->registerLinePattern(array($this,'patternPhrase'),'#(['.TEXY_CHAR.'0-9@\#$%&.,_-]+)()(?=:\[)'.TEXY_LINK.'()#Uu','phrase/quicklink');$tx->registerBlockPattern(array($this,'patternCodeSwitch'),'#^`=(none|code|kbd|samp|var|span)$#mUi','deprecated/codeswitch');}public
function
patternPhrase($parser,$matches,$phrase){list($match,$mContent,$mMod,$mLink)=$matches;$tx=$this->texy;$mod=new
TexyModifier($mMod);$link=NULL;$parser->again=$phrase!=='phrase/code'&&$phrase!=='phrase/quicklink';if($phrase==='phrase/span'||$phrase==='phrase/span-alt'){if($mLink==NULL){if(!$mMod)return
FALSE;}else{$link=$tx->linkModule->parse($mLink,$mMod,$mContent);}}elseif($phrase==='phrase/acronym'||$phrase==='phrase/acronym-alt'){$mod->title=trim(Texy::decode($mLink));}elseif($phrase==='phrase/quote'){$mod->cite=$tx->quoteModule->citeLink($mLink);}elseif($mLink!=NULL){$link=$tx->linkModule->parse($mLink,NULL,$mContent);}if(is_callable(array($tx->handler,'phrase'))){$res=$tx->handler->phrase($tx,$phrase,$mContent,$mod,$link);if($res!==NULL)return$res;}return$this->factory($phrase,$mContent,$mod,$link);}public
function
factory($phrase,$content,$mod,$link){$tx=$this->texy;$tag=isset($this->tags[$phrase])?$this->tags[$phrase]:NULL;if($tag==='a')$tag=$link&&$this->linksAllowed?NULL:'span';if($phrase==='phrase/code')$el=$tx->protect(Texy::encode($content),Texy::CONTENT_TEXTUAL);else$el=$content;if($phrase==='phrase/strong+em'){$el=TexyHtml::el($this->tags['phrase/em'])->setContent($el);$tag=$this->tags['phrase/strong'];}if($tag){$el=TexyHtml::el($tag)->setContent($el);$mod->decorate($tx,$el);}if($tag==='q')$el->cite=$mod->cite;if($link)return$tx->linkModule->factory($link)->setContent($el);return$el;}public
function
patternNoTexy($parser,$matches){list(,$mContent)=$matches;return$this->texy->protect(Texy::encode($mContent),Texy::CONTENT_TEXTUAL);}public
function
patternCodeSwitch($parser,$matches){$this->tags['phrase/code']=$matches[1];}} 

class
TexyQuoteModule
extends
TexyModule{protected$default=array('blockquote'=>TRUE);public
function
init(){$this->texy->registerBlockPattern(array($this,'pattern'),'#^(?:'.TEXY_MODIFIER_H.'\n)?\>(?:(\ +?|:)(.*))?()$#mU','blockquote');}public
function
pattern($parser,$matches){list(,$mMod,$mPrefix,$mContent)=$matches;$tx=$this->texy;$el=TexyHtml::el('blockquote');$mod=new
TexyModifier($mMod);$mod->decorate($tx,$el);$content='';$spaces='';do{if($mPrefix==='>'){$content.=$mPrefix.$mContent."\n";}elseif($mPrefix===':'){$mod->cite=$tx->quoteModule->citeLink($mContent);$content.="\n";}else{if($spaces==='')$spaces=max(1,strlen($mPrefix));$content.=$mContent."\n";}if(!$parser->receiveNext("#^\>(?:(\>|\ {1,$spaces}|:)(.*))?()$#mA",$matches))break;list(,$mPrefix,$mContent)=$matches;}while(TRUE);$el->cite=$mod->cite;$el->parseBlock($tx,$content);if(!$el->childNodes)return;return$el;}public
function
citeLink($link){$tx=$this->texy;if($link==NULL)return
NULL;if($link{0}==='['){$link=substr($link,1,-1);$ref=$tx->linkModule->getReference($link);if($ref){return
Texy::completeURL($ref['URL'],$tx->linkModule->root);}else{return
Texy::completeURL($link,$tx->linkModule->root);}}else{return
Texy::completeURL($link,$tx->linkModule->root);}}} 

class
TexyScriptModule
extends
TexyModule{protected$default=array('script'=>FALSE);public$handler;public
function
init(){$this->texy->registerLinePattern(array($this,'pattern'),'#\{\{([^'.TEXY_MARK.']+)\}\}()#U','script');}public
function
pattern($parser,$matches){list(,$mContent)=$matches;$func=trim($mContent);if($func==='')return
FALSE;$args=NULL;if(preg_match('#^([a-z_][a-z0-9_]*)\s*\(([^()]*)\)$#i',$func,$matches)){$func=$matches[1];$args=explode(',',$matches[2]);array_walk($args,'trim');}if($func==='texy')return$this->texyHandler($args);if(is_callable(array($this->handler,$func))){array_unshift($args,$this->texy);return
call_user_func_array(array($this->handler,$func),$args);}if(is_callable($this->handler))return
call_user_func_array($this->handler,array($this->texy,$func,$args));return
FALSE;}public
function
texyHandler($args){return'';}} 

class
TexyEmoticonModule
extends
TexyModule{protected$default=array('emoticon'=>FALSE);public$icons=array(':-)'=>'smile.gif',':-('=>'sad.gif',';-)'=>'wink.gif',':-D'=>'biggrin.gif','8-O'=>'eek.gif','8-)'=>'cool.gif',':-?'=>'confused.gif',':-x'=>'mad.gif',':-P'=>'razz.gif',':-|'=>'neutral.gif',);public$class;public$root;public$fileRoot;public
function
init(){if(empty($this->texy->allowed['emoticon']))return;krsort($this->icons);$pattern=array();foreach($this->icons
as$key=>$foo)$pattern[]=preg_quote($key,'#').'+';$this->texy->registerLinePattern(array($this,'pattern'),'#(?<=^|[\\x00-\\x20])('.implode('|',$pattern).')#','emoticon');}public
function
pattern($parser,$matches){$match=$matches[0];$tx=$this->texy;foreach($this->icons
as$emoticon=>$file){if(strncmp($match,$emoticon,strlen($emoticon))===0){if(is_callable(array($tx->handler,'emoticon'))){$res=$tx->handler->emoticon($tx,$emoticon,$match,$file);if($res!==NULL)return$res;}return$this->factory($emoticon,$match,$file);}}}public
function
factory($emoticon,$raw,$file){$tx=$this->texy;$el=TexyHtml::el('img');$el->src=Texy::completeURL($file,$this->root===NULL?$tx->imageModule->root:$this->root);$el->alt=$raw;$el->class[]=$this->class;$file=Texy::completePath($file,$this->fileRoot===NULL?$tx->imageModule->fileRoot:$this->fileRoot);if(is_file($file)){$size=getImageSize($file);if(is_array($size)){$el->width=$size[0];$el->height=$size[1];}}$tx->summary['images'][]=$el->src;return$el;}} 

class
TexyTableModule
extends
TexyModule{protected$default=array('table'=>TRUE);public$oddClass;public$evenClass;private$isHead;private$colModifier;private$last;private$row;public
function
init(){$this->texy->registerBlockPattern(array($this,'patternTable'),'#^(?:'.TEXY_MODIFIER_HV.'\n)?'.'\|.*()$#mU','table');}public
function
patternTable($parser,$matches){list(,$mMod)=$matches;$tx=$this->texy;$el=TexyHtml::el('table');$mod=new
TexyModifier($mMod);$mod->decorate($tx,$el);$parser->moveBackward();if($parser->receiveNext('#^\|(\#|\=){2,}(?!\\1)(.*)\\1*\|? *'.TEXY_MODIFIER_H.'?()$#Um',$matches)){list(,,$mContent,$mMod)=$matches;$caption=TexyHtml::el('caption');$mod=new
TexyModifier($mMod);$mod->decorate($tx,$caption);$caption->parseLine($tx,$mContent);$el->childNodes[]=$caption;}$this->isHead=FALSE;$this->colModifier=array();$this->last=array();$this->row=0;while(TRUE){if($parser->receiveNext('#^\|\-{3,}$#Um',$matches)){$this->isHead=!$this->isHead;continue;}if($elRow=$this->patternRow($parser)){$el->childNodes[]=$elRow;$this->row++;continue;}break;}return$el;}protected
function
patternRow($parser){$tx=$this->texy;if(!$parser->receiveNext('#^\|(.*)(?:|\|\ *'.TEXY_MODIFIER_HV.'?)()$#U',$matches)){return
FALSE;}list(,$mContent,$mMod)=$matches;$elRow=TexyHtml::el('tr');$mod=new
TexyModifier($mMod);$mod->decorate($tx,$elRow);if($this->row
%
2===0){if($this->oddClass)$elRow->class[]=$this->oddClass;}else{if($this->evenClass)$elRow->class[]=$this->evenClass;}$col=0;$elField=NULL;$mContent=str_replace('\\|','&#x7C;',$mContent);foreach(explode('|',$mContent)as$field){if(($field=='')&&$elField){$elField->colspan++;unset($this->last[$col]);$col++;continue;}$field=rtrim($field);if($field==='^'){if(isset($this->last[$col])){$this->last[$col]->rowspan++;$col+=$this->last[$col]->colspan;continue;}}if(!preg_match('#(\*??)\ *'.TEXY_MODIFIER_HV.'??(.*)'.TEXY_MODIFIER_HV.'?()$#AU',$field,$matches))continue;list(,$mHead,$mModCol,$mContent,$mMod)=$matches;if($mModCol){$this->colModifier[$col]=new
TexyModifier($mModCol);}if(isset($this->colModifier[$col]))$mod=clone$this->colModifier[$col];else$mod=new
TexyModifier;$mod->setProperties($mMod);$elField=new
TexyTableFieldElement;$elField->elName=$this->isHead||($mHead==='*')?'th':'td';$mod->decorate($tx,$elField);$elField->parseLine($tx,$mContent);if($elField->childNodes[0]==='')$elField->childNodes[0]="\xC2\xA0";$elRow->childNodes[]=$elField;$this->last[$col]=$elField;$col++;}return$elRow;}}class
TexyTableFieldElement
extends
TexyHtml{public$colspan=1;public$rowspan=1;public
function
startTag(){if($this->colspan==1)$this->colspan=NULL;if($this->rowspan==1)$this->rowspan=NULL;return
parent::startTag();}} 

class
TexyTypographyModule
extends
TexyModule
implements
ITexyLineModule{protected$default=array('typography'=>TRUE);static
public$locales=array('cs'=>array('singleQuotes'=>array("\xe2\x80\x9a","\xe2\x80\x98"),'doubleQuotes'=>array("\xe2\x80\x9e","\xe2\x80\x9c"),),'en'=>array('singleQuotes'=>array("\xe2\x80\x98","\xe2\x80\x99"),'doubleQuotes'=>array("\xe2\x80\x9c","\xe2\x80\x9d"),),'fr'=>array('singleQuotes'=>array("\xe2\x80\xb9","\xe2\x80\xba"),'doubleQuotes'=>array("\xc2\xab","\xc2\xbb"),),'de'=>array('singleQuotes'=>array("\xe2\x80\x9a","\xe2\x80\x98"),'doubleQuotes'=>array("\xe2\x80\x9e","\xe2\x80\x9c"),),'pl'=>array('singleQuotes'=>array("\xe2\x80\x9a","\xe2\x80\x99"),'doubleQuotes'=>array("\xe2\x80\x9e","\xe2\x80\x9d"),),);public$locale='cs';private$pattern,$replace;public
function
init(){$pairs=array('#(?<![.\x{2026}])\.{3,4}(?![.\x{2026}])#mu'=>"\xe2\x80\xa6",'#(?<=[\d ])-(?=[\d ])#'=>"\xe2\x80\x93",'#,-#'=>",\xe2\x80\x93",'#(?<!\d)(\d{1,2}\.) (\d{1,2}\.) (\d\d)#'=>"\$1\xc2\xa0\$2\xc2\xa0\$3",'#(?<!\d)(\d{1,2}\.) (\d{1,2}\.)#'=>"\$1\xc2\xa0\$2",'#([\x{2013}\x{2014}]) #u'=>"\$1\xc2\xa0",'# --- #'=>" \xe2\x80\x94\xc2\xa0",'# -- #'=>" \xe2\x80\x93\xc2\xa0",'# -> #'=>" \xe2\x86\x92 ",'# <- #'=>" \xe2\x86\x90 ",'# <-> #'=>" \xe2\x86\x94 ",'#(\d+)( ?)x\\2(\d+)\\2x\\2(\d+)#'=>"\$1\xc3\x97\$3\xc3\x97\$4",'#(\d+)( ?)x\\2(\d+)#'=>"\$1\xc3\x97\$3",'#(?<=\d)x(?= |,|.|$)#m'=>"\xc3\x97",'#(\S ?)\(TM\)#i'=>"\$1\xe2\x84\xa2",'#(\S ?)\(R\)#i'=>"\$1\xc2\xae",'#\(C\)( ?\S)#i'=>"\xc2\xa9\$1",'#\(EUR\)#'=>"\xe2\x82\xac",'#(\d{1,3}) (\d{3}) (\d{3}) (\d{3})#'=>"\$1\xc2\xa0\$2\xc2\xa0\$3\xc2\xa0\$4",'#(\d{1,3}) (\d{3}) (\d{3})#'=>"\$1\xc2\xa0\$2\xc2\xa0\$3",'#(\d{1,3}) (\d{3})#'=>"\$1\xc2\xa0\$2",'#(?<=.{50})\s+(?=[\x17-\x1F]*\S{1,6}[\x17-\x1F]*$)#us'=>"\xc2\xa0",'#(?<=^| |\.|,|-|\+|\x16)([\x17-\x1F]*\d+[\x17-\x1F]*)\s+([\x17-\x1F]*['.TEXY_CHAR.'\x{b0}-\x{be}\x{2020}-\x{214f}])#mu'=>"\$1\xc2\xa0\$2",'#(?<=^|[^0-9'.TEXY_CHAR.'])([\x17-\x1F]*[ksvzouiKSVZOUIA][\x17-\x1F]*)\s+([\x17-\x1F]*[0-9'.TEXY_CHAR.'])#mus'=>"\$1\xc2\xa0\$2",);if(isset(self::$locales[$this->locale])){$info=self::$locales[$this->locale];$pairs['#(?<!"|\w)"(?!\ |")(.+)(?<!\ |")"(?!")()#U']=$info['doubleQuotes'][0].'$1'.$info['doubleQuotes'][1];$pairs['#(?<!\'|\w)\'(?!\ |\')(.+)(?<!\ |\')\'(?!\')()#Uu']=$info['singleQuotes'][0].'$1'.$info['singleQuotes'][1];}$this->pattern=array_keys($pairs);$this->replace=array_values($pairs);}public
function
linePostProcess($text){if(empty($this->texy->allowed['typography']))return$text;return
preg_replace($this->pattern,$this->replace,$text);}} 
class
Texy{const
ALL=TRUE;const
NONE=FALSE;const
CONTENT_NONE="\x17";const
CONTENT_INLINE="\x16";const
CONTENT_TEXTUAL="\x15";const
CONTENT_BLOCK="\x14";static
public$xhtml=TRUE;public$encoding='utf-8';public$allowed=array();public$allowedTags;public$allowedClasses;public$allowedStyles;public$obfuscateEmail=TRUE;public$tabWidth=8;public$summary=array('images'=>array(),'links'=>array(),'preload'=>array(),);public$styleSheet='';public$mergeLines=TRUE;public$handler;public$defaultDocument='document/texy';public$scriptModule,$htmlModule,$imageModule,$linkModule,$phraseModule,$emoticonModule,$documentModule,$headingModule,$horizLineModule,$quoteModule,$listModule,$definitionListModule,$tableModule,$figureModule,$typographyModule,$longWordsModule;public$formatter,$formatterModule,$wellForm;static
public$blockTags=array('address'=>1,'blockquote'=>1,'caption'=>1,'col'=>1,'colgroup'=>1,'dd'=>1,'div'=>1,'dl'=>1,'dt'=>1,'fieldset'=>1,'form'=>1,'h1'=>1,'h2'=>1,'h3'=>1,'h4'=>1,'h5'=>1,'h6'=>1,'hr'=>1,'iframe'=>1,'legend'=>1,'li'=>1,'object'=>1,'ol'=>1,'p'=>1,'param'=>1,'pre'=>1,'table'=>1,'tbody'=>1,'td'=>1,'tfoot'=>1,'th'=>1,'thead'=>1,'tr'=>1,'ul'=>1,);static
public$inlineTags=array('a'=>1,'abbr'=>1,'acronym'=>1,'area'=>1,'b'=>1,'big'=>1,'br'=>1,'button'=>1,'cite'=>1,'code'=>1,'del'=>1,'dfn'=>1,'em'=>1,'i'=>1,'img'=>1,'input'=>1,'ins'=>1,'kbd'=>1,'label'=>1,'map'=>1,'noscript'=>1,'optgroup'=>1,'option'=>1,'q'=>1,'samp'=>1,'script'=>1,'select'=>1,'small'=>1,'span'=>1,'strong'=>1,'sub'=>1,'sup'=>1,'textarea'=>1,'tt'=>1,'var'=>1,);static
public$inlineCont=array('br'=>1,'button'=>1,'iframe'=>1,'img'=>1,'input'=>1,'object'=>1,'script'=>1,'select'=>1,'textarea'=>1,'applet'=>1,'isindex'=>1,);static
public$emptyTags=array('img'=>1,'hr'=>1,'br'=>1,'input'=>1,'meta'=>1,'area'=>1,'base'=>1,'col'=>1,'link'=>1,'param'=>1,);private$linePatterns=array();private$blockPatterns=array();private$docTypes=array();private$DOM;private$modules;private$marks=array();public$_paragraphMode;public$_classes,$_styles;public
function
__construct(){$this->loadModules();$this->formatter=new
TexyHtmlFormatter();$this->wellForm=new
TexyHtmlWellForm();$this->formatterModule=$this->formatter;$this->trustMode();$this->allowed['document/texy']=TRUE;$mod=new
TexyModifier;$mod->title='The best text -> HTML converter and formatter';$this->linkModule->addReference('texy','http://texy.info/','Texy!',$mod);$this->linkModule->addReference('google','http://www.google.com/search?q=%s');$this->linkModule->addReference('wikipedia','http://en.wikipedia.org/wiki/Special:Search?search=%s');}protected
function
loadModules(){$this->scriptModule=new
TexyScriptModule($this);$this->htmlModule=new
TexyHtmlModule($this);$this->imageModule=new
TexyImageModule($this);$this->phraseModule=new
TexyPhraseModule($this);$this->linkModule=new
TexyLinkModule($this);$this->emoticonModule=new
TexyEmoticonModule($this);$this->documentModule=new
TexyDocumentModule($this);$this->headingModule=new
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
registerModule($module){$this->modules[]=$module;}public
function
registerLinePattern($handler,$pattern,$name){if(empty($this->allowed[$name]))return;$this->linePatterns[$name]=array('handler'=>$handler,'pattern'=>$pattern,);}public
function
registerBlockPattern($handler,$pattern,$name){if(empty($this->allowed[$name]))return;$this->blockPatterns[$name]=array('handler'=>$handler,'pattern'=>$pattern.'m',);}public
function
registerDocType($handler,$name){if(empty($this->allowed[$name]))return;$this->docTypes[$name]=$handler;}protected
function
init(){if($this->handler&&!is_object($this->handler))throw
new
Exception('$texy->handler must be object. See documentation.');$this->_paragraphMode=TRUE;$this->marks=array();if(is_array($this->allowedClasses))$this->_classes=array_flip($this->allowedClasses);else$this->_classes=$this->allowedClasses;if(is_array($this->allowedStyles))$this->_styles=array_flip($this->allowedStyles);else$this->_styles=$this->allowedStyles;foreach($this->modules
as$module)$module->init();}public
function
process($text,$singleLine=FALSE){if($singleLine)$this->parseLine($text);else$this->parse($text);return$this->toHtml();}public
function
parse($text){$this->init();if(strcasecmp($this->encoding,'utf-8')!==0)$text=iconv($this->encoding,'utf-8',$text);$text=self::wash($text);$text=str_replace("\r\n","\n",$text);$text=strtr($text,"\r","\n");while(strpos($text,"\t")!==FALSE)$text=preg_replace_callback('#^(.*)\t#mU',create_function('$matches',"return \$matches[1] . str_repeat(' ', $this->tabWidth - strlen(\$matches[1]) % $this->tabWidth);"),$text);$text=preg_replace('#\xC2\xA7{2,}(?!\xC2\xA7).*(\xC2\xA7{2,}|$)(?!\xC2\xA7)#mU','',$text);$text=preg_replace("#[\t ]+$#m",'',$text);foreach($this->modules
as$module)$text=$module->preProcess($text);$this->DOM=TexyHtml::el();$this->DOM->parseDocument($this,$text);if(is_callable(array($this->handler,'afterParse')))$this->handler->afterParse($this,$this->DOM,FALSE);$this->docTypes=$this->linePatterns=$this->blockPatterns=array();}public
function
parseLine($text){$this->init();if(strcasecmp($this->encoding,'utf-8')!==0)$text=iconv($this->encoding,'utf-8',$text);$text=self::wash($text);$text=str_replace("\r\n","\n",$text);$text=strtr($text,"\r","\n");$this->DOM=TexyHtml::el();$this->DOM->parseLine($this,$text);if(is_callable(array($this->handler,'afterParse')))$this->handler->afterParse($this,$this->DOM,TRUE);$this->docTypes=$this->linePatterns=$this->blockPatterns=array();}public
function
toHtml(){if(!$this->DOM)throw
new
Exception('Call $texy->parse() first.');$html=$this->export($this->DOM);if(!defined('TEXY_NOTICE_SHOWED')){$html.="\n<!-- by Texy2! -->";define('TEXY_NOTICE_SHOWED',TRUE);}if(strcasecmp($this->encoding,'utf-8')!==0){$this->_chars=&self::$charTables[strtolower($this->encoding)];if(!$this->_chars){for($i=128;$i<256;$i++){$ch=iconv($this->encoding,'UTF-8//IGNORE',chr($i));if($ch)$this->_chars[$ch]=chr($i);}}$html=preg_replace_callback('#[\x80-\x{FFFF}]#u',array($this,'utfconv'),$html);}return$html;}public
function
toText(){if(!$this->DOM)throw
new
Exception('Call $texy->parse() first.');$save=$this->formatter->lineWrap;$this->formatter->lineWrap=FALSE;$html=$this->export($this->DOM);$this->formatter->lineWrap=$save;$html=preg_replace('#<(script|style)(.*)</\\1>#Uis','',$html);$html=strip_tags($html);$html=preg_replace('#\n\s*\n\s*\n[\n\s]*\n#',"\n\n",$html);$html=Texy::decode($html);$html=strtr($html,array("\xC2\xAD"=>'',"\xC2\xA0"=>' ',));if(strcasecmp($this->encoding,'utf-8')!==0)$html=iconv('utf-8',$this->encoding.'//TRANSLIT',$html);return$html;}public
function
export($el){$s=$el->export($this);$s=self::decode($s);$blocks=explode(self::CONTENT_BLOCK,$s);foreach($this->modules
as$module){if($module
instanceof
ITexyLineModule){foreach($blocks
as$n=>$s){if($n
%
2===0&&$s!=='')$blocks[$n]=$module->linePostProcess($s);}}}$s=implode(self::CONTENT_BLOCK,$blocks);$s=self::encode($s);$s=$this->unProtect($s);$s=$this->wellForm->process($s);$s=$this->formatter->process($s);if(!self::$xhtml)$html=preg_replace('#\\s*</(colgroup|dd|dt|li|option|p|td|tfoot|th|thead|tr)>#','',$html);$s=self::unfreezeSpaces($s);return$s;}public
function
safeMode(){$this->allowedClasses=self::NONE;$this->allowedStyles=self::NONE;$this->allowedTags=array('a'=>array('href'),'acronym'=>array('title'),'b'=>array(),'br'=>array(),'cite'=>array(),'code'=>array(),'em'=>array(),'i'=>array(),'strong'=>array(),'sub'=>array(),'sup'=>array(),'q'=>array(),'small'=>array(),);$this->allowed['image']=FALSE;$this->allowed['link/definition']=FALSE;$this->linkModule->forceNoFollow=TRUE;}public
function
trustMode(){$this->allowedClasses=self::ALL;$this->allowedStyles=self::ALL;$this->allowedTags=array_merge(self::$blockTags,self::$inlineTags);$this->allowed['image']=TRUE;$this->allowed['link/definition']=TRUE;$this->linkModule->forceNoFollow=FALSE;}static
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
protect($child,$contentType=self::CONTENT_BLOCK){if($child==='')return'';$key=$contentType.strtr(base_convert(count($this->marks),10,8),'01234567',"\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F").$contentType;$this->marks[$key]=$child;return$key;}public
function
unProtect($html){return
strtr($html,$this->marks);}static
public
function
completeURL($URL,$root,&$isAbsolute=NULL){if(preg_match('#^(https?://|ftp://|www\\.|ftp\\.|/)#i',$URL)){$isAbsolute=TRUE;$URL=str_replace('&amp;','&',$URL);if(strncasecmp($URL,'www.',4)===0)return'http://'.$URL;elseif(strncasecmp($URL,'ftp.',4)===0)return'ftp://'.$URL;return$URL;}$isAbsolute=FALSE;if($root==NULL)return$URL;return
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
getDocTypes(){return$this->docTypes;}static
private$charTables;private$_chars;private
function
utfconv($m){$m=$m[0];if(isset($this->_chars[$m]))return$this->_chars[$m];$ch1=ord($m[0]);$ch2=ord($m[1]);if(($ch2>>6)!==2)return'';if(($ch1&0xE0)===0xC0)return'&#'.((($ch1&0x1F)<<6)+($ch2&0x3F)).';';if(($ch1&0xF0)===0xE0){$ch3=ord($m[2]);if(($ch3>>6)!==2)return'';return'&#'.((($ch1&0xF)<<12)+(($ch2&0x3F)<<06)+(($ch3&0x3F))).';';}return'';}public
function
free(){foreach(array_keys(get_object_vars($this))as$key)$this->$key=NULL;}function
__get($nm){throw
new
Exception("Undefined property '".get_class($this)."::$$nm'");}function
__set($nm,$val){$this->__get($nm);}}?>