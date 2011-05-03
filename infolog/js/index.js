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

	// call regular nm_action to transmitt action and senders correct
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
}
