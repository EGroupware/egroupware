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

	$test[] = '0.0.1.008';
	function resources_upgrade0_0_1_008()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','picture_src',array(
			'type' => 'varchar',
			'precision' => '20'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.012';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}
?>
