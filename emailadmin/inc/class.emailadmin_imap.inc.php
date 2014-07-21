<?php
/**
 * EGroupware EMailAdmin: IMAP support using Horde_Imap_Client
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @author Stylite AG <info@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

require_once EGW_INCLUDE_ROOT.'/emailadmin/inc/class.defaultimap.inc.php';

/**
 * This class holds all information about the imap connection.
 * This is the base class for all other imap classes.
 *
 * Also proxies Sieve calls to emailadmin_sieve (eg. it behaves like the former felamimail bosieve),
 * to allow IMAP plugins to also manage Sieve connection.
 *
 * @property-read integer $ImapServerId acc_id of mail account (alias for acc_id)
 * @property-read boolean $enableSieve sieve enabled (alias for acc_sieve_enabled)
 * @property-read int $acc_id id
 * @property-read string $acc_name description / display name
 * @property-read string $acc_imap_host imap hostname
 * @property-read int $acc_imap_ssl 0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate
 * @property-read int $acc_imap_port imap port, default 143 or for ssl 993
 * @property-read string $acc_imap_username
 * @property-read string $acc_imap_password
 * @property-read boolean $acc_sieve_enabled sieve enabled
 * @property-read string $acc_sieve_host possible sieve hostname, default imap_host
 * @property-read int $acc_sieve_ssl 0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate
 * @property-read int $acc_sieve_port sieve port, default 4190, old non-ssl port 2000 or ssl 5190
 * @property-read string $acc_folder_sent sent folder
 * @property-read string $acc_folder_trash trash folder
 * @property-read string $acc_folder_draft draft folder
 * @property-read string $acc_folder_template template folder
 * @property-read string $acc_folder_junk junk/spam folder
 * @property-read string $acc_imap_type imap class to use, default emailadmin_imap
 * @property-read string $acc_imap_logintype how to construct login-name standard, vmailmgr, admin, uidNumber
 * @property-read string $acc_domain domain name
 * @property-read boolean $acc_imap_administration enable administration
 * @property-read string $acc_imap_admin_username
 * @property-read string $acc_imap_admin_password
 * @property-read boolean $acc_further_identities are non-admin users allowed to create further identities
 * @property-read boolean $acc_user_editable are non-admin users allowed to edit this account, if it is for them
 * @property-read array $params parameters passed to constructor (all above as array)
 */
class emailadmin_imap extends Horde_Imap_Client_Socket implements defaultimap
{
	/**
	 * Label shown in EMailAdmin
	 */
	const DESCRIPTION = 'standard IMAP server';

	/**
	 * Capabilities of this class (pipe-separated): default, sieve, admin, logintypeemail
	 */
	const CAPABILITIES = 'default|sieve';

	/**
	 * does the server with the serverID support keywords
	 * this information is filled/provided by examineMailbox
	 *
	 * @var array of boolean for each known serverID
	 */
	static $supports_keywords;

	/**
	 * is the mbstring extension available
	 *
	 * @var boolean
	 */
	protected $mbAvailable;

	/**
	 * Login type: 'uid', 'vmailmgr', 'uidNumber', 'email'
	 *
	 * @var string
	 */
	protected $imapLoginType;

	/**
	 * a debug switch
	 */
	public $debug = false;

	/**
	 * Sieve available
	 *
	 * @var boolean
	 */
	protected $enableSieve = false;

	/**
	 * True if connection is an admin connection
	 *
	 * @var boolean
	 */
	protected $isAdminConnection = false;

	/**
	 * Domain name
	 *
	 * @var string
	 */
	protected $domainName;

	/**
	 * Parameters passed to constructor from emailadmin_account
	 *
	 * @var array
	 */
	protected $params = array();

