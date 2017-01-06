<?php
/**
 * EGroupware Api: Application test base class
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage test
 * @author Nathan Gray
 * @copyright (c) 2016 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api;

require_once realpath(__DIR__.'/../loader/common.php');	// autoloader & check_load_extension

use PHPUnit_Framework_TestCase as TestCase;
use EGroupware\Api;

/**
 * Base class for application tests, loads the egroupware environment
 *
 * It's not best practice to require the session for every test, but so much
 * is reliant on the session that it's just easier.  By "not best practice",
 * I mean this is pretty bad, but better than manual testing.
 *
 * Each test case (extending class) should get its own session, but session is
 * shared between tests inside.
 *
 * The login information is pulled from doc/phpunit.xml.  Run tests from the
 * command line using 'phpunit -c doc/phpunit.xml'
 *
 * Extend this class into <appname>/test/ or <appname>/src/test/ to test one
 * small aspect of an application.
 */
abstract class AppTest extends TestCase
{
	/**
	 * Start session once before each test case
	 */
	public static function setUpBeforeClass()
	{
		// These globals pulled from the test config (phpunit.xml)
		static::load_egw($GLOBALS['EGW_USER'],$GLOBALS['EGW_PASSWORD'], $GLOBALS['EGW_DOMAIN']);

		// Re-init config, since it doesn't get handled by loading Egw
		Api\Config::init_static();

		$GLOBALS['egw']->db->connect();
	}

	/**
	 * End session when done - every test case gets its own session
	 */
	public static function tearDownAfterClass()
	{
		if($GLOBALS['egw'])
		{
			if($GLOBALS['egw']->session)
			{
				$GLOBALS['egw']->session->destroy(
					$GLOBALS['egw']->session->sessionid,
					$GLOBALS['egw']->session->kp3
				);
			}
			unset($GLOBALS['egw']);
		}
		unset($GLOBALS['egw_info']);
		unset($GLOBALS['_SESSION']);
		$_SESSION = array();
	}

	/**
	* Start the eGW session, exits on wrong credentials
	*
	* @param string $user
	* @param string $passwd
	* @param string $domain
	*/
	public static function load_egw($user,$passwd,$domain='default')
	{
		$_REQUEST['domain'] = $domain;
		$GLOBALS['egw_login_data'] = array(
			'login'  => $user,
			'passwd' => $passwd,
			'passwd_type' => 'text',
		);

		if (ini_get('session.save_handler') == 'files' && !is_writable(ini_get('session.save_path')) && is_dir('/tmp') && is_writable('/tmp'))
		{
			ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir
		}

		$GLOBALS['egw_info'] = array(
			'flags' => array(
				'currentapp' => 'api',
				'noheader' => true,
				'autocreate_session_callback' => __NAMESPACE__ . '\AppTest::create_session',
				'no_exception_handler' => 'cli',
				'noapi' => false,
			)
		);

		include(realpath(__DIR__ . '/../../../header.inc.php'));

		require_once realpath(__DIR__.'/../loader/common.php');	// autoloader & check_load_extension

		// egw is normally created when a file is loaded using require_once
		if(!$GLOBALS['egw'])
		{
			// From Api/src/loader.php
			$GLOBALS['egw_info']['user']['domain'] = Api\Session::search_instance(
				isset($_POST['login']) ? $_POST['login'] : (isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : $_SERVER['REMOTE_USER']),
				Api\Session::get_request('domain'),$GLOBALS['egw_info']['server']['default_domain'],
				array($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']),$GLOBALS['egw_domain']);

			$GLOBALS['egw_info']['server']['db_host'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_host'];
			$GLOBALS['egw_info']['server']['db_port'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_port'];
			$GLOBALS['egw_info']['server']['db_name'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_name'];
			$GLOBALS['egw_info']['server']['db_user'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_user'];
			$GLOBALS['egw_info']['server']['db_pass'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_pass'];
			$GLOBALS['egw_info']['server']['db_type'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_type'];

			$GLOBALS['egw'] = new Api\Egw(array_keys($GLOBALS['egw_domain']));
		}
	}


	/**
	* callback to authenticate with the user/pw specified on the commandline
	*
	* @param array &$account account_info with keys 'login', 'passwd' and optional 'passwd_type'
	* @return boolean/string true if we allow the access and account is set, a sessionid or false otherwise
	*/
	public static function create_session(&$account)
	{
		if (!($sessionid = $GLOBALS['egw']->session->create($GLOBALS['egw_login_data'])))
		{
			die("Wrong account or password - run tests with 'phpunit -c doc/phpunit.xml' or 'phpunit <test_dir> -c doc/phpunit.xml'\n\n");
		}
		unset($GLOBALS['egw_login_data']);
		return $sessionid;
	}
}