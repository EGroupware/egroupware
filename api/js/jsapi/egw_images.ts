/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

import './egw_core';

export interface ImagesModule
{
	/**
	 * Set imagemap, called from /api/images.php
	 *
	 * @param _images
	 * @param _need_clone _images need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_images(_images : object, _need_clone? : boolean) : void;

	/**
	 * Get image URL for a given image-name and application
	 *
	 * @param _name image-name without extension
	 * @param _app application name, default current app of window
	 * @return string with URL of image
	 */
	image(_name : string, _app? : string) : string;

	/**
	 * Get image url for a given mime-type and option file
	 *
	 * @param _mime
	 * @param _path vfs path to generate thumbnails for images
	 * @param _size defaults to 128 (only supported size currently)
	 * @param _mtime current modification time of file to allow infinit caching as url changes
	 * @returns url of image
	 */
	mime_icon(_mime : string, _path? : string, _size? : number, _mtime? : number) : string;

	/**
	 * Create DOM img or svn element depending on url
	 *
	 * @param _url source url
	 * @param _alt alt attribute for img tag
	 * @returns DOM node
	 */
	image_element(_url : string, _alt? : string) : HTMLImageElement;
}

declare global
{
	interface IegwGlobal extends ImagesModule
	{
	}
}

/**
 * Mapping some old formats to the newer form, or any other aliasing for mime-types
 *
 * Should be in sync with ../inc/class.mime_magic.inc.php
 */
const mime_alias_map : {[mime : string] : string} = {
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

class Images implements ImagesModule
{
	/**
	 * Map to serverside available images for users template-set
	 *
	 * @access: private, use egw.image(_name, _app)
	 */
	private images : any;

	/**
	 * Set imagemap, called from /api/images.php
	 */
	set_images = (_images : object, _need_clone? : boolean) : void =>
	{
		this.images = _need_clone ? (<any>jQuery).extend(true, {}, _images) : _images;
	}

	/**
	 * Get image URL for a given image-name and application
	 *
	 * Called as egw(app,wnd).image(...) - `this` must stay dynamically bound
	 * to whichever instance called it (reads this.getAppName()/this.webserverUrl,
	 * both per-instance), hence a plain `function` field rather than an arrow
	 * field. `self` captures this Images instance itself via closure, for
	 * reaching its own private `images` state.
	 */
	image = ((self : Images) => function(this : any, _name : string, _app? : string) : string
	{
		// For logging all paths tried
		var tries : {[key : string] : any} = {};

		if (!self.images)
		{
			console.log("calling egw.image('"+_name+"', '"+_app+"') before egw.set_images() returning null");
			return null;
		}
		if(!_name) return null;

		if (typeof _app === 'undefined')
		{
			// If the application name is not given, set it to the name of
			// current application
			_app = this.getAppName();
		}

		// Handle images in appname/imagename format
		if(_name.indexOf('/') > 0)
		{
			var split = _name.match(/^([^/]+)\/(.*)$/);
			// e.g. dhtmlxtree and egw_action are subdirs in image dir, not applications
			if (typeof self.images[split[1]] !== 'undefined')
			{
				_app = split[1];
				_name = split[2];
			}
		}

		// own instance specific images in vfs have the highest precedence
		tries.vfs = _name;
		if (typeof self.images.vfs !== 'undefined' && typeof self.images.vfs[_name] === 'string')
		{
			return this.webserverUrl+self.images.vfs[_name];
		}
		if (typeof self.images.global !== 'undefined' && (_name !== 'navbar' || _app === 'api'))
		{
			tries.global = '('+_app+'/)'+_name;
			let replace = self.images.global[_app+'/'+_name] || self.images.global[_name];
			if (replace)
			{
				if (typeof self.images.bootstrap[replace] === 'string')
				{
					return this.webserverUrl+self.images.bootstrap[replace];
				}
				const parts = replace.split('/');
				if (parts.length > 1)
				{
					_app = parts.shift();
					_name = parts.join('/');
				}
				else
				{
					_name = replace;
				}
			}
		}
		tries[_app + (_app == 'phpgwapi' ? " (current app)" : "")] = _name;
		if (typeof self.images[_app] !== 'undefined' && typeof self.images[_app][_name] === 'string')
		{
			return this.webserverUrl+self.images[_app][_name];
		}
		tries.bootstrap = _name;
		if (typeof self.images.bootstrap !== 'undefined' && typeof self.images.bootstrap[_name] === 'string')
		{
			return this.webserverUrl+self.images.bootstrap[_name];
		}
		tries.api = _name;
		if (typeof self.images.api !== 'undefined' && typeof self.images.api[_name] === 'string')
		{
			return this.webserverUrl+self.images.api[_name];
		}
		// if no match, check if it might contain an extension
		var matches = _name.match(/\.(png|gif|jpg)$/i);
		if (matches)
		{
			return this.image(_name.replace(/.(png|gif|jpg)$/i,''), _app);
		}
		if(matches != null) tries[_app + " (matched)"]= matches;
		if(_name && _name !== "undefined")
		{
			egw.debug("log", 'egw.image("' + _name + '", "' + _app + '") image NOT found!  Tried ', tries);
		}
		return null;
	})(this);

	/**
	 * Get image url for a given mime-type and option file
	 *
	 * Called as egw(app,wnd).mime_icon(...) - `this` must stay dynamically
	 * bound to whichever instance called it (reads this.config(...)/
	 * this.webserverUrl/this.link(...), calls this.image(...)/this.mime_icon(...)
	 * recursively which must dispatch through the same `this`), hence a plain
	 * `function` field. Doesn't touch any private module state, so no `self`
	 * capture is needed here - mime_alias_map is a plain module-scope const.
	 */
	mime_icon = function(this : any, _mime : string, _path? : string, _size? : number, _mtime? : number) : string
	{
		if (typeof _size == 'undefined') _size = 128;
		if (!_mime) _mime = 'unknown';
		if (_mime == 'httpd/unix-directory') _mime = 'directory';

		if (typeof _path == 'string' && _mime === 'directory')
		{
			const path_parts = _path.split('/');
			if (path_parts.length === 3 && (path_parts[1] === 'apps' || path_parts[1] === 'templates'))
			{
				_mime = 'egw/'+path_parts[2];
			}
		}

		var type  = _mime.toLowerCase().split('/');
		var image : any = type[0] == 'egw' ? this.image('navbar',type[1]) : undefined;

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
			var params : {path : string, thsize : any, mtime? : number} = { path: _path, thsize: this.config('link_list_thumbnail') || 64};
			if (_mtime) params.mtime = _mtime;
			image = this.link('/api/thumbnail.php', params);
		}
		// for svg return image itself
		else if (type[0] == 'image' && type[1] == 'svg+xml' && typeof _path == "string")
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
	}

	/**
	 * Create DOM img or svn element depending on url
	 */
	image_element = (_url : string, _alt? : string) : HTMLImageElement =>
	{
		var icon : HTMLImageElement;
		icon = document.createElement('img');
		if (_url) icon.src = _url;
		if (_alt) icon.alt = _alt;
		return icon;
	}
}

egw.extend('images', egw.MODULE_GLOBAL, () => new Images());
