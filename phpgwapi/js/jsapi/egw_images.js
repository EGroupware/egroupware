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

egw.extend('images', egw.MODULE_GLOBAL, function() {

	/**
	 * Map to serverside available images for users template-set
	 * 
	 * @access: private, use egw.image(_name, _app)
	 */
	var images = {};

	return {
		/**
		 * Set imagemap, called from /phpgwapi/images.php
		 * 
		 * @param array/object _images
		 */
		set_images: function (_images)
		{
			images = _images;
		},

		/**
		 * Get image URL for a given image-name and application
		 * 
		 * @param string _name image-name without extension
		 * @param string _app application name, default current app of window
		 * @return string with URL of image
		 */
		image: function (_name, _app)
		{
			// For logging all paths tried
			var tries = {};

			if (typeof _app == 'undefined')
			{
				if(_name.indexOf('/') > 0)
				{
					var split = et2_csvSplit(_value, 2,"/");
					var _app = split[0];
					_name = split[1];
				}
				else
				{
					_app = this.getAppName();
				}
			}
			
			// own instance specific images in vfs have highest precedence
			tries['vfs']=_name;
			if (typeof images['vfs'] != 'undefined' && typeof images['vfs'][_name] != 'undefined')
			{
				return this.webserverUrl+images['vfs'][_name];
			}
			tries[_app + (_app == 'phpgwapi' ? " (current app)" : "")] = _name;
			if (typeof images[_app] != 'undefined' && typeof images[_app][_name] != 'undefined')
			{
				return this.webserverUrl+images[_app][_name];
			}
			tries['phpgwapi'] = _name;
			if (typeof images['phpgwapi'] != 'undefined' && typeof images['phpgwapi'][_name] != 'undefined')
			{
				return this.webserverUrl+images['phpgwapi'][_name];
			}
			// if no match, check if it might contain an extension
			var matches = [];
			if (matches = _name.match(/\.(png|gif|jpg)$/i))
			{
				return this.image(_name.replace(/.(png|gif|jpg)$/i,''), _app);
			}
			if(matches != null) tries[_app + " (matched)"]= matches;
			console.log('egw.image("'+_name+'", "'+_app+'") image NOT found!  Tried ', tries);
			return null;
		}
	}
});

