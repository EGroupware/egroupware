/*
	DynAPI Distribution
	DynLayer NS4 Specific Functions

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
	
	requires: dynapi.api.DynLayerBase
*/

p = DynLayer.prototype;
p._ns4IPad = '<img src="'+dynapi.library.path+'gui/images/pixel.gif" width="0" height="0">'; // used with blackboard
p._remove = function() {
	if (this.elm) {
		var p = this.parent;
		if (p && this._alias) p[this._alias]=null;
		if (!p.doc.recycled) p.doc.recycled=[];
		p.doc.recycled[p.doc.recycled.length]=this.elm;
		this.elm.visibility="hide";
		this.elm = null;
		if (this.releaseMouseEvents) this.releaseMouseEvents();
		if (this.releaseKeyEvents) this.releaseKeyEvents();
	}
	/*this.frame = null;
	this.bgImage = null;
	this.bgColor = null;
	this.html = null;
	this.z = null;
	this.w = null;
	this.h = null;
	this.elm = null;
	this.doc = null;
	this.css = null;*/
};
p._create = function() {
	if (this.parent && !this.elm) {
		DynElement._flagPreCreate(this);
		var parentElement = this.parent.isClass('DynLayer')? this.parent.elm : this.parent.frame;
		var elm = new Layer(this.w||0, parentElement);
		if(this._className) elm.className=this._className; // does this work in ns4?
		if(!this._noStyle) {
			if (this.w) elm.clip.width = this.w+this._fixBw;
			if (this.h) elm.clip.height = this.h+this._fixBh;
			if (this.x && this.y) elm.moveTo(this.x,this.y);
			else if (this.x) elm.left = this.x;
			else if (this.y) elm.top = this.y;
			if (this.bgColor!=null) elm.document.bgColor = this.bgColor;
			if (this.clip) {
				var c = elm.clip, cl = this.clip;
				c.top=cl[0], c.right=cl[1], c.bottom=cl[2], c.left=cl[3];
			}
			if (this.z) elm.zIndex = this.z;
			if (this.visible) elm.visibility = 'inherit';
		}
		if (this.children.length || (this.html!=null && this.html!='')) {
			elm.document.write(this.getInnerHTML());
			elm.document.close();
		}
		DynLayer._assignElement(this,elm);
		DynElement._flagCreate(this);
	}
};
DynLayer._getLayerById = function(id,pElm){
	var i,lyrs,elm;
	pElm = (pElm)? pElm:document;
	lyrs = pElm.layers;
	for (i=0;i<lyrs.length;i++){
		elm=lyrs[i];
		if (elm.id==id) return elm;
		else if (elm.layers.length){
			elm = this._getLayerById(id,elm);
			if (elm) return elm;
		}				
	}
};
DynLayer._assignElement = function(dlyr,elm) {
	if (!elm) {
		elm = dlyr.parent.doc.layers[dlyr.id];
		if (!elm) elm=DynLayer._getLayerById(dlyr.id,dlyr.parent.elm);
		if (!elm){
			if(dlyr.isInline) dlyr._create();  // force create() for missing inline layer
			return;
		}
	}
	dlyr.elm = elm;
	dlyr.css = elm;
	dlyr.doc = elm.document;
	if(dlyr._blkBoardElm) {
		dlyr._blkBoardElm = elm.document.layers[dlyr.id+'blkboard'];
		dlyr.doc = dlyr._blkBoardElm.document; // useful for <forms>, images, links, etc
	}
	dlyr.elm._dynobj = dlyr.doc._dynobj = dlyr;
	dlyr._dyndoc = dlyr.parent._dyndoc;

	// COMMENTED OUT TO PERMIT CSS PRIORITY
	// by Raphael Pereira <raphael@think-e.com.br>
	if (0 && dlyr.html!=null && dlyr.html!='' && (dlyr.w==null || dlyr.h==null)) {
		var cw = (dlyr.w==null)? dlyr.getContentWidth() : null;
		var ch = (dlyr.h==null)? dlyr.getContentHeight() : null;
		//var cw = (dlyr.w==null)? dlyr.getElmWidth() : null;
		//var ch = (dlyr.h==null)? dlyr.getElmHeight() : null;
		dlyr.setSize(cw,ch);
	}
	if (dlyr.bgImage!=null) dlyr.setBgImage(dlyr.bgImage);
	
	var i,ch=dlyr.children; 
	for (i=0;i<ch.length;i++) DynLayer._assignElement(ch[i],null);


	if (dlyr._hasKeyEvents) dlyr.captureKeyEvents();
	if (dlyr._hasMouseEvents) dlyr.captureMouseEvents();
	else {
		// assign ._dynobj to images and links
		for (var i=0;i<dlyr.doc.images.length;i++) dlyr.doc.images[i]._dynobj=dlyr; // was _dynobji
		for (var i=0;i<dlyr.doc.links.length;i++) dlyr.doc.links[i]._dynobj=dlyr;
	}
};

