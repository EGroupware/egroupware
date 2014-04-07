/**
 * EGroupware eTemplate2 - JS URL object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_textbox;
	et2_valueWidget;
	phpgwapi.jquery.jquery.base64;
*/

/**
 * Class which implements the "url" XET-Tag, which covers URLs, email & phone
 *
 * @augments et2_textbox
 */
var et2_url = et2_textbox.extend(
{
	attributes: {
		"multiline": {
			"ignore": true
		}
	},

	/**
	 * Regexes for validating email addresses incl. email in angle-brackets eg.
	 * + "Ralf Becker <rb@stylite.de>"
	 * + "Ralf Becker (Stylite AG) <rb@stylite.de>"
	 * + "<rb@stylite.de>" or "rb@stylite.de"
	 * + '"Becker, Ralf" <rb@stylite.de>'
	 * + "'Becker, Ralf' <rb@stylite.de>"
	 * but NOT:
	 * - "Becker, Ralf <rb@stylite.de>" (contains comma outside " or ' enclosed block)
	 * - "Becker < Ralf <rb@stylite.de>" (contains <    ----------- " ---------------)
	 *
	 * About umlaut or IDN domains: we currently only allow German umlauts in domain part!
	 *
	 * Using \042 instead of " to NOT stall minifyer!
	 *
	 * Same preg is in etemplate_widget_url PHP class!
	 */
	EMAIL_PREG: new RegExp(/^(([^\042',<][^,<]+|\042[^\042]+\042|\'[^\']+\'|)\s?<)?[^\x00-\x20()<>@,;:\042\[\]]+@([a-z0-9ÄÖÜäöüß](|[a-z0-9ÄÖÜäöüß_-]*[a-z0-9ÄÖÜäöüß])\.)+[a-z]{2,6}>?$/i),
	/**
	 * @memberOf et2_url
	 */
	createInputWidget: function() {
		this.input = $j(document.createElement("input"))
			.blur(this,this.validate)
			.blur(this,function(e){e.data.set_value(e.data.getValue());});

		this._button = null;

		if(this.size) {
			this.set_size(this.size);
		}

		this.setDOMNode(this.input[0]);
	},

	destroy: function() {
		if(this.input) {
			this.input.unbind();
		}
		this._button = null;
		this._super.apply(this);
	},

	/**
	 * Override parent to update href of 'button'
	 *
	 * @param _value value to set
	 */
	set_value: function(_value) {
		this.update_button(_value);
		this._super.apply(this, arguments);
	},

	update_button: function(_value) {
		if(this.value == _value) return;
		if(_value)
		{
			// Create button if it doesn't exist yet
			if(this._button == null)
			{
				this._button = $j(document.createElement("a")).addClass("et2_url");
				this.getSurroundings().insertDOMNode(this._button[0]);
				this.getSurroundings().update();
			}
			this._button.removeClass("url phone email").removeAttr("href");
			_value = this.get_link(this._type, _value);
			switch(this._type)
			{
				case "url":
					// Silently use http if no protocol
					this._button.attr("href", _value).attr("target", "_blank").addClass("url");
					break;
				case "url-phone":
					if(_value) {
						if(typeof _value == 'function')
						{
							this._button.click(this, _value).addClass("phone").show();
						}
						else
						{
							this._button.attr("href", _value).addClass("phone").show();
						}
					} else if (_value === false) {
						// Can't make a good handler, hide button
						this._button.hide();
					}
					break;
				case "url-email":
					if(typeof _value == 'function')
					{
						this._button.click(this, _value).addClass("email");
					}
					else
					{
						this._button.attr("href", _value).addClass("email");
					}
					break;
			}
		}
		else
		{
			if(this._button) this._button.hide();
			if(this._button && this.getSurroundings && this.getSurroundings().removeDOMNode)
			{
				this.getSurroundings().removeDOMNode(this._button[0]);
			}
			this._button = null;
		}
	},

	get_link: function(type, value) {
		if(!value) return false;
		switch(type)
		{
			case "url":
				// Silently use http if no protocol
				if(value.indexOf("://") == -1) value = "http://"+value;
				break;
			case "url-phone":
				// Clean number
				value = value.replace('&#9829;','').replace('(0)','');
				value = value.replace(/[abc]/gi,2).replace(/[def]/gi,3).replace(/[ghi]/gi,4).replace(/[jkl]/gi,5).replace(/[mno]/gi,6);
				value = value.replace(/[pqrs]/gi,7).replace(/[tuv]/gi,8).replace(/[wxyz]/gi,9);

				// movile Webkit (iPhone, Android) have precedence over server configuration!
				if (navigator.userAgent.indexOf('AppleWebKit') !== -1 &&
					(navigator.userAgent.indexOf("iPhone") !== -1 || navigator.userAgent.indexOf("Android") !== -1) &&
					value.indexOf("tel:") == -1)
				{
					 value = "tel:"+value;
				}
				else if (this.egw().config("call_link"))
				{
					var link = this.egw().config("call_link")
						.replace("%1", encodeURIComponent(value))
						.replace("%u",this.egw().user('account_lid'))
						.replace("%t",this.egw().user('account_phone'));
					var popup = this.egw().config("call_popup");
					value = function() { egw.open_link(link, 'phonecall', popup); };
				}
				else {
					// Can't make a good handler
					return false;
				}
				break;
			case "url-email":
				if(value.indexOf("mailto:") == -1)
				{
					value = "mailto:"+value;
				}
				if((this.egw().user('apps').mail || this.egw().user('apps').felamimail) &&
					this.egw().preference('force_mailto','addressbook') != '1' )
				{
					return function() {egw.open_link(value);};
				}
				break;
		}

		return value;
	},

	validate: function(e) {
		e.data.hideMessage();

		if(e.data._super) {
			e.data._super.apply(this, arguments);
		}

		// Check value, and correct if possible
		var value = jQuery.trim(e.data.getValue());
		if(value == "") return;
		switch(e.data._type) {
			case "url":
				if(value.indexOf("://") == -1) {
					e.data.set_value("http://"+value);
					e.data.showMessage(e.data.egw().lang("Protocol is required"), "hint", true);
				}
				break;
			case "url-email":
				if(!e.data.EMAIL_PREG.test(value) ||
					// If they use Text <email>, make sure the <> match
					(value.indexOf("<") > 0 && value.indexOf(">") != value.length-1) ||
					(value.indexOf(">") > 0 && value.indexOf("<") < 0)
				)
				{
					e.data.showMessage("Invalid email","validation_error",true);
				}
		}
	},

	attachToDOM: function()
	{
		this._super.apply(this, arguments);

		if (this.input[0].parentNode) jQuery(this.input[0].parentNode).addClass('et2_url_span');
	}
});
et2_register_widget(et2_url, ["url", "url-email", "url-phone"]);

/**
 * et2_url_ro is the readonly implementation of the url, email & phone.
 * It renders things as links, when possible
 *
 * @augments et2_valueWidget
 */
var et2_url_ro = et2_valueWidget.extend([et2_IDetachedDOM],
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_url_ro
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("a"))
			.addClass("et2_textbox readonly");
		if(this._type == 'url-email')
		{
			this.span.addClass('et2_email');
		}
		this.setDOMNode(this.span[0]);
	},

	set_value: function(_value) {
		this.value = _value;

		var link = et2_url.prototype.get_link(this._type, _value);

		if(!link)
		{
			this.span.text(_value);
			this.span.removeAttr("href");
			return;
		}
		this.span.text(_value);
		switch(this._type) {
			case "url":
				this.span.attr("href", link).attr("target", "_blank");
				break;
			case "url-phone":
				if(typeof link == 'function')
				{
					this.span.off('click.et2_url');
					this.span.on('click.et2_url', link);
					this.span.attr("href", "#");
				}
				else if (link)
				{
					this.span.attr("href", link);
				}
				break;
			case "url-email":
				if(typeof link == 'function')
				{
					this.span.off('click.et2_url');
					this.span.on('click.et2_url', link);
					this.span.removeAttr("href");
				}
				else
				{
					this.span.attr("href", link);
					if(!this.span.attr("target"))
					{
					    this.span.attr("target", "_blank");
					}
				}
				break;
		}
	},

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes: function(_attrs)
	{
		_attrs.push("value", "class");
	},

	getDetachedNodes: function()
	{
		return [this.span[0]];
	},

	setDetachedAttributes: function(_nodes, _values)
	{
		// Update the properties
		this.span = jQuery(_nodes[0]);
		if (typeof _values["value"] != "undefined")
		{
			this.set_value(_values["value"]);
		}
		if (typeof _values["class"] != "undefined")
		{
			_nodes[0].setAttribute("class", _values["class"]);
		}
	}
});
et2_register_widget(et2_url_ro, ["url_ro", "url-email_ro", "url-phone_ro"]);