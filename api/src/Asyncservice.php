<?php
/**
 * EGroupware API - Timed Asynchron Services
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright Ralf Becker <RalfBecker-AT-outdoor-training.de>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @access public
 */

namespace EGroupware\Api;

/**
 * The class implements a general eGW service to execute callbacks at a given time.
 *
 * see http://www.egroupware.org/wiki/TimedAsyncServices
 */
class Asyncservice
{
	var $php = '';
	var $crontab = '';
	/**
	 * Our instance of the db-class
	 *
	 * @var Db
	 */
	var $db;
	var $db_table = 'egw_async';
	/**
	 * Enable logging to PHP error_log
	 *
	 * @var boolean
	 */
	var $debug = false;
	/**
	 * Line in crontab set by constructor with absolute path
	 */
	var $cronline = '/api/asyncservices.php default';

	/**
	 * Time to keep expired jobs marked as  "keep", until they got finally deleted
	 */
	const DEFAULT_KEEP_TIME = 86400;	// 1day

	/**
	 * constructor of the class
	 */
	function __construct()
	{
		if (is_object($GLOBALS['egw']->db))
		{
			$this->db = $GLOBALS['egw']->db;
		}
		else
		{
			$this->db = $GLOBALS['egw_setup']->db;
		}
		$this->cronline = EGW_SERVER_ROOT . '/api/asyncservices.php '.$GLOBALS['egw_info']['user']['domain'];

		$this->only_fallback = substr(php_uname(), 0, 7) == "Windows";	// atm cron-jobs dont work on win
	}

	/**
	 * calculates the next run of the timer and puts that with the rest of the data in the db for later execution.
	 *
	 * @param int|array $times unix timestamp or array('min','hour','dow','day','month','year') with execution time.
	 * 	Repeated events are possible to shedule by setting the array only partly, eg.
	 * 	array('day' => 1) for first day in each month 0am or array('min' => '* /5', 'hour' => '9-17')
	 * 	for every 5mins in the time from 9am to 5pm.
	 * @param string $id unique id to cancel the request later, if necessary. Should be in a form like
	 * 	eg. '<app><id>X' where id is the internal id of app and X might indicate the action.
	 * @param string $method Method to be called via ExecMethod($method,$data). $method has the form
	 * 	'<app>.<class>.<public function>'.
	 * @param mixed $data =null This data is passed back when the method is called. If it is an array,
	 * 	EGroupware will add the rest of the job parameters like id, next (sheduled exec time), times, ...
	 *  Integer value for key "keep" (in seconds) can be used to NOT delete the job automatically, after it was triggered.
	 *  Async service with set a async_next value of 0 and key "keep_time" with timestamp on how long to keep the entry around.
	 * @param int $account_id account_id, under which the methode should be called or False for the actual user
	 * @param boolean $debug =false
	 * @param boolean $allow_past =false allow to set alarms in the past eg. with times===0 to not trigger it in next run
	 * @return boolean False if $id already exists, else True
	 */
	function set_timer($times, $id, $method, $data=null, $account_id=False, $debug=false, $allow_past=false)
	{
		if (empty($id) || empty($method) || $this->read($id) ||
				!($next = $this->next_run($times,$debug)))
		{
			// allow to set "keep" alarms in the past ($next === false)
			if ($next === false && !is_array($times) && $allow_past)
			{
				$next = $times;
			}
			else
			{
				if ($this->debug) error_log(__METHOD__."(".array2string($times).", '$id', '$method', ".array2string($data).", $account_id, $debug, $allow_past) returning FALSE");
				return False;
			}
		}
		if ($account_id === False)
		{
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
		}
		$job = array(
			'id'     => $id,
			'next'   => $next,
			'times'  => $times,
			'method' => $method,
			'data'   => $data,
			'account_id' => $account_id
		);
		$this->write($job);

		if ($this->debug) error_log(__METHOD__."(".array2string($times).", '$id', '$method', ".array2string($data).", $account_id, $debug, $allow_past) returning TRUE");
		return True;
	}

