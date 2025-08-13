import {css} from 'lit';

export default css`
	:host {
		display: block;
		--gap-width: 1rem;
		--label-width: min(20rem, 30%);
	}

	.filterbox {
	}

	.filterbox__filters, .filterbox__filters [summary]::part(content) {
		display: flex;
		flex-direction: column;
		gap: var(--gap-width);
	}

	::slotted([slot="prefix"]) {
		padding-bottom: var(--gap-width);
	}

	::slotted([slot="suffix"]) {
		padding-top: var(--gap-width);
	}

	@media (max-width: 800px) {
		:host {
			--label-width: 100%;
		}
	}
`;