<?php
/**
 * Collabeditor So Class
 *
 * @link http://www.egroupware.org
 * @package collabeditor
 * @author Hadi Nategh <hn-AT-egroupware.de>
 * @copyright (c) 2016 by Hadi Nategh <hn-AT-egroupware.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */
namespace EGroupware\Collabeditor;

use EGroupware\Api;

/**
 * Storage Object of the Collabeditor
 */
class So
{

	/**
	 * session table name
	 */
	const SESSION_TABLE = 'egw_collab_session';

	/**
	 * member table name
	 */
	const MEMBER_TABLE = 'egw_collab_member';

	/**
	 * op table name
	 */
	const OP_TABLE = 'egw_collab_op';

	/**
	 * prefix value used for naming fields in DB
	 */
	const DB_FILEDS_PREFIX = 'collab_';

	/**
	 * Exception message for no session found
	 */
	const EXCEPTION_MESSAGE_NO_SESSION = 'Session id must be given, none given!';

	/**
	 * Exception message for no OP
	 */
	const EXCEPTION_MESSAGE_NO_OPS = 'ops need to be an array of op data, none array given!';

	/**
	 * Database object
	 * @var Api\Db
	 */
	protected $db;

	public function __construct()
	{
		$this->db = $GLOBALS['egw']->db;
	}

	/**
	 * Init session check for existing session or create one there's none
	 *
	 * @param string $es_id session id
	 *
	 * @return array returns an array contains of mapped session data
	 */
	protected function initSession ($es_id)
	{
		$query = $this->db->select(self::SESSION_TABLE,'*', array('collab_es_id' => $es_id),__LINE__,__FILE__);
		$full_name = $GLOBALS['egw_info']['user']['account_fullname'];
		$color = $GLOBALS['egw_info']['user']['preferences']['filemanager']['collab_user_color'];
		$user_id = $GLOBALS['egw_info']['user']['account_id'];

		$imageUrl = $GLOBALS['egw_info']['server']['webserver_url'].'/index.php?menuaction=addressbook.addressbook_ui.photo&account_id='.$user_id;

		if (!($result = self::db2id($query->fetchRow())) && $es_id)
		{
			$result = self::db2id($this->SESSION_add2Db($es_id));
		}


		$this->MEMBER_add2Db($es_id, $user_id, $color);

		$result['member_id'] = $this->MEMBER_getLastMember();

		$this->OP_addMember($es_id, $result['member_id'], $full_name, $user_id, $color, $imageUrl);

		return $result;
	}

	/**
	 * Add session data with provided session id into DB
	 *
	 * @param string $es_id session id
	 * @param string $genesis_url generated url out of genesis temp file
	 * @return array returns an array contains of session data
	 */
	protected function SESSION_add2Db ($es_id, $genesis_url='')
	{
		if ($es_id)
		{
			$data = array (
				'collab_es_id' => $es_id,
				'collab_genesis_url' => $genesis_url,
				'collab_last_save' => self::getTimeStamp(),
				'account_id' => $GLOBALS['egw_info']['user']['account_id']
			);

			$this->db->insert(self::SESSION_TABLE, $data,false,__LINE__, __FILE__,'filemanager');
		}
		return $data;
	}

	/**
	 * Method to clean up left over modifications data
	 *
	 * @param type $es_id session id
	 *
	 * @throws Exception
	 */
	public function SESSION_cleanup ($es_id)
	{
		if (!$es_id) throw new Exception (self::EXCEPTION_MESSAGE_NO_SESSION);

		$this->db->delete(
				self::OP_TABLE,
				array('collab_es_id' => $es_id),
				__LINE__,
				__FILE__
		);
		$this->db->delete(
				self::SESSION_TABLE,
				array('collab_es_id' => $es_id),
				__LINE__,
				__FILE__
		);
		$this->db->delete(
				self::MEMBER_TABLE,
				array('collab_es_id' => $es_id),
				__LINE__,
				__FILE__
		);
	}

