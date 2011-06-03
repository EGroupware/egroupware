/**
 * EGroupware infolog javascript code used on index page
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @link http://www.egroupware.org
 * @version $Id$
 */

/**
 * Confirm delete
 * If entry has children, asks if you want to delete children too
 *
 * @param _action
 * @param _senders
 */
function confirm_delete(_action, _senders)
{
	var children = false;
	var child_button = document.getElementById(egw_globalObjectManager.getObjectById('infolog.index.rows').etemplate_var_prefix+'[delete_sub]');
	if(child_button) {
		for(var i = 0; i < _senders.length; i++) {
			if ($(_senders[i].iface.node).hasClass('rowHasSubs')) {
				children = true;
				break;
			}
		}
		child_button.style.display = children ? 'block' : 'none';
	}
	nm_open_popup(_action, _senders);
}
