<?php
//
// +----------------------------------------------------------------------+
// | PEAR, the PHP Extension and Application Repository                   |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.php.net/license/3_0.txt.                                  |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Sterling Hughes <sterling@php.net>                          |
// |          Stig Bakken <ssb@php.net>                                   |
// |          Tomas V.V.Cox <cox@idecnet.com>                             |
// +----------------------------------------------------------------------+
//
// $Id$
//

/* viniciuscb: changed PEAR to PEARgw in this file to avoid eventual confilct */

define('PEARgw_ERROR_RETURN',     1);
define('PEARgw_ERROR_PRINT',      2);
define('PEARgw_ERROR_TRIGGER',    4);
define('PEARgw_ERROR_DIE',        8);
define('PEARgw_ERROR_CALLBACK',  16);
define('PEARgw_ERROR_EXCEPTION', 32);
define('PEARgw_ZE2', (function_exists('version_compare') &&
                    version_compare(zend_version(), "2-dev", "ge")));

if (substr(PHP_OS, 0, 3) == 'WIN') {
    define('OS_WINDOWS', true);
    define('OS_UNIX',    false);
    define('PEARgw_OS',    'Windows');
} else {
    define('OS_WINDOWS', false);
    define('OS_UNIX',    true);
    define('PEARgw_OS',    'Unix'); // blatant assumption
}

$GLOBALS['_PEARgw_default_error_mode']     = PEARgw_ERROR_RETURN;
$GLOBALS['_PEARgw_default_error_options']  = E_USER_NOTICE;
$GLOBALS['_PEARgw_destructor_object_list'] = array();
$GLOBALS['_PEARgw_shutdown_funcs']         = array();
$GLOBALS['_PEARgw_error_handler_stack']    = array();

ini_set('track_errors', true);

/**
 * Base class for other PEARgw classes.  Provides rudimentary
 * emulation of destructors.
 *
 * If you want a destructor in your class, inherit PEARgw and make a
 * destructor method called _yourclassname (same name as the
 * constructor, but with a "_" prefix).  Also, in your constructor you
 * have to call the PEARgw constructor: $this->PEARgw();.
 * The destructor method will be called without parameters.  Note that
 * at in some SAPI implementations (such as Apache), any output during
 * the request shutdown (in which destructors are called) seems to be
 * discarded.  If you need to get any debug information from your
 * destructor, use error_log(), syslog() or something similar.
 *
 * IMPORTANT! To use the emulated destructors you need to create the
 * objects by reference: $obj =& new PEARgw_child;
 *
 * @since PHP 4.0.2
 * @author Stig Bakken <ssb@php.net>
 * @see http://pear.php.net/manual/
 */
class PEARgw
{
    // {{{ properties

    /**
     * Whether to enable internal debug messages.
     *
     * @var     bool
     * @access  private
     */
    var $_debug = false;

    /**
     * Default error mode for this object.
     *
     * @var     int
     * @access  private
     */
    var $_default_error_mode = null;

    /**
     * Default error options used for this object when error mode
     * is PEARgw_ERROR_TRIGGER.
     *
     * @var     int
     * @access  private
     */
    var $_default_error_options = null;

    /**
     * Default error handler (callback) for this object, if error mode is
     * PEARgw_ERROR_CALLBACK.
     *
     * @var     string
     * @access  private
     */
    var $_default_error_handler = '';

    /**
     * Which class to use for error objects.
     *
     * @var     string
     * @access  private
     */
    var $_error_class = 'PEARgw_Error';

    /**
     * An array of expected errors.
     *
     * @var     array
     * @access  private
     */
    var $_expected_errors = array();

    // }}}

    // {{{ constructor

    /**
     * Constructor.  Registers this object in
     * $_PEARgw_destructor_object_list for destructor emulation if a
     * destructor object exists.
     *
     * @param string $error_class  (optional) which class to use for
     *        error objects, defaults to PEARgw_Error.
     * @access public
     * @return void
     */
    function PEARgw($error_class = null)
    {
        $classname = get_class($this);
        if ($this->_debug) {
            print "PEARgw constructor called, class=$classname\n";
        }
        if ($error_class !== null) {
            $this->_error_class = $error_class;
        }
        while ($classname) {
            $destructor = "_$classname";
            if (method_exists($this, $destructor)) {
                global $_PEARgw_destructor_object_list;
                $_PEARgw_destructor_object_list[] = &$this;
                break;
            } else {
                $classname = get_parent_class($classname);
            }
        }
    }

    // }}}
    // {{{ destructor

    /**
     * Destructor (the emulated type of...).  Does nothing right now,
     * but is included for forward compatibility, so subclass
     * destructors should always call it.
     *
     * See the note in the class desciption about output from
     * destructors.
     *
     * @access public
     * @return void
     */
    function _PEARgw() {
        if ($this->_debug) {
            printf("PEARgw destructor called, class=%s\n", get_class($this));
        }
    }

    // }}}
    // {{{ getStaticProperty()

    /**
    * If you have a class that's mostly/entirely static, and you need static
    * properties, you can use this method to simulate them. Eg. in your method(s)
    * do this: $myVar = &PEARgw::getStaticProperty('myVar');
    * You MUST use a reference, or they will not persist!
    *
    * @access public
    * @param  string $class  The calling classname, to prevent clashes
    * @param  string $var    The variable to retrieve.
    * @return mixed   A reference to the variable. If not set it will be
    *                 auto initialised to NULL.
    */
    function &getStaticProperty($class, $var)
    {
        static $properties;
        return $properties[$class][$var];
    }

    // }}}
    // {{{ registerShutdownFunc()

    /**
    * Use this function to register a shutdown method for static
    * classes.
    *
    * @access public
    * @param  mixed $func  The function name (or array of class/method) to call
    * @param  mixed $args  The arguments to pass to the function
    * @return void
    */
    function registerShutdownFunc($func, $args = array())
    {
        $GLOBALS['_PEARgw_shutdown_funcs'][] = array($func, $args);
    }

    // }}}
    // {{{ isError()

    /**
     * Tell whether a value is a PEARgw error.
     *
     * @param   mixed $data   the value to test
     * @param   int   $code   if $data is an error object, return true
     *                        only if $code is a string and
     *                        $obj->getMessage() == $code or
     *                        $code is an integer and $obj->getCode() == $code
     * @access  public
     * @return  bool    true if parameter is an error
     */
    function isError($data, $code = null)
    {
        if (is_object($data) && (get_class($data) == 'pear_error' ||
                                 is_subclass_of($data, 'pear_error'))) {
            if (is_null($code)) {
                return true;
            } elseif (is_string($code)) {
                return $data->getMessage() == $code;
            } else {
                return $data->getCode() == $code;
            }
        }
        return false;
    }

    // }}}
    // {{{ setErrorHandling()

    /**
     * Sets how errors generated by this object should be handled.
     * Can be invoked both in objects and statically.  If called
     * statically, setErrorHandling sets the default behaviour for all
     * PEARgw objects.  If called in an object, setErrorHandling sets
     * the default behaviour for that object.
     *
     * @param int $mode
     *        One of PEARgw_ERROR_RETURN, PEARgw_ERROR_PRINT,
     *        PEARgw_ERROR_TRIGGER, PEARgw_ERROR_DIE,
     *        PEARgw_ERROR_CALLBACK or PEARgw_ERROR_EXCEPTION.
     *
     * @param mixed $options
     *        When $mode is PEARgw_ERROR_TRIGGER, this is the error level (one
     *        of E_USER_NOTICE, E_USER_WARNING or E_USER_ERROR).
     *
     *        When $mode is PEARgw_ERROR_CALLBACK, this parameter is expected
     *        to be the callback function or method.  A callback
     *        function is a string with the name of the function, a
     *        callback method is an array of two elements: the element
     *        at index 0 is the object, and the element at index 1 is
     *        the name of the method to call in the object.
     *
     *        When $mode is PEARgw_ERROR_PRINT or PEARgw_ERROR_DIE, this is
     *        a printf format string used when printing the error
     *        message.
     *
     * @access public
     * @return void
     * @see PEARgw_ERROR_RETURN
     * @see PEARgw_ERROR_PRINT
     * @see PEARgw_ERROR_TRIGGER
     * @see PEARgw_ERROR_DIE
     * @see PEARgw_ERROR_CALLBACK
     * @see PEARgw_ERROR_EXCEPTION
     *
     * @since PHP 4.0.5
     */

    function setErrorHandling($mode = null, $options = null)
    {
        if (isset($this)) {
            $setmode     = &$this->_default_error_mode;
            $setoptions  = &$this->_default_error_options;
        } else {
            $setmode     = &$GLOBALS['_PEARgw_default_error_mode'];
            $setoptions  = &$GLOBALS['_PEARgw_default_error_options'];
        }

        switch ($mode) {
            case PEARgw_ERROR_RETURN:
            case PEARgw_ERROR_PRINT:
            case PEARgw_ERROR_TRIGGER:
            case PEARgw_ERROR_DIE:
            case PEARgw_ERROR_EXCEPTION:
            case null:
                $setmode = $mode;
                $setoptions = $options;
                break;

            case PEARgw_ERROR_CALLBACK:
                $setmode = $mode;
                if ((is_string($options) && function_exists($options)) ||
                    (is_array($options) && method_exists(@$options[0], @$options[1])))
                {
                    $setoptions = $options;
                } else {
                    trigger_error("invalid error callback", E_USER_WARNING);
                }
                break;

            default:
                trigger_error("invalid error mode", E_USER_WARNING);
                break;
        }
    }

