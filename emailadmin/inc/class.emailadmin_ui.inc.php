<?php
	/***************************************************************************\
	* EGroupWare - EMailAdmin                                                   *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@egroupware.org]                     *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id$ */

	class emailadmin_ui
	{	
		var $public_functions = array
		(
			'addProfile'	=> True,
			'deleteProfile'	=> True,
			'editProfile'	=> True,
			'listProfiles'	=> True,
			'saveProfile'	=> True
		);
		
		var $cats;
		var $nextmatchs;
		var $t;
		var $boqmailldap;

		function __construct()
		{
			$this->nextmatchs   =& CreateObject('phpgwapi.nextmatchs');
			$this->t            =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			#$this->boemailadmin =& CreateObject('emailadmin.bo');
			$this->boemailadmin = new emailadmin_bo();
			$this->css();
		}
		
		static function getAllGroups()
		{
			$allGroups = $GLOBALS['egw']->accounts->get_list('groups');
			foreach($allGroups as $groupInfo)
			{
				$groups[$groupInfo['account_id']] = $groupInfo['account_lid'];
			}
			asort($groups);

			$allGroups = array('' => lang('any group'));
			foreach($groups as $groupID => $groupName)
			{
				$allGroups[$groupID] = $groupName;
			}
			return $allGroups;
		}

		static function getAllUsers()
		{
			$allUsers = $GLOBALS['egw']->accounts->get_list('accounts');
			foreach($allUsers as $userInfo)
			{
				$users[$userInfo['account_id']] = $userInfo['account_lid'];
			}
			asort($users);
			$allUsers = array('' => lang('any user'));
			foreach($users as $userID => $userName)
			{
				$allUsers[$userID] = $userName;
			}
			return $allUsers;
		}

		static function getAllApps()
		{
			$applications = array(
				'calendar'	=> $GLOBALS['egw_info']['apps']['calendar']['title'],
				'felamimail' 	=> $GLOBALS['egw_info']['apps']['felamimail']['title'],
			);
			asort($applications);
			return $applications = array_merge(array('' => lang('any application')),$applications);
		}

		function addProfile()
		{
			if(is_int(intval($_GET['account_id'])) && !empty($_GET['account_id']))
			{
				if ( intval($_GET['account_id']) < 0 ) {
					$groupID =  intval($_GET['account_id']);
				} else {
					$accountID = intval($_GET['account_id']);
				}
			}
			$allGroups = self:: getAllGroups();
			$allUsers = self::getAllUsers();
			$applications = self::getAllApps();	
			$this->display_app_header();
			
			$this->t->set_file(array("body" => "editprofile.tpl"));
			$this->t->set_block('body','main');
			
			$this->translate();
			
			#$this->t->set_var('profile_name',$profileList[0]['description']);
			$this->t->set_var('smtpActiveTab','1');
			$this->t->set_var('imapActiveTab','2');	// IMAP
			$this->t->set_var('application_select_box', html::select('globalsettings[ea_appname]','',$applications, true, "style='width: 250px;'"));
			$this->t->set_var('group_select_box', html::select('globalsettings[ea_group]',($groupID ? $groupID :''),$allGroups, true, "style='width: 250px;'"));
			$this->t->set_var('user_select_box', html::select('globalsettings[ea_user]',($accountID ? $accountID :''),$allUsers, true, "style='width: 250px;'"));
			$this->t->set_var('selected_ea_active','checked="1"');
			$this->t->set_var('value_description',($accountID ? $allUsers[$accountID]: ($groupID ? $allGroups[$groupID]: '')));
			if ($accountID) 
			{
				// some useful presets, if you want to create a user dedicated profile
				$this->t->set_var('selected_smtpAuth','checked="1"');
				$this->t->set_var('value_ea_smtp_auth_username',$allUsers[$accountID]);
				$this->t->set_var('selected_imapLoginType_admin','selected="selected"');
				$this->t->set_var('value_imapAuthUsername',$allUsers[$accountID]);
			}
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.emailadmin_ui.saveProfile'
			);
			$this->t->set_var('action_url',$GLOBALS['egw']->link('/index.php',$linkData));
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.emailadmin_ui.listProfiles'
			);
			$this->t->set_var('back_url',$GLOBALS['egw']->link('/index.php',$linkData));

			$this->t->set_var('smtptype',html::select(
				'smtpsettings[smtpType]',
				$profileData['smtpType'], 
				$this->boemailadmin->getSMTPServerTypes(),
				true,
				'style="width: 250px;" id="smtpselector" onchange="smtp.display(this.value);"'
			));
						
			foreach($this->boemailadmin->getIMAPServerTypes() as $key => $value) {
				$imapServerTypes[$key] = $value['description'];
			};
			$selectFrom = html::select(
				'imapsettings[imapType]', 
				'', 
				$imapServerTypes, 
				false, 
				// stupid tabs javascript assumes value=position in selectbox, here's a littel workaround ;-)
				"style='width: 250px;' id='imapselector' onchange='var v=this.value; imap.display(this.value); this.value=v; ea_setIMAPDefaults(this.value);'"
			);
			$this->t->set_var('imaptype', $selectFrom);

			$this->t->set_var('value_smtpPort', '25');
			$this->t->set_var('value_imapPort', '110');
			$this->t->set_var('value_imapSievePort', '2000');
			
			// Stationery settings
			$bostationery = new felamimail_bostationery();
			$this->t->set_var('stored_templates', html::checkbox_multiselect(
				'globalsettings[ea_stationery_active_templates]',0
				,$bostationery->get_stored_templates(),true,'',3,true,'width: 100%;'));
			$this->t->set_var(
				'link_manage_templates',
				html::a_href(
					lang('manage stationery templates'),
					'/index.php?menuaction=etemplate.editor.edit',
					array('name' => 'felamimail.stationery'),
					'target="_blank"'
				)
			);
						
			$this->t->parse("out","main");
			print $this->t->get('out','main');
		}
	
		function css()
		{
			#$appCSS =
			$GLOBALS['egw_info']['flags']['css'] .= 
			'th.activetab
			{
				color:#000000;
				background-color:#D3DCE3;
				border-top-width : 1px;
				border-top-style : solid;
				border-top-color : Black;
				border-left-width : 1px;
				border-left-style : solid;
				border-left-color : Black;
				border-right-width : 1px;
				border-right-style : solid;
				border-right-color : Black;
			}
			
			th.inactivetab
			{
				color:#000000;
				background-color:#E8F0F0;
				border-bottom-width : 1px;
				border-bottom-style : solid;
				border-bottom-color : Black;
			}
			
			.td_left { border-left : 1px solid Gray; border-top : 1px solid Gray; }
			.td_right { border-right : 1px solid Gray; border-top : 1px solid Gray; }
			
			div.activetab{ display:inline; }
			div.inactivetab{ display:none; }';
			
			#return $appCSS;
		}
		
		function deleteProfile()
		{
			$this->boemailadmin->deleteProfile($_GET['profileid']);
			$this->listProfiles();
		}
		
		function display_app_header()
		{
			$GLOBALS['egw']->js->validate_file('tabs','tabs');
			$GLOBALS['egw_info']['flags']['include_xajax'] = True;

			switch($_GET['menuaction'])
			{
				case 'emailadmin.emailadmin_ui.addProfile':
				case 'emailadmin.emailadmin_ui.editProfile':
					$GLOBALS['egw_info']['nofooter'] = true;
					$GLOBALS['egw']->js->validate_file('jscode','editProfile','emailadmin');
					$GLOBALS['egw']->js->set_onload('javascript:initAll();');
					#$GLOBALS['egw']->js->set_onload('smtp.init();');

					break;

				case 'emailadmin.emailadmin_ui.listProfiles':
					$GLOBALS['egw']->js->validate_file('jscode','listProfile','emailadmin');

					break;
			}
			$GLOBALS['egw']->common->egw_header();
			
			if($_GET['menuaction'] == 'emailadmin.emailadmin_ui.listProfiles' || $_GET['menuaction'] == 'emailadmin.emailadmin_ui.deleteProfile')
				echo parse_navbar();
		}

		function editProfile($_profileID='') {
			$allGroups = self:: getAllGroups();
			$allUsers = self::getAllUsers();
			$applications = self::getAllApps();

			if($_profileID != '')
			{
				$profileID = $_profileID;
			}
			elseif(is_int(intval($_GET['profileid'])) && !empty($_GET['profileid']))
			{
				$profileID = intval($_GET['profileid']);
			}
			else
			{
				return false;
			}

			$profileList = $this->boemailadmin->getProfileList($profileID);
			$profileData = $this->boemailadmin->getProfile($profileID);
			$this->display_app_header();
			
			$this->t->set_file(array("body" => "editprofile.tpl"));
			$this->t->set_block('body','main');
			
			$this->translate();
			
			foreach((array)$profileData as $key => $value) {
				#print "$key $value<br>";
				switch($key) {
					case 'ea_default_signature':
						// nothing to do here
						break;
					case 'ea_stationery_active_templates':
						$activeTemplates = $value;
					case 'imapTLSEncryption':
						$this->t->set_var('checked_'. $key .'_'. $value,'checked="1"');
						break;
					case 'imapTLSAuthentication':
						if(!$value || $value=='no') {
							$this->t->set_var('selected_'.$key,'checked="1"');
						}
						break;
					case 'imapEnableCyrusAdmin':
					case 'imapEnableSieve':
					case 'smtpAuth':
					case 'smtpLDAPUseDefault':
					case 'userDefinedAccounts':
					case 'userDefinedIdentities':
					case 'ea_user_defined_signatures':
					case 'ea_active':
					case 'imapoldcclient':
					case 'editforwardingaddress':
						if($value == 'yes' || $value == 1) {
							$this->t->set_var('selected_'.$key,'checked="1"');
						}
						break;
					case 'imapType':
					case 'smtpType':
					case 'imapLoginType':
						$this->t->set_var('selected_'.$key.'_'.$value,'selected="1"');
						break;
					case 'ea_appname':
						$this->t->set_var('application_select_box', html::select('globalsettings[ea_appname]',$value,$applications, true, "style='width: 250px;'"));
						break;
					case 'ea_group':
						$this->t->set_var('group_select_box', html::select('globalsettings[ea_group]',$value,$allGroups, true, "style='width: 250px;'"));
						break;
					case 'ea_user':
						$this->t->set_var('user_select_box', html::select('globalsettings[ea_user]',$value,$allUsers, true, "style='width: 250px;'"));
						break;
					case 'ea_smtp_auth_username':
						#echo "<br>value_$key,$value";
						list($username,$senderadress) = explode(';',$value,2);
						if (!empty($senderadress)) $this->t->set_var('value_smtp_senderadress',$senderadress);
						$this->t->set_var('value_'.$key,$username);
						break;
					default:
						$this->t->set_var('value_'.$key,$value);
						break;
				}
			}
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.emailadmin_ui.saveProfile',
				'profileID'	=> $profileID
			);
			$this->t->set_var('action_url',$GLOBALS['egw']->link('/index.php',$linkData));
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.emailadmin_ui.listProfiles'
			);
			$this->t->set_var('back_url',$GLOBALS['egw']->link('/index.php',$linkData));

			$this->t->set_var('smtptype',html::select(
				'smtpsettings[smtpType]',
				$profileData['smtpType'], 
				$this->boemailadmin->getSMTPServerTypes(),
				true,
				'style="width: 250px;" id="smtpselector" onchange="smtp.display(this.value);"'
			));
			foreach($this->boemailadmin->getIMAPServerTypes() as $key => $value) {
				$imapServerTypes[$key] = $value['description'];
			};
			$selectFrom = html::select(
				'imapsettings[imapType]', 
				$profileData['imapType'], 
				$imapServerTypes, 
				true, 
				// stupid tabs javascript assumes value=position in selectbox, here's a littel workaround ;-)
				"style='width: 250px;' id='imapselector' onchange='var v = this.value; imap.display(this.value); this.value=v;'"
			);
			$this->t->set_var('imaptype', $selectFrom);

			$style="width:100%; border:0px; height:150px;";
			$this->t->set_var('signature', html::fckEditorQuick(
					'globalsettings[ea_default_signature]', 'advanced',
					$profileData['ea_default_signature'], '350px')
			);
			
			// Stationery settings
			$bostationery = new felamimail_bostationery();
			$activeTemplates = is_array($activeTemplates) ? $activeTemplates : array();
			$this->t->set_var('stored_templates', html::checkbox_multiselect(
				'globalsettings[ea_stationery_active_templates]',$activeTemplates
				,$bostationery->get_stored_templates(),true,'',3,true,'width: 100%;'));
			$this->t->set_var(
				'link_manage_templates',
				html::a_href(
					lang('manage stationery templates'),
					'/index.php?menuaction=etemplate.editor.edit',
					array('name' => 'felamimail.stationery'),
					'target="_blank"'
				)
			);
			
			$this->t->parse("out","main");
			print $this->t->get('out','main');
		}
		
		function listProfiles()
		{
			$accountID = false;
			$groupID = false;
			$appName = '';
			$profileID = '';
			if(is_int(intval($_GET['account_id'])) && !empty($_GET['account_id']))
			{
				if ( intval($_GET['account_id']) < 0 ) {
					$groupID =  intval($_GET['account_id']);
				} else {
					$accountID = intval($_GET['account_id']);
				}
			}

			$this->display_app_header();

			$this->t->set_file(array("body" => "listprofiles.tpl"));
			$this->t->set_block('body','main');
			
			$this->translate();

			$profileList = $this->boemailadmin->getProfileList($profileID,$appName,$groupID,$accountID);
			
			// create the data array
			if ($profileList)
			{
				for ($i=0; $i < count($profileList); $i++)
				{
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.emailadmin_ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '3',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$imapServerLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$profileList[$i]['imapServer'].'</a>';
					
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.emailadmin_ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '1',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$descriptionLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$profileList[$i]['description'].'</a>';
					
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.emailadmin_ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '2',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$smtpServerLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$profileList[$i]['smtpServer'].'</a>';
					
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.emailadmin_ui.deleteProfile',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$deleteLink = '<a href="'.$GLOBALS['egw']->link('/index.php',$linkData).
									'" onClick="return confirm(\''.lang('Do you really want to delete this Profile').'?\')">'.
									lang('delete').'</a>';

					$application = (empty($profileList[$i]['ea_appname']) ? lang('any application') : $GLOBALS['egw_info']['apps'][$profileList[$i]['ea_appname']]['title']);
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.emailadmin_ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '1',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$applicationLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$application.'</a>';					

					$group = (empty($profileList[$i]['ea_group']) ? lang('any group') : $GLOBALS['egw']->accounts->id2name($profileList[$i]['ea_group']));
					$user = (empty($profileList[$i]['ea_user']) ? lang('any user') : $GLOBALS['egw']->accounts->id2name($profileList[$i]['ea_user']));
					$isactive = (empty($profileList[$i]['ea_active']) ? lang('inactive') : ($profileList[$i]['ea_active']>0 ? lang('active') : lang('inactive')));
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.emailadmin_ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '1',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$groupLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$group.'</a>';
					$userLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$user.'</a>';
					$activeLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$isactive.'</a>';
					$moveButtons = '<img src="'. $GLOBALS['egw']->common->image('phpgwapi', 'up') .'" onclick="moveUp(this)">&nbsp;'.
						       '<img src="'. $GLOBALS['egw']->common->image('phpgwapi', 'down') .'" onclick="moveDown(this)">';
					
					$data['profile_'.$profileList[$i]['profileID']] = array(
						$descriptionLink,
						$smtpServerLink,
						$imapServerLink,
						$applicationLink,
						$groupLink,
						$userLink,
						$activeLink,
						$deleteLink,
						$moveButtons,
						
					);
				}
				if (($accountID || !empty($groupID)) && count($profileList)==1) 
				{
					$linkData = array
					(
						'menuaction'    => 'emailadmin.emailadmin_ui.editProfile',
						'nocache'   => '1',
						'tabpage'   => '1',
						'profileid' => $profileList[0]['profileID']
					);
					print "<script type=\"text/javascript\">".'egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600);</script>';
				}
			} else {
				if ($accountID || !empty($groupID)) {
					$linkData = array
					(
						'menuaction'    => 'emailadmin.emailadmin_ui.addProfile',
						'nocache'   => '1',
						'tabpage'   => '1',
						'account_id' => ($accountID ? $accountID : $groupID)
					);
					print "<script type=\"text/javascript\">".'egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_addProfile\',700,600);</script>';

				}
			}

			// create the array containing the table header 
			$rows = array(
				lang('description'),
				lang('smtp server name'),
				lang('imap server name'),
				lang('application'),
				lang('group'),
				lang('user'),
				lang('active'),
				lang('delete'),
				lang('order'),
			);
			if ($accountID || !empty($groupID)) {
				$linkData = array
				(
					'menuaction'    => 'emailadmin.emailadmin_ui.listProfiles',
				);
				$listLink = '<a href="'.$GLOBALS['egw']->link('/index.php',$linkData).
					'" onClick="return confirm(\''.lang('Do you really want to reset the filter for the Profile listing').'?\')">'.
					lang('reset filter').'</a>';

				if ($GLOBALS['egw_info']['user']['apps']['admin']) {
					$linkData = array
					(
						'menuaction'    => 'admin.uiaccounts.list_'.($accountID ? 'users' : 'groups'),
					);
					$listLink2 = '<a href="'.$GLOBALS['egw']->link('/index.php',$linkData).'">'.($accountID ? lang('Back to Admin/Userlist'): lang('Back to Admin/Grouplist')).'</a>';
				}
			}
	
			// create the table html code
			$this->t->set_var('server_next_match',$this->nextMatchTable(
				$rows, 
				$data, 
				lang('profile list').($accountID || !empty($groupID) ? ' '.($accountID ? lang('filtered by Account') :  lang('filtered by Group')).' ['.$listLink.']'.' ['.$listLink2.']': ''), 
				$_start, 
				$_total, 
				$_menuAction)
			);
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.emailadmin_ui.addProfile'
			);
			$this->t->set_var('add_link',$GLOBALS['egw']->link('/index.php',$linkData));

			$this->t->parse("out","main");
			
			print $this->t->get('out','main');
			
		}

		function nextMatchTable($_rows, $_data, $_description, $_start, $_total, $_menuAction)
		{
			$template =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$template->set_file(array("body" => "nextMatch.tpl"));
			$template->set_block('body','row_list','rowList');
			$template->set_block('body','header_row','headerRow');
		
			$var = Array(
				'th_bg'			=> $GLOBALS['egw_info']['theme']['th_bg'],
				'left_next_matchs'	=> $this->nextmatchs->left('/index.php',$start,$total,'menuaction=emailadmin.emailadmin_ui.listServers'),
				'right_next_matchs'	=> $this->nextmatchs->right('/admin/groups.php',$start,$total,'menuaction=emailadmin.emailadmin_ui.listServers'),
				'lang_groups'		=> lang('user groups'),
				'sort_name'		=> $this->nextmatchs->show_sort_order($sort,'account_lid',$order,'/index.php',lang('name'),'menuaction=emailadmin.emailadmin_ui.listServers'),
				'description'		=> $_description,
				'header_edit'		=> lang('Edit'),
				'header_delete'		=> lang('Delete')
			);
			$template->set_var($var);
			
			$data = '';
			if(is_array($_rows))
			{
				foreach($_rows as $value)
				{
					$data .= "<td align='center'><b>$value</b></td>";
				}
				$template->set_var('header_row_data', $data);
				$template->fp('headerRow','header_row',True);
				#$template->fp('header_row','header_row',True);
			}

			if(is_array($_data))
			{
				foreach($_data as $rowID => $value)
				{
					$data = '';
					foreach($value as $rowData)
					{
						$data .= "<td align='center'>$rowData</td>";
					}
					$template->set_var('row_data', $data);
					$template->set_var('row_id', $rowID);
					$template->fp('rowList','row_list',True);
				}
			}

			return $template->fp('out','body');
			
		}

		function saveProfile()
		{
			$globalSettings	= array();
			$smtpSettings	= array();
			$imapSettings	= array();
			
			// try to get the profileID
			if(is_int(intval($_GET['profileID'])) && !empty($_GET['profileID'])) {
				$globalSettings['profileID'] = intval($_GET['profileID']);
			}

			$globalSettings['description'] = $_POST['globalsettings']['description'];
			$globalSettings['defaultDomain'] = $_POST['globalsettings']['defaultDomain'];
			$globalSettings['organisationName'] = $_POST['globalsettings']['organisationName'];
			$globalSettings['userDefinedAccounts'] = ($_POST['globalsettings']['userDefinedAccounts'] == 'yes' ? 'yes' : 'no' );
			$globalSettings['userDefinedIdentities'] = ($_POST['globalsettings']['userDefinedIdentities'] == 'yes' ? 'yes' : 'no' );
			$globalSettings['ea_active'] = ($_POST['globalsettings']['ea_active'] == 'yes' ? 1 : 0 );
			$globalSettings['ea_user_defined_signatures'] = ($_POST['globalsettings']['ea_user_defined_signatures'] == 'yes' ? 'yes' : 'no' );
			$globalSettings['ea_default_signature'] = $_POST['globalsettings']['ea_default_signature'];
			$globalSettings['ea_appname'] = ($_POST['globalsettings']['ea_appname'] == 'any' ? '' : $_POST['globalsettings']['ea_appname']);
			$globalSettings['ea_group'] = ($_POST['globalsettings']['ea_group'] == 'any' ? '' : (int)$_POST['globalsettings']['ea_group']);
			$globalSettings['ea_user'] = ($_POST['globalsettings']['ea_user'] == 'any' ? '' : (int)$_POST['globalsettings']['ea_user']);	
			$globalSettings['ea_stationery_active_templates'] = $_POST['globalsettings']['ea_stationery_active_templates'];

			// get the settings for the smtp server
			$smtpType = $_POST['smtpsettings']['smtpType'];
			foreach($this->boemailadmin->getFieldNames($smtpType,'smtp') as $key) {
				$smtpSettings[$key] = $_POST['smtpsettings'][$smtpType][$key];
			}
			// append the email to be used as sender adress(, if set)
			if (!empty($smtpSettings['ea_smtp_auth_username']) && isset($_POST['smtpsettings'][$smtpType]['smtp_senderadress']) && !empty($_POST['smtpsettings'][$smtpType]['smtp_senderadress'])) {
				$smtpSettings['ea_smtp_auth_username'] .= ";".$_POST['smtpsettings'][$smtpType]['smtp_senderadress'];
			}
			$smtpSettings['smtpType'] = $smtpType;
			
			#_debug_array($smtpSettings); exit;
			
			// get the settings for the imap/pop3 server
			$imapType = $_POST['imapsettings']['imapType'];
			foreach($this->boemailadmin->getFieldNames($imapType,'imap') as $key) {
				switch($key) {
					case 'imapTLSAuthentication':
						$imapSettings[$key] = (!isset($_POST['imapsettings'][$imapType][$key])?'yes':'no');
						break;
					default:
						$imapSettings[$key] = $_POST['imapsettings'][$imapType][$key];
						break;
				}
			}
			$imapSettings['imapType'] = $imapType;

			#_debug_array($imapSettings);
			
			$this->boemailadmin->saveProfile($globalSettings, $smtpSettings, $imapSettings);

			print "<script type=\"text/javascript\">opener.location.reload(); window.close();</script>";
			$GLOBALS['egw']->common->egw_exit();
			exit;
		}
		
		function translate()
		{
			# skeleton
			# $this->t->set_var('',lang(''));
			$this->t->set_var('lang_server_name',lang('server name'));
			$this->t->set_var('lang_server_description',lang('description'));
			$this->t->set_var('lang_edit',lang('edit'));
			$this->t->set_var('lang_save',lang('save'));
			$this->t->set_var('lang_delete',lang('delete'));
			$this->t->set_var('lang_back',lang('back'));
			$this->t->set_var('lang_remove',lang('remove'));
			$this->t->set_var('lang_ldap_server',lang('LDAP server'));
			$this->t->set_var('lang_ldap_basedn',lang('LDAP basedn'));
			$this->t->set_var('lang_ldap_server_admin',lang('admin dn'));
			$this->t->set_var('lang_ldap_server_password',lang('admin password'));
			$this->t->set_var('lang_add_profile',lang('add profile'));
			$this->t->set_var('lang_domain_name',lang('domainname'));
			$this->t->set_var('lang_SMTP_server_hostname_or_IP_address',lang('SMTP-Server hostname or IP address'));
			$this->t->set_var('lang_SMTP_server_port',lang('SMTP-Server port'));
			$this->t->set_var('lang_Use_SMTP_auth',lang('Use SMTP auth'));
			$this->t->set_var('lang_Select_type_of_SMTP_Server',lang('Select type of SMTP Server'));
			$this->t->set_var('lang_profile_name',lang('Profile Name'));
			$this->t->set_var('lang_default_domain',lang('enter your default mail domain (from: user@domain)'));
			$this->t->set_var('lang_organisation_name',lang('name of organisation'));
			$this->t->set_var('lang_user_defined_accounts',lang('users can define their own emailaccounts'));
			$this->t->set_var('lang_user_defined_identities',lang('users can define their own identities'));
			$this->t->set_var('lang_user_defined_signatures',lang('users can define their own signatures'));
			$this->t->set_var('lang_LDAP_server_hostname_or_IP_address',lang('LDAP server hostname or ip address'));
			$this->t->set_var('lang_LDAP_server_admin_dn',lang('LDAP server admin DN'));
			$this->t->set_var('lang_LDAP_server_admin_pw',lang('LDAP server admin password'));
			$this->t->set_var('lang_LDAP_server_base_dn',lang('LDAP server accounts DN'));
			$this->t->set_var('lang_use_LDAP_defaults',lang('use LDAP defaults'));
			$this->t->set_var('lang_LDAP_settings',lang('LDAP settings'));
			$this->t->set_var('lang_select_type_of_imap/pop3_server',lang('select type of IMAP server'));
			$this->t->set_var('lang_pop3_server_hostname_or_IP_address',lang('POP3 server hostname or ip address'));
			$this->t->set_var('lang_pop3_server_port',lang('POP3 server port'));
			$this->t->set_var('lang_imap_server_hostname_or_IP_address',lang('IMAP server hostname or ip address'));
			$this->t->set_var('lang_imap_server_port',lang('IMAP server port'));
			$this->t->set_var('lang_use_tls_encryption',lang('use tls encryption'));
			$this->t->set_var('lang_use_tls_authentication',lang('use tls authentication'));
			$this->t->set_var('lang_sieve_settings',lang('Sieve settings'));
			$this->t->set_var('lang_enable_sieve',lang('enable Sieve'));
			$this->t->set_var('lang_sieve_server_hostname_or_ip_address',lang('Sieve server hostname or ip address'));
			$this->t->set_var('lang_sieve_server_port',lang('Sieve server port'));
			$this->t->set_var('lang_enable_cyrus_imap_administration',lang('enable Cyrus IMAP server administration'));
			$this->t->set_var('lang_cyrus_imap_administration',lang('Cyrus IMAP server administration'));
			$this->t->set_var('lang_admin_username',lang('admin username'));
			$this->t->set_var('lang_admin_password',lang('admin password'));
			$this->t->set_var('lang_imap_server_logintyp',lang('imap server logintyp'));
			$this->t->set_var('lang_standard',lang('username (standard)'));
			$this->t->set_var('lang_vmailmgr',lang('username@domainname (Virtual MAIL ManaGeR)'));
			$this->t->set_var('lang_pre_2001_c_client',lang('IMAP C-Client Version < 2001'));
			$this->t->set_var('lang_user_can_edit_forwarding_address',lang('user can edit forwarding address'));
			$this->t->set_var('lang_can_be_used_by_application',lang('can be used by application'));
			$this->t->set_var('lang_can_be_used_by_group',lang('can be used by group'));
			$this->t->set_var('lang_smtp_auth',lang('smtp authentication'));
			$this->t->set_var('lang_sender',lang('send using this eMail-Address'));
			$this->t->set_var('lang_username',lang('username'));
			$this->t->set_var('lang_password',lang('password'));
			$this->t->set_var('lang_smtp_settings',lang('smtp settings'));
			$this->t->set_var('lang_smtp_options',lang('smtp options'));
			$this->t->set_var('lang_profile_access_rights',lang('profile access rights'));
			$this->t->set_var('lang_global_settings',lang(''));
			$this->t->set_var('lang_organisation',lang('organisation'));
			$this->t->set_var('lang_global_options',lang('global options'));
			$this->t->set_var('lang_server_settings',lang('server settings'));
			$this->t->set_var('lang_encryption_settings',lang('encryption settings'));
			$this->t->set_var('lang_no_encryption',lang('no encryption'));
			$this->t->set_var('lang_encrypted_connection',lang('encrypted connection'));
			$this->t->set_var('lang_do_not_validate_certificate',lang('do not validate certificate'));
			$this->t->set_var('lang_vacation_requires_admin',lang('Vaction messages with start- and end-date require an admin account to be set!'));
			$this->t->set_var('lang_can_be_used_by_user',lang('can be used by user'));
			$this->t->set_var('lang_profile_isactive',lang('profile is active'));
			$this->t->set_var('lang_defined_by_admin',lang('Username/Password defined by admin'));
			$this->t->set_var('lang_Use_IMAP_auth', lang('Use predefined username and password defined below'));
			$this->t->set_var('lang_stationery', lang('stationery'));
			$this->t->set_var('lang_active_templates', lang('active templates'));
			$this->t->set_var('lang_active_templates_description', lang('users can utilize these stationery templates'));
			$this->t->set_var('lang_email',lang('use Users eMail-Address (as seen in Useraccount)'));
			$this->t->set_var('',lang(''));
			# $this->t->set_var('',lang(''));
			
		}
	}
?>
