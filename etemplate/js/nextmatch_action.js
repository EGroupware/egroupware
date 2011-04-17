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
		$(_node).click(function(e) {

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
		});

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
	if (typeof _action.data == 'undefined' || !_action.data) _action.data = {};
	if (typeof _action.data.nm_action == 'undefined') _action.data.nm_action = 'submit';
	
	var ids = "";
	for (var i = 0; i < _senders.length; i++)
	{
		ids += (_senders[i].id.indexOf(',') >= 0 ? '"'+_senders[i].id.replace(/"/g,'""')+'"' : _senders[i].id) + 
			((i < _senders.length - 1) ? "," : "");
	}
	console.log(_action);
	console.log(_senders);

	// let user confirm the action first
	if (typeof _action.data.confirm != 'undefined')
	{
		if (!confirm(_action.data.confirm)) return;
	}
	
	var url = '#';
	if (typeof _action.data.url != 'undefined')
	{
		url = _action.data.url.replace(/(\$|%24)id/,ids);
	}
	var target;
	if (typeof _action.data.target != 'undefined') target = _action.data.target;
	
	switch(_action.data.nm_action)
	{
		case 'alert':
			alert(_action.caption + " (\'" + _action.id + "\') executed on rows: " + ids);
			break;
			
		case 'location':
			window.location.href = url;
			break;
			
		case 'popup':
			egw_openWindowCentered2(url,target,_action.data.width,_action.data.height);
			break;
			
		case 'submit':
			document.getElementById('exec[nm][action]').value = _action.id;
			document.getElementById('exec[nm][selected]').value = ids;
			if (typeof _action.data.button != 'undefined')
			{
				submitit(eTemplate,'exec[nm][rows]['+_action.data.button+']['+ids+']');
			}
			else
			{
				eTemplate.submit();
			}
			break;
	}
}
