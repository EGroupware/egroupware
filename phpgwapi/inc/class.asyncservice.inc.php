<?php
	/**************************************************************************\
	* phpGroupWare API - Timed Asynchron Services for phpGroupWare             *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* Class for creating cron-job like timed calls of phpGroupWare methods     *
	* -------------------------------------------------------------------------*
	* This library is part of the phpGroupWare API                             *
	* http://www.phpgroupware.org/                                             *
	* ------------------------------------------------------------------------ *
	* This library is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published by *
	* the Free Software Foundation; either version 2.1 of the License,         *
	* or any later version.                                                    *
	* This library is distributed in the hope that it will be useful, but      *
	* WITHOUT ANY WARRANTY; without even the implied warranty of               *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
	* See the GNU Lesser General Public License for more details.              *
	* You should have received a copy of the GNU Lesser General Public License *
	* along with this library; if not, write to the Free Software Foundation,  *
	* Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
	\**************************************************************************/

	/* $Id$ */

	class asyncservice
	{
		var $public_functions = array(
			'set_timer' => True,
			'check_run' => True,
			'cancel_timer' => True,
			'read'      => True,
			'install'   => True,
			'installed' => True,
			'test'      => True
		);
		var $php = '';
		var $crontab = '';
		var $db;
		var $db_table = 'phpgw_async';
		
		function asyncservice()
		{
			$this->db = $GLOBALS['phpgw']->db;
		}

		/*!
		@function set_timer
		@abstract calculates the next run of the timer and puts that with the rest of the data in the db
		@syntax set_timer($times,$id,$method,$data)
		@param $times unix timestamp or array('min','hour','dow','day','month','year') with execution time. \
			Repeated events are possible to shedule by setting the array only partly, eg. \
			array('day' => 1) for first day in each month 0am or array('min' => '* /5', 'hour' => '9-17') \
			for every 5mins in the time from 9am to 5pm.
		@param $id unique id to cancel the request later, if necessary. Should be in a form like \
			eg. '<app><id>X' where id is the internal id of app and X might indicate the action.
		@param $method Method to be called via ExecMethod($method,$data). $method has the form \
			'<app>.<class>.<public function>'.
		@param $data This data is passed back when the method is called. It might simply be an \
			integer id, but it can also be a complete array.
		@Returns False if $id already exists, else True	
		*/
		function set_timer($times,$id,$method,$data)
		{
			if (empty($id) || empty($method) || $this->read($id) || 
			    !($next = $this->next_run($times)))
			{
				return False;
			}
			$job = array(
				'id'     => $id,
				'next'   => $next,
				'times'  => $times,
				'method' => $method,
				'data'   => $data
			);
			$this->write($job);
			
			return True;
		}

		/*!
		@function next_run
		@abstract calculates the next execution time for $time
		@syntax next_run($time)
		@param $times unix timestamp or array('year'=>$year,'month'=>$month,'dow'=>$dow,'day'=>$day,'hour'=>$hour,'min'=>$min) \
			with execution time. Repeated execution is possible to shedule by setting the array only partly, \
			eg. array('day' => 1) for first day in each month 0am or array('min' => '/5', 'hour' => '9-17') \
			for every 5mins in the time from 9am to 5pm. All not set units before the smallest one set, \
			are taken into account as every possible value, all after as the smallest possible value. 
		@returns a unix timestamp of the next execution time or False if no more executions
		*/
		function next_run($times,$debug=False)
		{
			$now = time();
			
			// $times is unix timestamp => if it's not expired return it, else False
			//
			if (!is_array($times))	
			{
				$next = intval($times);

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
				//echo "<p>$u: isset(times[$u]="; print_r($times[$u]); echo ")=".(isset($times[$u])?'True':'False')."</p>\n";
				if (isset($times[$u]))
				{
					$time = explode(',',$times[$u]);

					$times[$u] = array();

					foreach($time as $t)
					{
						if (strstr($t,'-') !== False && strstr($t,'/') === False)
						{
							list($min,$max) = $arr = explode('-',$t);
							
							if (count($arr) != 2 || !is_numeric($min) || !is_numeric($max) || $min > $max)
							{
								if ($debug) echo "<p>Syntax error in $u='$t', allowed is 'min-max', min <= max, min='$min', max='$max'</p>\n";

								return False;
							}
							for ($i = intval($min); $i <= $max; ++$i)
							{
								$times[$u][$i] = True;
							}
						}
						else
						{
							list($one,$inc) = $arr = explode('/',$t);
							
							if (!(is_numeric($one) && count($arr) == 1 || 
							      count($arr) == 2 && is_numeric($inc)))
							{
								if ($debug) echo "<p>Syntax error in $u='$t', allowed is a number or '{*|range}/inc', inc='$inc'</p>\n";

								return False;
							}
							if (count($arr) == 1)
							{
								$times[$u][intval($one)] = True;
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
				elseif ($n < $last_set)		// => empty gets enumerated
				{
					for ($i = $min_unit[$u]; $i <= $max_unit[$u]; ++$i)
					{
						$times[$u][$i] = True;
					}
				}
				else						// => empty is min-value
				{
					$times[$u][$min_unit[$u]] = True;
				}
			}
			//echo "enumerated times=<pre>"; print_r($times); echo "</pre>\n";
			
			// now we have the times enumerated, lets find the first not expired one
			//
			$found = array();
			while (!isset($found['min']))
			{
				$found = array();
				$future = False;

				$n = 0;
				foreach($units as $u => $date_pattern)
				{
					$unit_now = $u != 'dow' ? intval(date($date_pattern)) :
						intval(date($date_pattern,mktime(12,0,0,$found['month'],$found['day'],$found['year'])));

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
						
						if (!isset($next[$n-1]))
						{
							//echo "<p>Nothing found, exiting !!!</p>\n";
							return False;							
						}
						$next = $next[$n-1];
						$over = $found[$next];
						//echo "<p>Have to try the next $next, $u's are over for $next=$over !!!</p>\n";
						break;
					}
					$n++;
				}
			}
			//echo "<p>next="; print_r($found); echo "</p>\n";
			return mktime($found['hour'],$found['min'],0,$found['month'],$found['day'],$found['year']);
		}

		/*!
		@function cancel_timer
		@abstract cancels a timer
		@syntax cancel_timer($id)
		@param $id has to be the one used to set it.
		@returns True if the timer exists and is not expired.
		*/
		function cancel_timer($id)
		{
			return $this->delete($id);
		}

		/*!
		@function check_run
		@abstract checks if there are any jobs ready to run (timer expired) and executes them
		*/
		function check_run()
		{
			if (!($jobs = $this->read()))
			{
				return False;
			}
			foreach($jobs as $id => $job)
			{
				ExecMethod($job['method'],$job['data']);
				
				if ($job['next'] = $this->next_run($job['times']))
				{
					$this->write($job,True);
				}
				else	// no further runs
				{
					$this->delete($job['id']);
				}
			}
			return count($jobs);
		}

		/*!
		@function read
		@abstract reads all matching db-rows / jobs
		@syntax reay($id=0)
		@param $id =0 reads all expired rows / jobs ready to run\
			!= 0 reads all rows/jobs matching $id (sql-wildcards '%' and '_' can be used)
		@returns db-rows / jobs as array or False if no matches
		*/
		function read($id=0)
		{
			$id = $this->db->db_addslashes($id);
			if (strpos($id,'%') !== False || strpos($id,'_') !== False)
			{
				$where = "id LIKE '$id'";
			}
			elseif (!$id)
			{
				$where = 'next<='.time();
			}
			else
			{
				$where = "id='$id'";
			}
			$this->db->query($sql="SELECT * FROM $this->db_table WHERE $where",__LINE__,__FILE__);

			$jobs = array();
			while ($this->db->next_record())
			{
				$id = $this->db->f('id');

				$jobs[$id] = array(
					'id'     => $id,
					'next'   => $this->db->f('next'),
					'times'  => unserialize($this->db->f('times')),
					'method' => $this->db->f('method'),
					'data'   => unserialize($this->db->f('data'))
				);
			}
			if (!count($jobs))
			{
				return False;
			}
			return $jobs;
		}
		
		/*!
		@function write
		@abstract write a job / db-row to the db
		@syntax write($job,$exists = False)
		@param $job db-row as array
		@param $exits if True, we do an update, else we check if update or insert necesary
		*/
		function write($job,$exists = False)
		{
			$job['times'] = $this->db->db_addslashes(serialize($job['times']));
			$job['data']  = $this->db->db_addslashes(serialize($job['data']));

			if ($exists || $this->read($job['id']))
			{
				$this->db->query("UPDATE $this->db_table SET next=$job[next],times='$job[times]',".
					"method='$job[method]',data='$job[data]' WHERE id='$job[id]'",__FILE__,__LINE__);
			}
			else
			{
				$this->db->query("INSERT INTO $this->db_table (id,next,times,method,data) VALUES ".
					"('$job[id]',$job[next],'$job[times]','$job[method]','$job[data]')",__FILE__,__LINE__);
			}
		}

		/*!
		@function delete
		@abstract delete db-row / job with $id
		@returns False if $id not found else True
		*/
		function delete($id)
		{
			$this->db->query("DELETE FROM $this->db_table WHERE id='$id'",__LINE__,__FILE__);

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
				$binarys = array('php' => '/usr/bin/php','crontab' => '/usr/bin/crontab');
				foreach ($binarys as $name => $path)
				{
					$this->$name = $path;	// a reasonable default for *nix
					if (!is_executable($this->$name))
					{
						if ($fd = popen('/bin/sh -c "which '.$name.'"','r'))
						{
							$this->$name = fgets($fd,256);
							@pclose($fd);
						}
						if ($pos = strpos($this->$name,"\n"))
						{
							$this->$name = substr($this->$name,0,$pos);
						}
					}
					//echo "<p>$name = '".$this->$name."'</p>\n";
				}
			}
		
		}
		
		/*!
		@function installed
		@abstract checks if phpgwapi/cron/asyncservices.php is installed as cron-job
		@syntax installed()
		@returns the times asyncservices are run (normaly 'min'=>'* /5') or False if not installed
		@note Not implemented for Windows at the moment, always returns False
		*/
		function installed()
		{
			if (substr(php_uname(), 0, 7) == "Windows") {
				False;
			}
			$this->find_binarys();

			$times = False;
			if (($crontab = popen('/bin/sh -c "'.$this->crontab.' -l" 2>&1','r')) !== False)
			{
				while ($line = fgets($crontab,256))
				{
					if ($line[0] != '#' && strstr($line,'asyncservices.php'))
					{
						$time = explode(' ',$line);
						$cron_units = array('min','hour','day','month','dow');
						foreach($cron_units as $n => $u)
						{
							if ($time[$n] != '*')
							{
								$times[$u] = $time[$n];
							}
						}
						$times['cronline'] = $line;
						break;
					}
				}
				@pclose($crontab);
			}
			return $times;
		}
		
		/*!
		@function insall
		@abstract installs /phpgwapi/cron/asyncservices.php as cron-job
		@syntax install($times)
		@param $times array with keys 'min','hour','day','month','dow', not set is equal to '*'
		@returns the times asyncservices are run or False if they are not installed
		@note Not implemented for Windows at the moment, always dies with an error-message
		*/
		function install($times)
		{
			if (substr(php_uname(), 0, 7) == "Windows") {
				die ("Sorry, no automatic on Windows at the moment !!!\n");
			}
			$this->find_binarys();

			if (($crontab = popen('/bin/sh -c "'.$this->crontab.' -" 2>&1','w')) !== False)
			{
				$cron_units = array('min','hour','day','month','dow');
				foreach($cron_units as $cu)
				{
					$cronline .= (isset($times[$cu]) ? $times[$cu] : '*') . ' ';
				}
				$cronline .= $this->php.' -q '.PHPGW_SERVER_ROOT . '/phpgwapi/cron/asyncservices.php'."\n";
				//echo "<p>Installing: '$cronline'</p>\n";
				fwrite($crontab,$cronline);
				@pclose($crontab);
			}
			else
			{
				//echo "<p>Error: /usr/bin/crontab not found !!!</p>";
				return False;
			}
			return $this->installed();
		}
		
		function test($data)
		{
			echo "asyncservice::test: data =\n";

			print_r($data);
		}
	}

if (!isset($GLOBALS['phpgw_info']))
{	
	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp' => 'login'
	);
	include('../../header.inc.php');

	$async = new asyncservice;
	$units = array(
		'year'  => 'Year',
		'month' => 'Month',
		'day'   => 'Day',
		'dow'   => 'Day of week',
		'hour'  => 'Hour',
		'min'   => 'Minute'
	);
	
	if ($_POST['send'] || $_POST['test'] || $_POST['cancel'] || $_POST['install'])
	{
		$times = array();
		foreach($units as $u => $ulabel)
		{
			if (!empty($_POST[$u]))
			{
				$times[$u] = $_POST[$u];
			}
		}
		$next = $async->next_run($times,True);
		
		echo "<p>async::next_run(";print_r($times);echo")=".($next === False ? 'False':"'$next'=".date('D(w) d.m.Y H:i',$next))."</p>\n";
		
		if ($_POST['test'])
		{
			if (!$async->set_timer($times,'test','phpgwapi.asyncservice.test','Hello World!!!'))
			{
				echo "<p>Error setting timer, maybe there's one already running !!!</p>\n";
			}
		}
		if ($_POST['cancel'])
		{
			if (!$async->cancel_timer('test'))
			{
				echo "<p>Error canceling timer, maybe there's none set !!!</p>\n";
			}
		}
		if ($_POST['install'])
		{
			if ($install = $async->install($times))
			{
				echo "<p>Installing: '$install[cronline]'</p>\n";
			}
			else
			{
				echo "<p>Error: $async->crontab not found or other error !!!</p>";
			}
		}
	}
	echo '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">'."\n";
	foreach ($units as $u => $ulabel)
	{
		echo "$ulabel: <input name=\"$u\" value=\"$times[$u]\" size=5> &nbsp;\n";
	}
	echo "<input type=\"submit\" name=\"send\" value=\"Calculate next run\">\n";
	echo "<input type=\"submit\" name=\"test\" value=\"Start TestJob!\">\n";
	echo "<input type=\"submit\" name=\"cancel\" value=\"Cancel TestJob!\">\n";
	echo "<p><b>crontab:</b> \n";

	if ($installed = $async->installed())
	{
		echo "$installed[cronline]</p>";
	}
	else
	{
		echo "$async->crontab not found or asyncservices not installed !!!</p>";
	}
	echo "<input type=\"submit\" name=\"install\" value=\"Install crontab\">\n";
	
	echo "<p><b>jobs:</b></p>\n";
	if ($jobs = $async->read('%'))
	{
		echo "<table border=1>\n<tr>\n<th>Id</th><th>Next run</th><th>Times</th><th>Method</th><th>Data</th></tr>\n";
		foreach($jobs as $job)
		{
			echo "<tr>\n<td>$job[id]</td><td>".date('Y/m/d H:i',$job['next'])."</td><td>";
			print_r($job['times']); 
			echo "</td><td>$job[method]</td><td>"; 
			print_r($job['data']); 
			echo "</td></tr>\n"; 
		}
		echo "</table>\n";
	}
	else
	{
		echo "<p>No jobs in the database !!!</p>\n";
	}
	echo "</form>\n";
}
