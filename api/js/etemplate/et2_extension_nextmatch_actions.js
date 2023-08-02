/**
 * EGroupware eTemplate2 - JS Nextmatch object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright EGroupware GmbH 2012-2021
 */

/**
 * Default action for nextmatch rows, runs action specified _action.data.nm_action: see nextmatch_widget::egw_actions()
 *
 * @param {egwAction} _action action object with attributes caption, id, nm_action, ...
 * @param {array} _senders array of rows selected
 * @param {object} _target
 * @param {object} _ids attributs all and ids (array of string)
 */
export function nm_action(_action, _senders, _target, _ids)
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
		else
		{
			// This will probably fail without nm, but it depends on the action
			_ids = {ids: _senders.map(function(s) {return s.id;})};
		}
	}
	// row ids
	var row_ids = "";
	for (var i = 0; i < _ids.ids.length; i++)
	{
		var row_id = _ids.ids[i];
		row_ids += (row_id.indexOf(',') >= 0 ? '"'+row_id.replace(/"/g,'""')+'"' : row_id) +
			((i < _ids.ids.length - 1) ? "," : "");
	}

	// Translate the internal uids back to server uids
	var idsArr = _ids.ids;
	for (var i = 0; i < idsArr.length; i++)
	{
		// empty placeholder gets reported --> ignore it
		if (!idsArr[i])
		{
			idsArr.splice(i,1);
			continue;
		}
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
		// Add selected IDs to url
		url = _action.data.url.replace(/(\$|%24)id/,encodeURIComponent(ids))
			// Include select all flag too
			.replace(/(\$|%24)select_all/,_ids.all)
			// Add row_ids to url
			.replace(/(\$|%24)row_id/,encodeURIComponent(row_ids));
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
				egw.top.egw_appWindowOpen(_action.data.targetapp, url);
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
			// According to microsoft, IE 10/11 can only accept a url with 2083 characters
			// therefore we need to send request to compose window with POST method
			// instead of GET. We create a temporary <Form> and will post emails.
			// ** WebServers and other browsers also have url length limit:
			// Firefox:~ 65k, Safari:80k, Chrome: 2MB, Apache: 4k, Nginx: 4k
			if (url.length > 2083)
			{
				var $tmpForm = jQuery(document.createElement('form'));
				var $tmpSubmitInput = jQuery(document.createElement('input')).attr({type:"submit"});
				var params = url.split('&');
				url = params[0];
				for (var i=1;i<params.length;i++)
				{
					var values = params[i].split('=');
					switch (values[0])
					{
						case 'cd':
						case 'tz':
						case 'menuaction':
						case 'hasupdate':
							url = url + '&' + values.join('=');
							break;
					}
					$tmpForm.append(jQuery(document.createElement('input')).attr({name:values[0], type:"text", value: values[1]}));
				}
				var postRequest = true;
			}

			var popup_window = egw.open_link(url,target,_action.data.width+'x'+_action.data.height);

			if (postRequest)
			{
				popup_window.name = popup_window.name ? popup_window.name : 'postRequest';
				// Set the temporary form's attributes
				$tmpForm.attr({target:popup_window.name, action:url, method:"post"})
						.append($tmpSubmitInput).appendTo('body');
				$tmpForm.submit();
				// Remove the form after submit
				$tmpForm.remove();
			}
			break;

		case 'long_task':
			// Run a long task once for each ID with a nice dialog instead of
			// freezing for a while.  If egw_open is set, and only 1 row selected,
			// egw_open will be used instead.
			if(doLongTask(idsArr, _ids.all,_action, mgr.data.nextmatch)) break;

			// Fall through
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
						// Discard , special handling when from=merge is involved
						if(params.length>1 && params[0]=='' && params[1].indexOf('from=merge')!=-1)
						{
							params.shift();
						}
						else
						{
							params.shift(params[2]);
						}
					}
			}
			//special handling when from=merge is involved
			if(params.length>1 && params[0]=='' && params[1].indexOf('from=merge')!=-1)
			{
				params.shift();
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
				jQuery.extend(value, _action.data, nextmatch.activeFilters, {
					"selected": idsArr,
					"select_all": _ids.all,
					"checkboxes": checkbox_values
				});
				// Skip this one, it would cause the nm to change ID on reload
				delete value.id;
				value[nextmatch.options.settings.action_var]= _action.id;

				// Don't try to send the nextmatch
				delete value['nextmatch'];

				nextmatch.getValue = function() {
					return value;
				};


				// downloads need a regular submit via POST (no Ajax)
				if (_action.data.postSubmit)
				{
					nextmatch.getInstanceManager().postSubmit();
				}
				else
				{
					nextmatch.getInstanceManager().submit();
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
 * Fetch all IDs to the client side, user wants to do something with them...
 *
 * @param {string[]} ids Array of selected IDs
 * @param {et2_nextmatch} nextmatch
 * @param {function} callback Callback function
 * @returns {Boolean}
 */
export function fetchAll(ids, nextmatch, callback)
{
	if(!nextmatch || !nextmatch.controller) return false;
	var selection = nextmatch.getSelection();
	if(!selection.all) return false;

	if(nextmatch.controller._grid && nextmatch.controller._grid.getTotalCount() > ids.length)
	{
		// Need to actually fetch all (TODO: just ids) to do this client side
		var idsArr = [];
		var count = idsArr.length;
		var total = nextmatch.controller._grid.getTotalCount();
		var cancel = false;
		var dialog = et2_dialog.show_dialog(
			// Abort the long task if they canceled the data load
			function() {count = total; cancel=true;},
			egw.lang('Loading'), egw.lang('please wait...'),{},[
				{"button_id": et2_dialog.CANCEL_BUTTON,"text": egw.lang('cancel'),id: 'dialog[cancel]',image: 'cancel'}
			]
		);

		// dataFetch() is asyncronous, so all these requests just get fired off...
		// 200 rows chosen arbitrarily to reduce requests.
		do {
			nextmatch.controller.dataFetch({start:count, num_rows: 200}, function(data) {
				if(data && data.order)
				{
					for(var i = 0; i < data.order.length; i++)
					{
						idsArr.push(data.order[i].split("::").pop());
					}
				}
				// Update total, just in case it changed
				if(data && data.total) total = data.total;

				if(idsArr.length >= total)
				{
					dialog.destroy();
					if(!cancel)
					{
						callback.call(this, idsArr);
					}
				}
			},this);
			count += 200;
		} while (count < total)
		return true;
	}
	return false;
}

/**
 * Fetch all IDs and run a long task.
 *
 * @param {String[]} idsArr Array of IDs
 * @param {boolean} all True if all IDs are selected.  They'll have to be fetched if missing.
 * @param {type} _action
 * @param {et2_nextmatch} nextmatch
 * @returns {Boolean}
 */
export function doLongTask(idsArr, all, _action, nextmatch)
{
	if(all || idsArr.length > 1 || typeof _action.data.egw_open == 'undefined')
	{
		if(all)
		{
			var fetching = fetchAll(idsArr, nextmatch,function(idsArr){
				et2_dialog.long_task(null,_action.data.message||_action.caption,_action.data.title,_action.data.menuaction,idsArr);
			});
			if(fetching) return true;
		}
		et2_dialog.long_task(null,_action.data.message||_action.caption,_action.data.title,_action.data.menuaction,idsArr);
		return true;
	}
	return false;
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
export function nm_compare_field(_action, _senders, _target)
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
		value = jQuery(field).val();
	}
	if (!field) return false;


	if (_action.data.fieldValue.substr(0,1) == '!')
		return value != _action.data.fieldValue.substr(1);

	return value == _action.data.fieldValue;
}

// TODO: This code is rather suboptimal! No global variables as this code will
// run in a global context
window.nm_action = nm_action;
window.fetchAll = fetchAll;
window.doLongTask = doLongTask;
window.nm_popup_action = null;
window.nm_popup_ids = null;

/**
 * Open popup for a certain action requiring further input
 *
 * Popup needs to have eTemplate name of action id plus "_popup"
 *
 * @param {egwAction} _action
 * @param {egwActionObject[]} _selected
 */
export function nm_open_popup(_action, _selected)
{
	//Check if there is nextmatch on _action otherwise gets the uniqueid from _ids
	var uid;
	if (typeof _action.data.nextmatch !== 'undefined')
	{
		uid = _action.data.nextmatch.getInstanceManager().uniqueId;
	}
	else if (typeof _selected[0] !== 'undefined')
	{
		uid = _selected[0].manager.data.nextmatch.getInstanceManager().uniqueId;
	}
	let action = _action;
	let selected = _selected;
	nm_popup_action = _action;

	if (_selected.length && typeof _selected[0] == 'object')
	{
		_action.data.nextmatch = _selected[0]._context._widget;
		nm_popup_ids = _selected;
	}
	else
	{
		egw().debug("warn", 'Not proper format for IDs, should be array of egwActionObject', _selected);
		nm_popup_ids = _selected;
	}

	// Find the popup div
	let nm = _action.data.nextmatch;
	var popup = (nm?.getInstanceManager().DOMContainer || document.body).querySelector("et2-dialog[id*='" + _action.id + "_popup']") || document.body.querySelector("#" + (uid || "") + "_" + _action.id + "_popup") || document.body.querySelector("[id*='" + _action.id + "_popup']");
	if (popup && popup instanceof Et2Dialog)
	{
		popup.show();
	}
	else if (popup)
	{
		let dialog = new Et2Dialog(nm?.egw());
		dialog.destroyOnClose = false;
		dialog.id = popup.id;
		popup.removeAttribute("id");
		// Remove class that hides
		popup.classList.remove("prompt");
		popup.classList.remove("action_popup");

		// Set title
		let title = popup.querySelector(".promptheader")
		if (title)
		{
			title.slot = "label"
			dialog.appendChild(title);
		}
		popup.slot = "";

		dialog.addEventListener("close", () =>
		{
			window.nm_popup_action = null;
		});
		// Move buttons
		popup.querySelectorAll('et2-button').forEach((button, index) =>
		{
			button.slot = "footer";
			if (index == 0)
			{
				button.variant = "primary";
			}
			let button_click = button.onclick;
			button.onclick = (e) =>
			{
				window.nm_popup_action = button_click ? action : null;
				window.nm_popup_ids = selected;
				dialog.hide();

				return button_click?.apply(button, e.currentTarget);
			};
			dialog.appendChild(button);
		})
		dialog.appendChild(popup);
		dialog.requestUpdate();

		if (nm)
		{
			nm.getInstanceManager().DOMContainer.appendChild(dialog);
		}
		else
		{
			document.body.appendChild(dialog);
		}

		// Reset global variables
		nm_popup_action = null;
		nm_popup_ids = null;
	}
}

/**
 * Submit a popup action
 *
 * Must to be global, as it's used as onclick action!
 *
 * @param {DOMNode} button DOM node of button
 */
window.nm_submit_popup = function(button)
{
	if (nm_popup_action.data.nextmatch)
	{
		button.clicked = true;
	}

	// Mangle senders to get IDs where nm_action() wants them
	if(nm_popup_ids.length && typeof nm_popup_ids[0] != 'object')
	{
		// Legacy ID just as string
		var ids = {ids:[]};
		for(var i in nm_popup_ids)
		{
			if(nm_popup_ids[i])
			ids.ids.push(nm_popup_ids[i]);
		}
	}

	// call regular nm_action to transmit action and senders correct
	nm_action(nm_popup_action,nm_popup_ids, button, ids );

	nm_hide_popup(button, null);
	nm_popup_ids = null;
}

/**
 * Hide popup
 *
 * Must to be global, as it's used as onclick action!
 *
 * @param {DOMNode} element
 * @param {string} div_id
 * @returns {Boolean}
 */
window.nm_hide_popup = function(element, div_id)
{
	var prefix = element.id.substring(0, element.id.indexOf('['));
	var popup = div_id ? document.getElementById(div_id) : document.querySelector("#" + prefix + "_popup") || document.querySelector("[id*='" + prefix + "_popup']");

	// Hide popup
	if (popup)
	{
		popup.close();
	}
	nm_popup_action = null;
	nm_popup_ids = null;

	return false;
}

/**
 * Activate/click first link in row
 *
 * @param {egwAction} _action
 * @param {array} _senders of egwActionObject
 */
window.nm_activate_link = function(_action, _senders)
{
	jQuery(_senders[0].iface.getDOMNode()).find('.et2_clickable:first').trigger('click');
}
