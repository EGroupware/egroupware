/**
 * EGroupware eTemplate2 - JS Textbox object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

import './et2_core_common';
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_valueWidget} from './et2_core_valueWidget'
import {et2_inputWidget} from './et2_core_inputWidget'
import {et2_button} from './et2_widget_button'
import {et2_textbox, et2_textbox_ro} from "./et2_widget_textbox";
import {et2_dialog} from "./et2_widget_dialog";

/**
 * Class which implements the "textbox" XET-Tag
 *
 * @augments et2_inputWidget
 */
export class et2_password extends et2_textbox
{
	static readonly _attributes : any = {
		"autocomplete": {
			"name": "Autocomplete",
			"type": "string",
			"default": "off",
			"description": "Whether or not browser should autocomplete that field: 'on', 'off', 'default' (use attribute from form). Default value is set to off."
		},
		"viewable": {
			"name": "Viewable",
			"type": "boolean",
			"default": false,
			"description": "Allow password to be shown"
		},
		"plaintext": {
			name: "Plaintext",
			type: "boolean",
			default: true,
			description: "Password is plaintext"
		},
		"suggest": {
			name: "Suggest password",
			type: "integer",
			default: 0,
			description: "Suggest password length (0 for off)"
		}
	};

	public static readonly DEFAULT_LENGTH = 16;
	wrapper : JQuery;
	private suggest_button: et2_button;
	private show_button: et2_button;