	/**
	 * Method to update last time saved in session table
	 *
	 * @param type $es_id
	 *
	 * @return boolean returns true if update is successful otherwise false
	 * @throws Exception
	 */
	public function SESSION_Save ($es_id)
	{
		if (!$es_id) throw new Exception (self::EXCEPTION_MESSAGE_NO_SESSION);
		$query = $this->db->update(
				self::SESSION_TABLE,
				array('collab_last_save' => self::getTimeStamp()),
				array('collab_es_id' => $es_id),
				__LINE__,
				__FILE__,
				'filemanager');
		$where_query = 'collab_es_id ="'.$es_id.'" AND collab_optype != "AddMember" AND'.
				' collab_optype != "RemoveMember" AND collab_optype !="AddCursor" AND'.
				' collab_optype !="RemoveCursor"';
		// cleanup the op table
		$this->db->delete(
				self::OP_TABLE,
				$where_query,
				__LINE__,
				__FILE__,
				'filemanager'
		);
		return !$query? false: true;
	}

	/**
	 * Get session information based on session id
	 *
	 * @param string $es_id session id
	 *
	 * @return array|boolean return session info or false if nothing found
	 * @throws Exception
	 */
	public function SESSION_Get($es_id)
	{
		if (!$es_id) throw new Exception (self::EXCEPTION_MESSAGE_NO_SESSION);
		$query = $this->db->select(
				self::SESSION_TABLE,
				'*',
				array('collab_es_id' => $es_id),
				__LINE__,
				__FILE__
		);
		$session = $query->fetchrow();
		return is_array($session)? self::db2id($session): false;
	}

	/**
	 * OP addMember function is backend equivalent to addMember from frontend
	 *
	 * @param string $es_id session id
	 * @param string $member_id
	 * @param string $full_name
	 * @param string $user_id
	 * @param string $color
	 * @param string $imageUrl
	 */
	protected function OP_addMember($es_id, $member_id, $full_name, $user_id, $color='', $imageUrl='')
	{
		$op = array(
			'optype' => 'AddMember',
			'memberid' => (string) $member_id,
			'timestamp' => self::getTimeStamp(),
			'setProperties' => array(
				'fullName' => $full_name,
				'color' => $color,
				'imageUrl' => $imageUrl,
				'uid' => $user_id,
			)
		);
		$this->OP_add2Db($op, $es_id);
	}

	/**
	 * Function to remove  cursor for a member
	 *
	 * @param string $es_id session id
	 * @param string $member_id member id
	 */
	protected function OP_removeCursor ($es_id, $member_id)
	{
		$op = array(
			'optype' => 'RemoveCursor',
			'memberid' => (string) $member_id,
			'reason' => 'server-idle',
			'timestamp' => self::getTimeStamp()
		);

		$this->OP_add2Db($op, $es_id);
	}

	/**
	 * Function to discard all changes applied to a session
	 * @param type $es_id
	 */
	public function OP_Discard ($es_id)
	{
		$this->db->update(
				self::MEMBER_TABLE,
				array('collab_status' => 0, 'collab_is_active' => 0),
				array('collab_es_id' => $es_id),
				__LINE__,
				__FILE__,
				'filemanager'
		);
		$this->db->delete(
				self::OP_TABLE,
				array('collab_es_id' => $es_id),
				__LINE__,
				__FILE__,
				'filemanager'
		);
		$this->db->delete(
				self::SESSION_TABLE,
				array('collab_es_id' => $es_id),
				__LINE__,
				__FILE__
		);
	}

	/**
	 * Function to remove member from list
	 *
	 * @param string $es_id session id
	 * @param string $member_id membe id
	 *
	 * @return boolean returns true if remove member is successful otherwise false
	 */
	function OP_removeMember ($es_id, $member_id)
	{
		$op = array (
			'optype' => 'RemoveMember',
			'memberid' => (string) $member_id,
			'timestamp' => self::getTimeStamp()
		);
		return $this->OP_add2Db($op, $es_id)?true:false;
	}

