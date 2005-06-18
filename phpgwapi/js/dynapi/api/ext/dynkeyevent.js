/*
   DynAPI Distribution
   DynKeyEvent Extensions

   The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

   Requirements:
	dynapi.api
*/
function DynKeyEvent(type,src) {
	this.DynEvent = DynEvent;
	this.DynEvent(type,src);
	this.charKey=null;
};
var p=dynapi.setPrototype('DynKeyEvent','DynEvent');
p.getKey=function() {
	return this.charKey;
};
DynKeyEvent._keyEventListener=function(e) {
	var dynobj=this._dynobj;
	if(!dynobj) 
		return true;
	var dyndoc=dynobj.doc._dynobj;
	if(!dyndoc) return true;
	if(!e) var e=dyndoc.frame.event;

	var evt=new DynKeyEvent(e.type,dynobj);
	evt.which=(e.keyCode)?e.keyCode:e.which;
	var key=String.fromCharCode(evt.which).toLowerCase();
	if((key>='a'&&key<='z')||(key>='0'&&key<='9')) evt.charKey=key;
	evt.spaceKey=(evt.which==32);
	evt.enterKey=(evt.which==13);
	evt.tabKey=(evt.which==9||evt.which==65289);
	evt.leftKey=(evt.which==37||evt.which==52||evt.which==100||evt.which==65460);
	evt.rightKey=(evt.which==39||evt.which==54||evt.which==102||evt.which==65462);
	evt.upKey=(evt.which==38||evt.which==56||evt.which==104||evt.which==65464);
	evt.downKey=(evt.which==40||evt.which==50||evt.which==98||evt.which==65458);
	evt.altKey=(e.modifiers)?false:(e.altKey||e.altLeft||evt.which==18||evt.which==57388);
	evt.ctrlKey=(e.modifiers)?(e.modifiers&Event.CONTROL_MASK):(e.ctrlKey||e.ctrlLeft||evt.which==17||evt.which==57391);
	evt.shiftKey=(e.modifiers)?(e.modifiers&Event.SHIFT_MASK):(e.shiftKey||e.shiftLeft||evt.which==16||evt.which==57390);

	dynobj.invokeEvent(evt.type,evt);
	if(evt.defaultValue==false) {
		if(e.cancelBubble) e.cancelBubble=true;
		if(e.stopPropagation) e.stopPropagation();
	}
	return evt.defaultValue;
};

TabManager={};
TabManager._c=0; // Current tab manager index.
TabManager._all=[];
TabManager._active=false;
TabManager._activeTimeout=function() { // Prevent duplcate keydown events in NS4.
	TabManager._active=true;
	setTimeout('TabManager._active=false;',25);
};
TabManager._getForm=null;
TabManager.getForm=function(p) { // Prevent default tab focus in Mozilla.
	if(TabManager._getForm) return;
        TabManager._getForm=p;
	var html='<form name="__frm" onsubmit="return false;"><input name="__tab" size=1></form>';
	return p.addChild(new DynLayer(html),'__lyr');
};
TabManager._grabFocus=function() {
	var form=TabManager._getForm.__lyr;
	setTimeout(form+'.doc.forms.__frm.__tab.focus();',0);
};
TabManager._el={};
TabManager._el.onkeydown=function(e) {
	if(TabManager._getForm) { // User must have inserted TabManager form.
		if(TabManager._active) return;
		TabManager._activeTimeout();
	}
	var i1,o1,l1,i2,o2,l2;
	var nextKey=(e.tabKey||e.rightKey);
	var prevKey=((e.shiftKey&&e.tabKey)||e.leftKey);
	var submitKey=(e.enterKey||e.spaceKey);
	i1=TabManager._c; o1=TabManager._all[i1]; l1=TabManager._all.length;
	i2=o1._tabGroup._c; o2=o1._tabGroup._all[i2]; l2=o1._tabGroup._all.length;
	if(nextKey||prevKey) { // Cycle group.
		if(o2._hasFocusEvents) o2.setFocus(false,o2._focusBubble);
		else o2.invokeEvent('blur');
		if(prevKey) i2=(i2==0)?l2-1:i2-1;
		else i2=(i2==l2-1)?0:i2+1;
		o2=o1._tabGroup._all[i2]; o1._tabGroup._c=i2;
		if(o2._hasFocusEvents) o2.setFocus(true,o2._focusBubble);
		else o2.invokeEvent('focus');
	}		
	else if(e.upKey||e.downKey) { // Cycle manager.
		if(o2._hasFocusEvents) o2.setFocus(false,o2._focusBubble);
		else o2.invokeEvent('blur');
		if(e.upKey) i1=(i1==0)?l1-1:i1-1;
		else i1=(i1==l1-1)?0:i1+1;
		o1=TabManager._all[i1]; TabManager._c=i1;
		i2=o1._tabGroup._c;
		o2=o1._tabGroup._all[i2];
		if(o2._hasFocusEvents) o2.setFocus(true,o2._focusBubble);
		else o2.invokeEvent('focus');
	} else if(submitKey) {
		o2.invokeEvent('submit');
	}
	e.preventDefault();
	if(TabManager._getForm) TabManager._grabFocus();
};
DynElement.prototype.createTabManager=function() {
	var p=this, c=p.children; if(!c) return;
	var args=(arguments.length)?arguments:c;
	var l=args.length, s; if(!l) return;
	if(p._tabGroup) delete p._tabGroup;
	p._tabGroup={ _c:0, _all:[] };
        for(var i=0;i<l;i++) {
                c=args[i];
		p._tabGroup._all[i]=c;
		c._hasTabManager=true;
		if(!c._submitFn) {
			s=c.id.replace(/-/g,'.')+'()'; // Element id callback.
			c._submitFn=s;
		}
        }
	l=TabManager._all.length; TabManager._all[l]=p;
	if(l==0) dynapi.onLoad(function() {
			dynapi.document.addEventListener(TabManager._el);
		});
};
DynElement.prototype.updateTabManager=function() {
	var tm=TabManager, all=tm._all[TabManager._c]; if(!all) return;
	var old=all._tabGroup; if(!old||old._all[old._c]==this) return;
	var p=this.parent, l;
	var tg=(p&&p._tabGroup)?p._tabGroup:null; if(!tg) return;
	l=tg._all.length;
	for(var i=0;i<l;i++) if(tg._all[i]==this) { tg._c=i; break; }
	l=tm._all.length;
	for(var i=0;i<l;i++) if(tm._all[i]==p) { tm._c=i; break; }
};
DynElement.prototype.addTabListeners=function(el) {
	if(el&&this._tabGroup) {
		var a=this._tabGroup._all;
		for(var i in a) a[i].addEventListener(el);
	}
};
DynElement.prototype.addSubmitFn=function(fn) {
	if(fn) this._submitFn=fn;
};
DynElement.prototype.callSubmitFn=function() {
	var f=this._submitFn;
	if(typeof(f)=='function') f();
	else if(typeof(f)=='string') eval(f);
};

