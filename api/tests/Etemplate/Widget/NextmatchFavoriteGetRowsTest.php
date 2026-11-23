<?php

/**
 * Test file for Nextmatch::beforeSendToClient()'s favorite handling
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage etemplate
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api;
use EGroupware\Api\Etemplate;

require_once realpath(__DIR__.'/../WidgetBaseTest.php');

/**
 * get_rows is always decided server-side by the widget's own content; a stored favorite's
 * 'state' must not be able to change it, even though its other filter keys still apply.
 */
class NextmatchFavoriteGetRowsTest extends Etemplate\WidgetBaseTest
{
	const TEST_TEMPLATE = 'api.nextmatch_test';
	const FORM_NAME = 'favorite_nm';
	const PREF_APP = 'api';

	/** @var array|null last $query received by mock_get_rows() */
	private static $mock_get_rows_params;

	/** @var bool whether the favorite's own get_rows callback was called */
	private static $other_get_rows_called = false;

	/** @var mixed backup of the original $_GET['favorite'] */
	private $get_favorite_backup;

	protected function setUp() : void
	{
		parent::setUp();
		$this->get_favorite_backup = $_GET['favorite'] ?? null;
		self::$mock_get_rows_params = null;
		self::$other_get_rows_called = false;
	}

	protected function tearDown() : void
	{
		if ($this->get_favorite_backup !== null)
		{
			$_GET['favorite'] = $this->get_favorite_backup;
		}
		else
		{
			unset($_GET['favorite']);
		}
		unset($GLOBALS['egw_info']['user']['preferences'][self::PREF_APP]);
		parent::tearDown();
	}

	/**
	 * A 'get_rows' key inside a stored favorite's 'state' must never override the widget's own,
	 * server-side 'get_rows' - only other filter keys from the favorite may apply.
	 */
	public function testFavoriteCannotOverrideGetRows()
	{
		$_GET['favorite'] = 'test';
		$GLOBALS['egw_info']['user']['preferences'][self::PREF_APP]['favorite_test'] = array(
			'name'  => 'Test',
			'group' => false,
			'state' => array(
				// must be ignored - the widget's own get_rows must run instead
				'get_rows' => __CLASS__.'::other_get_rows',
				// the favorite's other content must still apply
				'search'   => 'from-favorite',
			),
		);

		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		Etemplate::reset_request();
		$etemplate = new Etemplate();
		$this->assertTrue($etemplate->read(self::TEST_TEMPLATE, 'test'),
			'could not load nextmatch test template');

		$this->mockedExec($etemplate, array(self::FORM_NAME => array(
			'get_rows'  => __CLASS__.'::mock_get_rows',
			'favorites' => true,
		)));

		$this->assertNotNull(self::$mock_get_rows_params,
			"the widget's own get_rows did not run");
		$this->assertFalse(self::$other_get_rows_called,
			"a 'get_rows' key from a stored favorite's state must never be dispatched");
		$this->assertSame('from-favorite', self::$mock_get_rows_params['search'] ?? null,
			"the favorite's other content must still be applied");
	}

	public static function mock_get_rows(&$query, &$rows, &$readonlys)
	{
		self::$mock_get_rows_params = $query;
		$rows = array();
		return 0;
	}

	/**
	 * Callback supplied only via a stored favorite's 'state'. No test may dispatch here.
	 */
	public static function other_get_rows(&$query, &$rows, &$readonlys)
	{
		self::$other_get_rows_called = true;
		$rows = array();
		return 0;
	}
}
