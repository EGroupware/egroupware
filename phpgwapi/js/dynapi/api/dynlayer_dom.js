/*
	DynAPI Distribution
	DynLayer DOM Specific Functions

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
	
	requires: dynapi.api.DynLayerBase
*/

p = DynLayer.prototype;
p._create = function() {
	if (this.parent && !this.elm) {
		DynElement._flagPreCreate(this);
		var elm, parentElement;
		parentElement = this.parent.elm;
		
		// this method seems faster for most dom browsers
		var r = parentElement.ownerDocument.createRange();
		r.setStartBefore(parentElement);
		var ptxt = r.createContextualFragment(this.getOuterHTML());
		parentElement.appendChild(ptxt);
		elm = parentElement.lastChild;

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

	if (dlyr.z) dlyr.css.zIndex = dlyr.z;

	// COMMENTED OUT TO PERMIT CSS PRIORITY
	// by Raphael Pereira <raphaelpereira@users.sourceforge.net>
	if (0 && dlyr.html!=null && dlyr.html!='' && (dlyr.w==null || dlyr.h==null)) {
		var cw = (dlyr.w==null)? dlyr.getContentWidth() : null;
		var ch = (dlyr.h==null)? dlyr.getContentHeight() : null;
		//var cw = (dlyr.w==null)? dlyr.getElmWidth() : null;
		//var ch = (dlyr.h==null)? dlyr.getElmHeight() : null;
		dlyr.setSize(cw,ch);
	}

	var i,ch=dlyr.children; 
	for (i=0;i<ch.length;i++) DynLayer._assignElement(ch[i],null,divs);

	// Box Fix - for Border Manager
	if (dlyr._needBoxFix) BorderManager.FixBoxModel(dlyr);

	if (dlyr._hasKeyEvents) dlyr.captureKeyEvents();
	if (dlyr._hasMouseEvents) dlyr.captureMouseEvents();
};
p.enableBlackboard = function(){
	if (!this._created) this._blkBoardElm=true;
	else if(!this._blkBoardElm){
		var r,ptxt;
		var h='',elm = this.elm;
		if(this.html!=null) h=this.html;
		r = elm.ownerDocument.createRange();
		r.setStartBefore(elm);
		ptxt = r.createContextualFragment('<div id="'+this.id+'_blkboard">'+h+'</div>');
		elm.appendChild(ptxt);
		this._blkBoardElm = elm.lastChild;		
	}
};
p.setLocation=function(x,y) {
	var cx = (x!=null && x!=this.x);
	var cy = (y!=null && y!=this.y);
	if (cx) this.x = x||0;
	if (cy) this.y = y||0;
	if (this.css!=null) {
		if (cx) this.css.left = this.x+"px";
		if (cy) this.css.top = this.y+"px";
		// adjust parent size after being moved
		if((cx||cy) && this.parent._aSz) this.parent._adjustSize();
	}
	if(this._hasLocationEvents) this.invokeEvent('locationchange');
	return (cx||cy);
};
p.setPageLocation = function(x,y) {
	if (this.isChild) {
		if (x!=null) x = x - this.parent.getPageX();
		if (y!=null) y = y - this.parent.getPageY();
	}
	return this.setLocation(x,y);
};
p.setHTML = function(html) {
	if (html!=this.html) {
		this.html = html;
		if (this.css) {
			var elm = (this._blkBoardElm)? this._blkBoardElm:this.elm;
			elm.innerHTML = html;		
			var sTmp=(this.w==null)?'<NOBR>'+this.html+'</NOBR>':this.html;
			while (elm.hasChildNodes()) elm.removeChild(elm.firstChild);
			var r=elm.ownerDocument.createRange();
			r.selectNodeContents(elm);
			r.collapse(true);
			var df=r.createContextualFragment(sTmp);
			elm.appendChild(df);
			this._adjustSize();
		}
	}
	if(this._hasContentEvents) this.invokeEvent('contentchange');	
};
p.setTextSelectable=function(b) {
	this._textSelectable = b;
	if(!this._hasMouseEvents) this.captureMouseEvents();
	if (!b) this.setCursor('default');
};
p.getCursor = function() {return (this._cursor=='pointer')? 'hand':this._cursor};
p.setCursor = function(c) {
	if (!c) c = 'default';
	else c=(c+'').toLowerCase();
	if (c=='hand') c='pointer';
	if (this._cursor!=c) {
		this._cursor = c;
		if (this.css) this.css.cursor = c;
	}		
};
p.getContentWidth=function() {
	if (this.elm==null) return 0;
	else {
		var p = this.parent;		
		var tw = this.elm.style.width;
		this.css.width = "auto";		
		var w = this.elm.offsetWidth;
		this.css.width = tw;
		return w;
	};
};
p.getContentHeight=function() {
	if (this.elm==null) return 0;
	else {
		var th = this.css.height;
		this.elm.style.height = "auto";
		var h = this.elm.offsetHeight;
		this.css.height = th;
		return h;
	}
};
