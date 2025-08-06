import {css} from "lit";

export default css`
    sl-split-panel > sl-icon {
        position: absolute;
        border-radius: var(--sl-border-radius-small);
        background-color: var(--sl-color-neutral-500);
        color: var(--sl-color-neutral-0);
        z-index: var(--sl-z-index-drawer);
        width: .5rem;
    }
`