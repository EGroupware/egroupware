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

	/*
	  Set a global flag to indicate this file was found by admin/config.php.
	  config.php will unset it after parsing the form values.
	*/
	$GLOBALS['phpgw_info']['server']['found_validation_hook'] = True;

	/* Check a specific setting.  Name must match the setting. */
	function ldap_contact_context($value='')
	{
		if($value == $GLOBALS['phpgw_info']['server']['ldap_context'])
		{
			$GLOBALS['config_error'] = 'Contact context for ldap must be different from the context used for accounts';
		}
		elseif($value == $GLOBALS['phpgw_info']['server']['ldap_group_context'])
		{
			$GLOBALS['config_error'] = 'Contact context for ldap must be different from the context used for groups';
		}
		else
		{
			$GLOBALS['config_error'] = '';
		}
	}

	/* Check all settings to validate input.  Name must be 'final_validation' */
	function final_validation($value='')
	{
		if($value['contact_repository'] == 'ldap' && !$value['ldap_contact_dn'])
		{
			$GLOBALS['config_error'] = 'Contact dn must be set';
		}
		elseif($value['contact_repository'] == 'ldap' && !$value['ldap_contact_context'])
		{
			$GLOBALS['config_error'] = 'Contact context must be set';
		}
		else
		{
			$GLOBALS['config_error'] = '';
		}
	}
?>
