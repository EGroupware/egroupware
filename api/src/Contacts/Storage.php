<?php
/**
 * EGroupware API - Contacts storage object
 *
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw-AT-von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2005-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Contacts;

use EGroupware\Api;

/**
 * Contacts storage object
 *
 * The contact storage has 3 operation modi (contact_repository):
 * - sql: contacts are stored in the SQL table egw_addressbook & egw_addressbook_extra (custom fields)
 * - ldap: contacts are stored in LDAP (accounts have to be stored in LDAP too!!!).
 *   Custom fields are not availible in that case!
 * - sql-ldap: contacts are read and searched in SQL, but saved to both SQL and LDAP.
 *   Other clients (Thunderbird, ...) can use LDAP readonly. The get maintained via eGroupWare only.
 *
 * The accounts can be stored in SQL or LDAP too (account_repository):
 * If the account-repository is different from the contacts-repository, the filter all (no owner set)
 * will only search the contacts and NOT the accounts! Only the filter accounts (owner=0) shows accounts.
 *
 * If sql-ldap is used as contact-storage (LDAP is managed from eGroupWare) the filter all, searches
 * the accounts in the SQL contacts-table too. Change in made in LDAP, are not detected in that case!
 */

class Storage
{
	/**
	 * name of customefields table
	 *
	 * @var string
	 */
	var $extra_table = 'egw_addressbook_extra';

	/**
	* @var string
	*/
	var $extra_id = 'contact_id';

	/**
	* @var string
	*/
	var $extra_owner = 'contact_owner';

	/**
	* @var string
	*/
	var $extra_key = 'contact_name';

	/**
	* @var string
	*/
	var $extra_value = 'contact_value';

	/**
	 * view for distributionlistsmembership
	 *
	 * @var string
	 */
	var $distributionlist_view ='(SELECT contact_id, egw_addressbook_lists.list_id as list_id, egw_addressbook_lists.list_name as list_name, egw_addressbook_lists.list_owner as list_owner FROM egw_addressbook_lists, egw_addressbook2list where egw_addressbook_lists.list_id=egw_addressbook2list.list_id) d_view ';
	var $distributionlist_tabledef = array();
	/**
	* @var string
	*/
	var $distri_id = 'contact_id';

	/**
	* @var string
	*/
	var $distri_owner = 'list_owner';

	/**
	* @var string
	*/
	var $distri_key = 'list_id';

	/**
	* @var string
	*/
	var $distri_value = 'list_name';

	/**
	 * Contact repository in 'sql' or 'ldap'
	 *
	 * @var string
	 */
	var $contact_repository = 'sql';

	/**
	 * Grants as  account_id => rights pairs
	 *
	 * @var array
	 */
	var $grants = array();

	/**
	 *  userid of current user
	 *
	 * @var int
	 */
	var $user;

	/**
	 * memberships of the current user
	 *
	 * @var array
	 */
	var $memberships;

	/**
	 * In SQL we can search all columns, though a view make on real sense
	 */
	var $sql_cols_not_to_search = array(
		'jpegphoto','owner','tid','private','cat_id','etag',
		'modified','modifier','creator','created','tz','account_id',
		'uid','carddav_name','freebusy_uri','calendar_uri',
		'geo','pubkey',
	);
	/**
	 * columns to search, if we search for a single pattern
	 *
	 * @var array
	 */
	var $columns_to_search = array();
	/**
	 * extra columns to search if accounts are included, eg. account_lid
	 *
	 * @var array
	 */
	var $account_extra_search = array();
	/**
	 * columns to search for accounts, if stored in different repository
	 *
	 * @var array
	 */
	var $account_cols_to_search = array();

	/**
	 * customfields name => array(...) pairs
	 *
	 * @var array
	 */
	var $customfields = array();
	/**
	 * content-types as name => array(...) pairs
	 *
	 * @var array
	 */
	var $content_types = array();

	/**
	 * Directory to store striped photo or public keys in VFS directory of entry
	 */
	const FILES_DIRECTORY = '.files';
	const FILES_PHOTO = '.files/photo.jpeg';
	const FILES_PGP_PUBKEY = '.files/pgp-pubkey.asc';
	const FILES_SMIME_PUBKEY =  '.files/smime-pubkey.crt';

	/**
	 * Constant for bit-field "contact_files" storing what files are available
	 */
	const FILES_BIT_PHOTO = 1;
	const FILES_BIT_PGP_PUBKEY = 2;
	const FILES_BIT_SMIME_PUBKEY = 4;

	/**
	 * These fields are options for checking for duplicate contacts
	 *
	 * @var array
	 */
	public static $duplicate_fields = array(
		'n_given'           => 'first name',
		'n_middle'          => 'middle name',
		'n_family'          => 'last name',
		'contact_bday'      => 'birthday',
		'org_name'          => 'Organisation',
		'org_unit'          => 'Department',
		'adr_one_locality'  => 'Location',
		'contact_title'     => 'title',
		'contact_email'     => 'business email',
		'contact_email_home'=> 'email (private)',
	);

	/**
	* Special content type to indicate a deleted addressbook
	*
	* @var String;
	*/
	const DELETED_TYPE = 'D';

	/**
	 * total number of matches of last search
	 *
	 * @var int
	 */
	var $total;

