<?php
/**************************************************************************\
* phpGroupWare - E-Mail                                                    *
* http://www.phpgroupware.org                                              *
* This file written by Joseph Engo <jengo@phpgroupware.org>                *
+   and Miles Lott <miloschjengo@phpgroupware.org>                         *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

	if ($action == 'Load Vcard')
	{
		$phpgw_info['flags'] = array(
			'noheader'   => True,
			'nonavbar'   => True,
			'currentapp' => 'addressbook',
			'enable_contacts_class' => True
		);
		include('../header.inc.php');
	}
	else
	{
		$phpgw_info['flags'] = array(
			'currentapp' => 'addressbook',
			'enable_contacts_class' => True
		);
		include('../header.inc.php');
		echo '<body bgcolor="' . $phpgw_info['theme']['bg_color'] . '">';
	}
  
	$uploaddir = $phpgw_info['server']['temp_dir'] . SEP;

	if ($action == 'Load Vcard')
	{
		if($uploadedfile == 'none' || $uploadedfile == '')
		{
			Header('Location: ' . $phpgw->link('/addressbook/vcardin.php','action=GetFile'));
		}
		else
		{
			srand((double)microtime()*1000000);
			$random_number = rand(100000000,999999999);
			$newfilename = md5("$uploadedfile, $uploadedfile_name, "
						. time() . getenv("REMOTE_ADDR") . $random_number );

			copy($uploadedfile, $uploaddir . $newfilename);
			$ftp = fopen($uploaddir . $newfilename . '.info','w');
			fputs($ftp,"$uploadedfile_type\n$uploadedfile_name\n");
			fclose($ftp);

			$filename = $uploaddir . $newfilename;

			$vcard = CreateObject('phpgwapi.vcard');
			$entry = $vcard->in_file($filename);
			/* _debug_array($entry);exit; */
			$contacts = CreateObject('phpgwapi.contacts');
			$contacts->add($phpgw_info['user']['account_id'],$entry);

			/* Delete the temp file. */
			unlink($filename);
			unlink($filename . '.info');
			Header('Location: ' . $phpgw->link('/addressbook/', 'cd=14'));
		}
	}

	if ($action == 'GetFile')
	{
		echo '<b><center>You must select a vcard. (*.vcf)</b></center><br><br>';
	}

	$t = new Template(PHPGW_APP_TPL);
	$t->set_file(array('vcardin' => 'vcardin.tpl'));

	$vcard_header  = '<p>&nbsp;<b>' . lang('Address book - VCard in') . '</b><hr><p>';

	$t->set_var(vcard_header,$vcard_header);
	$t->set_var(action_url,$phpgw->link('/addressbook/vcardin.php'));
	$t->set_var(lang_access,lang('Access'));
	$t->set_var(lang_groups,lang('Which groups'));
	$t->set_var(access_option,$access_option);

	$t->set_var(group_option,$group_option);

	$t->pparse('out','vcardin');

	if ($action != 'Load Vcard')
	{
		$phpgw->common->phpgw_footer();
	}
?>
