<?php
/**
 * EGroupware - Mail - interface class for identities and accounts
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Stylite AG [info@stylite.de]
 * @copyright (c) 2013 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */
include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate.inc.php');

class mail_uipreferences
{
	var $public_functions = array
	(
		'index' => True,
		'edit' => True,
	);

	/**
	 * nextMatch name for index
	 *
	 * @var string
	 */
	static $nm_index = 'acc';

	/**
	 * Reference to felamimail_bo
	 *
	 * @var felamimail_bo
	 */
	var $mail_bo;


	/**
	 * Constructor
	 *
	 */
	function __construct($_signatureID = NULL)
	{
		$this->accountID = $GLOBALS['egw_info']['user']['account_id'];
		$this->mail_bo	= mail_bo::getInstance(true,$icServerID);
		$this->bopreferences	= $this->mail_bo->bopreferences;


	}

	/**
	 * Main signature list page
	 *
	 * @param array $content=null
	 * @param string $msg=null
	 */
	function index(array $content=null,$msg=null)
	{
		//Instantiate an etemplate_new object
		$tmpl = new etemplate_new('mail.profiles.index');
		if (!is_array($content))
		{
			$content['acc']= $this->get_rows($rows,$readonlys);

			// Set content-menu actions
			$tmpl->set_cell_attribute('acc', 'actions',$this->get_actions());

			$sel_options = array(
				'status' => array(
					'ENABLED' => lang('Enabled'),
					'DISABLED' => lang('Disabled'),
				)
			);
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
		$tmpl->exec('mail.mail_uipreferences.index',$content,$sel_options,$readonlys);

	}

	/**
	 * Get actions / context menu for index
	 *
	 * Changes here, require to log out, as $content[self::$nm_index] get stored in session!
	 * @var &$action_links
	 *
	 * @return array see nextmatch_widget::egw_actions()
	 */
	private function get_actions(array &$action_links=array())
	{
		$actions =  array(
			'open' => array(
				'caption' => lang('Open'),
				'icon' => 'view',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.profile_open',
				'allowOnMultiple' => false,
				'default' => true,
			),
			'delete' => array(
				'caption' => lang('delete'),
				'icon' => 'delete',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.profile_delete',
				'allowOnMultiple' => false,
			),
		);
		return $actions;
	}

	/**
	 * Callback to fetch the rows for the nextmatch widget
	 *
	 * @param array $query
	 * @param array &$rows
	 * @param array &$readonlys
	 */
	function get_rows($query,&$rows)
	{
		if (!isset($this->bopreferences)) $this->bopreferences  = CreateObject('mail_bopreferences');
		$preferences =& $this->bopreferences->getPreferences();
		$allAccountData    = $this->bopreferences->getAllAccountData($preferences);
		if ($allAccountData) {
			foreach ($allAccountData as $tmpkey => $accountData)
			{
				$identity =& $accountData['identity'];

				#_debug_array($identity);

				foreach($identity as $key => $value) {
					if(is_object($value) || is_array($value)) {
						continue;
					}
					switch($key) {
						default:
							$tempvar[$key] = $value;
					}
				}
				$tempvar['id_key']=$tmpkey;
				$accountArray[]=$tempvar;
			}
		}
		$rows =$accountArray;//we are fetching the rows here
		foreach ($rows as $i => &$row)
		{
			$row['row_id']='account::'.($row['fm_accountid']?$row['fm_accountid']:$this->accountID).'::'.$row['id'];
			$row['description'] = mail_bo::generateIdentityString($allAccountData[$row['id_key']]['identity']);
			$row['default'] = ($row['default']?'Default':'');
		}
		array_unshift($rows,array(''=> ''));
		return $rows;
	}

	/**
	 * edit account/identity
	 *
	 * @param array $content=null
	 * @param string $msg=null
	 */
	function edit(array $content=null,$msg=null)
	{

	}

	/**
	 * delete personalMailProfile
	 *
	 * @param array account/identity list of UID's
	 * @return xajax response
	 */
	function ajax_deleteMailProfile($_profile)
	{
		error_log(__METHOD__.__LINE__.$_profile);
		$splitID = explode('::',$_profile);
	
		//$this->index(null,lang('deleted profile %1',$_profile));
	}
}
?>
