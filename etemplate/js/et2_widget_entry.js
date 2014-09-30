/* 
 * Egroupware etemplate2 JS Entry widget
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */


"use strict";

/*egw:uses
	et2_core_valueWidget;
*/

/**
 * A widget to display a value from an entry
 *
 * Since we have etemplate_widget_transformer, this client side widget exists
 * mostly to resolve the problem where the ID for the entry widget is the same
 * as the widget where you actually set the value, which prevents transformer
 * from working.
 *
 * Server side will find the associated entry, and load it into ~<entry_id> to
 * avoid overwriting the widget with id="entry_id".  This widget will reverse
 * that, and the modifications from transformer will be applied.
 *
 * @augments et2_valueWidget
 */
var et2_entry = et2_valueWidget.extend(
{
	attributes: {
		field: {
			'name': 'Fields',
			'description': 'Which entry field to display',
			'type': 'string'
		},
		value: {
			type: 'any'
		},
		readonly: {
			default: true
		}
	},

	legacyOptions: ["field"],

	// Doesn't really need a namespace, but this simplifies the sub-widgets
	createNamespace: true,

	prefix: '~',

	/**
	 * Constructor
	 *
	 * @memberOf et2_customfields_list
	 */
	init: function(parent, attrs) {
		// Often the ID conflicts, so check prefix
		if(attrs.id && attrs.id.indexOf(this.prefix) < 0 && typeof attrs.value == 'undefined')
		{
			attrs.id = this.prefix + attrs.id;
		}

		this._super.apply(this, arguments);

		this.widget = null;
		this.setDOMNode(document.createElement('span'));
	},

	loadFromXML: function(_node) {
		// Load the nodes as usual
		this._super.apply(this, arguments);

		// Do the magic
		this.loadField();
	},

	/**
	 * Initialize widget for entry field
	 */
	loadField: function() {
		// Create widget of correct type
		var modifications = this.getArrayMgr("modifications");
		if(modifications && this.options.field) {
			var entry = modifications.getEntry(this.options.field);
			if(entry == null)
			{
				entry = {type: 'label'};
			}
		}
		var attrs = {
			id: this.options.field,
			type: entry.type,
			readonly: this.options.readonly
		};
		var widget = et2_createWidget(attrs.type, attrs, this);
	}
});

et2_register_widget(et2_entry, ["entry", 'contact-value', 'contact-account', 'contact-template']);