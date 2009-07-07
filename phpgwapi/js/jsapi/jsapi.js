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

// works only correctly in Mozilla/FF and Konqueror
function egw_openWindowCentered2(_url, _windowName, _width, _height, _status)
{
	windowWidth = egw_getWindowOuterWidth();
	windowHeight = egw_getWindowOuterHeight();

	positionLeft = (windowWidth/2)-(_width/2)+egw_getWindowLeft();
	positionTop  = (windowHeight/2)-(_height/2)+egw_getWindowTop();

	windowID = window.open(_url, _windowName, "width=" + _width + ",height=" + _height +
		",screenX=" + positionLeft + ",left=" + positionLeft + ",screenY=" + positionTop + ",top=" + positionTop +
		",location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status="+_status);

	return windowID;
}

function egw_openWindowCentered(_url, _windowName, _width, _height)
{
	return egw_openWindowCentered2(_url, _windowName, _width, _height, 'no');
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
