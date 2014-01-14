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

			if (typeof _app === 'undefined')
			{
				// If the application name is not given, set it to the name of
				// current application
				_app = this.getAppName();

				// Handle images in appname/imagename format
				if(_name.indexOf('/') > 0)
				{
					var split = _name.split('/',2);
					// dhtmlxtree and egw_action are subdirs in image dir, not applications
					if (split[0] !== 'dhtmlxtree' && split[0] !== 'egw_action')
					{
						_app = split[0];
						_name = split[1];
					}
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
			egw.debug("log",'egw.image("'+_name+'", "'+_app+'") image NOT found!  Tried ', tries);
			return null;
		},

		/**
		 * Get image url for a given mime-type and option file
		 *
		 * @param _mime
		 * @param _path vfs path to generate thumbnails for images
		 * @param _size defaults to 16 (only supported size currently)
		 * @returns url of image
		 */
		mime_icon: function(_mime, _path, _size)
		{
			if (typeof _size == 'undefined') _size = 16;
			if (!_mime) _mime = 'unknown';
			if (_mime == 'httpd/unix-directory') _mime = 'directory';

			var type  = _mime.toLowerCase().split('/');
			var image;

			if (type[0] == 'egw' && (image = this.image('navbar',type[1])))
			{

			}
			else if (typeof _path == 'string' && type[0] == 'image' && type[1].match(/^(png|jpe?g|gif|bmp)$/))
			{
				var thsize = this.config('link_list_thumbnail') || 32;
				image = this.link('/etemplate/thumbnail.php',{ 'path': _path, 'thsize': thsize});
			}
			else
			{
				if ((typeof type[1] == 'undefined' || !(image = this.image('mime'+_size+'_'+type[0]+'_'+type[1], 'etemplate'))) &&
					!(image = this.image('mime'+_size+'_'+type[0], 'etemplate')))
				{
					image = this.image('mime'+_size+'_unknown', 'etemplate');
				}
			}
			return image;
		},

		/**
		 * Create DOM img or svn element depending on url
		 *
		 * @param {string} _url source url
		 * @param {string} _alt alt attribute for img tag
		 * @returns DOM node
		 */
		image_element: function(_url, _alt)
		{
			var icon;
			if (_url.match(/\.svg$/))
			{
				icon = document.createElement('object');
				icon.type = 'image/svg+xml';
				icon.data = _url;
			}
			else
			{
				icon = document.createElement('img');
				icon.src = _url;
				if (_alt) icon.alt = _alt;
			}
			return icon;
		}
	}
});

