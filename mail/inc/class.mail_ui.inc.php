<?php
/**
 * EGroupware - Mail - interface class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Klaus Leithoff [kl@stylite.de]
 * @copyright (c) 2013 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Mail Interface class
 */
class mail_ui
{
	/**
	 * Methods callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array
	(
		'index' => True,
		'displayHeader'	=> True,
		'saveMessage'	=> True,
		'vfsSaveMessage' => True,
		'TestConnection' => True,
	);

	/**
	 * current icServerID
	 *
	 * @var int
	 */
	static $icServerID;

	/**
	 * delimiter - used to separate profileID from foldertreestructure, and separate keyinformation in rowids
	 *
	 * @var string
	 */
	static $delimiter = '::';

	/**
	 * instance of mail_bo
	 *
	 * @var object
	 */
	var $mail_bo;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{

		// no autohide of the sidebox, as we use it for folderlist now.
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
		if (!empty($_GET["resetConnection"])) $connectionReset = html::purify($_GET["resetConnection"]);
		unset($_GET["resetConnection"]);

		//$icServerID =& egw_cache::getSession('mail','activeProfileID');
		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']) && !empty($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
		{
			self::$icServerID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
		}
		if ($connectionReset)
		{
			if (mail_bo::$debug) error_log(__METHOD__.__LINE__.' Connection Reset triggered:'.$connectionReset.' for Profile with ID:'.self::$icServerID);
			emailadmin_bo::unsetCachedObjects(self::$icServerID);
		}

		$this->mail_bo = mail_bo::getInstance(false,$icServerID);
		if (mail_bo::$debug) error_log(__METHOD__.__LINE__.' Fetched IC Server:'.self::$icServerID.'/'.$this->mail_bo->profileID.':'.function_backtrace());
		// no icServer Object: something failed big time
		if (!isset($this->mail_bo->icServer)) exit; // ToDo: Exception or the dialog for setting up a server config
		if (!($this->mail_bo->icServer->_connected == 1)) $this->mail_bo->openConnection(self::$icServerID);
	}

	/**
	 * changeProfile
	 *
	 * @param int $icServerID
	 */
	function changeProfile($_icServerID)
	{
		self::$icServerID = $_icServerID;
		if (mail_bo::$debug) error_log(__METHOD__.__LINE__.'->'.self::$icServerID);
		emailadmin_bo::unsetCachedObjects(self::$icServerID);
		$this->mail_bo = mail_bo::getInstance(false,self::$icServerID);
		if (mail_bo::$debug) error_log(__METHOD__.__LINE__.' Fetched IC Server:'.self::$icServerID.'/'.$this->mail_bo->profileID.':'.function_backtrace());
		// no icServer Object: something failed big time
		if (!isset($this->mail_bo->icServer)) exit; // ToDo: Exception or the dialog for setting up a server config
		/*if (!($this->mail_bo->icServer->_connected == 1))*/ $this->mail_bo->openConnection(self::$icServerID);
		// save session varchar
		$oldicServerID =& egw_cache::getSession('mail','activeProfileID');
		$oldicServerID = self::$icServerID;
		// save pref
		$GLOBALS['egw']->preferences->add('mail','ActiveProfileID',self::$icServerID,'user');
		$GLOBALS['egw']->preferences->save_repository(true);
		$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = self::$icServerID;
	}

