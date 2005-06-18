/*
	DS: the extra features of this version will possibly be rebuilt as an advanced timeline object
	
	DynAPI Distribution
	PathAnimation Class

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

	requires: dynapi.fx.Thread
*/

function PathAnimation(dlyr) {
	this.Thread = Thread;
	this.Thread(dlyr);
	this.paths = [];
	this.pathPlaying = null;
}
var p = dynapi.setPrototype('PathAnimation','Thread');

p.add = function (path, loops, resets) {
	var n = this.paths.length;
	this.paths[n] = path;
	this.setLoops(n,loops);
	this.setResets(n,resets);
	this.setFrame(n,0);
	return n;
};
p.setLoops = function (n, loops) {
	this.paths[n].loops = (loops);
};
p.setResets = function (n, resets) {
	this.paths[n].resets = (resets);
};
p.setFrame = function (n, frame) {
	this.paths[n].frame = frame;
};
p.playAnimation = function (noevt) {
	if (!this.playing) {
		this.pathPlaying = null;
		if (arguments[0]==null) arguments[0] = 0;
		if (typeof(arguments[0]) == "number") {
			this.pathPlaying = this.paths[arguments[0]];
		}
		else if (typeof(arguments[0]) == "object") {  
			this.pathPlaying = arguments[0];
			this.pathPlaying.loops = arguments[1]||false;
			this.pathPlaying.resets = arguments[2]||false;
			this.pathPlaying.frame = 0;
		}
		this.playing = true;
		if (this.dlyr!=null && noevt!=false) this.dlyr.invokeEvent("pathstart");
		this.start();
	}
};
//p._Thread_stop = Thread.prototype.stop;
p.stopAnimation = function (noevt) {
	if (this.pathPlaying && this.pathPlaying.resets && this.dlyr!=null) this.dlyr.setLocation(this.pathPlaying[0],this.pathPlaying[1]);
	this.stop();
	this.pathPlaying = null;
	this.playing = false;
	if (this.dlyr!=null && noevt!=false) this.dlyr.invokeEvent("pathstop");
};
p.run = function () {
	if (!this.playing || this.pathPlaying==null) return;
	var anim = this.pathPlaying;
	if (anim.frame>=anim.length/2) {
		if (anim.loops) {
			anim.frame = 0;
		}
		else if (anim.resets) {
			anim.frame = 0;
			if (this.dlyr!=null) this.dlyr.setLocation(anim[0],anim[1]);
			this.stopAnimation();
			this.dlyr.invokeEvent("pathfinish");
			return;
		}
		else {
			anim.frame = 0;
			this.stopAnimation();
			this.dlyr.invokeEvent("pathfinish");
			return;
		}
	}
	if (anim.frame==0 && (this.dlyr!=null && this.dlyr.x==anim[0] && this.dlyr.y==anim[1])) {
		anim.frame += 1;
	}
	this.newX = anim[anim.frame*2];
	this.newY = anim[anim.frame*2+1];
	
	if (this.dlyr!=null) {
		this.dlyr.invokeEvent("pathrun");
		this.dlyr.setLocation(this.newX,this.newY);
	}
	anim.frame++;
};

