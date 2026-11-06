import {ReactiveController, ReactiveControllerHost} from "lit";

/**
 * Gives a button that is shown as just its icon its label as tooltip.
 *
 * The layout CSS (kdots/css/src/layouts/edit.less) clips the label of a button with an icon once
 * the layout gets narrow.  That is CSS only, and the tooltip system has no CSS side, so this
 * looks at which buttons have lost their label to the clipping and binds the label as tooltip
 * on those that have no tooltip of their own.  Once the label is visible again the tooltip is
 * removed, so a button is never left with one that only repeats what it says.
 *
 * A button with a statustext, or a title, is left alone either way.
 *
 * Why this lives with the layout and not in Et2Button: what clips the label is layout CSS, and the
 * width that triggers it is the layout's own, so one observer on the layout host covers every
 * button in it.  In Et2Button it would be an observer on every button in the application, and the
 * widget would have to know about a layout's CSS.
 *
 * It is not tied to a layout by name: Et2LayoutController attaches it to every layout host, and
 * a button counts as icon-only because its label part really is clipped.  Any layout whose CSS
 * clips button labels gets the tooltips as is, with nothing to register.
 *
 * Consider moving the check into Et2Button (as a reactive controller) once labels are clipped
 * outside a layout host - a toolbar, say - or when buttons added after the last resize or update
 * need a tooltip at once: Et2Button has its own update cycle and sits next to bindTooltip().
 * isIconOnly(), hasOwnTooltip() and release() would move over unchanged.
 */
export class Et2IconOnlyButtonTooltips implements ReactiveController
{
	/** Buttons that currently have our tooltip, and nothing else may unbind */
	private bound = new WeakSet<HTMLElement>();
	private resizeObserver? : ResizeObserver;

	constructor(private host : ReactiveControllerHost & HTMLElement)
	{
		host.addController(this);
	}

	hostConnected()
	{
		if(typeof ResizeObserver !== "undefined" && !this.resizeObserver)
		{
			this.resizeObserver = new ResizeObserver(() => this.update());
			this.resizeObserver.observe(this.host);
		}
	}

	hostUpdated()
	{
		this.update();
	}

	hostDisconnected()
	{
		this.resizeObserver?.disconnect();
		this.resizeObserver = undefined;
		this.host.querySelectorAll<HTMLElement>("et2-button").forEach(button => this.release(button));
	}

	/**
	 * Bind or unbind the label tooltip on every button in the layout, to match what is showing
	 */
	update()
	{
		this.host.querySelectorAll<HTMLElement>("et2-button").forEach(button =>
		{
			const label = this.labelOf(button);
			if(label && !this.hasOwnTooltip(button) && this.isIconOnly(button))
			{
				if(!this.bound.has(button))
				{
					(<any>button).egw?.()?.tooltipBind(button, label);
					this.bound.add(button);
				}
			}
			else
			{
				this.release(button);
			}
		});
	}

	/**
	 * The button's label clipped to (almost) nothing: its text is still there as its accessible
	 * name, only the part showing it has been shrunk to a pixel
	 */
	private isIconOnly(button : HTMLElement) : boolean
	{
		const part = button.shadowRoot?.querySelector<HTMLElement>('[part~="label"]');
		return !!part && part.getClientRects().length > 0 && part.getBoundingClientRect().width <= 1;
	}

	private labelOf(button : HTMLElement) : string
	{
		return (<any>button).label || button.textContent?.trim() || "";
	}

	private hasOwnTooltip(button : HTMLElement) : boolean
	{
		return !!((<any>button).statustext || button.getAttribute("title"));
	}

	private release(button : HTMLElement)
	{
		if(this.bound.delete(button))
		{
			(<any>button).egw?.()?.tooltipUnbind(button);
			// A statustext set meanwhile has its own tooltip, which the unbind just removed
			if((<any>button).statustext)
			{
				(<any>button).bindTooltip?.();
			}
		}
	}
}
