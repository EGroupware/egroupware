<?php
	/***************************************************************************\
	* EGroupWare - LDAPManager		                                            *
	* http://www.egroupware.org                                                 *
	* Written by : Andreas Krause (ak703@users.sourceforge.net					*
	* based on EmailAdmin by Lars Kneschke [lkneschke@egroupware.org]        	*
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/

	class uildap_mgr
	{

		var $public_functions = array
		(
			'editUserData'	=> True,
			'saveUserData'	=> True
		);

		function uildap_mgr()
		{
			$this->t			= CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$this->boldapmgr		= CreateObject('admin.boldap_mgr');
		}
	
		function display_app_header()
		{
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();
			
		}

		function editUserData($_useCache='0')
		{
			global $phpgw, $phpgw_info, $HTTP_GET_VARS;
			
			$accountID = $HTTP_GET_VARS['account_id'];			
			$GLOBALS['account_id'] = $accountID;

			$this->display_app_header();

			$this->translate();

			$this->t->set_file(array("editUserData" => "account_form_ldapdata.tpl"));
			$this->t->set_block('editUserData','form','form');
			$this->t->set_block('editUserData','link_row','link_row');
			$this->t->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);
			$this->t->set_var("tr_color1",$phpgw_info["theme"]["row_on"]);
			$this->t->set_var("tr_color2",$phpgw_info["theme"]["row_off"]);
			
			$this->t->set_var("lang_email_config",lang("edit email settings"));
			$this->t->set_var("lang_emailAddress",lang("email address"));
			$this->t->set_var("lang_emailaccount_active",lang("email account active"));
			$this->t->set_var("lang_mailAlternateAddress",lang("alternate email address"));
			$this->t->set_var("lang_mailForwardingAddress",lang("forward email's to"));
			$this->t->set_var("lang_forward_also_to",lang("forward also to"));
			$this->t->set_var("lang_button",lang("save"));
			$this->t->set_var("lang_deliver_extern",lang("deliver extern"));
			$this->t->set_var("lang_edit_email_settings",lang("edit email settings"));
			$this->t->set_var("lang_ready",lang("Done"));
			$this->t->set_var("link_back",$phpgw->link('/admin/accounts.php'));
			$this->t->set_var("info_icon",PHPGW_IMAGES_DIR.'/info.gif');
			
						
			$linkData = array
			(
				'menuaction'	=> 'admin.uildap_mgr.saveUserData',
				'account_id'	=> $accountID
			);
			$this->t->set_var("form_action", $phpgw->link('/index.php',$linkData));
			
			// only when we show a existing user
			if($userData = $this->boldapmgr->getUserData($accountID, $_useCache))
			{
				echo "<br><br><br>";
				if ($userData['mailAlternateAddress'] != '')
				{
					$options_mailAlternateAddress = "<select size=\"6\" name=\"mailAlternateAddress\">\n";
					for ($i=0;$i < count($userData['mailAlternateAddress']); $i++)
					{
						$options_mailAlternateAddress .= "<option value=\"".$i."\">".
							$userData['mailAlternateAddress'][$i].
							"</option>\n";
					}
					$options_mailAlternateAddress .= "</select>\n";
				}
				else
				{
					$options_mailAlternateAddress = lang('no alternate email address');
				}
			
				$this->t->set_var("mail",$userData["mail"]);
				//$this->t->set_var("mailAlternateAddress",''); could be deleted?

				if ($userData["mailForwardingAddress"] == "") 
				{
					$this->t->set_var("mailForwardingAddress",$userData["mail"]);
				}
				else
				{
					$this->t->set_var("mailForwardingAddress",$userData["mailForwardingAddress"]);
				}

				$this->t->set_var("options_mailAlternateAddress",$options_mailAlternateAddress);
				
				$this->t->set_var("uid",rawurlencode($_accountData["dn"]));
				if ($userData["accountStatus"] == "active")
					$this->t->set_var("account_checked","checked");
				if ($userData["deliveryMode"] == "forwardOnly")
					$this->t->set_var("forwardOnly_checked","checked");
				if ($_accountData["deliverExtern"] == "active")
					$this->t->set_var("deliver_checked","checked");
			}
			else
			{
				$this->t->set_var("mail",'');
				$this->t->set_var("mailAlternateAddress",'');
				$this->t->set_var("mailForwardingAddress",'');
				$this->t->set_var("options_mailAlternateAddress",lang('no alternate email address'));
				$this->t->set_var("account_checked",'');
				$this->t->set_var("forwardOnly_checked",'');
			}
		
			// create the menu on the left, if needed		
			$menuClass = CreateObject('admin.uimenuclass');
			$this->t->set_var('rows',$menuClass->createHTMLCode('edit_user'));

			$this->t->pparse("out","form");

		}
		
		function saveUserData()
		{
			global $HTTP_POST_VARS, $HTTP_GET_VARS;
			
			if($HTTP_POST_VARS["accountStatus"] == "on")
			{
				$accountStatus = "active";
			}
			if($HTTP_POST_VARS["forwardOnly"] == "on")
			{
				$deliveryMode = "forwardOnly";
			}

			$formData = array
			(
				'mail'							=> $HTTP_POST_VARS["mail"],
				'mailAlternateAddress'			=> $HTTP_POST_VARS["mailAlternateAddress"],
				'mailForwardingAddress'			=> $HTTP_POST_VARS["mailForwardingAddress"],
				'add_mailAlternateAddress'		=> $HTTP_POST_VARS["mailAlternateAddressInput"],
				'remove_mailAlternateAddress'	=> $HTTP_POST_VARS["mailAlternateAddress"],
				'accountStatus'					=> $accountStatus,
				'deliveryMode'					=> $deliveryMode
			);
			
			//echo "<br><br>DebugArray in uiuserdata";
			// echo _debug_array($formData);
			
			if($HTTP_POST_VARS["add_mailAlternateAddress"]) $bo_action='add_mailAlternateAddress';
			if($HTTP_POST_VARS["remove_mailAlternateAddress"]) $bo_action='remove_mailAlternateAddress';
			if($HTTP_POST_VARS["save"]) $bo_action='save';

			if (!$HTTP_POST_VARS["mail"]== "")	//attribute 'mail'is not allowed to be empty
			{
// error generator necessary!!
				$this->boldapmgr->saveUserData($_GET['account_id'], $formData, $bo_action);
			}
			if ($bo_action == 'save')
			{
				// read date fresh from ldap storage
				$this->editUserData();
			}
			else
			{
				// use cached data
				$this->editUserData('1');
			}
		}
		
		function translate()
		{
			global $phpgw_info;			

			$this->t->set_var('th_bg',$phpgw_info['theme']['th_bg']);

			$this->t->set_var('lang_add',lang('add'));
			$this->t->set_var('lang_done',lang('Done'));
			$this->t->set_var('lang_remove',lang('remove'));
			$this->t->set_var('lang_remove',lang('remove'));
			$this->t->set_var('lang_advanced_options',lang('advanced options'));
			$this->t->set_var('lang_qmaildotmode',lang('qmaildotmode'));
			$this->t->set_var('lang_default',lang('default'));
			$this->t->set_var('lang_quota_settings',lang('quota settings'));
			$this->t->set_var('lang_qoutainmbyte',lang('qouta size in MByte'));
			$this->t->set_var('lang_inmbyte',lang('in MByte'));
			$this->t->set_var('lang_0forunlimited',lang('leave empty for no quota'));
			$this->t->set_var('lang_forward_only',lang('forward only'));
			$this->t->set_var('lang_mailAliases',lang('Aliases'));
			$this->t->set_var('lang_info_mailAliases',lang('Attribute mailAlternateAddress explained'));
			$this->t->set_var('lang_masterEmailAddress',lang('Main Email-Address'));
			$this->t->set_var('lang_info_masterEmailAddress',lang('Attribute mail explained'));
			$this->t->set_var('lang_RouteMailsTo',lang('Route all Mails to'));
			$this->t->set_var('lang_info_RouteMailsTo',lang('Attribute mailForwardingAddress explained'));
			$this->t->set_var('lang_info_AccountActive',lang('Attribute accountstatus explained'));
			$this->t->set_var('lang_info_UsageHints',lang('Explanation of LDAPMAN'));
		}
	}
?>