	/**
	 * Construtor
	 *
	 * @param array
	 * @param bool|int|string $_adminConnection create admin connection if true or account_id or imap username
	 * @param int $_timeout=null timeout in secs, if none given fmail pref or default of 20 is used
	 * @return void
	 */
	function __construct(array $params, $_adminConnection=false, $_timeout=null)
	{
		if (function_exists('mb_convert_encoding'))
		{
			$this->mbAvailable = true;
		}
		$this->params = $params;
		$this->isAdminConnection = $_adminConnection;
		$this->enableSieve = (boolean)$this->params['acc_sieve_enabled'];
		$this->loginType = $this->params['acc_imap_logintype'];
		$this->domainName = $this->params['acc_domain'];

		if (is_null($_timeout)) $_timeout = self::getTimeOut ();

		switch($this->params['acc_imap_ssl'] & ~emailadmin_account::SSL_VERIFY)
		{
			case emailadmin_account::SSL_STARTTLS:
				$secure = 'tls';	// Horde uses 'tls' for STARTTLS, not ssl connection with tls version >= 1 and no sslv2/3
				break;
			case emailadmin_account::SSL_SSL:
				$secure = 'ssl';
				break;
			case emailadmin_account::SSL_TLS:
				$secure = 'tlsv1';	// since Horde_Imap_Client-1.16.0 requiring Horde_Socket_Client-1.1.0
				break;
		}
		// Horde use locale for translation of error messages
		common::setlocale(LC_MESSAGES);

		// some plugins need extra measures to switch to an admin connection (eg. Dovecot constructs a special admin user name)
		$username = $_adminConnection;
		if (!is_bool($username) && is_numeric($username))
		{
			$username = $this->getMailBoxUserName($username);
		}
		if ($_adminConnection) $this->adminConnection($username);

		parent::__construct(array(
			'username' => $this->params[$_adminConnection ? 'acc_imap_admin_username' : 'acc_imap_username'],
			'password' => $this->params[$_adminConnection ? 'acc_imap_admin_password' : 'acc_imap_password'],
			'hostspec' => $this->params['acc_imap_host'],
			'port' => $this->params['acc_imap_port'],
			'secure' => $secure,
			'timeout' => $_timeout,
			//'debug_literal' => true,
			//'debug' => '/tmp/imap.log',
			'cache' => array(
				'backend' => new Horde_Imap_Client_Cache_Backend_Cache(array(
					'cacheob' => new emailadmin_horde_cache(),
				)),
			),
		));
	}

	/**
	 * Ensure we use an admin connection
	 *
	 * Plugins can overwrite it to eg. construct a special admin user name
	 *
	 * @param string $_username=null create an admin connection for given user or $this->acc_imap_username
	 */
	function adminConnection($_username=true)
	{
		if ($this->isAdminConnection !== $_username)
		{
			$this->logout();

			$this->__construct($this->params, $_username);
		}
	}

	/**
	 * Check admin credientials and connection (if supported)
	 *
	 * @param string $username=null create an admin connection for given user or $this->acc_imap_username
	 * @throws Horde_IMAP_Client_Exception
	 */
	public function checkAdminConnection($_username=true)
	{
		if ($this->acc_imap_administration)
		{
			$this->adminConnection($_username);
			$this->login();
		}
	}

	/**
	 * Allow read access to former public attributes
	 *
	 * @param type $name
	 */
	public function __get($name)
	{
		switch($name)
		{
			case 'acc_imap_administration':
				return !empty($this->params['acc_imap_admin_username']);

			case 'ImapServerId':
				return $this->params['acc_id'];

			case 'enableSieve':
				return (boolean)$this->params['acc_sieve_enabled'];

			default:
				// allow readonly access to all class attributes
				if (isset($this->$name))
				{
					return $this->$name;
				}
				if (array_key_exists($name,$this->params))
				{
					return $this->params[$name];
				}
				if ($this->getParam($name))
				{
					return $this->getParam($name);
				}
				throw new egw_exception_wrong_parameter("Tried to access unknown attribute '$name'!");
		}
	}

	/**
	 * opens a connection to a imap server
	 *
	 * @param bool $_adminConnection create admin connection if true
	 * @param int $_timeout=null timeout in secs, if none given fmail pref or default of 20 is used
	 * @deprecated allready called by constructor automatic, parameters must be passed to constructor!
	 * @return boolean|PEAR_Error true on success, PEAR_Error of false on failure
	 */
	function openConnection($_adminConnection=false, $_timeout=null)
	{
		unset($_timeout);	// not used
		if ($_adminConnection !== $this->params['adminConnection'])
		{
			throw new egw_exception_wrong_parameter('need to set parameters on calling emailadmin_account->imapServer()!');
		}
	}

	/**
	 * getTimeOut
	 *
	 * @param string _use decide if the use is for IMAP or SIEVE, by now only the default differs
	 * @return int - timeout (either set or default 20/10)
	 */
	static function getTimeOut($_use='IMAP')
	{
		$timeout = $GLOBALS['egw_info']['user']['preferences']['mail']['connectionTimeout'];
		if (empty($timeout) || !($timeout > 0)) $timeout = $_use == 'SIEVE' ? 10 : 20; // this is the default value
		return $timeout;
	}

	/**
	 * Return description for EMailAdmin
	 *
	 * @return string
	 */
	public static function description()
	{
		return static::DESCRIPTION;
	}

	/**
	 * adds a account on the imap server
	 *
	 * @param array $_hookValues
	 * @return bool true on success, false on failure
	 */
	function addAccount($_hookValues)
	{
		unset($_hookValues);	// not used
		return true;
	}

	/**
	 * updates a account on the imap server
	 *
	 * @param array $_hookValues
	 * @return bool true on success, false on failure
	 */
	function updateAccount($_hookValues)
	{
		unset($_hookValues);	// not used
		return true;
	}

