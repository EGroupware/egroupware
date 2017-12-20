/**
 * EGroupware - Collabeditor application object
 *
 * @link http://www.egroupware.org
 * @package Collabeditor
 * @author Hadi Nategh <hn-AT-egroupware.de>
 * @copyright (c) 2016 by Hadi Nategh <hn-AT-egroupware.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/*egw:uses
	/collabeditor/js/collab_config.js;
	/collabeditor/js/webodf/collab/dojo-amalgamation.js;
	/collabeditor/js/webodf/collab/webodf.js;
	/collabeditor/js/webodf/collab/wodocollabtexteditor.js;
	/filemanager/js/app.js;
*/

app.classes.filemanager = app.classes.filemanager.extend(
{

	/**
	 * Function to check wheter selected file is editable. ATM only .odt is supported.
	 *
	 * @param {object} _egwAction egw action object
	 * @param {object} _senders object of selected row
	 *
	 * @returns {boolean} returns true if is editable otherwise false
	 */
	isEditable: function (_egwAction, _senders) {
		if (_senders.length>1) return false;
		var data = egw.dataGetUIDdata(_senders[0].id),
			mime = this.et2._inst.widgetContainer.getWidgetById('$row');

		return data.data.mime.match(mime.mime_odf_regex)?true:false;
	}
});

/**
 * UI for collabeditor
 *
 * @augments AppJS
 */