	/**
	 * Function to get top head seq for a given session
	 *
	 * @param string $es_id
	 *
	 * @return string returns head seq or '' if none exists
	 * @throws Exception throws exception if no session is given
	 */
	protected function OP_getHeadSeq($es_id)
	{
		if (!$es_id) throw new Exception (self::EXCEPTION_MESSAGE_NO_SESSION);

		$query = $this->db->select(
				self::OP_TABLE,
				'collab_seq',
				array('collab_es_id' => $es_id),
				__LINE__,
				__FILE__,
				FALSE,
				'ORDER BY collab_seq DESC LIMIT 1',
				'filemanager'
		);
		$head_seq = $query->fetchRow();
		return is_array($head_seq)? $head_seq['collab_seq']: '';
	}

	/**
	 * Function to get opsepcs of session base on seq head
	 *
	 * @param string $es_id session id
	 * @param string $seq_head sequence head
	 *
	 * @return array returns array of opspecs
	 */
	protected function OP_getOPSECS($es_id, $seq_head)
	{
		if ($seq_head == "")
		{
			$seq_head = -1;
		}
		$ops = array();
		$query = $this->db->select(
				self::OP_TABLE,
				'collab_opspec',
				'collab_es_id ="'. $es_id.'" AND collab_seq >'.$seq_head,
				__LINE__,
				__FILE__,
				false, 'ORDER BY collab_seq ASC','filemanager');

		foreach ($query as $spec)
		{
				$op = json_decode($spec['collab_opspec'], true);
				$op['memberid'] = strval($op['memberid']);
				$ops [] = $op;
		}
		return $ops;
	}

	/**
	 * Function to ops data array into op table
	 *
	 * @param string $es_id session id
	 * @param string $member_id member id
	 * @param array $client_ops array of ops
	 * @throws Exception if no array of ops given
	 */
	protected function OP_addOPS ($es_id, $member_id, $client_ops)
	{
		if (count($client_ops)<= 0) throw new Exception (self::EXCEPTION_MESSAGE_NO_OPS);

		foreach ($client_ops as $op)
		{
			$this->OP_add2Db($op, $es_id);
		}
	}

	/**
	 * Insert op data into OP table in database
	 *
	 * @param array $op op data
	 * @param string $es_id session id
	 */
	protected function OP_add2Db ($op, $es_id)
	{
		$data = array (
			'collab_es_id' => $es_id,
			'collab_member' => $op['memberid'],
			'collab_optype' => $op['optype'],
			'collab_opspec' => json_encode($op)
		);
		return $this->db->insert(self::OP_TABLE, $data,false,__LINE__, __FILE__,'filemanager');
	}

	/**
	 * Add member data into member table in database
	 *
	 * @param string $es_id session id
	 * @param int $user_id user id
	 * @param string $color user color code
	 */
	protected function MEMBER_add2Db ($es_id, $user_id, $color)
	{
		$data = array (
			'collab_es_id' => $es_id,
			'collab_uid' => $user_id,
			'collab_color' => $color,
			'collab_is_active' => 1
		);
		$this->db->insert(self::MEMBER_TABLE, $data,false,__LINE__, __FILE__,'filemanager');
	}

	/**
	 * Function to update is_active field in member table
	 *
	 * @param string $es_id session id
	 * @param string $member_id member id
	 * @param int $is_active = 0 flag to show if member is active or not
	 *
	 * @throws Exception throws exception if no es_id or member id is given
	 */
	protected function MEMBER_UpdateActiveMember ($es_id, $member_id, $is_active = 0)
	{
		if (!$es_id || !$member_id) throw new Exception (self::EXCEPTION_MESSAGE_NO_SESSION);
		$this->db->update(
				self::MEMBER_TABLE,
				array('collab_is_active' => $is_active),
				'collab_es_id ="'.$es_id.'" AND collab_member_id="'.$member_id.'"',
				__LINE__,
				__FILE__,
				'filemanager'
		);
	}

