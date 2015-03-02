/**
 * EGroupware - Import/Export - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @author Nathan Gray
 * @copyright (c) 2013 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * JS for Import/Export
 *
 * @augments AppJS
 */
app.classes.importexport = AppJS.extend(
{
	appname: 'importexport',

	/**
	 * Constructor
	 *
	 * @memberOf app.timesheet
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

		if(this.et2.getWidgetById('export') && !this.et2.getArrayMgr("content").getEntry("definition"))
		{
			// et2 doesn't understand a disabled button in the normal sense
			$j(this.et2.getWidgetById('export').getDOMNode()).attr('disabled','disabled');
			$j(this.et2.getWidgetById('preview').getDOMNode()).attr('disabled','disabled');
		}
	},

	export_preview: function(event, widget)
	{
		var preview = $j(widget.getRoot().getWidgetById('preview_box').getDOMNode());
		$j('.content',preview).empty();

		preview
			.addClass('loading')
			.show(100, jQuery.proxy(function() {
				widget.clicked = true;
				widget.getInstanceManager().submit(false, true);
				widget.clicked = false;
				$j(widget.getRoot().getWidgetById('preview_box').getDOMNode())
					.removeClass('loading');
			},this));
		return false;
	},

	import_preview: function(event, widget)
	{
		var test = widget.getRoot().getWidgetById('dry-run');
		if(test.getValue() == test.options.unselected_value) return true;

		// Show preview
		var preview = $j(widget.getRoot().getWidgetById('preview_box').getDOMNode());
		$j('.content',preview).empty();
		preview
			.addClass('loading')
			.show(100, jQuery.proxy(function() {
				widget.clicked = true;
				widget.getInstanceManager().submit(false, true);
					widget.clicked = false;
					$j(widget.getRoot().getWidgetById('preview_box').getDOMNode())
						.removeClass('loading');
			},this));
		return false;
	},

	/**
	 * Open a popup to run a given definition
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	run_definition: function(action, selected)
	{
		if(!selected || selected.length != 1) return;

		var id = selected[0].id||null;
		var data = egw.dataGetUIDdata(id).data;
		if(!data || !data.type) return;

		egw.open_link(egw.link('/index.php',{
			menuaction: 'importexport.importexport_' + data.type + '_ui.' + data.type + '_dialog',
			appname: data.application,
			definition: data.definition_id
		}), false, '850x440', app);
	},

	/**
	 * Allowed users widget has been changed, if 'All users' or 'Just me'
	 * was selected, turn off any other options.
	 */
	allowed_users_change: function(node, widget)
	{
		var value = widget.getValue();

		// Only 1 selected, no checking needed
		if(value == null || value.length <= 1) return;

		// Don't jump it to the top, it's weird
		widget.selected_first = false;

		var index = null;
		var specials = ['','all']
		for(var i = 0; i < specials.length; i++)
		{
			var special = specials[i];
			if((index = value.indexOf(special)) >= 0)
			{
				if(window.event.target.value == special)
				{
					// Just clicked all/private, clear the others
					value = [special];
				}
				else
				{
					// Just added another, clear special
					value.splice(index,1);
				}

				// A little highlight to call attention to the change
				$j('input[value="'+special+'"]',node).parent().parent().effect('highlight',{},500);
				break;
			}
		}
		if(index >= 0)
		{
			widget.set_value(value);
		}
	}
});