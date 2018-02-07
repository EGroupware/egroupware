<?php
/**
 * EGroupware Api: Test base class for when you need an Egroupware session for
 * the class or test
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

use PHPUnit\Framework\TestCase as TestCase;
use EGroupware\Api;

/**
 * Base class for tests, loads the egroupware environment
 *
 * It's not best practice to require the session for every test, but so much
 * is reliant on the session that it's just easier.  By "not best practice",
 * I mean this is pretty bad, but better than manual testing.
 *
 * Each test case (extending class) should get its own session, but session is
 * shared between tests inside.
 *
 * The login information is pulled from doc/phpunit.xml.  Run tests from the
 * command line using 'phpunit -c doc'
 */
abstract class LoggedInTest extends TestCase
{
	/**
	 * Start session once before each test case
	 */
	public static function setUpBeforeClass()
	{
		try
		{
			// These globals pulled from the test config (phpunit.xml)
			static::load_egw($GLOBALS['EGW_USER'],$GLOBALS['EGW_PASSWORD'], $GLOBALS['EGW_DOMAIN']);

			if($GLOBALS['egw']->db)
			{
				$GLOBALS['egw']->db->connect();
			}
			else
			{
				static::markTestSkipped('No $GLOBALS[egw]->db');
				die();
			}

			// Re-init config, since it doesn't get handled by loading Egw
			Api\Config::init_static();
		}
		catch(Exception $e)
		{
			static::markTestSkipped('Unable to connect to Egroupware - ' . $e->getMessage());
			return;
		}
	}

	public function assertPreConditions()
	{
		// Do some checks to make sure things we expect are there
		$this->assertTrue(static::sanity_check(), 'Unable to connect to Egroupware - failed sanity check');
	}

	/**
	 * End session when done - every test class gets its own session
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
			if($GLOBALS['egw']->acl)
			{
				$GLOBALS['egw']->acl = null;
			}
			if($GLOBALS['egw']->accounts)
			{
				$GLOBALS['egw']->accounts = null;
			}
			if($GLOBALS['egw']->applications)
			{
				$GLOBALS['egw']->applications = null;
			}
			unset($GLOBALS['egw']);
		}
		unset($GLOBALS['egw_info']);
		unset($GLOBALS['_SESSION']);
		$_SESSION = array();
	}

	/**
	* Start the eGW session, skips the test if there are problems connecting
	*
	* @param string $user
	* @param string $passwd
	* @param string $domain
	*/
	public static function load_egw($user,$passwd,$domain='default',$info=array())
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

		if(!$info)
		{
			$info = array(
				'flags' => array(
					'currentapp' => 'api',
					'noheader' => true,
					'autocreate_session_callback' => __CLASS__ .'::create_session',
					'no_exception_handler' => 'cli',
					'noapi' => false,
				)
			);
		}
		$GLOBALS['egw_info'] = $info;

		try
		{
			include(realpath(__DIR__ . '/../../header.inc.php'));
		}
		catch (Exception $e)
		{
			// Something went wrong setting up egw environment - don't try
			// to do the test
			static::markTestSkipped($e->getMessage());
			return;
		}

		require_once realpath(__DIR__.'/../src/loader/common.php');	// autoloader & check_load_extension

		// egw is normally created when a file is loaded using require_once
		if(empty($GLOBALS['egw']) || !is_a($GLOBALS['egw'], 'EGroupware\Api\Egw\Base'))
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

		// Disable asyc while we test
		$GLOBALS['egw_info']['server']['asyncservice'] = 'off';
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

	/**
	 * Do some checks to make sure things we expect are there
	 */
	protected static function sanity_check()
	{
		// Check that the apps are loaded
		if(!array_key_exists('apps', $GLOBALS['egw_info']) || count($GLOBALS['egw_info']['apps']) == 0)
		{
			return false;
		}

		// Check that the user is loaded
		if(!array_key_exists('user', $GLOBALS['egw_info']) || !$GLOBALS['egw_info']['user']['account_id'])
		{
			return false;
		}

		return true;
	}
}