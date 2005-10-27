<?php
	/**************************************************************************\
	* eGroupWare SiteMgr - Web Content Management                              *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */
require_once (EGW_INCLUDE_ROOT.'/etemplate/inc/class.sitemgr_module.inc.php');

class module_resources extends sitemgr_module
{
	function module_resources()
	{
		$this->arguments = array();
		$this->properties = array();
		$this->title = lang('Resources');
		$this->description = lang('This module displays the resources app');
		$this->etemplate_method = 'resources.ui_resources.index';
	}
}