<?php
/**
 * EGroupware - Mail - interface class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Hadi Nategh [hn@stylite.de]
 * @copyright (c) 2013 by Hadi Nategh <hn-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.mail_ui.inc.php 42779 2013-06-17 14:25:20Z leithoff $
 */
include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate.inc.php');

class mail_sieve
{
	var $public_functions = array
		(
			'addScript'		=> True,
			'ajax_moveRules' => True,
			'deleteScript'	=> True,
			'editRule'		=> True,
			'editScript'	=> True,
			'editVacation'	=> True,
			'listScripts'	=> True,
			'index'			=> True,
			'edit'			=> True,
			'updateRules'	=> True,
			//'editEmailNotification'=> True, // Added email notifications
		);
	/**
	 * Flag if we can do a timed vaction message, requires Cyrus Admin User/Pw to enable/disable via async service
	 *
	 * @var boolean
	 */
	var $timed_vacation;
	//var $scriptName = 'felamimail';

	/**
	 * @var emailadmin_sieve
	 */
	var $bosieve;

	var $errorStack;

	var $tmpl;

	var $mailbo;

	/**
	 * Constructor
	 *
	 */

	function __construct()
	{

		if(empty($GLOBALS['egw_info']['user']['preferences']['mail']['sieveScriptName']))
		{
			$GLOBALS['egw']->preferences->add('mail','sieveScriptName','mail', 'forced');
			$GLOBALS['egw']->preferences->save_repository();
		}
		$this->scriptName = (!empty($GLOBALS['egw_info']['user']['preferences']['mail']['sieveScriptName'])) ? $GLOBALS['egw_info']['user']['preferences']['mail']['sieveScriptName'] : 'mail' ;
		$this->displayCharset	= $GLOBALS['egw']->translation->charset();
		$this->botranslation	=& $GLOBALS['egw']->translation;
		$profileID = 0;
		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
				$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
		$this->mailbo	= mail_bo::getInstance(true, $profileID);

		if (is_object($this->mailbo->mailPreferences))
		{
			// account select box
			$selectedID = $this->mailbo->getIdentitiesWithAccounts($identities);
			// if nothing valid is found return to user defined account definition
			if (empty($this->mailbo->icServer->host) && count($identities)==0 && $this->mailbo->mailPreferences->userDefinedAccounts)
			{
				// redirect to new personal account
				egw::redirect_link('/index.php',array('menuaction'=>'mail.uipreferences.editAccountData',
					'accountID'=>"new",
					'msg'   => lang("There is no IMAP Server configured.")." - ".lang("Please configure access to an existing individual IMAP account."),
				));

			}
		}
		$this->mailPreferences  =& $this->mailbo->mailPreferences;
		$this->mailConfig	= config::read('mail');

		$this->restoreSessionData();
		$icServer =& $this->mailbo->icServer;
		if(($icServer instanceof defaultimap) && $icServer->enableSieve)
		{
			$this->bosieve		=& $icServer;
			$serverclass = get_class($icServer);
			$classsupportstimedsieve = false;
			if (!empty($serverclass) && stripos(constant($serverclass.'::CAPABILITIES'),'timedsieve') !== false) $classsupportstimedsieve = true;
			$this->timed_vacation = $classsupportstimedsieve && $icServer->enableCyrusAdmin &&
			$icServer->adminUsername && $icServer->adminPassword;
		}
		else
		{
			die(lang('Sieve not activated'));
		}
	}

