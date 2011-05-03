/**
 * EGroupware infolog javascript code used on index page
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @link http://www.egroupware.org
 * @version $Id$
 */

var infolog_popup_action, infolog_popup_senders;

/**
 * Open popup for a certain action requiring further input
 * 
 * @param _action
 * @param _senders
 */
function open_popup(_action, _senders)
{
	var prefix = 'exec';
	var popup = document.getElementById(prefix + '[' + _action.id + '_popup]');

	if (popup) {
		infolog_popup_action = _action;
		infolog_popup_senders = _senders;
		popup.style.display = 'block';
	}
}

/**
 * Submit a popup action
 */
function submit_popup(button)
{
	button.form.submit_button.value = button.name;	// set name of button (sub-action)

	// call regular nm_action to transmit action and senders correct
	nm_action(infolog_popup_action, infolog_popup_senders);
}

/**
 * Hide popup
 */
function hide_popup(element, div_id) 
{
	var prefix = element.id.substring(0,element.id.indexOf('['));
	var popup = document.getElementById(prefix+'['+div_id+']');

	// Hide popup
	if(popup) {
		popup.style.display = 'none';
	}
	return false;
}

/**
 * Confirm delete
 * If entry has children, asks if you want to delete children too
 *
 * @param _action
 * @param _senders
 */
function confirm_delete(_action, _senders)
{
	var child_button = document.getElementById('exec[delete_sub]');
	var d_prompt = document.getElementById('delete_prompt');
	if(d_prompt.previousSibling) $(d_prompt.previousSibling).remove();
	var children = false;
	if(child_button) {
		for(var i = 0; i < _senders.length; i++) {
			children = children || $(_senders[i].iface.node).hasClass('rowHasSubs');
		}
		child_button.style.display = children ? 'block' : 'none';
		
		$(d_prompt).text(_senders.length > 1 ? _action.data.confirm_multiple : _action.data.confirm);
	}
	open_popup(_action, _senders);
}
