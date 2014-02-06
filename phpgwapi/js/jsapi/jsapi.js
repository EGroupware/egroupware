/**
 * eGroupWare - API
 * http://www.egroupware.org
 *
 * This file was originally created Tyamad, but their content is now completly removed!
 * It still contains some commonly used javascript functions, always included by EGroupware.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage jsapi
 * @version $Id$
 */

/***********************************************\
*               INITIALIZATION                  *
\***********************************************/
if (document.all)
{
	navigator.userAgent.toLowerCase().indexOf('msie 5') != -1 ? is_ie5 = true : is_ie5 = false;
	is_ie = true;
	is_moz1_6 = false;
	is_mozilla = false;
	is_ns4 = false;
}
else if (document.getElementById)
{
	navigator.userAgent.toLowerCase().match('mozilla.*rv[:]1\.6.*gecko') ? is_moz1_6 = true : is_moz1_6 = false;
	is_ie = false;
	is_ie5 = false;
	is_mozilla = true;
	is_ns4 = false;
}
else if (document.layers)
{
	is_ie = false;
	is_ie5 = false;
	is_moz1_6 = false;
	is_mozilla = false;
	is_ns4 = true;
}

//console.log('is_ie='+is_ie+', is_ie5='+is_ie5+', is_mozilla='+is_mozilla+', is_moz1_6='+is_moz1_6+', is_ns4='+is_ns4);

/**
 * Check whether the console object is defined - if not, define one
 */
if (typeof window.console == 'undefined')
{
	window.console = {
		'log': function() {
		},
		'warn': function() {
		},
		'error': function() {
		},
		'info': function() {
		}
	}
}

/**
 * Seperates all script tags from the given html code and returns the seperately
 * @param object _html object that the html code from which the script should be seperated. The html code has to be stored in _html.html, the result js will be written to _html.js
 */

egw_seperateJavaScript = function(_html)
{
	var html = _html.html;

	var in_pos = html.search(/<script/im);
	var out_pos = html.search(/<\/script>/im);
	while (in_pos > -1 && out_pos > -1)
	{
		/*Seperate the whole <script...</script> tag */
		var js_str = html.substring(in_pos, out_pos+9);

		/*Remove the initial tag */
		/*js_str = js_str.substring(js_str.search(/>/) + 1);*/
		_html.js += js_str;


		html = html.substring(0, in_pos) + html.substring(out_pos + 9);

		var in_pos = html.search(/<script/im);
		var out_pos = html.search(/<\/script>/im);
	}

	_html.html = html;
}

/**
 * Inserts the script tags inside the given html into the dom tree
 */
function egw_insertJS(_html)
{
	// Insert each script element seperately
	if (_html)
	{

		var in_pos = -1;
		var out_pos = -1;

		do {

			// Search in and out position
			var in_pos = _html.search(/<script/im);
			var out_pos = _html.search(/<\/script>/im);

			// Copy the text inside the script tags...
			if (in_pos > -1 && out_pos > -1)
			{
				if (out_pos > in_pos)
				{
					var scriptStart = _html.indexOf("\>", in_pos);
					if (scriptStart > in_pos)
					{
						var script = _html.substring(scriptStart + 1,
							out_pos);
						try
						{
							// And insert them as real script tags
							var tag = document.createElement("script");
							tag.setAttribute("type", "text/javascript");
							tag.text = script;
							document.getElementsByTagName("head")[0].appendChild(tag);
						}
						catch (e)
						{
							if (typeof console != "undefined" && typeof console.log != "undefined")
							{
								console.log('Error while inserting JS code:', _e);
							}
						}
					}
				}
				_html = _html.substr(out_pos + 9);
			}

		} while (in_pos > -1 && out_pos > -1)
	}
}

/**
 * Returns the top window which contains the current egw_instance, even for popup windows
 */
function egw_topWindow()
{
	if (typeof window.parent != "undefined" && typeof window.parent.top != "undefined")
	{
		return window.parent.top;
	}

	if (typeof window.opener != "undefined" && typeof window.opener.top != "undefined")
	{
		return window.opener.top;
	}

	return window.top;
}

/**
 * Returns the window object of the current application
 * @param string _app is the name of the application which requests the window object
 */
