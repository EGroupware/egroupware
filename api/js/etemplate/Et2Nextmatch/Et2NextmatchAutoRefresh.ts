import type {ReactiveController} from "lit";
import type {Et2Nextmatch} from "./Et2Nextmatch";

/**
 * Background autorefresh poll for an Et2Nextmatch instance, for apps/instances
 * without reliable push notifications.
 *
 * Each tick is a full reload (one `ajax_get_rows` request, via the host's own
 * `refresh()`), not a per-row update. Apps that always have push disable autorefresh outright
 * (the `disable_autorefresh` setting) rather than relying on this.
 *
 * The interval is read from a per-instance user preference
 * (`nextmatch-<preferenceBase>-autorefresh`, seconds; 0/absent = off), editable
 * from the column-selection dialog's `autoRefresh` field via
 * `seedColumnSelection()`/`applyColumnSelection()`.
 *
 * The timer pauses whenever this nextmatch isn't actually visible, checked live
 * rather than tracked as a toggled flag: its EGroupware app tab (if any) must be
 * the active one, and the browser tab/window itself must not be backgrounded.
 * Resuming does one immediate refresh, since the data may be stale, then resumes
 * the regular interval.
 */
export class Et2NextmatchAutoRefresh implements ReactiveController
{
	private host : Et2Nextmatch;
	private timer : number | null = null;

	/**
	 * Tracks whether autorefresh is currently paused for visibility reasons, so
	 * repeated/spurious hide-show events don't each trigger their own immediate
	 * refresh - only a real hidden-to-visible transition does.
	 */
	private paused = false;

	/**
	 * Nearest EGroupware app-tab ancestor. Used to pause autorefresh while this
	 * nextmatch's tab is not the active one, the same signal the framework uses to
	 * show/hide app tabs (`EgwFramework.showTab()` dispatches `hide`/`show` on the
	 * `<egw-app>` element and toggles its `active` attribute). `null` for a nextmatch
	 * outside the app-tab framework (eg. inside a popup window), which then only
	 * pauses on the standard `visibilitychange` event below.
	 */
	private appTab : Element | null = null;

	constructor(host : Et2Nextmatch)
	{
		this.host = host;
		host.addController(this);
	}

	/**
	 * Attach visibility listeners. Called by Lit when the host connects.
	 */
	hostConnected() : void
	{
		this.appTab = this.host.closest("egw-app");
		document.addEventListener("visibilitychange", this.syncVisibility);
		this.appTab?.addEventListener("hide", this.syncVisibility);
		this.appTab?.addEventListener("show", this.syncVisibility);
	}

	/**
	 * Detach everything. Called by Lit when the host disconnects.
	 */
	hostDisconnected() : void
	{
		this.stop();
		document.removeEventListener("visibilitychange", this.syncVisibility);
		this.appTab?.removeEventListener("hide", this.syncVisibility);
		this.appTab?.removeEventListener("show", this.syncVisibility);
		this.appTab = null;
	}

	/**
	 * Base for Et2Nextmatch's own per-instance preference keys - matches the PHP
	 * `Nextmatch` widget's own `$columnselection_pref ?? $template` formula, so
	 * anything keyed off this lands on the same preference PHP's admin
	 * default-setting flow already reads/writes, and an already-set value keeps
	 * working unchanged. Deliberately falls back to `template` (the server-set
	 * attribute), NOT `columnPreferenceName` - that property is empty unless an
	 * app opts in, and even when set can be a purely client-side, view-dependent
	 * column-storage key (eg. Mail's row-template variants) with no relation to
	 * the preference name PHP actually computed.
	 */
	private get preferenceBase() : string
	{
		return String(this.host.settings.columnselection_pref || this.host.template || "");
	}

	private get preferenceKey() : string
	{
		return `nextmatch-${this.preferenceBase}-autorefresh`;
	}

