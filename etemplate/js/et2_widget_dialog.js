/**
 * EGroupware eTemplate2 - JS Dialog Widget class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 * @version $Id$
 */

"use strict";

/*egw:uses
        et2_core_widget;
	jquery.jquery-ui;
*/

/**
 * A common dialog widget that makes it easy to imform users or prompt for information.
 *
 * It is possible to have a custom dialog by using a template, but you can also use
 * the static method et2_dialog.show_dialog().  At its simplest, you can just use:
 * <code>
 *	et2_dialog.show_dialog(false, "Operation completed");
 * </code>
 * Or a more complete example:
 * <code>
 * 	var callback = function (button_id)
 *	{
 *		if(button_id == et2_dialog.YES_BUTTON)
 *		{
 *			// Do stuff
 *		}
 *		else if (button_id == et2_dialog.NO_BUTTON)
 *		{
 *			// Other stuff
 *		}
 *		else if (button_id == et2_dialog.CANCEL_BUTTON)
 *		{
 *			// Abort
 *		}
 *	}.
 *	var dialog = et2_dialog.show_dialog(
 *		callback, "Erase the entire database?","Break things", {} // value
 *		et2_dialog.BUTTONS_YES_NO_CANCEL, et2_dialog.WARNING_MESSAGE
 *	);
 * </code>
 *
 *
 * The parameters for the above are all optional, except callback and message:
 *	callback - function called when the dialog closes, or false/null.
 *		The ID of the button will be passed.  Button ID will be one of the et2_dialog.*_BUTTON constants.
 *		The callback is _not_ called if the user closes the dialog with the X in the corner, or presses ESC.
 * 	message - (plain) text to display
 *	title - Dialog title
 *	value (for prompt)
 *	buttons - et2_dialog BUTTONS_* constant, or an array of button settings
 *	dialog_type - et2_dialog *_MESSAGE constant
 *	icon - URL of icon
 *
 * Note that these methods will _not_ block program flow while waiting for user input.
 * The user's input will be provided to the callback.
 *
 * You can also use the standard et2_createWidget() to create a custom dialog using an etemplate, even setting all
 * the buttons yourself.
 * <code>
 *	var dialog = et2_createWidget("dialog",{
 *		// If you use a template, the second parameter will be the value of the template, as if it were submitted.
 *		callback: function(button_id, value) {...},
 *		buttons: [
 *			// These ones will use the callback, just like normal
 *			{text: egw.lang("OK"),id:"OK", class="ui-priority-primary", default: true},
 *			{text: egw.lang("Yes"),id:"Yes"},
 *			{text: egw.lang("Sure"),id:"Sure"},
 *			{text: egw.lang("Maybe"),click: function() {
 *				// If you override, 'this' will be the dialog DOMNode.
 *				// Things get more complicated.
 *				// Do what you like, but don't forget this line:
 *				$j(this).dialog("close")
 *			}, class="ui-state-error"},
 *
 *		],
 *		title: 'Why would you want to do this?',
 *		template:"/egroupware/addressbook/templates/default/edit.xet",
 *		value: { content: {...default values}, sel_options: {...}...}
 *	});
 * </code>
 * @augments et2_widget
 * @see http://api.jqueryui.com/dialog/
 */
