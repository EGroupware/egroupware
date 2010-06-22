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
	is_ie5 = false
	is_moz1_6 = false;
	is_mozilla = false;
	is_ns4 = true;
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
	return window;
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
	if (typeof(_app) == 'undefined') _app = false;
	if (typeof(_returnID) == 'undefined') _returnID = false;
	windowWidth = egw_getWindowOuterWidth();
	windowHeight = egw_getWindowOuterHeight();

	positionLeft = (windowWidth/2)-(_width/2)+egw_getWindowLeft();
	positionTop  = (windowHeight/2)-(_height/2)+egw_getWindowTop();

	windowID = window.open(_url, _windowName, "width=" + _width + ",height=" + _height +
		",screenX=" + positionLeft + ",left=" + positionLeft + ",screenY=" + positionTop + ",top=" + positionTop +
		",location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status="+_status);

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
	if(is_mozilla)
	{
		return window.screenX;
	}
	else
	{
		return window.screenLeft;
	}
}

// return the left position of the window
function egw_getWindowTop()
{
	if(is_mozilla)
	{
		return window.screenY;
	}
	else
	{
		//alert(window.screenTop);
		return window.screenTop-90;
	}
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
	if (is_mozilla)
	{
		return window.outerWidth;
	}
	else
	{
		return egw_getWindowInnerWidth();
	}
}

// get the outerHeight of the browser window. For IE we simply return the innerHeight
function egw_getWindowOuterHeight()
{
	if (is_mozilla)
	{
		return window.outerHeight;
	}
	else
	{
		return egw_getWindowInnerHeight();
	}
}

// ie selectbox dropdown menu hack. as ie is not able to resize dropdown menus from selectboxes, we
// read the content of the dropdown menu and present it as popup resized for the user. if the user 
// clicks/seleckts a value, the selection is posted back to the origial selectbox
function dropdown_menu_hack(el)
{
	if(el.runtimeStyle)
	{
		if(typeof(enable_ie_dropdownmenuhack) !== 'undefined') {
			if (enable_ie_dropdownmenuhack==1){} else return;
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
			}
			if(!el.options[i].selected){el.options[i].removeNode(true);i--;};
		}
		el.onkeydown = switchMenu;
		el.onclick = showMenu;
		el.onmousewheel= switchMenu;
	}
}

