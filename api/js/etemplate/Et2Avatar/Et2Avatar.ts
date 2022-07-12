/**
 * EGroupware eTemplate2 - Avatar widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {Et2Widget} from "../Et2Widget/Et2Widget";
import {css, SlotMixin} from "@lion/core";
import {SlAvatar} from "@shoelace-style/shoelace";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {egw} from "../../jsapi/egw_global";
import shoelace from "../Styles/shoelace";

export class Et2Avatar extends Et2Widget(SlotMixin(SlAvatar)) implements et2_IDetachedDOM
{

	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			css`
			
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * The label of the image
			 * Actually not used as label, but we put it as title
			 * Added here as there's no Lion parent
			 */
			label: {
				type: String
			},

			/**
			 * Contact id should be either user account_id {account:number} or contact_id {contact:number or number}
			 */
			contact_id:{type: String},

			/**
			 * Image
			 * Displayed image
			 */
			src: {type: String},


			shape: {
				type: String,
				reflect: true
			},

			/**
			 * Make avatar widget editable to be able to crop profile picture or upload a new photo
			 */
			editable: {type: Boolean},

			image: {
				type: String,
				reflect: true
			}
		}
	}



	constructor()
	{
		super();
		this.contact_id = "";
		this.src = "";
		this.label = "";
	}

	/**
	 * Function to set contact id
	 * contact id could be in one of these formats:
	 *		'number', will be consider as contact_id
	 *		'contact:number', similar to above
	 *		'account:number', will be consider as account id
	 * @example: contact_id = "account:4"
	 *
	 * @param {string} _contact_id contact id could be as above mentioned formats
	 */
	set contact_id(_contact_id : string)
	{
		let params = {};
		let id = 'contact_id';

		if (!_contact_id)
		{
			_contact_id = this.egw().user('account_id');
		}
		else if(_contact_id.match(/account:/))
		{
			id = 'account_id';
			_contact_id = _contact_id.replace('account:','');
		}
		else
		{
			id = 'contact_id';
			_contact_id = _contact_id.replace('contact:', '');
		}

		// if our src (incl. cache-buster) already includes the correct id, use that one
		if (!this.options.src || !this.options.src.match("(&|\\?)contact_id="+_contact_id+"(&|\\$)"))
		{
			params[id] = _contact_id;
			this.src  = egw.link('/api/avatar.php',params);
		}
	}

	set value(_value)
	{
		this.contact_id = _value;
	}

	set src(_value)
	{
		this.image = _value;
	}

	/**
	 * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
	 */
	getDetachedAttributes(_attrs : string[])
	{
		_attrs.push("contact_id", "label", "href");
	}

	getDetachedNodes()
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes, _values)
	{
		for(let attr in _values)
		{
			this[attr] = _values[attr];
		}
	}
}
customElements.define("et2-avatar", Et2Avatar as any);