DynElement.prototype.captureKeyEvents=function() {
	// This impossibilitates Inheritance... changing to the same aproach as captureMouseEvents
	//var elm=(this.getClassName()=='DynLayer')?this.elm:this.doc;
	var elm;
	
	if (this.getKeyEventElement) elm = this.getKeyEventElement();
	else elm=(this.getClassName()=='DynDocument')?this.doc:this.elm;

	//if(!elm||this._hasKeyEvents) return true;
	if (!elm) return true;
	if(elm.addEventListener) {
		elm.addEventListener("keydown",DynKeyEvent._keyEventListener,false);
		elm.addEventListener("keypress",DynKeyEvent._keyEventListener,false);
		elm.addEventListener("keyup",DynKeyEvent._keyEventListener,false);
		elm.addEventListener("blur",DynKeyEvent._keyEventListener,false);
		elm.addEventListener("focus",DynKeyEvent._keyEventListener,false);
	}
	else {
		if(elm.captureEvents)
			elm.captureEvents(Event.KEYPRESS|Event.KEYDOWN|Event.KEYUP);
    		elm.onblur=elm.onfocus=elm.onkeydown=elm.onkeypress=elm.onkeyup=DynKeyEvent._keyEventListener;
	}
	this._hasKeyEvents=true;
	return false;
};
DynElement.prototype.releaseKeyEvents=function() {
	var elm=(this.getClassName()=='DynLayer')?this.elm:this.doc;
	if(!elm||!this._hasKeyEvents) return true;
	if(elm.removeEventListener) {
		elm.removeEventListener("keydown",DynKeyEvent._keyEventListener,false);
		elm.removeEventListener("keypress",DynKeyEvent._keyEventListener,false);
		elm.removeEventListener("keyup",DynKeyEvent._keyEventListener,false);
	}
	else {
		if(elm.releaseEvents)
			elm.releaseEvents(Event.KEYPRESS|Event.KEYDOWN|Event.KEYUP);
		elm.onkeydown=elm.onkeypress=elm.onkeyup=null;
	}
	this._hasKeyEvents=false;
	return false;
};

DynDocument.prototype.captureHotKey = function(key,fn){
	var klst=((key+'').toLowerCase()).split('+');
	klst.sort();
	key=klst.join('+');
	if(!this._hotKeys){
		this._hotKeys={};
		this._keyDn={};
		this._keyLst='';
		this.captureKeyEvents();
		this.addEventListener({
			onkeydown:function(e){
				var k = e.which;
				var o = e.getSource();	
				// to-do: add opera v7 key code (57xxx), e.g 57388
				if (k==13) k="enter";
				else if(k==27) k="esc";
				else if(k==45) k="insert";
				else if(k==46) k="delete";
				else if(k==36) k="home";
				else if(k==35) k="end";
				else if(k==33) k="pgup";
				else if(k==34) k="pgdn";
				else if(k==38) k="up";
				else if(k==40) k="down";
				else if(k==37) k="left";
				else if(k==39) k="right";				
				else if(e.altKey && !o._keyDn['alt']) k="alt";
				else if(e.ctrlKey && !o._keyDn['ctrl']) k="ctrl";
				else if(e.shiftKey && !o._keyDn['shift']) k="shift";
				else k=(String.fromCharCode(k)).toLowerCase();
				if(!o._keyDn[k]) {
					// store new key in keyDn array
					o._keyLst+=(((o._keyLst)? '+':'')+k); // build key list
					var ar=o._keyLst.split('+');
					ar.sort();
					o._keyLst=ar.join('+');
					o._keyDn[k]=true;
				}
				k=o._hotKeys[o._keyLst];
				if(k){
					o._keyLst='';o._keyDn={};
					if(typeof(k)=='string') return eval(k); else return k();
				}
			},
			onkeyup:function(e){
				var o=e.getSource();	
				o._keyLst='';o._keyDn={};
			}
		});
	}
	this._hotKeys[key]=fn;
};
DynDocument.prototype.releaseHotKey = function(key){
	if(this._hotKeys) delete this._hotKeys[key];
};

