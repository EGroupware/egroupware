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
	 * @param ?string $address
	 * @param array|null &$lavatar on return values for keys "lname" and "fname"
	 * @return string|null
	 * @ToDo add a domain based image as fallback eg. as fallback from their website
	 */
	static function getAvatar(?string $address=null, ?array &$lavatar=null)
	{
		if (empty($address))
		{
			return $lavatar = null;
		}
		$email = strtolower(current(AddressList::stripRFC822Addresses([$address])));

		// we cache on instance level, but also add current user to the key, as not all users might see the same contacts
		$cached = Api\Cache::getInstance(__CLASS__, $GLOBALS['egw_info']['user']['account_id'].'-'.$email,
			static function($email, $address)
			{
				if (($contacts = $GLOBALS['egw']->contacts->search(['contact_email' => $email, 'contact_email_home' => $email],
					['contact_id', 'email', 'email_home', 'n_fn', 'n_given', 'n_family'], '', '', '', false, 'OR', [0,1])))
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
	 * @param ?string $address
	 * @return string[] with values for keys "fname" and (optional) "lname"
	 * - "Ralf Becker <rb@egroupware.org>" --> ["fname" => "Ralf", "lname" => "Becker"]
	 * - "'Becker, Ralf' <rb@egroupware.org> --> dito
	 * - "ralf.becker@egroupware.org" --> dito
	 * - "rb@egroupware.org" --> ["fname" --> "r", "lname" => "b"]
	 * - "📦 DHL Paketankündigung <noreply@dhl.de>" --> ["fname" => "DHL", "lname" => "Paketankündigung"], starts with package emoji - emoji are ignored
	 */
	static function getLavatar(?string $address=null) : array
	{
		if (empty($address))
		{
			return [];
		}
		if (preg_match("/^\"?'?(.*?)'?\"?\s+<([^<>'\"]+)>$/", $address, $matches))
		{
			// emoji can not be used as initials, ignore them (a name consisting only of emoji falls back to the email)
			if (($parts = preg_split('/[, ]+/', self::stripEmoji($matches[1]), -1, PREG_SPLIT_NO_EMPTY)))
			{
				// if we have a usual title prefixing the name, skip it
				while (count($parts) > 1 && preg_match('/^(Hr\.|Herr|Mr.|Mister|Fr\.|Frau|Ms.|Miss|Dr\.|Doktor|Prof.|Professor)/', $parts[0]))
				{
					array_shift($parts);
				}
				return ['fname' => array_shift($parts), 'lname' => array_shift($parts), 'label' => $matches[1]];
			}
			$address = $matches[2];
		}
		if (($parts = preg_split('/[._]/', $address)) && count($parts) >= 2)
		{
			return ['fname' => array_shift($parts), 'lname' => array_pop($parts), 'label' => $address];
		}
		return ['fname' => $address[0], 'lname' => $address[1], 'label' => $address];
	}

	/**
	 * Remove emoji from a name, as they can not be used as initials
	 *
	 * Removes pictographs incl. their modifiers (variation-selectors, skin-tones, zero-width-joiners), flags and keycaps.
	 *
	 * @param string $name
	 * @return string name without emoji, surrounding whitespace is trimmed
	 */
	static function stripEmoji(string $name) : string
	{
		return trim(preg_replace('/[0-9#*]\x{FE0F}?\x{20E3}|[\p{Extended_Pictographic}\x{FE0F}\x{200D}\x{20E3}\x{1F3FB}-\x{1F3FF}\x{1F1E6}-\x{1F1FF}\x{E0020}-\x{E007F}]+/u', '', $name) ?? $name);
	}
}