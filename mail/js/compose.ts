/**
 * mail - compose functions
 *
 * @link: https://www.egroupware.org
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2013-2025 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import type {MailApp} from "./app";
import type {Et2Template} from "../../api/js/etemplate/Et2Template/Et2Template";
// IegwAppLocal/egw/egw_getFramework are ambient globals (declare global {} in
// egw_global.d.ts, unconditionally included via tsconfig's "**/*.d.ts") - no import
// needed or possible.
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {et2_widget} from "../../api/js/etemplate/et2_core_widget";
import {MailJmap} from "./jmap";
import type {JmapReplyContext} from "./jmap";

export class MailCompose
{
	protected app : MailApp;
	private et2 : Et2Template
	private autosaveInterval : number;

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md - set from this popup's own "&jmap=1" URL
	 * param, added by MailApp.composeMessage() for a genuinely new blank message (Step 1) or a
	 * single reply (Step 4, first slice - never reply_all/forward yet) opened while the
	 * "jmapCompose" toolbar toggle is on (see jmapComposeEnabled's docblock). Read once here
	 * rather than in submitAction() so a mid-edit toggle change in the (separate) main window
	 * can't retroactively change an already-open compose window's send behaviour.
	 */
	private readonly isJmapMode : boolean = new URLSearchParams(window.location.search).get('jmap') === '1';

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 1 - the JMAP Email id of this compose
	 * session's own draft, once trySaveDraftViaJmap() has created one - passed back in as
	 * saveDraft()'s existingEmailId on the NEXT autosave/save so it updates that same draft in
	 * place instead of creating a new one on every autosave tick.
	 */
	private jmapDraftEmailId? : string;

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 4 - the exact decorated substring
	 * MailJmap.composeBodyWithSignature() last inserted into the body widget, tracked so a later
	 * identity switch (updateSignatureForIdentity()) can strip it back out by plain substring
	 * match before recomposing with the new identity's signature - no marker/regex relocate hack
	 * needed (see that function's own docblock for why holding this client-side removes the need
	 * for the classic implementation's fragile one). Empty once nothing has been auto-inserted
	 * yet, or once it could no longer be located (the user edited around/inside it - left alone
	 * rather than guessed at, never silently duplicated).
	 */
	private insertedSignatureBlock : string = '';
	private signaturePlacement : 'top' | 'below' | 'none' = 'below';

	/**
	 * Set once by bootstrapReply() - kept so a later identity switch (updateSignatureForIdentity())
	 * still passes isReply through to composeBodyWithSignature() (the quoted body is already
	 * non-empty by then, so the "no leading blank line above a non-empty new-compose body" rule
	 * must not apply).
	 */
	private isReplyCompose : boolean = false;

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 4 - RFC 5322 threading headers (In-
	 * Reply-To/References) for a reply, set once by bootstrapReply() from MailJmap.fetchForReply()'s
	 * result and included in every currentEmailFields() call thereafter (send AND every
	 * save/autosave) - null for a plain new-message compose.
	 */
	private replyThreadingHeaders : {inReplyTo : string[] | null, references : string[] | null} | null = null;

	/**
	 * Cache of already-uploaded locally-staged attachments (uploadAttachmentsViaJmap()), keyed by
	 * the attachment's own tmp_name - a stable id for one staged file across this whole compose
	 * session. Without this, EVERY autosave tick (and the final send too, if it happens after at
	 * least one autosave) would re-fetch+re-upload the same file as a brand-new JMAP blob (found
	 * live 2026-08-31, ralf: "the same [as inline images] is also true for attachments, we need to
	 * cache their blobIds, to not upload them over and over again and also use them for
	 * submission") - same reasoning as MailJmap's own inlineImageUploads cache. A
	 * carryForwardAttachments() entry (jmapBlobId already set) never reaches this cache at all -
	 * it's already a stable, permanent reference to the original message's own blob, nothing to
	 * upload or remember.
	 */
	private uploadedAttachmentBlobs = new Map<string, {blobId : string, name : string, type : string, size : number}>();

	get egw() : IegwAppLocal
	{
		return this.app.egw;
	}

	constructor(mail : MailApp)
	{
		this.app = mail;

		this.handleEtemplateClear = this.handleEtemplateClear.bind(this);
	}

	destroy()
	{
		this.app = null;
		this.et2.getInstanceManager().DOMContainer.removeEventListener("clear", this.handleEtemplateClear);
		this.et2 = null;
	}
	keepFromExpander=false;

	setEtemplate(et2 : Et2Template)
	{
		this.et2 = et2;
		this.et2.getInstanceManager().DOMContainer.addEventListener("clear", this.handleEtemplateClear, {once: true});

		// Set autosaving interval to 2 minutes for compose message
		this.autosaveInterval = window.setInterval(() =>
		{
			if(document.querySelector('.ms-editor-wrap') === null)
			{
				void this.saveAsDraft(null, 'autosaving');
			}
		}, 120000);

		void this.bootstrapCompose();
	}

	private handleEtemplateClear(event)
	{
		this.et2 = null;
		clearInterval(this.autosaveInterval);
	}

	/**
	 * Visible attachment box in compose dialog as soon as the file starts to upload
	 */
	uploadStart()
	{
		const boxAttachment = this.et2.getWidgetById('attachments');
		if (boxAttachment)
		{
			const groupbox = boxAttachment.getParent();
			if (groupbox) groupbox.set_disabled(false);
		}
		return true;
	}

	/**
	 * Send names of uploaded files (again) to server, to process them: either copy to vfs or ask overwrite/rename
	 *
	 * @param {event object} _event
	 * @param {string} _file_count
	 * @param {string} _path [_path=current directory] Where the file is uploaded to.
	 */
	uploadFinish(_event, _file_count, _path)
	{
		// path is probably not needed when uploading for file; maybe it is when from vfs
		if(typeof _path == 'undefined')
		{
			//_path = this.get_path();
		}
		if (_file_count && Object.keys(_event.data.getValue() || {}).length > 0)
		{
			this.addAttachmentPlaceholder();
			this.et2.getInstanceManager().submit();
		}
	}

	addAttachmentPlaceholder()
	{
		if (this.et2.getArrayMgr("content").getEntry("is_html"))
		{
			// Add link placeholder box
			const email = this.et2.getWidgetById("mail_htmltext");
			const attach_type = this.et2.getWidgetById("filemode");
			const placeholder = '<fieldset class="attachments mceNonEditable"><legend>Download attachments</legend>' + this.egw.lang('Attachments') + '</fieldset>';

			if (email && !email.getValue().includes(placeholder) && attach_type.getValue() !== "attach")
			{
				email.editor.execCommand('mceInsertContent', false, placeholder);
			}
		}
	}

	/**
	 * Upload for compose (VFS)
	 *
	 * @param {egw object} _egw
	 * @param {widget object} _widget
	 * @param {window object} _window
	 */
	vfsUpload(_egw, _widget, _window)
	{
		if (!_widget || Object.keys(_widget).length === 0) return;
		if (Object.keys(_widget.getValue() || {}).length > 0)
		{
			this.addAttachmentPlaceholder();
			this.et2.getInstanceManager().submit();
		}
	}

	/**
	 * Check sharing mode and disable not available options
	 *
	 * @param {Node} _node
	 * @param {et2_widget} _widget can be omitted to get 'filemode' widget from et2
	 */
	checkSharingFilemode(_node, _widget?)
	{
		if (!this.et2 || this.et2.getArrayMgr('content').getEntry('no_griddata')) return;
		if (!_widget) _widget = this.et2.getWidgetById('filemode');

		const extended_settings = _widget.get_value() != 'attach' && this.egw.app('stylite');
		this.et2.getWidgetById('expiration').set_readonly(!extended_settings);
		this.et2.getWidgetById('password').set_readonly(!extended_settings);
		this.et2.getWidgetById('password').set_suggest(!extended_settings ? 0 : 8);

		if (_widget.get_value() == 'share_rw' && !this.egw.app('stylite'))
		{
			this.egw.message(this.egw.lang('Writable sharing requires EPL version!'), 'info');
			_widget.set_value('share_ro');
		}

		if (typeof _node != 'undefined')
		{
			const mode = _widget.get_value();
			const mode_label = _widget.select_options.filter(option => option.value == mode)[0]?.label;
			void Et2Dialog.alert(this.egw.lang('Be aware that all attachments will be sent as %1!', mode_label),
				this.egw.lang('Filemode has been switched to %1', mode_label),
				Et2Dialog.WARNING_MESSAGE);
			const content = this.et2.getArrayMgr('content');
			const attachments = this.et2.getWidgetById('attachments');
			for (const i in content.data.attachments)
			{
				if (content.data.attachments[i] == null)
				{
					content.data.attachments.splice(i,1);
					continue;
				}
				content.data.attachments[i]['filemode_icon'] = !content.data.attachments[i]['is_dir'] &&
				(mode == 'share_rw' || mode == 'share_ro') ? 'link' : mode;
			}
			this.et2.setArrayMgr('content', content);
			attachments.set_value({content:content.data.attachments});
		}
		this.addAttachmentPlaceholder();
	}

	/**
	 * Submit on change (VFS)
	 *
	 * @param {egw object} _egw
	 * @param {widget object} _widget
	 */
	submitOnChange(_egw, _widget)
	{
		if (_widget && Object.keys(_widget).length > 0)
		{
			const widgetId = typeof _widget.id !== 'undefined' ? _widget.id : undefined;
			// doc/ai/projects/mail-compose-jmap-migration.md, Step 4 - identity switch handled
			// entirely client-side when the JMAP toggle is on (MailJmap.getIdentities()/
			// composeBodyWithSignature()), no server round-trip at all - mail_compose::compose()'s
			// own "$jmapModeNewCompose" guard already skips server-side signature pre-fill for
			// this same case, so there's nothing stale server-side to fight with here either way.
			if (widgetId === 'mailaccount' && this.isJmapMode)
			{
				void this.updateSignatureForIdentity();
				return;
			}
			switch (widgetId)
			{
				case 'mimeType':
					this.et2.getInstanceManager().submit();
					break;
				default:
					if (Object.keys(_widget.getValue() || {}).length > 0)
					{
						this.et2.getInstanceManager().submit();
					}
			}
		}
	}

	/**
	 * Show/hide all elements matching selector - several compose header rows (Cc/Bcc/Folder/Reply-to/From)
	 * are toggled by class rather than through their own widget, see fieldExpanderInit()/fieldExpander().
	 *
	 * These rows are real <tr> elements with a stylesheet rule hiding them by default
	 * (app.less: "tr.mailComposeCc, ... { display: none; }") - clearing the inline style isn't
	 * enough to show them again (falls straight back to that rule), so "table-row" is set explicitly,
	 * matching what jQuery's .show() used to resolve to for a <tr>.
	 */
	private toggleRowVisibility(selector : string, show : boolean) : void
	{
		document.querySelectorAll(selector).forEach((el : HTMLElement) => el.style.display = show ? 'table-row' : 'none');
	}

	/**
	 * Set expandable fields (Folder, Cc and Bcc) based on their content
	 * - Only fields which have no content should get hidden
	 */
	fieldExpanderInit()
	{
		const widgets = {
			cc:{
				widget:{},
				selector: '.mailComposeCc'
			},
			bcc:{
				widget:{},
				selector: '.mailComposeBcc'
			},
			folder:{
				widget:{},
				selector: '.mailComposeFolder'
			},
			replyto:{
				widget:{},
				selector: '.mailComposeReplyto'
			},
			from:{
				widget:{},
				selector: '.mailComposeFrom'
			}
		};
		const maybe_actions = egw.preference('toggledOnActions', 'mail') ?? [];
		let actions:string[];
		if(maybe_actions === false) return;
		if (typeof maybe_actions === 'string')
			actions = maybe_actions ? maybe_actions.split(',') : [];
		//transform empty actions object to empty array
		if(!Array.isArray(actions))
			actions = Object.values(maybe_actions)
		for(const widget in widgets)
		{
			const expanderBtn = widget + '_expander';
			widgets[widget].widget = this.et2.getWidgetById(widget);
			if(widget === 'from')
				widgets['from'].widget = this.et2.getWidgetById('mailaccount');
			// Add expander button widget to the widgets object
			widgets[expanderBtn] = {widget:this.et2.getWidgetById(expanderBtn)};

			if (widgets[widget].widget && widgets[expanderBtn].widget &&
					(!widgets[widget].widget.value || !widgets[widget].widget.value.length) && actions.indexOf(expanderBtn) < 0 ||
				expanderBtn === 'from_expander' && actions.includes('from_expander') && !this.keepFromExpander)
			{
				widgets[expanderBtn].widget?.set_disabled(false);
				this.toggleRowVisibility(widgets[widget].selector, false);
			}
			else
			{
				this.toggleRowVisibility(widgets[widget].selector, true);
			}
		}
	}

	/**
	 * Display Folder,Cc or Bcc fields in compose popup
	 *
	 * @param {Event} event unused
	 * @param {widget} widget clicked label (Folder, Cc or Bcc) from compose popup. Can be ommited to show all widgets
	 *
	 */
	fieldExpander(event?:undefined,widget?)
	{
		if (typeof widget !='undefined')
		{
			switch (widget.id)
			{
				case 'cc_expander':
					this.toggleRowVisibility(".mailComposeCc", true);
					break;
				case 'bcc_expander':
					this.toggleRowVisibility(".mailComposeBcc", true);
					break;
				case 'folder_expander':
					this.toggleRowVisibility(".mailComposeFolder", true);
					break;
				case 'replyto_expander':
					this.toggleRowVisibility(".mailComposeReplyto", true);
					break;
				case 'from_expander':
					this.toggleRowVisibility('.mailComposeFrom', true);
					this.keepFromExpander = true;
					break;
			}
			// widget's parent is the "..." dropdown listing the not-yet-shown fields - hide it now
			// that one was picked, same as it closes after any other selection
			widget.parentElement.hide()
		}
		else if (typeof widget == "undefined") //show all widgets
		{
			const widgets = {cc:{},bcc:{},folder:{},replyto:{}};

			for(const widget in widgets)
			{
				widgets[widget] = this.et2.getWidgetById(widget);

				if (widgets[widget].get_value() && widgets[widget].get_value().length)
				{
					switch (widget)
					{
						case 'cc':
							this.toggleRowVisibility(".mailComposeCc", true);
							break;
						case 'bcc':
							this.toggleRowVisibility(".mailComposeBcc", true);
							break;
						case 'folder':
							this.toggleRowVisibility(".mailComposeFolder", true);
							break;
						case 'replyto':
							this.toggleRowVisibility(".mailComposeReplyto", true);
							break;
					}
				}
			}
		}
	}

	/**
	 * OnChange callback for recipients:
	 * - make them draggable
	 * - check if we have keys for recipients, if we compose an encrypted mail
	 **/
	recipientsOnChange()
	{
		// if we compose encrypted mail, check if we have keys for new recipient
		if (this.app.mailvelope_editor)
		{
			this.app.mailvelopeGetCheckRecipients().catch(_err =>
			{
				this.egw.message(_err.message, 'error');
			});
		}
	}

	/**
	 * Write / update compose window title with subject
	 *
	 * @param {Node} _node unused parameter
	 * @param {et2_widget} _widget
	 */
	subject2title(_node=undefined, _widget?)
	{
		if (!_widget) _widget = this.et2.getWidgetById('subject');

		if (_widget && _widget.get_value())
		{
			document.title = _widget.get_value();
		}
	}

	/**
	 * displayUploadedFile
	 *
	 * @param {object} tag_info
	 * @param {widget object} widget
	 */
	displayUploadedFile(tag_info, widget)
	{
		const attgrid = this.et2.getArrayMgr("content").getEntry('attachments')[widget.id.replace(/\[name]/,'')];

		// carryForwardAttachments() (Step 4, attachment carry-forward slice) - a bare JMAP blobId
		// reference, no classic tmp_name/uid/partID/folder addressing at all, so neither branch
		// below applies (found live 2026-08-31: the classic-upload branch crashed on
		// attgrid.file.replace(), since carry-forward entries have no .file at all).
		if (attgrid.jmapBlobId)
		{
			void this.displayJmapBlobAttachment(attgrid);
			return;
		}
		if (attgrid.uid && (attgrid.partID||attgrid.folder))
		{
			this.app.displayAttachment(tag_info, widget, true);
			return;
		}
		const get_param: {menuaction : string, tmpname : any, etemplate_exec_id : any, mode? : string} = {
			menuaction: 'mail.mail_compose.getAttachment',	// todo compose for Draft folder
			tmpname: attgrid.tmp_name,
			etemplate_exec_id: this.et2.getInstanceManager().etemplate_exec_id
		};
		let width;
		let height;
		let windowName ='maildisplayAttachment_'+attgrid.file.replace(/\//g,"_");
		switch(attgrid.type.toUpperCase())
		{
			case 'IMAGE/JPEG':
			case 'IMAGE/PNG':
			case 'IMAGE/GIF':
			case 'IMAGE/BMP':
			case 'APPLICATION/PDF':
			case 'TEXT/PLAIN':
			case 'TEXT/HTML':
			case 'TEXT/DIRECTORY':
			case 'TEXT/X-VCARD':
			case 'TEXT/VCARD':
			case 'TEXT/CALENDAR':
			case 'TEXT/X-VCALENDAR':
				let reg = '800x600';
				let reg2;
				// handle calendar/vcard
				if (attgrid.type.toUpperCase()=='TEXT/CALENDAR')
				{
					windowName = 'maildisplayEvent_'+attgrid.file.replace(/\//g,"_");
					reg2 = egw.link_get_registry('calendar');
					if (typeof reg2['view'] != 'undefined' && typeof reg2['view_popup'] != 'undefined' )
					{
						reg = reg2['view_popup'];
					}
				}
				if (attgrid.type.toUpperCase()=='TEXT/X-VCARD' || attgrid.type.toUpperCase()=='TEXT/VCARD')
				{
					windowName = 'maildisplayContact_'+attgrid.file.replace(/\//g,"_");
					reg2 = egw.link_get_registry('addressbook');
					if (typeof reg2['add'] != 'undefined' && typeof reg2['add_popup'] != 'undefined' )
					{
						reg = reg2['add_popup'];
					}
				}
				const w_h =reg.split('x');
				width = w_h[0];
				height = w_h[1];
				break;
			case 'MESSAGE/RFC822':
			default:
				get_param.mode = 'save';
				width = 870;
				height = 600;
				break;
		}
		egw.openPopup(egw.link('/index.php', get_param), width, height, windowName);
	}

	/**
	 * displayUploadedFile()'s JMAP-blob counterpart (carryForwardAttachments() entries) - opens a
	 * sized egw.openPopup() showing the downloaded blob, same convention as displayUploadedFile()'s
	 * own classic branches (never a plain browser tab). Deliberately NOT the Expose lightbox the
	 * message-view/preview uses for images - compose has never used Expose for its own attachment
	 * list (classic locally-staged or message-part attachments don't either, confirmed in
	 * app.displayAttachment()), so this stays consistent with compose's existing behaviour;
	 * unifying compose's OWN attachment clicks with Expose (ralf, 2026-08-31: "it would be nice if
	 * we can make that consistent, so images open in expose everywhere") is a separate follow-up,
	 * not done here. Doesn't attempt the classic vcard/calendar import-into-popup special cases
	 * (those need server-side parsing of the actual file content, not just a raw blob URL) - out of
	 * scope for a carried-forward attachment, which is always a plain file.
	 */
	private async displayJmapBlobAttachment(attgrid : any) : Promise<void>
	{
		const url = await this.app.jmap.downloadBlobUrl(attgrid.jmapProfileID, attgrid.jmapBlobId, attgrid.name, attgrid.type);
		egw.openPopup(url, 800, 600, 'maildisplayAttachment_' + attgrid.tmp_name);
	}

	/**
	 * Set the relevant widget to toolbar actions and submit
	 *
	 * @param {object|boolean} _action toolbar action or boolean value to stop extra call on
	 * compose_integrated_submit
	 */
	submitAction(_action)
	{
		// NOTE: wait === true can never be true (integrateSubmit() only ever returns a Promise,
		// see its own return statement) - this condition is dead code, pre-existing before this
		// typing fix (widened wait's type to fit both possible assignments without changing
		// behavior). Flagged rather than "fixed" since the original intent is unclear.
		let wait : any = Promise.resolve();
		if (_action && (wait = this.integrateSubmit()) && wait === true)
		{
			return false;
		}

		if (this.app.mailvelope_editor)
		{
			const self = this;
			wait.then(() =>
			{
				this.app.mailvelopeGetCheckRecipients().then((_recipients) =>
				{
					return self.app.mailvelope_editor.encrypt(_recipients);
				}).then((_armored) =>
				{
					self.et2.getWidgetById('mimeType').set_value(false);
					self.et2.getWidgetById('mail_plaintext').set_disabled(false);
					self.et2.getWidgetById('mail_plaintext').set_value(_armored);
				}).catch((_err) =>
				{
					self.egw.message(_err.message, 'error');
				});
			});
			return false;
		}
		// doc/ai/projects/mail-compose-jmap-migration.md, Step 1 - try the JMAP-native send path
		// for a plain new message opened via the "jmapCompose" toggle. trySendViaJmap() itself
		// decides eligibility (no attachments, no cross-app integration, no S/MIME) and falls
		// back to false - never an error - for anything it can't yet handle or an
		// unsupported-backend account; a REAL send failure is shown to the user and still
		// resolves true, so the classic postback below never double-sends.
		if (this.isJmapMode)
		{
			wait.then(() => this.trySendViaJmap()).then((sent) =>
			{
				if (sent) return;
				this.et2.getInstanceManager().submit(null, 'Please wait while sending your mail');
			});
			return;
		}

		wait.then(() =>
		{
			this.et2.getInstanceManager().submit(null, 'Please wait while sending your mail');
		});
	}

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 1 - attempt the JMAP-native send path.
	 * Only ever called when isJmapMode (a genuinely new message, no reply/forward/draft context -
	 * see MailApp.composeMessage()'s own guard for why that's guaranteed).
	 *
	 * @returns {Promise<boolean>} true if this send is fully handled (either actually sent via
	 *  JMAP, or a real failure was already shown to the user) - caller must NOT also run the
	 *  classic postback in that case, or the message could be sent twice. false if this compose
	 *  isn't eligible (attachments/integration/S-MIME in play) or the account's backend doesn't
	 *  support JMAP sending yet - caller falls through to the classic postback, silently.
	 */
	private async trySendViaJmap() : Promise<boolean>
	{
		if (!this.jmapEligible()) return false;

		try
		{
			await this.app.jmap.sendNewEmail(String(this.currentProfileID()), await this.currentEmailFields());
		}
		catch (e)
		{
			if (this.isUnsupportedBackendError(e))
			{
				return false;
			}
			this.egw.message(e.message || this.egw.lang('Failed to send message'), 'error');
			return true;
		}
		// the form still carries its unsent-draft content as far as ETemplate's own dirty-tracking
		// is concerned - it never went through ETemplate's own submit(), so closing now would
		// otherwise trip the "unsaved changes" beforeunload prompt despite the message having
		// already sent successfully (found live 2026-08-27)
		this.et2.getInstanceManager().skip_close_prompt();
		window.close();
		return true;
	}

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 1/3 - no cross-app integration, no
	 * S/MIME yet, for an actual SEND. Attachments are eligible as of Step 3 EXCEPT: a "share
	 * instead of attach" filemode (a Vfs\Sharing link, not a real attachment upload - out of
	 * scope) or one carried forward from an original message (has uid+partID/folder - needs
	 * reply/forward, Step 4, not fully built yet). "autosave... has the same unimplemented
	 * features as our current sending" (ralf, 2026-08-27) - true for attachments, but NOT for the
	 * integration/S-MIME toggle check below (see forSend's docblock - found live 2026-08-27 via a
	 * confirmed, reproducible case: to_infolog checked -> autosave fell back to the classic path
	 * -> crashed, the same raw-IMAP-fallthrough bug class as elsewhere in this codebase, since
	 * Api\Mail::appendMessage()/folderExists() are unguarded raw IMAP calls with no JMAP-native
	 * fast path - a JMAP-only account's classic draft-save can never actually work, so falling
	 * back to it for a toggle that doesn't even affect the SAVED DRAFT's own content was
	 * strictly worse than just not blocking on it).
	 *
	 * @param forSend true (the default, matching trySendViaJmap()'s use) also checks the
	 *  composeToolbar's integration/S-MIME toggles - those affect what gets built at SEND time
	 *  only, not a draft's own JMAP representation, so trySaveDraftViaJmap() passes false: a
	 *  draft with, say, "create an InfoLog entry" checked is still just plain body+recipients as
	 *  far as the SAVED draft itself is concerned - that toggle only matters again once actually
	 *  sent (still classic-only, unaffected by this).
	 */
	private jmapEligible(forSend : boolean = true) : boolean
	{
		if (!this.isJmapMode || this.app.mailvelope_editor)
		{
			return false;
		}
		const attachments : any[] = Object.values(this.et2.getArrayMgr('content').getEntry('attachments') || {});
		if (attachments.length)
		{
			const filemode = this.et2.getWidgetById('filemode')?.get_value();
			if (filemode && filemode !== 'attach')
			{
				return false;
			}
			if (attachments.some((a) => a.uid && (a.partID || a.folder)))
			{
				return false;
			}
		}
		if (!forSend)
		{
			return true;
		}
		const toolbar : any = this.et2.getWidgetById('composeToolbar');
		const blockingToggle = ['to_tracker', 'to_infolog', 'to_calendar', 'smime_sign', 'smime_encrypt'].find(
			(id) => toolbar?.getWidgetById(id)?.get_value());
		return !blockingToggle;
	}

	private currentProfileID() : string
	{
		return String(this.et2.getWidgetById('mailaccount')?.get_value());
	}

	private currentBodyWidget()
	{
		const isHtml = this.et2.getWidgetById('mimeType')?.get_value() !== false;
		return this.et2.getWidgetById(isHtml ? 'mail_htmltext' : 'mail_plaintext');
	}

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 4, first slice - dispatches to
	 * bootstrapReply() for a JMAP-mode single reply/reply-with-attachments/inline-forward
	 * (identified purely from this popup's own URL params, same technique isJmapMode already uses
	 * - "&from=reply&id=<rowId>", added by MailApp.composeMessage() only when the "jmapCompose"
	 * toggle is on), else the existing bootstrapSignature() for a genuinely new blank compose.
	 * "reply_attachments" (added 2026-08-31, attachment carry-forward slice) is the same reply
	 * plus carrying the original message's own attachments along - matching the classic code's own
	 * `getForwardData()` + `getReplyData()` fallthrough composition of the same two features.
	 * "forward" (added 2026-08-31 too, single-message inline forward only) reuses that exact same
	 * fetch+quote+attachment-carry-forward machinery again - ralf: "same thing as reply with
	 * attachments, just not setting To" - bootstrapReply()'s own `mode` param controls the
	 * remaining differences (subject prefix, to/cc, threading headers). "forwardasattach" (added
	 * 2026-08-31 too, one or more messages, dispatches to bootstrapForwardAsAttachment() instead -
	 * no quoted body at all, just the whole message(s) attached as message/rfc822). "reply_all"
	 * (added 2026-08-31 too) reuses the exact same fetch/quote/threading-header/identity-matching
	 * machinery as plain reply - only the to/cc computation differs (bootstrapReply()'s own `mode`
	 * param), matching classic getReplyData()'s own mode='all' 3-loop algorithm: reply-to-or-from
	 * (both, if they differ) + original to (minus the account's own addresses) into `to`, original
	 * cc (same exclusions, plus anything already in `to`) into `cc`. "Merge this forward into an
	 * already-open compose window" (egw.openWithinWindow()'s own multi-popup picker calling that
	 * OTHER window's live setCompose(), not a URL load at all) is architecturally out of scope here
	 * - composeMessage() still sets "&jmap=1" for that case (harmless: it only matters if
	 * openWithinWindow() actually opens a fresh popup instead), but isJmapMode is fixed at that
	 * OTHER window's own original load time and unrelated to this action, so it's a no-op there,
	 * same as before this slice.
	 */
	private async bootstrapCompose() : Promise<void>
	{
		if (!this.isJmapMode) return;
		const params = new URLSearchParams(window.location.search);
		const from = params.get('from') as 'reply' | 'reply_attachments' | 'reply_all' | 'forward' | null;
		if (from === 'forward' && params.get('mode') === 'forwardasattach')
		{
			await this.bootstrapForwardAsAttachment((params.get('id') || '').split(',').filter(Boolean));
		}
		else
		{
			const sourceId = (from === 'reply' || from === 'reply_attachments' || from === 'reply_all' || from === 'forward') ? params.get('id') : null;
			if (sourceId)
			{
				await this.bootstrapReply(sourceId, from);
			}
			else
			{
				await this.bootstrapSignature();
			}
		}
		// doc/ai/projects/mail-compose-jmap-migration.md, Step 4 - the widget set_value() calls
		// above (recipient/subject/body/signature) mark the form dirty exactly like a real user
		// edit would, unlike the classic path's server-rendered initial content, which is the
		// dirty-tracker's own clean baseline from the start - found live 2026-08-27 (ralf: closing
		// a freshly-opened, untouched reply popup showed the "unsaved changes" prompt).
		// etemplate2.resetDirty() (extracted from load()'s own identical post-load reset, ralf's
		// suggestion) resets every input widget's dirty flag back to clean once this bootstrap's
		// own programmatic population is done, so only a REAL subsequent user edit trips the
		// prompt again.
		// carryForwardAttachments() (if it ran) rebuilds the attachments grid's rows via
		// set_value(), which creates brand new row widgets (incl. an et2_IInput delete button per
		// row) - that rebuild's own child-widget creation/upgrade isn't awaited by set_value()
		// itself (a plain void-returning legacy et2_grid method, no updateComplete to await), so a
		// resetDirty() called immediately after can run BEFORE those new widgets finish settling,
		// missing them entirely - they never get their clean baseline, so the close-prompt trips
		// even though nothing was actually edited (found live 2026-08-31, reply-with-attachments
		// only - every other set_value() call in this method targets an already-existing widget,
		// no comparable gap). One extra macrotask is enough for anything already queued to finish.
		await new Promise((resolve) => setTimeout(resolve, 0));
		this.et2.getInstanceManager().resetDirty();
	}

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 4 - fetches the original message via
	 * JMAP (MailJmap.fetchForReply()) and populates subject/body (+ recipients/threading-headers
	 * for an actual reply) for all three JMAP-mode "compose from an existing message" cases:
	 * plain reply, reply-with-attachments, and single-message inline forward.
	 * mail_compose::compose()'s own matching "$jmapReplySkip" guard already skipped the classic
	 * getComposeFrom()/getReplyData() fetch+derive entirely for all three (found live 2026-08-27:
	 * that raw IMAP fetch could take ~20s against this account's backend, with no real safety-net
	 * value either - if the JMAP fetch fails, the same backend's classic IMAP fetch failing/being
	 * just as slow is at least as likely, not a genuinely independent fallback) - so unlike
	 * bootstrapSignature()'s new-compose case, there is now NO server-rendered content behind this
	 * at all. A JMAP failure here is surfaced as a visible error rather than silently leaving a
	 * blank compose that looks like an intentional new message (missing "Re:"/"[FWD]", no
	 * recipient, no quote) - never worth risking that being mistaken for done.
	 *
	 * @param mode 'reply': recipients + threading headers set, no attachments carried. 'forward':
	 *  same fetch/quote/identity-matching, but no recipients or threading headers - forwarding
	 *  isn't a reply-thread continuation - and "[FWD] " subject prefix instead of "Re: ", matching
	 *  classic getForwardData()'s own convention (which, for its own inline mode, is itself built
	 *  by calling getReplyData() for the body/quote and then discarding its to/cc/in-reply-to
	 *  side effects - the same composition this reuses). 'reply_attachments': same as 'reply' plus
	 *  attachment carry-forward. Attachments are ALSO carried forward for 'forward' (ralf,
	 *  2026-08-31: "same thing as reply with attachments, just not setting To") - matching
	 *  classic's own getForwardData() non-asmail branch, which populates attachments unconditionally.
	 */
	private async bootstrapReply(sourceId : string, mode : 'reply' | 'reply_attachments' | 'reply_all' | 'forward') : Promise<void>
	{
		const context = await this.app.jmap.fetchForReply(sourceId);
		if (!context)
		{
			this.egw.message(this.egw.lang('Failed to load original message(s)'), 'error');
			return;
		}
		const isForward = mode === 'forward';
		// classic mail_compose.inc.php's own $isReply flag (getComposeFrom()) is set for an inline
		// forward too, not just a true reply - it really means "quote-style compose", governing
		// signature placement (applySignatureForCurrentIdentity() below), not literally "is a reply".
		this.isReplyCompose = true;
		this.replyThreadingHeaders = isForward ? null : {inReplyTo: context.inReplyTo, references: context.references};

		const identities = await this.selectIdentityForRecipients(context);

		let subject : string;
		if (isForward)
		{
			// always prepended, no "already has [FWD]" dedup check - matches getForwardData()'s
			// own unconditional "[FWD] " . ... (unlike reply's own "don't double up Re:" check)
			subject = '[FWD] ' + context.subject;
		}
		else
		{
			const formatAddress = (a : {name? : string, email : string}) => a.name ? `${a.name} <${a.email}>` : a.email;
			if (mode === 'reply_all')
			{
				// matches getReplyData()'s own 3-loop mode='all' logic exactly: the primary
				// reply-to-or-from target is ALWAYS included (unlike plain reply, which only ever
				// uses replyTo when present) - if Reply-To differs from From, both end up in `to`,
				// same as classic. original to/cc are added minus anything already in `to`/`cc`
				// and minus any of the account's OWN addresses (across every identity, not just
				// the currently-selected one) - never reply to/cc yourself.
				const ownEmails = new Set(identities.map((i) => String(i.email).toLowerCase()));
				const seen = new Set<string>();
				const to : {name? : string, email : string}[] = [];
				const cc : {name? : string, email : string}[] = [];
				const addUnique = (list : {name? : string, email : string}[], target : {name? : string, email : string}[]) =>
				{
					for (const a of list)
					{
						const key = a.email.toLowerCase();
						if (ownEmails.has(key) || seen.has(key)) continue;
						seen.add(key);
						target.push(a);
					}
				};
				if (context.replyTo?.length) addUnique(context.replyTo, to);
				addUnique(context.from, to);
				addUnique(context.to, to);
				addUnique(context.cc, cc);
				this.et2.getWidgetById('to')?.set_value(to.map(formatAddress));
				if (cc.length) this.et2.getWidgetById('cc')?.set_value(cc.map(formatAddress));
			}
			else
			{
				const to = (context.replyTo?.length ? context.replyTo : context.from).map(formatAddress);
				this.et2.getWidgetById('to')?.set_value(to);
			}
			// "Re: " is hardcoded, not translated, matching the classic getReplyData()'s own convention
			subject = /^re:/i.test(context.subject.trim()) ? context.subject : 'Re: ' + context.subject;
		}
		this.et2.getWidgetById('subject')?.set_value(subject);

		const isHtml = context.mimeType === 'html';
		this.et2.getWidgetById('mimeType')?.set_value(isHtml);
		const quoted = this.app.jmap.quoteOriginalMessage(context);
		await this.applySignatureForCurrentIdentity(quoted, this.isReplyCompose);

		if ((mode === 'reply_attachments' || isForward) && context.attachments.length)
		{
			this.carryForwardAttachments(context.attachments, context.profileID);
		}
	}

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 4 - "Forward as attachment"
	 * ($_GET['mode']==='forwardasattach'), one or more source messages, each attached whole as a
	 * message/rfc822 file rather than quoted inline - matches classic getForwardData()'s own asmail
	 * branch (one addMessageAttachment(..., 'MESSAGE/RFC822', ...) call per forwarded message), but
	 * via MailJmap.fetchForForwardAsAttachment()'s blobId reference instead of a classic
	 * uid/partID/folder-addressed attachment - no quoted body, no to/cc/threading-headers at all
	 * (a forward-as-attachment is otherwise a genuinely blank new message).
	 *
	 * Subject for multiple messages: classic getForwardData() overwrites sessionData['subject']
	 * once per loop iteration, so its own final subject is just the LAST message's own subject -
	 * an accident of the loop, not a deliberate design (ralf, 2026-08-31: use the FIRST message's
	 * subject instead here, a small deliberate improvement over that classic quirk).
	 */
	private async bootstrapForwardAsAttachment(sourceIds : string[]) : Promise<void>
	{
		if (!sourceIds.length)
		{
			this.egw.message(this.egw.lang('Failed to load original message(s)'), 'error');
			return;
		}
		const results = await Promise.all(sourceIds.map((id) => this.app.jmap.fetchForForwardAsAttachment(id)));
		const messages = results.filter((r) : r is NonNullable<typeof r> => r !== null);
		if (!messages.length)
		{
			this.egw.message(this.egw.lang('Failed to load original message(s)'), 'error');
			return;
		}

		this.isReplyCompose = true;
		const subject = '[FWD] ' + messages[0].subject;
		this.et2.getWidgetById('subject')?.set_value(subject);

		const attachments = messages.map((m) => ({
			blobId: m.blobId,
			name: (m.subject || this.egw.lang('no subject')) + '.eml',
			type: 'message/rfc822',
			size: m.size,
		}));
		this.carryForwardAttachments(attachments, messages[0].profileID);

		// no quoted body - still apply the normal new-message signature (classic getForwardData()
		// never suppresses it for this mode either, $suppressSigOnTop stays false)
		await this.applySignatureForCurrentIdentity('', this.isReplyCompose);
	}

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 4, attachment carry-forward slice -
	 * populate the attachments grid straight from the original message's own blobIds (already
	 * uploaded, on the SAME account - see MailJmap.fetchForReply()'s own docblock for why no
	 * download+reupload round-trip is needed here, unlike Step 3's uploadAttachmentsViaJmap()
	 * for a genuinely locally-staged file), mirroring checkSharingFilemode()'s own established
	 * "mutate the array manager, then re-push into the grid widget" pattern rather than the
	 * classic postback-based getAttachment()/addAttachment() cycle.
	 *
	 * Each row still needs a `tmp_name`-shaped id (the grid template's delete button embeds it,
	 * `delete[$row_cont[tmp_name]]`) for the classic per-row delete mechanism to keep working
	 * unchanged - a synthetic "jmap:<blobId>" stands in for a real staged-file tmp_name.
	 * `jmapBlobId` is the new marker `uploadAttachmentsViaJmap()` checks to skip the
	 * fetch+reupload step for a row that's already a ready-to-use JMAP blob reference.
	 */
	private carryForwardAttachments(attachments : {blobId : string, name : string, type : string, size : number}[], profileID : string) : void
	{
		const content = this.et2.getArrayMgr('content');
		content.data.attachments = [
			...(content.data.attachments || []),
			...attachments.map((a) => ({
				tmp_name: 'jmap:' + a.blobId,
				jmapBlobId: a.blobId,
				// the account the blob actually lives on (the message being replied to) - may
				// differ from currentProfileID() later if the user switches identity, kept per-row
				// so displayUploadedFile() always downloads from the right place.
				jmapProfileID: profileID,
				name: a.name,
				type: a.type,
				size: a.size,
				// plain "attach" filemode's own icon, matching what a genuine upload gets server-side
				// (mail_compose.inc.php's own filemode_icon computation) - never per-mimetype
				filemode_icon: 'attach',
				filemode_title: '',
			})),
		];
		this.et2.setArrayMgr('content', content);
		content.data.attachmentsBlockTitle = content.data.attachments.length + ' ' + this.egw.lang('Attachments');
		const attachmentsWidget = this.et2.getWidgetById('attachments');
		attachmentsWidget?.set_value({content: content.data.attachments});
		// found live 2026-08-31: rows DID render into the DOM correctly (set_value() above works
		// fine), but stayed invisible - two separate ancestors both start disabled/collapsed for
		// an initial empty/no-attachments content and never got un-disabled: the et2-details
		// itself (Shoelace SlDetails under the hood, "!@attachments") AND, one level further up,
		// the WHOLE "et2_file mailUploadSection" box ("@no_griddata", server-computed as
		// `empty($content['attachments'])` - mail_compose.inc.php:1423) wrapping the attachments
		// details AND the filemode/expiration/password row below it. uploadStart() only ever
		// un-disables the et2-details (never had to touch the outer box, since a real upload's own
		// postback re-renders the whole popup with both flags correctly re-evaluated from
		// non-empty content from the start) - this client-only population never gets that server
		// re-render. `disabled`/`title` are one-shot expression bindings evaluated only at initial
		// render, not reactive to a later array-manager mutation either - set them directly on the
		// widgets instead of relying on @attachments/@no_griddata/@attachmentsBlockTitle
		// re-evaluating.
		const detailsWidget : any = attachmentsWidget?.getParent();
		detailsWidget?.set_disabled(false);
		const uploadSectionWidget : any = detailsWidget?.getParent();
		uploadSectionWidget?.set_disabled(false);
		if (detailsWidget)
		{
			// toggleOnHover="true" already reveals the body on hover - it should stay CLOSED on
			// load (ralf, 2026-08-31: "it should only open on hover, not permanent on first load"),
			// so `title`/`open` are deliberately left alone here.
			detailsWidget.title = content.data.attachmentsBlockTitle;
		}
		// un-disabling the whole "mailUploadSection" box above also exposes its OTHER child, the
		// "Send files as" filemode/expiration/password row (filemodeRow) - re-hide that one
		// specifically, it's not meaningful for carry-forward attachments (they're bare JMAP blob
		// references, not real VFS-shareable files) and jmapEligible() requires filemode==='attach'
		// to stay eligible, so exposing a control that could change it away would be actively
		// misleading here.
		this.et2.getWidgetById('filemodeRow')?.set_disabled(true);
		// the collapsed-details "summary" preview grid has NO id (deliberately, in the original
		// classic template - giving it one would create its OWN array-manager namespace/perspective
		// (et2_core_widget.ts's checkCreateNamespace(): any widget WITH an id always gets one),
		// breaking its row template's root-scoped "@attachments[0][...]" bindings, which are meant
		// to read the TRUE root content regardless of nesting - found live 2026-08-31 after trying
		// exactly that and getting a permanently empty summary for BOTH this slice and (if left in)
		// classic reply_attachments/forward). A direct loadFromXML() rebuild (found by walking the
		// unnamed tree instead of getWidgetById()) was tried next, but stayed empty too - this
		// grid's own getArrayMgr('content') is apparently NOT the same live instance
		// this.et2.setArrayMgr('content', content) updates (etemplate2's own controller-level
		// managers vs. the actual widget tree's delegated ones aren't the same object, it seems -
		// unconfirmed without deeper framework digging, and not worth more time chasing for a
		// purely cosmetic preview). Given direct set_value()-style population only reliably works
		// for widgets THIS code populates by explicit argument (the real "attachments" grid above),
		// not via array-manager re-evaluation - simplest robust fix: hide the classic (permanently
		// stale-for-this-case) preview outright, in favour of a plain, fully JS-driven replacement.
		const summaryBox : any = detailsWidget?.getChildren()
			?.find((c : any) => c.getDOMNode?.()?.getAttribute?.('slot') === 'summary');
		const summaryGrid : any = summaryBox?.getChildren?.()?.[0];
		summaryGrid?.set_disabled(true);
		// 1st attachment's own name (replacing the classic grid's job above, since it's hidden),
		// growing to fill the row (attachmentsSummaryName, "flex:1" in the .xet) - plus the same
		// "+N" convention as app.ts's own attachmentsBlock preview (`attachmentsBlockTitle =
		// _data.length > 1 ? \`+${_data.length-1}\` : ''`, for a RECEIVED message's attachment
		// list), right-aligned and bold, the count only - not the filename.
		this.et2.getWidgetById('attachmentsSummaryName')?.set_value(content.data.attachments[0].name);
		const moreCount = content.data.attachments.length - 1;
		this.et2.getWidgetById('attachmentsMoreText')?.set_value(moreCount > 0 ? '+' + moreCount : '');
	}

	/**
	 * Select the identity the original message was actually addressed to - matching one of the
	 * account's own identity email addresses against the reply target's To/Cc - rather than
	 * leaving whatever identity was last used/configured as default. Neither the classic
	 * mail_compose.inc.php nor Step 1's new-compose path do this at all
	 * (get_preferred_identity() only ever honours the 'last-used'/'default' preference, never the
	 * message actually being replied to) - genuinely useful for an account with several
	 * aliases/identities (eg. a 13-identity test account), replying "as" whichever address
	 * actually received the message rather than whichever identity happened to be selected last.
	 *
	 * Two edge cases (ralf, 2026-08-27):
	 * - No address matches at all (eg. the user was only bcc'ed) - do nothing, leaving the
	 *   widget's already-classically-rendered value, which is itself already the "last-used"
	 *   identity (mail_compose.inc.php's LastSignatureIDUsed preference, read back as the default
	 *   for every new compose unless a different `defaultIdentity` pref is configured).
	 * - More than one identity matches (eg. several aliases were all on the To/Cc) - prefer
	 *   keeping the current (again, "last-used") selection if it happens to be among the matches,
	 *   closest to previous behaviour, rather than an arbitrary pick among equally-valid matches.
	 *
	 * Silently does nothing if identities can't be fetched either - same
	 * never-worth-blocking-compose-on philosophy as applySignatureForCurrentIdentity().
	 */
	/**
	 * Returns the fetched identities list (empty on failure) - also used by bootstrapReply()'s
	 * 'reply_all' mode to filter the account's own addresses out of the computed to/cc.
	 */
	private async selectIdentityForRecipients(context : JmapReplyContext) : Promise<any[]>
	{
		let identities : any[];
		try
		{
			identities = await this.app.jmap.getIdentities(context.profileID);
		}
		catch (e)
		{
			return [];
		}
		const recipientEmails = new Set([...context.to, ...context.cc].map((a) => a.email.toLowerCase()));
		const matches = identities.filter((i) => recipientEmails.has(i.email.toLowerCase()));
		if (matches.length)
		{
			const [, currentIdentId] = String(this.et2.getWidgetById('mailaccount')?.get_value() ?? '').split(':', 2);
			const preferred = matches.find((i) => i.id === currentIdentId) ?? matches[0];
			this.et2.getWidgetById('mailaccount')?.set_value(`${context.profileID}:${preferred.id}`);
		}
		return identities;
	}

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 4 - bootstrap the signature for a
	 * genuinely new blank compose opened with the JMAP toggle on. mail_compose::compose()'s own
	 * "$jmapModeNewCompose" guard already skipped server-side signature insertion for exactly
	 * this case (mirroring mail_ui::displayMessage()'s "minimal content, client fetches/builds
	 * the rest" pattern rather than pre-computing this server-side), so the body widget starts
	 * with whatever the template itself set (normally empty) - passed through as the pristine
	 * base rather than assumed empty, in case that ever changes.
	 */
	private async bootstrapSignature() : Promise<void>
	{
		if (!this.isJmapMode) return;
		await this.applySignatureForCurrentIdentity(String(this.currentBodyWidget()?.get_value() ?? ''));
	}

	/**
	 * "From"/identity dropdown's change handler while isJmapMode (see submitOnChange()) - re-
	 * derive the pristine (signature-stripped) body from the widget by plain substring match
	 * against insertedSignatureBlock (the exact text bootstrapSignature()/this method's own
	 * previous run inserted), then insert the newly-selected identity's signature. Deliberately
	 * NOT the classic marker/regex relocate hack - see composeBodyWithSignature()'s docblock.
	 */
	private async updateSignatureForIdentity() : Promise<void>
	{
		if (!this.isJmapMode) return;
		const current = String(this.currentBodyWidget()?.get_value() ?? '');
		let pristine = current;
		if (this.insertedSignatureBlock)
		{
			if (this.signaturePlacement === 'below' && current.endsWith(this.insertedSignatureBlock))
			{
				pristine = current.slice(0, current.length - this.insertedSignatureBlock.length);
			}
			else if (this.signaturePlacement === 'top' && current.startsWith(this.insertedSignatureBlock))
			{
				pristine = current.slice(this.insertedSignatureBlock.length);
			}
			// else: can't confidently locate the previously-inserted signature (the user edited
			// around/inside it) - leave the current value as pristine rather than guessing; this
			// skips re-insertion once instead of risking a corrupted/duplicated signature
		}
		await this.applySignatureForCurrentIdentity(pristine, this.isReplyCompose);
	}

	/**
	 * Fetch the "From" dropdown's currently-selected identity (value is "acc_id:ident_id", same
	 * shape mail_compose.inc.php's own compose() splits server-side) and insert its signature
	 * into pristineBody via MailJmap.composeBodyWithSignature(), tracking the inserted substring
	 * (insertedSignatureBlock/signaturePlacement) for a later updateSignatureForIdentity() call.
	 * Silently does nothing on any failure (no account selected yet, identity fetch failed, ...) -
	 * signature insertion is a nice-to-have for this first slice, never worth blocking compose on.
	 *
	 * @param pristineBody body WITHOUT any signature - for a reply this is the already-quoted
	 *  (attribution + blockquote) body, not empty
	 * @param isReply passed straight through to composeBodyWithSignature() - never add an empty
	 *  leading line above an already-non-empty (quoted) body
	 */
	private async applySignatureForCurrentIdentity(pristineBody : string, isReply : boolean = false) : Promise<void>
	{
		const mailaccountValue = this.et2.getWidgetById('mailaccount')?.get_value();
		const [profileID, identId] = String(mailaccountValue ?? '').split(':', 2);
		if (!profileID) return;

		let identities : any[];
		try
		{
			identities = await this.app.jmap.getIdentities(profileID);
		}
		catch (e)
		{
			console.error('MailCompose.applySignatureForCurrentIdentity(): failed to fetch identities', e);
			return;
		}
		const identity = identities.find((i) => i.id === identId) ?? identities[0];
		if (!identity) return;

		const mimeType : 'html' | 'plain' = this.et2.getWidgetById('mimeType')?.get_value() !== false ? 'html' : 'plain';
		const insertPref = this.egw.preference('insertSignatureAtTopOfMessage', 'mail');
		const placement : 'top' | 'below' | 'none' =
			insertPref === '1' ? 'top' : insertPref === 'no_belowaftersend' ? 'none' : 'below';
		const disableRuler = !!this.egw.preference('disableRulerForSignatureSeparation', 'mail');

		const result = MailJmap.composeBodyWithSignature(pristineBody, mimeType, identity, {placement, disableRuler, isReply});
		this.signaturePlacement = placement;
		this.insertedSignatureBlock = placement === 'below' ? result.slice(pristineBody.length) :
			placement === 'top' ? result.slice(0, result.length - pristineBody.length) : '';

		this.currentBodyWidget()?.set_value(result);
	}

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 3 - fetch each classically-staged
	 * attachment's raw bytes (same menuaction displayUploadedFile() already uses to preview one)
	 * and upload it as a JMAP blob. Reuses the existing upload widget/staging entirely - no new
	 * upload UI, just a new step between "already staged server-side" and "referenced by blobId
	 * in the JMAP Email".
	 */
	private async uploadAttachmentsViaJmap(profileID : string) : Promise<any[]>
	{
		const attachments : any[] = Object.values(this.et2.getArrayMgr('content').getEntry('attachments') || {});
		const etemplateExecId = this.et2.getInstanceManager().etemplate_exec_id;
		return Promise.all(attachments.map(async(attachment) =>
		{
			// carryForwardAttachments() (Step 4, attachment carry-forward slice) - already a real
			// JMAP blob on this same account (from the message being replied to), no
			// download+reupload round-trip needed, unlike a genuinely locally-staged file below.
			if (attachment.jmapBlobId)
			{
				return {blobId: attachment.jmapBlobId, name: attachment.name, type: attachment.type, size: attachment.size};
			}
			const cached = this.uploadedAttachmentBlobs.get(attachment.tmp_name);
			if (cached)
			{
				return cached;
			}
			const url = this.egw.link('/index.php', {
				menuaction: 'mail.mail_compose.getAttachment',
				tmpname: attachment.tmp_name,
				etemplate_exec_id: etemplateExecId,
			});
			const response = await fetch(url, {credentials: 'same-origin'});
			if (!response.ok)
			{
				throw new Error(this.egw.lang('Failed to read attachment %1', attachment.name));
			}
			const blob = await response.blob();
			const uploaded = await this.app.jmap.uploadAttachment(profileID, blob, attachment.name, attachment.type);
			this.uploadedAttachmentBlobs.set(attachment.tmp_name, uploaded);
			return uploaded;
		}));
	}

	private async currentEmailFields()
	{
		const isHtml = this.et2.getWidgetById('mimeType')?.get_value() !== false;
		const hasAttachments = Object.keys(this.et2.getArrayMgr('content').getEntry('attachments') || {}).length > 0;
		return {
			to: this.et2.getWidgetById('to')?.get_value(),
			cc: this.et2.getWidgetById('cc')?.get_value(),
			bcc: this.et2.getWidgetById('bcc')?.get_value(),
			subject: this.et2.getWidgetById('subject')?.get_value(),
			body: this.et2.getWidgetById(isHtml ? 'mail_htmltext' : 'mail_plaintext')?.get_value(),
			isHtml,
			attachments: hasAttachments ? await this.uploadAttachmentsViaJmap(this.currentProfileID()) : undefined,
			// doc/ai/projects/mail-compose-jmap-migration.md, Step 4 - set once by bootstrapReply(),
			// undefined for a plain new-message compose
			inReplyTo: this.replyThreadingHeaders?.inReplyTo ?? undefined,
			references: this.replyThreadingHeaders?.references ?? undefined,
		};
	}

	/**
	 * this.app.jmap may now be the OPENER's own instance (see MailApp.jmap's own docblock) - an
	 * error it throws is an instance of ITS realm's JmapUnsupportedBackendError class, not this
	 * popup's own separately-loaded one, so `instanceof` here would always be false even for a
	 * real match (same pitfall as feedback_cross_realm_instanceof). Compare by name instead,
	 * which survives crossing the window boundary.
	 */
	private isUnsupportedBackendError(e : any) : boolean
	{
		return e?.constructor?.name === 'JmapUnsupportedBackendError';
	}

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md, Step 1 - attempt the JMAP-native draft-save
	 * path (autosave, "Save as Draft", and "Save as Draft and Print" - print() only needs *a*
	 * valid row-id, and the one built below is format-compatible with the classic path's own, so
	 * there's no reason to exclude it). See trySendViaJmap()'s own docblock for the true/false
	 * contract - same shape here.
	 */
	private async trySaveDraftViaJmap(action : string) : Promise<boolean>
	{
		if (!this.jmapEligible(false)) return false;

		let result : {emailId : string, mailboxId : string};
		try
		{
			result = await this.app.jmap.saveDraft(this.currentProfileID(), await this.currentEmailFields(), this.jmapDraftEmailId);
		}
		catch (e)
		{
			if (this.isUnsupportedBackendError(e))
			{
				return false;
			}
			this.egw.message(e.message || this.egw.lang('Failed to save draft'), 'error');
			return true;
		}
		this.jmapDraftEmailId = result.emailId;
		const content = this.et2.getArrayMgr('content');
		const rowId = `${this.egw.user('account_id')}::${this.currentProfileID()}::${result.mailboxId}::${result.emailId}`;
		content.data.lastDrafted = rowId;
		this.et2.setArrayMgr('content', content);
		(this.et2.getWidgetById('lastDrafted') as any)?.set_value(rowId);
		if (action === 'button[saveAsDraftAndPrint]')
		{
			this.print('mail::' + rowId);
			this.egw.message(this.egw.lang('Message saved'));
		}
		else if (action !== 'autosaving')
		{
			this.egw.message(this.egw.lang('Message saved'));
		}
		return true;
	}

	/**
	 * This function runs before client submit (send) mail to server
	 * and takes care of mail integration modules to popup entry selection
	 * dialog to give user a choice to which entry of selected app the compose
	 * should be integereated.
	 *
	 * @returns {Promise<void>}
	 */
	async integrateSubmit()
	{
		const wait = [];
		const integApps = ['to_tracker', 'to_infolog', 'to_calendar'];
		const subject = this.et2.getWidgetById('subject');
		const toolbar = this.et2.getWidgetById('composeToolbar');
		const to_integrate_ids = this.et2.getWidgetById('to_integrate_ids');
		let integWidget : any = {};
		for (let index = 0; index < integApps.length; index++)
		{
			integWidget = index < integApps.length ? toolbar.getWidgetById(integApps[index]) : null;
			const action = toolbar.actions.find((action) => action.id == integApps[index]);
			if (integWidget && integWidget.value && action &&
				typeof action.data['mail_import'] != 'undefined' &&
				typeof action.data['mail_import']['app_entry_method'] != 'undefined')
			{
				const mail_import_hook = action.data['mail_import']['app_entry_method'];
				const title = egw.lang('Select') + ' ' + egw.lang(integApps[index]) + ' ' + (egw.link_get_registry(integApps[index], 'entry') ? egw.link_get_registry(integApps[index], 'entry') : egw.lang('entry'));

				wait.push(new Promise<void>((resolve) =>
				{
					this.app.integrateCheckAppEntry(title, integApps[index].substr(3), subject.get_value(), '', mail_import_hook, (args) =>
					{
						const oldValue = to_integrate_ids.get_value() || [];
						to_integrate_ids.set_value([integApps[index] + ":" + args.entryid, ...oldValue]);
						resolve();
					});
				}));
			}
		}
		return Promise.all(wait);
	}

	/**
	 * Set the selected checkbox action
	 *
	 * @param {type} _action selected toolbar action with checkbox
	 * @returns {undefined}
	 */
	setToggle(_action)
	{
		const widget = this.et2?.getWidgetById(_action.id) || this.app?.et2?.getWidgetById(_action.id);
		if (widget && _action?.checkbox)
		{
			widget.set_value(_action.checked?"on":"off");
		}
	}

	/**
	 * Set the selected priority value
	 * @param {type} _action selected action
	 * @returns {undefined}
	 */
	priorityChange(_action)
	{
		const widget = this.et2.getWidgetById ('priority');
		if (widget)
		{
			widget.set_value(_action.id);
		}
	}

	/**
	 * Triger relative widget via its toolbar identical action
	 * @param {type} _action toolbar action
	 */
	triggerWidget(_action)
	{
		const helpers = this.et2.querySelector(".mailComposeHeaderSection") as any;
		const widget = helpers.getWidgetById(_action.id);
		if (widget)
		{
			switch(widget.id)
			{
				case 'uploadForCompose':
				case 'selectFromVFSForCompose':
					widget.show();
					break;
				default:
					widget.click();
			}
		}
	}

	/**
	 * Save drafted compose as eml file into VFS
	 * @param {type} _action action
	 */
	saveDraft2fm(_action)
	{
		const content = this.et2.getArrayMgr('content').data;
		const subject = this.et2.getWidgetById('subject');
		const elem = {0:{id:"", subject:""}};
		const self = this;
		if (typeof content != 'undefined' && content.lastDrafted && subject)
		{
			elem[0].id = content.lastDrafted;
			elem[0].subject = subject.get_value();
			this.app.save2Fm(_action, elem);
		}
		else // need to save as draft first
		{
			this.saveAsDraft(null, 'autosaving').then(() =>{
				self.saveDraft2fm(_action);
			}, () =>{
				void Et2Dialog.alert('You need to save the message as draft first before to be able to save it into VFS', 'Save to filemanager', 'info');
			});
		}
	}

	/**
	 * Save as Draft (VFS)
	 * -handel both actions save as draft and save as draft and print
	 *
	 * @param {egwAction} _egw_action
	 * @param {array|string} _action string "autosaving", if that triggered the action
	 *
	 * @return Promise
	 */
	saveAsDraft(_egw_action, _action)
	{
		const self = this;
		return new Promise<void>((_resolve, _reject) =>{
			const content = self.et2.getArrayMgr('content').data;
			let action = _action;
			if (_egw_action && _action !== 'autosaving')
			{
				action = _egw_action.id;
			}

			Object.assign(content, {...self.et2.getInstanceManager().getValues(self.et2, true), attachments: content.attachments});

			if (content)
			{
				// doc/ai/projects/mail-compose-jmap-migration.md, Step 1 - try the JMAP-native
				// draft-save path first (autosave and plain "Save as Draft"). Only ever engages
				// when the jmapCompose toggle is on AND this compose is otherwise eligible (no
				// attachments/integration/S-MIME/mailvelope) - "Not sure we want to follow that
				// up now" (ralf) on the classic autosave's own raw-IMAP-fallthrough error was the
				// reason to build this, so classic autosave/save must keep working unchanged
				// whenever the toggle is off or this compose isn't (yet) eligible.
				self.trySaveDraftViaJmap(action).then((handled) =>
				{
					if (handled)
					{
						_resolve();
						return;
					}
					self.saveAsDraftClassic(content, action, _resolve, _reject);
				});
			}
		});
	}

	/**
	 * The classic server-side "Save as Draft" postback - unchanged, still the ONLY path when
	 * trySaveDraftViaJmap() isn't eligible (see its own docblock).
	 */
	private saveAsDraftClassic(content : any, action : string, _resolve : () => void, _reject : () => void)
	{
		const self = this;
		// if we compose an encrypted message, we have to get the encrypted content
		if (self.app.mailvelope_editor)
		{
			self.app.mailvelope_editor.encrypt([]).then((_armored) =>
			{
				content['mail_plaintext'] = _armored;
				void self.egw.json('mail.mail_compose.ajax_saveAsDraft',[content, action],(_data) =>{
					const res = self.savingDraft_response(_data,action);
					if (res)
					{
						_resolve();
					}
					else
					{
						_reject();
					}
				}).sendRequest(true);
			}, (_err) =>
			{
				self.egw.message(_err.message, 'error');
				_reject();
			});
			return;
		}
		// Send request through framework main window, so it works even if the main window is reloaded
		egw_getFramework().egw_appWindow().egw.json('mail.mail_compose.ajax_saveAsDraft', [content, action], (_data) =>
		{
			const res = self.savingDraft_response(_data, action);
			if (res)
			{
				_resolve();
			}
			else
			{
				_reject();
			}
		}).sendRequest(true);
	}

	/**
	 * Set content of drafted message with new information sent back from server
	 * This function would be used as callback of send request to ajax_saveAsDraft.
	 *
	 * @param {object} _responseData response data sent back from server by ajax_saveAsDraft function.
	 *  the object conatins below items:
	 *  -draftedId: new drafted id created by server
	 *  -message: resault message
	 *  -success: true if saving was successful otherwise false
	 *  -draftfolder: Name of draft folder including its delimiter
	 *
	 * @param {string} _action action is the element which caused saving draft, it could be as such:
	 *  -button[saveAsDraft]
	 *  -button[saveAsDraftAndPrint]
	 *  -autosaving
	 *
	 *  @return boolean return true if successful otherwise false
	 */
	savingDraft_response(_responseData, _action)
	{
		//Make sure there's a response from server otherwise shoot an error message
		if (!_responseData || Object.keys(_responseData).length === 0)
		{
			this.egw.message('Could not saved the message. Because, the response from server failed.', 'error');
			return false;
		}

		if (_responseData.success)
		{
			const content = this.et2.getArrayMgr('content');
			const lastDrafted = this.et2.getWidgetById('lastDrafted');
			const folderTree = typeof (opener || window)?.etemplate2?.getByApplication('mail')[0] != 'undefined' ?
				(opener || window).etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('nm[foldertree]') : null;
			const activeFolder = folderTree ? folderTree.getSelectedNode() : null;
			if (content)
			{
				const prevDraftedId = content.data.lastDrafted;
				content.data.lastDrafted = _responseData.draftedId;
				this.et2.setArrayMgr('content', content);
				lastDrafted.set_value(_responseData.draftedId);
				if (folderTree && activeFolder)
				{
					if (typeof activeFolder.id !='undefined' && _responseData.draftfolder == activeFolder.id)
					{
						if (prevDraftedId)
						{
							opener.egw_refresh(_responseData.message,'mail', prevDraftedId, 'delete');
						}
						this.egw.refresh(_responseData.message,'mail',_responseData.draftedId);
					}
				}
				switch (_action)
				{
					case 'button[saveAsDraftAndPrint]':
						this.print('mail::'+_responseData.draftedId);
						this.egw.message(_responseData.message);
						break;
					case 'autosaving':
					//Any sort of thing if it's an autosaving action
					default:
						this.egw.message(_responseData.message);
				}
			}
			return true;
		}
		else
		{
			this.egw.message(_responseData.message, 'error');
			return false;
		}
	}

	/**
	 * Print a mail from compose
	 * @param {string} _id id of new draft
	 */
	print(_id)
	{
		this.egw.open(_id,'mail','view','&print='+_id+'&mode=print');
	}
}
