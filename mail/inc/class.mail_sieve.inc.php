<?php
/**
 * EGroupware - Mail - interface class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Hadi Nategh [hn@egroupware.org]
 * @copyright (c) 2013-16 by Hadi Nategh <hn-AT-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Mail;

class mail_sieve
{
	var $public_functions = array(
		'editVacation'	=> True,
		'index'			=> True,
		'edit'			=> True,
		'editEmailNotification'=> True, // Added email notifications
	);

	var $errorStack;

	/**
	 * Current Identitiy
	 *
	 * @var String
	 */
	var $currentIdentity;

	/**
	 * user has admin right to emailadmin
	 *
	 * @var	boolean
	 */
	var $mail_admin = false;

	/**
	 * account object
	 *
	 * @var Mail\Account
	 */
	var $account;

	/**
	 * flag to check if vacation is called from admin
	 *
	 * @var boolean
	 */
	var $is_admin_vac = false;

	/**
	 * siteConfigs
	 *
	 * @var array
	 */
	var $mailConfig = array();

	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->displayCharset = Api\Translation::charset();
		$this->mail_admin = isset($GLOBALS['egw_info']['user']['apps']['admin']);
		$this->mailConfig = Api\Config::read('mail');

		$acc_id = isset($_GET['acc_id']) ? (int)$_GET['acc_id'] : Api\Cache::getSession(__CLASS__, 'acc_id');
		if ($acc_id > 0)
		{
			$this->account = Mail\Account::read($acc_id);
			$identity = Mail\Account::read_identity($this->account->ident_id,true);
			$this->currentIdentity = Mail::generateIdentityString($identity,false);
		}

		$this->restoreSessionData();
	}

	/**
	 * Sieve rules list
	 *
	 * @param {array} $content
	 * @param {string} $msg
	 */
	function index(array $content=null,$msg=null)
	{
		if (!is_array($content))
		{
			Api\Cache::setSession(__CLASS__, 'acc_id', $this->account->acc_id);
		}
		//Instantiate an eTemplate object
		$tmpl = new Etemplate('mail.sieve.index');

		if ($msg)
		{
			$content['msg'] = $msg;
		}

		if ($this->account->acc_sieve_enabled)
		{
			//Initializes the Grid contents
			$content['rg']= $this->get_rows();

			// Set content-menu actions
			$tmpl->setElementAttribute('rg', 'actions',$this->get_actions());

			$sel_options = array(
				'status' => array(
					'ENABLED' => lang('Enabled'),
					'DISABLED' => lang('Disabled'),
				)
			);
		}
		else
		{
			$content['msg'] = lang('error').':'.lang('Serverside Filterrules (Sieve) are not activated').'. '.lang('Please contact your Administrator to validate if your Server supports Serverside Filterrules, and how to enable them in EGroupware for your active Account (%1) with ID:%2.',$this->currentIdentity,$this->account->acc_id);
			$content['hideIfSieveDisabled']='mail_DisplayNone';
		}
		$tmpl->exec('mail.mail_sieve.index',$content,$sel_options,array());
	}

	/**
	 * Email Notification Edit
	 *
	 *
	 * @param {array} $content
	 * @param {string} $msg
	 */
	function editEmailNotification($content=null, $msg='')
	{
		//Instantiate an eTemplate object, representing sieve.emailNotification
		$eNotitmpl = new Etemplate('mail.sieve.emailNotification');

		if ($this->account->acc_sieve_enabled)
		{
			$eNotification = $this->getEmailNotification();

			if (!is_array($content))
			{
				$content = $eNotification;

				if (!empty($eNotification['externalEmail']))
				{
					$content['externalEmail'] = explode(",",$eNotification['externalEmail']);
				}
			}
			else
			{
				$this->restoreSessionData();
				$button = @key($content['button']);
				unset ($content['button']);

				switch($button)
				{
					case 'save':
					case 'apply':
						if (isset($content['status']))
						{
							$newEmailNotification = $content;
							if (empty($this->mailConfig['prefpreventforwarding']) ||
								$this->mailConfig['prefpreventforwarding'] == 0 )
							{
								if (is_array($content['externalEmail']) && !empty($content['externalEmail']))
								{
									$newEmailNotification['externalEmail'] = implode(",",$content['externalEmail']);
								}
							}
						}
						if (isset($content['externalEmail']) && !empty($content['externalEmail']))
						{
							if (!$this->account->imapServer()->setEmailNotification($newEmailNotification))
							{
								$msg = lang("email notification update failed")."<br />";
								break;
							}
							else
							{
								$msg .= lang("email notification successfully updated!");
							}
						}
						else
						{
							$msg .= lang('email notification update failed! You need to set an email address!');
							break;
						}
						Framework::refresh_opener($msg, 'mail');
						if ($button === 'apply')
						{
							break;
						}

					case 'cancel':
						Framework::window_close();
						exit;
				}
				$this->saveSessionData();
			}

			$sel_options = array(
				'status' => array(
					'on' => lang('Active'),
					'off' => lang('Deactive'),
				),
				'displaySubject' => array(
					0 => lang('No'),
					1 => lang('Yes'),
				),
			);
			$content['msg'] = $msg;
		}
		else
		{
			$content['msg'] = lang('error').':'.lang('Serverside Filterrules (Sieve) are not activated').'. '.lang('Please contact your Administrator to validate if your Server supports Serverside Filterrules, and how to enable them in EGroupware for your active Account (%1) with ID:%2.',$this->currentIdentity,$this->account->acc_id);
			$content['hideIfSieveDisabled']='mail_DisplayNone';
		}
		$eNotitmpl->exec('mail.mail_sieve.editEmailNotification', $content,$sel_options);
	}

	/**
	 * Sieve rules edit
	 *
	 * @param {array} $content
	 */
	function edit ($content=null)
	{
		//Instantiate an eTemplate object, representing sieve.edit template
		$etmpl = new Etemplate('mail.sieve.edit');
		$etmpl->setElementAttribute('action_folder_text','searchOptions', array('noPrefixId'=> true));
		if (!is_array($content))
		{
			if ( $this->getRules($_GET['ruleID']) && isset($_GET['ruleID']))
			{

				$rules = $this->rulesByID;
				$content= $rules;
				$content ['ruleID'] = $_GET['ruleID'];
				switch ($rules['action'])
				{
					case 'folder':
						$content['action_folder_text'] = $rules['action_arg'];

						break;
					case 'address':

						$content['action_address_text'] = explode(',', $rules['action_arg']);
						break;
					case 'reject':
						$content['action_reject_text'] = $rules['action_arg'];
						break;
					case 'flags':
						$content['action_flags_list'] = explode(' ', $rules['action_arg']);
						break;
				}
				$content['anyof'] = $rules['anyof'] != 0?1:0;
			}
			else // Adding new rule
			{

				$this->getRules(null);
				$newRulePriority = count($this->rules)*2+1;
				$newRules ['priority'] = $newRulePriority;
				$newRules ['status'] = 'ENABLED';
				$readonlys = array(
					'button[delete]' => 'true',
					);
				$this->rulesByID = $newRules;
				$content = $this->rulesByID;
			}
			$this->saveSessionData();
		}
		else
		{
			$this->restoreSessionData();
			$button = @key($content['button']);

			//$ruleID is calculated by priority from the selected rule and is an unique ID
			$content['ruleID'] = $ruleID = ($this->rulesByID['priority'] -1) / 2;
            $error = 0;
            $msg = '';
			switch ($button)
			{
				case 'save':
				case 'apply':
					if($content)
					{
						unset($content['button']);

						$newRule = $content;
						$newRule['priority']	= $this->rulesByID['priority'];
						$newRule['status']	= $this->rulesByID['status'];

						switch ($content['action'])
						{
							case 'folder':
								$newRule['action_arg'] = $content['action_folder_text'];
								break;
							case 'address':
								$content['action_address_text'] = self::strip_rfc882_addresses($content['action_address_text']);
								$newRule['action_arg'] = implode(',', $content['action_address_text']);
								break;
							case 'reject':
								$newRule['action_arg'] = $content['action_reject_text'];
								break;
							case 'flags':
								$newRule['action_arg'] = implode(' ', $content['action_flags_list']);
								break;
						}
						unset($newRule['action_folder_text']);
						unset($newRule['action_address_text']);
						unset($newRule['action_reject_text']);
						unset($newRule['action_flags_list']);

						$newRule['flg'] = 0 ;
						if( $newRule['continue'] ) { $newRule['flg'] += 1; }
						if( $newRule['gthan'] )    { $newRule['flg'] += 2; }
						if( $newRule['anyof'] )    { $newRule['flg'] += 4; }
						if( $newRule['keep'] )     { $newRule['flg'] += 8; }
						if( $newRule['regexp'] )   { $newRule['flg'] += 128; }

						if($newRule['action'] && $this->rulesByID['priority'])
						{
							$this->rules[$ruleID] = $newRule;
							$ret = $this->account->imapServer()->setRules($this->rules);
							if (!$ret && !empty($this->account->imapServer()->error))
							{
								$msg .= lang("Saving the rule failed:")."<br />".$this->account->imapServer()->error."<br />";
							}
							else
							{
								$msg .= lang("The rule with priority %1 successfully saved!",$ruleID);
							}
							$this->saveSessionData();
						}
						else
						{
							$msg .= "\n".lang("Error: Could not save rule").' '.lang("No action defined!");
							$error++;
						}
					}
					else
					{
						$msg .= "\n".lang("Error: Could not save rule").' '.lang("No action defined!");
						$error++;
					}
					Framework::refresh_opener($msg, 'mail', 'sieve');
					if ($button == "apply")
					{
						break;
					}
				//fall through

				case 'delete':
					if ($button == "delete")
					{
						if ($ruleID === count($this->rules)-1)
						{
							$msg = lang('rule with priority ') . $ruleID . lang(' deleted!');
						}
						else
						{

							$msg = lang('rule with priority ') . $ruleID . lang(' deleted!') . lang(' And the rule with priority %1, now got the priority %2',$ruleID+1,$ruleID);
						}
						unset($this->rules[$ruleID]);
						$this->rules = array_values($this->rules);
						$this->updateScript();
					}
					Framework::refresh_opener($msg, 'mail', 'sieve');

				case 'cancel':
					Framework::window_close();
					exit;
			}
		}
		$sel_options = array(//array_merge($sel_options,array(
			'anyof' => array(
				0 => lang('all of'),
				1 => lang('any of'),
			),
			'gthan' => array(
				0 => lang('less than'),
				1 => lang('greater than'),
			),
			'bodytransform' => array(
				0 => 'raw',
				1 => 'text',
			),
			'ctype' => Mail\Script::$btransform_ctype_array,

		);

		// No forward should be applied regardless of content/rules
		$content['no_forward'] = $this->account->acc_smtp_type !== Api\Mail\Smtp::class && !$this->account->acc_user_forward;

		//Set the preselect_options for mail/folders as we are not allow free entry for folder taglist
		if (!empty($content['action_folder_text']))
		{
			$sel_options['action_folder_text'] = [$content['action_folder_text'] => $content['action_folder_text']];
		}
		return $etmpl->exec('mail.mail_sieve.edit',$content,$sel_options,$readonlys,array(),2);
	}

	/**
	 * Read email notification script from the sieve script from server
	 *
	 *
	 * @return type, returns array of email notification data, and in case of failure returns false
	 * @todo Need to be checked if it is still needed
	 */
	function getEmailNotification()
	{
		if(!(empty($this->mailConfig['prefpreventnotificationformailviaemail']) || $this->mailConfig['prefpreventnotificationformailviaemail'] == 0))
		{
			throw new Api\Exception\NoPermission();
		}

		try {
			$emailNotification = $this->account->imapServer()->getEmailNotification();
		}
		catch(Exception $e) {
			unset($e);
			$emailNotification = array();
		}
		return $emailNotification;
	}

	/**
	 * Fetch Vacation rules and predefined Addresses from mailserver
	 *
	 * @param string $accountID
	 *
	 * @return array return multi-dimensional array of vacation and aliases
	 */
	function getVacation($accountID = null)
	{
		if(!(empty($this->mailConfig['prefpreventabsentnotice']) || $this->mailConfig['prefpreventabsentnotice'] == 0))
		{
			throw new Api\Exception\NoPermission();
		}
		try {
			if ($this->is_admin_vac)
			{
				$icServer = $this->account->imapServer($accountID);
				$vacation = $icServer->getVacationUser($accountID);
			}
			else
			{
				$icServer = $this->account->imapServer();
				$icServer->retrieveRules(null);
				$vacation = $icServer->getVacation();
			}
		}
		catch(Exception $e)
		{
			Framework::window_close(lang($e->getMessage()));
		}
		if (is_null($accountID)) $accountID = $GLOBALS['egw_info']['user']['account_id'];

		$account_email =  Api\Accounts::id2name($accountID, 'account_email');
		$accAllIdentities = $this->account->smtpServer()->getAccountEmailAddress(Api\Accounts::id2name($accountID));
		$allAliases = $this->account->ident_email !=''?
				// Fix ident_email with no domain part set
				array(Mail::fixInvalidAliasAddress($account_email, $this->account->ident_email))
				: array();
		foreach ($accAllIdentities as &$val)
		{
			if ($val['type'] !='default')
			{
				// if the alias has no domain part set try to add
				// default domain extracted from ident_email address
				$allAliases[] =  Mail::fixInvalidAliasAddress($account_email, $val['address']);
			}
		}
		// try to fix already stored aliases
		foreach ($vacation['addresses'] as &$address)
		{
			$address = Mail::fixInvalidAliasAddress($account_email, $address);
		}
		asort($allAliases);
		return array(
			'vacation' =>$vacation,
			'aliases' => array_values($allAliases),
		);
	}

	/**
	 * Vacation edit
	 *
	 * @param {array} $content
	 * @param {string} $msg
	 */
	function editVacation($content=null, $msg='')
	{
		//Instantiate an eTemplate object, representing the sieve.vacation template
		$vtmpl = new Etemplate('mail.sieve.vacation');
		$vacation = array();

		if (isset($_GET['account_id'])) $account_id = $preserv['account_id'] = $_GET['account_id'];

		if (isset($content['account_id']))
		{
			$account_id = $content['account_id'];
			$preserv['acc_id'] = $content['acc_id'];
		}
		if(isset($account_id) && $this->mail_admin)
		{
			foreach(Mail\Account::search($account_id, false, null, false, 0, false) as $account)
			{
				try {
					// check if account is valid for multiple users, has admin credentials and sieve enabled
					if (Mail\Account::is_multiple($account) &&
						($icServer = $account->imapServer(true)) &&	// check on icServer object, so plugins can overwrite
						$icServer->acc_imap_admin_username && $icServer->acc_sieve_enabled)
					{
						$allAccounts[$account->acc_id] = $account->acc_name;
						$accounts[$account->acc_id] = $account;
					}
				}
				catch(Exception $e) {
					unset($e);
					// ignore broken accounts
				}
			}

			$profileID = !isset($content['acc_id']) ? key($accounts):$content['acc_id'];
			if (isset($_GET['acc_id']) && isset($allAccounts[$_GET['acc_id']]))
			{
				$profileID = $content['acc_id'] = (int)$_GET['acc_id'];
			}

			//Chooses the right account
			$this->account = $accounts[$profileID];

			$this->is_admin_vac = true;
			$preserv['account_id'] = $account_id;
		}
		elseif(!is_array($content) && isset($_GET['acc_id']))
		{
			$this->account = Mail\Account::read($_GET['acc_id']);
			$preserv['acc_id'] = $this->account->acc_id;
		}
		elseif ($content['acc_id'])
		{
			$this->account = Mail\Account::read($content['acc_id']);
			$preserv['acc_id'] = $content['acc_id'];
		}

		$icServer = $this->account->imapServer($this->is_admin_vac ? $account_id : false);

		if ($icServer->acc_sieve_enabled)
		{
			$vacRules = $this->getVacation($account_id);
			if ($vacRules['vacation']===false)
			{
				$content['msg'] = lang('error').':'.lang('Serverside Vacationnotice (via Sieve) are not activated').'. '.
					lang('Please contact your Administrator to validate if your Server supports Serverside Vacationmessages, and how to enable them in EGroupware for your active Account (%1) with ID:%2.',$this->currentIdentity,$icServer->ImapServerId);
				$content['hideIfSieveDisabled']='mail_DisplayNone';
			}
			else
			{
				if ($icServer->acc_imap_administration || (!empty($icServer->getExtensions()) && in_array('DATE', $icServer->getExtensions())))
				{
					$ByDate = array('by_date' => lang('By date'));
				}
				if (!is_array($content) || ($content['acc_id'] && !isset($content['button'])) || (strlen(trim($content['text']))==0 && in_array($content['status'],array('on','by_date'))))
				{
					$content = $vacation = $vacRules['vacation'];
					if (!empty($profileID)) $content['acc_id'] = $profileID;
					if (empty($vacation['addresses']) || implode('',$vacation['addresses']) == '')
					{
						$content['addresses'] = $vacRules['aliases'];
					}
					if (!empty($vacation['forwards']))
					{
						$content['forwards'] = explode(",",$vacation['forwards']);
					}
					else
					{
						$content['forwards'] = '';
					}
					if (strlen(trim($vacation['text']))==0 && $this->mailConfig['default_vacation_text']) $content['text'] = $this->mailConfig['default_vacation_text'];
					if (strlen(trim($content['text']))==0)
					{
						$content['msg'] = $msg = lang('error').': '.lang('No vacation notice text provided. Please enter a message.');
						Framework::refresh_opener($msg, 'mail');
					}
					//Set default value for days new entry
					if (empty($content['days']))
					{
						$content['days'] = '3';
					}
					$preserv['is_admin_vac'] = $content['is_admin_vac'] = $this->is_admin_vac;
				}
				else
				{
					$this->restoreSessionData();
					$button = @key($content['button']);
					unset ($content['button']);

					switch($button)
					{
						case 'save':
						case 'apply':
							if ($GLOBALS['egw_info']['user']['apps']['admin'])
							{
								// store text as default
								if ($content['set_as_default'] == 1 && $content['text'])
								{
									Api\Config::save_value('default_vacation_text', $content['text'], 'mail');
								}
							}
							if (isset($content['status']))
							{
								//error_log(__METHOD__. 'content:' . array2string($content));
								$newVacation = $content;

								if (empty($this->mailConfig['prefpreventforwarding']) ||
									$this->mailConfig['prefpreventforwarding'] == 0 )
								{
									$content['forwards'] = self::strip_rfc882_addresses($content['forwards']);
									$newVacation['forwards'] = implode(',', $content['forwards']);
								}
								else
								{
									unset($newVacation ['forwards']);
								}

								if (!in_array($newVacation['status'],array('on','off','by_date')))
								{
									$newVacation['status'] = 'off';
								}

								$checkAddresses = isset($content['check_mail_sent_to']) && $content['check_mail_sent_to'] != 0;
								if ($content['addresses'])
								{
									$newVacation ['addresses'] = $content['addresses'] =
										self::strip_rfc882_addresses($content['addresses']);
								}

								if($this->checkRule($newVacation,$checkAddresses))
								{
									if (isset($account_id) && $this->mail_admin)
									{
										$resSetvac = $icServer->setVacationUser($account_id, $newVacation, $this->scriptName);
									}
									else
									{
										$resSetvac = $icServer->setVacation($newVacation);
									}

									if (!$resSetvac)
									{
										$msg = lang('vacation update failed') . "\n" . lang('Vacation notice update failed') . ":" . $this->account->imapServer()->error;
										break;
									}
									// schedule job to switch message on/off, if request and not already in past
									else
									{
										if ($newVacation['status'] == 'by_date' && $newVacation['end_date']+24*3600 > time() ||
											$vacRules && $vacRules['vacation']['status'] == 'by_date')
										{
											self::setAsyncJob($newVacation);
										}
										//Reset vacationNotice cache which is used in mail_ui get_rows
										if (isset($account_id) && $this->mail_admin)
										{
											$account_lid = Api\Accounts::id2name($account_id,'account_lid');
											$cachedVacations = array($icServer->acc_id => $newVacation) + (array)Api\Cache::getCache(Api\Cache::INSTANCE, 'email', 'vacationNotice'.$account_lid);
											//error_log(__METHOD__.__LINE__.' Setting Cache for '.$account_lid.':'.array2string($cachedVacations));
											Api\Cache::setCache(Api\Cache::INSTANCE,'email', 'vacationNotice'.$account_lid, $cachedVacations);
										}
										else
										{
											$cachedVacations = array($icServer->acc_id => $newVacation) + (array)Api\Cache::getCache(Api\Cache::INSTANCE, 'email', 'vacationNotice'.$GLOBALS['egw_info']['user']['account_lid']);
											//error_log(__METHOD__.__LINE__.' Setting Cache for own ('.$GLOBALS['egw_info']['user']['account_lid'].'):'.array2string($cachedVacations));
											Api\Cache::setCache(Api\Cache::INSTANCE,'email', 'vacationNotice'.$GLOBALS['egw_info']['user']['account_lid'], $cachedVacations);
										}
										$msg = lang('Vacation notice sucessfully updated.');
									}
								}
								else
								{
									$msg .= implode("\n",$this->errorStack);
								}
								// refresh vacationNotice on index
								$response = Api\Json\Response::get();
								$response->call('app.mail.mail_callRefreshVacationNotice',$icServer->ImapServerId);
								Framework::refresh_opener($msg, 'mail');
								if ($button === 'apply' || $icServer->error !=="")
								{
									break;
								}
							}

						case 'cancel':
							Framework::window_close();
					}
				}

				$sel_options = array(
					'status' => array(
						'on' => lang('Active'),
						'off' => lang('Deactive'),
					),
					'addresses' => array_combine($vacRules['aliases'],$vacRules['aliases']),
				);
				if (!isset($account_id))
				{
					$readonlys['acc_id'] = true;
				}
				else
				{
					$sel_options['acc_id'] = $allAccounts;
				}
				if (!empty($ByDate))
				{
					$sel_options['status'] += $ByDate;
				}
				if (!isset($GLOBALS['egw_info']['user']['apps']['admin']))
				{
					$content['is_not_admin_user'] = true;
					$readonlys['set_as_default'] = true;
				}
				$content['msg'] = $msg;
			}
		}
		else
		{
			$content['msg'] = lang('error').':'.lang('Serverside Filterrules (Sieve) are not activated').'. '.lang('Please contact your Administrator to validate if your Server supports Serverside Filterrules, and how to enable them in EGroupware for your active Account (%1) with ID:%2.',$this->currentIdentity,$icServer->ImapServerId);
			$content['hideIfSieveDisabled']='mail_DisplayNone';
		}
		$vtmpl->exec('mail.mail_sieve.editVacation',$content,$sel_options,$readonlys,$preserv,2);
	}

	/**
	 * Strip personal part from rfc822 addresses: "Ralf Becker <rb@egroupware.org>" --> rb@egroupware.org
	 *
	 * Sieve only allows email-addresses, without angle brakets and personal parts.
	 *
	 * @param array|string $_addresses
	 * @return array of email-addresses without personal part
	 */
	static function strip_rfc882_addresses($_addresses)
	{
		$addresses = array();
		foreach(Mail::parseAddressList($_addresses) as $addr)
		{
			if ($addr->valid)
			{
				$addresses[] = $addr->mailbox.'@'.$addr->host;
			}
		}
		return $addresses;
	}

	/**
	 * set the asyncjob for a timed vacation
	 *
	 * @param array $_vacation vacation to set/unset with values for 'account_id', 'acc_id' and vacation stuff
	 * @param boolean $_reschedule do nothing but reschedule the job by 3 minutes
	 * @return  void
	 */
	static function setAsyncJob (array $_vacation, $_reschedule=false)
	{
		if (!($_vacation['acc_id'] > 0))
		{
			throw new Api\Exception\WrongParameter('No acc_id given!');
		}
		// setting up an async job to enable/disable the vacation message
		$async = new Api\Asyncservice();
		if (empty($_vacation['account_id'])) $_vacation['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
		$async_id = !empty($_vacation['id']) ? $_vacation['id'] : 'mail-vacation-'.$_vacation['account_id'];
		$async->delete($async_id);

		$end_date = $_vacation['end_date'] + 24*3600;   // end-date is inclusive, so we have to add 24h
		if ($_vacation['status'] == 'by_date' && time() < $end_date && !$_reschedule)
		{
			$time = time() < $_vacation['start_date'] ? $_vacation['start_date'] : $end_date;
			$async->set_timer($time,$async_id, 'mail_sieve::async_vacation', $_vacation, $_vacation['account_id']);
		}
		if ($_reschedule)
		{
			$_vacation['rescheduled'] = $_vacation['rescheduled'] ? 2*$_vacation['rescheduled'] : 5;
			// only try to reschedule for 2 days max
			if ($_vacation['rescheduled'] <= 2 * 24 * 60)
			{
				$time = time() + 60*($_vacation['rescheduled']);
				unset($_vacation['next']);
				unset($_vacation['times']);
				$async->set_timer($time, $async_id, 'mail_sieve::async_vacation', $_vacation, $_vacation['account_id']);
			}
		}
 	}

	/**
	 * Callback for the async job to enable/disable the vacation message
	 *
	 * @param array $_vacation
	 * @throws Api\Exception\NotFound if mail account is not found
	 */
	static function async_vacation(array $_vacation)
	{
		//error_log(__METHOD__.'('.array2string($_vacation).')');

		$account = Mail\Account::read($_vacation['acc_id'], $_vacation['account_id']);
		$icServer = $account->imapServer($_vacation['account_id']);

		//error_log(__METHOD__.'() imap username='.$icServer->acc_imap_username);

		try
		{
			$ret = $icServer->setVacationUser($_vacation['account_id'], $_vacation);
			self::setAsyncJob($_vacation);
		}
		// if mail account no longer exists --> remove async job
		catch (Api\Exception\NotFound $e)
		{
			$_vacation['status'] = 'off';
			self::setAsyncJob($_vacation);
		}
		catch (Exception $e) {
			error_log(__METHOD__.'('.array2string($_vacation).' failed '.$e->getMessage());
			self::setAsyncJob($_vacation, true);	// reschedule
			$ret = false;
		}

		return $ret;
	}

	/**
	 * Checking vaction validation
	 *
	 * @param {array} $_vacation
	 * @param {boolean} $_checkAddresses
	 *
	 * @return boolean
	 */
	function checkRule($_vacation,$_checkAddresses=true)
	{
		$this->errorStack = array();

		if (!$_vacation['text'])
		{
			$this->errorStack['text'] = lang('Please supply the message to send with auto-responses').'!	';
		}

		if (!$_vacation['days'])
		{
			$this->errorStack['days'] = lang('Please select the number of days to wait between responses').'!';
		}

		if(is_array($_vacation['addresses']) && !empty($_vacation['addresses']))
		{
			$regexp="/^[a-z0-9]+([_\\.-][a-z0-9]+)*@([a-z0-9]+([\.-][a-z0-9]+)*)+\\.[a-z]{2,}$/i";
			foreach ($_vacation['addresses'] as $addr)
			{
				if (!preg_match($regexp,$addr) && $_checkAddresses)
				{
					$this->errorStack['addresses'] = lang('One address is not valid').'!';
				}
			}
		}
		else
		{
			$this->errorStack['addresses'] = lang('Please select a address').'!';
		}
		if ($_vacation['status'] == 'by_date')
		{
			if (!$_vacation['start_date'] || !$_vacation['end_date'])
			{
				$this->errorStack['status'] = lang('Activating by date requires a start- AND end-date!');
			}
			elseif($_vacation['start_date'] > $_vacation['end_date'])
			{
				$this->errorStack['status'] = lang('Vacation start-date must be BEFORE the end-date!');
			}
		}
		if ($_vacation['forwards'])
		{
			foreach(preg_split('/, ?/',$_vacation['forwards']) as $addr)
			{
				if (!preg_match($regexp,$addr) && $_checkAddresses)
				{
					$this->errorStack['forwards'] = lang('One address is not valid'.'!');
				}
			}
		}
		//error_log(__METHOD__. array2string($this->errorStack));
		if(count($this->errorStack) == 0)
		{
			return true;
		}
		else
		{
			$this->errorStack['message'] = lang('Vacation notice is not saved yet! (But we filled in some defaults to cover some of the above errors. Please correct and check your settings and save again.)');
			return false;
		}
	}

	/**
	 * Move rule to an other position in list
	 *
	 * @param {array} $orders
	 */
	function ajax_moveRule($exec_id, $orders)
	{

		foreach ($orders as $keys => $val)
		{
			$orders[$keys] = $val -1;
		}

		$this->getRules(null);

		$newrules = $this->rules;

		foreach($orders as $keys => $ruleID)
		{
			$newrules[$keys] = $this->rules[$ruleID];
		}

		$this->rules = $newrules;
		$this->updateScript();
		$this->saveSessionData();

		//Calling to referesh after move action
		$response = Api\Json\Response::get();
		$response->call('app.mail.sieve_refresh');
	}

	/**
	 * Ajax function to handle actions over sieve rules list on gd
	 *
	 * @param string $action name of action
	 * @param string $checked the selected rule id
	 * @param string $msg containing the message comming from the client-side
	 *
	 */
	function ajax_action($action, $checked, $msg)
	{
		$this->getRules(null);

		switch ($action)
		{
			case 'delete':
				if ($checked === count($this->rules)-1)
				{
					$msg = lang('rule with priority ') . $checked . lang(' deleted!');
				}
				else
				{

					$msg = lang('rule with priority ') . $checked . lang(' deleted!') . lang(' And the rule with priority %1, now got the priority %2',$checked+1,$checked);
				}
				unset($this->rules[$checked]);
				$this->rules = array_values($this->rules);
				break;
			case 'enable':
				$msg = lang('rule with priority ') . $checked . lang(' enabled!');
				$this->rules[$checked]['status'] = 'ENABLED';
				break;
			case 'disable':
				$msg = lang('rule with priority ') . $checked . lang(' disabled!');
				$this->rules[$checked]['status'] = 'DISABLED';
				break;
			case 'move':
				break;
		}

		ob_start();
		$result = $this->updateScript();

		$response = Api\Json\Response::get();

		if($result)
		{
			$response->message($result);
			return;
		}
		$this->saveSessionData();

		$response->call('app.mail.sieve_refresh');
	}

	/**
	 * Convert an script seive format rule to human readable format
	 *
	 * @param {array} $rule Array of rules
	 * @return {string}  return the rule as a string.
	 */
	function buildRule($rule)
	{
		$andor = ' '. lang('and') .' ';
		$started = 0;
		if ($rule['anyof'])
		{
			$andor = ' '. lang('or') .' ';
		}
		$complete = lang('IF').' ';
		if ($rule['unconditional'])
		{
			$complete = "[Unconditional] ";
		}
		if ($rule['from'])
		{
			$match = $this->setMatchType($rule['from'],$rule['regexp']);
			$complete .= "'From:' " . $match . " '" . $rule['from'] . "'";
			$started = 1;
		}
		if ($rule['to'])
		{
			if ($started)
			{
				$complete .= $andor;
			}
			$match = $this->setMatchType($rule['to'],$rule['regexp']);
			$complete .= "'To:' " . $match . " '" . $rule['to'] . "'";
			$started = 1;
		}
		if ($rule['subject'])
		{
			if ($started)
			{
				$complete .= $andor;
			}
			$match = $this->setMatchType($rule['subject'],$rule['regexp']);
			$complete .= "'Subject:' " . $match . " '" . $rule['subject'] . "'";
			$started = 1;
		}
		if ($rule['field'] && $rule['field_val'])
		{
			if ($started)
			{
				$complete .= $andor;
			}
			$match = $this->setMatchType($rule['field_val'],$rule['regexp']);
			$complete .= "'" . $rule['field'] . "' " . $match . " '" . $rule['field_val'] . "'";
			$started = 1;
		}
		if ($rule['size'])
		{
			$xthan = " less than '";
			if ($rule['gthan'])
			{
				$xthan = " greater than '";
			}
			if ($started)
			{
				$complete .= $andor;
			}
			$complete .= "message " . $xthan . $rule['size'] . "KB'";
		}
		if (!empty($rule['field_bodytransform']))
		{
			$btransform	= " :raw ";
			$match = ' :contains';
			if ($rule['bodytransform'])
			{
				$btransform = " :text ";
			}
			if (preg_match("/\*|\?/", $rule['field_bodytransform']))
			{
				$match = ':matches';
			}
			if ($rule['regexp'])
			{
				$match = ':regex';
			}
			$complete .= " body " . $btransform . $match . " \"" . $rule['field_bodytransform'] . "\"";
		}
		if ($rule['ctype']!= '0' && !empty($rule['ctype']))
		{
			$btransform_ctype = Mail\Script::$btransform_ctype_array[$rule['ctype']];
			$ctype_subtype = "";
			if ($rule['field_ctype_val'])
			{
				$ctype_subtype = "/";
			}
			$complete .= " body :content " . " \"" . $btransform_ctype . $ctype_subtype . $rule['field_ctype_val'] . "\"" . " :contains \"\"";
			//error_log(__CLASS__."::".__METHOD__.array2string(Mail\Script::$btransform_ctype_array));
		}
		if (!$rule['unconditional'])
		{
			$complete .= ' '.lang('THEN').' ';
		}
		if (preg_match("/folder/i",$rule['action']))
		{
			$complete .= lang('file into')." '" . $rule['action_arg'] . "';";
		}
		if (preg_match("/reject/i",$rule['action']))
		{
			$complete .= lang('reject with')." '" . $rule['action_arg'] . "'.";
		}
		if (preg_match("/address/i",$rule['action']))
		{
			$complete .= lang('forward to').' ' . $rule['action_arg'] .'.';
		}
		if (preg_match("/discard/i",$rule['action']))
		{
			$complete .= lang('discard').'.';
		}
		if ($rule['continue'])
		{
			$complete .= " [Continue]";
		}
		if ($rule['keep'])
		{
			$complete .= " [Keep a copy]";
		}
		return $complete;
	}

	/**
	 * Helper function to find the type of content
	 *
	 * @param {string} $matchstr string that should be compared
	 * @param {string} $regex regular expresion as pattern to be matched
	 * @return {string} return the type
	 */
	function setMatchType (&$matchstr, $regex = false)
	{
		$match = lang('contains');
		if (preg_match("/^\s*!/", $matchstr))
		{
			$match = lang('does not contain');
		}
		if (preg_match("/\*|\?/", $matchstr))
		{
			$match = lang('matches');
			if (preg_match("/^\s*!/", $matchstr))
			{
				$match = lang('does not match');
			}
		}
		if ($regex)
		{
			$match = lang('matches regexp');
			if (preg_match("/^\s*!/", $matchstr))
			{
				$match = lang('does not match regexp');
			}
		}
		if ($regex && preg_match("/^\s*\\\\!/", $matchstr))
		{
			$matchstr = preg_replace("/^\s*\\\\!/","!",$matchstr);
		}
		else
		{
			$matchstr = preg_replace("/^\s*!/","",$matchstr);
		}
		return $match;
	}

	/**
	 * Save session data
	 */
	function saveSessionData()
	{
		$sessionData['sieve_rules']		= $this->rules;
		$sessionData['sieve_rulesByID'] = $this->rulesByID;
		$sessionData['sieve_scriptToEdit']	= $this->scriptToEdit;
		Api\Cache::setSession(__CLASS__, 'sieve_session_data', $sessionData);
	}

	/**
	 * Update the sieve script on mail server
	 */
	function updateScript()
	{
		if (!$this->account->imapServer()->setRules($this->rules))
		{
			return $this->account->imapServer()->error;
		}
	}

	/**
	 * Fetched rules save on array()rules.
	 *
	 * @param {string} $ruleID  Numeric Id of the rule if specify return the specitic rule| otherwise ruleByID would be null
	 *
	 * @return {boolean} returns false in case of failure and true in case of success.
	 */
	function getRules($ruleID = null)
	{
		try {
			$this->account->imapServer()->retrieveRules();
			$this->rules	= $this->account->imapServer()->getRules();
			$this->rulesByID = $this->rules[$ruleID];
			$this->vacation	= $this->account->imapServer()->getVacation();
		}
		catch(Exception $e) {
			error_log(__METHOD__.__LINE__.$e->getMessage().': '.$e->details);
			$this->rules	= array();
			$this->rulesByID = array();
			$this->vacation	= array();
			return false;
		}
		return true;
	}

	/**
	 * Restore session data
	 */
	function restoreSessionData()
	{
		$sessionData = Api\Cache::getSession(__CLASS__, 'sieve_session_data');
		$this->rules		= $sessionData['sieve_rules'];
		$this->rulesByID = $sessionData['sieve_rulesByID'];
		$this->scriptToEdit	= $sessionData['sieve_scriptToEdit'];
	}

	/**
	 *
	 * Get the data for iterating the rows on rules list grid
 	 *
	 * @return {boolean|array} Array of rows | false if failed
	 */
	function get_rows()
	{
		$rows = array();
		$this->getRules(null);

		if (is_array($this->rules) && !empty($this->rules) )
		{
			$rows = $this->rules;

			foreach ($rows as &$row )
			{
				$row['rules'] = $this->buildRule($row);
				$row['ruleID'] =(string)(($row['priority'] -1) / 2 );
				if ($row ['status'] === 'DISABLED')
				{
					$row['class'] = 'mail_sieve_DISABLED';
				}
            }
		}
		else
		{
			//error_log(__METHOD__.'There are no rules or something is went wrong at getRules()!');
			return false;
		}
		// Shift one down, because in grid the first row is reserved for header
		array_unshift($rows,array(''=> ''));
		return $rows;
	}

	/**
	 * Get actions / context menu for index
	 *
	 * @return {array} returns defined actions as an array
	 */
	private function get_actions()
	{
		$actions =array(

			'edit' => array(
				'caption' => 'Edit',
				'default' => true,
				'onExecute' => 'javaScript:app.mail.action',
                'disableClass' => 'th'
			),
			'add' => array(
				'caption' => 'Add',
				'onExecute' => 'javaScript:app.mail.action'
			),
			'enable' => array(
				'caption' => 'Enable',
				'onExecute' => 'javaScript:app.mail.action',
				'enableClass' => 'mail_sieve_DISABLED',
                'hideOnDisabled' => true
			),
			'disable' => array(
				'caption' => 'Disable',
				'onExecute' => 'javaScript:app.mail.action',
				'disableClass' => 'mail_sieve_DISABLED',
                'hideOnDisabled' => true
			),
			'delete' => array(
				'caption' => 'Delete',
				'onExecute' => 'javaScript:app.mail.action'
			),

		);
		return $actions;
	}
	/**
	 * Callback function to get mail folders
	 * int $_searchStringLength
	 * @param boolean $_returnList
	 * @param int $_mailaccountToSearch
	 * @param boolean $_noPrefixID = false, if set to true folders name does not get prefixed by account id
	 */
	function ajax_getFolders ($_searchStringLength=2, $_returnList=false, $_mailaccountToSearch=null, $_noPrefixId=false)
	{
		$mailCompose = new mail_compose();
		if ($_REQUEST['noPrefixId']) $_noPrefixId = $_REQUEST['noPrefixId'];
		$mailCompose->ajax_searchFolder($_searchStringLength, $_returnList, $_mailaccountToSearch, $_noPrefixId);
	}
}