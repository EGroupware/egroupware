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
		var $db;

		function soholiday()
		{
			global $phpgw;

			$this->db = $phpgw->db;
		}

		/* Begin Holiday functions */
		function save_holiday($holiday)
		{
			if(isset($holiday['hol_id']) && $holiday['hol_id'])
			{
//				echo "Updating LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
				$sql = "UPDATE phpgw_cal_holidays SET name='".$holiday['name']."', mday=".$holiday['mday'].', month_num='.$holiday['month_num'].', occurence='.$holiday['occurence'].', dow='.$holiday['dow'].', observance_rule='.intval($holiday['observance_rule']).' WHERE hol_id='.$holiday['hol_id'];
			}
			else
			{
//				echo "Inserting LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
				$sql = 'INSERT INTO phpgw_cal_holidays(locale,name,mday,month_num,occurence,dow,observance_rule) '
					. "VALUES('".strtoupper($holiday['locale'])."','".$holiday['name']."',".$holiday['mday'].','.$holiday['month_num'].','.$holiday['occurence'].','.$holiday['dow'].','.intval($holiday['observance_rule']).")";
			}
			$this->db->query($sql,__LINE__,__FILE__);
		}

		function read_holidays($locales='')
		{
			global $phpgw;

			$holidays = Array();

			if($locales == '')
			{
				return $holidays;
			}
			
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

			$sql = 'SELECT * FROM phpgw_cal_holidays WHERE locale in ('.$find.')';

			$this->db->query($sql,__LINE__,__FILE__);
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
			}
			return $holidays;
		}

		/* Private functions */
		function count_of_holidays($locale)
		{
			$sql = "SELECT count(*) FROM phpgw_cal_holidays WHERE locale='".$locale."'";
			$this->db->query($sql,__LINE__,__FILE__);
			$this->db->next_record();
			return $this->db->f(0);
		}
	}
?>
