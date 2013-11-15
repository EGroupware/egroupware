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
		$this->current_account = $this->mail_bo->icServer->acc_imap_username;
	}

	/**
	 * Edit folder ACLs for account(s)
	 *
	 * @param string $msg
	 * @param array $content
	 */
	function edit(array $content=null ,$msg='')
	{
		if (!is_array($content))
		{
			$mailbox = $_GET['mailbox'];
			if (!empty($mailbox))
			{
				$acl = $this->retrive_acl($mailbox, $msg);

			}
		}

		$tmpl = new etemplate_new('mail.acl');
		$content = array();
		$tmpl->exec('mail.mail_ui.edit_acl', $content, $sel_options, $readonlys, array(),2);
	}

	/**
	 * Update Folder ACL rights
	 *
	 */
	function update_acl ($mailbox, $ident,$options, &$msg)
	{

	}

	/**
	 * Retrive Folder ACL rights
	 *
	 */
	function retrive_acl ($mailbox, &$msg)
	{
		 if ($acl = $this->mail_bo->icServer->getACL($mailbox))
		 {
			if (is_array($acl))
			{
				$msg = lang('ACL rights retrived successfully!');
			}
			else
			{
				$msg = lang('ACL rights retrive failed, seems there are no rights set!');
			}
		 }
		 else
		 {
 			$msg = lang('Get ACL rights failed from IMAP server!');
			error_log(__METHOD__. "(" . $acl . ")" );
		 }
	}
}