function egw_appWindow(_app)
{
	var framework = egw_getFramework();
	if(framework && framework.egw_appWindow) return framework.egw_appWindow(_app);
	return window;
}

/**
 * Open _url in window of _app
 * @param _app
 * @param _url
 */
function egw_appWindowOpen(_app, _url)
{
	if (typeof _url == "undefined") {
		_url = "about:blank";
	}
	window.location = _url;
}

/**
 * Returns the current egw application
 * @param string _name is only used for fallback, if an onlder version of jdots is used.
 */
function egw_getApp(_name)
{
	return window.parent.framework.getApplicationByName(_name);
}

/**
 * Returns the name of the currently active application
 */
function egw_getAppName()
{
	if (typeof egw_appName == 'undefined')
	{
		return 'egroupware';
	}
	else
	{
		return egw_appName;
	}
}

/**
 * Refresh given application _targetapp display of entry _app _id, incl. outputting _msg
 *
 * Default implementation here only reloads window with it's current url with an added msg=_msg attached
 *
 * @param string _msg message (already translated) to show, eg. 'Entry deleted'
 * @param string _app application name
 * @param string|int _id=null id of entry to refresh
 * @param string _type=null either 'update', 'edit', 'delete', 'add' or null
 * - update: request just modified data from given rows.  Sorting is not considered,
 *		so if the sort field is changed, the row will not be moved.
 * - edit: rows changed, but sorting may be affected.  Requires full reload.
 * - delete: just delete the given rows clientside (no server interaction neccessary)
 * - add: requires full reload for proper sorting
 * @param string _targetapp which app's window should be refreshed, default current
 * @param string|RegExp _replace regular expression to replace in url
 * @param string _with
 * @param string _msg_type 'error', 'warning' or 'success' (default)
 */
function egw_refresh(_msg, _app, _id, _type, _targetapp, _replace, _with, _msg_type)
{
	// Log for debugging purposes
	egw.debug("log", "egw_refresh(%s, %s, %s, %o, %s, %s)", _msg, _app, _id, _type, _targetapp, _replace, _with, _msg_type);

	//alert("egw_refresh(\'"+_msg+"\',\'"+_app+"\',\'"+_id+"\',\'"+_type+"\')");
	var win = typeof _targetapp != 'undefined' ? egw_appWindow(_targetapp) : window;

	win.egw_message(_msg, _msg_type);

	// if window registered an app_refresh method or overwritten app_refresh, just call it
	if(typeof win.app_refresh == "function" && typeof win.app_refresh.registered == "undefined" ||
		typeof win.app_refresh != "undefined" && win.app_refresh.registered(_app))
	{
		win.app_refresh(_msg, _app, _id, _type);
		return;
	}

	// etemplate2 specific to avoid reloading whole page
	if(typeof etemplate2 != "undefined" && etemplate2.getByApplication)
	{
		var et2 = etemplate2.getByApplication(_app);
		for(var i = 0; i < et2.length; i++)
		{
			et2[i].refresh(_msg,_app,_id,_type);
		}

		// Refresh target or current app too
		var et2 = etemplate2.getByApplication(_targetapp || egw_appName);
		for(var i = 0; i < et2.length; i++)
		{
			et2[i].refresh(_msg,_app,_id,_type);
		}
		//In case that we have etemplate2 ready but it's empty
		if (et2.length >= 1)
		return;
	}

	var href = win.location.href;

	if (typeof _replace != 'undefined')
	{
		href = href.replace(typeof _replace == 'string' ? new RegExp(_replace) : _replace, typeof _with != 'undefined' ? _with : '');
	}

	if (href.indexOf('msg=') != -1)
	{
		href = href.replace(/msg=[^&]*/,'msg='+encodeURIComponent(_msg));
	}
	else if (_msg)
	{
		href += (href.indexOf('?') != -1 ? '&' : '?') + 'msg=' + encodeURIComponent(_msg);
	}
	//alert('egw_refresh() about to call '+href);
	win.location.href = href;
}

/**
 * Display an error or regular message
 *
 * @param {string} _msg message to show
 * @param {string} _type 'error', 'warning' or 'success' (default)
 * @deprecated use egw(window).message(_msg, _type)
 */
function egw_message(_msg, _type)
{
	egw(window).message(_msg, _type);
}

