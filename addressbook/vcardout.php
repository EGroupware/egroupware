<?php
/**************************************************************************\
* phpGroupWare - addressbook                                               *
* http://www.phpgroupware.org                                              *
* Written by Joseph Engo <jengo@phpgroupware.org>                          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

	if ($nolname || $nofname)
	{
		$phpgw_info['flags'] = array(
			'noheader'    => False,
			'nonavbar'    => False,
			'noappheader' => False,
			'noappfooter' => False
		);
	}
	else
	{
		$phpgw_info['flags'] = array(
			'noheader'    => True,
			'nonavbar'    => True,
			'noappheader' => True,
			'noappfooter' => True
		);
	}

	$phpgw_info['flags']['enable_contacts_class'] = True;
	$phpgw_info['flags']['enable_browser_class'] = True;
	$phpgw_info['flags']['currentapp'] = 'addressbook';
	include('../header.inc.php');

	if (!$ab_id)
	{
		Header('Location: ' . $phpgw->link('/addressbook/index.php'));
		$phpgw->common->phpgw_exit();
	}

	$this = CreateObject('phpgwapi.contacts');

	// First, make sure they have permission to this entry
	$check = addressbook_read_entry($ab_id,array('owner' => 'owner'));
	$perms = $this->check_perms($this->grants[$check[0]['owner']],PHPGW_ACL_READ);

	if ( (!$perms) && ($check[0]['owner'] != $phpgw_info['user']['account_id']) )
	{
		Header("Location: "
			. $phpgw->link('/addressbook/index.php',"cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
		$phpgw->common->phpgw_exit();
	}

 	$extrafields = array('address2' => 'address2');
	$qfields = $this->stock_contact_fields + $extrafields;

	$fieldlist = addressbook_read_entry($ab_id,$qfields);
	$fields = $fieldlist[0];

	$email        = $fields['email'];
	$emailtype    = $fields['email_type'];
	if (!$emailtype)
	{
		$fields['email_type'] = 'INTERNET';
	}
	$hemail       = $fields['email_home'];
	$hemailtype   = $fields['email_home_type'];
	if (!$hemailtype)
	{
		$fields['email_home_type'] = 'INTERNET';
	}
	$firstname    = $fields['n_given'];
	$lastname     = $fields['n_family'];

	if(!$nolname && !$nofname)
	{
		/* First name and last must be in the vcard. */
		if($lastname == '')
		{
			/* Run away here. */
			Header('Location: ' . $phpgw->link('/addressbook/vcardout.php',
				"nolname=1&ab_id=$ab_id&start=$start&order=$order&filter=$filter&query=$query&sort=$sort&cat_id=$cat_id"));
		}
		if($firstname == '')
		{
			Header('Location: ' . $phpgw->link('/addressbook/vcardout.php',
				"nofname=1&ab_id=$ab_id&start=$start&order=$order&filter=$filter&query=$query&sort=$sort&cat_id=$cat_id"));
		}

		if ($email)
		{
			$fn =  explode('@',$email);
			$filename = sprintf("%s.vcf", $fn[0]);
		}
		elseif ($hemail)
		{
			$fn =  explode('@',$hemail);
			$filename = sprintf("%s.vcf", $fn[0]);
		}
		else
		{
			$fn = strtolower($firstname);
			$filename = sprintf("%s.vcf", $fn);
		}

		// create vcard object
		$vcard = CreateObject('phpgwapi.vcard');
		// set translation variable
		$myexport = $vcard->export;
		// check that each $fields exists in the export array and
		// set a new array to equal the translation and original value
		while( list($name,$value) = each($fields) )
		{
			if ($myexport[$name] && ($value != "") )
			{
				//echo '<br>'.$name."=".$fields[$name]."\n";
				$buffer[$myexport[$name]] = $value;
			}
		}

		// create a vcard from this translated array
	    $entry = $vcard->out($buffer);
		// print it using browser class for headers
		// filename, mimetype, no length, default nocache True
		$phpgw->browser->content_header($filename,'text/x-vcard');
		echo $entry;
		$phpgw->common->exit;
	} /* !nolname && !nofname */

	if($nofname)
	{
		echo '<br><br><center>';
		echo lang("This person's first name was not in the address book.") .'<br>';
		echo lang('Vcards require a first name entry.') . '<br><br>';
		echo '<a href="' . $phpgw->link('/addressbook/index.php',
			"order=$order&start=$start&filter=$filter&query=$query&sort=$sort&cat_id=$cat_id") . '">' . lang('OK') . '</a>';
		echo '</center>';
	}

	if($nolname)
	{
		echo '<br><br><center>';
		echo lang("This person's last name was not in the address book.") . '<br>';
		echo lang('Vcards require a last name entry.') . '<br><br>';
		echo '<a href="' . $phpgw->link('/addressbook/index.php',
			"order=$order&start=$start&filter=$filter&query=$query&sort=$sort&cat_id=$cat_id") . '">' . lang('OK') . '</a>';
		echo '</center>';
	}

	if($nolname || $nofname)
	{
		$phpgw->common->phpgw_footer();
	}
?>
