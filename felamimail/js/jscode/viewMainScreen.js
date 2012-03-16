function egw_email_fetchDataProc(_elems, _columns, _callback, _context)
{
	var request = new egw_json_request("felamimail.uiwidgets.ajax_fetch_data",
		[_elems, _columns]);
	request.sendRequest(true, function(_data) {
		_callback.call(_context, _data);
	});
}

function egw_email_columnChangeProc(_set)
{
	var request = new egw_json_request("felamimail.uiwidgets.ajax_store_coldata",
		[_set]);
	request.sendRequest(true);
}

function mailGridGetSelected()
{
	// select messagesv from mailGrid
	var allSelected = mailGrid.dataRoot.actionObject.getSelectedObjects();
	var messages = {};
	// allSelected[i].id hält die id
	// zurückseten iteration über allSelected (getSelectedObjects) und dann allSelected[i].setSelected(false);
	if (allSelected.length>0) messages['msg'] = [];
	for (var i=0; i<allSelected.length; i++) 
	{
		if (allSelected[i].id.length>0) messages['msg'][i] = allSelected[i].id;
	}
	// mailGrid.dataRoot.actionObject.getFocused()
	return messages;
}

function mail_enabledByClass(_action, _senders, _target)
{
//alert('enableByClass:'+_action.data.enableClass);
//alert($j(_target.iface.getDOMNode()).hasClass(_action.data.enableClass));
	return $j(_target.iface.getDOMNode()).hasClass(_action.data.enableClass);
}

function mail_disabledByClass(_action, _senders, _target)
{
// as there only is an enabled attribute, we must negate the result (we find the class -> we return false to set enabled to false)
//alert('disableByClass:'+_action.data.disableClass);
//alert(!$j(_target.iface.getDOMNode()).hasClass(_action.data.disableClass));
	return !$j(_target.iface.getDOMNode()).hasClass(_action.data.disableClass);
}

function mail_parentRefreshListRowStyle(oldID, newID)
{
	// the old implementation is not working anymore, so we use the gridObject for this
	var allElements = mailGrid.dataRoot.actionObject.flatList();
	for (var i=0; i<allElements.length; i++) 
	{
		if (allElements[i].id.length>0) 
		{
			if (oldID == allElements[i].id)
			{
				allElements[i].setSelected(false);
				allElements[i].setFocused(false);
			}
			if (newID == allElements[i].id)
			{
				allElements[i].setSelected(false);
				allElements[i].setFocused(true);
			}
		}
	}
}
function setStatusMessage(_message) {
	document.getElementById('messageCounter').innerHTML = '<table cellpadding="0" cellspacing="0"><tr><td><img src="'+ activityImagePath +'"></td><td>&nbsp;' + _message + '</td></tr></table>';
}

function sendNotifyMS (uid) {
	ret = confirm(egw_appWindow('felamimail').lang_sendnotify);
	egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.sendNotify",uid,ret);	
}

function mail_changeSorting(_sort, _aNode) {

	mail_resetMessageSelect();

	document.getElementById('messageCounter').innerHTML = '<span style="font-weight: bold;">Change sorting ...</span>';
	mail_cleanup();
	document.getElementById('divMessageList').innerHTML = '';
//	aTags = document.getElementById('gridHeaderSubject');
//	alert(aTags);
	//aTags.style.fontWeight='normal';
	egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.changeSorting",_sort);
	_aNode.style.fontWeight='bold';
}

function compressFolder() {
	if (document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_compressingFolder +'</span>');
	egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.compressFolder");
}

/**
 * Open a single message
 * 
 * @param _action
 * @param _elems _elems[0].id is the row-id
 */
function mail_open(_action, _elems)
{
	//alert('mail_open('+_elems[0].id+')');
	if (activeFolderB64 == draftFolderB64 || activeFolderB64 == templateFolderB64)
	{
		_action.id='composefromdraft';
		mail_compose(_action,_elems);
	}
	else
	{
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=felamimail.uidisplay.display';	// todo compose for Draft folder
		url += '&mailbox='+egw_appWindow('felamimail').activeFolderB64;
		url += '&uid='+_elems[0].id;

		fm_readMessage(url, 'displayMessage_'+_elems[0].id, _elems[0].iface.getDOMNode());
	}
}

/**
 * Compose, reply or forward a message
 * 
 * @param _action _action.id is 'compose', 'composeasnew', 'reply', 'reply_all' or 'forward' (forward can be multiple messages)
 * @param _elems _elems[0].id is the row-id
 */
function mail_compose(_action, _elems)
{
	var idsToProcess = '';
	var multipleIds = false;

	if (_elems.length > 1) multipleIds = true;
	//for (var i=0; i<_elems.length; i++)
	//{
	//	if (i>0) idsToProcess += ',';
	//	idsToProcess += _elems[i].id;
	//}
	//alert('mail_'+_action.id+'('+idsToProcess+')');
	var url = window.egw_webserverUrl+'/index.php?';
	if (_action.id == 'compose')
	{
		if (multipleIds == false)
		{
			if (_elems.length == 1) mail_parentRefreshListRowStyle(_elems[0].id,_elems[0].id);
			url += 'menuaction=felamimail.uicompose.compose';
			mail_openComposeWindow(url)
		}
		else
		{
			mail_compose('forward',_elems);
		}
	}
	if (_action.id == 'composefromdraft')
	{
		url += 'menuaction=felamimail.uicompose.composeFromDraft';
		url += '&icServer='+egw_appWindow('felamimail').activeServerID;
		url += '&folder='+egw_appWindow('felamimail').activeFolderB64;
		url += '&uid='+_elems[0].id;
		egw_openWindowCentered(url,'composeasnew_'+_elems[0].id,700,egw_getWindowOuterHeight());
	}
	if (_action.id == 'composeasnew')
	{
		url += 'menuaction=felamimail.uicompose.composeAsNew';
		url += '&icServer='+egw_appWindow('felamimail').activeServerID;
		url += '&folder='+egw_appWindow('felamimail').activeFolderB64;
		url += '&reply_id='+_elems[0].id;
		egw_openWindowCentered(url,'composeasnew_'+_elems[0].id,700,egw_getWindowOuterHeight());
	}
	if (_action.id == 'reply')
	{
		url += 'menuaction=felamimail.uicompose.reply';
		url += '&icServer='+egw_appWindow('felamimail').activeServerID;
		url += '&folder='+egw_appWindow('felamimail').activeFolderB64;
		url += '&reply_id='+_elems[0].id;
		egw_openWindowCentered(url,'reply_'+_elems[0].id,700,egw_getWindowOuterHeight());
	}
	if (_action.id == 'reply_all')
	{
		url += 'menuaction=felamimail.uicompose.replyAll';
		url += '&icServer='+egw_appWindow('felamimail').activeServerID;
		url += '&folder='+egw_appWindow('felamimail').activeFolderB64;
		url += '&reply_id='+_elems[0].id;
		egw_openWindowCentered(url,'replyAll_'+_elems[0].id,700,egw_getWindowOuterHeight());
	}
	if (_action.id == 'forward'||_action.id == 'forwardinline'||_action.id == 'forwardasattach')
	{
		if (multipleIds||_action.id == 'forwardasattach')
		{
			url += 'menuaction=felamimail.uicompose.compose';
			mail_openComposeWindow(url,_action.id == 'forwardasattach');
		}
		else
		{
			url += 'menuaction=felamimail.uicompose.forward';
			url += '&icServer='+egw_appWindow('felamimail').activeServerID;
			url += '&folder='+egw_appWindow('felamimail').activeFolderB64;
			url += '&reply_id='+_elems[0].id;
			egw_openWindowCentered(url,'forward_'+_elems[0].id,700,egw_getWindowOuterHeight());
		}
	}
}