	/**
	 * deletes a account on the imap server
	 *
	 * @param array $_hookValues
	 * @return bool true on success, false on failure
	 */
	function deleteAccount($_hookValues)
	{
		unset($_hookValues);	// not used
		return true;
	}

	function disconnect()
	{

	}

	/**
	 * converts a foldername from current system charset to UTF7
	 *
	 * @param string $_folderName
	 * @return string the encoded foldername
	 */
	function encodeFolderName($_folderName)
	{
		if($this->mbAvailable) {
			return mb_convert_encoding($_folderName, "UTF7-IMAP", translation::charset());
		}

		// if not
		// we can encode only from ISO 8859-1
		return imap_utf7_encode($_folderName);
	}

	/**
	 * getMailbox
	 *
	 * @param string $mailbox
	 * @return mixed mailbox object/string (string if not found by listMailboxes but existing)
	 */
	function getMailbox($mailbox)
	{
		$mailboxes = $this->listMailboxes($mailbox,Horde_Imap_Client::MBOX_ALL);
		if (empty($mailboxes)) $mailboxes = $this->listMailboxes($mailbox,Horde_Imap_Client::MBOX_UNSUBSCRIBED);
		//error_log(__METHOD__.__LINE__.'->'.$mailbox.'/'.array2string($mailboxes));
		$mboxes = new Horde_Imap_Client_Mailbox_List($mailboxes);
		//_debug_array($mboxes->count());
		foreach ($mboxes->getIterator() as $k =>$box)
		{
			//error_log(__METHOD__.__LINE__.'->'.$k);
			if ($k!='user' && $k != '' && $k==$mailbox) return $box['mailbox']; //_debug_array(array($k => $client->status($k)));
		}
		return ($this->mailboxExist($mailbox)?$mailbox:false);
	}

	/**
	 * mailboxExists
	 *
	 * @param string $mailbox
	 * @return boolean
	 */
	function mailboxExist($mailbox)
	{
		try
		{
			//error_log(__METHOD__.__LINE__.':'.$mailbox);
			$currentMailbox = $this->currentMailbox();
		}
		catch(Exception $e)
		{
			//error_log(__METHOD__.__LINE__.' failed detecting currentMailbox:'.$currentMailbox.':'.$e->getMessage());
			$currentMailbox=null;
			unset($e);
		}
		try
		{
			//error_log(__METHOD__.__LINE__.':'.$mailbox);
			$this->openMailbox($mailbox);
			$returnvalue=true;
		}
		catch(Exception $e)
		{
			//error_log(__METHOD__.__LINE__.' failed opening:'.$mailbox.':'.$e->getMessage().' Called by:'.function_backtrace());
			unset($e);
			$returnvalue=false;
		}
		if (!empty($currentMailbox) && $currentMailbox['mailbox'] != $mailbox)
		{
			try
			{
				//error_log(__METHOD__.__LINE__.':'.$currentMailbox .'<->'.$mailbox);
				$this->openMailbox($currentMailbox['mailbox']);
			}
			catch(Exception $e)
			{
				//error_log(__METHOD__.__LINE__.' failed reopening:'.$currentMailbox.':'.$e->getMessage());
				unset($e);
			}
		}
		return $returnvalue;
	}

	/**
	 * getSpecialUseFolders
	 *
	 * @return current mailbox, or if none check on INBOX, and return upon existance
	 */
	function getCurrentMailbox()
	{
		$mailbox = $this->currentMailbox();
		if (!empty($mailbox)) return $mailbox['mailbox'];
		if (empty($mailbox) && $this->mailboxExist('INBOX')) return 'INBOX';
		return null;
	}

	/**
	 * getSpecialUseFolders
	 *
	 * @return array with special use folders
	 */
	function getSpecialUseFolders()
	{
		$mailboxes = $this->getMailboxes('',0,true);
		$suF = array();
		foreach ($mailboxes as $box)
		{
			if ($box['MAILBOX']!='user' && $box['MAILBOX'] != '')
			{
				//error_log(__METHOD__.__LINE__.$k.'->'.array2string($box));
				if (isset($box['ATTRIBUTES'])&&!empty($box['ATTRIBUTES'])&&
					stripos(strtolower(array2string($box['ATTRIBUTES'])),'\noselect')=== false&&
					stripos(strtolower(array2string($box['ATTRIBUTES'])),'\nonexistent')=== false)
				{
					$suF[$box['MAILBOX']] = $box;
				}
			}
		}
		return $suF;
	}

