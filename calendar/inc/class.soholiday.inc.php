<?php
  /**************************************************************************\
  * eGroupWare - Holiday                                                     *
  * http://www.egroupware.org                                                *
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
			$this->db = $GLOBALS['phpgw']->db;
			$this->table = 'phpgw_cal_holidays';
			$this->table_definition = $this->db->get_table_definitions('calendar',$this->table);
			$this->db->set_column_definitions($this->table_definition['fd']);
		}

		/* Begin Holiday functions */
		function save_holiday($holiday)
		{
			// observance_rule is either "True" or unset !
			$holiday['observance_rule'] = @$holiday['observance_rule'] ? 1 : 0;
			$holiday['locale'] = strtoupper($holiday['locale']);

			if(@$holiday['hol_id'])
			{
				if($this->debug)
				{
					echo "Updating LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
				}
				$sql = "UPDATE $this->table SET ".$this->db->column_data_implode(',',$holiday,True,True).' WHERE hol_id='.(int)$holiday['hol_id'];
			}
			else
			{
				if($this->debug)
				{
					echo "Inserting LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
				}
				unset($holiday['hol_id']);	// in case its 0
				$sql = "INSERT INTO $this->table ".$this->db->column_data_implode(',',$holiday,'VALUES',True);
			}
			//echo "<p>soholiday::save_holiday(".print_r($holiday,True).") sql='$sql'</p>\n";
			$this->db->query($sql,__LINE__,__FILE__);
		}

		function store_to_array(&$holidays)
		{
			while($this->db->next_record())
			{
				$holidays[] = Array(
					'index'			=> $this->db->f('hol_id'),
					'locale'		=> $this->db->f('locale'),
					'name'			=> $GLOBALS['phpgw']->strip_html($this->db->f('name')),
					'day'			=> (int)$this->db->f('mday'),
					'month'			=> (int)$this->db->f('month_num'),
					'occurence'		=> (int)$this->db->f('occurence'),
					'dow'			=> (int)$this->db->f('dow'),
					'observance_rule'	=> $this->db->f('observance_rule')
				);
				if($this->debug)
				{
					echo 'Holiday ID: '.$this->db->f('hol_id').'<br>'."\n";
				}
			}
		}

		function read_holidays($locales='',$query='',$order='',$year=0)
		{
			$holidays = Array();

			if($locales == '')
			{
				return $holidays;
			}

			$sql = $this->build_query($locales,$query,$order,$year);

			if($this->debug)
			{
				echo 'Read Holidays : '.$sql.'<br>'."\n";
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
				echo 'Reading Holiday ID : '.$id.'<br>'."\n";
			}
			$this->db->query("SELECT * FROM $this->table WHERE hol_id=".(int)$id,__LINE__,__FILE__);
			$this->store_to_array($holidays);
			@reset($holidays);
			return $holidays[0];
		}

		function delete_holiday($id)
		{
			$this->db->query("DELETE FROM $this->table WHERE hol_id=".(int)$id,__LINE__,__FILE__);
		}

		function delete_locale($locale)
		{
			$this->db->query("DELETE FROM $this->table WHERE locale=".$this->db->quote($locale),__LINE__,__FILE__);
		}
		
		/* Private functions */
		function build_query($locales,$query='',$order='',$year=0)
		{
			$querymethod = 'locale';
			if (is_array($locales))
			{
				$querymethod .= ' IN ('.$this->db->column_data_implode(',',$locales,False).')';
			}
			else
			{
				$querymethod .= '='.$this->db->quote($locales);
			}
			if($query)
			{
				$querymethod .= " AND name LIKE ".$this->db->quote('%'.$query.'%');
			}
			if ($year > 1900)
			{
				$querymethod .= " AND (occurence < 1900 OR occurence = ".(int)$year.")";
			}
			$querymethod .= ' ORDER BY '.(preg_match('/[a-zA-Z0-9_,]+/',$order) ? $order : 'month_num,mday');

			return "SELECT * FROM $this->table WHERE ".$querymethod;
		}

		function get_locale_list($sort='', $order='', $query='')
		{
			$querymethod = '';
			if($query)
			{
				$querymethod .= " WHERE locale LIKE ".$this->db->quote('%'.$query.'%');
			}
		
			if(preg_match('/[a-zA-Z0-9_,]+/',$order))
			{
				$querymethod .= ' ORDER BY '.$order;
			}
			$this->db->query("SELECT DISTINCT locale FROM $this->table".$querymethod,__LINE__,__FILE__);
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
				$querymethod = ' AND name LIKE '.$this->db->quote('%'.$query.'%');
			}
			if ($year >= 1900)
			{
				$querymethod .= ' AND (occurence < 1900 OR occurence = '.(int)$year.")";
			}
			$sql = "SELECT count(*) FROM $this->table WHERE locale=".$this->db->quote($locale).$querymethod;

			if($this->debug)
			{
				echo 'HOLIDAY_TOTAL : '.$sql.'<br>'."\n";
			}
			
			$this->db->query($sql,__LINE__,__FILE__);
			$this->db->next_record();
			$retval = (int)$this->db->f(0);
			if($this->debug)
			{
				echo 'Total Holidays for : '.$locale.' : '.$retval."<br>\n";
			}
			return $retval;
		}
	}
?>
