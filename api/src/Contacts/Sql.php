<?php
/**
 * EGroupware API: Contacts - SQL storage
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @subpackage contacts
 * @copyright (c) 2006-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Contacts;

use EGroupware\Api;

/**
 * Contacts - SQL storage
 */
class Sql extends Api\Storage
{
	/**
	 * name of custom fields table
	 *
	 * @var string
	 */
	var $account_repository = 'sql';
	var $contact_repository = 'sql';
	var $grants;

	/**
	 * join to show only active account (and not already expired ones)
	 */
	const ACCOUNT_ACTIVE_JOIN = ' LEFT JOIN egw_accounts ON egw_addressbook.account_id=egw_accounts.account_id ';
	/**
	 * filter to show only active account (and not already expired or deactived ones)
	 * UNIX_TIMESTAMP(NOW()) gets replaced with value of time() in the code!
	 */
	const ACOUNT_ACTIVE_FILTER = "(account_expires IS NULL OR account_expires = -1 OR account_expires > UNIX_TIMESTAMP(NOW())) AND (account_type IS NULL OR account_type!='u' OR account_status='A')";

	/**
	 * internal name of the id, gets mapped to uid
	 *
	 * @var string
	 */
	var $contacts_id='id';

	/**
	 * Name of the table for distribution lists
	 *
	 * @var string
	 */
	var $lists_table = 'egw_addressbook_lists';
	/**
	 * Name of the table with the members (contacts) of the distribution lists
	 *
	 * @var string
	 */
	var $ab2list_table = 'egw_addressbook2list';

	const EXTRA_TABLE = 'egw_addressbook_extra';
	const EXTRA_VALUE = 'contact_value';

	const SHARED_TABLE = 'egw_addressbook_shared';

	/**
	 * Constructor
	 *
	 * @param Api\Db $db =null
	 */
	function __construct(Api\Db $db=null)
	{
		parent::__construct('api', 'egw_addressbook', self::EXTRA_TABLE,
			'contact_', '_name', '_value', '_id', $db);

		$this->non_db_cols[] = 'jpegphoto'; // to get merge to merge it too and save can store it in filesystem

		// Get custom fields from addressbook instead of api
		$this->customfields = Api\Storage\Customfields::get('addressbook');

		if (!empty($GLOBALS['egw_info']['server']['account_repository']))
		{
			$this->account_repository = $GLOBALS['egw_info']['server']['account_repository'];
		}
		elseif (!empty($GLOBALS['egw_info']['server']['auth_type']))
		{
			$this->account_repository = $GLOBALS['egw_info']['server']['auth_type'];
		}
		if (!empty($GLOBALS['egw_info']['server']['contact_repository']))
		{
			$this->contact_repository = $GLOBALS['egw_info']['server']['contact_repository'];
		}
	}

