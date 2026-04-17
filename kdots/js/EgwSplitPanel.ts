import {customElement} from "lit/decorators.js";
import {SlSplitPanel} from "@shoelace-style/shoelace";
import {PropertyValues} from "lit";

@customElement('egw-split-panel')
export class EgwSplitPanel extends SlSplitPanel
{
	protected willUpdate(changedProperties : PropertyValues<this>)
	{
		if(changedProperties.has("disabled") && !this.disabled && this.checkVisibility())
		{
			this.detectSize();
		}
		super.willUpdate(changedProperties);
	}

	handleResize(entries : ResizeObserverEntry[])
	{
		if(this.disabled || !this.getRootNode().host?.hasAttribute("active"))
		{
			return;
		}
		return super.handleResize(entries);
	}

	handlePositionChange()
	{
		// Usually initial load but not visible
		if(isNaN(this.position) || this.position == Infinity)
		{
			this.position = parseInt(this.dataset.default) || 0;
			return super.handlePositionChange();
		}
		if(this.disabled || !this.checkVisibility())
		{
			return;
		}
		return super.handlePositionChange();
	}
}