/**
 * EGroupware eTemplate2 - JS Textbox object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

import './et2_core_common';
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_valueWidget} from './et2_core_valueWidget'
import {et2_inputWidget} from './et2_core_inputWidget'
import {et2_IDetachedDOM, et2_IResizeable} from "./et2_core_interfaces";
import {et2_csvSplit, et2_no_init} from "./et2_core_common";
import {egw} from "../jsapi/egw_global";

/**
 * Class which implements the "textbox" XET-Tag
 *
 * @augments et2_inputWidget
 */
export class et2_textbox extends et2_inputWidget implements et2_IResizeable
{
	static readonly _attributes : any = {
		"multiline": {
			"name": "multiline",
			"type": "boolean",
			"default": false,
			"description": "If true, the textbox is a multiline edit field."
		},
		"size": {
			"name": "Size",
			"type": "integer",
			"default": et2_no_init,
			"description": "Field width"
		},
		"maxlength": {
			"name": "Maximum length",
			"type": "integer",
			"default": et2_no_init,
			"description": "Maximum number of characters allowed"
		},
		"blur": {
			"name": "Placeholder",
			"type": "string",
			"default": "",
			"description": "This text get displayed if an input-field is empty and does not have the input-focus (blur). It can be used to show a default value or a kind of help-text."
		},
		// These for multi-line
		"rows": {
			"name": "Rows",
			"type": "integer",
			"default": -1,
			"description": "Multiline field height - better to use CSS"
		},
		"cols": {
			"name": "Size",
			"type": "integer",
			"default": -1,
			"description": "Multiline field width - better to use CSS"
		},
		"validator": {
			"name": "Validator",
			"type": "string",
			"default": et2_no_init,
			"description": "Perl regular expression eg. '/^[0-9][a-f]{4}$/i'"
		},
		onkeypress: {
			name: "onKeypress",
			type: "js",
			default: et2_no_init,
			description: "JS code or app.$app.$method called when key is pressed, return false cancels it."
		}
	};

