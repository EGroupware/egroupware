<?php
/**
 * Addressbook - ADS Backend
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Active directory backend for accounts (not yet AD contacts)
 *
 * We use ADS objectGUID as contact ID and UID.
 *
 * All values used to construct filters need to run through ldap::quote(),
 * to be save against LDAP query injection!!!
 *
 * @todo get saving of contacts working: fails while checking of container exists ...
 */
class addressbook_ads extends addressbook_ldap
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
	var $accountsFilter = '(objectclass=user)';

	/**
	 * Accounts ADS object
	 *
	 * @var accounts_ads
	 */
	protected $accounts_ads;

	/**
	 * constructor of the class
	 *
	 * @param array $ldap_config=null default use from $GLOBALS['egw_info']['server']
	 * @param resource $ds=null ldap connection to use
	 */
	function __construct(array $ldap_config=null, $ds=null)
	{
		//$this->db_data_cols 	= $this->stock_contact_fields + $this->non_contact_fields;
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
		//$this->personalContactsDN	= 'ou=personal,ou=contacts,'. $this->ldap_config['ldap_contact_context'];
		//$this->sharedContactsDN		= 'ou=shared,ou=contacts,'. $this->ldap_config['ldap_contact_context'];
		$this->allContactsDN = $this->accountContactsDN = $this->accounts_ads->ads_context();

		if ($ds)
		{
			$this->ds = $ds;
		}
		else
		{
			$this->connect();
		}
		$this->ldapServerInfo = ldapserverinfo::get($this->ds, $this->ldap_config['ads_host']);

		// AD seems to use user, instead of inetOrgPerson
		$this->schema2egw['user'] = $this->schema2egw['inetorgperson'];
		$this->schema2egw['user'] += array(
			'account_id'	=> 'objectsid',
			'account_lid'	=> 'samaccountname',
			'contact_uid'   => 'objectguid',
		);

		foreach($this->schema2egw as $schema => $attributes)
		{
			$this->all_attributes = array_merge($this->all_attributes,array_values($attributes));
		}
		$this->all_attributes = array_values(array_unique($this->all_attributes));

		$this->charset = translation::charset();
	}

	/**
	 * connect to LDAP server
	 *
	 * @param boolean $admin=false true (re-)connect with admin not user credentials, eg. to modify accounts
	 */
	function connect($admin=false)
	{
		$this->ds = $this->accounts_ads->ldap_connection();
	}

	/**
	 * reads contact data
	 *
	 * @param string/array $contact_id contact_id or array with values for id or account_id
	 * @return array/boolean data if row could be retrived else False
	*/
	function read($contact_id)
	{
		if (is_array($contact_id) && isset($contact_id['account_id']) ||
			!is_array($contact_id) && substr($contact_id,0,8) == 'account:')
		{
			$account_id = (int)(is_array($contact_id) ? $contact_id['account_id'] : substr($contact_id,8));
			$contact_id = $GLOBALS['egw']->accounts->id2name($account_id, 'person_id');
		}
		$contact_id = ldap::quote(!is_array($contact_id) ? $contact_id :
			(isset ($contact_id['id']) ? $contact_id['id'] : $contact_id['uid']));

		$rows = $this->_searchLDAP($this->allContactsDN, "(objectguid=$contact_id)", $this->all_attributes, ADDRESSBOOK_ALL);

		return $rows ? $rows[0] : false;
	}

	/**
	 * Special handling for mapping data of ADA user objectclass to eGW contact
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
		$contact['id'] = $contact['contact_uid'] = $this->accounts_ads->objectguid2str($data['objectguid']);

		// ignore system accounts
		if ($contact['account_id'] < accounts_ads::MIN_ACCOUNT_ID) return false;

		$this->_inetorgperson2egw($contact, $data);
	}
}
