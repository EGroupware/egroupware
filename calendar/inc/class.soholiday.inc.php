<?php
  /**************************************************************************\
  * phpGroupWare - Holiday                                                   *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	class soholiday
	{
		var $debug = False;
		var $db;

		function soholiday()
		{
			global $phpgw;

			$this->db = $phpgw->db;
		}

		/* Begin Holiday functions */
		function save_holiday($holiday)
		{
			if(@$holiday['hol_id'])
			{
				if($this->debug)
				{
					echo "Updating LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
				}
				$sql = "UPDATE phpgw_cal_holidays SET name='".$holiday['name']."', mday=".$holiday['mday'].', month_num='.$holiday['month_num'].', occurence='.$holiday['occurence'].', dow='.$holiday['dow'].', observance_rule='.intval($holiday['observance_rule']).' WHERE hol_id='.$holiday['hol_id'];
			}
			else
			{
				if($this->debug)
				{
					echo "Inserting LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
				}
				$sql = 'INSERT INTO phpgw_cal_holidays(locale,name,mday,month_num,occurence,dow,observance_rule) '
					. "VALUES('".strtoupper($holiday['locale'])."','".$holiday['name']."',".$holiday['mday'].','.$holiday['month_num'].','.$holiday['occurence'].','.$holiday['dow'].','.intval($holiday['observance_rule']).")";
			}
			$this->db->query($sql,__LINE__,__FILE__);
		}

		function store_to_array(&$holidays)
		{
			global $phpgw;
			
			while($this->db->next_record())
			{
				$holidays[] = Array(
					'index'			=> $this->db->f('hol_id'),
					'locale'		=> $this->db->f('locale'),
					'name'			=> $phpgw->strip_html($this->db->f('name')),
					'day'			=> intval($this->db->f('mday')),
					'month'			=> intval($this->db->f('month_num')),
					'occurence'		=> intval($this->db->f('occurence')),
					'dow'			=> intval($this->db->f('dow')),
					'observance_rule'	=> $this->db->f('observance_rule')
				);
				if($this->debug)
				{
					echo "Holiday ID: ".$this->db->f("hol_id")."<br>\n";
				}
			}
		}

		function read_holidays($locales='',$query='',$order='',$year=0)
		{
			global $phpgw;

			$holidays = Array();

			if($locales == '')
			{
				return $holidays;
			}

			$sql = $this->build_query($locales,$query,$order,$year);

			if($this->debug)
			{
				echo "Read Holidays : ".$sql."<br>\n";
			}

			$this->db->query($sql,__LINE__,__FILE__);
			$this->store_to_array($holidays);
			return $holidays;
		}

		function read_holiday($id)
		{
			$holidays = Array();
			if($this->debug)
			{
				echo "Reading Holiday ID : ".$id."<br>\n";
			}
			$this->db->query('SELECT * FROM phpgw_cal_holidays WHERE hol_id='.$id,__LINE__,__FILE__);
			$this->store_to_array($holidays);
			@reset($holidays);
			return $holidays[0];
		}

		function delete_holiday($id)
		{
			$this->db->query('DELETE FROM phpgw_cal_holidays WHERE hol_id='.$id,__LINE__,__FILE__);
		}

		function delete_locale($locale)
		{
			$this->db->query("DELETE FROM phpgw_cal_holidays WHERE locale='".$locale."'",__LINE__,__FILE__);
		}
		
		/* Private functions */
		function build_query($locales,$query='',$order='',$year=0)
		{

			if(is_string($locales))
			{
				$find = "'".$locales."'";
			}
			elseif(is_array($locales))
			{
				$find = '';
				while(list($key,$value) = each($locales))
				{
					if($find)
					{
						$find .= ',';
					}
					$find .= "'".$value."'";
				}
			}

			$querymethod = '';
			if($query)
			{
				$querymethod = " AND name like '%".$query."%'";
			}
			if (intval($year) > 1900)
			{
				$querymethod .= " AND (occurence < 1900 OR occurence = $year)";
			}
			$querymethod .= ' ORDER BY '.($order ? $order : 'month_num,mday');

			return 'SELECT * FROM phpgw_cal_holidays WHERE locale in ('.$find.')'.$querymethod;
		}

		function get_locale_list($sort='', $order='', $query='')
		{
			$querymethod = '';
			if($query)
			{
				$querymethod .= " WHERE locale like '%".$query."%'";
			}
		
			if($order)
			{
				$querymethod .= ' ORDER BY '.$order;
			}
			$this->db->query("SELECT DISTINCT locale FROM phpgw_cal_holidays".$querymethod,__LINE__,__FILE__);
			while($this->db->next_record())
			{
				$locale[] = $this->db->f('locale');
			}
			return $locale;
		}
		
		function holiday_total($locale,$query='',$year=0)
		{
			$querymethod='';
			if($query)
			{
				$querymethod = " AND name like '%".$query."%'";
			}
			if (intval($year) >= 1900)
			{
				$querymethod .= " AND (occurence < 1900 OR occurence = $year)";
			}
			$sql = "SELECT count(*) FROM phpgw_cal_holidays WHERE locale='".$locale."'".$querymethod;

			if($this->debug)
			{
				echo "HOLIDAY_TOTAL : ".$sql."<br>\n";
			}
			
			$this->db->query($sql,__LINE__,__FILE__);
			$this->db->next_record();
			$retval = intval($this->db->f(0));
			if($this->debug)
			{
				echo 'Total Holidays for : '.$locale.' : '.$retval."<br>\n";
			}
			return $retval;
		}
	}
?>
