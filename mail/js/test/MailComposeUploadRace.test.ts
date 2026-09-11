import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import type {MailApp} from "../app";

/**
 * Regression coverage for the classic (non-JMAP) attachment upload race found live 2026-09-03
 * ("attachments are not sent if the email is not saved as draft before sending"): a newly-picked
 * attachment only lands in content.attachments once uploadFinish()'s own postback comes back -
 * clicking Send/Save-as-Draft before that postback lands took a snapshot of content.attachments
 * without the new file, sending/saving successfully but silently missing the attachment, with no
 * error shown anywhere. "Save as draft first" only appeared to fix it because it gave that
 * postback time to land. The fix: pendingAttachmentUploads (incremented per file in uploadStart()'s
 * classic branch, reset in uploadFinish()) gates submitAction()/saveAsDraft() via
 * waitForPendingUploads(), same "wait for a promise before submitting" pattern bootstrapPromise
 * (see MailComposeBootstrapRace.test.ts) already uses for the sibling bootstrap race.
 */

const egw : any = {
	lang: (label : string, ...args : string[]) =>
	{
		let i = 0;
		return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
	},
	preference: (_key : string, _app? : string) => null,
	message: (_msg : string, _type? : string) => {},
	loading_prompt: (_id : string, _show : boolean, _msg? : string) => {},
};

function createFakeApp() : MailApp
{
	return {egw} as unknown as MailApp;
}

/** Bare-minimum et2 fake: no widgets are needed since integrateSubmit()/trySaveDraftViaJmap() are stubbed per-test. */
function createFakeEt2(submitSpy : () => void)
{
	return {
		getWidgetById: (_id : string) => null,
		getArrayMgr: (_name : string) => ({getEntry: (_key : string) => undefined, data: {} as any}),
		getInstanceManager: () => ({
			submit: submitSpy,
			getValues: (_container : any, _no_validation? : boolean) => ({}),
			resetDirty: () => {},
		}),
	};
}

function createClassicCompose()
{
	const app = createFakeApp();
	const compose = new MailCompose(app);
	(compose as any).isJmapMode = false;
	(compose as any).bootstrapPromise = Promise.resolve();
	(compose as any).integrateSubmit = async() => [];
	(compose as any).trySaveDraftViaJmap = async() => false;
	let submitCalls = 0;
	const et2 = createFakeEt2(() => { submitCalls++; });
	(compose as any).et2 = et2;
	return {compose, getSubmitCalls: () => submitCalls};
}

/** uploadFinish()'s own signature - an empty getValue() result mirrors a real batch whose files were already merged by an earlier call, so the extra addAttachmentPlaceholder()/submit() branch inside it stays inert; only the pendingAttachmentUploads reset under test fires. */
function fakeUploadFinishEvent()
{
	return {data: {getValue: () => ({})}};
}

/** Drains the microtask queue past submitAction()/saveAsDraft()'s multi-hop `wait` chain (each .then() that returns a promise costs more than one microtask tick to adopt) - a trailing setTimeout(0) macrotask guarantees everything pending has run, regardless of exact hop count. */
async function flushMicrotasks() : Promise<void>
{
	await new Promise<void>((resolve) => setTimeout(resolve, 0));
}

describe("MailCompose classic attachment-upload race (pendingAttachmentUploads)", () =>
{
	it("uploadStart() increments the pending-upload counter for a classic (non-JMAP) file add", () =>
	{
		const {compose} = createClassicCompose();

		compose.uploadStart();

		assert.strictEqual((compose as any).pendingAttachmentUploads, 1);
	});

	it("uploadStart() does NOT increment the counter in JMAP mode - direct-to-blob upload has no race", () =>
	{
		const {compose} = createClassicCompose();
		(compose as any).isJmapMode = true;
		(compose as any).uploadLocalAttachmentViaJmap = async() => {};
		const event = new CustomEvent("et2-add", {detail: {file: new File(["x"], "x.txt")}, cancelable: true});

		compose.uploadStart(event);

		assert.strictEqual((compose as any).pendingAttachmentUploads, 0);
	});

	it("uploadFinish() resets the counter back to 0", () =>
	{
		const {compose} = createClassicCompose();
		compose.uploadStart();
		compose.uploadStart();

		compose.uploadFinish(fakeUploadFinishEvent(), 2, undefined);

		assert.strictEqual((compose as any).pendingAttachmentUploads, 0);
	});

	it("submitAction() does not submit while an attachment upload is still pending - the exact reported bug", async() =>
	{
		const {compose, getSubmitCalls} = createClassicCompose();
		compose.uploadStart();

		compose.submitAction(false);
		await flushMicrotasks();

		assert.strictEqual(getSubmitCalls(), 0, "must not send while an attachment is still uploading");
	});

	it("submitAction() submits once uploadFinish() clears the pending upload", async() =>
	{
		const {compose, getSubmitCalls} = createClassicCompose();
		compose.uploadStart();

		compose.submitAction(false);
		await flushMicrotasks();
		assert.strictEqual(getSubmitCalls(), 0, "still waiting for the upload");

		compose.uploadFinish(fakeUploadFinishEvent(), 1, undefined);
		await flushMicrotasks();

		assert.strictEqual(getSubmitCalls(), 1, "sends once the upload clears");
	});

	it("submitAction() submits immediately when there is no pending upload (unaffected default case)", async() =>
	{
		const {compose, getSubmitCalls} = createClassicCompose();

		compose.submitAction(false);
		await flushMicrotasks();

		assert.strictEqual(getSubmitCalls(), 1);
	});

	it("saveAsDraft() does not build its payload while an attachment upload is still pending - the exact reported bug", async() =>
	{
		const {compose} = createClassicCompose();
		let classicCalled = false;
		(compose as any).saveAsDraftClassic = (_content : any, _action : any, _resolve : () => void) =>
		{
			classicCalled = true;
			_resolve();
		};
		compose.uploadStart();

		const pending = compose.saveAsDraft(null, 'save');
		await flushMicrotasks();
		assert.isFalse(classicCalled, "must not save while an attachment is still uploading");

		compose.uploadFinish(fakeUploadFinishEvent(), 1, undefined);
		await pending;

		assert.isTrue(classicCalled, "saves once the upload clears");
	});

	it("saveAsDraft() saves immediately when there is no pending upload (unaffected default case)", async() =>
	{
		const {compose} = createClassicCompose();
		let classicCalled = false;
		(compose as any).saveAsDraftClassic = (_content : any, _action : any, _resolve : () => void) =>
		{
			classicCalled = true;
			_resolve();
		};

		await compose.saveAsDraft(null, 'save');

		assert.isTrue(classicCalled);
	});
});
