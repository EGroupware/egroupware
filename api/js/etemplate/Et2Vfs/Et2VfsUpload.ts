import {Et2File, FileInfo, FileInfo as UploadFileInfo} from "../Et2File/Et2File";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {FileInfo as DialogFileInfo} from "./Et2VfsSelectDialog";

export type VfsFileInfo = UploadFileInfo & DialogFileInfo;

/**
 * @summary Displays a button to select files from the user's computer to upload into the VFS
 *
 * @dependency et2-file
 *
 * @slot image - The component's image
 * @slot label - Button label
 * @slot prefix	- Used to prepend a presentational icon or similar element before the button.
 * @slot suffix - Used to append a presentational icon or similar element after the button.
 * @slot help-text - Text that describes how to use the input. Alternatively, you can use the help-text attribute.
 * @slot button - A button to use in lieu of the default button
 * @slot list - Selected files are listed here.  Place something in this slot to override the normal file list.
 *
 *
 * @csspart base - Component internal wrapper
 */
@customElement('et2-vfs-upload')
export class Et2VfsUpload extends Et2File
{
	@property({type: String}) conflict : "overwrite" | "rename" | "ask" = "ask";

	private __path = ""

	constructor()
	{
		super();
		this.uploadTarget = "EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_upload";
	}

	protected resumableQuery(file /*: ResumableFile*/, chunk /*: ResumableChunk */)
	{
		return Object.assign(super.resumableQuery(file, chunk), {
			path: this.__path
		});
	}

	/**
	 * Target VFS path.  Specifying a directory will allow multiple files.  Including the filename will rename the file.
	 * @param {string} newPath
	 */
	@property({type: String})
	set path(newPath : string)
	{
		this.__path = newPath ?? "";
		this.multiple = this.__path.endsWith("/");
	}

	get path() : string { return this.__path; }

	handleFileRemove(info : VfsFileInfo)
	{
		// Unable to delete from server.  Probably failed upload.
		if(!info.path)
		{
			return super.handleFileRemove(info);
		}
		const superFileRemove = super.handleFileRemove.bind(this);

		// Set some user feedback that something is happening
		const item = this.findFileItem(info);
		const closable = item?.closable ?? true;
		if(item)
		{
			item.hidden = false;
			item.loading = true;
			item.closable = false;
			item.requestUpdate("loading");
			item.requestUpdate("closable");
		}
		return this.confirmDelete(info).then(async([button, value]) =>
		{
			if(item)
			{
				item.loading = false;
				item.closable = closable;
				item.requestUpdate("loading");
				item.requestUpdate("closable");
			}
			if(button !== Et2Dialog.YES_BUTTON)
			{
				return;
			}
			let data = await this.egw().request("EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_remove", [
				this.getInstanceManager()?.etemplate_exec_id, 			// request_id
				this.id,												// widget_id
				info.path.replace(/&quot/g, "'")	// path
			]);
			// Remove file from widget
			if(data && data.errs == 0)
			{
				superFileRemove(info);
			}
			else if(data && data.msg)
			{
				this.egw().message(data.msg, data.errs == 0 ? 'success' : 'error');
			}
		});
	}

	resumableFileAdded(info : FileInfo, event)
	{
		const superAdded = super.resumableFileAdded.bind(this);
		// Always overwriting, no need to check
		if(this.conflict == "overwrite")
		{
			return superAdded(info, event);
		}
		// Pause uploads while we check - superAdded() will resume
		this.resumable.pause();
		const add = superAdded(info, event);
		try
		{
			this._uploadPending[info.uniqueIdentifier] = Et2Dialog.confirm_file(
				this.getInstanceManager()?.etemplate_exec_id,
				this.path,
				info.file.name,
				info.file.type,
				this.conflict == "rename",
				this.egw()
			).then(async data =>
			{
				// Cancel
				if(data == false)
				{
					info.cancel();
					return;
				}
				if(data)
				{
					// Server provided a new name
					info.fileName = data;
				}
				await add;
			}).catch(e =>
			{
				info.abort();
				let item = this.findFileItem(info);
				if(item)
				{
					item.error(e.message ?? e.toString() ?? e);
				}
			});
		}
		catch(e)
		{
			info.abort();
			let item = this.findFileItem(info);
			if(item)
			{
				item.error(e.getMessage() ?? e.toString() ?? e);
			}
		}
	}

	protected confirmDelete(info : VfsFileInfo)
	{
		const confirm = Et2Dialog.show_dialog(undefined, this.egw().lang("Delete file") + "?",
			this.egw().lang("Confirmation required"), {},
			Et2Dialog.BUTTONS_YES_NO, Et2Dialog.WARNING_MESSAGE, undefined, this.egw()
		);
		return confirm.getComplete()
	}
}