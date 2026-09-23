/**
 * EGroupware eTemplate2 - add the selected contacts to, or remove them from, a distribution list
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import type {EgwAction} from "../../egw_action/EgwAction";
import type {EgwActionObject} from "../../egw_action/EgwActionObject";

/**
 * What Nextmatch-driven apps put in data['distributionLists']
 */
export interface DistributionListSettings
{
	/** menuaction returning [{value, label}] - called when the dialog opens, not shipped per row */
	optionsMenuaction : string;
	/** action id prefixes, so the ids the app's own action() understands are built here */
	addPrefix : string;
	removePrefix : string;
}

/**
 * Backs `nm_action = "distribution_lists"`.
 *
 * Replaces a sub-menu with one entry per distribution list, plus a separate
 * "Remove from distribution list" entry, with a single picker offering both verbs.
 *
 * It also closes a real usability hole. The old "Remove from distribution list" carried no list
 * id at all: the server fell back to `$query['filter2']`, ie. whichever list the filter dropdown
 * happened to be showing. That is why the entry had to be disabled unless a list was selected
 * there, and why it was impossible to remove a contact from a list you were not already filtered
 * to. Here the list is named explicitly, so the action means what it says.
 *
 * The options are fetched when the dialog opens rather than travelling with every get_rows()
 * response - the point of replacing a per-list sub-menu is not to send the lists at all.
 */
export class DistributionListAction
{
	static async open(egw, action : EgwAction, senders : EgwActionObject[] = [])
	{
		const settings : DistributionListSettings = action.data?.distributionLists;
		if(!settings)
		{
			return;
		}
		const nm = action.parent?.data?.nextmatch || action.data?.nextmatch;
		const all = nm?.getSelection?.().all === true;

		let options = [];
		try
		{
			options = <any>await egw.request(settings.optionsMenuaction, [true]) || [];
		}
		catch(e)
		{
			egw.message(egw.lang("Nothing available"), "info");
			return;
		}
		if(!options.length)
		{
			egw.message(egw.lang("Nothing available"), "info");
			return;
		}

		const ADD = "add", REMOVE = "remove";
		const dialog = new Et2Dialog(egw);
		dialog.transformAttributes({
			title: (<any>action).caption || egw.lang("Distribution lists"),
			buttons: [
				{button_id: ADD, id: "dialog[add]", label: egw.lang("Add"), image: "add", "default": true},
				{button_id: REMOVE, id: "dialog[remove]", label: egw.lang("Remove"), image: "delete"},
				{button_id: Et2Dialog.CANCEL_BUTTON, id: "dialog[cancel]", label: egw.lang("Cancel"), image: "cancel"}
			],
			width: 400,
			value: {
				content: {
					summary: senders.length > 1 || all ?
							 egw.lang("%1 selected entries", all ? "" : senders.length).trim() : "",
					selected: ""
				},
				sel_options: {selected: options}
			},
			template: egw.webserverUrl + "/api/templates/default/distribution_list_action.xet"
		});
		document.body.appendChild(dialog);
		await dialog.updateComplete;

		const select : any = (<any>dialog)._template_widget?.widgetContainer?.getWidgetById("selected");
		if(select)
		{
			// nothing preselected: without an empty option et2-select falls back to the first,
			// and OK-without-choosing would act on whichever list sorted first
			select.emptyLabel = egw.lang("Select one");
			select.value = "";
		}

		const [rawButton, content] = await dialog.getComplete();
		dialog.remove();
		// button_id is declared number|Object, but our verb buttons use string ids
		const button = <unknown>rawButton as string;
		const list = (<any>content)?.selected;
		if((button !== ADD && button !== REMOVE) || !list)
		{
			return;
		}

		const ids = senders.map(sender => (sender?.id || "").split("::").pop()).filter(Boolean);
		const menuaction = action.data?.menuaction || "addressbook.addressbook_ui.ajax_action";
		const prefix = button === ADD ? settings.addPrefix : settings.removePrefix;

		return egw.request(menuaction, [prefix + list, ids, all, {}]);
	}
}
