/*
	DynAPI Distribution
	Glide Animation Extension

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

	requires: DynLayer, dynapi.functions.Math
*/

function Thread(dlyr) {
	this.DynObject = DynObject;
	this.DynObject();
	
	if (dlyr) this.dlyr = dlyr;
	else dlyr = this;  // if no dynlayer passed it calls events onto itself
	
	this._frame = 0;
	this._path = null;
	this.loop = false;
}
var p = dynapi.setPrototype('Thread','DynObject');
p.interval = 20;
p.sleep = function (ms) {
	this.interval = Math.abs(parseInt(ms));
	if (this._timer) this.start();
};
p._restart = function () { // starts, or restarts if necessary
	this.stop(false);
	setTimeout(this+'.start()',this.interval+1);
};
p.start = function () { // starts, or restarts if necessary
	if (this._timer) this._restart();
	else {
		this.dlyr.invokeEvent("threadstart");
		this._timer = setInterval(this+'.run()',this.interval);
	}
};
p.run = function () {
	var p=this._path, d=this.dlyr;
	this.dlyr.invokeEvent("threadrun");
	if (p && this.dlyr!=this && this._timer) {
		if (this._frame>=p.length/2) {
			if (this.loop) this._frame = 0;
			else {
				this.stop(false);
				this.dlyr.invokeEvent("threadfinish");
				return;
			}
		}
		if (this._frame==0 && (d.x==p[0] && d.y==p[1])) this.frame += 1; // already at 1st coordinate		
		d.setLocation(p[this._frame*2],p[this._frame*2+1]);
		this._frame++;
	}
};
p.stop = function (noevt) {
	clearInterval(this._timer);
	this._timer = null;
	this._frame = 0;
	if (noevt!=false) this.dlyr.invokeEvent("threadstop");
};
p.play = function (path) {
	this._path = path;
	this.start();
};
