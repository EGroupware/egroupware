/*
	DynAPI Distribution
	Fader Animation Extension

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

	requires: DynLayer, Fader
*/

function TextAnimation(x,y,text,animBy,dlyr){
	this.EventObject = EventObject;
	this.EventObject();
	
	this._chars=[];
	this._lyrPool=[];
	this._text=text;
	this._animBy=null;
	this._inUse=0;
	this._dlyr=(dlyr||dynapi.document);

	this.setLocation(x,y);
	this.animateBy(animBy);
	this.visible=true;
	
	var me=this; // use delegate "me"
	var fn=function(){
		me._created=true;
		me._split();
	}
	if(this._dlyr!=dynapi.document) this._dlyr.onCreate(fn);
	else {
		var e={onload:fn};
		this._dlyr.addEventListener(e);
	}
};
var p = dynapi.setPrototype('TextAnimation','EventObject');
p._clear = function(){
	var l;
	var p=this._lyrPool;
	var c=this._chars;
	for(var i=0;i<c.length;i++){
		l=c[i];
		l.setVisible(false);
		l.removeAllEventListeners();
		p[p.length]=l;
	}
	this._chars.length=0;
};
p._createCharLayer=function(t){
	var l;
	var p=this._lyrPool;
	if (!p.length) l = this._dlyr.addChild(new DynLayer());
	else {
		l = p[p.length-1];
		p.length--;
	};
	l._ta=this;
	l.setHTML(t);
	if(l._created) {
		// resize char layer
		l.setSize(1,1); // why mozilla,gecko,ie??
		l.setSize(l.getContentWidth(),l.getContentHeight());
	}
	l.setVisible(this.visible);
	if(dynapi.ua.ie) l.css.filter='alpha(opacity=100)';
	else l.css.MozOpacity = 1;
	return l;
};
p._exec = function(cmd,i){
	window.setTimeout(cmd,(this._ms*i)+5);
};
p._resize=function(align){
	var x=this.x;
	var y=this.y;
	var i,l,c=this._chars;
	for(i=0;i<c.length;i++){
		l=c[i]; // resize char layer
		if(l._created) {
			l.setSize(1,1); // why mozilla,gecko,ie??
			l.setSize(l.getContentWidth(),l.getContentHeight());
			if(align) {
				l.setLocation(x,y);
				x+=l.w;
			}
		}
	}
};
p._split = function(){
	var i,ar,lyr;
	var t=this._text;
	var c=this._chars;
	var x=this.x;
	var y=this.y;
	if(!t||!this._created) return;
	this._clear();
	if(this._animBy=='all') {	// By all
		t='<nobr>'+t+'</nobr>';
		lyr=c[0]=this._createCharLayer(t);
		lyr.setLocation(x,y);
	}else {						// By word or letter
		if (this._animBy=='word') {
			t=t.replace(/\s/g,' &nbsp; ');
			ar=t.split(' ');
		}
		else {	
			ar=t.split('');
		}
		for(i=0;i<ar.length;i++){
			if (ar[i]==' ') ar[i]='&nbsp;';
			lyr=c[i]=this._createCharLayer(ar[i]);
			lyr.setLocation(x,y);
			x+=lyr.w;
		}
	}
	if(lyr) lyr.addEventListener(TextAnimation._tiggerEvents);
};
p.animateBy=function(s,delay){	// all, letter, word  
	this._animBy=s||this._animBy||'letter';
	this.setDelay(delay);
	this._split();
};
p.getCharLayer = function(i){
	if (i>=0 && i<this.chars.length) return this.chars[i];
};
p.setDelay = function(delay){
	this._ms=delay||this._ms||50;
};
p.setFont=function(size,family){

};
p.setLocation=function(x,y){
	x=x||0;	y=y||0;
	var byX=x-this.x;
	var byY=y-this.y;
	var c=this._chars;
	this.x=x; this.y=y;
	for (var i=0;i<c.length;i++){
		l=c[i];	l.setLocation(l.x+byX,l.y+byY);
	}
};
p.setText=function(t,animBy,delay){
	this._text=t||'';
	this.setDelay(delay);
	this.animateBy(animBy);
};
p.setVisible = function(b){
	var c=this._chars;
	this.visible=b;
	for (var i=0;i<c.length;i++) c[i].setVisible(b);
};

// Trigger Events
var fn = function(e){
	var o=e.getSource();
	o._ta._inUse--;
	if(!o._ta._inUse) window.setTimeout(o._ta+'.invokeEvent("animfinish")',55);
};
TextAnimation._tiggerEvents = {
	onpathfinish:fn,
	onfadein:fn,
	onfadeout:fn
};


