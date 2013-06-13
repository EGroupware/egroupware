<?php
	/***************************************************************************\
	* eGroupWare - EmailAdmin                                                   *
	* http://www.linux-at-work.de                                               *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License version 2 as published  *
	* by the Free Software Foundation.                                          *
	\***************************************************************************/

	/* $Id$ */

	class ajaxemailadmin
	{
		function ajaxemailadmin()
		{
			$this->bo		= new emailadmin_bo();
		}
		
		function addACL($_accountName, $_aclData)
		{
			if(!empty($_accountName))
			{
				$acl = implode('',(array)$_aclData['acl']);
				$data = $this->bofelamimail->addACL($this->sessionDataAjax['folderName'], $_accountName, $acl);
				#$response = new xajaxResponse();
				#$response->addScript("window.close();");
				#$response->addAssign("accountName", "value", $this->sessionDataAjax['folderName'].'-'.$_accountName.'-'.$acl);
				#return $response->getXML();

			}
		}
		
		function updateACLView()
		{
			$folderACL = $this->bofelamimail->getIMAPACL($this->sessionDataAjax['folderName']);
			
			$response = new xajaxResponse();
			$response->addAssign("aclTable", "innerHTML", $this->createACLTable($folderACL));
			return $response->getXML();
		}
		
	}
?>
