/**
 * EGroupware eTemplate nextmatch row action object interface
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/**
 * Contains the action object interface implementation for the nextmatch widget
 * row.
 */

/**
 * An action object interface for each nextmatch widget row - "inherits" from 
 * egwActionObjectInterface
 */
function nextmatchRowAOI(_node)
{
	var aoi = new egwActionObjectInterface();

	aoi.node = _node;

	aoi.checkBox = ($(":checkbox", aoi.node))[0];

	// Rows without a checkbox OR an id set are unselectable
	if (typeof aoi.checkBox != "undefined" || _node.id)
	{
		aoi.doGetDOMNode = function() {
			return aoi.node;
		}

		// Prevent the browser from selecting the content of the element, when
		// a special key is pressed.
		$(_node).mousedown(egwPreventSelect);

		// Now append some action code to the node
		selectHandler = function(e) {

			// Reset the prevent selection code (in order to allow wanted
			// selection of text)
			_node.onselectstart = null;

			if (e.target != aoi.checkBox)
			{
				var selected = egwBitIsSet(aoi.getState(), EGW_AO_STATE_SELECTED);
				var state = egwGetShiftState(e);

				aoi.updateState(EGW_AO_STATE_SELECTED,
					!egwBitIsSet(state, EGW_AO_SHIFT_STATE_MULTI) || !selected,
					state);
			}
		};

		if (egwIsMobile()) {
			_node.ontouchend = selectHandler;
		} else {
			$(_node).click(selectHandler);
		}

		$(aoi.checkBox).change(function() {
			aoi.updateState(EGW_AO_STATE_SELECTED, this.checked, EGW_AO_SHIFT_STATE_MULTI);
		});

		// Don't execute the default action when double clicking on an entry
		$(aoi.checkBox).dblclick(function() {
			return false;
		});

		aoi.doSetState = function(_state) {
			var selected = egwBitIsSet(_state, EGW_AO_STATE_SELECTED);

			if (this.checkBox)
			{
				this.checkBox.checked = selected;
			}

			$(this.node).toggleClass('focused',
				egwBitIsSet(_state, EGW_AO_STATE_FOCUSED));
			$(this.node).toggleClass('selected',
				selected);
		}
	}

	return aoi;
}

/**
 * Default action for nextmatch rows, runs action specified _action.data.nm_action: see nextmatch_widget::egw_actions()
 * 
 * @param _action action object with attributes caption, id, nm_action, ...
 * @param _senders array of rows selected
 */
function nm_action(_action, _senders)
{
	// ignore checkboxes, unless they have an explicit defined nm_action
	if (_action.checkbox && (!_action.data || typeof _action.data.nm_action == 'undefined')) return;

	if (typeof _action.data == 'undefined' || !_action.data) _action.data = {};
	if (typeof _action.data.nm_action == 'undefined') _action.data.nm_action = 'submit';
	
	var ids = "";
	for (var i = 0; i < _senders.length; i++)
	{
		ids += (_senders[i].id.indexOf(',') >= 0 ? '"'+_senders[i].id.replace(/"/g,'""')+'"' : _senders[i].id) + 
			((i < _senders.length - 1) ? "," : "");
	}
	//console.log(_action); console.log(_senders);

	var mgr = _action.getManager();

	var select_all = mgr.getActionById("select_all");
	var confirm_msg = (_senders.length > 1 || select_all && select_all.checked) && 
		typeof _action.data.confirm_multiple != 'undefined' ?
			_action.data.confirm_multiple : _action.data.confirm;

	// let user confirm the action first (if not select_all set and nm_action == 'submit'  --> confirmed later)
	if (!(select_all && select_all.checked && _action.data.nm_action == 'submit') &&
		typeof _action.data.confirm != 'undefined')
	{
		if (!confirm(confirm_msg)) return;
	}
	
	var url = '#';
	if (typeof _action.data.url != 'undefined')
	{
		url = _action.data.url.replace(/(\$|%24)id/,ids);
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
			if(target)
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
			var egw_open_id = _senders[0].id;
			if (typeof params[2] != 'undefined') egw_open_id = egw_open_id.split(':')[params[2]];
			egw_open(egw_open_id,params[1],params[0],params[3]);
			break;
			
		case 'open_popup':
			// open div styled as popup contained in current form and named action.id+'_popup'
			if (typeof nm_popup_action == 'undefined')
			{
				nm_open_popup(_action, _senders);
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

			document.getElementById(mgr.etemplate_var_prefix+'[nm][nm_action]').value = _action.id;
			document.getElementById(mgr.etemplate_var_prefix+'[nm][selected]').value = ids;
			if (typeof _action.data.button != 'undefined')
			{
				submitit(mgr.etemplate_form.context, mgr.etemplate_var_prefix+'[nm][rows]['+_action.data.button+']['+ids+']');
			}
			else
			{
				mgr.etemplate_form.submit();
			}
			// Clear action in case there's another one
			document.getElementById(mgr.etemplate_var_prefix+'[nm][nm_action]').value = null;
			
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
	return !$(_target.iface.getDOMNode()).hasClass(_action.data.disableClass);
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
	return $(_target.iface.getDOMNode()).hasClass(_action.data.enableClass);
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

	var value = $(field).val();
	
	if (_action.data.fieldValue.substr(0,1) == '!')
		return value != _action.data.fieldValue.substr(1);
	
	return value == _action.data.fieldValue;
}

var nm_popup_action, nm_popup_senders;

/**
 * Open popup for a certain action requiring further input
 * 
 * Popup needs to have eTemplate name of action id plus "_popup"
 * 
 * @param _action
 * @param _senders
 */
function nm_open_popup(_action, _senders)
{
	var popup = document.getElementById(_action.getManager().etemplate_var_prefix + '[' + _action.id + '_popup]');

	if (popup) {
		nm_popup_action = _action;
		nm_popup_senders = _senders;
		popup.style.display = 'block';
	}
}

/**
 * Submit a popup action
 */
function nm_submit_popup(button)
{
	button.form.submit_button.value = button.name;	// set name of button (sub-action)

	// call regular nm_action to transmit action and senders correct
	nm_action(nm_popup_action, nm_popup_senders);
}

/**
 * Hide popup
 */
function nm_hide_popup(element, div_id) 
{
	var prefix = element.id.substring(0,element.id.indexOf('['));
	var popup = document.getElementById(prefix+'['+div_id+']');

	// Hide popup
	if(popup) {
		popup.style.display = 'none';
	}
	delete nm_popup_action;
	delete nm_popup_senders;

	return false;
}
