//var tab = new Tabs(3,'activetab','inactivetab','tab','tabcontent','','','tabpage');
var selectedSuggestion;
var maxSuggestions=5;
var currentInputField;
var currentFolderSelectField;
var currentKeyCode;
var resultRows;
var results;
var keyDownCallback;
var searchesPending=0;
var resultboxVisible=false;
var searchActive=false;

// timer variables
var liveSearchTimer;
var keyboardTimeout=500;

var fileSelectorWindowTimer;
var fileSelectorWindowTimeout=500;

// windows
var fileSelectorWindow;

// special keys needed for navigation
var KEYCODE_TAB=9;
var KEYCODE_ENTER=13;
var KEYCODE_SHIFT=16;
var KEYCODE_ALT=18;
var KEYCODE_ESC=27;
var KEYCODE_LEFT=37;
var KEYCODE_UP=38;
var KEYCODE_RIGHT=39;
var KEYCODE_DOWN=40;

// disabled Keycodes
// quickserach input field
var disabledKeys1 = new Array(KEYCODE_TAB, KEYCODE_ENTER, KEYCODE_UP, KEYCODE_DOWN);
//var disabledKeys1 = new Array(KEYCODE_ENTER, KEYCODE_UP, KEYCODE_DOWN);

function initAll()
{
	//tab.init();
	//alert(document.onkeydown);
	var titletext = document.getElementById('fm_compose_subject').value;
	if (titletext.length>0) updateTitle(titletext);
}

function addEmail(to,email)
{
	//alert(to+': '+email);

	var tableBody = document.getElementById('addressRows');
	var tableRows = tableBody.getElementsByTagName('tr');

	var lastRow = tableRows[tableRows.length-1];
	var inputElements	= lastRow.getElementsByTagName('input');

	// look for empty fields above to fill in
	for(rowCounter = tableRows.length-1; rowCounter > 0; rowCounter--)
	{
		var rowBefore = tableRows[rowCounter-1];
		var inputBefore = rowBefore.getElementsByTagName('input');
		if(inputBefore[0].value == '')
		{
			lastRow = rowBefore;
			inputElements = inputBefore;
		}
		else
		{
			continue;
		}

	}

	if (inputElements[0].value != '')	// last row not empty --> create new
	{
		addAddressRow(lastRow);
		lastRow = tableRows[tableRows.length-1];
		inputElements = lastRow.getElementsByTagName('input');
	}
	// fill with email and set selectbox
	inputElements[0].value = email;
	var selectElements = lastRow.getElementsByTagName('select');
	selectElements[0].selectedIndex = to == 'cc' ? 1 : (to == 'bcc' ? 2 : 0);

	// add a new empty last row if there is no empty one at all
	lastRow = tableRows[tableRows.length-1];
	inputElements	= lastRow.getElementsByTagName('input');
	if (inputElements[0].value != '')
	{
		addAddressRow(lastRow);
	}
}

function addAddressRow(_tableRow)
{
	// the table body
	var tableBody = _tableRow.parentNode;

	// all table rows
	var tableRows = tableBody.getElementsByTagName('tr');


	var newTableRow		= _tableRow.cloneNode(true);
	var inputElements	= newTableRow.getElementsByTagName('input');
	var spanElements	= newTableRow.getElementsByTagName('span');
	var tdElements		= newTableRow.getElementsByTagName('td');

	//alert(inputElements.length);
	inputElements[0].value		= '';
	inputElements[0].disabled	= false;
	inputElements[0].style.width	= '99%';
	for(i=0; i<spanElements.length; i++) {
		if(spanElements[i].className == 'selectFolder') {
			spanElements[i].style.display	= 'none';
		}
	}

	tableBody.appendChild(newTableRow);

//	inputElements[0].focus();

	singleRowHeight = _tableRow.clientHeight;

	if (singleRowHeight == 0) singleRowHeight = 20;
	if(tableRows.length > 4) {
		neededHeight = singleRowHeight*4;
	} else {
		neededHeight = singleRowHeight*tableRows.length;
	}

	document.getElementById('addressDIV').style.height = neededHeight+'px';
	document.getElementById('addressDIV').scrollTop = document.getElementById('addressTable').clientHeight;
}

