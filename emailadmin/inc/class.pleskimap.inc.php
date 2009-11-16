<?php
/***************************************************************************\
* EGroupWare - EMailAdmin IMAP via Plesk                                    *
* http://www.egroupware.org                                                 *
* Written and (c) 2006 by RalfBecker-AT-outdoor-training.de                 *
* ------------------------------------------------------------------------- *
* emailadmin plugin for plesk:                                              *
* - tested with Plesk7.5 under Linux, but should work with other plesk      *
*   versions and Windows too as it uses plesks cli (command line interface) *
* - this plugin ONLY works if you have root access to the webserver !!!     *
* - you need to have mail activated for the domain in plesk first           *
* - you need to configure the path to plesk's mail.sh or mail.exe cli by    *
*   editing this file (search for psa_mail_script) for now                  *
* - to allow the webserver to use the mail cli under Linux you need to      *
*   install the sudo package and add the following line to your sudoers     *
*   file using the visudo command as root:                                  *
*   wwwrun ALL = NOPASSWD: /usr/local/psa/bin/mail.sh                       *
*   Replace wwwrun with the user the webserver is running as and, if        *
*   necessary adapt the path to mail.sh.                                    *
*   PLEASE NOTE: This allows all webserver users to run the mail.sh script  *
*                and to change the mail configuration of ALL domains !!!    *
* => as with the "LDAP, Postfix & Cyrus" plugin the plesk one creates mail  *
*    users and manages passwords, aliases, forwards and quota from within   *
*    eGroupWare - no need to additionally visit the plesk interface anymore *
* ------------------------------------------------------------------------- *
* This program is free software; you can redistribute it and/or modify it   *
* under the terms of the GNU General Public License as published by the     *
* Free Software Foundation; version 2 of the License.                       *
\***************************************************************************/
/* $Id$ */

include_once(EGW_SERVER_ROOT."/emailadmin/inc/class.defaultimap.inc.php");

class pleskimap extends defaultimap
{
	/**
	 * @var string $psa_mail_script full path to Plesk's mail.sh (Linux including sudo!) or mail.exe (Windows) interface
	 */
	var $psa_mail_script = '/usr/bin/sudo /usr/local/psa/bin/mail.sh';	// 'C:/psa/bin/mail.exe'
	/**
	 * @var boolean $allways_create_mailbox true = allways create a mailbox on user creation, 
	 *	false = only if a local email (no forward) is given. To use felamimail you need a mailbox!
	 */
	var $allways_create_mailbox = true;
	/**
	 * @var array $create_folders=array('Send','Trash') folders to automatic create and subscribe on account creation
	 */
	var $create_folders = array('Sent','Trash');
	/**
	 * @var string/boolean $error string with last error-message or false
	 */
	var $error = false;

