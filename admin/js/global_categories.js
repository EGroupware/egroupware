/**
 * Javascript for Admin / Global categories
 */

// Record original value
var cat_original_owner;
var permission_prompt;

/**
 * Check to see if admin has taken away access to a category
 */
function check_owner(button) {
	var select_owner = button.getRoot().getWidgetById('owner')
	var owner = select_owner.get_value();
	if(typeof owner != 'object')
	{
		owner = [owner];
	}
	var all_users = owner.indexOf('0') >= 0;

	// If they checked all users, uncheck the others
	if(all_users) {
		select_owner.set_value(['0']);
		return true;
	}

	// Find out what changed
	var seen = [], diff = [], labels = [];
	var cat_original_owner = select_owner.getArrayMgr('content').getEntry('owner');
	if(typeof cat_original_owner != "object")
	{
		cat_original_owner = [cat_original_owner];
	}
	for ( var i = 0; i < cat_original_owner.length; i++) {
		if(owner.indexOf(cat_original_owner[i]) < 0)
		{
			var checkbox = $j('input[value="'+cat_original_owner[i]+'"]',select_owner.node);
			diff.push(cat_original_owner[i]);
			labels.push($j(checkbox.get(0).nextSibling).text());
		}
	}

	// Somebody will lose permission, give warning.
	if(diff.length > 0) {
		var msg = egw.lang('Removing access for groups may cause problems for data in this category.  Are you sure?  Users in these groups may no longer have access:');
		for( var i = 0; i < labels.length; i++) {
			msg += labels[i];
		}
		return et2_dialog.confirm(button,msg);
		
	}
	return true;
}

/**
 * Show icon based on icon-selectbox, hide placeholder (broken image), if no icon selected
 */
function change_icon(widget)
{
	var img = widget.getRoot().getWidgetById('icon_url');
	
	if (img)
	{
		img.set_src(widget.getValue());
	}
}
