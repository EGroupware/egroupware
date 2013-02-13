/**
 * mail - static javaScript functions
 *
 * @link http://www.egroupware.org
 * @author klaus leithoff <kl at stylite.de>
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

var mail_doTimedRefresh;
var mail_refreshTimeOut = 1000*60*3;
mail_startTimerFolderStatusUpdate(mail_refreshTimeOut);

/**
 * mail_startTimerFolderStatusUpdate, timer functions, if the counter changes for the current folder
 * refresh the message list
 * @param timeout
 */
function mail_startTimerFolderStatusUpdate(_refreshTimeOut) {
	if (typeof _refreshTimeOut == 'undefined')
	{
		var minutes = egw.preference('refreshTime','mail');
		mail_refreshTimeOut = _refreshTimeOut= 1000*60*(minutes?minutes:3); // either the prefs or 3 Minutes
	}
	if (mail_refreshTimeOut > _refreshTimeOut) _refreshTimeOut = mail_refreshTimeOut;
	if(mail_doTimedRefresh) {
		window.clearTimeout(mail_doTimedRefresh);
	}
	if(_refreshTimeOut > 6000) {
		mail_doTimedRefresh = window.setInterval("mail_refreshFolderStatus()", _refreshTimeOut);
	}
}

function mail_refreshFolderStatus(_nodeID,mode) {
/*
	var nodeToRefresh = 0;
	var mode2use = "none";
	if (_nodeID) nodeToRefresh = _nodeID;
	if (mode) {
		if (mode == "forced") {mode2use = mode;}
	}
	var activeFolders = getTreeNodeOpenItems(nodeToRefresh,mode2use);
	queueRefreshFolderList(activeFolders);
*/
}

/**
 * Refresh given application _targetapp display of entry _app _id, incl. outputting _msg
 * 
 * Default implementation here only reloads window with it's current url with an added msg=_msg attached
 * 
 * @param string _msg message (already translated) to show, eg. 'Entry deleted'
 * @param string _app application name
 * @param string|int _id=null id of entry to refresh
 * @param string _type=null either 'edit', 'delete', 'add' or null
 */
var doStatus;
function app_refresh(_msg, _app, _id, _type)
{
	var bufferExists = false;
	window.clearInterval(doStatus); // whatever message was up to be activated
	//alert("app_refresh(\'"+_msg+"\',\'"+_app+"\',\'"+_id+"\',\'"+_type+"\')");
	//myCurrentMsg = mail_getMsg();
	//if (myCurrentMsg.length) {
		// clear message after some time
		myMessageBuffer = ""; //myCurrentMsg;
		bufferExists = true;
	//}
	mail_setMsg('<span style="font-weight: bold;">' +_msg+ '</span>');
	if (_app=='mail')
	{
		//we may want to trigger some actions, like modifying the grid, disable preview and stuff

		// TODO: more actions
	}
	if (bufferExists) doStatus = window.setInterval("egw_appWindow('mail').setMsg(myMessageBuffer);", 10000);
}

/**
 * mail_getMsg - gets the current Message
 * @return string
 */
function mail_getMsg()
{
	var msg_wdg = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('msg');
	if (msg_wdg)
	{
		return msg_wdg.valueOf().htmlNode[0].innerHTML;
	}
	return "";
}

/**
 * mail_setMsg - sets a Message, with the msg container, and controls if the container is enabled/disabled
 * @param string myMsg - the message
 */
function mail_setMsg(myMsg)
{
	var msg_wdg = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('msg');
	if (msg_wdg)
	{
		msg_wdg.set_value(myMsg);
		msg_wdg.set_disabled(myMsg.trim().length==0);
	}
}

/**
 * mail_emptyTrash
 */
function mail_emptyTrash() {
	app_refresh(egw.lang('empty trash'), 'mail');
	var request = new egw_json_request('mail.mail_ui.ajax_emptyTrash');
	request.sendRequest();
}

/**
 * mail_compressFolder
 */
function mail_compressFolder() {
	app_refresh(egw.lang('compress folder'), 'mail');
	var request = new egw_json_request('mail.mail_ui.ajax_compressFolder');
	request.sendRequest();
}

/**
 * mail_changeFolder
 */
function mail_changeFolder(folder,_widget) {
	//alert('change Folder called:'+folder);
	app_refresh(egw.lang('change folder'), 'mail');
	var img = _widget.getSelectedNode().images[0]; // fetch first image
	if (!(img.search(eval('/'+'NoSelect'+'/'))<0))
	{
		if (_widget.event_args.length==2)
		{
			folder = _widget.event_args[1];
			_widget.set_value(folder);
		}
		else
		{
			_widget.set_value('');
		}		
	}
	var nm = _widget.getRoot().getWidgetById('nm');
	nm.activeFilters["selectedFolder"] = folder;
	nm.applyFilters();
	var msg = _widget.getRoot().getWidgetById('msg');
	if (msg)
	{
		window.clearInterval(doStatus);
		displayname = _widget.getSelectedLabel();
		myMsg = (displayname?displayname:folder)+' '+egw.lang('selected');
		app_refresh(myMsg, 'mail');
	}
}