	/**
	 * Create a full mailbox or just forward, depending on the given email address
	 * If email matches the default domain, we create a full mailbox, otherwise we create a forward
	 *
	 * @param array $hookValues
	 * @param string $action='create'
	 * @return boolean true on success, false otherwise
	 */
	function addAccount($hookValues,$action='create')
	{
		//echo "<p>pleskimap::addAccount(".print_r($hookValues,true).")</p>\n";

		$defaultDomain = $this->profileData['defaultDomain'] ? $this->profileData['defaultDomain'] :
			$GLOBALS['egw_info']['server']['mail_suffix'];

		$localEmail = $hookValues['account_lid'].'@'.$defaultDomain;
		$aliases = $forwards = array();

		// is the given email a local address from our default domain?
		if (substr($hookValues['account_email'],-1-strlen($defaultDomain)) != '@'.$defaultDomain)
		{
			$forwards[] = $hookValues['account_email'];
		}
		elseif ($hookValues['account_email'] != $localEmail)
		{
			$aliases[] = $hookValues['account_email'];
		}
		// add a default alias with Firstname.Lastname
		if (!in_array($alias=$hookValues['account_firstname'].'.'.$hookValues['account_lastname'],$aliases) &&
			$this->is_email($alias))
		{
			$aliases[] = $alias;
		}
		$info = $this->plesk_mail($action,$hookValues['account_lid'],$hookValues['account_passwd'],
			$action != 'create' && !$aliases ? null : $aliases,$forwards,$this->allways_create_mailbox);

		if (!$info['SUCCESS']) return false;

		if ($forwards && !$this->allways_create_mailbox) return true;	// no mailbox created, only a forward

		// create Sent & Trash mailboxes and subscribe them
		if(($mbox = @imap_open ($this->getMailboxString(),$localEmail,$hookValues['account_passwd'])))
		{
			$list = imap_getmailboxes($mbox, $this->getMailboxString(),'INBOX');
			$delimiter = isset($list[0]->delimiter) ? $list[0]->delimiter : '.';
			imap_subscribe($mbox,$this->getMailboxString('INBOX'));

			foreach($this->create_folders as $folder)
			{
				$mailBoxName = 'INBOX'.$delimiter.$folder;
				if(imap_createmailbox($mbox,imap_utf7_encode('{'.$this->profileData['imapServer'].'}'.$mailBoxName)))
				{
					imap_subscribe($mbox,$this->getMailboxString($mailBoxName));
				}
			}
			imap_close($mbox);
		}
		return true;
	}

	function deleteAccount($hookValues)
	{
		//echo "<p>pleskimap::deleteAccount(".print_r($hookValues,true).")</p>\n";
		
		return $this->plesk_mail('remove',$hookValues['account_lid']);
	}

	function updateAccount($hookValues)
	{
		//echo "<p>pleskimap::updateAccount(".print_r($hookValues,true).")</p>\n";
		
		if($hookValues['account_lid'] != $hookValues['old_loginid'])
		{
			$this->error = lang("Plesk can't rename users --> request ignored");
			return false;
		}
		return $this->addAccount($hookValues,'update');
	}

	/**
	 * Read data from the mail account
	 *
	 * @param string/int $accountID
	 * @return array/boolean with keys mailLocalAddress, mailAlternateAddress, accountStatus, mailRoutingAddress, ... or false if not found
	 */
	function getUserData($accountID)
	{
		//echo "<p>pleskimap::getUserData('$accountID')</p>\n";

		if (!($info = $this->plesk_mail('info',$accountID))) return false;
		//_debug_array($info);
		
		$data = array(
			'mailLocalAddress'     => $info['Mailname'].'@'.$info['Domain'],
			'mailAlternateAddress' => $info['Alias(es)'] ? explode(' ',$info['Alias(es)']) : array(),
			'accountStatus'        => $info['Mailbox'] == 'true' || $info['Redirect'] == 'true' ? 'active' : 'disabled',
			'mailRoutingAddress'   => $info['Redirect address'] ? explode(' ',$info['Redirect address']) : false,
			'deliveryMode'         => $info['Redirect'] == 'true' && $info['Mailbox'] == 'false' ? 'forwardOnly' : '',
//				'qmailDotMode'         => false,
//				'deliveryProgramPath'  => false,
			'quotaLimit'           => $info['Mbox quota'] == 'Unlimited' ? '' : $info['Mbox quota']/1024.0,
		);
		//_debug_array($data);
		return $data;
	}
	
	/**
	 * Save mail account data
	 *
	 * @param string/int $accountID
	 * @param array $accountData with keys mailLocalAddress, mailAlternateAddress, accountStatus, mailRoutingAddress, ...
	 * @return boolean true on success, false otherwise
	 */
	function saveUserData($accountID, $accountData)
	{
		//echo "<p>pleskimap::saveUserData('$accountID',".print_r($accountData,true).")</p>\n";
		
		// not used: $accountData['accountStatus']=='active', $accountData['qmailDotMode'], $accountData['deliveryProgramPath']
		$info = $this->plesk_mail('update',$accountID,null,
			$accountData['mailAlternateAddress'] ? $accountData['mailAlternateAddress'] : array(),
			$accountData['mailRoutingAddress'] ? $accountData['mailRoutingAddress'] : array(),
			empty($accountData['deliveryMode']),
			1024*(float)$accountData['quotaLimit'],$accountData['accountStatus']=='active');
			
		if (!$info['SUCCSESS'])
		{
			if ($info) $this->error = implode(', ',$info);
			return false;
		}
		return true;
	}
	
