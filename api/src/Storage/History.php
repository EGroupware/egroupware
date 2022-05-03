<?php
/**
 * EGroupware API - Storage history logging
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @copyright 2001 by Joseph Engo <jengo@phpgroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> new DB-methods and search
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage storage
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Storage;

use EGroupware\Api;

/**
 * Record history logging service
 *
 * This class need to be instantiated for EACH app, which wishes to use it!
 */
class History
{
	/**
	 * Reference to the global db object
	 *
	 * @var Api\Db
	 */
	var $db;
	const TABLE = 'egw_history_log';

	/**
	 * PGP begin / end flags so we can handle them specially
	 */
	const BEGIN_PGP = '-----BEGIN PGP MESSAGE-----';
	const END_PGP = '-----END PGP MESSAGE-----';

	/**
	 * App.name this class is instanciated for / working on
	 *
	 * @var string
	 */
	var $appname;
	var $user;
	var $types = array(
		'C' => 'Created',
		'D' => 'Deleted',
		'E' => 'Edited'
	);

	/**
	 * Constructor
	 *
	 * @param string $appname app name this instance operates on
	 */
	function __construct($appname = '', $user = null)
	{
		$this->appname = $appname ?: $GLOBALS['egw_info']['flags']['currentapp'];
		$this->user = $user ?: $GLOBALS['egw_info']['user']['account_id'];

		if (isset($GLOBALS['egw_setup']) && is_object($GLOBALS['egw_setup']->db))
		{
			$this->db = $GLOBALS['egw_setup']->db;
		}
		else
		{
			$this->db = $GLOBALS['egw']->db;
		}
	}

	/**
	 * Delete the history-log of one or multiple records of $this->appname
	 *
	 * @param int|array $record_id one or more id's of $this->appname, or null to delete ALL records of $this->appname
	 * @return int number of deleted records/rows (0 is not necessaryly an error, it can just mean there's no record!)
	 */
	function delete($record_id)
	{
		$where = array('history_appname' => $this->appname);

		if(is_array($record_id) || is_numeric($record_id))
		{
			$where['history_record_id'] = $record_id;
		}
		$this->db->delete(self::TABLE, $where, __LINE__, __FILE__);

		return $this->db->affected_rows();
	}

	/**
	 * Delete history log of a certain field
	 * @param string $record_id ID of the record
	 * @param string $status Field name / ID
	 */
	function delete_field($record_id, $status)
	{
		$where = array(
			'history_appname' => $this->appname,
			'history_status'  => $status
		);

		if(is_array($record_id) || is_numeric($record_id))
		{
			$where['history_record_id'] = $record_id;
		}
		$this->db->delete(self::TABLE, $where, __LINE__, __FILE__);

		return $this->db->affected_rows();
	}

	/**
	 * Add a history record, if $new_value != $old_value
	 *
	 * @param string $status 2 letter code: eg. $this->types: C=Created, D=Deleted, E=Edited
	 * @param int $record_id it of the record in $this->appname (set by the constructor)
	 * @param string $new_value new value
	 * @param string $old_value old value
	 */
	function add($status, $record_id, $new_value, $old_value)
	{
		if($new_value != $old_value)
		{
			$share_with = static::get_share_with($this->appname, $record_id);

			$this->db->insert(self::TABLE, array(
				'history_record_id' => $record_id,
				'history_appname'   => $this->appname,
				'history_owner'     => $this->user,
				'history_status'    => $status,
				'history_new_value' => $new_value,
				'history_old_value' => $old_value,
				'history_timestamp' => time(),
				'sessionid'         => $GLOBALS['egw']->session->sessionid_access_log,
				'share_email'       => $share_with,
			),                false, __LINE__, __FILE__);
		}
	}

	/**
	 * Static function to add a history record
	 */
	public static function static_add($appname, $id, $user, $field_code, $new_value, $old_value = '')
	{
		if($new_value != $old_value)
		{
			$share_with = static::get_share_with($appname, $id);

			$GLOBALS['egw']->db->insert(self::TABLE, array(
				'history_record_id' => $id,
				'history_appname'   => $appname,
				'history_owner'     => (int)$user,
				'history_status'    => $field_code,
				'history_new_value' => $new_value,
				'history_old_value' => $old_value,
				'history_timestamp' => time(),
				'sessionid'         => $GLOBALS['egw']->session->sessionid_access_log,
				'share_email'       => $share_with,
			),                          false, __LINE__, __FILE__);
		}
	}