    // }}}
    // {{{ expectError()

    /**
     * This method is used to tell which errors you expect to get.
     * Expected errors are always returned with error mode
     * PEARgw_ERROR_RETURN.  Expected error codes are stored in a stack,
     * and this method pushes a new element onto it.  The list of
     * expected errors are in effect until they are popped off the
     * stack with the popExpect() method.
     *
     * Note that this method can not be called statically
     *
     * @param mixed $code a single error code or an array of error codes to expect
     *
     * @return int     the new depth of the "expected errors" stack
     * @access public
     */
    function expectError($code = '*')
    {
        if (is_array($code)) {
            array_push($this->_expected_errors, $code);
        } else {
            array_push($this->_expected_errors, array($code));
        }
        return sizeof($this->_expected_errors);
    }

    // }}}
    // {{{ popExpect()

    /**
     * This method pops one element off the expected error codes
     * stack.
     *
     * @return array   the list of error codes that were popped
     */
    function popExpect()
    {
        return array_pop($this->_expected_errors);
    }

    // }}}
    // {{{ _checkDelExpect()

    /**
     * This method checks unsets an error code if available
     *
     * @param mixed error code
     * @return bool true if the error code was unset, false otherwise
     * @access private
     * @since PHP 4.3.0
     */
    function _checkDelExpect($error_code)
    {
        $deleted = false;

        foreach ($this->_expected_errors AS $key => $error_array) {
            if (in_array($error_code, $error_array)) {
                unset($this->_expected_errors[$key][array_search($error_code, $error_array)]);
                $deleted = true;
            }

            // clean up empty arrays
            if (0 == count($this->_expected_errors[$key])) {
                unset($this->_expected_errors[$key]);
            }
        }
        return $deleted;
    }

    // }}}
    // {{{ delExpect()

    /**
     * This method deletes all occurences of the specified element from
     * the expected error codes stack.
     *
     * @param  mixed $error_code error code that should be deleted
     * @return mixed list of error codes that were deleted or error
     * @access public
     * @since PHP 4.3.0
     */
    function delExpect($error_code)
    {
        $deleted = false;

        if ((is_array($error_code) && (0 != count($error_code)))) {
            // $error_code is a non-empty array here;
            // we walk through it trying to unset all
            // values
            foreach($error_code AS $key => $error) {
                if ($this->_checkDelExpect($error)) {
                    $deleted =  true;
                } else {
                    $deleted = false;
                }
            }
            return $deleted ? true : PEARgw::raiseError("The expected error you submitted does not exist"); // IMPROVE ME
        } elseif (!empty($error_code)) {
            // $error_code comes alone, trying to unset it
            if ($this->_checkDelExpect($error_code)) {
                return true;
            } else {
                return PEARgw::raiseError("The expected error you submitted does not exist"); // IMPROVE ME
            }
        } else {
            // $error_code is empty
            return PEARgw::raiseError("The expected error you submitted is empty"); // IMPROVE ME
        }
    }

    // }}}
    // {{{ raiseError()

    /**
     * This method is a wrapper that returns an instance of the
     * configured error class with this object's default error
     * handling applied.  If the $mode and $options parameters are not
     * specified, the object's defaults are used.
     *
     * @param mixed $message a text error message or a PEARgw error object
     *
     * @param int $code      a numeric error code (it is up to your class
     *                  to define these if you want to use codes)
     *
     * @param int $mode      One of PEARgw_ERROR_RETURN, PEARgw_ERROR_PRINT,
     *                  PEARgw_ERROR_TRIGGER, PEARgw_ERROR_DIE,
     *                  PEARgw_ERROR_CALLBACK, PEARgw_ERROR_EXCEPTION.
     *
     * @param mixed $options If $mode is PEARgw_ERROR_TRIGGER, this parameter
     *                  specifies the PHP-internal error level (one of
     *                  E_USER_NOTICE, E_USER_WARNING or E_USER_ERROR).
     *                  If $mode is PEARgw_ERROR_CALLBACK, this
     *                  parameter specifies the callback function or
     *                  method.  In other error modes this parameter
     *                  is ignored.
     *
     * @param string $userinfo If you need to pass along for example debug
     *                  information, this parameter is meant for that.
     *
     * @param string $error_class The returned error object will be
     *                  instantiated from this class, if specified.
     *
     * @param bool $skipmsg If true, raiseError will only pass error codes,
     *                  the error message parameter will be dropped.
     *
     * @access public
     * @return object   a PEARgw error object
     * @see PEARgw::setErrorHandling
     * @since PHP 4.0.5
     */
    function raiseError($message = null,
                         $code = null,
                         $mode = null,
                         $options = null,
                         $userinfo = null,
                         $error_class = null,
                         $skipmsg = false)
    {
        // The error is yet a PEARgw error object
        if (is_object($message)) {
            $code        = $message->getCode();
            $userinfo    = $message->getUserInfo();
            $error_class = $message->getType();
            $message     = $message->getMessage();
        }

        if (isset($this) && isset($this->_expected_errors) && sizeof($this->_expected_errors) > 0 && sizeof($exp = end($this->_expected_errors))) {
            if ($exp[0] == "*" ||
                (is_int(reset($exp)) && in_array($code, $exp)) ||
                (is_string(reset($exp)) && in_array($message, $exp))) {
                $mode = PEARgw_ERROR_RETURN;
            }
        }
        // No mode given, try global ones
        if ($mode === null) {
            // Class error handler
            if (isset($this) && isset($this->_default_error_mode)) {
                $mode    = $this->_default_error_mode;
                $options = $this->_default_error_options;
            // Global error handler
            } elseif (isset($GLOBALS['_PEARgw_default_error_mode'])) {
                $mode    = $GLOBALS['_PEARgw_default_error_mode'];
                $options = $GLOBALS['_PEARgw_default_error_options'];
            }
        }

        if ($error_class !== null) {
            $ec = $error_class;
        } elseif (isset($this) && isset($this->_error_class)) {
            $ec = $this->_error_class;
        } else {
            $ec = 'PEARgw_Error';
        }
        if ($skipmsg) {
            return new $ec($code, $mode, $options, $userinfo);
        } else {
            return new $ec($message, $code, $mode, $options, $userinfo);
        }
    }

    // }}}
    // {{{ throwError()

    /**
     * Simpler form of raiseError with fewer options.  In most cases
     * message, code and userinfo are enough.
     *
     * @param string $message
     *
     */
    function &throwError($message = null,
                         $code = null,
                         $userinfo = null)
    {
        if (isset($this) && is_subclass_of($this, 'PEARgw_Error')) {
            return $this->raiseError($message, $code, null, null, $userinfo);
        } else {
            return PEARgw::raiseError($message, $code, null, null, $userinfo);
        }
    }

    // }}}
    // {{{ pushErrorHandling()

    /**
     * Push a new error handler on top of the error handler options stack. With this
     * you can easily override the actual error handler for some code and restore
     * it later with popErrorHandling.
     *
     * @param mixed $mode (same as setErrorHandling)
     * @param mixed $options (same as setErrorHandling)
     *
     * @return bool Always true
     *
     * @see PEARgw::setErrorHandling
     */
    function pushErrorHandling($mode, $options = null)
    {
        $stack = &$GLOBALS['_PEARgw_error_handler_stack'];
        if (isset($this)) {
            $def_mode    = &$this->_default_error_mode;
            $def_options = &$this->_default_error_options;
        } else {
            $def_mode    = &$GLOBALS['_PEARgw_default_error_mode'];
            $def_options = &$GLOBALS['_PEARgw_default_error_options'];
        }
        $stack[] = array($def_mode, $def_options);

        if (isset($this)) {
            $this->setErrorHandling($mode, $options);
        } else {
            PEARgw::setErrorHandling($mode, $options);
        }
        $stack[] = array($mode, $options);
        return true;
    }

    // }}}
    // {{{ popErrorHandling()

    /**
    * Pop the last error handler used
    *
    * @return bool Always true
    *
    * @see PEARgw::pushErrorHandling
    */
    function popErrorHandling()
    {
        $stack = &$GLOBALS['_PEARgw_error_handler_stack'];
        array_pop($stack);
        list($mode, $options) = $stack[sizeof($stack) - 1];
        array_pop($stack);
        if (isset($this)) {
            $this->setErrorHandling($mode, $options);
        } else {
            PEARgw::setErrorHandling($mode, $options);
        }
        return true;
    }

    // }}}
    // {{{ loadExtension()

    /**
    * OS independant PHP extension load. Remember to take care
    * on the correct extension name for case sensitive OSes.
    *
    * @param string $ext The extension name
    * @return bool Success or not on the dl() call
    */
    function loadExtension($ext)
    {
        if (!extension_loaded($ext)) {
            // if either returns true dl() will produce a FATAL error, stop that
            if ((ini_get('enable_dl') != 1) || (ini_get('safe_mode') == 1)) {
                return false;
            }
            if (OS_WINDOWS) {
                $suffix = '.dll';
            } elseif (PHP_OS == 'HP-UX') {
                $suffix = '.sl';
            } elseif (PHP_OS == 'AIX') {
                $suffix = '.a';
            } elseif (PHP_OS == 'OSX') {
                $suffix = '.bundle';
            } else {
                $suffix = '.so';
            }
            return @dl('php_'.$ext.$suffix) || @dl($ext.$suffix);
        }
        return true;
    }

