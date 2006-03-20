/*
Copyright Scand LLC http://www.scbr.com
This version of Software is free for using in non-commercial applications. 
For commercial use please contact info@scbr.com to obtain license
*/ 
function dtmlXMLLoaderObject(funcObject,dhtmlObject,async){
 this.xmlDoc="";
 if(arguments.length==2)
 this.async=true;
 else
 this.async=async;
 this.onloadAction=funcObject||null;
 this.mainObject=dhtmlObject||null;
 return this;
};
 
 dtmlXMLLoaderObject.prototype.waitLoadFunction=function(dhtmlObject){
 this.check=function(){
 if(dhtmlObject.onloadAction!=null){
 if(!dhtmlObject.xmlDoc.readyState)dhtmlObject.onloadAction(dhtmlObject.mainObject);
 else{
 if(dhtmlObject.xmlDoc.readyState != 4)return false;
 else dhtmlObject.onloadAction(dhtmlObject.mainObject,null,null,null,dhtmlObject);}
}
};
 return this.check;
};
 
 
 dtmlXMLLoaderObject.prototype.getXMLTopNode=function(tagName){
 if(this.xmlDoc.responseXML){var temp=this.xmlDoc.responseXML.getElementsByTagName(tagName);var z=temp[0];}
 else var z=this.xmlDoc.documentElement;
 if(z)return z;
 dhtmlxError.throwError("LoadXML","Incorrect XML",[this.xmlDoc])
 return document.createElement("DIV");
};
 
 
 dtmlXMLLoaderObject.prototype.loadXMLString=function(xmlString){
 try 
{
 var parser = new DOMParser();
 this.xmlDoc = parser.parseFromString(xmlString,"text/xml");
}
 catch(e){
 this.xmlDoc = new ActiveXObject("Microsoft.XMLDOM");
 this.xmlDoc.async=this.async
 this.xmlDoc.loadXML(xmlString);
}
 this.onloadAction(this.mainObject);
}
 dtmlXMLLoaderObject.prototype.loadXML=function(filePath){
 try 
{
 this.xmlDoc = new XMLHttpRequest();
 this.xmlDoc.open("GET",filePath,this.async);
 this.xmlDoc.onreadystatechange=new this.waitLoadFunction(this);
 this.xmlDoc.send(null);
}
 catch(e){

 if(document.implementation && document.implementation.createDocument)
{
 this.xmlDoc = document.implementation.createDocument("","",null);
 this.xmlDoc.onload = new this.waitLoadFunction(this);
}
 else
{
 this.xmlDoc = new ActiveXObject("Microsoft.XMLDOM");
 this.xmlDoc.async=this.async
 this.xmlDoc.onreadystatechange=new this.waitLoadFunction(this);
}
 this.xmlDoc.load(filePath);
}
};
 
 
function callerFunction(funcObject,dhtmlObject){
 this.handler=function(e){
 if(!e)e=event;
 funcObject(e,dhtmlObject);
 return true;
};
 return this.handler;
};

 
function getAbsoluteLeft(htmlObject){
 var xPos = htmlObject.offsetLeft;
 var temp = htmlObject.offsetParent;
 while(temp != null){
 xPos+= temp.offsetLeft;
 temp = temp.offsetParent;
}
 return xPos;
}
 
function getAbsoluteTop(htmlObject){
 var yPos = htmlObject.offsetTop;
 var temp = htmlObject.offsetParent;
 while(temp != null){
 yPos+= temp.offsetTop;
 temp = temp.offsetParent;
}
 return yPos;
}
 
 
 
