/*
	DynAPI Distribution
	DynEvent, EventObject, DynElement Classes

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
*/

function DynEvent(type,src) {
	this.type = type;
	this.src = src;
	this.origin = src;
	this.propagate = true;
	this.bubble = false;
	this.bubbleChild = null;
	this.defaultValue = true;
};
var p = DynEvent.prototype; 
p.getType = function() {return this.type};
p.getSource = function() {return this.src};
p.getOrigin=function() {return this.origin};
p.stopPropagation = function() {this.propagate = false};
p.preventBubble = function() {this.bubble = false};
p.preventDefault = function() {this.defaultValue = false};
p.getBubbleChild = function() {return this.bubbleChild};

function EventObject() {
	this.DynObject = DynObject;
	this.DynObject();
	this._listeners = [];
};
EventObject._SubClass={};

p = dynapi.setPrototype('EventObject','DynObject');
p.addEventListener = function(el) {
	if (el) {
		for (var i=0;i<this._listeners.length;i++) if (this._listeners[i]==el) return;
		this._listeners[this._listeners.length] = el;		
		// Use onCreate() and onPrecreate() function for create events
		this._hasContentEvents=(el['oncontentchange'])? true:this._hasContentEvents;
		this._hasLocationEvents=(el['onlocationchange'])? true:this._hasLocationEvents;
		this._hasResizeEvents=(el['onresize'])? true:this._hasResizeEvents;
		this._hasDragEvents=(el['ondragstart']||el['ondragmove']||
							el['ondragend']||el['ondragdrop']||el['ondrop']||
							el['ondragover']||el['ondragout'])? true:this._hasDragEvents;

		if (this.captureMouseEvents) {
			this._hasMouseEvents = this._hasMouseEvents||(el.onmousedown || el.onmouseup || el.onmouseover || el.onmouseout || el.onclick || el.ondblclick);
			if (this._created && !this._hasMouseEvents) this.captureMouseEvents();
		}
		if (this.captureKeyEvents) {
			this._hasKeyEvents = this._hasKeyEvents || (el.onkeyup || el.onkeydown || el.onkeypress);
			if (this._created && !this._hasKeyEvents && (el.onkeydown || el.onkeyup || el.onkeypress)) this.captureKeyEvents();
		}
	}
};
p.removeEventListener = function(el) {
	if (el) {
		DynAPI.functions.removeFromArray(this._listeners, el, false);
		if (!this._listeners.length && this.releaseMouseEvents && this.getClassName()!='DynDocument') this.releaseMouseEvents();
		if (!this._listeners.length && this.releaseKeyEvents && this.getClassName()!='DynDocument') this.releaseKeyEvents();
	}
};
p.removeAllEventListeners = function() {
	delete this._listeners;
	this._listeners = [];
};
p.invokeEvent = function(type,e,args) {
	if (!e) e = new DynEvent(type,this);
	e.src = this;
	e.type = type;
	
	// Check for subclassing
	var clsFn=EventObject._SubClass[this+'_'+type];
	if(clsFn) {
		if (clsFn(e,args)==false) return;
	};
	
	if (this._listeners.length) for (var i=0;i<this._listeners.length;i++) {
		if (this._listeners[i]["on"+type]) this._listeners[i]["on"+type](e,args);
		if (!e.propagate) break;
	}
	if (this["on"+type]) this["on"+type](e,args);
	if (e.bubble && this.parent) {
		//if ((type=="mouseover" || type=="mouseout") && e._relative==this.parent) return;
		e.x += this.x;
		e.y += this.y;
		e.bubbleChild = this;
		this.parent.invokeEvent(type,e,args);
	}
};

// Add subClassEvent() function to dynapi.functions
dynapi.functions.subClassEvent = function(type,eobj,fn){
	var ek=eobj+'_'+type;
	var cls=EventObject._SubClass;
	if(typeof(fn)=='function') cls[ek]=fn;
	else if(!fn && cls[ek]) delete cls[ek];
};

