import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {LinkAction} from "../LinkAction";

/**
 * Unit coverage for LinkAction's Add/Remove business logic (the popup itself, _openPopup(), is
 * exercised only indirectly here - these tests call the private _add()/_remove() methods
 * directly with a fake egw, since the interesting behaviour is the per-source-entry success/
 * failure aggregation, not the Et2Dialog UI). Mirrors the multi-select requirement: some
 * sources can fail (eg. permission denied) while others succeed in the very same call, and the
 * failure must be reported by title AND reason - never as a bare "All", which reads as "some
 * larger set failed" even for a single selected entry, and never as an unbounded list once
 * "select all" is involved (a nextmatch filter can easily match thousands of rows).
 */
describe("LinkAction", () =>
{
	let egwStub : any;
	let jsonqStub : sinon.SinonStub;
	let messageStub : sinon.SinonStub;
	let dataRefreshUIDsStub : sinon.SinonStub;

	beforeEach(() =>
	{
		jsonqStub = sinon.stub();
		messageStub = sinon.stub();
		dataRefreshUIDsStub = sinon.stub();
		egwStub = {
			lang: (key : string, ...args : any[]) => args.reduce((s : string, a, i) => s.replace("%" + (i + 1), String(a)), key),
			jsonq: jsonqStub,
			message: messageStub,
			dataRefreshUIDs: dataRefreshUIDsStub,
			link_title: sinon.stub().callsFake((app : string, id : string) => Promise.resolve(`${app}-${id}-title`))
		};
	});

	describe("_add", () =>
	{
		it("reports success and refreshes every source when all links succeed", async() =>
		{
			jsonqStub.resolves(true);
			const sources = [{app: "infolog", id: "1"}, {app: "infolog", id: "2"}];
			const target = {app: "addressbook", id: "9"};

			await (<any>LinkAction)._add(egwStub, sources, target);

			assert.equal(jsonqStub.callCount, 2);
			assert.deepEqual(jsonqStub.firstCall.args[1], ["infolog", "1", [{app: "addressbook", id: "9"}]]);
			assert.isTrue(messageStub.calledOnceWith("Linked", "success"));
			assert.equal(dataRefreshUIDsStub.callCount, 2);
		});

		it("reports the failed source's title AND reason when one of several fails", async() =>
		{
			jsonqStub.onFirstCall().resolves(true);
			jsonqStub.onSecondCall().rejects(new Error("Permission denied!"));
			const sources = [{app: "infolog", id: "1"}, {app: "infolog", id: "2"}];
			const target = {app: "addressbook", id: "9"};

			await (<any>LinkAction)._add(egwStub, sources, target);

			assert.isTrue(messageStub.calledOnceWith("Could not link: infolog-2-title (Permission denied!)", "error"));
			// only the successful source gets refreshed
			assert.equal(dataRefreshUIDsStub.callCount, 1);
			assert.equal(dataRefreshUIDsStub.firstCall.args[0], "infolog::1");
		});

		it("never reports bare 'All' for a single selected entry that fails - shows its title instead", async() =>
		{
			jsonqStub.rejects(new Error("Permission denied!"));
			const sources = [{app: "infolog", id: "14259"}];
			const target = {app: "infolog", id: "13826"};

			await (<any>LinkAction)._add(egwStub, sources, target);

			const shown = messageStub.firstCall.args[0];
			assert.notInclude(shown, "All");
			assert.equal(shown, "Could not link: infolog-14259-title (Permission denied!)");
		});

		it("shows the shared reason once (not repeated per title) when every source fails the same way", async() =>
		{
			jsonqStub.rejects(new Error("Permission denied!"));
			const sources = [{app: "infolog", id: "1"}, {app: "infolog", id: "2"}];
			const target = {app: "addressbook", id: "9"};

			await (<any>LinkAction)._add(egwStub, sources, target);

			assert.isTrue(messageStub.calledOnceWith(
				"Could not link: infolog-1-title, infolog-2-title (Permission denied!)", "error"));
			assert.equal(dataRefreshUIDsStub.callCount, 0);
		});

		it("annotates each title individually when failures have different reasons", async() =>
		{
			jsonqStub.onFirstCall().rejects(new Error("Permission denied!"));
			jsonqStub.onSecondCall().rejects(new Error("Some other error"));
			const sources = [{app: "infolog", id: "1"}, {app: "infolog", id: "2"}];
			const target = {app: "addressbook", id: "9"};

			await (<any>LinkAction)._add(egwStub, sources, target);

			assert.isTrue(messageStub.calledOnceWith(
				"Could not link: infolog-1-title (Permission denied!), infolog-2-title (Some other error)", "error"));
		});

		it("truncates a long failure list (eg. from Select all) instead of naming every entry", async() =>
		{
			jsonqStub.rejects(new Error("Permission denied!"));
			const sources = Array.from({length: 13}, (_, i) => ({app: "infolog", id: String(i)}));
			const target = {app: "addressbook", id: "9"};

			await (<any>LinkAction)._add(egwStub, sources, target);

			const shown = messageStub.firstCall.args[0];
			assert.include(shown, "infolog-0-title");
			assert.include(shown, "infolog-9-title");
			assert.notInclude(shown, "infolog-10-title", "only the first 10 failures are named");
			assert.include(shown, "3 more...");
			assert.include(shown, "(Permission denied!)");
		});

		it("strips a multi-line trace/detail dump from the reason, keeping only the first line", async() =>
		{
			jsonqStub.rejects(new Error("Permission denied!\n\nsome/file.php (123)\nstack trace..."));
			const sources = [{app: "infolog", id: "1"}];
			const target = {app: "addressbook", id: "9"};

			await (<any>LinkAction)._add(egwStub, sources, target);

			assert.isTrue(messageStub.calledOnceWith("Could not link: infolog-1-title (Permission denied!)", "error"));
		});
	});

	describe("_remove", () =>
	{
		it("deletes the link matching target and reports success", async() =>
		{
			jsonqStub.withArgs("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_list").resolves({
				5: {app: "addressbook", id: "9", link_id: 5}
			});
			jsonqStub.withArgs("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_delete").resolves(true);
			const sources = [{app: "infolog", id: "1"}];
			const target = {app: "addressbook", id: "9"};

			await (<any>LinkAction)._remove(egwStub, sources, target);

			assert.isTrue(jsonqStub.calledWith("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_delete", [5]));
			assert.isTrue(messageStub.calledOnceWith("Unlinked", "success"));
		});

		it("reports 'Not linked' for a source with no matching link to target, without deleting anything", async() =>
		{
			jsonqStub.withArgs("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_list").resolves({
				5: {app: "addressbook", id: "OTHER_ID", link_id: 5}
			});
			const sources = [{app: "infolog", id: "1"}];
			const target = {app: "addressbook", id: "9"};

			await (<any>LinkAction)._remove(egwStub, sources, target);

			assert.isFalse(jsonqStub.calledWith("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_delete"));
			assert.isTrue(messageStub.calledOnceWith("Could not unlink: infolog-1-title (Not linked)", "error"));
		});
	});
});
