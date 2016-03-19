/*
 * Egroupware etemplate2 JS Entry widget
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */


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
var et2_entry = (function(){ "use strict"; return et2_valueWidget.extend(
{
	attributes: {
		field: {
			'name': 'Fields',
			'description': 'Which entry field to display, or "sum" to add up the alternate_fields',
			'type': 'string'
		},
		compare: {
			name: 'Compare',
			description: 'if given, the selected field is compared with its value and an X is printed on equality, nothing otherwise',
			default: et2_no_init,
			type: 'string'
		},
		alternate_fields: {
			name: 'Alternate fields',
			description: 'colon (:) separated list of alternative fields.  The first non-empty one is used if the selected field is empty, (-) used for subtraction',
			type: 'string',
			default: et2_no_init
		},
		precision: {
			name: 'Decimals to be shown',
			description: 'Specifies the number of decimals for sum of alternates, the default is 2',
			type: 'string',
			default: '2'
		},
		value: {
			type: 'any'
		},
		readonly: {
			default: true
		}
	},

	legacyOptions: ["field","compare","alternate_fields"],

	prefix: '~',

	/**
	 * Constructor
	 *
	 * @memberOf et2_customfields_list
	 */
	init: function(parent, attrs) {
		// Often the ID conflicts, so check prefix
		if(attrs.id && attrs.id.indexOf(this.prefix) < 0)
		{
			attrs.id = this.prefix + attrs.id;
		}
		var value = attrs.value;

		this._super.apply(this, arguments);

		// Save value from parsing, but only if set
		if(value)
		{
			this.options.value = value;
		}

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
		var attrs = {
			id: this.id + '[' +this.options.field+']',
			type: 'label',
			readonly: this.options.readonly
		};
		var modifications = this.getArrayMgr("modifications");
		if(modifications && this.options.field)
		{
			jQuery.extend(attrs, modifications.getEntry(attrs.id));
		}

		// Supress labels on templates
		if(attrs.type == 'template' && this.options.label)
		{
			this.egw().debug('log', "Surpressed label on <" + this._type + ' label="' + this.options.label + '" id="' + this.id + '"...>');
			this.options.label = '';
		}
		var widget = et2_createWidget(attrs.type, attrs, this);

		// If value is not set, etemplate takes care of everything
		// If value was set, find the record explicitly.
		if(typeof this.options.value == 'string')
		{
			widget.options.value = this.getRoot().getArrayMgr('content').getEntry(this.prefix+this.options.value + '['+this.options.field+']');
		}
		if(this.options.compare)
		{
			widget.options.value = widget.options.value == this.options.compare ? 'X' : '';
		}
		if(this.options.alternate_fields)
		{
			var sum = 0;
			var fields = this.options.alternate_fields.split(':');
			for(var i = 0; i < fields.length; i++)
			{
				var value =  (fields[i][0] == "-")? this.getArrayMgr('content').getEntry(fields[i].replace('-',''))*-1:
								this.getArrayMgr('content').getEntry(fields[i]);
				sum += parseFloat(value);
				if(value && this.options.field !== 'sum')
				{
					widget.options.value = value;
					break;
				}
			}
			if(this.options.field == 'sum')
			{
				if (this.options.precision && jQuery.isNumeric(sum)) sum = parseFloat(sum).toFixed(this.options.precision);
				widget.options.value = sum;
			}
		}

	}
});}).call(this);

et2_register_widget(et2_entry, ["entry", 'contact-value', 'contact-account', 'contact-template', 'infolog-value','tracker-value','records-value']);