	/**
	 * calculates the next execution time for $times
	 *
	 * @param int/array $times unix timestamp or array('year'=>$year,'month'=>$month,'dow'=>$dow,'day'=>$day,'hour'=>$hour,'min'=>$min)
	 * 	with execution time. Repeated execution is possible to shedule by setting the array only partly,
	 * 	eg. array('day' => 1) for first day in each month 0am or array('min' => '/5', 'hour' => '9-17')
	 * 	for every 5mins in the time from 9am to 5pm. All not set units before the smallest one set,
	 * 	are taken into account as every possible value, all after as the smallest possible value.
	 * @param boolean $debug if True some debug-messages about syntax-errors in $times are echoed
	 * @param int $now Use this time to calculate then next run from.  Defaults to time().
	 * @return int a unix timestamp of the next execution time or False if no more executions
	 */
	function next_run($times,$debug=False, $now = null)
	{
		if ($this->debug)
		{
			error_log(__METHOD__."(".array2string($times).", '$debug', " . date('Y-m-d H:i', $now) . ")");
			$debug = True;	// enable syntax-error messages too
		}
		if(is_null($now)) {
			$now = time();
		}

		// $times is unix timestamp => if it's not expired return it, else False
		//
		if (!is_array($times))
		{
			$next = (int)$times;

			return $next > $now ? $next : False;
		}
		// If an array is given, we have to enumerate the possible times first
		//
		$units = array(
			'year'  => 'Y',
			'month' => 'm',
			'day'   => 'd',
			'dow'   => 'w',
			'hour'  => 'H',
			'min'   => 'i'
		);
		$max_unit = array(
			'min'   => 59,
			'hour'  => 23,
			'dow'   => 6,
			'day'   => 31,
			'month' => 12,
			'year'  => date('Y')+10	// else */[0-9] would never stop returning numbers
		);
		$min_unit = array(
			'min'   => 0,
			'hour'  => 0,
			'dow'   => 0,
			'day'   => 1,
			'month' => 1,
			'year'  => date('Y')
		);

		// get the number of the first and last pattern set in $times,
		// as empty patterns get enumerated before the the last pattern and
		// get set to the minimum after
		//
		$i = $first_set = $last_set = 0;
		foreach($units as $u => $date_pattern)
		{
			++$i;
			if (isset($times[$u]))
			{
				$last_set = $i;

				if (!$first_set)
				{
					$first_set = $i;
				}
			}
		}

		// now we go through all units and enumerate all patterns and not set patterns
		// (as descript above), enumerations are arrays with unit-values as keys
		//
		$n = 0;
		foreach($units as $u => $date_pattern)
		{
			++$n;
			if ($this->debug) error_log("n=$n, $u: isset(times[$u]=".array2string($times[$u]).")=".(isset($times[$u])?'True':'False'));
			if (isset($times[$u]))
			{
				if(is_array($times[$u])) {
					$time = array_keys($times[$u]);
				} else {
					$time = explode(',',$times[$u]);
				}
				$times[$u] = array();

				foreach($time as $t)
				{
					if (strpos($t,'-') !== False && strpos($t,'/') === False)
					{
						list($min,$max) = $arr = explode('-',$t);

						if (count($arr) != 2 || !is_numeric($min) || !is_numeric($max) || $min > $max)
						{
							if ($debug) error_log("Syntax error in $u='$t', allowed is 'min-max', min <= max, min='$min', max='$max'");

							return False;
						}
						for ($i = (int)$min; $i <= $max; ++$i)
						{
							$times[$u][$i] = True;
						}
					}
					else
					{
						if ((string)$t == '*') 	$t = '*/1';

						list($one,$inc) = ($arr = explode('/', $t))+[null,null];

						if (!(is_numeric($one) && count($arr) == 1 ||
									count($arr) == 2 && is_numeric($inc)))
						{
							if ($debug) error_log("Syntax error in $u='$t', allowed is a number or '{*|range}/inc', inc='$inc'");

							return False;
						}
						if (count($arr) == 1)
						{
							$times[$u][(int)$one] = True;
						}
						else
						{
							list($min,$max) = ($arr = explode('-', $one))+[null,null];
							if (empty($one) || $one == '*')
							{
								$min = $min_unit[$u];
								$max = $max_unit[$u];
							}
							elseif (count($arr) != 2 || $min > $max)
							{
								if ($debug) error_log("Syntax error in $u='$t', allowed is '{*|min-max}/inc', min='$min',max='$max', inc='$inc'");
								return False;
							}
							for ($i = $min; $i <= $max; $i += $inc)
							{
								$times[$u][$i] = True;
							}
						}
					}
				}
			}
			elseif ($n < $last_set || $u == 'dow')	// before last value set (or dow) => empty gets enumerated
			{
				for ($i = $min_unit[$u]; $i <= $max_unit[$u]; ++$i)
				{
					$times[$u][$i] = True;
				}
			}
			else	// => after last value set => empty is min-value
			{
				$times[$u][$min_unit[$u]] = True;
			}
		}
		if ($this->debug) error_log("enumerated times=".array2string($times));

		// now we have the times enumerated, lets find the first not expired one
		//
		$found = array();
		$over = $next = null;
		while (!isset($found['min']))
		{
			$future = False;

			foreach($units as $u => $date_pattern)
			{
				$unit_now = $u != 'dow' ? (int)date($date_pattern, $now) :
					(int)date($date_pattern,mktime(12,0,0,$found['month'],$found['day'],$found['year']));

				if (isset($found[$u]))
				{
					$future = $future || $found[$u] > $unit_now;
					if ($this->debug) error_log("--> already have a $u = ".$found[$u].", future='$future'");
					continue;	// already set
				}
				foreach(array_keys($times[$u]) as $unit_value)
				{
					switch($u)
					{
						case 'dow':
							$valid = $unit_value == $unit_now;
							break;
						case 'min':
							$valid = $future || $unit_value > $unit_now;
							break;
						default:
							$valid = $future || $unit_value >= $unit_now;
							break;

					}
					if ($valid && ($u != $next || $unit_value > $over))	 // valid and not over
					{
						$found[$u] = $unit_value;
						$future = $future || $unit_value > $unit_now;
						break;
					}
				}
				if (!isset($found[$u]))		// we have to try the next one, if it exists
				{
					$nexts = array_keys($units);
					if (!isset($nexts[count($found)-1]))
					{
						if ($this->debug) error_log("Nothing found, exiting !!!");
						return False;
					}
					$next = $nexts[count($found)-1];
					$over = $found[$next];
					unset($found[$next]);
					if ($this->debug) error_log("Have to try the next $next, $u's are over for $next=$over !!!");
					break;
				}
			}
		}
		if ($this->debug) error_log("next=".array2string($found));

		return mktime($found['hour'],$found['min'],0,$found['month'],$found['day'],$found['year']);
	}

