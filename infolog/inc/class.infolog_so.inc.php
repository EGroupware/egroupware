<?php
/**
 * EGroupare - InfoLog - Storage object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * storage object / db-layer for InfoLog
 *
 * all values passed to this class are run either through intval or addslashes to prevent query-insertion
 * and for pgSql 7.3 compatibility
 */
class infolog_so
{
	/**
	 * Instance of the db class
	 *
	 * @var egw_db
	 */
	var $db;
	/**
	 * Grants from other users
	 *
	 * @var array
	 */
	var $grants;
	/**
	 * Internal data array
	 *
	 * @var array
	 */
	var $data = array( );
	/**
	 * Current user (account_id)
	 *
	 * @var int
	 */
	var $user;
	/**
	 * Infolog table-name
	 *
	 * @var string
	 */
	var $info_table = 'egw_infolog';
	/**
	 * Infolog custom fileds table-name
	 *
	 * @var string
	 */
	var $extra_table = 'egw_infolog_extra';
	/**
	 * Offset between server- and user-time in h
	 *
	 * @var int
	 */
	 var $tz_offset;

	/**
	 * Constructor
	 *
	 * @param array $grants=array()
	 * @return soinfolog
	 */
	function __construct( $grants=array() )
	{
		$this->db     = clone($GLOBALS['egw']->db);
		$this->db->set_app('infolog');
		$this->grants = $grants;
		$this->user   = $GLOBALS['egw_info']['user']['account_id'];

		$this->tz_offset = $GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'];
	}

	/**
	 * Check if user is responsible for an entry: he or one of his memberships is in responsible
	 *
	 * @param array $info infolog entry as array
	 * @return boolean
	 */
	function is_responsible($info,$user=null)
	{
		if (!$user) $user = $this->user;

		static $um_cache = array();
		if ($user == $this->user) $user_and_memberships =& $um_cache[$user];
		if (!isset($user_and_memberships))
		{
			$user_and_memberships = $GLOBALS['egw']->accounts->memberships($user,true);
			$user_and_memberships[] = $user;
		}
		return $info['info_responsible'] && array_intersect((array)$info['info_responsible'],$user_and_memberships);
	}

	/**
	 * checks if user has the $required_rights to access $info_id (private access is handled too)
	 *
	 * @param array|int $info data or info_id of InfoLog entry
	 * @param int $required_rights EGW_ACL_xyz anded together
	 * @param boolean $implicit_edit=false responsible has only implicit read and add rigths, unless this is set to true
	 * @param array $grants=null grants to use, default (null) $this->grants
	 * @param int $user=null user to check, default (null) $this->user
	 * @return boolean True if access is granted else False
	 */
	function check_access( $info,$required_rights,$implicit_edit=false,array $grants=null,$user=null )
	{
		if (is_null($grants)) $grants = $this->grants;
		if (!$user) $user = $this->user;

		if (is_array($info))
		{

		}
		elseif ((int) $info != $this->data['info_id'])      	// already loaded?
		{
			// dont change our own internal data,
			$backup_data = $this->data;
			$info = $this->read(array('info_id'=>$info));
			$this->data = $backup_data;
		}
		else
		{
			$info = $this->data;
		}
		if (!$info)
		{
			return False;
		}
		$owner = $info['info_owner'];
		$access_ok = $owner == $user ||	// user has all rights
			// ACL only on public entrys || $owner granted _PRIVATE
			(!!($grants[$owner] & $required_rights) ||
				$this->is_responsible($info,$user) &&	// implicite rights for responsible user(s) and his memberships
				($required_rights == EGW_ACL_READ || $required_rights == EGW_ACL_ADD || $implicit_edit && $required_rights == EGW_ACL_EDIT)) &&
			($info['info_access'] == 'public' || !!($this->grants[$user] & EGW_ACL_PRIVATE));

		// error_log(__METHOD__."($info[info_id],$required_rights,$implicit_edit,".array2string($grants).",$user) returning ".array2string($access_ok));
		return $access_ok;
	}

	/**
	 * Filter for a given responsible user: info_responsible either contains a the user or one of his memberships
	 *
	 * @param int|array $users one or more account_ids
	 * @return string
	 *
	 * @todo make the responsible a second table and that filter a join with the responsible table
	 */
	function responsible_filter($users)
	{
		if (!$users) return '0';

		$responsible = array();
		foreach((array)$users as $user)
		{
			$responsible = array_merge($responsible,(array)
				($user > 0 ? $GLOBALS['egw']->accounts->memberships($user,true) :
					$GLOBALS['egw']->accounts->members($user,true)));
			$responsible[] = $user;
		}
		if (is_array($users))
		{
			$responsible = array_unique($responsible);
		}
		foreach($responsible as $key => $uid)
		{
			$responsible[$key] = $this->db->concat("','",'info_responsible',"','")." LIKE '%,$uid,%'";
		}
		//echo "<p align=right>responsible_filter($user) = ".'('.implode(' OR ',$responsible).')'."</p>\n";
		return '('.implode(' OR ',$responsible).')';
	}

