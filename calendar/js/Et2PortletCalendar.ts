import shoelace from "../../api/js/etemplate/Styles/shoelace";
import {css} from "@lion/core";
import {Et2PortletFavorite} from "../../home/js/Et2PortletFavorite";

/**
 * Home portlet to show a note
 */
export class Et2PortletCalendar extends Et2PortletFavorite
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

}

if(!customElements.get("et2-portlet-calendar"))
{
	customElements.define("et2-portlet-calendar", Et2PortletCalendar);
}