	// The password is stored encrypted server side, and passed encrypted.
	// This flag is for if we've decrypted the password to show it already
	private encrypted : boolean = true;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_password._attributes, _child || {}));

		if(this.options.plaintext)
		{
			this.encrypted = false;
		}
	}

	createInputWidget()
	{
		this.wrapper = jQuery(document.createElement("div"))
			.addClass("et2_password");
		this.input = jQuery(document.createElement("input"))

		this.input.attr("type", "password");
		// Make autocomplete default value off for password field
		// seems browsers not respecting 'off' anymore and started to
		// implement a new key called "new-password" considered as switching
		// autocomplete off.
		// https://developer.mozilla.org/en-US/docs/Web/Security/Securing_your_site/Turning_off_form_autocompletion
		if (this.options.autocomplete === "" || this.options.autocomplete == "off") this.options.autocomplete = "new-password";

		if(this.options.size) {
			this.set_size(this.options.size);
		}
		if(this.options.blur) {
			this.set_blur(this.options.blur);
		}
		if(this.options.readonly) {
			this.set_readonly(true);
		}
		this.input.addClass("et2_textbox")
		    .appendTo(this.wrapper);
		this.setDOMNode(this.wrapper[0]);
		if(this.options.value)
		{
			this.set_value(this.options.value);
		}
		if (this.options.onkeypress && typeof this.options.onkeypress == 'function')
		{
			var self = this;
			this.input.on('keypress', function(_ev)
			{
				return self.options.onkeypress.call(this, _ev, self);
			});
		}
		this.input.on('change', function() {
			this.encrypted = false;
		}.bind(this));

		// Show button is needed from start as you can't turn viewable on via JS
		let attrs = {
			class: "show_hide",
			image: "visibility",
			onclick: this.toggle_visibility.bind(this),
			statustext: this.egw().lang("Show password")
		};
		if(this.options.viewable)
		{
			this.show_button = <et2_button>et2_createWidget("button", attrs, this);
		}
	}

	getInputNode()
	{
		return this.input[0];
	}

	/**
	 * Override the parent set_id method to manuipulate the input DOM node
	 *
	 * @param {type} _value
	 * @returns {undefined}
	 */
	set_id(_value)
	{
		super.set_id(_value);

		// Remove the name attribute inorder to affect autocomplete="off"
		// for no password save. ATM seems all browsers ignore autocomplete for
		// input field inside the form
		if (this.options.autocomplete === "off") this.input.removeAttr('name');
	}

	/**
	 * Set whether or not the password is allowed to be shown in clear text.
	 *
	 * @param viewable
	 */
	set_viewable(viewable: boolean)
	{
		this.options.viewable = viewable;

		if(viewable)
		{
			jQuery('.show_hide', this.wrapper).show();
		}
		else
		{
			jQuery('.show_hide', this.wrapper).hide();
		}
	}

	/**
	 * Turn on or off the suggest password button.
	 *
	 * When clicked, a password of the set length will be generated.
	 *
	 * @param length Length of password to generate.  0 to disable.
	 */
	set_suggest(length: number)
	{
		if(typeof length !== "number")
		{
			length = typeof length === "string" ? parseInt(length) : (length ? et2_password.DEFAULT_LENGTH : 0);
		}
		this.options.suggest = length;

		if(length && !this.suggest_button)
		{
			let attrs = {
				class: "generate_password",
				image: "generate_password",
				onclick: this.suggest_password.bind(this),
				statustext: this.egw().lang("Suggest password")
			};
			this.suggest_button = <et2_button> et2_createWidget("button", attrs, this);
			if(this.parentNode)
			{
				// Turned on after initial load, need to run loadingFinished()
				this.suggest_button.loadingFinished();
			}
		}
		if(length)
		{
			jQuery('.generate_password', this.wrapper).show();
		}
		else
		{
			jQuery('.generate_password', this.wrapper).hide();
		}
	}

	/**
	 * If the password is viewable, toggle the visibility.
	 * If the password is still encrypted, we'll ask for the user's password then have the server decrypt it.
	 *
	 * @param on
	 */
	toggle_visibility(on : boolean | undefined)
	{
		if(typeof on !== "boolean")
		{
			on = this.input.attr("type") == "password";
		}
		if(!this.options.viewable)
		{
			this.input.attr("type", "password");
			return;
		}
		if(this.show_button)
		{
			this.show_button.set_image(this.egw().image(on ? 'visibility_off' : 'visibility'));
		}

		// If we are not encrypted or not showing it, we're done
		if(!this.encrypted || !on)
		{
			this.input.attr("type",on ? "textbox" : "password");
			return;
		}


		// Need username & password to decrypt
		let callback = function(button, user_password)
		{
			if(button == et2_dialog.CANCEL_BUTTON)
			{
				return this.toggle_visibility(false);
			}
			let request = egw.json(
				"EGroupware\\Api\\Etemplate\\Widget\\Password::ajax_decrypt",
				[user_password, this.options.value],
				function(decrypted)
	           {
		            if(decrypted)
		            {
			            this.encrypted = false;
			            this.input.val(decrypted);
						this.input.attr("type", "textbox");
		            }
		            else
		            {
		            	this.set_validation_error(this.egw().lang("invalid password"));
		            	window.setTimeout(function() {
		            		this.set_validation_error(false);
			            }.bind(this), 2000);
		            }
				},
				this,true,this
			).sendRequest();
		}.bind(this);
		let prompt = et2_dialog.show_prompt(
			callback,
			this.egw().lang("Enter your password"),
			this.egw().lang("Authenticate")
		);

		// Make the password prompt a password field
		prompt.div.on("load", function() {
			jQuery(prompt.template.widgetContainer.getWidgetById('value').getInputNode())
				.attr("type","password");
		});

	}

	/**
	 * Ask the server for a password suggestion
	 */
	suggest_password()
	{
		// They need to see the suggestion
		this.encrypted = false;
		this.options.viewable = true;
		this.toggle_visibility(true);

		let suggestion = "Suggestion";
		let request = egw.json("EGroupware\\Api\\Etemplate\\Widget\\Password::ajax_suggest",
			[this.options.suggest],
			function(suggestion) {
				this.encrypted = false;
				this.input.val(suggestion);
				this.input.trigger('change');

				// Check for second password, update it too
				let two = this.getParent().getWidgetById(this.id+'_2');
				if(two && two.getType() == this.getType())
				{
					two.options.viewable = true;
					two.toggle_visibility(true);
					two.set_value(suggestion);
				}
			},
			this,true,this
		).sendRequest();
	}

	destroy()
	{
		super.destroy();
	}

	getValue()
	{
		return this.input.val();
	}
}
et2_register_widget(et2_password, [ "passwd"]);


export class et2_password_ro extends et2_textbox_ro
{
	set_value(value)
	{
		this.value_span.text(value ? "********" : "");
	}
}
et2_register_widget(et2_password_ro, [ "passwd_ro"]);