	/**
	 * cancels a timer
	 *
	 * @param string $id has to be the one used to set it.
	 * @return boolean True if the timer exists and is not expired.
	 */
	function cancel_timer($id)
	{
		return $this->delete($id);
	}

	/**
	 * checks when the last check_run was run or set the run-semaphore (async_next != 0) if $semaphore == True
	 *
	 * @param boolean $semaphore if False only check, if true try to set/release the semaphore
	 * @param boolean $release if $semaphore == True, tells if we should set or release the semaphore
	 * @return mixed if !$set array('start' => $start,'end' => $end) with timestamps of last check_run start and end,  \
	 * 	!$end means check_run is just running. If $set returns True if it was able to get the semaphore, else False
	 */
	function last_check_run($semaphore=False,$release=False,$run_by='')
	{
		//echo "<p>last_check_run(semaphore=".($semaphore?'True':'False').",release=".($release?'True':'False').")</p>\n";
		if (($exists = $this->read('##last-check-run##')))
		{
			$last_run = array_pop($exists);
		}
		//echo "last_run (from db)=<pre>"; print_r($last_run); echo "</pre>\n";

		if (!$semaphore)
		{
			return $last_run['data'];
		}

		$where = array();
		if ($release)
		{
			$last_run['next'] = 0;
			$last_run['data']['end'] = time();
		}
		else
		{
			@set_time_limit(0);		// dont stop for an execution-time-limit
			ignore_user_abort(True);

			$last_run = array(
				'id'     => '##last-check-run##',
				'next'   => time(),
				'times'  => array(),
				'method' => 'none',
				'data'   => array(
					'run_by'=> $run_by,
					'start' => time(),
					'end'   => 0
				)
			);
			// as the async_next column is used as a semaphore we only update it,
			// if it is 0 (semaphore released) or older then 10min to recover from failed or crashed attempts
			if ($exists) $where = array('(async_next=0 OR async_next<'.(time()-600).')');
		}
		//echo "last_run=<pre>"; print_r($last_run); echo "</pre>\n";
		return $this->write($last_run, !!$exists, $where) > 0;
	}

