<?php
/**
 * EGroupware security relevant functions
 *
 * Usually loaded via header.inc.php or api/src/loader/common.php
 *
 * Found live via ticket #125621 (a real customer, Schofer/aERP, 2026-10-09): this file used to
 * also scan every $_GET/$_POST/$_REQUEST/$_COOKIE value for script-tag-shaped patterns and run
 * Api\Html\HtmLawed::purify() on anything that matched - a pre-CSP-era defense against a page
 * somewhere echoing raw request data back unescaped. It unconditionally ran that purifier on the
 * WHOLE raw value of any matching field, not just the offending fragment - for a REST-compose
 * request whose JSON `preset` field happened to embed a full HTML document (a `<meta>` tag is
 * enough to trigger it), this ran an HTML purifier over a JSON string, mangling it entirely
 * (hoisting/reformatting the document's own embedded `<style>` block to the front, stripping its
 * DOCTYPE/html/head wrapper and class attribute values) - exactly the class of bug CSP is the
 * correct place to defend against now instead (the threat this scanner targeted - an inline
 * `<script>`/`onclick=`/`javascript:` URL actually executing - is the thing CSP's script-src
 * blocks at the point of execution, not something worth trying to pre-filter out of every
 * incoming request value via regex). Removed entirely (ralf, 2026-10-09: checked security.php's
 * own history - its last real change predates this file's own register_globals handling being
 * already-dead code for well over a decade; "I'd say throw everything out"), keeping only the two
 * functions below that are still genuinely in use elsewhere (php_safe_unserialize()/
 * json_php_unserialize()) - unrelated to the removed scanner, just living in the same file.
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Unserialize a php serialized string, but only if it contains NO objects 'O:\d:"' or 'C:\d:"' pattern
 *
 * Should be used for all external content, to guard against exploidts.
 *
 * PHP 7.0+ can be told not to instantiate any classes (and calling eg. it's destructor).
 * In fact it instantiates it as __PHP_Incomplete_Class without any methods and therefore disarming threads.
 *
 * @param string $str
 * @return mixed
 */
function php_safe_unserialize($str)
{
	if (PHP_VERSION >= 7)
	{
		return unserialize($str, array('allowed_classes' => false));
	}
	if ((strpos($str, 'O:') !== false || strpos($str, 'C:') !== false) &&
		preg_match('/(^|;|{)[OC]:\d+:"/', $str))
	{
		error_log(__METHOD__."('$str') contains objects --> return NULL");
		return null;	// null, not false, to not trigger behavior of returning string itself to app code
	}
	return unserialize($str);
}

/**
 * Unserialize a json or php serialized array
 *
 * Used to migrate from PHP serialized database values to json-encoded ones.
 *
 * @param string $str string with serialized array
 * @param boolean $allow_not_serialized =false true: return $str as is, if it is no serialized array
 * @return array|string|false false if content can not be unserialized (not null like json_decode!)
 */
function json_php_unserialize($str, $allow_not_serialized=false)
{
	if (!isset($str)) return $str;

	if ((in_array($str[0], array('a', 'i', 's', 'b', 'O', 'C')) && $str[1] == ':' || $str === 'N;') &&
		($arr = php_safe_unserialize($str)) !== false || $str === 'b:0;')
	{
		return $arr;
	}
	if (!$allow_not_serialized || $str[0] == '[' || $str[0] == '{' || $str[0] == '"' || $str === 'null' || ($val = json_decode($str, true)) !== null)
	{
		// json_decode return null, if it cant decode the content
		if (isset($val) || ($val = json_decode($str, true)) !== null || $str === 'null')
		{
			return $val;
		}
		return false;
	}
	return $str;
}