	/**
	 * call plesk's mail command line interface
	 *
	 * 	Usage: mail.sh command <mail_name> [options]
	 * 
	 *     Available commands:
	 *     --create or -c     <mail>@<domain> creates mail account
	 *     --update or -u     <mail>@<domain> updates mail account parameters
	 *     --remove or -r     <mail>@<domain> removes mail account
	 *     --info or -i       <mail>@<domain> retrieves mail account information
	 *     --on               <domain>        enables mail service for domain
	 *     --off              <domain>        disables mail service for domain
	 *     --help or -h                       displays this help page
	 * 
	 *     Available options:
	 *     -cp_access         <true|false>    enables control panel access (default:
	 *                                        true)
	 *     -mailbox           <true|false>    creates/removes mailbox
	 *     -passwd            <passwd>        sets mailbox password [see the note
	 *                                        below for details]
	 *     -boxpass           <passwd>        obsolete alias for option "passwd"
	 *                                        (this option may be removed from
	 *                                        future releases)
	 *     -passwd_type       <plain|crypt>   specifies the type of mailbox
	 *                                        password, ignored if no password
	 *                                        specified [see the note below for
	 *                                        details]
	 *     -mbox_quota        <KB>            limits the mailbox quota to the
	 *                                        desired amount
	 *     -boxquota          <KB>            obsolete alias for option "mbox_quota"
	 *                                        (this option may be removed from
	 *                                        future releases)
	 *     -aliases           <add|del>:<name1[,name2]> adds or deletes mail
	 *                                        alias(es) to/from mailname
	 *     -mgroups           <add|del>:<list1[,list2]> adds or removes mail name
	 *                                        to/from mail group
	 *     -redirect          <true|false>    switches mail redirect on/off
	 *     -rediraddr         <addr>          sets redirect to address (required if
	 *                                        redirect is enabled)
	 *     -group             <true|false>    switches mail group on/off
	 *     -groupmem          <add|del>:<addr1[,addr2]> adds/removes address(-es)
	 *                                        to/from mail group
	 *     -repo              <add|del>:<file1[,file2]> adds/removes file to/from
	 *                                        attachments repository
	 *                                        [deprecated, use
	 *                                        autoresponder.sh]
	 *     -autorsp           <true|false>    switches all autoresponders on/off
	 *                                        [deprecated, use autoresponder.sh]
	 *     -autoname          <name>          autoresponder name (required for all
	 *                                        autoresponder options) [deprecated,
	 *                                        use autoresponder.sh]
	 *     -autostatus        <true|false>    switches on/off autoresponder with
	 *                                        specified name (true) [deprecated,
	 *                                        use autoresponder.sh]
	 *     -autoreq           <subj|body>:<string> or <always> defines the condition
	 *                                        for the autoresponder
	 *                                        to be activated
	 *                                        whether the
	 *                                        specified pattern is
	 *                                        encountered in the
	 *                                        subject or body, or
	 *                                        to respond always
	 *                                        [deprecated, use
	 *                                        autoresponder.sh]
	 *     -autosubj          <original|string> the subject line to be set up into
	 *                                        autoresponder ("Re: <incoming
	 *                                        subject>") or a custom string
	 *                                        [deprecated, use autoresponder.sh]
	 *     -auto_replyto      <string>        return address that will be set up
	 *                                        into the autoresponder's messages
	 *                                        [deprecated, use autoresponder.sh]
	 *     -autotext          <string>        autoresponder message text
	 *                                        [deprecated, use autoresponder.sh]
	 *     -autoatch          <add|del>:<file1[,file2]> adds/removes autoresponder
	 *                                        attachment files
	 *                                        [deprecated, use
	 *                                        autoresponder.sh]
	 *     -autofrq           <number>        defines the maximum number of
	 *                                        responses to a unique e-mail address
	 *                                        per day [deprecated, use
	 *                                        autoresponder.sh]
	 *     -autostor          <number>        defines the number of unique addresses
	 *                                        to be stored for autoresponder
	 *                                        [deprecated, use autoresponder.sh]
	 *     -autored           <addr>          defines the e-mail address to forward
	 *                                        all incoming mail to [deprecated, use
	 *                                       autoresponder.sh]
	 *     -multiple-sessions <true|false>    allow multiple sessions
	 * 
	 * Note:
	 *  For security reasons, you can transfer not encrypted passwords via environment
	 *  variable PSA_PASSWORD, by specifying the empty value in the command line for
	 *  the passwd arguments (like " -passwd ''") and setting the password value in
	 *  the PSA_PASSWORD variable.
	 *  Similarly, you can transfer the crypted password via the environment variable
	 *  PSA_CRYPTED_PASSWORD, by specifying the empty value in the command line for
	 *  the passwd arguments (like " -passwd ''") and by setting the password value in
	 *  the PSA_CRYPTED_PASSWORD variable.
	 * 
	 * Version: psa v7.5.0_build75041208.07 os_SuSE 9.1
	 *
	 * 		mail.sh --info account@domain.com
	 * 		Mailname:           account
	 * 		Domain:             domain.com
	 * 		Alias(es):          Firstname.Lastname
	 * 		CP Access:          true
	 * 		Mailbox:            true
	 * 		Password:           geheim
	 * 		Password type:      plain
	 * 		Mbox quota:         Unlimited
	 * 		Redirect:           false
	 * 		Mailgroup:          false
	 * 		File repository:    Empty
	 * 		Autoresponder:      false
	 * 		Antivirus mail
	 * 		checking:           Disabled
	 * 		
	 * 		SUCCESS: Gathering information for 'account@domain.com' complete
	 * 		
	 * 		mail.sh --info bogus@domain.com
	 * 		An error occured during getting mailname information: Mailname 'bogus@domain.com' doesn't exists
	 * 
	 * @param string $action 'info', 'create', 'update' or 'remove'
	 * @param string/int $account account_lid or numerical account_id
	 * @param string $password=null string with password or null to not change
	 * @param array $aliases=null array with aliases or null to not change the aliases
	 * @param array $forwards=null array of email address to forward or null to not change
	 * @param boolean $keepLocalCopy=null if forwarding keep a local copy or not, null = dont change
	 * @param int $quota_kb=null mailbox quota in kb
	 * @return boolean/array array with returned values or false otherwise, error-message in $this->error
	 */
	function plesk_mail($action,$account,$password=null,$aliases=null,$forwards=null,$keepLocalCopy=null,$quota_kb=null)
	{
		//echo "<p>smtpplesk::plesk_mail('$action','$account','$password',".print_r($aliases,true).",".print_r($forwards,true).",".(is_null($keepLocalCopy)?'':(int)$keepLocalCopy).",$quota_kb)</p>\n";

		$this->error = false;

		if (is_numeric($account))
		{
			$account_lid = $GLOBALS['egw']->accounts->id2name($account);
		}
		elseif ($GLOBALS['egw']->accounts->name2id($account))
		{
			$account_lid = $account;
		}
		if (!$account_lid)
		{
			$this->error = lang("Account '%1' not found !!!",$account);
			return false;
		}
		if (!in_array($action,array('info','create','update','remove')))
		{
			$this->error = lang("Unsupported action '%1' !!!",$action);
			return false;
		}
		$defaultDomain = $this->profileData['defaultDomain'] ? $this->profileData['defaultDomain'] :
			$GLOBALS['egw_info']['server']['mail_suffix'];

		if ($action == 'update' && !($info = $this->plesk_mail('info',$account)))
		{
			$action = 'create';	// mail-account does not yet exist --> create it
		}
		$localEmail = $account_lid.'@'.$defaultDomain;
		$script = $this->psa_mail_script . ' --'.$action . ' ' . $localEmail;

		if ($action != 'info')
		{
			// invalidate our cache
			$GLOBALS['egw']->session->appsession('plesk-email-'.$account_lid,'emailadmin',false);

			// we dont set passwords shorten then 5 chars, as it only give an error in plesk
			if (!is_null($password) && $password)
			{
				if (strlen($password) < 5 || strpos($password,$account_lid) !== false)
				{
					$this->error = lang('Plesk requires passwords to have at least 5 characters and not contain the account-name --> password NOT set!!!');
				}
				else
				{
					$script .= ' -passwd \''.str_replace('\'','\\\'',$password).'\' -passwd_type plain';
				}
			}
			if ($action == 'create' || !is_null($forwards) || !is_null($keepLocalCopy))
			{
				$script .= ' -mailbox '.(!$forwards || $keepLocalCopy ? 'true' : 'false');
			}
			// plesk allows only one forwarding address, we ignore everything but the first
			if (!is_null($forwards) && (!$forwards || $this->is_email($forwards[0])))
			{
				$script .= ' -redirect '.(!$forwards ? 'false' : 'true -rediraddr '.$forwards[0]);
			}
			if ($action == 'update')
			{
				if (!is_null($aliases))
				{
					$existing_aliases = explode(' ',$info['Alias(es)']);	// without domain!
					$delete_aliases = array();
					foreach($existing_aliases as $alias)
					{
						if ($alias && !in_array($alias,$aliases) && !in_array($alias.'@'.$defaultDomain,$aliases))
						{
							$delete_aliases[] = $alias;
						}
					}
					if ($delete_aliases)
					{
						$script .= ' -aliases del:'.implode(',',$delete_aliases);
					}
					foreach($aliases as $n => $alias)
					{
						if (in_array($alias,$existing_aliases) || in_array(str_replace('@'.$defaultDomain,'',$alias),$existing_aliases))
						{
							unset($aliases[$n]);	// no change
						}
					}
				}
			}
			if (!is_null($aliases) && count($aliases))
			{
				foreach($aliases as $alias)
				{
					if (!$this->is_email($alias)) return false;	// security precausion
				}
				$script .= ' -aliases add:'.str_replace('@'.$defaultDomain,'',implode(',',$aliases));
			}
			if (!is_null($quota_kb) && (int)$quota_kb)
			{
				$script .= ' -mbox_quota '.(int)$quota_kb;
			}
		}
		//echo "<p>$script</p>\n";
		if (!($fp = popen($script.' 2>&1','r')))
		{
			$this->error = lang("Plesk mail script '%1' not found !!!",$this->psa_mail_script);
			return false;
		}
		$values = array();
		while(!feof($fp))
		{
			$line = trim(fgets($fp));
			list($name,$value) = preg_split('/: */',$line,2);
			if (!is_null($value) && strpos($name,'An error occured') === false && $name)
			{
				$values[$name] = $value;
			}
			elseif ($line)
			{
				$values[] = $line;
			}
		}
		pclose($fp);

		if (!$values['SUCCESS'])
		{
			$this->error = implode(', ',$values);
			return false;
		}
		return $values;
	}
	
	/**
	 * checks for valid email addresse (local mail address dont need a domain!)
	 *
	 * Important as we run shell scripts with the address and one could try to run arbitrary commands this way!!!
	 * We only allow letters a-z, numbers and the following other chars: _ . - @
	 *
	 * @return boolean
	 */
	function is_email($email)
	{
		return preg_match('/^[@a-z0-9_.-]+$/i',$email);
	}
}