	/**
	 * checks if there are any jobs ready to run (timer expired) and executes them
	 */
	function check_run($run_by='')
	{
		if ($run_by === 'fallback') flush();

		if (!$this->last_check_run(True,False,$run_by))
		{
			return False;	// cant obtain semaphore
		}
		// mark enviroment as async-service, check with isset()!
		$GLOBALS['egw_info']['flags']['async-service'] = $run_by;

		if (($jobs = $this->read()))
		{
			foreach($jobs as $job)
			{
				// checking / setting up egw_info/user
				//
				//if ($GLOBALS['egw_info']['user']['account_id'] != $job['account_id'])
				{
					// run notifications, before changing account_id of enviroment
					Link::run_notifies();
					// unset all objects in $GLOBALS, which are created and used by ExecMethod, as they can contain user-data
					foreach($GLOBALS as $name => $value)
					{
						if ($name !== 'egw' && is_object($value)) unset($GLOBALS[$name]);
					}
					$domain = $GLOBALS['egw_info']['user']['domain'];
					$lang   = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
					unset($GLOBALS['egw_info']['user']);

					if (($GLOBALS['egw']->session->account_id = $job['account_id']))
					{
						$GLOBALS['egw']->session->account_lid = $GLOBALS['egw']->accounts->id2name($job['account_id']);
						$GLOBALS['egw']->session->account_domain = $domain;
						$GLOBALS['egw_info']['user']  = $GLOBALS['egw']->session->read_repositories();

						if ($lang != $GLOBALS['egw_info']['user']['preferences']['common']['lang'])
						{
							Translation::init();
						}
						// set VFS user for vfs access rights
						Vfs::$user = $job['account_id'];
						Vfs\StreamWrapper::init_static();
						Vfs::clearstatcache();
					}
					else
					{
						$GLOBALS['egw_info']['user']['domain'] = $domain;
					}
				}
				list($app) = strpos($job['method'],'::') !== false ? explode('_',$job['method']) :
					explode('.',$job['method']);
				Translation::add_app($app);
				if (is_array($job['data']))
				{
					$job['data'] += array_diff_key($job,array('data' => false));
				}

				if ($this->debug) error_log(__METHOD__."() processing job ".array2string($job));

				// purge keeped jobs, if they are due
				if (is_array($job['data']) && !empty($job['data']['keep_time']))
				{
					if ($job['data']['keep_time'] <= time())
					{
						if ($this->debug) error_log(__METHOD__."() finally deleting job ".array2string($job));
						$this->delete($job['id']);
					}
					// fix somehow created async-job with a next time before the keep time, eg. not updated alarm trigger time in the past
					if ($job['next'] < $job['data']['keep_time'])
					{
						$job['next'] = $job['data']['keep_time'];
						if ($this->debug) error_log(__METHOD__."() setting next to keep_time (".date('Y-m-d H:i:s', $job['data']['keep_time']).') for job '.array2string($job));
						$this->write($job, True);
					}
					if ($this->debug) error_log(__METHOD__."() keeping job ".array2string($job));
					continue;
				}

				// update job before running it, to cope with jobs taking longer then async-frequency
				if (($job['next'] = $this->next_run($job['times'])) ||
					// keep jobs with keep set (eg. calendar alarms) around
					is_array($job['data']) && !empty($job['data']['keep']))
				{
					if (!$job['next'])
					{
						$job['next'] = $job['data']['keep_time'] = time() + ((int)$job['data']['keep'] > 1 ? $job['data']['keep'] : self::DEFAULT_KEEP_TIME);
						if ($this->debug) error_log(__METHOD__."() setting keep_time to ".date('Y-m-d H:i:s', $job['data']['keep_time']).' for job '.array2string($job));
					}
					$this->write($job, True);
				}
				else	// no further runs
				{
					$this->delete($job['id']);
				}
				try
				{
					if ($this->debug) error_log(__METHOD__."() running job ".array2string($job));
					ExecMethod($job['method'],$job['data']);
				}
				catch(Exception $e)
				{
					// log the exception to error_log, but continue running other async jobs
					_egw_log_exception($e);
				}
			}
		}
		$this->last_check_run(True,True,$run_by);	// release semaphore

		return $jobs ? count($jobs) : 0;
	}

