/*
	DynAPI Distribution
	Debugger
	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
*/
// Note: Debugger does not have to be a DynObject - very important for blueprinted layers
function Debugger() {
	this._mode='normal';
	this.win = null;
	this._watch={};
	this._evalBuffer='';
	this._buffer = dynapi._debugBuffer;
	dynapi._debugBuffer = '';
	// close the debug window on unload
	this.closeOnUnLoad = false;
	dynapi.onUnload(function() {
		if (dynapi.debug.closeOnUnLoad) dynapi.debug.close();
	});
	this.open();
}
var p = Debugger.prototype; //dynapi.setPrototype('Debugger','DynObject');
p.close = function() {
	if (this.isLoaded()) {
		this.win.close();
		this.win = null;
	}
};
// error - output a browser generated error to the debug window
p.error = function(msg, url, lno) {
	if (url && url.indexOf(dynapi.documentPath)==0) {
		url = url.substring(dynapi.documentPath.length);
	}
	this.print('Error:'+ (lno? ' Line '+lno : '') +' ['+url+']\n       '+msg);
};
// evaluates an expression in the scope of the main dynapi window
p.evaluate = function(str) {
	dynapi.frame.eval(str);
	this.setEvalHistory(str);
};
// get evaluation history
p.getEvalHistory=function(n){
	if(!this.isLoaded()) return;
	var t,f=this.win.document.debugform;
	if(n>=1) {
		var lim=this.win.evalHistory.length-1;
		this.win.evalIndex++;
		if (this.win.evalIndex>lim) this.win.evalIndex=(lim<0)?0:lim;
		t=this.win.evalHistory[this.win.evalIndex];
		if(t)f.eval.value=t;
	}else if(n<=0){
		this.win.evalIndex--;
		if(this.win.evalIndex<0) this.win.evalIndex=0;
		t=this.win.evalHistory[this.win.evalIndex];
		if(t)f.eval.value=t;	
	}
};
// lists all known properties of an object
p.inspect = function(obj,showFunctions) {
	this.print('Inspecting:');
	var v;
	if (typeof(obj)=='string') obj=eval(obj);
	if (typeof(obj)=='object') {
		for (var i in obj) {
			if (obj[i]==null) v = 'null'
			else if (typeof(obj[i])=='undefined') v = 'null';
			else if (typeof(obj[i])=='function') {
				if (showFunctions==false) continue;
				else v = '[Function]';
			}
			else if (typeof(obj[i])=='object' && typeof(obj[i].length)!='undefined') v = 'Array';// ['+obj[i]+']';
			else if (typeof(obj[i])=='object') v = '[Object]';
			else v = obj[i];
			this.print('    '+i+' = '+v);
		}
	}
	else this.print('    undefined');
};
p.isLoaded = function() {
	return (this.win!=null && this.win.document && typeof(this.win.document.debugform)=="object");
};
// opens the debugger window
p.open = function() {
	var p = dynapi.library.path;
	if (!this.isLoaded() && p) {
		// Modified by Raphael Pereira
		//var url = dynapi.documentPath+p+'ext/debug.html#';
		var url = p+'ext/debug.html#';
		var w = (dynapi.ua.def||dynapi.ua.dom)? 350:355 //dynapi.ua.mac? (dynapi.ua.ie?330:300) : 350;
		var h = (dynapi.ua.def||dynapi.ua.dom)? 432:485 //dynapi.ua.mac? (dynapi.ua.ie?405:365) : (dynapi.ua.def||dynapi.ua.dom)? 420:476;
		this.win = window.open(url,'debugwin','width='+w+',height='+h+',scrollbars=no,status=no,toolbar=no');  //,resizable=no
		this.win.opener=window;
		this.win.evalHistory=[];
		this.win.evalIndex=0;
		this.print();
	/*	dynapi.frame.onerror = function(msg, url, lno) {			
			dynapi.debug.error(msg, url, lno);
		};
		*/
	}
};
// output text to the debug window
p.print = function(s) {
	if (s==null) s = '';
	else s = s + '\n';
	if (this.isLoaded()) {
		this.switchMode('normal');
		if (this._buffer != '') {  // dump buffer
			s = this._buffer + s;
			this._buffer = '';
		}
		this.win.document.debugform.print.value += s;
		this._normalModeData = this.win.document.debugform.print.value;
		
		// Does mozilla has something like this?
		if (dynapi.ua.ie) {
			var po = this.win.document.debugform.print;
			po.scrollTop = po.scrollHeight;
			var range = po.createTextRange();
			range.collapse(false);
			range.select();
		}
	}
	else this._buffer += s;
};
// reloads selected javascripts, packages or html pages
p.reload=function(t){
	if (!this.isLoaded) return;	
	t=t+'';
	if(t.substr(0,3).toLowerCase()=='go:') {
		t=t.substr(3).replace(/\\/g,'/');
		dynapi.frame.location.href=t;
		return;
	}
	var i,f=t.split(';');
	for(i=0;i<f.length;i++){
		t=f[i];
		if(t.indexOf('.js')<0) dynapi.library.load(t,null,true);
		else {
			var lib=dynapi.library;
			if (!lib.scripts[t]) lib.loadScript(t);
			else lib.reloadScript(t,null,true);
		}
	}
	if(this.win.focus) this.win.focus();
	else this.win.setZIndex({topmost:true});
};
p.reset=function(section){
	if (!this.isLoaded) return;	
	this._oldWatchSrc='';
	if(!section) {
		this.win.document.debugform.reset();
		this._normalModeData='';
		this.switchMode('normal');
	}else{
		var t=this.win.document.debugform[section];
		if(t) t.value='';
	}
};
p.status = function(str) {
	if (this.isLoaded()) {
		for (var i=1;i<arguments.length;i++) {
			str += ', '+arguments[i];
		}
		this.win.document.debugform.stat.value = str;
	};
};
// Set Mode
p.switchMode=function(m){
	if (!this.isLoaded) return;	
	if(m=='watch'||(this._mode=='normal' && m!='normal')) {	
		this._normalModeData = this.win.document.debugform.print.value;
		this._mode='watch';
		this._enableWatch();
	}else if(m=='normal'||(this._mode=='watch' && m!='watch')){
		this.win.document.debugform.print.value=(this._normalModeData)?this._normalModeData:'';
		this._mode='normal';	
		this._disableWatch();
	}
};
// enters text to the evaluate field in the debugger widnow
p.setEvaluate = function(str) {
	if (!this.isLoaded()) this._evalBuffer=str;
	else {
		if (!str) str = '';
		if(this._evalBuffer!='') {
			str =this._evalBuffer+str;
			this._evalBuffer='';
		}
		this.win.document.debugform.eval.value = str;
		this.setEvalHistory(str);
	}
};
// Set previous evaluation information
p.setEvalHistory=function(s){
	if(!this.isLoaded()) return;
	var i,found;
	if(s){
		for(i=0;i<this.win.evalHistory.length;i++){
			if(this.win.evalHistory[i]==s) {found=i;break;}
		}
		if(found!=null) this.win.evalHistory=dynapi.functions.removeFromArray(this.win.evalHistory,found);
		this.win.evalHistory[this.win.evalHistory.length]=s;
		this.win.evalIndex=this.win.evalHistory.length-1;
	}
};
p.showHelp=function(){
	var t=''
	+'-----------------------\n'
	+'Quick Help\n'
	+'-----------------------\n'
	+'1) To inspect an Object enter the name\n'
	+'of the object in the "Inspect Variable/Object"\n'
	+'textbox and then click on the "Inspect" button\n\n'
	+'2) To Load/Reload a DynAPI Package,\n'
	+'javascript or html page enter the name\n'
	+'of the package or javascript in the reload\n'
	+'text. For HTML pages type the prefix Go:\n'
	+'before the page name.\n'
	+'------------------------------------------------';
	this.print(t);
};
// watch object variables;
p.watch = function(name,value){
	if(arguments.length>1) this._watch[name]=value;
	else if(dynapi.frame.eval(name)) this._watch[name]='_watch object_';
	else this._watch[name]='_watch object_';
};
p._disableWatch = function(){
	this._oldWatchSrc='';
	if(this._timerWatch) {
		window.clearTimeout(this._timerWatch);
		this._timerWatch=0;
	}
};
p._enableWatch = function(){
	if(this._mode!='watch') return;
	var src,row,v;
	src='Name\t \t \t Value\n---------------------------------------\n';
	for(i in this._watch){
		if(this._watch[i]=='_watch object_') v=dynapi.frame.eval(i);
		else v=this._watch[i];
		if(v==null) v='null';
		if(typeof(v)=='string') v=v.replace(/\n/g,' ');
		src+=(i+'                      ').substr(0,22)+'\t '+v+'\n';
	}
	if(src!=this._oldWatchSrc){
		this.win.document.debugform.print.value=this._oldWatchSrc=src;
	}
	if(this._timerWatch) window.clearTimeout(this._timerWatch);
	this._timerWatch=window.setTimeout(this+'._enableWatch()',200);
};
dynapi.debug = new Debugger();
var t='------------------------------\n'
+'Click "?" for help\n'
+'------------------------------\n';
dynapi.debug.print(t);