	/**
	 * If a record was accessed via a share, we want to record who the entry was shared with, rather than the current
	 * user.  Since multiple shares can be active at once, and they might not be for the current entry, we check to
	 * see if the given entry was accessed via a share, and which share was used.
	 * The share's share_with is recorded into the history for some hope of tracking who made the change.
	 * share_with is a list of email addresses, and may be empty.
	 *
	 * @param $appname
	 * @param $id
	 *
	 * @return ?string
	 */
	static function get_share_with($appname, $id)
	{
		$share_with = null;
		foreach(isset($GLOBALS['egw']->sharing) ? $GLOBALS['egw']->sharing : [] as $token => $share_obj)
		{
			// Make sure share is of the correct type to access an entry, and it is the correct entry
			if($share_obj instanceof Api\Link\Sharing && "$appname::$id" === $share_obj['share_path'])
			{
				$share_with .= $share_obj->get_share_with();
			}
		}
		return $share_with;
	}

	/**
	 * Search history-log
	 *
	 * @param array|int $filter array with filters, or int record_id
	 * @param string $order ='history_id' sorting after history_id is identical to history_timestamp
	 * @param string $sort ='DESC'
	 * @param int $limit =null only return this many entries
	 * @return array of arrays with keys id, record_id, appname, owner (account_id), status, new_value, old_value,
	 *    timestamp (Y-m-d H:i:s in servertime), user_ts (timestamp in user-time)
	 */
	function search($filter, $order = 'history_id', $sort = 'DESC', $limit = null)
	{
		if(!is_array($filter))
		{
			$filter = is_numeric($filter) ? array('history_record_id' => $filter) : array();
		}

		if(!$order || !preg_match('/^[a-z0-9_]+$/i', $order) || !preg_match('/^(asc|desc)?$/i', $sort))
		{
			$orderby = 'ORDER BY history_id DESC';
		}
		else
		{
			$orderby = "ORDER BY $order $sort";
		}
		foreach($filter as $col => $value)
		{
			if(!is_numeric($col) && substr($col, 0, 8) != 'history_')
			{
				$filter['history_' . $col] = $value;
				unset($filter[$col]);
			}
		}
		if(!isset($filter['history_appname']))
		{
			$filter['history_appname'] = $this->appname;
		}

		// do not try to read all history entries of an app
		if(!$filter['history_record_id'])
		{
			return array();
		}

		$rows = array();
		foreach($this->db->select(self::TABLE, '*', $filter, __LINE__, __FILE__,
								  isset($limit) ? 0 : false, $orderby, 'phpgwapi', $limit
		) as $row)
		{
			$row['user_ts'] = $this->db->from_timestamp($row['history_timestamp']) + 3600 * $GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'];
			$rows[] = Api\Db::strip_array_keys($row, 'history_');
		}
		return $rows;
	}

