/*
	DynAPI Distribution
	Fader Animation Extension

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

	requires: DynLayer
*/

Fader = {}; // used by dynapi.library

p = DynLayer.prototype;

p._fadeMode = '';
p.fadeIn = function(inc,ms){
	this._opacity=0;
	this._fadeMode='in';
	this.fadeTo(100,(inc||4),ms);
};
p.fadeOut = function(inc,ms){
	this._opacity=100;
	this._fadeMode='out';
	this.fadeTo(0,inc||4,ms);
};
// Fades an object to "opacity" by "inc" increments in every "ms" millisenconds
p.fadeTo = function(opacity,inc,ms){
	if(this._opacity==null) this._opacity=100;
	inc=(inc)? Math.abs(inc):5;
	this._fadeMs=(ms)? ms:50;
	if (this._opacity>opacity) inc*=-1;
	this._fadeInc=inc;
	this._fadeToOpacity = opacity;
	this._fadeTimeout=window.setTimeout(this+'._fade()',this._fadeMs);
};
p._fade = function(){
	var ua=dynapi.ua;
	var fm=this._fadeMode;
	var inc = this._fadeInc;
	var opac = this._opacity;
	var fopac = this._fadeToOpacity;
	opac+=inc;
	if ((inc<0 && opac<=fopac)|| (inc>0 && opac>=fopac)) opac=fopac;
	this._opacity=opac;
	if(!ua.def) this.setVisible((opac>0)? true:false);
	else if(ua.dom && this.css){
		if(opac>1 && this.visible==false) this.setVisible(true);
		if(ua.ie) this.css.filter='alpha(opacity=' + opac + ')';
		else this.css.MozOpacity = parseInt(opac)/100;
	}
	if(opac!=fopac) this._fadeTimeout=window.setTimeout(this+'._fade()',this._fadeMs);
	else {
		this._fadeMode='';
		window.clearTimeout(this._fadeTimeout);
		this.invokeEvent('fade'+fm);
	
	}
};

p.getOpacity = function(){
	if (this._opacity ||(this._opacity == 0)) return this._opacity;
	else return	this._opacity=100;
};
p.setOpacity = function(n){
	var ua=dynapi.ua;
	if(n>100) n=100;
	else if(n<0)n=0;
	this._opacity = n;
	this.fadeTo(n);
};
