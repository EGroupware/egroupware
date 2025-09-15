<?php
/**
 * EGroupware Setup - Account import from LDAP (incl. ADS) to SQL
 *
 * @link https://www.egroupware.org
 * @package setup
 * @author Ralf Becker <rb@egroupware.org>
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

include('./inc/functions.inc.php');

// Authorize the user to use setup app and load the database
if (!$GLOBALS['egw_setup']->auth('Config') || $_POST['cancel'])
{
	Header('Location: index.php');
	exit;
}
// Does not return unless user is authorized

// check CSRF token for POST requests with any content (setup uses empty POST to call its modules!)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST)
{
	Api\Csrf::validate($_POST['csrf_token'], __FILE__);
}

try {
	$import = new Api\Accounts\Import(empty($_GET['export_ldif']) ? static function($str, $level)
	{
		switch($level)
		{
			case 'fatal':
				echo "<p style='color: red'><b>$str</b></p>\n";
				break;

			case 'error':
			case 'info':
				echo "<p><b>$str</b></p>\n";
				break;

			default:
				echo "<p>$str</p>\n";
				break;
		}
	} : null);
	if (!empty($_GET['log']))
	{
		$import->showLog();
		return;
	}
	if (!empty($_GET['export_ldif']))
	{
		Api\Header\Content::type('aliases.ldif', 'text/plain');
	}
	$import->logger("Manual import started via setup: initial=$_GET[initial], dry-run=$_GET[dry_run]", 'info');
	$import->run(!empty($_GET['initial']) && $_GET['initial'] !== 'false' || !empty($_GET['export_ldif']),
		!empty($_GET['dry_run'] ?? $_GET['dry-run']) && ($_GET['dry_run'] ?? $_GET['dry-run']) !== 'false',
		$_GET['export_ldif'] ?? null);
}
catch (\Exception $e) {
	http_response_code(500);
	// message already output through logger above
}