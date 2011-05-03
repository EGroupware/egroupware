/**
 * Javascript used on the infolog index page
 */


/**
 * Javascript handling for multiple entry actions
 */
function do_infolog_action(selbox) {
	if(selbox.value == "") return;
	var prefix = selbox.id.substring(0,selbox.id.indexOf('['));
	var popup = document.getElementById(prefix + '[' + selbox.value + '_popup]');
	if(popup) {
		popup.style.display = 'block';
		return;
	}
	selbox.form.submit();
	selbox.value = "";
}

function open_popup(_action, _senders)
{
	var prefix = 'exec';
	var popup = document.getElementById(prefix + '[' + _action.id + '_popup]');
	if(popup) {
		popup.style.display = 'block';
		return;
	}
}

/**
 * Hide popup and clear values
 */
function hide_popup(element, div_id, submit) {
	var prefix = element.id.substring(0,element.id.indexOf('['));
	var popup = document.getElementById(prefix+'['+div_id+']');

	// Get action command
	var action = div_id.substring(0,div_id.length-6);
	var action_input = document.getElementById('exec[nm][action]');

	// Hide popup
	if(popup) {
		popup.style.display = 'none';
	}

	// Submit form
	if(submit && action && action_input) {
		// Set action so it comes back
		action_input.value = action;
		element.form.submit();
	}
}
