<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
	/* $Id$ */

	$send_back_to = str_replace('submitlocale','holiday_admin',$HTTP_REFERER);
	if(!$locale)
	{
		Header('Location: '.$send_back_to);
	}

	$send_back_to = str_replace('&locale='.$locale,'',$send_back_to);
	$file = './holidays.'.$locale;
	if(!file_exists($file) && count($name))
	{
		$c_holidays = count($name);
		$fp = fopen($file,'w');
		for($i=0;$i<$c_holidays;$i++)
		{
			fwrite($fp,$locale."\t".$name[$i]."\t".$day[$i]."\t".$month[$i]."\t".$occurence[$i]."\t".$dow[$i]."\t".$observance[$i]."\n");
		}
		fclose($fp);
	}
	Header('Location: '.$send_back_to);
?>
