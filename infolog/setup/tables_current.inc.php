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
		'phpgw_infolog' => array(
			'fd' => array(
				'info_id' => array('type' => 'auto','nullable' => False),
				'info_type' => array('type' => 'varchar','precision' => '255','default' => 'task','nullable' => False),
				'info_addr_id' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
				'info_proj_id' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
				'info_from' => array('type' => 'varchar','precision' => '64','nullable' => True),
				'info_addr' => array('type' => 'varchar','precision' => '64','nullable' => True),
				'info_subject' => array('type' => 'varchar','precision' => '64','nullable' => True),
				'info_des' => array('type' => 'text','nullable' => True),
				'info_owner' => array('type' => 'int','precision' => '4','nullable' => False),
				'info_responsible' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
				'info_access' => array('type' => 'varchar','precision' => '10','nullable' => True,'default' => 'public'),
				'info_cat' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
				'info_datemodified' => array('type' => 'int','precision' => '4','nullable' => False),
				'info_startdate' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
				'info_enddate' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
				'info_id_parent' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
				'info_pri' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'normal'),
				'info_time' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
				'info_bill_cat' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
				'info_status' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'done'),
				'info_confirm' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'not'),
				'info_event_id' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False)
			),
			'pk' => array('info_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_links' => array(
			'fd' => array(
				'link_id' => array('type' => 'auto','nullable' => False),
				'link_app1' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'link_id1' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'link_app2' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'link_id2' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'link_remark' => array('type' => 'varchar','precision' => '50','nullable' => True),
				'link_lastmod' => array('type' => 'int','precision' => '4','nullable' => False),
				'link_owner' => array('type' => 'int','precision' => '4','nullable' => False)
			),
			'pk' => array('link_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