    // }}}
}

// {{{ _PEARgw_call_destructors()

function _PEARgw_call_destructors()
{
    global $_PEARgw_destructor_object_list;
    if (is_array($_PEARgw_destructor_object_list) &&
        sizeof($_PEARgw_destructor_object_list))
    {
        reset($_PEARgw_destructor_object_list);
        while (list($k, $objref) = each($_PEARgw_destructor_object_list)) {
            $classname = get_class($objref);
            while ($classname) {
                $destructor = "_$classname";
                if (method_exists($objref, $destructor)) {
                    $objref->$destructor();
                    break;
                } else {
                    $classname = get_parent_class($classname);
                }
            }
        }
        // Empty the object list to ensure that destructors are
        // not called more than once.
        $_PEARgw_destructor_object_list = array();
    }

    // Now call the shutdown functions
    if (is_array($GLOBALS['_PEARgw_shutdown_funcs']) AND !empty($GLOBALS['_PEARgw_shutdown_funcs'])) {
        foreach ($GLOBALS['_PEARgw_shutdown_funcs'] as $value) {
            call_user_func_array($value[0], $value[1]);
        }
    }
}

// }}}

class PEARgw_Error
{
    // {{{ properties

    var $error_message_prefix = '';
    var $mode                 = PEARgw_ERROR_RETURN;
    var $level                = E_USER_NOTICE;
    var $code                 = -1;
    var $message              = '';
    var $userinfo             = '';
    var $backtrace            = null;

    // }}}
    // {{{ constructor

    /**
     * PEARgw_Error constructor
     *
     * @param string $message  message
     *
     * @param int $code     (optional) error code
     *
     * @param int $mode     (optional) error mode, one of: PEARgw_ERROR_RETURN,
     * PEARgw_ERROR_PRINT, PEARgw_ERROR_DIE, PEARgw_ERROR_TRIGGER,
     * PEARgw_ERROR_CALLBACK or PEARgw_ERROR_EXCEPTION
     *
     * @param mixed $options   (optional) error level, _OR_ in the case of
     * PEARgw_ERROR_CALLBACK, the callback function or object/method
     * tuple.
     *
     * @param string $userinfo (optional) additional user/debug info
     *
     * @access public
     *
     */
    function PEARgw_Error($message = 'unknown error', $code = null,
                        $mode = null, $options = null, $userinfo = null)
    {
        if ($mode === null) {
            $mode = PEARgw_ERROR_RETURN;
        }
        $this->message   = $message;
        $this->code      = $code;
        $this->mode      = $mode;
        $this->userinfo  = $userinfo;
        if (function_exists("debug_backtrace")) {
            $this->backtrace = debug_backtrace();
        }
        if ($mode & PEARgw_ERROR_CALLBACK) {
            $this->level = E_USER_NOTICE;
            $this->callback = $options;
        } else {
            if ($options === null) {
                $options = E_USER_NOTICE;
            }
            $this->level = $options;
            $this->callback = null;
        }
        if ($this->mode & PEARgw_ERROR_PRINT) {
            if (is_null($options) || is_int($options)) {
                $format = "%s";
            } else {
                $format = $options;
            }
            printf($format, $this->getMessage());
        }
        if ($this->mode & PEARgw_ERROR_TRIGGER) {
            trigger_error($this->getMessage(), $this->level);
        }
        if ($this->mode & PEARgw_ERROR_DIE) {
            $msg = $this->getMessage();
            if (is_null($options) || is_int($options)) {
                $format = "%s";
                if (substr($msg, -1) != "\n") {
                    $msg .= "\n";
                }
            } else {
                $format = $options;
            }
            die(sprintf($format, $msg));
        }
        if ($this->mode & PEARgw_ERROR_CALLBACK) {
            if (is_string($this->callback) && strlen($this->callback)) {
                call_user_func($this->callback, $this);
            } elseif (is_array($this->callback) &&
                      sizeof($this->callback) == 2 &&
                      is_object($this->callback[0]) &&
                      is_string($this->callback[1]) &&
                      strlen($this->callback[1])) {
                      call_user_func($this->callback, $this);
            }
        }
        if (PEARgw_ZE2 && $this->mode & PEARgw_ERROR_EXCEPTION) {
            eval('throw $this;');
        }
    }

    // }}}
    // {{{ getMode()

    /**
     * Get the error mode from an error object.
     *
     * @return int error mode
     * @access public
     */
    function getMode() {
        return $this->mode;
    }

    // }}}
    // {{{ getCallback()

    /**
     * Get the callback function/method from an error object.
     *
     * @return mixed callback function or object/method array
     * @access public
     */
    function getCallback() {
        return $this->callback;
    }

    // }}}
    // {{{ getMessage()


    /**
     * Get the error message from an error object.
     *
     * @return  string  full error message
     * @access public
     */
    function getMessage()
    {
        return ($this->error_message_prefix . $this->message);
    }


    // }}}
    // {{{ getCode()

    /**
     * Get error code from an error object
     *
     * @return int error code
     * @access public
     */
     function getCode()
     {
        return $this->code;
     }

    // }}}
    // {{{ getType()

    /**
     * Get the name of this error/exception.
     *
     * @return string error/exception name (type)
     * @access public
     */
    function getType()
    {
        return get_class($this);
    }

    // }}}
    // {{{ getUserInfo()

    /**
     * Get additional user-supplied information.
     *
     * @return string user-supplied information
     * @access public
     */
    function getUserInfo()
    {
        return $this->userinfo;
    }

    // }}}
    // {{{ getDebugInfo()

    /**
     * Get additional debug information supplied by the application.
     *
     * @return string debug information
     * @access public
     */
    function getDebugInfo()
    {
        return $this->getUserInfo();
    }

    // }}}
    // {{{ getBacktrace()

    /**
     * Get the call backtrace from where the error was generated.
     * Supported with PHP 4.3.0 or newer.
     *
     * @param int $frame (optional) what frame to fetch
     * @return array Backtrace, or NULL if not available.
     * @access public
     */
    function getBacktrace($frame = null)
    {
        if ($frame === null) {
            return $this->backtrace;
        }
        return $this->backtrace[$frame];
    }

    // }}}
    // {{{ addUserInfo()

    function addUserInfo($info)
    {
        if (empty($this->userinfo)) {
            $this->userinfo = $info;
        } else {
            $this->userinfo .= " ** $info";
        }
    }

    // }}}
    // {{{ toString()

    /**
     * Make a string representation of this object.
     *
     * @return string a string with an object summary
     * @access public
     */
    function toString() {
        $modes = array();
        $levels = array(E_USER_NOTICE  => 'notice',
                        E_USER_WARNING => 'warning',
                        E_USER_ERROR   => 'error');
        if ($this->mode & PEARgw_ERROR_CALLBACK) {
            if (is_array($this->callback)) {
                $callback = get_class($this->callback[0]) . '::' .
                    $this->callback[1];
            } else {
                $callback = $this->callback;
            }
            return sprintf('[%s: message="%s" code=%d mode=callback '.
                           'callback=%s prefix="%s" info="%s"]',
                           get_class($this), $this->message, $this->code,
                           $callback, $this->error_message_prefix,
                           $this->userinfo);
        }
        if ($this->mode & PEARgw_ERROR_PRINT) {
            $modes[] = 'print';
        }
        if ($this->mode & PEARgw_ERROR_TRIGGER) {
            $modes[] = 'trigger';
        }
        if ($this->mode & PEARgw_ERROR_DIE) {
            $modes[] = 'die';
        }
        if ($this->mode & PEARgw_ERROR_RETURN) {
            $modes[] = 'return';
        }
        return sprintf('[%s: message="%s" code=%d mode=%s level=%s '.
                       'prefix="%s" info="%s"]',
                       get_class($this), $this->message, $this->code,
                       implode("|", $modes), $levels[$this->level],
                       $this->error_message_prefix,
                       $this->userinfo);
    }

    // }}}
}

register_shutdown_function("_PEARgw_call_destructors");

/*
 * Local Variables:
 * mode: php
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 */


/**
* Creates a (compressed) Tar archive
*
* @author   Vincent Blavet <vincent@blavet.net>
* @version  $Revision$
* @package  Archive
*/
class Archive_Tar extends PEARgw
{
    /**
    * @var string Name of the Tar
    */
    var $_tarname='';

    /**
    * @var boolean if true, the Tar file will be gzipped
    */
    var $_compress=false;

    /**
    * @var string Type of compression : 'none', 'gz' or 'bz2'
    */
    var $_compress_type='none';

    /**
    * @var file descriptor
    */
    var $_file=0;

    /**
    * @var string Local Tar name of a remote Tar (http:// or ftp://)
    */
    var $_temp_tarname='';

