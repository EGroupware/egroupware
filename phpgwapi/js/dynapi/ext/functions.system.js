/*
	DynAPI Distribution
	dynapi.functions.System extension	
*/

var f = dynapi.functions;
f.System = System = {}; // used by dynapi.library

// System Functions ---------------------------------

f.coalesce=function(){
	var a,i;
	for(i=0;arguments.length;i++){
		a=arguments[i];
		if(a!=null && a!='' && a!=undefined) return a;
	}
}
f.choose = function(index){
	if(isNaN(index)) return;
	if (arguments.length>index) return arguments[index+1];
};
f.cloneObject = function(src) {
	if(!src) return;
	var i,tar;
	if(typeof(src)!='object') return src;
	else {
		if((src.constructor+'')==(Array+'')) tar=[];
		else if((src.constructor+'')==(Date+'')) return src;
		else tar={};
	};
	for(i in src) {
		if(typeof(src[i])!='object') tar[i]=src[i];
		else tar[i]=this.cloneObject(src[i]);
	}
	return tar;
};
f.copyObject = function(from,to,noclone) {
	var i;
	if (to && !noclone) to=this.cloneObject(to);
	else if(to && noclone) to=to;
	else {
		if(typeof(from)=='object') {
			if((from.constructor+'')==(Array+'')) to=[];
			else if((from.constructor+'')==(Date+'')) return from;
			else to={};
		};
	}
	for(i in from) {
		if(typeof(from[i])!='object') to[i]=from[i];
		else to[i]=this.copyObject(from[i],to[i],true);
	}
	return to;
};
f.getElementById = function(id,parentLyr){
	if (document.all) return document.all[id];
	else if(document.getElementById) return document.getElementById(id);
	else if(document.layers){
		var i,nLayers,layer;
		parentLyr = (parentLyr)? parentLyr:document;
		nLayers = parentLyr.layers;
		for (i=0;i<nLayers;i++){
			layer=nLayers[i];
			if (layer.id == id) return layer;
			else if (layer.layers.length){
				layer = this.getElementById(id,layer);
				if (layer) return layer;
			}				
		}
	}
};
f.isNull=function(value,_default){
	if(value==null||value==''||value=='undefined') return _default;
	else return value;
};
f.lookUp = function(value,array){
	var i; if(!array) return;
	for(i=0;i<array.length;i++){
		if(value==array[i]) return i;
	}
};
f.nullIf = function(){
	var a,i;
	for(i=0;arguments.length;i++){
		a=arguments[i];
		if(a!=null && a!='' && a!=undefined) return null;
	}
};
