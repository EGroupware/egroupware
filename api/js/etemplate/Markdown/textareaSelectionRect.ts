/**
 * EGroupware eTemplate2 - locate a textarea's selection on screen
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

/**
 * A textarea's text is not in the DOM, so there is no Range and no per-character rect to ask for.
 * The only way to find out where a character sits is to render the same text, with the same
 * metrics, somewhere we *can* measure - a hidden mirror div.
 *
 * Used to anchor the markdown format popup over the selection: sl-popup takes any object with a
 * getBoundingClientRect(), so selectionVirtualElement() hands it one.
 */

/**
 * Everything that changes where a glyph lands.  Anything missing here shows up as the popup
 * drifting away from the selection, so keep it in sync with reality rather than trimming it.
 */
const MIRRORED_STYLES = [
	"direction", "textAlign", "textTransform", "textIndent",
	"fontStyle", "fontVariant", "fontWeight", "fontStretch", "fontSize", "fontSizeAdjust",
	"fontFamily", "lineHeight", "letterSpacing", "wordSpacing", "tabSize",
	"borderTopWidth", "borderRightWidth", "borderBottomWidth", "borderLeftWidth", "borderStyle",
	"paddingTop", "paddingRight", "paddingBottom", "paddingLeft"
];

let mirror: HTMLDivElement = null;

/**
 * One reusable mirror, kept out of the flow and out of the accessibility tree.
 */
function getMirror(): HTMLDivElement
{
	if(!mirror)
	{
		mirror = document.createElement("div");
		mirror.setAttribute("aria-hidden", "true");
		mirror.style.position = "absolute";
		mirror.style.top = "0";
		mirror.style.left = "0";
		mirror.style.visibility = "hidden";
		mirror.style.pointerEvents = "none";
		mirror.style.overflow = "hidden";
		// a textarea wraps like this, whatever the host page says
		mirror.style.whiteSpace = "pre-wrap";
		mirror.style.wordWrap = "break-word";
		document.body.appendChild(mirror);
	}
	return mirror;
}

/**
 * Position of one character offset, relative to the textarea's border box.
 */
function offsetPosition(textarea: HTMLTextAreaElement, offset: number): { top: number, left: number, height: number }
{
	const computed = window.getComputedStyle(textarea);
	const div = getMirror();

	MIRRORED_STYLES.forEach(name => div.style[name] = computed[name]);

	// clientWidth is content + padding and already excludes the scrollbar, so deriving the
	// content width from it keeps the mirror honest when the textarea is scrolling
	const padding = parseFloat(computed.paddingLeft) + parseFloat(computed.paddingRight);
	div.style.boxSizing = "content-box";
	div.style.width = Math.max(0, textarea.clientWidth - padding) + "px";

	const value = textarea.value ?? "";
	div.textContent = value.slice(0, offset);

	// the remainder goes in a span: it makes the line the offset sits on wrap exactly as it
	// does in the textarea, and the span's own box is what we measure
	const marker = document.createElement("span");
	// a trailing newline collapses without something after it, and an empty span has no box
	marker.textContent = value.slice(offset) || ".";
	div.appendChild(marker);

	const divRect = div.getBoundingClientRect();
	// the marker spans the rest of the text and therefore wraps over several lines.
	// getBoundingClientRect() would union those line boxes and report the container's left
	// edge; the first client rect is the one line box that starts at our offset.
	const markerRect = marker.getClientRects()[0] ?? marker.getBoundingClientRect();

	const height = parseFloat(computed.lineHeight) || markerRect.height || parseFloat(computed.fontSize);

	div.textContent = "";

	return {
		top: markerRect.top - divRect.top,
		left: markerRect.left - divRect.left,
		height: height
	};
}

/**
 * Client rect covering the current selection of `textarea`, or null when there is nothing to
 * point at (no selection, or the element is not rendered).
 *
 * A selection spanning several lines reports only its first line - that is where the popup
 * belongs, and it keeps the anchor stable while the selection grows downwards.
 */
export function textareaSelectionRect(textarea: HTMLTextAreaElement): DOMRect
{
	if(!textarea || !textarea.isConnected || textarea.selectionStart === textarea.selectionEnd)
	{
		return null;
	}
	const box = textarea.getBoundingClientRect();
	if(!box.width && !box.height)
	{
		return null;
	}

	const start = offsetPosition(textarea, textarea.selectionStart);
	const end = offsetPosition(textarea, textarea.selectionEnd);

	const sameLine = Math.abs(start.top - end.top) < 1;
	const left = box.left - textarea.scrollLeft + start.left;
	const top = box.top - textarea.scrollTop + start.top;
	const right = sameLine ? box.left - textarea.scrollLeft + end.left : box.right;

	return new DOMRect(left, top, Math.max(1, right - left), start.height);
}

/**
 * The same rect wrapped as a floating-ui VirtualElement, ready for sl-popup's `anchor`.
 *
 * The rect is recomputed on every call rather than captured, because sl-popup re-reads the
 * anchor while repositioning - so scrolling or typing keeps the popup attached.
 */
export function selectionVirtualElement(textarea: HTMLTextAreaElement)
{
	return {
		getBoundingClientRect: () => textareaSelectionRect(textarea) ?? new DOMRect(0, 0, 0, 0)
	};
}
