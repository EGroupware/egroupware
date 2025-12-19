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
import type {IegwAppLocal} from "../../api/js/jsapi/egw_global";
import {egw} from "../../api/js/jsapi/egw_global";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";

export class MailCompose
{
	protected app : MailApp;
	private et2 : Et2Template
	private autosaveInterval : number;

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
			if(jQuery('.ms-editor-wrap').length === 0)
			{
				this.saveAsDraft(null, 'autosaving');
			}
		}, 120000);
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
		var boxAttachment = this.et2.getWidgetById('attachments');
		if (boxAttachment)
		{
			var groupbox = boxAttachment.getParent();
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
		if (_file_count && !jQuery.isEmptyObject(_event.data.getValue()))
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
		if (jQuery.isEmptyObject(_widget)) return;
		if (!jQuery.isEmptyObject(_widget.getValue()))
		{
			this.addAttachmentPlaceholder();
			this.et2.getInstanceManager().submit();
		}
	}

	/**
	 * Check sharing mode and disable not available options
	 *
	 * @param {DOMNode} _node
	 * @param {et2_widget} _widget
	 */
	checkSharingFilemode(_node, _widget)
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
		if (!jQuery.isEmptyObject(_widget))
		{
			if (typeof _widget.id !== 'undefined') var widgetId = _widget.id;
			switch (widgetId)
			{
				case 'mimeType':
					this.et2.getInstanceManager().submit();
					break;
				default:
					if (!jQuery.isEmptyObject(_widget.getValue()))
					{
						this.et2.getInstanceManager().submit();
					}
			}
		}
	}

	/**
	 * Set expandable fields (Folder, Cc and Bcc) based on their content
	 * - Only fields which have no content should get hidden
	 */
	fieldExpanderInit()
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
			},
			from:{
				widget:{},
				jQClass: '.mailComposeJQueryFrom'
			}
		};
		let actions = egw.preference('toggledOnActions', 'mail') ?? [];
		if (typeof actions === 'string')
			actions = actions ? actions.split(',') : [];
		//transform empty actions object to empty array
		if(!actions.indexOf)
			actions = Object.values(actions)
		for(var widget in widgets)
		{
			var expanderBtn = widget + '_expander';
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
				jQuery(widgets[widget].jQClass).hide();
			}
			else
			{
				jQuery(widgets[widget].jQClass).show();
			}
		}
	}

	/**
	 * Display Folder,Cc or Bcc fields in compose popup
	 *
	 * @param {jQuery event} event
	 * @param {widget object} widget clicked label (Folder, Cc or Bcc) from compose popup
	 *
	 */
	fieldExpander(event,widget)
	{
		const expWidgets = {cc:{},bcc:{},folder:{},replyto:{}};
		for (const name in expWidgets)
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
						//expWidgets.cc.set_disabled(true);
					}
					break;
				case 'bcc_expander':
					jQuery(".mailComposeJQueryBcc").show();
					if (typeof expWidgets.bcc !='undefined')
					{
						//expWidgets.bcc.set_disabled(true);
					}
					break;
				case 'folder_expander':
					jQuery(".mailComposeJQueryFolder").show();
					if (typeof expWidgets.folder !='undefined')
					{
						//expWidgets.folder.set_disabled(true);
					}
					break;
				case 'replyto_expander':
					jQuery(".mailComposeJQueryReplyto").show();
					if (typeof expWidgets.replyto !='undefined')
					{
						//expWidgets.replyto.set_disabled(true);
					}
					break;
				case 'from_expander':
					document.querySelector('.mailComposeJQueryFrom').style.display=''
					this.keepFromExpander = true;
					break;
			}
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
							jQuery(".mailComposeJQueryCc").show();
							if (typeof expWidgets.cc != 'undefined')
							{
								//expWidgets.cc.set_disabled(true);
							}
							break;
						case 'bcc':
							jQuery(".mailComposeJQueryBcc").show();
							if (typeof expWidgets.bcc != 'undefined')
							{
								//expWidgets.bcc.set_disabled(true);
							}
							break;
						case 'folder':
							jQuery(".mailComposeJQueryFolder").show();
							if (typeof expWidgets.folder != 'undefined')
							{
								//expWidgets.folder.set_disabled(true);
							}
							break;
						case 'replyto':
							jQuery(".mailComposeJQueryReplyto").show();
							if (typeof expWidgets.replyto != 'undefiend')
							{
								//expWidgets.replyto.set_disabled(true);
							}
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
		this.setDraggingDnDCompose();
	}

	/**
	 * Make recipients draggable
	 */
	protected setDraggingDnDCompose()
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
				revert(){
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
				start(event, ui)
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
				create(event,ui)
				{
					jQuery(this).css('css','move');
				}
			}).draggable('disable');
			window.setTimeout(function(){

				if(dragItems && dragItems.data() && typeof dragItems.data()['uiDraggable'] !== 'undefined') dragItems.draggable('enable');
			},100);
		}
	}

	/**
	 * Write / update compose window title with subject
	 *
	 * @param {DOMNode} _node
	 * @param {et2_widget} _widget
	 */
	subject2title(_node, _widget)
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
		var attgrid;
		attgrid = this.et2.getArrayMgr("content").getEntry('attachments')[widget.id.replace(/\[name\]/,'')];

		if (attgrid.uid && (attgrid.partID||attgrid.folder))
		{
			this.app.displayAttachment(tag_info, widget, true);
			return;
		}
		var get_param = {
			menuaction: 'mail.mail_compose.getAttachment',	// todo compose for Draft folder
			tmpname: attgrid.tmp_name,
			etemplate_exec_id: this.et2.getInstanceManager().etemplate_exec_id
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
	}

	/**
	 * Set the relevant widget to toolbar actions and submit
	 *
	 * @param {object|boolean} _action toolbar action or boolean value to stop extra call on
	 * compose_integrated_submit
	 */
	submitAction(_action)
	{
		let wait = Promise.resolve();
		if (_action && (wait = this.integrateSubmit()) && wait === true)
		{
			return false;
		}

		if (this.app.mailvelope_editor)
		{
			var self = this;
			wait = wait.then(() =>
			{
				this.app.mailvelopeGetCheckRecipients().then(function (_recipients)
				{
					return self.app.mailvelope_editor.encrypt(_recipients);
				}).then(function (_armored)
				{
					self.et2.getWidgetById('mimeType').set_value(false);
					self.et2.getWidgetById('mail_plaintext').set_disabled(false);
					self.et2.getWidgetById('mail_plaintext').set_value(_armored);
				}).catch(function (_err)
				{
					self.egw.message(_err.message, 'error');
				});
			});
			return false;
		}
		wait.then(() =>
		{
			this.et2.getInstanceManager().submit(null, 'Please wait while sending your mail');
		});
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
		let integWidget = {};
		const self = this;

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

				wait.push(new Promise((resolve) =>
				{
					this.app.integrate_checkAppEntry(title, integApps[index].substr(3), subject.get_value(), '', mail_import_hook, function (args)
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
		var widget = this.et2.getWidgetById (_action.id);
		if (widget && typeof _action.checkbox != 'undefined' && _action.checkbox)
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
		var widget = this.et2.getWidgetById ('priority');
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
		const helpers = this.et2.querySelector(".mailComposeHeaderSection");
		var widget = helpers.getWidgetById(_action.id);
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
		var content = this.et2.getArrayMgr('content').data;
		var subject = this.et2.getWidgetById('subject');
		var elem = {0:{id:"", subject:""}};
		var self = this;
		if (typeof content != 'undefined' && content.lastDrafted && subject)
		{
			elem[0].id = content.lastDrafted;
			elem[0].subject = subject.get_value();
			this.app.mail_save2fm(_action, elem);
		}
		else // need to save as draft first
		{
			this.saveAsDraft(null, 'autosaving').then(function(){
				self.saveDraft2fm(_action);
			}, function(){
				Et2Dialog.alert('You need to save the message as draft first before to be able to save it into VFS', 'Save to filemanager', 'info');
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
		debugger;
		var self = this;
		return new Promise(function(_resolve, _reject){
			var content = self.et2.getArrayMgr('content').data;
			var action = _action;
			if (_egw_action && _action !== 'autosaving')
			{
				action = _egw_action.id;
			}

			Object.assign(content, {...self.et2.getInstanceManager().getValues(self.et2, true), attachments: content.attachments});

			if (content)
			{
				// if we compose an encrypted message, we have to get the encrypted content
				if (self.app.mailvelope_editor)
				{
					self.app.mailvelope_editor.encrypt([]).then(function(_armored)
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
		if (jQuery.isEmptyObject(_responseData))
		{
			this.egw.message('Could not saved the message. Because, the response from server failed.', 'error');
			return false;
		}

		if (_responseData.success)
		{
			var content = this.et2.getArrayMgr('content');
			var lastDrafted = this.et2.getWidgetById('lastDrafted');
			var folderTree = typeof (opener || window)?.etemplate2?.getByApplication('mail')[0] != 'undefined' ?
				(opener || window).etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('nm[foldertree]') : null;
			const activeFolder = folderTree ? folderTree.getSelectedNode() : null;
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
	 * @param {stirng} _id id of new draft
	 */
	print(_id)
	{
		this.egw.open(_id,'mail','view','&print='+_id+'&mode=print');
	}
}