<?php
/**
 * EGroupware importexport: tests for importexport_widget_filter
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/Etemplate/WidgetBaseTest.php');

/**
 * Tests for importexport_widget_filter's two main entry points:
 *
 * - validate() drops link-type filter values (an array with an 'app' key) that carry an
 *   app but no (or an empty) entry id - the shape produced when a user picks an app in a
 *   link filter but never selects an entry. Saving that partial value used to reach
 *   downstream export/search code as a truthy 'app' with no usable id, causing bogus
 *   filtering.
 * - beforeSendToClient() turns the app-supplied 'fields' attribute (a customfields-style
 *   field-type => field description map) into the actual client widget attributes/
 *   sel_options for each field, with per-type quirks (date/date-time collapse into a
 *   single 'date-range' widget, select-cat sets an extra 'application' attribute, etc).
 *
 * Both are exercised directly (not through a full etemplate round-trip), since they're
 * fairly self-contained methods. Both still need a logged-in EGroupware session though:
 * merely loading EGroupware\Api\Etemplate\Widget runs a top-level Widget::scanForWidgets()
 * (see api/src/Etemplate/Widget.php) which reads the app list from the database - there is
 * no way to touch this widget class at all without a DB-backed session, hence extending
 * WidgetBaseTest (which extends Api\LoggedInTest) rather than plain TestCase.
 *
 * Pass criteria per test: documented on each method.
 */
class ImportexportWidgetFilterTest extends \EGroupware\Api\Etemplate\WidgetBaseTest
{
	/** @var mixed snapshot of the class's own static $transformation, restored in tearDown */
	private $transformationBackup;

	protected function setUp(): void
	{
		parent::setUp();

		// beforeSendToClient()'s "no fields" fallback overwrites this static property
		// (shared across every importexport_widget_filter instance in this process) -
		// snapshot/restore it so that test doesn't leak into any other test's
		// expectations, regardless of run order.
		$prop = new ReflectionProperty(importexport_widget_filter::class, 'transformation');
		$this->transformationBackup = $prop->getValue();
	}

	protected function tearDown(): void
	{
		$prop = new ReflectionProperty(importexport_widget_filter::class, 'transformation');
		$prop->setValue(null, $this->transformationBackup);

		parent::tearDown();
	}

	/**
	 * Build a filter widget with a fixed id, matching how the etemplate uses it
	 * (eg. <filter id="set_filter"/> for the saved-filter definition editor).
	 */
	private function createWidget(string $id = 'set_filter'): importexport_widget_filter
	{
		return new importexport_widget_filter('<filter id="'.$id.'"/>');
	}

	/**
	 * Build a filter widget, set the field-type map beforeSendToClient() reads (via
	 * self::$request->content[$form_name]['fields'] - NOT a widget attribute, despite
	 * being defined much like one), plus any real XML attributes (eg. relative_dates,
	 * which IS read from $this->attrs), then call beforeSendToClient() and return the
	 * widget for inspection via getElementAttribute($form_name.'[...]', $attr).
	 */
	private function sendFields(array $fields, array $extra_attrs = array()): importexport_widget_filter
	{
		$widget = $this->createWidget();
		$widget->attrs = $extra_attrs;

		$requestProp = new ReflectionProperty(EGroupware\Api\Etemplate\Widget::class, 'request');
		$request = $requestProp->getValue();
		$content = (array)$request->content;
		$content['set_filter'] = array('fields' => $fields);
		$request->content = $content;

		$widget->beforeSendToClient('');

		return $widget;
	}

	/**
	 * Reads self::$request->sel_options[$key] via reflection, since Widget exposes no
	 * public accessor for it (it's written directly onto the shared static $request).
	 */
	private function getSelOptions(string $key)
	{
		$prop = new ReflectionProperty(EGroupware\Api\Etemplate\Widget::class, 'request');
		$request = $prop->getValue();

		return $request->sel_options[$key] ?? null;
	}

	/**
	 * A link-type value with 'app' chosen but no entry id (missing entirely, or an
	 * empty string) must not survive validation - this is the exact shape that caused
	 * the original bug (an app picked in a link filter, but no entry selected).
	 */
	public function testPartialLinkValueWithMissingIdIsDropped()
	{
		$widget = $this->sendFields(array('info_contact' => array('type' => 'addressbook')));
		$content = array('set_filter' => array(
			'info_contact' => array('app' => 'addressbook'),
		));
		$validated = array();

		$widget->validate('', array(), $content, $validated);

		$this->assertArrayNotHasKey('info_contact', $validated['set_filter'] ?? array(),
			'a link filter with an app but no id at all must be dropped');
	}

