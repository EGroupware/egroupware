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

	$send_back_to = str_replace('submit','admin',$_SERVER['HTTP_REFERER']);
	if(!$_POST['locale'])
	{
		Header('Location: '.$send_back_to);
	}

	$send_back_to = str_replace('&locale='.$_POST['locale'],'',$send_back_to);
	$file = './holidays.'.$_POST['locale'].'.csv';
	if(!file_exists($file))
	{
		if (count($_POST['name']))
		{
			$c_holidays = count($_POST['name']);
			$fp = fopen($file,'w');
			for($i=0;$i<$c_holidays;$i++)
			{
				fwrite($fp,$_POST['locale']."\t".$_POST['name'][$i]."\t".$_POST['day'][$i]."\t".$_POST['month'][$i]."\t".$_POST['occurence'][$i]."\t".$_POST['dow'][$i]."\t".$_POST['observance'][$i]."\n");
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
	<title>eGroupWare.org: There is already a holiday-file for '<?php echo $_POST['locale']; ?>' !!!</title>
</head>
<body>
	<h1>There is already a holiday-file for '<?php echo $_POST['locale']; ?>' !!!</h1>

	<p>If you think your version of the holidays for '<?php echo $_POST['locale']; ?>' should replace
	the existing one, please <a href="<?php echo $_SERVER['HTTP_REFERER']; ?>&download=1">download</a> the file
	and <a href="mailto:egroupware-developers@lists.sourceforge.net">mail it</a> to us.</p>

	<p>To get back to your own eGroupWare-install <a href="<?php echo $send_back_to; ?>">click here</a>.</p>
</body>
</html>
<?php
	}
?>