function fm_compose_addAttachmentRow(_tableRow)
{
	// the table body
	var tableBody = _tableRow.parentNode;

	// all table rows
	var tableRows = tableBody.getElementsByTagName('tr');


	var newTableRow		= _tableRow.cloneNode(true);
	var inputElements	= newTableRow.getElementsByTagName('input');

	//alert(inputElements.length);
//	inputElements[0].value		= '';

	if(tableRows.length < 5) {
		tableBody.appendChild(newTableRow);
	}

//	inputElements[0].focus();

	singleRowHeight = _tableRow.clientHeight;
	if (singleRowHeight == 0) singleRowHeight = 20;
	if(tableRows.length > 4) {
		neededHeight = singleRowHeight*4;
	} else {
		neededHeight = singleRowHeight*tableRows.length;
	}

	//document.getElementById('addressDIV').style.height = neededHeight+'px';
	//document.getElementById('addressDIV').scrollTop = document.getElementById('addressTable').clientHeight;
}

function deleteTableRow(_imageObject)
{
	// the table body
	tableBody = document.getElementById('addressRows');

	// all table rows
	tableRows = tableBody.getElementsByTagName('tr');

	if(tableRows.length > 4) {

		// the row where the clicked image is located
		tableRow = _imageObject.parentNode.parentNode;

		// the table body
		tableBody = document.getElementById('addressRows');
		tableBody.removeChild(tableRow);

		singleRowHeight = tableRows[0].clientHeight;
		if (singleRowHeight == 0) singleRowHeight = 20;
		if(tableRows.length > 4) {
			neededHeight = singleRowHeight*4;
		} else {
			neededHeight = singleRowHeight*tableRows.length;
		}

		document.getElementById('addressDIV').style.height = neededHeight+'px';
	} else {
		// the row where the clicked image is located
		tableRow = _imageObject.parentNode.parentNode;

		var inputElements	= tableRow.getElementsByTagName('input');
		inputElements[0].value	= '';

	}
}

function getPosLeft(_node) {
	var left=0;

	if(_node.offsetParent) {
		while (_node.offsetParent)
		{
			left += _node.offsetLeft;
			_node = _node.offsetParent;
		}
	} else if (_node.x) {
		left += _node.x;
	}

	return left;
}

function getPosTop(_node) {
	var top=0;

	if(_node.offsetParent) {
		while (_node.offsetParent) {
			top += _node.offsetTop;
			if(_node.parentNode.scrollTop) {
				top -= _node.parentNode.scrollTop
			}
			_node = _node.offsetParent;
		}
	} else if (_node.y) {
		left += _node.y;
	}

	return top;
}

function hideResultBox() {
	var resultBox;

	resultBox = document.getElementById('resultBox');
	resultBox.className = 'resultBoxHidden';

	//document.title='Search finnished';

	resultboxVisible=false;
}

function initResultBox(_inputField) {
	//var resultBox;

	currentInputField = _inputField;
	//document.title = resultRows.length;
	//document.title = "TEST";
	//resultBox = document.getElementById("resultBox");

	startCapturingEvents(keypressed);
}

function displayResultBox() {
	var top=0;
	var left=0;
	var width=0;

	var resultBox;

	//document.title='Search finnished';
	selectedSuggestion = -1;


	resultBox = document.getElementById('resultBox');
	if(searchActive) {
		top = getPosTop(currentInputField) + currentInputField.offsetHeight;
		left = getPosLeft(currentInputField);
		width = currentInputField.clientWidth;

		resultBox.style.top=top + 'px';
		resultBox.style.left=left + 'px';
		resultBox.style.width=width + 'px';

		resultBox.className = 'resultBoxVisible';
	}

	resultRows = resultBox.getElementsByTagName('div');

	resultboxVisible=true;
}

function startCapturingEvents(_callback) {
	document.onkeydown = keyDown;

	keyDownCallback=_callback;
	// nur fuer NS4
	//if (document.layers) {
	//	document.captureEvents(Event.KEYPRESS);
	//}
}

function stopCapturingEvents() {
	document.onkeydown = null;
	delete currentKeyCode;
	hideResultBox();
}