/**
 * Update app-header and website-title
 *
 * @param {string} _header
 * @param {string} _app Application name, if not for the current app
   @deprecated use egw(window).app_header(_header, _app)
*/
function egw_app_header(_header,_app)
{
	egw(window).app_header(_header, _app);
}

/**
 * View an EGroupware entry: opens a popup of correct size or redirects window.location to requested url
 *
 * Examples:
 * - egw_open(123,'infolog') or egw_open('infolog:123') opens popup to edit or view (if no edit rights) infolog entry 123
 * - egw_open('infolog:123','timesheet','add') opens popup to add new timesheet linked to infolog entry 123
 * - egw_open(123,'addressbook','view') opens addressbook view for entry 123 (showing linked infologs)
 * - egw_open('','addressbook','view_list',{ search: 'Becker' }) opens list of addresses containing 'Becker'
 *
 * @param string|int id either just the id or "app:id" if app==""
 * @param string app app-name or empty (app is part of id)
 * @param string type default "edit", possible "view", "view_list", "edit" (falls back to "view") and "add"
 * @param object|string extra extra url parameters to append as object or string
 * @param string target target of window to open
 * @deprecated use egw.open()
 */
function egw_open(id, app, type, extra, target)
{
	window.egw.open(id, app, type, extra, target);
}

window.egw_getFramework = function()
{
	if (typeof window.framework != 'undefined')
	{
		return framework;
	}
	else if (typeof window.parent.egw_getFramework != "undefined" && window != window.parent)
	{
		return window.parent.egw_getFramework();
	}
	else
	{
		return null;
	}
}

/**
 * Register a custom method to refresh an application in an intelligent way
 *
 * This function will be called any time the application needs to be refreshed.
 * The default is to just reload, but with more detailed knowledge of the application
 * internals, it should be possible to only refresh what is needed.
 *
 * The refresh function signature is:
 * function (_msg, _app, _id, _type);
 * returns void
 * @see egw_refresh()
 *
 * @param appname String Name of the application
 * @param refresh_func function to call when refreshing
 */
window.register_app_refresh = function(appname, refresh_func)
{
	var framework = window.egw_getFramework();
	if(framework != null && typeof framework.register_app_refresh == "function")
	{
		framework.register_app_refresh(appname,refresh_func);
	}
	else
	{
		if(typeof window.app_refresh != "function")
		{
			// Define the app_refresh stuff here
			window.app_refresh = function(_msg, appname, _id, _type) {
				if(window.app_refresh.registry[appname])
				{
					window.app_refresh.registry[appname].call(this,_msg,appname,_id,_type);
				}
			};
			window.app_refresh.registry = {};
			window.app_refresh.registered = function(appname) {
				return (typeof window.app_refresh.registry[appname] == "function");
			};
		}
		window.app_refresh.registry[appname] = refresh_func;
	}
}


function egw_set_checkbox_multiselect_enabled(_id, _enabled)
{
	//Retrieve the checkbox_multiselect base div
	var ms = document.getElementById('exec['+_id+']');
	if (ms !== null)
	{
		//Set the background color
		var label_color = "";
		if (_enabled)
		{
			ms.style.backgroundColor = "white";
			label_color = "black";
		}
		else
		{
			ms.style.backgroundColor = "#EEEEEE";
			label_color = "gray"
		}

		//Enable/Disable all children input elements
		for (var i = 0; i <ms.childNodes.length; i++)
		{
			if (ms.childNodes[i].nodeName == 'LABEL')
			{
				ms.childNodes[i].style.color = label_color;
				if ((ms.childNodes[i].childNodes.length >= 1) &&
					(ms.childNodes[i].childNodes[0].nodeName == 'INPUT'))
				{
					ms.childNodes[i].childNodes[0].disabled = !_enabled;
					ms.childNodes[i].childNodes[0].checked &= _enabled;
				}
			}
		}
	}
}

