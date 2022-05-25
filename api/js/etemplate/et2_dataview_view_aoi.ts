/**
 * EGroupware eTemplate2 - Contains interfaces used inside the dataview
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */

/*egw:uses
	egw_action.egw_action_common;
	egw_action.egw_action;
	/vendor/bower-asset/jquery-touchswipe/jquery.touchSwipe.js;
*/

import {
	egwActionObjectInterface
} from "../egw_action/egw_action.js";
import {EGW_AO_SHIFT_STATE_MULTI,
	EGW_AO_STATE_FOCUSED,
	EGW_AO_STATE_SELECTED} from '../egw_action/egw_action_constants.js';
import {egwBitIsSet, egwGetShiftState, egwPreventSelect, egwSetBit, egwUnfocus, egwIsMobile} from "../egw_action/egw_action_common.js";
import {_egw_active_menu} from "../egw_action/egw_menu.js";
import {tapAndSwipe} from "../tapandswipe";

/**
 * Contains the action object interface implementation for the nextmatch widget
 * row.
 */

export const EGW_SELECTMODE_DEFAULT = 0;
export const EGW_SELECTMODE_TOGGLE = 1;

/**
 * An action object interface for each nextmatch widget row - "inherits" from
 * egwActionObjectInterface
 *
 * @param {DOMNode} _node
 */
export function et2_dataview_rowAOI(_node)
{
	"use strict";

	var aoi = new egwActionObjectInterface();

	aoi.node = _node;

	aoi.selectMode = EGW_SELECTMODE_DEFAULT;

	aoi.checkBox = null; //(jQuery(":checkbox", aoi.node))[0];

	// Rows without a checkbox OR an id set are unselectable
	aoi.doGetDOMNode = function() {
		return aoi.node;
	};

	// Prevent the browser from selecting the content of the element, when
	// a special key is pressed.
	jQuery(_node).mousedown(egwPreventSelect);

	/**
	 * Now append some action code to the node
	 *
	 * @memberOf et2_dataview_rowAOI
	 * @param {DOMEvent} e
	 * @param {object} _params
	 */
	var selectHandler = function(e, _params) {
		// Reset the focus so that keyboard navigation will work properly
		// after the element has been clicked
		egwUnfocus();

		// Reset the prevent selection code (in order to allow wanted
		// selection of text)
		_node.onselectstart = null;

		if (e.target != aoi.checkBox)
		{
			var selected = egwBitIsSet(aoi.getState(), EGW_AO_STATE_SELECTED);
			var state = egwGetShiftState(e);

			if (_params)
			{
				if (egwIsMobile())
				{
					switch (_params.swip)
					{
						case "left":
						case "right":
							state = 1;
							// Hide context menu on swip actions
							if(_egw_active_menu) _egw_active_menu.hide();
							break;
						case "up":
						case "down":
							return;
					}
				}
			}
			switch (aoi.selectMode)
			{
			case EGW_SELECTMODE_DEFAULT:
				aoi.updateState(EGW_AO_STATE_SELECTED,
					!egwBitIsSet(state, EGW_AO_SHIFT_STATE_MULTI) || !selected,
					state);
				break;
			case EGW_SELECTMODE_TOGGLE:
				aoi.updateState(EGW_AO_STATE_SELECTED, !selected,
					egwSetBit(state, EGW_AO_SHIFT_STATE_MULTI, true));
				break;
			}
		}
	};

	if (egwIsMobile()) {
		let swipe = new tapAndSwipe(_node,{
			// set the same threshold as action_popup event to get the tapAndHold working
			tapHoldThreshold: 600,
			swipe: function (event, direction, distance)
			{
				if (distance > 100) selectHandler(event, {swip:direction});
			},
			tap: function (event)
			{
				selectHandler(event);
			},
			tapAndHold: function(event)
			{
				return;
			}
		});
	} else {
		jQuery(_node).click(selectHandler);
	}

	jQuery(aoi.checkBox).change(function() {
		aoi.updateState(EGW_AO_STATE_SELECTED, this.checked, EGW_AO_SHIFT_STATE_MULTI);
	});

	aoi.doSetState = function(_state) {
		var selected = egwBitIsSet(_state, EGW_AO_STATE_SELECTED);

		if (this.checkBox)
		{
			this.checkBox.checked = selected;
		}

		jQuery(this.node).toggleClass('focused',
			egwBitIsSet(_state, EGW_AO_STATE_FOCUSED));
		jQuery(this.node).toggleClass('selected',
			selected);
	};

	return aoi;
}

