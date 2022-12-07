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
 *
 * Replace calls like:
 * ```ts
 * this.dialog = <et2_dialog>et2_createWidget("dialog",
	{
		callback: this.submit_callback,
		title: this.egw().lang(this.dialog_title),
		buttons: buttons,
		minWidth: 500,
		minHeight: 400,
		width: 400,
		value: data,
		template: this.egw().webserverUrl + this.TEMPLATE,
		resizable: true
	}, et2_dialog._create_parent('api'));
 * ```
 *
 * with this:
 * ```ts
 * this.dialog = new Et2Dialog(this.egw());
 * this.dialog.transformAttributes({
		callback: this.submit_callback,
		title: this.dialog_title,
		buttons: buttons,
		width: 400,
		value: data,
		template: this.egw().webserverUrl + this.TEMPLATE
	});
 document.body.appendChild(this.dialog);
 * ```
 * Dialog size now comes from contents, so it's better to leave width & height unset.
 * Set minimum dimensions in CSS.
 * Title & message are translated by Et2Dialog
 * @deprecated
 */
export class et2_dialog extends Et2Dialog
{

	constructor(parent?, attrs?)
	{
		super(parent?.egw() || egw);
		if(attrs.hasOwnProperty("modal"))
		{
			// modal is an internal property of SlDialog
			console.warn("modal is an internal property, use isModal instead");
			attrs.isModal = attrs.modal;
			delete attrs.modal;
		}
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

	_getButtons() : any
	{
		if(Array.isArray(this.buttons) && this.buttons[0].text)
		{
			console.warn("Button definitions should follow DialogButton interface", this, this.buttons);
			return this.buttons.map((button) =>
			{
				if(button.text)
				{
					button.label = button.text;
				}
				return button;
			});
		}
		return super._getButtons();
	}

	handleOpen()
	{
		super.handleOpen();

		// move the overlay dialog into appendTo dom since we want it to be shown in that container
		if(this.appendTo)
		{
			document.getElementsByClassName(this.appendTo.replace('.', ''))[0].appendChild(this);
		}
	}

	handleClose(ev : PointerEvent)
	{
		// revert the moved container back to its original position in order to be able to teardown the overlay properly
		if(this.appendTo)
		{
			document.getElementsByClassName('global-overlays__overlay-container')[0].appendChild(this);
		}
		super.handleClose(ev);
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

	attrs[key] = {type: type_map[attr.type?.name || attr.name] || "any"};
}
attrs["value"] = {type: "any"};
et2_attribute_registry[et2_dialog.name] = attrs

customElements.define("legacy-dialog", et2_dialog);