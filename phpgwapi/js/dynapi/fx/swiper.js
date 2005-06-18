/*
	DynAPI Distribution
	Swiper Animation Extension - originally designed by Erik Arvidsson (http://web.eae.net)
	IncDec addon - Created by Daniel Tiru (http://www.tiru.se)

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
	
	requires: DynLayer
	
*/

Swiper = {}; // used by dynapi.library

DynLayer.prototype.swipeTo = function(dir, steps, ms, min) {

	this._swipeSteps = (steps!=null)? steps: 4;
	this._swipeMS = (ms!=null)? ms:25;
	this._swipeDir=dir;
	this._swiperMin=min;
	if (this._swiperMinimized==null) { 
		this._swiperMinimized = 0
	}

	if (this.swipeTimer != null) window.clearTimeout(this.swipeTimer);
		
	if (!this._swipeCnt) {		// No animation yet!
		this._swipeOrgX  = this.getX();
		this._swipeOrgY  = this.getY();
		this._swipeOrgWidth = this.getWidth();
		this._swipeOrgHeight  = this.getHeight();
	}
	
	this._swipeCnt = this._swipeSteps;
	if (dir.substr(0,3)!='dec' && dir.substr(0,3)!='inc') {
		this.setClip([0,0,0,0]);
	}
	window.setTimeout(this+"._swipe()", this._swipeMS);
};
DynLayer.prototype._swipe = function() {
	var steps	= this._swipeSteps;
	var x = this._swipeOrgX;
	var y = this._swipeOrgY;
	var w = this._swipeOrgWidth;
	var h = this._swipeOrgHeight;
	var min = this._swiperMin;
	
	if (this._swipeCnt == 0) {
		if (this._swipeDir.substr(0,3)!='dec' && this._swipeDir.substr(0,3)!='inc') {
			this.setClip([0, w, h,0]);
		}
		else if(this._swipeDir.substr(0,3)=='dec') {
			this._swiperMinimized=1;
		}
		else if(this._swipeDir.substr(0,3)=='inc') {
			this._swiperMinimized=0;
		}
		this.invokeEvent('swipefinish');
		return;
	}
	else {
		this._swipeCnt--;
		this.setVisible(true);
		switch (this._swipeDir) {
			case "bottom":		//down (see the numpad)
				this.setClip([h * this._swipeCnt / steps, w, h, 0]);
				this.setY(y - h * this._swipeCnt / steps);
				break;
			case "top":
				this.setClip([0, w, h * (steps - this._swipeCnt) / steps, 0]);
				this.setY(y + h * this._swipeCnt / steps);
				break;
			case "right":
				this.setClip([0, w, h,w * this._swipeCnt / steps]);
				this.setX(x - w * this._swipeCnt / steps);
				break;
			case "left":
				this.setClip([0, w * (steps - this._swipeCnt) / steps, h, 0]);
				this.setX(x + w * this._swipeCnt / steps);
				break;
			case "bottom-right":
				this.setClip([h * this._swipeCnt / steps, w, h, w * this._swipeCnt / steps]);
				this.setX(x - w * this._swipeCnt / steps);
				this.setY(y - h * this._swipeCnt / steps);
				break;
			case "bottom-left":
				this.setClip([h * this._swipeCnt / steps, w * (steps - this._swipeCnt) / steps, h, 0]);
				this.setX(x + w * this._swipeCnt / steps);
				this.setY(y - h * this._swipeCnt / steps);
				break;
			case "top-left":
				this.setClip([0, w * (steps - this._swipeCnt) / steps, h * (steps - this._swipeCnt) / steps, 0]);
				this.setX(x + w * this._swipeCnt / steps);
				this.setY(y + h * this._swipeCnt / steps);
				break;
			case "top-right":
				this.setClip([0, w, h * (steps - this._swipeCnt) / steps, w * this._swipeCnt / steps]);
				this.setX(x - w * this._swipeCnt / steps);
				this.setY(y + h * this._swipeCnt / steps);
				break;
			// inc-dec
			case "dec-right":
				if (this._swiperMinimized==0) {
					if ((w/steps*this._swipeCnt) > min) {
						this.setClip([0, (w/steps*this._swipeCnt), h, 0]);
					}
					else this.setClip([0, min, h, 0]);
				}
				break;
			case "inc-right":
				//var clippos = this.getClip().toString().split(',');
				if (this.getClip()[1] < w-(w/steps*this._swipeCnt)) {
					if (this._swiperMinimized==1) {
						this.setClip([0, w-(w/steps*this._swipeCnt), h, 0]);
					}
				}
				break;
			case "dec-left":
				if (this._swiperMinimized==0) {
					if ((w/steps*this._swipeCnt) > min) {
						this.setClip([0, Math.round(w/steps*this._swipeCnt), h, 0]);
						this.setX(w+(x-(Math.round(w/steps*this._swipeCnt))));
					}
					else{
						this.setClip([0, min, h, 0]);
						this.setX(w+x-min);
					}
				}
				break;
			case "inc-left":
				if (this._swiperMinimized==1) {
					if (this.getClip()[1] < w-Math.round(w/steps*this._swipeCnt)) {
						this.setClip([0, w-Math.round(w/steps*this._swipeCnt), h, 0]);
						if (w-(w/steps*this._swipeCnt) < x) {
							this.setX(x-(w-Math.round(w/steps*(this._swipeCnt)+min)));
						}
						else this.setX((steps*w/steps)-(x));
					}
				}
				break;
			case "dec-down":
				if (this._swiperMinimized==0) {
					if ((h/steps*this._swipeCnt) > min) {
						this.setClip([0, w, (h/steps*this._swipeCnt), 0]);
					}
					else this.setClip([0, w, min, 0]);
				}
				break;
			case "inc-down":
				if (this._swiperMinimized==1) {
					if (this.getClip()[2] < h-(h/steps*this._swipeCnt)) {
						this.setClip([0,w, h-(h/steps*this._swipeCnt), 0]);
					}
				}
				break;
			case "dec-up":
				if (this._swiperMinimized==0) {
					if ((h/steps*this._swipeCnt) > min) {
						this.setClip([0, w, Math.round(h/steps*this._swipeCnt), 0]);
						this.setY((h+y-(Math.round(h/steps*this._swipeCnt))));
					}
					else{
						this.setClip([0, w, min, 0]);
						this.setY(h+y-min);
					}
				}
				break;
			case "inc-up":
				if (this._swiperMinimized==1) {
					if (this.getClip()[2] < h-Math.round(h/steps*this._swipeCnt)) {
						this.setClip([0, w, h-Math.round(h/steps*this._swipeCnt), 0]);
						if (h-(h/steps*this._swipeCnt) < y) {
							this.setY(y-(h-Math.round(h/steps*(this._swipeCnt)+min)));
						}
						else this.setY((steps*h/steps)-(y-min));
					}
				}
				break;
		}		
		this.swipeTimer = window.setTimeout(this+"._swipe()", this._swipeMS);
	}
};

