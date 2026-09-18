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
 * This class needs to be instantiated for EACH app, which wishes to use it!
 *
 * There is only an automatic encoding and decoding of DateTime values.
 * Everything else must be scalar!
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
				'history_new_value' => self::encode($new_value),
				'history_old_value' => self::encode($old_value),
				'history_timestamp' => time(),
				'sessionid'         => $GLOBALS['egw']->session->sessionid_access_log,
				'share_email'       => $share_with,
			),                false, __LINE__, __FILE__);
		}
		$this->doRetention();
	}

	/**
	 * Remove history entries past their retention period
	 *
	 * Take care to not block the table for too long by only deleting 2000 entries per run.
	 *
	 * @return void
	 * @throws Api\Db\Exception
	 * @throws Api\Db\Exception\InvalidSql
	 * @return ?int number of deleted rows, or null if no retention-period is configured
	 */
	protected function doRetention() : ?int
	{
		static $once=0;
		if (!empty($GLOBALS['egw_info']['server']['history_retention']) &&
			// run retention only weekends or after 18h
			(in_array(date('l'), ['Saturday', 'Sunday']) || (new Api\DateTime())->format('H') >= 18) &&
			!$once++)
		{
			Api\Egw::on_shutdown(function()
			{
				$cut_off_date = ((int)date('Y') - $GLOBALS['egw_info']['server']['history_retention']) . '-01-01';

				$this->db->query('DELETE FROM ' . self::TABLE .
					' WHERE history_timestamp < ' . $this->db->quote($cut_off_date) .
					' ORDER BY history_timestamp LIMIT 5000',
					__LINE__, __FILE__);
			});
		}
		return null;
	}

	/**
	 * Static function to add a history record
	 */
	public static function static_add($appname, $id, $user, $field_code, $new_value, $old_value = '')
	{
		(new self($appname, $user))->add($field_code, $id, $new_value, $old_value);
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
			if($share_obj instanceof Api\Link\Sharing && "$appname::$id" === $share_obj->get_path())
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
			$row['history_new_value'] = self::decode($row['history_new_value']);
			$row['history_old_value'] = self::decode($row['history_old_value']);
			$rows[] = Api\Db::strip_array_keys($row, 'history_');
		}
		return $rows;
	}

	/**
	 * Encoding \DateTimeInterface objects as JSON
	 *
	 * @param mixed $value
	 * @return false|mixed|string
	 */
	protected static function encode($value)
	{
		if (is_object($value) && is_a($value, 'DateTimeInterface'))
		{
			$value = json_encode($value);
		}
		return $value;
	}

	/**
	 * Detecting encoded \DateTimeInterface values and returning them as DateTime objects again
	 *
	 * @param string|null $value
	 * @return DateTime|string|null
	 */
	protected static function decode(?string $value)
	{
		if ($value && str_starts_with($value, '{"date":"') && substr($value, -1) === '}' &&
			strpos($value, ',"timezone":"') !== false)
		{
			$arr = json_decode($value, true);

			// convert DateTime values back
			if (is_array($arr) && isset($arr['date']) && isset($arr['timezone']))
			{
				$value = new Api\DateTime($arr['date'], new \DateTimeZone($arr['timezone']));
			}
		}
		return $value;
	}

	/**
	 * Status value the attachment rows carry, and therefore the value a "changed field" filter
	 * uses to include or exclude them.
	 *
	 * Attachments do not live in the history table at all - they are UNIONed in from the VFS (see
	 * get_rows()), so none of the history columns a filter names exist on them.  Rather than
	 * inventing a separate "show attachments" switch, the filter reuses the status value the rows
	 * already carry, which is also the one the history-log widget already offers as an option.
	 */
	const FILE_STATUS = '~file~';

	/**
	 * Column filters get_rows() knows how to answer, mapped to their history-table column.
	 *
	 * Deliberately an allow-list: a filter key that is not in here is dropped rather than passed
	 * to the DB, so an unexpected key can never become a WHERE clause.  'user_ts' is the
	 * user-time timestamp the client sees and filters on; it maps to the server-time column.
	 */
	protected static $col_filters = array(
		'status'   => 'history_status',
		'owner'    => 'history_owner',
		'user_ts'  => 'history_timestamp',
	);

	/**
	 * Translate client column-filters into a WHERE array for the history table
	 *
	 * @param array $col_filter filter values keyed by client column name
	 * @param string $appname app whose custom-fields are allowed as filter keys
	 * @return array WHERE fragments/values suitable for Api\Db::select()
	 */
	protected static function columnFilters(array $col_filter, $appname) : array
	{
		$filter = array();
		$cfs = $appname ? Customfields::get($appname) : array();
		foreach($col_filter as $column => $value)
		{
			// Ignore numeric keys: they would be raw SQL fragments, which must never come from a
			// filter (the only trusted SQL fragments are the ones this method builds itself).
			if(is_int($column) || $value === '' || $value === null || $value === array())
			{
				continue;
			}
			// A custom-field filter names the field directly, eg. '#mycf'.  Accept only fields
			// that currently exist for this app, so this cannot be used to probe arbitrary values.
			if($column[0] === '#')
			{
				if(!isset($cfs[substr($column, 1)]))
				{
					continue;
				}
				$filter['history_status'] = $column;
				continue;
			}
			if(!isset(self::$col_filters[$column]))
			{
				continue;
			}
			$db_col = self::$col_filters[$column];
			// A date filter arrives as the from/to pair Et2DateRange produces.  The column is
			// server-time, the value is user-time, so it has to be converted rather than compared
			// as-is - a user 2h ahead of the server would otherwise lose 2h of their own history.
			if($column === 'user_ts')
			{
				foreach(self::dateRangeFilter($db_col, $value) as $fragment)
				{
					$filter[] = $fragment;
				}
				continue;
			}
			$filter[$db_col] = $value;
		}
		return $filter;
	}

	/**
	 * Build the WHERE fragments for a from/to date filter on a server-time timestamp column
	 *
	 * @param string $db_col server-time column to compare
	 * @param array|string $value ['from' => ..., 'to' => ...], or a single date
	 * @return string[] SQL fragments
	 */
	protected static function dateRangeFilter($db_col, $value) : array
	{
		$fragments = array();
		$range = is_array($value) ? $value : array('from' => $value, 'to' => $value);
		foreach(array('from' => '>=', 'to' => '<=') as $key => $op)
		{
			if(empty($range[$key]))
			{
				continue;
			}
			$date = new Api\DateTime($range[$key]);
			// The picker is day-granular, so a range always means whole days: start of the 'from'
			// day, end of the 'to' day.  Et2DateRange sends W3C strings with a midnight time
			// ("2026-09-16T00:00:00Z"), so keying this off "does the value carry a time?" - as an
			// earlier version did - never fired, and `to` stayed at midnight: the user's last
			// chosen day was excluded entirely, and a same-day range matched nothing at all.
			$date->setTime(...($key === 'to' ? [23, 59, 59] : [0, 0, 0]));
			$fragments[] = $db_col . ' ' . $op . ' ' .
				$GLOBALS['egw']->db->quote($GLOBALS['egw']->db->to_timestamp($date->format('ts')));
		}
		return $fragments;
	}

	/**
	 * Build the WHERE fragment for a free-text search over the stored values
	 *
	 * Cheap despite the LIKE: every history query is already scoped to one record of one app, so
	 * this never scans more than a single entry's history rows.
	 *
	 * @param string|null $search
	 * @return string|null SQL fragment, or null if there is nothing to search for
	 */
	protected static function searchFilter($search) : ?string
	{
		if(!is_string($search) || ($search = trim($search)) === '')
		{
			return null;
		}
		$db = $GLOBALS['egw']->db;
		// Escape the LIKE wildcards themselves, or searching for eg. "50%" would match everything
		$like = $db->quote('%' . strtr($search, array('\\' => '\\\\', '%' => '\\%', '_' => '\\_')) . '%');
		$op = $db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE] ?: 'LIKE';
		return '(history_new_value ' . $op . ' ' . $like . ' OR history_old_value ' . $op . ' ' . $like . ')';
	}

	/**
	 * Should the attachment (VFS) rows be part of this query?
	 *
	 * @param array $col_filter client column-filters
	 * @param string|null $search free-text search, if any
	 * @return bool
	 */
	protected static function wantsFiles(array $col_filter, $search) : bool
	{
		// Nothing a VFS row can be matched against
		if(is_string($search) && trim($search) !== '')
		{
			return false;
		}
		foreach($col_filter as $column => $value)
		{
			if(is_int($column) || $value === '' || $value === null || $value === array())
			{
				continue;
			}
			if($column === 'status')
			{
				// Explicitly asked for (or alongside) attachments
				if(!in_array(self::FILE_STATUS, (array)$value))
				{
					return false;
				}
				continue;
			}
			// Any other active filter names a history column attachments do not have
			if(isset(self::$col_filters[$column]) || $column[0] === '#')
			{
				return false;
			}
		}
		return true;
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
		// 'col_filter' is what eTemplate speaks everywhere, and every caller arrives through
		// Nextmatch::ajax_get_rows().  This method used to read 'colfilter' (no underscore)
		// instead - a key added in 2010 that nothing in EGroupware has ever written, so the
		// filtering it implemented was unreachable for its entire life; it is gone rather than
		// kept as an alias, so there is one spelling to get right.
		$col_filter = (array)($query['col_filter'] ?? []);
		// Only ever apply filters the history table can actually answer.  Anything else would end
		// up as a bogus WHERE column and fatal - the keys are constrained again (and earlier) by
		// Etemplate\Widget\HistoryLog::validate(), this is the last line of defence.
		$filter += self::columnFilters($col_filter, $query['appname']);
		if(($search = self::searchFilter($query['search'] ?? null)))
		{
			$filter[] = $search;
		}
		// Raw SQL an app added server-side, eg. calendar scoping participant changes to the one
		// recurrence being viewed (calendar_uiforms::setup_history()).  This is TRUSTED input and
		// must stay that way: it reaches us only from $content[<widget id>]['filter'], which the
		// app itself wrote, never from the request - Etemplate\Widget\HistoryLog::validate()
		// deliberately keeps 'filter' out of its allow-list so a client cannot supply one, and
		// Nextmatch::ajax_get_rows() merges the client's filters *over* the content without ever
		// introducing this key.  It only joins the history leg's WHERE, so the attachments leg
		// (whose columns it does not name) is unaffected.
		foreach((array)($query['filter'] ?? []) as $fragment)
		{
			if (is_string($fragment) && trim($fragment) !== '')
			{
				$filter[] = $fragment;
			}
		}

		// filter out private (or no longer defined) custom fields.
		// $cfs is read again in the row loop far below, which is only reachable with rows - and
		// today an empty appname returns none - but it must not depend on that: initialise it here
		// rather than only inside the branch that happens to guarantee it.
		$cfs = array();
		if($filter['history_appname'])
		{
			$to_or = array();
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

		// Add in files, if possible.
		//
		// Attachments are UNIONed in from the VFS, so none of the history columns a filter names
		// exist on them and no filter can be applied to this leg.  Instead the leg is included or
		// dropped as a whole, keyed on the status value the rows carry (self::FILE_STATUS):
		// no status filter means "everything", a status filter listing '~file~' keeps attachments,
		// and one that does not list it excludes them - which is what "show me only status
		// changes" has to mean.  A search or date filter cannot be answered for them either, so
		// they are dropped for those too.
		if(self::wantsFiles($col_filter, $query['search'] ?? null) &&
			$GLOBALS['egw_info']['user']['apps']['filemanager'] &&
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
				// handle DateTime objects stored JSON encoded
				$row[$field] = self::decode($row[$field]);

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