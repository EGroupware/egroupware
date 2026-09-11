/**
 * EGroupware eTemplate2 - markdown editing commands
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

/**
 * Everything the format popup and the keyboard shortcuts can do to markdown source.
 */
export type MarkdownCommand =
	"bold" | "italic" | "strikethrough" | "code" | "link"
	| "normal" | "h1" | "h2" | "h3" | "h4" | "h5" | "h6"
	| "quote" | "ul" | "ol" | "checklist";

/**
 * A command's effect: the new source, and where the selection should end up.
 */
export interface CommandResult
{
	value: string;
	start: number;
	end: number;
}

/**
 * Delimiters for the commands that wrap the selection.
 */
const INLINE_DELIMITER: { [command: string]: string } = {
	bold: "**",
	italic: "*",
	strikethrough: "~~",
	code: "`"
};

/**
 * Line prefixes for the commands that rewrite the start of a line.
 * "ol" is special-cased, it numbers its lines.
 */
const BLOCK_PREFIX: { [command: string]: string } = {
	h1: "# ",
	h2: "## ",
	h3: "### ",
	h4: "#### ",
	h5: "##### ",
	h6: "###### ",
	quote: "> ",
	ul: "- ",
	checklist: "- [ ] "
};

/**
 * Any block prefix we know how to remove, so switching between them replaces
 * rather than stacks.  Checklist has to come before the plain bullet, and the
 * ordered-list alternative before it would otherwise match nothing.
 */
