/**
 * EGroupware eTemplate2 - JS Nextmatch object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright Stylite 2012
 * @version $Id$
 */

/**
 * Default action for nextmatch rows, runs action specified _action.data.nm_action: see nextmatch_widget::egw_actions()
 *
 * @param _action action object with attributes caption, id, nm_action, ...
 * @param _senders array of rows selected
 */
function nm_action(_action, _senders, _target, _ids)
{
	// ignore checkboxes, unless they have an explicit defined nm_action
	if (_action.checkbox && (!_action.data || typeof _action.data.nm_action == 'undefined')) return;

	if (typeof _action.data == 'undefined' || !_action.data) _action.data = {};
	if (typeof _action.data.nm_action == 'undefined' && _action.type == 'popup') _action.data.nm_action = 'submit';

	if(typeof _ids == 'undefined')
	{
		// Get IDs from nextmatch - nextmatch is in the top of the action tree
		// @see et2_extension_nextmatch_controller._initActions()
		var nm = null;
		var action = _action;
		while(nm == null && action != null)
		{
			if(action.data != null && action.data.nextmatch)
			{
				nm = action.data.nextmatch;
			}
			action = action.parent;
		}
		if(nm)
		{
			_ids = nm.getSelection();
			_action.data.nextmatch = nm;
		}
	}

	// Translate the internal uids back to server uids
	var idsArr = _ids.ids;
	for (var i = 0; i < idsArr.length; i++)
	{
		idsArr[i] = idsArr[i].split("::").pop();
	}

	// Calculate the ids parameters
	var ids = "";
	for (var i = 0; i < idsArr.length; i++)
	{
		var id = idsArr[i];
		ids += (id.indexOf(',') >= 0 ? '"'+id.replace(/"/g,'""')+'"' : id) +
			((i < idsArr.length - 1) ? "," : "");
	}
	//console.log(_action); console.log(_senders);

	var mgr = _action.getManager();

	var url = '#';
	if (typeof _action.data.url != 'undefined')
	{
		url = _action.data.url.replace(/(\$|%24)id/,encodeURIComponent(ids));
	}

	var target = null;
	if (typeof _action.data.target != 'undefined')
	{
		target = _action.data.target;
	}

	switch(_action.data.nm_action)
	{
		case 'alert':
			alert(_action.caption + " (\'" + _action.id + "\') executed on rows: " + ids);
			break;

		case 'location':
			if (typeof _action.data.targetapp != 'undefined')
			{
				top.egw_appWindowOpen(_action.data.targetapp, url);
			}
			else if(target)
			{
				egw.open_link(url, target, _action.data.width ? _action.data.width+'x'+_action.data.height : false);
			}
			else
			{
				window.location.href = url;
			}
			break;

		case 'popup':
			egw.open_link(url,target,_action.data.width+'x'+_action.data.height);
			break;

		case 'egw_open':
			var params = _action.data.egw_open.split('-');	// type-appname-idNum (idNum is part of id split by :), eg. "edit-infolog"

			var egw_open_id = idsArr[0];
			var type = params.shift();
			var app = params.shift();
			if (typeof params[2] != 'undefined')
			{
					if(egw_open_id.indexOf(':') >= 0)
					{
						egw_open_id = egw_open_id.split(':')[params.shift(params[2])];
					}
					else
					{
						// Discard
						params.shift(params[2]);
					}
			}

			// Re-join, in case extra has a -
			var extra = params.join('-');
			egw(app,window).open(egw_open_id,app,type,extra,target);
			break;

		case 'open_popup':
			// open div styled as popup contained in current form and named action.id+'_popup'
			if (nm_popup_action == null)
			{
				nm_open_popup(_action, _ids.ids);
				break;
			}
			// fall through, if popup is open --> submit form
		case 'submit':
			var checkboxes = mgr.getActionsByAttr("checkbox", true);
			var checkbox_values = {};
			if (checkboxes)
				for (var i in checkboxes)
					checkbox_values[checkboxes[i].id] = checkboxes[i].checked;

			var nextmatch = _action.data.nextmatch;
			if(!nextmatch && _senders.length)
			{
				// Pull it from deep within, where it was stuffed in et2_dataview_controller_selection._attachActionObject()
				nextmatch = _senders[0]._context._widget;
			}
			if(nextmatch)
			{
				// Fake a getValue() function
				var old_value = nextmatch.getValue;
				var value = nextmatch.getValue();
				jQuery.extend(value, this.activeFilters, {
					"selected": idsArr,
					"select_all": _ids.all,
					"checkboxes": checkbox_values,
				});
				value[nextmatch.options.settings.action_var]= _action.id;

				nextmatch.getValue = function() {
					return value;
				}

				if(_action.data.nm_action == 'open_popup')
				{
					// Force nextmatch to re-load affected rows
					nextmatch.refresh(idsArr);
				}

				// downloads need a regular submit via POST (no Ajax)
				if (_action.data.postSubmit)
				{
					nextmatch.getInstanceManager().postSubmit();
				}
				else
				{
					nextmatch.getInstanceManager().submit();
				}

				if(_action.data.nm_action == 'open_popup')
				{
					// Reset action in case there's another one
					nextmatch.getValue = old_value;
				}
			}
			else
			{
				egw().debug("error", "Missing nextmatch widget, could not submit", _action);
			}
			break;
	}
}

/**
 * Callback to check if a certain field (_action.data.fieldId) is (not) equal to given value (_action.data.fieldValue)
 *
 * If field is not found, we return false too!
 *
 * @param _action egwAction object, we use _action.data.fieldId to check agains _action.data.fieldValue
 * @param _senders array of egwActionObject objects
 * @param _target egwActionObject object, get's called for every object in _senders
 * @returns boolean true if field found and has specified value, false otherwise
 */
function nm_compare_field(_action, _senders, _target)
{
	var value = false;

	// This probably won't work...
	var field = document.getElementById(_action.data.fieldId);

	// Use widget
	if (!field)
	{
		var nextmatch = _action.data.nextmatch;
		if(!nextmatch && _senders.length)
		{
			// Pull it from deep within, where it was stuffed in et2_dataview_controller_selection._attachActionObject()
			nextmatch = _senders[0]._context._widget;
		}
		if(!nextmatch) return false;

		field = nextmatch.getWidgetById(_action.data.fieldId);
		value = field.getValue();
	}
	else
	{
		value = $j(field).val();
	}
	if (!field) return false;


	if (_action.data.fieldValue.substr(0,1) == '!')
		return value != _action.data.fieldValue.substr(1);

	return value == _action.data.fieldValue;
}

// TODO: This code is rather suboptimal! No global variables as this code will
// run in a global context
var nm_popup_action, nm_popup_ids = null;

/**
 * Open popup for a certain action requiring further input
 *
 * Popup needs to have eTemplate name of action id plus "_popup"
 *
 * @param _action
 * @param _ids
 */
function nm_open_popup(_action, _ids)
{
	//Check if there is nextmatch on _action otherwise gets the uniqueid from _ids
	var uid;
	if (typeof _action.data.nextmatch !== 'undefined')
		uid = _action.data.nextmatch.getInstanceManager().uniqueId;
	else if(typeof _ids[0] !== 'undefined')
		uid = _ids[0].manager.data.nextmatch.getInstanceManager().uniqueId;
	// Find the popup div
	var popup = jQuery("#"+(uid||"") + "_"+_action.id+"_popup").first() || jQuery("[id*='" + _action.id + "_popup']").first();
	if (popup) {
		nm_popup_action = _action;
		if(_ids.length && typeof _ids[0] == 'object')
		{
			egw().debug("warn", 'Not proper format for IDs, should be array',_ids);
			_action.data.nextmatch = _ids[0]._context._widget;
			nm_popup_ids = [];
			for(var i = 0; i < _ids.length; i++)
			{
				nm_popup_ids.push(_ids[i].id);
			}
		}
		else
		{
			nm_popup_ids = _ids;
		}

		var dialog = jQuery('.action_popup-content',popup);
		if(dialog.length == 0)
		{
			// Couldn't get the dialog, use the div less the first (header) & last (buttons) nodes
			dialog = jQuery(document.createElement('div'))
				.addClass('action_popup-content');
			if(popup.children().length == 1)
			{
				dialog.append(popup.children().children().slice(1,popup.children().children().length-1));
			}
			else
			{
				dialog.append(popup.children().slice(1,popup.children().length-1));
			}
			dialog.appendTo(popup);
		}
		if(dialog.length == 1)
		{
			// Set up the buttons
			var dialog_parent = dialog.parent();
			var d_buttons = [];
			var action = _action;
			jQuery('button',popup).each(function(index) {
				var but = jQuery(this);
				but.hide();
				if(but.attr("id"))
				{
					// Find the associated widget
					var widget_id = but.attr("id").replace(_action.data.nextmatch.getInstanceManager().uniqueId+'_', '');
					var button = nm_popup_action.data.nextmatch.getRoot().getWidgetById(widget_id);
				}
				d_buttons.push({
					text: but.text(),
					click: button && button.onclick ? function(e) {
						dialog.dialog("close");
						nm_popup_action = action;
						button.onclick.apply(button, e.currentTarget);
					} : function(e) {
						dialog.dialog("close");
					}
				});
			});
			dialog.dialog({
				title: jQuery('.promptheader',popup).text(),
				modal: true,
				buttons: d_buttons,
				minWidth: dialog.outerWidth(true),
				close: function(event, ui) {
					// Need to destroy the dialog, etemplate widget needs divs back where they were
					dialog.dialog("destroy");

					// Put it back where it came from, or et2 will error when clear() is called
					dialog.appendTo(dialog_parent);
				}
			});
		}

		// Reset global variables
		nm_popup_action = null;
		nm_popup_senders = null;
	}
}

/**
 * Submit a popup action
 */
function nm_submit_popup(button)
{
	if(button.form)
	{
		button.form.submit_button.value = button.name;	// set name of button (sub-action)
	}
	else if (nm_popup_action.data.nextmatch)
	{
		// Find the associated widget
		var widget_id = $j(button).attr("id").replace(nm_popup_action.data.nextmatch.getInstanceManager().uniqueId+'_', '');
		nm_popup_action.data.nextmatch.getRoot().getWidgetById(widget_id).clicked = true;
	}

	// Mangle senders to get IDs where nm_action() wants them
	// No idea why this is needed
	var ids = {ids:[]};
	for(var i in nm_popup_ids)
	{
		ids.ids.push(nm_popup_ids[i]);
	}
	// call regular nm_action to transmit action and senders correct
	nm_action(nm_popup_action,nm_popup_ids, button, ids);

	nm_hide_popup(button, null);
}

/**
 * Hide popup
 */
function nm_hide_popup(element, div_id)
{
	var prefix = element.id.substring(0,element.id.indexOf('['));
	var popup = div_id ? document.getElementById(div_id) : jQuery("#"+prefix+"_popup").get(0) || jQuery("[id*='" + prefix + "_popup']").get(0);

	// Hide popup
	if(popup) {
		popup.style.display = 'none';
	}
	nm_popup_action = null;
	nm_popup_senders = null;

	return false;
}

/**
 * Activate/click first link in row
 */
function nm_activate_link(_action, _senders)
{
	$j(_senders[0].iface.getDOMNode()).find('.et2_clickable:first').trigger('click');
}
