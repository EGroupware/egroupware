// var tab = new Tabs(3,'activetab','inactivetab','tab','tabcontent','','','tabpage');
// var smtp = new Tabs(2,'activetab','inactivetab','smtp','smtpcontent','smtpselector','','smtppage');
// var imap = new Tabs(3,'activetab','inactivetab','imap','imapcontent','imapselector','','imappage');

var headerFullSize=false;

var headerDIVHeight;

var bodyDIVTop;

function sendNotify (uid) {
	ret = confirm(lang_sendnotify);
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.sendNotify",uid,ret);	
}

function goToMessage(url) {
	window.location.href = url;
	opener.refresh();
}

function initAll()
{
	//tab.init();

	var headerTable = document.getElementById('headerTable');
	var headerDIV = document.getElementById('headerDIV');
	if (headerTable) {
		if (headerTable.clientHeight) {
			if(headerTable.clientHeight > headerDIV.clientHeight) {
				var moreDIV	= document.getElementById('moreDIV');
				moreDIV.style.display = 'block';
			}
		}
	}
	if(is_ie) {
		fm_resizeBodyDIV();
		window.onresize = fm_resizeBodyDIV;
	}
	updateTitle();
}

function updateTitle() {
	var _text = document.getElementById('subjectDATA').firstChild ? document.getElementById('subjectDATA').firstChild.nodeValue:'';
    if(_text.length>40) {
        _text = _text.substring(0,40) + '...';
    }

    document.title = _text;
}

function toggleHeaderSize() {
	var toogleSPAN = document.getElementById('toogleSPAN');

	var headerTable = document.getElementById('headerTable');
	var headerDIV = document.getElementById('headerDIV');
	var bodyDIV	= document.getElementById('bodyDIV');

	if(!headerFullSize) {
		var navbarDIV	= document.getElementById('navbarDIV');
		var subjectDIV	= document.getElementById('subjectDIV');

		headerDIVHeight	= headerDIV.clientHeight;
		bodyDIVTop = bodyDIV.offsetTop;
		headerDIV.style.height = headerTable.clientHeight + 'px';		

		bodyDIV.style.top = 4 + navbarDIV.clientHeight + subjectDIV.clientHeight + headerDIV.clientHeight + 'px';

		headerFullSize=true;
		toogleSPAN.innerHTML = '-';
	} else {
		headerFullSize=false;
		toogleSPAN.innerHTML = '+';

		headerDIV.style.height = headerDIVHeight + 'px';
		bodyDIV.style.top = bodyDIVTop + 'px';
	}
}

function fm_resizeBodyDIV() {
	var attachmentDIV;
	var bodyDIV     = document.getElementById('bodyDIV');
	var attachmentDIV     = document.getElementById('attachmentDIV');

	if(attachmentDIV = document.getElementById('attachmentDIV')) {
		 bodyDIV.style.height = attachmentDIV.offsetTop - bodyDIV.offsetTop + 'px';
	} else {
		bodyDIV.style.height = egw_getWindowInnerHeight() - bodyDIV.offsetTop - 2 + 'px';
	}
}

function fm_displayHeaderLines(_url) {
	egw_openWindowCentered(_url,'fm_display_headerLines','700','600',window.outerWidth/2,window.outerHeight/2);
}