	/**
	 * Main mail page
	 *
	 * @param array $content=null
	 * @param string $msg=null
	 */
	function index(array $content=null,$msg=null)
	{
		//_debug_array($content);
		if (!is_array($content))
		{
			$content = array(
				'nm' => egw_session::appsession('index','mail'),
			);
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows'       =>	'mail.mail_ui.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'filter'         => 'INBOX',	// filter is used to choose the mailbox
					'no_filter2'     => false,	// I  disable the 2. filter (params are the same as for filter)
					'no_cat'         => true,	// I  disable the cat-selectbox
					//'cat_is_select'	 => 'no_lang', // true or no_lang
					'lettersearch'   => false,	// I  show a lettersearch
					'searchletter'   =>	false,	// I0 active letter of the lettersearch or false for [all]
					'start'          =>	0,		// IO position in list
					'order'          =>	'date',	// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'ASC',	// IO direction of the sort: 'ASC' or 'DESC'
					'default_cols'   => 'status,attachments,subject,fromaddress,date,size',	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
					'csv_fields'     =>	false, // I  false=disable csv export, true or unset=enable it with auto-detected fieldnames,
									//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
					'actions'        => self::get_actions(),
					'row_id'         => 'row_id', // is a concatenation of trim($GLOBALS['egw_info']['user']['account_id']):profileID:base64_encode(FOLDERNAME):uid
				);
				//$content['nm']['path'] = self::get_home_dir();
			}
		}
		if ($msg)
		{
			$content['msg'] = $msg;
		}
		else
		{
			unset($msg);
			unset($content['msg']);
		}
		$content['preview'] = "<html><body style='background-color: pink;'/></html>";
		$this->mail_bo->restoreSessionData();

		// filter is used to choose the mailbox
		//if (!isset($content['nm']['foldertree'])) // maybe we fetch the folder here
		/*
		$sel_options['nm']['foldertree'] =  array('id' => 0, 'item' => array(
			array('id' => '/INBOX', 'text' => 'INBOX', 'im0' => 'kfm_home.png', 'child' => '1', 'item' => array(
				array('id' => '/INBOX/sub', 'text' => 'sub'),
				array('id' => '/INBOX/sub2', 'text' => 'sub2'),
			)),
			array('id' => '/user', 'text' => 'user', 'child' => '1', 'item' => array(
				array('id' => '/user/birgit', 'text' => 'birgit'),
			)),
		));

		$content['nm']['foldertree'] = '/INBOX/sub';
		*/

		$sel_options['nm']['foldertree'] = $this->getFolderTree(false);

		$sessionFolder = $this->mail_bo->sessionData['maibox'];
		if ($this->mail_bo->folderExists($sessionFolder))
		{
			$content['nm']['selectedFolder'] = $this->mail_bo->profileID.self::$delimiter.$this->mail_bo->sessionData['maibox'];
		}

		if (!isset($content['nm']['foldertree'])) $content['nm']['foldertree'] = $this->mail_bo->profileID.self::$delimiter.'INBOX';
		if (!isset($content['nm']['selectedFolder'])) $content['nm']['selectedFolder'] = $this->mail_bo->profileID.self::$delimiter.'INBOX';
		$content['nm']['foldertree'] = $content['nm']['selectedFolder'];
		$sel_options['cat_id'] = array(1=>'none');
		if (!isset($content['nm']['cat_id'])) $content['nm']['cat_id'] = 'All';

		$etpl = new etemplate('mail.index');

		// Set tree actions
		$etpl->set_cell_attribute('nm[foldertree]','actions', array(

			'drop_move_mail' => array(
				'type' => 'drop',
				'acceptedTypes' => 'mail',
				'icon' => 'move',
				'caption' => 'Move to',
				'onExecute' => 'javaScript:mail_move'
			),
			'drop_copy_mail' => array(
				'type' => 'drop',
				'acceptedTypes' => 'mail',
				'icon' => 'copy',
				'caption' => 'Copy to',
				'onExecute' => 'javaScript:mail_copy'
			),
			'drop_cancel' => array(
				'caption' => 'Cancel',
				'acceptedTypes' => 'mail',
				'type' => 'drop',
			),
			// Tree doesn't support this one - yet
			'rename' => array(
				'caption' => 'Rename',
				'type' => 'popup'
			)
		));

		return $etpl->exec('mail.mail_ui.index',$content,$sel_options,$readonlys,$preserv);
	}

	/**
	 * Test Connection
	 * Simple Test, resets the active connections cachedObjects / ImapServer
	 */
	function TestConnection ()
	{
		// load translations
		translation::add_app('mail');

		common::egw_header();
		parse_navbar();
		//$GLOBALS['egw']->framework->sidebox();
		$preferences	=& $this->mail_bo->mailPreferences;

		if ($preferences->preferences['prefcontroltestconnection'] == 'none') die('You should not be here!');

		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
			$icServerID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
		//_debug_array($this->mail_bo->mailPreferences);
		if (is_object($preferences)) $imapServer = $preferences->getIncomingServer($icServerID);
		if (isset($imapServer->ImapServerId) && !empty($imapServer->ImapServerId))
		{
			$icServerID = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $imapServer->ImapServerId;
		}
		echo "<h2>".lang('Test Connection and display basic information about the selected profile')."</h2>";

		_debug_array('Connection Reset triggered:'.$connectionReset.' for Profile with ID:'.$icServerID);
		emailadmin_bo::unsetCachedObjects($icServerID);

		if (mail_bo::$idna2)
		{
			_debug_array('Umlautdomains supported (see Example below)');
			$dom = 'füßler.com';
			$encDom = mail_bo::$idna2->encode($dom);
			_debug_array(array('source'=>$dom,'result'=>array('encoded'=>$encDom,'decoded'=>mail_bo::$idna2->decode($encDom))));
		}

		if ($preferences->preferences['prefcontroltestconnection'] == 'reset') exit;

		echo "<hr /><h3 style='color:red'>".lang('IMAP Server')."</h3>";
		if($imapServer->_connectionErrorObject) $eO = $imapServer->_connectionErrorObject;
		unset($imapServer->_connectionErrorObject);
		$sieveServer = clone $imapServer;
		if (!empty($imapServer->adminPassword)) $imapServer->adminPassword='**********************';
		if ($preferences->preferences['prefcontroltestconnection'] == 'nopasswords' || $preferences->preferences['prefcontroltestconnection'] == 'nocredentials')
		{
			if (!empty($imapServer->password)) $imapServer->password='**********************';
		}
		if ($preferences->preferences['prefcontroltestconnection'] == 'nocredentials')
		{
			if (!empty($imapServer->adminUsername)) $imapServer->adminUsername='++++++++++++++++++++++';
			if (!empty($imapServer->username)) $imapServer->username='++++++++++++++++++++++';
			if (!empty($imapServer->loginName)) $imapServer->loginName='++++++++++++++++++++++';
		}
		if ($preferences->preferences['prefcontroltestconnection'] <> 'basic')
		{
			_debug_array($imapServer);
		}
		else
		{
			_debug_array(array('ImapServerId' =>$imapServer->ImapServerId,
				'host'=>$imapServer->host,
				'port'=>$imapServer->port,
				'validatecert'=>$imapServer->validatecert));
		}

		echo "<h4 style='color:red'>".lang('Connection Status')."</h4>";
		$lE = false;
		if ($eO && $eO->message)
		{
			_debug_array($eO->message);
			$lE = true;
		}
		$isError = egw_cache::getCache(egw_cache::INSTANCE,'email','icServerIMAP_connectionError'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*5);
		if ($isError[$icServerID]) {
			_debug_array($isError[$icServerID]);
			$lE = true;
		}
		_debug_array(($lE?'':lang('Successfully connected')));

		$suF = $this->mail_bo->getSpecialUseFolders();
		if (is_array($suF) && !empty($suF)) _debug_array(array(lang('Server supports Special-Use Folders')=>$suF));

		if(($sieveServer instanceof defaultimap) && $sieveServer->enableSieve) {
			$scriptName = (!empty($GLOBALS['egw_info']['user']['preferences']['mail']['sieveScriptName'])) ? $GLOBALS['egw_info']['user']['preferences']['mail']['sieveScriptName'] : 'mail';
			$sieveServer->getScript($scriptName);
			$rules = $sieveServer->retrieveRules($sieveServer->scriptName,true);
			$vacation = $sieveServer->getVacation($sieveServer->scriptName);
			echo "<h4 style='color:red'>".lang('Sieve Connection Status')."</h4>";
			$isSieveError = egw_cache::getCache(egw_cache::INSTANCE,'email','icServerSIEVE_connectionError'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*15);
			if ($isSieveError[$icServerID])
			{
				_debug_array($isSieveError[$icServerID]);
			}
			else
			{
				_debug_array(array(lang('Successfully connected'),$rules));
			}
		}
		echo "<hr /><h3 style='color:red'>".lang('Preferences')."</h3>";
		_debug_array($preferences->preferences);
		//error_log(__METHOD__.__LINE__.' ImapServerId:'.$imapServer->ImapServerId.' Prefs:'.array2string($preferences->preferences));
		//error_log(__METHOD__.__LINE__.' ImapServerObject:'.array2string($imapServer));
		if (is_object($preferences)) $activeIdentity =& $preferences->getIdentity($icServerID, true);
		//_debug_array($activeIdentity);
		$maxMessages	=  50;
		$userPreferences	=&  $GLOBALS['egw_info']['user']['preferences']['mail'];

		// retrieve data for/from user defined accounts
		$selectedID = 0;
		if (count($preferences->ic_server)) {
			foreach ($preferences->ic_server as $tmpkey => $accountData)
			{
				if ($tmpkey==0) continue;
				$identity =& $preferences->identities[$tmpkey];
				$icServer =& $accountData;
				//_debug_array($identity);
				//_debug_array($icServer);
				//error_log(__METHOD__.__LINE__.' Userdefined Profiles ImapServerId:'.$icServer->ImapServerId);
				if (empty($icServer->host)) continue;
				$identities[$identity->id]=$identity->realName.' '.$identity->organization.' &lt;'.$identity->emailAddress.'&gt;';
				if (!empty($identity->default)) $identities[$identity->id] = $identities[$identity->id].'<b>('.lang('selected').')</b>';
			}
		}
		if (count($identities)>0)
		{
			echo "<hr /><h3 style='color:red'>".lang('available personal EMail-Accounts/Profiles')."</h3>";
			_debug_array($identities);
		}

		if (empty($imapServer->host) && count($identities)==0 && $preferences->userDefinedAccounts)
		{
			// redirect to new personal account
			egw::redirect_link('/index.php',array('menuaction'=>'mail.uipreferences.editAccountData',
				'accountID'=>"new",
				'msg'	=> lang("There is no IMAP Server configured.")." - ".lang("Please configure access to an existing individual IMAP account."),
			));
		}
		common::egw_footer();
	}

	/**
	 * Ajax callback to fetch folders for given profile
	 *
	 * We currently load all folders of a given profile, tree can also load parts of a tree.
	 *
	 * @param string $_GET[id] if of node whos children are requested
	 */
	public function ajax_foldertree()
	{
		$nodeID = $_GET['id'];
		//error_log(__METHOD__.__LINE__.'->'.array2string($_REQUEST));
		//error_log(__METHOD__.__LINE__.'->'.array2string($_GET));
		$data = $this->getFolderTree(false, $nodeID);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
		common::egw_exit();
	}

	/**
	 * getFolderTree, get folders from server and prepare the folder tree
	 * @param bool $_fetchCounters, wether to fetch extended information on folders
	 * @param string $_nodeID, nodeID to fetch and return
	 * @return array something like that: array('id'=>0,
	 * 		'item'=>array(
	 *			'text'=>'INBOX',
	 *			'tooltip'=>'INBOX'.' '.lang('(not connected)'),
	 *			'im0'=>'kfm_home.png'
	 *			'item'=>array($MORE_ITEMS)
	 *		)
	 *	);
	 */
	function getFolderTree($_fetchCounters=false, $_nodeID=null)
	{
		if ($_nodeID)
		{
			list($_profileID,$_folderName) = explode(self::$delimiter,$_nodeID,2);
			if (is_numeric($_profileID))
			{
				if ($_profileID && $_profileID != $this->mail_bo->profileID)
				{
					//error_log(__METHOD__.__LINE__.' change Profile to ->'.$_profileID);
					$this->changeProfile($_profileID);
				}
			}
		}
		$folderObjects = $this->mail_bo->getFolderObjects(true,false,true);
		$trashFolder = $this->mail_bo->getTrashFolder();
		$templateFolder = $this->mail_bo->getTemplateFolder();
		$draftFolder = $this->mail_bo->getDraftFolder();
		$sentFolder = $this->mail_bo->getSentFolder();
		$userDefinedFunctionFolders = array();
		if (isset($trashFolder) && $trashFolder != 'none') $userDefinedFunctionFolders['Trash'] = $trashFolder;
		if (isset($sentFolder) && $sentFolder != 'none') $userDefinedFunctionFolders['Sent'] = $sentFolder;
		if (isset($draftFolder) && $draftFolder != 'none') $userDefinedFunctionFolders['Drafts'] = $draftFolder;
		if (isset($templateFolder) && $templateFolder != 'none') $userDefinedFunctionFolders['Templates'] = $templateFolder;
		$out = array('id' => 0);
		//_debug_array($this->mail_bo->mailPreferences);
		//if($this->mail_bo->mailPreferences->userDefinedAccounts) $allAccountData = $this->mail_bo->bopreferences->getAllAccountData($this->mail_bo->mailPreferences);
		if (count($this->mail_bo->mailPreferences->ic_server)) {
			foreach ($this->mail_bo->mailPreferences->ic_server as $tmpkey => $accountData)
			{
				if ($tmpkey==0) continue;
				$identity =& $this->mail_bo->mailPreferences->identities[$tmpkey];
				$icServer =& $accountData;
				//_debug_array($identity);
				//_debug_array($icServer);
				if ($_profileID && $icServer->ImapServerId<>$_profileID) continue;
				//error_log(__METHOD__.__LINE__.' Userdefined Profiles ImapServerId:'.$icServer->ImapServerId);
				if (empty($icServer->host)) continue;
				$identities[$icServer->ImapServerId]=$identity->realName.' '.$identity->organization.' &lt;'.$identity->emailAddress.'&gt;';
				$oA = array('id'=>$icServer->ImapServerId,
					'text'=>$identities[$icServer->ImapServerId], //$this->mail_bo->profileID,
					'tooltip'=>'('.$icServer->ImapServerId.') '.htmlspecialchars_decode($identities[$icServer->ImapServerId]),
					'im0' => 'thunderbird.png',
					'im1' => 'thunderbird.png',
					'im2' => 'thunderbird.png',
					'path'=> array($icServer->ImapServerId),
					'child'=> 1, // dynamic loading on unfold
					'parent' => ''
				);
				$this->setOutStructure($oA,$out,self::$delimiter);
			}
		}

		//_debug_array($folderObjects);
		$c = 0;
		foreach($folderObjects as $key => $obj)
		{
			$fS = $this->mail_bo->getFolderStatus($key,false,($_fetchCounters?false:true));
			//_debug_array($fS);
			$fFP = $folderParts = explode($obj->delimiter, $key);

			//get rightmost folderpart
			$shortName = array_pop($folderParts);

			// the rest of the array is the name of the parent
			$parentName = implode((array)$folderParts,$obj->delimiter);
			$parentName = $this->mail_bo->profileID.self::$delimiter.$parentName;
			$oA =array('text'=> $obj->shortDisplayName, 'tooltip'=> $obj->displayName);
			array_unshift($fFP,$this->mail_bo->profileID);
			$oA['path'] = $fFP;
			$path = $key; //$obj->folderName; //$obj->delimiter
			if ($fS['unseen']) $oA['text'] = '<b>'.$oA['text'].' ('.$fS['unseen'].')</b>';
			if ($path=='INBOX')
			{
				$oA['im0'] = $oA['im1']= $oA['im2'] = "kfm_home.png";
			}
			elseif (in_array($obj->shortFolderName,mail_bo::$autoFolders))
			{
				//echo $obj->shortFolderName.'<br>';
				$oA['im0'] = $oA['im1']= $oA['im2'] = "MailFolder".$obj->shortFolderName.".png";
				//$image2 = "'MailFolderPlain.png'";
				//$image3 = "'MailFolderPlain.png'";
			}
			elseif (in_array($key,$userDefinedFunctionFolders))
			{
				$_key = array_search($key,$userDefinedFunctionFolders);
				$oA['im0'] = $oA['im1']= $oA['im2'] = "MailFolder".$_key.".png";
			}
			else
			{
				$oA['im0'] =  "MailFolderPlain.png"; // one Level
				$oA['im1'] = "folderOpen.gif";
				$oA['im2'] = "MailFolderClosed.png"; // has Children
			}
			$path = $this->mail_bo->profileID.self::$delimiter.$key; //$obj->folderName; //$obj->delimiter
			$oA['id'] = $path; // ID holds the PATH
			if (!empty($fS['attributes']) && stripos(array2string($fS['attributes']),'\noselect')!== false)
			{
				$oA['im0'] = "folderNoSelectClosed.gif"; // one Level
				$oA['im1'] = "folderNoSelectOpen.gif";
				$oA['im2'] = "folderNoSelectClosed.gif"; // has Children
			}
			if (!empty($fS['attributes']) && stripos(array2string($fS['attributes']),'\hasnochildren')=== false)
			{
				$oA['child']=1; // translates to: hasChildren -> dynamicLoading
			}
			$oA['parent'] = $parentName;
//_debug_array($oA);
			$this->setOutStructure($oA,$out,$obj->delimiter);
			$c++;
		}
		if ($_nodeID)
		{
			$node = self::findNode($out,$_nodeID);
			//error_log(__METHOD__.__LINE__.array2string($node));
			return $node;
		}
		return ($c?$out:array('id'=>0, 'item'=>array('text'=>'INBOX','tooltip'=>'INBOX'.' '.lang('(not connected)'),'im0'=>'kfm_home.png')));
	}

	/**
	 * findNode - helper function to return only a branch of the tree
	 *
	 * @param array &$out, out array (to be processed)
	 * @param string $_nodeID, node to search for
	 * @param boolean $childElements=true return node itself, or only its child items
	 * @return array structured subtree
	 */
	static function findNode($_out, $_nodeID, $childElements = false)
	{
		foreach($_out['item'] as $node)
		{
			if ($node['id']==$_nodeID)
			{
				return ($childElements?$node['item']:$node);
			}
		}
	}

	/**
	 * setOutStructure - helper function to transform the folderObjectList to dhtmlXTreeObject requirements
	 *
	 * @param array $data, data to be processed
	 * @param array &$out, out array
	 * @param string $del='.', needed as glue for parent/child operation / comparsion
	 * @param boolean $createMissingParents=true create a missing parent, instead of throwing an exception
	 * @return void
	 */
	function setOutStructure($data, &$out, $del='.', $createMissingParents=true)
	{
		//error_log(__METHOD__."(".array2string($data).', '.array2string($out).", '$del')");
		$components = $data['path'];
		array_pop($components);	// remove own name

		$insert = &$out;
		$parents = array();
		foreach($components as $component)
		{
			if (count($parents)>1)
			{
				$helper = array_slice($parents,1,null,true);
				$parent = $parents[0].self::$delimiter.implode($del, $helper);
				if ($parent) $parent .= $del;
			}
			else
			{
				$parent = implode(self::$delimiter, $parents);
				if ($parent) $parent .= self::$delimiter;
			}
			
			if (!is_array($insert) || !isset($insert['item']))
			{
				//break;
				throw new egw_exception_assertion_failed(__METHOD__.':'.__LINE__." id=$data[id]: Parent '$parent' '$component' not found! out=".array2string($out));
			}
			foreach($insert['item'] as &$item)
			{
				if ($item['id'] == $parent.$component)
				{
					$insert =& $item;
					break;
				}
			}
			if ($item['id'] != $parent.$component)
			{
				if ($createMissingParents)
				{
					unset($item);
					$item = array('id' => $parent.$component, 'text' => $component, 'im0' => "folderNoSelectClosed.gif",'im1' => "folderNoSelectOpen.gif",'im2' => "folderNoSelectClosed.gif",'tooltip' => '**missing**');
					$insert['item'][] =& $item;
					$insert =& $item;
				}
				else
				{
					throw new egw_exception_assertion_failed(__METHOD__.':'.__LINE__.": id=$data[id]: Parent '$parent' '$component' not found!");
				}
			}
			$parents[] = $component;
		}
		unset($data['path']);
		$insert['item'][] = $data;
		//error_log(__METHOD__."() leaving with out=".array2string($out));
	}

	/**
	 * Get actions / context menu for index
	 *
	 * Changes here, require to log out, as $content['nm'] get stored in session!
	 * @var &$action_links
	 *
	 * @return array see nextmatch_widget::egw_actions()
	 */
	private function get_actions(array &$action_links=array())
	{
		// duplicated from mail_hooks
		static $deleteOptions = array(
			'move_to_trash'		=> 'move to trash',
			'mark_as_deleted'	=> 'mark as deleted',
			'remove_immediately' =>	'remove immediately',
		);
		// todo: real hierarchical folder list
		$folders = array(
			'INBOX' => 'INBOX',
			'Drafts' => 'Drafts',
			'Sent' => 'Sent',
		);
		$lastFolderUsedForMove = null;
		$moveaction = 'move_';
		$lastFolderUsedForMoveCont = egw_cache::getCache(egw_cache::INSTANCE,'email','lastFolderUsedForMove'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*1);
		if (isset($lastFolderUsedForMoveCont[$this->mail_bo->profileID]))
		{
			$_folder = $this->mail_bo->icServer->getCurrentMailbox();
			//error_log(__METHOD__.__LINE__.' '.$_folder."<->".$lastFolderUsedForMoveCont[$this->mail_bo->profileID].function_backtrace());
			//if ($_folder!=$lastFolderUsedForMoveCont[$this->mail_bo->profileID]) $this->mail_bo->icServer->selectMailbox($lastFolderUsedForMoveCont[$this->mail_bo->profileID]);
			if ($_folder!=$lastFolderUsedForMoveCont[$this->mail_bo->profileID])
			{
				$lastFolderUsedForMove = $this->mail_bo->getFolderStatus($lastFolderUsedForMoveCont[$this->mail_bo->profileID]);
				//error_log(array2string($lastFolderUsedForMove));
				$moveaction .= $lastFolderUsedForMoveCont[$this->mail_bo->profileID];
			}
			//if ($_folder!=$lastFolderUsedForMoveCont[$this->profileID]) $this->mail_bo->icServer->selectMailbox($_folder);

		}
		$actions =  array(
			'open' => array(
				'caption' => lang('Open'),
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.open',
				'allowOnMultiple' => false,
				'default' => true,
			),
			'reply' => array(
				'caption' => 'Reply',
				'icon' => 'mail_reply',
				'group' => ++$group,
				'onExecute' => 'javaScript:mail_compose',
				'allowOnMultiple' => false,
			),
			'reply_all' => array(
				'caption' => 'Reply All',
				'icon' => 'mail_replyall',
				'group' => $group,
				'onExecute' => 'javaScript:mail_compose',
				'allowOnMultiple' => false,
			),
			'forward' => array(
				'caption' => 'Forward',
				'icon' => 'mail_forward',
				'group' => $group,
				'children' => array(
					'forwardinline' => array(
						'caption' => 'forward inline',
						'icon' => 'mail_forward',
						'group' => $group,
						'onExecute' => 'javaScript:mail_compose',
						'allowOnMultiple' => false,
					),
					'forwardasattach' => array(
						'caption' => 'forward as attachment',
						'icon' => 'mail_forward',
						'group' => $group,
						'onExecute' => 'javaScript:mail_compose',
					),
				),
			),
			'composeasnew' => array(
				'caption' => 'Compose as new',
				'icon' => 'new',
				'group' => $group,
				'onExecute' => 'javaScript:mail_compose',
				'allowOnMultiple' => false,
			),
			$moveaction => array(
				'caption' => lang('Move selected to').': '.(isset($lastFolderUsedForMove['shortDisplayName'])?$lastFolderUsedForMove['shortDisplayName']:''),
				'icon' => 'move',
				'group' => ++$group,
				'onExecute' => 'javaScript:mail_move2folder',
				'allowOnMultiple' => true,
			),
			'infolog' => array(
				'caption' => 'InfoLog',
				'hint' => 'Save as InfoLog',
				'icon' => 'infolog/navbar',
				'group' => ++$group,
				'onExecute' => 'javaScript:mail_infolog',
				'url' => 'menuaction=infolog.infolog_ui.import_mail',
				'popup' => egw_link::get_registry('infolog', 'add_popup'),
				'allowOnMultiple' => false,
			),
			'tracker' => array(
				'caption' => 'Tracker',
				'hint' => 'Save as ticket',
				'group' => $group,
				'icon' => 'tracker/navbar',
				'onExecute' => 'javaScript:mail_tracker',
				'url' => 'menuaction=tracker.tracker_ui.import_mail',
				'popup' => egw_link::get_registry('tracker', 'add_popup'),
				'allowOnMultiple' => false,
			),
			'print' => array(
				'caption' => 'Print',
				'group' => ++$group,
				'onExecute' => 'javaScript:mail_print',
				'allowOnMultiple' => false,
			),
			'save' => array(
				'caption' => 'Save',
				'group' => $group,
				'icon' => 'fileexport',
				'children' => array(
					'save2disk' => array(
						'caption' => 'Save message to disk',
						'hint' => 'Save message to disk',
						'group' => $group,
						'icon' => 'fileexport',
						'onExecute' => 'javaScript:mail_save',
						'allowOnMultiple' => false,
					),
					'save2filemanager' => array(
						'caption' => 'Save to filemanager',
						'hint' => 'Save message to filemanager',
						'group' => $group,
						'icon' => 'filemanager/navbar',
						'onExecute' => 'javaScript:mail_save2fm',
						'allowOnMultiple' => false,
					),
				),
			),
			'view' => array(
				'caption' => 'View',
				'group' => $group,
				'icon' => 'kmmsgread',
				'children' => array(
					'header' => array(
						'caption' => 'Header lines',
						'hint' => 'View header lines',
						'group' => $group,
						'icon' => 'kmmsgread',
						'onExecute' => 'javaScript:mail_header',
						'allowOnMultiple' => false,
					),
					'mailsource' => array(
						'caption' => 'Mail Source',
						'hint' => 'View full Mail Source',
						'group' => $group,
						'icon' => 'fileexport',
						'onExecute' => 'javaScript:mail_mailsource',
						'allowOnMultiple' => false,
					),
				),
			),
			'mark' => array(
				'caption' => 'Mark as',
				'icon' => 'read_small',
				'group' => ++$group,
				'children' => array(
					// icons used from http://creativecommons.org/licenses/by-sa/3.0/
					// Artist: Led24
					// Iconset Homepage: http://led24.de/iconset
					// License: CC Attribution 3.0
					'setLabel' => array(
						'caption' => 'Set Label',
						'icon' => 'tag_message',
						'group' => ++$group,
						'children' => array(
							'label1' => array(
								'caption' => "<font color='#ff0000'>".lang('urgent')."</font>",
								'icon' => 'mail_label1',
								'onExecute' => 'javaScript:mail_flag',
							),
							'label2' => array(
								'caption' => "<font color='#ff8000'>".lang('job')."</font>",
								'icon' => 'mail_label2',
								'onExecute' => 'javaScript:mail_flag',
							),
							'label3' => array(
								'caption' => "<font color='#008000'>".lang('personal')."</font>",
								'icon' => 'mail_label3',
								'onExecute' => 'javaScript:mail_flag',
							),
							'label4' => array(
								'caption' => "<font color='#0000ff'>".lang('to do')."</font>",
								'icon' => 'mail_label4',
								'onExecute' => 'javaScript:mail_flag',
							),
							'label5' => array(
								'caption' => "<font color='#8000ff'>".lang('later')."</font>",
								'icon' => 'mail_label5',
								'onExecute' => 'javaScript:mail_flag',
							),
						),
					),
					// modified icons from http://creativecommons.org/licenses/by-sa/3.0/
					'unsetLabel' => array(
						'caption' => 'Remove Label',
						'icon' => 'untag_message',
						'group' => ++$group,
						'children' => array(
							'unlabel1' => array(
								'caption' => "<font color='#ff0000'>".lang('urgent')."</font>",
								'icon' => 'mail_unlabel1',
								'onExecute' => 'javaScript:mail_flag',
							),
							'unlabel2' => array(
								'caption' => "<font color='#ff8000'>".lang('job')."</font>",
								'icon' => 'mail_unlabel2',
								'onExecute' => 'javaScript:mail_flag',
							),
							'unlabel3' => array(
								'caption' => "<font color='#008000'>".lang('personal')."</font>",
								'icon' => 'mail_unlabel3',
								'onExecute' => 'javaScript:mail_flag',
							),
							'unlabel4' => array(
								'caption' => "<font color='#0000ff'>".lang('to do')."</font>",
								'icon' => 'mail_unlabel4',
								'onExecute' => 'javaScript:mail_flag',
							),
							'unlabel5' => array(
								'caption' => "<font color='#8000ff'>".lang('later')."</font>",
								'icon' => 'mail_unlabel5',
								'onExecute' => 'javaScript:mail_flag',
							),
						),
					),
					'flagged' => array(
						'group' => ++$group,
						'caption' => 'Flagged',
						'icon' => 'unread_flagged_small',
						'onExecute' => 'javaScript:mail_flag',
						//'disableClass' => 'flagged',
						//'enabled' => "javaScript:mail_disabledByClass",
						'shortcut' => egw_keymanager::shortcut(egw_keymanager::F, true, true),
					),
					'unflagged' => array(
						'group' => $group,
						'caption' => 'Unflagged',
						'icon' => 'read_flagged_small',
						'onExecute' => 'javaScript:mail_flag',
						//'enableClass' => 'flagged',
						//'enabled' => "javaScript:mail_enabledByClass",
						'shortcut' => egw_keymanager::shortcut(egw_keymanager::U, true, true),
					),
					'read' => array(
						'group' => $group,
						'caption' => 'Read',
						'icon' => 'read_small',
						'onExecute' => 'javaScript:mail_flag',
						//'enableClass' => 'unseen',
						//'enabled' => "javaScript:mail_enabledByClass",
					),
					'unread' => array(
						'group' => $group,
						'caption' => 'Unread',
						'icon' => 'unread_small',
						'onExecute' => 'javaScript:mail_flag',
						//'disableClass' => 'unseen',
						//'enabled' => "javaScript:mail_disabledByClass",
					),
					'undelete' => array(
						'group' => $group,
						'caption' => 'Undelete',
						'icon' => 'revert',
						'onExecute' => 'javaScript:mail_flag',
						'enableClass' => 'deleted',
						'enabled' => "javaScript:mail_enabledByClass",
					),
				),
			),
			'delete' => array(
				'caption' => 'Delete',
				'hint' => $deleteOptions[$this->mail_bo->mailPreferences->preferences['deleteOptions']],
				'group' => ++$group,
				'onExecute' => 'javaScript:mail_delete',
			),
			'drag_mail' => array(
				'dragType' => 'mail',
				'type' => 'drag',
				'onExecute' => 'javaScript:mail_dragStart',
			),
		);
		// save as tracker, save as infolog, as this are actions that are either available for all, or not, we do that for all and not via css-class disabling
		if (!isset($GLOBALS['egw_info']['user']['apps']['infolog']))
		{
			unset($actions['infolog']);
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['tracker']))
		{
			unset($actions['tracker']);
		}
		if (empty($lastFolderUsedForMove))
		{
			unset($actions[$moveaction]);
		}
		// note this one is NOT a real CAPABILITY reported by the server, but added by selectMailbox
		if (!$this->mail_bo->icServer->hasCapability('SUPPORTS_KEYWORDS'))
		{
			unset($actions['mark']['children']['setLabel']);
			unset($actions['mark']['children']['unsetLabel']);
		}
		return $actions;
	}

	/**
	 * Callback to fetch the rows for the nextmatch widget
	 *
	 * @param array $query
	 * @param array &$rows
	 * @param array &$readonlys
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
unset($query['actions']);
//error_log(__METHOD__.__LINE__.array2string($query));
//error_log(__METHOD__.__LINE__.' SelectedFolder:'.$query['selectedFolder'].' Start:'.$query['start'].' NumRows:'.$query['num_rows']);
		//$starttime = microtime(true);
		//error_log(__METHOD__.__LINE__.array2string($query['search']));
		//$query['search'] is the phrase in the searchbox

		//error_log(__METHOD__.__LINE__.' Folder:'.array2string($_folderName).' FolderType:'.$folderType.' RowsFetched:'.array2string($rowsFetched)." these Uids:".array2string($uidOnly).' Headers passed:'.array2string($headers));
		$this->mail_bo->restoreSessionData();
		$maxMessages = 50; // match the hardcoded setting for data retrieval as inital value
		$previewMessage = $this->mail_bo->sessionData['previewMessage'];
		if (isset($query['selectedFolder'])) $this->mail_bo->sessionData['maibox']=$query['selectedFolder'];
		$this->mail_bo->saveSessionData();

		$sRToFetch = null;
		$_folderName=$query['selectedFolder'];
		list($_profileID,$folderName) = explode(self::$delimiter,$_folderName,2);
		if (is_numeric($_profileID))
		{
			if ($_profileID && $_profileID != $this->mail_bo->profileID)
			{
				//error_log(__METHOD__.__LINE__.' change Profile to ->'.$_profileID);
				$this->changeProfile($_profileID);
			}
			$_folderName = $folderName;
		}
		//save selected Folder to sessionData (mailbox)->currentFolder
		if (isset($query['selectedFolder'])) $this->mail_bo->sessionData['maibox']=$_folderName;
		$this->mail_bo->saveSessionData();
		$rowsFetched['messages'] = null;
		$offset = $query['start']+1; // we always start with 1
		$maxMessages = $query['num_rows'];
		$sort = $query['order'];
		$filter = array();
		$reverse = ($query['order']=='ASC'?false:true);
		//error_log(__METHOD__.__LINE__.' maxMessages:'.$maxMessages.' Offset:'.$offset.' Filter:'.array2string($this->sessionData['messageFilter']));
		if ($maxMessages > 75)
		{
			$sR = $this->mail_bo->getSortedList(
				$_folderName,
				$sort,
				$reverse,
				$filter,
				$rByUid=true
			);
			$rowsFetched['messages'] = count($sR);
			// if $sR is false, something failed fundamentally
			if($reverse === true) $sR = ($sR===false?array():array_reverse((array)$sR));
			$sR = array_slice((array)$sR,($offset==0?0:$offset-1),$maxMessages); // we need only $maxMessages of uids
			$sRToFetch = $sR;//array_slice($sR,0,50); // we fetch only the headers of a subset of the fetched uids
			//error_log(__METHOD__.__LINE__.' Rows fetched (UID only):'.count($sR).' Data:'.array2string($sR));
			$maxMessages = 75;
			$sortResultwH['header'] = array();
			if (count($sRToFetch)>0)
			{
				//error_log(__METHOD__.__LINE__.' Headers to fetch with UIDs:'.count($sRToFetch).' Data:'.array2string($sRToFetch));
				$sortResult = array();
				// fetch headers
				$sortResultwH = $this->mail_bo->getHeaders(
					$_folderName,
					$offset,
					$maxMessages,
					$sort,
					$reverse,
					$filter,
					$sRToFetch
				);
			}
		}
		else
		{
			$sortResult = array();
			// fetch headers
			$sortResultwH = $this->mail_bo->getHeaders(
				$_folderName,
				$offset,
				$maxMessages,
				$sort,
				$reverse,
				$filter
			);
			$rowsFetched['messages'] = $sortResultwH['info']['total'];
		}
		if (is_array($sR) && count($sR)>0)
		{
			foreach ((array)$sR as $key => $v)
			{
				if (array_key_exists($key,(array)$sortResultwH['header'])==true)
				{
					$sortResult['header'][] = $sortResultwH['header'][$key];
				}
				else
				{
					if (!empty($v)) $sortResult['header'][] = array('uid'=>$v);
				}
			}
		}
		else
		{
			$sortResult = $sortResultwH;
		}
		$rowsFetched['rowsFetched'] = count($sortResult['header']);
		if (empty($rowsFetched['messages'])) $rowsFetched['messages'] = $rowsFetched['rowsFetched'];

		//error_log(__METHOD__.__LINE__.' Rows fetched:'.$rowsFetched.' Data:'.array2string($sortResult));
		$cols = array('row_id','uid','status','attachments','subject','toaddress','fromaddress','date','size','modified');
		if ($GLOBALS['egw_info']['user']['preferences']['common']['select_mode']=='EGW_SELECTMODE_TOGGLE') unset($cols[0]);
		$rows = $this->header2gridelements($sortResult['header'],$cols, $_folderName, $folderType,$previewMessage);
		//error_log(__METHOD__.__LINE__.array2string($rows));
		//$endtime = microtime(true) - $starttime;
		//error_log(__METHOD__.__LINE__.' SelectedFolder:'.$query['selectedFolder'].' Start:'.$query['start'].' NumRows:'.$query['num_rows'].' Took:'.$endtime);

		return $rowsFetched['messages'];
	}

	/**
	 * function createRowID - create a unique rowID for the grid
	 *
	 * @param string $_folderName, used to ensure the uniqueness of the uid over all folders
	 * @param string $message_uid, the message_Uid to be used for creating the rowID
	 * @return string - a colon separated string in the form accountID:profileID:folder:message_uid
	 */
	function createRowID($_folderName, $message_uid)
	{
		return trim($GLOBALS['egw_info']['user']['account_id']).self::$delimiter.$this->mail_bo->profileID.self::$delimiter.base64_encode($_folderName).self::$delimiter.$message_uid;
	}

	/**
	 * function splitRowID - split the rowID into its parts
	 *
	 * @param string $_rowID, string - a colon separated string in the form accountID:profileID:folder:message_uid
	 * @return array populated named result array (accountID,profileID,folder,msgUID)
	 */
	static function splitRowID($_rowID)
	{
		$res = explode(self::$delimiter,$_rowID);
		// as a rowID is perceeded by app::, should be mail!
		return array('app'=>$res[0], 'accountID'=>$res[1], 'profileID'=>$res[2], 'folder'=>base64_decode($res[3]), 'msgUID'=>$res[4]);
	}

	/**
	 * function header2gridelements - to populate the grid elements with the collected Data
	 *
	 * @param array $_headers, headerdata to process
	 * @param array $cols, cols to populate
	 * @param array $_folderName, used to ensure the uniqueness of the uid over all folders
	 * @param array $_folderType=0, foldertype, used to determine if we need to populate from/to
	 * @param array $previewMessage=0, the message previewed
	 * @return array populated result array
	 */
	public function header2gridelements($_headers, $cols, $_folderName, $_folderType=0, $previewMessage=0)
	{
		$timestamp7DaysAgo =
			mktime(date("H"), date("i"), date("s"), date("m"), date("d")-7, date("Y"));
		$timestampNow =
			mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y"));
		$dateToday = date("Y-m-d");
		$rv = array();
		$i=0;
		$firstuid = null;
		foreach((array)$_headers as $header)
		{
			$i++;
			$data = array();
			//error_log(__METHOD__.array2string($header));
			$result = array(
				"id" => $header['uid'],
				"group" => "mail", // activate the action links for mail objects
			);
			$message_uid = $header['uid'];
			$data['uid'] = $message_uid;
			$data['row_id']=$this->createRowID($_folderName,$message_uid);

			//_debug_array($header);
			#if($i<10) {$i++;continue;}
			#if($i>20) {continue;} $i++;
			// create the listing of subjects
			$maxSubjectLength = 60;
			$maxAddressLength = 20;
			$maxSubjectLengthBold = 50;
			$maxAddressLengthBold = 14;

			$flags = "";
			if(!empty($header['recent'])) $flags .= "R";
			if(!empty($header['flagged'])) $flags .= "F";
			if(!empty($header['answered'])) $flags .= "A";
			if(!empty($header['forwarded'])) $flags .= "W";
			if(!empty($header['deleted'])) $flags .= "D";
			if(!empty($header['seen'])) $flags .= "S";
			if(!empty($header['label1'])) $flags .= "1";
			if(!empty($header['label2'])) $flags .= "2";
			if(!empty($header['label3'])) $flags .= "3";
			if(!empty($header['label4'])) $flags .= "4";
			if(!empty($header['label5'])) $flags .= "5";

			$data["status"] = "<span class=\"status_img\"></span>";
			//error_log(__METHOD__.array2string($header).' Flags:'.$flags);

			// the css for this row
			$is_recent=false;
			$css_styles = array("mail");
			if ($header['deleted']) {
				$css_styles[] = 'deleted';
			}
			if ($header['recent'] && !($header['deleted'] || $header['seen'] || $header['answered'] || $header['forwarded'])) {
				$css_styles[] = 'recent';
				$is_recent=true;
			}
			if ($header['priority'] < 3) {
				$css_styles[] = 'prio_high';
			}
			if ($header['flagged']) {
				$css_styles[] = 'flagged';
/*
				if (!$header['seen'])
				{
					$css_styles[] = 'flagged_unseen';
				}
				else
				{
					$css_styles[] = 'flagged_seen';
				}
*/
			}
			if (!$header['seen']) {
				$css_styles[] = 'unseen'; // different status image for recent // solved via css !important
			}
			if ($header['answered']) {
				$css_styles[] = 'replied';
			}
			if ($header['forwarded']) {
				$css_styles[] = 'forwarded';
			}
			if ($header['label1']) {
				$css_styles[] = 'labelone';
			}
			if ($header['label2']) {
				$css_styles[] = 'labeltwo';
			}
			if ($header['label3']) {
				$css_styles[] = 'labelthree';
			}
			if ($header['label4']) {
				$css_styles[] = 'labelfour';
			}
			if ($header['label5']) {
				$css_styles[] = 'labelfive';
			}

			//error_log(__METHOD__.array2string($css_styles));
			//if (in_array("check", $cols))
			// don't overwrite check with "false" as this forces the grid to
			// deselect the row - sending "0" doesn't do that
			//if (in_array("check", $cols)) $data["check"] = $previewMessage == $header['uid'] ? true : 0;// $row_selected; //TODO:checkbox true or false
			//$data["check"] ='<input  style="width:12px; height:12px; border: none; margin: 1px;" class="{row_css_class}" type="checkbox" id="msgSelectInput" name="msg[]" value="'.$message_uid.'"
			//	onclick="toggleFolderRadio(this, refreshTimeOut)">';

			if (in_array("subject", $cols))
			{
				// filter out undisplayable characters
				$search = array('[\016]','[\017]',
					'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
					'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');
				$replace = '';

				$header['subject'] = preg_replace($search,$replace,$header['subject']);
				$headerSubject = $header['subject'];//mail_bo::htmlentities($header['subject'],$this->charset);
				$header['subject'] = $headerSubject;
				// curly brackets get messed up by the template!
				$header['subject'] = str_replace(array('{','}'),array('&#x7B;','&#x7D;'),$header['subject']);

				if (!empty($header['subject'])) {
					// make the subject shorter if it is to long
					$fullSubject = $header['subject'];
					$subject = $header['subject'];
					#$this->t->set_var('attachments', $header['attachment']);
				} else {
					$subject = @htmlspecialchars('('. lang('no subject') .')', ENT_QUOTES, $this->charset);
				}

				$data["subject"] = $subject; // the mailsubject
			}

			//_debug_array($header);
			if (in_array("attachments", $cols))
			{
				if($header['mimetype'] == 'multipart/mixed' ||
					$header['mimetype'] == 'multipart/signed' ||
					$header['mimetype'] == 'multipart/related' ||
					$header['mimetype'] == 'multipart/report' ||
					$header['mimetype'] == 'text/calendar' ||
					$header['mimetype'] == 'text/html' ||
					substr($header['mimetype'],0,11) == 'application' ||
					substr($header['mimetype'],0,5) == 'audio' ||
					substr($header['mimetype'],0,5) == 'video' ||
					$header['mimetype'] == 'multipart/alternative')
				{
					$linkDataAttachments = array (
						'menuaction'    => 'mail.uidisplay.displayAttachments',
						'showHeader'	=> 'false',
						'mailbox'    => base64_encode($_folderName),
						'uid'		=> $header['uid'],
						'id'		=> $header['id'],
					);
					$windowName =  'displayMessage_'.$header['uid'];

					$image = html::image('mail','attach');
					if (//$header['mimetype'] != 'multipart/mixed' &&
						$header['mimetype'] != 'multipart/signed'
					)
					{
						if ($this->mail_bo->icServer->_connected != 1)
						{
							$this->mail_bo->openConnection($this->profileID); // connect to the current server
							$this->mail_bo->reopen($_folderName);
						}
						$attachments = $this->mail_bo->getMessageAttachments($header['uid'],$_partID='', $_structure='', $fetchEmbeddedImages=true, $fetchTextCalendar=false, $resolveTNEF=false);
						if (count($attachments)<1) $image = '&nbsp;';
					}
					if (count($attachments)>0) $image = "<a name=\"subject_url\" href=\"#\"
						onclick=\"fm_handleAttachmentClick(false,'".$GLOBALS['egw']->link('/index.php',$linkDataAttachments)."', '".$windowName."', this); return false;\"
						title=\"".$header['subject']."\">".$image."</a>";

					$attachmentFlag = $image;
				} else {
					$attachmentFlag ='&nbsp;';
				}
				// show priority flag
				if ($header['priority'] < 3) {
					 $image = html::image('mail','prio_high');
				} elseif ($header['priority'] > 3) {
					$image = html::image('mail','prio_low');
				} else {
					$image = '';
				}
				// show a flag for flagged messages
				$imageflagged ='';
				if ($header['flagged'])
				{
					$imageflagged = html::image('mail','unread_flagged_small');
				}
				$data["attachments"] = $image.$attachmentFlag.$imageflagged; // icon for attachments available
			}

			// sent or draft or template folder -> to address
			if (in_array("toaddress", $cols))
			{
				if(!empty($header['to_name'])) {
					list($mailbox, $host) = explode('@',$header['to_address']);
					$senderAddress  = imap_rfc822_write_address($mailbox,
							$host,
							$header['to_name']);
				} else {
					$senderAddress  = $header['to_address'];
				}
				$linkData = array
				(
					'menuaction'    => 'mail.uicompose.compose',
					'send_to'	=> base64_encode($senderAddress)
				);
				$windowName = 'compose_'.$header['uid'];

				// sent or drafts or template folder means foldertype > 0, use to address instead of from
				$header2add = $header['to_address'];//mail_bo::htmlentities($header['to_address'],$this->charset);
				$header['to_address'] = $header2add;
				if (!empty($header['to_name'])) {
					$header2name = $header['to_name'];//mail_bo::htmlentities($header['to_name'],$this->charset);
					$header['to_name'] = $header2name;

					$sender_name	= $header['to_name'];
					$full_address	= $header['to_name'].' <'.$header['to_address'].'>';
				} else {
					$sender_name	= $header['to_address'];
					$full_address	= $header['to_address'];
				}
				//$data["toaddress"] = "<nobr><a href=\"#\" onclick=\"fm_handleComposeClick(false,'".$GLOBALS['egw']->link('/index.php',$linkData)."', '".$windowName."', this); return false;\" title=\"".@htmlspecialchars($full_address, ENT_QUOTES | ENT_IGNORE, $this->charset,false)."\">".@htmlspecialchars($sender_name, ENT_QUOTES | ENT_IGNORE, $this->charset,false)."</a></nobr>";
				$data["toaddress"] = $full_address;
			}

			//fromaddress
			if (in_array("fromaddress", $cols))
			{
				$header2add = $header['sender_address'];//mail_bo::htmlentities($header['sender_address'],$this->charset);
				$header['sender_address'] = $header2add;
				if (!empty($header['sender_name'])) {
					$header2name = $header['sender_name'];//mail_bo::htmlentities($header['sender_name'],$this->charset);
					$header['sender_name'] = $header2name;

					$sender_name	= $header['sender_name'];
					$full_address	= $header['sender_name'].' <'.$header['sender_address'].'>';
				} else {
					$sender_name	= $header['sender_address'];
					$full_address	= $header['sender_address'];
				}
				if(!empty($header['sender_name'])) {
					list($mailbox, $host) = explode('@',$header['sender_address']);
					$senderAddress  = imap_rfc822_write_address($mailbox,
							$host,
							$header['sender_name']);
				} else {
					$senderAddress  = $header['sender_address'];
				}
				/*
				$linkData = array
				(
					'menuaction'    => 'mail.uicompose.compose',
					'send_to'	=> base64_encode($senderAddress)
				);
				$windowName = 'compose_'.$header['uid'];

				$data["fromaddress"] = "<nobr><a href=\"#\" onclick=\"fm_handleComposeClick(false,'".$GLOBALS['egw']->link('/index.php',$linkData)."', '".$windowName."', this); return false;\" title=\"".@htmlspecialchars($full_address, ENT_QUOTES | ENT_IGNORE, $this->charset,false)."\">".@htmlspecialchars($sender_name, ENT_QUOTES | ENT_IGNORE, $this->charset,false)."</a></nobr>";
				*/
				$data["fromaddress"] = $full_address;
			}
			if (in_array("date", $cols))
			{
				/*
				if ($dateToday == mail_bo::_strtotime($header['date'],'Y-m-d')) {
 				    $dateShort = mail_bo::_strtotime($header['date'],($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i:s a':'H:i:s'));
				} else {
					$dateShort = mail_bo::_strtotime($header['date'],str_replace('Y','y',$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']).' '.
						($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ? 'h:i a' : 'H:i'));
				}
				*/
				$data["date"] = $header['date'];//$dateShort;//'<nobr><span style="font-size:10px" title="'.$dateLong.'">'.$dateShort.'</span></nobr>';
			}
			if (in_array("modified", $cols))
			{
				$data["modified"] = $header['internaldate'];
			}

			if (in_array("size", $cols))
				$data["size"] = $header['size']; /// size


			/*
			//TODO: url_add_to_addressbook isn't in any of the templates.
			//If you want to use it, you need to adopt syntax to the new addressbook (popup)
			$this->t->set_var('url_add_to_addressbook',$GLOBALS['egw']->link('/index.php',$linkData));
			*/
			//$this->t->set_var('msg_icon_sm',$msg_icon_sm);

			//$this->t->set_var('phpgw_images',EGW_IMAGES);
			//$result["data"] = $data;
			$data["class"] = implode(' ', $css_styles);
			$rv[] = $data;
			//error_log(__METHOD__.__LINE__.array2string($result));
		}
		return $rv;
	}

	/**
	 * display messages header lines
	 *
	 * all params are passed as GET Parameters
	 */
	function displayHeader()
	{
		if(isset($_GET['id'])) $rowID	= $_GET['id'];
		if(isset($_GET['part'])) $partID = $_GET['part'];

		$hA = self::splitRowID($rowID);
		$uid = $hA['msgUID'];
		$mailbox = $hA['folder'];

		//$transformdate	=& CreateObject('felamimail.transformdate');
		//$htmlFilter	=& CreateObject('felamimail.htmlfilter');
		//$uiWidgets	=& CreateObject('felamimail.uiwidgets');
		$this->mail_bo->reopen($mailbox);
		$rawheaders	= $this->mail_bo->getMessageRawHeader($uid, $partID);

		$webserverURL	= $GLOBALS['egw_info']['server']['webserver_url'];

		#$nonDisplayAbleCharacters = array('[\016]','[\017]',
		#		'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
		#		'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');

		#print "<pre>";print_r($rawheaders);print"</pre>";exit;

		// add line breaks to $rawheaders
		$newRawHeaders = explode("\n",$rawheaders);
		reset($newRawHeaders);

		// reset $rawheaders
		$rawheaders 	= "";
		// create it new, with good line breaks
		reset($newRawHeaders);
		while(list($key,$value) = @each($newRawHeaders)) {
			$rawheaders .= wordwrap($value, 90, "\n     ");
		}

		$this->mail_bo->closeConnection();

		header('Content-type: text/html; charset=iso-8859-1');
		print '<pre>'. htmlspecialchars($rawheaders, ENT_NOQUOTES, 'iso-8859-1') .'</pre>';

	}

	/**
	 * save messages on disk or filemanager, or display it in popup
	 *
	 * all params are passed as GET Parameters
	 */
	function saveMessage()
	{
		$display = false;
		if(isset($_GET['id'])) $rowID	= $_GET['id'];
		if(isset($_GET['part'])) $partID		= $_GET['part'];
		if (isset($_GET['location'])&& ($_GET['location']=='display'||$_GET['location']=='filemanager')) $display	= $_GET['location'];

		$hA = self::splitRowID($rowID);
		$uid = $hA['msgUID'];
		$mailbox = $hA['folder'];

		$this->mail_bo->reopen($mailbox);

		$message = $this->mail_bo->getMessageRawBody($uid, $partID);
		$headers = $this->mail_bo->getMessageHeader($uid, $partID);

		$this->mail_bo->closeConnection();

		$GLOBALS['egw']->session->commit_session();
		if ($display==false)
		{
			$subject = str_replace('$$','__',mail_bo::decode_header($headers['SUBJECT']));
			header ("Content-Type: message/rfc822; name=\"". $subject .".eml\"");
			header ("Content-Disposition: attachment; filename=\"". $subject .".eml\"");
			header("Expires: 0");
			// the next headers are for IE and SSL
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Pragma: public");

			echo $message;

			$GLOBALS['egw']->common->egw_exit();
			exit;
		}
		//elseif ($display=='filemanager') // done in vfsSaveMessage
		//{
		//}
		else
		{
			header('Content-type: text/html; charset=iso-8859-1');
			print '<pre>'. htmlspecialchars($message, ENT_NOQUOTES, 'iso-8859-1') .'</pre>';
		}
	}

	/**
	 * Save an Message in the vfs
	 *
	 * @param string|array $ids use splitRowID, to separate values
	 * @param string $path path in vfs (no egw_vfs::PREFIX!), only directory for multiple id's ($ids is an array)
	 * @return string javascript eg. to close the selector window
	 */
	function vfsSaveMessage($ids,$path)
	{
		return "alert('".__METHOD__.'("'.array2string($id).'","'.$path."\")'); window.close();";
/*
		if (is_array($ids) && !egw_vfs::is_writable($path) || !is_array($ids) && !egw_vfs::is_writable(dirname($path)))
		{
			return 'alert("'.addslashes(lang('%1 is NOT writable by you!',$path)).'"); window.close();';
		}
		foreach((array)$ids as $id)
		{
			$hA = self::splitRowID($id);
			$uid = $hA['msgUID'];
			$mailbox = $hA['folder'];
			if ($mb != $this->mail_bo->mailbox) $this->mail_bo->reopen($mb = $mailbox);
			$message = $this->mail_bo->getMessageRawBody($uid, $partID='');
			if (!($fp = egw_vfs::fopen($file=$path.($name ? '/'.$name : ''),'wb')) ||
				!fwrite($fp,$message))
			{
				$err .= 'alert("'.addslashes(lang('Error saving %1!',$file)).'");';
			}
			if ($fp) fclose($fp);
		}
		$this->mail_bo->closeConnection();

		return $err.'window.close();';
*/
	}

	/**
	 * getFolderStatus - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 * gets the counters and sets the text of a treenode if needed (unread Messages found)
	 * @return nothing
	 */
	function ajax_setFolderStatus($_folder)
	{
		//error_log(__METHOD__.__LINE__.array2string($_folder));
		if ($_folder)
		{
			$del = $this->mail_bo->getHierarchyDelimiter(false);
			$oA = array();
			foreach ($_folder as $_folderName)
			{
				list($profileID,$folderName) = explode(self::$delimiter,$_folderName,2);
				if (is_numeric($profileID))
				{
					if ($profileID != $this->mail_bo->profileID) continue; // only current connection
					if ($folderName)
					{
						$fS = $this->mail_bo->getFolderStatus($folderName,false);
						//error_log(__METHOD__.__LINE__.array2string($fS));
						if ($fS['unseen'])
						{
							$oA[$_folderName] = '<b>'.$fS['shortDisplayName'].' ('.$fS['unseen'].')</b>';

						}
					}
				}
			}
			//error_log(__METHOD__.__LINE__.array2string($oA));
			if ($oA)
			{
				$response = egw_json_response::get();
				$response->call('mail_setFolderStatus',$oA,'mail');
			}
		}
	}

	/**
	 * empty changeProfile - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 *
	 * @return nothing
	 */
	function ajax_changeProfile($icServerID)
	{
		if ($icServerID && $icServerID != $this->mail_bo->profileID)
		//error_log(__METHOD__.__LINE__.' change Profile to ->'.$icServerID);
		$this->changeProfile($icServerID);
		$response = egw_json_response::get();
		$response->call('egw_refresh',lang('changed profile'),'mail');
	}

	/**
	 * empty trash folder - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 *
	 * @return nothing
	 */
	function ajax_emptyTrash()
	{
		$trashFolder = $this->mail_bo->getTrashFolder();
		if(!empty($trashFolder)) {
			$this->mail_bo->compressFolder($trashFolder);
		}
		$response = egw_json_response::get();
		$response->call('egw_refresh',lang('empty trash'),'mail');
	}

	/**
	 * compress folder - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 * fetches the current folder from session and compresses it
	 * @return nothing
	 */
	function ajax_compressFolder()
	{
		$this->mail_bo->restoreSessionData();
		$folder = $this->mail_bo->sessionData['maibox'];
		if ($this->mail_bo->folderExists($folder))
		{
			if(!empty($folder)) {
				$this->mail_bo->compressFolder($folder);
			}
			$response = egw_json_response::get();
			$response->call('egw_refresh',lang('compress folder').': '.$folder,'mail');
		}
	}

	/**
	 * flag messages as read, unread, flagged, ...
	 *
	 * @param string _flag name of the flag
	 * @param array _messageList list of UID's
	 *
	 * @return xajax response
	 */
	function ajax_flagMessages($_flag, $_messageList)
	{
		if(mail_bo::$debug) error_log(__METHOD__."->".$_flag.':'.print_r($_messageList,true));
		if ($_messageList=='all' || !empty($_messageList['msg']))
		{
			if ($_messageList=='all')
			{
				// we have no folder information
				$folder=null;
			}
			else
			{
				$uidA = self::splitRowID($_messageList['msg'][0]);
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
			}
			foreach($_messageList['msg'] as $rowID)
			{
				$hA = self::splitRowID($rowID);
				$messageList[] = $hA['msgUID'];
			}
			$this->mail_bo->flagMessages($_flag, ($_messageList=='all' ? 'all':$messageList),$folder);
		}
		else
		{
			if(mail_bo::$debug) error_log(__METHOD__."-> No messages selected.");
		}

		// unset preview, as refresh would mark message again read
/*
		if ($_flag == 'unread' && in_array($this->sessionData['previewMessage'], $_messageList['msg']))
		{
			unset($this->sessionData['previewMessage']);
			$this->saveSessionData();
		}
*/
		$response = egw_json_response::get();
		$response->call('egw_refresh',lang('flagged %1 messages as %2 in %3',count($_messageList['msg']),$_flag,$folder),'mail');
	}

	/**
	 * delete messages
	 *
	 * @param array _messageList list of UID's
	 *
	 * @return xajax response
	 */
	function ajax_deleteMessages($_messageList)
	{
		if(mail_bo::$debug) error_log(__METHOD__."->".$_flag.':'.print_r($_messageList,true));
		if ($_messageList=='all' || !empty($_messageList['msg']))
		{
			if ($_messageList=='all')
			{
				// we have no folder information
				$folder=null;
			}
			else
			{
				$uidA = self::splitRowID($_messageList['msg'][0]);
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
			}
			foreach($_messageList['msg'] as $rowID)
			{
				$hA = self::splitRowID($rowID);
				$messageList[] = $hA['msgUID'];
			}
			$this->mail_bo->deleteMessages(($_messageList=='all' ? 'all':$messageList),$folder);
		}
		else
		{
			if(mail_bo::$debug) error_log(__METHOD__."-> No messages selected.");
		}
		$response = egw_json_response::get();
		$response->call('egw_refresh',lang('deleted %1 messages in %2',count($_messageList['msg']),$folder),'mail');
	}
}
