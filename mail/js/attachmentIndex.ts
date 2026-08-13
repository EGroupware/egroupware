/**
 * Auto-index: when a message has no visible text/html body (eg. a photo emailed with no
 * comment) but does have attachments, show them directly in the body area instead of leaving
 * it blank - images shown inline, PDFs embedded, everything else as a clickable mime-icon that
 * downloads on click. A single attachment gets no header (it already fully represents "the
 * content"); with more than one, each gets a "---- filename ----" divider.
 *
 * Purely a *display* convenience - never touches attachmentsBlock itself (the "official"
 * attachment list stays exactly as resolved, unaffected by what's inlined here).
 *
 * A standalone module (not a MailApp method) so it stays trivially unit-testable - importing
 * MailApp itself pulls in its whole heavy dependency graph.
 *
 * @param doc body iframe's contentDocument
 * @param attachmentsBlock current attachmentsBlock array - safe to call with an still-empty
 *  array (eg. before an on-demand JMAP fetch resolves); callers should retry once it's known
 * @param egw the app's IegwAppLocal-ish egw object - only .link()/.lang()/.image()/.open_link()
 *  are used, kept minimal so callers/tests don't need a full egw instance
 */
export function renderAttachmentIndex(doc : Document, attachmentsBlock : any[], egw : any) : void
{
	if (!doc?.body || doc.body.textContent.trim() !== '' || !attachmentsBlock?.length ||
		doc.body.querySelector('.mail_attachmentIndex'))
	{
		return;
	}
	const showHeader = attachmentsBlock.length > 1;
	const container = doc.createElement('div');
	container.className = 'mail_attachmentIndex';

	if (showHeader)
	{
		const downloadAll = doc.createElement('a');
		downloadAll.href = egw.link('/index.php', {
			menuaction: 'mail.mail_ui.download_zip',
			id: attachmentsBlock[0].mail_id
		});
		downloadAll.className = 'mail_attachmentIndexDownloadAll';
		downloadAll.textContent = egw.lang('Download all');
		container.appendChild(downloadAll);
	}

	attachmentsBlock.forEach((att : any) =>
	{
		const item = doc.createElement('div');
		item.className = 'mail_attachmentIndexItem';
		if (showHeader)
		{
			const header = doc.createElement('div');
			header.className = 'mail_attachmentIndexHeader';
			header.textContent = '---- ' + att.filename + ' ----';
			item.appendChild(header);
		}
		const type = (att.type || '').toLowerCase();
		const url = att.mime_url;

		if (type.startsWith('image/') && url)
		{
			const img = doc.createElement('img');
			img.loading = 'lazy';
			img.setAttribute('width', '100%');
			img.setAttribute('height', 'auto');
			img.src = url;
			img.alt = att.filename;
			item.appendChild(img);
		}
		else if (type === 'application/pdf' && url)
		{
			const iframe = doc.createElement('iframe');
			iframe.src = url;
			iframe.title = att.filename;
			iframe.className = 'mail_attachmentIndexPdf';
			item.appendChild(iframe);
		}
		else
		{
			// no directly embeddable URL (or a non-inlinable type, eg. a docx/zip) - a
			// clickable mime-icon that downloads/opens the file, same as the existing
			// attachment list's own download action
			const link = doc.createElement('a');
			link.className = 'mail_attachmentIndexIcon';
			link.title = att.filename;
			link.href = url || '#';
			if (!url && att.mime_data)
			{
				link.addEventListener('click', (ev) =>
				{
					ev.preventDefault();
					egw.open_link(att.mime_data, '_blank', undefined, undefined, att.type);
				});
			}
			const [main, sub] = type.split('/');
			const icon = doc.createElement('img');
			icon.src = egw.image('mime128_' + main + '_' + sub) || egw.image('mime128_' + main) ||
				egw.image('mime128_unknown');
			icon.alt = att.filename;
			link.appendChild(icon);
			const label = doc.createElement('div');
			label.textContent = att.filename;
			link.appendChild(label);
			item.appendChild(link);
		}
		container.appendChild(item);
	});

	// .mailDisplayBody (jmap.ts's wrapDocument(), and the classic server-rendered fallback's
	// showBody() - same markup either way) has height:100% and is body's first/only child, so
	// appending straight to doc.body would push the index below the fold, entirely out of view
	// (behind whatever height that empty wrapper claims). Insert into the actual content cell
	// instead, falling back to .mailDisplayBody or doc.body if the expected structure isn't there.
	const target = doc.querySelector('.mailDisplayBody .td_display') ||
		doc.querySelector('.mailDisplayBody') || doc.body;
	target.appendChild(container);
}
