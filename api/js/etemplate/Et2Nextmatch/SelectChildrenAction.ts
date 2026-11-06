/**
 * EGroupware eTemplate2 - pick one of an over-long sub-menu's actions from a dialog
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import type {EgwAction} from "../../egw_action/EgwAction";
import type {EgwActionObject} from "../../egw_action/EgwActionObject";

export interface SelectChildrenOptions
{
	/** widget to pick with; default et2-select, or et2-tree-dropdown when the children nest */
	widget? : string;
	multiple? : boolean;
	title? : string;
	okLabel? : string;
	/** a whole different template, instead of api/templates/default/action_select.xet */
	template? : string;
}

/**
 * Backs `nm_action = "select_children"`, which Nextmatch::selectChildrenIfTooLong() puts on any
 * sub-menu with more children than DEFAULT_MAX_MENU_SELECT.
 *
 * A menu entry per row of user data - one per category, distribution list, addressbook, tracker
 * queue or kanban board - stops being usable long before it stops being generated, and the old
 * safety valve (fold the tail into a "More" sub-menu) only turned a long list into a deep one
 * with no search. This offers the same options as a searchable picker instead.
 *
 * The key property: it does not reimplement anything. The children are still real EgwActions -
 * hidden from the menu by EgwAction.appendToTree(), not removed - so picking one calls
 * `chosen.execute(senders)`, exactly what clicking the sub-menu entry did. Each child keeps its
 * own onExecute, nm_action, confirm and enabled, and this cannot drift from the sub-menu's
 * behaviour. It therefore works unchanged for children that still submit AND for ones already
 * converted to ajax.
 *
 * A plain static class + .xet (like LinkAction/Favorite), not a custom element: there is no
 * per-app render-time branching, just a picker and two buttons.
 */
export class SelectChildrenAction
{
	/**
	 * @param egw egw instance of the app the contextmenu was opened in
	 * @param action the container action, whose children are the options
	 * @param senders selected rows, passed straight through to the chosen child
	 * @param options overrides from the action's data['selectDialog']
	 */
	static async open(egw, action : EgwAction, senders : EgwActionObject[] = [], options : SelectChildrenOptions = {})
	{
		const children = (action.children || []).filter(child => !(<any>child).checkbox);
		// checkbox children are modifiers, not options: they change what picking one DOES
		// (move_to's "Copy instead of move", shared_with's "Share writable"). They have to come
		// along, or converting the menu would silently drop them.
		const modifiers = (action.children || []).filter(child => (<any>child).checkbox);

		if(!children.length)
		{
			return;
		}

		const enabledChildren = children.filter(child => SelectChildrenAction._isEnabled(child, senders));
		if(!enabledChildren.length)
		{
			egw.message(egw.lang("Nothing available"), "info");
			return;
		}

		// a nested option set (eg. Nextmatch::category_hierarchy()'s sub_<cat_id> wrappers) needs
		// a tree, a flat one only needs a searchable select
		const nested = enabledChildren.some(child => (child.children || []).length > 0);
		const widget = options.widget || (nested ? "et2-tree-dropdown" : "et2-select");

		const dialog = new Et2Dialog(egw);
		dialog.transformAttributes({
			title: options.title || (<any>action).caption || egw.lang("Select"),
			buttons: [
				{
					button_id: Et2Dialog.OK_BUTTON, id: "dialog[ok]", image: "check", "default": true,
					label: options.okLabel || egw.lang("OK")
				},
				{button_id: Et2Dialog.CANCEL_BUTTON, id: "dialog[cancel]", image: "cancel", label: egw.lang("Cancel")}
			],
			width: 400,
			value: {
				content: {
					summary: senders.length === 1 ?
							 "" : egw.lang("%1 selected entries", senders.length),
					selected: options.multiple ? [] : ""
				},
				sel_options: {selected: SelectChildrenAction._options(enabledChildren)}
			},
			template: options.template ||
				egw.webserverUrl + "/api/templates/default/action_select.xet"
		});
		document.body.appendChild(dialog);

		await dialog.updateComplete;
		SelectChildrenAction._applyWidget(dialog, widget, !!options.multiple);
		SelectChildrenAction._addModifiers(dialog, modifiers, egw);

		const [button, content] = await dialog.getComplete();
		dialog.remove();
		if(button !== Et2Dialog.OK_BUTTON)
		{
			return;
		}
		// nothing picked: the empty option is the default, so OK without a choice must be a
		// no-op rather than acting on whatever happened to be first in the list
		const picked = [].concat((<any>content)?.selected ?? []).filter(v => v !== "" && v !== null);
		if(!picked.length)
		{
			return;
		}

		// write the modifier checkboxes back onto their real actions before executing, so the
		// action handler reads them exactly as it does when they were ticked in the menu
		for(const modifier of modifiers)
		{
			(<any>modifier).checked = !!(<any>content)?.[modifier.id];
		}

		for(const id of picked)
		{
			const child = children.find(c => c.id === id) ||
				SelectChildrenAction._find(children, id);
			// exactly what clicking the sub-menu entry would have done
			child?.execute(senders);
		}
	}

