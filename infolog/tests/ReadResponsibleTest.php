<?php

/**
 * Test harness for Infolog\Storage::read()'s info_responsible/info_cc hydration, written as
 * part of eliminating searchInfolog()'s row-duplicating egw_infolog_users JOINs
 * (doc/ai/projects/infolog-storage-migration.md, "eliminating searchInfolog()'s row-duplicating
 * JOINs" research) - read() used to fetch info_responsible/info_cc via a
 * LEFT JOIN+GROUP_CONCAT()/GROUP BY against egw_infolog_users; this locks down that read()
 * returns the exact same shapes (info_responsible: int[] account_ids, info_cc: comma-separated
 * string) after switching to the join-free read_responsible() helper instead.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

class ReadResponsibleTest extends \EGroupware\Api\AppTest
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
		$info = array('info_type' => 'task', 'info_subject' => 'ReadResponsibleTest '.$this->name());
		foreach($fields as $field => $value) { $info[$field] = $value; }
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	/**
	 * A single delegated-to user must come back as a one-element int[] array.
	 */
	public function testSingleResponsible()
	{
		$other = $this->bo->user == 1 ? 2 : 1;
		$info_id = $this->makeInfolog(array('info_responsible' => array($other)));

		$info = $this->bo->read($info_id);

		$this->assertIsArray($info['info_responsible']);
		$this->assertSame(array($other), $info['info_responsible']);
	}

	/**
	 * Multiple delegated-to users must all come back, none lost/duplicated - the case that
	 * would have multiplied read()'s main-table row under the old JOIN approach.
	 */
	public function testMultipleResponsible()
	{
		$others = array_values(array_diff(array(1, 2, 3), array((int)$this->bo->user)));
		$info_id = $this->makeInfolog(array('info_responsible' => $others));

		$info = $this->bo->read($info_id);

		$this->assertIsArray($info['info_responsible']);
		$this->assertSame($others, array_values(array_intersect($others, $info['info_responsible'])));
		$this->assertCount(count($others), $info['info_responsible']);
	}

	/**
	 * info_cc must come back as a comma-separated STRING, not an array - infolog_ui.inc.php and
	 * infolog_tracking.inc.php both expect that shape (explode(',', ...)/preg_split(...) on it).
	 */
	public function testCcIsCommaSeparatedString()
	{
		$info_id = $this->makeInfolog(array(
			'info_responsible' => array(1),
			'info_cc' => 'a@example.org,b@example.org',
		));

		$info = $this->bo->read($info_id);

		$this->assertIsString($info['info_cc']);
		$this->assertEqualsCanonicalizing(
			array('a@example.org', 'b@example.org'),
			explode(',', $info['info_cc'])
		);
	}

	/**
	 * No responsible/cc at all must give an empty array / empty string, not null or missing keys.
	 */
	public function testNoResponsibleOrCc()
	{
		$info_id = $this->makeInfolog();

		$info = $this->bo->read($info_id);

		$this->assertSame(array(), $info['info_responsible']);
		$this->assertSame('', $info['info_cc']);
	}
}