	public function testPartialLinkValueWithEmptyStringIdIsDropped()
	{
		$widget = $this->sendFields(array('info_contact' => array('type' => 'addressbook')));
		$content = array('set_filter' => array(
			'info_contact' => array('app' => 'addressbook', 'id' => ''),
		));
		$validated = array();

		$widget->validate('', array(), $content, $validated);

		$this->assertArrayNotHasKey('info_contact', $validated['set_filter'] ?? array(),
			'a link filter with an app and an empty-string id must be dropped');
	}

	/**
	 * A complete link value (app + a real, non-empty id) must survive validation
	 * completely unchanged.
	 *
	 * validate() only accepts keys present in the 'customfields' allowlist that
	 * beforeSendToClient() computes and stores server-side (see class doc there) - so,
	 * like a real submit, this test must render the field via sendFields() first to
	 * populate that allowlist, or validate() drops the value as an unknown field before
	 * ever reaching the link-completeness check this test is about.
	 */
	public function testCompleteLinkValueSurvivesUnchanged()
	{
		$widget = $this->sendFields(array('info_contact' => array('type' => 'addressbook')));
		$link = array('app' => 'addressbook', 'id' => 123);
		$content = array('set_filter' => array(
			'info_contact' => $link,
		));
		$validated = array();

		$widget->validate('', array(), $content, $validated);

		$this->assertSame($link, $validated['set_filter']['info_contact'] ?? null,
			'a complete link filter value must pass through validate() unchanged');
	}

	/**
	 * Non-link values must be unaffected by the link-completeness guard, proving that
	 * the array_key_exists('app', $value) check doesn't over-match: a plain scalar
	 * (eg. a select filter value) and a date-range-shaped array (no 'app' key) must both
	 * survive untouched.
	 *
	 * As above, both fields must first go through sendFields() so they're on the
	 * server-computed 'customfields' allowlist validate() now requires.
	 */
	public function testNonLinkValuesPassThroughUnchanged()
	{
		$widget = $this->sendFields(array(
			'info_type' => array('type' => 'select', 'values' => array('task' => 'Task')),
			'info_startdate' => array('type' => 'date'),
		));
		$range = array('from' => '2026-01-01', 'to' => '2026-01-31');
		$content = array('set_filter' => array(
			'info_type' => 'task',
			'info_startdate' => $range,
		));
		$validated = array();

		$widget->validate('', array(), $content, $validated);

		$this->assertSame('task', $validated['set_filter']['info_type'] ?? null,
			'a plain scalar filter value must pass through unchanged');
		$this->assertSame($range, $validated['set_filter']['info_startdate'] ?? null,
			'a date-range array with no "app" key must pass through unchanged, proving the '.
			'link guard only matches on array_key_exists("app", $value)');
	}

	/**
	 * Edge case: id === 0 or '0'. empty() treats both the same as a missing id, so today
	 * these are dropped exactly like the missing/empty-string cases. This is correct: every
	 * EGroupware entity table uses an auto_increment primary key starting at 1, and id 0 is
	 * used elsewhere in this codebase (eg. infolog_export_csv's own truthiness check on
	 * $link_filters['linked']['id']) to mean "not set" - so no real link entity can ever
	 * legitimately have id 0. This test locks in that (correct) behaviour rather than
	 * asserting it should change.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('zeroIdProvider')]
	public function testZeroIdIsTreatedAsEmpty($id)
	{
		$widget = $this->sendFields(array('info_contact' => array('type' => 'addressbook')));
		$content = array('set_filter' => array(
			'info_contact' => array('app' => 'addressbook', 'id' => $id),
		));
		$validated = array();

		$widget->validate('', array(), $content, $validated);

		$this->assertArrayNotHasKey('info_contact', $validated['set_filter'] ?? array(),
			'id 0 (int or string) has no valid real-world entity, so it must be treated as empty, same as a missing id');
	}

	public static function zeroIdProvider()
	{
		return array(
			'int 0' => array(0),
			'string "0"' => array('0'),
		);
	}

	/**
	 * A 'date' (or 'date-time') field is turned into a single 'date-range' widget, with
	 * the widget's relative_dates attribute copied onto it, and a default emptyLabel of
	 * the translated 'All...' when the field gave no empty_label of its own.
	 */
	public function testDateFieldBecomesDateRangeWithDefaultEmptyLabel()
	{
		$widget = $this->sendFields(
			array('due' => array('type' => 'date')),
			array('relative_dates' => array('Today' => array(0, 0, 0, 0, 0, 0, 1, 0)))
		);

		$this->assertSame(array('Today' => array(0, 0, 0, 0, 0, 0, 1, 0)),
			$widget->getElementAttribute('set_filter[due]', 'relative'));
		$this->assertSame(lang('All...'), $widget->getElementAttribute('set_filter[due]', 'emptyLabel'));
	}

