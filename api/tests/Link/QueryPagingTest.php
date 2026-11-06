<?php
/**
 * EGroupware Api: paging through Link::query() results
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Link;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;
use PHPUnit\Framework\Attributes\DataProvider;

require_once realpath(__DIR__.'/../LoggedInTest.php');

/**
 * $options['start'] is how a client asks for the next set of matches, so every page after the
 * first depends on it.
 *
 * Link::query() has to page the results of an app that does not page its own, and leave alone the
 * ones of an app that does - taking a window out of a window leaves nothing for any page but the
 * first. The two kinds are told apart by whether the app reported a total: one that returned a
 * single page has to, as the number of matches can no longer be counted from what it returned.
 *
 * Both kinds are registered here rather than relying on which apps an install happens to have.
 */
class QueryPagingTest extends LoggedInTest
{
	/**
	 * Matches both test apps answer with
	 */
	const MATCHES = 10;

	/**
	 * Every id in order, for comparing pages against
	 *
	 * @var array
	 */
	protected $expected = array();

	protected function setUp() : void
	{
		parent::setUp();

		for($n = 1; $n <= self::MATCHES; ++$n)
		{
			$this->expected[$n] = 'Entry '.$n;
		}
		$register = new \ReflectionProperty(Api\Link::class, 'app_register');
		$register->setAccessible(true);
		$apps = $register->getValue();
		$apps['test-pages-itself'] = array(
			'name'  => 'test-pages-itself',
			'query' => array(self::class, 'queryPagingItself'),
			'title' => array(self::class, 'title'),
		);
		$apps['test-pages-not'] = array(
			'name'  => 'test-pages-not',
			'query' => array(self::class, 'queryNotPaging'),
			'title' => array(self::class, 'title'),
		);
		$register->setValue(null, $apps);
	}

	/**
	 * An app that honours start/num_rows and reports how many there are, as most do
	 *
	 * @param string $pattern
	 * @param array $options
	 * @return array
	 */
	public static function queryPagingItself($pattern, array &$options = array())
	{
		unset($pattern);
		$all = array();
		for($n = 1; $n <= self::MATCHES; ++$n)
		{
			$all[$n] = 'Entry '.$n;
		}
		$options['total'] = count($all);

		return array_slice($all, (int)($options['start'] ?? 0), $options['num_rows'] ?? null, true);
	}

	/**
	 * An app that ignores them and answers with everything it found
	 *
	 * @param string $pattern
	 * @param array $options
	 * @return array
	 */
	public static function queryNotPaging($pattern, array &$options = array())
	{
		unset($pattern, $options);
		$all = array();
		for($n = 1; $n <= self::MATCHES; ++$n)
		{
			$all[$n] = 'Entry '.$n;
		}
		return $all;
	}

	/**
	 * @param int $id
	 * @return string
	 */
	public static function title($id)
	{
		return 'Entry '.$id;
	}

	public static function appProvider() : array
	{
		return array(
			'app pages itself'   => array('test-pages-itself'),
			'app does not page'  => array('test-pages-not'),
		);
	}

	/**
	 * Every page has to hold the matches it is asked for, whichever kind of app answered
	 *
	 */
	#[DataProvider('appProvider')]
	public function testEachPageHoldsItsOwnMatches($app)
	{
		$per_page = 3;
		for($start = 0; $start < self::MATCHES; $start += $per_page)
		{
			$options = array('start' => $start, 'num_rows' => $per_page);
			$page = Api\Link::query($app, '', $options);

			$this->assertSame(array_slice($this->expected, $start, $per_page, true), $page,
				"$app: the page from $start has to hold the matches from $start on");
			$this->assertSame(self::MATCHES, (int)$options['total'], "$app: total has to be all matches");
		}
	}

	/**
	 * ... including the last one, which holds fewer than a full page
	 *
	 */
	#[DataProvider('appProvider')]
	public function testTheLastPageIsShort($app)
	{
		$options = array('start' => 8, 'num_rows' => 5);
		$page = Api\Link::query($app, '', $options);

		$this->assertSame(array_slice($this->expected, 8, 5, true), $page);
		$this->assertCount(2, $page, "$app: only two matches are left from 8");
	}

	/**
	 * Asking for everything still answers with everything
	 *
	 */
	#[DataProvider('appProvider')]
	public function testWithoutAStartTheFirstPageIsAnswered($app)
	{
		$options = array('num_rows' => self::MATCHES);

		$this->assertSame($this->expected, Api\Link::query($app, '', $options));
	}

	/**
	 * The pages together are every match, each one once
	 *
	 */
	#[DataProvider('appProvider')]
	public function testThePagesHoldEveryMatch($app)
	{
		$seen = array();
		for($start = 0; $start < self::MATCHES; $start += 4)
		{
			$options = array('start' => $start, 'num_rows' => 4);
			$seen += Api\Link::query($app, '', $options);
		}
		$this->assertSame($this->expected, $seen, "$app: paging through has to see every match once");
	}
}
