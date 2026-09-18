/**
 * EGroupware eTemplate2 - history log: the diff widget's stylesheet, for use in a shadow root
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */

/**
 * Built once, then handed to every shadow root that hosts an `et2-diff`.  Null if the rules are
 * not in the document at all.
 */
let sheet : CSSStyleSheet | null = null;
let scanned = -1;

/**
 * The CSS that makes a diff look like a diff, as a stylesheet a shadow root can adopt.
 *
 * `et2-diff` renders the generated diff markup into its own *light* DOM on purpose, because the
 * rules that colour it - the diff2html library's, plus EGroupware's overrides that hide the
 * library's file header and recolour the +/- lines - are shipped in the page's theme stylesheet
 * (grunt concatenates `diff2html.min.css` and `etemplate2.css` into `<theme>.min.css`).  A
 * document stylesheet reaches a widget's light DOM, so anywhere a diff sits directly on the page
 * it is styled with no further work.
 *
 * It never crosses a shadow boundary, though, and the history log puts its diffs inside two:
 * `et2-historylog-value`'s, for the rows, and the history log's own, for the popped-out copy.
 * Without this the diff arrives as unstyled text - no colours, no line numbers, and the library's
 * "diff CHANGED" file header, which EGroupware hides, visible.
 *
 * Rather than keep a second copy of the library's CSS that would drift, the rules are lifted out
 * of whichever document stylesheet already carries them.  That also means a theme which restyles
 * diffs restyles these too.  Returns null - diffs render as plain text - if no readable
 * stylesheet mentions them, which is what happens without the theme's CSS anyway.
 */
export function diffStyleSheet() : CSSStyleSheet | null
{
	// Scanned once, and again only if the set of stylesheets has grown or shrunk - the history
	// log is lazy, so by the time it asks the theme is loaded, but a test or a theme switch can
	// add the rules later.
	if(sheet || scanned === document.styleSheets.length)
	{
		return sheet;
	}
	scanned = document.styleSheets.length;

	const rules : string[] = [];
	for(const source of Array.from(document.styleSheets))
	{
		let sourceRules : CSSRuleList;
		try
		{
			sourceRules = source.cssRules;
		}
		catch(e)
		{
			// Reading a stylesheet served from another origin throws; ours are not, so skip it
			continue;
		}
		for(const rule of Array.from(sourceRules))
		{
			// "d2h-" catches the library's own rules including the `:host, :root` custom property
			// block it opens with, "et2-diff" catches EGroupware's overrides
			const text = rule.cssText || "";
			if(text.indexOf("d2h-") !== -1 || text.indexOf("et2-diff") !== -1)
			{
				rules.push(text);
			}
		}
	}
	if(!rules.length)
	{
		return sheet;
	}
	try
	{
		const built = new CSSStyleSheet();
		built.replaceSync(rules.join("\n"));
		sheet = built;
	}
	catch(e)
	{
		// Constructable stylesheets are not available - leave the diff as text
	}
	return sheet;
}

/**
 * Adopt the diff stylesheet into a shadow root, once.
 */
export function adoptDiffStyles(root : ShadowRoot | null | undefined)
{
	const diffStyles = diffStyleSheet();
	if(!root || !diffStyles || root.adoptedStyleSheets.indexOf(diffStyles) !== -1)
	{
		return;
	}
	root.adoptedStyleSheets = [...root.adoptedStyleSheets, diffStyles];
}