	/**
	 * Sieve rules list
	 *
	 * @param array $content=null
	 * @param string $msg=null
	 */
	function index(array $content=null,$msg=null)
	{

		//Initialize the Grid contents
		$tmpl = new etemplate_new('mail.sieve.index');
		//$this->restoreSessionData();
		//if (isset($_GET['rule_id'])) $ruleID = $_GET['rule_id'];
		if ($_GET['msg']) $msg = $_GET['msg'];
		_debug_array($content);

		$content['rg']= $this->get_rows();
		//$content['rules']['']
		//error_log(__METHOD__. array2string($content));
		//_debug_array($readonlys);
		_debug_array($content);

		// Set content-menu actions
		$tmpl->set_cell_attribute('rg', 'actions',$this->get_actions());

		$sel_options = array(
			'status' => array(
				'ENABLED' => lang('Enabled'),
				'DISABLED' => lang('Disabled'),
			)
		);

		//$tmpl->read('mail.sieve.index');
		//_debug_array($content);
		//_debug_array($this->rules);

		$tmpl->exec('mail.mail_sieve.index',$content,$sel_options,$readonlys);

	}

	/**
	 * Sieve rules edit
	 *
	 * @param array $content=null
	 */
	function edit ($content=null)
	{
		$etmpl = new etemplate('mail.sieve.edit');
		//error_log(__METHOD__.array2string($content));
		if (!is_array($content))
		{
			if ( $this->getRules($_GET['ruleID']) && isset($_GET['ruleID']))
			{

				$rules = $this->rulesByID;
				$content= array_merge($rules);
				_debug_array($rules);
				//$content['ruleID'] = $ruleID;
				switch ($rules['action'])
				{
					case 'folder':
						$content['action_folder_text'] = $rules['action_arg'];
						break;
					case 'address':
						$content['action_address_text'] = $rules['action_arg'];
						break;
					case 'reject':
						$content['action_reject_text'] = $rules['action_arg'];
				}

			}
			else // Adding new rule
			{

				$this->getRules();
				$newRulePriority = count($this->rules)*2+1;
				$newRules = $content;
				$newRules ['priority'] = $newRulePriority;
				$newRules ['status'] = 'ENABLED';
				$this->rulesByID = $newRules;
				_debug_array($this->rulesByID);
			}
			$this->saveSessionData();

		}
		else
		{
			$this->restoreSessionData();
			list($button) = @each($content['button']);

			switch ($button)
			{
				case 'apply':

				case 'save':
					if($content)
					{
						unset($content['button']);
						//$ruleID is calculated by priority from the selected rule and is an unique ID
						$ruleID = ($this->rulesByID['priority'] -1) / 2;
						$newRule = $content;
						$newRule['priority']	= $this->rulesByID['priority'];
						$newRule['status']	= $this->rulesByID['status'];
						switch ($content['action'])
						{
							case 'folder':
								$newRule['action_arg'] = $content['action_folder_text'];
								break;
							case 'address':
								$newRule['action_arg'] = $content['action_address_text'];
								break;
							case 'reject':
								$newRule['action_arg'] = $content['action_reject_text'];
						}
						unset($newRule['action_folder_text']);
						unset($newRule['action_address_text']);
						unset($newRule['action_reject_text']);

						$newRule['flg'] = 0 ;
						if( $newRule['continue'] ) { $newRule['flg'] += 1; }
						if( $newRule['gthan'] )    { $newRule['flg'] += 2; }
						if( $newRule['anyof'] )    { $newRule['flg'] += 4; }
						if( $newRule['keep'] )     { $newRule['flg'] += 8; }
						if( $newRule['regexp'] )   { $newRule['flg'] += 128; }

						_debug_array($newRule);
						if($newRule['action'] && $this->rulesByID['priority'])
						{
							$this->rules[$ruleID] = $newRule;
							$ret = $this->bosieve->setRules($this->scriptName, $this->rules);
							if (!$ret && !empty($this->bosieve->error))
							{
								$msg .= lang("Saving the rule failed:")."<br />".$this->bosieve->error."<br />";
							}
							$this->saveSessionData();
						}
						else
						{
							$msg .= "\n".lang("Error: Could not save rule").' '.lang("No action defined!");
							$error++;
						}
						if ($button == "apply") break;
						//close the window and refresh the rules list
						$this->sieve_refresh();

					}
					else
					{
						$msg .= "\n".lang("Error: Could not save rule").' '.lang("No action defined!");
						$error++;
					}
				case 'cancel':
					break;

				case 'delete':
					$this->sieve_refresh();
					break;
			}
		}

		$sel_options = array(
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
			'ctype' => emailadmin_script::$btransform_ctype_array,

		);


		//error_log(__METHOD__. array2string($content));


		return $etmpl->exec('mail.mail_sieve.edit',$content,$sel_options,$readonlys,$preserv,2);
	}

