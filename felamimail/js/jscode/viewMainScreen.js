if (typeof CopyOrMove == 'undefined') var CopyOrMove = egw_appWindow('felamimail').CopyOrMove;
if (typeof prefAskForMove == 'undefined') var prefAskForMove = egw_appWindow('felamimail').prefAskForMove; 
if (typeof prefAskForMultipleForward == 'undefined') var prefAskForMultipleForward = egw_appWindow('felamimail').prefAskForMove; 
if (typeof sURL == 'undefined') var sURL = window.sURL;

if (typeof copyingMessages == 'undefined') var MessageBuffer;
// global vars to store server and active folder info
//var activeServerID			= '{activeServerID}';
if (typeof activeFolder == 'undefined') var activeFolder			= egw_appWindow('felamimail').activeFolder;
if (typeof activeFolderB64 == 'undefined') var activeFolderB64			= egw_appWindow('felamimail').activeFolderB64;
if (typeof activityImagePath == 'undefined') var activityImagePath		= egw_appWindow('felamimail').activityImagePath;

// how many row are selected currently
if (typeof checkedCounter == 'undefined') var checkedCounter=egw_appWindow('felamimail').checkedCounter;

// the refreshtimer objects
if (typeof aktiv == 'undefined') var aktiv = window.aktiv;
if (typeof fm_timerFolderStatus == 'undefined') var fm_timerFolderStatus = egw_appWindow('felamimail').fm_timerFolderStatus;
if (typeof fm_previewMessageID == 'undefined') var fm_previewMessageID = egw_appWindow('felamimail').fm_previewMessageID;
if (typeof fm_previewMessageFolderType == 'undefined') var fm_previewMessageFolderType = egw_appWindow('felamimail').fm_previewMessageFolderType;

// refresh time for mailboxview
if (typeof refreshTimeOut == 'undefined') var refreshTimeOut = egw_appWindow('felamimail').refreshTimeOut;

function parentRefreshListRowStyle(oldID, newID)
{
	var trElement;
	var aElements;
	trElement = document.getElementById('row_'+oldID);
	trElement.style.backgroundColor = "#FFFFFF";
	trElement.style.fontWeight='normal';
	aElements = trElement.getElementsByTagName("a");
	aElements[0].style.fontWeight='normal';
	aElements[1].style.fontWeight='normal';	
	trElement = document.getElementById('row_'+newID);
	trElement.style.backgroundColor = "#ddddFF";
	trElement.style.fontWeight='normal';
	aElements = trElement.getElementsByTagName("a");
	aElements[0].style.fontWeight='normal';
	aElements[1].style.fontWeight='normal';	

}
function setStatusMessage(_message) {
	document.getElementById('messageCounter').innerHTML = '<table cellpadding="0" cellspacing="0"><tr><td><img src="'+ activityImagePath +'"></td><td>&nbsp;' + _message + '</td></tr></table>';
}

function sendNotifyMS (uid) {
	ret = confirm(egw_appWindow('felamimail').lang_sendnotify);
	egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.sendNotify",uid,ret);	
}

function changeSorting(_sort, _aNode) {

	egw_appWindow('felamimail').resetMessageSelect();

	document.getElementById('messageCounter').innerHTML = '<span style="font-weight: bold;">Change sorting ...</span>';
	document.getElementById('divMessageList').innerHTML = '';
	aTags = document.getElementById('tableHeader').getElementsByTagName('a');
	aTags[0].style.fontWeight='normal';
	aTags[1].style.fontWeight='normal';
	aTags[2].style.fontWeight='normal';
	aTags[3].style.fontWeight='normal';
	egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.changeSorting",_sort);
	_aNode.style.fontWeight='bold';
}

function compressFolder() {
	if (document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_compressingFolder +'</span>');
	egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.compressFolder");
}

function deleteMessages(_messageList) {
	var Check = true;
	var cbAllMessages = document.getElementById('selectAllMessagesCheckBox').checked;

	egw_appWindow('felamimail').resetMessageSelect();

	if (cbAllMessages == true) Check = confirm(egw_appWindow('felamimail').lang_confirm_all_messages);
	if (cbAllMessages == true && Check == true)
	{
		_messageList = 'all';
	}
	if (Check == true) {
		egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">' + egw_appWindow('felamimail').lang_deleting_messages + '</span>');
		document.getElementById('divMessageList').innerHTML = '';
		egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.deleteMessages",_messageList);
	} else {
		for(i=0; i< document.forms.formMessageList.elements.length; i++) {
			if(document.forms.formMessageList.elements[i].checked) {
				document.forms.formMessageList.elements[i].checked = false;
			}
		}
	}
}