    // {{{ constructor
    /**
    * Archive_Tar Class constructor. This flavour of the constructor only
    * declare a new Archive_Tar object, identifying it by the name of the
    * tar file.
    * If the compress argument is set the tar will be read or created as a
    * gzip or bz2 compressed TAR file.
    *
    * @param    string  $p_tarname  The name of the tar archive to create
    * @param    string  $p_compress can be null, 'gz' or 'bz2'. This
    *                   parameter indicates if gzip or bz2 compression
    *                   is required.  For compatibility reason the
    *                   boolean value 'true' means 'gz'.
    * @access public
    */
    function Archive_Tar($p_tarname, $p_compress = null)
    {
        $this->PEARgw();
        $this->_compress = false;
        $this->_compress_type = 'none';
        if ($p_compress === null) {
            if (@file_exists($p_tarname)) {
                if ($fp = @fopen($p_tarname, "rb")) {
                    // look for gzip magic cookie
                    $data = fread($fp, 2);
                    fclose($fp);
                    if ($data == "\37\213") {
                        $this->_compress = true;
                        $this->_compress_type = 'gz';
                    // No sure it's enought for a magic code ....
                    } elseif ($data == "BZ") {
                        $this->_compress = true;
                        $this->_compress_type = 'bz2';
                    }
                }
            } else {
                // probably a remote file or some file accessible
                // through a stream interface
                if (substr($p_tarname, -2) == 'gz') {
                    $this->_compress = true;
                    $this->_compress_type = 'gz';
                } elseif ((substr($p_tarname, -3) == 'bz2') ||
                          (substr($p_tarname, -2) == 'bz')) {
                    $this->_compress = true;
                    $this->_compress_type = 'bz2';
                }
            }
        } else {
            if (($p_compress == true) || ($p_compress == 'gz')) {
                $this->_compress = true;
                $this->_compress_type = 'gz';
            } else if ($p_compress == 'bz2') {
                $this->_compress = true;
                $this->_compress_type = 'bz2';
            }
        }
        $this->_tarname = $p_tarname;
        if ($this->_compress) { // assert zlib or bz2 extension support
            if ($this->_compress_type == 'gz')
                $extname = 'zlib';
            else if ($this->_compress_type == 'bz2')
                $extname = 'bz2';

            if (!extension_loaded($extname)) {
                PEARgw::loadExtension($extname);
            }
            if (!extension_loaded($extname)) {
                die("The extension '$extname' couldn't be found.\n".
                    "Please make sure your version of PHP was built ".
                    "with '$extname' support.\n");
                return false;
            }
        }
    }
    // }}}

    // {{{ destructor
    function _Archive_Tar()
    {
        $this->_close();
        // ----- Look for a local copy to delete
        if ($this->_temp_tarname != '')
            @unlink($this->_temp_tarname);
        $this->_PEARgw();
    }
    // }}}

    // {{{ create()
    /**
    * This method creates the archive file and add the files / directories
    * that are listed in $p_filelist.
    * If a file with the same name exist and is writable, it is replaced
    * by the new tar.
    * The method return false and a PEARgw error text.
    * The $p_filelist parameter can be an array of string, each string
    * representing a filename or a directory name with their path if
    * needed. It can also be a single string with names separated by a
    * single blank.
    * For each directory added in the archive, the files and
    * sub-directories are also added.
    * See also createModify() method for more details.
    *
    * @param array  $p_filelist An array of filenames and directory names, or a single
    *                           string with names separated by a single blank space.
    * @return                   true on success, false on error.
    * @see createModify()
    * @access public
    */
    function create($p_filelist)
    {
        return $this->createModify($p_filelist, '', '');
    }
    // }}}

    // {{{ add()
    /**
    * This method add the files / directories that are listed in $p_filelist in
    * the archive. If the archive does not exist it is created.
    * The method return false and a PEARgw error text.
    * The files and directories listed are only added at the end of the archive,
    * even if a file with the same name is already archived.
    * See also createModify() method for more details.
    *
    * @param array  $p_filelist An array of filenames and directory names, or a single
    *                           string with names separated by a single blank space.
    * @return                   true on success, false on error.
    * @see createModify()
    * @access public
    */
    function add($p_filelist)
    {
        return $this->addModify($p_filelist, '', '');
    }
    // }}}

    // {{{ extract()
    function extract($p_path='')
    {
        return $this->extractModify($p_path, '');
    }
    // }}}

    // {{{ listContent()
    function listContent()
    {
        $v_list_detail = array();

        if ($this->_openRead()) {
            if (!$this->_extractList('', $v_list_detail, "list", '', '')) {
                unset($v_list_detail);
                $v_list_detail = 0;
            }
            $this->_close();
        }

        return $v_list_detail;
    }
    // }}}

    // {{{ createModify()
    /**
    * This method creates the archive file and add the files / directories
    * that are listed in $p_filelist.
    * If the file already exists and is writable, it is replaced by the
    * new tar. It is a create and not an add. If the file exists and is
    * read-only or is a directory it is not replaced. The method return
    * false and a PEARgw error text.
    * The $p_filelist parameter can be an array of string, each string
    * representing a filename or a directory name with their path if
    * needed. It can also be a single string with names separated by a
    * single blank.
    * The path indicated in $p_remove_dir will be removed from the
    * memorized path of each file / directory listed when this path
    * exists. By default nothing is removed (empty path '')
    * The path indicated in $p_add_dir will be added at the beginning of
    * the memorized path of each file / directory listed. However it can
    * be set to empty ''. The adding of a path is done after the removing
    * of path.
    * The path add/remove ability enables the user to prepare an archive
    * for extraction in a different path than the origin files are.
    * See also addModify() method for file adding properties.
    *
    * @param array  $p_filelist     An array of filenames and directory names, or a single
    *                               string with names separated by a single blank space.
    * @param string $p_add_dir      A string which contains a path to be added to the
    *                               memorized path of each element in the list.
    * @param string $p_remove_dir   A string which contains a path to be removed from
    *                               the memorized path of each element in the list, when
    *                               relevant.
    * @return boolean               true on success, false on error.
    * @access public
    * @see addModify()
    */
    function createModify($p_filelist, $p_add_dir, $p_remove_dir='')
    {
        $v_result = true;

        if (!$this->_openWrite())
            return false;

        if ($p_filelist != '') {
            if (is_array($p_filelist))
                $v_list = $p_filelist;
            elseif (is_string($p_filelist))
                $v_list = explode(" ", $p_filelist);
            else {
                $this->_cleanFile();
                $this->_error('Invalid file list');
                return false;
            }

            $v_result = $this->_addList($v_list, $p_add_dir, $p_remove_dir);
        }

        if ($v_result) {
            $this->_writeFooter();
            $this->_close();
        } else
            $this->_cleanFile();

        return $v_result;
    }
    // }}}

    // {{{ addModify()
    /**
    * This method add the files / directories listed in $p_filelist at the
    * end of the existing archive. If the archive does not yet exists it
    * is created.
    * The $p_filelist parameter can be an array of string, each string
    * representing a filename or a directory name with their path if
    * needed. It can also be a single string with names separated by a
    * single blank.
    * The path indicated in $p_remove_dir will be removed from the
    * memorized path of each file / directory listed when this path
    * exists. By default nothing is removed (empty path '')
    * The path indicated in $p_add_dir will be added at the beginning of
    * the memorized path of each file / directory listed. However it can
    * be set to empty ''. The adding of a path is done after the removing
    * of path.
    * The path add/remove ability enables the user to prepare an archive
    * for extraction in a different path than the origin files are.
    * If a file/dir is already in the archive it will only be added at the
    * end of the archive. There is no update of the existing archived
    * file/dir. However while extracting the archive, the last file will
    * replace the first one. This results in a none optimization of the
    * archive size.
    * If a file/dir does not exist the file/dir is ignored. However an
    * error text is send to PEARgw error.
    * If a file/dir is not readable the file/dir is ignored. However an
    * error text is send to PEARgw error.
    *
    * @param array      $p_filelist     An array of filenames and directory names, or a single
    *                                   string with names separated by a single blank space.
    * @param string     $p_add_dir      A string which contains a path to be added to the
    *                                   memorized path of each element in the list.
    * @param string     $p_remove_dir   A string which contains a path to be removed from
    *                                   the memorized path of each element in the list, when
    *                                   relevant.
    * @return                           true on success, false on error.
    * @access public
    */
    function addModify($p_filelist, $p_add_dir, $p_remove_dir='')
    {
        $v_result = true;

        if (!@is_file($this->_tarname))
            $v_result = $this->createModify($p_filelist, $p_add_dir, $p_remove_dir);
        else {
            if (is_array($p_filelist))
                $v_list = $p_filelist;
            elseif (is_string($p_filelist))
                $v_list = explode(" ", $p_filelist);
            else {
                $this->_error('Invalid file list');
                return false;
            }

            $v_result = $this->_append($v_list, $p_add_dir, $p_remove_dir);
        }

        return $v_result;
    }
    // }}}

    // {{{ addString()
    /**
    * This method add a single string as a file at the
    * end of the existing archive. If the archive does not yet exists it
    * is created.
    *
    * @param string     $p_filename     A string which contains the full filename path
    *                                   that will be associated with the string.
    * @param string     $p_string       The content of the file added in the archive.
    * @return                           true on success, false on error.
    * @access public
    */
    function addString($p_filename, $p_string)
    {
        $v_result = true;

        if (!@is_file($this->_tarname)) {
            if (!$this->_openWrite()) {
                return false;
            }
            $this->_close();
        }
        
        if (!$this->_openAppend())
            return false;

        // Need to check the get back to the temporary file ? ....
        $v_result = $this->_addString($p_filename, $p_string);

        $this->_writeFooter();

        $this->_close();

        return $v_result;
    }
    // }}}