	/**
	 * getStatus
	 *
	 * @param string $mailbox
	 * @return array with counters
	 */
	function getStatus($mailbox)
	{
		$mailboxes = $this->listMailboxes($mailbox,Horde_Imap_Client::MBOX_ALL,array(
				'attributes'=>true,
				'children'=>true, //child info
				'delimiter'=>true,
				'special_use'=>true,
			));

		$mboxes = new Horde_Imap_Client_Mailbox_List($mailboxes);
		//error_log(__METHOD__.__LINE__.array2string($mboxes->count()));
		foreach ($mboxes->getIterator() as $k =>$box)
		{
			if ($k!='user' && $k != '' && $k==$mailbox)
			{
				if (stripos(array2string($box['attributes']),'\noselect')=== false)
				{
					$status = $this->status($k);
					foreach ($status as $key => $v)
					{
						$_status[strtoupper($key)]=$v;
					}
					$_status['HIERACHY_DELIMITER'] = $_status['delimiter'] = $box['delimiter'];//$this->getDelimiter('user');
					$_status['ATTRIBUTES'] = $box['attributes'];
					//error_log(__METHOD__.__LINE__.$k.'->'.array2string($_status));
					return $_status;
				}
				else
				{
					return false;
				}
			}
		}
		return false;
	}

	/**
	 * Returns an array containing the names of the selected mailboxes
	 *
	 * @param   string  $reference          base mailbox to start the search (default is current mailbox)
	 * @param   string  $restriction_search false or 0 means return all mailboxes
	 *                                      true or 1 return only the mailbox that contains that exact name
	 *                                      2 return all mailboxes in that hierarchy level
	 * @param   string  $returnAttributes   true means return an assoc array containing mailbox names and mailbox attributes
	 *                                      false - the default - means return an array of mailboxes with only selected attributes like delimiter
	 *
	 * @return  mixed   array of mailboxes
	 */
	function getMailboxes($reference = ''  , $restriction_search = 0, $returnAttributes = false)
	{
		if ( is_bool($restriction_search) ){
			$restriction_search = (int) $restriction_search;
		}
		$mailbox = '';
		if ( is_int( $restriction_search ) ){
			switch ( $restriction_search ) {
			case 0:
				$searchstring = $reference."*";
				break;
			case 1:
				$mailbox = $searchstring = $reference;
				//$reference = '%';
				break;
			case 2:
				$searchstring = $reference."%";
				break;
			}
		}else{
			if ( is_string( $restriction_search ) ){
				$mailbox = $searchstring = $restriction_search;
			}
		}
		//error_log(__METHOD__.__LINE__.array2string($mailbox));
		//if (is_array($mailbox))error_log(__METHOD__.__LINE__.function_backtrace());
		$options = array(
				'attributes'=>true,
				'children'=>true, //child info
				'delimiter'=>true,
				'special_use'=>true,
				'sort'=>true,
			);
		if ($returnAttributes==false)
		{
			unset($options['attributes']);
			unset($options['children']);
			unset($options['special_use']);
		}
		$mailboxes = $this->listMailboxes($searchstring,Horde_Imap_Client::MBOX_ALL, $options);
		//$mboxes = new Horde_Imap_Client_Mailbox_List($mailboxes);
		//_debug_array($mboxes->count());
		foreach ((array)$mailboxes as $k =>$box)
		{
			//error_log(__METHOD__.__LINE__.' Box:'.$k.'->'.array2string($box));
			$ret[$k]=array('MAILBOX'=>$k,'ATTRIBUTES'=>$box['attributes'],'delimiter'=>$box['delimiter'],'SUBSCRIBED'=>true);
		}
		// for unknown reasons on ALL, UNSUBSCRIBED are not returned
		//always fetch unsubscribed, think about only fetching it when $options['attributes'] is set
		//but then allMailboxes are not all, ....
		//if (!empty($mailbox) && !isset($ret[$mailbox]))
		{
			$mailboxes = $this->listMailboxes($searchstring,Horde_Imap_Client::MBOX_UNSUBSCRIBED, $options);
			//$mboxes = new Horde_Imap_Client_Mailbox_List($mailboxes);
			//_debug_array($mboxes->count());
			//error_log(__METHOD__.__LINE__.' '.$mailbox.':'.count((array)$mailboxes).'->'.function_backtrace());
			foreach ((array)$mailboxes as $k =>$box)
			{
				//error_log(__METHOD__.__LINE__.' Box:'.$k.' already In?'.array_key_exists($k,$boxexists).'->'.array2string($box));
				if(!array_key_exists($k,$ret))
				{
					$ret[$k]=array('MAILBOX'=>$k,'ATTRIBUTES'=>$box['attributes'],'delimiter'=>$box['delimiter'],'SUBSCRIBED'=>false);
				}
				else
				{
					$ret[$k]['SUBSCRIBED'] = false;
				}
			}
		}
		return $ret;
	}