/**
 * Print a message
 * 
 * @param _action
 * @param _elems _elems[0].id is the row-id
 */
function mail_print(_action, _elems)
{
	var url = window.egw_webserverUrl+'/index.php?';
	url += 'menuaction=felamimail.uidisplay.printMessage';	// todo compose for Draft folder
	//url += '&icServer='+egw_appWindow('felamimail').activeServerID;
	url += '&mailbox='+egw_appWindow('felamimail').activeFolderB64;
	url += '&uid='+_elems[0].id;
	egw_openWindowCentered(url,'print_'+_elems[0].id,700,egw_getWindowOuterHeight());
}

/**
 * Save a message
 * 
 * @param _action
 * @param _elems _elems[0].id is the row-id
 */
function mail_save(_action, _elems)
{
	//alert('mail_save('+_elems[0].id+')');
	var url = window.egw_webserverUrl+'/index.php?';
	url += 'menuaction=felamimail.uidisplay.saveMessage';	// todo compose for Draft folder
	//url += '&icServer='+egw_appWindow('felamimail').activeServerID;
	url += '&mailbox='+egw_appWindow('felamimail').activeFolderB64;
	url += '&uid='+_elems[0].id;
	//window.open(url,'_blank','dependent=yes,width=100,height=100,scrollbars=yes,status=yes')
	document.location = url;
}

/**
 * Save a message to filemanager
 * 
 * @param _action
 * @param _elems _elems[0].id is the row-id
 */
