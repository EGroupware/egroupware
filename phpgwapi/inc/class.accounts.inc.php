<?php
/**
 * EGroupware API - accounts
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> complete rewrite in 6/2006 and earlier modifications
 *
 * Implements the (now depricated) interfaces on the former accounts class written by
 * Joseph Engo <jengo@phpgroupware.org> and Bettina Gille <ceb@phpgroupware.org>
 * Copyright (C) 2000 - 2002 Joseph Engo, Copyright (C) 2003 Joseph Engo, Bettina Gille
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 * @access public
 * @version $Id$
 */

use EGroupware\Api;

/**
 * API - accounts
 *
 * This class uses a backend class (at them moment SQL or LDAP) and implements some
 * caching on to top of the backend functions:
 *
 * a) instance-wide account-data cache queried by account_id including also members(hips)
 *    implemented by self::cache_read($account_id) and self::cache_invalidate($account_ids)
 *
 * b) session based cache for search, split_accounts and name2id
 *    implemented by self::setup_cache() and self::cache_invalidate()
 *
 * The backend only implements the read, save, delete, name2id and the {set_}members{hips} methods.
 * The account class implements all other (eg. name2id, id2name) functions on top of these.
 *
 * read and search return timestamps (account_(created|modified|lastlogin) in server-time!
 */
class accounts extends Api\Accounts
{
	var $xmlrpc_methods = array(
		array(
			'name'        => 'search',
			'description' => 'Returns a list of accounts and/or groups'
		),
		array(
			'name'        => 'name2id',
			'description' => 'Cross reference account_lid with account_id'
		),
		array(
			'name'        => 'id2name',
			'description' => 'Cross reference account_id with account_lid'
		),
		array(
			'name'        => 'get_list',
			'description' => 'Depricated: use search. Returns a list of accounts and/or groups'
		),
	);

	/**
	 * Depricated: Account this class was instanciated for
	 *
	 * @deprecated dont use this in new code, always explcitly specify the account to use
	 * @var int account_id
	 */
	var $account_id;
	/**
	 * Depricated: Account data of $this->account_id
	 *
	 * @deprecated dont use this in new code, store the data in your own code
	 * @var array
	 */
	var $data;

	/**
	 * Keys for which both versions with 'account_' prefix and without (depricated!) can be used, if requested.
	 * Migrate your code to always use the 'account_' prefix!!!
	 *
	 * @var array
	 */
	var $depricated_names = array('firstname','lastname','fullname','email','type',
		'status','expires','lastlogin','lastloginfrom','lastpasswd_change');

	/**
	 * Constructor
	 *
	 * @param string|array $backend =null string with backend 'sql'|'ldap', or whole config array, default read from global egw_info
	 */
	public function __construct($backend=null)
	{
		if (is_numeric($backend))	// depricated use with account_id
		{
			if ((int)$backend) $this->setAccountId((int)$backend);
			$backend = null;
		}
		parent::__construct();
	}

	/**
	 * set the accountId
	 *
	 * @param int $accountId
	 * @deprecated
	 */
    function setAccountId($accountId)
    {
        if($accountId && is_numeric($accountId))
        {
            $this->account_id = (int)$accountId;
        }
    }

	/**
	 * @deprecated not used any more, as static cache is a reference to the session
	 */
	function save_session_cache()
	{

	}

	/**
	 * Depricated functions of the old accounts class.
	 *
	 * Do NOT use them in new code, they will be removed after the next major release!!!
	 */

	/**
	 * Reads the data of the account this class is instanciated for
	 *
	 * @deprecated use read of $GLOBALS['egw']->accounts and not own instances of the accounts class
	 * @return array with the internal data
	 */
	function read_repository()
	{
		return $this->data = $this->account_id ? $this->read($this->account_id,true) : array();
	}

	/**
	 * saves the account-data in the internal data-structure of this class to the repository
	 *
	 * @deprecated use save of $GLOBALS['egw']->accounts and not own instances of the accounts class
	 */
	function save_repository()
	{
		$this->save($this->data,true);
	}

	/**
	 * Searches / lists accounts: users and/or groups
	 *
	 * @deprecated use search
	 */
	function get_list($_type='both',$start = null,$sort = '', $order = '', $query = '', $offset = null,$query_type='')
	{
		if (is_array($_type))	// XML-RPC
		{
			return array_values($this->search($_type));
		}
		return array_values($this->search(array(
			'type'       => $_type,
			'start'      => $start,
			'order'      => $order,
			'sort'       => $sort,
			'query'      => $query,
			'offset'     => $offset,
			'query_type' => $query_type ,
		)));
	}

