/**
 * EGroupware eTemplate2 - Email Tag WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
import {css, html, nothing, TemplateResult} from "lit";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import shoelace from "../../Styles/shoelace";
import {Et2Tag} from "./Et2Tag";
import {checkContact, ContactInfo, formatEmailAddress} from "../../Et2Email/utils";
import {until} from "lit/directives/until.js";

/**
 * Display a single email address
 * On hover, queries the server to see if the email is associated with a contact already.  If it is, we
 * show the contact's avatar, clicking it opens CRM view for that contact.  If the email is unknown, we
 * show and Add icon.  Clicking it opens the add contact dialog with the email pre-filled.
 *
 * Tag is usually used in a Et2EmailSelect with multiple=true, but there's no reason it can't go anywhere
 */
export class Et2EmailTag extends Et2Tag
{

	static get styles()
	{
		return [
			super.styles,
			shoelace, css`
			.tag {
			  position: relative;
			}

			.tag__prefix {
			  flex: 0 1 auto;

			  opacity: 30%;
			  cursor: pointer;
			}

			.tag__has_plus et2-button-icon {
			  visibility: visible;
			}

			:host(:hover) .tag__has_plus {
			  opacity: 100%;
			}

			/* Address is for a contact - always show */

			.tag__prefix.tag__has_contact {
			  opacity: 100%;
			}

			.tag__remove {
			  order: 3;
			}

			/* Shoelace disabled gives a not-allowed cursor, but we also set disabled for read-only.
			 * We don't want the not-allowed cursor, since you can always click the email address
			 */

			:host([readonly]) {
			  cursor: pointer !important;
			}

			`];
	}

	@property({type: Boolean, reflect: true})
	contactPlus = true;

	/**
	 * What to display for the selected email addresses
	 *
	 *	- full: "Mr Test User <test@example.com>
	 *	- name: "Mr Test User"
	 *	- domain: "Mr Test User (example.com)"
	 *	- email: "test@example.com"
	 *
	 * If name is unknown, we'll use the email instead.
	 */
	@property({type: String})
	emailDisplay : "full" | "email" | "name" | "domain" = "domain";

	constructor(...args : [])
	{
		super(...args);
		this.handleMouseEnter = this.handleMouseEnter.bind(this);
		this.handleMouseLeave = this.handleMouseLeave.bind(this);
		this.handleMouseClick = this.handleMouseClick.bind(this);
		this.handleContactMouseDown = this.handleContactMouseDown.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		if(this.contactPlus && this.egw().app('addressbook'))
		{
			this.addEventListener("mouseenter", this.handleMouseEnter);
			this.addEventListener("mouseleave", this.handleMouseLeave);
		}
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("mouseenter", this.handleMouseEnter);
		this.removeEventListener("mouseleave", this.handleMouseLeave);
	}

	handleMouseEnter(e : MouseEvent)
	{
		this.shadowRoot.querySelector(".tag").classList.add("contact_plus");
	}

	handleMouseLeave(e : MouseEvent)
	{
		this.shadowRoot.querySelector(".tag").classList.remove("contact_plus");
	}

	handleMouseClick(e : MouseEvent)
	{
		e.stopPropagation();

		let extra = {
			'presets[email]': this.value ?? ""
		};

		this.egw().open('', 'addressbook', 'add', extra);
	}

	handleContactMouseDown(e : MouseEvent)
	{
		e.stopPropagation();
		checkContact(this.value).then((result) =>
		{
			this.egw().open((<ContactInfo>result).id, 'addressbook', 'view', {
				title: (<ContactInfo>result).n_fn,
				icon: (<ContactInfo>result).photo
			});
		});
	}

	/**
	 * Get the node that is shown & clicked on to add email as contact
	 *
	 * @returns {Element}
	 */
	get _contactPlusNode() : HTMLElement
	{
		return this.shadowRoot.querySelector(".tag__prefix");
	}
	public _contentTemplate() : TemplateResult
	{
		const content = formatEmailAddress(this.value, this.emailDisplay);

		return html`
            <span part="content" class="tag__content" title="${this.value}">
			${until(content, this.value)}
        </span>`;
	}

	public _prefixTemplate() : TemplateResult
	{
		let classes = {
			"tag__prefix": true,
		}

		let button_or_avatar = checkContact(this.value).then((option) =>
		{
			let button_or_avatar;
			if(typeof option == "object")
			{
				// Show the lavatar for the contact
				classes['tag__has_contact'] = true;

				// lavatar uses a size property, not a CSS variable
				let style = getComputedStyle(this);
				button_or_avatar = html`
                <et2-lavatar slot="prefix" exportparts="image" part="icon" tabindex="-1"
                             @mousedown=${this.handleContactMouseDown}
                             .size=${style.getPropertyValue("--icon-width")}
                             lname=${option.lname || nothing}
                             fname=${option.fname || nothing}
                             image=${option.photo || nothing}
                             statustext="${this.egw().lang("Open existing contact") + ": " + option.n_fn}"
                >
                </et2-lavatar>`;
			}
			else
			{
				// Show a button to add as new contact
				classes['tag__has_plus'] = true;
				button_or_avatar = html`
                    <et2-button-icon image="add" tabindex="-1" @click=${this.handleMouseClick} .noSubmit=${true}
                                 label="${this.egw().lang("Add a new contact")}"
                                 statustext="${this.egw().lang("Add a new contact")}">
                </et2-button-icon>`;
			}

			return html`<span part="prefix" class=${classMap(classes)}>
				<slot name="prefix">
				</slot>
				${button_or_avatar}
			</span>`;
		});

		return html`
            ${until(button_or_avatar, html`
                <span part="prefix" class=${classMap(classes)}> 
					<slot name="prefix"></slot>
					<sl-spinner></sl-spinner>
				</span>`)}`;
	}
}

customElements.define("et2-email-tag", Et2EmailTag);