<?php
/**
 * EGroupware API: Contacts LDAP Backend
 *
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw-AT-von-und-zu-weiss.de>
 * @author Lars Kneschke <l.kneschke-AT-metaways.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @subpackage contacts
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Contacts;

use EGroupware\Api;
use EGroupware\Api\Ldap\ServerInfo;

/**
 * LDAP Backend for contacts, compatible with vars and parameters of eTemplate's so_sql.
 * Maybe one day this becomes a generalized ldap storage object :-)
 *
 * All values used to construct filters need to run through Api\Ldap::quote(),
 * to be save against LDAP query injection!!!
 */
class Ldap
{
	const ALL = 0;
	const ACCOUNTS = 1;
	const PERSONAL = 2;
	const GROUP = 3;

	/**
	 * Pseudo objectclass used for LDAP attributes made available to use as custom fields
	 */
	const CF_OBJECTCLASS = 'egwcustomfields';

	var $data;

	/**
	 * internal name of the id, gets mapped to uid
	 *
	 * @var string
	 */
	var $contacts_id='id';

	/**
	* @var string $accountName holds the accountname of the current user
	*/
	var $accountName;

	/**
	* @var ServerInfo $ldapServerInfo holds the information about the current used ldap server
	*/
	var $ldapServerInfo;

	/**
	* @var int $ldapLimit how many rows to fetch from ldap server
	*/
	var $ldapLimit = 2000;

	/**
	* @var string $personalContactsDN holds the base DN for the personal addressbooks
	*/
	var $personalContactsDN;

	/**
	* @var string $sharedContactsDN holds the base DN for the shared addressbooks
	*/
	var $sharedContactsDN;

	/**
	* @var string $accountContactsDN holds the base DN for accounts addressbook
	*/
	var $accountContactsDN;

	/**
	 * Filter used for accounts addressbook
	 * @var string
	 */
	var $accountsFilter = '(objectclass=posixaccount)';

	/**
	 * Filter used for all addressbooks
	 * @var string
	 */
	var $contactsFilter = '(objectclass=inetorgperson)';

	/**
	* @var string $allContactsDN holds the base DN of all addressbook
	*/
	var $allContactsDN;

	/**
	 * Attribute used for DN
	 *
	 * @var string
	 */
	var $dn_attribute='uid';

	/**
	 * Do NOT attempt to change DN (dn-attribute can NOT be part of schemas used in addressbook!)
	 *
	 * @var boolean
	 */
	var $never_change_dn = false;

	/**
	* @var int $total holds the total count of found rows
	*/
	var $total;

	/**
	 * Charset used by eGW
	 *
	 * @var string
	 */
	var $charset;

	/**
	 * LDAP searches only a limited set of attributes for performance reasons,
	 * you NEED an index for that columns, ToDo: make it configurable
	 * minimum: $this->columns_to_search = array('n_family','n_given','org_name','email');
	 */
	var $search_attributes = array(
		'n_family','n_middle','n_given','org_name','org_unit',
		'adr_one_locality','adr_two_locality','note',
		'email','mozillasecondemail','uidnumber',
	);

	/**
	 * maps between diverse ldap schema and the eGW internal names
	 *
	 * The ldap attribute names have to be lowercase!!!
	 *
	 * @var array
	 */
	var $schema2egw = array(
		'posixaccount' => array(
			'account_id'	=> 'uidnumber',
			'shadowexpire',
		),
		'inetorgperson' => array(
			'n_fn'			=> 'cn',
			'n_given'		=> 'givenname',
			'n_family'		=> 'sn',
			'sound'			=> 'audio',
			'note'			=> 'description',
			'url'			=> 'labeleduri',
			'org_name'		=> 'o',
			'org_unit'		=> 'ou',
			'title'			=> 'title',
			'adr_one_street'		=> 'street',
			'adr_one_locality'		=> 'l',
			'adr_one_region'		=> 'st',
			'adr_one_postalcode'	=> 'postalcode',
			'tel_work'		=> 'telephonenumber',
			'tel_home'		=> 'homephone',
			'tel_fax'		=> 'facsimiletelephonenumber',
			'tel_cell'		=> 'mobile',
			'tel_pager'		=> 'pager',
			'email'			=> 'mail',
			'room'			=> 'roomnumber',
			'jpegphoto'		=> 'jpegphoto',
			'n_fileas'		=> 'displayname',
			'label'			=> 'postaladdress',
			'pubkey'		=> 'usersmimecertificate',
			'uid'			=> 'entryuuid',
			'id'			=> 'uid',
		),
		'organizantionalperson' => [	// ActiveDirectory
			'n_fn'			=> 'displayname',	// to leave CN as part of DN untouched
			'n_given'		=> 'givenname',
			'n_family'		=> 'sn',
			//'sound'			=> 'audio',
			'note'			=> 'description',
			'url'			=> 'url',
			'org_name'		=> 'company',
			'org_unit'		=> 'department',
			'title'			=> 'title',
			'adr_one_street'		=> 'streetaddress',
			'adr_one_locality'		=> 'l',
			'adr_one_region'		=> 'st',
			'adr_one_postalcode'	=> 'postalcode',
			'adr_one_countryname'	=> 'co',
			'adr_one_countrycode'	=> 'c',
			'tel_work'		=> 'telephonenumber',
			'tel_home'		=> 'homephone',
			'tel_fax'		=> 'facsimiletelephonenumber',
			'tel_cell'		=> 'mobile',
			'tel_pager'		=> 'pager',
			'tel_other'		=> 'othertelephone',
			'tel_cell_private' => 'othermobile',
			'assistent'		=> 'assistant',
			'email'			=> 'mail',
			'room'			=> 'roomnumber',
			'jpegphoto'		=> 'jpegphoto',
			'n_fileas'		=> 'displayname',
			'label'			=> 'postaladdress',
			'pubkey'		=> 'usersmimecertificate',
			'uid'			=> 'objectguid',
			'id'			=> 'objectguid',
		],
		#displayName
		#mozillaCustom1
		#mozillaCustom2
		#mozillaCustom3
		#mozillaCustom4
		#mozillaHomeUrl
		#mozillaNickname
		#mozillaUseHtmlMail
		#nsAIMid
		#postOfficeBox
		'mozillaabpersonalpha' => array(
			'adr_one_street2'	=> 'mozillaworkstreet2',
			'adr_one_countryname'	=> 'c',	// 2 letter country code
			'adr_one_countrycode'	=> 'c',	// 2 letter country code
			'adr_two_street'	=> 'mozillahomestreet',
			'adr_two_street2'	=> 'mozillahomestreet2',
			'adr_two_locality'	=> 'mozillahomelocalityname',
			'adr_two_region'	=> 'mozillahomestate',
			'adr_two_postalcode'	=> 'mozillahomepostalcode',
			'adr_two_countryname'	=> 'mozillahomecountryname',
			'adr_two_countrycode'	=> 'mozillahomecountryname',
			'email_home'		=> 'mozillasecondemail',
			'url_home'			=> 'mozillahomeurl',
		),
		// similar to the newer mozillaAbPerson, but uses mozillaPostalAddress2 instead of mozillaStreet2
		'mozillaorgperson' => array(
			'adr_one_street2'	=> 'mozillapostaladdress2',
			'adr_one_countrycode'	=> 'c',	// 2 letter country code
			'adr_one_countryname'	=> 'co',	// human readable country name, must be after 'c' to take precedence on read!
			'adr_two_street'	=> 'mozillahomestreet',
			'adr_two_street2'	=> 'mozillahomepostaladdress2',
			'adr_two_locality'	=> 'mozillahomelocalityname',
			'adr_two_region'	=> 'mozillahomestate',
			'adr_two_postalcode'	=> 'mozillahomepostalcode',
			'adr_two_countryname'	=> 'mozillahomecountryname',
			'email_home'		=> 'mozillasecondemail',
			'url_home'			=> 'mozillahomeurl',
		),
		# managerName
		# otherPostalAddress
		# mailer
		# anniversary
		# spouseName
		# companyPhone
		# otherFacsimileTelephoneNumber
		# radio
		# telex
		# tty
		# categories(deprecated)
		'evolutionperson' => array(
			'bday'			=> 'birthdate',
			'note'			=> 'note',
			'tel_car'		=> 'carphone',
			'tel_prefer'	=> 'primaryphone',
			'cat_id'		=> 'category',	// special handling in _egw2evolutionperson method
			'role'			=> 'businessrole',
			'tel_assistent'	=> 'assistantphone',
			'assistent'		=> 'assistantname',
			'n_fileas'		=> 'fileas',
			'tel_fax_home'	=> 'homefacsimiletelephonenumber',
			'freebusy_uri'	=> 'freeBusyuri',
			'calendar_uri'	=> 'calendaruri',
			'tel_other'		=> 'otherphone',
			'tel_cell_private' => 'callbackphone',	// not the best choice, but better then nothing
		),
		// additional schema can be added here, including special functions

		/**
		 * still unsupported fields in LDAP:
		 * --------------------------------
		 * tz
		 * geo
		 */
	);

