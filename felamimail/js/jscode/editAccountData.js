function initEditAccountData()
{
	var activeElement;

	if(activeElement = document.getElementById('active')) {
		onchange_active(activeElement);
	}
}

function onchange_active(_checkbox) 
{
	identityInputs = document.getElementById('identity').getElementsByTagName('input');
	incomingInputs = document.getElementById('incoming_server').getElementsByTagName('input');
	outgoingInputs = document.getElementById('outgoing_server').getElementsByTagName('input');
	
	for(i=0; i<identityInputs.length; i++) {
		identityInputs[i].disabled = false;
	}
	if (allowAccounts) {
		if(_checkbox != null && _checkbox.checked) {
			for(i=0; i<incomingInputs.length; i++) {
				incomingInputs[i].disabled = false;
			}
			for(i=0; i<outgoingInputs.length; i++) {
				outgoingInputs[i].disabled = false;
			}
			document.getElementById('ic[folderstoshowinhome]').disabled =false;
			document.getElementById('ic[trashfolder]').disabled =false;
			document.getElementById('ic[sentfolder]').disabled =false;
			document.getElementById('ic[draftfolder]').disabled =false;
			document.getElementById('ic[templatefolder]').disabled =false;
		} else {
			for(i=0; i<incomingInputs.length; i++) {
				incomingInputs[i].disabled = true;
			}
			for(i=0; i<outgoingInputs.length; i++) {
				outgoingInputs[i].disabled = true;
			}
			document.getElementById('ic[folderstoshowinhome]').disabled =true;
			document.getElementById('ic[trashfolder]').disabled =true;
			document.getElementById('ic[sentfolder]').disabled =true;
			document.getElementById('ic[draftfolder]').disabled =true;
			document.getElementById('ic[templatefolder]').disabled =true;
		}

		onchange_og_smtpauth(document.getElementById('og[smtpauth]'));
		//onchange_ic_encryption(document.getElementById('ic[encryption]'));
		onchange_ic_enableSieve(document.getElementById('ic[enableSieve]'));
	} else {
		for(i=0; i<incomingInputs.length; i++) {
			incomingInputs[i].disabled = true;
		}
		for(i=0; i<outgoingInputs.length; i++) {
			outgoingInputs[i].disabled = true;
		}
		document.getElementById('ic[folderstoshowinhome]').disabled =true;
		document.getElementById('ic[trashfolder]').disabled =true;
		document.getElementById('ic[sentfolder]').disabled =true;
		document.getElementById('ic[draftfolder]').disabled =true;
		document.getElementById('ic[templatefolder]').disabled =true;
	}
}

function onchange_og_smtpauth(_checkbox) 
{
	isActive = document.getElementById('active').checked
	if(_checkbox != null && _checkbox.checked && isActive) {
		document.getElementById('og[username]').disabled = false;
		document.getElementById('og[password]').disabled = false;
	} else {
		document.getElementById('og[username]').disabled = true;
		document.getElementById('og[password]').disabled = true;
	}
}

function onchange_ic_enableSieve(_checkbox) 
{
	isActive = document.getElementById('active').checked
	if(_checkbox != null && _checkbox.checked && isActive) {
		document.getElementById('ic[sievePort]').disabled = false;
	} else {
		document.getElementById('ic[sievePort]').disabled = true;
	}
}


function onchange_ic_encryption(_checkbox) 
{
	isActive = document.getElementById('active').checked
	if(_checkbox != null && isActive) {
		if(_checkbox.value == 2 || _checkbox.value == 3) {
			if(document.getElementById('ic[port]').value == '143' || 
				document.getElementById('ic[port]').value == '')
			{
				document.getElementById('ic[port]').value = '993';
			}
			document.getElementById('ic[validatecert]').disabled = false;
		} else {
			if(document.getElementById('ic[port]').value == '993' || 
				document.getElementById('ic[port]').value == '')
			{
				document.getElementById('ic[port]').value = '143';
			}
			document.getElementById('ic[validatecert]').disabled = true;
		}
	}
}

function fm_deleteAccountData() {
	if(document.forms["userDefinedAccountList"].elements.length > 0) {
		var accountData = new Array();

		for(i=0; i<document.forms["userDefinedAccountList"].elements.length; i++) {
			if(document.forms["userDefinedAccountList"].elements[i].checked) {
				accountData.push(document.forms["userDefinedAccountList"].elements[i].value);
			}
		}

		if(accountData.length > 0) {
			if(confirm(lang_reallyDeleteAccountSettings)) {
				xajax_doXMLHTTP("felamimail.ajaxfelamimail.deleteAccountData", accountData.join(","));
				fm_refreshAccountDataTable();
			}
		}
	}
}

function fm_refreshAccountDataTable() {
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.refreshAccountDataTable");
}