// works only correctly in Mozilla/FF and Konqueror
function egw_openWindowCentered2(_url, _windowName, _width, _height, _status, _app, _returnID)
{
	// Log for debugging purposes
	egw.debug("navigation", "egw_openWindowCentered2(%s, %s, %s, %o, %s, %s)",_url,_windowName,_width,_height,_status,_app);

	if (typeof(_app) == 'undefined') _app = false;
	if (typeof(_returnID) == 'undefined') _returnID = false;
	windowWidth = egw_getWindowOuterWidth();
	windowHeight = egw_getWindowOuterHeight();

	positionLeft = (windowWidth/2)-(_width/2)+egw_getWindowLeft();
	positionTop  = (windowHeight/2)-(_height/2)+egw_getWindowTop();

	if (is_ie) _windowName = !_windowName ? '_blank' : _windowName.replace(/[^a-zA-Z0-9_]+/,'');	// IE fails, if name contains eg. a dash (-)

	windowID = window.open(_url, _windowName, "width=" + _width + ",height=" + _height +
		",screenX=" + positionLeft + ",left=" + positionLeft + ",screenY=" + positionTop + ",top=" + positionTop +
		",location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status="+_status);

	// inject egw object
	windowID.egw = window.egw;

	// returning something, replaces whole window in FF, if used in link as "javascript:egw_openWindowCentered2()"
	if (_returnID === false)
	{
		// return nothing
	}
	else
	{
		return windowID;
	}
}

function egw_openWindowCentered(_url, _windowName, _width, _height)
{
	return egw_openWindowCentered2(_url, _windowName, _width, _height, 'no', false, true);
}

// return the left position of the window
function egw_getWindowLeft()
{
	// workaround for Fennec bug https://bugzilla.mozilla.org/show_bug.cgi?format=multiple&id=648250 window.(outerHeight|outerWidth|screenX|screenY) throw exception
	try {
		if(is_mozilla) return window.screenX;
	}
	catch (e) {}

	return window.screenLeft;
}

// return the left position of the window
function egw_getWindowTop()
{
	// workaround for Fennec bug https://bugzilla.mozilla.org/show_bug.cgi?format=multiple&id=648250 window.(outerHeight|outerWidth|screenX|screenY) throw exception
	try {
		if(is_mozilla) return window.screenY;
	}
	catch (e) {}

	return window.screenTop-90;
}

// get the outerWidth of the browser window. For IE we simply return the innerWidth
function egw_getWindowInnerWidth()
{
	if (is_mozilla)
	{
		return window.innerWidth;
	}
	else
	{
		// works only after the body has parsed
		//return document.body.offsetWidth;
		return document.body.clientWidth;
		//return document.documentElement.clientWidth;
	}
}

// get the outerHeight of the browser window. For IE we simply return the innerHeight
function egw_getWindowInnerHeight()
{
	if (is_mozilla)
	{
		return window.innerHeight;
	}
	else
	{
		// works only after the body has parsed
		//return document.body.offsetHeight;
		//return document.body.clientHeight;
		return document.documentElement.clientHeight;
	}
}

// get the outerWidth of the browser window. For IE we simply return the innerWidth
function egw_getWindowOuterWidth()
{
	// workaround for Fennec bug https://bugzilla.mozilla.org/show_bug.cgi?format=multiple&id=648250 window.(outerHeight|outerWidth|screenX|screenY) throw exception
	try {
		if (is_mozilla) return window.outerWidth;
	}
	catch (e) {}

	return egw_getWindowInnerWidth();
}

// get the outerHeight of the browser window. For IE we simply return the innerHeight
function egw_getWindowOuterHeight()
{
	// workaround for Fennec bug https://bugzilla.mozilla.org/show_bug.cgi?format=multiple&id=648250 window.(outerHeight|outerWidth|screenX|screenY) throw exception
	try {
		if (is_mozilla) return window.outerHeight;
	}
	catch (e) {}

	return egw_getWindowInnerHeight();
}

