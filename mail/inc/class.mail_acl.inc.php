<?php
/**
 * EGroupware - Mail Folder ACL- interface class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Hadi Nategh [hn@egroupware.org]
 * @copyright (c) 2013-16 by EGroupware GmbH <info-AT-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/*
 * Reference: RFC 4314 DOCUMENTATION - RIGHTS (https://tools.ietf.org/html/rfc4314)
 *
 * Standard Rights:
 *
 * The currently defined standard rights are (note that the list below
 * doesn't list all commands that use a particular right):
 *
 * l - lookup (mailbox is visible to LIST/LSUB commands, SUBSCRIBE mailbox)
 * r - read (SELECT the mailbox, perform STATUS)
 * s - keep seen/unseen information across sessions (set or clear \SEEN flag
 *     via STORE, also set \SEEN during APPEND/COPY/ FETCH BODY[...])
 * w - write (set or clear flags other than \SEEN and \DELETED via
 *     STORE, also set them during APPEND/COPY)
 * i - insert (perform APPEND, COPY into mailbox)
 * p - post (send mail to submission address for mailbox,
 *     not enforced by IMAP4 itself)
 * k - create mailboxes (CREATE new sub-mailboxes in any
 *     implementation-defined hierarchy, parent mailbox for the new
 *     mailbox name in RENAME)
 * x - delete mailbox (DELETE mailbox, old mailbox name in RENAME)
 * t - delete messages (set or clear \DELETED flag via STORE, set
 *     \DELETED flag during APPEND/COPY)
 * e - perform EXPUNGE and expunge as a part of CLOSE
 * a - administer (perform SETACL/DELETEACL/GETACL/LISTRIGHTS)
 *
 *
 *
 * Obsolete Rights:
 *
 * Due to ambiguity in RFC 2086, some existing RFC 2086 server
 * implementations use the "c" right to control the DELETE command.
 * Others chose to use the "d" right to control the DELETE command.  For
 * the former group, let's define the "create" right as union of the "k"
 * and "x" rights, and the "delete" right as union of the "e" and "t"
 * rights.  For the latter group, let's define the "create" rights as a
 * synonym to the "k" right, and the "delete" right as union of the "e",
 * "t", and "x" rights.
 * For compatibility with RFC 2086, this section defines two virtual
 * rights "d" and "c".
 * If a client includes the "d" right in a rights list, then it MUST be
 * treated as if the client had included every member of the "delete"
 * right.  (It is not an error for a client to specify both the "d"
 * right and one or more members of the "delete" right, but the effect
 * is no different than if just the "d" right or all members of the
 * "delete" right had been specified.)
 *
 */


