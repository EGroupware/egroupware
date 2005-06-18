/*
	DynAPI Distribution
	DynLayer Base/Common Class

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
	
	requires: dynapi.api.DynDocument
*/

var DynLayerBase = {};	// used by library
function DynLayer(html,x,y,w,h,color,image) {
	this.DynElement = DynElement;
	this.DynElement();

	if (html && typeof(html)=='object'){ // typeof more stable than constructor when creating layers from another frame
		var args=html; // dictionary input
		html=args.html;
		x = args.x;
		y = args.y;
		w = args.w;
		h = args.h;
		color = args.color;
		image = args.image;
		this.z = (args.zIndex||1);
		this._saveAnchor = args.anchor;
		this.visible = (args.visible==false)? false:true;
		this._textSelectable = (args.textSelectable==false)?false:true;
		if (args.id)
			this.setID(args.id,true);
	}
	else {
		this.visible = true;
		this.z = 1;
		this._saveAnchor = false;
		this._textSelectable = true;
	}
	
	this.x = x||0;
	this.y = y||0;
	this.w = w;
	this.h = h;
	this.bgColor = color;
	this.bgImage = image;
	this.html = (html!=null)? html+'':null; // convert html to string
	this.elm = null;
	this.doc = null;
	this.css = null; 
};
var p = dynapi.setPrototype('DynLayer','DynElement');
p._cssBorder = '';
p._fixBw = 0;
p._fixBh = 0;
p._adjustSize=function(){
	var aw=this._aSzW;
	var ah=this._aSzH;
	if(this._created && (aw||ah)) {
		var i,c,w=0,h=0;
		// get furthest child
		for (i=0;i<this.children.length;i++){
			c=this.children[i];
			if(c && w<(c.x+c.w)) w=c.x+c.w;
			if(c && h<(c.y+c.h)) h=c.y+c.h;
		}
						
		if(aw) {
			i = this.getContentWidth();
			if(w<i) w=i;
			if(w!=this.w) this.setWidth(w); 	// set width first
		}
		if(ah) {
			i = this.getContentHeight();
			if(h<i) h=i;
			if(h!=this.h) this.setHeight(h);	// set height after width
		}
	}
};
p._destroy = function() {
	this._destroyAllChildren();
	this.removeAllEventListeners();
	if (this.elm) this._remove();
	this.setAnchor(null); // remove anchor
	this.frame = null;
	this.bgImage = null;
	this.bgColor = null;
	this.html = null;
	this.x = null;
	this.y = null;
	this.w = null;
	this.h = null;
	this.z = null;
	this.doc = null;
	this.css = null;
	this._dyndoc = null;
	this.parent = null;
	this._blkBoardElm = null;
	DynObject.all[this.id] = null;
};
p._destroyAllChildren = function() {
	var aSz = this._aSz;
	this._aSz = false; // prevent children from adjusting parent's size when removed
	for (var i=0;i<this.children.length;i++) {
		this.children[i]._destroy();
		delete this.children[i];
	}
	this.children.length=0;
	this._aSz = aSz;
};
p._remove = function() {	//! Overwritten by NS4
	var p = this.parent;
	if (p && this._alias) p[this._alias]=null;
	if (p && this.elm) {
		//this.elm.style.visibility = "hidden";
		//this.elm.innerHTML = "";
		//this.elm.outerHTML = "";
		var pref=p.elm;
		if(document.getElementById && document.childNodes){
			if(this.elm.parentNode) pref = this.elm.parentNode; // used with relative layers
			pref.removeChild(this.elm);
		}
		else if (pref && pref.children){
			this.elm.outerHTML='';
		}
		this.elm = null;
		if (this.releaseMouseEvents) this.releaseMouseEvents();
		if (this.releaseKeyEvents) this.releaseKeyEvents();
	}
	/*
	this.frame = null;
	this.bgImage = null;
	this.bgColor = null;
	this.html = null;
	this.z = null;
	this.w = null;
	this.h = null;
	this.elm = this.css = this.doc = null;
	*/
};
p._createInserted = function(divs){
	DynLayer._assignElement(this,null,divs); //! NS4 will ignore divs
	DynElement._flagCreate(this);
};
p.getOuterHTML=function() {	//! Overwritten by NS4
	// get box fix values
	var fixBw = (this._fixBw)? this._fixBw:0;
	var fixBh = (this._fixBh)? this._fixBh:0;
	if (fixBw||fixBh) this._fixBoxModel = true;
	if (this._noStyle) return '<div '+this._cssClass+' id="'+this.id+'">'+this.getInnerHTML()+'</div>';
	else {
		var s,clip='',bgimage=' background-image:none;';
		if(this.bgImage!=null) bgimage=' background-image:url('+this.bgImage+');';
		if (this.clip) clip=' clip:rect('+this.clip[0]+'px '+this.clip[1]+'px '+this.clip[2]+'px '+this.clip[3]+'px);';
		else if (this.w!=null && this.h!=null) clip=' clip:rect(0px '+(this.w+fixBw)+'px '+(this.h+fixBh)+'px 0px);';
		// modify box fix values
		if (!dynapi.ua.ie && !dynapi.ua.opera) fixBw = 0;
		if (!dynapi.ua.ie) fixBh = 0;
		return [
			'\n<div '+this._cssClass+' id="'+this.id+'" style="',
			' left:',(this.x!=null? this.x : 0),'px;',
			' top:',(this.y!=null? this.y : 0),'px;',		
			((this.w!=null)? ' width:'+(this.w+fixBw)+'px;':''),
			((this.h!=null)? ' height:'+(this.h+fixBh)+'px;':''),
			((this.z)? ' z-index:'+this.z+';':''),
			((this._cursor!=null)? ' cursor:'+this._cursor+';':'cursor:auto;'),
			((this.bgColor!=null)? ' background-color:'+this.bgColor+';':''),
			((this.visible==false)? ' visibility:hidden;':' visibility:inherit;'),
			bgimage,
			clip,
			this._cssBorder,
			this._cssOverflow,
			this._cssPosition,
			';">',
			this.getInnerHTML(),
			'</div>'
		].join('');	
	}
};
p.getInnerHTML=function() {	//! Overwritten by NS4
	var s = '';
	var i,ch=this.children;
	if (this.html!=null) s+=this.html;
	if (this._blkBoardElm) s=('<div id="'+this.id+'_blkboard">'+s+'</div>');	
	if (ch.length<50) for (i=0;i<ch.length;i++) s+=ch[i].getOuterHTML(); 
	else if(ch.length){
		var ar=['']; // speed improvement for layers with nested children
		for (i=0;i<ch.length;i++) ar[i]=ch[i].getOuterHTML();
		s=s+ar.join('');
	}
	return s;
};

