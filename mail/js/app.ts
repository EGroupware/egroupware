/**
 * mail - JavaScript functions
 *
 * @link: https://www.egroupware.org
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2013-2025 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {EgwApp} from "../../api/js/jsapi/egw_app";
import {et2_createWidget} from "../../api/js/etemplate/et2_core_widget";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {egw_getActionManager, egw_getObjectManager} from '../../api/js/egw_action/egw_action';
import {egwIsMobile, egwSetBit} from "../../api/js/egw_action/egw_action_common";
import {
	EGW_AO_FLAG_DEFAULT_FOCUS,
	EGW_KEY_ARROW_DOWN,
	EGW_KEY_ARROW_UP
} from "../../api/js/egw_action/egw_action_constants";
import {loadWebComponent} from "../../api/js/etemplate/Et2Widget/Et2Widget";
import type {Et2DatagridUpdateType} from "../../api/js/etemplate/Et2Datagrid/Et2Datagrid.types";
import {Et2DatagridUpdateTypes} from "../../api/js/etemplate/Et2Datagrid/Et2Datagrid.types";
import type {Et2Nextmatch} from "../../api/js/etemplate/Et2Nextmatch/Et2Nextmatch";
import {MailCompose} from "./compose";
import {isPreferenceOn, JmapBodyResult, JmapMessageReference, JmapUserError, MailJmap} from "./jmap";
import {renderAttachmentIndex} from "./attachmentIndex";
import {buildErrorNode, buildFolderLevel, FolderTreeNode} from "./folderTree";
import {egw, egw_getFramework} from "../../api/js/jsapi/egw_global";

import type {Et2Details} from "../../api/js/etemplate/Layout/Et2Details/Et2Details";
import type {Et2Tree} from "../../api/js/etemplate/Et2Tree/Et2Tree";
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import type {Et2Description} from "../../api/js/etemplate/Et2Description/Et2Description";
import type {Et2Textbox} from "../../api/js/etemplate/Et2Textbox/Et2Textbox";

interface CustomLabel
{
	name: string;
	color: string;
	icon?: string;
}

type CustomLabels = Record<string, CustomLabel>
/**
 * UI for mail
 *
 * @augments EgwApp
 */
export class MailApp extends EgwApp
{
	/**
	 * modified attribute in mail app to test new entries get added on top of list
	 */
	modification_field_name : any = 'date';

	/**
	 * et2 widget container
	 */
	nm : Et2Nextmatch = null;
	doStatus : any = null;

	mail_queuedFolders : any = [];
	mail_queuedFoldersIndex : any = 0;

	mail_selectedMails : any = [];
	mail_currentlyFocussed : any = '';
	mail_previewAreaActive : any = true; // we start with the area active

	nm_index : any = 'nm'; // nm name of index
	mail_fileSelectorWindow : any = null;
	mail_isMainWindow : any = true;

	// Aborts the in-flight fetchBody() request when a newer selection supersedes it.
	previewFetchAbort : AbortController = null;
	/**
	 *
	 */
	subscription_treeLastState : "";

	tree_wdg: Et2Tree = null;

	/**
	 * abbrevations for common access rights
	 * @array
	 *
	 */
	aclCommonRights : any = ['lrs','lprs','ilprs',	'ilprsw', 'aeiklprstwx', 'custom'];
	/**
	 * Demonstrates ACL rights
	 * @array
	 *
	 */
	aclRights : any = ['l','r','s','w','i','p','c','d','k','x','t','e','a'];

	/**
	 * In order to store Intervals assigned to window
	 * @array of setted intervals
	 */
	W_INTERVALS : any = [];

	/**
	 *
	 * @array of setted timeouts
	 */
	W_TIMEOUTS : any = [];

	/**
	 * Replace http:// in external image urls with
	 */
	image_proxy : any = 'https://';

	customLabels: CustomLabels = {};

	/**
	 * stores push activated acc ids
	 */
	push_active : any = {};

	private _compose : MailCompose;
	private _jmap : MailJmap;

	/**
	 * Pending subscribe/unsubscribe changes for the currently open mail.subscribe popup, recorded
	 * directly as the user toggles checkboxes (id -> desired subscribed state) rather than diffed
	 * from a full "original vs current" tree snapshot at save time - an unloaded/never-touched
	 * node simply never fires a toggle, so there's no need to eagerly load the whole account
	 * before Save can know what changed. null means the JMAP load never ran/succeeded, so
	 * subscriptionSave() must leave the classic submit untouched.
	 */
	private _subscriptionChanges : Map<string, boolean> | null = null;

	/**
	 * The subscribe popup's own profileID (mail_ui::subscription()'s $content['profileId']),
	 * remembered alongside _subscriptionChanges since it's needed to apply them on Save.
	 */
	private _subscriptionProfileID : string | null = null;

	/**
	 * The subscribe popup tree's own .value array as of the last time it was inspected (either
	 * freshly-loaded original data being seeded in, or an actual user toggle already recorded) -
	 * the baseline recordSubscriptionChange() diffs against to find what's new since then.
	 */
	private _subscriptionKnownValue : Set<string> = new Set();
	et2_obj: etemplate2;
// defer calls to refreshFolderStatus,
// to accumulate updates of multiple rows e.g. deleting multiple emails
	refresh_timeout: any;
	/**
	 * Compose functions sub-object (gets automatic instanciated, if used)
	 */
	get compose() : MailCompose
	{
		if(!window.app._compose)
		{
			window.app._compose = new MailCompose(this);
		}
		return window.app._compose;
	}

	/**
	 * Direct client-side JMAP access sub-object (gets automatically instanciated, if used)
	 *
	 * Uses a server's native JMAP endpoint or the local plain-IMAP shim.
	 */
	get jmap() : MailJmap
	{
		if(!window.app._jmap)
		{
			window.app._jmap = new MailJmap(this);
		}
		return window.app._jmap;
	}

	/**
	 * Initialize javascript for this application
	 *
	 * @memberOf mail
	 */
	constructor(_app, _wnd)
	{
		super('mail', _wnd);

		if (!this.egw.is_popup())
		{
			// Turn on client side, persistent cache
			// egw.data system runs encapsulated below etemplate, so this must be
			// done before the nextmatch is created.
			this.egw.dataCacheRegister('mail',
				// Called to determine cache key
				this.nmCache,
				this
			);

			// Let mail's direct-JMAP path (see jmap.ts) answer NextMatch's regular row-fetch itself for Stalwart-backed accounts, instead of round-tripping through get_rows.
			// Not from a popup: it shares this dataRegister with the window that opened it.
			this.egw.dataRegisterFetch('mail', this.jmap.fetchRows, this.jmap);
		}
	}

	/**
	 * Destructor
	 */
	destroy()
	{

		// Only if we are the window that registered them (see constructor):
		// dataCacheUnregister() resets the *entire* callback list for the 'mail' prefix, and that list is shared with popups,
		// so calling it from a closing popup tore down the main window's wiring too.
		if (!this.egw.is_popup())
		{
			this.egw.dataCacheUnregister('mail');
			//only unregister the fetch that was actually registered
			this.egw.dataUnregisterFetch('mail',this.jmap.fetchRows, this.jmap);
		}

		this.tree_wdg?.destroy && this.tree_wdg.destroy();
		this.tree_wdg?.remove && this.tree_wdg.remove();
		this.tree_wdg = null;

		delete this.et2_obj;

		// delete compose sub-object
		this._compose?.destroy();
		delete this._compose;

		// delete jmap sub-object
		this._jmap?.destroy();
		delete this._jmap;

		// call parent
		super.destroy.apply(this, arguments);
	}

