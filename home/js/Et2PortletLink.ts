import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";
import shoelace from "../../api/js/etemplate/Styles/shoelace";
import {css} from "@lion/core";
import {SelectOption} from "../../api/js/etemplate/Et2Select/FindSelectOptions";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";

/**
 * Home portlet to show a list of entries
 */
export class Et2PortletLink extends Et2Portlet
{
	static get styles()
	{
		return [
			...shoelace,
			...(super.styles || []),
			css`
			`
		]
	}

	/**
	 * Get a list of user-configurable properties
	 * @returns {[{name : string, type : string, select_options? : [SelectOption]}]}
	 */
	get portletProperties() : { name : string, type : string, label : string, select_options? : SelectOption[] }[]
	{
		return [
			...super.portletProperties,
			{name: "entry", type: "et2-link-entry", label: "Entry"}
		]
	}

	_process_edit(button_id, value)
	{
		if(button_id == Et2Dialog.OK_BUTTON && value.entry && value.entry != this.settings.entry)
		{
			// Update title with new app immediately
			this.title = this.egw().lang(value.entry.app);
		}
		super._process_edit(button_id, value);
	}
}

if(!customElements.get("et2-portlet-link"))
{
	customElements.define("et2-portlet-link", Et2PortletLink);
}