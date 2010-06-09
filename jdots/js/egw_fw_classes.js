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

function egw_fw_class_application(_parentFw, _appName, _displayName, _icon, _execName)
{
	//Copy the application properties
	this.appName = _appName;
	this.displayName = _displayName;
	this.icon = _icon;
	this.execName = _execName;
	this.sidebox_md5 = '';

	//Setup a link to the parent framework class
	this.parentFw = _parentFw;

	//Preset some variables
	this.hasSideboxMenuContent = false;
	this.sidemenuEntry = null;
	this.tab = null;
	this.iframe = null;
}

/*----------------------------
  Class egw_fw_class_callback
  ----------------------------*/

function egw_fw_class_callback(_context, _proc)
{
	this.context = _context;
	this.proc = _proc;
}

egw_fw_class_callback.prototype.call = function(_sender)
{
	this.proc.call(this.context, _sender);
}

Array.prototype.remove = function(index)
{
	this.splice(index, 1);
}

