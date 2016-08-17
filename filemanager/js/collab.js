/**
 * EGroupware - Filemanager - Collab editor application object
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Hadi Nategh <hn-AT-stylite.de>
 * @copyright (c) 2016 Stylite AG
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/*egw:uses
	/filemanager/js/collab_config.js;
	/api/js/webodf/collab/dojo-amalgamation.js;
	/api/js/webodf/collab/webodf.js;
	/api/js/webodf/collab/wodocollabtexteditor.js;
	/filemanager/js/app.js;
*/

/**
 * UI for filemanager collab
 *
 * @augments AppJS
 */
app.classes.filemanager = app.classes.filemanager.extend({
	/*
	 * @var editor odf editor object
	 */
	editor: {},

	/**
	 * @var regexp for acceptable mime types
	 */
	editor_mime: RegExp(/application\/vnd\.oasis\.opendocument\.text/),

	/**
	 * @var collab_server server object
	 */
	collab_server: {},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		delete this.editor;
		delete editor_mime;
		delete collab_server;
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 * @param {string} name template name
	 */
	et2_ready: function(et2,name)
	{
		// call parent
		this._super.apply(this, arguments);

		if (name == "filemanager.editor")
		{
			// need to make body rock solid to avoid extra scrollbars
			jQuery('body').css({overflow:'hidden'});
			var self = this;
			jQuery(window).on('unload', function(){self.editor_leaveSession()});
			this._init_odf_collab_editor ();
		}
	},

	/**
	 * Initiate odf editor popup & load given file_path
	 *
	 */
	_init_odf_editor: function ()
	{
		var widgetFilePath = this.et2.getWidgetById('file_path'),
			file_path = widgetFilePath.value,
			isNew = file_path == '/api/js/webodf/template.odt'? true: false,
			self = this;

		var onEditorCreated = function (err ,editor)
		{
			if (err)
			{
				console.log('Something went wrong whilst loading editor.'+ err);
				return;
			}
			self.editor = editor;
			self.editor.openDocumentFromUrl(egw.webserverUrl+file_path);
			if (isNew) {
				widgetFilePath.set_value('');
			}
		};

		var editorOptions = {
			allFeaturesEnabled: true,
			userData: {
				fullName: egw.user('account_fullName'),
				color: 'blue'
			}
		};

		var editor = this.et2.getWidgetById('odfEditor');
		if (editor)
		{
			Wodo.createTextEditor('filemanager-editor_odfEditor', editorOptions, onEditorCreated);
		}
	},

	/**
	 * Initiate odf collab editor popup & load given file_path as active session
	 */
	_init_odf_collab_editor: function ()
	{

		var widgetFilePath = this.et2.getWidgetById('file_path'),
			file_path = widgetFilePath.value;


		var serverOptions = {
			"serverParams": {
					url:egw.link('/index.php?', {
						menuaction: 'filemanager.filemanager_collab.poll'
					}),
					genesisUrl:egw.webserverUrl+file_path
				},
			"sessionId": this.editor_getSessionId(),
			editorOptions: {
				allFeaturesEnabled: true,
				userData: {
					fullName: egw.user('account_fullName'),
					color: 'blue'
				}
			}
		};
		var editor = this.et2.getWidgetById('odfEditor');
		if (editor)
		{
			this.create_collab_editor(serverOptions);
		}
	},

	/**
	 * Function to leave the current editing session
	 * and as result it will call client-side and server leave session.
	 */
	editor_leaveSession: function ()
	{
		this.editor.leaveSession(function(){});
		this.collab_server.server.leaveSession(this.collab_server.es_id, this.collab_server.memberid);
	},

	/**
	 * Method to close an opened document
	 *
	 * @param {object} _egwAction egw action object
	 * @param {function} _callback callback function gets called after close operation
	 */
	editor_close: function (_egwAction, _callback) {
		var self = this,
			action = _egwAction.id,
			callback = _callback;

		if (this.editor)
		{
			var closeFn = function ()
			{
				self.editor_leaveSession();
				if (action != 'new')
				{
					window.close();
				}
				callback.call(this);
			};

			// warn user about unsaved changes
			if (this.editor.isDocumentModified())
			{
				et2_dialog.show_dialog(
					function(_btn)
					{
						if (_btn == 2)
						{
							closeFn();
						}
					},
					'There are unsaved changes. Are you sure you want to close this document without saving them?',
					'unsaved changes',
					null,
					et2_dialog.BUTTONS_YES_NO,
					et2_dialog.WARNING_MESSAGE
				);
			}
			else
			{
				closeFn();
			}
		}
	},

	/**
	 * Method call for saving edited document
	 *
	 * @param {object} _egwAction egw action object
	 */
	editor_save: function (_egwAction) {
		var self = this,
			widgetFilePath = this.et2.getWidgetById('file_path'),
			file_path = widgetFilePath.value;

		if (this.editor)
		{
			function saveByteArrayLocally(err, data) {
				if (err) {
					alert(err);
					return;
				}

				var filename = file_path.split('/webdav.php'),
					blob = new Blob([data.buffer], {type: self.editor_mime});

				self.editor_file_operation({
						url: egw.webserverUrl+file_path,
						method: 'PUT',
						processData: false,
						success: function(data) {
							egw(window).message(egw.lang('Document %1 successfully has been saved.', filename[1]));
							self.editor.setDocumentModified(false);
							egw.json('filemanager.filemanager_collab.ajax_actions',[self.editor_getSessionId(), 'save']).sendRequest();
						},
						error: function () {},
						data: blob,
						mimeType: self.editor_mime
				});
			}

			//existed file
			if (file_path != '' && _egwAction.id != 'saveas') {
				this.editor.getDocumentAsByteArray(saveByteArrayLocally);
			}
			// new file
			else
			{
				// create file selector
				var vfs_select = et2_createWidget('vfs-select', {
					id:'savefile',
					mode: 'saveas',
					button_caption:"",
					button_label:_egwAction.id == 'saveas'?"save as":"save",
					value: "doc.odt"
				}, this.et2);

				// bind change handler for setting the selected path and calling save
				jQuery(vfs_select.getDOMNode()).on('change', function (){
					file_path = '/webdav.php'+vfs_select.get_value();
					if (vfs_select.get_value())
					{
						// Add odt extension if not exist
						if (!file_path.match(/\.odt$/,'ig')) file_path += '.odt';
						widgetFilePath.set_value(file_path);
						self.editor_leaveSession();
						self.editor.getDocumentAsByteArray(saveByteArrayLocally);
						self._init_odf_collab_editor();
						egw.refresh('','filemanager');
					}
				});
				// start the file selector dialog
				jQuery(vfs_select.getDOMNode()).click();
			}
		}
	},

	/**
	 * Method to delete loaded file in editor
	 * @param {type} _egwAction
	 */
	editor_delete: function (_egwAction) {
		var fullpath = this.et2.getWidgetById('file_path').value;
		fullpath = fullpath.split('/webdav.php')[1];
		var selected = fullpath.split('/');
		selected.pop();
		var path = selected.join('/');
		var self =this;

		et2_dialog.show_dialog(
			function(_btn)
			{
				if (_btn == 2)
				{
					self._do_action('delete', [fullpath], false, path);
					self.editor_close(_egwAction);
				}
			},
			egw.lang('Delete file %1?', path),
			'Delete file',
			null,
			et2_dialog.BUTTONS_YES_NO,
			et2_dialog.WARNING_MESSAGE
		);
	},

	/**
	 * Function to handle file operations (PGD) for editor
	 *
	 * @param {object} _params jquery ajax parameters
	 */
	editor_file_operation: function (_params)
	{
		var ajaxObj = {
			url: egw.webserverUrl+'/webdav.php?/home/'+egw.user('account_lid')+'/default.odt'
		};
		jQuery.extend(ajaxObj, _params);
		switch (ajaxObj && ajaxObj.cmd)
		{
			case 'PUT':
				jQuery.extend({},ajaxObj, {
					data: JSON.stringify(ajaxObj.data),
					contentType: 'application/json'
				});
				break;
			case 'GET':
				jQuery.extend({},ajaxObj, {
					dataType: 'json'
				});
				break;
			case 'DELETE':
				break;
		}
		jQuery.ajax(ajaxObj);
	},

	/**
	 * Function to check wheter selected file is editable. ATM only .odt is supported.
	 *
	 * @param {object} _egwAction egw action object
	 * @param {object} _senders object of selected row
	 *
	 * @returns {boolean} returns true if is editable otherwise false
	 */
	isEditable: function (_egwAction, _senders) {
		var data = egw.dataGetUIDdata(_senders[0].id),
			mime = this.et2._inst.widgetContainer.getWidgetById('$row');

		return data.data.mime.match(mime.mime_odf_regex)?true:false;
	},

	/**
	 * Function to generate session id
	 *
	 * @returns {String} retruns session id
	 */
	editor_getSessionId: function ()
	{
		var widgetFilePath = this.et2.getWidgetById('file_path'),
			file_path = widgetFilePath.value,
			es_id = egw.webserverUrl+file_path;
		return es_id;
	},

	/**
	 * Function to create collab editor
	 *
	 * @param {type} _args parameteres to be set for server factory and texteditor
	 */
	create_collab_editor: function (_args)
	{

		var serverFactory,
			server,
			serverParams = _args.serverParams,
			sessionId = _args.sessionId,
			editorOptions = jQuery.extend(_args.editorOptions,{networkSecurityToken:'', closeCallback:this.editor_close}),
			userId = egw.user('account_id'),
			memberId,
			self = this;

		/**
		 * Editor error handler function
		 * @param {type} e
		 */
		function handleEditingError (e)
		{
			console.log(e)
		};

		function onEditing ()
		{

		};

		/**
		 * Callback function which gets called after the collab editor is created
		 *
		 * @param {string} _err
		 * @param {object} _editor webodf collabtexteditor object
		 *
		 * @return {undefined} return undefined if something goes wrong
		 */
		function onEditorCreated (_err, _editor)
		{
			if (_err)
			{
				console.log('Something went wrong whilst loading editor.'+ _err);
				return;
			}
			self.editor = _editor;
			self.editor.addEventListener(Wodo.EVENT_UNKNOWNERROR, handleEditingError);
			self.editor.joinSession(serverFactory.createSessionBackend(sessionId, memberId, server), onEditing);

		};

		/**
		 * Function to join a doc session
		 *
		 * @param {type} _sessionId session id of the opened document
		 */
		function joinSession(_sessionId)
		{
			var sid = _sessionId;

			server.joinSession(userId, sid, function (_memberId) {
				memberId = _memberId;
				// Set server object for current session
				self.collab_server = {server:server, memberid: memberId, es_id: sid};

				if (Object.keys(self.editor).length == 0) {
					Wodo.createCollabTextEditor('filemanager-editor_odfEditor', editorOptions, onEditorCreated);
				} else {
					self.editor.joinSession(serverFactory.createSessionBackend(sid, _memberId, server), onEditing);
				}
			}, function(err) {
				console.log(err);
			});
		};

		require(["egwCollab/ServerFactory"], function (ServerFactory) {
			serverFactory = new ServerFactory();
			server = serverFactory.createServer(serverParams);
			server.connect(8000, function (state) {
				switch (state)
				{
					case "ready":
						joinSession(sessionId);
						break;
					case "timeout":
						console.log('did not connect to server because of timeout.')
						break;
					default:
						console.log('server is not available.')
				}
			});
		});
	}
});

