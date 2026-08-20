<?php
/**
 * EGroupware importexport: regression test for ajax_get_plugin_description()'s class guard
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Test double that is NOT an importexport_iface_export_plugin - must never be instantiated
 * by ajax_get_plugin_description().
 */
class ImportexportNonPluginProbe
{
	public static $instantiated = false;
	public function __construct() { self::$instantiated = true; }
}

/**
 * Test double that IS a real importexport_iface_export_plugin - must still be instantiated
 * (no regression for legitimate plugins).
 */
class ImportexportPluginProbe implements importexport_iface_export_plugin
{
	public static $instantiated = false;
	public function __construct() { self::$instantiated = true; }
	public function export($_stream, importexport_definition $_definition) {}
	public static function get_name() { return 'probe'; }
	public static function get_description() { return 'probe-description'; }
	public static function get_filesuffix() { return 'probe'; }
	public static function get_mimetype() { return 'text/plain'; }
	public function get_options_etpl(importexport_definition &$definition=null) { return array(); }
	public function get_selectors_etpl() { return array(); }
	public static function get_egw_record_class() { return ''; }
}

/**
 * Before the fix, importexport_export_ui::ajax_get_plugin_description() did `new $_plugin`
 * unconditionally on the fully client-controlled $_plugin argument, checking
 * is_a(...,'importexport_iface_export_plugin') only AFTER construction - so the
 * constructor of an arbitrary autoloadable class always ran.
 */
class ImportexportExportUiTest extends \EGroupware\Api\AppTest
{
	protected function setUp(): void
	{
		parent::setUp();
		ImportexportNonPluginProbe::$instantiated = false;
		ImportexportPluginProbe::$instantiated = false;
	}

	/**
	 * Pass criteria: a class not implementing importexport_iface_export_plugin must
	 * never be instantiated.
	 */
	public function testRejectsNonPluginClass()
	{
		(new importexport_export_ui())->ajax_get_plugin_description('ImportexportNonPluginProbe');

		$this->assertFalse(ImportexportNonPluginProbe::$instantiated,
			'a non-plugin class must never be instantiated');
	}

	/**
	 * Pass criteria: a real plugin class must still be instantiated (no regression).
	 */
	public function testAllowsRealPluginClass()
	{
		(new importexport_export_ui())->ajax_get_plugin_description('ImportexportPluginProbe');

		$this->assertTrue(ImportexportPluginProbe::$instantiated,
			'a real plugin class must still be instantiated');
	}
}
