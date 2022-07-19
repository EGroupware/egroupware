/**
 * EGroupware eTemplate2 - Email Tag WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
import {css, PropertyValues} from "@lion/core";
import shoelace from "../../Styles/shoelace";
import {Et2Tag} from "./Et2Tag";
import {cssImage} from "../../Et2Widget/Et2Widget";

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

	private static email_cache : { [address : string] : ContactInfo | false } = {};

	static get styles()
	{
		return [
			super.styles,
			shoelace, css`
			.tag {
				position: relative;
			}
			.tag__prefix {				
				width: 20px;
				height: 20px;
				flex: 0 1 auto;
				
				background-color: initial;
				background-repeat: no-repeat;
				background-size: contain;
				background-position-y: center;
				
				opacity: 30%;
				cursor: pointer;
			}
			
			.contact_plus .tag__prefix {
				opacity: 100%;
			}
			.tag__prefix.loading {
				opacity: 100%;
				background-image: ${cssImage("loading")};
			}
		
			.tag__prefix.contact_plus_add {
				height: 80%;
				background-image: ${cssImage("add")};
			}
			/* Address is for a contact - always show */
			.tag__prefix.contact_plus_contact {
				opacity: 100%;
				background-image: ${cssImage("contact")};
			}
			.tag__remove {
				order: 3;
			}
		`];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Check if the email is associated with an existing contact, and if it is not show a button to create
			 * a new contact with this email address.
			 */
			contact_plus: {
				type: Boolean,
				reflect: true,
			},

			/**
			 * If the email is a contact, we normally show the contact name instead of the email.
			 * Set to true to turn this off and always show the email
			 */
			full_email: {type: Boolean}
		}
	}

	constructor(...args : [])
	{
		super(...args);
		this.contact_plus = true;
		this.full_email = false;
		this.handleMouseEnter = this.handleMouseEnter.bind(this);
		this.handleMouseLeave = this.handleMouseLeave.bind(this);
		this.handleClick = this.handleClick.bind(this);
		this.handleContactClick = this.handleContactClick.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		if(this.contact_plus && this.egw().app('addressbook'))
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

	public checkContact(email : string) : Promise<boolean | ContactInfo>
	{
		if(typeof Et2EmailTag.email_cache[email] !== "undefined")
		{
			return Promise.resolve(Et2EmailTag.email_cache[email]);
		}
		return this.egw().jsonq('EGroupware\\Api\\Etemplate\\Widget\\Url::ajax_contact', [email]).then(
			(result) =>
			{
				Et2EmailTag.email_cache[email] = result;
				return result;
			});
	}

	handleMouseEnter(e : MouseEvent)
	{
		this.shadowRoot.querySelector(".tag").classList.add("contact_plus");
	}

	handleMouseLeave(e : MouseEvent)
	{
		this.shadowRoot.querySelector(".tag").classList.remove("contact_plus");
	}

	/**
	 * We either have a contact ID, or false.  If false, show the add button.
	 * @param {false | ContactInfo} data
	 */
	handleContactResponse(data : false | ContactInfo)
	{
		this._contactPlusNode.classList.remove("loading");
		if(data)
		{
			this._contactPlusNode.classList.add("contact_plus_contact");
			// Add name in if missing
			if(!this.full_email && data.n_fn && !this.splitEmail(this.value).name)
			{
				// Append current value as email, data may have work & home email in it
				this.textContent = data.n_fn + " <" + this.value + ">"
			}
			if(data.photo)
			{
				this._contactPlusNode.style.backgroundImage = "url(" + data.photo + ")";
			}
			this._contactPlusNode.addEventListener("click", this.handleContactClick);
			this.egw().tooltipBind(this._contactPlusNode, this.egw().lang("Open existing contact") + ":\n" + data.n_fn, false, {});
		}
		else
		{
			this._contactPlusNode.classList.add("contact_plus_add");
			this._contactPlusNode.addEventListener("click", this.handleClick);
			this.egw().tooltipBind(this._contactPlusNode, this.egw().lang("Add a new contact"), false, {});
		}
	}

	handleClick(e : MouseEvent)
	{
		e.stopPropagation();

		let extra = {
			'presets[email]': this.value
		};

		this.egw().open('', 'addressbook', 'add', extra);
	}

	handleContactClick(e : MouseEvent)
	{
		e.stopPropagation();
		this.checkContact(this.value).then((result) =>
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

	protected update(changedProperties : PropertyValues)
	{
		super.update(changedProperties);

		if(changedProperties.has("value") && this.value)
		{
			// Send the request
			this.checkContact(this.value).then((result) =>
			{
				this.handleContactResponse(result);
			});
		}
	}


	/**
	 * Check the content for name <email>.
	 * If there's a name, just show the name, otherwise show the email
	 *
	 * @param {string} new_content
	 */
	set textContent(new_content)
	{
		if(this.full_email)
		{
			super.textContent = new_content;
			return;
		}

		const split = this.splitEmail(new_content);
		super.textContent = split.name || split.email;

		// Show full email in tooltip.
		// We could do better here for known contacts
		this.egw().tooltipBind(this, new_content.trim(), false, {});
	}

	get textContent()
	{
		return super.textContent;
	}

	/**
	 * if we have a "name <email>" value split it into name & email
	 * @param email_string
	 *
	 * @return {name:string, email:string}
	 */
	public splitEmail(email_string) : { name : string, email : string }
	{
		let split = {name: "", email: email_string};
		if(email_string && email_string.indexOf('<') !== -1)
		{
			const parts = email_string.split('<');
			if(parts[0])
			{
				split.email = parts[1].substring(0, parts[1].length - 1).trim();
				split.name = parts[0].trim();
				// remove quotes
				if((split.name[0] === '"' || split.name[0] === "'") && split.name[0] === split.name.substr(-1))
				{
					split.name = split.name.substring(1, split.name.length - 1);
				}
			}
			else	// <email> --> email
			{
				split.email = parts[1].substring(0, email_string.length - 1);
			}
		}
		return split;
	}
}

interface ContactInfo
{
	id : number,
	n_fn : string,
	photo? : string
}
customElements.define("et2-email-tag", Et2EmailTag);