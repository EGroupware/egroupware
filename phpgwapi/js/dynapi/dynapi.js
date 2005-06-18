/*
	DynAPI Distribution
	DynObject, DynAPI Object, UserAgent, Library, Functions

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
*/

function DynObject() {
	this.id = "DynObject"+DynObject._c++;
	DynObject.all[this.id] = this;
};
var p = DynObject.prototype;
p.getClassName = function() {return this._className};
p.getClass = function() {return dynapi.frame[this._className]};
p.isClass = function(n) {return DynObject.isClass(this._className,n)};
p.addMethod = function(n,fn) {this[n] = fn};
p.removeMethod = function(n) {this[n] = null};
p.setID = function(id,isInline,noImports) {
	if (this.id) delete DynObject.all[this.id];
	this.id = id;
	this.isInline=isInline;
	this._noInlineValues=noImports;
	DynObject.all[this.id] = this;
};
p.toString = function() {return "DynObject.all."+this.id};
DynObject.all = {};
DynObject._c = 0;
DynObject.isClass = function(cn,n) {
	if (cn == n) return true;
	else {
		var c = dynapi.frame[cn];
		var p = c.prototype._pClassName;
		if (p) return DynObject.isClass(p,n);
		else return false;
	}
};

function _UserAgent() {
	var b = navigator.appName;
	var v = this.version = navigator.appVersion;
	var ua = navigator.userAgent.toLowerCase();	
	this.v = parseInt(v);
	this.safari = ua.indexOf("safari")>-1;	// always check for safari & opera 
	this.opera = ua.indexOf("opera")>-1;	// before ns or ie
	this.ns = !this.opera && !this.safari && (b=="Netscape");
	this.ie = !this.opera && (b=="Microsoft Internet Explorer");
	this.gecko = ua.indexOf('gecko')>-1; // check for gecko engine
	if (this.ns) {
		this.ns4 = (this.v==4);
		this.ns6 = (this.v>=5);
		this.b = "Netscape";
	}else if (this.ie) {
		this.ie4 = this.ie5 = this.ie55 = this.ie6 = false;
		if (v.indexOf('MSIE 4')>0) {this.ie4 = true; this.v = 4;}
		else if (v.indexOf('MSIE 5')>0) {this.ie5 = true; this.v = 5;}
		else if (v.indexOf('MSIE 5.5')>0) {this.ie55 = true; this.v = 5.5;}
		else if (v.indexOf('MSIE 6')>0) {this.ie6 = true; this.v = 6;}
		this.b = "MSIE";
	}else if (this.opera) {
		this.v=parseInt(ua.substr(ua.indexOf("opera")+6,1)); // set opera version
		this.opera6=(this.v>=6);
		this.opera7=(this.v>=7);
		this.b = "Opera";
	}else if (this.safari) {
		this.ns6 = (this.v>=5);	// ns6 compatible correct?
		this.b = "Safari";
	}
	this.dom = (document.createElement && document.appendChild && document.getElementsByTagName)? true : false;
	this.def = (this.ie||this.dom);
	this.win32 = ua.indexOf("win")>-1;
	this.mac = ua.indexOf("mac")>-1;
	this.other = (!this.win32 && !this.mac);
	this.supported = (this.def||this.ns4||this.ns6||this.opera)? true:false;
	this.broadband=false;
	this._bws=new Date; // bandwidth timer start 

	// Extended by Raphael Derosso Pereira
	this.ua = this.safari ? 'safari' : this.opera ? 'opera' : this.ie ? 'ie' : this.gecko ? 'gecko' : this.ns ? 'ns' : 'unknown';
};

function DynAPIObject() {
	this.DynObject = DynObject;
	this.DynObject();

	this.version = '3.0.0 Beta 1';
	this.loaded = false;

	this.ua = new _UserAgent();

	this._loadfn = [];
	this._unloadfn = [];
	var f = this.frame = window;

	var url = f.document.location.href;
	url = url.substring(0,url.lastIndexOf('/')+1);
	this.documentPath = url;

	var o = this;

	this.library = {};
	this.library.setPath = function(p) {o.library.path = p};

	f.onload = function() {
		o.loaded = true;
		if (!o.ua.supported) return alert('Unsupported Browser. Exiting.');
		if (o.library._create) o.library._create();  // calls dynapi._onLoad() after loading necessary files
		else setTimeout(o+'._onLoad()',1);
	};
	f.onunload = function() {
		for (var i=0;i<o._unloadfn.length;i++) o._unloadfn[i]();
		if (o.document) {
			o.document._destroy();
			o.document = null;
		}
	};
};
p = DynAPIObject.prototype = new DynObject;