	/**
	 * Query organisations by given parameters
	 *
	 * @var array $param
	 * @var string $param[org_view] 'org_name', 'org_name,adr_one_location', 'org_name,org_unit' how to group
	 * @var int $param[owner] addressbook to search
	 * @var string $param[search] search pattern for org_name
	 * @var string $param[searchletter] letter the org_name need to start with
	 * @var array $param[col_filter] filter
	 * @var string $param[search] or'ed search pattern
	 * @var array $param[advanced_search] indicator that advanced search is active
	 * @var string $param[op] (operator like AND or OR; will be passed when advanced search is active)
	 * @var string $param[wildcard] (wildcard like % or empty or not set (for no wildcard); will be passed when advanced search is active)
	 * @var int $param[start]
	 * @var int $param[num_rows]
	 * @var string $param[sort] ASC or DESC
	 * @return array or arrays with keys org_name,count and evtl. adr_one_location or org_unit
	 */
	function organisations($param)
	{
		$filter = is_array($param['col_filter']) ? $param['col_filter'] : array();
		$join = '';
		$op = 'OR';
		if (isset($param['op']) && !empty($param['op'])) $op = $param['op'];
		$advanced_search = false;
		if (isset($param['advanced_search']) && !empty($param['advanced_search'])) $advanced_search = true;
		$wildcard ='%';
		if ($advanced_search || (isset($param['wildcard']) && !empty($param['wildcard']))) $wildcard = ($param['wildcard']?$param['wildcard']:'');

		// fix cat_id filter to search in comma-separated multiple cats and return subcats
		if ($filter['cat_id'])
		{
			$filter[] = $this->_cat_filter($filter['cat_id']);
			unset($filter['cat_id']);
		}
		// add filter for read ACL in sql, if user is NOT the owner of the addressbook
		if ($param['owner'] && $param['owner'] == $GLOBALS['egw_info']['user']['account_id'])
		{
			$filter['owner'] = $param['owner'];
		}
		else
		{
			// we have no private grants in addressbook at the moment, they have then to be added here too
			if ($param['owner'])
			{
				if (!$this->grants[(int) $filter['owner']]) return false;	// we have no access to that addressbook

				$filter['owner'] = $param['owner'];
				$filter['private'] = 0;
			}
			else	// search all addressbooks, incl. accounts
			{
				if ($this->account_repository != 'sql' && $this->contact_repository != 'sql-ldap')
				{
					$filter[] = $this->table_name.'.contact_owner != 0';	// in case there have been accounts in sql previously
				}
				$filter[] = "(".$this->table_name.".contact_owner=".(int)$GLOBALS['egw_info']['user']['account_id'].
					(!$this->grants ? ')' :
					" OR contact_private=0 AND ".$this->table_name.".contact_owner IN (".
					implode(',',array_keys($this->grants))."))");
			}
			if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] !== 'none')
			{
				$join .= self::ACCOUNT_ACTIVE_JOIN;
				if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] === '0')
				{
					$filter[] = str_replace('UNIX_TIMESTAMP(NOW())',time(),self::ACOUNT_ACTIVE_FILTER);
				}
				else
				{
					$filter[] = 'egw_accounts.account_id IS NULL';
				}
			}
		}
		if ($param['searchletter'])
		{
			$filter[] = 'org_name '.$this->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$this->db->quote($param['searchletter'].'%');
		}
		else
		{
			$filter[] = "org_name != ''";// AND org_name IS NOT NULL";
		}
		if (isset($filter['list']))
		{
			if ($filter['list'] < 0)
			{
				$join .= " JOIN egw_acl ON $this->table_name.account_id=acl_account AND acl_appname='phpgw_group' AND ".
					$this->db->expression('egw_acl', array('acl_location' => $filter['list']));
			}
			else
			{
				$join .= " JOIN $this->ab2list_table ON $this->table_name.contact_id=$this->ab2list_table.contact_id AND ".
					$this->db->expression($this->ab2list_table, array('list_id' => $filter['list']));
			}
			unset($filter['list']);
		}
		$sort = $param['sort'] == 'DESC' ? 'DESC' : 'ASC';

		list(,$by) = explode(',',$param['org_view']);
		if (!$by)
		{
			$extra = array(
				'COUNT(DISTINCT egw_addressbook.contact_id) AS org_count',
				"COUNT(DISTINCT CASE WHEN org_unit IS NULL THEN '' ELSE org_unit END) AS org_unit_count",
				"COUNT(DISTINCT CASE WHEN adr_one_locality IS NULL THEN '' ELSE adr_one_locality END) AS adr_one_locality_count",
			);
			$append = "GROUP BY org_name ORDER BY org_name $sort";
		}
		else	// by adr_one_location or org_unit
		{
			// org total for more then one $by
			$by_expr = $by == 'org_unit_count' ? "COUNT(DISTINCT CASE WHEN org_unit IS NULL THEN '' ELSE org_unit END)" :
				"COUNT(DISTINCT CASE WHEN adr_one_locality IS NULL THEN '' ELSE adr_one_locality END)";
			parent::search($param['search'],array('org_name'),
				"GROUP BY org_name HAVING $by_expr > 1 ORDER BY org_name $sort", array(
				"NULL AS $by",
				'1 AS is_main',
				'COUNT(DISTINCT egw_addressbook.contact_id) AS org_count',
				"COUNT(DISTINCT CASE WHEN org_unit IS NULL THEN '' ELSE org_unit END) AS org_unit_count",
				"COUNT(DISTINCT CASE WHEN adr_one_locality IS NULL THEN '' ELSE adr_one_locality END) AS adr_one_locality_count",
			),$wildcard,false,$op/*'OR'*/,'UNION',$filter,$join);
			// org by location
			parent::search($param['search'],array('org_name'),
				"GROUP BY org_name,$by ORDER BY org_name $sort,$by $sort", array(
				"CASE WHEN $by IS NULL THEN '' ELSE $by END AS $by",
				'0 AS is_main',
				'COUNT(DISTINCT egw_addressbook.contact_id) AS org_count',
				"COUNT(DISTINCT CASE WHEN org_unit IS NULL THEN '' ELSE org_unit END) AS org_unit_count",
				"COUNT(DISTINCT CASE WHEN adr_one_locality IS NULL THEN '' ELSE adr_one_locality END) AS adr_one_locality_count",
			),$wildcard,false,$op/*'OR'*/,'UNION',$filter,$join);
			$append = "ORDER BY org_name $sort,is_main DESC,$by $sort";
		}
		$rows = parent::search($param['search'],array('org_name'),$append,$extra,$wildcard,false,$op/*'OR'*/,
			array($param['start'],$param['num_rows']),$filter,$join);

		if (!$rows) return false;

		// query the values for *_count == 1, to display them instead
		$filter['org_name'] = $orgs = array();
		foreach($rows as $row)
		{
			if ($row['org_unit_count'] == 1 || $row['adr_one_locality_count'] == 1)
			{
				$filter['org_name'][$row['org_name']] = $row['org_name'];	// use as key too to have every org only once
			}
			$org_key = $row['org_name'].($by ? '|||'.($row[$by] || $row[$by.'_count']==1 ? $row[$by] : '|||') : '');
			$row['group_count'] = $row['org_count'];
			$orgs[$org_key] = $row;
		}
		unset($rows);

		if (count($filter['org_name']))
		{
			foreach((array) parent::search(null, array('org_name','org_unit','adr_one_locality'),
				'GROUP BY org_name,org_unit,adr_one_locality',
				'',$wildcard,false,$op/*'AND'*/,false,$filter,$join) as $row)
			{
				$org_key = $row['org_name'].($by ? '|||'.$row[$by] : '');
				if ($orgs[$org_key]['org_unit_count'] == 1)
				{
					$orgs[$org_key]['org_unit'] = $row['org_unit'];
				}
				if ($orgs[$org_key]['adr_one_locality_count'] == 1)
				{
					$orgs[$org_key]['adr_one_locality'] = $row['adr_one_locality'];
				}
				if ($by && isset($orgs[$org_key = $row['org_name'].'||||||']))
				{
					if ($orgs[$org_key]['org_unit_count'] == 1)
					{
						$orgs[$org_key]['org_unit'] = $row['org_unit'];
					}
					if ($orgs[$org_key]['adr_one_locality_count'] == 1)
					{
						$orgs[$org_key]['adr_one_locality'] = $row['adr_one_locality'];
					}
				}
			}
		}
		return array_values($orgs);
	}


	/**
	 * Query for duplicate contacts according to given parameters
	 *
	 * We join egw_addressbook to itself, and count how many fields match.  If
	 * enough of the fields we care about match, we count those two records as
	 * duplicates.
	 *
	 * @var array $param
	 * @var string $param[grouped_view] 'duplicate', 'duplicate,adr_one_location', 'duplicate,org_name' how to group
	 * @var int $param[owner] addressbook to search
	 * @var string $param[search] search pattern for org_name
	 * @var string $param[searchletter] letter the name need to start with
	 * @var array $param[col_filter] filter
	 * @var string $param[search] or'ed search pattern
	 * @var array $param[advanced_search] indicator that advanced search is active
	 * @var string $param[op] (operator like AND or OR; will be passed when advanced search is active)
	 * @var string $param[wildcard] (wildcard like % or empty or not set (for no wildcard); will be passed when advanced search is active)
	 * @var int $param[start]
	 * @var int $param[num_rows]
	 * @var string $param[sort] ASC or DESC
	 * @return array or arrays with keys org_name,count and evtl. adr_one_location or org_unit
	 */
	function duplicates($param)
	{
		$join = 'JOIN ' . $this->table_name . ' AS a2 ON ';
		$filter = $param['col_filter'];
		$op = 'OR';
		if (isset($param['op']) && !empty($param['op'])) $op = $param['op'];
		$advanced_search = false;
		if (isset($param['advanced_search']) && !empty($param['advanced_search'])) $advanced_search = true;
		$wildcard ='%';
		if ($advanced_search || (isset($param['wildcard']) && !empty($param['wildcard']))) $wildcard = ($param['wildcard']?$param['wildcard']:'');

		// fix cat_id filter to search in comma-separated multiple cats and return subcats
		if ($param['cat_id'])
		{
			$cat_filter = $this->_cat_filter($filter['cat_id']);
			$filter[] = str_replace('cat_id', $this->table_name . '.cat_id', $cat_filter);
			$join .= str_replace('cat_id', 'a2.cat_id', $cat_filter) . ' AND ';
			unset($filter['cat_id']);
		}
		if ($filter['tid'])
		{
			$filter[$this->table_name . '.contact_tid'] = $param['col_filter']['tid'];
			$join .= 'a2.contact_tid = ' . $this->db->quote($filter['tid']) . ' AND ';
			unset($filter['tid']);
		}
		else
		{
			$join .= 'a2.contact_tid != \'D\' AND ';
		}
		// add filter for read ACL in sql, if user is NOT the owner of the addressbook
		if (array_key_exists('owner',$param) && $param['owner'] == $GLOBALS['egw_info']['user']['account_id'])
		{
			$filter[$this->table_name.'.contact_owner'] = $param['owner'];
			$join .= 'a2.contact_owner = ' . $this->db->quote($param['owner']) . ' AND ';
		}
		else
		{
			// we have no private grants in addressbook at the moment, they have then to be added here too
			if (array_key_exists('owner', $param))
			{
				if (!$this->grants[(int) $param['owner']]) return false;	// we have no access to that addressbook

				$filter[$this->table_name . '.contact_owner'] = $param['owner'];
				$filter[$this->table_name . '.contact_private'] = 0;
				$join .= 'a2.contact_owner = ' . $this->db->quote($param['owner']) . ' AND ';
				$join .= 'a2.contact_private = ' . $this->db->quote($filter['private']) . ' AND ';
			}
			else	// search all addressbooks, incl. accounts
			{
				if ($this->account_repository != 'sql' && $this->contact_repository != 'sql-ldap')
				{
					$filter[] = $this->table_name.'.contact_owner != 0';	// in case there have been accounts in sql previously
				}
				$filter[] = $access = "(".$this->table_name.".contact_owner=".(int)$GLOBALS['egw_info']['user']['account_id'].
					" OR {$this->table_name}.contact_private=0 AND ".$this->table_name.".contact_owner IN (".
					implode(',',array_keys($this->grants))."))";
				$join .= str_replace($this->table_name, 'a2', $access) . ' AND ';
			}
		}
		if ($param['searchletter'])
		{
			$filter[] = $this->table_name.'.n_fn '.$this->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$this->db->quote($param['searchletter'].'%');
		}
		$sort = $param['sort'] == 'DESC' ? 'DESC' : 'ASC';
		$group = $GLOBALS['egw_info']['user']['preferences']['addressbook']['duplicate_fields'] ?
				explode(',',$GLOBALS['egw_info']['user']['preferences']['addressbook']['duplicate_fields']):
				array('n_family', 'org_name', 'contact_email');
		$match_count = $GLOBALS['egw_info']['user']['preferences']['addressbook']['duplicate_threshold'] ?
				$GLOBALS['egw_info']['user']['preferences']['addressbook']['duplicate_threshold'] : 3;

		$columns = Array();
		$extra = Array();
		$order = in_array($param['order'], $group) ? $param['order'] : $group[0];
		$join .= $this->table_name .'.contact_id != a2.contact_id AND (';
		$join_fields = Array();
		foreach($group as $field)
		{
			$extra[] = "IF({$this->table_name}.$field = a2.$field, 1, 0)";
			$join_fields[] = $this->table_name . ".$field = a2.$field";
			$columns[] = "IF({$this->table_name}.$field = a2.$field, {$this->table_name}.$field, '') AS $field";
		}
		$extra = Array(
			implode('+', $extra) . ' AS match_count'
		);
		$join .= $this->db->column_data_implode(' OR ',$join_fields) . ')';
		if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] !== 'none')
		{
			if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] === '0')
			{
				$join .=' LEFT JOIN egw_accounts AS account_1 ON egw_addressbook.account_id=account_1.account_id ';
				$join .=' LEFT JOIN egw_accounts AS account_2 ON egw_addressbook.account_id=account_2.account_id ';
				$filter[] = str_replace(array('UNIX_TIMESTAMP(NOW())', 'account_'),array(time(),'account_1.account_'),self::ACOUNT_ACTIVE_FILTER);
				$filter[] = str_replace(array('UNIX_TIMESTAMP(NOW())', 'account_'),array(time(),'account_2.account_'),self::ACOUNT_ACTIVE_FILTER);
			}
			else
			{
				$filter[] = 'egw_addressbook.account_id IS NULL and a2.account_id IS NULL';
			}
		}
		$append = " HAVING match_count >= $match_count ORDER BY {$order} $sort, $this->table_name.contact_id";
		$columns[] = $this->table_name.'.contact_id AS contact_id';

		$criteria = array();
		if ($param['search'] && !is_array($param['search']))
		{
			$search_cols = array();
			foreach($group as $col)
			{
				$search_cols[] = $this->table_name . '.' . $col;
			}
			$search = $this->search2criteria($param['search'],$wildcard,$op, null, $search_cols);
			$criteria = array($search);
		}
		$query = $this->parse_search(array_merge($criteria, $filter), $wildcard, false, ' AND ');

		$sub_query = $this->db->select($this->table_name,
			'DISTINCT ' . implode(', ',array_merge($columns, $extra)),
			$query,
			False, False, 0, $append, False, -1,
			$join
		);

		$columns = implode(', ', $group);
		if ($this->db->Type == 'mysql' && (float)$this->db->ServerInfo['version'] >= 4.0)
		{
			$mysql_calc_rows = 'SQL_CALC_FOUND_ROWS ';
		}

		$rows = $this->db->query(
				"SELECT $mysql_calc_rows " . $columns. ', COUNT(contact_id) AS group_count' .
				' FROM (' . $sub_query . ') AS matches GROUP BY ' . implode(',',$group) .
				' HAVING group_count > 1 ORDER BY ' . $order,
				__LINE__, __FILE__, (int)$param['start'],$mysql_calc_rows ? (int)$param['num_rows'] : -1
		);

		// Go through rows and only return one for each pair/triplet/etc. of matches
		$dupes = array();
		foreach($rows as $key => $row)
		{
			$row['email'] = $row['contact_email'];
			$row['email_home'] = $row['contact_email_home'];
			$dupes[] = $this->db2data($row);
		}

		if ($mysql_calc_rows)
		{
			$this->total = $this->db->query('SELECT FOUND_ROWS()')->fetchColumn();
		}
		else
		{
			$this->total = $rows->NumRows();
		}
		return $dupes;
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * For a union-query you call search for each query with $start=='UNION' and one more with only $order_by and $start set to run the union-query.
	 *
	 * @param array|string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean|string|array $only_keys =true True returns only keys, False returns all cols. or
	 *	comma seperated list or array of columns to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count =false If true an unlimited query is run to determine the total number of rows, default false
	 * @param boolean $ignore_acl =false true: no acl check
	 * @return boolean/array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false, $ignore_acl=false)
	{
		if ((int) $this->debug >= 4) echo '<p>'.__METHOD__.'('.array2string($criteria).','.array2string($only_keys).",'$order_by','$extra_cols','$wildcard','$empty','$op',$start,".array2string($filter).",'$join')</p>\n";
		//error_log(__METHOD__.'('.array2string($criteria,true).','.array2string($only_keys).",'$order_by', ".array2string($extra_cols).",'$wildcard','$empty','$op',$start,".array2string($filter).",'$join')");

		$owner = isset($filter['owner']) ? $filter['owner'] : (isset($criteria['owner']) ? $criteria['owner'] : null);

		// fix cat_id criteria to search in comma-separated multiple cats and return subcats
		if (is_array($criteria) && !empty($criteria['cat_id']))
		{
			$criteria = array_merge($criteria, $this->_cat_search($criteria['cat_id']));
			unset($criteria['cat_id']);
		}
		// fix cat_id filter to search in comma-separated multiple cats and return subcats
		if (!empty($filter['cat_id']))
		{
			if ($filter['cat_id'][0] == '!')
			{
				$filter['cat_id'] = substr($filter['cat_id'],1);
				$not = 'NOT';
			}
			$filter[] = $this->_cat_filter($filter['cat_id'],$not);
			unset($filter['cat_id']);
		}

		if (!empty($filter['shared_by']))
		{
			$filter[] = $this->table_name.'.contact_id IN (SELECT DISTINCT contact_id FROM '.self::SHARED_TABLE.' WHERE '.
				'shared_deleted IS NULL AND shared_by='.(int)$filter['shared_by'].
				((string)$filter['owner'] !== '' ? ' AND shared_with='.(int)$filter['owner'] : '').')';
			unset($filter['shared_by']);
			$shared_sql = '1=1';	// can not be empty and must be true
		}
		else
		{
			// SQL to get all shared contacts to be OR-ed into ACL filter
			$shared_sql = $this->table_name.'.contact_id IN (SELECT contact_id FROM '.self::SHARED_TABLE.' WHERE '.
				// $filter[tid] === null is used by sync-collection report, in which case we need to return deleted shares, to remove them from devices
				(array_key_exists('tid', $filter) && !isset($filter['tid']) ? '' : 'shared_deleted IS NULL AND ').
				$this->db->expression(self::SHARED_TABLE, ['shared_with' => $filter['owner'] ?? array_keys($this->grants ?? [0])]).')';
		}

		// add filter for read ACL in sql, if user is NOT the owner of the addressbook
		if (isset($this->grants) && !$ignore_acl)
		{
			// add read ACL for groupmembers (they have no
			if ($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'groupmembers' &&
				(!isset($filter['owner']) || in_array('0',(array)$filter['owner'])))
			{
				$groupmembers = array();
				foreach($GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'],true) as $group_id)
				{
					if (($members = $GLOBALS['egw']->accounts->members($group_id,true)))
					{
						$groupmembers = array_merge($groupmembers,$members);
					}
				}
				$groupmember_sql = $this->db->expression($this->table_name, ' OR '.$this->table_name.'.',array(
					'account_id' => array_unique($groupmembers),
				));
			}
			// we have no private grants in addressbook at the moment, they have then to be added here too
			if (isset($filter['owner']))
			{
				// no grants for selected owner/addressbook
				if (!array_intersect((array)$filter['owner'],array_keys($this->grants)))
				{
					if (!isset($groupmember_sql))
					{
						$ret = false;
						return $ret;
					}
					$filter[] = '('.substr($groupmember_sql,4)." OR $shared_sql)";
					unset($filter['owner']);
				}
				// for an owner filter, which does NOT include current user, filter out private entries
				elseif (!in_array($GLOBALS['egw_info']['user']['account_id'], (array)$filter['owner']))
				{
					$filter[] = '('.$this->db->expression($this->table_name, $this->table_name.'.', ['contact_owner' => $filter['owner'], 'contact_private' => 0]).
						" OR $shared_sql)";
					unset($filter['owner']);
				}
				// if multiple addressbooks (incl. current owner) are searched, we need full acl filter
				elseif(is_array($filter['owner']) && count($filter['owner']) > 1)
				{
					$filter[] = "($this->table_name.contact_owner=".(int)$GLOBALS['egw_info']['user']['account_id'].
						" OR $shared_sql".
						" OR contact_private=0 AND $this->table_name.contact_owner IN (".
						implode(',',array_keys($this->grants)).") $groupmember_sql OR $this->table_name.contact_owner IS NULL)";
				}
				else
				{
					$filter[] = '('.$this->db->expression($this->table_name, $this->table_name.'.', ['contact_owner' => $filter['owner']]).
						" OR $shared_sql)";
					unset($filter['owner']);
				}
			}
			else	// search all addressbooks, incl. accounts
			{
				if ($this->account_repository != 'sql' && $this->contact_repository != 'sql-ldap')
				{
					$filter[] = $this->table_name.'.contact_owner != 0';	// in case there have been accounts in sql previously
				}
				$filter[] = "($this->table_name.contact_owner=".(int)$GLOBALS['egw_info']['user']['account_id'].
					" OR $shared_sql".
					($this->grants ? " OR contact_private=0 AND $this->table_name.contact_owner IN (".
						implode(',',array_keys($this->grants)).")" : '').
					($groupmember_sql??'')." OR $this->table_name.contact_owner IS NULL)";
			}
		}
		if (isset($filter['list']))
		{
			if ($filter['list'] < 0)
			{
				$join .= " JOIN egw_acl ON $this->table_name.account_id=acl_account AND acl_appname='phpgw_group' AND ".
					$this->db->expression('egw_acl', array('acl_location' => $filter['list']));
			}
			else
			{
				$join .= " JOIN $this->ab2list_table ON $this->table_name.contact_id=$this->ab2list_table.contact_id AND ".
					$this->db->expression($this->ab2list_table, array('list_id' => $filter['list']));
			}
			unset($filter['list']);
		}
		// add join to show only active accounts (only if accounts are shown and in sql and we not already join the accounts table, eg. used by admin)
		if ((is_array($owner) ? in_array(0, $owner) : !$owner) && substr($this->account_repository,0,3) == 'sql' &&
			strpos($join,$GLOBALS['egw']->accounts->backend->table) === false && !array_key_exists('account_id',$filter))
		{
			$join .= self::ACCOUNT_ACTIVE_JOIN;
			if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] === '0')
			{
				$filter[] = str_replace('UNIX_TIMESTAMP(NOW())',time(),self::ACOUNT_ACTIVE_FILTER);
			}
		}
		if ($join || ($criteria && is_string($criteria)) || ($criteria && is_array($criteria) && $order_by))	// search also adds a join for custom fields!
		{
			switch(gettype($only_keys))
			{
				case 'boolean':
					// Correctly handled by parent class
					break;
				case 'string':
					$only_keys = explode(',',$only_keys);
					// fall through
			}
			// postgres requires that expressions in order by appear in the columns of a distinct select
			$all_matches = null;
			if ($this->db->Type != 'mysql' && preg_match_all("/(#?[a-zA-Z_.]+) *(<> *''|IS NULL|IS NOT NULL)? *(ASC|DESC)?(,|$)/ui",
				$order_by, $all_matches, PREG_SET_ORDER))
			{
				if (!is_array($extra_cols))	$extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
				foreach($all_matches as $matches)
				{
					$table = '';
					$column = $matches[1];
					if ($column[0] == '#') continue;	// order by custom field is handeled in so_sql_cf anyway
					if (($key = array_search($column, $this->db_cols)) !== false) $column = $key;
					if (strpos($column,'.') === false)
					{
						$table = $column == $this->extra_value ? $this->extra_table : $this->table_name;
						if (isset($this->db_cols[$column]))
						{
							$table .= '.';
						}
						else
						{
							$table = '';
						}
					}
					$extra_cols[] = $table.$column.' '.$matches[2];
					//_debug_array($matches);
					if (!empty($order_by) && $table) // postgres requires explizit order by
					{
						$order_by = str_replace($matches[0],$table.$column.' '.$matches[2].' '.$matches[3].$matches[4],$order_by);
					}
				}
				//_debug_array($order_by); _debug_array($extra_cols);
			}

			// Understand search by date with wildcard (????.10.??) according to user date preference
			if(is_string($criteria) && strpos($criteria, '?') !== false)
			{
				// First, check for a 'date', with wildcards, in the user's format
				$date_regex = str_replace('Q','d',
					str_replace(array('Y','m','d','.','-'),
						array('(?P<Y>(?:\?|\Q){4})','(?P<m>(?:\?|\Q){2})','(?P<d>(?:\?|\Q){2})','\.','\-'),
							$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']));

				if(preg_match_all('$'.$date_regex.'$', $criteria, $matches))
				{
					foreach($matches[0] as $m_id => $match)
					{
						// Birthday is Y-m-d
						$criteria = str_replace($match, "*{$matches['Y'][$m_id]}-{$matches['m'][$m_id]}-{$matches['d'][$m_id]}*",$criteria);
					}
				}
			}
		}
		// shared with column and filter
		if (!is_array($extra_cols))	$extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
		$shared_with = '(SELECT '.$this->db->group_concat('DISTINCT shared_with').' FROM '.self::SHARED_TABLE.
			' WHERE '.self::SHARED_TABLE.'.contact_id='.$this->table_name.'.contact_id AND shared_deleted IS NULL)';
		if (($key = array_search('shared_with', $extra_cols)) !== false)
		{
			$extra_cols[$key] = "$shared_with AS shared_with";
		}
		switch ($filter['shared_with'] ?? '')
		{
			case '':	// filter not set
				break;
			case 'not':
				$filter[] = $shared_with.' IS NULL';
				break;
			case 'shared':
				$filter[] = $shared_with.' IS NOT NULL';
				break;
			default:
				$join .= ' JOIN '.self::SHARED_TABLE.' sw ON '.$this->table_name.'.contact_id=sw.contact_id AND sw.'.
					$this->db->expression(self::SHARED_TABLE, ['shared_with' => $filter['shared_with']]).
					' AND sw.shared_deleted IS NULL';
				break;
		}
		unset($filter['shared_with']);

		$rows =& parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);

		if ($start === false) $this->total = is_array($rows) ? count($rows) : 0;	// so_sql sets total only for $start !== false!

		return $rows;
	}

	/**
	 * fix cat_id filter to search in comma-separated multiple cats and return subcats
	 *
	 * @internal
	 * @param int|array $cat_id
	 * @return string sql to filter by given cat
	 */
	function _cat_filter($cat_id, $not='')
	{
		if (!is_object($GLOBALS['egw']->categories))
		{
			$GLOBALS['egw']->categories = new Api\Categories;
		}
		foreach($GLOBALS['egw']->categories->return_all_children($cat_id) as $cat)
		{
			$cat_filter[] = $this->db->concat("','", 'cat_id', "','")." $not LIKE '%,$cat,%'";
		}
		$cfilter = '('.implode(' OR ',$cat_filter).')';
		if(!empty($not))
		{
			$cfilter = "( $cfilter OR cat_id IS NULL )";
		}
		return $cfilter;
	}

	/**
	 * fix cat_id criteria to search in comma-separated multiple cats
	 *
	 * @internal
	 * @param int|array|string $cats
	 * @return array of sql-strings to be OR'ed or AND'ed together
	 */
	function _cat_search($cats)
	{
		$cat_filter = array();
		foreach(is_array($cats) ? $cats : (is_numeric($cats) ? array($cats) : explode(',',$cats)) as $cat)
		{
			if (is_numeric($cat)) $cat_filter[] = $this->db->concat("','", 'cat_id', "','")." LIKE '%,$cat,%'";
		}
		return $cat_filter;
	}

	/**
	 * Change the ownership of contacts and distribution-lists owned by a given account
	 *
	 * @param int $account_id account-id of the old owner
	 * @param int $new_owner account-id of the new owner
	 */
	function change_owner($account_id,$new_owner)
	{
		if (!$new_owner)	// otherwise we would create an account (contact_owner==0)
		{
			throw Api\Exception\WrongParameter(__METHOD__."($account_id, $new_owner) new owner must not be 0!");
		}
		// contacts
		$this->db->update($this->table_name,array(
			'contact_owner' => $new_owner,
		),array(
			'contact_owner' => $account_id,
		),__LINE__,__FILE__);

		// cfs
		$this->db->update(self::EXTRA_TABLE, array(
			'contact_owner' => $new_owner
		),array(
			'contact_owner' => $account_id
		), __LINE__, __FILE__);

		// lists
		$this->db->update($this->lists_table, array(
			'list_owner' => $new_owner,
		),array(
			'list_owner' => $account_id,
		),__LINE__,__FILE__);
	}

	/**
	 * Get the availible distribution lists for givens users and groups
	 *
	 * @param array $uids array of user or group id's for $uid_column='list_owners', or values for $uid_column,
	 * 	or whole where array: column-name => value(s) pairs
	 * @param string $uid_column ='list_owner' column-name or null to use $uids as where array
	 * @param string $member_attr =null null: no members, 'contact_uid', 'contact_id', 'caldav_name' return members as that attribute
	 * @param boolean|int|array $limit_in_ab =false if true only return members from the same owners addressbook,
	 * 	if int|array only return members from the given owners addressbook(s)
	 * @return array with list_id => array(list_id,list_name,list_owner,...) pairs
	 */
	function get_lists($uids,$uid_column='list_owner',$member_attr=null,$limit_in_ab=false)
	{
		if (is_array($uids) && array_key_exists('list_id', $uids))
		{
			$uids[] = $this->db->expression($this->lists_table, $this->lists_table.'.',array('list_id' => $uids['list_id']));
			unset($uids['list_id']);
		}
		$lists = array();
		foreach($this->db->select($this->lists_table,'*',$uid_column?array($uid_column=>$uids):$uids,__LINE__,__FILE__,
			false,'ORDER BY list_owner<>'.(int)$GLOBALS['egw_info']['user']['account_id'].',list_name') as $row)
		{
			if ($member_attr) $row['members'] = array();
			$lists[$row['list_id']] = $row;
		}
		if ($lists && $member_attr && in_array($member_attr,array('contact_id','contact_uid','caldav_name')))
		{
			if ($limit_in_ab)
			{
				$in_ab_join = " JOIN $this->lists_table ON $this->lists_table.list_id=$this->ab2list_table.list_id AND ";
				if (!is_bool($limit_in_ab))
				{
					$in_ab_join .= $this->db->expression($this->table_name, $this->table_name.'.', ['contact_owner' => $limit_in_ab]);
				}
				else
				{
					$in_ab_join .= "$this->lists_table.list_owner=$this->table_name.contact_owner";
				}
			}
			foreach($this->db->select($this->ab2list_table,"$this->ab2list_table.list_id,$this->table_name.$member_attr",
				$this->db->expression($this->ab2list_table, $this->ab2list_table.'.', array('list_id'=>array_keys($lists))),
				__LINE__,__FILE__,false,$member_attr=='contact_id' ? '' :
				'',false,0,"JOIN $this->table_name ON $this->ab2list_table.contact_id=$this->table_name.contact_id".$in_ab_join) as $row)
			{
				$lists[$row['list_id']]['members'][] = $row[$member_attr];
			}
		}
		/* groups as list are implemented currently in Contacts\Storage::get_lists() for all backends
		if ($uid_column == 'list_owner' && in_array(0, (array)$uids) && (!$limit_in_ab || in_array(0, (array)$limit_in_ab)))
		{
			foreach($GLOBALS['egw']->accounts->search(array(
				'type' => 'groups'
			)) as $account_id => $group)
			{
				$list = array(
					'list_id' => $account_id,
					'list_name' => Api\Accounts::format_username($group['account_lid'], '', '', $account_id),
					'list_owner' => 0,
					'list_uid' => 'group'.$account_id,
					'list_carddav_name' => 'group'.$account_id.'.vcf',
					'list_etag' => md5(json_encode($GLOBALS['egw']->accounts->members($account_id, true)))
				);
				if ($member_attr)
				{
					$list['members'] = array();	// ToDo
				}
				$lists[(string)$account_id] = $list;
			}
		}*/
		//error_log(__METHOD__.'('.array2string($uids).", '$uid_column', '$member_attr') returning ".array2string($lists));
		return $lists;
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
		//error_log(__METHOD__.'('.array2string($keys).", $owner, ".array2string($contacts).', '.array2string($data).') '.function_backtrace());
		if (!$keys && !$data || !(int)$owner) return false;

		if ($keys && !is_array($keys)) $keys = array('list_name' => $keys);
		if ($keys)
		{
			$keys['list_owner'] = $owner;
		}
		else
		{
			$data['list_owner'] = $owner;
		}
		if (!$keys || !($list_id = $this->db->select($this->lists_table,'list_id',$keys,__LINE__,__FILE__)->fetchColumn()))
		{
			$data['list_created'] = time();
			$data['list_creator'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		else
		{
			$data[] = 'list_etag=list_etag+1';
		}
		$data['list_modified'] = time();
		$data['list_modifier'] = $GLOBALS['egw_info']['user']['account_id'];
		if (!$data['list_id']) unset($data['list_id']);

		if (!$this->db->insert($this->lists_table,$data,$keys,__LINE__,__FILE__)) return false;

		if (!$list_id && ($list_id = $this->db->get_last_insert_id($this->lists_table,'list_id')) &&
			(!isset($data['list_uid']) || !isset($data['list_carddav_name'])))
		{
			$update = array();
			if (!isset($data['list_uid']))
			{
				$update['list_uid'] = $data['list_uid'] = Api\CalDAV::generate_uid('addresbook-lists', $list_id);
			}
			if (!isset($data['list_carddav_name']))
			{
				$update['list_carddav_name'] = $data['list_carddav_name'] = $data['list_uid'].'.vcf';
			}
			$this->db->update($this->lists_table,$update,array('list_id'=>$list_id),__LINE__,__FILE__);

			$this->add2list($list_id,$contacts,array());
		}
		if ($keys) $data += $keys;
		//error_log(__METHOD__.'('.array2string($keys).", $owner, ...) data=".array2string($data).' returning '.array2string($list_id));
		return $list_id;
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
		if (!(int)$list || !is_array($contact) && !(int)$contact) return false;

		if (!is_array($existing))
		{
			$existing = array();
			foreach($this->db->select($this->ab2list_table,'contact_id',array('list_id'=>$list),__LINE__,__FILE__) as $row)
			{
				$existing[] = $row['contact_id'];
			}
		}
		if (!($to_add = array_diff((array)$contact,$existing)))
		{
			return true;	// no need to insert it, would give sql error
		}
		foreach($to_add as $contact)
		{
			$this->db->insert($this->ab2list_table,array(
				'contact_id' => $contact,
				'list_id' => $list,
				'list_added' => time(),
				'list_added_by' => $GLOBALS['egw_info']['user']['account_id'],
			),array(),__LINE__,__FILE__);
		}
		// update etag
		return $this->db->update($this->lists_table,array(
			'list_etag=list_etag+1',
			'list_modified' => time(),
			'list_modifier' => $GLOBALS['egw_info']['user']['account_id'],
		),array(
			'list_id' => $list,
		),__LINE__,__FILE__);
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
		if (!(int)$list && !is_null($list) || !is_array($contact) && !(int)$contact) return false;

		$where = array(
			'contact_id' => $contact,
		);
		if (!is_null($list))
		{
			$where['list_id'] = $list;
		}
		else
		{
			$list = array();
			foreach($this->db->select($this->ab2list_table,'list_id',$where,__LINE__,__FILE__) as $row)
			{
				$list[] = $row['list_id'];
			}
		}
		if (!$this->db->delete($this->ab2list_table,$where,__LINE__,__FILE__))
		{
			return false;
		}
		foreach((array)$list as $list_id)
		{
			$this->db->update($this->lists_table,array(
				'list_etag=list_etag+1',
				'list_modified' => time(),
				'list_modifier' => $GLOBALS['egw_info']['user']['account_id'],
			),array(
				'list_id' => $list_id,
			),__LINE__,__FILE__);
		}
		return true;
	}

	/**
	 * Deletes a distribution list (incl. it's members)
	 *
	 * @param int|array $list list_id(s)
	 * @return number of members deleted or false if list does not exist
	 */
	function delete_list($list)
	{
		if (!$this->db->delete($this->lists_table,array('list_id' => $list),__LINE__,__FILE__)) return false;

		$this->db->delete($this->ab2list_table,array('list_id' => $list),__LINE__,__FILE__);

		return $this->db->affected_rows();
	}

	/**
	 * Get ctag (max list_modified as timestamp) for lists
	 *
	 * @param int|array $owner =null null for all lists user has access too
	 * @return int
	 */
	function lists_ctag($owner=null)
	{
		if (is_null($owner)) $owner = array_keys($this->grants);

		if (!($modified = $this->db->select($this->lists_table,'MAX(list_modified)',array('list_owner'=>$owner),
			__LINE__,__FILE__)->fetchColumn()))
		{
			return 0;
		}
		return $this->db->from_timestamp($modified);
	}

	/**
	 * Reads a contact, reimplemented to use the uid, if a non-numeric key is given
	 *
	 * @param int|string|array $keys
	 * @param string|array $extra_cols
	 * @param string $join
	 * @return array|boolean
	 */
	function read($keys,$extra_cols='',$join='')
	{
		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'])) {
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		} else {
			$minimum_uid_length = 8;
		}

		if (!is_array($keys) && !is_numeric($keys))
		{
			$keys = array('uid' => $keys);
		}
		try {
			$contact = parent::read($keys,$extra_cols,$join);
		}
		// catch Illegal mix of collations (ascii_general_ci,IMPLICIT) and (utf8_general_ci,COERCIBLE) for operation '=' (1267)
		// caused by non-ascii chars compared with ascii field uid
		catch(Api\Db\Exception $e) {
			_egw_log_exception($e);
			return false;
		}

		// enforce a minium uid strength
		if (is_array($contact) && (!isset($contact['uid'])
				|| strlen($contact['uid']) < $minimum_uid_length)) {
			parent::update(array('uid' => Api\CalDAV::generate_uid('addressbook',$contact['id'])));
		}
		if (is_array($contact))
		{
			$contact['shared'] = $this->read_shared($contact['id']);
		}
		return $contact;
	}

	/**
	 * Read sharing information of a contact
	 *
	 * @param int $id contact_id to read
	 * @param ?boolean $deleted =false false: ignore deleted, true: only deleted, null: both
	 * @return array of array with values for keys "shared_(with|writable|by|at|id|deleted)"
	 */
	function read_shared($id, $deleted=false)
	{
		$shared = [];
		$where = ['contact_id' => $id];
		if (isset($deleted)) $where[] = $deleted ? 'shared_deleted IS NOT NULL' : 'shared_deleted IS NULL';
		foreach($this->db->select(self::SHARED_TABLE, '*', $where, __LINE__, __FILE__, false) as $row)
		{
			$row['shared_at'] = Api\DateTime::server2user($row['shared_at'], 'object');
			$shared[] = $row;
		}
		return $shared;
	}

	/**
	 * Save sharing information of a contact
	 *
	 * @param int $id
	 * @param array $shared array of array with values for keys "shared_(with|writable|by|at|id)"
	 * @return array of array with values for keys "shared_(with|writable|by|at|id)"
	 */
	function save_shared($id, array $shared)
	{
		$ids = [];
		foreach($shared as $key => &$data)
		{
			if (empty($data['shared_id']))
			{
				unset($data['shared_id']);
				$data['contact_id'] = $id;
				$data['shared_at'] = Api\DateTime::user2server($data['shared_at'] ?: 'now');
				$data['shared_by'] = $data['shared_by'] ?: $GLOBALS['egw_info']['user']['account_id'];
				$data['shared_deleted'] = null;
				foreach($shared as $ckey => $check)
				{
					if (!empty($check['shared_id']) &&
						$data['shared_with'] == $check['shared_with'] &&
						$data['shared_by'] == $check['shared_by'])
					{
						if ($data['shared_writable'] == $check['shared_writable'])
						{
							unset($shared[$key]);
							continue 2;	// no need to save identical entry
						}
						// remove
						unset($shared[$ckey]);
						break;
					}
				}
				$this->db->insert(self::SHARED_TABLE, $data,
					$where = array_intersect_key($data, array_flip(['shared_by','shared_with','contact_id','share_id'])), __LINE__, __FILE__);
				// if we resurect a previous deleted share, we dont get the shared_id back, need to query it
				$data['shared_id'] = $this->db->select(self::SHARED_TABLE, 'shared_id', $where, __LINE__, __FILE__)->fetchColumn();
			}
			$ids[] = (int)$data['shared_id'];
		}
		$delete = ['contact_id' => $id, 'shared_deleted IS NULL'];
		if ($ids) $delete[] = 'shared_id NOT IN ('.implode(',', $ids).')';
		$this->db->update(self::SHARED_TABLE, ['shared_deleted' => new Api\DateTime('now')], $delete, __LINE__, __FILE__);
		foreach($shared as &$data)
		{
			$data['shared_at'] = Api\DateTime::server2user($data['shared_at'], 'object');
		}
		return $shared;
	}

	/**
	 * deletes row representing keys in internal data or the supplied $keys if != null
	 *
	 * reimplented to also delete sharing info
	 *
	 * @param array|int $keys =null if given array with col => value pairs to characterise the rows to delete, or integer autoinc id
	 * @param boolean $only_return_ids =false return $ids of delete call to db object, but not run it (can be used by extending classes!)
	 * @return int|array affected rows, should be 1 if ok, 0 if an error or array with id's if $only_return_ids
	 */
	function delete($keys=null,$only_return_ids=false)
	{
		if (!$only_return_ids)
		{
			if (is_scalar($keys))
			{
				$query = ['contact_id' => $keys];
			}
			elseif (!isset($keys['contact_id']))
			{
				$query = ['contact_id' => parent::delete($keys,true)];
			}
			$this->db->delete(self::SHARED_TABLE, $query ?? $keys, __LINE__, __FILE__);
		}
		return parent::delete($keys, $only_return_ids);
	}

	/**
	 * Saves a contact, reimplemented to check a given etag and set a uid
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where =null extra where clause, eg. to check the etag, returns 'nothing_affected' if not affected rows
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys = NULL, $extra_where = NULL)
	{
		unset($extra_where);	// not used, but required by function signature

		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'])) {
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		} else {
			$minimum_uid_length = 8;
		}

		if (is_array($keys) && count($keys)) $this->data_merge($keys);

		$new_entry = !$this->data['id'];

		if (isset($this->data['etag']))		// do we have an etag in the data to write
		{
			$etag = $this->data['etag'];
			unset($this->data['etag']);
			if (!($err = parent::save(array('contact_etag=contact_etag+1'),array('contact_etag' => $etag))))
			{
				$this->data['etag'] = (int)$etag+1;
			}
			else
			{
				$this->data['etag'] = $etag;
			}
		}
		else
		{
			unset($this->data['etag']);
			if (!($err = parent::save(array('contact_etag=contact_etag+1'))) && $new_entry)
			{
				$this->data['etag'] = 0;
			}
		}

		$update = array();
		// enforce a minium uid strength
		if (!isset($this->data['uid']) || strlen($this->data['uid']) < $minimum_uid_length)
		{
			$update['uid'] = Api\CalDAV::generate_uid('addressbook',$this->data['id']);
			//echo "<p>set uid={$this->data['uid']}, etag={$this->data['etag']}</p>";
		}
		// set carddav_name, if not given by caller
		if (empty($this->data['carddav_name']))
		{
			$update['carddav_name'] = $this->data['id'].'.vcf';
		}
		// update photo in entry-directory, unless hinted it is unchanged
		if (!$err && $this->data['photo_unchanged'] !== true)
		{
			// in case files bit-field is not available read it from DB
			if (!isset($this->data['files']))
			{
				$this->data['files'] = (int)$this->db->select($this->table_name, 'contact_files', array(
					'contact_id' => $this->data['id'],
				), __LINE__, __FILE__)->fetchColumn();
			}
			$path =  Api\Link::vfs_path('addressbook', $this->data['id'], Api\Contacts::FILES_PHOTO);
			$backup = Api\Vfs::$is_root; Api\Vfs::$is_root = true;
			if (empty($this->data['jpegphoto']))
			{
				unlink($path);
				$update['files'] = $this->data['files'] & ~Api\Contacts::FILES_BIT_PHOTO;
			}
			else
			{
				file_put_contents($path, $this->data['jpegphoto']);
				$update['files'] = $this->data['files'] | Api\Contacts::FILES_BIT_PHOTO;
			}
			Api\Vfs::$is_root = $backup;
		}
		if (!$err && $update)
		{
			parent::update($update);
		}
		// save sharing information, if given, eg. not the case for CardDAV
		if (!$err && isset($this->data['shared']))
		{
			$this->data['shared'] = $this->save_shared($this->data['id'], $this->data['shared']);
		}
		return $err;
	}


	/**
	 * Read data of a distribution list
	 *
	 * @param int $list list_id
	 * @return array of data or false if list does not exist
	 */
	function read_list($list)
	{
		if (!$list) return false;

		return $this->db->select($this->lists_table,'*',array('list_id'=>$list),__LINE__,__FILE__)->fetch();
	}

	/**
	 * saves custom field data
	 * Re-implemented to deal with extra contact_owner column
	 *
	 * @param array $data data to save (cf's have to be prefixed with self::CF_PREFIX = #)
	 * @param array $extra_cols =array() extra-data to be saved
	 * @return bool false on success, errornumber on failure
	 */
	function save_customfields(&$data, array $extra_cols=array())
	{
		return parent::save_customfields($data, array('contact_owner' => $data['owner'])+$extra_cols);
	}

	/**
	* Deletes custom field data
	* Implemented to deal with LDAP backend, which saves CFs in SQL, but the account record is in LDAP
	*/
	function delete_customfields($data)
	{
		$this->db->delete($this->extra_table,$data,__LINE__,__FILE__);
	}
}