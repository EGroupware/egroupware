<?php
/**
 * EGroupware - Mail - interface class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2013-2016 by EGroupware GmbH <info-AT-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Vfs;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Etemplate\KeyManager;
use EGroupware\Api\Mail;

/**
 * Mail User Interface
 *
 * As we do NOT want to connect to previous imap server, when a profile change is triggered
 * by user get_rows and ajax_changeProfile are not static methods and instanciates there own
 * mail_ui object.
 *
 * If they detect a profile change is to be triggered they call:
 *		$mail_ui = new mail_ui(false);	// not call constructor / connect to imap server
 *		$mail_ui->changeProfile($_profileID);
 * If no profile change is needed they just call:
 *		$mail_ui = new mail_ui();
 * Afterwards they use $mail_ui instead of $this.
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
		'displayMessage'	=> True,
		'displayImage'		=> True,
		'getAttachment'		=> True,
		'download_zip'		=> True,
		'saveMessage'	=> True,
		'vfsSaveMessages' => True,
		'loadEmailBody'	=> True,
		'importMessage'	=> True,
		'importMessageFromVFS2DraftAndDisplay'=>True,
		'subscription'	=> True,
		'folderManagement' => true,
		'smimeExportCert' => true
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
	 * nextMatch name for index
	 *
	 * @var string
	 */
	static $nm_index = 'nm';

	/**
	 * instance of Mail
	 *
	 * @var Mail
	 */
	var $mail_bo;

	/**
	 * definition of available / supported search types
	 *
	 * @var array
	 */
	var $searchTypes = array(
		'quick'		=> 'quicksearch',	// lang('quicksearch')
		'quickwithcc'=> 'quicksearch (with cc)',	// lang('quicksearch (with cc)')
		'subject'	=> 'subject',		// lang('subject')
		'body'		=> 'message body',	// lang('message body')
		'from'		=> 'from',			// lang('from')
		'to'		=> 'to',			// lang('to')
		'cc'		=> 'cc',			// lang('cc')
		'text'		=> 'whole message',	// lang('whole message')
		'larger'		=> 'greater than',	// lang('greater than')
		'smaller'		=> 'less than',	// lang('less than')
		'bydate' 	=> 'Selected date range (with quicksearch)',// lang('Selected date range (with quicksearch)')
	);

	/**
	 * definition of available / supported status types
	 *
	 * @var array
	 */
	var $statusTypes = array(
		'any'		=> 'any status',// lang('any status')
		'flagged'	=> 'flagged',	// lang('flagged')
		'unseen'	=> 'unread',	// lang('unread')
		'answered'	=> 'replied',	// lang('replied')
		'seen'		=> 'read',		// lang('read')
		'deleted'	=> 'deleted',	// lang('deleted')
	);

	/**
	 * Constructor
	 *
	 * @param boolean $run_constructor =true false: no not run constructor and therefore do NOT connect to imap server
	 */
	function __construct($run_constructor=true)
	{
		$this->mail_tree = new mail_tree($this);
		if (!$run_constructor) return;

		if (Mail::$debugTimes) $starttime = microtime (true);
		// no autohide of the sidebox, as we use it for folderlist now.
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);

		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']) && !empty($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
		{
			self::$icServerID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
		}
		if ($_GET["resetConnection"])
		{
			unset($_GET["resetConnection"]);
			if (Mail::$debug) error_log(__METHOD__.__LINE__.' Connection Reset triggered: for Profile with ID:'.self::$icServerID);
			Mail::unsetCachedObjects(self::$icServerID);
		}

		try {
			$this->mail_bo = Mail::getInstance(true,self::$icServerID, true, false, true);
			if (Mail::$debug) error_log(__METHOD__.__LINE__.' Fetched IC Server:'.self::$icServerID.'/'.$this->mail_bo->profileID.':'.function_backtrace());
			//error_log(__METHOD__.__LINE__.array2string($this->mail_bo->icServer));

			// RegEx to minimize extra openConnection
			$needle = '/^(?!mail)/';
			if (!preg_match($needle,$_GET['menuaction']) && !Api\Json\Request::isJSONRequest())
			{
				//error_log(__METHOD__.__LINE__.' Fetched IC Server openConnection:'.self::$icServerID.'/'.$this->mail_bo->profileID.':'.function_backtrace());
				//openConnection gathers SpecialUseFolderInformation and Delimiter Info
				$this->mail_bo->openConnection(self::$icServerID);
			}
		}
		catch (Exception $e)
		{
			// we need this to handle failed JSONRequests
			if (Api\Json\Request::isJSONRequest() && $_GET['menuaction'] != 'mail.mail_ui.index')
			{
				$response = Api\Json\Response::get();
				$response->call('egw.message',$e->getMessage(),'error');
			}
			// redirect to mail wizard to handle it (redirect works for ajax too), unless index is called. we want the sidebox
			if ($_GET['menuaction'] != 'mail.mail_ui.index') self::callWizard($e->getMessage(),true,'error',false);
		}
		if (Mail::$debugTimes) Mail::logRunTimes($starttime,null,'',__METHOD__.__LINE__);
	}

	/**
	 * callWizard
	 *
	 * @param string $message
	 * @param boolean $exit If true, will call exit() after opening the wizardpopup
	 * @param string $msg_type = 'success' message type
	 */
	static function callWizard($message, $exit=true, $msg_type='success',$reset_sidebox_on_index=true)
	{
		//error_log(__METHOD__."('$message', $exit) ".function_backtrace());
		$linkData=(self::$icServerID ? array(
				'menuaction' => 'mail.mail_wizard.edit',
				'acc_id' => self::$icServerID,
			) : array(
				'menuaction' => 'mail.mail_wizard.add',
			)) + array(
				'msg' => $message,
				'msg_type' => $msg_type
			);

		// if we already called the wizard, ignore further calls for 5min = 300s
		if (!Api\Cache::getSession(__CLASS__, $id='call-wizzard-'.self::$icServerID))
		{
			Api\Cache::setSession(__CLASS__, $id, self::$icServerID, 300);
		}
		// ignore further calls / one popup is enough
		elseif($exit)
		{
			exit;
		}
		else
		{
			return;
		}

		if (Api\Json\Response::isJSONResponse())
		{
			$response = Api\Json\Response::get();
			$windowName = "editMailAccount".self::$icServerID;
			$response->call("egw.open_link", Egw::link('/index.php', $linkData), $windowName, "600x480",null,true);
			Framework::message($message, 'error');
			if ($_GET['menuaction'] == 'mail.mail_ui.index' && $reset_sidebox_on_index)
			{
				$response->call('framework.setSidebox','mail',array(),'md5');
			}
			if ($exit)
			{
				exit();
			}
		}
		else	// regular GET request eg. in idots template
		{
			$windowName = "editMailAccount".self::$icServerID;
			Framework::popup(Framework::link('/index.php',$linkData),$windowName);
			$GLOBALS['egw']->framework->render($message,'',true);
			if ($exit)
			{
				exit();
			}
		}
	}

	/**
	 * changeProfile
	 *
	 * @param int $_icServerID
	 * @param boolean $unsetCache
	 *
	 * @throws Api\Exception
	 */
	function changeProfile($_icServerID,$unsetCache=false)
	{
		if (Mail::$debugTimes) $starttime = microtime (true);
		if (self::$icServerID != $_icServerID)
		{
			self::$icServerID = $_icServerID;
		}
		if (Mail::$debug) error_log(__METHOD__.__LINE__.'->'.self::$icServerID.'<->'.$_icServerID);

		if ($unsetCache) Mail::unsetCachedObjects(self::$icServerID);
		$this->mail_bo = Mail::getInstance(false,self::$icServerID,true, false, true);
		if (Mail::$debug) error_log(__METHOD__.__LINE__.' Fetched IC Server:'.self::$icServerID.'/'.$this->mail_bo->profileID.':'.function_backtrace());
		// no icServer Object: something failed big time
		if (!isset($this->mail_bo) || !isset($this->mail_bo->icServer) || $this->mail_bo->icServer->ImapServerId<>$_icServerID)
		{
			self::$icServerID = $_icServerID;
			throw new Api\Exception('Profile change failed!');
		}

		// save session varchar
		$oldicServerID =& Api\Cache::getSession('mail','activeProfileID');
		if ($oldicServerID != self::$icServerID)
		{
			$this->mail_bo->openConnection(self::$icServerID);
		}
		if (true) $oldicServerID = self::$icServerID;
		if (!Mail::storeActiveProfileIDToPref($this->mail_bo->icServer, self::$icServerID, true ))
		{
			throw new Api\Exception(__METHOD__." failed to change Profile to $_icServerID");
		}

		if (Mail::$debugTimes) Mail::logRunTimes($starttime,null,'',__METHOD__.__LINE__);
	}

	/**
	 * Ajax function to request next branch of a tree branch
	 */
	static function ajax_tree_autoloading ($_id = null)
	{
		$mail_ui = new mail_ui();
		$id = $_id ? $_id : $_GET['id'];
		Etemplate\Widget\Tree::send_quote_json($mail_ui->mail_tree->getTree($id,'',1,false));
	}

	/**
	 * Subscription popup window
	 *
	 * @param array $content
	 * @param type $msg
	 */
	function subscription(array $content=null ,$msg=null)
	{
		$stmpl = new Etemplate('mail.subscribe');

		if(is_array($content))
		{
			$profileId = $content['profileId'];
		}
		elseif (!($profileId = (int)$_GET['acc_id']))
		{
			Framework::window_close('Missing acc_id!');
		}
		// Initial tree's options, the rest would be loaded dynamicaly by autoloading,
		// triggered from client-side. Also, we keep this here as
		$sel_options['foldertree'] =  $this->mail_tree->getTree(null,$profileId,1,true,false,true);

		//Get all subscribed folders
		// as getting all subscribed folders is very fast operation
		// we can use it to get a comparison base for folders which
		// got subscribed or unsubscribed by the user
		try {
			$subscribed = $this->mail_bo->icServer->listSubscribedMailboxes('',0,true);
		} catch (Exception $ex) {
			Framework::message($ex->getMessage());
		}

		if (!is_array($content))
		{
			$content['foldertree'] = array();

			foreach ($subscribed as $folder)
			{
				$folderName = $profileId . self::$delimiter . $folder['MAILBOX'];
				array_push($content['foldertree'], $folderName);
			}
		}
		else
		{
			$button = @key($content['button']);
			switch ($button)
			{
				case 'save':
				case 'apply':
				{
					// do not let user (un)subscribe namespace roots eg. "other", "user" or "INBOX", same for tree-root/account itself
					$namespace_roots = array($profileId);
					foreach($this->mail_bo->_getNameSpaces() as $namespace)
					{
						$namespace_roots[] = $profileId . self::$delimiter . str_replace($namespace['delimiter'], '', $namespace['prefix']);
					}
					$to_unsubscribe = $to_subscribe = array();
					foreach ($content['foldertree'] as $path => $value)
					{
						list(,$node) = explode($profileId.self::$delimiter, $path);
						if ($node)
						{
							if (is_array($subscribed) && $subscribed[$node] && !$value['value']) $to_unsubscribe []= $node;
							if (is_array($subscribed) && !$subscribed[$node] && $value['value']) $to_subscribe [] = $node;
							if ($value['value']) $cont[] = $path;
						}

					}
					$content['foldertree'] = $cont;
					// set foldertree options to basic node in order to avoid initial autoloading
					// from client side, as no options would trigger that.
					$sel_options['foldertree'] = array('id' => '0', 'item'=> array());
					foreach(array_merge($to_subscribe, $to_unsubscribe) as $mailbox)
					{
						if (in_array($profileId.self::$delimiter.$mailbox, $namespace_roots, true))
						{
							continue;
						}
						$subscribe = in_array($mailbox, $to_subscribe);
						try {
							$this->mail_bo->icServer->subscribeMailbox($mailbox, $subscribe);
						}
						catch (Exception $ex)
						{
							$msg_type = 'error';
							if ($subscribe)
							{
								$msg .= lang('Failed to subscribe folder %1!', $mailbox).' '.$ex->getMessage();
							}
							else
							{
								$msg .= lang('Failed to unsubscribe folder %1!', $mailbox).' '.$ex->getMessage();
							}
						}
					}
					if (!isset($msg))
					{
						$msg_type = 'success';
						if ($to_subscribe || $to_unsubscribe)
						{
							$msg = lang('Subscription successfully saved.');
						}
						else
						{
							$msg = lang('Nothing to change.');
						}
					}
					// update foldertree in main window
					$parentFolder='INBOX';
					$refreshData = array(
						$profileId => lang($parentFolder),
					);
					$response = Api\Json\Response::get();
					foreach($refreshData as $folder => &$name)
					{
						$name = $this->mail_tree->getTree($folder, $profileId,1,true,true,true);
					}
					// give success/error message to opener and popup itself
					//$response->call('opener.app.mail.subscription_refresh',$refreshData);
					$response->call('opener.app.mail.mail_reloadNode',$refreshData);

					Framework::refresh_opener($msg, 'mail', null, null, null, null, null, $msg_type);
					if ($button == 'apply')
					{
						Framework::message($msg, $msg_type);
						break;
					}
				}
				case 'cancel':
				{
					Framework::window_close();
				}
			}
		}

		$preserv['profileId'] = $profileId;

		$readonlys = array();

		$stmpl->exec('mail.mail_ui.subscription', $content,$sel_options,$readonlys,$preserv,2);
	}

	const DEFAULT_IMAGE_PROXY = 'https://';
	const EGROUPWARE_IMAGE_PROXY = 'https://proxy.egroupware.org/7d510d4f7966f97ab56580425ddb4811e707c018/';
	const IMAGE_PROXY_CONFIG = 'http_image_proxy';

	/**
	 * Get image proxy / http:// replacement for image urls
	 *
	 * @return string
	 */
	protected static function image_proxy()
	{
		$configs = Api\Config::read('mail');
		$image_proxy = $configs[self::IMAGE_PROXY_CONFIG] ?? self::DEFAULT_IMAGE_PROXY;
		if (strpos(self::EGROUPWARE_IMAGE_PROXY, parse_url($image_proxy, PHP_URL_HOST)))
		{
			$image_proxy = self::EGROUPWARE_IMAGE_PROXY;
		}
		return $image_proxy;
	}

	/**
	 * Main mail page
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function index(array $content=null,$msg=null)
	{
		//error_log(__METHOD__.__LINE__.array2string($content));
		try	{
				if (!isset($this->mail_bo)) throw new Api\Exception\WrongUserinput(lang('Initialization of mail failed. Please use the Wizard to cope with the problem.'));
				//error_log(__METHOD__.__LINE__.function_backtrace());
				if (Mail::$debugTimes) $starttime = microtime (true);
				$this->mail_bo->restoreSessionData();
				$sessionFolder = $this->mail_bo->sessionData['mailbox'];
				if ($this->mail_bo->folderExists($sessionFolder))
				{
					$this->mail_bo->reopen($sessionFolder); // needed to fetch full set of capabilities
				}
				else
				{
					$sessionFolder = $this->mail_bo->sessionData['mailbox'] = 'INBOX';
				}
				//error_log(__METHOD__.__LINE__.' SessionFolder:'.$sessionFolder.' isToSchema:'.$toSchema);
				if (!is_array($content))
				{
					$content = array(
						self::$nm_index => Api\Cache::getSession('mail', 'index'),
					);
					if (!is_array($content[self::$nm_index]))
					{
						// These only set on first load
						$content[self::$nm_index] = array(
							'filter'         => 'any',	// filter is used to choose the mailbox
							'lettersearch'   => false,	// I  show a lettersearch
							'searchletter'   =>	false,	// I0 active letter of the lettersearch or false for [all]
							'start'          =>	0,		// IO position in list
							'order'          =>	'date',	// IO name of the column to sort after (optional for the sortheaders)
							'sort'           =>	'DESC',	// IO direction of the sort: 'ASC' or 'DESC'
							'no_columnselection' => $this->mail_bo->mailPreferences['previewPane'] == 'vertical'? true : false
						);
					}
					if (Api\Header\UserAgent::mobile()) $content[self::$nm_index]['header_row'] = 'mail.index.header_right';
				}

				// These must always be set, even if $content is an array
				$content[self::$nm_index]['cat_is_select'] = true;    // Category select is just a normal selectbox
				$content[self::$nm_index]['no_filter2'] = false;       // Disable second filter
				$content[self::$nm_index]['actions'] = self::get_actions();
				$content[self::$nm_index]['row_id'] = 'row_id';	     // is a concatenation of trim($GLOBALS['egw_info']['user']['account_id']):profileID:base64_encode(FOLDERNAME):uid
				$content[self::$nm_index]['placeholder_actions'] = array('composeasnew');
				$content[self::$nm_index]['get_rows'] = 'mail_ui::get_rows';
				$content[self::$nm_index]['num_rows'] = 0;      // Do not send any rows with initial request
				$content[self::$nm_index]['default_cols'] = 'avatar,status,attachments,subject,address,date,size';	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
				$content[self::$nm_index]['csv_fields'] = false;
				if ($msg)
				{
					$content['msg'] = $msg;
				}
				else
				{
					unset($msg);
					unset($content['msg']);
				}
				// call getQuotaRoot asynchronously in getRows by initiating a client Server roundtrip
				$quota = false;//$this->mail_bo->getQuotaRoot();
				if($quota !== false && $quota['limit'] != 'NOT SET') {
					$quotainfo = $this->quotaDisplay($quota['usage'], $quota['limit']);
					$content[self::$nm_index]['quota'] = $sel_options[self::$nm_index]['quota'] = $quotainfo['text'];
					$content[self::$nm_index]['quotainpercent'] = $sel_options[self::$nm_index]['quotainpercent'] =  (string)$quotainfo['percent'];
					$content[self::$nm_index]['quotaclass'] = $sel_options[self::$nm_index]['quotaclass'] = $quotainfo['class'];
					$content[self::$nm_index]['quotanotsupported'] = $sel_options[self::$nm_index]['quotanotsupported'] = "";
				} else {
					$content[self::$nm_index]['quota'] = $sel_options[self::$nm_index]['quota'] = lang("Quota not provided by server");
					$content[self::$nm_index]['quotaclass'] = $sel_options[self::$nm_index]['quotaclass'] = "mail_DisplayNone";
					$content[self::$nm_index]['quotanotsupported'] = $sel_options[self::$nm_index]['quotanotsupported'] = "mail_DisplayNone";
				}

				//$zstarttime = microtime (true);
				$sel_options[self::$nm_index]['foldertree'] = $this->mail_tree->getInitialIndexTree(null, $this->mail_bo->profileID, null, !$this->mail_bo->mailPreferences['showAllFoldersInFolderPane'],!$this->mail_bo->mailPreferences['showAllFoldersInFolderPane']);
				//$zendtime = microtime(true) - $zstarttime;
				//error_log(__METHOD__.__LINE__. " time used: ".$zendtime);
				$content[self::$nm_index]['selectedFolder'] = $this->mail_bo->profileID.self::$delimiter.(!empty($this->mail_bo->sessionData['mailbox'])?$this->mail_bo->sessionData['mailbox']:'INBOX');
				// since we are connected,(and selected the folder) we check for capabilities SUPPORTS_KEYWORDS to eventually add the keyword filters
				if ( $this->mail_bo->icServer->hasCapability('SUPPORTS_KEYWORDS'))
				{
					$this->statusTypes = array_merge($this->statusTypes,array(
						'keyword1'	=> 'important',//lang('important'),
						'keyword2'	=> 'job',	//lang('job'),
						'keyword3'	=> 'personal',//lang('personal'),
						'keyword4'	=> 'to do',	//lang('to do'),
						'keyword5'	=> 'later',	//lang('later'),
					));
				}
				else
				{
					$keywords = array('keyword1','keyword2','keyword3','keyword4','keyword5');
					foreach($keywords as &$k)
					{
						if (array_key_exists($k,$this->statusTypes)) unset($this->statusTypes[$k]);
					}
				}

				if (!isset($content[self::$nm_index]['foldertree'])) $content[self::$nm_index]['foldertree'] = $this->mail_bo->profileID.self::$delimiter.'INBOX';
				if (!isset($content[self::$nm_index]['selectedFolder'])) $content[self::$nm_index]['selectedFolder'] = $this->mail_bo->profileID.self::$delimiter.'INBOX';

				$content[self::$nm_index]['foldertree'] = $content[self::$nm_index]['selectedFolder'];

				if (is_null(Mail::$supportsORinQuery) || !isset(Mail::$supportsORinQuery[$this->mail_bo->profileID]))
				{
					Mail::$supportsORinQuery = Api\Cache::getCache(Api\Cache::INSTANCE, 'email', 'supportsORinQuery'.trim($GLOBALS['egw_info']['user']['account_id']), null, array(), 60*60*10);
					if (!isset(Mail::$supportsORinQuery[$this->mail_bo->profileID])) Mail::$supportsORinQuery[$this->mail_bo->profileID]=true;
				}
				if (!Mail::$supportsORinQuery[$this->mail_bo->profileID])
				{
					unset($this->searchTypes['quick']);
					unset($this->searchTypes['quickwithcc']);
				}
				$sel_options['cat_id'] = $this->searchTypes;
				//error_log(__METHOD__.__LINE__.array2string($sel_options['cat_id']));
				//error_log(__METHOD__.__LINE__.array2string($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveSearchType']));
				$content[self::$nm_index]['cat_id'] = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveSearchType'];
				$sel_options['filter'] = $this->statusTypes;
				$sel_options['filter2'] = array(''=>lang('No Sneak Preview in list'),1=>lang('Sneak Preview in list'));
				$content[self::$nm_index]['filter2'] = $GLOBALS['egw_info']['user']['preferences']['mail']['ShowDetails'];

				$etpl = new Etemplate('mail.index');
				//apply infolog_filter_change javascript method (hide/show of date filter form) over onchange filter
				$content[self::$nm_index]['cat_id_onchange'] = "app.mail.mail_searchtype_change()";
				// set the actions on tree
				$etpl->setElementAttribute(self::$nm_index.'[foldertree]','actions', $this->get_tree_actions());

				// sending preview toolbar actions
				if (!empty($content['mailSplitter'])) $etpl->setElementAttribute('mailPreview[toolbar]', 'actions', $this->get_toolbar_actions());

				// We need to send toolbar actions to client-side because view template needs them
				if (Api\Header\UserAgent::mobile()) $sel_options['toolbar'] = $this->get_toolbar_actions();

				//we use the category "filter" option as specifier where we want to search (quick, subject, from, to, etc. ....)
				if (empty($content[self::$nm_index]['cat_id']) || empty($content[self::$nm_index]['search']))
				{
					$content[self::$nm_index]['cat_id']=($content[self::$nm_index]['cat_id']?(!Mail::$supportsORinQuery[$this->mail_bo->profileID]&&($content[self::$nm_index]['cat_id']=='quick'||$content[self::$nm_index]['cat_id']=='quickwithcc')?'subject':$content[self::$nm_index]['cat_id']):(Mail::$supportsORinQuery[$this->mail_bo->profileID]?'quick':'subject'));
				}
				$readonlys = $preserv = array();
				if (Mail::$debugTimes) Mail::logRunTimes($starttime,null,'',__METHOD__.__LINE__);
		}
		catch (Exception $e)
		{
			// do not exit here. mail-tree should be build. if we exit here, we never get there.
			error_log(__METHOD__.__LINE__.$e->getMessage().($e->details?', '.$e->details:'').' Menuaction:'.$_GET['menuaction'].'.'.function_backtrace());
			if (isset($this->mail_bo))
			{
				if (empty($etpl))
				{
					$sel_options[self::$nm_index]['foldertree'] = $this->mail_tree->getInitialIndexTree(null, $this->mail_bo->profileID, null, !$this->mail_bo->mailPreferences['showAllFoldersInFolderPane'],!$this->mail_bo->mailPreferences['showAllFoldersInFolderPane']);
					$etpl = new Etemplate('mail.index');
				}
				$etpl->setElementAttribute(self::$nm_index.'[foldertree]','actions', $this->get_tree_actions(false));
			}
			$readonlys = $preserv = array();
			if (empty($content)) $content=array();

			self::callWizard($e->getMessage().($e->details?', '.$e->details:''),(isset($this->mail_bo)?false:true), 'error',false);
			//return false;
		}
		switch ($this->mail_bo->mailPreferences['previewPane'])
		{
			case "1"://preference used to be '1', now 'hide'
			case "hide":
				$etpl->setElementAttribute('splitter', 'template', 'mail.index.nosplitter');
				break;
			case "vertical":
				$etpl->setElementAttribute('mailSplitter', 'orientation', 'v');
				break;
			case "expand":
			case "fixed":
				$etpl->setElementAttribute('mailSplitter', 'orientation', 'h');
				if (!Api\Header\UserAgent::mobile()) $etpl->setElementAttribute('nm', 'template', 'mail.index.rows.horizental');
				break;
			default:
				$etpl->setElementAttribute('mailSplitter', 'orientation', 'v');
		}
		// send configured image proxy to client-side
		$content['image_proxy'] = self::image_proxy();
		$content['no_vfs'] = !$GLOBALS['egw_info']['user']['apps']['filemanager'];
		return $etpl->exec('mail.mail_ui.index',$content,$sel_options,$readonlys,$preserv);
	}

	/**
	 * Get tree actions / context menu for tree
	 *
	 * Changes here, may require to log out, as $content[self::$nm_index] get stored in session!
	 * @param {boolean} $imap_actions set to false if you want to avoid to talk to the imap-server
	 * @return array
	 */
	function get_tree_actions($imap_actions=true)
	{
		// Start at 2 so auto-added copy+paste actions show up as second group
		// Needed because there's no 'select all' action to push things down
		$group=1;
		// Set tree actions
		$tree_actions = array(
			'drop_move_mail' => array(
				'type' => 'drop',
				'acceptedTypes' => 'mail',
				'icon' => 'move',
				'caption' => 'Move to',
				'onExecute' => 'javaScript:app.mail.mail_move'
			),
			'drop_copy_mail' => array(
				'type' => 'drop',
				'acceptedTypes' => 'mail',
				'icon' => 'copy',
				'caption' => 'Copy to',
				'onExecute' => 'javaScript:app.mail.mail_copy'
			),
			'drop_cancel' => array(
				'icon' => 'cancel',
				'caption' => 'Cancel',
				'acceptedTypes' => 'mail',
				'type' => 'drop',
			),
			'drop_move_folder' => array(
				'caption' => 'Move folder',
				'hideOnDisabled' => true,
				'type' => 'drop',
				'acceptedTypes' => 'mailFolder',
				'onExecute' => 'javaScript:app.mail.mail_MoveFolder'
			),
			// Tree does support this one
			'add' => array(
				'caption' => 'Add Folder',
				'onExecute' => 'javaScript:app.mail.mail_AddFolder',
				'enabled'	=> 'javaScript:app.mail.mail_CheckFolderNoSelect',
				'group'		=> $group,
			),
			'edit' => array(
				'caption' => 'Rename Folder',
				'onExecute' => 'javaScript:app.mail.mail_RenameFolder',
				'enabled'	=> 'javaScript:app.mail.mail_CheckFolderNoSelect',
				'group'		=> $group,
			),
			'move' => array(
				'caption' => 'Move Folder',
				'type' => 'drag',
				'enabled'	=> 'javaScript:app.mail.mail_CheckFolderNoSelect',
				'dragType' => array('mailFolder'),
				'group'		=> $group,
			),
			'delete' => array(
				'caption' => 'Delete Folder',
				'enabled'	=> 'javaScript:app.mail.mail_CheckFolderNoSelect',
				'onExecute' => 'javaScript:app.mail.mail_DeleteFolder',
				'group'		=> $group,
			),
			'readall' => array(
				'group' => $group,
				'caption' => "<font color='#ff0000'>".lang('mark all as read')."</font>",
				'icon' => 'kmmsgread',
				'onExecute' => 'javaScript:app.mail.mail_flag',
				'hint' => 'mark all messages in folder as read',
				'toolbarDefault' => false
			),
			'subscribe' => array(
				'caption' => 'Subscribe folder ...',
				//'icon' => 'configure',
				'enabled'	=> 'javaScript:app.mail.mail_CheckFolderNoSelect',
				'onExecute' => 'javaScript:app.mail.edit_subscribe',
				'group'		=> $group
			),
			'unsubscribe' => array(
				'caption' => 'Unsubscribe folder',
				'enabled'	=> 'javaScript:app.mail.mail_CheckFolderNoSelect',
				'onExecute' => 'javaScript:app.mail.unsubscribe_folder',
				'group'		=> $group,
			),
			'foldermanagement' => array(
				'caption' => 'Folder Management ...',
				'icon' => 'folder_management',
				'enabled'	=> 'javaScript:app.mail.mail_CheckFolderNoSelect',
				'onExecute' => 'javaScript:app.mail.folderManagement',
				'group'		=> $group,
				'hideOnMobile' => true
			),
			'sieve' => array(
				'caption' => 'Mail filter',
				'onExecute' => 'javaScript:app.mail.edit_sieve',

				'enabled'	=> 'javaScript:app.mail.sieve_enabled',
				'icon' => 'mail/filter',	// funnel
				'hideOnMobile' => true
			),
			'vacation' => array(
				'caption' => 'Vacation notice',
				'icon' => 'mail/navbar',	// mail as in admin
				'onExecute' => 'javaScript:app.mail.edit_vacation',
				'enabled'	=> 'javaScript:app.mail.sieve_enabled',
			),
			'edit_account' => array(
				'caption' => 'Edit account ...',
				'icon' => 'configure',
				'onExecute' => 'javaScript:app.mail.edit_account',
			),
			'edit_acl'	=> array(
				'caption' => 'Edit folder ACL ...',
				'icon'	=> 'lock',
				'enabled'	=> 'javaScript:app.mail.acl_enabled',
				'onExecute' => 'javaScript:app.mail.edit_acl',
			),
			'predefined-addresses' => array(
				'caption' => 'Set predefined values for compose...',
				'onExecute' => 'javaScript:app.mail.set_predefined_addresses',
				'icon' => 'edit',
			)
		);
		// the preference prefaskformove controls actually if there is a popup on target or not
		// if there are multiple options there is a popup on target, 0 for prefaskformove means
		// that only move is available; 1 stands for move and cancel; 2 (should be the default if
		// not set); so we are assuming this, when not set
		if (isset($this->mail_bo->mailPreferences['prefaskformove']))
		{
			switch ($this->mail_bo->mailPreferences['prefaskformove'])
			{
				case 0:
					unset($tree_actions['drop_copy_mail']);
					unset($tree_actions['drop_cancel']);
					break;
				case 1:
					unset($tree_actions['drop_copy_mail']);
					break;
				default:
					// everything is fine
			}
		}
		//error_log(__METHOD__.__LINE__.' showAllFoldersInFolderPane:'.$this->mail_bo->mailPreferences['showAllFoldersInFolderPane'].'/'.$GLOBALS['egw_info']['user']['preferences']['mail']['showAllFoldersInFolderPane']);
		if ($this->mail_bo->mailPreferences['showAllFoldersInFolderPane'])
		{
			unset($tree_actions['subscribe']);
			unset($tree_actions['unsubscribe']);
		}
		++$group;	// put delete in own group
		switch($GLOBALS['egw_info']['user']['preferences']['mail']['deleteOptions'])
		{
			case 'move_to_trash':
				$tree_actions['empty_trash'] = array(
					'caption' => 'empty trash',
					'icon' => 'dhtmlxtree/MailFolderTrash',
					'onExecute' => 'javaScript:app.mail.mail_emptyTrash',
					'group'	=> $group,
				);
				break;
			case 'mark_as_deleted':
				$tree_actions['compress_folder'] = array(
					'caption' => 'compress folder',
					'icon' => 'dhtmlxtree/MailFolderTrash',
					'onExecute' => 'javaScript:app.mail.mail_compressFolder',
					'group'	=> $group,
				);
				break;
		}
		$junkFolder = ($imap_actions?$this->mail_bo->getJunkFolder():null);

		//error_log(__METHOD__.__LINE__.$junkFolder);
		if ($junkFolder && !empty($junkFolder))
		{
			$tree_actions['empty_spam'] = array(
				'caption' => 'empty junk',
				'icon' => 'dhtmlxtree/MailFolderJunk',
				'enabled'	=> 'javaScript:app.mail.spamfolder_enabled',
				'onExecute' => 'javaScript:app.mail.mail_emptySpam',
				'group'	=> $group,
			);
		}
		$tree_actions['sieve']['group']	= $tree_actions['vacation']['group'] = ++$group;	// new group for filter
		$tree_actions['edit_account']['group'] = $tree_actions['edit_acl']['group']	=
				$tree_actions['predefined-addresses']['group'] = ++$group;


		// enforce global (group-specific) ACL
		if (!mail_hooks::access('aclmanagement'))
		{
			unset($tree_actions['edit_acl']);
		}
		if (!mail_hooks::access('editfilterrules'))
		{
			unset($tree_actions['sieve']);
		}
		if (!mail_hooks::access('absentnotice'))
		{
			unset($tree_actions['vacation']);
		}
		if (!mail_hooks::access('managefolders'))
		{
			unset($tree_actions['add']);
			unset($tree_actions['move']);
			unset($tree_actions['delete']);
			unset($tree_actions['foldermanagement']);
			// manage folders should not affect the ability to subscribe or unsubscribe
			// to existing folders, it should only affect add/rename/move/delete
		}
		return $tree_actions;
	}

	/**
	 * Ajax callback to subscribe / unsubscribe a Mailbox of an account
	 *
	 * @param {int} $_acc_id profile Id of selected mailbox
	 * @param {string} $_folderName name of mailbox needs to be subcribe or unsubscribed
	 * @param {boolean} $_status set true for subscribe and false to unsubscribe
	 */
	public function ajax_foldersubscription($_acc_id,$_folderName, $_status)
	{
		//Change the Mail object to related profileId
		$this->changeProfile($_acc_id);
		try{
			$this->mail_bo->icServer->subscribeMailbox($_folderName, $_status);
			$this->mail_bo->resetFolderObjectCache($_acc_id);
			$this->ajax_reloadNode($_acc_id,!$this->mail_bo->mailPreferences['showAllFoldersInFolderPane']);
		} catch (Horde_Imap_Client_Exception $ex) {
			error_log(__METHOD__.__LINE__."()". lang('Folder %1 %2 failed because of %3!',$_folderName,$_status?'subscribed':'unsubscribed', $ex));
			Framework::message(lang('Folder %1 %2 failed!',$_folderName,$_status));
		}
	}

	/**
	 * Ajax callback to fetch folders for given profile
	 *
	 * We currently load all folders of a given profile, tree can also load parts of a tree.
	 *
	 * @param string $_nodeID if of node whose children are requested
	 * @param boolean $_subscribedOnly flag to tell whether to fetch all or only subscribed (default)
	 */
	public function ajax_foldertree($_nodeID = null,$_subscribedOnly=null)
	{
		$nodeID = $_GET['id'];
		if (!is_null($_nodeID)) $nodeID = $_nodeID;
		$subscribedOnly = (bool)(!is_null($_subscribedOnly)?$_subscribedOnly:!$this->mail_bo->mailPreferences['showAllFoldersInFolderPane']);
		$fetchCounters = !is_null($_nodeID);
		list($_profileID,$_folderName) = explode(self::$delimiter,$nodeID,2);

		if (!empty($_folderName)) $fetchCounters = true;

		// Check if it is called for refresh root
		// then we need to reinitialized the index tree
		if(!$nodeID && !$_profileID)
		{
			$data = $this->mail_tree->getInitialIndexTree(null,null,null,null,true,!$this->mail_bo->mailPreferences['showAllFoldersInFolderPane']);
		}
		else
		{
			$data = $this->mail_tree->getTree($nodeID,$_profileID,0, false,$subscribedOnly,!$this->mail_bo->mailPreferences['showAllFoldersInFolderPane']);
		}
		if (!is_null($_nodeID)) return $data;
		Etemplate\Widget\Tree::send_quote_json($data);
	}

	/**
	 * findNode - helper function to return only a branch of the tree
	 *
	 * @param array $_out out array (to be searched)
	 * @param string $_nodeID node to search for
	 * @param boolean $childElements return node itself, or only its child items
	 * @return array structured subtree
	 */
	static function findNode($_out, $_nodeID, $childElements = false)
	{
		foreach($_out['item'] as $node)
		{
			if (strcmp($node['id'],$_nodeID)===0)
			{
				//error_log(__METHOD__.__LINE__.':'.$_nodeID.'->'.$node['id']);
				return ($childElements?$node['item']:$node);
			}
			elseif (is_array($node['item']) && strncmp($node['id'],$_nodeID,strlen($node['id']))===0 && strlen($_nodeID)>strlen($node['id']))
			{
				//error_log(__METHOD__.__LINE__.' descend into '.$node['id']);
				return self::findNode($node,$_nodeID,$childElements);
			}
		}
	}

	/**
	 * Method to execute spam actions
	 *
	 * @param type $_action action id
	 * @param type $_items
	 */
	public function ajax_spamAction($_action, $_items)
	{
		$msg = array();
		$refresh = false;
		$response = Api\Json\Response::get();
		// Check active profile and change it if it's neccessary
		if (is_array($_items[0]))
		{
			$id_parts = self::splitRowID($_items[0]['row_id']);
			if ($id_parts['profileID'] && $id_parts['profileID'] != $this->mail_bo->profileID)
			{
				$this->changeProfile($id_parts['profileID']);
			}
		}

		$delimiter = $this->mail_bo->getHierarchyDelimiter();
		// Ham folder
		$ham = $this->mail_bo->profileID.self::$delimiter.$this->mail_bo->icServer->acc_folder_ham;
		// Junk folder
		$junk = $this->mail_bo->profileID.self::$delimiter.$this->mail_bo->getJunkFolder();
		// Inbox folder
		$inbox = $this->mail_bo->profileID.self::$delimiter.'INBOX';

		$messages = array();

		foreach ($_items as &$params)
		{
			$id_parts = self::splitRowID($params['row_id']);
			// Current Mailbox
			$mailbox = $id_parts['folder'];
			$messages[] = $params['row_id'];
			if ($GLOBALS['egw_info']['apps']['stylite'] && $this->mail_bo->icServer->acc_spam_api)
			{
				$params['mailbody'] = $this->get_load_email_data($params['uid'], null, $mailbox);
			}
		}
		switch ($_action)
		{
			case 'spam':
				$msg[] = $this->ajax_copyMessages($junk, array(
					'all' => false,
					'msg' => $messages
					), 'move', null, true);
				$refresh = true;
				break;
			case 'ham':
				if ($this->mail_bo->icServer->acc_folder_ham && empty($this->mail_bo->icServer->acc_spam_api))
				{
					$msg[] = $this->ajax_copyMessages($ham, array(
						'all' => false,
						'msg' => $messages
						), 'copy', null, true);
				}
				// Move mails to Inbox if they are in Junk folder
				if ($junk == $this->mail_bo->profileID.self::$delimiter.$mailbox)
				{
					$msg[] = $this->ajax_copyMessages($inbox, array(
						'all' => false,
						'msg' => $messages
					), 'move', null, true);
					$refresh = true;
				}
				break;
		}
		if ($GLOBALS['egw_info']['apps']['stylite'] && $this->mail_bo->icServer->acc_spam_api)
		{
			if (strpos($user=$this->mail_bo->icServer->acc_imap_username, '@') === false)
			{
				if (!empty($this->mail_bo->icServer->acc_domain))
				{
					$user .= '@'.$this->mail_bo->icServer->acc_domain;
				}
				else
				{
					$user = $this->mail_bo->icServer->ident_email;
				}
			}
			stylite_mail_spamtitan::setActionItems($_action, $_items, $auth=[
				'user'		=> $user,
				'userpwd'	=> $this->mail_bo->icServer->acc_imap_password,
				'api_url'	=> $this->mail_bo->icServer->acc_spam_api,
				'api_token'	=> $this->mail_bo->icServer->acc_spam_password,
			]);

			// sync aliases to SpamTitan when the first spam action in a session is used
			if (Api\Mail\Account::read($this->mail_bo->profileID)->acc_smtp_type !== 'EGroupware\\Api\\Mail\\Smtp' &&
				!Api\Cache::getSession('SpamTitian', 'AliasesSynced-'.$this->mail_bo->icServer->acc_id.'-'.$this->mail_bo->icServer->acc_imap_username))
			{
				$data = Api\Mail\Account::read($this->mail_bo->profileID)->smtpServer()->getUserData($GLOBALS['egw_info']['user']['account_id']);
				if (($m = stylite_mail_spamtitan::setActionItems('sync_aliases',
					array(array_merge((array)$data['mailLocalAddress'], (array)$data['mailAlternateAddress'])), $auth)))
				{
					$msg[] = $m;
				}
				Api\Cache::setSession('SpamTitian', 'AliasesSynced-'.$this->mail_bo->icServer->acc_id.'-'.$this->mail_bo->icServer->acc_imap_username, true);
			}
		}

		if ($refresh)
		{
			$response->data([implode('\n',$msg),$messages]);
		}
		else
		{
			$response->apply('egw.message',[implode('\n',$msg)]);
		}
	}

	/**
	 * Build spam actions
	 *
	 * @return array actions
	 */
	public function getSpamActions ()
	{
		$actions = array (
			'spamfilter' => array (
				'caption'	=> 'Spam',
				'icon'		=> 'dhtmlxtree/MailFolderJunk',
				'allowOnMultiple' => true,
				'children'	=> array (
					'spam' => array (
						'caption'	=> 'Report as Spam',
						'icon'		=> 'dhtmlxtree/MailFolderJunk',
						'onExecute' => 'javaScript:app.mail.spam_actions',
						'hint'		=> 'Report this email content as Spam - spam solutions like spamTitan will learn',
						'allowOnMultiple' => true
					),
					'ham' => array (
						'caption'	=> 'Report as Ham',
						'icon'		=> 'dhtmlxtree/MailFolderHam',
						'onExecute' => 'javaScript:app.mail.spam_actions',
						'hint'		=> 'Report this email content as Ham (not spam) - spam solutions like spamTitan will learn',
						'allowOnMultiple' => true
					)
				)
			)
		);
		$account = Mail\Account::read($this->mail_bo->profileID);
		// spamTitan actions
		if ($account->acc_spam_api && class_exists('stylite_mail_spamtitan'))
		{
			$actions['spamfilter']['children'] = array_merge($actions['spamfilter']['children'], stylite_mail_spamtitan::getActions());
		}
		return $actions;
	}

	/**
	 * Get actions / context menu for index
	 *
	 * Changes here, require to log out, as $content[self::$nm_index] get stored in session!
	 * @return array see nextmatch_widget::egw_actions()
	 */
	private function get_actions()
	{
		static $accArray=array(); // buffer identity names on single request
		// duplicated from mail_hooks
		static $deleteOptions = array(
			'move_to_trash'		=> 'move to trash',
			'mark_as_deleted'	=> 'mark as deleted',
			'remove_immediately' =>	'remove immediately',
		);
		// todo: real hierarchical folder list
		$lastFolderUsedForMove = null;
		$moveactions = array();
		$archiveFolder = $this->mail_bo->getArchiveFolder();
		$lastFoldersUsedForMoveCont = Api\Cache::getCache(Api\Cache::INSTANCE,'email','lastFolderUsedForMove'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*1);
		//error_log(__METHOD__.__LINE__." StoredFolders->".array2string($lastFoldersUsedForMoveCont));
		//error_log(__METHOD__.__LINE__.' ProfileId:'.$this->mail_bo->profileID." StoredFolders->(".count($lastFoldersUsedForMoveCont[$this->mail_bo->profileID]).") ".array2string($lastFoldersUsedForMoveCont[$this->mail_bo->profileID]));
		if (is_null($accArray))
		{
			foreach(Mail\Account::search($only_current_user=true, false) as $acc_id => $accountObj)
			{
				//error_log(__METHOD__.__LINE__.array2string($accountObj));
				if (!$accountObj->is_imap())
				{
					// not to be used for IMAP Foldertree, as there is no Imap host
					continue;
				}
				$identity_name = Mail\Account::identity_name($accountObj,true,$GLOBALS['egw_info']['user']['acount_id']);
				$accArray[$acc_id] = str_replace(array('<','>'),array('[',']'),$identity_name);// as angle brackets are quoted, display in Javascript messages when used is ugly, so use square brackets instead
			}
		}
		if (!is_array($lastFoldersUsedForMoveCont)) $lastFoldersUsedForMoveCont=array();
		foreach (array_keys($lastFoldersUsedForMoveCont) as $pid)
		{
			if ($this->mail_bo->profileID==$pid && isset($lastFoldersUsedForMoveCont[$this->mail_bo->profileID]))
			{
				$_folder = $this->mail_bo->icServer->getCurrentMailbox();
				//error_log(__METHOD__.__LINE__.' '.$_folder."<->".$lastFoldersUsedForMoveCont[$this->mail_bo->profileID].function_backtrace());
				$counter =1;
				foreach ($lastFoldersUsedForMoveCont[$this->mail_bo->profileID] as $i => $lastFolderUsedForMoveCont)
				{
					$moveaction = 'move_';
					if ($_folder!=$i)
					{
						$moveaction .= $lastFolderUsedForMoveCont;
						//error_log(__METHOD__.__LINE__.'#'.$moveaction);
						//error_log(__METHOD__.__LINE__.'#'.$currentArchiveActionKey);
						if ($this->mail_bo->folderExists($i)) // only 10 entries per mailaccount.Control this on setting the buffered folders
						{
							$fS['profileID'] = $this->mail_bo->profileID;
							$fS['profileName'] = $accArray[$this->mail_bo->profileID];
							$fS['shortDisplayName'] = $i;
							$moveactions[$moveaction] = $fS;
							$counter ++;
						}
						else
						{
							unset($lastFoldersUsedForMoveCont[$this->mail_bo->profileID][$i]);
						}
						//error_log(array2string($moveactions[$moveaction]));
					}
				}
			}
			elseif ($this->mail_bo->profileID!=$pid && isset($lastFoldersUsedForMoveCont[$pid]) && !empty($lastFoldersUsedForMoveCont[$pid]))
			{
				$counter =1;
				foreach ($lastFoldersUsedForMoveCont[$pid] as $i => $lastFolderUsedForMoveCont)
				{
					//error_log(__METHOD__.__LINE__."$i => $lastFolderUsedForMoveCont");
					if (!empty($lastFolderUsedForMoveCont)) // only 10 entries per mailaccount.Control this on setting the buffered folders
					{
						$moveaction = 'move_'.$lastFolderUsedForMoveCont;
						//error_log(__METHOD__.__LINE__.'#'.$moveaction);
						$fS = array();
						$fS['profileID'] = $pid;
						$fS['profileName'] = $accArray[$pid];
						$fS['shortDisplayName'] = $i;
						$moveactions[$moveaction] = $fS;
						$counter ++;
					}
				}
			}
		}
		Api\Cache::setCache(Api\Cache::INSTANCE,'email','lastFolderUsedForMove'.trim($GLOBALS['egw_info']['user']['account_id']),$lastFoldersUsedForMoveCont, $expiration=60*60*1);
		$group = 0;
		$actions =  array(
			'open' => array(
				'caption' => lang('Open'),
				'icon' => 'view',
				'group' => ++$group,
				'onExecute' => Api\Header\UserAgent::mobile()?'javaScript:app.mail.mobileView':'javaScript:app.mail.mail_open',
				'allowOnMultiple' => false,
				'default' => true,
				'mobileViewTemplate' => 'view?'.filemtime(Api\Etemplate\Widget\Template::rel2path('/mail/templates/mobile/view.xet'))
			),
			'reply' => array(
				'caption' => 'Reply',
				'icon' => 'mail_reply',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.mail_compose',
				'allowOnMultiple' => false,
				'toolbarDefault' => true
			),
			'reply_all' => array(
				'caption' => 'Reply All',
				'icon' => 'mail_replyall',
				'group' => $group,
				'onExecute' => 'javaScript:app.mail.mail_compose',
				'allowOnMultiple' => false,
				'shortcut' => array('ctrl' => true, 'shift' => true, 'keyCode' => 65, 'caption' => 'Ctrl + Shift + A'),
			),
			'forward' => array(
				'caption' => 'Forward',
				'icon' => 'mail_forward',
				'group' => $group,
				'children' => array(
					'forwardinline' => array(
						'caption' => 'Inline',
						'icon' => 'mail_forward',
						'group' => $group,
						'hint' => 'forward inline',
						'onExecute' => 'javaScript:app.mail.mail_compose',
						'allowOnMultiple' => false,
						'shortcut' => array('ctrl' => true, 'keyCode' => 70, 'caption' => 'Ctrl + F'),
						'toolbarDefault' => true
					),
					'forwardasattach' => array(
						'caption' => 'Attachment',
						'hint' => 'forward as attachment',
						'icon' => 'mail_forward_attach',
						'group' => $group,
						'onExecute' => 'javaScript:app.mail.mail_compose',
					),
				),
				'hideOnMobile' => true
			),
			'composeasnew' => array(
				'caption' => 'Compose',
				'icon' => 'new',
				'hint' => 'Compose as new',
				'group' => $group,
				'onExecute' => 'javaScript:app.mail.mail_compose',
				'allowOnMultiple' => false,
			),
			'modifysubject' => array(
				'caption' => 'Modify Subject',
				'icon' => 'edit',
				'hint' => 'Modify subject of this message',
				'group' => $group,
				'onExecute' => 'javaScript:app.mail.modifyMessageSubjectDialog',
				'allowOnMultiple' => false,
				'shortcut' =>  array('ctrl' => true, 'keyCode' => 77, 'caption' => 'Ctrl + M'),
			)
		);
		$macounter=0;
		if (!empty($moveactions))
		{
			//error_log(__METHOD__.__LINE__.array2string($moveactions));
			$children=array();
			$pID=0;
			foreach ($moveactions as $moveaction => $lastFolderUsedForMove)
			{
				$group = ($pID != $lastFolderUsedForMove['profileID'] && $macounter>0? $group+1 : $group);
				//error_log(__METHOD__.__LINE__."#$pID != ".$lastFolderUsedForMove['profileID']."#".$macounter.'#'.$groupCounter.'#');
				$children = array_merge($children,
					array(
						$moveaction => array(
							'caption' => (!empty($lastFolderUsedForMove['profileName'])?$lastFolderUsedForMove['profileName']:'('.$lastFolderUsedForMove['profileID'].')').': '.(isset($lastFolderUsedForMove['shortDisplayName'])?$lastFolderUsedForMove['shortDisplayName']:''),
							'icon' => 'move',
							'group' => $group,
							'onExecute' => 'javaScript:app.mail.mail_move2folder',
							'allowOnMultiple' => true,
						)
					)
				);
				$pID = $lastFolderUsedForMove['profileID'];
				$macounter++;
			}
			$actions['moveto'] =	array(
				'caption' => lang('Move selected to'),
				'icon' => 'move',
				'group' => $group,
				'children' => $children,
			);

		} else {
			$group++;
		}
		$spam_actions = $this->getSpamActions();
		$group++;
		foreach ($spam_actions as &$action)
		{
			$action['group'] = $group;
		}
		//error_log(__METHOD__.__LINE__.$archiveFolder);
		$actions['move2'.$this->mail_bo->profileID.self::$delimiter.$archiveFolder] = array( //toarchive
			'caption' => 'Move to archive',
			'hint' => 'move selected mails to archive',
			'icon' => 'archive',
			'group' => $group++,
			'enabled' => 'javaScript:app.mail.archivefolder_enabled',
			//'hideOnDisabled' => true, // does not work as expected on message-list
			'onExecute' => 'javaScript:app.mail.mail_move2folder',
			'shortcut' => KeyManager::shortcut(KeyManager::V, true, true),
			'allowOnMultiple' => true,
			'toolbarDefault' => false
		);

		$actions += array(
			'infolog' => array(
				'caption' => 'InfoLog',
				'hint' => 'Save as InfoLog',
				'icon' => 'infolog/navbar',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.mail_integrate',
				'popup' => Link::get_registry('infolog', 'add_popup'),
				'allowOnMultiple' => false,
				'toolbarDefault' => true
			),
			'tracker' => array(
				'caption' => 'Tracker',
				'hint' => 'Save as ticket',
				'group' => $group,
				'icon' => 'tracker/navbar',
				'onExecute' => 'javaScript:app.mail.mail_integrate',
				'popup' => Link::get_registry('tracker', 'add_popup'),
				'mail_import' => Api\Hooks::single(array('location' => 'mail_import'),'tracker'),
				'allowOnMultiple' => false,
			),
			'calendar' => array(
				'caption' => 'Calendar',
				'hint' => 'Save as Calendar',
				'icon' => 'calendar/navbar',
				'group' => $group,
				'onExecute' => 'javaScript:app.mail.mail_integrate',
				'popup' => Link::get_registry('calendar', 'add_popup'),
				'allowOnMultiple' => false,
				'toolbarDefault' => true
			),
			'print' => array(
				'caption' => 'Print',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.mail_print',
				'allowOnMultiple' => false,
				'hideOnMobile' => true
			),
			'save' => array(
				'caption' => 'Save',
				'group' => $group,
				'icon' => 'fileexport',
				'children' => array(
					'save2disk' => array(
						'caption' => 'Save to disk',
						'hint' => 'Save message to disk',
						'group' => $group,
						'icon' => 'fileexport',
						'onExecute' => 'javaScript:app.mail.mail_save',
						'allowOnMultiple' => true,
						'hideOnMobile' => true
					),
					'save2filemanager' => array(
						'caption' => 'Filemanager',
						'hint' => 'Save to filemanager',
						'group' => $group,
						'icon' => 'filemanager/navbar',
						'onExecute' => 'javaScript:app.mail.mail_save2fm',
						'allowOnMultiple' => true,
					),
				),
				'hideOnMobile' => true
			),
			'view' => array(
				'caption' => 'View',
				'group' => $group,
				'icon' => 'kmmsgread',
				'children' => array(
					'header' => array(
						'caption' => 'Header',
						'hint' => 'View header lines',
						'group' => $group,
						'icon' => 'kmmsgread',
						'onExecute' => 'javaScript:app.mail.mail_header',
						'allowOnMultiple' => false,
					),
					'mailsource' => array(
						'caption' => 'Source',
						'hint' => 'View full Mail Source',
						'group' => $group,
						'icon' => 'source',
						'onExecute' => 'javaScript:app.mail.mail_mailsource',
						'allowOnMultiple' => false,
					),
					'openastext' => array(
						'caption' => lang('Text mode'),
						'hint' => 'Open in Text mode',
						'group' => ++$group,
						'icon' => 'textmode',
						'onExecute' => 'javaScript:app.mail.mail_openAsText',
						'allowOnMultiple' => false,
					),
					'openashtml' => array(
						'caption' => lang('HTML mode'),
						'hint' => 'Open in HTML mode',
						'group' => $group,
						'icon' => 'htmlmode',
						'onExecute' => 'javaScript:app.mail.mail_openAsHtml',
						'allowOnMultiple' => false,
					),
				),
				'hideOnMobile' => true
			),
			'mark' => array(
				'caption' => 'Set / Remove Flags',
				'icon' => 'kmmsgread',
				'group' => ++$group,
				'children' => array(
					// icons used from http://creativecommons.org/licenses/by-sa/3.0/
					// Artist: Led24
					// Iconset Homepage: http://led24.de/iconset
					// License: CC Attribution 3.0
					'setLabel' => array(
						'caption' => 'Set / Remove Labels',
						'icon' => 'tag_message',
						'group' => ++$group,
						// note this one is NOT a real CAPABILITY reported by the server, but added by selectMailbox
						'enabled' => $this->mail_bo->icServer->hasCapability('SUPPORTS_KEYWORDS'),
						'hideOnDisabled' => true,
						'children' => array(
							'unlabel' => array(
								'group' => ++$group,
								'caption' => "<font color='#ff0000'>".lang('remove all')."</font>",
								'icon' => 'mail_label',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_0, true, true),
							),
							'label1' => array(
								'group' => ++$group,
								'caption' => "<font color='#ff0000'>".lang('important')."</font>",
								'icon' => 'mail_label1',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_1, true, true),
							),
							'label2' => array(
								'group' => $group,
								'caption' => "<font color='#ff8000'>".lang('job')."</font>",
								'icon' => 'mail_label2',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_2, true, true),
							),
							'label3' => array(
								'group' => $group,
								'caption' => "<font color='#008000'>".lang('personal')."</font>",
								'icon' => 'mail_label3',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_3, true, true),
							),
							'label4' => array(
								'group' => $group,
								'caption' => "<font color='#0000ff'>".lang('to do')."</font>",
								'icon' => 'mail_label4',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_4, true, true),
							),
							'label5' => array(
								'group' => $group,
								'caption' => "<font color='#8000ff'>".lang('later')."</font>",
								'icon' => 'mail_label5',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_5, true, true),
							),
						),
					),
					// modified icons from http://creativecommons.org/licenses/by-sa/3.0/
					'flagged' => array(
						'group' => ++$group,
						'caption' => 'Flag / Unflag',
						'icon' => 'unread_flagged_small',
						'onExecute' => 'javaScript:app.mail.mail_flag',
						'hint' => 'Flag or Unflag a mail',
						'shortcut' => KeyManager::shortcut(KeyManager::F, true, true),
						'toolbarDefault' => true
					),
					'read' => array(
						'group' => $group,
						'caption' => 'Read / Unread',
						'icon' => 'kmmsgread',
						'onExecute' => 'javaScript:app.mail.mail_flag',
						'shortcut' => KeyManager::shortcut(KeyManager::U, true, true),

					),
					'readall' => array(
						'group' => ++$group,
						'caption' => "<font color='#ff0000'>".lang('mark all as read')."</font>",
						'icon' => 'kmmsgread',
						'onExecute' => 'javaScript:app.mail.mail_flag',
						'hint' => 'mark all messages in folder as read',
						'toolbarDefault' => false
					),
					'undelete' => array(
						'group' => $group,
						'caption' => 'Undelete',
						'icon' => 'revert',
						'onExecute' => 'javaScript:app.mail.mail_flag',
					),
				),
			),
			'delete' => array(
				'caption' => 'Delete',
				'hint' => $deleteOptions[$this->mail_bo->mailPreferences['deleteOptions']],
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.mail_delete',
				'toolbarDefault' => true
			),
			'drag_mail' => array(
				'dragType' => array('mail'),
				'type' => 'drag',
				//'onExecute' => 'javaScript:app.mail.mail_dragStart',
			)
		);
		//error_log(__METHOD__.__LINE__.array2string(array_keys($actions)));
		// save as tracker, save as infolog, as this are actions that are either available for all, or not, we do that for all and not via css-class disabling
		if (!isset($GLOBALS['egw_info']['user']['apps']['infolog']))
		{
			unset($actions['infolog']);
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['tracker']))
		{
			unset($actions['tracker']);
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['calendar']))
		{
			unset($actions['calendar']);
		}
		// remove vfs actions if the user has no run access to filemanager
		if (!$GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			unset($actions['save']['children']['save2filemanager']);
		}
		return array_merge($actions, $spam_actions);
	}

	/**
	 * Callback to fetch the rows for the nextmatch widget
	 *
	 * Function is static to not automatic call constructor in case profile is changed.
	 *
	 * @param array $query
	 * @param array &$rows
	 * @param array &$readonlys
	 */
	public static function get_rows(&$query,&$rows,&$readonlys)
	{
		unset($readonlys);	// not used, but required by function signature

		// handle possible profile change in get_rows
		if (!empty($query['selectedFolder']))
		{
			list($_profileID,$folderName) = explode(self::$delimiter, $query['selectedFolder'], 2);
			if (is_numeric(($_profileID)) && $_profileID != $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'])
			{
				try {
					$mail_ui = new mail_ui(false);	// do NOT run constructor, as we change profile anyway
					$mail_ui->changeProfile($_profileID);
					$query['actions'] = $mail_ui->get_actions();
				}
				catch(Exception $e)
				{
					unset($e);
					$rows=array();
					return 0;
				}
				if (empty($folderName)) $query['selectedFolder'] = $_profileID.self::$delimiter.'INBOX';
			}
		}
		if (!isset($mail_ui))
		{
			try
			{
				$mail_ui = new mail_ui(true);	// run constructor for current profile
			}
			catch(Exception $e)
			{
				unset($e);
				$rows=array();
				return 0;
			}
			if (empty($query['selectedFolder'])) $query['selectedFolder'] = $mail_ui->mail_bo->profileID.self::$delimiter.'INBOX';
		}
		// enable push notifications, if supported (and configured) by the server
		if ($mail_ui->mail_bo->icServer instanceof Api\Mail\Imap\PushIface &&
			$mail_ui->mail_bo->icServer->pushAvailable())
		{
			Api\Json\Response::get()->call('app.mail.disable_autorefresh',
				$mail_ui->mail_bo->icServer->enablePush());
		}
		else
		{
			Api\Json\Response::get()->call('app.mail.disable_autorefresh', false);
		}
		//error_log(__METHOD__.__LINE__.' SelectedFolder:'.$query['selectedFolder'].' Start:'.$query['start'].' NumRows:'.$query['num_rows'].array2string($query['order']).'->'.array2string($query['sort']));
		//Mail::$debugTimes=true;
		if (Mail::$debugTimes) $starttime = microtime(true);
		//$query['search'] is the phrase in the searchbox

		$mail_ui->mail_bo->restoreSessionData();
		if (isset($query['selectedFolder'])) $mail_ui->mail_bo->sessionData['mailbox']=$query['selectedFolder'];

		$sRToFetch = null;
		list($_profileID,$_folderName) = explode(self::$delimiter,$query['selectedFolder'],2);
		if (strpos($_folderName,self::$delimiter)!==false)
		{
			list($app,$_profileID,$_folderName) = explode(self::$delimiter,$_folderName,3);
			unset($app);
		}
		//save selected Folder to sessionData (mailbox)->currentFolder
		if (isset($query['selectedFolder'])) $mail_ui->mail_bo->sessionData['mailbox']=$_folderName;
		$toSchema = false;//decides to select list schema with column to selected (if false fromaddress is default)
		if ($mail_ui->mail_bo->folderExists($_folderName))
		{
			$toSchema = $mail_ui->mail_bo->isDraftFolder($_folderName,false)||$mail_ui->mail_bo->isSentFolder($_folderName,false)||$mail_ui->mail_bo->isTemplateFolder($_folderName,false);
		}
		else
		{
			// take the extra time on failure
			if (!$mail_ui->mail_bo->folderExists($_folderName,true))
			{
				//error_log(__METHOD__.__LINE__.' Test on Folder:'.$_folderName.' failed; Using INBOX instead');
				$query['selectedFolder']=$mail_ui->mail_bo->sessionData['mailbox']=$_folderName='INBOX';
			}
		}
		$rowsFetched['messages'] = null;
		$offset = $query['start']+1; // we always start with 1
		$maxMessages = $query['num_rows'];
		//error_log(__METHOD__.__LINE__.array2string($query));
		$sort = ($query['order']=='address'?($toSchema?'toaddress':'fromaddress'):$query['order']);
		if (!empty($query['search'])||($query['cat_id']=='bydate' && (!empty($query['startdate'])||!empty($query['enddate']))))
		{
			if (is_null(Mail::$supportsORinQuery) || !isset(Mail::$supportsORinQuery[$mail_ui->mail_bo->profileID]))
			{
				Mail::$supportsORinQuery = Api\Cache::getCache(Api\Cache::INSTANCE,'email','supportsORinQuery'.trim($GLOBALS['egw_info']['user']['account_id']), null, array(), 60*60*10);
				if (!isset(Mail::$supportsORinQuery[$mail_ui->mail_bo->profileID]))
				{
					Mail::$supportsORinQuery[$mail_ui->mail_bo->profileID]=true;
				}
			}
			//error_log(__METHOD__.__LINE__.' Startdate:'.$query['startdate'].' Enddate'.$query['enddate']);
			$cutoffdate = $cutoffdate2 = null;
			if ($query['startdate']) $cutoffdate = Api\DateTime::to($query['startdate'],'ts');//SINCE, enddate
			if ($query['enddate']) $cutoffdate2 = Api\DateTime::to($query['enddate'],'ts');//BEFORE, startdate
			//error_log(__METHOD__.__LINE__.' Startdate:'.$cutoffdate2.' Enddate'.$cutoffdate);
			$filter = array(
				'filterName' => (Mail::$supportsORinQuery[$mail_ui->mail_bo->profileID]?lang('quicksearch'):lang('subject')),
				'type' => ($query['cat_id']?$query['cat_id']:(Mail::$supportsORinQuery[$mail_ui->mail_bo->profileID]?'quick':'subject')),
				'string' => $query['search'],
				'status' => 'any',
				//'range'=>"BETWEEN",'since'=> date("d-M-Y", $cutoffdate),'before'=> date("d-M-Y", $cutoffdate2)
			);
			if ($query['enddate']||$query['startdate']) {
				$filter['range'] = "BETWEEN";
				if ($cutoffdate) {
					$filter[(empty($cutoffdate2)?'date':'since')] =  date("d-M-Y", $cutoffdate);
					if (empty($cutoffdate2)) $filter['range'] = "SINCE";
				}
				if ($cutoffdate2) {
					$filter[(empty($cutoffdate)?'date':'before')] =  date("d-M-Y", $cutoffdate2);
					if (empty($cutoffdate)) $filter['range'] = "BEFORE";
				}
			}
		}
		else
		{
			$filter = array();
		}
		if ($query['filter'])
		{
			$filter['status'] = $query['filter'];
		}
		$reverse = ($query['sort']=='ASC'?false:true);
		$prefchanged = false;
		if (!isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveSearchType']) || ($query['cat_id'] !=$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveSearchType']))
		{
			//error_log(__METHOD__.__LINE__.' Changing userPref ActivesearchType:'.$query['cat_id']);
			$GLOBALS['egw']->preferences->add('mail','ActiveSearchType',$query['cat_id'],'user');
			$prefchanged = true;
		}
		if (!isset($GLOBALS['egw_info']['user']['preferences']['mail']['ShowDetails']) || ($query['filter2'] !=$GLOBALS['egw_info']['user']['preferences']['mail']['ShowDetails']))
		{
			$GLOBALS['egw']->preferences->add('mail','ShowDetails',$query['filter2'],'user');
			$prefchanged = true;
		}
		if ($prefchanged)
		{
			// save prefs
			$GLOBALS['egw']->preferences->save_repository(true);
		}
		//error_log(__METHOD__.__LINE__.' maxMessages:'.$maxMessages.' Offset:'.$offset.' Filter:'.array2string($mail_ui->sessionData['messageFilter']));
