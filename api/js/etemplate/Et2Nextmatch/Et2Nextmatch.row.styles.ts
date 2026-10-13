import {css} from "lit";
import bootstrapIcons from "../Styles/bootstrap-icons";

/**
 * Row styles for et2-nextmatch.  These are loaded inside the Et2Datagrid shadowRoot.
 * Only EGroupware-wide styles are allowed here.
 *
 * bootstrapIcons (the full per-glyph `.bi-xxx::before { content: "\fxxx" }` ruleset, normally
 * adopted per-component via Et2Widget.ts's own `static styles`) is included here explicitly -
 * ticket #126391 (Sam, 2026-10-13: "Markierungen Mails werden ... nicht angezeigt", flag icons
 * missing from the mail row list since the move to this widget). Row content is rendered as
 * LIGHT DOM inside this shadow root for virtualization (confirmed live: a row's own
 * `<et2-image class="bi-flag-fill">` has no shadowRoot of its own at all), so it never gets
 * Et2Widget's per-component style adoption - the generic `font-family: bootstrap-icons` rule
 * above still reached it (inherited custom styling does cross shadow boundaries), but the actual
 * `content: "\fxxx"` glyph value for any specific icon class never did, rendering a correctly
 * coloured, correctly sized, but completely BLANK icon. Not mail-specific: any app's nextmatch
 * rows using a bootstrap-icon glyph class hit the identical gap.
 */
export default css`
	${bootstrapIcons}

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

	/*
	 * .et2_link is the same "link" marker class apps put directly on a plain <et2-description>,
	 * not a dedicated widget - it renders its content into light DOM (no shadow root to scope
	 * into), so unlike et2-link/et2-url_ro above this needs a plain descendant selector rather
	 * than ::part(). Same fix, same reason: the host is stretched to the full cell width by the
	 * row's flex layout, but its own rendered content only wraps the visible text.
	 *
	 * Deliberately a universal selector, not just "a": most apps set an href (rendering a real
	 * anchor), but some (calendar's "Join videoconference", mail compose's attachment name,
	 * filemanager's et2-vfs-name, projectmanager's title) only set onclick with no href at all,
	 * which doesn't render an anchor - the click handler lives on the host element itself.
	 * Scoping to just anchors would leave those completely unclickable (no descendant left to
	 * re-enable pointer-events on, so hit-testing would fall through the host to whatever is
	 * behind it and never fire the handler); a universal selector re-enables every rendered
	 * descendant regardless of which pattern applies, matching the same et2-url_ro breadth
	 * already used above (also note: no backtick characters in this comment - it lives inside
	 * the css tagged template literal below, and an unescaped backtick here would terminate the
	 * string early and corrupt the whole minified bundle into broken JS, exactly as happened
	 * once while writing this very comment).
	 */
	.et2_link {
		pointer-events: none;
	}

	.et2_link * {
		pointer-events: auto;
	}
`;
