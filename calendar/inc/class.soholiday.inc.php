<?php
/**
 * eGroupWare - Calendar Holidays
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Mark Peters <skeeter@phpgroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Storage layer for calendar holidays
 *
 * Maintained and further developed by RalfBecker@outdoor-training.de
 * Originaly written by Mark Peters <skeeter@phpgroupware.org>
 */
class soholiday
{
	var $debug = False;
	/**
	 * Reference to the global db-object
	 *
	 * @var egw_db
	 */
	var $db;
	var $table = 'egw_cal_holidays';

	function soholiday()
	{
		$this->db = $GLOBALS['egw']->db;
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
					$holiday['hol_'.$name] = $val;
				}
				unset($holiday[$name]);
			}
		}
		$hol_id = $holiday['hol_id'];
		unset($holiday['hol_id']);
		unset($holiday['hol_locales']);

		if ($hol_id)
		{
			if($this->debug)
			{
				echo "Updating LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
			}
			$this->db->update($this->table,$holiday,array('hol_id' => $hol_id),__LINE__,__FILE__,'calendar');
		}
		else
		{
			if($this->debug)
			{
				echo "Inserting LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
			}
			// delete evtl. existing rules with same name, year (occurence) and local
			$this->db->delete($this->table, array(
				'hol_name' => $holiday['hol_name'],
				'hol_occurence' => $holiday['hol_occurence'],
				'hol_locale' => $holiday['hol_locale'],
			), __LINE__, __FILES__, 'calendar');
			$this->db->insert($this->table,$holiday,False,__LINE__,__FILE__,'calendar');
		}
	}

	function store_to_array(&$holidays,$rs)
	{
		foreach($rs as $row)
		{
			$holidays[] = Array(
				'index'			=> $row['hol_id'],
				'locale'		=> $row['hol_locale'],
				'name'			=> $GLOBALS['egw']->strip_html($row['hol_name']),
				'day'			=> (int)$row['hol_mday'],
				'month'			=> (int)$row['hol_month_num'],
				'occurence'		=> (int)$row['hol_occurence'],
				'dow'			=> (int)$row['hol_dow'],
				'observance_rule'	=> $row['hol_observance_rule']
			);
			if($this->debug)
			{
				echo 'Holiday ID: '.$row['hol_id'].'<br>'."\n";
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

		$rs = $this->db->select($this->table,'*',$where,__LINE__,__FILE__,false,'','calendar');
		$this->store_to_array($holidays,$rs);

		return $holidays;
	}

	function read_holiday($id)
	{
		$holidays = Array();
		if($this->debug)
		{
			echo 'Reading Holiday ID : '.$id.'<br>'."\n";
		}
		$rs = $this->db->select($this->table,'*',array('hol_id'=>$id),__LINE__,__FILE__,false,'','calendar');
		$this->store_to_array($holidays,$rs);
		return $holidays[0];
	}

	function delete_holiday($id)
	{
		$this->db->delete($this->table,array('hol_id' => $id),__LINE__,__FILE__,'calendar');
	}

	function delete_locale($locale)
	{
		$this->db->delete($this->table,array('hol_locale' => $locale),__LINE__,__FILE__,'calendar');
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
			$querymethod = 'hol_locale LIKE '.$this->db->quote('%'.$query.'%');
		}

		if(!preg_match('/^[a-zA-Z0-9_,]+$/',$order))
		{
			$order = 'hol_locale';
		}
		if (strtoupper($sort) != 'DESC') $sort = 'ASC';
		if (strpos($order, ',') === false) $order .= ' '.$sort;
		foreach($this->db->select($this->table,'DISTINCT hol_locale',$querymethod,__LINE__,__FILE__,false,'ORDER BY '.$order,'calendar') as $row)
		{
			$locale[] = $row['hol_locale'];
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

		$retval = $this->db->select($this->table,'count(*)',$where,__LINE__,__FILE__,false,'','calendar')->fetchColumn();

		if($this->debug)
		{
			echo 'Total Holidays for : '.$locale.' : '.$retval."<br>\n";
		}
		return $retval;
	}
}