use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Mail;

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
	 * static used define abbreviations for common access rights
	 *
	 * @array
	 *
	 */
	var $aclRightsAbbrvs = array(
		'lrs'		=> array('label'=>'readable','title'=>'Allows a user to read the contents of the mailbox.'),
		'lprs'		=> array('label'=>'post','title'=>'Allows a user to read the mailbox and post to it through the delivery system by sending mail to the submission address of the mailbox.'),
		'ilprs'		=> array('label'=>'append','title'=>'Allows a user to read the mailbox and append messages to it, either via IMAP or through the delivery system.'),
		'ilprsw'	=> array('label'=>'write','title'=>'Allows a user to read and write the maibox, post to it, append messages to it.'),
		'eilprswtk'	=> array('label'=>'write & delete','title'=>'Allows a user to read, write and create folders and mails, post to it, append messages to it and delete messages.'),
		'aeiklprstwx'=> array('label'=>'all','title'=>'The user has all possible rights on the mailbox. This is usually granted to users only on the mailboxes they own.'),
		'custom'	=> array('label'=>'custom','title'=>'User defined combination of rights for the ACL'),
	);

	/**
	 * imap object instantiated in constructor for account to edit
	 *
	 * @var Mail\Imap
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
	 * @param array $content = null
	 * @param string $msg = ''
	 *
	 */
	function edit(array $content=null ,$msg='')
	{
		$tmpl = new Etemplate('mail.acl');
		if (!is_array($content))
		{
			$acc_id = $_GET['acc_id'] ?? $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
			if (isset($_GET['account_id']) && !isset($GLOBALS['egw_info']['user']['apps']['admin']))
			{
				Framework::window_close(lang('Permission denied'));
			}
			$account_id = $_GET['account_id'];
		}
		else
		{
			$acc_id = $content['acc_id'];
			$account_id = $content['account_id'];
		}
		$account = Mail\Account::read($acc_id, $account_id);
		$this->imap = $account->imapServer(isset($account_id) ? (int)$account_id : false);

		$mailbox = $_GET['mailbox']? base64_decode($_GET['mailbox']): self::_extract_mailbox($content['mailbox'], $acc_id);
		if (empty($mailbox))
		{
			$mailbox = $this->imap->isAdminConnection ? $this->imap->getUserMailboxString($account_id) : 'INBOX';
		}
		if (!$this->imap->isAdminConnection)
		{
			$tmpl->setElementAttribute('mailbox', 'searchOptions', array('mailaccount' => $acc_id));
		}
		else
		{
			$tmpl->setElementAttribute('mailbox', 'searchUrl', '');
			//Todo: Implement autocomplete_url function with admin stuffs consideration
		}
		// Unset the content if folder is changed, in order to read acl rights for new selected folder
		if (!is_array($content['button']) && self::_extract_mailbox($content['mailbox'], $acc_id) && !is_array($content['grid']['delete'])) unset($content);

		if (!is_array($content))
		{
			if (!empty($mailbox))
			{
				$content['mailbox'] = $mailbox;
				if (($acls = $this->retrieve_acl($mailbox, $msg)) === false)
				{
					Api\Framework::window_close($msg);
				}
				$n = 1;
				foreach ($acls as $key => $acl)
				{
					$rights = [];
					foreach ($acl->getIterator() as $right)
					{
						$content['grid'][$n]['acl_'. $right] = true;
						$rights[] = $right;
					}
					$virtual = $acl->getString(Horde_Imap_Client_Data_Acl::RFC_2086);
					foreach(['c', 'd'] as $right)
					{
						if (strpos($virtual, $right) !== false)
						{
							$content['grid'][$n]['acl_'. $right] = true;
						}
					}

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
			$button = !empty ($content['grid']['delete']) ? 'delete' : @key((array)$content['button']);
			$data = $content;
			$data['mailbox'] = self::_extract_mailbox($content['mailbox'], $acc_id);
			switch ($button)
			{
				case 'save':
				case 'apply':
					if ($content)
					{
						$validation_err = $this->update_acl($data,$msg);
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
					Framework::message($msg);
					if ($button == "apply") break;
					Framework::window_close();
					exit;

				case 'delete':
					$aclRvmCnt = $this->remove_acl($data, $msg);
					if (is_array($aclRvmCnt))
					{
						$content['grid'] = $aclRvmCnt;
					}
					else
					{
						error_log(__METHOD__.__LINE__. "()" . "The remove_acl suppose to return an array back, something is wrong there");
					}
					Framework::message($msg);
			}
		}
		$readonlys = $sel_options = array();
		$sel_options['mailbox'] = [['value' => $mailbox, 'label' => $mailbox]];
		$sel_options['acl'] = $this->aclRightsAbbrvs;

		//Make the account owner's fields all readonly as owner has all rights and should not be able to change them
		foreach($content['grid'] as $key => $fields)
		{
			if (self::_extract_acc_id($fields['acc_id']) == $this->imap->acc_imap_username ||
					$this->imap->getMailBoxUserName(self::_extract_acc_id($fields['acc_id'])) == $this->imap->acc_imap_username)
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
			if (count($content['grid']) != $key)
			{
				$preserv ['grid'][$key]['acc_id'] = self::_extract_acc_id($fields['acc_id']);
				$preserv['grid'][$key]['acl_recursive'] = false;
				$readonlys['grid'][$key]['acc_id'] = true;
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
			$tmpl->setElementAttribute('mailbox', 'searchUrl', __CLASS__ . '::ajax_folders');
			$tmpl->setElementAttribute('mailbox', 'searchOptions', array(
				'acc_id'     => $acc_id,
				'account_id' => $account_id,
			));
		}

		$tmpl->exec('mail.mail_acl.edit', $content, $sel_options, $readonlys, $preserv,2);
	}

	/**
	 * Autocomplete for folder taglist
	 *
	 * @throws Api\Exception\NoPermission\Admin
	 */
	public static function ajax_folders()
	{
		if (!empty($_GET['account_id']) && !$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			throw new Api\Exception\NoPermission\Admin;
		}
		$account = Mail\Account::read($_GET['acc_id'], $_GET['account_id']);
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
		Api\Json\Request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($folders);

		exit;
	}

    /**
     * Update ACL rights of a folder or including subfolders for an account(s)
     *
     * @param array $content content including the acl rights
     * @param string $msg Message
     *
     * @return Array | void return array of validation messages or nothing
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
			foreach ($value as $key => $set)
			{
				if ($set)
				{
					$right = explode("acl_", $key);
					if ($right[1] === 'c') $right[1] = 'kx'; // c = kx , rfc 4314
					if ($right[1] === 'd') $right[1] = 'et'; // d = et , rfc 4314
					$options['rights'] .=  $right[1];
				}
			}
			$username = self::_extract_acc_id($content['grid'][$keys]['acc_id']);

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
					$msg = lang('Error while setting ACL for folder %1!', $content['mailbox'])."\n".$msg;
				}
			}
			else
			{
				if($keys !== count($content['grid']))
				{
					array_push($validator, $keys);
					$msg = lang("Could not save the ACL because some names are empty");
				}
			}
		}
		if (is_array($validator))
		{
			return $validator;
		}
	}

	/**
	 * Retrieve Folder ACL rights
     * @param string $mailbox
     * @param string &$msg
	 *
     * @return Horde_Imap_Client_Data_Acl[]|false returns array of acl or false on failure
     * @todo rights 'c' and 'd' should be fixed
	 */
	function retrieve_acl ($mailbox, &$msg)
	{
		if (($acl = $this->getACL($mailbox)) !== false)
		 {
			$msg = lang('ACL rights retrieved successfully');
			return $acl;
		 }
		 else
		 {
			$msg = lang('Get ACL rights failed from IMAP server!');
			return false;
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
	 * @return Array | Boolean An array as new content for grid or false in case of error
	 */
	function remove_acl($content, &$msg)
	{
		$row_num = array_keys($content['grid']['delete'],"pressed");
		if ($row_num) $row_num = $row_num[0];
		$recursive = $content['grid'][$row_num]['acl_recursive'];
		$identifier = self::_extract_acc_id($content['grid'][$row_num]['acc_id']);
		$content['mailbox'] = is_array($content['mailbox'])? $content['mailbox'][0] : $content['mailbox'];
		if (is_numeric($identifier) && ($u = $this->imap->getMailBoxUserName($identifier)))
		{
			$identifier = $u;
		}
		//error_log(__METHOD__.__LINE__."(".$content['mailbox'].", ".$identifier.", ".$recursive.")");
		if(($res = $this->deleteACL($content['mailbox'], $identifier,$recursive,$msg)))
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
			$msg = lang("An error happend while trying to remove ACL rights from the account %1!",$identifier)."\n".$msg;
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
	 * @param String& $msg=null on return error-message
	 * @return Boolean FALSE in case of any exceptions and TRUE in case of success
	 */
	function deleteACL ($mailbox, $identifier, $recursive, &$msg=null)
	{
		if ($recursive)
		{
			$folders = self::getSubfolders($mailbox, $this->imap);
		}
		else
		{
			$folders = (array)$mailbox;
		}
		$errors = [];
		$success = 0;
		foreach($folders as $sbFolders)
		{
			try
			{
				$this->imap->deleteACL($sbFolders, $identifier);
				$success++;
			}
			catch (Exception $e)
			{
				$errors[] = $sbFolders.': '.$e->getMessage();
				error_log(__METHOD__. "Could not delete ACL rights of folder " . $sbFolders . " for account ". $identifier ."." .$e->getMessage());
			}
		}
		if ($errors)
		{
			$msg = lang("Succeeded on %1 folders, failed on %2", $success, count($errors)).":\n- ".
				implode("\n- ", $errors);
			return false;
		}
		return true;
	}

	/**
	 * Get subfolders of a mailbox
	 *
	 * @param string $mailbox structural folder name
	 * @param Mail\Imap $imap
	 * @return Array an array including all subfolders of given mailbox| returns an empty array in case of no subfolders
	 */
	protected static function getSubfolders($mailbox, Mail\Imap $imap)
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
		$errors = [];
		$success = 0;
		foreach($folders as $sbFolders)
		{
			try
			{
				$this->imap->setACL($sbFolders,$identifier,$options);
				$success++;
			}
			catch (Exception $e)
			{
				$errors[] = $sbFolders.': '.$e->getMessage();
				error_log(__METHOD__. "Could not set ACL rights on folder " . $sbFolders . " for account ". $identifier . "." .$e->getMessage());
			}
		}
		if ($errors)
		{
			$msg = lang("Succeeded on %1 folders, failed on %2", $success, count($errors)).":\n- ".
				implode("\n- ", $errors);
			return false;
		}
		return true;
	}

	/**
	 * Get ACL rights of a folder from an account
	 *
	 * @param String $mailbox folder name that needs to be read
	 * @return Horde_Imap_Client_Data_Acl[]|false FALSE in case of any exceptions and returns Array in case of success,
	 */
	function getACL ($mailbox)
	{
		try
		{
			return $this->imap->getACL($mailbox);
		} catch (Exception $e) {
			error_log(__METHOD__. "Could not get ACL rights from folder " . $mailbox . "." .$e->getMessage());
			return false;
		}
	}

	/**
	 * Method to get acc_id id value whether if is a flat value or an array
	 *
	 * @param type $acc_id acc_id value comming from client-side
	 *
	 * @return string returns acc_id in flat format
	 */
	private static function _extract_acc_id ($acc_id)
	{
		return is_array($acc_id)?$acc_id[0]:$acc_id;
	}

    /**
     * @param string | array $mailbox
     * @param string $acc_id
     *
     * @return string | NULL return sanitate mailbox of acc id and delimiter and return it as string
     */
	private static function _extract_mailbox ($mailbox, $acc_id)
    {
        $mailbox = is_array($mailbox) ? $mailbox[0] : $mailbox;
        return preg_replace("/^".$acc_id."::/",'', $mailbox);
    }
}