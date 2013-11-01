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
app.importexport = AppJS.extend(
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
		if(!test.getValue()) return true;
		
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
	}
});