p.getOuterHTML = function() {
	// get box fix values
	var fixBw = (this._fixBw)? this._fixBw:0;
	var fixBh = (this._fixBh)? this._fixBh:0;
	var tag='layer',clip='';
	if (fixBw||fixBh) this._fixBoxModel = true;
	if(this._position=='relative') tag='ilayer';
	if(this._noStyle) return '\n<'+tag+' '+this._cssClass+' id="'+this.id+'">'+this.getInnerHTML()+'</'+tag+'>';
	else {
		if (this.clip) clip=' clip="'+this.clip[3]+','+this.clip[0]+','+this.clip[1]+','+this.clip[2]+'"';
		else clip=' clip="0,0,'+((this.w>=0)? this.w+fixBw:0)+','+((this.h>=0)? this.h+fixBh:0)+'"';
		return [
			'\n<'+tag+' ',this._cssClass,' id="'+this.id+'"',
			' left=',(this.x!=null? this.x : 0),
			' top=',(this.y!=null? this.y : 0),
			((this.visible)? ' visibility="inherit"':' visibility="hide"'),
			((this.w!=null)? ' width='+(this.w+fixBw):''),
			((this.h!=null)? ' height='+(this.h+fixBw):''),
			((this.z)? ' zindex='+this.z:''), 
			((this.bgColor!=null)? ' bgcolor="'+this.bgColor+'"':''),
			((this.bgImage!=null)? ' background="'+this.bgImage+'"':''),			
			clip,'>',this.getInnerHTML(),'</'+tag+'>'
		].join('');
	}
};
p.getInnerHTML = function() {
	var i,s = '',ch=this.children;
	if (this.html!=null) {
		if (this.w==null) s += '<nobr>'+this.html+'</nobr>';
		else s+=this.html;
	}
	if (this._blkBoardElm) s='<layer id="'+this.id+'blkboard">'+this._ns4IPad+s+'</layer>';	
	if(ch.length<50) for (i=0;i<ch.length;i++) s+=ch[i].getOuterHTML(); 
	else if(ch.length){
		var ar=['']; // speed improvement for layers with nested children
		for (i=0;i<ch.length;i++) ar[i]=ch[i].getOuterHTML();
		s=s+ar.join('');	
	}
	return s;
};
p.enableBlackboard = function(){
	if (!this._created) this._blkBoardElm=true;
	else if(!this._blkBoardElm){
		var c,i,h='',elm = this.elm;
		if(this.html!=null) h=this.html;		
		var parentElement = this.parent.isClass('DynLayer')? this.parent.elm : this.parent.frame;
		var belm = this._blkBoardElm = new Layer(0, elm);
		this.doc = belm.document;
		this.doc.write(h); this.doc.close();
		belm.visibility = 'inherit';
		for (i=0;i<this.children.length;i++){
			c=this.children[i];
			c.css.zIndex=c.css.zIndex; // reset zindex
		}		
	}
};
p.setLocation = function(x,y) {
	var cx = (x!=null && x!=this.x);
	var cy = (y!=null && y!=this.y);
	if (cx) this.x = x||0;
	if (cy) this.y = y||0;
	if (this.css!=null) {
		if (cx && cy) this.elm.moveTo(this.x, this.y);
		else if (cx) this.css.left = this.x;
		else if (cy) this.css.top = this.y;
		// adjust parent size after being moved
		if((cx||cy) && this.parent._aSz) this.parent._adjustSize();			
	}
	if(this._hasLocationEvents) this.invokeEvent('locationchange');	
	return (cx||cy);
};
p.setPageLocation = function(x,y) {
	if (this.css) {
		if (x!=null) {
			this.css.pageX = x;
			this.x = this.css.left;
		}
		if (y!=null) {
			this.css.pageY = y;
			this.y = this.css.top;
		}
		return true;
	}
	else {
		if (this.isChild) {
			if (x!=null) x = x - this.parent.getPageX();
			if (y!=null) y = y - this.parent.getPageY();
		}
		return this.setLocation(x,y);
	}
};
p.getPageX = function() {return this.css? this.css.pageX : null};
p.getPageY = function() {return this.css? this.css.pageY : null};
p.setVisible = function(b) {
	if (b!=this.visible) {
		this.visible = b;
		if (this.css) this.css.visibility = b? "inherit" : "hide";
	}
};
p.setSize = function(w,h) {
	if (this._useMinSize||this._useMaxSize){
		if (this._minW && w<this._minW) w=this._minW;
		if (this._minH && h<this._minH) h=this._minH;
		if (this._maxW && w>this._maxW) w=this._maxW;
		if (this._maxH && h>this._maxH) h=this._maxH;
	}
	var cw = (w!=null && w!=this.w);
	var ch = (h!=null && h!=this.h);
	if (cw) this.w = w<0? 0 : w;
	if (ch) this.h = h<0? 0 : h;
	if (cw||ch) {
		if (this._hasAnchor) this.updateAnchor(); // update this anchor
		if (this._hasChildAnchors) this._updateAnchors(); // update child anchors
		if (this.css) {
			if (cw) this.css.clip.width = (this.w || 0)+this._fixBw;
			if (ch) this.css.clip.height = (this.h || 0)+this._fixBh;
			// adjust parent size after being sized
			if((cw||ch) && this.parent._aSz) this.parent._adjustSize();
			if (this.updateLayout) this.updateLayout();			
		}
	}
	if(this._hasResizeEvents) this.invokeEvent('resize');
	return (cw||ch);
};
p.setHTML=function(html) {
	var ch = (html!=null && html!=this.html);
	if (ch) {
		this.html = html;
		if (this.css) {
			var i, doc = this.doc;
			var html=(!this._blkBoardElm)? this.html:this._ns4IPad+this.html; // don't ask why! See HTMLContainer
			doc.open();	doc.write(html); doc.close();
			for (i=0;i<doc.images.length;i++) doc.images[i]._dynobj = this;
			for (i=0;i<doc.links.length;i++) doc.links[i]._dynobj = this;
			this._adjustSize();
		}
	}
	if(this._hasContentEvents) this.invokeEvent('contentchange');
};
p.setTextSelectable=function(b) {
	this._textSelectable = b;
	this.addEventListener({
		onmousemove : function(e) {
			e.preventDefault();
		}
	});
	// && this.captureMouseEvents && !this._hasMouseEvents) this.captureMouseEvents();
};
p.getCursor = function() {return this._cursor};
p.setCursor = function(c) {
	if (!c) c = 'default';
	if (this._cursor!=c) this._cursor = c;	
	// Note: not supported in ns4
};
p.setBgColor=function(c) {
	this.bgColor = c;
	if (this.css) this.elm.document.bgColor = c;
};
p.setBgImage=function(path) {
	this.bgImage=path||'none';
	if (this.css) {
		//if (!path) this.setBgColor(this.getBgColor());
		setTimeout(this+'.elm.background.src="'+this.bgImage+'"',1);
	}
};
p.getContentWidth=function() {
	if (this.elm==null) return 0;
	else {
		return this.doc.width;
	};
};
p.getContentHeight=function() {
	if (this.elm==null) return 0;
	else {
		return this.doc.height;
	}
};
p.setClip=function(clip) {
	var cc=this.getClip();
	for (var i=0;i<clip.length;i++) if (clip[i]==null) clip[i]=cc[i];
	this.clip=clip;
	if (this.css==null) return;
	var c=this.css.clip;
	c.top=clip[0], c.right=clip[1], c.bottom=clip[2], c.left=clip[3];
};
p.getClip=function() {
	if (this.css==null || !this.css.clip) return [0,0,0,0];
	var c = this.css.clip;
	if (c) {
		return [c.top,c.right,c.bottom,c.left];
	}
};

