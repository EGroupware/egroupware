/**
 * eGroupware JavaScript Framework - Non UI classes
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Andreas Stoeckel <as@stylite.de>
 * @version $Id$
 */

/*----------------------------
  Class egw_fw_class_application
  ----------------------------*/
/**
 * application class constructor
 * 
 * @param {type} _parentFw
 * @param {type} _appName
 * @param {type} _displayName
 * @param {type} _icon
 * @param {type} _indexUrl
 * @param {type} _sideboxWidth
 * @param {type} _baseUrl
 * @param {type} _internalName
 * @returns {egw_fw_class_application}
 */
function egw_fw_class_application(_parentFw, _appName, _displayName, _icon,
	_indexUrl, _sideboxWidth, _baseUrl, _internalName)
{
	//Copy the application properties
	this.appName = _appName;
	this.internalName = _internalName;
	this.displayName = _displayName;
	this.icon = _icon;
	this.indexUrl = _indexUrl;
	this.sidebox_md5 = '';
	this.hasPrerequisites;
	this.baseUrl = _baseUrl;

	this.website_title = '';
	this.app_header = '';

	this.sideboxWidth = _sideboxWidth;

	//Setup a link to the parent framework class
	this.parentFw = _parentFw;

	//Preset some variables
	this.hasSideboxMenuContent = false;
	this.sidemenuEntry = null;
	this.tab = null;
	this.browser = null;
}

/**
 * Returns an menuaction inside the jdots_framework for this application.
 * without a "this" context (by directly calling egw_fw_class_application.prototype.getAjaxUrl)
 * or passing null to a "call" call "home" will be used as application name and
 * the the base url will be omitted (default behaviour for all applications which)
 * lie inside the default egw instance.
 *
 * @param {string} _fun is the function which shall be called on the server.
 */
egw_fw_class_application.prototype.getMenuaction = function(_fun)
{
	var baseUrl = '';
	var appName = 'home';

	if (this)
	{
		baseUrl = this.getBaseUrl();
		appName = this.internalName;
	}

	// Check whether the baseurl is actually set. If not, then this application
	// resides inside the same egw instance as the jdots framework. We'll simply
	// return a menu action and not a full featured url here.
	if (baseUrl != '')
	{
		baseUrl = baseUrl + 'json.php?menuaction=';
	}

	// use template handler to call current framework, eg. pixelegg
	return baseUrl + appName + '.jdots_framework.' + _fun + '.template';
};

/**
 * Returns the base url for this application. If the application resides inside
 * the default egw instance, '' will be returned unless the _force parameter is
 * set to true.
 *
 * @param {boolean} _force Optional parameter. If set, getBaseUrl will return the
 *  webserverUrl instead of '' if the application resides inside the main
 *  egw instance.
 */
egw_fw_class_application.prototype.getBaseUrl = function(_force)
{
	if (this.baseUrl)
	{
		return this.baseUrl;
	}
	else if ((typeof _force != 'undefined') && _force)
	{
		return egw_topWindow().egw_webserverUrl;
	}
	else
	{
		return '';
	}
};

function egw_fw_getMenuaction(_fun)
{
	return egw_fw_class_application.prototype.getMenuaction.call(null, _fun);
}

/*----------------------------
  Class egw_fw_class_callback
  ----------------------------*/

function egw_fw_class_callback(_context, _proc)
{
	this.context = _context;
	this.proc = _proc;
}

egw_fw_class_callback.prototype.call = function()
{
	return this.proc.apply(this.context, arguments);
};

array_remove = function(array, index)
{
	array.splice(index, 1);
};