	/**
	 * Function to get active members
	 *
	 * @param string $es_id session id
	 *
	 * @return array returns array of members records
	 * @throws Exception throws exception if no es_id is given
	 */
	protected function MEMBER_getActiveMembers ($es_id)
	{
		if (!$es_id) throw new Exception (self::EXCEPTION_MESSAGE_NO_SESSION);
		$query = $this->db->select(
				self::MEMBER_TABLE,
				'*',
				array('collab_es_id' => $es_id, 'collab_is_active' => 1),
				__LINE__,
				__FILE__,
				'filemanager'
		);
		$members = $query->getRows();
		return is_array($members)?self::db2id($members):true;
	}

	/**
	 * Function to get member record of specific member id
	 *
	 * @param string $member_id member id
	 *
	 * @return string member id or null
	 * @throws Exception throws exception if no member id is given
	 */
	protected function MEMBER_getUserMemberId ($es_id, $user_id)
	{
		if (!$es_id || !$user_id) throw new Exception (self::EXCEPTION_MESSAGE_NO_SESSION);
		$query = $this->db->select(
				self::MEMBER_TABLE,
				'collab_member_id',
				array('collab_es_id' => $es_id, 'collab_uid' => $user_id),
				__LINE__,
				__FILE__
		);
		$member = $query->fetchRow();
		return is_array($member)? $member['collab_member_id']: null;
	}

	/**
	 * Function to get member record of specific member id
	 *
	 * @param string $member_id member id
	 *
	 * @return string member id or null
	 * @throws Exception throws exception if no member id is given
	 */
	protected function MEMBER_getUserLastMemberId ($es_id, $user_id)
	{
		if (!$es_id || !$user_id) throw new Exception (self::EXCEPTION_MESSAGE_NO_SESSION);
		$query = $this->db->select(
				self::MEMBER_TABLE,
				'collab_member_id',
				array('collab_es_id' => $es_id, 'collab_uid' => $user_id),
				__LINE__,
				__FILE__,
				false,
				"ORDER BY `collab_member_id` DESC LIMIT 1;"
		);
		$member = $query->fetchRow();
		return is_array($member)? $member['collab_member_id']: null;
	}

	/**
	 * Function to get member record of a session
	 *
	 * @param string $es_id session id
	 *
	 * @return array array of records or null
	 * @throws Exception throws exception if no member id is given
	 */
	protected function MEMBER_getMember ($es_id, $member_id)
	{
		if (!$es_id) throw new Exception (self::EXCEPTION_MESSAGE_NO_SESSION);
		$query = $this->db->select(
				self::MEMBER_TABLE,
				'*',
				array('collab_es_id' => $es_id, 'collab_member_id' => $member_id),
				__LINE__,
				__FILE__
		);
		$member = $query->fetchRow();
		return is_array($member)? self::db2id($member): null;
	}

	/**
	 * Utility function to map DB fields to ids
	 *
	 * @param array $data
	 * @return array|boolean returns an array contains of mapped DB data, otherwise false
	 */
	protected static function db2id($data)
	{
		if (!is_array($data)) return false;
		$keys = array_keys($data);
		array_walk($keys,function (&$key){$key = str_replace(self::DB_FILEDS_PREFIX, '', $key);});
		return array_combine ($keys, $data);
	}

	/**
	 * Return last member id from member table in db
	 *
	 * @return int returns the last member id or if it's not exist returns 0
	 */
	protected function MEMBER_getLastMember()
	{
		$query = $this->db->select(
				self::MEMBER_TABLE,
				'collab_member_id',
				'',
				__LINE__,
				__FILE__,
				false,
				"ORDER BY `collab_member_id` DESC LIMIT 1;"
		);
		$last_row = $query->fetchRow();
		return is_array($last_row)? $last_row['collab_member_id']: 0;
	}

	/**
	 * Get timestamp
	 * @return int returns current time as timestamp
	 */
	static function getTimeStamp ()
	{
		$date = new Api\DateTime();
		return $date->getTimestamp();
	}
}