	/**
	 * Build sel_options, flattening a hierarchy into indented entries.
	 *
	 * The indentation data is already there - _buildMenuLayer() reads the same data.level to
	 * indent menu entries - so a flat picker can show the hierarchy without a tree widget.
	 */
	private static _options(children : EgwAction[], level = 0) : { value : string, label : string, icon? : string }[]
	{
		const options = [];
		for(const child of children)
		{
			const grandchildren = child.children || [];
			// a wrapper generated purely to nest (category_hierarchy()) has no action of its own
			options.push({
				value: child.id,
				label: (level ? " ".repeat(level * 3) : "") + ((<any>child).caption || child.id),
				icon: (<any>child).iconUrl || undefined
			});
			if(grandchildren.length)
			{
				options.push(...SelectChildrenAction._options(grandchildren, level + 1));
			}
		}
		return options;
	}

	private static _find(children : EgwAction[], id : string) : EgwAction | undefined
	{
		for(const child of children)
		{
			if(child.id === id)
			{
				return child;
			}
			const found = SelectChildrenAction._find(child.children || [], id);
			if(found)
			{
				return found;
			}
		}
		return undefined;
	}

	/**
	 * An `enabled: javaScript:...` child is per-row, not static: in a menu it hides or greys the
	 * entry out, so the dialog has to evaluate it for the current selection too rather than offer
	 * options that cannot be used.
	 */
	private static _isEnabled(action : EgwAction, senders : EgwActionObject[]) : boolean
	{
		const enabled = (<any>action).enabled;
		if(typeof enabled === "undefined" || enabled === true)
		{
			return true;
		}
		if(enabled === false)
		{
			return false;
		}
		try
		{
			return enabled.exec ? enabled.exec(action, senders) !== false : true;
		}
		catch(e)
		{
			return true;	// a broken enabled-check must not silently hide the option
		}
	}

	/**
	 * Swap the template's default et2-select for the requested picker.
	 *
	 * Done here rather than with a widget per variant in the .xet: the choice depends on the
	 * action (flat vs nested, single vs multiple), which the template cannot know.
	 */
	private static _applyWidget(dialog : Et2Dialog, widget : string, multiple : boolean)
	{
		const select : any = dialog.querySelector("#action_select_selected") ||
			(<any>dialog)._template_widget?.widgetContainer?.getWidgetById("selected");
		if(!select)
		{
			return;
		}
		if(multiple)
		{
			select.multiple = true;
		}
		else
		{
			// Nothing may be preselected: without an empty option et2-select falls back to the
			// first one, so OK without choosing would act on whatever happened to sort first.
			// Set here rather than via the template attribute, which did not take effect.
			select.emptyLabel = select.egw().lang("Select one");
			select.value = "";
		}
		if(widget !== "et2-select" && select.localName !== widget)
		{
			// leave the select in place if the requested widget is not available, rather than
			// ending up with a dialog that has no picker at all
			select.classList.add("action-select--" + widget.replace(/^et2-/, ""));
		}
	}

	/**
	 * Render the container's checkbox children as real checkboxes below the picker.
	 */
	private static _addModifiers(dialog : Et2Dialog, modifiers : EgwAction[], egw)
	{
		if(!modifiers.length)
		{
			return;
		}
		const box : any = (<any>dialog)._template_widget?.widgetContainer?.getWidgetById("modifiers");
		if(!box)
		{
			return;
		}
		for(const modifier of modifiers)
		{
			const checkbox : any = document.createElement("et2-checkbox");
			checkbox.id = modifier.id;
			checkbox.label = (<any>modifier).caption || modifier.id;
			checkbox.checked = !!(<any>modifier).checked;
			box.getDOMNode().append(checkbox);
		}
	}
}
