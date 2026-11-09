/**
 * Stand-in for an edit form that opens in an in-page dialog ("Same Window")
 *
 * While the server is still answering the request that opens the form, a dialog of the size the form had last time is
 * shown with the generic stand-in (Framework::skeleton(), a theme coloured mask, no request and no layout).  The real
 * dialog loads exactly as it always did, but hidden.  When its form has loaded, the dialog has found its size and
 * stopped changing, the real dialog is shown and the stand-in removed in one task, so the dialog is not drawn twice and
 * does not resize at the swap.
 *
 * The stand-in is inert (no clicks, focus or requests), is not registered as an etemplate2 instance and never calls
 * et2_ready().  It only appears for menuactions known to be forms: the first time one opens, the controller remembers
 * its menuaction and the size it settled at.
 */
import type {ReactiveController} from "lit";
import type {Et2Dialog} from "./Et2Dialog";

/** The real dialog's panel has to keep its (layout) size this long, ms, before it is shown */
const SETTLE_TIME = 100;
/** Never leave the user looking at a stand-in longer than this, ms */
const MAX_WAIT = 15000;
/** Smaller than this (px) is not an edit form, no stand-in */
const MIN_SIZE = {width: 480, height: 360};

const SIZE_KEY = "egw_skeleton_size:";

/**
 * @param {string} url
 * @returns {string} menuaction (without further parameters) or ""
 */