	/**
	 * storage object: sql (Sql) or ldap (addressbook_ldap) backend class
	 *
	 * @var Sql
	 */
	var $somain;
	/**
	 * storage object for accounts, if not identical to somain (eg. accounts in ldap, contacts in sql)
	 *
	 * @var Ldap
	 */
	var $so_accounts;
	/**
	 * account repository sql or ldap
	 *
	 * @var string
	 */
	var $account_repository = 'sql';
	/**
	 * custom fields backend
	 *
	 * @var Sql
	 */
	var $soextra;
	var $sodistrib_list;

	/**
	 * Constructor
	 *
	 * @param string $contact_app ='addressbook' used for acl->get_grants()
	 * @param Api\Db $db =null
	 */
	function __construct($contact_app='addressbook',Api\Db $db=null)
	{
		$this->db     = is_null($db) ? $GLOBALS['egw']->db : $db;

		$this->user = $GLOBALS['egw_info']['user']['account_id'];
		$this->memberships = $GLOBALS['egw']->accounts->memberships($this->user,true);

		// account backend used
		if ($GLOBALS['egw_info']['server']['account_repository'])
		{
			$this->account_repository = $GLOBALS['egw_info']['server']['account_repository'];
		}
		elseif ($GLOBALS['egw_info']['server']['auth_type'])
		{
			$this->account_repository = $GLOBALS['egw_info']['server']['auth_type'];
		}
		$this->customfields = Api\Storage\Customfields::get('addressbook');
		// contacts backend (contacts in LDAP require accounts in LDAP!)
		if($GLOBALS['egw_info']['server']['contact_repository'] == 'ldap' && $this->account_repository == 'ldap')
		{
			$this->contact_repository = 'ldap';
			$this->somain = new Ldap();
			$this->columns_to_search = $this->somain->search_attributes;
		}
		else	// sql or sql->ldap
		{
			if ($GLOBALS['egw_info']['server']['contact_repository'] == 'sql-ldap')
			{
				$this->contact_repository = 'sql-ldap';
			}
			$this->somain = new Sql($db);

			// remove some columns, absolutly not necessary to search in sql
			$this->columns_to_search = array_diff(array_values($this->somain->db_cols),$this->sql_cols_not_to_search);
		}
		$this->grants = $this->get_grants($this->user,$contact_app);

		if ($this->account_repository != 'sql' && $this->contact_repository == 'sql')
		{
			if ($this->account_repository != $this->contact_repository)
			{
				$class = 'EGroupware\\Api\\Contacts\\'.ucfirst($this->account_repository);
				$this->so_accounts = new $class();
				$this->account_cols_to_search = $this->so_accounts->search_attributes;
			}
			else
			{
				$this->account_extra_search = array('uid');
			}
		}
		if ($this->contact_repository == 'sql' || $this->contact_repository == 'sql-ldap')
		{
			$tda2list = $this->db->get_table_definitions('api','egw_addressbook2list');
			$tdlists = $this->db->get_table_definitions('api','egw_addressbook_lists');
			$this->distributionlist_tabledef = array('fd' => array(
					$this->distri_id => $tda2list['fd'][$this->distri_id],
					$this->distri_owner => $tdlists['fd'][$this->distri_owner],
        	    	$this->distri_key => $tdlists['fd'][$this->distri_key],
					$this->distri_value => $tdlists['fd'][$this->distri_value],
				), 'pk' => array(), 'fk' => array(), 'ix' => array(), 'uc' => array(),
			);
		}
		// ToDo: it should be the other way arround, the backend should set the grants it uses
		$this->somain->grants =& $this->grants;

		if($this->somain instanceof Sql)
		{
			$this->soextra =& $this->somain;
		}
		else
		{
			$this->soextra = new Sql($db);
		}

		$this->content_types = Api\Config::get_content_types('addressbook');
		if (!$this->content_types)
		{
			$this->content_types = array('n' => array(
				'name' => 'contact',
				'options' => array(
					'template' => 'addressbook.edit',
					'icon' => 'navbar.png'
			)));
		}

		// Add in deleted type, if holding deleted contacts
		$config = Api\Config::read('phpgwapi');
		if($config['history'])
		{
			$this->content_types[self::DELETED_TYPE] = array(
				'name'	=>	lang('Deleted'),
				'options' =>	array(
					'template'	=>	'addressbook.edit',
					'icon'		=>	'deleted.png'
				)
			);
		}
	}

