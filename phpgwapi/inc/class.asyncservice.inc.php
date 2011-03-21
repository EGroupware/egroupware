<?php
/**
 * API - Timed Asynchron Services for eGroupWare
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright Ralf Becker <RalfBecker-AT-outdoor-training.de>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @access public
 * @version $Id$
 */

/**
 * The class implements a general eGW service to execute callbacks at a given time.
 *
 * see http://www.egroupware.org/wiki/TimedAsyncServices
 */
class asyncservice
{
	var $php = '';
	var $crontab = '';
	/**
	 * Our instance of the db-class
	 *
	 * @var egw_db
	 */
	var $db;
	var $db_table = 'egw_async';
	var $debug = 0;
	/**
	 * Line in crontab set by constructor with absolute path
	 *
	 * @var string
	 */
	var $cronline = '/phpgwapi/cron/asyncservices.php default';

	/**
	 * constructor of the class
	 */
	function asyncservice()
	{
		if (is_object($GLOBALS['egw']->db))
		{
			$this->db = $GLOBALS['egw']->db;
		}
		else
		{
			$this->db = $GLOBALS['egw_setup']->db;
		}
		$this->cronline = EGW_SERVER_ROOT . '/phpgwapi/cron/asyncservices.php '.$GLOBALS['egw_info']['user']['domain'];

		$this->only_fallback = substr(php_uname(), 0, 7) == "Windows";	// atm cron-jobs dont work on win
	}

