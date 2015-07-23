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
	static function getFolderData ($_path, $_hDelimiter)
	{
		list(,$path) = explode(self::$delimiter, $_path);
		$parts = explode($_hDelimiter, $path);
		$name = array_pop($parts);
		return array (
			'name' => $name,
			'mailbox' => $path,
			'parent' => implode($_hDelimiter, $parts),
			'text' => $name,
			'tooltip' => $name
		);
	}
	
	/**
	 * getTree provides tree structure regarding to selected node
	 *
	 * @param string $_parent = null no parent node means root with the first level of folders
	 * @param string $_profileID = '' icServer id
	 * @param int|boolean $_openTopLevel = 1 Open top level folders on load if it's set to 1|true,
	 *  false|0 leaves them in closed state
	 *
	 * @return array returns an array of mail tree structure according to provided node
	 */
	function getTree ($_parent = null, $_profileID = '', $_openTopLevel = 1)
	{
		//Init mail folders
		$tree = array(tree::ID=> $_parent?$_parent:0,tree::CHILDREN => array());
		$hDelimiter = $this->ui->mail_bo->getHierarchyDelimiter();
		$fn_nodeHasChildren = function ($_node)
		{
			$hasChildren = 0;
			if (in_array('\haschildren', $_node['ATTRIBUTES']) ||
					in_array('\Haschildren', $_node['ATTRIBUTES']) ||
					in_array('\HasChildren', $_node['ATTRIBUTES'])) $hasChildren = 1;
			return $hasChildren;
		};
		
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
		
		if ($_parent) // Single node loader
		{
			try
			{
				$nodeInfo = self::getFolderData($_parent, $hDelimiter);
				$folders = $this->ui->mail_bo->getFolderArray($nodeInfo['mailbox']);
			} catch (Exception $ex) {
				return self::treeLeafNoConnectionArray($_profileID, $ex->getMessage(),array($_profileID), '');
			}
			
			$childrenNode = array();
			foreach ($folders as &$node)
			{
				$nodeId = $_profileID.self::$delimiter.$node['MAILBOX'];
				$nodeData = self::getFolderData($nodeId, $node['delimiter']);
				$childrenNode[] = array(
					tree::ID=> $nodeId,
					tree::AUTOLOAD_CHILDREN => $fn_nodeHasChildren($node),
					tree::CHILDREN =>array(),
					tree::LABEL => $nodeData['text'],
					tree::TOOLTIP => $nodeData['tooltip'],
					tree::IMAGE_LEAF => self::$leafImages['folderLeaf'],
					tree::IMAGE_FOLDER_OPEN => self::$leafImages['folderOpen'],
					tree::IMAGE_FOLDER_CLOSED => self::$leafImages['folderClose'],
					tree::CHECKED => $node['SUBSCRIBED'],
					'parent' => $_parent
				);
			}
			$tree[tree::CHILDREN] = $childrenNode;
		}
		else //Top Level Nodes loader
		{
			$baseNode = array('id' => 0);
			foreach(emailadmin_account::search(true, false) as $acc_id => $accObj)
			{
				if (!$accObj->is_imap()|| $acc_id != $_profileID) continue;
				$identity = emailadmin_account::identity_name($accObj,true,$GLOBALS['egw_info']['user']['acount_id']);
				$baseNode = array(
								tree::ID=> $acc_id,
								tree::LABEL => str_replace(array('<','>'),array('[',']'),$identity),
								tree::TOOLTIP => '('.$acc_id.') '.htmlspecialchars_decode($identity),
								tree::IMAGE_LEAF => self::$leafImages['folderAccount'],
								tree::IMAGE_FOLDER_OPEN => self::$leafImages['folderAccount'],
								tree::IMAGE_FOLDER_CLOSED => self::$leafImages['folderAccount'],
								'path'=> array($acc_id),
								tree::CHILDREN => array(), // dynamic loading on unfold
								tree::AUTOLOAD_CHILDREN => true,
								'parent' => '',
								tree::OPEN => $_openTopLevel,
								// mark on account if Sieve is enabled
								'data' => array(
											'sieve' => $accObj->imapServer()->acc_sieve_enabled,
											'spamfolder'=> $accObj->imapServer()->acc_folder_junk?true:false
										),
				);
				self::setOutStructure($baseNode, $tree,self::$delimiter);
			}
			//List of folders
			$foldersList = $this->ui->mail_bo->getFolderArray(null, true);
			
			// Parent node arrays
			$parentNode = $parentNodes = array();
			
			foreach ($foldersList as $index => $topFolder)
			{
				$parentNode = array(
					tree::ID=>$_profileID.self::$delimiter.$topFolder[$index]['MAILBOX'],
					tree::AUTOLOAD_CHILDREN => $fn_nodeHasChildren($topFolder[$index]),
					tree::CHILDREN =>array(),
					tree::LABEL =>lang($topFolder[$index]['MAILBOX']),
					tree::OPEN => $_openTopLevel,
					tree::TOOLTIP => lang($topFolder[$index]['MAILBOX']),
					tree::CHECKED => $topFolder[$index]['SUBSCRIBED']
				);
				if ($index === "INBOX")
				{
					$parentNode[tree::IMAGE_LEAF] = self::$leafImages['folderHome'];
					$parentNode[tree::IMAGE_FOLDER_OPEN] = self::$leafImages['folderHome'];
					$parentNode[tree::IMAGE_FOLDER_CLOSED] = self::$leafImages['folderHome'];
				}
				if(stripos(array2string($topFolder[$index]['ATTRIBUTES']),'\noselect')!== false)
				{
					$parentNode[tree::IMAGE_LEAF] = self::$leafImages['folderNoSelectClosed'];
					$parentNode[tree::IMAGE_FOLDER_OPEN] = self::$leafImages['folderNoSelectOpen'];
					$parentNode[tree::IMAGE_FOLDER_CLOSED] = self::$leafImages['folderNoSelectClosed'];
				}
				// Save parentNodes
				$parentNodes []= $index;
				// Remove the parent nodes from the list
				unset ($topFolder[$index]);
				
				//$parentNode[tree::CHILDREN][] =$childrenNode;
				$baseNode[tree::CHILDREN][] = $parentNode;
			}
			foreach ($parentNodes as $pIndex => $parent)
			{
				$childrenNodes = $childNode = array();
				$definedFolders = array(
					'Trash'     => $this->ui->mail_bo->getTrashFolder(false),
					'Templates' => $this->ui->mail_bo->getTemplateFolder(false),
					'Drafts'    => $this->ui->mail_bo->getDraftFolder(false),
					'Sent'      => $this->ui->mail_bo->getSentFolder(false),
					'Junk'      => $this->ui->mail_bo->getJunkFolder(false),
					'Outbox'    => $this->ui->mail_bo->getOutboxFolder(false),
				);
				// Iterate over childern of each top folder(namespaces)
				foreach ($foldersList[$parent] as &$node)
				{
					// Skipe the parent node itself
					if (is_array($foldersList[$parent][$parent]) &&
						$foldersList[$parent][$parent]['MAILBOX'] === $node['MAILBOX']) continue;
					
					$pathArr = explode($node['delimiter'], $node['MAILBOX']);
					$folderName = array_pop($pathArr);
					$parentPath = $_profileID.self::$delimiter.implode($pathArr,$node['delimiter']);
					
					$nodeId = $_profileID.self::$delimiter.$node['MAILBOX'];
			
					$childNode = array(
						tree::ID => $nodeId,
						tree::AUTOLOAD_CHILDREN => $fn_nodeHasChildren($node),
						tree::CHILDREN => array(),
						tree::LABEL => lang($folderName),
						'parent' => $parentPath,
						tree::CHECKED => $node['SUBSCRIBED']
					);
					
					if (array_search($node['MAILBOX'], $definedFolders) !== false)
					{
						//User defined folders icons
						$childNode[tree::IMAGE_LEAF] =
							$childNode[tree::IMAGE_FOLDER_OPEN] =
							$childNode [tree::IMAGE_FOLDER_CLOSED] = "MailFolder".$folderName.".png";
					}
					elseif(stripos(array2string($node['ATTRIBUTES']),'\noselect')!== false)
					{
						$childNode[tree::IMAGE_LEAF] = self::$leafImages['folderNoSelectClosed'];
						$childNode[tree::IMAGE_FOLDER_OPEN] = self::$leafImages['folderNoSelectOpen'];
						$childNode[tree::IMAGE_FOLDER_CLOSED] = self::$leafImages['folderNoSelectClosed'];
					}
					else
					{
						$childNode[tree::IMAGE_LEAF] = self::$leafImages['folderLeaf'];
						$childNode[tree::IMAGE_FOLDER_OPEN] = self::$leafImages['folderOpen'];
						$childNode[tree::IMAGE_FOLDER_CLOSED] = self::$leafImages['folderClose'];
					}
					$childrenNodes[] = $childNode;
				}
				$baseNode[tree::CHILDREN][$pIndex][tree::CHILDREN] = $childrenNodes;
			}
			$tree[tree::CHILDREN][0] = $baseNode;
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
	static function setOutStructure($data, &$out, $del='.', $createMissingParents=true, $nameSpace=array())
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
		unset($data['path']);
		$insert['item'][] = $data;
		//error_log(__METHOD__."() leaving with out=".array2string($out));
	}

}