	/**
	 * Get grants for a given user, taking into account static LDAP ACL
	 *
	 * @param int $user
	 * @param string $contact_app ='addressbook'
	 * @return array
	 */
	function get_grants($user, $contact_app='addressbook', $preferences=null)
	{
		if (!isset($preferences)) $preferences = $GLOBALS['egw_info']['user']['preferences'];

		if ($user)
		{
			// contacts backend (contacts in LDAP require accounts in LDAP!)
			if($GLOBALS['egw_info']['server']['contact_repository'] == 'ldap' && $this->account_repository == 'ldap')
			{
				// static grants from ldap: all rights for the own personal addressbook and the group ones of the meberships
				$grants = array($user => ~0);
				foreach($GLOBALS['egw']->accounts->memberships($user,true) as $gid)
				{
					$grants[$gid] = ~0;
				}
			}
			else	// sql or sql->ldap
			{
				// group grants are now grants for the group addressbook and NOT grants for all its members,
				// therefor the param false!
				$grants = $GLOBALS['egw']->acl->get_grants($contact_app,false,$user);
			}
			// add grants for accounts: if account_selection not in ('none','groupmembers'): everyone has read access,
			// if he has not set the hide_accounts preference
			// ToDo: be more specific for 'groupmembers', they should be able to see the groupmembers
			if (!in_array($preferences['common']['account_selection'], array('none','groupmembers')))
			{
				$grants[0] = Api\Acl::READ;
			}
			// add account grants for admins (only for current user!)
			if ($user == $this->user && $this->is_admin())	// admin rights can be limited by ACL!
			{
				$grants[0] = Api\Acl::READ;	// admins always have read-access
				if (!$GLOBALS['egw']->acl->check('account_access',16,'admin')) $grants[0] |= Api\Acl::EDIT;
				if (!$GLOBALS['egw']->acl->check('account_access',4,'admin'))  $grants[0] |= Api\Acl::ADD;
				if (!$GLOBALS['egw']->acl->check('account_access',32,'admin')) $grants[0] |= Api\Acl::DELETE;
			}
			// allow certain groups to edit contact-data of accounts
			if (self::allow_account_edit($user))
			{
				$grants[0] |= Api\Acl::READ|Api\Acl::EDIT;
			}
		}
		// no user, eg. setup or not logged in, allow read access to accounts
		else
		{
			$grants = [0 => Api\Acl::READ];
		}
		//error_log(__METHOD__."($user, '$contact_app') returning ".array2string($grants));
		return $grants;
	}

	/**
	 * Check if the user is an admin (can unconditionally edit accounts)
	 *
	 * We check now the admin ACL for edit users, as the admin app does it for editing accounts.
	 *
	 * @param array $contact =null for future use, where admins might not be admins for all accounts
	 * @return boolean
	 */
	function is_admin($contact=null)
	{
		unset($contact);	// not (yet) used

		return isset($GLOBALS['egw_info']['user']['apps']['admin']) && !$GLOBALS['egw']->acl->check('account_access',16,'admin');
	}

	/**
	 * Check if current user is in a group, which is allowed to edit accounts
	 *
	 * @param int $user =null default $this->user
	 * @return boolean
	 */
	function allow_account_edit($user=null)
	{
		return $GLOBALS['egw_info']['server']['allow_account_edit'] &&
			array_intersect($GLOBALS['egw_info']['server']['allow_account_edit'],
				$GLOBALS['egw']->accounts->memberships($user ? $user : $this->user, true));
	}

	/**
	 * Read all customfields of the given id's
	 *
	 * @param int|array $ids
	 * @param array $field_names =null custom fields to read, default all
	 * @return array id => name => value
	 */
	function read_customfields($ids,$field_names=null)
	{
		return $this->soextra->read_customfields($ids,$field_names);
	}

	/**
	 * Read all distributionlists of the given id's
	 *
	 * @param int|array $ids
	 * @return array id => name => value
	 */
	function read_distributionlist($ids, $dl_allowed=array())
	{
		if ($this->contact_repository == 'ldap')
		{
			return array();	// ldap does not support distributionlists
		}
		foreach($ids as $key => $id)
		{
			if (!is_numeric($id)) unset($ids[$key]);
		}
		if (!$ids) return array();	// nothing to do, eg. all these contacts are in ldap
		$fields = array();
		$filter[$this->distri_id]=$ids;
		if (count($dl_allowed)) $filter[$this->distri_key]=$dl_allowed;
		$distri_view = str_replace(') d_view',' and '.$this->distri_id.' in ('.implode(',',$ids).')) d_view',$this->distributionlist_view);
		#_debug_array($this->distributionlist_tabledef);
		foreach($this->db->select($distri_view, '*', $filter, __LINE__, __FILE__,
			false, 'ORDER BY '.$this->distri_id, false, 0, '', $this->distributionlist_tabledef) as $row)
		{
			if ((isset($row[$this->distri_id])&&strlen($row[$this->distri_value])>0))
			{
				$fields[$row[$this->distri_id]][$row[$this->distri_key]] = $row[$this->distri_value].' ('.
					Api\Accounts::username($row[$this->distri_owner]).')';
			}
		}
		return $fields;
	}

