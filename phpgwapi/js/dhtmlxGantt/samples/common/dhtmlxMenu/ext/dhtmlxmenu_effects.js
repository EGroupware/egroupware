/* effects: opacity, slide */

dhtmlXMenuObject.prototype.enableEffect = function(name, maxOpacity, effectSpeed) {
	
	this._menuEffect = (name=="opacity"||name=="slide"||name=="slide+"?name:false);
	
	for (var a in this.idPull) {
		if (a.search(/polygon/) === 0) {
			this._pOpacityApply(a,(_isIE?100:1));
			this.idPull[a].style.height = "";
		}
		
	}
	
	// opacity max value
	this._pOpMax = (typeof(maxOpacity)=="undefined"?100:maxOpacity)/(_isIE?1:100);
	
	// opacity css styles
	this._pOpStyleName = (_isIE?"filter":"opacity");
	this._pOpStyleValue = (_isIE?"progid:DXImageTransform.Microsoft.Alpha(Opacity=#)":"#");
	
	
	// count of steps to open full polygon
	this._pSlSteps = (_isIE?10:20);
	
	// timeout to open polygon
	this._pSlTMTimeMax = effectSpeed||50;
	
}

// extended show
dhtmlXMenuObject.prototype._showPolygonEffect = function(pId) {
	this._pShowHide(pId, true);
};

// extended hide
dhtmlXMenuObject.prototype._hidePolygonEffect = function(pId) {
	this._pShowHide(pId, false);
};

// apply opacity css
dhtmlXMenuObject.prototype._pOpacityApply = function(pId, val) {
	this.idPull[pId].style[this._pOpStyleName] = String(this._pOpStyleValue).replace("#", val||this.idPull[pId]._op);
};

dhtmlXMenuObject.prototype._pShowHide = function(pId, mode) {
	
	if (!this.idPull) return;
	
	// check if mode in progress
	if (this.idPull[pId]._tmShow != null) {
		if ((this.idPull[pId]._step_h > 0 && mode == true) || (this.idPull[pId]._step_h < 0 && mode == false)) return;
		window.clearTimeout(this.idPull[pId]._tmShow);
		this.idPull[pId]._tmShow = null;
		this.idPull[pId]._max_h = null;
	}
	
	if (mode == false && (this.idPull[pId].style.visibility == "hidden" || this.idPull[pId].style.display == "none")) return;
	
	if (mode == true && this.idPull[pId].style.display == "none") {
		this.idPull[pId].style.visibility = "hidden";
		this.idPull[pId].style.display = "";
	}
	
	// init values or show-hide revert
	if (this.idPull[pId]._max_h == null) {
		
		this.idPull[pId]._max_h = parseInt(this.idPull[pId].offsetHeight);
		this.idPull[pId]._h = (mode==true?0:this.idPull[pId]._max_h);
		this.idPull[pId]._step_h = Math.round(this.idPull[pId]._max_h/this._pSlSteps)*(mode==true?1:-1);
		if (this.idPull[pId]._step_h == 0) return;
		this.idPull[pId]._step_tm = Math.round(this._pSlTMTimeMax/this._pSlSteps);
		
		if (this._menuEffect == "slide+" || this._menuEffect == "opacity") {
			this.idPull[pId].op_tm = this.idPull[pId]._step_tm;
			this.idPull[pId].op_step = (this._pOpMax/this._pSlSteps)*(mode==true?1:-1);
			if (_isIE) this.idPull[pId].op_step = Math.round(this.idPull[pId].op_step);
			this.idPull[pId]._op = (mode==true?0:this._pOpMax);
			this._pOpacityApply(pId);
		} else {
			this.idPull[pId]._op = (_isIE?100:1);
			this._pOpacityApply(pId);
		}
		
		// show first time
		if (this._menuEffect.search(/slide/) === 0) this.idPull[pId].style.height = "0px";
		this.idPull[pId].style.visibility = "visible";
		
	}
	
	// run cycle
	this._pEffectSet(pId, this.idPull[pId]._h+this.idPull[pId]._step_h);
	
}

dhtmlXMenuObject.prototype._pEffectSet = function(pId, t) {
	
	if (!this.idPull) return;
	
	if (this.idPull[pId]._tmShow) window.clearTimeout(this.idPull[pId]._tmShow);
	
	// check and apply next step
	this.idPull[pId]._h = Math.max(0,Math.min(t,this.idPull[pId]._max_h));
	if (this._menuEffect.search(/slide/) === 0) this.idPull[pId].style.height = this.idPull[pId]._h+"px";
	
	t += this.idPull[pId]._step_h;
	
	if (this._menuEffect == "slide+" || this._menuEffect == "opacity") {
		this.idPull[pId]._op = Math.max(0,Math.min(this._pOpMax,this.idPull[pId]._op+this.idPull[pId].op_step));
		this._pOpacityApply(pId);
	}
	
	if ((this.idPull[pId]._step_h > 0 && t <= this.idPull[pId]._max_h) || (this.idPull[pId]._step_h < 0 && t >= 0)) {
		// continue
		var k = this;
		this.idPull[pId]._tmShow = window.setTimeout(function(){k._pEffectSet(pId,t);}, this.idPull[pId]._step_tm);
	} else {
		
		// clear height
		if (this._menuEffect.search(/slide/) === 0) this.idPull[pId].style.height = "";
		
		// hide completed
		if (this.idPull[pId]._step_h < 0) this.idPull[pId].style.visibility = "hidden";
		
		if (this._menuEffect == "slide+" || this._menuEffect == "opacity") {
			this.idPull[pId]._op = (this.idPull[pId]._step_h<0?(_isIE?100:1):this._pOpMax);
			this._pOpacityApply(pId);
		}
		
		// clear values
		this.idPull[pId]._tmShow = null;
		this.idPull[pId]._h = null;
		this.idPull[pId]._max_h = null;
		///this.idPull[pId]._step_h = null;
		this.idPull[pId]._step_tm = null;
	}
	
};
