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

	function encryptalgo($config)
	{
		if(@function_exists('mcrypt_list_algorithms'))
		{
			$listed = array();
			if(!isset($config['mcrypt_algo']))
			{
				$config['mcrypt_algo'] = 'tripledes';  /* MCRYPT_TRIPLEDES */
			}
			$algos = @mcrypt_list_algorithms();

			if(@is_array($algos))
			{
				while (list ($key, $value) = each ($algos))
				{
					/* Only show each once - seems this is a problem in some installs */
					if(!in_array($value,$listed))
					{
						if ($config['mcrypt_algo'] == $value)
						{
							$selected = ' selected';
						}
						else
						{
							$selected = '';
						}
						$descr = strtoupper($value);
		
						$out .= '<option value="' . $value . '"' . $selected . '>' . $descr . '</option>' . "\n";
						$listed[] = $value;
					}
				}
			}
			else
			{
				/* Something is wrong with their mcrypt install or php.ini */
				$out = '<option value="tripledes">' . lang('no algorithms available') . '</option>' . "\n";;
			}
		}
		else
		{
			$out = '<option value="tripledes">TRIPLEDES</option>' . "\n";;
		}
		return $out;
	}

	function encryptmode($config)
	{
		if(@function_exists('mcrypt_list_modes'))
		{
			$listed = array();
			if(!isset($config['mcrypt_mode']))
			{
				$config['mcrypt_mode'] = 'cbc'; /* MCRYPT_MODE_CBC */
			}
			$modes = @mcrypt_list_modes();

			if(@is_array($modes))
			{
				while (list ($key, $value) = each ($modes))
				{
					/* Only show each once - seems this is a problem in some installs */
					if(!in_array($value,$listed))
					{
						if ($config['mcrypt_mode'] == $value)
						{
							$selected = ' selected';
						}
						else
						{
							$selected = '';
						}
						$descr = strtoupper($value);
		
						$out .= '<option value="' . $value . '"' . $selected . '>' . $descr . '</option>' . "\n";
						$listed[] = $value;
					}
				}
			}
			else
			{
				/* Something is wrong with their mcrypt install or php.ini */
				$out = '<option value="cbc" selected>' . lang('no modes available') . '</option>' . "\n";
			}
		}
		else
		{
			$out = '<option value="cbc" selected>CBC</option>' . "\n";
		}
		return $out;
	}
?>
