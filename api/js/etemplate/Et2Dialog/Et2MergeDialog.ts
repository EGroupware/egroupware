import {customElement} from "lit/decorators/custom-element.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {css, html, LitElement} from "lit";
import shoelace from "../Styles/shoelace";
import {Et2VfsSelectDialog} from "../Et2Vfs/Et2VfsSelectDialog";
import {property} from "lit/decorators/property.js";
import {Et2Dialog} from "./Et2Dialog";
import {state} from "lit/decorators/state.js";

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
				}
			`,
		];
	}

	@property()
	application : string

	@property()
	path : string

	// Can't merge "& send" if no email template selected
	@state()
	canEmail = false;

	private get dialog() : Et2VfsSelectDialog
	{
		return <Et2VfsSelectDialog><unknown>this.shadowRoot.querySelector("et2-vfs-select-dialog");
	}

	public async getComplete() : Promise<{
		documents : { path : string, mime : string }[],
		options : { [key : string] : string | boolean }
	}>
	{
		await this.updateComplete;
		const [button, value] = await this.dialog.getComplete();

		if(!button)
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
		this.requestUpdate("canEmail", oldCanEmail);
	}


	render()
	{
		return html`
            <et2-vfs-select-dialog
                    class=egw_app_merge_document"
                    path=${this.path}
                    multiple="true"
                    buttonLabel="Download"
                    .title="${this.egw().lang("Insert in document")}"
                    .open=${true}
                    @et2-select=${this.handleFileSelect}
            >
                ${this.canEmail ?
                  html`
                      <et2-button slot="footer" id="send" label="Merge &amp; send" image="mail"
                                  noSubmit="true"></et2-button> ` :
                  html`
                      <et2-button slot="footer" id="send" label="Merge" image="etemplate/merge"
                                  noSubmit="true"></et2-button>`
                }
                <et2-details>
                    <span slot="summary">${this.egw().lang("Merge options")}</span>
                    <et2-checkbox label="Save as PDF" id="pdf"></et2-checkbox>
                    <et2-checkbox id="link"
                                  label="${this.egw().lang("Link to each %1", <string>this.egw().link_get_registry(this.application, "entry") || this.egw().lang("entry"))}"
                    ></et2-checkbox>
                    <et2-checkbox label="Merge individually" id="individual"></et2-checkbox>
                </et2-details>
            </et2-vfs-select-dialog>`;
	}
}