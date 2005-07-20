		/**  
          *     @desc: xmlLoader object
          *     @type: private
          *     @param: funcObject - xml parser function
          *     @param: object - jsControl object
		  *     @topic: 0  
          */	
function dtmlXMLLoaderObject(funcObject, dhtmlObject){
	this.xmlDoc="";
	this.onloadAction=funcObject||null;
	this.mainObject=dhtmlObject||null;
	return this;	
};
		/**  
          *     @desc: xml loading handler
          *     @type: private
          *     @param: dtmlObject - xmlLoader object
		  *     @topic: 0  
          */
	dtmlXMLLoaderObject.prototype.waitLoadFunction=function(dhtmlObject){
		this.check=function (){		
			if (!dhtmlObject.xmlDoc.readyState) dhtmlObject.onloadAction(dhtmlObject.mainObject);
			else	{ 
			if (dhtmlObject.xmlDoc.readyState != 4) return false;
				else dhtmlObject.onloadAction(dhtmlObject.mainObject);	}
		};
		return this.check;
	};
	
		/**  
          *     @desc: return XML top node
		  *     @param: tagName - top XML node tag name (not used in IE, required for Safari and Mozilla)
          *     @type: private
		  *     @returns: top XML node
		  *     @topic: 0  
          */
	dtmlXMLLoaderObject.prototype.getXMLTopNode=function(tagName){
			if (this.xmlDoc.responseXML)  { var temp=this.xmlDoc.responseXML.getElementsByTagName(tagName); var z=temp[0];  }   
			else var z=this.xmlDoc.documentElement;	
			if (z) return z;
			alert("Incorrect XML");
			return document.createElement("DIV");
	};
	
		/**  
          *     @desc: load XML
          *     @type: private
          *     @param: filePath - xml file path
		  *     @topic: 0  
          */	
	dtmlXMLLoaderObject.prototype.loadXMLString=function(xmlString){
 	 try 
	 {
		 var parser = new DOMParser();
		 this.xmlDoc = parser.parseFromString(xmlString,"text/xml");
	 }
	 catch(e){
		this.xmlDoc = new ActiveXObject("Microsoft.XMLDOM");
		this.xmlDoc.loadXML(xmlString);
	 }
	  this.onloadAction(this.mainObject);
	}
	dtmlXMLLoaderObject.prototype.loadXML=function(filePath){
	 try 
	 {
	 	this.xmlDoc = new XMLHttpRequest();
		this.xmlDoc.open("GET",filePath,true);
		this.xmlDoc.onreadystatechange=new this.waitLoadFunction(this);
		this.xmlDoc.send(null);	
	 }
	 catch(e){

    		if (document.implementation && document.implementation.createDocument)
    		{
    			this.xmlDoc = document.implementation.createDocument("", "", null);
    			this.xmlDoc.onload = new this.waitLoadFunction(this);
    		}
    		else
    		{
    			this.xmlDoc = new ActiveXObject("Microsoft.XMLDOM");
    			this.xmlDoc.async="true";
    			this.xmlDoc.onreadystatechange=new this.waitLoadFunction(this);
    		}
				this.xmlDoc.load(filePath);				
  		}
	};
	
		/**  
          *     @desc: Call wrapper
          *     @type: private
          *     @param: funcObject - action handler
          *     @param: dhtmlObject - user data
		  *     @returns: function handler
		  *     @topic: 0  
          */
function callerFunction(funcObject,dhtmlObject){
	this.handler=function(e){
		if (!e) e=event;
		funcObject(e,dhtmlObject);
		return true;
	};
	return this.handler;
};

		/**  
          *     @desc: Calculate absolute position of html object
          *     @type: private
          *     @param: htmlObject - html object
		  *     @topic: 0  
          */
function getAbsoluteLeft(htmlObject){
        var xPos = htmlObject.offsetLeft;
        var temp = htmlObject.offsetParent;
        while (temp != null) {
            xPos += temp.offsetLeft;
            temp = temp.offsetParent;
        }
        return xPos;
    }
		/**  
          *     @desc: Calculate absolute position of html object
          *     @type: private
          *     @param: htmlObject - html object
		  *     @topic: 0  
          */	
