/*
	DynAPI Distribution
	dynapi.functions.Color extension	
*/

var f = dynapi.functions;
f.Color = Color = {}; // used by dynapi.library

// Color Functions ---------------------------

f.DecToHex = function(val){
	lo=val%16;
	val-=lo;
	lo+=48;
	if (lo>57) lo+=7;
	hi=val/16;
	hi+=48;
	if (hi>57) hi+=7;
	return String.fromCharCode(hi,lo);
};
f.getColor = function(r,g,b) { 
	return '#'+dynapi.functions.DecToHex(r)+dynapi.functions.DecToHex(g)+dynapi.functions.DecToHex(b);
};
f.getRandomColor = function() {
	var s = '';
	for (var i=0;i<3;i++) s += dynapi.functions.DecToHex(Math.floor(255*Math.random()));
	return s;
};
f.createRedPal = function(pal) {
	var r=g=b=0;
	for (var i=0; i<256; i++){
		pal[i]=dynapi.functions.getColor(r,g,b);
		r+=8;
		if (r>255) { r=255; g+=6; b+=2; }
		if (g>255) { g=255; b+=2; }
		if (b>255) { b=255; }
	}
};
f.createGrayPal = function(pal) {
	var r=0;
	for (var i=0; i<256; i++){
		pal[i]=dynapi.functions.getColor(r,r,r);
		r+=4;
		if (r>255) { r=255; }
	}
};
f.createBluePal = function(pal){
	var r=g=b=0;
	for (var i=0; i<256; i++){
		pal[i]=dynapi.functions.getColor(r,g,b);
		b+=6;
		if (b>255) { b=255; g+=2; }
		if (g>255) { g=255; r+=2; }
	}
};
f.createGreenPal = function(pal) {
	var r=g=b=0;
	for (var i=0; i<256; i++){
		pal[i]=dynapi.functions.getColor(r,g,b);
		g+=6;
		if (g>255) { g=255; b+=2; }
		if (b>255) { b=255; r+=2; }
	}
};
f.fadeColor = function(from, to, percent){
	if(!from || !to) return;
	if(percent<0) return from;
	else if(percent>100) to;
	
	if(from.substring(0,1)!='#') from='#'+from;
	if(to.substring(0,1)!='#') to='#'+to;

	from = {
		red:parseInt(from.substring(1,3),16),
		green:parseInt(from.substring(3,5),16),
		blue:parseInt(from.substring(5,7),16)
	}

	to = {
		red:parseInt(to.substring(1,3),16),
		green:parseInt(to.substring(3,5),16),
		blue:parseInt(to.substring(5,7),16)
	}

	var r=from.red+Math.round((percent/100)*(to.red-from.red));
	var g=from.green+Math.round((percent/100)*(to.green-from.green));
	var b=from.blue+Math.round((percent/100)*(to.blue-from.blue));

	r = (r < 16 ? '0' : '') + r.toString(16);
	g = (g < 16 ? '0' : '') + g.toString(16);
	b = (b < 16 ? '0' : '') + b.toString(16);

	return '#' + r + g + b;
};
