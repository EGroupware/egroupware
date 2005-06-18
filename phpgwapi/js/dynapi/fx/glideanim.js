/*
	DynAPI Distribution
	Glide Animation Extension

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

	requires: dynapi.fx.Thread
*/

function GlideAnimation(x1,y1,x2,y2,angleinc,startSpeed,endSpeed) {
	if (x2==null) x2 = x1;
	if (y2==null) y2 = y1;
		
	var normAngle = dynapi.functions.getNormalizedAngle(x1,y1,x2,y2);
	var distx = x2-x1;
	var disty = y2-y1;
	var distance = Math.sqrt(Math.pow(distx,2) + Math.pow(disty,2));
	angleinc = (angleinc==null)? 7 : Math.abs(angleinc);
	
	// a terrible mess but it works
	var r = 1;
	if (startSpeed == "fast") {
		var centerX = x1;
		var centerY = y1;
		var centerX2 = x2;
		var centerY2 = y2;
		startAngle = 0;
		endAngle = 90;
		if (endSpeed=="fast") distance = distance/2;
	}
	else {
		startAngle = -90;
		endAngle = 0;
		if (endSpeed == "fast") {
			var centerX = x1+distx;
			var centerY = y1+disty;
		}
		else {  // default slow,slow
			var centerX2 = x2-distx/2;
			var centerY2 = y2-disty/2;
			distance = distance/2;
			var centerX = x1+distx/2;
			var centerY = y1+disty/2;
			r = -1;
		}
	}

	var i,d,x,y,dx,dy,path=[];
	for (var a=startAngle; a<endAngle; a+=angleinc) {
		i = path.length;
		d = distance*Math.sin(a*Math.PI/180);
		path[i] = Math.round(centerX + d*Math.cos(normAngle));
		path[i+1] = Math.round(centerY - d*Math.sin(normAngle));
	}
	if (startSpeed==endSpeed) {
		for (var a=endAngle; a<endAngle+90; a+=angleinc) {
			i = path.length;
			d = distance*Math.sin(a*Math.PI/180);
			path[i] = Math.round(centerX2 - r*d*Math.cos(normAngle));
			path[i+1] = Math.round(centerY2 + r*d*Math.sin(normAngle));
		}
	}
	
	var l = path.length;
	if (path[l-2] != x2 || path[l-1]!=y2) {
		path[l] = x2;
		path[l+1] = y2;
	}
	
	return path;
};

DynLayer.prototype.glideStop = function () {
	if (this._thread) this._thread.stop();
};
DynLayer.prototype.glideTo = function(x2,y2,angleinc,ms,startSpeed,endSpeed) {
	if (this.x!=x2 || this.y!=y2) {
		if (!this._thread) this._thread = new Thread(this);
		if (ms) this._thread.interval = ms;
		this._thread.play(GlideAnimation(this.x,this.y,x2,y2,angleinc,startSpeed,endSpeed) );
	}
};