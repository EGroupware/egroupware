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
import type {Et2Nextmatch} from "../../api/js/etemplate/Et2Nextmatch/Et2Nextmatch";
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
	 * Last value of each allowed users select, see allowed_users_change()
	 */
	private allowed_users_previous = new WeakMap<object, string[]>();

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
		else if(_name == "importexport.definition_index")
		{
			// app.admin is typed generically as EgwApp; enableAppToolbar() is AdminApp-specific
			const admin = <any>app.admin;
			if(_et2.DOMContainer?.closest("egw-app#admin") && typeof admin?.enableAppToolbar === "function")
			{
				// Shown inside Admin: put the Add button into Admin's app header, as its own lists do
				admin.enableAppToolbar(_et2, _name);
			}
			else
			{
				// Preferences (users without admin rights) has nothing to move it into the app
				// header, so show it in the nextmatch's own header instead
				const header = <HTMLElement><unknown>_et2.widgetContainer.getWidgetById(_name + ".header");
				const nm = _et2.DOMContainer?.querySelector("et2-nextmatch");
				if(header && nm)
				{
					header.slot = "header";
					nm.append(header);
				}
			}
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
		// Set the widget's value via its own API (set_value()), not by touching its DOM children
		// directly - Et2Html renders into the light DOM via Lit, and mutating those children with
		// replaceChildren()/insertAdjacentHTML() corrupts Lit's internal bookkeeping for that
		// instance, causing a "Cannot read properties of null (reading 'insertBefore')" crash the
		// next time the widget's value is set the normal way (eg. by the server response below).
		const contentWidget = widget.getRoot().getWidgetById('preview-box');
		if(contentWidget) contentWidget.set_value('<div class="loading" style="width:100%;height:100%"></div>');

		// jQuery's animated .show(100, callback) has no simple native equivalent (a CSS-transition
		// based rewrite is out of scope for this pass) - show immediately and run the callback right
		// away instead of after the 100ms animation.
		// Note: an empty string here only clears an inline override - it does NOT make the element
		// visible if something else (a stylesheet rule, or simply the element never having an
		// explicit display before) leaves it computing to "none" - jQuery's .show() used to paper
		// over that by computing and setting a real display value. preview_box is an et2-vbox
		// (flex layout), so set that explicitly instead of relying on the cascade.
		preview.style.display = 'flex';
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

		// Show preview - see export_preview() above for why this is 'flex', not ''
		const preview = (<any>widget.getRoot().getWidgetById('preview_box')).getDOMNode();
		// TD gets the class too
		preview.style.display = 'flex';
		// See export_preview() above for why this goes through set_value() and not the DOM directly
		const contentWidget = widget.getRoot().getWidgetById('preview');
		if(contentWidget) contentWidget.set_value(this.egw.lang("Please wait..."));
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
	 *
	 * The select's value comes in option order, not in the order the user picked, and 'Just me' /
	 * 'All users' are the last options - so the option just picked is found by comparing with the
	 * value before this change, not by its position.
	 */
	allowed_users_change(event, widget)
	{
		const value : string[] = [...(widget.getValue() || [])];
		const specials = ['', 'all'];
		// Before the first change, the previous value is what the server sent
		const previous : string[] = this.allowed_users_previous.get(widget) ??
			[].concat(widget.getArrayMgr("content")?.getEntry(widget.id) ?? []).map(v => v === null ? '' : String(v));
		const added = value.filter(v => !previous.includes(v));

		let changed = value;
		const special = added.find(v => specials.includes(v));
		if(typeof special !== "undefined")
		{
			// Just picked all/private, clear the others
			changed = [special];
		}
		else if(added.length)
		{
			// Just picked a group, clear the specials
			changed = value.filter(v => !specials.includes(v));
		}
		this.allowed_users_previous.set(widget, changed);

		if(changed.length != value.length)
		{
			// Don't jump it to the top, it's weird
			widget.selected_first = false;
			widget.set_value(changed);
		}
	}

	/**
	 * Open a specific import/export definition dialog by clicking on the icon from the list
	 *
	 * Row widgets share the nextmatch's content manager rather than a per-row perspective, so the
	 * row is found from the DOM: its data-row-id is the same uid the "Execute" action gets.
	 *
	 * @param event
	 * @param widget the clicked icon
	 */
	open_definition(event, widget)
	{
		const id = (<HTMLElement>widget).closest?.('[data-row-id]')?.getAttribute('data-row-id');
		if(id)
		{
			this.run_definition(null, [{id}]);
		}
	}

	/**
	 * Submit one of the definition list's "Change" popups (owner, allowed users)
	 *
	 * The popups are real <et2-dialog>s, so Et2NextmatchActionController.openActionPopup() just sets
	 * their .selectedIds and shows them; the window.nm_popup_action/nm_popup_ids globals the legacy
	 * nm_submit_popup() relied on are never set.  executeAction() triggers the normal whole-template
	 * submit, so the dialog's fields arrive as $content['owner_popup'] / $content['allowed_popup'],
	 * with the nextmatch's action, selection and "select all" merged in.
	 *
	 * @param _event
	 * @param _widget the clicked button
	 * @param _action_id the nm action the popup was opened for, "owner" or "allowed"
	 * @return false to stop the button's own submit
	 */
	submit_popup(_event : Event, _widget, _action_id : string) : boolean
	{
		const dialog = <any>_widget.closest('et2-dialog');
		const nm = <Et2Nextmatch>_widget.getInstanceManager()?.widgetContainer?.getWidgetById('nm');
		if(!nm)
		{
			return false;
		}
		// Prefer the live selection - it still carries "select all", which the dialog's
		// .selectedIds (a plain array of ids) does not
		const selection = nm.getSelection();
		if(!selection.all && dialog?.selectedIds?.length)
		{
			selection.ids = dialog.selectedIds;
		}
		nm.executeAction(_action_id, selection, {nmAction: "submit"});
		dialog?.close();
		return false;
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