// Effects
// ------------------------
// Apear
p.appear = function(){
	var i,ext,c=this._chars;
	this._exec(this+'.invokeEvent("animstart")',1);
	for (i=0;i<c.length;i++) {
		if(!this._inUse) c[i].setVisible(false);
		if(i==(c.length-1)) ext=c[i]+'.invokeEvent("pathfinish")';
		this._exec(c[i]+'.setVisible(true);;'+ext,i);
	}
	this._inUse++;
};
// Bounce
p.bounce = function(h,modifier){
	var i,l,c;
	c=this._chars;
	h=(h||100);
	modifier=(modifier<-1)? -1:(modifier||0);
	this._exec(this+'.invokeEvent("animstart")',1);
	// setup bounce chars
	for (i=0;i<c.length; i++) {
		l=c[i];
		l._bonctmr=i<<modifier;
		l._boncyv=0;
		l._boncmaxv=8;
		l._boncy=-l.h;
		l.setLocation(null,this.y-h);
		if(!this._inUse) l.setVisible(true);
		this._exec(this+'._startBounce('+i+','+h+');',i);
	}
	this._inUse++;
};
p._startBounce = function(i,h){
	var l,y,c,ext;
	var c=this._chars;
	l=c[i];
	if (l._bonctmr>0) l._bonctmr--;
	else {
		yv=l._boncyv;
		//y=l._boncy;
		l._boncy+=yv;
		if (yv<l._boncmaxv) l._boncyv++;
		if (l._boncy>h-l.h) {
			l._boncy=h-l.h;
			l._boncyv=-l._boncyv;
			if (l._boncmaxv>0) l._boncmaxv--;
		}
		l.setLocation(null,(this.y-h)+l._boncy+l.h);
	}

	if(l._boncmaxv!=0){
		this._exec(this+'._startBounce('+i+','+h+');'+ext,1);
	}
	else if(i==(c.length-1)) {
		this._exec(l+'.invokeEvent("pathfinish")',1);
	}	
};
// Fade In
p.fadeIn = function(inc,ms){
	var i,c=this._chars;
	this._exec(this+'.invokeEvent("animstart")',1);
	for (i=0;i<c.length;i++) this._exec(c[i]+'.fadeIn('+inc+','+ms+');',i);
	this._inUse++;
};
// Fade Out
p.fadeOut = function(inc,ms){
	var i,c=this._chars;
	this._exec(this+'.invokeEvent("animstart")',1);
	for (i=0;i<c.length;i++) this._exec(c[i]+'.fadeOut('+inc+','+ms+');',i);
	this._inUse++;
};
// Fly From
p.flyFrom = function(x,y,inc,ms){
	var i,l,c=this._chars;
	this._exec(this+'.invokeEvent("animstart")',1);
	for (i=0;i<c.length;i++) {
		l=c[i];
		this._exec(
			((!this._inUse)? l+'.setVisible(true);':'')
			+l+'.slideTo('+l.x+','+l.y+','+inc+','+ms+');'
		,i);
		l.setLocation(x,y);
	}
	this._inUse++;	
};
p.zoomText = function(from,to,inc,ms){
	var i,l,c;
	c=this._chars;
	this._exec(this+'.invokeEvent("animstart")',1);
	// setup chars
	for (i=0;i<c.length; i++) {
		l=c[i];
		l._zTo = to||20;
		l._zFrom = from||10;
		l._zMs=(ms)? ms:50;
		l._zInc=(inc)? Math.abs(inc):5;
		l.css.fontSize=l._zFrom+'px';
		if (l._zFrom>l._zTo) l._zInc*=-1;
		if(!this._inUse) l.setVisible(true);
		this._exec(this+'._startZoom('+i+');',i);
	}
	this._inUse++;
};
p._startZoom= function(i){
	var l,y,c,ext;
	var l=this._chars[i];
	var inc = l._zInc;
	var from = l._zFrom;
	var to = l._zTo;
	from+=inc;
	if ((inc<0 && from<=to)|| (inc>0 && from>=to)) from=to;
	l._zFrom=from;
	l.css.fontSize=from+'px';
	l.setSize(0,0); // ??
	l.setSize(l.getContentWidth(),l.getContentHeight());
	if(i==0) l.setLocation(null,this.y-(l.h/2));
	else l.setLocation(this._chars[i-1].x+this._chars[i-1].w,this.y-(l.h/2));
	if(from!=to) l._zTmr=window.setTimeout(this+'._startZoom('+i+')',l._zMs);
	else if(i==(this._chars.length-1)) {
		this._exec(l+'.invokeEvent("pathfinish")',1);
	}	
};


// to-do:
p.wave = function(){};
p.nudge = function(){};
p.quake = function(){};