p.getPageX = function() {return (this.isChild)? this.parent.getPageX()+(this.x||0) : this.x||0}; //! Overwritten by NS4
p.getPageY = function() {return (this.isChild)? this.parent.getPageY()+(this.y||0) : this.y||0}; //! Overwritten by NS4

p.setAutoSize = function(w,h){	
	this._aSzW = w; // automatically adjust the size of layer to the size of its content
	this._aSzH = h;
	this._aSz = w||h;
	if(this._aSz) this._adjustSize();
}; 

p._cssClass = '';
p.setClass = function(c,noInlineStyle){
	this._class=c;
	if(this.elm) this.elm.className=c;
	else {
		this._cssClass=(c)? 'class="'+c+'"':'';
		this._noStyle=noInlineStyle;
	}
};

p.setVisible = function(b) { //! Overwritten by NS4
	//if (b!=this.visible) {
		this.visible = b;
		if (this.css) this.css.visibility = b? "inherit" : "hidden";
	//}
};
p.setSize = function(w,h) { //! Overwritten by NS4
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
			if (cw) this.css.width = this.w||0;
			if (ch) this.css.height = this.h||0;
			if (cw || ch) {				
				if(this._needBoxFix) BorderManager.FixBoxModel(this,true);
				else this.css.clip = 'rect(0px '+(this.w||0)+'px '+(this.h||0)+'px 0px)';
				// adjust parent size after being sized
				if(this.parent._aSz) this.parent._adjustSize();
			}
			if (this.updateLayout) this.updateLayout(); // what's this?
		}
	}
	if(this._hasResizeEvents) this.invokeEvent('resize');
	return (cw||ch);
};
p.setMaximumSize = function(w,h){
	this._maxW=w; this._maxH=h;
	this._useMaxSize=(w!=h!=null);
	w=(this.w>w)?w:this.w;
	h=(this.h>h)? h:this.h;
	this.setSize(this.w,this.h);
};
p.setMinimumSize = function(w,h){
	this._minW=w; this._minH=h;
	this._useMinSize=(w!=h!=null);
	this.setSize(this.w,this.h);
};

p._position  = 'absolute';
p._cssPosition = ' position:absolute';
p.setPosition = function(p){
	if(p!='static' && p!='relative' && p!='fixed' && p!='absolute') p='absolute';
	this._position=p;
	if (this.css) this.css.position=p;
	else this._cssPosition = ' position:'+p;
};

p._overflow='hidden';
p._cssOverflow =' overflow:hidden;';
p.getOverflow = function(){return this._overflow};
p.setOverflow = function(s){
//	if(!s) s='default';
	this._overflow=s;
	if(this.css) this.css.overflow=s;
	else this._cssOverflow=' overflow:'+s+';';
};

