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
	resetMessageSelect();

	setStatusMessage('<span style="font-weight: bold;">' + lang_deleting_messages + '</span>');
	document.getElementById('divMessageList').innerHTML = '';
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.deleteMessages",_messageList);
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

function onNodeSelect(_nodeID) {
	var Check = true;
	if(tree.getUserData(_nodeID, 'folderName')) {
		if(document.getElementsByName("folderAction")[0].value == "moveMessage") {
			if (prefAskForMove == 1) Check = confirm(lang_askformove + tree.getUserData(_nodeID, 'folderName'));
			if (Check == true)
			{
				resetMessageSelect();
				formData = xajax.getFormValues('formMessageList');
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
		}
	}
}

function quickSearch() {
	var searchType;
	var searchString;
	var status;

	resetMessageSelect();

	setStatusMessage('<span style="font-weight: bold;">' + lang_updating_view + '</span>');
	document.getElementById('divMessageList').innerHTML = '';

	document.getElementById('quickSearch').select();

	searchType = document.getElementById('searchType').value;
	searchString = document.getElementById('quickSearch').value;
	status 	= document.getElementById('status').value;

	xajax_doXMLHTTP('felamimail.ajaxfelamimail.quickSearch', searchType, searchString, status);
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

	setStatusMessage('<span style="font-weight: bold;">Applying filter '+_selectBox.options[_selectBox.selectedIndex].text+'</span>');
	document.getElementById('divMessageList').innerHTML = '';

	document.getElementById('quickSearch').value = '';

	xajax_doXMLHTTP('felamimail.ajaxfelamimail.extendedSearch',_selectBox.options[_selectBox.selectedIndex].value);
}

function flagMessages(_flag)
{
	var selectedMessages = new Array();
	var selectedCheckBoxes = new Array();
	
	resetMessageSelect();
	
	//alert(_messageList);
	for(i=0; i< document.forms.formMessageList.elements.length; i++) {
		if(document.forms.formMessageList.elements[i].checked) {
			selectedMessages.push(document.forms.formMessageList.elements[i].value);
			selectedCheckBoxes.push(document.forms.formMessageList.elements[i]);
		}
	}
//	alert(_flag);
//	switch(_flag) {
//		case 'read':
//			for(i=0; i< selectedMessages.length; i++) {
//				document.getElementById('row_' + selectedMessages[i]).style.fontWeight='normal';
//				selectedCheckBoxes[i].checked = false;
//			}
//			break;
//		case 'unread':
//			for(i=0; i< selectedMessages.length; i++) {
//				document.getElementById('row_' + selectedMessages[i]).style.fontWeight='bold';
//				selectedCheckBoxes[i].checked = false;
//			}
//			break;
//	}

	setStatusMessage('<span style="font-weight: bold;">' + lang_updating_message_status + '</span>');
	document.getElementById('divMessageList').innerHTML = '';
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.flagMessages", _flag, selectedMessages);
	
	fm_startTimerMessageListUpdate(refreshTimeOut);
}

function resetMessageSelect()
{
	document.getElementById('messageCheckBox').checked = false;
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

function refreshFolderStatus() {
	xajax_doXMLHTTP('felamimail.ajaxfelamimail.refreshFolderList');
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