	function editVacation($content=null)
	{
		$vtmpl = new etemplate('mail.sieve.editVacation');

		$preferences =& $this->mailPreferences;
		if(!(empty($preferences->preferences['prefpreventabsentnotice']) || $preferences->preferences['prefpreventabsentnotice'] == 0))
		{
			die('You should not be here!');
		}
		//$uiwidgets	=& CreateObject('felamimail.uiwidgets',EGW_APP_TPL);
		$boemailadmin	= new emailadmin_bo();
		if ($this->timed_vacation)
		{
			include_once(EGW_API_INC.'/class.jscalendar.inc.php');
			$jscal = new jscalendar();
		}
		if($this->bosieve->getScript($this->scriptName))
		{
			if(PEAR::isError($error = $this->bosieve->retrieveRules($this->scriptName)) )
			{
				$rules		= array();
				$vacation	= array();
			}
			else
			{
				$rules		= $this->bosieve->getRules($this->scriptName);
				$vacation	= $this->bosieve->getVacation($this->scriptName);
			}
		}
		else
		{
			// something went wrong
		}
		if (is_array($content))
		{
			list($button) = @each($content['button']);
			unset ($content['button']);

			switch($button)
			{
				case 'delete':
					break;
				case 'apply':
				case 'save':

			}
		}
		_debug_array($content);
		/*
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			// store text as default
			if (isset($_POST['set_as_default']))
			{
				config::save_value('default_vacation_text', $_POST['vacation_text'], 'felamimail');
			}
			//$this->t->set_var('set_as_default','<input type="submit" name="set_as_default" value="'.htmlspecialchars(lang('Set as default')).'" />');
			//set as default
		}
		$checkAddresses=(get_var('check_mail_sent_to',array('POST'))=='off'?false:true);

		if ($content['vacationStatus'])
		{

		}
		if(isset($_POST["vacationStatus"]))
		{
			$newVacation['text']		= get_var('vacation_text',array('POST'));
			if (strpos($newVacation['text'],"\r\n")===false) $newVacation['text'] = str_replace("\n\n","\r\n",$newVacation['text']);
			$newVacation['text']		= $this->botranslation->convert($newVacation['text'],$this->displayCharset,'UTF-8');
			$newVacation['days']		= get_var('days',array('POST'));
			$newVacation['addresses']	= get_var('vacationAddresses',array('POST'));
			$newVacation['status']		= get_var('vacationStatus',array('POST'));
			if (empty($preferences->preferences['prefpreventforwarding']) ||
				$preferences->preferences['prefpreventforwarding'] == 0 ) # ||
				#($ogServer instanceof emailadmin_smtp) || $ogServer->editForwardingAddress)
			{
				$newVacation['forwards']    = get_var('vacation_forwards',array('POST'));
			}
			if (!in_array($newVacation['status'],array('on','off','by_date'))) $newVacation['status'] = 'off';
			if ($this->timed_vacation||isset($_POST['start_date']) || isset($_POST['end_date']))
			{
				if (isset($_POST['start_date']))
				{
					$date = $jscal->input2date($_POST['start_date']);
					if ($date['raw']) $newVacation['start_date'] = $date['raw']-12*3600;
				}
				if (isset($_POST['end_date']))
				{
					$date = $jscal->input2date($_POST['end_date']);
					if ($date['raw']) $newVacation['end_date'] = $date['raw']-12*3600;
				}
			}
			if(isset($_POST['save']) || isset($_POST['apply']))
			{
				if($this->checkRule($newVacation,$checkAddresses))
				{
					if (!$this->bosieve->setVacation($this->scriptName, $newVacation))
					{
						print "vacation update failed<br>";
						#print $script->errstr."<br>";
						$this->t->set_var('validation_errors', lang('Vacation notice update failed').': '.$this->bosieve->error);
					}
					else
					{
						//error_log(__METHOD__.__LINE__.array2string($newVacation));
						if (!isset($newVacation['scriptName']) || empty($newVacation['scriptName'])) $newVacation['scriptName'] = $this->scriptName;
						$this->bosieve->setAsyncJob($newVacation);
						$this->t->set_var('validation_errors', lang('Vacation notice sucessful updated.'));
					}
				}
				else
				{
					if(isset($_POST['save'])) unset($_POST['save']);
					$this->t->set_var('validation_errors',implode('<br />',$this->errorStack));
				}
			}
			$vacation = $newVacation;
			if(isset($_POST['save']) || isset($_POST['cancel']))
			{
				$GLOBALS['egw']->redirect_link('/felamimail/index.php');
			}
		}
		$this->saveSessionData();
		// display the header
		$this->display_app_header();
		// initialize the template
		$this->t->set_file(array("filterForm" => "sieveForm.tpl"));
		$this->t->set_block('filterForm','vacation');
		// translate most of the parts
		$this->translate();

		// vacation status
		if($vacation['status'] == 'on')
		{
			$this->t->set_var('checked_active', 'checked');
		}
		elseif($vacation['status'] == 'off')
		{
			$this->t->set_var('checked_disabled', 'checked');
		}
		if($checkAddresses)
		{
			$this->t->set_var('check_mail_sent_to_active', 'checked');
		}
		else
		{
			$this->t->set_var('check_mail_sent_to_disabled', 'checked');
		}
		// vacation text
		if (empty($vacation['text']))
		{
			$config = new config('felamimail');
			$config = $config->read_repository();
			$vacation['text'] = $config['default_vacation_text'];
		}
		$this->t->set_var('vacation_text',$this->botranslation->convert($vacation['text'],'UTF-8'));
		//vacation days
		if(empty($vacation))
		{
			$this->t->set_var('selected_7', 'selected="selected"');
			// ToDO set default
		}
		else
		{
			$this->t->set_var('selected_'.($vacation['days']?$vacation['days']:'7'), 'selected="selected"');
		}
		if (empty($preferences->preferences['prefpreventforwarding']) || $preferences->preferences['prefpreventforwarding'] == 0 )
		{
			$this->t->set_var('vacation_forwards','<input class="input_text" name="vacation_forwards" size="80" value="'.htmlspecialchars($vacation['forwards']).'" />');
		}
		else
		{
			$this->t->set_var('vacation_forwards',lang('not allowed'));
			unset($vacation['forwards']);
		}
		// vacation addresses
		if(is_array($vacation['addresses']))
		{
			foreach($vacation['addresses'] as $address)
			{
				$selectedAddresses[$address] = $address;
			}
			asort($selectedAddresses);
		}
		$allIdentities = $preferences->getIdentity();
		//_debug_array($allIdentities);
		foreach($allIdentities as $key => $singleIdentity)
		{
			if((empty($vacation) || empty($selectedAddresses))&& $singleIdentity->default === true)
			{
				$selectedAddresses[$singleIdentity->emailAddress] = $singleIdentity->emailAddress;
			}
			$predefinedAddresses[$singleIdentity->emailAddress] = $singleIdentity->emailAddress;
		}
		asort($predefinedAddresses);
		$this->t->set_var('multiSelectBox',$uiwidgets->multiSelectBox(
			$selectedAddresses,
			$predefinedAddresses,
			'vacationAddresses',
			'400px'
		));

		$linkData = array (
			'menuaction'	=> 'felamimail.uisieve.editVacation',
		);

		$this->t->set_var('vacation_action_url',$GLOBALS['egw']->link('/index.php',$linkData));

			if ($this->timed_vacation)
			{
				$this->t->set_var('by_date','<input type="radio" name="vacationStatus" value="by_date" id="status_by_date" '.
					($vacation['status']=='by_date'?' checked':'').' /> <label for="status_by_date">'.lang('by date').'</label>: '.
					$jscal->input('start_date',$vacation['start_date']).' - '.$jscal->input('end_date',$vacation['end_date']));
				$this->t->set_var('lang_help_start_end_replacement','<br />'.lang('You can use %1 for the above start-date and %2 for the end-date.','$$start$$','$$end$$'));
			}
			$this->t->pfp('out','vacation');
*/
		$sel_options = array(
			'status' => array(
				'active' => lang('all of'),
				'disabled' => lang('any of'),
			),
		);
		$vtmpl->exec('mail.mail_sieve.editVacation',$content,$sel_options);
	}