var et2_dialog = et2_widget.extend({


	attributes: {
		callback: {
			name: "Callback",
			type: "js",
			description: "Callback function is called with the value when the dialog is closed",
			"default": function(button_id) {egw.debug("log","Button ID: %d",button_id);}
		},
		message: {
			name: "Message",
			type: "string",
			description: "Dialog message (plain text, no html)",
			"default": "Somebody forgot to set this...",
		},
		dialog_type: {
			name: "Dialog type",
			type: "integer",
			description: "To use a pre-defined dialog style, use et2_dialog.ERROR_MESSAGE, INFORMATION_MESSAGE,WARNING_MESSAGE,QUESTION_MESSAGE,PLAIN_MESSAGE constants.  Default is et2_dialog.PLAIN_MESSAGE",
			"default": 0, //this.PLAIN_MESSAGE
		},
		buttons: {
			name: "Buttons",
			type: "any",
			"default": 0, //this.BUTTONS_OK,
			description: "Buttons that appear at the bottom of the dialog.  You can use the constants et2_dialog.BUTTONS_OK, BUTTONS_YES_NO, BUTTONS_YES_NO_CANCEL, BUTTONS_OK_CANCEL, or pass in an array for full control",
		},
		icon: {
			name: "Icon",
			type: "string",
			description: "URL of an icon for the dialog.  If omitted, an icon based on dialog_type will be used.",
			"default": ""
		},
		title: {
			name: "Title",
			type: "string",
			description: "Title for the dialog box (plain text, no html)",
			"default": ""
		},
		modal: {
			name: "Modal",
			type: "boolean",
			description: "Prevent the user from interacting with the page",
			"default": true
		},
		resizable: {
			name: "Resizable",
			type: "boolean",
			description: "Allow the user to resize the dialog",
			"default": true
		},
		value: {
			"name": "Value",
			"description": "The (default) value of the dialog.  Use with template.",
			"type": "any",
			"default": et2_no_init
		},
		template: {
			"name": "Template",
			"description": "Instead of displaying a simple message, a full template can be loaded instead.  Set defaults with value.",
			"type": "string",
			"default": et2_no_init
		}
	},

	/**
	 * Details for dialog type options
	 */
	_dialog_types: [
		//PLAIN_MESSAGE: 0
		"",
		//INFORMATION_MESSAGE: 1,
		"dialog_info",
		//QUESTION_MESSAGE: 2,
		"dialog_help",
		//WARNING_MESSAGE: 3,
		"dialog_warning",
		//ERROR_MESSAGE: 4,
		"dialog_error",
	],

	_buttons: [
		/*
		Pre-defined Button combos
		 - button ids copied from et2_dialog static, since the constants are not defined yet
		 - image get replaced by 'style="background-image: url('+egw.image(image)+')' for an image prefixing text
		*/
		//BUTTONS_OK: 0,
		[{"button_id": 1,"text": 'ok', id: 'dialog[ok]', image: 'check', "default":true}],
		//BUTTONS_OK_CANCEL: 1,
		[
			{"button_id": 1,"text": 'ok', id: 'dialog[ok]', image: 'check', "default":true},
			{"button_id": 0,"text": 'cancel', id: 'dialog[cancel]', image: 'cancel'}
		],
		//BUTTONS_YES_NO: 2,
		[
			{"button_id": 2,"text": 'yes', id: 'dialog[yes]', image: 'check', "default":true},
			{"button_id": 3,"text": 'no', id: 'dialog[no]', image: 'cancelled'}
		],
		//BUTTONS_YES_NO_CANCEL: 3,
		[
			{"button_id": 2,"text": 'yes', id: 'dialog[yes]', image: 'check', "default":true},
			{"button_id": 3,"text": 'no', id: 'dialog[no]', image: 'cancelled'},
			{"button_id": 0,"text": 'cancel', id: 'dialog[cancel]', image: 'cancel'}
		]
	],

	// Define this as null to avoid breaking any hierarchies (eg: destroy())
	_parent: null,

	/**
	 * Constructor
	 *
	 * @memberOf et2_dialog
	 */
	init: function() {
		// Call the inherited constructor
		this._super.apply(this, arguments);

		// Button callbacks need a reference to this
		var self = this;
		for(var i = 0; i < this._buttons.length; i++)
		{
			for(var j = 0; j < this._buttons[i].length; j++)
			{
				this._buttons[i][j].click = (function(id) {
					return function(event) {
						self.click(event.target,id);
					};
				})(this._buttons[i][j].button_id);
				// translate button texts, as translations are not available before
				this._buttons[i][j].text = egw.lang(this._buttons[i][j].text);
			}
		}

		this.div = $j(document.createElement("div"));

		this._createDialog();
	},

	/**
	 * Clean up dialog
	 */
	destroy: function() {
		if(this.div != null)
		{
			// Un-dialog the dialog
			this.div.dialog("destroy");

			if(this.template)
			{
				this.template.clear();
				this.template = null;
			}

			this.div = null;
		}

		// Call the inherited constructor
		this._super.apply(this, arguments);
	},

	/**
	 * Internal callback registered on all standard buttons.
	 * The provided callback is called after the dialog is closed.
	 *
	 * @param target DOMNode The clicked button
	 * @param button_id integer The ID of the clicked button
	 */
	click: function(target, button_id) {
		if(this.options.callback)
		{
			this.options.callback.call(this,button_id,this.get_value());
		}
		// Triggers destroy too
		this.div.dialog("close");
	},

	/**
	 * Returns the values of any widgets in the dialog.  This does not include
	 * the buttons, which are only supplied for the callback.
	 */
	get_value: function() {
		var value = this.options.value;
		if(this.template)
		{
			value = this.template.getValues(this.template.widgetContainer);
		}
		return value;
	},

	/**
	 * Set the displayed prompt message
	 *
	 * @param string New message for the dialog
	 */
	set_message: function(message) {
		this.options.message = message;

		this.div.empty()
			.append("<img class='dialog_icon' />")
			.append($j('<div/>').text(message));
	},

	/**
	 * Set the dialog type to a pre-defined type
	 *
	 * @param integer Type constant from et2_dialog
	 */
	set_dialog_type: function(type) {
		if(this.options.dialog_type != type && typeof this._dialog_types[type] == "string")
		{
			this.options.dialog_type = type;
		}
		this.set_icon(this._dialog_types[type] ? egw.image(this._dialog_types[type]) : "");
	},

	/**
	 * Set the icon for the dialog
	 *
	 * @param string icon
	 */
	set_icon: function(icon_url) {
		if(icon_url == "")
		{
			$j("img.dialog_icon",this.div).hide();
		}
		else
		{
			$j("img.dialog_icon",this.div).show().attr("src", icon_url);
		}
	},

	/**
	 * Set the dialog buttons
	 *
	 * Use either the pre-defined options in et2_dialog, or an array
	 * @see http://api.jqueryui.com/dialog/#option-buttons
	 */
	set_buttons: function(buttons) {
		this.options.buttons = buttons;
		if (buttons instanceof Array)
		{
			for (var i = 0; i < buttons.length; i++)
			{
				var button = buttons[i];
				if(!button.click)
				{
					button.click = jQuery.proxy(this.click,this,null,button.id);
				}
				// set a default background image and css class based on buttons id
				if (button.id && typeof button.class == 'undefined')
				{
					for(var name in et2_button.default_classes)
					{
						if (button.id.match(et2_button.default_classes[name]))
						{
							button.class = (typeof button.class == 'undefined' ? '' : button.class+' ')+name;
							break;
						}
					}
				}
				if (button.id && typeof button.image == 'undefined' && typeof button.style == 'undefined')
				{
					for(var name in et2_button.default_background_images)
					{
						if (button.id.match(et2_button.default_background_images[name]))
						{
							button.image = name;
							break;
						}
					}
				}
				if (button.image)
				{
					button.style = 'background-image: url('+this.egw().image(button.image)+')';
					delete button.image;
				}
			}
		}

		// If dialog already created, update buttons
		if(this.div.data('ui-dialog'))
		{
			this.div.dialog("option", "buttons", buttons);

			// Focus default button so enter works
			$j('.ui-dialog-buttonpane button[default]',this.div.parent()).focus();
		}
	},

	/**
	 * Set the dialog title
	 *
	 * @param string New title for the dialog
	 */
	set_title: function(title) {
		this.options.title = title;
		this.div.dialog("option","title",title);
	},

	/**
	 * Block interaction with the page behind the dialog
	 *
	 * @param boolean Block page behind dialog
	 */
	set_modal: function(modal) {
		this.options.modal = modal;
		this.div.dialog("option","modal",modal);
	},

	/**
	 * Load an etemplate into the dialog
	 *
	 * @param template String etemplate file name
	 */
	set_template: function(template) {
		if(this.template && this.options.template != template)
		{
			this.template.clear();
		}

		this.template = new etemplate2(this.div[0], false);
		if(template.indexOf('.xet') > 0)
		{
			// File name provided, fetch from server
			this.template.load("",template,this.options.value||{}, jQuery.proxy(function() {
				// Set focus to the first input
				$j('input',this.div).first().focus();
			},this));
		}
		else
		{
			// Just template name, it better be loaded already
			this.template.load(template,'',this.options.value||{});
		}
	},

	/**
	 * Actually create and display the dialog
	 */
	_createDialog: function() {
		if(this.options.template)
		{
			this.set_template(this.options.template);
		}
		else
		{
			this.set_message(this.options.message);
			this.set_dialog_type(this.options.dialog_type);
		}
		this.set_buttons(typeof this.options.buttons == "number" ? this._buttons[this.options.buttons] : this.options.buttons);
		this.div.dialog({
			// Pass the internal object, not the option
			buttons: this.options.buttons,
			modal: this.options.modal,
			resizable: this.options.resizable,
			width: "auto",
			maxWidth: 640,
			title: this.options.title,
			open: function() {
				// Focus default button so enter works
				$j(this).parents('.ui-dialog-buttonpane button[default]').focus();
			},
			close: jQuery.proxy(function() {this.destroy();},this)
		});
	}
});
et2_register_widget(et2_dialog, ["dialog"]);

