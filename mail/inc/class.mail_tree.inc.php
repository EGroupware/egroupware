<?php
/**
 * EGroupware - Mail - tree worker class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Hadi Nategh [hn@egroupware.org]
 * @copyright (c) 2015-16 by EGroupware GmbH <info-AT-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id:$
 */

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Api\Etemplate\Widget\Tree;

/**
 * Mail tree worker class
 *  -provides backend functionality for folder tree
 *  -provides classes that may be used by other apps too
 *
 */
class mail_tree
{
	/**
	 * delimiter - used to separate acc_id from mailbox / folder-tree-structure
	 *
	 * @var string
	 */
	const DELIMITER = Mail::DELIMITER;

	/**
	 * bit flag: ident_realname
	 */
	const IDENT_NAME = 1;
	/**
	 * bit flag: ident_email
	 */
	const IDENT_EMAIL = 2;
	/**
	 * bit flag: ident_org
	 */
	const IDENT_ORG = 4;
	/**
	 * bit flag: ident_name
	 */
	const IDENT_NAME_IDENTITY= 8;

	/**
	 * bit flag: org | name email
	 */
	const ORG_NAME_EMAIL = 16;

	/**
	 * Icons used for nodes different states
	 *
	 * @var array
	 */
	static $leafImages = array(
		'folderNoSelectClosed' => "folderNoSelectClosed",
		'folderNoSelectOpen' => "folderNoSelectOpen",
		'folderOpen' => "folderOpen",
		'folderClosed' => "MailFolderClosed",
		'folderLeaf' => "MailFolderPlain",
		'folderHome' => "kfm_home",
		'folderAccount' => "thunderbird",
	);

	/**
	 * Instance of mail_ui class
	 *
	 * @var mail_ui
	 */
	var $ui;

	/**
	 * Mail tree constructor
	 *
	 * @param mail_ui $mail_ui
	 */
	function __construct(mail_ui $mail_ui)
	{
		$this->ui = $mail_ui;

		// check images available in png or svg
		foreach(self::$leafImages as &$image)
		{
			if (strpos($image, '.') === false)
			{
				$image = basename($img=Api\Image::find('mail', 'dhtmlxtree/'.$image));
			}
		}
	}

	/**
	 *
	 * Structs an array of fake INBOX to show as an error node
	 * @param string $_profileID icServer profile id
	 * @param string $_err error message to be shown on tree node
	 * @param mixed $_path
	 * @param mixed $_parent
	 * @return array returns an array of tree node
	 */
	static function treeLeafNoConnectionArray($_profileID, $_err, $_path, $_parent)
	{
		$baseNode = array('id' => $_profileID);
		$leaf =  array(
			'id' => $_profileID.self::DELIMITER.'INBOX',
			'text' => $_err,
			'tooltip' => $_err,
			'im0' => self::$leafImages["folderNoSelectClosed"],
			'im1' => self::$leafImages["folderNoSelectOpen"],
			'im2' => self::$leafImages["folderNoSelectClosed"],
			'path'=> $_path,
			'parent' => $_parent
		);
		self::setOutStructure($leaf, $baseNode, self::DELIMITER);

		return ($baseNode?$baseNode:array( // fallback not connected array
						'id'=>0,
						'item'=> array(
							'text'=>'INBOX',
							'tooltip'=>'INBOX'.' '.lang('(not connected)'),
							'im0'=> self::$leafImages['folderHome']
						)
					)
		);
	}

	/**
	 * Check if a given node has children attribute set
	 *
	 * @param array $_node array of a node
	 * @return int returns 1 if it has children flag set otherwise 0
	 */
	private static function nodeHasChildren ($_node)
	{
		$hasChildren = 0;
		if (in_array('\haschildren', $_node['ATTRIBUTES']) ||
				in_array('\Haschildren', $_node['ATTRIBUTES']) ||
				in_array('\HasChildren', $_node['ATTRIBUTES'])) $hasChildren = 1;
		return $hasChildren;
	}