function getAbsoluteTop(htmlObject) {
        var yPos = htmlObject.offsetTop;
        var temp = htmlObject.offsetParent;
        while (temp != null) {
            yPos += temp.offsetTop;
            temp = temp.offsetParent;
        }
        return yPos;
   }
   
   
/**  
*     @desc: Convert string to it boolean representation
*     @type: private
*     @param: inputString - string for covertion
*     @topic: 0  
*/	  
function convertStringToBoolean(inputString){ if (typeof(inputString)=="string") inputString=inputString.toLowerCase();
	switch(inputString){
		case "1":
		case "true":
		case "yes":
		case "y":
		case 1:		
		case true:		
					return true; 
					break;
		default: 	return false;
	}
}

/**  
*     @desc: find out what symbol to use as url param delimiters in further params
*     @type: private
*     @param: str - current url string
*     @topic: 0  
*/	
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
		if (window.dhtmlDragAndDrop) return window.dhtmlDragAndDrop;
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
		if (window.dhtmlDragAndDrop.waitDrag) {
			 window.dhtmlDragAndDrop.waitDrag=0;		 
			 document.body.onmouseup=window.dhtmlDragAndDrop.tempDOMU;
			 document.body.onmousemove=window.dhtmlDragAndDrop.tempDOMM;
			 return;
		}
		
		window.dhtmlDragAndDrop.waitDrag=1;
		window.dhtmlDragAndDrop.tempDOMU=document.body.onmouseup;
		window.dhtmlDragAndDrop.tempDOMM=document.body.onmousemove;		
		window.dhtmlDragAndDrop.dragStartNode=this;
		window.dhtmlDragAndDrop.dragStartObject=this.dragStarter;
		document.body.onmouseup=window.dhtmlDragAndDrop.preCreateDragCopy;
		document.body.onmousemove=window.dhtmlDragAndDrop.callDrag;
	};
	dhtmlDragAndDropObject.prototype.callDrag=function(e){
		if (!e) e=window.event;
		dragger=window.dhtmlDragAndDrop;
		if (!dragger.dragNode) {
			dragger.dragNode=dragger.dragStartObject._createDragNode(dragger.dragStartNode);
			document.body.appendChild(dragger.dragNode);
			document.body.onmouseup=dragger.stopDrag;
			dragger.waitDrag=0;
			}

			dragger.dragNode.style.left=e.clientX+15+document.body.scrollLeft;  dragger.dragNode.style.top=e.clientY+3+document.body.scrollTop; 

		if (!e.srcElement) 	var z=e.target; 	else 	z=e.srcElement;
		dragger.checkLanding(z);
	}
	dhtmlDragAndDropObject.prototype.checkLanding=function(htmlObject){ 
		if (htmlObject.dragLanding) { if (this.lastLanding) this.lastLanding.dragLanding._dragOut(this.lastLanding);
										 this.lastLanding=htmlObject; this.lastLanding=this.lastLanding.dragLanding._dragIn(this.lastLanding,this.dragStartNode); }
		else {
			 if (htmlObject.tagName!="BODY") this.checkLanding(htmlObject.parentNode);
			 else  { if (this.lastLanding) this.lastLanding.dragLanding._dragOut(this.lastLanding); this.lastLanding=0; }
			 }
	}
	dhtmlDragAndDropObject.prototype.stopDrag=function(e){
		dragger=window.dhtmlDragAndDrop;
		if (dragger.lastLanding) dragger.lastLanding.dragLanding._drag(dragger.dragStartNode,dragger.dragStartObject,dragger.lastLanding);
		dragger.lastLanding=0;
		dragger.dragNode.parentNode.removeChild(dragger.dragNode);
		dragger.dragNode=0;
		dragger.dragStartNode=0;
		dragger.dragStartObject=0;
		document.body.onmouseup=dragger.tempDOMU;
		document.body.onmousemove=dragger.tempDOMM;
		dragger.tempDOMU=null;
		dragger.tempDOMM=null;
		dragger.waitDrag=0;
	}	