<?php

/**
 * Regression test for infolog_bo::link_query() / searchInfolog() ignoring num_rows when no
 * 'start' is given (ticket #124531): the Link widget's autocomplete search
 * (Etemplate\Widget\Link::ajax_link_search()) always sets $options['num_rows'] but never
 * $options['start']. infolog_bo::link_query() forwards both into $query as-is, so $query['start']
 * ends up null. searchInfolog() used `isset($query['start']) ? (int)$query['num_rows'] : -1` to
 * decide the SQL row limit - since isset() is false for a null value, the limit silently became
 * -1 (unlimited) whenever only num_rows was given. A broad search pattern against a long-lived
 * InfoLog table then fetched every matching row into memory, exhausting PHP's memory limit
 * (reported as a sporadic 500 error while searching InfoLog links).
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

class LinkQueryNumRowsLimitTest extends \EGroupware\Api\AppTest
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
			$this->bo->delete($info_id);	// second one purges it
		}
		$this->info_ids = array();
		$this->bo = null;
	}

	protected function makeInfolog($subject)
	{
		$info = array(
			'info_type'    => 'task',
			'info_subject' => $subject,
		);
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	/**
	 * link_query() with only num_rows set (no 'start', exactly like
	 * Etemplate\Widget\Link::ajax_link_search()) must still cap the number of rows returned.
	 */
	public function testLinkQueryRespectsNumRowsWithoutStart()
	{
		$pattern = 'LinkQueryNumRowsLimitTest-' . uniqid();
		$total_created = 5;
		for ($i = 0; $i < $total_created; $i++)
		{
			$this->makeInfolog($pattern . ' entry ' . $i);
		}

		// exactly what Etemplate\Widget\Link::ajax_link_search() passes on to Api\Link::query() -->
		// infolog_bo::link_query(): num_rows set, start NOT set at all.
		// "legacy:" prefix forces the plain SQL-LIKE search path (bypassing this dev instance's
		// RAG semantic search, which has its own internal cap and wouldn't exercise this bug, and
		// whose fuzzy matching against freshly-created, not-yet-embedded rows is non-deterministic
		// anyway) - matches the ticket's "reproduces in both legacy and full-text mode" report.
		$options = array('num_rows' => 2);

		$content = $this->bo->link_query('legacy:'.$pattern, $options);

		$this->assertCount(2, $content,
			'link_query() must cap the result to num_rows even when start was never set');
		$this->assertEquals($total_created, $options['total'],
			'The reported total must still be the real (unlimited) match count');
	}
}
