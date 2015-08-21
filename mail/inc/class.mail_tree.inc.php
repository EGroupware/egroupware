<?php
/**
 * EGroupware - Mail - tree worker class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Hadi Nategh [hn@stylite.de]
 * @copyright (c) 2015 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id:$
 */


/**
 * Define tree as class tree widget
 */
use \etemplate_widget_tree as tree;


/**
 * Mail tree worker class
 *  -provides backend functionality for folder tree
 *  -provides classes that may be used by other apps too
 *
 */
class mail_tree
{
	/**
	 * delimiter - used to separate profileID from folder-tree-structure
	 *
	 * @var string
	 */
	static $delimiter = '::';

	/**
	 * Icons used for nodes different states
	 *
	 * @var array
	 */
	static $leafImages = array(
		'folderNoSelectClosed' => "folderNoSelectClosed.gif",
		'folderNoSelectOpen' => "folderNoSelectOpen.gif",
		'folderOpen' => "folderOpen.gif",
		'folderClosed' => "MailFolderClosed.png",
		'folderLeaf' => "MailFolderPlain.png",
		'folderHome' => "kfm_home.png",
		'folderAccount' => "thunderbird.png",
	);

	/**
	 * Mail tree constructor
	 *
	 * @param object $mail_ui
	 */
	function __construct($mail_ui) {
		$this->ui = $mail_ui;
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
			'id' => $_profileID.self::$delimiter.'INBOX',
			'text' => $_err,
			'tooltip' => $_err,
			'im0' => self::$leafImages["folderNoSelectClosed"],
			'im1' => self::$leafImages["folderNoSelectOpen.gif"],
			'im2' => self::$leafImages["folderNoSelectClosed"],
			'path'=> $_path,
			'parent' => $_parent
		);
		self::setOutStructure($leaf, $baseNode, self::$delimiter);

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
	 * Get folder data from path
	 *
	 * @param string $path a node path
	 * @return array returns an array of data extracted from given node path
	 */
	static function pathToFolderData ($_path, $_hDelimiter)
	{
		if (!strpos($_path, self::$delimiter)) $_path = self::$delimiter.$_path;
		list(,$path) = explode(self::$delimiter, $_path);
		$path_chain = $parts = explode($_hDelimiter, $path);
		$name = array_pop($parts);
		return array (
			'name' => $name,
			'mailbox' => $path,
			'parent' => implode($_hDelimiter, $parts),
			'text' => $name,
			'tooltip' => $name,
			'path' => $path_chain
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
		list(,$leaf) = explode(self::$delimiter, $_node);
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
		$tree = array(tree::ID=> $_parent?$_parent:0,tree::CHILDREN => array());
		$hDelimiter = $this->ui->mail_bo->getHierarchyDelimiter();

		if ($_parent) list($_profileID) = explode(self::$delimiter, $_parent);

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
			// User defined folders based on account
			$definedFolders = array(
				'Trash'     => $this->ui->mail_bo->getTrashFolder(false),
				'Templates' => $this->ui->mail_bo->getTemplateFolder(false),
				'Drafts'    => $this->ui->mail_bo->getDraftFolder(false),
				'Sent'      => $this->ui->mail_bo->getSentFolder(false),
				'Junk'      => $this->ui->mail_bo->getJunkFolder(false),
				'Outbox'    => $this->ui->mail_bo->getOutboxFolder(false),
			);
			if ($_parent && !self::isAccountNode($_parent)) // Single node loader
			{
				$nodeInfo = self::pathToFolderData($_parent, $hDelimiter);
				$folders = $this->ui->mail_bo->getFolderArrays($nodeInfo['mailbox'],false,$_allInOneGo?0:2, $_subscribedOnly);

				$childrenNode = array();
				foreach ($folders as &$node)
				{
					$nodeId = $_profileID.self::$delimiter.$node['MAILBOX'];
					$nodeData = self::pathToFolderData($nodeId, $node['delimiter']);
					$childrenNode[] = array(
						tree::ID=> $nodeId,
						tree::AUTOLOAD_CHILDREN => $_allInOneGo?false:self::nodeHasChildren($node),
						tree::CHILDREN =>array(),
						tree::LABEL => $nodeData['text'],
						tree::TOOLTIP => $nodeData['tooltip'],
						tree::IMAGE_LEAF => self::$leafImages['folderLeaf'],
						tree::IMAGE_FOLDER_OPEN => self::$leafImages['folderOpen'],
						tree::IMAGE_FOLDER_CLOSED => self::$leafImages['folderClose'],
						tree::CHECKED => $_checkSubscribed?$node['SUBSCRIBED']:false,
						'parent' => $_parent
					);
				}
				$tree[tree::CHILDREN] = $childrenNode;
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
				$foldersList = $this->ui->mail_bo->getFolderArrays(null, true, $_allInOneGo?0:2,$_subscribedOnly, true);
				foreach ($foldersList as &$folder)
				{
					$path = $parent = $parts = explode($folder['delimiter'], $folder['MAILBOX']);
					array_pop($parent);

					array_unshift($path, $_profileID);

					$data = array(
						tree::ID=>$_profileID.self::$delimiter.$folder['MAILBOX'],
						tree::AUTOLOAD_CHILDREN => $_allInOneGo?false:self::nodeHasChildren($folder),
						tree::CHILDREN =>array(),
						tree::LABEL =>lang($folder['MAILBOX']),
						tree::OPEN => self::getNodeLevel($folder['MAILBOX'], $folder['delimiter']) <= $_openTopLevel?1:0,
						tree::TOOLTIP => lang($folder['MAILBOX']),
						tree::CHECKED => $_checkSubscribed?$folder['SUBSCRIBED']:false,
						tree::NOCHECKBOX => 0,
						'parent' => $parent?$_profileID.self::$delimiter.implode($folder['delimiter'], $parent):$_profileID,
						'path' => $path,
						'folderarray' => $folder
					);
					// Set Acl capability for INBOX
					if ($folder['MAILBOX'] === "INBOX")
					{
						$data['data'] = array('acl' => $this->ui->mail_bo->icServer->queryCapability('ACL'));
						$data[tree::NOCHECKBOX] = $_noCheckboxNS;
					}
					else
					{
						//Do not open Initially other folders but INBOX
						$data[tree::OPEN] = 0;
					}
					self::setOutStructure($data, $tree, $folder['delimiter'], true, $this->ui->mail_bo->_getNameSpaces(), $definedFolders);
				}
				// Structs children of account root node. Used for mail index tree when we do autoloading on account id
				if (self::isAccountNode($_parent))
				{
					$tree = array(
						tree::ID => (string)$_parent,
						tree::CHILDREN => $tree[tree::CHILDREN][0][tree::CHILDREN],
						tree::LABEL => $tree[tree::CHILDREN][0][tree::LABEL],
						tree::IMAGE_LEAF => $tree[tree::CHILDREN][0][tree::IMAGE_LEAF],
						tree::IMAGE_FOLDER_OPEN => $tree[tree::CHILDREN][0][tree::IMAGE_FOLDER_OPEN],
						tree::IMAGE_FOLDER_CLOSED => $tree[tree::CHILDREN][0][tree::IMAGE_FOLDER_CLOSED],
						tree::OPEN => 1,
						tree::TOOLTIP => $tree[tree::CHILDREN][0][tree::TOOLTIP],
						tree::AUTOLOAD_CHILDREN => 1,
						'data' => $tree[tree::CHILDREN][0]['data']
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
				$parent = $parents[0].self::$delimiter.implode($del, $helper);
				if ($parent) $parent .= $del;
			}
			else
			{
				$parent = implode(self::$delimiter, $parents);
				if ($parent) $parent .= self::$delimiter;
			}

			if (!is_array($insert) || !isset($insert['item']))
			{
				// throwing an exeption here seems to be unrecoverable,
				// even if the cause is a something that can be handeled by the mailserver
				if (mail_bo::$debug) error_log(__METHOD__.':'.__LINE__." id=$data[id]: Parent '$parent' of '$component' not found!");
				// should we hit the break? if in personal: sure, something is wrong with the folderstructure
				// if in shared or others we may proceed as access to folders may very well be limited to
				// a single folder within the tree
				$break = true;
				foreach ($nameSpace as $nsp)
				{
					// if (appropriately padded) namespace prefix of (others or shared) is the leading part of parent
					// we want to create the node in question as we meet the above considerations
					if ($nsp['type']!='personal' && $nsp['prefix_present'] && stripos($parent,$data['path'][0].self::$delimiter.$nsp['prefix'])===0)
					{
						if (mail_bo::$debug) error_log(__METHOD__.__LINE__.' about to create:'.$parent.' in '.$data['path'][0].self::$delimiter.$nsp['prefix']);
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
					$item = array('id' => $parent.$component, 'text' => $component, 'im0' => "folderNoSelectClosed.gif",'im1' => "folderNoSelectOpen.gif",'im2' => "folderNoSelectClosed.gif",'tooltip' => lang('no access'));
					$insert['item'][] =& $item;
					$insert =& $item;
				}
				else
				{
					throw new egw_exception_assertion_failed(__METHOD__.':'.__LINE__.": id=$data[id]: Parent '$parent' '$component' not found!");
				}
			}
			$parents[] = $component;
		}
		if ($data['folderarray']['delimiter'] && $data['folderarray']['MAILBOX'])
		{
			$path = explode($data['folderarray']['delimiter'], $data['folderarray']['MAILBOX']);
			$folderName = array_pop($path);

			if ($data['folderarray']['MAILBOX'] === "INBOX")
			{
				$data[tree::IMAGE_LEAF] = self::$leafImages['folderHome'];
				$data[tree::IMAGE_FOLDER_OPEN] = self::$leafImages['folderHome'];
				$data[tree::IMAGE_FOLDER_CLOSED] = self::$leafImages['folderHome'];
				$data[tree::LABEL] = lang($folderName);
				$data[tree::TOOLTIP] = lang($folderName);
			}
			// User defined folders may get different icons
			// plus they need to be translated too
			elseif (($key = array_search($data['folderarray']['MAILBOX'], $definedFolders, true)) !== false)
			{
				$data[tree::LABEL] = lang($key);
				$data[tree::TOOLTIP] = lang($key);
				//User defined folders icons
				$data[tree::IMAGE_LEAF] =
					$data[tree::IMAGE_FOLDER_OPEN] =
					$data [tree::IMAGE_FOLDER_CLOSED] = "MailFolder".$key.".png";
			}
			elseif(stripos(array2string($data['folderarray']['attributes']),'\noselect')!== false)
			{
				$data[tree::IMAGE_LEAF] = self::$leafImages['folderNoSelectClosed'];
				$data[tree::IMAGE_FOLDER_OPEN] = self::$leafImages['folderNoSelectOpen'];
				$data[tree::IMAGE_FOLDER_CLOSED] = self::$leafImages['folderNoSelectClosed'];
			}
			elseif ($data['parent'])
			{
				$data[tree::LABEL] = $folderName;
				$data[tree::TOOLTIP] = $folderName;
				$data[tree::IMAGE_LEAF] = self::$leafImages['folderLeaf'];
				$data[tree::IMAGE_FOLDER_OPEN] = self::$leafImages['folderOpen'];
				$data[tree::IMAGE_FOLDER_CLOSED] = self::$leafImages['folderClose'];
			}

			// Contains unseen mails for the folder
			$unseen = $data['folderarray']['counter']['UNSEEN'];

			// if there's unseen mails then change the label and style
			// accordingly to indicate useen mails
			if ($unseen > 0)
			{
				$data[tree::LABEL] = $data[tree::LABEL].'('.$unseen.')';
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
		$roots = array(tree::ID => 0, tree::CHILDREN => array());

		foreach(emailadmin_account::search(true, false) as $acc_id => $accObj)
		{
			if (!$accObj->is_imap()|| $_profileID && $acc_id != $_profileID) continue;
			$identity = emailadmin_account::identity_name($accObj,true,$GLOBALS['egw_info']['user']['acount_id']);
			// Open top level folders for active account
			$openActiveAccount = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] == $acc_id?1:0;

			$baseNode = array(
							tree::ID=> (string)$acc_id,
							tree::LABEL => str_replace(array('<','>'),array('[',']'),$identity),
							tree::TOOLTIP => '('.$acc_id.') '.htmlspecialchars_decode($identity),
							tree::IMAGE_LEAF => self::$leafImages['folderAccount'],
							tree::IMAGE_FOLDER_OPEN => self::$leafImages['folderAccount'],
							tree::IMAGE_FOLDER_CLOSED => self::$leafImages['folderAccount'],
							'path'=> array($acc_id),
							tree::CHILDREN => array(), // dynamic loading on unfold
							tree::AUTOLOAD_CHILDREN => true,
							'parent' => '',
							tree::OPEN => $_openTopLevel?$_openTopLevel:$openActiveAccount,
							// mark on account if Sieve is enabled
							'data' => array(
										'sieve' => $accObj->imapServer()->acc_sieve_enabled,
										'spamfolder'=> $accObj->imapServer()->acc_folder_junk?true:false
									),
							tree::NOCHECKBOX  => $_noCheckbox
			);
			self::setOutStructure($baseNode, $roots,self::$delimiter);
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
		foreach ($tree[tree::CHILDREN] as &$account)
		{
			if ($account[tree::ID] == $_profileID)
			{
				$account = $branches;
			}
		}
		return $tree;
	}
}
