<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_baseline = array(
		'egw_cal' => array(
			'fd' => array(
				'cal_id' => array('type' => 'auto','nullable' => False),
				'cal_uid' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'cal_owner' => array('type' => 'int','precision' => '4','nullable' => False),
				'cal_category' => array('type' => 'varchar','precision' => '30'),
				'cal_modified' => array('type' => 'int','precision' => '8'),
				'cal_priority' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '2'),
				'cal_public' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '1'),
				'cal_title' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '1'),
				'cal_description' => array('type' => 'text'),
				'cal_location' => array('type' => 'varchar','precision' => '255'),
				'cal_reference' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'cal_modifier' => array('type' => 'int','precision' => '4'),
				'cal_non_blocking' => array('type' => 'int','precision' => '2','default' => '0')
			),
			'pk' => array('cal_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'egw_cal_holidays' => array(
			'fd' => array(
				'hol_id' => array('type' => 'auto','nullable' => False),
				'hol_locale' => array('type' => 'char','precision' => '2','nullable' => False),
				'hol_name' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'hol_mday' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
				'hol_month_num' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
				'hol_occurence' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
				'hol_dow' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
				'hol_observance_rule' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0')
			),
			'pk' => array('hol_id'),
			'fk' => array(),
			'ix' => array('hol_locale'),
			'uc' => array()
		),
		'egw_cal_repeats' => array(
			'fd' => array(
				'cal_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'recur_type' => array('type' => 'int','precision' => '2','nullable' => False),
				'recur_enddate' => array('type' => 'int','precision' => '8'),
				'recur_interval' => array('type' => 'int','precision' => '2','default' => '1'),
				'recur_data' => array('type' => 'int','precision' => '2','default' => '1'),
				'recur_exception' => array('type' => 'text')
			),
			'pk' => array('cal_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'egw_cal_user' => array(
			'fd' => array(
				'cal_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'cal_recur_date' => array('type' => 'int','precision' => '8','default' => '0'),
				'cal_user_type' => array('type' => 'varchar','precision' => '1','nullable' => False,'default' => 'u'),
				'cal_user_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'cal_status' => array('type' => 'char','precision' => '1','default' => 'A'),
				'cal_quantity' => array('type' => 'int','precision' => '4','default' => '1')
			),
			'pk' => array('cal_id','cal_recur_date','cal_user_type','cal_user_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'egw_cal_extra' => array(
			'fd' => array(
				'cal_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'cal_extra_name' => array('type' => 'varchar','precision' => '40','nullable' => False),
				'cal_extra_value' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '')
			),
			'pk' => array('cal_id','cal_extra_name'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'egw_cal_dates' => array(
			'fd' => array(
				'cal_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'cal_start' => array('type' => 'int','precision' => '8','nullable' => False),
				'cal_end' => array('type' => 'int','precision' => '8','nullable' => False)
			),
			'pk' => array('cal_id','cal_start'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
