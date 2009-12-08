<?php
	/***************************************************************************\
	* eGroupWare - EMailAdmin                                                   *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@egrouware.org]                      *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; version 2 of the License.                       *
	\***************************************************************************/
	/* $Id: class.bopreferences.inc.php,v 1.26 2005/11/28 18:00:18 lkneschke Exp $ */

	class ea_identity
	{
		// email address of the user
		var $emailAddress;
		
		// real name of the user
		var $realName;
		
		// name of the organization
		var $organization;
		
		// the default identity
		var $default = true;
	}
?>