/**
 * mail - static javaScript functions
 *
 * @link http://www.egroupware.org
 * @author klaus leithoff <kl at stylite.de>
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for mail
 *
 * @augments AppJS
 */
app.mail = AppJS.extend(
{
	appname: 'mail',

	/**
	 * et2 widget container
	 */
	et2: null,
	doStatus: null,
	mail_doTimedRefresh: false,

	mail_refreshTimeOut: 1000*60*3, // initial call
	mail_queuedFolders: [],
	mail_queuedFoldersIndex: 0,

	mail_selectedMails: [],
	mail_currentlyFocussed: '',
	mail_previewAreaActive: true, // we start with the area active

	nm_index: 'nm', // nm nome of index
	mail_fileSelectorWindow: null,
	mail_isMainWindow: true,

	/**
	 * Initialize javascript for this application
	 *
	 * @memberOf mail
	 */
	init: function() {
		this._super.apply(this,arguments);
		window.register_app_refresh("mail", this.app_refresh);
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		delete this.et2;
		delete this.et2_obj;
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
		// call parent; somehow this function is called more often. (twice on a display and compose) why?
		this._super.apply(this, arguments);
		this.et2_obj = et2;
		this.et2 = et2.widgetContainer;
		var isMainView = false;
		var isDisplay = false;
		var isCompose = false;
		for (var t in et2.templates)
		{
			//alert(t); // as we iterate through this more than once, ... we separate trigger and action
			switch (t) {
				case 'mail.index':
					this.mail_isMainWindow = isMainView=true;
					break;
				case 'mail.display':
					this.mail_isMainWindow = false;
					isDisplay=true;
					break;
				case 'mail.compose':
					this.mail_isMainWindow = false;
					isCompose = true;
			}
		}
		//alert('action about to go down');
		if (isMainView)
		{
			this.mail_disablePreviewArea(true);
			this.mail_startTimerFolderStatusUpdate(this.mail_refreshTimeOut);
			//inital call of refresh folderstatus
			var self = this;
			window.setTimeout(function() {self.mail_refreshFolderStatus.apply(self);},1000);
		}
		if (isDisplay)
		{
			var subject = this.et2.getWidgetById('mail_displaysubject');
			var body = this.et2.getWidgetById('mail_displaybody');
			body.node.parentNode.style.top=subject.node.offsetTop+40+'px';
			var app_registry = egw.link_get_registry('mail');//this.appname);
			//console.log(app_registry);
			w=870;
			if (typeof app_registry['view'] != 'undefined' && typeof app_registry['view_popup'] != 'undefined' )
			{
				var w_h =app_registry['view_popup'].split('x');
				if (w_h[1] == 'egw_getWindowOuterHeight()') w_h[1] = (screen.availHeight>egw_getWindowOuterHeight()?screen.availHeight:egw_getWindowOuterHeight());
			}
			//alert('resizing to'+(w_h[0]?w_h[0]:870)+','+(w_h[1]?w_h[1]:egw_getWindowOuterHeight()));
			window.resizeTo((w_h[0]?w_h[0]:870),(w_h[1]?w_h[1]:(screen.availHeight>egw_getWindowOuterHeight()?screen.availHeight:egw_getWindowOuterHeight())));
		}
		if (isCompose)
		{
			var app_registry = egw.link_get_registry('mail');//this.appname);
			//console.log(app_registry);
			w=870;
			if (typeof app_registry['edit'] != 'undefined' && typeof app_registry['edit_popup'] != 'undefined' )
			{
				var w_h =app_registry['edit_popup'].split('x');
				if (w_h[1] == 'egw_getWindowOuterHeight()') w_h[1] = (screen.availHeight>egw_getWindowOuterHeight()?screen.availHeight:egw_getWindowOuterHeight());
			}
			//alert('resizing to'+(w_h[0]?w_h[0]:870)+','+(w_h[1]?w_h[1]:egw_getWindowOuterHeight()));
			window.resizeTo((w_h[0]?w_h[0]:870),(w_h[1]?w_h[1]:(screen.availHeight>egw_getWindowOuterHeight()?screen.availHeight:egw_getWindowOuterHeight())));
		}
		//Vacation By_date filter
		if (typeof et2.templates['mail.sieve.vacation'] != 'undefined')
		{
			this.vacationFilterStatusChange();
		}
	},

	/**
	 * mail_fetchCurrentlyFocussed - implementation to decide wich mail of all the selected ones is the current
	 *
	 * @param _selected array of the selected mails
	 * @param _reset bool - tell the function to reset the global vars used
	 */
	mail_fetchCurrentlyFocussed: function(_selected, _reset) {
		//console.log("mail_fetchCurrentlyFocussed",_selected, _reset);
		// reinitialize the buffer-info on selected mails
		if (_reset == true || typeof _selected == 'undefined')
		{
			if (_reset == true)
			{
				//var nm = this.et2.getWidgetById(nm_index);
				//if (this.mail_currentlyFocussed!='') nm.refresh([this.mail_currentlyFocussed],'delete');//egw.dataDeleteUID(this.mail_currentlyFocussed);
				if (this.mail_currentlyFocussed!='') egw.dataDeleteUID(this.mail_currentlyFocussed);
				for(var k = 0; k < this.mail_selectedMails.length; k++) egw.dataDeleteUID(this.mail_selectedMails[k]);
				//nm.refresh(this.mail_selectedMails,'delete');
			}
			this.mail_selectedMails = [];
			this.mail_currentlyFocussed = '';
			return '';
		}
		for(var k = 0; k < _selected.length; k++)
		{
			if (jQuery.inArray(_selected[k],this.mail_selectedMails)==-1)
			{
				this.mail_currentlyFocussed = _selected[k];
				break;
			}
		}
		this.mail_selectedMails = _selected;
		return this.mail_currentlyFocussed;
	},

	/**
	 * mail_open - implementation of the open action
	 *
	 * @param _action
	 * @param _senders - the representation of the elements(s) the action is to be performed on
	 * @param _mode - you may pass the mode. if not given view is used (tryastext|tryashtml are supported)
	 */
	mail_open: function(_action, _senders, _mode) {
		//console.log("mail_open",_action, _senders);
		if (typeof _senders == 'undefined')
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _senders = [];
				_senders.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if (typeof _senders == 'undefined' && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _senders = [];
					_senders.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		var _id = _senders[0].id;
		// reinitialize the buffer-info on selected mails
		if (!(_mode == 'tryastext' || _mode == 'tryashtml' || _mode == 'view')) _mode = 'view';
		this.mail_selectedMails = [];
		this.mail_selectedMails.push(_id);
		this.mail_currentlyFocussed = _id;

		var dataElem = egw.dataGetUIDdata(_id);
		var subject = dataElem.data.subject;
		//alert('Open Message:'+_id+' '+subject);
		var h = egw().open( _id,'mail','view',_mode+'='+_id.replace(/=/g,"_") );
		egw(h).ready(function() {
			h.document.title = subject;
		});
	},

	/**
	 * Open a single message in html mode
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_openAsHtml: function(_action, _elems)
	{
		this.mail_open(_action, _elems,'tryashtml');
	},

	/**
	 * Open a single message in plain text mode
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_openAsText: function(_action, _elems)
	{
		this.mail_open(_action, _elems,'tryastext');
	},

	/**
	 * Compose, reply or forward a message
	 *
	 * @function
	 * @memberOf mail
	 * @param _action _action.id is 'compose', 'composeasnew', 'reply', 'reply_all' or 'forward' (forward can be multiple messages)
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_compose: function(_action, _elems)
	{
		if (typeof _elems == 'undefined')
		{
			//console.log(this.et2.getArrayMgr('content').data,this.et2.getArrayMgr("content").getEntry('mail_id'));
			if (this.et2 && this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if (typeof _elems == 'undefined' && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		console.log(_action, _elems);
		// Extra info passed to egw.open()
		var settings = {
			// 'Source' Mail UID
			id: '',
			// How to pull data from the Mail IDs for the compose
			from: ''
		};

		// We only handle one for everything but forward
		settings.id = (typeof _elems == 'undefined'?'':_elems[0].id);

		switch(_action.id)
		{
			case 'compose':
				if (_elems.length == 1)
				{
					//mail_parentRefreshListRowStyle(settings.id,settings.id);
				}
				else
				{
					return this.mail_compose('forward',_elems);
				}
				break;
			case 'forward':
			case 'forwardinline':
			case 'forwardasattach':
				if (_elems.length>1||_action.id == 'forwardasattach')
				{
					var url = 'menuaction=mail.mail_compose.compose';
					return this.mail_openComposeWindow(url,_action.id == 'forwardasattach', _elems);
				}
				else
				{
					settings.from = 'forward';
					settings.mode = 'forwardinline';
				}
				break;
			default:
				// No further client side processing needed for these
				settings.from = _action.id;
		}
		var compose_list = egw.getOpenWindows("mail", /^compose_/);
		var window_name = 'compose_' + compose_list.length + '_'+ (settings.from || '') + '_' + settings.id;
		return egw().open('','mail','add',settings,window_name,'mail');
	},

	/**
	 * Set content into a compose window
	 *
	 * @function
	 * @memberOf mail
	 *
	 * @param {String} window_name The name of an open content window.
	 * @param content Data to set into the window's fields
	 * @param content.to Addresses to add to the to line
	 * @param content.cc Addresses to add to the CC line
	 * @param content.bcc Addresses to add to the BCC line
	 *
	 * @return {boolean} Success
	 */
	setCompose: function(window_name, content)
	{
		// Get window
		var compose = window.open('', window_name);
		if(!compose || compose.closed) return false;

		// Get etemplate of popup
		var compose_et2 = compose.etemplate2.getByApplication('mail');
		if(!compose_et2 || compose_et2.length != 1 || !compose_et2[0].widgetContainer)
		{
			return false;
		}

		// Set each field provided
		var success = true;
		for(var field in content)
		{
			try
			{
				var widget = compose_et2[0].widgetContainer.getWidgetById(field);

				// Merge array values, replace strings
				var value = widget.getValue() || content[field];
				if(jQuery.isArray(value))
				{
					if(jQuery.isArray(content[field]))
					{
						value.concat(content[field]);
					}
					else
					{
						value.push(content[field]);
					}
				}
				widget.set_value(value);
			}
			catch(e)
			{
				egw.log("error", "Unable to set field %s to '%s' in window '%s'", field, content[field],window_name);
				success = false;
				continue;
			}
		}
		return success;
	},

	/**
	 * Compose, reply or forward a message
	 *
	 * @param _url url to open
	 * @param forwardByCompose boolean to decide about the method
	 */
	mail_openComposeWindow: function(_url,forwardByCompose,_elems) {
		var Check=true;
		var alreadyAsked=false;
		var _messageList;
		var sMessageList='';
		// check if mailgrid exists, before accessing it
		var cbAllVisibleMessages;
		var cbAllMessages = false;
		if (typeof forwardByCompose == 'undefined') forwardByCompose = true;
		if (forwardByCompose == false)
		{
			cbAllMessages = cbAllVisibleMessages = Check = false;
		}
		if (typeof prefAskForMultipleForward == 'undefined') prefAskForMultipleForward = egw.preference('prefaskformultipleforward','mail');
		if (cbAllMessages == true || cbAllVisibleMessages == true)
		{
			Check = confirm(this.egw.lang('multiple forward of all mesages'));
			alreadyAsked=true;
		}

		if ((cbAllMessages == true || cbAllVisibleMessages == true ) && Check == true)
		{
			//_messageList = 'all'; // all is not supported by now, only visibly selected messages are chosen
			_messageList = this.mail_getFormData(_elems);
		}
		else
		{
			if (Check == true) _messageList = this.mail_getFormData(_elems);
		}
		if (typeof _messageList != 'undefined')
		{
			for (var i in _messageList['msg']) {
				//alert('eigenschaft:'+_messageList['msg'][i]);
				sMessageList=sMessageList+_messageList['msg'][i]+',';
				//sMessageList.concat(',');
			}
		}
		if (prefAskForMultipleForward == 1 && Check == true && alreadyAsked == false && sMessageList.length >0 && _messageList['msg'].length>1)
		{
			askme = this.egw.lang('multipleforward');
			//if (cbAllMessages == true || cbAllVisibleMessages == true) askme = egw_appWindow('felamimail').lang_confirm_all_messages; // not supported
			Check = confirm(askme);
		}
		//alert("Check:"+Check+" MessageList:"+sMessageList+"#");
		if (Check != true) sMessageList=''; // deny the appending off selected messages to new compose -> reset the sMessageList
		if (Check == true || sMessageList=='')
		{
			if (sMessageList.length >0) {
				sMessageList= 'AsForward&from=forward&mode=asattach&reply_id='+sMessageList.substring(0,sMessageList.length-1);
			}
			//alert(sMessageList);
			egw_openWindowCentered(window.egw_webserverUrl+'/index.php?'+_url+sMessageList,'compose',870,egw_getWindowOuterHeight());
		}
		//ToDo: reset message selection
	},

	/**
	 * mail_disablePreviewArea - implementation of the disablePreviewArea action
	 *
	 * @param _value
	 */
	mail_disablePreviewArea: function(_value) {
		var splitter = this.et2.getWidgetById('mailSplitter');
		if (typeof splitter == 'undefined' || splitter == null) return;
		var splitterDN = splitter.getDOMNode();
		// check if DOM Node has class that contains docked; then we assume the bar docked, whatever our class var states
		for (var i=0; i < splitterDN.childNodes[1].classList.length;i++) if (splitterDN.childNodes[1].classList[i].search(/docked/)>=0) this.mail_previewAreaActive = false;
		//if this.mail_previewAreaActive but clientHeight of childNode is 0, assume this.mail_previewAreaActive incorrect
		if ( this.mail_previewAreaActive && splitterDN.childNodes.length > 2 && splitterDN.childNodes[2].clientHeight < 15) this.mail_previewAreaActive=false;
		//this.et2.getWidgetById('mailPreviewHeadersFrom').set_disabled(_value);
		//this.et2.getWidgetById('mailPreviewHeadersTo').set_disabled(_value);
		//this.et2.getWidgetById('mailPreviewHeadersDate').set_disabled(_value);
		//this.et2.getWidgetById('mailPreviewHeadersSubject').set_disabled(_value);
		this.et2.getWidgetById('mailPreview').set_disabled(_value);
		if (_value==true)
		{
			if (this.mail_previewAreaActive) splitter.dock();
			this.mail_previewAreaActive = false;
		}
		else
		{
			if (!this.mail_previewAreaActive) splitter.undock();
			this.mail_previewAreaActive = true;
		}
	},

	/**
	 * mail_preview - implementation of the preview action
	 *
	 * @param nextmatch et2_nextmatch The widget whose row was selected
	 * @param selected Array Selected row IDs.  May be empty if user unselected all rows.
	 */
	mail_preview: function(selected, nextmatch) {
		//console.log("mail_preview",nextmatch, selected);
		// Empty values, just in case selected is empty (user cleared selection)
		var dataElem = {data:{subject:"",fromaddress:"",toaddress:"",date:"",subject:""}};
		if(typeof selected != 'undefined' && selected.length == 1)
		{
			var _id = this.mail_fetchCurrentlyFocussed(selected);
			dataElem = egw.dataGetUIDdata(_id);
		}
		//get_class does not exist yet
		//var pAAClass = this.et2.getWidgetById('previewAttachmentArea').get_class();
		//console.log(pAAClass);
		if (this.et2.getWidgetById('previewAttachmentArea'))
		{
			this.et2.getWidgetById('previewAttachmentArea').set_class('previewAttachmentArea');
		}
		else
		{
			return;
		}
		if(typeof selected == 'undefined' || selected.length == 0 || selected.length > 1 || typeof dataElem =='undefined')
		{
			this.mail_fetchCurrentlyFocussed();
			var subject ="";
			this.et2.getWidgetById('previewFromAddress').set_value("");
			this.et2.getWidgetById('previewToAddress').set_value("");
			this.et2.getWidgetById('previewDate').set_value("");
			this.et2.getWidgetById('previewSubject').set_value("");
			//this.et2.getWidgetById('previewAttachmentArea').set_value("");
			this.et2.getWidgetById('previewAttachmentArea').set_class('previewAttachmentArea noContent mail_DisplayNone');
			var IframeHandle = this.et2.getWidgetById('messageIFRAME');
			IframeHandle.set_src(egw.link('/index.php',{menuaction:'mail.mail_ui.loadEmailBody',_messageID:""}));
			this.mail_disablePreviewArea(true);
			return;
		}
		//console.log("mail_preview",dataElem);
		this.mail_selectedMails.push(_id);
		var subject =dataElem.data.subject;
		this.mail_disablePreviewArea(false);
		this.et2.getWidgetById('previewFromAddress').set_value(dataElem.data.fromaddress);
		this.et2.getWidgetById('previewToAddress').set_value(dataElem.data.toaddress);
		this.et2.getWidgetById('previewDate').set_value(dataElem.data.date);
		this.et2.getWidgetById('previewSubject').set_value(subject);
		if (dataElem.data.attachmentsBlock.length<1)
		{
			this.et2.getWidgetById('previewAttachmentArea').set_class('previewAttachmentArea noContent mail_DisplayNone');
		}
		else
		{
			var pAttArea = this.et2.getWidgetById('previewAttachmentArea');
			console.log(dataElem.data.attachmentsBlock,pAttArea);
			//pAttArea.createTableFromCells(pAttArea.cells,pAttArea.colData,dataElem.data.attachmentsBlock);
		}
		this.et2.getWidgetById('toolbar').set_actions(JSON.parse(dataElem.data.toolbaractions));
		var IframeHandle = this.et2.getWidgetById('messageIFRAME');
		//console.log(IframeHandle);
		IframeHandle.set_src(egw.link('/index.php',{menuaction:'mail.mail_ui.loadEmailBody',_messageID:_id}));
		var messages = {};
		messages['msg'] = [_id];
		this.mail_removeRowClass(messages,'unseen');
	//	var request = new egw_json_request('mail.mail_ui.ajax_loadEmailBody',[_id]);
	//	request.sendRequest(false);
	},

	mail_setMailBody: function(content) {
		//console.log('mail_setMailBody',content);
		var IframeHandle = this.et2.getWidgetById('messageIFRAME');
		IframeHandle.set_value('');
	},

	/**
	 * mail_startTimerFolderStatusUpdate, timer functions, if the counter changes for the current folder
	 * refresh the message list
	 * @param timeout
	 */
	mail_startTimerFolderStatusUpdate: function(_refreshTimeOut) {
		if (typeof _refreshTimeOut == 'undefined')
		{
			var minutes = egw.preference('refreshTime','mail');
			this.mail_refreshTimeOut = _refreshTimeOut= 1000*60*(minutes?minutes:3); // either the prefs or 3 Minutes
		}
		if (this.mail_refreshTimeOut > _refreshTimeOut) _refreshTimeOut = this.mail_refreshTimeOut;
		if(this.mail_doTimedRefresh) {
			window.clearTimeout(this.mail_doTimedRefresh);
		}
		if(_refreshTimeOut > 9999) {//we do not set _refreshTimeOut's less than 10 seconds
			var self = this;
			this.mail_doTimedRefresh = window.setInterval(function() {
				self.mail_refreshFolderStatus.apply(self);
			}, _refreshTimeOut);
		}
	},

	/**
	 * mail_refreshFolderStatus, function to call to read the counters of a folder and apply them
	 *
	 * @param _nodeID
	 * @param mode
	 */
	mail_refreshFolderStatus: function(_nodeID,mode) {
		var nodeToRefresh = 0;
		var mode2use = "none";
		if (_nodeID) nodeToRefresh = _nodeID;
		if (mode) {
			if (mode == "forced") {mode2use = mode;}
		}
		try
		{
			var tree_wdg = this.et2.getWidgetById(this.nm_index+'[foldertree]');

			var activeFolders = tree_wdg.getTreeNodeOpenItems(nodeToRefresh,mode2use);
			//alert(activeFolders.join('#,#'));
			this.mail_queueRefreshFolderList(activeFolders);
			this.mail_refreshQuotaDisplay();
			// maybe to use the mode forced as trigger for grid reload and using the grids own autorefresh
			// would solve the refresh issue more accurately
			//if (mode == "forced") this.mail_refreshMessageGrid();
			this.mail_refreshMessageGrid();
		} catch(e) { } // ignore the error; maybe the template is not loaded yet
	},

	/**
	 * mail_refreshQuotaDisplay, function to call to read the quota for the active server
	 *
	 */
	mail_refreshQuotaDisplay: function(_server)
	{
		egw.json('mail.mail_ui.ajax_refreshQuotaDisplay',[_server])
			.sendRequest(true);
	},

	/**
	 * mail_setQuotaDisplay, function to call to read the quota for the active server
	 *
	 */
	mail_setQuotaDisplay: function(_data)
	{
		//this.et2 should do the same as etemplate2.getByApplication('mail')[0].widgetContainer
		var quotabox = this.et2.getWidgetById(this.nm_index+'[quotainpercent]');
		//console.log(_data,quotabox);
		//try to set it via set_value and set label
		this.et2.getWidgetById(this.nm_index+'[quotainpercent]').set_class(_data.data.quotaclass);
		this.et2.getWidgetById(this.nm_index+'[quotainpercent]').set_value(_data.data.quotainpercent);
		this.et2.getWidgetById(this.nm_index+'[quotainpercent]').set_label(_data.data.quota);
	},

	/**
	 * Queues a refreshFolderList request for 1ms. Actually this will just execute the
	 * code after the calling script has finished.
	 */
	mail_queueRefreshFolderList: function(_folders)
	{
		this.mail_queuedFolders.push(_folders);
		this.mail_queuedFoldersIndex++;

		// Copy idx onto the anonymous function scope
		var idx = this.mail_queuedFoldersIndex;
		var self = this;
		window.setTimeout(function() {
			if (idx == self.mail_queuedFoldersIndex)
			{
				//var folders = mail_queuedFolders.join(",");
				self.mail_queuedFoldersIndex = 0;
				self.mail_queuedFolders = [];

				egw.json('mail.mail_ui.ajax_setFolderStatus',[_folders])
					.sendRequest(true);
			}
		}, 10);
	},

	/**
	 * mail_setFolderStatus, function to set the status for the visible folders
	 */
	mail_setFolderStatus: function(_status) {
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		for (var i in _status) ftree.setLabel(i,_status[i]);//alert(i +'->'+_status[i]);
	},

	/**
	 * mail_setLeaf, function to set the id and description for the folder given by status key
	 * @param array _status status array with the required data (new id, desc, old desc)
	 *		key is the original id of the leaf to change
	 *		multiple sets can be passed to mail_setLeaf
	 */
	mail_setLeaf: function(_status) {
		//console.log('mail_setLeaf',_status);
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var selectedNode = ftree.getSelectedNode();
		for (var i in _status)
		{
			// if olddesc is undefined or #skip# then skip the message, as we process subfolders
			if (typeof _status[i]['olddesc'] !== 'undefined' && _status[i]['olddesc'] !== '#skip-user-interaction-message#') app.mail.app_refresh(this.egw.lang("Renamed Folder %1 to %2",_status[i]['olddesc'],_status[i]['desc'], 'mail'));
			ftree.renameItem(i,_status[i]['id'],_status[i]['desc']);
			//alert(i +'->'+_status[i]['id']+'+'+_status[i]['desc']);
			if (_status[i]['id']==selectedNode.id)
			{
				var nm = this.et2.getWidgetById(this.nm_index);
				nm.activeFilters["selectedFolder"] = _status[i]['id'];
				nm.applyFilters();
			}
		}
	},

	/**
	 * mail_removeLeaf, function to remove the leaf represented by the given ID
	 * @param array _status status array with the required data (KEY id, VALUE desc)
	 *		key is the id of the leaf to delete
	 *		multiple sets can be passed to mail_deleteLeaf
	 */
	mail_removeLeaf: function(_status) {
		//console.log('mail_removeLeaf',_status);
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var selectedNode = ftree.getSelectedNode();
		for (var i in _status)
		{
			// if olddesc is undefined or #skip# then skip the message, as we process subfolders
			if (typeof _status[i] !== 'undefined' && _status[i] !== '#skip-user-interaction-message#') app.mail.app_refresh(this.egw.lang("Removed Folder %1 ",_status[i], 'mail'));
			ftree.deleteItem(i,(selectedNode.id==i));
			var selectedNodeAfter = ftree.getSelectedNode();
			//alert(i +'->'+_status[i]['id']+'+'+_status[i]['desc']);
			if (selectedNodeAfter.id!=selectedNode.id && selectedNode.id==i)
			{
				var nm = this.et2.getWidgetById(this.nm_index);
				nm.activeFilters["selectedFolder"] = selectedNodeAfter.id;
				nm.applyFilters();
			}
		}
	},

	/**
	 * mail_reloadNode, function to reload the leaf represented by the given ID
	 * @param array _status status array with the required data (KEY id, VALUE desc)
	 *		key is the id of the leaf to delete
	 *		multiple sets can be passed to mail_deleteLeaf
	 */
	mail_reloadNode: function(_status) {
		//console.log('mail_reloadNode',_status);
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var selectedNode = ftree.getSelectedNode();
		for (var i in _status)
		{
			// if olddesc is undefined or #skip# then skip the message, as we process subfolders
			if (typeof _status[i] !== 'undefined' && _status[i] !== '#skip-user-interaction-message#') app.mail.app_refresh(this.egw.lang("Reloaded Folder %1 ",_status[i], 'mail'));
			ftree.refreshItem(i);
			var selectedNodeAfter = ftree.getSelectedNode();
			//alert(i +'->'+_status[i]['id']+'+'+_status[i]['desc']);
			if (selectedNodeAfter.id!=selectedNode.id && selectedNode.id==i)
			{
				var nm = this.et2.getWidgetById(this.nm_index);
				nm.activeFilters["selectedFolder"] = selectedNodeAfter.id;
				nm.applyFilters();
			}
		}
	},

	/**
	 * mail_refreshMessageGrid, function to call to reread ofthe current folder
	 */
	mail_refreshMessageGrid: function(_isPopup) {
		if (typeof _isPopup == 'undefined') _isPopup = false;
		var nm;
		if (_isPopup && !this.mail_isMainWindow)
		{
			nm = window.opener.etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById(this.nm_index);
		}
		else
		{
			nm = this.et2.getWidgetById(this.nm_index);
		}
		nm.applyFilters(); // this should refresh the active folder
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
	app_refresh: function(_msg, _app, _id, _type)
	{
		var bufferExists = false;
		window.clearInterval(app.mail.doStatus); // whatever message was up to be activated
		//alert("app_refresh(\'"+_msg+"\',\'"+_app+"\',\'"+_id+"\',\'"+_type+"\')");
		//myCurrentMsg = mail_getMsg();
		//if (myCurrentMsg.length) {
			// clear message after some time
			myMessageBuffer = ""; //myCurrentMsg;
			bufferExists = true;
		//}
		//app.mail.mail_setMsg('<span style="font-weight: bold;">' +_msg+ '</span>');
		egw_message(_msg,_type);
		app.mail.mail_setMsg('');//without that applyFilters is not refreshing the index page
		if (_app=='mail')
		{
			//we may want to trigger some actions, like modifying the grid, disable preview and stuff

			// TODO: more actions
		}
		if (bufferExists)
		{
			//this.doStatus = window.setInterval("app.mail.mail_setMsg(myMessageBuffer);", 10000);
		}
	},

	/**
	 * mail_getMsg - gets the current Message
	 * @return string
	 */
	mail_getMsg: function()
	{
		var msg_wdg = this.et2.getWidgetById('msg');
		if (msg_wdg)
		{
			return msg_wdg.valueOf().htmlNode[0].innerHTML;
		}
		return "";
	},

	/**
	 * mail_setMsg - sets a Message, with the msg container, and controls if the container is enabled/disabled
	 * @param string myMsg - the message
	 */
	mail_setMsg: function(myMsg)
	{
		var msg_wdg = this.et2.getWidgetById('msg');
		if (msg_wdg)
		{
			msg_wdg.set_value(myMsg);
			msg_wdg.set_disabled(myMsg.trim().length==0);
		}
	},

	/**
	 * Delete mails
	 * takes in all arguments
	 * @param _action
	 * @param _elems
	 */
	mail_delete: function(_action,_elems)
	{
		var calledFromPopup = false;
		if (typeof _elems == 'undefined')
		{
			calledFromPopup = true;
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if (typeof _elems == 'undefined' && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		var msg = this.mail_getFormData(_elems);
		//alert(_action.id+','+ msg);
		app.mail.app_refresh(this.egw.lang('delete messages'), 'mail');
		if (!calledFromPopup) this.mail_setRowClass(_elems,'deleted');
		this.mail_deleteMessages(msg,'no',calledFromPopup);
		if (calledFromPopup && this.mail_isMainWindow==false) window.close();
	},

	/**
	 * Delete mails - actually calls the backend function for deletion
	 * takes in all arguments
	 * @param _msg - message list
	 * @param _action - optional action
	 */
	mail_deleteMessages: function(_msg,_action,_calledFromPopup)
	{
		app.mail.app_refresh(this.egw.lang('delete messages'), 'mail');
		egw.json('mail.mail_ui.ajax_deleteMessages',[_msg,(typeof _action == 'undefined'?'no':_action)])
			.sendRequest();
		for (var i = 0; i < _msg['msg'].length; i++)  egw.dataDeleteUID(_msg['msg'][i]);
		this.mail_refreshMessageGrid(_calledFromPopup);
		this.mail_preview();
	},

	/**
	 * retry to Delete mails
	 * @param responseObject ->
	 * 	 reason - reason to report
	 * 	 messageList
	 */
	mail_retryForcedDelete: function(responseObject)
	{
		var reason = responseObject['response'];
		var messageList = responseObject['messageList'];
		Check = confirm(reason);
		if (Check==true)
		{
			this.mail_deleteMessages(messageList,'remove_immediately');
		}
		else
		{
			app.mail.app_refresh(this.egw.lang('canceled deletion due to userinteraction'), 'mail');
			this.mail_removeRowClass(messageList,'deleted');
		}
		this.mail_refreshMessageGrid();
		this.mail_preview();
	},

	/**
	 * UnDelete mailMessages
	 *
	 * @param _messageList
	 */
	mail_undeleteMessages: function(_messageList) {
	// setting class of row, the old style
	/*
			for(var i=0;i<_messageList['msg'].length;i++) {
				_id = _messageList['msg'][i];
				var dataElem = egw_appWindow('felamimail').mailGrid.dataRoot.getElementById(_id);
				if (dataElem)
				{
					//dataElem.clearData();
					//dataElem.addClass('deleted');
					dataElem.removeClass('deleted');
				}
			}
	*/
	},

	/**
	 * mail_emptyTrash
	 */
	mail_emptyTrash: function() {
		app.mail.app_refresh(this.egw.lang('empty trash'), 'mail');
		egw.json('mail.mail_ui.ajax_emptyTrash')
			.sendRequest(true);
	},

	/**
	 * mail_compressFolder
	 */
	mail_compressFolder: function() {
		app.mail.app_refresh(this.egw.lang('compress folder'), 'mail');
		egw.json('mail.mail_ui.ajax_compressFolder')
			.sendRequest(true);
	},

	/**
	 * mail_changeProfile
	 * @param folder, the ID of the selected Node -> should be an integer
	 * @param _widget, handle to the tree widget
	 */
	mail_changeProfile: function(folder,_widget) {
	//	alert(folder);
		egw.json('mail.mail_ui.ajax_changeProfile',[folder])
			.sendRequest();
		this.mail_refreshMessageGrid();

		return true;
	},

	/**
	 * mail_changeFolder
	 * @param folder, the ID of the selected Node
	 * @param _widget, handle to the tree widget
	 */
	mail_changeFolder: function(folder,_widget) {
		//alert('change Folder called:'+folder);
		var server = folder.split('::');
		app.mail.app_refresh(this.egw.lang('change folder')+'...', 'mail');
		var img = _widget.getSelectedNode().images[0]; // fetch first image
		if (!(img.search(eval('/'+'NoSelect'+'/'))<0) || !(img.search(eval('/'+'thunderbird'+'/'))<0))
		{
			if (!(img.search(eval('/'+'thunderbird'+'/'))<0))
			{
				rv = this.mail_changeProfile(folder,_widget);
				if (rv)
				{
					return rv;
				}
			}
			if (_widget.event_args.length==2)
			{
				folder = _widget.event_args[1];
				_widget.set_value(folder);
			}
			else
			{
				_widget.set_value('');
			}
		}
		var nm = _widget.getRoot().getWidgetById(this.nm_index);
		nm.activeFilters["selectedFolder"] = folder;
		//nm.applyFilters();// its done in refrefreshMessageGrid
		var msg = _widget.getRoot().getWidgetById('msg');
		if (msg)
		{
			window.clearInterval(this.doStatus);
			displayname = _widget.getSelectedLabel();
			inBraket = displayname.search(/\(/);
			if (inBraket!=-1)
			{
				outBraket = displayname.search(/\)/);
				if (outBraket!=-1)
				{
					displayname = displayname.replace(/\((.*?)\)/,"");
					displayname = displayname.replace(/<b>/,"");
					displayname = displayname.replace(/<\/b>/,"");
				}
			}
			myMsg = (displayname?displayname:folder)+' '+this.egw.lang('selected');
			app.mail.app_refresh(myMsg, 'mail');
		}
		//mail_refreshMessageGrid();// its done in refreshFolderStatus already
		this.mail_refreshFolderStatus(folder,'forced');
		this.mail_refreshQuotaDisplay(server[0]);
		this.mail_startTimerFolderStatusUpdate(this.mail_refreshTimeOut);
		this.mail_fetchCurrentlyFocussed(null,true);
		this.mail_preview();
	},

	/**
	 * Flag mail as 'read', 'unread', 'flagged' or 'unflagged'
	 *
	 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
	 * @param _elems
	 */
	mail_flag: function(_action, _elems)
	{
		var do_nmactions = true;
		var msg;
		if (typeof _elems == 'undefined')
		{
			do_nmactions = false;//indicates that this action is probably a popup?
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				msg = {};
				msg['msg'] = [this.et2.getArrayMgr('content').getEntry('mail_id') || ''];
			}
			if (typeof _elems == 'undefined' && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					msg = {};
					msg['msg'] = [this.mail_currentlyFocussed];
				}
			}
		}

		//alert(_action.id+' - '+_elems[0].id);
		//console.log(_action, _elems);
		if (do_nmactions)
		{
			msg = this.mail_getFormData(_elems);
			if (_action.id.substring(0,2)=='un') {
				this.mail_removeRowClass(_elems,_action.id.substring(2));
			}
			else
			{
				this.mail_removeRowClass(_elems,'un'+_action.id);
			}
			this.mail_setRowClass(_elems,_action.id);
		}
		else
		{
			//mail_parentRefreshListRowStyle(msg,_action.id);
		}
		this.mail_flagMessages(_action.id,msg,(do_nmactions?false:true));
	},

	/**
	 * Flag mail as 'read', 'unread', 'flagged' or 'unflagged'
	 *
	 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
	 * @param _elems
	 */
	mail_flagMessages: function(_flag, _elems,_isPopup)
	{
		console.log(_flag, _elems);
		app.mail.app_refresh(this.egw.lang('flag messages'), 'mail');
		egw.json('mail.mail_ui.ajax_flagMessages',[_flag, _elems])
			.sendRequest();
		this.mail_refreshMessageGrid(_isPopup);
	},

	/**
	 * display header lines, or source of mail, depending on the url given
	 *
	 * @param _url
	 */
	mail_displayHeaderLines: function(_url) {
		// only used by right clickaction
		egw_openWindowCentered(_url,'mail_display_headerLines','870','600',window.outerWidth/2,window.outerHeight/2);
	},

	/**
	 * View header of a message
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_header: function(_action, _elems)
	{
		if (typeof _elems == 'undefined')
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if (typeof _elems == 'undefined' && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		//alert('mail_header('+_elems[0].id+')');
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=mail.mail_ui.displayHeader';	// todo compose for Draft folder
		url += '&id='+_elems[0].id;
		this.mail_displayHeaderLines(url);
	},

	/**
	 * View message source
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_mailsource: function(_action, _elems)
	{
		if (typeof _elems == 'undefined')
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if (typeof _elems == 'undefined' && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		//alert('mail_mailsource('+_elems[0].id+')');
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=mail.mail_ui.saveMessage';	// todo compose for Draft folder
		url += '&id='+_elems[0].id;
		url += '&location=display';
		this.mail_displayHeaderLines(url);
	},

	/**
	 * Save a message
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_save: function(_action, _elems)
	{
		if (typeof _elems == 'undefined')
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if (typeof _elems == 'undefined' && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		//alert('mail_save('+_elems[0].id+')');
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=mail.mail_ui.saveMessage';	// todo compose for Draft folder
		url += '&id='+_elems[0].id;
		//window.open(url,'_blank','dependent=yes,width=100,height=100,scrollbars=yes,status=yes');
		document.location = url;
	},

	/**
	 * User clicked an address (FROM, TO, etc)
	 *
	 * @param object tag_info with values for attributes id, label, title, ...
	 * @param et2_taglist widget
	 */
	address_click: function(tag_info, widget)
	{
		console.log(this, arguments);
	},

	/**
	 * 
	 */
	displayAttachment: function(tag_info, widget)
	{
		//console.log(this, arguments, widget);
		var mailid = this.et2.getArrayMgr("content").getEntry('mail_id');
		var attgrid = this.et2.getArrayMgr("content").getEntry('mail_displayattachments')[widget.id.replace(/\[filename\]/,'')];
		//console.log(mailid,attgrid.partID,attgrid.filename,attgrid.mimetype);
		var url = window.egw_webserverUrl+'/index.php?';
		var width;
		var height;
		var windowName ='mail';
		switch(attgrid.type.toUpperCase())
		{
			case 'MESSAGE/RFC822':
				url += 'menuaction=mail.mail_ui.displayMessage';	// todo compose for Draft folder
				url += '&id='+mailid;
				url += '&part='+attgrid.partID;
				url += '&is_winmail='+attgrid.winmailFlag;
				windowName = windowName+'displayMessage_'+mailid+'_'+attgrid.partID;
				width = 870;
				height = egw_getWindowOuterHeight();
				break;
			case 'IMAGE/JPEG':
			case 'IMAGE/PNG':
			case 'IMAGE/GIF':
			case 'IMAGE/BMP':
			case 'APPLICATION/PDF':
			case 'TEXT/PLAIN':
			case 'TEXT/HTML':
			case 'TEXT/DIRECTORY':
/*
				$sfxMimeType = $value['mimeType'];
				$buff = explode('.',$value['name']);
				$suffix = '';
				if (is_array($buff)) $suffix = array_pop($buff); // take the last extension to check with ext2mime
				if (!empty($suffix)) $sfxMimeType = mime_magic::ext2mime($suffix);
				if (strtoupper($sfxMimeType) == 'TEXT/VCARD' || strtoupper($sfxMimeType) == 'TEXT/X-VCARD')
				{
					$attachments[$key]['mimeType'] = $sfxMimeType;
					$value['mimeType'] = strtoupper($sfxMimeType);
				}
*/
			case 'TEXT/X-VCARD':
			case 'TEXT/VCARD':
			case 'TEXT/CALENDAR':
			case 'TEXT/X-VCALENDAR':
				url += 'menuaction=mail.mail_ui.getAttachment';	// todo compose for Draft folder
				url += '&id='+mailid;
				url += '&part='+attgrid.partID;
				url += '&is_winmail='+attgrid.winmailFlag;
				windowName = windowName+'displayAttachment_'+mailid+'_'+attgrid.partID;
				var reg = '800x600';
				var reg2;
				// handle calendar/vcard
				if (attgrid.type.toUpperCase()=='TEXT/CALENDAR')
				{
					windowName = 'maildisplayEvent_'+mailid+'_'+attgrid.partID;
					reg2 = egw.link_get_registry('calendar');
					if (typeof app_registry['view'] != 'undefined' && typeof app_registry['view_popup'] != 'undefined' )
					{
						reg = app_registry['view_popup'];
					}
				}
				if (attgrid.type.toUpperCase()=='TEXT/X-VCARD' || attgrid.type.toUpperCase()=='TEXT/VCARD')
				{
					windowName = 'maildisplayContact_'+mailid+'_'+attgrid.partID;
					reg2 = egw.link_get_registry('addressbook');
					if (typeof app_registry['add'] != 'undefined' && typeof app_registry['add_popup'] != 'undefined' )
					{
						reg = app_registry['add_popup'];
					}
				}
				var w_h =reg.split('x');
				width = w_h[0];
				height = w_h[1];
				break;
			default:
				url += 'menuaction=mail.mail_ui.getAttachment';	// todo compose for Draft folder
				url += '&id='+mailid;
				url += '&part='+attgrid.partID;
				url += '&is_winmail='+attgrid.winmailFlag;
				windowName = windowName+'displayAttachment_'+mailid+'_'+attgrid.partID;
				width = 870;
				height = 600;
				//document.location = url;
				//return;
		}
		egw_openWindowCentered(url,windowName,width,height);
	},

	saveAttachment: function(tag_info, widget)
	{
		//console.log(this, arguments);
		var mailid = this.et2.getArrayMgr("content").getEntry('mail_id');
		var attgrid = this.et2.getArrayMgr("content").getEntry('mail_displayattachments')[widget.id.replace(/\[save\]/,'')];
		//console.log(mailid,attgrid.partID,attgrid.filename,attgrid.mimetype);
		var url = window.egw_webserverUrl+'/index.php?';
		var width;
		var height;
		var windowName ='mail';
		url += 'menuaction=mail.mail_ui.getAttachment';	// todo compose for Draft folder
		url += '&mode=save';
		url += '&id='+mailid;
		url += '&part='+attgrid.partID;
		url += '&is_winmail='+attgrid.winmailFlag;
		document.location = url;
	},

	saveAttachmentToVFS: function(tag_info, widget)
	{
		//console.log(this, arguments);
		var mailid = this.et2.getArrayMgr("content").getEntry('mail_id');
		var attgrid = this.et2.getArrayMgr("content").getEntry('mail_displayattachments')[widget.id.replace(/\[saveAsVFS\]/,'')];
		//console.log(mailid,attgrid.partID,attgrid.filename,attgrid.mimetype);
		var url = window.egw_webserverUrl+'/index.php?';
		var width=640;
		var height=570;
		var windowName ='mail';
		url += 'menuaction=filemanager.filemanager_select.select';	// todo compose for Draft folder
		url += '&mode=saveas';
		url += '&id='+mailid+'::'+attgrid.partID+'::'+attgrid.winmailFlag;
		url += '&name='+attgrid.filename;
		url += '&type='+attgrid.type.toLowerCase();
		url += '&method=mail.mail_ui.vfsSaveAttachment';
		url += '&label='+egw.lang('Save');
		egw_openWindowCentered(url,windowName,width,height);
	},

	saveAllAttachmentsToVFS: function(tag_info, widget)
	{
		var mailid = this.et2.getArrayMgr("content").getEntry('mail_id');
		var attgrid = this.et2.getArrayMgr("content").getEntry('mail_displayattachments');
		console.log(mailid,attgrid);
		var url = window.egw_webserverUrl+'/index.php?';
		var width=640;
		var height=570;
		var windowName ='mail';
		url += 'menuaction=filemanager.filemanager_select.select';	// todo compose for Draft folder
		url += '&mode=select-dir';
		url += '&method=mail.mail_ui.vfsSaveAttachment';
		url += '&label='+egw.lang('Save all');
		for (i=0;i<attgrid.length;i++)
		{
			if (attgrid[i] != null) url += '&id['+i+']='+mailid+'::'+attgrid[i].partID+'::'+attgrid[i].winmailFlag+'::'+attgrid[i].filename;
		}
		egw_openWindowCentered(url,windowName,width,height);
	},

	/**
	 * Save a message to filemanager
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_save2fm: function(_action, _elems)
	{
		if (typeof _elems == 'undefined')
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if (typeof _elems == 'undefined' && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		var _id = _elems[0].id;
		var dataElem = egw.dataGetUIDdata(_id);
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=filemanager.filemanager_select.select';	// todo compose for Draft folder
		url += '&mode=saveas';
		var filename =dataElem.data.subject.replace(/[\f\n\t\v/\\:*#?<>\|]/g,"_");
		url += '&name='+encodeURIComponent(filename+'.eml');
		url += '&mime=message'+encodeURIComponent('/')+'rfc822';
		url += '&method=mail.mail_ui.vfsSaveMessage'
		url += '&id='+_elems[0].id;
		url += '&label=Save';
		//window.open(url,'_blank','dependent=yes,width=100,height=100,scrollbars=yes,status=yes')
		//document.location = url;
		egw_openWindowCentered(url,'vfs_save_message_'+_elems[0].id,'640','570',window.outerWidth/2,window.outerHeight/2);

	},

	/**
	 * Save message as InfoLog
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_infolog: function(_action, _elems)
	{
		if (typeof _elems == 'undefined')
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if (typeof _elems == 'undefined' && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
			if (typeof _action.data.width == 'undefined' && typeof _action.data.height == 'undefined' && !(typeof _action.data.event == 'undefined' &&typeof _action.data.event.popup == 'undefined'))
			{
				if (_action.data.event.popup)
				{
					var app_registry = _action.data.event.popup;
				}
				else
				{
					var app_registry = egw.link_get_registry('infolog');//this.appname);
				}
				//console.log(app_registry);
				if (typeof app_registry['edit'] != 'undefined' && typeof app_registry['edit_popup'] != 'undefined' )
				{
					var w_h =app_registry['edit_popup'].split('x');
				}
			}
		}
		//alert('mail_infolog('+_elems[0].id+')');return;
		//console.log(_action, _elems);
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=infolog.infolog_ui.import_mail';	// todo compose for Draft folder
		url += '&rowid='+_elems[0].id;
		egw_openWindowCentered(url,'import_mail_'+_elems[0].id,(_action.data.width?_action.data.width:w_h[0]),(_action.data.height?_action.data.height:w_h[1]));
	},

	/**
	 * Save message as ticket
	 *
	 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
	 * @param _elems
	 */
	mail_tracker: function(_action, _elems)
	{
		if (typeof _elems == 'undefined')
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if (typeof _elems == 'undefined' && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
			if (typeof _action.data.width == 'undefined' && typeof _action.data.height == 'undefined' && !(typeof _action.data.event == 'undefined' &&typeof _action.data.event.popup == 'undefined'))
			{
				if (_action.data.event.popup)
				{
					var app_registry = _action.data.event.popup;
				}
				else
				{
					var app_registry = egw.link_get_registry('infolog');//this.appname);
				}
				//console.log(app_registry);
				if (typeof app_registry['edit'] != 'undefined' && typeof app_registry['edit_popup'] != 'undefined' )
				{
					var w_h =app_registry['edit_popup'].split('x');
				}
			}
		}
		//alert('mail_tracker('+_elems[0].id+')');
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=tracker.tracker_ui.import_mail';	// todo compose for Draft folder
		url += '&rowid='+_elems[0].id;
		egw_openWindowCentered(url,'import_tracker_'+_elems[0].id,(_action.data.width?_action.data.width:w_h[0]),(_action.data.height?_action.data.height:w_h[1]));
	},


	/**
	 * mail_getFormData
	 *
	 * @param _actionObjects, the senders
	 * @return structured array of message ids: array(msg=>message-ids)
	 */
	mail_getFormData: function(_actionObjects) {
		var messages = {};
		if (_actionObjects.length>0)
		{
			messages['msg'] = [];
		}

		for (var i = 0; i < _actionObjects.length; i++)
		{
			if (_actionObjects[i].id.length>0)
			{
				messages['msg'][i] = _actionObjects[i].id;
			}
		}

		return messages;
	},

	/**
	 * mail_setRowClass
	 *
	 * @param _actionObjects, the senders
	 */
	mail_setRowClass: function(_actionObjects,_class) {
		if (typeof _class == 'undefined') return false;

		for (var i = 0; i < _actionObjects.length; i++)
		{
			if (_actionObjects[i].id.length>0)
			{
				var dataElem = $j(_actionObjects[i].iface.getDOMNode());
				dataElem.addClass(_class);

			}
		}
	},

	/**
	 * mail_removeRowClass
	 *
	 * @param _actionObjects, the senders, or a messages object
	 * @param _class, the class to be removed
	 */
	mail_removeRowClass: function(_actionObjects,_class) {
		if (typeof _class == 'undefined') return false;

		if (typeof _actionObjects['msg'] == 'undefined')
		{
			for (var i = 0; i < _actionObjects.length; i++)
			{
				if (_actionObjects[i].id.length>0)
				{
					var dataElem = $j(_actionObjects[i].iface.getDOMNode());
					dataElem.removeClass(_class);

				}
			}
		}
		else
		{
			var nm = this.et2.getWidgetById(this.nm_index);
			var aO = nm.controller._objectManager.selectedChildren;
			for (var i = 0; i < _actionObjects['msg'].length; i++)
			{
				for (var i = 0; i < aO.length; i++)
				{
					if (aO[i].id==_actionObjects['msg'][i])
					{
						var dataElem = $j(aO[i].iface.getDOMNode());
						dataElem.removeClass(_class);

					}
				}
			}
		}
	},

	// Tree widget stubs
	/**
	 * mail_dragStart - displays information while dragging
	 *
	 * @param action
	 * @param _senders - the representation of the elements dragged
	 * @return the ddhelper
	 */
	mail_dragStart: function(action,_senders) {
		//console.log(action,_senders);
		return $j("<div class=\"ddhelper\">" + _senders.length + " Mails selected </div>");
	},
	/**
	 * mail_move - implementation of the move action from drag n drop
	 *
	 * @param _action
	 * @param _senders - the representation of the elements dragged
	 * @param _target - the representation of the target
	 */
	mail_move: function(_action,_senders,_target) {
		//console.log(_action,_senders,_target);
		var target = _action.id == 'drop_move_mail' ? _target.iface.id : _action.id.substr(5);
		var messages = this.mail_getFormData(_senders);
		//alert('mail_move('+messages.msg.join(',')+' --> '+target+')');
		// TODO: Write move/copy function which cares about doing the same stuff
		// as the "onNodeSelect" function!
		egw.json('mail.mail_ui.ajax_moveMessages',[target, messages])
			.sendRequest();
		var nm = this.et2.getWidgetById(this.nm_index);
		this.mail_setRowClass(_senders,'deleted');
		nm.refresh(messages['msg'],'delete')
		//for (var i = 0; i < messages['msg'].length; i++) egw.dataDeleteUID(messages['msg'][i]);
		this.mail_refreshMessageGrid();
	},
	/**
	 * mail_copy - implementation of the copy action from drag n drop
	 *
	 * @param _action
	 * @param _senders - the representation of the elements dragged
	 * @param _target - the representation of the target
	 */
	mail_copy: function(_action,_senders,_target) {
		//console.log(_action,_senders,_target);
		var target = _action.id == 'drop_copy_mail' ? _target.iface.id : _action.id.substr(5);
		var messages = this.mail_getFormData(_senders);
		//alert('mail_copy('+messages.msg.join(',')+' --> '+target+')');
		// TODO: Write move/copy function which cares about doing the same stuff
		// as the "onNodeSelect" function!
		egw.json('mail.mail_ui.ajax_copyMessages',[target, messages])
			.sendRequest();
		this.mail_refreshMessageGrid();
	},

	/**
	 * mail_AddFolder - implementation of the AddFolder action of right click options on the tree
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	mail_AddFolder: function(action,_senders) {
		//console.log(action,_senders);
		//action.id == 'add'
		//_senders.iface.id == target leaf / leaf to edit
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		OldFolderName = ftree.getLabel(_senders[0].iface.id);
		if (jQuery(OldFolderName).text().length>0) OldFolderName = jQuery(OldFolderName).text();
		OldFolderName = OldFolderName.trim();
		OldFolderName = OldFolderName.replace(/\([0-9]*\)/g,'').trim();
		//console.log(OldFolderName);
		NewFolderName = prompt(this.egw.lang("Add a new Folder to %1:",OldFolderName));
		if (jQuery(NewFolderName).text().length>0) NewFolderName = jQuery(NewFolderName).text();
		//alert(NewFolderName);
		if (NewFolderName && NewFolderName.length>0)
		{
			app.mail.app_refresh(this.egw.lang("Adding Folder %1 to %2",NewFolderName, OldFolderName, 'mail'));
			egw.json('mail.mail_ui.ajax_addFolder',[_senders[0].iface.id, NewFolderName])
				.sendRequest(true);
		}
	},

	/**
	 * mail_RenameFolder - implementation of the RenameFolder action of right click options on the tree
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	mail_RenameFolder: function(action,_senders) {
		//console.log(action,_senders);
		//action.id == 'rename'
		//_senders.iface.id == target leaf / leaf to edit
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		OldFolderName = ftree.getLabel(_senders[0].iface.id);
		if (jQuery(OldFolderName).text().length>0) OldFolderName = jQuery(OldFolderName).text();
		OldFolderName = OldFolderName.trim();
		OldFolderName = OldFolderName.replace(/\([0-9]*\)/g,'').trim();
		//console.log(OldFolderName);
		NewFolderName = prompt(this.egw.lang("Rename Folder %1 to:",OldFolderName));
		if (jQuery(NewFolderName).text().length>0) NewFolderName = jQuery(NewFolderName).text();
		//alert(NewFolderName);
		if (NewFolderName && NewFolderName.length>0)
		{
			app.mail.app_refresh(this.egw.lang("Renaming Folder %1 to %2",OldFolderName,NewFolderName, 'mail'));
			egw.json('mail.mail_ui.ajax_renameFolder',[_senders[0].iface.id, NewFolderName])
				.sendRequest(true);
		}
	},

	/**
	 * mail_DeleteFolder - implementation of the DeleteFolder action of right click options on the tree
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	mail_DeleteFolder: function(action,_senders) {
		//console.log(action,_senders);
		//action.id == 'delete'
		//_senders.iface.id == target leaf / leaf to edit
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		OldFolderName = ftree.getLabel(_senders[0].iface.id);
		if (jQuery(OldFolderName).text().length>0) OldFolderName = jQuery(OldFolderName).text();
		OldFolderName = OldFolderName.trim();
		OldFolderName = OldFolderName.replace(/\([0-9]*\)/g,'').trim();
		//console.log(OldFolderName);
		reallyDelete = confirm(this.egw.lang("Do you really want to DELETE Folder %1 ? \r\nAll messages in the folder will be lost",OldFolderName));
		if (reallyDelete)
		{
			app.mail.app_refresh(this.egw.lang("Deleting Folder %1",OldFolderName, 'mail'));
			egw.json('mail.mail_ui.ajax_deleteFolder',[_senders[0].iface.id])
				.sendRequest(true);
		}
	},

	/**
	 * Send names of uploaded files (again) to server, to process them: either copy to vfs or ask overwrite/rename
	 *
	 * @param _event
	 * @param _file_count
	 * @param {string} [_path=current directory] Where the file is uploaded to.
	 */
	uploadForImport: function(_event, _file_count, _path)
	{
		//console.log(_event,_file_count,_path);
		// path is probably not needed when uploading for file; maybe it is when from vfs
		if(typeof _path == 'undefined')
		{
			//_path = this.get_path();
		}
		if (_file_count && !jQuery.isEmptyObject(_event.data.getValue()))
		{
			var widget = _event.data;
			//console.log(widget.getValue());
//			var request = new egw_json_request('mail_ui::ajax_importMessage', ['upload', widget.getValue(), _path], this);
//			widget.set_value('');
//			request.sendRequest();//false, this._upload_callback, this);
			this.et2_obj.submit();
		}
	},

	/**
	 * Send names of uploaded files (again) to server, to process them: either copy to vfs or ask overwrite/rename
	 *
	 * @param _event
	 * @param _file_count
	 * @param {string} [_path=current directory] Where the file is uploaded to.
	 */
	uploadForCompose: function(_event, _file_count, _path)
	{
		//console.log(_event,_file_count,_path);
		// path is probably not needed when uploading for file; maybe it is when from vfs
		if(typeof _path == 'undefined')
		{
			//_path = this.get_path();
		}
		if (_file_count && !jQuery.isEmptyObject(_event.data.getValue()))
		{
			var widget = _event.data;
			//console.log(widget.getValue());
//			var request = new egw_json_request('mail_ui::ajax_importMessage', ['upload', widget.getValue(), _path], this);
//			widget.set_value('');
//			request.sendRequest();//false, this._upload_callback, this);
			this.et2_obj.submit();
		}
	},

	vfsUploadForImport: function(_egw, _widget, _window) {
		//console.log(_egw, _widget, _window);
		if (!jQuery.isEmptyObject(_widget.getValue()))
		{
			this.et2_obj.submit();
		}
	},

	vfsUploadForCompose: function(_egw, _widget, _window)
	{
		//console.log(_egw, _widget, _window);
		if (!jQuery.isEmptyObject(_widget.getValue()))
		{
			this.et2_obj.submit();
		}
	},

	submitOnChange: function(_egw, _widget, _window) {
		//console.log(_egw, _widget, _window);
		if (!jQuery.isEmptyObject(_widget.getValue()))
		{
			this.et2_obj.submit();
		}
	},

	saveAsDraft: function(_egw, _widget, _window)
	{
		this.et2_obj.submit();
	},

	saveAsDraftAndPrint: function(_egw, _widget, _window)
	{
		this.et2_obj.submit();
	},

	/**
	 * Focus handler for folder, address, reject textbox/taglist to automatic check associated radio button
	 */
	sieve_focus_radioBtn: function(_ev, _widget)
	{
		_widget.getRoot().getWidgetById('action').set_value(_widget.id.replace(/^action_([^_]+)_text$/, '$1'));
	},

	/**
	 *
	 */
	sieve_vac_all_aliases: function()
	{
		var addr = this.et2.getWidgetById('addresses');

		console.log('say something');
		addr.select_all();
		//addr.set_autocomplete_url('mail.mail_compose.ajax_searchAddress');
	},

	/**
	 * Disable/Enable date widgets on vacation seive rules form when status is "by_date"
	 *
	 */
	vacationFilterStatusChange: function()
	{
		var status = this.et2.getWidgetById('status');
		var s_date = this.et2.getWidgetById('start_date');
		var e_date = this.et2.getWidgetById('end_date');
		var by_date_label = this.et2.getWidgetById('by_date_label');

		if (status && s_date && e_date && by_date_label)
		{
			s_date.set_disabled(status.get_value() != "by_date");
			e_date.set_disabled(status.get_value() != "by_date");
			by_date_label.set_disabled(status.get_value() != "by_date");
		}
	},

	/**
	 * action - handling actions on sieve rules
	 *
	 * @param _type - action name
	 * @param _selected - selected row from the sieve rule list
	 * @param _msg - messages
	 */
	action: function(_type, _selected)
	{
		var  actionData ;
		var that = this;
		var typeId = _type.id;
		var ruleID = ((_selected[0].id.split("_").pop()) - 1); // subtract the row id from 1 because the first row id is reserved by grid header
		if (_type)
		{

			switch (_type.id)
			{
				case 'delete':

					var callbackDeleteDialog = function (button_id)
					{
						if (button_id == et2_dialog.YES_BUTTON )
						{
							actionData = _type.parent.data.widget.getArrayMgr('content');
							that._do_action(typeId, actionData['data'],ruleID);
						}
					}
					var confirmDeleteDialog = et2_dialog.show_dialog(callbackDeleteDialog, this.egw.lang("Do you really want to DELETE this Rule"),this.egw.lang("Delete"), {},et2_dialog.BUTTONS_YES_NO_CANCEL, et2_dialog.WARNING_MESSAGE);

					break;
				case 'add'	:
					$linkData = "mail.mail_sieve.edit";
					egw.open_link($linkData,'',"600x480");
					break;
				case 'edit'	:
					$linkData = "mail.mail_sieve.edit&ruleID="+ruleID;
					egw.open_link($linkData,'',"600x480");
					break;
				case 'enable':
					actionData = _type.parent.data.widget.getArrayMgr('content');
					this._do_action(typeId,actionData['data'],ruleID);
					//window.egw_refresh('refreshed',this.appname);
					break;
				case 'disable':
					actionData = _type.parent.data.widget.getArrayMgr('content');
					this._do_action(typeId,actionData['data'],ruleID);
					break;

			}
		}

	},

	_do_action: function(_typeID, _data,_selectedID,_msg)
	{
		if (_typeID && _data)
		{
			var request = egw().json('mail.mail_sieve.ajax_action', [_typeID,_selectedID,_msg],null,null,true);
			console.log(request);
			request.sendRequest();
		}
	},

	sieve_egw_refresh: function(_execid,_msg)
	{
		var request = egw().json('mail.mail_sieve.ajax_sieve_egw_refresh', [this.et2_obj.etemplate_exec_id,_msg],null,this.et2_obj,true);
		console.log(request);
		request.sendRequest();
	},

});