function keypressed(keycode, keyvalue) {
	if(liveSearchTimer) {
		window.clearTimeout(liveSearchTimer);
	}

	//_pressed = new Date().getTime();

	switch (keycode) {
	//	case KEYCODE_LEFT:
		case KEYCODE_UP:
			if(selectedSuggestion > 0) {
				selectSuggestion(selectedSuggestion-1);
			} else {
				selectSuggestion(resultRows.length-1);
			}
			break;

	//	case KEYCODE_RIGHT:
		case KEYCODE_DOWN:
			//document.title='down '+selectedSuggestion;
			//if(selectedSuggestion) {
			if(resultboxVisible) {
				//document.title='is selected';
				if(selectedSuggestion < resultRows.length-1) {
					selectSuggestion(selectedSuggestion+1);
				} else {
					selectSuggestion(0);
				}
			}
			break;

		case KEYCODE_ENTER:
			if(resultboxVisible) {
				currentInputField.value = results[selectedSuggestion];
				hideResultBox();
			}
			focusToNextInputField();
			searchActive=false;
			break;

		case KEYCODE_ESC:
			hideResultBox();
			break;

		case KEYCODE_TAB:
			if(resultboxVisible) {
				if( selectedSuggestion < resultRows.length-1) {
					selectSuggestion(selectedSuggestion+1);
				} else {
					selectSuggestion(0);
				}
			} else {
				rv = focusToNextInputField();
				if (rv == 'fm_compose_subject') 
				{
					currentKeyCode = 13;
					//alert(currentKeyCode);
				}
			}
			break;


		case KEYCODE_ALT:
		case KEYCODE_SHIFT:
			break;

		default:
			//_setValue(-1);
			liveSearchTimer = window.setTimeout('startLiveSearch()', keyboardTimeout);
			if(!currentInputField.parentNode.parentNode.nextSibling) {
				addAddressRow(currentInputField.parentNode.parentNode);
			}
			hideResultBox();
	}
}

function keyDown(e) {
	var pressedKeyID = document.all ? window.event.keyCode : e.which;
	var pressedKey = String.fromCharCode(pressedKeyID).toLowerCase();

	currentKeyCode=pressedKeyID;
	if(keyDownCallback!=null) {
		keyDownCallback(pressedKeyID, pressedKey);
	}
}

function startLiveSearch() {
	if(currentInputField.value.length > 2) {
		fm_blink_currentInputField();
		searchActive=true;
		//document.title='Search started';
		xajax_doXMLHTTP("felamimail.ajax_contacts.searchAddress",currentInputField.value);
	}
}

function selectSuggestion(_selectedSuggestion) {
	selectedSuggestion = _selectedSuggestion;
	for(i=0; i<resultRows.length; i++) {
		if(i == _selectedSuggestion) {
			resultRows[i].className = 'activeResultRow';
		} else {
			resultRows[i].className = 'inactiveResultRow';
		}
	}
}

function keycodePressed(_keyCode) {
	//alert(currentKeyCode +'=='+ _keyCode);
	if(currentKeyCode == _keyCode) {
		return false;
	} else {
		return true;
	}
}

function disabledKeyCodes(_keyCodes) {
	for (var i = 0; i < _keyCodes.length; ++i) {
		if(currentKeyCode == _keyCodes[i]) {
			return false;
		}
	}

	return true;
}

function updateTitle(_text) {
	if(_text.length>30) {
		_text = _text.substring(0,30) + '...';
	}

	document.title = _text;
}

function focusToNextInputField() {
	var nextRow;

	if(nextRow = currentInputField.parentNode.parentNode.nextSibling) {
		if(nextRow.nodeType == 3) {
			inputElements = nextRow.nextSibling.getElementsByTagName('input');
			inputElements[0].focus();
		} else {
			inputElements = nextRow.getElementsByTagName('input');
			inputElements[0].focus();
		}
		return 'addressinput';
	} else {
		document.getElementById('fm_compose_subject').focus();
		//document.doit.fm_compose_subject.focus();
		return 'fm_compose_subject';
	}
}

function focusToPrevInputField() {
    var prevRow;

    if(prevRow = currentInputField.parentNode.parentNode.previousSibling) {
        if(prevRow.nodeType == 3) {
            inputElements = prevRow.previousSibling.getElementsByTagName('input');
            inputElements[0].focus();
        } else {
            inputElements = prevRow.getElementsByTagName('input');
            inputElements[0].focus();
        }
    } else {
        document.getElementById('fm_compose_subject').focus();
        //document.doit.fm_compose_subject.focus();
    }
}

