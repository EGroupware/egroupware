<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	function country_set($config)
	{
		$country  = array(
			'user_choice'  => 'Users Choice',
			'force_select' => 'Force Selectbox'
		);

		$out = '';
		while (list ($key, $value) = each ($country))
		{
			if ($config['countrylist'] == $key)
			{
				$selected = ' selected';
			}
			else
			{
				$selected = '';
			}
			$descr = lang($value);

			$out .= '<option value="' . $key . '"' . $selected . '>' . $descr . '</option>' . "\n";
		}
		return $out;
	}
?>