	/**
	 * Effective autorefresh interval in seconds, 0 meaning off. Always 0 when the
	 * app opted out via the `disable_autorefresh` setting (Infolog, Timesheet and
	 * Invoices do this since they already get updates via push), regardless of any
	 * stored preference value.
	 */
	private get interval() : number
	{
		if(this.host.settings.disable_autorefresh)
		{
			return 0;
		}
		return parseInt(this.host.egw().preference(this.preferenceKey, this.host._getAppName())) || 0;
	}

	/**
	 * Whether autorefresh is allowed to run right now, ie. this nextmatch is
	 * actually visible: its app tab (if any) is the active one, and the browser
	 * tab/window itself is not in the background.
	 */
	private get shouldRun() : boolean
	{
		if(typeof document !== "undefined" && document.hidden)
		{
			return false;
		}
		return !this.appTab || this.appTab.hasAttribute("active");
	}

	/**
	 * Full reload - one `ajax_get_rows` request, same as the toolbar refresh
	 * action. Autorefresh exists specifically for instances where an admin has
	 * disabled push, so it has to catch new/removed/reordered rows too, not just
	 * pick up changes to rows already loaded - a targeted per-row patch (what
	 * push-driven single-row updates use `refresh(ids, ...)` for elsewhere) would
	 * miss exactly what autorefresh is for.
	 */
	private tick = () : void =>
	{
		this.host.refresh(undefined);
	};

	/**
	 * (Re)start the timer from the current interval/visibility state. Safe to call
	 * any time - a no-op when autorefresh is off or currently paused.
	 *
	 * @param immediate Also run one check right away, used when resuming after
	 *  being hidden since the currently-loaded rows may now be stale.
	 */
	private start(immediate = false) : void
	{
		this.stop();
		const interval = this.interval;
		if(interval <= 0 || !this.shouldRun)
		{
			return;
		}
		if(immediate)
		{
			this.tick();
		}
		this.timer = window.setInterval(this.tick, interval * 1000);
	}

	private stop() : void
	{
		if(this.timer !== null)
		{
			window.clearInterval(this.timer);
			this.timer = null;
		}
	}

	/**
	 * Called after a (re)load completes, and after the user changes the
	 * autorefresh interval from column selection - re-derives everything from
	 * current state rather than trying to patch the running timer.
	 */
	restart() : void
	{
		this.paused = !this.shouldRun;
		this.start(false);
	}

	/**
	 * Handler for both the app-tab `hide`/`show` events and the browser's own
	 * `visibilitychange` - either can flip visibility independently (switching
	 * EGroupware app tabs vs. backgrounding the whole browser tab/window), so both
	 * just ask `shouldRun` for the combined answer. Only acts on an actual
	 * transition, so repeated/spurious events don't each force their own
	 * immediate refresh.
	 */
	private syncVisibility = () : void =>
	{
		const shouldRun = this.shouldRun;
		if(shouldRun && this.paused)
		{
			this.paused = false;
			this.start(true);
		}
		else if(!shouldRun && !this.paused)
		{
			this.paused = true;
			this.stop();
		}
	};

	/**
	 * Seed the column-selection dialog's `autoRefresh` field with the current
	 * value, via the `et2-column-selection-items` event's `content` object.
	 */
	seedColumnSelection(content : Record<string, any>) : void
	{
		if(!this.host.settings.disable_autorefresh)
		{
			content.autoRefresh = this.interval || "";
		}
	}

	/**
	 * Persist a changed `autoRefresh` value from the column-selection dialog
	 * result (the `et2-column-selection-apply` event's `values` object) and
	 * restart the timer against it. Always stores the literal value, including
	 * `0` for "off" - unsetting the preference instead would fall through to any
	 * admin-configured default and silently undo the user's choice.
	 */
	applyColumnSelection(values : Record<string, any> | undefined) : void
	{
		if(this.host.settings.disable_autorefresh || typeof values?.autoRefresh === "undefined")
		{
			return;
		}
		const seconds = parseInt(values.autoRefresh) || 0;
		this.host.egw().set_preference(this.host._getAppName(), this.preferenceKey, seconds);
		this.restart();
	}
}
