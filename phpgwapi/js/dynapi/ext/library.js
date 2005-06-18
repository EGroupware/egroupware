/*
	DynAPI Distribution
	Dynamic Loading extension to dynapi.library

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
*/

// begin loading the object
DynAPILibrary.prototype.load = function(n,fn) {
	var list = this._queue(n,null,arguments[2]);
	//return dynapi.debug.print('going to load: '+list);
	if (list.length) {
		var s,src;
		for (var i=0;i<list.length;i++) {
			src = list[i];
			s = this.scripts[list[i]];
			if (i==list.length-1 && fn!=null) s.fn = fn;
			this.loadList[this.loadList.length] = src;
		}
		this._load();
	}
	else if (fn) fn();
};

// reload the object
DynAPILibrary.prototype.reload = function(n,fn,force) {
	var s = this.objects[n];
	if (s) {
		s.loaded = false;
		this.load(n,fn,force);
	}
};

// load a script that is not added to the library
DynAPILibrary.prototype.loadScript = function(src,fn) {
	if (!this.scripts[src]) {
		var n = 'unnamed'+this._c++;
		s = this.add(n,src);  // generate a name for the script
		s.unnamed = true;
		s.fn = null;
		s.dep = [];
		this.load(n,fn);
	}
};

// reload a script 
DynAPILibrary.prototype.reloadScript = function(src,fn,force) {
	var s=this.scripts[src];
	if(s) this.load(s.objects[0],fn,force);
}

