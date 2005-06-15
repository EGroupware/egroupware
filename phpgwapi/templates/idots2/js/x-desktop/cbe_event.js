/* cbe_event.js $Revision$
 * CBE v4.19, Cross-Browser DHTML API from Cross-Browser.com
 * Copyright (c) 2002 Michael Foster (mike@cross-browser.com)
 * Distributed under the terms of the GNU LGPL from gnu.org
*/
function cbeELReg(eventType, eventListener, eventCapture, listenerObject) { // event listener registration object constructor
  this.type = eventType; this.listener = eventListener; this.capture = eventCapture; this.obj = listenerObject;
}
function CrossBrowserEvent(e) { // Object constructor
  // from DOM2 Interface Event
  this.type = "";
  this.target = null;
  this.currentTarget = null;
  this.eventPhase = 0;
  this.bubbles = true;
  this.cancelable = true;
  this.timeStamp = 0;
  this.AT_TARGET = 1; this.BUBBLING_PHASE = 2; this.CAPTURING_PHASE = 3; // eventPhase masks
  // from DOM2 Interface MouseEvent : UIEvent
  this.screenX = 0;
  this.screenY = 0;
  this.clientX = 0;
  this.clientY = 0;
  this.ctrlKey = false;
  this.shiftKey = false;
  this.altKey = false;
  this.metaKey = false;
  this.button = 3; // 3 == undefined
  this.relatedTarget = null;
  this.LEFT = 0; this.MIDDLE = 1; this.RIGHT = 2; // button masks
  // from IE4 Object Model
  this.keyCode = 0;
  this.offsetX = 0;
  this.offsetY = 0;
  // from NN4 Object Model
  this.pageX = 0;
  this.pageY = 0;
  // CBE
  this.stopPropagationFlag = false;
  this.preventDefaultFlag = false;
  this.cbeTarget = window.cbe;
  this.cbeCurrentTarget = window.cbe;

  if (!e) return;
  
  if (e.type) { this.type = e.type; }
  if (e.target) { this.target = e.target; }
  else if (e.srcElement) { this.target = e.srcElement; }
  if (e.currentTarget) { this.currentTarget = e.currentTarget; }
  else if (e.toElement) { this.currentTarget = e.toElement; }
  if (e.eventPhase) { this.eventPhase = e.eventPhase; }
  if (e.bubbles) { this.bubbles = e.bubbles; }
  if (e.cancelable) { this.cancelable = e.cancelable; }
  if (e.timeStamp) { this.timeStamp = e.timeStamp; }

  if (e.screenX) { this.screenX = e.screenX; }
  if (e.screenY) { this.screenY = e.screenY; }
  if (is.opera5or6) { this.clientX = e.clientX - document.cbe.scrollLeft(); }
  else if (e.clientX) { this.clientX = e.clientX; }
  else if (e.pageX) { this.clientX = e.pageX - document.cbe.scrollLeft(); }
  if (is.opera5or6) { this.clientY = e.clientY - document.cbe.scrollLeft(); }
  else if (e.clientY) { this.clientY = e.clientY; }
  else if (e.pageY) { this.clientY = e.pageY - document.cbe.scrollLeft(); }
  if (is.opera5or6) { this.ctrlKey = e.type=='mousemove' ? e.shiftKey : e.ctrlKey; }
  else if (_def(e.ctrlKey)) { this.ctrlKey = e.ctrlKey; }
  else if (_def(e.modifiers) && window.Event) { this.ctrlKey = (e.modifiers & window.Event.CONTROL_MASK) != 0; }
  if (is.opera5or6) { this.shiftKey = e.type=='mousemove' ? e.ctrlKey : e.shiftKey; }
  else if (_def(e.shiftKey)) { this.shiftKey = e.shiftKey; }
  else if (_def(e.modifiers) && window.Event) { this.shiftKey = (e.modifiers & Event.SHIFT_MASK) != 0; }
  if (e.altKey) { this.altKey = e.altKey; }
  else if (_def(e.modifiers) && window.Event) { this.altKey = (e.modifiers & Event.ALT_MASK) != 0; }
  if (e.metaKey) { this.metaKey = e.metaKey; }

  // button (?)
  if (is.ie) { 
    if (this.type.indexOf('mouse') != -1) {
      if (e.button == 1) this.button = this.LEFT;
      else if (e.button == 4) this.button = this.MIDDLE;
      else if (e.button == 2) this.button = this.RIGHT;
    }
    else if (this.type == 'click') this.button = this.LEFT;
    else this.button = 4; // non-mouse event
  }
  else if (_def(e.button)) { // standard
    if (this.type.indexOf('mouse') != -1) { this.button = e.button; if (this.button < 0 || this.button > 2) {this.button = 3;} }
    else if (this.type == 'click') this.button = this.LEFT;
    else this.button = 4; // non-mouse event
  }  
  else if (_def(e.which)) {
    if (document.layers) { // nn4
      if (this.type.indexOf('mouse') != -1) { this.button = e.which - 1; if (this.button < 0 || this.button > 2) {this.button = 3;} }
      else if (this.type == 'click') this.button = this.LEFT;
      else this.button = 4; // non-mouse event
    }
    else { // opera5or6
      if ((e.type == 'click' && e.which == 0) || ((e.type == 'mousedown' || e.type == 'mouseup') && e.which == 1)) {this.button = this.LEFT;}
    }
  }

  if (e.relatedTarget) { this.relatedTarget = e.relatedTarget; }
  else if (e.fromElement) { this.relatedTarget = e.fromElement; } // ? may need to be toElement in some cases ?
  if (_def(e.which)) { this.keyCode = e.which; }
  else if (_def(e.keyCode)) { this.keyCode = e.keyCode; }
  var calcOfs = false;
  if (_def(e.layerX,e.layerY)) { this.offsetX = e.layerX; this.offsetY = e.layerY; }
  else calcOfs = true; // calculate it below
  if (is.opera5or6) { this.pageX = e.clientX; this.pageY = e.clientY; }
  else if (_def(e.pageX,e.pageY)) { this.pageX = e.pageX; this.pageY = e.pageY; }
  else {
    this.pageX = this.clientX + document.cbe.scrollLeft();
    this.pageY = this.clientY + document.cbe.scrollTop();
  }
  
  // Find the CBE event target
  if (document.layers) {
    this.cbeTarget = cbeGetNodeFromPoint(this.pageX, this.pageY);
    // NN4 note: mouseout works only if mouseover and mouseout are both added to the same object
    if (this.type == 'mouseover') cbeMOT = this.cbeTarget;
    else if (this.type == 'mouseout') this.cbeTarget = cbeMOT || document.cbe;
  }
  else { var target = this.target; while (!target.cbe) {target = cbeGetParentElement(target);} this.cbeTarget = target.cbe; }
  this.cbeCurrentTarget = this.cbeTarget;
  if (calcOfs) { this.offsetX = this.pageX - this.cbeTarget.pageX(); this.offsetY = this.pageY - this.cbeTarget.pageY(); }
}