	/**
	 * Get a slice of history records
	 *
	 * Similar to search(), except this one can take a start and a number of records
	 *
	 * @see Base::get_rows()
	 */
	public static function get_rows(&$query, &$rows)
	{
		$filter = array();
		$rows = array();
		$filter['history_appname'] = $query['appname'];
		$filter['history_record_id'] = $query['record_id'];
		if(is_array($query['colfilter']))
		{
			foreach($query['colfilter'] as $column => $value)
			{
				$filter[$column] = $value;
			}
		}

		// filter out private (or no longer defined) custom fields
		if($filter['history_appname'])
		{
			$to_or[] = "history_status NOT LIKE '#%'";
			// explicitly allow "##" used to store iCal/vCard X-attributes
			if(in_array($filter['history_appname'], array('calendar', 'infolog', 'addressbook')))
			{
				$to_or[] = "history_status LIKE '##%'";
			}
			if(($cfs = Customfields::get($filter['history_appname'])))
			{
				$to_or[] = 'history_status IN (' . implode(',', array_map(function ($str)
															  {
																  return $GLOBALS['egw']->db->quote('#' . $str);
															  }, array_keys($cfs))
					) . ')';
			}
			$filter[] = '(' . implode(' OR ', $to_or) . ')';
		}
		$_query = array(array(
							'table' => self::TABLE,
							'cols'  => array(
								'history_id',
								'history_record_id',
								'history_appname',
								'history_owner',
								'history_status',
								'history_new_value',
								'history_timestamp',
								'history_old_value',
								'share_email'
							),
							'where' => $filter,
						));

		// Add in files, if possible
		if($GLOBALS['egw_info']['user']['apps']['filemanager'] &&
			($sqlfs_sw = new Api\Vfs\Sqlfs\StreamWrapper()) &&
			($file = $sqlfs_sw->url_stat("/apps/{$query['appname']}/{$query['record_id']}", STREAM_URL_STAT_LINK)))
		{
			$_query[] = array(
				'table' => Api\Vfs\Sqlfs\StreamWrapper::TABLE,
				'cols'  => array('fs_id', 'fs_dir', "'filemanager'", 'COALESCE(fs_modifier,fs_creator)', "'~file~'",
								 'fs_name', 'fs_modified', 'fs_mime', "'' AS share_email"),
				'where' => array('fs_dir' => $file['ino'])
			);
		}
		$new_file_id = array();
		foreach($GLOBALS['egw']->db->union(
			$_query,
			__LINE__, __FILE__,
			preg_match('/^(([a-z0-9_]+) (ASC|DESC),?)+$/', $query['order'].' '.($query['sort'] ?: 'DESC')) ?
				' ORDER BY ' . $query['order'] . ' ' . ($query['sort'] ?: 'DESC') : ' ORDER BY history_timestamp DESC,history_id ASC',
			$query['start'],
			$query['num_rows']
		) as $row)
		{
			$row['user_ts'] = Api\DateTime::server2user($GLOBALS['egw']->db->from_timestamp($row['history_timestamp']), Api\DateTime::ET2);

			// Explode multi-part values
			foreach(array('history_new_value', 'history_old_value') as $field)
			{
				if(strpos($row[$field], Tracking::ONE2N_SEPERATOR) !== false)
				{
					$row[$field] = explode(Tracking::ONE2N_SEPERATOR, $row[$field]);
				}
			}
			if($row['history_old_value'] !== Tracking::DIFF_MARKER && (
					static::needs_diff($row['history_status'], $row['history_old_value']) ||
					static::needs_diff($row['history_status'], $row['history_old_value'])
				))
			{
				// Larger text stored with full old / new value - calculate diff and just send that
				$diff = new \Horde_Text_Diff('auto', array(explode("\n", $row['history_old_value']),
														   explode("\n", $row['history_new_value'])));
				$renderer = new \Horde_Text_Diff_Renderer_Unified();
				$row['history_new_value'] = $renderer->render($diff);
				$row['history_old_value'] = Tracking::DIFF_MARKER;
			}
			// Get information needed for proper display
			if($row['history_appname'] == 'filemanager')
			{
				$new_version = $new_file_id[$row['history_new_value']];
				$new_file_id[$row['history_new_value']] = count($rows);
				$path = Api\Vfs\Sqlfs\StreamWrapper::id2path($row['history_id']);

				// Apparently we don't have to do anything with it, just ask...
				// without this, previous versions are not handled properly
				Api\Vfs::getExtraInfo($path);

				$row['history_new_value'] = array(
					'path' => $path,
					'name' => Api\Vfs::basename($path),
					'mime' => $row['history_old_value']
				);
				$row['history_old_value'] = '';
				if($new_version !== null)
				{
					$rows[$new_version]['old_value'] = $row['history_new_value'];
				}
			}

			// Properly format customfield date values
			if($row['history_status'][0] == '#' && $cfs && array_key_exists(substr($row['history_status'], 1), $cfs) &&
				in_array($cfs[substr($row['history_status'], 1)]['type'], ['date', 'date-time']))
			{
				if($row['history_new_value'])
				{
					$row['history_new_value'] = Api\DateTime::to($row['history_new_value'], Api\DateTime::ET2);
				}
				if($row['history_old_value'])
				{
					$row['history_old_value'] = Api\DateTime::to($row['history_old_value'], Api\DateTime::ET2);
				}
			}

			$rows[] = Api\Db::strip_array_keys($row, 'history_');
		}
		$total = $GLOBALS['egw']->db->union($_query, __LINE__, __FILE__)->NumRows();

		// allow to hook into get_rows of other apps
		Api\Hooks::process(array(
							   'hook_location' => 'etemplate2_history_get_rows',
							   'get_rows'      => __METHOD__,
							   'value'         => &$query,
							   'rows'          => &$rows,
							   'total'         => &$total,
						   ), array(), true);    // true = no permission check

		return $total;
	}

	/**
	 * Check to see if we would rather store a unified diff of the changes rather
	 * than full old and new values.  We don't care for small text values, but
	 * any long or multi-line values will be diff-ed.
	 *
	 * We do not store a diff of encrypted values, since that winds up as nonsense.
	 *
	 * @param string $name Field name, used to do some decision making
	 * @param string $value Value, either old or new
	 * @return boolean
	 */
	public static function needs_diff($name, $value)
	{
		// No diff on arrays or encrypted content
		if (is_array($value) || strpos($value, static::BEGIN_PGP) == 0 && strpos($value, static::END_PGP) !== FALSE)
		{
			return false;
		}
		return $name == 'note' ||    // Addressbook
			strpos($name, 'description') !== false ||    // Calendar, Records, Timesheet, ProjectManager, Resources
			$name == 'De' ||    // Tracker, InfoLog
			($value && (strlen($value) > 200 || strstr($value, "\n") !== FALSE));
	}
}