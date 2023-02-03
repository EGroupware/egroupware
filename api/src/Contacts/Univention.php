<?php
/**
 * EGroupware API: Contacts Univention Backend
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package api
 * @subpackage contacts
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Contacts;

use EGroupware\Api\Accounts;

/**
 * Univention backend for addressbook
 *
 * Different mail attribute is only difference to LDAP backend
 */
class Univention extends Ldap
{
	function __construct($ldap_config = null, $ds = null)
	{
		$this->schema2egw += [
			'univentionmail' => [
				'email' => Accounts\Univention::MAIL_ATTR,
			],
			'univentionperson' => [
				'bday' => 'univentionbirthday',
			],
		];
		parent::__construct($ldap_config, $ds);
	}

	/**
	 * saves the content of data to the db
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null)
	{
		// UCS lowercases email when storing
		$keys['email'] = strtolower(!empty($keys['email']) ? $keys['email'] : $this->data['email']);

		return parent::save($keys);
	}
}