<?php
/**
 * EGroupware - InfoLog - Storage object, based on the generic Api\Storage
 *
 * Replaces infolog_so (still present, no longer instantiated by infolog_bo) as part of
 * doc/ai/projects/infolog-storage-migration.md. ACL decisions (check_access/is_responsible/
 * aclFilter) already moved to infolog_bo in an earlier commit (decision #4) - this class has
 * no ACL awareness of its own, matching every other app's Api\Storage-based SO class.
 *
 * Custom-field read/write is delegated to the inherited read_customfields()/save_customfields()
 * (Api\Storage already implements the exact date-time-with-UTC-"Z"-suffix convention InfoLog's
 * own hand-rolled code used to duplicate) rather than re-implemented. The bespoke main-table SQL
 * (responsible/cc joins via egw_infolog_users, category/free-text/RAG search, custom-field
 * multi-select filtering, sortbycf, action-based link filtering) is a faithful port of
 * infolog_so's logic, NOT yet rewritten to use Api\Storage's generic search()/process_search()
 * machinery - that is deliberately out of scope for this pass, see the migration doc.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

use EGroupware\Api;
use EGroupware\Api\Link;

class Storage extends Api\Storage
{
	/**
	 * Widened from Storage\Base's protected - infolog_bo::aclFilter() needs it to build raw
	 * ACL SQL fragments (infolog_bo has no $this->db of its own, per the migration doc's
	 * decision #1: composition, not infolog_bo extending Api\Storage directly).
	 *
	 * @var Api\Db
	 */
	public $db;

	/**
	 * Infolog delegation / iCal attendees table-name - no Api\Storage equivalent
	 *
	 * @var string
	 */
	public $users_table = 'egw_infolog_users';

	/**
	 * Offset between server- and user-time in h, for dateFilter()'s legacy "today"/"tomorrow"
	 * boundary math (unchanged from infolog_so - flagged for cleanup in a later phase, not
	 * this one, see the migration doc)
	 *
	 * @var int
	 */
	public $tz_offset;

	/**
	 * Current user (account_id) - Api\Storage/Storage\Base don't set this themselves
	 *
	 * @var int
	 */
	public $user;

	function __construct(?Api\Db $db=null)
	{
		// Set BEFORE calling parent::__construct(): Storage\Base::__construct() calls
		// $this->init() internally (to set up $this->data), and our own init() override below
		// reads $this->user - setting it after the parent call would leave that first init()
		// with $this->user still null, so $this->data['info_owner'] would be null too instead of
		// the acting user.
		$this->user = $GLOBALS['egw_info']['user']['account_id'];
		$this->tz_offset = $GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'];

		// $no_clone left at Api\Storage's own default (true), same as every other app's
		// Api\Storage-based SO class - Storage\Base's own save()/delete() always pass $this->app
		// explicitly to every Db::update()/insert()/delete() call rather than relying on the db
		// object's own internal app-context, and every db call in this class below does the same
		// (passing 'infolog' explicitly) for exactly that reason - no need to clone.
		parent::__construct('infolog', 'egw_infolog', 'egw_infolog_extra', '',
			'info_extra_name', 'info_extra_value', 'info_id', $db);
	}

	/**
	 * Filter for a given responsible user: info_responsible either contains a the user or one of his memberships
	 *
	 * @param int|array $users one or more account_ids
	 * @param boolean $deleted_too =false true: also use deleted entries
	 * @return string
	 */
	function responsible_filter($users, $deleted_too=false)
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
		$sql = "$this->users_table.account_id IN (".implode(',', array_map(array($this->db, 'quote'), $responsible)).')';

		if (!$deleted_too)
		{
			// we use NULL or true, not false!
			$sql .= " AND $this->users_table.info_res_deleted IS NULL";
		}
		return $sql;
	}

	/**
	 * generate sql to filter based on the status of the log-entry
	 *
	 * @param string $_filter done = done or billed, open = not (done, billed, cancelled or deleted), offer = offer
	 * @param boolean $prefix_and =true if true prefix the filter with ' AND '
	 * @return string the necessary sql
	 */
	function statusFilter($_filter = '',$prefix_and=true)
	{
		$vars = null;
		preg_match('/(done|open|offer|deleted|\+deleted|\+archived)/',$_filter,$vars);
		$filter = $vars[1]??null;

		switch ($filter)
		{
			case 'done':	$filter = "info_status IN ('done','billed','cancelled')"; break;
			case 'open':	$filter = "NOT (info_status IN ('done','billed','cancelled','deleted','template','nonactive','archive'))"; break;
			case 'offer':	$filter = "info_status = 'offer'";    break;
			case 'deleted': $filter = "info_status = 'deleted'";  break;
			case '+deleted':$filter = "NOT (info_status IN ('template','nonactive','archive'))"; break;
			case '+archived':$filter = "NOT (info_status IN ('deleted','template','nonactive'))"; break;
			default:        $filter = "NOT (info_status IN ('deleted','template','nonactive','archive'))"; break;
		}
		return ($prefix_and ? ' AND ' : '').$filter;
	}

	/**
	 * generate sql to filter based on the start- and enddate of the log-entry
	 *
	 * @param string $_filter upcoming = startdate is in the future
	 * 	today: startdate < tomorrow
	 * 	overdue: enddate < tomorrow
	 *  date: today <= startdate && startdate < tomorrow
	 *  enddate: today <= enddate && enddate < tomorrow
	 * 	limitYYYY/MM/DD not older or open
	 * @return string the necesary sql
	 */
	function dateFilter($_filter = '')
	{
		$vars = null;
		preg_match('/(open-upcoming|upcoming|today|overdue|date|enddate)([-\\/.0-9]*)/',$_filter,$vars);
		$filter = $vars[1]??null;

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
	function init($keys=array())		// $keys unused, but required to stay signature-compatible
		// with the parent Storage\Base::init($keys=array()) this overrides
	{
		unset($keys);
		$this->data = array(
			'info_owner'    => $this->user,
			'info_priority' => 1,
			'info_responsible' => array(),
		);
	}

	/**
	 * read InfoLog entry $info_id
	 *
	 * some caching is done to prevent multiple reads of the same entry
	 *
	 * @param array $where where clause for entry to read
	 * @return array|boolean the entry as array or False on error (eg. entry not found)
	 */
	function read($where, $extra_cols='', $join='')		// did _not_ ensure ACL - $extra_cols/$join
		// unused here, but required to stay signature-compatible with the parent
		// Api\Storage::read($keys, $extra_cols='', $join='') this overrides (PHP fatals on an
		// override with fewer parameters than its parent, even all-optional trailing ones)
	{
		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
		{
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}
		if (isset($where['info_id']))
		{
			$where[] = $this->db->expression($this->table_name, $this->table_name.'.', array('info_id' => $where['info_id']));
			unset($where['info_id']);
		}

		if (!$where ||
			!($this->data = $this->db->select($this->table_name,
			$this->table_name.'.*,'.$this->db->group_concat('account_id').' AS info_responsible,'.
			$this->db->group_concat('info_res_attendee').' AS info_cc,'.
			$this->table_name.'.info_id AS info_id',
			$where, __LINE__, __FILE__, false, "GROUP BY $this->table_name.info_id", 'infolog', 1,
			"LEFT JOIN $this->users_table ON $this->table_name.info_id=$this->users_table.info_id AND $this->users_table.info_res_deleted IS NULL")->fetch()))
		{
			$this->init( );
			return False;
		}
		// entry without uid --> create one based on our info_id and save it
		if (!$this->data['info_uid'] || strlen($this->data['info_uid']) < $minimum_uid_length)
		{
			$this->data['info_uid'] = Api\CalDAV::generate_uid('infolog', $this->data['info_id']);
			$this->db->update($this->table_name,
				array('info_uid' => $this->data['info_uid']),
				array('info_id' => $this->data['info_id']), __LINE__,__FILE__,'infolog');
		}
		if (!is_array($this->data['info_responsible']))
		{
			$this->data['info_responsible'] = $this->data['info_responsible'] ? explode(',',$this->data['info_responsible']) : array();
			foreach($this->data['info_responsible'] as $k => $v)
			{
				if (!is_numeric($v)) unset($this->data['info_responsible'][$k]);
			}
			$this->data['info_responsible'] = array_values($this->data['info_responsible']);
		}
		// Cast back to integer
		$this->data['info_id_parent'] = (int)$this->data['info_id_parent'];

		// custom fields: delegate REGISTERED ones to Api\Storage's own read_customfields(),
		// instead of re-implementing the date-time-with-UTC-"Z"-suffix hydration infolog_so
		// used to. read_customfields() only looks at registered $this->customfields names, so
		// UNREGISTERED "#"-prefixed rows (infolog_ical.inc.php's "##propertyname" iCal
		// X-property storage - a real, actively used mechanism) need a separate raw fetch, same
		// plain-string hydration infolog_so used for them (they're never date-time typed, since
		// there's no registered type info for them to begin with).
		if (($cfs = $this->read_customfields($this->data['info_id'])))
		{
			$this->data = array_merge($this->data, $cfs[$this->data['info_id']] ?? []);
		}
		$unregistered_where = array('info_id' => $this->data['info_id']);
		if ($this->customfields)
		{
			$unregistered_where[] = 'info_extra_name NOT IN ('.
				implode(',', array_map(array($this->db,'quote'), array_keys($this->customfields))).')';
		}
		foreach($this->db->select($this->extra_table,'info_extra_name,info_extra_value',$unregistered_where,__LINE__,__FILE__,false,'','infolog') as $row)
		{
			$this->data['#'.$row['info_extra_name']] = $row['info_extra_value'];
		}
		return $this->data;
	}

	/**
	 * delete InfoLog entry $info_id AND the links to it
	 *
	 * @param int $info_id id of log-entry
	 * @param boolean $delete_children delete the children, if not set there parent-id to $new_parent
	 * @param int $new_parent new parent-id to set for subs
	 */
	function delete($info_id=null,$delete_children=True,$new_parent=0)  // did _not_ ensure ACL -
		// $info_id must default to null, not stay required: must stay signature-compatible with
		// the parent Api\Storage::delete($keys=null, ...) this overrides
	{
		if ((int) $info_id <= 0)
		{
			return;
		}
		$this->db->delete($this->table_name,array('info_id'=>$info_id),__LINE__,__FILE__,'infolog');
		$this->db->delete($this->extra_table,array('info_id'=>$info_id),__LINE__,__FILE__,'infolog');
		$this->db->delete($this->users_table,array('info_id'=>$info_id),__LINE__,__FILE__,'infolog');
		Link::unlink(0,'infolog',$info_id);

		if ($this->data['info_id'] == $info_id)
		{
			$this->init( );
		}
		// delete children, if they are owned by the user
		if ($delete_children)
		{
			$db2 = clone($this->db);	// we need an extra result-set
			foreach($db2->select($this->table_name,'info_id',array(
					'info_id_parent'	=> $info_id,
					'info_owner'		=> $this->user,
				),__LINE__,__FILE__,false,'','infolog') as $row)
			{
				$this->delete($row['info_id'], $delete_children);
			}
		}
		// set parent_id to $new_parent or 0 for all not deleted children
		$this->db->update($this->table_name,array('info_id_parent'=>$new_parent),array('info_id_parent'=>$info_id),__LINE__,__FILE__,'infolog');
	}

	/**
	 * Return array with children of $info_id as info_id => info_owner pairs
	 *
	 * @param int $info_id
	 * @return array with info_id => info_owner pairs
	 */
	function get_children($info_id)
	{
		$children = array();
		foreach($this->db->select($this->table_name, 'info_id,info_owner', array(
			'info_id_parent'	=> $info_id,
		),__LINE__,__FILE__,false,'','infolog') as $row)
		{
			$children[$row['info_id']] = $row['info_owner'];
		}
		return $children;
	}

	/**
	 * changes or deletes entries with a spezified owner (for hook_delete_account)
	 *
	 * static (registered directly as the 'deleteaccount' hook via
	 * 'EGroupware\Infolog\Storage::change_delete_owner' in infolog/setup/setup.inc.php,
	 * matching every other namespaced hook's convention in this codebase - a
	 * Class::method hook value is dispatched as a genuinely static call, which silently
	 * no-ops for a non-static method). infolog_so (kept only as a zero-logic compatibility
	 * subclass for installations whose hook registration is still the pre-migration
	 * dotted 'infolog.infolog_so.change_delete_owner' string until their next setup/
	 * upgrade run) still resolves this correctly too - PHP allows calling a static method
	 * via an instance-shaped callable array, which is exactly what that older dispatch
	 * path uses.
	 *
	 * @param array $args hook arguments
	 * @param int $args['account_id'] account to delete
	 * @param int $args['new_owner']=0 new owner
	 */
	static function change_delete_owner(array $args)  // new_owner=0 means delete
	{
		$so = new self();

		if (!(int) $args['new_owner'])
		{
			foreach($so->db->select($so->table_name,'info_id',array('info_owner'=>$args['account_id']),__LINE__,__FILE__,false,'','infolog') as $row)
			{
				$so->delete($row['info_id'],False);
			}
		}
		else
		{
			$so->db->update($so->table_name,array('info_owner'=>$args['new_owner']),array('info_owner'=>$args['account_id']),__LINE__,__FILE__,'infolog');
		}

		if ($args['new_owner'])
		{
			// we cant just set the new owner, as he might be already set and we have a unique index
			$so->db->query('UPDATE '.$so->users_table.
				" LEFT JOIN $so->users_table new_owner ON new_owner.info_id=$so->users_table.info_id".
					" AND new_owner.account_id=".$so->db->quote($args['new_owner']).
				' SET '.$so->users_table.'.account_id='.$so->db->quote($args['new_owner']).
				' WHERE '.$so->users_table.'.account_id='.$so->db->quote($args['account_id']).
					' AND new_owner.account_id IS NULL',
				__LINE__, __FILE__);
		}
		$so->db->delete($so->users_table, array('account_id' => $args['account_id']), __LINE__, __FILE__, 'infolog');
	}

	/**
	 * writes the given $values to InfoLog, a new entry gets created if info_id is not set or 0
	 *
	 * @param array $values with the data of the log-entry
	 * @param int $check_modified =0 old modification date to check before update (include in WHERE)
	 * @param string $purge_cfs =null null=dont, 'ical'=only iCal X-properties (cfs name starting with "#"), 'all'=all cfs
	 * @param boolean $force_insert =false force using insert, even if an id is given eg. for import
	 * @return int|boolean info_id, false on error or 0 if the entry has been updated in the meantime
	 */
	function write($values, $check_modified=0, $purge_cfs=null, $force_insert=false)  // did _not_ ensure ACL
	{
		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
		{
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}

		$info_id = (int) $values['info_id'];

		$table_def = $this->db->get_table_definitions('infolog',$this->table_name);
		$to_write = array();
		foreach($values as $key => $val)
		{
			if (($key != 'info_id' || $force_insert && $info_id > 0) && isset($table_def['fd'][$key]))
			{
				$to_write[$key] = $this->data[$key] = $val;   // update internal data
			}
		}
		// If no price list use NULL not 0
		if($to_write['pl_id'] == '')
		{
			$to_write['pl_id'] = NULL;
		}
		// writing no price as SQL NULL (required by postgres)
		if ($to_write['info_price'] === '') $to_write['info_price'] = NULL;

		if (($this->data['info_id'] = $info_id) && !$force_insert)
		{
			$where = array('info_id' => $info_id);
			if ($check_modified)
			{
				$where['info_datemodified'] = $check_modified;

				// also check etag, if we got it
				if (isset($values['info_etag']))
				{
					$where['info_etag'] = $values['info_etag'];
				}
				unset($to_write['info_etag']);
				// and increment it
				$to_write[] = 'info_etag=info_etag+1';
			}
			if (!$this->db->update($this->table_name,$to_write,$where,__LINE__,__FILE__,'infolog'))
			{
				return false;	// Error
			}
			if ($check_modified && $this->db->affected_rows() < 1)
			{
				return 0;	// someone else updated the modtime or deleted the entry
			}
		}
		else
		{
			if (!isset($to_write['info_id_parent'])) $to_write['info_id_parent'] = 0;	// must not be null

			$this->db->insert($this->table_name,$to_write,false,__LINE__,__FILE__,'infolog');
			$info_id = $this->data['info_id'] = $this->db->get_last_insert_id($this->table_name,'info_id');
		}

		$update = array();
		// entry without (reasonable) uid --> create one based on our info_id and save it
		if (!$this->data['info_uid'] || strlen($this->data['info_uid']) < $minimum_uid_length)
		{
			$update['info_uid'] = $this->data['info_uid'] = Api\CalDAV::generate_uid('infolog', $info_id);
		}
		// entry without caldav_name --> generate one based on info_id plus '.ics' extension
		if (empty($this->data['caldav_name']))
		{
			$update['caldav_name'] = $this->data['caldav_name'] = $info_id.'.ics';
		}
		if ($update)
		{
			$this->db->update($this->table_name,$update,
				array('info_id' => $info_id), __LINE__,__FILE__,'infolog');
		}

		// write customfields now - delegate REGISTERED ones to Api\Storage's own
		// save_customfields(), instead of re-implementing the date-time-with-UTC-"Z"-suffix
		// persistence infolog_so used to. UNREGISTERED "#"-prefixed keys (infolog_ical.inc.php's
		// "##propertyname" iCal X-property storage - a real, actively used mechanism, NOT a
		// registered custom field, so save_customfields()/$this->customfields never sees it and
		// would otherwise silently drop it) keep the exact old raw insert/delete handling.
		if ($purge_cfs)
		{
			$where = array('info_id' => $info_id);
			if ($purge_cfs == 'ical') $where[] = "info_extra_name LIKE '#%'";
			$this->db->delete($this->extra_table,$where,__LINE__,__FILE__,'infolog');
		}
		$cf_data = array('info_id' => $info_id);
		$to_delete = array();
		foreach($values as $key => $val)
		{
			if ($key[0] != '#')
			{
				continue;
			}
			$this->data[$key] = $val;	// update internal data

			if (isset($this->customfields[substr($key,1)]))
			{
				$cf_data[$key] = $val;	// registered cf, let save_customfields() handle it below
				continue;
			}
			// unregistered pseudo-cf (eg. "##propertyname") - same raw handling infolog_so used
			if ($val)
			{
				$this->db->insert($this->extra_table,array(
						// store multivalued CalDAV properties as serialized array, everything else get comma-separated
						'info_extra_value'	=> is_array($val) ? ($key[1] == '#' ? json_encode($val) : implode(',',$val)) : $val,
					),array(
						'info_id'			=> $info_id,
						'info_extra_name'	=> substr($key,1),
					),__LINE__,__FILE__,'infolog');
			}
			else
			{
				$to_delete[] = substr($key,1);
			}
		}
		if ($to_delete && !$purge_cfs)
		{
			$this->db->delete($this->extra_table,array(
					'info_id'			=> $info_id,
					'info_extra_name'	=> $to_delete,
				),__LINE__,__FILE__,'infolog');
		}
		$this->save_customfields($cf_data);

		// update attendees/delegates
		if (array_key_exists('info_responsible', $values) || array_key_exists('info_cc', $values))
		{
			if (!is_array($values['info_responsible']))
			{
				$values['info_responsible'] = empty($values['info_responsible']) ? [] : explode(',', $values['info_responsible']);
			}
			$users = array_combine($values['info_responsible'], array_fill(0, count($values['info_responsible']), null));

			foreach(!empty($values['info_cc']) ? explode(',', $values['info_cc']) : array() as $email)
			{
				$email = trim($email);
				$matches = null;
				if (preg_match('/<[^>]+@[^>]+>$/', $email, $matches))
				{
					$hash = md5(strtolower($matches[1]));
				}
				else
				{
					$hash = md5(strtolower($email));
				}
				$users[$hash] = $email;
			}

			// mark removed attendees as deleted
			$this->db->update($this->users_table, array(
				'info_res_deleted' => true,
				'info_res_modifier' => $this->user,
			), array(
				'info_id' => $this->data['info_id'],
				'info_res_deleted IS NULL',
			)+(!$values['info_responsible'] ? array() :
				array(1=>'account_id NOT IN ('.implode(',', array_map(array($this->db, 'quote'), array_keys($users))).')')),
				__LINE__, __FILE__, 'infolog');

			// add newly added attendees
			if ($users)
			{
				$old_users = array();
				foreach($this->db->select($this->users_table,'account_id,info_res_attendee',array(
					'info_id' => $this->data['info_id'],
					'info_res_deleted IS NULL',
				), __LINE__, __FILE__, false, '', 'infolog') as $row)
				{
					$old_users[] = $row['account_id'];
				}
				foreach(array_diff(array_keys($users), $old_users) as $account_id)
				{
					$this->db->insert($this->users_table, array(
						'info_res_modifier' => $this->user,
						'info_res_status' => 'NEEDS-ACTION',
						'info_res_attendee' => $users[$account_id],
						'info_res_deleted' => null,
					), array(
						'info_id' => $this->data['info_id'],
						'account_id' => $account_id,
					), __LINE__, __FILE__, 'infolog');
				}
			}
		}

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
		foreach($this->db->select($this->table_name,'info_id_parent,COUNT(*) AS info_anz_subs',array(
			'info_id_parent' => $info_id,
			"info_status != 'deleted'",	// dont count deleted subs as subs, as they are not shown by default
		),__LINE__,__FILE__,
			false,'GROUP BY info_id_parent','infolog') as $row)
		{
			$counts[$row['info_id_parent']] = (int)$row['info_anz_subs'];
		}
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
	 * @param ?bool $query['return-iterator'] true, false, or default isset($query['cols'])
	 * @param ?string $query['join'] additional join(s)
	 * @param ?string $query['having'] HAVING clause
	 * @param string $query[append]=null get's appended to sql query, eg. for GROUP BY
	 * @param boolean $query['custom_fields']=false query custom-fields too, default not
	 * @param boolean $no_acl =false true: ignore all acl
	 * @param ?string $acl_filter =null pre-built ACL sql fragment to AND into the query, required unless
	 * 	$no_acl is true - ACL decisions are made by infolog_bo::aclFilter(), not here (this class has no
	 * 	ACL awareness of its own); defaults to '0=1' (matches nothing) rather than '1=1' if omitted, so a
	 * 	caller that forgets to pass it fails closed, not open
	 * @return array|iterator with id's as key of the matching log-entries or recordset/iterator if cols is set
	 *
	 * Named searchInfolog(), not search(): this takes $query BY REFERENCE (start/total get
	 * written back into the caller's array) with a completely different parameter shape than
	 * Api\Storage::search($criteria, ...) - overriding that method's by-value signature here
	 * would either break the by-ref start/total contract every infolog_bo call site relies on,
	 * or fatal on the signature-compatibility check, so this is a new method instead.
	 */
	function searchInfolog(&$query, $no_acl=false, $acl_filter=null)
	{
		$action2app = array(
			'addr'        => 'addressbook',
			'proj'        => 'projects',
			'event'       => 'calendar'
		);
		// query children independent of action
		if (empty($query['col_filter']['info_id_parent']))
		{
			$action = isset($action2app[$query['action']??null]) ? $action2app[$query['action']] : ($query['action'] ?? null);
			if ($action)
			{
				$links = Link\Storage::get_links($action=='sp'?'infolog':$action,
					is_array($query['action_id']) ? $query['action_id'] : explode(',',$query['action_id']),'infolog','',$query['col_filter']['info_status'] =='deleted');

				if (count($links))
				{
					$links = call_user_func_array('array_merge',$links);	// flatten the array
					$link_extra = ($action == 'sp' ? 'OR' : 'AND')." main.info_id IN (".implode(',',$links).')';
				}
			}
		}
		$sortbycf='';
		if (!empty($query['order']) && preg_match('/^#?[a-z_0-9, ]+$/i',$query['order']) &&
			(empty($query['sort']) || is_string($query['sort']) && preg_match('/^(DESC|ASC)$/i',$query['sort'])))
		{
			$order = array();
			foreach(explode(',',$query['order']) as $val)
			{
				$val = trim($val);
				if ($val[0] == '#')
				{
					$sortbycf = substr($val,1);
					$val = "cfsortcrit IS NULL,cfsortcrit";
				}
				else
				{
					static $table_def = null;
					if (is_null($table_def)) $table_def = $this->db->get_table_definitions('infolog',$this->table_name);
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
		$filtermethod = $no_acl ? '1=1' : ($acl_filter ?? '0=1');
		if (empty($query['col_filter']['info_status']))  $filtermethod .= $this->statusFilter($query['filter']);
		$filtermethod .= $this->dateFilter($query['filter']);
		if (isset($query['col_filter']) && is_array($query['col_filter']))
		{
			foreach($query['col_filter'] as $col => $data)
			{
				if (is_int($col))
				{
					$filtermethod .= ' AND '.$data;
					continue;
				}
				if ($col[0] != '#' && substr($col,0,5) != 'info_' && isset($table_def['fd']['info_'.$col])) $col = 'info_'.$col;
				if ((!empty($data) || (string)$data !== '') && preg_match('/^[a-z_0-9]+$/i',$col))
				{
					switch ($col)
					{
						case 'info_responsible':
							$data = (int) $data;
							if (!$data) continue 2;	// +1 for switch
							$filtermethod .= ' AND ('.$this->responsible_filter($data)." OR $this->users_table.account_id IS NULL AND ".
								$this->db->expression($this->table_name,array(
									'info_owner' => $data > 0 ? $data : $GLOBALS['egw']->accounts->members($data,true)
								)).')';
							break;

						case 'info_id':	// info_id itself is ambigous
							$filtermethod .= ' AND '.$this->db->expression($this->table_name,'main.',array('info_id' => $data));
							break;

						default:
							$filtermethod .= ' AND '.$this->db->expression($this->table_name,array($col => $data));
							break;
					}
				}
				if($col[0] == '#' && $data)
				{
					$filtermethod .= " AND main.info_id IN (SELECT DISTINCT info_id FROM $this->extra_table WHERE ";

					if($this->customfields[substr($col, 1)]['type'] == 'select')
					{
						// Multi-select - any entry with the filter value selected matches
						$filtermethod .= $this->db->expression($this->extra_table, array(
								'info_extra_name' => substr($col, 1),
								'(' . implode(' OR ', array_map(function ($v)
								{
									return "INSTR(CONCAT(',', info_extra_value, ','), CONCAT(',', " . $this->db->quote($v) . ", ',')) > 0";
								}, is_array($data) ? $data : explode(',', $data))) . ')',
						)).')';
					}
					else
					{
						$filtermethod .= $this->db->expression($this->extra_table,array(
							'info_extra_name'  => substr($col,1),
							'info_extra_value' => $data,
						)).')';
					}
				}
			}
		}

		if (!empty($query['cat_id']) && (int)$query['cat_id'])
		{
			$categories = new Api\Categories('','infolog');
			$cats = $categories->return_all_children((int)$query['cat_id']);
			$filtermethod .= ' AND info_cat'.(count($cats)>1? ' IN ('.implode(',',$cats).') ' : '='.(int)$query['cat_id']);
		}
		$join = $query['join'] ?? '';
		$distinct = '';
		if (!empty($query['query'])) $query['search'] = $query['query'];	// allow both names
		if (!empty($query['search']))			  // we search in _from, _subject, _des and _extra_value for $query
		{
			$filter = $extra_cols = [];
			$order_by = null;
			if (!class_exists('EGroupware\\Rag\\Embedding') ||
				!\EGroupware\Rag\Embedding::search2criteria('infolog', $query['search'], $order_by, $extra_cols, $filter))
			{
				// legacy search
				$columns = array('info_from','info_location','info_subject');
				// at the moment MaxDB 7.5 cant cast nor search text columns, it's suppost to change in 7.6
				if ($this->db->capabilities['like_on_text']) $columns[] = 'info_des';

				$wildcard = '%'; $op = null;
				// a throwaway instance, not $this: search2criteria() qualifies columns with
				// $this->table_name, but this query uses the "main" alias, not the real
				// egw_infolog table name $this->table_name holds everywhere else in this method
				$so_sql = new Api\Storage('infolog', $this->table_name, $this->extra_table, '', 'info_extra_name', 'info_extra_value', 'info_id', $this->db);
				$so_sql->table_name = 'main';
				$search = $so_sql->search2criteria($query['search'], $wildcard, $op, null, $columns, order_by: $order_by);
				$sql_query = 'AND ('.(is_numeric($query['search']) ? 'main.info_id='.(int)$query['search'].' OR ' : '').
					implode($op, $search) .')';
			}
			else
			{
				$sql_query = 'AND ('.(is_numeric($query['search']) ? 'main.info_id='.(int)$query['search'].' OR ' : '').
					current($filter).')';
			}
			// check if RAG-search changed order
			if (isset($order_by))
			{
				$ordermethod = 'ORDER BY '.$order_by;
			}
		}
		$join .= " LEFT JOIN $this->users_table ON main.info_id=$this->users_table.info_id";
		if (strpos($query['filter'], '+deleted') === false)
		{
			$join .= " AND $this->users_table.info_res_deleted IS NULL";
		}
		// do not return deleted attendees
		$join .= " LEFT JOIN $this->users_table attendees ON main.info_id=attendees.info_id AND attendees.info_res_deleted IS NULL";
		$group_by = ' GROUP BY main.info_id ';
		// check if $query['append'] already contains a GROUP BY clause
		if (!empty($query['append']) && stripos($query['append'], 'group by') !== false)
		{
			$query['append'] .= ',main.info_id ';
		}
		else
		{
			$query['append'] = $group_by;
		}
		if (!empty($query['having']))
		{
			$query['append'] .= ' HAVING '.$query['having'];
		}
		$pid = 'AND ' . $this->db->expression($this->table_name,array('info_id_parent' => ($action == 'sp' ?$query['action_id'] : 0)));

		if ($GLOBALS['egw_info']['user']['preferences']['infolog']['listNoSubs'] != '1' && $action != 'sp' ||
			($query['col_filter']['info_id_parent']??'') !== '' ||
			 isset($query['subs']) && $query['subs'] || $action != 'sp' && !empty($query['search']))
		{
			$pid = '';
		}
		$ids = array( );
		if ($action == '' || $action == 'sp' || count($links))
		{
			$sql_query = "FROM $this->table_name main $join WHERE ($filtermethod $pid ".($sql_query ?? '').') '.($link_extra??'');

			if (substr($this->db->Type, 0, 5) === 'mysql' && (float)$this->db->ServerInfo['version'] >= 4.0)
			{
				$mysql_calc_rows = 'SQL_CALC_FOUND_ROWS ';
				unset($query['total']);
			}
			else
			{
				$query['total'] = $this->db->query($sql="SELECT $distinct main.info_id ".$sql_query.$group_by,__LINE__,__FILE__)->NumRows();
			}
			$info_customfield = '';
			if ($sortbycf != '')
			{
				$sort_col = "(SELECT DISTINCT info_extra_value FROM $this->extra_table sub2 WHERE sub2.info_id=main.info_id AND info_extra_name=".$this->db->quote($sortbycf).")";
				switch($this->customfields[$sortbycf]['type'])
				{
					case 'int':
						$sort_col = $this->db->to_int($sort_col);
						break;
					case 'float':
						$sort_col = $this->db->to_double($sort_col);
						break;
				}
				$info_customfield = ", $sort_col AS cfsortcrit ";
			}
			do
			{
				if (isset($query['start']) && isset($query['total']) && $query['start'] > $query['total'])
				{
					$query['start'] = 0;
				}
				$cols = isset($query['cols']) ? $query['cols'] : 'main.*';
				if (is_array($cols)) $cols = implode(',',$cols);
				$cols .= ','.$this->db->group_concat('attendees.account_id').' AS info_responsible';
				$cols .= ','.$this->db->group_concat('attendees.info_res_attendee').' AS info_cc';
				if (!empty($extra_cols)) $cols .= ','.implode(',', $extra_cols);    // join relevance/distance from RAG search
				$rs = $this->db->query($sql='SELECT '.$mysql_calc_rows.' '.$distinct.' '.$cols.' '.$info_customfield.' '.$sql_query.
					$query['append'].$ordermethod,__LINE__,__FILE__,
					(int)($query['start']??0),isset($query['start']) ? (int) $query['num_rows'] : -1,false,Api\Db::FETCH_ASSOC);

				if ($mysql_calc_rows)
				{
					$query['total'] = $this->db->Link_ID->GetOne('SELECT FOUND_ROWS()');
				}
			}
			// check if start is behind total --> loop to set start=0
			while (isset($query['start']) && $query['start'] > $query['total']);

			if ($query['return-iterator'] ?? isset($query['cols']))
			{
				return $rs;
			}
			foreach($rs as $info)
			{
				$info['info_responsible'] = $info['info_responsible'] ? array_unique(explode(',',$info['info_responsible'])) : array();
				foreach($info['info_responsible'] as $k => $v)
				{
					if (!is_numeric($v)) unset($info['info_responsible'][$k]);
				}
				$info['info_responsible'] = array_values($info['info_responsible']);

				$ids[$info['info_id']] = $info;
			}
			static $index_load_cfs = null;
			if (is_null($index_load_cfs) && !empty($query['col_filter']['info_type']))
			{
				$config_data = Api\Config::read('infolog');
				$index_load_cfs = $config_data['index_load_cfs'] ?? [];
				if (!is_array($index_load_cfs)) $index_load_cfs = explode(',', $index_load_cfs);
			}
			// if no specific custom field is selected, show/query all custom fields
			if ($ids && (!empty($query['custom_fields']) || !empty($query['csv_export']) ||
				$index_load_cfs && !empty($query['col_filter']['info_type']) && in_array($query['col_filter']['info_type'],$index_load_cfs)))
			{
				// delegate REGISTERED cfs to Api\Storage's own read_customfields(), instead of
				// re-implementing the date-time-with-UTC-"Z"-suffix hydration infolog_so used to
				$field_names = null;	// null = all cfs, matching read_customfields()'s default
				if (!($query['csv_export'] || strchr(is_array($query['selectcols']) ? implode(',',$query['selectcols']):$query['selectcols'],'#') === false ||
					$index_load_cfs && $query['col_filter']['info_type'] && in_array($query['col_filter']['info_type'],$index_load_cfs)))
				{
					$field_names = array();
					foreach(is_array($query['selectcols']) ? $query['selectcols'] : explode(',',$query['selectcols']) as $col)
					{
						if ($col[0] == '#') $field_names[] = substr($col,1);
					}
				}
				foreach($this->read_customfields(array_keys($ids), $field_names) as $id => $data)
				{
					$ids[$id] = array_merge($ids[$id], $data);
				}
				// UNREGISTERED "#"-prefixed values (eg. infolog_ical.inc.php's "##propertyname"
				// iCal X-property storage) aren't in $this->customfields, so read_customfields()
				// never returns them - fetch them separately, same raw plain-string hydration
				// infolog_so used to (they're never date-time typed, since there's no registered
				// type info for them to begin with).
				$unregistered_names = $field_names === null ? null : array_values(array_diff($field_names, array_keys($this->customfields)));
				if ($unregistered_names === null || $unregistered_names)
				{
					$where = array('info_id' => array_keys($ids));
					if ($unregistered_names)
					{
						$where['info_extra_name'] = $unregistered_names;
					}
					elseif ($this->customfields)
					{
						$where[] = 'info_extra_name NOT IN ('.
							implode(',', array_map(array($this->db,'quote'), array_keys($this->customfields))).')';
					}
					foreach($this->db->select($this->extra_table,'info_id,info_extra_name,info_extra_value',$where,__LINE__,__FILE__,false,'','infolog') as $row)
					{
						$ids[$row['info_id']]['#'.$row['info_extra_name']] = $row['info_extra_value'];
					}
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

		foreach($this->db->select($this->table_name,'DISTINCT info_owner',array(
			str_replace(' AND ','',$this->statusFilter('open')),
			'(ABS(info_startdate-'.time().')<'.(4*24*60*60).' OR '.	// start_day within 4 days
			'ABS(info_enddate-'.time().')<'.(4*24*60*60).')',		// end_day within 4 days
		),__LINE__,__FILE__,false,'','infolog') as $row)
		{
			$users[] = $row['info_owner'];
		}
		foreach($this->db->select($this->table_name, "DISTINCT $this->users_table.account_id AS account_id",
			$this->statusFilter('open',false), __LINE__, __FILE__, false, '', 'infolog', 0,
			"JOIN $this->users_table ON $this->table_name.info_id=$this->users_table.info_id AND info_res_deleted IS NULL") as $row)
		{
			$responsible = $row['account_id'];

			if ($GLOBALS['egw']->accounts->get_type($responsible) == 'g')
			{
				$responsible = $GLOBALS['egw']->accounts->members($responsible,true);
			}
			if ($responsible)
			{
				foreach((array)$responsible as $user)
				{
					if ($user && !in_array($user,$users)) $users[] = $user;
				}
			}
		}
		return $users;
	}
}