import {css} from "lit";

export default css`
	egw-split-panel > sl-icon {
        position: absolute;
        border-radius: var(--sl-border-radius-small);
        background-color: var(--sl-color-neutral-500);
        color: var(--sl-color-neutral-0);
        z-index: var(--sl-z-index-drawer);
        width: .5rem;
    }

	egw-split-panel::part(divider) {
		grid-row: start / end;
		font-size: var(--sl-font-size-medium);
	}

	egw-split-panel.no-content {
		--divider-width: 0px;
	}

	egw-split-panel.no-content::part(divider) {
		display: none;
	}
`