	/**
	 * Returns an array containing the names of the subscribed selected mailboxes
	 *
	 * @param   string  $reference          base mailbox to start the search
	 * @param   string  $restriction_search false or 0 means return all mailboxes
	 *                                      true or 1 return only the mailbox that contains that exact name
	 *                                      2 return all mailboxes in that hierarchy level
	 * @param   string  $returnAttributes   true means return an assoc array containing mailbox names and mailbox attributes
	 *                                      false - the default - means return an array of mailboxes with only selected attributes like delimiter
	 *
	 * @return  mixed   array of mailboxes
	 */
	function listSubscribedMailboxes($reference = ''  , $restriction_search = 0, $returnAttributes = false)
	{
		if ( is_bool($restriction_search) ){
			$restriction_search = (int) $restriction_search;
		}
		$mailbox = '';
		if ( is_int( $restriction_search ) ){
			switch ( $restriction_search ) {
			case 0:
				$searchstring = $reference."*";
				break;
			case 1:
				$mailbox = $searchstring = $reference;
				//$reference = '%';
				break;
			case 2:
				$searchstring = $reference."%";
				break;
			}
		}else{
			if ( is_string( $restriction_search ) ){
				$mailbox = $searchstring = $restriction_search;
			}
		}
		//error_log(__METHOD__.__LINE__.$mailbox);
		$options = array(
				'attributes'=>true,
				'children'=>true, //child info
				'delimiter'=>true,
				'special_use'=>true,
				'sort'=>true,
			);
		if ($returnAttributes==false)
		{
			unset($options['attributes']);
			unset($options['children']);
			unset($options['special_use']);
		}
		$mailboxes = $this->listMailboxes($searchstring,Horde_Imap_Client::MBOX_SUBSCRIBED_EXISTS, $options);
		//$mboxes = new Horde_Imap_Client_Mailbox_List($mailboxes);
		//_debug_array($mboxes->count());
		foreach ((array)$mailboxes as $k =>$box)
		{
			//error_log(__METHOD__.__LINE__.' Searched for:'.$mailbox.' got Box:'.$k.'->'.array2string($box).function_backtrace());
			if ($returnAttributes==false)
			{
				$ret[]=$k;
			}
			else
			{
				$ret[$k]=array('MAILBOX'=>$k,'ATTRIBUTES'=>$box['attributes'],'delimiter'=>$box['delimiter'],'SUBSCRIBED'=>true);
			}
		}
		return $ret;
	}

	/**
	 * Returns an array containing the names of the selected unsubscribed mailboxes
	 *
	 * @param   string  $reference          base mailbox to start the search
	 * @param   string  $restriction_search false or 0 means return all mailboxes
	 *                                      true or 1 return only the mailbox that contains that exact name
	 *                                      2 return all mailboxes in that hierarchy level
	 *
	 * @return  mixed   array of mailboxes
	 */
	function listUnSubscribedMailboxes($reference = ''  , $restriction_search = 0)
	{
		if ( is_bool($restriction_search) ){
			$restriction_search = (int) $restriction_search;
		}

		if ( is_int( $restriction_search ) ){
			switch ( $restriction_search ) {
			case 0:
				$mailbox = $reference."*";
				break;
			case 1:
				$mailbox = $reference;
				$reference = '%';
				break;
			case 2:
				$mailbox = "%";
				break;
			}
		}else{
			if ( is_string( $restriction_search ) ){
				$mailbox = $restriction_search;
			}
		}
		//error_log(__METHOD__.__LINE__.$mailbox);
		$options = array(
			'sort'=>true,
			//'flat'=>true,
		);
		$mailboxes = $this->listMailboxes($mailbox,Horde_Imap_Client::MBOX_SUBSCRIBED_EXISTS, $options);
		foreach ($mailboxes as $box)
		{
			//error_log(__METHOD__.__LINE__.' Box:'.$k.'->'.array2string($box['mailbox']->utf8));
			$sret[]=$box['mailbox']->utf8;
		}
		$unsubscribed = $this->listMailboxes($mailbox,Horde_Imap_Client::MBOX_UNSUBSCRIBED, $options);
		foreach ($unsubscribed as $box)
		{
			//error_log(__METHOD__.__LINE__.' Box:'.$k.'->'.array2string($box['mailbox']->utf8));
			if (!in_array($box['mailbox']->utf8,$sret) && $box['mailbox']->utf8!='INBOX') $ret[]=$box['mailbox']->utf8;
		}
		return $ret;
	}

