/**
 * EGroupware  eTemplate2 widget browser
 * View & play with et2 widgets - javascript
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @author Hadi Nategh
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

	// Build DTD
	this._init_dtd();
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

	// Sort the registry
	var types = [];
	for(var type in et2_registry)
	{
		types.push(type);
		}
	types.sort();
	for(var i = 0; i < types.length; i++)
	{
		list.append('<li>'+types[i]+'</li>');
		this.dump_attributes(types[i]);
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

widget_browser.prototype.dump_attributes = function(_type)
{
	console.log(_type);

	try {
		var attrs = {};
		window.wb_widget = this.widget = et2_createWidget(_type, attrs, this.et2.widgetContainer);
		this.widget.loadingFinished();

		if(this.widget !== null && this.widget.attributes)
		{
			for(var attr in this.widget.attributes)
			{
				console.log(attr, this.widget.attributes[attr]);
			}
		}
	}
	catch(e) {
		console.log('*** '+_type+' error '+(typeof e.message != 'undefined' ? e.message : e));
	}
	try {
		if (this.widget)
		{
			this.widget.destroy();
			delete this.widget;
		}
	}
	catch(e) {

	}
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

/**
 * Initialise the DTD generator
 */
widget_browser.prototype._init_dtd = function ()
{
	//Contains all widgets
	this.widgets = [];

	//Contains not readonly widgets
	this.dtd_widgets = [];

	//Contians readonly widgets
	this.dtd_widgets_ro = [];

	// Contains the whole DTD string
	this.dtd = "";

	var self = this;

	// Create DTD Generator button and bind click handler on it
	var dtd_btn = $j(document.createElement('button'))
			.attr({id:'dtd_btn', title:'Generates Document Type Definition (DTD) for all widgets'})
			.click(function(){
				self._dtd_builder();
			})
			.addClass('dtd_btn')
			.appendTo('body');
	dtd_btn.text('DTD Generator');
}

/**
 * Iterates over all et2_widget to build DTD tags
 * and display them as string
 *
 */
widget_browser.prototype._dtd_builder = function()
{
	var dtdContentW = "";
	var i = 0;
	for (var widget_type in et2_registry)
	{
		var attrs = {};

		// creating a dialog popups an empty dialog,
		// which we don't want therefore
		// we eliminate dialog tag from dtd ATM.
		if (widget_type.match(/dialog/,'i')) continue;

		if (!widget_type.match(/nextmatch/,'i'))
		{

			this.widgets[i] = et2_createWidget(widget_type ,attrs, this.et2.widgetContainer)
			if (widget_type.match(/_ro/,'i'))
			{
				this.dtd_widgets_ro.push( widget_type.replace('_ro',''));
			}
			else
			{
				this.dtd_widgets.push(widget_type);
				dtdContentW += this._dtd_widgets(widget_type, this.widgets[i])
			}
			i++;
		}
	}
	// DTD Final Content
	this.dtd = this._dtd_header() + dtdContentW;

	//Display DTD resault and UI to copy/download them
	et2_createWidget("dialog", {
			callback: function() {},
			title: egw.lang('DTD Result'),
			buttons:et2_dialog.BUTTONS_OK,
			value: {
				content: {
					value: this.dtd,
					message: egw.lang('DTD Content')
				}
			},
			template: egw.webserverUrl+'/etemplate/templates/default/dtd.xet',
			modal:true,
			resizable:false
		});
}

/**
 * Builds some specific header DTD tags (e.g. ENTITY)
 *
 * @returns {String} returns dtd header tags as string
 */
widget_browser.prototype._dtd_header = function ()
{
	var dtd = '';
	dtd = '<!ENTITY % Widgets "' + this.dtd_widgets.join('|') + '">\r\n';
	dtd += '<!ELEMENT overlay (%Widgets;)*>\r\n';
	return dtd;
}

/**
 * Builds DTD ELEMENTS and teir ATTRLIST for given widget
 *
 * @param {string} _type widget type
 * @param {object} _widget widget object
 * @returns {String} returns generated dtd tags in string
 */
widget_browser.prototype._dtd_widgets = function (_type, _widget)
{
	var dtd = '';
	switch (_type)
	{
		// Special handling for menulist widget as it has a complicated structure
		case 'menulist':
			dtd = '<!ELEMENT menulist (menupopup)>\r\n';
			break;

		// Special handling for grid widget as it has a complicated structure
		case 'grid':
			dtd += '<!ELEMENT grid (columns,rows)>\r\n';
			dtd += '<!ELEMENT columns (column)*>\r\n\
					<!ELEMENT column EMPTY >\n\
					<!ATTLIST column\n\
						disabled CDATA #IMPLIED\n\
						class CDATA #IMPLIED\n\
						width CDATA #IMPLIED><!ELEMENT rows (row)*>\n\
					<!ELEMENT row (%Widgets;)>\n\
					<!ATTLIST row\n\
						class CDATA #IMPLIED\n\
						height CDATA #IMPLIED\n\
						valign CDATA #IMPLIED\n\
						disabled CDATA #IMPLIED\n\
					>\r\n';
			break;

		// Special handling for tabbox widget as it has a complicated structure
		case 'tabbox':
			dtd += '<!ELEMENT tabbox (tabs,tabpanels)>\r\n';
			dtd += '<!ELEMENT tabs (tab)>\r\n';
			dtd += '<!ELEMENT tabpanels (template)>\r\n';
			break;

		// Widget which can be a parent
		case 'vbox':
		case 'hbox':
		case 'box':
		case 'groupbox':
		case 'template':
			dtd = '<!ELEMENT '+ _type + ' (%Widgets;)*>\r\n';
			break;

		// Other widgets which only can be used as child
		default:
			dtd = '<!ELEMENT '+ _type + ' EMPTY>\r\n';
	}

	dtd +='<!ATTLIST ' + _type + '\r\n';
	for(var attr in _widget.attributes)
	{
		//DTD attribute helper object
		var dtdAttrObj = {attrType:'CDATA',attrVal:'', attrReq:' #IMPLIED'};

		//if(_widget.attributes[attr].ignore) continue;
		switch (_widget.attributes[attr].type)
		{
			case 'boolean':
				dtdAttrObj.attrType = ' (true|false)';
				dtdAttrObj.attrVal = ' "' + _widget.attributes[attr].default + '"';
				dtdAttrObj.attrReq = '';
				break;
			default:
				dtdAttrObj.attrType = ' CDATA';

				if (_widget.attributes[attr].default !="" &&
						typeof _widget.attributes[attr].default != 'undefined' &&
						!jQuery.isEmptyObject(_widget.attributes[attr].default))
				{
					dtdAttrObj.attrReq = '';
					dtdAttrObj.attrVal  = ' "' + _widget.attributes[attr].default + '"';
				}
				else
				{
					dtdAttrObj.attrVal  = "";
				}

		}
		dtd += attr + dtdAttrObj.attrType +
				(dtdAttrObj.attrVal !=""?dtdAttrObj.attrVal:'') +
				dtdAttrObj.attrReq +'\r\n';
	}
	dtd += '>\r\n';
	return dtd;
}

egw_LAB.wait(function() {
	var wb = new widget_browser(
	document.getElementById("widget_list"),
	document.getElementById("widget_container")
	);
});
