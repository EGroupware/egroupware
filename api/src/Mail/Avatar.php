<?php
/**
 * EGroupware Api: Mail avatars
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@egroupware.org>
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api;

/**
 * Class for managing avatars for email addresses including proper caching
 */
class Avatar
{
	const CACHE_TIME = 86400;

	/**
	 * Get (cached) avatar URL for given email
	 *
	 * @param string $email
	 * @param array|null &$lavatar on return values for keys "lname" and "fname"
	 * @return string|null
	 * @ToDo add a domain based image as fallback eg. as fallback from their website
	 */
	static function getAvatar(string $address, array &$lavatar=null)
	{
		if (empty($address))
		{
			return $lavatar = null;
		}
		$email = strtolower(current(Api\Mail::stripRFC822Addresses([$address])));

		// we cache on instance level, but also add current user to the key, as not all users might see the same contacts
		$cached = Api\Cache::getInstance(__CLASS__, $GLOBALS['egw_info']['user']['account_id'].'-'.$email,
			static function($email, $address)
			{
				if (($contacts = $GLOBALS['egw']->contacts->search(['contact_email' => $email, 'contact_email_home' => $email],
					['contact_id', 'email', 'email_home', 'n_fn', 'n_given', 'n_family'], '', '', '', false, 'OR', false)))
				{
					return [$contacts[0]['photo'], ['lname' => $contacts[0]['n_family'], 'fname' => $contacts[0]['n_given']]];
				}
				return [1 => self::getLavatar($address)];
			}, [$email, $address], self::CACHE_TIME);

		$lavatar = $cached[1] ?? null;

		return $cached[0] ?? null;
	}

	/**
	 * Get (L)etter avatar from given email address
	 *
	 * @param string $address
	 * @return string[] with values for keys "fname" and (optional) "lname"
	 * - "Ralf Becker <rb@egroupware.org>" --> ["fname" => "Ralf", "lname" => "Becker"]
	 * - "'Becker, Ralf' <rb@egroupware.org> --> dito
	 * - "ralf.becker@egroupware.org" --> dito
	 * - "rb@egroupware.org" --> ["fname" --> "r", "lname" => "b"]
	 */
	static function getLavatar(string $address=null) : array
	{
		if (empty($address))
		{
			return [];
		}
		if (preg_match("/^\"?'?(.*)'?\"?\s+<([^<>'\"]+)>$/", $address, $matches))
		{
			if (($parts = preg_split('/[, ]+/', $matches[1])))
			{
				return ['fname' => array_shift($parts), 'lname' => array_pop($parts)];
			}
			$address = $matches[2];
		}
		if (($parts = preg_split('/[._]/', $address)) && count($parts) >= 2)
		{
			return ['fname' => array_shift($parts), 'lname' => array_pop($parts)];
		}
		return ['fname' => $address[0], 'lname' => $address[1]];
	}
}