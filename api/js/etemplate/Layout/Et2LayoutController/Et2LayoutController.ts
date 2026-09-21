import {CSSResult, ReactiveController, ReactiveControllerHost} from "lit";
import {Et2LayoutName, getLayoutStrategy} from "./Et2LayoutStrategies";
import {Et2TopLayerPopupController} from "./Et2TopLayerPopupController";

// Re-exported so a widget adding layout support only has to import from here: it needs
// Et2LayoutHost and Et2LayoutController anyway, and the `layout` property it declares to
// satisfy the interface has to be typed Et2LayoutName.
export type {Et2LayoutName};

/**
 * Widgets with layout implement Et2LayoutHost
 *
 * 1.  Implement Et2LayoutHost interface
 * 2.  Create an Et2LayoutController
 *
 * private _layout = new Et2LayoutController(this);
 *
 */
export interface Et2LayoutHost extends ReactiveControllerHost, HTMLElement
{
	layout : Et2LayoutName;
}

export interface Et2LayoutStrategy
{
	apply(host : HTMLElement, children : HTMLElement[]) : void;

	cleanup?(host : HTMLElement, children : HTMLElement[]) : void;
}

export class Et2LayoutController implements ReactiveController
{
	static readonly styles : CSSResult[] = [];//Object.values(LAYOUT_CSS);
	private activeStrategy? : Et2LayoutStrategy;

	/*
	 * A layout that collapses its own columns declares itself a container query container, which
	 * misplaces every hoisted popup opened inside it.  The controller is created here rather than
	 * by each host widget because the layout is what introduces the container in the first place.
	 */
	private topLayerPopups : Et2TopLayerPopupController;

	constructor(private host : Et2LayoutHost)
	{
		(host as ReactiveControllerHost).addController(this);
		this.topLayerPopups = new Et2TopLayerPopupController(host);
	}

	hostConnected()
	{
		this.applyLayout();
	}

	hostUpdated()
	{
		this.applyLayout();
	}

	hostDisconnected()
	{
		if(this.activeStrategy?.cleanup)
		{
			const children = Array.from(this.host.children) as HTMLElement[];
			this.activeStrategy.cleanup(this.host, children);
		}
		this.activeStrategy = undefined;
	}

	private applyLayout()
	{
		const strategy = getLayoutStrategy(this.host.layout);
		if(!strategy)
		{
			return;
		}

		const children = Array.from(this.host.children) as HTMLElement[];
		if(this.activeStrategy && this.activeStrategy !== strategy && this.activeStrategy.cleanup)
		{
			this.activeStrategy.cleanup(this.host, children);
		}
		strategy.apply(this.host, children);
		this.activeStrategy = strategy;
	}
}
