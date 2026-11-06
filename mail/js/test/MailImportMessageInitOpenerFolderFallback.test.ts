import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * Ticket #127461 ("cannot import new mail via jmap", Milan): when the opener's own folder tree
 * had failed to load (eg. because its JMAP client's session fetch had timed out - see
 * MailJmapRecoversBrokenSessionClient.test.ts for that half of the same ticket), its
 * getActiveFilters().selectedFolder was left as a bare profileID like "2", with no "::path" at
 * all. importMessageInit() used to split that into [profileID, undefined] and return immediately,
 * BEFORE ever setting the FOLDER widget's select_options - an et2-select with no options at all
 * drops even its own server-rendered default value, so the import then failed with
 * "Destination folder not set".
 */

function createFolderWidget()
{
	const widget : any = {select_options : [], value : ''};
	widget.getValue = () => widget.value;
	widget.set_value = (v : string) => { widget.value = v; };
	return widget;
}

function createMailApp(folderWidget : any, getAllMailboxes : (profileID : string) => Promise<any>)
{
	const app = Object.create(MailApp.prototype) as MailApp;
	Object.assign(app, {
		et2 : {getWidgetById : (id : string) => id === 'FOLDER' ? folderWidget : null},
	});
	// MailApp.jmap is a getter with no setter - replace it outright rather than assigning through it
	Object.defineProperty(app, 'jmap', {configurable : true, value : {getAllMailboxes}});
	return app;
}

function setOpener(selectedFolder : string)
{
	Object.defineProperty(window, 'opener', {
		configurable : true,
		value : {closed : false, app : {mail : {getActiveFilters : () => ({selectedFolder})}}},
	});
}

describe("MailApp.importMessageInit() - opener's selectedFolder without a '::path'", () =>
{
	const originalOpener = window.opener;
	afterEach(() => Object.defineProperty(window, 'opener', {configurable : true, value : originalOpener}));

	it("falls back to the widget's own default instead of leaving FOLDER with no options at all", async() =>
	{
		const folderWidget = createFolderWidget();
		folderWidget.value = '2::Drafts';	// the server's own default, already on the widget
		setOpener('2');	// opener's tree never loaded - no "::path" at all
		const app = createMailApp(folderWidget, async() => null);

		await (app as any).importMessageInit();

		assert.deepEqual(folderWidget.select_options, [{value : '2::Drafts', label : 'Drafts'}],
			"FOLDER must keep a usable option instead of being left empty");
		assert.equal(folderWidget.getValue(), '2::Drafts');
	});

	it("still honors a valid opener selectedFolder (one that does have a path)", async() =>
	{
		const folderWidget = createFolderWidget();
		folderWidget.value = '2::Drafts';
		setOpener('3::INBOX/Sub');
		const app = createMailApp(folderWidget, async() => null);

		await (app as any).importMessageInit();

		assert.equal(folderWidget.getValue(), '3::INBOX/Sub', "a well-formed opener value must still win, as before this fix");
	});
});
