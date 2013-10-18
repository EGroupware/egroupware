<?php
/**
 * EGroupware - Mail - interface class for compose mails in popup
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Klaus Leithoff [kl@stylite.de]
 * @copyright (c) 2013 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */
include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate.inc.php');

class mail_signatures
{
	var $public_functions = array
	(
		'index' => True,
	);

	/**
	 * nextMatch name for index
	 *
	 * @var string
	 */
	static $nm_index = 'sig';

	var $tableName = 'egw_felamimail_signatures';

	var $fm_signatureid = NULL;

	var $fm_description = NULL;

	var $fm_signature = NULL;

	var $fm_defaultsignature = NULL;

	var $boemailadmin;
	var $profileData;

	/**
	 * Constructor
	 *
	 */
	function __construct($_signatureID = NULL)
	{
		$this->accountID = $GLOBALS['egw_info']['user']['account_id'];

		if($_signatureID !== NULL) {
			$this->read($_signatureID);
		}
		$this->boemailadmin = new emailadmin_bo();
		$this->profileData = $this->boemailadmin->getUserProfile('felamimail');
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
		$tmpl = new etemplate_new('mail.signatures.index');
		if (!is_array($content))
		{
			$content['sig']= $this->get_rows($rows,$readonlys);

			// Set content-menu actions
			$tmpl->set_cell_attribute('sig', 'actions',$this->get_actions());

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
		$tmpl->exec('mail.mail_signatures.index',$content,$sel_options,$readonlys);

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
				'onExecute' => 'javaScript:app.mail.mail_open',
				'allowOnMultiple' => false,
				'default' => true,
			),
			'delete' => array(
				'caption' => lang('delete'),
				'icon' => 'delete',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.mail_open',
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
		$rows = $this->getListOfSignatures();
		foreach ($rows as $i => &$row)
		{
			$row['row_id']='signature::'.($row['fm_accountid']?$row['fm_accountid']:$this->accountID).'::'.$row['fm_signatureid'];
			$row['fm_defaultsignature'] = ($row['fm_defaultsignature']?'Default':'');
		}
		array_unshift($rows,array(''=> ''));
//_debug_array($rows);
		return $rows;
	}

	function getDefaultSignature() {
		$db = clone($GLOBALS['egw']->db);
		$db->set_app('felamimail');

		$where = array(
			'fm_accountid'		=> $this->accountID,
			'fm_defaultsignature'	=> true
		);

		$db->select($this->tableName,'fm_signatureid,fm_description,fm_signature,fm_defaultsignature',
			$where, __LINE__, __FILE__);

		if(($row = $db->row(true))) {
			return $row['fm_signatureid'];
		}

		return false;
	}

	function read($_signatureID) {
		$db = clone($GLOBALS['egw']->db);
		$db->set_app('felamimail');

		$where = array(
			'fm_accountid'		=> $this->accountID,
			'fm_signatureid'	=> $_signatureID
		);

		$db->select($this->tableName,'fm_signatureid,fm_description,fm_signature,fm_defaultsignature',
			$where, __LINE__, __FILE__);

		if(($data = $db->row(true))) {
			if (empty($data['fm_description']))
			{
				$buff = trim(substr(str_replace(array("\r\n","\r","\n","\t"),array(" "," "," "," "),translation::convertHTMLToText($data['fm_signature'])),0,100));
				$data['fm_description'] = $buff?$buff:lang('none');
			}
			$this->fm_signatureid	= $data['fm_signatureid'];
			$this->fm_description	= $data['fm_description'];
			$this->fm_signature	= $data['fm_signature'];
			$this->fm_defaultsignature = (bool)$data['fm_defaultsignature'];

			return TRUE;
		}

		return FALSE;
	}

	function deleteSignatures($_signatureID)
	{
		if(!is_array($_signatureID)) {
			return false;
		}
		foreach($_signatureID as $signatureID) {
			#error_log("$signatureID");
			$this->delete($signatureID);
		}
	}

	private function delete($_signatureID = FALSE) {
		$db = clone($GLOBALS['egw']->db);
		$db->set_app('felamimail');

		if($_signatureID !== FALSE) {
			$signatureID = (int)$_signatureID;
		} else {
			$signatureID = (int)$this->fm_signatureid;
		}

		$where = array(
			'fm_accountid'		=> $this->accountID,
			'fm_signatureid'	=> $signatureID
		);

		$db->delete($this->tableName, $where, __LINE__, __FILE__);

		if ($db->affected_rows() === 0) {
			return false;
		}

		return true;
	}

	function saveSignature($_signatureID, $_description, $_signature, $_isDefaultSignature)
	{
		if($_signatureID == -1) {
			// the systemwide profile
			// can only be made the default profile

			return -1;
		} else {
			if($this->profileData->ea_user_defined_signatures == false) {
				return false;
			}

			$this->fm_description	= $_description;
			$this->fm_signature	= $_signature;
			$this->fm_defaultsignature	= (bool)$_isDefaultSignature;
			if((int)$_signatureID > 0) {
				$this->fm_signatureid = (int)$_signatureID;
			}

			$this->save();

			return $this->fm_signatureid;
		}
	}

	private function save() {
		$db = clone($GLOBALS['egw']->db);
		$db->set_app('felamimail');

		// reset fm_defaultsignature in all other rows to false
		if($this->fm_defaultsignature === true) {
			$where = array(
				'fm_accountid'		=> $this->accountID,
			);
			$data = array(
				'fm_defaultsignature'	=> false,
			);

			$db->update($this->tableName, $data, $where, __LINE__, __FILE__);
		}
		if (empty($this->fm_description))
		{
			$buff = trim(substr(str_replace(array("\r\n","\r","\n","\t"),array(" "," "," "," "),translation::convertHTMLToText($this->fm_signature)),0,100));
			$this->fm_description = $buff?$buff:lang('none');
		}
		$data = array(
			'fm_accountid'		=> $this->accountID,
			'fm_signature'		=> $this->fm_signature,
			'fm_description'	=> $this->fm_description,
			'fm_defaultsignature'	=> $this->fm_defaultsignature,
		);


		if($this->fm_signatureid === NULL) {
			$db->insert($this->tableName, $data, '', __LINE__, __FILE__);

			$this->fm_signatureid = $db->get_last_insert_id($this->tableName,'fm_signatureid');

			return TRUE;
		} else {
			$where = array(
				'fm_accountid'		=> $this->accountID,
				'fm_signatureid'	=> $this->fm_signatureid,
			);
			$db->update($this->tableName, $data, $where, __LINE__, __FILE__);

			return TRUE;
		}
	}

	function search() {
		$signatures = array();

		$db = clone($GLOBALS['egw']->db);
		$db->set_app('felamimail');

		$where = array(
			'fm_accountid'		=> $this->accountID
		);

		$db->select($this->tableName,'fm_signatureid,fm_description,fm_signature,fm_defaultsignature',
			$where, __LINE__, __FILE__);

		while ($data = $db->row(true)) {
			if (empty($data['fm_description']))
			{
				$buff = trim(substr(str_replace(array("\r\n","\r","\n","\t"),array(" "," "," "," "),translation::convertHTMLToText($data['fm_signature'])),0,100));
				$data['fm_description'] = $buff?$buff:lang('none');
			}

			$signatureData = array(
				'fm_signatureid'	=> $data['fm_signatureid'],
				'fm_description'	=> $data['fm_description'],
				'fm_signature'		=> $data['fm_signature'],
				'fm_defaultsignature'	=> (bool)$data['fm_defaultsignature'],
			);
			$signatures[$data['fm_signatureid']] = $signatureData;
		}

		return $signatures;
	}

	function getListOfSignatures() {
		//$fmSignatures = new felamimail_signatures();

		#$profileData = $boemailadmin->getUserProfile('felamimail');

		$systemSignatures = array();
		if(!empty($this->profileData->ea_default_signature)) {
			$systemSignatures[-1] = array(
				'fm_signatureid'	=> -1,
				'fm_description'	=> lang('system signature'),
				'fm_defaultsignature'	=> FALSE,
			);

			if($this->profileData->ea_user_defined_signatures != true) {
				$systemSignatures[-1]['fm_defaultsignature'] = TRUE;
			}
		}
		// return only systemsignature, if no user defined signatures are enabled
		if($this->profileData->ea_user_defined_signatures != true) {
			return $systemSignatures;
		}

		$signatures = $this->search();

		if(count($signatures) == 0 &&
			!isset($GLOBALS['egw_info']['user']['preferences']['mail']['email_sig_copied']) &&
			!empty($GLOBALS['egw_info']['user']['preferences']['mail']['email_sig'])) {

			$GLOBALS['egw']->preferences->read_repository();
			$newSignature = new mail_signatures();
			$newSignature->fm_description		= lang('default signature');
			$newSignature->fm_signature		= nl2br($GLOBALS['egw_info']['user']['preferences']['felamimail']['email_sig']);
			$newSignature->fm_defaultsignature	= true;
			$newSignature->save();
			$GLOBALS['egw']->preferences->add('mail', 'email_sig_copied', true);
			$GLOBALS['egw']->preferences->save_repository();

			$signatures = $this->search();
		}

		// make systemsignature the default, if no other signature is defined as default signature
		if($this->getDefaultSignature() === false) {
			$systemSignatures[-1]['fm_defaultsignature'] = TRUE;
		}

		$signatures = array_merge($systemSignatures, $signatures);
		#_debug_array($signatures);
		return $signatures;
	}

	function getSignature($_signatureID, $_unparsed = false)
	{
		if($_signatureID == -1) {

			$systemSignatureIsDefaultSignature = $this->getDefaultSignature();

			$signature = new mail_signatures();
			$signature->fm_signatureid	= -1;
			$signature->fm_description	= 'eGroupWare '. lang('default signature');
			$signature->fm_signature	= ($_unparsed === true ? $this->profileData->ea_default_signature : $GLOBALS['egw']->preferences->parse_notify($this->profileData->ea_default_signature));
			$signature->fm_defaultsignature = $systemSignatureIsDefaultSignature;

			return $signature;

		} else {
			$signatures = $this->getListOfSignatures();

			foreach($signatures as $signature) {
				if ($_signatureID == $signature['fm_signatureid'])
				{
					$matchedSig = new mail_signatures();
					$matchedSig->fm_signatureid	= $signature['fm_signatureid'];
					$matchedSig->fm_description	= $signature['fm_description'];
					$matchedSig->fm_signature	= $signature['fm_signature'];
					$matchedSig->fm_defaultsignature = $signature['fm_defaultsignature'];
					break;
				}
			}

			if($_unparsed === false) {
				$matchedSig->fm_signature = ($_unparsed === true ? $this->profileData->ea_default_signature : $GLOBALS['egw']->preferences->parse_notify($matchedSig->fm_signature));
			}
			return $matchedSig;
		}
	}
}
?>
