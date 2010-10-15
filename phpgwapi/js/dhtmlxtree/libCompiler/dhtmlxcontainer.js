function dhtmlXContainer(obj) {
	
	var that = this;
	
	this.obj = obj;
	this.dhxcont = null;
	
	this.st = document.createElement("DIV");
	this.st.style.position = "absolute";
	this.st.style.left = "-200px";
	this.st.style.top = "0px";
	this.st.style.width = "100px";
	this.st.style.height = "1px";
	this.st.style.visibility = "hidden";
	this.st.style.overflow = "hidden";
	document.body.insertBefore(this.st, document.body.childNodes[0]);
	
	this.obj._getSt = function() {
		// return this.st object, needed for content moving
		return that.st;
	}
	
	this.obj.dv = "def"; // default
	this.obj.av = this.obj.dv; // active for usage
	this.obj.cv = this.obj.av; // current opened
	this.obj.vs = {}; // all
	this.obj.vs[this.obj.av] = {};
	
	this.obj.view = function(name) {
		
		if (!this.vs[name]) {
			
			this.vs[name] = {};
			this.vs[name].dhxcont = this.vs[this.dv].dhxcont;
			var mainCont = document.createElement("DIV");
			mainCont.style.position = "relative";
			mainCont.style.left = "0px";
			mainCont.style.width = "200px";
			mainCont.style.height = "200px";
			mainCont.style.overflow = "hidden";
			that.st.appendChild(mainCont);
			
			this.vs[name].dhxcont.mainCont[name] = mainCont;
			
		}
		
		this.avt = this.av;
		this.av = name;
		
		return this;
		
	}
	
	this.obj.setActive = function() {
		
		if (!this.vs[this.av]) return;
		
		this.cv = this.av;
		
		// detach current content
		
		if (this.vs[this.avt].dhxcont == this.vs[this.avt].dhxcont.mainCont[this.avt].parentNode) {
			
			that.st.appendChild(this.vs[this.avt].dhxcont.mainCont[this.avt]);
			
			if (this.vs[this.avt].menu) that.st.appendChild(document.getElementById(this.vs[this.avt].menuId));
			if (this.vs[this.avt].toolbar) that.st.appendChild(document.getElementById(this.vs[this.avt].toolbarId));
			if (this.vs[this.avt].sb) that.st.appendChild(document.getElementById(this.vs[this.avt].sbId));
			
		}
		
		
		
		// adjust content
		if (this._isCell) {
			//this.adjustContent(this.childNodes[0], (this._noHeader?0:this.skinParams[this.skin]["cpanel_height"]));
		}
		//this.vs[this.av].dhxcont.mainCont[this.av].style.width = this.vs[this.av].dhxcont.mainCont[this.avt].style.width;
		//this.vs[this.av].dhxcont.mainCont[this.av].style.height = this.vs[this.av].dhxcont.mainCont[this.avt].style.height;
		
		if (this.vs[this.av].dhxcont != this.vs[this.av].dhxcont.mainCont[this.av].parentNode) {
			
			this.vs[this.av].dhxcont.insertBefore(this.vs[this.av].dhxcont.mainCont[this.av],this.vs[this.av].dhxcont.childNodes[this.vs[this.av].dhxcont.childNodes.length-1]);
			
			if (this.vs[this.av].menu) this.vs[this.av].dhxcont.insertBefore(document.getElementById(this.vs[this.av].menuId), this.vs[this.av].dhxcont.childNodes[0]);
			if (this.vs[this.av].toolbar) this.vs[this.av].dhxcont.insertBefore(document.getElementById(this.vs[this.av].toolbarId), this.vs[this.av].dhxcont.childNodes[(this.vs[this.av].menu?1:0)]);
			if (this.vs[this.av].sb) this.vs[this.av].dhxcont.insertBefore(document.getElementById(this.vs[this.av].sbId), this.vs[this.av].dhxcont.childNodes[this.vs[this.av].dhxcont.childNodes.length-1]);
			
		}
		
		if (this._doOnResize) this._doOnResize();
		
		this.avt = null;
	}
	
	this.obj._viewRestore = function() {
		var t = this.av;
		if (this.avt) { this.av = this.avt; this.avt = null; }
		return t;
	}
	
	this.setContent = function(data) {
		/*
		this.dhxcont = data;
		this.dhxcont.innerHTML = "<div style='position: relative; left: 0px; top: 0px; overflow: hidden;'></div>"+
					 "<div class='dhxcont_content_blocker' style='display: none;'></div>";
		this.dhxcont.mainCont = this.dhxcont.childNodes[0];
		this.obj.vs[this.obj.av].dhxcont = this.dhxcont;
		*/
		
		this.obj.vs[this.obj.av].dhxcont = data;
		this.obj._init();
	}
	
	this.obj._init = function() {
		
		this.vs[this.av].dhxcont.innerHTML = "<div ida='dhxMainCont' style='position: relative; left: 0px; top: 0px; overflow: hidden;'></div>"+
							"<div ida='dhxContBlocker' class='dhxcont_content_blocker' style='display: none;'></div>";
		
		this.vs[this.av].dhxcont.mainCont = {};
		this.vs[this.av].dhxcont.mainCont[this.av] = this.vs[this.av].dhxcont.childNodes[0];
		
	}
	
	this.obj._genStr = function(w) {
		var s = ""; var z = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		for (var q=0; q<w; q++) s += z.charAt(Math.round(Math.random() * (z.length-1)));
		return s;
	}
	
	this.obj.setMinContentSize = function(w, h) {
		this.vs[this.av]._minDataSizeW = w;
		this.vs[this.av]._minDataSizeH = h;
	}
	
	this.obj._setPadding = function(p, altCss) {
		
		if (typeof(p) == "object") {
			this._offsetTop = p[0];
			this._offsetLeft = p[1];
			this._offsetWidth = p[2];
			this._offsetHeight = p[3];
		} else {
			this._offsetTop = p;
			this._offsetLeft = p;
			this._offsetWidth = -p*2;
			this._offsetHeight = -p*2;
		}
		this.vs[this.av].dhxcont.className = "dhxcont_global_content_area "+(altCss||"");
		
	}
	
	this.obj.moveContentTo = function(cont) {
		
		
		// move dhtmlx components
		
		for (var a in this.vs) {
			
			cont.view(a).setActive();
			
			var pref = null;
			if (this.vs[a].grid) pref = "grid";
			if (this.vs[a].tree) pref = "tree";
			if (this.vs[a].tabbar) pref = "tabbar";
			if (this.vs[a].folders) pref = "folders";
			if (this.vs[a].layout) pref = "layout";
			
			if (pref != null) {
				
				
				cont.view(a).attachObject(this.vs[a][pref+"Id"]);
				cont.vs[a][pref] = this.vs[a][pref];
				cont.vs[a][pref+"Id"] = this.vs[a][pref+"Id"];
				cont.vs[a][pref+"Obj"] = this.vs[a][pref+"Obj"];
				
				this.vs[a][pref] = null;
				this.vs[a][pref+"Id"] = null;
				this.vs[a][pref+"Obj"] = null;
				
			}
			
			if (this.vs[a]._frame) {
				cont.vs[a]._frame = this.vs[a]._frame;
				this.vs[a]._frame = null;
			}
			
			if (this.vs[a].menu != null) {
				
				if (cont.cv == cont.av) {
					cont.vs[cont.av].dhxcont.insertBefore(document.getElementById(this.vs[a].menuId), cont.vs[cont.av].dhxcont.childNodes[0]);
				} else {
					cont._getSt().appendChild(document.getElementById(this.vs[a].menuId));
				}
				cont.vs[a].menu = this.vs[a].menu;
				cont.vs[a].menuId = this.vs[a].menuId;
				cont.vs[a].menuHeight = this.vs[a].menuHeight;
				this.vs[a].menu = null;
				this.vs[a].menuId = null;
				this.vs[a].menuHeight = null;
				
				if (this.cv == this.av && this._doOnAttachMenu) this._doOnAttachMenu("unload");
				if (cont.cv == cont.av && cont._doOnAttachMenu) cont._doOnAttachMenu("move");
				
			}
				
			if (this.vs[a].toolbar != null) {
				
				if (cont.cv == cont.av) {
					cont.vs[cont.av].dhxcont.insertBefore(document.getElementById(this.vs[a].toolbarId), cont.vs[cont.av].dhxcont.childNodes[(cont.vs[cont.av].menu!=null?1:0)]);
				} else {
					cont._getSt().appendChild(document.getElementById(this.vs[a].toolbarId));
				}
				
				cont.vs[a].toolbar = this.vs[a].toolbar;
				cont.vs[a].toolbarId = this.vs[a].toolbarId;
				cont.vs[a].toolbarHeight = this.vs[a].toolbarHeight;
				this.vs[a].toolbar = null;
				this.vs[a].toolbarId = null;
				this.vs[a].toolbarHeight = null;
				
				if (this.cv == this.av && this._doOnAttachToolbar) this._doOnAttachToolbar("unload");
				if (cont.cv == cont.av && cont._doOnAttachToolbar) cont._doOnAttachToolbar("move");
			}
			
			if (this.vs[a].sb != null) {
				
				if (cont.cv == cont.av) {
					cont.vs[cont.av].dhxcont.insertBefore(document.getElementById(this.vs[a].sbId), cont.vs[cont.av].dhxcont.childNodes[cont.vs[cont.av].dhxcont.childNodes.length-1]);
				} else {
					cont._getSt().appendChild(document.getElementById(this.vs[a].sbId));
				}
				
				cont.vs[a].sb = this.vs[a].sb;
				cont.vs[a].sbId = this.vs[a].sbId;
				cont.vs[a].sbHeight = this.vs[a].sbHeight;
				this.vs[a].sb = null;
				this.vs[a].sbId = null;
				this.vs[a].sbHeight = null;
				if (this.cv == this.av && this._doOnAttachStatusBar) this._doOnAttachStatusBar("unload");
				if (cont.cv == cont.av && cont._doOnAttachStatusBar) cont._doOnAttachStatusBar("move");
			}
			
			
			var objA = this.vs[a].dhxcont.mainCont[a];
			var objB = cont.vs[a].dhxcont.mainCont[a];
			while (objA.childNodes.length > 0) objB.appendChild(objA.childNodes[0]);
		
			//this.vs[a] = null;
			
			
		}
		
		cont.view(this.av).setActive();
		
		
	}
	
	this.obj.adjustContent = function(parentObj, offsetTop, marginTop, notCalcWidth, offsetBottom) {
		
		this.vs[this.av].dhxcont.style.left = (this._offsetLeft||0)+"px";
		this.vs[this.av].dhxcont.style.top = (this._offsetTop||0)+offsetTop+"px";
		//
		var cw = parentObj.clientWidth+(this._offsetWidth||0);
		if (notCalcWidth !== true) this.vs[this.av].dhxcont.style.width = Math.max(0, cw)+"px";
		if (notCalcWidth !== true) if (this.vs[this.av].dhxcont.offsetWidth > cw) this.vs[this.av].dhxcont.style.width = Math.max(0, cw*2-this.vs[this.av].dhxcont.offsetWidth)+"px";
		//
		var ch = parentObj.clientHeight+(this._offsetHeight||0);
		this.vs[this.av].dhxcont.style.height = Math.max(0, ch-offsetTop)+(marginTop!=null?marginTop:0)+"px";
		if (this.vs[this.av].dhxcont.offsetHeight > ch - offsetTop) this.vs[this.av].dhxcont.style.height = Math.max(0, (ch-offsetTop)*2-this.vs[this.av].dhxcont.offsetHeight)+"px";
		if (offsetBottom) if (!isNaN(offsetBottom)) this.vs[this.av].dhxcont.style.height = Math.max(0, parseInt(this.vs[this.av].dhxcont.style.height)-offsetBottom)+"px";
		
		// main window content
		if (this.vs[this.av]._minDataSizeH != null) {
			// height for menu/toolbar/status bar should be included
			if (parseInt(this.vs[this.av].dhxcont.style.height) < this.vs[this.av]._minDataSizeH) this.vs[this.av].dhxcont.style.height = this.vs[this.av]._minDataSizeH+"px";
		}
		if (this.vs[this.av]._minDataSizeW != null) {
			if (parseInt(this.vs[this.av].dhxcont.style.width) < this.vs[this.av]._minDataSizeW) this.vs[this.av].dhxcont.style.width = this.vs[this.av]._minDataSizeW+"px";
		}
		
		if (notCalcWidth !== true) {
			this.vs[this.av].dhxcont.mainCont[this.av].style.width = this.vs[this.av].dhxcont.clientWidth+"px";
			// allow border to this.dhxcont.mainCont
			if (this.vs[this.av].dhxcont.mainCont[this.av].offsetWidth > this.vs[this.av].dhxcont.clientWidth) this.vs[this.av].dhxcont.mainCont[this.av].style.width = Math.max(0, this.vs[this.av].dhxcont.clientWidth*2-this.vs[this.av].dhxcont.mainCont[this.av].offsetWidth)+"px";
		}
		
		var menuOffset = (this.vs[this.av].menu!=null?(!this.vs[this.av].menuHidden?this.vs[this.av].menuHeight:0):0);
		var toolbarOffset = (this.vs[this.av].toolbar!=null?(!this.vs[this.av].toolbarHidden?this.vs[this.av].toolbarHeight:0):0);
		var statusOffset = (this.vs[this.av].sb!=null?(!this.vs[this.av].sbHidden?this.vs[this.av].sbHeight:0):0);
		
		// allow border to this.dhxcont.mainCont
		this.vs[this.av].dhxcont.mainCont[this.av].style.height = this.vs[this.av].dhxcont.clientHeight+"px";
		if (this.vs[this.av].dhxcont.mainCont[this.av].offsetHeight > this.vs[this.av].dhxcont.clientHeight) this.vs[this.av].dhxcont.mainCont[this.av].style.height = Math.max(0, this.vs[this.av].dhxcont.clientHeight*2-this.vs[this.av].dhxcont.mainCont[this.av].offsetHeight)+"px";
		this.vs[this.av].dhxcont.mainCont[this.av].style.height = Math.max(0, parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.height)-menuOffset-toolbarOffset-statusOffset)+"px";
		
	}
	this.obj.coverBlocker = function() {
		return this.vs[this.av].dhxcont.childNodes[this.vs[this.av].dhxcont.childNodes.length-1];
	}
	this.obj.showCoverBlocker = function() {
		this.coverBlocker().style.display = "";
	}
	this.obj.hideCoverBlocker = function() {
		this.coverBlocker().style.display = "none";
	}
	this.obj.updateNestedObjects = function() {
		
		if (this.vs[this.av].grid) { this.vs[this.av].grid.setSizes(); }
		if (this.vs[this.av].sched) { this.vs[this.av].sched.setSizes(); }
		if (this.vs[this.av].tabbar) { 
			this.vs[this.av].tabbar.adjustOuterSize();
		}
		if (this.vs[this.av].folders) { this.vs[this.av].folders.setSizes(); }
		if (this.vs[this.av].editor) {
			if (!_isIE) this.vs[this.av].editor._prepareContent(true);
			this.vs[this.av].editor.setSizes();
		}
		
		//if (_isOpera) { var t = this; window.setTimeout(function(){t.editor.adjustSize();},10); } else { this.vs[this.av].editor.adjustSize(); } }
		if (this.vs[this.av].layout) {
			if (this.vs[this.av]._isAcc && this.vs[this.av].skin == "dhx_skyblue") {
				this.vs[this.av].layoutObj.style.width = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.width)+2+"px";
				this.vs[this.av].layoutObj.style.height = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.height)+2+"px";
			} else {
				this.vs[this.av].layoutObj.style.width = this.vs[this.av].dhxcont.mainCont[this.av].style.width;
				this.vs[this.av].layoutObj.style.height = this.vs[this.av].dhxcont.mainCont[this.av].style.height;
			}
			this.vs[this.av].layout.setSizes();
		}
		
		if (this.vs[this.av].accordion != null) {
			
			if (this.vs[this.av].skin == "dhx_web") {
				this.vs[this.av].accordionObj.style.width = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.width)+"px";
				this.vs[this.av].accordionObj.style.height = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.height)+"px";
			} else {
				this.vs[this.av].accordionObj.style.width = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.width)+2+"px";
				this.vs[this.av].accordionObj.style.height = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.height)+2+"px";
			}
			this.vs[this.av].accordion.setSizes();
		}
		// docked layout's cell
		if (this.vs[this.av].dockedCell) { this.vs[this.av].dockedCell.updateNestedObjects(); }
		/*
		if (win.accordion != null) { win.accordion.setSizes(); }
		if (win.layout != null) { win.layout.setSizes(win); }
		*/
		if (this.vs[this.av].form) this.vs[this.av].form.setSizes();
	}
	/**
	*   @desc: attaches a status bar to a window
	*   @type: public
	*/
	this.obj.attachStatusBar = function() {
		
		if (this.vs[this.av].sb) return;
		
		var sbObj = document.createElement("DIV");
		
		if (this._isCell) {
			sbObj.className = "dhxcont_sb_container_layoutcell";
		} else {
			sbObj.className = "dhxcont_sb_container";
		}
		sbObj.id = "sbobj_"+this._genStr(12);
		sbObj.innerHTML = "<div class='dhxcont_statusbar'></div>";
		
		if (this.cv == this.av) this.vs[this.av].dhxcont.insertBefore(sbObj, this.vs[this.av].dhxcont.childNodes[this.vs[this.av].dhxcont.childNodes.length-1]); else that.st.appendChild(sbObj);
		
		sbObj.setText = function(text) { this.childNodes[0].innerHTML = text; }
		sbObj.getText = function() { return this.childNodes[0].innerHTML; }
		sbObj.onselectstart = function(e) { e=e||event; e.returnValue=false; return false; }
		
		this.vs[this.av].sb = sbObj;
		this.vs[this.av].sbHeight = (this.skin=="dhx_web"?41:(this.skin=="dhx_skyblue"?23:sbObj.offsetHeight));
		this.vs[this.av].sbId = sbObj.id;
		
		if (this._doOnAttachStatusBar) this._doOnAttachStatusBar("init");
		this.adjust();
		
		return this.vs[this._viewRestore()].sb;
	}
	/**
	*   @desc: detaches a status bar from a window
	*   @type: public
	*/
	this.obj.detachStatusBar = function() {
		if (!this.vs[this.av].sb) return;
		this.vs[this.av].sb.setText = null;
		this.vs[this.av].sb.getText = null;
		this.vs[this.av].sb.onselectstart = null;
		this.vs[this.av].sb.parentNode.removeChild(this.vs[this.av].sb);
		this.vs[this.av].sb = null;
		this.vs[this.av].sbHeight = null;
		this.vs[this.av].sbId = null;
		this._viewRestore();
		if (this._doOnAttachStatusBar) this._doOnAttachStatusBar("unload");
	}
	/**
	*   @desc: attaches a dhtmlxMenu to a window
	*   @type: public
	*/
	this.obj.attachMenu = function(skin) {
		
		if (this.vs[this.av].menu) return;
		
		var menuObj = document.createElement("DIV");
		menuObj.style.position = "relative";
		menuObj.style.overflow = "hidden";
		menuObj.id = "dhxmenu_"+this._genStr(12);
		
		if (this.cv == this.av) this.vs[this.av].dhxcont.insertBefore(menuObj, this.vs[this.av].dhxcont.childNodes[0]); else that.st.appendChild(menuObj);
		
		this.vs[this.av].menu = new dhtmlXMenuObject(menuObj.id, (skin||this.skin));
		this.vs[this.av].menuHeight = (this.skin=="dhx_web"?29:menuObj.offsetHeight);
		this.vs[this.av].menuId = menuObj.id;
		
		if (this._doOnAttachMenu) this._doOnAttachMenu("init");
		this.adjust();
		
		return this.vs[this._viewRestore()].menu;
	}
	/**
	*   @desc: detaches a dhtmlxMenu from a window
	*   @type: public
	*/
	this.obj.detachMenu = function() {
		if (!this.vs[this.av].menu) return;
		var menuObj = document.getElementById(this.vs[this.av].menuId);
		this.vs[this.av].menu.unload();
		this.vs[this.av].menu = null;
		this.vs[this.av].menuId = null;
		this.vs[this.av].menuHeight = null;
		menuObj.parentNode.removeChild(menuObj);
		menuObj = null;
		this._viewRestore();
		if (this._doOnAttachMenu) this._doOnAttachMenu("unload");
	}
	/**
	*   @desc: attaches a dhtmlxToolbar to a window
	*   @type: public
	*/
	this.obj.attachToolbar = function(skin) {
		
		if (this.vs[this.av].toolbar) return;
		
		var toolbarObj = document.createElement("DIV");
		toolbarObj.style.position = "relative";
		toolbarObj.style.overflow = "hidden";
		toolbarObj.id = "dhxtoolbar_"+this._genStr(12);
		
		if (this.cv == this.av) this.vs[this.av].dhxcont.insertBefore(toolbarObj, this.vs[this.av].dhxcont.childNodes[(this.vs[this.av].menu!=null?1:0)]); else that.st.appendChild(toolbarObj);
		
		this.vs[this.av].toolbar = new dhtmlXToolbarObject(toolbarObj.id, (skin||this.skin));
		this.vs[this.av].toolbarHeight = (this.skin=="dhx_web"?41:toolbarObj.offsetHeight+(this._isLayout&&this.skin=="dhx_skyblue"?2:0));
		this.vs[this.av].toolbarId = toolbarObj.id;
		
		if (this._doOnAttachToolbar) this._doOnAttachToolbar("init");
		this.adjust();
		
		return this.vs[this._viewRestore()].toolbar;
	}
	/**
	*   @desc: detaches a dhtmlxToolbar from a window
	*   @type: public
	*/
	this.obj.detachToolbar = function() {
		if (!this.vs[this.av].toolbar) return;
		var toolbarObj = document.getElementById(this.vs[this.av].toolbarId);
		this.vs[this.av].toolbar.unload();
		this.vs[this.av].toolbar = null;
		this.vs[this.av].toolbarId = null;
		this.vs[this.av].toolbarHeight = null;
		toolbarObj.parentNode.removeChild(toolbarObj);
		toolbarObj = null;
		this._viewRestore();
		if (this._doOnAttachToolbar) this._doOnAttachToolbar("unload");
	}
	/**
	*   @desc: attaches a dhtmlxGrid to a window
	*   @type: public
	*/
	this.obj.attachGrid = function() {
		if (this._isWindow && this.skin == "dhx_skyblue") {
			this.vs[this.av].dhxcont.mainCont[this.av].style.border = "#a4bed4 1px solid";
			this._redraw();
		}
		var obj = document.createElement("DIV");
		obj.id = "dhxGridObj_"+this._genStr(12);
		obj.style.width = "100%";
		obj.style.height = "100%";
		obj.cmp = "grid";
		document.body.appendChild(obj);
		this.attachObject(obj.id, false, true);
		
		this.vs[this.av].grid = new dhtmlXGridObject(obj.id);
		this.vs[this.av].grid.setSkin(this.skin);
		if (this.skin != "dhx_web") {
			this.vs[this.av].grid.entBox.style.border = "0px solid white";
			this.vs[this.av].grid._sizeFix=0;
		}
		this.vs[this.av].gridId = obj.id;
		this.vs[this.av].gridObj = obj;
		
		return this.vs[this._viewRestore()].grid;
	}
	/**
	*   @desc: attaches a dhtmlxScheduler to a window
	*   @type: public
	*/	
	this.obj.attachScheduler = function(day,mode) {
		var obj = document.createElement("DIV");
		obj.id = "dhxSchedObj_"+this._genStr(12);
		obj.innerHTML = '<div id="'+obj.id+'" class="dhx_cal_container" style="width:100%; height:100%;"><div class="dhx_cal_navline"><div class="dhx_cal_prev_button">&nbsp;</div><div class="dhx_cal_next_button">&nbsp;</div><div class="dhx_cal_today_button"></div><div class="dhx_cal_date"></div><div class="dhx_cal_tab" name="day_tab" style="right:204px;"></div><div class="dhx_cal_tab" name="week_tab" style="right:140px;"></div><div class="dhx_cal_tab" name="month_tab" style="right:76px;"></div></div><div class="dhx_cal_header"></div><div class="dhx_cal_data"></div></div>';
		
		document.body.appendChild(obj.firstChild);
		this.attachObject(obj.id, false, true);
		
		this.vs[this.av].sched = scheduler;
		this.vs[this.av].schedId = obj.id;
		scheduler.setSizes = scheduler.update_view;
		scheduler.destructor=function(){};
		scheduler.init(obj.id,day,mode);
		
		return this.vs[this._viewRestore()].sched;
	}	
	/**
	*   @desc: attaches a dhtmlxTree to a window
	*   @param: rootId - not mandatory, tree super root, see dhtmlxTree documentation for details
	*   @type: public
	*/
	this.obj.attachTree = function(rootId) {
		if (this._isWindow && this.skin == "dhx_skyblue") {
			this.vs[this.av].dhxcont.mainCont[this.av].style.border = "#a4bed4 1px solid";
			this._redraw();
		}
		var obj = document.createElement("DIV");
		obj.id = "dhxTreeObj_"+this._genStr(12);
		obj.style.width = "100%";
		obj.style.height = "100%";
		obj.cmp = "tree";
		document.body.appendChild(obj);
		this.attachObject(obj.id, false, true);
		this.vs[this.av].tree = new dhtmlXTreeObject(obj.id, "100%", "100%", (rootId||0));
		this.vs[this.av].tree.setSkin(this.skin);
		// this.tree.allTree.style.paddingTop = "2px";
		this.vs[this.av].tree.allTree.childNodes[0].style.marginTop = "2px";
		this.vs[this.av].tree.allTree.childNodes[0].style.marginBottom = "2px";
		
		this.vs[this.av].treeId = obj.id;
		this.vs[this.av].treeObj = obj;
		
		return this.vs[this._viewRestore()].tree;
	}
	/**
	*   @desc: attaches a dhtmlxTabbar to a window
	*   @type: public
	*/
	this.obj.attachTabbar = function(mode) {
		
		if (this._isWindow && this.skin == "dhx_skyblue") {
			this.vs[this.av].dhxcont.style.border = "none";
			this.setDimension(this.w, this.h);
		}
		
		var obj = document.createElement("DIV");
		obj.id = "dhxTabbarObj_"+this._genStr(12);
		obj.style.width = "100%";
		obj.style.height = "100%";
		obj.style.overflow = "hidden";
		obj.cmp = "tabbar";
		document.body.appendChild(obj);
		this.attachObject(obj.id, false, true);
		
		// manage dockcell if exists
		if (this.className == "dhtmlxLayoutSinglePoly") this.hideHeader();
		//
		this.vs[this.av].tabbar = new dhtmlXTabBar(obj.id, mode||"top", 20);
		if (!this._isWindow) this.vs[this.av].tabbar._s.expand = true;
		this.vs[this.av].tabbar.setSkin(this.skin);
		this.vs[this.av].tabbar.adjustOuterSize();
		this.vs[this.av].tabbarId = obj.id;
		this.vs[this.av].tabbarObj = obj;
		
		return this.vs[this._viewRestore()].tabbar;
	}
	/**
	*   @desc: attaches a dhtmlxFolders to a window
	*   @type: public
	*/
	this.obj.attachFolders = function() {
		if (this._isWindow && this.skin == "dhx_skyblue") {
			this.vs[this.av].dhxcont.mainCont[this.av].style.border = "#a4bed4 1px solid";
			this._redraw();
		}
		var obj = document.createElement("DIV");
		obj.id = "dhxFoldersObj_"+this._genStr(12);
		obj.style.width = "100%";
		obj.style.height = "100%";
		obj.style.overflow = "hidden";
		obj.cmp = "folders";
		document.body.appendChild(obj);
		this.attachObject(obj.id, false, true);
		this.vs[this.av].folders = new dhtmlxFolders(obj.id);
		this.vs[this.av].folders.setSizes();
		
		this.vs[this.av].foldersId = obj.id;
		this.vs[this.av].foldersObj = obj;
		
		return this.vs[this._viewRestore()].folders;
	}
	/**
	*   @desc: attaches a dhtmlxAccordion to a window
	*   @type: public
	*/
	this.obj.attachAccordion = function() {
		if (this._isWindow && this.skin == "dhx_skyblue") {
			this.vs[this.av].dhxcont.mainCont[this.av].style.border = "#a4bed4 1px solid";
			this._redraw();
		}
		
		var obj = document.createElement("DIV");
		obj.id = "dhxAccordionObj_"+this._genStr(12);
		
		if (this.skin == "dhx_web") {
			obj.style.left = "0px";
			obj.style.top = "0px";
			obj.style.width = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.width)+"px";
			obj.style.height = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.height)+"px";
		} else {
		
			obj.style.left = "-1px";
			obj.style.top = "-1px";
			obj.style.width = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.width)+2+"px";
			obj.style.height = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.height)+2+"px";
		}
		//
		obj.style.position = "relative";
		obj.cmp = "accordion";
		document.body.appendChild(obj);
		this.attachObject(obj.id, false, true);
		
		this.vs[this.av].accordion = new dhtmlXAccordion(obj.id, this.skin);
		this.vs[this.av].accordion.setSizes();
		this.vs[this.av].accordionId = obj.id;
		this.vs[this.av].accordionObj = obj;
		
		return this.vs[this._viewRestore()].accordion;
	}
	/**
	*   @desc: attaches a dhtmlxLayout to a window
	*   @param: view - layout's pattern
	*   @param: skin - layout's skin
	*   @type: public
	*/
	this.obj.attachLayout = function(view, skin) {
		
		// attach layout to layout
		if (this._isCell && this.skin == "dhx_skyblue") {
			this.hideHeader();
			this.vs[this.av].dhxcont.style.border = "0px solid white";
			this.adjustContent(this.childNodes[0], 0);
		}
		
		if (this._isCell && this.skin == "dhx_web") {
			this.hideHeader();
		}
		
		var obj = document.createElement("DIV");
		obj.id = "dhxLayoutObj_"+this._genStr(12);
		obj.style.overflow = "hidden";
		obj.style.position = "absolute";
		
		obj.style.left = "0px";
		obj.style.top = "0px";
		obj.style.width = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.width)+"px";
		obj.style.height = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.height)+"px";
		
		if (this._isAcc && this.skin == "dhx_skyblue") {
			obj.style.left = "-1px";
			obj.style.top = "-1px";
			obj.style.width = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.width)+2+"px";
			obj.style.height = parseInt(this.vs[this.av].dhxcont.mainCont[this.av].style.height)+2+"px";
		}
		
		// needed for layout's init
		obj.dhxContExists = true;
		obj.cmp = "layout";
		document.body.appendChild(obj);
		this.attachObject(obj.id, false, true);
		
		this.vs[this.av].layout = new dhtmlXLayoutObject(obj, view, (skin||this.skin));
		// window/layout events configuration
		if (this._isWindow) this.attachEvent("_onBeforeTryResize", this.vs[this.av].layout._defineWindowMinDimension);
		
		this.vs[this.av].layoutId = obj.id;
		this.vs[this.av].layoutObj = obj;
		
		// this.adjust();
		
		return this.vs[this._viewRestore()].layout;
	}
	/**
	*   @desc: attaches a dhtmlxEditor to a window
	*   @param: skin - not mandatory, editor's skin
	*   @type: public
	*/
	this.obj.attachEditor = function(skin) {
		if (this._isWindow && this.skin == "dhx_skyblue") {
			this.vs[this.av].dhxcont.mainCont[this.av].style.border = "#a4bed4 1px solid";
			this._redraw();
		}
		var obj = document.createElement("DIV");
		obj.id = "dhxEditorObj_"+this._genStr(12);
		obj.style.position = "relative";
		obj.style.display = "none";
		obj.style.overflow = "hidden";
		obj.style.width = "100%";
		obj.style.height = "100%";
		obj.cmp = "editor";
		document.body.appendChild(obj);
		//
		this.attachObject(obj.id, false, true);
		//
		this.vs[this.av].editor = new dhtmlXEditor(obj.id, this.skin);
		
		this.vs[this.av].editorId = obj.id;
		this.vs[this.av].editorObj = obj;
		return this.vs[this._viewRestore()].editor;
		
	}
	
	this.obj.attachMap = function(opts) {
		
		var obj = document.createElement("DIV");
		obj.id = "GMapsObj_"+this._genStr(12);
		obj.style.position = "relative";
		obj.style.display = "none";
		obj.style.overflow = "hidden";
		obj.style.width = "100%";
		obj.style.height = "100%";
		obj.cmp = "gmaps";
		document.body.appendChild(obj);
		
		this.attachObject(obj.id, false, true);
		
		if (!opts) opts = {center: new google.maps.LatLng(40.719837,-73.992348), zoom: 11, mapTypeId: google.maps.MapTypeId.ROADMAP};
		this.vs[this.av].gmaps = new google.maps.Map(obj, opts);
		
		return this.vs[this.av].gmaps;
		
	}
	
	/**
	*   @desc: attaches an object into a window
	*   @param: obj - object or object id
	*   @param: autoSize - set true to adjust a window to object's dimension
	*   @type: public
	*/
	this.obj.attachObject = function(obj, autoSize, localCall) {
		if (typeof(obj) == "string") obj = document.getElementById(obj);
		if (autoSize) {
			obj.style.visibility = "hidden";
			obj.style.display = "";
			var objW = obj.offsetWidth;
			var objH = obj.offsetHeight;
		}
		this._attachContent("obj", obj);
		if (autoSize && this._isWindow) {
			obj.style.visibility = "visible";
			this._adjustToContent(objW, objH);
			/* this._engineAdjustWindowToContent(this, objW, objH); */
		}
		if (!localCall) this._viewRestore();
	}
	/**
	*
	*
	*/
	this.obj.detachObject = function(remove, moveTo) {
		
		// detach dhtmlx components
		
		var p = null;
		var pObj = null;
		var t = ["tree","grid","layout","tabbar","accordion","folders"];
		for (var q=0; q<t.length; q++) {
			if (this.vs[this.av][t[q]]) {
				p = this.vs[this.av][t[q]];
				pObj = this.vs[this.av][t[q]+"Obj"];
				if (remove) {
					if (p.unload) p.unload();
					if (p.destructor) p.destructor();
					while (pObj.childNodes.length > 0) pObj.removeChild(pObj.childNodes[0]);
					pObj.parentNode.removeChild(pObj);
					pObj = null;
					p = null;
				} else {
					document.body.appendChild(pObj);
					pObj.style.display = "none";
				}
				this.vs[this.av][t[q]] = null;
				this.vs[this.av][t[q]+"Id"] = null;
				this.vs[this.av][t[q]+"Obj"] = null;
			}
		}
		
		if (p != null && pObj != null) return new Array(p, pObj);
		
		// detach any other content
		if (remove && this.vs[this.av]._frame) {
			this._detachURLEvents();
			this.vs[this.av]._frame = null;
		}
		
		var objA = this.vs[this.av].dhxcont.mainCont[this.av];
		while (objA.childNodes.length > 0) {
			if (remove == true) {
				// add frame events removing
				objA.removeChild(objA.childNodes[0]);
			} else {
				var obj = objA.childNodes[0];
				if (moveTo != null) {
					if (typeof(moveTo) != "object") moveTo = document.getElementById(moveTo);
					moveTo.appendChild(obj);
				} else {
					document.body.appendChild(obj);
				}
				obj.style.display = "none";
			}
		}
	}
	
	/**
	*   @desc: appends an object into a window
	*   @param: obj - object or object id
	*   @type: public
	*/
	this.obj.appendObject = function(obj) {
		if (typeof(obj) == "string") { obj = document.getElementById(obj); }
		this._attachContent("obj", obj, true);
	}
	/**
	*   @desc: attaches an html string as an object into a window
	*   @param: str - html string
	*   @type: public
	*/
	this.obj.attachHTMLString = function(str) {
		this._attachContent("str", str);
		var z=str.match(/<script[^>]*>[^\f]*?<\/script>/g)||[];
		for (var i=0; i<z.length; i++){
			var s=z[i].replace(/<([\/]{0,1})script[^>]*>/g,"")
			if (window.execScript) window.execScript(s);
			else window.eval(s);
		}
	}
	/**
	*   @desc: attaches an url into a window
	*   @param: url
	*   @param: ajax - loads an url with ajax
	*   @type: public
	*/
	this.obj.attachURL = function(url, ajax) {
		this._attachContent((ajax==true?"urlajax":"url"), url, false);
		this._viewRestore();
	}
	this.obj.adjust = function() {
		if (this.skin == "dhx_skyblue") {
			if (this.vs[this.av].menu) {
				if (this._isWindow || this._isLayout) {
					this.vs[this.av].menu._topLevelOffsetLeft = 0;
					document.getElementById(this.vs[this.av].menuId).style.height = "26px";
					this.vs[this.av].menuHeight = document.getElementById(this.vs[this.av].menuId).offsetHeight;
					if (this._doOnAttachMenu) this._doOnAttachMenu("show");
				}
				if (this._isCell) {
					document.getElementById(this.vs[this.av].menuId).className += " in_layoutcell";
					// document.getElementById(this.menuId).style.height = "25px";
					this.vs[this.av].menuHeight = 25;
				}
				if (this._isAcc) {
					document.getElementById(this.vs[this.av].menuId).className += " in_acccell";
					// document.getElementById(this.menuId).style.height = "25px";
					this.vs[this.av].menuHeight = 25;
				}
				if (this._doOnAttachMenu) this._doOnAttachMenu("adjust");
			}
			if (this.vs[this.av].toolbar) {
				if (this._isWindow || this._isLayout) {
					document.getElementById(this.vs[this.av].toolbarId).style.height = "29px";
					this.vs[this.av].toolbarHeight = document.getElementById(this.vs[this.av].toolbarId).offsetHeight;
					if (this._doOnAttachToolbar) this._doOnAttachToolbar("show");
				}
				if (this._isCell) {
					document.getElementById(this.vs[this.av].toolbarId).className += " in_layoutcell";
				}
				if (this._isAcc) {
					document.getElementById(this.vs[this.av].toolbarId).className += " in_acccell";
				}
			}
		}
		
		if (this.skin == "dhx_web") {
			
			
		}
	}
	// attach content obj|url
	this.obj._attachContent = function(type, obj, append) {
		// clear old content
		if (append !== true) {
			if (this.vs[this.av]._frame) {
				this._detachURLEvents();
				this.vs[this.av]._frame = null;
			}
			while (this.vs[this.av].dhxcont.mainCont[this.av].childNodes.length > 0) this.vs[this.av].dhxcont.mainCont[this.av].removeChild(this.vs[this.av].dhxcont.mainCont[this.av].childNodes[0]);
		}
		// attach
		if (type == "url") {
			if (this._isWindow && obj.cmp == null && this.skin == "dhx_skyblue") {
				this.vs[this.av].dhxcont.mainCont[this.av].style.border = "#a4bed4 1px solid";
				this._redraw();
			}
			var fr = document.createElement("IFRAME");
			fr.frameBorder = 0;
			fr.border = 0;
			fr.style.width = "100%";
			fr.style.height = "100%";
			fr.setAttribute("src","javascript:false;");
			this.vs[this.av].dhxcont.mainCont[this.av].appendChild(fr);
			fr.src = obj;
			
			// ?? this._frame = fr;
			this.vs[this.av]._frame = fr;
			this._attachURLEvents();
			
		} else if (type == "urlajax") {
			
			if (this._isWindow && obj.cmp == null && this.skin == "dhx_skyblue") {
				this.vs[this.av].dhxcont.mainCont[this.av].style.border = "#a4bed4 1px solid";
				this.vs[this.av].dhxcont.mainCont[this.av].style.backgroundColor = "#FFFFFF";
				this._redraw();
			}
			var t = this;
			var xmlParser = function(){
				t.attachHTMLString(this.xmlDoc.responseText, this);
				//if (t._doOnAttachURL) t._doOnAttachURL(false);
				if (t._doOnFrameContentLoaded) t._doOnFrameContentLoaded();
				this.destructor();
			}
			var xmlLoader = new dtmlXMLLoaderObject(xmlParser, window);
			xmlLoader.dhxWindowObject = this;
			xmlLoader.loadXML(obj);
			
		} else if (type == "obj") {
			
			if (this._isWindow && obj.cmp == null && this.skin == "dhx_skyblue") {
				this.vs[this.av].dhxcont.mainCont[this.av].style.border = "#a4bed4 1px solid";
				this.vs[this.av].dhxcont.mainCont[this.av].style.backgroundColor = "#FFFFFF";
				this._redraw();
			}
			this.vs[this.av].dhxcont._frame = null;
			this.vs[this.av].dhxcont.mainCont[this.av].appendChild(obj);
			// this._engineGetWindowContent(win).style.overflow = (append===true?"auto":"hidden");
			// win._content.childNodes[2].appendChild(obj);
			this.vs[this.av].dhxcont.mainCont[this.av].style.overflow = (append===true?"auto":"hidden");
			obj.style.display = "";
			
		} else if (type == "str") {
			
			if (this._isWindow && obj.cmp == null && this.skin == "dhx_skyblue") {
				this.vs[this.av].dhxcont.mainCont[this.av].style.border = "#a4bed4 1px solid";
				this.vs[this.av].dhxcont.mainCont[this.av].style.backgroundColor = "#FFFFFF";
				this._redraw();
			}
			this.vs[this.av].dhxcont._frame = null;
			this.vs[this.av].dhxcont.mainCont[this.av].innerHTML = obj;
		}
	}
	
	this.obj._attachURLEvents = function() {
		var t = this;
		var fr = this.vs[this.av]._frame;
		if (_isIE) {
			fr.onreadystatechange = function(a) {
				if (fr.readyState == "complete") {
					try {fr.contentWindow.document.body.onmousedown=function(){if(t._doOnFrameMouseDown)t._doOnFrameMouseDown();};}catch(e){};
					try{if(t._doOnFrameContentLoaded)t._doOnFrameContentLoaded();}catch(e){};
				}
			}
		} else {
			fr.onload = function() {
				try{fr.contentWindow.onmousedown=function(){if(t._doOnFrameMouseDown)t._doOnFrameMouseDown();};}catch(e){};
				try{if(t._doOnFrameContentLoaded)t._doOnFrameContentLoaded();}catch(e){};
			}
		}
	}
	
	this.obj._detachURLEvents = function() {
		if (_isIE) {
			try {
				this.vs[this.av]._frame.onreadystatechange = null;
				this.vs[this.av]._frame.contentWindow.document.body.onmousedown = null;
				this.vs[this.av]._frame.onload = null;
			} catch(e) {};
		} else {
			try {
				this.vs[this.av]._frame.contentWindow.onmousedown = null;
				this.vs[this.av]._frame.onload = null;
			} catch(e) {};
		}
	}
	
	this.obj.showMenu = function() {
		if (!(this.vs[this.av].menu && this.vs[this.av].menuId)) return;
		if (document.getElementById(this.vs[this.av].menuId).style.display != "none") return;
		this.vs[this.av].menuHidden = false;
		if (this._doOnAttachMenu) this._doOnAttachMenu("show");
		document.getElementById(this.vs[this.av].menuId).style.display = "";
		this._viewRestore();
	}
	
	this.obj.hideMenu = function() {
		if (!(this.vs[this.av].menu && this.vs[this.av].menuId)) return;
		if (document.getElementById(this.vs[this.av].menuId).style.display == "none") return;
		document.getElementById(this.vs[this.av].menuId).style.display = "none";
		this.vs[this.av].menuHidden = true;
		if (this._doOnAttachMenu) this._doOnAttachMenu("hide");
		this._viewRestore();
	}
	
	this.obj.showToolbar = function() {
		if (!(this.vs[this.av].toolbar && this.vs[this.av].toolbarId)) return;
		if (document.getElementById(this.vs[this.av].toolbarId).style.display != "none") return;
		this.vs[this.av].toolbarHidden = false;
		if (this._doOnAttachToolbar) this._doOnAttachToolbar("show");
		document.getElementById(this.vs[this.av].toolbarId).style.display = "";
		this._viewRestore();
	}
	
	this.obj.hideToolbar = function() {
		if (!(this.vs[this.av].toolbar && this.vs[this.av].toolbarId)) return;
		if (document.getElementById(this.vs[this.av].toolbarId).style.display == "none") return;
		this.vs[this.av].toolbarHidden = true;
		document.getElementById(this.vs[this.av].toolbarId).style.display = "none";
		if (this._doOnAttachToolbar) this._doOnAttachToolbar("hide");
		this._viewRestore();
	}
	
	this.obj.showStatusBar = function() {
		if (!(this.vs[this.av].sb && this.vs[this.av].sbId)) return;
		if (document.getElementById(this.vs[this.av].sbId).style.display != "none") return;
		this.vs[this.av].sbHidden = false;
		if (this._doOnAttachStatusBar) this._doOnAttachStatusBar("show");
		document.getElementById(this.vs[this.av].sbId).style.display = "";
		this._viewRestore();
	}
	
	this.obj.hideStatusBar = function() {
		if (!(this.vs[this.av].sb && this.vs[this.av].sbId)) return;
		if (document.getElementById(this.vs[this.av].sbId).style.display == "none") return;
		this.vs[this.av].sbHidden = true;
		document.getElementById(this.vs[this.av].sbId).style.display = "none";
		if (this._doOnAttachStatusBar) this._doOnAttachStatusBar("hide");
		this._viewRestore();
	}
	
	this.obj._dhxContDestruct = function() {
		
		// clear attached objects
		
		var av = this.av;
		for (var a in this.vs) {
			
			this.av = a;
			
			// menu, toolbar, status
			this.detachMenu();
			this.detachToolbar();
			this.detachStatusBar();
			
			// remove any attached object or dhtmlx component
			this.detachObject(true);
			
			this.vs[a].dhxcont.mainCont[a].parentNode.removeChild(this.vs[a].dhxcont.mainCont[a]);
			this.vs[a].dhxcont.mainCont[a] = null;
			
		}
		
		this.vs[this.dv].dhxcont.mainCont = null;
		this.vs[this.dv].dhxcont.parentNode.removeChild(this.vs[this.dv].dhxcont);
		for (var a in this.vs) this.vs[a].dhxcont = null;
		this.vs = null;
		
		this.attachMenu = null;
		this.attachToolbar = null;
		this.attachStatusBar = null;
		this.detachMenu = null;
		this.detachToolbar = null;
		this.detachStatusBar = null;
		this.showMenu = null;
		this.showToolbar = null;
		this.showStatusBar = null;
		this.hideMenu = null;
		this.hideToolbar = null;
		this.hideStatusBar = null;
		
		this.attachGrid = null;
		this.attachScheduler = null;
		this.attachTree = null;
		this.attachTabbar = null;
		this.attachFolders = null;
		this.attachAccordion = null;
		this.attachLayout = null;
		this.attachEditor = null;
		this.attachObject = null;
		this.detachObject = null;
		this.appendObject = null;
		this.attachHTMLString = null;
		this.attachURL = null;
		
		this.view = null;
		this.show = null;
		this.adjust = null;
		this.setMinContentSize = null;
		this.moveContentTo = null;
		this.adjustContent = null;
		this.coverBlocker = null;
		this.showCoverBlocker = null;
		this.hideCoverBlocker = null;
		this.updateNestedObjects = null;
		
		this._attachContent = null;
		this._attachURLEvents = null;
		this._detachURLEvents = null;
		this._viewRestore = null;
		this._setPadding = null;
		this._init = null;
		this._genStr = null;
		this._dhxContDestruct = null;
		
		that.st.parentNode.removeChild(that.st);
		that.st = null;
		
		that.setContent = null;
		that.dhxcont = null; // no more used at all?
		that.obj = null;
		that = null;
		
		// remove attached components
		/*
		for (var a in this.vs) {
		
			if (this.vs[a].layout) this.vs[a].layout.unlaod();
			if (this.vs[a].accordion) this.vs[a].accordion.unlaod();
			if (this.vs[a].sched) this.vs[a].sched.destructor();
			
			this.vs[a].layout = null;
			this.vs[a].accordion = null;
			this.vs[a].sched = null;
			
		}
		*/
		// extended functionality
		if (dhtmlx.detaches) for (var a in dhtmlx.detaches) dhtmlx.detaches[a](this);
		
	}
	
	// extended functionality
	if (dhtmlx.attaches) for (var a in dhtmlx.attaches) this.obj[a] = dhtmlx.attaches[a];
	
}
