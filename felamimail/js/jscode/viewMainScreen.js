function setStatusMessage(_message) {
	document.getElementById('messageCounter').innerHTML = '<table cellpadding="0" cellspacing="0"><tr><td><img src="'+ activityImagePath +'"></td><td>&nbsp;' + _message + '</td></tr></table>';
}

function sendNotifyMS (uid) {
	ret = confirm(lang_sendnotify);
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.sendNotify",uid,ret);	
}

function changeSorting(_sort, _aNode) {

	resetMessageSelect();

	document.getElementById('messageCounter').innerHTML = '<span style="font-weight: bold;">Change sorting ...</span>';
	document.getElementById('divMessageList').innerHTML = '';
	aTags = document.getElementById('tableHeader').getElementsByTagName('a');
	aTags[0].style.fontWeight='normal';
	aTags[1].style.fontWeight='normal';
	aTags[2].style.fontWeight='normal';
	aTags[3].style.fontWeight='normal';
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.changeSorting",_sort);
	_aNode.style.fontWeight='bold';
}

function compressFolder() {
	if (document.getElementById('messageCounter').innerHTML.search(eval('/'+lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	setStatusMessage('<span style="font-weight: bold;">'+ lang_compressingFolder +'</span>');
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.compressFolder");
}

function deleteMessages(_messageList) {
	var Check = true;
	var cbAllMessages = document.getElementById('selectAllMessagesCheckBox').checked;

	resetMessageSelect();

	if (cbAllMessages == true) Check = confirm(lang_confirm_all_messages);
	if (cbAllMessages == true && Check == true)
	{
		_messageList = 'all';
	}
	if (Check == true) {
		setStatusMessage('<span style="font-weight: bold;">' + lang_deleting_messages + '</span>');
		document.getElementById('divMessageList').innerHTML = '';
		xajax_doXMLHTTP("felamimail.ajaxfelamimail.deleteMessages",_messageList);
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
	if (document.getElementById('messageCounter').innerHTML.search(eval('/'+lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	setStatusMessage('<span style="font-weight: bold;">' + lang_emptyTrashFolder + '</span>');
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.emptyTrash");
}

function tellUser(message,_nodeID) {
	if (_nodeID) {
		alert(message+tree.getUserData(_nodeID, 'folderName'));
	} else {
		alert(message);
	}
}

function getTreeNodeOpenItems(_nodeID, mode) {
	var z = tree.getSubItems(_nodeID).split(",");
	var oS;
	var PoS;
	var rv;
	var returnValue = ""+_nodeID;
	var modetorun = "none";
	if (mode) { modetorun = mode }
	PoS = tree.getOpenState(_nodeID)
	if (modetorun == "forced") PoS = 1;
	if (PoS == 1) {
		for(var i=0;i<z.length;i++) {
			oS = tree.getOpenState(z[i])
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
	//tree.setItemImage(_nodeID, 'loading.gif','loading.gif');
    //alert(_nodeID);
	oS = tree.getOpenState(_nodeID)
	if (oS == -1) { 
		//closed will be opened
		//alert(_nodeID+ " state -1");
		refreshFolderStatus(_nodeID,"forced"); 
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
//	tree.setItemImage(_nodeID, 'folderClose.gif','folderOpen.gif');
//	alert(_nodeID);
//}

function callNodeSelect(_nodeIDfc, mode) {
	_nodeIDfc = _nodeIDfc.replace(/#ampersand#/g,"&amp;");
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
//alert("onNodeSelect:"+_nodeID);
	var Check = CopyOrMove;
	var actionPending = false;
	if(tree.getUserData(_nodeID, 'folderName')) {
		if(document.getElementsByName("folderAction")[0].value == "moveMessage") {
			if (prefAskForMove == 1 || prefAskForMove == 2) 
			{
				//Check = confirm(lang_askformove + tree.getUserData(_nodeID, 'folderName'));
				title = lang_MoveCopyTitle;
				node2call = _nodeID.replace(/&amp;/g,'#ampersand#');
				message = lang_askformove + tree.getUserData(_nodeID, 'folderName');
				message = message + "<p><button onclick=\"callNodeSelect('"+node2call+"', 1);hideDialog();\">"+lang_move+"</button>";
				if (prefAskForMove == 2) message = message + "&nbsp;<button onclick=\"callNodeSelect('"+node2call+"', 2);hideDialog();\">"+lang_copy+"</button>";
				message = message + "&nbsp;<button onclick=\"callNodeSelect('"+node2call+"', 0);hideDialog();\">"+lang_cancel+"</button>";
				type = 'prompt';
				autohide = 0;
				showDialog(title,message,type,autohide);
				Check = false;
				actionPending = true;
			}
			if (prefAskForMove==99) actionPending = 'copy';
			if (Check == true && document.getElementById('selectAllMessagesCheckBox').checked == true) Check = confirm(lang_confirm_all_messages);
			if (Check == true)
			{
				if (document.getElementById('messageCounter').innerHTML.search(eval('/'+lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
				if (document.getElementById('selectAllMessagesCheckBox').checked == true) {
					resetMessageSelect();
					formData = 'all';
				} else {
					resetMessageSelect();
					formData = xajax.getFormValues('formMessageList');
				}
				if (actionPending == 'copy') 
				{
					setStatusMessage(copyingMessages +' <span style="font-weight: bold;">'+ tree.getUserData(_nodeID, 'folderName') +'</span>');
					document.getElementById('divMessageList').innerHTML = '';
					xajax_doXMLHTTP("felamimail.ajaxfelamimail.copyMessages", _nodeID, formData);
				}
				else
				{
					// default: move messages
					setStatusMessage(movingMessages +' <span style="font-weight: bold;">'+ tree.getUserData(_nodeID, 'folderName') +'</span>');
					document.getElementById('divMessageList').innerHTML = '';
					xajax_doXMLHTTP("felamimail.ajaxfelamimail.moveMessages", _nodeID, formData);
				}
			} else {
				if (actionPending == false)
				{
					resetMessageSelect();
					for(i=0; i< document.forms.formMessageList.elements.length; i++) {
						if(document.forms.formMessageList.elements[i].checked) {
							document.forms.formMessageList.elements[i].checked = false;
						}
					}
				}
			}
		} else {
			resetMessageSelect();
			setStatusMessage('<span style="font-weight: bold;">' + lang_loading + ' ' + tree.getUserData(_nodeID, 'folderName') + '</span>');
			document.getElementById('divMessageList').innerHTML = '';
			xajax_doXMLHTTP("felamimail.ajaxfelamimail.updateMessageView",_nodeID);
			refreshFolderStatus(_nodeID);
		}
	}
	CopyOrMove = true;
}

function quickSearch() {
	var searchType;
	var searchString;
	var status;

	resetMessageSelect();
	//disable select allMessages in Folder Checkbox, as it is not implemented for filters
	document.getElementById('selectAllMessagesCheckBox').disabled  = true;
	setStatusMessage('<span style="font-weight: bold;">' + lang_updating_view + '</span>');
	document.getElementById('divMessageList').innerHTML = '';

	document.getElementById('quickSearch').select();

	searchType = document.getElementById('searchType').value;
	searchString = document.getElementById('quickSearch').value;
	status 	= document.getElementById('status').value;
	if (searchString+'grrr###'+status == 'grrr###any') document.getElementById('selectAllMessagesCheckBox').disabled  = false;

	xajax_doXMLHTTP('felamimail.ajaxfelamimail.quickSearch', searchType, searchString, status);
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
		var textNode = document.createTextNode(lang_select_target_folder);
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
		document.getElementById("folderFunction").innerHTML=lang_select_target_folder;
		document.getElementsByName("folderAction")[0].value = "moveMessage";
		fm_startTimerMessageListUpdate(1800000);
	} else {
		document.getElementById('messageCheckBox').checked = false;
		document.getElementById('selectAllMessagesCheckBox').checked = false;
		while (folderFunctions.hasChildNodes()) {
		    folderFunctions.removeChild(folderFunctions.lastChild);
		}
		//var textNode = document.createTextNode('{lang_change_folder}');
		//folderFunctions.appendChild(textNode);
		document.getElementsByName("folderAction")[0].value = "changeFolder";
		fm_startTimerMessageListUpdate(_refreshTimeOut);
	}
}

function extendedSearch(_selectBox) {
	resetMessageSelect();
	//disable select allMessages in Folder Checkbox, as it is not implemented for filters
	document.getElementById('selectAllMessagesCheckBox').disabled  = true;
	setStatusMessage('<span style="font-weight: bold;">Applying filter '+_selectBox.options[_selectBox.selectedIndex].text+'</span>');
	document.getElementById('divMessageList').innerHTML = '';

	document.getElementById('quickSearch').value = '';

	xajax_doXMLHTTP('felamimail.ajaxfelamimail.extendedSearch',_selectBox.options[_selectBox.selectedIndex].value);
}

function flagMessages(_flag)
{
	var Check=true;
	var _messageList;
	var cbAllMessages = document.getElementById('selectAllMessagesCheckBox').checked;
    resetMessageSelect();
	if (cbAllMessages == true) Check = confirm(lang_confirm_all_messages);
	if (cbAllMessages == true && Check == true)
	{
		_messageList = 'all';
	} else {
	    _messageList = xajax.getFormValues('formMessageList');
	}

	//alert(_messageList);

	if (Check == true) 
	{
		setStatusMessage('<span style="font-weight: bold;">' + lang_updating_message_status + '</span>');
		xajax_doXMLHTTP("felamimail.ajaxfelamimail.flagMessages", _flag, _messageList);
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
	if (document.getElementById('messageCounter').innerHTML.search(eval('/'+lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
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
	resetMessageSelect();

	setStatusMessage('<span style="font-weight: bold;">'+ lang_skipping_forward +'</span>');
	document.getElementById('divMessageList').innerHTML = '';

	xajax_doXMLHTTP('felamimail.ajaxfelamimail.skipForward');
}

function skipPrevious() {
	resetMessageSelect();

	setStatusMessage('<span style="font-weight: bold;">'+ lang_skipping_previous +'</span>');
	document.getElementById('divMessageList').innerHTML = '';

	xajax_doXMLHTTP('felamimail.ajaxfelamimail.skipPrevious');
}

function jumpEnd() {
	resetMessageSelect();

	setStatusMessage('<span style="font-weight: bold;">'+ lang_jumping_to_end +'</span>');
	document.getElementById('divMessageList').innerHTML = '';

	xajax_doXMLHTTP('felamimail.ajaxfelamimail.jumpEnd');
}

function jumpStart() {
	resetMessageSelect();

	setStatusMessage('<span style="font-weight: bold;">'+ lang_jumping_to_start +'</span>');
	document.getElementById('divMessageList').innerHTML = '';

	xajax_doXMLHTTP('felamimail.ajaxfelamimail.jumpStart');
}

var searchesPending=0;

function refresh() {
	//searchesPending++;
	//document.title=searchesPending;
	resetMessageSelect();
	xajax_doXMLHTTP('felamimail.ajaxfelamimail.refreshMessageList');
	if (fm_previewMessageID>0)
	{
		//setStatusMessage('<span style="font-weight: bold;">'+ lang_updating_view +'</span>');
		//xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshMessagePreview",fm_previewMessageID,fm_previewMessageFolderType);
	}
}     

function refreshFolderStatus(_nodeID,mode) {
	var nodeToRefresh = 0;
	var mode2use = "none";
	if (document.getElementById('messageCounter')) {
		if (document.getElementById('messageCounter').innerHTML.search(eval('/'+lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	}
	if (_nodeID) nodeToRefresh = _nodeID;
	if (mode) {
		if (mode == "forced") {mode2use = mode;}
	}
	var activeFolders = getTreeNodeOpenItems(nodeToRefresh,mode2use);
	xajax_doXMLHTTP('felamimail.ajaxfelamimail.refreshFolderList', activeFolders);
//	if (fm_previewMessageID>0)
//	{
//		//setStatusMessage('<span style="font-weight: bold;">'+ lang_updating_view +'</span>');
//		//xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshMessagePreview",fm_previewMessageID,fm_previewMessageFolderType);
//	}
}

function refreshView() {
	if (document.getElementById('messageCounter').innerHTML.search(eval('/'+lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
	document.mainView.submit();
	document.getElementById('messageCounter').innerHTML = MessageBuffer;
}

function openComposeWindow(_url) {
    var Check=true;
    var _messageList;
    var cbAllMessages = document.getElementById('selectAllMessagesCheckBox').checked;
    resetMessageSelect();
    if (cbAllMessages == true) Check = confirm(lang_confirm_all_messages);
    if (cbAllMessages == true && Check == true)
    {
        _messageList = 'all';
    } else {
        _messageList = xajax.getFormValues('formMessageList');
    }
	sMessageList='';
	for (var i in _messageList['msg']) {
		//alert('eigenschaft:'+_messageList['msg'][i]);
		sMessageList=sMessageList+_messageList['msg'][i]+',';
		//sMessageList.concat(',');
	}
	if (sMessageList.length >0) {
		sMessageList= 'AsForward&forwardmails=1&folder='+activeFolderB64+'&reply_id='+sMessageList.substring(0,sMessageList.length-1);
	}
	//alert(sMessageList);
    if (Check == true)
    {
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

function fm_readMessage(_url, _windowName, _node) {
	var windowArray = _windowName.split('_');
	if (windowArray[0] == 'MessagePreview')
	{
		//document.getElementById('spanMessagePreview').innerHTML = '';
		if (document.getElementById('messageCounter').innerHTML.search(eval('/'+lang_updating_view+'/'))<0 ) {MessageBuffer = document.getElementById('messageCounter').innerHTML;}
		setStatusMessage('<span style="font-weight: bold;">'+ lang_updating_view +'</span>');
		fm_previewMessageID = windowArray[1];
		fm_previewMessageFolderType = windowArray[2];
		xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshMessagePreview",windowArray[1],windowArray[2]);
	} else {
		egw_openWindowCentered(_url, _windowName, 750, egw_getWindowOuterHeight());
	}
	trElement = _node.parentNode.parentNode.parentNode;
	trElement.style.fontWeight='normal';

	aElements = trElement.getElementsByTagName("a");
	aElements[0].style.fontWeight='normal';
	aElements[1].style.fontWeight='normal';
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshFolder");
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
	xajax_doXMLHTTP('felamimail.ajaxfelamimail.changeActiveAccount',_accountSelection.value);
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
  dialogmask.style.height = content.offsetHeight + 'px';
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

