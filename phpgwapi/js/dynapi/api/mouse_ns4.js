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
	this._browserEvent = null;
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
p.getButton = function() {
	if (!this._browserEvent) return "left";
	var b = this._browserEvent.which;
	if (b==2) return "middle";
	if (b==3) return "right";
	else return "left";
};
p._init = function(type,e,src) {
	this.type = type;
	this._browserEvent = e;
	this.origin = src;
	this.bubbleChild = null;
	this.pageX = e.pageX-this._dyndoc.frame.pageXOffset;
	this.pageY = e.pageY-this._dyndoc.frame.pageYOffset;
	if (e.target._dynobj == src) {
		this.x = e.layerX;
		this.y = e.layerY;
	}
	else {
		this.x = e.pageX - (src.pageX||0);
		this.y = e.pageY - (src.pageY||0);
	}
	this.defaultValue = true;
	this.bubble = true;
};
p._invoke = function() {
	var o = this.origin;
	o.invokeEvent(this.type,this);
	// synthetic click event
	if (this.type=='mouseup') {
		this._init('click',this._browserEvent,o);
		this._invoke();
		
		// synthetic dblclick event
		if (dynapi.ua.other);
	}
};
function main() {
	dynapi.document._mouseEvent = new MouseEvent(dynapi.document);
};
if (!dynapi.loaded) main();
MouseEvent._docMoveHandler = function(e) {
	var dyndoc = this._dynobj;
	var src = e.target;
	var dynobj = src._dynobj || src._dynobji;
	
	if (!dynobj || !dynobj._hasMouseEvents) {
		var rel=dyndoc._moveOver;
		if(rel && dynobj && !dynobj.isChildOf(rel)) {
			var me = dyndoc._mouseEvent;
			me._init('mouseout',e,rel);
			me._invoke();
			dyndoc._moveOver = null;
		}
		if(dynobj){
			dynobj=dynobj.parent;
			while (dynobj && !dynobj._hasMouseEvents){
				dynobj=dynobj.parent;
			}
		}
		if(!dynobj) return true;
	}
	
	var me = dyndoc._mouseEvent;
	//dynapi.debug.status('move '+dynobj.name+' '+e.layerX+' '+e.layerY);
	me._init('mousemove',e,dynobj);
	me._invoke();
	var defaultVal = me.defaultValue;
	
	// synthetic mouseover/out events
	if (dyndoc._moveOver!=dynobj) {
		var rel = dyndoc._moveOver;
		//var bubble = true;
		// mouse out
		if (rel && !dynobj.isChildOf(rel)) {  //   && !rel.isChildOf(dynobj)
			// during mouseout e.getRelated() is which elm it is moving to
			//bubble = !dynobj.isChildOf(rel);
			me._init('mouseout',e,rel);
			//prevent bubbling from child to parent for mouseout
			if (rel.isChildOf(dynobj)) me.bubble=false; 
			me._relative = dynobj;
			me._invoke();
			//MouseEvent._generateEvent('mouseout',e,me,rel,dynobj,bubble);  // out occurs before over
		}		
		// mouse over
		dyndoc._moveOver = dynobj;
		//if (rel) var bubble = !rel.isChildOf(dynobj);
		//var bubble = !dynobj.isChildOf(rel);
		// during mouseover e.getRelated() is which elm it is moving to
		if(!rel || !rel.isChildOf(dynobj)){
			me._init('mouseover',e,dynobj);
			//prevent bubbling from child to parent for mouseover
			if(dynobj.isChildOf(rel)) me.bubble=false;
			me._relative = rel;
			me._invoke();
		}
		//MouseEvent._generateEvent('mouseover',e,me,dynobj,rel);
	}
	// prevent image dragging
	if (e.type=="mousemove" && (e.target+'')=='[object Image]') {
		me.defaultValue = defaultVal = false;
	}
	
	return defaultVal;
};
MouseEvent._eventHandler = function(e) {
	var src = e.target;
	var dynobj = this._dynobj;
	if (!dynobj) return true;
	
	var dyndoc = dynobj._dyndoc;
	var me = dyndoc._mouseEvent;
	me._wasHandled = false;
	var r = routeEvent(e);
	if (!me._wasHandled) {
		//if (src._dynobji) {  // src._dynobji == dynlayer.doc.images[x]._dynobji
		//	me._init(e.type,e,src._dynobji);
		//	if (e.type=='mousedown') me.defaultValue = false;
		//	me._invoke();
		//}
		// else 
		if (src._dynobj) {  // src._dynobj == dynlayer.doc._dynobj,dynlayer.doc.images[x]._dynobj,dynlayer.doc.links[x]._dynobj
			me._init(e.type,e,src._dynobj);
			me._invoke();
		}
		else {  // dynobj == dynlayer.elm._dynobj
			me._init(e.type,e,dynobj);
			me._invoke();
		}
		me._wasHandled = true;
	}
	dynobj = (src._dynobj)? src._dynobj:dynobj;
	if (e.type=='mousedown'){
		// disable text select
		if(dynobj._textSelectable==false) {
			// ns4 will disable hyperlinks. this is my workaround
			me.defaultValue =(e.target.href)? null:false;
		}
		
		// allow images (<input type="image">) to be clicked
		if ((e.target+'')=='[object Image]') {
			me.defaultValue = true;
		}
		
		// allow form elements to be selected
		var t = (e.target.type+'').toLowerCase();
		if (t=='button'||t=='checkbox'||t=='radio') {
			me.defaultValue=true;
		}
	}
	
	return me.defaultValue;
};
DynElement.prototype.disableContextMenu = function(){
	this._noContextMenu = true;
	// can this be done in ns?
};
DynElement.prototype.captureMouseEvents = function() {
	this._hasMouseEvents = true;
	var elm = this.elm;
	if (elm) {
		elm.captureEvents(Event.MOUSEDOWN | Event.MOUSEUP | Event.DBLCLICK);
		elm.onmousedown = elm.onmouseup = elm.ondblclick = MouseEvent._eventHandler;
		
		if (this.getClassName()=='DynDocument') {  // move/over/out events are generated from the document
			this.doc.captureEvents(Event.MOUSEMOVE);
			elm.onmousemove = MouseEvent._docMoveHandler;
		}
		elm._dynobj = this;
		this.doc._dynobj = this;
		if(this._blkBoardElm) this.elm.document._dynobj = this;
		for (var i=0;i<this.doc.images.length;i++) this.doc.images[i]._dynobj=this; // was _dynobji
		for (var i=0;i<this.doc.links.length;i++) this.doc.links[i]._dynobj=this;
	}
};
DynElement.prototype.releaseMouseEvents = function() {
	this._hasMouseEvents = false;
	var elm = this.elm;
	if (elm) {
		elm.releaseEvents(Event.MOUSEDOWN | Event.MOUSEUP | Event.DBLCLICK);
		elm.onmousedown = elm.onmouseup = elm.ondblclick = null;
		
		if (this.getClassName()=='DynDocument') {
			elm.releaseEvents(Event.MOUSEMOVE);
			elm.onmousemove = null;
		}
		elm._dynobj = null;
		this.doc._dynobj = null;
		for (var i=0;i<this.doc.images.length;i++) this.doc.images[i]._dynobji=null;
		for (var i=0;i<this.doc.links.length;i++) this.doc.links[i]._dynobj=null;
	}
};
