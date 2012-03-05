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

"use strict";

/*egw:uses
	egw_core;
*/

egw.extend('config', egw.MODULE_GLOBAL, function() {

	/**
	 * Clientside config
	 * 
	 * @access: private, use egw.config(_name, _app="phpgwapi")
	 */
	var configs = {};

	return {
		/**
		 * Query clientside config
		 * 
		 * @param string _name name of config variable
		 * @param string _app default "phpgwapi"
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
		 * @param array/object
		 */
		set_configs: function(_configs)
		{
			this.configs = _configs;
		}
	};
});

