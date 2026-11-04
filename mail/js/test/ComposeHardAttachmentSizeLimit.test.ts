import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import type {MailApp} from "../app";

/**
 * Regression coverage for the ticket #126971-adjacent huge-attachment report (2026-11-04, ralf):
 * uploading a file bigger than the mail server's own willingness-to-process limit went straight to
 * a single-shot JMAP blob upload (Stalwart directly, or Api\Mail\Jmap\Imap::upload() for the shim)
 * with no size check beforehand at all - certain failure for Stalwart, a silent-truncation risk for
 * the shim. uploadStart() now decides SYNCHRONOUSLY (exceedsHardLimit()) whether a file must be
 * routed through Et2File's own normal classic chunked upload instead (same path classic/non-JMAP
 * compose always uses) - uploadFinish() then merges the result client-side (never a postback, which
 * would reset unsent body/mimeType edits in JMAP mode) and locks the filemode selector to 'link',
 * since sending such a file any other way is a guaranteed failure, not just a soft/reversible
 * preference the way warnAttachmentSizeLimit()'s own attachment_limit_mb threshold is.
 */

const egw : any = {
	lang : (label : string, ...args : string[]) =>
	{
		let i = 0;
		return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
	},
	messages : [] as {msg : string, type? : string}[],
	message(msg : string, type? : string) { this.messages.push({msg, type}); },
	loadingPromptCalls : [] as {id : string, show : boolean, msg? : string}[],
	loading_prompt(id : string, show : boolean, msg? : string) { this.loadingPromptCalls.push({id, show, msg}); },
	request : async(_method : string, _args : any[]) : Promise<any> => undefined,
};

function createFakeApp() : MailApp
{
	return {egw, jmap: {maxSizeUpload: async() => Infinity}} as unknown as MailApp;
}

function createFakeEt2(initialContent : Record<string, any> = {})
{
	const filemodeWidget : any = {
		value : 'attach', disabled : false, hidden : false,
		// matches Et2InputWidget.getValue()'s own real behaviour (api/js/etemplate/Et2InputWidget/
		// Et2InputWidget.ts): `return this.readonly || this.disabled ? null : this.value`. ralf,
		// 2026-11-04, live: a real send with a locked/disabled filemode failed server-side
		// ("Expected a partId or blobId field in body part") because currentEmailFields() read
		// filemode via get_value() - null while locked - and so never converted attachments to
		// links at all. An earlier, less accurate version of this very mock (plain `return
		// this.value`, ignoring disabled) would NOT have caught that regression - see
		// currentEmailFieldsReadsFilemodeViaValueWhileLocked below, the test this fix added.
		get_value() { return this.disabled ? null : this.value; },
		set_value(v : any) { this.value = v; },
		// deliberately NO set_disabled() here - a real Et2Widget's own legacy set_disabled()
		// is @deprecated precisely because it also sets .hidden (see recomputeFilemodeLock()'s
		// own docblock) - this mock would need to replicate that mistake to hide the regression;
		// omitting it makes a stray set_disabled() call fail loudly instead of silently matching
	};
	const attachmentsWidget : any = {set_value(_v : any) {}, getParent : () => detailsWidget};
	const detailsWidget : any = {set_disabled(_v : boolean) {}, getParent : () => uploadSectionWidget, getChildren : () => [], title: ''};
	const uploadSectionWidget : any = {set_disabled(_v : boolean) {}};

	let content : any = {data : {attachments : [], ...initialContent}, getEntry(key : string) { return this.data[key]; }};

	return {
		getArrayMgr : (_name : string) => content,
		setArrayMgr : (_name : string, mgr : any) => { content = mgr; },
		getWidgetById : (id : string) =>
		{
			if(id === 'filemode') return filemodeWidget;
			if(id === 'attachments') return attachmentsWidget;
			return null;
		},
		getInstanceManager : () => ({etemplate_exec_id : 'test-exec-id'}),
		filemodeWidget,
	};
}

