<?php
/**
 * EGroupware API - old deprecated exceptions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 * @access public
 * @version $Id$
 */

use EGroupware\Api;

/**
 * eGroupWare API - Exceptions
 *
 * All eGroupWare exceptions should extended this class, so we are able to eg. add some logging later.
 *
 * The messages for most exceptions should be translated and ready to be displayed to the user.
 * Only exception to this are exceptions like egw_exception_assertion_fails, egw_exception_wrong_parameter
 * or egw_exception_db, which are suppost to happen only during program development.
 *
 * @deprecated use Api\Exception
 */
class egw_exception extends Api\Exception {}

/**
 * Base class for all exceptions about missing permissions
 *
 * New NoPermisison excpetion has to extend deprecated egw_exception_no_permission
 * to allow legacy code to catch them!
 *
 * @deprecated use Api\Exception\NoPermission
 */
class egw_exception_no_permission extends Api\Exception {}

/**
 * User lacks the right to run an application
 *
 * @deprecated use Api\Exception\NoPermission\App
 */
class egw_exception_no_permission_app extends Api\Exception\NoPermission\App {}

/**
 * User is no eGroupWare admin (no right to run the admin application)
 *
 * @deprecated use Api\Exception\NoPermission\Admin
 */
class egw_exception_no_permission_admin extends Api\Exception\NoPermission\Admin {}

/**
 * User lacks a record level permission, eg. he's not the owner and has no grant from the owner
 *
 * @deprecated use Api\Exception\NoPermission\Record
 */
class egw_exception_no_permission_record extends Api\Exception\NoPermission\Record {}

/**
 * A record or application entry was not found for the given id
 *
 * @deprecated use Api\Exception\NotFound
 */
class egw_exception_not_found extends Api\Exception\NotFound {}

/**
 * An necessary assumption the developer made failed, regular execution can not continue
 *
 * As you get this only by an error in the code or during development, the message does not need to be translated
 *
 * @deprecated use Api\Exception\AssertionFailed
 */
class egw_exception_assertion_failed extends Api\Exception\AssertionFailed {}

/**
 * A method or function was called with a wrong or missing parameter
 *
 * As you get this only by an error in the code or during development, the message does not need to be translated
 *
 * @deprecated use Api\Exception\WrongParameter
 */
class egw_exception_wrong_parameter extends Api\Exception\WrongParameter {}

/**
 * Wrong or missing required user input: message should be translated so it can be shown directly to the user
 *
 * @deprecated use Api\Exception\WrongUserInput
 */
class egw_exception_wrong_userinput extends Api\Exception\WrongUserinput {}

/**
 * Exception thrown by the egw_db class for everything not covered by extended classed below
 *
 * New Db\Exception has to extend deprecated egw_exception_db to allow legacy code
 * to catch exceptions thrown by Api\Db class!
 *
 * @deprecated use Api\Db\Exception
 */
class egw_exception_db extends Api\Exception {}

/**
 * Classic invalid SQL error
 *
 * New InvalidSql exception has to extend deprecated egw_exception_db_invalid_sql
 * to allow legacy code to catch exceptions thrown by Api\Db!
 *
 * @deprecated use Api\Db\Exception\InvalidSql
 */
class egw_exception_db_invalid_sql extends Api\Db\Exception {}

/**
 * Allow callbacks to request a redirect
 *
 * Can be caught be applications and is otherwise handled by global exception handler.
 */
class egw_exception_redirect extends Api\Exception\Redirect {}