p.onLoad = function(f) {
	if (typeof(f)=="function") {
		if (!this.loaded) this._loadfn[this._loadfn.length] = f;
		else f();
	}
};
p._onLoad = function(f) {
	for (var i=0;i<this._loadfn.length;i++) this._loadfn[i]();
};
p.onUnload = function(f) {
	if (typeof(f)=="function") this._unloadfn[this._unloadfn.length] = f;
};
p.setPrototype = function(sC,sP) {
	var c = this.frame[sC];
	var p = this.frame[sP];
	if ((!c || !p) && this.ua.ns4 && this.library && this.library.elm) {
		if (!c) c = this.library.elm[sC];
		if (!p) p = this.library.elm[sP];
	}
	if (!c || !p) return alert('Prototype Error');
	c.prototype = new p();
	c.prototype._className = sC;
	c.prototype._pClassName = sP;
	c.toString = function() {return '['+sC+']'};
	return c.prototype;
};

var dynapi = new DynAPIObject();

dynapi.ximages={'__xCnTer__':0}; // eXtensible Images
p._imageGetHTML=function(){
	t= '<img src="'+this.src+'"'
	+((this.width)? ' width="'+this.width+'"':'')
	+((this.height)? ' height="'+this.height+'"':'')
	+' border="0">';
	return t;
};

dynapi.functions = {
	removeFromArray : function(array, index, id) {
		// This seems to be wrong!
		// Commented out by Raphael Derosso Pereira
		//var which=(typeof(index)=="object")?index:array[index];
		var which = index;
		if (id) delete array[which.id];
        else for (var i=0; i<array.length; i++) {
			if (array[i]==which) {
				if(array.splice) array.splice(i,1);
				else {	
					for(var x=i; x<array.length-1; x++) array[x]=array[x+1];
         			array.length -= 1; 
         		}
				break;
			}
		}
		return array;
	},
	removeFromObject : function(object, id) {
		if(!dynapi.ua.opera) delete object[id];
		else {
			var o={};
			for (var i in object) if(id!=i) o[i]=object[i];
			object=o;
		}
		return object;
	},
	True : function() {return true},
	False : function() {return false},
	Null : function() {},
	Zero : function() {return 0;},
	Allow : function() {
		event.cancelBubble = true;
		return true;
	},
	Deny : function() {
		event.cancelBubble = false;
		return false;
	},
	getImage : function(src,w,h) {
		img=(w!=null&&h!=null)? new Image(w,h) : new Image();
		img.src=src;
		img.getHTML=dynapi._imageGetHTML;
		return img;
	},
	getURLArguments : function(o) {  // pass a string or frame/layer object
		var url,l={};
		if (typeof(o)=="string") url = o;
		else if (dynapi.ua.ns4 && o.src) url = o.src;
		else if (o.document) url = o.document.location.href;
		else return l;
		var s = url.substring(url.indexOf('?')+1);
		var a = s.split('&');
		for (var i=0;i<a.length;i++) {
			var b = a[i].split('=');
			l[b[0]] = unescape(b[1]);
		}
		return l;
	},
	getAnchorLocation : function(a,lyr){
		var o,x=0,y=0;
		if(lyr && !lyr.doc) lyr=null;
		lyr=(lyr)? lyr:{doc:document,elm:document};
		if(typeof(a)=='string') {
			if(lyr.doc.all) a=lyr.doc.all[a];
			else if(lyr.doc.getElementById) a=lyr.doc.getElementById(a);
			else if(lyr.doc.layers) a=lyr.doc.anchors[a];
		}
		if(a) o=a;
		else return;
		if(lyr.doc.layers) { y+=o.y; x+=o.x;}
		else if(lyr.doc.getElementById || lyr.doc.all){
			while (o.offsetParent && lyr.elm!=o){
				x+= o.offsetLeft;y+= o.offsetTop;
				o = o.offsetParent;
			}
		}
		return {x:x,y:y,anchor:a};
	}
};

dynapi.documentArgs = dynapi.functions.getURLArguments(dynapi.frame);

dynapi.debug = {};
dynapi._debugBuffer = '';
dPrint=function(s){var d=dynapi.debug; d.print(s)};
dynapi.debug.print = function(s) {
	//@IF:DEBUG[
		if(s==null) s='';
		dynapi._debugBuffer += s + '\n';
	//]:DEBUG
};

// The DynAPI library system is optional, this can be removed if you want to include other scripts manually
function DynAPILibrary() {
	this.DynObject = DynObject;
	this.DynObject();

	// list of js files: this.scripts['../src/api/dynlayer_ie.js'] = {dep, objects, pkg, fn};
	this.scripts = {};

	// list of package names: this.packages['dynapi.api'] = dynapi.api = {_objects,_path}
	this.packages = {};

	// list of object names: this.objects['DynLayer'] = this.scripts['../src/api/dynlayer_ie.js']
	this.objects = {};

	this._c = 0;
	this.loadList = [];
	this.loadIndex = -1;
	this.path = null;
	this.busy = true;
};
p = dynapi.setPrototype('DynAPILibrary','DynObject');

// can return a path specific to a package, eg. dynapi.library.getPath('dynapi.api') returns '/src/dynapi/api/'
p.getPath = function(pkg) {
	if (!pkg) pkg = 'dynapi';
	if (this.packages[pkg]) return this.packages[pkg]._path;
	return null;
};