	/**
	 * Move rule to an other position in list
	 *
	 * @param int $from 0, 1, ...
	 * @param int $to   0, 1, ...
	 */
	function ajax_moveRule($objType, $orders)
	{
		//$this->restoreSessionData();
		foreach ($orders as $keys => $val) $orders[$keys] = $orders[$keys] -1;
		error_log(__METHOD__.array2string($orders));
		$this->getRules(null);

		//_debug_array($this->rules);
		$newrules = $this->rules;
		$keyfound = 1;
		foreach($orders as $keys => $ruleID)
		{
			//error_log(__METHOD__. $ruleID);
			$newrules[$keys] = $this->rules[$ruleID];

			error_log(__METHOD__. "keys +1:" . $orders[$keys +1]);
			error_log(__METHOD__. "key:" . $orders[$keys]);
			if ((($ruleID - $orders[$keys +1]) !== -1) && ($keyfound == 1))
			{
				if ($orders[$keys +1] < ($orders[$keys] + 1) )
				{
					$to = $orders[$keys];
				}
				else
				{
					$from = $orders[$keys];
				}
				$keyfound = 0;
				error_log(__METHOD__. "from=" .$from);
				error_log(__METHOD__. "to=" .$to);
			}


		}
		error_log(__METHOD__. "from=" .$from);
		$msg = 'the rule with priority' . $from . 'moved to' . $to;
		//$this->rules = $newrules;
		_debug_array($newrules);
		$this->updateScript();
		$this->saveSessionData();
		$this->sieve_refresh($msg);

	}

