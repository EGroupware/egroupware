/**
 * mail - static javaScript functions
 *
 * @link http://www.egroupware.org
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2013-2020 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
*/

import {AppJS} from "../../api/js/jsapi/app_base.js";
import {et2_createWidget} from "../../api/js/etemplate/et2_core_widget";
import {et2_dialog} from "../../api/js/etemplate/et2_widget_dialog";
import {et2_button} from "../../api/js/etemplate/et2_widget_button";
import {egw_getObjectManager} from '../../api/js/egw_action/egw_action.js';
import {egwIsMobile, egwSetBit} from "../../api/js/egw_action/egw_action_common.js";
import {EGW_AO_FLAG_DEFAULT_FOCUS} from "../../api/js/egw_action/egw_action_constants.js";
import {
	egw_keycode_translation_function,
	egw_keycode_makeValid,
	egw_keyHandler
} from "../../api/js/egw_action/egw_keymanager.js";
import {Et2UrlEmailReadonly} from "../../api/js/etemplate/Et2Url/Et2UrlEmailReadonly";
import {Et2SelectEmail} from "../../api/js/etemplate/Et2Select/Et2SelectEmail";
/* required dependency, commented out because no module, but egw:uses is no longer parsed
*/

/**
 * UI for mail
 *
 * @augments AppJS
 */
