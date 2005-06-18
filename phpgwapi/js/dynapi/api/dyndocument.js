/*
	DynAPI Distribution
	DynDocument Class

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
	
	requires: dynapi.api.DynElement
*/

function DynDocument(frame) {
	this.DynElement = DynElement;
	this.DynElement();
	this.frame = frame;
	this.doc = this.frame.document;
	this._dyndoc = this;
	this.x = 0;
	this.y = 0;
	this.w = 0;
	this.h = 0;
	this._topZIndex = 10000;
	var o = this;
	this.frame.onresize = function() {o._handleResize()};
	this.onResizeNS4 = "reload"; // or "redraw"
	this._created = false;
};
var p = dynapi.setPrototype('DynDocument','DynElement');
p._remove = function() {
	this.elm=null;
	this.doc=null;
	this.frame=null;
};
p.getBgColor = function() {
	return this.bgColor;
};
p.getX = p.getY = p.getPageX = p.getPageY = dynapi.functions.Zero;
p.getWidth = function() {
	if (!this.w) this.findDimensions();
	return this.w;
};
p.getHeight = function() {
	if (!this.h) this.findDimensions();
	return this.h;
};
p.getXScroll = function ()
{
	return (dynapi.ua.ns)? this.frame.pageXOffset : this.elm.scrollLeft;
}
p.getYScroll = function ()
{
	return (dynapi.ua.ns)? this.frame.pageYOffset : this.elm.scrollTop;
}
p.findDimensions = function() {
	this.w=(dynapi.ua.ns||dynapi.ua.opera)? this.frame.innerWidth : (dynapi.ua.ie6) ? 
		this.doc.documentElement.clientWidth : this.elm.clientWidth;
	this.h=(dynapi.ua.ns||dynapi.ua.opera)? this.frame.innerHeight : (dynapi.ua.ie6) ? 
		this.doc.documentElement.clientHeight : this.elm.clientHeight;
};
p.setBgColor = function(color) {
	if (color == null) color='';
	if (dynapi.ua.ns4 && color == '') color = '#ffffff';
	this.bgColor = color;
	this.doc.bgColor = color;
};
p.setFgColor = function(color) {
	if (color == null) color='';
	if (dynapi.ua.ns4 && color == '') color='#ffffff';
	this.fgColor = color;
	this.doc.fgColor = color;
};
p.insertChild = function(c,pos,usebp) { // Blueprint Enabled
	if (c && !c.isInline && c.parent == this) {
		if(pos) c.setPosition(pos);
		DynElement._flagPreCreate(c);
		if(usebp)
			c.isInline=c._noInlineValues=true;
		else {
			this.doc.write(c.getOuterHTML());
			c._inserted = true;
		}
	}
};
p.insertAllChildren = function(usebp,bpSrc) { // Blueprint Enabled
	var i,c,str =[''];
	var ch=this.children;
	for(i=0;i<ch.length;i++) {
		c = ch[i];
		if(!c.isInline && !c._inserted){
			DynElement._flagPreCreate(c);
			if(usebp) 
				c.isInline=c._noInlineValues=true;
			else {
				str[i]=c.getOuterHTML();
				c._inserted = true;
			}
		}
	}
	if(this._hBuffer.length) this.doc.write(this._hBuffer.join('')); // used by addHTML()
	if(usebp){
		if(bpSrc) dynapi.frame.document.write('<script type="text/javascript" language="JavaScript" src="'+bpSrc+'"><\/script>');	
	}
	else {
		this.doc.write(str.join('\n'));
		this.doc.close();
	}
};

p._create = function() {
	var ua=dynapi.ua;
	this._created = true;
	if (ua.ns4) {
		this.css = this.doc;
		this.elm = this.doc;
	}
	else {
		this.elm = this.frame.document.body;
		this.css = this.frame.document.body.style;
		if (ua.ie) {
			this._overflow = this.css.overflow || '';
		}
		if (this._cursor) this.css.cursor = this._cursor;
	}
	this.elm._dynobj = this;
	this.doc._dynobj = this; // DynKeyEvent needs this!
	this.findDimensions();

	this.fgColor = this.doc.fgColor||'';
	this.bgColor = this.doc.bgColor||'';

	var divs;
	// create divs object - speeds up DOM browsers on Win32. Linux & Mac?
	if (ua.ie||ua.dom) {
		divs={};
		var dv,all=(ua.ie||ua.opera)? document.all.tags('div') : document.getElementsByTagName('div');
		var i=0,l=all.length; // very important!
		while (i<l){
			dv=all[i];
			divs[dv.id]=dv;
			i++;
		}
	}
	
	var c,ch=this.children;
	for(i=0;i<ch.length;i++){
		c=ch[i];
		if (c._inserted) c._createInserted(divs);
		else if(c.isInline) c._createInline(divs);
		else c._create();
	};
	this._updateAnchors();	

	if(ua.ie && this._textSelectable==false) this.doc.onselectstart = dynapi.functions.Deny;
	
	if (this.captureMouseEvents) this.captureMouseEvents();
	if (this.captureKeyEvents) this.captureKeyEvents();
	this.invokeEvent('load');
};
p.destroyAllChildren = function() {
	for (var i=0;i<this.children.length;i++) {
		this.children[i]._destroy();
		delete this.children[i];
	}
	this.children = [];
};
p._destroy = function() {
	this.destroyAllChildren();
	delete DynObject.all;
	this.elm = null;
	this.css = null;
	this.frame = null;
};

