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

	/* $Id: class.ajaxfelamimail.inc.php 21848 2006-06-15 21:50:59Z ralfbecker $ */

	class ajax_contacts {
		function ajax_contacts() {
			$GLOBALS['egw']->session->commit_session();
			$this->charset	= $GLOBALS['egw']->translation->charset();
		}
		
		function searchAddress($_searchString) {
			if ($GLOBALS['egw_info']['user']['apps']['addressbook']) {
				if (method_exists($GLOBALS['egw']->contacts,'search')) {
					// 1.3+
					$contacts = $GLOBALS['egw']->contacts->search(array(
						'n_fn'       => $_searchString,
						'email'      => $_searchString,
						'email_home' => $_searchString,
					),array('n_fn','email','email_home'),'n_fn','','%',false,'OR',array(0,20));

					$showAccounts = true;
					if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts']) $showAccounts=false;
					// additionally search the accounts, if the contact storage is not the account storage
					if ($showAccounts &&
						$GLOBALS['egw_info']['server']['account_repository'] == 'ldap' &&
						$GLOBALS['egw_info']['server']['contact_repository'] == 'sql')
					{
						$accounts = $GLOBALS['egw']->contacts->search(array(
							'n_fn'       => $_searchString,
							'email'      => $_searchString,
							'email_home' => $_searchString,
						),array('n_fn','email','email_home'),'n_fn','','%',false,'OR',array(0,20),array('owner' => 0));
						
						if ($contacts && $accounts)
						{
							$contacts = array_merge($contacts,$accounts);
							usort($contacts,create_function('$a,$b','return strcasecmp($a["n_fn"],$b["n_fn"]);'));
						}
						elseif($accounts)
						{
							$contacts =& $accounts;
						}
						unset($accounts);
					}
				} else {
					// < 1.3
					$contacts = $GLOBALS['egw']->contacts->read(0,20,array(
						'fn' => 1,
						'email' => 1,
						'email_home' => 1,
					), $_searchString, 'tid=n', '', 'fn');
				}
			}
			$response = new xajaxResponse();

			if(is_array($contacts)) {
				$innerHTML	= '';
				$jsArray	= array();
				$i		= 0;
				
				foreach($contacts as $contact) {
					foreach(array($contact['email'],$contact['email_home']) as $email) {
						// avoid wrong addresses, if an rfc822 encoded address is in addressbook
						$email = preg_replace("/(^.*<)([a-zA-Z0-9_\-]+@[a-zA-Z0-9_\-\.]+)(.*)/",'$2',$email);
						$contact['n_fn'] = str_replace(array(',','@'),' ',$contact['n_fn']);
						$completeMailString = addslashes(trim($contact['n_fn'] ? $contact['n_fn'] : $contact['fn']) .' <'. trim($email) .'>');
						if(!empty($email) && in_array($completeMailString ,$jsArray) === false) {
							$i++;
							$str = $GLOBALS['egw']->translation->convert(trim($contact['n_fn'] ? $contact['n_fn'] : $contact['fn']) .' <'. trim($email) .'>', $this->charset, 'utf-8');
							#$innerHTML .= '<div class="inactiveResultRow" onclick="selectSuggestion('. $i .')">'.
							$innerHTML .= '<div class="inactiveResultRow" onmousedown="keypressed(13,1)" onmouseover="selectSuggestion('.($i-1).')">'.
								htmlentities($str, ENT_QUOTES, 'utf-8') .'</div>';
							$jsArray[$i] = $completeMailString;
						}
						if ($i > 10) break;	// we check for # of results here, as we might have empty email addresses
					}
				}

				if($jsArray) {
					$response->addAssign('resultBox', 'innerHTML', $innerHTML);
					$response->addScript('results = new Array("'.implode('","',$jsArray).'");');
					$response->addScript('displayResultBox();');
				}
				//$response->addScript("getResults();");
				//$response->addScript("selectSuggestion(-1);");
			} else {
				$response->addAssign('resultBox', 'className', 'resultBoxHidden');
			}

			return $response->getXML();
		}
	}
