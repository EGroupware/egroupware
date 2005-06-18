/*
	DynAPI Distribution
	DynLayer IE Specific Functions

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
	
	requires: dynapi.api.DynLayerBase
*/

p = DynLayer.prototype;
p._create = function() {
	if (this.parent && !this.elm) {			
		DynElement._flagPreCreate(this);		
		var elm, parentElement;
		parentElement = this.parent.elm;
//		if(dynapi.ua.v<5){
			parentElement.insertAdjacentHTML("beforeEnd",this.getOuterHTML());
			elm = parentElement.children[parentElement.children.length-1];
/*		}
		else {
			// this method is more efficient for ie5+. any comment?
			//elm=document.createElement('DIV');
			elm=this._getDOMObject();
			elm.id=this.id;
			if(this._className) elm.className=this._class;
			if(!this._noStyle) {
				var css = elm.style;
				css.position=(this._position||'absolute');
				if (this.x) css.pixelLeft = this.x;
				if (this.y) css.pixelTop = this.y;
				if (this.w) css.width = this.w;
				if (this.h) css.height = this.h;
				if (this.bgColor) css.backgroundColor = this.bgColor;
				if (this.z) css.zIndex = this.z;
				if (this._cursor) css.cursor = this._cursor;
				if (this._overflow) css.overflow = this._overflow;			
				if (this.bgImage!=null) css.backgroundImage='url('+this.bgImage+')';
				//if (this.bgImage==null && this.html==null) css.backgroundImage='';
				if (this.clip) css.clip='rect('+this.clip[0]+'px '+this.clip[1]+'px '+this.clip[2]+'px '+this.clip[3]+'px)';
				else if (this.w!=null && this.h!=null) css.clip='rect(0px '+this.w+'px '+this.h+'px 0px)';		
				css.visibility=(this.visible==false)? 'hidden':'inherit';
				// border - set by BorderManager
				if(this._cssBorTop){
					css.borderTop = this._cssBorTop||'';
					css.borderRight = this._cssBorRight||''; 
					css.borderBottom = this._cssBorBottom||'';
					css.borderLeft = this._cssBorLeft||'';
				}
			}
			try {
				elm.innerHTML = this.getInnerHTML();
			}
			catch (e)
			{
			}
			//alert(elm.outerHTML);
			parentElement.appendChild(elm);
		}
*/		DynLayer._assignElement(this,elm);
		DynElement._flagCreate(this);
	}
};
p._getDOMObject = function()
{
	return document.createElement('DIV');
}

DynLayer._assignElement = function(dlyr,elm,divs) {
	if (!elm ) {
		elm = (divs)? divs[dlyr.id] : dlyr.parent.elm.all[dlyr.id];
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
	if(dlyr._blkBoardElm) dlyr._blkBoardElm = (divs)? divs[dlyr.id+'_blkboard'] : dlyr.parent.elm.all[dlyr.id+'_blkboard'];

	// COMMENTED OUT TO PERMIT CSS PRIORITY
	// by Raphael Pereira <raphael@think-e.com.br>
	if (0 && dlyr.html!=null && dlyr.html!='' && (dlyr.w==null || dlyr.h==null)) {
		var cw = (dlyr.w==null)? dlyr.getContentWidth() : null;
		var ch = (dlyr.h==null)? dlyr.getContentHeight() : null;
		dlyr.setSize(cw,ch);
	}
	
	var i,ch=dlyr.children; 
	for (i=0;i<ch.length;i++) DynLayer._assignElement(ch[i],null,divs);

	if (dlyr._textSelectable==false) elm.onselectstart = dynapi.functions.Deny;

	// prevent dragging of images
	//if (elm.all.tags("img").length) elm.ondragstart = dynapi.functions.False;

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
		// adjust parent size after being moved
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
		var w,tw = this.css.width;
		this.css.width='auto'; // force ie to get width
		if (dynapi.ua.platform=="mac") w = this.elm.offsetWidth;
		else w = parseInt(this.elm.scrollWidth);
		this.css.width=tw;
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
p.setOverflow = function(s)
{
	if(!s) s='';
	this._overflow=s;
	if(this.css) this.css.overflow=s;
	else this._cssOverflow=' overflow:'+s+';';
}
