import {css} from 'lit';

export default css`
	:host {
		display: block;
		--gap-width: 1rem;
		--label-width: min(20rem, 30%);
	}

	/* An app with several nextmatches marks the inactive ones' filterboxes hidden, which is also
	   how EgwFrameworkApp.filters picks the current one.  Without this the UA's own [hidden] rule
	   loses to the display above and they all stay visible in the drawer. */
	:host([hidden]) {
		display: none;
	}

	.filterbox {
	}

	.filterbox__filters, et2-template {
		display: flex;
		flex-direction: column;
		gap: var(--gap-width);
		flex: 1 1 auto
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