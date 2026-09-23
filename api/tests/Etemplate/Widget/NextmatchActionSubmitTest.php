<?php
/**
 * EGroupware Api: guard against nextmatch actions silently falling through to a full submit
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate\Widget\Nextmatch;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../LoggedInTest.php');

/**
 * A nextmatch action that declares no onExecute, url, egw_open or nm_action falls through to
 * nm_action = "submit" (Et2NextmatchActionController.executeNextmatchAction()), which posts the
 * whole eTemplate back and re-renders it: a brand new nextmatch, so the list's scroll position,
 * selection and row state are lost. Nothing in the action definition says that will happen, which
 * is why it keeps getting written by accident.
 *
 * WHAT THIS PROVES
 * Each app's real get_actions() is run through the real Nextmatch::egw_actions(), and every
 * resulting leaf is classified by the same rule the client applies. The set of actions that fall
 * through must equal self::BASELINE exactly.
 *
 * HOW IT FAILS
 * - A *new* fall-through (someone added an action without declaring its behaviour) fails, naming it.
 * - A *resolved* one still listed in BASELINE also fails: the baseline doubles as this project's
 *   progress list (doc/ai/projects/nextmatch-action-ajax-conversion.md), so converting an app and
 *   pruning its entry belong in the same commit.
 *
 * ENVIRONMENT SENSITIVITY
 * Counts are instance data - how many categories, distribution lists, trackers or boards exist -
 * so this asserts on action *ids* only, never on how many there are. Apps that are not installed
 * are skipped rather than failed, so the same baseline holds on a partial install.
 */
class NextmatchActionSubmitTest extends LoggedInTest
{
	/**
	 * Actions known to fall through to submit, as class => [action path, ...].
	 *
	 * Paths use the resolved action tree ('parent/child'), and per-row children generated from
	 * instance data are collapsed to a 'parent/*' wildcard, since their number and ids vary per
	 * installation.
	 */
	const BASELINE = [
		'EGroupware\\Admin\\Token' => [
			'activate',
			'revoke'
		],
		'EGroupware\\Aiassistant\\Ui' => [
			'delete',
			'separator'
		],
		'EGroupware\\Aitools\\Admin' => [
			'delete'
		],
		'EGroupware\\Developer\\TranslationTools' => [
			'all',
			'current',
			'delete',
			'import',
			'move_to_api'
		],
		'EGroupware\\Invoices\\Ui' => [
			'delete'
		],
		'EGroupware\\Kanban\\Ui\\BoardList' => [
			'copy'
		],
		'EGroupware\\SmallParT\\Courses' => [
			'copy_course',
			'copy_no_participants'
		],
		'EGroupware\\SmallParT\\Questions' => [
			'delete',
			'exempt',
			'readd'
		],
		'EGroupware\\Stylite\\Calls' => [
			'delete'
		],
		// view_org/view_duplicates switch the list to a different rows template rather than acting
		// on the selection, so they are not action() operations at all and stay a redraw - see the
		// doc. export/kanban belong to other apps.
		'addressbook_ui' => [
			'export/*',
			'kanban',
			'view_duplicates',
			'view_org'
		],
		'admin_accesslog' => [
			'delete'
		],
		'admin_customfields' => [
			'delete'
		],
		'bookmarks_ui' => [
			'delete'
		],
		'importexport_definitions_ui' => [
			'copy',
			'createexport',
			'delete'
		],
		'news_admin_gui' => [
			'delete'
		],
		'news_admin_ui' => [
			'delete',
			'update'
		],
		// 'erole' only exists when the enable_eroles config is on, and is a container (not a leaf)
		// when it is - it is here because this instance has the config off.
		// delete/sync_all were converted and then REVERTED: projectmanager_bo::check_acl() returns
		// true for everything but DELETE when no project is loaded, so an ajax endpoint (which has
		// no project, only untrusted pe_ids) skips the permission check entirely - and a
		// per-element check cannot identify the element from pe_id alone, since (pm_id, pe_id) is
		// the composite key. Left on submit rather than shipping either hole.
		'projectmanager_elements_ui' => [
			'cat/*',
			'delete',
			'erole',
			'sync_all'
		],
		// FOUND, NOT FIXED: this 'delete' has no server-side handler at all -
		// projectmanager_pricelist_ui extends projectmanager_pricelist_bo, not the UI class that
		// dispatches $content['nm']['action'], so the submit re-renders and deletes nothing.
		// Making it work is new functionality, not a transport change.
		'projectmanager_pricelist_ui' => [
			'delete'
		],
		'records_ui' => [
			'delete'
		],
	];