function convertStringToBoolean(inputString){if(typeof(inputString)=="string")inputString=inputString.toLowerCase();
 switch(inputString){
 case "1":
 case "true":
 case "yes":
 case "y":
 case 1: 
 case true: 
 return true;
 break;
 default: return false;
}
}

 
function getUrlSymbol(str){
 if(str.indexOf("?")!=-1)
 return "&"
 else
 return "?"
}
 
 
function dhtmlDragAndDropObject(){
 this.lastLanding=0;
 this.dragNode=0;
 this.dragStartNode=0;
 this.dragStartObject=0;
 this.tempDOMU=null;
 this.tempDOMM=null;
 this.waitDrag=0;
 if(window.dhtmlDragAndDrop)return window.dhtmlDragAndDrop;
 window.dhtmlDragAndDrop=this;

 return this;
};
 
 dhtmlDragAndDropObject.prototype.removeDraggableItem=function(htmlNode){
 htmlNode.onmousedown=null;
 htmlNode.dragStarter=null;
 htmlNode.dragLanding=null;
}
 dhtmlDragAndDropObject.prototype.addDraggableItem=function(htmlNode,dhtmlObject){
 htmlNode.onmousedown=this.preCreateDragCopy;
 htmlNode.dragStarter=dhtmlObject;
 this.addDragLanding(htmlNode,dhtmlObject);
}
 dhtmlDragAndDropObject.prototype.addDragLanding=function(htmlNode,dhtmlObject){
 htmlNode.dragLanding=dhtmlObject;
}
 dhtmlDragAndDropObject.prototype.preCreateDragCopy=function(e)
{
 if(window.dhtmlDragAndDrop.waitDrag){
 window.dhtmlDragAndDrop.waitDrag=0;
 document.body.onmouseup=window.dhtmlDragAndDrop.tempDOMU;
 document.body.onmousemove=window.dhtmlDragAndDrop.tempDOMM;
 return false;
}

 window.dhtmlDragAndDrop.waitDrag=1;
 window.dhtmlDragAndDrop.tempDOMU=document.body.onmouseup;
 window.dhtmlDragAndDrop.tempDOMM=document.body.onmousemove;
 window.dhtmlDragAndDrop.dragStartNode=this;
 window.dhtmlDragAndDrop.dragStartObject=this.dragStarter;
 document.body.onmouseup=window.dhtmlDragAndDrop.preCreateDragCopy;
 document.body.onmousemove=window.dhtmlDragAndDrop.callDrag;

 if((e)&&(e.preventDefault)){e.preventDefault();return false;}
 return false;
};
 dhtmlDragAndDropObject.prototype.callDrag=function(e){
 if(!e)e=window.event;
 dragger=window.dhtmlDragAndDrop;

 if((e.button==0)&&(isIE()))return dragger.stopDrag();
 if(!dragger.dragNode){
 dragger.dragNode=dragger.dragStartObject._createDragNode(dragger.dragStartNode);
 if(!dragger.dragNode)return dragger.stopDrag();
 dragger.gldragNode=dragger.dragNode;
 document.body.appendChild(dragger.dragNode);
 document.body.onmouseup=dragger.stopDrag;
 dragger.waitDrag=0;
 dragger.dragNode.pWindow=window;
 dragger.initFrameRoute();
}


 if(dragger.dragNode.parentNode!=window.document.body){
 var grd=dragger.gldragNode;
 if(dragger.gldragNode.old)grd=dragger.gldragNode.old;

 
 grd.parentNode.removeChild(grd);
 var oldBody=dragger.dragNode.pWindow;
 if(isIE()){
 var div=document.createElement("Div");
 div.innerHTML=dragger.dragNode.outerHTML;
 dragger.dragNode=div.childNodes[0];}
 else dragger.dragNode=dragger.dragNode.cloneNode(true);
 dragger.dragNode.pWindow=window;
 dragger.gldragNode.old=dragger.dragNode;
 document.body.appendChild(dragger.dragNode);
 oldBody.dhtmlDragAndDrop.dragNode=dragger.dragNode;
}
 dragger.dragNode.style.left=e.clientX+15+(dragger.fx?dragger.fx*(-1):0)+document.body.scrollLeft+"px";
 dragger.dragNode.style.top=e.clientY+3+(dragger.fy?(-1)*dragger.fy:0)+document.body.scrollTop+"px";
 if(!e.srcElement)var z=e.target;else z=e.srcElement;
 dragger.checkLanding(z,e.clientX,e.clientY);
}
 
 dhtmlDragAndDropObject.prototype.calculateFramePosition=function(n){
 
 if(window.name){
 var el =parent.frames[window.name].frameElement.offsetParent;
 var fx=0;
 var fy=0;
 while(el){fx+= el.offsetLeft;fy+= el.offsetTop;el = el.offsetParent;}
 if((parent.dhtmlDragAndDrop)){var ls=parent.dhtmlDragAndDrop.calculateFramePosition(1);fx+=ls.split('_')[0]*1;fy+=ls.split('_')[1]*1;}
 if(n)return fx+"_"+fy;
 else this.fx=fx;this.fy=fy;
}
 return "0_0";
}
 dhtmlDragAndDropObject.prototype.checkLanding=function(htmlObject,x,y){

 if((htmlObject)&&(htmlObject.dragLanding)){if(this.lastLanding)this.lastLanding.dragLanding._dragOut(this.lastLanding);
 this.lastLanding=htmlObject;this.lastLanding=this.lastLanding.dragLanding._dragIn(this.lastLanding,this.dragStartNode,x,y);}
 else{
 if((htmlObject)&&(htmlObject.tagName!="BODY"))this.checkLanding(htmlObject.parentNode,x,y);
 else{if(this.lastLanding)this.lastLanding.dragLanding._dragOut(this.lastLanding,x,y);this.lastLanding=0;}
}
}
 dhtmlDragAndDropObject.prototype.stopDrag=function(e,mode){
 dragger=window.dhtmlDragAndDrop;
 if(!mode)
{
 dragger.stopFrameRoute();
 var temp=dragger.lastLanding;
 dragger.lastLanding=null;
 if(temp)temp.dragLanding._drag(dragger.dragStartNode,dragger.dragStartObject,temp);
}
 dragger.lastLanding=null;
 if((dragger.dragNode)&&(dragger.dragNode.parentNode==document.body))dragger.dragNode.parentNode.removeChild(dragger.dragNode);
 dragger.dragNode=0;
 dragger.gldragNode=0;
 dragger.fx=0;
 dragger.fy=0;
 dragger.dragStartNode=0;
 dragger.dragStartObject=0;
 document.body.onmouseup=dragger.tempDOMU;
 document.body.onmousemove=dragger.tempDOMM;
 dragger.tempDOMU=null;
 dragger.tempDOMM=null;
 dragger.waitDrag=0;
}
 
 dhtmlDragAndDropObject.prototype.stopFrameRoute=function(win){
 if(win)
 window.dhtmlDragAndDrop.stopDrag(1,1);
 
 for(var i=0;i<window.frames.length;i++)
 if((window.frames[i]!=win)&&(window.frames[i].dhtmlDragAndDrop))
 window.frames[i].dhtmlDragAndDrop.stopFrameRoute(window);
 if((parent.dhtmlDragAndDrop)&&(parent!=window)&&(parent!=win))
 parent.dhtmlDragAndDrop.stopFrameRoute(window);
}
 dhtmlDragAndDropObject.prototype.initFrameRoute=function(win,mode){
 if(win){


 window.dhtmlDragAndDrop.preCreateDragCopy();
 window.dhtmlDragAndDrop.dragStartNode=win.dhtmlDragAndDrop.dragStartNode;
 window.dhtmlDragAndDrop.dragStartObject=win.dhtmlDragAndDrop.dragStartObject;
 window.dhtmlDragAndDrop.dragNode=win.dhtmlDragAndDrop.dragNode;
 window.dhtmlDragAndDrop.gldragNode=win.dhtmlDragAndDrop.dragNode;
 window.document.body.onmouseup=window.dhtmlDragAndDrop.stopDrag;
 window.waitDrag=0;
 if((!isIE())&&(mode))window.dhtmlDragAndDrop.calculateFramePosition();
}
 if((parent.dhtmlDragAndDrop)&&(parent!=window)&&(parent!=win))
 parent.dhtmlDragAndDrop.initFrameRoute(window);
 for(var i=0;i<window.frames.length;i++)
 if((window.frames[i]!=win)&&(window.frames[i].dhtmlDragAndDrop))
 window.frames[i].dhtmlDragAndDrop.initFrameRoute(window,((!win||mode)?1:0));

}

