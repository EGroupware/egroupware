/**
 * EGroupware eTemplate2 - Link/unlink several selected entries to one target entry
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";

interface LinkActionEntry
{
	app : string;
	id : string;
}

/**
 * Backs the automatic "Link" contextmenu action (EgwPopupActionImplementation._addLinkAction()):
 * shows a small popup to pick one target entry via <et2-link-entry>, then either links every
 * selected entry to it ("Add") or removes an existing link to it ("Remove").
 *
 * A plain static class + .xet template (like Favorite.ts/add_favorite.xet), not a lit custom
 * element like Et2MergeDialog - this dialog has no per-app render-time branching, just a fixed
 * picker widget and two buttons, so it doesn't need a dedicated custom element.
 */
export class LinkAction
{
	/**
	 * @param egw egw instance of the app the contextmenu was opened in
	 * @param selected Selected row ids, in the "app::id" uid format action objects use
	 */
	static async open(egw, selected : { id : string }[]) : Promise<void>
	{
		const sources : LinkActionEntry[] = selected
			.map(s => (s.id || "").split("::"))
			.filter(parts => parts[0] && parts[1])
			.map(([app, id]) => ({app, id}));

		if(!sources.length)
		{
			return;
		}

		const dialog = await LinkAction._openPopup(egw, sources);
		const [rawButton, content] = await dialog.getComplete();
		dialog.remove();

		// button_id is declared as number|Object on Et2Dialog, but our own buttons (below) use
		// the string ids "add"/"remove" - cast rather than fight the widened declared type.
		const button = <unknown>rawButton as string;
		const target : LinkActionEntry = (<{ target? : LinkActionEntry }>content)?.target;
		if((button !== "add" && button !== "remove") || !target?.app || !target?.id)
		{
			return;
		}

		if(button === "add")
		{
			await LinkAction._add(egw, sources, target);
		}
		else
		{
			await LinkAction._remove(egw, sources, target);
		}
	}

	private static async _openPopup(egw, sources : LinkActionEntry[]) : Promise<Et2Dialog>
	{
		const summary = sources.length === 1 ?
			await egw.link_title(sources[0].app, sources[0].id, true) || sources[0].id :
			egw.lang('%1 selected entries', sources.length);

		const dialog = new Et2Dialog(egw);
		dialog.transformAttributes({
			title: egw.lang('Link'),
			buttons: [
				{button_id: "add", label: egw.lang("Add"), id: "dialog[add]", image: "add", "default": true},
				{button_id: "remove", label: egw.lang("Remove"), id: "dialog[remove]", image: "delete"},
				{button_id: Et2Dialog.CANCEL_BUTTON, label: egw.lang("Cancel"), image: "cancel", id: "dialog[cancel]"}
			],
			width: 400,
			value: {content: {summary}},
			template: egw.webserverUrl + '/api/templates/default/link_action.xet'
		});
		document.body.appendChild(dialog);

		// et2-link-entry's search combobox opens a position:fixed results popup - but a
		// position:fixed descendant is still clipped by an ancestor's overflow:auto (fixed
		// positioning only changes ITS OWN coordinate system, it does not exempt the box from a
		// scrolling ancestor's clip region). Et2Dialog's own .dialog__body always sets
		// overflow:auto, which was clipping the popup and putting an unwanted scrollbar on this
		// otherwise non-scrolling dialog. Scoped to just this dialog instance, not a shared
		// Et2Dialog style change.
		await dialog.updateComplete;
		const body = <HTMLElement>dialog.shadowRoot?.querySelector(".dialog__body");
		if(body)
		{
			body.style.overflow = "visible";
		}

		// _setupMoveResize() (to support dragging/resizing the dialog) locks the panel to its
		// size at open time via an inline height - fine normally, but this dialog's body can grow
		// afterwards (the search dropdown above), and a body taller than the locked panel pushes
		// the footer (Add/Remove/Cancel) out past the panel's own bottom edge, since the panel
		// itself never grows to match. Dropping the inline height lets the panel go back to its
		// natural (flex/content-driven) auto height, so it keeps fitting header+body+footer as
		// body's size changes - a user dragging the resize handle afterwards still works fine,
		// since that sets a fresh explicit height itself (Et2Dialog._onMoveResize()).
		//
		// Must happen on "sl-show" (fired, composed, when the search combobox's dropdown opens -
		// bubbles up through et2-link-entry/et2-link-search's shadow boundaries to the dialog),
		// NOT right here: Et2Dialog's own handleOpen() schedules _setupMoveResize() off
		// Promise.all([_template_promise, updateComplete]), which can resolve AFTER our own
		// `await dialog.updateComplete` above - clearing the height here lost the race and
		// _setupMoveResize() (which doesn't set height itself, but interact.js's own
		// resizable()/draggable() setup snapshots the panel's current size into an inline style)
		// re-locked it right after. Reacting to the dropdown's own open event instead guarantees
		// we run long after that initial setup has already settled.
		dialog.addEventListener("sl-show", () =>
		{
			const panel = <HTMLElement>dialog.shadowRoot?.querySelector(".dialog__panel");
			panel?.style.removeProperty("height");
		});

		return dialog;
	}

