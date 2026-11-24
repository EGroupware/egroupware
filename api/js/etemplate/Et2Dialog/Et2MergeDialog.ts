import {customElement} from "lit/decorators/custom-element.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {css, html, LitElement} from "lit";
import shoelace from "../Styles/shoelace";
import {Et2VfsSelectDialog} from "../Et2Vfs/Et2VfsSelectDialog";
import {property} from "lit/decorators/property.js";
import {Et2Dialog} from "./Et2Dialog";
import {state} from "lit/decorators/state.js";
import type {Et2Button} from "../Et2Button/Et2Button";

@customElement("et2-merge-dialog")
export class Et2MergeDialog extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			super.styles,
			shoelace,
			css`
				:host {
				}

				et2-details::part(content) {
					display: grid;
					grid-template-columns: repeat(3, 1fr);
					gap: var(--sl-spacing-medium);
				}
			`,
		];
	}

	@property()
	application : string

	@property()
	path : string

	/**
	 * Only one entry is being merged
	 *
	 * Merging one entry does not end in a download or a sent email: an email template is opened in
	 * compose, and a single document is opened (in Collabora, if available).
	 */
	@property({type: Boolean})
	singleEntry = false;

	// Can't merge "& send" if no email template selected
	@state()
	canEmail = false;

	// Number of selected files, a single entry merged into exactly one document gets opened
	@state()
	fileCount = 0;

	private get dialog() : Et2VfsSelectDialog
	{
		return <Et2VfsSelectDialog><unknown>this.shadowRoot.querySelector("et2-vfs-select-dialog");
	}

	/**
	 * The file dialog's primary button, which downloads the merged documents
	 */
	private get downloadButton() : Et2Button
	{
		return <Et2Button><unknown>this.dialog?.shadowRoot?.querySelector("et2-button#ok");
	}

	public async getComplete() : Promise<{
		documents : { path : string, mime : string }[],
		options : { [key : string] : string | boolean }
	}>
	{
		await this.updateComplete;
		const [button, value] = await this.dialog.getComplete();

		// Download is not available with an email template, however the dialog got confirmed
		if(!button || button == Et2Dialog.OK_BUTTON && this.canEmail)
		{
			return {documents: [], options: this.optionValues};
		}

		const documents = [];
		Array.from(<ArrayLike<string>>value).forEach(value =>
		{
			const fileInfo = this.dialog.fileInfo(value) ?? [];
			documents.push({path: value, mime: fileInfo.mime})
		});
		let options = this.optionValues;
		if(button == Et2Dialog.OK_BUTTON)
		{
			options.download = true;
		}
		return {documents: documents, options: options};
	}

	public get optionValues()
	{
		const optionValues = {
			download: false
		};
		this.dialog.querySelectorAll(":not([slot='footer'])").forEach(e =>
		{
			if(typeof e.getValue == "function")
			{
				optionValues[e.getAttribute("id")] = e.getValue() === "true" ? true : e.getValue();
			}
		});
		return optionValues;
	}

	private option(component_name)
	{
		return this.dialog.querySelector("et2-details > [id='" + component_name + "']");
	}

	protected handleFileSelect(event)
	{
		// Disable PDF checkbox for only email files selected
		let canPDF = false;
		const oldCanEmail = this.canEmail;
		this.canEmail = false;
		this.fileCount = this.dialog.value.length;

		this.dialog.value.forEach(path =>
		{
			if(this.dialog.fileInfo(path).mime !== "message/rfc822")
			{
				canPDF = true;
			}
			else
			{
				this.canEmail = true;
			}
		});
		this.option("pdf").disabled = !canPDF;

		// An email can only be merged & sent (or opened in compose), not downloaded
		const download = this.downloadButton;
		if(download)
		{
			download.disabled = this.canEmail;
			download.statustext = this.canEmail ? this.egw().lang("Email templates can not be downloaded") : "";
		}
		this.requestUpdate("canEmail", oldCanEmail);
	}


	/**
	 * The merge button, labelled with what will happen
	 */
	protected mergeButtonTemplate()
	{
		let label = "Merge";
		let image = "etemplate/merge";
		if(this.canEmail)
		{
			label = this.singleEntry ? "Merge & open" : "Merge & send";
			image = "mail";
		}
		else if(this.singleEntry && this.fileCount == 1)
		{
			label = "Merge & open";
		}
		return html`
            <et2-button slot="footer" id="send" label=${this.egw().lang(label)} image=${image}
                        noSubmit="true"></et2-button>`;
	}

	render()
	{
		return html`
            <et2-vfs-select-dialog
                    class="egw_app_merge_document"
                    path=${this.path}
                    multiple="true"
                    buttonLabel=${this.egw().lang("Download")}
                    .title="${this.egw().lang("Insert in document")}"
                    .open=${true}
                    @et2-select=${this.handleFileSelect}
            >
                ${this.mergeButtonTemplate()}
                <et2-details>
                    <span slot="summary">${this.egw().lang("Merge options")}</span>
                    <et2-checkbox label=${this.egw().lang("Save as PDF")} id="pdf"></et2-checkbox>
                    <et2-checkbox id="link"
                                  label="${this.egw().lang("Link to each entry")}"
                    ></et2-checkbox>
                    <et2-checkbox label=${this.egw().lang("Merge individually")} id="individual"></et2-checkbox>
                    <et2-checkbox label=${this.egw().lang("smime signed message")}
                                  id="smime-sign"
                                  disabled
                    ></et2-checkbox>
                    <et2-checkbox label=${this.egw().lang("smime encrypted message")}
                                  id="smime-encrypted"
                                  disabled
                    ></et2-checkbox>
                    <slot></slot>
                </et2-details>
            </et2-vfs-select-dialog>`;
	}
}