/*
$cutoffdate = Api\DateTime::to('now','ts')-(3600*24*6);//SINCE, enddate
$cutoffdate2 = Api\DateTime::to('now','ts')-(3600*24*3);//BEFORE, startdate
$filter['range'] = "BETWEEN";// we support SINCE, BEFORE, BETWEEN and ON
$filter['since'] = date("d-M-Y", $cutoffdate);
$filter['before']= date("d-M-Y", $cutoffdate2);
*/
		$sR = array();
		try
		{
			if ($maxMessages > 75)
			{
				$rByUid = true;
				$_sR = $mail_ui->mail_bo->getSortedList(
					$_folderName,
					$sort,
					$reverse,
					$filter,
					$rByUid
				);
				$rowsFetched['messages'] = $_sR['count'];
				$ids = $_sR['match']->ids;
				// if $sR is false, something failed fundamentally
				if($reverse === true) $ids = ($ids===false?array():array_reverse((array)$ids));
				$sR = array_slice((array)$ids,($offset==0?0:$offset-1),$maxMessages); // we need only $maxMessages of uids
				$sRToFetch = $sR;//array_slice($sR,0,50); // we fetch only the headers of a subset of the fetched uids
				//error_log(__METHOD__.__LINE__.' Rows fetched (UID only):'.count($sR).' Data:'.array2string($sR));
				$maxMessages = 75;
				$sortResultwH['header'] = array();
				if (count($sRToFetch)>0)
				{
					//error_log(__METHOD__.__LINE__.' Headers to fetch with UIDs:'.count($sRToFetch).' Data:'.array2string($sRToFetch));
					$sortResult = array();
					// fetch headers
					$sortResultwH = $mail_ui->mail_bo->getHeaders(
						$_folderName,
						$offset,
						$maxMessages,
						$sort,
						$reverse,
						$filter,
						$sRToFetch,
						true, //cacheResult
						($query['filter2']?true:false) // fetchPreview
					);
				}
			}
			else
			{
				$sortResult = array();
				$uids = array_map(function($row_id)
				{
					return self::splitRowID($row_id)['msgUID'];
				}, (array)$query['col_filter']['row_id']) ?: null;
				// fetch headers
				$sortResultwH = $mail_ui->mail_bo->getHeaders(
					$_folderName,
					$offset,
					$maxMessages,
					$sort,
					$reverse,
					$filter,
					$uids, // this uids only
					true, // cacheResult
					($query['filter2']?true:false) // fetchPreview
				);
				$rowsFetched['messages'] = $sortResultwH['info']['total'];
			}
		}
		catch (Exception $e)
		{
			$sortResultwH=array();
			$sR=array();
			self::callWizard($e->getMessage(), false, 'error');
		}
		$response = Api\Json\Response::get();
		// unlock immediately after fetching the rows
		if (stripos($_GET['menuaction'],'ajax_get_rows')!==false)
		{
			//error_log(__METHOD__.__LINE__.' unlock tree ->'.$_GET['menuaction']);
			$response->call('app.mail.unlock_tree');
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
		$rowsFetched['rowsFetched'] = $sortResult['header'] ? count($sortResult['header']) : 0;
		if (empty($rowsFetched['messages'])) $rowsFetched['messages'] = $rowsFetched['rowsFetched'];

		//error_log(__METHOD__.__LINE__.' Rows fetched:'.$rowsFetched.' Data:'.array2string($sortResult));
		$cols = array('row_id','uid','status','attachments','subject','address','toaddress','fromaddress','ccaddress','additionaltoaddress','date','size','modified','bodypreview', 'security');
		if ($GLOBALS['egw_info']['user']['preferences']['common']['select_mode']=='EGW_SELECTMODE_TOGGLE') unset($cols[0]);
		$rows = $mail_ui->header2gridelements($sortResult['header'],$cols, $_folderName, $folderType=$toSchema);

		// Save the session (since we are committing session) at the end
		// to make sure all necessary data are stored in session.
		// e.g.: Link:: get_data which is used to read attachments data.
		$mail_ui->mail_bo->saveSessionData();

		if (Mail::$debugTimes) Mail::logRunTimes($starttime,null,'Folder:'.$_folderName.' Start:'.$query['start'].' NumRows:'.$query['num_rows'],__METHOD__.__LINE__);
		return $rowsFetched['messages'];
	}

	/**
	 * function createRowID - create a unique rowID for the grid
	 *
	 * @param string $_folderName used to ensure the uniqueness of the uid over all folders
	 * @param string $message_uid the message_Uid to be used for creating the rowID
	 * @param boolean $_prependApp to indicate that the app 'mail' is to be used for creating the rowID
	 * @return string - a colon separated string in the form [app:]accountID:profileID:folder:message_uid
	 */
	function createRowID($_folderName, $message_uid, $_prependApp=false)
	{
		return self::generateRowID($this->mail_bo->profileID, $_folderName, $message_uid, $_prependApp);
	}

	/**
	 * static function generateRowID - create a unique rowID for the grid
	 *
	 * @param integer $_profileID profile ID for the rowid to be used
	 * @param string $_folderName to ensure the uniqueness of the uid over all folders
	 * @param string $message_uid the message_Uid to be used for creating the rowID
	 * @param boolean $_prependApp to indicate that the app 'mail' is to be used for creating the rowID
	 * @return string - a colon separated string in the form [app:]accountID:profileID:folder:message_uid
	 */
	static function generateRowID($_profileID, $_folderName, $message_uid, $_prependApp=false)
	{
		return ($_prependApp?'mail'.self::$delimiter:'').trim($GLOBALS['egw_info']['user']['account_id']).self::$delimiter.$_profileID.self::$delimiter.base64_encode($_folderName).self::$delimiter.$message_uid;
	}

	/**
	 * function splitRowID - split the rowID into its parts
	 *
	 * @param string $_rowID string - a colon separated string in the form accountID:profileID:folder:message_uid
	 * @return array populated named result array (accountID,profileID,folder,msgUID)
	 */
	static function splitRowID($_rowID)
	{
		$res = explode(self::$delimiter,$_rowID);
		// as a rowID is perceeded by app::, should be mail!
		//error_log(__METHOD__.__LINE__.array2string($res).' [0] isInt:'.is_int($res[0]).' [0] isNumeric:'.is_numeric($res[0]).' [0] isString:'.is_string($res[0]).' Count:'.count($res));
		if (count($res)==4 && is_numeric($res[0]) )
		{
			// we have an own created rowID; prepend app=mail
			array_unshift($res,'mail');
		}
		return array('app'=>$res[0], 'accountID'=>$res[1]??null, 'profileID'=>$res[2]??null, 'folder'=>base64_decode($res[3]??null), 'msgUID'=>$res[4]??null);
	}

	/**
	 * Get actions for preview toolbar
	 *
	 * @return array
	 */
	function get_toolbar_actions()
	{
		$actions = $this->get_actions();
		$arrActions = array('composeasnew', 'reply', 'reply_all', 'forward', 'flagged', 'delete', 'print',
			'infolog', 'tracker', 'calendar', 'save', 'view', 'read', 'label1',	'label2', 'label3',	'label4', 'label5','spam', 'ham');
		foreach( $arrActions as &$act)
		{
			//error_log(__METHOD__.__LINE__.' '.$act.'->'.array2string($actions[$act]));
			switch ($act)
			{
				case 'forward':
					$actionsenabled[$act]=$actions[$act];
					break;
				case 'save':
					$actionsenabled[$act]=$actions[$act];

					break;
				case 'view':
					$actionsenabled[$act]=$actions[$act];
					break;
				case 'flagged':
					$actionsenabled[$act]= $actions['mark']['children'][$act];
					break;
				case 'read':
					$actionsenabled[$act]= $actions['mark']['children'][$act];
					break;
				case 'label1':
					$actions['mark']['children']['setLabel']['children'][$act]['caption'] = lang('important');
					$actionsenabled[$act]= $actions['mark']['children']['setLabel']['children'][$act];
					break;
				case 'label2':
					$actions['mark']['children']['setLabel']['children'][$act]['caption'] = lang('job');
					$actionsenabled[$act]= $actions['mark']['children']['setLabel']['children'][$act];
					break;
				case 'label3':
					$actions['mark']['children']['setLabel']['children'][$act]['caption'] = lang('personal');
					$actionsenabled[$act]= $actions['mark']['children']['setLabel']['children'][$act];
					break;
				case 'label4':
					$actions['mark']['children']['setLabel']['children'][$act]['caption'] = lang('to do');
					$actionsenabled[$act]= $actions['mark']['children']['setLabel']['children'][$act];
					break;
				case 'label5':
					$actions['mark']['children']['setLabel']['children'][$act]['caption'] = lang('later');
					$actionsenabled[$act]= $actions['mark']['children']['setLabel']['children'][$act];
					break;
				case 'ham':
				case 'spam':
					$actionsenabled[$act]= $actions['spamfilter']['children'][$act];
					break;
				default:
					if (isset($actions[$act])) $actionsenabled[$act]=$actions[$act];
			}
		}
		unset($actionsenabled['drag_mail']);
		//error_log(array2string($actionsenabled['view']));
		unset($actionsenabled['view']['children']['openastext']);//not supported in preview
		unset($actionsenabled['view']['children']['openashtml']);//not supported in preview

		return $actionsenabled;
	}

	/**
	 * function header2gridelements - to populate the grid elements with the collected Data
	 *
	 * @param array $_headers headerdata to process
	 * @param array $cols cols to populate
	 * @param array $_folderName to ensure the uniqueness of the uid over all folders
	 * @param array $_folderType used to determine if we need to populate from/to
	 * @return array populated result array
	 */
	public function header2gridelements($_headers, $cols, $_folderName, $_folderType=0)
	{
		if (Mail::$debugTimes) $starttime = microtime(true);
		$rv = array();
		$i=0;
		foreach((array)$_headers as $header)
		{
			$i++;
			$data = array();
			//error_log(__METHOD__.array2string($header));
			$message_uid = $header['uid'];
			$data['uid'] = $message_uid;
			$data['row_id']=$this->createRowID($_folderName,$message_uid);

			if ($header['smimeType'])
			{
				$data['smime'] = Mail\Smime::isSmimeSignatureOnly($header['smimeType'])?
				Mail\Smime::TYPE_SIGN : Mail\Smime::TYPE_ENCRYPT;
			}

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
				$css_styles[] = 'label1';
			}
			if ($header['label2']) {
				$css_styles[] = 'label2';
			}
			if ($header['label3']) {
				$css_styles[] = 'label3';
			}
			if ($header['label4']) {
				$css_styles[] = 'label4';
			}
			if ($header['label5']) {
				$css_styles[] = 'label5';
			}

			//error_log(__METHOD__.array2string($css_styles));

			if (in_array("subject", $cols))
			{
				// filter out undisplayable characters
				$search = array('[\016]','[\017]',
					'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
					'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');
				$replace = '';

				$header['subject'] = preg_replace($search,$replace,$header['subject']);
				// curly brackets get messed up by the template!

				if (!empty($header['subject'])) {
					// make the subject shorter if it is to long
					$subject = $header['subject'];
				} else {
					$subject = '('. lang('no subject') .')';
				}

				$data["subject"] = $subject; // the mailsubject
			}

			$imageHTMLBlock = '';
			//error_log(__METHOD__.__LINE__.array2string($header));
			if (in_array("attachments", $cols))
			{
				if (!empty($header['attachments']) && (in_array($header['mimetype'], array(
						'multipart/mixed', 'multipart/signed', 'multipart/related', 'multipart/report',
						'text/calendar', 'text/html', 'multipart/alternative',
					)) ||
					substr($header['mimetype'],0,11) == 'application' ||
					substr($header['mimetype'],0,5) == 'audio' ||
					substr($header['mimetype'],0,5) == 'video'))
				{
					$image = Api\Html::image('mail','attach');
					$datarowid = $this->createRowID($_folderName,$message_uid,true);
					$attachments = $header['attachments'];
					if (count($attachments) == 1)
					{
						$image = Api\Html::image('mail','attach',$attachments[0]['name']);
					}
					else
					{
						$image = Api\Html::image('mail','attach',lang('%1 attachments',count($attachments)));
					}
					$imageHTMLBlock = self::createAttachmentBlock($attachments, $datarowid, $header['uid'],$_folderName);

					$attachmentFlag = $image;
				}
				else
				{
					$attachmentFlag = '&nbsp;';
					$imageHTMLBlock = '';
				}
				// show priority flag
				if ($header['priority'] < 3)
				{
					 $image = Api\Html::image('mail','prio_high');
				}
				elseif ($header['priority'] > 3)
				{
					$image = Api\Html::image('mail','prio_low');
				}
				else
				{
					$image = '';
				}
				// show a flag for flagged messages
				$imageflagged ='';
				if ($header['flagged'])
				{
					$imageflagged = Api\Html::image('mail','unread_flagged_small');
				}
				$data["attachments"] = $image.$attachmentFlag.$imageflagged; // icon for attachments available
			}

			// sent or draft or template folder -> to address
			if (in_array("toaddress", $cols))
			{
				// sent or drafts or template folder means foldertype > 0, use to address instead of from
				$data["toaddress"] = $header['to_address'];//Mail::htmlentities($header['to_address'],$this->charset);
			}

			if (in_array("additionaltoaddress", $cols))
			{
				$data['additionaltoaddress'] = $header['additional_to_addresses'];
			}
			//fromaddress
			if (in_array("fromaddress", $cols))
			{
				$data["fromaddress"] = $header['sender_address'];
			}
			$data['additionalfromaddress'] = $header['additional_from_addresses'];
			if (in_array("ccaddress", $cols))
			{
				$data['ccaddress'] = $header['cc_addresses'];
			}
			if (in_array("date", $cols))
			{
				$data["date"] = $header['date'];
			}
			if (in_array("modified", $cols))
			{
				$data["modified"] = $header['internaldate'];
			}

			if (in_array("size", $cols))
				$data["size"] = $header['size']; /// size

			$data["class"] = implode(' ', $css_styles);
			//translate style-classes back to flags
			$data['flags'] = Array();
			if ($header['seen']) $data["flags"]['read'] = 'read';
			foreach ($css_styles as &$flag) {
				if ($flag!='mail')
				{
					if ($flag=='label1') {$data["flags"]['label1'] = 'label1';}
					elseif ($flag=='label2') {$data["flags"]['label2'] = 'label2';}
					elseif ($flag=='label3') {$data["flags"]['label3'] = 'label3';}
					elseif ($flag=='label4') {$data["flags"]['label4'] = 'label4';}
					elseif ($flag=='label5') {$data["flags"]['label5'] = 'label5';}
					elseif ($flag=='unseen') {unset($data["flags"]['read']);}
					else $data["flags"][$flag] = $flag;
				}
			}
			if ($header['disposition-notification-to']) $data['dispositionnotificationto'] = $header['disposition-notification-to'];
			if (($header['mdnsent']||$header['mdnnotsent']|$header['seen'])&&isset($data['dispositionnotificationto'])) unset($data['dispositionnotificationto']);
			$data['attachmentsBlock'] = $imageHTMLBlock;
			if ($_folderType)
			{
				$fromcontact = self::getContactFromAddress($data['fromaddress']);
				if(!empty($fromcontact) && $fromcontact[0]['photo'])
				{
					$data['fromavatar'] = $fromcontact[0]['photo'];
				}
			}
			$data['address'] = ($_folderType ? $data["toaddress"] : $data["fromaddress"]);
			$data['lavatar'] = ['fname' => $data['address']];

			$contact = self::getContactFromAddress($data['address']);
			if(!empty($contact))
			{
				$data['lavatar'] = ['fname' => $contact[0]['n_given'], 'lname' => $contact[0]['n_family']];
				if($contact[0]['photo'])
				{
					$data['avatar'] = $contact[0]['photo'];
				}
				if(!$_folderType)
				{
					$data['fromavatar'] = $data['avatar'];
				}
			}

			if (in_array("bodypreview", $cols)&&$header['bodypreview'])
			{
				$data["bodypreview"] = $header['bodypreview'];
			}
			$rv[] = $data;
			//error_log(__METHOD__.__LINE__.array2string($data));
		}
		if (Mail::$debugTimes) Mail::logRunTimes($starttime,null,'Folder:'.$_folderName,__METHOD__.__LINE__);

		// ToDo: call this ONLY if labels change
		Etemplate\Widget::setElementAttribute('toolbar', 'actions', $this->get_toolbar_actions());

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
		$icServerID = $hA['profileID'];
		$rememberServerID = $this->mail_bo->profileID;
		if ($icServerID && $icServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile to ->'.$icServerID);
			$this->changeProfile($icServerID);
		}

		$this->mail_bo->reopen($mailbox);
		$headers_in	= $this->mail_bo->getMessageRawHeader($uid, $partID);

		// add line breaks to $rawheaders
		$newRawHeaders = explode("\n",$headers_in);
		reset($newRawHeaders);

		// reset $rawheaders
		$rawheaders 	= "";
		// create it new, with good line breaks
		reset($newRawHeaders);
		foreach($newRawHeaders as $value)
		{
			$rawheaders .= wordwrap($value, 90, "\n     ");
		}

		$this->mail_bo->closeConnection();
		if ($rememberServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile back to where we came from->'.$rememberServerID);
			$this->changeProfile($rememberServerID);
		}

		header('Content-type: text/html; charset=iso-8859-1');
		print '<pre>'. htmlspecialchars($rawheaders, ENT_NOQUOTES, 'iso-8859-1') .'</pre>';

	}

	/**
	 * display messages
	 * @param array $_requesteddata etemplate content
	 * all params are passed as GET Parameters, but can be passed via ExecMethod2 as array too
	 */
	function displayMessage($_requesteddata = null)
	{
		if (is_null($_requesteddata)) $_requesteddata = $_GET;

		$preventRedirect=false;
		if(isset($_requesteddata['id'])) $rowID	= $_requesteddata['id'];
		if(isset($_requesteddata['part'])) $partID = $_requesteddata['part']!='null'?$_requesteddata['part']:null;
		if(isset($_requesteddata['mode'])) $preventRedirect   = (($_requesteddata['mode']=='display' || $_requesteddata['mode'] == 'print')?true:false);

		$hA = self::splitRowID($rowID);
		$uid = $hA['msgUID'];
		$mailbox = $hA['folder'];
		$icServerID = $hA['profileID'];
		$rememberServerID = $this->mail_bo->profileID;
		if ($icServerID && $icServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile to ->'.$icServerID);
			$this->changeProfile($icServerID);
		}
		$htmlOptions = $this->mail_bo->htmlOptions;
		if (!empty($_requesteddata['tryastext'])) $htmlOptions  = "only_if_no_text";
		if (!empty($_requesteddata['tryashtml'])) $htmlOptions  = "always_display";

		//error_log(__METHOD__.__LINE__.array2string($hA));
		if (($this->mail_bo->isDraftFolder($mailbox)) && $_requesteddata['mode'] == 'print')
		{
			$response = Api\Json\Response::get();
			$response->call('app.mail.print_for_compose', $rowID);
		}
		if (!$preventRedirect && ($this->mail_bo->isDraftFolder($mailbox) || $this->mail_bo->isTemplateFolder($mailbox)))
		{
			Egw::redirect_link('/index.php',array('menuaction'=>'mail.mail_compose.compose','id'=>$rowID,'from'=>'composefromdraft'));
		}
		$this->mail_bo->reopen($mailbox);
		// retrieve the flags of the message, before touching it.
		try
		{
			$headers	= $this->mail_bo->getMessageHeader($uid, $partID,true,true,$mailbox);
		}
		catch (Api\Exception $e)
		{
			$error_msg[] = lang("ERROR: Message could not be displayed.");
			$error_msg[] = lang("In Mailbox: %1, with ID: %2, and PartID: %3",$mailbox,$uid,$partID);
			Framework::message($e->getMessage(), 'error');
		}
		if (!empty($uid)) $this->mail_bo->getFlags($uid);
		$envelope	= $this->mail_bo->getMessageEnvelope($uid, $partID,true,$mailbox);
		//error_log(__METHOD__.__LINE__.array2string($envelope));
		$this->mail_bo->getMessageRawHeader($uid, $partID,$mailbox);
		$fetchEmbeddedImages = false;
		// if we are in HTML so its likely that we should show the embedded images; as a result
		// we do NOT want to see those, that are embedded in the list of attachments
		if ($htmlOptions !='always_display') $fetchEmbeddedImages = true;
		try{
			$attachments = $this->mail_bo->getMessageAttachments($uid, $partID, null, $fetchEmbeddedImages,true,true,$mailbox);
		}
		catch(Mail\Smime\PassphraseMissing $e)
		{
			//continue
		}

		//error_log(__METHOD__.__LINE__.array2string($attachments));
		$attachmentHTMLBlock = self::createAttachmentBlock($attachments, $rowID, $uid, $mailbox);

		$nonDisplayAbleCharacters = array('[\016]','[\017]',
				'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
				'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');

		//error_log(__METHOD__.__LINE__.$mailBody);
		$this->mail_bo->closeConnection();
		//$GLOBALS['egw_info']['flags']['currentapp'] = 'mail';//should not be needed
		$etpl = new Etemplate('mail.display');
		$subject = $this->mail_bo->decode_subject(preg_replace($nonDisplayAbleCharacters,'',$envelope['SUBJECT']),false);

		// Set up data for taglist widget(s)
		if ($envelope['FROM']==$envelope['SENDER']) unset($envelope['SENDER']);
		$sel_options = array();
		foreach(array('SENDER','FROM','TO','CC','BCC') as $field)
		{
			if (!isset($envelope[$field])) continue;
			foreach($envelope[$field] as $field_data)
			{
				//error_log(__METHOD__.__LINE__.array2string($field_data));
				$content[strtolower($field)][] = $field_data;
				$sel_options[$field][] = array(
					// taglist requires these - not optional
					'id' => $field_data,
					'label' => str_replace('"',"'",$field_data),
				);
			}
		}
		$actionsenabled = $this->getDisplayToolbarActions();
		$content['displayToolbaractions'] = json_encode($actionsenabled);
		if (empty($subject)) $subject = lang('no subject');
		$content['msg'] = (is_array($error_msg)?implode("<br>",$error_msg):$error_msg);
		// Send mail ID so we can use it for actions
		$content['mail_id'] = $rowID;
		if (!is_array($headers) || !isset($headers['DATE']))
		{
			$headers['DATE'] = (is_array($envelope)&&$envelope['DATE']?$envelope['DATE']:'');
		}
		$content['mail_displaydate'] = Mail::_strtotime($headers['DATE'],'ts',true);
		$content['mail_displaysubject'] = $subject;
		$linkData = array('menuaction'=>"mail.mail_ui.loadEmailBody","_messageID"=>$rowID);
		if (!empty($partID)) $linkData['_partID']=$partID;
		if ($htmlOptions != $this->mail_bo->htmlOptions) $linkData['_htmloptions']=$htmlOptions;
		$content['mailDisplayBodySrc'] = Egw::link('/index.php',$linkData);
		if (!empty($attachmentHTMLBlock))
		{
			$content['mail_displayattachments'] = $attachmentHTMLBlock;
			$content['attachmentsBlockTitle'] = count($attachmentHTMLBlock) > 1 ? '+'.(count($attachmentHTMLBlock)-1) : '';
			$sel_options['mail_displayattachments']['actions'] = mail_hooks::attachmentsBlockActions();
		}

		$content['mail_id']=$rowID;

		// DRAG attachments actions
		$etpl->setElementAttribute('mail_displayattachments', 'actions', array(
			'file_drag' => array(
				'dragType' => 'file',
				'type' => 'drag',
				'onExecute' => 'javaScript:app.mail.drag_attachment'
			)
		));
		$readonlys = $preserv = $content;
		unset($readonlys['mail_displayattachments']);
		$readonlys['mail_displaydate'] = true;
		if ($rememberServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile back to where we came from->'.$rememberServerID);
			$this->changeProfile($rememberServerID);
		}
		// send configured image proxy to client-side
		$content['image_proxy'] = self::image_proxy();
		$contact = self::getContactFromAddress($content['from'][0]);

		if (!empty($contact))
		{
			$content['avatar'] = $contact[0]['photo'];
		}

		$etpl->exec('mail.mail_ui.displayMessage', $content, $sel_options, $readonlys, $preserv, 2);
	}

	/**
	 * Retrieve contact info from a given address
	 *
	 * @param string|null $address
	 * @return array
	 */
	static function getContactFromAddress($address)
	{
		if (empty($address)) return [];

		$email = Mail::stripRFC822Addresses([$address]);

		return $GLOBALS['egw']->contacts->search(
			array('contact_email' => $email[0], 'contact_email_home' => $email[0]),
			array('contact_id', 'email', 'email_home', 'n_fn', 'n_given', 'n_family'),
			'', '', '', false, 'OR', false
		);
	}


	/**
	 * This is a helper function to trigger Push method
	 * faster than normal 60 sec cycle.
	 * @todo: Once we have socket push implemented we should
	 * remove this function plus its client side companion.
	 */
	function ajax_smimeAttachmentsChecker ()
	{
		$response = Api\Json\Response::get();
		$response->data(true);
	}

	/**
	 * Adds certificate to relevant contact
	 * @param array $_metadata data of sender's certificate
	 */
	function ajax_smimeAddCertToContact ($_metadata)
	{
		$response = Api\Json\Response::get();
		$ab = new addressbook_bo();
		$response->data($ab->set_smime_keys(array($_metadata['email'] => $_metadata['cert'])));
	}

	/**
	 * Generates certificate base on given data and send
	 * private key, pubkey and certificate back to client callback.
	 *
	 * @param array $_data
	 */
	function ajax_smimeGenCertificate ($_data)
	{
		$smime = new Mail\Smime();
		$response = Api\Json\Response::get();
		// fields need to be excluded from data
		$discards = array ('passphrase', 'passphraseConf', 'ca', 'validity');
		$ca = $_data['ca'];
		$passphrase = $_data['passphrase'];
		foreach (array_keys($_data) as $key)
		{
			if (empty($_data[$key]) || in_array($key, $discards)) unset($_data[$key]);
		}
		$response->data($smime->generate_certificate($_data, $ca, null, $passphrase, $_data['validity']));
	}

	/**
	 * Export stored smime certificate in database
	 * @return boolean return false if not successful
	 */
	function smimeExportCert()
	{
		if (empty($_GET['acc_id'])) return false;
		$acc_smime = Mail\Smime::get_acc_smime($_GET['acc_id']);
		$length = 0;
		$mime = 'application/x-pkcs12';
		Api\Header\Content::safe($acc_smime['acc_smime_password'], "certificate.p12", $mime, $length, true, true);
		echo $acc_smime['acc_smime_password'];
		exit();
	}

	/**
	 * Build actions for display toolbar
	 */
	function getDisplayToolbarActions ()
	{
		$actions = $this->get_toolbar_actions();
		$actions['mark']['children']['flagged']=array(
			'group' => $actions['mark']['children']['flagged']['group'],
			'caption' => 'Flagged',
			'icon' => 'unread_flagged_small',
			'onExecute' => 'javaScript:app.mail.mail_flag',
		);
		$actions['mark']['children']['unflagged']=array(
			'group' => $actions['mark']['children']['flagged']['group'],
			'caption' => 'Unflagged',
			'icon' => 'read_flagged_small',
			'onExecute' => 'javaScript:app.mail.mail_flag',
		);
		$actions['tracker']['toolbarDefault'] = true;
		$actions['forward']['toolbarDefault'] = true;

		$compose = $actions['composeasnew'];
		unset($actions['composeasnew']);

		$actions2 = array_reverse($actions,true);
		$actions2['composeasnew']= $compose;
		return array_reverse($actions2,true);
	}

	/**
	 * helper function to create the attachment block/table
	 *
	 * @param array $attachments array with the attachments information
	 * @param string $rowID rowid of the message
	 * @param int $uid uid of the message
	 * @param string $mailbox mailbox identifier
	 * @param boolean $_returnFullHTML flag wether to return HTML or data array
	 * @return array|string data array or html or empty string
	 */
	static function createAttachmentBlock($attachments, $rowID, $uid, $mailbox,$_returnFullHTML=false)
	{
		$attachmentHTMLBlock='';
		$attachmentHTML = array();

		// skip message/delivery-status and set a title for original eml file
		if (($attachments[0]['mimeType'] === 'message/delivery-status'))
		{
			unset($attachments[0]);
			if (is_array($attachments))
			{
				$attachments = array_values($attachments);
				$attachments[0]['name'] = lang('Original Email Content');
			}
		}

		if (is_array($attachments) && count($attachments) > 0) {
			foreach ($attachments as $key => $value)
			{
				if (Mail\Smime::isSmime($value['mimeType'])) continue;
				$attachmentHTML[$key]['filename']= ($value['name'] ? ( $value['filename'] ? $value['filename'] : $value['name'] ) : lang('(no subject)'));
				$attachmentHTML[$key]['filename'] = Api\Translation::convert_jsonsafe($attachmentHTML[$key]['filename'],'utf-8');
				//error_log(array2string($value));
				//error_log(strtoupper($value['mimeType']) .'<->'. Api\MimeMagic::filename2mime($attachmentHTML[$key]['filename']));
				if (strtoupper($value['mimeType']) == 'APPLICATION/OCTET-STREAM') $value['mimeType'] = Api\MimeMagic::filename2mime($attachmentHTML[$key]['filename']);
				$attachmentHTML[$key]['type']=$value['mimeType'];
				$attachmentHTML[$key]['mimetype'] = Api\MimeMagic::mime2label($value['mimeType']);
				$hA = self::splitRowID($rowID);
				$uid = $hA['msgUID'];
				$mailbox = $hA['folder'];
				$acc_id = $hA['profileID'];

				$attachmentHTML[$key]['mime_data'] = Link::set_data($value['mimeType'], 'EGroupware\\Api\\Mail::getAttachmentAccount', array(
					$acc_id, $mailbox, $uid, $value['partID'], $value['is_winmail'], true
				));
				$attachmentHTML[$key]['size']=Vfs::hsize($value['size']);
				$attachmentHTML[$key]['attachment_number']=$key;
				$attachmentHTML[$key]['partID']=$value['partID'];
				$attachmentHTML[$key]['mail_id'] = $rowID;
				$attachmentHTML[$key]['winmailFlag']=$value['is_winmail'];
				$attachmentHTML[$key]['smime_type'] = $value['smime_type'];

				if ($GLOBALS['egw_info']['apps']['collabora']
					&& $GLOBALS['egw_info']['user']['preferences']['filemanager']['document_doubleclick_action'] === 'collabora'
					&& array_key_exists($value['mimeType'], filemanager_hooks::getEditorPrefMimes() ?: []))
				{
					$attachmentHTML[$key]['actions'] = 'collabora';
					$attachmentHTML[$key]['actionsDefaultLabel'] = 'Open with Collabora';
				}
				else
				{
					$attachmentHTML[$key]['actions'] = 'downloadOneAsFile';
					$attachmentHTML[$key]['actionsDefaultLabel'] = 'Download';
				}

				// reset mode array as it should be considered differently for
				// each attachment
				$mode = array();
				switch(strtoupper($value['mimeType']))
				{
					case 'MESSAGE/RFC822':
						$linkData = array
						(
							'menuaction'	=> 'mail.mail_ui.displayMessage',
							'mode'		=> 'display', //message/rfc822 attachments should be opened in display mode
							'id'		=> $rowID,
							'part'		=> $value['partID'],
							'is_winmail'    => $value['is_winmail']
						);
						$windowName = 'displayMessage_'. $rowID.'_'.$value['partID'];
						$linkView = "egw_openWindowCentered('".Egw::link('/index.php',$linkData)."','$windowName',700,egw_getWindowOuterHeight());";
						break;
					case 'IMAGE/JPEG':
					case 'IMAGE/PNG':
					case 'IMAGE/GIF':
					case 'IMAGE/BMP':
						// set mode for media mimetypes because we need
						// to structure a download url to be used maybe in expose.
						$mode = array(
							'mode' => 'save'
						);
					case 'APPLICATION/PDF':
					case 'TEXT/PLAIN':
					case 'TEXT/HTML':
					case 'TEXT/DIRECTORY':
						$sfxMimeType = $value['mimeType'];
						$buff = explode('.',$value['name']);
						$suffix = '';
						if (is_array($buff)) $suffix = array_pop($buff); // take the last extension to check with ext2mime
						if (!empty($suffix)) $sfxMimeType = Api\MimeMagic::ext2mime($suffix);
						if (strtoupper($sfxMimeType) == 'TEXT/VCARD' || strtoupper($sfxMimeType) == 'TEXT/X-VCARD')
						{
							$attachments[$key]['mimeType'] = $sfxMimeType;
							$value['mimeType'] = strtoupper($sfxMimeType);
						}
					case 'TEXT/X-VCARD':
					case 'TEXT/VCARD':
					case 'TEXT/CALENDAR':
					case 'TEXT/X-VCALENDAR':
						$linkData = array_merge(array
						(
							'menuaction'	=> 'mail.mail_ui.getAttachment',
							'id'		=> $rowID,
							'part'		=> $value['partID'],
							'is_winmail'=> $value['is_winmail'],
							'mailbox'   => base64_encode($mailbox),
							'smime_type' => $value['smime_type']
						) , $mode);
						$windowName = 'displayAttachment_'. $uid;
						$reg = '800x600';
						// handle calendar/vcard
						if (strtoupper($value['mimeType'])=='TEXT/CALENDAR')
						{
							$windowName = 'displayEvent_'. $rowID;
							$reg2 = Link::get_registry('calendar','view_popup');
							$attachmentHTML[$key]['popup']=(!empty($reg2) ? $reg2 : $reg);
						}
						if (strtoupper($value['mimeType'])=='TEXT/X-VCARD' || strtoupper($value['mimeType'])=='TEXT/VCARD')
						{
							$windowName = 'displayContact_'. $rowID;
							$reg2 = Link::get_registry('addressbook','add_popup');
							$attachmentHTML[$key]['popup']=(!empty($reg2) ? $reg2 : $reg);
						}
						// apply to action
						list($width,$height) = explode('x',(!empty($reg2) ? $reg2 : $reg));
						$linkView = "egw_openWindowCentered('".Egw::link('/index.php',$linkData)."','$windowName',$width,$height);";
						break;
					default:
						$linkData = array
						(
							'menuaction'	=> 'mail.mail_ui.getAttachment',
							'id'		=> $rowID,
							'part'		=> $value['partID'],
							'is_winmail'    => $value['is_winmail'],
							'mailbox'   => base64_encode($mailbox),
							'smime_type' => $value['smime_type']
						);
						$linkView = "window.location.href = '".Egw::link('/index.php',$linkData)."';";
						break;
				}
				// we either use mime_data for server-side supported mime-types or mime_url for client-side or download
				if (empty($attachmentHTML[$key]['mime_data']))
				{
					$attachmentHTML[$key]['mime_url'] = Egw::link('/index.php',$linkData);
					unset($attachmentHTML[$key]['mime_data']);
				}
				$attachmentHTML[$key]['windowName'] = $windowName;

				//error_log(__METHOD__.__LINE__.$linkView);
				$attachmentHTML[$key]['link_view'] = '<a href="#" ." title="'.$attachmentHTML[$key]['filename'].'" onclick="'.$linkView.' return false;"><b>'.
					($value['name'] ? $value['name'] : lang('(no subject)')).
					'</b></a>';

				$linkData = array
				(
					'menuaction'	=> 'mail.mail_ui.getAttachment',
					'mode'		=> 'save',
					'id'		=> $rowID,
					'part'		=> $value['partID'],
					'is_winmail'    => $value['is_winmail'],
					'mailbox'   => base64_encode($mailbox),
					'smime_type' => $value['smime_type']
				);
				$attachmentHTML[$key]['link_save'] ="<a href='".Egw::link('/index.php',$linkData)."' title='".$attachmentHTML[$key]['filename']."'>".Api\Html::image('mail','fileexport')."</a>";

				if (!$GLOBALS['egw_info']['user']['apps']['filemanager']) $attachmentHTML[$key]['no_vfs'] = true;
			}
			$attachmentHTMLBlock="<table width='100%'>";
			foreach ((array)$attachmentHTML as $row)
			{
				$attachmentHTMLBlock .= "<tr><td><div class='useEllipsis'>".$row['link_view'].'</div></td>';
				$attachmentHTMLBlock .= "<td>".$row['mimetype'].'</td>';
				$attachmentHTMLBlock .= "<td>".$row['size'].'</td>';
				$attachmentHTMLBlock .= "<td>".$row['link_save'].'</td></tr>';
			}
			$attachmentHTMLBlock .= "</table>";
		}
		if (!$_returnFullHTML)
		{
			foreach ((array)$attachmentHTML as $ikey => $value)
			{
				unset($attachmentHTML[$ikey]['link_view']);
				unset($attachmentHTML[$ikey]['link_save']);
			}
		}
		return ($_returnFullHTML?$attachmentHTMLBlock:$attachmentHTML);
	}

	/**
	 * fetch vacation info from active Server using icServer object
	 *
	 * @param array $cachedVacations an array of cached vacations for an user
	 * @return array|boolean array with vacation on success or false on failure
	 */
	function gatherVacation($cachedVacations = array())
	{
		$isVacationEnabled = $this->mail_bo->icServer->acc_sieve_enabled && ($this->mail_bo->icServer->acc_sieve_host||$this->mail_bo->icServer->acc_imap_host);
		//error_log(__METHOD__.__LINE__.' Server:'.self::$icServerID.' Sieve Enabled:'.array2string($vacation));

		if ($isVacationEnabled)
		{
			$sieveServer = $this->mail_bo->icServer;
			try
			{
				$sieveServer->retrieveRules();
				$vacation = $sieveServer->getVacation();

				$cachedVacations = array($sieveServer->acc_id => $vacation) + (array)$cachedVacations;
				// Set vacation to the instance cache for particular account with expiration of one day
				Api\Cache::setCache(Api\Cache::INSTANCE, 'email', 'vacationNotice'.$GLOBALS['egw_info']['user']['account_lid'], $cachedVacations, 60*60*24);
			}
			catch (PEAR_Exception $ex)
			{
				$this->callWizard($ex->getMessage(), true, 'error');
			}
		}
		//error_log(__METHOD__.__LINE__.' Server:'.self::$icServerID.' Vacation retrieved:'.array2string($vacation));
		return $vacation;
	}

	/**
	 * gather Info on how to display the quota info
	 *
	 * @param int $_usage amount of usage in Kb
	 * @param int $_limit amount of limit in Kb
	 * @return array  returns an array of info used for quota
	 *		array(
	 *			class		=> string,
	 *			text		=> string,
	 *			percent		=> string,
	 *			freespace	=> integer
	 *		)
	 */
	function quotaDisplay($_usage, $_limit)
	{
		$percent = $_limit == 0 ? 100 : round(($_usage*100)/$_limit);
		$limit = Mail::show_readable_size($_limit*1024);
		$usage = Mail::show_readable_size($_usage*1024);

		if ($_limit > 0)
		{
			$text = $usage .'/'.$limit;
			switch ($percent)
			{
				case ($percent > 90):
					$class ='mail-index_QuotaRed';
					break;
				case ($percent > 80):
					$class ='mail-index_QuotaYellow';
					break;
				default:
					$class ='mail-index_QuotaGreen';
			}
		}
		else
		{
			$text = $usage;
			$class ='mail-index_QuotaGreen';
		}
		return array (
			'class'		=> $class,
			'text'		=> lang('Quota: %1',$text),
			'percent'	=> $percent,
			'freespace'	=> $_limit*1024 - $_usage*1024
		);
	}

	/**
	 * display image
	 *
	 * all params are passed as GET Parameters
	 */
	function displayImage()
	{
		$uid	= base64_decode($_GET['uid']);
		$cid	= base64_decode($_GET['cid']);
		$partID = urldecode($_GET['partID']);
		if (!empty($_GET['mailbox'])) $mailbox  = base64_decode($_GET['mailbox']);

		//error_log(__METHOD__.__LINE__.":$uid, $cid, $partID");
		$this->mail_bo->reopen($mailbox);

		$attachment = $this->mail_bo->getAttachmentByCID($uid, $cid, $partID, true);	// true get contents as stream

		$this->mail_bo->closeConnection();

		$GLOBALS['egw']->session->commit_session();

		if ($attachment)
		{
			header("Content-Type: ". $attachment->getType());
			header('Content-Disposition: inline; filename="'. $attachment->getDispositionParameter('filename') .'"');
			//header("Expires: 0");
			// the next headers are for IE and SSL
			//header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			//header("Pragma: public");
			Api\Session::cache_control(true);
			echo $attachment->getContents();
		}
		else
		{
			// send a 404 Not found
			header("HTTP/1.1 404 Not found");
		}
		exit();
	}

	function getAttachment()
	{
		if(isset($_GET['id'])) $rowID	= $_GET['id'];

		$hA = self::splitRowID($rowID);
		$uid = $hA['msgUID'];
		$mailbox = $hA['folder'];
		$icServerID = $hA['profileID'];
		$rememberServerID = $this->mail_bo->profileID;
		if ($icServerID && $icServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile to ->'.$icServerID);
			$this->changeProfile($icServerID);
		}
		$part		= $_GET['part'];
		$is_winmail = $_GET['is_winmail'] ? $_GET['is_winmail'] : 0;

		$this->mail_bo->reopen($mailbox);
		$attachment = $this->mail_bo->getAttachment($uid,$part,$is_winmail,false);
		$this->mail_bo->closeConnection();
		if ($rememberServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile back to where we came from->'.$rememberServerID);
			$this->changeProfile($rememberServerID);
		}

		$GLOBALS['egw']->session->commit_session();
		//error_log(__METHOD__.print_r($_GET,true));
		if ($_GET['mode'] != "save")
		{
			if (strtoupper($attachment['type']) == 'TEXT/DIRECTORY' || empty($attachment['type']))
			{
				$sfxMimeType = $attachment['type'];
				$buff = explode('.',$attachment['filename']);
				$suffix = '';
				if (is_array($buff)) $suffix = array_pop($buff); // take the last extension to check with ext2mime
				if (!empty($suffix)) $sfxMimeType = Api\MimeMagic::ext2mime($suffix);
				$attachment['type'] = $sfxMimeType;
				if (strtoupper($sfxMimeType) == 'TEXT/VCARD' || strtoupper($sfxMimeType) == 'TEXT/X-VCARD') $attachment['type'] = strtoupper($sfxMimeType);
			}
			//error_log(__METHOD__.print_r($attachment,true));
			if (strtoupper($attachment['type']) == 'TEXT/CALENDAR' || strtoupper($attachment['type']) == 'TEXT/X-VCALENDAR')
			{
				//error_log(__METHOD__."about to call calendar_ical");
				$calendar_ical = new calendar_ical();
				$event = $calendar_ical->importVCal($attachment['attachment'],-1,null,true,0,'',null,$attachment['charset']);
				//error_log(__METHOD__.$event);
				if ((int)$event > 0)
				{
					$vars = array(
						'menuaction'      => 'calendar.calendar_uiforms.edit',
						'cal_id'      => $event,
					);
					Egw::redirect_link('../index.php',$vars);
				}
				//Import failed, download content anyway
			}
			if (strtoupper($attachment['type']) == 'TEXT/X-VCARD' || strtoupper($attachment['type']) == 'TEXT/VCARD')
			{
				$addressbook_vcal = new addressbook_vcal();
				// double \r\r\n seems to end a vcard prematurely, so we set them to \r\n
				//error_log(__METHOD__.__LINE__.$attachment['attachment']);
				$attachment['attachment'] = str_replace("\r\r\n", "\r\n", $attachment['attachment']);
				$vcard = $addressbook_vcal->vcardtoegw($attachment['attachment'], $attachment['charset']);
				if ($vcard['uid'])
				{
					$vcard['uid'] = trim($vcard['uid']);
					//error_log(__METHOD__.__LINE__.print_r($vcard,true));
					$contact = $addressbook_vcal->find_contact($vcard,false);
				}
				if (!$contact) $contact = null;
				// if there are not enough fields in the vcard (or the parser was unable to correctly parse the vcard (as of VERSION:3.0 created by MSO))
				if ($contact || count($vcard)>2)
				{
					$contact = $addressbook_vcal->addVCard($attachment['attachment'],(is_array($contact)?array_shift($contact):$contact),true,$attachment['charset']);
				}
				if ((int)$contact > 0)
				{
					$vars = array(
						'menuaction'	=> 'addressbook.addressbook_ui.edit',
						'contact_id'	=> $contact,
					);
					Egw::redirect_link('../index.php',$vars);
				}
				//Import failed, download content anyway
			}
		}
		//error_log(__METHOD__.__LINE__.'->'.array2string($attachment));
		$filename = ($attachment['name']?$attachment['name']:($attachment['filename']?$attachment['filename']:$mailbox.'_uid'.$uid.'_part'.$part));
		$size = 0;
		Api\Header\Content::safe($attachment['attachment'], $filename, $attachment['type'], $size, True, $_GET['mode'] == "save");
		echo $attachment['attachment'];

		exit();
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
		if(isset($_GET['part'])) $partID = $_GET['part'];
		if (isset($_GET['location'])&& ($_GET['location']=='display'||$_GET['location']=='filemanager')) $display	= $_GET['location'];

		$hA = self::splitRowID($rowID);
		$uid = $hA['msgUID'];
		$mailbox = $hA['folder'];
		$icServerID = $hA['profileID'];
		$rememberServerID = $this->mail_bo->profileID;
		if ($icServerID && $icServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile to ->'.$icServerID);
			$this->changeProfile($icServerID);
		}

		$this->mail_bo->reopen($mailbox);

		$message = $this->mail_bo->getMessageRawBody($uid, $partID, $mailbox);

		$this->mail_bo->closeConnection();
		if ($rememberServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile back to where we came from ->'.$rememberServerID);
			$this->changeProfile($rememberServerID);
		}

		$GLOBALS['egw']->session->commit_session();
		$headers = Horde_Mime_Headers::parseHeaders($message);
		$subject = str_replace('$$','__',Mail::decode_header($headers['SUBJECT']));
		if (!$display)
		{
			$subject = Api\Mail::clean_subject_for_filename($subject);
			$mime='message/rfc822';
			Api\Header\Content::safe($message, $subject.".eml", $mime);
			echo $message;
		}
		else
		{
			$subject = Api\Mail::clean_subject_for_filename($subject);
			$mime = 'text/html';
			$size = 0;
			Api\Header\Content::safe($message, $subject . ".eml", $mime, $size, true, false);
			print '<pre>' . htmlspecialchars($message, ENT_NOQUOTES | ENT_SUBSTITUTE, 'utf-8') . '</pre>';
		}
	}

	/**
	 * Ajax function to save message(s)/attachment(s) in the vfs
	 *
	 * @param string $attachment_id
	 * @param string $filename
	 *
	 * @return string Temporary path to open
	 */
	function ajax_vfsOpen($attachment_id, $filename)
	{
		// Use a sub-dir so we can give a nice filename
		$temp_path = '/home/' . $GLOBALS['egw_info']['user']['account_lid'] . "/.mail/";
		if(!Vfs::is_dir($temp_path))
		{
			Vfs::mkdir($temp_path);
		}

		$result = $this->vfsSaveAttachments([$attachment_id], $temp_path . $filename, 'rename');

		$response = Api\Json\Response::get();
		$response->data($result['savepath'][$attachment_id] ?? "");
	}

	/**
	 * Ajax function to save message(s)/attachment(s) in the vfs
	 *
	 * @param array $params array of mail ids and action name
	 *            params = array (
	 *                ids => array of string
	 *                action => string
	 *            )
	 * @param string $path path to save the emails
	 * @param string $submit_button_id dialog button id of triggered submit
	 * @param string $savemode save mode: 'overwrite' or 'rename'
	 */
	function ajax_vfsSave ($params, $path, $submit_button_id='', $savemode='rename')
	{
		unset($submit_button_id); // not used here

		$response = Api\Json\Response::get();

		switch ($params['action'])
		{
			case 'message':
				$result = $this->vfsSaveMessages($params['ids'], $path, $savemode);
				break;
			case 'attachment':
				$result = $this->vfsSaveAttachments($params['ids'], $path, $savemode);
				break;
		}
		$response->call('app.mail.vfsSaveCallback', $result);
	}

	/**
	 * Save Message(s) in the vfs
	 *
	 * @param string|array $ids use splitRowID, to separate values
	 * @param string $path path in vfs (no Vfs::PREFIX!), only directory for multiple id's ($ids is an array)
	 * @param string $savemode save mode: 'overwrite' or 'rename'
	 *
	 * @return array returns an array including message and success result
	 *		array (
	 *			'msg' => STRING,
	 *			'success' => BOOLEAN
	 *		)
	 */
	function vfsSaveMessages($ids,$path, $savemode='rename')
	{
		// add mail translation
		Api\Translation::add_app('mail');
		$res = array ();

		// extract dir from the path
		$dir = Vfs::is_dir($path) ? $path : Vfs::dirname($path);

		// exit if user has no right to the dir
		if (!Vfs::is_writable($dir))
		{
			return array (
				'msg' => lang('%1 is NOT writable by you!',$path),
				'success' => false
			);
		}

		$preservedServerID = $this->mail_bo->profileID;
		foreach((array)$ids as $id)
		{
			$hA = self::splitRowID($id);
			$uid = $hA['msgUID'];
			$mailbox = $hA['folder'];
			$icServerID = $hA['profileID'];
			if ($icServerID && $icServerID != $this->mail_bo->profileID)
			{
				$this->changeProfile($icServerID);
			}
			$message = $this->mail_bo->getMessageRawBody($uid, $partID='', $mailbox);

			// is multiple messages
			if (Vfs::is_dir($path))
			{
				$headers = $this->mail_bo->getMessageHeader($uid,$partID,true,false,$mailbox);
				$file = $dir . '/'.Api\Mail::clean_subject_for_filename($headers['SUBJECT']).'.eml';
			}
			else
			{
				$file = $dir . '/' . Api\Mail::clean_subject_for_filename(str_replace($dir.'/', '', $path));
			}

			if ($savemode != 'overwrite')
			{
				// Check if file already exists, then try to assign a none existance filename
				$counter = 1;
				$tmp_file = $file;
				while (Vfs::file_exists($tmp_file))
				{
					$tmp_file = $file;
					$pathinfo = pathinfo(Vfs::basename($tmp_file));
					$tmp_file = $dir . '/' . $pathinfo['filename'] . '(' . $counter . ')' . '.' . $pathinfo['extension'];
					$counter++;
				}
				$file = $tmp_file;
			}

			if (!is_string($message) || !($fp = Vfs::fopen($file,'wb')) || !fwrite($fp,$message))
			{
				$res['msg'] = lang('Error saving %1!',$file);
				$res['success'] = false;
			}
			else
			{
				$res['success'] = true;
			}
			if ($fp) fclose($fp);
			if ($res['success'])
			{
				unset($headers['SUBJECT']);//already in filename
				$infoSection = Mail::createHeaderInfoSection($headers, 'SUPPRESS', false);
				$props = array(array('name' => 'comment','val' => $infoSection));
				Vfs::proppatch($file,$props);
			}
		}
		if ($preservedServerID != $this->mail_bo->profileID)
		{
			//change Profile back to where we came from
			$this->changeProfile($preservedServerID);
		}
		return $res;
	}

	/**
	 * Save attachment(s) in the vfs
	 *
	 * @param string|array $ids '::' delimited mailbox::uid::part-id::is_winmail::name (::name for multiple id's)
	 * @param string $path path in vfs (no Vfs::PREFIX!), only directory for multiple id's ($ids is an array)
	 * @param string $savemode save mode: 'overwrite' or 'rename'
	 *
	 * @return array returns an array including message and success result
	 *		array (
	 *			'msg' => STRING,
	 *			'success' => BOOLEAN
	 *		)
	 */
	function vfsSaveAttachments($ids,$path, $savemode='rename')
	{
		$res = array (
			'msg' => lang('Attachment has been saved successfully.'),
			'success' => true
		);

		if (Vfs::is_dir($path))
		{
			$dir = $path;
		}
		else
		{
			$dir = Vfs::dirname($path);
			// Need to deal with any ? here, or basename will truncate
			$filename = Api\Mail::clean_subject_for_filename(str_replace('?','_',Vfs::basename($path)));
		}

		if (!Vfs::is_writable($dir))
		{
			return array (
				'msg' => lang('%1 is NOT writable by you!',$path),
				'success' => false
			);
		}

		$preservedServerID = $this->mail_bo->profileID;

		/**
		 * Extract all parameteres from the given id
		 * @param int $id message id ('::' delimited mailbox::uid::part-id::is_winmail::name)
		 *
		 * @return array an array of parameters
		 */
		$getParams = function ($id) {
			list($app,$user,$serverID,$mailbox,$uid,$part,$is_winmail,$name) = explode('::',$id,8);
			$lId = implode('::',array($app,$user,$serverID,$mailbox,$uid));
			$hA = mail_ui::splitRowID($lId);
			return array(
				'is_winmail' => $is_winmail == "null" || !$is_winmail?false:$is_winmail,
				'user' => $user,
				'name' => $name,
				'part' => $part,
				'uid' => $hA['msgUID'],
				'mailbox' => $hA['folder'],
				'icServer' => $hA['profileID']
			);
		};

		//Examine the first attachment to see if attachment
		//is winmail.dat embedded attachments.
		$p = $getParams((is_array($ids)?$ids[0]:$ids));
		if ($p['is_winmail'])
		{
			if ($p['icServer'] && $p['icServer'] != $this->mail_bo->profileID)
			{
				$this->changeProfile($p['icServer']);
			}
			$this->mail_bo->reopen($p['mailbox']);
			// retrieve all embedded attachments at once
			// avoids to fetch heavy winmail.dat content
			// for each file.
			$attachments = $this->mail_bo->getTnefAttachments($p['uid'],$p['part'], false, $p['mailbox']);
		}

		foreach((array)$ids as $id)
		{
			$params = $getParams($id);

			if ($params['icServer'] && $params['icServer'] != $this->mail_bo->profileID)
			{
				$this->changeProfile($params['icServer']);
			}
			$this->mail_bo->reopen($params['mailbox']);

			// is multiple attachments
			if (Vfs::is_dir($path) || $params['is_winmail'])
			{
				if ($params['is_winmail'])
				{
					// Try to find the right content for file id
					foreach ($attachments as $key => $val)
					{
						if ($key == $params['is_winmail']) $attachment = $val;
					}
				}
				else
				{
					$attachment = $this->mail_bo->getAttachment($params['uid'],$params['part'],$params['is_winmail'],false);
				}
			}
			else
			{
				$attachment = $this->mail_bo->getAttachment($params['uid'],$params['part'],$params['is_winmail'],false);
			}

			$file = $dir. '/' . ($filename ? $filename : Mail::clean_subject_for_filename($attachment['filename']));

			if ($savemode != 'overwrite')
			{
				$counter = 1;
				$tmp_file = $file;
				while (Vfs::file_exists($tmp_file))
				{
					$tmp_file = $file;
					$pathinfo = pathinfo(Vfs::basename($tmp_file));
					$tmp_file = $dir . '/' . $pathinfo['filename'] . '(' . $counter . ')' . '.' . $pathinfo['extension'];
					$counter++;
				}
				$file = $tmp_file;
			}

			if (!($fp = Vfs::fopen($file,'wb')) ||
				!fwrite($fp,$attachment['attachment']))
			{
				$res['msg'] = lang('Error saving %1!',$file);
				$res['success'] = false;
			}
			if ($fp)
			{
				fclose($fp);
			}
			$res['savepath'][$id] = $file;
		}

		$this->mail_bo->closeConnection();

		if ($preservedServerID != $this->mail_bo->profileID)
		{
			//change Profile back to where we came from
			$this->changeProfile($preservedServerID);
		}
		return $res;
	}

	/**
	 * Zip all attachments and send to user
	 * @param string $message_id = null
	 */
	function download_zip($message_id=null)
	{
		//error_log(__METHOD__.__LINE__.array2string($_GET));
		// First, get all attachment IDs
		if(isset($_GET['id'])) $message_id	= $_GET['id'];
		//error_log(__METHOD__.__LINE__.$message_id);
		$rememberServerID = $this->mail_bo->profileID;
		if(!is_numeric($message_id))
		{
			$hA = self::splitRowID($message_id);
			$message_id = $hA['msgUID'];
			$mailbox = $hA['folder'];
			$icServerID = $hA['profileID'];
			if ($icServerID && $icServerID != $this->mail_bo->profileID)
			{
				//error_log(__METHOD__.__LINE__.' change Profile to ->'.$icServerID);
				$this->changeProfile($icServerID);
			}
		}
		else
		{
			$mailbox = $this->mail_bo->sessionData['mailbox'];
		}
		// always fetch all, even inline (images)
		$fetchEmbeddedImages = true;
		$attachments = $this->mail_bo->getMessageAttachments($message_id,null, null, $fetchEmbeddedImages, true,true,$mailbox);
		// put them in VFS so they can be zipped
		$header = $this->mail_bo->getMessageHeader($message_id,'',true,false,$mailbox);
		//get_home_dir may fetch the users startfolder if set; if not writeable, action will fail. TODO: use temp_dir
		$homedir = '/home/'.$GLOBALS['egw_info']['user']['account_lid'];
		$temp_path = $homedir/*Vfs::get_home_dir()*/ . "/.mail_$message_id";
		if(Vfs::is_dir($temp_path)) Vfs::remove ($temp_path);

		// Add subject to path, so it gets used as the file name, replacing ':'
		// as it seems to cause an error
		$path = $temp_path . '/' . ($header['SUBJECT'] ? Vfs::encodePathComponent(Api\Mail::clean_subject_for_filename(str_replace(':','-', $header['SUBJECT']))) : lang('mail')) .'/';
		if(!Vfs::mkdir($path, 0700, true))
		{
			echo "Unable to open temp directory $path";
			return;
		}

		$file_list = array();
		$dupe_count = array();
		$this->mail_bo->reopen($mailbox);
		if ($attachments[0]['is_winmail'] && $attachments[0]['is_winmail']!='null')
		{
			$tnefAttachments = $this->mail_bo->getTnefAttachments($message_id, $attachments[0]['partID'],true, $mailbox);
		}
		foreach($attachments as $file)
		{
			if ($file['is_winmail'])
			{
				// Try to find the right content for file id
				foreach ($tnefAttachments as $key => $val)
				{
					error_log(__METHOD__.' winmail = '.$key);
					if ($key == $file['is_winmail']) $attachment = $val;
				}
			}
			else
			{
				$attachment = $this->mail_bo->getAttachment($message_id,$file['partID'],$file['is_winmail'],false,true);
			}
			$success=true;
			if (empty($file['filename'])) $file['filename'] = $file['name'];
			if(in_array($path.$file['filename'], $file_list))
			{
				$dupe_count[$path.$file['filename']]++;
				$file['filename'] = pathinfo($file['filename'], PATHINFO_FILENAME) .
					' ('.($dupe_count[$path.$file['filename']] + 1).')' . '.' .
					pathinfo($file['filename'], PATHINFO_EXTENSION);
			}
			// Strip special characters to make sure the files are visible for all OS (windows has issues)
			$target_name = Api\Mail::clean_subject_for_filename(iconv($file['charset'] ? $file['charset'] : $GLOBALS['egw_info']['server']['system_charset'], 'ASCII//IGNORE', $file['filename']));

			if (!($fp = Vfs::fopen($path.$target_name,'wb')) ||
				!(!fseek($attachment['attachment'], 0, SEEK_SET) && stream_copy_to_stream($attachment['attachment'], $fp)))
			{
				$success=false;
				Framework::message("Unable to zip {$target_name}",'error');
			}
			if ($success) $file_list[] = $path.$target_name;
			if ($fp) fclose($fp);
		}
		$this->mail_bo->closeConnection();
		if ($rememberServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile back to where we came from ->'.$rememberServerID);
			$this->changeProfile($rememberServerID);
		}

		// Zip it up
		Vfs::download_zip($file_list);

		// Clean up
		Vfs::remove($temp_path);

		exit();
	}

	function get_load_email_data($uid, $partID, $mailbox,$htmlOptions=null, $smimePassphrase = '')
	{
		// seems to be needed, as if we open a mail from notification popup that is
		// located in a different folder, we experience: could not parse message
		$this->mail_bo->reopen($mailbox);
		$this->mailbox = $mailbox;
		$this->uid = $uid;
		$this->partID = $partID;
		$bufferHtmlOptions = $this->mail_bo->htmlOptions;
		if (empty($htmlOptions)) $htmlOptions = $this->mail_bo->htmlOptions;
		// fetching structure now, to supply it to getMessageBody and getMessageAttachment, so it does not get fetched twice
		try
		{
			if ($smimePassphrase)
			{
				if ($this->mail_bo->mailPreferences['smime_pass_exp'] != $_POST['smime_pass_exp'])
				{
					$GLOBALS['egw']->preferences->add('mail', 'smime_pass_exp', $_POST['smime_pass_exp']);
					$GLOBALS['egw']->preferences->save_repository();
				}
				Api\Cache::setSession('mail', 'smime_passphrase', $smimePassphrase, (int)($_POST['smime_pass_exp']?:10) * 60);
			}
			$structure = $this->mail_bo->getStructure($uid, $partID, $mailbox, false);
			if (($smime = $structure->getMetadata('X-EGroupware-Smime')))
			{
				$smime['msg'] = lang($smime['msg']);
				$acc_smime = Mail\Smime::get_acc_smime($this->mail_bo->profileID);
				$attachments = $this->mail_bo->getMessageAttachments($uid, $partID, $structure,true,false,true, $mailbox);
				$push = new Api\Json\Push($GLOBALS['egw_info']['user']['account_id']);
				if (!empty($acc_smime) && !empty($smime['addtocontact'])) $push->call('app.mail.smime_certAddToContact', $smime);
				if (is_array($attachments))
				{
					$push->call('app.mail.set_smimeAttachments', $this->createAttachmentBlock($attachments, $_GET['_messageID'], $uid, $mailbox));
				}
				$push->call('app.mail.set_smimeFlags', $smime);
			}
		}
		catch(Mail\Smime\PassphraseMissing $e)
		{
			$acc_smime = Mail\Smime::get_acc_smime($this->mail_bo->profileID);
			if (empty($acc_smime))
			{
				self::callWizard($e->getMessage().' '.lang('Please configure your S/MIME certificate in Encryption tab located at Edit Account dialog.'), true, 'error');
			}
			Framework::message($e->getMessage());
			$configs = Api\Config::read('mail');
			// do NOT include any default CSS
			$smimeHtml = $this->get_email_header().
			'<div class="smime-message">'.lang("This message is smime encrypted and password protected.").'</div>'.
			'<form id="smimePasswordRequest" method="post">'.
					'<div class="bg-style"></div>'.
					'<div>'.
						'<input type="password" placeholder="'.lang("Please enter password").'" name="smime_passphrase"/>'.
						'<input type="submit" value="'.lang("submit").'"/>'.
						'<div style="margin-top:10px;position:relative;text-align:center;margin-left:-15px;">'.
							lang("Remember the password for ").
								'<input name="smime_pass_exp" type="number" max="480" min="1" placeholder="'.
								(is_array($configs) && $configs['smime_pass_exp'] ? $configs['smime_pass_exp'] : "10").
								'" value="'.$this->mail_bo->mailPreferences['smime_pass_exp'].'"/> '.lang("minutes.").
						'</div>'.
					'</div>'.
			'</form>';
			return $smimeHtml;
		}
		$calendar_part = null;
		$bodyParts	= $this->mail_bo->getMessageBody($uid, ($htmlOptions?$htmlOptions:''), $partID, $structure, false, $mailbox, $calendar_part);

		// for meeting requests (multipart alternative with text/calendar part) let calendar render it
		if ($calendar_part && isset($GLOBALS['egw_info']['user']['apps']['calendar']) && empty($smime))
		{
			$charset = $calendar_part->getContentTypeParameter('charset');
			// Do not try to fetch raw part content if it's smime signed message
			$this->mail_bo->fetchPartContents($uid, $calendar_part);
			$headers = $this->mail_bo->getHeaders($mailbox, 0, 1, '', false, null, $uid);
			Api\Cache::setSession('calendar', 'ical', array(
				'charset' => $charset ?: 'utf-8',
				'attachment' => $calendar_part->getContents(),
				'method' => $calendar_part->getContentTypeParameter('method'),
				'sender' => empty($headers['header'][0]['sender_address']) ? null :
					(preg_match('/<([^>]+?)>$/', $sender = strtolower($headers['header'][0]['sender_address']), $matches) ?
						$matches[1] : $sender),
			));
			$this->mail_bo->htmlOptions = $bufferHtmlOptions;
			Api\Translation::add_app('calendar');
			return ExecMethod('calendar.calendar_uiforms.meeting',
				array('event'=>null,'msg'=>'','useSession'=>true)
			);
		}
		if (!$smime)
		{
			Api\Session::cache_control(true);

			// more strict CSP for displaying mail
			foreach(['frame-src', 'connect-src', 'manifest-src'] as $src)
			{
				Api\Header\ContentSecurityPolicy::add($src, 'none');
			}
			Api\Header\ContentSecurityPolicy::add('script-src', 'self', true);	// true = remove default 'unsafe-eval'
			Api\Header\ContentSecurityPolicy::add('img-src', 'http:');
			Api\Header\ContentSecurityPolicy::add('media-src', ['https:','http:']);
		}
		// Compose the content of the frame
		$frameHtml =
			$this->get_email_header($this->mail_bo->getStyles($bodyParts)).
			$this->showBody($this->getdisplayableBody($bodyParts,true,false), false);
		//IE10 eats away linebreaks preceeded by a whitespace in PRE sections
		$frameHtml = str_replace(" \r\n","\r\n",$frameHtml);
		$this->mail_bo->htmlOptions = $bufferHtmlOptions;

		return $frameHtml;
	}

	static function get_email_header($additionalStyle='')
	{
		// egw_info[flags][css] already include <style> tags
		$GLOBALS['egw_info']['flags']['css'] = preg_replace('|</?style[^>]*>|i', '', $additionalStyle);
		$GLOBALS['egw_info']['flags']['nofooter']=true;
		$GLOBALS['egw_info']['flags']['nonavbar']=true;
		// do NOT include any default CSS
		Framework::includeCSS('mail', 'preview', true, true);

		// load preview.js to activate mailto links
		Framework::includeJS('/mail/js/preview.js');

		// send CSP and content-type header
		return $GLOBALS['egw']->framework->header();
	}

	function showBody(&$body, $print=true,$fullPageTags=true)
	{
		$BeginBody = '<div class="mailDisplayBody">
<table width="100%" style="table-layout:fixed"><tr><td class="td_display">';

		$EndBody = '</td></tr></table></div>';
		if ($fullPageTags) $EndBody .= "</body></html>";
		if ($print)	{
			print $BeginBody. $body .$EndBody;
		} else {
			return $BeginBody. $body .$EndBody;
		}
	}

	function &getdisplayableBody($_bodyParts,$modifyURI=true,$useTidy = true)
	{
		$bodyParts	= $_bodyParts;

		$nonDisplayAbleCharacters = array('[\016]','[\017]',
				'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
				'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');

		$body = '';

		//error_log(__METHOD__.array2string($bodyParts)); //exit;
		if (empty($bodyParts))
		{
			$ret = '';
			return $ret;
		}
		foreach((array)$bodyParts as $singleBodyPart) {
			if (!isset($singleBodyPart['body'])) {
				$singleBodyPart['body'] = $this->getdisplayableBody($singleBodyPart,$modifyURI,$useTidy);
				$body .= $singleBodyPart['body'];
				continue;
			}
			$bodyPartIsSet = strlen(trim($singleBodyPart['body']));
			if (!$bodyPartIsSet)
			{
				$body .= '';
				continue;
			}
			if(!empty($body)) {
				$body .= '<hr style="border:dotted 1px silver;">';
			}
			//error_log($singleBodyPart['body']);
			//error_log(__METHOD__.__LINE__.' CharSet:'.$singleBodyPart['charSet'].' mimeType:'.$singleBodyPart['mimeType']);
			// some characterreplacements, as they fail to translate
			$sar = array(
				'@(\x84|\x93|\x94)@',
				'@(\x96|\x97|\x1a)@',
				'@(\x82|\x91|\x92)@',
				'@(\x85)@',
				'@(\x86)@',
				'@(\x99)@',
				'@(\xae)@',
			);
			$rar = array(
				'"',
				'-',
				'\'',
				'...',
				'&',
				'(TM)',
				'(R)',
			);

			if(($singleBodyPart['mimeType'] == 'text/html' || $singleBodyPart['mimeType'] == 'text/plain') &&
				strtoupper($singleBodyPart['charSet']) != 'UTF-8')
			{
				// check if client set a wrong charset and content is utf-8 --> use utf-8
				if (preg_match('//u', $singleBodyPart['body']))
				{
					$singleBodyPart['charSet'] = 'UTF-8';
				}
				else
				{
					$singleBodyPart['body'] = preg_replace($sar,$rar,$singleBodyPart['body']);
				}
			}
			//error_log(__METHOD__.__LINE__.'reports:'.$singleBodyPart['charSet']);
			if ($singleBodyPart['charSet']=='us-ascii')
			{
				$orgCharSet=$singleBodyPart['charSet'];
				$singleBodyPart['charSet'] = Api\Translation::detect_encoding($singleBodyPart['body']);
				error_log(__METHOD__.__LINE__.'reports:'.$orgCharSet.' but seems to be:'.$singleBodyPart['charSet']);
			}
			$singleBodyPart['body'] = Api\Translation::convert_jsonsafe($singleBodyPart['body'],$singleBodyPart['charSet']);
			//error_log(__METHOD__.__LINE__.array2string($singleBodyPart));
			if($singleBodyPart['mimeType'] == 'text/plain')
			{
				$newBody	= @htmlentities($singleBodyPart['body'],ENT_QUOTES, strtoupper(Mail::$displayCharset));
				//error_log(__METHOD__.__LINE__.'..'.$newBody);
				// if empty and charset is utf8 try sanitizing the string in question
				if (empty($newBody) && strtolower($singleBodyPart['charSet'])=='utf-8') $newBody = @htmlentities(iconv('utf-8', 'utf-8', $singleBodyPart['body']),ENT_QUOTES, strtoupper(Mail::$displayCharset));
				// if the conversion to htmlentities fails somehow, try without specifying the charset, which defaults to iso-
				if (empty($newBody)) $newBody    = htmlentities($singleBodyPart['body'],ENT_QUOTES);

				// search http[s] links and make them as links available again
				// to understand what's going on here, have a look at
				// http://www.php.net/manual/en/function.preg-replace.php

				// create links for websites
				if ($modifyURI) $newBody = Api\Html::activate_links($newBody);

				// create links for email addresses
				// create links for inline images
				if ($modifyURI)
				{
					$newBody = self::resolve_inline_images($newBody, $this->mailbox, $this->uid, $this->partID, 'plain');
				}

				// to display a mailpart of mimetype plain/text, may be better taged as preformatted
				$newBody	= "<pre>".Mail::wordwrap($newBody,90,"\n",'&gt;')."</pre>";
			}
			else
			{
				$alreadyHtmlLawed=false;
				$newBody	= $singleBodyPart['body'];
				//TODO:$newBody	= $this->highlightQuotes($newBody);
				#error_log(print_r($newBody,true));
				if ($useTidy && extension_loaded('tidy'))
				{
					$tidy = new tidy();
					$cleaned = $tidy->repairString($newBody, Mail::$tidy_config,'utf8');
					// Found errors. Strip it all so there's some output
					if($tidy->getStatus() == 2)
					{
						error_log(__METHOD__.' ('.__LINE__.') '.' ->'.$tidy->errorBuffer);
					}
					else
					{
						$newBody = $cleaned;
					}
					// filter only the 'body', as we only want that part, if we throw away the Api\Html
					if (preg_match('`(<htm.+?<body[^>]*>)(.+?)(</body>.*?</html>)`ims', $newBody, $matches) && !empty($matches[2]))
					{
						$hasOther = true;
						$newBody = $matches[2];
					}
				}
				else
				{
					$htmLawed = new Api\Html\HtmLawed();
					// the next line should not be needed, but produces better results on HTML 2 Text conversion,
					// as we switched off HTMLaweds tidy functionality
					$newBody = str_replace(array('&amp;amp;','<DIV><BR></DIV>',"<DIV>&nbsp;</DIV>",'<div>&nbsp;</div>'),array('&amp;','<BR>','<BR>','<BR>'),$newBody);
					$newBody = $htmLawed->run($newBody,Mail::$htmLawed_config);
					$alreadyHtmlLawed=true;
				}
				// do the cleanup, set for the use of purifier
				Mail::getCleanHTML($newBody);

				// removes stuff between http and ?http
				$Protocol = '(http:\/\/|(ftp:\/\/|https:\/\/))';    // only http:// gets removed, other protocolls are shown
				$newBody = preg_replace('~'.$Protocol.'[^>]*\?'.$Protocol.'~sim','$1',$newBody); // removes stuff between http:// and ?http://
				// TRANSFORM MAILTO LINKS TO EMAILADDRESS ONLY, WILL BE SUBSTITUTED BY parseEmail TO CLICKABLE LINK
				$newBody = preg_replace('/(?<!"|href=|href\s=\s|href=\s|href\s=)'.'mailto:([a-z0-9._-]+)@([a-z0-9_-]+)\.([a-z0-9._-]+)/i',
					"\\1@\\2.\\3",
					$newBody);

				// create links for inline images
				if ($modifyURI)
				{
					$newBody = self::resolve_inline_images ($newBody, $this->mailbox, $this->uid, $this->partID);
				}
				// email addresses / mailto links get now activated on client-side
			}

			$body .= $newBody;
		}
		// create links for windows shares
		// \\\\\\\\ == '\\' in real life!! :)
		$body = preg_replace("/(\\\\\\\\)([\w,\\\\,-]+)/i",
			"<a href=\"file:$1$2\" target=\"_blank\"><font color=\"blue\">$1$2</font></a>", $body);

		$body = preg_replace($nonDisplayAbleCharacters,'',$body);

		return $body;
	}

	/**
	 * Resolve inline images from CID to proper url
	 *
	 * @param string $_body message content
	 * @param string $_mailbox mail folder
	 * @param string $_uid uid
	 * @param string $_partID part id
	 * @param string $_messageType = 'html', message type is either html or plain
	 * @return string message body including all CID images replaced
	 */
	public static function resolve_inline_images ($_body,$_mailbox, $_uid, $_partID, $_messageType = 'html')
	{
		if ($_messageType === 'plain')
		{
			return self::resolve_inline_image_byType($_body, $_mailbox, $_uid, $_partID, 'plain');
		}
		else
		{
			foreach(array('src','url','background') as $type)
			{
				$_body = self::resolve_inline_image_byType($_body, $_mailbox, $_uid, $_partID, $type);
			}
			return $_body;
		}
	}

	/**
	 * Replace CID with proper type of content understandable by browser
	 *
	 * @param type $_body content of message
	 * @param type $_mailbox mail box
	 * @param type $_uid uid
	 * @param type $_partID part id
	 * @param type $_type = 'src' type of inline image that needs to be resolved and replaced
	 *	- types: {plain|src|url|background}
	 * @param callback $_link_callback Function to generate the link to the image.  If
	 *	not provided, a default (using mail) will be used.
	 * @return string returns body content including all CID replacements
	 */
	public static function resolve_inline_image_byType ($_body,$_mailbox, $_uid, $_partID, $_type ='src', callable $_link_callback = null)
	{
		/**
		 * Callback to generate the link
		 */
		if(is_null($_link_callback))
		{
			$_link_callback = function($_cid) use ($_mailbox, $_uid, $_partID)
			{
				$linkData = array (
					'menuaction'    => 'mail.mail_ui.displayImage',
					'uid'		=> base64_encode($_uid),
					'mailbox'	=> base64_encode($_mailbox),
					'cid'		=> base64_encode($_cid),
					'partID'	=> $_partID,
				);
				return Egw::link('/index.php', $linkData);
			};
		}

		/**
		 * Callback for preg_replace_callback function
		 * returns matched CID replacement string based on given type
		 * @param array $matches
		 * @param string $_mailbox
		 * @param string $_uid
		 * @param string $_partID
		 * @param string $_type
		 * @return string|boolean returns the replace
		*/
		$replace_callback = function ($matches) use ($_mailbox,$_uid, $_partID,  $_type, $_link_callback)
		{
			if (!$_type)	return false;
			$CID = '';
			// Build up matches according to selected type
			switch ($_type)
			{
				case "plain":
					$CID = $matches[1];
					break;
				case "src":
					// as src:cid contains some kind of url, it is likely to be urlencoded
					$CID = urldecode($matches[2]);
					break;
				case "url":
					$CID = $matches[1];
					break;
				case "background":
					$CID = $matches[2];
					break;
			}

			static $cache = array();	// some caching, if mails containing the same image multiple times

			if (is_array($matches) && $CID)
			{
				$imageURL = call_user_func($_link_callback, $CID);
				// to test without data uris, comment the if close incl. it's body
				if (Api\Header\UserAgent::type() != 'msie' || Api\Header\UserAgent::version() >= 8)
				{
					if (!isset($cache[$imageURL]))
					{
						if ($_type !="background" && !$imageURL)
						{
							$bo = Mail::getInstance(false, mail_ui::$icServerID);
							$attachment = $bo->getAttachmentByCID($_uid, $CID, $_partID);

							// only use data uri for "smaller" images, as otherwise the first display of the mail takes to long
							if (($attachment instanceof Horde_Mime_Part) && $attachment->getBytes() < 8192)	// msie=8 allows max 32k data uris
							{
								$bo->fetchPartContents($_uid, $attachment);
								$cache[$imageURL] = 'data:'.$attachment->getType().';base64,'.base64_encode($attachment->getContents());
							}
							else
							{
								$cache[$imageURL] = $imageURL;
							}
						}
						else
						{
							$cache[$imageURL] = $imageURL;
						}
					}
					$imageURL = $cache[$imageURL];
				}

				// Decides the final result of replacement according to the type
				switch ($_type)
				{
					case "plain":
						return '<img src="'.$imageURL.'" />';
					case "src":
						return 'src="'.$imageURL.'"';
					case "url":
						return 'url('.$imageURL.');';
					case "background":
						return 'background="'.$imageURL.'"';
				}
			}
			return false;
		};

		// return new body content base on chosen type
		switch($_type)
		{
			case"plain":
				return preg_replace_callback("/\[cid:(.*)\]/iU",$replace_callback,$_body);
			case "src":
				return preg_replace_callback("/src=(\"|\')cid:(.*)(\"|\')/iU",$replace_callback,$_body);
			case "url":
				return preg_replace_callback("/url\(cid:(.*)\);/iU",$replace_callback,$_body);
			case "background":
				return preg_replace_callback("/background=(\"|\')cid:(.*)(\"|\')/iU",$replace_callback,$_body);
		}
	}

	/**
	 * Create a new message from modified message then sends the original one to
	 * the trash.
	 *
	 * @param string $_rowID row id
	 * @param string $_subject subject to be replaced with old subject
	 *
	 * Sends json response to client with following data:
	 *		array (
	 *			success => boolean
	 *			msg => string
	 *		)
	 */
	function ajax_saveModifiedMessageSubject ($_rowID, $_subject)
	{
		$response = Api\Json\Response::get();
		$idData = self::splitRowID($_rowID);
		$folder = $idData['folder'];
		try {
			$raw = $this->mail_bo->getMessageRawBody($idData['msgUID'],'', $folder);
			$result = array ('success' => true, 'msg' =>'');
			if ($raw && $_subject)
			{
				$mailer = new Api\Mailer();
				$this->mail_bo->parseRawMessageIntoMailObject($mailer, $raw);
				$mailer->removeHeader('subject');
				$mailer->addHeader('subject', $_subject);
				$this->mail_bo->openConnection();
				$delimiter = $this->mail_bo->getHierarchyDelimiter();
				if($folder == 'INBOX'.$delimiter) $folder='INBOX';
				if ($this->mail_bo->folderExists($folder,true))
				{
					$this->mail_bo->appendMessage($folder, $mailer->getRaw(), null,'\\Seen');
					$this->mail_bo->deleteMessages($idData['msgUID'], $folder);
				}
				else
				{
					$result['success'] = false;
					$result['msg'] = lang('Changing subject failed folder %1 does not exist', $folder);
				}
			}
		} catch (Exception $e) {
			$result['success'] = false;
			$result['msg'] = lang('Changing subject failed because of %1 ', $e->getMessage());
		}
		$response->data($result);
	}

	/**
	 * importMessage
	 * @param array $content = null an array of content
	 */
	function importMessage($content=null)
	{
		//error_log(__METHOD__.__LINE__.$this->mail_bo->getDraftFolder());

		if (!empty($content))
		{
			//error_log(__METHOD__.__LINE__.array2string($content));
			if ($content['vfsfile'])
			{
				$file = $content['vfsfile'] = array(
					'name' => Vfs::basename($content['vfsfile']),
					'type' => Vfs::mime_content_type($content['vfsfile']),
					'file' => Vfs::PREFIX.$content['vfsfile'],
					'size' => filesize(Vfs::PREFIX.$content['vfsfile']),
				);
			}
			else
			{
				$file = $content['uploadForImport'];
			}
			$destination = $content['FOLDER'];

			if (stripos($destination,self::$delimiter)!==false) list($icServerID,$destination) = explode(self::$delimiter,$destination,2);
			if ($icServerID && $icServerID != $this->mail_bo->profileID)
			{
				//error_log(__METHOD__.__LINE__.' change Profile to ->'.$icServerID);
				$this->changeProfile($icServerID);
			}
			//error_log(__METHOD__.__LINE__.self::$delimiter.array2string($destination));
			$importID = Mail::getRandomString();
			$importFailed = false;
			try
			{
				$messageUid = $this->importMessageToFolder($file,$destination,$importID);
			    $linkData = array
			    (
					'id' => $this->createRowID($destination, $messageUid, true),
			    );
			}
			catch (Api\Exception\WrongUserinput $e)
			{
					$importFailed=true;
					$content['msg']		= $e->getMessage();
			}
			if (!$importFailed)
			{
				Api\Json\Response::get()->call('egw.open', $linkData['id'], 'mail', 'view');
				Api\Json\Response::get()->call('window.close');
				return;
			}
		}
		if (!is_array($content)) $content = array();
		if (empty($content['FOLDER']))
		{
			$draft = $this->mail_bo->getDraftFolder();
			$content['FOLDER']=(array)(preg_match($draft, "/::/") ? $draft : $this->mail_bo->profileID.'::'.$draft);
		}
		if (!empty($content['FOLDER']))
		{
			$compose = new mail_compose();
			$sel_options['FOLDER'] = $compose->ajax_searchFolder(0,true);
		}

		$etpl = new Etemplate('mail.importMessage');
		$etpl->setElementAttribute('uploadForImport','onFinish','app.mail.uploadForImport');
		$etpl->exec('mail.mail_ui.importMessage',$content,$sel_options,array(),array(),2);
	}

	/**
	 * importMessageToFolder
	 *
	 * @param array $_formData Array with information of name, type, file and size
	 * @param string $_folder (passed by reference) will set the folder used. must be set with a folder, but will hold modifications if
	 *					folder is modified
	 * @param string $importID ID for the imported message, used by attachments to identify them unambiguously
	 * @return mixed $messageUID or exception
	 */
	function importMessageToFolder($_formData,&$_folder,$importID='')
	{
		$importfailed = false;
		//error_log(__METHOD__.__LINE__.array2string($_formData));
		if (empty($_formData['file'])) $_formData['file'] = $_formData['tmp_name'];
		// check if formdata meets basic restrictions (in tmp dir, or vfs, mimetype, etc.)
		$alert_msg = '';
		try
		{
			$tmpFileName = Mail::checkFileBasics($_formData,$importID);
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			$importfailed = true;
			$alert_msg .= $e->getMessage();
		}
		// -----------------------------------------------------------------------
		if ($importfailed === false)
		{
			$mailObject = new Api\Mailer();
			try
			{
				$this->mail_bo->parseFileIntoMailObject($mailObject, $tmpFileName);
			}
			catch (Api\Exception\AssertionFailed $e)
			{
				$importfailed = true;
				$alert_msg .= $e->getMessage();
			}
			$this->mail_bo->openConnection();
			if (empty($_folder))
			{
				$importfailed = true;
				$alert_msg .= lang("Import of message %1 failed. Destination Folder not set.",$_formData['name']);
			}
			$delimiter = $this->mail_bo->getHierarchyDelimiter();
			if($_folder=='INBOX'.$delimiter) $_folder='INBOX';
			if ($importfailed === false)
			{
				if ($this->mail_bo->folderExists($_folder,true)) {
					try
					{
						$messageUid = $this->mail_bo->appendMessage($_folder,
							$mailObject->getRaw(),
							null,'\\Seen');
					}
					catch (Api\Exception\WrongUserinput $e)
					{
						$importfailed = true;
						$alert_msg .= lang("Import of message %1 failed. Could not save message to folder %2 due to: %3",$_formData['name'],$_folder,$e->getMessage());
					}
				}
				else
				{
					$importfailed = true;
					$alert_msg .= lang("Import of message %1 failed. Destination Folder %2 does not exist.",$_formData['name'],$_folder);
				}
			}
		}
		// set the url to open when refreshing
		if ($importfailed == true)
		{
			throw new Api\Exception\WrongUserinput($alert_msg);
		}
		else
		{
			return $messageUid;
		}
	}

	/**
	 * importMessageFromVFS2DraftAndEdit
	 *
	 * @param array $formData Array with information of name, type, file and size; file is required,
	 *                               name, type and size may be set here to meet the requirements
	 *						Example: $formData['name']	= 'a_email.eml';
	 *								 $formData['type']	= 'message/rfc822';
	 *								 $formData['file']	= 'vfs://default/home/leithoff/a_email.eml';
	 *								 $formData['size']	= 2136;
	 * @return void
	 */
	function importMessageFromVFS2DraftAndEdit($formData='')
	{
		$this->importMessageFromVFS2DraftAndDisplay($formData,'edit');
	}

	/**
	 * importMessageFromVFS2DraftAndDisplay
	 *
	 * @param array $formData Array with information of name, type, file and size; file is required,
	 *                               name, type and size may be set here to meet the requirements
	 *						Example: $formData['name']	= 'a_email.eml';
	 *								 $formData['type']	= 'message/rfc822';
	 *								 $formData['file']	= 'vfs://default/home/leithoff/a_email.eml';
	 *								 $formData['size']	= 2136;
	 * @param string $mode mode to open ImportedMessage display and edit are supported
	 * @return void
	 */
	function importMessageFromVFS2DraftAndDisplay($formData='',$mode='display')
	{
		if (empty($formData)) if (isset($_REQUEST['formData'])) $formData = $_REQUEST['formData'];
		//error_log(__METHOD__.__LINE__.':'.array2string($formData).' Mode:'.$mode.'->'.function_backtrace());
		$draftFolder = $this->mail_bo->getDraftFolder(false);
		$importID = Mail::getRandomString();

		// handling for mime-data hash
		if (!empty($formData['data']))
		{
			$formData['file'] = 'egw-data://'.$formData['data'];
		}
		// name should be set to meet the requirements of checkFileBasics
		if (parse_url($formData['file'],PHP_URL_SCHEME) == 'vfs' && empty($formData['name']))
		{
			$buff = explode('/',$formData['file']);
			if (is_array($buff)) $formData['name'] = array_pop($buff); // take the last part as name
		}
		// type should be set to meet the requirements of checkFileBasics
		if (parse_url($formData['file'],PHP_URL_SCHEME) == 'vfs' && empty($formData['type']))
		{
			$buff = explode('.',$formData['file']);
			$suffix = '';
			if (is_array($buff)) $suffix = array_pop($buff); // take the last extension to check with ext2mime
			if (!empty($suffix)) $formData['type'] = Api\MimeMagic::ext2mime($suffix);
		}
		// size should be set to meet the requirements of checkFileBasics
		if (parse_url($formData['file'],PHP_URL_SCHEME) == 'vfs' && !isset($formData['size']))
		{
			$formData['size'] = strlen($formData['file']); // set some size, to meet requirements of checkFileBasics
		}
		try
		{
			$messageUid = $this->importMessageToFolder($formData,$draftFolder,$importID);
			$linkData = array
			(
		        'menuaction'    => ($mode=='display'?'mail.mail_ui.displayMessage':'mail.mail_compose.composeFromDraft'),
				'id'		=> $this->createRowID($draftFolder,$messageUid,true),
				'deleteDraftOnClose' => 1,
			);
			if ($mode!='display')
			{
				unset($linkData['deleteDraftOnClose']);
				$linkData['method']	='importMessageToMergeAndSend';
			}
			else
			{
				$linkData['mode']=$mode;
			}
			Egw::redirect_link('/index.php',$linkData);
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			Framework::window_close($e->getMessage());
		}
	}

	/**
	 * loadEmailBody
	 *
	 * @param string _messageID UID
	 *
	 * @return xajax response
	 */
	function loadEmailBody($_messageID=null,$_partID=null,$_htmloptions=null)
	{
		//error_log(__METHOD__.__LINE__.array2string($_GET));
		if (!$_messageID && !empty($_GET['_messageID'])) $_messageID = $_GET['_messageID'];
		if (!$_partID && !empty($_GET['_partID'])) $_partID = $_GET['_partID'];
		if (!$_htmloptions && !empty($_GET['_htmloptions'])) $_htmloptions = $_GET['_htmloptions'];
		if(Mail::$debug) error_log(__METHOD__."->".print_r($_messageID,true).",$_partID,$_htmloptions");
		if (empty($_messageID)) return "";
		$uidA = self::splitRowID($_messageID);
		$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
		$messageID = $uidA['msgUID'];
		$icServerID = $uidA['profileID'];
		//something went wrong. there is a $_messageID but no $messageID: means $_messageID is crippeled
		if (empty($messageID)) return "";
		if ($icServerID && $icServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile to ->'.$icServerID);
			$this->changeProfile($icServerID);
		}

		$bodyResponse = $this->get_load_email_data($messageID,$_partID,$folder,$_htmloptions, $_POST['smime_passphrase']);
		//error_log(array2string($bodyResponse));
		echo $bodyResponse;

	}

	/**
	 * ajax_setFolderStatus - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 * gets the counters and sets the text of a treenode if needed (unread Messages found)
	 * @param array $_folder folders to refresh its unseen message counters
	 * @return nothing
	 */
	function ajax_setFolderStatus($_folder)
	{
		Api\Translation::add_app('mail');
		//error_log(__METHOD__.__LINE__.array2string($_folder));
		if ($_folder)
		{
			$this->mail_bo->getHierarchyDelimiter(false);
			$oA = array();
			foreach ($_folder as $_folderName)
			{
				list($profileID,$folderName) = explode(self::$delimiter,$_folderName,2);
				if (is_numeric($profileID))
				{
					if ($profileID != $this->mail_bo->profileID) continue; // only current connection
					if ($folderName)
					{
						try
						{
							$fS = $this->mail_bo->getFolderStatus($folderName,false,false,false);
						}
						catch (Exception $e)
						{
							if (Mail::$debug) error_log(__METHOD__,' ()'.$e->getMessage ());
							continue;
						}
						if (in_array($fS['shortDisplayName'],Mail::$autoFolders)) $fS['shortDisplayName']=lang($fS['shortDisplayName']);
						//error_log(__METHOD__.__LINE__.array2string($fS));
						if ($fS['unseen'])
						{
							$oA[$_folderName] = $fS['shortDisplayName'].' ('.$fS['unseen'].')';
						}
						if ($fS['unseen']==0 && $fS['shortDisplayName'])
						{
							$oA[$_folderName] = $fS['shortDisplayName'];
						}
					}
				}
			}
			//error_log(__METHOD__.__LINE__.array2string($oA));
			if ($oA)
			{
				$response = Api\Json\Response::get();
				$response->call('app.mail.mail_setFolderStatus',$oA);
			}
		}
	}

	/**
	 * This function creates folder/subfolder based on its selected parent
	 *
	 * @param string $_parent folder name or profile+folder name to add a folder to
	 * @param string $_new new folder name to be created
	 *
	 */
	function ajax_addFolder($_parent, $_new)
	{
		$error='';
		$created = false;
		$response = Api\Json\Response::get();
		$del = $this->mail_bo->getHierarchyDelimiter(false);
		if (strpos($_new, $del) !== FALSE)
		{
			return $response->call('egw.message', lang('failed to rename %1 ! Reason: %2 is not allowed!',$_parent, $del));
		}
		if ($_parent)
		{
			$parent = $this->mail_bo->decodeEntityFolderName($_parent);
			//the conversion is handeled by horde, frontend interaction is all utf-8
			$new = $this->mail_bo->decodeEntityFolderName($_new);

			list($profileID,$p_no_delimiter) = explode(self::$delimiter,$parent,2);

			if (is_numeric($profileID))
			{
				if ($profileID != $this->mail_bo->profileID) $this->changeProfile ($profileID);
				$delimiter = $this->mail_bo->getHierarchyDelimiter(false);
				$parts = explode($delimiter,$new);

				if (!!empty($parent)) $folderStatus = $this->mail_bo->getFolderStatus($parent,false);

				//open the INBOX
				$this->mail_bo->reopen('INBOX');

				// if $new has delimiter ($del) in it, we need to create the subtree
				if (!empty($parts))
				{
					$counter = 0;
					foreach($parts as $subTree)
					{
						$err = null;
						if(($new = $this->mail_bo->createFolder($p_no_delimiter, $subTree, $err)))
						{
							$counter++;
							if (!$p_no_delimiter)
							{
								// we first test below INBOX, because testing just the name wrongly reports it as subscribed
								// for servers not allowing to create folders parallel to INBOX
								$status = $this->mail_bo->getFolderStatus('INBOX'.$delimiter.$new,false, true, true) ?:
									$this->mail_bo->getFolderStatus($new,false, true, true);
								if (!$status['subscribed'])
								{
									try
									{
										$this->mail_bo->icServer->subscribeMailbox ('INBOX'.$delimiter.$new);
									}
									catch(Horde_Imap_Client_Exception $e)
									{
										$error = Lang('Folder %1 has been created successfully,'.
												' although the subscription failed because of %2', $new, $e->getMessage());
									}
								}
							}
						}
						else
						{
							if (!$p_no_delimiter)
							{
								$new = $this->mail_bo->createFolder('INBOX', $subTree, $err);
								if ($new) $counter++;
							}
							else
							{
								$error .= $err;
							}
						}
					}
					if ($counter == count($parts)) $created=true;
				}
				if (!empty($new)) $this->mail_bo->reopen($new);
			}


			if ($created===true && $error =='')
			{
				$this->mail_bo->resetFolderObjectCache($profileID);
				if ( $folderStatus['shortDisplayName'])
				{
					$nodeInfo = array($parent=>$folderStatus['shortDisplayName']);
				}
				else
				{
					$nodeInfo = array($profileID=>lang('INBOX'));
				}
				$response->call('app.mail.mail_reloadNode',$nodeInfo);
			}
			else
			{
				if ($error)
				{
					$response->call('egw.message',$error);
				}
			}
		}
		else {
			error_log(__METHOD__.__LINE__."()"."This function needs a parent folder to work!");
		}
	}

	/**
	 * ajax_renameFolder - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 * @param string $_folderName folder to rename and refresh
	 * @param string $_newName new foldername
	 * @return nothing
	 */
	function ajax_renameFolder($_folderName, $_newName)
	{
		if (Mail::$debug) error_log(__METHOD__.__LINE__.' OldFolderName:'.array2string($_folderName).' NewName:'.array2string($_newName));
		//error_log(__METHOD__.__LINE__.array2string($oA));
		$response = Api\Json\Response::get();
		$del = $this->mail_bo->getHierarchyDelimiter(false);
		if (strpos($_newName, $del) !== FALSE)
		{
			return $response->call('egw.message', lang('failed to rename %1 ! Reason: %2 is not allowed!',$_folderName, $del));
		}

		if ($_folderName)
		{
			Api\Translation::add_app('mail');
			$decodedFolderName = $this->mail_bo->decodeEntityFolderName($_folderName);
			$_newName = $this->mail_bo->decodeEntityFolderName($_newName);

			$oA = array();
			list($profileID,$folderName) = explode(self::$delimiter,$decodedFolderName,2);
			$hasChildren = false;
			if (is_numeric($profileID))
			{
				if ($profileID != $this->mail_bo->profileID) $this->changeProfile ($profileID);
				$pA = explode($del,$folderName);
				array_pop($pA);
				$parentFolder = implode($del,$pA);
				if (strtoupper($folderName)!= 'INBOX')
				{
					//error_log(__METHOD__.__LINE__."$folderName, $parentFolder, $_newName");
					$oldFolderInfo = $this->mail_bo->getFolderStatus($folderName,false);
					//error_log(__METHOD__.__LINE__.array2string($oldFolderInfo));
					if (!empty($oldFolderInfo['attributes']) && stripos(array2string($oldFolderInfo['attributes']),'\hasnochildren')=== false)
					{
						$hasChildren=true; // translates to: hasChildren -> dynamicLoading
						$delimiter = $this->mail_bo->getHierarchyDelimiter();
						$nameSpace = $this->mail_bo->_getNameSpaces();
						$prefix = $this->mail_bo->getFolderPrefixFromNamespace($nameSpace, $folderName);
						//error_log(__METHOD__.__LINE__.'->'."$_folderName, $delimiter, $prefix");
						$fragments = array();
						$subFolders = $this->mail_bo->getMailBoxesRecursive($folderName, $delimiter, $prefix);
						foreach ($subFolders as $k => $folder)
						{
							// we do not monitor failure or success on subfolders
							if ($folder == $folderName)
							{
								unset($subFolders[$k]);
							}
							else
							{
								$rv = $this->mail_bo->icServer->subscribeMailbox($folder, false);
								$fragments[$profileID.self::$delimiter.$folder] = substr($folder,strlen($folderName));
							}
						}
						//error_log(__METHOD__.__LINE__.' Fetched Subfolders->'.array2string($fragments));
					}

					$this->mail_bo->reopen('INBOX');
					$success = false;
					try
					{
						if(($newFolderName = $this->mail_bo->renameFolder($folderName, $parentFolder, $_newName)))
						{
							$this->mail_bo->resetFolderObjectCache($profileID);
							//enforce the subscription to the newly named server, as it seems to fail for names with umlauts
							$this->mail_bo->icServer->subscribeMailbox($newFolderName, true);
							$this->mail_bo->icServer->subscribeMailbox($folderName, false);
							$success = true;
						}
					}
					catch (Exception $e)
					{
						$newFolderName=$folderName;
						$msg = $e->getMessage();
					}
					$this->mail_bo->reopen($newFolderName);
					$fS = $this->mail_bo->getFolderStatus($newFolderName,false);
					//error_log(__METHOD__.__LINE__.array2string($fS));
					if ($hasChildren)
					{
						$subFolders = $this->mail_bo->getMailBoxesRecursive($newFolderName, $delimiter, $prefix);
						foreach ($subFolders as $k => $folder)
						{
							// we do not monitor failure or success on subfolders
							if ($folder == $folderName)
							{
								unset($subFolders[$k]);
							}
							else
							{
								$rv = $this->mail_bo->icServer->subscribeMailbox($folder, true);
							}
						}
						//error_log(__METHOD__.__LINE__.' Fetched Subfolders->'.array2string($subFolders));
					}

					$oA[$_folderName]['id'] = $profileID.self::$delimiter.$newFolderName;
					$oA[$_folderName]['olddesc'] = $oldFolderInfo['shortDisplayName'];
					if ($fS['unseen'])
					{
						$oA[$_folderName]['desc'] = $fS['shortDisplayName'].' ('.$fS['unseen'].')';

					}
					else
					{
						$oA[$_folderName]['desc'] = $fS['shortDisplayName'];
					}
					foreach($fragments as $oldFolderName => $fragment)
					{
						//error_log(__METHOD__.__LINE__.':'.$oldFolderName.'->'.$profileID.self::$delimiter.$newFolderName.$fragment);
						$oA[$oldFolderName]['id'] = $profileID.self::$delimiter.$newFolderName.$fragment;
						$oA[$oldFolderName]['olddesc'] = '#skip-user-interaction-message#';
						$fS = $this->mail_bo->getFolderStatus($newFolderName.$fragment,false);
						if ($fS['unseen'])
						{
							$oA[$oldFolderName]['desc'] = $fS['shortDisplayName'].' ('.$fS['unseen'].')';

						}
						else
						{
							$oA[$oldFolderName]['desc'] = $fS['shortDisplayName'];
						}
					}
				}
			}
			if ($folderName==$this->mail_bo->sessionData['mailbox'])
			{
				$this->mail_bo->sessionData['mailbox']=$newFolderName;
				$this->mail_bo->saveSessionData();
			}
			//error_log(__METHOD__.__LINE__.array2string($oA));
			$response = Api\Json\Response::get();
			if ($oA && $success)
			{
				$response->call('app.mail.mail_setLeaf',$oA);
			}
			else
			{
				$response->call('egw.refresh',lang('failed to rename %1 ! Reason: %2',$oldFolderName,$msg),'mail');
			}
		}
	}

	/**
	 * reload node
	 *
	 * @param string _folderName  folder to reload
	 * @param boolean $_subscribedOnly = true
	 * @return void
	 */
	function ajax_reloadNode($_folderName,$_subscribedOnly=true)
	{
		Api\Translation::add_app('mail');
		$oldPrefForSubscribedOnly = !$this->mail_bo->mailPreferences['showAllFoldersInFolderPane'];
		$decodedFolderName = $this->mail_bo->decodeEntityFolderName($_folderName);
		list($profileID,$folderName) = explode(self::$delimiter,$decodedFolderName,2);
		if ($profileID != $this->mail_bo->profileID) $this->changeProfile($profileID);

		// if pref and required mode dont match -> reset the folderObject cache to ensure
		// that we get what we request
		if ($_subscribedOnly != $oldPrefForSubscribedOnly) $this->mail_bo->resetFolderObjectCache($profileID);

		if (!empty($folderName))
		{
			$parentFolder=(!empty($folderName)?$folderName:'INBOX');
			$folderInfo = $this->mail_bo->getFolderStatus($parentFolder,false,false,false);
			if ($folderInfo['unseen'])
			{
				$folderInfo['shortDisplayName'] = $folderInfo['shortDisplayName'].' ('.$folderInfo['unseen'].')';
			}
			if ($folderInfo['unseen']==0 && $folderInfo['shortDisplayName'])
			{
				$folderInfo['shortDisplayName'] = $folderInfo['shortDisplayName'];
			}

			$refreshData = array(
				$profileID.self::$delimiter.$parentFolder=>$folderInfo['shortDisplayName']);
		}
		else
		{
			$refreshData = array(
				$profileID=>lang('INBOX')//string with no meaning lateron
			);
		}
		// Send full info back in the response
		$response = Api\Json\Response::get();
		foreach($refreshData as $folder => &$name)
		{
			$name = $this->mail_tree->getTree($folder,$profileID,1,false, $_subscribedOnly,true);
		}
		$response->call('app.mail.mail_reloadNode',$refreshData);

	}

	/**
	 * ResolveWinmail fetches the encoded attachments
	 * from winmail.dat and will response expected structure back
	 * to client in order to display them.
	 *
	 * Note: this ajax function should only be called via
	 * nm mail selection as it does not support profile change
	 * and uses the current available ic_server connection.
	 *
	 * @param type $_rowid row id from nm
	 *
	 */
	function ajax_resolveWinmail ($_rowid)
	{
		$response = Api\Json\Response::get();

		$idParts = self::splitRowID($_rowid);
		$uid = $idParts['msgUID'];
		$mbox = $idParts['folder'];

		$attachments = $this->mail_bo->getMessageAttachments($uid, null, null, false,true,true,$mbox);
		if (is_array($attachments))
		{
			$attachments = $this->createAttachmentBlock($attachments, $_rowid, $uid, $mbox, false);
			$response->data($attachments);
		}
		else
		{
			$response->call('egw.message', lang('Can not resolve the winmail.dat attachment!'));
		}
	}

	/**
	 * move folder
	 *
	 * @param string _folderName  folder to vove
	 * @param string _target target folder
	 *
	 * @return void
	 */
	function ajax_MoveFolder($_folderName, $_target)
	{
		if (Mail::$debug) error_log(__METHOD__.__LINE__."Move Folder: $_folderName to Target: $_target");
		if ($_folderName)
		{
			$decodedFolderName = $this->mail_bo->decodeEntityFolderName($_folderName);
			$_newLocation2 = $this->mail_bo->decodeEntityFolderName($_target);
			list($profileID,$folderName) = explode(self::$delimiter,$decodedFolderName,2);
			list($newProfileID,$_newLocation) = explode(self::$delimiter,$_newLocation2,2);
			if ($profileID != $this->mail_bo->profileID || $profileID != $newProfileID) $this->changeProfile($profileID);
			$del = $this->mail_bo->getHierarchyDelimiter(false);
			$hasChildren = false;
			if (is_numeric($profileID))
			{
				$pA = explode($del,$folderName);
				$namePart = array_pop($pA);
				$_newName = $namePart;
				$oldParentFolder = implode($del,$pA);
				$parentFolder = $_newLocation;

				if (strtoupper($folderName)!= 'INBOX' &&
					(($oldParentFolder === $parentFolder) || //$oldParentFolder == $parentFolder means move on same level
					(($oldParentFolder != $parentFolder &&
					strlen($parentFolder)>0 && strlen($folderName)>0 &&
					strpos($parentFolder,$folderName)===false)))) // indicates that we move the older up the tree within its own branch
				{
					//error_log(__METHOD__.__LINE__."$folderName, $parentFolder, $_newName");
					$oldFolderInfo = $this->mail_bo->getFolderStatus($folderName,false,false,false);
					//error_log(__METHOD__.__LINE__.array2string($oldFolderInfo));
					if (!empty($oldFolderInfo['attributes']) && stripos(array2string($oldFolderInfo['attributes']),'\hasnochildren')=== false)
					{
						$hasChildren=true; // translates to: hasChildren -> dynamicLoading
						$delimiter = $this->mail_bo->getHierarchyDelimiter();
						$nameSpace = $this->mail_bo->_getNameSpaces();
						$prefix = $this->mail_bo->getFolderPrefixFromNamespace($nameSpace, $folderName);
						//error_log(__METHOD__.__LINE__.'->'."$_folderName, $delimiter, $prefix");

						$subFolders = $this->mail_bo->getMailBoxesRecursive($folderName, $delimiter, $prefix);
						foreach ($subFolders as $k => $folder)
						{
							// we do not monitor failure or success on subfolders
							if ($folder == $folderName)
							{
								unset($subFolders[$k]);
							}
							else
							{
								$rv = $this->mail_bo->icServer->subscribeMailbox($folder, false);
							}
						}
					}

					$this->mail_bo->reopen('INBOX');
					$success = false;
					try
					{
						if(($newFolderName = $this->mail_bo->renameFolder($folderName, $parentFolder, $_newName)))
						{
							$this->mail_bo->resetFolderObjectCache($profileID);
							//enforce the subscription to the newly named server, as it seems to fail for names with umlauts
							$this->mail_bo->icServer->subscribeMailbox($newFolderName, true);
							$this->mail_bo->icServer->subscribeMailbox($folderName, false);
							$this->mail_bo->resetFolderObjectCache($profileID);
							$success = true;
						}
					}
					catch (Exception $e)
					{
						$newFolderName=$folderName;
						$msg = $e->getMessage();
					}
					$this->mail_bo->reopen($parentFolder);
					$this->mail_bo->getFolderStatus($parentFolder,false,false,false);
					//error_log(__METHOD__.__LINE__.array2string($fS));
					if ($hasChildren)
					{
						$subFolders = $this->mail_bo->getMailBoxesRecursive($parentFolder, $delimiter, $prefix);
						foreach ($subFolders as $k => $folder)
						{
							// we do not monitor failure or success on subfolders
							if ($folder == $folderName)
							{
								unset($subFolders[$k]);
							}
							else
							{
								$rv = $this->mail_bo->icServer->subscribeMailbox($folder, true);
							}
						}
						//error_log(__METHOD__.__LINE__.' Fetched Subfolders->'.array2string($subFolders));
					}
				}
			}
			if ($folderName==$this->mail_bo->sessionData['mailbox'])
			{
				$this->mail_bo->sessionData['mailbox']=$newFolderName;
				$this->mail_bo->saveSessionData();
			}
			//error_log(__METHOD__.__LINE__.array2string($oA));
			$response = Api\Json\Response::get();
			if ($success)
			{
				Api\Translation::add_app('mail');

				$oldFolderInfo = $this->mail_bo->getFolderStatus($oldParentFolder,false,false,false);
				$folderInfo = $this->mail_bo->getFolderStatus($parentFolder,false,false,false);
				$refreshData = array(
					$profileID.self::$delimiter.$oldParentFolder=>$oldFolderInfo['shortDisplayName'],
					$profileID.self::$delimiter.$parentFolder=>$folderInfo['shortDisplayName']);
				// if we move the folder within the same parent-branch of the tree, there is no need no refresh the upper part
				if (strlen($parentFolder)>strlen($oldParentFolder) && strpos($parentFolder,$oldParentFolder)!==false) unset($refreshData[$profileID.self::$delimiter.$parentFolder]);
				if (count($refreshData)>1 && strlen($oldParentFolder)>strlen($parentFolder) && strpos($oldParentFolder,$parentFolder)!==false) unset($refreshData[$profileID.self::$delimiter.$oldParentFolder]);

				// Send full info back in the response
				foreach($refreshData as $folder => &$name)
				{
					$name = $this->mail_tree->getTree($folder,$profileID,1,false,!$this->mail_bo->mailPreferences['showAllFoldersInFolderPane'],true);
				}
				$response->call('app.mail.mail_reloadNode',$refreshData);

			}
			else
			{
				$response->call('egw.refresh',lang('failed to move %1 ! Reason: %2',$folderName,$msg),'mail');
			}
		}
	}

	/**
	 * ajax_deleteFolder - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 * @param string $_folderName folder to delete
	 * @param boolean $_return = false wheter return the success value (true) or send response to client (false)
	 * @return nothing
	 */
	function ajax_deleteFolder($_folderName, $_return = false)
	{
		//error_log(__METHOD__.__LINE__.' OldFolderName:'.array2string($_folderName));
		$success = false;
		if ($_folderName)
		{
			$decodedFolderName = $this->mail_bo->decodeEntityFolderName($_folderName);
			$oA = array();
			list($profileID,$folderName) = explode(self::$delimiter,$decodedFolderName,2);
			if (is_numeric($profileID) && $profileID != $this->mail_bo->profileID) $this->changeProfile ($profileID);
			$del = $this->mail_bo->getHierarchyDelimiter(false);
			$hasChildren = false;
			if (is_numeric($profileID))
			{
				$pA = explode($del,$folderName);
				array_pop($pA);
				if (strtoupper($folderName)!= 'INBOX')
				{
					//error_log(__METHOD__.__LINE__."$folderName,  implode($del,$pA), $_newName");
					$oA = array();
					$subFolders = array();
					$oldFolderInfo = $this->mail_bo->getFolderStatus($folderName,false,false,false);
					//error_log(__METHOD__.__LINE__.array2string($oldFolderInfo));
					if (!empty($oldFolderInfo['attributes']) && stripos(array2string($oldFolderInfo['attributes']),'\hasnochildren')=== false)
					{
						$hasChildren=true; // translates to: hasChildren -> dynamicLoading
						$ftD = array();
						$delimiter = $this->mail_bo->getHierarchyDelimiter();
						$nameSpace = $this->mail_bo->_getNameSpaces();
						$prefix = $this->mail_bo->getFolderPrefixFromNamespace($nameSpace, $folderName);
						//error_log(__METHOD__.__LINE__.'->'."$_folderName, $delimiter, $prefix");
						$subFolders = $this->mail_bo->getMailBoxesRecursive($folderName, $delimiter, $prefix);
						//error_log(__METHOD__.__LINE__.'->'."$folderName, $delimiter, $prefix");
						foreach ($subFolders as $k => $f)
						{
							$ftD[substr_count($f,$delimiter)][]=$f;
						}
						krsort($ftD,SORT_NUMERIC);//sort per level
						//we iterate per level of depth of the subtree, deepest nesting is to be deleted first, and then up the tree
						foreach($ftD as $k => $lc)//collection per level
						{
							foreach($lc as $f)//folders contained in that level
							{
								try
								{
									//error_log(__METHOD__.__LINE__.array2string($f).'<->'.$folderName);
									$this->mail_bo->deleteFolder($f);
									$success = true;
									if ($f==$folderName) $oA[$_folderName] = $oldFolderInfo['shortDisplayName'];
								}
								catch (Exception $e)
								{
									$msg .= ($msg?' ':'').lang("Failed to delete %1. Server responded:",$f).$e->getMessage();
									$success = false;
								}
							}
						}
					}
					else
					{
						try
						{
							$this->mail_bo->deleteFolder($folderName);
							$success = true;
							$oA[$_folderName] = $oldFolderInfo['shortDisplayName'];
						}
						catch (Exception $e)
						{
							$msg = $e->getMessage();
							$success = false;
						}
					}
				}
				else
				{
					$msg = lang("refused to delete folder INBOX");
				}
			}
			if ($_return) return $success;
			$response = Api\Json\Response::get();
			if ($success)
			{
				//error_log(__METHOD__.__LINE__.array2string($oA));
				$response->call('app.mail.mail_removeLeaf',$oA);
			}
			else
			{
				$response->call('egw.refresh',lang('failed to delete %1 ! Reason: %2',$oldFolderInfo['shortDisplayName'],$msg),'mail');
			}
		}
	}

	/**
	 * empty changeProfile - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 *
	 * Made static to NOT call __construct, as it would connect to old server, before going to new one
	 *
	 * @param int $icServerID New profile / server ID
	 * @param bool $getFolders The client needs the folders for the profile
	 * @return nothing
	 */
	public static function ajax_changeProfile($icServerID, $getFolders = true, $exec_id=null)
	{
		$response = Api\Json\Response::get();

		$previous_id = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];

		if ($icServerID && $icServerID != $previous_id)
		{
			$mail_ui = new mail_ui(false);	// do NOT run constructor, as we call changeProfile anyway
			try
			{
				$mail_ui->changeProfile($icServerID);
				// if we have an eTemplate exec_id, also send changed actions
				if ($exec_id && ($actions = $mail_ui->get_actions()))
				{
					$response->generic('assign', array(
						'etemplate_exec_id' => $exec_id,
						'id' => 'nm',
						'key' => 'actions',
						'value' => $actions,
					));
				}
			}
			catch (Exception $e) {
				self::callWizard($e->getMessage(),true, 'error');
			}
		}
		else
		{
			$mail_ui = new mail_ui(true);	// run constructor
		}
	}

	/**
	 * ajax_refreshVacationNotice - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 *	Note: only the activeProfile VacationNotice is refreshed
	 * @param int $icServerID profileId / server ID to work on; may be empty -> then activeProfile is used
	 *						if other than active profile; nothing is done!
	 * @return nothing
	 */
	public static function ajax_refreshVacationNotice($icServerID=null)
	{
		//Get vacation from cache if it's available
		$cachedVacations = Api\Cache::getCache(Api\Cache::INSTANCE, 'email', 'vacationNotice'.$GLOBALS['egw_info']['user']['account_lid']);
		$vacation = $cachedVacations[$icServerID];

		if (!$vacation)
		{
			try
			{
				// Create mail app object
				$mail = new mail_ui();

				if (empty($icServerID)) $icServerID = $mail->Mail->profileID;
				if ($icServerID != $mail->Mail->profileID) return;

				$vacation = $mail->gatherVacation($cachedVacations);
			} catch (Exception $e) {
				$vacation=false;
				error_log(__METHOD__.__LINE__." ".$e->getMessage());
				unset($e);
			}
		}

		if($vacation) {
			if (is_array($vacation) && ($vacation['status'] == 'on' || $vacation['status']=='by_date'))
			{
				$dtfrmt = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
				$refreshData['vacationnotice'] = lang('Vacation notice is active');
				$refreshData['vacationrange'] = ($vacation['status']=='by_date'? Api\DateTime::server2user($vacation['start_date'],$dtfrmt,true).($vacation['end_date']>$vacation['start_date']?'->'.Api\DateTime::server2user($vacation['end_date']+ 24*3600-1,$dtfrmt,true):''):'');
				if($vacation['status'] == 'by_date' && $vacation['end_date'] + 24 * 3600 < time())
				{
					$refreshData = null;
				}
			}
		}
		if ($vacation==false)
		{
			$refreshData = null;
		}
		$response = Api\Json\Response::get();
		$response->call('app.mail.mail_refreshVacationNotice',$refreshData);
	}

	/**
	 * ajax_refreshFilters - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 *	Note: only the activeProfile Filters are refreshed
	 * @param int $icServerID profileId / server ID to work on; may be empty -> then activeProfile is used
	 *						if other than active profile; nothing is done!
	 * @return nothing
	 */
	function ajax_refreshFilters($icServerID=null)
	{
		//error_log(__METHOD__.__LINE__.array2string($icServerId));
		if (empty($icServerID)) $icServerID = $this->mail_bo->profileID;
		if (is_null(Mail::$supportsORinQuery) || !isset(Mail::$supportsORinQuery[$this->mail_bo->profileID]))
		{
			Mail::$supportsORinQuery = Api\Cache::getCache(Api\Cache::INSTANCE,'email','supportsORinQuery'.trim($GLOBALS['egw_info']['user']['account_id']), null, array(), 60*60*10);
			if (!isset(Mail::$supportsORinQuery[$this->mail_bo->profileID])) Mail::$supportsORinQuery[$this->mail_bo->profileID]=true;
		}
		if (!Mail::$supportsORinQuery[$this->mail_bo->profileID])
		{
			unset($this->searchTypes['quick']);
			unset($this->searchTypes['quickwithcc']);
		}
		if ( $this->mail_bo->icServer->hasCapability('SUPPORTS_KEYWORDS'))
		{
			$this->statusTypes = array_merge($this->statusTypes,array(
				'keyword1'	=> 'important',//lang('important'),
				'keyword2'	=> 'job',	//lang('job'),
				'keyword3'	=> 'personal',//lang('personal'),
				'keyword4'	=> 'to do',	//lang('to do'),
				'keyword5'	=> 'later',	//lang('later'),
			));
		}
		else
		{
			$keywords = array('keyword1','keyword2','keyword3','keyword4','keyword5');
			foreach($keywords as &$k)
			{
				if (array_key_exists($k,$this->statusTypes)) unset($this->statusTypes[$k]);
			}
		}

		$response = Api\Json\Response::get();
		$response->call('app.mail.mail_refreshCatIdOptions',$this->searchTypes);
		$response->call('app.mail.mail_refreshFilterOptions',$this->statusTypes);
		$response->call('app.mail.mail_refreshFilter2Options',array(''=>lang('No Sneak Preview in list'),1=>lang('Sneak Preview in list')));

	}

	/**
	 * This function asks quota from IMAP server and makes the
	 * result as JSON response to send it to mail_sendQuotaDisplay
	 * function in client side.
	 *
	 * @param string $icServerID = null
	 *
	 */
	function ajax_refreshQuotaDisplay($icServerID=null)
	{
		Api\Translation::add_app('mail');
		if (is_null($icServerID)) $icServerID = $this->mail_bo->profileID;
		$rememberServerID = $this->mail_bo->profileID;
		try
		{
			if ($icServerID && $icServerID != $this->mail_bo->profileID)
			{
				$this->changeProfile($icServerID);
			}
			$quota = $this->mail_bo->getQuotaRoot();
		} catch (Exception $e) {
			$quota['limit'] = 'NOT SET';
			error_log(__METHOD__.__LINE__." ".$e->getMessage());
			unset($e);
		}

		if($quota !== false && $quota['limit'] != 'NOT SET') {
			$quotainfo = $this->quotaDisplay($quota['usage'], $quota['limit']);
			$quotaMin = ceil($quotainfo['freespace']/pow(1024, 2));
			$quota_limit_warning = isset(mail::$mailConfig['quota_limit_warning']) ? mail::$mailConfig['quota_limit_warning'] : 30;
			$content = array (
				'quota'				=> $quotainfo['text'],
				'quotainpercent'	=> (string)$quotainfo['percent'],
				'quotaclass'		=> $quotainfo['class'],
				'quotanotsupported'	=> "",
				'profileid'			=> $icServerID,
				'quotawarning'		=> $quotaMin <  $quota_limit_warning ? true : false,
				'quotafreespace'	=> Mail::show_readable_size($quotainfo['freespace'])
			);
		}
		else
		{
			$content = array (
				'quota'				=> lang("Quota not provided by server"),
				'quotaclass'		=> "mail_DisplayNone",
				'quotanotsupported'	=> "mail_DisplayNone"
			);
		}
		if ($rememberServerID != $this->mail_bo->profileID)
		{
			try
			{
				$this->changeProfile($rememberServerID);
			} catch (Exception $e) {
				unset($e);
			}
		}
		$response = Api\Json\Response::get();
		$response->call('app.mail.mail_setQuotaDisplay',array('data'=>$content));
	}

	/**
	 * Empty spam/junk folder
	 *
	 * @param string $icServerID id of the server to empty its junkFolder
	 * @param string $selectedFolder seleted(active) folder by nm filter
	 * @return nothing
	 */
	function ajax_emptySpam($icServerID, $selectedFolder)
	{
		//error_log(__METHOD__.__LINE__.' '.$icServerID);
		Api\Translation::add_app('mail');
		$response = Api\Json\Response::get();
		$rememberServerID = $this->mail_bo->profileID;
		if ($icServerID && $icServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile to ->'.$icServerID);
			$this->changeProfile($icServerID);
		}
		$junkFolder = $this->mail_bo->getJunkFolder();
		if(!empty($junkFolder)) {
			if ($selectedFolder == $icServerID.self::$delimiter.$junkFolder)
			{
				// Lock the tree if the active folder is junk folder
				$response->call('app.mail.lock_tree');
			}
			$this->mail_bo->deleteMessages('all',$junkFolder,'remove_immediately');

			$heirarchyDelimeter = $this->mail_bo->getHierarchyDelimiter(true);
			$parts = explode($heirarchyDelimeter, $junkFolder);
			$fShortName = array_pop($parts);
			$fStatus = array(
				$icServerID.self::$delimiter.$junkFolder => lang($fShortName)
			);
			//Call to reset folder status counter, after junkFolder triggered not from Junk folder
			//-as we don't have junk folder specific information available on client-side we need to deal with it on server
			$response->call('app.mail.mail_setFolderStatus',$fStatus);
		}
		if ($rememberServerID != $this->mail_bo->profileID)
		{
			$oldFolderInfo = $this->mail_bo->getFolderStatus($junkFolder,false,false,false);
			$response->call('egw.message',lang('empty junk'));
			$response->call('app.mail.mail_reloadNode',array($icServerID.self::$delimiter.$junkFolder=>$oldFolderInfo['shortDisplayName']));
			//error_log(__METHOD__.__LINE__.' change Profile to ->'.$rememberServerID);
			$this->changeProfile($rememberServerID);
		}
		else if ($selectedFolder == $icServerID.self::$delimiter.$junkFolder)
		{
			$response->call('egw.refresh',lang('empty junk'),'mail');
		}
	}

	/**
	 * Empty trash folder
	 *
	 * @param string $icServerID id of the server to empty its trashFolder
	 * @param string $selectedFolder seleted(active) folder by nm filter
	 * @return nothing
	 */
	function ajax_emptyTrash($icServerID, $selectedFolder)
	{
		//error_log(__METHOD__.__LINE__.' '.$icServerID);
		Api\Translation::add_app('mail');
		$response = Api\Json\Response::get();
		$rememberServerID = $this->mail_bo->profileID;
		if ($icServerID && $icServerID != $this->mail_bo->profileID)
		{
			//error_log(__METHOD__.__LINE__.' change Profile to ->'.$icServerID);
			$this->changeProfile($icServerID);
		}
		$trashFolder = $this->mail_bo->getTrashFolder();
		if(!empty($trashFolder)) {
			if ($selectedFolder == $icServerID.self::$delimiter.$trashFolder)
			{
				// Lock the tree if the active folder is Trash folder
				$response->call('app.mail.lock_tree');
			}
			$this->mail_bo->compressFolder($trashFolder);

			$heirarchyDelimeter = $this->mail_bo->getHierarchyDelimiter(true);
			$parts = explode($heirarchyDelimeter, $trashFolder);
			$fShortName = array_pop($parts);
			$fStatus = array(
				$icServerID.self::$delimiter.$trashFolder => lang($fShortName)
			);
			//Call to reset folder status counter, after emptyTrash triggered not from Trash folder
			//-as we don't have trash folder specific information available on client-side we need to deal with it on server
			$response->call('app.mail.mail_setFolderStatus',$fStatus);
		}
		if ($rememberServerID != $this->mail_bo->profileID)
		{
			$oldFolderInfo = $this->mail_bo->getFolderStatus($trashFolder,false,false,false);
			$response->call('egw.message',lang('empty trash'));
			$response->call('app.mail.mail_reloadNode',array($icServerID.self::$delimiter.$trashFolder=>$oldFolderInfo['shortDisplayName']));
			//error_log(__METHOD__.__LINE__.' change Profile to ->'.$rememberServerID);
			$this->changeProfile($rememberServerID);
		}
		else if ($selectedFolder == $icServerID.self::$delimiter.$trashFolder)
		{
			$response->call('egw.refresh',lang('empty trash'),'mail');
		}
	}

	/**
	 * compress folder - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 * fetches the current folder from session and compresses it
	 * @param string $_folderName id of the folder to compress
	 * @return nothing
	 */
	function ajax_compressFolder($_folderName)
	{
		//error_log(__METHOD__.__LINE__.' '.$_folderName);
		Api\Translation::add_app('mail');

		$this->mail_bo->restoreSessionData();
		$decodedFolderName = $this->mail_bo->decodeEntityFolderName($_folderName);
		list($icServerID,$folderName) = explode(self::$delimiter,$decodedFolderName,2);

		if (empty($folderName)) $folderName = $this->mail_bo->sessionData['mailbox'];
		if ($this->mail_bo->folderExists($folderName))
		{
			$rememberServerID = $this->mail_bo->profileID;
			if ($icServerID && $icServerID != $this->mail_bo->profileID)
			{
				//error_log(__METHOD__.__LINE__.' change Profile to ->'.$icServerID);
				$this->changeProfile($icServerID);
			}
			if(!empty($_folderName)) {
				$this->mail_bo->compressFolder($folderName);
			}
			if ($rememberServerID != $this->mail_bo->profileID)
			{
				//error_log(__METHOD__.__LINE__.' change Profile back to where we came from ->'.$rememberServerID);
				$this->changeProfile($rememberServerID);
			}
			$response = Api\Json\Response::get();
			$response->call('egw.refresh',lang('compress folder').': '.$folderName,'mail');
		}
	}

	/**
	 * sendMDN, ...
	 *
	 * @param array _messageList list of UID's
	 *
	 * @return nothing
	 */
	function ajax_sendMDN($_messageList)
	{
		if(Mail::$debug) error_log(__METHOD__."->".array2string($_messageList));
		$uidA = self::splitRowID($_messageList['msg'][0]);
		if ($uidA['profileID'] && $uidA['profileID'] != $this->mail_bo->profileID)
		{
			$this->changeProfile($uidA['profileID']);
		}
		$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
		$this->mail_bo->sendMDN($uidA['msgUID'],$folder);
	}

	/**
	 * flag messages as read, unread, flagged, ...
	 *
	 * @param string _flag name of the flag
	 * @param array _messageList list of UID's
	 * @param bool _sendJsonResponse tell fuction to send the JsonResponse
	 *
	 * @return xajax response
	 */
	function ajax_flagMessages($_flag, $_messageList, $_sendJsonResponse=true)
	{
		if(Mail::$debug) error_log(__METHOD__."->".$_flag.':'.array2string($_messageList));
		Api\Translation::add_app('mail');
		$alreadyFlagged=false;
		$flag2check='';
		$filter2toggle = $query = array();
		if ($_messageList=='all' || !empty($_messageList['msg']))
		{
			if (isset($_messageList['all']) && $_messageList['all'])
			{
				// we have both messageIds AND allFlag folder information
				$uidA = self::splitRowID($_messageList['msg'][0]);
				if ($uidA['profileID'] && $uidA['profileID'] != $this->mail_bo->profileID)
				{
					$this->changeProfile($uidA['profileID']);
				}
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
				if(!$folder && !$uidA['msg'] && $uidA['accountID'])
				{
					$folder = $uidA['accountID'];
				}
				if (isset($_messageList['activeFilters']) && $_messageList['activeFilters'])
				{
					$query = $_messageList['activeFilters'];
					if (!empty($query['search']) || !empty($query['filter'])||($query['cat_id']=='bydate' && (!empty($query['startdate'])||!empty($query['enddate']))))
					{
						//([filterName] => Schnellsuche[type] => quick[string] => ebay[status] => any
						if (is_null(Mail::$supportsORinQuery) || !isset(Mail::$supportsORinQuery[$this->mail_bo->profileID]))
						{
							Mail::$supportsORinQuery = Api\Cache::getCache(Api\Cache::INSTANCE,'email','supportsORinQuery'.trim($GLOBALS['egw_info']['user']['account_id']), null, array(), 60*60*10);
							if (!isset(Mail::$supportsORinQuery[$this->mail_bo->profileID])) Mail::$supportsORinQuery[$this->mail_bo->profileID]=true;
						}
						//error_log(__METHOD__.__LINE__.' Startdate:'.$query['startdate'].' Enddate'.$query['enddate']);
						$cutoffdate = $cutoffdate2 = null;
						if ($query['startdate']) $cutoffdate = Api\DateTime::to($query['startdate'],'ts');//SINCE, enddate
						if ($query['enddate']) $cutoffdate2 = Api\DateTime::to($query['enddate'],'ts');//BEFORE, startdate
						//error_log(__METHOD__.__LINE__.' Startdate:'.$cutoffdate2.' Enddate'.$cutoffdate);
						$filter = array(
							'filterName' => lang('subject'),
							'type' => ($query['cat_id']?$query['cat_id']:'subject'),
							'string' => $query['search'],
							'status' => 'any',//this is a status change. status will be manipulated later on
							//'range'=>"BETWEEN",'since'=> date("d-M-Y", $cutoffdate),'before'=> date("d-M-Y", $cutoffdate2)
						);
						if ($query['enddate']||$query['startdate']) {
							$filter['range'] = "BETWEEN";
							if ($cutoffdate) {
								$filter[(empty($cutoffdate2)?'date':'since')] =  date("d-M-Y", $cutoffdate);
								if (empty($cutoffdate2)) $filter['range'] = "SINCE";
							}
							if ($cutoffdate2) {
								$filter[(empty($cutoffdate)?'date':'before')] =  date("d-M-Y", $cutoffdate2);
								if (empty($cutoffdate)) $filter['range'] = "BEFORE";
							}
						}
						$filter2toggle = $filter;
					}
					else
					{
						$filter = $filter2toggle = array();
					}
					// flags read,flagged,label1,label2,label3,label4,label5 can be toggled: handle this when all mails in a folder
					// should be affected serverside. here.
					$messageList = $messageListForToggle = array();
					$flag2check = ($_flag=='read'?'seen':$_flag);
					if (in_array($_flag,array('read','flagged','label1','label2','label3','label4','label5')) &&
						!($flag2check==$query['filter'] || stripos($query['filter'],$flag2check)!==false))
					{
						$filter2toggle['status'] = array('un'.$_flag);
						if ($query['filter'] && $query['filter']!='any')
						{
							$filter2toggle['status'][] = $query['filter'];
						}
						$reverse = 1;
						$rByUid = true;
						$_sRt = $this->mail_bo->getSortedList(
							$folder,
							$sort = 0,
							$reverse,
							$filter2toggle,
							$rByUid,
							false
						);
						$messageListForToggle = $_sRt['match']->ids;
						$filter['status'] = array($_flag);
						if ($query['filter'] && $query['filter'] !='any')
						{
							$filter['status'][] = $query['filter'];
						}
						$reverse = 1;
						$rByUid = true;
						$_sR = $this->mail_bo->getSortedList(
							$folder,
							$sort = 0,
							$reverse,
							$filter,
							$rByUid,
							false
						);
						$messageList = $_sR['match']->ids;
						if (count($messageListForToggle)>0)
						{
							$flag2set = (strtolower($_flag));
							if(Mail::$debug) error_log(__METHOD__.__LINE__." toggle un$_flag -> $flag2set ".array2string($filter2toggle).array2string($messageListForToggle));
							$this->mail_bo->flagMessages($flag2set, $messageListForToggle,$folder);
						}
						if (count($messageList)>0)
						{
							$flag2set = 'un'.$_flag;
							if(Mail::$debug) error_log(__METHOD__.__LINE__." $_flag -> $flag2set ".array2string($filter).array2string($messageList));
							$this->mail_bo->flagMessages($flag2set, $messageList,$folder);
						}
						$alreadyFlagged=true;
					}
					elseif (!empty($filter) &&
						(!in_array($_flag,array('read','flagged','label1','label2','label3','label4','label5')) ||
						(in_array($_flag,array('read','flagged','label1','label2','label3','label4','label5')) &&
						($flag2check==$query['filter'] || stripos($query['filter'],$flag2check)!==false))))
					{
						if ($query['filter'] && $query['filter'] !='any')
						{
							$filter['status'] = $query['filter'];
							// since we toggle and we toggle by the filtered flag we must must change _flag
							$_flag = ($query['filter']=='unseen' && $_flag=='read' ? 'read' : ($query['filter']=='seen'&& $_flag=='read'?'unread':($_flag==$query['filter']?'un'.$_flag:$_flag)));
						}
						if(Mail::$debug) error_log(__METHOD__.__LINE__." flag all with $_flag on filter used:".array2string($filter));
						$rByUid = true;
						$reverse = 1;
						$_sR = $this->mail_bo->getSortedList(
							$folder,
							$sort=0,
							$reverse,
							$filter,
							$rByUid,
							false
						);
						$messageList = $_sR['match']->ids;
						unset($_messageList['all']);
						$_messageList['msg'] = array();
					}
					else
					{
						if(Mail::$debug) error_log(__METHOD__.__LINE__." $_flag all ".array2string($filter));
						$alreadyFlagged=true;
						$uidA = self::splitRowID($_messageList['msg'][0]);
						$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
						$this->mail_bo->flagMessages($_flag, 'all', $folder);
					}
				}
			}
			else
			{
				$uidA = self::splitRowID($_messageList['msg'][0]);
				if ($uidA['profileID'] && $uidA['profileID'] != $this->mail_bo->profileID)
				{
					$this->changeProfile($uidA['profileID']);
				}
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
			}
			if (!$alreadyFlagged)
			{
				foreach($_messageList['msg'] as $rowID)
				{
					$hA = self::splitRowID($rowID);
					$messageList[] = $hA['msgUID'];
				}
				if(Mail::$debug) error_log(__METHOD__.__LINE__." $_flag in $folder:".array2string(((isset($_messageList['all']) && $_messageList['all']) ? 'all':$messageList)));
				$this->mail_bo->flagMessages($_flag, ((isset($_messageList['all']) && $_messageList['all']) ? 'all':$messageList),$folder);
			}
		}
		else
		{
			if(Mail::$debug) error_log(__METHOD__."-> No messages selected.");
		}

		if ($_sendJsonResponse)
		{
			$flag=array(
				'label1'	=> 'important',//lang('important'),
				'label2'	=> 'job',	//lang('job'),
				'label3'	=> 'personal',//lang('personal'),
				'label4'	=> 'to do',	//lang('to do'),
				'label5'	=> 'later',	//lang('later'),
			);
			$response = Api\Json\Response::get();
			if (isset($_messageList['msg']) && $_messageList['popup'])
			{
				$response->call('egw.refresh',lang('flagged %1 messages as %2 in %3',$_messageList['msg'],lang(($flag[$_flag]?$flag[$_flag]:$_flag)),lang($folder)),'mail', $_messageList['msg'], 'update');
			}
			else if ((isset($_messageList['all']) && $_messageList['all']) || ($query['filter'] && ($flag2check==$query['filter'] || stripos($query['filter'],$flag2check)!==false)))
			{
				$response->call('egw.refresh',lang('flagged %1 messages as %2 in %3',(isset($_messageList['all']) && $_messageList['all']?lang('all'):count($_messageList['msg'])),lang(($flag[$_flag]?$flag[$_flag]:$_flag)),lang($folder)),'mail');
			}
			else
			{
				$response->call('egw.message',lang('flagged %1 messages as %2 in %3',(isset($_messageList['all']) && $_messageList['all']?lang('all'):count($_messageList['msg'])),lang(($flag[$_flag]?$flag[$_flag]:$_flag)),lang($folder)));
			}
		}
	}

	/**
	 * delete messages
	 *
	 * @param array _messageList list of UID's
	 * @param string _forceDeleteMethod - method of deletion to be enforced
	 * @return xajax response
	 */
	function ajax_deleteMessages($_messageList,$_forceDeleteMethod=null)
	{
		if(Mail::$debug) error_log(__METHOD__."->".print_r($_messageList,true).' Method:'.$_forceDeleteMethod);
		$error = null;
		$filtered =  false;
		if ($_messageList=='all' || !empty($_messageList['msg']))
		{
			if (isset($_messageList['all']) && $_messageList['all'])
			{
				// we have both messageIds AND allFlag folder information
				$uidA = self::splitRowID($_messageList['msg'][0]);
				if ($uidA['profileID'] && $uidA['profileID'] != $this->mail_bo->profileID)
				{
					$this->changeProfile($uidA['profileID']);
				}
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
				if (isset($_messageList['activeFilters']) && $_messageList['activeFilters'])
				{
					$query = $_messageList['activeFilters'];
					if (!empty($query['search']) || !empty($query['filter'])||($query['cat_id']=='bydate' && (!empty($query['startdate'])||!empty($query['enddate']))))
					{
						//([filterName] => Schnellsuche[type] => quick[string] => ebay[status] => any
						if (is_null(Mail::$supportsORinQuery) || !isset(Mail::$supportsORinQuery[$this->mail_bo->profileID]))
						{
							Mail::$supportsORinQuery = Api\Cache::getCache(Api\Cache::INSTANCE,'email','supportsORinQuery'.trim($GLOBALS['egw_info']['user']['account_id']), null, array(), 60*60*10);
							if (!isset(Mail::$supportsORinQuery[$this->mail_bo->profileID])) Mail::$supportsORinQuery[$this->mail_bo->profileID]=true;
						}
						$filtered =  true;
						$cutoffdate = $cutoffdate2 = null;
						if ($query['startdate']) $cutoffdate = Api\DateTime::to($query['startdate'],'ts');//SINCE, enddate
						if ($query['enddate']) $cutoffdate2 = Api\DateTime::to($query['enddate'],'ts');//BEFORE, startdate
						//error_log(__METHOD__.__LINE__.' Startdate:'.$cutoffdate2.' Enddate'.$cutoffdate);
						$filter = array(
							'filterName' => lang('subject'),
							'type' => ($query['cat_id']?$query['cat_id']:'subject'),
							'string' => $query['search'],
							'status' => (!empty($query['filter'])?$query['filter']:'any'),
							//'range'=>"BETWEEN",'since'=> date("d-M-Y", $cutoffdate),'before'=> date("d-M-Y", $cutoffdate2)
						);
						if ($query['enddate']||$query['startdate']) {
							$filter['range'] = "BETWEEN";
							if ($cutoffdate) {
								$filter[(empty($cutoffdate2)?'date':'since')] =  date("d-M-Y", $cutoffdate);
								if (empty($cutoffdate2)) $filter['range'] = "SINCE";
							}
							if ($cutoffdate2) {
								$filter[(empty($cutoffdate)?'date':'before')] =  date("d-M-Y", $cutoffdate2);
								if (empty($cutoffdate)) $filter['range'] = "BEFORE";
							}
						}
					}
					else
					{
						$filter = array();
					}
					//error_log(__METHOD__.__LINE__."->".print_r($filter,true).' folder:'.$folder.' Method:'.$_forceDeleteMethod);
					$reverse = 1;
					$rByUid = true;
					$_sR = $this->mail_bo->getSortedList(
						$folder,
						$sort=0,
						$reverse,
						$filter,
						$rByUid,
						false
					);
					$messageList = $_sR['match']->ids;
				}
				else
				{
					$messageList='all';
				}
				try
				{
					//error_log(__METHOD__.__LINE__."->".print_r($messageList,true).' folder:'.$folder.' Method:'.$_forceDeleteMethod);
					$this->mail_bo->deleteMessages(($messageList=='all' ? 'all':$messageList),$folder,(empty($_forceDeleteMethod)?'no':$_forceDeleteMethod));
				}
				catch (Api\Exception $e)
				{
					$error = str_replace('"',"'",$e->getMessage());
				}
			}
			else
			{
				$uidA = self::splitRowID($_messageList['msg'][0]);
				if ($uidA['profileID'] && $uidA['profileID'] != $this->mail_bo->profileID)
				{
					$this->changeProfile($uidA['profileID']);
				}
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
				foreach($_messageList['msg'] as $rowID)
				{
					$hA = self::splitRowID($rowID);
					$messageList[] = $hA['msgUID'];
				}
				try
				{
					//error_log(__METHOD__.__LINE__."->".print_r($messageList,true).' folder:'.$folder.' Method:'.$_forceDeleteMethod);
					$this->mail_bo->deleteMessages($messageList,$folder,(empty($_forceDeleteMethod)?'no':$_forceDeleteMethod));
				}
				catch (Api\Exception $e)
				{
					$error = str_replace('"',"'",$e->getMessage());
				}
			}
			$response = Api\Json\Response::get();
			if (empty($error))
			{
				$response->call('app.mail.mail_deleteMessagesShowResult',array('egw_message'=>'', 'msg'=>$_messageList['msg']));
			}
			else
			{
				$error = str_replace('\n',"\n",lang('mailserver reported:\n%1 \ndo you want to proceed by deleting the selected messages immediately (click ok)?\nif not, please try to empty your trashfolder before continuing. (click cancel)',$error));
				$response->call('app.mail.mail_retryForcedDelete',array('response'=>$error,'messageList'=>$_messageList));
			}
		}
		else
		{
			if(Mail::$debug) error_log(__METHOD__."-> No messages selected.");
		}
	}

	/**
	 * copy messages
	 *
	 * @param array _folderName target folder
	 * @param array _messageList list of UID's
	 * @param string _copyOrMove method to use copy or move allowed
	 * @param string _move2ArchiveMarker marker to indicate if a move 2 archive was triggered
	 * @param boolean _return if true the function will return the result instead of
	 * responding to client
	 *
	 * @return xajax response
	 */
	function ajax_copyMessages($_folderName, $_messageList, $_copyOrMove='copy', $_move2ArchiveMarker='_', $_return = false)
	{
		if(Mail::$debug) error_log(__METHOD__."->".$_folderName.':'.print_r($_messageList,true).' Method:'.$_copyOrMove.' ArchiveMarker:'.$_move2ArchiveMarker);
		Api\Translation::add_app('mail');
		$folderName = $this->mail_bo->decodeEntityFolderName($_folderName);
		// only copy or move are supported as method
		if (!($_copyOrMove=='copy' || $_copyOrMove=='move')) $_copyOrMove='copy';
		list($targetProfileID,$targetFolder) = explode(self::$delimiter,$folderName,2);
		// check if move2archive was called with the correct archiveFolder
		$archiveFolder = $this->mail_bo->getArchiveFolder();
		if ($_move2ArchiveMarker=='2' && $targetFolder != $archiveFolder)
		{
			error_log(__METHOD__.__LINE__."#Move to Archive called with:"."$targetProfileID,$targetFolder");
			$targetProfileID = $this->mail_bo->profileID;
			$targetFolder = $archiveFolder;
			error_log(__METHOD__.__LINE__."#Fixed ArchiveFolder:"."$targetProfileID,$targetFolder");
		}
		$lastFoldersUsedForMoveCont = Api\Cache::getCache(Api\Cache::INSTANCE,'email','lastFolderUsedForMove'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*1);
		$changeFolderActions = false;
		//error_log(__METHOD__.__LINE__."#"."$targetProfileID,$targetFolder");
		//error_log(__METHOD__.__LINE__.array2string($lastFoldersUsedForMoveCont));
		if (!isset($lastFoldersUsedForMoveCont[$targetProfileID][$targetFolder]))
		{
			//error_log(__METHOD__.__LINE__.array2string($lastFoldersUsedForMoveCont[$targetProfileID][$targetFolder]));
			if ($lastFoldersUsedForMoveCont[$targetProfileID] && count($lastFoldersUsedForMoveCont[$targetProfileID])>3)
			{
				$keys = array_keys($lastFoldersUsedForMoveCont[$targetProfileID]);
				foreach( $keys as &$f)
				{
					if (count($lastFoldersUsedForMoveCont[$targetProfileID])>9) unset($lastFoldersUsedForMoveCont[$targetProfileID][$f]);
					else break;
				}
				//error_log(__METHOD__.__LINE__.array2string($lastFoldersUsedForMoveCont[$targetProfileID]));
			}
			//error_log(__METHOD__.__LINE__."#"."$targetProfileID,$targetFolder = $_folderName");
			$lastFoldersUsedForMoveCont[$targetProfileID][$targetFolder]=$folderName;
			$changeFolderActions = true;
		}
		$filtered = false;
		if ($_messageList=='all' || !empty($_messageList['msg']))
		{
			$error=false;
			if (isset($_messageList['all']) && $_messageList['all'])
			{
				// we have both messageIds AND allFlag folder information
				$uidA = self::splitRowID($_messageList['msg'][0]);
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
				$sourceProfileID = $uidA['profileID'];
				if (isset($_messageList['activeFilters']) && $_messageList['activeFilters'])
				{
					$query = $_messageList['activeFilters'];
					if (!empty($query['search']) || !empty($query['filter'])||($query['cat_id']=='bydate' && (!empty($query['startdate'])||!empty($query['enddate']))))
					{
						//([filterName] => Schnellsuche[type] => quick[string] => ebay[status] => any
						if (is_null(Mail::$supportsORinQuery) || !isset(Mail::$supportsORinQuery[$this->mail_bo->profileID]))
						{
							Mail::$supportsORinQuery = Api\Cache::getCache(Api\Cache::INSTANCE,'email','supportsORinQuery'.trim($GLOBALS['egw_info']['user']['account_id']), null, array(), 60*60*10);
							if (!isset(Mail::$supportsORinQuery[$this->mail_bo->profileID])) Mail::$supportsORinQuery[$this->mail_bo->profileID]=true;
						}
						$filtered = true;
						$cutoffdate = $cutoffdate2 = null;
						if ($query['startdate']) $cutoffdate = Api\DateTime::to($query['startdate'],'ts');//SINCE, enddate
						if ($query['enddate']) $cutoffdate2 = Api\DateTime::to($query['enddate'],'ts');//BEFORE, startdate
						//error_log(__METHOD__.__LINE__.' Startdate:'.$cutoffdate2.' Enddate'.$cutoffdate);
						$filter = array(
							'filterName' => lang('subject'),
							'type' => ($query['cat_id']?$query['cat_id']:'subject'),
							'string' => $query['search'],
							'status' => (!empty($query['filter'])?$query['filter']:'any'),
							//'range'=>"BETWEEN",'since'=> date("d-M-Y", $cutoffdate),'before'=> date("d-M-Y", $cutoffdate2)
						);
						if ($query['enddate']||$query['startdate']) {
							$filter['range'] = "BETWEEN";
							if ($cutoffdate) {
								$filter[(empty($cutoffdate2)?'date':'since')] =  date("d-M-Y", $cutoffdate);
								if (empty($cutoffdate2)) $filter['range'] = "SINCE";
							}
							if ($cutoffdate2) {
								$filter[(empty($cutoffdate)?'date':'before')] =  date("d-M-Y", $cutoffdate2);
								if (empty($cutoffdate)) $filter['range'] = "BEFORE";
							}
						}
					}
					else
					{
						$filter = array();
					}
					$reverse = 1;
					$rByUid = true;
					$_sR = $this->mail_bo->getSortedList(
						$folder,
						$sort=0,
						$reverse,
						$filter,
						$rByUid,
						false
					);
					$messageList = $_sR['match']->ids;
					foreach($messageList as $uID)
					{
						//error_log(__METHOD__.__LINE__.$uID);
						if ($_copyOrMove=='move')
						{
							$messageListForRefresh[] = self::generateRowID($sourceProfileID, $folderName, $uID, $_prependApp=false);
						}
					}
				}
				else
				{
					$messageList='all';
				}
				try
				{
					//error_log(__METHOD__.__LINE__."->".print_r($messageList,true).' folder:'.$folder.' Method:'.$_forceDeleteMethod.' '.$targetProfileID.'/'.$sourceProfileID);
					$this->mail_bo->moveMessages($targetFolder,$messageList,($_copyOrMove=='copy'?false:true),$folder,false,$sourceProfileID,($targetProfileID!=$sourceProfileID?$targetProfileID:null));
				}
				catch (Api\Exception $e)
				{
					$error = str_replace('"',"'",$e->getMessage());
				}
			}
			else
			{
				$messageList = array();
				while(count($_messageList['msg']) > 0)
				{
					$uidA = self::splitRowID($_messageList['msg'][0]);
					$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
					$sourceProfileID = $uidA['profileID'];
					$moveList = array();
					foreach($_messageList['msg'] as $rowID)
					{
						$hA = self::splitRowID($rowID);

						// If folder changes, stop and move what we've got
						if($hA['folder'] != $folder) break;

						array_shift($_messageList['msg']);
						$messageList[] = $hA['msgUID'];
						$moveList[] = $hA['msgUID'];
						if ($_copyOrMove=='move')
						{
							$helpvar = explode(self::$delimiter,$rowID);
							array_shift($helpvar);
							$messageListForRefresh[]= implode(self::$delimiter,$helpvar);
						}
					}
					try
					{
						//error_log(__METHOD__.__LINE__."->".print_r($moveList,true).' folder:'.$folder.' Method:'.$_forceDeleteMethod.' '.$targetProfileID.'/'.$sourceProfileID);
						$this->mail_bo->moveMessages($targetFolder,$moveList,($_copyOrMove=='copy'?false:true),$folder,false,$sourceProfileID,($targetProfileID!=$sourceProfileID?$targetProfileID:null));
					}
					catch (Api\Exception $e)
					{
						$error = str_replace('"',"'",$e->getMessage());
					}
				}
			}

			$response = Api\Json\Response::get();
			if ($error)
			{
				if ($changeFolderActions == false)
				{
					unset($lastFoldersUsedForMoveCont[$targetProfileID][$targetFolder]);
					$changeFolderActions = true;
				}
				if ($_return) return $error;
				$response->call('egw.message',$error,"error");
			}
			else
			{
				if ($_copyOrMove=='copy')
				{
					$msg = lang('copied %1 message(s) from %2 to %3',($messageList=='all'||$_messageList['all']?($filtered?lang('all filtered'):lang('all')):count($messageList)),lang($folder),lang($targetFolder));
					if ($_return) return $msg;
					$response->call('egw.message',$msg);
				}
				else
				{
					$msg = lang('moved %1 message(s) from %2 to %3',($messageList=='all'||$_messageList['all']?($filtered?lang('all filtered'):lang('all')):count($messageList)),lang($folder),lang($targetFolder));
					if ($_return) return $msg;
					foreach($messageListForRefresh as $mail_id)
					{
						$response->call('egw.refresh','','mail',$mail_id, 'delete');
					}
					$response->message($msg,'success');
				}
			}
			if ($changeFolderActions == true)
			{
				//error_log(__METHOD__.__LINE__.array2string($lastFoldersUsedForMoveCont));
				Api\Cache::setCache(Api\Cache::INSTANCE,'email','lastFolderUsedForMove'.trim($GLOBALS['egw_info']['user']['account_id']),$lastFoldersUsedForMoveCont, $expiration=60*60*1);
				$actionsnew = Etemplate\Widget\Nextmatch::egw_actions(self::get_actions());
				$response->call('app.mail.mail_rebuildActionsOnList',$actionsnew);
			}
		}
		else
		{
			if(Mail::$debug) error_log(__METHOD__."-> No messages selected.");
		}
	}

	/**
	 * Autoloading function to load branches of tree node
	 * of management folder tree
	 *
	 * @param type $_id
	 */
	function ajax_folderMgmtTree_autoloading ($_id = null)
	{
		$mail_ui = new mail_ui();
		$id = $_id? $_id : $_GET['id'];
		Etemplate\Widget\Tree::send_quote_json($mail_ui->mail_tree->getTree($id,'',1,true,false,false,false));
	}

	/**
	 * Main function to handle folder management dialog
	 *
	 * @param array $content content of dialog
	 */
	function folderManagement (array $content = null)
	{
		$dtmpl = new Etemplate('mail.folder_management');
		$profileID = $_GET['acc_id']? $_GET['acc_id']: $content['acc_id'];
		$sel_options['tree'] = $this->mail_tree->getTree(null,$profileID, 1, true, false, false);

		if (!is_array($content))
		{
			$content = array ('acc_id' => $profileID);
		}

		$readonlys = array();
		// Preserv
		$preserv = array(
			'acc_id' => $content['acc_id'] // preserve acc id to be used in client-side
		);
		$dtmpl->exec('mail.mail_ui.folderManagement', $content,$sel_options,$readonlys,$preserv,2);
	}

	/**
	 * Function to delete folder for management longTask dialog
	 * it sends successfully deleted folder as response to be
	 * used in long task response handler.
	 *
	 * @param type $_folderName
	 */
	function ajax_folderMgmt_delete ($_folderName)
	{
		if ($_folderName)
		{
			$success = $this->ajax_deleteFolder($_folderName,true);
			$response = Api\Json\Response::get();
			list(,$folderName) = explode(self::$delimiter, $_folderName);
			if ($success)
			{
				$res = $folderName;
			}
			else
			{
				$res = lang("Failed to delete %1",$folderName);
			}
			$response->data($res);
		}
	}
}