	/**
	 * examineMailbox
	 *
	 * @param string $mailbox
	 * @return array of counters for mailbox
	 */
	function examineMailbox($mailbox)
	{
		if ($mailbox=='') return false;
		$mailboxes = $this->listMailboxes($mailbox);

		$mboxes = new Horde_Imap_Client_Mailbox_List($mailboxes);
		//_debug_array($mboxes->count());
		foreach ($mboxes->getIterator() as $k => $box)
		{
			//error_log(__METHOD__.__LINE__.array2string($box));
			unset($box);
			if ($k!='user' && $k != '' && $k==$mailbox)
			{
				$status = $this->status($k, Horde_Imap_Client::STATUS_ALL | Horde_Imap_Client::STATUS_FLAGS | Horde_Imap_Client::STATUS_PERMFLAGS);
				//error_log(__METHOD__.__LINE__.array2string($status));
				foreach ($status as $key => $v)
				{
					$_status[strtoupper($key)]=$v;
				}
				self::$supports_keywords[$this->ImapServerId] = stripos(array2string($_status['FLAGS']),'$label')!==false;
				return $_status;
			}
		}
		return false;
	}

	/**
	 * returns the supported capabilities of the imap server
	 * return false if the imap server does not support capabilities
	 *
	 * @deprecated use capability()
	 * @return array the supported capabilites
	 */
	function getCapabilities()
	{
		$cap = $this->capability();
		foreach ($cap as $c => $v)
		{
			if (is_array($v))
			{
				foreach ($v as $v)
				{
					$cap[$c.'='.$v] = true;
				}
			}
		}
		return $cap;
	}