	/**
	 * reads all matching db-rows / jobs
	 *
	 * @param string $id =0 reads all expired rows / jobs ready to run\
	 * 	!= 0 reads all rows/jobs matching $id (sql-wildcards '%' and '_' can be used)
	 * @param array|string $cols ='*' string or array of column-names / select-expressions
	 * @param int|bool $offset =False offset for a limited query or False (default)
	 * @param string $append ='ORDER BY async_next' string to append to the end of the query
	 * @param int $num_rows =0 number of rows to return if offset set, default 0 = use default in user prefs
	 * @return array/boolean db-rows / jobs as array or False if no matches
	 */
	function read($id=0,$cols='*',$offset=False,$append='ORDER BY async_next',$num_rows=0)
	{
		if (!is_a($this->db, 'EGroupware\\Api\\Db')) return false;

		if ($id === '%')
		{
			$where = "async_id != '##last-check-run##'";
		}
		elseif (!is_array($id) && (strpos($id,'%') !== False || strpos($id,'_') !== False))
		{
			$id = $this->db->quote($id);
			$where = "async_id LIKE $id AND async_id != '##last-check-run##'";
		}
		elseif (!$id)
		{
			$where = 'async_next <= '.time()." AND async_id != '##last-check-run##'";
		}
		else
		{
			$where = array('async_id' => $id);
		}
		$jobs = array();
		foreach($this->db->select($this->db_table,$cols,$where,__LINE__,__FILE__,$offset,$append,False,$num_rows) as $row)
		{
			$row['async_times'] = json_php_unserialize($row['async_times']);
			// check for broken value during migration
			if (($row['async_data'][0]??null) === '"' && substr($row['async_data'], 0, 7) == '"\\"\\\\\\"')
			{
				$row['async_data'] = null;
				$this->write(Db::strip_array_keys($row,'async_'), true);
			}
			else
			{
				$row['async_data'] = !empty($row['async_data']) ?
					json_php_unserialize($row['async_data'], true) : null;	// allow non-serialized data
			}
			$jobs[$row['async_id']] = Db::strip_array_keys($row,'async_');
		}
		if (!count($jobs))
		{
			return False;
		}
		return $jobs;
	}

	/**
	 * write a job / db-row to the db
	 *
	 * @param array $job db-row as array
	 * @param boolean $exists if True, we do an update, else we check if update or insert necesary
	 * @param array $where additional where statemetn to update only if a certain condition is met, used for the semaphore
	 * @return int affected rows, can be 0 if an additional where statement is given
	 */
	function write($job, $exists = False, $where=array())
	{
		if (!is_a($this->db, 'EGroupware\\Api\\Db')) return 0;

		if (is_array($job['data']) && isset($job['data']['next']) && isset($job['next'])) $job['data']['next'] = $job['next'];
		$data = array(
			'async_next'      => $job['next'],
			'async_times'     => json_encode($job['times']),
			'async_method'    => $job['method'],
			'async_data'      => $job['data'] ? json_encode($job['data']) : '',
			'async_account_id'=> $job['account_id'],
		);
		$where['async_id'] = $job['id'];
		if ($exists)
		{
			$this->db->update($this->db_table, $data, $where, __LINE__, __FILE__);
		}
		else
		{
			$this->db->insert($this->db_table, $data, $where, __LINE__, __FILE__);
		}
		return $this->db->affected_rows();
	}

	/**
	 * delete db-row / job with $id
	 *
	 * @return boolean False if $id not found else True
	 */
	function delete($id)
	{
		if ($this->debug) error_log(__METHOD__."('$id') ".function_backtrace());
		$this->db->delete($this->db_table,array('async_id' => $id),__LINE__,__FILE__);

		return $this->db->affected_rows();
	}

