<?php
/**
 * EGroupware - Filemanager Collab
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Hadi Nategh <hn-AT-stylite.de>
 * @copyright (c) 2016 by Stylite AG
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

class filemanager_collab extends filemanager_collab_bo {

	/**
	 * Methods callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'poll' => true
	);

	/**
	 * session identification for an empty new file
	 */
	const NEW_FILE_ES_ID = 'new';

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();
	}

	/**
	 * Join session, initialises edit session for opened file by user
	 *
	 * @param type $es_id session id, 'new' session id means it's an empty
	 * template opened as new file, and should not be store in DB.
	 *
	 * @return array returns an array consists of session data
	 */
	function join_session ($es_id)
	{
		if ($es_id === self::NEW_FILE_ES_ID)
		{
			$response = array(
				'member_id' => '0',
				'es_id' => self::NEW_FILE_ES_ID
			);
		}
		else
		{
			$response = $this->initSession($es_id);
		}
		$response += array (
			'id' => $GLOBALS['egw_info']['user']['account_id'],
			'full_name' => $GLOBALS['egw_info']['user']['account_fullname'],
			'success' => true
		);

		return $response;
	}

	/**
	 * This function gets called when user leaves the session
	 *
	 * @param string $es_id
	 * @param string $member_id
	 *
	 * @return array returns an array of data as response for client-side
	 */
	function leave_session ($es_id, $member_id)
	{
		if (!$this->is_sessionValid($es_id) || !$this->is_memberValid($es_id, $member_id)) throw new Exception ('Session is not valid!');
		$this->MEMBER_UpdateActiveMember($es_id, $member_id, 0);
		return array (
			'session_id' => $es_id,
			'memberid' => $member_id,
			'success' => $this->OP_removeMember($es_id, $member_id)
		);
	}

	/**
	 * Polling mechanisim to sysncronise data
	 *
	 * @throws Exception
	 */
	function poll ()
	{
		// Get POST request payload
		$payload = file_get_contents('php://input');
		$params = $payload? json_decode ($payload, true): null;
		$response = array();
		if (is_array($params))
		{
			switch ($params['command'])
			{
				case 'join_session':
					$response = $this->join_session($params['args']['es_id'],$params['args']['user_id']);
					break;
				case 'leave_session':
					if ($params['args']['es_id'] === self::NEW_FILE_ES_ID)
					{
						$response = array ('success' => true,'memberid' => '0','session_id'=>self::NEW_FILE_ES_ID);
						break;
					}
					$response = $this->leave_session($params['args']['es_id'],$params['args']['member_id']);
					break;
				case 'sync_ops':
					try
					{
						// handle new file operation
						if ($params['args']['es_id'] === self::NEW_FILE_ES_ID)
						{
							if (!$params['args']['client_ops'] && !$params['args']['seq_head'])
							{
								$response = $this->prepare_newFile();
							}
							else
							{
								$response = array(
									'result' => 'added',
									'seq_head' => 1
								);
							}
							break;
						}

						$memberid = $params['args']['member_id']? $params['args']['member_id']: '';
						$es_id = $params['args']['es_id'];
						$seq_head = (string) isset($params['args']['seq_head'])? $params['args']['seq_head']: null;
						// we need to inform clients about session changes to update themselves
						// based on that. For instance, after discarding changes other participants
						// should get notified to reload their session.
						if (!$this->is_memberValid($es_id, $memberid)) {
							$response = array (
								'result' => 'error',
								'error' => 'ENOSESSION'
							);
							throw new Exception('Session is not valid!');
						}

						if(!is_null($seq_head))
						{
							$client_ops = $params['args']['client_ops']? $params['args']['client_ops']: [];
							$current_seq_head = $this->OP_getHeadSeq($es_id);
							if ($seq_head == $current_seq_head) {

								if (count($client_ops)>0)
								{
									$newHead = $this->get_newHead($es_id, $memberid, $client_ops);
									$response = array(
										'result' => 'added',
										'head_seq' => $newHead ? $newHead : $current_seq_head
									);
								}
								else
								{
									$response = array(
										'result' => 'new_ops',
										'ops' => array(),
										'head_seq' => $current_seq_head
									);
								}
							}
							else
							{
								$response = array(
									'result' => count($client_ops)>0 ? 'conflict' : 'new_ops',
									'ops' => $this->OP_getOPSECS($es_id, $seq_head),
									'head_seq' => $current_seq_head,
								);
							}

						}
						else
						{
							throw new Exception('Invalid seq head!');
						}
					} catch (Exception $ex) {
						error_log($ex->getMessage());
					}

					break;
				default:
					//
			}
		}
		header('content-type: application/json; charset=utf-8');
		echo json_encode($response);
		exit();
	}

	/**
	 * This function prepare an op structure for new file operation
	 * as new file is not saved yet in database we need to satisfy the
	 * client in order to be able to edit an empty document.
	 *
	 * @return array return op structure
	 */
	function prepare_newFile()
	{
		$date = new Api\DateTime();
		$use_id = $GLOBALS['egw_info']['user']['account_id'];
		$response = array (
			'result' => 'new_ops',
			'ops'=> array (
				0 => array (
					'optype' => 'AddMember',
					'memberid' => '0',
					'timestamp' => $date->getTimestamp(),
					'setProperties' => array(
						'fullName' => $GLOBALS['egw_info']['user']['account_fullname'],
						'color' => $GLOBALS['egw_info']['user']['preferences']['filemanager']['collab_user_color'],
						'imageUrl' => $GLOBALS['egw_info']['server']['webserver_url'].'/index.php?menuaction=addressbook.addressbook_ui.photo&account_id='.$use_id,
						'uid' => $use_id
					)
				)
			),
			'head_seq' => '1'
		);
		return $response;
	}

	/**
	 * Function to get a new head sequence
	 *
	 * @param string $es_id session id
	 * @param string $member_id member id
	 * @param array $client_ops opspec from client side
	 *
	 * @return string return a seq head number
	 */
	function get_newHead ($es_id, $member_id, $client_ops)
	{
		$this->OP_addOPS($es_id, $member_id, $client_ops);

		return $this->OP_getHeadSeq($es_id);
	}

	/**
	 * Ajax function to handle actions called by client-side
	 * types: save, delete, discard
	 *
	 * @param array $params
	 * @param string $action
	 */
	function ajax_actions ($params, $action)
	{
		$response = Api\Json\Response::get();
		switch ($action)
		{
			case 'save':
				$this->SESSION_Save($params['es_id']);
				//update genesis file after save happened
				if ($params['file_path']) self::generateGenesis ($params['file_path'], $params['es_id']);
				break;
			case 'delete':
				$this->SESSION_cleanup($params['es_id']);
				break;
			case 'discard':
				$this->OP_Discard($params['es_id']);
				break;
			case 'checkLastMember':
				$activeMembers = $this->MEMBER_getActiveMembers($params['es_id']);
				$response->data(is_array($activeMembers) && count($activeMembers) > 1?false:true);
				break;
			default:
				//
		}
	}


	/**
	 * Check if the collaboration is allowed for given file path
	 *
	 * @param string $file_path file path
	 * @param int $_right VFS file access right
	 *
	 * @return boolean returns true if allowed
	 */
	function is_collabAllowed ($file_path, $_right=null)
	{
		$paths = explode('/webdav.php', $file_path);
		$right = $_right ? $_right : Api\Vfs::WRITABLE;
		$allowed =	Api\Vfs::check_access($paths[1], $right) &&
					!preg_match('/\/api\/js\/webodf\/template.odf$/', $file_path);
		return $allowed;
	}

	/**
	 * Check if session is valid
	 *
	 * @param type $es_id
	 *
	 * @return boolean return true if session is valid otherwise false
	 */
	function is_sessionValid ($es_id)
	{
		$session = $this->SESSION_Get($es_id);
		return $session? true : false;
	}

	/**
	 * Check if the member id of the session has valid status
	 * status: 1 is valid 0 is invalid
	 *
	 * @param type $es_id
	 * @param type $member_id
	 * @return boolean returns true if it's valid
	 */
	function is_memberValid ($es_id, $member_id)
	{
		$member = $this->MEMBER_getMember($es_id, $member_id);
		return $member && $member['status'] != 0 ? true: false;
	}

	/**
	 * Function to get genesis url by generating a temp genesis temp file
	 * out of given path, and returnig es_id md5 hash and genesis url to
	 * client.
	 *
	 * @param type $file_path file path
	 * @param boolean $_isNew true means this is an empty doc opened as new file
	 * in client-side and not stored yet therefore no genesis file should get generated for it.
	 */
	function ajax_getGenesisUrl ($file_path, $_isNew)
	{
		$result = array();
		$es_id = md5($file_path);
		$response = Api\Json\Response::get();
		// handle new empty file
		if ($_isNew)
		{
			$response->data(array (
				'es_id' => self::NEW_FILE_ES_ID,
				'genesis_url' => $GLOBALS['egw_info']['server']['webserver_url'].'/api/js/webodf/template.odt'
			));
			return;
		}
		$session = $this->SESSION_Get($es_id);

		if ($session && $session['genesis_url'] !== '')
		{
			$gen_file = explode('/webdav.php',$session['genesis_url']);
			if (!Api\Vfs::file_exists($gen_file[1])) self::generateGenesis ($file_path, $es_id);
			$result = array (
				'es_id' => $session['es_id'],
				'genesis_url' => $session['genesis_url']
			);
		}
		else if ($this->is_collabAllowed($file_path, Api\Vfs::WRITABLE))
		{
			$result = array (
				'es_id' => $es_id,
				'genesis_url' => self::generateGenesis($file_path, $es_id)
			);
			$this->SESSION_add2Db($es_id, $result['genesis_url']);
		}
		$response->data($result);
	}

	/**
	 * Generate a genesis file out of given file path and session id
	 *
	 * @param type $file_path file path in webdav format example: egroupware/webdav.php/home/sysop/test.odt
	 * @param type $es_id session id
	 *
	 * @return string returns genesis url
	 */
	static function generateGenesis ($file_path, $es_id)
	{
		$paths = explode('/webdav.php', $file_path);
		$dir_parts = explode('/',$paths[1]);
		array_pop($dir_parts);
		$dir = join('/', $dir_parts);
		$genesis_file = $dir.'/.'.$es_id.'.webodf.odt';
		$genesis_url = $paths[0].'/webdav.php'.$genesis_file;
		Api\Vfs::copy($paths[1], $genesis_file);
		return $genesis_url;
	}
}