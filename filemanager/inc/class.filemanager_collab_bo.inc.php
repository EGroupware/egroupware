<?php
/**
 * Filemanager collab session Class
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Hadi Nategh <hn-AT-stylite.de>
 * @copyright (c) 2016 by Stylite AG
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Storage Object of the filemanager
 */
class filemanager_collab_bo
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
		$session = $this->db->select(self::SESSION_TABLE,'*', array('collab_es_id' => $es_id));
		$full_name = $GLOBALS['egw_info']['user']['account_fullname'];
		$color = $GLOBALS['egw_info']['user']['prefs']['collab_user_color'];
		$user_id = $GLOBALS['egw_info']['user']['account_id'];


		if (!($result = self::db2id($session->fields)) && $es_id)
		{
			$result = self::db2id($this->SESSION_add2Db($es_id));
		}

		$this->MEMBER_add2Db($es_id, $user_id, $color);

		$member_id = $this->MEMBER_getLastMember();

		$this->OP_addMember($es_id, $member_id, $full_name, $user_id, $color);
	}

	/**
	 * Add session data with provided session id into DB
	 *
	 * @param string $es_id session id
	 *
	 * @return array returns an array contains of session data
	 */
	protected function SESSION_add2Db ($es_id)
	{
		if ($es_id)
		{
			$data = array (
				'collab_es_id' => $es_id,
				'collab_genesis_url' => '',
				'collab_genesis_hash' => '',
				'account_id' => $GLOBALS['egw_info']['user']['account_id']
			);

			$this->db->insert(self::SESSION_TABLE, $data,false,__LINE__, __FILE__,'filemanager');
		}
		return $data;
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
	protected function OP_addMember($es_id, $member_id, $full_name, $user_id, $color, $imageUrl)
	{
		$date = new DateTime();
		$op = array(
			'optype' => 'AddMember',
			'memberid' => (string) $member_id,
			'timestamp' => $date->getTimestamp(),
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
	 * Insert op data into OP table in database
	 *
	 * @param array $op op data
	 * @param string $es_id session id
	 */
	protected function OP_add2Db ($op, $es_id)
	{
		$data = array (
			'collab_es_id' => $es_id,
			'collab_member' => $op['member_id'],
			'collab_optype' => $op['optype'],
			'collab_opspec' => json_encode($op)
		);
		$this->db->insert(self::OP_TABLE, $data,false,__LINE__, __FILE__,'filemanager');
	}

	/**
	 * Add member data into member table in database
	 *
	 * @param type $es_id session id
	 * @param type $user_id user id
	 * @param type $color user color code
	 */
	protected function MEMBER_add2Db ($es_id, $user_id, $color)
	{
		$data = array (
			'collab_es_id' => $es_id,
			'collab_uid' => $user_id,
			'collab_color' => $color
		);
		$this->db->insert(self::MEMBER_TABLE, $data,false,__LINE__, __FILE__,'filemanager');
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
		$last_row = $this->db->select(self::MEMBER_TABLE, 'collab_member_id','',__LINE__,__FILE__,false,"ORDER BY `collab_member_id` DESC LIMIT 1;");
		return $last_row->fields? $last_row->fields['collab_member_id']: 0;
	}
}