function mail_save2fm(_action, _elems)
{
	//alert('mail_save('+_elems[0].id+')'+'->'+_elems[0].data.data.subject.data+'.eml');
	var url = window.egw_webserverUrl+'/index.php?';
	url += 'menuaction=filemanager.filemanager_select.select';	// todo compose for Draft folder
	//url += '&icServer='+egw_appWindow('felamimail').activeServerID;
	url += '&mode=saveas';
	var filename = _elems[0].data.data.subject.data.replace(/[\f\n\t\v/\\:*#?<>\|]/g,"_");
	url += '&name='+encodeURIComponent(filename+'.eml');
	url += '&mime=message'+encodeURIComponent('/')+'rfc822';
	url += '&method=felamimail.uidisplay.vfsSaveMessage'
	url += '&mailbox='+egw_appWindow('felamimail').activeFolderB64;
	//url += '&uid='+_elems[0].id;
	url += '&id='+encodeURIComponent(egw_appWindow('felamimail').activeFolder+'::'+_elems[0].id);
	url += '&label=Save';
	//window.open(url,'_blank','dependent=yes,width=100,height=100,scrollbars=yes,status=yes')
	//document.location = url;
	egw_openWindowCentered(url,'vfs_save_message_'+_elems[0].id,'640','570',window.outerWidth/2,window.outerHeight/2);

}

/**
 * View header of a message
 * 
 * @param _action
 * @param _elems _elems[0].id is the row-id
 */
function mail_header(_action, _elems)
{
	//alert('mail_header('+_elems[0].id+')');
	var url = window.egw_webserverUrl+'/index.php?';
	url += 'menuaction=felamimail.uidisplay.displayHeader';	// todo compose for Draft folder
	//url += '&icServer='+egw_appWindow('felamimail').activeServerID;
	url += '&mailbox='+egw_appWindow('felamimail').activeFolderB64;
	url += '&uid='+_elems[0].id;
	mail_displayHeaderLines(url);
}

/**
 * View message source
 * 
 * @param _action
 * @param _elems _elems[0].id is the row-id
 */
function mail_mailsource(_action, _elems)
{
	//alert('mail_mailsource('+_elems[0].id+')');
	var url = window.egw_webserverUrl+'/index.php?';
	url += 'menuaction=felamimail.uidisplay.saveMessage';	// todo compose for Draft folder
	//url += '&icServer='+egw_appWindow('felamimail').activeServerID;
	url += '&mailbox='+egw_appWindow('felamimail').activeFolderB64;
	url += '&uid='+_elems[0].id;
	url += '&location=display';
	mail_displayHeaderLines(url);
}

/**
 * Flag mail as 'read', 'unread', 'flagged' or 'unflagged'
 * 
 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
 * @param _elems
 */
function mail_flag(_action, _elems)
{
	mail_flagMessages(_action.id);
}

/**
 * Save message as InfoLog
 * 
 * @param _action
 * @param _elems _elems[0].id is the row-id
 */
function mail_infolog(_action, _elems)
{
	//alert('mail_infolog('+_elems[0].id+')');
	var url = window.egw_webserverUrl+'/index.php?';
	url += 'menuaction=infolog.infolog_ui.import_mail';	// todo compose for Draft folder
	//url += '&icServer='+egw_appWindow('felamimail').activeServerID;
	url += '&mailbox='+egw_appWindow('felamimail').activeFolderB64;
	url += '&uid='+_elems[0].id;
	egw_openWindowCentered(url,'import_mail_'+_elems[0].id,_action.data.width,_action.data.height);
}

/**
 * Save message as ticket
 * 
 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
 * @param _elems
 */
function mail_tracker(_action, _elems)
{
	//alert('mail_tracker('+_elems[0].id+')');
	var url = window.egw_webserverUrl+'/index.php?';
	url += 'menuaction=tracker.tracker_ui.import_mail';	// todo compose for Draft folder
	//url += '&icServer='+egw_appWindow('felamimail').activeServerID;
	url += '&mailbox='+egw_appWindow('felamimail').activeFolderB64;
	url += '&uid='+_elems[0].id;
	egw_openWindowCentered(url,'import_tracker_'+_elems[0].id,_action.data.width,_action.data.height);
}

/**
 * Delete mails
 * 
 * @param _action
 * @param _elems
 */
function mail_delete(_action, _elems)
{
	messageList = mailGridGetSelected()
	mail_deleteMessages(messageList);
}

function mail_deleteMessages(_messageList) {
	var Check = true;
	var cbAllMessages = document.getElementById('selectAllMessagesCheckBox').checked;

	mail_resetMessageSelect();

	if (cbAllMessages == true) Check = confirm(egw_appWindow('felamimail').lang_confirm_all_messages);
	if (cbAllMessages == true && Check == true)
	{
		_messageList = 'all';
	}
	if (Check == true) {
		egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">' + egw_appWindow('felamimail').lang_deleting_messages + '</span>');
		mail_cleanup();
		document.getElementById('divMessageList').innerHTML = '';
		egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.deleteMessages",_messageList);
	} else {
		mailGrid.dataRoot.actionObject.setAllSelected(false);
	}
}

function displayMessage(_url,_windowName) {
	egw_openWindowCentered(_url, _windowName, 850, egw_getWindowOuterHeight());
}

function mail_displayHeaderLines(_url) {
	// only used by right clickaction
	egw_openWindowCentered(_url,'fm_display_headerLines','700','600',window.outerWidth/2,window.outerHeight/2);
}

function emptyTrash() {
	if (document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">' + egw_appWindow('felamimail').lang_emptyTrashFolder + '</span>');
	egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.emptyTrash");
}

function tellUser(message,_nodeID) {
	if (_nodeID) {
		alert(message+top.tree.getUserData(_nodeID, 'folderName'));
	} else {
		alert(message);
	}
}

function getTreeNodeOpenItems(_nodeID, mode) {
	var z = top.tree.getSubItems(_nodeID).split(",");
	var oS;
	var PoS;
	var rv;
	var returnValue = ""+_nodeID;
	var modetorun = "none";
	if (mode) { modetorun = mode }
	PoS = top.tree.getOpenState(_nodeID)
	if (modetorun == "forced") PoS = 1;
	if (PoS == 1) {
		for(var i=0;i<z.length;i++) {
			oS = top.tree.getOpenState(z[i])
			//alert(oS)
			if (oS == -1) { returnValue=returnValue+"#,#"+ z[i]}
			if (oS == 0) {returnValue=returnValue+"#,#"+ z[i]}
			if (oS == 1) {
				//alert("got here")
				rv = getTreeNodeOpenItems(z[i]);
				returnValue = returnValue+"#,#"+rv
			}		
		}
	}
	return returnValue
}

function OnLoadingStart(_nodeID) {
	// this one is used, when you click on the expand "+" icon in the tree
	//top.tree.setItemImage(_nodeID, 'loading.gif','loading.gif');
    //alert(_nodeID);
	oS = top.tree.getOpenState(_nodeID)
	if (oS == -1) { 
		//closed will be opened
		//alert(_nodeID+ " state -1");
		egw_appWindow('felamimail').refreshFolderStatus(_nodeID,"forced"); 
	}
	if (oS == 0) { 
		// should not occur
		//alert(_nodeID+" state 0");
	}
	if (oS == 1) { 
		// open, will be closed
		//alert(_nodeID+ "state 1");
	}
	return true; // if function not return true, operation will be stoped
}

function callNodeSelect(_nodeIDfc, mode) {
	_nodeIDfc = _nodeIDfc.replace(/#ampersand#/g,"&amp;");
	if (typeof prefAskForMove == 'undefined') prefAskForMove = egw_appWindow('felamimail').prefAskForMove; 
	//alert("callNodeSelect:"+_nodeIDfc);
	var buff = prefAskForMove;
	if (mode == 0) // cancel
	{
		prefAskForMove = 0;
		CopyOrMove = false;
		onNodeSelect(_nodeIDfc);
	}
	if (mode == 1) // move
	{
		prefAskForMove = 0;
		CopyOrMove = true;
		onNodeSelect(_nodeIDfc);
	}
	if (mode == 2) // copy
	{
		prefAskForMove = 99;
		CopyOrMove = true;
		onNodeSelect(_nodeIDfc);
	}
	prefAskForMove = buff;
	CopyOrMove = true;
	return true;
}

function onNodeSelect(_nodeID) {
	if (typeof CopyOrMove == 'undefined') CopyOrMove = egw_appWindow('felamimail').CopyOrMove;
	if (typeof prefAskForMove == 'undefined') prefAskForMove = egw_appWindow('felamimail').prefAskForMove; 
	var Check = CopyOrMove;
	var actionPending = false;
//	var formData = new Array();
	if(top.tree.getUserData(_nodeID, 'folderName')) {
/*
		if(document.getElementsByName("folderAction")[0].value == "moveMessage") {
			if (prefAskForMove == 1 || prefAskForMove == 2) 
			{
				//Check = confirm(egw_appWindow('felamimail').lang_askformove + top.tree.getUserData(_nodeID, 'folderName'));
				title = egw_appWindow('felamimail').lang_MoveCopyTitle;
				node2call = _nodeID.replace(/&amp;/g,'#ampersand#');
				message = egw_appWindow('felamimail').lang_askformove + top.tree.getUserData(_nodeID, 'folderName');
				message = message + "<p><button onclick=\"callNodeSelect('"+node2call+"', 1);hideDialog();\">"+egw_appWindow('felamimail').lang_move+"</button>";
				if (prefAskForMove == 2) message = message + "&nbsp;<button onclick=\"callNodeSelect('"+node2call+"', 2);hideDialog();\">"+egw_appWindow('felamimail').lang_copy+"</button>";
				message = message + "&nbsp;<button onclick=\"callNodeSelect('"+node2call+"', 0);hideDialog();\">"+egw_appWindow('felamimail').lang_cancel+"</button>";
				type = 'prompt';
				autohide = 0;
				showDialog(title,message,type,autohide);
				Check = false;
				actionPending = true;
			}
			if (prefAskForMove==99) actionPending = 'copy';
			if (Check == true && document.getElementById('selectAllMessagesCheckBox').checked == true) Check = confirm(egw_appWindow('felamimail').lang_confirm_all_messages);
			if (Check == true)
			{
				if (document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
				if (document.getElementById('selectAllMessagesCheckBox').checked == true) {
					mail_resetMessageSelect();
					formData = 'all';
				} else {
					mail_resetMessageSelect();
					formData = egw_appWindow('felamimail').mailGridGetSelected();
				}
				if (actionPending == 'copy') 
				{
					egw_appWindow('felamimail').setStatusMessage(egw_appWindow('felamimail').copyingMessages +' <span style="font-weight: bold;">'+ top.tree.getUserData(_nodeID, 'folderName') +'</span>');
					mail_cleanup();
					document.getElementById('divMessageList').innerHTML = '';
					egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.copyMessages", _nodeID, formData);
				}
				else
				{
					// default: move messages
					egw_appWindow('felamimail').setStatusMessage(egw_appWindow('felamimail').movingMessages +' <span style="font-weight: bold;">'+ top.tree.getUserData(_nodeID, 'folderName') +'</span>');
					mail_cleanup();
					document.getElementById('divMessageList').innerHTML = '';
					egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.moveMessages", _nodeID, formData);
				}
			} else {
				if (actionPending == false)
				{
					mail_resetMessageSelect();
					mailGrid.dataRoot.actionObject.setAllSelected(false);
				}
			}
		} else {
*/
			mail_resetMessageSelect();
			egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">' + egw_appWindow('felamimail').lang_loading + ' ' + top.tree.getUserData(_nodeID, 'folderName') + '</span>');
			mail_cleanup();
			document.getElementById('divMessageList').innerHTML = '';
			egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.updateMessageView",_nodeID);
			egw_appWindow('felamimail').refreshFolderStatus(_nodeID);
//		}
	}
	CopyOrMove = true;
}

function quickSearch() {
	var searchType;
	var searchString;
	var status;

	mail_resetMessageSelect();
	//disable select allMessages in Folder Checkbox, as it is not implemented for filters
	document.getElementById('selectAllMessagesCheckBox').disabled  = true;
	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">' + egw_appWindow('felamimail').lang_updating_view + '</span>');
	mail_cleanup();
	document.getElementById('divMessageList').innerHTML = '';

	document.getElementById('quickSearch').select();

	searchType = document.getElementById('searchType').value;
	searchString = document.getElementById('quickSearch').value;
	status 	= document.getElementById('status').value;
	if (searchString+'grrr###'+status == 'grrr###any') document.getElementById('selectAllMessagesCheckBox').disabled  = false;

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.quickSearch', searchType, searchString, status);

}

function mail_focusGridElement(_uid)
{
//alert('mail_focusGridElement'+_uid);
	var allElements = mailGrid.dataRoot.actionObject.flatList();
	if (allElements.length>0) 
	{
		allElements[0].setFocused(true);
		if (typeof _uid == 'undefined')
		{
			allElements[0].setFocused(true);
		}
		else
		{
			for (var i=0; i<allElements.length; i++) 
			{
				if (allElements[i].id.length>0) 
				{
					if (_uid == allElements[i].id)
					{
						allElements[i].setFocused(true);
						i = allElements.length;
					}
				}
			}
		}
	}
}

function selectFolderContent(inputBox, _refreshTimeOut) {
	maxMessages = 0;

	selectAll(inputBox, _refreshTimeOut);
}

/**
 * fm_previewMessageID is internally used to save the currently used message
 * id. The preview function won't be called for a particular message if
 * fm_previewMessageID is set to it.
 */
var fm_previewMessageID = null;

function selectedGridChange(_selectAll) {
	// Get the currently focused object
	if (mailGrid)
	{
		var focused = mailGrid.dataRoot.actionObject.getFocusedObject();

		if (focused)
		{
			// Get the iframe height, as indicator for preview active
			var IFRAME_HEIGHT = typeof felamimail_iframe_height == "number" ? felamimail_iframe_height : 0;
			if (isNaN(IFRAME_HEIGHT) || IFRAME_HEIGHT<0) IFRAME_HEIGHT=0;
			// Get all currently selected object - we don't want to do a preview
			// if more than one message is selected.
			var allSelected = mailGrid.dataRoot.actionObject.getSelectedObjects();

			if (allSelected.length > 0 && fm_previewMessageID != focused.id && IFRAME_HEIGHT > 0) {
				if (allSelected.length == 1)
				{
					MessageBuffer ='';

					fm_previewMessageFolderType = 0;
					if (activeFolderB64 == sentFolderB64) fm_previewMessageFolderType = 1;
					if (activeFolderB64 == draftFolderB64) fm_previewMessageFolderType = 2;
					if (activeFolderB64 == templateFolderB64) fm_previewMessageFolderType = 3;

					// Call the preview function for this message. Set fm_previewMessageID
					// to the id of this item, so that this function won't be called
					// again for the same item.
					fm_previewMessageID = focused.id;

					fm_readMessage('', 'MessagePreview_'+focused.id+'_'+fm_previewMessageFolderType,
						focused.iface.getDOMNode());
				}
			}
			return;
		}
	}
}

function selectAll(inputBox, _refreshTimeOut) {
	maxMessages = 0;
	mailGrid.dataRoot.actionObject.setAllSelected(inputBox.checked);
	var allSelected = mailGrid.dataRoot.actionObject.getSelectedObjects();
	
	
	folderFunctions = document.getElementById('folderFunction');

	if(allSelected.length>0) {
		checkedCounter = allSelected.length;
		while (folderFunctions.hasChildNodes()) {
		    folderFunctions.removeChild(folderFunctions.lastChild);
		}
		var textNode = document.createTextNode(egw_appWindow('felamimail').lang_select_target_folder);
		folderFunctions.appendChild(textNode);
		document.getElementsByName("folderAction")[0].value = "moveMessage";
		fm_startTimerMessageListUpdate(1800000);
	} else {
		checkedCounter = 0;
		while (folderFunctions.hasChildNodes()) {
		    folderFunctions.removeChild(folderFunctions.lastChild);
		}
		var textNode = document.createTextNode('');
		folderFunctions.appendChild(textNode);
		document.getElementsByName("folderAction")[0].value = "changeFolder";
		fm_startTimerMessageListUpdate(_refreshTimeOut);
	}
}

function toggleFolderRadio(inputBox, _refreshTimeOut) {
	//alert('toggleFolderRadio called');
	folderFunctions = document.getElementById("folderFunction");
	checkedCounter += (inputBox.checked) ? 1 : -1;
	if (checkedCounter > 0) {
		while (folderFunctions.hasChildNodes()) {
		    folderFunctions.removeChild(folderFunctions.lastChild);
		}
		var textNode = document.createTextNode('{lang_move_message}');
		//folderFunctions.appendChild(textNode);
		document.getElementById("folderFunction").innerHTML=egw_appWindow('felamimail').lang_select_target_folder;
		document.getElementsByName("folderAction")[0].value = "moveMessage";
		fm_startTimerMessageListUpdate(1800000);
	} else {
//		document.getElementById('messageCheckBox').checked = false;
		document.getElementById('selectAllMessagesCheckBox').checked = false;
		while (folderFunctions.hasChildNodes()) {
		    folderFunctions.removeChild(folderFunctions.lastChild);
		}
		//var textNode = document.createTextNode('{egw_appWindow('felamimail').lang_change_folder}');
		//folderFunctions.appendChild(textNode);
		document.getElementsByName("folderAction")[0].value = "changeFolder";
		fm_startTimerMessageListUpdate(_refreshTimeOut);
	}
}

function extendedSearch(_selectBox) {
	mail_resetMessageSelect();
	//disable select allMessages in Folder Checkbox, as it is not implemented for filters
	document.getElementById('selectAllMessagesCheckBox').disabled  = true;
	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">Applying filter '+_selectBox.options[_selectBox.selectedIndex].text+'</span>');
	mail_cleanup();
	document.getElementById('divMessageList').innerHTML = '';

	document.getElementById('quickSearch').value = '';

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.extendedSearch',_selectBox.options[_selectBox.selectedIndex].value);
}

function mail_flagMessages(_flag)
{
	var Check=true;
	var _messageList;
	var cbAllMessages = document.getElementById('selectAllMessagesCheckBox').checked;
    mail_resetMessageSelect();
	if (cbAllMessages == true) Check = confirm(egw_appWindow('felamimail').lang_confirm_all_messages);
	if (cbAllMessages == true && Check == true)
	{
		_messageList = 'all';
	} else {
		_messageList = egw_appWindow('felamimail').mailGridGetSelected();
	}

	//alert(_messageList);

	if (Check == true) 
	{
		egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">' + egw_appWindow('felamimail').lang_updating_message_status + '</span>');
		egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.flagMessages", _flag, _messageList);
		mail_cleanup();
		document.getElementById('divMessageList').innerHTML = '';
		fm_startTimerMessageListUpdate(refreshTimeOut);
	} else {
		mailGrid.dataRoot.actionObject.setAllSelected(false);
	}
}

function mail_resetMessageSelect()
{
	if (document.getElementById('messageCounter') != null && document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
//	document.getElementById('messageCheckBox').checked = false;
	if (document.getElementById('selectAllMessagesCheckBox') != null) document.getElementById('selectAllMessagesCheckBox').checked = false;
	checkedCounter = 0;
	folderFunctions = document.getElementById('folderFunction');
	if (folderFunctions != null)
	{
		while (folderFunctions.hasChildNodes())
			folderFunctions.removeChild(folderFunctions.lastChild);
		var textNode = document.createTextNode('');
		folderFunctions.appendChild(textNode);
	}
	if (!(typeof document.getElementsByName("folderAction")[0] == 'undefined'))	document.getElementsByName("folderAction")[0].value = "changeFolder";
}

function skipForward()
{
	mail_resetMessageSelect();

	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_skipping_forward +'</span>');
	mail_cleanup();
	document.getElementById('divMessageList').innerHTML = '';

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.skipForward');
}

function skipPrevious() {
	mail_resetMessageSelect();

	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_skipping_previous +'</span>');
	mail_cleanup();
	document.getElementById('divMessageList').innerHTML = '';

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.skipPrevious');
}

function jumpEnd() {
	mail_resetMessageSelect();

	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_jumping_to_end +'</span>');
	mail_cleanup();
	document.getElementById('divMessageList').innerHTML = '';

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.jumpEnd');
}

function jumpStart() {
	mail_resetMessageSelect();

	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_jumping_to_start +'</span>');
	mail_cleanup();
	document.getElementById('divMessageList').innerHTML = '';

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.jumpStart');
}

var searchesPending=0;

function refresh() {
	//searchesPending++;
	//document.title=searchesPending;
	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.refreshMessageList');
}

function refreshFolderStatus(_nodeID,mode) {
	var nodeToRefresh = 0;
	var mode2use = "none";
	if (document.getElementById('messageCounter')) {
		if (document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	}
	if (_nodeID) nodeToRefresh = _nodeID;
	if (mode) {
		if (mode == "forced") {mode2use = mode;}
	}
	var activeFolders = getTreeNodeOpenItems(nodeToRefresh,mode2use);
	queueRefreshFolderList(activeFolders);
}


var felamimail_queuedFolders = [];
var felamimail_queuedFoldersIndex = 0;

/**
 * Queues a refreshFolderList request for 1ms. Actually this will just execute the
 * code after the calling script has finished.
 */
function queueRefreshFolderList(_folders)
{
	felamimail_queuedFolders.push(_folders);
	felamimail_queuedFoldersIndex++;

	// Copy idx onto the anonymous function scope
	var idx = felamimail_queuedFoldersIndex;
	window.setTimeout(function() {
		if (idx == felamimail_queuedFoldersIndex)
		{
			var folders = felamimail_queuedFolders.join(",");
			felamimail_queuedFoldersIndex = 0;
			felamimail_queuedFolders = [];

			egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.refreshFolderList', folders);
		}
	}, 1);
}

function refreshView() {
	if (typeof framework == 'undefined') {
		if (document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
		document.mainView.submit();
		document.getElementById('messageCounter').innerHTML = MessageBuffer;
	} else {
		framework.getApplicationByName('felamimail').browser.reload();
	}
}

function mail_openComposeWindow(_url,forwardByCompose) {
	var Check=true;
	var alreadyAsked=false;
	var _messageList;
	var sMessageList='';
	var cbAllMessages = document.getElementById('selectAllMessagesCheckBox').checked;
	// check if mailgrid exists, before accessing it
	var cbAllVisibleMessages;
	if (mailGrid) cbAllVisibleMessages = mailGrid.dataRoot.actionObject.getAllSelected();
	if (typeof forwardByCompose == 'undefined') forwardByCompose = true;
	if (forwardByCompose == false)
	{
		cbAllMessages = cbAllVisibleMessages = Check = false;
	}
	if (typeof prefAskForMultipleForward == 'undefined') prefAskForMultipleForward = egw_appWindow('felamimail').prefAskForMultipleForward;
	mail_resetMessageSelect();
	// ask anyway if a whole page is selected
	//if (cbAllMessages == true || cbAllVisibleMessages == true) Check = confirm(egw_appWindow('felamimail').lang_confirm_all_messages); // not supported
	if (cbAllMessages == true || cbAllVisibleMessages == true)
	{
		Check = confirm(egw_appWindow('felamimail').lang_multipleforward);
		alreadyAsked=true;
	}

	if ((cbAllMessages == true || cbAllVisibleMessages == true ) && Check == true)
	{
		//_messageList = 'all'; // all is not supported by now, only visibly selected messages are chosen
		_messageList = egw_appWindow('felamimail').mailGridGetSelected();
	}
	else
	{
		if (Check == true) _messageList = egw_appWindow('felamimail').mailGridGetSelected();
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
		askme = egw_appWindow('felamimail').lang_multipleforward;
		//if (cbAllMessages == true || cbAllVisibleMessages == true) askme = egw_appWindow('felamimail').lang_confirm_all_messages; // not supported
		Check = confirm(askme);
	}
	//alert("Check:"+Check+" MessageList:"+sMessageList+"#");
	if (Check != true) sMessageList=''; // deny the appending off selected messages to new compose -> reset the sMessageList
	if (Check == true || sMessageList=='')
	{
		if (sMessageList.length >0) {
			sMessageList= 'AsForward&forwardmails=1&folder='+activeFolderB64+'&reply_id='+sMessageList.substring(0,sMessageList.length-1);
		}
		//alert(sMessageList);
		egw_openWindowCentered(_url+sMessageList,'compose',700,egw_getWindowOuterHeight());
	}
	mailGrid.dataRoot.actionObject.setAllSelected(false);
}

// timer functions
function fm_startTimerFolderStatusUpdate(_refreshTimeOut) {
	if(fm_timerFolderStatus) {
		window.clearTimeout(fm_timerFolderStatus);
	}
	if(_refreshTimeOut > 5000) {
		fm_timerFolderStatus = window.setInterval("refreshFolderStatus()", _refreshTimeOut);
	}
}

function fm_startTimerMessageListUpdate(_refreshTimeOut) {
	if(aktiv) {
		window.clearTimeout(aktiv);
	}
	if(_refreshTimeOut > 5000) {
		aktiv = window.setInterval("refresh()", _refreshTimeOut);
	}
}

var felamimail_readMessage = null;
var felamimail_abortView = false;
var felamimail_rm_timeout = 400;
var felamimail_doubleclick_timeout = 300;

function fm_msg_addClass(_id, _class) {
	// Set the opened message read
	var dataObject = mailGrid.dataRoot.getElementById(_id);
	if (dataObject)
	{
		dataObject.addClass(_class);
	}
}

function fm_msg_removeClass(_id, _class) {
	// Set the opened message read
	var dataObject = mailGrid.dataRoot.getElementById(_id);
	if (dataObject)
	{
		dataObject.removeClass(_class);
	}
}

function fm_readMessage(_url, _windowName, _node) {
	if (felamimail_abortView)
		return;

	var windowArray = _windowName.split('_');
	var msgId = windowArray[1];

	if (windowArray[0] == 'MessagePreview')
	{
		// Check whether this mail has not already be queued and the message
		// preview is actuall displayed
		if (!isNaN(felamimail_iframe_height)) {

			window.felamimail_readMessage = msgId;

			// Wait felamimail_rm_timeout seconds before opening the email in the
			// preview iframe
			window.setTimeout(function() {
				// Abort if another mail should be displayed
				if (felamimail_readMessage == msgId && !felamimail_abortView)
				{
					// Copy the old status message
					// TODO. Make this an own function
					if (document.getElementById('messageCounter').innerHTML.search(
						eval('/'+egw_appWindow('felamimail').lang_updating_view+'/')) < 0 )
					{
						MessageBuffer = document.getElementById('messageCounter').innerHTML;
					}

					// Set the "updating view" message
					egw_appWindow('felamimail').setStatusMessage(
						'<span style="font-weight: bold;">' + egw_appWindow('felamimail').lang_updating_view + '</span>');

					fm_previewMessageFolderType = windowArray[2];

					// refreshMessagePreview now also refreshes the folder state
					egw_appWindow('felamimail').xajax_doXMLHTTP(
						"felamimail.ajaxfelamimail.refreshMessagePreview",
						windowArray[1], windowArray[2]);

					// Mark the message as read
					fm_msg_removeClass(windowArray[1], 'unseen');
					fm_msg_removeClass(windowArray[1], 'recent');
				}

			}, felamimail_rm_timeout);
		}
	} else {
		window.setTimeout(function() {

			if (!felamimail_abortView) {
				// Remove the url which shall be opened as we do not want to open this
				// message in the preview window
				window.felamimail_readMessage = null;

				egw_openWindowCentered(_url, _windowName, 750, egw_getWindowOuterHeight());

				// Refresh the folder state (count of unread emails)
				egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshFolder");

				fm_msg_removeClass(windowArray[1], 'unseen');
			}
		}, 0);
	}
}

/**
 * Handles message clicks and distinguishes between double clicks and single clicks
 */
function fm_handleAttachmentClick(_double, _url, _windowName, _node)
{
	var msgId = _windowName.split('_')[1];

	felamimail_readMessage = msgId;
	felamimail_abortView = true;

	// Wait "felamimail_dblclick_speed" milliseconds. Only if the doubleclick
	// event doesn't occur in this time, trigger the single click function
	window.setTimeout(function () {
		if (msgId == felamimail_readMessage)
		{
			fm_readAttachments(_url, _windowName, _node);
			window.setTimeout(function() {
				felamimail_abortView = false;
			}, 100);
		}
	}, felamimail_doubleclick_timeout);
}

function fm_readAttachments(_url, _windowName, _node) {
	egw_openWindowCentered(_url, _windowName, 750, 220);
	egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshFolder");
	mailGrid.dataRoot.actionObject.setAllSelected(false);
}


/**
 * Handles message clicks and distinguishes between double clicks and single clicks
 */

function fm_handleComposeClick(_double, _url, _windowName, _node)
{
	var msgId = _windowName.split('_')[1];

	// Queue the url
	felamimail_readMessage = msgId;
	felamimail_abortView = true;

	// Wait "felamimail_dblclick_speed" milliseconds. Only if the doubleclick
	// event doesn't occur in this time, trigger the single click function
	window.setTimeout(function () {
		if (felamimail_readMessage == msgId)
		{
			fm_compose(_url, _windowName, _node);
			window.setTimeout(function() {
				felamimail_abortView = false;
			}, 100);
		}
	}, felamimail_doubleclick_timeout);
}

function fm_compose(_url, _windowName, _node) {
	egw_openWindowCentered(_url, _windowName, 700, egw_getWindowOuterHeight());
	//egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshFolder");
	mailGrid.dataRoot.actionObject.setAllSelected(false);
}


function fm_clearSearch() {
	var inputQuickSearch = document.getElementById('quickSearch');
	var status 	= document.getElementById('status').value;

	//enable select allMessages in Folder Checkbox again
	if (status == 'any') document.getElementById('selectAllMessagesCheckBox').disabled  = false;

	if(inputQuickSearch.value != '') {
		inputQuickSearch.value = '';
		quickSearch();
	}
	
	inputQuickSearch.focus();
}

function changeActiveAccount(_accountSelection)
{
	//alert(_accountSelection.value);
	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.changeActiveAccount',_accountSelection.value);
}

// global var to hold the available overall document height
var felamimail_documentHeight = 0;

function handleResize()
{
	//alert($j("body").height()+' bodyHeight');
	//alert($j(document).height()+' documentHeight');
	var documentHeight =  $j("body").height() == 0 ? $j(document).height() : $j("body").height();
	//alert(documentHeight+' DocumentHeight (a)');
	if (document.documentElement.clientHeight > documentHeight) documentHeight= documentHeight + ((document.documentElement.clientHeight-documentHeight)/3);
	//alert(documentHeight+' DocumentHeight (b)');
	if (felamimail_documentHeight == 0) felamimail_documentHeight = documentHeight;
	if (felamimail_documentHeight > 0 && felamimail_documentHeight != documentHeight) documentHeight = felamimail_documentHeight;
	//alert(document.getElementById('thesideboxcolumn').offsetHeight+" SideboxHeight");
	// if the sidebox is larger than the documentHeight, use that as documentHeight
	if (document.getElementById('thesideboxcolumn') != null && typeof document.getElementById('thesideboxcolumn').offsetHeight == "number" && document.getElementById('thesideboxcolumn').offsetHeight > documentHeight) documentHeight = document.getElementById('thesideboxcolumn').offsetHeight;
	var containerHeight = $j(outerContainer).height();
	//alert(documentHeight+' DocumentHeight');
	if (document.getElementById('divUpperTabs') != null)
	{
		var otabsHeight =0;
		//otabsHeight += document.getElementById('divUpperTabs') != null && typeof document.getElementById('divUpperTabs').offsetHeight == "numeber" ? document.getElementById('divUpperTabs').offsetHeight : 0;
		otabsHeight += document.getElementById('topmenu') != null && typeof document.getElementById('topmenu').offsetHeight == "number" ? document.getElementById('topmenu').offsetHeight : 0;
		otabsHeight += document.getElementById('divAppIconBar') != null && typeof document.getElementById('divAppIconBar').offsetHeight == "number" ? document.getElementById('divAppIconBar').offsetHeight : 0;
		otabsHeight += document.getElementById('divStatusBar') != null && typeof document.getElementById('divStatusBar').offsetHeight == "number" ? document.getElementById('divStatusBar').offsetHeight: 0;
		otabsHeight += document.getElementById('divGenTime') != null && typeof document.getElementById('divGenTime').offsetHeight == "number" ? document.getElementById('divGenTime').offsetHeight: 0;
		otabsHeight += document.getElementById('divPoweredBy') != null && typeof document.getElementById('divPoweredBy').offsetHeight == "number" ? document.getElementById('divPoweredBy').offsetHeight: 0;
		//alert(otabsHeight+' hoehe verfügbar:'+ documentHeight);
		if (document.getElementById('tdAppbox') != null && typeof document.getElementById('tdAppbox').offsetHeight=="number") document.getElementById('tdAppbox').height=documentHeight-otabsHeight-30;
	}
	var MIN_TABLE_HEIGHT = typeof felamimail_messagelist_height == "number" ? felamimail_messagelist_height : 100;
	if (isNaN(MIN_TABLE_HEIGHT) || MIN_TABLE_HEIGHT<0) MIN_TABLE_HEIGHT = 100;

	var MAX_TABLE_WHITESPACE = 25;

	// Get the default iframe height, as it was set in the template
	var IFRAME_HEIGHT = typeof felamimail_iframe_height == "number" ? felamimail_iframe_height : 0;
	if (isNaN(IFRAME_HEIGHT) || IFRAME_HEIGHT<0) IFRAME_HEIGHT=0;

	// Calculate how many space is actually there for the whole mail view
	var outerContainer = $j('#divMessageList');
	var mainViewArea = $j('#divMainView');

	// Exit if the felamimail containers do not exist
	if (!outerContainer || !mainViewArea ) {
		return;
	}
	// maybe check on  $j(mainViewArea).offset()== null as well
	var mainAreaOffsetTop = $j(mainViewArea).offset()==null ? 0 : $j(mainViewArea).offset().top;

	var viewportHeight = $j(window).height();
	
	var totalHeight = viewportHeight;
	if (mainAreaOffsetTop == 0)
	{
		// if the mainViewArea offset from top is 0 we are in frameview, containerheight may/should be set decently
		totalHeight = Math.max(0, viewportHeight - (documentHeight - containerHeight));
	}
	else
	{
		// containerHeight is not set with a decent height when in idots/jerryr, for this reason we use this to calculate the
		totalHeight = Math.max(0, Math.min(documentHeight, viewportHeight)- mainAreaOffsetTop - 100);
	}
	var resultIframeHeight = IFRAME_HEIGHT;
	var resultGridHeight = 0;

	// Check whether there is enough space for extending any of the objects
	var remainingHeight = totalHeight - IFRAME_HEIGHT;
	if (totalHeight - IFRAME_HEIGHT > 0)
	{
		var gridHeight = 0;
		if (mailGrid != null)
		{
			gridHeight = mailGrid.getDataHeight();
			var allElements = mailGrid.dataRoot.actionObject.flatList();
			gridHeight = gridHeight + (allElements.length*3) + 10;
		}
		// Get the height of the mailGrid content
		var contentHeight = Math.max(MIN_TABLE_HEIGHT, gridHeight);

		// Extend the gridHeight as much as possible
		resultGridHeight = Math.max(MIN_TABLE_HEIGHT, Math.min(remainingHeight, contentHeight));

		// Set the iframe height
		resultIframeHeight = Math.max(IFRAME_HEIGHT, totalHeight - resultGridHeight);
	}
	else
	{
		// Size the grid as small as possible
		resultGridHeight = MIN_TABLE_HEIGHT;
	}
	if (IFRAME_HEIGHT==0) resultGridHeight = resultGridHeight -2;
	// Now apply the calculated sizes to the DOM elements

	// Resize the grid
	var divMessageTableList = document.getElementById('divMessageTableList');
	if (divMessageTableList)
	{
		divMessageTableList.style.height = resultGridHeight + 'px';
		if (mailGrid != null)
		{
			mailGrid.resize($j(divMessageTableList).outerWidth(), resultGridHeight);
		}
	}

	// Remove the border of the gray panel above the mail from the iframe height
	resultIframeHeight -= 52;

	// Resize the message table
	var iframe = document.getElementById('messageIFRAME');
	if (typeof iframe != 'undefined' && iframe)
	{
		iframe.height = resultIframeHeight;
	}

	var tdiframe = document.getElementById('tdmessageIFRAME');
	if (tdiframe != 'undefined' && tdiframe)
	{
		tdiframe.height = resultIframeHeight;
	}
}


// DIALOG BOXES by Michael Leigeber
// global variables //
var TIMER = 5;
var SPEED = 10;
var WRAPPER = 'divPoweredBy';

// calculate the current window width //
function pageWidth() {
  return window.innerWidth != null ? window.innerWidth : document.documentElement && document.documentElement.clientWidth ? document.documentElement.clientWidth : document.body != null ? document.body.clientWidth : null;
}

// calculate the current window height //
function pageHeight() {
  return window.innerHeight != null? window.innerHeight : document.documentElement && document.documentElement.clientHeight ? document.documentElement.clientHeight : document.body != null? document.body.clientHeight : null;
}

// calculate the current window vertical offset //
function topPosition() {
  return typeof window.pageYOffset != 'undefined' ? window.pageYOffset : document.documentElement && document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop ? document.body.scrollTop : 0;
}

// calculate the position starting at the left of the window //
function leftPosition() {
  return typeof window.pageXOffset != 'undefined' ? window.pageXOffset : document.documentElement && document.documentElement.scrollLeft ? document.documentElement.scrollLeft : document.body.scrollLeft ? document.body.scrollLeft : 0;
}
/*
// build/show the dialog box, populate the data and call the fadeDialog function //
function showDialog(title,message,type,autohide) {
  if(!type) {
    type = 'error';
  }
  var dialog;
  var dialogheader;
  var dialogclose;
  var dialogtitle;
  var dialogcontent;
  var dialogmask;
  if(!document.getElementById('dialog')) {
    dialog = document.createElement('div');
    dialog.id = 'dialog';
    dialogheader = document.createElement('div');
    dialogheader.id = 'dialog-header';
    dialogtitle = document.createElement('div');
    dialogtitle.id = 'dialog-title';
    dialogclose = document.createElement('div');
    dialogclose.id = 'dialog-close'
    dialogcontent = document.createElement('div');
    dialogcontent.id = 'dialog-content';
    dialogmask = document.createElement('div');
    dialogmask.id = 'dialog-mask';
    document.body.appendChild(dialogmask);
    document.body.appendChild(dialog);
    dialog.appendChild(dialogheader);
    dialogheader.appendChild(dialogtitle);
    dialogheader.appendChild(dialogclose);
    dialog.appendChild(dialogcontent);;
    dialogclose.setAttribute('onclick','hideDialog()');
    dialogclose.onclick = hideDialog;
  } else {
    dialog = document.getElementById('dialog');
    dialogheader = document.getElementById('dialog-header');
    dialogtitle = document.getElementById('dialog-title');
    dialogclose = document.getElementById('dialog-close');
    dialogcontent = document.getElementById('dialog-content');
    dialogmask = document.getElementById('dialog-mask');
    dialogmask.style.visibility = "visible";
    dialog.style.visibility = "visible";
  }
  dialog.style.opacity = .00;
  dialog.style.filter = 'alpha(opacity=0)';
  dialog.alpha = 0;
  var width = pageWidth();
  var height = pageHeight();
  var left = leftPosition();
  var top = topPosition();
  var dialogwidth = dialog.offsetWidth;
  var dialogheight = dialog.offsetHeight;
  var topposition = top + (height / 3) - (dialogheight / 2);
  var leftposition = left + (width / 2) - (dialogwidth / 2);
  dialog.style.top = topposition + "px";
  dialog.style.left = leftposition + "px";
  dialogheader.className = type + "header";
  dialogtitle.innerHTML = title;
  dialogcontent.className = type;
  dialogcontent.innerHTML = message;
  var content = document.getElementById(WRAPPER);
  if (typeof content == 'undefined' || content == null) 
  {
      dialogmask.style.height = '10px';
  } 
  else 
  {
    dialogmask.style.height = content.offsetHeight + 'px';
  }
  dialog.timer = setInterval("fadeDialog(1)", TIMER);
  if(autohide) {
    dialogclose.style.visibility = "hidden";
    window.setTimeout("hideDialog()", (autohide * 1000));
  } else {
    dialogclose.style.visibility = "visible";
  }
}

// hide the dialog box //
function hideDialog() {
  var dialog = document.getElementById('dialog');
  clearInterval(dialog.timer);
  dialog.timer = setInterval("fadeDialog(0)", TIMER);
}

// fade-in the dialog box //
function fadeDialog(flag) {
  if(flag == null) {
    flag = 1;
  }
  var dialog = document.getElementById('dialog');
  var value;
  if(flag == 1) {
    value = dialog.alpha + SPEED;
  } else {
    value = dialog.alpha - SPEED;
  }
  dialog.alpha = value;
  dialog.style.opacity = (value / 100);
  dialog.style.filter = 'alpha(opacity=' + value + ')';
  if(value >= 99) {
    clearInterval(dialog.timer);
    dialog.timer = null;
  } else if(value <= 1) {
    dialog.style.visibility = "hidden";
    document.getElementById('dialog-mask').style.visibility = "hidden";
    clearInterval(dialog.timer);
  }
}
*/
function felamimail_transform_foldertree() {
	// Get the felamimail object manager, but do not create it!
	var objectManager = egw_getObjectManager('felamimail', false);

	if (!objectManager) {
		return;
	}

	// Get the top level element for the felamimail tree
	var treeObj = objectManager.getObjectById("felamimail_folderTree");
	if (treeObj == null) {
		// Add a new container to the object manager which will hold the tree
		// objects
		treeObj = objectManager.addObject("felamimail_folderTree", 
			null, EGW_AO_FLAG_IS_CONTAINER);
	}

	// Delete all old objects
	treeObj.clear();

	// Go over the folder list
	if (typeof felamimail_folders != 'undefined' && felamimail_folders.length > 0)
	{
		if (typeof prefAskForMove == 'undefined') prefAskForMove = egw_appWindow('felamimail').prefAskForMove; 
		for (var i = 0; i < felamimail_folders.length; i++) {
			var folderName = felamimail_folders[i];

			// Add a new action object to the object manager
			var obj = treeObj.addObject(folderName,
				new dhtmlxtreeItemAOI(tree, folderName));
			if (prefAskForMove == 2) 
			{
				obj.updateActionLinks(["drop_move_mail", "drop_copy_mail", "drop_cancel"]);
			}
			else if ( prefAskForMove == 1 )
			{
				obj.updateActionLinks(["drop_move_mail", "drop_cancel"]);
			}
			else
			{
				obj.updateActionLinks(["drop_move_mail"]);
			}
		}
	}
}

function mail_dragStart(_action, _senders) {
	//TODO 
	return $j("<div class=\"ddhelper\">" + _senders.length + " Mails selected </div>")
}

function mail_getFormData(_actionObjects) {
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
}

/**
 * Move (multiple) messages to given folder
 * 
 * @param _action _action.id is 'drop_move_mail' or 'move_'+folder
 * @param _senders selected messages
 * @param _target drop-target, if _action.id = 'drop_move_mail'
 */
function mail_move(_action, _senders, _target) {
	var target = _action.id == 'drop_move_mail' ? _target.id : _action.id.substr(5);
	var messages = mail_getFormData(_senders);
	//alert('mail_move('+messages.msg.join(',')+' --> '+target+')');
	// TODO: Write move/copy function which cares about doing the same stuff
	// as the "onNodeSelect" function!
	if (document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	egw_appWindow('felamimail').setStatusMessage(egw_appWindow('felamimail').movingMessages +' <span style="font-weight: bold;">'+ target +'</span>');
	mail_cleanup();
	document.getElementById('divMessageList').innerHTML = '';

	egw_appWindow('felamimail').xajax_doXMLHTTP(
		"felamimail.ajaxfelamimail.moveMessages", target, messages);
}

/**
 * Copy (multiple) messages to given folder
 * 
 * @param _action _action.id is 'drop_copy_mail' or 'copy_'+folder
 * @param _senders selected messages
 * @param _target drop-target, if _action.id = 'drop_copy_mail'
 */
function mail_copy(_action, _senders, _target) {
	var target = _action.id == 'drop_copy_mail' ? _target.id : _action.id.substr(5);
	var messages = mail_getFormData(_senders);
	//alert('mail_copy('+messages.msg.join(',')+' --> '+target+')');
	// TODO: Write move/copy function which cares about doing the same stuff
	// as the "onNodeSelect" function!
	if (document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	egw_appWindow('felamimail').setStatusMessage(egw_appWindow('felamimail').copyingMessages +' <span style="font-weight: bold;">'+ target +'</span>');
	mail_cleanup();
	document.getElementById('divMessageList').innerHTML = '';
	egw_appWindow('felamimail').xajax_doXMLHTTP(
		"felamimail.ajaxfelamimail.copyMessages", target, messages);
}

function mail_cleanup() {
	var objectManager = egw_getObjectManager("felamimail");
	objectManager.clear();
	mailGrid = null;
	$j("#divMessageTableList").children().remove();
}
