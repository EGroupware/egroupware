/**
 * EGroupware eTemplate2 - JS Placeholder widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2021
 */

/*egw:uses
	et2_core_inputWidget;
	et2_core_valueWidget;
	et2_widget_description;
*/

import {et2_valueWidget} from "./et2_core_valueWidget";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_dialog} from "./et2_widget_dialog";
import {et2_inputWidget} from "./et2_core_inputWidget";
import type {egw} from "../jsapi/egw_global";
import {et2_selectbox} from "./et2_widget_selectbox";
import {et2_description} from "./et2_widget_description";
import {et2_link_entry} from "./et2_widget_link";
import type {et2_button} from "./et2_widget_button";


/**
 * Display a dialog to choose a placeholder
 */
export class et2_placeholder_select extends et2_inputWidget
{
	static readonly _attributes : any = {
		insert_callback: {
			"name": "Insert callback",
			"description": "Method called with the selected placeholder text",
			"type": "js"
		}
	};

	static placeholders : Object | null = null;

	button : JQuery;
	submit_callback : any;
	dialog : et2_dialog;
	protected value : any;

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param _attrs
	 * @memberOf et2_vfsSelect
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_placeholder_select._attributes, _child || {}));

		// Allow no child widgets
		this.supportedWidgetClasses = [];
	}

	_content(_content, _callback)
	{
		let self = this;
		if(this.dialog && this.dialog.div)
		{
			this.dialog.div.dialog('close');
		}

		var callback = _callback || this._buildDialog;
		if(et2_placeholder_select.placeholders === null)
		{
			this.egw().loading_prompt('placeholder_select', true, '', 'body');
			this.egw().json(
				'EGroupware\\Api\\Etemplate\\Widget\\Placeholder::ajax_get_placeholders',
				[],
				function(_content)
				{
					this.egw().loading_prompt('placeholder_select', false);
					et2_placeholder_select.placeholders = _content;
					callback.apply(self, arguments);
				}.bind(this)
			).sendRequest(true);
		}
		else
		{
			this._buildDialog(et2_placeholder_select.placeholders);
		}
	}

	/**
	 * Builds file navigator dialog
	 *
	 * @param {object} _data content
	 */
	private _buildDialog(_data)
	{

		let self = this;
		let buttons = [
			{
				text: this.egw().lang("Insert"),
				id: "submit",
				image: "export"
			}
		];
		let extra_buttons_action = {};

		if(this.options.extra_buttons && this.options.method)
		{
			for(let i = 0; i < this.options.extra_buttons.length; i++)
			{
				delete (this.options.extra_buttons[i]['click']);
				buttons.push(this.options.extra_buttons[i]);
				extra_buttons_action[this.options.extra_buttons[i]['id']] = this.options.extra_buttons[i]['id'];
			}

		}
		buttons.push({text: this.egw().lang("Cancel"), id: "cancel", image: "cancel"});

		let data = {
			content: {app: '', group: '', entry: {}},
			sel_options: {app: [], group: []},
			modifications: {outer_box: {entry: {}}}
		};

		Object.keys(_data).map((key) =>
		{
			data.sel_options.app.push(
				{
					value: key,
					label: this.egw().lang(key)
				});
		});
		data.sel_options.group = this._get_group_options(Object.keys(_data)[0]);
		data.content.app = data.sel_options.app[0].value;
		data.content.group = data.sel_options.group[0].value;
		data.content.entry = data.modifications.outer_box.entry.only_app = data.content.app;
		data.modifications.outer_box.entry.application_list = Object.keys(_data);

		// callback for dialog
		this.submit_callback = function(submit_button_id, submit_value)
		{
			if((submit_button_id == 'submit' || (extra_buttons_action && extra_buttons_action[submit_button_id])) && submit_value)
			{
				this.options.insert_callback(submit_value.placeholder_list);
				return true;
			}
		}.bind(this);

		this.dialog = <et2_dialog>et2_createWidget("dialog",
			{
				callback: this.submit_callback,
				title: this.options.dialog_title || this.egw().lang("Insert Placeholder"),
				buttons: buttons,
				minWidth: 500,
				minHeight: 400,
				width: 400,
				value: data,
				template: this.egw().webserverUrl + '/api/templates/default/insert_merge_placeholder.xet?1',
				resizable: true
			}, et2_dialog._create_parent('api'));
		this.dialog.template.uniqueId = 'api.insert_merge_placeholder';


		this.dialog.div.on('load', function(e)
		{
			console.log(this);

			let app = <et2_selectbox>this.dialog.template.widgetContainer.getDOMWidgetById("app");
			let group = <et2_selectbox>this.dialog.template.widgetContainer.getDOMWidgetById("group");
			let placeholder_list = <et2_selectbox>this.dialog.template.widgetContainer.getDOMWidgetById("placeholder_list");
			let preview = <et2_description>this.dialog.template.widgetContainer.getDOMWidgetById("preview_placeholder");
			let entry = <et2_link_entry>this.dialog.template.widgetContainer.getDOMWidgetById("entry");


			placeholder_list.set_select_options(this._get_placeholders(app.get_value(), group.get_value()));

			// Further setup / styling that can't be done in etemplate
			this.dialog.template.DOMContainer.style.display = "flex";
			this.dialog.template.DOMContainer.firstChild.style.display = "flex";
			group.getDOMNode().size = 5;
			placeholder_list.getDOMNode().size = 5;

			// Bind some handlers
			app.onchange = (node, widget) =>
			{
				group.set_select_options(this._get_group_options(widget.get_value()));
				entry.set_value({app: widget.get_value()});
			}
			group.onchange = (select_node, select_widget) =>
			{
				console.log(this, arguments);
				placeholder_list.set_select_options(this._get_placeholders(app.get_value(), group.get_value()));
				preview.set_value("");
			}
			placeholder_list.onchange = this._on_placeholder_select.bind(this);
			entry.onchange = this._on_placeholder_select.bind(this);
			(<et2_button>this.dialog.template.widgetContainer.getDOMWidgetById("insert_placeholder")).onclick = () =>
			{
				this.options.insert_callback(this.dialog.template.widgetContainer.getDOMWidgetById("preview_placeholder").getDOMNode().textContent);
			};
			(<et2_button>this.dialog.template.widgetContainer.getDOMWidgetById("insert_content")).onclick = () =>
			{
				this.options.insert_callback(this.dialog.template.widgetContainer.getDOMWidgetById("preview_content").getDOMNode().textContent);
			};

			this._on_placeholder_select();
		}.bind(this));
	}

	doLoadingFinished()
	{
		this._content.call(this, null);
		return true;
	}

	_on_placeholder_select(node, widget : et2_selectbox | et2_link_entry)
	{
		let app = <et2_link_entry>this.dialog.template.widgetContainer.getDOMWidgetById("app");
		let entry = <et2_link_entry>this.dialog.template.widgetContainer.getDOMWidgetById("entry");
		let placeholder_list = <et2_selectbox>this.dialog.template.widgetContainer.getDOMWidgetById("placeholder_list");
		let preview = <et2_description>this.dialog.template.widgetContainer.getDOMWidgetById("preview_placeholder");
		let preview_content = <et2_description>this.dialog.template.widgetContainer.getDOMWidgetById("preview_content");

		// Show the selected placeholder
		preview.set_value(placeholder_list.get_value());
		preview.getDOMNode().parentNode.style.visibility = placeholder_list.get_value().trim() ? null : 'hidden';

		if(placeholder_list.get_value() && entry.get_value())
		{
			// Show the selected placeholder replaced with value from the selected entry
			this.egw().json(
				'EGroupware\\Api\\Etemplate\\Widget\\Placeholder::ajax_fill_placeholders',
				[app.get_value(), placeholder_list.get_value(), entry.get_value()],
				function(_content)
				{
					preview_content.set_value(_content);
					preview_content.getDOMNode().parentNode.style.visibility = _content.trim() ? null : 'hidden';
				}.bind(this)
			).sendRequest(true);
		}
		else
		{
			// No value, hide the row
			preview_content.getDOMNode().parentNode.style.visibility = 'hidden';
		}
	}

	_get_group_options(appname)
	{
		let options = [];
		Object.keys(et2_placeholder_select.placeholders[appname]).map((key) =>
		{
			options.push(
				{
					value: key,
					label: this.egw().lang(key)
				});
		});
		return options;
	}

	_get_placeholders(appname, group)
	{
		let options = [];
		Object.keys(et2_placeholder_select.placeholders[appname][group]).map((key) =>
		{
			options.push(
				{
					value: key,
					label: et2_placeholder_select.placeholders[appname][group][key]
				});
		});
		return options;
	}

	set_value(value)
	{
		this.value = value;
	}

	getValue()
	{
		return this.value;
	}
};
et2_register_widget(et2_placeholder_select, ["placeholder-select"]);