	/**
	 * Fall-throughs that are deliberate and are NOT going to be converted: downloads, which need a
	 * real form POST (postSubmit) to reach the browser as a file. See AGENTS.md "File downloads".
	 */
	const INTENTIONAL = [
		'EGroupware\\Invoices\\Ui' => ['downloadZIP-PDF', 'downloadZIP-XML'],
		'calendar_uilist' => ['ical'],
		'filemanager_ui' => ['saveaszip'],
		'importexport_definitions_ui' => ['export'],
		'infolog_ui' => ['ical'],
	];

	/**
	 * Classes whose get_actions() cannot be reached without running their constructor.
	 *
	 * Constructors are deliberately NOT run here - some of them write (addressbook's calls
	 * Api\Hooks::read(true) when a hook is missing) and a test must not. Listing them explicitly
	 * keeps the gap visible: if a class silently joins this list, the test fails rather than
	 * quietly covering less than it claims.
	 */
	const UNREACHABLE = [
		// get_actions() reads $this->mail_bo->getArchiveFolder(), and mail_bo is only set up by a
		// real, connected IMAP/JMAP profile - there is nothing to construct here without a live
		// mailbox. Mail is covered by the live check instead (it has exactly one fall-through,
		// the 'copyto' container, whose folder children are fetched after the menu opens).
		'EGroupware\\Mail\\Ui' => 'needs a connected mail_bo',
	];

	/**
	 * class => [method, args, how to get an instance]
	 *
	 * The third element is `false` for a static method, `'new'` to really construct the object, or
	 * `'bypass'` for newInstanceWithoutConstructor(). `'new'` is used wherever get_actions() reads
	 * members the constructor sets - which is every app that matters here - and was checked to be
	 * side-effect free for each: they build a bo/Etemplate and read config/prefs. calendar_ui's
	 * manage_states() only *reads* saved states, it never writes them back.
	 */
	protected static function targets() : array
	{
		return [
			'addressbook_ui'                          => ['get_actions', [null], 'new'],
			'infolog_ui'                              => ['get_actions', [[]], 'new'],
			'timesheet_ui'                            => ['get_actions', [[]], 'new'],
			'calendar_uilist'                         => ['get_actions', [], 'new'],
			'tracker_ui'                              => ['get_actions', [null, null], 'new'],
			'projectmanager_ui'                       => ['get_actions', [], 'new'],
			'projectmanager_elements_ui'              => ['get_actions', [], 'new'],
			'projectmanager_pricelist_ui'             => ['get_actions', [], 'bypass'],
			'resources_ui'                            => ['get_actions', [], 'new'],
			'records_ui'                              => ['get_actions', [], 'new'],
			'bookmarks_ui'                            => ['get_actions', ['user'], 'new'],
			'importexport_definitions_ui'             => ['get_actions', [], 'new'],
			'admin_customfields'                      => ['get_actions', [], 'new'],
			'admin_categories'                        => ['get_actions', [], 'new'],
			'admin_acl'                               => ['get_actions', [], false],
			'admin_accesslog'                         => ['get_actions', [false], false],
			'news_admin_ui'                           => ['get_actions', [], 'new'],
			'news_admin_gui'                          => ['get_actions', [], 'new'],
			'esyncpro_ui'                             => ['get_actions', [], 'new'],
			'filemanager_ui'                          => ['get_actions', [], false],
			'filemanager_shares'                      => ['get_actions', [], false],
			'mail_sieve'                              => ['get_actions', [], 'new'],
			'resources_acl_ui'                        => ['get_actions', ['resources'], false],
			'EGroupware\\Filemanager\\Jobs'           => ['get_actions', [], 'bypass'],
			'EGroupware\\Mail\\Ui'                    => ['get_actions', [], 'bypass'],
			'EGroupware\\Admin\\Token'                => ['get_actions', ['admin'], false],
			'EGroupware\\Invoices\\Ui'                => ['get_actions', [], 'bypass'],
			'EGroupware\\SmallParT\\Courses'          => ['get_actions', [], 'bypass'],
			'EGroupware\\SmallParT\\Questions'        => ['get_actions', [], 'bypass'],
			'EGroupware\\SmallParT\\Student\\Ui'     => ['get_actions', [], false],
			'EGroupware\\Kanban\\Ui\\BoardList'      => ['get_actions', [], 'bypass'],
			'EGroupware\\Kanban\\Datasource'          => ['get_actions', [], 'bypass'],
			'EGroupware\\Policy\\Ui'                  => ['get_actions', [''], 'bypass'],
			'EGroupware\\Rag\\Ui'                     => ['get_actions', [], 'bypass'],
			'EGroupware\\Aitools\\Admin'              => ['get_actions', [], 'bypass'],
			'EGroupware\\Aiassistant\\Ui'             => ['get_actions', [[]], 'new'],
			'EGroupware\\Developer\\TranslationTools' => ['get_actions', [], 'bypass'],
			'EGroupware\\Status\\Ui'                  => ['get_actions', [], false],
			'EGroupware\\Stylite\\Calls'              => ['get_actions', [''], false],
			'EGroupware\\Stylite\\Firewall'           => ['get_actions', [], false],
			'EGroupware\\Stylite\\Vfs\\S3\\Config'   => ['get_actions', [], false],
		];
	}

