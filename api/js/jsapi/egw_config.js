/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/*egw:uses
	egw_core;
*/
import './egw_core.js';

egw.extend('config', egw.MODULE_GLOBAL, function()
{
	"use strict";

	/**
	 * Clientside config
	 *
	 * @access: private, use egw.config(_name, _app="phpgwapi")
	 */
	var configs = {};


	/**
	 * register our mail as mailto protocol handler (only for main-page, FF seems to pop it up constantly, if we do so in an iframe)
 	 */
	function install_mailto_handler()
	{
		if (document.location.href.match(/(\?|&)cd=yes(&|$)/) &&
			typeof navigator.registerProtocolHandler === 'function')	// eg. Safari 15.5 does NOT implement it
		{
			let url = egw_webserverUrl;
			if (url[0] === '/') url = document.location.protocol+'://'+document.location.hostname+(url !== '/' ? url : '');
			navigator.registerProtocolHandler('mailto', url+'/index.php?menuaction=mail.mail_compose.compose&preset[mailto]=%s', 'Mail');
		}
	}

	return {
		/**
		 * Query clientside config
		 *
		 * @param {string} _name name of config variable
		 * @param {string} _app default "phpgwapi"
		 * @return mixed
		 */
		config: function (_name, _app)
		{
			if (typeof _app == 'undefined') _app = 'phpgwapi';

			if (typeof configs[_app] == 'undefined') return null;

			return configs[_app][_name];
		},

		/**
		 * Set clientside configuration for all apps
		 *
		 * @param {object} _configs
		 * @param {boolean} _need_clone _configs need to be cloned, as it is from different window context
		 *	and therefore will be inaccessible in IE, after that window is closed
		 */
		set_configs: function(_configs, _need_clone)
		{
			configs = _need_clone ? jQuery.extend(true, {}, _configs) : _configs;

			if (this.config('install_mailto_handler') !== 'disabled')
			{
				install_mailto_handler();
			}
		}
	};
});