	/**
	 * Link every source entry to target, one Widget\Link::ajax_link() call per source so each
	 * one gets its own edit-rights check (Widget\Link::checkLinkAccess() only ever validates the
	 * single app/id passed as the call's OWN entry, never the $links array) - required so a
	 * permission-denied source is reported individually instead of silently skipped or aborting
	 * the others. Queued via jsonq() so all of them still travel as one HTTP request.
	 */
	private static async _add(egw, sources : LinkActionEntry[], target : LinkActionEntry) : Promise<void>
	{
		const results = await Promise.allSettled(sources.map(source =>
			egw.jsonq("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link",
				[source.app, source.id, [{app: target.app, id: target.id}]])
		));
		await LinkAction._reportResults(egw, sources, results, "link", egw.lang('Linked'));
	}

	/**
	 * Remove the link between each source entry and target. ajax_delete() only takes a link_id,
	 * so each source's link_id first has to be looked up via ajax_link_list() (a source can have
	 * several links to other entries of target's app, only_app narrows but doesn't guarantee a
	 * single match). Both steps go through jsonq() so the whole operation is 2 HTTP requests
	 * total, regardless of selection size.
	 */
	private static async _remove(egw, sources : LinkActionEntry[], target : LinkActionEntry) : Promise<void>
	{
		const results = await Promise.allSettled(sources.map(async(source) =>
		{
			const links : any = await egw.jsonq("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_list",
				[{to_app: source.app, to_id: source.id, only_app: target.app}]);
			const match : any = Object.values(links || {}).find((link : any) =>
				link && String(link.id) === String(target.id));
			if(!match)
			{
				throw new Error(egw.lang("Not linked"));
			}
			return egw.jsonq("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_delete", [match.link_id]);
		}));
		await LinkAction._reportResults(egw, sources, results, "unlink", egw.lang('Unlinked'));
	}

	/**
	 * Longest failure list spelled out in full before falling back to "title, title, ..., and N
	 * more" - matters once "Select all" is involved (a nextmatch filter easily matches thousands
	 * of rows).
	 */
	private static readonly MAX_LISTED_FAILURES = 10;

	/**
	 * Report success, or which source entries could not be (un)linked, each with why (eg. "no
	 * permission") - never just "All", even when every source failed: with a single selected
	 * entry that reads as if something else ("all of some larger set") failed, not the one entry
	 * the user was looking at.
	 */
	private static async _reportResults(
		egw, sources : LinkActionEntry[], results : PromiseSettledResult<any>[],
		action : "link" | "unlink", successMessage : string
	) : Promise<void>
	{
		const failed = sources
			.map((source, i) => ({source, result: results[i]}))
			.filter(({result}) => result.status === "rejected");

		if(!failed.length)
		{
			egw.message(successMessage, "success");
			sources.forEach(source => egw.dataRefreshUIDs?.(source.app + "::" + source.id, "update"));
			return;
		}

		const shown = failed.slice(0, LinkAction.MAX_LISTED_FAILURES);
		const shownReasons = shown.map(({result}) => LinkAction._reasonOf(<PromiseRejectedResult>result));
		const titles = await Promise.all(shown.map(async({source}) =>
			await egw.link_title(source.app, source.id, true) || source.id
		));

		// Bulk failures (eg. a "select all" spanning entries the user has no rights to) almost
		// always share one cause - only show it once instead of repeating it per entry. Only
		// annotate each title individually when the reasons actually differ (eg. some
		// already-not-linked and some permission-denied, on a "Remove" spanning a mixed
		// selection) - otherwise per-title annotations would just repeat the same reason
		// needlessly for every single entry.
		const allReasons = [...new Set(failed.map(({result}) => LinkAction._reasonOf(<PromiseRejectedResult>result)).filter(Boolean))];
		let names = allReasons.length > 1 ?
			titles.map((title, i) => shownReasons[i] ? `${title} (${shownReasons[i]})` : title).join(', ') :
			titles.join(', ');
		if(failed.length > shown.length)
		{
			names += ', ' + egw.lang('%1 more...', failed.length - shown.length);
		}

		const list = allReasons.length === 1 ? `${names} (${allReasons[0]})` : names;

		egw.message(egw.lang(action === "link" ? 'Could not link: %1' : 'Could not unlink: %1', list), "error");

		sources.filter((source, i) => results[i].status === "fulfilled")
			.forEach(source => egw.dataRefreshUIDs?.(source.app + "::" + source.id, "update"));
	}

	/**
	 * First line of a rejected jsonq() call's error message (eg. "Permission denied!") - a
	 * server-side exception can bundle a multi-line trace/detail dump after it when
	 * exception_show_trace is on, which isn't useful in a toast message.
	 */
	private static _reasonOf(result : PromiseRejectedResult) : string
	{
		const reason : any = result.reason;
		const message : string = (reason && reason.message) ? reason.message : String(reason ?? '');
		return message.split('\n')[0].trim();
	}
}