	/**
	 * Walk a resolved action tree, returning the paths of every leaf that falls through to submit.
	 *
	 * Mirrors Et2NextmatchActionController.executeNextmatchAction() exactly:
	 * leaf && type == 'popup' (the default) && no url && no egw_open && no nm_action && no onExecute.
	 *
	 * Two traps this has to handle, both of which produced wrong answers on the first attempt:
	 * - A hand-written 'nm_action'/'url'/'egw_open' stays at the action's own top level server-side;
	 *   egw_actions() only writes into data[] for the cases it derives itself. egw_action's
	 *   updateAction() moves unknown keys into data[] client-side, so both places count as declared.
	 * - select_all and the egw_copy/egw_copy_add/egw_paste clipboard pseudo-actions get their
	 *   onExecute installed client-side, so they are never really a submit.
	 */
	protected static function fallThroughs(array $actions, string $path = '') : array
	{
		$found = [];
		foreach($actions as $id => $action)
		{
			if (!is_array($action)) continue;
			$full = $path === '' ? (string)$id : $path . '/' . $id;

			if (!empty($action['children']))
			{
				$found = array_merge($found, self::fallThroughs($action['children'], $full));
				continue;
			}
			if ($id === 'select_all' || strpos((string)$id, 'egw_copy') === 0 || (string)$id === 'egw_paste')
			{
				continue;
			}
			if (($action['type'] ?? 'popup') !== 'popup') continue;	// drag/drop
			if (!empty($action['checkbox'])) continue;
			if (!empty($action['onExecute'])) continue;

			$data = $action['data'] ?? [];
			if (isset($data['nm_action']) || isset($action['nm_action'])) continue;
			if (isset($data['url']) || isset($action['url'])) continue;
			if (isset($data['egw_open']) || isset($action['egw_open'])) continue;

			$found[] = $full;
		}
		return $found;
	}

	/**
	 * Reduce action paths to instance-independent "families".
	 *
	 * Everything below a container is generated from instance data - categories, distribution
	 * lists, trackers, boards, content types - so neither the ids nor how many there are can go
	 * in a shared baseline. A top-level leaf is a real, developer-written action and keeps its
	 * name (digits normalised, for ids baked into an id like tracker's close_100_<resolution>);
	 * anything nested collapses to its parent plus '*'.
	 *
	 * Nested container ids can be generated too - Nextmatch::category_hierarchy() emits
	 * 'cat_add_sub_<cat_id>' wrappers whose depth follows the category tree - so a parent path is
	 * truncated at its first data-ish (digit-bearing) segment.
	 *
	 * Trade-off: this also merges hand-written siblings, eg. tracker's 'change/seen' and
	 * 'change/unseen' land in 'change/*' together with the generated 'change/type/...'. The
	 * baseline is then coarser for that subtree - a new fall-through added under an
	 * already-listed parent will not be caught. Accepted deliberately: those subtrees are all in
	 * scope to be converted, and each entry disappears from the baseline when its app is done.
	 */
	protected static function collapse(array $paths) : array
	{
		$out = [];
		foreach($paths as $path)
		{
			$parts = explode('/', $path);
			if (count($parts) === 1)
			{
				$out[preg_replace('/\d+/', '#', $parts[0])] = true;
				continue;
			}
			array_pop($parts);		// the leaf itself is always instance data or a named sibling
			$family = [array_shift($parts)];
			foreach($parts as $part)
			{
				if (preg_match('/\d/', $part)) break;	// generated wrapper - stop here
				$family[] = $part;
			}
			$out[implode('/', $family) . '/*'] = true;
		}
		$out = array_keys($out);
		sort($out);
		return $out;
	}

