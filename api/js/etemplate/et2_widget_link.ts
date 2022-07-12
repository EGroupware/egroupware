/**
 * EGroupware eTemplate2 - JS Link object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	/vendor/bower-asset/jquery-ui/jquery-ui.js;
	et2_core_inputWidget;
	et2_core_valueWidget;
	et2_widget_selectbox;

	// Include menu system for list context menu
	egw_action.egw_menu_dhtmlx;
*/

import {et2_createWidget, et2_register_widget, et2_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_inputWidget} from "./et2_core_inputWidget";
import {et2_button} from "./et2_widget_button";
import {Et2LinkList} from "./Et2Link/Et2LinkList";
import type {Et2LinkString} from "./Et2Link/Et2LinkString";
import {Et2Link} from "./Et2Link/Et2Link";
import type {Et2LinkTo} from "./Et2Link/Et2LinkTo";
import type {Et2LinkAppSelect} from "./Et2Link/Et2LinkAppSelect";
import type {Et2LinkEntry, Et2LinkEntryReadonly} from "./Et2Link/Et2LinkEntry";

/**
 * @deprecated use Et2LinkTo
 */
export type et2_link_to = Et2LinkTo;

/**
 * @deprecated use Et2LinkAppSelect
 */
export type et2_link_apps = Et2LinkAppSelect;

/**
 * @deprecated use Et2LinkEntry
 */
export type et2_link_entry = Et2LinkEntry;

/**
 * @deprecated use Et2Link
 */
export type et2_link = Et2Link;

/**
 * @deprecated use Et2LinkEntryReadonly
 */
export type et2_link_entry_ro = Et2LinkEntryReadonly;

/**
 * @deprecated use Et2LinkString
 */
export type et2_link_string = Et2LinkString;

/**
 * @deprecated use Et2LinkList
 */
// can't just define as type, as tracker/app.ts uses it with iterateOver()!
// export type et2_link_list = Et2LinkList;
export class et2_link_list extends Et2LinkList {}

/**
 *
 *
 */
export class et2_link_add extends et2_inputWidget
{
	static readonly _attributes: any = {
		"value": {
			"description": "Either an array of link information (see egw_link::link()) or array with keys to_app and to_id",
			"type": "any"
		},
		"application": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Limit to the listed application or applications (comma seperated)"
		}
	};
	private span: JQuery;
	private div: JQuery;
	private app_select: et2_link_apps;
	private button: et2_button;

	/**
	 * Constructor
	 */
	constructor(_parent: et2_widget, _attrs?: WidgetConfig, _child?: object)
	{
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_link_add._attributes, _child || {}));


		this.span = jQuery(document.createElement("span"))
			.text(this.egw().lang("Add new"))
			.addClass('et2_link_add_span');
		this.div = jQuery(document.createElement("div")).append(this.span);
		this.setDOMNode(this.div[0]);
	}

	doLoadingFinished()
	{
		super.doLoadingFinished.apply(this, arguments);
		if (this.app_select && this.button)
		{
			// Already done
			return false;
		}
		this.app_select = <et2_link_apps>et2_createWidget("link-apps", jQuery.extend({}, this.options, {
			'id': this.options.id + 'app',
			value: this.options.application ? this.options.application : this.options.value && this.options.value.add_app ? this.options.value.add_app : null,
			application_list: this.options.application ? this.options.application : null
		}), this);
		this.div.append(this.app_select);
		this.button = <et2_button>et2_createWidget("button", {
			id: this.options.id + "_add",
			label: this.egw().lang("add")
		}, this);
		this.button.set_label(this.egw().lang("add"));
		var self = this;
		this.button.click = function ()
		{
			self.egw().open(self.options.value.to_app + ":" + self.options.value.to_id, self.app_select.value, 'add');
			return false;
		};
		this.div.append(this.button.getDOMNode());

		return true;
	}

	/**
	 * Should be handled client side.
	 * Return null to avoid overwriting other link values, in case designer used the same ID for multiple widgets
	 */
	getValue()
	{
		return null;
	}
}

et2_register_widget(et2_link_add, ["link-add"]);