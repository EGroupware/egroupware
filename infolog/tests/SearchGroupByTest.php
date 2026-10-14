<?php

/**
 * Test harness for infolog_bo::search()'s $query[append], the GROUP BY belonging to a
 * $query[cols] that selects aggregates.
 *
 * Every other app's entries are grouped by handing a GROUP BY to Api\Storage\Base::search()'s
 * positional $order_by, which appends it verbatim. infolog_bo::search() takes a nextmatch-style
 * $query array instead and had no way to say it at all: a caller's GROUP BY was dropped without
 * a word, the query ran ungrouped, and MySQL answered it as one implicit group - a single row,
 * COUNT(*) over everything, with the grouped column taken from an arbitrary row. A caller had no
 * way to tell that apart from a real result.
 *
 * The fragment can not be validated the way an ORDER BY can (Api\Storage\Base::sanitizeOrderBy()
 * rejects every GROUP BY for exactly that reason, and a grouping caller has to switch it off), so
 * only a plain list of column names is accepted and anything else is ignored.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

use EGroupware\Api;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

class SearchGroupByTest extends \EGroupware\Api\AppTest
{
	/**
	 * Start dates of the entries created, as year => how many
	 */
	const YEARS = array('2021' => 2, '2023' => 1);

	protected $bo;

	protected $info_ids = array();

	protected function setUp() : void
	{
		$this->bo = new \infolog_bo();
		$this->mockTracking($this->bo, 'infolog_tracking');

		foreach(self::YEARS as $year => $num)
		{
			for($n = 0; $n < $num; ++$n)
			{
				$this->makeInfolog($year.'-0'.($n+3).'-01');
			}
		}
	}

	protected function tearDown() : void
	{
		foreach(array_unique($this->info_ids) as $info_id)
		{
			// the first delete() only flags the entry as deleted, see infolog_bo::delete()
			$this->bo->delete($info_id);
			$this->bo->delete($info_id);
		}
		$this->info_ids = array();
		$this->bo = null;
	}

	protected function makeInfolog($startdate)
	{
		$info = array(
			'info_type'      => 'task',
			'info_subject'   => 'SearchGroupByTest '.$this->name(),
			'info_owner'     => $this->bo->user,
			'info_startdate' => Api\DateTime::to($startdate, 'ts'),
		);
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);

		return $info_id;
	}

	/**
	 * Count the entries created here per year of their start date
	 *
	 * @param string $append =null what to group by, default the start date's year
	 * @return array rows as search() answered with them
	 */
	protected function countPerYear($append = 'GROUP BY hash')
	{
		$query = array(
			'filter'     => 'none',
			'col_filter' => array('info_id' => $this->info_ids),
			'cols'       => array(
				$this->bo->so->db->date_format($this->bo->so->db->from_unixtime('info_startdate'), '%Y').' AS hash',
				'COUNT(*) AS num',
			),
			'append'     => $append,
			'order'      => 'hash',
			'sort'       => 'ASC',
		);
		return $this->bo->search($query);
	}

	/**
	 * Each year is its own row, holding the entries started in it
	 */
	public function testEntriesAreCountedPerGroup()
	{
		$per_year = array();
		foreach($this->countPerYear() as $row)
		{
			$per_year[(string)$row['hash']] = (int)$row['num'];
		}

		$this->assertSame(array_map('intval', self::YEARS), $per_year);
	}

	/**
	 * Without a GROUP BY the same query is one implicit group - which is what every grouping
	 * caller used to get, whatever it asked for
	 */
	public function testWithoutAnAppendEverythingIsOneGroup()
	{
		$rows = $this->countPerYear(null);

		$this->assertCount(1, $rows);
		$this->assertSame(array_sum(self::YEARS), (int)$rows[0]['num']);
	}

	/**
	 * Anything but a plain list of columns is ignored, leaving the query ungrouped rather than
	 * running a fragment nothing has validated
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('notAColumnListProvider')]
	public function testOnlyAColumnListIsGroupedBy($append)
	{
		$rows = $this->countPerYear($append);

		$this->assertCount(1, $rows, var_export($append, true).' must not reach the query');
		$this->assertSame(array_sum(self::YEARS), (int)$rows[0]['num']);
	}

	public static function notAColumnListProvider() : array
	{
		return array(
			'having'      => array('GROUP BY hash HAVING num>0'),
			'subquery'    => array('GROUP BY (SELECT 1)'),
			'union'       => array('GROUP BY hash UNION SELECT info_subject FROM egw_infolog'),
			'comment'     => array('GROUP BY hash -- '),
			'quoted'      => array("GROUP BY 'hash'"),
			'order by'    => array('ORDER BY hash'),
			'bare column' => array('hash'),
			'not a string'=> array(array('GROUP BY hash')),
		);
	}
}
