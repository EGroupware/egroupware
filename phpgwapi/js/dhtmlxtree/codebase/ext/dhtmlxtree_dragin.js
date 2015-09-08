//v.3.6 build 131023

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/
dhtmlXTreeObject.prototype.makeDraggable=function(a,b){typeof a!="object"&&(a=document.getElementById(a));dragger=new dhtmlDragAndDropObject;dropper=new dhx_dragSomethingInTree;dragger.addDraggableItem(a,dropper);a.dragLanding=null;a.ondragstart=dropper._preventNsDrag;a.onselectstart=new Function("return false;");a.parentObject={};a.parentObject.img=a;a.parentObject.treeNod=dropper;dropper._customDrop=b};dhtmlXTreeObject.prototype.makeDragable=dhtmlXTreeObject.prototype.makeDraggable;
dhtmlXTreeObject.prototype.makeAllDraggable=function(a){for(var b=document.getElementsByTagName("div"),c=0;c<b.length;c++)b[c].getAttribute("dragInDhtmlXTree")&&this.makeDragable(b[c],a)};
function dhx_dragSomethingInTree(){this.lWin=window;this._createDragNode=function(a){var b=document.createElement("div");b.style.position="absolute";b.innerHTML=a.innerHTML||a.value;b.className="dragSpanDiv";return b};this._preventNsDrag=function(a){(a||window.event).cancelBubble=!0;a&&a.preventDefault&&a.preventDefault();return!1};this._nonTrivialNode=function(a,b,c,d){if(this._customDrop)return this._customDrop(a,d.img.id,b.id,c?c.id:null);var e=d.img.getAttribute("image")||"",f=d.img.id||"new",
g=d.img.getAttribute("text")||(_isIE?d.img.innerText:d.img.textContent);a[c?"insertNewNext":"insertNewItem"](c?c.id:b.id,f,g,"",e,e,e)}};

//v.3.6 build 131023

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/