function createCompose(initialContent : Record<string, any> = {})
{
	const compose = Object.create(MailCompose.prototype) as any;
	compose.app = createFakeApp();
	compose.isJmapMode = true;
	compose.pendingAttachmentUploads = 0;
	compose.pendingUploadWaiters = [];
	compose.cachedMaxSizeUploadBytes = Infinity;
	compose.resolvePendingUploadWaiters = () => {};
	compose._wireAttachmentDeleteHandlers = () => {};
	const et2 = createFakeEt2(initialContent);
	compose.et2 = et2;
	return {compose, et2};
}

function oneMbFile(name : string = "huge.pdf") : File
{
	// jsdom/browser File.size reflects the actual byte content - a real 1MB blob, not a label
	return new File([new Uint8Array(1024 * 1024)], name, {type: "application/pdf"});
}

describe("MailCompose.exceedsHardLimit()", () =>
{
	it("is false for a small file well under both ceilings", () =>
	{
		const {compose} = createCompose({maxMessageSizeMb: 64});
		assert.isFalse(compose.exceedsHardLimit(1024));
	});

	it("is true when the file alone exceeds the account's own cachedMaxSizeUploadBytes", () =>
	{
		const {compose} = createCompose({maxMessageSizeMb: 64});
		compose.cachedMaxSizeUploadBytes = 10 * 1024 * 1024;
		assert.isTrue(compose.exceedsHardLimit(20 * 1024 * 1024));
	});

	it("is true when the base64-encoded total (existing + new) would exceed max_message_size_mb", () =>
	{
		const {compose} = createCompose({maxMessageSizeMb: 1});
		// 1MB raw * 4/3 inflation = ~1.33MB encoded, already over a 1MB total ceiling
		assert.isTrue(compose.exceedsHardLimit(1024 * 1024));
	});

	it("accounts for ALREADY-attached files, not just the new one", () =>
	{
		const {compose} = createCompose({maxMessageSizeMb: 2, attachments: [{size: 1024 * 1024}]});
		// existing 1MB + new 0.5MB = 1.5MB raw * 4/3 = 2MB encoded - right at the edge, still fits
		assert.isFalse(compose.exceedsHardLimit(0.5 * 1024 * 1024));
		// existing 1MB + new 1MB = 2MB raw * 4/3 = ~2.67MB encoded - over the 2MB ceiling
		assert.isTrue(compose.exceedsHardLimit(1024 * 1024));
	});

	it("falls back to 64MB when max_message_size_mb isn't configured", () =>
	{
		const {compose} = createCompose({});
		assert.isFalse(compose.exceedsHardLimit(10 * 1024 * 1024));
		assert.isTrue(compose.exceedsHardLimit(100 * 1024 * 1024));
	});
});

describe("MailCompose.uploadStart() - routes an oversized file around the direct JMAP blob upload", () =>
{
	it("a small file still goes straight to the JMAP blob upload (preventDefault + no pending-upload count)", () =>
	{
		const {compose} = createCompose({maxMessageSizeMb: 64});
		let uploadedFile : File | null = null;
		compose.uploadLocalAttachmentViaJmap = async(f : File) => { uploadedFile = f; };
		const file = new File(["x"], "small.txt");
		const event = new CustomEvent("et2-add", {detail: {file}, cancelable: true});

		compose.uploadStart(event);

		assert.isTrue(event.defaultPrevented);
		assert.strictEqual(compose.pendingAttachmentUploads, 0);
		assert.strictEqual(uploadedFile, file);
	});

	it("an oversized file is left un-canceled, falling through to the classic chunked-upload counter", () =>
	{
		const {compose} = createCompose({maxMessageSizeMb: 1});
		let jmapUploadCalled = false;
		compose.uploadLocalAttachmentViaJmap = async() => { jmapUploadCalled = true; };
		const event = new CustomEvent("et2-add", {detail: {file: oneMbFile()}, cancelable: true});

		compose.uploadStart(event);

		assert.isFalse(event.defaultPrevented, "must NOT cancel Et2File's own chunked upload for an oversized file");
		assert.strictEqual(compose.pendingAttachmentUploads, 1, "must still gate Send/Save-as-Draft, same as the classic path");
		assert.isFalse(jmapUploadCalled);
	});

	/**
	 * ralf, 2026-11-04, live: "for the big attachment it looks broken, because nothing happens,
	 * until the upload is really complete" - a 240MB chunked upload can take genuinely long, and
	 * Et2File's own per-file progress UI isn't visible in this compose layout, so without this the
	 * popup looked frozen for the whole duration.
	 */
	it("shows a loading prompt for an oversized file, but NOT for a small direct-upload one", () =>
	{
		const {compose} = createCompose({maxMessageSizeMb: 1});
		compose.uploadLocalAttachmentViaJmap = async() => {};
		egw.loadingPromptCalls.length = 0;

		compose.uploadStart(new CustomEvent("et2-add", {detail: {file: new File(["x"], "small.txt")}, cancelable: true}));
		assert.lengthOf(egw.loadingPromptCalls, 0, "a small file resolves almost instantly - no spinner needed");

		compose.uploadStart(new CustomEvent("et2-add", {detail: {file: oneMbFile()}, cancelable: true}));
		assert.lengthOf(egw.loadingPromptCalls, 1);
		assert.isTrue(egw.loadingPromptCalls[0].show);
		assert.include(egw.loadingPromptCalls[0].msg, "Upload in progress");
	});
});