function keyDownSubject(keycode, keyvalue) {
}

function startCaptureEventSubjects(_inputField) {
	_inputField.onkeydown = keyDown;

	keyDownCallback = keyDownSubject;
}

function fm_compose_selectFolder() {
	egw_openWindowCentered(folderSelectURL,'fm_compose_selectFolder','350','500',window.outerWidth/2,window.outerHeight/2);
}

function OnLoadingStart(_nodeID) {
    return true;
}

function onNodeSelect(_folderName) {
	opener.fm_compose_setFolderSelectValue(_folderName);
	self.close();
}

function fm_compose_changeInputType(_selectBox) {
	var selectBoxRow	= _selectBox.parentNode.parentNode;
	var inputElements	= selectBoxRow.getElementsByTagName('input');
	var spanElements	= selectBoxRow.getElementsByTagName('span');
	var tdElements	= selectBoxRow.getElementsByTagName('td');

	if(_selectBox.value == 'folder') {
		inputElements[0].value		= '';
		for(i=0; i<spanElements.length; i++) {
			if(spanElements[i].className == 'selectFolder') {
				spanElements[i].style.display	= 'inline';
			}
		}
		currentFolderSelectField	= inputElements[0];
	} else {
		for(i=0; i<spanElements.length; i++) {
			if(spanElements[i].className == 'selectFolder') {
				spanElements[i].style.display	= 'none';
			}
		}
		delete currentFolderSelectField;
	}

	var tdElements	= selectBoxRow.getElementsByTagName('td');
}

function fm_compose_setFolderSelectValue(_folderName) {
	if(currentFolderSelectField) {
		currentFolderSelectField.value = _folderName;
		if(!currentFolderSelectField.parentNode.parentNode.nextSibling) {
			addAddressRow(currentFolderSelectField.parentNode.parentNode);
		}
		var nextSibling = currentFolderSelectField.parentNode.parentNode.nextSibling;
		if(nextSibling.nodeType == '3') {
			nextSibling = nextSibling.nextSibling;
		}
		nextSibling.getElementsByTagName('input')[0].focus();
	}
}

function fm_compose_displayFileSelector() {
	fileSelectorWindow = egw_openWindowCentered(displayFileSelectorURL,'fm_compose_fileSelector','550','100',window.outerWidth/2,window.outerHeight/2);
	if(fileSelectorWindowTimer) {
		window.clearTimeout(fileSelectorWindowTimer);
	}
	fileSelectorWindowTimer = window.setInterval('fm_compose_reloadAttachments()', fileSelectorWindowTimeout);
}

function fm_compose_displayVfsSelector() {
	fileSelectorWindow = egw_openWindowCentered(displayVfsSelectorURL,'fm_compose_vfsSelector','640','580',window.outerWidth/2,window.outerHeight/2);
	if(fileSelectorWindowTimer) {
		window.clearTimeout(fileSelectorWindowTimer);
	}
	fileSelectorWindowTimer = window.setInterval('fm_compose_reloadAttachments()', fileSelectorWindowTimeout);
}

function fm_compose_addFile() {
	document.getElementById('statusMessage').innerHTML = 'Sending file to server ...';
	document.getElementById('fileSelectorDIV1').style.display = 'none';
	document.getElementById('fileSelectorDIV2').style.display = 'inline';
	document.fileUploadForm.submit();
}

function fm_compose_reloadAttachments() {
	//searchesPending++;
	//document.title=searchesPending;
	if(fileSelectorWindow.closed == true) {
		window.clearTimeout(fileSelectorWindowTimer);
		xajax_doXMLHTTP("felamimail.ajaxfelamimail.reloadAttachments", composeID);
	}
}

function fm_compose_deleteAttachmentRow(_imageNode, _composeID, _attachmentID) {
	_imageNode.parentNode.parentNode.parentNode.parentNode.deleteRow(_imageNode.parentNode.parentNode.rowIndex);
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.deleteAttachment", _composeID, _attachmentID);
}

