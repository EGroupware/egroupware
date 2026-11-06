/**
 * EGroupware clientside API: Trusted Types wrapper for this app's own new Function()/eval() call sites
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage jsapi
 * @link http://www.egroupware.org
 */

/**
 * Narrows this app's own `script-src 'unsafe-eval'` CSP grant down to exactly its OWN seven
 * `new Function()` call sites (`et2_core_legacyJSFunctions.ts`'s legacy onchange/onclick attribute
 * compiler, `et2_widget_script.ts`'s `<script>` customisation widget, `et2_extension_
 * nextmatch_rowProvider.ts`'s DOM-path compiler, `egw_json.ts`'s JSON-response "script" plugin,
 * `egw_open.ts`'s `javascript:` href handling, and notifications/js/app.ts's own two action-
 * execution sites) - instead of leaving eval()/new Function() wide open for ANY code anywhere
 * (first-party or, via some other XSS, injected) to call with an arbitrary string.
 *
 * `api/src/Header/ContentSecurityPolicy.php`'s own `script-src` default carries BOTH
 * `'unsafe-eval'` AND `'trusted-types-eval'`, plus a `trusted-types` policy-name allowlist
 * (`egw-legacy-eval` for this module, plus `lit-html`/`dompurify`/`'allow-duplicates'` for
 * bundled libraries that proactively create their own named policies on load) - deliberately NOT
 * replacing 'unsafe-eval' outright:
 *
 * - `require-trusted-types-for 'script'` - the directive that would actually ENFORCE Trusted
 *   Types on eval()/new Function() (and, much more broadly, on every innerHTML/outerHTML/
 *   document.write() sink app-wide) - is DELIBERATELY NOT enabled yet. Tried live 2026-10-05,
 *   reverted within the hour: it broke jQuery's own .html() and the bundled offline.min.js
 *   immediately. See ContentSecurityPolicy.php's own comment for the full reasoning and what
 *   enabling it for real would need. Without it, 'trusted-types'/'trusted-types-eval' are
 *   currently inert (restrict policy-NAME creation only, enforce nothing) - this module and its
 *   seven call sites exist now so enabling enforcement later is a CSP-only change, not a code
 *   change.
 * - On a browser that does NOT support Trusted Types at all (pre Safari 26/Firefox 148/
 *   Chrome+Edge 145 - caniuse.com/?search=trusted-types-eval, checked 2026-10-05): it simply
 *   doesn't recognise 'trusted-types-eval'/trusted-types at all, and falls back to 'unsafe-eval'
 *   alone - eval() keeps working exactly as before, zero regression. createPolicy() below is
 *   itself feature-detected for the exact same reason.
 *
 * Deliberately master-only for now (ralf, 2026-10-05): wants this to sit and soak for a while
 * before considering a backport to 26, given how foundational et2_core_legacyJSFunctions.ts's own
 * call site is (the base widget classes' own onchange/onclick attribute compiler).
 *
 * The policy's own createScript() does no validation at all (identity passthrough) - this is
 * deliberate for now: the inputs are still exactly the same first-party, server/hook/DOM-derived
 * strings these call sites already executed unconditionally before Trusted Types existed. The
 * actual security value here is the NARROWING (only code that imports this module can create a
 * TrustedScript at all - nothing else in the entire codebase, nor any injected payload elsewhere,
 * has access to the policy object to forge one), not input sanitisation - tightening
 * createScript() itself to validate shape/origin of `code` is a possible future step, not required
 * for this first cut.
 */
let policy : {createScript(code : string) : any} | null = null;

function getPolicy() : {createScript(code : string) : any}
{
	if (!policy)
	{
		const tt = (window as any).trustedTypes;
		policy = tt ? tt.createPolicy('egw-legacy-eval', {createScript : (code : string) => code}) :
			{createScript : (code : string) => code};
	}
	return policy;
}

/**
 * Wrap a code string for eval()/new Function() - see this module's own docblock for why.
 * Only ever call this immediately before handing the result to eval()/new Function(); never
 * store or pass a TrustedScript around otherwise, the policy is meant to be a narrow choke point.
 */
export function trustedScript(code : string) : any
{
	return getPolicy().createScript(code);
}
