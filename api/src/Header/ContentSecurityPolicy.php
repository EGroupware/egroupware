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
	private static $sources = array(				// our dhtmlxcommon version (not the current) uses eval,
		'script-src'  => array("'unsafe-eval'"),	// sidebox javascript links, maybe more
		'style-src'   => array("'unsafe-inline'"),	// eTemplate styles and custom framework colors
		'connect-src' => null,	// NOT array(), to call the hook
		'frame-src'   => null,	// NOT array(), to call the hook
		'manifest-src'=> ["'self'"],
		'frame-ancestors' => ["'self'"],	// does not allow to frame (embed in frameset) other then self / clickjacking protection
		'media-src'   => ["data:"],
		'img-src'     => ["data:", "https:", "blob:"],
		'default-src' => ["'none'"],	// disallows all not explicit set sources!
	);

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
			if (in_array($attr, array('none', 'self', 'unsafe-eval', 'unsafe-inline')))
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
			// automatic add 'self', if not 'none'
			elseif (!in_array("'self'", $urls)) {
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