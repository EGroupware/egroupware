/**
 * mail - static javaScript functions
 *
 * @link http://www.egroupware.org
 * @author Stylite AG [info@stylite.de]
 * @copyright (c) 2013-2014 by Stylite AG <info-AT-stylite.de>
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/*egw:uses
	phpgwapi.jquery.jquery.base64;
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
	aclRights:['l','r','s','w','i','p','c','d','a'],

	/**
	 * Initialize javascript for this application
	 *
	 * @memberOf mail
	 */
	init: function() {
		this._super.apply(this,arguments);

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
				$j(nm).off('refresh');
			}
		}
		
		// Unregister client side cache
		this.egw.dataCacheUnregister('mail');

		delete this.et2_obj;
		// call parent
		this._super.apply(this, arguments);
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

		switch (_name)
		{
			case 'mail.sieve.vacation':
				this.vacationFilterStatusChange();
				break;
			case 'mail.index':
				var nm = this.et2.getWidgetById(this.nm_index);
				this.mail_isMainWindow = true;
				this.mail_disablePreviewArea(true);

				// Bind to nextmatch refresh to update folder status
				if(nm != null && (typeof jQuery._data(nm).events=='undefined'||typeof jQuery._data(nm).events.refresh == 'undefined'))
				{
					var self = this;
					$j(nm).on('refresh',function() {self.mail_refreshFolderStatus.call(self,undefined,undefined,false);});
				}
				var tree_wdg = this.et2.getWidgetById(this.nm_index+'[foldertree]');
				if (tree_wdg)
				{
					tree_wdg.set_onopenstart(jQuery.proxy(this.openstart_tree, this));
					tree_wdg.set_onopenend(jQuery.proxy(this.openend_tree, this));
				}
				break;
			case 'mail.display':
				var subject = this.et2.getWidgetById('mail_displaysubject');
				var body = this.et2.getWidgetById('mailDisplayBodySrc');
				this.mail_isMainWindow = false;
				this.mail_display();
				body.node.parentNode.style.top=subject.node.offsetTop+40+'px';
				var app_registry = egw.link_get_registry('mail');
				var w=870;
				if (typeof app_registry['view'] != 'undefined' && typeof app_registry['view_popup'] != 'undefined' )
				{
					var w_h =app_registry['view_popup'].split('x');
					if (w_h[1] == 'egw_getWindowOuterHeight()')
					{
						w_h[1] = (screen.availHeight>egw_getWindowOuterHeight()?screen.availHeight:egw_getWindowOuterHeight());
					}
				}
				window.resizeTo((w_h[0]?w_h[0]:870),(w_h[1]?w_h[1]:(screen.availHeight>egw_getWindowOuterHeight()?screen.availHeight:egw_getWindowOuterHeight())));

				// Register attachments for drag
				this.register_for_drag(
					this.et2.getArrayMgr("content").getEntry('mail_id'),
					this.et2.getArrayMgr("content").getEntry('mail_displayattachments')
				);
				break;
			case 'mail.compose':
				var that = this;
				this.mail_isMainWindow = false;
				this.compose_fieldExpander_hide();

				/* Control focus actions on subject to handle expanders properly.*/
				jQuery("#mail-compose_subject").on({
					focus:function(){
						that.compose_fieldExpander_hide();
						that.compose_fieldExpander();
					}
				});
				/*Trigger compose_resizeHandler after the CKEditor is fully loaded*/
				jQuery('#mail-compose').load ('load', function() {that.compose_resizeHandler();});

				this.compose_fieldExpander();
				break;
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
				break;

			case 'emailadmin':	// update tree with given mail account _id and _type
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
						tree.refreshItem(0);	// refresh root
						break;
				}
		}
	},

	/**
	 * Callback function for dataFetch caching.
	 *
	 * We only cache the first chunk (50 rows), and only if search filter is not set,
	 * but we cache this for every combination of folder, filter & filter2.
	 *
	 * @param {object} query_context Query information from egw.dataFetch()
	 * @returns {string|false} Cache key, or false to not cache
	 */
	nm_cache: function(query_context)
	{
		// Only cache first chunk of rows, if no search filter
		if((!query_context || !query_context.start) && query_context.count == 0 && !(
			!query_context.self._filters || query_context.self._filters.search || false)
		)
		{
			// Tree is not a child of nextmatch, so we need to
			// set nextmatch filter 'selectedFolder' to initial value, but in that
			// case, the normal structure is not fully set up yet.
			if(!query_context.self._filters.selectedFolder && query_context.self._widget)
			{
				var tree = query_context.self._widget;
				while(tree.getParent() != null)
				{
					// find root
					tree = tree.getParent();
				}
				if(tree)
				{
					tree = tree.getWidgetById('nm[foldertree]');
				}
				if(tree)
				{
					query_context.self._filters.selectedFolder = tree.getValue();
				}
			}
			// Make sure keys match, even if some filters are not defined
			return JSON.stringify({
				selectedFolder: query_context.self._filters.selectedFolder || '',
				filter: query_context.self._filters.filter || '',
				filter2: query_context.self._filters.filter2 || '',
				sort: query_context.self._filters.sort
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
		var h = egw().open( _id,'mail','view',_mode+'='+_id.replace(/=/g,"_") );
		egw(h).ready(function() {
			h.document.title = subject;
		});
		// THE FOLLOWING IS PROBABLY NOT NEEDED, AS THE UNEVITABLE PREVIEW IS HANDLING THE COUNTER ISSUE
		var messages = {};
		messages['msg'] = [_id];
		// When body is requested, mail is marked as read by the mail server.  Update UI to match.
		if (typeof dataElem != 'undefined' && typeof dataElem.data != 'undefined' && typeof dataElem.data.flags != 'undefined' && typeof dataElem.data.flags.read != 'undefined') dataElem.data.flags.read = 'read';
		if (typeof dataElem != 'undefined' && typeof dataElem.data != 'undefined' && (dataElem.data.class.indexOf('unseen') >= 0 || dataElem.data.class.indexOf('recent') >= 0))
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
	 * @param {String} window_name The name of an open content window.
	 * @param {object} content
	 *
	 * @description content Data to set into the window's fields
	 * content.to Addresses to add to the to line
	 * content.cc Addresses to add to the CC line
	 * content.bcc Addresses to add to the BCC line
	 *
	 * @return {boolean} Success
	 */
	setCompose: function(window_name, content)
	{
		// Get window
		var compose = window.open('', window_name);
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
				var widget = compose_et2[0].widgetContainer.getWidgetById(field);

				// Merge array values, replace strings
				var value = widget.getValue() || content[field];
				if(jQuery.isArray(value))
				{
					if(jQuery.isArray(content[field]))
					{
						value.concat(content[field]);
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
				egw.log("error", "Unable to set field %s to '%s' in window '%s'", field, content[field],window_name);
				success = false;
				continue;
			}
		}
		if (content['cc'] || content['bcc'])
		{
			this.compose_fieldExpander();
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
		if (typeof splitter == 'undefined' || splitter == null) return;
		var splitterDN = splitter.getDOMNode();

		if(splitter.isDocked())
		{
			this.mail_previewAreaActive = false;
		}

		//this.et2.getWidgetById('mailPreviewHeadersFrom').set_disabled(_value);
		//this.et2.getWidgetById('mailPreviewHeadersTo').set_disabled(_value);
		//this.et2.getWidgetById('mailPreviewHeadersDate').set_disabled(_value);
		//this.et2.getWidgetById('mailPreviewHeadersSubject').set_disabled(_value);
		this.et2.getWidgetById('mailPreview').set_disabled(_value);
		if (_value==true)
		{
			if (this.mail_previewAreaActive) splitter.dock();
			this.mail_previewAreaActive = false;
		}
		else
		{
			if (!this.mail_previewAreaActive) splitter.undock();
			this.mail_previewAreaActive = true;
		}
	},

	/**
	 * Create an expand on click box
	 *
	 * @param {object} _expContent an object with at least these elements
	 *					{build_children, data_one, data, widget, line}
	 *
	 * @param {object} _dataElem includes data of the widget which need to be expand
	 *
	 * @return _dataElem content of widgets
	 */
	url_email_expandOnClick: function (_expContent, _dataElem)
	{

		for(var j = 0; j < _expContent.length; j++)
		{
			var field = _expContent[j] || [];
			var content = _dataElem.data[field.data] || [];

			// Add in single address, if there
			if(typeof field.data_one != 'undefined')
			{
				content.unshift(_dataElem.data[field.data_one]);
				// Unique
				content = content.filter(function(value, index, self) {
					return self.indexOf(value) === index;
				});
			}

			// Disable whole box if there are none
			var line = this.et2.getWidgetById(field.line);
			if(line != null) line.set_disabled(content.length == 0);

			var widget = this.et2.getWidgetById(field.widget);
			if(widget == null) continue;
			$j(widget.getDOMNode()).removeClass('visible');

			// Programatically build the child elements
			if(field.build_children)
			{
				// Remove any existing
				var children = widget.getChildren();
				for(var i = children.length-1; i >= 0; i--)
				{
					children[i].destroy();
					widget.removeChild(children[i]);
				}
				if (content.length == 1 && typeof content[0] != 'undefined' && content[0])
				{
					content = content[0].split(',');
				}
				// Add for current record
				for(var i = 0; i < content.length; i++)
				{
					var value = content[i];
					var email = et2_createWidget('url-email',{id:widget.id+'_'+i, value:value,readonly:true},widget);
					email.loadingFinished();
				}
			}
			else
			{
				widget.set_value({content: content});
			}

			// Show or hide button, as needed
			line.iterateOver(function(button) {
				// Avoid binding to any child buttons
				if(button.getParent() != line) return;
				button.set_disabled(
					// Disable if only 1 address
					content.length <=1 || (
					// Disable if all content is visible
					$j(widget.getDOMNode()).innerWidth() >= widget.getDOMNode().scrollWidth &&
					$j(widget.getDOMNode()).innerHeight() >= widget.getDOMNode().scrollHeight)
				);
			},this,et2_button);
		}

		return _dataElem;
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
		var expand_content = [
			{build_children: true, data_one: 'FROM', data: 'FROM', widget: 'FROM', line: 'mailDisplayHeadersFrom'},
			{build_children: true,  data: 'SENDER', widget: 'SENDER', line: 'mailDisplayHeadersSender'},
			{build_children: true, data: 'TO', widget: 'TO', line: 'mailDisplayHeadersTo'},
			{build_children: true, data: 'CC', widget: 'CC', line: 'mailDisplayHeadersCc'},
			{build_children: true, data: 'BCC', widget:'BCC', line: 'mailDisplayHeadersBcc'}
		];

		if (typeof  content != 'undefiend')
		{
			dataElem.data = jQuery.extend(dataElem.data, content);

			this.url_email_expandOnClick(expand_content, dataElem);
			var toolbaractions = ((typeof dataElem != 'undefined' && typeof dataElem.data != 'undefined' && typeof dataElem.data.displayToolbaractions != 'undefined')?JSON.parse(dataElem.data.displayToolbaractions):undefined);
			if (toolbaractions) this.et2.getWidgetById('displayToolbar').set_actions(toolbaractions);
		}

		// Trigger print command if the mail oppend for printing porpuse
		if (window.location.search.search('&print=') >= 0)
		{
			var that = this;
			jQuery('#mail-display_mailDisplayBodySrc').bind('load',function(){that.mail_print();});
		}

	},

	/**
	 * mail_preview - implementation of the preview action
	 *
	 * @param nextmatch et2_nextmatch The widget whose row was selected
	 * @param selected Array Selected row IDs.  May be empty if user unselected all rows.
	 */
	mail_preview: function(selected, nextmatch) {
		// Empty values, just in case selected is empty (user cleared selection)
		//dataElem.data is populated, when available with fromaddress(string),toaddress(string),additionaltoaddress(array),ccaddress (array)
		var dataElem = {data:{subject:"",fromaddress:"",toaddress:"",ccaddress:"",date:"",attachmentsBlock:""}};
		if(typeof selected != 'undefined' && selected.length == 1)
		{
			var _id = this.mail_fetchCurrentlyFocussed(selected);
			dataElem = jQuery.extend(dataElem, egw.dataGetUIDdata(_id));
		}
		//get_class does not exist yet
		//var pAAClass = this.et2.getWidgetById('previewAttachmentArea').get_class();
		if (this.et2.getWidgetById('previewAttachmentArea') && typeof _id != 'undefined' && _id !='' && typeof dataElem !== 'undefined')
		{
			this.et2.getWidgetById('previewAttachmentArea').set_class('previewAttachmentArea');
		}
		else
		{
			// Leave if we're here and there is nothing selected, too many, or no data
			this.et2.getWidgetById('previewAttachmentArea').set_value({content:[]});
			this.et2.getWidgetById('previewAttachmentArea').set_class('previewAttachmentArea noContent mail_DisplayNone');
			var IframeHandle = this.et2.getWidgetById('messageIFRAME');
			IframeHandle.set_src('about:blank');
			this.mail_disablePreviewArea(true);
			return;
		}

		// Widget ID:data key map of widgets we can directly set from cached data
		var data_widgets = {
			'previewFromAddress':	'fromaddress',
			'previewDate':			'date',
			'previewSubject':		'subject'
		};

		// Set widget values from cached data
		for(var id in data_widgets)
		{
			var widget = this.et2.getWidgetById(id);
			if(widget == null) continue;
			widget.set_value(dataElem.data[data_widgets[id]] || "");
		}

		// Blank first, so we don't show previous email while loading
		var IframeHandle = this.et2.getWidgetById('messageIFRAME');
		IframeHandle.set_src('about:blank');

		// Set up additional content that can be expanded.
		// We add a new URL widget for each address, so they get all the UI
		// TO addresses have the first one split out, not all together
		// list of keys:
		var expand_content = [
			{build_children: true, data_one: 'toaddress', data: 'additionaltoaddress', widget: 'additionalToAddress', line: 'mailPreviewHeadersTo'},
			{build_children: true, data: 'ccaddress', widget: 'additionalCCAddress', line: 'mailPreviewHeadersCC'},
			{build_children: false, data: 'attachmentsBlock', widget:'previewAttachmentArea', line: 'mailPreviewHeadersAttachments'}
		];

		dataElem = this.url_email_expandOnClick(expand_content,dataElem);


		// Update the internal list of selected mails, if needed
		if(this.mail_selectedMails.indexOf(_id) < 0)
		{
			this.mail_selectedMails.push(_id);
		}
		this.mail_disablePreviewArea(false);

		// Request email body from server
		IframeHandle.set_src(egw.link('/index.php',{menuaction:'mail.mail_ui.loadEmailBody',_messageID:_id}));

		var messages = {};
		messages['msg'] = [_id];

		// When body is requested, mail is marked as read by the mail server.  Update UI to match.
		if (typeof dataElem != 'undefined' && typeof dataElem.data != 'undefined' && typeof dataElem.data.flags != 'undefined' && typeof dataElem.data.flags.read != 'undefined') dataElem.data.flags.read = 'read';
		if (typeof dataElem != 'undefined' && typeof dataElem.data != 'undefined' && typeof dataElem.data.class != 'undefined' && (dataElem.data.class.indexOf('unseen') >= 0 || dataElem.data.class.indexOf('recent') >= 0))
		{
			this.mail_removeRowClass(messages,'recent');
			this.mail_removeRowClass(messages,'unseen');
			// reduce counter without server roundtrip
			this.mail_reduceCounterWithoutServerRoundtrip();
			if (typeof dataElem.data.dispositionnotificationto != 'undefined' && dataElem.data.dispositionnotificationto &&
				typeof dataElem.data.flags.mdnsent == 'undefined' && typeof dataElem.data.flags.mdnnotsent == 'undefined')
			{
				var buttons = [
					{text: this.egw.lang("Yes"), id: "mdnsent"},
					{text: this.egw.lang("No"), id:"mdnnotsent"}
				];
				et2_dialog.show_dialog(function(_button_id, _value) {
					switch (_button_id)
					{
						case "mdnsent":
							egw.jsonq('mail.mail_ui.ajax_sendMDN',[messages]);
							egw.jsonq('mail.mail_ui.ajax_flagMessages',['mdnsent', messages, true]);
							return;
						case "mdnnotsent":
							egw.jsonq('mail.mail_ui.ajax_flagMessages',['mdnnotsent', messages, true]);
					}
				},
				this.egw.lang("The message sender has requested a response to indicate that you have read this message. Would you like to send a receipt?"),
				this.egw.lang("Confirm"),
				messages, buttons);
			}
			egw.jsonq('mail.mail_ui.ajax_flagMessages',['read', messages, false]);
		}
		// Pre-load next email already so user gets it faster
		// Browser will cache the file for us
/*
		var fO = egw_getObjectManager('mail',false,1).getObjectById(this.nm_index).getFocusedObject();
		var next = false;
		if (fO) next = fO.getNext(1);
		// Stop until we get all the details worked out - server marks as seen automatically
		if(false && next && next.id)
		{
			if(this.preview_preload.timer != null)
			{
				window.clearTimeout(this.preview_preload.timer);
			}
			// Wait 0.5s to avoid flooding server if user is scrolling through their mail
			this.preview_preload.timer = window.setTimeout( jQuery.proxy(function() {
				this.preview_preload.request = jQuery.get(
					egw.link('/index.php',{menuaction:'mail.mail_ui.loadEmailBody',_messageID:next.id})
				);
			},this),500);
		}
*/
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
		$j('body').one('click', list, function(ev) {
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
		var div = $j(document.createElement("div"))
			.css({
				position: 'absolute',
				top: '0px',
				left: '0px',
				width: '300px'
			});

		var data = _elems[0].data || {};

		var text = $j(document.createElement('div')).css({left: '30px', position: 'absolute'});
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
			this.et2.getWidgetById(this.nm_index+'[vacationnotice]').set_value('');
			this.et2.getWidgetById(this.nm_index+'[vacationrange]').set_value('');
		}
		else
		{
			this.et2.getWidgetById(this.nm_index+'[vacationnotice]').set_value(_data.vacationnotice);
			this.et2.getWidgetById(this.nm_index+'[vacationrange]').set_value(_data.vacationrange);
		}
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
		if (!currentexists) filter2.set_value('subject');
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
	 * Queues a refreshFolderList request for 10ms. Actually this will just execute the
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
			egw.jsonq('mail.mail_ui.ajax_setFolderStatus',[_folders], function (){self.unlock_tree()});
		}, 100);
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
	 * Check if Sieve is enabled on that account
	 *
	 * Sieve enabled is stored as data { acl: true/false } on account node.
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

		return node && node.data.acl && this.mail_CheckFolderNoSelect(_action,_senders,_currentNode);
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
			if (typeof _status[i]['olddesc'] !== 'undefined' && _status[i]['olddesc'] !== '#skip-user-interaction-message#') this.egw.message(this.egw.lang("Renamed Folder %1 to %2",_status[i]['olddesc'],_status[i]['desc']));
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
			if (typeof _status[i] !== 'undefined' && _status[i] !== '#skip-user-interaction-message#') this.egw.message(this.egw.lang("Removed Folder %1 ",_status[i]));
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
				this.egw.message(this.egw.lang("Reloaded Folder %1 ",typeof _status[i] == "string" ? _status[i].replace(this._unseen_regexp, '') : _status[i].text.replace(this._unseen_regexp, '')));
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
	 */
	mail_refreshMessageGrid: function(_isPopup) {
		if (typeof _isPopup == 'undefined') _isPopup = false;
		var nm;
		if (_isPopup && !this.mail_isMainWindow)
		{
			nm = window.opener.etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById(this.nm_index);
		}
		else
		{
			nm = this.et2.getWidgetById(this.nm_index);
		}
		nm.applyFilters(); // this should refresh the active folder
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
		if (calledFromPopup && this.mail_isMainWindow==false) window.close();
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
		if (res.length==4 && parseInt(res[0])!=NaN )
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
			if (message[3]) _foldernode = displayname = jQuery.base64Decode(message[3]);
		}

		// Tell server
		egw.json('mail.mail_ui.ajax_deleteMessages',[_msg,(typeof _action == 'undefined'?'no':_action)])
			.sendRequest(true);

		// Update list
		//var ids = [];
		//for (var i = 0; i < _msg['msg'].length; i++)
		//{
		//	ids.push(_msg['msg'][i].replace(/mail::/,''));
		//}
		if (_msg['all']) this.egw.refresh(this.egw.lang("deleted %1 messages in %2",(_msg['all']?egw.lang('all'):_msg['msg'].length),(displayname?displayname:egw.lang('current folder'))),'mail');//,ids,'delete');
		this.egw.message(this.egw.lang("deleted %1 messages in %2",(_msg['all']?egw.lang('all'):_msg['msg'].length),(displayname?displayname:egw.lang('current Folder'))));
	},

	/**
	 * Delete mails show result - called from the backend function for display of deletionmessages
	 * takes in all arguments
	 * @param _msg - message list
	 */
	mail_deleteMessagesShowResult: function(_msg)
	{
		// Update list
		var ids = [];
		for (var i = 0; i < _msg['msg'].length; i++)
		{
			ids.push(_msg['msg'][i].replace(/mail::/,''));
		}
		//this.egw.message(_msg['egw_message']);
		if (_msg['all'])
		{
			this.egw.refresh(_msg['egw_message'],'mail');
		}
		else
		{
			this.egw.refresh(_msg['egw_message'],'mail',ids,'delete');
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
			this.egw.message(this.egw.lang('canceled deletion due to userinteraction'));
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
	 * mail_emptyTrash
	 *
	 * @param {object} action
	 * @param {object} _senders
	 */
	mail_emptyTrash: function(action,_senders) {
		var server = _senders[0].iface.id.split('::');
		var activeFilters = this.mail_getActiveFilters();
		var self = this;
		
		this.egw.message(this.egw.lang('empty trash'));
		egw.json('mail.mail_ui.ajax_emptyTrash',[server[0], activeFilters['selectedFolder']? activeFilters['selectedFolder']:null],function(){self.unlock_tree()})
			.sendRequest(true);
	},

	/**
	 * mail_compressFolder
	 *
	 * @param {object} action
	 * @param {object} _senders
	 *
	 */
	mail_compressFolder: function(action,_senders) {
		this.egw.message(this.egw.lang('compress folder'));
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
		this.egw.message(this.egw.lang('Connect to Profile %1',_widget.getSelectedLabel().replace(this._unseen_regexp, '')));

		this.lock_tree();
		egw.json('mail.mail_ui.ajax_changeProfile',[folder, getFolders], jQuery.proxy(function() {
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
			this.egw.message(myMsg);
		}

		// Update non-grid
		this.mail_refreshFolderStatus(_folder,'forced',false,false);
		this.mail_refreshQuotaDisplay(server[0]);
		this.mail_preview();
		if (server[0]!=previousServer[0])
		{
			this.mail_callRefreshVacationNotice(server[0]);
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
		var that = this;
		var rvMain = false;
		if (obj_manager && _elems.length>1 && obj_manager.getAllSelected())
		{
			if (_confirm)
			{
				var buttons = [
					{text: this.egw.lang("Yes"), id: "all", class: "ui-priority-primary", "default": true},
					{text: this.egw.lang("Cancel"), id:"cancel"}
				];
				var messageToDisplay = '';
				switch (_action.id)
				{
					case "unlabel":
					case "label1":
					case "label2":
					case "label3":
					case "label4":
					case "label5":
					case "flagged":
					case "read":
					case "undelete":
						messageToDisplay = this.egw.lang("Do you really want to toggle flag %1 for ALL messages in the current view?",this.egw.lang(_action.id))+" ";
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
				return et2_dialog.show_dialog(function(_button_id) {
					var rv = false;
					switch (_button_id)
					{
						case "all":
							rv = true;
							break;
						case "cancel":
							rv = 'cancel';
					}
					if (rv !="cancel") that.lock_tree();
					switch (_action.id)
					{
						case "delete":
							that.mail_callDelete(_action, _elems,rv);
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
				_action.id, buttons);
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
			return obj_manager.manager.data.nextmatch.activeFilters;
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
		var do_nmactions = true;
		var msg;
		var ftree;
		var _folder;
		if (typeof _allMessagesChecked=='undefined') _allMessagesChecked=false;
		if (_action.id=='read')
		{
			ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
			var _foldernode = ftree.getSelectedNode();
			_folder = _foldernode.id;
		}
		if (typeof _elems == 'undefined'|| _elems.length==0)
		{
			do_nmactions = false;//indicates that this action is probably a popup?
			if (this.et2.getArrayMgr("content").getEntry('mail_id'))
			{
				msg = {};
				msg['msg'] = [this.et2.getArrayMgr('content').getEntry('mail_id') || ''];
			}
			if ((typeof _elems == 'undefined'|| _elems.length==0) && this.mail_isMainWindow)
			{
				if (this.mail_currentlyFocussed)
				{
					msg = {};
					msg['msg'] = [this.mail_currentlyFocussed];
					_elems = msg;
					do_nmactions = true;// is triggered from preview
				}
			}
		}

		var classToProcess = _action.id;
		if (_action.id=='read') classToProcess='seen';
		else if (_action.id=='label1') classToProcess='labelone';
		else if (_action.id=='label2') classToProcess='labeltwo';
		else if (_action.id=='label3') classToProcess='labelthree';
		else if (_action.id=='label4') classToProcess='labelfour';
		else if (_action.id=='label5') classToProcess='labelfive';

		if (do_nmactions)
		{
			msg = this.mail_getFormData(_elems);
			msg['all'] = _allMessagesChecked;
			if (msg['all']=='cancel') return false;
			msg['activeFilters'] = this.mail_getActiveFilters(_action);
			if (_action.id.substring(0,2)=='un') {
				//old style, only available for undelete and unlabel (no toggle)
				if ( _action.id=='unlabel') // this means all labels should be removed
				{
					var labels = ['labelone','labeltwo','labelthree','labelfour','labelfive'];
					for (var i=0; i<labels.length; i++)	this.mail_removeRowClass(_elems,labels[i]);
					this.mail_flagMessages(_action.id,msg,(do_nmactions?false:true));
				}
				else
				{
					this.mail_removeRowClass(_elems,_action.id.substring(2));
					this.mail_setRowClass(_elems,_action.id);
					this.mail_flagMessages(_action.id,msg,(do_nmactions?false:true));
				}
			}
			else
			{
				var msg_set = {msg:[]};
				var msg_unset = {msg:[]};
				var dataElem;
				var flags;
				var classes = '';
				for (var i=0; i<msg.msg.length; i++)
				{
					dataElem = egw.dataGetUIDdata(msg.msg[i]);
					if(typeof dataElem.data.flags == 'undefined')
					{
						dataElem.data.flags = {};
					}
					flags = dataElem.data.flags;
					classes = dataElem.data.class || "";
					classes = classes.split(' ');
					// since we toggle we need to unset the ones already set, and set the ones not set
					// flags is data, UI is done by class, so update both
					// Flags are there or not, class names are flag or 'un'+flag
					if(classes.indexOf(classToProcess) >= 0)
					{
						classes.splice(classes.indexOf(classToProcess),1);
					}
					if(classes.indexOf('un' + classToProcess) >= 0)
					{
						classes.splice(classes.indexOf('un' + classToProcess),1);
					}
					if (flags[_action.id])
					{
						msg_unset['msg'].push(msg.msg[i]);
						classes.push('un'+classToProcess);
						delete flags[_action.id];
					}
					else
					{
						msg_set['msg'].push(msg.msg[i]);
						flags[_action.id] = _action.id;
						classes.push(classToProcess);
					}

					// Update cache & call callbacks - updates list
					dataElem.data.class = classes.join(' ');
					egw.dataStoreUID(msg.msg[i],dataElem.data);
				}

				// Notify server of changes
				if (msg_unset['msg'] && msg_unset['msg'].length)
				{
					if (!msg['all']) this.mail_flagMessages('un'+_action.id,msg_unset);
				}
				if (msg_set['msg'] && msg_set['msg'].length)
				{
					if (!msg['all']) this.mail_flagMessages(_action.id,msg_set);
				}
				//server must do the toggle, as we apply to ALL, not only the visible
				if (msg['all']) this.mail_flagMessages(_action.id,msg);
				// No further update needed, only in case of read, the counters should be refreshed
				if (_action.id=='read') this.mail_refreshFolderStatus(_folder,'thisfolderonly',false,true);
				return;
			}
		}
		else
		{
			this.mail_flagMessages(_action.id,msg,(do_nmactions?false:true));
		}
		// only refresh counter. not grid as the ajaxmethod is called asyncronously
		// on flagging, only seen/unseen has effect on counterdisplay
		if (_action.id=='read') this.mail_refreshFolderStatus(_folder,'thisfolderonly',false,true);
		//this.mail_refreshFolderStatus();
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
		egw_openWindowCentered(_url,'mail_display_headerLines','870','600',window.outerWidth/2,window.outerHeight/2);
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
		//alert('mail_save('+_elems[0].id+')');
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=mail.mail_ui.saveMessage';	// todo compose for Draft folder
		url += '&id='+_elems[0].id;
		//window.open(url,'_blank','dependent=yes,width=100,height=100,scrollbars=yes,status=yes');
		document.location = url;
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
			mailid = mailida[widget.id.replace(/\[name\]/,'')];
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
				//document.location = url;
				//return;
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
		var url = window.egw_webserverUrl+'/index.php?';
		var width;
		var height;
		var windowName ='mail';
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
				url += 'menuaction=mail.mail_compose.getAttachment';	// todo compose for Draft folder
				url += '&filename='+attgrid.file;
				url += '&tmpname='+attgrid.tmp_name;
				url += '&name='+attgrid.name;
				//url += '&size='+attgrid.size;
				url += '&type='+attgrid.type;

				windowName = windowName+'displayAttachment_'+attgrid.file.replace(/\//g,"_");
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
/*
				url += 'menuaction=mail.mail_ui.displayMessage';	// todo compose for Draft folder
				url += '&id='+mailid;
				url += '&part='+attgrid.partID;
				url += '&is_winmail='+attgrid.winmailFlag;
				windowName = windowName+'displayMessage_'+mailid+'_'+attgrid.partID;
				width = 870;
				height = egw_getWindowOuterHeight();
				break;
*/
			default:
				url += 'menuaction=mail.mail_compose.getAttachment';	// todo compose for Draft folder
				url += '&filename='+attgrid.file;
				url += '&tmpname='+attgrid.tmp_name;
				url += '&name='+attgrid.name;
				//url += '&size='+attgrid.size;
				url += '&type='+attgrid.type;
				url += '&mode='+'save';

				windowName = windowName+'displayAttachment_'+attgrid.file.replace(/\//g,"_");
				width = 870;
				height = 600;
				//document.location = url;
				//return;
		}
		egw_openWindowCentered(url,windowName,width,height);
	},

	saveAttachment: function(tag_info, widget)
	{
		var mailid;
		var attgrid;
		if (this.mail_isMainWindow)
		{
			mailid = this.mail_currentlyFocussed;//this.et2.getArrayMgr("content").getEntry('mail_id');
			var p = widget.getParent();
			var cont = p.getArrayMgr("content").data;
			attgrid = cont[widget.id.replace(/\[save\]/,'')];
		}
		else
		{
			mailid = this.et2.getArrayMgr("content").getEntry('mail_id');
			attgrid = this.et2.getArrayMgr("content").getEntry('mail_displayattachments')[widget.id.replace(/\[save\]/,'')];
		}
		var url = window.egw_webserverUrl+'/index.php?';
		var width;
		var height;
		var windowName ='mail';
		url += 'menuaction=mail.mail_ui.getAttachment';	// todo compose for Draft folder
		url += '&mode=save';
		url += '&id='+mailid;
		url += '&part='+attgrid.partID;
		url += '&is_winmail='+attgrid.winmailFlag;
		document.location = url;
	},

	saveAllAttachmentsToZip: function(tag_info, widget)
	{
		var mailid;
		var attgrid;
		if (this.mail_isMainWindow)
		{
			mailid = this.mail_currentlyFocussed;//this.et2.getArrayMgr("content").getEntry('mail_id');
			var p = widget.getParent();
			var cont = p.getArrayMgr("content").data;
			attgrid = cont[widget.id.replace(/\[save\]/,'')];
		}
		else
		{
			mailid = this.et2.getArrayMgr("content").getEntry('mail_id');
			attgrid = this.et2.getArrayMgr("content").getEntry('mail_displayattachments')[widget.id.replace(/\[save\]/,'')];
		}
		var url = window.egw_webserverUrl+'/index.php?';
		var width;
		var height;
		var windowName ='mail';
		url += 'menuaction=mail.mail_ui.download_zip';	// todo compose for Draft folder
		url += '&mode=save';
		url += '&id='+mailid;
		document.location = url;
	},

	saveAttachmentToVFS: function(tag_info, widget)
	{
		var mailid;
		var attgrid;
		if (this.mail_isMainWindow)
		{
			mailid = this.mail_currentlyFocussed;//this.et2.getArrayMgr("content").getEntry('mail_id');
			var p = widget.getParent();
			var cont = p.getArrayMgr("content").data;
			attgrid = cont[widget.id.replace(/\[saveAsVFS\]/,'')];
		}
		else
		{
			mailid = this.et2.getArrayMgr("content").getEntry('mail_id');
			attgrid = this.et2.getArrayMgr("content").getEntry('mail_displayattachments')[widget.id.replace(/\[saveAsVFS\]/,'')];
		}
		var url = window.egw_webserverUrl+'/index.php?';
		var width=640;
		var height=570;
		var windowName ='mail';
		url += 'menuaction=filemanager.filemanager_select.select';	// todo compose for Draft folder
		url += '&mode=saveas';
		url += '&id='+mailid+'::'+attgrid.partID+'::'+attgrid.winmailFlag;
		url += '&name='+attgrid.filename;
		url += '&type='+attgrid.type.toLowerCase();
		url += '&method=mail.mail_ui.vfsSaveAttachment';
		url += '&label='+egw.lang('Save');
		egw_openWindowCentered(url,windowName,width,height);
	},

	saveAllAttachmentsToVFS: function(tag_info, widget)
	{
		var mailid;
		var attgrid;
		if (this.mail_isMainWindow)
		{
			mailid = this.mail_currentlyFocussed;//this.et2.getArrayMgr("content").getEntry('mail_id');
			var p = widget.getParent();
			attgrid = p.getArrayMgr("content").data;
		}
		else
		{
			mailid = this.et2.getArrayMgr("content").getEntry('mail_id');
			attgrid = this.et2.getArrayMgr("content").getEntry('mail_displayattachments');
		}
		var url = window.egw_webserverUrl+'/index.php?';
		var width=640;
		var height=570;
		var windowName ='mail';
		url += 'menuaction=filemanager.filemanager_select.select';	// todo compose for Draft folder
		url += '&mode=select-dir';
		url += '&method=mail.mail_ui.vfsSaveAttachment';
		url += '&label='+egw.lang('Save all');
		for (var i=0;i<attgrid.length;i++)
		{
			if (attgrid[i] != null) url += '&id['+i+']='+mailid+'::'+attgrid[i].partID+'::'+attgrid[i].winmailFlag+'::'+attgrid[i].filename;
		}
		egw_openWindowCentered(url,windowName,width,height);
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
		var _id = _elems[0].id;
		var dataElem = egw.dataGetUIDdata(_id);
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=filemanager.filemanager_select.select';	// todo compose for Draft folder
		url += '&mode=saveas';
		var filename =dataElem.data.subject.replace(/[\f\n\t\v/\\:*#?<>\|]/g,"_");
		url += '&name='+encodeURIComponent(filename+'.eml');
		url += '&mime=message'+encodeURIComponent('/')+'rfc822';
		url += '&method=mail.mail_ui.vfsSaveMessage';
		url += '&id='+_elems[0].id;
		url += '&label=Save';
		//window.open(url,'_blank','dependent=yes,width=100,height=100,scrollbars=yes,status=yes')
		//document.location = url;
		egw_openWindowCentered(url,'vfs_save_message_'+_elems[0].id,'640','570',window.outerWidth/2,window.outerHeight/2);

	},

	/**
	 * Save message as InfoLog
	 *
	 * @param _action
	 * @param _elems _elems[0].id is the row-id
	 */
	mail_infolog: function(_action, _elems)
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
			if (typeof _action.data.width == 'undefined' && typeof _action.data.height == 'undefined' && !(typeof _action.data.event == 'undefined' &&typeof _action.data.event.popup == 'undefined'))
			{
				if (_action.data.event.popup)
				{
					var app_registry = _action.data.event.popup;
				}
				else
				{
					var app_registry = egw.link_get_registry('infolog');//this.appname);
				}
				if (typeof app_registry['edit'] != 'undefined' && typeof app_registry['edit_popup'] != 'undefined' )
				{
					var w_h =app_registry['edit_popup'].split('x');
				}
			}
		}
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=infolog.infolog_ui.import_mail';	// todo compose for Draft folder
		url += '&rowid='+_elems[0].id;
		egw_openWindowCentered(url,'import_mail_'+_elems[0].id,(_action.data.width?_action.data.width:w_h[0]),(_action.data.height?_action.data.height:w_h[1]));
	},

	/**
	 * Save message as ticket
	 *
	 * @param _action _action.id is 'read', 'unread', 'flagged' or 'unflagged'
	 * @param _elems
	 */
	mail_tracker: function(_action, _elems)
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
			if (typeof _action.data.width == 'undefined' && typeof _action.data.height == 'undefined' && !(typeof _action.data.event == 'undefined' &&typeof _action.data.event.popup == 'undefined'))
			{
				if (_action.data.event.popup)
				{
					var app_registry = _action.data.event.popup;
				}
				else
				{
					var app_registry = egw.link_get_registry('tracker');//this.appname);
				}
				if (typeof app_registry['add'] != 'undefined' && typeof app_registry['add_popup'] != 'undefined' )
				{
					var w_h =app_registry['add_popup'].split('x');
				}
			}
		}
		//alert('mail_tracker('+_elems[0].id+')');
		var url = window.egw_webserverUrl+'/index.php?';
		url += 'menuaction=tracker.tracker_ui.import_mail';	// todo compose for Draft folder
		url += '&rowid='+_elems[0].id;
		egw_openWindowCentered(url,'import_tracker_'+_elems[0].id,(_action.data.width?_action.data.width:w_h[0]),(_action.data.height?_action.data.height:w_h[1]));
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
				if (_actionObjects[i].id.length>0)
				{
					var dataElem = $j(_actionObjects[i].iface.getDOMNode());
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
				dataElem.data.class += ' ' + _class;

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
					var dataElem = $j(_actionObjects[i].iface.getDOMNode());
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
				var classes = dataElem.data.class || "";
				classes = classes.split(' ');
				if(classes.indexOf(_class) >= 0)
				{
					classes.splice(classes.indexOf(_class),1);
					dataElem.data.class = classes.join(' ');

					// Update record, which updates all listeners (including nextmatch)
					egw.dataStoreUID(mail_uid,dataElem.data);
				}
			}
		}
	},

	// Tree widget stubs
	/**
	 * mail_dragStart - displays information while dragging
	 *
	 * @param action
	 * @param _senders - the representation of the elements dragged
	 * @return the ddhelper
	 */
	mail_dragStart: function(action,_senders) {
		return $j("<div class=\"ddhelper\">" + _senders.length + " Mails selected </div>");
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
		// TODO: Write move/copy function which cares about doing the same stuff
		// as the "onNodeSelect" function!
		messages['all'] = _allMessagesChecked;
		if (messages['all']=='cancel') return false;
		if (messages['all']) messages['activeFilters'] = this.mail_getActiveFilters(_action);
		var self = this;
		egw.json('mail.mail_ui.ajax_copyMessages',[target, messages, 'move'], function(){self.unlock_tree();})
			.sendRequest();
		var nm = this.et2.getWidgetById(this.nm_index);
		this.mail_setRowClass(_senders,'deleted');
		// Server response contains refresh
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
			{text: this.egw.lang("Add"), id: "add", class: "ui-priority-primary", "default": true},
			{text: this.egw.lang("Cancel"), id:"cancel"}
		];
		et2_dialog.show_prompt(function(_button_id, _value) {
			var NewFolderName = null;
			if (_value.length>0) NewFolderName = _value;
			//alert(NewFolderName);
			if (NewFolderName && NewFolderName.length>0)
			{
				switch (_button_id)
				{
					case "add":
						egw.json('mail.mail_ui.ajax_addFolder',[_senders[0].id, NewFolderName])
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
			{text: this.egw.lang("Rename"), id: "rename", class: "ui-priority-primary", image: 'edit', "default": true},
			{text: this.egw.lang("Cancel"), id:"cancel"}
		];
		et2_dialog.show_prompt(function(_button_id, _value) {
			var NewFolderName = null;
			if (_value.length>0) NewFolderName = _value;
			//alert(NewFolderName);
			if (NewFolderName && NewFolderName.length>0)
			{
				switch (_button_id)
				{
					case "rename":
						egw.json('mail.mail_ui.ajax_renameFolder',[_senders[0].id, NewFolderName])
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
		// Some UI feedback while the folder is moved - using just the iface DOMNode would
		// put the load image in every row
		var load_node = $j(destination.iface.getDOMNode()).closest('td').prev()
			.addClass('loading');

		for(var i = 0; i < _senders.length; i++)
		{
			egw.jsonq('mail.mail_ui.ajax_MoveFolder',[_senders[i].id, destination.id],
				// Move is done (successfully or not), remove loading
				function() {load_node.removeClass('loading');}
			);
		}
	},

	/**
	 * mail_DeleteFolder - implementation of the DeleteFolder action of right click options on the tree
	 *
	 * @param _action
	 * @param _senders - the representation of the tree leaf to be manipulated
	 */
	mail_DeleteFolder: function(_action,_senders) {
		//action.id == 'delete'
		//_senders.iface.id == target leaf / leaf to edit
		var ftree = this.et2.getWidgetById(this.nm_index+'[foldertree]');
		var OldFolderName = ftree.getLabel(_senders[0].id).replace(this._unseen_regexp,'');
		var buttons = [
			{text: this.egw.lang("Yes"), id: "delete", class: "ui-priority-primary", "default": true},
			{text: this.egw.lang("Cancel"), id:"cancel"}
		];
		et2_dialog.show_dialog(function(_button_id, _value) {
			switch (_button_id)
			{
				case "delete":
					egw.json('mail.mail_ui.ajax_deleteFolder',[_senders[0].id])
						.sendRequest(true);
					return;
				case "cancel":
			}
		},
		this.egw.lang("Do you really want to DELETE Folder %1 ?",OldFolderName)+" "+(ftree.hasChildren(_senders[0].id)?this.egw.lang("All subfolders will be deleted too, and all messages in all affected folders will be lost"):this.egw.lang("All messages in the folder will be lost")),
		this.egw.lang("DELETE Folder %1 ?",OldFolderName),
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
//			var request = new egw_json_request('mail_ui::ajax_importMessage', ['upload', widget.getValue(), _path], this);
//			widget.set_value('');
//			request.sendRequest();//false, this._upload_callback, this);
			this.et2_obj.submit();
		}
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
	* @param {window object} _window
	*/
	submitOnChange: function(_egw, _widget, _window) {
		if (!jQuery.isEmptyObject(_widget))
		{
			if (!jQuery.isEmptyObject(_widget.getValue()))
			{
				this.et2_obj.submit();
			}
		}
	},

	/**
	* Save as Draft (VFS)
	*
	* @param {egw object} _egw
	* @param {widget object} _widget
	* @param {window object} _window
	*/
	saveAsDraft: function(_egw, _widget, _window)
	{
		this.et2_obj.submit();
	},

	/**
	* Save as Draf and pring (VFS)
	*
	* @param {egw object} _egw
	* @param {widget object} _widget
	* @param {window object} _window
	*/
	saveAsDraftAndPrint: function(_egw, _widget, _window)
	{
		this.et2_obj.submit();
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
			var deDuplicator = function (item,pos){
									return tmp.indexOf(item) == pos	
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
						if (button_id == et2_dialog.YES_BUTTON )
						{
							actionData = _type.parent.data.widget.getArrayMgr('content');
							that._do_action(typeId, actionData['data'],ruleID);
						}
					};
					var confirmDeleteDialog = et2_dialog.show_dialog(callbackDeleteDialog, this.egw.lang("Do you really want to DELETE this Rule"),this.egw.lang("Delete"), {},et2_dialog.BUTTONS_YES_CANCEL, et2_dialog.WARNING_MESSAGE);

					break;
				case 'add'	:
					linkData = "mail.mail_sieve.edit";
					this.egw.open_link(linkData,'_blank',"600x480");
					break;
				case 'edit'	:
					linkData = "mail.mail_sieve.edit&ruleID="+ruleID;
					this.egw.open_link(linkData,'_blank',"600x480");
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
		var rights = (widget.get_value() == "custom"?[]:widget.get_value() == "aeiklprstwx")?
						widget.get_value().replace(/[k,x,t,e]/g,"cd").split(""):widget.get_value().split("");

		for (var i=0;i<this.aclRights.length;i++)
		{
			var rightsWidget = this.et2.getWidgetById(rowId+'[acl_' + this.aclRights[i]+ ']');
			rightsWidget.set_value((jQuery.inArray(this.aclRights[i],rights) != -1 )?true:false);
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

	   for (var i=0;i<this.aclRights.length;i++)
	   {
		   var rightsWidget = this.et2.getWidgetById(rowId+'[acl_' + this.aclRights[i]+ ']');
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

		if (!this.loadIframe(url))
		{
			this.egw.open_link(url);
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
		var mailSplitter = this.et2.getWidgetById('mailSplitter');
		var quotaipercent = this.et2.getWidgetById('nm[quotainpercent]');
		var iframe = _iFrame || this.et2.getWidgetById('extra_iframe');
		if (typeof iframe != 'undefined')
		{
			if (_url)
			{
				iframe.set_src(_url);
			}
			if (typeof mailSplitter != 'undefined' && typeof quotaipercent != 'undefined')
			{
				mailSplitter.set_disabled(!!_url);
				quotaipercent.set_disabled(!!_url);
				iframe.set_disabled(!_url);
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
		var acc_id = parseInt(_senders[0].id);
		this.egw.open_link('mail.mail_sieve.editVacation&acc_id='+acc_id,'_blank','700x480');
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
		this.egw.open_link('mail.mail_ui.subscription&acc_id='+acc_id, '_blank', '720x500');
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
		this.egw.message(this.egw.lang('Subscribe to Folder %1',ftree.getLabel(_senders[0].id).replace(this._unseen_regexp,'')));
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
		this.egw.message(this.egw.lang('Unsubscribe from Folder %1',ftree.getLabel(_senders[0].id).replace(this._unseen_regexp,'')));
		egw.json('mail.mail_ui.ajax_foldersubscription',[acc_id,folder,false])
			.sendRequest();
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
		var folder = mailbox[1], acc_id = mailbox[0];
		this.egw.open_link('mail.mail_acl.edit&mailbox='+ jQuery.base64Encode(folder)+'&acc_id='+acc_id, '_blank', '640x480');
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
		this.egw.open_link('mail.mail_wizard.edit&acc_id='+acc_id, '_blank', '720x500');
	},

	/**
	 * Hide Folder, Cc and Bcc rows from the compose popup
	 *
	 */
	compose_fieldExpander_hide: function ()
	{
		var widgets = {cc:{},bcc:{},folder:{}};
		for(var widget in widgets)
		{
			widgets[widget] = this.et2.getWidgetById(widget+'_expander');
			if (typeof widgets[widget] != 'undefined')
			{
				widgets[widget].set_disabled(false);
			}
		}
		jQuery(".mailComposeJQueryCc,.mailComposeJQueryBcc,.mailComposeJQueryFolder").hide();
	},

	/**
	 * Control textArea size based on available free space at the bottom
	 *
	 */
	compose_resizeHandler: function()
	{
		var bodyH = jQuery('body').height();
		var textArea = this.et2.getWidgetById('mail_plaintext');
		var toolbar = jQuery('.mailSignature');

		if (typeof textArea != 'undefined')
		{
			var textAreaH = textArea.node.clientHeight;
			if (textArea.getParent().disabled)
			{
				textArea = this.et2.getWidgetById('mail_htmltext');
				textAreaH = parseInt(textArea.getParent().node.clientHeight);
			}

			var freeSpace = (bodyH  - Math.round(toolbar.height() + toolbar.offset().top) - 65);

			if (textArea.id != "mail_htmltext")
			{
				textArea.set_height(textAreaH + freeSpace);
			}
			else if (typeof textArea != 'undefined' && textArea.id == 'mail_htmltext' && typeof textArea.getParent().node.children[1] != 'undefined')
			{
				jQuery(textArea.getParent().node.children[1].children[1].children[1]).css('height',textAreaH + (freeSpace - 90));
			}
			else
			{
				textArea.set_height(textAreaH + (freeSpace - 90));
			}
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
		var expWidgets = {cc:{},bcc:{},folder:{}};
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
			}
		}
		else if (typeof widget == "undefined")
		{
			var widgets = {cc:{},bcc:{},folder:{}};

			for(var widget in widgets)
			{
				widgets[widget] = this.et2.getWidgetById(widget);

				if (widgets[widget].get_value().length)
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
				this.mail_prev_print();
				break;
			case 'mail.display':
				this.mail_display_print();
		}

	},

	/**
	 * Print a mail from Display
	 *
	 */
	mail_display_print: function ()
	{
		var mainIframe = jQuery('#mail-display_mailDisplayBodySrc');

		if (jQuery('#tempPrintDiv').length == 0)
		{
			var tmpPrintDiv = jQuery(document.createElement('div'))
							.attr('id', 'tempPrintDiv')
							.addClass('tmpPrintDiv');
			if (mainIframe)
			{
				tmpPrintDiv[0].innerHTML = mainIframe.contents().find('body').html();
			}

			jQuery('#mail-display_mailDisplayBodySrc').after(tmpPrintDiv);

			tmpPrintDiv.find('#divAppboxHeader').remove();
		}

		this.egw.message('Printing....');
		var display = this;
		// Make sure the print happens after the content is loaded. Seems Firefox can't handle timing for print command correctly
		setTimeout(function(){display.egw.window.print();},1);
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
	}
});

// Bind a mouseenter event once for every read-only email
$j(function() {
	// If user doesn't have access to addressbook, stop
	if(!egw.app('addressbook')) return;

	$j('body').on('mouseenter', 'a[id^="mail-"].et2_email', function() {
		$j(this).tooltip({
			items: 'a.et2_email',
			position: {my:"left top", at:"left bottom", collision:"flipfit"},
			tooltipClass: "et2_email_popup",
			content: function() {
				// Here we could do all sorts of things
				var extra = {
					'presets[email]': $j(this).text()
				};
				return $j('<a href="#">'+ egw.lang('Add a new contact') + '</a>')
					.on('click', function() {
						egw.open('','addressbook','add',extra);
					});
			},
			close: function( event, ui ) {
				ui.tooltip.hover(
					function () {
						$j(this).stop(true).fadeTo(400, 1);
						//.fadeIn("slow"); // doesn't work because of stop()
					},
					function () {
						$j(this).fadeOut("400", function(){ $j(this).remove(); });
					}
				);
			}
		}).tooltip("open");
	});
});