function DynElement() {
	this.EventObject = EventObject;
	this.EventObject();
	this.isChild = false;
	this._created = false;
	this.parent = null;
	this._dyndoc = null;
	this.children = [];
	this._childAnchors = {};
	//raphaelpereira
	this._cfn = {};
	this._fn = 0;
};
DynElement._flagCreate = function(c){ // much faster than using DynElemnt._flagEvent
	var ch=c.children;
	c._created = true;
	if (c._hasCreateFn) c._flagCreateEvent('create');		
	for (var i=0; i<ch.length; i++) this._flagCreate(ch[i]);
};
DynElement._flagPreCreate = function(c){
	var ch=c.children;
	if (c._hasPCreateFn) c._flagCreateEvent('precreate');		
	for (var i=0; i<ch.length; i++) this._flagPreCreate(ch[i]);
};
DynElement._flagEvent = function(c,type) {
	var ch=c.children;
	c.invokeEvent(type);
	for (var i=0; i<ch.length; i++) this._flagEvent(ch[i],type);
};
p = dynapi.setPrototype('DynElement','EventObject');
p._adjustSize = dynapi.functions.Null;
p.addChild = function(c,alias,inlineID) {
	if (!c) return dynapi.debug.print("Error: no object sent to [DynLayer].addChild()");
	if (c.isChild) c.removeFromParent();
	c.isChild = true;
	c.parent = this;
	if (c._saveAnchor) {
		c.setAnchor(c._saveAnchor);
		c._saveAnchor = null;
		delete c._saveAnchor;
	}
	c._alias = alias;
	if(alias) this[alias]=c;
	if(inlineID)
		c.setID(inlineID,true);
	if (this._created)	{
		if (c.isInline) c._createInline();
		else c._create();
	}
	this.children[this.children.length] = c;
	if(this._aSz) this._adjustSize(); // adjust size if necessary
	return c;
};
p.deleteAllChildren = function() {	// removes & destroy all children
	var i=0;
	var ch =this.children;
	var aSz = this._aSz;
	this._aSz = false; // prevent children from adjusting parent's size when removed
	while(ch.length) {
		c=ch[0];
		if(c) c.deleteFromParent();
		else {
			i++; // fail safe method
			if(i>=ch.length) break;
		}
	};	
	ch.length = 0;
	this._aSz = aSz;
	if(this._aSz) this._adjustSize(); // adjust size if necessary
};
p.deleteChild = function(c) { // removes & destroy child
	var l = this.children.length;
	for (var i=0;i<l && this.children[i]!=c;i++);
	if (i!=l) {
		c._destroy();
		this.dropChildIndex(i);
	}
};
p.deleteFromParent = function () { // removes & destroy child
	if (this.parent) this.parent.deleteChild(this);
};
p.dropChildIndex = function(i){
	var ch = this.children;
	var l = ch.length;
	delete ch[i];
	ch[i] = ch[l-1];
	ch[l-1] = null;
	ch.length--;
	// adjust parent size if necessary
	if(this._aSz) this._adjustSize();
};
p.removeChild = function(c) {
	var l = this.children.length;
	for (var i=0;i<l && this.children[i]!=c;i++);
	if (i!=l) {
		c._remove();
		c._created = c.isChild = false;
		c.parent = c.dyndoc = null;
		c.elm = c._blkBoardElm = c.css = c.doc = null;
		this.dropChildIndex(i);
	}
};
p.removeFromParent = function () {
	if (this.parent) this.parent.removeChild(this);
};
p._create = p._createInLine = p._createInserted = p._remove = p._delete = p._destroy = dynapi.functions.Null;

p.getChildren = function() {return this.children};
p.getAllChildren = function() {
	var temp;
	var ret = [];
	var ch = this.children;
	var l = ch.length;
	for(var i=0;i<l;i++) {
		ret[ch[i].id] = ch[i];
		temp = ch[i].getAll();
		for(var j in temp) ret[j] = temp[j];
	}
	return ret;
};
p.getParents = function(l) {
	if (l==null) l = [];
	if (this.parent) {
		l[l.length] = this.parent;
		l = this.parent.getParents(l);
	}
	return l;
};
p.isParentOf = function(c) {
	if (c) {
		var p = c.getParents();
		for (var i=0;i<p.length;i++) {
			if (p[i]==this) return true;
		}
	}
	return false;
};
p.isChildOf = function(p) {
	if (!p) return false;
	return p.isParentOf(this);
};

// New onPreCreate() and onCreate() callback functions
p.onCreate = function(fn){
	if(!fn) return;
	if(!this._cfn){this._fn=0;this._cfn=[];}
	var s='create'+this._fn++;
	this._cfn[s]='create';
	this._hasCreateFn=true;
	this[s]=fn;
};
p.onPreCreate = function(fn){
	if(!fn) return;
	if(!this._cfn){this._fn=0;this._cfn=[];}
	var s='precreate'+this._fn++;
	this._cfn[s]='precreate';
	this._hasPCreateFn=true;
	this[s]=fn;
};
p._flagCreateEvent = function(t){
	for(var i in this._cfn){ 
		if(this._cfn[i]==t) 
		try{this[i]();}
		catch(e){return}
	};
};

