<?php
/**
 * Addressbook - SQL backend
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.so_sql.inc.php');

/**
 * SQL storage object of the adressbook
 *
 * @package addressbook
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

class socontacts_sql extends so_sql 
{
	/**
	 * name of customefields table
	 * 
	 * @var string
	 */
	var $extra_table = 'egw_addressbook_extra';
	var $extra_join = ' LEFT JOIN egw_addressbook_extra ON egw_addressbook.contact_id=egw_addressbook_extra.contact_id';
	var $account_repository = 'sql';
	var $contact_repository = 'sql';
	
	/**
	 * internal name of the id, gets mapped to uid
	 *
	 * @var string
	 */
	var $contacts_id='id';

	function socontacts_sql()
	{
		$this->so_sql('phpgwapi','egw_addressbook',null,'contact_');	// calling the constructor of the extended class

		if ($GLOBALS['egw_info']['server']['account_repository'])
		{
			$this->account_repository = $GLOBALS['egw_info']['server']['account_repository'];
		}
		elseif ($GLOBALS['egw_info']['server']['auth_type'])
		{
			$this->account_repository = $GLOBALS['egw_info']['server']['auth_type'];
		}
		if ($GLOBALS['egw_info']['server']['contact_repository'])
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
	 * @var int $param[start]
	 * @var int $param[num_rows]
	 * @var string $param[sort] ASC or DESC
	 * @return array or arrays with keys org_name,count and evtl. adr_one_location or org_unit
	 */ 
	function organisations($param)
	{
		$filter = is_array($param['col_filter']) ? $param['col_filter'] : array();

		// fix cat_id filter to search in comma-separated multiple cats and return subcats
		if ((int)$filter['cat_id'])
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
				$filter[] = "(contact_owner=".(int)$GLOBALS['egw_info']['user']['account_id'].
					" OR contact_private=0 AND contact_owner IN (".
					implode(',',array_keys($this->grants))."))";
			}
		}
		if ($param['searchletter'])
		{
			$filter[] = 'org_name LIKE '.$this->db->quote($param['searchletter'].'%');
		}
		else
		{
			$filter[] = "org_name != ''";// AND org_name IS NOT NULL";
		}
		$sort = $param['sort'] == 'DESC' ? 'DESC' : 'ASC';

		list(,$by) = explode(',',$param['org_view']);
		if (!$by)
		{
			$extra = array(
				'COUNT(org_name) AS org_count',
				"COUNT(DISTINCT CASE WHEN org_unit IS NULL THEN '' ELSE org_unit END) AS org_unit_count",
				"COUNT(DISTINCT CASE WHEN adr_one_locality IS NULL THEN '' ELSE adr_one_locality END) AS adr_one_locality_count",
			);
			$append = "GROUP BY org_name ORDER BY org_name $sort";
		}
		else	// by adr_one_location or org_unit
		{
			// org total for more then one $by
			$append = "GROUP BY org_name HAVING {$by}_count > 1 ORDER BY org_name $sort";
			parent::search($param['search'],array('org_name'),$append,array(
				"NULL AS $by",
				'COUNT(org_name) AS org_count',
				"COUNT(DISTINCT CASE WHEN org_unit IS NULL THEN '' ELSE org_unit END) AS org_unit_count",
				"COUNT(DISTINCT CASE WHEN adr_one_locality IS NULL THEN '' ELSE adr_one_locality END) AS adr_one_locality_count",
			),'%',false,'OR','UNION',$filter);
			// org by location
			$append = "GROUP BY org_name,$by ORDER BY org_name $sort,$by $sort";
			parent::search($param['search'],array('org_name'),$append,array(
				"CASE WHEN $by IS NULL THEN '' ELSE $by END AS $by",
				'COUNT(org_name) AS org_count',
				"COUNT(DISTINCT CASE WHEN org_unit IS NULL THEN '' ELSE org_unit END) AS org_unit_count",
				"COUNT(DISTINCT CASE WHEN adr_one_locality IS NULL THEN '' ELSE adr_one_locality END) AS adr_one_locality_count",
			),'%',false,'OR','UNION',$filter);
			$append = "ORDER BY org_name $sort,CASE WHEN $by IS NULL THEN 1 ELSE 2 END,$by $sort";
		}
		$rows = parent::search($param['search'],array('org_name'),$append,$extra,'%',false,'OR',
			array($param['start'],$param['num_rows']),$filter);
			
		if (!$rows) return false;

		// query the values for *_count == 1, to display them instead
		$filter['org_name'] = $orgs = array();
		foreach($rows as $n => $row)
		{
			if ($row['org_unit_count'] == 1 || $row['adr_one_locality_count'] == 1)
			{
				$filter['org_name'][$row['org_name']] = $row['org_name'];	// use as key too to have every org only once
			}
			$org_key = $row['org_name'].($by ? '|||'.($row[$by] || $row[$by.'_count']==1 ? $row[$by] : '|||') : '');
			$orgs[$org_key] = $row; 
		}
		unset($rows);
		
		if (count($filter['org_name']))
		{
			foreach((array) parent::search($criteria,array('org_name','org_unit','adr_one_locality'),'GROUP BY org_name,org_unit,adr_one_locality',
				'','%',false,'AND',false,$filter) as $row)
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
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * For a union-query you call search for each query with $start=='UNION' and one more with only $order_by and $start set to run the union-query.
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string/array $only_keys=true True returns only keys, False returns all cols. or 
	 *	comma seperated list or array of columns to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
	 * @return boolean/array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
		if ((int) $this->debug >= 4) echo "<p>socontacts_sql::search(".print_r($criteria,true).",".print_r($only_keys,true).",'$order_by','$extra_cols','$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";
		
		$owner = isset($filter['owner']) ? $filter['owner'] : (isset($criteria['owner']) ? $criteria['owner'] : null);

		// fix cat_id filter to search in comma-separated multiple cats and return subcats
		if ((int)$filter['cat_id'])
		{
			$filter[] = $this->_cat_filter($filter['cat_id']);
			unset($filter['cat_id']);
		}
		// add filter for read ACL in sql, if user is NOT the owner of the addressbook
		if (isset($this->grants) && !(isset($filter['owner']) && $filter['owner'] == $GLOBALS['egw_info']['user']['account_id']))
		{
			// we have no private grants in addressbook at the moment, they have then to be added here too
			if (isset($filter['owner']))
			{
				if (!$this->grants[(int) $filter['owner']]) return false;	// we have no access to that addressbook
				
				$filter['private'] = 0;
			}
			else	// search all addressbooks, incl. accounts
			{
				if ($this->account_repository != 'sql' && $this->contact_repository != 'sql-ldap')
				{
					$filter[] = $this->table_name.'.contact_owner != 0';	// in case there have been accounts in sql previously
				}
				$filter[] = "($this->table_name.contact_owner=".(int)$GLOBALS['egw_info']['user']['account_id'].
					" OR contact_private=0 AND $this->table_name.contact_owner IN (".
					implode(',',array_keys($this->grants)).") OR $this->table_name.contact_owner IS NULL)";
			}
		}
		$search_customfields = isset($criteria['contact_value']) && !empty($criteria['contact_value']);
		if (is_array($criteria))
		{
			foreach($criteria as $col => $val)
			{
				if ($col{0} == '#')	// search for a value in a certain custom field
				{
					unset($criteria[$col]);
					$criteria[] = $this->db->expression($this->extra_table,'(',array('contact_name'=>substr($col,1),'contact_value'=>$val),')');
					$search_customfields = true;
				}
			}
		}
		if ($search_customfields)	// search the custom-fields
		{
			$join .= $this->extra_join;
			if (is_string($only_keys)) $only_keys = 'DISTINCT '.str_replace(array('contact_id','contact_owner'),
				array($this->table_name.'.contact_id',$this->table_name.'.contact_owner'),$only_keys);
			
			// only return the egw_addressbook columns, to not generate dublicates by the left join
			// and to not return the NULL for contact_{id|owner} of not found custom fields!
			if (is_bool($only_keys)) $only_keys = 'DISTINCT '.$this->table_name.'.*';
				
			if (isset($filter['owner']))
			{
				$filter[] = $this->table_name.'.contact_owner='.(int)$filter['owner'];
				unset($filter['owner']);
			}
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
	}
	
	/**
	 * fix cat_id filter to search in comma-separated multiple cats and return subcats
	 * 
	 * @internal 
	 * @param int $cat_id
	 * @return string sql to filter by given cat
	 */
	function _cat_filter($cat_id)
	{
		if (!is_object($GLOBALS['egw']->categories))
		{
			$GLOBALS['egw']->categories = CreateObject('phpgwapi.categories');
		}
		foreach($GLOBALS['egw']->categories->return_all_children((int)$cat_id) as $cat)
		{
			$cat_filter[] = $this->db->concat("','",cat_id,"','")." LIKE '%,$cat,%'";
		}
		return '('.implode(' OR ',$cat_filter).')';
	}
}