	/**
	 * generate sql to be AND'ed into a query to ensure ACL is respected (incl. _PRIVATE)
	 *
	 * @param string $filter: none|all - list all entrys user have rights to see<br>
	 * 	private|own - list only his personal entrys (incl. those he is responsible for !!!),
	 *  responsible|my = entries the user is responsible for
	 *  delegated = entries the user delegated to someone else
	 * @return string the necesary sql
	 */
	function aclFilter($filter = False)
	{
		preg_match('/(my|responsible|delegated|own|privat|private|all|none|user)([0-9,-]*)/',$filter_was=$filter,$vars);
		$filter = $vars[1];
		$f_user = $vars[2];

		if (isset($this->acl_filter[$filter.$f_user]))
		{
			return $this->acl_filter[$filter.$f_user];  // used cached filter if found
		}
		if ($f_user && strpos($f_user,',') !== false)
		{
			$f_user = explode(',',$f_user);
		}

		$filtermethod = " (info_owner=$this->user"; // user has all rights

		if ($filter == 'my' || $filter == 'responsible')
		{
			$filtermethod .= " AND info_responsible='0'";
		}
		if ($filter == 'delegated')
		{
			$filtermethod .= " AND info_responsible<>'0')";
		}
		else
		{
			if (is_array($this->grants))
			{
				foreach($this->grants as $user => $grant)
				{
					// echo "<p>grants: user=$user, grant=$grant</p>";
					if ($grant & (EGW_ACL_READ|EGW_ACL_EDIT))
					{
						$public_user_list[] = $user;
					}
					if ($grant & EGW_ACL_PRIVATE)
					{
						$private_user_list[] = $user;
					}
				}
				if (count($private_user_list))
				{
					$has_private_access = $this->db->expression($this->info_table,array('info_owner' => $private_user_list));
				}
			}
			$public_access = $this->db->expression($this->info_table,array('info_owner' => $public_user_list));
			// implicit read-rights for responsible user
			$filtermethod .= " OR (".$this->responsible_filter($this->user).')';

			// private: own entries plus the one user is responsible for
			if ($filter == 'private' || $filter == 'privat' || $filter == 'own')
			{
				$filtermethod .= " OR (".$this->responsible_filter($this->user).
					($filter == 'own' && count($public_user_list) ?	// offer's should show up in own, eg. startpage, but need read-access
						" OR info_status = 'offer' AND $public_access" : '').")".
				                 " AND (info_access='public'".($has_private_access?" OR $has_private_access":'').')';
			}
			elseif ($filter != 'my' && $filter != 'responsible')	// none --> all entrys user has rights to see
			{
				if ($has_private_access)
				{
					$filtermethod .= " OR $has_private_access";
				}
				if (count($public_user_list))
				{
					$filtermethod .= " OR (info_access='public' AND $public_access)";
				}
			}
			$filtermethod .= ') ';

			if ($filter == 'user' && $f_user)
			{
				$filtermethod .= $this->db->expression($this->info_table,' AND (',array(
					'info_owner' => $f_user,
				)," AND info_responsible='0' OR ",$this->responsible_filter($f_user),')');
			}
		}
		//echo "<p>aclFilter(filter='$filter_was',user='$f_user') = '$filtermethod', privat_user_list=".print_r($privat_user_list,True).", public_user_list=".print_r($public_user_list,True)."</p>\n";
		return $this->acl_filter[$filter.$f_user] = $filtermethod;  // cache the filter
	}

	/**
	 * generate sql to filter based on the status of the log-entry
	 *
	 * @param string $filter done = done or billed, open = not (done, billed, cancelled or deleted), offer = offer
	 * @param boolean $prefix_and=true if true prefix the fileter with ' AND '
	 * @return string the necesary sql
	 */
	function statusFilter($filter = '',$prefix_and=true)
	{
		preg_match('/(done|open|offer|deleted)/',$filter,$vars);
		$filter = $vars[1];

		switch ($filter)
		{
			case 'done':	$filter = "info_status IN ('done','billed','cancelled')"; break;
			case 'open':	$filter = "NOT (info_status IN ('done','billed','cancelled','deleted','template','nonactive','archive'))"; break;
			case 'offer':	$filter = "info_status = 'offer'";    break;
			case 'deleted': $filter = "info_status = 'deleted'";  break;
			default:        $filter = "NOT (info_status IN ('deleted','template','nonactive','archive'))"; break;
		}
		return ($prefix_and ? ' AND ' : '').$filter;
	}