    // {{{ extractModify()
    /**
    * This method extract all the content of the archive in the directory
    * indicated by $p_path. When relevant the memorized path of the
    * files/dir can be modified by removing the $p_remove_path path at the
    * beginning of the file/dir path.
    * While extracting a file, if the directory path does not exists it is
    * created.
    * While extracting a file, if the file already exists it is replaced
    * without looking for last modification date.
    * While extracting a file, if the file already exists and is write
    * protected, the extraction is aborted.
    * While extracting a file, if a directory with the same name already
    * exists, the extraction is aborted.
    * While extracting a directory, if a file with the same name already
    * exists, the extraction is aborted.
    * While extracting a file/directory if the destination directory exist
    * and is write protected, or does not exist but can not be created,
    * the extraction is aborted.
    * If after extraction an extracted file does not show the correct
    * stored file size, the extraction is aborted.
    * When the extraction is aborted, a PEARgw error text is set and false
    * is returned. However the result can be a partial extraction that may
    * need to be manually cleaned.
    *
    * @param string $p_path         The path of the directory where the files/dir need to by
    *                               extracted.
    * @param string $p_remove_path  Part of the memorized path that can be removed if
    *                               present at the beginning of the file/dir path.
    * @return boolean               true on success, false on error.
    * @access public
    * @see extractList()
    */
    function extractModify($p_path, $p_remove_path)
    {
        $v_result = true;
        $v_list_detail = array();

        if ($v_result = $this->_openRead()) {
            $v_result = $this->_extractList($p_path, $v_list_detail, "complete", 0, $p_remove_path);
            $this->_close();
        }

        return $v_result;
    }
    // }}}

    // {{{ extractInString()
    /**
    * This method extract from the archive one file identified by $p_filename.
    * The return value is a string with the file content, or NULL on error.
    * @param string $p_filename     The path of the file to extract in a string.
    * @return                       a string with the file content or NULL.
    * @access public
    */
    function extractInString($p_filename)
    {
        if ($this->_openRead()) {
            $v_result = $this->_extractInString($p_filename);
            $this->_close();
        } else {
            $v_result = NULL;
        }

        return $v_result;
    }
    // }}}

    // {{{ extractList()
    /**
    * This method extract from the archive only the files indicated in the
    * $p_filelist. These files are extracted in the current directory or
    * in the directory indicated by the optional $p_path parameter.
    * If indicated the $p_remove_path can be used in the same way as it is
    * used in extractModify() method.
    * @param array  $p_filelist     An array of filenames and directory names, or a single
    *                               string with names separated by a single blank space.
    * @param string $p_path         The path of the directory where the files/dir need to by
    *                               extracted.
    * @param string $p_remove_path  Part of the memorized path that can be removed if
    *                               present at the beginning of the file/dir path.
    * @return                       true on success, false on error.
    * @access public
    * @see extractModify()
    */
    function extractList($p_filelist, $p_path='', $p_remove_path='')
    {
        $v_result = true;
        $v_list_detail = array();

        if (is_array($p_filelist))
            $v_list = $p_filelist;
        elseif (is_string($p_filelist))
            $v_list = explode(" ", $p_filelist);
        else {
            $this->_error('Invalid string list');
            return false;
        }

        if ($v_result = $this->_openRead()) {
            $v_result = $this->_extractList($p_path, $v_list_detail, "partial", $v_list, $p_remove_path);
            $this->_close();
        }

        return $v_result;
    }
    // }}}

    // {{{ _error()
    function _error($p_message)
    {
        // ----- To be completed
        $this->raiseError($p_message);
    }
    // }}}

    // {{{ _warning()
    function _warning($p_message)
    {
        // ----- To be completed
        $this->raiseError($p_message);
    }
    // }}}

    // {{{ _openWrite()
    function _openWrite()
    {
        if ($this->_compress_type == 'gz')
            $this->_file = @gzopen($this->_tarname, "wb");
        else if ($this->_compress_type == 'bz2')
            $this->_file = @bzopen($this->_tarname, "wb");
        else if ($this->_compress_type == 'none')
            $this->_file = @fopen($this->_tarname, "wb");
        else
            $this->_error('Unknown or missing compression type ('.$this->_compress_type.')');

        if ($this->_file == 0) {
            $this->_error('Unable to open in write mode \''.$this->_tarname.'\'');
            return false;
        }

        return true;
    }
    // }}}

    // {{{ _openRead()
    function _openRead()
    {
        if (strtolower(substr($this->_tarname, 0, 7)) == 'http://') {

          // ----- Look if a local copy need to be done
          if ($this->_temp_tarname == '') {
              $this->_temp_tarname = uniqid('tar').'.tmp';
              if (!$v_file_from = @fopen($this->_tarname, 'rb')) {
                $this->_error('Unable to open in read mode \''.$this->_tarname.'\'');
                $this->_temp_tarname = '';
                return false;
              }
              if (!$v_file_to = @fopen($this->_temp_tarname, 'wb')) {
                $this->_error('Unable to open in write mode \''.$this->_temp_tarname.'\'');
                $this->_temp_tarname = '';
                return false;
              }
              while ($v_data = @fread($v_file_from, 1024))
                  @fwrite($v_file_to, $v_data);
              @fclose($v_file_from);
              @fclose($v_file_to);
          }

          // ----- File to open if the local copy
          $v_filename = $this->_temp_tarname;

        } else
          // ----- File to open if the normal Tar file
          $v_filename = $this->_tarname;

        if ($this->_compress_type == 'gz')
            $this->_file = @gzopen($v_filename, "rb");
        else if ($this->_compress_type == 'bz2')
            $this->_file = @bzopen($v_filename, "rb");
        else if ($this->_compress_type == 'none')
            $this->_file = @fopen($v_filename, "rb");
        else
            $this->_error('Unknown or missing compression type ('.$this->_compress_type.')');

        if ($this->_file == 0) {
            $this->_error('Unable to open in read mode \''.$v_filename.'\'');
            return false;
        }

        return true;
    }
    // }}}

    // {{{ _openReadWrite()
    function _openReadWrite()
    {
        if ($this->_compress_type == 'gz')
            $this->_file = @gzopen($this->_tarname, "r+b");
        else if ($this->_compress_type == 'bz2')
            $this->_file = @bzopen($this->_tarname, "r+b");
        else if ($this->_compress_type == 'none')
            $this->_file = @fopen($this->_tarname, "r+b");
        else
            $this->_error('Unknown or missing compression type ('.$this->_compress_type.')');

        if ($this->_file == 0) {
            $this->_error('Unable to open in read/write mode \''.$this->_tarname.'\'');
            return false;
        }

        return true;
    }
    // }}}

    // {{{ _close()
    function _close()
    {
        if (isset($this->_file)) {
            if ($this->_compress_type == 'gz')
                @gzclose($this->_file);
            else if ($this->_compress_type == 'bz2')
                @bzclose($this->_file);
            else if ($this->_compress_type == 'none')
                @fclose($this->_file);
            else
                $this->_error('Unknown or missing compression type ('.$this->_compress_type.')');

            $this->_file = 0;
        }

        // ----- Look if a local copy need to be erase
        // Note that it might be interesting to keep the url for a time : ToDo
        if ($this->_temp_tarname != '') {
            @unlink($this->_temp_tarname);
            $this->_temp_tarname = '';
        }

        return true;
    }
    // }}}

    // {{{ _cleanFile()
    function _cleanFile()
    {
        $this->_close();

        // ----- Look for a local copy
        if ($this->_temp_tarname != '') {
            // ----- Remove the local copy but not the remote tarname
            @unlink($this->_temp_tarname);
            $this->_temp_tarname = '';
        } else {
            // ----- Remove the local tarname file
            @unlink($this->_tarname);
        }
        $this->_tarname = '';

        return true;
    }
    // }}}

    // {{{ _writeBlock()
    function _writeBlock($p_binary_data, $p_len=null)
    {
      if ($this->_file) {
          if ($p_len === null) {
              if ($this->_compress_type == 'gz')
                  @gzputs($this->_file, $p_binary_data);
              else if ($this->_compress_type == 'bz2')
                  @bzwrite($this->_file, $p_binary_data);
              else if ($this->_compress_type == 'none')
                  @fputs($this->_file, $p_binary_data);
              else
                  $this->_error('Unknown or missing compression type ('.$this->_compress_type.')');
          } else {
              if ($this->_compress_type == 'gz')
                  @gzputs($this->_file, $p_binary_data, $p_len);
              else if ($this->_compress_type == 'bz2')
                  @bzwrite($this->_file, $p_binary_data, $p_len);
              else if ($this->_compress_type == 'none')
                  @fputs($this->_file, $p_binary_data, $p_len);
              else
                  $this->_error('Unknown or missing compression type ('.$this->_compress_type.')');

          }
      }
      return true;
    }
    // }}}

    // {{{ _readBlock()
    function _readBlock($p_len=null)
    {
      $v_block = null;
      if ($this->_file) {
          if ($p_len === null)
              $p_len = 512;
              
          if ($this->_compress_type == 'gz')
              $v_block = @gzread($this->_file, 512);
          else if ($this->_compress_type == 'bz2')
              $v_block = @bzread($this->_file, 512);
          else if ($this->_compress_type == 'none')
              $v_block = @fread($this->_file, 512);
          else
              $this->_error('Unknown or missing compression type ('.$this->_compress_type.')');

      }
      return $v_block;
    }
    // }}}

    // {{{ _jumpBlock()
    function _jumpBlock($p_len=null)
    {
      if ($this->_file) {
          if ($p_len === null)
              $p_len = 1;

          if ($this->_compress_type == 'gz')
              @gzseek($this->_file, @gztell($this->_file)+($p_len*512));
          else if ($this->_compress_type == 'bz2') {
              // ----- Replace missing bztell() and bzseek()
              for ($i=0; $i<$p_len; $i++)
                  $this->_readBlock();
          } else if ($this->_compress_type == 'none')
              @fseek($this->_file, @ftell($this->_file)+($p_len*512));
          else
              $this->_error('Unknown or missing compression type ('.$this->_compress_type.')');

      }
      return true;
    }
    // }}}

