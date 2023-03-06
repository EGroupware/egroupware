import shoelace from "../../api/js/etemplate/Styles/shoelace";
import {css, html, TemplateResult, unsafeHTML} from "@lion/core";
import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";

/**
 * Home portlet to show a note
 */
export class Et2PortletNote extends Et2Portlet
{
	static get styles()
	{
		return [
			...shoelace,
			...(super.styles || []),
			css`
			  .delete_button {
				padding-right: 10px;
			  }
			`
		]
	}

	constructor()
	{
		super();

		this.edit = this.edit.bind(this);
	}

	public edit()
	{
		// CKEditor is impossible to use below a certain size
		// Add 35px for the toolbar, 35px for the buttons
		let window_width = Math.max(580, parseInt(getComputedStyle(this).width) + 20);
		let window_height = Math.max(350, parseInt(getComputedStyle(this).height) + 70);

		// Open popup, but add 70 to the height for the toolbar
		this.egw().open_link(this.egw().link('/index.php', {
			menuaction: 'home.home_note_portlet.edit',
			id: this.id,
			height: window_height - 70
		}), 'home_' + this.id, window_width + 'x' + window_height, 'home');
	}

	bodyTemplate() : TemplateResult
	{
		return html`
            <div @dblclick=${this.edit}>
                ${unsafeHTML(this.settings?.note || "")}
            </div>
		`;
	}
}

if(!customElements.get("et2-portlet-note"))
{
	customElements.define("et2-portlet-note", Et2PortletNote);
}