import {css} from "lit";

export default css`
	:host {
		display: flex;
		flex-direction: row;
		justify-content: flex-end;
	}
	et2-avatar {
		--size: 1.5rem;
		flex: 0 0 auto;
		min-width: 20px;
		transition-duration:0.1s;
	}
	et2-avatar:not(:first-of-type) {
		margin-left: -0.5rem;
	}
	et2-avatar::part(base) {
		border: solid 2px var(--sl-color-neutral-0);
	}
	et2-avatar:hover {
		--size: 2.5rem;
		overflow: visible;
		z-index: 11;
		transition-delay: 1s;
		transition-suration:0.5s
	}
`;
