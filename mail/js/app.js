/**
 * mail - static javaScript functions
 *
 * @link http://www.egroupware.org
 * @author klaus leithoff <kl at stylite.de>
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

app.mail = AppJS.extend({

appname: 'mail',
doStatus: null,
mail_doTimedRefresh: false,

mail_refreshTimeOut: 1000*60*3, // initial call
mail_queuedFolders: [],
mail_queuedFoldersIndex: 0,

/**
 * Initialize javascript for this application
 */
init: function() {

	this._super.apply(this,arguments);

	window.register_app_refresh("mail", this.app_refresh);

	this.mail_startTimerFolderStatusUpdate(this.mail_refreshTimeOut);
	//inital call of refresh folderstatus
	var self = this;
	window.setTimeout(function() {self.mail_refreshFolderStatus.apply(self);},1000);
},

open: function(action, senders, ids) {
	console.log("open",action, senders, ids);
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
		this.mail_doTimedRefresh = window.setInterval(function() {self.mail_refreshFolderStatus.apply(self)}, _refreshTimeOut);
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
	var tree_wdg = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('nm[foldertree]');

	var activeFolders = tree_wdg.getTreeNodeOpenItems(nodeToRefresh,mode2use);
	//alert(activeFolders.join('#,#'));
	this.mail_queueRefreshFolderList(activeFolders);

	this.mail_refreshMessageGrid();
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

			var request = new egw_json_request('mail.mail_ui.ajax_setFolderStatus',[_folders]);
			request.sendRequest();
		}
	}, 10);
},

/**
 * mail_setFolderStatus, function to set the status for the visible folders
 */
mail_setFolderStatus: function(_status) {
	var ftree = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('nm[foldertree]');
	for (var i in _status) ftree.setLabel(i,_status[i]);//alert(i +'->'+_status[i]);
},

/**
 * mail_refreshMessageGrid, function to call to reread ofthe current folder
 */
mail_refreshMessageGrid: function() {
	var nm = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('nm');
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
	app.mail.mail_setMsg('<span style="font-weight: bold;">' +_msg+ '</span>');
	if (_app=='mail')
	{
		//we may want to trigger some actions, like modifying the grid, disable preview and stuff

		// TODO: more actions
	}
	if (bufferExists)
	{
		this.doStatus = window.setInterval("app.mail.mail_setMsg(myMessageBuffer);", 10000);
	}
},

/**
 * mail_getMsg - gets the current Message
 * @return string
 */
mail_getMsg: function()
{
	var msg_wdg = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('msg');
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
	var msg_wdg = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('msg');
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
	var msg = this.mail_getFormData(_elems);
	//alert(_action.id+','+ msg);
	app_refresh(egw.lang('delete messages'), 'mail');
	this.mail_setRowClass(_elems,'deleted');
	var request = new egw_json_request('mail.mail_ui.ajax_deleteMessages',[msg]);
	request.sendRequest(false);
	this.mail_refreshMessageGrid()
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
	app_refresh(egw.lang('empty trash'), 'mail');
	var request = new egw_json_request('mail.mail_ui.ajax_emptyTrash');
	request.sendRequest();
},

/**
 * mail_compressFolder
 */
mail_compressFolder: function() {
	app_refresh(egw.lang('compress folder'), 'mail');
	var request = new egw_json_request('mail.mail_ui.ajax_compressFolder');
	request.sendRequest();
},

/**
 * mail_changeProfile
 * @param folder, the ID of the selected Node -> should be an integer
 * @param _widget, handle to the tree widget
 */
mail_changeProfile: function(folder,_widget) {
//	alert(folder);
	var request = new egw_json_request('mail.mail_ui.ajax_changeProfile',[folder]);
	request.sendRequest(false);
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
	app_refresh(egw.lang('change folder')+'...', 'mail');
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
	var nm = _widget.getRoot().getWidgetById('nm');
	nm.activeFilters["selectedFolder"] = folder;
	nm.applyFilters();
	var msg = _widget.getRoot().getWidgetById('msg');
	if (msg)
	{
		window.clearInterval(this.doStatus);
		displayname = _widget.getSelectedLabel();
		inBraket = displayname.search(/\(/);
		if (inBraket!=-1)
		{
			outBraket = displayname.search(/\)/);
			if (outBraket!=-1) displayname = displayname.replace(/\((.*?)\)/,"");
		}
		myMsg = (displayname?displayname:folder)+' '+egw.lang('selected');
		app_refresh(myMsg, 'mail');
	}
	//mail_refreshMessageGrid();
	this.mail_refreshFolderStatus(folder,'forced');
	this.mail_startTimerFolderStatusUpdate(this.mail_refreshTimeOut);
},

