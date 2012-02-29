<?php
/**
 * Addressbook - LDAP Backend
 *
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw-AT-von-und-zu-weiss.de>
 * @author Lars Kneschke <l.kneschke-AT-metaways.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

define('ADDRESSBOOK_ALL',0);
define('ADDRESSBOOK_ACCOUNTS',1);
define('ADDRESSBOOK_PERSONAL',2);
define('ADDRESSBOOK_GROUP',3);

/**
 * LDAP Backend for contacts, compatible with vars and parameters of eTemplate's so_sql.
 * Maybe one day this becomes a generalized ldap storage object :-)
 *
 * All values used to construct filters need to run through ldap::quote(),
 * to be save against LDAP query injection!!!
 */
class addressbook_ldap
{
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
	* @var object $ldapServerInfo holds the information about the current used ldap server
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
	 * maps between diverse ldap schema and the eGW internal names
	 *
	 * The ldap attribute names have to be lowercase!!!
	 *
	 * @var array
	 */
	var $schema2egw = array(
		'posixaccount' => array(
			'account_id'	=> 'uidnumber',
			'account_lid'	=> 'uid',
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
		),

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
	 * constructor of the class
	 */
	function __construct()
	{
		//$this->db_data_cols 	= $this->stock_contact_fields + $this->non_contact_fields;
		$this->accountName 		= $GLOBALS['egw_info']['user']['account_lid'];

		$this->personalContactsDN	= 'ou=personal,ou=contacts,'. $GLOBALS['egw_info']['server']['ldap_contact_context'];
		$this->sharedContactsDN		= 'ou=shared,ou=contacts,'. $GLOBALS['egw_info']['server']['ldap_contact_context'];

		$this->connect();
		$this->ldapServerInfo = $GLOBALS['egw']->ldap->getLDAPServerInfo($GLOBALS['egw_info']['server']['ldap_contact_host']);

		foreach($this->schema2egw as $schema => $attributes)
		{
			$this->all_attributes = array_merge($this->all_attributes,array_values($attributes));
		}
		$this->all_attributes = array_values(array_unique($this->all_attributes));

		$this->charset = translation::charset();
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
	 */
	function connect()
	{
		// if ldap is NOT the contact repository, we only do accounts and need to use the account-data
		if (substr($GLOBALS['egw_info']['server']['contact_repository'],-4) != 'ldap')	// not (ldap or sql-ldap)
		{
			$GLOBALS['egw_info']['server']['ldap_contact_host'] = $GLOBALS['egw_info']['server']['ldap_host'];
			$GLOBALS['egw_info']['server']['ldap_contact_context'] = $GLOBALS['egw_info']['server']['ldap_context'];
			$this->ds = $GLOBALS['egw']->ldap->ldapConnect();
		}
		else
		{
			$this->ds = $GLOBALS['egw']->ldap->ldapConnect(
				$GLOBALS['egw_info']['server']['ldap_contact_host'],
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
			$filter = 'uidNumber='.(int)(is_array($contact_id) ? $contact_id['account_id'] : substr($contact_id,8));
		}
		else
		{
			$contact_id = ldap::quote(!is_array($contact_id) ? $contact_id :
				(isset ($contact_id['id']) ? $contact_id['id'] : $contact_id['uid']));
			$filter = "(|(entryUUID=$contact_id)(uid=$contact_id))";
		}
		$rows = $this->_searchLDAP($GLOBALS['egw_info']['server']['ldap_contact_context'],
			$filter, $this->all_attributes, ADDRESSBOOK_ALL);

		return $rows ? $rows[0] : false;
	}

	/**
	 * saves the content of data to the db
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null)
	{
		if(is_array($keys))
		{
			$this->data = is_array($this->data) ? array_merge($this->data,$keys) : $keys;
		}
		$contactUID = '';

		$data =& $this->data;
		$isUpdate = false;
		$newObjectClasses = array();
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
			$baseDN = 'cn='. ldap::quote($cn) .','.($data['owner'] < 0 ? $this->sharedContactsDN : $this->personalContactsDN);
		}
		// only an admin or the user itself is allowed to change the data of an account
		elseif ($data['account_id'] && ($GLOBALS['egw_info']['user']['apps']['admin'] ||
			$data['account_id'] == $GLOBALS['egw_info']['user']['account_id']))
		{
			// account
			$baseDN = $GLOBALS['egw_info']['server']['ldap_context'];
			$cn	= false;
			// we need an admin connection
			$this->ds = $GLOBALS['egw']->ldap->ldapConnect();

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
		$attributes = array('dn','cn','objectClass','uid','mail');
		$contactUID	= $this->data[$this->contacts_id];
		if(!empty($contactUID) &&
			($result = ldap_search($this->ds, $GLOBALS['egw_info']['server']['ldap_contact_context'],
				'(|(entryUUID='.ldap::quote($contactUID).')(uid='.ldap::quote($contactUID).'))', $attributes)) &&
			($oldContactInfo = ldap_get_entries($this->ds, $result)) && $oldContactInfo['count'])
		{
			unset($oldContactInfo[0]['objectclass']['count']);
			foreach($oldContactInfo[0]['objectclass'] as $objectclass)
			{
				$oldObjectclasses[]	= strtolower($objectclass);
			}
		   	$isUpdate = true;
		}
		if(!$contactUID)
		{
			$this->data[$this->contacts_id] = $contactUID = md5($GLOBALS['egw']->common->randomstring(15));
		}

		$ldapContact['uid'] = $contactUID;

		// add for all supported objectclasses the objectclass and it's attributes
		foreach($this->schema2egw as $objectclass => $mapping)
		{
			if(!$this->ldapServerInfo->supportsObjectClass($objectclass) || $objectclass == 'posixaccount') continue;

			if(!in_array($objectclass, $oldObjectclasses))
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
				if(!empty($data[$egwFieldName]))
				{
					// dont convert the (binary) jpegPhoto!
					$ldapContact[$ldapFieldName] = $ldapFieldName == 'jpegphoto' ? $data[$egwFieldName] :
						translation::convert(trim($data[$egwFieldName]),$this->charset,'utf-8');
				}
				elseif($isUpdate && isset($data[$egwFieldName]))
				{
					$ldapContact[$ldapFieldName] = array();
				}
			}
			// handling of special attributes, like cat_id in evolutionPerson
			$egw2objectclass = '_egw2'.$objectclass;
			if (method_exists($this,$egw2objectclass))
			{
				$this->$egw2objectclass($ldapContact,$data,$isUpdate);
			}
		}
		if($isUpdate)
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
			// never allow to change the uidNumber (account_id) on update, as it could be misused by eg. xmlrpc or syncml
			unset($ldapContact['uidnumber']);
			unset($ldapContact['entryuuid']);	// not allowed to modify that, no need either

			// add missing objectclasses
			if($ldapContact['objectClass'] && array_diff($ldapContact['objectClass'],$oldObjectclasses))
			{
				if (!@ldap_mod_add($this->ds, $dn, array('objectClass' => $ldapContact['objectClass'])))
				{
					if(in_array(ldap_errno($this->ds),array(69,20)))
					{
						// need to modify structural objectclass
						$needRecreation = true;

					}
					else
					{
						//echo "<p>ldap_mod_add($this->ds,'$dn',array(objectClass =>".print_r($ldapContact['objectClass'],true)."))</p>\n";
						error_log('class.so_ldap.inc.php ('. __LINE__ .') update of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')');
						return $this->_error(__LINE__);
					}
				}
			}

			// check if we need to rename the DN or need to recreate the contact
			$newRDN = 'uid='. ldap::quote($contactUID);
			$newDN = $newRDN .','. $baseDN;
			if(strtolower($dn) != strtolower($newDN) || $needRecreation)
			{
				$result = ldap_read($this->ds, $dn, 'objectclass=*');
				$oldContact = ldap_get_entries($this->ds, $result);
				foreach($oldContact[0] as $key => $value)
				{
					if(is_array($value))
					{
						unset($value['count']);
						$newContact[$key] = $value;
					}
				}
				$newContact['uid'] = $contactUID;

				if(is_array($ldapContact['objectClass']) && count($ldapContact['objectClass']) > 0)
				{
					$newContact['objectclass'] = array_unique(array_map('strtolower',	// objectclasses my have different case
						array_merge($newContact['objectclass'], $ldapContact['objectClass'])));
				}

				if(!ldap_delete($this->ds, $dn))
				{
					error_log('class.so_ldap.inc.php ('. __LINE__ .') delete of old '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')');
					return $this->_error(__LINE__);
				}
				if(!@ldap_add($this->ds, $newDN, $newContact))
				{
					//echo "<p>recreate: ldap_add($this->ds,'$newDN',".print_r($newContact,true).")</p>\n";
					//print 'class.so_ldap.inc.php ('. __LINE__ .') update of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')';_debug_array($newContact);exit;
					error_log('class.so_ldap.inc.php ('. __LINE__ .') re-create contact as '. $newDN .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')');
					error_log(print_r($newContact,true));
					return $this->_error(__LINE__);
				}
				$dn = $newDN;
			}
			unset($ldapContact['objectClass']);

			if (!@ldap_modify($this->ds, $dn, $ldapContact))
			{
				//echo "<p>ldap_modify($this->ds,'$dn',".print_r($ldapContact,true).")</p>\n";
				error_log('class.so_ldap.inc.php ('. __LINE__ .') update of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')');
				error_log(print_r($ldapContact,true));
				return $this->_error(__LINE__);
			}
		}
		else
		{
			$dn = 'uid='. ldap::quote($ldapContact['uid']) .','. $baseDN;

			if (!@ldap_add($this->ds, $dn, $ldapContact))
			{
				//echo "<p>ldap_add($this->ds,'$dn',".print_r($ldapContact,true).")</p>\n";
				error_log('class.so_ldap.inc.php ('. __LINE__ .') add of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')');
				error_log(print_r($ldapContact,true));
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
			$entry = ldap::quote(is_array($entry) ? $entry['id'] : $entry);
			if($result = ldap_search($this->ds, $GLOBALS['egw_info']['server']['ldap_contact_context'],
				"(|(entryUUID=$entry)(uid=$entry))", $attributes))
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
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string $only_keys=true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
		//_debug_array($criteria); print "OrderBY: $order_by";_debug_array($extra_cols);_debug_array($filter);
		#$order_by = explode(',',$order_by);
		#$order_by = explode(' ',$order_by);
		#$sort = $order_by[0];
		#$order = $order_by[1];
		#$query = $criteria;
		#$fields = $only_keys ? ($only_keys === true ? $this->contacts_id : $only_keys) : '';
		#$limit = $need_full_no_count ? 0 : $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
		#return parent::read($start,$limit,$fields,$query,$filter,$sort,$order);

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

		if((int)$filter['owner'])
		{
			if (!($accountName = $GLOBALS['egw']->accounts->id2name($filter['owner']))) return false;

			$searchDN = 'cn='. ldap::quote(strtolower($accountName)) .',';

			if ($filter['owner'] < 0)
			{
				$searchDN .= $this->sharedContactsDN;
				$addressbookType = ADDRESSBOOK_GROUP;
			}
			else
			{
				$searchDN .= $this->personalContactsDN;
				$addressbookType = ADDRESSBOOK_PERSONAL;
			}
		}
		elseif (!isset($filter['owner']))
		{
			$searchDN = $GLOBALS['egw_info']['server']['ldap_contact_context'];
			$addressbookType = ADDRESSBOOK_ALL;
		}
		else
		{
			$searchDN = $GLOBALS['egw_info']['server']['ldap_context'];
			$addressbookType = ADDRESSBOOK_ACCOUNTS;
		}

		// create the search filter
		switch($addressbookType)
		{
			case ADDRESSBOOK_ACCOUNTS:
				$objectFilter = '(objectclass=posixaccount)';
				break;
			default:
				$objectFilter = '(objectclass=inetorgperson)';
				break;
		}

		$searchFilter = '';
		if(is_array($criteria) && count($criteria) > 0)
		{
			$wildcard = $wildcard === '%' ? '*' : '';
			$searchFilter = '';
			foreach($criteria as $egwSearchKey => $searchValue)
			{
				foreach($this->schema2egw as $mapping)
				{
					if(($ldapSearchKey = $mapping[$egwSearchKey]))
					{
						$searchString = translation::convert($searchValue,$this->charset,'utf-8');
						$searchFilter .= '('.$ldapSearchKey.'='.$wildcard.ldap::quote($searchString).$wildcard.')';
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
		$ldapFilter = "(&$objectFilter$searchFilter$colFilter)";

		if (!($rows = $this->_searchLDAP($searchDN, $ldapFilter, $this->all_attributes, $addressbookType)))
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
			$rows = ExecMethod2('phpgwapi.arrayfunctions.arfsort',$rows,$order,$sort);
		}
		// if requested ($start !== false) return only limited resultset
		if (is_array($start))
		{
			list($start,$offset) = $start;
		}
		if(is_numeric($start) && is_numeric($offset))
		{
			return array_slice($rows, $start, $offset);
		}
		elseif(is_numeric($start))
		{
			return array_slice($rows, $start, $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']);
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
					if (is_null($value))
					{
						$filters .= '(!(uidNumber=*))';
					}
					elseif ($value)
					{
						$filters .= '(uidNumber='.ldap::quote($value).')';

					}
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
							$GLOBALS['egw']->categories = CreateObject('phpgwapi.categories');
						}
						$cats = $GLOBALS['egw']->categories->return_all_children((int)$value);
						if (count($cats) > 1) $filters .= '(|';
						foreach($cats as $cat)
						{
							$catName = translation::convert(
								$GLOBALS['egw']->categories->id2name($cat),$this->charset,'utf-8');
							$filters .= '(category='.ldap::quote($catName).')';
						}
						if (count($cats) > 1) $filters .= ')';
					}
					break;

				default:
					if (!is_int($key))
					{
						foreach($this->schema2egw as $mapping)
						{
							if (isset($mapping[$key]))
							{
								// todo: value = "!''"
								$filters .= '('.$mapping[$key].'='.($value === "!''" ? '*' :
									ldap::quote(translation::convert($value,$this->charset,'utf-8'))).')';
								break;
							}
						}
					}
					// filter for letter-search
					elseif (preg_match("/^([^ ]+) ".preg_quote($GLOBALS['egw']->db->capabilities[egw_db::CAPABILITY_CASE_INSENSITIV_LIKE])." '(.*)%'$/",$value,$matches))
					{
						list(,$name,$value) = $matches;
						if (strpos($name,'.') !== false) list(,$name) = explode('.',$name);
						foreach($this->schema2egw as $mapping)
						{
							if (isset($mapping[$name]))
							{
								$filters .= '('.$mapping[$name].'='.ldap::quote(
									translation::convert($value,$this->charset,'utf-8')).'*)';
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
	 * Perform the actual ldap-search, retrieve and convert all entries
	 *
	 * Used be read and search
	 *
	 * @internal
	 * @param string $_ldapContext
	 * @param string $_filter
	 * @param array $_attributes
	 * @param int $_addressbooktype
	 * @return array/boolean with eGW contacts or false on error
	 */
	function _searchLDAP($_ldapContext, $_filter, $_attributes, $_addressbooktype)
	{
		$this->total = 0;

		$_attributes[] = 'entryUUID';
		$_attributes[] = 'uid';
		$_attributes[] = 'uidNumber';
		$_attributes[] = 'objectClass';
		$_attributes[] = 'createTimestamp';
		$_attributes[] = 'modifyTimestamp';
		$_attributes[] = 'creatorsName';
		$_attributes[] = 'modifiersName';

		//echo "<p>ldap_search($this->ds, $_ldapContext, $_filter, $_attributes, 0, $this->ldapLimit)</p>\n";
		if($_addressbooktype == ADDRESSBOOK_ALL)
		{
			$result = ldap_search($this->ds, $_ldapContext, $_filter, $_attributes, 0, $this->ldapLimit);
		}
		else
		{
			$result = @ldap_list($this->ds, $_ldapContext, $_filter, $_attributes, 0, $this->ldapLimit);
		}
		if(!$result) return array();

		$entries = ldap_get_entries($this->ds, $result);
		$this->total = $entries['count'];
		foreach((array)$entries as $i => $entry)
		{
			if (!is_int($i)) continue;	// eg. count

			$contact = array(
				'id'  => $entry['uid'][0] ? $entry['uid'][0] : $entry['entryuuid'][0],
				'tid' => 'n',	// the type id for the addressbook
			);
			foreach($entry['objectclass'] as $ii => $objectclass)
			{
				$objectclass = strtolower($objectclass);
				if (!is_int($ii) || !isset($this->schema2egw[$objectclass]))
				{
					continue;	// eg. count or unsupported objectclass
				}
				foreach($this->schema2egw[$objectclass] as $egwFieldName => $ldapFieldName)
				{
					if(!empty($entry[$ldapFieldName][0]) && !isset($contact[$egwFieldName]))
					{
						$contact[$egwFieldName] = translation::convert($entry[$ldapFieldName][0],'utf-8');
					}
				}
				$objectclass2egw = '_'.$objectclass.'2egw';
				if (method_exists($this,$objectclass2egw))
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
			#########################################
			## this piece of code could never have been working, as the call to $GLOBALS['egw']->accounts->name2id is wrong
			#########################################
			#foreach(array(
			#	'creatorsname' => 'creator',
			#	'modifiersname' => 'modifier',
			#) as $ldapFieldName => $egwFieldName)
			#{
			#	if (!empty($entry[$ldapFieldName][0]) && preg_match('/^cn=([^,]+),/',$entry[$ldapFieldName][0],$matches))
			#	{
			#		$contact[$egwFieldName] = $GLOBALS['egw']->accounts->name2id($matches[1],'u');
			#	}
			#}
			foreach(array(
				'createtimestamp' => 'created',
				'modifytimestamp' => 'modified',
			) as $ldapFieldName => $egwFieldName)
			{
				if(!empty($entry[$ldapFieldName][0]))
				{
					$contact[$egwFieldName] = $this->_ldap2ts($entry[$ldapFieldName][0]);
				}
			}
			$contacts[] = $contact;
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
	function _ldap2ts($date)
	{
		return gmmktime(substr($date,8,2),substr($date,10,2),substr($date,12,2),
			substr($date,4,2),substr($date,6,2),substr($date,0,4));
	}

	/**
	 * check if $baseDN exists. If not create it
	 *
	 * @param string $baseDN cn=xxx,ou=yyy,ou=contacts,$GLOBALS['egw_info']['server']['ldap_contact_context']
	 * @return boolean/string false on success or string with error-message
	 */
	function _check_create_dn($baseDN)
	{
		// check if $baseDN exists. If not create new one
		if(@ldap_read($this->ds, $baseDN, 'objectclass=*'))
		{
			return false;
		}
		if(ldap_errno($this->ds) != 32 || substr($baseDN,0,3) != 'cn=')
		{
			return $this->_error(__LINE__);	// baseDN does NOT exist and we cant/wont create it
		}
		// create a admin connection to add the needed DN
		$adminLDAP = new ldap;
		$adminDS = $adminLDAP->ldapConnect();

		list(,$ou) = explode(',',$baseDN);
		foreach(array(
			'ou=contacts,'.$GLOBALS['egw_info']['server']['ldap_contact_context'],
			$ou.',ou=contacts,'.$GLOBALS['egw_info']['server']['ldap_contact_context'],
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
				if(!@ldap_add($adminDS, $dn, $data))
				{
					//echo "<p>ldap_add($adminDS,'$dn',".print_r($data,true).")</p>\n";
					$err = $this->_error(__LINE__,$adminDS);
					$adminLDAP->ldapDisconnect();
					return $err;
				}
			}
		}
		$adminLDAP->ldapDisconnect();

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
		return ldap_error($ds ? $ds : $this->ds).': so_ldap: '.$line;
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
				$ldapContact['category'][] = translation::convert(
					ExecMethod('phpgwapi.categories.id2name',$cat),$this->charset,'utf-8');
			}
		}
		foreach(array(
			'postaladdress' => $data['adr_one_street'] .'$'. $data['adr_one_locality'] .', '. $data['adr_one_region'] .'$'. $data['adr_one_postalcode'] .'$$'. $data['adr_one_countryname'],
			'homepostaladdress' => $data['adr_two_street'] .'$'. $data['adr_two_locality'] .', '. $data['adr_two_region'] .'$'. $data['adr_two_postalcode'] .'$$'. $data['adr_two_countryname'],
		) as $attr => $value)
		{
			if($value != '$, $$$')
			{
				$ldapContact[$attr] = translation::convert($value,$this->charset,'utf-8');
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

				$contact['cat_id'][] = ExecMethod('phpgwapi.categories.name2id',$cat);
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
	function _inetorgperson2egw(&$contact,$data)
	{
		if(empty($data['givenname'][0]))
		{
			$parts = explode($data['sn'][0], $data['cn'][0]);
			$contact['n_prefix'] = trim($parts[0]);
			$contact['n_suffix'] = trim($parts[1]);
		}
		else
		{
			$parts = preg_split('/'. preg_quote($data['givenname'][0],'/') .'.*'. preg_quote($data['sn'][0],'/') .'/', $data['cn'][0]);
			$contact['n_prefix'] = trim($parts[0]);
			$contact['n_suffix'] = trim($parts[1]);
			if(preg_match('/'. preg_quote($data['givenname'][0],'/') .' (.*) '. preg_quote($data['sn'][0],'/') .'/',$data['cn'][0], $matches))
			{
				$contact['n_middle'] = $matches[1];
			}
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
			$contact['adr_one_countryname'] = ExecMethod('phpgwapi.country.get_full_name',$data['c'][0]);
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
			$ldapContact['c'] = ExecMethod('phpgwapi.country.country_code',$data['adr_one_countryname']);
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
			$ldapContact['c'] = ExecMethod('phpgwapi.country.country_code',$data['adr_one_countryname']);
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
		error_log("so_ldap::change_owner($account_id,$new_owner) not yet implemented");
	}
}