var _isFF=false;var _isIE=false;var _isOpera=false;var _isSafari=false;
if(navigator.userAgent.indexOf('Safari')!= -1 || navigator.userAgent.indexOf('Konqueror')!= -1)
 _isSafari=true;
else if(navigator.userAgent.indexOf('Opera')!= -1)
 _isOpera=true;
else if(navigator.appName.indexOf("Microsoft")!=-1)
 _isIE=true;
else _isFF=true;

 
 
function isIE(){
 if(navigator.appName.indexOf("Microsoft")!=-1)
 if(navigator.userAgent.indexOf('Opera')== -1)
 return true;
 return false;
}

 
dtmlXMLLoaderObject.prototype.doXPath = function(xpathExp,docObj){
 if((_isOpera)||(_isSafari))return this.doXPathOpera(xpathExp,docObj);
 if(isIE()){
 if(arguments.length==1){
 docObj = this.xmlDoc
}
 return docObj.selectNodes(xpathExp);
}else{
 var nodeObj = docObj;
 if(!docObj){
 if(!this.xmlDoc.nodeName){
 docObj = this.xmlDoc.responseXML
}else{
 docObj = this.xmlDoc;
}
}
 if(docObj.nodeName.indexOf("document")!=-1){
 nodeObj = docObj;
}else{
 nodeObj = docObj;
 docObj = docObj.ownerDocument;

}
 var rowsCol = new Array();
 var col = docObj.evaluate(xpathExp,nodeObj,null,XPathResult.ANY_TYPE,null);
 var thisColMemb = col.iterateNext();
 while(thisColMemb){
 rowsCol[rowsCol.length] = thisColMemb;
 thisColMemb = col.iterateNext();
}
 return rowsCol;
}
}
 