	/**
	 * generate sql to filter based on the start- and enddate of the log-entry
	 *
	 * @param string $filter upcoming = startdate is in the future
	 * 	today: startdate < tomorrow
	 * 	overdue: enddate < tomorrow
	 *  date: today <= startdate && startdate < tomorrow
	 *  enddate: today <= enddate && enddate < tomorrow
	 * 	limitYYYY/MM/DD not older or open
	 * @return string the necesary sql
	 */
	function dateFilter($filter = '')
	{
		preg_match('/(open-upcoming|upcoming|today|overdue|date|enddate)([-\\/.0-9]*)/',$filter,$vars);
		$filter = $vars[1];

		if (isset($vars[2]) && !empty($vars[2]) && ($date = preg_split('/[-\\/.]/',$vars[2])))
		{
			$today = mktime(-$this->tz_offset,0,0,intval($date[1]),intval($date[2]),intval($date[0]));
			$tomorrow = mktime(-$this->tz_offset,0,0,intval($date[1]),intval($date[2])+1,intval($date[0]));
		}
		else
		{
			$now = getdate(time()-60*60*$this->tz_offset);
			$tomorrow = mktime(-$this->tz_offset,0,0,$now['mon'],$now['mday']+1,$now['year']);
		}
		switch ($filter)
		{
			case 'open-upcoming':
				return  "AND (info_startdate >= $tomorrow OR NOT (info_status IN ('done','billed','cancelled','deleted','template','nonactive','archive')))";
			case 'upcoming':
				return " AND info_startdate >= $tomorrow";
			case 'today':
				return " AND info_startdate < $tomorrow";
			case 'overdue':
				return " AND (info_enddate != 0 AND info_enddate < $tomorrow)";
			case 'date':
				if (!$today || !$tomorrow)
				{
					return '';
				}
				return " AND ($today <= info_startdate AND info_startdate < $tomorrow)";
			case 'enddate':
				if (!$today || !$tomorrow)
				{
					return '';
				}
				return " AND ($today <= info_enddate AND info_enddate < $tomorrow)";
			case 'limit':
				return " AND (info_modified >= $today OR NOT (info_status IN ('done','billed','cancelled')))";
		}
		return '';
	}

	/**
	 * initialise the internal $this->data to be empty
	 *
	 * only non-empty values got initialised
	 */
	function init()
	{
		$this->data = array(
			'info_owner'    => $this->user,
			'info_priority' => 1,
			'info_responsible' => array(),
		);
	}

	/**
	 * read InfoLog entry $info_id
	 *
	 * some cacheing is done to prevent multiple reads of the same entry
	 *
	 * @param array $where where clause for entry to read
	 * @return array|boolean the entry as array or False on error (eg. entry not found)
	 */
	function read(array $where)		// did _not_ ensure ACL
	{
		//error_log(__METHOD__.'('.array2string($where).')');
		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
		{
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}

		if (!$where || !($this->data = $this->db->select($this->info_table,'*',$where,__LINE__,__FILE__)->fetch()))
		{
			$this->init( );
			//error_log(__METHOD__.'('.array2string($where).') returning FALSE');
			return False;
		}
		// entry without uid --> create one based on our info_id and save it
		if (!$this->data['info_uid'] || strlen($this->data['info_uid']) < $minimum_uid_length)
		{
			$this->data['info_uid'] = common::generate_uid('infolog', $this->data['info_id']);
			$this->db->update($this->info_table,
				array('info_uid' => $this->data['info_uid']),
				array('info_id' => $this->data['info_id']), __LINE__,__FILE__);
		}
		if (!is_array($this->data['info_responsible']))
		{
			$this->data['info_responsible'] = $this->data['info_responsible'] ? explode(',',$this->data['info_responsible']) : array();
		}
		foreach($this->db->select($this->extra_table,'info_extra_name,info_extra_value',array('info_id'=>$this->data['info_id']),__LINE__,__FILE__) as $row)
		{
			$this->data['#'.$row['info_extra_name']] = $row['info_extra_value'];
		}
		//error_log(__METHOD__.'('.array2string($where).') returning '.array2string($this->data));
		return $this->data;
	}

