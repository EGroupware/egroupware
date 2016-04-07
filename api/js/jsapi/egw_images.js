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

egw.extend('images', egw.MODULE_GLOBAL, function()
{
	"use strict";

	/**
	 * Map to serverside available images for users template-set
	 *
	 * @access: private, use egw.image(_name, _app)
	 */
	var images = {};

	/**
	 * Mapping some old formats to the newer form, or any other aliasing for mime-types
	 *
	 * Should be in sync with ../inc/class.mime_magic.inc.php
	 */
	var mime_alias_map = {
		'text/vcard': 'text/x-vcard',
		'text/comma-separated-values': 'text/csv',
		'text/rtf': 'application/rtf',
		'text/xml': 'application/xml',
		'text/x-diff': 'text/diff',
		'application/x-jar': 'application/java-archive',
		'application/x-javascript': 'application/javascript',
		'application/x-troff': 'text/troff',
		'application/x-egroupware-etemplate': 'application/xml'
	};

	return {
		/**
		 * Set imagemap, called from /api/images.php
		 *
		 * @param {array|object} _images
		 * @param {boolean} _need_clone _images need to be cloned, as it is from different window context
		 *	and therefore will be inaccessible in IE, after that window is closed
		 */
		set_images: function (_images, _need_clone)
		{
			images = _need_clone ? jQuery.extend(true, {}, _images) : _images;
		},

		/**
		 * Get image URL for a given image-name and application
		 *
		 * @param {string} _name image-name without extension
		 * @param {string} _app application name, default current app of window
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
			var matches = _name.match(/\.(png|gif|jpg)$/i);
			if (matches)
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
		 * @param {string} _mime
		 * @param {string} _path vfs path to generate thumbnails for images
		 * @param {number} _size defaults to 128 (only supported size currently)
		 * @param {number} _mtime current modification time of file to allow infinit caching as url changes
		 * @returns url of image
		 */
		mime_icon: function(_mime, _path, _size, _mtime)
		{
			if (typeof _size == 'undefined') _size = 128;
			if (!_mime) _mime = 'unknown';
			if (_mime == 'httpd/unix-directory') _mime = 'directory';

			var type  = _mime.toLowerCase().split('/');
			var image = type[0] == 'egw' ? this.image('navbar',type[1]) : undefined;

			if (image)
			{

			}
			else if (typeof _path == 'string' && (type[0] == 'image' && type[1].match(/^(png|jpe?g|gif|bmp)$/) ||
				type[0] == 'application' && (
					// Open Document
					type[1].indexOf('vnd.oasis.opendocument.') === 0 ||
					// PDF
					type[1] == 'pdf' ||
					// Microsoft
					type[1].indexOf('vnd.openxmlformats-officedocument.') === 0
				)
			))
			{
				var params = { 'path': _path, 'thsize': this.config('link_list_thumbnail') || 64};
				if (_mtime) params.mtime = _mtime;
				image = this.link('/api/thumbnail.php', params);
			}
			// for svg return image itself
			else if (type[0] == 'image' && type[1] == 'svg+xml')
			{
				image = this.webserverUrl+'/webdav.php'+_path;
			}
			else
			{
				if ((typeof type[1] == 'undefined' || !(image = this.image('mime'+_size+'_'+type[0]+'_'+type[1], 'etemplate')) &&
					!(typeof mime_alias_map[_mime] != 'undefined' && (image=this.mime_icon(mime_alias_map[_mime], _path, _size, _mtime)))) &&
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
			icon = document.createElement('img');
			if (_url) icon.src = _url;
			if (_alt) icon.alt = _alt;
			return icon;
		}
	};
});