	/**
	 * Dynamic disable NM autorefresh on get_rows response depending on push support of imap-server
	 *
	 * @param {bool} _disable
	 */
	/**
	 * check and try to reinitialize et2 of module
	 */
	checkET2()
	{
		//this.et2 should do the same as etemplate2.getByApplication('mail')[0].widgetContainer
		if (!this.et2) // if not defined try this in order to recover
		{
			try
			{
				this.et2 = etemplate2.getByApplication('mail')[0].widgetContainer;
			}
			catch(e)
			{
				return false;
			}
		}
		return true;
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 * @param {string} _name template name
	 */
	et2_ready(et2, _name)
	{
		super.et2_ready(et2, _name);
		
		this.et2_obj = et2;
		this.push_active = {};
    const self = this;
		switch (_name)
		{
			case 'mail.sieve.vacation':
				this.vacationFilterStatusChange();
				break;
			case 'mail.index':
				jQuery('iframe#mail-index_messageIFRAME').on('load', function ()
				{
					// decrypt preview body if mailvelope is available
					self.mailvelopeAvailable(self.mailvelopeDisplay);
					self.preparePrint();
				});
				var nm = this.et2.getWidgetById(this.nm_index);
				this.mail_isMainWindow = true;

				// Stop list from focussing next row on keypress
				let aom = egw_getObjectManager('mail').getObjectById('nm');
				// @ts-ignore
				aom.flags = egwSetBit(aom.flags, EGW_AO_FLAG_DEFAULT_FOCUS, false);

				let splitter = this.et2.getWidgetById('mailSplitter');
				if (splitter && egw.preference('previewPane', 'mail') == 'expand')
				{
					splitter.style.setProperty('--max', '100%');
					window.setTimeout(() =>
					{
						splitter.dock();
					});
				}
				// Set preview pane state
				this.disablePreviewArea(!this.getPreviewPaneState());

				//Get initial folder status
				this.refreshFolderStatus(undefined, undefined, false);

				// Bind to nextmatch refresh to update folder status
				if (nm != null)
				{
					nm.addEventListener('refresh', (_event) =>
					{
						if (!self.push_active[nm.activeFilters.selectedFolder.split("::")[0]])
						{
							// defer calls to refreshFolderStatus for 2s, to accumulate updates of multiple rows e.g. deleting multiple emails
							if (!self.refresh_timeout)
							{
								self.refresh_timeout = window.setTimeout(() =>
								{
									self.refresh_timeout = null;
									self.refreshFolderStatus.call(self, undefined, undefined, false);
								}, 2000);
							}
						}
					});
				}
				if(!this.tree_wdg){
					this.tree_wdg = this.et2.getWidgetById(this.nm_index+'[foldertree]');
				}
				if (this.tree_wdg) {
					// show / open selected folder, if necessary autoload it
					if (typeof this.tree_wdg.value === "string" && !this.tree_wdg.scrollToSelected())
					{
						const parts = this.tree_wdg.value.split('::');
						const path_parts = parts[1].split('/');
						const do_open = (folder) => {
							this.tree_wdg.openItem(folder).then(() => {
								if (path_parts.length > 1)
								{
									do_open(folder + '/' + path_parts.shift());
								}
							});
						}
						path_parts && do_open(parts[0]+'::'+path_parts.shift());
					}
					//TODO check if there are changes necessary
					this.tree_wdg.set_onopenstart(jQuery.proxy(this.openStartTree, this));
					this.tree_wdg.set_onopenend(jQuery.proxy(this.openEndTree, this));

					// Lazy per-level JMAP folder loading (see doc/ai/projects/mail-folder-tree-jmap.md),
					// replacing the classic ajax_foldertree menuaction (now removed) for both
					// desktop and mobile, which share this same template id/case. One preference
					// for the whole tree (not per-profile): node ids already carry
					// "profileID::path", so a single flat expanded-ids list already covers every
					// account shown in this one tree instance.
					this.tree_wdg.autoloading = this.mail_folderTreeAutoload.bind(this);
					this.tree_wdg.openStatePreference = 'mail.ExpandedFolders';
				}
				// Show vacation notice on load for the current profile (if not called by searchtypeChange())
				const cat_id = this.et2.getWidgetById('cat_id');
				const already_refreshed = this.searchtypeChange(null, cat_id);
				if (!already_refreshed) this.callRefreshVacationNotice();
				break;
			case 'mail.display':
				// Prepare display dialog for printing
				// copies iframe content to a DIV, as iframe causes
				// trouble for multipage printing

				jQuery('iframe#mail-display_mailDisplayBodySrc').on('load', function(e)
				{
					// encrypt body if mailvelope is available
					self.mailvelopeAvailable(self.mailvelopeDisplay);
					self.preparePrint();
					self.resolveExternalImages((this as HTMLIFrameElement).contentWindow.document, window.location.search.endsWith('&mode=print_images'));
					// Trigger print command if the mail oppend for printing porpuse
					// load event fires twice in IE and the first time the content is not ready
					// Check if the iframe content is loaded then trigger the print command
					if (window.location.search.search('&print=') >= 0 && jQuery((this as HTMLIFrameElement).contentWindow.document.body).children().length > 0)
					{
						self.print();
					}
				});

				this.mail_isMainWindow = false;
				this.display();

				break;
			case 'mail.compose':
				this.compose.setEtemplate(this.et2);
				const composeToolbar = this.et2.getWidgetById('composeToolbar');
				// set smime values in the toolbar assist to the initial values of the toolbar
				// try{
				// this.et2.getWidgetById('smime_sign').value = composeToolbar.getWidgetById('smime_sign').value;
				// this.et2.getWidgetById('smime_encrypt').value = composeToolbar.getWidgetById('smime_encrypt').value;}
				// catch (e)
				// {
				// 	egw.debug("warn","could not set initial values for compose toolbar helper")
				// }
				if (composeToolbar?.getWidgetById('pgp')?.value ||
					(this.et2.getArrayMgr('content').data as any)?.mail_plaintext?.includes(this.begin_pgp_message))
				{
					this.mailvelopeAvailable(this.mailvelopeCompose);
				}
				this.mail_isMainWindow = false;
				// add predefined addresses, but only if not already added (happens on several server-side roundtrips!)
				//NOTE: THIS NOW HAPPENS SERVER SIDE ON LOAD
				/*
				const pca = egw.preference(this.et2.getWidgetById('mailaccount').getValue().split(":")[0]+'_predefined_compose_addresses', 'mail');
				for (const p in pca)
				{
					if (this.et2.getWidgetById(p).getValue() && pca[p]?.length)
					{
						const widget = this.et2.getWidgetById(p);
						const values = widget.getValue();
						pca[p].forEach((value) => {
							values.indexOf(value) == -1 && values.push(value);
						});
						widget.set_value(pca[p]);
					}
				}*/
				this.compose.fieldExpanderInit();
				this.compose.checkSharingFilemode(undefined);

				this.compose.subject2title();

				var that = this;
				var plainText = this.et2.getWidgetById('mail_plaintext');
				var textAreaWidget = this.et2.getWidgetById('mail_htmltext');

				/* Control focus actions on subject to handle expanders properly.*/
				jQuery("#mail-compose_subject").on({
					focus(){
						that.compose.fieldExpanderInit();
						that.compose.fieldExpander();
					}
				});
				/*Trigger after the TinyMCE is fully loaded*/
				jQuery('#mail-compose').on ('load',function() {

					if (textAreaWidget && textAreaWidget.tinymce)
					{
						textAreaWidget.tinymce.then(()=>
						{
							if (textAreaWidget.editor)
							{
								jQuery(textAreaWidget.editor.iframeElement.contentWindow.document).on('dragenter', function ()
								{
									// anything to bind on tinymce iframe
								});
							}
						});
					}
					else
					{
						//that.compose.fieldExpander();
					}
				});

				//Resize compose after window resize to not getting scrollbar
				jQuery(window).on ('resize',function(e) {
					// Stop immediately the resize event if we are in mobile template
					if (egwIsMobile())
					{
						e.stopImmediatePropagation();
						return false;
					}
				});

				// Set focus on To/body field
				// depending on To field value
				const to = this.et2.getWidgetById('to');
				const content = this.et2.getArrayMgr('content').data;
				if (to && to.get_value() && to.get_value().length > 0)
				{
					if (typeof to.blur == "function")
					{
						// html area changes focus as part of its init, make sure it doesn't re-focus to
						to.blur();
					}
					if (content.is_plain)
					{
						// focus
						jQuery(plainText.getDOMNode()).focus();
						// get the cursor to the top of the textarea
						if (typeof plainText.getDOMNode().setSelectionRange !='undefined' && !jQuery(plainText.getDOMNode()).is(":hidden"))
						{
							setTimeout(function ()
							{
								plainText.getDOMNode().setSelectionRange(0, 0)
								plainText.focus();
								plainText.shadowRoot.querySelector("textarea").scrollTop = 0;
							}, 2000);
						}
					}
					else if(textAreaWidget && textAreaWidget.tinymce)
					{
						textAreaWidget.tinymce.then(()=>{setTimeout(function(){textAreaWidget.editor.focus()}, 500);});
					}
				}
				else if(to)
				{
					jQuery('input',to.getDOMNode()).focus();
					// set cursor to the begining of the textarea only for first focus
					if (content.is_plain
						&& typeof plainText.getDOMNode().setSelectionRange !='undefined')
					{
						plainText.getDOMNode().setSelectionRange(0,0);
					}
				}
				const smime_sign = this.et2.getWidgetById('smime_sign');
				const smime_encrypt = this.et2.getWidgetById('smime_encrypt');

				if (composeToolbar._actionManager.getActionById('smime_sign') &&
						composeToolbar._actionManager.getActionById('smime_encrypt'))
				{
					if (smime_sign.getValue() == 'on')
					{
						composeToolbar.getWidgetById('smime_sign').value = true;
					}
					if (smime_encrypt.getValue() == 'on')
					{
						composeToolbar.getWidgetById('smime_encrypt').value = true;
					}
				}
				break;
			case 'mail.view':
				// we need to set mail_currentlyFocused var otherwise mail
				// defined actions won't work
				// this means mobileView() was called earlier and not this is set
				//@ts-ignore
				this.mail_currentlyFocussed = this.et2.mail_currentlyFocussed;
				break;
			case 'mail.subscribe':
				this.subscriptionLoad();
				break;
			case 'mail.folder_management':
				this.folderManagementLoad();
				break;
		}
		this.customLabels = this.et2.getArrayMgr('content').getEntry('customLabels') ||
			window.opener?.app?.mail?.customLabels || this.customLabels;
		this.updateCustomLabelStylesheet();
		// set image_proxy for resolveExternalImages
		this.image_proxy = this.et2.getArrayMgr('content').getEntry('image_proxy') || 'https://';
	}

	/**
	 * Get configured custom labels, including from the opener for popup actions
	 */
	getCustomLabels(): CustomLabels
	{
		return Object.keys(this.customLabels).length ? this.customLabels :
			window.opener?.app?.mail?.customLabels || {};
	}

	/**
	 * Resolve a case-insensitive IMAP keyword to its category-name label ID
	 */
	getCustomLabelId(_id: string)
	{
		return Object.keys(this.getCustomLabels()).find(
			labelId => labelId.toLowerCase() === _id.toLowerCase()
		);
	}

	/**
	 * Check if an action or IMAP keyword is a configured custom label
	 */
	isCustomLabel(_id: string)
	{
		return typeof this.getCustomLabelId(_id) !== 'undefined';
	}

	/**
	 * All labels represented in row flags
	 */
	getLabelIds()
	{
		return ['label1', 'label2', 'label3', 'label4', 'label5',
			...Object.keys(this.getCustomLabels())];
	}

	/**
	 * Check if an action is a built-in or configured label
	 */
	isLabel(_id: string)
	{
		return this.getLabelIds().includes(_id);
	}

	/**
	 * Add configured custom-label colors after the static Mail label rules
	 */
	updateCustomLabelStylesheet()
	{
		const style = new CSSStyleSheet();
		const customLabels = this.getCustomLabels();
		for (const labelId of Object.keys(customLabels))
		{
			const customLabel = customLabels[labelId];
			style.insertRule(
				`tr.mail.${CSS.escape(labelId)} { --mail-left-border-color: ${customLabel.color}; }`
			)
		}
		this.nm?.addRowStylesheet(style);
	}

	/**
	 * Handle a push notification about entry changes from the websocket
	 *
	 * Get's called for data of all apps, but should only handle data of apps it displays,
	 * which is by default only it's own, but can be for multiple apps eg. for calendar.
	 *
	 * @param  pushData
	 * @param {string} pushData.app application name
	 * @param {(string|number)} pushData.id id of entry to refresh or null
	 * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param {number} pushData.account_id User that caused the notification
	 */
	push(pushData)
	{
		// don't care about other apps data, reimplement if your app does care eg. calendar
		if (pushData.app !== this.appname) return;

		let id0 = typeof pushData.id === 'string' ? pushData.id : pushData.id[0];
		let acc_id = id0.split('::')[1];
		// pushData.acl.folder (a real "/"-joined path, e.g. "INBOX/Sub") is already computed by
		// every caller for the Trash/Junk/Drafts/Sent check below - use it here too instead of
		// re-deriving it from id0's own third segment, which isn't reliably a base64(path) at all:
		// JMAP-native row ids (accountId::profileID::folderId::emailId, used for real JMAP/Stalwart
		// accounts) put the raw JMAP Mailbox id there, not base64(path) like the classic
		// accountId::profileID::base64(path)::uid shape does - atob() on that would silently
		// produce garbage instead of throwing, breaking folder-tree badge updates below.
		let folder = acc_id+'::'+pushData.acl.folder;
		let foldertree = this.et2 ? this.et2.getWidgetById('nm[foldertree]') : null;
		this.push_active[acc_id] = true;

		// update unseen counter in folder-tree (also for delete)
		if (foldertree && pushData.acl.folder && typeof pushData.acl.unseen !== 'undefined')
		{
			let folder_id = {};
			folder_id[folder] = pushData.acl.unseen;
			this.setFolderStatus(folder_id);
		}

		// only handle delete by default, for simple case of uid === "$app::$id"
		if (pushData.type === 'delete')
		{
			[].concat(pushData.id).forEach(uid => {
				let parts = uid.split('::');
				// a destroyed mailbox (id has no emailId segment) - only ever sent once its real
				// path is known (see MailJmap.buildWsPushPayload()'s folderPaths-cache lookup), so
				// remove its folder-tree node directly: egw.data has no notion of folder-tree
				// nodes at all, there's no dataHasUID()-based path for this like there is for rows
				if (parts.length === 3)
				{
					this.removeLeaf({[folder]: pushData.acl.folder});
					return;
				}
				// a destroyed email's folder can never be resolved via JMAP after the fact (see
				// MailJmap.buildEmailDeletePush()/Api\Mail\Imap\Jmap::pushCallback()'s own comments
				// for why) - the folderId segment is a literal "*" wildcard in that case. Email ids
				// are unique per account, so search the row cache directly instead of an exact-match
				// lookup: whichever folder(s) currently have this row cached get it removed.
				if (parts[2] === '*')
				{
					let escaped = [parts[0], parts[1], parts[3]].map(s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
					let regexp = new RegExp(`^${this.appname}::${escaped[0]}::${escaped[1]}::.*::${escaped[2]}$`);
					// dataRefreshUIDs() only notifies widgets that previously registered via
					// dataRegisterUID() for that exact uid - our NextMatch doesn't use that
					// mechanism, so it would silently do nothing (confirmed live). Use
					// dataSearchUIDs() just to discover the real cached uid(s) instead, then feed
					// each one through the same already-working exact-match delete below.
					Object.keys(this.egw.dataSearchUIDs(regexp)).forEach(fullUid => {
						pushData.id = fullUid.replace(new RegExp(`^${this.appname}::`), '');
						super.push(pushData);
					});
					return;
				}
				pushData.id = uid;
				super.push(pushData);
			});
			return;
		}

		// notify user a new mail arrived
		if (pushData.type === 'add' && pushData.acl.event === 'MessageNew')
		{
			// never notify for Trash, Junk, Drafts or Sent folder (user might use Sieve to move mails there!)
			if (pushData.acl.folder.match(/^(INBOX.)?(Trash|Spam|Junk|Drafts|Sent)$/)) return;
			// increment notification counter on (closed) mail tab
			let framework = egw_getFramework();
			if (framework && framework.notifyAppTab) framework.notifyAppTab('mail');
			// check if user wants a new mail notification
			this.notifyNew(pushData);
		}
		// check if we might not see it because we are on a different mail account or folder
		let nm = this.et2 ? this.et2.getWidgetById('nm') : null;
		let nm_value = nm ? nm.getValue() : null;

		// nm_value.selectedFolder is not always set, read it from foldertree, if not
		let displayed_folder = (nm_value ? nm_value.selectedFolder : null) || (foldertree ? foldertree.getValue() : '');
		if (!displayed_folder.match(/::/)) displayed_folder += '::INBOX';
		if (folder === displayed_folder)
		{
			switch(pushData.acl.event)
			{
				case 'Flags':
				case 'FlagsSet':
					// TB (probably other MUA too) mark mail as deleted, our UI removes/expunges it immediately
					if (pushData.acl.flags.includes('\\Deleted') || pushData.acl.flags.includes('$deleted'))
					{
						pushData.type = 'delete';
						return super.push(pushData);
					}
					// fall through - a flag/keyword change is a plain in-place row refresh either way
				case 'FlagsClear':
					nm.refresh(pushData.id, Et2DatagridUpdateTypes.UPDATE_IN_PLACE);
					break;
				default:
					// Just update the nm (todo: pushData.message = total number of messages in folder)
					nm.refresh(pushData.id, pushData.type === 'update' ? 'update-in-place' : pushData.type, pushData.messages);
			}
		}
	}

	/**
	 * Check if user want's new mail notification
	 *
	 * @param pushData
	 */
	notifyNew(pushData)
	{
		let framework = egw_getFramework();
		let notify = this.egw.preference('new_mail_notification', 'mail');
		const message = egw.lang('New mail from %1', pushData.acl.from)+'\n'+pushData.acl.subject+'\n'+pushData.acl.snippet;
		if (typeof notify === 'undefined' || notify === 'always' ||
			notify === 'not-mail' && framework && framework.activeApp.appName !== 'mail')
		{
			this.egw.message(message, 'success');
			this.egw.notification(egw.lang('new mail'), {body: message, tag: 'mail', icon: egw.image('navbar', 'mail')});
		}
	}

	/**
	 * Observer method receives update notifications from all applications
	 *
	 * App is responsible for only reacting to "messages" it is interested in!
	 *
	 * @param {string} _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param {string} _app application name
	 * @param {(string|number)} _id id of entry to refresh or null
	 * @param {string} _type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {string} _msg_type 'error', 'warning' or 'success' (default)
	 * @param {object|null} _links app => array of ids of linked entries
	 * or null, if not triggered on server-side, which adds that info
	 * @return {false|*} false to stop regular refresh, thought all observers are run
	 */
	observer(_msg, _app, _id, _type, _msg_type, _links)
	{
		switch(_app)
		{
			case 'mail':
				if (_id === 'sieve')
				{
					var iframe = this.et2.getWidgetById('extra_iframe');
					if (iframe && iframe.getDOMNode())
					{
						var contentWindow = iframe.getDOMNode().contentWindow;
						if (contentWindow && contentWindow.app && contentWindow.app.mail)
						{
							contentWindow.app.mail.sieveRefresh();
						}
					}
					return false;	// mail nextmatch needs NOT to be refreshed
				}
				// stop refresh, in case push has already deleted it
				// (done here as it's hard to know if imap server supports push on delete
				// and if both happen sometimes we "loose" a row as nextmatch removes it anyway)
				if (_type === 'delete' && !this.egw.dataHasUID('mail::'+_id)) return false;
				break;

			case 'mail-account':	// update tree with given mail account _id and _type
				var tree = this.et2 ? this.et2.getWidgetById(this.nm_index+'[foldertree]') : null;
				if (!tree) break;
				var node = tree.getNode(_id);
				// Make sure ID is a string, that's what tree uses
				_id = "" + _id;
				switch(_type)
				{
					case 'delete':
						if (node)	// we dont care for deleted accounts not shown (eg. other users)
						{
							tree.deleteItem(_id);
							// ToDo: blank list, if _id was active account
						}
						break
					case 'update':
					case 'edit':
						if (node)	// we dont care for updated accounts not shown (eg. other users)
						{
							// refreshItem() with no data re-runs the tree's own JMAP-first
							// autoloading callback (mail_folderTreeAutoload()) for this account's
							// root node - same mechanism the 'add' case below already uses
							// successfully, no need for the classic-only ajax_reloadNode round
							// trip anymore
							tree.refreshItem(_id);
						}
						break;
					case 'add':
						const current_id = tree.getValue();

						tree._selectOptions.push({
							id: "" + _id,
							// Use text instead of label because server side is only sending text
							text: this.egw.lang("Loading..."),
							selected: false,
							loading: true,
							lazy: true
						});
						tree.requestUpdate("_selectOptions");
						tree.updateComplete.then(async () =>
						{
							// need to wait tree is refreshed: current and new id are there AND current folder is selected again
							await tree.refreshItem(_id);
							if (tree.getNode(_id) && tree.getNode(current_id))
							{
								if (!tree.getSelectedNode())
								{
									tree.reSelectItem(current_id);
								}
								else
								{
									// open new account
									// need to wait new folders are loaded AND current folder is selected again
									await tree.openItem(_id, true);
									if (tree.getNode(_id + '::INBOX'))
									{
										if (!tree.getSelectedNode())
										{
											tree.reSelectItem(current_id);
										}
										else
										{
											this.changeFolder(_id + '::INBOX', tree, current_id);
											tree.reSelectItem(_id + '::INBOX');
										}
									}
								}
							}
						});
						break;
					default: // null
				}
		}
		return undefined;
	}

	/**
	 * Callback function for dataFetch caching.
	 *
	 * We only cache the first chunk (50 rows), and only if search filter is not set,
	 * but we cache this for every combination of folder, filter & filter2.
	 *
	 * We do not cache, if we dont find selectedFolder in query_context,
	 * as looking it up in tree causes mails to be cached for wrong folder
	 * (Probably because user already clicked on an other folder)!
	 *
	 * @param {object} query_context Query information from egw.dataFetch()
	 * @returns {string|false} Cache key, or false to not cache
	 */
	nmCache(query_context)
	{
		// Only cache first chunk of rows, if no search filter
		if((!query_context || !query_context.start) && query_context.count == 0 &&
			query_context.filters && query_context.filters.selectedFolder &&
			!(!query_context.filters || query_context.filters.search)
		)
		{
			// Make sure keys match, even if some filters are not defined
			return JSON.stringify({
				selectedFolder: query_context.filters.selectedFolder || '',
				cat_id: query_context.filters.cat_id || '',
				filter: query_context.filters.filter || '',
				filter2: query_context.filters.filter2 || '',
				sort: query_context.filters.sort
			});
		}
		return false;
	}

	/**
	 * nextmatch normally handles updates and selection of next row after delete, but mail is different
	 *
	 * Mail uses the delete event's remembered neighbours and selects one only if
	 * the user uses an arrow key in the next 10 seconds.
	 *
	 * The datagrid also has its own native Up/Down handling bound on its table, which
	 * would otherwise race this listener depending on where the browser happens to park
	 * focus after the deleted row's DOM node is removed. Listening on the
	 * capture phase makes mail see the key first, but we only actually act - and stop the
	 * event - when real focus has NOT already recovered onto a rendered grid row.  Once
	 * focus is back on a row (which is normally the case well before a human can react),
	 * the grid's own activeRowIndex is trustworthy again and must be left to handle
	 * navigation (and, via Et2Nextmatch's own capture-phase action-shortcut handler,
	 * subsequent Delete-key presses) as usual.
	 *
	 * @param Et2Nextmatch nm
	 * @param string[] row_ids
	 * @param string type
	 */
	refresh(nm : Et2Nextmatch, row_ids : string[], type : Et2DatagridUpdateType)
	{
		const selectRemembered = (event : CustomEvent<{previousRowId : string | null; nextRowId : string | null}>) =>
		{
			const selectNeighbour = (e : KeyboardEvent) =>
			{
				if(e.keyCode !== EGW_KEY_ARROW_UP && e.keyCode !== EGW_KEY_ARROW_DOWN)
				{
					return;
				}
				// Focus already resting on a real row means the grid's own navigation
				// has recovered and is trustworthy - defer to it instead of hijacking
				// what may be unrelated, later keyboard navigation.
				let active : Element | null = document.activeElement;
				while(active?.shadowRoot?.activeElement)
				{
					active = active.shadowRoot.activeElement;
				}
				if(active?.closest?.("[data-row-index]"))
				{
					return;
				}

				e.preventDefault();
				e.stopImmediatePropagation();

				const rowId = e.keyCode === EGW_KEY_ARROW_UP ? event.detail.previousRowId : event.detail.nextRowId;
				if(!rowId) return;
				nm.selectSingleRow(rowId);
				nm.focusRowById(rowId);
			};
			document.addEventListener("keydown", selectNeighbour, {capture: true, once: true});
			window.setTimeout(() => document.removeEventListener("keydown", selectNeighbour, {capture: true}), 10000);
		};
		nm.addEventListener("et2-rows-deleted", selectRemembered as EventListener, {once: true});
		nm.refresh(row_ids, type);
		window.setTimeout(() => nm.removeEventListener("et2-rows-deleted", selectRemembered as EventListener), 0);
	}

		/**
	 * mail rebuild Action menu On nm-list
	 *
	 * @param _actions
	 */
	rebuildActionsOnList(_actions)
	{
		this.et2.getWidgetById(this.nm_index).set_actions(_actions);
	}

	/**
	 * Does _id look like a real "(mail::)?accountID::profileID::mailboxId::emailId" message row
	 * id - i.e. would MailJmap.messageReference() accept it? Mirrors that method's own shape
	 * check (kept in sync manually, jmap.ts's version is private) - used to defensively filter
	 * out anything else (e.g. a bare folder id) before handing it to egw.dataRefreshUID(), which
	 * has no validation of its own. See feedback_et2nextmatch_mail_regression memory.
	 */
	private isValidRowId(id : string) : boolean
	{
		let parts = String(id || '').split('::');
		if (parts[0] === 'mail') parts = parts.slice(1);
		return parts.length === 4 && !!parts[1] && !!parts[2] && !!parts[3];
	}

	/**
	 * fetchCurrentlyFocussed - implementation to decide wich mail of all the selected ones is the current
	 *
	 * @param _selected array of the selected mails
	 * @param _reset bool - tell the function to reset the global vars used
	 */
	fetchCurrentlyFocussed(_selected, _reset) {
		// reinitialize the buffer-info on selected mails
		if (_reset == true || typeof _selected == 'undefined')
		{
			if (_reset == true)
			{
				// Request updated data, if possible - skip anything that isn't shaped like a real
				// message row id (a known, not yet root-caused issue can leave something else
				// here instead, e.g. a folder id - see feedback_et2nextmatch_mail_regression
				// memory; dataRefreshUID() has no validation of its own)
				if (this.mail_currentlyFocussed!='' && this.isValidRowId(this.mail_currentlyFocussed)) egw.dataRefreshUID(this.mail_currentlyFocussed);
				for(let k = 0; k < this.mail_selectedMails.length; k++)
				{
					if (this.isValidRowId(this.mail_selectedMails[k])) egw.dataRefreshUID(this.mail_selectedMails[k]);
				}
				//nm.refresh(this.mail_selectedMails,'delete');
			}
			this.mail_selectedMails = [];
			this.mail_currentlyFocussed = '';
			return '';
		}
		for(let k = 0; k < _selected.length; k++)
		{
			if (jQuery.inArray(_selected[k],this.mail_selectedMails)==-1)
			{
				this.mail_currentlyFocussed = _selected[k];
				break;
			}
		}
		this.mail_selectedMails = _selected;
		return this.mail_currentlyFocussed;
	}

	/**
	 * openMessage - implementation of the open action
	 *
	 * @param _action
	 * @param _senders - the representation of the elements(s) the action is to be performed on
	 * @param _mode - you may pass the mode. if not given view is used (tryastext|tryashtml are supported)
	 */
	openMessage(_action, _senders, _mode)
	{
		if(typeof _senders == 'undefined' || _senders.length == 0)
		{
			if(this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				_senders = [];
				_senders.push({id: this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if((typeof _senders == 'undefined' || _senders.length == 0) && this.mail_isMainWindow)
			{
				if(this.mail_currentlyFocussed)
				{
					_senders = [];
					_senders.push({id: this.mail_currentlyFocussed});
				}
			}
		}
		var _id = _senders[0].id;
		// reinitialize the buffer-info on selected mails
		if(!['tryastext', 'tryashtml', 'view', 'print', 'print_images'].includes(_mode))
		{
			_mode = 'view';
		}
		this.mail_selectedMails = [];
		this.mail_selectedMails.push(_id);
		this.mail_currentlyFocussed = _id;

		var dataElem = egw.dataGetUIDdata(_id);
		var subject = dataElem.data.subject;
		let command = _mode;
		if(command == 'print_images')
		{
			command = 'print';
		}
		//alert('Open Message:'+_id+' '+subject);
		var h:any = egw().open(_id, 'mail', 'view', command + '=' + _id.replace(/=/g, "_") + '&mode=' + _mode);
		const setTitle = async(w) =>
		{
			await egw(w).ready;
			w.document.title = subject;
		}
		if(typeof h?.then == "function")
		{
			h.then(setTitle);
		}
		else
		{
			setTitle(h);
		}
		// THE FOLLOWING IS PROBABLY NOT NEEDED, AS THE UNEVITABLE PREVIEW IS HANDLING THE COUNTER ISSUE
		// When body is requested, mail is marked as read by the mail server. Update UI to match instantly.
		if (typeof dataElem != 'undefined' && typeof dataElem.data != 'undefined' && typeof dataElem.data['class'] != 'undefined' && (dataElem.data['class'].indexOf('unseen') >= 0 || dataElem.data['class'].indexOf('recent') >= 0))
		{
			if (typeof dataElem.data.flags != 'undefined') dataElem.data.flags.read = 'read';
			dataElem.data['class'] = dataElem.data['class'].split(' ')
				.filter((className) => className != 'unseen' && className != 'recent').join(' ');
			this.patchRow(_id);
			// reduce counter without server roundtrip
			this.reduceCounterWithoutServerRoundtrip();
			// not needed, as an explizit read flags the message as seen anyhow
			//egw.jsonq('mail.mail_ui.ajax_flagMessages',['read', messages, false]);
		}
	}

	/**
	 * Open a single message in html mode
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	openAsHtml(_action, _elems)
	{
		this.openMessage(_action, _elems,'tryashtml');
	}

	/**
	 * Open a single message in plain text mode
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	openAsText(_action, _elems)
	{
		this.openMessage(_action, _elems,'tryastext');
	}

	/**
	 * Compose, reply or forward a message
	 *
	 * @function
	 * @memberOf mail
	 * @param _action _action.id is 'compose', 'composeasnew', 'reply', 'reply_all' or 'forward' (forward can be multiple messages)
	 * @param _elems _elems[0].id is the row-id
	 */
	compose(_action, _elems)
	{
		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			if (this.et2 && this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				_elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					_elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		// Extra info passed to egw.open()
		const settings: { id: string; from: string;smime_type?:string } = {
			// 'Source' Mail UID
			id: '',
			// How to pull data from the Mail IDs for the compose
			from: ''
		};

		// We only handle one for everything but forward
		settings.id = ((typeof _elems == 'undefined'|| _elems.length == 0)?'':_elems[0].id);
		const content = egw.dataGetUIDdata(settings.id);
		if (content) settings.smime_type = content.data['smime'];
		switch(_action.id)
		{
			case 'compose':
				if (_elems.length == 1)
				{
					//mail_parentRefreshListRowStyle(settings.id,settings.id);
				}
				else
				{
					return this.compose('forward',_elems);
				}
				break;
			case 'forward':
			case 'forwardinline':
			case 'forwardasattach':
				if (_elems.length>1||_action.id == 'forwardasattach')
				{
					settings.from = 'forward';
					settings.mode = 'forwardasattach';
					if (typeof _elems != 'undefined' && _elems.length>1)
					{
						for(var j = 1; j < _elems.length; j++)
						settings.id = settings.id + ',' + _elems[j].id;
					}
					return egw.openWithinWindow("mail", "setCompose", {
						data:{
							emails:{
								ids:settings.id,
								processedmail_id: settings.id
							}
						}
						}, settings, /mail.mail_compose.compose/);
				}
				else
				{
					settings.from = 'forward';
					settings.mode = 'forwardinline';
				}
				break;
			default:
				// No further client side processing needed for these
				settings.from = _action.id;
		}
		var compose_list = egw.getOpenWindows("mail", /^compose_/);
		var window_name = 'compose_' + compose_list.length + '_'+ (settings.from || '') + '_' + settings.id;
		return egw().open('','mail','add',settings,window_name,'mail');
	}

	/**
	 * Set content into a compose window
	 *
	 * @function
	 * @memberOf mail
	 *
	 * @param {window object} compose compose window object
	 * @param {object} content
	 *
	 * @description content Data to set into the window's fields
	 * content.to Addresses to add to the to line
	 * content.cc Addresses to add to the CC line
	 * content.bcc Addresses to add to the BCC line
	 *
	 * @return {boolean} Success
	 */
	setCompose(compose, content)
	{
		// Get window
		if(!compose || compose.closed) return false;

		// Get etemplate of popup
		var compose_et2 = compose.etemplate2.getByApplication('mail');
		if(!compose_et2 || compose_et2.length != 1 || !compose_et2[0].widgetContainer)
		{
			return false;
		}

		// Set each field provided
		var success = true;
		var arrContent = [];
		for(var field in content)
		{
			try
			{
				if (field == 'data')
				{
					var w = compose_et2[0].widgetContainer.getWidgetById('appendix_data');
					w.set_value(JSON.stringify(content[field]));
					var filemode = compose_et2[0].widgetContainer.getWidgetById('filemode');
					if (content[field]['files'] && content[field]['files']['filemode']
							&& filemode && filemode.get_value() != content[field]['files']['filemode'])
					{
						var filemode_label = filemode.select_options.filter(_item =>
						{
							return _item.value == content[field]['files']['filemode']
                            })[0]['label'];
						Et2Dialog.show_dialog(function (_button)
							{
								if (_button == Et2Dialog.YES_BUTTON)
								{
									compose_et2[0].submit();
								}
							},
							this.egw.lang(
								'Be aware by adding all selected files as %1 mode, it will also change all existing attachments in the list to %2 mode as well. Would you like to proceed?',
								filemode_label, filemode_label),
							this.egw.lang('Add files as %1', filemode_label), '', Et2Dialog.BUTTONS_YES_NO, Et2Dialog.WARNING_MESSAGE);
						return;
					}
					else
					{
						return compose_et2[0].widgetContainer._inst.submit();
					}
				}

				var widget = compose_et2[0].widgetContainer.getWidgetById(field);

				// Merge array values, replace strings
				var value = widget.getValue() || content[field];
				if(jQuery.isArray(value) || jQuery.isArray(content[field]))
				{
					if(jQuery.isArray(content[field]))
					{
						value = value.concat(content[field]);
					}
					else
					{
						arrContent = content[field].split(',');
						for (var k=0;k < arrContent.length;k++)
						{
							value.push(arrContent[k]);
						}
					}
				}
				widget.set_value(value);
			}
			catch(e)
			{
				egw.debug("error", "Unable to set field %s to '%s' in window '%s'", field, content[field],window.name);
				success = false;
				continue;
			}
		}
		if (content['cc'] || content['bcc'] || content['folder'] || content['replyto'])
		{
			this.compose.fieldExpander();
			this.compose.fieldExpanderInit();
		}
		return success;
	}

	/**
	 * disablePreviewArea - implementation of the disablePreviewArea action
	 *
	 * @param _value
	 */
	disablePreviewArea(_value) {
		var splitter = this.et2.getWidgetById('mailSplitter');
		var previewPane = this.egw.preference('previewPane', 'mail') || 'vertical';
		// return if there's no splitter we maybe in mobile mode
		if (typeof splitter == 'undefined' || splitter == null || previewPane == 'vertical') return;
		let dock = function(){
			splitter.style.setProperty('--max','100%');
			splitter.dock();
		};
		let undock = function ()
		{
			splitter.style.setProperty('--max','70%');
			splitter.undock();
		};

		if(splitter.isDocked())
		{
			this.mail_previewAreaActive = false;
		}
		this.et2.getWidgetById('mailPreview').set_disabled(_value);
		//Dock the splitter always if we are browsing with mobile
		if (egwIsMobile())
		{
			this.disablePreviewArea = _value = true;
		}

		if (_value==true)
		{
			if (this.mail_previewAreaActive) dock();
			this.mail_previewAreaActive = false;
		}
		else
		{
			if (!this.mail_previewAreaActive)
			{
				undock();
				//window.setTimeout(function(){splitter.left.trigger('resize.et2_split.mailSplitter');},200);
			}
			this.mail_previewAreaActive = true;
		}
	}

	/**
	 * Set values for mail dispaly From,Sender,To,Cc, and Bcc
	 * Additionally, apply expand on click feature on thier widgets
	 *
	 */
	display()
	{
		var dataElem = {data:{FROM:"",SENDER:"",TO:"",CC:"",BCC:""}};
		var content = this.et2.getArrayMgr('content').data;

		if (typeof  content != 'undefiend')
		{
			dataElem.data = jQuery.extend(dataElem.data, content);

			var toolbaractions = ((typeof dataElem != 'undefined' && typeof dataElem.data != 'undefined' && typeof dataElem.data.displayToolbaractions != 'undefined')?JSON.parse(dataElem.data.displayToolbaractions):undefined);
			if (toolbaractions)
			{
				this.et2.getWidgetById('displayToolbar').actions = toolbaractions;
			}

			// Popup content isn't fetched server-side (see mail_ui::displayMessage()) - fill it
			// the same way the preview panel does, from the row already cached in the window
			// that opened this popup, or a fallback ajax call if that's unavailable.
			const rowId = content.mail_id;
			const details = this.et2.getWidgetById('mailDisplayDetails');
			if (rowId && details)
			{
				this.renderPopupMessage(details, rowId);
			}
		}
	}

	/**
	 * Populate the "view" popup's header/address/attachments
	 *
	 * Sources data from the row already cached in the window that opened this popup (the
	 * established window.opener.<egw|etemplate2|app> pattern already used elsewhere in this
	 * codebase for popups, e.g. this.et2.getById() lookups via window.opener further down this
	 * file) - no extra IMAP round-trip, same data the list/preview panel already fetched.
	 * Falls back to one ajax call (ajax_fetchMessageDetails) when that's unavailable: a
	 * bookmarked/direct link, or the opener window was closed.
	 *
	 * @param template the mailDisplayDetails grid widget
	 * @param rowId
	 */
	renderPopupMessage(template, rowId : string)
	{
		let openerData : any;
		try
		{
			openerData = window.opener && !window.opener.closed && window.opener.egw ?
				window.opener.egw.dataGetUIDdata(rowId)?.data : undefined;
		}
		catch (e)
		{
			// window.opener can be from a different origin in some conditions - fall through to ajax
		}

		if (openerData && Object.keys(openerData).length)
		{
			const data = this.renderMessageInto(template, rowId, openerData);
			this.registerForDrag(rowId, data.attachmentsBlock);
		}
		else
		{
			// Not this.egw.jsonq() (queues under menuaction=api.queue): the server side
			// generates a Link::set_data() download token for each attachment, which needs to
			// persist in the session - api.queue's handler closes/commits the session up front
			// (to avoid blocking other concurrent queued requests), silently discarding that
			// write. egw.request() sends a normal, immediate, non-queued request instead.
			this.egw.request('mail.mail_ui.ajax_fetchMessageDetails', [rowId]).then((_data) =>
			{
				if (_data)
				{
					egw.dataStoreUID(_data.uid ?? rowId, _data);
					const data = this.renderMessageInto(template, rowId, _data);
					this.registerForDrag(rowId, data.attachmentsBlock);
				}
			});
		}
	}

	/**
	 * Handle actions from attachments block
	 * @param _e
	 * @param _widget
	 */
	attachmentsBlockActions(_e, _widget)
	{
		const id = _widget.id.replace('[actions]','');
		const action = _widget.value;
		_widget.label = this.egw.lang(_widget.select_options.find(_item =>
		{
			return _item.value == _widget.value
		})?.label || "");
		this.saveAttachmentHandler(_widget,action, id);
	}

	/**
	 * Resolve a row's data (from cache, or a caller-supplied object) and fill the given
	 * template with it: address concat, on-demand attachmentsBlock resolution (winmail.dat or
	 * JMAP rows missing a resolved block - see mail/js/jmap.ts), then template.set_value().
	 *
	 * Shared by preview() (below, sourcing data from this window's own row cache) and the
	 * "view" popup (openMessage()'s target page, sourcing data from window.opener's cache or a
	 * server fallback) - both render the same message the same way, from the same data shape.
	 *
	 * @param template et2 widget with set_value({content, sel_options}), e.g. the mailPreview grid
	 * @param rowId
	 * @param data optional pre-resolved row data (e.g. from window.opener's cache); defaults to
	 *  this window's own egw.dataGetUIDdata(rowId).data
	 * @return the row data object (attachmentsBlock may still be updating asynchronously)
	 */
	renderMessageInto(template, rowId : string, data? : any) : any
	{
		let sel_options = {};
		let attachmentsBlock = this.et2.getWidgetById('attachmentsBlock');
		data = data ?? egw.dataGetUIDdata(rowId).data ?? {};
		data.emailTag = egw.preference('emailTag', 'mail') ?? 'onlyname';

		// Try to resolve winmail.data attachment
		if (data && data.attachmentsBlock && data.attachmentsBlock[0]
				&& data.attachmentsBlock[0].winmailFlag
				&& (data.attachmentsBlock[0].mimetype =='application/ms-tnef' ||
				data.attachmentsBlock[0].filename == "winmail.dat"))
		{
			if (attachmentsBlock) attachmentsBlock.getDOMNode().classList.add('loading');
			// Not this.egw.jsonq() - see the ajax_fetchMessageDetails call above for why: this
			// also generates a Link::set_data() token that needs to survive in the session.
			this.egw.request('mail.mail_ui.ajax_resolveWinmail',[rowId]).then((_data) =>
			{
				if (attachmentsBlock) attachmentsBlock.getDOMNode().classList.remove('loading');
				if (typeof _data == 'object')
				{
					data.attachmentsBlock = _data;
					data.attachmentsBlockTitle = _data.length > 1 ? `+${_data.length-1}` : '';
					// Update client cache to avoid resolving winmail.dat attachment again
					egw.dataStoreUID(data.uid, data);
					if (!egwIsMobile() && template) template.set_value({content:data});
				}
				else
				{
					console.log('Can not resolve the winmail.data!');
				}
			});
		}
		// Rows fetched via client-side JMAP (see mail/js/jmap.ts) don't carry a resolved
		// attachmentsBlock (building it needs a server-side mime_data/download token via
		// Link::set_data(), not just JMAP metadata) - fetch it on demand, same as the
		// winmail.dat resolution above, whenever the row indicates it has attachment(s).
		else if (data && Array.isArray(data.attachmentsBlock) && data.attachmentsBlock.length === 0
			&& data.attachments && data.attachments !== '&nbsp;')
		{
			if (attachmentsBlock) attachmentsBlock.getDOMNode().classList.add('loading');
			// Not this.egw.jsonq() - same reason as above.
			this.egw.request('mail.mail_ui.ajax_fetchAttachments', [rowId]).then((_data) =>
			{
				if (attachmentsBlock) attachmentsBlock.getDOMNode().classList.remove('loading');
				if (_data && Array.isArray(_data.attachmentsBlock) && _data.attachmentsBlock.length)
				{
					data.attachmentsBlock = _data.attachmentsBlock;
					this.setupViewAttachmentActions(data, sel_options);
					// Update client cache to avoid re-fetching the attachment block again
					egw.dataStoreUID(data.uid, data);
					if (!egwIsMobile() && template) template.set_value({content:data, sel_options:sel_options});
					// body may have already finished loading (empty) before this resolved -
					// retry the auto-index now that attachmentsBlock is known, but only into
					// the iframe still actually showing this row (loadMessageBody() marks it)
					const iframeDoc = (this.et2.getWidgetById('messageIFRAME')?.getDOMNode() as HTMLIFrameElement)
						?.contentDocument;
					if (iframeDoc?.documentElement?.dataset.rowId === rowId)
					{
						renderAttachmentIndex(iframeDoc, data.attachmentsBlock, this.egw);
					}
				}
			});
		}
		// A real JMAP server (eg. Stalwart) parses From/To/Cc/Bcc itself - if its own address
		// parser isn't RFC 2047-aware, MailJmap.email2row() flags the affected field(s) here
		// (an entry with no usable email address). Re-fetch+re-parse just that one broken field,
		// on demand, the same "only when it actually looks wrong" way attachmentsBlock is above -
		// never for every message. The local IMAP shim never sets this (it already re-parses raw
		// headers unconditionally), so this only ever fires for a real server's own mistake.
		if (Array.isArray(data.suspectAddressFields) && data.suspectAddressFields.length)
		{
			const fields = data.suspectAddressFields;
			data.suspectAddressFields = [];
			fields.forEach((field : 'from' | 'to' | 'cc' | 'bcc') =>
			{
				this.jmap.repairAddressField(rowId, field).then((list) =>
				{
					if (!list)
					{
						return;
					}
					const formatted = list.map(a => a.name ? `${a.name} <${a.email}>` : a.email);
					if (field === 'from' || field === 'to')
					{
						data[field + 'address'] = formatted[0] || '';
						data['additional' + field + 'address'] = formatted.slice(1);
					}
					else
					{
						data[field + 'address'] = formatted;
					}
					egw.dataStoreUID(data.uid, data);
					if (!egwIsMobile() && template) template.set_value({content: data, sel_options: sel_options});
				});
			});
		}

		if (data.toaddress||data.fromaddress)
		{
			data.additionaltoaddress = (data.additionaltoaddress??[]).concat(data.toaddress);
			data.additionaltoaddress = 	data.additionaltoaddress.filter((i, item) => {
				return data.additionaltoaddress.indexOf(i) == item
			});
			data.additionalfromaddress = (data.additionalfromaddress??[]).concat(data.fromaddress);
			data.additionalfromaddress = data.additionalfromaddress.filter((i, item) => {
				return data.additionalfromaddress.indexOf(i) == item
			});
		}

		if (data.attachmentsBlock)
		{
			this.setupViewAttachmentActions(data, sel_options);
		}

		if (!egwIsMobile() && template) template.set_value({content:data, sel_options:sel_options});

		return data;
	}

	/**
	 * preview - implementation of the preview action
	 *
	 * @param nextmatch Et2Nextmatch The widget whose row was selected
	 * @param selected Array Selected row IDs.  May be empty if user unselected all rows.
	 */
	preview(selected, nextmatch) {
		let data:any = {};
		let rowId = '';
		let attachmentsBlock = this.et2.getWidgetById('attachmentsBlock');
		let mailPreview = this.et2.getWidgetById('mailPreview');
		let previewPane = this.egw.preference('previewPane', 'mail')||'vertical';
		// don't go further if the preview is supposed to be disabled and we're not in mobile view
		if (previewPane == 'hide' && !egwIsMobile()) return;

		// A newer selection supersedes any body-fetch still in flight for the previous one
		if (this.previewFetchAbort)
		{
			this.previewFetchAbort.abort();
			this.previewFetchAbort = null;
		}

		if(typeof selected != 'undefined' && selected.length == 1 && selected[0])
		{
			rowId = this.fetchCurrentlyFocussed(selected);
			data = this.renderMessageInto(mailPreview, rowId);
		}
		else if (!egwIsMobile() && mailPreview)
		{
			mailPreview.set_value({content:data, sel_options:{}});
		}
		// We cannot do any sensible thing if there is no rowId (or data) to act on after the mailPreview is cleared
		if(!rowId && Object.keys(data).length === 0) return

		if (selected && selected.length>1)
		{
			// A pending single-selection body-load timer
			// (scheduled below, in the plain-selection branch) targets a rowId that is no longer selected now
			// without this, it can still
			// fire ~300ms later and load that stale row's body into the iframe this branch just
			// blanked/disabled.
			for (const t in this.W_TIMEOUTS) {window.clearTimeout(this.W_TIMEOUTS[t]);}
			// Leave if we're here and there is nothing selected, too many, or no data
			if (attachmentsBlock)
			{
				// check if the widget is attached before setting its content
				if (attachmentsBlock.parentNode)
				{
					attachmentsBlock.set_value({content:[]});
					attachmentsBlock.set_class('previewAttachmentArea noContent mail_DisplayNone');
				}
				const IframeHandle = this.et2.getWidgetById('messageIFRAME');
				if(IframeHandle) IframeHandle.set_src('about:blank');
				this.disablePreviewArea(true);
			}
			if (!egwIsMobile())return;
		}
		// Not applied to mobile preview
		else if (!egwIsMobile() && previewPane !='hide')
		{
			// Blank first, so we don't show previous email while loading
			const IframeHandle = this.et2.getWidgetById('messageIFRAME');
			IframeHandle.set_src('about:blank');

			this.smimeClearFlags([this.et2.getWidgetById('mailPreviewContainer').getDOMNode()]);

			// show iframe, in case we hide it from mailvelopes one and remove that
			jQuery(IframeHandle.getDOMNode()).show()
				.next(this.mailvelope_iframe_selector).remove();

			// need to have the DOM ready for calculation.
			this.disablePreviewArea((typeof selected == 'undefined' || selected.length == 0 && previewPane == 'expand'));

			// Update the internal list of selected mails, if needed
			if(this.mail_selectedMails.indexOf(rowId) < 0)
			{
				this.mail_selectedMails.push(rowId);
			}

			// Try to avoid sending so many request when user tries to scroll on list
			// via key up/down quite fast.
			for (const t in this.W_TIMEOUTS) {window.clearTimeout(this.W_TIMEOUTS[t]);}
			this.W_TIMEOUTS.push(window.setTimeout(()=>{

				const controller = new AbortController();
				this.previewFetchAbort = controller;
				this.loadMessageBody(IframeHandle, rowId, (doc) =>
				{
					this.resolveExternalImages(doc);
					renderAttachmentIndex(doc, data.attachmentsBlock, this.egw);
				}, controller.signal);
			}, 300));
		}

		var messages = {};
		messages['msg'] = [rowId];

		// When body is requested, mail is marked as read by the mail server. Update UI to match instantly.
		if (typeof data != 'undefined' && typeof data != 'undefined' && typeof data['class']  != 'undefined' && (data['class'].indexOf('unseen') >= 0 || data['class'].indexOf('recent') >= 0))
		{
			if (typeof data.flags != 'undefined') data.flags.read = 'read';
			data['class'] = data['class'].split(' ').filter((className) => className != 'unseen' && className != 'recent').join(' ');
			this.patchRow(rowId);
			// reduce counter without server roundtrip
			this.reduceCounterWithoutServerRoundtrip();
			if (typeof data.dispositionnotificationto != 'undefined' && data.dispositionnotificationto &&
				typeof data.flags.mdnsent == 'undefined' && typeof data.flags.mdnnotsent == 'undefined')
			{
				var buttons = [
					{label: this.egw.lang("Yes"), id: "mdnsent", image: "check"},
					{label: this.egw.lang("No"), id: "mdnnotsent", image: "cancelled"}
				];
				Et2Dialog.show_dialog((_button_id, _value) =>
					{
						switch (_button_id)
						{
							case "mdnsent":
								egw.jsonq('mail.mail_ui.ajax_sendMDN', [messages]);
								this.trySetMdnFlag(messages, true);
								return;
							case "mdnnotsent":
								this.trySetMdnFlag(messages, false);
						}
					},
				this.egw.lang("The message sender has requested a response to indicate that you have read this message. Would you like to send a receipt?"),
				this.egw.lang("Confirm"),
				messages, buttons);
			}
			egw.jsonq('mail.mail_ui.ajax_flagMessages',['read', messages, false]);
		}
	}

	protected setupViewAttachmentActions(data, sel_options)
	{
		const actions = [
			{
				id: 'downloadOneAsFile',
				label: 'Download',
				icon: 'fileexport',
				value: 'downloadOneAsFile'
			},
			{
				id: 'saveOneToVfs',
				label: 'Save to Filemanager',
				icon: 'filemanager/navbar',
				value: 'saveOneToVfs'
			},
			{
				id: 'saveAllToVfs',
				label: 'Save all attachments to Filemanager',
				icon: 'mail/save_all',
				value: 'saveAllToVfs'
			},
			{
				id: 'downloadAllToZip',
				label: 'Save as ZIP',
				icon: 'mail/save_zip',
				value: 'downloadAllToZip'
			},
			{
				id: 'forward',
				label: 'Forward to',
				icon: 'mail/forward',
				value: 'forward'
			}
		];
		const collabora = {
			id: 'collabora',
			label: 'Collabora',
			icon: 'collabora/navbar',
			value: 'collabora'
		};
		data.attachmentsBlockTitle = data.attachmentsBlock.length > 1 ? `+${data.attachmentsBlock.length - 1}` : '';
		sel_options.attachmentsBlock = {};
		data.attachmentsBlock.forEach(_item =>
		{
			_item.actions = 'downloadOneAsFile';
			// for some reason label needs to be set explicitly for the dropdown button. It needs more investigation.
			_item.actionsDefaultLabel = 'Download';

			if(typeof this.egw.user('apps')['collabora'] !== "undefined" && !egwIsMobile() && this.egw.isCollaborable(_item.type))
			{
				// Start with download on top, Collabora on bottom
				sel_options.attachmentsBlock[_item.attachment_number + "[actions]"] = [...actions, collabora];

				if(egw.preference('document_doubleclick_action', 'filemanager') === 'collabora')
				{
					_item.actions = 'collabora';
					_item.actionsDefaultLabel = 'Collabora';
					// Put Collabora on top
					sel_options.attachmentsBlock[_item.attachment_number + "[actions]"] = [collabora, ...actions];
				}
			}
			// if mime-type is supported by invoices (or the EPL viewer), add it at the end
			const invoices_app = this.egw.user('apps')['invoices'] ? 'invoices' : 'stylite';
			if(egw.get_mime_info(_item.type, invoices_app))
			{
				sel_options.attachmentsBlock[_item.attachment_number + "[actions]"] = [...actions, {
					id: invoices_app,
					label: 'invoices',
					icon: 'invoices/navbar',
					value: invoices_app
				}];
			}
		});

		sel_options.attachmentsBlock.actions = actions;
	}

	/**
	 * Load a message body into an iframe widget: try the fast client-side JMAP body-fetch first
	 * (mail/js/jmap.ts's MailJmap.fetchBody()), falling back to the existing full server-rendered
	 * page load - identical fallback behaviour to before this feature - for special-case messages
	 * (S/MIME, winmail.dat, meeting invites, PGP/MIME) or any fetch failure.
	 *
	 * Deliberately does not call resolveExternalImages() itself - the two call sites (preview
	 * panel, popup) already did that differently (popup skips it for meeting-invite content) - left
	 * to $onLoad, same as before this method existed. resolveInlineImages() (cid: images) has no
	 * such per-caller difference, so it *is* called here, for the fast path only (the fallback path
	 * still resolves cid: images server-side, same as always).
	 *
	 * @param iframeWidget the et2 iframe widget (messageIFRAME)
	 * @param rowId
	 * @param onLoad called with the iframe's contentDocument once loaded, either path
	 * @param signal aborted if a newer selection supersedes this fetch before it resolves; when
	 *        given, the result is also dropped if rowId no longer matches mail_currentlyFocussed
	 */
	private loadMessageBody(iframeWidget: any, rowId: string, onLoad: (doc: Document) => void, signal?: AbortSignal): void
	{
		const iframe = iframeWidget.getDOMNode() as HTMLIFrameElement;
		this.jmap.fetchBody(rowId, undefined, signal).then((result) =>
		{
			// superseded by a newer selection while this request was in flight - drop it
			if (signal?.aborted) return;
			// belt-and-suspenders alongside the abort check above, scoped to the preview-pane
			// call site (the only one that passes a signal) - mobileView()'s single-message
			// dialog has no comparable "currently selected" concept to check against.
			if (signal && rowId !== this.mail_currentlyFocussed) return;
			if (result.special)
			{
				iframe.addEventListener('load', () =>
				{
					const doc = iframe.contentWindow.document;
					doc.documentElement.dataset.rowId = rowId;
					onLoad(doc);
				}, {once: true});
				iframeWidget.set_src(egw.link('/index.php', {menuaction: 'mail.mail_ui.loadEmailBody', _messageID: rowId}));
				return;
			}
			// explicit cast, not relying on control-flow narrowing of the "special" discriminant -
			// this project's tsconfig has strictNullChecks off, where that narrowing doesn't hold
			const fast = result as Extract<JmapBodyResult, { special : false }>;
			iframe.addEventListener('load', () =>
			{
				const doc = iframe.contentWindow.document;
				doc.documentElement.dataset.rowId = rowId;
				this.jmap.resolveInlineImages(doc, rowId, fast).catch((e) =>
					console.error('MailApp.loadMessageBody(): resolveInlineImages failed', e));
				onLoad(doc);
			}, {once: true});
			iframe.srcdoc = fast.html;
		});
	}

		/**
		 * Show external images
		 * @param _node
		 * @param show True to show images, otherwise use preferences
		 */
		resolveExternalImages(_node, show = null)
	{
		let image_proxy = this.image_proxy;
		//Do not run resolve images if it's forced already to show them all
		// or forced to not show them all.
		var pref_img = egw.preference('allowExternalIMGs', 'mail');
		if (!show && pref_img == 0)
		{
			return;
		}

		var external_images = jQuery(_node).find('img[alt*="[blocked external image:"]');
		if (external_images.length > 0 && jQuery(_node).find('.mail_externalImagesMsg').length == 0)
		{
			var container = jQuery(document.createElement('div'))
					.click(function(){jQuery(this).remove();})
					.addClass('mail_externalImagesMsg');
			var getUrlParts = function (_rawUrl) {
				var u = _rawUrl.split('[blocked external image:');
				u = u[1].replace(']','');
				var url = u;
				var protocol = '';
				if (u.substr(0,7) == 'http://')
				{
					u = u.replace ('http://','');
					url = url.replace('http://', image_proxy);
					protocol = 'http';
				}
				else if (u.substr(0,8) == 'https://')
				{
					u = u.replace ('https://','');
					protocol = 'https';
				}
				var url_parts = u.split('/');
				return {
					url: url,
					domain: url_parts[0],
					protocol: protocol
				};
			};

			var host = getUrlParts(external_images[0].alt);
			var showImages = function (_images, _save)
			{
				var save = _save || false;
				_images.each(function(i, node) {
					var parts = getUrlParts (node.alt);
					if (save)
					{
						if (pref && pref.length)
						{
							if (pref.indexOf(parts.domain) == -1)
							{
								pref.push(parts.domain);
								egw.set_preference( 'mail', 'allowExternalDomains', pref);
							}
						}
						else
						{
							pref = [parts.domain];
							egw.set_preference( 'mail', 'allowExternalDomains', pref);
						}
					}
					node.src = parts.url;
				});
			};
			if (show == true)
			{
				return showImages(external_images, false);
			}
			var pref = egw.preference('allowExternalDomains', 'mail') || {};
			pref = Object.values(pref);
			if (pref.indexOf(host.domain)>-1)
			{
				showImages (external_images);
				return;
			}
			let message = this.egw.lang('In order to protect your privacy all external sources within this email are blocked.');
			for(let i in external_images)
			{
				if (!external_images[i].alt) continue;
				let r = getUrlParts(external_images[i].alt);
				if (r && r.protocol == 'http')
				{
					message = this.egw.lang('This mail contains external images served via insecure HTTP protocol. Be aware showing or allowing them can compromise your security!');
					container.addClass('red');
					break;
				}
			}

			// Use flag in print button action to keep images
			const toolbar = egw_getActionManager('toolbar', false) ?? egw_getActionManager('displayToolbar', false);
			if (toolbar)
			{
				const print = toolbar.getActionById('print');
				if (print)
				{
					delete print.data.images;
				}
			}

			jQuery(document.createElement('p'))
					.text(message)
					.appendTo(container);
			jQuery(document.createElement('button'))
					.addClass ('closeBtn')
					.click (function (){
						container.remove();
					})
					.appendTo(container);
			jQuery(document.createElement('button'))
					.text(this.egw.lang('Allow'))
					.attr ('title', this.egw.lang('Always allow external sources from %1', host.domain))
					.click (function (){
						showImages(external_images, true);
						container.remove();
					})
					.appendTo(container);
			jQuery(document.createElement('button'))
					.text(this.egw.lang('Show'))
					.attr ('title', this.egw.lang('Show them this time only'))
				.click(() =>
				{
					showImages(external_images);
					container.remove();
					if (_node.querySelector("body"))
					{
						_node.querySelector("body").dispatchEvent(new Event('load'));
					}
					const print = toolbar.getActionById('print');
					if (print)
					{
						if (!print.data)
						{
							print.data = {};
						}
						print.data.images = true;
						// Reload temp print

					}
				})
				.appendTo(container);
			container.appendTo(_node.body? _node.body:_node);
		}
	}

	/**
	 * If a preview header is partially hidden, this is the handler for clicking the
	 * expand button that shows all the content for that header.
	 * The button must be directly after the widget to be expanded in the template.
	 * The widget to be expended is set in the event data.
	 *
	 * requires: mainWindow, one mail selected for preview
	 *
	 * @param {jQuery event} event
	 * @param {Object} widget
	 * @param {DOMNode} button
	 */
	showAllHeader(event,widget,button) {
		// Show list as a list
		var list = jQuery(button).prev();
	/*	if (list.length <= 0)
		{
			list = jQuery(button.target).prev();
		}*/

		list.toggleClass('visible');

		// Revert if user clicks elsewhere
		jQuery('body').one('click', list, function(ev) {
			ev.data.removeClass('visible');
		});
	}

	setMailBody(content) {
		var IframeHandle = this.et2.getWidgetById('messageIFRAME');
		IframeHandle.set_value('');
	}

	/**
	 * refreshFolderStatus, function to call to read the counters of a folder and apply them
	 *
	 * @param {string} _nodeID
	 * @param {string} mode
	 * @param {boolean} _refreshGridArea
	 * @param {boolean} _refreshQuotaDisplay
	 *
	 */
	refreshFolderStatus(_nodeID: string, mode: string, _refreshGridArea = true, _refreshQuotaDisplay = true)
	{
		let nodeToRefresh: string | 0 = 0;
		let mode2use = "none";
		if (_nodeID) nodeToRefresh = _nodeID;
		if (mode) {
			if (mode == "forced") {mode2use = mode;}
		}
		try
		{
			if(!this.tree_wdg){
				this.tree_wdg = this.et2.getWidgetById(this.nm_index+'[foldertree]');
			}

			const activeFolders = this.tree_wdg.getTreeNodeOpenItems(nodeToRefresh,mode2use);
			//alert(activeFolders.join('#,#'));
			this.queueRefreshFolderList((mode=='thisfolderonly'&&nodeToRefresh?[_nodeID]:activeFolders));
			if (_refreshGridArea)
			{
				// maybe to use the mode forced as trigger for grid reload and using the grids own autorefresh
				// would solve the refresh issue more accurately
				//if (mode == "forced") this.refreshMessageGrid();
				this.refreshMessageGrid();
			}
			if (_refreshQuotaDisplay)
			{
				this.refreshQuotaDisplay();
			}
		} catch(e) {
		} // ignore the error; maybe the template is not loaded yet
	}

	/**
	 * refreshQuotaDisplay, function to call to read the quota for the active server
	 *
	 * Tries MailJmap.getQuota() (direct JMAP, no server round-trip at all) first - falls back to
	 * the classic ajax_refreshQuotaDisplay() round-trip only if that declines, which now only
	 * happens for a real JMAP server not advertising the Quota extension (a genuinely different
	 * capability, worth trying via classic IMAP) - an unreachable account gets a "not reachable"
	 * display directly from getQuota() instead of falling back (see its own docblock for why).
	 *
	 * @param {object} _server omitting uses the currently active profile
	 *
	 */
	refreshQuotaDisplay(_server?: any)
	{
		// same "not always set, read it from foldertree" fallback fetchRows()/buildJmapQuery()
		// already use for resolving the currently active profile client-side
		const profileID = String(_server ||
			this.et2?.getWidgetById(this.nm_index + '[foldertree]')?.getValue() ||
			this.egw.preference('ActiveProfileID', 'mail') || '').split('::')[0];

		const classicFallback = () => egw.json('mail.mail_ui.ajax_refreshQuotaDisplay', [_server]).sendRequest(true);

		if (!profileID)
		{
			classicFallback();
			return;
		}
		this.jmap.getQuota(profileID).then((data) =>
		{
			if (data)
			{
				this.setQuotaDisplay(data);
				return;
			}
			classicFallback();
		});
	}

	/**
	 * setQuotaDisplay, function to call to read the quota for the active server
	 *
	 * @param {object} _data
	 *
	 */
	setQuotaDisplay(_data)
	{
		if (!this.et2 && !this.checkET2()) return;

		var quotabox = this.et2.getWidgetById(this.nm_index+'[quotainpercent]');

		// Check to make sure it's there
		if(quotabox)
		{
			//try to set it via set_value and set label
			quotabox.set_class(_data.data.quotaclass);
			quotabox.set_value(_data.data.quotainpercent);
			quotabox.set_label(_data.data.quota);
			if (_data.quotawarning)
			{
				var self = this;
				var buttons = [
					{label: this.egw.lang("Empty Trash and Junk"), id: "cleanup", class: "ui-priority-primary", default: true, image: "delete"},
					{label: this.egw.lang("Cancel"), id: "cancel", image:'cancelDialog'}
				];
				var server = [{iface:{id: _data.data.profileid+'::'}}];
				Et2Dialog.show_dialog(function (_button_id)
					{
						if (_button_id == "cleanup")
						{
							self.emptySpam(null, server);
							self.emptyTrash(null, server);
						}
						return;
					},
					this.egw.lang("Your remaining quota %1 is too low, you may not be able to send/receive further emails.\n Although cleaning up emails in trash or junk folder might help you to get some free space back.\n If that didn't help, please ask your administrator for more quota.", _data.data.quotafreespace),
					this.egw.lang("Mail cleanup"),
					'', buttons, Et2Dialog.WARNING_MESSAGE);
			}
		}
	}

	/**
	 * callRefreshVacationNotice, function to call the serverside function to refresh the vacationnotice for the active server
	 *
	 * @param {object} _server
	 *
	 */
	callRefreshVacationNotice(_server?)
	{
		egw.jsonq('mail_ui::ajax_refreshVacationNotice',[_server]);
	}
	/**
	 * Make sure attachments have all needed data, so they can be found for
	 * HTML5 native dragging
	 *
	 * @param {string} mail_id Mail UID
	 * @param {array} attachments Attachment information.
	 */
	registerForDrag(mail_id, attachments)
	{
		// Put required info in global store
		var data = {};
		if (!attachments) return;
		for (let i = 0; i < attachments.length; i++)
		{
			data = attachments[i] || {};
			if(!data.filename || !data.type) continue;

			// Add required info
			data.mime = data.type;
			data.download_url = egw.link('/index.php', {
				menuaction: 'mail.mail_ui.getAttachment',
				id: mail_id,
				part: data.partID,
				is_winmail: data.winmailFlag
			});
			data.name = data.filename;
		}
	}

	/**
	 * Display helper for dragging attachments
	 *
	 * @param {egwAction} _action
	 * @param {egwActionElement[]} _elems
	 * @returns {DOMNode}
	 */
	dragAttachment(_action, _elems)
	{
		var div = jQuery(document.createElement("div"))
			.css({
				position: 'absolute',
				top: '0px',
				left: '0px',
				width: '300px'
			});

		var data = _elems[0].data || {};

		var text = jQuery(document.createElement('div')).css({left: '30px', position: 'absolute'});
		// add filename or number of files for multiple files
		text.text(_elems.length > 1 ? _elems.length+' '+this.egw.lang('files') : data.name || '');
		div.append(text);

		// Add notice of Ctrl key, if supported
		if(window.FileReader && 'draggable' in document.createElement('span') &&
			navigator && navigator.userAgent.indexOf('Chrome') >= 0)
		{
			var key = ["Mac68K","MacPPC","MacIntel"].indexOf(window.navigator.platform) < 0 ? 'Ctrl' : 'Command';
			text.append('<br />' + this.egw.lang('Hold %1 to drag files to your computer',key));
		}
		return div;
	}

	/**
	 * refreshVacationNotice, function to call with appropriate data to refresh the vacationnotice for the active server
	 *
	 * @param {object} _data
	 *
	 */
	refreshVacationNotice(_data)
	{
		if (!this.et2 && !this.checkET2()) return;
		if (_data == null)
		{
			this.et2.getWidgetById('mail.index.vacationnotice')?.set_disabled(true);
			this.et2.getWidgetById(this.nm_index+'[vacationnotice]').set_value('');
			this.et2.getWidgetById(this.nm_index+'[vacationrange]').set_value('');
		}
		else
		{
			this.et2.getWidgetById('mail.index.vacationnotice')?.set_disabled(false);
			this.et2.getWidgetById(this.nm_index+'[vacationnotice]').set_value(_data.vacationnotice);
			this.et2.getWidgetById(this.nm_index+'[vacationrange]').set_value(_data.vacationrange);
		}
	}

	/**
	 * Enable or disable the date filter
	 *
	 * If the searchtype (cat_id) is set to something that needs dates, we enable the
	 * header_right template.  Otherwise, it is disabled.
	 *
	 * @param ev : Event|undefined
	 * @param filter : Et2Select cat_id filter
	 */
	searchtypeChange(ev, filter)
	{
		const nm = this.et2.getWidgetById(this.nm_index);
		const dates = this.et2.getWidgetById('mail.index.dates');
		if(nm && filter)
		{
			switch(filter.value)
			{
				case 'bydate':
					if (filter && dates)
					{
						dates.set_disabled(false);
						const filterDrawer = filter.closest('egw-app').filtersDrawer;
						if (ev && filterDrawer && !filterDrawer.open)
						{
							filterDrawer.open = true;
						}
						ev && window.setTimeout(() => dates.getWidgetById('startdate').focus());
					}
					this.callRefreshVacationNotice();
					return true;
				default:
					if (dates)
					{
						dates.set_disabled(true);
					}
					this.callRefreshVacationNotice();
					return true;
			}
		}
		return false;
	}

	/**
	 * refreshFilter2Options, function to call with appropriate data to refresh the filter2 options for the active server
	 *
	 * @param {object} _data
	 *
	 */
	refreshFilter2Options(_data)
	{
		//alert('refreshFilter2Options');
		if (_data == null) return;
		if (!this.et2 && !this.checkET2()) return;

		var filter2 = this.et2.getWidgetById('filter2');
		var current = filter2.value;
		var currentexists=false;
		for (var k in _data)
		{
			if (k==current) currentexists=true;
		}
		if (!currentexists) filter2.set_value('');
		filter2.set_select_options(_data);
	}

	/**
	 * refreshFilterOptions, function to call with appropriate data to refresh the filter options for the active server
	 *
	 * @param {object} _data
	 *
	 */
	refreshFilterOptions(_data)
	{
		//alert('refreshFilterOptions');
		if (_data == null) return;
		if (!this.et2 && !this.checkET2()) return;

		var filter = this.et2.getWidgetById('filter');
		var current = filter.value;
		var currentexists=false;
		for (var k in _data)
		{
			if (k==current) currentexists=true;
		}
		if (!currentexists) filter.set_value('');
		filter.set_select_options(_data);

	}

	/**
	 * refreshCatIdOptions, function to call with appropriate data to refresh the filter options for the active server
	 *
	 * @param {object} _data
	 *
	 */
	refreshCatIdOptions(_data)
	{
		//alert('refreshCatIdOptions');
		if (_data == null) return;
		if (!this.et2 && !this.checkET2()) return;

		var filter = this.et2.getWidgetById('cat_id');
		var current = filter.value;
		var currentexists=false;
		for (var k in _data)
		{
			if (k==current) currentexists=true;
		}
		if (!currentexists) filter.set_value('');
		filter.set_select_options(_data);

	}

	/**
	 * Queues a refreshFolderList request for 500ms. Actually this will just execute the
	 * code after the calling script has finished.
	 *
	 * @param {array} _folders description
	 */
	queueRefreshFolderList(_folders)
	{
		var self = this;
		// as jsonq is too fast wrap it to be delayed a bit, to ensure the folder actions
		// are executed last of the queue
		window.setTimeout(function() {
			egw.jsonq('mail.mail_ui.ajax_setFolderStatus',[_folders], function (){self.unlockTree();});
		}, 500);
	}

	/**
	 * checkFolderNoSelect - implementation of the checkFolderNoSelect action to control right click options on the tree
	 *
	 * @param {object} action
	 * @param {object} _senders the representation of the tree leaf to be manipulated
	 * @param {object} _currentNode
	 */
	checkFolderNoSelect(action,_senders,_currentNode) {

		// Abort if user selected an un-selectable node
		// Use image over anything else because...?
		var ftree, node;
		ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		if (ftree)
		{
			node = ftree.getNode(_senders[0].id);
		}

		if (node && node?.im0?.indexOf('NoSelect') !== -1)
		{
			//ftree.reSelectItem(_previous);
			return false;
		}

		return true;
	}

	/**
	 * Check if SpamFolder is enabled on that account
	 *
	 * SpamFolder enabled is stored as data { spamfolder: true/false } on account node.
	 *
	 * @param {object} _action
	 * @param {object} _senders the representation of the tree leaf to be manipulated
	 * @param {object} _currentNode
	 */
	spamfolderEnabled(_action,_senders,_currentNode)
	{
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var acc_id = _senders[0].id.split('::')[0];
		var node = ftree ? ftree.getNode(acc_id) : null;

		return node && node.data && node.data.spamfolder;
	}


	/**
	 * Check if archiveFolder is enabled on that account
	 *
	 * ArchiveFolder enabled is stored as data { archivefolder: true/false } on account node.
	 *
	 * @param {object} _action
	 * @param {object} _senders the representation of the tree leaf to be manipulated
	 * @param {object} _currentNode
	 */
	archivefolderEnabled(_action,_senders,_currentNode)
	{
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var acc_id = _currentNode.id.split('::')[2]; // this is operating on mails
		var node = ftree && acc_id ? ftree.getNode(acc_id) : null;

		return node && node.data && node.data.archivefolder;
	}

	/**
	 * Check if Sieve is enabled on that account
	 *
	 * Sieve enabled is stored as data { sieve: true/false } on account node.
	 *
	 * @param {object} _action
	 * @param {object} _senders the representation of the tree leaf to be manipulated
	 * @param {object} _currentNode
	 */
	sieveEnabled(_action,_senders,_currentNode)
	{
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var acc_id = _senders[0].id.split('::')[0];
		var node = ftree ? ftree.getNode(acc_id) : null;

		return node && node.data && node.data.sieve;
	}

	/**
	 * Check if ACL is enabled on that account
	 *
	 * ACL enabled is stored as data { acl: true/false } on INBOX node.
	 * We also need to check if folder is marked as no-select!
	 *
	 * @param {object} _action
	 * @param {object} _senders the representation of the tree leaf to be manipulated
	 * @param {object} _currentNode
	 */
	aclEnabled(_action,_senders,_currentNode)
	{
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var inbox = _senders[0].id.split('::')[0]+'::INBOX';
		var node = ftree ? ftree.getNode(inbox) : null;

		return node && node.data && node.data.acl && this.checkFolderNoSelect(_action,_senders,_currentNode);
	}

	/**
	 * setFolderStatus, function to set the status for the visible folders
	 *
	 * @param {array} _status
	 *
	 * type _status =
	 * {'folderId':{displayName:String, unseenCount?:number}}
	 */
	setFolderStatus(_status) {
		if (!this.et2 && !this.checkET2()) return;
		const ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		if (!ftree) return;
		for (const folderId in _status) {
			//ftree.setLabel(folderId,_status[folderId]["displayName"]);
			// display folder-name bold for unseen mails
			if(_status[folderId] ===0 || _status[folderId] ==="0") _status[folderId] = null;
			if(_status[folderId])
			{
				ftree.setClass(folderId, 'unread','+');
			}else if(!_status[folderId] || _status[folderId] ===0 || _status[folderId] ==="0") {
				ftree.setClass(folderId, 'unread','-');
				_status[folderId]=null;
			}
			ftree.set_badge(folderId,_status[folderId]);
			//alert(i +'->'+_status[i]);
		}
	}

	/**
	 * setLeaf, function to set the id and description for the folder given by status key
	 * @param {array} _status status array with the required data (new id, desc, old desc)
	 *		key is the original id of the leaf to change
	 *		multiple sets can be passed to setLeaf
	 */
	setLeaf(_status) {
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
            var selectedNode = ftree.getSelectedItem();
		for (var i in _status)
		{
			// if olddesc is undefined or #skip# then skip the message, as we process subfolders
			if (typeof _status[i]['olddesc'] !== 'undefined' && _status[i]['olddesc'] !== '#skip-user-interaction-message#') this.egw.message(this.egw.lang("Renamed Folder %1 to %2",_status[i]['olddesc'],_status[i]['desc']), 'success');
			ftree.renameItem(i,_status[i]['id'],_status[i]['desc']);
			ftree.setStyle(i, 'font-weight: '+(_status[i]['desc'].match(this._unseen_regexp) ? 'bold' : 'normal'));
			//alert(i +'->'+_status[i]['id']+'+'+_status[i]['desc']);
			if (_status[i]['id']==selectedNode.id)
			{
				var nm = this.et2.getWidgetById(this.nm_index);
				nm.applyFilters({selectedFolder: _status[i]['id']});
			}
		}
	}

	/**
	 * removeLeaf, function to remove the leaf represented by the given ID
	 * @param {array} _status status array with the required data (KEY id, VALUE desc)
	 *		key is the id of the leaf to delete
	 *		multiple sets can be passed to mail_deleteLeaf
	 */
	removeLeaf(_status) {
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var selectedNode = ftree.getSelectedNode();
		for (var i in _status)
		{
			// if olddesc is undefined or #skip# then skip the message, as we process subfolders
			if (typeof _status[i] !== 'undefined' && _status[i] !== '#skip-user-interaction-message#') this.egw.message(this.egw.lang("Removed Folder %1 ",_status[i]), 'success');
			ftree.deleteItem(i,(selectedNode.id==i));
			var selectedNodeAfter = ftree.getSelectedNode();
			//alert(i +'->'+_status[i]['id']+'+'+_status[i]['desc']);
			if (selectedNodeAfter.id!=selectedNode.id && selectedNode.id==i)
			{
				var nm = this.et2.getWidgetById(this.nm_index);
				nm.applyFilters({selectedFolder: selectedNodeAfter.id});
			}
		}
	}

	/**
	 * reloadNode, function to reload the leaf represented by the given ID
	 * @param {Object.<string,string>|Object.<string,Object}}  _status
	 *		Object with the required data (KEY id, VALUE desc), or ID => {new data}
	 */
	reloadNode(_status) {
		var ftree = this.et2?this.et2.getWidgetById(this.nm_index+'[foldertree]'):null;
		if (!ftree) return;
		var selectedNode = ftree.getSelectedNode();
		for (var i in _status)
		{
			// if olddesc is undefined or #skip# then skip the message, as we process subfolders
			if (typeof _status[i] !== 'undefined' && _status[i] !== '#skip-user-interaction-message#')
			{
				this.egw.message(this.egw.lang((typeof _status[i].parent !== 'undefined' ? "Reloaded Folder %1" : "Reloaded Account %1"),
					(typeof _status[i] == "string" ? _status[i].replace(this._unseen_regexp, '') :
							(_status[i].text ? _status[i].text.replace(this._unseen_regexp, '') : _status[i].id))), 'success');
			}
			ftree.refreshItem(i,typeof _status[i] == "object" ? _status[i] : null);
			if (typeof _status[i] == "string") ftree.setStyle(i, 'font-weight: '+(_status[i].match(this._unseen_regexp) ? 'bold' : 'normal'));
		}

		var selectedNodeAfter = ftree.getSelectedNode();

		// If selected folder changed, refresh nextmatch
		if (selectedNodeAfter != null && selectedNodeAfter.id!=selectedNode.id)
		{
			var nm = this.et2.getWidgetById(this.nm_index);
			nm.applyFilters({selectedFolder: selectedNodeAfter.id});
		}
	}

	/**
	 * refreshMessageGrid, function to call to reread ofthe current folder
	 *
	 * @param {boolean} _isPopup
	 * @param {boolean} _refreshVacationNotice
	 */
	refreshMessageGrid(_isPopup: boolean = false, _refreshVacationNotice: boolean = false)
	{
		let nm: Et2Nextmatch;
		if (_isPopup && !this.mail_isMainWindow)
		{
			nm = window.opener.etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById(this.nm_index);
		}
		else
		{
			nm = this.et2.getWidgetById(this.nm_index);
		}
		const dates = this.et2.getWidgetById('mail.index.datefilter');
		const filter = this.et2.getWidgetById('cat_id');
		if(nm && filter)
		{
			const filters: any = {startdate: null, enddate: null};
			switch(filter.getValue())
			{
				case 'bydate':

					if (filter && dates)
					{
						if (this.et2.getWidgetById('startdate') && this.et2.getWidgetById('startdate').get_value()) filters.startdate = this.et2.getWidgetById('startdate').value;
						if (this.et2.getWidgetById('enddate') && this.et2.getWidgetById('enddate').get_value()) filters.enddate = this.et2.getWidgetById('enddate').value;
					}
			}
			nm.applyFilters(filters); // this should refresh the active folder
		}
		if (_refreshVacationNotice) this.callRefreshVacationNotice();
	}

	/**
	 * getMsg - gets the current Message
	 * @return string
	 */
	getMsg()
	{
		var msg_wdg = this.et2.getWidgetById('msg');
		if (msg_wdg)
		{
			return msg_wdg.valueOf().htmlNode[0].innerHTML;
		}
		return "";
	}

	/**
	 * setMsg - sets a Message, with the msg container, and controls if the container is enabled/disabled
	 * @param {string} myMsg - the message
	 */
	setMsg(myMsg)
	{
		var msg_wdg = this.et2.getWidgetById('msg');
		if (msg_wdg)
		{
			msg_wdg.set_value(myMsg);
			msg_wdg.set_disabled(myMsg.trim().length==0);
		}
	}

	/**
	 * Delete mails
	 * takes in all arguments
	 * @param _action
	 * @param _elems
	 */
	deleteMessage(_action,_elems)
	{
		this.checkAllSelected(_action,_elems,null,true);
	}

	/**
	 * call Delete mails
	 * takes in all arguments
	 * @param {object} _action
	 * @param {array} _elems
	 * @param {boolean} _allMessagesChecked
	 */
	callDelete(_action,_elems,_allMessagesChecked)
	{
		var calledFromPopup = false;
		if (typeof _allMessagesChecked == 'undefined') _allMessagesChecked=false;
		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			calledFromPopup = true;
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				_elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					_elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		var msg = this.getFormData(_elems);
		msg['all'] = _allMessagesChecked;
		if (msg['all']=='cancel') return false;
		if (msg['all']) msg['activeFilters'] = this.getActiveFilters(_action);
		//alert(_action.id+','+ msg);
		this.deleteMessages(msg,'no',calledFromPopup);
		if (calledFromPopup && this.mail_isMainWindow==false)
		{
			egw(window).close();
		}
		else if (typeof this.et2_view!='undefined' && typeof this.et2_view.close == 'function')
		{
			this.et2_view.close();
		}
	}

	/**
	 * function to find (and reduce) unseen count from folder-name
	 */
	reduceCounterWithoutServerRoundtrip()
	{
		const ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		const _foldernode = ftree?.getSelectedItem();
		let counter = _foldernode?.badge;
		let icounter = 0;
		if (counter) icounter = parseInt(counter);
		if (icounter>0)
		{
			let newcounter = icounter - 1;
			if (newcounter === 0)
			{
				newcounter = null;
				ftree.setClass(_foldernode.id, 'unread','-');
			}
			ftree.set_badge(_foldernode.id, newcounter?.toString());
		}
	}

	/**
	 * Regular expression to find (and remove) unseen count from folder-name
	 */
	_unseen_regexp = / \([0-9]+\)$/;

	/**
	 * splitRowId
	 *
	 * @param {string} _rowID
	 *
	 */
	splitRowId(_rowID)
	{
		var res = _rowID.split('::');
		// as a rowID is perceeded by app::, should be mail!
		if (res.length==4 && !isNaN(parseInt(res[0])))
		{
			// we have an own created rowID; prepend app=mail
			res.unshift('mail');
		}
		return res;
	}

	/**
	 * Delete mails - actually calls the backend function for deletion
	 *
	 * Most other apps we tell the server directly, then refresh() tells the nextmatch to remove the rows.  Nextmatch
	 * then removes the rows & selects the next row for focus.  In mail we tell the nextmatch to remove the rows
	 * immediately and keep track of the rows above & below the deleted row(s), not setting focus to a new row.
	 * Then tell the server, and if the user presses up or down arrow in the next 10s, we focus the above or below row.
	 * Mail keeps its delayed-arrow selection behaviour via the nextmatch delete event.
	 *
	 * @param {string} _msg - message list
	 * @param {object} _action - optional action
	 * @param {object} _calledFromPopup
	 */
	/**
	 * Shared catch handler for every mail_tryJmapXxx() fast-path wrapper: a JmapUserError means
	 * JMAP was actually reached and gave a definitive answer (a real ["error",...] response, or a
	 * Mailbox/set|Email/set per-item SetError) - rethrown rather than also attempting the classic
	 * fallback, which would very likely fail the same way for the same reason. Any other caught
	 * value (network failure, ineligible account) keeps today's silent-fallback behaviour
	 * unchanged - falls back to the classic ajax call, whose own success/failure is what the
	 * returned promise now settles with.
	 *
	 * Deliberately shows no message and touches no UI itself - every caller optimistically
	 * changes something before firing the request that ends up here, so only the caller knows
	 * what needs reconciling on failure. Callers must catch the promise this feeds into, show
	 * the resulting error (e.message for a JmapUserError, a generic one otherwise), and reconcile
	 * their own optimistic change - this used to just swallow the error via a "silently keep the
	 * optimistic UI change" default, leaving the UI showing something that was never actually
	 * confirmed server-side.
	 *
	 * @param e the caught rejection
	 * @param fallback the classic ajax call to run when e is NOT a JmapUserError
	 */
	private mail_handleJmapError(e : any, fallback : () => any) : any
	{
		if (e instanceof JmapUserError)
		{
			throw e;
		}
		console.error('MailApp: JMAP action failed, falling back to classic', e);
		return fallback();
	}

	/**
	 * Try the fast client-side JMAP delete path - MailJmap.deleteMessages() for an explicit
	 * selection, or deleteAllMatching() for "select all matching the current filter". Returns null
	 * if not applicable at all (caller falls back to the unchanged ajax_deleteMessages() call
	 * directly); otherwise a Promise that either succeeds via JMAP or, on any failure, falls back
	 * to that same classic call internally - so the caller can treat the return value uniformly
	 * (e.g. .finally()) either way.
	 */
	private tryJmapDelete(_msg : any, _action : any) : Promise<any> | null
	{
		const mode : 'trash' | 'destroy' = _action === 'remove_immediately' ? 'destroy' :
			_action === 'move_to_trash' ? 'trash' :
			(this.egw.preference('deleteOptions', 'mail') === 'remove_immediately' ? 'destroy' : 'trash');
		const fallback = () => egw.json('mail.mail_ui.ajax_deleteMessages',
			[_msg, (typeof _action == 'undefined' ? 'no' : _action)]).sendRequest(true);

		if (_msg['all'])
		{
			return this.jmap.deleteAllMatching(this.buildJmapQuery(_msg), mode)
				.catch((e) => this.mail_handleJmapError(e, fallback));
		}
		if (!Array.isArray(_msg['msg']) || !_msg['msg'].length)
		{
			return null;
		}
		let references : JmapMessageReference[];
		try
		{
			references = _msg['msg'].map((id : string) => this.jmap.messageReference(id));
		}
		catch (e)
		{
			return null;
		}
		return this.jmap.deleteMessages(references, mode)
			.catch((e) => this.mail_handleJmapError(e, fallback));
	}

	deleteMessages(_msg,_action,_calledFromPopup)
	{
		let message, ftree, _foldernode, displayname;
		if (_calledFromPopup)
		{
			// getting reference back to the main window
			//window.egw is global (main) egw
			//.window is the global main wiondow
			//.app.mail is the main mail app
			//.et2 is its etemplate containing the nextmatch and the folder tree
			//et2 of the mail app taken from the global window variables of the global egw
			ftree = window?.egw?.window?.app?.mail?.et2?.getWidgetById(this.nm_index + '[foldertree]');
		} else
		{
			ftree = this.et2.getWidgetById(this.nm_index + '[foldertree]');
		}
		if (ftree)
		{
                _foldernode = ftree.getSelectedItem();

                displayname = _foldernode.text.replace(this._unseen_regexp, '');
            } else {
			message = this.splitRowId(_msg['msg'][0]);
			if (message[3]) _foldernode = displayname = atob(message[3]);
		}
		// Mail only selects an adjacent row after the user's next arrow key.
		const nm = _calledFromPopup ?
			window?.egw?.window?.app?.mail?.et2?.getWidgetById(this.nm_index) :
			this.et2.getWidgetById(this.nm_index);
		if (!_msg["all"])
		{
			this.refresh(nm, _msg["msg"], Et2DatagridUpdateTypes.DELETE);
		}

		// Tell server - fast client-side JMAP path for the common case (explicit selection, not
		// "select all matching the current filter"), falling back to the classic ajax call
		// unchanged for anything else. Reconciles the optimistic removal above on failure either
		// way (below) - a message that was never actually deleted server-side must come back,
		// not silently vanish until the next reload reveals it.
		Promise.resolve(this.tryJmapDelete(_msg, _action) ??
			egw.json('mail.mail_ui.ajax_deleteMessages', [_msg, (typeof _action == 'undefined' ? 'no' : _action)]).sendRequest(true))
			.then(() =>
			{
				if (_msg['all']) this.egw.refresh(this.egw.lang("deleted %1 messages in %2",(_msg['all']?egw.lang('all'):_msg['msg'].length),(displayname?displayname:egw.lang('current folder'))),'mail');//,ids,'delete');
				this.egw.message(this.egw.lang("deleted %1 messages in %2", (_msg['all'] ? egw.lang('all') : _msg['msg'].length), (displayname ? displayname : egw.lang('current Folder'))), 'success');
			})
			.catch((e) =>
			{
				this.egw.message(e?.message || this.egw.lang('Failed to delete messages'), 'error');
				if (!_msg['all']) nm.refresh();
			});
	}

	/**
	 * Delete mails show result - called from the backend function for display of deletionmessages
	 * takes in all arguments
	 * @param _msg - message list
	 */
	deleteMessagesShowResult(_msg)
	{
		// Update list

		//this.egw.message(_msg['egw_message']);
		if (_msg['all'])
		{
			this.egw.refresh(_msg['egw_message'],'mail');
		}
		else
		{
			for (var i = 0; i < _msg['msg'].length; i++)
			{
				this.egw.refresh(_msg['egw_message'], 'mail', _msg['msg'][i].replace(/mail::/, ''), 'delete');
			}
		}
	}

	/**
	 * retry to Delete mails
	 * @param responseObject ->
	 * 	 reason - reason to report
	 * 	 messageList
	 */
	retryForcedDelete(responseObject)
	{
		// Start a full list refresh to show current data
		const nm = this.et2.getWidgetById('nm');
		nm?.refresh();

		var reason = responseObject['response'];
		var messageList = responseObject['messageList'];
		if (confirm(reason))
		{
			this.deleteMessages(messageList,'remove_immediately');
		}
		else
		{
			this.egw.message(this.egw.lang('canceled deletion due to user interaction'), 'success');
		}
		this.refreshMessageGrid();
		this.preview();
	}

	/**
	 * UnDelete mailMessages
	 *
	 * @param _messageList
	 */
	undeleteMessages(_messageList) {
	// setting class of row, the old style
	}

	/**
	 * Try the fast client-side JMAP path (MailJmap.purgeFolder()) for "empty junk"/"empty trash" -
	 * always applicable (no "select all"/single-selection distinction, it's a whole-folder purge),
	 * but purgeFolder() throws if the profile has no junk/trash folder configured, or on any JMAP
	 * failure - either way this falls back to the given classic ajax call unchanged, which has its
	 * own completion callback (unlockTree()) already - not duplicated here. On success, replicates
	 * the two client-visible effects the classic call's server response used to push: clear the
	 * folder-tree badge (setFolderStatus - the folder is now empty) and, if the purged folder
	 * is the one currently displayed, refresh the grid (classic path's conditional egw.refresh()).
	 */
	private mail_tryJmapPurgeFolder(profileID : string, which : 'trash' | 'junk', selectedFolder : string,
		onSuccess : () => void, fallback : () => Promise<any>) : Promise<any>
	{
		return this.jmap.purgeFolder(profileID, which).then((purgedFolder) =>
		{
			this.setFolderStatus({[purgedFolder]: 0});
			if (purgedFolder === selectedFolder)
			{
				this.refreshMessageGrid();
			}
			onSuccess();
		}, (e) => this.mail_handleJmapError(e, fallback));
	}

	/**
	 * emptySpam
	 *
	 * @param {object} action
	 * @param {object} _senders
	 */
	emptySpam(action,_senders) {
		var server = _senders[0].id.split('::');
		var activeFilters = this.getActiveFilters();
		var self = this;

		this.jmap.invalidateQuota(server[0]);
		this.egw.message(this.egw.lang('empty junk'), 'success');
		const classicEmptySpam = () => egw.json('mail.mail_ui.ajax_emptySpam',
			[server[0], activeFilters['selectedFolder']? activeFilters['selectedFolder']:null],
			function(){self.unlockTree();}).sendRequest(true);
		this.mail_tryJmapPurgeFolder(server[0], 'junk', activeFilters['selectedFolder'], () => self.unlockTree(), classicEmptySpam)
			.catch((e) => this.egw.message(e?.message || this.egw.lang('Failed to empty junk'), 'error'));

		// Directly delete any trash cache for selected server
		if(window.localStorage)
		{
			for(var i = 0; i < window.localStorage.length; i++)
			{
				var key = window.localStorage.key(i);

				// Find directly by what the key would look like
				if(key.indexOf('cached_fetch_mail::{"selectedFolder":"'+server[0]+'::') == 0 &&
					key.toLowerCase().indexOf(egw.lang('junk').toLowerCase()) > 0)
				{
					window.localStorage.removeItem(key);
				}
			}
		}
	}

	/**
	 * emptyTrash
	 *
	 * @param {object} action
	 * @param {object} _senders
	 */
	emptyTrash(action,_senders) {
		var server = _senders[0].id.split('::');
		var activeFilters = this.getActiveFilters();
		var self = this;

		this.jmap.invalidateQuota(server[0]);
		this.egw.message(this.egw.lang('empty trash'), 'success');
		const classicEmptyTrash = () => egw.json('mail.mail_ui.ajax_emptyTrash',
			[server[0], activeFilters['selectedFolder']? activeFilters['selectedFolder']:null],
			function(){self.unlockTree();}).sendRequest(true);
		this.mail_tryJmapPurgeFolder(server[0], 'trash', activeFilters['selectedFolder'], () => self.unlockTree(), classicEmptyTrash)
			.catch((e) => this.egw.message(e?.message || this.egw.lang('Failed to empty trash'), 'error'));

		// Directly delete any trash cache for selected server
		if(window.localStorage)
		{
			for(var i = 0; i < window.localStorage.length; i++)
			{
				var key = window.localStorage.key(i);

				// Find directly by what the key would look like
				if(key.indexOf('cached_fetch_mail::{"selectedFolder":"'+server[0]+'::') == 0 &&
					key.toLowerCase().indexOf(egw.lang('trash').toLowerCase()) > 0)
				{
					window.localStorage.removeItem(key);
				}
			}
		}
	}

	/**
	 * changeProfile
	 *
	 * @param {string} folder the ID of the selected Node -> should be an integer
	 * @param {object} _widget handle to the tree widget
	 * @param {boolean} getFolders Flag to indicate that the profile needs the mail
	 *		folders.  False means they're already loaded in the tree, and we don't need
	 *		them again
	 */
	changeProfile(folder,_widget, getFolders) {
		if(typeof getFolders == 'undefined')
		{
			getFolders = true;
		}
	//	alert(folder);
		this.egw.message(this.egw.lang('Connect to Profile %1',_widget.getSelectedLabel().replace(this._unseen_regexp, '')), 'success');

		//Open unloaded tree to get loaded
            _widget.getSelectedNode().expanded = true;

		this.lockTree();
		egw.json('mail_ui::ajax_changeProfile',[folder, getFolders, this.et2._inst.etemplate_exec_id], jQuery.proxy(function() {
			// Profile changed, select inbox
			var inbox = folder + '::INBOX';
                //_widget.reSelectItem(inbox);

			this.unlockTree();
		},this))
			.sendRequest(true);
            _widget.finishedLazyLoading().then (() => {
                this.changeFolder(folder+"::INBOX", _widget, '');
                _widget.reSelectItem(folder+"::INBOX")
            });

		return true;
	}

	/**
	 * changeFolder
	 * @param {string} _folder the ID of the selected Node
         * @param {Et2Tree} _widget handle to the tree widget
	 * @param {string} _previous - Previously selected node ID
	 */
	changeFolder(_folder,_widget, _previous) {

		// to reset iframes to the normal status
		this.loadIframe();

		// reset nm action selection, seems actions system accumulate selected items
		// and that leads to corruption for selected all actions
		(this.et2.getWidgetById(this.nm_index) as Et2Nextmatch).clearSelection();

		// Abort if user selected an un-selectable node
		// Use image over anything else because...?
		const img = _widget.getSelectedItem()?.im0 ?? "";
		if (img.indexOf('NoSelect') !== -1)
		{
			_widget.reSelectItem(_previous);
			return;
		}

		// check for mobile framework and close the sidebox/-bar
		if (typeof framework.toggleMenu === 'function')
		{
			framework.toggleMenu('on');
		}

		// Check if this is a top level node and
		// change profile if server has changed
		var server = _folder.split('::');
		var previousServer = _previous?.split('::');
		var profile_selected = (_folder.indexOf('::') === -1);
		if ((!previousServer || server[0] != previousServer[0]) && profile_selected)
		{
			// changeProfile triggers a refresh, no need to do any more
			return this.changeProfile(_folder,_widget, _widget.getSelectedNode().childsCount == 0);
		}

		// Apply new selected folder to list, which updates data
		var nm = _widget.getRoot().getWidgetById(this.nm_index);
		if(nm)
		{
			this.lockTree();
			nm.applyFilters({'selectedFolder': _folder});
		}

		// Remember this as the last-used folder for this profile, so mail reopens here next time
		const [profileID, folderName] = _folder.split('::');
		if (profileID && folderName) this.egw.set_preference('mail', profileID + '_LastFolder', folderName);

		// Get nice folder name for message, if selected is not a profile
		if(!profile_selected)
		{
			var displayname = _widget.getSelectedLabel();
			var myMsg = (displayname?displayname:_folder).replace(this._unseen_regexp, '')+' '+this.egw.lang('selected');
			this.egw.message(myMsg, 'success');
		}

		// Update non-grid
		this.refreshFolderStatus(_folder,'forced',false,false);
		this.refreshQuotaDisplay(server[0]);
		this.preview();
		this.callRefreshVacationNotice(server[0]);
		if (previousServer && server[0] != previousServer[0])
		{
			egw.jsonq('mail.mail_ui.ajax_refreshFilters',[server[0]]);
		}
	}

	/**
	 * checkAllSelected
	 *
	 * @param _action
	 * @param _elems
	 * @param _target
	 * @param _confirm
	 */
	checkAllSelected(_action, _elems, _target, _confirm)
	{
		if (typeof _confirm == 'undefined') _confirm = false;
		// we can NOT query global object manager for this.nm_index="nm", as we might not get the one from mail,
		// if other tabs are open, we have to query for obj_manager for "mail" and then it's child with id "nm"
		var obj_manager = egw_getObjectManager(this.appname).getObjectById(this.nm_index);
		let tree = this.et2.getWidgetById('nm[foldertree]');
		var that = this;
		var rvMain = false;
		if ((obj_manager && _elems.length>1 && obj_manager.getAllSelected() && !_action.paste) || _action.id=='readall')
		{
			try {
				let splitedID = [];
				let mailbox = '';
				// Avoid possibly doing select all action on not desired mailbox e.g. INBOX
				for (let n=0;n<_elems.length;n++)
				{
					splitedID = _elems[n].id.split("::");
					// find the mailbox from the constructed rowID, sometimes the rowID may not contain the app name
					mailbox = splitedID.length == 4?atob(splitedID[2]):atob(splitedID[3]);
					// drop the action if there's a mixedup mailbox found in the selected messages
					if (mailbox != tree.getSelectedNode().id.split("::")[1]) return;
				}
			}catch(e)
			{
				// continue
			}


			if (_confirm)
			{
				var buttons = [
					{label: this.egw.lang("Yes"), id: "all", "class": "ui-priority-primary", "default": true, image: 'check'},
					{label: this.egw.lang("Cancel"), id: "cancel", image: 'cancelDialog'},
				];
				var messageToDisplay = '';
				var actionlabel =_action.id;
				switch (_action.id)
				{
					case "readall":
						messageToDisplay = this.egw.lang("Do you really want to mark ALL messages as read in the current folder?")+" ";
						break;
					case "unlabel":
						messageToDisplay = this.egw.lang("Do you really want to remove ALL labels from ALL messages in the current folder?")+" ";
						break;
					case "label1":
						if (_action.id=="label1") actionlabel="important";
					case "label2":
						if (_action.id=="label2") actionlabel="job";
					case "label3":
						if (_action.id=="label3") actionlabel="personal";
					case "label4":
						if (_action.id=="label4") actionlabel="to do";
					case "label5":
						if (_action.id=="label5") actionlabel="later";
					case "customFlag1":
						if (_action.id=="customFlag1") actionlabel="red";
					case "customFlag2":
						if (_action.id=="customFlag2") actionlabel="orange";
					case "customFlag3":
						if (_action.id=="customFlag3") actionlabel="green";
					case "customFlag4":
						if (_action.id=="customFlag4") actionlabel="blue";
					case "customFlag5":
						if (_action.id=="customFlag5") actionlabel="purple";
					case "flagged":
					case "read":
					case "undelete":
						messageToDisplay = this.egw.lang("Do you really want to toggle flag %1 for ALL messages in the current view?",this.egw.lang(actionlabel))+" ";
						if (_action.id.substr(0,5)=='label') messageToDisplay = this.egw.lang("Do you really want to toggle label %1 for ALL messages in the current view?",this.egw.lang(actionlabel))+" ";
						break;
					default:
						if (this.isCustomLabel(_action.id))
						{
							messageToDisplay = this.egw.lang(
								"Do you really want to toggle label %1 for ALL messages in the current view?",
								_action.caption
							) + " ";
							break;
						}
						var type = null;
						if (_action.id.substr(0,4)=='move' || _action.id === "drop_move_mail")
						{
							type = 'Move';
						}
						if (_action.id.substr(0,4)=='copy' || _action.id === "drop_copy_mail")
						{
							type = 'Copy';
						}
						messageToDisplay = this.egw.lang("Do you really want to apply %1 to ALL messages in the current view?",this.egw.lang(type?type:_action.id))+" ";
				}
				return Et2Dialog.show_dialog(function (_button_id)
				{
					var rv = false;
					switch (_button_id)
					{
						case "all":
							rv = true;
							break;
						case "cancel":
							rv = 'cancel';
					}
					if (rv != "cancel")
					{
						that.lockTree();
					}
					switch (_action.id)
					{
						case "delete":
							that.callDelete(_action, _elems, rv);
							break;
						case "readall":
						case "unlabel":
						case "label1":
						case "label2":
						case "label3":
						case "label4":
						case "label5":
						case "customFlag1":
						case "customFlag2":
						case "customFlag3":
						case "customFlag4":
						case "customFlag5":
						case "flagged":
						case "read":
						case "undelete":
							that.callFlagMessages(_action, _elems, rv);
							break;
						case "drop_move_mail":
							that.callMove(_action, _elems, _target, rv);
							break;
						case "drop_copy_mail":
							that.callCopy(_action, _elems, _target, rv);
							break;
						default:
							if (that.isCustomLabel(_action.id))
							{
								that.callFlagMessages(_action, _elems, rv);
							}
							else if (_action.id.substr(0, 4) == 'move')
							{
								that.callMove(_action, _elems, _target, rv);
							}
							else if (_action.id.substr(0, 4) == 'copy')
							{
								that.callCopy(_action, _elems, _target, rv);
							}
					}
				}, messageToDisplay, this.egw.lang("Confirm"), null, buttons);
			}
			else
			{
				rvMain = true;
			}
		}
		switch (_action.id)
		{
			case "delete":
				//If in main Window (nm view) and we have no selection, do not try to
				// delete anything
				if (!this.egw.is_popup() && _elems.length === 0 && !_elems.all
					&& !this.nm?.getSelection()?.all && this.nm?.getSelection()?.ids?.length === 0)
				{
					egw.debug('warn',"Tried to delete a mail when no mail was selected. NoOp!")
					break
				}
				this.callDelete(_action, _elems,rvMain);
				break;
			case "unlabel":
			case "label1":
			case "label2":
			case "label3":
			case "label4":
			case "label5":
			case "customFlag1":
			case "customFlag2":
			case "customFlag3":
			case "customFlag4":
			case "customFlag5":
			case "flagged":
			case "read":
			case "undelete":
				this.callFlagMessages(_action, _elems,rvMain);
				break;
			case "drop_move_mail":
				this.callMove(_action, _elems,_target, rvMain);
				break;
			case "drop_copy_mail":
				this.callCopy(_action, _elems,_target, rvMain);
				break;
			default:
				if (this.isCustomLabel(_action.id))
				{
					this.callFlagMessages(_action, _elems,rvMain);
				}
				else if (_action.id.substr(0,4)=='move')
				{
					this.callMove(_action, _elems,_target, rvMain);
				}
				else if (_action.id.substr(0,4)=='copy')
				{
					this.callCopy(_action, _elems,_target, rvMain);
				}
		}
	}

	/**
	 * doActionCall
	 *
	 * @param _action
	 * @param _elems
	 */
	doActionCall(_action, _elems)
	{
	}

	/**
	 * getActiveFilters
	 *
	 * @param _action
	 * @return mixed boolean/activeFilters object
	 */
	getActiveFilters(_action)
	{
		// we can NOT query global object manager for this.nm_index="nm", as we might not get the one from mail,
		// if other tabs are open, we have to query for obj_manager for "mail" and then it's child with id "nm"
		var obj_manager = egw_getObjectManager(this.appname).getObjectById(this.nm_index);
		if (obj_manager && obj_manager.manager && obj_manager.manager.data && obj_manager.manager.data.nextmatch && obj_manager.manager.data.nextmatch.activeFilters)
		{
			var af = obj_manager.manager.data.nextmatch.activeFilters;
			// merge startdate and enddate into the active filters (if set)
			['startdate','enddate'].forEach((date) => {
				if (this.et2.getWidgetById(date)?.value)
				{
					af[date] = this.et2.getWidgetById(date).value.split('T')[0];
				}
			});
			return af;
		}
		return false;
	}

	/**
	 * Flag mail as 'read', 'unread', 'flagged' or 'unflagged'
	 *
	 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
	 * @param _elems
	 */
	flag(_action, _elems)
	{
		this.checkAllSelected(_action,_elems,null,true);
	}

	/**
	 * Trigger a targeted, in-place refresh of specific nextmatch rows from the server/JMAP's
	 * actual current state - a real network round-trip (Et2NextmatchDataProvider.refresh(), which
	 * for mail routes through MailApp's dataRegisterFetch('mail', jmap.fetchRows) wiring to a real
	 * JMAP Email/get call). Too slow to use for every optimistic flag click (that's what
	 * patchRow() is for) - use this to reconcile back to truth after a failed optimistic
	 * change, or for changes with no local guess to make (push notifications from other sessions).
	 */
	refreshRows(_ids: string[]): void
	{
		if (!_ids?.length) return;
		this.mail_nmOwner()?.nm.refresh(_ids, Et2DatagridUpdateTypes.UPDATE_IN_PLACE);
	}

	/**
	 * The mail app instance owning the nextmatch, plus that nextmatch:
	 * `this` instance in the main window, the opener's when called from a "view" popup (which has no list itself).
	 *
	 * egw  data cache is shared with the opener
	 * (api/js/jsapi/egw.js does window.egw = window.opener.top.egw),
	 * but window.app is not: a popup builds its own MailApp and MailJmap.
	 * So whatever does changes on the nm has to use the owner nm.
	 * the optimistic marker the fetch() handler reads has to go through the owning instance, not `this`.
	 *
	 * @return null if no reachable window has a message list (e.g. popup whose opener is gone)
	 */
	private mail_nmOwner(): { app: MailApp, nm: Et2Nextmatch } | null
	{
		for (const app of [this, window.opener?.app?.mail as MailApp])
		{
			const nm = (app?.nm ?? app?.et2?.getWidgetById(app?.nm_index)) as Et2Nextmatch;
			if (nm) return {app, nm};
		}
		return null;
	}

	/**
	 * Instantly reflect a keyword/class change on an already-rendered row
	 * Caller must already have written the row's
	 * *new* flags/class into dataElem.data (the "what should this look like now" computation stays
	 * with the caller, e.g. callFlagMessages's toggle logic).
	 * mark the row as an unconfirmed guess (MailJmap.markOptimistic()), and asks the nextmatch to refresh it.
	 * jmap.ts's fetchRows()/refreshRows() (registered via egw.dataRegisterFetch()) sees the guess
	 * and echoes it straight back with no JMAP round-trip, so the row re-renders without one -
	 * see MailJmap.optimisticRows for why the guess is then trusted rather than re-checked.
	 *
	 * Works from the "view" popup too: the data cache is shared with the opener, and the marker
	 *
	 * @param _uid row uid, already updated in egw's central data cache
	 */
	patchRow(_uid: string): void
	{
		// Nothing anywhere renders this row - skip, rather than mark a guess no refresh can consume
		const owner = this.mail_nmOwner();
		if (!owner) return;

		const dataElem = egw.dataGetUIDdata(_uid);
		if (!dataElem) return;

		// Mirrors MailJmap.email2row()'s status_icon logic exactly, so a locally-guessed value
		// renders identically to what a real JMAP re-fetch would later produce.
		const flags = dataElem.data.flags || {};
		dataElem.data.status_icon = flags.forwarded ? 'mail_forward' :
			flags.replied ? 'mail_reply' : !flags.read ? 'mail_unseen' : '';
		const hasFlag = !!flags.flagged ||
			['customFlag1', 'customFlag2', 'customFlag3', 'customFlag4', 'customFlag5'].some(f => !!flags[f]);
		dataElem.data.flagged_icon = hasFlag ? 'unread_flagged_small' : '';

		egw.dataStoreUID(_uid, dataElem.data, false);
		owner.app.jmap.markOptimistic(_uid);
		owner.nm.refresh([_uid], Et2DatagridUpdateTypes.UPDATE_IN_PLACE);
	}

	/**
	 * Flag mail as 'read', 'unread', 'flagged' or 'unflagged'
	 *
	 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
	 * @param _elems
	 * @param _allMessagesChecked
	 */
	callFlagMessages(_action, _elems, _allMessagesChecked)
	{
		/**
		 * vars
		 */
		let folder = '';
		let data : any = {
				msg: [this.et2.getArrayMgr("content").getEntry('mail_id')] || '',
				all: _allMessagesChecked || false,
				popup: typeof this.et2_view!='undefined' || egw(window).is_popup() || false,
				activeFilters: _action.id == 'readall'? false : this.getActiveFilters(_action)
		}

		if (typeof _elems === 'undefined' || _elems.length == 0)
		{
			if (this.mail_isMainWindow && this.mail_currentlyFocussed)
			{
				data.msg = [this.mail_currentlyFocussed];
				_elems = data;
				data.msg = this.getFormData(_elems).msg;
			}
		}
		else // action called by contextmenu
		{
			data.msg = this.getFormData(_elems).msg;
		}
		if (_action.id == 'read')
		{
			let tree;
			if (data.popup)
			{
				const et_2 = typeof this.et2_view != 'undefined' ? etemplate2 : opener.etemplate2;
				tree = et_2.getByApplication('mail')[0].widgetContainer.getWidgetById(this.nm_index+'[foldertree]');
			}
			else
			{
				tree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
			}
			folder = tree.value;
		}
		// jQuery(data).extend({},data, formData);
		if (data['all']=='cancel') return false;

		// 'unlabel' is the only action id actually reaching this branch today (no other action id
		// starts with "un" - customFlag1-5/label1-5/flagged/read are all plain toggles handled below)
		if (_action.id == 'unlabel')
		{
			// Optimistically clear all labels locally so the row updates instantly - flagMessages()
			// falls back to refreshRows() (a real re-fetch) only if the JMAP call actually fails.
			const labels = this.getLabelIds();
			for (const uid of data.msg)
			{
				const dataElem = egw.dataGetUIDdata(uid);
				if (!dataElem) continue;
				dataElem.data.flags ||= {};
				let classes = (dataElem.data['class'] || '').split(' ');
				labels.forEach(label =>
				{
					delete dataElem.data.flags[label];
					classes = classes.filter(className => className != label && className != 'un' + label);
				});
				dataElem.data['class'] = classes.join(' ');
				this.patchRow(uid);
			}
			this.flagMessages(_action.id, data);
		}
		else if (_action.id=='readall')
		{
			this.flagMessages('read',data);
		}
		else
		{
			// Toggle flags/class locally first, for instant feedback - flagMessages() falls back
			// to refreshRows() (a real re-fetch) only if the JMAP call it fires below actually
			// fails, reconciling back to the server's real state for whichever rows the guess got wrong.
			const customFlags = ['customFlag1', 'customFlag2', 'customFlag3', 'customFlag4', 'customFlag5'];
			const rowClass = _action.id;
			const msg_set = {msg:[]};
			const msg_unset = {msg:[]};
			for (let i = 0; i < data.msg.length; i++)
			{
				const dataElem = egw.dataGetUIDdata(data.msg[i]);
				if (!dataElem) continue;
				dataElem.data.flags ||= {};
				const flags = dataElem.data.flags;
				let classes = (dataElem.data['class'] || '').split(' ');

				if (_action.id === 'read')
				{
					// Real convention (MailJmap.email2row()): being read has no class of its own -
					// unread is signalled by the presence of 'unseen', not by a generic
					// rowClass/'un'+rowClass pair the way flagged/label toggles below work.
					classes = classes.filter((className) => className != 'unseen');
					if (flags.read)
					{
						msg_unset['msg'].push(data.msg[i]);
						delete flags.read;
						classes.push('unseen');
					} else
					{
						msg_set['msg'].push(data.msg[i]);
						flags.read = 'read';
					}
					dataElem.data['class'] = classes.join(' ');
					this.patchRow(data.msg[i]);
					this.updateFilterData(data.msg[i], data.activeFilters, flags);
					continue;
				}

				// since we toggle we need to unset the ones already set, and set the ones not set
				// flags is data, UI is done by class, so update both
				// Flags are there or not, class names are flag or 'un'+flag
				if (classes.indexOf(rowClass) >= 0)
				{
					classes.splice(classes.indexOf(rowClass), 1);
				}
				if (classes.indexOf('un' + rowClass) >= 0)
				{
					classes.splice(classes.indexOf('un' + rowClass), 1);
				}

				if (flags[_action.id])
				{
					msg_unset['msg'].push(data.msg[i]);
					if (customFlags.includes(_action.id))
					{
						delete flags['flagged'];
						classes = classes.filter((className) => className != 'flagged' && className != 'unflagged');
					} else if (!this.isLabel(_action.id))
					{
						classes.push('un' + rowClass);
						if (_action.id === 'flagged')
						{
							// Plain unflag clears any colored custom flag too - a customFlag implies
							// $flagged (jmap.ts), so leaving one set here would make the row look
							// flagged again on the next render.
							customFlags.forEach(customFlag =>
							{
								delete flags[customFlag];
								classes = classes.filter((className) => className != customFlag && className != 'un' + customFlag);
							});
						}
					}
					delete flags[_action.id];
				} else
				{
					if (customFlags.includes(_action.id))
					{
						customFlags.forEach(customFlag =>
						{
							if (customFlag != _action.id)
							{
								delete flags[customFlag];
								classes = classes.filter((className) => className != customFlag && className != 'un' + customFlag);
							}
						});
						flags['flagged'] = 'flagged';
						classes = classes.filter((className) => className != 'flagged' && className != 'unflagged');
						classes.push('flagged');
					}
					msg_set['msg'].push(data.msg[i]);
					flags[_action.id] = _action.id;
					classes.push(rowClass);
				}

				dataElem.data['class'] = classes.join(' ');
				this.patchRow(data.msg[i]);

				// Hide this row now if it no longer matches the active status filter (e.g. viewing
				// "Unread" and marking read) - independent of the class/icon patch above, and not
				// knowable from a server response since the server doesn't know our active filter.
				this.updateFilterData(data.msg[i], data.activeFilters, flags);
			}

			// Notify server of changes
			if (msg_unset['msg'].length && !data['all'])
			{
				this.flagMessages(
					this.isLabel(_action.id) ?
						{customLabel: _action.id, set: false} : 'un'+_action.id,
					msg_unset
				);
			}
			if (msg_set['msg'].length && !data['all'])
			{
				this.flagMessages(
					this.isLabel(_action.id) ?
						{customLabel: _action.id, set: true} : _action.id,
					msg_set
				);
			}
			//server must do the toggle, as we apply to ALL, not only the visible
			if (data['all']) this.flagMessages(_action.id,data);
			// No further update needed, only in case of read, the counters should be refreshed
			if (_action.id=='read') this.refreshFolderStatus(folder,'thisfolderonly',false,true);
			return;
		}
	}

	/**
	 * Hide a row from an active status filter if its just-updated flags no longer match it.
	 *
	 * Checks the row's actual resulting flags against the filter, rather than inferring a match
	 * from which action id was clicked - e.g. switching between customFlag colors while viewing the
	 * "flagged" filter is a set on the new color and (usually) an unset on the old one, but the row
	 * stays flagged throughout and must never be deleted for either half of that switch; a plain
	 * "did this action's name match the filter's name" check can't tell the two apart from a single
	 * action id, since both the customFlag being set AND the one being unset map to the same
	 * 'flagged' filter name.
	 *
	 * @param {type} _uid mail uid
	 * @param {type} _filters activefilters
	 * @param {type} _flags the row's flags, already updated to reflect this action
	 */
	updateFilterData(_uid, _filters, _flags)
	{
		if (!_filters?.filter) return;
		const uid = _uid.replace('mail::','');
		let matches;
		switch (_filters.filter)
		{
			case 'flagged':
				matches = !!_flags.flagged;
				break;
			case 'seen':
				matches = !!_flags.read;
				break;
			case 'unseen':
				matches = !_flags.read;
				break;
			case 'keyword1':
				matches = !!_flags.label1;
				break;
			case 'keyword2':
				matches = !!_flags.label2;
				break;
			case 'keyword3':
				matches = !!_flags.label3;
				break;
			case 'keyword4':
				matches = !!_flags.label4;
				break;
			case 'keyword5':
				matches = !!_flags.label5;
				break;
			default:
				// custom labels use their own id as both the flag key and the filter value
				if (this.isCustomLabel(_filters.filter))
				{
					matches = !!_flags[_filters.filter];
				} else
				{
					// a filter this action can never affect (e.g. 'deleted') - nothing to check
					return;
				}
				break;
		}
		if (!matches)
		{
			egw.refresh('','mail',uid, 'delete');
		}
	}

	/**
	 * Turn a "select all matching filter" _elems/_msg object's activeFilters into the
	 * JmapGetRowsQuery shape MailJmap.buildFilter() (and everything built on it - toggleForAll(),
	 * clearLabelsForAll(), moveAllMatching(), deleteAllMatching()) expects.
	 *
	 * @param {object} _elems _msg/_elems object with an .activeFilters property (only present/used
	 *  when .all is truthy)
	 */
	private buildJmapQuery(_elems) : any
	{
		const filters = _elems.activeFilters || {};
		let selectedFolder = filters.selectedFolder ||
			this.et2?.getWidgetById(this.nm_index + '[foldertree]')?.getValue() ||
			this.egw.preference('ActiveProfileID', 'mail');
		if (selectedFolder && !selectedFolder.includes('::')) selectedFolder += '::INBOX';
		const query : any = {
			selectedFolder,
			cat_id: filters.cat_id,
			search: filters.search,
			filter: filters.filter,
			startdate: filters.startdate,
			enddate: filters.enddate,
		};
		if (filters.sort && typeof filters.sort === 'object')
		{
			query.order = filters.sort.id;
			query.sort = filters.sort.asc ? 'ASC' : 'DESC';
		}
		return query;
	}

	/**
	 * Flag mail as 'read', 'unread', 'flagged' or 'unflagged'
	 *
	 * @param {object} _flag
	 * @param {object} _elems
	 * @param {boolean} _isPopup
	 */
	flagMessages(_flag, _elems,_isPopup?)
	{
		const labelOperation = typeof _flag === 'object' && typeof _flag?.customLabel === 'string' ? _flag : null;
		const actionId = labelOperation?.customLabel || String(_flag);
		const customFlag = actionId.replace(/^un/, '').match(/^customFlag[1-5]$/) ? actionId.replace(/^un/, '') : null;
		// standard system flags (read/unread, flagged/unflagged) - JMAP-native only for an
		// explicit selection (fixes the "N selected rows, one emailId2uid() search each" gap);
		// "select all matching filter" keeps the classic path for these two, whose semantics are
		// filter-aware (e.g. "mark all as read" while viewing the Unseen filter), not a plain
		// per-row toggle - not replicated here
		const systemFlagKeyword = !_elems.all ? MailJmap.systemFlagKeyword(actionId.replace(/^un/, '')) : null;
		const jmapKeywordAction = !!labelOperation || this.isLabel(actionId) || !!customFlag ||
			actionId === 'unlabel' || !!systemFlagKeyword;

		if (jmapKeywordAction)
		{
			let operation : Promise<void>;
			if (_elems.all)
			{
				const query = this.buildJmapQuery(_elems);
				operation = actionId === 'unlabel' ?
					this.jmap.clearLabelsForAll(query) : this.jmap.toggleForAll(query, actionId);
			}
			else
			{
				try
				{
					const references = (_elems.msg || []).map(id => this.jmap.messageReference(id));
					if (actionId === 'unlabel')
					{
						operation = this.jmap.clearLabels(references);
					}
					else if (customFlag)
					{
						operation = this.jmap.setCustomFlag(references, customFlag, !actionId.startsWith('un'));
					}
					else if (systemFlagKeyword)
					{
						operation = this.jmap.setSystemFlag(references, systemFlagKeyword, !actionId.startsWith('un'));
					}
					else
					{
						operation = this.jmap.setLabel(references, actionId, labelOperation?.set ?? true);
					}
				}
				catch (error)
				{
					operation = Promise.reject(error);
				}
			}
			operation.then(() =>
			{
				// Nothing to do here for an explicit selection - the caller already patched the
				// row(s) optimistically (patchRow()) before firing this JMAP call, and the
				// operation just confirmed that guess was correct. "select all matching filter" has
				// no such local guess (arbitrarily many rows, not all loaded client-side), so it
				// always needs the real refresh.
				if (_elems.all) this.refreshMessageGrid(!!_elems.popup);
			}).catch((error) =>
			{
				this.egw.message(error?.message || this.egw.lang('Failed to update messages'), 'error');
				// The optimistic patch (or "all" case) may now be showing the wrong thing - reconcile
				// with the server's real current state.
				if (_elems.all) this.refreshMessageGrid(!!_elems.popup);
				else this.refreshRows(_elems.msg);
			});
			return;
		}

		//false means do not send back a request response
		//if we selected only some mails the handling is done clientside already
		const needsResponse = _elems.all || this.egw.is_popup();
		egw.jsonq('mail.mail_ui.ajax_flagMessages', [_flag, _elems, needsResponse]);
		//	.sendRequest(true);
	}

	/**
	 * display header lines, or source of mail, depending on the url given
	 *
	 * @param _url
	 */
	displayHeaderLines(_url) {
		// only used by right clickaction
		egw.openPopup(_url, '870', '600', null, 'mail');
	}

	/**
	 * View header of a message
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	header(_action, _elems)
	{
		if (typeof _elems == 'undefined'|| _elems.length==0)
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				_elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					_elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		//alert('header('+_elems[0].id+')');
		const rowId = _elems[0].id;
		const classicHeaderPopup = () =>
		{
			let url = window.egw_webserverUrl+'/index.php?';
			url += 'menuaction=mail.mail_ui.displayHeader';	// todo compose for Draft folder
			url += '&id='+rowId;
			this.displayHeaderLines(url);
		};
		this.jmap.fetchRawHeader(rowId).then(async(text : string) =>
		{
			// egw.openPopup() (kdots framework) returns a Promise resolving to the actual
			// popup Window, not the Window itself - must be awaited before touching .document
			const popup = await egw.openPopup('about:blank', 870, 600, null, 'mail', true) as any as Window;
			if (!popup || !popup.document)
			{
				classicHeaderPopup();
				return;
			}
			const escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
			popup.document.open();
			popup.document.write('<pre>'+escaped+'</pre>');
			popup.document.close();
		}).catch((e) => this.egw.message(e.message, 'error'));
	}

	/**
	 * View message source
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mailSource(_action, _elems)
	{
		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				_elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined'|| _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					_elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		//alert('mailSource('+_elems[0].id+')');
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=mail.mail_ui.saveMessage';	// todo compose for Draft folder
		url += '&id='+_elems[0].id;
		url += '&location=display';
		this.displayHeaderLines(url);
	}

	/**
	 * Save a message
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	save(_action, _elems)
	{
		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				_elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					_elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}

		for (var i in _elems)
		{
			//alert('save('+_elems[0].id+')');
			var url = window.egw_webserverUrl+'/index.php?';
			url += 'menuaction=mail.mail_ui.saveMessage';	// todo compose for Draft folder
			url += '&id='+_elems[i].id;
			var a = document.createElement('a');
			a = jQuery(a)
				.prop('href', url)
				.prop('download',"")
				.appendTo(this.et2.getDOMNode());
			var evt = document.createEvent('MouseEvent');
			evt.initMouseEvent('click', true, true, window, 1, 0, 0, 0, 0, false, false, false, false, 0, null);
			a[0].dispatchEvent(evt);
			a.remove();
		}
	}

	/**
	 * User clicked an address (FROM, TO, etc)
	 *
	 * @param {object} tag_info with values for attributes id, label, title, ...
	 * @param {widget object} widget
	 *
	 * @todo seems this function is not implemented, need to be checked if it is neccessary at all
	 */
	addressClick(tag_info, widget)
	{

	}

	/**
	 * displayAttachment
	 *
	 * @param {object} tag_info
	 * @param {widget object} widget
	 * @param {object} calledForCompose
	 */
	displayAttachment(tag_info, widget, calledForCompose)
	{
		var mailid;
		var attgrid;
		if (typeof calledForCompose == 'undefined' || typeof calledForCompose == 'object') calledForCompose=false;
		if (calledForCompose===false)
		{
			if (this.mail_isMainWindow)
			{
				mailid = this.mail_currentlyFocussed;//this.et2.getArrayMgr("content").getEntry('mail_id');
				var p = widget.getParent();
				var cont = p.getArrayMgr("content").data;
				attgrid = cont[widget.id.replace(/\[filename\]/,'')];
			}
			else
			{
				mailid = this.et2.getArrayMgr("content").getEntry('mail_id');
				attgrid = this.et2.getArrayMgr("content").getEntry('attachmentsBlock')[widget.id.replace(/\[filename\]/,'')];
			}
		}
		if (calledForCompose===true)
		{
			// CALLED FOR COMPOSE; processedmail_id could hold several IDs seperated by comma
			attgrid = this.et2.getArrayMgr("content").getEntry('attachments')[widget.id.replace(/\[name\]/,'')];
			var mailids = this.et2.getArrayMgr("content").getEntry('processedmail_id');
			var mailida = mailids.split(',');
			// either several attachments of one email, or multiple emlfiles
			mailid = mailida.length==1 ? mailida[0] : mailida[widget.id.replace(/\[name\]/,'')];
			if (typeof attgrid.uid != 'undefined' && attgrid.uid && mailid.indexOf(attgrid.uid)==-1)
			{
				for (var i=0; i<mailida.length; i++)
				{
					if (mailida[i].indexOf('::'+attgrid.uid)>-1) mailid = mailida[i];
				}
			}
		}
		var url = window.egw_webserverUrl+'/index.php?';
		var width;
		var height;
		var windowName ='mail';
		switch(attgrid.type.toUpperCase())
		{
			case 'MESSAGE/RFC822':
				url += 'menuaction=mail.mail_ui.displayMessage';	// todo compose for Draft folder
				url += '&mode=display';//message/rfc822 attachments should be opened in display mode
				url += '&id='+mailid;
				url += '&part=' + (attgrid.partID ?? "");
				url += '&is_winmail='+attgrid.winmailFlag;
				windowName = windowName + 'displayMessage_' + mailid + '_' + (attgrid.partID ?? "");
				width = 870;
				height = egw_getWindowOuterHeight();
				break;
			case 'IMAGE/JPEG':
			case 'IMAGE/PNG':
			case 'IMAGE/GIF':
			case 'IMAGE/BMP':
			case 'APPLICATION/PDF':
			case 'TEXT/PLAIN':
			case 'TEXT/HTML':
			case 'TEXT/DIRECTORY':
/*
				$sfxMimeType = $value['mimeType'];
				$buff = explode('.',$value['name']);
				$suffix = '';
				if (is_array($buff)) $suffix = array_pop($buff); // take the last extension to check with ext2mime
				if (!empty($suffix)) $sfxMimeType = mime_magic::ext2mime($suffix);
				if (strtoupper($sfxMimeType) == 'TEXT/VCARD' || strtoupper($sfxMimeType) == 'TEXT/X-VCARD')
				{
					$attachments[$key]['mimeType'] = $sfxMimeType;
					$value['mimeType'] = strtoupper($sfxMimeType);
				}
*/
			case 'TEXT/X-VCARD':
			case 'TEXT/VCARD':
			case 'TEXT/CALENDAR':
			case 'TEXT/X-VCALENDAR':
				url += 'menuaction=mail.mail_ui.getAttachment';	// todo compose for Draft folder
				url += '&id='+mailid;
				url += '&part='+attgrid.partID;
				url += '&is_winmail='+attgrid.winmailFlag;
				windowName = windowName+'displayAttachment_'+mailid+'_'+attgrid.partID;
				var reg = '800x600';
				var reg2;
				// handle calendar/vcard
				if (attgrid.type.toUpperCase()=='TEXT/CALENDAR')
				{
					windowName = 'maildisplayEvent_'+mailid+'_'+attgrid.partID;
					reg2 = egw.link_get_registry('calendar');
					if (typeof reg2['view'] != 'undefined' && typeof reg2['view_popup'] != 'undefined' )
					{
						reg = reg2['view_popup'];
					}
				}
				if (attgrid.type.toUpperCase()=='TEXT/X-VCARD' || attgrid.type.toUpperCase()=='TEXT/VCARD')
				{
					windowName = 'maildisplayContact_'+mailid+'_'+attgrid.partID;
					reg2 = egw.link_get_registry('addressbook');
					if (typeof reg2['add'] != 'undefined' && typeof reg2['add_popup'] != 'undefined' )
					{
						reg = reg2['add_popup'];
					}
				}
				var w_h =reg.split('x');
				width = w_h[0];
				height = w_h[1];
				break;
			default:
				url += 'menuaction=mail.mail_ui.getAttachment';	// todo compose for Draft folder
				url += '&id='+mailid;
				url += '&part='+attgrid.partID;
				url += '&is_winmail='+attgrid.winmailFlag;
				windowName = windowName+'displayAttachment_'+mailid+'_'+attgrid.partID;
				width = 870;
				height = 600;
				break;
		}
		egw_openWindowCentered(url,windowName,width,height);
	}

	/**
	 * Callback function to handle vfsSave response messages
	 *
	 * @param {type} _data
	 */
	vfsSaveCallback(_data)
	{
		egw.message(_data.msg, _data.success ? "success" : "error");
	}

	/**
	 * A handler for saving to VFS/downloading attachments
	 *
	 * @param {type} widget
	 * @param {type} action
	 * @param {type} row_id
	 */
	saveAttachmentHandler(widget, action, row_id)
	{
		let mail_id, attachments,attachment;

		if (this.mail_isMainWindow)
		{
			mail_id = this.mail_currentlyFocussed || app.mail.mail_currentlyFocussed;
			const p = widget.getParent();
			attachments = p.getArrayMgr("content").data;
		}
		else
		{
			// this.et2 does not reliably resolve to the "view" popup's own template (its
			// getArrayMgr("content").getEntry(...) calls silently return undefined there,
			// crashing every action below on a single-attachment message) - walk up from the
			// clicked widget itself instead, same as the main-window branch above, and read
			// mail_id off the attachment row itself (createAttachmentBlock() in
			// class.mail_ui.inc.php always sets it there) rather than a separate lookup
			const p = widget.getParent();
			attachments = p.getArrayMgr("content").data;
			mail_id = (attachments && (attachments[row_id] ?? attachments[0]))?.mail_id ??
				this.et2.getArrayMgr("content").getEntry('mail_id');
		}

		switch (action)
		{
			case 'saveOneToVfs':
			case 'saveAllToVfs':
				const ids = [];
				attachments = action === 'saveOneToVfs' ? [attachments[row_id]] : attachments;
				for (const attachment of attachments)
				{
					if (attachment != null)
					{
						ids.push(mail_id+'::'+attachment.partID+'::'+attachment.winmailFlag+'::'+attachment.filename);
					}
				}
				let vfs_select = loadWebComponent('et2-vfs-select', {
					mode: action === 'saveOneToVfs' ? 'saveas' : 'select-dir',
					method: 'mail.mail_ui.ajax_vfsSave',
					buttonLabel: this.egw.lang(action === 'saveOneToVfs' ? 'Save' : 'Save all'),
					title: this.egw.lang(action === 'saveOneToVfs' ? 'Save attachment' : 'Save attachments'),
					filename: action === 'saveOneToVfs' ? attachments[0]['filename'] : null
				}, this.et2 ?? app.mail.et2);
				// Serious violation of type - methodId is a string
				// Set it to an array here bypassing normal checking
				vfs_select.methodId = ids.length > 1 ? {ids: ids, action: 'attachment'} : {ids: ids[0], action: 'attachment'},
					vfs_select.updateComplete.then(() => vfs_select.click());
				// Single use only, remove when done
				vfs_select.addEventListener("change", () => vfs_select.remove());
				break;
			case 'collabora':
				attachment = attachments[row_id];
				let id = mail_id + '::' + attachment.partID + '::' + attachment.winmailFlag + '::' + attachment.filename;

				// This can take a few seconds, show loader
				this.egw.loading_prompt('mail_open_file', true, attachment.filename);

				// Temp save to VFS
				this.egw.request('mail.mail_ui.ajax_vfsOpen', [id, attachment.filename]).then((temp_path) =>
				{
					if (temp_path)
					{
						// Open in Collabora
						window.open(this.egw.link('/index.php', {
							'menuaction': 'collabora.EGroupware\\collabora\\Ui.editor',
							'path': temp_path,
							'cd': 'no'	// needed to not reload framework in sharing
						}));
					}
				}).finally(() =>
				{
					// Hide load prompt
					this.egw.loading_prompt('mail_open_file', false);
				});
				break;

			case 'downloadOneAsFile':
			case 'downloadAllToZip':
				attachment = attachments[row_id];
				const classicDownload = () =>
				{
					let url = window.egw_webserverUrl+'/index.php?';
					url += new URLSearchParams({
						menuaction: action === 'downloadOneAsFile' ?
							'mail.mail_ui.getAttachment' : 'mail.mail_ui.download_zip',
						mode: 'save',
						id: attachment.mail_id,
						part: attachment.partID,
						is_winmail: attachment.winmailFlag,
						smime_type: attachment.smime_type ?? ''
					}).toString();
					window.etemplate2.prototype.download(url);
				};
				// Fast client-side JMAP path for a single attachment with a known blobId (set by
				// mail_ui::jmapAttachmentsToLegacy(), both backends). downloadAllToZip stays on the
				// classic path (server-side zip assembly, not a per-file fetch); an unparseable
				// mail_id or missing blobId falls back to it too (not a JMAP failure, just not
				// applicable) - but once the JMAP download itself is attempted, any failure shows
				// the error directly, there's no classic fallback (see mail_folderTreeAutoload()'s
				// docblock for why).
				if (action === 'downloadOneAsFile' && attachment.blobId)
				{
					let profileID : string;
					try
					{
						profileID = this.jmap.messageReference(attachment.mail_id).profileID;
					}
					catch (e)
					{
						classicDownload();
						break;
					}
					this.jmap.downloadAttachment(profileID, attachment.blobId, attachment.filename, attachment.type)
						.catch((e) => this.egw.message(e.message, 'error'));
					break;
				}
				classicDownload();
				break;
			case 'forward':
				// Give some UI feedback, this might take a second
				document.body.style.cursor = 'wait';

				// Move the attachment to VFS
				const file_id = mail_id+'::'+attachments[row_id].partID+'::'+attachments[row_id].winmailFlag+'::'+attachments[row_id].filename;
				this.egw.request("mail.mail_ui.ajax_vfsOpen", [file_id,attachments[row_id].filename])
					.then((vfs_path) => {
						if(!vfs_path)
						{
							// Server call will also display an error on failure
							return;
						}

						// File is in VFS, put it in a compose window
						const params = {};
						let content = {data:{files:{file:[]}}};
						params['preset[file][]'] = 'vfs://default'+vfs_path;
						content.data.files.file.push('vfs://default'+vfs_path);
						content.data.files["filemode"] = params['preset[filemode]'];
						// always open compose in html mode, as attachment links look a lot nicer in html
						params["mimeType"] = 'html';
						egw.openWithinWindow("mail", "setCompose", content, params, /mail.mail_compose.compose/, true);
					})
					.finally(() => {
						// No matter what, clear the waiting style
						document.body.style.cursor = '';
					});
				break;
			case 'invoices':
				egw.open_link(attachments[row_id].invoice_data, '_blank', '', action, true, attachments[row_id].type);
				break;
		}
	}

	/**
	 * Save a message to filemanager
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	save2Fm(_action, _elems)
	{
		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				_elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					_elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		var ids = [], names = [];
		for (const i in _elems)
		{
			const _id = _elems[i].id;
			const dataElem = egw.dataGetUIDdata(_id);
			let subject = dataElem? dataElem.data.subject: _elems[i].subject;
			if (this.egw.is_popup() && this.et2._inst.name == 'mail.display')
			{
				subject = this.et2.getArrayMgr('content').getEntry('subject');
			}
			// Replace these now, they really cause problems later
			const filename = subject ? subject.replace(/[\f\n\t\v\x0b\:*#?<>%"\/\\\?]/g,"_") : 'unknown';
			ids.push(_id);
			names.push(filename+'.eml');
		}
		let vfs_select = loadWebComponent('et2-vfs-select', {
			mode: _elems.length > 1 ? 'select-dir' : 'saveas',
			mime: 'message/rfc822',
			method: 'mail.mail_ui.ajax_vfsSave',
			buttonLabel: _elems.length > 1 ? egw.lang('Save all') : egw.lang('save'),
			title: this.egw.lang("Save email"),
			filename: _elems.length > 1 ? names : names[0],
		}, this.et2);
		// Serious violation of type - methodId is a string
		// Set it to an array here bypassing normal checking
		vfs_select.methodId = _elems.length > 1 ? {ids: ids, action: 'message'} : {ids: ids[0], action: 'message'};
		vfs_select.updateComplete.then(() => vfs_select.click());
		// Single use only, remove when done
		vfs_select.addEventListener("change", () => vfs_select.remove());
	}

	/**
	 * Integrate mail message into another app's entry
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	integrate(_action, _elems)
	{
		const app = _action.id;
		let w_h = ['750','580']; // define a default wxh if there's no popup size registered

		if (typeof _action.data != 'undefined' )
		{
			if (typeof _action.data.popup != 'undefined' && _action.data.popup) w_h = _action.data.popup.split('x');
			if (typeof _action.data.mail_import != 'undefined') var mail_import_hook = _action.data.mail_import;
		}

		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				_elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					_elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}

		var url = window.egw_webserverUrl+ '/index.php?menuaction=mail.mail_integration.integrate&rowid=' + _elems[0].id + '&app='+app;

		if (mail_import_hook && typeof mail_import_hook.app_entry_method != 'undefined')
		{
			var data = egw.dataGetUIDdata(_elems[0].id);
			var title = egw.lang('Select') + ' ' + egw.lang(app) + ' ' + (egw.link_get_registry(app, 'entry') ? egw.link_get_registry(app, 'entry') : egw.lang('entry'));
			var subject = (data && typeof data.data != 'undefined')? data.data.subject : '';
			this.integrateCheckAppEntry(title, app, subject, url,  mail_import_hook.app_entry_method, function (args){
				egw_openWindowCentered(args.url+ (args.entryid ?'&entry_id=' + args.entryid: ''),'import_mail_'+_elems[0].id,w_h[0],w_h[1]);
			});
		}
		else
		{
			egw_openWindowCentered(url,'import_mail_'+_elems[0].id,w_h[0],w_h[1]);
		}

	}

   /**
	* Checks the application entry existance and offers user
	* to select desire app id to append mail content into it,
	* or add the mail content as a new app entry
	*
	* @param {string} _title select app entry title
	* @param {string} _appName app to be integrated
	* @param {string} _subject
	* @param {string} _url
	* @param {string} _appCheckCallback registered mail_import hook method
	* @param {function} _execCallback function to get called on dialog actions
	*/
	integrateCheckAppEntry(_title, _appName, _subject ,_url, _appCheckCallback, _execCallback)
	{
	   var subject = _subject || '';
	   var execCallback = _execCallback;
	   egw.json(_appCheckCallback, subject,function(_entryId){

		   // if there's no entry saved already
		   // open dialog in order to select one
		   if (!_entryId)
		   {
			   var buttons = [
				   {label: app.mail.egw.lang('Append'), id: 'append', image: 'check', default: true},
				   {label: app.mail.egw.lang('Add as new'), id: 'new', image: 'check'},
				   {label: app.mail.egw.lang('Cancel'), id: 'cancel', image: 'check'}
			   ];
			   const dialog = new Et2Dialog(this.egw);
			   dialog.transformAttributes({
				   callback(_buttons, _value)
				   {
					   if (_buttons == 'cancel')
					   {
						   return;
					   }
					   if (_buttons == 'append' && _value)
					   {
						   _entryId = _value.id;
					   }
					   execCallback.call(this, {entryid: _entryId, url: _url});
				   },
				   title: egw.lang(_title),
				   buttons: buttons || Et2Dialog.BUTTONS_OK_CANCEL,
				   value: {
					   content: {
						   appName: _appName // appName to search on its list later
					   }
				   },
				   template: egw.webserverUrl + '/mail/templates/default/integration_to_entry_dialog.xet'
			   });
			   document.body.appendChild(dialog);
		   }
		   else // there is an entry saved related to this mail's subject
		   {
			   execCallback.call(this,{entryid:_entryId,url:_url});
		   }
	   },this,true,this).sendRequest();
	}

	/**
	 * getFormData
	 *
	 * @param {object} _actionObjects the senders
	 *
	 * @return structured array of message ids: array(msg=>message-ids)
	 */
	getFormData(_actionObjects) {
		var messages = {};
		// if
		if (typeof _actionObjects['msg'] != 'undefined' && _actionObjects['msg'].length>0) return _actionObjects;
		if (_actionObjects.length>0)
		{
			messages['msg'] = [];
		}

		for (var i = 0; i < _actionObjects.length; i++)
		{
			if (_actionObjects[i].id.length>0)
			{
				messages['msg'][i] = _actionObjects[i].id;
			}
		}

		return messages;
	}

	/**
	 * move2Folder - implementation of the move action from action menu
	 *
	 * @param _action _action.id holds folder target information
	 * @param _elems - the representation of the elements to be affected
	 */
	move2Folder(_action, _elems) {
		this.move(_action, _elems, null);
	}

	/**
	 * move - implementation of the move action from drag n drop
	 *
	 * @param _action
	 * @param _senders - the representation of the elements dragged
	 * @param _target - the representation of the target
	 */
	move(_action,_senders,_target) {
		this.checkAllSelected(_action,_senders,_target,true);
	}

	/**
	 * Try the fast client-side JMAP move path - MailJmap.moveMessages() for an explicit selection,
	 * or moveAllMatching() for "select all matching the current filter" - within one account, and
	 * not the "move to archive" shortcut (that needs the server to resolve the actual archive
	 * folder, see ajax_copyMessages()'s $_move2ArchiveMarker). Cross-account moves fall through to
	 * the classic path too (moveMessages()/moveAllMatching() both throw for those). Returns null if
	 * not applicable (caller falls back to the unchanged classic call directly); otherwise a
	 * Promise that either succeeds via JMAP or, on any failure, falls back to that same classic call.
	 */
	private mail_tryJmapMove(target : string, messages : any, isArchiveShortcut : boolean,
		classicMove : () => Promise<any>) : Promise<any> | null
	{
		if (isArchiveShortcut)
		{
			return null;
		}
		const sepIndex = target.indexOf('::');
		const targetProfileID = sepIndex > 0 ? target.substring(0, sepIndex) : '';
		const targetFolderPath = sepIndex > 0 ? target.substring(sepIndex + 2) : '';
		if (!targetProfileID || !targetFolderPath)
		{
			return null;
		}
		if (messages['all'])
		{
			return this.jmap.moveAllMatching(this.buildJmapQuery(messages), targetProfileID, targetFolderPath)
				.catch((e) => this.mail_handleJmapError(e, classicMove));
		}
		if (!Array.isArray(messages.msg) || !messages.msg.length)
		{
			return null;
		}
		let references : JmapMessageReference[];
		try
		{
			references = messages.msg.map((id : string) => this.jmap.messageReference(id));
		}
		catch (e)
		{
			return null;
		}
		return this.jmap.moveMessages(references, targetProfileID, targetFolderPath)
			.catch((e) => this.mail_handleJmapError(e, classicMove));
	}

	/**
	 * Try the fast client-side JMAP path for the MDN Yes/No dialog's flag write
	 * (MailJmap.setMdnFlag()) - always a single previewed message, no "select all" case. Falls back
	 * to the classic ajax_flagMessages() call on any failure (reference-building or the JMAP call
	 * itself). ajax_sendMDN() (the actual outbound receipt) is unrelated and unchanged.
	 */
	private trySetMdnFlag(messages : any, sent : boolean) : void
	{
		const classicFallback = () =>
			egw.jsonq('mail.mail_ui.ajax_flagMessages', [sent ? 'mdnsent' : 'mdnnotsent', messages, true]);
		let references : JmapMessageReference[];
		try
		{
			references = (messages.msg || []).map((id : string) => this.jmap.messageReference(id));
		}
		catch (e)
		{
			classicFallback();
			return;
		}
		this.jmap.setMdnFlag(references, sent)
			.catch((e) => this.mail_handleJmapError(e, classicFallback))
			.catch((e) => this.egw.message(e?.message || this.egw.lang('Failed to update messages'), 'error'));
	}

	/**
	 * move - implementation of the move action from drag n drop
	 *
	 * @param _action
	 * @param _senders - the representation of the elements dragged
	 * @param _target - the representation of the target
	 * @param _allMessagesChecked
	 */
	callMove(_action,_senders,_target,_allMessagesChecked) {
		var target = _action.id == 'drop_move_mail' ? _target.id : _action.id.substr(5);
		var messages = this.getFormData(_senders);
		if (typeof _allMessagesChecked=='undefined') _allMessagesChecked=false;

		// Directly delete any cache for target
		if(window.localStorage)
		{
			for(var i = 0; i < window.localStorage.length; i++)
			{
				var key = window.localStorage.key(i);

				// Find directly by what the key would look like
				if(key.indexOf('cached_fetch_mail::{"selectedFolder":"'+target+'"') == 0)
				{
					window.localStorage.removeItem(key);
				}
			}
		}
		// TODO: Write move/copy function which cares about doing the same stuff
		// as the "onNodeSelect" function!
		messages['all'] = _allMessagesChecked;
		if (messages['all']=='cancel') return false;
		if (messages['all']) messages['activeFilters'] = this.getActiveFilters(_action);

		// Make sure a default target folder is set in case of drop target is parent 0 (mail account name)
		if (!target.match(/::/g)) target += '::INBOX';

		var self = this;
		var nm = this.et2.getWidgetById(this.nm_index);
		// The legacy callback is cancelable through its selection event, rather
		// than changing the component's callback property.
		const suppressPreview = (event : Event) => event.preventDefault();
		nm.addEventListener("et2-selection-changed", suppressPreview, {capture: true, once: true});
		_senders[0].parent.setAllSelected(false);
		queueMicrotask(() => nm.removeEventListener("et2-selection-changed", suppressPreview, {capture: true}));
		this.preview([], nm);

		// Remove from nm immediately so the user gets immediate feedback, we send an error message later in case something went wrong
		this.refresh(nm, messages.msg, Et2DatagridUpdateTypes.DELETE);

		// thev 4th param indicates if it is a normal move messages action. if not the action is a move2.... (archiveFolder) action
		const isArchiveShortcut = _action.id.substr(0,4)=='move'&&_action.id.substr(4,1)=='2';
		const classicMove = () => egw.json('mail.mail_ui.ajax_copyMessages',[target, messages, 'move', (isArchiveShortcut?'2':'_') ], function(){
			self.unlockTree();

			// Server response may contain refresh, but it's always delete
			// Refresh list if current view is the target (happens when pasting)
			var tree = self.et2.getWidgetById('nm[foldertree]');
			if(nm && tree && target == tree.getValue())
			{
				// Can't trust the sorting, needs to be full refresh
				nm.refresh();
			}
		}).sendRequest(true);

		// Fast client-side JMAP path for the common case, falling back to the classic ajax call
		// unchanged for anything else (see mail_tryJmapMove()). Reconciles the optimistic removal
		// above on failure either way - a message that never actually moved must come back, not
		// silently vanish until the next reload reveals it.
		Promise.resolve(this.mail_tryJmapMove(target, messages, isArchiveShortcut, classicMove) ?? classicMove())
			.catch((e) =>
			{
				this.egw.message(e?.message || this.egw.lang('Failed to move messages'), 'error');
				if (!messages['all']) nm.refresh();
			});
	}

	/**
	 * copy - implementation of the move action from drag n drop
	 *
	 * @param _action
	 * @param _senders - the representation of the elements dragged
	 * @param _target - the representation of the target
	 */
	copy(_action,_senders,_target) {
		this.checkAllSelected(_action,_senders,_target,true);
	}

	/**
	 * Try the fast client-side JMAP copy path - MailJmap.copyMessages() for an explicit selection,
	 * or copyAllMatching() for "select all matching the current filter" - within one account.
	 * Cross-account copies fall through to the classic path (copyMessages()/copyAllMatching() both
	 * throw for those). Returns null if not applicable (caller falls back to the unchanged classic
	 * call directly); otherwise a Promise that either succeeds via JMAP or, on any failure, falls
	 * back to that same classic call. Mirrors mail_tryJmapMove() exactly, minus the "move to
	 * archive" shortcut concept, which doesn't apply to copy.
	 */
	private mail_tryJmapCopy(target : string, messages : any, classicCopy : () => Promise<any>) : Promise<any> | null
	{
		const sepIndex = target.indexOf('::');
		const targetProfileID = sepIndex > 0 ? target.substring(0, sepIndex) : '';
		const targetFolderPath = sepIndex > 0 ? target.substring(sepIndex + 2) : '';
		if (!targetProfileID || !targetFolderPath)
		{
			return null;
		}
		if (messages['all'])
		{
			return this.jmap.copyAllMatching(this.buildJmapQuery(messages), targetProfileID, targetFolderPath)
				.catch((e) => this.mail_handleJmapError(e, classicCopy));
		}
		if (!Array.isArray(messages.msg) || !messages.msg.length)
		{
			return null;
		}
		let references : JmapMessageReference[];
		try
		{
			references = messages.msg.map((id : string) => this.jmap.messageReference(id));
		}
		catch (e)
		{
			return null;
		}
		return this.jmap.copyMessages(references, targetProfileID, targetFolderPath)
			.catch((e) => this.mail_handleJmapError(e, classicCopy));
	}

	/**
	 * callCopy - implementation of the copy action from drag n drop
	 *
	 * @param _action
	 * @param _senders - the representation of the elements dragged
	 * @param _target - the representation of the target
	 * @param _allMessagesChecked
	 */
	callCopy(_action,_senders,_target,_allMessagesChecked) {
		var target = _action.id == 'drop_copy_mail' ? _target.id : _action.id.substr(5);
		var messages = this.getFormData(_senders);
		if (typeof _allMessagesChecked=='undefined') _allMessagesChecked=false;
		// TODO: Write move/copy function which cares about doing the same stuff
		// as the "onNodeSelect" function!
		messages['all'] = _allMessagesChecked;
		if (messages['all']=='cancel') return false;
		if (messages['all']) messages['activeFilters'] = this.getActiveFilters(_action);
		var self = this;
		const classicCopy = () => egw.json('mail.mail_ui.ajax_copyMessages',[target, messages],function (){self.unlockTree();})
			.sendRequest();
		// Server response contains refresh

		// Fast client-side JMAP path for the common case, falling back to the classic ajax call
		// unchanged for anything else (see mail_tryJmapCopy()). No optimistic UI change to
		// reconcile here (copy never removes/alters the source row), but still needs a message on
		// failure - mail_handleJmapError() no longer shows one itself.
		Promise.resolve(this.mail_tryJmapCopy(target, messages, classicCopy) ?? classicCopy())
			.catch((e) => this.egw.message(e?.message || this.egw.lang('Failed to copy messages'), 'error'));
	}

	/**
	 * addFolder - implementation of the AddFolder action of right click options on the tree
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	addFolder(_action,_senders) {
		//action.id == 'add'
		//_senders.iface.id == target leaf / leaf to edit
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var OldFolderName = ftree.getLabel(_senders[0].id).replace(this._unseen_regexp,'');
		var buttons = [
			{label: this.egw.lang("Add"), id: "add", image:'plus', "class": "ui-priority-primary", "default": true},
			{label: this.egw.lang("Cancel"), id: "cancel", image:'cancelDialog'}
		];
		Et2Dialog.show_prompt((_button_id, _value) =>
			{
				var NewFolderName = null;
				if (_value.length > 0)
				{
					NewFolderName = _value;
				}
				//alert(NewFolderName);
				if (NewFolderName && NewFolderName.length > 0)
				{
					switch (_button_id)
					{
						case "add":
							(this.tryJmapAddFolder(_senders[0].id, NewFolderName) ??
								egw.json('mail.mail_ui.ajax_addFolder', [_senders[0].id, NewFolderName]).sendRequest(true));
							return;
					case "cancel":
				}
			}
		},
		this.egw.lang("Enter the name for the new Folder:"),
		this.egw.lang("Add a new Folder to %1:",OldFolderName),
		'', buttons);
	}

	/**
	 * Try the fast client-side JMAP create-folder path - MailJmap.createMailbox(). Refreshes the
	 * parent's tree level on success; on failure shows the error directly, there's no classic
	 * fallback (see mail_folderTreeAutoload()'s docblock for why).
	 */
	private tryJmapAddFolder(parentTreeId : string, name : string) : Promise<any> | null
	{
		const [profileID, parentPath] : [string, string] = parentTreeId.indexOf('::') !== -1 ?
			parentTreeId.split('::', 2) as [string, string] : [parentTreeId, ''];

		return this.jmap.createMailbox(profileID, parentPath, name).then(() =>
		{
			return this.refreshFolderLevel(profileID, parentPath);
		}).catch((e) => this.egw.message(e.message, 'error'));
	}

	/**
	 * renameFolder - implementation of the RenameFolder action of right click options on the tree
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	renameFolder(_action,_senders) {
		//action.id == 'rename'
		//_senders.iface.id == target leaf / leaf to edit
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var OldFolderName = ftree.getLabel(_senders[0].id).replace(this._unseen_regexp,'');
		var buttons = [
			{label: this.egw.lang("Rename"), id: "rename", "class": "ui-priority-primary", image: 'edit', "default": true},
			{label: this.egw.lang("Cancel"), id: "cancel", image:'cancelDialog'}
		];
		Et2Dialog.show_prompt((_button_id, _value) =>
			{
				var NewFolderName = null;
				if (_value.length > 0)
				{
					NewFolderName = _value;
				}
				//alert(NewFolderName);
				if (NewFolderName && NewFolderName.length > 0)
				{
					switch (_button_id)
					{
						case "rename":
							(this.tryJmapRenameFolder(_senders[0].id, NewFolderName) ??
								egw.json('mail.mail_ui.ajax_renameFolder', [_senders[0].id, NewFolderName]).sendRequest(true));
							return;
					case "cancel":
				}
			}
		},
		this.egw.lang("Rename Folder %1 to:",OldFolderName),
		this.egw.lang("Rename Folder %1 ?",OldFolderName),
		OldFolderName, buttons);
	}

	/**
	 * Try the fast client-side JMAP rename path - MailJmap.renameMailbox() (same parent, new leaf
	 * name only - matches classic ajax_renameFolder()'s own "rename in place" semantics, no move).
	 * Refreshes the parent's tree level on success; on failure shows the error directly, there's no
	 * classic fallback (see mail_folderTreeAutoload()'s docblock for why).
	 */
	private tryJmapRenameFolder(treeId : string, newName : string) : Promise<any> | null
	{
		if (treeId.indexOf('::') === -1) return null;	// an account root can't be renamed
		const [profileID, path] : [string, string] = treeId.split('::', 2) as [string, string];
		const parentPath = path.includes('/') ? path.substring(0, path.lastIndexOf('/')) : '';

		return this.jmap.renameMailbox(profileID, path, newName).then(() =>
		{
			return this.refreshFolderLevel(profileID, parentPath);
		}).catch((e) => this.egw.message(e.message, 'error'));
	}

	/**
	 * moveFolder - implementation of the MoveFolder action on the tree
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _senders - the representation of the tree leaf to be manipulated
	 * @param {egwActionObject} destination Drop target egwActionObject representing the destination
	 */
	moveFolder(_action,_senders,destination) {
		if(!destination || !destination.id)
		{
			egw.debug('warn', "Move folder, but no target");
			return;
		}
		var sourceProfile = _senders[0].id.split('::');
		var targetProfile = destination.id.split('::');
		if (sourceProfile[0]!=targetProfile[0])
		{
			egw.message(this.egw.lang('Moving Folders from one Mailaccount to another is not supported'), 'error');
			return;
		}
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var src_label = _senders[0].id.replace(/^[0-9]+::/,'');
		var dest_label = destination.id.replace(/^[0-9]+::/,'');

		var callback = (_button) =>
		{
			if (_button == Et2Dialog.YES_BUTTON)
			{
				egw.appName = 'mail';
				egw.message(egw.lang('Folder %1 is moving to folder %2', src_label, dest_label));
				egw.loading_prompt('mail_moveFolder', true, '', '#egw_fw_basecontainer');
				for (var i = 0; i < _senders.length; i++)
				{
					(this.tryJmapMoveFolder(_senders[i].id, destination.id) ??
						egw.request('mail.mail_ui.ajax_MoveFolder', [_senders[i].id, destination.id]))
						.finally(() =>
							{
								// Move is done (successfully or not), remove loading
								var id = destination.id.split('::');
								//refersh the top parent
								ftree.refreshItem(id[0], null);
								egw.loading_prompt('mail_moveFolder', false);
							}
						);
				}
			}
		};
		Et2Dialog.show_dialog(callback, this.egw.lang('Are you sure you want to move folder %1 to folder %2?',
			src_label, dest_label), this.egw.lang('Move folder'), {}, Et2Dialog.BUTTONS_YES_NO, Et2Dialog.WARNING_MESSAGE);
	}

	/**
	 * Try the fast client-side JMAP move path - MailJmap.moveMailbox(). Same-account only
	 * (moveFolder() already rejected a cross-account move before ever reaching this).
	 * Refreshes *both* the source's old parent level and the destination level on success (the
	 * moved node disappears from one, appears in the other); on failure shows the error directly,
	 * there's no classic fallback (see mail_folderTreeAutoload()'s docblock for why).
	 */
	private tryJmapMoveFolder(sourceTreeId : string, destTreeId : string) : Promise<any> | null
	{
		if (sourceTreeId.indexOf('::') === -1) return null;	// an account root can't be moved
		const [profileID, sourcePath] : [string, string] = sourceTreeId.split('::', 2) as [string, string];
		const sourceParentPath = sourcePath.includes('/') ? sourcePath.substring(0, sourcePath.lastIndexOf('/')) : '';
		const destPath = destTreeId.indexOf('::') !== -1 ? destTreeId.split('::', 2)[1] : '';

		return this.jmap.moveMailbox(profileID, sourcePath, destPath).then(() =>
		{
			return Promise.all([
				this.refreshFolderLevel(profileID, sourceParentPath),
				this.refreshFolderLevel(profileID, destPath),
			]);
		}).catch((e) => this.egw.message(e.message, 'error'));
	}

	/**
	 * deleteFolder - implementation of the DeleteFolder action of right click options on the tree
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	deleteFolder(_action,_senders)
	{
		//action.id == 'delete'
		//_senders.iface.id == target leaf / leaf to edit
		var ftree = this.et2.getWidgetById(this.nm_index + '[foldertree]');
		var OldFolderName = ftree.getLabel(_senders[0].id).replace(this._unseen_regexp, '');
		var buttons = [
			{label: this.egw.lang("Yes"), id: "delete", "class": "ui-priority-primary", "default": true, image: "check"},
			{label: this.egw.lang("Cancel"), id: "cancel", image: "cancel"}
		];
		Et2Dialog.show_dialog((_button_id, _value) =>
			{
				switch (_button_id)
				{
					case "delete":
						this.jmap.invalidateQuota(_senders[0].id.split('::', 1)[0]);
						(this.tryJmapDeleteFolder(_senders[0].id) ??
							egw.json('mail.mail_ui.ajax_deleteFolder', [_senders[0].id]).sendRequest(true));
						return;
					case "cancel":
				}
			},
			this.egw.lang("Do you really want to DELETE Folder %1 ?", OldFolderName) + " " + (ftree.hasChildren(_senders[0].id) ? this.egw.lang("All subfolders will be deleted too, and all messages in all affected folders will be lost") : this.egw.lang("All messages in the folder will be lost")),
			this.egw.lang("DELETE Folder %1 ?", OldFolderName),
			OldFolderName, buttons);
	}

	/**
	 * Try the fast client-side JMAP delete path - MailJmap.deleteMailbox(). Refreshes the parent's
	 * tree level on success; on failure shows the error directly, there's no classic fallback (see
	 * mail_folderTreeAutoload()'s docblock for why).
	 */
	private tryJmapDeleteFolder(treeId : string) : Promise<any> | null
	{
		if (treeId.indexOf('::') === -1) return null;	// an account root can't be deleted
		const [profileID, path] : [string, string] = treeId.split('::', 2) as [string, string];
		const parentPath = path.includes('/') ? path.substring(0, path.lastIndexOf('/')) : '';

		return this.jmap.deleteMailbox(profileID, path).then(() =>
		{
			return this.refreshFolderLevel(profileID, parentPath);
		}).catch((e) => this.egw.message(e.message, 'error'));
	}

	/**
	 * Send names of uploaded files (again) to server, to process them: either copy to vfs or ask overwrite/rename
	 *
	 * @param _event
	 * @param _file_count
	 * @param {string?} _path where the file is uploaded to, default current directory
	 */
	uploadForImport(_event, _file_count, _path)
	{
		// path is probably not needed when uploading for file; maybe it is when from vfs
		if(typeof _path == 'undefined')
		{
			//_path = this.get_path();
		}
		if (_file_count && !jQuery.isEmptyObject(_event.data.getValue()))
		{
			var widget = _event.data;
//			var request = new egw_json_request('mail_ui::ajax_importMessage', ['upload', widget.getValue(), _path], this);
//			widget.set_value('');
//			request.sendRequest();//false, this._upload_callback, this);
			this.et2_obj.submit();
		}
	}

	/**
	* Upload for import (VFS)
	*
	* @param {egw object} _egw
	* @param {widget object} _widget
	* @param {window object} _window
	*/
	vfsUploadForImport(_egw, _widget, _window) {
		if (jQuery.isEmptyObject(_widget)) return;
		if (!jQuery.isEmptyObject(_widget.getValue()))
		{
			this.et2_obj.submit();
		}
	}

	/**
	 * Focus handler for folder, address, reject textbox/taglist to automatic check associated radio button
	 *
	 * @param {event} _ev
	 * @param {object} _widget taglist
	 *
	 */
	sieveFocusRadioBtn(_ev, _widget)
	{
		_widget.getRoot().getWidgetById('action').set_value(_widget.id.replace(/^action_([^_]+)_text$/, '$1'));
	}

	/**
	 * Select all aliases
	 *
	 */
	sieveVacAllAliases()
	{
		var aliases = [];
		var tmp = [];
		var addr = this.et2.getWidgetById('addresses');
		var addresses = this.et2.getArrayMgr('sel_options').data.addresses;

		for(var id in addresses) aliases.push(id);
		if (addr)
		{
			tmp = aliases.concat(addr.get_value());

			// returns de-duplicate items of an array
			var deDuplicator = function (item,pos)
			{
				return tmp.indexOf(item) == pos;
			};

			aliases = tmp.filter(deDuplicator);
			addr.set_value(aliases);
		}
	}

	/**
	 * Disable/Enable date widgets on vacation seive rules form when status is "by_date"
	 *
	 */
	vacationFilterStatusChange()
	{
		var status = this.et2.getWidgetById('status');
		var s_date = this.et2.getWidgetById('start_date');
		var e_date = this.et2.getWidgetById('end_date');
		var by_date_label = this.et2.getWidgetById('by_date_label');

		if (status && s_date && e_date && by_date_label)
		{
			s_date.set_disabled(status.get_value() != "by_date");
			e_date.set_disabled(status.get_value() != "by_date");
			by_date_label.set_disabled(status.get_value() != "by_date");
		}
	}

	/**
	 * action - handling actions on sieve rules
	 *
	 * @param _type - action name
	 * @param _selected - selected row from the sieve rule list
	 */
	action(_type, _selected)
	{
		var  actionData ;
		var that = this;
		var typeId = _type.id;
		var linkData = '';
		var ruleID = ((_selected[0].id.split("_").pop()) - 1); // subtract the row id from 1 because the first row id is reserved by grid header
		if (_type)
		{

			switch (_type.id)
			{
				case 'delete':

					var callbackDeleteDialog = function (button_id)
					{
						if (button_id == Et2Dialog.YES_BUTTON)
						{
							actionData = _type.parent.data.widget.getArrayMgr('content');
							that._do_action(typeId, actionData['data'], ruleID);
						}
					};
					Et2Dialog.show_dialog(callbackDeleteDialog, this.egw.lang("Do you really want to DELETE this Rule"), this.egw.lang("Delete"), {}, Et2Dialog.BUTTONS_YES_CANCEL, Et2Dialog.WARNING_MESSAGE);

					break;
				case 'add'	:
					linkData = "mail.mail_sieve.edit";
					this.egw.open_link(linkData,'_blank',"600x690");
					break;
				case 'edit'	:
					linkData = "mail.mail_sieve.edit&ruleID="+ruleID;
					this.egw.open_link(linkData,'_blank',"600x690");
					break;
				case 'enable':
					actionData = _type.parent.data.widget.getArrayMgr('content');
					this._do_action(typeId,actionData['data'],ruleID);
					break;
				case 'disable':
					actionData = _type.parent.data.widget.getArrayMgr('content');
					this._do_action(typeId,actionData['data'],ruleID);
					break;

			}
		}

	}

	/**
	* Send back sieve action result to server
	*
	* @param {string} _typeID action name
	* @param {object} _data content
	* @param {string} _selectedID selected row id
	* @param {string} _msg message
	*
	*/
	_do_action(_typeID, _data,_selectedID,_msg)
	{
		if (_typeID && _data)
		{
			var request = this.egw.json('mail.mail_sieve.ajax_action', [_typeID,_selectedID,_msg],null,null,true);
			request.sendRequest();
		}
	}

	/**
	* Send ajax request to server to refresh the sieve grid
	*/
	sieveRefresh()
	{
		this.et2._inst.submit();
	}

	/**
	 * Select the right combination of the rights for radio buttons from the selected common right
	 *
	 * @@param {jQuery event} event
	 * @param {widget} widget common right selectBox
	 *
	 */
	aclCommonRightsSelector(event,widget)
	{
		var rowId = widget.id.replace(/[^0-9.]+/g, '');
		var rights = [];

		switch (widget.get_value())
		{
			case 'custom':
				break;
			case 'aeiklprstwx':
				rights = widget.get_value().replace(/[k,x,t,e]/g,"cd").split("");
				break;
			default:
				rights = widget.get_value().split("");
		}
		if (rights.length > 0)
		{
			for (var i=0;i<this.aclRights.length;i++)
			{
				var rightsWidget = this.et2.getWidgetById(rowId+'[acl_' + this.aclRights[i]+ ']');
				rightsWidget.set_value((jQuery.inArray(this.aclRights[i],rights) != -1 )?true:false);
				if ((rights.indexOf('c') == -1 && ['k','x'].indexOf(this.aclRights[i]) > -1)
						|| (rights.indexOf('d') == -1 && ['e','x','t'].indexOf(this.aclRights[i]) > -1 ))
				{
					rightsWidget.set_readonly(false);
				}
			}
		}
	}

	/**
	 *
	 * Choose the right common right option for common ACL selecBox
	 *
	 * @param {jQuery event} event
	 * @param {widget} widget radioButton rights
	 *
	 */
	aclCommonRights(event, widget)
	{
		var rowId = widget.id.replace(/[^0-9.]+/g, '');
		var aclCommonWidget = this.et2.getWidgetById(rowId + '[acl]');
		var rights = '';
		var selectedBox = widget.id;
		var virtualDelete = ['e','t','x'];
		var virtualCreate = ['k','x'];

		for (let i=0;i<this.aclRights.length;i++)
		{
			var rightsWidget = this.et2.getWidgetById(rowId+'[acl_' + this.aclRights[i]+ ']');
			if (selectedBox == rowId+'[acl_c]' && virtualCreate.indexOf(this.aclRights[i])>-1)
			{
				rightsWidget.set_value(false);
				rightsWidget.set_readonly(widget.get_value() == "true" ? true:false);
			}
			if (selectedBox == rowId+'[acl_d]' && virtualDelete.indexOf(this.aclRights[i])>-1)
			{
				rightsWidget.set_value(false);
				rightsWidget.set_readonly(widget.get_value() == "true" ? true:false);
			}
			if (rightsWidget.get_value() == "true")
				rights += this.aclRights[i];
		}

		for (let i=0;i<this.aclCommonRights.length;i++)
		{
			if (rights.split("").sort().toString() == this.aclCommonRights[i].split("").sort().toString())
				rights = this.aclCommonRights[i];
		}
		if (jQuery.inArray(rights,this.aclCommonRights ) == -1 && rights !='lrswipcda')
		{
			aclCommonWidget.set_value('custom');
		}
		else if (rights =='lrswipcda')
		{
			aclCommonWidget.set_value('aeiklprstwx');
		}
		else
		{
			aclCommonWidget.set_value(rights);
		}
	}

	/**
	 * Open seive filter list
	 *
	 * @param {action} _action
	 * @param {sender} _senders
	 *
	 */
	editSieve(_action, _senders)
	{
		var acc_id = parseInt(_senders[0].id);

		var url = this.egw.link('/index.php',{
					'menuaction': 'mail.mail_sieve.index',
					'acc_id': acc_id,
					'ajax': 'true'
		});

		// an ugly hack for idots to show up sieve rules not in an iframe
		// but as new link, better to remove it after get rid of idots template
		if (typeof window.framework == 'undefined')
		{
			this.egw.open_link(url);
		}
		else
		{
			this.loadIframe(url);
		}
	}

	/**
	 * Load an url on an iframe
	 *
	 * @param {string} _url string egw url
	 * @param {iframe widget} _iFrame an iframe to be set if non, extra_iframe is default
	 *
	 * @return {boolean} return TRUE if success, and FALSE if iframe not given
	 */
	loadIframe(_url, _iFrame)
	{
		var mailSplitter = this.et2.getWidgetById('splitter');
		var quotaipercent = this.et2.getWidgetById('nm[quotainpercent]');
		var iframe = _iFrame || this.et2.getWidgetById('extra_iframe');
		if (typeof iframe != 'undefined' && iframe)
		{
			if (_url)
			{
				iframe.set_src(_url);
			}
			if (typeof mailSplitter != 'undefined' && mailSplitter && typeof quotaipercent != 'undefined')
			{
				mailSplitter.set_disabled(!!_url);
				quotaipercent.set_disabled(!!_url);
				iframe.set_disabled(!_url);
			}
			// extra_iframe used for showing up sieve rules
			// need some special handling for mobile device
			// as we wont have splitter, and also a fix for
			// iframe with display none
			if (iframe.id == "extra_iframe")
			{
				if (egwIsMobile())
				{
					var nm = this.et2.getWidgetById(this.nm_index);
					nm.set_disabled(!!_url);
					iframe.set_disabled(!_url);
				}
				// Set extra_iframe a class with height and width
				// and position relative, seems iframe display none
				// with 100% height/width covers mail tree and block
				// therefore block the click handling
				if (!iframe.disabled)
				{
					iframe.set_class('mail-index-extra-iframe');
				}
				else
				{
					iframe.set_class('');
				}
			}
			return true;
		}
		return false;
	}

	/**
	 * Edit vacation message
	 *
	 * @param {action} _action
	 * @param {sender} _senders
	 */
	editVacation(_action, _senders)
	{
		let acc_id;
		if (!Array.isArray(_senders))
		{
			// Coming from "on vacation" in nm header
			acc_id = parseInt(this.et2.getWidgetById('nm[foldertree]').value);
		}
		else
		{
			// Coming from tree
			acc_id = parseInt(_senders[0].id);
		}
		this.egw.open_link('mail.mail_sieve.editVacation&acc_id=' + acc_id, '_blank', '700x800');
	}

	subscriptionRefresh(_data)
	{
		console.log(_data);
	}

	/**
	 * Popup the subscription dialog
	 *
	 * @param {action} _action
	 * @param {sender} _senders
	 */
	editSubscribe(_action,_senders)
	{
		var acc_id = parseInt(_senders[0].id);
		this.egw.open_link('mail.mail_ui.subscription&acc_id='+acc_id, '_blank', '720x580');
	}

	/**
	 * Subscribe selected unsubscribed folder
	 *
	 * @param {action} _action
	 * @param {sender} _senders
	 */
	subscribeFolder(_action,_senders)
	{
		var mailbox = _senders[0].id.split('::');
		var folder = mailbox[1], acc_id = mailbox[0];
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		this.egw.message(this.egw.lang('Subscribe to Folder %1',ftree.getLabel(_senders[0].id).replace(this._unseen_regexp,'')), 'success');
		(this.tryJmapSetSubscribed(_senders[0].id, true) ??
			egw.json('mail.mail_ui.ajax_foldersubscription',[acc_id,folder,true]).sendRequest());
	}

	/**
	 * Try the fast client-side JMAP (un)subscribe path - MailJmap.setMailboxSubscribed(). No tree
	 * level refresh needed (unlike add/rename/move/delete) - the node's own id doesn't change, so
	 * just flip its `checked` field locally for instant feedback, matching the classic path's own
	 * fire-and-forget behaviour (it doesn't reshuffle the tree live either). On failure shows the
	 * error directly, there's no classic fallback (see mail_folderTreeAutoload()'s docblock for why).
	 */
	private tryJmapSetSubscribed(treeId : string, subscribed : boolean) : Promise<any> | null
	{
		if (treeId.indexOf('::') === -1) return null;	// an account root has no subscription state
		const [profileID, path] : [string, string] = treeId.split('::', 2) as [string, string];

		return this.jmap.setMailboxSubscribed(profileID, path, subscribed).then(() =>
		{
			const ftree = this.et2?.getWidgetById(this.nm_index + '[foldertree]');
			const node = ftree?.getNode(treeId);
			if (node)
			{
				node.checked = subscribed;
				ftree.requestUpdate();
			}
		}).catch((e) => this.egw.message(e.message, 'error'));
	}

	/**
	 * Unsubscribe selected subscribed folder
	 *
	 * @param {action} _action
	 * @param {sender} _senders
	 */
	unsubscribeFolder(_action,_senders)
	{
		var mailbox = _senders[0].id.split('::');
		var folder = mailbox[1], acc_id = mailbox[0];
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		this.egw.message(this.egw.lang('Unsubscribe from Folder %1',ftree.getLabel(_senders[0].id).replace(this._unseen_regexp,'')), 'success');
		(this.tryJmapSetSubscribed(_senders[0].id, false) ??
			egw.json('mail.mail_ui.ajax_foldersubscription',[acc_id,folder,false]).sendRequest());
	}

	/**
	 * Onclick for foldertree to (un)select children
	 *
	 * Used to (un)check node including all children
	 *
	 * @param {string} _id id of clicked node
	 * @param {et2_tree} _widget reference to tree widget
	 * @param {PoinerEvent} _ev
	 * @return {Promise<any>} resolves once the (un)check - including the "autoload subitems
	 *  first" case - is fully applied; subscriptionSubselect() chains onto this to know when
	 *  it's safe to record what changed.
	 */
	folderTreeSubselect(_id, _widget, _ev) : Promise<any>
	{
		const node = _widget.getNode(_id);
		// do we need to autoload the subitems first
		if (node.child && !node.item.length)
		{
			return _widget.refreshItem(_id).then(() => _widget.setSubChecked(_id, "toggle"));
		}
		return Promise.resolve(_widget.setSubChecked(_id, "toggle"));
	}

	/**
	 * mail.subscribe popup's own onclick (bound in subscriptionLoad(), replacing the
	 * template's static onclick="app.mail.folderTreeSubselect") - runs the same "(un)check
	 * including all children" behaviour, then records whatever it just changed.
	 */
	private subscriptionSubselect(_id : string, _widget : any, _ev : any) : void
	{
		Promise.resolve(this.folderTreeSubselect(_id, _widget, _ev)).then(() =>
			this.recordSubscriptionChange(_widget));
	}

	/**
	 * mail.subscribe popup load (et2_ready()'s 'mail.subscribe' case): try to replace the classic
	 * server-rendered subscription tree with one loaded via JMAP - lazily, one level at a time,
	 * exactly like the main index tree/folder-management dialog (mail_folderTreeAutoload()/
	 * folderManagementLoad()), not the whole account fetched up front.  Always shows every
	 * folder regardless of showAllFoldersInFolderPane, same reasoning as
	 * folderManagementLoad() - this dialog manages subscriptions, including for currently
	 * unsubscribed folders.
	 *
	 * A checkbox tree's rendering only ever consults its own .value array, never a node's own
	 * .checked field (Et2Tree.ts's _optionTemplate()) - seedSubscriptionValue() is what seeds
	 * .value from freshly-loaded .checked data, both here and for every later interactive expand
	 * (see the tree.autoloading wrapper below). Since a not-yet-loaded node obviously can't have
	 * been toggled, subscriptionSave() never needs to force-load the rest of the account
	 * first - see recordSubscriptionChange()'s own docblock.
	 *
	 * On any failure (network, non-JMAP-capable account) this is a no-op: the tree the server
	 * already rendered (with the right initial selection) is left exactly as-is, and
	 * subscriptionSave() falls back to a plain classic submit since _subscriptionChanges
	 * stays null.
	 */
	private subscriptionLoad() : void
	{
		const ftree : any = this.et2.getWidgetById('foldertree');
		// mail_ui::subscription() only ever sets profileId into $preserv (for its own next
		// submit round-trip), not into $content directly - that never actually surfaces via
		// getArrayMgr('content') on the initial load, so read the same acc_id the PHP side
		// itself resolved from, straight off this popup's own URL (editSubscribe() always
		// opens it as .../mail.mail_ui.subscription&acc_id=X)
		const profileID = new URLSearchParams(window.location.search).get('acc_id') ??
			String(this.et2.getArrayMgr('content').getEntry('profileId') ?? '');
		if (!ftree || !profileID) return;

		this._subscriptionChanges = new Map();
		this._subscriptionProfileID = profileID;
		this._subscriptionKnownValue = new Set();
		// replaces the template's static onclick="app.mail.folderTreeSubselect" - same "(un)check
		// including all children" behaviour, plus recording what that changed
		ftree.onclick = (id : string, widget : any, ev : any) => this.subscriptionSubselect(id, widget, ev);
		ftree.addEventListener('et2-selection-change', () => this.recordSubscriptionChange(ftree));
		ftree.autoloading = (item : any) => this.mail_folderTreeAutoload(item, false).then((result) =>
		{
			this.seedSubscriptionValue(ftree, result?.item ?? []);
			return result;
		});

		this.buildRootFolderData(profileID, false).then((data) =>
		{
			if (data === null)
			{
				this._subscriptionChanges = null;
				return;
			}
			ftree.select_options = data;
			this.seedSubscriptionValue(ftree, data);
			// buildRootFolderData() already eagerly embeds INBOX's own children (it's always
			// auto-opened) - seed those too, since they never go through the autoloading wrapper
			const inbox = data.find((node) => node.id === profileID + '::INBOX');
			if (inbox) this.seedSubscriptionValue(ftree, inbox.item);
		}).catch((e) =>
		{
			this._subscriptionChanges = null;
			if (e instanceof JmapUserError)
			{
				this.egw.message(e.message, 'error');
			}
			console.error('MailApp.subscriptionLoad(): JMAP tree load failed, keeping the classic server-rendered tree', e);
		});
	}

	/**
	 * Seed the tree widget's own .value array from freshly-loaded nodes' .checked state, since
	 * Et2Tree's checkbox rendering only ever consults .value, never a node's own .checked field -
	 * called for every batch of nodes as soon as it's loaded (the initial root fetch and every
	 * later interactive expand). Also updates _subscriptionKnownValue to match, so this seeding
	 * itself is never mistaken for a user-driven change by recordSubscriptionChange() - only
	 * an actual toggle *after* a node is already known moves it into _subscriptionChanges.
	 */
	private seedSubscriptionValue(ftree : any, nodes : FolderTreeNode[]) : void
	{
		if (!this._subscriptionChanges || !nodes.length) return;
		const value = new Set<string>(ftree.value || []);
		nodes.forEach((node) =>
		{
			if (node.checked) value.add(node.id);
		});
		ftree.value = [...value];
		this._subscriptionKnownValue = value;
	}

	/**
	 * Record whatever just changed in the tree's .value array (since the last time it was
	 * inspected, either by this method or by seedSubscriptionValue()'s own baseline update)
	 * into _subscriptionChanges - called after every user-driven toggle, both a plain click
	 * (the 'et2-selection-change' listener subscriptionLoad() attaches, which fires once
	 * .value already reflects the single clicked node's new state) and the "(un)check all
	 * children" cascade (subscriptionSubselect(), which can flip several already-loaded
	 * descendants' state at once without firing that event for each one).
	 *
	 * This is the whole point of tracking changes as they happen instead of diffing a full
	 * "original vs current" snapshot at Save time: an unloaded node's checkbox can never have
	 * been clicked, so it can never appear here - no need to eagerly load the rest of the account
	 * first.
	 */
	private recordSubscriptionChange(ftree : any) : void
	{
		if (!this._subscriptionChanges) return;
		const current = new Set<string>(ftree.value || []);
		[...new Set([...this._subscriptionKnownValue, ...current])]
			.filter((id) => this._subscriptionKnownValue.has(id) !== current.has(id))
			.forEach((id) => this._subscriptionChanges.set(id, current.has(id)));
		this._subscriptionKnownValue = current;
	}

	/**
	 * Save/Apply button handler for the mail.subscribe popup (button[save] / button[apply]) -
	 * mirrors aclSave()'s exact shape/contract (same handler for both buttons, disambiguated
	 * by _widget.id, true/false return controls whether the normal submit proceeds).
	 *
	 * If subscriptionLoad() never replaced the tree with JMAP data (_subscriptionChanges is
	 * null), this is a complete no-op: return true and let the classic submit/server-side
	 * diff-and-apply run exactly as before - that classic diff needs the *complete* submitted
	 * foldertree value to be trustworthy (everything not present in it is treated as
	 * unsubscribed), which only holds when the whole dialog stayed classic-only.
	 *
	 * Once JMAP has taken over, falling back to that same classic submit on a failure would be
	 * actively wrong now that the tree loads lazily: the submitted .value only reflects whatever
	 * happened to be loaded/toggled, so the classic diff would read every untouched, never-loaded
	 * folder as "now unsubscribed" and mass-unsubscribe the account - so on any failure this just
	 * shows the error and leaves the popup open instead (same "no silent 2nd path" reasoning as
	 * everywhere else this session). _subscriptionChanges is left intact, so simply pressing
	 * Save again retries the exact same changes - reapplying an already-successful one is a
	 * harmless no-op.
	 *
	 * On success, applies exactly the changes recorded in _subscriptionChanges (no reason to
	 * eagerly load/diff the rest of the account first - an unloaded node was never touched, so it
	 * can't be in there) via MailJmap.setMailboxSubscribed(), then refreshes the opener's own tree
	 * and closes/re-submits like aclSave() does.
	 *
	 * @param {Event} _event
	 * @param {Et2Button} _widget button[save] or button[apply]
	 * @return {boolean} true to let the normal submit proceed, false to block it (this handler
	 *	already triggers the submit/close itself once the JMAP calls finish)
	 */
	subscriptionSave(_event, _widget) : boolean
	{
		if (!this._subscriptionChanges) return true;

		const profileID = this._subscriptionProfileID;
		const changes = [...this._subscriptionChanges];

		Promise.all(changes.map(([id, subscribed]) =>
		{
			const path = id.split('::', 2)[1] ?? '';
			return this.jmap.setMailboxSubscribed(profileID, path, subscribed);
		})).then(() =>
		{
			window.opener?.app?.mail?.refreshFolderLevel?.(profileID, '');
			_widget.id === 'button[save]' ? window.close() : this.et2._inst.submit();
		}).catch((e) =>
		{
			this.egw.message(e?.message || this.egw.lang('Account not reachable'), 'error');
			console.error('MailApp.subscriptionSave(): JMAP save failed', e);
		});
		return false;
	}

	/**
	 * Populate the folder-management dialog's multi-select tree via JMAP - lazily, exactly like
	 * the main index tree and the mail.subscribe popup (mail_folderTreeAutoload()/
	 * getRootFolders()): the top level and INBOX's own direct children load immediately,
	 * everything deeper loads on demand as the user expands a node - eagerly fetching a large
	 * account's entire tree just to populate a dialog the user might only use to delete one
	 * folder would be wasteful.
	 *
	 * mail_folderTreeAutoload() is reused as-is for expanding any deeper node - on a JMAP failure
	 * it shows an error leaf rather than falling back to a second, classic code path (see its own
	 * docblock).
	 *
	 * Always shows every folder regardless of the showAllFoldersInFolderPane preference - this
	 * dialog manages folders, including unsubscribed ones, so it must never hide any of them (see
	 * mail_folderTreeAutoload()'s own subscribedOnly param docblock; matches classic
	 * mail_tree.inc.php's own folderManagement()/ajax_folderMgmtTree_autoloading() calls, which
	 * hardcoded $_subscribedOnly=false the same way).
	 *
	 * On any failure (network, non-JMAP-capable account), this is a no-op: the tree keeps whatever
	 * the server already rendered (mail_ui::folderManagement()'s own mail_tree->getTree() call).
	 */
	private folderManagementLoad() : void
	{
		const tree : any = this.et2.getWidgetById('tree');
		const profileID = String(this.et2.getArrayMgr('content').getEntry('acc_id') ?? '');
		if (!tree || !profileID) return;

		tree.autoloading = (item : any) => this.mail_folderTreeAutoload(item, false);
		this.buildRootFolderData(profileID, false).then((data) =>
		{
			if (data === null)
			{
				console.error('MailApp.folderManagementLoad(): account not JMAP-reachable, keeping the classic server-rendered tree');
				return;
			}
			tree.select_options = data;
		}).catch((e) =>
		{
			if (e instanceof JmapUserError)
			{
				this.egw.message(e.message, 'error');
			}
			console.error('MailApp.folderManagementLoad(): JMAP tree load failed, keeping the classic server-rendered tree', e);
		});
	}

	/**
	 * Edit a folder acl for account(s)
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	editAcl(_action, _senders)
	{
		var mailbox = _senders[0].id.split('::');
		var folder = mailbox[1] || 'INBOX', acc_id = mailbox[0];
		this.egw.open_link('mail.mail_acl.edit&mailbox='+ btoa(folder)+'&acc_id='+acc_id, '_blank', '1150x600');
	}

	/**
	 * Submit new selected folder back to server in order to read its acl's rights
	 */
	aclFolderChange()
	{
		var mailbox = this.et2.getWidgetById('mailbox');

		if (mailbox)
		{
			if (mailbox.value.length > 0)
			{
				this.et2._inst.submit();
			}
		}
	}

	/**
	 * Enumerate all subfolders of the currently selected mailbox, then run one menuaction
	 * call per folder through a long-task progress dialog.
	 *
	 * Shared by aclSave() (grant) and aclDeleteRow() (revoke): both need to expand the
	 * mailbox tree client-side, so the server never has to recurse through possibly
	 * thousands of IMAP folders inside a single request (which used to be able to run into
	 * PHP's execution-time limit with no feedback to the user).
	 *
	 * @param {string} menuaction mail.mail_acl.ajax_setACL or mail.mail_acl.ajax_deleteACL
	 * @param {function} buildItem(folder, isRoot, acc_id, account_id) builds the long_task
	 *	list item for one folder; isRoot tells it whether folder is the originally selected
	 *	mailbox itself (getSubfolders() on the server always includes it) or one of its
	 *	descendants - needed because rows without "recursive" checked must only ever be
	 *	applied to the root, never to descendants
	 * @param {string} title long_task dialog title
	 * @param {function} msgFor(count) long_task dialog message
	 * @param {function} callback long_task completion callback
	 */
	aclRunRecursive(menuaction, buildItem, title, msgFor, callback)
	{
		// acc_id/account_id are preserved server-side state for this etemplate, not part of
		// the submitted content - getValues() never has them. The Folder field itself is
		// the only place the client has them, since the server put them there for its own
		// remote-search use (edit() sets searchOptions to {acc_id, account_id}, or just
		// {mailaccount: acc_id} for a non-admin editing their own mailbox).
		const mailboxWidget = this.et2.getWidgetById('mailbox');
		const mailbox = Array.isArray(mailboxWidget.value) ? mailboxWidget.value[0] : mailboxWidget.value;
		const searchOptions : any = mailboxWidget.searchOptions || {};
		const acc_id = searchOptions.acc_id ?? searchOptions.mailaccount;
		const account_id = searchOptions.account_id;

		const loading_id = 'mail-acl-recursive';
		this.egw.loading_prompt(loading_id, true, this.egw.lang('please wait...'));
		const url = this.egw.link(this.egw.ajaxUrl('mail.mail_acl.ajax_folders'), {
			acc_id: acc_id,
			account_id: account_id,
			mailbox: mailbox,
			query: ''
		});
		return this.egw.request(url, []).then((folders : { id : string, label : string }[]) =>
		{
			this.egw.loading_prompt(loading_id, false);
			const list = folders.map(folder => buildItem(folder.id, folder.id === mailbox, acc_id, account_id));
			Et2Dialog.long_task(callback, msgFor(list.length), title, menuaction, list, 'mail');
		});
	}

	/**
	 * Save/Apply button handler for the folder ACL dialog (button[save] / button[apply])
	 *
	 * If none of the grid rows have "recursive" checked, this falls through to the normal
	 * ajax etemplate submit. Otherwise it expands the mailbox tree and grants the rights
	 * one folder at a time through a long-running task with progress feedback.
	 *
	 * @param {Event} _event
	 * @param {Et2Button} _widget button[save] or button[apply]
	 * @return {boolean} true to let the normal submit proceed, false to block it (this
	 *	handler already triggers the submit itself once the long task finishes)
	 */
	aclSave(_event, _widget)
	{
		const values = this.et2._inst.getValues(this.et2);
		// acc_id is only present in getValues() for a row added this session - once a row
		// has been saved once, the server marks its account picker readonly (edit(), to
		// stop it being reassigned to a different account) and readonly widgets are
		// dropped from getValues(). The widget itself still has the real value though.
		const grid : [string, any][] = Object.entries(values.grid || {}).map(([key, row]) =>
			[key, {...(<object>row), acc_id: (<any>row).acc_id ?? this.et2.getWidgetById(key + '[acc_id]')?.value}]);

		if (!grid.some(([, row]) => row.acl_recursive && row.acc_id))
		{
			return true;
		}

		this.aclRunRecursive('mail.mail_acl.ajax_setACL',
			(folder, isRoot, acc_id, account_id) => ({
				...values,
				mailbox: folder,
				acc_id: acc_id,
				account_id: account_id,
				// every row applies to the root folder, but only rows with "recursive"
				// checked may also apply to its descendants (getSubfolders() includes the
				// root itself, so rows without "recursive" would otherwise leak into every
				// subfolder too)
				grid: Object.fromEntries(grid
					.filter(([, row]) => row.acc_id && (isRoot || row.acl_recursive))
					.map(([key, row]) => [key, {...row, acl_recursive: false}]))
			}),
			this.egw.lang('Applying rights'),
			(count) => this.egw.lang('Applying rights to %1 folders ...', count),
			(val) =>
			{
				if (val)
				{
					// recursion is already fully handled by the long task above - reset
					// the checkboxes before any follow-up submit/refresh, or a still-checked
					// "recursive" would re-trigger the old unbounded synchronous loop again
					grid.forEach(([key]) =>
					{
						const cb = this.et2.getWidgetById(key + '[acl_recursive]');
						if (cb) cb.set_value(false);
					});
					_widget.id === 'button[save]' ? window.close() : this.et2._inst.submit();
				}
			}
		);
		return false;
	}

	/**
	 * Delete button handler for one grid row of the folder ACL dialog (delete[$row])
	 *
	 * If the row's "recursive" checkbox isn't set, this behaves exactly like before
	 * (Et2Dialog.confirm(), which submits the row deletion itself on confirmation).
	 * If it is set, after confirming, revokes the ACL from every subfolder one at a time
	 * through a long-running task instead of the old unbounded server-side loop.
	 *
	 * @param {Event} _event
	 * @param {Et2Button} _widget delete[$row] button
	 * @return {boolean|void} always falsy: submitting is either delegated to
	 *	Et2Dialog.confirm() or triggered manually once the long task finishes
	 */
	aclDeleteRow(_event, _widget)
	{
		const rowId = _widget.id.replace(/[^0-9.]+/g, '');
		const values = this.et2._inst.getValues(this.et2);
		const row = values.grid?.[rowId];

		if (!row || !row.acl_recursive)
		{
			return Et2Dialog.confirm(_widget, this.egw.lang('Do you really want to remove all rights from this account?'), this.egw.lang('Remove'));
		}

		// acc_id is readonly (and so missing from getValues()) for any row that was
		// already saved before this session - read the real value straight from the widget
		const identifier = row.acc_id ?? this.et2.getWidgetById(rowId + '[acc_id]')?.value;

		Et2Dialog.show_dialog((button_id) =>
		{
			if (button_id !== Et2Dialog.YES_BUTTON) return;

			this.aclRunRecursive('mail.mail_acl.ajax_deleteACL',
				(folder, isRoot, acc_id, account_id) => ({
					mailbox: folder,
					identifier: identifier,
					acc_id: acc_id,
					account_id: account_id
				}),
				this.egw.lang('Removing rights'),
				(count) => this.egw.lang('Removing rights from %1 folders ...', count),
				(val) =>
				{
					if (val)
					{
						// same reasoning as aclSave(): never let a still-checked
						// "recursive" box re-trigger the old synchronous loop on refresh
						const cb = this.et2.getWidgetById(rowId + '[acl_recursive]');
						if (cb) cb.set_value(false);
						this.et2._inst.submit();
					}
				}
			);
		}, this.egw.lang('Do you really want to remove all rights from this account?'), this.egw.lang('Remove'), {},
			Et2Dialog.BUTTONS_YES_NO, Et2Dialog.WARNING_MESSAGE, undefined, this.egw);
	}

	/**
	 * Edit a mail account
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	editAccount(_action, _senders)
	{
		var acc_id = parseInt(_senders[0].id);
		this.egw.open_link('mail.mail_wizard.edit&acc_id='+acc_id, '_blank', '740x670');
	}

	/**
	 * Lock tree so it does NOT receive any more mouse-clicks
	 */
	lockTree()
	{
		// No-op.  Tree could be set disabled or readonly, but those were not implemented.
	}

	/**
	 * Unlock tree so it receives again mouse-clicks after calling lockTree()
	 */
	unlockTree()
	{
		// No-op, see lockTree()
	}

	/**
	 * Called when tree opens up an account or folder
	 *
	 * @param {String} _id account-id[::folder-name]
	 * @param {et2_widget_tree} _widget
	 * @param {Number} _hasChildren 0 - item has no child nodes, -1 - item is closed, 1 - item is opened
	 */
	openStartTree(_id, _widget, _hasChildren)
	{
		if (_id.indexOf('::') == -1 &&	// it's an account, not a folder in an account
			!_hasChildren)
		{
			this.lockTree();
		}
		return true;	// allow opening of node
	}

	/**
	 * Called when tree opens up an account or folder
	 *
	 * @param {String} _id account-id[::folder-name]
	 * @param {et2_widget_tree} _widget
	 * @param {Number} _hasChildren 0 - item has no child nodes, -1 - item is closed, 1 - item is opened
	 */
	openEndTree(_id, _widget, _hasChildren)
	{
		if (_id.indexOf('::') == -1 &&	// it's an account, not a folder in an account
			_hasChildren == 1)
		{
			this.unlockTree();
		}
	}

	/**
	 * Et2Tree autoloading callback (see et2_ready()'s 'mail.index' case) - lazy per-level JMAP
	 * folder loading, one level per expand, instead of the classic ajax_foldertree menuaction
	 * (see doc/ai/projects/mail-folder-tree-jmap.md).
	 *
	 * item.id is either a bare profileID (an account root node, seeded server-side - expanding
	 * it means "list its top-level folders", parentId null) or "profileID::canonical/path" (a
	 * folder node built by this same callback on an earlier level - item.jmapId is then the raw
	 * JMAP Mailbox id to pass as parentId, not derived from the path: a real JMAP/Stalwart
	 * mailbox id is server-assigned and opaque, see FolderTreeNode's docblock in ./folderTree).
	 *
	 * A "profileID::path" node with no jmapId at all means this node was never built by this
	 * callback (or buildRootFolderData()) in the first place - eg. the classic server-
	 * rendered tree mail_ui::folderManagement()/subscription() seed shown while their own JMAP
	 * root fetch is still in flight (or already declined), which uses the same "profileID::path"
	 * id scheme but has no concept of a JMAP Mailbox id at all.
	 *
	 * Every account is JMAP-eligible in principle (a real JMAP server, or JmapShim wrapping the
	 * exact same IMAP connection classic code would use) - "JMAP isn't reachable right now" means
	 * the underlying connection itself is down, not that JMAP specifically is broken while classic
	 * would still work. Retrying via the classic ajax_foldertree fetch would either hit the exact
	 * same failure one layer down, or - worse - silently paper over a genuine bug in the JMAP/shim
	 * code path by making it look like things still work. Shows an error leaf instead (mirroring
	 * mail_tree.inc.php's own treeLeafNoConnectionArray()) for both a definitive JmapUserError and
	 * a plain decline (no usable token) - no classic fallback for folder-tree browsing anymore.
	 *
	 * @param item the node being expanded
	 * @param subscribedOnly explicit override - omit to fall back to the showAllFoldersInFolderPane
	 *  preference (this callback's own default, used as-is by the main browsing tree). The
	 *  folder-management dialog (folderManagementLoad()) binds this with `false` instead,
	 *  matching classic mail_tree.inc.php's own folderManagement()/ajax_folderMgmtTree_autoloading()
	 *  calls (hardcoded $_subscribedOnly=false) - that dialog manages folders, including
	 *  unsubscribed ones, so every level of its tree must always show everything.
	 * @return {item: FolderTreeNode[]} - Et2Tree's expected handleLazyLoading() result shape
	 */
	mail_folderTreeAutoload(item : any, subscribedOnly? : boolean) : Promise<{ item : FolderTreeNode[] } | any>
	{
		const hasParent = typeof item.id === "string" && item.id.indexOf('::') !== -1;
		const [profileID, parentPath] : [string, string] = hasParent ? item.id.split('::', 2) : [item.id, ''];
		const parentId : string | null = hasParent ? item.jmapId : null;
		const errorLeaf = () => ({item: [buildErrorNode(profileID, parentPath, this.egw.lang('(not connected)'), egw)]});

		if (hasParent && !parentId)
		{
			return Promise.resolve(errorLeaf());
		}

		const fetchLevel = hasParent
			? this.mail_buildFolderLevelData(profileID, parentPath, parentId, subscribedOnly)
			: this.buildRootFolderData(profileID, subscribedOnly);

		return fetchLevel.then((data) =>
			data === null ? errorLeaf() : {item: data}
		).catch((e) =>
		{
			if (e instanceof JmapUserError)
			{
				return {item: [buildErrorNode(profileID, parentPath, e.message, egw)]};
			}
			throw e;
		});
	}

	/**
	 * Root-level folder-tree autoload: fetches the account root AND INBOX's own direct children
	 * in one proactive pass (MailJmap.getRootFolders()) instead of leaving Et2Tree to reactively
	 * fire its own separate lazy-load request for INBOX right after the root level renders (INBOX
	 * is always auto-expanded - see folderTree.ts's buildNode()). Embeds INBOX's children
	 * directly into its node's `item` before this data ever reaches Et2Tree, so INBOX's own
	 * `lazy` flag (Et2Tree.ts's _optionTemplate()) reads false and no further autoload fires for
	 * it - see MailJmap.getRootFolders()'s own docblock for why this still costs two requests, not
	 * one, and why that's still strictly better than today's reactive round trip.
	 *
	 * @param subscribedOnlyOverride see mail_folderTreeAutoload()'s own param docblock
	 */
	private buildRootFolderData(profileID : string, subscribedOnlyOverride? : boolean) : Promise<FolderTreeNode[] | null>
	{
		return this.jmap.getRootFolders(profileID, subscribedOnlyOverride).then((result) =>
		{
			if (result === null) return null;
			const subscribedOnly = subscribedOnlyOverride ?? !isPreferenceOn(egw.preference('showAllFoldersInFolderPane', 'mail'));
			const top = buildFolderLevel(result.top, profileID, '', {subscribedOnly, isTopLevel: true}, egw);
			if (result.inboxChildren !== null)
			{
				const inboxNode = top.find((node) => node.id === profileID + '::INBOX');
				if (inboxNode)
				{
					inboxNode.item = buildFolderLevel(result.inboxChildren, profileID, 'INBOX', {subscribedOnly, isTopLevel: true}, egw);
					inboxNode.child = inboxNode.item.length > 0;
				}
			}
			return top;
		});
	}

	/**
	 * Fetch + build one folder-tree level (shared by mail_folderTreeAutoload() and
	 * refreshFolderLevel()) - null means "JMAP not reachable", same contract
	 * MailJmap.getMailboxChildren() itself has, for the caller to decide its own fallback.
	 *
	 * @param subscribedOnlyOverride see mail_folderTreeAutoload()'s own param docblock
	 */
	private mail_buildFolderLevelData(profileID : string, parentPath : string, parentId : string | null,
		subscribedOnlyOverride? : boolean) : Promise<FolderTreeNode[] | null>
	{
		// classic mail_tree.inc.php only ever special-cases folder icons/names (Trash, Sent,
		// Templates, ...) at this same "top level" scope (Api\Mail::getFolderArrays()'s
		// $_onlyTopLevel mode) - never at any deeper level, even for a folder that happens to
		// carry a matching name/role. Which path counts as "top" depends on the mail server: some
		// put special folders as siblings of INBOX (parentPath === ''), others nest them under it
		// (parentPath === 'INBOX') - both are covered.
		const isTopLevel = parentPath === '' || parentPath === 'INBOX';
		return this.jmap.getMailboxChildren(profileID, parentId, isTopLevel, subscribedOnlyOverride).then((mailboxes) =>
		{
			if (mailboxes === null) return null;
			const subscribedOnly = subscribedOnlyOverride ?? !isPreferenceOn(egw.preference('showAllFoldersInFolderPane', 'mail'));
			return buildFolderLevel(mailboxes, profileID, parentPath, {subscribedOnly, isTopLevel}, egw);
		});
	}

	/**
	 * Re-fetch one folder-tree level via JMAP and push it directly into the tree (Et2Tree's
	 * refreshItem() with data, not a lazy-load re-trigger) - used after a successful folder CRUD
	 * fast path (create/rename/move/delete) to reflect the change immediately, without a full
	 * page reload. Resolves false (never throws) on any failure - callers that care already ran
	 * the actual mutation via a separate JMAP call before calling this; a refresh failure here
	 * just means the tree looks stale until the user next expands/reloads, not that the mutation
	 * itself failed.
	 *
	 * @param profileID
	 * @param parentPath canonical path of the level to refresh, '' for the top level
	 */
	private refreshFolderLevel(profileID : string, parentPath : string) : Promise<boolean>
	{
		const ftree = this.et2?.getWidgetById(this.nm_index + '[foldertree]');
		if (!ftree) return Promise.resolve(false);

		return this.jmap.resolveMailboxId(profileID, parentPath)
			.then((parentId) => this.mail_buildFolderLevelData(profileID, parentPath, parentId))
			.then((data) =>
			{
				if (data === null) return false;
				const parentTreeId = parentPath !== '' ? profileID + '::' + parentPath : profileID;
				return ftree.refreshItem(parentTreeId, {item: data}).then(() => true);
			})
			.catch((e) =>
			{
				console.error('MailApp.refreshFolderLevel(): failed to refresh the tree after a folder change', e);
				return false;
			});
	}

	/**
	 * Print a mail from list

	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 * both parameters can be ommited if we are in a mail.display and not in mail.index
	 */
	print(_action?, _senders?)
	{
		const currentTemp = this.et2_obj.name;

		switch (currentTemp)
		{
			case 'mail.index':
				this.prevPrint(_action, _senders);
				break;
			case 'mail.display':
				this.displayPrint();
		}

	}

	/**
	 * Bind special handler on print media.
	 * -FF and IE have onafterprint event, and as Chrome does not have that event we bind afterprint function to onFocus
	 */
	printForCompose()
	{
		var afterprint = function (){
			egw(window).close();
		};

		if (!window.onafterprint)
		{
			// For browsers which does not support onafterprint event, eg. Chrome
			setTimeout(function() {
				egw(window).close();
			}, 2000);
		}
		else
		{
			window.onafterprint = afterprint;
		}
	}

	/**
	 * Prepare display dialog for printing
	 * copies iframe content to a DIV, as iframe causes
	 * trouble for multipage printing
	 * @param _iframe mail body iframe, can be ommited to use querySelector
	 */
	preparePrint(_iframe?: HTMLIFrameElement)
	{
		const mainIframe = _iframe || document.body.querySelector('#mail-display_mailDisplayBodySrc');
		let tmpPrintDiv = document.body.querySelector('#tempPrintDiv');
		let notAttached = false;

		if (!tmpPrintDiv)
		{
			tmpPrintDiv = document.createElement('div');
			tmpPrintDiv.id = 'tempPrintDiv';
			tmpPrintDiv.classList.add('tmpPrintDiv');
			notAttached = true;
		}

		if (mainIframe && tmpPrintDiv)
		{
			const copyContent = function ()
			{
				// Wait a little longer
				window.setTimeout(function ()
				{
					tmpPrintDiv.innerHTML = mainIframe.contentDocument.body.innerHTML;
				}, 600);
			}
			copyContent();
			// Wait if iframe fires load, then copy again
			mainIframe.contentDocument.body.addEventListener("load", copyContent);
		}

		// Attach the element to the DOM after maniupulation
		if (notAttached && mainIframe)
		{
			mainIframe.parentNode.insertBefore(tmpPrintDiv, mainIframe.nextElementSibling);
		}
		tmpPrintDiv.querySelector('#divAppboxHeader')?.remove();
	}

	/**
	 * Print a mail from Display
	 */
	displayPrint()
	{
		this.egw.message(this.egw.lang('Printing')+' ...', 'success');

		// Make sure the print happens after the content is loaded. Seems Firefox and IE can't handle timing for print command correctly
		setTimeout(function(){
			egw(window).window.print();
		},1000);
	}

	/**
	 * Print a mail from list
	 *
	 * @param {Object} _action
	 * @param {Object} _elems
	 *
	 */
	prevPrint(_action, _elems)
	{
		this.openMessage(_action, _elems, _action.data.images ? 'print_images' : 'print');
	}

	/**
	 * Print a mail from list
	 *
	 * @param {egw object} _egw
	 * @param {widget object} _widget mail account selectbox
	 *
	 */
	vacationChangeAccount(_egw, _widget)
	{
		_widget.getInstanceManager().submit();
	}

	/**
	 * Clear intervals stored in W_INTERVALS which assigned to window
	 */
	clearIntervals()
	{
		for(var i=0;i<this.W_INTERVALS.length;i++)
		{
			clearInterval(this.W_INTERVALS[i]);
			delete this.W_INTERVALS[i];
		}
	}

	/**override egw_app
	 * window title specifically for mail.
	 * This is different from all other apps since it does not write "mail-display" infront
	 *
	 */
	_set_Window_title(){
		document.title = this.getWindowTitle();
	}
	/**
	 * Window title getter function in order to set the window title
	 *
	 * @returns {string|undefined} window title
	 */
	getWindowTitle()
	{
		//mail display uses #mail-display_mailDisplayDetails_subject text and
		// mail compose uses #mail-compose_subject input
		const widget:Et2Textbox | Et2Description = document.querySelector('#mail-display_mailDisplayDetails_subject') ||
			document.querySelector('#mail-compose_subject')
		return widget?.value
	}

	/**
	 *
	 * @returns {undefined}
	 */
	prepareMailvelopePrint()
	{
		var tempPrint = jQuery('div#tempPrintDiv');
		var mailvelopeTopContainer = jQuery('div.mailDisplayContainer');
		var originFrame = jQuery('#mail-display_mailDisplayBodySrc');
		var iframe = jQuery(this.mailvelope_iframe_selector);

		if (tempPrint.length >0)
		{
			// Mailvelope iframe height is approximately equal to the height of encrypted origin message
			// we add an arbitary plus pixels to make sure it's covering the full content in print view and
			// it is not getting acrollbar in normal view
			// @TODO: after Mailvelope plugin provides a hieght value, we can replace the height with an accurate value
			iframe.addClass('mailvelopeIframe').height(originFrame[0].contentWindow.document.body.scrollHeight + 400);
			tempPrint.hide();
			mailvelopeTopContainer.addClass('mailvelopeTopContainer');
		}
	}

	/**
	 * Mailvelope (clientside PGP) integration:
	 * - detect Mailvelope plugin and open "egroupware" keyring (app_base.mailvelopeAvailable and _mailvelopeOpenKeyring)
	 * - display and preview of encrypted messages (mailvelopeDisplay)
	 * - button to toggle between regular and encrypted mail (togglePgpEncrypt)
	 * - compose encrypted messages (mailvelopeCompose, compose.submitAction)
	 * - fix autosave and save as draft to store encrypted content (saveAsDraft)
	 * - fix inline reply to encrypted message to clientside decrypt message and add signature (mailvelopeCompose)
	 */

	/**
	 * Called on load of preview or display iframe, if mailvelope is available
	 *
	 * @param {Keyring} _keyring Mailvelope keyring to use
	 * @ToDo signatures
	 */
	mailvelopeDisplay(_keyring)
	{
		let self = this;
		let iframe = jQuery('iframe#mail-display_mailDisplayBodySrc,iframe#mail-index_messageIFRAME');
		let armored = iframe.contents().find('td.td_display > pre').text().trim();

		if (armored == "" || armored.indexOf(this.begin_pgp_message) === -1) return;

		let container = iframe.parent()[0];
		let container_selector = this.et2._inst.name == 'mail.display'  ? '.mailDisplayContainer' : `#${container.dom_id}`;
		let options = {
			showExternalContent: this.egw.preference('allowExternalIMGs') == 1	// "1", or "0", undefined --> true or false
		};
		// get sender address, so Mailvelope can check signature
		let from = this.et2._inst.name == 'mail.display' ? this.et2.getArrayMgr('content').data.from : this.et2.getWidgetById('additionalfromaddress').value;
		if (from)
		{
			options.senderAddress = from[0].replace(/^.*<([^<>]+)>$/, '$1');
		}
		window.mailvelope.createDisplayContainer(container_selector, armored, _keyring, options).then(function()
		{
			// hide our iframe to give space for mailvelope iframe with encrypted content
			iframe.hide();
			self.prepareMailvelopePrint();
		},
		function(_err)
		{
			self.egw.message(_err.message, 'error');
		});
	}

	/**
	 * Editor object of active compose
	 *
	 * @var {Editor}
	 */
	mailvelope_editor : any = undefined;

	/**
	 * Called on compose, if mailvelope is available
	 *
	 * @param {Keyring} _keyring Mailvelope keyring to use
	 */
	mailvelopeCompose(_keyring)
	{
		delete this.mailvelope_editor;

		// currently Mailvelope only supports plain-text, to this is unnecessary
		var mimeType = this.et2.getWidgetById('mimeType');
		var is_html = mimeType.get_value();
		var container = is_html ? '.mailComposeHtmlContainer' : '.mailComposeTextContainer';
		var editor = this.et2.getWidgetById(is_html ? 'mail_htmltext' : 'mail_plaintext');
		var options = { predefinedText: editor.get_value() };

		// check if we have some sort of reply to an encrypted message
		// --> parse header, encrypted mail to quote and signature so Mailvelope understands it
		var start_pgp = options.predefinedText.indexOf(this.begin_pgp_message);
		if (start_pgp != -1)
		{
			var end_pgp = options.predefinedText.indexOf(this.end_pgp_message);
			if (end_pgp != -1)
			{
				options = {
					quotedMailHeader: options.predefinedText.slice(0, start_pgp).replace(/> /mg, '').trim()+"\n",
					quotedMail: options.predefinedText.slice(start_pgp, end_pgp+this.end_pgp_message.length+1).replace(/> /mg, ''),
					quotedMailIndent: start_pgp != 0,
					predefinedText: options.predefinedText.slice(end_pgp+this.end_pgp_message.length+1).replace(/^> \s*/m,''),
					signMsg: true	// for now (no UI) always sign, when we encrypt
				};
				// set encrypted checkbox, if not already set
				var composeToolbar = this.et2.getWidgetById('composeToolbar');
				if (!composeToolbar.checkbox('pgp'))
				{
					composeToolbar.checkbox('pgp',true);
				}
			}
		}

		var self = this;
		mailvelope.createEditorContainer(container, _keyring, options).then(function(_editor)
		{
			self.mailvelope_editor = _editor;
			editor.set_disabled(true);
			mimeType.set_readonly(true);
		},
		function(_err)
		{
			self.egw.message(_err.message, 'error');
		});
	}

	/**
	 * Switch sending PGP encrypted mail on and off
	 *
	 * @param {object} _action toolbar action
	 */
	togglePgpEncrypt(_action)
	{
		var self = this;
		if (_action.checked)
		{
			if (typeof mailvelope == 'undefined')
			{
				this.mailvelopeInstallationOffer();
				// switch encrypt button off again
				this.et2.getWidgetById('composeToolbar')._actionManager.getActionById('pgp').set_checked(false);
				jQuery('button#composeToolbar-pgp').toggleClass('toolbar_toggled');
				return;
			}
			// check if we have keys for all recipents, before switching
			this.mailvelopeGetCheckRecipients().then(function(_recipients)
			{
				var mimeType = self.et2.getWidgetById('mimeType');
				// currently Mailvelope only supports plain-text, switch to it if necessary
				if (mimeType.get_value())
				{
					mimeType.set_value(false);
					self.et2._inst.submit();
					return;	// ToDo: do that without reload
				}
				self.mailvelopeOpenKeyring().then(function(_keyring)
				{
					self.mailvelopeCompose(_keyring);
				});
			})
			.catch(function(_err)
			{
				self.egw.message(_err.message, 'error');
				self.et2.getWidgetById('composeToolbar')._actionManager.getActionById('pgp').set_checked(false);
				jQuery('button#composeToolbar-pgp').toggleClass('toolbar_toggled');
				return;
			});
		}
		else
		{
			// switch Mailvelop off again, but warn user he will loose his content
			Et2Dialog.show_dialog(function (_button_id)
				{
					if (_button_id == Et2Dialog.YES_BUTTON)
					{
						self.et2.getWidgetById('mimeType').set_readonly(false);
						self.et2.getWidgetById('mail_plaintext').set_disabled(false);
						jQuery(self.mailvelope_iframe_selector).remove();
					}
					else
					{
						self.et2.getWidgetById('composeToolbar').checkbox('pgp', true);
					}
				},
				this.egw.lang('You will loose current message body, unless you save it to your clipboard!'),
				this.egw.lang('Switch off encryption?'),
				{}, Et2Dialog.BUTTONS_YES_NO, Et2Dialog.WARNING_MESSAGE, undefined, this.egw);
		}
	}

	/**
	 * Check if we have a key for all recipients
	 *
	 * @returns {Promise.<Array, Error>} Array of recipients or Error with recipients without key
	 */
	mailvelopeGetCheckRecipients()
	{
		// collect all recipients
		let recipients = this.et2.getWidgetById('to').get_value();
		recipients = recipients.concat(this.et2.getWidgetById('cc').get_value());
		recipients = recipients.concat(this.et2.getWidgetById('bcc').get_value());

		return super.mailvelopeGetCheckRecipients(recipients);
	}

	/**
	 * Folder Management, opens the folder magnt. dialog
	 * with the selected acc_id from index tree
	 *
	 * @param {egw action object} _action actions
	 * @param {object} _senders selected node
	 */
	folderManagement(_action,_senders)
	{
		var acc_id = parseInt(_senders[0].id);
		this.egw.open_link('mail.mail_ui.folderManagement&acc_id='+acc_id, '_blank', '720x580');
	}

	/**
	 * Range selection for old dhtmlx tree currently NOT used
	 *
	 * @param {type} _ids
	 * @param {type} _widget
	 * @returns {undefined}
	 */
	folderManagementOnSelect(_ids, _widget)
	{
		// Flag to reset selected items
		var resetSelection = false;

		var self = this;

		/**
		 * helper function to multiselect range of nodes in same level
		 *
		 * @param {string} _a start node id
		 * @param {string} _b end node id
		 * @param {string} _branch total node ids in the level
		 */
		var rangeSelector = function(_a,_b, _branch)
		{
			var branchItems = _branch.split(_widget.input.dlmtr);
			var _aIndex = _widget.input.getIndexById(_a);
			var _bIndex = _widget.input.getIndexById(_b);
			if (_bIndex<_aIndex)
			{
				var tmpIndex = _aIndex;
				_aIndex = _bIndex;
				_bIndex = tmpIndex;
			}
			for(var i =_aIndex;i<=_bIndex;i++)
			{
				self.folderMgmt_setCheckbox(_widget, branchItems[i], !_widget.input.isItemChecked(branchItems[i]));
			}
		};

		// extract items ids
		var itemIds = _ids.split(_widget.input.dlmtr);

		if(itemIds.length == 2) // there's a range selected
		{
			var branch = _widget.input.getSubItems(_widget.input.getParentId(itemIds[0]));
			// Set range of selected/unselected
			rangeSelector(itemIds[0], itemIds[1], branch);
		}
		else if(itemIds.length != 1)
		{
			resetSelection = true;
		}

		if (resetSelection)
		{
			_widget.input._unselectItems();
		}
	}

	/**
	 * Delete button handler
	 * triggers longTask dialog and send delete operation url
	 *
	 */
	folderManagementDeleteBtn()
	{
		const tree = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('tree');

		if (!tree.value.length)
		{
			Et2Dialog.alert(this.egw.lang('You need to select some folders first (by clicking on them)!'), this.egw.lang('Delete selected folders'));
			return;
		}

		const callbackDialog = (_btn) =>
		{
			egw.appName='mail';
			if (_btn === Et2Dialog.YES_BUTTON)
			{
				if (tree)
				{
					const selFolders = tree.value;
					if (selFolders && selFolders.length)
					{
						const msg = egw.lang('Deleting %1 folders in progress ...', selFolders.length);
						Et2Dialog.long_task(function (_val, _resp)
						{
							if (_val && _resp.type !== 'error')
							{
								const stat = selFolders.map(id => id.split('::')[1]);
								// delete the item from index folderTree
								egw.window.app.mail.removeLeaf(stat);
							}
							else
							{
								// submit
								etemplate2.getByApplication('mail')[0].widgetContainer._inst.submit();
							}
						}, msg, egw.lang('Deleting folders'), (treeId : string) => this.folderManagementDeleteOne(treeId), selFolders, 'mail');
						return true;
					}
				}
			}
		};
		Et2Dialog.show_dialog(callbackDialog, this.egw.lang('Are you sure you want to delete all selected folders?'), this.egw.lang('Delete folder'), {},
			Et2Dialog.BUTTON_YES_NO, Et2Dialog.WARNING_MESSAGE, undefined, this.egw);
	}

	/**
	 * Per-folder delete for the folder-management dialog's long_task() batch (folderManagementDeleteBtn())
	 * - the JMAP-first counterpart of the classic ajax_folderMgmt_delete/FolderHandler::folderMgmtDelete(),
	 * now called directly client-side instead of driving long_task's per-item server round-trip
	 * (see Et2Dialog.long_task()'s _item_callback param). Resolves the deleted folder's own (leaf)
	 * name on success - the exact same per-item contract folderMgmtDelete() already had, so
	 * folderManagementDeleteBtn()'s own success-handling (removeLeaf()) needs no change at all.
	 *
	 * On any failure throws a plain Error (long_task()'s own per-item failure contract) with the
	 * folder name and JMAP's error message - there's no classic fallback (see
	 * mail_folderTreeAutoload()'s docblock for why).
	 */
	private folderManagementDeleteOne(treeId : string) : Promise<string>
	{
		const [profileID, path] = treeId.split('::', 2) as [string, string];
		const folderName = path.includes('/') ? path.substring(path.lastIndexOf('/') + 1) : path;

		return this.jmap.deleteMailbox(profileID, path).then(() => folderName)
			.catch((e) =>
			{
				throw new Error(this.egw.lang('Failed to delete %1', folderName) + ': ' + e.message);
			});
	}

	/**
	 * Spam Actions handler
	 *
	 * @param {object} _action egw action
	 * @param {object} _senders nm row
	 */
	spam_actions(_action, _senders)
	{
		var id,fromaddress,domain, email = '';
		var data = {};
		var items = [];
		//if call happens from a popup this.et2 is the wrong reference --- see deleteMessages
		const nm = this.et2.getWidgetById(this.nm_index) ??
			window?.egw?.window?.app?.mail?.et2?.getWidgetById(this.nm_index)
		// called action for a single row from toolbar
		if (_senders.length == 0)
		{
			_senders = [{id:nm.getSelection().ids[0]}];
		}

		for (var i in _senders)
		{
			id = _senders[i].id;
			data = egw.dataGetUIDdata(id);
			fromaddress = data.data.fromaddress.match(/<([^\'\" <>]+)>$/);
			email = (fromaddress && fromaddress[1])?fromaddress[1]:data.data.fromaddress;
			domain = '@'+email.split('@')[1];
			items[i] = {
				'acc_id':id.split('::')[2],
				'row_id':data.data.row_id,
				'uid': data.data.uid,
				'sender': _action.id.match(/domain/)? domain : email
			};
		}

		this.egw.json('mail.mail_ui.ajax_spamAction', [
			_action.id,items
		], function(_data){
			if (_data[1] && _data[1].length > 0)
			{
				egw.refresh(_data[0],'mail',_data[1],'delete');
				nm.clearSelection();
			}
			else
			{
				egw.message(_data[0]);
			}
		}).sendRequest(true);
	}

	spamTitanSetActionTitle(_action, _sender)
	{
		var id = _sender[0].id != 'nm'? _sender[0].id:_sender[1].id;
		var email = this.egw.lang('emails');
		var domain = this.egw.lang('domains');
		var data = egw.dataGetUIDdata(id);
		if(_sender.length === 1 && data && data.data && data.data.fromaddress)
		{
			var fromaddress = data.data.fromaddress.match(/<([^\'\" <>]+)>$/);
			email = (fromaddress && fromaddress[1]) ?fromaddress[1]:data.data.fromaddress;
			domain = email.split('@')[1];
		}
		switch (_action.id.replace(/_all$/, ''))
		{
			case 'whitelist_email_add':
				_action.set_caption(this.egw.lang('Add "%1" into whitelisted emails', email));
				break;
			case 'whitelist_email_remove':
				_action.set_caption(this.egw.lang('Remove "%1" from whiltelisted emails', email));
				break;
			case 'whitelist_domain_add':
				_action.set_caption(this.egw.lang('Add "%1" into whiltelisted domains', domain));
				break;
			case 'whitelist_domain_remove':
				_action.set_caption(this.egw.lang('Remove "%1" from whiltelisted domains', domain));
				break;
			case 'blacklist_email_add':
				_action.set_caption(this.egw.lang('Add "%1" into blacklisted emails', email));
				break;
			case 'blacklist_email_remove':
				_action.set_caption(this.egw.lang('Remove "%1" from blacklisted emails', email));
				break;
			case 'blacklist_domain_add':
				_action.set_caption(this.egw.lang('Add "%1" into blacklisted domains', domain));
				break;
			case 'blacklist_domain_remove':
				_action.set_caption(this.egw.lang('Remove "%1" from blacklisted domains', domain));
				break;
		}

		return true;
	}
	/**
	 * Implement mobile view
	 *
	 * @param {type} _action
	 * @param {type} _sender
	 */
	mobileView(_action, _sender)
	{
		// row id in nm
		const id = _sender[0].id;

		const defaultActions= {
			actions:['delete', 'forward','reply','flagged'], // default actions to display
			check(_action:string)
			{
				return this.actions.includes(_action);
			}
		};

		if (id){
			const content = egw.dataGetUIDdata(id);
			content.data['toolbar'] = this.et2.getArrayMgr('sel_options').getEntry('toolbar');
			if (content.data.toaddress||content.data.fromaddress)
			{
				content.data.additionaltoaddress = (content.data.additionaltoaddress??[]).concat(content.data.toaddress);
				content.data.additionaltoaddress = 	content.data.additionaltoaddress.filter((i, item) => {
					return content.data.additionaltoaddress.indexOf(i) == item
				});
				content.data.additionalfromaddress = (content.data.additionalfromaddress??[]).concat(content.data.fromaddress);
				content.data.additionalfromaddress = content.data.additionalfromaddress.filter((i, item) => {
					return content.data.additionalfromaddress.indexOf(i) == item
				});
			}

			// Set default actions
			for(const action in content.data['toolbar'])
			{
				content.data.toolbar[action]['toolbarDefault'] = defaultActions.check(action);
			}
			// update local storage with added toolbar actions
			egw.dataStoreUID(id,content.data);
		}


		const self = this;
		this.viewEntry(_action, _sender, true, function(etemplate){
			// et2 object in view
			const et2 = etemplate.widgetContainer;
			// iframe to load message
			const iframe = et2.getWidgetById('iframe');
			// toolbar widget
			const toolbar = et2.getWidgetById('toolbar');
			// attachments details title DOM node
			const attachment:Et2Details = document.querySelector('.attachments');
			// Content
			const content = et2.getArrayMgr('content').data;

			// set the current selected row
			et2.mail_currentlyFocussed = id;

			if (content.attachmentsBlock.length>0 && content.attachmentsBlock[0].filename)
			{
				const sel_options = {};
				self.setupViewAttachmentActions(content, sel_options);
				et2.querySelector("et2-details.attachments").querySelector("[slot=summary]").innerHTML += content.attachmentsBlockTitle;
				et2.querySelector('#view_attachmentsBlock').querySelectorAll("et2-dropdown-button").forEach(n =>
				{
					n.select_options = sel_options['attachmentsBlock'][n.id] ?? sel_options['attachmentsBlock']['actions'];
					n.readonly = false;
				});
			}
			else
			{
				// disable attachments area if there are no attachments
				attachment.set_disabled(true);
			}

			toolbar.readonly = false;
			toolbar.actions = content.toolbar || {};


			// Request email body - fast client-side JMAP path, or the full server-rendered page
			// for special-case messages (see loadMessageBody())
			self.loadMessageBody(iframe, id, (doc) =>
			{
				const frame = iframe.getDOMNode();
				if (jQuery(doc.body).find('#calendar-meeting').length > 0)
				{
					jQuery(frame).show();
					// calendar meeting mails still need to be in iframe, therefore, we calculate the height
					// and set the iframe with a fixed height to be able to see all content without getting
					// scrollbar becuase of scrolling issue in iframe
					window.setTimeout(function(){jQuery(frame).height(doc.body.scrollHeight);}, 500);
				}
				else
				{
					self.resolveExternalImages(doc);
					renderAttachmentIndex(doc, content.attachmentsBlock, self.egw);
					// Deal with scrolling by setting iframe size to content height
					jQuery(frame).height(doc.body.scrollHeight);
				}
			});
		});
	}

	/**
	 * Open smime certificate
	 *
	 * @param {type} egw
	 * @param {type} widget
	 * @returns {undefined}
	 */
	smimeSigBtn(egw, widget)
	{
		let url = '';
		if (this.mail_isMainWindow)
		{
			const content = this.egw.dataGetUIDdata(this.mail_currentlyFocussed);
			url = content.data.smimeSigUrl;
		}
		else
		{
			url = this.et2.getArrayMgr("content").getEntry('smimeSigUrl');
		}
		window.egw.openPopup(url,'700','400');
	}

	/**
	 * smime password dialog
	 *
	 * @param {string} _msg message
 	 */
	smimePassDialog(_msg)
	{
		const self = this;
		const pass_exp = egw.preference('smime_pass_exp', 'mail');
		const dialog = loadWebComponent("et2-dialog", {
			callback(_button_id, _value)
			{
				if (_button_id == 'send' && _value)
				{
					const pass = self.et2.getWidgetById('smime_passphrase');
					pass.set_value(_value.value);
					const toolbar = self.et2.getWidgetById('composeToolbar');
					if(typeof toolbar.value==="object")toolbar.value.action = 'send'
					else toolbar.value = {action:'send'};
					egw.set_preference('mail', 'smime_pass_exp', _value.pass_exp);
					self.compose.submitAction(false);
				}
			},
			title: egw.lang('Request for passphrase'),
			buttons: [
				{label: this.egw.lang("Send"), id: "send", image:'send', "class": "ui-priority-primary", "default": true},
				{label: this.egw.lang("Cancel"), id: "cancel", image:'cancelDialog'}
			],
			value:{
				content:{
					value: '',
					message: _msg,
					'exp_min': pass_exp
			}},
			template: egw.webserverUrl+'/api/templates/default/password.xet',
			resizable: false
		},undefined);
		document.body.append(dialog);
	}

	/**
	 * set attachments of smime message for mobile view
	 * @param {type} _attachments
	 */
	setSmimeAttachmentsMobile(_attachments)
	{
		var attachmentsBlock = this.et2_view.widgetContainer.getWidgetById('attachmentsBlock');
		var $attachment = jQuery('.et2_details.attachments');
		if (attachmentsBlock && _attachments.length > 0)
		{
			attachmentsBlock.set_value({content:_attachments});
			$attachment.show();
		}
	}

	/**
	 * Set attachments of smime message
	 *
	 * @param {object} _attachments
	 */
	setSmimeAttachments(_attachments)
	{
		if (egwIsMobile())
		{
			this.setSmimeAttachmentsMobile(_attachments);
			return;
		}
		let data = {};
		let selected = [];

		let cmprAttchObjs = function(_obj1,_obj2)
		{
			for (let i=0;i<_obj1.length;i++)
			{
				if (_obj1[i]['mail_id'] != _obj2[i]['mail_id'] || _obj1[i]['partID'] != _obj2[i]['partID']) return false;
			}
			if (_obj1.length != _obj2.length) return false;
			return true;
		};
		if (_attachments && _attachments.length)
		{
			selected = [_attachments[0]['mail_id']];
			data = egw.dataGetUIDdata(selected[0]);
			// do not call preview if we have the attachments already resolved, avoid infinit loop
			if (data.data.attachmentsBlock.length>0 && cmprAttchObjs(data.data.attachmentsBlock, _attachments)) return;

			data.data.attachmentsBlock = _attachments;
			data.data.attachmentsBlockTitle = _attachments.lenght;
			egw.dataStoreUID(selected[0], data.data);
			this.preview(selected, this.et2.getWidgetById('nm'));
		}
	}
	/**
	 * This function helps to trigger the Push notification immidiately.
	 * @todo: Must be removed after socket push notification is implemented
	 */
	smimeAttachmentsCheckerInterval()
	{
		var self = this;
		var attachmentArea = this.et2.getWidgetById('previewAttachmentArea');
		if (attachmentArea) attachmentArea.getDOMNode().classList.add('loading');
		var interval = window.setInterval(function(){
			self.egw.json('mail.mail_ui.ajax_smimeAttachmentsChecker',null,function(_stop){
				if (_stop)
				{
					window.clearInterval(interval);
				}
			}).sendRequest(true);
		},1000);
	}

	/**
	 *
	 * @param {object} _data smime resolved certificate data
	 * @returns {undefined}
	 */
	setSmimeFlags(_data)
	{
		if (!_data) return;
		var self = this;
		var et2_object = egwIsMobile()? this.et2_view.widgetContainer: this.et2;
		var data = _data;
		var attachmentArea = et2_object.getWidgetById('previewAttachmentArea');
		if (attachmentArea) attachmentArea.getDOMNode().classList.remove('loading');
		var smime_signature = et2_object.getWidgetById('smime_signature');
		var smime_encryption = et2_object.getWidgetById('smime_encryption');
		var mail_container = egwIsMobile()? document.getElementsByClassName('mailContent')[0] :
				egw(window).is_popup() ? document.getElementsByClassName('mailDisplayContainer') :
				et2_object.getWidgetById('mailPreviewContainer').getDOMNode();
		smime_signature.set_disabled(!data.signed);
		smime_encryption.set_disabled(!data.encrypted);
		if (!data.signed)
		{
			this.smimeClearFlags([mail_container]);
			return;
		}
		else if (data.verify)
		{
			mail_container.classList.add((data.class='smime_cert_verified'));
			smime_signature.set_class(data.class);
			smime_signature.set_statustext(data.msg);
		}
		else if (!data.verify && data.cert)
		{
			mail_container.classList.add((data.class='smime_cert_notverified'));
			smime_signature.set_class(data.class);
			smime_signature.set_statustext(data.msg);
		}
		else if (!data.verify && !data.cert)
		{
			mail_container.classList.add((data.class='smime_cert_notvalid'));
			smime_signature.set_class(data.class);
			smime_signature.set_statustext(data.msg);
		}
		if (data.unknownemail)
		{
			mail_container.classList.add((data.class='smime_cert_unknownemail'));
			smime_signature.set_class(data.class);
		}
		data.class = data.class ? data.class : "";
		jQuery(smime_signature.getDOMNode(), smime_encryption.getDOMNode()).off().on('click',function(){
			self.smimeCertAddToContact(data,true);
		}).addClass('et2_clickable');
		jQuery(smime_encryption.getDOMNode()).off().on('click',function(){
			self.smimeCertAddToContact(data, true);
		}).addClass('et2_clickable');
	}

	/**
	 * Reset flags classes and click handler
	 *
	 * @param {jQuery Object} _nodes
	 */
	smimeClearFlags(_nodes)
	{
		for(var i=0;i<_nodes.length;i++)
		{
			_nodes[i].classList.remove(...['smime_cert_verified',
				'smime_cert_notverified',
				'smime_cert_notvalid', 'smime_cert_unknownemail']);
		}
	}

	/**
	 * Inform user about sender's certificate and offers to add it into
	 * relevant contact in addressbook.
	 *
	 * @param {type} _metadata
	 * @param {boolean} _display if set to true will only show close button
	 */
	smimeCertAddToContact(_metadata, _display)
	{
		//do not show the dialog on mobile
		if(egwIsMobile()){
			return;
		}
		if (!_metadata || _metadata.length < 1) return;
		var self = this;
		var content = jQuery.extend(true, {message:_metadata.msg}, _metadata);
		var buttons = [

			{label: this.egw.lang("Close"), id: "close", image:'cancelDialog'}
		];
		if (!_display)
		{
			buttons[1] = {
				label: this.egw.lang("Add this certificate into contact"),
				id: "contact",
				image: "add",
				"class": "ui-priority-primary",
				"default": true
			};
			content.message2 = egw.lang('You may add this certificate into your contact, if you trust this signature.');
		}
		var extra = {
			'presets[email]': _metadata.email,
			'presets[n_given]': _metadata.certDetails.subject.commonName,
			'presets[pubkey]': _metadata.cert,
			'presets[org_name]': _metadata.certDetails.subject.organizationName,
			'presets[org_unit]': _metadata.certDetails.subject.organizationUnitName
		};
		content.class="";
		const dialog = et2_createWidget('et2-dialog', {
			callback(_button_id, _value)
			{
				if (_button_id == 'contact' && _value)
				{
					self.egw.json('mail.mail_ui.ajax_smimeAddCertToContact',
					_metadata,function(_result){
						if (!_result)
						{
							egw.open('','addressbook','add',extra);
						}
						egw.message(_result);
					}).sendRequest(true);
				}
			},
			title: egw.lang('Certificate info for email %1', _metadata.email),
			buttons: buttons,
			minWidth: 500,
			minHeight: 500,
			value:{content:content},
			template: egw.webserverUrl+'/mail/templates/default/smimeCertAddToContact.xet?1',
			resizable: false
		});
		document.body.append(dialog);
	}

	/**
	 * get preview pane state base on selected preference.
	 *
	 * @returns {Boolean} returns true for visible Pane and false for hiding
	 */
	getPreviewPaneState()
	{
		var previewPane = this.egw.preference('previewPane', 'mail') || 'vertical';
		var state = false;
		switch (previewPane)
		{
			case true:
			case '1':
			case 'hide':
			case 'expand':
				state = false;
				break;
			case 'fixed':
				state = true;
				break;
			default: // default is vertical
				state = true;
		}
		return state;
	}

	/**
	 * Creates a dialog for changing meesage subject
	 *
	 * @param {object} _action|_widget
	 * @param {object} _sender|_content
	 */
	modifyMessageSubjectDialog(_action, _sender)
	{
		_sender = _sender ? _sender : [{id:this.mail_currentlyFocussed}];
		var id = (_sender && _sender.uid) ? _sender.row_id:
			_sender[0].id != 'nm'? _sender[0].id:_sender[1].id;
		var data = (_sender && _sender.uid) ? {data:_sender} : egw.dataGetUIDdata(id);
		var subject = data && data.data? data.data.subject : "";

		const dialog = et2_createWidget("et2-dialog",
		{
			callback(_button_id, _value) {
				var newSubject = null;
				if (_value && _value.value) newSubject = _value.value;

				if (newSubject && newSubject.length>0)
				{
					switch (_button_id)
					{
						case Et2Dialog.OK_BUTTON:
							egw.loading_prompt('modifyMessageSubjectDialog', true);
							egw.json('mail.mail_ui.ajax_saveModifiedMessageSubject', [id, newSubject], function (_data)
							{
								egw.loading_prompt('modifyMessageSubjectDialog', false);
								if (_data && !_data.success)
								{
									egw.message(_data.msg, "error");
									return;
								}
								var nm = app.mail.et2.getWidgetById('nm');
								if (nm)
								{
									nm.applyFilters();
								}

							}).sendRequest(true);
							return;
						case "cancel":
					}
				}
			},
			title: this.egw.lang("Modify subject"),
			buttons: Et2Dialog.BUTTONS_OK_CANCEL,
			value: {content: {value: subject}},
			template: egw.webserverUrl + '/mail/templates/default/modifyMessageSubjectDialog.xet?1',
			resizable: false,
			width: 500
		});
		document.body.append(dialog);
	}

	/**
	 * Set predefined addresses for compose dialog
	 *
	 * @param {type} action
	 * @param {type} _senders
	 * @returns {undefined}
	 */
	setPredefinedAddresses(action, _senders)
	{
		const pref_id = _senders[0].id.split('::')[0] + '_predefined_compose_addresses';
		const prefs = egw.deepExtend({}, egw.preference(pref_id, 'mail'));
		let selOptions = {}
		for (const predefined in prefs) {
			selOptions[predefined] = [];
			for (const predefinedElement of prefs[predefined]) {
				selOptions[predefined].push({label: predefinedElement, value: predefinedElement});
			}
		}
		// @ts-ignore
		const dialog = loadWebComponent("et2-dialog",
			{
				callback: function (_button_id, _value) {
					switch (_button_id)
					{
						case Et2Dialog.OK_BUTTON:
							egw.set_preference('mail', pref_id, _value);
							return;
						case "cancel":
					}
				},
				title: this.egw.lang("Predefined addresses for compose"),
				buttons: Et2Dialog.BUTTONS_OK_CANCEL,
				value: {
					content: prefs || {},
					sel_options: selOptions
				},
				minWidth: 410,
				template: egw.webserverUrl + '/mail/templates/default/predefinedAddressesDialog.xet?',
				resizable: false,
			});
		document.body.append(dialog);
	}

	/**
	 * open
	 * @param _node
	 * @param _address
	 */
	onclickCompose(_node, _address)
	{
		if (_address.value && this.egw.preference('force_mailto', 'addressbook') != '1')
		{
			this.egw.open_link('mailto:' + _address.value);
		}
		else
		{
			window.open("mailto:" + _address.value);
		}
	}

	addressbookSelect()
	{
		this.openDialog('addressbook.addressbook_ui.index&template=addressbook.select');
	}

	/**
	 * Show only untranslated has been clicked
	 */
	toggleDetails(_ev, _widget)
	{
		this.nm && this.nm.applyFilters({filter2: _widget.value ? '1' : ''});
	}

	/**
	 * Check if any NM filter or search in app-toolbar needs to be updated to reflect NM internal state
	 *
	 * Overwritten to support the details toggle.
	 *
	 * @param app_toolbar
	 * @param id
	 * @param value
	 */
	checkNmFilterChanged(app_toolbar, id, value)
	{
		super.checkNmFilterChanged(app_toolbar, id, value);

		// details toggle
		if (id === 'filter2')
		{
			const details_toggle = this.et2.getWidgetById('details');
			if (details_toggle && details_toggle.value != (value === '1')) {
				details_toggle.value = value === '1';
			}
		}
	}

	/**
	 * Propagate filters in app_toolbar to NM and filter thingy
	 *
	 * Use as onchange on these filters (named like the ones in NM!)
	 *
	 * Overwritten to call this.searchtypeChange() for cat_id.
	 *
	 * @param _ev
	 * @param _widget
	 */
	changeNmFilter(_ev, _widget)
	{
		super.changeNmFilter(_ev, _widget);

		// open/close date filters
		if (_widget.id === 'cat_id')
		{
				this.searchtypeChange(_ev, _widget);
		}
	}
}
app.classes.mail = MailApp;