CrossBrowserElement.prototype.addEventListener = function(eventType, eventListener, useCapture, listenerObject) {
  if (!useCapture) useCapture = false;
  eventType = eventType.toLowerCase();
  if (
    (eventType.indexOf('mouse') != -1)
    || eventType == 'click'
    || (eventType.indexOf('key') != -1)
/*    || (eventType.indexOf('resize') != -1 && !is.nav4 && !is.opera)
    || (eventType.indexOf('scroll') != -1 && !is.nav && !is.opera) */
  ) {
    var add=true;
    for (var i=0; i < this.listeners.length; ++i) { if (eventType == this.listeners[i].type) {add=false; break;} }
    if (add) {
      cbeNativeAddEventListener(this.ele, eventType, cbePropagateEvent, false);
    }
    this.listeners[this.listeners.length] = new cbeELReg(eventType, eventListener, useCapture, listenerObject);
    return;
  }
  switch(eventType) {
    case 'slidestart': this.onslidestart = eventListener; return;
    case 'slide': this.onslide = eventListener; return;
    case 'slideend': this.onslideend = eventListener; return;
    case 'dragstart': this.ondragstart = eventListener; return;
    case 'drag':
      this.ondragCapture = useCapture;
      this.ondrag = eventListener;
      this.addEventListener('mousedown', cbeDragStartEvent, useCapture);
      return;
    case 'dragend': this.ondragend = eventListener; return;
    case 'dragresize': if (window.cbeUtilJsLoaded) cbeAddDragResizeListener(this); return;
    case 'scroll':
      if (is.nav || is.opera) {
        window.cbeOldScrollTop = cbePageYOffset();
        window.cbeOnScrollListener = eventListener;
        cbeScrollEvent();
        return;
      }
      break;
    case 'resize':
      if (is.nav4 || is.opera) {
        window.cbeOldWidth = cbeInnerWidth();
        window.cbeOldHeight = cbeInnerHeight();
        window.cbeOnResizeListener = eventListener;
        cbeResizeEvent();
        return;
      }
      break;
  } // end switch
  cbeNativeAddEventListener(this.ele, eventType, eventListener, useCapture);
}
function cbeNativeAddEventListener(ele, eventType, eventListener, useCapture) {
  if (!useCapture) useCapture = false;
  eventType = eventType.toLowerCase();
  var eh = "ele.on" + eventType + "=eventListener";
  if (ele.addEventListener) {
    ele.addEventListener(eventType, eventListener, useCapture);
  }
  else if (ele.captureEvents) {
//    if (useCapture || (eventType.indexOf('mousemove')!=-1))  // ???
      ele.captureEvents(eval("Event." + eventType.toUpperCase()));
    eval(eh);
  }
  else { eval(eh); }
}
function cbeNativeRemoveEventListener(ele, eventType, eventListener, useCapture) {
  if (!useCapture) useCapture = false;
  eventType = eventType.toLowerCase();
  var eh = "ele.on" + eventType + "=null";
  if (ele.removeEventListener) {
    ele.removeEventListener(eventType, eventListener, useCapture);
  }
  else if (ele.releaseEvents) {
//    if (useCapture || (eventType.indexOf('mousemove')!=-1))  // ???
      ele.releaseEvents(eval("Event." + eventType.toUpperCase()));
    eval(eh);
  }
  else { eval(eh); }
}
CrossBrowserElement.prototype.removeEventListener = function(eventType, eventListener, useCapture) {
  eventType = eventType.toLowerCase();
  if (!useCapture) useCapture = false;
  if ((eventType.indexOf('mouse') != -1) || eventType == 'click' || (eventType.indexOf('key') != -1)) {
    var i;
    for (i = 0; i < this.listeners.length; ++i) {
      if (this.listeners[i].type == eventType && this.listeners[i].listener == eventListener && this.listeners[i].capture == useCapture) {
        if (this.listeners.splice) this.listeners.splice(i, 1);
        else this.listeners[i].type = "*";
        break;
      }
    }
    var remove=true;
    for (i = 0; i < this.listeners.length; ++i) { if (eventType == this.listeners[i].type) { remove = false; break; } }
    if (remove) cbeNativeRemoveEventListener(this.ele, eventType, cbePropagateEvent, false);
    return;
  }
  switch(eventType) {
    case 'slidestart': this.onslidestart = null; return;
    case 'slide': this.onslide = null; return;
    case 'slideend': this.onslideend = null; return;
    case 'dragstart': this.ondragstart = null; return;
    case 'drag':
      this.removeEventListener('mousedown', cbeDragStartEvent, this.ondragCapture);
      this.ondrag = null;
      return;
    case 'dragend': this.ondragend = null; return;
    case 'dragresize': if (window.cbeUtilJsLoaded) cbeRemoveDragResizeListener(this); return;
    case 'scroll':
      if (is.nav || is.opera) {
        window.cbeOnScrollListener = null;
        return;
      }
      break;
    case 'resize':
      if (is.nav4 || is.opera) {
        window.cbeOnResizeListener = null;
        return;
      }
      break;
  } // end switch
  cbeNativeRemoveEventListener(this.ele, eventType, eventListener, useCapture);
}
CrossBrowserEvent.prototype.stopPropagation = function() { this.stopPropagationFlag = true; }
CrossBrowserEvent.prototype.preventDefault = function() { this.preventDefaultFlag = true; }
CrossBrowserElement.prototype.dispatchEvent= function(e) {
  var dispatch;
  e.cbeCurrentTarget = this;
  for (var i=0; i < this.listeners.length; ++i) {
    dispatch = false;
    if (e.type == this.listeners[i].type) {
      if (e.eventPhase == e.CAPTURING_PHASE) {
        if (this.listeners[i].capture) dispatch = true;
      }
      else if (!this.listeners[i].capture) dispatch = true;
    }
    if (dispatch) {
      if (this.listeners[i].obj) cbeEval(this.listeners[i].obj, this.listeners[i].listener, e);
      else cbeEval(this.listeners[i].listener, e);
    }
  }
}
function cbePropagateEvent(evt) {
  var i=0, e=null, a=new Array();
  if (evt) e = new CrossBrowserEvent(evt);
  else if (window.event) e = new CrossBrowserEvent(window.event);
  else return;
  // Create an array of EventTargets, following the parent chain up (does not include cbeTarget)
  var node = e.cbeTarget.parentNode;
  while(node) {
    a[i++] = node;
    node = node.parentNode;
  }
  // The capturing phase
  e.eventPhase = e.CAPTURING_PHASE;
  for (i = a.length-1; i>=0; --i) {
    a[i].dispatchEvent(e);
    if (e.stopPropagationFlag) break;
  }
  // The at-target phase
  if (!e.stopPropagationFlag) {
    e.eventPhase = e.AT_TARGET;
    e.cbeTarget.dispatchEvent(e);
    // The bubbling phase
    if (!e.stopPropagationFlag && e.bubbles) {
      e.eventPhase = e.BUBBLING_PHASE;
      for (i = 0; i < a.length; ++i) {
        a[i].dispatchEvent(e);
        if (e.stopPropagationFlag) break;
      }
    }
  }
  //  Don't allow native bubbling
  if (is.ie) window.event.cancelBubble = true;
  else if (is.gecko) evt.stopPropagation();
  // Allow listener to cancel default action
  if (e.cancelable && e.preventDefaultFlag) {
    if (is.gecko || is.opera) evt.preventDefault();
    return false;
  }
  else return true;
}
function cbeGetNodeFromPoint(x, y) {
  var hn /* highNode */, hz=0 /* highZ */, cn /* currentNode */, cz /* currentZ */;
  hn = document.cbe;
  while (hn.firstChild && hz >= 0) {
    hz = -1;
    cn = hn.firstChild;
    while (cn) {
      if (cn.contains(x, y)) {
        cz = cn.zIndex();
        if (cz >= hz) {
          hn = cn;
          hz = cz;
        }
      }
      cn = cn.nextSibling;
    }
  }
  return hn;
}
function cbeScrollEvent() {
  if (!window.cbeOnScrollListener) { return; }
  if (cbePageYOffset() != window.cbeOldScrollTop) {
    cbeEval(window.cbeOnScrollListener);
    window.cbeOldScrollTop = cbePageYOffset();
  }
  setTimeout("cbeScrollEvent()", 250);
}
function cbeResizeEvent() {
  if (!window.cbeOnResizeListener) { return; }
  var dw = window.cbeOldWidth - cbeInnerWidth();
  var dh = window.cbeOldHeight - cbeInnerHeight();
  if (dw != 0 || dh != 0) {
    if (window.cbeOnResizeListener) cbeEval(window.cbeOnResizeListener, dw, dh);
    window.cbeOldWidth = cbeInnerWidth();
    window.cbeOldHeight = cbeInnerHeight();
  }
  setTimeout("cbeResizeEvent()", 250);
}
function cbeDefaultResizeListener() {
  if (is.opera) location.replace(location.href);
  else history.go(0);
}
var cbeDragObj, cbeDragTarget, cbeDragPhase;
function cbeDragStartEvent(e) {
  if (is.opera) { var tn = e.target.tagName.toLowerCase(); if (tn == 'a') return; }
  else if (is.nav4) { if (e.target.href) return; }
  cbeDragObj = e.cbeCurrentTarget;
  cbeDragTarget = e.cbeTarget;
  if (cbeDragTarget.id == cbeDragObj.id) cbeDragPhase = e.AT_TARGET;
  else if (cbeDragObj.ondragCapture) cbeDragPhase = e.CAPTURING_PHASE;
  else cbeDragPhase = e.BUBBLING_PHASE;
  if (cbeDragObj) {
    if (cbeDragObj.ondragstart) { e.type = 'dragstart'; cbeEval(cbeDragObj.ondragstart, e); e.type = 'mousedown'; }
    cbeDragObj.x = e.pageX;
    cbeDragObj.y = e.pageY;
    document.cbe.addEventListener('mousemove', cbeDragEvent, cbeDragObj.ondragCapture);
    document.cbe.addEventListener('mouseup', cbeDragEndEvent, false);
  }
  e.stopPropagation();
  e.preventDefault();
}
function cbeDragEndEvent(e) {
  document.cbe.removeEventListener('mousemove', cbeDragEvent, cbeDragObj.ondragCapture);
  document.cbe.removeEventListener('mouseup', cbeDragEndEvent, false);
  if (cbeDragObj.ondragend) {
    e.type = 'dragend';
    e.cbeCurrentTarget = cbeDragObj;
    e.cbeTarget = cbeDragTarget;
    cbeEval(cbeDragObj.ondragend, e);
    e.type = 'mouseup';
  }
  //e.stopPropagation();
  e.preventDefault();
}
function cbeDragEvent(e) {
  if (cbeDragObj) {
    e.dx = e.pageX - cbeDragObj.x;
    e.dy = e.pageY - cbeDragObj.y;
    cbeDragObj.x = e.pageX;
    cbeDragObj.y = e.pageY;
    e.type = 'drag';
    e.cbeTarget = cbeDragTarget;
    e.cbeCurrentTarget = cbeDragObj;
    e.eventPhase = cbeDragPhase;
    if (cbeDragObj.ondrag) cbeEval(cbeDragObj.ondrag, e);
    else cbeDragObj.moveBy(e.dx,e.dy);
    e.type = 'mousemove';
  }
  //e.stopPropagation();
  e.preventDefault();
}
var cbeEventPhase = new Array('', 'AT_TARGET', 'BUBBLING_PHASE', 'CAPTURING_PHASE');
var cbeButton = new Array('LEFT', 'MIDDLE', 'RIGHT', 'undefined', 'non-mouse event');
CrossBrowserElement.prototype.ondragstart = null;
CrossBrowserElement.prototype.ondrag = null;
CrossBrowserElement.prototype.ondragend = null;
var cbeEventJsLoaded = true;
// End cbe_event.js