export function menuactionOf(url : string) : string
{
	const match = /[?&]?menuaction=([^&#]+)/.exec(url || "");
	try
	{
		return match ? decodeURIComponent(match[1]) : "";
	}
	catch(e)
	{
		// a malformed %-escape: not something we know
		return "";
	}
}

/**
 * localStorage egw_skeleton=0 turns the skeletons off, for comparing.  Storage can be blocked, then they stay on.
 */
export function skeletonsOff() : boolean
{
	try
	{
		return window.localStorage.getItem("egw_skeleton") === "0";
	}
	catch(e)
	{
		return false;
	}
}

export interface SkeletonSize
{
	width : number;
	height : number;
}

/**
 * Size a form settled at the last time, clamped to the current viewport
 *
 * @param {string} menuaction
 * @returns {SkeletonSize | null} null if the form is not known (yet), or is too small to be a form
 */
export function rememberedSize(menuaction : string) : SkeletonSize | null
{
	try
	{
		const size = JSON.parse(window.localStorage.getItem(SIZE_KEY + menuaction) || "null");
		if(!size || !(size.width >= MIN_SIZE.width) || !(size.height >= MIN_SIZE.height))
		{
			return null;
		}
		return {
			width: Math.min(size.width, Math.floor(window.innerWidth * 0.95)),
			height: Math.min(size.height, Math.floor(window.innerHeight * 0.95))
		};
	}
	catch(e)
	{
		return null;
	}
}

/**
 * Remember the size a dialog settled at, for the next stand-in
 *
 * Taken once the panel has stopped changing: the dialog can still resize after its form has loaded.
 *
 * @param {string} menuaction
 * @param {Et2Dialog} real
 */
export function rememberDialogSize(menuaction : string, real : Et2Dialog)
{
	let last = "";
	let stableSince = 0;
	const start = performance.now();
	const timer = window.setInterval(() =>
	{
		try
		{
			const panel = real.shadowRoot?.querySelector(".dialog__panel");
			const now = performance.now();
			if(!panel || !real.isConnected || now - start > 5000)
			{
				return window.clearInterval(timer);
			}
			const box = panel.getBoundingClientRect();
			const signature = [box.width, box.height].map(Math.round).join("x");
			if(signature !== last)
			{
				last = signature;
				stableSince = now;
			}
			else if(now - stableSince >= 300)
			{
				window.clearInterval(timer);
				window.localStorage.setItem(SIZE_KEY + menuaction, JSON.stringify({
					width: Math.round(box.width), height: Math.round(box.height)
				}));
			}
		}
		catch(e)
		{
			// storage can be unavailable
			window.clearInterval(timer);
		}
	}, 50);
}

/**
 * Do not draw a stand-in for a form again
 *
 * @param {string} menuaction
 */
export function forgetDialogSize(menuaction : string)
{
	try
	{
		window.localStorage.removeItem(SIZE_KEY + menuaction);
	}
	catch(e)
	{
		// storage can be unavailable
	}
}

let hiddenStyleSheet : CSSStyleSheet | undefined;

/**
 * Style sheet that makes a dialog's base part transparent and unclickable
 */
function hiddenSheet() : CSSStyleSheet
{
	if(!hiddenStyleSheet)
	{
		hiddenStyleSheet = new CSSStyleSheet();
		hiddenStyleSheet.replaceSync(".dialog{opacity:0 !important;pointer-events:none !important}");
	}
	return hiddenStyleSheet;
}

export class DialogSkeletonController implements ReactiveController
{
	protected _discarded = false;
	protected _handed = false;
	/** Real dialogs hidden the moment they were added next to the stand-in */
	protected _hidden = new Set<HTMLElement>();
	protected _observer? : MutationObserver;

	/**
	 * @param host the stand-in dialog
	 * @param menuaction menuaction the form is opened with, used to remember its size
	 * @param size size to give the stand-in
	 * @param icon url of the application icon
	 */
	constructor(public readonly host : Et2Dialog, public readonly menuaction : string, public readonly size : SkeletonSize,
				public readonly icon : string = "")
	{
		host.addController(this);
	}

	/**
	 * Create the stand-in dialog
	 *
	 * @param {string} url what the dialog is opened with
	 * @param {HTMLElement} parent element the dialog is appended to, the same the real dialog will go in
	 * @param {string} icon url of the application icon
	 * @returns {DialogSkeletonController | undefined} undefined if the form is not known yet, or too small
	 */
	static create(url : string, parent : HTMLElement, icon : string = "") : DialogSkeletonController | undefined
	{
		const menuaction = menuactionOf(url) || url.split("&")[0];
		const size = rememberedSize(menuaction);
		if(!size || skeletonsOff() || !document.getElementById("egw-skeleton-css"))
		{
			return undefined;
		}
		const dialog = <Et2Dialog>document.createElement("et2-dialog");
		dialog.isModal = true;
		dialog.classList.add("egw-popup", "egw-dialog-skeleton");
		// Its size is set below and must stay: no fit to content
		dialog.sizeIsSet = true;
		parent.append(dialog);
		const controller = new DialogSkeletonController(dialog, menuaction, size, icon);
		controller._hideNewDialogs(parent);
		return controller;
	}

	/**
	 * Hide any other dialog added next to the stand-in before it is first painted
	 *
	 * The real dialog is created and opened while the request's answer is still being handed over, so it would be
	 * drawn (open animation and its own loader included) on top of the stand-in for a frame or two before
	 * handOver() can hide it.  A mutation observer runs before the next paint.
	 *
	 * @param {HTMLElement} parent
	 */
	protected _hideNewDialogs(parent : HTMLElement)
	{
		this._observer = new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node =>
		{
			if(node instanceof HTMLElement && node !== this.host && node.localName === "et2-dialog")
			{
				this._hide(node);
			}
		})));
		this._observer.observe(parent, {childList: true});
	}

	/**
	 * Hide a real dialog until it is shown again, see handOver()
	 *
	 * Hidden with opacity: widgets that check checkVisibility() treat visibility:hidden as "not shown" and lay out
	 * differently.  The dialog element itself is display:contents, which has no box for opacity to act on, so it is
	 * the dialog's own base part that is made transparent.  That exists once the dialog is connected, so this is
	 * done again when it is added to the page.
	 *
	 * @param {HTMLElement} dialog
	 */
	protected _hide(dialog : HTMLElement)
	{
		this._hidden.add(dialog);
		const root = dialog.shadowRoot;
		if(root && !root.adoptedStyleSheets.includes(hiddenSheet()))
		{
			root.adoptedStyleSheets = [...root.adoptedStyleSheets, hiddenSheet()];
		}
	}

	/**
	 * Give a hidden real dialog back
	 *
	 * @param {HTMLElement} dialog
	 */
	protected _unhide(dialog : HTMLElement)
	{
		this._hidden.delete(dialog);
		const root = dialog.shadowRoot;
		if(root)
		{
			root.adoptedStyleSheets = root.adoptedStyleSheets.filter(sheet => sheet !== hiddenSheet());
		}
	}

	hostConnected()
	{
		this.host.inert = true;
		this.host.updateComplete.then(() => this._draw());
	}

	protected _draw()
	{
		if(this._discarded || !this.host.isConnected)
		{
			return;
		}
		const panel = <HTMLElement>this.host.shadowRoot?.querySelector(".dialog__panel");
		if(!panel)
		{
			return;
		}
		panel.style.width = this.size.width + "px";
		panel.style.height = this.size.height + "px";

		let content = this.host._contentNode;
		if(!content)
		{
			content = document.createElement("form");
			content.className = "et2_container dialog_content";
			content.addEventListener("submit", e => e.preventDefault());
			this.host.append(content);
		}
		// Fill what is left of the body exactly: a pixel too much and the body scrolls
		const body = <HTMLElement | null>this.host.shadowRoot?.querySelector(".dialog__body");
		let height = "100%";
		if(body)
		{
			const style = getComputedStyle(body);
			height = (body.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom)) + "px";
		}
		content.style.cssText = "position:relative;width:100%;overflow:hidden;height:" + height;
		const skeleton = document.createElement("div");
		skeleton.className = "egw-skeleton";
		skeleton.setAttribute("aria-hidden", "true");
		if(this.icon)
		{
			const img = document.createElement("img");
			img.src = this.icon;
			img.alt = "";
			skeleton.append(img);
		}
		content.replaceChildren(skeleton);
	}

	/**
	 * Remove the stand-in
	 */
	discard()
	{
		this._discarded = true;
		this._observer?.disconnect();
		// anything still hidden on the stand-in's behalf is shown again
		[...this._hidden].forEach(dialog => this._unhide(dialog));
		this.host.destroyOnClose = false;
		this.host.remove();
	}

	/**
	 * Replace the stand-in with the real dialog, which was loading hidden in the meantime
	 *
	 * @param real the dialog the server's response created
	 */
	handOver(real : Et2Dialog) : void
	{
		if(this._handed)
		{
			return;
		}
		this._handed = true;
		this._hide(real);

		let loaded = false;
		let done = false;
		let last = "";
		let stableSince = 0;
		const start = performance.now();
		const form = () => real._contentNode;

		const show = () =>
		{
			if(done)
			{
				return;
			}
			done = true;
			window.clearInterval(timer);
			real.removeEventListener("load", onLoad);
			real.removeEventListener("close", show);
			// The open animation was played while hidden: do not play it a second time on the swap
			real.shadowRoot?.querySelectorAll("*").forEach(e => e.getAnimations?.().forEach(a =>
			{
				try
				{
					a.finish();
				}
				catch(err)
				{
					// an endless animation cannot be finished, the others still can
				}
			}));
			this._unhide(real);
			this.discard();
			if(loaded)
			{
				this._remember(real);
			}
		};
		// Only the dialog's own form: a template nested in it fires its own load earlier
		const onLoad = (ev : CustomEvent) =>
		{
			if(ev.target === form())
			{
				loaded = true;
				// The server no longer wants one for this form: do not draw one next time
				if(!ev.detail?.skeleton)
				{
					forgetDialogSize(this.menuaction);
				}
			}
		};
		real.addEventListener("load", onLoad);
		real.addEventListener("close", show);

		const timer = window.setInterval(() =>
		{
			const now = performance.now();
			if(now - start > MAX_WAIT)
			{
				return show();
			}
			const panel = real.shadowRoot?.querySelector(".dialog__panel");
			// The dialog sets sizeIsSet when it has fitted itself to its content
			if(!loaded || !real.sizeIsSet || !panel)
			{
				return;
			}
			// The layout size, not the bounding box: the open animation (played while the dialog is hidden) scales
			// the panel, and waiting for that to end would be waiting for something nobody sees
			const signature = (<HTMLElement>panel).offsetWidth + "x" + (<HTMLElement>panel).offsetHeight;
			if(signature !== last)
			{
				last = signature;
				stableSince = now;
			}
			else if(now - stableSince >= SETTLE_TIME)
			{
				show();
			}
		}, 10);
	}

	/**
	 * Remember the size the real dialog settled at, for the next stand-in
	 */
	protected _remember(real : Et2Dialog)
	{
		rememberDialogSize(this.menuaction, real);
	}

	/**
	 * Remember the size of a dialog that had no stand-in, so the next one has
	 *
	 * @param {string} url what the dialog was opened with
	 * @param {Et2Dialog} real
	 */
	static learn(url : string, real : Et2Dialog)
	{
		const menuaction = menuactionOf(url) || url.split("&")[0];
		// The dialog's own form: the server says whether it has a skeleton.  Remember its size if it does, and forget
		// it if it does not (anymore), so no stand-in is drawn for it
		real.addEventListener("load", (ev : CustomEvent) =>
		{
			if(ev.target !== real._contentNode)
			{
				return;
			}
			if(ev.detail?.skeleton)
			{
				rememberDialogSize(menuaction, real);
			}
			else
			{
				forgetDialogSize(menuaction);
			}
		});
	}
}
