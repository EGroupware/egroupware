<?php
/**
 * eGroupWare API - Exceptions
 *
 * This file defines as set of Exceptions used in eGroupWare.
 *
 * Applications having the need for further exceptions should extends the from one defined in this file.
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 * @access public
 * @version $Id$
 */

/**
 * eGroupWare API - Exceptions
 *
 * All eGroupWare exceptions should extended this class, so we are able to eg. add some logging later.
 *
 * The messages for most exceptions should be translated and ready to be displayed to the user.
 * Only exception to this are exceptions like egw_exception_assertion_fails, egw_exception_wrong_parameter
 * or egw_exception_db, which are suppost to happen only during program development.
 */
class egw_exception extends Exception
{
	// nothing fancy yet
}

/**
 * Base class for all exceptions about missing permissions
 *
 */
class egw_exception_no_permission extends egw_exception
{
	/**
	 * Constructor
	 *
	 * @param string $msg=null message, default "Permission denied!"
	 * @param int $code=100 numerical code, default 100
	 */
	function __construct($msg=null,$code=100)
	{
		if (is_null($msg)) $msg = lang('Permisson denied!');

		parent::__construct($msg,$code);
	}
}

/**
 * User lacks the right to run an application
 *
 */
class egw_exception_no_permission_app extends egw_exception_no_permission
{
	function __construct($msg=null,$code=101)
	{
		if (isset($GLOBALS['egw_info']['apps'][$msg]))
		{
			if ($msg == 'admin')
			{
				$msg = lang('You need to be an eGroupWare administrator to access this functionality!');
			}
			else
			{
				$currentapp = $GLOBALS['egw_info']['flags']['currentapp'];
				$app = isset($GLOBALS['egw_info']['apps'][$currentapp]['title']) ?
					$GLOBALS['egw_info']['apps'][$currentapp]['title'] : $msg;

				$msg = lang('You\'ve tried to open the eGroupWare application: %1, but you have no permission to access this application.',
						'"'.$app.'"');
			}
		}
		parent::__construct($msg,$code);
	}
}

/**
 * User is no eGroupWare admin (no right to run the admin application)
 *
 */
class egw_exception_no_permission_admin extends egw_exception_no_permission_app
{
	function __construct($msg=null,$code=102)
	{
		if (is_null($msg)) $msg = 'admin';

		parent::__construct($msg,$code);
	}
}

/**
 * User lacks a record level permission, eg. he's not the owner and has no grant from the owner
 *
 */
class egw_exception_no_permission_record extends egw_exception_no_permission { }

/**
 * A record or application entry was not found for the given id
 *
 */
class egw_exception_not_found extends egw_exception
{
	/**
	 * Constructor
	 *
	 * @param string $msg=null message, default "Entry not found!"
	 * @param int $code=99 numerical code, default 2
	 */
	function __construct($msg=null,$code=2)
	{
		if (is_null($msg)) $msg = lang('Entry not found!');

		parent::__construct($msg,$code);
	}
}

/**
 * An necessary assumption the developer made failed, regular execution can not continue
 *
 * As you get this only by an error in the code or during development, the message does not need to be translated
 */
class egw_exception_assertion_failed extends egw_exception { }

/**
 * A method or function was called with a wrong or missing parameter
 *
 * As you get this only by an error in the code or during development, the message does not need to be translated
 */
class egw_exception_wrong_parameter extends egw_exception_assertion_failed { }

/**
 * Wrong or missing required user input: message should be translated so it can be shown directly to the user
 *
 */
class egw_exception_wrong_userinput extends egw_exception_assertion_failed { }

/**
 * Exceptions thrown by the egw_db class
 *
 */
class egw_exception_db extends egw_exception
{
	/**
	 * Constructor
	 *
	 * @param string $msg=null message, default "Database error!"
	 * @param int $code=100
	 */
	function __construct($msg=null,$code=100)
	{
		if (is_null($msg)) $msg = lang('Database error!');

		parent::__construct($msg,$code);
	}
}

/**
 * Storing the row violates a unique key constrain
 *
 */
class egw_exception_db_not_unique extends egw_exception_db { }