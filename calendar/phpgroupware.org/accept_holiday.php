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
	$send_back_to = str_replace('submit','admin',$HTTP_REFERER);	// 0.9.14.xxx
	if(!$locale)
	{
		Header('Location: '.$send_back_to);
	}

	$send_back_to = str_replace('&locale='.$locale,'',$send_back_to);
	$file = './holidays.'.$locale;
	if(!file_exists($file))
	{
		if (count($name))
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
	}
	else
	{
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<title>phpGroupWare.org: There is already a holiday-file for '<?php echo $locale; ?>' !!!</title>
</head>
<body>
	<h1>There is already a holiday-file for '<?php echo $locale; ?>' !!!</h1>

	<p>If you think your version of the holidays for '<?php echo $locale; ?>' should replace
	the existing one, please <a href="<?php echo $HTTP_REFERER; ?>&download=1">download</a> the file
	and <a href="mailto:phpgroupware-developers@gnu.org">mail it</a> to us.</p>

	<p>To get back to your own phpGroupWare-install <a href="<?php echo $send_back_to; ?>">click here</a>.</p>
</body>
</html>
<?php
	}
?>
