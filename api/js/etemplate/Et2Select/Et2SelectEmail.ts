/**
 * EGroupware eTemplate2 - Email-selection WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2Select} from "./Et2Select";
import {css} from "@lion/core";
import {IsEmail} from "../Validators/IsEmail";
import interact from "@interactjs/interact";

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
			::part(icon), .select__icon {
				display: none;
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
			 * Allow drag and drop tags
			 */
			allowDragAndDrop: {type: Boolean}
		}
	}

	constructor(...args : any[])
	{
		super(...args);
		this.search = true;
		this.searchUrl = "EGroupware\\Api\\Etemplate\\Widget\\Taglist::ajax_email";
		this.allowFreeEntries = true;
		this.editModeEnabled = true;
		this.allowDragAndDrop = false;
		this.multiple = true;
		this.defaultValidators.push(new IsEmail());
	}

	connectedCallback()
	{
		super.connectedCallback();
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
				e.target.createFreeEntry(e.draggable.target.value);
				e.target.classList.remove('et2_toolbarDropArea');

				// remove the dragged value from its origin source
				e.draggable.parent_node.value = e.draggable.parent_node.value.filter(_item => {return e.draggable.target.value !== _item;})

				// set value for newly dropped target
				e.target.value.push(e.draggable.target.value);
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
		return this.egw().json(this.searchUrl, [search]).sendRequest().then((result) =>
		{
			this.processRemoteResults(result);
		});
	}

	/**
	 * Add in remote results
	 *
	 * Overridden to get results in a format parent expects.
	 * Current server-side gives {
	 * 	icon: "/egroupware/api/avatar.php?contact_id=5&etag=1"
	 * 	id: "ng@egroupware.org"
	 * 	label: "ng@egroupware.org"
	 * 	name: ""
	 * 	title: "ng@egroupware.org"
	 * }
	 * Parent expects value instead of id
	 *
	 * @param results
	 * @protected
	 */
	protected processRemoteResults(results)
	{
		results.forEach(r => r.value = r.id);
		super.processRemoteResults(results);
	}

	/**
	 * override tag creation in order to add DND functionality
	 * @param item
	 * @protected
	 */
	protected _createTagNode(item)
	{
		let tag = super._createTagNode(item);
		if (!this.readonly && this.allowFreeEntries && this.allowDragAndDrop)
		{
			let dragTranslate = {x:0,y:0};
			tag.class = item.classList.value + " et2-select-draggable";
			let draggable = interact(tag).draggable({
				startAxis: 'xy',
				listeners: {
					start: function(e)
					{
						let dragPosition = {x:e.page.x, y:e.page.y};
						e.target.setAttribute('style', `width:${e.target.clientWidth}px !important`);
						e.target.style.position = 'fixed';
						e.target.style.transform =
							`translate(${dragPosition.x}px, ${dragPosition.y}px)`;
					},
					move : function(e)
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
		}
		return tag;
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-email", Et2SelectEmail);