// set dynapi path
p.setPath = function(p,pkgFile) {
	this.path = p;

	// to-do: rearrange so add()'s can be done before setPath
	//        full paths will then be determined when queued
	//        need an extra argument on addPackage to specify whether the path is relative to this.path or not
	// OR:    add functionality so that these package definitions can be loaded/included on the fly
	
	// load pkgFile or 'ext/packages.js' file
	var s='<script type="text/javascript" language="JavaScript" src="'
	+((pkgFile)? pkgFile:p+'ext/packages.js')+'"><\/script>';
	document.write(s);
};

// adds package(s) to the library
p.addPackage = function(pkg, path) {
	var ps;
	if (pkg.indexOf('.')) ps = pkg.split('.');
	else ps = [pkg];

	var p = dynapi.frame;
	for (var i=0;i<ps.length;i++) {  // returns the package object (eg. dynapi.api), or creates it if non-existant
		if (!p[ps[i]]) p[ps[i]] = {};
		p = p[ps[i]];
	}
	this.packages[pkg] = p;
	p._objects = [];
	p._path = path;
	return p;
};

// add object(s) to the library
p.add = function(name, src, dep, relSource) {
	var objects = typeof(name)=="string"? [name] : name;
	dep = (!dep)? [] : typeof(dep)=="string"? [dep] : dep;

	var s,p,pkg;
	if (objects[0].indexOf('.')) {
		pkg = objects[0].substring(0,objects[0].lastIndexOf('.'));
		if (pkg && this.packages[pkg]) {
			p = this.packages[pkg];
			if (relSource!=false) src = p._path + src;
		}
	}
	if (!this.scripts[src]) s = this.scripts[src] = {};
	else s = this.scripts[src];
	s.objects = [];
	s.dep = dep;
	s.rdep = [];
	s.src = src;
	s.pkg = pkg;
	s.loaded = false;
	s.fn = null;

	var n;
	for (var i=0;i<objects.length;i++) {
		n = objects[i];
		if (pkg) n = n.substring(n.lastIndexOf('.')+1);
		this.objects[n] = s;
		s.objects[s.objects.length] = n;
		if (p) p._objects[p._objects.length] = n;
	}

	return s;
};
// adds a dependency, whenever object "n" is loaded it will load object "d" beforehand
p.addBefore = function(n, d) {
	var s = this.objects[n];
	if (s && this.objects[d]) s.dep[s.dep.length] = d;
};
// adds a reverse dependency, whenever object "n" is loaded it will load object "r" afterword
p.addAfter = function(n, r) {
	var s = this.objects[n];
	if (s && this.objects[r]) s.rdep[s.rdep.length] = r;
};

// returns a list of js source filenames to load
p._queue = function(n, list, force) {
	var na=[], names=[],o;
	if (list==null) list = [];
	if (typeof(n)=="string") na = [n];
	else na = n;

	for (var i=0;i<na.length;i++) {
		o = na[i];
		if (typeof(o)=="string") {
			if (this.packages[o])
				for (var j in this.packages[o]._objects)
					names[names.length] = this.packages[o]._objects[j];
			else names[names.length] = o;
		}
		else if (typeof(o)=="object" && o.length) {
			list = this._queue(o, list, force);
		}
	}

	var s;
	for (var j=0;j<names.length;j++) {
		s = this._queueObject(names[j], force);
		if (s) {
			if (s.dep)
				for (var i=0;i<s.dep.length;i++)
					list = this._queue(s.dep[i], list, force);
			list[list.length] = s.src;
			// also include reverse deps
			if (s.rdep.length) list = this._queue(s.rdep, list, force);
		}
	}
	return list;
};

// determines whether to queue the script this object is in
p._queueObject = function(n, f) {
	if (n.indexOf('.')) {
		var pkg = n.substring(0,n.lastIndexOf('.'));
		if (this.packages[pkg]) n = n.substring(n.lastIndexOf('.')+1);
	}
	var s = this.objects[n];
	if (s) {
		if (!s.queued) {
			if (f!=true && s.loaded) dynapi.debug.print('Library Warning: '+n+' is already loaded');
			else {
				s.queued = true;
				s.loaded = false;
				return s;
			}
		}
	}
	else dynapi.debug.print('Library Error: no library map for '+n);
	return false;
};

// writes the <script> tag for the object
p.include = function() { 
	var a = arguments;
	if (a[0]==true) a=a[1]; // arguments used ONLY by packages.js
	// buffer includes until packages(.js) are loaded
	if (!this._pakLoaded) { 
		if(!this._buffer) this._buffer=[];
		this._buffer[this._buffer.length]=a;
		return;
	}
	if (dynapi.loaded) this.load(a);
	else {
		var list = this._queue(a);
		var src;
		for (var i=0;i<list.length;i++) {
			src = list[i];
			this.scripts[src].loaded = true;
			dynapi.frame.document.write('<script type="text/javascript" language="JavaScript" src="'+src+'"><\/script>');
		}
	}
};
p.load = p.reload = p.loadScript = p.reloadScript = function(n) {
	dynapi.debug.print('Warning: dynapi.library load extensions not included');
};
dynapi.library = new DynAPILibrary();

// deprecated
var DynAPI = dynapi;