	/**
	 * Read the status of the given infolog-ids
	 *
	 * @param array $ids array with id's
	 * @return array with id => status pairs
	 */
	function get_status($ids)
	{
		$this->db->select($this->info_table,'info_id,info_type,info_status,info_percent',array('info_id'=>$ids),__LINE__,__FILE__);
		while (($info = $this->db->row(true)))
		{
			switch ($info['info_type'].'-'.$info['info_status'])
			{
				case 'phone-not-started':
					$status = 'call';
					break;
				case 'phone-ongoing':
					$status = 'will-call';
					break;
				default:
					$status = $info['info_status'] == 'ongoing' ? $info['info_percent'].'%' : $info['info_status'];
			}
			$stati[$info['info_id']] = $status;
		}
		return $stati;
	}

	/**
	 * delete InfoLog entry $info_id AND the links to it
	 *
	 * @param int $info_id id of log-entry
	 * @param boolean $delete_children delete the children, if not set there parent-id to $new_parent
	 * @param int $new_parent new parent-id to set for subs
	 */
	function delete($info_id,$delete_children=True,$new_parent=0)  // did _not_ ensure ACL
	{
		//echo "<p>soinfolog::delete($info_id,'$delete_children',$new_parent)</p>\n";
		if ((int) $info_id <= 0)
		{
			return;
		}
		$this->db->delete($this->info_table,array('info_id'=>$info_id),__LINE__,__FILE__);
		$this->db->delete($this->extra_table,array('info_id'=>$info_id),__LINE__,__FILE__);
		egw_link::unlink(0,'infolog',$info_id);
		egw_index::delete('infolog',$info_id);

		if ($this->data['info_id'] == $info_id)
		{
			$this->init( );
		}
		// delete children, if they are owned by the user
		if ($delete_children)
		{
			$db2 = clone($this->db);	// we need an extra result-set
			$db2->select($this->info_table,'info_id',array(
					'info_id_parent'	=> $info_id,
					'info_owner'		=> $this->user,
				),__LINE__,__FILE__);
			while ($db2->next_record())
			{
				$this->delete($db2->f(0),$delete_children);
			}
		}
		// set parent_id to $new_parent or 0 for all not deleted children
		$this->db->update($this->info_table,array('info_id_parent'=>$new_parent),array('info_id_parent'=>$info_id),__LINE__,__FILE__);
	}

	/**
	 * Return array with children of $info_id as info_id => info_owner pairs
	 *
	 * @param int $info_id
	 * @return array with info_id => info_owner pairs
	 */
	function get_children($info_id)
	{
		$this->db->select($this->info_table,'info_id,info_owner',array(
			'info_id_parent'	=> $info_id,
		),__LINE__,__FILE__);

		$children = array();
		while (($row = $this->db->row(true)))
		{
			$children[$row['info_id']] = $row['info_owner'];
		}
		return $children;
	}

	/**
	 * changes or deletes entries with a spezified owner (for hook_delete_account)
	 *
	 * @param array $args hook arguments
	 * @param int $args['account_id'] account to delete
	 * @param int $args['new_owner']=0 new owner
	 */
	function change_delete_owner(array $args)  // new_owner=0 means delete
	{
		if (!(int) $args['new_owner'])
		{
			foreach($this->db->select($this->info_table,'info_id',array('info_owner'=>$args['account_id']),__LINE__,__FILE__,false,'','infolog') as $row)
			{
				$this->delete($row['info_id'],False);
			}
		}
		else
		{
			$this->db->update($this->info_table,array('info_owner'=>$args['new_owner']),array('info_owner'=>$args['account_id']),__LINE__,__FILE__,'infolog');
		}
		foreach($this->db->select($this->info_table,'info_id,info_responsible',
			$this->db->concat("','",'info_responsible',"','").' LIKE '.$this->db->quote('%,'.(int)$args['account_id'].',%'),
			__LINE__,__FILE__,false,'','infolog') as $row)
		{
			$new_responsible = explode(',',$row['info_responsible']);
			unset($new_responsible[array_search($args['account_id'],$new_responsible)]);
			if ((int)$args['new_owner']) $new_responsible[] = (int)$args['new_owner'];
			$new_responsible = implode(',',$new_responsible);
			$this->db->update($this->info_table,array(
				'info_responsible' => $new_responsible,
			),array('info_id' => $row['info_id']),__LINE__,__FILE__,'infolog');
		}

	}

