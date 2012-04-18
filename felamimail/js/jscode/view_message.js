// var tab = new Tabs(3,'activetab','inactivetab','tab','tabcontent','','','tabpage');
// var smtp = new Tabs(2,'activetab','inactivetab','smtp','smtpcontent','smtpselector','','smtppage');
// var imap = new Tabs(3,'activetab','inactivetab','imap','imapcontent','imapselector','','imappage');

var headerFullSize=false;

var headerDIVHeight;

var bodyDIVTop;

var do_onunload = true;

function getUrlPart(url, name )
{
  name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
  var regexS = "[\\?&]"+name+"=([^&#]*)";
  var regex = new RegExp( regexS );
  var results = regex.exec( url );
  if( results == null )
    return "";
  else
    return results[1];
}

function sendNotify (uid) {
	do_onunload = false;
	ret = confirm(lang_sendnotify);
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.sendNotify",uid,ret);	
}

function goToMessage(url) {
	do_onunload = false;
	//alert(getUrlPart(window.location.href,'uid'));
	var oldUid = getUrlPart(window.location.href,'uid');
	var newUid = getUrlPart(url,'uid');
	window.opener.mail_parentRefreshListRowStyle(oldUid, newUid);
	window.location.href = url;
	//opener.refresh();
}

function initAll()
{
	//tab.init();
	if (egw_getWindowOuterHeight()<750)
	{
		var fm_height = screen.availHeight/100*75;
		var resizeHeight = fm_height-egw_getWindowOuterHeight();
		//alert(fm_height+' resize By:0,'+resizeHeight);
		if (fm_height >= 750) window.resizeBy(0,resizeHeight);
	}

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
	do_onunload = true;
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

function mailview_deleteMessages(_messageList) {
	var divMessageList = opener.document.getElementById('divMessageList');
	xajax_doXMLHTTPsync("felamimail.ajaxfelamimail.deleteMessages",_messageList,false);
	if (typeof divMessageList != 'undefined')
	{
		//divMessageList.innerHTML = '';
		for(var i=0;i<_messageList['msg'].length;i++) {
			_id = _messageList['msg'][i];
			var dataElem = opener.mailGrid.dataRoot.getElementById(_id);
			if (dataElem)
			{
				//dataElem.clearData();
				dataElem.addClass('deleted');
				//dataElem.parentActionObject.remove();
				opener.app_refresh(opener.lang_deleting_messages,'felamimail',_id,'delete');
			}
		}
		opener.refresh();
	}
	this.close();
	//egw_appWindow('felamimail').
}

function mailview_undeleteMessages(_messageList) {
	var divMessageList = opener.document.getElementById('divMessageList');
	//if (typeof divMessageList != 'undefined') divMessageList.innerHTML = '';
	if (typeof divMessageList != 'undefined')
	{
		//divMessageList.innerHTML = '';
		for(var i=0;i<_messageList['msg'].length;i++) {
			_id = _messageList['msg'][i];
			var dataElem = opener.mailGrid.dataRoot.getElementById(_id);
			if (dataElem)
			{
				//dataElem.clearData();
				dataElem.removeClass('deleted');
			}
		}
	}
	xajax_doXMLHTTP("felamimail.ajaxfelamimail.undeleteMessages",_messageList,false);
	//egw_appWindow('felamimail').
}