app.classes.mail = AppJS.extend(
	{
	appname: 'mail',
	/**
	 * modified attribute in mail app to test new entries get added on top of list
	 */
	modification_field_name: 'date',

	/**
	 * et2 widget container
	 */
	et2: null,
	doStatus: null,

	mail_queuedFolders: [],
	mail_queuedFoldersIndex: 0,

	mail_selectedMails: [],
	mail_currentlyFocussed: '',
	mail_previewAreaActive: true, // we start with the area active

	nm_index: 'nm', // nm name of index
	mail_fileSelectorWindow: null,
	mail_isMainWindow: true,

	// Some state variables to track preview pre-loading
	preview_preload: {
		timeout: null,
		request: null
	},
	/**
	 *
	 */
	subscription_treeLastState : "",

	/**
	 * abbrevations for common access rights
	 * @array
	 *
	 */
	aclCommonRights:['lrs','lprs','ilprs',	'ilprsw', 'aeiklprstwx', 'custom'],
	/**
	 * Demonstrates ACL rights
	 * @array
	 *
	 */
	aclRights:['l','r','s','w','i','p','c','d','k','x','t','e','a'],

	/**
	 * In order to store Intervals assigned to window
	 * @array of setted intervals
	 */
	W_INTERVALS:[],

	/**
	 *
	 * @array of setted timeouts
	 */
	W_TIMEOUTS: [],

	/**
	 * Replace http:// in external image urls with
	 */
	image_proxy: 'https://',

	/**
	 * stores push activated acc ids
	 */
	push_active: {},

	/**
	 * Initialize javascript for this application
	 *
	 * @memberOf mail
	 */
	init: function() {
		this._super.apply(this,arguments);
		if (!this.egw.is_popup())
			// Turn on client side, persistent cache
			// egw.data system runs encapsulated below etemplate, so this must be
			// done before the nextmatch is created.
			this.egw.dataCacheRegister('mail',
				// Called to determine cache key
				this.nm_cache,
				// Called whenever cache is used
				// TODO: Change this as needed
				function(server_query)
				{
					// Unlock tree if using a cache, since the server won't
					if(!server_query) this.unlock_tree();
				},
				this
			);
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		// Unbind from nm refresh
		if(this.et2 != null)
		{
			var nm = this.et2.getWidgetById(this.nm_index);
			if(nm != null)
			{
				jQuery(nm).off('refresh');
			}
		}

		// Unregister client side cache
		this.egw.dataCacheUnregister('mail');

		delete this.et2_obj;
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * Dynamic disable NM autorefresh on get_rows response depending on push support of imap-server
	 *
	 * @param {bool} _disable
	 */
	disable_autorefresh: function(_disable)
	{
		if (this.checkET2())
		{
			this.et2.getWidgetById('nm').set_disable_autorefresh(_disable);
		}
	},

	/**
	 * check and try to reinitialize et2 of module
	 */
	checkET2: function()
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
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 * @param {string} _name template name
	 */
	et2_ready: function(et2, _name)
	{
		// call parent; somehow this function is called more often. (twice on a display and compose) why?
		this._super.apply(this, arguments);
		this.et2_obj = et2;
		this.push_active = {};
		switch (_name)
		{
			case 'mail.sieve.vacation':
				this.vacationFilterStatusChange();
				break;
			case 'mail.index':
				var self = this;
				jQuery('iframe#mail-index_messageIFRAME').on('load', function ()
				{
					// decrypt preview body if mailvelope is available
					self.mailvelopeAvailable(self.mailvelopeDisplay);
					self.mail_prepare_print();
				});
				var nm = this.et2.getWidgetById(this.nm_index);
				this.mail_isMainWindow = true;

				// Stop list from focussing next row on keypress
				let aom = egw_getObjectManager('mail').getObjectById('nm');
				aom.flags = egwSetBit(aom.flags, EGW_AO_FLAG_DEFAULT_FOCUS, false);

				// Set preview pane state
				this.mail_disablePreviewArea(!this.getPreviewPaneState());

				//Get initial folder status
				this.mail_refreshFolderStatus(undefined, undefined, false);

				// Bind to nextmatch refresh to update folder status
				if (nm != null && (typeof jQuery._data(nm).events == 'undefined' || typeof jQuery._data(nm).events.refresh == 'undefined'))
				{
					var self = this;
					jQuery(nm).on('refresh', (_event, _widget, _row_id, _type) =>
					{
						if (!self.push_active[_widget.settings.foldertree.split("::")[0]])
						{
							// defer calls to mail_refreshFolderStatus for 2s, to accumulate updates of multiple rows e.g. deleting multiple emails
							if (typeof self.refresh_timeout === 'undefined')
							{
								self.refresh_timeout = window.setTimeout(() =>
								{
									delete self.refresh_timeout;
									self.mail_refreshFolderStatus.call(self, undefined, undefined, false);
								}, 2000);
							}
						}
					});
				}
				var tree_wdg = this.et2.getWidgetById(this.nm_index+'[foldertree]');
				if (tree_wdg)
				{
					tree_wdg.set_onopenstart(jQuery.proxy(this.openstart_tree, this));
					tree_wdg.set_onopenend(jQuery.proxy(this.openend_tree, this));
				}
				// Show vacation notice on load for the current profile (if not called by mail_searchtype_change())
				var alreadyrefreshed = this.mail_searchtype_change();
				if (!alreadyrefreshed) this.mail_callRefreshVacationNotice();
				if (!egwIsMobile())
				{
					let splitter = this.et2.getWidgetById('mailSplitter');
					let composeBtn = this.et2.getWidgetById('button[mailcreate]');
					let composeBtnLabel = composeBtn.label;
					if (splitter && !splitter.vertical)
					{
						splitter.addEventListener('sl-reposition', function(){
							if (this.position < 44)
							{
								this.classList.add('limitted');
								if (this.position < 38)
								{
									this.classList.add('squeezed');
									composeBtn.label = '';
								}
								else
								{
									this.classList.remove('squeezed');
								}
							}
							else
							{
								this.classList.remove('limitted');
								this.classList.remove('squeezed');
								composeBtn.label = composeBtnLabel;
							}
						});
					}
				}
				break;
			case 'mail.display':
				var self = this;
				// Prepare display dialog for printing
				// copies iframe content to a DIV, as iframe causes
				// trouble for multipage printing

				jQuery('iframe#mail-display_mailDisplayBodySrc').on('load', function(e)
				{
					// encrypt body if mailvelope is available
					self.mailvelopeAvailable(self.mailvelopeDisplay);
					self.mail_prepare_print();
					self.resolveExternalImages(this.contentWindow.document);
					// Trigger print command if the mail oppend for printing porpuse
					// load event fires twice in IE and the first time the content is not ready
					// Check if the iframe content is loaded then trigger the print command
					if (window.location.search.search('&print=') >= 0 && jQuery(this.contentWindow.document.body).children().length >0 )
					{
						self.mail_print();
					}
				});

				this.mail_isMainWindow = false;
				this.mail_display();

				// Register attachments for drag
				this.register_for_drag(
					this.et2.getArrayMgr("content").getEntry('mail_id'),
					this.et2.getArrayMgr("content").getEntry('mail_displayattachments')
				);
				this.smimeAttachmentsCheckerInterval();
				break;
			case 'mail.compose':
				var composeToolbar = this.et2.getWidgetById('composeToolbar');
				if (composeToolbar._actionManager.getActionById('pgp') &&
					composeToolbar._actionManager.getActionById('pgp').checked ||
					this.et2.getArrayMgr('content').data.mail_plaintext &&
						this.et2.getArrayMgr('content').data.mail_plaintext.indexOf(this.begin_pgp_message) != -1)
				{
					this.mailvelopeAvailable(this.mailvelopeCompose);
				}
				var that = this;
				var plainText = this.et2.getWidgetById('mail_plaintext');
				var textAreaWidget = this.et2.getWidgetById('mail_htmltext');
				this.mail_isMainWindow = false;
				var pca = egw.preference(this.et2.getWidgetById('mailaccount').getValue().split(":")[0]+'_predefined_compose_addresses', 'mail');
				for (var p in pca)
				{
					if (this.et2.getWidgetById(p).getValue() && pca[p])
					{
						pca[p] = pca[p].concat(this.et2.getWidgetById(p).getValue());
					}
					this.et2.getWidgetById(p).set_value(pca[p]);
				}
				this.compose_fieldExpander_init();
				this.check_sharing_filemode();

				this.subject2title();

				// Set autosaving interval to 2 minutes for compose message
				this.W_INTERVALS.push(window.setInterval(function (){
					if (jQuery('.ms-editor-wrap').length === 0)
					{
						that.saveAsDraft(null, 'autosaving');
					}
				}, 120000));

				/* Control focus actions on subject to handle expanders properly.*/
				jQuery("#mail-compose_subject").on({
					focus:function(){
						that.compose_fieldExpander_init();
						that.compose_fieldExpander();
					}
				});
				/*Trigger compose_resizeHandler after the TinyMCE is fully loaded*/
				jQuery('#mail-compose').on ('load',function() {

					if (textAreaWidget && textAreaWidget.tinymce)
					{
						textAreaWidget.tinymce.then(()=>{
							that.compose_resizeHandler();
							if (textAreaWidget.editor) jQuery(textAreaWidget.editor.iframeElement.contentWindow.document).on('dragenter', function(){
							// anything to bind on tinymce iframe
							});
						});
					}
					else
					{
						that.compose_fieldExpander();
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
					that.compose_resizeHandler();
				});
				// Init key handler
				this.init_keyHandler();

				// Set focus on To/body field
				// depending on To field value
				var to = this.et2.getWidgetById('to');
				var content = this.et2.getArrayMgr('content').data;
				if (to && to.get_value() && to.get_value().length > 0)
				{
					if (content.is_plain)
					{
						// focus
						jQuery(plainText.getDOMNode()).focus();
						// get the cursor to the top of the textarea
						if (typeof plainText.getDOMNode().setSelectionRange !='undefined' && !jQuery(plainText.getDOMNode()).is(":hidden"))
						{
							setTimeout(function(){
								plainText.getDOMNode().setSelectionRange(0,0)
								plainText.focus();
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
				var smime_sign = this.et2.getWidgetById('smime_sign');
				var smime_encrypt = this.et2.getWidgetById('smime_encrypt');

				if (composeToolbar._actionManager.getActionById('smime_sign') &&
						composeToolbar._actionManager.getActionById('smime_encrypt'))
				{
					if (smime_sign.getValue() == 'on') composeToolbar.checkbox('smime_sign', true);
					if (smime_encrypt.getValue() == 'on') composeToolbar.checkbox('smime_encrypt', true);
				}
				break;
			case 'mail.subscribe':
				if (this.subscription_treeLastState != "")
				{
					var tree = this.et2.getWidgetById('foldertree');
					//Saved state of tree
					var state = jQuery.parseJSON(this.subscription_treeLastState);

					tree.input.loadJSONObject(tree._htmlencode_node(state));
				}
				break;
			case 'mail.folder_management':
				this.egw.message(this.egw.lang('If you would like to select multiple folders in one action, you can hold ctrl key then select a folder as start range and another folder within a same level as end range, all folders in between will be selected or unselected based on their current status.'),'info','mail:folder_management');
				break;
			case 'mail.view':
				// we need to set mail_currentlyFocused var otherwise mail
				// defined actions won't work
				this.mail_currentlyFocussed = this.et2.mail_currentlyFocussed;

		}
		// set image_proxy for resolveExternalImages
		this.image_proxy = this.et2.getArrayMgr('content').getEntry('image_proxy') || 'https://';

		this.preSetToggledOnActions ();
	},

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
	push: function(pushData)
	{
		// don't care about other apps data, reimplement if your app does care eg. calendar
		if (pushData.app !== this.appname) return;

		let id0 = typeof pushData.id === 'string' ? pushData.id : pushData.id[0];
		let acc_id = id0.split('::')[1];
		let folder = acc_id+'::'+atob(id0.split('::')[2]);
		let foldertree = this.et2 ? this.et2.getWidgetById('nm[foldertree]') : null;
		this.push_active[acc_id] = true;

		// update unseen counter in folder-tree (also for delete)
		if (foldertree && pushData.acl.folder && typeof pushData.acl.unseen !== 'undefined')
		{
			let folder_id = {};
			folder_id[folder] = (foldertree.getLabel(folder) || pushData.acl.folder)
				.replace(this._unseen_regexp, '')+
				(pushData.acl.unseen ? " ("+pushData.acl.unseen+")" : '');
			this.mail_setFolderStatus(folder_id);
		}

		// only handle delete by default, for simple case of uid === "$app::$id"
		if (pushData.type === 'delete')
		{
			[].concat(pushData.id).forEach(uid => {
				pushData.id = uid;
				this._super.call(this, pushData);
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
				case 'FlagsSet':
					// TB (probably other MUA too) mark mail as deleted, our UI removes/expunges it immediatly
					if (pushData.acl.flags.includes('\\Deleted'))
					{
						pushData.type = 'delete';
						return this._super.call(this, pushData);
					}
					this.pushUpdateFlags(pushData);
					break;
				case 'FlagsClear':
					this.pushUpdateFlags(pushData);
					break;
				default:
					// Just update the nm (todo: pushData.message = total number of messages in folder)
					nm.refresh(pushData.id, pushData.type === 'update' ? 'update-in-place' : pushData.type, pushData.messages);
			}
		}
	},

	/**
	 * Check if user want's new mail notification
	 *
	 * @param pushData
	 */
	notifyNew: function(pushData)
	{
		let framework = egw_getFramework();
		let notify = this.egw.preference('new_mail_notification', 'mail');
		if (typeof notify === 'undefined' || notify === 'always' ||
			notify === 'not-mail' && framework && framework.activeApp.appName !== 'mail')
		{
			this.egw.message(egw.lang('New mail from %1', pushData.acl.from)+'\n'+pushData.acl.subject+'\n'+pushData.acl.snippet, 'success');
		}
	},

	/**
	 * Updates flags on respective rows
	 *
	 * @param {type} pushData
	  */
	pushUpdateFlags: function(pushData)
	{
		let flag = pushData.acl.flags[0] || pushData.acl.keywords[0];
		let unset = (pushData.acl.flags_old && pushData.acl.flags_old.indexOf(pushData.acl.flags[0]) > -1)
				|| (pushData.acl.keywords_old && pushData.acl.keywords_old.indexOf(pushData.acl.keywords[0]) > -1) ? true : false;
		let rowClass = '';
		if (flag[0] == '\\' || flag[0] == '$') flag = flag.slice(1).toLowerCase();
		let ids = typeof pushData.id == "string" ? [pushData.id] : pushData.id;
		for (let i in ids)
		{
			let msg = {msg:['mail::'+ids[i]]};
			switch(flag)
			{
				case 'seen':
						this.mail_removeRowClass(msg, (unset) ? 'seen' : 'unseen');
						rowClass = (unset) ? 'unseen' : 'seen';
					break;
				case 'label1':
				case 'label2':
				case 'label3':
				case 'label4':
				case 'label5':
				case 'flagged':
					if (unset)
					{
						this.mail_removeRowClass(msg, flag);
					}
					else
					{
						rowClass = flag;
					}
					break;
			}
			this.mail_setRowClass(msg, rowClass);
		}
	},

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
	observer: function(_msg, _app, _id, _type, _msg_type, _links)
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
							contentWindow.app.mail.sieve_refresh();
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
							//tree.refreshItem(_id);
							egw.json('mail.mail_ui.ajax_reloadNode',[_id])
								.sendRequest(true);
						}
						break;
					case 'add':
						const current_id = tree.getValue();
						tree.refreshItem(0);	// refresh root
						// ToDo: tree.refreshItem() and openItem() should return a promise
						// need to wait tree is refreshed: current and new id are there AND current folder is selected again
						const interval = window.setInterval(() => {
							if (tree.getNode(_id) && tree.getNode(current_id))
							{
								if (!tree.getSelectedNode())
								{
									tree.reSelectItem(current_id);
								}
								else
								{
									window.clearInterval(interval);
									// open new account
									tree.openItem(_id, true);
									// need to wait new folders are loaded AND current folder is selected again
									const open_interval = window.setInterval(() => {
										if (tree.getNode(_id + '::INBOX')) {
											if (!tree.getSelectedNode()) {
												tree.reSelectItem(current_id);
											} else {
												window.clearInterval(open_interval);
												this.mail_changeFolder(_id + '::INBOX', tree, current_id);
												tree.reSelectItem(_id + '::INBOX');
											}
										}
									}, 200);
								}
							}
						}, 200);
						break;
					default: // null
				}
		}
		return undefined;
	},

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
	nm_cache: function(query_context)
	{
		// Only cache first chunk of rows, if no search filter
		if((!query_context || !query_context.start) && query_context.count == 0 &&
			query_context.filters && query_context.filters.selectedFolder &&
			!(!query_context.filters || query_context.filters.search)
		)
		{
			// Make sure keys match, even if some filters are not defined
			// using JSON.stringfy() directly gave a crash in Safari 7.0.4
			return this.egw.jsonEncode({
				selectedFolder: query_context.filters.selectedFolder || '',
				cat_id: query_context.filters.cat_id || '',
				filter: query_context.filters.filter || '',
				filter2: query_context.filters.filter2 || '',
				sort: query_context.filters.sort
			});
		}
		return false;
	},

	/**
	 * mail rebuild Action menu On nm-list
	 *
	 * @param _actions
	 */
	mail_rebuildActionsOnList: function(_actions)
	{
		this.et2.getWidgetById(this.nm_index).set_actions(_actions);
	},

	/**
	 * mail_fetchCurrentlyFocussed - implementation to decide wich mail of all the selected ones is the current
	 *
	 * @param _selected array of the selected mails
	 * @param _reset bool - tell the function to reset the global vars used
	 */
	mail_fetchCurrentlyFocussed: function(_selected, _reset) {
		// reinitialize the buffer-info on selected mails
		if (_reset == true || typeof _selected == 'undefined')
		{
			if (_reset == true)
			{
				// Request updated data, if possible
				if (this.mail_currentlyFocussed!='') egw.dataRefreshUID(this.mail_currentlyFocussed);
				for(var k = 0; k < this.mail_selectedMails.length; k++) egw.dataRefreshUID(this.mail_selectedMails[k]);
				//nm.refresh(this.mail_selectedMails,'delete');
			}
			this.mail_selectedMails = [];
			this.mail_currentlyFocussed = '';
			return '';
		}
		for(var k = 0; k < _selected.length; k++)
		{
			if (jQuery.inArray(_selected[k],this.mail_selectedMails)==-1)
			{
				this.mail_currentlyFocussed = _selected[k];
				break;
			}
		}
		this.mail_selectedMails = _selected;
		return this.mail_currentlyFocussed;
	},

	/**
	 * mail_open - implementation of the open action
	 *
	 * @param _action
	 * @param _senders - the representation of the elements(s) the action is to be performed on
	 * @param _mode - you may pass the mode. if not given view is used (tryastext|tryashtml are supported)
	 */
	mail_open: function(_action, _senders, _mode) {
		if (typeof _senders == 'undefined' || _senders.length==0)
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _senders = [];
				_senders.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _senders == 'undefined' || _senders.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _senders = [];
					_senders.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		var _id = _senders[0].id;
		// reinitialize the buffer-info on selected mails
		if (!(_mode == 'tryastext' || _mode == 'tryashtml' || _mode == 'view' || _mode == 'print')) _mode = 'view';
		this.mail_selectedMails = [];
		this.mail_selectedMails.push(_id);
		this.mail_currentlyFocussed = _id;

		var dataElem = egw.dataGetUIDdata(_id);
		var subject = dataElem.data.subject;
		//alert('Open Message:'+_id+' '+subject);
		var h = egw().open( _id,'mail','view',_mode+'='+_id.replace(/=/g,"_")+'&mode='+_mode);
		egw(h).ready(function() {
			h.document.title = subject;
		});
		// THE FOLLOWING IS PROBABLY NOT NEEDED, AS THE UNEVITABLE PREVIEW IS HANDLING THE COUNTER ISSUE
		var messages = {};
		messages['msg'] = [_id];
		// When body is requested, mail is marked as read by the mail server.  Update UI to match.
		if (typeof dataElem != 'undefined' && typeof dataElem.data != 'undefined' && typeof dataElem.data.flags != 'undefined' && typeof dataElem.data.flags.read != 'undefined') dataElem.data.flags.read = 'read';
		if (typeof dataElem != 'undefined' && typeof dataElem.data != 'undefined' && typeof dataElem.data['class'] != 'undefined' && (dataElem.data['class'].indexOf('unseen') >= 0 || dataElem.data['class'].indexOf('recent') >= 0))
		{
			this.mail_removeRowClass(messages,'recent');
			this.mail_removeRowClass(messages,'unseen');
			// reduce counter without server roundtrip
			this.mail_reduceCounterWithoutServerRoundtrip();
			// not needed, as an explizit read flags the message as seen anyhow
			//egw.jsonq('mail.mail_ui.ajax_flagMessages',['read', messages, false]);
		}
	},

	/**
	 * Open a single message in html mode
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_openAsHtml: function(_action, _elems)
	{
		this.mail_open(_action, _elems,'tryashtml');
	},

	/**
	 * Open a single message in plain text mode
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_openAsText: function(_action, _elems)
	{
		this.mail_open(_action, _elems,'tryastext');
	},

	/**
	 * Compose, reply or forward a message
	 *
	 * @function
	 * @memberOf mail
	 * @param _action _action.id is 'compose', 'composeasnew', 'reply', 'reply_all' or 'forward' (forward can be multiple messages)
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_compose: function(_action, _elems)
	{
		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			if (this.et2 && this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		// Extra info passed to egw.open()
		var settings = {
			// 'Source' Mail UID
			id: '',
			// How to pull data from the Mail IDs for the compose
			from: ''
		};

		// We only handle one for everything but forward
		settings.id = (typeof _elems == 'undefined'?'':_elems[0].id);
		var content = egw.dataGetUIDdata(settings.id);
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
					return this.mail_compose('forward',_elems);
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
					return egw.openWithinWindow("mail", "setCompose", {data:{emails:{ids:settings.id, processedmail_id:settings.id}}}, settings, /mail.mail_compose.compose/);
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
	},

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
	setCompose: function(compose, content)
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
						var filemode_label = filemode.options.select_options[content[field]['files']['filemode']]['label'];
						Et2Dialog.show_dialog(function (_button)
							{
								if (_button == Et2Dialog.YES_BUTTON)
								{
									compose_et2[0].widgetContainer._inst.submit();
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
		if (content['cc'] || content['bcc'])
		{
			this.compose_fieldExpander();
			this.compose_fieldExpander_init();
		}
		return success;
	},

	/**
	 * mail_disablePreviewArea - implementation of the disablePreviewArea action
	 *
	 * @param _value
	 */
	mail_disablePreviewArea: function(_value) {
		var splitter = this.et2.getWidgetById('mailSplitter');
		var previewPane = this.egw.preference('previewPane', 'mail');
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
	},

	/**
	 * Set values for mail dispaly From,Sender,To,Cc, and Bcc
	 * Additionally, apply expand on click feature on thier widgets
	 *
	 */
	mail_display: function()
	{
		var dataElem = {data:{FROM:"",SENDER:"",TO:"",CC:"",BCC:""}};
		var content = this.et2.getArrayMgr('content').data;

		if (typeof  content != 'undefiend')
		{
			dataElem.data = jQuery.extend(dataElem.data, content);

			var toolbaractions = ((typeof dataElem != 'undefined' && typeof dataElem.data != 'undefined' && typeof dataElem.data.displayToolbaractions != 'undefined')?JSON.parse(dataElem.data.displayToolbaractions):undefined);
			if (toolbaractions) this.et2.getWidgetById('displayToolbar').set_actions(toolbaractions);
		}
	},

	/**
	 * Handle actions from attachments block
	 * @param _e
	 * @param _widget
	 */
	attachmentsBlockActions: function(_e, _widget)
	{
		const id = _widget.id.replace('[actions]','');
		const action = _widget.value;
		_widget.label = _widget.select_options.filter(_item=>{return _item.value == _widget.value})[0].label;
		this.saveAttachmentHandler(_widget,action, id);
	},

	/**
	 * mail_preview - implementation of the preview action
	 *
	 * @param nextmatch et2_nextmatch The widget whose row was selected
	 * @param selected Array Selected row IDs.  May be empty if user unselected all rows.
	 */
	mail_preview: function(selected, nextmatch) {
		let data = {};
		let rowId = '';
		let sel_options = {}
		let attachmentsBlock = this.et2.getWidgetById('attachmentsBlock');
		let mailPreview = this.et2.getWidgetById('mailPreview');
		if(typeof selected != 'undefined' && selected.length == 1)
		{
			rowId = this.mail_fetchCurrentlyFocussed(selected);
			data = egw.dataGetUIDdata(rowId).data;

			// Try to resolve winmail.data attachment
			if (data && data.attachmentsBlock[0]
					&& data.attachmentsBlock[0].winmailFlag
					&& (data.attachmentsBlock[0].mimetype =='application/ms-tnef' ||
					data.attachmentsBlock[0].filename == "winmail.dat"))
			{
				attachmentsBlock.getDOMNode().classList.add('loading');
				this.egw.jsonq('mail.mail_ui.ajax_resolveWinmail',[rowId], jQuery.proxy(function(_data){
					attachmentsBlock.getDOMNode().classList.remove('loading');
					if (typeof _data == 'object')
					{
						data.attachmentsBlock = _data;
						data.attachmentsBlockTitle = _data.length > 1 ? `+${_data.length-1}` : '';
						// Update client cache to avoid resolving winmail.dat attachment again
						egw.dataStoreUID(data.uid, data);
						if (!egwIsMobile() && mailPreview) mailPreview.set_value({content:data});
					}
					else
					{
						console.log('Can not resolve the winmail.data!');
					}
				},data));
			}
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
			const actions = [
				{
					id: 'downloadOneAsFile',
					label: 'Download',
					icon: 'fileexport',
					value: 'downloadOneAsFile'
				},
				{
					id: 'saveOneToVfs',
					label: 'Save in Filemanager',
					icon: 'filemanager/navbar',
					value: 'saveOneToVfs'
				},
				{
					id: 'saveAllToVfs',
					label: 'Save all to Filemanager',
					icon: 'mail/save_all',
					value: 'saveAllToVfs'
				},
				{
					id: 'downloadAllToZip',
					label: 'Save as ZIP',
					icon: 'mail/save_zip',
					value: 'downloadAllToZip'
				}
			];
			const collabora = {
				id: 'collabora',
				label: 'Collabora',
				icon: 'collabora/navbar',
				value: 'collabora'
			};
			data.attachmentsBlockTitle = data.attachmentsBlock.length > 1 ? `+${data.attachmentsBlock.length-1}` : '';
			sel_options.attachmentsBlock = {};
			data.attachmentsBlock.forEach(_item =>
			{
				_item.actions = 'downloadOneAsFile';
				// for some reason label needs to be set explicitly for the dropdown button. It needs more investigation.
				_item.actionsDefaultLabel = 'Download';

				if (typeof this.egw.user('apps')['collabora'] !== "undefined" && this.egw.isCollaborable(_item.type))
				{
					// Start with download on top, Collabora on bottom
					sel_options.attachmentsBlock[_item.attachment_number + "[actions]"] = [...actions, collabora];

					if (egw.preference('document_doubleclick_action', 'filemanager') === 'collabora')
					{
						_item.actions = 'collabora';
						_item.actionsDefaultLabel = 'Collabora';
						// Put Collabora on top
						sel_options.attachmentsBlock[_item.attachment_number + "[actions]"] = [collabora, ...actions];
					}
				}
			});

			sel_options.attachmentsBlock.actions = actions;
		}

		if (!egwIsMobile() && mailPreview) mailPreview.set_value({content:data, sel_options:sel_options});

		if (selected && selected.length>1)
		{
			// Leave if we're here and there is nothing selected, too many, or no data
			if (attachmentsBlock)
			{
				// check if the widget is attached before setting its content
				if (attachmentsBlock.parentNode)
				{
					attachmentsBlock.set_value({content:[]});
					attachmentsBlock.set_class('previewAttachmentArea noContent mail_DisplayNone');
				}
				var IframeHandle = this.et2.getWidgetById('messageIFRAME');
				if(IframeHandle) IframeHandle.set_src('about:blank');
				this.mail_disablePreviewArea(true);
			}
			if (!egwIsMobile())return;
		}

		// Not applied to mobile preview
		if (!egwIsMobile() && this.getPreviewPaneState())
		{
			// Blank first, so we don't show previous email while loading
			var IframeHandle = this.et2.getWidgetById('messageIFRAME');
			IframeHandle.set_src('about:blank');

			this.smime_clear_flags([this.et2.getWidgetById('mailPreviewContainer').getDOMNode()]);

			// show iframe, in case we hide it from mailvelopes one and remove that
			jQuery(IframeHandle.getDOMNode()).show()
				.next(this.mailvelope_iframe_selector).remove();

			// need to have the DOM ready for calculation.
			this.mail_disablePreviewArea(false);

			// Update the internal list of selected mails, if needed
			if(this.mail_selectedMails.indexOf(rowId) < 0)
			{
				this.mail_selectedMails.push(rowId);
			}
			var self = this;

			// Try to avoid sending so many request when user tries to scroll on list
			// via key up/down quite fast.
			for (var t in this.W_TIMEOUTS) {window.clearTimeout(this.W_TIMEOUTS[t]);}
			this.W_TIMEOUTS.push(window.setTimeout(function(){

				console.log(rowId);
				// Request email body from server
				IframeHandle.set_src(egw.link('/index.php',{menuaction:'mail.mail_ui.loadEmailBody',_messageID:rowId}));
				jQuery(IframeHandle.getDOMNode()).on('load', function(e){
					self.resolveExternalImages (this.contentWindow.document);
				});
			}, 300));
		}
		if (data['smime']) this.smimeAttachmentsCheckerInterval();
		var messages = {};
		messages['msg'] = [rowId];

		// When body is requested, mail is marked as read by the mail server.  Update UI to match.
		if (typeof data != 'undefined' && typeof data != 'undefined' && typeof data.flags != 'undefined' && typeof data.flags.read != 'undefined') data.flags.read = 'read';
		if (typeof data != 'undefined' && typeof data != 'undefined' && typeof data['class']  != 'undefined' && (data['class'].indexOf('unseen') >= 0 || data['class'].indexOf('recent') >= 0))
		{
			this.mail_removeRowClass(messages,'recent');
			this.mail_removeRowClass(messages,'unseen');
			// reduce counter without server roundtrip
			this.mail_reduceCounterWithoutServerRoundtrip();
			if (typeof data.dispositionnotificationto != 'undefined' && data.dispositionnotificationto &&
				typeof data.flags.mdnsent == 'undefined' && typeof data.flags.mdnnotsent == 'undefined')
			{
				var buttons = [
					{label: this.egw.lang("Yes"), id: "mdnsent", image: "check"},
					{label: this.egw.lang("No"), id: "mdnnotsent", image: "cancelled"}
				];
				Et2Dialog.show_dialog(function (_button_id, _value)
					{
						switch (_button_id)
						{
							case "mdnsent":
								egw.jsonq('mail.mail_ui.ajax_sendMDN', [messages]);
								egw.jsonq('mail.mail_ui.ajax_flagMessages', ['mdnsent', messages, true]);
								return;
							case "mdnnotsent":
								egw.jsonq('mail.mail_ui.ajax_flagMessages', ['mdnnotsent', messages, true]);
						}
					},
				this.egw.lang("The message sender has requested a response to indicate that you have read this message. Would you like to send a receipt?"),
				this.egw.lang("Confirm"),
				messages, buttons);
			}
			egw.jsonq('mail.mail_ui.ajax_flagMessages',['read', messages, false]);
		}
	},

	resolveExternalImages: function (_node)
	{
		let image_proxy = this.image_proxy;
		//Do not run resolve images if it's forced already to show them all
		// or forced to not show them all.
		var pref_img = egw.preference('allowExternalIMGs', 'mail');
		if (pref_img == 0) return;

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
					.click(function(){
						showImages(external_images);
						container.remove();
					})
					.appendTo(container);
			container.appendTo(_node.body? _node.body:_node);
		}
	},

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
	showAllHeader: function(event,widget,button) {
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
	},

	mail_setMailBody: function(content) {
		var IframeHandle = this.et2.getWidgetById('messageIFRAME');
		IframeHandle.set_value('');
	},

	/**
	 * mail_refreshFolderStatus, function to call to read the counters of a folder and apply them
	 *
	 * @param {stirng} _nodeID
	 * @param {string} mode
	 * @param {boolean} _refreshGridArea
	 * @param {boolean} _refreshQuotaDisplay
	 *
	 */
	mail_refreshFolderStatus: function(_nodeID,mode,_refreshGridArea,_refreshQuotaDisplay) {
		if (typeof _nodeID != 'undefined' && typeof _nodeID[_nodeID] != 'undefined' && _nodeID[_nodeID])
		{
			_refreshGridArea = _nodeID[_refreshGridArea];
			mode = _nodeID[mode];
			_nodeID = _nodeID[_nodeID];
		}
		var nodeToRefresh = 0;
		var mode2use = "none";
		if (typeof _refreshGridArea == 'undefined') _refreshGridArea=true;
		if (typeof _refreshQuotaDisplay == 'undefined') _refreshQuotaDisplay=true;
		if (_nodeID) nodeToRefresh = _nodeID;
		if (mode) {
			if (mode == "forced") {mode2use = mode;}
		}
		try
		{
			var tree_wdg = this.et2.getWidgetById(this.nm_index+'[foldertree]');

			var activeFolders = tree_wdg.getTreeNodeOpenItems(nodeToRefresh,mode2use);
			//alert(activeFolders.join('#,#'));
			this.mail_queueRefreshFolderList((mode=='thisfolderonly'&&nodeToRefresh?[_nodeID]:activeFolders));
			if (_refreshGridArea)
			{
				// maybe to use the mode forced as trigger for grid reload and using the grids own autorefresh
				// would solve the refresh issue more accurately
				//if (mode == "forced") this.mail_refreshMessageGrid();
				this.mail_refreshMessageGrid();
			}
			if (_refreshQuotaDisplay)
			{
				this.mail_refreshQuotaDisplay();
			}
			//the two lines below are not working yet.
			//var no =tree_wdg.getSelectedNode();
			//tree_wdg.focusItem(no.id);
		} catch(e) { } // ignore the error; maybe the template is not loaded yet
	},

	/**
	 * mail_refreshQuotaDisplay, function to call to read the quota for the active server
	 *
	 * @param {object} _server
	 *
	 */
	mail_refreshQuotaDisplay: function(_server)
	{
		egw.json('mail.mail_ui.ajax_refreshQuotaDisplay',[_server])
			.sendRequest(true);
	},

	/**
	 * mail_setQuotaDisplay, function to call to read the quota for the active server
	 *
	 * @param {object} _data
	 *
	 */
	mail_setQuotaDisplay: function(_data)
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
					{label: this.egw.lang("Cancel"), id: "cancel"}
				];
				var server = [{iface:{id: _data.data.profileid+'::'}}];
				Et2Dialog.show_dialog(function (_button_id)
					{
						if (_button_id == "cleanup")
						{
							self.mail_emptySpam(null, server);
							self.mail_emptyTrash(null, server);
						}
						return;
					},
					this.egw.lang("Your remaining quota %1 is too low, you may not be able to send/receive further emails.\n Although cleaning up emails in trash or junk folder might help you to get some free space back.\n If that didn't help, please ask your administrator for more quota.", _data.data.quotafreespace),
					this.egw.lang("Mail cleanup"),
					'', buttons, Et2Dialog.WARNING_MESSAGE);
			}
		}
	},

	/**
	 * mail_callRefreshVacationNotice, function to call the serverside function to refresh the vacationnotice for the active server
	 *
	 * @param {object} _server
	 *
	 */
	mail_callRefreshVacationNotice: function(_server)
	{
		egw.jsonq('mail_ui::ajax_refreshVacationNotice',[_server]);
	},
	/**
	 * Make sure attachments have all needed data, so they can be found for
	 * HTML5 native dragging
	 *
	 * @param {string} mail_id Mail UID
	 * @param {array} attachments Attachment information.
	 */
	register_for_drag: function(mail_id, attachments)
	{
		// Put required info in global store
		var data = {};
		if (!attachments) return;
		for (var i = 0; i < attachments.length; i++)
		{
			var data = attachments[i] || {};
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
	},

	/**
	 * Display helper for dragging attachments
	 *
	 * @param {egwAction} _action
	 * @param {egwActionElement[]} _elems
	 * @returns {DOMNode}
	 */
	drag_attachment: function(_action, _elems)
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
	},

	/**
	 * mail_refreshVacationNotice, function to call with appropriate data to refresh the vacationnotice for the active server
	 *
	 * @param {object} _data
	 *
	 */
	mail_refreshVacationNotice: function(_data)
	{
		if (!this.et2 && !this.checkET2()) return;
		if (_data == null)
		{
			this.et2.getWidgetById('mail.index.vacationnotice').set_disabled(true);
			this.et2.getWidgetById(this.nm_index+'[vacationnotice]').set_value('');
			this.et2.getWidgetById(this.nm_index+'[vacationrange]').set_value('');
		}
		else
		{
			this.et2.getWidgetById('mail.index.vacationnotice').set_disabled(false);
			this.et2.getWidgetById(this.nm_index+'[vacationnotice]').set_value(_data.vacationnotice);
			this.et2.getWidgetById(this.nm_index+'[vacationrange]').set_value(_data.vacationrange);
		}
	},

	/**
	 * Enable or disable the date filter
	 *
	 * If the searchtype (cat_id) is set to something that needs dates, we enable the
	 * header_right template.  Otherwise, it is disabled.
	 */
	mail_searchtype_change: function()
	{
		var filter = this.et2.getWidgetById('cat_id');
		var nm = this.et2.getWidgetById(this.nm_index);
		var dates = this.et2.getWidgetById('mail.index.datefilter');
		if(nm && filter)
		{
			switch(filter.getValue())
			{
				case 'bydate':

					if (filter && dates)
					{
						dates.set_disabled(false);
						if (this.et2.getWidgetById('startdate')) jQuery(this.et2.getWidgetById('startdate').getDOMNode()).find('input').focus();
					}
					this.mail_callRefreshVacationNotice();
					return true;
				default:
					if (dates)
					{
						dates.set_disabled(true);
					}
					this.mail_callRefreshVacationNotice();
					return true;
			}
		}
		return false;
	},

	/**
	 * mail_refreshFilter2Options, function to call with appropriate data to refresh the filter2 options for the active server
	 *
	 * @param {object} _data
	 *
	 */
	mail_refreshFilter2Options: function(_data)
	{
		//alert('mail_refreshFilter2Options');
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
	},

	/**
	 * mail_refreshFilterOptions, function to call with appropriate data to refresh the filter options for the active server
	 *
	 * @param {object} _data
	 *
	 */
	mail_refreshFilterOptions: function(_data)
	{
		//alert('mail_refreshFilterOptions');
		if (_data == null) return;
		if (!this.et2 && !this.checkET2()) return;

		var filter = this.et2.getWidgetById('filter');
		var current = filter.value;
		var currentexists=false;
		for (var k in _data)
		{
			if (k==current) currentexists=true;
		}
		if (!currentexists) filter.set_value('any');
		filter.set_select_options(_data);

	},

	/**
	 * mail_refreshCatIdOptions, function to call with appropriate data to refresh the filter options for the active server
	 *
	 * @param {object} _data
	 *
	 */
	mail_refreshCatIdOptions: function(_data)
	{
		//alert('mail_refreshCatIdOptions');
		if (_data == null) return;
		if (!this.et2 && !this.checkET2()) return;

		var filter = this.et2.getWidgetById('cat_id');
		var current = filter.value;
		var currentexists=false;
		for (var k in _data)
		{
			if (k==current) currentexists=true;
		}
		if (!currentexists) filter.set_value('quick');
		filter.set_select_options(_data);

	},

	/**
	 * Queues a refreshFolderList request for 500ms. Actually this will just execute the
	 * code after the calling script has finished.
	 *
	 * @param {array} _folders description
	 */
	mail_queueRefreshFolderList: function(_folders)
	{
		var self = this;
		// as jsonq is too fast wrap it to be delayed a bit, to ensure the folder actions
		// are executed last of the queue
		window.setTimeout(function() {
			egw.jsonq('mail.mail_ui.ajax_setFolderStatus',[_folders], function (){self.unlock_tree();});
		}, 500);
	},

	/**
	 * mail_CheckFolderNoSelect - implementation of the mail_CheckFolderNoSelect action to control right click options on the tree
	 *
	 * @param {object} action
	 * @param {object} _senders the representation of the tree leaf to be manipulated
	 * @param {object} _currentNode
	 */
	mail_CheckFolderNoSelect: function(action,_senders,_currentNode) {

		// Abort if user selected an un-selectable node
		// Use image over anything else because...?
		var ftree, node;
		ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		if (ftree)
		{
			node = ftree.getNode(_senders[0].id);
		}

		if (node && node.im0.indexOf('NoSelect') !== -1)
		{
			//ftree.reSelectItem(_previous);
			return false;
		}

		return true;
	},

	/**
	 * Check if SpamFolder is enabled on that account
	 *
	 * SpamFolder enabled is stored as data { spamfolder: true/false } on account node.
	 *
	 * @param {object} _action
	 * @param {object} _senders the representation of the tree leaf to be manipulated
	 * @param {object} _currentNode
	 */
	spamfolder_enabled: function(_action,_senders,_currentNode)
	{
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var acc_id = _senders[0].id.split('::')[0];
		var node = ftree ? ftree.getNode(acc_id) : null;

		return node && node.data && node.data.spamfolder;
	},


	/**
	 * Check if archiveFolder is enabled on that account
	 *
	 * ArchiveFolder enabled is stored as data { archivefolder: true/false } on account node.
	 *
	 * @param {object} _action
	 * @param {object} _senders the representation of the tree leaf to be manipulated
	 * @param {object} _currentNode
	 */
	archivefolder_enabled: function(_action,_senders,_currentNode)
	{
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var acc_id = _senders[0].id.split('::')[2]; // this is operating on mails
		var node = ftree ? ftree.getNode(acc_id) : null;

		return node && node.data && node.data.archivefolder;
	},

	/**
	 * Check if Sieve is enabled on that account
	 *
	 * Sieve enabled is stored as data { sieve: true/false } on account node.
	 *
	 * @param {object} _action
	 * @param {object} _senders the representation of the tree leaf to be manipulated
	 * @param {object} _currentNode
	 */
	sieve_enabled: function(_action,_senders,_currentNode)
	{
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var acc_id = _senders[0].id.split('::')[0];
		var node = ftree ? ftree.getNode(acc_id) : null;

		return node && node.data && node.data.sieve;
	},

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
	acl_enabled: function(_action,_senders,_currentNode)
	{
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var inbox = _senders[0].id.split('::')[0]+'::INBOX';
		var node = ftree ? ftree.getNode(inbox) : null;

		return node && node.data && node.data.acl && this.mail_CheckFolderNoSelect(_action,_senders,_currentNode);
	},

	/**
	 * mail_setFolderStatus, function to set the status for the visible folders
	 *
	 * @param {array} _status
	 */
	mail_setFolderStatus: function(_status) {
		if (!this.et2 && !this.checkET2()) return;
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		for (var i in _status) {
			ftree.setLabel(i,_status[i]);
			// display folder-name bold for unseen mails
			ftree.setStyle(i, 'font-weight: '+(_status[i].match(this._unseen_regexp) ? 'bold' : 'normal'));
			//alert(i +'->'+_status[i]);
		}
	},

	/**
	 * mail_setLeaf, function to set the id and description for the folder given by status key
	 * @param {array} _status status array with the required data (new id, desc, old desc)
	 *		key is the original id of the leaf to change
	 *		multiple sets can be passed to mail_setLeaf
	 */
	mail_setLeaf: function(_status) {
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var selectedNode = ftree.getSelectedNode();
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
				nm.activeFilters["selectedFolder"] = _status[i]['id'];
				nm.applyFilters();
			}
		}
	},

	/**
	 * mail_removeLeaf, function to remove the leaf represented by the given ID
	 * @param {array} _status status array with the required data (KEY id, VALUE desc)
	 *		key is the id of the leaf to delete
	 *		multiple sets can be passed to mail_deleteLeaf
	 */
	mail_removeLeaf: function(_status) {
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
				nm.activeFilters["selectedFolder"] = selectedNodeAfter.id;
				nm.applyFilters();
			}
		}
	},

	/**
	 * mail_reloadNode, function to reload the leaf represented by the given ID
	 * @param {Object.<string,string>|Object.<string,Object}}  _status
	 *		Object with the required data (KEY id, VALUE desc), or ID => {new data}
	 */
	mail_reloadNode: function(_status) {
		var ftree = this.et2?this.et2.getWidgetById(this.nm_index+'[foldertree]'):null;
		if (!ftree) return;
		var selectedNode = ftree.getSelectedNode();
		for (var i in _status)
		{
			// if olddesc is undefined or #skip# then skip the message, as we process subfolders
			if (typeof _status[i] !== 'undefined' && _status[i] !== '#skip-user-interaction-message#')
			{
					this.egw.message(this.egw.lang((typeof _status[i].parent !== 'undefined'? "Reloaded Folder %1" : "Reloaded Account %1") ,
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
			nm.activeFilters["selectedFolder"] = selectedNodeAfter.id;
			nm.applyFilters();
		}
	},

	/**
	 * mail_refreshMessageGrid, function to call to reread ofthe current folder
	 *
	 * @param {boolean} _isPopup
	 * @param {boolean} _refreshVacationNotice
	 */
	mail_refreshMessageGrid: function(_isPopup, _refreshVacationNotice) {
		if (typeof _isPopup == 'undefined') _isPopup = false;
		if (typeof _refreshVacationNotice == 'undefined') _refreshVacationNotice = false;
		var nm;
		if (_isPopup && !this.mail_isMainWindow)
		{
			nm = window.opener.etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById(this.nm_index);
		}
		else
		{
			nm = this.et2.getWidgetById(this.nm_index);
		}
		var dates = this.et2.getWidgetById('mail.index.datefilter');
		var filter = this.et2.getWidgetById('cat_id');
		if(nm && filter)
		{
			nm.activeFilters["startdate"]=null;
			nm.activeFilters["enddate"]=null;
			switch(filter.getValue())
			{
				case 'bydate':

					if (filter && dates)
					{
						if (this.et2.getWidgetById('startdate') && this.et2.getWidgetById('startdate').get_value()) nm.activeFilters["startdate"] = this.et2.getWidgetById('startdate').date;
						if (this.et2.getWidgetById('enddate') && this.et2.getWidgetById('enddate').get_value()) nm.activeFilters["enddate"] = this.et2.getWidgetById('enddate').date;
					}
			}
		}
		nm.applyFilters(); // this should refresh the active folder
		if (_refreshVacationNotice) this.mail_callRefreshVacationNotice();
	},

	/**
	 * mail_getMsg - gets the current Message
	 * @return string
	 */
	mail_getMsg: function()
	{
		var msg_wdg = this.et2.getWidgetById('msg');
		if (msg_wdg)
		{
			return msg_wdg.valueOf().htmlNode[0].innerHTML;
		}
		return "";
	},

	/**
	 * mail_setMsg - sets a Message, with the msg container, and controls if the container is enabled/disabled
	 * @param {string} myMsg - the message
	 */
	mail_setMsg: function(myMsg)
	{
		var msg_wdg = this.et2.getWidgetById('msg');
		if (msg_wdg)
		{
			msg_wdg.set_value(myMsg);
			msg_wdg.set_disabled(myMsg.trim().length==0);
		}
	},

	/**
	 * Delete mails
	 * takes in all arguments
	 * @param _action
	 * @param _elems
	 */
	mail_delete: function(_action,_elems)
	{
		this.mail_checkAllSelected(_action,_elems,null,true);
	},

	/**
	 * call Delete mails
	 * takes in all arguments
	 * @param {object} _action
	 * @param {array} _elems
	 * @param {boolean} _allMessagesChecked
	 */
	mail_callDelete: function(_action,_elems,_allMessagesChecked)
	{
		var calledFromPopup = false;
		if (typeof _allMessagesChecked == 'undefined') _allMessagesChecked=false;
		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			calledFromPopup = true;
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		var msg = this.mail_getFormData(_elems);
		msg['all'] = _allMessagesChecked;
		if (msg['all']=='cancel') return false;
		if (msg['all']) msg['activeFilters'] = this.mail_getActiveFilters(_action);
		//alert(_action.id+','+ msg);
		if (!calledFromPopup) this.mail_setRowClass(_elems,'deleted');
		this.mail_deleteMessages(msg,'no',calledFromPopup);
		if (calledFromPopup && this.mail_isMainWindow==false)
		{
			egw(window).close();
		}
		else if (typeof this.et2_view!='undefined' && typeof this.et2_view.close == 'function')
		{
			this.et2_view.close();
		}
	},

	/**
	 * function to find (and reduce) unseen count from folder-name
	 */
	mail_reduceCounterWithoutServerRoundtrip: function()
	{
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var _foldernode = ftree.getSelectedNode();
		var counter = _foldernode.label.match(this._unseen_regexp);
		var icounter = 0;
		if ( counter ) icounter = parseInt(counter[0].replace(' (','').replace(')',''));
		if (icounter>0)
		{
			var newcounter = icounter-1;
			if (newcounter>0) _foldernode.label = _foldernode.label.replace(' ('+String(icounter)+')',' ('+String(newcounter)+')');
			if (newcounter==0) _foldernode.label = _foldernode.label.replace(' ('+String(icounter)+')','');
			ftree.setLabel(_foldernode.id,_foldernode.label);
		}
	},

	/**
	 * Regular expression to find (and remove) unseen count from folder-name
	 */
	_unseen_regexp: / \([0-9]+\)$/,

	/**
	 * mail_splitRowId
	 *
	 * @param {string} _rowID
	 *
	 */
	mail_splitRowId: function(_rowID)
	{
		var res = _rowID.split('::');
		// as a rowID is perceeded by app::, should be mail!
		if (res.length==4 && !isNaN(parseInt(res[0])))
		{
			// we have an own created rowID; prepend app=mail
			res.unshift('mail');
		}
		return res;
	},

	/**
	 * Delete mails - actually calls the backend function for deletion
	 * takes in all arguments
	 * @param {string} _msg - message list
	 * @param {object} _action - optional action
	 * @param {object} _calledFromPopup
	 */
	mail_deleteMessages: function(_msg,_action,_calledFromPopup)
	{
		var message, ftree, _foldernode, displayname;
		ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		if (ftree)
		{
			_foldernode = ftree.getSelectedNode();

			displayname = _foldernode.label.replace(this._unseen_regexp, '');
		}
		else
		{
			message = this.mail_splitRowId(_msg['msg'][0]);
			if (message[3]) _foldernode = displayname = atob(message[3]);
		}

		// Tell server
		egw.json('mail.mail_ui.ajax_deleteMessages', [_msg, (typeof _action == 'undefined' ? 'no' : _action)])
			.sendRequest(true);

		if (_msg['all']) this.egw.refresh(this.egw.lang("deleted %1 messages in %2",(_msg['all']?egw.lang('all'):_msg['msg'].length),(displayname?displayname:egw.lang('current folder'))),'mail');//,ids,'delete');
		this.egw.message(this.egw.lang("deleted %1 messages in %2", (_msg['all'] ? egw.lang('all') : _msg['msg'].length), (displayname ? displayname : egw.lang('current Folder'))), 'success');
	},

	/**
	 * Delete mails show result - called from the backend function for display of deletionmessages
	 * takes in all arguments
	 * @param _msg - message list
	 */
	mail_deleteMessagesShowResult: function(_msg)
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

			// Nextmatch automatically selects the next row and calls preview.
			// Unselect it and thanks to the timeout selectionMgr uses, preview
			// will close when the selection callback fires.
			this.et2.getWidgetById(this.nm_index).controller._selectionMgr.resetSelection();
		}
	},

	/**
	 * retry to Delete mails
	 * @param responseObject ->
	 * 	 reason - reason to report
	 * 	 messageList
	 */
	mail_retryForcedDelete: function(responseObject)
	{
		var reason = responseObject['response'];
		var messageList = responseObject['messageList'];
		if (confirm(reason))
		{
			this.mail_deleteMessages(messageList,'remove_immediately');
		}
		else
		{
			this.egw.message(this.egw.lang('canceled deletion due to user interaction'), 'success');
			this.mail_removeRowClass(messageList,'deleted');
		}
		this.mail_refreshMessageGrid();
		this.mail_preview();
	},

	/**
	 * UnDelete mailMessages
	 *
	 * @param _messageList
	 */
	mail_undeleteMessages: function(_messageList) {
	// setting class of row, the old style
	},

	/**
	 * mail_emptySpam
	 *
	 * @param {object} action
	 * @param {object} _senders
	 */
	mail_emptySpam: function(action,_senders) {
		var server = _senders[0].iface.id.split('::');
		var activeFilters = this.mail_getActiveFilters();
		var self = this;

		this.egw.message(this.egw.lang('empty junk'), 'success');
		egw.json('mail.mail_ui.ajax_emptySpam',[server[0], activeFilters['selectedFolder']? activeFilters['selectedFolder']:null],function(){self.unlock_tree();})
			.sendRequest(true);

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
	},

	/**
	 * mail_emptyTrash
	 *
	 * @param {object} action
	 * @param {object} _senders
	 */
	mail_emptyTrash: function(action,_senders) {
		var server = _senders[0].iface.id.split('::');
		var activeFilters = this.mail_getActiveFilters();
		var self = this;

		this.egw.message(this.egw.lang('empty trash'), 'success');
		egw.json('mail.mail_ui.ajax_emptyTrash',[server[0], activeFilters['selectedFolder']? activeFilters['selectedFolder']:null],function(){self.unlock_tree();})
			.sendRequest(true);

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
	},

	/**
	 * mail_compressFolder
	 *
	 * @param {object} action
	 * @param {object} _senders
	 *
	 */
	mail_compressFolder: function(action,_senders) {
		this.egw.message(this.egw.lang('compress folder'), 'success');
		egw.jsonq('mail.mail_ui.ajax_compressFolder',[_senders[0].iface.id]);
		//	.sendRequest(true);
		// since the json reply is using this.egw.refresh, we should not need to call refreshFolderStatus
		// as the actions thereof are now bound to run after grid refresh
		//this.mail_refreshFolderStatus();
	},

	/**
	 * mail_changeProfile
	 *
	 * @param {string} folder the ID of the selected Node -> should be an integer
	 * @param {object} _widget handle to the tree widget
	 * @param {boolean} getFolders Flag to indicate that the profile needs the mail
	 *		folders.  False means they're already loaded in the tree, and we don't need
	 *		them again
	 */
	mail_changeProfile: function(folder,_widget, getFolders) {
		if(typeof getFolders == 'undefined')
		{
			getFolders = true;
		}
	//	alert(folder);
		this.egw.message(this.egw.lang('Connect to Profile %1',_widget.getSelectedLabel().replace(this._unseen_regexp, '')), 'success');

		//Open unloaded tree to get loaded
		_widget.openItem(folder, true);

		this.lock_tree();
		egw.json('mail_ui::ajax_changeProfile',[folder, getFolders, this.et2._inst.etemplate_exec_id], jQuery.proxy(function() {
			// Profile changed, select inbox
			var inbox = folder + '::INBOX';
			_widget.reSelectItem(inbox);
			this.mail_changeFolder(inbox,_widget,'');
			this.unlock_tree();
		},this))
			.sendRequest(true);

		return true;
	},

	/**
	 * mail_changeFolder
	 * @param {string} _folder the ID of the selected Node
	 * @param {widget object} _widget handle to the tree widget
	 * @param {string} _previous - Previously selected node ID
	 */
	mail_changeFolder: function(_folder,_widget, _previous) {

		// to reset iframes to the normal status
		this.loadIframe();

		// reset nm action selection, seems actions system accumulate selected items
		// and that leads to corruption for selected all actions
		this.et2.getWidgetById(this.nm_index).controller._selectionMgr.resetSelection();

		// Abort if user selected an un-selectable node
		// Use image over anything else because...?
		var img = _widget.getSelectedNode().images[0];
		if (img.indexOf('NoSelect') !== -1)
		{
			_widget.reSelectItem(_previous);
			return;
		}

		// Check if this is a top level node and
		// change profile if server has changed
		var server = _folder.split('::');
		var previousServer = _previous.split('::');
		var profile_selected = (_folder.indexOf('::') === -1);
		if (server[0] != previousServer[0] && profile_selected)
		{
			// mail_changeProfile triggers a refresh, no need to do any more
			return this.mail_changeProfile(_folder,_widget, _widget.getSelectedNode().childsCount == 0);
		}

		// Apply new selected folder to list, which updates data
		var nm = _widget.getRoot().getWidgetById(this.nm_index);
		if(nm)
		{
			this.lock_tree();
			nm.applyFilters({'selectedFolder': _folder});
		}

		// Get nice folder name for message, if selected is not a profile
		if(!profile_selected)
		{
			var displayname = _widget.getSelectedLabel();
			var myMsg = (displayname?displayname:_folder).replace(this._unseen_regexp, '')+' '+this.egw.lang('selected');
			this.egw.message(myMsg, 'success');
		}

		// Update non-grid
		this.mail_refreshFolderStatus(_folder,'forced',false,false);
		this.mail_refreshQuotaDisplay(server[0]);
		this.mail_preview();
		this.mail_callRefreshVacationNotice(server[0]);
		if (server[0]!=previousServer[0])
		{
			egw.jsonq('mail.mail_ui.ajax_refreshFilters',[server[0]]);
		}
	},

	/**
	 * mail_checkAllSelected
	 *
	 * @param _action
	 * @param _elems
	 * @param _target
	 * @param _confirm
	 */
	mail_checkAllSelected: function(_action, _elems, _target, _confirm)
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
					{label: this.egw.lang("Cancel"), id: "cancel"}
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
					case "flagged":
					case "read":
					case "undelete":
						messageToDisplay = this.egw.lang("Do you really want to toggle flag %1 for ALL messages in the current view?",this.egw.lang(actionlabel))+" ";
						if (_action.id.substr(0,5)=='label') messageToDisplay = this.egw.lang("Do you really want to toggle label %1 for ALL messages in the current view?",this.egw.lang(actionlabel))+" ";
						break;
					default:
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
							that.lock_tree();
						}
						switch (_action.id)
					{
						case "delete":
							that.mail_callDelete(_action, _elems,rv);
							break;
						case "readall":
						case "unlabel":
						case "label1":
						case "label2":
						case "label3":
						case "label4":
						case "label5":
						case "flagged":
						case "read":
						case "undelete":
							that.mail_callFlagMessages(_action, _elems,rv);
							break;
						case "drop_move_mail":
							that.mail_callMove(_action, _elems,_target, rv);
							break;
						case "drop_copy_mail":
							that.mail_callCopy(_action, _elems,_target, rv);
							break;
						default:
							if (_action.id.substr(0,4)=='move') that.mail_callMove(_action, _elems,_target, rv);
							if (_action.id.substr(0,4)=='copy') that.mail_callCopy(_action, _elems,_target, rv);
					}
				},
				messageToDisplay,
				this.egw.lang("Confirm"),
				null, buttons);
			}
			else
			{
				rvMain = true;
			}
		}
		switch (_action.id)
		{
			case "delete":
				this.mail_callDelete(_action, _elems,rvMain);
				break;
			case "unlabel":
			case "label1":
			case "label2":
			case "label3":
			case "label4":
			case "label5":
			case "flagged":
			case "read":
			case "undelete":
				this.mail_callFlagMessages(_action, _elems,rvMain);
				break;
			case "drop_move_mail":
				this.mail_callMove(_action, _elems,_target, rvMain);
				break;
			case "drop_copy_mail":
				this.mail_callCopy(_action, _elems,_target, rvMain);
				break;
			default:
				if (_action.id.substr(0,4)=='move') this.mail_callMove(_action, _elems,_target, rvMain);
				if (_action.id.substr(0,4)=='copy') this.mail_callCopy(_action, _elems,_target, rvMain);
		}
	},

	/**
	 * mail_doActionCall
	 *
	 * @param _action
	 * @param _elems
	 */
	mail_doActionCall: function(_action, _elems)
	{
	},

	/**
	 * mail_getActiveFilters
	 *
	 * @param _action
	 * @return mixed boolean/activeFilters object
	 */
	mail_getActiveFilters: function(_action)
	{
		// we can NOT query global object manager for this.nm_index="nm", as we might not get the one from mail,
		// if other tabs are open, we have to query for obj_manager for "mail" and then it's child with id "nm"
		var obj_manager = egw_getObjectManager(this.appname).getObjectById(this.nm_index);
		if (obj_manager && obj_manager.manager && obj_manager.manager.data && obj_manager.manager.data.nextmatch && obj_manager.manager.data.nextmatch.activeFilters)
		{
			var af = obj_manager.manager.data.nextmatch.activeFilters;
			// merge startdate and enddate into the active filters (if set)
			if (this.et2.getWidgetById('startdate') && this.et2.getWidgetById('startdate').get_value()) af["startdate"] = this.et2.getWidgetById('startdate').date;
			if (this.et2.getWidgetById('enddate') && this.et2.getWidgetById('enddate').get_value()) af["enddate"] = this.et2.getWidgetById('enddate').date;
			return af;
		}
		return false;
	},

	/**
	 * Flag mail as 'read', 'unread', 'flagged' or 'unflagged'
	 *
	 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
	 * @param _elems
	 */
	mail_flag: function(_action, _elems)
	{
		this.mail_checkAllSelected(_action,_elems,null,true);
	},

	/**
	 * Flag mail as 'read', 'unread', 'flagged' or 'unflagged'
	 *
	 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
	 * @param _elems
	 * @param _allMessagesChecked
	 */
	mail_callFlagMessages: function(_action, _elems, _allMessagesChecked)
	{
		/**
		 * vars
		 */
		var folder = '',
			tree = {},
			formData = {},
			data = {
				msg: [this.et2.getArrayMgr("content").getEntry('mail_id')] || '',
				all: _allMessagesChecked || false,
				popup: typeof this.et2_view!='undefined' || egw(window).is_popup() || false,
				activeFilters: _action.id == 'readall'? false : this.mail_getActiveFilters(_action)
			},
			rowClass = _action.id;

		if (typeof _elems === 'undefined' || _elems.length == 0)
		{
			if (this.mail_isMainWindow && this.mail_currentlyFocussed)
			{
				data.msg = [this.mail_currentlyFocussed];
				_elems = data;
				data.msg = this.mail_getFormData(_elems).msg;
			}
		}
		else // action called by contextmenu
		{
			data.msg = this.mail_getFormData(_elems).msg;
		}
		switch (_action.id)
		{
			case 'read':
				rowClass = 'seen';
				if (data.popup)
				{
					var et_2 = typeof this.et2_view!='undefined'? etemplate2:opener.etemplate2;
					tree = et_2.getByApplication('mail')[0].widgetContainer.getWidgetById(this.nm_index+'[foldertree]');
				}
				else
				{
					tree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
				}
				folder = tree.getSelectedNode().id;
				break;
			case 'readall':
				rowClass = 'seen';
				break;
			case 'label1':
				rowClass = 'label1';
				break;
			case 'label2':
				rowClass = 'label2';
				break;
			case 'label3':
				rowClass = 'label3';
				break;
			case 'label4':
				rowClass = 'label4';
				break;
			case 'label5':
				rowClass = 'label5';
				break;
			default:
				break;
		}
		jQuery(data).extend({},data, formData);
		if (data['all']=='cancel') return false;

		if (_action.id.substring(0,2)=='un') {
			//old style, only available for undelete and unlabel (no toggle)
			if ( _action.id=='unlabel') // this means all labels should be removed
			{
				var labels = ['label1','label2','label3','label4','label5'];
				for (var i=0; i<labels.length; i++)	this.mail_removeRowClass(_elems,labels[i]);
				this.mail_flagMessages(_action.id,data);
			}
			else
			{
				this.mail_removeRowClass(_elems,_action.id.substring(2));
				this.mail_setRowClass(_elems,_action.id);
				this.mail_flagMessages(_action.id,data);
			}
		}
		else if (_action.id=='readall')
		{
			this.mail_flagMessages('read',data);
		}
		else
		{
			var msg_set = {msg:[]};
			var msg_unset = {msg:[]};
			var dataElem;
			var flags;
			var classes = '';
			for (var i=0; i<data.msg.length; i++)
			{
				dataElem = egw.dataGetUIDdata(data.msg[i]);
				if(typeof dataElem.data.flags == 'undefined')
				{
					dataElem.data.flags = {};
				}
				flags = dataElem.data.flags;
				classes = dataElem.data['class'] || "";
				classes = classes.split(' ');
				// since we toggle we need to unset the ones already set, and set the ones not set
				// flags is data, UI is done by class, so update both
				// Flags are there or not, class names are flag or 'un'+flag
				if(classes.indexOf(rowClass) >= 0)
				{
					classes.splice(classes.indexOf(rowClass),1);
				}
				if(classes.indexOf('un' + rowClass) >= 0)
				{
					classes.splice(classes.indexOf('un' + rowClass),1);
				}
				if (flags[_action.id])
				{
					msg_unset['msg'].push(data.msg[i]);
					classes.push('un'+rowClass);
					delete flags[_action.id];
				}
				else
				{
					msg_set['msg'].push(data.msg[i]);
					flags[_action.id] = _action.id;
					classes.push(rowClass);
				}

				// Update cache & call callbacks - updates list
				dataElem.data['class']  = classes.join(' ');
				egw.dataStoreUID(data.msg[i],dataElem.data);

				//Refresh the nm rows after we told dataComponent about all changes, since the dataComponent doesn't talk to nm, we need to do it manually
				this.updateFilter_data(data.msg[i], _action.id, data.activeFilters);
			}

			// Notify server of changes
			if (msg_unset['msg'] && msg_unset['msg'].length)
			{
				if (!data['all']) this.mail_flagMessages('un'+_action.id,msg_unset);
			}
			if (msg_set['msg'] && msg_set['msg'].length)
			{
				if (!data['all']) this.mail_flagMessages(_action.id,msg_set);
			}
			//server must do the toggle, as we apply to ALL, not only the visible
			if (data['all']) this.mail_flagMessages(_action.id,data);
			// No further update needed, only in case of read, the counters should be refreshed
			if (_action.id=='read') this.mail_refreshFolderStatus(folder,'thisfolderonly',false,true);
			return;
		}
	},

	/**
	 * Update changes on filtered mail rows in nm, triggers manual refresh
	 *
	 * @param {type} _uid mail uid
	 * @param {type} _actionId action id sended by nm action
	 * @param {type} _filters activefilters
	 */
	updateFilter_data: function (_uid, _actionId, _filters)
	{
		var uid = _uid.replace('mail::','');
		var action = '';
		switch (_actionId)
		{
			case 'flagged':
				action = 'flagged';
				break;
			case 'read':
				if (_filters.filter == 'seen')
				{
					action = 'seen';
				}
				else if (_filters.filter == 'unseen')
				{
					action = 'unseen';
				}
				break;
			case 'label1':
				action = 'keyword1';
				break;
			case 'label2':
				action = 'keyword2';
				break;
			case 'label3':
				action = 'keyword3';
				break;
			case 'label4':
				action = 'keyword4';
				break;
			case 'label4':
				action = 'keyword4';
				break;
		}
		if (action == _filters.filter)
		{
			egw.refresh('','mail',uid, 'delete');
		}
	},

	/**
	 * Flag mail as 'read', 'unread', 'flagged' or 'unflagged'
	 *
	 * @param {object} _flag
	 * @param {object} _elems
	 * @param {boolean} _isPopup
	 */
	mail_flagMessages: function(_flag, _elems,_isPopup)
	{
		egw.jsonq('mail.mail_ui.ajax_flagMessages',[_flag, _elems]);
		//	.sendRequest(true);
	},

	/**
	 * display header lines, or source of mail, depending on the url given
	 *
	 * @param _url
	 */
	mail_displayHeaderLines: function(_url) {
		// only used by right clickaction
		egw.openPopup(_url, '870', '600', null, 'mail');
	},

	/**
	 * View header of a message
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_header: function(_action, _elems)
	{
		if (typeof _elems == 'undefined'|| _elems.length==0)
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		//alert('mail_header('+_elems[0].id+')');
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=mail.mail_ui.displayHeader';	// todo compose for Draft folder
		url += '&id='+_elems[0].id;
		this.mail_displayHeaderLines(url);
	},

	/**
	 * View message source
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_mailsource: function(_action, _elems)
	{
		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined'|| _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		//alert('mail_mailsource('+_elems[0].id+')');
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=mail.mail_ui.saveMessage';	// todo compose for Draft folder
		url += '&id='+_elems[0].id;
		url += '&location=display';
		this.mail_displayHeaderLines(url);
	},

	/**
	 * Save a message
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_save: function(_action, _elems)
	{
		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}

		for (var i in _elems)
		{
			//alert('mail_save('+_elems[0].id+')');
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
	},

	/**
	 * User clicked an address (FROM, TO, etc)
	 *
	 * @param {object} tag_info with values for attributes id, label, title, ...
	 * @param {widget object} widget
	 *
	 * @todo seems this function is not implemented, need to be checked if it is neccessary at all
	 */
	address_click: function(tag_info, widget)
	{

	},

	/**
	 * displayAttachment
	 *
	 * @param {object} tag_info
	 * @param {widget object} widget
	 * @param {object} calledForCompose
	 */
	displayAttachment: function(tag_info, widget, calledForCompose)
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
				attgrid = this.et2.getArrayMgr("content").getEntry('mail_displayattachments')[widget.id.replace(/\[filename\]/,'')];
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
				url += '&part='+attgrid.partID;
				url += '&is_winmail='+attgrid.winmailFlag;
				windowName = windowName+'displayMessage_'+mailid+'_'+attgrid.partID;
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
	},

	/**
	 * displayUploadedFile
	 *
	 * @param {object} tag_info
	 * @param {widget object} widget
	 */
	displayUploadedFile: function(tag_info, widget)
	{
		var attgrid;
		attgrid = this.et2.getArrayMgr("content").getEntry('attachments')[widget.id.replace(/\[name\]/,'')];

		if (attgrid.uid && (attgrid.partID||attgrid.folder))
		{
			this.displayAttachment(tag_info, widget, true);
			return;
		}
		var get_param = {
			menuaction: 'mail.mail_compose.getAttachment',	// todo compose for Draft folder
			tmpname: attgrid.tmp_name,
			etemplate_exec_id: this.et2._inst.etemplate_exec_id
		};
		var width;
		var height;
		var windowName ='maildisplayAttachment_'+attgrid.file.replace(/\//g,"_");
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
				var reg = '800x600';
				var reg2;
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
				var w_h =reg.split('x');
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
	},

	/**
	 * Callback function to handle vfsSave response messages
	 *
	 * @param {type} _data
	 */
	vfsSaveCallback: function (_data)
	{
		egw.message(_data.msg, _data.success ? "success": "error");
	},

	/**
	 * A handler for saving to VFS/downloading attachments
	 *
	 * @param {type} widget
	 * @param {type} action
	 * @param {type} row_id
	 */
	saveAttachmentHandler: function (widget, action, row_id)
	{
		var mail_id, attachments;

		if (this.mail_isMainWindow)
		{
			mail_id = this.mail_currentlyFocussed;
			var p = widget.getParent();
			attachments = p.getArrayMgr("content").data;
		}
		else
		{
			mail_id = this.et2.getArrayMgr("content").getEntry('mail_id');
			attachments = this.et2.getArrayMgr("content").getEntry('mail_displayattachments');
		}

		switch (action)
		{
			case 'saveOneToVfs':
			case 'saveAllToVfs':
				var ids = [];
				attachments = action === 'saveOneToVfs' ? [attachments[row_id]] : attachments;
				for (var i=0;i<attachments.length;i++)
				{
					if (attachments[i] != null)
					{
						ids.push(mail_id+'::'+attachments[i].partID+'::'+attachments[i].winmailFlag+'::'+attachments[i].filename);
					}
				}
				var vfs_select = et2_createWidget('vfs-select', {
					mode: action === 'saveOneToVfs' ? 'saveas' : 'select-dir',
					method: 'mail.mail_ui.ajax_vfsSave',
					button_label: this.egw.lang(action === 'saveOneToVfs' ? 'Save' : 'Save all'),
					dialog_title: this.egw.lang(action === 'saveOneToVfs' ? 'Save attachment' : 'Save attachments'),
					method_id: ids.length > 1 ? {ids: ids, action: 'attachment'} : {ids: ids[0], action: 'attachment'},
					name: action === 'saveOneToVfs' ? attachments[0]['filename'] : null
				});
				vfs_select.click();
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
				var attachment = attachments[row_id];
				var url = window.egw_webserverUrl+'/index.php?';
				url += jQuery.param({
					menuaction: action === 'downloadOneAsFile' ?
						'mail.mail_ui.getAttachment' : 'mail.mail_ui.download_zip',
					mode: 'save',
					id: mail_id,
					part: attachment.partID,
					is_winmail: attachment.winmailFlag,
					smime_type: (attachment.smime_type ? attachment.smime_type : '')
				});
				this.et2._inst.download(url);
				break;
		}
	},

	/**
	 * Save a message to filemanager
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_save2fm: function(_action, _elems)
	{
		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
					_elems.push({id:this.mail_currentlyFocussed});
				}
			}
		}
		var ids = [], names = [];
		for (var i in _elems)
		{
			var _id = _elems[i].id;
			var dataElem = egw.dataGetUIDdata(_id);
			var subject = dataElem? dataElem.data.subject: _elems[i].subject;
			if (this.egw.is_popup() && this.et2._inst.name == 'mail.display')
			{
				subject = this.et2.getArrayMgr('content').getEntry('mail_displaysubject');
			}
			// Replace these now, they really cause problems later
			var filename = subject ? subject.replace(/[\f\n\t\v\x0b\:*#?<>%"\/\\\?]/g,"_") : 'unknown';
			ids.push(_id);
			names.push(filename+'.eml');
		}
		var vfs_select = et2_createWidget('vfs-select', {
			mode: _elems.length > 1 ? 'select-dir' : 'saveas',
			mime: 'message/rfc822',
			method: 'mail.mail_ui.ajax_vfsSave',
			button_label: _elems.length>1 ? egw.lang('Save all') : egw.lang('save'),
			dialog_title: this.egw.lang("Save email"),
			method_id: _elems.length > 1 ? {ids:ids, action:'message'}: {ids: ids[0], action: 'message'},
			name: _elems.length > 1 ? names : names[0],
		});
		vfs_select.click();
	},

	/**
	 * Integrate mail message into another app's entry
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_integrate: function(_action, _elems)
	{
		var app = _action.id;
		var w_h = ['750','580']; // define a default wxh if there's no popup size registered

		if (typeof _action.data != 'undefined' )
		{
			if (typeof _action.data.popup != 'undefined' && _action.data.popup) w_h = _action.data.popup.split('x');
			if (typeof _action.data.mail_import != 'undefined') var mail_import_hook = _action.data.mail_import;
		}

		if (typeof _elems == 'undefined' || _elems.length==0)
		{
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				var _elems = [];
				_elems.push({id:this.et2.getArrayMgr("content").getEntry('mail_id') || ''});
			}
			if ((typeof _elems == 'undefined' || _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					var _elems = [];
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
			this.integrate_checkAppEntry(title, app, subject, url,  mail_import_hook.app_entry_method, function (args){
				egw_openWindowCentered(args.url+ (args.entryid ?'&entry_id=' + args.entryid: ''),'import_mail_'+_elems[0].id,w_h[0],w_h[1]);
			});
		}
		else
		{
			egw_openWindowCentered(url,'import_mail_'+_elems[0].id,w_h[0],w_h[1]);
		}

	},

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
	integrate_checkAppEntry: function (_title, _appName, _subject ,_url, _appCheckCallback, _execCallback)
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
				   callback: function (_buttons, _value)
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
	},

	/**
	 * mail_getFormData
	 *
	 * @param {object} _actionObjects the senders
	 *
	 * @return structured array of message ids: array(msg=>message-ids)
	 */
	mail_getFormData: function(_actionObjects) {
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
	},

	/**
	 * mail_setRowClass
	 *
	 * @param {object} _actionObjects the senders
	 * @param {string} _class
	 */
	mail_setRowClass: function(_actionObjects,_class) {
		if (typeof _class == 'undefined') return false;

		if (typeof _actionObjects['msg'] == 'undefined')
		{
			for (var i = 0; i < _actionObjects.length; i++)
			{
				// Check that the ID & interface is there.  Paste is missing iface.
				if (_actionObjects[i].id.length>0 && _actionObjects[i].iface)
				{
					var dataElem = jQuery(_actionObjects[i].iface.getDOMNode());
					dataElem.addClass(_class);

				}
			}
		}
		else
		{
			for (var i = 0; i < _actionObjects['msg'].length; i++)
			{
				var mail_uid = _actionObjects['msg'][i];

				// Get the record from data cache
				var dataElem = egw.dataGetUIDdata(mail_uid);
				if(dataElem == null || typeof dataElem == undefined)
				{
					// Unknown ID, nothing to update
					return;
				}

				// Update class
				dataElem.data['class']  += ' ' + _class;

				// need to update flags too
				switch(_class)
				{
					case 'unseen':
						delete dataElem.data.flags.read;
						break;
				}

				// Update record, which updates all listeners (including nextmatch)
				egw.dataStoreUID(mail_uid,dataElem.data);
			}
		}
	},

	/**
	 * mail_removeRowFlag
	 * Removes a flag and updates the CSS class.  Updates the UI, but not the server.
	 *
	 * @param {action object} _actionObjects the senders, or a messages object
	 * @param {string} _class the class to be removed
	 */
	mail_removeRowClass: function(_actionObjects,_class) {
		if (typeof _class == 'undefined') return false;

		if (typeof _actionObjects['msg'] == 'undefined')
		{
			for (var i = 0; i < _actionObjects.length; i++)
			{
				if (_actionObjects[i].id.length>0)
				{
					var dataElem = jQuery(_actionObjects[i].iface.getDOMNode());
					dataElem.removeClass(_class);

				}
			}
		}
		else
		{
			for (var i = 0; i < _actionObjects['msg'].length; i++)
			{
				var mail_uid = _actionObjects['msg'][i];

				// Get the record from data cache
				var dataElem = egw.dataGetUIDdata(mail_uid);
				if(dataElem == null || typeof dataElem == undefined)
				{
					// Unknown ID, nothing to update
					return;
				}

				// Update class
				var classes = dataElem.data['class'] || "";
				classes = classes.split(' ');
				if(classes.indexOf(_class) >= 0)
				{
					for(var c in classes)
					{
						classes.splice(classes.indexOf(_class),1);
						if (classes.indexOf(_class) < 0) break;
					}
					dataElem.data['class'] = classes.join(' ');

					// need to update flags too
					switch(_class)
					{
						case 'unseen':
							dataElem.data.flags.read = true;
							break;
					}

					// Update record, which updates all listeners (including nextmatch)
					egw.dataStoreUID(mail_uid,dataElem.data);
				}
			}
		}
	},

	/**
	 * mail_move2folder - implementation of the move action from action menu
	 *
	 * @param _action _action.id holds folder target information
	 * @param _elems - the representation of the elements to be affected
	 */
	mail_move2folder: function(_action, _elems) {
		this.mail_move(_action, _elems, null);
	},

	/**
	 * mail_move - implementation of the move action from drag n drop
	 *
	 * @param _action
	 * @param _senders - the representation of the elements dragged
	 * @param _target - the representation of the target
	 */
	mail_move: function(_action,_senders,_target) {
		this.mail_checkAllSelected(_action,_senders,_target,true);
	},

	/**
	 * mail_move - implementation of the move action from drag n drop
	 *
	 * @param _action
	 * @param _senders - the representation of the elements dragged
	 * @param _target - the representation of the target
	 * @param _allMessagesChecked
	 */
	mail_callMove: function(_action,_senders,_target,_allMessagesChecked) {
		var target = _action.id == 'drop_move_mail' ? _target.iface.id : _action.id.substr(5);
		var messages = this.mail_getFormData(_senders);
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
		if (messages['all']) messages['activeFilters'] = this.mail_getActiveFilters(_action);

		// Make sure a default target folder is set in case of drop target is parent 0 (mail account name)
		if (!target.match(/::/g)) target += '::INBOX';

		var self = this;
		var nm = this.et2.getWidgetById(this.nm_index);
		// Nextmatch automatically selects the next row and calls preview.
		// Stop it for now, we'll put it back when the copy is done
		let on_select = nm.options.onselect;
		nm.options.onselect = null;
		_senders[0].parent.setAllSelected(false);
		this.mail_preview([],nm);
		// Restore onselect handler
		nm.options.onselect = on_select;
		// thev 4th param indicates if it is a normal move messages action. if not the action is a move2.... (archiveFolder) action
		egw.json('mail.mail_ui.ajax_copyMessages',[target, messages, 'move', (_action.id.substr(0,4)=='move'&&_action.id.substr(4,1)=='2'?'2':'_') ], function(){
			self.unlock_tree();

			// Server response may contain refresh, but it's always delete
			// Refresh list if current view is the target (happens when pasting)
			var tree = self.et2.getWidgetById('nm[foldertree]');
			if(nm && tree && target == tree.getValue())
			{
				// Can't trust the sorting, needs to be full refresh
				nm.refresh();
			}
		})
			.sendRequest();
		this.mail_setRowClass(_senders,'deleted');
		// Server response may contain refresh, not needed here
	},

	/**
	 * mail_copy - implementation of the move action from drag n drop
	 *
	 * @param _action
	 * @param _senders - the representation of the elements dragged
	 * @param _target - the representation of the target
	 */
	mail_copy: function(_action,_senders,_target) {
		this.mail_checkAllSelected(_action,_senders,_target,true);
	},

	/**
	 * mail_callCopy - implementation of the copy action from drag n drop
	 *
	 * @param _action
	 * @param _senders - the representation of the elements dragged
	 * @param _target - the representation of the target
	 * @param _allMessagesChecked
	 */
	mail_callCopy: function(_action,_senders,_target,_allMessagesChecked) {
		var target = _action.id == 'drop_copy_mail' ? _target.iface.id : _action.id.substr(5);
		var messages = this.mail_getFormData(_senders);
		if (typeof _allMessagesChecked=='undefined') _allMessagesChecked=false;
		// TODO: Write move/copy function which cares about doing the same stuff
		// as the "onNodeSelect" function!
		messages['all'] = _allMessagesChecked;
		if (messages['all']=='cancel') return false;
		if (messages['all']) messages['activeFilters'] = this.mail_getActiveFilters(_action);
		var self = this;
		egw.json('mail.mail_ui.ajax_copyMessages',[target, messages],function (){self.unlock_tree();})
			.sendRequest();
		// Server response contains refresh
	},

	/**
	 * mail_AddFolder - implementation of the AddFolder action of right click options on the tree
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	mail_AddFolder: function(_action,_senders) {
		//action.id == 'add'
		//_senders.iface.id == target leaf / leaf to edit
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var OldFolderName = ftree.getLabel(_senders[0].id).replace(this._unseen_regexp,'');
		var buttons = [
			{label: this.egw.lang("Add"), id: "add", "class": "ui-priority-primary", "default": true},
			{label: this.egw.lang("Cancel"), id: "cancel"}
		];
		Et2Dialog.show_prompt(function (_button_id, _value)
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
							egw.json('mail.mail_ui.ajax_addFolder', [_senders[0].id, NewFolderName])
								.sendRequest(true);
							return;
					case "cancel":
				}
			}
		},
		this.egw.lang("Enter the name for the new Folder:"),
		this.egw.lang("Add a new Folder to %1:",OldFolderName),
		'', buttons);
	},

	/**
	 * mail_RenameFolder - implementation of the RenameFolder action of right click options on the tree
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	mail_RenameFolder: function(_action,_senders) {
		//action.id == 'rename'
		//_senders.iface.id == target leaf / leaf to edit
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var OldFolderName = ftree.getLabel(_senders[0].id).replace(this._unseen_regexp,'');
		var buttons = [
			{label: this.egw.lang("Rename"), id: "rename", "class": "ui-priority-primary", image: 'edit', "default": true},
			{label: this.egw.lang("Cancel"), id: "cancel"}
		];
		Et2Dialog.show_prompt(function (_button_id, _value)
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
							egw.json('mail.mail_ui.ajax_renameFolder', [_senders[0].id, NewFolderName])
								.sendRequest(true);
							return;
					case "cancel":
				}
			}
		},
		this.egw.lang("Rename Folder %1 to:",OldFolderName),
		this.egw.lang("Rename Folder %1 ?",OldFolderName),
		OldFolderName, buttons);
	},

	/**
	 * mail_MoveFolder - implementation of the MoveFolder action on the tree
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _senders - the representation of the tree leaf to be manipulated
	 * @param {egwActionObject} destination Drop target egwActionObject representing the destination
	 */
	mail_MoveFolder: function(_action,_senders,destination) {
		if(!destination || !destination.id)
		{
			egw.debug('warn', "Move folder, but no target");
			return;
		}
		var sourceProfile = _senders[0].id.split('::');
		var targetProfile = destination.id.split('::');
		if (sourceProfile[0]!=targetProfile[0])
		{
			egw.message(this.egw.lang('Moving Folders from one Mailaccount to another is not supported'),'error');
			return;
		}
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var src_label = _senders[0].id.replace(/^[0-9]+::/,'');
		var dest_label = destination.id.replace(/^[0-9]+::/,'');

		var callback = function (_button)
		{
			if (_button == Et2Dialog.YES_BUTTON)
			{
				egw.appName = 'mail';
				egw.message(egw.lang('Folder %1 is moving to folder %2', src_label, dest_label));
				egw.loading_prompt('mail_moveFolder', true, '', '#egw_fw_basecontainer');
				for (var i = 0; i < _senders.length; i++)
				{
					egw.jsonq('mail.mail_ui.ajax_MoveFolder', [_senders[i].id, destination.id],
						// Move is done (successfully or not), remove loading
						function ()
						{
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
	},

	/**
	 * mail_DeleteFolder - implementation of the DeleteFolder action of right click options on the tree
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	mail_DeleteFolder: function(_action,_senders)
	{
		//action.id == 'delete'
		//_senders.iface.id == target leaf / leaf to edit
		var ftree = this.et2.getWidgetById(this.nm_index + '[foldertree]');
		var OldFolderName = ftree.getLabel(_senders[0].id).replace(this._unseen_regexp, '');
		var buttons = [
			{label: this.egw.lang("Yes"), id: "delete", "class": "ui-priority-primary", "default": true, image: "check"},
			{label: this.egw.lang("Cancel"), id: "cancel", image: "cancel"}
		];
		Et2Dialog.show_dialog(function (_button_id, _value)
			{
				switch (_button_id)
				{
					case "delete":
						egw.json('mail.mail_ui.ajax_deleteFolder', [_senders[0].id])
							.sendRequest(true);
						return;
					case "cancel":
				}
			},
			this.egw.lang("Do you really want to DELETE Folder %1 ?", OldFolderName) + " " + (ftree.hasChildren(_senders[0].id) ? this.egw.lang("All subfolders will be deleted too, and all messages in all affected folders will be lost") : this.egw.lang("All messages in the folder will be lost")),
			this.egw.lang("DELETE Folder %1 ?", OldFolderName),
			OldFolderName, buttons);
	},

	/**
	 * Send names of uploaded files (again) to server, to process them: either copy to vfs or ask overwrite/rename
	 *
	 * @param _event
	 * @param _file_count
	 * @param {string?} _path where the file is uploaded to, default current directory
	 */
	uploadForImport: function(_event, _file_count, _path)
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
	},

	/**
	 * Send names of uploaded files (again) to server, to process them: either copy to vfs or ask overwrite/rename
	 *
	 * @param {event object} _event
	 * @param {string} _file_count
	 * @param {string} _path [_path=current directory] Where the file is uploaded to.
	 */
	uploadForCompose: function(_event, _file_count, _path)
	{
		// path is probably not needed when uploading for file; maybe it is when from vfs
		if(typeof _path == 'undefined')
		{
			//_path = this.get_path();
		}
		if (_file_count && !jQuery.isEmptyObject(_event.data.getValue()))
		{
			var widget = _event.data;
			this.et2_obj.submit();
		}
	},

	/**
	 * Visible attachment box in compose dialog as soon as the file starts to upload
	 */
	composeUploadStart: function ()
	{
		var boxAttachment = this.et2.getWidgetById('attachments');
		if (boxAttachment)
		{
			var groupbox = boxAttachment.getParent();
			if (groupbox) groupbox.set_disabled(false);
		}
		//Resize the compose dialog
		var self = this;
		setTimeout(function(){self.compose_resizeHandler();}, 100);
		return true;
	},

	/**
	* Upload for import (VFS)
	*
	* @param {egw object} _egw
	* @param {widget object} _widget
	* @param {window object} _window
	*/
	vfsUploadForImport: function(_egw, _widget, _window) {
		if (jQuery.isEmptyObject(_widget)) return;
		if (!jQuery.isEmptyObject(_widget.getValue()))
		{
			this.et2_obj.submit();
		}
	},

	/**
	* Upload for compose (VFS)
	*
	* @param {egw object} _egw
	* @param {widget object} _widget
	* @param {window object} _window
	*/
	vfsUploadForCompose: function(_egw, _widget, _window)
	{
		if (jQuery.isEmptyObject(_widget)) return;
		if (!jQuery.isEmptyObject(_widget.getValue()))
		{
			this.et2_obj.submit();
		}
	},

	/**
	* Submit on change (VFS)
	*
	* @param {egw object} _egw
	* @param {widget object} _widget
	*/
	submitOnChange: function(_egw, _widget)
	{
		if (!jQuery.isEmptyObject(_widget))
		{
			if (typeof _widget.id !== 'undefined') var widgetId = _widget.id;
			switch (widgetId)
			{
				case 'mimeType':
					this.et2_obj.submit();
					break;
				default:
					if (!jQuery.isEmptyObject(_widget.getValue()))
					{
						this.et2_obj.submit();
					}
			}
		}
	},

	/**
	 * Save as Draft (VFS)
	 * -handel both actions save as draft and save as draft and print
	 *
	 * @param {egwAction} _egw_action
	 * @param {array|string} _action string "autosaving", if that triggered the action
	 *
	 * @return Promise
	 */
	saveAsDraft: function(_egw_action, _action)
	{
		var self = this;
		return new Promise(function(_resolve, _reject){
			var content = self.et2.getArrayMgr('content').data;
			var action = _action;
			if (_egw_action && _action !== 'autosaving')
			{
				action = _egw_action.id;
			}

			var widgets = ['from','to','cc','bcc','subject','folder','replyto','mailaccount',
				'mail_htmltext', 'mail_plaintext', 'lastDrafted', 'filemode', 'expiration', 'password'];
			var widget = {};
			for (var index in widgets)
			{
				widget = self.et2.getWidgetById(widgets[index]);
				if (widget)
				{
					content[widgets[index]] = widget.get_value();
				}
			}

			if (content)
			{
				// if we compose an encrypted message, we have to get the encrypted content
				if (self.mailvelope_editor)
				{
					self.mailvelope_editor.encrypt([]).then(function(_armored)
					{
						content['mail_plaintext'] = _armored;
						self.egw.json('mail.mail_compose.ajax_saveAsDraft',[content, action],function(_data){
							var res = self.savingDraft_response(_data,action);
							if (res)
							{
								_resolve();
							}
							else
							{
								_reject();
							}
						}).sendRequest(true);
					}, function(_err)
					{
						self.egw.message(_err.message, 'error');
						_reject();
					});
					return false;
				}
				else
				{
					// Send request through framework main window, so it works even if the main window is reloaded
					framework.egw_appWindow().egw.json('mail.mail_compose.ajax_saveAsDraft', [content, action], function (_data)
					{
						var res = self.savingDraft_response(_data, action);
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
			}
		});
	},

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
	savingDraft_response: function(_responseData, _action)
	{
		//Make sure there's a response from server otherwise shoot an error message
		if (jQuery.isEmptyObject(_responseData))
		{
			this.egw.message('Could not saved the message. Because, the response from server failed.', 'error');
			return false;
		}

		if (_responseData.success)
		{
			var content = this.et2.getArrayMgr('content');
			var lastDrafted = this.et2.getWidgetById('lastDrafted');
			var folderTree = typeof opener.etemplate2.getByApplication('mail')[0] !='undefined'?
								opener.etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('nm[foldertree]'): null;
			var activeFolder = folderTree?folderTree.getSelectedNode():null;
			if (content)
			{
				var prevDraftedId = content.data.lastDrafted;
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
						this.mail_compose_print('mail::'+_responseData.draftedId);
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
	},

	/**
	 * Focus handler for folder, address, reject textbox/taglist to automatic check associated radio button
	 *
	 * @param {event} _ev
	 * @param {object} _widget taglist
	 *
	 */
	sieve_focus_radioBtn: function(_ev, _widget)
	{
		_widget.getRoot().getWidgetById('action').set_value(_widget.id.replace(/^action_([^_]+)_text$/, '$1'));
	},

	/**
	 * Select all aliases
	 *
	 */
	sieve_vac_all_aliases: function()
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
	},

	/**
	 * Disable/Enable date widgets on vacation seive rules form when status is "by_date"
	 *
	 */
	vacationFilterStatusChange: function()
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
	},

	/**
	 * action - handling actions on sieve rules
	 *
	 * @param _type - action name
	 * @param _selected - selected row from the sieve rule list
	 */
	action: function(_type, _selected)
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
					this.egw.open_link(linkData,'_blank',"600x680");
					break;
				case 'edit'	:
					linkData = "mail.mail_sieve.edit&ruleID="+ruleID;
					this.egw.open_link(linkData,'_blank',"600x680");
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

	},

	/**
	* Send back sieve action result to server
	*
	* @param {string} _typeID action name
	* @param {object} _data content
	* @param {string} _selectedID selected row id
	* @param {string} _msg message
	*
	*/
	_do_action: function(_typeID, _data,_selectedID,_msg)
	{
		if (_typeID && _data)
		{
			var request = this.egw.json('mail.mail_sieve.ajax_action', [_typeID,_selectedID,_msg],null,null,true);
			request.sendRequest();
		}
	},

	/**
	* Send ajax request to server to refresh the sieve grid
	*/
	sieve_refresh: function()
	{
		this.et2._inst.submit();
	},

	/**
	 * Select the right combination of the rights for radio buttons from the selected common right
	 *
	 * @@param {jQuery event} event
	 * @param {widget} widget common right selectBox
	 *
	 */
	acl_common_rights_selector: function(event,widget)
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
	},

	/**
	 *
	 * Choose the right common right option for common ACL selecBox
	 *
	 * @param {jQuery event} event
	 * @param {widget} widget radioButton rights
	 *
	 */
	acl_common_rights: function(event, widget)
	{
		var rowId = widget.id.replace(/[^0-9.]+/g, '');
		var aclCommonWidget = this.et2.getWidgetById(rowId + '[acl]');
		var rights = '';
		var selectedBox = widget.id;
		var virtualDelete = ['e','t','x'];
		var virtualCreate = ['k','x'];

		for (var i=0;i<this.aclRights.length;i++)
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

		for (var i=0;i<this.aclCommonRights.length;i++)
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
	},

	/**
	 * Open seive filter list
	 *
	 * @param {action} _action
	 * @param {sender} _senders
	 *
	 */
	edit_sieve: function(_action, _senders)
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
	},

	/**
	 * Load an url on an iframe
	 *
	 * @param {string} _url string egw url
	 * @param {iframe widget} _iFrame an iframe to be set if non, extra_iframe is default
	 *
	 * @return {boolean} return TRUE if success, and FALSE if iframe not given
	 */
	loadIframe: function (_url, _iFrame)
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
	},

	/**
	 * Edit vacation message
	 *
	 * @param {action} _action
	 * @param {sender} _senders
	 */
	edit_vacation: function(_action, _senders)
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
		this.egw.open_link('mail.mail_sieve.editVacation&acc_id=' + acc_id, '_blank', '700x560');
	},

	subscription_refresh: function(_data)
	{
		console.log(_data);
	},

	/**
	 * Submit on apply button and save current tree state
	 *
	 * @param {type} _egw
	 * @param {type} _widget
	 * @returns {undefined}
	 */
	subscription_apply: function (_egw, _widget)
	{
		var tree = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('foldertree');
		if (tree)
		{
			tree.input._xfullXML = true;
			this.subscription_treeLastState = tree.input.serializeTreeToJSON();
		}
		this.et2._inst.submit(_widget);
	},

	/**
	 * Show ajax-loader when the autoloading get started
	 *
	 * @param {type} _id item id
	 * @param {type} _widget tree widget
	 * @returns {Boolean}
	 */
	subscription_autoloadingStart: function (_id, _widget)
	{
		var node = _widget.input._globalIdStorageFind(_id);
		if (node && typeof node.htmlNode != 'undefined')
		{
			var img = jQuery('img',node.htmlNode)[0];
			img.src = egw.image('ajax-loader', 'admin');
		}
		return true;
	},

	/**
	 * Revert back the icon after autoloading is finished
	 * @returns {Boolean}
	 */
	subscription_autoloadingEnd: function ()
	{
		return true;
	},

	/**
	 * Popup the subscription dialog
	 *
	 * @param {action} _action
	 * @param {sender} _senders
	 */
	edit_subscribe: function (_action,_senders)
	{
		var acc_id = parseInt(_senders[0].id);
		this.egw.open_link('mail.mail_ui.subscription&acc_id='+acc_id, '_blank', '720x580');
	},

	/**
	 * Subscribe selected unsubscribed folder
	 *
	 * @param {action} _action
	 * @param {sender} _senders
	 */
	subscribe_folder: function(_action,_senders)
	{
		var mailbox = _senders[0].id.split('::');
		var folder = mailbox[1], acc_id = mailbox[0];
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		this.egw.message(this.egw.lang('Subscribe to Folder %1',ftree.getLabel(_senders[0].id).replace(this._unseen_regexp,'')), 'success');
		egw.json('mail.mail_ui.ajax_foldersubscription',[acc_id,folder,true])
			.sendRequest();
	},

	/**
	 * Unsubscribe selected subscribed folder
	 *
	 * @param {action} _action
	 * @param {sender} _senders
	 */
	unsubscribe_folder: function(_action,_senders)
	{
		var mailbox = _senders[0].id.split('::');
		var folder = mailbox[1], acc_id = mailbox[0];
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		this.egw.message(this.egw.lang('Unsubscribe from Folder %1',ftree.getLabel(_senders[0].id).replace(this._unseen_regexp,'')), 'success');
		egw.json('mail.mail_ui.ajax_foldersubscription',[acc_id,folder,false])
			.sendRequest();
	},

	/**
	 * Onclick for node/foldername in subscription popup
	 *
	 * Used to (un)check node including all children
	 *
	 * @param {string} _id id of clicked node
	 * @param {et2_tree} _widget reference to tree widget
	 */
	subscribe_onclick: function(_id, _widget)
	{
		_widget.setSubChecked(_id, "toggle");
	},

	/**
	 * Edit a folder acl for account(s)
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	edit_acl: function(_action, _senders)
	{
		var mailbox = _senders[0].id.split('::');
		var folder = mailbox[1] || 'INBOX', acc_id = mailbox[0];
		this.egw.open_link('mail.mail_acl.edit&mailbox='+ btoa(folder)+'&acc_id='+acc_id, '_blank', '640x480');
	},

	/**
	 * Submit new selected folder back to server in order to read its acl's rights
	 */
	acl_folderChange: function ()
	{
		var mailbox = this.et2.getWidgetById('mailbox');

		if (mailbox)
		{
			if (mailbox.value.length > 0)
			{
				this.et2._inst.submit();
			}
		}
	},

	/**
	 * Edit a mail account
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	edit_account: function(_action, _senders)
	{
		var acc_id = parseInt(_senders[0].id);
		this.egw.open_link('mail.mail_wizard.edit&acc_id='+acc_id, '_blank', '740x670');
	},

	/**
	 * Set expandable fields (Folder, Cc and Bcc) based on their content
	 * - Only fields which have no content should get hidden
	 */
	compose_fieldExpander_init: function ()
	{
		var widgets = {
			cc:{
				widget:{},
				jQClass: '.mailComposeJQueryCc'
			},
			bcc:{
				widget:{},
				jQClass: '.mailComposeJQueryBcc'
			},
			folder:{
				widget:{},
				jQClass: '.mailComposeJQueryFolder'
			},
			replyto:{
				widget:{},
				jQClass: '.mailComposeJQueryReplyto'
			}};
		var actions = egw.preference('toggledOnActions', 'mail');
		actions = actions ? actions.split(',') : [];
		for(var widget in widgets)
		{
			var expanderBtn = widget + '_expander';
			widgets[widget].widget = this.et2.getWidgetById(widget);
			// Add expander button widget to the widgets object
			widgets[expanderBtn] = {widget:this.et2.getWidgetById(expanderBtn)};

			if (typeof widgets[widget].widget != 'undefined'
					&& typeof widgets[expanderBtn].widget != 'undefined'
					&& widgets[widget].widget.get_value().length == 0
					&& actions.indexOf(expanderBtn)<0)
			{
				widgets[expanderBtn].widget.set_disabled(false);
				jQuery(widgets[widget].jQClass).hide();
			}
		}
	},

	/**
	 * Control textArea size based on available free space at the bottom
	 *
	 */
	compose_resizeHandler: function()
	{
		// Do not resize compose dialog if it's running on mobile device
		// in this case user would be able to edit mail body by scrolling down,
		// which is more convenient on small devices. Also resize mailbody with
		// tinyMCE may causes performance regression, especially on devices with
		// very limited resources and slow proccessor.
		if (egwIsMobile()) return false;

		try {
			var bodyH = egw_getWindowInnerHeight();
			var textArea = this.et2.getWidgetById('mail_plaintext');
			var $headerSec = jQuery('.mailComposeHeaderSection');
			var content = this.et2.getArrayMgr('content').data;

			if (typeof textArea != 'undefined' && textArea != null)
			{
				if (textArea.getParent().disabled)
				{
					textArea = this.et2.getWidgetById('mail_htmltext');
				}
				// Tolerate values base on plain text or html, in order to calculate freespaces
				var textAreaDelta = textArea.id == "mail_htmltext"?20:40;

				// while attachments are in progress take progress visiblity into account
				// otherwise the attachment progress is finished and consider attachments list
				var delta =  textAreaDelta;

				var bodySize = (bodyH  - Math.round($headerSec.height() + $headerSec.offset().top) - delta);

				if (textArea.id != "mail_htmltext")
				{
					textArea.getParent().set_height(bodySize);
					textArea.set_height(bodySize);
				}
				else if (typeof textArea != 'undefined' && textArea.id == 'mail_htmltext')
				{
					if (textArea.editor)
					{
						jQuery(textArea.editor.editorContainer).height(bodySize);
						jQuery(textArea.editor.iframeElement).height(bodySize - (textArea.editor.editorContainer.getElementsByClassName('tox-toolbar')[0].clientHeight +
								textArea.editor.editorContainer.getElementsByClassName('tox-statusbar')[0].clientHeight));
					}
				}
				else
				{
					textArea.set_height(bodySize - 90);
				}
			}
		}
		catch(e) {
			// ignore errors causing compose to load twice
		}
	},

	/**
	 * Display Folder,Cc or Bcc fields in compose popup
	 *
	 * @param {jQuery event} event
	 * @param {widget object} widget clicked label (Folder, Cc or Bcc) from compose popup
	 *
	 */
	compose_fieldExpander: function(event,widget)
	{
		var expWidgets = {cc:{},bcc:{},folder:{},replyto:{}};
		for (var name in expWidgets)
		{
			expWidgets[name] = this.et2.getWidgetById(name+'_expander');
		}

		if (typeof widget !='undefined')
		{
			switch (widget.id)
			{
				case 'cc_expander':
					jQuery(".mailComposeJQueryCc").show();
					if (typeof expWidgets.cc !='undefined')
					{
						expWidgets.cc.set_disabled(true);
					}
					break;
				case 'bcc_expander':
					jQuery(".mailComposeJQueryBcc").show();
					if (typeof expWidgets.bcc !='undefined')
					{
						expWidgets.bcc.set_disabled(true);
					}
					break;
				case 'folder_expander':
					jQuery(".mailComposeJQueryFolder").show();
					if (typeof expWidgets.folder !='undefined')
					{
						expWidgets.folder.set_disabled(true);
					}
					break;
				case 'replyto_expander':
					jQuery(".mailComposeJQueryReplyto").show();
					if (typeof expWidgets.replyto !='undefined')
					{
						expWidgets.replyto.set_disabled(true);
					}
					break;
			}
		}
		else if (typeof widget == "undefined")
		{
			var widgets = {cc:{},bcc:{},folder:{},replyto:{}};

			for(var widget in widgets)
			{
				widgets[widget] = this.et2.getWidgetById(widget);

				if (widgets[widget].get_value() && widgets[widget].get_value().length)
				{
					switch (widget)
					{
						case 'cc':
							jQuery(".mailComposeJQueryCc").show();
							if (typeof expWidgets.cc != 'undefiend')
							{
								expWidgets.cc.set_disabled(true);
							}
							break;
						case 'bcc':
							jQuery(".mailComposeJQueryBcc").show();
							if (typeof expWidgets.bcc != 'undefiend')
							{
								expWidgets.bcc.set_disabled(true);
							}
							break;
						case 'folder':
							jQuery(".mailComposeJQueryFolder").show();
							if (typeof expWidgets.folder != 'undefiend')
							{
								expWidgets.folder.set_disabled(true);
							}
							break;
						case 'replyto':
							jQuery(".mailComposeJQueryReplyto").show();
							if (typeof expWidgets.replyto != 'undefiend')
							{
								expWidgets.replyto.set_disabled(true);
							}
							break;
					}
				}
			}
		}
		this.compose_resizeHandler();
	},

	/**
	 * Lock tree so it does NOT receive any more mouse-clicks
	 */
	lock_tree: function()
	{
		if (!document.getElementById('mail_folder_lock_div'))
		{
			var parent = jQuery('#mail-index_nm\\[foldertree\\]');
			var lock_div = jQuery(document.createElement('div'));
			lock_div.attr('id', 'mail_folder_lock_div')
				.addClass('mail_folder_lock');
			parent.prepend(lock_div);
		}
	},

	/**
	 * Unlock tree so it receives again mouse-clicks after calling lock_tree()
	 */
	unlock_tree: function()
	{
		jQuery('#mail_folder_lock_div').remove();
	},

	/**
	 * Called when tree opens up an account or folder
	 *
	 * @param {String} _id account-id[::folder-name]
	 * @param {et2_widget_tree} _widget
	 * @param {Number} _hasChildren 0 - item has no child nodes, -1 - item is closed, 1 - item is opened
	 */
	openstart_tree: function(_id, _widget, _hasChildren)
	{
		if (_id.indexOf('::') == -1 &&	// it's an account, not a folder in an account
			!_hasChildren)
		{
			this.lock_tree();
		}
		return true;	// allow opening of node
	},

	/**
	 * Called when tree opens up an account or folder
	 *
	 * @param {String} _id account-id[::folder-name]
	 * @param {et2_widget_tree} _widget
	 * @param {Number} _hasChildren 0 - item has no child nodes, -1 - item is closed, 1 - item is opened
	 */
	openend_tree: function(_id, _widget, _hasChildren)
	{
		if (_id.indexOf('::') == -1 &&	// it's an account, not a folder in an account
			_hasChildren == 1)
		{
			this.unlock_tree();
		}
	},

	/**
	 * Print a mail from list

	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	mail_print: function(_action, _senders)
	{
		var currentTemp = this.et2._inst.name;

		switch (currentTemp)
		{
			case 'mail.index':
				this.mail_prev_print(_action, _senders);
				break;
			case 'mail.display':
				this.mail_display_print();
		}

	},

	/**
	 * Print a mail from compose
	 * @param {stirng} _id id of new draft
	 */
	mail_compose_print:function (_id)
	{
		this.egw.open(_id,'mail','view','&print='+_id+'&mode=print');
	},

	/**
	 * Bind special handler on print media.
	 * -FF and IE have onafterprint event, and as Chrome does not have that event we bind afterprint function to onFocus
	 */
	print_for_compose: function()
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
	},

	/**
	 * Prepare display dialog for printing
	 * copies iframe content to a DIV, as iframe causes
	 * trouble for multipage printing
	 * @param {jQuery object} _iframe mail body iframe
	 * @returns {undefined}
	 */
	mail_prepare_print: function(_iframe)
	{
		var $mainIframe = _iframe || jQuery('#mail-display_mailDisplayBodySrc');
		var tmpPrintDiv = jQuery('#tempPrintDiv');

		if (tmpPrintDiv.length == 0 && tmpPrintDiv.children())
		{
			tmpPrintDiv = jQuery(document.createElement('div'))
							.attr('id', 'tempPrintDiv')
							.addClass('tmpPrintDiv');
			var notAttached = true;
		}

		if ($mainIframe)
		{
			window.setTimeout(function(){
				tmpPrintDiv[0].innerHTML = $mainIframe.contents().find('body').html();
			}, 600);
		}
		// Attach the element to the DOM after maniupulation
		if (notAttached) $mainIframe.after(tmpPrintDiv);
		tmpPrintDiv.find('#divAppboxHeader').remove();

	},

	/**
	 * Print a mail from Display
	 */
	mail_display_print: function ()
	{
		this.egw.message(this.egw.lang('Printing')+' ...', 'success');

		// Make sure the print happens after the content is loaded. Seems Firefox and IE can't handle timing for print command correctly
		setTimeout(function(){
			egw(window).window.print();
		},1000);
	},

	/**
	 * Print a mail from list
	 *
	 * @param {Object} _action
	 * @param {Object} _elems
	 *
	 */
	mail_prev_print: function (_action, _elems)
	{
		this.mail_open(_action, _elems, 'print');
	},

	/**
	 * Print a mail from list
	 *
	 * @param {egw object} _egw
	 * @param {widget object} _widget mail account selectbox
	 *
	 */
	vacation_change_account: function (_egw, _widget)
	{
		_widget.getInstanceManager().submit();
	},

	/**
	 * OnChange callback for recipients:
	 * - make them draggable
	 * - check if we have keys for recipients, if we compose an encrypted mail
	 **/
	recipients_onchange: function()
	{
		// if we compose an encrypted mail, check if we have keys for new recipient
		if (this.mailvelope_editor)
		{
			var self = this;
			this.mailvelopeGetCheckRecipients().catch(function(_err)
			{
				self.egw.message(_err.message, 'error');
			});
		}
		this.set_dragging_dndCompose();
	},

	/**
	 * Make recipients draggable
	 */
	set_dragging_dndCompose: function ()
	{
		var zIndex = 100;
		var dragItems = jQuery('div.ms-sel-item:not(div.ui-draggable)');
		dragItems.each(function(i,item){
				var $isErr = jQuery(item).find('.ui-state-error');
				if ($isErr.length > 0)
				{
					delete dragItems.splice(i,1);
				}
			});
		if (dragItems.length > 0)
		{
			dragItems.draggable({
				appendTo:'body',
				//Performance wise better to not add ui-draggable class to items since we are not using that class
				containment:'document',
				distance: 0,
				cursor:'move',
				cursorAt:{left:2},
				//cancel dragging on close button to avoid conflict with close action
				cancel:'.ms-close-btn',
				delay: '300',
				/**
				 * function to act on draggable item on revert's event
				 * @returns {Boolean} return true
				 */
				revert: function (){
					this.parent().find('.ms-sel-item').css('position','relative');
					var $input = this.parent().children('input');
					// Make sure input field not getting into second line after revert
					$input.width($input.width()-10);
					return true;
				},
				/**
				 * function to act as draggable starts dragging
				 *
				 * @param {type} event
				 * @param {type} ui
				 */
				start:function(event, ui)
				{
					var dragItem = jQuery(this);
					if (event.ctrlKey || event.metaKey)
					{
						dragItem.addClass('mailCompose_copyEmail')
								.css('cursor','copy');
					}
					dragItem.css ('z-index',zIndex++);
					dragItem.css('position','absolute');
				},
				/**
				 *
				 * @param {type} event
				 * @param {type} ui
				 */
				create:function(event,ui)
				{
					jQuery(this).css('css','move');
				}
			}).draggable('disable');
			window.setTimeout(function(){

				if(dragItems && dragItems.data() && typeof dragItems.data()['uiDraggable'] !== 'undefined') dragItems.draggable('enable');
			},100);
		}

	},

	/**
	 * Keyhandler for compose window
	 * Use this one so we can handle keys even on inputs
	 */
	init_keyHandler: function()
	{
		jQuery(document).on('keydown', function(e) {
			// Translate the given key code and make it valid
			var keyCode = e.which;
			keyCode = egw_keycode_translation_function(keyCode);
			keyCode = egw_keycode_makeValid(keyCode);

			// Only go on if this is a valid key code - call the key handler
			if (keyCode != -1)
			{
				if (egw_keyHandler(keyCode, e.shiftKey, e.ctrlKey || e.metaKey, e.altKey))
				{
					// If the key handler successfully passed the key event to some
					// sub component, prevent the default action
					e.preventDefault();
				}
			}
		});
	},

	/**
	* Check sharing mode and disable not available options
	*
	* @param {DOMNode} _node
	* @param {et2_widget} _widget
	*/
	check_sharing_filemode: function(_node, _widget)
	{
		if (!this.et2 || this.et2.getArrayMgr('content').getEntry('no_griddata')) return;
		if (!_widget) _widget = this.et2.getWidgetById('filemode');

		var extended_settings = _widget.get_value() != 'attach' && this.egw.app('stylite');
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
			Et2Dialog.alert(this.egw.lang('Be aware that all attachments will be sent as %1!', mode_label),
				this.egw.lang('Filemode has been switched to %1', mode_label),
				Et2Dialog.WARNING_MESSAGE);
			const content = this.et2.getArrayMgr('content');
			const attachments = this.et2.getWidgetById('attachments');
			for (let i in content.data.attachments)
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
	},

	/**
	 * Write / update compose window title with subject
	 *
	 * @param {DOMNode} _node
	 * @param {et2_widget} _widget
	 */
	subject2title: function(_node, _widget)
	{
		if (!_widget) _widget = this.et2.getWidgetById('subject');

		if (_widget && _widget.get_value())
		{
			document.title = _widget.get_value();
		}
	},

	/**
	 * Clear intervals stored in W_INTERVALS which assigned to window
	 */
	clearIntevals: function ()
	{
		for(var i=0;i<this.W_INTERVALS.length;i++)
		{
			clearInterval(this.W_INTERVALS[i]);
			delete this.W_INTERVALS[i];
		}
	},

	/**
	 * Window title getter function in order to set the window title
	 *
	 * @returns {string|undefined} window title
	 */
	getWindowTitle: function ()
	{
		var widget = {};
		switch(this.et2._inst.name)
		{
			case 'mail.display':
				widget = this.et2.getWidgetById('mail_displaysubject');
				if (widget) return widget.options.value;
				break;
			case 'mail.compose':
				widget = this.et2.getWidgetById('subject');
				if (widget) return widget.get_value();
				break;
		}
		return undefined;
	},

	/**
	 *
	 * @returns {undefined}
	 */
	prepareMailvelopePrint: function()
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
	},

	/**
	 * Mailvelope (clientside PGP) integration:
	 * - detect Mailvelope plugin and open "egroupware" keyring (app_base.mailvelopeAvailable and _mailvelopeOpenKeyring)
	 * - display and preview of encrypted messages (mailvelopeDisplay)
	 * - button to toggle between regular and encrypted mail (togglePgpEncrypt)
	 * - compose encrypted messages (mailvelopeCompose, compose_submitAction)
	 * - fix autosave and save as draft to store encrypted content (saveAsDraft)
	 * - fix inline reply to encrypted message to clientside decrypt message and add signature (mailvelopeCompose)
	 */

	/**
	 * Called on load of preview or display iframe, if mailvelope is available
	 *
	 * @param {Keyring} _keyring Mailvelope keyring to use
	 * @ToDo signatures
	 */
	mailvelopeDisplay: function(_keyring)
	{
		var self = this;
		var mailvelope = window.mailvelope;
		var iframe = jQuery('iframe#mail-display_mailDisplayBodySrc,iframe#mail-index_messageIFRAME');
		var armored = iframe.contents().find('td.td_display > pre').text().trim();

		if (armored == "" || armored.indexOf(this.begin_pgp_message) === -1) return;

		var container = iframe.parent()[0];
		var container_selector = container.id ? '#'+container.id : 'div.mailDisplayContainer';

		var options = {
			showExternalContent: this.egw.preference('allowExternalIMGs') == 1	// "1", or "0", undefined --> true or false
		};
		// get sender address, so Mailvelope can check signature
		var from_widget = this.et2.getWidgetById('FROM_0') || this.et2.getWidgetById('previewFromAddress');
		if (from_widget && from_widget.value)
		{
			options.senderAddress = from_widget.value.replace(/^.*<([^<>]+)>$/, '$1');
		}
		mailvelope.createDisplayContainer(container_selector, armored, _keyring, options).then(function()
		{
			// hide our iframe to give space for mailvelope iframe with encrypted content
			iframe.hide();
			self.prepareMailvelopePrint();
		},
		function(_err)
		{
			self.egw.message(_err.message, 'error');
		});
	},

	/**
	 * Editor object of active compose
	 *
	 * @var {Editor}
	 */
	mailvelope_editor: undefined,

	/**
	 * Called on compose, if mailvelope is available
	 *
	 * @param {Keyring} _keyring Mailvelope keyring to use
	 */
	mailvelopeCompose: function(_keyring)
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
	},

	/**
	 * Switch sending PGP encrypted mail on and off
	 *
	 * @param {object} _action toolbar action
	 */
	togglePgpEncrypt: function (_action)
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
	},

	/**
	 * Check if we have a key for all recipients
	 *
	 * @returns {Promise.<Array, Error>} Array of recipients or Error with recipients without key
	 */
	mailvelopeGetCheckRecipients: function()
	{
		// collect all recipients
		var recipients = this.et2.getWidgetById('to').get_value();
		recipients = recipients.concat(this.et2.getWidgetById('cc').get_value());
		recipients = recipients.concat(this.et2.getWidgetById('bcc').get_value());

		return this._super.call(this, recipients);
	},

	/**
	 * Set the relevant widget to toolbar actions and submit
	 *
	 * @param {object|boolean} _action toolbar action or boolean value to stop extra call on
	 * compose_integrated_submit
	 */
	compose_submitAction: function (_action)
	{
		if (this.compose_integrate_submit() && _action) return false;

		if (this.mailvelope_editor)
		{
			var self = this;
			this.mailvelopeGetCheckRecipients().then(function(_recipients)
			{
				return self.mailvelope_editor.encrypt(_recipients);
			}).then(function(_armored)
			{
				self.et2.getWidgetById('mimeType').set_value(false);
				self.et2.getWidgetById('mail_plaintext').set_disabled(false);
				self.et2.getWidgetById('mail_plaintext').set_value(_armored);
				self.et2._inst.submit();
			}).catch(function(_err)
			{
				self.egw.message(_err.message, 'error');
			});
			return false;
		}
		this.et2._inst.submit(null, 'Please wait while sending your mail');
	},

	/**
	 * This function runs before client submit (send) mail to server
	 * and takes care of mail integration modules to popup entry selection
	 * dialog to give user a choice to which entry of selected app the compose
	 * should be integereated.
	 * @param {int|boolean} _integIndex
	 *
	 * @returns {Boolean} return true if to_tracker is checked otherwise false
	 */
	compose_integrate_submit: function (_integIndex)
	{
		if (_integIndex == false) return false;
		var index = _integIndex || 0;
		var integApps = ['to_tracker', 'to_infolog', 'to_calendar'];
		var subject = this.et2.getWidgetById('subject');
		var toolbar = this.et2.getWidgetById('composeToolbar');
		var to_integrate_ids = this.et2.getWidgetById('to_integrate_ids');
		var integWidget= {};
		var self = this;

		integWidget = this.et2.getWidgetById(integApps[index]);
		if (toolbar.options.actions[integApps[index]] &&
				typeof toolbar.options.actions[integApps[index]]['mail_import'] != 'undefined' &&
				typeof toolbar.options.actions[integApps[index]]['mail_import']['app_entry_method'] != 'unefined')
		{
			var mail_import_hook = toolbar.options.actions[integApps[index]]['mail_import']['app_entry_method'];
			if (integWidget.get_value() == 'on')
			{
				var title = egw.lang('Select') + ' ' + egw.lang(integApps[index]) + ' ' + (egw.link_get_registry(integApps[index], 'entry') ? egw.link_get_registry(integApps[index], 'entry') : egw.lang('entry'));
				this.integrate_checkAppEntry(title, integApps[index].substr(3), subject.get_value(), '', mail_import_hook , function (args){

					var oldValue = to_integrate_ids.get_value() || [];
					to_integrate_ids.set_value([integApps[index] + ":" + args.entryid, ...oldValue]);
					index = index < integApps.length ? ++index : false;
					self.compose_integrate_submit(index);
				});
				return true;
			}
		}
		// the to_tracker action might not be presented because lack of app permissions
		else if(integApps[index] == "to_tracker" && !toolbar.options.actions[integApps[index]])
		{
			return false;
		}
		else if(index<integApps.length)
		{
			this.compose_integrate_submit(++index);
		}
		else
		{
			this.compose_submitAction(false);
		}

		// apply default font and -size before submitting to server for sending
		this.et2?.getWidgetById('mail_htmltext')?.applyDefaultFont();

		return false;
	},

	/**
	 * Set the selected checkbox action
	 *
	 * @param {type} _action selected toolbar action with checkbox
	 * @returns {undefined}
	 */
	compose_setToggle: function (_action)
	{
		var widget = this.et2.getWidgetById (_action.id);
		if (widget && typeof _action.checkbox != 'undefined' && _action.checkbox)
		{
			widget.set_value(_action.checked?"on":"off");
		}
	},

	/**
	 * Set the selected priority value
	 * @param {type} _action selected action
	 * @returns {undefined}
	 */
	compose_priorityChange: function (_action)
	{
		var widget = this.et2.getWidgetById ('priority');
		if (widget)
		{
			widget.set_value(_action.id);
		}
	},

	/**
	 * Triger relative widget via its toolbar identical action
	 * @param {type} _action toolbar action
	 */
	compose_triggerWidget:function (_action)
	{
		var widget = this.et2.getWidgetById(_action.id);
		if (widget)
		{
			switch(widget.id)
			{
				case 'uploadForCompose':
					document.getElementById('mail-compose_uploadForCompose').click();
					break;
				default:
					widget.click();
			}
		}
	},

	/**
	 * Save drafted compose as eml file into VFS
	 * @param {type} _action action
	 */
	compose_saveDraft2fm: function (_action)
	{
		var content = this.et2.getArrayMgr('content').data;
		var subject = this.et2.getWidgetById('subject');
		var elem = {0:{id:"", subject:""}};
		var self = this;
		if (typeof content != 'undefined' && content.lastDrafted && subject)
		{
			elem[0].id = content.lastDrafted;
			elem[0].subject = subject.get_value();
			this.mail_save2fm(_action, elem);
		}
		else // need to save as draft first
		{
			this.saveAsDraft(null, 'autosaving').then(function(){
				self.compose_saveDraft2fm(_action);
			}, function(){
				Et2Dialog.alert('You need to save the message as draft first before to be able to save it into VFS', 'Save to filemanager', 'info');
			});
		}
	},

	/**
	 * Folder Management, opens the folder magnt. dialog
	 * with the selected acc_id from index tree
	 *
	 * @param {egw action object} _action actions
	 * @param {object} _senders selected node
	 */
	folderManagement: function (_action,_senders)
	{
		var acc_id = parseInt(_senders[0].id);
		this.egw.open_link('mail.mail_ui.folderManagement&acc_id='+acc_id, '_blank', '720x580');
	},

	/**
	 * Show ajax-loader when the autoloading get started
	 *
	 * @param {type} _id item id
	 * @param {type} _widget tree widget
	 * @returns {Boolean}
	 */
	folderMgmt_autoloadingStart: function(_id, _widget)
	{
		return this.subscription_autoloadingStart (_id, _widget);
	},

	/**
	 * Revert back the icon after autoloading is finished
	 * @param {type} _id item id
	 * @param {type} _widget tree widget
	 * @returns {Boolean}
	 */
	folderMgmt_autoloadingEnd: function(_id, _widget)
	{
		return true;
	},

	/**
	 *
	 * @param {type} _ids
	 * @param {type} _widget
	 * @returns {undefined}
	 */
	folderMgmt_onSelect: function(_ids, _widget)
	{
		// Flag to reset selected items
		var resetSelection = false;

		var self = this;

		/**
		 * helper function to multiselect range of nodes in same level
		 *
		 * @param {string} _a start node id
		 * @param {string} _b end node id
		 * @param {string} _branch totall node ids in the level
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
	},

	/**
	 * Set enable/disable checkbox
	 *
	 * @param {object} _widget tree widget
	 * @param {string} _itemId item tree id
	 * @param {boolean} _stat - status to be set on checkbox true/false
	 */
	folderMgmt_setCheckbox: function (_widget, _itemId, _stat)
	{
		if (_widget)
		{
			_widget.input.setCheck(_itemId, _stat);
			_widget.input.setSubChecked(_itemId,_stat);
		}
	},

	/**
	 *
	 * @param {type} _id
	 * @param {type} _widget
	 * @TODO: Implement onCheck handler in order to select or deselect subItems
	 *	of a checked parent node
	 */
	folderMgmt_onCheck: function (_id, _widget)
	{
		var selected = _widget.input.getAllChecked();
		if (selected && selected.split(_widget.input.dlmtr).length > 5)
		{
			egw.message(egw.lang('If you would like to select multiple folders in one action, you can hold ctrl key then select a folder as start range and another folder within a same level as end range, all folders in between will be selected or unselected based on their current status.'), 'success');
		}
	},

	/**
	 * Detele button handler
	 * triggers longTask dialog and send delete operation url
	 *
	 */
	folderMgmt_deleteBtn: function ()
	{
		var tree = etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('tree');
		var menuaction= 'mail.mail_ui.ajax_folderMgmt_delete';

		var callbackDialog = function(_btn)
		{
			egw.appName='mail';
			if (_btn === Et2Dialog.YES_BUTTON)
			{
				if (tree)
				{
					var selFolders = tree.input.getAllChecked();
					if (selFolders)
					{
						var selFldArr = selFolders.split(tree.input.dlmtr);
						var msg = egw.lang('Deleting %1 folders in progress ...', selFldArr.length);
						Et2Dialog.long_task(function (_val, _resp)
						{
							console.log(_val, _resp);
							if (_val && _resp.type !== 'error')
							{
								var stat = [];
								var folderName = '';
								for (var i = 0; i < selFldArr.length; i++)
								{
									folderName = selFldArr[i].split('::');
									stat[selFldArr[i]] = folderName[1];
								}
								// delete the item from index folderTree
								egw.window.app.mail.mail_removeLeaf(stat);
							}
							else
							{
								// submit
								etemplate2.getByApplication('mail')[0].widgetContainer._inst.submit();
							}
						}, msg, egw.lang('Deleting folders'), menuaction, selFldArr, 'mail');
						return true;
					}
				}
			}
		};
		Et2Dialog.show_dialog(callbackDialog, this.egw.lang('Are you sure you want to delete all selected folders?'), this.egw.lang('Delete folder'), {},
			Et2Dialog.BUTTON_YES_NO, Et2Dialog.WARNING_MESSAGE, undefined, egw);
	},

	/**
	 * Spam Actions handler
	 *
	 * @param {object} _action egw action
	 * @param {object} _senders nm row
	 */
	spam_actions: function (_action, _senders)
	{
		var id,fromaddress,domain, email = '';
		var data = {};
		var items = [];
		var nm = this.et2.getWidgetById(this.nm_index);

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
				nm.controller._selectionMgr.resetSelection();
			}
			else
			{
				egw.message(_data[0]);
			}
		}).sendRequest(true);
	},

	spamTitan_setActionTitle: function (_action, _sender)
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
		switch (_action.id)
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
	},
	/**
	 * Implement mobile view
	 *
	 * @param {type} _action
	 * @param {type} _sender
	 */
	mobileView: function(_action, _sender)
	{
		// row id in nm
		var id = _sender[0].id;

		var defaultActions= {
			actions:['delete', 'forward','reply','flagged'], // default actions to display
			check:function(_action){
				for (var i=0;i<= this.actions.length;i++)
				{
					if (_action == this.actions[i]) return true;
				}
				return false;
			}
		};

		var content = {};
		var self = this;

		if (id){
			content = egw.dataGetUIDdata(id);
			content.data['toolbar'] = this.et2.getArrayMgr('sel_options').getEntry('toolbar');
			// Set default actions
			for(var action in content.data['toolbar'])
			{
				content.data.toolbar[action]['toolbarDefault'] = defaultActions.check(action);
			}
			// update local storage with added toolbar actions
			egw.dataStoreUID(id,content.data);
		}

		this.viewEntry(_action, _sender, true, function(etemplate){
			// et2 object in view
			var et2 = etemplate.widgetContainer;
			// iframe to load message
			var iframe = et2.getWidgetById('iframe');
			// toolbar widget
			var toolbar = et2.getWidgetById('toolbar');
			// attachments details title DOM node
			var $attachment = jQuery('.attachments span.et2_details_title');
			// details DOM
			var $details = jQuery('.et2_details.details');
			// Content
			var content = et2.getArrayMgr('content').data;

			// set the current selected row
			et2.mail_currentlyFocussed = id;

			if (content.attachmentsBlock.length>0 && content.attachmentsBlock[0].filename)
			{
				$attachment.text(self.egw.lang('%1 attachments', content.attachmentsBlock.length));
			}
			else
			{
				// disable attachments area if there's no attachments
				$attachment.parent().hide();
			}
			// disable the detials if there's no details
			if (!content.ccaddress && !content.additionaltoaddress) $details.hide();

			toolbar.set_actions(content.toolbar);
			var toaddressdetails = self.et2_view.widgetContainer.getWidgetById('toaddressdetails');
			if (toaddressdetails && content.additionaltoaddress)
			{
				toaddressdetails.set_value('... ' +content.additionaltoaddress.length + egw.lang(' more'));
				jQuery(toaddressdetails.getDOMNode()).off().on('click', function(){
					$details.find('.et2_details_toggle').click();
				});
			}

			// Request email body from server
			iframe.set_src(egw.link('/index.php',{menuaction:'mail.mail_ui.loadEmailBody',_messageID:id}));
			jQuery(iframe.getDOMNode()).on('load',function(){

				if (jQuery(this.contentWindow.document.body).find('#calendar-meeting').length > 0)
				{
					var frame = this;
					jQuery(this).show();
					// calendar meeting mails still need to be in iframe, therefore, we calculate the height
					// and set the iframe with a fixed height to be able to see all content without getting
					// scrollbar becuase of scrolling issue in iframe
					window.setTimeout(function(){jQuery(frame).height(frame.contentWindow.document.body.scrollHeight);}, 500);
				}
				else
				{
					self.resolveExternalImages(this.contentWindow.document);
					// Use prepare print function to copy iframe content into div
					// as we don't want to show content in iframe (scrolling problem).
					if (jQuery(this.contentWindow.document.body).find('#smimePasswordRequest').length == 0)
					{
						iframe.set_disabled(true);
						self.mail_prepare_print(jQuery(this));
					}
				}
			});
		});
	},

	/**
	 * Open smime certificate
	 *
	 * @param {type} egw
	 * @param {type} widget
	 * @returns {undefined}
	 */
	smimeSigBtn: function (egw, widget)
	{
		var url = '';
		if (this.mail_isMainWindow)
		{
			var content = this.egw.dataGetUIDdata(this.mail_currentlyFocussed);
			url = content.data.smimeSigUrl;
		}
		else
		{
			url = this.et2.getArrayMgr("content").getEntry('smimeSigUrl');
		}
		window.egw.openPopup(url,'700','400');
	},

	/**
	 * smime password dialog
	 *
	 * @param {string} _msg message
 	 */
	smimePassDialog: function (_msg)
	{
		var self = this;
		var pass_exp = egw.preference('smime_pass_exp', 'mail');
		et2_createWidget("dialog",
		{
			callback: function(_button_id, _value)
			{
				if (_button_id == 'send' && _value)
				{
					var pass = self.et2.getWidgetById('smime_passphrase');
					pass.set_value(_value.value);
					var toolbar = self.et2.getWidgetById('composeToolbar');
					toolbar.value = 'send';
					egw.set_preference('mail', 'smime_pass_exp', _value.pass_exp);
					self.compose_submitAction(false);
				}
			},
			title: egw.lang('Request for passphrase'),
			buttons: [
				{label: this.egw.lang("Send"), id: "send", "class": "ui-priority-primary", "default": true},
				{label: this.egw.lang("Cancel"), id: "cancel"}
			],
			value:{
				content:{
					value: '',
					message: _msg,
					'exp_min': pass_exp
			}},
			template: egw.webserverUrl+'/api/templates/default/password.xet',
			resizable: false
		}, et2_dialog._create_parent('mail'));
	},

	/**
	 * set attachments of smime message for mobile view
	 * @param {type} _attachments
	 */
	set_smimeAttachmentsMobile: function (_attachments)
	{
		var attachmentsBlock = this.et2_view.widgetContainer.getWidgetById('attachmentsBlock');
		var $attachment = jQuery('.et2_details.attachments');
		if (attachmentsBlock && _attachments.length > 0)
		{
			attachmentsBlock.set_value({content:_attachments});
			$attachment.show();
		}
	},

	/**
	 * Set attachments of smime message
	 *
	 * @param {object} _attachments
	 */
	set_smimeAttachments:function (_attachments)
	{
		if (egwIsMobile())
		{
			this.set_smimeAttachmentsMobile(_attachments);
			return;
		}
		var attachmentArea = this.et2.getWidgetById(egw(window).is_popup()?'mail_displayattachments':'attachmentsBlock');
		var content = this.et2.getArrayMgr('content');
		var mailPreview = this.et2.getWidgetById('mailPreviewContainer');
		if (attachmentArea && _attachments && _attachments.length > 0)
		{
			attachmentArea.getParent().set_disabled(false);
			content.data[attachmentArea.id] = _attachments;
			this.et2.setArrayMgr('contnet', content);
			attachmentArea.getDOMNode().classList.remove('loading');
			attachmentArea.set_value({content:_attachments});
			if (attachmentArea.id == 'attachmentsBlock')
			{
				var a_node = attachmentArea.getDOMNode();
				var m_node = mailPreview.getDOMNode();
				var offset = m_node.offsetTop - a_node.offsetTop;
				if (a_node.offsetTop + a_node.offsetHeight > m_node.offsetTop)
				{
					m_node.style.setProperty('top', m_node.offsetTop + offset+"px");
				}
			}
		}
		else
		{
			attachmentArea.getParent().set_disabled(true);
		}
	},
	/**
	 * This function helps to trigger the Push notification immidiately.
	 * @todo: Must be removed after socket push notification is implemented
	 */
	smimeAttachmentsCheckerInterval:function ()
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
	},

	/**
	 *
	 * @param {object} _data smime resolved certificate data
	 * @returns {undefined}
	 */
	set_smimeFlags: function (_data)
	{
		if (!_data) return;
		var self = this;
		var et2_object = egwIsMobile()? this.et2_view.widgetContainer: this.et2;
		var data = _data;
		var attachmentArea = et2_object.getWidgetById('previewAttachmentArea');
		if (attachmentArea) attachmentArea.getDOMNode().classList.remove('loading');
		var smime_signature = et2_object.getWidgetById('smime_signature');
		var smime_encryption = et2_object.getWidgetById('smime_encryption');
		var mail_container = egwIsMobile()? document.getElementsByClassName('mail-d-h1').next() :
				egw(window).is_popup() ? document.getElementsByClassName('mailDisplayContainer'):
				et2_object.getWidgetById('mailPreviewContainer').getDOMNode();
		smime_signature.set_disabled(!data.signed);
		smime_encryption.set_disabled(!data.encrypted);
		if (!data.signed)
		{
			this.smime_clear_flags([mail_container]);
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
			self.smime_certAddToContact(data,true);
		}).addClass('et2_clickable');
		jQuery(smime_encryption.getDOMNode()).off().on('click',function(){
			self.smime_certAddToContact(data, true);
		}).addClass('et2_clickable');
	},

	/**
	 * Reset flags classes and click handler
	 *
	 * @param {jQuery Object} _nodes
	 */
	smime_clear_flags: function (_nodes)
	{
		for(var i=0;i<_nodes.length;i++)
		{
			_nodes[i].classList.remove(...['smime_cert_verified',
				'smime_cert_notverified',
				'smime_cert_notvalid', 'smime_cert_unknownemail']);
		}
	},

	/**
	 * Inform user about sender's certificate and offers to add it into
	 * relevant contact in addressbook.
	 *
	 * @param {type} _metadata
	 * @param {boolean} _display if set to true will only show close button
	 */
	smime_certAddToContact: function (_metadata, _display)
	{
		if (!_metadata || _metadata.length < 1) return;
		var self = this;
		var content = jQuery.extend(true, {message:_metadata.msg}, _metadata);
		var buttons = [

			{label: this.egw.lang("Close"), id: "close"}
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
		et2_createWidget("dialog",
		{
			callback: function(_button_id, _value)
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
		}, et2_dialog._create_parent('mail'));
	},

	/**
	 * get preview pane state base on selected preference.
	 *
	 * It also set a right css class for vertical state.
	 *
	 * @returns {Boolean} returns true for visible Pane and false for hiding
	 */
	getPreviewPaneState: function ()
	{
		var previewPane = this.egw.preference('previewPane', 'mail');
		var nm = this.et2.getWidgetById(this.nm_index);
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
				nm.header.right_div.addClass('vertical_splitter');
		}
		return state;
	},

	/**
	 * Creates a dialog for changing meesage subject
	 *
	 * @param {object} _action|_widget
	 * @param {object} _sender|_content
	 */
	modifyMessageSubjectDialog: function (_action, _sender)
	{
		_sender = _sender ? _sender : [{id:this.mail_currentlyFocussed}];
		var id = (_sender && _sender.uid) ? _sender.row_id:
			_sender[0].id != 'nm'? _sender[0].id:_sender[1].id;
		var data = (_sender && _sender.uid) ? {data:_sender} : egw.dataGetUIDdata(id);
		var subject = data && data.data? data.data.subject : "";

		et2_createWidget("dialog",
		{
			callback: function(_button_id, _value) {
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
		}, et2_dialog._create_parent('mail'));
	},

	/**
	 * Pre set toggled actions
	 */
	preSetToggledOnActions: function ()
	{
		var actions = egw.preference('toggledOnActions', 'mail');
		var toolbar = this.et2.getWidgetById('composeToolbar');
		if (actions)
		{
			actions = actions.split(',');
			for (var i=0; i < actions.length; i++)
			{
				if (toolbar && toolbar.options.actions[actions[i]])
				{
					let d = document.getElementById('mail-compose_composeToolbar-'+actions[i]);
					if (d && toolbar._actionManager.getActionById(actions[i]).checkbox
							&& !toolbar._actionManager.getActionById(actions[i]).checked)
					{
						d.click();
					}
				}
				else
				{
					var widget = this.et2.getWidgetById(actions[i]);
					if (widget)
					{
						jQuery(widget.getDOMNode()).trigger('click');
					}
				}
			}
		}
	},

	/**
	 * Set predefined addresses for compose dialog
	 *
	 * @param {type} action
	 * @param {type} _senders
	 * @returns {undefined}
	 */
	set_predefined_addresses: function(action,_senders)
	{
		var pref_id = _senders[0].id.split('::')[0]+'_predefined_compose_addresses';
		var prefs = egw.preference(pref_id, 'mail');

		et2_createWidget("dialog",
		{
			callback: function (_button_id, _value)
			{
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
			value: {content: prefs || {}},
			minWidth: 410,
			template: egw.webserverUrl + '/mail/templates/default/predefinedAddressesDialog.xet?',
			resizable: false,
		}, et2_dialog._create_parent('mail'));
	},

	/**
	 * open
	 * @param _node
	 * @param _address
	 */
	onclickCompose(_node, _address)
	{
		egw.open_link('mailto:'+_address.value);
	}
});