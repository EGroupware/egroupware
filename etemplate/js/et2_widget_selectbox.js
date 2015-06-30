/**
 * EGroupware eTemplate2 - JS Selectbox object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @author Andreas Stöckel
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	/phpgwapi/js/jquery/chosen/chosen.jquery.js;
	et2_core_xml;
	et2_core_DOMWidget;
	et2_core_inputWidget;
*/

/**
 * @augments et2_inputWidget
 */
var et2_selectbox = et2_inputWidget.extend(
{
	attributes: {
		"multiple": {
			"name": "multiple",
			"type": "boolean",
			"default": false,
			"description": "Allow selecting multiple options"
		},
		"expand_multiple_rows": {
			"name": "Expand multiple",
			"type": "integer",
			"default": et2_no_init,
			"description": "Shows single select widget, with a button.  If the "+
				"user clicks the button, the input will toggle to a multiselect,"+
				"with this many rows.  "
		},
		"rows": {
			"name": "Rows",
			"type": "any",	// Old options put either rows or empty_label in first space
			"default": 1,
			"description": "Number of rows to display"
		},
		"empty_label": {
			"name": "Empty label",
			"type": "string",
			"default": "",
			"description": "Textual label for first row, eg: 'All' or 'None'.  ID will be ''",
			translate:true
		},
		"select_options": {
			"type": "any",
			"name": "Select options",
			"default": {},
			"description": "Internaly used to hold the select options."
		},
		"selected_first": {
			"name": "Selected options first",
			"type": "boolean",
			"default": true,
			"description": "For multi-selects, put the selected options at the top of the list when first loaded"
		},

		// Chosen options
		"search": {
			"name": "Search",
			"type": "boolean",
			"default": false,
			"description": "For single selects, add a search box to the drop-down list"
		},
		"tags": {
			"name": "Tag style",
			"type": "boolean",
			"default": false,
			"description": "For multi-selects, displays selected as a list of tags instead of a big list"
		},

		// Value can be string or integer
		"value": {
			"type": "any"
		},
		// Type specific legacy options.  Avoid using.
		"other": {
			"ignore": true,
			"type": "any"
		}
	},

	legacyOptions: ["rows","other"], // Other is sub-type specific

	/**
	 * Construtor
	 *
	 * @memberOf et2_selectbox
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.input = null;
		// Start at '' to avoid infinite loops while setting value/select options
		this.value = '';

		// Allow no other widgets inside this one
		this.supportedWidgetClasses = [];

		// Legacy options could have row count or empty label in first slot
		if(typeof this.options.rows == "string")
		{
			if(isNaN(this.options.rows))
			{
				this.options.empty_label = this.egw().lang(this.options.rows);
				this.options.rows = 1;
			}
			else
			{
				this.options.rows = parseInt(this.options.rows);
			}
		}

		if(this.options.rows > 1)
		{
			this.options.multiple = true;
			if(this.options.tags)
			{
				this.createInputWidget();
			}
			else
			{
				this.createMultiSelect();
			}
		}
		else
		{
			this.createInputWidget();
		}
	},

	destroy: function() {
		if(this.input != null)
		{
			this.input.unchosen();
		}
		if(this.expand_button)
		{
			this.expand_button.off();
			this.expand_button.remove();
			this.expand_button = null;
		}
		this._super.apply(this, arguments);

		this.input = null;
	},

	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		// If select_options are already known, skip the rest
		if(this.options && this.options.select_options && !jQuery.isEmptyObject(this.options.select_options) ||
			_attrs.select_options && !jQuery.isEmptyObject(_attrs.select_options) ||
			// Allow children to skip select_options - check to make sure default got set to something (should be {})
			typeof _attrs.select_options == 'undefined' || _attrs.select_options === null
		)
		{
			return;
		}

		var sel_options = et2_selectbox.find_select_options(this, _attrs['select_options'], _attrs);
		if(!jQuery.isEmptyObject(sel_options))
		{
			_attrs['select_options'] = sel_options;
		}
	},

	/**
	 * Switch instanciated widget to multi-selection and back, optionally enabeling tags too
	 *
	 * If you want to switch tags on too, you need to do so after switching to multiple!
	 *
	 * @param {boolean} _multiple
	 * @param {integer} _size default=3
	 */
	set_multiple: function(_multiple, _size)
	{
		this.options.multiple = _multiple;

		if (this.input)
		{
			if (_multiple)
			{
				this.input.attr('size', _size || 3);
				this.input.attr('multiple', true);
				this.input.attr('name', this.id + '[]');

				if (this.input[0].options.length && this.input[0].options[0].value === '')
				{
					this.input[0].options[0] = null;
				}
			}
			else
			{
				this.input.attr('multiple', false);
				this.input.removeAttr('size');
				this.input.attr('name', this.id);

				if (this.options.empty_label && this.input[0].options[0].value !== '')
				{
					this._appendOptionElement('', this.options.empty_label);
				}
			}
		}
	},

	/**
	 * Add an option to regular drop-down select
	 *
	 * @param {string} _value value attribute of option
	 * @param {string} _label label of option
	 * @param {string} _title title attribute of option
	 * @param {node} dom_element parent of new option
	 */
	_appendOptionElement: function(_value, _label, _title, dom_element) {
		if(_value == "" && (_label == null || _label == "")) {
			return;	// empty_label is added in set_select_options anyway, ignoring it here to not add it twice
		}

		if(this.input == null)
		{
			return this._appendMultiOption(_value, _label, _title, dom_element);
		}

		var option = $j(document.createElement("option"))
			.attr("value", _value)
			.text(_label+"");

		if (typeof _title != "undefined" && _title)
		{
			option.attr("title", _title);
		}
		if(_label == this.options.empty_label || this.options.empty_label == "" && _value === "")
		{
			// Make sure empty / all option is first
			option.prependTo(this.input);
		}
		else
		{
			option.appendTo(dom_element || this.input);
		}
	},

	/**
	 * Append a value to multi-select
	 *
	 * @param {string} _value value attribute of option
	 * @param {string} _label label of option
	 * @param {string} _title title attribute of option
	 * @param {node} dom_element parent of new option
	 */
	_appendMultiOption: function(_value, _label, _title, dom_element) {
		var option_data = null;
		if(typeof _label == "object")
		{
			option_data = _label;
			_label = option_data.label;
		}

		// Already in header
		if(_label == this.options.empty_label) return;

		var opt_id = this.dom_id + "_opt_" + _value;
		var label = jQuery(document.createElement("label"))
			.attr("for", opt_id)
			.hover(
				function() {jQuery(this).addClass("ui-state-hover");},
				function() {jQuery(this).removeClass("ui-state-hover");}
			);
		var option = jQuery(document.createElement("input"))
			.attr("type", "checkbox")
			.attr("id",opt_id)
			.attr("value", _value)
			.appendTo(label);
		if(typeof _title !== "undefined")
		{
			option.attr("title",_title);
		}

		// Some special stuff for categories
		if(option_data )
		{
			if(option_data.icon)
			{
				var img = this.egw().image(option_data.icon);
				jQuery(document.createElement("img"))
					.attr("src", img)
					.appendTo(label);
			}
			if(option_data.color)
			{
				label.css("background-color",option_data.color);
			}
		}
		label.append(jQuery("<span>"+_label+"</span>"));
		var li = jQuery(document.createElement("li")).append(label);

		li.appendTo(dom_element || this.multiOptions);
	},

	/**
	 * Create a regular drop-down select box
	 */
	createInputWidget: function() {
		// Create the base input widget
		this.input = $j(document.createElement("select"))
			.addClass("et2_selectbox")
			.attr("size", this.options.rows);

		this.setDOMNode(this.input[0]);

		// Add the empty label
		if(this.options.empty_label)
		{
			this._appendOptionElement("", this.options.empty_label);
		}

		// Set multiple
		if(this.options.multiple)
		{
			this.input.attr("multiple", "multiple");
		}
	},

	/**
	 * Create a list of checkboxes
	 */
	createMultiSelect: function() {
		var node = jQuery(document.createElement("div"))
			.addClass("et2_selectbox");

		var header = jQuery(document.createElement("div"))
			.addClass("ui-widget-header ui-helper-clearfix")
			.appendTo(node);
		var controls = jQuery(document.createElement("ul"))
			.addClass('ui-helper-reset')
			.appendTo(header);

		if(this.options.empty_label)
		{
			jQuery(document.createElement("span"))
				.text(this.options.empty_label)
				.addClass("ui-multiselect-header")
				.appendTo(header);
		}

		// Set up for options to be added later
		var options = this.multiOptions = jQuery(document.createElement("ul"));
		this.multiOptions.addClass("ui-multiselect-checkboxes ui-helper-reset")
			.css("height", 1.9*this.options.rows + "em")
			.appendTo(node);

		if(this.options.rows >= 5)
		{
			// Check / uncheck all
			var header_controls = {
				check: {
					icon_class: 'ui-icon-check',
					label:	'Check all',
					click: function(e) {
						var all_set = jQuery("input[type='checkbox']",e.data).prop("checked");
						jQuery("input[type='checkbox']",e.data).prop("checked", !all_set);
					}
				}
			};
			for(var key in header_controls)
			{
				jQuery(document.createElement("li"))
					.addClass("et2_clickable")
					.click(options, header_controls[key].click)
					.attr("title", header_controls[key].label)
					.append('<span class="ui-icon ' + header_controls[key].icon_class + '"/>')
					.appendTo(controls);
			}

		}

		this.setDOMNode(node[0]);
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		this.set_tags(this.options.tags, this.options.width);

		return true;
	},

	loadFromXML: function(_node) {
		// Handle special case where legacy option for empty label is used (conflicts with rows), and rows is set as an attribute
		var legacy = _node.getAttribute("options");
		if(legacy)
		{
			var legacy = legacy.split(",");
			if(legacy.length && isNaN(legacy[0]))
			{
				this.options.empty_label = legacy[0];
			}
		}

		// Read the option-tags
		var options = et2_directChildrenByTagName(_node, "options");
		for (var i = 0; i < options.length; i++)
		{
			this.options.select_options[et2_readAttrWithDefault(options[i], "value", options[i].textContent)] = {
				"label": options[i].textContent,
				"title": et2_readAttrWithDefault(options[i], "title", "")
			};
		}

		this.set_select_options(this.options.select_options);
	},

	/**
	 * Regular expression, to check string-value contains multiple comma-separated values
	 */
	_is_multiple_regexp: /^[,0-9A-Za-z/_-]+$/,

	/**
	 * Set value
	 *
	 * @param _value
	 */
	set_value: function(_value)
	{
		if (typeof _value == "number") _value = ""+_value;	// convert to string for consitent matching
		if(typeof _value == "string" && this.options.multiple && _value.match(this._is_multiple_regexp) !== null)
		{
			_value = _value.split(',');
		}
		if(this.input !== null && this.options.select_options && (
				!jQuery.isEmptyObject(this.options.select_options) || this.options.select_options.length > 0
			) && this.input.children().length == 0)
		{
			// No options set yet
			this.set_select_options(this.options.select_options);
		}
		this._oldValue = this.value;
		if(this.input !== null && (this.options.tags || this.options.search))
		{
			// Value must be a real Array, not an object
			this.input.val(typeof _value == 'object' && _value != null ? jQuery.map(_value,function(value,index){return [value];}) : _value);
			this.input.trigger("liszt:updated");
			this.value = this.input.val();
			return;
		}
		if(this.input == null)
		{
			return this.set_multi_value(_value);
		}
		if(typeof _value != 'string' && jQuery(this.value).not(_value).length == 0 && jQuery(_value).not(this.value).length == 0)
		{
			// Unchanged
			if(_value == this.value) return;
		}
		jQuery("option",this.input).prop("selected", false);
		if(typeof _value == "array")
		{
			for(var i = 0; i < _value.length; i++)
			{
				jQuery("option[value='"+_value[i]+"']", this.input).prop("selected", true);
			}
		}
		else if (typeof _value == "object")
		{
			for(var i in _value)
			{
				jQuery("option[value='"+_value[i]+"']", this.input).prop("selected", true);
			}
		}
		else
		{
			if(_value && jQuery("option[value='"+_value+"']", this.input).prop("selected", true).length == 0)
			{
				if(this.options.select_options[_value])
				{
					// Options not set yet? Do that now, which will try again.
					return this.set_select_options(this.options.select_options);
				}
				else if (jQuery.isEmptyObject(this.options.select_options))
				{
					this.egw().debug("warn", "Can't set value to '%s', widget has no options set",_value, this);
				}
				else
				{
					var debug_value = _value;
					if(debug_value === null) debug_value == 'NULL';
					this.egw().debug("warn", "Tried to set value '%s' that isn't an option", debug_value, this);
				}
				return;
			}
		}
		this.value = _value;
		if(this.isAttached() && this._oldValue !== et2_no_init && this._oldValue !== _value)
		{
			this.input.change();
		}
	},

	set_multi_value: function(_value) {
		jQuery("input",this.multiOptions).prop("checked", false);
		if(typeof _value == "array")
		{
			for(var i = 0; i < _value.length; i++)
			{
				jQuery("input[value='"+_value[i]+"']", this.multiOptions).prop("checked", true);
			}
		}
		else if (typeof _value == "object")
		{
			for(var i in _value)
			{
				jQuery("input[value='"+_value[i]+"']", this.multiOptions).prop("checked", true);
			}
		}
		else
		{
			if(jQuery("input[value='"+_value+"']", this.multiOptions).prop("checked", true).length == 0)
			{
				var debug_value = _value;
				if(debug_value === null) debug_value == 'NULL';
				this.egw().debug("warn", "Tried to set value '%s' that isn't an option", debug_value, this);
			}
		}

		// Sort selected to the top
		if(this.selected_first)
		{
			this.multiOptions.find("li:has(input:checked)").prependTo(this.multiOptions);
		}
		this.value = _value;
	},

	/**
	 * Add a button to toggle between single select and multi select.
	 *
	 * @param {number} _rows How many rows for multi-select
	 */
	set_expand_multiple_rows: function(_rows)
	{
		this.options.expand_multiple_rows = _rows;

		var surroundings = this.getSurroundings();
		if(_rows <= 1 )
		{
			// Remove
			surroundings.removeDOMNode(this.expand_button.get(0));
		}
		else
		{
			if (!this.expand_button)
			{
				var button_id = this.getInstanceManager().uniqueId+'_'+this.id.replace(/\./g, '-') + "_expand";
				this.expand_button = $j("<button class='et2_button et2_button_icon et2_selectbox_expand' id='" + button_id + "'/>")
					.on("click", jQuery.proxy(function(e) {
						if(typeof this.input.attr('size') !== 'undefined' && this.input.attr('size') != 1)
						{
							this.set_multiple(false, 1);
						}
						else
						{
							this.set_multiple(true, this.options.expand_multiple_rows);
						}
					},this));
			}
			surroundings.appendDOMNode(this.expand_button.get(0));
		}
		surroundings.update();
	},

	/**
	 * Turn tag style on and off
	 *
	 * If you want to switch multiple on too, you need to do so before switching tags on!
	 *
	 * @param {boolean} _tags
	 * @param {string} _width width to use, default width of selectbox
	 */
	set_tags: function(_tags, _width)
	{
		this.options.tags = _tags;

		// Can't actually do chosen until attached, loadingFinished should call again
		if(!this.isAttached()) return;

		if(this.input != null && !this.options.tags && !this.options.search)
		{
			this.input.unchosen().css('width', '');

			return;
		}

		// Turn on tags, if desired
		if(this.input != null && (this.options.search || this.options.tags) && !this.options.disabled)
		{
			if(this.options.empty_label)
			{
				this.input.attr("data-placeholder", this.options.empty_label);
				// Remove from list of options, if multiple
				if (this.options.multiple)
				{
					this.input.children('[value=""]').remove();
				}
			}
			// Don't do it again if already done
			if(this.input.hasClass("chzn-done")) return;

			// Properly size chosen, even if on a hidden tab
			var size = egw.getHiddenDimensions(this.input);
			this.input.chosen({
				inherit_select_classes: true,
				search_contains: true,
				width: _width || size.w + "px"
			})
			.change(this.onchange);
		}
	},

	/**
	 * The set_select_options function is added, as the select options have to be
	 * added after the "option"-widgets were added to selectbox.
	 *
	 * @param {(array|object)} _options array or object with options
	 */
	set_select_options: function(_options) {
		// Empty current options
		if(this.input)
		{
			this.input.empty();
		}
		else if (this.multiOptions)
		{
			this.multiOptions.empty();
		}
		// Re-add empty, it's usually not there (empty_label get's allways translated, independent of no_lang!)
		// Empty label should not be added as an option for chosen, it conflicts
		if(this.options.empty_label && !(this.options.tags || this.options.search))
		{
			this._appendOptionElement('',this.egw().lang(this.options.empty_label));
		}

		// Add the select_options
		for (var key in _options)
		{
			// Translate the options
			if(!this.options.no_lang)
			{
				if (typeof _options[key] === 'object' && _options[key] !== null)
				{
					if(_options[key]["label"]) _options[key]["label"] = this.egw().lang(_options[key]["label"]);
					if(_options[key]["title"]) _options[key]["title"] = this.egw().lang(_options[key]["title"]);
				}
				else
				{
					_options[key] = this.egw().lang(_options[key]);
				}
			}

			if (typeof _options[key] === 'object' && _options[key] !== null)
			{
				// Optgroup
				if(typeof _options[key]["label"] == 'undefined' && typeof _options[key]["title"] == "undefined")
				{
					var group = $j(document.createElement("optgroup"))
						.attr("label", this.options.no_lang ? key : this.egw().lang(key))
						.appendTo(this.input);
					if(this.input == null)
					{
						group = jQuery(document.createElement("ul"))
							.append('<li class="ui-widget-header"><span>'+key+'</span></li>')
							.appendTo(this.multiOptions);
					}

					for(var sub in _options[key])
					{
						if (typeof _options[key][sub] === 'object' && _options[key][sub] !== null)
						{
							this._appendOptionElement(sub,
								_options[key][sub]["label"] ? _options[key][sub]["label"] : "",
								_options[key][sub]["title"] ? _options[key][sub]["title"] : "",
								group
							);
						}
						else
						{
							this._appendOptionElement(sub, _options[key][sub],undefined,group);
						}
					}
				}
				else if(this.input == null)
				{
					// Allow some special extras for objects by passing the whole thing
					_options[key]["label"] = _options[key]["label"] ? _options[key]["label"] : "";
					this._appendMultiOption(typeof _options[key].value != 'undefined' ? _options[key].value : key,
						_options[key], _options[key]["title"]);
				}
				else
				{
					this._appendOptionElement(typeof _options[key].value != 'undefined'  ? _options[key].value : key,
						_options[key]["label"] ? _options[key]["label"] : "",
						_options[key]["title"] ? _options[key]["title"] : "");
				}
			}
			else
			{
				this._appendOptionElement(key, _options[key]);
			}
		}

		// Sometimes value gets set before options
		if(this.value || this.options.empty_label) this.set_value(this.value);
	},

	getValue: function() {
		if(this.input == null)
		{
			var value = [];
			jQuery("input:checked",this.multiOptions).each(function(){value.push(this.value);});
			// we need to return null for no value instead of empty array, which gets overwritten by preserved value on server-side
			this.value = value;
		}
		else
		{
			this.value = this._super.apply(this, arguments);
			if (this.value === null) this.value = [];	// do NOT return null, as it does not get transmitted to server
		}
		return this.value;
	},

	isDirty: function() {
		if(this.input == null)
		{
			var value = this.getValue();
			// Array comparison
			return !($j(this._oldValue).not(value).length == 0 && $j(value).not(this._oldValue).length == 0);
		}
		else
		{
			return this._super.apply(this, arguments);
		}
	}
});
et2_register_widget(et2_selectbox, ["menupopup", "listbox", "select", "select-cat",
	"select-percent", 'select-priority',
	'select-country', 'select-state', 'select-year', 'select-month',
	'select-day', 'select-dow', 'select-hour', 'select-number', 'select-app',
	'select-lang', 'select-bool', 'select-timezone' ]);

