/*
	DynAPI Distribution
	Slide Animation Extension

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

	requires: dynapi.fx.Thread
*/

// Generates a path between 2 points, stepping inc pixels at a time
function SlideAnimation(x1,y1,x2,y2,inc) {
	var n,dx,dy,lx,ly,p=[];
	if (x2==null) x2 = x1;
	if (y2==null) y2 = y1;

	lx = x2-x1;
	ly = y2-y1;
	n = Math.sqrt(Math.pow(lx,2) + Math.pow(ly,2))/(inc||10);
	dx = lx/n;
	dy = ly/n;
	for (var i=0;i<n;i++) {
		p[i*2] = x1 + Math.round(dx*i);
		p[i*2+1] = y1 + Math.round(dy*i);
	}
	if (p[i*2-2] != x2 || p[i*2-1] != y2) {
		p[i*2] = x2;
		p[i*2+1] = y2;
	}
	return p;
};


DynLayer.prototype.slideTo = function(x2,y2,inc,ms) {
	if (this.x!=x2 || this.y!=y2) {
		if (!this._thread) this._thread = new Thread(this);
		if (ms) this._thread.interval = ms;
		this._thread.play(SlideAnimation(this.x,this.y,x2,y2,inc));
	}
};
DynLayer.prototype.slideStop = function () {
	if (this._thread) this._thread.stop();
};
