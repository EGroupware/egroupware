/* cbe_slide.js $Revision$
 * CBE v4.19, Cross-Browser DHTML API from Cross-Browser.com
 * Copyright (c) 2002 Michael Foster (mike@cross-browser.com)
 * Distributed under the terms of the GNU LGPL from gnu.org
*/
CrossBrowserElement.prototype.slideBy = function(dX, dY, totalTime, endListener) {
  var targetX, targetY;
  dX = parseInt(dX); dY = parseInt(dY); targetX = this.left() + dX; targetY = this.top() + dY;
  this.slideTo(targetX, targetY, totalTime, endListener)
}
CrossBrowserElement.prototype.slideTo = function(x, y, totalTime, endListener) {
  if (this.onslidestart) cbeEval(this.onslidestart, this);
  this.xTarget = parseInt(x); this.yTarget = parseInt(y);
  this.slideTime = parseInt(totalTime);
  if (isNaN(this.xTarget)) {
    var outside=false;
    if (isNaN(this.yTarget)) { y = 0; outside = true; }
    this.cardinalPosition(x, y, outside); this.xTarget = this.x; this.yTarget = this.y;
  }
  if (endListener && window.cbeEventJsLoaded) { this.autoRemoveListener = true; this.addEventListener('slideend', endListener); }
  this.stop = false;
  this.yA = this.yTarget - this.top(); this.xA = this.xTarget - this.left(); // A = distance
  this.B = Math.PI / (2 * this.slideTime); // B = period
  this.yD = this.top(); this.xD = this.left(); // D = initial position
  if (this.slideRate == cbeSlideRateLinear) { this.B = 1/this.slideTime; }
  else if (this.slideRate == cbeSlideRateCosine) {
    this.yA = -this.yA; this.xA = -this.xA; this.yD = this.yTarget; this.xD = this.xTarget;
  }
  var d = new Date(); this.C = d.getTime();
  if (!this.moving) this.slide();
}
CrossBrowserElement.prototype.slide = function() {
  var now, s, t, newY, newX;
  now = new Date();
  t = now.getTime() - this.C;
  if (this.stop) { this.moving = false; }
  else if (t < this.slideTime) {
    setTimeout("window.cbeAll["+this.index+"].slide()", this.timeout);
    if (this.slideRate == cbeSlideRateLinear) s = this.B * t;
    else if (this.slideRate == cbeSlideRateSine) s = Math.sin(this.B * t);
    else s = Math.cos(this.B * t); // this.slideRate == cbeSlideRateCosine
    newX = Math.round(this.xA * s + this.xD);
    newY = Math.round(this.yA * s + this.yD);
    if (this.onslide) cbeEval(this.onslide, this, newX, newY, t);
    this.moveTo(newX, newY);
    this.moving = true;
  }  
  else {
    this.moveTo(this.xTarget, this.yTarget);
    this.moving = false;
    if (this.onslideend) {
      var tmp = this.onslideend;
      if (this.autoRemoveListener && window.cbeEventJsLoaded) {
        this.autoRemoveListener = false;
        this.removeEventListener('slideend');
      }
      cbeEval(tmp, this);
    }
  }  
}
CrossBrowserElement.prototype.ellipse = function(xRadius, yRadius, radiusInc, totalTime, startAngle, stopAngle, endListener) {
  if (this.onslidestart) cbeEval(this.onslidestart, this);
  this.stop = false;
  this.xA = parseInt(xRadius);
  this.yA = parseInt(yRadius);
  this.radiusInc = parseInt(radiusInc);
  this.slideTime = parseInt(totalTime);
  startAngle = cbeRadians(parseFloat(startAngle));
  stopAngle = cbeRadians(parseFloat(stopAngle));
  if (endListener && window.cbeEventJsLoaded) {
    this.autoRemoveListener = true;
    this.addEventListener('slideend', endListener);
  }
  var startTime = (startAngle * this.slideTime) / (stopAngle - startAngle);
  this.stopTime = this.slideTime + startTime;
  this.B = (stopAngle - startAngle) / this.slideTime;
  this.xD = this.left() - Math.round(this.xA * Math.cos(this.B * startTime)); // center point
  this.yD = this.top() - Math.round(this.yA * Math.sin(this.B * startTime)); 
  this.xTarget = Math.round(this.xA * Math.cos(this.B * this.stopTime) + this.xD); // end point
  this.yTarget = Math.round(this.yA * Math.sin(this.B * this.stopTime) + this.yD); 
  var d = new Date();
  this.C = d.getTime() - startTime;
  if (!this.moving) this.ellipse1();
}
CrossBrowserElement.prototype.ellipse1 = function() {
  var now, t, newY, newX;
  now = new Date();
  t = now.getTime() - this.C;
  if (this.stop) { this.moving = false; }
  else if (t < this.stopTime) {
    setTimeout("window.cbeAll["+this.index+"].ellipse1()", this.timeout);
    if (this.radiusInc) {
      this.xA += this.radiusInc;
      this.yA += this.radiusInc;
    }
    newX = Math.round(this.xA * Math.cos(this.B * t) + this.xD);
    newY = Math.round(this.yA * Math.sin(this.B * t) + this.yD);
    if (this.onslide) cbeEval(this.onslide, this, newX, newY, t);
    this.moveTo(newX, newY);
    this.moving = true;
  }  
  else {
    if (this.radiusInc) {
      this.xTarget = Math.round(this.xA * Math.cos(this.B * this.slideTime) + this.xD);
      this.yTarget = Math.round(this.yA * Math.sin(this.B * this.slideTime) + this.yD); 
    }
    this.moveTo(this.xTarget, this.yTarget);
    this.moving = false;
    if (this.onslideend) {
      var tmp = this.onslideend;
      if (this.autoRemoveListener && window.cbeEventJsLoaded) {
        this.autoRemoveListener = false;
        this.removeEventListener('slideend');
      }
      cbeEval(tmp, this);
    }
  }  
}
CrossBrowserElement.prototype.stopSlide = function() { this.stop = true; }
CrossBrowserElement.prototype.startSequence = function(uIndex) {
  if (!this.moving) {
    if (!uIndex) this.seqIndex = 0;
    else this.seqIndex = uIndex;
    this.addEventListener('slideEnd', cbeSlideSequence);
    cbeSlideSequence(this);
  }
}
CrossBrowserElement.prototype.stopSequence = function() {
  this.stop=true;
  this.removeEventListener('slideEnd', cbeSlideSequence);
}
function cbeSlideSequence(cbe) {
  var
    pw = cbe.parentNode.width(),
    ph = cbe.parentNode.height(),
    w = cbe.width(),
    h = cbe.height();
  if (cbe.seqIndex >= cbe.sequence.length) cbe.seqIndex = 0;
  eval('cbe.'+cbe.sequence[cbe.seqIndex++]);
}
var cbeSlideRateLinear=0, cbeSlideRateSine=1, cbeSlideRateCosine=2;
CrossBrowserElement.prototype.slideRate = cbeSlideRateSine;
CrossBrowserElement.prototype.seqIndex = 0;
CrossBrowserElement.prototype.radiusInc = 0;
CrossBrowserElement.prototype.t = 0;
CrossBrowserElement.prototype.xTarget = 0;     
CrossBrowserElement.prototype.yTarget = 0;     
CrossBrowserElement.prototype.slideTime = 1000;
CrossBrowserElement.prototype.xA = 0;
CrossBrowserElement.prototype.yA = 0;
CrossBrowserElement.prototype.xD = 0;
CrossBrowserElement.prototype.yD = 0;
CrossBrowserElement.prototype.B = 0;
CrossBrowserElement.prototype.C = 0;
CrossBrowserElement.prototype.moving = false;
CrossBrowserElement.prototype.stop = true;
CrossBrowserElement.prototype.timeout = 35;
CrossBrowserElement.prototype.autoRemoveListener = false;
CrossBrowserElement.prototype.onslidestart = null;
CrossBrowserElement.prototype.onslide = null;
CrossBrowserElement.prototype.onslideend = null;
var cbeSlideJsLoaded = true;
// End cbe_slide.js