	/**
	 * changes the data from the db-format to your work-format
	 *
	 * it gets called everytime when data is read from the db
	 * This function needs to be reimplemented in the derived class
	 *
	 * @param array $data
	 */
	function db2data($data)
	{
		return $data;
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * It gets called everytime when data gets writen into db or on keys for db-searches
	 * this needs to be reimplemented in the derived class
	 *
	 * @param array $data
	 */
	function data2db($data)
	{
		return $data;
	}

	/**
	* deletes contact entry including custom fields
	*
	* @param mixed $contact array with id or just the id
	* @param int $check_etag =null
	* @return boolean|int true on success or false on failiure, 0 if etag does not match
	*/
	function delete($contact,$check_etag=null)
	{
		if (is_array($contact)) $contact = $contact['id'];

		$where = array('id' => $contact);
		if ($check_etag) $where['etag'] = $check_etag;

		// delete mainfields
		if ($this->somain->delete($where))
		{
			// delete customfields, can return 0 if there are no customfields
			if(!($this->somain instanceof Sql))
			{
				$this->soextra->delete_customfields(array($this->extra_id => $contact));
			}

			// delete from distribution list(s)
			$this->remove_from_list($contact);

			if ($this->contact_repository == 'sql-ldap')
			{
				if ($contact['account_id'])
				{
					// LDAP uses the uid attributes for the contact-id (dn),
					// which need to be the account_lid for accounts!
					$contact['id'] = $GLOBALS['egw']->accounts->id2name($contact['account_id']);
				}
				(new Ldap())->delete($contact);
			}
			return true;
		}
		return $check_etag ? 0 : false;		// if etag given, we return 0 on failure, thought it could also mean the whole contact does not exist
	}

	/**
	* saves contact data including custom fields
	*
	* @param array &$contact contact data from etemplate::exec
	* @return bool false on success, errornumber on failure
	*/
	function save(&$contact)
	{
		// save mainfields
		if ($contact['id'] && $this->contact_repository != $this->account_repository && is_object($this->so_accounts) &&
			($this->contact_repository == 'sql' && !is_numeric($contact['id']) ||
			 $this->contact_repository == 'ldap' && is_numeric($contact['id'])))
		{
			$this->so_accounts->data = $this->data2db($contact);
			$error_nr = $this->so_accounts->save();
			$contact['id'] = $this->so_accounts->data['id'];
		}
		else
		{
			// contact_repository sql-ldap (accounts in ldap) the person_id is the uid (account_lid)
			// for the sql write here we need to find out the existing contact_id
			if ($this->contact_repository == 'sql-ldap' && $contact['id'] && !is_numeric($contact['id']) &&
				$contact['account_id'] && ($old = $this->somain->read(array('account_id' => $contact['account_id']))))
			{
				$contact['id'] = $old['id'];
			}
			$this->somain->data = $this->data2db($contact);

			if (!($error_nr = $this->somain->save()))
			{
				$contact['id'] = $this->somain->data['id'];
				$contact['uid'] = $this->somain->data['uid'];
				$contact['etag'] = $this->somain->data['etag'];
				$contact['files'] = $this->somain->data['files'];

				if ($this->contact_repository == 'sql-ldap')
				{
					$data = $this->somain->data;
					if ($contact['account_id'])
					{
						// LDAP uses the uid attributes for the contact-id (dn),
						// which need to be the account_lid for accounts!
						$data['id'] = $GLOBALS['egw']->accounts->id2name($contact['account_id']);
					}
					$error_nr = (new Ldap())->save($data);
				}
			}
		}
		if($error_nr) return $error_nr;

		return false;	// no error
	}

	/**
	 * reads contact data including custom fields
	 *
	 * @param int|string $contact_id contact_id or 'a'.account_id
	 * @return array|boolean data if row could be retrived else False
	*/
	function read($contact_id)
	{
		if (empty($contact_id))
		{
			return false;	// no need to pass to backend, will fail anyway
		}
		if (!is_array($contact_id) && substr($contact_id,0,8) == 'account:')
		{
			$contact_id = array('account_id' => (int) substr($contact_id,8));
		}
		// read main data
		$backend = $this->get_backend($contact_id);
		if (!($contact = $backend->read($contact_id)))
		{
			return $contact;
		}
		$dl_list=$this->read_distributionlist(array($contact['id']));
		if (count($dl_list)) $contact['distrib_lists']=implode("\n",$dl_list[$contact['id']]);
		return $this->db2data($contact);
	}

	/**
	 * @param array $ids
	 * @param ?boolean $deleted false: no deleted, true: only deleted, null: both
	 * @return array contact_id => array of array with sharing info
	 */
	function read_shared(array $ids, $deleted=false)
	{
		$contacts = [];
		if (method_exists($backend = $this->get_backend($ids[0]), 'read_shared'))
		{
			foreach($backend->read_shared($ids, $deleted) as $shared)
			{
				$contacts[$shared['contact_id']][] = $shared;
			}
		}
		return $contacts;
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array|string $criteria array of key and data cols, OR string to search over all standard search fields
	 * @param boolean|string $only_keys =true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 *  $filter['cols_to_search'] limit search columns to given columns, otherwise $this->columns_to_search is used
	 * @param string $join ='' sql to do a join (only used by sql backend!), eg. " RIGHT JOIN egw_accounts USING(account_id)"
	 * @param boolean $ignore_acl =false true: no acl check
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='', $ignore_acl=false)
	{
		//error_log(__METHOD__.'('.array2string($criteria,true).','.array2string($only_keys).",'$order_by','$extra_cols','$wildcard','$empty','$op',".array2string($start).','.array2string($filter,true).",'$join')");

		// Handle 'None' country option
		if(is_array($filter) && $filter['adr_one_countrycode'] == '-custom-')
		{
			$filter[] = 'adr_one_countrycode IS NULL';
			unset($filter['adr_one_countrycode']);
		}
		// Hide deleted items unless type is specifically deleted
		if(!is_array($filter)) $filter = $filter ? (array) $filter : array();

		if (isset($filter['cols_to_search']))
		{
			$cols_to_search = $filter['cols_to_search'];
			unset($filter['cols_to_search']);
		}

		// if no tid set or tid==='' do NOT return deleted entries ($tid === null returns all entries incl. deleted)
		if(!array_key_exists('tid', $filter) || $filter['tid'] === '')
		{
			if ($join && strpos($join,'RIGHT JOIN') !== false)	// used eg. to search for groups
			{
				$filter[] = '(contact_tid != \'' . self::DELETED_TYPE . '\' OR contact_tid IS NULL)';
			}
			else
			{
				$filter[] = 'contact_tid != \'' . self::DELETED_TYPE . '\'';
			}
		}
		elseif(is_null($filter['tid']))
		{
			unset($filter['tid']);	// return all entries incl. deleted
		}
		$backend = $this->get_backend(null, isset($filter['list']) && $filter['list'] < 0 ? 0 : $filter['owner']);
		// single string to search for --> create so_sql conformant search criterial for the standard search columns
		if ($criteria && !is_array($criteria))
		{
			$op = 'OR';
			$wildcard = '%';
			$search = $criteria;
			$criteria = array();

			if (isset($cols_to_search))
			{
				$cols = $cols_to_search;
			}
			elseif ($backend === $this->somain)
			{
				$cols = $this->columns_to_search;
			}
			else
			{
				$cols = $this->account_cols_to_search;
			}
			if($backend instanceof Sql)
			{
				// Keep a string, let the parent handle it
				$criteria = $search;

				foreach($cols as $key => &$col)
				{
					if($col != Sql::EXTRA_VALUE &&
						$col != Sql::EXTRA_TABLE.'.'.Sql::EXTRA_VALUE &&
						!array_key_exists($col, $backend->db_cols))
					{
						if(!($col = array_search($col, $backend->db_cols)))
						{
							// Can't search this column, it will error if we try
							unset($cols[$key]);
						}
					}
					if ($col=='contact_id') $col='egw_addressbook.contact_id';
				}

				$backend->columns_to_search = $cols;
			}
			else
			{
				foreach($cols as $col)
				{
					// remove from LDAP backend not understood use-AND-syntax
					$criteria[$col] = str_replace(' +',' ',$search);
				}
			}
		}
		if (is_array($criteria) && count($criteria))
		{
			$criteria = $this->data2db($criteria);
		}
		if (is_array($filter) && count($filter))
		{
			$filter = $this->data2db($filter);
		}
		else
		{
			$filter = $filter ? array($filter) : array();
		}
		// get the used backend for the search and call it's search method
		$rows = $backend->search($criteria, $only_keys, $order_by, $extra_cols,
			$wildcard, $empty, $op, $start, $filter, $join, false, $ignore_acl);

		$this->total = $backend->total;

		if ($rows)
		{
			foreach($rows as $n => $row)
			{
				$rows[$n] = $this->db2data($row);
			}

			// allow other apps to hook into search
			Api\Hooks::process(array(
				'hook_location' => 'contacts_search',
				'criteria'      => $criteria,
				'filter'        => $filter,
				'ignore_acl'    => $ignore_acl,
				'obj'           => $this,
				'rows'          => &$rows,
				'total'         => &$this->total,
			), array(), true);	// true = no permission check
		}
		return $rows;
	}

	/**
	 * Query organisations by given parameters
	 *
	 * @var array $param
	 * @var string $param[org_view] 'org_name', 'org_name,adr_one_location', 'org_name,org_unit' how to group
	 * @var int $param[owner] addressbook to search
	 * @var string $param[search] search pattern for org_name
	 * @var string $param[searchletter] letter the org_name need to start with
	 * @var int $param[start]
	 * @var int $param[num_rows]
	 * @var string $param[sort] ASC or DESC
	 * @return array or arrays with keys org_name,count and evtl. adr_one_location or org_unit
	 */
	function organisations($param)
	{
		if (!method_exists($this->somain,'organisations'))
		{
			$this->total = 0;
			return false;
		}
		if ($param['search'] && !is_array($param['search']))
		{
			$search = $param['search'];
			$param['search'] = array();
			if($this->somain instanceof Sql)
			{
				// Keep the string, let the parent deal with it
				$param['search'] = $search;
			}
			else
			{
				foreach($this->columns_to_search as $col)
				{
					if ($col != 'contact_value') $param['search'][$col] = $search;	// we dont search the customfields
				}
			}
		}
		if (is_array($param['search']) && count($param['search']))
		{
			$param['search'] = $this->data2db($param['search']);
		}
		if(!array_key_exists('tid', $param['col_filter']) || $param['col_filter']['tid'] === '')
		{
			$param['col_filter'][] = 'contact_tid != \'' . self::DELETED_TYPE . '\'';
		}
		elseif(is_null($param['col_filter']['tid']))
		{
			unset($param['col_filter']['tid']);	// return all entries incl. deleted
		}

		$rows = $this->somain->organisations($param);
		$this->total = $this->somain->total;

		if (!$rows) return array();

		foreach($rows as $n => $row)
		{
			if (strpos($row['org_name'],'&')!==false) $row['org_name'] = str_replace('&','*AND*',$row['org_name']);
			$rows[$n]['id'] = 'org_name:'.$row['org_name'];
			foreach(array(
				'org_unit' => lang('departments'),
				'adr_one_locality' => lang('locations'),
			) as $by => $by_label)
			{
				if ($row[$by.'_count'] > 1)
				{
					$rows[$n][$by] = $row[$by.'_count'].' '.$by_label;
				}
				else
				{
					if (strpos($row[$by],'&')!==false) $row[$by] = str_replace('&','*AND*',$row[$by]);
					$rows[$n]['id'] .= '|||'.$by.':'.$row[$by];
				}
			}
		}
		return $rows;
	}

	/**
	 * Find contacts that appear to be duplicates
	 *
	 * @param Array $param
	 * @param string $param[org_view] 'org_name', 'org_name,adr_one_location', 'org_name,org_unit' how to group
	 * @param int $param[owner] addressbook to search
	 * @param string $param[search] search pattern for org_name
	 * @param string $param[searchletter] letter the org_name need to start with
	 * @param int $param[start]
	 * @param int $param[num_rows]
	 * @param string $param[sort] ASC or DESC
	 *
	 * @return array of arrays
	 */
	public function duplicates($param)
	{
		if (!method_exists($this->somain,'duplicates'))
		{
			$this->total = 0;
			return false;
		}
		if ($param['search'] && !is_array($param['search']))
		{
			$search = $param['search'];
			$param['search'] = array();
			if($this->somain instanceof Sql)
			{
				// Keep the string, let the parent deal with it
				$param['search'] = $search;
			}
			else
			{
				foreach($this->columns_to_search as $col)
				{
					// we don't search the customfields
					if ($col != 'contact_value') $param['search'][$col] = $search;
				}
			}
		}
		if (is_array($param['search']) && count($param['search']))
		{
			$param['search'] = $this->data2db($param['search']);
		}
		if(!array_key_exists('tid', $param['col_filter']) || $param['col_filter']['tid'] === '')
		{
			$param['col_filter'][] = $this->somain->table_name.'.contact_tid != \'' . self::DELETED_TYPE . '\'';
		}
		elseif(is_null($param['col_filter']['tid']))
		{
			// return all entries including deleted
			unset($param['col_filter']['tid']);
		}
		if(array_key_exists('filter', $param) && $param['filter'] != '')
		{
			$param['owner'] = $param['filter'];
			unset($param['filter']);
		}
		if(array_key_exists('owner', $param['col_filter']) && $param['col_filter']['owner'] != '')
		{
			$param['owner'] = $param['col_filter']['owner'];
			unset($param['col_filter']['owner']);
		}


		$rows = $this->somain->duplicates($param);
		$this->total = $this->somain->total;

		if (!$rows) return array();

		foreach($rows as $n => $row)
		{
			$rows[$n]['id'] = 'duplicate:';
			foreach(array_keys(static::$duplicate_fields) as $by)
			{
				if (strpos($row[$by],'&')!==false) $row[$by] = str_replace('&','*AND*',$row[$by]);
				if($row[$by])
				{
					$rows[$n]['id'] .= '|||'.$by.':'.$row[$by];
				}
			}
		}
		return $rows;
	}

 	/**
	 * gets all contact fields from database
	 *
	 * @return array of (internal) field-names
	 */
	function get_contact_columns()
	{
		$fields = $this->get_fields('all');
		foreach (array_keys((array)$this->customfields) as $cfield)
		{
			$fields[] = '#'.$cfield;
		}
		return $fields;
	}

	/**
	 * delete / move all contacts of an addressbook
	 *
	 * @param array $data
	 * @param int $data['account_id'] owner to change
	 * @param int $data['new_owner']  new owner or 0 for delete
	 */
	function deleteaccount($data)
	{
		$account_id = $data['account_id'];
		$new_owner =  $data['new_owner'];

		if (!$new_owner)
		{
			$this->somain->delete(array('owner' => $account_id));	// so_sql_cf::delete() takes care of cfs too

			if (method_exists($this->somain, 'get_lists') &&
				($lists = $this->somain->get_lists($account_id)))
			{
				$this->somain->delete_list(array_keys($lists));
			}
		}
		else
		{
			$this->somain->change_owner($account_id,$new_owner);
		}
	}

	/**
	 * return the backend, to be used for the given $contact_id
	 *
	 * @param array|string|int $keys =null
	 * @param int $owner =null account_id of owner or 0 for accounts
	 * @return Sql|Ldap|Ads|Univention
	 */
	function get_backend($keys=null,$owner=null)
	{
		if ($owner === '') $owner = null;

		$contact_id = !is_array($keys) ? $keys :
			(isset($keys['id']) ? $keys['id'] : $keys['contact_id']);

		if ($this->contact_repository != $this->account_repository && is_object($this->so_accounts) &&
			(!is_null($owner) && !$owner || is_array($keys) && $keys['account_id'] || !is_null($contact_id) &&
			($this->contact_repository == 'sql' && (!is_numeric($contact_id) && !is_array($contact_id) )||
			 $this->contact_repository == 'ldap' && is_numeric($contact_id))))
		{
			return $this->so_accounts;
		}
		return $this->somain;
	}

	/**
	 * Returns the supported, all or unsupported fields of the backend (depends on owner or contact_id)
	 *
	 * @param sting $type ='all' 'supported', 'unsupported' or 'all'
	 * @param mixed $contact_id =null
	 * @param int $owner =null account_id of owner or 0 for accounts
	 * @return array with eGW contact field names
	 */
	function get_fields($type='all',$contact_id=null,$owner=null)
	{
		$def = $this->db->get_table_definitions('api','egw_addressbook');

		$all_fields = array();
		foreach(array_keys($def['fd']) as $field)
		{
			$all_fields[] = substr($field,0,8) == 'contact_' ? substr($field,8) : $field;
		}
		if ($type == 'all')
		{
			return $all_fields;
		}
		$backend = $this->get_backend($contact_id,$owner);

		$supported_fields = method_exists($backend, 'supported_fields') ? $backend->supported_fields() : $all_fields;

		if ($type == 'supported')
		{
			return $supported_fields;
		}
		return array_diff($all_fields,$supported_fields);
	}

	/**
	 * Migrates an SQL contact storage to LDAP, SQL-LDAP or back to SQL
	 *
	 * @param string|array $type comma-separated list or array of:
	 *  - "contacts" contacts to ldap
	 *  - "accounts" accounts to ldap
	 *  - "accounts-back" accounts back to sql (for sql-ldap!)
	 *  - "sql" contacts and accounts to sql
	 *  - "accounts-back-ads" accounts back from ads to sql
	 */
	function migrate2ldap($type)
	{
		//error_log(__METHOD__."(".array2string($type).")");
		$sql_contacts  = new Sql();
		if ($type == 'accounts-back-ads')
		{
			$ldap_contacts = new Ads();
		}
		else
		{
			// we need an admin connection
			$ds = $GLOBALS['egw']->ldap->ldapConnect();
			$ldap_contacts = new Ldap(null, $ds);
		}

		if (!is_array($type)) $type = explode(',', $type);

		$start = $n = 0;
		$num = 100;

		// direction SQL --> LDAP, either only accounts, or only contacts or both
		if (($do = array_intersect($type, array('contacts', 'accounts'))))
		{
			$filter = count($do) == 2 ? null :
				array($do[0] == 'contacts' ? 'contact_owner != 0' : 'contact_owner = 0');

			while (($contacts = $sql_contacts->search(false,false,'n_family,n_given','','',false,'AND',
				array($start,$num),$filter)))
			{
				foreach($contacts as $contact)
				{
					if ($contact['account_id']) $contact['id'] = $GLOBALS['egw']->accounts->id2name($contact['account_id']);

					$ldap_contacts->data = $contact;
					$n++;
					if (!($err = $ldap_contacts->save()))
					{
						echo '<p style="margin: 0px;">'.$n.': '.$contact['n_fn'].
							($contact['org_name'] ? ' ('.$contact['org_name'].')' : '')." --> LDAP</p>\n";
					}
					else
					{
						echo '<p style="margin: 0px; color: red;">'.$n.': '.$contact['n_fn'].
							($contact['org_name'] ? ' ('.$contact['org_name'].')' : '').': '.$err."</p>\n";
					}
				}
				$start += $num;
			}
		}
		// direction LDAP --> SQL: either "sql" (contacts and accounts) or "accounts-back" (only accounts)
		if (($do = array_intersect(array('accounts-back','sql'), $type)))
		{
			//error_log(__METHOD__."(".array2string($type).") do=".array2string($type));
			$filter = in_array('sql', $do) ? null : array('owner' => 0);

			foreach($ldap_contacts->search(false,false,'n_family,n_given','','',false,'AND',
				false, $filter) as $contact)
			{
				//error_log(__METHOD__."(".array2string($type).") do=".array2string($type)." migrating ".array2string($contact));
				if ($contact['jpegphoto'])	// photo is NOT read by LDAP backend on search, need to do an extra read
				{
					$contact = $ldap_contacts->read($contact['id']);
				}
				$old_contact_id = $contact['id'];
				unset($contact['id']);	// ldap uid/account_lid
				if ($contact['account_id'] && ($old = $sql_contacts->read(array('account_id' => $contact['account_id']))))
				{
					$contact['id'] = $old['id'];
				}
				$sql_contacts->data = $contact;

				$n++;
				if (!($err = $sql_contacts->save()))
				{
					echo '<p style="margin: 0px;">'.$n.': '.$contact['n_fn'].
						($contact['org_name'] ? ' ('.$contact['org_name'].')' : '')." --> SQL (".
						($contact['owner']?lang('User'):lang('Contact')).")<br>\n";

					$new_contact_id = $sql_contacts->data['id'];
					echo "&nbsp;&nbsp;&nbsp;&nbsp;" . $old_contact_id . " --> " . $new_contact_id . " / ";

					$this->db->update('egw_links',array(
						'link_id1' => $new_contact_id,
					),array(
						'link_app1' => 'addressbook',
						'link_id1' => $old_contact_id
					),__LINE__,__FILE__);

					$this->db->update('egw_links',array(
						'link_id2' => $new_contact_id,
					),array(
						'link_app2' => 'addressbook',
						'link_id2' => $old_contact_id
					),__LINE__,__FILE__);
					echo "</p>\n";
				}
				else
				{
					echo '<p style="margin: 0px; color: red;">'.$n.': '.$contact['n_fn'].
						($contact['org_name'] ? ' ('.$contact['org_name'].')' : '').': '.$err."</p>\n";
				}
			}
		}
	}

	/**
	 * Get the availible distribution lists for a user
	 *
	 * @param int $required =Api\Acl::READ required rights on the list or multiple rights or'ed together,
	 * 	to return only lists fullfilling all the given rights
	 * @param string $extra_labels =null first labels if given (already translated)
	 * @return array with id => label pairs or false if backend does not support lists
	 */
	function get_lists($required=Api\Acl::READ,$extra_labels=null)
	{
		$lists = is_array($extra_labels) ? $extra_labels : array();

		if (method_exists($this->somain,'get_lists'))
		{
			$uids = array();
			foreach($this->grants as $uid => $rights)
			{
				// only requests groups / list in accounts addressbook for read
				if (!$uid && $required != Api\Acl::READ) continue;

				if (($rights & $required) == $required)
				{
					$uids[] = $uid;
				}
			}

			foreach($this->somain->get_lists($uids) as $list_id => $data)
			{
				$lists[$list_id] = $data['list_name'];
				if ($data['list_owner'] != $this->user)
				{
					$lists[$list_id] .= ' ('.Api\Accounts::username($data['list_owner']).')';
				}
			}
		}

		// add groups for all backends, if accounts addressbook is not hidden &
		// preference has not turned them off
		if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] !== '1' &&
				$GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_groups_as_lists'] == '0')
		{
			foreach($GLOBALS['egw']->accounts->search(array(
				'type' => 'groups'
			)) as $account_id => $group)
			{
				$lists[(string)$account_id] = Api\Accounts::format_username($group['account_lid'], '', '', $account_id);
			}
		}

		return $lists;
	}