// inserts the script element into the page
DynAPILibrary.prototype._load = function() {
	if (this.busy) return; // dynapi.debug.print('Library Warning: busy');
	else {
		if (this.loadIndex<this.loadList.length-1) {
			this.busy = true;
			this.loadIndex++;
			var src = this.loadList[this.loadIndex];
			//if (!confirm('load: '+src+' ?')) return;
			var rsrc = src + '?'+Math.random();     // random ensures cached files are not loaded
			var s = this.scripts[src];
			if (dynapi.ua.ns4) {
				// delete the constructors
				for (var j=0;j<s.objects.length;j++) {
					var n = s.objects[j];
					if (dynapi.frame[n]) {
						dynapi.frame[n] = null;
						if (s.pkg) dynapi.frame[s.pkg+'.'+n] = null;
					}
				}
				//NS4 does not like "/" inside the ?name=value
				src=src.replace(/\//g,'+'); //substitute "/" for "+"
				this.elm.src = dynapi._path+'ext/library.html?js='+src;
			}
			else if (dynapi.ua.ie && (dynapi.ua.v==4 || dynapi.ua.mac)) {
				dynapi.frame.document.body.insertAdjacentHTML('beforeEnd','<script type="text/javascript" language="javascript" src="'+rsrc+'" defer><\/script>');
				this._export(src);
			}
			else {
				var elm = s.elm = dynapi.frame.document.createElement('script');
				elm.src = rsrc;
				elm.type = 'text/javascript';
				elm.defer = true;
				if (dynapi.ua.ie) {
					elm.C = 0;
					var o = this;
					elm.onreadystatechange = function() {
						elm.C++;
						if (elm.C==2 || elm.readyState=="complete") {  // use 2nd statechange for onload
							o._export(src);
						}
					}
				}
				dynapi.frame.document.getElementsByTagName('head')[0].appendChild(elm);
				
				// I could not find way to know when the script is complete in Moz v0.9.3
				if (dynapi.ua.ns6) setTimeout(this+'._export("'+src+'")',100);
			}
		}
	}
};

// executed after a script is finished loading, run main() functions
DynAPILibrary.prototype._export = function(src) {
	var src = this.loadList[this.loadIndex];
	var s = this.scripts[src];
	if (s) {
		this._register(s);

		// run elm.main)() before global main()
		if (dynapi.ua.ns4 && typeof(this.elm.main)=="function") {
			this.elm.main();
			this.elm.main = null;
		}
				
		// run global main() if available
		if (typeof(main)=="function") {
			main();
			main = null;
		}

		// clear out all functions in the layer's scope
		if (dynapi.ua.ns4) {
			for (var i in this.elm) {
				if (typeof(this.elm[i])=="function") delete this.elm[i];
			}
		}
		this.busy = false;

		// load next file
		this._load();
	}
	//else return alert('Library Error: unknown script '+src);
};

// registers the script as loaded, exports the objects
DynAPILibrary.prototype._register = function(s) {
	//dynapi.debug.print('loaded "'+s.src+'"');
	s.loaded = true;
	s.queued = false;
	if (!s.unnamed) {
		var n,found;
		// loop through each object that the script contains
		for (var i=0;i<s.objects.length;i++) {
			found = false;
			n = s.objects[i];
					
			// scope local objects in the layer to the DynAPI frame
			if (dynapi.ua.ns4 && this.elm && typeof(this.elm[n])!="undefined") {
				dynapi.frame[n] = this.elm[n];
				found = true;
			}
			else if (typeof(dynapi.frame[n])!="undefined") found = true;
			else if (n.indexOf('.')>0) {
				var ns = n.split('.'), o = dynapi.frame, b = false;
				for (var j=0;j<ns.length;j++) {
					o = o[ns[j]];
				}
				if (typeof(o)!="undefined") found = true;
			}
			else if (typeof(dynapi[n])!="undefined") found = true;
			
			if (found) {
				if (s.pkg) {
					// make package link: dynapi.api.DynLayer = DynLayer
					if (s.pkg!="dynapi") this.packages[s.pkg][n] = dynapi.frame[n];
					n = s.pkg+'.'+n;
				}
				dynapi.debug.print('loaded ['+n+']');
			}
			else {
				dynapi.debug.print('Library Error: could not find ['+n+']');
			}
		}
	}
	// run handler if available
	if (s.fn) {
		s.fn();
		delete s.fn;
	}
};

// called from /lib/dynapi/library.html to write the <script>, NS4 only
DynAPILibrary.prototype._handleLoad = function(elm) {
	var args = dynapi.functions.getURLArguments(elm.src);
	var js = args["js"];
	if (js) {
		js=js.replace(/\+/g,'/'); // convert + to /
		if (js.indexOf('http')!=0) {
			var l = dynapi.frame.document.location;
			if (js.substr(0,1)=='/') js = l.port+'//'+host+src;
			else js = dynapi.documentPath+js;
		}
		elm.document.write('<script type="text/javascript" language="JavaScript" src="'+js+'?r'+Math.random()+'"><\/script>');
		elm.document.close();
		elm.onload = function() {
			dynapi.library._export(js);
		}
	}
};

// inserts the layer for NS4, register included scripts
DynAPILibrary.prototype._create = function() {
	// ensure a previous main function is wiped out
	if (typeof(main)=="function") main = null;
		
	// register objects from scripts included by dynapi.library.include() or manually
	var s,n;
	for (var i in this.scripts) {
		s = this.scripts[i];
		n=s.objects[0];
		if(s.pkg=='dynapi.functions') { // used to avoid conflicts with intrinsic objects such as String, Image, Date and Math objects
			if(dynapi.functions[n]) this._register(s);
		}
		else if (s.loaded || (s.objects[0] && dynapi.frame[s.objects[0]])) this._register(s);
	}
	
	// create NS4 layer to load scripts into
	if (dynapi.ua.ns4) this.elm = new Layer(0, dynapi.frame);
	this.busy = false;
	
	// load any scripts before proceeding
	if (this.loadList.length) {
		var s = this.scripts[this.loadList[this.loadList.length-1]];
		s.fn = function() {
			setTimeout('dynapi._onLoad()',1);
		}
		this._load();
	}
	else setTimeout('dynapi._onLoad()',1);
};
