/**
 * EGroupware eTemplate2 - JS Dialog Widget class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 */

import {Et2Dialog} from "./Et2Dialog/Et2Dialog";
import {et2_attribute_registry, et2_register_widget, et2_widget} from "./et2_core_widget";

/**
 * Just a stub that wraps Et2Dialog
 * @deprecated
 */
export class et2_dialog extends Et2Dialog
{

	constructor(parent, attrs?)
	{
		super(parent.egw());
		if(attrs)
		{
			this.transformAttributes(attrs);
		}
		document.body.appendChild(this);
	}

	get template()
	{
		return super.template || {};
	}

	set template(value)
	{
		super.template = value;
	}

	/**
	 * @deprecated
	 * @returns {any}
	 */
	get div()
	{
		return this;
	}

	/**
	 * Create a parent to inject application specific egw object with loaded translations into et2_dialog
	 *
	 * @param {string|egw} _egw_or_appname egw object with already loaded translations or application name to load translations for
	 */
	static _create_parent(_egw_or_appname? : string | IegwAppLocal)
	{
		if(typeof _egw_or_appname == 'undefined')
		{
			// @ts-ignore
			_egw_or_appname = egw_appName;
		}
		// create a dummy parent with a correct reference to an application specific egw object
		let parent = new et2_widget();
		// if egw object is passed in because called from et2, just use it
		if(typeof _egw_or_appname != 'string')
		{
			parent.setApiInstance(_egw_or_appname);
		}
		// otherwise use given appname to create app-specific egw instance and load default translations
		else
		{
			parent.setApiInstance(egw(_egw_or_appname));
			parent.egw().langRequireApp(parent.egw().window, _egw_or_appname);
		}
		return parent;
	}
}

// Get it working transparently as a legacy dialog
et2_register_widget(et2_dialog, ["dialog", "legacy_dialog"]);
const type_map = {String: "string", Function: "js"};
let attrs = {};
for(const [key, value] of Object.entries(et2_dialog.properties))
{
	let attr = et2_dialog.properties[key];

	attrs[key] = {type: type_map[attr.type?.name || attr.name] || "string"};
}
attrs["value"] = {type: "any"};
et2_attribute_registry[et2_dialog.name] = attrs

customElements.define("legacy-dialog", et2_dialog);