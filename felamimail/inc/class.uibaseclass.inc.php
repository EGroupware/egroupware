<?php
	/***************************************************************************\
	* eGroupWare - FeLaMiMail                                                   *
	* http://www.linux-at-work.de                                               *
	* http://www.phpgw.de                                                       *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id$ */

	class uibaseclass
	{
		var $public_functions = array(
			'accounts_popup'	=>	True,
			'create_html'		=>	True
		);
		
		function accounts_popup($_appName)
		{
			$GLOBALS['egw']->accounts->accounts_popup($_appName);
		}
		
		function create_html()
		{
			if(!isset($GLOBALS['egw_info']['server']['deny_user_grants_access']) || !$GLOBALS['egw_info']['server']['deny_user_grants_access'])
			{
				$accounts = $GLOBALS['egw']->acl->get_ids_for_location('run',1,'calendar');
				$users = Array();
#				$this->build_part_list($users,$accounts,$event['owner']);

				$str = '';
				@asort($users);
				@reset($users);

				switch($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'])
				{
					case 'popup':
						while (is_array($event['participants']) && list($id) = each($event['participants']))
						{
							if($id != intval($event['owner']))
							{
								$str .= '<option value="' . $id.$event['participants'][$id] . '"'.($event['participants'][$id]?' selected':'').'>('.$GLOBALS['egw']->accounts->get_type($id)
										.') ' . $GLOBALS['egw']->common->grab_owner_name($id) . '</option>' . "\n"; 
							}
						}
						$var[] = array
						(
							'field'	=> '<input type="button" value="' . lang('Participants') . '" onClick="accounts_popup();">' . "\n"
									. '<input type="hidden" name="accountid" value="' . $accountid . '">',
							'data'	=> "\n".'   <select name="participants[]" multiple size="7">' . "\n" . $str . '</select>'
						);
						break;
					default:
						foreach($users as $id => $user_array)
						{
							if($id != intval($event['owner']))
							{
								$str .= '    <option value="' . $id.$event['participants'][$id] . '"'.($event['participants'][$id]?' selected':'').'>('.$user_array['type'].') '.$user_array['name'].'</option>'."\n";
							}
						}
						$var[] = array
						(
							'field'	=> lang('Participants'),
							'data'	=> "\n".'   <select name="participants[]" multiple size="7">'."\n".$str.'   </select>'
						);
						break;
				}
			}
			
		}
	}
?>
