<?php
/**
 * EGroupware test app to test eg. Api\Storage\Base - update script
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2017RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/**
 * Add a nullable unique-key column and a bool column, needed to test
 * Storage\Base::not_unique(), read()'s unique-key fallback, and db2data()'s bool conversion -
 * see doc/ai/projects/storage-test-coverage.md
 */
function test_upgrade17_1_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_test', 't_uniq', array(
		'type' => 'varchar',
		'precision' => '80',
		'nullable' => True,
		'comment' => 'nullable unique column, for Storage\Base::not_unique()/read() unique-key-fallback tests',
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_test', 't_active', array(
		'type' => 'bool',
		'nullable' => True,
		'default' => '1',
		'comment' => 'bool column, for Storage\Base::db2data() bool-conversion tests',
	));
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_test', array('t_uniq'), true);

	return $GLOBALS['setup_info']['test']['currentver'] = '17.1.002';
}

/**
 * Add a JSON blob column, needed to test Storage\Json/JsonCF/JsonTrait against a real table -
 * see doc/ai/projects/storage-test-coverage.md
 */
function test_upgrade17_1_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_test', 't_json', array(
		'type' => 'text',
		'nullable' => True,
		'comment' => 'JSON blob column, for Storage\Json/JsonCF/JsonTrait tests',
	));

	return $GLOBALS['setup_info']['test']['currentver'] = '17.1.003';
}
