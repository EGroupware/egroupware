<?php
/**
 * EGroupware Mail: folder-tree account-root node builder
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Etemplate\Widget\Tree as TreeWidget;
use EGroupware\Api\Mail;
use EGroupware\Mail\Compose;
use mail_ui;

/**
 * Builds the per-account root nodes shown in the mail folder tree - the only part of the classic
 * tree-building code still needed server-side, everything below the account root is 100%
 * client-side JMAP now (mail/js/folderTree.ts/app.ts, see doc/ai/projects/mail-folder-tree-jmap.md).
 *
 * Was `mail_tree` (mail/inc/class.mail_tree.inc.php) until 2026-09-08, when everything else in
 * that class was found to be dead: `getTree()`/`setOutStructure()`/`nodeHasChildren()`/
 * `isAccountNode()`/`getNodeLevel()`/`treeLeafNoConnectionArray()` only ever existed to build the
 * deep, eager, full-account folder tree `mail_ui::subscription()`/`folderManagement()` used as a
 * "classic fallback for a non-JMAP-reachable account" - but every *other* JMAP surface in this
 * codebase had already dropped that same kind of fallback (folder-tree browsing, folder CRUD,
 * message actions - see mail-folder-tree-jmap.md's dead-code-sweep commits) on the reasoning that
 * "JMAP unreachable" means the underlying mail-server connection itself is down (JmapShim wraps
 * the exact same IMAP connection classic code would use), so a classic retry can never succeed
 * where JMAP genuinely failed - a real JmapShim/JMAP-level error is thrown as a `JmapUserError` and
 * shown directly instead of falling through to this. `subscription()`/`folderManagement()` were
 * the last 2 holdouts; removing their use of `getTree()` left this class down to just the account
 * enumeration, which is not JMAP/IMAP-conditional at all (`getAccountsRootNode()` lists every
 * configured account regardless of backend). `getIdentityName()` moved into
 * `ComposeMessageBuilder` (it's an identity-display-name formatter, not tree-structure logic, and
 * is called from there for the classic send/draft-save path too, not just from here).
 */
class Tree
{
	/**
	 * delimiter - used to separate acc_id from mailbox / folder-tree-structure
	 *
	 * @var string
	 */
	const DELIMITER = Mail::DELIMITER;