	/**
	 * calculates the next run of the timer and puts that with the rest of the data in the db for later execution.
	 *
	 * @param int/array $times unix timestamp or array('min','hour','dow','day','month','year') with execution time.
	 * 	Repeated events are possible to shedule by setting the array only partly, eg.
	 * 	array('day' => 1) for first day in each month 0am or array('min' => '* /5', 'hour' => '9-17')
	 * 	for every 5mins in the time from 9am to 5pm.
	 * @param string $id unique id to cancel the request later, if necessary. Should be in a form like
	 * 	eg. '<app><id>X' where id is the internal id of app and X might indicate the action.
	 * @param string $method Method to be called via ExecMethod($method,$data). $method has the form
	 * 	'<app>.<class>.<public function>'.
	 * @param mixed $data This data is passed back when the method is called. It might simply be an
	 * 	integer id, but it can also be a complete array.
	 * @param int $account_id account_id, under which the methode should be called or False for the actual user
	 * @return boolean False if $id already exists, else True
	 */
	function set_timer($times,$id,$method,$data,$account_id=False)
	{
		if (empty($id) || empty($method) || $this->read($id) ||
				!($next = $this->next_run($times)))
		{
			return False;
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
			echo "<p>next_run("; print_r($times); echo ",'$debug', " . date('Y-m-d H:i', $now) . ")</p>\n";
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
		$n = $first_set = $last_set = 0;
		foreach($units as $u => $date_pattern)
		{
			++$n;
			if (isset($times[$u]))
			{
				$last_set = $n;

				if (!$first_set)
				{
					$first_set = $n;
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
			if ($this->debug) { echo "<p>n=$n, $u: isset(times[$u]="; print_r($times[$u]); echo ")=".(isset($times[$u])?'True':'False')."</p>\n"; }
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
							if ($debug) echo "<p>Syntax error in $u='$t', allowed is 'min-max', min <= max, min='$min', max='$max'</p>\n";

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

						list($one,$inc) = $arr = explode('/',$t);

						if (!(is_numeric($one) && count($arr) == 1 ||
									count($arr) == 2 && is_numeric($inc)))
						{
							if ($debug) echo "<p>Syntax error in $u='$t', allowed is a number or '{*|range}/inc', inc='$inc'</p>\n";

							return False;
						}
						if (count($arr) == 1)
						{
							$times[$u][(int)$one] = True;
						}
						else
						{
							list($min,$max) = $arr = explode('-',$one);
							if (empty($one) || $one == '*')
							{
								$min = $min_unit[$u];
								$max = $max_unit[$u];
							}
							elseif (count($arr) != 2 || $min > $max)
							{
								if ($debug) echo "<p>Syntax error in $u='$t', allowed is '{*|min-max}/inc', min='$min',max='$max', inc='$inc'</p>\n";
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
		if ($this->debug) { echo "enumerated times=<pre>"; print_r($times); echo "</pre>\n"; }

		// now we have the times enumerated, lets find the first not expired one
		//
		$found = array();
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
					if ($this->debug) echo "--> already have a $u = ".$found[$u].", future='$future'<br>\n";
					continue;	// already set
				}
				foreach($times[$u] as $unit_value => $nul)
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
					$next = array_keys($units);
					if (!isset($next[count($found)-1]))
					{
						if ($this->debug) echo "<p>Nothing found, exiting !!!</p>\n";
						return False;
					}
					$next = $next[count($found)-1];
					$over = $found[$next];
					unset($found[$next]);
					if ($this->debug) echo "<p>Have to try the next $next, $u's are over for $next=$over !!!</p>\n";
					break;
				}
			}
		}
		if ($this->debug) { echo "<p>next="; print_r($found); echo "</p>\n"; }

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
		if ($exists = $this->read('##last-check-run##'))
		{
			list(,$last_run) = each($exists);
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
			if ($exists) $where = array('async_next=0 OR async_next<'.time()-600);
		}
		//echo "last_run=<pre>"; print_r($last_run); echo "</pre>\n";
		return $this->write($last_run,!!$exits,$where) > 0;
	}

	/**
	 * checks if there are any jobs ready to run (timer expired) and executes them
	 */
	function check_run($run_by='')
	{
		flush();

		if (!$this->last_check_run(True,False,$run_by))
		{
			return False;	// cant obtain semaphore
		}
		if ($jobs = $this->read())
		{
			foreach($jobs as $id => $job)
			{
				// checking / setting up egw_info/user
				//
				if ($GLOBALS['egw_info']['user']['account_id'] != $job['account_id'])
				{
					$domain = $GLOBALS['egw_info']['user']['domain'];
					$lang   = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
					unset($GLOBALS['egw_info']['user']);

					if ($GLOBALS['egw']->session->account_id = $job['account_id'])
					{
						$GLOBALS['egw']->session->account_lid = $GLOBALS['egw']->accounts->id2name($job['account_id']);
						$GLOBALS['egw']->session->account_domain = $domain;
						$GLOBALS['egw']->session->read_repositories();
						$GLOBALS['egw_info']['user']  = $GLOBALS['egw']->session->user;

						if ($lang != $GLOBALS['egw_info']['user']['preferences']['common']['lang'])
						{
							translation::init();
						}
					}
					else
					{
						$GLOBALS['egw_info']['user']['domain'] = $domain;
					}
				}
				list($app) = explode('.',$job['method']);
				$GLOBALS['egw']->translation->add_app($app);
				try
				{
					ExecMethod($job['method'],$job['data']);
				}
				catch(Exception $e)
				{
					// log the exception to error_log, but continue running other async jobs
					_egw_log_exception($e);
				}
				// re-read job, in case it had been updated or even deleted in the method
				$updated = $this->read($id);
				if ($updated && isset($updated[$id]))
				{
					$job = $updated[$id];

					if ($job['next'] = $this->next_run($job['times']))
					{
						$this->write($job,True);
					}
					else	// no further runs
					{
						$this->delete($job['id']);
					}
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
	 * @return array/boolean db-rows / jobs as array or False if no matches
	 */
	function read($id=0)
	{
		if (!is_array($id) && (strpos($id,'%') !== False || strpos($id,'_') !== False))
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
		foreach($this->db->select($this->db_table,'*',$where,__LINE__,__FILE__) as $row)
		{
			$row['async_times'] = unserialize($row['async_times']);
			$row['async_data'] = unserialize($row['async_data']);
			$jobs[$row['async_id']] = egw_db::strip_array_keys($row,'async_');
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
	 * @param boolean $exits if True, we do an update, else we check if update or insert necesary
	 * @param array $where additional where statemetn to update only if a certain condition is met, used for the semaphore
	 * @return int affected rows, cat be 0 if an additional where statement is given
	 */
	function write($job,$exists = False,$where=array())
	{
		$data = array(
			'async_next'      => $job['next'],
			'async_times'     => serialize($job['times']),
			'async_method'    => $job['method'],
			'async_data'      => serialize($job['data']),
			'async_account_id'=> $job['account_id'],
		);
		if ($exists)
		{
			$this->db->update($this->db_table,$data,array('async_id' => $job['id']),__LINE__,__FILE__);
		}
		else
		{
			$this->db->insert($this->db_table,$data,array('async_id' => $job['id']),__LINE__,__FILE__);
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
		$run = True;

		if (substr(php_uname(), 0, 7) == "Windows")
		{
			// ToDo: find php-cgi on windows
		}
		else
		{
			$binarys = array(
				'php'  => '/usr/bin/php',
				'php4' => '/usr/bin/php4',		// this is for debian
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
						if (!($perms & 0x0001) && ($perms & 0x0008))	// only executable by group
						{
							$group = posix_getgrgid(filegroup($this->$name));
							$webserver = posix_getpwuid(posix_getuid ());
							echo '<p>'.lang("You need to add the webserver user '%1' to the group '%2'.",$webserver['name'],$group['name'])."</p>\n";							}
					}
					if ($fd = popen('/bin/sh -c "type -p '.$name.'"','r'))
					{
						$this->$name = fgets($fd,256);
						@pclose($fd);
					}
					if ($pos = strpos($this->$name,"\n"))
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
			if ($this->php4{0} == '/')	// we found a php4 binary
			{
				$this->php = $this->php4;
			}
			if ($this->php5{0} == '/')	// we found a php5 binary
			{
				$this->php = $this->php5;
			}
		}
	}

	/**
	 * checks if phpgwapi/cron/asyncservices.php is installed as cron-job
	 *
	 * @return array the times asyncservices are run (normaly 'min'=>'* /5') or False if not installed or 0 if crontab not found
	 * Not implemented for Windows at the moment, always returns 0
	 */
	function installed()
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
		$this->other_cronlines = array();
		if (($crontab = popen('/bin/sh -c "'.$this->crontab.' -l" 2>&1','r')) !== False)
		{
			while ($line = fgets($crontab,256))
			{
				if ($this->debug) echo 'line '.++$n.": $line<br>\n";
				$parts = explode(' ',$line,6);

				if ($line{0} == '#' || count($parts) < 6 || ($parts[5]{0} != '/' && substr($parts[5],0,3) != 'php'))
				{
					// ignore comments
					if ($line{0} != '#')
					{
						$times['error'] .= $line;
					}
				}
				elseif (strpos($line,$this->cronline) !== False)
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
					$this->other_cronlines[] = $line;
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
		$this->installed();	// find other installed cronlines

		if (($crontab = popen('/bin/sh -c "'.$this->crontab.' -" 2>&1','w')) !== False)
		{
			if (is_array($this->other_cronlines))
			{
				foreach ($this->other_cronlines as $cronline)
				{
					fwrite($crontab,$cronline);		// preserv the other lines on install
				}
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
