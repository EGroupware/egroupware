/**
 * EGroupware eTemplate2 - JS widget class with value attribute and auto loading
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_baseWidget;
*/

import { et2_baseWidget } from './et2_core_baseWidget'
import {WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_csvSplit, et2_no_init} from "./et2_core_common";

/**
 * et2_valueWidget is the base class for et2_inputWidget - valueWidget introduces
 * the "value" attribute and automatically loads it from the "content" array
 * after loading from XML.
 */
export class et2_valueWidget extends et2_baseWidget
{
	static readonly _attributes : any = {
		"label": {
			"name": "Label",
			"default": "",
			"type": "string",
			"description": "The label is displayed by default in front (for radiobuttons behind) each widget (if not empty). If you want to specify a different position, use a '%s' in the label, which gets replaced by the widget itself. Eg. '%s Name' to have the label Name behind a checkbox. The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
			"translate": true
		},
		"value": {
			"name": "Value",
			"description": "The value of the widget",
			"type": "rawstring",	// no html-entity decoding
			"default": et2_no_init
		}
	};

	label: string = '';
	protected value: string|number|Object;
	protected _labelContainer: JQuery = null;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_valueWidget._attributes, _child || {}));
	}

	/**
	 *
	 * @param _attrs
	 */
	transformAttributes(_attrs : object)
	{
		super.transformAttributes(_attrs);

		if (this.id)
		{
			// Set the value for this element
			var contentMgr = this.getArrayMgr("content");
			if (contentMgr != null) {
				let val = contentMgr.getEntry(this.id, false, true);
				if (val !== null)
				{
					_attrs["value"] = val;
				}
			}
			// Check for already inside namespace
			if(this._createNamespace() && this.getArrayMgr("content").perspectiveData.owner == this)
			{
				_attrs["value"] = this.getArrayMgr("content").data;
			}

		}
	}

	set_label(_value : string)
	{
		// Abort if there was no change in the label
		if (_value == this.label)
		{
			return;
		}

		if (_value)
		{
			// Create the label container if it didn't exist yet
			if (this._labelContainer == null)
			{
				this._labelContainer = jQuery(document.createElement("label"))
					.addClass("et2_label");
				this.getSurroundings().insertDOMNode(this._labelContainer[0]);
			}

			// Clear the label container.
			this._labelContainer.empty();

			// Create the placeholder element and set it
			var ph = document.createElement("span");
			this.getSurroundings().setWidgetPlaceholder(ph);

			// Split the label at the "%s"
			var parts = et2_csvSplit(_value, 2, "%s");

			// Update the content of the label container
			for (var i = 0; i < parts.length; i++)
			{
				if (parts[i])
				{
					this._labelContainer.append(document.createTextNode(parts[i]));
				}
				if (i == 0)
				{
					this._labelContainer.append(ph);
				}
			}

			// add class if label is empty
			this._labelContainer.toggleClass('et2_label_empty', !_value || !parts[0]);
		}
		else
		{
			// Delete the labelContainer from the surroundings object
			if (this._labelContainer)
			{
				this.getSurroundings().removeDOMNode(this._labelContainer[0]);
			}
			this._labelContainer = null;
		}

		// Update the surroundings in order to reflect the change in the label
		this.getSurroundings().update();

		// Copy the given value
		this.label = _value;
	}

	get_value()
	{
		return this.value;
	}

	/**
	 * Set value of widget
	 *
	 * @param {string} _value value to set
	 */
	set_value(_value : any | null)
	{
		this.value = _value;
	}
}

