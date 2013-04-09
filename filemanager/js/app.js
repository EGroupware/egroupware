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
	 * Initialize javascript for this application
	 */
	init: function() 
	{
		this._super.apply(this,arguments);

		//window.register_app_refresh("mail", this.app_refresh);
	}/*,
	
	remove_prefix: /^filemanager::/,
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
	
	mail: function(_action, _elems)
	{
		var ids = [];
		for (var i = 0; i < _elems.length; i++)
		{
			ids.push(_elems[i].id);
		}
		this.open_mail(ids);
	},

	setMsg: function(_msg)
	{
		$j(document.getElementById('nm[msg]')).text(_msg);
	},
	
	clipboard_files: [],
	
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
	
	clipboard_tooltip: function(link)
	{
		xajax_doXMLHTTP("filemanager_ui::ajax_clipboard_tooltip", link.id);
	
		window.setTimeout(UnTip, 3000);
	},
	
	clipboard: function(_action, _elems)
	{
		if (_action.id != "cut") clipboard_files = [];
	
		var ids = [];
		for (var i = 0; i < _elems.length; i++)
		{
			clipboard_files.push(_elems[i].id);
			ids.push(_elems[i].id);
		}
		xajax_doXMLHTTP("filemanager_ui::ajax_clipboard", _action.id, ids);
	},
	
	force_download: function(_action, _senders)
	{
		var data = egw.dataGetUIDdata(_senders[0].id);
		var url = data ? data.data.download_url : '/webdav.php'+_senders[0].id.replace(this.remove_prefix,'');
		if (url[0] == '/') url = egw.link(url);
		window.location = url+"?download";
	}*/
});