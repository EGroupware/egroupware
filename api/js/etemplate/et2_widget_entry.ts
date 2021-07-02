/*
 * Egroupware etemplate2 JS Entry widget
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


/*egw:uses
	et2_core_valueWidget;
*/

import {et2_createWidget, et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_valueWidget} from "./et2_core_valueWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_cloneObject, et2_no_init} from "./et2_core_common";

/**
 * A widget to display a value from an entry
 *
 * Since we have Etemplate\Widget\Transformer, this client side widget exists
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
export class et2_entry extends et2_valueWidget
{
	static readonly _attributes : any = {
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
		regex: {
			name: 'Regular expression pattern',
			description: 'Only used server-side in a preg_replace with regex_replace to modify the value',
			default: et2_no_init,
			type: 'string'
		},
		regex_replace: {
			name: 'Regular expression replacement pattern',
			description: 'Only used server-side in a preg_replace with regex to modify the value',
			default: et2_no_init,
			type: 'string'
		},
		value: {
			type: 'any'
		},
		readonly: {
			default: true
		}
	};

	public static readonly legacyOptions : string[] = ["field","compare","alternate_fields"];

	public static readonly prefix = '~';
	protected widget = null;

	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_entry._attributes, _child || {}));

		// Often the ID conflicts, so check prefix
		if(_attrs.id && _attrs.id.indexOf(et2_entry.prefix) < 0)
		{
			_attrs.id = et2_entry.prefix + _attrs.id;
		}
		let value = _attrs.value;

		// Add all attributes hidden in the content arrays to the attributes
		// parameter
		this.transformAttributes(_attrs);

		// Create a local copy of the options object
		this.options = et2_cloneObject(_attrs);

		// Save value from parsing, but only if set
		if(value)
		{
			this.options.value = value;
		}

		this.widget = null;
		this.setDOMNode(document.createElement('span'));
	}

	loadFromXML(_node)
	{
		// Load the nodes as usual
		super.loadFromXML(_node);
		// Do the magic
		this.loadField();
	}

	/**
	 * Initialize widget for entry field
	 */
	loadField()
	{
		// Create widget of correct type
		let attrs = {
			id: this.id + (this.options.field ? '[' +this.options.field+']' : ''),
			type: 'label',
			readonly: this.options.readonly
		};
		let  modifications = this.getArrayMgr("modifications");
		if(modifications && this.options.field)
		{
			jQuery.extend(attrs, modifications.getEntry(attrs.id));
		}

		// Supress labels on templates
		if(attrs.type == 'template' && this.options.label)
		{
			this.egw().debug('log', "Surpressed label on <" + this.getType() + ' label="' + this.options.label + '" id="' + this.id + '"...>');
			this.options.label = '';
		}
		let widget = et2_createWidget(attrs.type, attrs, this);

		// If value is not set, etemplate takes care of everything
		// If value was set, find the record explicitly.
		if(typeof this.options.value == 'string')
		{
			widget.options.value = this.getArrayMgr('content').getEntry(this.id+'['+this.options.field+']') ||
				this.getRoot().getArrayMgr('content').getEntry(et2_entry.prefix+this.options.value + '['+this.options.field+']');
		}
		else if (this.options.field && this.options.value && this.options.value[this.options.field])
		{
			widget.options.value = this.options.value[this.options.field];
		}
		if(this.options.compare)
		{
			widget.options.value = widget.options.value == this.options.compare ? 'X' : '';
		}
		if(this.options.alternate_fields)
		{
			let sum : number | string = 0;
			let fields = this.options.alternate_fields.split(':');
			for(let i = 0; i < fields.length; i++)
			{
				let negate = (fields[i][0] == "-");
				let value =  this.getArrayMgr('content').getEntry(fields[i].replace('-',''));
				sum += typeof value === 'undefined' ? 0 : (parseFloat(value) * (negate ? -1 : 1));
				if(value && this.options.field !== 'sum')
				{
					widget.options.value = value;
					break;
				}
			}
			if(this.options.field == 'sum')
			{
				if (this.options.precision && jQuery.isNumeric(sum)) sum = parseFloat(<string><unknown>sum).toFixed(this.options.precision);
				widget.options.value = sum;
			}
		}
	}
}
et2_register_widget(et2_entry, ["entry", 'contact-value', 'contact-account', 'contact-template', 'infolog-value','tracker-value','records-value']);
