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
		// If we're not just overwriting, check
		if(this.conflict == "overwrite")
		{
			return superAdded(info, event);
		}
		this.egw().request("EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_conflict_check", [
			this.getInstanceManager()?.etemplate_exec_id, 			// request_id
			this.path,	// path
			info.file.name,
			info.file.type
		]).then(async(data) =>
		{
			if(data && data.errs != 0)
			{
				info.cancel();
			}
			if(data && (data.exists && this.conflict == "rename" || !data.exists) && data.filename)
			{
				// Server provided a new name, no user interaction needed
				info.fileName = data.filename;
			}
			else if(data && data.exists && this.conflict == "ask")
			{
				const upload = await this.confirmConflict(info, data.filename ?? info.fileName);
				if(!upload)
				{
					info.cancel();
					return;
				}
			}
			await superAdded(info, event);
			if(data.errs != 0 && data.msg)
			{
				let item = this.findFileItem(info);
				if(item)
				{
					item.error(data.msg);
				}
			}
		});
	}

	protected async confirmConflict(info : FileInfo, suggestedName : string)
	{
		const buttons = [
			{
				label: this.egw().lang("Overwrite"),
				id: "overwrite",
				class: "ui-priority-primary",
				"default": true,
				image: 'check'
			},
			{label: this.egw().lang("Rename"), id: "rename", image: 'edit'},
			{label: this.egw().lang("Cancel"), id: "cancel", image: "cancel"}
		];
		let button_id, value;
		if(this.path.endsWith("/"))
		{
			// Filename is up to user, let them rename
			[button_id, value] = <[string, Object]><unknown>await Et2Dialog.show_prompt(undefined,
				this.egw().lang('Do you want to overwrite existing file %1 in directory %2?', info.fileName, this.path),
				this.egw().lang('File %1 already exists', info.fileName),
				suggestedName ?? info.fileName, buttons, this.egw()
			).getComplete();
		}
		else
		{
			// Filename is set, only ask to overwrite
			buttons.splice(1, 1);
			info.fileName = suggestedName ?? info.fileName;
			[button_id, value] = <[string, Object]><unknown>await Et2Dialog.show_dialog(undefined,
				this.egw().lang('Do you want to overwrite existing file %1 in directory %2?', info.fileName, this.label ?? this.title ?? this.path),
				this.egw().lang('File %1 already exists', info.fileName),
				undefined, buttons, Et2Dialog.QUESTION_MESSAGE, "", this.egw()
			).getComplete();
		}
		switch(button_id)
		{
			case "overwrite":
				// Upload as set
				return true;
			case "rename":
				info.fileName = value?.value ?? info.fileName;
				return true;
			case "cancel":
				// Don't upload
				return false;
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