app.classes.collabeditor = AppJS.extend({
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

		if (name == "collabeditor.editor")
		{
			// need to make body rock solid to avoid extra scrollbars
			jQuery('body').css({overflow:'hidden'});
			// Remove app header to be able to see the toolbar in compact template
			jQuery('#divAppboxHeader').remove();
			var self = this;
			jQuery(window).on('unload', function(){self.editor_leaveSession();});
			jQuery(window).on('beforeunload', function(){
				if (!self.collab_server.close)
				{
					return true;
				}
				else
				{
					return ;
				}
			});
			this._init_odf_collab_editor ();
		}
	},

	/**
	 * Initiate odf collab editor popup & load given file_path as active session
	 * editors options:
	 *		directParagraphStylingEnabled
	 *		paragraphStyleSelectingEnabled
	 *		paragraphStyleEditingEnabled
	 *		zoomingEnabled
	 *		directTextStylingEnabled
	 *		imageEditingEnabled
	 *		hyperlinkEditingEnabled
	 *		annotationsEnabled
	 *		unstableFeaturesEnabled
	 *		reviewModeEnabled
	 */
	_init_odf_collab_editor: function ()
	{
		var	self = this,
			isNew = window.location.href.search(/&path=/) == -1?true:false;
		egw.json('collabeditor.EGroupware\\collabeditor\\Ui.ajax_getGenesisUrl',[this.editor_getFilePath(), isNew], function (_data){
			var serverOptions = {
				serverParams: {
						url:egw.link('/index.php?', {
							menuaction: 'collabeditor.EGroupware\\collabeditor\\Ui.poll'
						}),
						genesisUrl:_data.genesis_url
					},
				sessionId: _data.es_id,
				editorOptions: {
					directParagraphStylingEnabled:true,
					paragraphStyleSelectingEnabled:true,
					paragraphStyleEditingEnabled:true,
					zoomingEnabled: true,
					directTextStylingEnabled:true,
					imageEditingEnabled:true,
					hyperlinkEditingEnabled:true,
					annotationsEnabled:true,
					unstableFeaturesEnabled:true,
					// review has to be explicitly disabled to be able to edit the document
					reviewModeEnabled:false,
					viewOptions:{
						editInfoMarkersInitiallyVisible: true,
						caretAvatarsInitiallyVisible: false,
						caretBlinksOnRangeSelect: true
					}
				}
			};
			var editor = self.et2.getWidgetById('odfEditor');
			if (editor)
			{
				self.create_collab_editor(serverOptions);
			}
		}).sendRequest();
	},

	/**
	 * Method to create a new document
	 * @param {object} _action either action or node
	 * @param {object} _selected either widget or selected row
	 *
	 * @return {boolean} returns true
	 */
	create_new: function (_action, _selected) {
		var fe = egw.link_get_registry('filemanager-editor');
		if (fe && fe.edit)
		{
			egw.open_link(egw.link('/index.php', {
				menuaction: fe.edit.menuaction
			}), '', fe.popup_edit);
		}
		return true;
	},
	
	/**
	 * Function to leave the current editing session
	 * and as result it will call client-side and server leave session.
	 *
	 * @param {function} _successCallback function to gets called after leave session is successful
	 * @param {boolean} _checkLastActive flag to set for checking if the user is the last
	 * one in the open sessions.
	 */
	editor_leaveSession: function (_successCallback,_checkLastActive)
	{
		var self = this;
		var successCallback = _successCallback  || function(){window.close();};
		var leave = function ()
		{
			self.editor.leaveSession(function(){});
			self.collab_server.server.leaveSession(self.collab_server.es_id, self.collab_server.memberid, successCallback);
			self.collab_server.close = true;
		};
		if (!_checkLastActive)
		{
			leave();
		}
		egw.json('collabeditor.EGroupware\\collabeditor\\Ui.ajax_actions',[{'es_id':this.collab_server.es_id, 'member_id':this.collab_server.memberid},'checkLastMember'], function(_isLastMember){
			if (_isLastMember)
			{
				var buttons = [
					{"button_id": 2,"text": 'save and close', id: 'save', image: 'check' },
					{"button_id": 1,"text": 'discard unsaved changes', id: 'discard', image: 'discard' },
					{"button_id": 0,"text": 'cancel', id: 'cancel', image: 'cancel', "default":true}
				];
				et2_dialog.show_dialog(
					function(_btn)
					{
						switch (_btn)
						{
							case 'save':
								self.editor_save({id:'save'}, function(){
									leave();
								});
								break;
							case 'discard':
								self.editor_discard();
								break;
							default:

						}
					},
					egw.lang('You are the last one on this session. What would you like to do with all unsaved changes in this document?'),
					'Closing session',
					null,
					buttons,
					et2_dialog.WARNING_MESSAGE
				);
			}
			else
			{
				leave();
			}
		}).sendRequest();

	},

	/**
	 * Method to close an opened document
	 */
	editor_close: function () {
		var self = this,
			file_path = this.et2.getWidgetById('file_path');

		if (this.editor)
		{
			var closeFn = function (_checkLastActive)
			{
				self.editor_leaveSession(null, _checkLastActive);
			};

			// it's an unsaved new file try to warn user about unsaved changes
			if (file_path.value == '')
			{
				et2_dialog.show_dialog(
					function(_btn)
					{
						if (_btn == 2)
						{
							closeFn(false);
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
				closeFn(true);
			}
		}
	},

	/**
	 * Method call for saving edited document
	 *
	 * @param {object} _egwAction egw action object
	 * @param {function} _afterSaveCallback callback function to be called after
	 * save action happens.
	 *
	 */
	editor_save: function (_egwAction, _afterSaveCallback) {
		var self = this,
			widgetFilePath = this.et2.getWidgetById('file_path'),
			file_path = widgetFilePath.value,
			afterSaveCallback = _afterSaveCallback || function(){};


		if (this.editor)
		{
			function saveByteArrayLocally(err, data) {
				if (err) {
					alert(err);
					return;
				}

				var blob = new Blob([data.buffer], {type: self.editor_mime});

				self.editor_file_operation({
						url: egw.webserverUrl+'/webdav.php'+file_path,
						method: 'PUT',
						processData: false,
						success: function(data) {
							egw(window).message(egw.lang('Document %1 has been saved successfully.', file_path));
							self.editor.setDocumentModified(false);
							if (_egwAction.id == 'saveas') return;
							egw.json('collabeditor.EGroupware\\collabeditor\\Ui.ajax_actions',[{'es_id':self.collab_server.es_id, 'file_path': egw.webserverUrl+'/webdav.php'+file_path}, 'save'], function(){
								afterSaveCallback.call(self,{});
							}).sendRequest();
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

				var vfs_attrs = {
					id:'savefile',
					mode: 'saveas',
					button_caption:"",
					button_label:_egwAction.id == 'saveas'?"save as":"save",
					value: "doc.odt"
				};
				// create file selector
				var vfs_select = et2_createWidget('vfs-select', vfs_attrs, this.et2);

				jQuery(vfs_select.getDOMNode()).on('change', function (){
					file_path = vfs_select.get_value();
					if (vfs_select.get_value())
					{
						// Add odt extension if not exist
						if (!file_path.match(/\.odt$/,'ig')) file_path += '.odt';
						widgetFilePath.set_value(file_path);
						self.editor.getDocumentAsByteArray(saveByteArrayLocally);
						self.editor_leaveSession(function(){
							var path = window.location.href.split('&path=');
							window.location.href = path[0]+'&path='+self.editor_getFilePath();
						});
						egw.refresh('','filemanager');
					}
				});
				// start the file selector dialog
				vfs_select.click();
			}
		}
	},

	/**
	 * Method to delete loaded file in editor
	 * @param {type} _egwAction
	 */
	editor_delete: function (_egwAction) {
		var fullpath = this.et2.getWidgetById('file_path').value;
		var selected = fullpath.split('/');
		selected.pop();
		var path = selected.join('/');
		var self =this;

		et2_dialog.show_dialog(
			function(_btn)
			{
				if (_btn == 2)
				{
					self._do_action('delete', [fullpath,path+'/.'+self.collab_server.es_id+'.webodf.odt'], false, path);
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
	 * Function to get full file path
	 *
	 * @returns {String} retruns file path
	 */
	editor_getFilePath: function ()
	{
		var widgetFilePath = this.et2.getWidgetById('file_path'),
			file_path = widgetFilePath.value,
			path = egw.webserverUrl+'/webdav.php'+file_path;
		return path;
	},

	/**
	 * This function gets called after discard action to
	 * notify particioant to join to the new session or
	 * save as the document to not lose changes.
	 *
	 */
	editor_discarded: function ()
	{
		var self = this;
		var buttons = [
			{"button_id": 1,"text": 'reload', id: 'reload', image: 'check' },
			{"button_id": 0,"text": 'save as', id: 'save', image: 'cancel', "default":true}
		];
		et2_dialog.show_dialog(
			function(_btn)
			{
				if (_btn == 'save')
				{
					self.editor_save({id:'saveas'});
				}
				else if (_btn == 'reload')
				{
					self.collab_server.close = true;
					window.location.reload();
				}
			},
			egw.lang('This session is not valid anymore! Save as your local changes if you need them or reload to join new session.'),
			'Delete file',
			null,
			buttons,
			et2_dialog.WARNING_MESSAGE
		);
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
			editorOptions = jQuery.extend(_args.editorOptions,{}),
			userId = egw.user('account_id'),
			memberId,
			self = this;

		/**
		 * Editor error handler function
		 *
		 * this function also been used in order to notify
		 * participant about session changes.
		 *
		 * @param {string} e
		 */
		function handleEditingError (e)
		{
			switch (e)
			{
				// This type of error happens when the session is discarded or
				// the document has been deleted and all records in database's been removed.
				case 'sessionDoesNotExist':
					this.editor_discarded();
					break;
				default:
					console.log(e);
			}
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
			self.editor.addEventListener(Wodo.EVENT_UNKNOWNERROR, jQuery.proxy(handleEditingError, self));
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
					Wodo.createCollabTextEditor('collabeditor-editor_odfEditor', editorOptions, onEditorCreated);
				} else {
					self.editor.joinSession(serverFactory.createSessionBackend(sid, _memberId, server), onEditing);
				}
			}, function(err) {
				console.log(err);
			});
		};

		webodfModule.require(["egwCollab/ServerFactory"], function (ServerFactory) {
			serverFactory = new ServerFactory();
			server = serverFactory.createServer(serverParams);
			server.connect(8000, function (state) {
				switch (state)
				{
					case "ready":
						joinSession(sessionId);
						break;
					case "timeout":
						console.log('did not connect to server because of timeout.');
						break;
					default:
						console.log('server is not available.');
				}
			});
		});
	},

	_do_action_callback:function(_data)
	{
		this._super.apply(this,arguments);

		switch(_data.action)
		{
			case 'delete':
				if (!_data.errs) egw.json('collabeditor.EGroupware\\collabeditor\\Ui.ajax_actions', [{'es_id':this.collab_server.es_id}, 'delete'], function(){window.close();}).sendRequest();
		}
	},

	/**
	 * Discard stacked modification in session from all participants
	 * it will warn user about the consequences which would be removing
	 * all stored OP modfifications in DB. Then as result it will notify
	 * other participants about the action and prompt them to reload the
	 * session or save as the current session if they want to keep their
	 * changes.
	 *
	 */
	editor_discard: function ()
	{
		var self = this;
		var buttons = [
			{"button_id": 1,"text": 'discard', id: 'discard', image: 'discard' },
			{"button_id": 0,"text": 'cancel', id: 'cancel', image: 'cancel', "default":true}
		];
		et2_dialog.show_dialog(
			function(_btn)
			{
				if (_btn == 'discard')
				{
					egw.json('collabeditor.EGroupware\\collabeditor\\Ui.ajax_actions',[{'es_id': self.collab_server.es_id}, 'discard'], function(){
						self.collab_server.close = true;
						window.location.reload();
					}).sendRequest();
				}
			},
			egw.lang('Are you sure that you want to discard all modifications applied to this document by you and other participants?'),
			'Discard all changes',
			null,
			buttons,
			et2_dialog.WARNING_MESSAGE
		);
	}
});

