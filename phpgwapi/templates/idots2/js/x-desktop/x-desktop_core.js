/*==============================================================
  x-desktop_core.js - release 1 $
  x-Desktop CrossBrowserDesktop Library from www.x-desktop.org
  Copyright (c) 2003-2004 Tobias Schulze (webmaster@x-desktop.org) & Lars Gehrmann
  Distributed under the terms of the GNU GPL from gnu.org
  see attached license.html or visit http://www.x-desktop.org
================================================================*/
/* ============ */
/* Enumerations */
/* ============ */
function xDesktopVariables() {
 var xDTwList = new Array();
 var xDTwName = new Array();
 var xDTTaskbars = new Array();
 var xDTSkins = new Array(new Array("DEFAULT"));
 var xDTmain = "";  var maxwin = 0; var xDTsyswin = 15;
 var xDTdragZ = 0;
 var xDTnoevent = false;
 this.lastWindow = function _lastWindow(mwin) {if (mwin) maxwin = mwin; return maxwin }
 this.setWindowHandle = _setWindowHandle;  this.wHandle = _wHandle;  this.wName = _wName;  this.setStartup = _setStartup;  this.Startup = _Startup;
 this.wLength = function _wLength() { return xDTwList.length }
 this.window = function _window(windowName) {if (xDTwName[windowName] != null) return xDTwList[xDTwName[windowName]]["cbe"]; else return null }
 this.windowalert = function _windowalert(windowName) {if (xDTwName[windowName] != null) return xDTwList[xDTwName[windowName]]["cbe"]; else {alert("Unknown window: " + windowName + " !"); return xDTwList[xDTsyswin-1]["cbe"] } }
 this.windowalert2 = function _windowalert(windowName) {if (xDTwName[windowName] != null) return xDTwList[xDTwName[windowName]]["innercbe"]; else {alert("Unknown innercbe: " + windowName + " !");return xDTwList[xDTsyswin-1]["innercbe"] } }
 this.property = _property;  this.newWindow = _newWindow;  this.getWindowIndex = _getWindowIndex;  this.getAllWindows = _getAllWindows;
 this.getAllUserWindows = _getAllUserWindows;  this.getAllWindowProperties = _getAllWindowProperties;  this.deleteWindowProperties = _deleteWindowProperties;
 this.xDTTaskbar = function _taskbars(wSkin) { xDTTaskbars[wSkin](); }
 this.xDTdrag = function _xDTdrag() {++xDTdragZ; return xDTdragZ }
 this.addSkin = _addSkin;  this.skin = _skin;  this.noevent = _noevent;
 this.marginTop = function _marginTop(sk) {return xDTSkins[sk]["mTop"] }
 this.marginBottom = function _marginBottom(sk) {return xDTSkins[sk]["mBottom"] }
 this.syswin = xDTsyswin;
 function _noevent(ev) {if (ev == true || ev == false) xDTnoevent = ev; return xDTnoevent }
 function _deleteWindowProperties(idx) {
  if (idx < xDTsyswin) return;
  var tmp = xDTwList[idx]["cbe"];         // save cbe window handle;
  xDTwName[xDTwList[idx]["wName"]] = null; // delete windowName
  _setWindowHandle(idx);                 // initialize
  xDTwList[idx]["cbe"] = tmp;            // restore previous cbe window handle
 }
 function _setWindowHandle(idx) {
  xDTwList[idx] = new Array("cbe","innercbe","wName","wTitle","wWidthOrg","wWidth","wHeightOrg","wHeight","wPos","wX","wY","wSkin","wIcon","wUrl","wScroll","wHtml","zIndex","wIndex","wStat","fClose","wVisible","fResize","fMove");              // create this window properties
  var w = xDTwList[idx].join(',').split(",");
  for (var i in w) xDTwList[idx][w[i]] = "";
  if (idx == 0) { xDTwList[idx]["wName"] = "dDesktop"; xDTwName["dDesktop"] = idx }
  else if (idx == 1) { xDTwList[idx]["wName"] = "dTaskbar"; xDTwName["dTaskbar"] = idx }
  else if (idx == 2) { xDTwList[idx]["wName"] = "dMove"; xDTwName["dMove"] = idx }    // Move window (instead of real window....)
  else if (idx == 3) { xDTwList[idx]["wName"] = "dMessage"; xDTwName["dMessage"] = idx }    // System Message window
  else if (idx == 4) { xDTwList[idx]["wName"] = "dSound"; xDTwName["dSound"] = idx }    // System Sound window
  else if (idx == 5) { xDTwList[idx]["wName"] = "dUser"; xDTwName["dUser"] = idx }    // User Messages like alert etc...
  else if (idx == 9) { xDTwList[idx]["wName"] = "dDummy"; xDTwName["dDummy"] = idx }    // Dummy window
  else if (idx == 10) { xDTwList[idx]["wName"] = "dCustom1"; xDTwName["dCustom1"] = idx }    // dCustom1
  else if (idx == 11) { xDTwList[idx]["wName"] = "dCustom2"; xDTwName["dCustom2"] = idx }    // dCustom2
  else if (idx == 12) { xDTwList[idx]["wName"] = "dCustom3"; xDTwName["dCustom3"] = idx }    // dCustom3
  else if (idx == 13) { xDTwList[idx]["wName"] = "dCustom4"; xDTwName["dCustom4"] = idx }    // dCustom4
  else if (idx == 14) { xDTwList[idx]["wName"] = "dCustom5"; xDTwName["dCustom5"] = idx }    // dCustom5
  else xDTwList[idx]["wName"] = "";
  xDTwList[idx]["wIndex"] = idx;
 }
 function wName(idx) {if (idx < xDTwList.length) return xDTwList[idx]["wName"]; else return -1 }
 function _wHandle(idx,param,val) {xDTwList[idx][param] = val; return val }
 function _wName(idx) {if (idx < xDTwList.length) return xDTwList[idx]["wName"]; else return -1 }
 function _setStartup(func) { xDTmain = func }
 function _Startup() { xDTmain() }
 function _getWindowIndex(windowName) { return xDTwName[windowName] }
 function _property(windowName,wProp,wVal) {
  if (xDTwName[windowName] == null) return xDTwName[windowName];
  if (wProp) {
   if (wVal == 0 || (wVal && wVal != '') ) xDTwList[xDTwName[windowName]][wProp] = wVal;
   return xDTwList[xDTwName[windowName]][wProp];
  }
  return "";
 }
 function _newWindow(windowName) {
  //if (xDTwName[windowName]) {alert("Window " + windowName + " does exists already !"); return 0 }
  if (xDTwName[windowName]) return "_WTHERE_"; //OK, window will be reused
  for (var i=xDTsyswin;i<=maxwin;i++) {
   if (xDTwList[i]["wName"] == "") {
    if (windowName == "w") windowName += i;
    xDTwList[i]["wName"] = windowName;
    xDTwName[windowName] = i;
    return windowName;
   }
  }
  alert("Cannot create window " + windowName + ". Out of space. Increase maxwin !");
  return 0;
 }
 function _getAllWindows(sk) {var str = ""; for(var i in xDTwName) {if (sk) {if (xDTwName[i] < sk) continue };if (str != "") str += "/"; str += i; } return str; }
 function _getAllUserWindows() {for(i=xDTsyswin;i<=maxwin;i++) {if (xDTwList[i]["wName"] != "") return xDTwList[i]["wName"] } return 0 }
 function _getAllWindowProperties(windowName) {
  if (xDTwName[windowName] != 0 && (xDTwName[windowName] == "" || xDTwName[windowName] == null) ) return "";
  return xDTwList[xDTwName[windowName]].join(",");
 }
 function _addSkin(wskin,mTop,mBottom) {
  var _func = eval( "skin_" + wskin);
  if ( typeof _func == "function") xDTSkins[wskin] = _func;
  else alert("skin_" + wskin + " function missing or invalid / not loaded");

  var skin_taskbar = eval("taskbar_" + wskin);
  if ( typeof skin_taskbar != "function") {
          skin_taskbar = eval( "taskbar_DEFAULT");
  }
  xDTTaskbars[wskin] = skin_taskbar;

  (mTop && mTop >= 0) ? xDTSkins[wskin]["mTop"] = mTop : xDTSkins[wskin]["mTop"] = 0;
  (mBottom && mBottom >= 0) ? xDTSkins[wskin]["mBottom"] = mBottom : xDTSkins[wskin]["mBottom"] = 0;
 }
 function _skin(wskin,wName) {
  if (typeof xDTSkins[wskin] == "function") return xDTSkins[wskin](wName);
  if (typeof eval("skin_" + wskin) == "function") {_addSkin(wskin,0,0); return xDTSkins[wskin](wName) }
  alert("Skin " + wskin + " is not available ! Using skin_DEFAULT() instead ....");
  return xDTSkins["DEFAULT"](wName);
 }
 for (i=0;i<xDTSkins.length;i++) {
  var _func = eval( "skin_" + xDTSkins[i]);
  if ( typeof _func == "function") xDTSkins[xDTSkins[i]] = _func;
 }
}
/*========================*/
/* internal Object        */
/*========================*/
var xDTwin = new xDesktopVariables();
/* ========================*/
/* load / unload functions */
/* ========================*/
function windowOnload() {
 for (var i=0;document.getElementById('xDTwi' + i);i++) {
   xDTwin.setWindowHandle(i);
   with ( xDTwin.wHandle(i,"cbe",document.getElementById('xDTwi' + i).cbe ) ) {
     hide();
     color('#000000');
     background('#ffffff');
     resizeTo(1,1);
     moveTo(0,0);
     if (i>2) {
      addEventListener('dragStart',dStartListener,false);
      addEventListener('drag',dListener,false);
      addEventListener('dragEnd',dEndListener,false);
    }
   }
 }
}
function windowOnunload() {
 //xDTwin = null;
}
/* ========================*/
/* The xDesktop Object     */
/* ========================*/
function xDesktop(respath,sdt,maxwin,_fmain) {                 // resourcepath, startupdesktop,maximumwindows,startup function
 var p_zIndex = 10;                                        // z-index of window
 var p_respath = './xDT/';                                // (root) path for resources
 var p_desktop = 'DEFAULT';                                // default Desktop style
 var p_tbBgColor = 'transparent';                        // default Taskbar Background Color
 var p_tbColor = '#000000';                                // default Taskbar Foreground Color
 var p_tbBoColor = '#000000';                           // default Taskbar Border Color
 var p_taskbar = false; var p_lastpopup = "";                // default number of window elements
 var p_hidedesktop = false;                                // hide Desktop when moving windows
 var p_language = "";                                        // default Language (none = english)
 var p_version_major = "1.5 Development Release";       // Major Release
 var p_version_minor = "0";                                // Minor Release
 var p_message = "";                                         // Message for alert/confirm etc.. and push button status
 var p_cOK = ""; var p_cCancel = "";                        // confirm OK/Cancel push button assigned functions
 var p_caldate = "";                                        // calender date target field
 var p_lastdt = "";                                        // last desktop skin
 var p_sysmessage = "";                                        // current systemmessage
 var p_scw = 0;                                                // screen width (0 <=1024,1 <=1280,2 >1280)
 var p_useautopopup = 0;                                // use autopopup
 var p_autopop = 1;                                        // do auto popup
 var p_dateformat = 0;                                        // dateformat 0=european , 1 = us
 var p_data = new Array();                                // data array for global use
 var p_syswin = 15;                                        // internal sys windows same as xDTsyswin
 SetScreen();
 if (! maxwin || maxwin < 20 || maxwin > 500) maxwin = 50;
 xDTwin.lastWindow(maxwin);                                // default number of window elements
 if (typeof(respath) == 'string' && respath.length > 1) p_respath = respath;
 if (typeof(sdt) == 'string' && sdt.length > 1) p_desktop = sdt;
 var p_lError = new Array(0,""); var p_windowList = new Array(); var p_Debug = 1; var p_maxwin = maxwin;
 this.lastError = _lastError; this.window = new _window; this.desktop = new _desktop; this.resPath = _respath; this.taskbar = _taskbar; this.sysMessage = _sysMessage;
 this.maxWindow = _maxWindow; this.addWindow = _addWindow; this.deleteWindow = this.closeWindow = _closeWindow; this.moveWindow = _moveWindow; this.resizeWindow = _resizeWindow;
 this.refreshWindow = _refreshWindow; this.positionWindow = _positionWindow; this.positionAllWindows = _positionAllWindows; this.popupWindow = _popupWindow;
 this.innerWindows = _innerWindows; this.maximizeWindow = _maximizeWindow; this.minimizeWindow = _minimizeWindow; this.minimizeAllWindows = _minimizeAllWindows; this.deleteAllWindows = this.closeAllWindows = _closeAllWindows;
 this.restoreAllWindows = _restoreAllWindows; this.arrangeAllWindows = _arrangeAllWindows; this.frameName = _frameName; this.show = _show; this.hide = _hide;
 this.cbe = _cbe; this.prop = _property; this.url = _url; this.html = _html; this.taskbarColor = _taskbarColor; this.taskbarStatus = _taskbarStatus; this.version = _version;
 this.addSkin = _addSkin; this.setSkin = _setSkin; this.dSkin =_dSkin; this.playSound = _playSound; this.hideDesktop = _hideDesktop; this.checkUpdate = _checkUpdate;
 this.alert = _alert; this.confirm = _confirm; this.quit = _quit; this.language = _language; this.docs = _docs; this.home = _home;  this.preloadImages = _preloadImages;
 this.calendar = _calendar; this.autoPopupWindow = _autoPopupWindow; this.autoPopup = _autoPopup; this.dateFormat = _dateFormat;
 this.wName = _wName;
 //this.data = _data;
 /*==================================*/
 /* -- Auto init stuff ------------- */
 /*==================================*/
 for (var i=0;i<=maxwin;i++) {
   document.write('<div id="xDTwi' + i + '" class="xDT_clsCBE"></div>');
 }
 /*==================================*/
 /* -- Object Methods for public use */
 /*==================================*/
 function _dateFormat(df) { if (typeof(df) == 'undefined') return p_dateformat; else if (typeof(df) == 'string') df = parseInt(df); df ? p_dateformat = 1 :  p_dateformat = 0; return p_dateformat }
 function _minimizeWindow(wName) { if (wName) { _property(wName,"wStat","min"); _hide(wName); _taskbar() } }
 function _language(lang) {if (lang && lang.length) p_language = lang; return p_language }
 function _version() {var str = ""; p_version_major.match(/^([0-9.]+)\s+(.+)$/) ? str = RegExp.$1 + "." + p_version_minor + " (" + RegExp.$2 + ")" : str = p_version_major + " " + p_version_minor; return str }
 function _hideDesktop(stat) { stat ? p_hidedesktop = true : p_hidedesktop = false; return p_hidedesktop }
 function _show(wName) {if (! xDTwin.window(wName)) return; _property(wName,"wVisible",true); _cbe(wName).show(); return true}
 function _hide(wName) {if (! xDTwin.window(wName)) return; _property(wName,"wVisible",false); _cbe(wName).hide(); return true}
 function _popupWindow(wName) {if (_property(wName,"wIndex") < p_syswin) return; if (! xDTwin.window(wName) || wName == p_lastpopup) return wName; _cbe(wName).zIndex(xDTwin.xDTdrag()); _property(wName,"zIndex",_cbe(wName).zIndex()); _show(wName); _property(wName,"wStat","OK"); p_lastpopup = wName; return wName  }
 function _autoPopupWindow(wName) {if (p_useautopopup && p_autopop) _popupWindow(wName) }
 function _autoPopup(onoff) {if (_autoPopup.arguments.length == 0) return p_useautopopup; (onoff) ? p_useautopopup = 1 : p_useautopopup = 0; return p_useautopopup }
 function _frameName(wName) {if (! xDTwin.window(wName)) return ""; if (_property(wName,"wUrl") != "") return "xDTiF_" + wName; return "" }
 function  _taskbarStatus() {return p_taskbar }
 function  _taskbarColor(bg,c,b) {if (bg) p_tbBgColor = bg; if (c) p_tbColor = c; if (b) p_tbBoColor = b; _taskbar() }
 function _closeWindow(wName,wC) {if (xDTwin.window(wName) && (DeleteWindowFunction(wName,wC) || wC == "KILL" )) {_hide(wName); if (typeof(_property(wName,"innercbe")) == "object") _property(wName,"innercbe").innerHtml(""); xDTwin.deleteWindowProperties(xDTwin.getWindowIndex(wName)); _taskbar() } }
 function _addWindow(wName,wTitle,wWidth,wHeight,wPos,wSkin) {
  p_zIndex++;
  if (wName && !wName.match(/^[a-zA-Z][a-zA-Z0-9]+$/) ) {alert('Invalid window name: ' + wName); return 0 }
  if (!wName) wName = "w";
  var wNsave = wName;
  if (! wName.match(/^(dMessage|dUser|dSound|dCustom\d+)$/) ) wName = xDTwin.newWindow(wName);
  if (wName == "_WTHERE_") {_property(wNsave,"wStat","OK"); _taskbar(wNsave); return wNsave }
  if (!wName) return 0;
  _cbe(wName).background('transparent');
  if (!wSkin) wSkin = _dSkin();
  _property(wName,"wSkin",wSkin);                                                        // default window style
  SetScreen();
  wWidth = ChooseSize(wWidth);
  wHeight = ChooseSize(wHeight);
  (wWidth > 0 && wWidth <= 10000) ? wWidth = wWidth : wWidth = 300;
  (wHeight > 0 && wHeight <= 10000) ? wHeight = wHeight : wHeight = 200;
  _property(wName,"wWidth",wWidth);                                                        // default window width
  _property(wName,"wHeight",wHeight);                                                        // default window height
  _property(wName,"wWidthOrg",_property(wName,"wWidth"));                                // save Org width Value
  _property(wName,"wHeightOrg",_property(wName,"wHeight"));                                // save Org height Values
  _cbe(wName).resizeTo(wWidth,wHeight);
  if (!wPos) wPos = "center";
  SetWindowPos(wName,wPos);                                                                // default window pos
  if (!wTitle) wTitle = " ";
  _property(wName,"wTitle",wTitle);                                                        // default Title
  p_zIndex = xDTwin.xDTdrag();
  _property(wName,"zIndex",p_zIndex);
  _setSkin(wName,wSkin);
  _cbe(wName).zIndex(p_zIndex);
        _taskbar();
  return wName;
 }
 function _resizeWindow(wName,wWidth,wHeight,wPos) {
  if (! xDTwin.window(wName)) return;
  SetScreen();
  wWidth = ChooseSize(wWidth);
  wHeight = ChooseSize(wHeight);
  if (wWidth > 0 && wWidth <= 10000) _property(wName,"wWidth",wWidth);
  if (wHeight > 0 && wHeight <= 10000) _property(wName,"wHeight",wHeight);
  _cbe(wName).resizeTo(_property(wName,"wWidth"),_property(wName,"wHeight"));
  if (!wPos) wPos = _property(wName,"wPos");
  SetWindowPos(wName,wPos);
 }
 function _maximizeWindow(wName) {
  if (_property(wName,"wIndex") < p_syswin) return;
  var tb = 0; if (_taskbarStatus()) tb = _property("dTaskbar","wHeight") + 1;
  if (_property(wName,"wStat") == "MAX") {
   _cbe(wName).moveTo(_property(wName,"wX"),_property(wName,"wY"));
   _cbe(wName).resizeTo(_property(wName,"wWidth"),_property(wName,"wHeight"));
   _property(wName,"wStat","OK");
   return;
  }
  _property(wName,"wStat","MAX");
  _cbe(wName).moveTo(0,xDTwin.marginTop(_dSkin()));
  _cbe(wName).resizeTo(document.cbe.width() - 1,document.cbe.height() - tb - xDTwin.marginBottom(_dSkin()) - xDTwin.marginTop(_dSkin()) - 1);
  _cbe(wName).zIndex(xDTwin.xDTdrag());
  _property(wName,"zIndex",_cbe(wName).zIndex());
 }
 function _positionWindow(wName) {
  var wSkin = _dSkin();
  var wX = _property(wName,"wX");        // left
  var wY = _property(wName,"wY");        // top
  var mT = xDTwin.marginTop(wSkin);
  var tb = 0; if (_taskbarStatus()) tb = _property("dTaskbar","wHeight") + 1;
  var fB = document.cbe.height() - (_cbe(wName).height() + xDTwin.marginBottom(wSkin) + tb);
  if (_cbe(wName).left() < 0) _cbe(wName).moveTo(0,_cbe(wName).top());
  if ( (_cbe(wName).left() + _cbe(wName).width()) > document.cbe.width()) _cbe(wName).moveTo(document.cbe.width() - _cbe(wName).width(),_cbe(wName).top());
  if ( wY <=  mT) _cbe(wName).moveTo(wX,mT); if ( wY > fB )  _cbe(wName).moveTo(wX,fB);
  if (_cbe(wName).left() < 0) _cbe(wName).moveTo(0,_cbe(wName).top());
  if ( (_cbe(wName).left() + _cbe(wName).width()) > document.cbe.width()) _cbe(wName).moveTo(document.cbe.width() - _cbe(wName).width(),_cbe(wName).top());
  _property(wName,"wX",_cbe(wName).left()); _property(wName,"wY",_cbe(wName).top());
 }
 function _positionAllWindows() {
  if (xDTwin.getAllWindows(p_syswin) != 0) {
   var uw = xDTwin.getAllWindows(p_syswin).split('/');
   for (var w in uw) _positionWindow(uw[w]);
   _positionWindow("dMessage");
   _positionWindow("dUser");
  }
 }
 function _innerWindows(wName,stat) {
  if (xDTwin.getAllWindows(p_syswin) != 0) {
   var uw = xDTwin.getAllWindows(p_syswin).split('/');
   if (uw) uw.push("dMessage");
   if (stat == "hide" && p_hidedesktop) _hide("dDesktop");
   else if (stat == "show" && p_hidedesktop) _show("dDesktop");
   for (var w in uw) {
    if (uw[w] == wName) continue;
    if (_property(uw[w],"innercbe")) {
     if (stat == "hide") _property(uw[w],"innercbe").hide();
     else if (stat == "show") _property(uw[w],"innercbe").show();
    }
   }
  }
 }
 function _setSkin(wName,wSkin) {
  _cbe(wName).innerHtml( xDTwin.skin(wSkin,wName) );
  var ele = "";
  if (ele = document.getElementById(wName + 'iTD')) {
   var cbe = new CrossBrowserElement();
   cbeBindElement(cbe,ele);
   document.cbe.appendNode(cbe);
   _property(wName,"innercbe",cbe);
  }
 }
 function _url(wName,wUrl,wScroll) {
  if (! xDTwin.property(wName,"wName") ) {alert("Unknown window name: " + wName + " !"); return }
  if (wUrl) xDTwin.property(wName,"wUrl",wUrl);
  wUrl = xDTwin.property(wName,"wUrl",wUrl); // maybe some existing url
  if (wScroll == 'no' || wScroll == 0 || wScroll == false) wScroll = 'no'; else wScroll = 'auto';
  xDTwin.property(wName,"wScroll",wScroll); /*allowtransparency="true" style="background-color: transparent"*/
  _property(wName,"innercbe").innerHtml('<iframe onmouseover="xDT.autoPopupWindow(' + "'" + wName + "')" + '" name="xDTiF_' + wName + '" src="' + wUrl + '" width="100%" height="100%"  marginwidth="0" marginheight="0" frameborder="0" scrolling="' + wScroll + '">');
  if (document.all) {e = document.getElementById('xDTiF_'+wName); e.src = wUrl; e.width = "100%"; e.height = "100%" }
  // next line is to fix a bug in mozilla 1.3
  if (! _property(wName,"wSkin").match(/^xDT/) && _property(wName,"innercbe").height() >= _property(wName,"cbe").height()) _resizeWindow(wName,_property(wName,"wWidth"),200);
 }
 function _html(wName,wHtml,wUrl,wScroll) {
  if (! xDTwin.property(wName,"wName") ) {alert("Unknown window name: " + wName + " !"); return }
  if (wHtml) _property(wName,"wHtml",wHtml);
  wHtml = _property(wName,"wHtml",wHtml); // maybe some existing html
  if (typeof(wUrl) == 'undefined' || ! wUrl.length) wUrl = p_respath + 'files/htmlwindow.html';
  if (wScroll == 'no' || wScroll == 0 || wScroll == false) wScroll = 'no'; else wScroll = 'auto';
  xDTwin.property(wName,"wScroll",wScroll); /*allowtransparency="true" style="background-color: transparent"*/
  _property(wName,"innercbe").innerHtml('<iframe onmouseover="xDT.autoPopupWindow(' + "'" + wName + "')" + '" name="xDTiF_' + wName + '" src="' + wUrl + '" width="100%" height="100%" marginwidth="0" marginheight="0" frameborder="0" scrolling="' + wScroll + '">');
  if (document.all) {e = document.getElementById('xDTiF_'+wName); e.src = wUrl; e.width = "100%"; e.height = "100%" }
  // next line is to fix a bug in mozilla 1.3
  //if (! _property(wName,"wSkin").match(/^xDT/) && _property(wName,"innercbe").height() >= _property(wName,"cbe").height()) _resizeWindow(wName,_property(wName,"wWidth"),200);
  if (_property(wName,"innercbe").height() >= _property(wName,"cbe").height()) _resizeWindow(wName,_property(wName,"wWidth"),parseInt(_property(wName,"cbe").height()) + 60);
 }
 function _refreshWindow(wName,wSkin) {
  var sc = ""; // save content
  eval(_frameName(wName) + ".location.reload()");
  /*
  if ( _property(wName,"innercbe") ) sc = _property(wName,"innercbe").innerHtml(); else sc = _cbe(wName).innerHtml();
  if (! wSkin) wSkin = _property(wName,"wSkin");  _setSkin(wName,wSkin);
  if ( _property(wName,"innercbe") )  _property(wName,"innercbe").innerHtml(sc);  else _cbe(wName).innerHtml(sc);
  _property(wName,"wSkin",wSkin);
  */
 }
 function _sysMessage(msg,hide) {
  if (typeof(msg) == 'undefined' && typeof(hide) == 'undefined') return p_sysmessage;
  _addWindow('dMessage','System Message',300,200,'ne','');
  if (msg) p_sysmessage = msg;
  if (!hide) _cbe('dMessage').zIndex(xDTwin.xDTdrag());
  if (!hide) _show('dMessage');
  _url('dMessage',p_respath + 'files/sysmsg.html');
 }
 function _checkUpdate(wPos) {  if (typeof(wPos) == 'undefined') wPos = "0,0";  _addWindow('xDTonlUC','Online Update Check',400,130,wPos);  _url('xDTonlUC',p_respath + 'files/update.html');  _show('xDTonlUC'); }
 function _alert(msg,cOK) {
  if (typeof(msg) == 'undefined') return p_message;
  if ( (typeof(cOK) == 'string' && cOK.length) || typeof(cOK) == 'function') p_cOK = cOK; else p_cOK = "";
  msg += "";
  (msg.match(/^(\s+)?$/)) ? p_message = '&lt;NO VALUE&gt;' : p_message = msg;
  _cbe('dDummy').background('transparent');
  var opac = "";
  (document.all) ? opac = 'style="background-color: #000000; filter:alpha(opacity=35); -moz-opacity:0.35"' : opac = 'style="background-image: url(' + p_respath + 'images/opac.gif)" ';
  _cbe('dDummy').innerHtml('<table ' + opac + ' cellpadding="0" cellspacing="0" border="0" width="100%" height="100%"><tr><td width="100%" height="100%"></td></tr></table>');
  _cbe('dDummy').resizeTo(document.cbe.width(),document.cbe.height());
  _cbe('dDummy').zIndex(9999998);
  _cbe('dDummy').show();
  var w = 150;
  (parseInt(400 / p_message.length) < 10) ? w = 400 : w += p_message.length * 10;
  _addWindow('dUser','',w,140,'center','');
  _url('dUser',p_respath + 'files/alert.html');
  _cbe('dUser').zIndex(9999999);
  _show('dUser');
  p_autopop = 0;
 }
 function _confirm(msg,cOK,cCancel) {
  if (typeof(msg) == 'undefined') return p_message;
  if ( (typeof(cOK) == 'string' && cOK.length) || typeof(cOK) == 'function') p_cOK = cOK; else p_cOK = "";
  if ( (typeof(cCancel) == 'string' && cCancel.length ) || typeof(cCancel) == 'function') p_cCancel = cCancel; else p_cCancel = "";
  (msg.match(/^(\s+)?$/)) ? p_message = '&lt;NO VALUE&gt;' : p_message = msg;
  _cbe('dDummy').background('transparent');
  var opac = "";
  (document.all) ? opac = 'style="background-color: #000000; filter:alpha(opacity=35); -moz-opacity:0.35"' : opac = 'style="background-image: url(' + p_respath + 'images/opac.gif)" ';
  _cbe('dDummy').innerHtml('<table ' + opac + ' cellpadding="0" cellspacing="0" border="0" width="100%" height="100%"><tr><td width="100%" height="100%"></td></tr></table>');
  _cbe('dDummy').resizeTo(document.cbe.width(),document.cbe.height());
  _cbe('dDummy').zIndex(9999998);
  _cbe('dDummy').show();
  var w = 200;
  (parseInt(400 / p_message.length) < 40) ? w = 400 : w += p_message.length * 10;
  _addWindow('dUser','',w,140,'center','');
  _url('dUser',p_respath + 'files/confirm.html');
  _cbe('dUser').zIndex(9999999);
  _show('dUser');
  p_autopop = 0;
 }
 function _quit(c,cval) {
  p_autopop = 1;
  if (!c) { _hide('dDummy'); _hide('dUser'); return }
  if (c.match(/^c(OK|CANCEL)/)) {
   _hide('dDummy');
   _hide('dUser');
   if (c == 'cOK' && (typeof p_cOK) == 'string' && p_cOK.length) eval(p_cOK);
   else if (c == 'cOK' && (typeof p_cOK) == 'function') p_cOK();
   else if (c == 'cCANCEL' && (typeof p_cCancel) == 'string' && p_cCancel.length) eval(p_cCancel);
   else if (c == 'cCANCEL' && (typeof p_cCancel) == 'function') p_cCancel();
  }
  else if (c == "cDate" && typeof(cval) == "string") p_caldate.value = cval;
 }
 function _docs(anker,apath) {
  if (! anker || !anker.length) anker = 'docs.html'; else anker = 'docs.html#' + anker;
  (apath) ? apath += anker  : apath = p_respath + 'docs/' + anker;
  var win = _addWindow('xDTlocDOC','Development documentation',700,480,'ne','');
  _url(win,apath);
  _show(win);
 }
 function _home() { var win = _addWindow('xDTlocHOME','Home of x-Desktop',800,600,'ne',''); _url(win,'http://www.x-desktop.org'); _show(win) }
 function _playSound(sfile,vol) {if (! sfile.match(/\.mp3$/i) ) return; if (vol) sfile += "," + vol; _addWindow('dSound',sfile,200,200,'center',''); _property('dSound',"wTitle",sfile); _url('dSound',p_respath + 'files/sound.html'); }
 function _cbe(wName) {if (wName) return xDTwin.windowalert(wName) }
 function _property(wName,wProp,wVal) {if (wName) return xDTwin.property(wName,wProp,wVal) }
 function _maxWindow() {return xDTwin.lastWindow()}
 function _wName(idx) {return xDTwin.wName(idx) }
 function _respath(rpath) {if (rpath) p_respath = rpath; return p_respath}
 function _moveWindow(wTitle) {_cbe("dMove").innerHtml('<table cellpadding="0" cellspacing="0" border="0" height="100%" width="100%"><tr><td class="xDT_moveWindow" align="center" valign="middle">' + wTitle + '</td></tr></table>') }
 function _addSkin(wskin,mTop,mBottom) {xDTwin.addSkin(wskin,mTop,mBottom); if (wskin) { _taskbarColor("transparent","#000000","#000000") } }
 function _dSkin() {return p_desktop }
 function _lastError() {
  var errMessages = new Array(["No Errors"],
                              ["Error "],
                              ["Missing parameter"],
                              ["Nothing found"]
                             );
  return "[" + p_lError[0] + "] " + errMessages[p_lError[0]][0] + " " + p_lError[1];
 }

 function _window(wName) {
    this.find  = _find;
    this.skin = _skin;
    this.pos   = _pos;
    this.title = _title;
    this.icon  = _icon;
    this.name = _name;
    this.onClose = _onClose;
    this.onResize = _onResize;
    this.onMove = _onMove;
    this.properties = _properties;
    function _find(wName)  {if (wName != "undefined" && xDTwin.window(wName) != null ) return 1; else return 0 }
    function _skin(wName,wSkin) {if (! _find(wName) || ! _property(wName,"wSkin") ) return 0;if (wSkin && wSkin.length) _property(wName,"wSkin",wSkin);return _property(wName,"wSkin")}
    function _pos(wName,wPos) {if (! _find(wName) || ! _property(wName,"wPos") ) return 0;if (wPos && wPos.length) _property(wName,"wPos",wPos);return _property(wName,"wPos")}
    function _title(wName,wTitle) {if (! _find(wName) ) return 0;if (typeof(wTitle) != 'undefined') _property(wName,"wTitle",wTitle); if (document.getElementById('xDTiT_'+wName)) document.getElementById('xDTiT_'+wName).innerHTML = wTitle ;return _property(wName,"wTitle")}
    function _icon(wName,wIcon) {if (! _find(wName) ) return 0;if ( wIcon.match(/^[IXMC][10]$/) ) _property(wName,"wIcon",wIcon);return _property(wName,"wIcon")}
    function _name(wName) {if (wName.indexOf('xDTiF_') == 0) wName = wName.replace(/^xDTiF_/,"");if (! _find(wName) ) return 0;return wName}
    function _onClose(wName,fcl) {if (wName && (typeof(fcl) == 'string' || typeof(fcl) == 'function')) _property(wName,"fClose",fcl) }
    function _onResize(wName,fcl) {if (wName && (typeof(fcl) == 'string' || typeof(fcl) == 'function')) _property(wName,"fResize",fcl) }
    function _onMove(wName,fcl) {if (wName && (typeof(fcl) == 'string' || typeof(fcl) == 'function')) _property(wName,"fMove",fcl) }
    function _properties(wName) {
      var str = "";
      var w = xDTwin.getAllWindows().split('/');
      if (w[0].length == 0) return "";
      if (wName && _find(wName)) w = new Array(wName);
      for (var s in w) {
              var p = xDTwin.getAllWindowProperties(w[s]).split(",");
              if (p == "" || _property(w[s],"wName") == "" ) continue;
              if (_property(w[s],"wIndex") < p_syswin) str += w[s] + " (system)\n"; else str += w[s] + "\n";
              for (var prop in p)  {
               str += " !-- " + p[prop] + ": ";
               if (p[prop] == "wHtml" && _property(w[s],p[prop]).length > 15) str += _property(w[s],p[prop]).substr(0,12) + "...";
               else {
                       var to = typeof _property(w[s],p[prop]);
                       ( to.match(/function|object/) ) ? to = to : to = _property(w[s],p[prop]);
                       str += to;
               }
               str += "\n";
        }
        str += " !-- " + 'DesktopSkin' + ": " + _dSkin() + "\n";
        str += " !-- " + 'cbe.width' + ": " + _cbe(w[s]).width() + "\n";
        str += " !-- " + 'cbe.height' + ": " + _cbe(w[s]).height() + "\n";
        str += " !-- " + 'cbe.left' + ": " + _cbe(w[s]).left() + "\n";
        str += " !-- " + 'cbe.top' + ": " + _cbe(w[s]).top() + "\n";
        str += " !-- " + 'p_zIndex' + ": " + p_zIndex + "\n";
        str += " !-- " + 'p_respath' + ": " + p_respath + "\n";
        str += " !-- " + 'p_desktop' + ": " + p_desktop + "\n";
        str += " !-- " + 'p_tbBgColor' + ": " + p_tbBgColor + "\n";
        str += " !-- " + 'p_tbColor' + ": " + p_tbColor + "\n";
        str += " !-- " + 'p_tbBoColor' + ": " + p_tbBoColor + "\n";
        str += " !-- " + 'p_taskbar' + ": " + p_taskbar + "\n";
        str += " !-- " + 'p_hidedesktop' + ": " + p_hidedesktop + "\n";
        str += " !-- " + 'maxwin' + ": " + maxwin + "\n";

      }
      if (str) { _sysMessage(str); _positionWindow("dMessage") }
    }
 }
 function _desktop() {
  this.init = _init;
  this.skin = _skin;
  var dtw = "";                                // Desktop Window Object
  function _init() {
   if (dtw == "") { window.cbe.addEventListener('resize',DesktopResizeListener)  }
   _skin();                                // startup Desktop
   p_lastdt = p_desktop;
   _addWindow('dMessage','System Message',300,200,'ne',''); // create by default
   _addWindow('dUser','',400,150,'center','');               // create by default
   _sysMessage("x-Desktop initialized.",true);
  }
  function _skin(wSkin) {
   if (wSkin) p_desktop = wSkin;
   var _setskin = eval("desktop_" + p_desktop);
   _setskin();
   if (xDTwin.getAllWindows(p_syswin) == 0) return;
   var uw = xDTwin.getAllWindows(p_syswin).split('/');
   for (var w in uw){if (p_lastdt.length && p_lastdt != _property(uw[w],"wSkin") ) continue; _refreshWindow(uw[w],wSkin); _positionWindow(uw[w]) }
   _refreshWindow("dMessage",wSkin);
   _positionWindow("dMessage");
   _refreshWindow("dUser",wSkin);
   _positionWindow("dUser");
   _taskbar();
   p_lastdt = wSkin;
  }
 }
 function _taskbar(rWin) {
  if (rWin) {
   _cbe(rWin).zIndex(xDTwin.xDTdrag());
   if(_property(rWin, "wStat") == "min") {
      	_show(rWin);
   	_property(rWin,"wStat","OK");
   }
   else {
	_hide(rWin);
	_property(rWin,"wStat","min");
	}
  }

  xDTwin.xDTTaskbar(p_desktop);

 }
 function _closeAllWindows(wName,wC) {
   if (xDTwin.getAllWindows(p_syswin) == 0) return false;
   var uw = xDTwin.getAllWindows(p_syswin).split('/');
   if (wName) wName = "," + wName + ",";
   for (var w in uw){
     if (wName && wName.indexOf(',' + uw[w] + ',') > -1 ) continue;
     if (_property(uw[w],"fClose") == "") {_hide(uw[w]); _closeWindow(uw[w],wC) }
     else if (_property(uw[w],"fClose") != "" && wC) {_hide(uw[w]); _closeWindow(uw[w],wC) }
     else return false;
   }
   _closeWindow('dMessage'); _closeWindow('dUser'); _closeWindow('dDummy'); _closeWindow('dSound');
   _taskbar();
   return true;
 }
 function _minimizeAllWindows(wName) {
   if (xDTwin.getAllWindows(p_syswin) == 0) return;   var uw = xDTwin.getAllWindows(p_syswin).split('/');   if (wName) wName = "," + wName + ",";
   for (var w in uw){if (wName && wName.indexOf(',' + uw[w] + ',') > -1 ) continue; _property(uw[w],"wStat","min");_hide(uw[w]) }
   _taskbar();
 }
 function _restoreAllWindows() {
   if (xDTwin.getAllWindows(p_syswin) == 0) return;
   var uw = xDTwin.getAllWindows(p_syswin).split('/');
   for (var w in uw){ _show(uw[w]); _cbe(uw[w]).zIndex(_property(uw[w],"zIndex")); _property(uw[w],"wStat","OK"); }
  _taskbar();
 }
 function _arrangeAllWindows(x,y,xoffset,yoffset,wName) {
   if ((typeof x) == 'undefined') {x = 20; y = 20; xoffset = 20; yoffset = 20 }
   if ((typeof y) == 'undefined') {y = 20; xoffset = 20; yoffset = 20 }
   if ((typeof xoffset) == 'undefined') {xoffset = 20; yoffset = 20 }
   if ((typeof yoffset) == 'undefined') yoffset = 20;
   if (xDTwin.getAllWindows(p_syswin) == 0) return;
   var uw = xDTwin.getAllWindows(p_syswin).split('/');
   var wLeft = x;
   var wTop = xDTwin.marginTop(_dSkin());
   (wTop != 0) ? wTop += 0 : wTop = y;
   if (wName) wName = "," + wName + ",";
   for (var w in uw) {
    if (wName && wName.indexOf(',' + uw[w] + ',') > -1 ) continue;
    _cbe(uw[w]).zIndex(xDTwin.xDTdrag());
    _cbe(uw[w]).moveTo(wLeft,wTop);
    _cbe(uw[w]).resizeTo(_property(uw[w],"wWidth"),_property(uw[w],"wHeight"));
    _show(uw[w]);
    _property(uw[w],"wX",wLeft);
    _property(uw[w],"wY",wTop);
    _property(uw[w],"zIndex",_cbe(uw[w]).zIndex());
    _property(uw[w],"wStat","OK");
    wTop += yoffset;
    (xoffset > 0) ? wLeft += xoffset : wLeft = wLeft;
   }
   _taskbar();
 }
 function _preloadImages() {
  var mysrc = "";
  for (var i=0;i<arguments.length;i++) {
   (arguments[i].indexOf(':/') == -1 && arguments[i].indexOf('/') != 0) ? mysrc = p_respath + arguments[i] : mysrc = arguments[i];
   var img = new Image();
   img.src = mysrc;
  }
 }
 /*
 function _data(key,val) {
  if (_data.arguments.length == 0) {var str = ""; var n = 0; for (var i in p_data) {str += i + "=" + typeof(p_data[i]) + "\t"; n++ } return n }
  if (typeof(key) != "string" || key.length == 0) return -1;
  if (_data.arguments.length == 1) return p_data[key]; else {p_data.push[key];p_data[key] = val;}
 }
 */
 /*========================================*/
 /* -- Modules / Interfaces to 3rd parties */
 /*========================================*/
 function _calendar(calfield) {
  p_caldate = calfield;
  var w = 'mCalendar';
  _addWindow(w,'',228,220,'center');
  _url(w,p_respath + 'files/calendar.html','no');
  _cbe(w).zIndex(9999999);
  _show(w);
 }

 _show("dTaskbar");
 /*===================================*/
 /* -- Object Methods for private use */
 /*===================================*/
 function ChooseSize(val) {  if (typeof(val) != 'string') return val;  var r = val.split(","); if (r.length == 1) return parseInt(r[0]);  if ( p_scw < r.length ) return parseInt(r[p_scw]); else return parseInt(r[r.length-1]) }
 function SetScreen() {p_scw = (screen.width <= 1024)? 0 : 1; if (p_scw) p_scw = (screen.width <= 1280)? 1 : 2; }
 function SetWindowPos(wName,wPos) {
  if ( wPos.match(/^(\d+),(\d+)$/) ) {
   _property(wName,"wX",RegExp.$1);                                                         // default wX
   _property(wName,"wY",RegExp.$2);                                                            // default wY
   _cbe(wName).moveTo(RegExp.$1,RegExp.$2);
  }
  else if (wPos.match(/(.+)\:(.)\:([-+]?\d+)$/) ) {
   if (! xDT.window.find(RegExp.$1) ) {alert("Unknown Window: " + RegExp.$1 + "," + RegExp.$2 + "," + RegExp.$3);_cbe(wName).moveTo('center');_property(wName,"wX",_cbe(wName).left());_property(wName,"wY",_cbe(wName).top()) }
   else if (RegExp.$2 == "B") {_property(wName,"wX",_property(RegExp.$1,"wX")); _property(wName,"wY",_property(RegExp.$1,"wY") + _property(RegExp.$1,"wHeight") +  parseInt(RegExp.$3) ); _cbe(wName).moveTo(_property(wName,"wX"),_property(wName,"wY")) }
   else if (RegExp.$2 == "T") {_property(wName,"wX",_property(RegExp.$1,"wX"));_property(wName,"wY",_property(RegExp.$1,"wY") - _property(wName,"wHeight") -  parseInt(RegExp.$3) );_cbe(wName).moveTo(_property(wName,"wX"),_property(wName,"wY")) }
   else if (RegExp.$2 == "L") {_property(wName,"wY",_property(RegExp.$1,"wY"));_property(wName,"wX",_property(RegExp.$1,"wX") - _property(wName,"wWidth") -  parseInt(RegExp.$3) );_cbe(wName).moveTo(_property(wName,"wX"),_property(wName,"wY")) }
   else if (RegExp.$2 == "R") {_property(wName,"wY",_property(RegExp.$1,"wY"));_property(wName,"wX",_property(RegExp.$1,"wX") + _property(RegExp.$1,"wWidth") +  parseInt(RegExp.$3) );_cbe(wName).moveTo(_property(wName,"wX"),_property(wName,"wY")) }
   else {alert("Unknown Parameter(s): " + RegExp.$1 + "," + RegExp.$2 + "," + RegExp.$3);_cbe(wName).moveTo('center');_property(wName,"wX",_cbe(wName).left());_property(wName,"wY",_cbe(wName).top()); }
  }
  else {_cbe(wName).moveTo(wPos);_property(wName,"wX",_cbe(wName).left());_property(wName,"wY",_cbe(wName).top());}
  _property(wName,"wPos",wPos);
  _positionWindow(wName);
 }

 function DeleteWindowFunction(wName,wC) { if (wC) return true;  var f = _property(wName,"fClose"); if ((typeof f) == 'function') return f(wName); if ((typeof f) == 'string' && f.length) return eval(f); return true; }
 function SetLastError(number,text) { p_lError[0] = number; var fs = functionstack(); (!text) ? p_lError[1] = fs : p_lError[1] = fs + ": " + text; }
 function functionname(fstr) { var str = fstr.toString().match(/function (\w*)/)[1]; if ((str == null) || (str.length==0)) str="anonymous"; return str; }
 function functionstack() {
  if (! p_Debug) return "";
  var str = "";
  for (var ac = arguments.caller; ac != null; ac = ac.caller) {
   if (str != "") str += "->";
   str += functionname(ac.callee);
   if (ac.caller == ac) {str += "*"; break }
  }
  return str;
 }
 /*========================================*/
 /* final calls during main object creation*/
 /*========================================*/
 if (_fmain) xDTwin.setStartup(_fmain);
 else if ( (typeof start) != 'undefined') xDTwin.setStartup(start);
 else xDTwin.setStartup(function() { alert("No startup routine defined !") });
 CheckInit(maxwin,_fmain);
}
/*==========================*/
/* -- External Functions -- */
/*==========================*/
function test() { alert('hier') }
function CheckInit(max) { if (xDTwin.wLength() >= max) xDTwin.Startup(); else {var str = "CheckInit('" + max + "')"; setTimeout(str,10);}}
function TimeAndDate() {
 var timer = null;  var clock = "";  var secs = 0;  var showsecs = 0;  var prev = "";
 this.time = _time;  this.stop = _stop; this.clock = _clock; this.date = _date;
 this.showSecs = function _showSecs(doit) {
   if (doit) {showsecs = 1 }
   else { showsecs = 0 }
 }
 function _clock() {
  var time = new Date();  var hours = time.getHours();  var minutes = time.getMinutes();
  minutes=((minutes < 10) ? "0" : "") + minutes;  var seconds = time.getSeconds();
  seconds=((seconds < 10) ? "0" : "") + seconds;  clock = hours + ":" + minutes + ":" + seconds;
//  if (!showsecs) clock = clock.replace(/\:\d\d$/,"");
//  if (clock != prev) {xDT.cbe("dClock").innerHtml('<b class="xDT_clock">' + clock + '</b>'); prev = clock }
  secs++; timer = setTimeout("_clock()",1000);
 }
 function _time() { return clock }
 function _stop() { clearTimeout(timer) }
 function _date() {
  var d=new Date();
  var monthname=new Array("Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Okt","Nov","Dec");
  var str =  d.getDate() + "/" + (d.getMonth() + 1) + "/" + d.getFullYear();
 }
}
function SwiImg(obj,img) { // Switch Image
	document.images[obj].src = img;
	img = document.images[obj];
	var imgName = img.src.toUpperCase();
	if (imgName.substring(imgName.length-3, imgName.length) == "PNG")
	{
		w = img.width;
		h = img.height;
		if(w != 0) {
			img.style.filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + img.src + "\', sizingMethod='scale')";
			img.src = "phpgwapi/templates/idots2/images/spacer.gif";
			img.style.width = w + "px";
			img.style.height = h + " px";
		}
	}

}