// ie selectbox dropdown menu hack. as ie is not able to resize dropdown menus from selectboxes, we
// read the content of the dropdown menu and present it as popup resized for the user. if the user
// clicks/seleckts a value, the selection is posted back to the origial selectbox
function dropdown_menu_hack(el)
{
	if(el.runtimeStyle)
	{
		if(typeof(enable_ie_dropdownmenuhack) !== 'undefined')
		{
			if (enable_ie_dropdownmenuhack==1){

			}
			else
				return;
		} else {
			return;
		}
		if(el.runtimeStyle.behavior.toLowerCase()=="none"){return;}
		el.runtimeStyle.behavior="none";

		if (el.multiple ==1) {return;}
		if (el.size > 1) {return;}

		var ie5 = (document.namespaces==null);
		el.ondblclick = function(e)
		{
			window.event.returnValue=false;
			return false;
		}

		if(window.createPopup==null)
		{
			var fid = "dropdown_menu_hack_" + Date.parse(new Date());

			window.createPopup = function()
			{
				if(window.createPopup.frameWindow==null)
				{
					el.insertAdjacentHTML("MyFrame","<iframe id='"+fid+"' name='"+fid+"' src='about:blank' frameborder='1' scrolling='no'></></iframe>");
					var f = document.frames[fid];
					f.document.open();
					f.document.write("<html><body></body></html>");
					f.document.close();
					f.fid = fid;


					var fwin = document.getElementById(fid);
					fwin.style.cssText="position:absolute;top:0;left:0;display:none;z-index:99999;";


					f.show = function(px,py,pw,ph,baseElement)
					{
						py = py + baseElement.getBoundingClientRect().top + Math.max( document.body.scrollTop, document.documentElement.scrollTop) ;
						px = px + baseElement.getBoundingClientRect().left + Math.max( document.body.scrollLeft, document.documentElement.scrollLeft) ;
						fwin.style.width = pw + "px";
						fwin.style.height = ph + "px";
						fwin.style.posLeft =px ;
						fwin.style.posTop = py ;
						fwin.style.display="block";
					}


					f_hide = function(e)
					{
						if(window.event && window.event.srcElement && window.event.srcElement.tagName && window.event.srcElement.tagName.toLowerCase()=="select"){return true;}
						fwin.style.display="none";
					}
					f.hide = f_hide;
					document.attachEvent("onclick",f_hide);
					document.attachEvent("onkeydown",f_hide);

				}
				return f;
			}
		}

		function showMenu()
		{

			function selectMenu(obj)
			{
				var o = document.createElement("option");
				o.value = obj.value;
				//alert("val"+o.value+', text:'+obj.innerHTML+'selected:'+obj.selectedIndex);
				o.text = obj.innerHTML;
				o.text = o.text.replace('<NOBR>','');
				o.text = o.text.replace('</NOBR>','');
				//if there is no value, you should not try to set the innerHTML, as it screws up the empty selection ...
				if (o.value != '') o.innerHTML = o.text;
				while(el.options.length>0){el.options[0].removeNode(true);}
				el.appendChild(o);
				el.title = o.innerHTML;
				el.contentIndex = obj.selectedIndex ;
				el.menu.hide();
				if(el.onchange)
				{
					el.onchange();
				}
			}


			el.menu.show(0 , el.offsetHeight , 10, 10, el);
			var mb = el.menu.document.body;

			mb.style.cssText ="border:solid 1px black;margin:0;padding:0;overflow-y:auto;overflow-x:auto;background:white;font:12px Tahoma, sans-serif;";
			var t = el.contentHTML;
			//alert("1"+t);
			t = t.replace(/<select/gi,'<div');
			//alert("2"+t);
			t = t.replace(/<option/gi,'<span');
			//alert("3"+t);
			t = t.replace(/<\/option/gi,'</span');
			//alert("4"+t);
			t = t.replace(/<\/select/gi,'</div');
			t = t.replace(/<optgroup label=\"([\w\s\wäöüßÄÖÜ]*[^>])*">/gi,'<span value="i-opt-group-lable-i">$1</span>');
			t = t.replace(/<\/optgroup>/gi,'<span value="">---</span>');
			mb.innerHTML = t;
			//mb.innerHTML = "<div><span value='dd:ff'>gfgfg</span></div>";

			el.select = mb.all.tags("div")[0];
			el.select.style.cssText="list-style:none;margin:0;padding:0;";
			mb.options = el.select.getElementsByTagName("span");

			for(var i=0;i<mb.options.length;i++)
			{
				//alert('Value:'+mb.options[i].value + ', Text:'+ mb.options[i].innerHTML);
				mb.options[i].selectedIndex = i;
				mb.options[i].style.cssText = "list-style:none;margin:0;padding:1px 2px;width/**/:100%;white-space:nowrap;"
				if (mb.options[i].value != 'i-opt-group-lable-i') mb.options[i].style.cssText = mb.options[i].style.cssText + "cursor:hand;cursor:pointer;";
				mb.options[i].title =mb.options[i].innerHTML;
				mb.options[i].innerHTML ="<nobr>" + mb.options[i].innerHTML + "</nobr>";
				if (mb.options[i].value == 'i-opt-group-lable-i') mb.options[i].innerHTML = "<b><i>"+mb.options[i].innerHTML+"</b></i>";
				if (mb.options[i].value != 'i-opt-group-lable-i') mb.options[i].onmouseover = function()
				{
					if( mb.options.selected )
					{mb.options.selected.style.background="white";mb.options.selected.style.color="black";}
					mb.options.selected = this;
					this.style.background="#333366";this.style.color="white";
				}
				mb.options[i].onmouseout = function(){this.style.background="white";this.style.color="black";}
				if (mb.options[i].value != 'i-opt-group-lable-i')
				{
					mb.options[i].onmousedown = function(){selectMenu(this); }
					mb.options[i].onkeydown = function(){selectMenu(this); }
				}
				if(i == el.contentIndex)
				{
					mb.options[i].style.background="#333366";
					mb.options[i].style.color="white";
					mb.options.selected = mb.options[i];
				}
			}
			var mw = Math.max( ( el.select.offsetWidth + 22 ), el.offsetWidth + 22 );
			mw = Math.max( mw, ( mb.scrollWidth+22) );
			var mh = mb.options.length * 15 + 8 ;
			var mx = (ie5)?-3:0;
			var docW = document.documentElement.offsetWidth ;
			var sideW = docW - el.getBoundingClientRect().left ;
			if (sideW < mw)
			{
				//alert(el.getBoundingClientRect().left+' Avail: '+docW+' Mx:'+mx+' My:'+my);
				// if it does not fit into the window on the right side, move it to the left
				mx = mx -mw + sideW-5;
			}
			var my = el.offsetHeight -2;
			my=my+5;
			var docH = document.documentElement.offsetHeight ;
			var bottomH = docH - el.getBoundingClientRect().bottom ;
			mh = Math.min(mh, Math.max(( docH - el.getBoundingClientRect().top - 50),100) );
			if(( bottomH < mh) )
			{
				mh = Math.max( (bottomH - 12),10);
				if( mh <100 )
				{
					my = -100 ;
				}
				mh = Math.max(mh,100);
			}
			self.focus();
			el.menu.show( mx , my , mw, mh , el);
			sync=null;
			if(mb.options.selected)
			{
				mb.scrollTop = mb.options.selected.offsetTop;
			}
			window.onresize = function(){el.menu.hide()};
		}

		function switchMenu()
		{
			if(event.keyCode)
			{
				if(event.keyCode==40){ el.contentIndex++ ;}
				else if(event.keyCode==38){ el.contentIndex--; }
			}
			else if(event.wheelDelta )
			{
				if (event.wheelDelta >= 120)
					el.contentIndex++ ;
				else if (event.wheelDelta <= -120)
					el.contentIndex-- ;
			}
			else{return true;}
			if( el.contentIndex > (el.contentOptions.length-1) ){ el.contentIndex =0;}
			else if (el.contentIndex<0){el.contentIndex = el.contentOptions.length-1 ;}
			var o = document.createElement("option");
			o.value = el.contentOptions[el.contentIndex].value;
			o.innerHTML = el.contentOptions[el.contentIndex].text;
			while(el.options.length>0){el.options[0].removeNode(true);}
			el.appendChild(o);
			el.title = o.innerHTML;
		}
		if(dropdown_menu_hack.menu ==null)
		{
			dropdown_menu_hack.menu = window.createPopup();
			document.attachEvent("onkeydown",dropdown_menu_hack.menu.hide);
		}
		el.menu = dropdown_menu_hack.menu ;
		el.contentOptions = new Array();
		el.contentIndex = el.selectedIndex;
		el.contentHTML = el.outerHTML;

		for(var i=0;i<el.options.length;i++)
		{

			el.contentOptions [el.contentOptions.length] =
			{
				"value": el.options[i].value,"text": el.options[i].innerHTML
			};
			if(!el.options[i].selected){el.options[i].removeNode(true);i--;};
		}
		el.onkeydown = switchMenu;
		el.onclick = showMenu;
		el.onmousewheel= switchMenu;
	}
}