	/**
	 * Get the availible distribution lists for givens users and groups
	 *
	 * @param array $keys column-name => value(s) pairs, eg. array('list_uid'=>$uid)
	 * @param string $member_attr ='contact_uid' null: no members, 'contact_uid', 'contact_id', 'caldav_name' return members as that attribute
	 * @param boolean $limit_in_ab =false if true only return members from the same owners addressbook
	 * @return array with list_id => array(list_id,list_name,list_owner,...) pairs
	 */
	function read_lists($keys,$member_attr=null,$limit_in_ab=false)
	{
		$backend = (string)$limit_in_ab === '0' && $this->so_accounts ? $this->so_accounts : $this->somain;
		if (!method_exists($backend, 'get_lists')) return false;

		return $backend->get_lists($keys,null,$member_attr,$limit_in_ab);
	}

	/**
	 * Adds / updates a distribution list
	 *
	 * @param string|array $keys list-name or array with column-name => value pairs to specify the list
	 * @param int $owner user- or group-id
	 * @param array $contacts =array() contacts to add (only for not yet existing lists!)
	 * @param array &$data=array() values for keys 'list_uid', 'list_carddav_name', 'list_name'
	 * @return int|boolean integer list_id or false on error
	 */
	function add_list($keys,$owner,$contacts=array(),array &$data=array())
	{
		$backend = (string)$owner === '0' && $this->so_accounts ? $this->so_accounts : $this->somain;
		if (!method_exists($backend, 'add_list')) return false;

		return $backend->add_list($keys,$owner,$contacts,$data);
	}

