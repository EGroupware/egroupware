/**
 * eGroupWare eTemplate2 - JS Nextmatch object
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
	if (typeof _action.data.nm_action == 'undefined') _action.data.nm_action = 'submit';

	// ----------------------
	// TODO: Parse the _ids.inverted flag!
	// ----------------------

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

	var select_all = mgr.getActionById("select_all");
	var confirm_msg = (idsArr.length > 1 || select_all && select_all.checked) && 
		typeof _action.data.confirm_multiple != 'undefined' ?
			_action.data.confirm_multiple : _action.data.confirm;

	// let user confirm the action first (if not select_all set and nm_action == 'submit'  --> confirmed later)
	if (!(select_all && select_all.checked && _action.data.nm_action == 'submit') &&
		typeof _action.data.confirm != 'undefined')
	{
		if (!confirm(confirm_msg)) return;
	}
	// in case we only need to confirm multiple selected (only _action.data.confirm_multiple)
	else if (typeof _action.data.confirm_multiple != 'undefined' &&  (idsArr.length > 1 || select_all && select_all.checked))
	{
		if (!confirm(_action.data.confirm_multiple)) return;		
	}
	
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
				window.open(url, target);
			}
			else
			{
				window.location.href = url;
			}
			break;
			
		case 'popup':
			egw_openWindowCentered2(url,target,_action.data.width,_action.data.height);
			break;
			
		case 'egw_open':
			var params = _action.data.egw_open.split('-');	// type-appname-idNum (idNum is part of id split by :), eg. "edit-infolog"
			console.log(params);
			var egw_open_id = idsArr[0].id;
			if (typeof params[2] != 'undefined') egw_open_id = egw_open_id.split(':')[params[2]];
			egw_open(egw_open_id,params[1],params[0],params[3]);
			break;
			
		case 'open_popup':
			// open div styled as popup contained in current form and named action.id+'_popup'
			if (nm_popup_action == null)
			{
				nm_open_popup(_action, _ids);
				break;
			}
			// fall through, if popup is open --> submit form
		case 'submit':
			// let user confirm select-all
			if (select_all && select_all.checked)
			{
				if (!confirm((confirm_msg ? confirm_msg : _action.caption.replace(/^(&nbsp;| |	)+/,''))+"\n\n"+select_all.hint)) return;
			}
			var checkboxes = mgr.getActionsByAttr("checkbox", true);
			var checkboxes_elem = document.getElementById(mgr.etemplate_var_prefix+'[nm][checkboxes]');
			if (checkboxes && checkboxes_elem)
				for (var i in checkboxes)
					checkboxes_elem.value += checkboxes[i].id + ":" + (checkboxes[i].checked ? "1" : "0") + ";";

			var nextmatch = _action.data.nextmatch;
			if(!nextmatch && _senders.length)
			{
				// Pull it from deep within, where it was stuffed in et2_dataview_controller_selection._attachActionObject()
				nextmatch = _senders[0]._context._widget;
			}
			if(nextmatch)
			{
				// Fake a getValue() function
				nextmatch.getValue = function() {
					var value = {
						"selected": idsArr,
						"checkboxes": checkboxes_elem ? checkboxes_elem.value : null
					};
					value[nextmatch.options.settings.action_var]= _action.id;
					return value;
				}
				nextmatch.getInstanceManager().submit();

				// Clear action in case there's another one
				delete nextmatch.getValue;
			}
			else
			{
				egw().debug("error", "Missing nextmatch widget, could not submit", _action);
			}
			break;
	}
}

/**
 * Callback to check if none of _senders rows has disableClass set
 * 
 * @param _action egwAction object, we use _action.data.disableClass to check
 * @param _senders array of egwActionObject objects
 * @param _target egwActionObject object, get's called for every object in _senders
 * @returns boolean true if none has disableClass, false otherwise
 */
function nm_not_disableClass(_action, _senders, _target)
{
	return !$j(_target.iface.getDOMNode()).hasClass(_action.data.disableClass);
}

/**
 * Callback to check if all of _senders rows have enableClass set
 * 
 * @param _action egwAction object, we use _action.data.enableClass to check
 * @param _senders array of egwActionObject objects
 * @param _target egwActionObject object, get's called for every object in _senders
 * @returns boolean true if none has disableClass, false otherwise
 */
function nm_enableClass(_action, _senders, _target)
{
	return $j(_target.iface.getDOMNode()).hasClass(_action.data.enableClass);
}

/**
 * Enable an _action, if it matches a given regular expresstion in _action.data.enableId
 * 
 * @param _action egwAction object, we use _action.data.enableId to check
 * @param _senders array of egwActionObject objects
 * @param _target egwActionObject object, get's called for every object in _senders
 * @returns boolean true if _target.id matches _action.data.enableId
 */
function nm_enableId(_action, _senders, _target)
{
	if (typeof _action.data.enableId == 'string')
		_action.data.enableId = new RegExp(_action.data.enableId);
	
	return _target.id.match(_action.data.enableId);
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
	var field = document.getElementById(_action.data.fieldId);

	if (!field) return false;

	var value = $j(field).val();
	
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
	var popup = jQuery("#"+_action.id+"_popup").get(0) || jQuery("[id*='" + _action.id + "_popup']").get(0);
	if (popup) {
		nm_popup_action = _action;
		nm_popup_ids = _ids;
		popup.style.display = 'block';
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

	// Mangle senders to get IDs where nm_action() wants them
	// No idea why this is needed
	var ids = {ids:[]};
	for(var i in nm_popup_ids)
	{
		ids.ids.push(nm_popup_ids[i].id);
	}
	// call regular nm_action to transmit action and senders correct
	nm_action(nm_popup_action,nm_popup_ids, null, ids);
}

/**
 * Hide popup
 */
function nm_hide_popup(element, div_id) 
{
	var prefix = element.id.substring(0,element.id.indexOf('['));
	var popup = jQuery("#"+_action.id+"_popup").get(0) || jQuery("[id*='" + _action.id + "_popup']").get(0);

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
