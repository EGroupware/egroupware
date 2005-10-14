<?php
/**************************************************************************\
* eGroupWare                                                               *
* http://www.egroupware.org                                                *
* Based on the file written by Joseph Engo <jengo@phpgroupware.org>        *
* Based on the file modified by Greg Haygood <shrykedude@bellsouth.net>    *
* Original file: home.php by phpgroupware                                  *
* The file written by Edo van Bruggen <edovanbruggen@raketnet.nl>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/



/*
** Provides the status of the eGroupware installation.
*/
class updates {

	/*
	** @return boolean: are there updates or not
	*/
	function hasUpdates() 
	{
		if(count($this->showUpdates()) > 0) {
			return false;
		}
		else 
		{
			return false;
		}
	
	
	}
	
	/*
	** @return array with string-message about status of eGroupware and status of all application
	*/	
	function showUpdates() {
		$updates = array();
		
		if ((isset($GLOBALS['egw_info']['user']['apps']['admin']) &&
		$GLOBALS['egw_info']['user']['apps']['admin']) &&
		(isset($GLOBALS['egw_info']['server']['checkfornewversion']) &&
		$GLOBALS['egw_info']['server']['checkfornewversion']))
		{
			
			$GLOBALS['egw']->network =& CreateObject('phpgwapi.network');
			
			$GLOBALS['egw']->network->set_addcrlf(False);
			$lines = $GLOBALS['egw']->network->gethttpsocketfile('http://www.egroupware.org/currentversion');
			for($i=0; $i<count($lines); $i++)
			{
				if(strstr($lines[$i],'currentversion'))
				{
					$line_found = explode(':',chop($lines[$i]));
				}
			}
			
			if($GLOBALS['egw']->common->cmp_version_long($GLOBALS['egw_info']['server']['versions']['phpgwapi'],$line_found[1]))
			{
				$updates['egroupware'] = '<p>There is a new version of eGroupWare available. <a href="'
					. 'http://www.egroupware.org">http://www.egroupware.org</a></p>';
			}

			$_found = False;
			$GLOBALS['egw']->db->query("select app_name,app_version from phpgw_applications",__LINE__,__FILE__);
			while($GLOBALS['egw']->db->next_record())
			{
				$_db_version  = $GLOBALS['egw']->db->f('app_version');
				$_app_name    = $GLOBALS['egw']->db->f('app_name');
				$_app_dir = $GLOBALS['egw']->common->get_app_dir($_app_name);
				$_versionfile = $_app_dir . '/setup/setup.inc.php';
				if($_app_dir && file_exists($_versionfile))
				{
					include($_versionfile);
					$_file_version = $setup_info[$_app_name]['version'];
					$_app_title    = $GLOBALS['egw_info']['apps'][$_app_name]['title'];
					unset($setup_info);

					if($GLOBALS['egw']->common->cmp_version_long($_db_version,$_file_version))
					{
						$_found = True;
						$_app_string .= '<br>' . $_app_title;
					}
					unset($_file_version);
					unset($_app_title);
				}
				unset($_db_version);
				unset($_versionfile);
			}
			if($_found)
			{
				$updates['apps'] = lang('The following applications require upgrades') . ':' . "\n";
				$updates['apps'] .= $_app_string . "\n";
				$updates['apps'] .= '<br><a href="setup/" target="_blank">' . lang('Please run setup to become current') . '.' . "</a>\n";
				unset($_app_string);
			}
		}
		return $updates;	
	} 
	
}

?>
