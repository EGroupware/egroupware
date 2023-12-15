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


import "../../vendor/bower-asset/jquery/dist/jquery.min.js";
//import "../../vendor/bower-asset/jquery-ui/jquery-ui.js";
import {EgwApp} from '../../api/js/jsapi/egw_app';
import {egw} from "../../api/js/jsapi/egw_global";

/**
 * JS for Import/Export
 *
 * @augments AppJS
 */
class ImportExportApp extends EgwApp
{
	/**
	 * Constructor
	 *
	 * @memberOf app.infolog
	 */
	constructor()
	{
		// call parent
		super('importexport');
	}

	/**
	 * Destructor
	 */
	destroy(_app)
	{
		// call parent
		super.destroy(_app);
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} _et2 newly ready object
	 * @param {string} _name template name
	 */
	et2_ready(_et2, _name)
	{
		// call parent
		super.et2_ready(_et2, _name);

		if(this.et2.getWidgetById('export'))
		{
			if(!this.et2.getArrayMgr("content").getEntry("definition"))
			{
				// et2 doesn't understand a disabled button in the normal sense
				jQuery(this.et2.getDOMWidgetById('export').getDOMNode()).attr('disabled','disabled');
				jQuery(this.et2.getDOMWidgetById('preview').getDOMNode()).attr('disabled','disabled');
			}
			if(!this.et2.getArrayMgr("content").getEntry("filter"))
			{
				jQuery('input[value="filter"]').parent().hide();
			}

			// Disable / hide definition filter if not selected
			if(this.et2.getArrayMgr("content").getEntry("selection") != 'filter')
			{
				jQuery('div.filters').hide();
			}
		}
		else if(_name == "importexport.import_dialog")
		{
			// Store popup so we can find it from parent
			// Using _name only allows one import (at a time) to be updated
			this.egw.window.name = _name;
			this.egw.window.opener.egw.storeWindow(this.appname, this.egw.window);
		}
	}

	/**
	 * Callback to download the file without destroying the etemplate request
	 *
	 * @param data URL to get the export file
	 */
	download(data:string)
	{
		// Try to get the file to download in the parent window
		let app_templates = this.egw.top.etemplate2.getByApplication(framework.activeApp.appName);
		if(app_templates.length > 0)
		{
			app_templates[0].download(data);
		}
		else
		{
			// Couldn't download in opener, download here before popup closes
			this.et2.getInstanceManager().download(data);
		}
	}

	export_preview(event, widget)
	{
		var preview = jQuery(widget.getRoot().getWidgetById('preview_box').getDOMNode());
		// TD gets the class too
		preview.parent().show();
		jQuery('.content', preview).empty()
			.append('<div class="loading" style="width:100%;height:100%"></div>');

		preview
			.show(100, jQuery.proxy(function()
			{
				widget.clicked = true;
				widget.getInstanceManager().submit(false, true);
				widget.clicked = false;
			}, this));
		return false;
	}

	import_preview(event, widget)
	{
		var test = widget.getRoot().getWidgetById('dry-run');
		if(test.getValue() == test.options.unselected_value)
		{
			return true;
		}

		// Show preview
		var preview = jQuery(widget.getRoot().getWidgetById('preview_box').getDOMNode());
		// TD gets the class too
		preview.parent().show();
		jQuery('.content', preview).empty().text(this.egw.lang("Please wait..."));
		preview
			.removeClass("hideme")
			.addClass('loading')
			.show(100, jQuery.proxy(function()
			{
				widget.clicked = true;
				widget.getInstanceManager().submit(false, true);
				widget.clicked = false;
				jQuery(widget.getRoot().getWidgetById('preview_box').getDOMNode())
					.removeClass('loading');
			}, this));
		return false;
	}

	closePreview()
	{
		const preview = jQuery(this.et2.getWidgetById("preview_box"));

		// TD gets the class too
		preview.parent().hide();
	}

