<?php
/**
 * EGroupware API - Content Security Policy headers
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @subpackage header
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Header;

use EGroupware\Api;

/**
 * Content Security Policy headers
 */
class ContentSecurityPolicy
{
	/**
	 * Additional attributes or urls for CSP beside always added 'self' for everything not 'none'
	 *
	 *	- "script-src 'self' 'unsafe-eval'" allows only self and eval, but forbids inline scripts, onchange, etc
	 *	- "connect-src 'self'" allows ajax requests only to self
	 *	- "style-src 'self' 'unsafe-inline'" allows only self and inline style, which we need
	 *	- "frame-src 'self' manual.egroupware.org" allows frame and iframe content only for self or manual.egroupware.org
	 *	- "manifest-src 'self'"
	 *  - "'"frame-ancestors 'self'" does not allow to frame (embed in frameset) other then self / clickjacking protection
	 *	- "media-src 'self' data:"
	 *	- "img-src 'self' data: https:"
	 *	- "default-src 'none'" disallows all not explicitly set sources
	 *
	 * @var array
	 */
	private static $sources = array(
		// 'blob:' here is a CORE default, not app-specific - ticket #125641 (2026-10-04): found
		// live that mail's own PDF-attachment print/view wrapper (MailJmap.wrapPdfViewerWithDownload(),
		// pdf.js worker loading, both via a blob: object URL) only worked because smallpart happens
		// to be installed on the same instance - smallpart's own csp_frame_src() hook (misusing that
		// hook slot, the only one with per-app aggregation for frame-src/connect-src - see add()'s
		// own code below) directly calls self::add('script-src', 'blob:') as a side effect, which
		// then applies GLOBALLY to every app's own CSP for the rest of that request, regardless of
		// which app's hook triggered it. On any install without smallpart, that accidental grant
		// would be missing and mail's own, unrelated blob:-script feature would silently break with
		// no visible error (a CSP-blocked script failure is silent by nature) - 'blob:' is already a
		// core-trusted scheme for both img-src and object-src below, extending that same trust to
		// script-src (a well-established, secure pattern for dynamically-created worker/script
		// blobs) belongs at this level, not behind an unrelated app's install state.
		//
		// 'trusted-types-eval' + the (not-yet-enabled, see below) trusted-types directive
		// (ralf, 2026-10-05, master only for now - see api/js/jsapi/egw_trusted_types.ts's own
		// docblock for the full reasoning): narrows 'unsafe-eval' down to exactly this app's own
		// seven new Function() call sites (all routed through that module's trustedScript()),
		// rather than leaving eval open for literally any string anywhere. Deliberately NOT
		// replacing 'unsafe-eval' - kept side-by-side so a browser that doesn't support Trusted
		// Types yet (pre Safari 26/Firefox 148/Chrome+Edge 145 - caniuse.com/?search=trusted-types-eval)
		// just falls back to it with zero regression.
		// @see https://centralcsp.com/en/blog/trusted-types-eval-csp
		//
		// require-trusted-types-for 'script' is DELIBERATELY NOT enabled yet (tried live
		// 2026-10-05, reverted within the hour): it doesn't just gate eval()/new Function() - it
		// enforces on EVERY DOM-XSS sink application-wide (.innerHTML/.outerHTML/document.write()/
		// etc.), requiring a TrustedHTML object instead of a plain string for ALL of them. jQuery's
		// own .html()/innerHTML and the offline.min.js library both broke immediately on page load
		// (TypeError: "This document requires 'TrustedHTML' assignment"). Enabling this for real
		// needs a 'default' Trusted Types policy (a specially-reserved policy name that
		// automatically intercepts any UNWRAPPED sink assignment app-wide, including third-party
		// code we don't control) covering createHTML() as a passthrough at minimum, likely wired up
		// alongside egw_trusted_types.ts - not attempted yet, much bigger retrofit than the eval
		// call sites alone. 'trusted-types' below stays declared (harmless on its own re: sink
		// ENFORCEMENT - it only restricts which POLICY NAMES may be created, independent of
		// require-trusted-types-for) so egw_trusted_types.ts's createPolicy('egw-legacy-eval', ...)
		// call keeps succeeding once this policy allowlist is reached in a future, more careful pass.
		// That allowlist restriction itself is NOT harmless though - found live 2026-10-05: lit-html
		// (this app's own UI framework, loaded on every page regardless of Trusted Types) proactively
		// creates its OWN named policy ('lit-html') on startup, unconditionally, for its own internal
		// sanitizeDOMValue() use - blocked outright the moment 'trusted-types' lists anything that
		// doesn't include it ("Creating a TrustedTypePolicy named 'lit-html' violates..."). Added
		// here; if another bundled library's own policy name shows up blocked later, same fix.
		//
		// 'allow-duplicates' also needed - found live 2026-10-05, immediately after the above:
		// "a TrustedTypePolicy with that name already exists and the directive does not contain
		// 'allow-duplicates'". This app's own per-app bundles each pull in their own copy of lit/
		// lit-html (rollup's chunking doesn't fully dedupe it across every entry point), so more
		// than one copy of lit-html's own createPolicy('lit-html', ...) bootstrap call can run in
		// the same document - the spec blocks RE-creating an existing policy name by default (an
		// anti-hijacking guard, to stop an attacker from overwriting an already-trusted policy),
		// but here it's just this app's own benign multi-bundle structure, not an attack.
		//
		// 'dompurify' (DOMPurify, bundled for Et2Image/et2-html-area sanitisation - see
		// api/js/etemplate/Et2Image/dompurify-shim.ts) also proactively creates its OWN named
		// policy, same pattern as lit-html - found live 2026-10-05 right after the above two fixes.
		// Searched every bundled dependency directly for the literal createPolicy('name', ...)
		// pattern (`grep -rhoE "createPolicy\(['\"][a-zA-Z0-9_-]+['\"]" node_modules/`) rather than
		// keep discovering these one at a time live - lit-html and dompurify are the only two REAL
		// runtime ones in this app's entire dependency tree ('default'/'my-organization' only ever
		// appear inside dompurify's own README.md as doc examples, never executed).
		'script-src'  => array("'unsafe-eval'", "'trusted-types-eval'", 'blob:'),
		'trusted-types' => ['egw-legacy-eval', 'lit-html', 'dompurify', "'allow-duplicates'"],
		'style-src'   => array("'unsafe-inline'"),	// eTemplate styles and custom framework colors
		'connect-src' => null,	// NOT array(), to call the hook
		'frame-src'   => null,	// NOT array(), to call the hook
		'manifest-src'=> ["'self'"],
		'frame-ancestors' => ["'self'"],	// does not allow to frame (embed in frameset) other than self / clickjacking protection
		'media-src'   => ["data:"],
		'img-src'     => ["data:", "https:", "blob:"],
		'font-src'    => ["'self'"],
		'default-src' => ["'none'"],	// disallows all not explicit set sources!
	);

