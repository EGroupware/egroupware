<?php
	/**************************************************************************\
	* eGroupWare - FeLaMiMail                                                  *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; version 2 of the License                       *
	\**************************************************************************/

	/* $Id$ */

	$d1 = strtolower(substr(APP_INC,0,3));
	if($d1 == 'htt' || $d1 == 'ftp' )
	{
		echo "Failed attempt to break in via an old Security Hole!<br>\n";
		$GLOBALS['egw']->common->egw_exit();
	}
	unset($d1);

	if (@$GLOBALS['egw_info']['user']['apps']['felamimail'])
	{
		$my_msg_bootstrap = '';

		$my_msg_bootstrap =& CreateObject("felamimail.bofelamimail");

		$connectionStatus = $my_msg_bootstrap->openConnection();

		$folderStatus = $my_msg_bootstrap->getFolderStatus('INBOX');
		#_debug_array($folderStatus);
		$my_msg_bootstrap->closeConnection();
		
		$current_uid=$folderStatus['uidnext'];
		
		$oldUidNext=$GLOBALS['egw']->session->appsession('notifywindow','felamimail');
		
		#if(!empty($old_uid))
		#{
		#	$new_msgs=$current_id-$old_id;
		#}
		#else
		#{
		# 	$new_msgs=$inbox_data['number_new'];
		#}
		
		if ($connectionStatus == True)
		{
			// we reload the notify window, because after i did click on the CheckEmail link
			// it did not refresh itself anymore
			echo '<script language="JavaScript">'."\n";
			echo '	<!-- Activate Cloaking Device'."\n";
			echo '	function CheckEmail()'."\n";
			echo '	{'."\n";
			echo '		window.opener.document.location.href="'.$GLOBALS['egw']->link('/index.php','menuaction=felamimail.uifelamimail.viewMainScreen').'";'."\n";
			echo '		window.opener.focus()'."\r\n";
			#echo '          window.resizeTo(1,1);'."\r\n";
			echo '          window.blur();'."\r\n";
			echo '		location.reload()'."\n";
			echo '	}'."\n";
			echo '	//-->'."\n";
			echo '	</script>'."\n";
			echo "\r\n" . '<tr><td align="left"><!-- Mailbox info X10 -->' . "\r\n";
			echo '<table width="100%" style="border-color:#000000;border-style:solid;border-width:1px;"><tr>'."\r\n";
			echo '<td width="20%" valign="middle" align="center">'."\r\n";
			echo '<a href="JavaScript:CheckEmail();"><img src="'.$GLOBALS['egw']->common->image('felamimail','navbar').'" alt="email icon" border=0></a>'."\r\n";
			echo "<td>\r\n";

			if($folderStatus[recent]>0)
			{
			 	echo '<a href="JavaScript:CheckEmail();"><b>'.lang('new').':</b> '.$folderStatus[recent].'</a><br>';
			 	
			 	if($oldUidNext != $folderStatus['uidnext'] || $folderStatus[recent]>0)
				{
					$urgent = True;
				}
			}
			else
			{
			 	echo '<a href="JavaScript:CheckEmail();"><b>'.lang('new').':</b> '.lang('None').'</a><br>'."\r\n";
			}
			
			if($folderStatus[unseen]>0)
			{
			 	echo '<a href="JavaScript:CheckEmail();"><b>'.lang('unread').':</b> '.$folderStatus[unseen].'</a><br>'."\r\n";
			}
			else
			{
			 	echo '<a href="JavaScript:CheckEmail();"><b>'.lang('unread').':</b> '.lang('None').'</a><br>'."\r\n";
			}

		 	echo '<a href="JavaScript:CheckEmail();"><b>INBOX:</b> '.$folderStatus[messages].'</a>'."\r\n";

			if($urgent == True)
			{
				echo '<script type="text/javascript" language="Javascript 1.3">'."\r\n";
				echo 'document.bgcolor="#666666";'."\r\n";
				#echo 'window.resizeTo(300,170);'."\r\n";
				echo 'window.focus();'."\r\n";
				echo '</script>'."\r\n";
			}

			echo "</td></tr></table>\r\n";
			echo "\r\n".'<!-- Mailox info --></td></tr>'."\r\n";
		}
		$GLOBALS['egw']->session->appsession('notifywindow','felamimail',$folderStatus['uidnext']);
	}
?>