	/**
	 * Icons used for nodes different states
	 *
	 * @var array
	 */
	static $leafImages = array(
		// used by mail_ui::index()'s own catch block to mark the active account as a
		// connection-error leaf when it fails entirely - the only other real caller of this class
		'folderNoSelectClosed' => "folderNoSelectClosed",
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
				$image = Api\Image::find('mail', 'dhtmlxtree/' . $image);
			}
		}
	}

	/**
	 * Get accounts root node, fetches all or an accounts for a user
	 *
	 * @param type $_profileID = null Null means all accounts and giving profileid means fetches node for the account
	 * @param type $_noCheckbox = false option to switch checkbox of
	 * @param type $_openTopLevel = 0 option to either start the node opened (1) or closed (0)
	 * @param bool $_try_connect = true whether to live-check each account's IMAP connectivity
	 *  (Mail\Account::is_imap()'s own default) - a real login() attempt per account, which can
	 *  block for as long as that account's server takes to answer or time out (confirmed live:
	 *  ~20s for one account alone on this dev install). getInitialIndexTree() (this class's only
	 *  other caller of this method) passes false - connectivity is then verified lazily instead,
	 *  client-side, the moment that account's node is actually opened (mail/js/app.ts's
	 *  folderTreeAutoload()/buildRootFolderData() -> MailJmap.getRootFolders(), which already
	 *  renders a "Connection could not be established" error leaf on failure) - so no user-visible
	 *  check is lost, only deferred to when it's actually needed. `mail_ui::index()`'s own catch
	 *  block (the only external caller) keeps the live check, since by then something has already
	 *  gone wrong and it's specifically trying to find out what.
	 *
	 * @return array an array of baseNodes of accounts
	 */
	static function getAccountsRootNode($_profileID = null, $_noCheckbox = false, $_openTopLevel = 0, $_try_connect = true)
	{
		$roots = array(TreeWidget::ID => 0, TreeWidget::CHILDREN => array());

		foreach(Mail\Account::search(true, 'params') as $acc_id => $params)
		{
			// checking a single requested account only: skip everything else before it can
			// trigger a live is_imap() connection attempt for accounts we don't even want
			if ($_profileID && $acc_id != $_profileID) continue;

			try {
				$accObj = new Mail\Account($params);
				if (!$accObj->is_imap($_try_connect)) continue;
				$identity = Compose::getIdentityName(Mail\Account::identity_name($accObj,true, $GLOBALS['egw_info']['user']['account_id'], true));
				// Open top level folders for active account
				$openActiveAccount = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] == $acc_id?1:0;

				$baseNode = array(
					TreeWidget::ID => (string)$acc_id,
					TreeWidget::LABEL => str_replace(array('<','>'),array('[',']'),$identity),
					TreeWidget::TOOLTIP => '('.$acc_id.') '.htmlspecialchars_decode($identity),
					TreeWidget::IMAGE_LEAF => self::$leafImages['folderAccount'],
					TreeWidget::IMAGE_FOLDER_OPEN => self::$leafImages['folderAccount'],
					TreeWidget::IMAGE_FOLDER_CLOSED => self::$leafImages['folderAccount'],
					TreeWidget::CHILDREN => array(), // dynamic loading on unfold
					TreeWidget::AUTOLOAD_CHILDREN => true,
					'parent' => '',
					TreeWidget::OPEN => $_openTopLevel?:$openActiveAccount,
					// mark on account if Sieve is enabled
					'data' => array(
						'sieve' => $accObj->imapServer()->acc_sieve_enabled,
						'spamfolder'=> $accObj->imapServer()->acc_folder_junk&&(strtolower($accObj->imapServer()->acc_folder_junk)!='none')?true:false,
						'archivefolder'=> $accObj->imapServer()->acc_folder_archive&&(strtolower($accObj->imapServer()->acc_folder_archive)!='none')?true:false,
						// bare email address, eg. for mail/js/app.ts's updateFolderQuickAction() -
						// the configured identity label (this node's own TreeWidget::LABEL) may
						// contain name/org and is not always the account's email address
						'email' => $accObj->ident_email ?: $accObj->acc_imap_username,
					),
                    TreeWidget::NOCHECKBOX => $_noCheckbox,
                    TreeWidget::CLASS_LIST => 'mailAccount',
                );
			}
			catch (\Exception $ex) {
				$baseNode = array(
					TreeWidget::ID => (string)$acc_id,
					TreeWidget::LABEL => lang('Error').': '.lang($ex->getMessage()),
					TreeWidget::TOOLTIP => '('.$acc_id.') '.htmlspecialchars_decode($params['acc_name']),
					TreeWidget::IMAGE_LEAF => self::$leafImages['folderAccount'],
					TreeWidget::IMAGE_FOLDER_OPEN => self::$leafImages['folderAccount'],
					TreeWidget::IMAGE_FOLDER_CLOSED => self::$leafImages['folderAccount'],
					TreeWidget::CHILDREN => array(), // dynamic loading on unfold
					TreeWidget::AUTOLOAD_CHILDREN => false,
					'parent' => '',
					TreeWidget::OPEN => false,
					TreeWidget::NOCHECKBOX  => true
				);
			}
			// setOutStructure()'s generic parent-path-walking logic never applied to this baseNode
			// (a single-segment 'path' - now dropped entirely, see the removed 'path' key above -
			// pops to an empty $components, so its whole foreach() was always a no-op here; it
			// only ever mattered for getTree()'s deep, per-folder nesting, which no longer exists)
			$roots[TreeWidget::CHILDREN][] = $baseNode;
		}
		return $roots;
	}

	/**
	 * Initialization tree for index sidebox menu
	 *
	 * The active account's own root node is already marked open/autoload-children by
	 * getAccountsRootNode()'s $openActiveAccount logic, so its top-level folders get lazy-loaded
	 * client-side exactly like every other account's, instead of being fetched eagerly here.
	 * Et2Tree.ts's _optionTemplate() already self-triggers a lazy-load for any node rendered
	 * open+childless+autoloadable, so no client-side change is needed for this to keep working
	 * (see doc/ai/projects/mail-folder-tree-jmap.md's "active-account eager expand" note).
	 *
	 * $_try_connect=false: this renders once per index() load/reload, so a per-account live
	 * IMAP connect here blocks the whole page on however long the slowest configured account
	 * takes to answer or time out (confirmed live: one account alone added ~20s). Connectivity
	 * is checked lazily instead, client-side, the moment an account's node is actually opened -
	 * see getAccountsRootNode()'s own docblock for where that happens and how failures surface.
	 *
	 * @return array an array of tree
	 */
	function getInitialIndexTree()
	{
		return self::getAccountsRootNode(null, false, 0, false);
	}
}