	/**
	 * Directives whose values are NEVER "'self'" / a URL - send()'s own auto-"'self'"-prepend
	 * (below) must not apply to them. 'trusted-types' takes bare policy-name tokens (not even
	 * quoted) and 'require-trusted-types-for' takes only the fixed keyword 'script' - "'self'"
	 * would be meaningless, and almost certainly rejected outright, in either one.
	 *
	 * @var array
	 */
	private static $no_self_sources = ['trusted-types', 'require-trusted-types-for'];

	/**
	 * Add Content-Security-Policy sources
	 *
	 * Calling this method with an empty array for frame-src or connect-src causes the hook to NOT run and just set 'self'!
	 *
	 * @param string $source valid CSP source types like 'script-src', 'style-src', 'connect-src', 'frame-src', ...
	 * @param string|array $_attrs 'unsafe-eval', 'unsafe-inline' (without quotes!), full URLs or protocols (incl. colon!)
	 * 	'none' removes all other attributes, even ones set later!
	 * @param bool $reset =false true: remove existing default or hook attributes
	 */
	public static function add($source, $_attrs, $reset=false)
	{
		$attrs = (array)$_attrs;

		if ($reset)
		{
			self::$sources[$source] = [];
		}
		elseif (!isset(self::$sources[$source]))
		{
			// set frame-src attrs of API and apps via hook
			if (in_array($source, ['frame-src', 'connect-src']) && $_attrs !== [])
			{
				// for regular (non login) pages, call hook allowing apps to add additional frame- and connect-src
				if (basename($_SERVER['PHP_SELF']) !== 'login.php' &&
					// no permission / user-run-rights check for connect-src
					($app_additional = Api\Hooks::process('csp-'.$source, [], $source === 'connect-src')))
				{
					foreach($app_additional as $app => $additional)
					{
						if ($additional) $attrs = array_unique(array_merge($attrs, $additional));
					}
				}
			}
			self::$sources[$source] = [];
		}
		// Shoelace needs connect-src: data:
		if ($source === 'connect-src') /** @noinspection UnsupportedStringOffsetOperationsInspection */ $attrs[] = 'data:';

		foreach($attrs as $attr)
		{
			if (in_array($attr, array('none', 'self', 'unsafe-eval', 'unsafe-inline', 'trusted-types-eval')))
			{
				$attr = "'$attr'";	// automatic add quotes
			}
			// only add scheme and host, not path
			elseif ($source !== 'report-uri' && ($parsed=parse_url($attr)) && !empty($parsed['scheme']) && !empty($parsed['path']))
			{
				$attr = $parsed['scheme'].'://'.$parsed['host'].(!empty($parsed['port']) ? ':'.$parsed['port'] : '');
			}
			if (!in_array($attr, self::$sources[$source]))
			{
				self::$sources[$source][] = $attr;
				//error_log(__METHOD__."() setting CSP script-src $attr ".function_backtrace());
			}
		}
	}

