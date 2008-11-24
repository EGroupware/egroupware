// variable used by "edit rule" dialog
var ruleEditWindow;
var ruleEditWindowTimer;
var ruleEditWindowTimeout=500;

//var searchesPending=0;

function fm_sieve_displayRuleEditWindow(_displayRuleEditWindowURL) {
	//ruleEditWindow = egw_openWindowCentered(_displayRuleEditWindowURL,'fm_sieve_ruleEditWindow','730','510',window.outerWidth/2,window.outerHeight/2);	
	ruleEditWindow = egw_openWindowCentered(_displayRuleEditWindowURL,'fm_sieve_ruleEditWindow','730','510');	
	if(ruleEditWindowTimer) {
		window.clearTimeout(ruleEditWindowTimer);
	}
	ruleEditWindowTimer = window.setInterval('fm_sieve_reloadRulesList()', ruleEditWindowTimeout);
}

function fm_sieve_reloadRulesList() {
//	searchesPending++;
//	document.title=searchesPending;
	if(ruleEditWindow.closed == true) {
		window.clearTimeout(ruleEditWindowTimer);
		//xajax_doXMLHTTP("felamimail.ajaxfelamimail.reloadAttachments", composeID);
		window.location.href=refreshURL;
	}
}

function fm_sieve_cancelReload() {
	if(ruleEditWindowTimer) {
		window.clearTimeout(ruleEditWindowTimer);
	}
}