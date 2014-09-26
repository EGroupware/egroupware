<?php
/**
 * EGroupware - Mail Folder ACL- interface class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Hadi Nategh [hn@stylite.de]
 * @copyright (c) 2013 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

class mail_acl
{
	/**
	 * Methods callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'edit'	=> True,
	);

	/**
	 * static used define abbrevations for common access rights
	 *
	 * @array
	 *
	 */
	var $aclRightsAbbrvs = array(
		'lrs'		=> array('label'=>'readable','title'=>'Allows a user to read the contents of the mailbox.'),
		'lprs'		=> array('label'=>'post','title'=>'Allows a user to read the mailbox and post to it through the delivery system by sending mail to the submission address of the mailbox.'),
		'ilprs'		=> array('label'=>'append','title'=>'Allows a user to read the mailbox and append messages to it, either via IMAP or through the delivery system.'),
		'ilprsw'	=> array('label'=>'write','title'=>'Allows a user to read the maibox, post to it, append messages to it, and delete messages or the mailbox itself. The only right not given is the right to change the ACL of the mailbox.'),
		'aeiklprstwx'=> array('label'=>'all','title'=>'The user has all possible rights on the mailbox. This is usually granted to users only on the mailboxes they own.'),
		'custom'	=> array('label'=>'custom','title'=>'User defined combination of rights for the ACL'),
	);

	/**
	 * instance of mail_bo
	 *
	 * @var mail_bo
	 */
	var $mail_bo;

	/**
	 * imap object instanciated in constructor for account to edit
	 *
	 * @var emailadmin_imap
	 */
	var $imap;

	/**
	 *
	 * @var mail_account
	 */
	var $current_account;

	/**
	 * Edit folder ACLs of account(s)
	 *
	 * @param string $content = null
	 * @param array $msg = ''
	 *
	 */
	function edit(array $content=null ,$msg='')
	{
		$tmpl = new etemplate_new('mail.acl');
		if (!is_array($content))
		{
			$acc_id = $_GET['acc_id']?$_GET['acc_id']:$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
			if (isset($_GET['account_id']) && !isset($GLOBALS['egw_info']['user']['apps']['admin']))
			{
				egw_framework::window_close(lang('Permission denied'));
			}
			$account_id = $_GET['account_id'];
		}
		else
		{
			$acc_id = $content['acc_id'];
			$account_id = $content['account_id'];
		}
		$account = emailadmin_account::read($acc_id, $account_id);
		$this->imap = $account->imapServer(isset($account_id) ? (int)$account_id : false);

		$mailbox = $_GET['mailbox']? base64_decode($_GET['mailbox']): $content['mailbox'][0];
		if (empty($mailbox))
		{
			$mailbox = $this->imap->isAdminConnection ? $this->imap->getUserMailboxString($this->imap->isAdminConnection) : 'INBOX';
		}
		if (!$this->imap->isAdminConnection)
		{
			$tmpl->setElementAttribute('mailbox', 'autocomplete_url', 'mail.mail_compose.ajax_searchFolder');
		}
		else
		{
			//Todo: Implement autocomplete_url function with admin stuffs consideration
		}
		// Unset the content if folder is changed, in order to read acl rights for new selected folder
		if (!is_array($content['button']) && is_array($content['mailbox']) && !is_array($content['grid']['delete'])) unset($content);

		if (!is_array($content))
		{
			if (!empty($mailbox))
			{
				$content['mailbox'] = $mailbox;
				$acl = (array)$this->retrive_acl($mailbox, $msg);
				$n = 1;
				foreach ($acl as $key => $value)
				{
					$virtuals = array_pop(array_values((array)$value));
					$rights = array_shift(array_values((array)$value));

					foreach ($rights as $right)
					{
						$content['grid'][$n]['acl_'. $right] = true;
					}
					$virtualD = array('e','t');
					$content['grid'][$n]['acl_c'] = array_diff($virtuals['c'],array_intersect($rights,$virtuals['c']))? false: true; //c=kx more information rfc4314, Obsolote Rights
					$content['grid'][$n]['acl_d'] = array_diff($virtualD,array_intersect($rights,$virtuals['d']))? false: true; //d=et more information rfc4314, Obsolote Rights

					sort($rights);
					$acl_abbrvs = implode('',$rights);

					if (array_key_exists($acl_abbrvs, $this->aclRightsAbbrvs))
					{
						$content['grid'][$n]['acl'] = $acl_abbrvs;
					}
					else
					{
						$content['grid'][$n]['acl'] = 'custom';
					}
					if (($user = $this->imap->getMailBoxAccountId($key)))
					{
						$content['grid'][$n++]['acc_id'] = $user;
					}
					else
					{
						$content['grid'][$n++]['acc_id'] = $key;
					}
				}
				//error_log(__METHOD__."() acl=".array2string($acl).' --> grid='.array2string($content['grid']));
			}
			//Set the acl entry in the last row with lrs as default ACL
			array_push($content['grid'], array(
				'acc_id'=>'',
				'acl_l' => true,
				'acl_r' => true,
				'acl_s' => true));
		}
		else
		{
			list($button) = @each($content['button']);
			if (!empty ($content['grid']['delete']))
			{
				$button = 'delete';
			}
			switch ($button)
			{
				case 'save':
				case 'apply':
					if ($content)
					{
						$validation_err = $this->update_acl($content,$msg);
						if ($validation_err)
						{
							foreach ($validation_err as &$row)
							{
								$tmpl->set_validation_error('grid['.$row.']'.'[acc_id]', "You must fill this field!");
							}
						}

						//Add new row at the end
						if ($content['grid'][count($content['grid'])]['acc_id'])
							array_push($content['grid'], array('acc_id'=>''));
					}
					else
					{
						$msg .= "\n".lang("Error: Could not save ACL").' '.lang("reason!");
					}
					//Send message
					egw_framework::message($msg);
					if ($button == "apply") break;

				//Fall through
				case 'cancel':
					egw_framework::window_close();
					common::egw_exit();
					break;
				case 'delete':
					$aclRvmCnt = $this->remove_acl($content, $msg);
					if (is_array($aclRvmCnt))
					{
						$content['grid'] = $aclRvmCnt;
					}
					else
					{
						error_log(__METHOD__.__LINE__. "()" . "The remove_acl suppose to return an array back, something is wrong there");
					}
					egw_framework::message($msg);
			}
		}
		$readonlys = $sel_options = array();
		$sel_options['acl'] = $this->aclRightsAbbrvs;

		//Make the account owner's fields all readonly as owner has all rights and should not be able to change them
		foreach($content['grid'] as $key => $fields)
		{
			if ($fields['acc_id'] == $this->imap->acc_imap_username ||
					$fields['acc_id'][0] == $this->imap->acc_imap_username)
			{
				foreach (array_keys($fields) as $index)
				{
					$readonlys['grid'][$key][$index] = true;
				}
				$readonlys['grid']['delete['.$key.']'] = true;
				$readonlys['grid'][$key]['acl_recursive'] = true;
				$preserv ['grid'][$key] = $fields;
				$preserv['grid'][$key]['acl_recursive'] = false;
			}
		}
		//Make entry row's delete button readonly
		$readonlys['grid']['delete['.count($content['grid']).']'] = true;

		$preserv['mailbox'] = $content['mailbox'];
		$preserv['acc_id'] = $acc_id;
		$preserv['account_id'] = $account_id;
		$content['grid']['account_type'] = $this->imap->supportsGroupAcl() ? 'both' : 'accounts';

		// set a custom autocomplete method for mailbox taglist
		if ($account_id)
		{
			$tmpl->setElementAttribute('mailbox', 'autocomplete_url', __CLASS__.'::ajax_folders');
			$tmpl->setElementAttribute('mailbox', 'autocomplete_params', array(
				'acc_id' => $acc_id,
				'account_id' => $account_id,
			));
		}

		$tmpl->exec('mail.mail_acl.edit', $content, $sel_options, $readonlys, $preserv,2);
	}

	/**
	 * Autocomplete for folder taglist
	 *
	 * @throws egw_exception_no_permission_admin
	 */
	public static function ajax_folders()
	{
		if (!empty($_GET['account_id']) && !$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			throw new egw_exception_no_permission_admin;
		}
		$account = emailadmin_account::read($_GET['acc_id'], $_GET['account_id']);
		$imap = $account->imapServer(!empty($_GET['account_id']) ? (int)$_GET['account_id'] : false);
		$mailbox = $imap->isAdminConnection ? $imap->getUserMailboxString($imap->isAdminConnection) : 'INBOX';

		$folders = array();
		foreach(self::getSubfolders($mailbox, $imap) as $folder)
		{
			if (stripos($folder, $_GET['query']) !== false)
			{
				$folders[] = array(
					'id' => $folder,
					'label' => $folder,
				);
			}
		}
		// switch regular JSON response handling off
		egw_json_request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($folders);

		common::egw_exit();
	}

	/**
	 * Update ACL rights of a folder or including subfolders for an account(s)
	 *
	 * @param array $content content including the acl rights
	 * @param Boolean $recursive boolean flag FALSE|TRUE. If it is FALSE, only the folder take in to account, but in case of TRUE
	 *		the mailbox including all its subfolders will be considered.
	 * @param string $msg Message
	 *
	 */
	function update_acl ($content, &$msg)
	{
		$validator = array();

		foreach ($content['grid'] as $keys => $value)
		{
			$recursive = $value['acl_recursive'];
			unset($value['acc_id']);
			unset($value['acl_recursive']);
			unset($value['acl']);

			$options = array();
			foreach (array_keys($value) as $key)
			{
				if ($value[$key] == true)
				{
					$right = explode("acl_" ,$key);
					if ($right[1] === 'c') $right[1] = 'kx'; // c = kx , rfc 4314
					if ($right[1] === 'd') $right[1] = 'et'; // d = et , rfc 4314
					$options['rights'] .=  $right[1];
				}
			}
			$username = $content['grid'][$keys]['acc_id'] == $this->imap->acc_imap_username
				?$content['grid'][$keys]['acc_id']:$content['grid'][$keys]['acc_id'][0];
			//error_log(__METHOD__."(".__LINE__.") setACL($content[mailbox], $username, ".array2string($options).", $recursive)");
			if (is_numeric($username) && ($u = $this->imap->getMailBoxUserName($username)))
			{
				$username = $u;
			}
			if (!empty($username))
			{
				//error_log(__METHOD__."() setACL($content[mailbox], $username, ".array2string($options).", $recursive)");
				if (($ret=$this->setACL($content['mailbox'], $username, $options, $recursive, $msg)))
				{
					$msg = lang("The Folder %1 's ACLs saved", $content['mailbox']);

				}
				else
				{
					$msg = lang('Error while setting ACL for folder %1!', $content['mailbox']).' '.$msg;
				}
			}
			else
			{
				if($keys !== count($content['grid']))
				{
					array_push($validator, $keys);
					$msg = lang("Error:Could not save the ACL! Because some names are empty!");
				}
			}
		}
		if (is_array($validator))
		{
			return $validator;
		}
	}

	/**
	 * Retrive Folder ACL rights
	 * @todo rights 'c' and 'd' should be fixed
	 */
	function retrive_acl ($mailbox, &$msg)
	{
		if (($acl = $this->getACL($mailbox)))
		 {
			$msg = lang('ACL rights retrived successfully');
			return $acl;
		 }
		 else
		 {
			$msg = lang('Get ACL rights failed from IMAP server!');
		 }
	}

	/**
	 * remove_acl
	 * This method take content of acl rights, and will delete the one from ACL IMAP,
	 * for selected folder and/or its subfolders
	 *
	 * @param Array $content content array of popup window
	 * @param string $msg message
	 *
	 * @return Array An array as new content for grid
	 */
	function remove_acl($content, &$msg)
	{
		$row_num = array_keys($content['grid']['delete'],"pressed");
		if ($row_num) $row_num = $row_num[0];
		$recursive = $content['grid'][$row_num]['acl_recursive'];
		$identifier = $content['grid'][$row_num]['acc_id'][0];
		if (is_array($content['mailbox'])) $content['mailbox'] = $content['mailbox'][0];
		if (is_numeric($identifier) && ($u = $this->imap->getMailBoxUserName($identifier)))
		{
			$identifier = $u;
		}
		//error_log(__METHOD__.__LINE__."(".$content['mailbox'].", ".$identifier.", ".$recursive.")");
		if(($res = $this->deleteACL($content['mailbox'], $identifier,$recursive)))
		{
			unset($content['grid'][$row_num]);
			unset($content['grid']['delete']);
			if ($recursive)
			{
				$msg = lang("The %1 's acl, including its subfolders, removed from the %2",$content['mailbox'],$identifier);
			}
			else
			{
				$msg = lang("The %1 's acl removed from the %2",$content['mailbox'],$identifier);
			}

			return array_combine(range(1, count($content['grid'])), array_values($content['grid']));
		}
		else
		{
			$msg = lang("An error happend while trying to remove ACL rights from the account %1!",$identifier);
			return false;
		}
	}

	/**
	 * Delete ACL rights of a folder or including subfolders from an account
	 *
	 * @param String $mailbox folder name that needs to be edited
	 * @param String $identifier The identifier to delete.
	 * @param Boolean $recursive boolean flag FALSE|TRUE. If it is FALSE, only the folder take in to account, but in case of TRUE
	 *		the mailbox including all its subfolders will be considered.
	 *
	 * @return Boolean FALSE in case of any exceptions and TRUE in case of success
	 */
	function deleteACL ($mailbox, $identifier, $recursive)
	{
		if ($recursive)
		{
			$folders = self::getSubfolders($mailbox, $this->imap);
		}
		else
		{
			$folders = (array)$mailbox;
		}
		foreach($folders as $sbFolders)
		{
			try
			{
				$this->imap->deleteACL($sbFolders, $identifier);
			}
			catch (Exception $e)
			{
				error_log(__METHOD__. "Could not delete ACL rights of folder " . $mailbox . " for account ". $identifier ." because of " .$e->getMessage());
				return false;
			}
		}
		return true;
	}

	/**
	 * Get subfolders of a mailbox
	 *
	 * @param string $mailbox structural folder name
	 * @param emailadmin_imap $imap
	 * @return Array an array including all subfolders of given mailbox| returns an empty array in case of no subfolders
	 */
	protected static function getSubfolders($mailbox, emailadmin_imap $imap)
	{
		$delimiter = $imap->getDelimiter();
		$nameSpace = $imap->getNameSpace();
		$prefix = $imap->getFolderPrefixFromNamespace($nameSpace, $mailbox);
		if (($subFolders = $imap->getMailBoxesRecursive($mailbox, $delimiter, $prefix)))
		{
			return $subFolders;
		}
		else
		{
			return array();
		}
	}

	/**
	 * Set ACL rights of a folder or including subfolders to an account
	 * @param String $mailbox folder name that needs to be edited
	 * @param String $identifier The identifier to set.
	 * @param Array $options Additional options:
	 * 				- rights: (string) The rights to alter or set.
	 * 				- action: (string, optional) If 'add' or 'remove', adds or removes the
	 * 				specified rights. Sets the rights otherwise.
	 * @param Boolean $recursive boolean flag FALSE|TRUE. If it is FALSE, only the folder take in to account, but in case of TRUE
	 *		the mailbox including all its subfolders will be considered.
	 * @param String $msg message
	 * @return Boolean FALSE in case of any exceptions and TRUE in case of success,
	 *
	 */
	function setACL($mailbox, $identifier,$options, $recursive, &$msg)
	{
		if ($recursive)
		{
			$folders = self::getSubfolders($mailbox, $this->imap);
		}
		else
		{
			$folders = (array)$mailbox;
		}
		foreach($folders as $sbFolders)
		{
			try
			{
				$this->imap->setACL($sbFolders,$identifier,$options);
			}
			catch (Exception $e)
			{
				$msg = $e->getMessage();
				error_log(__METHOD__. "Could not set ACL rights on folder " . $mailbox . " for account ". $identifier . " because of " .$e->getMessage());
				return false;
			}
		}
		return true;
	}

	/**
	 * Get ACL rights of a folder from an account
	 *
	 * @param String $mailbox folder name that needs to be read
	 * @return Boolean FALSE in case of any exceptions and if TRUE in case of success,
	 */
	function getACL ($mailbox)
	{
		try
		{
			$acl = $this->imap->getACL($mailbox);
			return $acl;
		} catch (Exception $e) {
			error_log(__METHOD__. "Could not get ACL rights from folder " . $mailbox . " because of " .$e->getMessage());
			return false;
		}
	}
}
