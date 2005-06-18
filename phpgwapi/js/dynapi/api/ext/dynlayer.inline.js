/*
	DynAPI Distribution
	DynLayer Inline Extension

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
	
	requires: dynapi.api.DynLayer
*/

var DynLayerInline = {};

DynLayer.getInline = function (id, p) {
	var elm;
	var pobj;
	if (!p) pobj = dynapi.document;
	else if (p.isClass && p.isClass('DynElement')) pobj = p;
	
	if (pobj) {
		if (dynapi.ua.ns4) elm = pobj.doc.layers[id];
		else if (dynapi.ua.ie) elm = pobj.doc.all[id];
		else if (dynapi.ua.dom) elm = pobj.doc.getElementById(id);
	}
	if (!elm) return alert("DynLayerInline Error: did not find element "+id);

	var dlyr = new DynLayer();
	dlyr.setID(id);
	dlyr.parent = pobj;
	dlyr.elm = elm;
	if (dynapi.ua.ns4) dlyr.doc = elm.document;
	DynLayer._importInlineValues(dlyr);
	DynLayer._assignElement(dlyr,elm);
	DynElement._flagCreate(dlyr);
	return dlyr;
};

DynLayer.prototype._createInline = function (divs) {
    if (this.parent && !this.elm) {
		var ch=this.children;
		DynLayer._assignElement(this,null,divs);
		DynLayer._importInlineValues(this);
		for (var i=0;i<ch.length;i++) DynLayer._importInlineValues(ch[i]);	
		DynElement._flagCreate(this);
    }
};

DynLayer._importInlineValues = function(dlyr) {
	if(dlyr && dlyr._noInlineValues) return;
	if (dynapi.ua.def) {
		if (dynapi.ua.ie) {
			var css = dlyr.elm.currentStyle;
			dlyr.x = parseInt(css.left);
			dlyr.y = parseInt(css.top);
			dlyr.w = dynapi.ua.ie4? css.pixelWidth : dlyr.elm.offsetWidth;
			dlyr.h = dynapi.ua.ie4? css.pixelHeight : dlyr.elm.offsetHeight;
			dlyr.bgImage = css.backgroundImage;
			dlyr.bgColor = css.backgroundColor;
			dlyr.html = dlyr.elm.innerHTML;
		}	
		else if (dynapi.ua.dom) {
			var css = dlyr.elm.style;
			dlyr.x = parseInt(dlyr.elm.offsetLeft);
			dlyr.y = parseInt(dlyr.elm.offsetTop);
			dlyr.w=  dlyr.elm.offsetWidth;
			dlyr.h= dlyr.elm.offsetHeight;
			dlyr.bgImage = css.backgroundImage;
			dlyr.bgColor = css.backgroundColor;
			dlyr.html = dlyr.elm.innerHTML;
		}

	}
	else if (dynapi.ua.ns4) {
		var css = dlyr.elm;
		dlyr.x = parseInt(css.left);
		dlyr.y = parseInt(css.top);
		dlyr.w = css.clip.width;
		dlyr.h = css.clip.height;
		dlyr.clip = [css.clip.top,css.clip.right,css.clip.bottom,css.clip.left];
		dlyr.bgColor = dlyr.doc.bgColor!=''? dlyr.doc.bgColor : null;
		dlyr.bgImage = css.background.src!=''? css.background.src : null;
		dlyr.html = '';
	}
	dlyr.z = css.zIndex;
	var b = css.visibility;
	dlyr.visible = (b=="inherit" || b=="show" || b=="visible" || b=="");
};

// Generate Blueprint
DynElement.prototype.getBlueprint = function(type) {
	var i,c,ht,str =[];
	var f,ch=this.children;
	for(i=0;i<ch.length;i++) {
		c = ch[i];
		DynElement._flagPreCreate(c);
		ht=c.getOuterHTML();
		if(!type || type=='css') str[i]=ht;
		else {
			ht=ht.replace(/\'/g,'\\\'');
			ht=ht.replace(/\r/g,'\\r');
			ht=ht.replace(/\n/g,'\\n');
			str[str.length]='_bw(\''+ht+'\');';
		}
	}
	if(!type || type=='css') str=str.join('');
	else str=str.join('\n');
	if(type=='css') {	// generate style sheet from blueprints
		var ar=str.split('<div');
		for(i=0;i<ar.length;i++){
			ar[i]=ar[i].replace(/(.+)id="(.+)" style="(.+)"(.+)/g,'#$2 {$3}');
		}
		str=ar.join('');
	}
	return str;
};
DynElement.prototype.generateBlueprint = function(type) {
	var url=dynapi.library.path+'ext/blueprint.html';
	var win=window.open(url,'blueprint','width=500,height=350,scrollbars=no,status=no,toolbar=no');
	var f=win.document.forms['frm'];
	f.txtout.value=this.getBlueprint(type);
};

// Blueprint Document write
_bw = function(str){
	document.write(str);
};