// Static class stuff
jQuery.extend(et2_selectbox,
{
	type_cache: {},

	/**
	 * Find the select options for a widget, out of the many places they could be.
	 * @param {et2_widget} widget to check for.  Should be some sort of select widget.
	 * @param {object} attr_options Select options in attributes array
	 * @param {object} attrs Widget attributes
	 * @return {object} Select options, or empty object
	 */
	find_select_options: function(widget, attr_options, attrs)
	{
		var name_parts = widget.id.replace(/&#x5B;/g,'[').replace(/]|&#x5D;/g,'').split('[');

		var type_options = [];
		var content_options = {};

		// First check type, there may be static options.  There's some special handling
		// for filterheaders, which have the wrong type.
		var type = widget.instanceOf(et2_nextmatch_filterheader) ? attrs.widget_type || '' : widget._type;
		var type_function = type.replace('select-','').replace('_ro','')+'_options';
		if(typeof this[type_function] == 'function')
		{
			var old_type = widget._type;
			widget._type = type;
			if(typeof attrs.other == 'string')
			{
				attrs.other = attrs.other.split(',');
			}
			// Copy, to avoid accidental modification
			jQuery.extend(true, type_options, this[type_function].call(this, widget, attrs));
			widget._type = old_type;
		}

		// Try to find the options inside the "sel-options"
		if(widget.getArrayMgr("sel_options"))
		{
			// Try first according to ID
			content_options = widget.getArrayMgr("sel_options").getEntry(widget.id);
			// ID can get set to an array with 0 => ' ' - not useful
			if(content_options && content_options.length == 1 && typeof content_options[0] == 'string' && content_options[0].trim() == '')
			{
				content_options = null;
			}
			// We could wind up too far inside options if label,title are defined
			if(content_options && !isNaN(name_parts[name_parts.length -1]) && content_options.label && content_options.title)
			{
				name_parts.pop();
				content_options = widget.getArrayMgr("sel_options").getEntry(name_parts.join('['));
				delete content_options["$row"];
			}

			// Select options tend to be defined once, at the top level, so try that
			if(!content_options || content_options.length == 0)
			{
				content_options = widget.getArrayMgr("sel_options").getRoot().getEntry(name_parts[name_parts.length-1]);
			}

			// Try in correct namespace (inside a grid or something)
			if(!content_options || content_options.length == 0)
			{
				content_options = widget.getArrayMgr("sel_options").getEntry(name_parts[name_parts.length-1]);
			}

			// Try name like widget[$row]
			if(name_parts.length > 1 && (!content_options || content_options.length == 0 ))
			{
				var pop_that = jQuery.extend([],name_parts);
				while(pop_that.length > 0 && (!content_options || content_options.length == 0))
				{
					var last = pop_that.pop();
					content_options = widget.getArrayMgr('sel_options').getEntry(pop_that.join('['));

					// Double check, might have found a normal parent namespace ( eg subgrid in subgrid[selectbox] )
					// with an empty entry for the selecbox.  If there were valid options here,
					// we would have found them already, and keeping this would result in the ID as an option
					if(content_options && typeof content_options[last] != 'undefined' && content_options[last] )
					{
						content_options = content_options[last];
					}
				}
			}

			// Maybe in a row, and options got stuck in ${row} instead of top level
			// not sure this code is still needed, as server-side no longer creates ${row} or {$row} for select-options
			var row_stuck = ['${row}','{$row}'];
			for(var i = 0; i < row_stuck.length && (!content_options || content_options.length == 0); i++)
			{
				// perspectiveData.row in nm, data["${row}"] in an auto-repeat grid
				if(widget.getArrayMgr("sel_options").perspectiveData.row || widget.getArrayMgr("sel_options").data[row_stuck[i]])
				{
					var row_id = widget.id.replace(/[0-9]+/,row_stuck[i]);
					content_options = widget.getArrayMgr("sel_options").getEntry(row_id);
					if(!content_options || content_options.length == 0)
					{
						content_options = widget.getArrayMgr("sel_options").getEntry(row_stuck[i] + '[' + widget.id + ']');
					}
				}
			}
			if(attr_options && !jQuery.isEmptyObject(attr_options) && content_options)
			{
				content_options = jQuery.extend(true, {},attr_options,content_options);
			}
		}

		// Check whether the options entry was found, if not read it from the
		// content array.
		if (jQuery.isEmptyObject(content_options) && widget.getArrayMgr('content') != null)
		{
			if (content_options) attr_options = content_options;
			var content_mgr = widget.getArrayMgr('content');
			if (content_mgr)
			{
				// If that didn't work, check according to ID
				if (!content_options) content_options = content_mgr.getEntry("options-" + widget.id);
				// Again, try last name part at top level
				if (!content_options) content_options = content_mgr.getRoot().getEntry("options-"+name_parts[name_parts.length-1]);
			}
		}

		// Default to an empty object
		if (content_options == null)
		{
			content_options = {};
		}
		
		// Include type options, preferring any content options
		if(type_options.length || !jQuery.isEmptyObject(type_options))
		{
			for(var i in content_options)
			{
				var value = typeof content_options[i] == 'object' && typeof content_options[i].value !== 'undefined' ? content_options[i].value : i;
				var added = false;

				// Override any existing
				for(var j in type_options)
				{
					if(type_options[j].value === value)
					{
						added = true;
						type_options[j] = content_options[i];
						break;
					}
				}
				if(!added)
				{
					type_options.splice(i,0,content_options[i]);
				}
			}
			content_options = type_options;
		}
		return content_options;
	},

	/**
	 * Some static options, no need to transfer them over and over.
	 * We still need the same thing on the server side to validate, so they
	 * have to match.  See etemplate_widget_menupopup::typeOptions()
	 * The type specific legacy options wind up in attrs.other.
	 */
	priority_options: function(widget) {
		return [
			{value: 1, label: 'low'},
			{value: 2, label: 'normal'},
			{value: 3, label: 'high'}
		];
	},
	bool_options: function(widget) {
		return [
			{value: 0, label: 'no'},
			{value: 1, label: 'yes'}
		];
	},
	month_options: function(widget) {
		return [
			{value: 1, label:'January'},
			{value: 2, label:'February'},
			{value: 3, label:'March'},
			{value: 4, label:'April'},
			{value: 5, label:'May'},
			{value: 6, label:'June'},
			{value: 7, label:'July'},
			{value: 8, label:'August'},
			{value: 9, label:'September'},
			{value: 10, label:'October'},
			{value: 11, label:'November'},
			{value: 12, label:'December'}
		];
	},
	number_options: function(widget, attrs) {
		if(typeof attrs.other != 'object')
		{
			attrs.other = [];
		}
		var options = [];
		var min = typeof(attrs.other[0]) == 'undefined' ? 1 : parseInt(attrs.other[0]);
		var max = typeof(attrs.other[1]) == 'undefined' ? 10: parseInt(attrs.other[1]);
		var interval = typeof(attrs.other[2]) == 'undefined' ? 1: parseInt(attrs.other[2]);
		var format = '%d';

		// leading zero specified in interval
		if (attrs.other[2] && attrs.other[2][0] == '0')
		{
			format = '%0'+(''+interval).length+'d';
		}
		// Suffix
		if(attrs.other[3])
		{
			format += widget.egw().lang(attrs.other[3]);
		}

		// Avoid infinite loop if interval is the wrong direction
		if ((min <= max) != (interval > 0))
		{
			interval = -interval;
		}

		for (var i=0, n=min; n <= max && i <= 100; n += interval,++i)
		{
			options.push({value: n, label: sprintf(format,n)});
		}
		return options;
	},
	percent_options: function(widget, attrs)
	{
		if(typeof attrs.other != 'object')
		{
			attrs.other = [];
		}
		attrs.other[0] = 0;
		attrs.other[1] = 100;
		attrs.other[2] = typeof(attrs.other[2]) == 'undefined' ? 10 : parseInt(attrs.other[2]);
		attrs.other[3] = '%%';
		return this.number_options(widget,attrs);
	},
	year_options: function(widget, attrs)
	{
		if(typeof attrs.other != 'object')
		{
			attrs.other = [];
		}
		var t = new Date();
		attrs.other[0] = t.getFullYear() - (typeof(attrs.other[0]) == 'undefined' ? 3 : parseInt(attrs.other[0]));
		attrs.other[1] = t.getFullYear() + (typeof(attrs.other[1]) == 'undefined' ? 2 : parseInt(attrs.other[1]));
		attrs.other[2] = typeof(attrs.other[2]) == 'undefined' ? 1 : parseInt(attrs.other[2]);
		return this.number_options(widget,attrs);
	},
	day_options: function(widget, attrs)
	{
		attrs.other = [1,31,1];
		return this.number_options(widget,attrs);
	},
	hour_options: function(widget, attrs)
	{
		var options = [];
		var timeformat = egw.preference('common','timeformat');
		for (var h = 0; h <= 23; ++h)
		{
			options.push({
				value: h,
				label: timeformat == 12 ?
					(( 12 ? h % 12 : 12)+' '+(h < 12 ? egw.lang('am') : egw.lang('pm'))) :
					sprintf('%02d',h)
			});
		}
		return options;
	},
	app_options: function(widget,attrs) {
		var options = ','+(attrs.other||[]).join(',');
		return this.cached_server_side_options(widget, options, attrs);
	},
	cat_options: function(widget, attrs) {
		// Add in application, if not there
		if(typeof attrs.other == 'undefined')
		{
			attrs.other = new Array(4);
		}
		if(typeof attrs.other[3] == 'undefined')
		{
			attrs.other[3] = attrs.application || widget.getInstanceManager().app;
		}
		var options =(attrs.other||[]).join(',');
		return this.cached_server_side_options(widget, options, attrs);
	},
	country_options: function(widget, attrs) {
		var options = ',';
		return this.cached_server_side_options(widget, options, attrs);
	},
	dow_options: function(widget,attrs) {
		var options = ','+(attrs.other||[]).join(',');
		return this.cached_server_side_options(widget, options, attrs);
	},
	lang_options: function(widget,attrs) {
		var options = ','+(attrs.other||[]).join(',');
		return this.cached_server_side_options(widget, options, attrs);
	},
	timezone_options: function(widget,attrs) {
		var options = ','+(attrs.other||[]).join(',');
		return this.cached_server_side_options(widget, options, attrs);
	},
	/**
	 * Some options change, or are too complicated to have twice, so we get the
	 * options from the server once, then keep them to use if they're needed again.
	 * We use the options string to keep the different possibilites (eg. categories
	 * for different apps) seperate.
	 *
	 * @param {et2_selectbox} widget Selectbox we're looking at
	 * @param {string} options_string
	 * @param {Object} attrs Widget attributes (not yet fully set)
	 * @returns {Object} Array of options, or empty and they'll get filled in later
	 */
	cached_server_side_options: function(widget, options_string, attrs)
	{
		// normalize options by removing trailing commas
		options_string = options_string.replace(/,+$/, '');
		
		var cache_id = widget._type+'_'+options_string;
		var cache = egw.window.et2_selectbox.type_cache[cache_id];
		if (typeof cache == 'undefined')
		{
			// Fetch with json instead of jsonq because there may be more than
			// one widget listening for the response by the time it gets back,
			// and we can't do that when it's queued.
			egw.window.et2_selectbox.type_cache[cache_id] = egw.json(
				widget.getInstanceManager().app+'.etemplate_widget_menupopup.ajax_get_options.etemplate',
				[widget._type,options_string]
			).sendRequest(true);
		}
		cache = egw.window.et2_selectbox.type_cache[cache_id];
		if(typeof cache.done == 'function')
		{
			// pending, wait for it
			cache.done(jQuery.proxy(function(response) {
				egw.window.et2_selectbox.type_cache[this.cache_id] = response.response[0].data||undefined;
				// Set select_options in attributes in case we get a resonse before
				// the widget is finished loading (otherwise it will re-set to {})
				attrs.select_options = egw.window.et2_selectbox.type_cache[this.cache_id];

				egw.window.setTimeout(jQuery.proxy(function() {
					this.widget.set_select_options(et2_selectbox.find_select_options(this.widget,{}, this.widget.options));
				},this),1);
			},{widget:widget,cache_id:cache_id}));
			return [];
		}
		else
		{
			return cache;
		}
	}
});

/**
 * et2_selectbox_ro is the readonly implementation of the selectbox.
 *
 * @augments et2_selectbox
 */
var et2_selectbox_ro = et2_selectbox.extend([et2_IDetachedDOM],
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_selectbox_ro
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.supportedWidgetClasses = [];
		this.optionValues = {};
		if(this.options.select_options) this.set_select_options(this.options.select_options);
	},

	createInputWidget: function() {
		this.span = $j(document.createElement("span"))
			.addClass("et2_selectbox readonly")
			.text(this.options.empty_label);

		this.setDOMNode(this.span[0]);
	},

	// Handle read-only multiselects in the same way
	createMultiSelect: function() {
		this.span = $j(document.createElement("ul"))
			.addClass("et2_selectbox readonly");

		this.setDOMNode(this.span[0]);
	},

	loadFromXML: function(_node) {
		// Read the option-tags
		var options = et2_directChildrenByTagName(_node, "options");
		for (var i = 0; i < options.length; i++)
		{
			this.optionValues[et2_readAttrWithDefault(options[i], "value", 0)] =
			{
				"label": options[i].textContent,
				"title": et2_readAttrWithDefault(options[i], "title", "")
			};
		}
	},

	set_select_options: function(_options) {
		for (var key in _options)
		{
			// Translate the options
			if(!this.options.no_lang)
			{
				if (typeof _options[key] === 'object' && _options[key] != null)
				{
					if(_options[key]["label"]) _options[key]["label"] = this.egw().lang(_options[key]["label"]);
					if(_options[key]["title"]) _options[key]["title"] = this.egw().lang(_options[key]["title"]);

				}
				else
				{
					_options[key] = this.egw().lang(_options[key]);
				}
			}
		}
		this.optionValues = _options;
	},

	set_value: function(_value) {
		this.value = _value;

		if(typeof _value == "string")
		{
			_value = _value.match(this._is_multiple_regexp) !== null ? _value.split(',') : [_value];
		}
		// need to handle numerical values too
		else if (typeof _value == 'number')
		{
			_value = [_value];
		}
		this.span.empty();

		if(_value)
		{
			for(var i = 0; i < _value.length; i++)
			{
				for(var o in this.optionValues)
				{
					var option = this.optionValues[o];
					var key = typeof option == 'object' && option != null && typeof option.value != 'undefined' ? option.value : o;
					if (key != _value[i]) continue;
					var label = typeof option == 'object' ? option.label : option;
					if (_value.length == 1)
					{
						this.span.text(label);
						if (typeof option == 'object' && option.title) this.set_statustext(option.title);
						break;
					}
					else
					{
						$j('<li>')
							.text(label)
							.attr('data-value', _value[i])
							.appendTo(this.span);
						break;
					}
				}
			}
		}
		else if (this.options.empty_label)
		{
			this.span.text(this.options.empty_label);
		}
	},

	/**
	 * Override parent to return null - no value, not node value
	 */
	getValue: function() {
		return null;
	},

	/**
	 * Functions for et2_IDetachedDOM
	 */
	 /**
	 * Creates a list of attributes which can be set when working in the
	 * "detached" mode. The result is stored in the _attrs array which is provided
	 * by the calling code.
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("value");
	},

	/**
	 * Returns an array of DOM nodes. The (relatively) same DOM-Nodes have to be
	 * passed to the "setDetachedAttributes" function in the same order.
	 *
	 * @return {array}
	 */
	getDetachedNodes: function() {
		return [this.span[0]];
	},

	/**
	 * Sets the given associative attribute->value array and applies the
	 * attributes to the given DOM-Node.
	 *
	 * @param _nodes is an array of nodes which have to be in the same order as
	 *      the nodes returned by "getDetachedNodes"
	 * @param _values is an associative array which contains a subset of attributes
	 *      returned by the "getDetachedAttributes" function and sets them to the
	 *      given values.
	 */
	setDetachedAttributes: function(_nodes, _values) {
		this.span = jQuery(_nodes[0]);
		this.set_value(_values["value"]);
	}
});
et2_register_widget(et2_selectbox_ro, ["menupopup_ro", "listbox_ro", "select_ro", "select-cat_ro",
	"select-percent_ro", 'select-priority_ro', 'select-access_ro',
	'select-country_ro', 'select-state_ro', 'select-year_ro', 'select-month_ro',
	'select-day_ro', 'select-dow_ro', 'select-hour_ro', 'select-number_ro', 'select-app_ro',
	'select-lang_ro', 'select-bool_ro', 'select-timezone_ro' ]);