	/**
	 * Check if the given tree id is account node (means root)
	 *
	 * @param type $_node a tree id node
	 * @return boolean returns true if the node is account node otherwise false
	 */
	private static function isAccountNode ($_node)
	{
		list(,$leaf) = explode(self::DELIMITER, $_node)+[null,null];
		if ($leaf || $_node == null) return false;
		return true;
	}

	/**
	 * Calculate node level form the root
	 * @param type $_path tree node full path, e.g. INBOX/Drafts
	 *
	 * @return int returns node level distance from the root,
	 * returns false if something goes wrong
	 */
	private static function getNodeLevel($_path, $_delimiter = '.')
	{
		$parts = explode($_delimiter, $_path);
		if (is_array($parts))
		{
			return count($parts);
		}
		return false;
	}

	/**
	 * getTree provides tree structure regarding to selected node
	 *
	 * @param string $_parent = null no parent node means root with the first level of folders
	 * @param string $_profileID = '' icServer id
	 * @param int|boolean $_openTopLevel = 1 Open top level folders on load if it's set to 1|true,
	 *  false|0 leaves them in closed state
	 * @param $_noCheckboxNS = false no checkbox for namesapaces makes sure to not put checkbox for namespaces node
	 * @param boolean $_subscribedOnly = false get only subscribed folders
	 * @param boolean $_allInOneGo = false, true will get all folders (dependes on subscribedOnly option) of the account in one go
	 * @param boolean $_checkSubscribed = true, pre-check checkboxes of subscribed folders
	 *
	 * @return array returns an array of mail tree structure according to provided node
	 */
	function getTree ($_parent = null, $_profileID = '', $_openTopLevel = 1, $_noCheckboxNS = false, $_subscribedOnly= false, $_allInOneGo = false, $_checkSubscribed = true)
	{
		//Init mail folders
		$tree = array(Tree::ID=> $_parent?$_parent:0,Tree::CHILDREN => array());
		if (!isset($this->ui->mail_bo)) throw new Api\Exception\WrongUserinput(lang('Initialization of mail failed. Please use the Wizard to cope with the problem'));
		$hDelimiter = $this->ui->mail_bo->getHierarchyDelimiter();

		if ($_parent) list($_profileID) = explode(self::DELIMITER, $_parent);

		if (is_numeric($_profileID) && $_profileID != $this->ui->mail_bo->profileID)
		{
			try
			{
				$this->ui->changeProfile($_profileID);
			} catch (Exception $ex) {
				return self::treeLeafNoConnectionArray($_profileID, $ex->getMessage(),array($_profileID), '');
			}
		}

		try
		{
			// *** Note: Should not apply any imap transaction, because in case of exception it will stop the
			// process of rendering root node

			if ($_parent && !self::isAccountNode($_parent)) // Single node loader
			{
				$nodeInfo = Mail::pathToFolderData($_parent, $hDelimiter);
				$folders = $this->ui->mail_bo->getFolderArrays($nodeInfo['mailbox'],false,$_allInOneGo?0:2, $_subscribedOnly);

				$childrenNode = array();
				foreach ($folders as &$node)
				{
					$nodeId = $_profileID.self::DELIMITER.$node['MAILBOX'];
					$nodeData = Mail::pathToFolderData($nodeId, $node['delimiter']);
					$childrenNode[] = array(
						Tree::ID=> $nodeId,
						Tree::AUTOLOAD_CHILDREN => $_allInOneGo?false:self::nodeHasChildren($node),
						Tree::CHILDREN =>array(),
						Tree::LABEL => $nodeData['text'],
						Tree::TOOLTIP => $nodeData['tooltip'],
						Tree::IMAGE_LEAF => self::$leafImages['folderLeaf'],
						Tree::IMAGE_FOLDER_OPEN => self::$leafImages['folderOpen'],
						Tree::IMAGE_FOLDER_CLOSED => self::$leafImages['folderClosed'],
						Tree::CHECKED => $_checkSubscribed?$node['SUBSCRIBED']:false,
						'parent' => $_parent
					);
				}
				$tree[Tree::CHILDREN] = $childrenNode;
			}
			else //Top Level Nodes loader
			{
				if (self::isAccountNode($_parent)) // An account called for open
				{
					$_openTopLevel = 1;
					$tree = self::getAccountsRootNode($_profileID, $_noCheckboxNS, $_openTopLevel);
				}
				else // Initial accounts|root nodes
				{
					$tree = self::getAccountsRootNode($_profileID, $_noCheckboxNS, $_openTopLevel);
					if (!$_profileID && !$_openTopLevel) return $tree;
				}

				//List of folders
				$foldersList = $this->ui->mail_bo->getFolderArrays(null, true, $_allInOneGo?0:2,$_subscribedOnly, false);

				// User defined folders based on account
				$definedFolders = array(
					'Trash'     => $this->ui->mail_bo->getTrashFolder(false),
					'Templates' => $this->ui->mail_bo->getTemplateFolder(false),
					'Drafts'    => $this->ui->mail_bo->getDraftFolder(false),
					'Sent'      => $this->ui->mail_bo->getSentFolder(false),
					'Junk'      => $this->ui->mail_bo->getJunkFolder(false),
					'Outbox'    => $this->ui->mail_bo->getOutboxFolder(false),
					'Ham'		=> $this->ui->mail_bo->icServer->acc_folder_ham
				);
				foreach ($foldersList as &$folder)
				{
					$path = $parent = $parts = explode($folder['delimiter'], $folder['MAILBOX']);
					array_pop($parent);

					array_unshift($path, $_profileID);

					$data = array(
						Tree::ID=>$_profileID.self::DELIMITER.$folder['MAILBOX'],
						Tree::AUTOLOAD_CHILDREN => $_allInOneGo?false:self::nodeHasChildren($folder),
						Tree::CHILDREN =>array(),
						Tree::LABEL =>lang($folder['MAILBOX']),
						Tree::OPEN => self::getNodeLevel($folder['MAILBOX'], $folder['delimiter']) <= $_openTopLevel?1:0,
						Tree::TOOLTIP => lang($folder['MAILBOX']),
						Tree::CHECKED => $_checkSubscribed?$folder['SUBSCRIBED']:false,
						Tree::NOCHECKBOX => 0,
						'parent' => $parent?$_profileID.self::DELIMITER.implode($folder['delimiter'], $parent):$_profileID,
						'path' => $path,
						'folderarray' => $folder
					);
					// Set Acl capability for INBOX
					if ($folder['MAILBOX'] === "INBOX")
					{
						$data['data'] = array('acl' => $this->ui->mail_bo->icServer->queryCapability('ACL'));
						$data[Tree::NOCHECKBOX] = $_noCheckboxNS;
					}
					else
					{
						//Do not open Initially other folders but INBOX
						$data[Tree::OPEN] = 0;
					}
					self::setOutStructure($data, $tree, $folder['delimiter'], true, $this->ui->mail_bo->_getNameSpaces(), $definedFolders);
				}
				// Structs children of account root node. Used for mail index tree when we do autoloading on account id
				if (self::isAccountNode($_parent))
				{
					$tree = array(
						Tree::ID => (string)$_parent,
						Tree::CHILDREN => $tree[Tree::CHILDREN][0][Tree::CHILDREN],
						Tree::LABEL => $tree[Tree::CHILDREN][0][Tree::LABEL],
						Tree::IMAGE_LEAF => $tree[Tree::CHILDREN][0][Tree::IMAGE_LEAF],
						Tree::IMAGE_FOLDER_OPEN => $tree[Tree::CHILDREN][0][Tree::IMAGE_FOLDER_OPEN],
						Tree::IMAGE_FOLDER_CLOSED => $tree[Tree::CHILDREN][0][Tree::IMAGE_FOLDER_CLOSED],
						Tree::OPEN => 1,
						Tree::TOOLTIP => $tree[Tree::CHILDREN][0][Tree::TOOLTIP],
						Tree::AUTOLOAD_CHILDREN => 1,
						'data' => $tree[Tree::CHILDREN][0]['data']
					);
				}
			}
		}
		catch (Exception $ex) // Catch exceptions
		{
			//mail_ui::callWizard($ex->getMessage(), false, 'error');
			return self::treeLeafNoConnectionArray($_profileID, $ex->getMessage(),array($_profileID), '');
		}

		return $tree;
	}