    // {{{ _writeFooter()
    function _writeFooter()
    {
      if ($this->_file) {
          // ----- Write the last 0 filled block for end of archive
          $v_binary_data = pack("a512", '');
          $this->_writeBlock($v_binary_data);
      }
      return true;
    }
    // }}}

    // {{{ _addList()
    function _addList($p_list, $p_add_dir, $p_remove_dir)
    {
      $v_result=true;
      $v_header = array();

      // ----- Remove potential windows directory separator
      $p_add_dir = $this->_translateWinPath($p_add_dir);
      $p_remove_dir = $this->_translateWinPath($p_remove_dir, false);

      if (!$this->_file) {
          $this->_error('Invalid file descriptor');
          return false;
      }

      if (sizeof($p_list) == 0)
          return true;

      for ($j=0; ($j<count($p_list)) && ($v_result); $j++) {
        $v_filename = $p_list[$j];

        // ----- Skip the current tar name
        if ($v_filename == $this->_tarname)
            continue;

        if ($v_filename == '')
            continue;

        if (!file_exists($v_filename)) {
            $this->_warning("File '$v_filename' does not exist");
            continue;
        }

        // ----- Add the file or directory header
        if (!$this->_addFile($v_filename, $v_header, $p_add_dir, $p_remove_dir))
            return false;

        if (@is_dir($v_filename)) {
            if (!($p_hdir = opendir($v_filename))) {
                $this->_warning("Directory '$v_filename' can not be read");
                continue;
            }
            $p_hitem = readdir($p_hdir); // '.' directory
            $p_hitem = readdir($p_hdir); // '..' directory
            while (false !== ($p_hitem = readdir($p_hdir))) {
                if ($v_filename != ".")
                    $p_temp_list[0] = $v_filename.'/'.$p_hitem;
                else
                    $p_temp_list[0] = $p_hitem;

                $v_result = $this->_addList($p_temp_list, $p_add_dir, $p_remove_dir);
            }

            unset($p_temp_list);
            unset($p_hdir);
            unset($p_hitem);
        }
      }

      return $v_result;
    }
    // }}}

    // {{{ _addFile()
    function _addFile($p_filename, &$p_header, $p_add_dir, $p_remove_dir)
    {
      if (!$this->_file) {
          $this->_error('Invalid file descriptor');
          return false;
      }

      if ($p_filename == '') {
          $this->_error('Invalid file name');
          return false;
      }

      // ----- Calculate the stored filename
      $p_filename = $this->_translateWinPath($p_filename, false);;
      $v_stored_filename = $p_filename;
      if (strcmp($p_filename, $p_remove_dir) == 0) {
          return true;
      }
      if ($p_remove_dir != '') {
          if (substr($p_remove_dir, -1) != '/')
              $p_remove_dir .= '/';

          if (substr($p_filename, 0, strlen($p_remove_dir)) == $p_remove_dir)
              $v_stored_filename = substr($p_filename, strlen($p_remove_dir));
      }
      $v_stored_filename = $this->_translateWinPath($v_stored_filename);
      if ($p_add_dir != '') {
          if (substr($p_add_dir, -1) == '/')
              $v_stored_filename = $p_add_dir.$v_stored_filename;
          else
              $v_stored_filename = $p_add_dir.'/'.$v_stored_filename;
      }

      $v_stored_filename = $this->_pathReduction($v_stored_filename);

      if (is_file($p_filename)) {
          if (($v_file = @fopen($p_filename, "rb")) == 0) {
              $this->_warning("Unable to open file '$p_filename' in binary read mode");
              return true;
          }

          if (!$this->_writeHeader($p_filename, $v_stored_filename))
              return false;

          while (($v_buffer = fread($v_file, 512)) != '') {
              $v_binary_data = pack("a512", "$v_buffer");
              $this->_writeBlock($v_binary_data);
          }

          fclose($v_file);

      } else {
          // ----- Only header for dir
          if (!$this->_writeHeader($p_filename, $v_stored_filename))
              return false;
      }

      return true;
    }
    // }}}

    // {{{ _addString()
    function _addString($p_filename, $p_string)
    {
      if (!$this->_file) {
          $this->_error('Invalid file descriptor');
          return false;
      }

      if ($p_filename == '') {
          $this->_error('Invalid file name');
          return false;
      }

      // ----- Calculate the stored filename
      $p_filename = $this->_translateWinPath($p_filename, false);;

      if (!$this->_writeHeaderBlock($p_filename, strlen($p_string), 0, 0, "", 0, 0))
          return false;

      $i=0;
      while (($v_buffer = substr($p_string, (($i++)*512), 512)) != '') {
          $v_binary_data = pack("a512", $v_buffer);
          $this->_writeBlock($v_binary_data);
      }

      return true;
    }
    // }}}

    // {{{ _writeHeader()
    function _writeHeader($p_filename, $p_stored_filename)
    {
        if ($p_stored_filename == '')
            $p_stored_filename = $p_filename;
        $v_reduce_filename = $this->_pathReduction($p_stored_filename);

        if (strlen($v_reduce_filename) > 99) {
          if (!$this->_writeLongHeader($v_reduce_filename))
            return false;
        }

        $v_info = stat($p_filename);
        $v_uid = sprintf("%6s ", DecOct($v_info[4]));
        $v_gid = sprintf("%6s ", DecOct($v_info[5]));
        $v_perms = sprintf("%6s ", DecOct(fileperms($p_filename)));

        $v_mtime = sprintf("%11s", DecOct(filemtime($p_filename)));

        if (@is_dir($p_filename)) {
          $v_typeflag = "5";
          $v_size = sprintf("%11s ", DecOct(0));
        } else {
          $v_typeflag = '';
          clearstatcache();
          $v_size = sprintf("%11s ", DecOct(filesize($p_filename)));
        }

        $v_linkname = '';

        $v_magic = '';

        $v_version = '';

        $v_uname = '';

        $v_gname = '';

        $v_devmajor = '';

        $v_devminor = '';

        $v_prefix = '';

        $v_binary_data_first = pack("a100a8a8a8a12A12", $v_reduce_filename, $v_perms, $v_uid, $v_gid, $v_size, $v_mtime);
        $v_binary_data_last = pack("a1a100a6a2a32a32a8a8a155a12", $v_typeflag, $v_linkname, $v_magic, $v_version, $v_uname, $v_gname, $v_devmajor, $v_devminor, $v_prefix, '');

        // ----- Calculate the checksum
        $v_checksum = 0;
        // ..... First part of the header
        for ($i=0; $i<148; $i++)
            $v_checksum += ord(substr($v_binary_data_first,$i,1));
        // ..... Ignore the checksum value and replace it by ' ' (space)
        for ($i=148; $i<156; $i++)
            $v_checksum += ord(' ');
        // ..... Last part of the header
        for ($i=156, $j=0; $i<512; $i++, $j++)
            $v_checksum += ord(substr($v_binary_data_last,$j,1));

        // ----- Write the first 148 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_first, 148);

        // ----- Write the calculated checksum
        $v_checksum = sprintf("%6s ", DecOct($v_checksum));
        $v_binary_data = pack("a8", $v_checksum);
        $this->_writeBlock($v_binary_data, 8);

        // ----- Write the last 356 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_last, 356);

        return true;
    }
    // }}}

    // {{{ _writeHeaderBlock()
    function _writeHeaderBlock($p_filename, $p_size, $p_mtime=0, $p_perms=0, $p_type='', $p_uid=0, $p_gid=0)
    {
        $p_filename = $this->_pathReduction($p_filename);

        if (strlen($p_filename) > 99) {
          if (!$this->_writeLongHeader($p_filename))
            return false;
        }

        if ($p_type == "5") {
          $v_size = sprintf("%11s ", DecOct(0));
        } else {
          $v_size = sprintf("%11s ", DecOct($p_size));
        }

        $v_uid = sprintf("%6s ", DecOct($p_uid));
        $v_gid = sprintf("%6s ", DecOct($p_gid));
        $v_perms = sprintf("%6s ", DecOct($p_perms));

        $v_mtime = sprintf("%11s", DecOct($p_mtime));

        $v_linkname = '';

        $v_magic = '';

        $v_version = '';

        $v_uname = '';

        $v_gname = '';

        $v_devmajor = '';

        $v_devminor = '';

        $v_prefix = '';

        $v_binary_data_first = pack("a100a8a8a8a12A12", $p_filename, $v_perms, $v_uid, $v_gid, $v_size, $v_mtime);
        $v_binary_data_last = pack("a1a100a6a2a32a32a8a8a155a12", $p_type, $v_linkname, $v_magic, $v_version, $v_uname, $v_gname, $v_devmajor, $v_devminor, $v_prefix, '');

        // ----- Calculate the checksum
        $v_checksum = 0;
        // ..... First part of the header
        for ($i=0; $i<148; $i++)
            $v_checksum += ord(substr($v_binary_data_first,$i,1));
        // ..... Ignore the checksum value and replace it by ' ' (space)
        for ($i=148; $i<156; $i++)
            $v_checksum += ord(' ');
        // ..... Last part of the header
        for ($i=156, $j=0; $i<512; $i++, $j++)
            $v_checksum += ord(substr($v_binary_data_last,$j,1));

        // ----- Write the first 148 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_first, 148);

        // ----- Write the calculated checksum
        $v_checksum = sprintf("%6s ", DecOct($v_checksum));
        $v_binary_data = pack("a8", $v_checksum);
        $this->_writeBlock($v_binary_data, 8);

        // ----- Write the last 356 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_last, 356);

        return true;
    }
    // }}}

