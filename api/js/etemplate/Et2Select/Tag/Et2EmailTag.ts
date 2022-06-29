/**
 * EGroupware eTemplate2 - Email Tag WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
import {css} from "@lion/core";
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
				display: none;
				
				height: 80%;
				
				background-color: white;
				background-repeat: no-repeat;
				background-size: contain;
			}
			
			.contact_plus .tag__prefix {
				display: block;
				order: 2;
			}
			.tag__prefix.loading {
				width: 16px;
				background-image: ${cssImage("loading")};
			}
		
			.tag__prefix.contact_plus_add, .tag__prefix.contact_plus_contact {
				width: 16px;
				background-image: ${cssImage("add")};
				cursor: pointer;
			}
			.tag__prefix.contact_plus_contact {
				background-image: ${cssImage("contact")}
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
			}
		}
	}

	constructor(...args : [])
	{
		super(...args);
		this.contact_plus = true;
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
		this._contactPlusNode.classList.add("loading");
		this._contactPlusNode.style.right = getComputedStyle(this).left;

		this.checkContact(this.value).then((result) =>
		{
			this._contactPlusNode.classList.remove("loading");
			this.handleContactResponse(result);
		})
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
		if(data)
		{
			this._contactPlusNode.classList.add("contact_plus_contact");
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
}

interface ContactInfo
{
	id : number,
	n_fn : string,
	photo? : string
}
customElements.define("et2-email-tag", Et2EmailTag);