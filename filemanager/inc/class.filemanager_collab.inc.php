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
	 * @param type $es_id session id
	 * @return array returns an array consists of session data
	 */
	function join_session ($es_id)
	{
		$response = $this->initSession($es_id);
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
					$response = $this->leave_session($params['args']['es_id'],$params['args']['member_id']);
					break;
				case 'sync_ops':
					try
					{
						$memberid = $params['args']['member_id']? $params['args']['member_id']: '';
						$es_id = $params['args']['es_id'];
						$seq_head = (string) isset($params['args']['seq_head'])? $params['args']['seq_head']: null;
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
	 *
	 * @param type $es_id
	 * @param type $action
	 */
	function ajax_actions ($es_id, $action)
	{
		switch ($action)
		{
			case 'save':
				$this->SESSION_Save($es_id);
				break;
		}
	}

	
	function is_collabAllowed ($file_path, $_right)
	{
		$paths = explode('/webdav.php', $file_path);
		$right = $_right ? $_right : Api\Vfs::WRITABLE;
		$allowed =	Api\Vfs::check_access($paths[1], $right) &&
					!preg_match('/\/api\/js\/webodf\/template.odf$/', $file_path);
		return $allowed;
	}

	function ajax_getGenesisUrl ($file_path)
	{
		$result = array();
		$es_id = md5($file_path);

		$paths = explode('/webdav.php', $file_path);
		$dir_parts = explode('/',$paths[1]);
		array_pop($dir_parts);
		$dir = join('/', $dir_parts);
		$response = Api\Json\Response::get();
		$session = $this->SESSION_Get($es_id);

		if ($session && $session['genesis_url'] !== '')
		{
			$result = array (
				'es_id' => $session['es_id'],
				'genesis_url' => $session['genesis_url']
			);
		}
		else if ($this->is_collabAllowed($file_path))
		{
			$genesis_file = $dir.'/.'.$es_id.'.webodf.odt';
			$genesis_url = $paths[0].'/webdav.php'.$genesis_file;
			$result = array (
				'es_id' => $es_id,
				'genesis_url' => $genesis_url
			);
			Api\Vfs::copy($paths[1], $genesis_file);
			$this->SESSION_add2Db($es_id, $genesis_url);
		}
		$response->data($result);
	}
}