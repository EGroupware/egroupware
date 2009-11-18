function dragSidebar()
{
	if(!dd.obj.sideresizeStart) { dd.obj.sideresizeStart = dd.obj.x; }
	var mainbox = dd.elements.thesideboxcolumn;
	mainbox.resizeTo(dd.obj.x-dd.obj.sideresizeStart+parseInt(dd.obj.my_sideboxwidth), mainbox.h);
}

function dropSidebar()
{
	var mainbox = dd.elements.thesideboxcolumn;
	xajax_doXMLHTTP("preferences.ajaxpreferences.storeEGWPref",currentapp,"idotssideboxwidth",mainbox.w);
}