	/**
	 * Create a new account with the given $account_info
	 *
	 * @deprecated use save
	 * @param array $account_info account data for the new account
	 * @param booelan $default_prefs =true has no meaning any more, as we use "real" default prefs since 1.0
	 * @return int new nummeric account-id
	 */
	function create($account_info,$default_prefs=True)
	{
		unset($default_prefs);	// not used, but required by function signature
		return $this->save($account_info);
	}

	/**
	 * copies the given $data into the internal array $this->data
	 *
	 * @deprecated store data in your own code and use save to save it
	 * @param array $data array with account data
	 * @return array $this->data = $data
	 */
	function update_data($data)
	{
		return $this->data = $data;
	}

	/**
	 * Get all memberships of an account $accountid / groups the account is a member off
	 *
	 * @deprecated use memberships() which account_id => account_lid pairs
	 * @param int|string $_accountid ='' numeric account-id or alphanum. account-lid,
	 *	default account of the user of this session
	 * @return array or arrays with keys 'account_id' and 'account_name' for the groups $accountid is a member of
	 */
	function membership($_accountid = '')
	{
		$accountid = get_account_id($_accountid);

		if (!($memberships = $this->memberships($accountid)))
		{
			return $memberships;
		}
		$old = array();
		foreach($memberships as $id => $lid)
		{
			$old[] = array('account_id' => $id, 'account_name' => $lid);
		}
		return $old;
	}

	/**
	 * Get all members of the group $accountid
	 *
	 * @deprecated use members which returns acount_id => account_lid pairs
	 * @param int|string $accountid ='' numeric account-id or alphanum. account-lid,
	 *	default account of the user of this session
	 * @return array of arrays with keys 'account_id' and 'account_name'
	 */
	function member($accountid)
	{
		if (!($members = $this->members($accountid)))
		{
			return $members;
		}
		$old = array();
		foreach($members as $uid => $lid)
		{
			$old[] = array('account_id' => $uid, 'account_name' => $lid);
		}
		return $old;
	}

	/**
	 * phpGW compatibility function, better use split_accounts
	 *
	 * @deprecated  use split_accounts
	 */
	function return_members($accounts)
	{
		$arr = $this->split_accounts($accounts);

		return array(
			'users'  => $arr['accounts'],
			'groups' => $arr['groups'],
		);
	}


	/**
	 * Gets account-name (lid), firstname and lastname of an account $accountid
	 *
	 * @deprecated use read to read account data
	 * @param int|string $accountid ='' numeric account-id or alphanum. account-lid,
	 *	if !$accountid account of the user of this session
	 * @param string &$lid on return: alphanumeric account-name (lid)
	 * @param string &$fname on return: first name
	 * @param string &$lname on return: last name
	 * @return boolean true if $accountid was found, false otherwise
	 */
	function get_account_name($accountid,&$lid,&$fname,&$lname)
	{
		if (!($data = $this->read($accountid))) return false;

		$lid   = $data['account_lid'];
		$fname = $data['account_firstname'];
		$lname = $data['account_lastname'];

		if (empty($fname)) $fname = $lid;
		if (empty($lname)) $lname = $this->get_type($accountid) == 'g' ? lang('Group') : lang('user');

		return true;
	}

	/**
	 * Reads account-data for a given $account_id from the repository AND sets the class-vars with it
	 *
	 * Same effect as instanciating the class with that account, dont do it with $GLOBALS['egw']->account !!!
	 *
	 * @deprecated use read to read account data and store it in your own code
	 * @param int $account_id numeric account-id
	 * @return array with keys lid, firstname, lastname, fullname, type
	 */
	function get_account_data($account_id)
	{
		$this->account_id = $account_id;
		$this->read_repository();

		$data = array();
		$data[$this->data['account_id']]['lid']       = $this->data['account_lid'];
		$data[$this->data['account_id']]['firstname'] = $this->data['firstname'];
		$data[$this->data['account_id']]['lastname']  = $this->data['lastname'];
		$data[$this->data['account_id']]['fullname']  = $this->data['fullname'];
		$data[$this->data['account_id']]['type']      = $this->data['account_type'];

		return $data;
	}
}