describe("MailCompose.uploadFinish() - JMAP mode merges a chunked (Tier-3) upload client-side", () =>
{
	function fakeChunkedUploadEvent(tempName : string, file : File)
	{
		return {data: {getValue: () => ({[tempName]: {name: file.name, type: file.type, file}})}};
	}

	it("merges the uploaded file as a forced-link attachment, without ever calling submit()", () =>
	{
		const {compose, et2} = createCompose({maxMessageSizeMb: 1});
		let submitCalled = false;
		(et2 as any).getInstanceManager = () => ({submit : () => { submitCalled = true; }});
		const file = oneMbFile("huge.pdf");

		compose.uploadFinish(fakeChunkedUploadEvent("tmp123", file), 1, undefined);

		assert.isFalse(submitCalled, "a classic postback would reset unsent body/mimeType edits in JMAP mode");
		const attachments = et2.getArrayMgr('content').data.attachments;
		assert.lengthOf(attachments, 1);
		assert.deepInclude(attachments[0], {
			tmp_name: "tmp123", name: "huge.pdf", type: "application/pdf",
			size: file.size, filemode_icon: "link", forcedLink: true,
		});
	});

	it("hides the loading prompt uploadStart() showed, once the chunked upload completes", () =>
	{
		const {compose, et2} = createCompose({maxMessageSizeMb: 1});
		(et2 as any).getInstanceManager = () => ({submit : () => {}});
		egw.loadingPromptCalls.length = 0;

		compose.uploadFinish(fakeChunkedUploadEvent("tmp123", oneMbFile()), 1, undefined);

		assert.lengthOf(egw.loadingPromptCalls, 1);
		assert.isFalse(egw.loadingPromptCalls[0].show);
	});

	/**
	 * ralf, 2026-11-04 (live screenshot): a huge upload showed TWO stacked toasts -
	 * warnAttachmentSizeLimit()'s generic soft-threshold message ("exceeds the limit of 26 MB")
	 * right alongside this method's own specific one - always redundant here, since the hard
	 * limit is never smaller than the soft one, so crossing it always also crosses the soft one.
	 * "I think we should only have a single (translated) message, not two."
	 */
	it("shows exactly ONE message (the specific hard-limit one), not also the generic soft-threshold one", () =>
	{
		const {compose, et2} = createCompose({maxMessageSizeMb: 1, attachmentLimitMb: 1});
		(et2 as any).getInstanceManager = () => ({submit : () => {}});
		egw.messages.length = 0;

		compose.uploadFinish(fakeChunkedUploadEvent("tmp123", oneMbFile("huge.pdf")), 1, undefined);

		assert.lengthOf(egw.messages, 1);
		assert.include(egw.messages[0].msg, "huge.pdf");
		assert.include(egw.messages[0].msg, "too large for the mail server to accept as a real attachment");
	});

	it("still runs the classic postback for non-JMAP compose (unaffected default case)", () =>
	{
		const {compose, et2} = createCompose();
		compose.isJmapMode = false;
		let submitCalled = false;
		(et2 as any).getInstanceManager = () => ({submit : () => { submitCalled = true; }});

		compose.uploadFinish(fakeChunkedUploadEvent("tmp123", oneMbFile()), 1, undefined);

		assert.isTrue(submitCalled);
	});

	it("locks the filemode selector to 'link' after merging a forced attachment", () =>
	{
		const {compose, et2} = createCompose({maxMessageSizeMb: 1});
		(et2 as any).getInstanceManager = () => ({submit : () => {}});

		compose.uploadFinish(fakeChunkedUploadEvent("tmp123", oneMbFile()), 1, undefined);

		// NOT get_value() - it deliberately returns null once the widget is disabled below (matches
		// Et2InputWidget's own real getValue(), see this file's own filemodeWidget mock docblock);
		// .value is the widget's own true current selection regardless of disabled state.
		assert.strictEqual(et2.filemodeWidget.value, "link");
		assert.isTrue(et2.filemodeWidget.disabled, "must not be switchable back to 'attach' - that's a guaranteed failure");
		assert.isFalse(et2.filemodeWidget.hidden,
			"the user must still SEE the selector (and its current 'link' value), just not be able to change it - "
			+ "Et2Widget's own legacy set_disabled() sets .hidden too, which must NOT be used here");
	});
});

