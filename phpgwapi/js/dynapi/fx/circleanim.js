/*
	DynAPI Distribution
	CircleAnimation Class

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

	requires: dynapi.functions.Math,dynapi.fx.Thread
*/

function CircleAnimation(dlyr) {
	this.Thread = Thread;
	this.Thread(dlyr);

	this.offsetX = 0;
	this.offsetY = 0;
	this.playing = false;
	this.radius = 100;
	this.angle = 0;
	this.setAngleIncrement(10);
};
var p = dynapi.setPrototype('CircleAnimation','Thread');
p.setRadius = function (r) {
	this.hradius = this.vradius = r;
};
p.setHRadius = function (r) {
	this.hradius = r;
};
p.setVRadius = function (r) {
	this.vradius = r;
};
p.setAngle = function (a) {
	this.angle = dynapi.functions.degreeToRadian(a);
};
p.setAngleIncrement = function (inc) {
	this.angleinc = dynapi.functions.degreeToRadian(inc);
};
p.playAnimation = function () {
	this.playing = true;
	if (this.dlyr!=null) {
		this.offsetX = this.hradius*Math.cos(this.angle);
		this.offsetY = -this.vradius*Math.sin(this.angle);
		this.baseX = this.dlyr.x-this.offsetX;
		this.baseY = this.dlyr.y+this.offsetY;
		this.dlyr.invokeEvent("circlestart");
	}
	this.start();
};
p.stopAnimation = function () {
	this.playing = false;
	this.stop();
	if (this.dlyr!=null) this.dlyr.invokeEvent("circlestop");
};
p.run = function () {
	if (!this.playing || this.dlyr==null) return;	
	this.angle += this.angleinc;
	this.offsetX = this.hradius*Math.cos(this.angle);
	this.offsetY = -this.vradius*Math.sin(this.angle);

	if (this.dlyr!=null) {
		this.dlyr.invokeEvent("circlerun");
		this.dlyr.setLocation(this.baseX+this.offsetX,this.baseY+this.offsetY);
	}
};
p.reset = function () {
	this.angle = this.offsetX = this.offsetY = 0;
};
p.generatePath = function(centerX,centerY) {
	if (centerX==null) centerX = this.dlyr!=null? this.dlyr.x : 0;
	if (centerY==null) centerY = this.dlyr!=null? this.dlyr.y : 0;
	var path = [];
	var i = 0;
/*	for (var a=this.angle;a<=this.angle+Math.PI*2;a+=this.angleinc) {
		path[i] = Math.round(centerX + this.hradius*Math.cos(a));
		path[i+1] = Math.round(centerY - this.vradius*Math.sin(a));
		i+=2;
	}*/

	if (this.angleinc>0)
		for (var a=this.angle;a<=this.angle+Math.PI*2;a+=this.angleinc) {
			path[i] = Math.round(centerX + this.hradius*Math.cos(a));
			path[i+1] = Math.round(centerY - this.vradius*Math.sin(a));
			i+=2;
		}
	else
		for (var a=this.angle;a>=this.angle-Math.PI*2;a+=this.angleinc) {
			path[i] = Math.round(centerX + this.hradius*Math.cos(a));
			path[i+1] = Math.round(centerY - this.vradius*Math.sin(a));
			i+=2;
		}
	return path;
};