function displayMessage(_url,_windowName) {
	egw_openWindowCentered(_url, _windowName, 850, egw_getWindowOuterHeight());
}

function fm_displayHeaderLines(_url) {
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

//function OnLoadingEnd(_nodeID) {
//	top.tree.setItemImage(_nodeID, 'folderClose.gif','folderOpen.gif');
//	alert(_nodeID);
//}

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
	if(top.tree.getUserData(_nodeID, 'folderName')) {
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
					egw_appWindow('felamimail').resetMessageSelect();
					formData = 'all';
				} else {
					egw_appWindow('felamimail').resetMessageSelect();
					formData = egw_appWindow('felamimail').xajax.getFormValues('formMessageList');
				}
				if (actionPending == 'copy') 
				{
					egw_appWindow('felamimail').setStatusMessage(egw_appWindow('felamimail').copyingMessages +' <span style="font-weight: bold;">'+ top.tree.getUserData(_nodeID, 'folderName') +'</span>');
					document.getElementById('divMessageList').innerHTML = '';
					egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.copyMessages", _nodeID, formData);
				}
				else
				{
					// default: move messages
					egw_appWindow('felamimail').setStatusMessage(egw_appWindow('felamimail').movingMessages +' <span style="font-weight: bold;">'+ top.tree.getUserData(_nodeID, 'folderName') +'</span>');
					document.getElementById('divMessageList').innerHTML = '';
					egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.moveMessages", _nodeID, formData);
				}
			} else {
				if (actionPending == false)
				{
					egw_appWindow('felamimail').resetMessageSelect();
					for(i=0; i< document.forms.formMessageList.elements.length; i++) {
						if(document.forms.formMessageList.elements[i].checked) {
							document.forms.formMessageList.elements[i].checked = false;
						}
					}
				}
			}
		} else {
			egw_appWindow('felamimail').resetMessageSelect();
			egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">' + egw_appWindow('felamimail').lang_loading + ' ' + top.tree.getUserData(_nodeID, 'folderName') + '</span>');
			document.getElementById('divMessageList').innerHTML = '';
			egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.updateMessageView",_nodeID);
			egw_appWindow('felamimail').refreshFolderStatus(_nodeID);
		}
	}
	CopyOrMove = true;
}

function quickSearch() {
	var searchType;
	var searchString;
	var status;

	egw_appWindow('felamimail').resetMessageSelect();
	//disable select allMessages in Folder Checkbox, as it is not implemented for filters
	document.getElementById('selectAllMessagesCheckBox').disabled  = true;
	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">' + egw_appWindow('felamimail').lang_updating_view + '</span>');
	document.getElementById('divMessageList').innerHTML = '';

	document.getElementById('quickSearch').select();

	searchType = document.getElementById('searchType').value;
	searchString = document.getElementById('quickSearch').value;
	status 	= document.getElementById('status').value;
	if (searchString+'grrr###'+status == 'grrr###any') document.getElementById('selectAllMessagesCheckBox').disabled  = false;

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.quickSearch', searchType, searchString, status);
}

function selectFolderContent(inputBox, _refreshTimeOut) {
	maxMessages = 0;

	selectAll(inputBox, _refreshTimeOut);
}

