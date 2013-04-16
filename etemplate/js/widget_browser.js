/**
 * EGroupware  eTemplate2 widget browser
 * View & play with et2 widgets - javascript
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2013 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage tools
 * @version $Id$
 */


/**
 * widget_browser shows a list of widgets, and allows you to view them one at a time.
 * You can view and edit defined properties to see the effect.
 */
function widget_browser(list_div, widget_div)
{

	// Initialize etemplate2
	this.et2 = new etemplate2(widget_div, "etemplate::ajax_process_content");

	// Normally this would be passed from app
	var _data = {};

	// Create the basic widget container and attach it to the DOM
	// Not really needed, but let's be consitent with et2
        this.et2.widgetContainer = new et2_container(null);
        this.et2.widgetContainer.setApiInstance(egw('etemplate', egw.elemWindow(this.et2.DOMContainer)));
        this.et2.widgetContainer.setInstanceManager(this.et2);
        this.et2.widgetContainer.setParentDOMNode(this.et2.DOMContainer);
	this.et2.widgetContainer.setArrayMgrs(this.et2._createArrayManagers(_data));

	// Set up UI
	this.list_div = $j(list_div);
	this.widget_div = $j(widget_div);
	this.attribute_list = null;

	// Create and popuplate the widget list
	this._init_list();
	
}

/**
 * Read the widget registry and create a list.
 * The user can pick a widget, and we'll instanciate it.
 */
widget_browser.prototype._init_list = function()
{
	var self = this;

	// Create list
	var list = $j(document.createElement('ul'))
		.attr('id', 'widgets')
		.click(function(e) {self.select_widget(e);})
		.appendTo(this.list_div);
	for(var type in et2_registry)
	{
		var class_name = et2_registry[type];
		list.append('<li>'+type+'</li>');
	}

	// Build attribute table
	attribute_table = $j(document.createElement('table'));
	attribute_table.append('<thead class = "ui-widget-header"><td>'+egw().lang('Name')+"</td><td>"+egw().lang("Data type") +
		"</td><td>"+egw().lang("Value") + "</td></thead>");
	this.attribute_list = $j(document.createElement('tbody'))
		.appendTo(attribute_table);

	this.list_div.append(
		$j(document.createElement('div'))
			.attr('id', 'widget_attributes')
			.append(attribute_table)
	);
};

/**
 * User selected a widget from the list
 * 
 * Create an instance of the widget, get its attributes, and display it.
 */
widget_browser.prototype.select_widget = function(e,f)
{
	// UI prettyness - clear selected
	$j(e.target).parent().children().removeClass("ui-state-active");

	// Clear previous widget
	if(this.widget)
	{
		this.et2.widgetContainer.removeChild(this.widget);
		this.widget.free();
		this.widget = null;
	}

	// Get the type of widget
	var type = $j(e.target).text();
	if(!type || e.target.nodeName != 'LI')
	{
		return;
	}

	// UI prettyness - show item as selected
	$j(e.target).addClass('ui-state-active');

	// Widget attributes
	var attrs = {};


	window.wb_widget = this.widget = et2_createWidget(type, attrs, this.et2.widgetContainer);
	this.widget.loadingFinished();

	// Attribute list
	this.attribute_list.empty();
	if(this.widget !== null && this.widget.attributes)
	{
		for(var attr in this.widget.attributes)
		{
			if(this.widget.attributes[attr].ignore) continue;
			this.create_attribute(attr, this.widget.attributes[attr])
				.appendTo(this.attribute_list);
		}
	}
};


/**
 * Create the UI (DOM) elements for a single widget attribute
 *
 * @param name Name of the attribute
 * @param settings attribute attributes (Human name, description, etc)
 */
widget_browser.prototype.create_attribute = function(name, settings)
{
	var set_function_name = "set_"+name;
	var row = $j(document.createElement("tr"))
		.addClass(typeof this.widget[set_function_name] == 'function' ? 'ui-state-default':'ui-state-disabled')
		// Human Name
		.append($j(document.createElement('td'))
			.text(settings.name)
		)
		// Data type
		.append($j(document.createElement('td'))
			.text(settings.type)
		);
	// Add attribute name & description as a tooltip
	if(settings.description)
	{
		egw().tooltipBind(row,settings.description);
	}

	// Value
	var value = $j(document.createElement('td')).appendTo(row);
	if(row.hasClass('ui-state-disabled'))
	{
		// No setter - just use text
		value.text(this.widget.options[name]);
		return row;
	}

	// Setter function - maybe editable?
	var self = this;
	var input = null;
	switch(settings.type)
	{
		case 'string':
			input = $j('<input/>')
				.change(function(e) {
					self.widget[set_function_name].apply(self.widget, [$j(e.target).val()]);
				});
			input.val(this.widget.options[name]);
			break;
		case 'boolean':
			input = $j('<input type="checkbox"/>')
				.attr("checked", this.widget.options[name])
				.change(function(e) {
					self.widget[set_function_name].apply(self.widget, [e.target.checked]);
				});
			break;
		default:
			value.text(this.widget.options[name]);
			return row;
	}
	input.appendTo(value);

	return row;
};