p.getAnchor = function(){
	if(!this.parent) return this._saveAnchors;
	else if (this.parent._childAnchors) {
		return this.parent._childAnchors[this.id];
	}
};
p.setAnchor = function(anchor) {
	if (anchor == null) {
		delete this._saveAnchor;
		if (this.parent && this.parent._childAnchors && this.parent._childAnchors[this.id]) delete this.parent._childAnchors[this.id];
		this._hasAnchor = false;
	}
	else if (this.parent) {
		if (!this.parent._childAnchors) this.parent._childAnchors = {};
		var a = this.parent._childAnchors;
		a[this.id] = anchor;
		this.parent._updateAnchor(this.id);
		this._hasAnchor = this.parent._hasChildAnchors = true;
	}
	else this._saveAnchor = anchor;
};
p.setX=function(x) {this.setLocation(x,null)};
p.setY=function(y) {this.setLocation(null,y)};
p.getX=function() {return this.x||0};
p.getY=function() {return this.y||0};
p.setPageX = function(x) {this.setPageLocation(x,null)};
p.setPageY = function(y) {this.setPageLocation(null,y)};
p.getVisible=function() {return this.visible};
p.getZIndex=function() {return this.z};
p.setZIndex=function(z) {
	if (typeof(z)=="object") {
		if (z.above) this.z = z.above.z + 1;
		else if (z.below) this.z = z.below.z - 1;
		else if (z.topmost && !this.parent) this.z = (DynLayer._z)? (DynLayer._z++):(DynLayer._z=1000);
		else if (z.topmost) {
			var topZ=10000,ch=this.parent.children;
			for(var i=0;i<ch.length;i++) if (ch[i].z>topZ) topZ=ch[i].z;
			this.parent._topZ = topZ+2;
			this.z = this.parent._topZ;
		}
	}
	else this.z = z;
	if (this.css) this.css.zIndex = this.z;
};
p.getHTML = function() {return this.html};
p.setWidth=function(w) {this.setSize(w,null)};
p.setHeight=function(h) {this.setSize(null,h)};
p.getWidth=function() {return this.w||0};
p.getHeight=function() {return this.h||0};
p.getBgImage=function() {return this.bgImage};
p.getBgColor=function() {return this.bgColor};
p.setBgColor=function(c) {	//! Overwritten by NS4
	if (c==null) c = 'transparent';
	this.bgColor = c;
	if (this.css) this.css.backgroundColor = c;
};
p.setBgImage=function(path) {	//! Overwritten by NS4
	this.bgImage=path;
	if (this.css) this.css.backgroundImage='url('+path+')';
};
p.setClip=function(clip) {	//! Overwritten by NS4
	var cc=this.getClip();
	for (var i=0;i<clip.length;i++) if (clip[i]==null) clip[i]=cc[i];
	this.clip=clip;
	if (this.css==null) return;
	var c=this.css.clip;
	this.css.clip="rect("+clip[0]+"px "+clip[1]+"px "+clip[2]+"px "+clip[3]+"px)";
};
p.getClip=function() {	//! Overwritten by NS4
	if (this.css==null || !this.css.clip) return [0,0,0,0];
	var c = this.css.clip;
	if (c) {
		if (c.indexOf("rect(")>-1) {
			c=c.split("rect(")[1].split(")")[0];
			c=c.replace(/(\D+)/g,',').split(",");
			for (var i=0;i<c.length;i++) c[i]=parseInt(c[i]);
			return [c[0],c[1],c[2],c[3]];
		}
		else return [0,this.w,this.h,0];
	}
};
p.slideTo = function(endx,endy,inc,speed) {
	if (!this._slideActive) {
		var x = this.x||0;
		var y = this.y||0;
		if (endx==null) endx = x;
		if (endy==null) endy = y;
		var distx = endx-x;
		var disty = endy-y;
		if (x==endx && y==endy) return;
		var num = Math.sqrt(Math.pow(distx,2) + Math.pow(disty,2))/(inc||10)-1;
		var dx = distx/num;
		var dy = disty/num;
		this._slideActive = true;
		this._slide(dx,dy,endx,endy,num,this.x,this.y,1,(speed||20));
	}
};
p.slideStop = function() {
	this._slideActive = false;
	//this.invokeEvent('pathcancel');
};
p._slide = function(dx,dy,endx,endy,num,x,y,i,speed) {
	if (!this._slideActive) this.slideStop();
	else if (i++ < num) {
		this.invokeEvent('pathrun');
		if (this._slideActive) {
			x += dx;
			y += dy;
			this.setLocation(Math.round(x),Math.round(y));
			setTimeout(this+'._slide('+dx+','+dy+','+endx+','+endy+','+num+','+x+','+y+','+i+','+speed+')',speed);
		}
		//else this.slideStop();
	}
	else {
		this._slideActive = false;
		this.invokeEvent('pathrun');
		this.setLocation(endx,endy);
		this.invokeEvent('pathfinish');
	}
};