    // {{{ _writeLongHeader()
    function _writeLongHeader($p_filename)
    {
        $v_size = sprintf("%11s ", DecOct(strlen($p_filename)));

        $v_typeflag = 'L';

        $v_linkname = '';

        $v_magic = '';

        $v_version = '';

        $v_uname = '';

        $v_gname = '';

        $v_devmajor = '';

        $v_devminor = '';

        $v_prefix = '';

        $v_binary_data_first = pack("a100a8a8a8a12A12", '././@LongLink', 0, 0, 0, $v_size, 0);
        $v_binary_data_last = pack("a1a100a6a2a32a32a8a8a155a12", $v_typeflag, $v_linkname, $v_magic, $v_version, $v_uname, $v_gname, $v_devmajor, $v_devminor, $v_prefix, '');

        // ----- Calculate the checksum
        $v_checksum = 0;
        // ..... First part of the header
        for ($i=0; $i<148; $i++)
            $v_checksum += ord(substr($v_binary_data_first,$i,1));
        // ..... Ignore the checksum value and replace it by ' ' (space)
        for ($i=148; $i<156; $i++)
            $v_checksum += ord(' ');
        // ..... Last part of the header
        for ($i=156, $j=0; $i<512; $i++, $j++)
            $v_checksum += ord(substr($v_binary_data_last,$j,1));

        // ----- Write the first 148 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_first, 148);

        // ----- Write the calculated checksum
        $v_checksum = sprintf("%6s ", DecOct($v_checksum));
        $v_binary_data = pack("a8", $v_checksum);
        $this->_writeBlock($v_binary_data, 8);

        // ----- Write the last 356 bytes of the header in the archive
        $this->_writeBlock($v_binary_data_last, 356);

        // ----- Write the filename as content of the block
        $i=0;
        while (($v_buffer = substr($p_filename, (($i++)*512), 512)) != '') {
            $v_binary_data = pack("a512", "$v_buffer");
            $this->_writeBlock($v_binary_data);
        }

        return true;
    }
    // }}}

    // {{{ _readHeader()
    function _readHeader($v_binary_data, &$v_header)
    {
        if (strlen($v_binary_data)==0) {
            $v_header['filename'] = '';
            return true;
        }

        if (strlen($v_binary_data) != 512) {
            $v_header['filename'] = '';
            $this->_error('Invalid block size : '.strlen($v_binary_data));
            return false;
        }

        // ----- Calculate the checksum
        $v_checksum = 0;
        // ..... First part of the header
        for ($i=0; $i<148; $i++)
            $v_checksum+=ord(substr($v_binary_data,$i,1));
        // ..... Ignore the checksum value and replace it by ' ' (space)
        for ($i=148; $i<156; $i++)
            $v_checksum += ord(' ');
        // ..... Last part of the header
        for ($i=156; $i<512; $i++)
           $v_checksum+=ord(substr($v_binary_data,$i,1));

        $v_data = unpack("a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1typeflag/a100link/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor", $v_binary_data);

        // ----- Extract the checksum
        $v_header['checksum'] = OctDec(trim($v_data['checksum']));
        if ($v_header['checksum'] != $v_checksum) {
            $v_header['filename'] = '';

            // ----- Look for last block (empty block)
            if (($v_checksum == 256) && ($v_header['checksum'] == 0))
                return true;

            $this->_error('Invalid checksum for file "'.$v_data['filename'].'" : '.$v_checksum.' calculated, '.$v_header['checksum'].' expected');
            return false;
        }

        // ----- Extract the properties
        $v_header['filename'] = trim($v_data['filename']);
        $v_header['mode'] = OctDec(trim($v_data['mode']));
        $v_header['uid'] = OctDec(trim($v_data['uid']));
        $v_header['gid'] = OctDec(trim($v_data['gid']));
        $v_header['size'] = OctDec(trim($v_data['size']));
        $v_header['mtime'] = OctDec(trim($v_data['mtime']));
        if (($v_header['typeflag'] = $v_data['typeflag']) == "5") {
          $v_header['size'] = 0;
        }
        /* ----- All these fields are removed form the header because they do not carry interesting info
        $v_header[link] = trim($v_data[link]);
        $v_header[magic] = trim($v_data[magic]);
        $v_header[version] = trim($v_data[version]);
        $v_header[uname] = trim($v_data[uname]);
        $v_header[gname] = trim($v_data[gname]);
        $v_header[devmajor] = trim($v_data[devmajor]);
        $v_header[devminor] = trim($v_data[devminor]);
        */

        return true;
    }
    // }}}

    // {{{ _readLongHeader()
    function _readLongHeader(&$v_header)
    {
      $v_filename = '';
      $n = floor($v_header['size']/512);
      for ($i=0; $i<$n; $i++) {
        $v_content = $this->_readBlock();
        $v_filename .= $v_content;
      }
      if (($v_header['size'] % 512) != 0) {
        $v_content = $this->_readBlock();
        $v_filename .= $v_content;
      }

      // ----- Read the next header
      $v_binary_data = $this->_readBlock();

      if (!$this->_readHeader($v_binary_data, $v_header))
        return false;

      $v_header['filename'] = $v_filename;

      return true;
    }
    // }}}

    // {{{ _extractInString()
    /**
    * This method extract from the archive one file identified by $p_filename.
    * The return value is a string with the file content, or NULL on error.
    * @param string $p_filename     The path of the file to extract in a string.
    * @return                       a string with the file content or NULL.
    * @access private
    */
    function _extractInString($p_filename)
    {
        $v_result_str = "";

        While (strlen($v_binary_data = $this->_readBlock()) != 0)
        {
          if (!$this->_readHeader($v_binary_data, $v_header))
            return NULL;

          if ($v_header['filename'] == '')
            continue;

          // ----- Look for long filename
          if ($v_header['typeflag'] == 'L') {
            if (!$this->_readLongHeader($v_header))
              return NULL;
          }

          if ($v_header['filename'] == $p_filename) {
              if ($v_header['typeflag'] == "5") {
                  $this->_error('Unable to extract in string a directory entry {'.$v_header['filename'].'}');
                  return NULL;
              } else {
                  $n = floor($v_header['size']/512);
                  for ($i=0; $i<$n; $i++) {
                      $v_result_str .= $this->_readBlock();
                  }
                  if (($v_header['size'] % 512) != 0) {
                      $v_content = $this->_readBlock();
                      $v_result_str .= substr($v_content, 0, ($v_header['size'] % 512));
                  }
                  return $v_result_str;
              }
          } else {
              $this->_jumpBlock(ceil(($v_header['size']/512)));
          }
        }

        return NULL;
    }
    // }}}

