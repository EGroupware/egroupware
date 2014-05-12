/**
 * EGroupware emailadmin static javascript code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package emailadmin
 * @link http://www.egroupware.org
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

/**
 * UI for emailadmin
 *
 * @augments AppJS
 */
app.classes.emailadmin = AppJS.extend(
{
	appname: 'emailadmin',
	/**
	 * No SSL
	 */
	SSL_NONE: 0,
	/**
	 * STARTTLS on regular tcp connection/port
	 */
	SSL_STARTTLS: 1,
	/**
	 * SSL (inferior to TLS!)
	 */
	SSL_SSL: 3,
	/**
	 * require TLS version 1+, no SSL version 2 or 3
	 */
	SSL_TLS: 2,
	/**
	 * if set, verify certifcate (currently not implemented in Horde_Imap_Client!)
	 */
	SSL_VERIFY: 8,

	/**
	 * Constructor
	 *
	 * @memberOf app.emailadmin
	 */
	init: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 */
	et2_ready: function(et2)
	{
		// call parent
		this._super.apply(this, arguments);

		for (var t in et2.templates)
		{
			//alert(t); // as we iterate through this more than once, ... we separate trigger and action
			switch (t)
			{
				case 'emailadmin.account':
					this.account_hide_not_applying();
					break;
			}
		}
	},

	/**
	 * Switch account wizard to manual entry
	 */
	wizard_manual: function()
	{
		jQuery('tr.emailadmin_manual').fadeToggle();// not sure how to to this et2-isch
	},

	/**
	 * onclick for continue button to show progress animation
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	wizard_detect: function(_event, _widget)
	{
		// we need to do a manual asynchronious submit to show progress animation
		// default synchronious submit stops animation!
		if (this.et2._inst.submit('button[continue]', true))	// true = async submit
		{
			var sieve_enabled = this.et2.getWidgetById('acc_sieve_enabled');
			if (!sieve_enabled || sieve_enabled.get_value())
			{
				jQuery('td.emailadmin_progress').show();
			}
		}
		return false;
	},

	/**
	 * Set default port, if imap ssl-type changes
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	wizard_imap_ssl_onchange: function(_event, _widget)
	{
		var ssl_type = _widget.get_value();
		this.et2.getWidgetById('acc_imap_port').set_value(
			ssl_type == this.SSL_SSL || ssl_type == this.SSL_TLS ? 993 : 143);
	},

	/**
	 * Set default port, if imap ssl-type changes
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	wizard_smtp_ssl_onchange: function(_event, _widget)
	{
		var ssl_type = _widget.get_value();
		this.et2.getWidgetById('acc_smtp_port').set_value(
			ssl_type == 'no' ? 25 : (ssl_type == this.SSL_SSL || ssl_type == this.SSL_TLS ? 465 : 587));
	},

	/**
	 * Set default port, if imap ssl-type changes
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	wizard_sieve_ssl_onchange: function(_event, _widget)
	{
		var ssl_type = _widget.get_value();
		this.et2.getWidgetById('acc_sieve_port').set_value(
			ssl_type == this.SSL_SSL || ssl_type == this.SSL_TLS ? 5190 : 4190);
		this.wizard_sieve_onchange(_event, _widget);
	},

	/**
	 * Enable sieve, if user changes some setting
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	wizard_sieve_onchange: function(_event, _widget)
	{
		this.et2.getWidgetById('acc_sieve_enabled').set_value(1);
	},

	/**
	 * Switch to select multiple accounts
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	edit_multiple: function(_event, _widget)
	{
		// hide multiple button
		_widget.set_disabled(true);

		// switch account-selection to multiple
		var account_id = this.et2.getWidgetById('account_id');
		account_id.set_multiple(true);
	},

	/**
	 * Hide not applying fields, used as:
	 * - onchange handler on account_id
	 * - called from et2_ready for emailadmin.account template
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	account_hide_not_applying: function(_event, _widget)
	{
		var account_id = this.et2.getWidgetById('account_id');
		var ids = account_id && account_id.get_value ? account_id.get_value() : [];
		if (typeof ids == 'string') ids = ids.split(',');

		var multiple = ids.length >= 2 || ids[0] === '';
		//alert('multiple='+(multiple?'true':'false')+': '+ids.join(','));

		// initial call
		if (typeof _widget == 'undefined')
		{
			if (!multiple)
			{
				jQuery('.emailadmin_no_single').hide();
			}
			if (!this.egw.user('apps').emailadmin)
			{
				jQuery('.emailadmin_no_user,#button\\[multiple\\]').hide();
			}
			if (ids.length == 1)
			{
				// switch back to single selectbox
				account_id.set_multiple(false);
				this.et2.getWidgetById('button[multiple]').set_disabled(false);
			}
		}
		// switched to single user
		else if (!multiple)
		{
			jQuery('.emailadmin_no_single').fadeOut();
			// switch back to single selectbox
			account_id.set_multiple(false);
			this.et2.getWidgetById('button[multiple]').set_disabled(false);
		}
		// switched to multiple user
		else
		{
			jQuery('.emailadmin_no_single').fadeIn();
		}
		if (_event && _event.stopPropagation) _event.stopPropagation();
		return false;
	},

	/**
	 * Callback if user changed account selction
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	change_account: function(_event, _widget)
	{
		// todo check dirty and query user to a) save changes, b) discard changes, c) cancel selection
		_widget.getInstanceManager().submit();
	}
});

function disableGroupSelector()
{
	//alert('Group'+document.getElementById('exec[ea_group]').value+' User'+document.getElementById('eT_accountsel_exec_ea_user').value);
	if (document.getElementById('eT_accountsel_exec_ea_user').value != '')
	{
		if (document.getElementById('exec[ea_group]').value != '') document.getElementById('exec[ea_group]').value = '';
		document.getElementById('exec[ea_group]').disabled = true;
	}
	else
	{
		document.getElementById('exec[ea_group]').disabled = false;
	}
}

function addRow(_selectBoxName, _prompt) {
	result = prompt(_prompt, '');

	if((result == '') || (result == null)) {
		return false;
	}

	var newOption = new Option(result, result);

	selectBox = document.getElementById(_selectBoxName);
	var length      = selectBox.length;

	selectBox.options[length] = newOption;
	selectBox.selectedIndex = length;
}

function editRow(_selectBoxName, _prompt) {
	selectBox = document.getElementById(_selectBoxName);

	selectedItem = selectBox.selectedIndex;

	if(selectedItem != null && selectedItem != -1) {
		value = selectBox.options[selectedItem].text;
		result = prompt(_prompt, value);

		if((result == '') || (result == null)) {
			return false;
		}

		var newOption = new Option(result, result);

		selectBox.options[selectedItem] = newOption;
		selectBox.selectedIndex = selectedItem;
	}
}

function removeRow(_selectBoxName) {
	selectBox = document.getElementById(_selectBoxName);

	selectedItem = selectBox.selectedIndex;
	if(selectedItem != null) {
		selectBox.options[selectedItem] = null;
	}
	selectedItem--;
	if(selectedItem >= 0) {
		selectBox.selectedIndex = selectedItem;
	} else if (selectBox.length > 0) {
		selectBox.selectedIndex = 0;
	}
}

function selectAllOptions(_selectBoxName) {
	selectBox = document.getElementById(_selectBoxName);

	for(var i=0;i<selectBox.length;i++) {
		selectBox[i].selected=true;
	}

}