/**
 * Flag mail as 'read', 'unread', 'flagged' or 'unflagged'
 * 
 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
 * @param _elems
 */
mail_flag: function(_action, _elems)
{
	//alert(_action.id+' - '+_elems[0].id);
	var msg = this.mail_getFormData(_elems);
	//
	this.mail_flagMessages(_action.id,msg);
},

/**
 * Flag mail as 'read', 'unread', 'flagged' or 'unflagged'
 * 
 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
 * @param _elems
 */
mail_flagMessages: function(_flag, _elems)
{
	app_refresh(egw.lang('flag messages'), 'mail');
	var request = new egw_json_request('mail.mail_ui.ajax_flagMessages',[_flag, _elems]);
	request.sendRequest(false);
	this.mail_refreshMessageGrid()
},

/**
 * display header lines, or source of mail, depending on the url given
 * 
 * @param _url
 */
mail_displayHeaderLines: function(_url) {
	// only used by right clickaction
	egw_openWindowCentered(_url,'mail_display_headerLines','700','600',window.outerWidth/2,window.outerHeight/2);
},

/**
 * View header of a message
 * 
 * @param _action
 * @param _elems _elems[0].id is the row-id
 */
mail_header: function(_action, _elems)
{
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
	//alert('mail_save('+_elems[0].id+')');
	var url = window.egw_webserverUrl+'/index.php?';
	url += 'menuaction=mail.mail_ui.saveMessage';	// todo compose for Draft folder
	url += '&id='+_elems[0].id;
	//window.open(url,'_blank','dependent=yes,width=100,height=100,scrollbars=yes,status=yes')
	document.location = url;
},

/**
 * Save a message to filemanager
 * 
 * @param _action
 * @param _elems _elems[0].id is the row-id
 */
mail_save2fm: function(_action, _elems)
{
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
	//alert('mail_infolog('+_elems[0].id+')');return;
	var url = window.egw_webserverUrl+'/index.php?';
	url += 'menuaction=infolog.infolog_ui.import_mail';	// todo compose for Draft folder
	url += '&rowid='+_elems[0].id;
	egw_openWindowCentered(url,'import_mail_'+_elems[0].id,_action.data.width,_action.data.height);
},

/**
 * Save message as ticket
 * 
 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
 * @param _elems
 */
mail_tracker: function(_action, _elems)
{
	//alert('mail_tracker('+_elems[0].id+')');
	var url = window.egw_webserverUrl+'/index.php?';
	url += 'menuaction=tracker.tracker_ui.import_mail';	// todo compose for Draft folder
	url += '&rowid='+_elems[0].id;
	egw_openWindowCentered(url,'import_tracker_'+_elems[0].id,_action.data.width,_action.data.height);
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
			var _id = _actionObjects[i].id;
			var dataElem = $j(_actionObjects[i].iface.getDOMNode());
			dataElem.addClass(_class);

		}
	}
},

// Tree widget stubs
mail_dragStart: function(action,_senders) {
	//console.log(action,_senders);
	return $j("<div class=\"ddhelper\">" + _senders.length + " Mails selected </div>")
},
mail_move: function(_action,_senders,_target) {
	//console.log(_action,_senders,_target);
	var target = _action.id == 'drop_move_mail' ? _target.iface.id : _action.id.substr(5);
	var messages = this.mail_getFormData(_senders);
	//alert('mail_move('+messages.msg.join(',')+' --> '+target+')');
	// TODO: Write move/copy function which cares about doing the same stuff
	// as the "onNodeSelect" function!
	var request = new egw_json_request('mail.mail_ui.ajax_moveMessages',[target, messages]);
	request.sendRequest(false);
	this.mail_refreshMessageGrid()
},
mail_copy: function(_action,_senders,_target) {
	//console.log(_action,_senders,_target);
	var target = _action.id == 'drop_copy_mail' ? _target.id : _action.id.substr(5);
	var messages = this.mail_getFormData(_senders);
	//alert('mail_copy('+messages.msg.join(',')+' --> '+target+')');
	// TODO: Write move/copy function which cares about doing the same stuff
	// as the "onNodeSelect" function!
	var request = new egw_json_request('mail.mail_ui.ajax_copyMessages',[target, messages]);
	request.sendRequest(false);
	this.mail_refreshMessageGrid()
}
});
