/**
 * Javascript for Admin / Global categories
 */

// Record original value
var cat_original_owner;
var permission_prompt;

/**
 * Check to see if admin has taken away access to a category
 */
function check_owner(element_id) {
	var checkboxes = $(':checkbox', document.getElementById(element_id));
	var all_users = $(document.getElementById(element_id + '[0]'));

	// If they checked all users, uncheck the others
	if(all_users.length > 0 && all_users.attr("checked")) {
		checkboxes.attr("checked",false); 
		all_users.attr("checked", true);
		checkboxes = $(':checkbox', document.getElementById(element_id)).filter(':checked');
		return true;
	}

	// Find out what changed
	var seen = [], diff = [], labels = [];
	for ( var i = 0; i < cat_original_owner.length; i++) {
		var checkbox = checkboxes.filter('[value="'+cat_original_owner[i]+'"]');
		if(checkbox.filter(':checked').length == 0 && checkbox.get(0) != undefined) {
			diff.push(cat_original_owner[i]);
			labels.push($(checkbox.get(0).nextSibling).text());
		}
	}

	// Single selection? 
	if(checkboxes.length == 0) {
		var new_group = $(document.getElementById(element_id));
		if(new_group.attr('value') != 0 && cat_original_owner.length > 0) {
			diff.push(cat_original_owner[0]);
			var selector = 'option[value="'+cat_original_owner[0]+'"]';
			labels.push("\n"+$(selector, new_group).text());
		}
	}


	// Somebody will lose permission, give warning.
	if(diff.length > 0) {
		var msg = permission_prompt;
		for( var i = 0; i < labels.length; i++) {
			msg += labels[i];
		}
		return confirm(msg);
	}
	return true;
}
