<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_baseline = array(
		'phpgw_cal' => array(
			'fd' => array(
				'cal_id' => array('type' => 'auto','nullable' => False),
				'uid' => array('type' => 'varchar', 'precision' => 255,'nullable' => False),
				'owner' => array('type' => 'int', 'precision' => 8,'nullable' => False),
				'category' => array('type' => 'varchar', 'precision' => 30,'nullable' => True),
				'groups' => array('type' => 'varchar', 'precision' => 255,'nullable' => True),
				'datetime' => array('type' => 'int', 'precision' => 8,'nullable' => True),
				'mdatetime' => array('type' => 'int', 'precision' => 8,'nullable' => True),
				'edatetime' => array('type' => 'int', 'precision' => 8,'nullable' => True),
				'priority' => array('type' => 'int', 'precision' => 8,'nullable' => False,'default' => 2),
				'cal_type' => array('type' => 'varchar', 'precision' => 10,'nullable' => True),
				'is_public' => array('type' => 'int', 'precision' => 8,'nullable' => False,'default' => 1),
				'title' => array('type' => 'varchar', 'precision' => 80,'nullable' => False,'default' => '1'),
				'description' => array('type' => 'text','nullable' => True),
				'location' => array('type' => 'varchar', 'precision' => 255,'nullable' => True),
				'reference' => array('type' => 'int', 'precision' => 8, 'nullable' => False, 'default' => 0)
			),
			'pk' => array('cal_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_cal_holidays' => array(
			'fd' => array(
				'hol_id' => array('type' => 'auto','nullable' => False),
				'locale' => array('type' => 'char', 'precision' => 2, 'nullable' => False),
				'name' => array('type' => 'varchar', 'precision' => 50, 'nullable' => False),
				'mday' => array('type' => 'int', 'precision' => 8, 'nullable' => False, 'default' => 0),
				'month_num' => array('type' => 'int', 'precision' => 8, 'nullable' => False, 'default' => 0),
				'occurence' => array('type' => 'int', 'precision' => 8, 'nullable' => False, 'default' => 0),
				'dow' => array('type' => 'int', 'precision' => 8, 'nullable' => False, 'default' => 0),
				'observance_rule' => array('type' => 'int', 'precision' => 8, 'nullable' => False, 'default' => 0)
			),
			'pk' => array('hol_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_cal_repeats' => array(
			'fd' => array(
				'cal_id' => array('type' => 'int', 'precision' => 8, 'nullable' => False),
				'recur_type' => array('type' => 'int', 'precision' => 8, 'nullable' => False),
				'recur_use_end' => array('type' => 'int', 'precision' => 8, 'nullable' => True, 'default' => 0),
				'recur_enddate' => array('type' => 'int', 'precision' => 8, 'nullable' => True),
				'recur_interval' => array('type' => 'int', 'precision' => 8, 'nullable' => True, 'default' => 1),
				'recur_data' => array('type' => 'int', 'precision' => 8, 'nullable' => True, 'default' => 1),
				'recur_exception' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True, 'default' => '')
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_cal_user' => array(
			'fd' => array(
				'cal_id' => array('type' => 'int', 'precision' => 8, 'nullable' => False, 'default' => 0),
				'cal_login' => array('type' => 'int', 'precision' => 8, 'nullable' => False, 'default' => 0),
				'cal_status' => array('type' => 'char', 'precision' => 1, 'nullable' => True, 'default' => 'A')
			),
			'pk' => array('cal_id','cal_login'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_cal_alarm' => array(
			'fd' => array(
				'alarm_id' => array('type' => 'auto','nullable' => False),		
				'cal_id' => array('type' => 'int', 'precision' => 8, 'nullable' => False),
				'cal_owner'	=> array('type' => 'int', 'precision' => 8, 'nullable' => False),
				'cal_time' => array('type' => 'int', 'precision' => 8, 'nullable' => False),
				'cal_text' => array('type' => 'varchar', 'precision' => 50, 'nullable' => False),
				'alarm_enabled' => array('type' => 'int', 'precision' => 4, 'nullable' => False, 'default' => '1')
			),
			'pk' => array('alarm_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
?>