function selectAll(inputBox, _refreshTimeOut) {
	maxMessages = 0;

	for (var i = 0; i < document.getElementsByTagName('input').length; i++) {
		if(document.getElementsByTagName('input')[i].name == 'msg[]') {
			//alert(document.getElementsByTagName('input')[i].name);
			document.getElementsByTagName('input')[i].checked = inputBox.checked;
			maxMessages++;
		}
	}

	folderFunctions = document.getElementById('folderFunction');

	if(inputBox.checked) {
		checkedCounter = maxMessages;
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
		document.getElementById('messageCheckBox').checked = false;
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
	egw_appWindow('felamimail').resetMessageSelect();
	//disable select allMessages in Folder Checkbox, as it is not implemented for filters
	document.getElementById('selectAllMessagesCheckBox').disabled  = true;
	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">Applying filter '+_selectBox.options[_selectBox.selectedIndex].text+'</span>');
	document.getElementById('divMessageList').innerHTML = '';

	document.getElementById('quickSearch').value = '';

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.extendedSearch',_selectBox.options[_selectBox.selectedIndex].value);
}

function flagMessages(_flag)
{
	var Check=true;
	var _messageList;
	var cbAllMessages = document.getElementById('selectAllMessagesCheckBox').checked;
    egw_appWindow('felamimail').resetMessageSelect();
	if (cbAllMessages == true) Check = confirm(egw_appWindow('felamimail').lang_confirm_all_messages);
	if (cbAllMessages == true && Check == true)
	{
		_messageList = 'all';
	} else {
	    _messageList = egw_appWindow('felamimail').xajax.getFormValues('formMessageList');
	}

	//alert(_messageList);

	if (Check == true) 
	{
		egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">' + egw_appWindow('felamimail').lang_updating_message_status + '</span>');
		egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.flagMessages", _flag, _messageList);
		document.getElementById('divMessageList').innerHTML = '';
		fm_startTimerMessageListUpdate(refreshTimeOut);
	} else {
		for(i=0; i< document.forms.formMessageList.elements.length; i++) {
			if(document.forms.formMessageList.elements[i].checked) {
				document.forms.formMessageList.elements[i].checked = false;
			}
		}
	}
}

function resetMessageSelect()
{
	if (document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	document.getElementById('messageCheckBox').checked = false;
	document.getElementById('selectAllMessagesCheckBox').checked = false;
	checkedCounter = 0;
	folderFunctions = document.getElementById('folderFunction');
	
	while (folderFunctions.hasChildNodes())
		folderFunctions.removeChild(folderFunctions.lastChild);
	var textNode = document.createTextNode('');
	folderFunctions.appendChild(textNode);
	document.getElementsByName("folderAction")[0].value = "changeFolder";
}

function skipForward()
{
	egw_appWindow('felamimail').resetMessageSelect();

	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_skipping_forward +'</span>');
	document.getElementById('divMessageList').innerHTML = '';

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.skipForward');
}

function skipPrevious() {
	egw_appWindow('felamimail').resetMessageSelect();

	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_skipping_previous +'</span>');
	document.getElementById('divMessageList').innerHTML = '';

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.skipPrevious');
}

function jumpEnd() {
	egw_appWindow('felamimail').resetMessageSelect();

	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_jumping_to_end +'</span>');
	document.getElementById('divMessageList').innerHTML = '';

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.jumpEnd');
}

function jumpStart() {
	egw_appWindow('felamimail').resetMessageSelect();

	egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_jumping_to_start +'</span>');
	document.getElementById('divMessageList').innerHTML = '';

	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.jumpStart');
}

var searchesPending=0;

