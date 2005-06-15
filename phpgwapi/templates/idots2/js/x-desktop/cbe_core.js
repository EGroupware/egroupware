/* cbe_core.js $Revision$
 * CBE v4.19, Cross-Browser DHTML API from Cross-Browser.com
 * Copyright (c) 2002 Michael Foster (mike@cross-browser.com)
 * Distributed under the terms of the GNU LGPL from gnu.org
*/
var cbeVersion="4.19", cbeDocumentId='idDocument', cbeWindowId='idWindow', cbeAll=new Array();
window.onload=function(){cbeInitialize("DIV", "SPAN"); if (window.windowOnload) window.windowOnload();}
window.onunload=function(){if(window.windowOnunload){window.windowOnunload();}if(window.cbeDebugObj){window.cbeDebugObj=null;}for(var i=0; i<cbeAll.length; i++){if(cbeAll[i]){if(cbeAll[i].ele){if(cbeAll[i].ele.cbe){cbeAll[i].ele.cbe=null;}cbeAll[i].ele=null;}cbeAll[i]=null;}}}
function CrossBrowserNode(){}
CrossBrowserNode.prototype.appendNode=function(cbeChild){if (cbeChild){if (!this.firstChild){this.firstChild=cbeChild;} else{cbeChild.previousSibling=this.lastChild; this.lastChild.nextSibling=cbeChild;}cbeChild.parentNode=this; this.lastChild=cbeChild; ++this.childNodes;}return cbeChild;}
CrossBrowserElement.prototype=new CrossBrowserNode;
function CrossBrowserElement(){
  this.contains=this.left=this.top=this.offsetLeft=this.offsetTop=this.pageX=this.pageY=this.zIndex=_retZero;
  this.show=this.hide=this.moveTo=this.moveBy=this.sizeTo=this.sizeBy=this.resizeTo=this.resizeBy=_retVoid;
  this.visibility=this.color=this.background=this.clip=this.innerHtml=_retEStr;
  if (cbeAll.length < 2){this.width=cbeInnerWidth; this.height=cbeInnerHeight; this.scrollLeft=cbePageXOffset; this.scrollTop=cbePageYOffset;}
  else{this.width=this.height=this.scrollLeft=this.scrollTop=_retZero;}
  this.id=""; this.index=cbeAll.length; cbeAll[this.index]=this; this.w=this.h=0; this.x=this.y=0;
  if (window.cbeEventJsLoaded) this.listeners=new Array();
}
function cbeBindElement(cbe, ele){
  if (!cbe || !ele) return;
  cbe.ele=ele; cbe.ele.cbe=cbe; cbe.parentElement=cbeGetParentElement(ele);
  if (ele==window){cbe.id=ele.id=cbeWindowId; return;} else if (ele==document){cbe.id=ele.id=cbeDocumentId; return;} else{cbe.id=ele.id;}
  if (_def(ele.clip)){cbe.w=ele.clip.width; cbe.h=ele.clip.height;}
  var css=_def(ele.style);
  // left, top
  cbe.moveTo=_cbeMoveTo; cbe.moveBy=_cbeMoveBy; if (css && _def(ele.style.left, ele.style.top) && typeof(ele.style.left)=="string"){cbe.left=_domLeft; cbe.top=_domTop;}else if (css && _def(ele.style.pixelLeft, ele.style.pixelTop)){cbe.left=_ieLeft; cbe.top=_ieTop;}else if (_def(ele.left, ele.top)){cbe.left=_nnLeft; cbe.top=_nnTop;}else{_sup(false,"left","top","moveTo","moveBy");}
  // width, height
  cbe.sizeTo=_cbeSizeTo; cbe.sizeBy=_cbeSizeBy; cbe.resizeTo=_cbeResizeTo; cbe.resizeBy=_cbeResizeBy; if (css && _def(ele.style.width, ele.style.height, ele.offsetWidth, ele.offsetHeight) && typeof(ele.style.width)=="string"){cbe.width=_domWidth; cbe.height=_domHeight;}else if (css && _def(ele.style.pixelWidth, ele.style.pixelHeight)){cbe.width=_ieWidth; cbe.height=_ieHeight;}else if (_def(ele.clip) && _def(ele.clip.width, ele.clip.height)){cbe.width=_nnWidth; cbe.height=_nnHeight;}else{_sup(false, "width","height","sizeTo","sizeBy","resizeTo","resizeBy");}
  // zIndex
  if (css && _def(ele.style.zIndex)){cbe.zIndex=_domZIndex;} else if (_def(ele.zIndex)){cbe.zIndex=_nnZIndex;} else{_sup(false,"zIndex");}
  // visibility
  cbe.show=_cbeShow; cbe.hide=_cbeHide; if (css && _def(ele.style.visibility)){cbe.visibility=_domVisibility;} else if (_def(ele.visibility)){cbe.visibility=_nnVisibility;} else{_sup(false,"visibility","show","hide");}
  // background
  if (css && _def(ele.style.backgroundColor, ele.style.backgroundImage)){cbe.background=_domBackground;} else if (_def(ele.bgColor, ele.background)){cbe.background=_nnBackground;} else{_sup(false,"background");}
  // color
  if (css && _def(ele.style.color)){cbe.color=_domColor;} else{_sup(false,"color");}
  // clip
  if (css && _def(ele.style.clip)){cbe.clip=_domClip;} else if (_def(ele.clip)){cbe.clip=_nnClip;} else{_sup(false,"clip");}
  // offsetLeft, offsetTop
  if (_def(ele.offsetLeft, ele.offsetTop, ele.offsetParent)){cbe.offsetLeft=_ieOffsetLeft; cbe.offsetTop=_ieOffsetTop;}else if (_def(ele.pageX, ele.pageY)){cbe.offsetLeft=_nnOffsetLeft; cbe.offsetTop=_nnOffsetTop;}else{_sup(false,"offsetLeft","offsetTop");}
  // pageX, pageY
  cbe.contains=_cbeContains; if (_def(ele.pageX, ele.pageY)){cbe.pageX=_nnPageX; cbe.pageY=_nnPageY;}else if (document.cbe.isSupported("offsetLeft")){cbe.pageX=_cbePageX; cbe.pageY=_cbePageY;}else{_sup(false,"pageX","pageY","contains");}
  // innerHtml
  if (_def(ele.innerHTML)){cbe.innerHtml=_ieInnerHtml;} else if (_def(ele.document) && _def(ele.document.write)){cbe.innerHtml=_nnInnerHtml;} else{_sup(false,"innerHtml");}
  // scrollLeft, scrollTop
  if (_def(ele.scrollLeft, ele.scrollTop)){cbe.scrollLeft=_cbeScrollLeft; cbe.scrollTop=_cbeScrollTop;}else{_sup(false,"scrollLeft","scrollTop");}
  // createElement, appendChild, removeChild (these need more work)
  if (!_def(document.createElement) && !document.layers){_sup(false,"createElement","appendChild","removeChild");}else{if (!_def(ele.appendChild)){_sup(false,"appendChild");} if (!_def(ele.removeChild)){_sup(false,"removeChild");}}
}
function cbeInitialize(sTagNames){
  var t,i,ele,eleList,cbe;
  cbe=new CrossBrowserElement(window);
  cbeBindElement(cbe, window);
  cbe=new CrossBrowserElement(document);
  cbeBindElement(cbe, document);
  if (!document.getElementById) document.getElementById=cbeGetElementById;
  if (document.createElement || document.layers) document.cbe.createElement=_cbeCreateElement;
  document.cbe.isSupported=_cbeIsSupported;
  document.cbe.supported=new Array();
  _sup(true,"left","top","width","height","zIndex","show","hide","visibility","background","color","clip","offsetLeft","offsetTop","pageX","pageY","innerHtml","scrollLeft","scrollTop","createElement","appendChild","removeChild","moveTo","moveBy","sizeTo","sizeBy","resizeTo","resizeBy","contains");
  for (t=0; t < arguments.length; ++t){
    eleList=cbeGetElementsByTagName(arguments[t]);
    for (i=0; i < eleList.length; ++i){
      ele=eleList[i];
      if ( ele.id && ele.id !=""){
        cbe=new CrossBrowserElement();
        cbeBindElement(cbe, ele);
     }
   }
    if (document.layers) break;
 }
  _cbeCreateTree();
  if (window.cbeEventJsLoaded && (document.layers || is.opera5or6)){window.cbe.addEventListener("resize", cbeDefaultResizeListener);}
}
function _cbeIsSupported(sMethods){var i; for (i=0; i<arguments.length; ++i){if (!document.cbe.supported[arguments[i]]) return false;}return true;}
function _sup(bValue, sMethods){var i; for (i=1; i<arguments.length; ++i) document.cbe.supported[arguments[i]]=bValue;}
function _cbeCreateTree(){var parent; for (var i=1; i < cbeAll.length; ++i){parent=cbeAll[i].parentElement; if (!parent.cbe){while (parent && !parent.cbe){parent=cbeGetParentElement(parent);}if (!parent) parent=document;}parent.cbe.appendNode(cbeAll[i]);}}
function cbeGetElementById(sId){var ele=null; if (sId==window.cbeWindowId) ele=window; else if (sId==window.cbeDocumentId) ele=document; else if (is.dom1getbyid) ele=document.getElementById(sId); else if (document.all) ele=document.all[sId]; else if (document.layers) ele=nnGetElementById(sId); if (!ele && window.cbeUtilJsLoaded){ele=cbeGetImageByName(sId); if (!ele){ele=cbeGetFormByName(sId);}} return ele;}
function nnGetElementById(sId){for (var i=0; i < cbeAll.length; i++){if ( cbeAll[i].id==sId ) return cbeAll[i].ele;}return null;}
function cbeGetElementsByTagName(sTagName){
  var eleList;
  if (document.getElementsByTagName) eleList=document.getElementsByTagName(sTagName); // standard
  else if (document.body && document.body.getElementsByTagName) eleList=document.body.getElementsByTagName(sTagName); // opera5or6
  else if (document.all && document.all.tags) eleList=document.all.tags(sTagName); // ie4
  else if (document.layers){eleList=new Array(); nnGetAllLayers(window, eleList, 0);}// nn4
  return eleList;
}
function nnGetAllLayers(parent, layerArray, nextIndex){
  var i, layer;
  for (i=0; i < parent.document.layers.length; i++){
    layer=parent.document.layers[i]; layerArray[nextIndex++]=layer;
    if (layer.document.layers.length) nextIndex=nnGetAllLayers(layer, layerArray, nextIndex);
 }
  return nextIndex;
}
function cbeGetParentElement(child){
  var parent=document;
  if (child==window) parent=null;
  else if (child==document) parent=window;
  else if (child.parentLayer){if (child.parentLayer !=window) parent=child.parentLayer;}
  else{
    if (child.parentNode) parent=child.parentNode;
    else if (child.offsetParent) parent=child.offsetParent;
    else if (child.parentElement) parent=child.parentElement;
 }
  return parent;
}
function _def(){var i; for (i=0; i<arguments.length; ++i){if (typeof(arguments[i])=="" || typeof(arguments[i])=="undefined") return false;}return true;}
function _retZero(){return 0;}
function _retNull(){return null;}
function _retEStr(){return "";}
function _retVoid(){}
////// when optimizing, don't remove anything above this comment //////
function _cbeCreateElement(sEleType){// returns an Element object
  var ele=null;
  if (document.createElement && sEleType.length){
    ele=document.createElement(sEleType);
    if (ele && ele.style){ele.style.position="absolute";}
 }
  else if (document.layers){
    ele=new Object();
 }
  return ele;
}
CrossBrowserNode.prototype.appendChild=function(eleChild){// returns the appended Element object on success
  var cbe, ele, rv=null;
  if (document.layers){
    var thisEle;
    if (this.index < 2) thisEle=window;
    else thisEle=this.ele;
    ele=new Layer(this.width(), thisEle);
    if (ele){
      if (eleChild.id) ele.id=ele.name=eleChild.id;
      cbe=new CrossBrowserElement();
      cbeBindElement(cbe, ele);
      this.appendNode(ele.cbe);
      eleChild.cbe=cbe;
      ++this.childNodes;
      rv=ele;
   }
 }
  else{
    if (this.index < 2) ele=document.body;
    else ele=this.ele;
    if (ele.appendChild){
      ele.appendChild(eleChild);
      cbe=new CrossBrowserElement();
      cbeBindElement(cbe, eleChild);
      this.appendNode(eleChild.cbe);
      ++this.childNodes;
      rv=eleChild;
   }
 }
  return rv;
}
CrossBrowserNode.prototype.removeChild=function(eleChild){
  var ele, rv=null;
  if (this.index < 2) ele=document.body;
  else ele=this.ele;
  if (ele.removeChild || document.layers){
    --this.childNodes;
    var prevSib=eleChild.cbe.previousSibling;
    var nextSib=eleChild.cbe.nextSibling;
    with (eleChild.cbe){
      parentNode=null;
      previousSibling=null;
      nextSibling=null;
   }
    if (prevSib) prevSib.nextSibling=nextSib;
    else this.firstChild=nextSib;
    if (nextSib) nextSib.previousSibling=prevSib;
    else this.lastChild=prevSib;
    if (document.layers){
      //// working on it
   }
    else{
      ele.removeChild(eleChild);
   }
    rv=eleChild;
 }
  return rv;
}
function _cbeContains(iLeft, iTop, iClipTop, iClipRight, iClipBottom, iClipLeft){if (arguments.length==2){iClipTop=iClipRight=iClipBottom=iClipLeft=0;} else if (arguments.length==3){iClipRight=iClipBottom=iClipLeft=iClipTop;} else if (arguments.length==4){iClipLeft=iClipRight; iClipBottom=iClipTop;} var thisX=this.pageX(), thisY=this.pageY(); return ( iLeft >=thisX + iClipLeft && iLeft <=thisX + this.width() - iClipRight && iTop >=thisY + iClipTop && iTop <=thisY + this.height() - iClipBottom );}
function _cbeMoveTo(x_cr, y_mar, outside, xEndL){if (isFinite(x_cr)){this.left(x_cr); this.top(y_mar);}else{this.cardinalPosition(x_cr, y_mar, outside); this.left(this.x); this.top(this.y);}if (xEndL) cbeEval(xEndL, this);}
function _cbeMoveBy(uDX, uDY, xEndL){if (uDX){this.left(this.left() + uDX);}  if (uDY){this.top(this.top() + uDY);} if (xEndL){cbeEval(xEndL, this);}}
//function _domLeft(iX){if (arguments.length){if (! ("" + iX).match(/^\d+/) ) iX=0; this.ele.style.left=iX + "px";} else{iX=parseInt(this.ele.style.left); if (isNaN(iX)) iX=0;}return iX;}
function _domLeft(iX){if (arguments.length){this.ele.style.left=iX + "px";} else{iX=parseInt(this.ele.style.left); if (isNaN(iX)) iX=0;}return iX;}
function _ieLeft(iX){if (arguments.length){this.ele.style.pixelLeft=iX;} else{iX=this.ele.style.pixelLeft;} return iX;}
function _nnLeft(iX){if (arguments.length){this.ele.left=iX;} else{iX=this.ele.left;} return iX;}
function _domTop(iY){if (arguments.length){this.ele.style.top=(iY && iY != "") ? iY + "px" : 0 + "px";} else{iY=parseInt(this.ele.style.top); if (isNaN(iY)) iY=0;}return iY;}
function _ieTop(iY){if (arguments.length){this.ele.style.pixelTop=iY;} else{iY=this.ele.style.pixelTop;} return iY;}
function _nnTop(iY){if (arguments.length){this.ele.top=iY;} else{iY=this.ele.top;} return iY;}
function _nnOffsetLeft(){var ol=this.ele.pageX - this.parentElement.pageX; if (isNaN(ol)){ol=this.ele.pageX;} return ol;}
function _nnOffsetTop(){var ot=this.ele.pageY - this.parentElement.pageY; if (isNaN(ot)){ot=this.ele.pageY;} return ot;}
function _ieOffsetLeft(){var x=this.ele.offsetLeft, parent=this.ele.offsetParent; while(parent && !parent.cbe){x +=parent.offsetLeft; parent=parent.offsetParent;}return x;}
function _ieOffsetTop(){var y=this.ele.offsetTop, parent=this.ele.offsetParent; while(parent && !parent.cbe){y +=parent.offsetTop; parent=parent.offsetParent;}return y;}
function _nnPageX(){return this.ele.pageX;}
function _nnPageY(){return this.ele.pageY;}
function _cbePageX(){var x=this.offsetLeft(), parent=this.parentNode; if (parent){while(parent.index > 1){x +=parent.offsetLeft(); parent=parent.parentNode;}} return x;}
function _cbePageY(){var y=this.offsetTop(), parent=this.parentNode; if (parent){while(parent.index > 1){y +=parent.offsetTop(); parent=parent.parentNode;}} return y;}
function _cbeSizeTo(uW, uH){this.width(uW); this.height(uH);}
function _cbeSizeBy(iDW, iDH){this.width(this.width() + iDW); this.height(this.height() + iDH);}
function _cbeResizeTo(uW, uH, xEndListener){this.sizeTo(uW, uH); this.clip('auto'); cbeEval(xEndListener, this);}
function _cbeResizeBy(iDW, iDH, xEndListener){this.sizeBy(iDW, iDH); this.clip('auto'); cbeEval(xEndListener, this);}
function _domWidth(uW){if (arguments.length){uW=Math.round(uW); _domSetWidth(this.ele, uW);}return this.ele.offsetWidth;}
function _ieWidth(uW){if (arguments.length){uW=Math.round(uW); this.ele.style.pixelWidth=uW;}return this.ele.style.pixelWidth;}
function _nnWidth(uW){if (arguments.length){this.w=Math.round(uW); this.ele.clip.right=this.w;}return this.w;}
function _domHeight(uH){if (arguments.length){uH=Math.round(uH); _domSetHeight(this.ele, uH);}return this.ele.offsetHeight;}
function _ieHeight(uH){if (arguments.length){uH=Math.round(uH); this.ele.style.pixelHeight=uH;}return this.ele.style.pixelHeight;}
function _nnHeight(uH){if (arguments.length){this.h=Math.round(uH); this.ele.clip.bottom=this.h;}return this.h;}
function _domSetWidth(ele,uW){
  if (uW < 0) return;
  var pl=0,pr=0,bl=0,br=0;
  if (_def(document.defaultView) && _def(document.defaultView.getComputedStyle)){// gecko and standard
    pl=parseInt(document.defaultView.getComputedStyle(ele, "").getPropertyValue("padding-left"));
    pr=parseInt(document.defaultView.getComputedStyle(ele, "").getPropertyValue("padding-right"));
    bl=parseInt(document.defaultView.getComputedStyle(ele, "").getPropertyValue("border-left-width"));
    br=parseInt(document.defaultView.getComputedStyle(ele, "").getPropertyValue("border-right-width"));
 }
  else if (_def(ele.currentStyle, document.compatMode)){
    if (document.compatMode=="CSS1Compat"){// ie6up in css1compat mode
      pl=parseInt(ele.currentStyle.paddingLeft);
      pr=parseInt(ele.currentStyle.paddingRight);
      bl=parseInt(ele.currentStyle.borderLeftWidth);
      br=parseInt(ele.currentStyle.borderRightWidth);
   }
 }
  if (isNaN(pl)) pl=0; if (isNaN(pr)) pr=0; if (isNaN(bl)) bl=0; if (isNaN(br)) br=0;
  var cssW=uW-(pl+pr+bl+br);
  if (isNaN(cssW) || cssW < 0) return;
  ele.style.width=cssW + "px";
}
function _domSetHeight(ele,uH){
  if (uH < 0) return;
  var pt=0,pb=0,bt=0,bb=0;
  if (_def(document.defaultView) && _def(document.defaultView.getComputedStyle)){
    pt=parseInt(document.defaultView.getComputedStyle(ele, "").getPropertyValue("padding-top"));
    pb=parseInt(document.defaultView.getComputedStyle(ele, "").getPropertyValue("padding-bottom"));
    bt=parseInt(document.defaultView.getComputedStyle(ele, "").getPropertyValue("border-top-width"));
    bb=parseInt(document.defaultView.getComputedStyle(ele, "").getPropertyValue("border-bottom-width"));
 }
  else if (_def(ele.currentStyle, document.compatMode)){
    if (document.compatMode=="CSS1Compat"){
      pt=parseInt(ele.currentStyle.paddingTop);
      pb=parseInt(ele.currentStyle.paddingBottom);
      bt=parseInt(ele.currentStyle.borderTopWidth);
      bb=parseInt(ele.currentStyle.borderBottomWidth);
   }
 }
  if (isNaN(pt)) pt=0; if (isNaN(pb)) pb=0; if (isNaN(bt)) bt=0; if (isNaN(bb)) bb=0;
  var cssH=uH-(pt+pb+bt+bb);
  if (isNaN(cssH) || cssH < 0) return;
  ele.style.height=cssH + "px";
}
function _cbeScrollLeft(){return this.ele.scrollLeft;}
function _cbeScrollTop(){return this.ele.scrollTop;}
function _cbeShow(){this.visibility(1);}
function _cbeHide(){this.visibility(0);}
function _domVisibility(vis){if (arguments.length){if (vis){this.ele.style.visibility='inherit';} else{this.ele.style.visibility='hidden';}}else return (this.ele.style.visibility=='visible' || this.ele.style.visibility=='inherit' || this.ele.style.visibility=='');}
function _nnVisibility(vis){if (arguments.length){if (vis){this.ele.visibility='inherit';} else{this.ele.visibility='hide';}}else return (this.ele.visibility=='show' || this.ele.visibility=='inherit' || this.ele.visibility=='');}
function _domZIndex(uZ){if (arguments.length){this.ele.style.zIndex=uZ;} else{uZ=parseInt(this.ele.style.zIndex); if (isNaN(uZ)) uZ=0;}return uZ;}
function _nnZIndex(uZ){if (arguments.length) this.ele.zIndex=uZ; return this.ele.zIndex;}
function _domBackground(sColor, sImage){if (arguments.length){if (!sColor){sColor='transparent';} this.ele.style.backgroundColor=sColor; if (arguments.length==2){ (sImage != "") ? this.ele.style.backgroundImage="url(" + sImage + ")" : this.ele.style.backgroundImage = "" }}else return this.ele.style.backgroundColor;}
function _nnBackground(sColor, sImage){if (arguments.length){if (sColor=='transparent'){sColor=null;} this.ele.bgColor=sColor; if (arguments.length==2){this.ele.background.src=sImage || null;}}else{var bg=this.ele.bgColor; if (window.cbeUtilJsLoaded){bg=cbeHexString(bg,6,'#');} return bg;}}
function _domColor(newColor){if (arguments.length){this.ele.style.color=newColor;}else return this.ele.style.color;}
function _domClip(iTop, iRight, iBottom, iLeft){if (arguments.length==4){var clipRect="rect(" + iTop + "px " + iRight + "px " + iBottom + "px " + iLeft + "px" + ")"; this.ele.style.clip=clipRect;}else{this.clip(0, this.ele.offsetWidth, this.ele.offsetHeight, 0);}}
function _nnClip(iTop, iRight, iBottom, iLeft){if (arguments.length==4){this.ele.clip.top=iTop; this.ele.clip.right=iRight; this.ele.clip.bottom=iBottom; this.ele.clip.left=iLeft;}else{this.clip(0, this.width(), this.height(), 0);}}
function _ieInnerHtml(sHtml){if (arguments.length){this.ele.innerHTML=sHtml;}else return this.ele.innerHTML;}
function _nnInnerHtml(sHtml){if (arguments.length){if (sHtml==""){sHtml=" ";} this.ele.document.open(); this.ele.document.write(sHtml); this.ele.document.close();}else return "";}
CrossBrowserElement.prototype.cardinalPosition=function(cp, margin, outside){
  if (typeof(cp) !='string'){window.status='cardinalPosition() error: cp=' + cp + ', id=' + this.id; return;}
  var x=this.left(), y=this.top(), w=this.width(), h=this.height();
  var pw=this.parentNode.width(), ph=this.parentNode.height();
  var sx=this.parentNode.scrollLeft(), sy=this.parentNode.scrollTop();
  var right=sx + pw, bottom=sy + ph;
  var cenLeft=sx + Math.floor((pw-w)/2), cenTop=sy + Math.floor((ph-h)/2);
  if (!margin) margin=0;
  else{
    if (outside) margin=-margin;
    sx +=margin; sy +=margin; right -=margin; bottom -=margin;
 }
  switch (cp.toLowerCase()){
    case 'n': x=cenLeft; if (outside) y=sy - h; else y=sy; break;
    case 'ne': if (outside){x=right; y=sy - h;}else{x=right - w; y=sy;}break;
    case 'e': y=cenTop; if (outside) x=right; else x=right - w; break;
    case 'se': if (outside){x=right; y=bottom;}else{x=right - w; y=bottom - h}break;
    case 's': x=cenLeft; if (outside) y=sy - h; else y=bottom - h; break;
    case 'sw': if (outside){x=sx - w; y=bottom;}else{x=sx; y=bottom - h;}break;
    case 'w': y=cenTop; if (outside) x=sx - w; else x=sx; break;
    case 'nw': if (outside){x=sx - w; y=sy - h;}else{x=sx; y=sy;}break;
    case 'cen': case 'center': x=cenLeft; y=cenTop; break;
    case 'cenh': x=cenLeft; break;
    case 'cenv': y=cenTop; break;
 }
  this.x=x; this.y=y;
}
function cbeInnerWidth(){
  var w=0;
  if (is.opera5or6){w=window.innerWidth;}
  else if (is.ie && document.documentElement && document.documentElement.clientWidth) w=document.documentElement.clientWidth; // ie6 compat mode
  else if (document.body && document.body.clientWidth) w=document.body.clientWidth; // ie4up and gecko
  else if (_def(window.innerWidth,window.innerHeight,document.height)){// nn4
    w=window.innerWidth;
    if (document.height > window.innerHeight) w -=16;
 }
  return w;
}
function cbeInnerHeight(){
  var h=0;
  if (is.opera5or6){h=window.innerHeight;}
  else if (is.ie && document.documentElement && document.documentElement.clientHeight) h=document.documentElement.clientHeight;
  else if (document.body && document.body.clientHeight) h=document.body.clientHeight;
  else if (_def(window.innerWidth,window.innerHeight,document.width)){
    h=window.innerHeight;
    if (document.width > window.innerWidth) h -=16;
 }
  return h;
}
function cbePageXOffset(){
  var offset=0;
  if (_def(window.pageXOffset)) offset=window.pageXOffset; // gecko, nn4, opera
  else if (document.documentElement && document.documentElement.scrollLeft) offset=document.documentElement.scrollLeft; // ie6 compat mode
  else if (document.body && _def(document.body.scrollLeft)) offset=document.body.scrollLeft; // ie4up
  return offset;
}
function cbePageYOffset(){
  var offset=0;
  if (_def(window.pageYOffset)) offset=window.pageYOffset;
  else if (document.documentElement && document.documentElement.scrollTop) offset=document.documentElement.scrollTop;
  else if (document.body && _def(document.body.scrollTop)) offset=document.body.scrollTop;
  return offset;
}
function cbeEval(exp, arg1, arg2, arg3, arg4, arg5, arg6){
  if (typeof(exp)=="function") exp(arg1, arg2, arg3, arg4, arg5, arg6);
  else if (typeof(exp)=="object" && typeof(arg1)=="function") {
    exp._cbeEval_ = arg1;
    exp._cbeEval_(arg2, arg3, arg4, arg5, arg6);
  }  
  else if (typeof(exp)=="string") eval(exp);
}
function ClientSnifferJr(){
  this.ua=navigator.userAgent.toLowerCase();
  this.major=parseInt(navigator.appVersion);
  this.minor=parseFloat(navigator.appVersion);
  if (document.addEventListener && document.removeEventListener) this.dom2events=true;
  if (document.getElementById) this.dom1getbyid=true;
  if (window.opera){
    this.opera=true;
    this.opera5=(this.ua.indexOf("opera 5") !=-1 || this.ua.indexOf("opera/5") !=-1);
    this.opera6=(this.ua.indexOf("opera 6") !=-1 || this.ua.indexOf("opera/6") !=-1);
    this.opera5or6=this.opera5 || this.opera6;
    this.opera7=(this.ua.indexOf("opera 7") !=-1 || this.ua.indexOf("opera/7") !=-1);
    return;
 }
  this.konq=this.ua.indexOf('konqueror') !=-1;
  this.ie=this.ua.indexOf('msie') !=-1;
  if (this.ie){
    this.ie3=this.major < 4;
    this.ie4=(this.major==4 && this.ua.indexOf('msie 5')==-1 && this.ua.indexOf('msie 6')==-1);
    this.ie4up=this.major >=4;
    this.ie5=(this.major==4 && this.ua.indexOf('msie 5.0') !=-1);
    this.ie5up=!this.ie3 && !this.ie4;
    this.ie6=(this.major==4 && this.ua.indexOf('msie 6.0') !=-1);
    this.ie6up=(!this.ie3 && !this.ie4 && !this.ie5 && this.ua.indexOf("msie 5.5")==-1);
    return;
 }
  this.hotjava=this.ua.indexOf('hotjava') !=-1;
  this.webtv=this.ua.indexOf('webtv') !=-1;
  this.aol=this.ua.indexOf('aol') !=-1;
  if (this.hotjava || this.webtv || this.aol) return;
  // Gecko, NN4, and NS6
  this.gecko=this.ua.indexOf('gecko') !=-1;
  this.nav=(this.ua.indexOf('mozilla') !=-1 && this.ua.indexOf('spoofer')==-1 && this.ua.indexOf('compatible')==-1);
  if (this.nav){
    this.nav4=this.major==4;
    this.nav4up=this.major >=4;
    this.nav5up=this.major >=5;
    this.nav6=this.major==5;
    this.nav6up=this.nav5up;
 }
}
window.is=new ClientSnifferJr();
// End cbe_core.js
