<?php
/**
 * EGroupware API: Contacts ADS Backend
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package api
 * @subpackage contacts
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Contacts;

use EGroupware\Api;

/**
 * Active directory backend for accounts (not yet AD contacts)
 *
 * We use ADS string representation of objectGUID as contact ID and UID.
 *
 * Unfortunatly Samba4 and active directory of win2008r2 differn on how to search for an objectGUID:
 * - Samba4 can only search for string representation eg. (objectGUID=2336A3FC-EDBD-42A2-9EEB-BD7A5DD2804E)
 * - win2008r2 can only search for hex representation eg. (objectGUID=\FC\A3\36\23\BD\ED\A2\42\9E\EB\BD\7A\5D\D2\80\4E)
 * We could use both filters or-ed together, for now we detect Samba4 and use string GUID for it.
 *
 * All values used to construct filters need to run through ldap::quote(),
 * to be save against LDAP query injection!!!
 */
class Ads extends Ldap
{
	/**
	 * LDAP searches only a limited set of attributes for performance reasons,
	 * you NEED an index for that columns, ToDo: make it configurable
	 * minimum: $this->columns_to_search = array('n_family','n_given','org_name','email');
	 */
	var $search_attributes = array(
		'n_family','n_middle','n_given','org_name','org_unit',
		'adr_one_location','note','email','samaccountname',
	);

	/**
	 * Filter used for accounts addressbook
	 * @var string
	 */
	var $accountsFilter = '(objectCategory=person)';

	/**
	 * Attribute used for DN
	 *
	 * @var string
	 */
	var $dn_attribute='cn';

	/**
	 * Do NOT attempt to change DN (dn-attribute can NOT be part of schemas used in addressbook!)
	 *
	 * Set here to true, as accounts can be stored in different containers and CN is not used as n_fn (displayName is)
	 *
	 * @var boolean
	 */
	var $never_change_dn = true;

	/**
	 * Accounts ADS object
	 *
	 * @var Api\Accounts\Ads
	 */
	protected $accounts_ads;

	/**
	 * ADS is Samba4 (true), otherwise false
	 *
	 * @var boolean
	 */
	public $is_samba4 = false;


	/**
	 * constructor of the class
	 *
	 * @param ?array $ldap_config =null default use from $GLOBALS['egw_info']['server']
	 * @param ?resource $ds =null ldap connection to use
	 */
	function __construct(array $ldap_config=null, $ds=null)
	{
		if (false) parent::__construct ();	// quiten IDE warning, we are explicitly NOT calling parent constructor!

		$this->accountName 		= $GLOBALS['egw_info']['user']['account_lid'];

		if ($ldap_config)
		{
			$this->ldap_config = $ldap_config;
		}
		else
		{
			$this->ldap_config =& $GLOBALS['egw_info']['server'];
		}

		$this->accounts_ads = $GLOBALS['egw']->accounts->backend;
		if (!is_a($this->accounts_ads, Api\Accounts\Ads::class))
		{
			throw new Api\Exception\AssertionFailed('$GLOBALS[egw]->accounts->backend is no Api\Accounts\Ads object!');
		}
		//$this->personalContactsDN	= 'ou=personal,ou=contacts,'. $this->ldap_config['ldap_contact_context'];
		//$this->sharedContactsDN		= 'ou=shared,ou=contacts,'. $this->ldap_config['ldap_contact_context'];
		$this->allContactsDN = $this->accountContactsDN = $this->accounts_ads->ads_context();

		// get filter for accounts (incl. additional filter from setup)
		$this->accountsFilter = $this->accounts_ads->type_filter('u', true);
		$this->contactsFilter = "(|(objectclass=contact)$this->accountsFilter)";

		if ($ds)
		{
			$this->ds = $ds;
		}
		else
		{
			$this->connect();
		}
		$this->ldapServerInfo = Api\Ldap\ServerInfo::get($this->ds, $this->ldap_config['ads_host']);
		$this->is_samba4 = $this->ldapServerInfo->serverType == Api\Ldap\ServerInfo::SAMBA4;

		// check if there are any attributes defined via custom-fields
		foreach(Api\Storage\Customfields::get('addressbook') as $cf)
		{
			if (substr($cf['name'], 0, 5) === 'ldap_')
			{
				$this->schema2egw[self::CF_OBJECTCLASS]['#'.$cf['name']] = strtolower(substr($cf['name'], 5));
			}
		}

		// AD seems to use user, instead of inetOrgPerson
		unset($this->schema2egw['posixaccount']);
		$this->schema2egw['user'] = array_merge($this->schema2egw['organizantionalperson'], array(
			'account_id'	=> 'objectsid',
			'accountexpires', 'useraccountcontrol',	// needed to exclude deactivated or expired accounts
		));
		unset($this->schema2egw['user']['n_fileas']);
		unset($this->schema2egw['inetorgperson']);

		foreach($this->schema2egw as $attributes)
		{
			$this->all_attributes = array_merge($this->all_attributes,array_values($attributes));
		}
		$this->all_attributes = array_values(array_unique($this->all_attributes));

		$this->charset = Api\Translation::charset();
	}

