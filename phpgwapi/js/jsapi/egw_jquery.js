/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @version $Id$
 */

/*egw:uses
	egw_core;
	egw_files;
	egw_ready;
*/

/**
 * NOT USED
 * @param {string} _app application name object is instanciated for
 * @param {object} _wnd window object is instanciated for
 */
egw.extend('jquery', egw.MODULE_WND_LOCAL, function(_app, _wnd)
{
	"use strict";

	// Get the reference to the "files" and the "ready" module for the current
	// window
	var files = this.module('files', _wnd);
	var ready = this.module('ready', _wnd);

	// Include the jQuery and jQuery UI library.
	var token = ready.readyWaitFor();
	files.includeJS([
		this.webserverUrl + '/phpgwapi/js/jquery/jquery.js',
		this.webserverUrl + '/phpgwapi/js/jquery/jquery-ui.js',
		this.webserverUrl + '/phpgwapi/js/jquery/jquery.html5_upload.js'
	], function () {
		this.constant('jquery', '$j', _wnd.$j, _wnd);
		ready.readyDone(token);
	}, this);

	return {
		'$j': null
	};
});