	public static readonly legacyOptions: string[] = ["size", "maxlength", "validator"];
	input: JQuery = null;
	size: number;
	maxLength: number;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_textbox._attributes, _child || {}));

		this.input = null;

		this.createInputWidget();
	}

	createInputWidget()
	{
		if (this.options.multiline || this.options.rows > 1 || this.options.cols > 1)
		{
			this.input = jQuery(document.createElement("textarea"));

			if (this.options.rows > 0)
			{
				this.input.attr("rows", this.options.rows);
			}

			if (this.options.cols > 0)
			{
				this.input.attr("cols", this.options.cols);
			}
		}
		else
		{
			this.input = jQuery(document.createElement("input"));
			switch(this.options.type)
			{
				case "hidden":
					this.input.attr("type", "hidden");
					break;
			}
			if (this.options.autocomplete) this.input.attr("autocomplete", this.options.autocomplete);
		}

		if(this.options.size) {
			this.set_size(this.options.size);
		}
		if(this.options.blur) {
			this.set_blur(this.options.blur);
		}
		if(this.options.readonly) {
			this.set_readonly(true);
		}
		this.input.addClass("et2_textbox");
		this.setDOMNode(this.input[0]);
		if(this.options.value)
		{
			this.set_value(this.options.value);
		}
		if (this.options.onkeypress && typeof this.options.onkeypress == 'function')
		{
			var self = this;
			this.input.keypress(function(_ev)
			{
				return self.options.onkeypress.call(this, _ev, self);
			});
		}
	}

	destroy()
	{
		var node = this.getInputNode();
		if (node) jQuery(node).unbind("keypress");

		super.destroy();
	}

	getValue()
	{
		// only return "" for blur-value, if browser does not support html5 placeholder
		if (this.options && this.options.blur && this.input &&
			!(<HTMLInputElement>this.input[0]).placeholder &&
			this.input.val() == this.options.blur)
		{
			return "";
		}

		return super.getValue();
	}

	/**
	 * Clientside validation using regular expression in "validator" attribute
	 *
	 * @param {array} _messages
	 */
	isValid(_messages)
	{
		var ok = true;
		// Check input is valid
		if(this.options && this.options.validator && !this.options.readonly && !this.disabled)
		{
			if (typeof this.options.validator == 'string')
			{
				var parts = this.options.validator.split('/');
				var flags = parts.pop();
				if (parts.length < 2 || parts[0] !== '')
				{
					_messages.push(this.egw().lang("'%1' has an invalid format !!!", this.options.validator));
					return false;	// show invalid expression
				}
				parts.shift();
				this.options.validator = new RegExp(parts.join('/'), flags);
			}
			var value = this.getValue();
			if (!(ok = this.options.validator.test(value)))
			{
				_messages.push(this.egw().lang("'%1' has an invalid format !!!", value));
			}
		}
		return super.isValid(_messages) && ok;
	}

	/**
	 * Set input widget size
	 * @param _size Rather arbitrary size units, approximately characters
	 */
	set_size(_size : number)
	{
		if (this.options.multiline || this.options.rows > 1 || this.options.cols > 1)
		{
			this.input.css('width', _size + "em");
		}
		else if (typeof _size != 'undefined' && _size != parseInt(this.input.attr("size")))
		{
			this.size = _size;
			this.input.attr("size", this.size);
		}
	}

	/**
	 * Set maximum characters allowed
	 * @param _size Max characters allowed
	 */
	set_maxlength(_size : number)
	{
		if (typeof _size != 'undefined' && _size != parseInt(this.input.attr("maxlength")))
		{
			this.maxLength = _size;
			this.input.attr("maxLength", this.maxLength);
		}
	}

	/**
	 * Set HTML readonly attribute.
	 * Do not confuse this with etemplate readonly, which would use et_textbox_ro instead
	 * @param _readonly Boolean
	 */
	set_readonly(_readonly)
	{
		this.input.attr("readonly", _readonly);
		this.input.toggleClass('et2_textbox_ro', _readonly);
	}

	set_blur(_value)
	{
		if(_value) {
			this.input.attr("placeholder", this.egw().lang(_value) + "");	// HTML5
			if (!(<HTMLInputElement>this.input[0]).placeholder) {
				// Not HTML5
				if(this.input.val() == "") this.input.val(this.egw().lang(this.options.blur));
				this.input.focus(this,function(e) {
					if(e.data.input.val() == e.data.egw().lang(e.data.options.blur)) e.data.input.val("");
				}).blur(this, function(e) {
					if(e.data.input.val() == "") e.data.input.val(e.data.egw().lang(e.data.options.blur));
				});
			}
		} else {
			if (!this.getValue()) this.input.val('');
			this.input.removeAttr("placeholder");
		}
		this.options.blur = _value;
	}

	set_autocomplete(_value)
	{
		this.options.autocomplete = _value;
		this.input.attr('autocomplete', _value);
	}

	resize(_height : number)
	{
		if (_height && this.options.multiline)
		{
			// apply the ratio
			_height = (this.options.resize_ratio != '')? _height * this.options.resize_ratio: _height;
			if (_height != 0)
			{
				this.input.height(this.input.height() + _height);
				// resize parent too, so mailvelope injected into parent inherits its height
				this.input.parent().height(this.input.parent().height()+_height);
			}
		}
	}
}
et2_register_widget(et2_textbox, ["textbox", "hidden"]);

/**
 * et2_textbox_ro is the dummy readonly implementation of the textbox.
 *
 * @augments et2_valueWidget
 */
