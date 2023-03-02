import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";

export class Et2PortletFavorite extends Et2Portlet
{
}

if(!customElements.get("et2-portlet-favorite"))
{
	customElements.define("et2-portlet-favorite", Et2PortletFavorite);
}