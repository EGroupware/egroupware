/**
 * eGroupWare eTemplate2 - JS VFS widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 * @version $Id$
*/

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

/**
 * Class which implements the "vfs" XET-Tag
 */
var et2_vfs = et2_valueWidget.extend({

	attributes: {
		"value": {
			"type": "any", // Object
			"description": "Label of the button"
		}
	},

	/**
	 * Mime type of directories
	 */
	DIR_MIME_TYPE: 'httpd/unix-directory',

	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("ul"))
			.addClass('et2_vfs');

		this.setDOMNode(this.span[0]);
	},

	set_value: function(_value) {
		var path = '';
		if(_value.path)
		{
			path = _value.path
		}
		else if (typeof _value !== 'object')
		{
			this.egw().debug("warn", "%s only has path, needs full array", this.id);
			this.span.empty().text(_value);
			return;
		}
		var path_parts = path ? path.split('/') : [];

		// TODO: This is apparently not used in old etemplate
		//this._make_path(path_parts);
		this._make_path([path_parts[path_parts.length-1]]);

		// TODO: check perms
		if(_value.mime && _value.mime == this.DIR_MIME_TYPE)
		{
		}
		// Make it clickable
		var data = {path: path, type: _value.mime}
		jQuery("li",this.span).addClass("et2_clickable et2_link")
			.click({data:data, egw: this.egw()}, function(e) {
			e.data.egw.open(e.data.data, "file");
		});
	},

	/**
	 * Create a list of clickable path components
	 */
	_make_path: function(path_parts) {
		for(var i = 0; i < path_parts.length; i++)
		{
			var text = path_parts[i];

			// Nice human-readable stuff for apps
			if(path_parts[1] == 'apps')
			{
				switch(path_parts.length)
				{
					case 2:
						if(i == 1)
						{
							text = this.egw().lang('applications');
						}
						break;
					case 3:
						if( i == 2)
						{
							text = this.egw().lang(path_parts[2]);
						}
						break;
					case 4:
						if(!isNaN(text))
						{
							text = this.egw().link_title(path_parts[2],path_parts[3]);
						}
						break;
				}
			}
			path_parts[i] = text;

			var part = $j(document.createElement("li"))
				.addClass("vfsFilename")
				.text(text)
				.appendTo(this.span);
		}
	}

});

et2_register_widget(et2_vfs, ["vfs", "vfs-name", "vfs-size", "vfs-mode","vfs-mime","vfs-uid","vfs-gid"]);