	/**
	 * Adds contact(s) to a distribution list
	 *
	 * @param int|array $contact contact_id(s)
	 * @param int $list list-id
	 * @param array $existing =null array of existing contact-id(s) of list, to not reread it, eg. array()
	 * @return false on error
	 */
	function add2list($contact,$list,array $existing=null)
	{
		if (!method_exists($this->somain,'add2list')) return false;

		return $this->somain->add2list($contact,$list,$existing);
	}

	/**
	 * Removes one contact from distribution list(s)
	 *
	 * @param int|array $contact contact_id(s)
	 * @param int $list =null list-id or null to remove from all lists
	 * @return false on error
	 */
	function remove_from_list($contact,$list=null)
	{
		if (!method_exists($this->somain,'remove_from_list')) return false;

		return $this->somain->remove_from_list($contact,$list);
	}

	/**
	 * Deletes a distribution list (incl. it's members)
	 *
	 * @param int|array $list list_id(s)
	 * @return number of members deleted or false if list does not exist
	 */
	function delete_list($list)
	{
		if (!method_exists($this->somain,'delete_list')) return false;

		return $this->somain->delete_list($list);
	}

	/**
	 * Read data of a distribution list
	 *
	 * @param int $list list_id
	 * @return array of data or false if list does not exist
	 */
	function read_list($list)
	{
		if (!method_exists($this->somain,'read_list')) return false;

		return $this->somain->read_list($list);
	}

	/**
	 * Check if distribution lists are availible for a given addressbook
	 *
	 * @param int|string $owner ='' addressbook (eg. 0 = accounts), default '' = "all" addressbook (uses the main backend)
	 * @return boolean
	 */
	function lists_available($owner='')
	{
		$backend =& $this->get_backend(null,$owner);

		return method_exists($backend,'read_list');
	}

	/**
	 * Get ctag (max list_modified as timestamp) for lists
	 *
	 * @param int|array $owner =null null for all lists user has access too
	 * @return int
	 */
	function lists_ctag($owner=null)
	{
		if (!method_exists($this->somain,'lists_ctag')) return 0;

		return $this->somain->lists_ctag($owner);
	}
}
