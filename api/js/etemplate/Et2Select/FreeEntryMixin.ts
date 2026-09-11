/**
 * EGroupware eTemplate2 - free entries for select widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {LitElement} from "lit";
import {property} from "lit/decorators/property.js";
import {dedupeMixin} from "@open-wc/dedupe-mixin";
import {SlOption} from "@shoelace-style/shoelace";
import {SelectOption} from "./FindSelectOptions";

type Constructor<T = {}> = new (...args : any[]) => T;

/**
 * What a widget gains from FreeEntryMixin.
 *
 * Declared so mixins layered above it (SelectSearchMixin) can say they depend on this being
 * below them, rather than reaching for undeclared properties.
 */
export declare class FreeEntryMixinInterface
{
	/**
	 * These characters end a free entry
	 */
	static TAG_BREAK : string[];

	/**
	 * Allow values that are not in the options
	 */
	allowFreeEntries : boolean;

	/**
	 * Turn some text into an option and add it to the value.
	 * Returns false if the text was rejected.
	 */
	createFreeEntry(text : string) : boolean;

	/**
	 * Would createFreeEntry() accept this text?  Runs the widget's validators against it.
	 */
	validateFreeEntry(text : string) : boolean;
}

/**
 * "The value may contain things that are not options."
 *
 * This is not searching, though the two are usually enabled together: Et2SelectThumbnail has free
 * entries with search explicitly off, and Et2SelectTab has them with no searchUrl.  Kept separate
 * so neither has to drag the other in.
 *
 * Layer it *below* the search mixin - the search mixin reads allowFreeEntries to decide whether to
 * turn typed text into an entry on a tag break, and its select_options getter builds on this one's.
 *
 * @example
 * class Et2Select extends SelectSearchMixin(FreeEntryMixin(Et2WidgetWithSelect)) {}
 */
export const FreeEntryMixin = dedupeMixin(<T extends Constructor<LitElement>>(superclass : T) =>
{
	class Et2WidgetWithFreeEntries extends superclass
	{
		/**
		 * Allow custom entries that are not in the options
		 */
		@property({type: Boolean, reflect: true})
		allowFreeEntries = false;

		/**
		 * These characters end a free entry
		 */
		static TAG_BREAK : string[] = ["Tab", "Enter", ","];

		constructor(...args : any[])
		{
			super(...args);

			this.allowFreeEntries = false;

			this._handlePaste = this._handlePaste.bind(this);
		}

		connectedCallback()
		{
			super.connectedCallback();

			if(this.allowFreeEntries)
			{
				this.addEventListener("paste", this._handlePaste);
			}
		}

		disconnectedCallback()
		{
			super.disconnectedCallback();

			this.removeEventListener("paste", this._handlePaste);
		}

		/**
		 * Free entries the user has already added, as rendered options
		 */
		protected get freeEntries() : NodeList
		{
			return this.select?.querySelectorAll(this.optionTag + ".freeEntry") ?? [];
		}

		/**
		 * Include the free entries the user has added.
		 *
		 * A mixin layered above this one (the search mixin) adds its own options on top of these.
		 */
		get select_options() : SelectOption[]
		{
			const options = super.select_options ?? [];

			if(!this.allowFreeEntries)
			{
				return options;
			}

			this.freeEntries.forEach((item : SlOption) =>
			{
				// sl-option cannot hold a space in its value attribute, Et2Select encodes them
				if(!options.some(i => i.value == item.value.replaceAll("___", " ")))
				{
					options.push({value: item.value, label: item.textContent, class: item.classList.toString()});
				}
			})

			return options;
		}

		set select_options(options : SelectOption[])
		{
			super.select_options = options;
		}

		/**
		 * A value we have no option for has to become a free entry, or the widget would drop it.
		 */
		willUpdate(changedProperties)
		{
			super.willUpdate(changedProperties);

			if(!this.allowFreeEntries || !changedProperties.has("value") || !this.value)
			{
				return;
			}

			if(typeof this.value == "string" && !this.select_options.find(o => o.value == this.value))
			{
				this.createFreeEntry(this.value);
			}
			else if(this.multiple)
			{
				this.getValueAsArray().forEach((e) =>
				{
					if(!this.select_options.find(o => o.value == e))
					{
						this.createFreeEntry(e);
					}
				});
			}
		}

		/**
		 * Create an entry that is not in the options and add it to the value
		 *
		 * @param {string} text Used as both value and label
		 */
		public createFreeEntry(text : string) : boolean
		{
			if(!text || !this.validateFreeEntry(text))
			{
				return false;
			}
			// Trim once, up front.  The option and the value have to agree exactly: the option we
			// create carries isMatch:false, so _optionTemplate() only renders it while it is in the
			// value.  Trimming one but not the other left the user with a value that had no visible
			// option or tag behind it.
			text = text.trim();

			// Make sure not to double-add
			if(!this.querySelector("[value='" + text.replace(/'/g, "\\\'") + "']") && !this.select_options.find(o => o.value == text))
			{
				this.__select_options.push(<SelectOption>{
					value: text,
					label: text,
					class: "freeEntry",
					isMatch: false
				});
				this.requestUpdate('select_options');
			}

			// Make sure not to double-add, but wait until the option is there
			if(this.multiple && this.getValueAsArray().indexOf(text) == -1)
			{
				let value = this.getValueAsArray();
				value.push(text);
				this.value = value;
			}
			else if(!this.multiple && this.value !== text)
			{
				this.value = text;
			}
			this.dispatchEvent(new Event("change", {bubbles: true}));

			return true;
		}

		/**
		 * Check if a free entry value is acceptable.
		 * We use validators directly using the proposed value
		 *
		 * @param text
		 * @returns {boolean}
		 */
		public validateFreeEntry(text) : boolean
		{
			if(typeof text !== "string")
			{
				return false;
			}
			let validators = [...this.validators, ...this.defaultValidators];
			let result = validators.filter(v =>
				v.execute(text, v.param, {node: this}),
			);
			return validators.length > 0 && result.length == 0 || validators.length == 0;
		}

		/**
		 * Pasted text becomes one or more free entries, split on comma or tab.
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
			// Split on a comma or a tab - not on a comma *followed by* a tab
			let values = paste.split(/[,\t]/);

			values.forEach(v =>
			{
				this.createFreeEntry(v.trim());
			});
			this.dropdown?.hide();
		}
	};

	return Et2WidgetWithFreeEntries as unknown as Constructor<FreeEntryMixinInterface> & T;
});