function refresh() {
	//searchesPending++;
	//document.title=searchesPending;
	egw_appWindow('felamimail').resetMessageSelect();
	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.refreshMessageList');
	if (fm_previewMessageID>0)
	{
		//setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_updating_view +'</span>');
		//xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshMessagePreview",fm_previewMessageID,fm_previewMessageFolderType);
	}
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
//	egw_appWindow('felamimail').xajax_doXMLHTTP('felamimail.ajaxfelamimail.refreshFolderList', activeFolders);
//	if (fm_previewMessageID>0)
//	{
//		//setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_updating_view +'</span>');
//		//xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshMessagePreview",fm_previewMessageID,fm_previewMessageFolderType);
//	}
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
	if (document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	document.mainView.submit();
	document.getElementById('messageCounter').innerHTML = MessageBuffer;
}

function openComposeWindow(_url) {
	var Check=true;
	var alreadyAsked=false;
	var _messageList;
	var sMessageList='';
	var cbAllMessages = document.getElementById('selectAllMessagesCheckBox').checked;
	var cbAllVisibleMessages = document.getElementById('messageCheckBox').checked;
	if (typeof prefAskForMultipleForward == 'undefined') prefAskForMultipleForward = egw_appWindow('felamimail').prefAskForMultipleForward;
	egw_appWindow('felamimail').resetMessageSelect();
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
		_messageList = egw_appWindow('felamimail').xajax.getFormValues('formMessageList');
	}
	else
	{
		if (Check == true) _messageList = egw_appWindow('felamimail').xajax.getFormValues('formMessageList');
	}
	if (typeof _messageList != 'undefined')
	{
		for (var i in _messageList['msg']) {
			//alert('eigenschaft:'+_messageList['msg'][i]);
			sMessageList=sMessageList+_messageList['msg'][i]+',';
			//sMessageList.concat(',');
		}
	}
	if (prefAskForMultipleForward == 1 && Check == true && alreadyAsked == false && sMessageList.length >0)
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
	for(i=0; i< document.forms.formMessageList.elements.length; i++) {
		if(document.forms.formMessageList.elements[i].checked) {
			document.forms.formMessageList.elements[i].checked = false;
		}
	}
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

var felamimail_messageUrls = {};
var felamimail_dblclick_speed = 300;

/**
 * Handles message clicks and distinguishes between double clicks and single clicks
 */
function fm_handleMessageClick(_double, _url, _windowName, _node)
{
	if (_double)
	{
		// Unset the given message url - the timeout which was triggered in the
		// click handler will now no longer call the fm_readMessage function
		delete (felamimail_messageUrls[_url]);

		fm_readMessage(_url, _windowName, _node);
	}
	else
	{
		// Check whether the given url is already queued. Only continue if this
		// is not the case
		if (typeof felamimail_messageUrls[_url] == "undefined")
		{
			// Queue the url
			felamimail_messageUrls[_url] = true;

			// Wait "felamimail_dblclick_speed" milliseconds. Only if the doubleclick
			// event doesn't occur in this time, trigger the single click function
			window.setTimeout(function () {
				if (typeof felamimail_messageUrls[_url] == "boolean")
				{
					fm_readMessage(_url, _windowName, _node);
					delete (felamimail_messageUrls[_url]);
				}
			}, felamimail_dblclick_speed);
		}
	}
}

function fm_readMessage(_url, _windowName, _node) {
	var windowArray = _windowName.split('_');
	var tableElement =_node.parentNode.parentNode.parentNode.parentNode;
	var allRows = tableElement.getElementsByTagName("tr");
	for(i=0; i< allRows.length; i++) {
		allRows[i].style.backgroundColor = "#FFFFFF";
	}
	if (windowArray[0] == 'MessagePreview')
	{
		//document.getElementById('spanMessagePreview').innerHTML = '';
		if (document.getElementById('messageCounter').innerHTML.search(eval('/'+egw_appWindow('felamimail').lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
		egw_appWindow('felamimail').setStatusMessage('<span style="font-weight: bold;">'+ egw_appWindow('felamimail').lang_updating_view +'</span>');
		fm_previewMessageID = windowArray[1];
		fm_previewMessageFolderType = windowArray[2];
		// refreshMessagePreview now also refreshes the folder state
		egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshMessagePreview",windowArray[1],windowArray[2]);
	} else {
		egw_openWindowCentered(_url, _windowName, 750, egw_getWindowOuterHeight());

		// Refresh the folder state (count of unread emails)
		egw_appWindow('felamimail').xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshFolder");
	}
	trElement = _node.parentNode.parentNode.parentNode;
	trElement.style.fontWeight='normal';
	trElement.style.backgroundColor = "#ddddFF";

	aElements = trElement.getElementsByTagName("a");
	aElements[0].style.fontWeight='normal';
	aElements[1].style.fontWeight='normal';
}

function fm_readAttachments(_url, _windowName, _node) {
	egw_openWindowCentered(_url, _windowName, 750, 220);
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

// stuff to change row background color
function HexToR(h) {return parseInt((cutHex(h)).substring(0,2),16)}
function HexToG(h) {return parseInt((cutHex(h)).substring(2,4),16)}
function HexToB(h) {return parseInt((cutHex(h)).substring(4,6),16)}
function cutHex(h) {return (h.charAt(0)=="#") ? h.substring(1,7):h}
function RGBtoHex(R,G,B) {return toHex(R)+toHex(G)+toHex(B)}
function toHex(N) {
 if (N==null) return "00";
 N=parseInt(N); if (N==0 || isNaN(N)) return "00";
 N=Math.max(0,N); N=Math.min(N,255); N=Math.round(N);
 return "0123456789ABCDEF".charAt((N-N%16)/16)
      + "0123456789ABCDEF".charAt(N%16);
}
function compareColor(colorA, colorB)
{
	var cA = colorA.search(/#/);
	var cA2C = colorA;
	var cB2C = colorB;
	if (cA != -1)
	{
		cA2C = "rgb("+HexToR(colorA)+", "+HexToG(colorA)+", "+HexToB(colorA)+")";
	}
	var cB = colorB.search(/#/);
	if (cB != -1)
	{
		cB2C = "rgb("+HexToR(colorB)+", "+HexToG(colorB)+", "+HexToB(colorB)+")";
	}
	if (cA2C == cB2C) 
	{
		//alert("match:"+colorA+cA2C+" == "+colorB+cB2C);
		return true;
	}
	else
	{
		//alert("not match:"+colorA+cA2C+" == "+colorB+cB2C);
		return false;
	}
}
function onChangeColor(el,direction)
{
	if (!compareColor(el.style.backgroundColor,"#ddddFF") && !compareColor(el.style.backgroundColor,"#eeeddd"))
	{
		if (direction == 'in') el.style.backgroundColor="#dddddd";
		if (direction == 'out') el.style.backgroundColor="#FFFFFF";
	}
	else
	{
		if (direction == 'in') el.style.backgroundColor="#eeeddd";
		if (direction == 'out') el.style.backgroundColor="#ddddFF";
	}
	return true;
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