	/**
	 * Handling actions over sieve rules list on gd
	 *
	 * @param type $actions
	 * @param type $checked
	 * @param type $action_msg
	 * @param type $msg
	 */
	function ajax_action($action,$checked,$msg='')
	{
		$this->getRules();
		$response = egw_json_response::get();
		switch ($action)
		{
			case 'delete':
				$msg = lang('rule ') . $checked . lang(' deleted!');
				unset($this->rules[$checked]);
				$this->rules = array_values($this->rules);
				break;
			case 'enable':
				$msg = lang('rule ') . $checked . lang(' enabled!');
				$this->rules[$checked][status] = 'ENABLED';
				break;
			case 'disable':
				$msg = lang('rule ') . $checked . lang(' disabled!');
				$this->rules[$checked][status] = 'DISABLED';
				break;
		}
		$this->updateScript();
		$this->saveSessionData();
		$this->sieve_refresh($msg);
		$response->call('app.mail.action',$action,$checked,$msg);
	}


	/**
	 *
	 */
	function addScript()
	{
		if($scriptName = $_POST['newScriptName'])
		{
			$this->bosieve->installScript($scriptName, '');
		}
			$this->listScripts();
	}

	/**
	 *
	 * @param type $rule
	 * @return string
	 */
	function buildRule($rule)
	{
		$andor = ' '. lang('and') .' ';
		$started = 0;
		if ($rule['anyof']) $andor = ' '. lang('or') .' ';
		$complete = lang('IF').' ';
		if ($rule['unconditional']) $complete = "[Unconditional] ";
		if ($rule['from'])
		{
			$match = $this->setMatchType($rule['from'],$rule['regexp']);
			$complete .= "'From:' " . $match . " '" . $rule['from'] . "'";
			$started = 1;
		}
		if ($rule['to'])
		{
			if ($started) $complete .= $andor;
			$match = $this->setMatchType($rule['to'],$rule['regexp']);
			$complete .= "'To:' " . $match . " '" . $rule['to'] . "'";
			$started = 1;
		}
		if ($rule['subject'])
		{
			if ($started) $complete .= $andor;
			$match = $this->setMatchType($rule['subject'],$rule['regexp']);
			$complete .= "'Subject:' " . $match . " '" . $rule['subject'] . "'";
			$started = 1;
		}
		if ($rule['field'] && $rule['field_val'])
		{
			if ($started) $complete .= $andor;
			$match = $this->setMatchType($rule['field_val'],$rule['regexp']);
			$complete .= "'" . $rule['field'] . "' " . $match . " '" . $rule['field_val'] . "'";
			$started = 1;
		}
		if ($rule['size'])
		{
			$xthan = " less than '";
			if ($rule['gthan']) $xthan = " greater than '";
			if ($started) $complete .= $andor;
			$complete .= "message " . $xthan . $rule['size'] . "KB'";
			$started = 1;
		}
		if (!$rule['unconditional']) $complete .= ' '.lang('THEN').' ';
		if (preg_match("/folder/i",$rule['action']))
			$complete .= lang('file into')." '" . $rule['action_arg'] . "';";
		if (preg_match("/reject/i",$rule['action']))
			$complete .= lang('reject with')." '" . $rule['action_arg'] . "'.";
		if (preg_match("/address/i",$rule['action']))
			$complete .= lang('forward to').' ' . $rule['action_arg'] .'.';
		if (preg_match("/discard/i",$rule['action']))
			$complete .= lang('discard').'.';
		if ($rule['continue']) $complete .= " [Continue]";
		if ($rule['keep']) $complete .= " [Keep a copy]";
		return $complete;
	}