describe("MailCompose.deleteAttachment() - releases the filemode lock once the forced attachment is removed", () =>
{
	it("re-enables the filemode selector once the last forced-link attachment is deleted", () =>
	{
		const {compose, et2} = createCompose({
			attachments: [{tmp_name: "tmp123", name: "huge.pdf", size: 1024, forcedLink: true}],
		});
		et2.filemodeWidget.set_value("link");
		et2.filemodeWidget.disabled = true;

		compose.deleteAttachment({id: "delete[tmp123]"});

		assert.isFalse(et2.filemodeWidget.disabled);
	});

	it("keeps the lock while at least one other forced-link attachment remains", () =>
	{
		const {compose, et2} = createCompose({
			attachments: [
				{tmp_name: "tmp1", name: "a.pdf", size: 1024, forcedLink: true},
				{tmp_name: "tmp2", name: "b.pdf", size: 1024, forcedLink: true},
			],
		});
		et2.filemodeWidget.disabled = true;

		compose.deleteAttachment({id: "delete[tmp1]"});

		assert.isTrue(et2.filemodeWidget.disabled);
	});
});

describe("MailCompose.uploadAttachmentsViaJmap() - a forcedLink attachment never becomes a real JMAP blob", () =>
{
	/**
	 * ralf, 2026-11-04, live: "Failed to read attachment ...pdf" - this method used to try reading
	 * a forcedLink attachment's bytes via the classic getAttachment() endpoint and re-uploading
	 * them as a JMAP blob, exactly like any other classically-staged attachment. That always 404s
	 * for a client-side-only merge (getAttachment() reads Etemplate\Request's own server-side
	 * POSTBACK state, which JMAP-mode deliberately never establishes), and even if it didn't, a
	 * 200+MB blob upload is exactly the failure this whole mechanism exists to avoid. A send must
	 * instead get the plain classic shape straight through - resolveJmapAttachmentsToFiles()
	 * (mail/src/Compose.php) now knows how to read it directly from temp storage.
	 */
	it("passes the plain tmp_name shape straight through for a send, without any network call", async() =>
	{
		const {compose, et2} = createCompose({
			attachments: [{tmp_name: "tmp123", name: "huge.pdf", type: "application/pdf", size: 240 * 1024 * 1024, forcedLink: true}],
		});
		let fetchCalled = false;
		const originalFetch = globalThis.fetch;
		(globalThis as any).fetch = async() => { fetchCalled = true; return {ok: true, blob: async() => new Blob()}; };

		let resolved : any[];
		try
		{
			resolved = await compose.uploadAttachmentsViaJmap("acc1", true);
		}
		finally
		{
			globalThis.fetch = originalFetch;
		}

		assert.isFalse(fetchCalled, "must never attempt to read/re-upload bytes for a forcedLink attachment");
		assert.deepEqual(resolved, [{tmp_name: "tmp123", name: "huge.pdf", type: "application/pdf", size: 240 * 1024 * 1024}]);
	});

	/**
	 * A draft save (forSend=false) never converts attachments to links at all (currentEmailFields()'s
	 * own docblock: a draft keeps real attachments as real blobs, only an actual send resolves
	 * links) - there is no valid JMAP shape to give a forcedLink attachment in that case (it can
	 * never become a real blobId), so it's excluded from the saved draft entirely rather than
	 * handed through as a broken reference. The attachment itself survives in content.attachments
	 * regardless (this method never touches that array) - still there, and still handled correctly,
	 * the next time the user actually sends.
	 */
	it("excludes a forcedLink attachment from a draft save entirely, not as a broken reference", async() =>
	{
		const {compose, et2} = createCompose({
			attachments: [{tmp_name: "tmp123", name: "huge.pdf", type: "application/pdf", size: 240 * 1024 * 1024, forcedLink: true}],
		});

		const resolved = await compose.uploadAttachmentsViaJmap("acc1", false);

		assert.deepEqual(resolved, []);
		assert.lengthOf(et2.getArrayMgr('content').data.attachments, 1,
			"the attachment reference itself must survive in content.attachments for a later send");
	});
});

