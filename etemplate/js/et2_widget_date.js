/**
 * eGroupWare eTemplate2 - JS Date object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

/**
 * Class which implements the "date" XET-Tag
 */ 
var et2_date = et2_inputWidget.extend({

	attributes: {
		"value": {
			"type": "any"
		},
		"type": {
			"ignore": false
		}
	},

	/**
	 * Internal container for working easily with dates
	 */
	date: new Date(),

	init: function() {
		this._super.apply(this, arguments);

		this.input = null;

		this.createInputWidget();
	},

	createInputWidget: function() {
		this.input = $j(document.createElement("input"));

		var type="text";
		switch(this.type) {
			case "date":
				type = "date";
				break;
			case "date-timeonly":
				type = "time";
				break;
			case "date-time":
				type = "datetime-local";
				break;
		}
		this.input.attr("type", type).addClass("et2_date");

		this.setDOMNode(this.input[0]);
	},

	set_type: function(_type) {
		this.type = _type;
		this.createInputWidget();
	},

	set_value: function(_value) {
		if(typeof _value == 'string' && isNaN(_value)) {
			_value = Date.parse(_value);
		}
		this.value = _value;

		// JS dates use milliseconds
		this.date.setTime(parseInt(_value)*1000);
		var display = this.date.toString();

		// TODO: Use user's preference, not browser's locale
		switch(this.type) {
			case "date":
				display = this.date.toLocaleDateString();
				break;
			case "date-timeonly":
				display = this.date.toLocaleTimeString();
				break;
			case "date-time":
				display = this.date.toLocaleString();
				break;
		}
		this._super.apply(this, [display]);
	}
});

et2_register_widget(et2_date, ["date", "date-time", "date-timeonly"]);

/**
 * et2_date_ro is the dummy readonly implementation of the date widget.
 */
var et2_date_ro = et2_valueWidget.extend({

	/**
	 * Ignore all more advanced attributes.
	 */
	attributes: {
		"value": {
			"type": "integer"
		},
		"type": {
			"ignore": false
		}
	},

	/**
	 * Internal container for working easily with dates
	 */
	date: new Date(),

	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("time"))
			.addClass("et2_date_ro");

		this.setDOMNode(this.span[0]);
	},

	set_value: function(_value) {
		this.value = _value;

		// JS dates use milliseconds
		this.date.setTime(parseInt(_value)*1000);
		var display = this.date.toString();

		// TODO: Use user's preference, not browser's locale
		switch(this.type) {
			case "date":
				display = this.date.toLocaleDateString();
				break;
			case "time":
				display = this.date.toLocaleTimeString();
				break;
			case "date-time":
				display = this.date.toLocaleString();
				break;
		}
		this.span.attr("datetime", this.date.toUTCString()).text(display);
	}

});

et2_register_widget(et2_date_ro, ["date_ro", "date-time_ro"]);