	/**
	 *
	 * @param type $matchstr
	 * @param type $regex
	 * @return type
	 */
	function setMatchType (&$matchstr, $regex = false)
	{
		$match = lang('contains');
		if (preg_match("/\s*!/", $matchstr))
			$match = lang('does not contain');
		if (preg_match("/\*|\?/", $matchstr))
		{
			$match = lang('matches');
			if (preg_match("/\s*!/", $matchstr))
				$match = lang('does not match');
		}
		if ($regex)
		{
			$match = lang('matches regexp');
			if (preg_match("/\s*!/", $matchstr))
				$match = lang('does not match regexp');
		}
		$matchstr = preg_replace("/^\s*!/","",$matchstr);
		return $match;
	}

	/**
	 *
	 */
	function saveScript()
	{
		$scriptName 	= $_POST['scriptName'];
		$scriptContent	= $_POST['scriptContent'];
		if(isset($scriptName) and isset($scriptContent))
		{
			if($this->sieve->sieve_sendscript($scriptName, stripslashes($scriptContent)))
			{
				#print "Successfully loaded script onto server. (Remember to set it active!)<br>";
			}
		}
			$this->mainScreen();
	}

	/**
	 *
	 */
	function saveSessionData()
	{
		$sessionData['sieve_rules']		= $this->rules;
		$sessionData['sieve_rulesByID'] = $this->rulesByID;
		$sessionData['sieve_scriptToEdit']	= $this->scriptToEdit;
		$GLOBALS['egw']->session->appsession('sieve_session_data','',$sessionData);
	}