	/**
	 * Timestamps ldap => egw used in several places
	 * @var string[]
	 */
	public $timestamps2egw = [
		'createtimestamp' => 'created',
		'modifytimestamp' => 'modified',
	];

	/**
	 * additional schema required by one of the above schema
	 *
	 * @var array
	 */
	var $required_subs = array(
		'inetorgperson' => array('person'),
	);

	/**
	 * array with the names of all ldap attributes of the above schema2egw array
	 *
	 * @var array
	 */
	var  $all_attributes = array();

	/**
	 * LDAP configuration
	 *
	 * @var array values for keys "ldap_contact_context", "ldap_host", "ldap_context"
	 */
	protected $ldap_config;

	/**
	 * LDAP connection
	 *
	 * @var resource
	 */
	var $ds;

	/**
	 * constructor of the class
	 *
	 * @param array $ldap_config =null default use from $GLOBALS['egw_info']['server']
	 * @param resource $ds =null ldap connection to use
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
		$this->accountContactsDN	= $this->ldap_config['ldap_context'];
		$this->allContactsDN		= $this->ldap_config['ldap_contact_context'];
		$this->personalContactsDN	= 'ou=personal,ou=contacts,'. $this->allContactsDN;
		$this->sharedContactsDN		= 'ou=shared,ou=contacts,'. $this->allContactsDN;

		if ($ds)
		{
			$this->ds = $ds;
		}
		else
		{
			$this->connect();
		}
		$this->ldapServerInfo = $GLOBALS['egw']->ldap->getLDAPServerInfo($this->ldap_config['ldap_contact_host']);

		// check if there are any attributes defined via custom-fields
		foreach(Api\Storage\Customfields::get('addressbook') as $cf)
		{
			if (substr($cf['name'], 0, 5) === 'ldap_')
			{
				$this->schema2egw[self::CF_OBJECTCLASS]['#'.$cf['name']] = strtolower(substr($cf['name'], 5));
			}
		}

		foreach($this->schema2egw as $attributes)
		{
			$this->all_attributes = array_merge($this->all_attributes,array_values($attributes));
		}
		$this->all_attributes = array_values(array_unique($this->all_attributes));

		$this->charset = Api\Translation::charset();

		// add ldap_search_filter from admin
		$accounts_filter = str_replace(['%user','%domain'], ['*', $GLOBALS['egw_info']['user']['domain']],
			$this->ldap_config['ldap_search_filter'] ?: '(uid=%user)');
		$this->accountsFilter = "(&$this->accountsFilter$accounts_filter)";
	}

	/**
	 * Magic method called when object gets serialized
	 *
	 * We do NOT store ldapConnection, as we need to reconnect anyway.
	 * PHP 8.1 gives an error when trying to serialize LDAP\Connection object!
	 *
	 * @return array
	 */
	function __sleep()
	{
		$vars = get_object_vars($this);
		unset($vars['ds']);
		return array_keys($vars);
	}

	/**
	 * __wakeup function gets called by php while unserializing the object to reconnect with the ldap server
	 */
	function __wakeup()
	{
		$this->connect();
	}

	/**
	 * connect to LDAP server
	 *
	 * @param boolean $admin =false true (re-)connect with admin not user credentials, eg. to modify accounts
	 */
	function connect($admin = false)
	{
		if ($admin)
		{
			$this->ds = Api\Ldap::factory();
		}
		// if ldap is NOT the contact repository, we only do accounts and need to use the account-data
		elseif (substr($GLOBALS['egw_info']['server']['contact_repository'],-4) != 'ldap')	// not (ldap or sql-ldap)
		{
			$this->ldap_config['ldap_contact_host'] = $this->ldap_config['ldap_host'];
			$this->allContactsDN = $this->ldap_config['ldap_context'];
			$this->ds = Api\Ldap::factory();
		}
		else
		{
			$this->ds = Api\Ldap::factory(true,
				$this->ldap_config['ldap_contact_host'],
				$GLOBALS['egw_info']['user']['account_dn'],
				$GLOBALS['egw_info']['user']['passwd']
			);
		}
	}

	/**
	 * Returns the supported fields of this LDAP server (based on the objectclasses it supports)
	 *
	 * @return array with eGW contact field names
	 */
	function supported_fields()
	{
		$fields = array(
			'id','tid','owner',
			'n_middle','n_prefix','n_suffix',	// stored in the cn
			'created','modified',				// automatic timestamps
			'creator','modifier',				// automatic for non accounts
			'private',							// true for personal addressbooks, false otherwise
		);
		foreach($this->schema2egw as $objectclass => $mapping)
		{
			if($this->ldapServerInfo->supportsObjectClass($objectclass))
			{
				$fields = array_merge($fields,array_keys($mapping));
			}
		}
		return array_values(array_unique($fields));
	}

	/**
	 * Return LDAP filter for contact id
	 *
	 * @param string $id
	 * @return string
	 */
	protected function id_filter($id)
	{
		return '(|(entryUUID='.Api\Ldap::quote($id).')(uid='.Api\Ldap::quote($id).'))';
	}