export class et2_textbox_ro extends et2_valueWidget implements et2_IDetachedDOM
{
	/**
	 * Ignore all more advanced attributes.
	 */
	static readonly _attributes : any = {
		"multiline": {
			"ignore": true
		},
		"maxlength": {
			"ignore": true
		},
		"onchange": {
			"ignore": true
		},
		"rows": {
			"ignore": true
		},
		"cols": {
			"ignore": true
		},
		"size": {
			"ignore": true
		},
		"needed": {
			"ignore": true
		}
	};
	value: string = "";
	span: JQuery;
	value_span: JQuery;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_textbox_ro._attributes, _child || {}));

		this.span = jQuery(document.createElement("label"))
			.addClass("et2_label");
		this.value_span = jQuery(document.createElement("span"))
			.addClass("et2_textbox_ro")
			.appendTo(this.span);

		this.setDOMNode(this.span[0]);
	}

	set_label(label)
	{
		// Remove current label
		this.span.contents()
			.filter(function(){ return this.nodeType == 3; }).remove();

		var parts = et2_csvSplit(label, 2, "%s");
		this.span.prepend(parts[0]);
		this.span.append(parts[1]);
		this.label = label;

		// add class if label is empty
		this.span.toggleClass('et2_label_empty', !label || !parts[0]);
	}

	set_value(_value)
	{
		this.value = _value;

		if(!_value)
		{
			_value = "";
		}
		if (this.label !="")
		{
			this.span.removeClass('et2_label_empty');
		}
		else
		{
			this.span.addClass('et2_label_empty');
		}
		this.value_span.text(_value);
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("value", "label");
	}

	getDetachedNodes()
	{
		return [this.span[0], this.value_span[0]];
	}

	setDetachedAttributes(_nodes, _values)
	{
		this.span = jQuery(_nodes[0]);
		this.value_span = jQuery(_nodes[1]);
		if(typeof _values["label"] != 'undefined')
		{
			this.set_label(_values["label"]);
		}
		if(typeof _values["value"] != 'undefined')
		{
			this.set_value(_values["value"]);
		}
	}
}
et2_register_widget(et2_textbox_ro, ["textbox_ro"]);

/**
 * et2_searchbox is a widget which provides a collapsable input search
 * with on searching indicator and clear handler regardless of any browser limitation.
 */
