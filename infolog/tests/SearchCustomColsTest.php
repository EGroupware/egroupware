<?php

/**
 * Test harness for infolog_bo::search()'s $query['cols'] path (a caller-supplied narrow column
 * list, meaning "return raw column data as-is, not the fully-hydrated info array" - used by
 * pm_icons() and infolog_groupdav's propfind_generator), written after a real regression found
 * during phase 3 of doc/ai/projects/infolog-storage-migration.md (delegating searchInfolog()'s
 * query assembly to the inherited Api\Storage::search()).
 *
 * Before phase 3, searchInfolog()'s $query['cols'] early return handed back the raw ADODB
 * recordset object straight from $this->db->query() - infolog_bo::search()'s own
 * is_array($ret) check on that object was false (it's an object, not an array), so its
 * check_access()/timestamp-conversion post-processing loop was skipped entirely for this path,
 * whether by design or accident. Once searchInfolog() started delegating to the inherited,
 * always-array-returning Api\Storage::search(), that same is_array($ret) check became true,
 * turning on check_access() filtering that was never exercised before - which then rejected
 * every row, because checkAccessGrants() needs info_owner/info_access to make its decision and
 * a narrow $cols list (by design) doesn't include them. Fixed by making infolog_bo::search()
 * skip that post-processing explicitly whenever $q['cols']/'return-iterator' is set, matching
 * searchInfolog()'s own early-return condition, instead of relying on the accidental
 * is_array()-on-an-object behavor.
 *
 * A second, unrelated bug surfaced by the same investigation: Api\Storage\Base::_get_columns()
 * doesn't strip a table-qualifier off a plain (non-aliased) column name in a $only_keys string -
 * "egw_infolog.info_id" (without "AS") ends up as a data key of literally "egw_infolog.info_id"
 * with a null value, since the actual SQL result column is just named "info_id". Fixed by
 * explicitly aliasing it ("egw_infolog.info_id AS info_id") in pm_icons()'s cols string,
 * matching the convention infolog_groupdav's propfind_generator cols string already used.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

class SearchCustomColsTest extends \EGroupware\Api\AppTest
{
	protected $bo;

	protected $info_ids = array();

	protected function setUp() : void
	{
		$this->bo = new \infolog_bo();
		$this->mockTracking($this->bo, 'infolog_tracking');
	}

	protected function tearDown() : void
	{
		foreach(array_unique($this->info_ids) as $info_id)
		{
			$this->bo->delete($info_id);
			$this->bo->delete($info_id);
		}
		$this->info_ids = array();
		$this->bo = null;
	}

	protected function makeInfolog(array $fields = array())
	{
		$info = array('info_type' => 'task', 'info_subject' => 'SearchCustomColsTest '.$this->name());
		foreach($fields as $field => $value) { $info[$field] = $value; }
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	/**
	 * pm_icons() - the projectmanager custom-app-icons hook - relies on search()'s $cols path
	 * returning its entries at all, and correctly keyed by info_id.
	 */
	public function testPmIconsColsPath()
	{
		$info_id = $this->makeInfolog();

		$result = $this->bo->pm_icons(array('infolog' => array($info_id)));

		$this->assertArrayHasKey($info_id, $result);
	}

	/**
	 * A $cols query for a "private" entry the acting user does NOT own must still be returned -
	 * check_access()'s normal READ re-check is skipped for this path (see class docblock), by
	 * design: the caller's own filtermethod (via $no_acl/$acl_filter) already scopes visibility,
	 * a narrow $cols row doesn't carry enough fields (info_owner/info_access) for check_access()
	 * to re-decide anyway.
	 */
	public function testColsPathBypassesReadAccessRecheck()
	{
		$info_id = $this->makeInfolog(array('info_access' => 'private'));

		$query = array(
			'col_filter' => array('info_id' => $info_id),
			'cols' => 'egw_infolog.info_id AS info_id,info_subject',
		);
		$ret = $this->bo->search($query);

		$rows = array_column((array)$ret, 'info_subject', 'info_id');
		$this->assertArrayHasKey($info_id, $rows);
	}
}
