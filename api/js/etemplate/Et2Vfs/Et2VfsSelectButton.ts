/**
 * EGroupware eTemplate2 - Button to open a vfs select dialog
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {html, LitElement, nothing} from "lit";
import {HasSlotController} from "../Et2Widget/slot";
import {property} from "lit/decorators/property.js";
import {Et2VfsSelectDialog, FileInfo} from "./Et2VfsSelectDialog";
import {waitForEvent} from "../Et2Widget/event";

/**
 * @summary Button to open a file selection dialog, and either return the selected path(s) as a value or take immediate
 * action with them using the `method` property.
 * @since 23.1
 *
 * @dependency et2-vfs-select-dialog
 * @dependency et2-button
 *
 * @slot footer - Buttons are added to the dialog footer.  Control their position with CSS `order` property.
 *
 * @event change - Emitted when the control's value changes.
 *
 * @csspart button - The button control
 * @csspart dialog - The et2-vfs-select-dialog
 */

export class Et2VfsSelectButton extends Et2InputWidget(LitElement)
{
	/** Icon for the button */
	@property() image : string;

	/** Currently selected files */
	@property() value : string[] | FileInfo[] = [];

	/**
	 * The dialog’s label as displayed in the header.
	 * You should always include a relevant label, as it is required for proper accessibility.
	 */
	@property() title : string = "Select";

	/**
	 * Dialog mode
	 * Quickly sets button label, multiple, selection and for "select-dir", mime-type
	 **/
	@property({type: String}) mode : "open" | "open-multiple" | "saveas" | "select-dir";

	/** Button label */
	@property({type: String}) buttonLabel : string = "Select";

	/** Provide a suggested filename for saving */
	@property() filename : string = "";

	/** Allow selecting multiple files */
	@property({type: Boolean}) multiple = false;

	/** Start path in VFS.  Leave unset to use the last used path. */
	@property() path : string = "";

	/** Limit display to the given mime-type */
	@property() mime : string | string[] | RegExp = "";

	/** Server side callback to process selected value(s) in
	 *	app.class.method or class::method format.  The first parameter will
	 *	be Method ID, the second the file list. 'download' is reserved and it
	 *	means it should use download_baseUrl instead of path in value (no method
	 * will be actually executed).
	 */
	@property() method : string = "";
	/** ID passed to method */
	@property({type: String, reflect: true, attribute: "method-id"}) methodId : string;

	protected readonly hasSlotController = new HasSlotController(this, '');
	protected processingPromise : Promise<FileActionResult> = null;

	get _dialog() : Et2VfsSelectDialog
	{
		return this.hasSlotController.test("[default]") ? <Et2VfsSelectDialog><unknown>this.querySelector("*") :
			   <Et2VfsSelectDialog><unknown>this.shadowRoot.querySelector("et2-vfs-select-dialog") ?? null
	};

	constructor()
	{
		super();
		this.handleClick = this.handleClick.bind(this);
	}

	/** Programmatically trigger the dialog */
	public click()
	{
		this.handleClick(new Event("click"));
	}

	protected handleClick(event)
	{
		if(this._dialog && typeof this._dialog.show == "function")
		{
			this._dialog.show();
			// Avoids dialog showing old value if reused
			this._dialog.requestUpdate("value");

			// This is were we bind to get informed when user closes the dialog
			waitForEvent(this._dialog, "sl-after-show").then(async() =>
			{
				this.processDialogComplete(await this._dialog.getComplete());
			});
		}
	}

	/**
	 * The select dialog has been closed, now deal with the provided paths
	 *
	 * @param {string | number} button
	 * @param {string[]} paths
	 * @protected
	 */
	protected processDialogComplete([button, paths] : [string | number, string[]])
	{
		// Cancel or close do nothing
		if(typeof button !== "undefined" && !button)
		{
			return;
		}

		const oldValue = this.value;
		this.value = paths ?? [];
		this.requestUpdate("value", oldValue);

		if(this.method && this.method == "download")
		{
			// download
			this.value.forEach(path =>
			{
				this.egw().open_link(this._dialog.fileInfo(path)?.downloadUrl, "blank", "view", 'download');
			});
		}
		else if(this.method)
		{
			this.sendFiles(button);
		}
		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new Event("change", {bubbles: true}));

			// Reset value after processing
			if(this.method)
			{
				this.value = [];
				this.requestUpdate("value");
			}
		})
	}

	protected sendFiles(button? : string | number)
	{
		// Some destinations expect only a single value when multiple=false
		let value : string[] | FileInfo[] | string = this.value;
		if(!this.multiple && this.value.length > 0)
		{
			// @ts-ignore This is the typecheck, no need to warn about it
			value = (typeof this.value[0].path != "undefined") ? this.value[0].path : this.value[0];
		}

		// Send to server
		this.processingPromise = this.egw().request(
			this.method,
			[this.methodId, value, button/*, savemode*/]
		).then((data) =>
			{
				this.processingPromise = null;

				// UI update now that we're done
				this.requestUpdate();
				return {success: true};
			}
		);

		// UI update, we're busy
		this.requestUpdate();
	}

	protected dialogTemplate()
	{
		return html`
            <et2-vfs-select-dialog
                    part="dialog"
                    .title=${this.title ?? nothing}
                    .value=${this.value ?? nothing}
                    .mode=${this.mode ?? nothing}
                    .multiple=${this.multiple ?? nothing}
                    .path=${this.path ?? nothing}
                    .filename=${this.filename ?? nothing}
                    .mime=${this.mime ?? nothing}
                    .buttonLabel=${this.buttonLabel ?? nothing}
            >
                <slot name="footer" slot="footer"></slot>
            </et2-vfs-select-dialog>
		`;
	}


	render()
	{
		const hasUserDialog = this.hasSlotController.test("[default]");
		const processing = this.processingPromise !== null;
		const image = processing ? "" : (this.image || "filemanager/navbar");

		return html`
            <et2-button part="button"
                        image=${image}
                        ?disabled=${this.disabled}
                        ?readonly=${this.readonly || processing}
                        .noSubmit=${true}
                        @click=${this.handleClick}
            >
                ${processing ? html`
                    <sl-spinner></sl-spinner>` : nothing}
            </et2-button>
            ${hasUserDialog ? html`
                <slot></slot>` : this.dialogTemplate()}
		`;
	}
}

customElements.define("et2-vfs-select", Et2VfsSelectButton);

export interface FileActionResult
{
	success : boolean,
	message? : string
}