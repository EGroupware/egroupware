/**
 * eGroupWare eTemplate nextmatch row action object interface
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @version $Id$
 */

/**
 * Contains the action object interface implementation for the nextmatch widget
 * row.
 */

// An action object interface for each nextmatch widget row - "inherits" from 
// egwActionObjectInterface
function nextmatchRowAOI(_node)
{
	var aoi = new egwActionObjectInterface();

	aoi.node = _node;

	aoi.checkBox = ($(":checkbox", aoi.node))[0];

	// Rows without a checkbox are unselectable
	if (typeof aoi.checkBox != "undefined")
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