	/**
	 * setOutStructure - helper function to transform the folderObjectList to dhtmlXTreeObject requirements
	 *
	 * @param array $data data to be processed
	 * @param array &$out, out array
	 * @param string $del needed as glue for parent/child operation / comparsion
	 * @param boolean $createMissingParents a missing parent, instead of throwing an exception
	 * @param array $nameSpace used to check on creation of nodes in namespaces other than personal
	 *					as clearance for access may be limited to a single branch-node of a tree
	 * @return void
	 */
	static function setOutStructure($data, &$out, $del='.', $createMissingParents=true, $nameSpace=array(), $definedFolders= array())
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
				$parent = $parents[0].self::DELIMITER.implode($del, $helper);
				if ($parent) $parent .= $del;
			}
			else
			{
				$parent = implode(self::DELIMITER, $parents);
				if ($parent) $parent .= self::DELIMITER;
			}

			if (!is_array($insert) || !isset($insert['item']))
			{
				// throwing an exeption here seems to be unrecoverable,
				// even if the cause is a something that can be handeled by the mailserver
				if (Mail::$debug) error_log(__METHOD__.':'.__LINE__." id=$data[id]: Parent '$parent' of '$component' not found!");
				// should we hit the break? if in personal: sure, something is wrong with the folderstructure
				// if in shared or others we may proceed as access to folders may very well be limited to
				// a single folder within the tree
				$break = true;
				foreach ($nameSpace as $nsp)
				{
					// if (appropriately padded) namespace prefix of (others or shared) is the leading part of parent
					// we want to create the node in question as we meet the above considerations
					if ($nsp['type']!='personal' && $nsp['prefix_present'] && stripos($parent,$data['path'][0].self::DELIMITER.$nsp['prefix'])===0)
					{
						if (Mail::$debug) error_log(__METHOD__.__LINE__.' about to create:'.$parent.' in '.$data['path'][0].self::DELIMITER.$nsp['prefix']);
						$break=false;
					}
				}
				if ($break) break;
			}
			if ($insert['item'])
			{
				foreach($insert['item'] as &$item)
				{
					if ($item['id'] == $parent.$component)
					{
						$insert =& $item;
						break;
					}
				}
			}
			if ($item['id'] != $parent.$component)
			{
				if ($createMissingParents)
				{
					unset($item);
					$item = array(
						'id' => $parent.$component,
						'text' => $component,
						'im0' => self::$leafImages["folderNoSelectClosed"],
						'im1' => self::$leafImages["folderNoSelectOpen"],
						'im2' => self::$leafImages["folderNoSelectClosed"],
						'tooltip' => lang('no access')
					);
					$insert['item'][] =& $item;
					$insert =& $item;
				}
				else
				{
					throw new Api\Exception\AssertionFailed(__METHOD__.':'.__LINE__.": id=$data[id]: Parent '$parent' '$component' not found!");
				}
			}
			$parents[] = $component;
		}
		if (!empty($data['folderarray']['delimiter']) && !empty($data['folderarray']['MAILBOX']))
		{
			$path = explode($data['folderarray']['delimiter'], $data['folderarray']['MAILBOX']);
			$folderName = array_pop($path);

			if ($data['folderarray']['MAILBOX'] === "INBOX")
			{
				$data[Tree::IMAGE_LEAF] = self::$leafImages['folderHome'];
				$data[Tree::IMAGE_FOLDER_OPEN] = self::$leafImages['folderHome'];
				$data[Tree::IMAGE_FOLDER_CLOSED] = self::$leafImages['folderHome'];
				$data[Tree::LABEL] = lang($folderName);
				$data[Tree::TOOLTIP] = lang($folderName);
			}
			// User defined folders may get different icons
			// plus they need to be translated too
			elseif (($key = array_search($data['folderarray']['MAILBOX'], $definedFolders, true)) !== false)
			{
				$data[Tree::LABEL] = lang($key);
				$data[Tree::TOOLTIP] = $key;
				//User defined folders icons
				$data[Tree::IMAGE_LEAF] =
					$data[Tree::IMAGE_FOLDER_OPEN] =
					$data [Tree::IMAGE_FOLDER_CLOSED] = basename(Api\Image::find('mail', 'dhtmlxtree/'."MailFolder".$key));
			}
			elseif(!empty($data['folderarray']['attributes']) && stripos(array2string($data['folderarray']['attributes']),'\noselect') !== false)
			{
				$data[Tree::IMAGE_LEAF] = self::$leafImages['folderNoSelectClosed'];
				$data[Tree::IMAGE_FOLDER_OPEN] = self::$leafImages['folderNoSelectOpen'];
				$data[Tree::IMAGE_FOLDER_CLOSED] = self::$leafImages['folderNoSelectClosed'];
			}
			elseif ($data['parent'])
			{
				$data[Tree::LABEL] = $folderName;
				$data[Tree::TOOLTIP] = $folderName;
				$data[Tree::IMAGE_LEAF] = self::$leafImages['folderLeaf'];
				$data[Tree::IMAGE_FOLDER_OPEN] = self::$leafImages['folderOpen'];
				$data[Tree::IMAGE_FOLDER_CLOSED] = self::$leafImages['folderClosed'];
			}

			// Contains unseen mails for the folder
			$unseen = $data['folderarray']['counter']['UNSEEN'] ?? 0;

			// if there's unseen mails then change the label and style
			// accordingly to indicate useen mails
			if ($unseen > 0)
			{
				$data[Tree::LABEL] = $data[Tree::LABEL].'('.$unseen.')';
				$data['style'] = 'font-weight: bold';
			}
		}
		//Remove extra data from tree structure
		unset($data['folderarray']);
		unset($data['path']);

		$insert['item'][] = $data;
	}

	/**
	 * Get accounts root node, fetches all or an accounts for a user
	 *
	 * @param type $_profileID = null Null means all accounts and giving profileid means fetches node for the account
	 * @param type $_noCheckbox = false option to switch checkbox of
	 * @param type $_openTopLevel = 0 option to either start the node opened (1) or closed (0)
	 *
	 * @return array an array of baseNodes of accounts
	 */
	static function getAccountsRootNode($_profileID = null, $_noCheckbox = false, $_openTopLevel = 0 )
	{
		$roots = array(Tree::ID => 0, Tree::CHILDREN => array());

		foreach(Mail\Account::search(true, 'params') as $acc_id => $params)
		{
			try {
				$accObj = new Mail\Account($params);
				if (!$accObj->is_imap()|| $_profileID && $acc_id != $_profileID) continue;
				$identity = self::getIdentityName(Mail\Account::identity_name($accObj,true, $GLOBALS['egw_info']['user']['account_id'], true));
				// Open top level folders for active account
				$openActiveAccount = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] == $acc_id?1:0;

				$baseNode = array(
					Tree::ID => (string)$acc_id,
					Tree::LABEL => str_replace(array('<','>'),array('[',']'),$identity),
					Tree::TOOLTIP => '('.$acc_id.') '.htmlspecialchars_decode($identity),
					Tree::IMAGE_LEAF => self::$leafImages['folderAccount'],
					Tree::IMAGE_FOLDER_OPEN => self::$leafImages['folderAccount'],
					Tree::IMAGE_FOLDER_CLOSED => self::$leafImages['folderAccount'],
					'path'=> array($acc_id),
					Tree::CHILDREN => array(), // dynamic loading on unfold
					Tree::AUTOLOAD_CHILDREN => true,
					'parent' => '',
					Tree::OPEN => $_openTopLevel?:$openActiveAccount,
					// mark on account if Sieve is enabled
					'data' => array(
						'sieve' => $accObj->imapServer()->acc_sieve_enabled,
						'spamfolder'=> $accObj->imapServer()->acc_folder_junk&&(strtolower($accObj->imapServer()->acc_folder_junk)!='none')?true:false,
						'archivefolder'=> $accObj->imapServer()->acc_folder_archive&&(strtolower($accObj->imapServer()->acc_folder_archive)!='none')?true:false
					),
					Tree::NOCHECKBOX  => $_noCheckbox
				);
			}
			catch (\Exception $ex) {
				$baseNode = array(
					Tree::ID => (string)$acc_id,
					Tree::LABEL => lang('Error').': '.lang($ex->getMessage()),
					Tree::TOOLTIP => '('.$acc_id.') '.htmlspecialchars_decode($params['acc_name']),
					Tree::IMAGE_LEAF => self::$leafImages['folderAccount'],
					Tree::IMAGE_FOLDER_OPEN => self::$leafImages['folderAccount'],
					Tree::IMAGE_FOLDER_CLOSED => self::$leafImages['folderAccount'],
					'path'=> array($acc_id),
					Tree::CHILDREN => array(), // dynamic loading on unfold
					Tree::AUTOLOAD_CHILDREN => false,
					'parent' => '',
					Tree::OPEN => false,
					Tree::NOCHECKBOX  => true
				);
			}
			self::setOutStructure($baseNode, $roots,self::DELIMITER);
		}
		return $roots;
	}

	/**
	 * Initialization tree for index sidebox menu
	 *
	 * This function gets all accounts root nodes and then
	 * fill the active accounts with its children.
	 *
	 * @param string $_parent = null no parent node means root with the first level of folders
	 * @param string $_profileID = '' active profile / acc_id
	 * @param int|boolean $_openTopLevel = 1 Open top level folders on load if it's set to 1|true,
	 *  false|0 leaves them in closed state
	 * @param boolean $_subscribedOnly = false get only subscribed folders
	 * @param boolean $_allInOneGo = false, true will get all folders (dependes on subscribedOnly option) of the account in one go
	 * @return type an array of tree
	 */
	function getInitialIndexTree ($_parent = null, $_profileID = '', $_openTopLevel = 1, $_subscribedOnly= false, $_allInOneGo = false)
	{
		$tree = $this->getTree($_parent, '', $_openTopLevel, false, $_subscribedOnly, $_allInOneGo);
		$branches = $this->getTree($_profileID, $_profileID,1,false,$_subscribedOnly,$_allInOneGo);
		foreach ($tree[Tree::CHILDREN] as &$account)
		{
			if ($account[Tree::ID] == $_profileID)
			{
				$account = array_merge($account , $branches);
			}
		}
		return $tree;
	}

	/**
	 * Build folder tree parent identity label
	 *
	 * @param array $_account
	 * @param bool $_fullString = true full or false=NamePart only is returned
	 * @return string
	 */
	static function getIdentityName ($_account, bool $_fullString=true)
	{
		$identLabel = $GLOBALS['egw_info']['user']['preferences']['mail']['identLabel'];
		$name = array();

		if ($identLabel & self::IDENT_NAME_IDENTITY)
		{
			$name[] = $_account['ident_name'];
		}

		if ($identLabel & self::IDENT_NAME)
		{
			$name[] = $_account['ident_realname']. ' ';
		}

		if ($identLabel & self::IDENT_ORG)
		{
			$name[] = $_account['ident_org'];
		}

		if ($identLabel & self::ORG_NAME_EMAIL)
		{
			$name[] = $_account['ident_org']." | ".$_account['ident_realname'].($_fullString ? ' '.' <'.$_account['ident_email'].'>' : '');
		}

		if ($identLabel & self::IDENT_EMAIL || empty($name))
		{
			if ($_fullString && trim($_account['ident_email']))
			{
				$name[] = ' <'.$_account['ident_email'].'>';
			}
			elseif (!empty($_account['acc_imap_username']) && trim($_account['acc_imap_username']))
			{
				$name[] = ' <'.$_account['acc_imap_username'].'>';
			}
		}
		return implode(' ', $name);
	}
}