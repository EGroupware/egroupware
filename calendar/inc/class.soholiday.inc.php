<?php
	/**************************************************************************\
	* eGroupWare - Holiday                                                     *
	* http://www.egroupware.org                                                *
	* Maintained and further developed by RalfBecker@outdoor-training.de       *
	* Originaly written by Mark Peters <skeeter@phpgroupware.org>             *
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
			$this->db->set_app('calendar');
			$this->table = 'phpgw_cal_holidays';
		}

		/* Begin Holiday functions */
		function save_holiday($holiday)
		{
			// observance_rule is either "True" or unset !
			$holiday['observance_rule'] = @$holiday['observance_rule'] ? 1 : 0;
			$holiday['locale'] = strtoupper($holiday['locale']);

			foreach($holiday as $name => $val)
			{
				if (substr($name,0,4) != 'hol_')
				{
					if (!is_numeric($name))
					{
						$holiday['hol_'.$name] = $holiday[$name];
					}
					unset($holiday[$name]);
				}
			}
			$hol_id = $holiday['hol_id'];
			unset($holiday['hol_id']);

			if ($hol_id)
			{
				if($this->debug)
				{
					echo "Updating LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
				}
				$this->db->update($this->table,$holiday,array('hol_id' => $hol_id),__LINE__,__FILE__);
			}
			else
			{
				if($this->debug)
				{
					echo "Inserting LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
				}
				$this->db->insert($this->table,$holiday,False,__LINE__,__FILE__);
			}
		}

		function store_to_array(&$holidays)
		{
			while($this->db->next_record())
			{
				$holidays[] = Array(
					'index'			=> $this->db->f('hol_id'),
					'locale'		=> $this->db->f('hol_locale'),
					'name'			=> $GLOBALS['phpgw']->strip_html($this->db->f('hol_name')),
					'day'			=> (int)$this->db->f('hol_mday'),
					'month'			=> (int)$this->db->f('hol_month_num'),
					'occurence'		=> (int)$this->db->f('hol_occurence'),
					'dow'			=> (int)$this->db->f('hol_dow'),
					'observance_rule'	=> $this->db->f('hol_observance_rule')
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

			$where = $this->_build_where($locales,$query,$order,$year);

			if($this->debug)
			{
				echo 'Read Holidays : '.$where.'<br>'."\n";
			}

			$this->db->select($this->table,'*',$where,__LINE__,__FILE__);
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
			$this->db->select($this->table,'*',array('hol_id'=>$id),__LINE__,__FILE__);
			$this->store_to_array($holidays);
			@reset($holidays);
			return $holidays[0];
		}

		function delete_holiday($id)
		{
			$this->db->delete($this->table,array('hol_id' => $id),__LINE__,__FILE__);
		}

		function delete_locale($locale)
		{
			$this->db->delete($this->table,array('hol_local' => $locale),__LINE__,__FILE__);
		}
		
		/* Private functions */
		function _build_where($locales,$query='',$order='',$year=0,$add_order_by=True)
		{
			$querymethod = 'hol_locale';
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
				$querymethod .= " AND hol_name LIKE ".$this->db->quote('%'.$query.'%');
			}
			if ($year > 1900)
			{
				$querymethod .= " AND (hol_occurence < 1900 OR hol_occurence = ".(int)$year.")";
			}
			if ($add_order_by)
			{
				$querymethod .= ' ORDER BY '.(preg_match('/^[a-zA-Z0-9_,]+$/',$order) ? $order : 'hol_month_num,hol_mday');
			}
			return $querymethod;
		}

		function get_locale_list($sort='', $order='', $query='')
		{
			$querymethod = '';
			if($query)
			{
				$querymethod .= " WHERE hol_locale LIKE ".$this->db->quote('%'.$query.'%');
			}
		
			if(preg_match('/[a-zA-Z0-9_,]+/',$order))
			{
				$querymethod .= ' ORDER BY '.$order;
			}
			$this->db->select($this->table,'DISTINCT hol_locale',$querymethod,__LINE__,__FILE__);
			while($this->db->next_record())
			{
				$locale[] = $this->db->f('locale');
			}
			return $locale;
		}
		
		function holiday_total($locale,$query='',$year=0)
		{
			$where = $this->_build_where($locale,$query,'',$year,False);

			if($this->debug)
			{
				echo 'HOLIDAY_TOTAL : '.$where.'<br>'."\n";
			}
			
			$this->db->select($this->table,'count(*)',$where,__LINE__,__FILE__);
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
