/**
 * eGroupware JavaScript Framework - Non UI classes
 *
 * @link http://www.egroupware.org
 * @author Andreas Stoeckel <as@stylite.de>
 * @version $Id$
 */

/*----------------------------
  Class egw_fw_class_application
  ----------------------------*/

function egw_fw_class_application(_parentFw, _appName, _displayName, _icon,
	_indexUrl, _sideboxWidth, _legacyApp)
{
	//Copy the application properties
	this.appName = _appName;
	this.displayName = _displayName;
	this.icon = _icon;
	this.indexUrl = _indexUrl;
	this.sidebox_md5 = '';
	this.legacyApp = _legacyApp;
	this.hasPrerequisites;

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
}

Array.prototype.remove = function(index)
{
	this.splice(index, 1);
}

