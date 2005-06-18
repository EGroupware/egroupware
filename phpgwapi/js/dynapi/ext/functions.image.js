/*
	DynAPI Distribution
	dynapi.functions.Image extension	
*/

var f = dynapi.functions;
f.Image = {}; // used by dynapi.library

// Image Functions ---------------------------

f._imgTTL = 30000; // Image Time To Load (ms)
f.getImage = function(src,w,h,params) {
	var img,name,p=params;
	if(!p) name=src;
	else name=(!p.alias)? src:p.alias;
	img = dynapi.ximages[name];
	if(!img || (img && img.params!=params)){ // if user enters a new set of params then create a new image object
		img=dynapi.ximages[name] = (w!=null&&h!=null)? new Image(w,h) : new Image();
		img.w=w||null;
		img.h=h||null;
		img.src=src;img.params=p;
		img.getHTML=dynapi._imageGetHTML;
		img.reload=dynapi._imageReload;
		img.dtStart=new Date();
		if(p) {
			var f=dynapi.functions;
			if(p.oversrc) f.getImage(p.oversrc);
			if(p.downsrc) f.getImage(p.downsrc);
		}
		if(!this._imgTmr && this._imgProgFn) this._imageProgress();
	}
	return img;
};
f.getFailedImages = function(){
	var ar=[];
	for(i in dynapi.ximages){
		img=dynapi.ximages[i];
		if(img && img.failed) ar[ar.length]=img;
	}
	return ar;
};
f.captureImageProgress=function(fn){ //fn = fn(completed,failed,total);
	this._imgProgFn=fn;
	this._imageProgress();
};
f._imageProgress = function(){
	var i,c=0,f=0,t=0;
	var img,dtEnd = new Date;
	var fn=this._imgProgFn;
	for(i in dynapi.ximages){
		img=dynapi.ximages[i];
		if(img && img.complete!=null){
			t++;
			img.failed=(!img.complete && (dtEnd-img.dtStart)>this._imgTTL)? true:false;
			if (img.complete) c++;
			else if(img.failed) f++;
		}
	}
	if(fn) fn(c,f,t);
	if(c+f<t) this._imgTmr=window.setTimeout('dynapi.functions._imageProgress()',100);
	else this._imgTmr=0;
};
f.setImageTTL = function(ms){ 
	this._imgTTL=ms;
};


dynapi._imageReload = function(){
	var t=this.src;
	this.src='';
	this.src=t;	
	this.dtStart=new Date();
	this.failed=false;
	dynapi.functions._imageProgress();
};
dynapi._imageHookArray={};
dynapi._imageHook=function(anc,id,img,act,iSrc){
	var rt,f,tf,p=dynapi._imageHookArray[id];
 	if(img && iSrc) img.src = iSrc;
 	if(p) { 		
		f=p['on'+((act!='click')?'mouse':'')+act];tf=typeof(f);
		if(f) rt=((tf=='string')? eval(f):f(act,anc,img,iSrc));
		if(anc && !dynapi.ua.ns4) anc.blur();
	}
	return (!rt)? false:rt;
};
dynapi._imageGetHTML=function(params){
	var c,i,t,text,dir,xtags='';
	var p=(params)? params:{};
	var lparams=(this.params)? this.params:{}; // opera can't do a for(i in object) on a null variable?
	var forbid =',width,height,alias,src,tooltip,link,text,textdir,'
	+'oversrc,downsrc,onclick,onmouseover,onmouseout,onmouseup,onmousedown,';
	for(i in lparams) {if(p[i]==null) p[i]=lparams[i]};
	if(!p.name) p.name='XImage'+dynapi.ximages['__xCnTer__']++;
	if(p.border==null) p.border=0;
	// setup width & height
	if(this.width && this.w==null) this.w=this.width;
	if(this.height && this.h==null) this.h=this.height;
	text=p['text'];	dir=p['textdir'];
	t= '<img src="'+this.src+'"'
	+((this.w)? ' width="'+this.w+'"':'')
	+((this.h)? ' height="'+this.h+'"':'')
	+((p['tooltip'])?' alt="'+p['tooltip']+'"':'');
	c='return dynapi._imageHook(this,\''+p.name+'\','+((dynapi.ua.ns4)? '((this._dynobj)? this._dynobj.doc:document)':'document')+'.images[\'';
	if(p.onclick) xtags=' onclick="'+c+p.name+'\'],\'click\');"';
	if(p.onmouseover||p.oversrc) xtags+=' onmouseover="'+c+p.name+'\'],\'over\',\''+((p.oversrc)?p.oversrc:'')+'\');"';
	if(p.onmouseout||p.oversrc) xtags+=' onmouseout="'+c+p.name+'\'],\'out\',\''+((p.oversrc)?this.src:'')+'\');"';
	if(p.onmousedown||p.downsrc) xtags+=' onmousedown="'+c+p.name+'\'],\'down\',\''+((p.downsrc)?p.downsrc:'')+'\');"';
	if(p.onmouseup||p.downsrc) xtags+=' onmouseup="'+c+p.name+'\'],\'up\',\''+((p.downsrc)?((p.oversrc)?p.oversrc:this.src):'')+'\');"';
	if(!p.link && (p.onclick||p.oversrc||p.downsrc)) p.link='javascript:;';
	if(!xtags && p.name.indexOf('XImage')==0) p.name=null; // remove name if not needed
	for(i in p){if(forbid.indexOf(','+i+',')<0 && p[i]!=null) t+=' '+i+'="'+p[i]+'"';}	
	t+='>';
	if (text){
		dir=(dir)?(dir+'').toUpperCase():'E';
		if (dir=='N') t=text+'<br>'+t;
		else if (dir=='S') t=t+'<br>'+text;
		else if (dir=='E') t=t+text;
		else if (dir=='W') t=text+t;
	}
	if(p.link) t='<a title="'+((p.tooltip)? p.tooltip:'')+'" href="'+p['link']+'"'+xtags+'>'+t+'</a>';
	if(xtags && p.name) dynapi._imageHookArray[p.name]=p;
	return t;
};
