/*
	DynAPI Distribution
	dynapi.functions.Math extension	
	
	Math and Path functions
*/

var f = dynapi.functions;
f.Math = {}; // used by dynapi.library

// Math Functions --------------------------

f.radianToDegree = function(radian) {
	return radian*180/Math.PI;
};
f.degreeToRadian = function(degree) {
	return degree*Math.PI/180;
};
f.sintable = function(lsin) {
	for (var i=0; i<361; i+=1) lsin[i]=Math.sin((i/180)*Math.PI);
};
f.costable = function(lcos) {
	for (var i=0; i<361; i+=1) lcos[i]=Math.cos((i/180)*Math.PI);
};
f.getRandomNumber = function(n) {
	var t=new Date();
	n=(n)? n: parseInt(Math.random()*10000);
	return Math.round((Math.abs(Math.sin(t.getTime()))*(Math.random()*1000)))%n+1;
};
f.getGUID = function() { // Globally Unique ID
	var n1,n2,n3,n4;
	var l,r,m,c=Math.random()+'';
	c=c.substr(c.indexOf('.')+1);
	m=(c.length-1)/2;
	l=c.substr(0,m); r=c.substr(m);
	n1=this.getRandomNumber(1000);
	n2=this.getRandomNumber(255);n3=this.getRandomNumber(255);n4=this.getRandomNumber(255);
	n2 = (n2 < 16 ? '0' : '') + n2.toString(16);
	n3 = (n3 < 16 ? '0' : '') + n3.toString(16);
	n4 = (n4 < 16 ? '0' : '') + n4.toString(16);	
	return (n1+n2+'-'+l+'-'+n3+n4+'-'+r).toUpperCase();
};

// Path Functions ------------------------

// Combines separate [x1,x2],[y1,y2] arrays into a path array [x1,y1,x2,y2]
f.interlacePaths = function (x,y) {
	var l = Math.max(x.length,y.length);
	var a = new Array(l*2);
	for (var i=0; i<l; i++) {
		a[i*2] = x[i];
		a[i*2+1] = y[i];
	}
	return a;
};

// Returns correct angle in radians between 2 points
f.getNormalizedAngle = function (x1,y1,x2,y2) {
	var distx = Math.abs(x1-x2);
	var disty = Math.abs(y1-y2);
	if (distx==0 && disty==0) angle = 0;
	else if (distx==0) angle = Math.PI/2;
	else angle = Math.atan(disty/distx);
	if (x1<x2) {
		if (y1<y2) angle = Math.PI*2-angle;
	}
	else {
		if (y1<y2) angle = Math.PI+angle;
		else angle = Math.PI-angle;
	}
	return angle;
};

// Generates a path between 2 points in N steps
f.generateLinePath = function(x1,y1,x2,y2,N) {
	if (N==0) return [];
	var dx = (x2 == x1)? 0 : (x2 - x1)/N;
	var dy = (y2 == y1)? 0 : (y2 - y1)/N;
	var path = new Array();
	for (var i=0;i<=N;i++) {
		path[i*2] = Math.round(x1 + i*dx);
		path[i*2+1] = Math.round(y1 + i*dy);
	}
	return path;
};