	/**
	 * writes the given $values to InfoLog, a new entry gets created if info_id is not set or 0
	 *
	 * @param array $values with the data of the log-entry
	 * @param int $check_modified=0 old modification date to check before update (include in WHERE)
	 * @return int|boolean info_id, false on error or 0 if the entry has been updated in the meantime
	 */
	function write($values,$check_modified=0)  // did _not_ ensure ACL
	{
		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
		{
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}

		//echo "soinfolog::write(,$check_modified) values="; _debug_array($values);
		$info_id = (int) $values['info_id'];

		if (array_key_exists('info_responsible',$values))	// isset($values['info_responsible']) returns false for NULL!
		{
			$values['info_responsible'] = $values['info_responsible'] ? implode(',',$values['info_responsible']) : '0';
		}
		$table_def = $this->db->get_table_definitions('infolog',$this->info_table);
		$to_write = array();
		foreach($values as $key => $val)
		{
			if ($key != 'info_id' && isset($table_def['fd'][$key]))
			{
				$to_write[$key] = $this->data[$key] = $val;   // update internal data
			}
		}
		// writing no price as SQL NULL (required by postgres)
		if ($to_write['info_price'] === '') $to_write['info_price'] = NULL;

		if (($this->data['info_id'] = $info_id))
		{
			$where = array('info_id' => $info_id);
			if ($check_modified) $where['info_datemodified'] = $check_modified;
			if (!$this->db->update($this->info_table,$to_write,$where,__LINE__,__FILE__))
			{
				//error_log("### soinfolog::write(".print_r($to_write,true).") where=".print_r($where,true)." returning false");
				return false;	// Error
			}
			if ($check_modified && $this->db->affected_rows() < 1)
			{
				//error_log("### soinfolog::write(".print_r($to_write,true).") where=".print_r($where,true)." returning 0 (nothing updated, eg. condition not met)");
				return 0;	// someone else updated the modtime or deleted the entry
			}
		}
		else
		{
			if (!isset($to_write['info_id_parent'])) $to_write['info_id_parent'] = 0;	// must not be null

			$this->db->insert($this->info_table,$to_write,false,__LINE__,__FILE__);
			$info_id = $this->data['info_id'] = $this->db->get_last_insert_id($this->info_table,'info_id');
		}

		$update = array();
		// entry without (reasonable) uid --> create one based on our info_id and save it
		if (!$this->data['info_uid'] || strlen($this->data['info_uid']) < $minimum_uid_length)
		{
			$update['info_uid'] = $this->data['info_uid'] = common::generate_uid('infolog', $info_id);
		}
		// entry without caldav_name --> generate one based on info_id plus '.ics' extension
		if (empty($this->data['caldav_name']))
		{
			$update['caldav_name'] = $this->data['caldav_name'] = $info_id.'.ics';
		}
		if ($update)
		{
			$this->db->update($this->info_table,$update,
				array('info_id' => $info_id), __LINE__,__FILE__);
		}

		//echo "<p>soinfolog.write values= "; _debug_array($values);

		// write customfields now
		$to_delete = array();
		foreach($values as $key => $val)
		{
			if ($key[0] != '#')
			{
				continue;	// no customfield
			}
			$this->data[$key] = $val;	// update internal data

			if ($val)
			{
				$this->db->insert($this->extra_table,array(
						'info_extra_value'=>$val
					),array(
						'info_id'			=> $info_id,
						'info_extra_name'	=> substr($key,1),
					),__LINE__,__FILE__);
			}
			else
			{
				$to_delete[] = substr($key,1);
			}
		}
		if ($to_delete)
		{
			$this->db->delete($this->extra_table,array(
					'info_id'			=> $info_id,
					'info_extra_name'	=> $to_delete,
				),__LINE__,__FILE__);
		}
		// echo "<p>soinfolog.write this->data= "; _debug_array($this->data);
		//error_log("### soinfolog::write(".print_r($to_write,true).") where=".print_r($where,true)." returning id=".$this->data['info_id']);

		// update the index
		egw_index::save('infolog',$this->data['info_id'],$this->data['info_owner'],$this->data,$this->data['info_cat'],
			array('info_uid','info_type','info_status','info_confirm','info_access'));

		return $this->data['info_id'];
	}

	/**
	 * count the sub-entries of $info_id
	 *
	 * This is done now be search too (in key info_anz_subs), if DB can use sub-queries
	 *
	 * @param int|array $info_id id(s) of log-entry
	 * @return int|array the number of sub-entries or indexed by info_id, if array as param given
	 */
	function anzSubs( $info_id )
	{
		if (!is_array($info_id) || !$info_id)
		{
			if ((int)$info_id <= 0) return 0;
		}
		$counts = array();
		foreach($this->db->select($this->info_table,'info_id_parent,COUNT(*) AS info_anz_subs',array(
			'info_id_parent' => $info_id,
			"info_status != 'deleted'",	// dont count deleted subs as subs, as they are not shown by default
		),__LINE__,__FILE__,
			false,'GROUP BY info_id_parent','infolog') as $row)
		{
			$counts[$row['info_id_parent']] = (int)$row['info_anz_subs'];
		}
		//echo '<p>'.__METHOD__."($info_id) = ".array2string($counts)."</p>\n";
		return is_array($info_id) ? $counts : (int)array_pop($counts);
	}

