import {css} from "lit";

/**
 * Row styles for et2-nextmatch.  These are loaded inside the Et2Datagrid shadowRoot.
 * Only EGroupware-wide styles are allowed here.
 */
export default css`
	et2-link,
	et2-link-string {
		color: var(--sl-color-sky-900);
	}

	.et2_link {
		color: var(--sl-color-sky-900);
	}

	.et2_link:hover {
		cursor: pointer;
		text-decoration: underline;
	}

	/*
	 * These widgets are block-level and install their own click handler that
	 * stops propagation, so a full-width host swallows clicks anywhere in the
	 * cell's empty space that should have selected the row instead (only
	 * visible when there's leftover space, e.g. next to a short email
	 * address or link title). Rather than shrinking the host itself (which
	 * broke text-wrapping/sizing for widgets whose internal layout assumes a
	 * definite width, e.g. et2-link's title/remark flex children), make the
	 * host transparent to pointer events and only re-enable them on the
	 * parts that actually render visible content, so empty space passes the
	 * click through to row selection while the visible text/icon stays
	 * clickable.
	 */
	et2-link {
		pointer-events: none;
	}

	et2-link::part(icon),
	et2-link::part(title),
	et2-link::part(remark) {
		pointer-events: auto;
	}

	et2-url_ro,
	et2-url-email_ro,
	et2-url-phone_ro,
	et2-url-fax_ro {
		pointer-events: none;
	}

	et2-url_ro *,
	et2-url-email_ro *,
	et2-url-phone_ro *,
	et2-url-fax_ro * {
		pointer-events: auto;
	}
`;
