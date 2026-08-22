<?php

/**
 * Test file for Nextmatch::ajax_get_rows()
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
 * ajax_get_rows() requires $form_name to resolve to a real nextmatch/historylog
 * widget in the current template before trusting any of the filters sent with it -
 * unless $form_name is registered in Nextmatch::$raw_form_names, which some apps use
 * for views that fetch rows via egw.dataFetch() without a backing widget (eg.
 * calendar's day/week/month/planner views, which pass the app name as $form_name).
 * In both cases, the actual get_rows callback that gets called must always come from
 * the server side (either the widget's own content, or this allowlist) - a client-
 * supplied 'get_rows' filter must never be able to pick the callback.
 */
class NextmatchTest extends Etemplate\WidgetBaseTest
{
	/**
	 * @var array|null backup of Nextmatch::$raw_form_names, restored in tearDown
	 */
	private $raw_form_names_backup;

	/**
	 * @var array|null last $query received by mock_get_rows()
	 */
	private static $mock_get_rows_params;

	protected function tearDown(): void
	{
		if ($this->raw_form_names_backup !== null)
		{
			$ref = new \ReflectionProperty(Nextmatch::class, 'raw_form_names');
			$ref->setAccessible(true);
			$ref->setValue(null, $this->raw_form_names_backup);
			$this->raw_form_names_backup = null;
		}
		parent::tearDown();
	}

	/**
	 * A form_name that resolves neither to a widget nor to a registered raw
	 * form-name must still be rejected outright.
	 */
	public function testAjaxGetRowsRejectsUnresolvableFormName()
	{
		$request = Etemplate\Request::read();
		$exec_id = $request->id();
		$form_name = 'phpunit_unresolvable_'.bin2hex(random_bytes(4));
		$request->content = array($form_name => array());
		unset($request);

		$this->expectException(\InvalidArgumentException::class);
		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(), $form_name);
	}

	/**
	 * A registered "raw" (non-widget) form_name must dispatch to its server-side
	 * registered get_rows callback, even if the client sends its own 'get_rows'
	 * filter alongside it - the client-supplied value must be ignored.
	 */
	public function testAjaxGetRowsUsesRegisteredCallbackForRawFormName()
	{
		$form_name = 'phpunit_raw_'.bin2hex(random_bytes(4));

		$ref = new \ReflectionProperty(Nextmatch::class, 'raw_form_names');
		$ref->setAccessible(true);
		$this->raw_form_names_backup = $ref->getValue();
		$ref->setValue(null, $this->raw_form_names_backup + array(
			$form_name => __CLASS__.'::mock_get_rows',
		));

		$request = Etemplate\Request::read();
		$exec_id = $request->id();
		$request->content = array($form_name => array());
		unset($request);

		$filters = array(
			// must be ignored - the registered callback above must run instead
			'get_rows' => '.EGroupware\\Api\\Accounts.save',
		);

		self::$mock_get_rows_params = null;
		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), $filters, $form_name);

		$this->assertNotNull(self::$mock_get_rows_params, 'registered raw get_rows callback was not called');
		$this->assertArrayNotHasKey('account_id', self::$mock_get_rows_params ?? array(),
			'client-supplied filter data leaked into the registered callback beyond what it should see');

		$response = $this->ajax_response->returnResult();
		$data = null;
		foreach ($response as $command)
		{
			if ($command['type'] == 'data')
			{
				$data = $command['data'];
				break;
			}
		}
		$this->assertEquals(1, $data['total'] ?? null);
	}

	/**
	 * Stand-in get_rows callback, used as the registered target in
	 * testAjaxGetRowsUsesRegisteredCallbackForRawFormName()
	 */
	public static function mock_get_rows(&$query, &$rows, &$readonlys)
	{
		self::$mock_get_rows_params = $query;
		$rows = array(array('id' => 1));
		return 1;
	}
}
