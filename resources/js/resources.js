/**
 * Javascript for resources app
 */

/**
 * Calendar needs to have resource IDs prefixed with 'r' so it can tell them apart
 * from calendar entries.
 */
function view_calendar(action, senders) {
	for(var i = 0; i < senders.length; i++) {
		action.data.url += ',r'+senders[i].id;
	}
	nm_action(action, senders);
}