	/**
	 * Query a single capability
	 *
	 * @deprecated use queryCapability($capability)
	 * @param string $capability
	 * @return boolean
	 */
	function hasCapability($capability)
	{
		//return $this->queryCapability($capability);
		if ($capability=='SUPPORTS_KEYWORDS')
		{
			//error_log(__METHOD__.__LINE__.' '.$capability.'->'.array2string(self::$supports_keywords));
			return self::$supports_keywords[$this->ImapServerId];
		}
		try
		{
			$cap = $this->capability();
		}
		catch (Exception $e)
		{
			if ($this->debug) error_log(__METHOD__.__LINE__.' error querying for capability:'.$capability.' ->'.$e->getMessage());
			return false;
		}
		if (!is_array($cap))
		{
			error_log(__METHOD__.__LINE__.' error querying for capability:'.$capability.' Expected array but got->'.array2string($cap));
			return false;
		}
		foreach ($cap as $c => $v)
		{
			if (is_array($v))
			{
				foreach ($v as $v)
				{
					$cap[$c.'='.$v] = true;
				}
			}
		}
		//error_log(__METHOD__.__LINE__.$capability.'->'.array2string($cap));
		if (isset($cap[$capability]) && $cap[$capability])
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * return the delimiter used by the current imap server
	 * @param mixed _type (1=personal, 2=user/other, 3=shared)
	 * @return string the delimimiter
	 */
	function getDelimiter($_type=1)
	{
		switch ($_type)
		{
			case 'user':
			case 'other':
			case 2:
				$type=2;
				break;
			case 'shared':
			case '':
			case 3:
				$type=3;
				break;
			case 'personal':
			case 1:
			default:
				$type=1;
		}
		$namespaces = $this->getNamespaces();
		foreach ($namespaces as $nsp)
		{
			if ($nsp['type']==$type) return $nsp['delimiter'];
		}
		return "/";
	}

	/**
	 * Check if IMAP server supports group ACL, can be overwritten in extending classes
	 *
	 * If group ACL is supported getMailBoxUserName and getMailBoxAccountId should be
	 * modified too, to return correct values for groups.
	 *
	 * @return boolean true if group ACL is supported, false if not
	 */
	function supportsGroupAcl()
	{
		return false;
	}

	/**
	 * get the effective Username for the Mailbox, as it is depending on the loginType
	 *
	 * @param string|int $_username account_id or account_lid
	 * @return string the effective username to be used to access the Mailbox
	 */
	function getMailBoxUserName($_username)
	{
		if (is_numeric($_username))
		{
			$_username = $GLOBALS['egw']->accounts->id2name($_username);
		}
		else
		{
			$accountID = $GLOBALS['egw']->accounts->name2id($_username);
		}
		switch ($this->loginType)
		{
			case 'email':
				$accountemail = $GLOBALS['egw']->accounts->id2name($accountID,'account_email');
				//$accountemail = $GLOBALS['egw']->accounts->read($GLOBALS['egw']->accounts->name2id($_username,'account_email'));
				if (!empty($accountemail))
				{
					list($lusername,$domain) = explode('@',$accountemail,2);
					if (strtolower($domain) == strtolower($this->domainName) && !empty($lusername))
					{
						$_username = $lusername;
					}
				}
				break;

			case 'vmailmgr':
				$_username .= '@'.$this->domainName;
				break;

			case 'uidNumber':
				$_username = 'u'.$accountID;
				break;
			default:
				if (empty($this->loginType))
				{
					// try to figure out by params['acc_imap_username']
					list($lusername,$domain) = explode('@',$this->params['acc_imap_username'],2);
					if (strpos($_username,'@')===false && !empty($domain) && !empty($lusername))
					{
						$_username = $_username.'@'.$domain;
					}
				}
		}
		return strtolower($_username);
	}

	/**
	 * Get account_id from a mailbox username
	 *
	 * @param string $_username
	 * @return int|boolean account_id of user or false if no matching user found
	 */
	function getMailBoxAccountId($_username)
	{
		switch ($this->loginType)
		{
			case 'email':
				$account_id = $GLOBALS['egw']->accounts->name2id($_username, 'account_email');
				if (!$account_id)
				{
					list($uid) = explode('@', $_username);
					$account_id = $GLOBALS['egw']->accounts->name2id($uid, 'account_lid');
				}
				break;

			case 'uidNumber':
				$account_id = (int)substr($_username, 1);
				break;

			default:
				$account_id = $GLOBALS['egw']->accounts->name2id($_username, 'account_lid');
		}
		return $account_id;
	}

	/**
	 * Create mailbox string from given mailbox-name and user-name
	 *
	 * @param string $_folderName=''
	 * @return string utf-7 encoded (done in getMailboxName)
	 */
	function getUserMailboxString($_username, $_folderName='')
	{
		$nameSpaces = $this->getNameSpaceArray();

		if(!isset($nameSpaces['others'])) {
			return false;
		}

		$_username = $this->getMailBoxUserName($_username);
		if($this->loginType == 'vmailmgr' || $this->loginType == 'email' || $this->loginType == 'uidNumber') {
			$_username .= '@'. $this->domainName;
		}

		$mailboxString = $nameSpaces['others'][0]['name'] . $_username . (!empty($_folderName) ? $nameSpaces['others'][0]['delimiter'] . $_folderName : '');

		return $mailboxString;
	}

	/**
	 * get list of namespaces
	 *	Note: a IMAPServer may present several namespaces under each key
	 * @return array with keys 'personal', 'shared' and 'others' and value array with values for keys 'name' and 'delimiter'
	 */
	function getNameSpaceArray()
	{
		static $types = array(
			Horde_Imap_Client::NS_PERSONAL => 'personal',
			Horde_Imap_Client::NS_OTHER    => 'others',
			Horde_Imap_Client::NS_SHARED   => 'shared'
		);
		//error_log(__METHOD__.__LINE__.array2string($types));
		$result = array();
		foreach($this->getNamespaces() as $data)
		{
			//error_log(__METHOD__.__LINE__.array2string($data));
			if (isset($types[$data['type']]))
			{
				$result[$types[$data['type']]][] = array(
					'type' => $types[$data['type']],
					'name' => $data['name'],
					'prefix' => $data['name'],
					'prefix_present' => !empty($data['name']),
					'delimiter' => $data['delimiter'],
				);
			}
		}
		//error_log(__METHOD__.__LINE__.array2string($result));
		return $result;
	}

	/**
	 * return the quota for the current user
	 *
	 * @param string $mailboxName
	 * @return mixed the quota for the current user -> array with all available Quota Information, or false
	 */
	function getStorageQuotaRoot($mailboxName)
	{
		$storageQuota = $this->getQuotaRoot($mailboxName);
		foreach ($storageQuota as $qInfo)
		{
			if ($qInfo['storage'])
			{
				return array('USED'=>$qInfo['storage']['usage'],'QMAX'=>$qInfo['storage']['limit']);
			}
		}
		return false;
	}

	/**
	 * return the quota for another user
	 * used by admin connections only
	 *
	 * @param string $_username
	 * @param string $_what - what to retrieve either limit/QMAX, usage/USED or ALL is supported
	 * @return int|array|boolean the quota for specified user (by what) or array with values for "limit" and "usage", or false
	 */
	function getQuotaByUser($_username, $_what='QMAX')
	{
		$mailboxName = $this->getUserMailboxString($_username);
		$storageQuota = $this->getQuotaRoot($mailboxName);
		//error_log(__METHOD__.' Username:'.$_username.' Mailbox:'.$mailboxName.' getQuotaRoot('.$_what.'):'.array2string($storageQuota));

		if (is_array($storageQuota) && isset($storageQuota[$mailboxName]) && is_array($storageQuota[$mailboxName]) &&
			isset($storageQuota[$mailboxName]['storage']) && is_array($storageQuota[$mailboxName]['storage']))
		{
			switch($_what)
			{
				case 'QMAX':
					$_what = 'limit';
					break;
				case 'USED':
					$_what = 'usage';
				case 'ALL':
					return $storageQuota[$mailboxName]['storage'];
			}
			return isset($storageQuota[$mailboxName]['storage'][$_what]) ? (int)$storageQuota[$mailboxName]['storage'][$_what] : false;
		}

		return false;
	}

	/**
	 * returns information about a user
	 *
	 * Only a stub, as admin connection requires, which is only supported for Cyrus
	 *
	 * @param string $_username
	 * @return array userdata
	 */
	function getUserData($_username)
	{
		unset($_username);	// not used
		return array();
	}

	/**
	 * set userdata
	 *
	 * @param string $_username username of the user
	 * @param int $_quota quota in bytes
	 * @return bool true on success, false on failure
	 */
	function setUserData($_username, $_quota)
	{
		unset($_username, $_quota);	// not used
		return true;
	}

	/**
	 * check if imap server supports given capability
	 *
	 * @param string $_capability the capability to check for
	 * @return bool true if capability is supported, false if not
	 */
	function supportsCapability($_capability)
	{
		return $this->hasCapability($_capability);
	}

	/**
	 * Instance of emailadmin_sieve
	 *
	 * @var emailadmin_sieve
	 */
	private $sieve;

	public $scriptName;
	public $error;

	//public $error;

	/**
	 * Proxy former felamimail bosieve methods to internal emailadmin_sieve instance
	 *
	 * @param string $name
	 * @param array $params
	 * @throws Exception
	 */
	public function __call($name,array $params=null)
	{
		if ($this->debug) error_log(__METHOD__.'->'.$name.' with params:'.array2string($params));
		switch($name)
		{
			case 'installScript':
			case 'getScript':
			case 'setActive':
			case 'setEmailNotification':
			case 'getEmailNotification':
			case 'setRules':
			case 'getRules':
			case 'retrieveRules':
			case 'getVacation':
			case 'setVacation':
				try {
					if (is_null($this->sieve))
					{
						PEAR::setErrorHandling(PEAR_ERROR_EXCEPTION);
						$this->sieve = new emailadmin_sieve($this);
						$this->error =& $this->sieve->error;
					}
					$ret = call_user_func_array(array($this->sieve,$name),$params);
				}
				catch(Exception $e) {
					throw new PEAR_Exception('Error in Sieve: '.$e->getMessage(), $e);
				}
				//error_log(__CLASS__.'->'.$name.'('.array2string($params).') returns '.array2string($ret));
				return $ret;
		}
		throw new egw_exception_wrong_parameter("No method '$name' implemented!");
	}

	/**
	 * Set vacation message for given user
	 *
	 * @param int|string $_euser nummeric account_id or imap username
	 * @param array $_vacation
	 * @param string $_scriptName=null
	 * @return boolean
	 */
	public function setVacationUser($_euser, array $_vacation, $_scriptName=null)
	{
		if ($this->debug) error_log(__METHOD__.' User:'.array2string($_euser).' Scriptname:'.array2string($_scriptName).' VacationMessage:'.array2string($_vacation));

		if (is_numeric($_euser))
		{
			$_euser = $this->getMailBoxUserName($_euser);
		}
		if (is_null($this->sieve) || $this->isAdminConnection !== $_euser)
		{
			$this->adminConnection($_euser);
			PEAR::setErrorHandling(PEAR_ERROR_EXCEPTION);
			$this->sieve = new emailadmin_sieve($this, $_euser, $_scriptName);
			$this->scriptName =& $this->sieve->scriptName;
			$this->error =& $this->sieve->error;
		}
		$ret = $this->setVacation($_vacation, $_scriptName);

		return $ret;
	}

	/**
	 * Get vacation message for given user
	 *
	 * @param int|string $_euser nummeric account_id or imap username
	 * @param string $_scriptName=null
	 * @throws Exception on connection error or authentication failure
	 * @return array
	 */
	public function getVacationUser($_euser, $_scriptName=null)
	{
		if ($this->debug) error_log(__METHOD__.' User:'.array2string($_euser));

		if (is_numeric($_euser))
		{
			$_euser = $this->getMailBoxUserName($_euser);
		}
		if (is_null($this->sieve) || $this->isAdminConnection !== $_euser)
		{
			$this->adminConnection($_euser);
			PEAR::setErrorHandling(PEAR_ERROR_EXCEPTION);
			$this->sieve = new emailadmin_sieve($this, $_euser, $_scriptName);
			$this->error =& $this->sieve->error;
			$this->scriptName =& $this->sieve->scriptName;
		}
		return $this->sieve->getVacation();
	}

	/**
	 * Return fields or tabs which should be readonly in UI for given imap implementation
	 *
	 * @return array fieldname => true pairs or 'tabs' => array(tabname => true)
	 */
	public static function getUIreadonlys()
	{
		return array();
	}
}
