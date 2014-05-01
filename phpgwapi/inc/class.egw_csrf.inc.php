<?php
/**
 * EGroupware API: CSRF (Cross Site Request Forgery) protection
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2014 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

/**
 * Class supplying methods to prevent successful CSRF by requesting a random token,
 * stored on server and validated when request get posted.
 *
 * CSRF token generation used openssl_random_pseudo_bytes, if available, otherwise
 * mt_rand based auth::randomstring is used.
 *
 * CSRF tokens are stored (incl. optional purpose) in user session.
 *
 * If a token does not validate (incl. purpose, if specified in generation)
 * the request will be imediatly terminated.
 */
class egw_csrf
{
	/**
	 * Get a CSRF token for an optional $purpose, which can be validated
	 *
	 * @param mixed $_purpose=true if given it need to be used in validate too! (It must NOT be NULL)
	 * @return string CSRF token
	 */
	public static function token($_purpose=true)
	{
		if (is_null($_purpose))
		{
			throw new egw_exception_wrong_parameter(__METHOD__.'(NULL) $_purspose must NOT be NULL!');
		}
		// generate random token (using oppenssl if available otherwise mt_rand based auth::randomstring)
		$token = function_exists('openssl_random_pseudo_bytes') ?
			base64_encode(openssl_random_pseudo_bytes(64)) :
			auth::randomstring(64);

		// store it in session for later validation
		egw_cache::setSession(__CLASS__, $token, $_purpose);

		return $token;
	}

	/**
	 * Validate a CSRF token or teminate the request
	 *
	 * @param string $_token CSRF token generated with egw_csfr::token()
	 * @param string $_purpose=true optional purpose string passed to token method
	 * @param boolean $_delete_token=true true if token should be deleted after validation, it will validate no second time
	 */
	public static function validate($_token, $_purpose=true, $_delete_token=true)
	{
		$stored_purpose = egw_cache::getSession(__CLASS__, $_token);

		// if token and purpose dont validate, log and terminate request
		if (!isset($stored_purpose) || $stored_purpose !== $_purpose)
		{
			error_log('CSRF detected from IP '.$_SERVER['REMOTE_ADDR'].' to '.$_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']);
			if ($_POST) error_log(array2string($_POST));
			// we are not throwing an exception here, but die, to not allow catching it!
			die("CSRF detected, request terminated!");
		}
		if ($_delete_token) egw_cache::unsetTree (__CLASS__, $_token);
	}
}