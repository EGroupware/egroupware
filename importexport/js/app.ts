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


import {EgwApp} from '../../api/js/jsapi/egw_app';
// egw is an ambient global (declare global {} in egw_global.d.ts, unconditionally included via
// tsconfig's "**/*.d.ts") - no import needed or possible.

/**
 * JS for Import/Export
 *
 * @augments EgwApp
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
				// getDOMWidgetById() is typed as returning "typeof Et2Widget" (the mixin function)
				// instead of a widget instance - a known framework typing bug (see
				// doc/ai/projects/app-ts-modernization.md) - <any> cast to work around it
				(<any>this.et2.getDOMWidgetById('export')).getDOMNode().setAttribute('disabled', 'disabled');
				(<any>this.et2.getDOMWidgetById('preview')).getDOMNode().setAttribute('disabled', 'disabled');
			}
			if(!this.et2.getArrayMgr("content").getEntry("filter"))
			{
				document.querySelectorAll<HTMLElement>('input[value="filter"]').forEach(el => {
					if(el.parentElement) el.parentElement.style.display = 'none';
				});
			}

			// Disable / hide definition filter if not selected
			if(this.et2.getArrayMgr("content").getEntry("selection") != 'filter')
			{
				document.querySelectorAll<HTMLElement>('div.filters').forEach(el => el.style.display = 'none');
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
		const preview = (<any>widget.getRoot().getWidgetById('preview_box')).getDOMNode();
		// TD gets the class too
		if(preview.parentElement) preview.parentElement.style.display = '';
		const content = preview.querySelector('.content');
		if(content)
		{
			content.replaceChildren();
			content.insertAdjacentHTML('beforeend', '<div class="loading" style="width:100%;height:100%"></div>');
		}

		// jQuery's animated .show(100, callback) has no simple native equivalent (a CSS-transition
		// based rewrite is out of scope for this pass) - show immediately and run the callback right
		// away instead of after the 100ms animation
		preview.style.display = '';
		widget.clicked = true;
		widget.getInstanceManager().submit(false, true);
		widget.clicked = false;
		return false;
	}

	import_preview(event, widget)
	{
		const test = widget.getRoot().getWidgetById('dry-run');
		if(test.getValue() == test.options.unselected_value)
		{
			return true;
		}

		// Show preview
		const preview = (<any>widget.getRoot().getWidgetById('preview_box')).getDOMNode();
		// TD gets the class too
		preview.style.display = '';
		const content = preview.querySelector('.content');
		if(content) content.textContent = this.egw.lang("Please wait...");
		preview.classList.remove("hideme");
		preview.classList.add('loading');
		// jQuery's animated .show(100, callback) dropped, see export_preview() above
		widget.clicked = true;
		widget.getInstanceManager().submit(false, true);
		widget.clicked = false;
		preview.classList.remove('loading');
		return false;
	}

	closePreview()
	{
		const preview = this.et2.getWidgetById("preview_box");

		// TD gets the class too
		if(preview) (<HTMLElement>(<unknown>preview)).style.display = 'none';
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
		// getDOMWidgetById() is typed as returning "typeof Et2Widget" (the mixin function) instead
		// of a widget instance - same known framework typing bug as et2_ready() above - <any> cast
		const progress_box = <any>this.et2.getDOMWidgetById("progress_box");
		progress_box.classList.remove("hideme");

		const preview_box = <any>this.et2.getDOMWidgetById("preview_box");
		preview_box.classList.add("hideme");

		// progress_record/sl-progress-bar/import_log are all read/write dynamically at runtime with
		// no matching typed widget shape (progress_record's .value isn't declared anywhere,
		// sl-progress-bar isn't an etemplate widget at all) - <any> cast, same as other framework
		// gaps documented in doc/ai/projects/app-ts-modernization.md
		const record : any = progress_box.getWidgetById("progress_record");
		record.value = progress.label || "";

		// sl-progress-bar is not an etemplate widget and chokes the server processing if we put it in the xet
		let bar : any = progress_box.querySelector("sl-progress-bar");
		if(!bar)
		{
			bar = document.createElement("sl-progress-bar");
			progress_box.insertBefore(bar, record.nextSibling);
		}

		bar.indeterminate = !Number.isInteger(progress.progress);
		bar.value = progress.progress || 0;

		if(progress.log)
		{
			const log : any = <any>this.et2.getDOMWidgetById("import_log");
			log.value = log.value + "\n" + progress.log;
			// Try to scroll to bottom
			const text = log.shadowRoot.querySelector("textarea");
			text.scrollTop = text.scrollHeight + 200;
		}
	}

	_closeProgress()
	{
		const progress_box = <any>this.et2.getDOMWidgetById("progress_box");
		progress_box.classList.add("hideme");
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

		const id = selected[0].id || null;
		const data = egw.dataGetUIDdata(id).data;
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
		let value = widget.getValue();

		// Only 1 selected, no checking needed
		if(value == null || value.length <= 1)
		{
			return;
		}

		// Don't jump it to the top, it's weird
		widget.selected_first = false;

		let index = null;
		const specials = ['', 'all']
		for(let i = 0; i < specials.length; i++)
		{
			const special = specials[i];
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