p.updateAnchor = function() {
	this.parent._updateAnchor(this.id);
};
p._updateAnchor = function(id) {
	if (!id) return;
	var dlyr = DynObject.all[id];
	var a = this._childAnchors[id];
	var tw = this.w;
	var th = this.h;
	if (a==null || (tw==null && th==null)) return;
	
	// anchoring/docking
	var fn=dynapi.functions;
	var padX=0,padY=0;
	if(a.topA) {
		anc=fn.getAnchorLocation(a.topA,this);
		if(anc){padY=anc.y; th=th-padY;}
	}
	if(a.leftA) {
		anc=(a.leftA==a.topA && anc)? anc:fn.getAnchorLocation(a.leftA,this);
		if(anc) {padX=anc.x; tw=tw-padX;}
	}
	if(a.bottomA) {
		anc=fn.getAnchorLocation(a.bottomA,this);
		th=th-(this.h-anc.y);
	}
	if(a.rightA) {
		anc=(a.bottomA==a.rightA && anc)? anc:fn.getAnchorLocation(a.rightA,this);
		if(anc) tw=tw-(this.w-anc.x);				
	}
	
	var aleft=(tw>0 && a.left && typeof(a.left)=='string')? tw*(parseInt(a.left)/100):a.left;
	var aright=(tw>0 && a.right && typeof(a.right)=='string')? tw*(parseInt(a.right)/100):a.right;
	var atop=(th>0 && a.top && typeof(a.top)=='string')? th*(parseInt(a.top)/100):a.top;
	var abottom=(th>0 && a.bottom && typeof(a.bottom)=='string')? th*(parseInt(a.bottom)/100):a.bottom;
	var x = aleft;
	var y = atop;
	

	var w = null;
	var h = null;
	var dlyrWidth=dlyr.getWidth();
	var dlyrHeight=dlyr.getHeight();
	if (a.stretchH!=null) {
		if(typeof(a.stretchH)!='string') w=a.stretchH;
		else {
			if(a.stretchH=='*') w = tw - ((aleft!=null)? aleft:0);
			else w = tw*(parseInt(a.stretchH)/100);
		}
		dlyrWidth=w;
	}
	if (a.centerH!=null) {
		x = Math.ceil(tw/2 - dlyrWidth/2 + a.centerH);
	}else if (aright!=null) {
		if (aleft!=null) w = (tw - aright) - aleft;
		else x = (tw - dlyrWidth) - aright;
		if(tw<=0 && x<0) x=null; // ns4 needs x>=0
	}	
	if (a.stretchV!=null) {
		if(typeof(a.stretchV)!='string') h=a.stretchV;
		else {
			if(a.stretchV=='*') h = th - ((atop!=null)? atop:0);
			else h = th*(parseInt(a.stretchV)/100);
		}
		dlyrHeight=h;
	}	
	if (a.centerV!=null) {
		y = Math.ceil(th/2 - dlyrHeight/2 + a.centerV);
	}else if (abottom!=null) {
		if (atop!=null) h = (th - abottom) - atop;
		else y = (th - dlyrHeight) - abottom;
		if(th<=0 && y<0) y=null; // ns4 needs y>=0
	}
	if(padX) {x=(x)? x:0;x+=padX}
	if(padY) {y=(y)? y:0;y+=padY}

	// IE seems to be getting wrong position
	if (dynapi.ua.ie)
	{
/*		aleft += 10;
		aright += 10;
		atop += 10;
		abottom += 10;*/
		x += 7;
		y += 14;
	}
		var tmp=dlyr._hasAnchor;	
	dlyr._hasAnchor=false; // ignore anchor updates of this layer
	if(x!=null||y!=null) dlyr.setLocation(x,y);
	if(w!=null||h!=null) dlyr.setSize(w,h);
	dlyr._hasAnchor = tmp; // useful for preventing stack overflow
};
p._updateAnchors = function() {
	var tw = this.w;
	var th = this.h;
	if (tw==null && th==null) return;
	for (id in this._childAnchors) this._updateAnchor(id);
};


// Bandwidth timer stop
var ua=dynapi.ua; ua._bwe=new Date;
ua.broadband=((ua._bwe-ua._bws)<=1500)? true:false;
