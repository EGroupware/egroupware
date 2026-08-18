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
use EGroupware\Api\Mail\AddressList;
use EGroupware\Api\Mail\BodyDecoding;
use EGroupware\Api\Mail\CustomLabels;
use EGroupware\Api\Mail\FolderHelpers;
use EGroupware\Api\Mail\Imap\Jmap as ImapJmap;
use EGroupware\Mail\JmapShim;
use EGroupware\Mail\Ui\AttachmentHandler;
use EGroupware\Mail\Ui\AttachmentJmap;
use EGroupware\Mail\Ui\BodyHandler;
use EGroupware\Mail\Ui\FolderHandler;
use EGroupware\Mail\Ui\ImportHandler;
use EGroupware\Mail\Ui\MessageActionHandler;
use EGroupware\Mail\Ui\MessageDisplayHandler;
use EGroupware\Mail\Ui\ProfileHandler;
use EGroupware\Mail\Ui\SmimeHandler;

/**
 * Mail User Interface
 *
 * As we do NOT want to connect to previous imap server, when a profile change is triggered
 * by user ajax_changeProfile is not a static method and instanciates its own
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
		'smimeExportCert' => true,
		'smimeExportCsr' => true,
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
		''		=> 'quicksearch',	// lang('quicksearch')
		'quickwithcc'=> 'quicksearch (with cc)',	// lang('quicksearch (with cc)')
		'subject'	=> 'subject',		// lang('subject')
		'body'		=> 'message body',	// lang('message body')
		'from'		=> 'from',			// lang('from')
		'to'		=> 'to',			// lang('to')
		'cc'		=> 'cc',			// lang('cc')
		'bcc'       => 'bcc',           // lang('Bcc')
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
		''		=> 'any status',// lang('any status')
		'flagged'	=> 'flagged',	// lang('flagged')
		'unseen'	=> 'unread',	// lang('unread')
		'answered'	=> 'replied',	// lang('replied')
		'seen'		=> 'read',		// lang('read')
		'deleted'	=> 'deleted',	// lang('deleted')
	);

	/**
	 * Custom labels as searchable status options
	 *
	 * @return array<string,string>
	 */
	private static function customLabelStatusTypes(): array
	{
		$statusTypes = array();
		foreach (CustomLabels::getCustomLabels() as $id => $customLabel)
		{
			$statusTypes[$id] = $customLabel['name'];
		}
		return $statusTypes;
	}

	/**All mime types in mail-attachments
	 * that only want to be handled with server-side links if
	 * mail registers a mime-handler for them
	 * e.g. do not try to link to records for image attachments, even if records registered a mime-handler for them
	 * @var string
	 */
	static string $mimeTypesHandledOnlyByMail = '/image/i';

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

	private ?SmimeHandler $_smimeHandler = null;

	/**
	 * S/MIME certificate/key ajax-handler sub-object (gets automatically instantiated, if used)
	 */
	private function smimeHandler() : SmimeHandler
	{
		return $this->_smimeHandler ??= new SmimeHandler();
	}

	private ?ImportHandler $_importHandler = null;

	/**
	 * Message-import sub-object (gets automatically instantiated, if used)
	 */
	private function importHandler() : ImportHandler
	{
		return $this->_importHandler ??= new ImportHandler($this);
	}

	private ?MessageActionHandler $_messageActionHandler = null;

	/**
	 * Message-action (save/MDN) sub-object (gets automatically instantiated, if used)
	 */
	private function messageActionHandler() : MessageActionHandler
	{
		return $this->_messageActionHandler ??= new MessageActionHandler($this);
	}

	private ?AttachmentHandler $_attachmentHandler = null;

	/**
	 * Classic (mail_bo-coupled) attachment/body-fetch sub-object (gets automatically instantiated,
	 * if used)
	 *
	 * Package-default visibility (not private): MessageDisplayHandler::get_load_email_data() calls
	 * this cross-class to reach getdisplayableBody(), same reason get_actions() was widened earlier.
	 */
	function attachmentHandler() : AttachmentHandler
	{
		return $this->_attachmentHandler ??= new AttachmentHandler($this);
	}

	private ?MessageDisplayHandler $_messageDisplayHandler = null;

	/**
	 * Message-display (image/attachment/zip download, JMAP-native S/MIME+TNEF, classic body-render)
	 * sub-object (gets automatically instantiated, if used)
	 */
	private function messageDisplayHandler() : MessageDisplayHandler
	{
		return $this->_messageDisplayHandler ??= new MessageDisplayHandler($this);
	}

	private ?FolderHandler $_folderHandler = null;

	/**
	 * Folder ajax-handler sub-object (gets automatically instantiated, if used)
	 *
	 * Package-default visibility (not private): MessageActionHandler::setFolderStatus() reaches
	 * this cross-class, same reason attachmentHandler() was widened.
	 */
	function folderHandler() : FolderHandler
	{
		return $this->_folderHandler ??= new FolderHandler($this);
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
		(new mail_ui())->folderHandler()->treeAutoloading($_id);
	}

	/**
	 * Subscription popup window
	 *
	 * @param ?array $content
	 * @param type $msg
	 */
	function subscription(?array $content=null, $msg=null)
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
		// Initial tree's options, the rest would be loaded dynamically by autoloading,
		// triggered from client-side. Also, we keep this here as
		$sel_options['foldertree'] =  $this->mail_tree->getTree(null,$profileId,1,true,false,true);

		//Get all subscribed folders
		// as getting all subscribed folders is very fast operation
		// we can use it to get a comparison base for folders which
		// got subscribed or unsubscribed by the user
		try {
			$subscribed = array_keys($this->mail_bo->icServer->listSubscribedMailboxes('',0,true) ?: []);
		} catch (Exception $ex) {
			Framework::message($ex->getMessage());
		}

		if (!is_array($content))
		{
			$content['foldertree'] = array_map(static function($folder) use ($profileId)
			{
				return $profileId.self::$delimiter.$folder;
			}, $subscribed);
		}
		else
		{
			$button = @key($content['button']);
			unset($content[$button]);
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
					$to_unsubscribe = array_diff($subscribed, $subs=array_map(static function($id)
					{
						return explode(self::$delimiter, $id)[1];
					}, $content['foldertree']));
					$to_subscribe = array_diff($subs, $subscribed);
					// set foldertree options to basic node in order to avoid initial autoloading
					// from client side, as no options would trigger that.
					//$sel_options['foldertree'] = array('id' => '0', 'item'=> array());
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
	 * @param ?array $content
	 * @param string $msg
	 */
	function index(?array $content=null, $msg=null)
	{
		//error_log(__METHOD__.__LINE__.array2string($content));
		try	{
				if (!isset($this->mail_bo)) throw new Api\Exception\WrongUserinput(lang('Initialization of mail failed. Please use the Wizard to cope with the problem.'));
				//error_log(__METHOD__.__LINE__.function_backtrace());
				if (Mail::$debugTimes) $starttime = microtime (true);
				$this->mail_bo->restoreSessionData();
				$sessionFolder = $GLOBALS['egw_info']['user']['preferences']['mail'][$this->mail_bo->profileID.'_LastFolder'] ?? null;
				if ($sessionFolder && $this->mail_bo->folderExists($sessionFolder))
				{
					$this->mail_bo->reopen($sessionFolder); // needed to fetch full set of capabilities
				}
				else
				{
					$sessionFolder = 'INBOX';
				}
				$this->mail_bo->sessionData['mailbox'] = $sessionFolder;
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
							'filter'         => '',	// filter is used to choose the mailbox
							'lettersearch'   => false,	// I  show a lettersearch
							'searchletter'   =>	false,	// I0 active letter of the lettersearch or false for [all]
							'start'          =>	0,		// IO position in list
							'order'          =>	'date',	// IO name of the column to sort after (optional for the sortheaders)
							'sort'           =>	'DESC',	// IO direction of the sort: 'ASC' or 'DESC'
							'no_columnselection' => false,
							'extra_attributes' => ['selectedFolder'],   // I non-standard attributes send via ajax_get_rows
						);
					}
//					if (Api\Header\UserAgent::mobile())
//					{
//						$content[self::$nm_index]['header_row'] = 'mail.index.header_right';
//					}
				}

				// These must always be set, even if $content is an array
				$content[self::$nm_index]['cat_is_select'] = true;    // Category select is just a normal selectbox
				$content[self::$nm_index]['cat_id_aria_label'] = lang('Search');
				$content[self::$nm_index]['filter_aria_label'] = lang('Status');
				$content[self::$nm_index]['filter2_aria_label'] = lang('Details');
				$content[self::$nm_index]['no_filter2'] = false;       // Disable second filter
				$content[self::$nm_index]['actions'] = self::get_actions();
				$content['customLabels'] = CustomLabels::getCustomLabels();
				$content[self::$nm_index]['row_id'] = 'row_id';	     // is a concatenation of trim($GLOBALS['egw_info']['user']['account_id']):profileID:base64_encode(FOLDERNAME):uid
				$content[self::$nm_index]['placeholder_actions'] = array('composeasnew');
				// no 'get_rows' callback: rows are fetched client-side via direct JMAP access
				// (mail/js/jmap.ts's MailJmap, see egw.dataRegisterFetch('mail', ...) in this
				// app's constructor) for every account (Stalwart real-JMAP, or plain IMAP via
				// our local shim) - there is no server-side fallback path anymore.
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
					$quotainfo = ProfileHandler::quotaDisplay($quota['usage'], $quota['limit']);
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
				$sel_options[self::$nm_index]['foldertree'] = $this->mail_tree->getInitialIndexTree(null, $this->mail_bo->profileID, null, !$this->mail_bo->mailPreferences['showAllFoldersInFolderPane']);
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
					), self::customLabelStatusTypes());
				}
				else
				{
					$keywords = array_merge(
						array('keyword1','keyword2','keyword3','keyword4','keyword5'),
						array_keys(self::customLabelStatusTypes())
					);
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
					unset($this->searchTypes['']);
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
				$content[self::$nm_index]['cat_id_onchange'] = "app.mail.mail_searchtype_change";
				// set the actions on tree
				$etpl->setElementAttribute(self::$nm_index.'[foldertree]','actions', $this->get_tree_actions());

				// sending preview toolbar actions
			$etpl->setElementAttribute('toolbar', 'actions', $this->get_toolbar_actions());

				// We need to send toolbar actions to client-side because view template needs them
				if (Api\Header\UserAgent::mobile()) $sel_options['toolbar'] = $this->get_toolbar_actions();

				//we use the category "filter" option as specifier where we want to search (quick, subject, from, to, etc. ....)
				if (empty($content[self::$nm_index]['cat_id']) || empty($content[self::$nm_index]['search']))
				{
					$content[self::$nm_index]['cat_id'] = $content[self::$nm_index]['cat_id'] ?
						(!Mail::$supportsORinQuery[$this->mail_bo->profileID] &&
							($content[self::$nm_index]['cat_id'] == '' || $content[self::$nm_index]['cat_id'] == 'quickwithcc') ?
								'subject' : $content[self::$nm_index]['cat_id']) :
						(Mail::$supportsORinQuery[$this->mail_bo->profileID] ? '' : 'subject');
				}

				$content['emailTag'] = $GLOBALS['egw_info']['user']['preferences']['mail']['emailTag'] ?? 'onlyname';
				$readonlys = $preserv = array();
				if (Mail::$debugTimes) Mail::logRunTimes($starttime,null,'',__METHOD__.__LINE__);
		}
		catch (Exception $e)
		{
			// do not exit here. mail-tree should be build. if we exit here, we never get there.
			_egw_log_exception($e);
			if (isset($this->mail_bo))
			{
				if (empty($etpl))
				{
					$sel_options[self::$nm_index]['foldertree'] = $this->mail_tree->getInitialIndexTree(null, $this->mail_bo->profileID, null, !$this->mail_bo->mailPreferences['showAllFoldersInFolderPane']);
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
			case "allColumns":
				$etpl->setElementAttribute('mailSplitter', 'orientation', 'v');
				$etpl->setElementAttribute('nm', 'template', 'mail.index.rows.horizontal');
				break;
			case "expand":
			case "fixed":
				$etpl->setElementAttribute('mailSplitter', 'orientation', 'h');
				if (!Api\Header\UserAgent::mobile()) $etpl->setElementAttribute('nm', 'template', 'mail.index.rows.horizontal');
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
				'caption' => lang('mark all as read'),
				'color' => 'red',
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
		// Turn off drag folder on mobile, it can conflict with context menu on Android
		if(EGroupware\Api\Header\UserAgent::mobile())
		{
			unset($tree_actions['move']);
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
		$this->folderHandler()->folderSubscription($_acc_id, $_folderName, $_status);
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
		return $this->folderHandler()->folderTree($_nodeID, $_subscribedOnly);
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
			$id_parts = Mail::splitRowID($_items[0]['row_id']);
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
			$id_parts = Mail::splitRowID($params['row_id']);
			// Current Mailbox
			$mailbox = $id_parts['folder'];
			$messages[] = $params['row_id'];
			if ($GLOBALS['egw_info']['apps']['stylite'] && $this->mail_bo->icServer->acc_spam_api)
			{
				$params['mailbody'] = $this->messageDisplayHandler()->get_load_email_data($params['uid'], null, $mailbox);
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
		if (($account->acc_spam_api || !empty($account->getParamOverwrites()['acc_spam_api'])) && class_exists('stylite_mail_spamtitan'))
		{
			$actions['spamfilter']['children'] = array_merge($actions['spamfilter']['children'], $spam_actions=stylite_mail_spamtitan::getActions());

			// allow EGroupware admins to white- or blacklist for everyone/whole domain
			if (!empty($GLOBALS['egw_info']['apps']['admin']))
			{
				foreach($spam_actions as $id => $action)
				{
					$children = [];
					foreach($action['children'] as $child_id => $child)
					{
						$children[$child_id.'_all'] = $child;
					}
					$actions['spamfilter']['children'][$id.'_all'] = [
						'caption' => lang('%1 for all users', $action['caption']),
						'children' => $children,
					]+$action;
				}
			}
		}
		return $actions;
	}

	/**
	 * Get actions / context menu for index
	 *
	 * Changes here, require to log out, as $content[self::$nm_index] get stored in session!
	 * @return array see nextmatch_widget::egw_actions()
	 */
	function get_actions()
	{
		static $accArray=array(); // buffer identity names on single request
		// duplicated from mail_hooks
		static $deleteOptions = array(
			'move_to_trash'		=> 'move to trash',
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
							$fS['profileName'] = $accArray[$this->mail_bo->profileID] ?? null;
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
			'replies' => array(
				'caption' => 'Reply',
				'icon' => 'mail_reply',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.mail_compose',
				'allowOnMultiple' => false,
				'children' => [
					'reply' => [
						'caption' => 'Reply',
						'icon' => 'mail_reply',
						'onExecute' => 'javaScript:app.mail.mail_compose',
						'allowOnMultiple' => false,
						'toolbarDefault' => true,
					],
					'reply_all' => [
						'caption' => 'Reply All',
						'icon' => 'mail_replyall',
						'onExecute' => 'javaScript:app.mail.mail_compose',
						'allowOnMultiple' => false,
						'shortcut' => array('ctrl' => true, 'shift' => true, 'keyCode' => 65, 'caption' => KeyManager::shortcut_caption(KeyManager::A,true,true)),
						'toolbarDefault' => true,
					],
					'reply_attachments' => [
						'caption' => 'Reply With Attachments',
						'icon' => 'attach',
						'onExecute' => 'javaScript:app.mail.mail_compose',
						'allowOnMultiple' => false,
					],
				],
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
						'shortcut' => array('ctrl' => true, 'keyCode' => 70, 'caption' => KeyManager::shortcut_caption(KeyManager::F,false,true)),
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
				'shortcut' =>  array('ctrl' => true, 'keyCode' => 77, 'caption' => KeyManager::shortcut_caption(KeyManager::M,false,true)),
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
						'icon' => 'code-square',
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
								'caption' => lang('remove all'),
								'icon' => 'tag_message',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_0, true, true),
							),
							'label1' => array(
								'group' => ++$group,
								'caption' => lang('important'),
								'color' => '#ff0000',
								'icon' => 'mail_label1',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_1, true, true),
							),
							'label2' => array(
								'group' => $group,
								'caption' => lang('job'),
								'color' => '#ff8000',
								'icon' => 'mail_label2',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_2, true, true),
							),
							'label3' => array(
								'group' => $group,
								'caption' => lang('personal'),
								'color' => '#008000',
								'icon' => 'mail_label3',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_3, true, true),
							),
							'label4' => array(
								'group' => $group,
								'caption' => lang('to do'),
								'color' => '#0000ff',
								'icon' => 'mail_label4',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_4, true, true),
							),
							'label5' => array(
								'group' => $group,
								'caption' => lang('later'),
								'color' => '#8000ff',
								'icon' => 'mail_label5',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'shortcut' => KeyManager::shortcut(KeyManager::_5, true, true),
							),
						),
					),
					'flag' => array(
						'caption' => 'Flag / Unflag',
						'icon' => 'unread_flagged_small',
						'group' => ++$group,
						'children' => array(
							'flagged' => array(
								'group' => ++$group,
								'caption' => 'Flag / Unflag',
								'icon' => 'unread_flagged_small',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'hint' => 'Flag or Unflag a mail',
								'shortcut' => KeyManager::shortcut(KeyManager::F, true, true),
								'toolbarDefault' => true
							),
							'customFlag1' => array(
								'group' => ++$group,
								'caption' => 'red',
								'iconColor' => '#ff0000',
								'icon' => 'unread_flagged_small',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'enabled' => $this->mail_bo->icServer->hasCapability('SUPPORTS_KEYWORDS'),
								'hideOnDisabled' => true,
							),
							'customFlag2' => array(
								'group' => $group,
								'caption' => 'orange',
								'iconColor' => '#ff8000',
								'icon' => 'unread_flagged_small',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'enabled' => $this->mail_bo->icServer->hasCapability('SUPPORTS_KEYWORDS'),
								'hideOnDisabled' => true,
							),
							'customFlag3' => array(
								'group' => $group,
								'caption' => 'green',
								'iconColor' => '#008000',
								'icon' => 'unread_flagged_small',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'enabled' => $this->mail_bo->icServer->hasCapability('SUPPORTS_KEYWORDS'),
								'hideOnDisabled' => true,
							),
							'customFlag4' => array(
								'group' => $group,
								'caption' => 'blue',
								'iconColor' => '#0000ff',
								'icon' => 'unread_flagged_small',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'enabled' => $this->mail_bo->icServer->hasCapability('SUPPORTS_KEYWORDS'),
								'hideOnDisabled' => true,
							),
							'customFlag5' => array(
								'group' => $group,
								'caption' => 'purple',
								'iconColor' => '#8000ff',
								'icon' => 'unread_flagged_small',
								'onExecute' => 'javaScript:app.mail.mail_flag',
								'enabled' => $this->mail_bo->icServer->hasCapability('SUPPORTS_KEYWORDS'),
								'hideOnDisabled' => true,
							),
						),
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
						'caption' => lang('mark all as read'),
						'color' => 'red',
						'icon' => 'kmmsgread',
						'onExecute' => 'javaScript:app.mail.mail_flag',
						'hint' => 'mark all messages in folder as read',
						'toolbarDefault' => false
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
		foreach (CustomLabels::getCustomLabels() as $id => $customLabel)
		{
			$actions['mark']['children']['setLabel']['children'][$id] = array(
				'group' => $actions['mark']['children']['setLabel']['children']['label5']['group'],
				'caption' => $customLabel['name'],
				'no_lang' => true,
				'color' => $customLabel['color'],
				'icon' => 'tag_message',//TODO maybe allow to use the custom icon set in the category
				'onExecute' => 'javaScript:app.mail.mail_flag',
			);
		}
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
	 * function generateJmapRowID - create a unique rowID for a JMAP-sourced row (see Api\Mail::splitRowID())
	 *
	 * Unlike generateRowID(), $_folderID is a JMAP Mailbox id, NOT base64-encoded (it's already
	 * an opaque id without special characters), and $_emailID is the JMAP Email.id, NOT a
	 * numeric IMAP UID - that's exactly what lets Api\Mail::splitRowID() tell the two shapes apart.
	 *
	 * @param integer $_profileID profile ID for the rowid to be used
	 * @param string $_folderID JMAP Mailbox id
	 * @param string $_emailID JMAP Email id
	 * @param boolean $_prependApp to indicate that the app 'mail' is to be used for creating the rowID
	 * @return string - a colon separated string in the form [app:]accountID:profileID:folderID:emailID
	 */
	static function generateJmapRowID($_profileID, $_folderID, $_emailID, $_prependApp=false)
	{
		return ($_prependApp?'mail'.self::$delimiter:'').trim($GLOBALS['egw_info']['user']['account_id']).self::$delimiter.$_profileID.self::$delimiter.$_folderID.self::$delimiter.$_emailID;
	}

	/**
	 * Get actions for preview toolbar
	 *
	 * @return array
	 */
	function get_toolbar_actions()
	{
		$actions = $this->get_actions();
		$arrActions = array('composeasnew', 'replies', 'forward', 'flagged', 'delete', 'print',
			'infolog', 'tracker', 'calendar', 'save', 'view', 'read', 'label1',	'label2', 'label3',	'label4', 'label5','spam', 'ham');
		$actionsenabled = [];
		foreach( $arrActions as &$act)
		{
			//error_log(__METHOD__.__LINE__.' '.$act.'->'.array2string($actions[$act]));
			switch ($act)
			{
				case 'replies':
					// flatten reply-actions for toolbar
					foreach($actions[$act]['children'] as $name => $child)
					{
						$actionsenabled[$name]=$child+[
							'group' => $actions[$act]['group'],
						];
					}
					break;
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
					$actionsenabled[$act]= $actions['mark']['children']['flag']['children'][$act];
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
	 * display messages header lines
	 *
	 * all params are passed as GET Parameters
	 */
	function displayHeader()
	{
		if(isset($_GET['id'])) $rowID	= $_GET['id'];
		if(isset($_GET['part'])) $partID = $_GET['part'];

		$hA = Mail::splitRowID($rowID);
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
	 * Display messages
	 *
	 * @param array|null $_requesteddata etemplate content
	 * all params are passed as GET Parameters, but can be passed via ExecMethod2 as an array too
	 *
	 * @throws Api\Exception
	 * @throws Api\Exception\AssertionFailed
	 * @throws Api\Json\Exception
	 */
	/**
	 * Bootstrap the "view" popup
	 *
	 * Only resolves and validates the row-id, switches to its profile (needed for
	 * getDisplayToolbarActions()'s account/folder-derived action list), and builds the
	 * mail.display template shell - it does NOT fetch the message header/envelope/attachments
	 * itself. mail.display uses the exact same field ids as mail.index.preview (the inline
	 * preview panel's template, see mail/templates/default/index.xet), filled client-side by
	 * the same MailApp.renderMessageInto() (mail/js/app.ts): from the row already cached in the
	 * window that opened this popup, or - if that's unavailable (e.g. a bookmarked/direct link,
	 * or the opener was closed) - a fallback ajax call to ajax_fetchMessageDetails(). Message
	 * *body* loading is unaffected - still the loadEmailBody iframe below, unchanged, already
	 * shared with the preview panel and already resolving classic/local-shim/Stalwart row-ids
	 * transparently via Mail::splitRowID().
	 */
	function displayMessage(?array $_requesteddata = null)
	{
		if (is_null($_requesteddata)) $_requesteddata = $_GET;

		$rowID	= $_requesteddata['id'] ?? null;
		$partID = $_requesteddata['part'] ?? null;
		$preventRedirect   = isset($_requesteddata['mode']) && in_array($_requesteddata['mode'], ['display', 'print']);

		$hA = Mail::splitRowID($rowID);
		$uid = $hA['msgUID'];
		$mailbox = $hA['folder'];
		$icServerID = $hA['profileID'];
		$rememberServerID = $this->mail_bo->profileID;
		if ($icServerID && $icServerID != $this->mail_bo->profileID)
		{
			$this->changeProfile($icServerID);
		}
		$htmlOptions = $this->mail_bo->htmlOptions;
		if (!empty($_requesteddata['tryastext'])) $htmlOptions  = "only_if_no_text";
		if (!empty($_requesteddata['tryashtml'])) $htmlOptions  = "always_display";

		if (($this->mail_bo->isDraftFolder($mailbox)) && $_requesteddata['mode'] == 'print')
		{
			$response = Api\Json\Response::get();
			$response->call('app.mail.print_for_compose', $rowID);
		}
		if (!$preventRedirect && ($this->mail_bo->isDraftFolder($mailbox) || $this->mail_bo->isTemplateFolder($mailbox)))
		{
			Egw::redirect_link('/index.php',array('menuaction'=>'mail.mail_compose.compose','id'=>$rowID,'from'=>'composefromdraft'));
		}

		$content = [
			// Send mail ID so client JS can populate header/address/attachments and dispatch
			// actions - everything else here is chrome, not message content.
			'mail_id' => $rowID,
			'displayToolbaractions' => json_encode($this->getDisplayToolbarActions()),
			'image_proxy' => self::image_proxy(),
			'emailTag' => $GLOBALS['egw_info']['user']['preferences']['mail']['emailTag'] ?? 'onlyname',
		];
		if (!$uid || !$mailbox)
		{
			$content['msg'] = lang("ERROR: Message could not be displayed.").' '.
				lang("In Mailbox: %1, with ID: %2, and PartID: %3",$mailbox,$uid,$partID);
		}
		$linkData = array('menuaction'=>"mail.mail_ui.loadEmailBody","_messageID"=>$rowID);
		if (!empty($partID)) $linkData['_partID']=$partID;
		if ($htmlOptions != $this->mail_bo->htmlOptions) $linkData['_htmloptions']=$htmlOptions;
		$content['mailDisplayBodySrc'] = Egw::link('/index.php',$linkData);

		$this->mail_bo->closeConnection();
		if ($rememberServerID != $this->mail_bo->profileID)
		{
			$this->changeProfile($rememberServerID);
		}

		$etpl = new Etemplate('mail.display');
		// DRAG attachment actions
		$etpl->setElementAttribute('attachmentsBlock', 'actions', array(
			'file_drag' => array(
				'dragType' => 'file',
				'type' => 'drag',
				'onExecute' => 'javaScript:app.mail.drag_attachment'
			)
		));
		$etpl->exec('mail.mail_ui.displayMessage', $content, array(), array(), $content, 2);
	}

	/**
	 * This is a helper function to trigger Push method
	 * faster than normal 60 sec cycle.
	 * @todo: Once we have socket push implemented we should
	 * remove this function plus its client side companion.
	 */
	function ajax_smimeAttachmentsChecker ()
	{
		$this->smimeHandler()->ajaxAttachmentsChecker();
	}

	/**
	 * Adds certificate to relevant contact
	 * @param array $_metadata data of sender's certificate
	 */
	function ajax_smimeAddCertToContact ($_metadata)
	{
		$this->smimeHandler()->ajaxAddCertToContact($_metadata);
	}

	/**
	 * Export stored smime certificate in database
	 * @return boolean return false if not successful
	 */
	function smimeExportCert()
	{
		return $this->smimeHandler()->exportCert();
	}

	/**
	 * Export a CSR (certificate signing request) generated from the stored
	 * smime private key, so a CA can (re-)issue a certificate for it
	 *
	 * @return boolean return false if not successful
	 */
	function smimeExportCsr()
	{
		return $this->smimeHandler()->exportCsr();
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
	 * display image
	 *
	 * all params are passed as GET Parameters
	 */
	function displayImage()
	{
		$this->messageDisplayHandler()->displayImage();
	}

	function getAttachment()
	{
		$this->messageDisplayHandler()->getAttachment();
	}

	/**
	 * save messages on disk or filemanager, or display it in popup
	 *
	 * all params are passed as GET Parameters
	 */
	function saveMessage()
	{
		$this->messageActionHandler()->saveMessage();
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
		$this->attachmentHandler()->vfsOpen($attachment_id, $filename);
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
		$this->attachmentHandler()->vfsSave($params, $path, $savemode);
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
		return $this->attachmentHandler()->vfsSaveMessages($ids, $path, $savemode);
	}

	/**
	 * Zip all attachments and send to user
	 * @param string $message_id = null
	 */
	function download_zip($message_id=null)
	{
		$this->messageDisplayHandler()->download_zip($message_id);
	}

	/**
	 * Replace CID with proper type of content understandable by browser
	 *
	 * Kept as a thin wrapper - tracker's tracker_bo (a separate repo) calls this exact
	 * mail_ui::resolve_inline_image_byType() name, see mail/src/Ui/BodyHandler.php.
	 *
	 * @param string $_body content of message
	 * @param string $_mailbox mail box
	 * @param string $_uid uid
	 * @param string $_partID part id
	 * @param string $_type = 'src' type of inline image that needs to be resolved and replaced
	 *	- types: {plain|src|url|background}
	 * @param callable $_link_callback Function to generate the link to the image.  If
	 *	not provided, a default (using mail) will be used.
	 * @return string returns body content including all CID replacements
	 */
	public static function resolve_inline_image_byType ($_body,$_mailbox, $_uid, $_partID, $_type ='src', callable $_link_callback = null)
	{
		return BodyHandler::resolveInlineImageByType($_body, $_mailbox, $_uid, $_partID, $_type, $_link_callback);
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
		Api\Json\Response::get()->data($this->attachmentHandler()->saveModifiedMessageSubject($_rowID, $_subject));
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
				$messageUid = $this->importHandler()->importMessageToFolder($file,$destination,$importID);
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
		$this->importHandler()->importMessageFromVFS2DraftAndDisplay($formData, $mode);
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
		$this->messageDisplayHandler()->loadEmailBody($_messageID, $_partID, $_htmloptions);
	}

	/**
	 * ajax_setFolderStatus - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 * gets the counters and sets the text of a treenode if needed (unread Messages found)
	 * @param array $_folder folders to refresh its unseen message counters
	 * @return nothing
	 */
	function ajax_setFolderStatus($_folder, $force_change = false)
	{
		$this->folderHandler()->setFolderStatus($_folder, $force_change);
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
		$this->folderHandler()->addFolder($_parent, $_new);
	}

	/**
	 * ajax_renameFolder - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 * @param string $_folderName folder to rename and refresh
	 * @param string $_newName new foldername
	 * @return nothing
	 */
	function ajax_renameFolder($_folderName, $_newName)
	{
		$this->folderHandler()->renameFolder($_folderName, $_newName);
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
		$this->folderHandler()->reloadNode($_folderName, $_subscribedOnly);
	}


	/**
	 * ResolveWinmail fetches the encoded attachments
	 * from winmail.dat and will response expected structure back
	 * to client in order to display them.
	 *
	 * @param type $_rowid row id from nm
	 *
	 */
	function ajax_resolveWinmail ($_rowid)
	{
		$response = Api\Json\Response::get();

		$attachments = AttachmentJmap::resolveWinmailJmap($_rowid) ?? $this->attachmentHandler()->resolveAttachmentsBlock($_rowid);
		if (!empty($attachments))
		{
			$response->data($attachments);
		}
		else
		{
			$response->call('egw.message', lang('Can not resolve the winmail.dat attachment!'));
		}
	}


	/**
	 * Fetch the attachmentsBlock for a single row on demand
	 *
	 * Rows fetched via client-side JMAP (see mail/js/jmap.ts and mail/src/JmapShim.php) only
	 * carry a "does it have attachments" flag, not a resolved attachmentsBlock - building that
	 * needs a server-side download token (Link::set_data(), see createAttachmentBlock()), which
	 * isn't something the JMAP metadata alone can provide. The preview panel (app.ts's
	 * mail_preview()) calls this on demand, once, for whichever single row is being previewed.
	 *
	 * @param string $_rowid row id from nm
	 * @return void
	 */
	function ajax_fetchAttachments($_rowid)
	{
		Api\Json\Response::get()->data([
			'attachmentsBlock' => AttachmentJmap::resolveAttachmentsJmap($_rowid) ?? $this->attachmentHandler()->resolveAttachmentsBlock($_rowid),
		]);
	}

	/**
	 * Re-parse a raw From/To/Cc/Bcc header via Api\Mail::parseAddressList(), for a real JMAP
	 * server's (eg. Stalwart's) own address-list parsing to fall back to, on-demand, when its
	 * result looks broken.
	 *
	 * Real JMAP servers parse From/To/Cc/Bcc themselves - EGroupware never sees the raw header
	 * for those accounts (mail/js/jmap.ts talks to them directly from the browser, bypassing PHP
	 * entirely, see MailJmap.ensureToken()). If that server's own parser isn't RFC 2047-aware
	 * (a sending MUA's malformed encoded-word can contain a literal, unencoded comma inside a
	 * quoted display name - valid per RFC 2047, but breaks a naive comma-split), the resulting
	 * address list comes back with an entry either missing its email address, or with a valid
	 * email but a backslash/quote-mangled display name - MailJmap.email2row()'s
	 * suspectAddressFields detects exactly those shapes client-side and calls this endpoint,
	 * requesting the raw header itself (JMAP header:X, RFC 8621 4.1.3's default "Raw" form) as a
	 * one-off repair, instead of doing this for every message regardless of whether its
	 * addresses actually need it.
	 *
	 * The local IMAP shim doesn't need this at all - JmapShim::addressListFromHeader() already
	 * re-parses raw headers server-side unconditionally, for every message, since it's already
	 * making a local IMAP round-trip either way.
	 *
	 * @param string $_header raw (still RFC 2047-encoded, un-decoded) header value, eg.
	 *  'Jane Doe <jane@example.com>, "Example Corp, Consulting" <info@example.com>'
	 * @return void
	 */
	function ajax_parseAddressList($_header)
	{
		Api\Json\Response::get()->data(AttachmentJmap::parseAddressList((string)$_header));
	}

	/**
	 * Fetch a single row's full header/address/attachment detail, shaped exactly like
	 * mail_preview() / MailApp.renderMessageInto() (mail/js/app.ts) expect - the same fields
	 * email2row() (mail/js/jmap.ts) produces for list rows.
	 *
	 * Fallback for the "view" popup (mail_ui::displayMessage()) when window.opener's row cache
	 * isn't available - a bookmarked/direct link, or the opener window was closed. The normal,
	 * zero-extra-round-trip case reuses the opener's already-fetched row instead of calling this.
	 *
	 * @param string $_rowid row id from nm
	 * @return void
	 */
	function ajax_fetchMessageDetails($_rowid)
	{
		Api\Json\Response::get()->data($this->attachmentHandler()->fetchMessageDetails($_rowid));
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
		$this->folderHandler()->moveFolder($_folderName, $_target);
	}

	/**
	 * ajax_deleteFolder - its called via json, so the function must start with ajax (or the class-name must contain ajax)
	 * @param string $_folderName folder to delete
	 * @param boolean $_return = false wheter return the success value (true) or send response to client (false)
	 * @return nothing
	 */
	function ajax_deleteFolder($_folderName, $_return = false)
	{
		return $this->folderHandler()->deleteFolder($_folderName, $_return);
	}

	/**
	 * Bootstrap payload for client-side direct JMAP access
	 *
	 * @param int|null $icServerID profile / server ID, defaults to the active profile
	 * @return nothing values for keys "sessionUrl", "accountId", "access_token", "expires_in", or null
	 */
	public static function ajax_jmapBootstrap($icServerID=null)
	{
		ProfileHandler::jmapBootstrap($icServerID);
	}

	/**
	 * (Re-)enable server push for a profile, if its mail server supports it
	 *
	 * @param int|string $icServerID profile / server ID
	 * @param string|null $selectedFolder "profileID::folder/path", used to seed Stalwart's
	 *  "current folder" for its push-state diffing - defaults to INBOX if not given
	 * @return nothing
	 */
	public static function ajax_enablePush($icServerID, $selectedFolder=null)
	{
		ProfileHandler::enablePush($icServerID, $selectedFolder);
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

		$previous_id = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];

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
				$refreshData['vacationnotice'] = lang('Vacation notice is active');
				$refreshData['vacationrange'] = ($vacation['status'] == 'by_date' ? Api\DateTime::to($vacation['start_date'], true) . ($vacation['end_date'] > $vacation['start_date'] ? '->' . Api\DateTime::to($vacation['end_date'] + 24 * 3600 - 1, true) : '') : '');
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
			unset($this->searchTypes['']);
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
			), self::customLabelStatusTypes());
		}
		else
		{
			$keywords = array_merge(
				array('keyword1','keyword2','keyword3','keyword4','keyword5'),
				array_keys(self::customLabelStatusTypes())
			);
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
			// try JMAP first, so we don't pay for a classic IMAP connect+examine round-trip
			// (and its occasional flakiness) just to display quota on a JMAP-native account
			$quota = $this->jmapQuota($icServerID);
			if ($quota === null)
			{
				if ($icServerID && $icServerID != $this->mail_bo->profileID)
				{
					$this->changeProfile($icServerID);
				}
				$quota = $this->mail_bo->getQuotaRoot();
			}
		} catch (Exception $e) {
			$quota['limit'] = 'NOT SET';
			error_log(__METHOD__.__LINE__." ".$e->getMessage());
			unset($e);
		}

		if($quota !== false && $quota['limit'] != 'NOT SET') {
			$quotainfo = ProfileHandler::quotaDisplay($quota['usage'], $quota['limit']);
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
	 * Try to get quota via the JMAP Quota extension, without touching the classic IMAP
	 * connection/profile machinery at all - avoids ajax_refreshQuotaDisplay() paying for a
	 * classic IMAP connect+examine round-trip (and its occasional flakiness/latency) just to
	 * display quota on a JMAP-native (Stalwart) account.
	 *
	 * @param string|int $icServerID
	 * @return array|null with keys "usage"/"limit" in KB (same shape/units as
	 *  Api\Mail::getQuotaRoot()), or null if not applicable (not a JMAP account, server doesn't
	 *  advertise the Quota extension, or no account-level "octets" quota found) - the caller
	 *  falls back to the classic path unchanged in that case
	 */
	private function jmapQuota($icServerID) : ?array
	{
		try
		{
			$icServer = Mail::getInstance(false, $icServerID, true, false, true)->icServer;
			if (!$icServer instanceof ImapJmap)
			{
				return null;
			}
			if (($list = $icServer->jmapClient()->getQuota()) === null)
			{
				return null;   // server does not support the JMAP Quota extension
			}
			foreach ($list as $q)
			{
				if (($q['resourceType'] ?? null) === 'octets' && ($q['scope'] ?? 'account') === 'account')
				{
					return ['usage' => (int)round($q['used'] / 1024), 'limit' => (int)round($q['hardLimit'] / 1024)];
				}
			}
			return null;
		}
		catch (\Exception $e)
		{
			error_log(__METHOD__.'() failed, falling back to classic IMAP quota: '.$e->getMessage());
			return null;
		}
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
		$this->messageActionHandler()->emptySpam($icServerID, $selectedFolder);
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
		$this->messageActionHandler()->emptyTrash($icServerID, $selectedFolder);
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
		$this->messageActionHandler()->sendMDN($_messageList);
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
		$this->messageActionHandler()->flagMessages($_flag, $_messageList, $_sendJsonResponse);
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
		$this->messageActionHandler()->deleteMessages($_messageList, $_forceDeleteMethod);
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
		return $this->messageActionHandler()->copyMessages($_folderName, $_messageList, $_copyOrMove, $_move2ArchiveMarker, $_return);
	}


	/**
	 * Autoloading function to load branches of tree node
	 * of management folder tree
	 *
	 * @param type $_id
	 */
	function ajax_folderMgmtTree_autoloading ($_id = null)
	{
		(new mail_ui())->folderHandler()->folderMgmtTreeAutoloading($_id);
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
		$this->folderHandler()->folderMgmtDelete($_folderName);
	}
}
