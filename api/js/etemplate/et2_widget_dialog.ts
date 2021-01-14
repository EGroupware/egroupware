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

/*egw:uses
        et2_core_widget;
	/vendor/bower-asset/jquery-ui/jquery-ui.js;
*/

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_widget} from "./et2_core_widget";
import {et2_button} from "./et2_widget_button";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_DOMWidget} from "./et2_core_DOMWidget";
import {etemplate2} from "./etemplate2";

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
 *		callback: function(button_id, value) {...},	// return false to prevent dialog closing
 *		buttons: [
 *			// These ones will use the callback, just like normal
 *			{text: egw.lang("OK"),id:"OK", class="ui-priority-primary", default: true},
 *			{text: egw.lang("Yes"),id:"Yes"},
 *			{text: egw.lang("Sure"),id:"Sure"},
 *			{text: egw.lang("Maybe"),click: function() {
 *				// If you override, 'this' will be the dialog DOMNode.
 *				// Things get more complicated.
 *				// Do what you like, but don't forget this line:
 *				jQuery(this).dialog("close")
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
export class et2_dialog extends et2_widget {
    static readonly _attributes: any = {
		callback: {
		    name: "Callback",
		    type: "js",
		    description: "Callback function is called with the value when the dialog is closed",
		    "default": function (button_id) {
				egw.debug("log", "Button ID: %d", button_id);
		    }
		},
		beforeClose: {
		    name: "before close callback",
		    type: "js",
		    description: "Callback function before dialog is closed, return false to prevent that",
				"default": function () {
		    }
		},
		message: {
			name: "Message",
			type: "string",
			description: "Dialog message (plain text, no html)",
			"default": "Somebody forgot to set this..."
		},
		dialog_type: {
			name: "Dialog type",
			type: "integer",
			description: "To use a pre-defined dialog style, use et2_dialog.ERROR_MESSAGE, INFORMATION_MESSAGE,WARNING_MESSAGE,QUESTION_MESSAGE,PLAIN_MESSAGE constants.  Default is et2_dialog.PLAIN_MESSAGE",
			"default": 0 //this.PLAIN_MESSAGE
		},
		buttons: {
			name: "Buttons",
			type: "any",
			"default": 0, //this.BUTTONS_OK,
			description: "Buttons that appear at the bottom of the dialog.  You can use the constants et2_dialog.BUTTONS_OK, BUTTONS_YES_NO, BUTTONS_YES_NO_CANCEL, BUTTONS_OK_CANCEL, or pass in an array for full control"
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
		},
		minWidth: {
			name: "minimum width",
			type: "integer",
			description: "Define minimum width of dialog",
			"default": 0
		},
		minHeight: {
			name: "minimum height",
			type: "integer",
			description: "Define minimum height of dialog",
			"default": 0
		},
		width: {
		    name: "width",
		    type: "string",
		    description: "Define width of dialog, the default is auto",
		   "default": et2_no_init
		},
		height: {
			name: "height",
			type: "string",
			description: "Define width of dialog, the default is auto",
			"default": 'auto'
		},
		position: {
			name: "position",
			type: "string",
			description: "Define position of dialog in the main window",
			default: "center"
		},
	    appendTo: {
			name: "appendTo",
			type: "string",
			description: "Defines the dialog parent context",
			default: ''
		},
	    draggable: {
		    name: "Draggable",
		    type: "boolean",
		    description: "Allow the user to drag the dialog",
		    default: true
	    },
	    closeOnEscape: {
			name: "close on escape",
		    type: "boolean",
		    description: "Allow the user to close the dialog by hiting escape",
		    default: true
	    },
	    dialogClass: {
		    name: "dialog class",
		    type: "string",
		    description: "Add css classed into dialog container",
		    default: ''
	    }
	};

    /**
     * Details for dialog type options
     */
    private readonly _dialog_types: any = [
        //PLAIN_MESSAGE: 0
        "",
        //INFORMATION_MESSAGE: 1,
        "dialog_info",
        //QUESTION_MESSAGE: 2,
        "dialog_help",
        //WARNING_MESSAGE: 3,
        "dialog_warning",
        //ERROR_MESSAGE: 4,
        "dialog_error"
    ];

    private readonly _buttons: any = [
        /*
        Pre-defined Button combos
         - button ids copied from et2_dialog static, since the constants are not defined yet
         - image get replaced by 'style="background-image: url('+egw.image(image)+')' for an image prefixing text
        */
        //BUTTONS_OK: 0,
        [{"button_id": 1, "text": 'ok', id: 'dialog[ok]', image: 'check', "default": true}],
        //BUTTONS_OK_CANCEL: 1,
        [
            {"button_id": 1, "text": 'ok', id: 'dialog[ok]', image: 'check', "default": true},
            {"button_id": 0, "text": 'cancel', id: 'dialog[cancel]', image: 'cancel'}
        ],
        //BUTTONS_YES_NO: 2,
        [
            {"button_id": 2, "text": 'yes', id: 'dialog[yes]', image: 'check', "default": true},
            {"button_id": 3, "text": 'no', id: 'dialog[no]', image: 'cancelled'}
        ],
        //BUTTONS_YES_NO_CANCEL: 3,
        [
            {"button_id": 2, "text": 'yes', id: 'dialog[yes]', image: 'check', "default": true},
            {"button_id": 3, "text": 'no', id: 'dialog[no]', image: 'cancelled'},
            {"button_id": 0, "text": 'cancel', id: 'dialog[cancel]', image: 'cancel'}
        ]
    ];

    /**
     * Types
     * @constant
     */
    public static PLAIN_MESSAGE: number = 0;
    public static INFORMATION_MESSAGE: number = 1;
    public static QUESTION_MESSAGE: number = 2;
    public static WARNING_MESSAGE: number = 3;
    public static ERROR_MESSAGE: number = 4;

    /* Pre-defined Button combos */
    public static BUTTONS_OK: number = 0;
    public static BUTTONS_OK_CANCEL: number = 1;
    public static BUTTONS_YES_NO: number = 2;
    public static BUTTONS_YES_NO_CANCEL: number = 3;

    /* Button constants */
    public static CANCEL_BUTTON: number = 0;
    public static OK_BUTTON: number = 1;
    public static YES_BUTTON: number = 2;
    public static NO_BUTTON: number = 3;

    div: JQuery = null;
    template: any = null;

    constructor(_parent?, _attrs? : WidgetConfig, _child? : object) {
        super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_dialog._attributes, _child || {}));

        // Define this as null to avoid breaking any hierarchies (eg: destroy())
        if (this.getParent() != null) this.getParent().removeChild(this);

        // Button callbacks need a reference to this
        let self = this;
        for (let i = 0; i < this._buttons.length; i++) {
            for (let j = 0; j < this._buttons[i].length; j++) {
                this._buttons[i][j].click = (function (id) {
                    return function (event) {
                        self.click(event.target, id);
                    };
                })(this._buttons[i][j].button_id);
                // translate button texts, as translations are not available before
                this._buttons[i][j].text = egw.lang(this._buttons[i][j].text);
            }
        }

        this.div = jQuery(document.createElement("div"));

        this._createDialog();
    }

    /**
     * Clean up dialog
     */
    destroy() {
        if (this.div != null) {
            // Un-dialog the dialog
            this.div.dialog("destroy");

            if (this.template) {
                this.template.clear();
                this.template = null;
            }

            this.div = null;
        }

        // Call the inherited constructor
        super.destroy();
    }

    /**
     * Internal callback registered on all standard buttons.
     * The provided callback is called after the dialog is closed.
     *
     * @param target DOMNode The clicked button
     * @param button_id integer The ID of the clicked button
     */
    click(target: HTMLElement, button_id: number) {
        if (this.options.callback) {
            if (this.options.callback.call(this, button_id, this.get_value()) === false) return;
        }
        // Triggers destroy too
        this.div.dialog("close");
    }

    /**
     * Returns the values of any widgets in the dialog.  This does not include
     * the buttons, which are only supplied for the callback.
     */
    get_value() {
        var value = this.options.value;
        if (this.template) {
            value = this.template.getValues(this.template.widgetContainer);
        }
        return value;
    }

    /**
     * Set the displayed prompt message
     *
     * @param {string} message New message for the dialog
     */
    set_message(message) {
        this.options.message = message;

        this.div.empty()
            .append("<img class='dialog_icon' />")
            .append(jQuery('<div/>').text(message));
    }

    /**
     * Set the dialog type to a pre-defined type
     *
     * @param {integer} type constant from et2_dialog
     */
    set_dialog_type(type) {
        if (this.options.dialog_type != type && typeof this._dialog_types[type] == "string") {
            this.options.dialog_type = type;
        }
        this.set_icon(this._dialog_types[type] ? egw.image(this._dialog_types[type]) : "");
    }

    /**
     * Set the icon for the dialog
     *
     * @param {string} icon_url
     */
    set_icon(icon_url) {
        if (icon_url == "") {
            jQuery("img.dialog_icon", this.div).hide();
        } else {
            jQuery("img.dialog_icon", this.div).show().attr("src", icon_url);
        }
    }

    /**
     * Set the dialog buttons
     *
     * Use either the pre-defined options in et2_dialog, or an array
     * @see http://api.jqueryui.com/dialog/#option-buttons
     * @param {array} buttons
     */
    set_buttons(buttons) {
        this.options.buttons = buttons;
        if (buttons instanceof Array) {
            for (var i = 0; i < buttons.length; i++) {
                var button = buttons[i];
                if (!button.click) {
                    button.click = jQuery.proxy(this.click, this, null, button.id);
                }
                // set a default background image and css class based on buttons id
                if (button.id && typeof button.class == 'undefined') {
                    for (var name in et2_button.default_classes) {
                        if (button.id.match(et2_button.default_classes[name])) {
                            button.class = (typeof button.class == 'undefined' ? '' : button.class + ' ') + name;
                            break;
                        }
                    }
                }
                if (button.id && typeof button.image == 'undefined' && typeof button.style == 'undefined') {
                    for (var name in et2_button.default_background_images) {
                        if (button.id.match(et2_button.default_background_images[name])) {
                            button.image = name;
                            break;
                        }
                    }
                }
                if (button.image) {
                    button.style = 'background-image: url(' + this.egw().image(button.image, 'api') + ')';
                    delete button.image;
                }
            }
        }

        // If dialog already created, update buttons
        if (this.div.data('ui-dialog')) {
            this.div.dialog("option", "buttons", buttons);

            // Focus default button so enter works
            jQuery('.ui-dialog-buttonpane button[default]', this.div.parent()).focus();
        }
    }

    /**
     * Set the dialog title
     *
     * @param {string} title New title for the dialog
     */
    set_title(title) {
        this.options.title = title;
        this.div.dialog("option", "title", title);
    }

    /**
     * Block interaction with the page behind the dialog
     *
     * @param {boolean} modal Block page behind dialog
     */
    set_modal(modal) {
        this.options.modal = modal;
        this.div.dialog("option", "modal", modal);
    }

    /**
     * Load an etemplate into the dialog
     *
     * @param template String etemplate file name
     */
    set_template(template) {
        if (this.template && this.options.template != template) {
            this.template.clear();
        }

        this.template = new etemplate2(this.div[0], false);
        if (template.indexOf('.xet') > 0) {
            // File name provided, fetch from server
            this.template.load("", template, this.options.value || {content: {}}, jQuery.proxy(function () {
                // Set focus to the first input
                jQuery('input', this.div).first().focus();
            }, this));
        } else {
            // Just template name, it better be loaded already
            this.template.load(template, '', this.options.value || {},
                // true: do NOT call et2_ready, as it would overwrite this.et2 in app.js
                undefined, undefined, true);
        }

        // Don't let dialog closing destroy the parent session
	    if(this.template.etemplate_exec_id && this.template.app)
	    {
	        for(let et of etemplate2.getByApplication(this.template.app))
	        {
	        	if(et !== this.template && et.etemplate_exec_id === this.template.etemplate_exec_id)
		        {
		        	// Found another template using that exec_id, don't destroy when dialog closes.
			        this.template.unbind_unload();
			        break;
		        }
	        }
	    }
        // set template-name as id, to allow to style dialogs
        this.div.children().attr('id', template.replace(/^(.*\/)?([^/]+)(\.xet)?$/, '$2').replace(/\./g, '-'));
    }

    /**
     * Actually create and display the dialog
     */
    _createDialog() {
        if (this.options.template) {
            this.set_template(this.options.template);
        } else {
            this.set_message(this.options.message);
            this.set_dialog_type(this.options.dialog_type);
        }
        this.set_buttons(typeof this.options.buttons == "number" ? this._buttons[this.options.buttons] : this.options.buttons);
        let position_my, position_at = '';
        if (this.options.position)
        {
            let positions = this.options.position.split(',');
            position_my = positions[0] ? positions[0].trim() : 'center';
            position_at = positions[1] ? positions[1].trim() : position_my;
        }
        let options = {
            // Pass the internal object, not the option
            buttons: this.options.buttons,
            modal: this.options.modal,
            resizable: this.options.resizable,
            minWidth: this.options.minWidth,
            minHeight: this.options.minHeight,
            maxWidth: 640,
            height: this.options.height,
            title: this.options.title,
            open: function () {
                // Focus default button so enter works
                jQuery(this).parents('.ui-dialog-buttonpane button[default]').focus();
                window.setTimeout(function () {
                    jQuery(this).dialog('option', 'position', {
                        my: position_my,
                        at: position_at,
                        of: window
                    });
                }.bind(this), 0);
            },
            close: jQuery.proxy(function () {
                this.destroy();
            }, this),
            beforeClose: this.options.beforeClose,
            closeText: this.egw().lang('close'),
            position: {my: "center", at: "center", of: window},
	        appendTo: this.options.appendTo,
	        draggable: this.options.draggable,
	        closeOnEscape: this.options.closeOnEscape,
	        dialogClass: this.options.dialogClass,
        };
        // Leaving width unset lets it size itself according to contents
        if (this.options.width) {
            options['width'] = this.options.width;
        }

        this.div.dialog(options);

        // Make sure dialog is wide enough for the title
        // Arbitrary numbers that seem to work nicely.
        let title_width = 20 + 10 * this.options.title.length;
        if (this.div.width() < title_width && this.options.title.trim()) {
            // Auto-sizing chopped the title
            this.div.dialog('option', 'width', title_width);
        }
    }

    /**
     * Create a parent to inject application specific egw object with loaded translations into et2_dialog
     *
     * @param {string|egw} _egw_or_appname egw object with already loaded translations or application name to load translations for
     */
    static _create_parent(_egw_or_appname? : string | IegwAppLocal) {
        if (typeof _egw_or_appname == 'undefined') {
            // @ts-ignore
            _egw_or_appname = egw_appName;
        }
        // create a dummy parent with a correct reference to an application specific egw object
        let parent = new et2_widget();
        // if egw object is passed in because called from et2, just use it
        if (typeof _egw_or_appname != 'string') {
            parent.setApiInstance(_egw_or_appname);
        }
        // otherwise use given appname to create app-specific egw instance and load default translations
        else {
            parent.setApiInstance(egw(_egw_or_appname));
            parent.egw().langRequireApp(parent.egw().window, _egw_or_appname);
        }
        return parent;
    }

    /**
     * Show a confirmation dialog
     *
     * @param {function} _callback Function called when the user clicks a button.  The context will be the et2_dialog widget, and the button constant is passed in.
     * @param {string} _message Message to be place in the dialog.
     * @param {string} _title Text in the top bar of the dialog.
     * @param _value passed unchanged to callback as 2. parameter
     * @param {integer|array} _buttons One of the BUTTONS_ constants defining the set of buttons at the bottom of the box
     * @param {integer} _type One of the message constants.  This defines the style of the message.
     * @param {string} _icon URL of an icon to display.  If not provided, a type-specific icon will be used.
     * @param {string|egw} _egw_or_appname egw object with already laoded translations or application name to load translations for
     */
    static show_dialog(_callback? : Function, _message? : string, _title? : string, _value? : object, _buttons?, _type? : number, _icon? : string, _egw_or_appname? : string | IegwAppLocal) {
        let parent = et2_dialog._create_parent(_egw_or_appname);

        // Just pass them along, widget handles defaults & missing
        return et2_createWidget("dialog", {
            callback: _callback || function () {
            },
            message: _message,
            title: _title || parent.egw().lang('Confirmation required'),
            buttons: typeof _buttons != 'undefined' ? _buttons : et2_dialog.BUTTONS_YES_NO,
            dialog_type: typeof _type != 'undefined' ? _type : et2_dialog.QUESTION_MESSAGE,
            icon: _icon,
            value: _value,
            width: 'auto'
        }, parent);
    };

    /**
     * Show an alert message with OK button
     *
     * @param {string} _message Message to be place in the dialog.
     * @param {string} _title Text in the top bar of the dialog.
     * @param {integer} _type One of the message constants.  This defines the style of the message.
     */
    static alert(_message? : string, _title? : string, _type?) {
        let parent = et2_dialog._create_parent(et2_dialog._create_parent().egw());
        et2_createWidget("dialog", {
            callback: function () {
            },
            message: _message,
            title: _title,
            buttons: et2_dialog.BUTTONS_OK,
            dialog_type: _type || et2_dialog.INFORMATION_MESSAGE
        }, parent);
    }

    /**
     * Show a prompt dialog
     *
     * @param {function} _callback Function called when the user clicks a button.  The context will be the et2_dialog widget, and the button constant is passed in.
     * @param {string} _message Message to be place in the dialog.
     * @param {string} _title Text in the top bar of the dialog.
     * @param {string} _value for prompt, passed to callback as 2. parameter
     * @param {integer|array} _buttons One of the BUTTONS_ constants defining the set of buttons at the bottom of the box
     * @param {string|egw} _egw_or_appname egw object with already laoded translations or application name to load translations for
     */
    static show_prompt(_callback, _message, _title?, _value?, _buttons?, _egw_or_appname?) {
        var callback = _callback;
        // Just pass them along, widget handles defaults & missing
        return et2_createWidget("dialog", {
            callback: function (_button_id, _value) {
                if (typeof callback == "function") {
                    callback.call(this, _button_id, _value.value);
                }
            },
            title: _title || egw.lang('Input required'),
            buttons: _buttons || et2_dialog.BUTTONS_OK_CANCEL,
            value: {
                content: {
                    value: _value,
                    message: _message
                }
            },
            template: egw.webserverUrl + '/api/templates/default/prompt.xet',
            class: "et2_prompt"
        }, et2_dialog._create_parent(_egw_or_appname));
    }

    /**
     * Method to build a confirmation dialog only with
     * YES OR NO buttons and submit content back to server
     *
     * @param {widget} _senders widget that has been clicked
     * @param {String} _dialogMsg message shows in dialog box
     * @param {String} _titleMsg message shows as a title of the dialog box
     * @param {Bool} _postSubmit true: use postSubmit instead of submit
     *
     * @description submit the form contents including the button that has been pressed
     */
    static confirm(_senders, _dialogMsg, _titleMsg, _postSubmit) {
        var senders = _senders;
        var buttonId = _senders.id;
        var dialogMsg = (typeof _dialogMsg != "undefined") ? _dialogMsg : '';
        var titleMsg = (typeof _titleMsg != "undefined") ? _titleMsg : '';
        var egw = _senders instanceof et2_widget ? _senders.egw() : et2_dialog._create_parent().egw();
        var callbackDialog = function (button_id) {
            if (button_id == et2_dialog.YES_BUTTON) {
                if (_postSubmit) {
                    senders.getRoot().getInstanceManager().postSubmit(buttonId);
                } else {
                    senders.getRoot().getInstanceManager().submit(buttonId);
                }
            }
        };
        et2_dialog.show_dialog(callbackDialog, egw.lang(dialogMsg), egw.lang(titleMsg), {},
            et2_dialog.BUTTONS_YES_NO, et2_dialog.WARNING_MESSAGE, undefined, egw);
    };


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
     * @param {function} _callback Function called when the user clicks a button,
     *	or when the list is done processing.  The context will be the et2_dialog
     *	widget, and the button constant is passed in.
     * @param {string} _message Message to be place in the dialog.  Usually just
     *	text, but DOM nodes will work too.
     * @param {string} _title Text in the top bar of the dialog.
     * @param {string} _menuaction the menuaction function which should be called and
     * 	which handles the actual request. If the menuaction is a full featured
     * 	url, this one will be used instead.
     * @param {Array[]} _list - List of parameters, one for each call to the
     *	address.  Multiple parameters are allowed, in an array.
     * @param {string|egw} _egw_or_appname egw object with already laoded translations or application name to load translations for
     *
     * @return {et2_dialog}
     */
    static long_task(_callback, _message, _title, _menuaction, _list, _egw_or_appname)
    {
        let parent = et2_dialog._create_parent(_egw_or_appname);
        let egw = parent.egw();

        // Special action for cancel
        let buttons = [
            {"button_id": et2_dialog.OK_BUTTON, "text": egw.lang('ok'), "default": true, "disabled": true},
            {
                "button_id": et2_dialog.CANCEL_BUTTON, "text": egw.lang('cancel'), click: function () {
                    // Cancel run
                    cancel = true;
                    jQuery("button[button_id=" + et2_dialog.CANCEL_BUTTON + "]", dialog.div.parent()).button("disable");
                    update.call(_list.length, '');
                }
            }
        ];
        let dialog = et2_createWidget("dialog", {
            template: egw.webserverUrl + '/api/templates/default/long_task.xet',
            value: {
                content: {
                    message: _message
                }
            },
            callback: function (_button_id, _value) {
                if (_button_id == et2_dialog.CANCEL_BUTTON) {
                    cancel = true;
                }
                if (typeof _callback == "function") {
                    _callback.call(this, _button_id, _value.value);
                }
            },
            title: _title || egw.lang('please wait...'),
            buttons: buttons
        }, parent);

        // OK starts disabled
        jQuery("button[button_id=" + et2_dialog.OK_BUTTON + "]", dialog.div.parent()).button("disable");

        let log = null;
        let progressbar = null;
        let cancel = false;
        let totals = {
            success: 0,
            skipped: 0,
            failed: 0,
            widget: null
        };

        // Updates progressbar & log, calls next step
        let update = function (response) {
            // context is index
            let index = this || 0;

            progressbar.set_value(100 * (index / _list.length));
            progressbar.set_label(index + ' / ' + _list.length);

            // Display response information
            switch (response.type) {
                case 'error':
                    jQuery("<div class='message error'></div>")
                        .text(response.data)
                        .appendTo(log);

                    totals.failed++;

                    // Ask to retry / ignore / abort
                    et2_createWidget("dialog", {
                        callback: function (button) {
                            switch (button) {
                                case 'dialog[cancel]':
                                    cancel = true;
                                    return update.call(index, '');
                                case 'dialog[skip]':
                                    // Continue with next index
                                    totals.skipped++;
                                    return update.call(index, '');
                                default:
                                    // Try again with previous index
                                    return update.call(index - 1, '');
                            }

                        },
                        message: response.data,
                        title: '',
                        buttons: [
                            // These ones will use the callback, just like normal
                            {text: egw.lang("Abort"), id: 'dialog[cancel]'},
                            {text: egw.lang("Retry"), id: 'dialog[retry]'},
                            {text: egw.lang("Skip"), id: 'dialog[skip]', class: "ui-priority-primary", default: true}
                        ],
                        dialog_type: et2_dialog.ERROR_MESSAGE
                    }, parent);
                    // Early exit
                    return;
                default:
                    if (response && typeof response === "string") {
                        totals.success++;
                        jQuery("<div class='message'></div>")
                            .text(response)
                            .appendTo(log);
                    }
                    else
                    {
                    	jQuery("<div class='message error'></div>")
                            .text(JSON.stringify(response))
                            .appendTo(log);
                    }
            }
            // Scroll to bottom
            let height = log[0].scrollHeight;
            log.scrollTop(height);

            // Update totals
            totals.widget.set_value(egw.lang(
                "Total: %1 Successful: %2 Failed: %3 Skipped: %4",
                _list.length, <string><unknown>totals.success, <string><unknown>totals.failed, <string><unknown>totals.skipped
            ));

            // Fire next step
            if (!cancel && index < _list.length) {
                var parameters = _list[index];
                if (typeof parameters != 'object') parameters = [parameters];

                // Async request, we'll take the next step in the callback
                // We can't pass index = 0, it looks like false and causes issues
                egw.json(_menuaction, parameters, update, index + 1, true, index + 1).sendRequest();
            } else {
                // All done
                if (!cancel) progressbar.set_value(100);
                jQuery("button[button_id=" + et2_dialog.CANCEL_BUTTON + "]", dialog.div.parent()).button("disable");
                jQuery("button[button_id=" + et2_dialog.OK_BUTTON + "]", dialog.div.parent()).button("enable");
                if (!cancel && typeof _callback == "function") {
                    _callback.call(dialog, true, response);
                }
            }
        };

        jQuery(dialog.template.DOMContainer).on('load', function () {
            // Get access to template widgets
            log = jQuery(dialog.template.widgetContainer.getWidgetById('log').getDOMNode());
            progressbar = dialog.template.widgetContainer.getWidgetById('progressbar');
            progressbar.set_label('0 / ' + _list.length);
            totals.widget = dialog.template.widgetContainer.getWidgetById('totals');

            // Start
            window.setTimeout(function () {
                update.call(0, '');
            }, 0);
        });

        return dialog;
    }
}
et2_register_widget(et2_dialog, ["dialog"]);