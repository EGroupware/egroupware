/**
 * mail - static javaScript functions
 *
 * @link http://www.egroupware.org
 * @author klaus leithoff <kl at stylite.de>
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
	//myCurrentMsg = getMsg();
	//if (myCurrentMsg.length) {
		// clear message after some time
		myMessageBuffer = ""; //myCurrentMsg;
		bufferExists = true;
	//}
	setMsg('<span style="font-weight: bold;">' +_msg+ '</span>');
	if (_app=='mail')
	{
		//we may want to trigger some actions, like modifying the grid, disable preview and stuff

		// TODO: more actions
	}
	if (bufferExists) doStatus = window.setInterval("egw_appWindow('mail').setMsg(myMessageBuffer);", 10000);
}

/**
 * getMsg - gets the current Message
 * @return string
 */
function getMsg()
{
	var msg_wdg = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('msg');
	if (msg_wdg)
	{
		return msg_wdg.valueOf().htmlNode[0].innerHTML;
	}
	return "";
}

/**
 * setMsg - sets a Message, with the msg container, and controls if the container is enabled/disabled
 * @param string myMsg - the message
 */
function setMsg(myMsg)
{
	var msg_wdg = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('msg');
	if (msg_wdg)
	{
		msg_wdg.set_value(myMsg);
		msg_wdg.set_disabled(myMsg.trim().length==0);
	}
}

/**
 * emptyTrash
 */
function emptyTrash() {
	app_refresh(egw.lang('empty trash'), 'mail');
	var request = new egw_json_request('mail.mail_ui.ajax_emptyTrash');
	request.sendRequest();
}

/**
 * changeFolder
 */
function changeFolder(folder,_widget) {
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

