<?php
/**
 * EGroupware - Mail Folder ACL- interface class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Hadi Nategh [hn@stylite.de]
 * @copyright (c) 2013 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version
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
		'cdilprsw'	=> array('label'=>'write','title'=>'Allows a user to read the maibox, post to it, append messages to it, and delete messages or the mailbox itself. The only right not given is the right to change the ACL of the mailbox.'),
		'acdilprsw'	=> array('label'=>'all','title'=>'The user has all possible rights on the mailbox. This is usually granted to users only on the mailboxes they own.'),
		'custom'	=> array('label'=>'custom','title'=>'User defined combination of rights for the ACL'),
	);

	/**
	 * instance of mail_bo
	 *
	 * @var mail_bo
	 */
	var $mail_bo;

	/**
	 *
	 * @var mail_account
	 */
	var $current_account;

	/**
	 * Constructor
	 *
	 *
	 */
	function __construct()
	{
		$this->mail_bo = mail_bo::getInstance(false, (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']);

	}

	/**
	 * Edit folder ACLs for account(s)
	 *
	 * @param string $msg
	 * @param array $content
	 *
	 * @todo delete action / recursive action/
	 */
	function edit(array $content=null ,$msg='')
	{

		$tmpl = new etemplate_new('mail.acl');
		$preserv['mailbox'] = $mailbox = $_GET['mailbox'];
		if (!is_array($content))
		{
			if (!empty($mailbox))
			{
				$acl = (array)$this->retrive_acl($mailbox, $msg);
				$n = 1;
				foreach ($acl as $keys => $value)
				{

					$value = array_shift(array_values((array)$value));
					foreach ($value as $right)
					{
						$content['grid'][$n]['acl_'. $right] = true;
					}
					$acl_abbrvs = implode('',$value);
					//$acl_c =
					if (array_key_exists($acl_abbrvs, $this->aclRightsAbbrvs))
					{
						$content['grid'][$n]['acl'] = $acl_abbrvs;
					}
					else
					{
						$content['grid'][$n]['acl'] = 'custom';
					}

					$content['grid'][$n++]['acc_id'] = $keys;

				}
			}
			array_push($content['grid'], array('acc_id'=>''));
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
						else
						{
							$msg .= lang("The Folder %1 's ACLs saved!", $content['mailbox']);
						}
						//Add new row at the end
						if ($content['grid'][count($content['grid'])]['acc_id'])
							array_push($content['grid'], array('acc_id'=>''));

						$preserv ['mailbox'] = $content['mailbox'];

					}
					else
					{
						$msg .= "\n".lang("Error: Could not save ACL").' '.lang("reason!");
					}
					//Send message
					egw_framework::refresh_opener($msg, 'mail', 'update');
					if ($button == "apply") break;


				case 'cancel':
					egw_framework::window_close();
					common::egw_exit();
					break;
				case 'delete':
						$msg = "delete";
						$content['grid'] = $this->remove_acl($content,$msg);
						egw_framework::refresh_opener($msg, 'mail', 'update');
			}
		}
		$sel_options['acl'] = $this->aclRightsAbbrvs;
		
		$content['msg'] = $msg;
		$tmpl->exec('mail.mail_acl.edit', $content, $sel_options, $readonlys, $preserv,2);
	}

	/**
	 * Update ACL rights of a folder or including subfolders for an account(s)
	 *
	 * @param array $content content including the acl rights
	 * @param Boolean $recursive boolean flag FALSE|TRUE. If it is FALSE, only the folder take in to account, but in case of TRUE
	 *		the mailbox including all its subfolders will be considered.
	 * @param string $msg Message
	 *
	 * @todo need to consider recursively update
	 * @todo rights 'c' and 'd' should be fixed
	 */
	function update_acl ($content, &$msg)
	{
		$validator = array();

		foreach ($content['grid'] as $keys => $value)
		{
			unset($value['acc_id']);
			unset($value['acl_recursive']);
			unset($value['acl']);
			$i=0;
			$options = array();
			foreach ($value as $key => $val)
			{
				if ($value[$key] == true)
				{
					$right = explode("acl_" ,$key);
					$options['rights'] .=  $right[1];
				}
			}
			if (!empty($content['grid'][$keys]['acc_id'][0]))
			{
				$this->setACL($content['mailbox'], $content['grid'][$keys]['acc_id'][0],$options );
			}
			else
			{
				if($keys !== count($content['grid']))
				{
					array_push($validator, $keys) ;
					$msg = lang("Could not save the ACL! Because some names are empty!");
				}
			}
		}
		if (is_array($validator)) return $validator;
	}

	/**
	 * Retrive Folder ACL rights
	 * @todo rights 'c' and 'd' should be fixed
	 */
	function retrive_acl ($mailbox, &$msg)
	{
		if (($acl = $this->getACL($mailbox)))
		 {
			$msg = lang('ACL rights retrived successfully!');
			return $acl;
		 }
		 else
		 {
			$msg = lang('Get ACL rights failed from IMAP server!');
		 }
	}

	/**
	 * remove_acl
	 *
	 * @param Array $content content array of popup window
	 * @param string $msg message
	 *
	 * @todo need to be completed
	 */
	function remove_acl($content,$msg)
	{
		$row_num = array_keys($content['grid']['delete'],"pressed");
		$row_num = $row_num[0];
		$identifier = $content['grid'][$row_num]['acc_id'][0];
		//$this->deleteACL($content['mailbox'], $identifier,$content['grid'][$row_num]['recursively'] );
		unset($content['grid'][$row_num]);
		unset($content['grid']['delete']);
		return array_combine(range(1, count($content['grid'])), array_values($content['grid']));
	}

	/**
	 * Delete ACL rights of a folder or including subfolders from an account
	 *
	 * @param String $mailbox folder name that needs to be edited
	 * @param String $identifier The identifier to delete.
	 * @param Boolean $recursive boolean flag FALSE|TRUE. If it is FALSE, only the folder take in to account, but in case of TRUE
	 *		the mailbox including all its subfolders will be considered.
	 *
	 * @todo need to considetr recursive action
	 */
	function deleteACL ($mailbox, $identifier, $recursive)
	{
		try
		{
			$this->mail_bo->icServer->deleteACL($mailbox, $identifier);
			return true;
		}
		catch (Exception $e)
		{
			error_log(__METHOD__. "Could not delete ACL rights of folder " . $mailbox . " for account ". $identifier ." because of " .$e->getMessage());
			return false;
		}

	}

	/**
	 * Set ACL rights of a folder or including subfolders to an account
	 * @param String $mailbox folder name that needs to be edited
	 * @param String $Identifier The identifier to set.
	 * @param Array $options Additional options:
     *				- rights: (string) The rights to alter or set.
     *				- action: (string, optional) If 'add' or 'remove', adds or removes the
     *				specified rights. Sets the rights otherwise.
	 * @param Boolean $recursive boolean flag FALSE|TRUE. If it is FALSE, only the folder take in to account, but in case of TRUE
	 *		the mailbox including all its subfolders will be considered.
	 * @param String $msg message
	 * @return Boolean FALSE in case of any exceptions and if TRUE in case of success,
	 *
	 */
	function setACL($mailbox, $identifier,$options)
	{
		try
		{
			$this->mail_bo->icServer->setACL($mailbox,$identifier,$options);
			return true;
		}
		catch (Exception $e)
		{
			error_log(__METHOD__. "Could not set ACL rights on folder " . $mailbox . " for account ". $identifier . " because of " .$e->getMessage());
			return false;
		}
	}

	/**
	 * Get ACL rights of a folder from an account
	 *
	 * @param String $mailbox folder name that needs to be read
	 * @return Boolean FALSE in case of any exceptions and if TRUE in case of success,
	 */
	function getACL ($mailbox)
	{
		if(($acl =$this->mail_bo->icServer->getACL($mailbox)))
		{
			try
			{
				$acl = $this->mail_bo->icServer->getACL($mailbox);
				return $acl;
			} catch (Exception $e) {
				error_log(__METHOD__. "Could not get ACL rights from folder " . $mailbox . " because of " .$e->getMessage());
				return false;
			}
		}
	}
}