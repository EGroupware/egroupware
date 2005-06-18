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
p.getRelative = function() {return this._relative};
p.preventBubble = function() {this.bubble = false;};
p.getButton = function() {
	if (!this._mouseEvent) return "left";
	var b = this._mouseEvent.button;
	if (b==4) return "middle";
	if (b==2) return "right";
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
	if (!element) return null;
	while (!element._dynobj && element.parentElement && element.parentElement!=element) {
		element = element.parentElement;
	}
	return element._dynobj;
};
//MouseEvent.trapMouseUp = dynapi.functions.False; // or MouseEvent.trapMouseUp=null

MouseEvent._eventHandler = function() {
	var e = dynapi.frame.event;
	var dynobj;
	if (this._dynobj) dynobj = this._dynobj;
	else if (e.srcElement._dynobj) dynobj = e.srcElement._dynobj;
	else dynobj = dynapi.document;
	
	var dyndoc = dynobj._dyndoc;
	var target = e.srcElement;

	var me = dyndoc._mouseEvent;
	var src = MouseEvent._getContainerLayerOf(target);
	me._init(e.type,e,src);

	var rel = e.type=="mouseout"? e.toElement : e.fromElement;
	var r = me._relative = MouseEvent._getContainerLayerOf(rel);
	if (e.type=="mouseout" || e.type=="mouseover") {
		if (r && src && (r==src||src.isParentOf(r))) return; //fix for #15 (ie only)
		if (r && src && (r==src.parent||r.isChildOf(src.parent))) me.bubble=false;
	}
	me.pageX = e.clientX;
	me.pageY = e.clientY;
	if(!src) return;
	me.x = (me.pageX + (document.body.scrollLeft||0)) - src.getPageX(); //offsetX;
	me.y = (me.pageY + (document.body.scrollTop||0)) - src.getPageY(); //offsetY;
	e.cancelBubble = true;
	me._invoke();

	var tt=target.type;
	var tn=(target.tagName+'').toLowerCase();
	
	// fix for form elements inside drag-enabled layer #08
	if(tt=='textarea'||tt=='text' && target.onselectstart==null) target.onselectstart = dynapi.functions.Allow;
	if(e.type=='mousedown' && tn=='input'||tn=='textarea'||tn=='button') {
		var de=dynapi.frame.DragEvent;
		de=(de && de.dragevent)? de.dragevent:null;
		if(de && de.isDragging) de.cancelDrag();
	}
	
	// prevent image dragging
	if(target.tagName=='IMG' && typeof(target.ondragstart)!="function") {
		target.ondragstart=dynapi.functions.False;
	}
	
};

DynElement.prototype.disableContextMenu = function(){
	this._noContextMenu = true;
	if(this.elm) this.elm.oncontextmenu = dynapi.functions.False;
};
DynElement.prototype.captureMouseEvents = function() {
	this._hasMouseEvents = true;
	if (this.elm) {
		var elm = (this.getClassName()=='DynDocument')? this.doc : this.elm;
		elm.onmouseover = elm.onmouseout = elm.onmousedown = elm.onmouseup = elm.onclick = elm.ondblclick = elm.onmousemove = MouseEvent._eventHandler;
		if(this._noContextMenu) elm.oncontextmenu = dynapi.functions.False;
	}
};
DynElement.prototype.releaseMouseEvents = function() {
	this._hasMouseEvents = false;
	if (this.elm) {
		var elm = (this.getClassName()=='DynDocument')? this.doc : this.elm;
		elm.onmousedown = elm.onmouseup = elm.onclick = elm.ondblclick = null;
		elm.oncontextmenu = null;
	}
};

function main_mouse_ie() { 
	dynapi.document._mouseEvent = new MouseEvent(dynapi.document); 
};
if (!dynapi.loaded) main_mouse_ie();
