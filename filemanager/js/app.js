/**
 * EGroupware - Filemanager - Javascript actions
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

app.filemanager = AppJS.extend(
{
	appname: 'filemanager',
	/**
	 * et2 widget container
	 */
	et2: null,
	/**
	 * path widget
	 */
	path_widget: null,
	/**
	 * Array of pathes from files in clipboard
	 */
	clipboard_files: [],
	/**
	 * Are files cut into clipboard - need to be deleted at source on paste
	 */
	clipboard_is_cut: false,
	/**
	 * Regexp to convert id to a path
	 */
	remove_prefix: /^filemanager::/,
	/**
	 * Key for storing clipboard in browser localstorage
	 */
	clipboard_localstore_key: 'filemanager.clipboard',

	/**
	 * Constructor
	 */
	init: function() 
	{
		// call parent
		this._super.apply(this, arguments);
		
		//window.register_app_refresh("mail", this.app_refresh);
	},
	
	/**
	 * Destructor
	 */
	destroy: function()
	{
		delete this.et2;
		// call parent
		this._super.apply(this, arguments);
	},
	
	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 */
	et2_ready: function(et2)
	{
		// call parent
		this._super.apply(this, arguments);

		this.et2 = et2.widgetContainer;
		this.path_widget = this.et2.getWidgetById('path');
		
		// get clipboard from browser localstore and update button tooltips
		if (typeof window.localStorage != 'undefined' && typeof window.localStorage[this.clipboard_localstore_key] != 'undefined')
		{
			this.clipboard_files = JSON.parse(window.localStorage[this.clipboard_localstore_key]);
		}
		this.clipboard_tooltips();
	},

	/**
	 * Refresh given application _targetapp display of entry _app _id, incl. outputting _msg
	 * 
	 * Default implementation here only reloads window with it's current url with an added msg=_msg attached
	 * 
	 * @param string _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param string _app application name
	 * @param string|int _id=null id of entry to refresh
	 * @param string _type=null either 'edit', 'delete', 'add' or null
	 */
	/*app_refresh: function(_msg, _app, _id, _type)
	{
		$j(document.getElementById('nm[msg]')).text(_msg);
		
		if (_app == this.appname)
		{
			this.et2.getWidgetById('nm').applyFilters();
		}
	},*/

	/**
	 * Open compose with already attached files
	 * 
	 * @param attachments
	 */
	open_mail: function(attachments)
	{
		if (typeof attachments == 'undefined') attachments = clipboard_files;
		var params = {};
		if (!(attachments instanceof Array)) attachments = [ attachments ];
		for(var i=0; i < attachments.length; i++)
		{
		   params['preset[file]['+i+']'] = 'vfs://default'+attachments[i].replace(this.remove_prefix,'');
		}
		egw.open('', 'felamimail', 'add', params);
	},
	
	/**
	 * Mail files action: open compose with already attached files
	 * 
	 * @param _action
	 * @param _elems
	 */
	mail: function(_action, _elems)
	{
		var ids = [];
		for (var i = 0; i < _elems.length; i++)
		{
			ids.push(_elems[i].id);
		}
		this.open_mail(ids);
	},

	/**
	 * Check if file would overwrite a file on server
	 * 
	 * @param upload
	 * @param path_id
	 */
	check_files: function(upload, path_id)
	{
		var files = [];
		if (upload.files)
		{
			for(var i = 0; i < upload.files.length; ++i)
			{
				files.push(upload.files[i].name || upload.files[i].fileName);
			}
		}
		else if (upload.value)
		{
			files = upload.value;
		}
		var path = document.getElementById(path_id ? path_id : upload.id.replace(/upload\]\[/,"nm][path"));
	
		xajax_doXMLHTTP("filemanager_ui::ajax_check_upload_target",upload.id, files, path.value);
	},
	
	/**
	 * Update clickboard tooltips in 
	 */
	clipboard_tooltips: function()
	{
		var paste_buttons = ['button[paste]', 'button[linkpaste]', 'button[mailpaste]'];
		for(var i=0; i < paste_buttons.length; ++i)
		{
			var button = this.et2.getWidgetById(paste_buttons[i]);
			button.set_statustext(this.clipboard_files.join(",\n"));
		}
	},
	
	/**
	 * Clip files into clipboard
	 * 
	 * @param _action
	 * @param _elems
	 */
	clipboard: function(_action, _elems)
	{
		this.clipboard_is_cut = _action.id == "cut";
		if (_action.id != "add") this.clipboard_files = [];
	
		for (var i = 0; i < _elems.length; i++)
		{
			var id = _elems[i].id.replace(this.remove_prefix, '');
			this.clipboard_files.push(id);
		}
		this.clipboard_tooltips();
		
		// update localstore with files
		if (typeof window.localStorage != 'undefined')
		{
			window.localStorage[this.clipboard_localstore_key] = JSON.stringify(this.clipboard_files);
		}
	},
	
	/**
	 * Paste files into current directory or mail them
	 * 
	 * @param _type 'paste', 'linkpaste', 'mailpaste'
	 */
	paste: function(_type)
	{
		switch(_type)
		{
			case 'mailpaste':
				this.open_mail(this.clipboard_files);
				break;
				
			case 'linkpaste':
			case 'paste':
				alert('Not yet implemented!');
		}
	},
	
	/**
	 * Force download of a file by appending '?download' to it's download url
	 * 
	 * @param _action
	 * @param _senders
	 */
	force_download: function(_action, _senders)
	{
		var data = egw.dataGetUIDdata(_senders[0].id);
		var url = data ? data.data.download_url : '/webdav.php'+_senders[0].id.replace(this.remove_prefix,'');
		if (url[0] == '/') url = egw.link(url);
		window.location = url+"?download";
	},
	
	/**
	 * Change directory
	 * 
	 * @param _dir directory to change to incl. '..' for one up
	 */
	change_dir: function(_dir)
	{
		switch (_dir)
		{
			case '..':
				_dir = this.path_widget.getValue();
				if (_dir != '/')
				{
					var parts = _dir.split('/');
					parts.pop();
					_dir = parts.length > 1 ? parts.join('/') : '/';
				}
				break;
			case '~':
				_dir = this.et2.getWidgetById('nm').options.settings.home_dir;
				break;
		}
		this.path_widget.set_value(_dir);
		this.path_widget.change();
	},
	
	/**
	 * Open/active an item
	 * 
	 * @param _action
	 * @param _senders
	 */
	open: function(_action, _senders)
	{
		var data = egw.dataGetUIDdata(_senders[0].id);
		var path = _senders[0].id.replace(this.remove_prefix, '');
		if (data.data.mime == 'httpd/unix-directory')
		{
			this.change_dir(path);
		}
		else
		{
			egw.open(path, 'file');
		}
	}
});