    // {{{ _extractList()
    function _extractList($p_path, &$p_list_detail, $p_mode, $p_file_list, $p_remove_path)
    {
    $v_result=true;
    $v_nb = 0;
    $v_extract_all = true;
    $v_listing = false;

    $p_path = $this->_translateWinPath($p_path, false);
    if ($p_path == '' || (substr($p_path, 0, 1) != '/' && substr($p_path, 0, 3) != "../" && !strpos($p_path, ':'))) {
      $p_path = "./".$p_path;
    }
    $p_remove_path = $this->_translateWinPath($p_remove_path);

    // ----- Look for path to remove format (should end by /)
    if (($p_remove_path != '') && (substr($p_remove_path, -1) != '/'))
      $p_remove_path .= '/';
    $p_remove_path_size = strlen($p_remove_path);

    switch ($p_mode) {
      case "complete" :
        $v_extract_all = TRUE;
        $v_listing = FALSE;
      break;
      case "partial" :
          $v_extract_all = FALSE;
          $v_listing = FALSE;
      break;
      case "list" :
          $v_extract_all = FALSE;
          $v_listing = TRUE;
      break;
      default :
        $this->_error('Invalid extract mode ('.$p_mode.')');
        return false;
    }

    clearstatcache();

    While (strlen($v_binary_data = $this->_readBlock()) != 0)
    {
      $v_extract_file = FALSE;
      $v_extraction_stopped = 0;

      if (!$this->_readHeader($v_binary_data, $v_header))
        return false;

      if ($v_header['filename'] == '')
        continue;

      // ----- Look for long filename
      if ($v_header['typeflag'] == 'L') {
        if (!$this->_readLongHeader($v_header))
          return false;
      }

      if ((!$v_extract_all) && (is_array($p_file_list))) {
        // ----- By default no unzip if the file is not found
        $v_extract_file = false;

        for ($i=0; $i<sizeof($p_file_list); $i++) {
          // ----- Look if it is a directory
          if (substr($p_file_list[$i], -1) == '/') {
            // ----- Look if the directory is in the filename path
            if ((strlen($v_header['filename']) > strlen($p_file_list[$i])) && (substr($v_header['filename'], 0, strlen($p_file_list[$i])) == $p_file_list[$i])) {
              $v_extract_file = TRUE;
              break;
            }
          }

          // ----- It is a file, so compare the file names
          elseif ($p_file_list[$i] == $v_header['filename']) {
            $v_extract_file = TRUE;
            break;
          }
        }
      } else {
        $v_extract_file = TRUE;
      }

      // ----- Look if this file need to be extracted
      if (($v_extract_file) && (!$v_listing))
      {
        if (($p_remove_path != '')
            && (substr($v_header['filename'], 0, $p_remove_path_size) == $p_remove_path))
          $v_header['filename'] = substr($v_header['filename'], $p_remove_path_size);
        if (($p_path != './') && ($p_path != '/')) {
          while (substr($p_path, -1) == '/')
            $p_path = substr($p_path, 0, strlen($p_path)-1);

          if (substr($v_header['filename'], 0, 1) == '/')
              $v_header['filename'] = $p_path.$v_header['filename'];
          else
            $v_header['filename'] = $p_path.'/'.$v_header['filename'];
        }
        if (file_exists($v_header['filename'])) {
          if ((@is_dir($v_header['filename'])) && ($v_header['typeflag'] == '')) {
            $this->_error('File '.$v_header['filename'].' already exists as a directory');
            return false;
          }
          if ((is_file($v_header['filename'])) && ($v_header['typeflag'] == "5")) {
            $this->_error('Directory '.$v_header['filename'].' already exists as a file');
            return false;
          }
          if (!is_writeable($v_header['filename'])) {
            $this->_error('File '.$v_header['filename'].' already exists and is write protected');
            return false;
          }
          if (filemtime($v_header['filename']) > $v_header['mtime']) {
            // To be completed : An error or silent no replace ?
          }
        }

        // ----- Check the directory availability and create it if necessary
        elseif (($v_result = $this->_dirCheck(($v_header['typeflag'] == "5"?$v_header['filename']:dirname($v_header['filename'])))) != 1) {
            $this->_error('Unable to create path for '.$v_header['filename']);
            return false;
        }

        if ($v_extract_file) {
          if ($v_header['typeflag'] == "5") {
            if (!@file_exists($v_header['filename'])) {
                if (!@mkdir($v_header['filename'], 0777)) {
                    $this->_error('Unable to create directory {'.$v_header['filename'].'}');
                    return false;
                }
            }
          } else {
              if (($v_dest_file = @fopen($v_header['filename'], "wb")) == 0) {
                  $this->_error('Error while opening {'.$v_header['filename'].'} in write binary mode');
                  return false;
              } else {
                  $n = floor($v_header['size']/512);
                  for ($i=0; $i<$n; $i++) {
                      $v_content = $this->_readBlock();
                      fwrite($v_dest_file, $v_content, 512);
                  }
            if (($v_header['size'] % 512) != 0) {
              $v_content = $this->_readBlock();
              fwrite($v_dest_file, $v_content, ($v_header['size'] % 512));
            }

            @fclose($v_dest_file);

            // ----- Change the file mode, mtime
            @touch($v_header['filename'], $v_header['mtime']);
            // To be completed
            //chmod($v_header[filename], DecOct($v_header[mode]));
          }

          // ----- Check the file size
          clearstatcache();
          if (filesize($v_header['filename']) != $v_header['size']) {
              $this->_error('Extracted file '.$v_header['filename'].' does not have the correct file size \''.filesize($v_filename).'\' ('.$v_header['size'].' expected). Archive may be corrupted.');
              return false;
          }
          }
        } else {
          $this->_jumpBlock(ceil(($v_header['size']/512)));
        }
      } else {
          $this->_jumpBlock(ceil(($v_header['size']/512)));
      }

      /* TBC : Seems to be unused ...
      if ($this->_compress)
        $v_end_of_file = @gzeof($this->_file);
      else
        $v_end_of_file = @feof($this->_file);
        */

      if ($v_listing || $v_extract_file || $v_extraction_stopped) {
        // ----- Log extracted files
        if (($v_file_dir = dirname($v_header['filename'])) == $v_header['filename'])
          $v_file_dir = '';
        if ((substr($v_header['filename'], 0, 1) == '/') && ($v_file_dir == ''))
          $v_file_dir = '/';

        $p_list_detail[$v_nb++] = $v_header;
      }
    }

        return true;
    }
    // }}}

    // {{{ _openAppend()
    function _openAppend()
    {
        if (filesize($this->_tarname) == 0)
          return $this->_openWrite();
          
        if ($this->_compress) {
            $this->_close();

            if (!@rename($this->_tarname, $this->_tarname.".tmp")) {
                $this->_error('Error while renaming \''.$this->_tarname.'\' to temporary file \''.$this->_tarname.'.tmp\'');
                return false;
            }

            if ($this->_compress_type == 'gz')
                $v_temp_tar = @gzopen($this->_tarname.".tmp", "rb");
            elseif ($this->_compress_type == 'bz2')
                $v_temp_tar = @bzopen($this->_tarname.".tmp", "rb");
                
            if ($v_temp_tar == 0) {
                $this->_error('Unable to open file \''.$this->_tarname.'.tmp\' in binary read mode');
                @rename($this->_tarname.".tmp", $this->_tarname);
                return false;
            }

            if (!$this->_openWrite()) {
                @rename($this->_tarname.".tmp", $this->_tarname);
                return false;
            }

            if ($this->_compress_type == 'gz') {
                $v_buffer = @gzread($v_temp_tar, 512);

                // ----- Read the following blocks but not the last one
                if (!@gzeof($v_temp_tar)) {
                    do{
                        $v_binary_data = pack("a512", $v_buffer);
                        $this->_writeBlock($v_binary_data);
                        $v_buffer = @gzread($v_temp_tar, 512);

                    } while (!@gzeof($v_temp_tar));
                }

                @gzclose($v_temp_tar);
            }
            elseif ($this->_compress_type == 'bz2') {
                $v_buffered_lines   = array();
                $v_buffered_lines[] = @bzread($v_temp_tar, 512);

                // ----- Read the following blocks but not the last one
                while (strlen($v_buffered_lines[] = @bzread($v_temp_tar, 512)) > 0) {
                    $v_binary_data = pack("a512", array_shift($v_buffered_lines));
                    $this->_writeBlock($v_binary_data);
                }

                @bzclose($v_temp_tar);
            }

            if (!@unlink($this->_tarname.".tmp")) {
                $this->_error('Error while deleting temporary file \''.$this->_tarname.'.tmp\'');
            }

        } else {
            // ----- For not compressed tar, just add files before the last 512 bytes block
            if (!$this->_openReadWrite())
               return false;

            clearstatcache();
            $v_size = filesize($this->_tarname);
            fseek($this->_file, $v_size-512);
        }

        return true;
    }
    // }}}

    // {{{ _append()
    function _append($p_filelist, $p_add_dir='', $p_remove_dir='')
    {
        if (!$this->_openAppend())
            return false;
            
        if ($this->_addList($p_filelist, $p_add_dir, $p_remove_dir))
           $this->_writeFooter();

        $this->_close();

        return true;
    }
    // }}}

    // {{{ _dirCheck()

    /**
     * Check if a directory exists and create it (including parent
     * dirs) if not.
     *
     * @param string $p_dir directory to check
     *
     * @return bool TRUE if the directory exists or was created
     */
    function _dirCheck($p_dir)
    {
        if ((@is_dir($p_dir)) || ($p_dir == ''))
            return true;

        $p_parent_dir = dirname($p_dir);

        if (($p_parent_dir != $p_dir) &&
            ($p_parent_dir != '') &&
            (!$this->_dirCheck($p_parent_dir)))
             return false;

        if (!@mkdir($p_dir, 0777)) {
            $this->_error("Unable to create directory '$p_dir'");
            return false;
        }

        return true;
    }

    // }}}

    // {{{ _pathReduction()

    /**
     * Compress path by changing for example "/dir/foo/../bar" to "/dir/bar", and
     * remove double slashes.
     *
     * @param string $p_dir path to reduce
     *
     * @return string reduced path
     *
     * @access private
     *
     */
    function _pathReduction($p_dir)
    {
        $v_result = '';

        // ----- Look for not empty path
        if ($p_dir != '') {
            // ----- Explode path by directory names
            $v_list = explode('/', $p_dir);

            // ----- Study directories from last to first
            for ($i=sizeof($v_list)-1; $i>=0; $i--) {
                // ----- Look for current path
                if ($v_list[$i] == ".") {
                    // ----- Ignore this directory
                    // Should be the first $i=0, but no check is done
                }
                else if ($v_list[$i] == "..") {
                    // ----- Ignore it and ignore the $i-1
                    $i--;
                }
                else if (($v_list[$i] == '') && ($i!=(sizeof($v_list)-1)) && ($i!=0)) {
                    // ----- Ignore only the double '//' in path,
                    // but not the first and last /
                } else {
                    $v_result = $v_list[$i].($i!=(sizeof($v_list)-1)?'/'.$v_result:'');
                }
            }
        }
        $v_result = strtr($v_result, '\\', '/');
        return $v_result;
    }

    // }}}

    // {{{ _translateWinPath()
    function _translateWinPath($p_path, $p_remove_disk_letter=true)
    {
      if (OS_WINDOWS) {
          // ----- Look for potential disk letter
          if (($p_remove_disk_letter) && (($v_position = strpos($p_path, ':')) != false)) {
              $p_path = substr($p_path, $v_position+1);
          }
          // ----- Change potential windows directory separator
          if ((strpos($p_path, '\\') > 0) || (substr($p_path, 0,1) == '\\')) {
              $p_path = strtr($p_path, '\\', '/');
          }
      }
      return $p_path;
    }
    // }}}

}
?>
