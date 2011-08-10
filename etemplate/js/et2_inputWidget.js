/**
 * eGroupWare eTemplate2 - JS Widget base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id: et2_widget.js 36021 2011-08-07 13:43:46Z igel457 $
 */

"use strict";

/*egw:uses
	jquery.jquery;
	et2_baseWidget;
*/

/**
 * Interface for all widgets which support returning a value
 */
var et2_IInput = new Interface({
	/**
	 * getValue has to return the value of the input widget
	 */
	getValue: function() {},

	/**
	 * Is dirty returns true if the value of the widget has changed since it
	 * was loaded.
	 */
	isDirty: function() {},

	/**
	 * Causes the dirty flag to be reseted.
	 */
	resetDirty: function() {}
});

/**
 * et2_inputWidget derrives from et2_simpleWidget and implements the IInput
 * interface. When derriving from this class, call setDOMNode with an input
 * DOMNode.
 */
var et2_inputWidget = et2_baseWidget.extend(et2_IInput, {

	attributes: {
		"value": {
			"name": "Value",
			"description": "The value of the widget",
			"type": "string",
			"default": ""
		}
	},

	init: function() {
		this._super.apply(this, arguments);

		this._oldValue = "";
	},

	set_value: function(_value) {
		this._oldValue = _value;

		if (this.node)
		{
			$j(this.node).val(_value);
		}
	},

	get_value: function() {
		return this.getValue();
	},

	getValue: function() {
		if (this.node)
		{
			return $j(this.node).val();
		}

		return this._oldValue;
	},

	isDirty: function() {
		return this._oldValue != this.getValue();
	},

	resetDirty: function() {
		this._oldValue = this.getValue();
	}

});

