/**
 * "Download all attachments": save every attachment of a message as its own file, the download
 * counterpart to the existing "Save all to Filemanager" action - as opposed to "Save as ZIP",
 * which hands out a single server-assembled archive.
 *
 * Downloads run strictly sequentially, with a small gap between them: only one attachment is
 * held in memory at a time, the server sees one fetch at a time instead of a burst, and
 * browsers cope far better with serialized saves than with N of them fired at once. A failing
 * attachment does not abort the rest - the caller gets the per-file failures back and reports
 * them once, instead of one message per attachment.
 *
 * Each attachment takes the same two paths the single-attachment "Download" action uses: the
 * fast client-side JMAP blob download when the row has a blobId and its mail_id parses, else
 * the classic mail.mail_ui.getAttachment URL. The classic one is fetched into a blob and saved
 * from a `blob:` URL rather than by pointing a synthetic <a download> at the server URL - see
 * AGENTS.md's "File downloads" section for why that is unreliable (silently sends no request at
 * all from inside a popup window), which a multi-file loop would only make worse.
 *
 * A standalone module (not a MailApp method) so it stays trivially unit-testable - importing
 * MailApp itself pulls in its whole heavy dependency graph. Deliberately UI-free: it returns
 * counts and reports nothing itself, leaving egw.message() to the caller.
 */

/**
 * One attachmentsBlock row, as built by AttachmentJmap::createAttachmentBlock() -
 * only the fields needed to download it.
 */
export interface AttachmentRow
{
	filename: string;
	mail_id: string;
	partID?: string;
	winmailFlag?: any;
	smime_type?: string;
	// set by mail_ui::jmapAttachmentsToLegacy() for both backends, absent for eg. winmail.dat parts
	blobId?: string;
	type?: string;
}

/**
 * The bits of MailJmap this module uses, kept minimal so tests can stub it.
 */
export interface AttachmentJmapAccess
{
	messageReference(mail_id: string): {profileID: string};
	downloadAttachment(profileID: string, blobId: string, filename: string, mimeType: string): Promise<void>;
}

export interface AttachmentDownloadDeps
{
	// IegwAppLocal-ish, only .webserverUrl is used
	egw: any;
	// omitted (or a row without a blobId) forces the classic path
	jmap?: AttachmentJmapAccess;
	// injectable for tests
	fetch?: typeof window.fetch;
	saveBlob?: (blob: Blob, filename: string) => void;
	delay?: (ms: number) => Promise<void>;
}

export interface AttachmentDownloadResult
{
	downloaded: number;
	// filenames that could not be downloaded, in the order they were attempted
	failed: string[];
}

/**
 * Gap between two saves - long enough for the browser to have processed the previous
 * download click, short enough not to feel like a stall for a handful of attachments.
 */
export const DOWNLOAD_GAP_MS = 200;

/**
 * Classic (non-JMAP) download URL for a single attachment, identical to what the
 * single-attachment "Download" action has always used.
 */
export function attachmentSaveUrl(egw: any, attachment: AttachmentRow): string
{
	return egw.webserverUrl + '/index.php?' + new URLSearchParams({
		menuaction: 'mail.mail_ui.getAttachment',
		mode: 'save',
		id: attachment.mail_id,
		part: attachment.partID,
		is_winmail: attachment.winmailFlag,
		smime_type: attachment.smime_type ?? ''
	} as any).toString();
}

/**
 * Download every given attachment as its own file, one after the other.
 *
 * @param attachments attachmentsBlock rows; entries without a filename (eg. the null holes
 *	an attachmentsBlock can contain) are skipped
 * @param deps
 * @return per-file outcome, for the caller to report in a single message
 */
export async function downloadAttachments(attachments: AttachmentRow[], deps: AttachmentDownloadDeps): Promise<AttachmentDownloadResult>
{
	const doFetch = deps.fetch ?? ((input, init?) => window.fetch(input, init));
	const saveBlob = deps.saveBlob ?? saveBlobAsFile;
	const wait = deps.delay ?? ((ms: number) => new Promise<void>(resolve => window.setTimeout(resolve, ms)));
	const rows = (attachments ?? []).filter(attachment => attachment?.filename);
	const result: AttachmentDownloadResult = {downloaded: 0, failed: []};

	for (const [index, attachment] of rows.entries())
	{
		if (index) await wait(DOWNLOAD_GAP_MS);
		try
		{
			await downloadOne(attachment, deps, doFetch, saveBlob);
			result.downloaded++;
		}
		catch (e)
		{
			console.error('downloadAttachments(): failed for ' + attachment.filename, e);
			result.failed.push(attachment.filename);
		}
	}
	return result;
}

/**
 * JMAP blob download when applicable, classic fetch-to-blob otherwise.
 *
 * @throws whatever the JMAP download threw (a JmapUserError, message already translated), or
 *	an Error naming the failing HTTP status
 */
async function downloadOne(attachment: AttachmentRow, deps: AttachmentDownloadDeps,
						   doFetch: typeof window.fetch, saveBlob: (blob: Blob, filename: string) => void): Promise<void>
{
	const profileID = jmapProfileID(attachment, deps.jmap);
	if (profileID !== null)
	{
		// jmap.downloadAttachment() saves the file itself, same as for a single download
		return deps.jmap.downloadAttachment(profileID, attachment.blobId, attachment.filename, attachment.type);
	}
	const response = await doFetch(attachmentSaveUrl(deps.egw, attachment), {credentials: 'same-origin'});
	if (!response.ok)
	{
		throw new Error('HTTP ' + response.status + ' ' + response.statusText);
	}
	saveBlob(await response.blob(), attachment.filename);
}

/**
 * profileID to download this attachment via JMAP, or null if the classic path has to be used:
 * no jmap access, no blobId on the row, or a mail_id messageReference() cannot parse. None of
 * those is a JMAP failure, just "not applicable" - same fallback rule as a single download.
 */
function jmapProfileID(attachment: AttachmentRow, jmap?: AttachmentJmapAccess): string | null
{
	if (!jmap || !attachment.blobId)
	{
		return null;
	}
	try
	{
		return jmap.messageReference(attachment.mail_id).profileID;
	}
	catch (e)
	{
		return null;
	}
}

/**
 * Save an already-fetched blob to the user's disk, same mechanism (and object-URL lifecycle)
 * MailJmap.downloadAttachment() uses for the JMAP path.
 */
function saveBlobAsFile(blob: Blob, filename: string): void
{
	const url = URL.createObjectURL(blob);
	try
	{
		const link = document.createElement('a');
		link.href = url;
		link.download = filename || 'attachment';
		document.body.appendChild(link);
		link.click();
		link.remove();
	}
	finally
	{
		// revoke after the click has been processed, not synchronously
		window.setTimeout(() => URL.revokeObjectURL(url), 1000);
	}
}