const ANY_BLOCK_PREFIX = /^([ \t]*)(#{1,6} |> |[-*+] \[[ xX]\] |[-*+] |\d+\. )?/;

/**
 * Is `command` one that wraps the selection rather than prefixing lines?
 */
function isInline(command: MarkdownCommand): boolean
{
	return typeof INLINE_DELIMITER[command] !== "undefined";
}

/**
 * True when `value` has `delimiter` immediately outside [start, end).
 *
 * A single "*" needs care: in "**bold**" the character next to the selection is a "*",
 * but it belongs to a bold marker, so italic must not claim it.
 */
function isWrappedOutside(value: string, start: number, end: number, delimiter: string): boolean
{
	const len = delimiter.length;
	if(start < len || value.slice(start - len, start) !== delimiter || value.slice(end, end + len) !== delimiter)
	{
		return false;
	}
	if(delimiter === "*")
	{
		// "**" on either side is bold, not two italics
		return !(value.slice(start - 2, start) === "**" || value.slice(end, end + 2) === "**");
	}
	return true;
}

/**
 * True when the selection itself starts and ends with `delimiter`.
 */
function isWrappedInside(selected: string, delimiter: string): boolean
{
	return selected.length >= 2 * delimiter.length
		&& selected.startsWith(delimiter) && selected.endsWith(delimiter);
}

/**
 * Wrap the selection in `delimiter`, or unwrap it when it is already wrapped.
 */
function applyInline(value: string, start: number, end: number, delimiter: string): CommandResult
{
	const selected = value.slice(start, end);
	const len = delimiter.length;

	// "**bold**" selected whole - drop the delimiters
	if(isWrappedInside(selected, delimiter))
	{
		const inner = selected.slice(len, -len);
		return {value: value.slice(0, start) + inner + value.slice(end), start: start, end: start + inner.length};
	}

	// "bold" selected inside "**bold**" - drop the delimiters around it
	if(isWrappedOutside(value, start, end, delimiter))
	{
		return {
			value: value.slice(0, start - len) + selected + value.slice(end + len),
			start: start - len,
			end: end - len
		};
	}

	return {
		value: value.slice(0, start) + delimiter + selected + delimiter + value.slice(end),
		start: start + len,
		end: end + len
	};
}

/**
 * Turn the selection into a link, leaving the caret in the url.
 */
function applyLink(value: string, start: number, end: number): CommandResult
{
	const selected = value.slice(start, end);
	const inserted = "[" + selected + "](url)";
	// select the "url" placeholder so typing replaces it
	const urlStart = start + selected.length + 3;
	return {
		value: value.slice(0, start) + inserted + value.slice(end),
		start: urlStart,
		end: urlStart + 3
	};
}

/**
 * Grow [start, end) to cover whole lines.
 */
function lineBounds(value: string, start: number, end: number): { from: number, to: number }
{
	const from = value.lastIndexOf("\n", start - 1) + 1;
	let to = value.indexOf("\n", end);
	if(to === -1)
	{
		to = value.length;
	}
	// a selection ending exactly on a newline should not drag in the next line
	if(end > start && value[end - 1] === "\n")
	{
		to = end - 1;
	}
	return {from: from, to: to};
}

/**
 * Prefix every selected line, or strip the prefix when every line already has it.
 * "normal" always strips and never adds.
 */
function applyBlock(value: string, start: number, end: number, command: MarkdownCommand): CommandResult
{
	const {from, to} = lineBounds(value, start, end);
	const lines = value.slice(from, to).split("\n");
	const prefix = BLOCK_PREFIX[command] ?? "";

	const wanted = (line: string, index: number) => command === "ol" ? (index + 1) + ". " : prefix;

	// toggling off only makes sense when every line already carries what we would add
	const allPrefixed = command !== "normal" && lines.every((line, index) =>
	{
		const match = line.match(ANY_BLOCK_PREFIX);
		return (match?.[2] ?? "") === wanted(line, index);
	});

	const changed = lines.map((line, index) =>
	{
		const match = line.match(ANY_BLOCK_PREFIX);
		const indent = match?.[1] ?? "";
		const rest = line.slice((match?.[0] ?? "").length);
		return command === "normal" || allPrefixed ? indent + rest : indent + wanted(line, index) + rest;
	}).join("\n");

	return {
		value: value.slice(0, from) + changed + value.slice(to),
		start: from,
		end: from + changed.length
	};
}

/**
 * Apply a markdown command to `value` over the selection [start, end).
 *
 * Pure: no DOM, no widget.  Returns the new source and where the selection belongs,
 * leaving it to the caller to write both back to the textarea.
 *
 * @param value markdown source
 * @param start selection start offset
 * @param end selection end offset
 * @param command what to do
 */
export function applyCommand(value: string, start: number, end: number, command: MarkdownCommand): CommandResult
{
	value = value ?? "";

	// clamp, so a stale selection can never slice outside the value
	start = Math.max(0, Math.min(start ?? 0, value.length));
	end = Math.max(start, Math.min(end ?? start, value.length));

	if(command === "link")
	{
		return applyLink(value, start, end);
	}
	if(isInline(command))
	{
		return applyInline(value, start, end, INLINE_DELIMITER[command]);
	}
	return applyBlock(value, start, end, command);
}

/**
 * The smallest replacement turning `oldValue` into `newValue`.
 *
 * Commands rebuild the whole source, but replacing the whole textarea would collapse the
 * browser's undo history into one entry.  Narrowing the edit to the region that actually
 * changed keeps Ctrl+Z granular, and keeps the caret from jumping in long documents.
 *
 * @param oldValue what the textarea holds now
 * @param newValue what the command produced
 */
export function minimalEdit(oldValue: string, newValue: string): { from: number, to: number, text: string }
{
	oldValue = oldValue ?? "";
	newValue = newValue ?? "";

	const max = Math.min(oldValue.length, newValue.length);

	let prefix = 0;
	while(prefix < max && oldValue[prefix] === newValue[prefix])
	{
		prefix++;
	}

	let suffix = 0;
	while(suffix < max - prefix
		&& oldValue[oldValue.length - 1 - suffix] === newValue[newValue.length - 1 - suffix])
	{
		suffix++;
	}

	return {
		from: prefix,
		to: oldValue.length - suffix,
		text: newValue.slice(prefix, newValue.length - suffix)
	};
}

/**
 * Character offset of the start of `line` (0-based) in `value`.
 */
export function offsetOfLine(value: string, line: number): number
{
	value = value ?? "";
	let offset = 0;
	for(let i = 0; i < Math.max(0, line); i++)
	{
		const next = value.indexOf("\n", offset);
		if(next === -1)
		{
			return value.length;
		}
		offset = next + 1;
	}
	return Math.min(offset, value.length);
}

/**
 * Map an offset in a block's *rendered* text back to an offset in the markdown source.
 *
 * Needed because a source line is far too coarse to click on: with breaks:true a whole run of
 * plain-text lines renders as ONE paragraph full of <br>, so every click in it maps to the same
 * line and the caret never moves off the start.
 *
 * The two strings are almost the same - rendered text is the source minus its markers - so a
 * parallel walk aligns them: matching characters advance both cursors, and a character that only
 * exists in the source (a "*", a "#", a "> ") advances the source alone.
 *
 * @param source markdown source
 * @param blockStart offset in `source` where the clicked block begins
 * @param rendered the block's rendered text
 * @param renderedOffset offset within `rendered` that was clicked
 */
export function sourceOffsetForRendered(source: string, blockStart: number, rendered: string,
										renderedOffset: number): number
{
	source = source ?? "";
	rendered = rendered ?? "";

	let i = Math.max(0, Math.min(blockStart, source.length));
	let r = 0;
	const target = Math.max(0, Math.min(renderedOffset, rendered.length));

	while(i < source.length)
	{
		const matches = r < rendered.length && source[i] === rendered[r];

		// stop once we are sitting ON the target character rather than on a marker in front
		// of it - otherwise clicking the "H" of "# Heading" would land the caret on the "#"
		if(r === target && (matches || r >= rendered.length))
		{
			break;
		}
		if(matches)
		{
			r++;
		}
		i++;
	}
	return i;
}