function DesktopResizeListener() {
 var w = document.cbe.width();
 var h = document.cbe.height();
 //w--; h--;
 xDT.cbe("dDesktop").resizeTo(w,h);
 xDT.taskbar();
 xDT.positionAllWindows();
}
function dStartListener(e) {
 xDTwin.noevent(false);
 var wName = xDTwin.wName(parseInt(e.cbeCurrentTarget.id.replace(/xDTwi/,'')));
 if (wName.match(/^(dDummy|dUser)$/) ) return;
 if (xDT.prop(wName,"wStat") == "MAX" && ! xDT.prop(wName,"wIcon").match(/.1$/) ) {xDTwin.noevent(true); return;}
 else if (xDT.prop(wName,"wIcon") == "C1") {xDTwin.noevent(true); xDT.deleteWindow(wName); return }
 else if (xDT.prop(wName,"wIcon") == "I1" && wName != "dMessage") {xDTwin.noevent(true); xDT.window.properties(wName); return }
 else if (xDT.prop(wName,"wIcon") == "M1") {
  xDT.prop(wName,"wStat","min")        ;
  xDTwin.noevent(true);
  e.cbeCurrentTarget.hide();
  xDT.taskbar();
  return;
 }
 else if (xDT.prop(wName,"wIcon") == "X1") {
  xDT.maximizeWindow(wName);
  xDTwin.noevent(true);
  return;
 }
 if (e.offsetX > (e.cbeCurrentTarget.width() - 20) &&  e.offsetY > (e.cbeCurrentTarget.height() - 20) ) {e.cbeCurrentTarget.isResizing = true; /*xDT.innerWindows(wName,'hide');*/ }
 else e.cbeCurrentTarget.isResizing = false;
 e.cbeCurrentTarget.zIndex(0);
 e.cbeCurrentTarget.hide();
 xDT.cbe("dMove").resizeTo(xDT.prop(wName,"wWidth"),xDT.prop(wName,"wHeight"));
 xDT.cbe("dMove").moveTo(xDT.prop(wName,"wX"),xDT.prop(wName,"wY"));
 xDT.cbe("dMove").zIndex(9999999);
 xDT.moveWindow(xDT.prop(wName,"wTitle"));
 xDT.prop("dMove","wTitle",xDT.prop(wName,"wTitle"));
 xDT.moveWindow(xDT.prop("dMove","wTitle") + "" );
 xDT.cbe("dMove").background(xDT.cbe(wName).background(),"");
 xDT.show("dMove");
 xDT.innerWindows(wName,'hide');
}
function dListener(e) {
 if (e.cbeCurrentTarget.isResizing) { xDT.cbe("dMove").resizeBy(e.dx,e.dy) }
 else {xDT.cbe("dMove").moveBy(e.dx,e.dy) }
 if (xDT.cbe("dMove").left() < 0) { xDT.cbe("dMove").moveTo(0,xDT.cbe("dMove").top()) }
 if ( (xDT.cbe("dMove").left() + xDT.cbe("dMove").width() ) > document.cbe.width()) { xDT.cbe("dMove").moveTo(document.cbe.width() - xDT.cbe("dMove").width(),xDT.cbe("dMove").top()) }
 //xDT.moveWindow(xDT.prop("dMove","wTitle") + "<br><br>" + xDT.cbe("dMove").left() + ',' + xDT.cbe("dMove").top() + "<br><br>" + xDT.cbe("dMove").width() + "," + xDT.cbe("dMove").height() );

}
function dEndListener(e) {
 var wName = xDTwin.wName(parseInt(e.cbeCurrentTarget.id.replace(/xDTwi/,'')));
 if (wName == "" || wName.match(/^(dDummy|dUser)$/) ) return;
 if ( xDTwin.noevent() ) return;
 e.cbeCurrentTarget.resizeTo(xDT.cbe("dMove").width(),xDT.cbe("dMove").height());
 if (xDT.cbe("dMove").width() < xDT.prop(wName,"wWidthOrg")) e.cbeCurrentTarget.resizeTo(xDT.prop(wName,"wWidthOrg"),xDT.cbe("dMove").height());
 if (xDT.cbe("dMove").height() < xDT.prop(wName,"wHeightOrg")) e.cbeCurrentTarget.resizeTo(xDT.cbe("dMove").width(),xDT.prop(wName,"wHeightOrg"));
 if (xDT.cbe("dMove").width() < xDT.prop(wName,"wWidthOrg") && xDT.cbe("dMove").height() < xDT.prop(wName,"wHeightOrg")) e.cbeCurrentTarget.resizeTo(xDT.prop(wName,"wWidthOrg"),xDT.prop(wName,"wHeightOrg"));

 e.cbeCurrentTarget.moveTo(xDT.cbe("dMove").left(),xDT.cbe("dMove").top());
 e.cbeCurrentTarget.zIndex(xDTwin.xDTdrag());
 e.cbeCurrentTarget.show();
 if (xDT.prop(wName,"innercbe") ) {/*xDT.prop(wName,"innercbe").zIndex(xDT.prop(wName,"cbe").zIndex());*/ xDT.prop(wName,"innercbe").show(); }
 xDT.prop(wName,"wX",xDT.cbe(wName).left());
 xDT.prop(wName,"wY",xDT.cbe(wName).top());
 xDT.prop(wName,"wPos",xDT.cbe(wName).left() + "," + xDT.cbe(wName).top());
 xDT.prop(wName,"wWidth",xDT.cbe(wName).width());
 xDT.prop(wName,"wHeight",xDT.cbe(wName).height());
 xDT.prop(wName,"zIndex",xDTwin.xDTdrag());
 xDT.cbe("dMove").zIndex(0);
 xDT.cbe("dMove").resizeTo(10,10);
 xDT.cbe("dMove").moveTo(xDT.prop(wName,"wX"),xDT.prop(wName,"wY"));
 xDT.hide("dMove");
 xDT.positionWindow(wName);
 xDT.innerWindows(wName,'show');
 if (e.cbeCurrentTarget.isResizing) { var f = xDT.prop(wName,"fResize"); if ((typeof f) == 'function') return f(wName); if ((typeof f) == 'string' && f.length) return eval(f); }
 else { var f = xDT.prop(wName,"fMove"); if ((typeof f) == 'function') return f(wName); if ((typeof f) == 'string' && f.length) return eval(f); }
}
function skin_DEFAULT(wName) {
  var frame_bgcolor = "#cacaca";
  var frame_titleclass = "xDT_wTitleBn";
  var frame_borderwidth = 2;
  var frame_topheight = 16;
  var frame_bottomheight = 10;
  var frame_contentbgcolor = '#6B8CCE';
  var frame_dummypic = xDT.resPath() + 'images/blank.gif';
  var iconpath = xDT.resPath() + 'skins/DEFAULT';
  var frame_stylecolor = '#000000';
  var frame_border = 1;
  var frame_bordertype = "outset"; // solid, outset, inset
  var frame_style =         'border-top: ' + frame_border + 'px ' + frame_stylecolor + ' ' + frame_bordertype + '; ' +
                          'border-bottom: ' + frame_border + 'px ' + frame_stylecolor + ' ' + frame_bordertype + '; ' +
                          'border-left: ' + frame_border + 'px ' + frame_stylecolor + ' ' + frame_bordertype + '; ' +
                          'border-right: ' + frame_border + 'px ' + frame_stylecolor + ' ' + frame_bordertype + '; ';
  return (
                          '<table cellpadding="0" cellspacing="0" border="0" height="100%" width="100%"><tr>' +
                          '<td align="left" valign="top" height="100%" width="100%" style="' + frame_style + '">' +
                          '<table cellpadding="0" cellspacing="0" border="0" height="100% width="100%" bgcolor="' + frame_bgcolor + '" >' +
                          '<tr style="cursor: move"><td><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" height="' + frame_topheight + '" border="0"></td>' +
                               '<td bgcolor="' + frame_bgcolor + '" width="100%" align="left" valign="middle" class="' + frame_titleclass + '">' +
                               '<table cellpadding="0" cellspacing="0" border="0" background="' + iconpath + '/wintitlebgr.gif"><tr>' +
                                 '<td class=""><a class="" href="javascript: void(0)" onmouseover="' + "SwiImg('winleft_" + wName + "','" + iconpath + "/winleft_over.gif'); " + 'xDT.prop(' + "'" + wName + "','wIcon','I1'" +')" ' +  'onmouseout="' + "SwiImg('winleft_" + wName + "','" + iconpath + "/winleft.gif');" + 'xDT.prop(' + "'" + wName + "','wIcon','I0'" + ')"'+ '><img name="winleft_' + wName + '" border="0" src="' + iconpath + '/winleft.gif"></a><img name="wintitleleft" border="0" src="' + iconpath + '/wintitleleft.gif"></td>' +
                                 '<td width="100%" align="left" valign="middle" class="' + frame_titleclass + '">&nbsp;<span id="xDTiT_' + wName + '">' + xDT.prop(wName,"wTitle") + '</span></td>' +
                                 '<td class="' + frame_titleclass + '"><a class="" href="javascript: void(0)" onmouseover="' + "SwiImg('winmin_" + wName + "','" + iconpath + "/winmin_over.gif'); " + 'xDT.prop(' + "'" + wName + "','wIcon','M1'" +')" ' +  'onmouseout="' + "SwiImg('winmin_" + wName + "','" + iconpath + "/winmin.gif'); " + 'xDT.prop(' + "'" + wName + "','wIcon','M0'" + ')"'+ '><img name="winmin_' + wName + '" border="0" src="' + iconpath + '/winmin.gif"></a></td>' +
                                 '<td><img src="' + frame_dummypic + '" width="5" border="0"></td>' +
                                 '<td class="' + frame_titleclass + '"><a class="" href="javascript: void(0)" onmouseover="' + "SwiImg('winmax_" + wName + "','" + iconpath + "/winmax_over.gif'); " + 'xDT.prop(' + "'" + wName + "','wIcon','X1'" +')" ' +  'onmouseout="' + "SwiImg('winmax_" + wName + "','" + iconpath + "/winmax.gif'); " + 'xDT.prop(' + "'" + wName + "','wIcon','X0'" + ')"'+ '><img name="winmax_' + wName + '" border="0" src="' + iconpath + '/winmax.gif"></a></td>' +
                                 '<td><img src="' + frame_dummypic + '" width="5" border="0"></td>' +
                                 '<td class="' + frame_titleclass + '"><a class="" href="javascript: void(0)" onmouseover="' + "SwiImg('winclose_" + wName + "','" + iconpath + "/winclose_over.gif'); " + 'xDT.prop(' + "'" + wName + "','wIcon','C1'" +')" ' +  'onmouseout="' + "SwiImg('winclose_" + wName + "','" + iconpath + "/winclose.gif'); " + 'xDT.prop(' + "'" + wName + "','wIcon','C0'" + ')"'+ '><img name="winclose_' + wName + '" border="0" src="' + iconpath + '/winclose.gif"></a></td>' +
                               '</tr></table>' +
                               '</td>' +
                               '<td><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" height="' + frame_topheight + ' border="0"></td></tr>' +
                          '<tr><td><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" border="0"></td><td align="left" valign="top" width="100%" background="' + iconpath + '/wintitlebgr.gif" height="100%" style="background: ' + frame_contentbgcolor +'; " id="' + wName + 'iTD' + '"></td><td><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" border="0"></td></tr>' +
                          '<tr style="cursor: move"><td><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" height="' + frame_bottomheight + '" border="0"></td><td align="right" width="100%" background="' + iconpath + '/wintitlebgr.gif"><img src="' + frame_dummypic + '" style="cursor: se-resize" width="15" height="' + frame_bottomheight + '" border="0"></td><td><img src="' + frame_dummypic + '" style="cursor: se-resize" width="' + frame_borderwidth + '" height="' + frame_bottomheight + '" border="0"></td></tr></table>' +
                          '</td></tr></table>'
         );
}
function desktop_DEFAULT() {
 var iconpath = xDT.resPath() + 'skins/DEFAULT';
 xDT.addSkin('DEFAULT',0,0);
 xDT.taskbarColor("#6B8CCE","#ffffff","#000000");
 xDT.cbe("dDesktop").resizeTo(document.cbe.width(),document.cbe.height());
 xDT.cbe("dDesktop").innerHtml('<img src="' + iconpath +'/wallpaper_default.jpg" border="0" style="width: 100%; height: 100%">');
 xDT.cbe("dDesktop").zIndex(0);
 xDT.show("dDesktop");
}