	function find_binarys()
	{
		static $run = False;
		if ($run)
		{
			return;
		}
		else
		{
			$run = True;
		}

		if (substr(php_uname(), 0, 7) == "Windows")
		{
			// ToDo: find php-cgi on windows
		}
		else
		{
			$binarys = array(
				'php'  => '/usr/bin/php',
				'php5' => '/usr/bin/php5',		// SuSE 9.3 with php5
				'crontab' => '/usr/bin/crontab'
			);
			foreach ($binarys as $name => $path)
			{
				$this->$name = $path;	// a reasonable default for *nix

				if (!($Ok = @is_executable($this->$name)))
				{
					if (file_exists($this->$name))
					{
						echo '<p>'.lang('%1 is not executable by the webserver !!!',$this->$name)."</p>\n";
						$perms = fileperms($this->$name);
						if (!($perms & 0x0001) && ($perms & 0x0008) && function_exists('posix_getuid'))	// only executable by group
						{
							$group = posix_getgrgid(filegroup($this->$name));
							$webserver = posix_getpwuid(posix_getuid ());
							echo '<p>'.lang("You need to add the webserver user '%1' to the group '%2'.",$webserver['name'],$group['name'])."</p>\n";							}
					}
					if (function_exists('popen') && ($fd = popen('/bin/sh -c "type -p '.$name.'"','r')))
					{
						$this->$name = fgets($fd,256);
						@pclose($fd);
					}
					if (($pos = strpos($this->$name,"\n")))
					{
						$this->$name = substr($this->$name,0,$pos);
					}
				}
				if (!$Ok && !@is_executable($this->$name))
				{
					$this->$name = $name;	// hopefully its in the path
				}
				//echo "<p>$name = '".$this->$name."'</p>\n";
			}
			if ($this->php5[0] == '/')	// we found a php5 binary
			{
				$this->php = $this->php5;
			}
		}
	}

	/**
	 * checks if phpgwapi/cron/asyncservices.php is installed as cron-job
	 *
	 * @param array& $other_cronlines =array() on return other cronlines found
	 * @return array the times asyncservices are run (normaly 'min'=>'* /5') or False if not installed or 0 if crontab not found
	 * Not implemented for Windows at the moment, always returns 0
	 */
	/**
	 *
	 * @return int
	 */
	function installed(array &$other_cronlines=array())
	{
		if ($this->only_fallback) {
			return 0;
		}
		$this->find_binarys();

		if (!is_executable($this->crontab))
		{
			//echo "<p>Error: $this->crontab not found !!!</p>";
			return 0;
		}
		$times = False;
		$other_cronlines = array();
		if (function_exists('popen') && ($crontab = popen('/bin/sh -c "'.$this->crontab.' -l" 2>&1','r')) !== False)
		{
			$n = 0;
			while ($line = fgets($crontab,256))
			{
				if ($this->debug) error_log(__METHOD__.'() line '.++$n.": $line");
				$parts = explode(' ',$line,6);

				if ($line[0] == '#' || count($parts) < 6 || ($parts[5][0] != '/' && substr($parts[5],0,3) != 'php'))
				{
					// ignore comments
					if ($line[0] != '#')
					{
						$times['error'] .= $line;
					}
				}
				elseif (strpos($line,$this->cronline) !== False ||
					// also check of old phpgwapi/cron path
					strpos($line, str_replace('/api/', '/phpgwapi/cron/', $this->cronline)) !== False)
				{
					$cron_units = array('min','hour','day','month','dow');
					foreach($cron_units as $n => $u)
					{
						$times[$u] = $parts[$n];
					}
					$times['cronline'] = $line;
				}
				else
				{
					$other_cronlines[] = $line;
				}
			}
			@pclose($crontab);
		}
		return $times;
	}

	/**
	 * installs /phpgwapi/cron/asyncservices.php as cron-job
	 *
	 * Not implemented for Windows at the moment, always returns 0
	 *
	 * @param array $times array with keys 'min','hour','day','month','dow', not set is equal to '*'.
	 * 	False means de-install our own crontab line
	 * @return mixed the times asyncservices are run, False if they are not installed,
	 * 	0 if crontab not found and ' ' if crontab is deinstalled
	 */
	function install($times)
	{
		if ($this->only_fallback && $times !== False) {
			return 0;
		}
		$other_cronlines = array();
		$this->installed($other_cronlines);	// find other installed cronlines

		if (function_exists('popen') && ($crontab = popen('/bin/sh -c "'.$this->crontab.' -" 2>&1','w')) !== False)
		{
			foreach ($other_cronlines as $cronline)
			{
				fwrite($crontab,$cronline);		// preserv the other lines on install
			}
			if ($times !== False)
			{
				$cron_units = array('min','hour','day','month','dow');
				$cronline = '';
				foreach($cron_units as $cu)
				{
					$cronline .= (isset($times[$cu]) ? $times[$cu] : '*') . ' ';
				}
				// -d memory_limit=-1 --> no memory limit
				$cronline .= $this->php.' -q -d memory_limit=-1 '.$this->cronline."\n";
				//echo "<p>Installing: '$cronline'</p>\n";
				fwrite($crontab,$cronline);
			}
			@pclose($crontab);
		}
		return $times !== False ? $this->installed() : ' ';
	}
}