/**
 * Dummy link handler, which can be overwritten by templates
 *
 * @param _link
 * @param _app
 */
function egw_link_handler(_link, _app)
{
	if (window.framework)
	{
		window.framework.linkHandler(_link, _app);
	}
	else
	{
		window.location.href = _link;
	}
}

/**
 * Call context / open app specific preferences function
 *
 * @param string name type 'acl', 'prefs', or 'cats'
 * @param array|object apps array with apps allowing to call that type, or object/hash with app and boolean or hash with url-params
 */
function egw_preferences(name, apps)
{
	var current_app = egw_getAppName();
	var query = {};
	// give warning, if app does not support given type, but all apps link to common prefs, if they dont support prefs themselfs
	if ($j.isArray(apps) && $j.inArray(current_app, apps) == -1 && name != 'prefs' ||
		!$j.isArray(apps) && (typeof apps[current_app] == 'undefined' || !apps[current_app]))
	{
		egw_message(egw.lang('Not supported by current application!'), 'warning');
	}
	else
	{
		var url = '/index.php';
		switch(name)
		{
			case 'prefs':
				query.menuaction ='preferences.preferences_settings.index';
				if ($j.inArray(current_app, apps) != -1) query.appname=current_app;
				break;

			case 'acl':
				query.menuaction='preferences.preferences_acl.index';
				query.acl_app=current_app;
				query.ajax=true;
				break;

			case 'cats':
				if (typeof apps[current_app] == 'object')
				{
					for(var key in apps[current_app])
					{
						query[key] = encodeURIComponent(apps[current_app][key]);
					}
				}
				else
				{
					query.menuaction='preferences.preferences_categories_ui.index';
					query.cats_app=current_app;
					query.ajax=true;
				}
				break;
		}
		query.current_app = current_app;
		egw_link_handler(egw.link(url, query), current_app);
	}
}

