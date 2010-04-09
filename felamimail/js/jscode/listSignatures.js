var ruleSignatureWindowTimeout=500;
var signatureEditWindow;
var signatureEditWindowTimer;

function fm_getEditorContent()
{
	// Get the editor instance that we want to interact with.
	return FCKeditorAPI.GetInstance('signature').GetXHTML( true );
}

function fm_addSignature(_url)
{
	signatureEditWindow = egw_openWindowCentered(_url,'editSignature','750',egw_getWindowOuterHeight()/2,window.outerWidth/2,window.outerHeight/2);
	if(signatureEditWindowTimer) {
		window.clearTimeout(signatureEditWindowTimer);
	}
	signatureEditWindowTimer = window.setInterval('fm_checkSignatureEditWindow()', ruleSignatureWindowTimeout);
}

function fm_saveSignature() {
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.saveSignature", "save", 
		document.getElementById('signatureID').value, 
		document.getElementById('signatureDesc').value, 
		fm_getEditorContent(),
		document.getElementById('isDefaultSignature').checked
	);
	//fm_refreshSignatureTable();
	//window.setTimeout("window.close()", 1000);
}

function fm_applySignature() {
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.saveSignature", "apply", 
		document.getElementById('signatureID').value, 
		document.getElementById('signatureDesc').value, 
		fm_getEditorContent(),
		document.getElementById('isDefaultSignature').value
	);
	fm_refreshSignatureTable();
}

function fm_initEditLayout() {
	alert(document.body.offsetHeight);
}

function fm_deleteSignatures() {
	if(document.forms["signatureList"].elements.length > 0) {
		var signatures = new Array();

		for(i=0; i<document.forms["signatureList"].elements.length; i++) {
			if(document.forms["signatureList"].elements[i].checked) {
				signatures.push(document.forms["signatureList"].elements[i].value);
			}
		}

		if(signatures.length > 0) {
			if(confirm(lang_reallyDeleteSignatures)) {
				xajax_doXMLHTTP("felamimail.ajaxfelamimail.deleteSignatures", signatures.join(","));
				fm_refreshSignatureTable();
			}
		}
	}
}

function fm_refreshSignatureTable() {
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshSignatureTable");
}

function fm_checkSignatureEditWindow() {
	if(signatureEditWindow.closed == true) {
		window.clearTimeout(signatureEditWindowTimer);
		fm_refreshSignatureTable();
	}
}