	/**
	 * A field-supplied empty_label is used as-is (not translated again via lang()) for
	 * the emptyLabel attribute.
	 */
	public function testDateFieldUsesGivenEmptyLabel()
	{
		$widget = $this->sendFields(
			array('due' => array('type' => 'date-time', 'empty_label' => 'Whenever'))
		);

		$this->assertSame('Whenever', $widget->getElementAttribute('set_filter[due]', 'emptyLabel'));
	}

	/**
	 * A plain 'select' field gets multiple/tags defaulted to true and empty_label
	 * defaulted to '' when the field doesn't specify its own, and its (translated)
	 * values are published as sel_options for the client.
	 */
	public function testSelectFieldGetsDefaultsAndSelOptions()
	{
		// Deliberately not real words like "Open"/"Closed" - beforeSendToClient() runs
		// each value through lang(), and a live session's translation catalog may
		// already define an unrelated phrase under one of those keys (observed:
		// "Closed" came back as "closed" via lang()'s case-insensitive fallback
		// lookup) - use values with no plausible catalog match instead, so this test
		// only exercises the "no translation found, value passed through as-is" path.
		$widget = $this->sendFields(
			array('status' => array('type' => 'select', 'values' => array('1' => 'StatusOpenXyz', '2' => 'StatusClosedXyz')))
		);

		$this->assertTrue($widget->getElementAttribute('set_filter[status]', 'multiple'));
		$this->assertTrue($widget->getElementAttribute('set_filter[status]', 'tags'));
		$this->assertSame('', $widget->getElementAttribute('set_filter[status]', 'empty_label'));
		$this->assertSame(array(1 => 'StatusOpenXyz', 2 => 'StatusClosedXyz'), $this->getSelOptions('status'));
	}

	/**
	 * An explicit multiple/tags/empty_label on the field overrides the defaults.
	 */
	public function testSelectFieldExplicitOptionsOverrideDefaults()
	{
		$widget = $this->sendFields(
			array('status' => array(
				'type' => 'select',
				'values' => array('1' => 'Open'),
				'multiple' => false,
				'tags' => false,
				'empty_label' => 'All...',
			))
		);

		$this->assertFalse($widget->getElementAttribute('set_filter[status]', 'multiple'));
		$this->assertFalse($widget->getElementAttribute('set_filter[status]', 'tags'));
		$this->assertSame('All...', $widget->getElementAttribute('set_filter[status]', 'empty_label'));
	}

	/**
	 * A "please select" placeholder option (a non-empty label at the '' key, eg.
	 * "Select...") is dropped before the values reach sel_options - the client's own
	 * empty_label attribute (tested above) is what supplies that placeholder instead.
	 */
	public function testSelectFieldDropsEmptyPlaceholderOption()
	{
		$widget = $this->sendFields(
			array('status' => array('type' => 'select', 'values' => array('' => 'Select...', '1' => 'StatusOpenXyz')))
		);

		$sel_options = $this->getSelOptions('status');
		$this->assertArrayNotHasKey('', $sel_options,
			'a non-empty placeholder label at the "" key must be stripped before becoming sel_options');
		$this->assertSame('StatusOpenXyz', $sel_options[1]);
	}

	/**
	 * select-cat additionally sets an 'application' attribute (used by the client's
	 * category widget to know which app's categories to offer), on top of the same
	 * select defaults every other select-like type gets (case 'select-cat' falls
	 * through into the shared 'select'/default handling).
	 */
	public function testSelectCatSetsApplicationAttribute()
	{
		$widget = $this->sendFields(
			array('pm_id' => array('type' => 'select-cat', 'application' => 'projectmanager'))
		);

		$this->assertSame('projectmanager', $widget->getElementAttribute('set_filter[pm_id]', 'application'));
		// Still gets the shared select defaults via fallthrough.
		$this->assertTrue($widget->getElementAttribute('set_filter[pm_id]', 'multiple'));
	}

	/**
	 * When the widget's 'fields' attribute isn't an array at all (eg. never set), there
	 * is nothing to build filters for - beforeSendToClient() must fall back to a plain
	 * "No fields" label instead of erroring, and must not publish a 'fields'/
	 * 'customfields' attribute (both only get set on the fields-present path).
	 */
	public function testMissingFieldsFallsBackToLabelWithoutError()
	{
		$widget = $this->createWidget();
		$widget->attrs = array(); // no 'fields' key at all

		$widget->beforeSendToClient('');

		$this->assertNull($widget->getElementAttribute('set_filter', 'fields'));
		$this->assertNull($widget->getElementAttribute('set_filter', 'customfields'));
	}
}
