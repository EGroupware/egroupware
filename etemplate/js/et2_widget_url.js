/**
 * eGroupWare eTemplate2 - JS URL object
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
*/

/**
 * Class which implements the "url" XET-Tag, which covers URLs, email & phone
 */ 
var et2_url = et2_textbox.extend({

	attributes: {
		"multiline": {
			"ignore": true
		}
	},

	createInputWidget: function() {
		this.input = $j(document.createElement("input"))
			.blur(this,this.validate)
			.blur(this,function(e){e.data.set_value(e.data.getValue());});

		this._button = null;

		console.log(this);

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
	},

	/**
	 * Override parent to update href of 'button'
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
			switch(this._type)
			{
				case "url":
					// Silently use http if no protocol
					if(_value.indexOf("://") == -1) _value = "http://"+_value;
					this._button.attr("href", _value).attr("target", "_blank").addClass("url");
					break;
				case "url-phone":
					if(navigator.userAgent.indexOf('AppleWebKit') !== -1 && (
							navigator.userAgent.indexOf("iPhone") !== -1 ||
							navigator.userAgent.indexOf("Android") !== -1 
						) &&
						 _value.indexOf("tel:") == -1)
					{
						 _value = "tel:"+_value;
						this._button.attr("href", _value).addClass("phone").show();
					} else if (false) {
						// TODO: Check for telephony config, use link from server
						//this._button.attr("href", _value).addClass("phone").show();
					} else {
						// Can't make a good handler, hide button
						this._button.hide();
					}
					break;
				case "url-email":
					if(egw.link_registry && egw.link_registry.felamimail)
					{
						this._button.click(this, function() {
							egw.open("","felamimail","add","send_to="+_value);
						}).addClass("email");
					}
					else if(_value.indexOf("mailto:") == -1)
					{
						_value = "mailto:"+_value;
						this._button.attr("href", _value).addClass("email");
					}
					break;
			}
		}
		else
		{
			if(this._button)
			{
				this.getSurroundings().deleteDOMNode(this._button[0]);
			}
			this._button = null;
		}
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
					e.data.showMessage(egw.lang("Protocol is required"), "hint", true);
				}
				break;
		}
	}
});

et2_register_widget(et2_url, ["url", "url-email", "url-phone"]);

/**
 * et2_url_ro is the readonly implementation of the url, email & phone.
 * It renders things as links, when possible
 */
var et2_url_ro = et2_valueWidget.extend({

	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("a"))
			.addClass("et2_textbox readonly");

		this.setDOMNode(this.span[0]);
	},

	set_value: function(_value) {
		this.value = _value;

		this.span.text(_value);
		switch(this._type) {
			case "url":
				// Silently use http if no protocol
				if(_value.indexOf("://") == -1) _value = "http://"+_value;
				this.span.attr("href", _value).attr("target", "_blank");
				break;
			case "url-phone":
				if(navigator.userAgent.indexOf('AppleWebKit') !== -1 && (
						navigator.userAgent.indexOf("iPhone") !== -1 ||
						navigator.userAgent.indexOf("Android") !== -1 
				) {
					if(_value.indexOf("tel:") == -1) _value = "tel:"+_value;
					this.span.attr("href", _value);
				} else {
					//TODO: Check for telephony integration, use link from server
				}
				break;
			case "url-email":
				if(_value.indexOf("mailto:") == -1) _value = "mailto:"+_value;
				this.span.attr("href", _value);
				break;
		}
	}

});

et2_register_widget(et2_url_ro, ["url_ro", "url-email_ro", "url-phone_ro"]);