p._handleResize = function() {
	var w = this.w;
	var h = this.h;
	this.findDimensions();
	if (this.w!=w || this.h!=h) {
		if (dynapi.ua.ns4) {
			if (this.onResizeNS4=="redraw") {
				for (var i=0;i<this.children.length;i++) {
					this.children[i].elm = null;
					if (this.children[i]._created) {
						this.children[i]._created = false;
						this.children[i]._create();
					}
				}
				this.invokeEvent('resize');
			}
			else if (this.onResizeNS4=="reload") {
				this.doc.location.href = this.doc.location.href;
			}
		}
		else {
			this.invokeEvent('resize');
			this._updateAnchors();
		}
	}
};
p.getCursor = function() {return (this._cursor=='pointer')? 'hand':this._cursor};
p.setCursor = function(c) {
	if (!c) c = 'default';
	else c=(c+'').toLowerCase();
	if (!dynapi.ua.ie && c=='hand') c='pointer';
	if (this._cursor!=c) {
		this._cursor = c;
		if (this.css) this.css.cursor = c;
	}
};
p.setTextSelectable = function(b){
	this._textSelectable = b;	
	if(!dynapi.ua.ie) this.captureMouseEvents();
	else{
		if (this.doc) this.doc.onselectstart = b? dynapi.functions.Allow : dynapi.functions.Deny;
	}
	if (!b) this.setCursor('default');
};
p.showScrollBars = function(b){
	if(b==this._showScroll) return;
	else this._showScroll=b;
	if(dynapi.ua.ie){
		window.setTimeout('document.body.scroll="'+((b)? 'yes':'no')+'"',100);
	}else if(dynapi.ua.ns||dynapi.ua.opera){
		if(b){
			this._docSize=[document.width,document.height];
			document.width = this.frame.innerWidth;
			document.height = this.frame.innerHeight;
		}else if(this._docSize){
			document.width = this._docSize[0];
			document.height = this._docSize[1];
		}
	}
};
p.writeStyle = function(s){
	// note: don't ever add \n to the end of the following strings or ns4 will choke!
	if(!s) return;
	var css ='<style><!-- ';
	for(var i in s)	css +='.'+i+' {'+s[i]+';} ';
	css += ' --></style>';
	document.write(css);
};

p._hBuffer = [];
p.addHTML = function(html){
	var elm,ua = dynapi.ua;
	var hbuf=this._hBuffer;
	var cnt=(this._hblc)? this._hblc++:(this._hblc=1);
	if (ua.ns4) {
		html='<nobr>'+html+'<nobr>';
		if(!this._created) hbuf[cnt]=html;
		else {
			elm=new Layer(0,this.frame);
			elm.left=elm.top=0;
			var doc=elm.document;
			elm.clip.width=dynapi.document.w;
			elm.clip.height=dynapi.document.h;
			doc.open();doc.write(html);doc.close();
			elm.visibility = 'inherit';
		}
	}
	else {
		var pelm=this.elm;
		if(!this._created) hbuf[cnt]=html;
		else {
			if(ua.ie){
				pelm.insertAdjacentHTML("beforeEnd",html);
				elm = pelm.children[pelm.children.length-1];				
			}
			else{
				var r = pelm.ownerDocument.createRange();
				r.setStartBefore(pelm);
				var ptxt = r.createContextualFragment(html);
				pelm.appendChild(ptxt);
				elm = pelm.lastChild;				
			}
		}
	}
};

function main() {
	if (dynapi.document==null) {
		dynapi.document = new DynDocument(dynapi.frame);
		if (dynapi.loaded) dynapi.document._create();
		else dynapi.onLoad(function() {
			dynapi.document._create();
		});
	}
};
if (!dynapi.loaded) main();

