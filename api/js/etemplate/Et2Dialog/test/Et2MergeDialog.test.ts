/**
 * Tests for the merge dialog's handling of email templates.
 *
 * CONTRACT
 * An email template is merged & sent, or opened in compose for a single entry - it is never
 * downloaded. While at least one selected file is an email, the dialog's download button is
 * disabled and explains why; it becomes available again once no email is selected. Should the
 * dialog get confirmed with download anyway (keyboard, double click), that counts as cancelled:
 * no documents are returned, so nothing is merged or sent by accident.
 *
 * SETUP
 * The file dialog inside can not be rendered in a test, so the methods are run on an object
 * with the dialog's prototype and a stand-in for the file dialog: its selected paths, their
 * mime types, its download button and the button it was closed with.
 *
 * PASS CRITERIA
 * The stand-in download button's disabled state / the returned documents are as described.
 */
import {assert} from "@open-wc/testing";
import {Et2MergeDialog} from "../Et2MergeDialog";
import {Et2Dialog} from "../Et2Dialog";

const MIME = {
	"/templates/Letter.eml": "message/rfc822",
	"/templates/Letter.odt": "application/vnd.oasis.opendocument.text",
	"/templates/Sheet.ods": "application/vnd.oasis.opendocument.spreadsheet"
};

/**
 * A merge dialog whose file dialog has the given files selected
 *
 * @param selected selected paths
 * @param closedWith button the file dialog gets closed with
 */
function mergeDialog(selected : string[], closedWith : number | null = null, singleEntry = false)
{
	const download = {disabled: false, statustext: ""};
	const pdf = {disabled: false};
	const fileDialog = {
		value: selected,
		fileInfo: (path : string) => ({mime: MIME[path]}),
		getComplete: () => Promise.resolve([closedWith, selected]),
		querySelector: () => pdf,
		querySelectorAll: () => [],
		shadowRoot: {querySelector: (selector : string) => selector == "et2-button#ok" ? download : null}
	};
	const dialog = Object.create(Et2MergeDialog.prototype, {
		shadowRoot: {value: {querySelector: () => fileDialog}},
		updateComplete: {value: Promise.resolve(true)},
		egw: {value: () => ({lang: (s : string) => s})},
		requestUpdate: {value: () => {}},
		canEmail: {value: false, writable: true},
		fileCount: {value: 0, writable: true},
		singleEntry: {value: singleEntry, writable: true}
	});
	return {dialog, fileDialog, download, pdf};
}

describe("Et2MergeDialog: an email template can not be downloaded", () =>
{
	it("leaves download available for documents", () =>
	{
		const {dialog, download} = mergeDialog(["/templates/Letter.odt", "/templates/Sheet.ods"]);
		dialog.handleFileSelect({});

		assert.isFalse(download.disabled);
		assert.strictEqual(download.statustext, "");
	});

	it("disables download, with an explanation, when an email is selected", () =>
	{
		const {dialog, download} = mergeDialog(["/templates/Letter.eml"]);
		dialog.handleFileSelect({});

		assert.isTrue(download.disabled);
		assert.isNotEmpty(download.statustext);
	});

	it("disables download when an email is selected together with documents", () =>
	{
		const {dialog, download} = mergeDialog(["/templates/Letter.odt", "/templates/Letter.eml"]);
		dialog.handleFileSelect({});

		assert.isTrue(download.disabled);
	});

	it("makes download available again once the email is deselected", () =>
	{
		const {dialog, fileDialog, download} = mergeDialog(["/templates/Letter.odt", "/templates/Letter.eml"]);
		dialog.handleFileSelect({});
		fileDialog.value = ["/templates/Letter.odt"];
		dialog.handleFileSelect({});

		assert.isFalse(download.disabled);
		assert.strictEqual(download.statustext, "");
	});

	it("returns the documents with download set when documents are downloaded", async() =>
	{
		const {dialog} = mergeDialog(["/templates/Letter.odt"], Et2Dialog.OK_BUTTON);
		dialog.handleFileSelect({});
		const result = await dialog.getComplete();

		assert.deepEqual(result.documents.map(d => d.path), ["/templates/Letter.odt"]);
		assert.isTrue(result.options.download);
	});

	it("counts as cancelled if confirmed with download while an email is selected", async() =>
	{
		const {dialog} = mergeDialog(["/templates/Letter.odt", "/templates/Letter.eml"], Et2Dialog.OK_BUTTON);
		dialog.handleFileSelect({});
		const result = await dialog.getComplete();

		assert.deepEqual(result.documents, []);
	});

	it("still returns the email for merge & send", async() =>
	{
		// any other button than download or cancel is the merge button
		const {dialog} = mergeDialog(["/templates/Letter.odt", "/templates/Letter.eml"], 99);
		dialog.handleFileSelect({});
		const result = await dialog.getComplete();

		assert.deepEqual(result.documents.map(d => d.path), ["/templates/Letter.odt", "/templates/Letter.eml"]);
		assert.isFalse(result.options.download);
	});
});

/**
 * The merge button says what is going to happen: one entry is opened (email in compose, a single
 * document in the editor), several entries are merged, or merged & sent for an email template.
 */
describe("Et2MergeDialog: merge button label", () =>
{
	const label = (selected : string[], singleEntry : boolean) =>
	{
		const {dialog} = mergeDialog(selected, null, singleEntry);
		dialog.handleFileSelect({});
		return dialog.mergeButtonTemplate().values[0];
	};

	it("is Merge & send for an email template and several entries", () =>
	{
		assert.strictEqual(label(["/templates/Letter.eml"], false), "Merge & send");
	});
	it("is Merge & open for an email template and one entry", () =>
	{
		assert.strictEqual(label(["/templates/Letter.eml"], true), "Merge & open");
		assert.strictEqual(label(["/templates/Letter.odt", "/templates/Letter.eml"], true), "Merge & open");
	});
	it("is Merge & open for one document and one entry", () =>
	{
		assert.strictEqual(label(["/templates/Letter.odt"], true), "Merge & open");
	});
	it("is Merge for several documents or several entries", () =>
	{
		assert.strictEqual(label(["/templates/Letter.odt", "/templates/Sheet.ods"], true), "Merge");
		assert.strictEqual(label(["/templates/Letter.odt"], false), "Merge");
	});
});
