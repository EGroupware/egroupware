/*
	DynAPI Distribution
	HoverAnimation Class

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

	requires: dynapi.functions.Math, dynapi.fx.Thread
*/

function HoverAnimation(dlyr) {
	this.Thread = Thread;
	this.Thread(dlyr);

	this.offsetX = 0;
	this.offsetY = 0;
	this.playing = false;
	this.amplitude = 100;
	this.angle = 0;
	this.setAngleIncrement(10);
};
var p = dynapi.setPrototype('HoverAnimation','Thread');
p.setAmplitude = function (amp) {
	this.amplitude = amp;
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
		this.offsetX = 0;
		this.offsetY = this.amplitude*Math.sin(this.angle);
		this.baseX = this.dlyr.x;
		this.baseY = this.dlyr.y+this.offsetY;
		this.dlyr.invokeEvent("hoverstart");
	}
	this.start();
};
p.stopAnimation = function () {
	this.playing = false;
	this.stop();
	if (this.dlyr!=null) this.dlyr.invokeEvent("hoverstop");
};
p.run = function () {
	if (!this.playing || this.dlyr==null) return;
	this.angle += this.angleinc;
	this.offsetX = 0;
	this.offsetY = this.amplitude*Math.sin(this.angle);
	if (this.dlyr!=null) {
		this.dlyr.invokeEvent("hoverrun");
		this.dlyr.setLocation(this.baseX+this.offsetX,this.baseY+this.offsetY);
	}
};
p.reset = function () {
	this.angle = this.offsetX = this.offsetY = 0;
};

p.generatePath = function(centerX,centerY) {
	// to do
};