	/**
	 * searches InfoLog for a certain pattern in $query
	 *
	 * @param string $query[order] column-name to sort after
	 * @param string $query[sort] sort-order DESC or ASC
	 * @param string $query[filter] string with combination of acl-, date- and status-filters, eg. 'own-open-today' or ''
	 * @param int $query[cat_id] category to use or 0 or unset
	 * @param string $query[search] pattern to search, search is done in info_from, info_subject and info_des
	 * @param string $query[action] / $query[action_id] if only entries linked to a specified app/entry show be used
	 * @param int &$query[start], &$query[total] nextmatch-parameters will be used and set if query returns less entries
	 * @param array $query[col_filter] array with column-name - data pairs, data == '' means no filter (!)
	 * @param boolean $query[subs] return subs or not, if unset the user preference is used
	 * @param int $query[num_rows] number of rows to return if $query[start] is set, default is to use the value from the general prefs
	 * @param string|array $query[cols]=null what to query, if set the recordset / iterator get's returned
	 * @param string $query[append]=null get's appended to sql query, eg. for GROUP BY
	 * @return array|iterator with id's as key of the matching log-entries or recordset/iterator if cols is set
	 */
	function search(&$query)
	{
		//error_log(__METHOD__.'('.array2string($query).')');
		$action2app = array(
			'addr'        => 'addressbook',
			'proj'        => 'projects',
			'event'       => 'calendar'
		);
		$action = isset($action2app[$query['action']]) ? $action2app[$query['action']] : $query['action'];
		if ($action != '')
		{
			$links = solink::get_links($action=='sp'?'infolog':$action,
				is_array($query['action_id']) ? $query['action_id'] : explode(',',$query['action_id']),'infolog');

			if (count($links))
			{
				$links = call_user_func_array('array_merge',$links);	// flatten the array
				$link_extra = ($action == 'sp' ? 'OR' : 'AND')." main.info_id IN (".implode(',',$links).')';
			}
		}
		$sortbycf='';
		if (!empty($query['order']) && (preg_match('/^[a-z_0-9, ]+$/i',$query['order']) || stripos($query['order'],'#')!==FALSE ) &&
			(empty($query['sort']) || preg_match('/^(DESC|ASC)$/i',$query['sort'])))
		{
			$order = array();
			foreach(explode(',',$query['order']) as $val)
			{
				$val = trim($val);
				if ($val[0] == '#')
				{
					$sortbycf = substr($val,1);
					$val = "cfsortcrit";
				}
				else
				{
					static $table_def;
					if (is_null($table_def)) $table_def = $this->db->get_table_definitions('infolog',$this->info_table);
					if (substr($val,0,5) != 'info_' && isset($table_def['fd']['info_'.$val])) $val = 'info_'.$val;
					if ($val == 'info_des' && $this->db->capabilities['order_on_text'] !== true)
					{
						if (!$this->db->capabilities['order_on_text']) continue;

						$val = sprintf($this->db->capabilities['order_on_text'],$val);
					}
				}
				$order[] = $val;
			}
			$ordermethod = 'ORDER BY ' . implode(',',$order) . ' ' . $query['sort'];
		}
		else
		{
			$ordermethod = 'ORDER BY info_datemodified DESC';   // newest first
		}
		$acl_filter = $filtermethod = $this->aclFilter($query['filter']);
		if (!$query['col_filter']['info_status'])  $filtermethod .= $this->statusFilter($query['filter']);
		$filtermethod .= $this->dateFilter($query['filter']);
		$cfcolfilter=0;
		if (is_array($query['col_filter']))
		{
			foreach($query['col_filter'] as $col => $data)
			{
				if (is_int($col))
				{
					$filtermethod .= ' AND '.$data;
					continue;
				}
				if ($col[0] != '#' && substr($col,0,5) != 'info_' && isset($table_def['fd']['info_'.$col])) $col = 'info_'.$col;
				if (!empty($data) && preg_match('/^[a-z_0-9]+$/i',$col))
				{
					switch ($col)
					{
						case 'info_responsible':
							$data = (int) $data;
							if (!$data) continue;
							$filtermethod .= ' AND ('.$this->responsible_filter($data)." OR info_responsible='0' AND ".
								$this->db->expression($this->info_table,array(
									'info_owner' => $data > 0 ? $data : $GLOBALS['egw']->accounts->members($data,true)
								)).')';
							break;

						case 'info_id':	// info_id itself is ambigous
							$filtermethod .= ' AND '.$this->db->expression($this->info_table,'main.',array('info_id' => $data));
							break;

						default:
							$filtermethod .= ' AND '.$this->db->expression($this->info_table,array($col => $data));
							break;
					}
				}
				if ($col[0] == '#' &&  $query['custom_fields'] && $data)
				{
					$filtermethod .= " AND main.info_id IN (SELECT DISTINCT info_id FROM $this->extra_table WHERE ";
					$custom_fields = config::get_customfields('infolog');

					if($custom_fields[substr($col,1)]['type'] == 'select' && $custom_fields[substr($col,1)]['rows'] > 1)
					{
						// Multi-select - any entry with the filter value selected matches
						$filtermethod .= $this->db->expression($this->extra_table, array(
							'info_extra_name' => substr($col,1),
							$this->db->concat("','",'info_extra_value',"','").' '.$this->db->capabilities[egw_db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$this->db->quote('%,'.$data.',%'),
						)).')';
					}
					else
					{
						$filtermethod .= $this->db->expression($this->extra_table,array(
							'info_extra_name'  => substr($col,1),
							'info_extra_value' => $data,
						)).')';
					}
					$cfcolfilter++;
				}
			}
		}
		//echo "<p>filtermethod='$filtermethod'</p>";

		if ((int)$query['cat_id'])
		{
			//$filtermethod .= ' AND info_cat='.intval($query['cat_id']).' ';
			$cats = $GLOBALS['egw']->categories->return_all_children((int)$query['cat_id']);
			$filtermethod .= ' AND info_cat'.(count($cats)>1? ' IN ('.implode(',',$cats).') ' : '='.(int)$query['cat_id']);
		}
		$join = $distinct = '';
		if ($query['query']) $query['search'] = $query['query'];	// allow both names
		if ($query['search'])			  // we search in _from, _subject, _des and _extra_value for $query
		{
			/* new code join the index
			if (ctype_digit($query['search']))	// search by ticket-number (numbers get never indexed!)
			{
				$sql_query = 'AND info_id='.(int)$query['search'];
			}
			else
			{
				$join = egw_index::sql_join_ids_by_keyword($query['search'],'infolog','info_id');
			}
			*/
			/* new code with info_id IN (subquery) --> way to slow
			$sql_query .= 'AND info_id IN ('.
				egw_index::sql_ids_by_keyword(explode(' ',$query['search']),egw_index::MATCH_CONTAINS,'infolog').
				// add search string itself, if it is numeric, to allow to search for a info_id/ticket number
				ctype_digit($query['search'] ? ' UNION (SELECT '.$this->db->quote($query['search']).')' : '').')';
			*/
			/* old code searching the table direct */
			$pattern = ' '.$this->db->capabilities[egw_db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$this->db->quote('%'.$query['search'].'%');

			$columns = array('info_from','info_addr','info_location','info_subject','info_extra_value');
			// at the moment MaxDB 7.5 cant cast nor search text columns, it's suppost to change in 7.6
			if ($this->db->capabilities['like_on_text']) $columns[] = 'info_des';

			$search = so_sql::search2criteria($query['search'], $wildcard, $op, null, $columns);
			$sql_query = 'AND ('.(is_numeric($query['search']) ? 'main.info_id='.(int)$query['search'].' OR ' : '').
				implode($op, $search) .')';

			$join = ($cfcolfilter>0 ? '':'LEFT')." JOIN $this->extra_table ON main.info_id=$this->extra_table.info_id ";
			// mssql and others cant use DISTICT if text columns (info_des) are involved
			$distinct = $this->db->capabilities['distinct_on_text'] ? 'DISTINCT' : '';
		}
		$pid = 'AND ' . $this->db->expression($this->info_table,array('info_id_parent' => ($action == 'sp' ?$query['action_id'] : 0)));

		if (!$GLOBALS['egw_info']['user']['preferences']['infolog']['listNoSubs'] &&
			 $action != 'sp' || isset($query['subs']) && $query['subs'])
		{
			$pid = '';
		}
		$ids = array( );
		if ($action == '' || $action == 'sp' || count($links))
		{
			$sql_query = "FROM $this->info_table main $join WHERE ($filtermethod $pid $sql_query) $link_extra";
			#error_log("infolog.so.search:\n" . print_r($sql_query, true));

			if ($this->db->Type == 'mysql' && $this->db->ServerInfo['version'] >= 4.0)
			{
				$mysql_calc_rows = 'SQL_CALC_FOUND_ROWS ';
				unset($query['total']);
			}
			else
			{
				$query['total'] = $this->db->query($sql="SELECT $distinct main.info_id ".$sql_query,__LINE__,__FILE__)->NumRows();
			}
			$info_customfield = '';
			if ($sortbycf != '')
			{
				$info_customfield = ", (SELECT DISTINCT info_extra_value FROM $this->extra_table sub2 where sub2.info_id=main.info_id AND info_extra_name=".$this->db->quote($sortbycf).") AS cfsortcrit ";
			}
			//echo "SELECT $distinct main.* $info_customfield $sql_query $ordermethod"."<br>";
			do
			{
				if (isset($query['start']) && isset($query['total']) && $query['start'] > $query['total'])
				{
					$query['start'] = 0;
				}
				$cols = isset($query['cols']) ? $query['cols'] : 'main.*';
				if (is_array($cols)) $cols = implode(',',$cols);
				$rs = $this->db->query($sql='SELECT '.$mysql_calc_rows.' '.$distinct.' '.$cols.' '.$info_customfield.' '.$sql_query.$query['append'].' '.$ordermethod,__LINE__,__FILE__,
					(int) $query['start'],isset($query['start']) ? (int) $query['num_rows'] : -1,false,egw_db::FETCH_ASSOC);
				//echo "<p>db::query('$sql',,,".(int)$query['start'].','.(isset($query['start']) ? (int) $query['num_rows'] : -1).")</p>\n";

				if ($mysql_calc_rows)
				{
					$query['total'] = $this->db->Link_ID->GetOne('SELECT FOUND_ROWS()');
				}
			}
			// check if start is behind total --> loop to set start=0
			while (isset($query['start']) && $query['start'] > $query['total']);

			if (isset($query['cols']))
			{
				return $rs;
			}
			foreach($rs as $info)
			{
				$info['info_responsible'] = $info['info_responsible'] ? explode(',',$info['info_responsible']) : array();

				$ids[$info['info_id']] = $info;
			}
			// if no specific custom field is selected, show/query all custom fields
			if ($ids && ($query['custom_fields'] || $query['csv_export']))
			{
				$where = array('info_id' => array_keys($ids));
				if (!($query['csv_export'] || strchr($query['selectcols'],'#') === false))
				{
					$where['info_extra_name'] = array();
					foreach(explode(',',$query['selectcols']) as $col)
					{
						if ($col[0] == '#') $where['info_extra_name'][] = substr($col,1);
					}
				}
				foreach($this->db->select($this->extra_table,'*',$where,__LINE__,__FILE__) as $row)
				{
					$ids[$row['info_id']]['#'.$row['info_extra_name']] = $row['info_extra_value'];
				}
			}
		}
		else
		{
			$query['start'] = $query['total'] = 0;
		}
		return $ids;
	}

	/**
	 * Query infolog for users with open entries, either own or responsible, with start or end within 4 days
	 *
	 * This functions tries to minimize the users really checked with the complete filters, as creating a
	 * user enviroment and running the specific check costs ...
	 *
	 * @return array with acount_id's groups get resolved to there memebers
	 */
	function users_with_open_entries()
	{
		$users = array();

		$this->db->select($this->info_table,'DISTINCT info_owner',array(
			str_replace(' AND ','',$this->statusFilter('open')),
			'(ABS(info_startdate-'.time().')<'.(4*24*60*60).' OR '.	// start_day within 4 days
			'ABS(info_enddate-'.time().')<'.(4*24*60*60).')',		// end_day within 4 days
		),__LINE__,__FILE__);
		while ($this->db->next_record())
		{
			$users[] = $this->db->f(0);
		}
		$this->db->select($this->info_table,'DISTINCT info_responsible',$this->statusFilter('open',false),__LINE__,__FILE__);
		while ($this->db->next_record())
		{
			foreach(explode(',',$this->db->f(0)) as $responsible)
			{
				if ($GLOBALS['egw']->accounts->get_type($responsible) == 'g')
				{
					$responsible = $GLOBALS['egw']->accounts->members($responsible,true);
				}
				if ($responsible)
				{
					foreach(is_array($responsible) ? $responsible : array($responsible) as $user)
					{
						if ($user && !in_array($user,$users)) $users[] = $user;
					}
				}
			}
		}
		return $users;
	}
}