// Static class stuff
jQuery.extend(et2_dialog,
/** @lends et2_dialog */
{
	// Some class constants //

	/**
	 * Types
	 * @constant
	 */
	PLAIN_MESSAGE: 0,
	INFORMATION_MESSAGE: 1,
	QUESTION_MESSAGE: 2,
	WARNING_MESSAGE: 3,
	ERROR_MESSAGE: 4,

	/* Pre-defined Button combos */
	BUTTONS_OK: 0,
	BUTTONS_OK_CANCEL: 1,
	BUTTONS_YES_NO: 2,
	BUTTONS_YES_NO_CANCEL: 3,

	/* Button constants */
	CANCEL_BUTTON: 0,
	OK_BUTTON: 1,
	YES_BUTTON: 2,
	NO_BUTTON: 3,


	/**
	 * Create a parent to inject application specific egw object with loaded translations into et2_dialog
	 *
	 * @param {string|egw} _egw_or_appname= egw object with already laoded translations or application name to load translations for
	 */
	_create_parent: function(_egw_or_appname)
	{
		if (typeof _egw_or_appname == 'undefined')
		{
			_egw_or_appname = egw_appName;
		}
		// create a dummy parent with a correct reference to an application specific egw object
		var parent = new et2_widget();
		// if egw object is passed in because called from et2, just use it
		if (typeof _egw_or_appname == 'object')
		{
			parent._egw = _egw_or_appname;
		}
		// otherwise use given appname to create app-specific egw instance and load default translations
		else
		{
			parent._egw = egw(_egw_or_appname);
			parent._egw.langRequireApp(parent._egw.window, _egw_or_appname);
		}
		return parent;
	},

	/**
	 * Show a confirmation dialog
	 *
	 * @param function _callback Function called when the user clicks a button.  The context will be the et2_dialog widget, and the button constant is passed in.
	 * @param String _message Message to be place in the dialog.
	 * @param String _title Text in the top bar of the dialog.
	 * @param any _value passed unchanged to callback as 2. parameter
	 * @param integer|Array _buttons One of the BUTTONS_ constants defining the set of buttons at the bottom of the box
	 * @param integer _type One of the message constants.  This defines the style of the message.
	 * @param String _icon URL of an icon to display.  If not provided, a type-specific icon will be used.
	 * @param {string|egw} _egw_or_appname= egw object with already laoded translations or application name to load translations for
	 */
	show_dialog: function(_callback, _message, _title, _value, _buttons, _type, _icon, _egw_or_appname)
	{
		var parent = et2_dialog._create_parent(_egw_or_appname);

		// Just pass them along, widget handles defaults & missing
		return et2_createWidget("dialog", {
			callback: _callback||function(){},
			message: _message,
			title: _title||parent._egw.lang('Confirmation required'),
			buttons: typeof _buttons != 'undefined' ? _buttons : et2_dialog.BUTTONS_YES_NO,
			dialog_type: typeof _type != 'undefined' ? _type : et2_dialog.QUESTION_MESSAGE,
			icon: _icon,
			value: _value
		}, parent);
	},
	
	/**
	 * Show an alert message with OK button
	 * 
	 * @param {String} _message Message to be place in the dialog.
	 * @param {String} _title Text in the top bar of the dialog.
	 * @param integer _type One of the message constants.  This defines the style of the message.
	 */
	alert: function (_message, _title, _type)
	{
		var parent = et2_dialog._create_parent(et2_dialog._create_parent()._egw);
		et2_createWidget("dialog", {
			callback:function(){},
			message: _message,
			title: _title,
			buttons: et2_dialog.BUTTONS_OK,
			dialog_type: _type || et2_dialog.INFORMATION_MESSAGE
			}, parent);
	},
	
	/**
	 * Show a prompt dialog
	 *
	 * @param function _callback Function called when the user clicks a button.  The context will be the et2_dialog widget, and the button constant is passed in.
	 * @param String _message Message to be place in the dialog.
	 * @param String _title Text in the top bar of the dialog.
	 * @param String _value for prompt, passed to callback as 2. parameter
	 * @param integer|Array _buttons One of the BUTTONS_ constants defining the set of buttons at the bottom of the box
	 * @param {string|egw} _egw_or_appname= egw object with already laoded translations or application name to load translations for
	 */
	show_prompt: function(_callback, _message, _title, _value, _buttons, _egw_or_appname)
	{
		var callback = _callback;
		// Just pass them along, widget handles defaults & missing
		return et2_createWidget("dialog", {
			callback: function(_button_id, _value) {
				if (typeof callback == "function")
				{
					callback.call(this, _button_id, _value.value);
				}
			},
			title: _title||egw.lang('Input required'),
			buttons: _buttons||et2_dialog.BUTTONS_OK_CANCEL,
			value: {
				content: {
					value: _value,
					message: _message
				}
			},
			template: egw.webserverUrl+'/etemplate/templates/default/prompt.xet',
			class: "et2_prompt"
		}, et2_dialog._create_parent(_egw_or_appname));
	},

	/**
	 * Method to build a confirmation dialog only with
	 * YES OR NO buttons and submit content back to server
	 *
	 * @param {widget} _senders widget that has been clicked
	 * @param {String} _dialogMsg message shows in dialog box
	 * @param {String} _titleMsg message shows as a title of the dialog box
	 *
	 * @description submit the form contents including the button that has been pressed
	 */
	confirm: function(_senders,_dialogMsg, _titleMsg)
	{
		var senders = _senders;
		var buttonId = _senders.id;
		var dialogMsg = (typeof _dialogMsg !="undefined") ? _dialogMsg : '';
		var titleMsg = (typeof _titleMsg !="undefined") ? _titleMsg : '';
		var egw = _senders instanceof et2_widget ? _senders.egw() : et2_dialog._create_parent()._egw;
		var callbackDialog = function (button_id)
		{
			if (button_id == et2_dialog.YES_BUTTON )
			{
				senders.getRoot().getInstanceManager().submit(buttonId);
			}
		};
		et2_dialog.show_dialog(callbackDialog, egw.lang(dialogMsg), egw.lang(titleMsg), {},
			et2_dialog.BUTTON_YES_NO, et2_dialog.WARNING_MESSAGE, undefined, egw);
	},


	/**
	 * Show a dialog for a long-running, multi-part task
	 *
	 * Given a server url and a list of parameters, this will open a dialog with
	 * a progress bar, asynchronously call the url with each parameter, and update
	 * the progress bar.
	 * Any output from the server will be displayed in a box.
	 *
	 * When all tasks are done, the callback will be called with boolean true.  It will
	 * also be called if the user clicks a button (OK or CANCEL), so be sure to
	 * check to avoid executing more than intended.
	 *
	 * @param function _callback Function called when the user clicks a button,
	 *	or when the list is done processing.  The context will be the et2_dialog
	 *	widget, and the button constant is passed in.
	 * @param String _message Message to be place in the dialog.  Usually just
	 *	text, but DOM nodes will work too.
	 * @param String _title Text in the top bar of the dialog.
	 * @param {string} _menuaction the menuaction function which should be called and
	 * 	which handles the actual request. If the menuaction is a full featured
	 * 	url, this one will be used instead.
	 * @param {Array[]} _list - List of parameters, one for each call to the
	 *	address.  Multiple parameters are allowed, in an array.
	 * @param {string|egw} _egw_or_appname= egw object with already laoded translations or application name to load translations for
	 *
	 * @return {et2_dialog}
	 */
	long_task: function(_callback, _message, _title, _menuaction, _list, _egw_or_appname)
	{
		var parent = et2_dialog._create_parent(_egw_or_appname);
		var egw = parent._egw;

		// Special action for cancel
		var buttons = [
			{"button_id": et2_dialog.OK_BUTTON,"text": egw.lang('ok'), "default":true, "disabled":true},
			{"button_id": et2_dialog.CANCEL_BUTTON,"text": egw.lang('cancel'), click: function() {
				// Cancel run
				cancel = true;
				$j("button[button_id="+et2_dialog.CANCEL_BUTTON+"]", dialog.div.parent()).button("disable");
				update.call(_list.length,'');
			}}
		];
		var dialog = et2_createWidget("dialog", {
			template: egw.webserverUrl+'/etemplate/templates/default/long_task.xet',
			value: {
				content: {
					message: _message
				}
			},
			callback: function(_button_id, _value) {
				if(_button_id == et2_dialog.CANCEL_BUTTON)
				{
					cancel = true;
				}
				if (typeof _callback == "function")
				{
					_callback.call(this, _button_id, _value.value);
				}
			},
			title: _title||egw.lang('please wait...'),
			buttons: buttons
		}, parent);

		// OK starts disabled
		$j("button[button_id="+et2_dialog.OK_BUTTON+"]", dialog.div.parent()).button("disable");

		var log = null;
		var progressbar = null;
		var cancel = false;

		// Updates progressbar & log, calls next step
		var update = function(response) {
			// context is index
			var index = this || 0;

			progressbar.set_value(100*(index/_list.length));

			// Display response information
			switch(response.type)
			{
				case 'error':
					log.append("<div class='message error'>"+response.data+"</div>");
					break;
				default:
					if(response)
					{
						log.append("<div class='message'>"+response+"</div>");
					}
			}
			// Scroll to bottom
			var height = log[0].scrollHeight;
			log.scrollTop(height);

			// Fire next step
			if(!cancel && index < _list.length)
			{
				var parameters = _list[index];
				if(typeof parameters != 'object') parameters = [parameters];

				// Async request, we'll take the next step in the callback
				egw.json(_menuaction, parameters, update, index+1,true,index+1).sendRequest();
			}
			else
			{
				// All done
				if(!cancel) progressbar.set_value(100);
				$j("button[button_id="+et2_dialog.CANCEL_BUTTON+"]", dialog.div.parent()).button("disable");
				$j("button[button_id="+et2_dialog.OK_BUTTON+"]", dialog.div.parent()).button("enable");
				if (!cancel && typeof _callback == "function")
				{
					_callback.call(dialog, true);
				}
			}
		};

		$j(dialog.template.DOMContainer).on('load', function() {
			// Get access to template widgets
			log = $j(dialog.template.widgetContainer.getWidgetById('log').getDOMNode());
			progressbar = dialog.template.widgetContainer.getWidgetById('progressbar');

			// Start
			window.setTimeout(function() {update.call(0,'');},0);
		});

		return dialog;
	}
});
