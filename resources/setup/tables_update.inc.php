<?php
  /**************************************************************************\
  * eGroupWare - Setup                                                       *
  * http://www.eGroupWare.org                                                *
  * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de *
  * --------------------------------------------                             *
  * This program is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU General Public License as published by the    *
  * Free Software Foundation; either version 2 of the License, or (at your   *
  * option) any later version.                                               *
  \**************************************************************************/

  /* $Id$ */

	$test[] = '0.0.1.010';
	function resources_upgrade0_0_1_010()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','quantity',array(
			'type' => 'int',
			'precision' => '11'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','useable',array(
			'type' => 'int',
			'precision' => '11'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.011';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}
?>
