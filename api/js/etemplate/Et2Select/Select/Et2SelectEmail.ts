/**
 * EGroupware eTemplate2 - Email-selection WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2Select} from "../Et2Select";
import {css, html, nothing, PropertyValues} from "lit";
import {IsEmail} from "../../Validators/IsEmail";
import interact from "@interactjs/interact";
import {Validator} from "@lion/form-core";
import {classMap} from "lit/directives/class-map.js";

/**
 * Select email address(es)
 *
 * Allows free entries of valid email addresses, but also searches contacts.
 *
 * You should set multiple="true" for most cases for better UI
 * @see Et2SelectEmail
 */
export class Et2SelectEmail extends Et2Select
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				display: block;
				flex: 1 1 auto;
				min-width: 200px;
			}
			::slotted(sl-icon[slot="suffix"]) {
				display: none;
			}
			
			/* Hide selected options from the dropdown */
			::slotted([checked])
			{
				display: none;
			}
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,

			/**
			 * Allow drag and drop tags between two or more Et2SelectEmail widgets
			 */
			allowDragAndDrop: {type: Boolean},

			/**
			 * Allow placeholders like {{email}}, beside real email-addresses
			 */
			allowPlaceholder: {type: Boolean},

			/**
			 * Include mailing lists: returns them with their integer list_id
			 */
			includeLists: {type: Boolean},

			/**
			 * If the email is a contact, we normally show the contact name instead of the email.
			 * Set to true to turn this off and always show just the email
			 * Mutually exclusive with fullEmail!
			 */
			onlyEmail: {type: Boolean},

			/**
			 * Show the full, original value email address under all circumstances, rather than the contact name for known contacts
			 */
			fullEmail: {type: Boolean}
		}
	}

	constructor(...args : any[])
	{
		super(...args);

		// Additional option for select email, per ticket #79694
		this._close_on_select = this.egw().preference("select_multiple_close") != "open";

		this.search = true;
		this.searchUrl = "EGroupware\\Api\\Etemplate\\Widget\\Taglist::ajax_email";
		this.allowFreeEntries = true;
		this.allowPlaceholder = false;
		this.editModeEnabled = true;
		this.allowDragAndDrop = false;
		this.includeLists = false;
		this.multiple = false;
		this.fullEmail = false;
		this.onlyEmail = false;
		this.defaultValidators.push(new IsEmail(this.allowPlaceholder));
	}


	/** @param {import('@lion/core').PropertyValues } changedProperties */
	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has('allowPlaceholder'))
		{
			this.defaultValidators = (<Array<Validator>>this.defaultValidators).filter(v => !(v instanceof IsEmail));
			this.defaultValidators.push(new IsEmail(this.allowPlaceholder));
		}
	}

	updated(changedProperties : Map<string, any>)
	{
		super.updated(changedProperties);

		// Make tags draggable
		if(!this.readonly && this.allowFreeEntries && this.allowDragAndDrop)
		{
			this._makeDraggable();
		}
	}

	/**
	 * Make the tags draggable
	 * @protected
	 */
	protected _makeDraggable()
	{
		this.select.updateComplete.then(() =>
		{
			let dragTranslate = {x: 0, y: 0};
			const tags = Array.from(this.select.shadowRoot.querySelectorAll(".select__tags et2-email-tag"));
			let draggable = interact(tags).draggable({
				startAxis: 'xy',
				listeners: {
					start: function(e)
					{
						let dragPosition = {x: e.page.x, y: e.page.y};
						dragTranslate = {x: 0, y: 0};
						e.target.setAttribute('style', `width:${e.target.clientWidth}px !important`);
						e.target.style.position = 'fixed';
						e.target.style.zIndex = 10;
						e.target.style.transform =
							`translate(${dragPosition.x}px, ${dragPosition.y}px)`;
					},
					move: function(e)
					{
						dragTranslate.x += e.delta.x;
						dragTranslate.y += e.delta.y;
						e.target.style.transform =
							`translate(${dragTranslate.x}px, ${dragTranslate.y}px)`;
					}
				}
			});
			// set parent_node with widget context in order to make it accessible after drop
			draggable.parent_node = this;
		});
	}

	protected _bindListeners()
	{
		super._bindListeners();
		if(!this.multiple)
		{
			return;
		}
		interact(this).dropzone({
			accept: `.et2-select-draggable`,
			ondrop: function(e)
			{
				// Add in as free entry
				e.target.createFreeEntry(e.draggable.target.value);
				e.target.classList.remove('et2_dropZone');

				// remove the dragged value from its origin source
				e.draggable.parent_node.value = e.draggable.parent_node.value.filter(_item => {return e.draggable.target.value !== _item;})
			},
			ondragenter: function(e)
			{
				e.target.classList.add('et2_dropZone');
			},
			ondragleave: function(e)
			{
				e.target.classList.remove('et2_dropZone');
			}
		});
	}
	
	/**
	 * Handle keypresses inside the search input
	 * Overridden from parent to also skip the hidden selected options, which other selects do not do
	 *
	 * @param {KeyboardEvent} event
	 * @protected
	 */
	protected _handleSearchKeyDown(event : KeyboardEvent)
	{
		// Pass off some keys to select
		if(['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key))
		{
			// Strip out hidden non-matching selected so key navigation works
			this.menuItems = this.menuItems.filter(i => !i.checked);
		}
		return super._handleSearchKeyDown(event);
	}

	/**
	 * Actually query the server.
	 *
	 * Overridden to change request to match server
	 *
	 * @param {string} search
	 * @param {object} options
	 * @returns {any}
	 * @protected
	 */
	protected remoteQuery(search : string, options : object)
	{
		return this.egw().request(this.searchUrl, [search, {includeLists: this.includeLists}]).then((results) =>
		{
			// If results have a total included, pull it out.
			// It will cause errors if left in the results
			if(typeof results.total !== "undefined")
			{
				this._total_result_count += results.total;
				delete results.total;
				// Make it an array, since it was probably an object, and cleanSelectOptions() treats objects differently
				results = Object.values(results);
			}
			else
			{
				this._total_result_count += results.length;
			}
			this._total_result_count -= this.processRemoteResults(results);
		});
	}

	/**
	 * Use a custom tag for when multiple=true
	 *
	 * @returns {string}
	 */
	_tagTemplate(option, index)
	{
		const readonly = (this.readonly || option && typeof (option.disabled) != "undefined" && option.disabled);
		const isEditable = this.editModeEnabled && !readonly;

		return html`
            <et2-email-tag
                    class=${classMap({
                        ...option.classList,
                        "et2-select-draggable": !this.readonly && this.allowFreeEntries && this.allowDragAndDrop
                    })}
                    .fullEmail=${this.fullEmail}
                    .onlyEmail=${this.onlyEmail}
                    ?removable=${!readonly}
                    ?readonly=${readonly}
                    ?editable=${isEditable}
                    .value=${option.value.replaceAll("___", " ")}
            >
                ${option.getTextLabel().trim()}
            </et2-email-tag>
		`;
	}

	/**
	 * Override icon for the select option to use lavatar, same as Et2SelectAccount
	 *
	 * @param option
	 * @protected
	 */
	protected _iconTemplate(option)
	{
		// lavatar uses a size property, not a CSS variable
		let style = getComputedStyle(this);

		return html`
            <et2-lavatar slot="prefix" part="icon" .size=${style.getPropertyValue("--icon-width")}
                         lname=${option.lname || nothing}
                         fname=${option.fname || nothing}
                         image=${option.icon || nothing}
            >
            </et2-lavatar>`;
	}

	/**
	 * Overwritten to NOT split RFC822 addresses containing a comma in quoted name part
	 *
	 * E.g. '"Becker, Ralf" <rb@egroupware.org>'
	 *
	 * @param val
	 */
	set_value(val : string | string[] | number | number[])
	{
		if(typeof val === 'string' && val.indexOf(',') !== -1)
		{
			val = val.split(',');
			for(let n=0; n < val.length-1; n++)
			{
				while (val[n].indexOf('@') === -1 && n < val.length-1)
				{
					val[n] += ',' + val[n+1];
					val.splice(n+1, 1);
				}
			}
		}
		super.set_value(val);
	}


	/**
	 * Sometimes users paste multiple comma separated values at once.  Split them then handle normally.
	 * Overridden here to handle email addresses that may have commas using the regex from the validator.
	 *
	 * @param {ClipboardEvent} event
	 * @protected
	 */
	protected _handlePaste(event : ClipboardEvent)
	{
		event.preventDefault();

		let paste = event.clipboardData.getData('text');
		if(!paste)
		{
			return;
		}
		const selection = window.getSelection();
		if(selection.rangeCount)
		{
			selection.deleteFromDocument();
		}

		let preg = this.allowPlaceholder ? IsEmail.EMAIL_PLACEHOLDER_PREG : IsEmail.EMAIL_PREG;
		// Trim line start / end anchors off validation regex, make global
		let regex = new RegExp(preg.toString().substring(2, preg.toString().length - 3), 'g');
		let values = paste.match(regex);
		values.forEach(v =>
		{
			this.createFreeEntry(v.trim());
		});
		this.dropdown.hide();
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-email", Et2SelectEmail);