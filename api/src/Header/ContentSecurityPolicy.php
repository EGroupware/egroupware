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
	 * Additional attributes or urls for CSP beside always added "self"
	 *
	 *	- "script-src 'self' 'unsafe-eval'" allows only self and eval, but forbids inline scripts, onchange, etc
	 *	- "connect-src 'self'" allows ajax requests only to self
	 *	- "style-src 'self' 'unsafe-inline'" allows only self and inline style, which we need
	 *	- "frame-src 'self' manual.egroupware.org" allows frame and iframe content only for self or manual.egroupware.org
	 *
	 * @var array
	 */
	private static $sources = array(
		'script-src'  => array("'unsafe-eval'"),
		'style-src'   => array("'unsafe-inline'"),
		'connect-src' => array(),
		'frame-src'   => null,	// NOT array(), to allow setting no default frame-src!
	);

	/**
	 * Add Content-Security-Policy sources
	 *
	 * Calling this method with an empty array for frame-src, sets no defaults but "'self'"!
	 *
	 * @param string|array $set =array() URL (incl. protocol!)
	 * @param string $source valid CSP source types like 'script-src', 'style-src', 'connect-src', 'frame-src', ...
	 * @param string|array $attrs 'unsafe-eval' and/or 'unsafe-inline' (without quotes!) or URL (incl. protocol!)
	 */
	public static function add($source, $attrs)
	{
		if (!isset(self::$sources[$source]))
		{
			// set frame-src attrs of API and apps via hook
			if ($source == 'frame-src' && !isset($attrs))
			{
				$attrs = array('www.egroupware.org');
				if (($app_additional = Api\Hooks::process('csp-frame-src')))
				{
					foreach($app_additional as $addtional)
					{
						if ($addtional) $attrs = array_unique(array_merge($attrs, $addtional));
					}
				}
			}
			self::$sources[$source] = array();
		}
		foreach((array)$attrs as $attr)
		{
			if (in_array($attr, array('none', 'self', 'unsafe-eval', 'unsafe-inline')))
			{
				$attr = "'$attr'";	// automatic add quotes
			}
			if (!in_array($attr, self::$sources[$source]))
			{
				self::$sources[$source][] = $attr;
				//error_log(__METHOD__."() setting CSP script-src $attr ".function_backtrace());
			}
		}
	}

	/**
	 * Set Content-Security-Policy attributes for script-src: 'unsafe-eval' and/or 'unsafe-inline'
	 *
	 * Using CK-Editor currently requires both to be set :(
	 *
	 * Old pre-et2 apps might need to call Api\Headers::script_src_attrs(array('unsafe-eval','unsafe-inline'))
	 *
	 * EGroupware itself currently still requires 'unsafe-eval'!
	 *
	 * @param string|array $set =array() 'unsafe-eval' and/or 'unsafe-inline' (without quotes!) or URL (incl. protocol!)
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
	 * @param string|array $set =array() 'unsafe-inline' (without quotes!) and/or URL (incl. protocol!)
	 */
	public static function add_style_src($set=null)
	{
		self::add('style-src', $set);
	}

	/**
	 * Set Content-Security-Policy attributes for connect-src:
	 *
	 * @param string|array $set =array() URL (incl. protocol!)
	 */
	public static function add_connect_src($set=null)
	{
		self::add('connect-src', $set);
	}

	/**
	 * Set/get Content-Security-Policy attributes for frame-src:
	 *
	 * Calling this method with an empty array sets no frame-src, but "'self'"!
	 *
	 * @param string|array $set =array() URL (incl. protocol!)
	 * @return string with attributes eg. "'unsafe-inline'"
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
		self::add('frame-src', null);	// set defaults for frame-src

		$policies = array();
		foreach(self::$sources as $source => $urls)
		{
			$policies[] = "$source 'self' ".implode(' ', $urls);
		}
		$csp = implode('; ', $policies);

		//$csp = "default-src * 'unsafe-eval' 'unsafe-inline'";	// allow everything

		$user_agent = UserAgent::type();
		$version = UserAgent::version();

		// recommendaton ist to not send regular AND deprecated headers together, as they can cause unexpected behavior
		if ($user_agent == 'chrome' && $version < 25 || $user_agent == 'safari' && $version < 7)
		{
			header("X-Webkit-CSP: $csp");	// Chrome: <= 24, Safari incl. iOS
		}
		elseif ($user_agent == 'firefox' && $version < 23 || $user_agent == 'msie')	// Edge is reported as 'edge'!
		{
			header("X-Content-Security-Policy: $csp");
		}
		else
		{
			header("Content-Security-Policy: $csp");
		}
	}
}