function taskbar_DEFAULT() {
  var str = ""; var winName = ""; var winTitle = "";
  for (var i=0;i<= xDT.maxwin;i++) { winName = xDTwin.wName(i); if (!winName) continue; if (i <  xDT.p_syswin && !winName.match(/^(dMessage)$/) ) continue; if (_property(winName,"wStat") != "min") continue; if (str == "") str = winName; else str += "," + winName; }
  wins = str.split(",");
  if (!str || wins.length < 1) { xDT.cbe("dTaskbar").resizeTo(20,20);  xDT.cbe("dTaskbar").moveTo(0,0);  xDT.hide("dTaskbar");  xDT._taskbar = false; return }
  str = '<form style="position: absolute">';
  xDT._taskbar = true;
  var bh = 20;         // height of default task button, see stylesheet
  var bw = 120; // width of default task button, see stylesheet
  var tl = 15;         // max title length
  var h = 0;
  var bprow = parseInt( (document.cbe.width() - 1) / bw); // buttons per row
  if (wins.length <= bprow) h = bh;
  else if ( (wins.length % bprow) == 0 )  h = bh * (wins.length / bprow);
  else h = bh * (1 + parseInt(wins.length / bprow));
  //alert("taskbar= " + p_tbBgColor + "/" + p_tbColor);
  for (var i=0;i<wins.length;i++) {
   winName = wins[i];
   winTitle = _property(winName,"wTitle");
   if (winTitle.length > tl) winTitle = winTitle.substr(0,tl) + "...";
   str += '<input type="button" class="xDT_min_DEFAULT" style="border: 1px ' + p_tbBoColor + ' solid; background-color: ' + p_tbBgColor + "; color: " + p_tbColor+ '" name="tb' + i + '" value="' + winTitle + '" onclick="' + "xDT.taskbar('" + winName + "')" + '"> ';
  }
  str += '</form>';
  _cbe("dTaskbar").resizeTo(document.cbe.width() - 1,h);
  _cbe("dTaskbar").background('transparent');
  _cbe("dTaskbar").innerHtml(str);
  _cbe("dTaskbar").moveTo(150,document.cbe.height() - (++h) - 0 );
  _cbe("dTaskbar").moveTo(150,document.cbe.height() - (++h) - xDTwin.marginBottom(_dSkin()) );
  _cbe("dTaskbar").zIndex(xDTwin.xDTdrag());
  _property("dTaskbar","wWidth",_cbe("dTaskbar").width());
  _property("dTaskbar","wHeight",_cbe("dTaskbar").height());
  _show("dTaskbar");
 }



function skin_xDTnoBORDER(wName) {
 var frame_borderwidth = 1;
 var frame_topheight = 10;
 var frame_bgcolor = "transparent";
 var frame_dummypic = xDT.resPath() + 'images/blank.gif';
 return (
          '<table style="background-color: ' + frame_bgcolor + '" cellpadding="0" cellspacing="0" border="0" height="100%" width="100%">' +
            '<tr><td></td><td style="cursor: move" height="' + frame_topheight + '"></td><td></td></tr>' +
            '<tr><td><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" border="0"></td><td width="100%" height="100%" id="' + wName + 'iTD' + '"></td><td><img src="' + frame_dummypic + '" width="' + frame_borderwidth + '" border="0"></td></tr>' +
            '<tr><td></td><td height="' + frame_borderwidth + '"></td><td></td></tr>' +
          '</table>'
        );
}
function skin_xDTnoWIN(wName) {
 return ('<table cellpadding="0" cellspacing="0" border="0" height="100%" width="100%"><tr><td width="100%" height="100%" id="' + wName + 'iTD' + '"></td></tr></table>');
}