function fm_compose_selectSuggestionOnClick(_selectedSuggestion) {
	if(resultboxVisible) {
		currentInputField.value = results[_selectedSuggestion];
		hideResultBox();
	}
	focusToNextInputField();
	searchActive=false;
}

function fm_compose_saveAsDraft() {
	document.getElementById('saveAsDraft').value=1;
	document.doit.submit();
}

function fm_compose_printit() {
	document.getElementById('printit').value=1;
	document.doit.submit();
}

function fm_blink_currentInputField() {
	currentInputField.style.border = "1px solid #666666";
	window.setTimeout("currentInputField.style.border = ''", 100);
	window.setTimeout("currentInputField.style.border = '1px solid #666666'", 200);
	window.setTimeout("currentInputField.style.border = ''", 450);
}

function fm_compose_sendEMail() {
	var addressTable = document.getElementById('addressRows').rows;
	var addressSet = false;

	for (i=0; i<addressTable.length; i++) {
		if(addressTable.item(i).cells[2].firstChild.value != '') {
			addressSet = true;
		}
	}

	if(addressSet == true) {
		document.doit.submit();
	} else {
		alert(fm_compose_langNoAddressSet);
	}
}

// Set the state of the HTML/Plain toggles based on the _is_html field value
function fm_set_editor_toggle_states()
{
	// set the editor toggle based on the state of the editor

	var htmlFlag = document.getElementsByName('_is_html')[0];
	var toggles = document.getElementsByName('_editorSelect');
	for(var t=0; t<toggles.length; t++)
	{
		if (toggles[t].value == 'html')
		{
			toggles[t].checked = (htmlFlag.value == "1");
		}
		else
		{
			toggles[t].checked = (htmlFlag.value == "0");
		}
	}
}

// Toggle between the HTML and Plain Text editors
function fm_toggle_editor(toggler)
{
	var selectedEditor = toggler.value;

	// determine the currently displayed editor

	var htmlFlag = document.getElementsByName('_is_html')[0];
	var mimeType = document.getElementById('mimeType');
	var currentEditor = htmlFlag.value;
	var currentMode ='';
	if (currentEditor == 1)
	{
		currentMode='html';
	}
	else
	{
		currentMode='plain';
	}

	if (selectedEditor == currentMode)
	{
		return;
	}

	// do the appropriate conversion

	if (selectedEditor == 'html')
	{
		var composeElement = document.getElementsByName('body')[0];
		var existingPlainText = composeElement.value;
		var htmlText = "<pre>" + existingPlainText + "</pre>";
		xajax_doXMLHTTP("felamimail.ajaxfelamimail.toggleEditor", composeID,htmlText,'simple');
		htmlFlag.value = "1";
		mimeType.value = "html";
	}
	else
	{
		var editor = FCKeditorAPI.GetInstance('body');
	    var existingHtml = editor.GetHTML();
		delete editor;
		xajax_doXMLHTTP("felamimail.ajaxfelamimail.toggleEditor", composeID,existingHtml,'ascii');
		//removeFCK('body');
	    htmlFlag.value = "0";
		mimeType.value = "text";
	}
}
function removeFCK(fieldId)
{
       var configElement = document.getElementById(fieldId+'___Config');
       var frameElement =  document.getElementById(fieldId+'___Frame');
       //var textarea = document.forms[this].elements[fieldId];
       var editor = FCKeditorAPI.GetInstance(fieldId);

       //if (editor!=null && configElement && frameElement && configElement.parentNode==textarea.parentNode && frameElement.parentNode==textarea.parentNode && document.removeChild)
	   if (editor!=null && configElement && frameElement && document.removeChild)
       {
          editor.UpdateLinkedField();
          configElement.parentNode.removeChild(configElement);
          frameElement.parentNode.removeChild(frameElement);
          //textarea.style.display = '';
          delete FCKeditorAPI.Instances[fieldId];
          delete editor;
       }

}
function changeIdentity(SelectedId)
{
	//alert(SelectedId.value);
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.setComposeSignature", SelectedId.value);
}
function setSignature(SelectedId)
{
	for (i = 0; i < document.doit.signatureID.length; ++i)
		if (document.doit.signatureID.options[i].value == SelectedId)
			document.doit.signatureID.options[i].selected = true;
		//else
		//	document.doit.signatureID.options[i].selected = false;
}
