/*
	DynAPI Distribution
	MouseEvent Class

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
	
	requires: dynapi.api.DynDocument
*/

function MouseEvent(dyndoc) {
	this.DynEvent = DynEvent;
	this.DynEvent();
	this.bubble = true;
	this._mouseEvent = null;
	this._relative = null;
	this._dyndoc = dyndoc;
};
var p = dynapi.setPrototype('MouseEvent','DynEvent');
p.getX = function() {return this.x};
p.getY = function() {return this.y};
p.getPageX = function() {return this.pageX};
p.getPageY = function() {return this.pageY};
//p.trapMouseUp = dynapi.functions.Null;
p.getRelative = function() {return this._relative};
p.preventBubble = function() {
	this.bubble = false;
};
p.getButton = function() {
	if (!this._mouseEvent) return "left";
	var b = this._mouseEvent.which;
	if (b==2) return "middle";
	if (b==3) return "right";
	else return "left";
};
p._init = function(type,e,src) {
	this.type = type;
	this._mouseEvent = e;
	this.origin = src;
	this.bubbleChild = null;
	this.defaultValue = true;
	this.bubble = true;
};
p._invoke = function() {
	var o = this.origin;
	o.invokeEvent(this.type,this);
};
MouseEvent._getContainerLayerOf = function(element) {
	try{
		if (!element) return null;
		while (!element._dynobj && element.parentNode && element.parentNode!=element) {
			element = element.parentNode;
		}
		return element._dynobj;
	}
	catch(e)
	{
		//FIXME: For some wierd reason, a InputElement is parent of a DIV
		// and then it falls here. For now, the error is ignored
	}
};
MouseEvent._eventHandler = function(e) {
	var dynobj = this._dynobj;
	if (!dynobj) return true;
	var dyndoc = dynobj._dyndoc;
	var target = e.target;

	var me = dyndoc._mouseEvent;
	var src = MouseEvent._getContainerLayerOf(target);
	me._init(e.type,e,src);
	var rel = e.relatedTarget;
	var r = me._relative = MouseEvent._getContainerLayerOf(rel);
	if (e.type=="mouseout" || e.type=="mouseover") {
		if(!r && dynapi.ua.opera) return; //fix for #15
		if (r && (r==src||src.isParentOf(r))) return; // fix for #15
		if (r && (r==src.parent||r.isChildOf(src.parent))) me.bubble=false;
	}
	me.pageX = e.clientX;
	me.pageY = e.clientY;
	if(!src) return;
	me.x = (me.pageX+(window.pageXOffset||0)) - src.getPageX(); //offsetX;
	me.y = (me.pageY+(window.pageYOffset||0)) - src.getPageY(); //offsetY;
	
	// NOTE: This is done because in Mozilla, when adding event listeners
	// to document element, 'title' properties doesn't work anymore...
	// raphaelpereira@users.sourceforge.net
	if (src.getClassName() != 'DynDocument') e.cancelBubble = true;

	me._invoke();

	var tn=(target.tagName+'').toLowerCase();
	
	// fix for form elements inside drag-enabled layer #08
	if(e.type=='mousedown' && tn=='input'||tn=='textarea'||tn=='button') {
		var de=dynapi.frame.DragEvent;
		de=(de && de.dragevent)? de.dragevent:null;
		if(de && de.isDragging) de.cancelDrag();
	}

	// prevent image dragging
	if(tn=='img' && typeof(target.onmousedown)!="function") {
		target.onmousedown=dynapi.functions.False;
	}
	
	// disable text select
	if (e.type=='mousedown' && src._textSelectable==false) {
		e.preventDefault();
		return false;
	}
	
};

DynElement.prototype.disableContextMenu = function(){
	this._noContextMenu = true;
	if(this.elm) this.elm.addEventListener("contextmenu",MouseEvent._eventHandler,false);
};
DynElement.prototype.captureMouseEvents = function() {
	this._hasMouseEvents = true;
	var elm = (this.getClassName()=='DynDocument')? this.doc : this.elm;

	if(elm) {
		elm.addEventListener("mousemove",MouseEvent._eventHandler,false);
		elm.addEventListener("mousedown",MouseEvent._eventHandler,false);
		elm.addEventListener("mouseup",MouseEvent._eventHandler,false);
		elm.addEventListener("mouseover",MouseEvent._eventHandler,false);
		elm.addEventListener("mouseout",MouseEvent._eventHandler,false);
		elm.addEventListener("click",MouseEvent._eventHandler,false);
		elm.addEventListener("dblclick",MouseEvent._eventHandler,false);
		if(this._noContextMenu) elm.addEventListener("contextmenu",MouseEvent._eventHandler,false);
	}
};
DynElement.prototype.releaseMouseEvents=function() {
	this._hasMouseEvents = false;
	var elm = (this.getClassName()=='DynDocument')? this.doc : this.elm;
	if (typeof(elm)=='object') {
	/*	elm.removeEventListener("mousemove",MouseEvent._eventHandler,false);
		elm.removeEventListener("mousedown",MouseEvent._eventHandler,false);
		elm.removeEventListener("mouseup",MouseEvent._eventHandler,false);
		elm.removeEventListener("mouseover",MouseEvent._eventHandler,false);
		elm.removeEventListener("mouseout",MouseEvent._eventHandler,false);
		elm.removeEventListener("click",MouseEvent._eventHandler,false);
		elm.removeEventListener("dblclick",MouseEvent._eventHandler,false);*/
	}
};

function main_mouse_dom() { 
	dynapi.document._mouseEvent = new MouseEvent(dynapi.document);
};
if (!dynapi.loaded) main_mouse_dom();
