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
 * emptyTrash
 */
function emptyTrash() {
	var request = new egw_json_request('mail.mail_ui.ajax_emptyTrash');
	request.sendRequest();
}

/**
 * changeFolder
 */
function changeFolder(folder,_widget) {
	//alert('change Folder called:'+folder);
	var nm = _widget.getRoot().getWidgetById('nm');
	nm.activeFilters["selectedFolder"] = folder;
	nm.applyFilters();
}