/**
 * Widget class which represents a single option inside a selectbox
 */
/*var et2_option = et2_baseWidget.extend({

	attributes: {
		"value": {
			"name": "Value",
			"type": "string",
			"description": "Value which is sent back to the server when this entry is selected."
		},
		"label": {
			"name": "Label",
			"type": "string",
			"description": "Caption of the option element"
		},
		"width": {
			"ignore": true
		},
		"height": {
			"ignore": true
		},
		"align": {
			"ignore": true
		}
	},

	init: function() {
		this._super.apply(this, arguments);

		// Only allow other options inside of this element
		this.supportedWidgetClasses = [et2_option];

		this.option = $j(document.createElement("option"))
			.attr("value", this.options.value)
			.attr("selected", this._parent.options.value == this.options.value ?
				"selected" : "");

		if (this.options.label)
		{
			this.option.text(this.options.label);
		}

		this.setDOMNode(this.option[0]);
	},

	destroy: function() {
		this._super.apply(this, arguments);

		this.option = null;
	},

	loadContent: function(_data) {
		this.option.text(_data);
	}

/*	Doesn't work either with selectboxes
	set_statustext: function(_value) {
		this.statustext = _value;
		this.option.attr("title", _value);
	}*/

//});*/

//et2_register_widget(et2_option, ["option"]);


/**
 * Class which just implements the menulist container
 *
 * @augments et2_DOMWidget
 */
var et2_menulist = et2_DOMWidget.extend(
{
	/**
	 * Construtor
	 *
	 * @memberOf et2_menulist
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.supportedWidgetClasses = [et2_selectbox, et2_selectbox_ro];
	},

	// Just pass the parent DOM node through
	getDOMNode: function(_sender) {
		if (_sender != this)
		{
			return this._parent.getDOMNode(this);
		}

		return null;
	},

	// Also need to pass through parent's children
	getChildren: function() {
		return this._parent.getChildren();
	}

});
et2_register_widget(et2_menulist, ["menulist"]);

