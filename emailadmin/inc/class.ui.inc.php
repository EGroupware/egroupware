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

	class ui
	{
		
		var $public_functions = array
		(
			'addProfile'	=> True,
			'css'		=> True,
			'deleteProfile'	=> True,
			'editProfile'	=> True,
			'listProfiles'	=> True,
			'saveProfile'	=> True
		);
		
		var $cats;
		var $nextmatchs;
		var $t;
		var $boqmailldap;

		function ui()
		{
			$this->nextmatchs   =& CreateObject('phpgwapi.nextmatchs');
			$this->t            =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$this->boemailadmin =& CreateObject('emailadmin.bo');
		}
		
		function addProfile()
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
			
			$applications = array(
				'calendar'	=> $GLOBALS['egw_info']['apps']['calendar']['title'],
				'felamimail' 	=> $GLOBALS['egw_info']['apps']['felamimail']['title'],
			);
			asort($applications);
			$applications = array_merge(array('' => lang('any application')),$applications);
			
			$this->display_app_header();
			
			$this->t->set_file(array("body" => "editprofile.tpl"));
			$this->t->set_block('body','main');
			
			$this->translate();
			
			#$this->t->set_var('profile_name',$profileList[0]['description']);
			$this->t->set_var('smtpActiveTab','1');
			$this->t->set_var('imapActiveTab','1');
			$this->t->set_var('application_select_box', $GLOBALS['egw']->html->select('globalsettings[ea_appname]','',$applications, true, "style='width: 250px;'"));
			$this->t->set_var('group_select_box', $GLOBALS['egw']->html->select('globalsettings[ea_group]','',$allGroups, true, "style='width: 250px;'"));
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.ui.saveProfile'
			);
			$this->t->set_var('action_url',$GLOBALS['egw']->link('/index.php',$linkData));
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.ui.listProfiles'
			);
			$this->t->set_var('back_url',$GLOBALS['egw']->link('/index.php',$linkData));

			foreach($this->boemailadmin->getSMTPServerTypes() as $key => $value)
			{
				$this->t->set_var("lang_smtp_option_$key",$value);
			};
						
			foreach($this->boemailadmin->getIMAPServerTypes() as $key => $value) {
				$imapServerTypes[$key] = $value['description'];
			};
			$selectFrom = $GLOBALS['egw']->html->select(
				'imapsettings[imapType]', 
				'', 
				$imapServerTypes, 
				false, 
				"style='width: 250px;' id='imapselector' onchange='imap.display(this.value); ea_setIMAPDefaults(this.value);'"
			);
			$this->t->set_var('imaptype', $selectFrom);

			$this->t->set_var('value_smtpPort', '25');
			$this->t->set_var('value_imapPort', '110');
			$this->t->set_var('value_imapSievePort', '2000');
						
			$this->t->parse("out","main");
			print $this->t->get('out','main');
		}
	
		function css()
		{
			$appCSS = 
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
			
			return $appCSS;
		}
		
		function deleteProfile()
		{
			$this->boemailadmin->deleteProfile($_GET['profileid']);
			$this->listProfiles();
		}
		
		function display_app_header()
		{
			if(!@is_object($GLOBALS['egw']->js))
			{
				$GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
			}
			$GLOBALS['egw']->js->validate_file('tabs','tabs');
			$GLOBALS['egw_info']['flags']['include_xajax'] = True;

			switch($_GET['menuaction'])
			{
				case 'emailadmin.ui.addProfile':
				case 'emailadmin.ui.editProfile':
					$GLOBALS['egw_info']['nofooter'] = true;
					$GLOBALS['egw']->js->validate_file('jscode','editProfile','emailadmin');
					$GLOBALS['egw']->js->set_onload('javascript:initAll();');
					#$GLOBALS['egw']->js->set_onload('smtp.init();');

					break;

				case 'emailadmin.ui.listProfiles':
					$GLOBALS['egw']->js->validate_file('jscode','listProfile','emailadmin');

					break;
			}
			$GLOBALS['egw']->common->egw_header();
			
			if($_GET['menuaction'] == 'emailadmin.ui.listProfiles' || $_GET['menuaction'] == 'emailadmin.ui.deleteProfile')
				echo parse_navbar();
		}

		function editProfile($_profileID='') {
			if(!is_object($GLOBALS['egw']->html)) {
				$GLOBALS['egw']->html =& CreateObject('phpgwapi.html');
			}
			                                                        
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
			
			$applications = array(
				'calendar'	=> $GLOBALS['egw_info']['apps']['calendar']['title'],
				'felamimail' 	=> $GLOBALS['egw_info']['apps']['felamimail']['title'],
			);
			asort($applications);
			$applications = array_merge(array('' => lang('any application')),$applications);
			
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
					case 'imapTLSEncryption':
						$this->t->set_var('checked_'. $key .'_'. $value,'checked="1"');
						break;
					case 'imapTLSAuthentication':
						if($value == '1') {
							$this->t->set_var('selected_'.$key,'checked="1"');
						}
						break;
					case 'imapEnableCyrusAdmin':
					case 'imapEnableSieve':
					case 'smtpAuth':
					case 'smtpLDAPUseDefault':
					case 'userDefinedAccounts':
					case 'imapoldcclient':
					case 'editforwardingaddress':
						if($value == 'yes') {
							$this->t->set_var('selected_'.$key,'checked="1"');
						}
						break;
					case 'imapType':
					case 'smtpType':
					case 'imapLoginType':
						$this->t->set_var('selected_'.$key.'_'.$value,'selected="1"');
						break;
					case 'ea_appname':
						$this->t->set_var('application_select_box', $GLOBALS['egw']->html->select('globalsettings[ea_appname]',$value,$applications, true, "style='width: 250px;'"));
						break;
					case 'ea_group':
						$this->t->set_var('group_select_box', $GLOBALS['egw']->html->select('globalsettings[ea_group]',$value,$allGroups, true, "style='width: 250px;'"));
						break;
					default:
						$this->t->set_var('value_'.$key,$value);
						break;
				}
			}
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.ui.saveProfile',
				'profileID'	=> $profileID
			);
			$this->t->set_var('action_url',$GLOBALS['egw']->link('/index.php',$linkData));
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.ui.listProfiles'
			);
			$this->t->set_var('back_url',$GLOBALS['egw']->link('/index.php',$linkData));

			$this->t->set_var('smtptype',$GLOBALS['egw']->html->select(
				'smtpsettings[smtpType]',
				$profileData['smtpType'], 
				$this->boemailadmin->getSMTPServerTypes(),
				true,
				'style="width: 250px;" id="smtpselector" onchange="smtp.display(this.value);"'
			));
			foreach($this->boemailadmin->getIMAPServerTypes() as $key => $value) {
				$imapServerTypes[$key] = $value['description'];
			};
			$selectFrom = $GLOBALS['egw']->html->select(
				'imapsettings[imapType]', 
				$profileData['imapType'], 
				$imapServerTypes, 
				true, 
				"style='width: 250px;' id='imapselector' onchange='imap.display(this.value);'"
			);
			$this->t->set_var('imaptype', $selectFrom);
						
			$this->t->parse("out","main");
			print $this->t->get('out','main');
		}
		
		function listProfiles()
		{
			$this->display_app_header();

			$this->t->set_file(array("body" => "listprofiles.tpl"));
			$this->t->set_block('body','main');
			
			$this->translate();

			$profileList = $this->boemailadmin->getProfileList();
			
			// create the data array
			if ($profileList)
			{
				for ($i=0; $i < count($profileList); $i++)
				{
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '3',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$imapServerLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$profileList[$i]['imapServer'].'</a>';
					
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '1',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$descriptionLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$profileList[$i]['description'].'</a>';
					
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '2',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$smtpServerLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$profileList[$i]['smtpServer'].'</a>';
					
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.ui.deleteProfile',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$deleteLink = '<a href="'.$GLOBALS['egw']->link('/index.php',$linkData).
									'" onClick="return confirm(\''.lang('Do you really want to delete this Profile').'?\')">'.
									lang('delete').'</a>';

					$application = (empty($profileList[$i]['ea_appname']) ? lang('any application') : $GLOBALS['egw_info']['apps'][$profileList[$i]['ea_appname']]['title']);
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '1',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$applicationLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$application.'</a>';					

					$group = (empty($profileList[$i]['ea_group']) ? lang('any group') : $GLOBALS['egw']->accounts->id2name($profileList[$i]['ea_group']));
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '1',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$groupLink = '<a href="#" onclick="egw_openWindowCentered2(\''.$GLOBALS['egw']->link('/index.php',$linkData).'\',\'ea_editProfile\',700,600); return false;">'.$group.'</a>';

					$moveButtons = '<img src="'. $GLOBALS['egw']->common->image('phpgwapi', 'up') .'" onclick="moveUp(this)">&nbsp;'.
						       '<img src="'. $GLOBALS['egw']->common->image('phpgwapi', 'down') .'" onclick="moveDown(this)">';
					
					$data['profile_'.$profileList[$i]['profileID']] = array(
						$descriptionLink,
						$smtpServerLink,
						$imapServerLink,
						$applicationLink,
						$groupLink,
						$deleteLink,
						$moveButtons,
						
					);
				}
			}

			// create the array containing the table header 
			$rows = array(
				lang('description'),
				lang('smtp server name'),
				lang('imap/pop3 server name'),
				lang('application'),
				lang('group'),
				lang('delete'),
				lang('order'),
			);
				
			// create the table html code
			$this->t->set_var('server_next_match',$this->nextMatchTable(
				$rows, 
				$data, 
				lang('profile list'), 
				$_start, 
				$_total, 
				$_menuAction)
			);
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.ui.addProfile'
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
				'left_next_matchs'	=> $this->nextmatchs->left('/index.php',$start,$total,'menuaction=emailadmin.ui.listServers'),
				'right_next_matchs'	=> $this->nextmatchs->right('/admin/groups.php',$start,$total,'menuaction=emailadmin.ui.listServers'),
				'lang_groups'		=> lang('user groups'),
				'sort_name'		=> $this->nextmatchs->show_sort_order($sort,'account_lid',$order,'/index.php',lang('name'),'menuaction=emailadmin.ui.listServers'),
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
			$globalSettings['userDefinedAccounts'] = $_POST['globalsettings']['userDefinedAccounts'];
			$globalSettings['ea_appname'] = ($_POST['globalsettings']['ea_appname'] == 'any' ? '' : $_POST['globalsettings']['ea_appname']);
			$globalSettings['ea_group'] = ($_POST['globalsettings']['ea_group'] == 'any' ? '' : (int)$_POST['globalsettings']['ea_group']);
			
			
			// get the settings for the smtp server
			$smtpType = $_POST['smtpsettings']['smtpType'];
			foreach($this->boemailadmin->getFieldNames($smtpType,'smtp') as $key) {
				$smtpSettings[$key] = $_POST['smtpsettings'][$smtpType][$key];
			}
			$smtpSettings['smtpType'] = $smtpType;
			
			#_debug_array($smtpSettings); exit;
			
			// get the settings for the imap/pop3 server
			$imapType = $_POST['imapsettings']['imapType'];
			foreach($this->boemailadmin->getFieldNames($imapType,'imap') as $key) {
				switch($key) {
					case 'imapTLSAuthentication':
						$imapSettings[$key] = $_POST['imapsettings'][$imapType][$key] != 'dontvalidate';
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
			$this->t->set_var('lang_LDAP_server_hostname_or_IP_address',lang('LDAP server hostname or ip address'));
			$this->t->set_var('lang_LDAP_server_admin_dn',lang('LDAP server admin DN'));
			$this->t->set_var('lang_LDAP_server_admin_pw',lang('LDAP server admin password'));
			$this->t->set_var('lang_LDAP_server_base_dn',lang('LDAP server accounts DN'));
			$this->t->set_var('lang_use_LDAP_defaults',lang('use LDAP defaults'));
			$this->t->set_var('lang_LDAP_settings',lang('LDAP settings'));
			$this->t->set_var('lang_select_type_of_imap/pop3_server',lang('select type of IMAP/POP3 server'));
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
			$this->t->set_var('',lang(''));
			# $this->t->set_var('',lang(''));
			
		}
	}
?>
