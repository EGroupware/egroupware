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
			$this->cats			= CreateObject('phpgwapi.categories');
			$this->nextmatchs		= CreateObject('phpgwapi.nextmatchs');
			$this->t			= CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			#$this->grants			= $phpgw->acl->get_grants('notes');
			#$this->grants[$this->account]	= PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE;
			$this->boemailadmin		= CreateObject('emailadmin.bo');
		}
		
		function addProfile()
		{
			$this->display_app_header();
			
			$this->t->set_file(array("body" => "editprofile.tpl"));
			$this->t->set_block('body','main');
			
			$this->translate();
			
			#$this->t->set_var('profile_name',$profileList[0]['description']);
			$this->t->set_var('smtpActiveTab','1');
			$this->t->set_var('imapActiveTab','1');
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.ui.saveProfile'
			);
			$this->t->set_var('action_url',$GLOBALS['phpgw']->link('/index.php',$linkData));
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.ui.listProfiles'
			);
			$this->t->set_var('back_url',$GLOBALS['phpgw']->link('/index.php',$linkData));

			foreach($this->boemailadmin->getSMTPServerTypes() as $key => $value)
			{
				$this->t->set_var("lang_smtp_option_$key",$value);
			};
						
			foreach($this->boemailadmin->getIMAPServerTypes() as $key => $value)
			{
				$this->t->set_var("lang_imap_option_$key",$value['description']);
			};
						
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
			if(!@is_object($GLOBALS['phpgw']->js))
			{
				$GLOBALS['phpgw']->js = CreateObject('phpgwapi.javascript');
			}
			$GLOBALS['phpgw']->js->validate_file('tabs','tabs');
			switch($_GET['menuaction'])
			{
				case 'emailadmin.ui.addProfile':
				case 'emailadmin.ui.editProfile':
					$GLOBALS['phpgw']->js->validate_file('jscode','editProfile','emailadmin');
					$GLOBALS['phpgw']->js->set_onload('javascript:initAll();');
					#$GLOBALS['phpgw']->js->set_onload('smtp.init();');

					break;
			}
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();
		}

		function editProfile($_profileID='')
		{
			
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
			
			foreach($profileData as $key => $value)
			{
				#print "$key $value<br>";
				switch($key)
				{
					case 'imapEnableCyrusAdmin':
					case 'imapEnableSieve':
					case 'imapTLSAuthentication':
					case 'imapTLSEncryption':
					case 'smtpAuth':
					case 'smtpLDAPUseDefault':
					case 'userDefinedAccounts':
					case 'imapoldcclient':
						if($value == 'yes')
							$this->t->set_var('selected_'.$key,'checked="1"');
						break;
					case 'imapType':
					case 'smtpType':
					case 'imapLoginType':
						$this->t->set_var('selected_'.$key.'_'.$value,'selected="1"');
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
			$this->t->set_var('action_url',$GLOBALS['phpgw']->link('/index.php',$linkData));
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.ui.listProfiles'
			);
			$this->t->set_var('back_url',$GLOBALS['phpgw']->link('/index.php',$linkData));

			foreach($this->boemailadmin->getSMTPServerTypes() as $key => $value)
			{
				$this->t->set_var("lang_smtp_option_$key",$value);
			};
						
			foreach($this->boemailadmin->getIMAPServerTypes() as $key => $value)
			{
				$this->t->set_var("lang_imap_option_$key",$value['description']);
			};
						
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
					$imapServerLink = '<a href="'.$GLOBALS['phpgw']->link('/index.php',$linkData).'">'.$profileList[$i]['imapServer'].'</a>';
					
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '1',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$descriptionLink = '<a href="'.$GLOBALS['phpgw']->link('/index.php',$linkData).'">'.$profileList[$i]['description'].'</a>';
					
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.ui.editProfile',
						'nocache'	=> '1',
						'tabpage'	=> '2',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$smtpServerLink = '<a href="'.$GLOBALS['phpgw']->link('/index.php',$linkData).'">'.$profileList[$i]['smtpServer'].'</a>';
					
					$linkData = array
					(
						'menuaction'	=> 'emailadmin.ui.deleteProfile',
						'profileid'	=> $profileList[$i]['profileID']
					);
					$deleteLink = '<a href="'.$GLOBALS['phpgw']->link('/index.php',$linkData).
						      '" onClick="return confirm(\''.lang('Do you really want to delete this Profile').'?\')">'.
						      lang('delete').'</a>';
					
					$data[] = array(
						$descriptionLink,
						$smtpServerLink,
						$imapServerLink,
						$deleteLink
						
					);
				}
			}

			// create the array containing the table header 
			$rows = array(
				lang('description'),
				lang('smtp server name'),
				lang('imap/pop3 server name'),
				lang('delete')
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
			$this->t->set_var('add_link',$GLOBALS['phpgw']->link('/index.php',$linkData));

			$this->t->parse("out","main");
			
			print $this->t->get('out','main');
			
		}

		function nextMatchTable($_rows, $_data, $_description, $_start, $_total, $_menuAction)
		{
			$template = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$template->set_file(array("body" => "nextMatch.tpl"));
			$template->set_block('body','row_list','rowList');
			$template->set_block('body','header_row','headerRow');
		
			$var = Array(
				'th_bg'			=> $GLOBALS['phpgw_info']['theme']['th_bg'],
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
				foreach($_data as $value)
				{
					$data = '';
					foreach($value as $rowData)
					{
						$data .= "<td align='center'>$rowData</td>";
					}
					$template->set_var('row_data', $data);
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
			if(is_int(intval($_GET['profileID'])) && !empty($_GET['profileID']))
			{
				$globalSettings['profileID'] = intval($_GET['profileID']);
			}
			$globalSettings['description'] = $_POST['globalsettings']['description'];
			$globalSettings['defaultDomain'] = $_POST['globalsettings']['defaultDomain'];
			$globalSettings['organisationName'] = $_POST['globalsettings']['organisationName'];
			$globalSettings['userDefinedAccounts'] = $_POST['globalsettings']['userDefinedAccounts'];
			
			
			// get the settings for the smtp server
			$smtpType = $_POST['smtpsettings']['smtpType'];
			foreach($this->boemailadmin->getFieldNames($smtpType,'smtp') as $key)
			{
				$smtpSettings[$key] = $_POST['smtpsettings'][$smtpType][$key];
			}
			$smtpSettings['smtpType'] = $smtpType;
			
			#_debug_array($smtpSettings);
			
			// get the settings for the imap/pop3 server
			$imapType = $_POST['imapsettings']['imapType'];
			foreach($this->boemailadmin->getFieldNames($imapType,'imap') as $key)
			{
				$imapSettings[$key] = $_POST['imapsettings'][$imapType][$key];
			}
			$imapSettings['imapType'] = $imapType;
			
			#_debug_array($imapSettings);
			
			$this->boemailadmin->saveProfile($globalSettings, $smtpSettings, $imapSettings);
			#if ($HTTP_POST_VARS['bo_action'] == 'save_ldap' || $HTTP_GET_VARS['bo_action'] == 'save_ldap')
			#{
				$this->listProfiles();
			#}
			#else
			#{
			#	$this->editServer($HTTP_GET_VARS["serverid"],$HTTP_GET_VARS["pagenumber"]);
			#}
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
			$this->t->set_var('lang_admin_password',lang('admin passwort'));
			$this->t->set_var('lang_imap_server_logintyp',lang('imap server logintyp'));
			$this->t->set_var('lang_standard',lang('standard'));
			$this->t->set_var('lang_vmailmgr',lang('Virtual MAIL ManaGeR'));
			$this->t->set_var('lang_pre_2001_c_client',lang('IMAP C-Client Version < 2001'));
			# $this->t->set_var('',lang(''));
			
		}
	}
?>