if((window.Node)&&(!_isSafari))
Node.prototype.removeNode = function(removeChildren)
{
 var self = this;
 if(Boolean(removeChildren))
{
 return this.parentNode.removeChild(self);
}
 else
{
 var range = document.createRange();
 range.selectNodeContents(self);
 return this.parentNode.replaceChild(range.extractContents(),self);
}
}

function _dhtmlxError(type,name,params){
 if(!this.catches)
 this.catches=new Array();

 return this;
}

_dhtmlxError.prototype.catchError=function(type,func_name){
 this.catches[type]=func_name;
}
_dhtmlxError.prototype.throwError=function(type,name,params){
 if(this.catches[type])return this.catches[type](type,name,params);
 if(this.catches["ALL"])return this.catches["ALL"](type,name,params);
 alert("Error type: "+arguments[0]+"\nDescription: "+arguments[1]);
 return null;
}

window.dhtmlxError=new _dhtmlxError();


 
 
dtmlXMLLoaderObject.prototype.doXPathOpera = function(xpathExp,docObj){
 
 var z=xpathExp.replace(/[\/]+/gi,"/").split('/');
 var obj=null;
 var i=1;

 if(!z.length)return [];
 if(z[0]==".")
 obj=[docObj];
 else if(z[0]=="")
{
 obj=this.xmlDoc.responseXML.getElementsByTagName(z[i]);
 i++;
}
 else return [];

 for(i;i<z.length;i++)
 obj=this._getAllNamedChilds(obj,z[i]);

 return obj;
}

dtmlXMLLoaderObject.prototype._getAllNamedChilds = function(a,b){
 var c=new Array();
 for(var i=0;i<a.length;i++)
 for(var j=0;j<a[i].childNodes.length;j++)
 if(a[i].childNodes[j].tagName==b)c[c.length]=a[i].childNodes[j];

 return c;
}