	progressUpdate(progress : ProgressUpdate)
	{
		const dialog = window.open('', "importexport.import_dialog");

		if(!dialog || !dialog.app?.importexport?.et2)
		{
			this.egw.message(this.egw.lang("Lost the dialog, no progress updates"), "warning");
			if(dialog)
			{
				dialog.close();
			}
			return;
		}
		// Find the template in the dialog and do the update there
		const et2 = dialog.app.importexport.et2;
		if(progress !== null)
		{
			dialog.app.importexport._doProgressUpdate(progress);
		}
		else
		{
			dialog.app.importexport._closeProgress();
		}
	}

	_doProgressUpdate(progress : ProgressUpdate)
	{
		const progress_box = this.et2.getDOMWidgetById("progress_box")
		progress_box.classList.remove("hideme");
		// Get the td too
		progress_box.parentNode.classList.remove("hideme");
		const preview_box = this.et2.getDOMWidgetById("preview_box").parentNode
		preview_box.classList.add("hideme");

		const record = progress_box.getWidgetById("progress_record");
		record.value = progress.label || "";

		// sl-progress-bar is not an etemplate widget and chokes the server processing if we put it in the xet
		let bar = progress_box.querySelector("sl-progress-bar");
		if(!bar)
		{
			bar = document.createElement("sl-progress-bar");
			progress_box.insertBefore(bar, record.nextSibling);
		}

		bar.indeterminate = !Number.isInteger(progress.progress);
		bar.value = progress.progress || 0;

		if(progress.log)
		{
			const log = this.et2.getDOMWidgetById("import_log")
			log.value = log.value + "\n" + progress.log;
			// Try to scroll to bottom
			const text = log.shadowRoot.querySelector("textarea");
			text.scrollTop = text.scrollHeight + 200;
		}
	}

	_closeProgress()
	{
		const progress_box = this.et2.getDOMWidgetById("progress_box")
		progress_box.parentNode.classList.add("hideme");
	}

	/**
	 * Open a popup to run a given definition
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	run_definition(action, selected)
	{
		if(!selected || selected.length != 1)
		{
			return;
		}

		var id = selected[0].id || null;
		var data = egw.dataGetUIDdata(id).data;
		if(!data || !data.type)
		{
			return;
		}

		egw.open_link(egw.link('/index.php', {
			menuaction: 'importexport.importexport_' + data.type + '_ui.' + data.type + '_dialog',
			appname: data.application,
			definition: data.definition_id
		}), "", '850x440', data.application);
	}

	/**
	 * Allowed users widget has been changed, if 'All users' or 'Just me'
	 * was selected, turn off any other options.
	 */
	allowed_users_change(event, widget)
	{
		var value = widget.getValue();

		// Only 1 selected, no checking needed
		if(value == null || value.length <= 1)
		{
			return;
		}

		// Don't jump it to the top, it's weird
		widget.selected_first = false;

		var index = null;
		var specials = ['', 'all']
		for(var i = 0; i < specials.length; i++)
		{
			var special = specials[i];
			if((index = value.indexOf(special)) >= 0)
			{
				if(value.indexOf(special) == value.length - 1)
				{
					// Just clicked all/private (it's at the end), clear the others
					value = [special];
				}
				else
				{
					// Just added another, clear special
					value.splice(index, 1);
				}
				break;
			}
		}
		if(index >= 0)
		{
			widget.set_value(value);
		}
	}

	/**
	 * Open a specific import/export definition dialog by clicking on the icon from the list
	 * @param widget
	 */
	open_definition(event, widget)
	{
		const mgr = widget.getArrayMgr("content");
		const data = mgr.getEntry("" + mgr.perspectiveData.row) || {};
		const type = data.type || "";
		const application = data.application || "";
		const definition_id = data.definition_id || "";
		this.egw.openPopup(
			this.egw.link("/index.php", {
				menuaction: "importexport.importexport_" + type + "_ui." + type + "_dialog",
				appname: application,
				definition: definition_id
			}),
			850, 440
		)
	}
}

app.classes.importexport = ImportExportApp;

interface ProgressUpdate
{
	// Update the progress bar
	progress : number | false;
	// Set label in progress bar
	label? : string;
	// Add something to the log
	log? : string;
}
