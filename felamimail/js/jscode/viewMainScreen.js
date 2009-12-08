function setStatusMessage(_message) {
	document.getElementById('messageCounter').innerHTML = '<table cellpadding="0" cellspacing="0"><tr><td><img src="'+ activityImagePath +'"></td><td>&nbsp;' + _message + '</td></tr></table>';
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

function emptyTrash() {
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

function onNodeSelect(_nodeID) {
//alert(_nodeID)
	var Check = true;
	if(tree.getUserData(_nodeID, 'folderName')) {
		if(document.getElementsByName("folderAction")[0].value == "moveMessage") {
			if (prefAskForMove == 1) Check = confirm(lang_askformove + tree.getUserData(_nodeID, 'folderName'));
			if (Check == true && document.getElementById('selectAllMessagesCheckBox').checked == true) Check = confirm(lang_confirm_all_messages);
			if (Check == true)
			{
				if (document.getElementById('selectAllMessagesCheckBox').checked == true) {
					resetMessageSelect();
					formData = 'all';
				} else {
					resetMessageSelect();
					formData = xajax.getFormValues('formMessageList');
				}
				setStatusMessage(movingMessages +' <span style="font-weight: bold;">'+ tree.getUserData(_nodeID, 'folderName') +'</span>');
				document.getElementById('divMessageList').innerHTML = '';
				xajax_doXMLHTTP("felamimail.ajaxfelamimail.moveMessages", _nodeID, formData);
			} else {
				resetMessageSelect();
				for(i=0; i< document.forms.formMessageList.elements.length; i++) {
					if(document.forms.formMessageList.elements[i].checked) {
						document.forms.formMessageList.elements[i].checked = false;
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
}     

function refreshFolderStatus(_nodeID,mode) {
	var nodeToRefresh = 0;
	var mode2use = "none";
	if (_nodeID) nodeToRefresh = _nodeID;
	if (mode) {
		if (mode == "forced") {mode2use = mode;}
	}
	var activeFolders = getTreeNodeOpenItems(nodeToRefresh,mode2use);
	xajax_doXMLHTTP('felamimail.ajaxfelamimail.refreshFolderList', activeFolders);
}

function refreshView() {
	document.mainView.submit();
}

function openComposeWindow(_url) {
	egw_openWindowCentered(_url,'test',700,egw_getWindowOuterHeight());
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
	egw_openWindowCentered(_url, _windowName, 750, egw_getWindowOuterHeight());
	trElement = _node.parentNode.parentNode.parentNode;
	trElement.style.fontWeight='normal';

	aElements = trElement.getElementsByTagName("a");
	aElements[0].style.fontWeight='normal';
	aElements[1].style.fontWeight='normal';
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshFolder");
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