export class et2_searchbox extends et2_textbox
{
	/**
	 * Advanced attributes
	 */
	static readonly _attributes : any = {
		overlay:{
			name:"Overlay searchbox",
			type:"boolean",
			default:false,
			description:"Define wheter the searchbox overlays while it's open (true) or stay as solid box infront of the search button (false). Default is false."
		},
		fix: {
			name:"Fix searchbox",
			type:"boolean",
			default:true,
			description:"Define whether the searchbox should be a fix input field or flexible search button. Default is true (fix)."
		}
	}
	value: string = "";
	div: JQuery;
	flex: JQuery;
	button: JQuery;
	search: et2_textbox;
	oldValue: any;
	clear: JQuery;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_searchbox._attributes, _child || {}));
	}

	createInputWidget()
	{
		var self = this;

		this.div = jQuery(document.createElement('div'))
			.addClass('et2_searchbox');
		this.flex = jQuery(document.createElement('div'))
			.addClass('flex')
			.appendTo(this.div);
		this.setDOMNode(this.div[0]);
		if (this.options.overlay) this.flex.addClass('overlay');

		// search button indicator
		// no need to create search button if it's a fix search field
		if (!this.options.fix)
		{
			this.button = jQuery(document.createElement('button'))
				.css({"background-image": egw.image('search')})
				.click(function(){
					self._show_hide(jQuery(self.flex).hasClass('hide'));
					self.search.input.focus();
				})
				.addClass('et2_button');
			this.div.prepend(this.button);
		}
		// input field
		this.search = <et2_textbox><unknown>et2_createWidget('textbox',{"blur":egw.lang("search"),
			onkeypress:function(event) {
				if(event.which == 13)
				{
					event.preventDefault();
					self.getInstanceManager().autocomplete_fixer();
					// Use a timeout to make sure we get the autocomplete value,
					// if one was chosen, instead of what was actually typed.
					// Chrome doesn't need this, but FF does.
					window.setTimeout(function() {
						self.set_value(self.search.input.val());
						self.change();
					},0);
				}
			}},this);
		// Autocomplete needs name
		this.search.input.attr('name', this.id||'searchbox');
		this.search.input.on({
			keyup:function(event)
			{
				self.clear.toggle(self.get_value() !='' || !self.options.fix);
				if(event.which == 27) // Escape
				{
					// Excape clears search
					self.set_value('');
				}
			},

			blur(event){
				if (egwIsMobile()) return;
				if (!event.relatedTarget || !jQuery(event.relatedTarget.parentNode).hasClass('et2_searchbox'))
				{
					self._show_hide((!self.options.overlay && self.get_value()));
				}
				if (typeof self.oldValue !='undefined' && self._oldValue != self.get_value()) {
					self.change();
				}
			},
			mousedown:function(event){
				if (event.target.tagName == 'span') event.stopImmediatePropagation();
			}
		});

		// clear button implementation
		this.clear = jQuery(document.createElement('span'))
				.addClass('ui-icon clear')
				.toggle(!this.options.fix || (this._oldValue != '' && !jQuery.isEmptyObject(this._oldValue)))
				.on('mousedown',function(event){
					event.preventDefault();
				})
				.on('click',function(event) {
					if (self.get_value()){
						self.search.input.val('');
						self.search.input.focus();
						self._show_hide(true);
						if (self._oldValue) self.change();
					}
					else
					{
						self._show_hide(false);
					}
					if (self.options.fix) self.clear.hide();
				})
				.appendTo(this.flex);
	}

	/**
	 * Show/hide search field
	 * @param {boolean} _stat true means show and false means hide
	 */
	_show_hide(_stat)
	{
		// Not applied for fix option
		if (this.options.fix) return;

		jQuery(this.flex).toggleClass('hide',!_stat);
		jQuery(this.getDOMNode(this)).toggleClass('expanded', _stat);
	}

	/**
	 * toggle search button status based on value
	 */
	_searchToggleState()
	{
		if (this.options.fix || egwIsMobile()) return;

		if (!this.get_value())
		{
			jQuery(this.button.getDOMNode()).removeClass('toolbar_toggled');
			this.button.set_statustext('');
		}
		else
		{
			jQuery(this.button.getDOMNode()).addClass('toolbar_toggled');
			this.button.set_statustext(egw.lang("search for '%1'", this.get_value()));
		}
	}

	/**
	 * override change function in order to preset the toggle state
	 */
	change()
	{
		this._searchToggleState();

		super.change.apply(this,arguments);
	}

	getInputNode(): HTMLElement
	{
		return this.search?.input.get(0);
	}

	get_value()
	{
		return  this.search.input.val();
	}

	set_value(_value)
	{
		super.set_value(_value);
		if (this.search)
		{
			this.search.input.val(_value);
			this.clear.toggle(_value != '');
		}
	}

	set_readonly(_readonly)
	{
		this.search.set_readonly(_readonly);
	}
	set_blur(_value)
	{
		this.search.set_blur(_value);
	}
	/**
	 * override doLoadingFinished in order to set initial state
	 */
	doLoadingFinished()
	{
		super.doLoadingFinished();

		if (!this.get_value())
		{
			this._show_hide(false);
		}
		else
		{
			this._show_hide(!this.options.overlay);
			this._searchToggleState();
		}
		return true;
	}

	getDOMNode(asker)
	{
		if(asker && asker.getParent() == this)
		{
			return this.flex[0];
		}
		return super.getDOMNode(asker);
	}
	/**
	 * Overrride attachToDOM in order to unbind change handler
	 */
	attachToDOM()
	{
		let ret = super.attachToDOM();

		var node = this.getInputNode();
		if (node)
		{
			jQuery(node).off('.et2_inputWidget');
		}
		return ret;
	}
}
et2_register_widget(et2_searchbox, ["searchbox"]);
