/*
	DynAPI Distribution
	MotionX Class by Raymond Irving (http://dyntools.shorturl.com)

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.

	requires: Dynlayer
*/

MotionX = {}; // used by dynapi.library

DynLayer.prototype.makeSolid=function(){
	this.isHard=true;
	this._collideX=this.x;
	this._collideY=this.x;
	this.collideEvent ={
		onlocationchange:function(e) {
			var me=e.getSource();
			var dirX='',dirY='';
			// get direction
			if (me._collideX!=me.x) {
				if (me._collideX<me.x){dirX="E"}else{dirX="W"};
			}
			if (me._collideY!=me.y){
				if (me._collideY<me.y){dirY="S"}else{dirY="N"};
			}
			// get angle direction
			me._setCollideAngleDirection(me._collideX,me._collideY,me.x,me.y);
			me._collideX=me.x;
			me._collideY=me.y;
			me._collideDirection=dirY+dirX;
			me._checkForCollision();
		}
	}
	this.addEventListener(this.collideEvent);
};
DynLayer.prototype._setCollideAngleDirection = function(x1,y1,x2,y2) {
	var distx = (x2-x1),disty = (y1-y2),angle;
	//if (distx==0 && disty==0) return 0;
	var rad=Math.abs(Math.atan2(disty,distx))
	if (disty>=0) {
		if (distx>=0) angle = 90-(rad*180/Math.PI);
		else angle = 270+(180-(rad*180/Math.PI));
	}else{
		angle = 90+(rad*180/Math.PI);
	}
	this._collideAngle=Math.ceil(angle);
};
DynLayer.prototype._checkForCollision=function(){
	if (!this.parent.children.length>0) return false;
	var ch,chX,sX,sY,colX1,colX2,colY1,colY2,n1,n2;
	this.collideObject==null;
	for (var i in this.parent.children) {
		ch=this.parent.children[i];
		if (ch!=this && ch.isHard==true) {
			chX=ch.x;
			chY=ch.y;
			sX=this.x;
			sY=this.y;
			colX1=(sX>=chX && sX<=chX+ch.w);
			colX2=(chX>=sX && chX<=sX+this.w);
			colY1=(sY>=chY && sY<=chY+ch.h);
			colY2=(chY>=sY && chY<=sY+this.h);
			if ((colX1 || colX2) && (colY1 || colY2)) {
				if (this._collideDirection=='NE') {
					n1=((chY+ch.h)-this.y);n2=((sX+this.w)-chX);
					if (n1<n2) {face="S"}else{face="W"}
				}else if (this._collideDirection=='NW') {
					n1=((chY+ch.h)-this.y);n2=((chX+ch.w)-sX);
					if (n1<n2) {face="S"}else{face="E"}
				}else if (this._collideDirection=='SE') {
					n1=((sY+this.h)-ch.y);n2=((sX+this.w)-chX);
					if (n1<n2) {face="N"}else{face="W"}
				}else if (this._collideDirection=='SW') {
					n1=((sY+this.h)-ch.y);n2=((chX+ch.w)-sX);
					if (n1<n2) {face="N"}else{face="E"}
				}else if (this._collideDirection=='E') {
					face="W";
				}else if (this._collideDirection=='W') {
					face="E";
				}else if (this._collideDirection=='N') {
					face="S";
				}else if (this._collideDirection=='S') {
					face="N";
				}

				ch._impactSide=face;
				if (face=="W"){this._impactSide="E"}
				if (face=="E"){this._impactSide="W"}
				if (face=="N"){this._impactSide="S"}
				if (face=="S"){this._impactSide="N"}

				this._collideObject=ch;
				this.invokeEvent("collide");
				ch._collideObject=this;
				ch.invokeEvent("collide");
			}
		}
	}
	return false;
};
DynLayer.prototype.getImpactSide=function(){
	return this._impactSide;
}
DynLayer.prototype.getObstacle=function(){
	return this._collideObject;
}
DynLayer.prototype.getDirection=function(){
	return this._collideDirection;
}
DynLayer.prototype.getDirectionAngle=function(){
	return this._collideAngle;
}
