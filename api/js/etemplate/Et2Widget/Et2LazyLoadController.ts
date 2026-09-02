/**
 * EGroupware eTemplate2 - Tells a widget when it's worth doing deferred work
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */

import type {ReactiveController, ReactiveControllerHost} from "lit";

export type Et2LazyLoadHost = ReactiveControllerHost & HTMLElement & { checkVisibility?() : boolean };

/**
 * Lets a widget hold off on work that isn't worth doing yet - typically because nobody
 * can see the result, but `isExtraReady` can add any other condition (e.g. "the parent
 * widget that owns our data has finished its own load") on top of that.
 *
 * Whether a widget is displayed is not something it can tell from its own attributes - a
 * `display: none` several DOM levels (and shadow roots) up hides it just as well, and the
 * widget must not have to know about whatever app-specific state switched that on (a
 * details toggle, an inactive tab, ...).  `ready` answers that from the element itself:
 * it covers `display: none` on the element or any of its ancestors, and being detached.
 * It deliberately ignores the viewport: something displayed but scrolled away counts as
 * ready, same as a widget with no visibility concept at all.
 *
 * `onReady` is the missing event for the visibility half: an element with no box never
 * intersects anything, so an IntersectionObserver reports getting a box - by being
 * displayed again, or by being scrolled into view - as becoming ready.  There's no such
 * event for `isExtraReady`, since this controller has no way to know what it depends on;
 * call `recheck()` when that condition changes (e.g. from whatever "other load" you were
 * waiting on, once it finishes).
 *
 * `onReady` is called every time `ready` is observed becoming true - including more than
 * once, if the host is hidden and shown again, or `recheck()` is called again after
 * `isExtraReady` has already been satisfied once.  Make it idempotent (as
 * Et2LinkString._loadDeferred() is: a no-op once there's nothing pending) rather than
 * relying on it firing exactly once.
 *
 * Sometimes the deferral itself needs to be overridden - a print view needs every row's
 * content whether or not it was ever scrolled into view, a user action can ask for a
 * hidden tab's data right now instead of waiting for the tab to become active.  `force()`
 * just runs `onReady` right away, gates or no gates - it doesn't change what `ready`
 * reports, so a caller whose `onReady` re-checks `ready` itself (as
 * Et2LinkString._loadDeferred() does, since it can also be reached from a plain
 * re-render - see its `updated()`) needs to do that check with the same bypass in mind
 * if it wants force() to actually go through.
 *
 * Nothing is remembered across a disconnect, so a host that gets detached, re-attached
 * or re-used for other content (as happens when a virtualized list recycles a row
 * widget) needs no extra care from the caller.
 *
 * `whenReady` is the same event as `onReady`, as a Promise instead of a callback - for
 * code that wants to `await` the gates once rather than react to them every time they
 * open.  It resolves the moment `onReady` would be called - which, since `onReady` fires
 * on every open (see above), can already be in the past: if `ready` is already true when
 * something reads `whenReady`, it resolves right away instead of waiting for the next
 * open, which may never come.  It settles once and is done; a caller that expects to
 * wait again later (e.g. after the host is hidden and shown again) reads it again rather
 * than holding on to the first Promise.
 */
export class Et2LazyLoadController implements ReactiveController
{
	private host : Et2LazyLoadHost;
	private onReady : () => void;
	private isExtraReady : () => boolean;
	private observer : IntersectionObserver;
	private resolvers : Array<() => void> = [];

	/**
	 * @param host
	 * @param onReady Called whenever `ready` is seen becoming true - make it idempotent.
	 *  Optional for a caller that only wants to `await whenReady`.
	 * @param isExtraReady Additional condition to require alongside visibility, checked
	 *  on every observation; call `recheck()` when whatever it depends on changes.
	 *  Defaults to visibility being the only condition.
	 */
	constructor(host : Et2LazyLoadHost, onReady : () => void = () => {}, isExtraReady : () => boolean = () => true)
	{
		this.host = host;
		this.onReady = onReady;
		this.isExtraReady = isExtraReady;
		host.addController(this);
	}

	/**
	 * Is the host worth doing deferred work for right now?
	 */
	get ready() : boolean
	{
		// A browser without checkVisibility() gives us nothing to go on, so treat the host as
		// visible and let whatever wanted to wait go ahead instead
		return this.host.isConnected && (this.host.checkVisibility?.() ?? true) && this.isExtraReady();
	}

	/**
	 * The same event as `onReady`, as a Promise - see the class doc for what "the same
	 * event" means when `onReady` can fire more than once.
	 */
	get whenReady() : Promise<void>
	{
		if(this.ready)
		{
			return Promise.resolve();
		}
		return new Promise(resolve => this.resolvers.push(resolve));
	}

	hostConnected() : void
	{
		if(!this.observer)
		{
			this.observer = new IntersectionObserver(() =>
			{
				// The observer only tells us the host's box changed how it overlaps the viewport,
				// which includes gaining a box in the first place.  What we actually want to know
				// is asked directly, so a callback for something else costs nothing.
				if(this.ready)
				{
					this.fire();
				}
			}, {
				// Start a bit before the host scrolls into view, so something rendered just past
				// the edge of the viewport has its content ready by the time it is reached
				rootMargin: "20%"
			});
		}
		this.observer.observe(this.host);
	}

	hostDisconnected() : void
	{
		this.observer?.disconnect();
	}

	/**
	 * Re-check readiness for whatever `isExtraReady` depends on - the visibility half has
	 * its own IntersectionObserver and doesn't need this.  Call it from the code that owns
	 * the condition `isExtraReady` checks, once that condition changes.
	 */
	recheck() : void
	{
		if(this.ready)
		{
			this.fire();
		}
	}

	/**
	 * Run `onReady` right away, regardless of visibility or `isExtraReady` - a one-off
	 * bypass, not a change to what `ready` reports afterward.
	 */
	force() : void
	{
		this.fire();
	}

	private fire() : void
	{
		this.onReady();
		const resolvers = this.resolvers;
		this.resolvers = [];
		resolvers.forEach(resolve => resolve());
	}
}