	/**
	 * Return LDAP filter for (multiple) contact ids
	 *
	 * @param array|string $ids
	 * @throws Api\Exception\AssertionFailed if $contact_id is no valid GUID (for ADS!)
	 * @return string
	 */
	protected function ids_filter($ids)
	{
		if (!is_array($ids) || count($ids) == 1)
		{
			return $this->id_filter(is_array($ids) ? array_shift($ids) : $ids);
		}
		$filter = array();
		foreach($ids as $id)
		{
			$filter[] = $this->id_filter($id);
		}
		return '(|'.implode('', $filter).')';
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
			$filter = '(!(uidNumber=*))';
		}
		elseif ($ids)
		{
			$filter = $this->ids_filter(array_map(static function($account_id)
			{
				return $GLOBALS['egw']->accounts->id2name($account_id, 'person_id');
			}, (array)$ids));
		}
		return $filter;
	}

	/**
	 * reads contact data
	 *
	 * @param string|array $contact_id contact_id or array with values for id or account_id
	 * @return array|false data if row could be retrived else False
	*/
	function read($contact_id)
	{
		if (is_array($contact_id) && isset($contact_id['account_id']) ||
			!is_array($contact_id) && substr($contact_id,0,8) == 'account:')
		{
			$filter = 'uidNumber='.(int)(is_array($contact_id) ? $contact_id['account_id'] : substr($contact_id,8));
		}
		else
		{
			if (is_array($contact_id)) $contact_id = isset ($contact_id['id']) ? $contact_id['id'] : $contact_id['uid'];
			$filter = $this->id_filter($contact_id);
		}
		$rows = $this->_searchLDAP($this->allContactsDN,
			$filter, $this->all_attributes, self::ALL, array('_posixaccount2egw'));

		return $rows ? $rows[0] : false;
	}

	/**
	 * Remove attributes we are not allowed to update
	 *
	 * @param array $attributes
	 */
	function sanitize_update(array &$ldapContact)
	{
		// never allow to change the uidNumber (account_id) on update, as it could be misused by eg. xmlrpc or syncml
		unset($ldapContact['uidnumber']);

		unset($ldapContact['entryuuid']);	// not allowed to modify that, no need either

		unset($ldapContact['objectClass']);
	}

	/**
	 * saves the content of data to the db
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @return int 0 on success and errno != 0 else
	 * @noinspection UnsupportedStringOffsetOperationsInspection
	 */
	function save($keys=null)
	{
		//error_log(__METHOD__."(".array2string($keys).") this->data=".array2string($this->data));
		if(is_array($keys))
		{
			$this->data = is_array($this->data) ? array_merge($this->data,$keys) : $keys;
		}

		$data =& $this->data;
		$isUpdate = false;
		$ldapContact = array();

		// generate addressbook dn
		if((int)$data['owner'])
		{
			// group address book
			if(!($cn = strtolower($GLOBALS['egw']->accounts->id2name((int)$data['owner']))))
			{
				error_log('Unknown owner');
				return true;
			}
			$baseDN = 'cn='. $cn .','.($data['owner'] < 0 ? $this->sharedContactsDN : $this->personalContactsDN);
		}
		// only an admin or the user itself is allowed to change the data of an account
		elseif ($data['account_id'] && ($GLOBALS['egw_info']['user']['apps']['admin'] ||
			$data['account_id'] == $GLOBALS['egw_info']['user']['account_id']))
		{
			// account
			$baseDN = $this->accountContactsDN;
			$cn	= false;
			// we need an admin connection
			$this->connect(true);

			// for sql-ldap we need to account_lid/uid as id, NOT the contact_id in id!
			if ($GLOBALS['egw_info']['server']['contact_repository'] == 'sql-ldap')
			{
				$data['id'] = $GLOBALS['egw']->accounts->id2name($data['account_id']);
			}
		}
		else
		{
			error_log("Permission denied, to write: data[owner]=$data[owner], data[account_id]=$data[account_id], account_id=".$GLOBALS['egw_info']['user']['account_id']);
			return lang('Permission denied !!!');	// only admin or the user itself is allowd to write accounts!
		}
		// check if $baseDN exists. If not create it
		if (($err = $this->_check_create_dn($baseDN)))
		{
			return $err;
		}
		// check the existing objectclasses of an entry, none = array() for new ones
		$oldObjectclasses = array();
		$attributes = array('dn','cn','objectClass',$this->dn_attribute,'mail');

		$contactUID	= $this->data[$this->contacts_id];
		if (!empty($contactUID) &&
			($result = ldap_search($this->ds, $base=$this->allContactsDN, $this->id_filter($contactUID), $attributes)) &&
			($oldContactInfo = ldap_get_entries($this->ds, $result)) && $oldContactInfo['count'])
		{
			unset($oldContactInfo[0]['objectclass']['count']);
			foreach($oldContactInfo[0]['objectclass'] as $objectclass)
			{
				$oldObjectclasses[]	= strtolower($objectclass);
			}
		   	$isUpdate = true;
		}

		if(empty($contactUID))
		{
			$ldapContact[$this->dn_attribute] = $this->data[$this->contacts_id] = $contactUID = md5(Api\Auth::randomstring(15));
		}
		//error_log(__METHOD__."() contactUID='$contactUID', isUpdate=".array2string($isUpdate).", oldContactInfo=".array2string($oldContactInfo));
		// add for all supported objectclasses the objectclass and it's attributes
		foreach($this->schema2egw as $objectclass => $mapping)
		{
			if(!$this->ldapServerInfo->supportsObjectClass($objectclass)) continue;

			if($objectclass != 'posixaccount' && !in_array($objectclass, $oldObjectclasses))
			{
				$ldapContact['objectClass'][] = $objectclass;
			}
			if (isset($this->required_subs[$objectclass]))
			{
				foreach($this->required_subs[$objectclass] as $sub)
				{
					if(!in_array($sub, $oldObjectclasses))
					{
						$ldapContact['objectClass'][] = $sub;
					}
				}
			}
			foreach($mapping as $egwFieldName => $ldapFieldName)
			{
				if (is_int($egwFieldName)) continue;
				if(!empty($data[$egwFieldName]))
				{
					// dont convert the (binary) jpegPhoto!
					$ldapContact[$ldapFieldName] = $ldapFieldName == 'jpegphoto' ? $data[$egwFieldName] :
						Api\Translation::convert($data[$egwFieldName], $this->charset,'utf-8');
				}
				elseif($isUpdate && array_key_exists($egwFieldName, $data))
				{
					$ldapContact[$ldapFieldName] = array();
				}
				//error_log(__METHOD__."() ".__LINE__." objectclass=$objectclass, data['$egwFieldName']=".array2string($data[$egwFieldName])." --> ldapContact['$ldapFieldName']=".array2string($ldapContact[$ldapFieldName]));
			}
			// handling of special attributes, like cat_id in evolutionPerson
			$egw2objectclass = '_egw2'.$objectclass;
			if (method_exists($this,$egw2objectclass))
			{
				$this->$egw2objectclass($ldapContact,$data,$isUpdate);
			}
		}
		if ($isUpdate)
		{
			// make sure multiple email-addresses in the mail attribute "survive"
			if (isset($ldapContact['mail']) && $oldContactInfo[0]['mail']['count'] > 1)
			{
				$mail = $oldContactInfo[0]['mail'];
				unset($mail['count']);
				$mail[0] = $ldapContact['mail'];
				$ldapContact['mail'] = array_values(array_unique($mail));
			}
			// update entry
			$dn = $oldContactInfo[0]['dn'];
			$needRecreation = false;

			// add missing objectclasses
			if($ldapContact['objectClass'] && ($missing=array_diff($ldapContact['objectClass'],$oldObjectclasses)))
			{
				if (!@ldap_mod_add($this->ds, $dn, array('objectClass' => $ldapContact['objectClass'])))
				{
					if(in_array(ldap_errno($this->ds),array(69,20)))
					{
						// need to modify structural objectclass
						$needRecreation = true;
						//error_log(__METHOD__."() ".__LINE__." could not add objectclasses ".array2string($missing)." --> need to recreate contact");
					}
					else
					{
						//echo "<p>ldap_mod_add($this->ds,'$dn',array(objectClass =>".print_r($ldapContact['objectClass'],true)."))</p>\n";
						error_log(__METHOD__.'() '.__LINE__.' update of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')');
						return $this->_error(__LINE__);
					}
				}
			}

			// check if we need to rename the DN or need to recreate the contact
			$newRDN = $this->dn_attribute.'='. $ldapContact[$this->dn_attribute];
			$newDN = $newRDN .','. $baseDN;
			if ($needRecreation)
			{
				$result = ldap_read($this->ds, $dn, 'objectclass=*');
				$entries = ldap_get_entries($this->ds, $result);
				$oldContact = Api\Ldap::result2array($entries[0]);
				unset($oldContact['dn']);

				$newContact = $oldContact;
				$newContact[$this->dn_attribute] = $ldapContact[$this->dn_attribute];

				if(is_array($ldapContact['objectClass']) && count($ldapContact['objectClass']) > 0)
				{
					$newContact['objectclass'] = array_unique(array_map('strtolower',	// objectclasses my have different case
						array_merge($newContact['objectclass'], $ldapContact['objectClass'])));
				}

				if(!ldap_delete($this->ds, $dn))
				{
					error_log(__METHOD__.'() '.__LINE__.' delete of old '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')');
					return $this->_error(__LINE__);
				}
				if(!@ldap_add($this->ds, $newDN, $newContact))
				{
					error_log(__METHOD__.'() '.__LINE__.' re-create contact as '. $newDN .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .') newContact='.json_encode($newContact));
					// if adding with new objectclass or dn fails, re-add deleted contact
					@ldap_add($this->ds, $dn, $oldContact);
					return $this->_error(__LINE__);
				}
				$dn = $newDN;
			}
			if ($this->never_change_dn)
			{
				// do NOT change DN, set by addressbook_ads, as accounts can be stored in different containers
			}
			// try renaming entry if content of dn-attribute changed
			elseif (strtolower($dn) != strtolower($newDN) || $ldapContact[$this->dn_attribute] != $oldContactInfo[$this->dn_attribute][0])
			{
				if (@ldap_rename($this->ds, $dn, $newRDN, null, true))
				{
					$dn = $newDN;
				}
				else
				{
					error_log(__METHOD__.'() '.__LINE__." ldap_rename of $dn to $newRDN failed! ".ldap_error($this->ds));
				}
			}
			unset($ldapContact[$this->dn_attribute]);

			$this->sanitize_update($ldapContact);

			if (!@ldap_modify($this->ds, $dn, $ldapContact))
			{
				error_log(__METHOD__.'() '.__LINE__.' update of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .') ldapContact='.json_encode($ldapContact));
				return $this->_error(__LINE__);
			}
		}
		else
		{
			$dn = $this->dn_attribute.'='. $ldapContact[$this->dn_attribute] .','. $baseDN;
			unset($ldapContact['entryuuid']);	// trying to write it, gives an error

			if (!@ldap_add($this->ds, $dn, $ldapContact))
			{
				error_log(__METHOD__.'() '.__LINE__.' add of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .') ldapContact='.json_encode($ldapContact));
				return $this->_error(__LINE__);
			}
		}
		return 0;	// Ok, no error
	}

	/**
	 * deletes row representing keys in internal data or the supplied $keys if != null
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null)
	{
		// single entry
		if($keys[$this->contacts_id]) $keys = array( 0 => $keys);

		if(!is_array($keys))
		{
			$keys = array( $keys);
		}

		$ret = 0;

		$attributes = array('dn');

		foreach($keys as $entry)
		{
			$entry = Api\Ldap::quote(is_array($entry) ? $entry['id'] : $entry);
			if(($result = ldap_search($this->ds, $this->allContactsDN,
				"(|(entryUUID=$entry)(uid=$entry))", $attributes)))
			{
				$contactInfo = ldap_get_entries($this->ds, $result);
				if(@ldap_delete($this->ds, $contactInfo[0]['dn']))
				{
					$ret++;
				}
			}
		}
		return $ret;
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array|string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean|string $only_keys =true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count =false If true an unlimited query is run to determine the total number of rows, default false
	 * @return array|false of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
		//error_log(__METHOD__."(".array2string($criteria).", ".array2string($only_keys).", '$order_by', ".array2string($extra_cols).", '$wildcard', '$empty', '$op', ".array2string($start).", ".array2string($filter).")");
		unset($only_keys, $extra_cols, $empty, $join, $need_full_no_count);	// not used, but required by function signature

		if (is_array($filter['owner']))
		{
			if (count($filter['owner']) == 1)
			{
				$filter['owner'] = array_shift($filter['owner']);
			}
			else
			{
				// multiple addressbooks happens currently only via CardDAV or eSync
				// currently we query all contacts and remove not matching ones (not the most efficient way to do it)
				$owner_filter = $filter['owner'];
				unset($filter['owner']);
			}
		}
		// search filter for modified date (eg. for CardDAV sync-report)
		$datefilter = '';
		foreach($filter as $key => $value)
		{
			$matches = null;
			if (is_int($key) && preg_match('/^(contact_)?(modified|created)([<=>]+)([0-9]+)$/', $value, $matches) &&
				($attr = array_search($matches[2], $this->timestamps2egw)))
			{
				// Microsoft AD can NOT filter by (modify|create)TimeStamp, we have to use when(Created|Changed) attribute
				if (static::class === Ads::class)
				{
					$attr = $attr === 'modifytimestamp' ? 'whenChanged' : 'whenCreated';
				}
				$append = '';
				if ($matches[3] == '>')
				{
					$matches['3'] = '<=';
					$datefilter .= '(!';
					$append = ')';
				}
				$datefilter .= '('.$attr.$matches[3].self::_ts2ldap($matches[4]).')'.$append;
			}
		}

		if((int)$filter['owner'])
		{
			if (!($accountName = $GLOBALS['egw']->accounts->id2name($filter['owner'])))
			{
				$ret = false;
				return $ret;
			}

			$searchDN = 'cn='. Api\Ldap::quote(strtolower($accountName)) .',';

			if ($filter['owner'] < 0)
			{
				$searchDN .= $this->sharedContactsDN;
				$addressbookType = self::GROUP;
			}
			else
			{
				$searchDN .= $this->personalContactsDN;
				$addressbookType = self::PERSONAL;
			}
		}
		elseif (!isset($filter['owner']))
		{
			$searchDN = $this->allContactsDN;
			$addressbookType = self::ALL;
		}
		else
		{
			$searchDN = $this->accountContactsDN;
			$addressbookType = self::ACCOUNTS;
		}
		// create the search filter
		switch($addressbookType)
		{
			case self::ACCOUNTS:
				$objectFilter = $this->accountsFilter;
				break;
			default:
				$objectFilter = $this->contactsFilter;
				break;
		}
		// exclude expired accounts
		//$shadowExpireNow = floor((time()+date('Z'))/86400);
		//$objectFilter .= "(|(!(shadowExpire=*))(shadowExpire>=$shadowExpireNow))";
		// shadowExpire>= does NOT work, as shadow schema only specifies integerMatch and not integerOrderingMatch :-(

		$searchFilter = '';
		if(is_array($criteria) && count($criteria) > 0)
		{
			$wildcard = $wildcard === '%' ? '*' : '';
			$searchFilter = '';
			foreach($criteria as $egwSearchKey => $searchValue)
			{
				if (in_array($egwSearchKey, array('id','contact_id')))
				{
					try {
						$searchFilter .= $this->ids_filter($searchValue);
					}
					// catch and ignore exception caused by id not being a valid GUID
					catch(Api\Exception\AssertionFailed $e) {
						unset($e);
					}
					continue;
				}
				foreach($this->schema2egw as $mapping)
				{
					$matches = null;
					if (preg_match('/^(egw_addressbook\.)?(contact_)?(.*)$/', $egwSearchKey, $matches))
					{
						$egwSearchKey = $matches[3];
					}
					if(($ldapSearchKey = $mapping[$egwSearchKey]))
					{
						foreach((array)$searchValue as $val)
						{
							$searchString = Api\Translation::convert($val, $this->charset,'utf-8');
							$searchFilter .= '('.$ldapSearchKey.'='.$wildcard.Api\Ldap::quote($searchString).$wildcard.')';
						}
						break;
					}
				}
			}
			if($op == 'AND')
			{
				$searchFilter = "(&$searchFilter)";
			}
			else
			{
				$searchFilter = "(|$searchFilter)";
			}
		}
		$colFilter = $this->_colFilter($filter);
		$ldapFilter = "(&$objectFilter$searchFilter$colFilter$datefilter)";
		//error_log(__METHOD__."(".array2string($criteria).", ".array2string($only_keys).", '$order_by', ".array2string($extra_cols).", '$wildcard', '$empty', '$op', ".array2string($start).", ".array2string($filter).") --> ldapFilter='$ldapFilter'");
		if (!($rows = $this->_searchLDAP($searchDN, $ldapFilter, $this->all_attributes, $addressbookType, [], $order_by, $start)))
		{
			return $rows;
		}
		// only return certain owners --> unset not matching ones
		if ($owner_filter)
		{
			foreach($rows as $k => $row)
			{
				if (!in_array($row['owner'],$owner_filter))
				{
					unset($rows[$k]);
					--$this->total;
				}
			}
		}
		if ($order_by)
		{
			$order = array();
			$sort = 'ASC';
			foreach(explode(',',$order_by) as $o)
			{
				if (substr($o,0,8) == 'contact_') $o = substr($o,8);
				if (substr($o,-4) == ' ASC')
				{
					$sort = 'ASC';
					$order[] = substr($o,0,-4);
				}
				elseif (substr($o,-5) == ' DESC')
				{
					$sort = 'DESC';
					$order[] = substr($o,0,-5);
				}
				elseif ($o)
				{
					$order[] = $o;
				}
			}
			usort($rows, function($a, $b) use ($order, $sort)
			{
				foreach($order as $f)
				{
					if($sort == 'ASC')
					{
						$strc = strcmp($a[$f], $b[$f]);
					}
					else
					{
						$strc = strcmp($b[$f], $a[$f]);
					}
					if ($strc) return $strc;
				}
				return 0;
			});
		}
		// if requested ($start !== false) return only limited resultset
		if (is_array($start))
		{
			list($start,$offset) = $start;
		}
		if(is_numeric($start) && is_numeric($offset) && $offset >= 0)
		{
			$rows = array_slice($rows, $start, $offset);
		}
		elseif(is_numeric($start))
		{
			$rows = array_slice($rows, $start, $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']);
		}
		return $rows;
	}

	/**
	 * Process so_sql like filters (at the moment only a subset used by the addressbook UI
	 *
	 * @param array $filter col-name => value pairs or just sql strings
	 * @return string ldap filter
	 */
	function _colFilter($filter)
	{
		if (!is_array($filter)) return '';

		$filters = '';
		foreach($filter as $key => $value)
		{
			if ($key != 'cat_id' && $key != 'account_id' && !$value) continue;

			switch((string) $key)
			{
				case 'owner':	// already handled
				case 'tid':		// ignored
					break;

				case 'account_id':
					$filters .= $this->account_ids_filter($value);
					break;

				case 'cat_id':
					if (is_null($value))
					{
						$filters .= '(!(category=*))';
					}
					elseif((int)$value)
					{
						if (!is_object($GLOBALS['egw']->categories))
						{
							$GLOBALS['egw']->categories = new Api\Categories();
						}
						$cats = $GLOBALS['egw']->categories->return_all_children((int)$value);
						if (count($cats) > 1) $filters .= '(|';
						foreach($cats as $cat)
						{
							$catName = Api\Translation::convert(
								$GLOBALS['egw']->categories->id2name($cat),$this->charset,'utf-8');
							$filters .= '(category='.Api\Ldap::quote($catName).')';
						}
						if (count($cats) > 1) $filters .= ')';
					}
					break;

				case 'carddav_name':
					if (!is_array($value)) $value = array($value);
					foreach($value as &$v)
					{
						$v = basename($v, '.vcf');
					}
					// fall through
				case 'id':
				case 'contact_id':
					$filters .= $this->ids_filter($value);
					break;

				case 'list':
					$filters .= $this->membershipFilter($value);
					break;

				default:
					$matches = null;
					if (!is_int($key))
					{
						foreach($this->schema2egw as $mapping)
						{
							if (isset($mapping[$key]))
							{
								// todo: value = "!''"
								$filters .= '('.$mapping[$key].'='.($value === "!''" ? '*' :
									Api\Ldap::quote(Api\Translation::convert($value,$this->charset,'utf-8'))).')';
								break;
							}
						}
					}
					// filter for letter-search
					elseif (preg_match("/^([^ ]+) ".preg_quote($GLOBALS['egw']->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE], '/')." '(.*)%'$/",$value,$matches))
					{
						list(,$name,$value) = $matches;
						if (strpos($name,'.') !== false) list(,$name) = explode('.',$name);
						foreach($this->schema2egw as $mapping)
						{
							if (isset($mapping[$name]))
							{
								$filters .= '('.$mapping[$name].'='.Api\Ldap::quote(
									Api\Translation::convert($value,$this->charset,'utf-8')).'*)';
								break;
							}
						}
					}
					break;
			}
		}
		return $filters;
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
		if ($gid < 0)
		{
			$filter .= '(|';
			// unfortunately we have no group-membership attribute in LDAP, like in AD
			foreach($GLOBALS['egw']->accounts->members($gid, true) as $account_id)
			{
				$filter .= '(uidNumber='.(int)$account_id.')';
			}
			$filter .= ')';
		}
		return $filter;
	}

	/**
	 * Get value(s) for LDAP_CONTROL_SORTREQUEST
	 *
	 * Sorting by multiple criteria is supported in LDAP RFC 2891, but - at least with Univention Samba - gives wired results,
	 * Windows AD does NOT support it and gives an error if the oid is specified!
	 *
	 * @param ?string $order_by sql order string eg. "contact_email ASC"
	 * @return array of arrays with values for keys 'attr', 'oid' (caseIgnoreMatch='2.5.13.3') and 'reverse'
	 */
	protected function sort_values($order_by)
	{
		$values = [];
		while (!empty($order_by) && preg_match("/^(contact_)?([^ ]+)( ASC| DESC)?,?/i", $order_by, $matches))
		{
			if (($attr = array_search($matches[2], $this->timestamps2egw)))
			{
				// Microsoft AD can NOT VLV sort by (modify|create)TimeStamp, we have to use when(Created|Changed) attribute
				if (static::class === Ads::class)
				{
					$attr = $attr === 'modifytimestamp' ? 'whenChanged' : 'whenCreated';
				}
				$values[] = [
					'attr' => $attr,
					// use default match 'oid' => '',
					'reverse' => strtoupper($matches[3]) === ' DESC',
				];
			}
			else
			{
				foreach ($this->schema2egw as $mapping)
				{
					if (isset($mapping[$matches[2]]))
					{
						$value = [
							'attr' => $mapping[$matches[2]],
							'oid' => '2.5.13.3',    // caseIgnoreMatch
							'reverse' => strtoupper($matches[3]) === ' DESC',
						];
						// Windows AD does NOT support caseIgnoreMatch sorting, only it's default sorting
						if ($this->ldapServerInfo->activeDirectory(true)) unset($value['oid']);
						$values[] = $value;
						break;
					}
				}
			}
			$order_by = substr($order_by, strlen($matches[0]));
			if ($values) break;	// sorting by multiple criteria gives no result for Windows AD and wired result for Samba4
		}
		//error_log(__METHOD__."('$order_by') returning ".json_encode($values));
		return $values;
	}

	/**
	 * Perform the actual ldap-search, retrieve and convert all entries
	 *
	 * Used be read and search
	 *
	 * @param string $_ldapContext
	 * @param string $_filter
	 * @param array $_attributes
	 * @param int $_addressbooktype
	 * @param array $_skipPlugins =null schema-plugins to skip
	 * @param string $order_by sql order string eg. "contact_email ASC"
	 * @param null|int|array $start [$start, $num_rows], on return null, if result sorted and limited by server
	 * @return array/boolean with eGW contacts or false on error
	 */
	function _searchLDAP($_ldapContext, $_filter, $_attributes, $_addressbooktype, array $_skipPlugins=null, $order_by=null, &$start=null)
	{
		$_attributes[] = 'entryUUID';
		$_attributes[] = 'objectClass';
		$_attributes[] = 'createTimestamp';
		$_attributes[] = 'modifyTimestamp';
		$_attributes[] = 'creatorsName';
		$_attributes[] = 'modifiersName';

		//error_log(__METHOD__."('$_ldapContext', '$_filter', ".array2string($_attributes).", $_addressbooktype)");

		// check if we require sorting and server supports it
		$control = [];
		if (PHP_VERSION >= 7.3 && !empty($order_by) && is_array($start) && $this->ldapServerInfo->supportedControl(LDAP_CONTROL_SORTREQUEST, LDAP_CONTROL_VLVREQUEST) &&
			($sort_values = $this->sort_values($order_by)))
		{
			[$offset, $num_rows] = $start;

			$control[] = [
				'oid' => LDAP_CONTROL_SORTREQUEST,
				//'iscritical' => TRUE,
				'value' => $sort_values,
			];
			$control[] = [
				'oid' => LDAP_CONTROL_VLVREQUEST,
				//'iscritical' => TRUE,
				'value' => [
					'before'	=> 0, // Return 0 entry before target
					'after'		=> $num_rows-1, // total-1
					'offset'	=> $offset+1, // first = 1, NOT 0!
					'count'		=> 0, // We have no idea how many entries there are
				]
			];
		}
		elseif (PHP_VERSION >= 7.3 && empty($order_by) &&
			($start === false || is_array($start) && count($start) === 3) &&
			$this->ldapServerInfo->supportedControl(LDAP_CONTROL_PAGEDRESULTS))
		{
			if ($start === false)
			{
				$start = [false, 500, ''];
			}
			$control[] = [
				'oid' => LDAP_CONTROL_PAGEDRESULTS,
				//'iscritical' => TRUE,
				'value' => [
					'size' => $start[1],
					'cookie' => $start[2],
				],
			];
		}
		if (!is_array($start) || count($start) < 3 || $start[2] === '')
		{
			$this->total = 0;
		}

		if($_addressbooktype == self::ALL || $_ldapContext == $this->allContactsDN)
		{
			$result = ldap_search($this->ds, $_ldapContext, $_filter, $_attributes, 0, $this->ldapLimit, null, null, $control);
		}
		else
		{
			$result = @ldap_list($this->ds, $_ldapContext, $_filter, $_attributes, 0, $this->ldapLimit, null, null, $control);
		}
		if(!$result || !$entries = ldap_get_entries($this->ds, $result)) return array();
		$this->total += $entries['count'];
		//error_log(__METHOD__."('$_ldapContext', '$_filter', ".array2string($_attributes).", $_addressbooktype) result of $entries[count]");

		// check if given controls succeeded
		if ($control && ldap_parse_result($this->ds, $result, $errcode, $matcheddn, $errmsg, $referrals, $serverctrls))
		{
			if (isset($serverctrls[LDAP_CONTROL_VLVRESPONSE]['value']['count']))
			{
				$this->total = $serverctrls[LDAP_CONTROL_VLVRESPONSE]['value']['count'];
				$start = null;	// so caller does NOT run it's own limit
			}
			elseif (isset($serverctrls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie']))
			{
				$start[2] = $serverctrls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'];
			}
		}

		foreach($entries as $i => $entry)
		{
			if (!is_int($i)) continue;	// eg. count

			$contact = array(
				'id'  => $entry['uid'][0] ?? $entry['entryuuid'][0],
				'tid' => 'n',	// the type id for the addressbook
			);
			if (!empty($this->schema2egw[self::CF_OBJECTCLASS]))
			{
				$entry['objectclass'][] = self::CF_OBJECTCLASS;
			}
			foreach($entry['objectclass'] as $ii => $objectclass)
			{
				$objectclass = strtolower($objectclass);
				if (!is_int($ii) || !isset($this->schema2egw[$objectclass]))
				{
					continue;	// eg. count or unsupported objectclass
				}
				foreach($this->schema2egw[$objectclass] as $egwFieldName => $ldapFieldName)
				{
					if(!empty($entry[$ldapFieldName][0]) && !is_int($egwFieldName) && !isset($contact[$egwFieldName]))
					{
						$contact[$egwFieldName] = Api\Translation::convert($entry[$ldapFieldName][0],'utf-8');
					}
				}
				$objectclass2egw = '_'.$objectclass.'2egw';
				if (!in_array($objectclass2egw, (array)$_skipPlugins) &&method_exists($this,$objectclass2egw))
				{
					$this->$objectclass2egw($contact,$entry);
				}
			}
			// read binary jpegphoto only for one result == call by read
			if ($this->total == 1 && isset($entry['jpegphoto'][0]))
			{
				$bin = ldap_get_values_len($this->ds,ldap_first_entry($this->ds,$result),'jpegphoto');
				$contact['jpegphoto'] = $bin[0];
			}
			else
			{
				$contact['jpegphoto'] = isset($entry['jpegphoto'][0]);
			}
			$matches = null;
			if(preg_match('/cn=([^,]+),'.preg_quote($this->personalContactsDN,'/').'$/i',$entry['dn'],$matches))
			{
				// personal addressbook
				$contact['owner'] = $GLOBALS['egw']->accounts->name2id($matches[1],'account_lid','u');
				$contact['private'] = 0;
			}
			elseif(preg_match('/cn=([^,]+),'.preg_quote($this->sharedContactsDN,'/').'$/i',$entry['dn'],$matches))
			{
				// group addressbook
				$contact['owner'] = $GLOBALS['egw']->accounts->name2id($matches[1],'account_lid','g');
				$contact['private'] = 0;
			}
			else
			{
				// accounts
				$contact['owner'] = 0;
				$contact['private'] = 0;
			}
			foreach($this->timestamps2egw as $ldapFieldName => $egwFieldName)
			{
				if(!empty($entry[$ldapFieldName][0]))
				{
					$contact[$egwFieldName] = $this->_ldap2ts($entry[$ldapFieldName][0]);
				}
			}
			$contacts[] = $contact;
		}

		// if we have a non-empty cookie from paged results, continue reading from the server
		while (is_array($start) && count($start) === 3 && $start[0] === false && $start[2] !== '')
		{
			foreach($this->_searchLDAP($_ldapContext, $_filter, $_attributes, $_addressbooktype, $_skipPlugins, $order_by, $start) as $contact)
			{
				$contacts[] = $contact;
			}
		}
		if (is_array($start) && $start[0] === false)
		{
			$start = false;
		}
		return $contacts;
	}

	/**
	 * Creates a timestamp from the date returned by the ldap server
	 *
	 * @internal
	 * @param string $date YYYYmmddHHiiss
	 * @return int
	 */
	static function _ldap2ts($date)
	{
		return gmmktime(substr($date,8,2),substr($date,10,2),substr($date,12,2),
			substr($date,4,2),substr($date,6,2),substr($date,0,4));
	}

	/**
	 * Create LDAP date-value from timestamp
	 *
	 * @param integer $ts
	 * @return string
	 */
	static function _ts2ldap($ts)
	{
		return gmdate('YmdHis', $ts).'.0Z';
	}

	/**
	 * check if $baseDN exists. If not create it
	 *
	 * @param string $baseDN cn=xxx,ou=yyy,ou=contacts,$this->allContactsDN
	 * @return boolean/string false on success or string with error-message
	 */
	function _check_create_dn($baseDN)
	{
		// check if $baseDN exists. If not create new one
		if(@ldap_read($this->ds, $baseDN, 'objectclass=*'))
		{
			return false;
		}
		//error_log(__METHOD__."('$baseDN') !ldap_read({$this->ds}, '$baseDN', 'objectclass=*') ldap_errno()=".ldap_errno($this->ds).', ldap_error()='.ldap_error($this->ds).get_class($this));
		if(ldap_errno($this->ds) != 32 || substr($baseDN,0,3) != 'cn=')
		{
			error_log(__METHOD__."('$baseDN') baseDN does NOT exist and we cant/wont create it! ldap_errno()=".ldap_errno($this->ds).', ldap_error()='.ldap_error($this->ds));
			return $this->_error(__LINE__);	// baseDN does NOT exist and we cant/wont create it
		}

		list(,$ou) = explode(',',$baseDN);
		$adminDS = null;
		foreach(array(
			'ou=contacts,'.$this->allContactsDN,
			$ou.',ou=contacts,'.$this->allContactsDN,
			$baseDN,
		) as $dn)
		{
			if (!@ldap_read($this->ds, $dn, 'objectclass=*') && ldap_errno($this->ds) == 32)
			{
				// entry does not exist, lets try to create it
				list($top) = explode(',',$dn);
				list($var,$val) = explode('=',$top);
				$data = array(
					'objectClass' => $var == 'cn' ? 'organizationalRole' : 'organizationalUnit',
					$var => $val,
				);
				// create a admin connection to add the needed DN
				if (!isset($adminDS)) $adminDS = Api\Ldap::factory();
				if(!@ldap_add($adminDS, $dn, $data))
				{
					//echo "<p>ldap_add($adminDS,'$dn',".print_r($data,true).")</p>\n";
					$err = lang("Can't create dn %1",$dn).': '.$this->_error(__LINE__,$adminDS);
					return $err;
				}
			}
		}

		return false;
	}

	/**
	 * error message for failed ldap operation
	 *
	 * @param int $line
	 * @return string
	 */
	function _error($line,$ds=null)
	{
		return ldap_error($ds ? $ds : $this->ds).': '.__CLASS__.': '.$line;
	}

	/**
	 * Special handling for mapping of eGW contact-data to the evolutionPerson objectclass
	 *
	 * Please note: all regular fields are already copied!
	 *
	 * @internal
	 * @param array &$ldapContact already copied fields according to the mapping
	 * @param array $data eGW contact data
	 * @param boolean $isUpdate
	 */
	function _egw2evolutionperson(&$ldapContact,$data,$isUpdate)
	{
		if(!empty($data['cat_id']))
		{
			$ldapContact['category'] = array();
			foreach(is_array($data['cat_id']) ? $data['cat_id'] : explode(',',$data['cat_id'])  as $cat)
			{
				$ldapContact['category'][] = Api\Translation::convert(
					Api\Categories::id2name($cat),$this->charset,'utf-8');
			}
		}
		foreach(array(
			'postaladdress' => $data['adr_one_street'] .'$'. $data['adr_one_locality'] .', '. $data['adr_one_region'] .'$'. $data['adr_one_postalcode'] .'$$'. $data['adr_one_countryname'],
			'homepostaladdress' => $data['adr_two_street'] .'$'. $data['adr_two_locality'] .', '. $data['adr_two_region'] .'$'. $data['adr_two_postalcode'] .'$$'. $data['adr_two_countryname'],
		) as $attr => $value)
		{
			if($value != '$, $$$')
			{
				$ldapContact[$attr] = Api\Translation::convert($value,$this->charset,'utf-8');
			}
			elseif($isUpdate)
			{
				$ldapContact[$attr] = array();
			}
		}
		// save the phone number of the primary contact and not the eGW internal field-name
		if ($data['tel_prefer'] && $data[$data['tel_prefer']])
		{
			$ldapContact['primaryphone'] = $data[$data['tel_prefer']];
		}
		elseif($isUpdate)
		{
			$ldapContact['primaryphone'] = array();
		}
	}

	/**
	 * Special handling for mapping data of the evolutionPerson objectclass to eGW contact
	 *
	 * Please note: all regular fields are already copied!
	 *
	 * @internal
	 * @param array &$contact already copied fields according to the mapping
	 * @param array $data eGW contact data
	 */
	function _evolutionperson2egw(&$contact,$data)
	{
		if ($data['category'] && is_array($data['category']))
		{
			$contact['cat_id'] = array();
			foreach($data['category'] as $iii => $cat)
			{
				if (!is_int($iii)) continue;

				$contact['cat_id'][] = $GLOBALS['egw']->categories->name2id($cat);
			}
			if ($contact['cat_id']) $contact['cat_id'] = implode(',',$contact['cat_id']);
		}
		if ($data['primaryphone'])
		{
			unset($contact['tel_prefer']);	// to not find itself
			$contact['tel_prefer'] = array_search($data['primaryphone'][0],$contact);
		}
	}

	/**
	 * Special handling for mapping data of the inetOrgPerson objectclass to eGW contact
	 *
	 * Please note: all regular fields are already copied!
	 *
	 * @internal
	 * @param array &$contact already copied fields according to the mapping
	 * @param array $data eGW contact data
	 */
	function _inetorgperson2egw(&$contact, $data, $cn='cn')
	{
		$matches = null;
		if (empty($data['givenname'][0]) && !empty($data['sn'][0]))
		{
			$parts = explode($data['sn'][0], $data[$cn][0]);
			$contact['n_prefix'] = trim($parts[0]);
			$contact['n_suffix'] = trim($parts[1]);
		}
		// iOS addressbook either use "givenname surname" or "surname givenname" depending on contact preference display-order
		// in full name, so we need to check for both when trying to parse prefix, middle name and suffix form full name
		elseif (preg_match($preg='/^(.*) *'.preg_quote($data['givenname'][0], '/').' *(.*) *'.preg_quote($data['sn'][0], '/').' *(.*)$/', $data[$cn][0], $matches) ||
			preg_match($preg='/^(.*) *'.preg_quote($data['sn'][0], '/').'[, ]*(.*) *'.preg_quote($data['givenname'][0], '/').' *(.*)$/', $data[$cn][0], $matches))
		{
			list(,$contact['n_prefix'], $contact['n_middle'], $contact['n_suffix']) = $matches;
			//error_log(__METHOD__."() preg_match('$preg', '{$data[$cn][0]}') = ".array2string($matches));
		}
		else
		{
			$contact['n_prefix'] = $contact['n_suffix'] = $contact['n_middle'] = '';
		}
		//error_log(__METHOD__."(, data=array($cn=>{$data[$cn][0]}, sn=>{$data['sn'][0]}, givenName=>{$data['givenname'][0]}), cn='$cn') returning with contact=array(n_prefix={$contact['n_prefix']}, n_middle={$contact['n_middle']}, n_suffix={$contact['n_suffix']}) ".function_backtrace());
	}

	/**
	 * Special handling for mapping data of posixAccount objectclass to eGW contact
	 *
	 * Please note: all regular fields are already copied!
	 *
	 * @internal
	 * @param array &$contact already copied fields according to the mapping
	 * @param array $data eGW contact data
	 */
	function _posixaccount2egw(&$contact,$data)
	{
		unset($contact);	// not used, but required by function signature
		static $shadowExpireNow=null;
		if (!isset($shadowExpireNow)) $shadowExpireNow = floor((time()-date('Z'))/86400);

		// exclude expired or deactivated accounts
		if (isset($data['shadowexpire']) && $data['shadowexpire'][0] <= $shadowExpireNow)
		{
			return false;
		}
	}

	/**
	 * Special handling for mapping data of the mozillaAbPersonAlpha objectclass to eGW contact
	 *
	 * Please note: all regular fields are already copied!
	 *
	 * @internal
	 * @param array &$contact already copied fields according to the mapping
	 * @param array $data eGW contact data
	 */
	function _mozillaabpersonalpha2egw(&$contact,$data)
	{
		if ($data['c'])
		{
			$contact['adr_one_countryname'] = Api\Country::get_full_name($data['c'][0]);
		}
	}

	/**
	 * Special handling for mapping of eGW contact-data to the mozillaAbPersonAlpha objectclass
	 *
	 * Please note: all regular fields are already copied!
	 *
	 * @internal
	 * @param array &$ldapContact already copied fields according to the mapping
	 * @param array $data eGW contact data
	 * @param boolean $isUpdate
	 */
	function _egw2mozillaabpersonalpha(&$ldapContact,$data,$isUpdate)
	{
		if ($data['adr_one_countrycode'])
		{
			$ldapContact['c'] = $data['adr_one_countrycode'];
		}
		elseif ($data['adr_one_countryname'])
		{
			$ldapContact['c'] = Api\Country::country_code($data['adr_one_countryname']);
			if ($ldapContact['c'] && strlen($ldapContact['c']) > 2)	// Bad countryname when "custom" selected!
			{
				$ldapContact['c'] = array(); // should return error...
			}
		}
		elseif ($isUpdate)
		{
			$ldapContact['c'] = array();
		}
	}

	/**
	 * Special handling for mapping data of the mozillaOrgPerson objectclass to eGW contact
	 *
	 * Please note: all regular fields are already copied!
	 *
	 * @internal
	 * @param array &$contact already copied fields according to the mapping
	 * @param array $data eGW contact data
	 */
	function _mozillaorgperson2egw(&$contact,$data)
	{
		unset($contact, $data);	// not used, but required by function signature
		// no special handling necessary, as it supports two distinct attributes: c, cn
	}

	/**
	 * Special handling for mapping of eGW contact-data to the mozillaOrgPerson objectclass
	 *
	 * Please note: all regular fields are already copied!
	 *
	 * @internal
	 * @param array &$ldapContact already copied fields according to the mapping
	 * @param array $data eGW contact data
	 * @param boolean $isUpdate
	 */
	function _egw2mozillaorgperson(&$ldapContact,$data,$isUpdate)
	{
		if ($data['adr_one_countrycode'])
		{
			$ldapContact['c'] = $data['adr_one_countrycode'];
			if ($isUpdate) $ldapContact['co'] = array();
		}
		elseif ($data['adr_one_countryname'])
		{
			$ldapContact['c'] = Api\Country::country_code($data['adr_one_countryname']);
			if ($ldapContact['c'] && strlen($ldapContact['c']) > 2)	// Bad countryname when "custom" selected!
			{
				$ldapContact['c'] = array(); // should return error...
			}
		}
		elseif ($isUpdate)
		{
			$ldapContact['c'] = $ldapContact['co'] = array();
		}
		//error_log(__METHOD__."() adr_one_countrycode='{$data['adr_one_countrycode']}', adr_one_countryname='{$data['adr_one_countryname']}' --> c=".array2string($ldapContact['c']).', co='.array2string($ldapContact['co']));
	}

	/**
	 * Change the ownership of contacts owned by a given account
	 *
	 * @param int $account_id account-id of the old owner
	 * @param int $new_owner account-id of the new owner
	 */
	function change_owner($account_id,$new_owner)
	{
		error_log(__METHOD__."($account_id,$new_owner) not yet implemented");
	}
}