describe("MailCompose.currentEmailFields() - reads a locked filemode correctly while sending", () =>
{
	/**
	 * ralf, 2026-11-04, live: a real send attempt with a forced-link attachment failed server-side
	 * ("Expected a partId or blobId field in body part") - currentEmailFields() read filemode via
	 * get_value(), which Et2InputWidget.getValue() deliberately returns null for while the widget
	 * is disabled (matching native HTML form semantics) - recomputeFilemodeLock() keeps it disabled
	 * for exactly as long as a forcedLink attachment is present, so this condition
	 * (`filemode && filemode !== 'attach' && ...`) never fired, attachments never got converted to
	 * links, and the plain {tmp_name,...} shape reached draftEmailProperties() unconverted, which
	 * emits {blobId: undefined,...} for it - a body part with neither a real blobId nor a partId.
	 * Fixed by reading the widget's own `.value` directly instead of `.get_value()`.
	 */
	it("still converts attachments to links for send when filemode is locked/disabled to 'link'", async() =>
	{
		const {compose, et2} = createCompose({
			attachments: [{tmp_name: "tmp123", name: "huge.pdf", type: "application/pdf", size: 240 * 1024 * 1024, forcedLink: true}],
		});
		et2.filemodeWidget.value = "link";
		et2.filemodeWidget.disabled = true;
		compose.explicitShareModeChosen = true;
		assert.isNull(et2.filemodeWidget.get_value(), "sanity check: the mock replicates the real disabled-returns-null behaviour");

		let capturedParams : any = null;
		const originalRequest = compose.app.egw.request;
		compose.app.egw.request = async(method : string, args : any[]) =>
		{
			if (method === 'mail.EGroupware\\Mail\\Compose.ajax_getAttachmentLinksBody')
			{
				capturedParams = args[0];
				return '<p>body</p><p>STUBBED LINK</p>';
			}
			return undefined;
		};

		let fields : any;
		try
		{
			fields = await compose.currentEmailFields(true);
		}
		finally
		{
			compose.app.egw.request = originalRequest;
		}

		assert.isNotNull(capturedParams, "ajax_getAttachmentLinksBody must be called - this is what actually converts the attachment to a link");
		assert.strictEqual(capturedParams.filemode, "link");
		assert.lengthOf(capturedParams.attachments, 1);
		assert.strictEqual(fields.body, '<p>body</p><p>STUBBED LINK</p>');
		assert.isUndefined(fields.attachments,
			"must be cleared - a forcedLink attachment can never reach draftEmailProperties(), it has no blobId to give it");
	});
});