	/**
	 * Add a nonce to a given source
	 *
	 * @param string $source
	 * @return string
	 * @throws \Exception
	 */
	public static function addNonce($source='script-src')
	{
		static $nonce=null;
		if (!isset($nonce))
		{
			$nonce = base64_encode(random_bytes(16));
			self::add($source, "'nonce-$nonce'");
		}
		return $nonce;
	}

	/**
	 * Set Content-Security-Policy attributes for script-src: 'unsafe-eval' and/or 'unsafe-inline'
	 *
	 * Old pre-et2 apps might need to call Api\Headers::script_src_attrs(array('unsafe-eval','unsafe-inline'))
	 *
	 * EGroupware itself currently still requires 'unsafe-eval'!
	 *
	 * @param string|array $set 'unsafe-eval', 'unsafe-inline' (without quotes!), full URLs or protocols (incl. colon!)
	 */
	public static function add_script_src($set=null)
	{
		self::add('script-src', $set);
	}

	/**
	 * Set Content-Security-Policy attributes for style-src: 'unsafe-inline'
	 *
	 * EGroupware itself currently still requires 'unsafe-inline'!
	 *
	 * @param string|array $set 'unsafe-eval', 'unsafe-inline' (without quotes!), full URLs or protocols (incl. colon!)
	 */
	public static function add_style_src($set=null)
	{
		self::add('style-src', $set);
	}

	/**
	 * Set Content-Security-Policy attributes for connect-src:
	 *
	 * Calling this method with an empty array for caused the hook to NOT run and just set 'self'!
	 *
	 * @param string|array $set 'unsafe-eval', 'unsafe-inline' (without quotes!), full URLs or protocols (incl. colon!)
	 */
	public static function add_connect_src($set=null)
	{
		self::add('connect-src', $set);
	}

	/**
	 * Set/get Content-Security-Policy attributes for frame-src:
	 *
	 * Calling this method with an empty array for caused the hook to NOT run and just set 'self'!
	 *
	 * @param string|array $set 'unsafe-eval', 'unsafe-inline' (without quotes!), full URLs or protocols (incl. colon!)
	 */
	public static function add_frame_src($set=null)
	{
		self::add('frame-src', $set);
	}

	/**
	 * Send Content-Security-Policy header
	 *
	 * @link http://content-security-policy.com/
	 */
	public static function send()
	{
		self::add('connect-src', null);    // set defaults for connect-src (no run rights checked)
		self::add('frame-src', null);    // set defaults for frame-src

		// force default-src 'none'
		self::$sources['default-src'] = ["'none'"];

		$policies = array();
		foreach (self::$sources as $source => $urls) {
			// for 'none' remove source, as we use "default-src 'none'"
			if (in_array("'none'", $urls)) {
				if ($source !== 'default-src') continue;
			}
			// automatic add 'self', if not 'none' - except for directives whose values are never
			// "'self'" to begin with (trusted-types/require-trusted-types-for, see $no_self_sources)
			elseif (!in_array($source, self::$no_self_sources) && !in_array("'self'", $urls)) {
				array_unshift($urls, "'self'");
			}
			$policies[] = "$source " . implode(' ', $urls);
		}
		self::header(implode('; ', $policies));
	}

	/**
	 * Send a CSP header with given policy
	 *
	 * @param {string} $csp
	 */
	public static function header($csp)
	{
		$user_agent = UserAgent::type();
		$version = UserAgent::version();

		// recommendation is to not send regular AND deprecated headers together, as they can cause unexpected behavior
		if ($user_agent === 'chrome' && $version < 25 || $user_agent === 'safari' && $version < 7)
		{
			header("X-Webkit-CSP: $csp");	// Chrome: <= 24, Safari incl. iOS
		}
		elseif ($user_agent === 'firefox' && $version < 23 || $user_agent === 'msie')	// Edge is reported as 'edge'!
		{
			header("X-Content-Security-Policy: $csp");
		}
		else
		{
			header("Content-Security-Policy: $csp");
		}
	}
}