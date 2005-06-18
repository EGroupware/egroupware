/*
	DynAPI Distribution
	Bezier Class
	
	Bezier Algorithm Reference: http://astronomy.swin.edu.au/~pbourke/curves/bezier/

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

	requires: dynapi.fx.Thread
*/


function Bezier(cp, n) {
	var l = cp.length;
	var p = [];
	for (var i=0; i<n; i++) p = p.concat(Bezier._plot(cp,i/n));
	return p.concat([cp[l-2],cp[l-1]]);
}

Bezier._plot = function (cp, mu) {
	var n = (cp.length/2)-1;
	var k,kn,nn,nkn;
	var blend;
	var b = [0,0];

	var muk = 1;
	var munk = Math.pow(1-mu, n);

	for (k=0;k<=n;k++) {
		nn = n;
		kn = k;
		nkn = n - k;
		blend = muk * munk;
		muk *= mu;
		munk /= (1-mu);
		while (nn >= 1) {
			blend *= nn;
			nn--;
			if (kn > 1) {
				blend /= kn;
				kn--;
			}
			if (nkn > 1) {
				blend /= nkn;
				nkn--;
			}
		}
		
		b[0] += cp[k*2] * blend;
		b[1] += cp[k*2+1] * blend;
	}
	b[0] = Math.round(b[0]);
	b[1] = Math.round(b[1]);
	return b;
}

/*function Bezier3(cp,mu) {
	var x1=cp[0],y1=cp[1],x2=cp[2],y2=cp[3],x3=cp[4],y3=cp[5];
	var mu2 = mu * mu;
	var mum1 = 1 - mu;
	var mum12 = mum1 * mum1;
	var x = Math.round(x1 * mum12 + 2 * x2 * mum1 * mu + x3 * mu2);
	var y = Math.round(y1 * mum12 + 2 * y2 * mum1 * mu + y3 * mu2);
	return [x,y];
}

function Bezier4(cp,mu) {
	var x1=cp[0],y1=cp[1],x2=cp[2],y2=cp[3],x3=cp[4],y3=cp[5],x4=cp[6],y4=cp[7];
 	var mum1 = 1 - mu;
	var mum13 = mum1 * mum1 * mum1;
	var mu3 = mu * mu * mu;
	var x = Math.round(mum13*x1 + 3*mu*mum1*mum1*x2 + 3*mu*mu*mum1*x3 + mu3*x4);
	var y = Math.round(mum13*y1 + 3*mu*mum1*mum1*y2 + 3*mu*mu*mum1*y3 + mu3*y4);
	return [x,y];
}*/
