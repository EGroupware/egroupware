var tab = new Tabs(4,'activetab','inactivetab','tab','tabcontent','','','tabpage');
var smtp = new Tabs(5,'activetab','inactivetab','smtp','smtpcontent','smtpselector','','');
var imap = new Tabs(6,'activetab','inactivetab','imap','imapcontent','imapselector','','');

function initAll() {
	tab.init();
	smtp.init();
	imap.init();
}

function ea_setIMAPDefaults(_imapType) {
	var currentInput = document.getElementsByName("imapsettings[" + _imapType + "][imapPort]")[0];

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