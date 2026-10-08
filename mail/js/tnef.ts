/**
 * Helpers for a winmail.dat (TNEF) attachment, which the server unpacks into its own files
 *
 * A standalone module (not MailApp methods) to keep the rules in one place for the preview and the display popup,
 * and trivially unit-testable - importing MailApp itself pulls in its whole heavy dependency graph.
 */

/**
 * One attachmentsBlock row, as built by AttachmentJmap::createAttachmentBlock(), or an RFC 8621 EmailBodyPart
 * of the client-side JMAP metadata - only the fields needed to recognize a TNEF.
 */
export interface TnefAttachmentRow
{
	filename? : string;
	name? : string;
	type? : string;
	partID? : string;
	partId? : string;
	blobId? : string | null;
	attachment_number? : number;

	[key : string] : any;
}

const TNEF_TYPES = ['application/ms-tnef', 'application/vnd.ms-tnef'];
const RTF_TYPES = ['application/rtf', 'text/rtf', 'application/x-rtf'];

/**
 * Is that attachment a winmail.dat (TNEF), which has to be unpacked?
 *
 * By its type or name only, NOT by the "winmailFlag" the classic server code sets: the JMAP code leaves it empty for
 * the raw winmail.dat, and relying on it left a display popup showing just "winmail.dat" (ticket #126161).
 * "mimetype" is a label ("MS Tnef") and can NOT be used, "type" is the real mime-type.
 */
export function isTnefEntry(entry : TnefAttachmentRow | null | undefined) : boolean
{
	if(!entry)
	{
		return false;
	}
	return TNEF_TYPES.includes((entry.type || "").toLowerCase()) ||
		(entry.filename ?? entry.name ?? "").toLowerCase() === "winmail.dat";
}

/**
 * @return index of the first winmail.dat in the attachments or -1
 */
export function findTnefEntry(attachments : TnefAttachmentRow[] | null | undefined) : number
{
	return Array.isArray(attachments) ? attachments.findIndex(isTnefEntry) : -1;
}

/**
 * Is that the RTF copy of the mail body, which Outlook puts into every winmail.dat?
 *
 * The TNEF decoder names it "Untitled.rtf". It is no attachment of the user, and the body is already shown.
 */
export function isRtfBody(entry : TnefAttachmentRow | null | undefined) : boolean
{
	if(!entry)
	{
		return false;
	}
	const type = (entry.type || "").toLowerCase();
	return (entry.filename ?? entry.name ?? "").toLowerCase() === "untitled.rtf" && (!type || RTF_TYPES.includes(type));
}

/**
 * Remove the RTF copy of the mail body from the files unpacked from a winmail.dat
 *
 * Only if there are other files: a winmail.dat with nothing but that RTF might carry the only copy of the body.
 *
 * @param unpacked the unpacked files
 * @return the files without the RTF body, the given array if nothing needs to be removed
 */
export function dropRtfBody<T extends TnefAttachmentRow>(unpacked : T[]) : T[]
{
	if(!Array.isArray(unpacked) || unpacked.length < 2)
	{
		return unpacked;
	}
	const rest = unpacked.filter(entry => !isRtfBody(entry));
	return rest.length ? rest : unpacked;
}

/**
 * Give the attachments a new continuous attachment_number
 *
 * @return the given array
 */
export function renumber<T extends TnefAttachmentRow>(attachments : T[]) : T[]
{
	attachments.forEach((entry, number) =>
	{
		if(entry)
		{
			entry.attachment_number = number;
		}
	});
	return attachments;
}

/**
 * Replace the winmail.dat in the attachments by the files unpacked from it, keeping all other attachments
 *
 * @param attachments all attachments
 * @param index index of the winmail.dat in attachments
 * @param unpacked the files unpacked from it
 * @return new array, the entries get a new continuous attachment_number
 */
export function spliceUnpacked<T extends TnefAttachmentRow>(attachments : T[], index : number, unpacked : T[]) : T[]
{
	return renumber([...attachments.slice(0, index), ...unpacked, ...attachments.slice(index + 1)]);
}
