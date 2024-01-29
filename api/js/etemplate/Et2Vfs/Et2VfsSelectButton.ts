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
import {FileInfo} from "./Et2VfsSelectDialog";

/**
 * @summary Button to open a file selection dialog, and return the selected path(s) as a value
 * @since 23.1
 *
 * @dependency et2-vfs-select-dialog
 * @dependency et2-button
 *
 * @slot prefix - Before the toolbar
 * @slot suffix - Like prefix, but after
 *
 * @event change - Emitted when the control's value changes.
 *
 * @csspart form-control-input - The textbox's wrapper.
 * @csspart form-control-help-text - The help text's wrapper.
 * @csspart prefix - The container that wraps the prefix slot.
 * @csspart suffix - The container that wraps the suffix slot.
 *
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
	@property() method_id : string;

	protected readonly hasSlotController = new HasSlotController(this, '');

	//private _dialog : Et2VfsSelectDialog = this.shadowRoot.querySelector("et2-vfs-select-dialog") ?? null;

	constructor()
	{
		super();
		this.handleClick = this.handleClick.bind(this);
		this.handleDialogClose = this.handleDialogClose.bind(this);
	}

	protected handleClick(event)
	{
		const dialog : any = this.shadowRoot.querySelector("et2-vfs-select-dialog");
		if(dialog && typeof dialog.show == "function")
		{
			dialog.show();
		}
	}

	protected handleDialogClose(event)
	{
		debugger;
		this.value = dialog.value ?? [];

		if(this.method && this.method == "download")
		{
			// download
		}
		else if(this.method)
		{
			this.sendFiles();
		}
	}

	protected sendFiles()

	protected dialogTemplate()
	{
		return html`
            <et2-vfs-select-dialog
                    .title=${this.title ?? nothing}
                    .value=${this.value ?? nothing}
                    .mode=${this.mode ?? nothing}
                    .multiple=${this.multiple ?? nothing}
                    .path=${this.path ?? nothing}
                    .filename=${this.filename ?? nothing}
                    .mime=${this.mime ?? nothing}
                    .buttonLabel=${this.buttonLabel ?? nothing}
                    @close=${this.handleDialogClose}
            >

            </et2-vfs-select-dialog>
		`;
	}


	render()
	{
		const hasUserDialog = this.hasSlotController.test("[default]");

		return html`
            <et2-button image=${this.image || "filemanager/navbar"}
                        ?disabled=${this.disabled}
                        ?readonly=${this.readonly}
                        .noSubmit=${true}
                        @click=${this.handleClick}
            >
            </et2-button>
            <slot>${hasUserDialog ? nothing : this.dialogTemplate()}</slot>
		`;
	}
}

customElements.define("et2-vfs-select", Et2VfsSelectButton);