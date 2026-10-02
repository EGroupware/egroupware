import {assert} from "@open-wc/testing";
import "./InfologAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way addressbook/js/test/AddressbookNoFiltersReload.test.ts does, before app.ts pulls it in
import "../../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * app.ts has to be loaded through its explicit source path - see
 * addressbook/js/test/MailVcardMessage.test.ts's docblock for why. InfologApp itself is not
 * exported - the module registers it as `app.classes.infolog` at module scope.
 */
const APP_SOURCE = '/infolog/js/app.ts';

/**
 * Coverage for InfologApp.confirm_delete(), the list's Delete action
 *
 * It collects the ids actionCallback() later sends to infolog_ui::ajax_action(), and enables
 * the delete popup's "delete including sub-entries" button if any selected entry has some.
 * It used to stop collecting ids at the first selected entry with sub-entries, so every entry
 * selected after it was silently not deleted, and it read the sub-entries from the row's
 * infolog_rowHasSubs class, which selected rows the grid never rendered do not have - their
 * senders only carry a detached placeholder row.
 *
 * The method only reads the senders, the popup's button and egw.dataGetUIDdata(), so it is
 * exercised on the prototype with a minimal `this` rather than through a constructed app.
 */
describe('InfologApp.confirm_delete()', () =>
{
	let InfologApp : any;
	let button : HTMLButtonElement;

	before(async function()
	{
		this.timeout(15000);
		await import(APP_SOURCE);
		InfologApp = (<any>window).app.classes.infolog;
	});

	beforeEach(() =>
	{
		button = document.createElement("button");
		button.id = "delete_sub";
		document.body.append(button);
	});

	afterEach(() =>
	{
		button.remove();
	});

	/**
	 * A sender as EgwAction.execute() passes it, with a detached row like the ones
	 * Et2NextmatchActionController.actionObjectsForRows() makes for rows never rendered
	 */
	function sender(id : number, rowClass = "")
	{
		const row = document.createElement("tr");
		row.className = rowClass;
		return {id: "infolog::" + id, iface: {getDOMNode: () => row}};
	}

	function confirmDelete(senders : any[], rowData : { [uid : string] : any } = {})
	{
		const context : any = {
			egw: {dataGetUIDdata: (uid : string) => rowData[uid] ? {data: rowData[uid]} : null},
			_open_action_popup: () => {},
			_row_has_subs: InfologApp.prototype._row_has_subs
		};
		InfologApp.prototype.confirm_delete.call(context,
			{parent: {data: {nextmatch: {getSelection: () => ({all: false})}}}}, senders);
		return context;
	}

	it('collects every selected id, also the ones after an entry with sub-entries', () =>
	{
		const context = confirmDelete([sender(1), sender(2), sender(3)], {
			"infolog::1": {info_anz_subs: 0},
			"infolog::2": {info_anz_subs: 1},
			"infolog::3": {info_anz_subs: 0}
		});
		assert.deepEqual(context._action_ids, ["1", "2", "3"]);
		assert.isFalse(button.disabled);
	});

	it('collects the ids without the delete including sub-entries button', () =>
	{
		button.remove();
		const context = confirmDelete([sender(1), sender(2)]);
		assert.deepEqual(context._action_ids, ["1", "2"]);
	});

	it('finds sub-entries of a row never rendered from its row data', () =>
	{
		confirmDelete([sender(1), sender(2)], {
			"infolog::1": {info_anz_subs: 0},
			"infolog::2": {info_anz_subs: "2"}
		});
		assert.isFalse(button.disabled);
	});

	it('disables delete including sub-entries if no selected entry has any', () =>
	{
		confirmDelete([sender(1), sender(2)], {
			"infolog::1": {info_anz_subs: 0},
			"infolog::2": {info_anz_subs: 0}
		});
		assert.isTrue(button.disabled);
	});

	it('falls back to the row class without row data', () =>
	{
		confirmDelete([sender(1), sender(2, "infolog_rowHasSubs")]);
		assert.isFalse(button.disabled);
	});
});
