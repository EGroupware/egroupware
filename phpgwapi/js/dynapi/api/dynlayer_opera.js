/*
	DynAPI Distribution
	DynLayer Opera Specific Functions

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
	
	requires: dynapi.api.DynLayerBase
*/

// Warning: Avoid using document.all collection as it has been reported 
// that on some platforms when opera is set to an identity other than IE 
// the all[] collection is not available 

p = DynLayer.prototype;
p._create = function() {
	if (this.parent && !this.elm) {
		DynElement._flagPreCreate(this);
		var elm, parentElement;
		parentElement = this.parent.elm;
		
		// this method is more efficient for opera7+
		parentElement.insertAdjacentHTML("beforeEnd",this.getOuterHTML());
		elm = parentElement.children[parentElement.children.length-1];
		
		DynLayer._assignElement(this,elm);
		DynElement._flagCreate(this);
	}
};
DynLayer._assignElement = function(dlyr,elm,divs) {
	if (!elm ) {
		elm = (divs)? divs[dlyr.id] : dlyr.parent.doc.getElementById(dlyr.id);
		if (!elm){
			if(dlyr.isInline) dlyr._create();  // force create() for missing inline layer
			return;
		}
	}
	dlyr.elm = elm;
	dlyr.css = elm.style;
	dlyr.doc = dlyr.parent.doc;
	dlyr.elm._dynobj = dlyr;
	dlyr._dyndoc = dlyr.parent._dyndoc;
	if(dlyr._blkBoardElm) dlyr._blkBoardElm = (divs)? divs[dlyr.id+'_blkboard'] : dlyr.parent.doc.getElementById(dlyr.id+'_blkboard');

	// COMMENTED OUT TO PERMIT CSS PRIORITY
	// by Raphael Pereira <raphael@think-e.com.br>
	if (0 && dlyr.html!=null && dlyr.html!='' && (dlyr.w==null || dlyr.h==null)) {
		var cw = (dlyr.w==null)? dlyr.getContentWidth() : null;
		var ch = (dlyr.h==null)? dlyr.getContentHeight() : null;
		//var cw = (dlyr.w==null)? dlyr.getElmWidth() : null;
		//var ch = (dlyr.h==null)? dlyr.getElmHeight() : null;
		dlyr.setSize(cw,ch);
	}

	var i,ch=dlyr.children; 
	for (i=0;i<ch.length;i++) DynLayer._assignElement(ch[i],null,divs);

	if (this._textSelectable==false) elm.onselectstart = dynapi.functions.Disallow;

	// Box Fix - for Border Manager
	if (dlyr._needBoxFix) BorderManager.FixBoxModel(dlyr);

	if (dlyr._hasKeyEvents) dlyr.captureKeyEvents();
	if (dlyr._hasMouseEvents) dlyr.captureMouseEvents();	
};
p.enableBlackboard = function(){
	if (!this._created) this._blkBoardElm=true;
	else if(!this._blkBoardElm){
		var h='',elm = this.elm;
		if(this.html!=null) h=this.html;
		elm.insertAdjacentHTML("beforeEnd",'<div id="'+this.id+'_blkboard">'+h+'</div>');
		this._blkBoardElm = elm.children[elm.children.length-1];
	}
};
p.setLocation=function(x,y) {
	var cx = (x!=null && x!=this.x);
	var cy = (y!=null && y!=this.y);
	if (cx) this.x = x||0;
	if (cy) this.y = y||0;
	if (this.css!=null) {
		if (cx) this.css.pixelLeft = this.x;
		if (cy) this.css.pixelTop = this.y;
		// adjust parent size after being sized
		if((cx||cy) && this.parent._aSz) this.parent._adjustSize();		
	}
	if(this._hasLocationEvents) this.invokeEvent('locationchange');		
	return (cx||cy);
};
p.setPageLocation = function(x,y) {
	if (this.isChild) {
		if (dynapi.ua.v>=5) {
			if (cx) this.css.pixelLeft = this.x;
			if (cy) this.css.pixelTop = this.y;
		}
		else {
			if (cx) this.css.left = this.x+"px";
			if (cy) this.css.top = this.y+"px";
		}
	}
	return this.setLocation(x,y);
};
p.setHTML = function(html) {
	if (html!=this.html) {
		this.html = html;
		if (this.css) {
			var elm = (this._blkBoardElm)? this._blkBoardElm:this.elm;
			elm.innerHTML = html;
			this._adjustSize();
		}
	}
	if(this._hasContentEvents) this.invokeEvent('contentchange');
};
p.setTextSelectable=function(b) {
	this._textSelectable = b;
	if (this.elm) this.elm.onselectstart = b? dynapi.functions.Allow : dynapi.functions.Deny;
	if (!b) this.setCursor('default');
	// && this.captureMouseEvents && !this._hasMouseEvents) this.captureMouseEvents();
};
p.getCursor = function() {return this._cursor};
p.setCursor = function(c) {
	if (!c) c = 'default';
	else c=(c+'').toLowerCase();
	if (this._cursor!=c) {
		this._cursor = c;
		if (this.css) this.css.cursor = c;
	}		
};
p.getContentWidth=function() {
	if (this.elm==null) return 0;
	else {
		var tw=this.css.width;
		var w,to = this.css.overflow;
		this.css.width='auto';
		this.css.overflow='auto';
		w = parseInt(this.elm.scrollWidth);
		this.css.width=tw;
		this.css.overflow=to;
		return w;
		
	};
};
p.getContentHeight=function() {
	if (this.elm==null) return 0;
	else {
		if (dynapi.ua.platform=="mac") return this.elm.offsetHeight;
		return parseInt(this.elm.scrollHeight);
	}
};