/**
 * Support functions for uiaccountselection class
 *
 * @ToDo: should be removed if uiaccountsel class is no longer in use
 */
function addOption(id,label,value,do_onchange)
{
	selectBox = document.getElementById(id);
	for (var i=0; i < selectBox.length; i++) {
	//		check existing entries if they're already there and only select them in that case
		if (selectBox.options[i].value == value) {
			selectBox.options[i].selected = true;
			break;
		}
	}
	if (i >= selectBox.length) {
		if (!do_onchange) {
			if (selectBox.length && selectBox.options[0].value=='') selectBox.options[0] = null;
			selectBox.multiple=true;
			selectBox.size=4;
		}
		selectBox.options[selectBox.length] = new Option(label,value,false,true);
	}
	if (selectBox.onchange && do_onchange) selectBox.onchange();
}
/**
 * Install click handlers for popup and multiple triggers of uiaccountselection
 */
$j(function(){
	$j(document).on('click', 'input.uiaccountselection_trigger',function(){
		var selectBox = document.getElementById(this.id.replace(/(_multiple|_popup)$/, ''));
		if (selectBox)
		{
			var link = selectBox.getAttribute('data-popup-link');

			if (selectBox.multiple || this.id.match(/_popup$/))
			{
				window.open(link, 'uiaccountsel', 'width=600,height=420,toolbar=no,scrollbars=yes,resizable=yes');
			}
			else
			{
				selectBox.size = 4;
				selectBox.multiple = true;
				if (selectBox.options[0].value=='') selectBox.options[0] = null;

				if (!$j(selectBox).hasClass('groupmembers') && !$j(selectBox).hasClass('selectbox'))	// no popup!
				{
					this.src = egw.image('search');
					this.title = egw.lang('Search accounts');
				}
				else
				{
					this.style.display = 'none';
					selectBox.style.width = '100%';
				}
			}
		}
	});
	$j(document).on('change', 'select.uiaccountselection',function(e){
		if (this.value == 'popup')
		{
			var link = this.getAttribute('data-popup-link');
			window.open(link, 'uiaccountsel', 'width=600,height=420,toolbar=no,scrollbars=yes,resizable=yes');
			e.preventDefault();
		}
	});
});