	/**
	 * connect to LDAP server
	 *
	 * @param boolean $admin =false true (re-)connect with admin not user credentials, eg. to modify accounts
	 */
	function connect($admin=false)
	{
		unset($admin);	// not used, but required by function signature

		$this->ds = $this->accounts_ads->ldap_connection();
	}

	/**
	 * Return LDAP filter for contact id
	 *
	 * @param string $contact_id
	 * @throws Api\Exception\AssertionFailed if $contact_id is no valid GUID
	 * @return string
	 */
	protected function id_filter($contact_id)
	{
		// check that GUID eg. from URL contains only valid hex characters and dash
		// we cant use ldap::quote() for win2008r2 hex GUID, as it contains backslashes
		if (strlen($contact_id) !== 36 || !preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i', $contact_id))
		{
			throw new Api\Exception\AssertionFailed("'$contact_id' is NOT a valid GUID!");
		}

		// samba4 can only search by string representation of objectGUID, while win2008r2 requires hex representation
		return '(objectguid='.($this->is_samba4 ? $contact_id : $this->accounts_ads->objectguid2hex($contact_id)).')';
	}

	/**
	 * Return LDAP filter for (multiple) account ids
	 *
	 * @param int|int[]|null $ids
	 * @return string
	 */
	protected function account_ids_filter($ids)
	{
		$filter = '';
		if (is_null($ids))
		{
			$filter = '(!(objectsid=*))';
		}
		elseif ($ids)
		{
			if (is_array($ids)) $filter = '(|';
			foreach((array)$ids as $account_id)
			{
				$filter .= '(objectsid='.$this->accounts_ads->get_sid($account_id).')';
			}
			if (is_array($ids)) $filter .= ')';
		}
		return $filter;
	}

	/**
	 * Reads contact data
	 *
	 * @param string|array $_contact_id contact_id or array with values for id or account_id
	 * @return array|boolean data if row could be retrived else False
	*/
	function read($_contact_id)
	{
		if (is_array($_contact_id) && isset($_contact_id['account_id']) ||
			!is_array($_contact_id) && substr($_contact_id,0,8) == 'account:')
		{
			$account_id = (int)(is_array($_contact_id) ? $_contact_id['account_id'] : substr($_contact_id,8));
			if ($account_id < 0 || !($_contact_id = $GLOBALS['egw']->accounts->id2name($account_id, 'person_id')))
			{
				return false;
			}
		}
		$contact_id = !is_array($_contact_id) ? $_contact_id :
			(isset ($_contact_id['id']) ? $_contact_id['id'] : $_contact_id['uid']);

		try {
			$rows = $this->_searchLDAP($this->allContactsDN, $filter = $this->id_filter($contact_id), $this->all_attributes, Ldap::ALL);
		}
		catch (Api\Exception\AssertionFailed $e) {
			$rows = null;
		}
		//error_log(__METHOD__."('$contact_id') _searchLDAP($this->allContactsDN, '$filter',...)=".array2string($rows));
		return $rows ? $rows[0] : false;
	}

	/**
	 * Special handling for mapping data of ADS user objectclass to eGW contact
	 *
	 * Please note: all regular fields are already copied!
	 *
	 * @internal
	 * @param array &$contact already copied fields according to the mapping
	 * @param array $data eGW contact data
	 */
	function _user2egw(&$contact, $data)
	{
		$contact['account_id'] = $this->accounts_ads->objectsid2account_id($data['objectsid']);
		$contact['id'] = $contact['uid'] = $this->accounts_ads->objectguid2str($data['objectguid']);

		$this->_inetorgperson2egw($contact, $data, 'displayname');
	}

	/**
	 * Remove attributes we are not allowed to update
	 *
	 * @param array $attributes
	 */
	function sanitize_update(array &$ldapContact)
	{
		// not allowed and not need to update these in AD
		unset($ldapContact['objectguid']);
		unset($ldapContact['objectsid']);

		parent::sanitize_update($ldapContact);
	}

	/**
	 * Return a LDAP filter by group membership
	 *
	 * @param int $gid gidNumber (< 0 as used in EGroupware!)
	 * @return string filter or '' if $gid not < 0
	 */
	function membershipFilter($gid)
	{
		$filter = '';
		if ($gid < 0 && ($dn = $GLOBALS['egw']->accounts->id2name($gid, 'account_dn')))
		{
			$filter .= '(|(memberOf='.$dn.')(primaryGroupID='.abs($gid).'))';
		}
		return $filter;
	}
}