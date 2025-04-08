/**
 * EGroupware eTemplate2 - Letter Avatar widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {Et2Avatar} from "./Et2Avatar";
import shoelace from "../Styles/shoelace";
import {css} from "lit";
import {property} from "lit/decorators/property.js";
import {splitEmail} from "../Et2Email/utils";

/**
 * Avatars are used to represent a person or profile.  LAvatar has a coloured background.
 *
 * @slot icon The default icon to use when no image or initials are provided.
 *
 * @event sl-error	The image could not be loaded. This may because of an invalid URL, a temporary network condition, or some unknown cause.
 *
 * @csspart base	The component’s base wrapper.
 * @csspart icon	The container that wraps the avatar’s icon.
 * @csspart initials	The container that wraps the avatar’s initials.
 * @csspart image	The avatar image. Only shown when the image attribute is set, or when contactId has an associated avatar image
 */

export class Et2LAvatar extends Et2Avatar
{
	/**
	 * background color codes
	 */
	static LAVATAR_BG_COLORS : string[] = [
		'#5a8770', '#b2b7bb', '#6fa9ab', '#f5af29',
		'#0088b9', '#f18636', '#d93a37', '#a6b12e',
		'#0088b9', '#f18636', '#d93a37', '#a6b12e',
		'#5c9bbc', '#f5888d', '#9a89b5', '#407887',
		'#9a89b5', '#5a8770', '#d33f33', '#a2b01f',
		'#f0b126', '#0087bf', '#f18636', '#0087bf',
		'#b2b7bb', '#72acae', '#9c8ab4', '#5a8770',
		'#eeb424', '#407887'
	];

	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			css`
				[part='base'] {
					background-color: var(--background-color, var(--sl-color-neutral-400));
				}
			`
		];
	}

	@property()
	lname = "";

	@property()
	fname = "";

	/**
	 * Handle changes that have to happen based on changes to properties
	 *
	 */
	willUpdate(changedProperties)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has("lname") || changedProperties.has("fname") || changedProperties.has("contactId") || changedProperties.has("image"))
		{
			if(changedProperties.has("contactId") && this.contactId.startsWith("email:") && this.contactId.includes("<"))
			{
				// Accept ONLY the email, including the name with it changes the lavatar color
				const split = splitEmail(this.contactId.substr(6))
				if(!this.lname && !this.fname && split.name)
				{
					[this.fname, this.lname] = split.name.split(" ");
				}
				this.contactId = "email:" + split['email'];
			}
			if((!this.image || decodeURIComponent(this.image).match("lavatar=1")) || (this.fname || this.lname))
			{
				let lavatar = Et2LAvatar.lavatar(this.fname, this.lname, this.contactId);
				this.initials = lavatar.initials;
			}

			if(this.lname || this.fname)
			{
				// Update tooltip - we don't have enough info for more than name but we can put it in the right order
				let label = (this.egw().preference("account_display", "common") || "firstname").includes("first") || !this.lname || !this.fname ?
							this.fname + " " + this.lname :
							this.lname + ", " + this.fname;
				if(label != this.statustext && !egwIsMobile())
				{
					this.statustext = label.trim();
				}
			}
		}
	}


	updated(changedProperties)
	{
		super.updated(changedProperties);
		if(changedProperties.has("lname") || changedProperties.has("fname") || changedProperties.has("contactId") || changedProperties.has("image"))
		{
			let lavatar = Et2LAvatar.lavatar(this.fname, this.lname, this.contactId);
			this._baseNode?.style.setProperty("--background-color", lavatar.background);
		}
	}

	/**
	 *
	 */
	getDetachedAttributes(_attrs : string[])
	{
		super.getDetachedAttributes(_attrs);
		_attrs.push("lname", "fname");
	}

	/**
	 * Generate letter avatar with given data
	 * @param {type} _fname
	 * @param {type} _lname
	 * @param {type} _id
	 * @returns {string} return data url
	 */
	static lavatar(_fname, _lname, _id)
	{
		let str = _fname + _lname + _id;
		let getBgColor = function(_str)
		{
			let hash = 0;
			for (let i=0; i< _str.length; i++)
			{
				hash = _str[i].charCodeAt(0) + hash;
			}
			return Et2LAvatar.LAVATAR_BG_COLORS[hash % Et2LAvatar.LAVATAR_BG_COLORS.length];
		};
		let bg = getBgColor(str);
		let text = (_fname ? _fname[0].toUpperCase() : "")+(_lname ? _lname[0].toUpperCase() : "");
		return {background: bg, initials: text};
	}
}

customElements.define("et2-lavatar", Et2LAvatar);