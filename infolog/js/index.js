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
	var child_button = jQuery('#delete_sub').get(0) || jQuery('[id*="delete_sub"]').get(0);
	if(child_button) {
		for(var i = 0; i < _senders.length; i++) {
			if ($j(_senders[i].iface.node).hasClass('rowHasSubs')) {
				children = true;
				break;
			}
		}
		child_button.style.display = children ? 'block' : 'none';
	}
	nm_open_popup(_action, _senders);
}

/**
 * Show or hide details by changing the CSS class
 */
function show_details(show)
{
	// Show / hide descriptions
        egw.css(".et2_box.infoDes","display:" + (show ? "block;" : "none;"));
}