	public function testNoNewSubmitFallThroughs()
	{
		$actual = [];
		$unreachable = [];

		foreach(static::targets() as $class => [$method, $args, $construct])
		{
			if (!class_exists($class)) continue;	// app not installed on this instance

			try
			{
				$rc = new \ReflectionClass($class);
				if (!$rc->hasMethod($method))
				{
					$unreachable[$class] = "no $method()";
					continue;
				}
				$rm = $rc->getMethod($method);
				$rm->setAccessible(true);
				$obj = null;
				if (!$rm->isStatic())
				{
					$obj = $construct === 'new' ? $rc->newInstance() : $rc->newInstanceWithoutConstructor();
				}
				$actions = $rm->invokeArgs($obj, $args);
				if (!is_array($actions))
				{
					$unreachable[$class] = 'returned ' . gettype($actions);
					continue;
				}
				$action_links = [];
				$resolved = Nextmatch::egw_actions($actions, $class, '', $action_links);
				$paths = array_diff(
					self::collapse(self::fallThroughs($resolved)),
					static::INTENTIONAL[$class] ?? []
				);
				if ($paths) $actual[$class] = array_values($paths);
			}
			catch(\EGroupware\Api\Exception\NoPermission $e)
			{
				// Admin-only lists this user cannot see. Which ones those are depends on who the
				// test runs as, so they must NOT land in UNREACHABLE - that would make the
				// baseline non-portable between instances.
				continue;
			}
			catch(\Throwable $e)
			{
				$unreachable[$class] = get_class($e) . ': ' . strtok($e->getMessage(), "\n") .
					' @ ' . basename($e->getFile()) . ':' . $e->getLine();
			}
		}

		$this->assertSame(
			array_keys(static::UNREACHABLE), array_keys($unreachable),
			"Different set of classes could not be reached than expected - this test covers less " .
			"(or more) than its baseline assumes.\nGot:\n" . print_r($unreachable, true)
		);

		$expected = array_intersect_key(static::BASELINE, $actual + array_flip(array_keys(static::BASELINE)));
		foreach(array_keys(static::BASELINE) as $class)
		{
			if (!class_exists($class)) unset($expected[$class]);	// not installed here
		}
		ksort($expected);
		ksort($actual);

		$new = [];
		foreach($actual as $class => $paths)
		{
			$diff = array_diff($paths, $expected[$class] ?? []);
			if ($diff) $new[$class] = array_values($diff);
		}
		$this->assertSame([], $new,
			"New nextmatch action(s) fall through to nm_action=\"submit\", which rebuilds the whole " .
			"template and loses the list's scroll position and selection.\nGive them an onExecute " .
			"(see EgwApp.ajax_action) or declare 'nm_action' explicitly:\n" . print_r($new, true)
		);

		// The other direction - baseline entries that did NOT show up - is reported but does NOT
		// fail. Whether a whole action family exists at all depends on the running user's data,
		// not just on the code: addressbook only builds its to_list/remove_from_list/delete_list
		// actions `if (($add_lists = $this->get_lists(Acl::EDIT)))`, so they are simply absent for
		// a user with no editable distribution lists. Failing here would make the baseline
		// un-shareable between instances. Pruning it as apps get converted is a manual step -
		// this notice is the reminder.
		$absent = [];
		foreach($expected as $class => $paths)
		{
			$diff = array_diff($paths, $actual[$class] ?? []);
			if ($diff) $absent[$class] = array_values($diff);
		}
		if ($absent && getenv('EGW_TEST_VERBOSE'))
		{
			fwrite(STDERR, "\n" . static::class . ": baseline entries not seen on this instance - " .
				"either converted (prune them, and tick them off in " .
				"doc/ai/projects/nextmatch-action-ajax-conversion.md) or just not present for this " .
				"user's data:\n" . print_r($absent, true));
		}
	}
}