	/**
	 *
	 */
	function updateScript()
	{
		if (!$this->bosieve->setRules($this->scriptToEdit, $this->rules))
		{
			print "update failed<br>";exit;
		}
	}

	/**
	 * getRules()
	 * Fetched rules save on array()rules.
	 *
	 * @return boolean, returns false in case of failure and true in case of success.
	 */
	function getRules($ruleID)
	{
		if($script = $this->bosieve->getScript($this->scriptName))
		{
			$this->scriptToEdit 	= $this->scriptName;
			if(PEAR::isError($error = $this->bosieve->retrieveRules($this->scriptName)) )
			{
				error_log(__METHOD__.__LINE__.$error->message);
				$this->rules	= array();
				$this->rulesByID = array();
				$this->vacation	= array();
			}
			else
			{
				$this->rules	= $this->bosieve->getRules($this->scriptName);
				$this->rulesByID = $this->rules[$ruleID];
				$this->vacation	= $this->bosieve->getVacation($this->scriptName);
			}
			//$ruleslist= preg_match('#rule',$script, $subject)
			return true;
		}
		else
		{
			// something went wrong
			error_log(__METHOD__.__LINE__.' failed');
			return false;
		}
		//error_log(__METHOD__.array2string( $script));
	}


	/**
	 *
	 */
	function restoreSessionData()
	{
		$sessionData = $GLOBALS['egw']->session->appsession('sieve_session_data');
		$this->rules		= $sessionData['sieve_rules'];
		$this->rulesByID = $sessionData['sieve_rulesByID'];
		$this->scriptToEdit	= $sessionData['sieve_scriptToEdit'];
	}

	/**
	 *
	 * @return type
	 */
	function get_rows(&$rows,&$readonlys)
	{
		$rows = array();
		$this->getRules();	/* ADDED BY GHORTH */
		//$this->saveSessionData();

		if (is_array($this->rules) && !empty($this->rules) )
		{
			$rows = $this->rules;

			foreach ($rows as &$row )
			{
				$row['rules'] = $this->buildRule($row);
				$row['ruleID'] =(string)(($row['priority'] -1) / 2 );
				if ($row ['status'] === 'ENABLED')
				{

				}
			}

			//error_log(__METHOD__. array2string($rules));
			//_debug_array($rules);
		}else
		{
			//error_log(__METHOD__.'There are no rules or something is went wrong at getRules()!');
			return ;
		}
		array_unshift($rows,array(''=> ''));
		//_debug_array($rows);
		return $rows;
	}

	/**
	 * Get actions / context menu for index
	 *
	 *
	 *
	 * @return array
	 */
	private function get_actions(array $query=array())
	{
		$actions =array(

			'edit' => array(
				'caption' => 'Edit',
				'default' => true,
				'onExecute' => 'javaScript:app.mail.action'
			),
			'add' => array(
				'caption' => 'Add',
				//'url' => 'menuaction=mail.mail_sieve.edit',
				'onExecute' => 'javaScript:app.mail.action'
			),
			'enable' => array(
				'caption' => 'Enable',
				'onExecute' => 'javaScript:app.mail.action',
				'enableClass' => 'mail_sieve_ENABLED',
				'hideOnDisabled' => true,
			),
			'disable' => array(
				'caption' => 'Disable',
				'onExecute' => 'javaScript:app.mail.action',
				'disableClass' => 'mail_sieve_ENABLED',
				'hideOnDisabled' => true,

			),
			'delete' => array(
				'caption' => 'Delete',
				'onExecute' => 'javaScript:app.mail.action'
			),

		);
		//_debug_array($actions);
		return $actions;
	}

	function sieve_refresh($msg)
	{
		$response = egw_json_response::get();
		$response->alert('');
		$this->get_rows($rows, $readonlys);
		error_log(__METHOD__. count($rows));
		$response->call('app.mail.sieve_refresh',$rows, $msg);
	}

}


?>
