var tab = new Tabs(4,'activetab','inactivetab','tab','tabcontent','','','tabpage');
var smtp = new Tabs(5,'activetab','inactivetab','smtp','smtpcontent','smtpselector','','');
var imap = new Tabs(6,'activetab','inactivetab','imap','imapcontent','imapselector','','');

function initAll() {
	tab.init();
	smtp.init();
	imap.init();
	var imapType = document.getElementsByName("imapsettings[imapType]")[0];
	var v=imapType.value; imap.display(imapType.value); imapType.value=v; 
	onchange_imapsettings(v, 'imapLoginType');
}

function ea_setIMAPDefaults(_imapType) {
	var currentInput = document.getElementsByName("imapsettings[" + _imapType + "][imapPort]")[0];
	onchange_imapsettings(_imapType, 'imapLoginType');
	if(_imapType > 1) {
		// imap
		if(currentInput.value == '110') {
			currentInput.value = '143';
		}
	} else {
		// pop3
		if(currentInput.value == '143') {
			currentInput.value = '110';
		}
	}
}

function onchange_imapsettings(_imapType,_varname) {
	var currentAuthType = document.getElementsByName("imapsettings[" + _imapType + "][" + _varname + "]")[0];
	var imapuser = document.getElementsByName("imapsettings[" + _imapType + "][imapAuthUsername]")[0];
	var imappw = document.getElementsByName("imapsettings[" + _imapType + "][imapAuthPassword]")[0];

	if (currentAuthType.value == "admin") {
		imapuser.disabled = false;
		imappw.disabled = false;
	} else {
		imapuser.disabled=true;
		imappw.disabled=true;
	}
}
