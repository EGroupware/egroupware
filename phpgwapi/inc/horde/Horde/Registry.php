<?php

/** Do not start a session. */
define('HORDE_SESSION_NONE', 1);

/** Do not write changes to session. */
define('HORDE_SESSION_READONLY', 2);

/**
 * The Registry:: class provides a set of methods for communication
 * between Horde applications and keeping track of application
 * configuration information.
 *
 * $Horde: framework/Horde/Horde/Registry.php,v 1.249 2005/02/14 18:00:59 jan Exp $
 *
 * Copyright 1999-2005 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 1999-2005 Jon Parise <jon@horde.org>
 * Copyright 1999-2005 Anil Madhavapeddy <anil@recoil.org>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @author  Anil Madhavapeddy <anil@recoil.org>
 * @since   Horde 1.3
 * @package Horde_Framework
 */
class Registry {

    /**
     * Hash storing all of the known services and callbacks.
     *
     * @var array $_apiCache
     */
    var $_apiCache = array();

    /**
     * Hash storing all known data types.
     *
     * @var array $_typeCache
     */
    var $_typeCache = array();

    /**
     * Hash storing all of the registered interfaces that applications
     * provide.
     *
     * @var array $_interfaces
     */
    var $_interfaces = array();

    /**
     * Hash storing information on each registry-aware application.
     *
     * @var array $applications
     */
    var $applications = array();

    /**
     * Stack of in-use applications.
     *
     * @var array $_appStack
     */
    var $_appStack = array();

    /**
     * Quick pointer to the current application.
     *
     * @var $_currentApp
     */
    var $_currentApp = null;

    /**
     * Cache of $prefs objects
     *
     * @var array $_prefsCache
     */
    var $_prefsCache = array();

    /**
     * Cache of application configurations.
     *
     * @var array $_confCache
     */
    var $_confCache = array();

    /**
     * Returns a reference to the global Registry object, only
     * creating it if it doesn't already exist.
     *
     * This method must be invoked as: $registry = &Registry::singleton()
     *
     * @param optional integer $session_flags  Any session flags.
     *
     * @return object Registry  The Horde Registry instance.
     */
    function &singleton($session_flags = 0)
    {
        static $registry;

        if (!isset($registry)) {
            $registry = new Registry($session_flags);
        }

        return $registry;
    }

    /**
     * Create a new registry instance. Should never be called except
     * by &Registry::singleton().
     *
     * @param optional integer $session_flags  Any session flags.
     *
     * @access private
     */
    function Registry($session_flags = 0)
    {
        /* Import and global Horde's configuration values. */
        $this->importConfig('horde');

        /* Start a session. */
        if ($session_flags & HORDE_SESSION_NONE) {
            /* Never start a session if the session flags include
               HORDE_SESSION_NONE. */
            $_SESSION = array();
        } else {
            Horde::setupSessionHandler();
            @session_start();
            if ($session_flags & HORDE_SESSION_READONLY) {
                /* Close the session immediately so no changes can be
                   made but values are still available. */
                @session_write_close();
            }
        }

        /* Read the registry configuration file. */
        require_once HORDE_BASE . '/config/registry.php';

        /* Initialize the localization routines and variables. */
#        NLS::setLang();
#        NLS::setTextdomain('horde', HORDE_BASE . '/locale', NLS::getCharset());
#        String::setDefaultCharset(NLS::getCharset());

        /* Stop system if Horde is inactive. */
        if ($this->applications['horde']['status'] == 'inactive') {
            Horde::fatal(_("This system is currently deactivated."), __FILE__, __LINE__);
        }

        /* Scan for all APIs provided by each app, and set other
         * common defaults like templates and graphics. */
        $appList = array_keys($this->applications);
        foreach ($appList as $appName) {
            $app = &$this->applications[$appName];
            if (($app['status'] == 'heading') ||
                ($app['status'] == 'inactive') ||
                ($app['status'] == 'admin' && !Auth::isAdmin())) {
                continue;
            }
            if (isset($app['provides'])) {
                if (is_array($app['provides'])) {
                    foreach ($app['provides'] as $interface) {
                        $this->_interfaces[$interface] = $appName;
                    }
                } else {
                    $this->_interfaces[$app['provides']] = $appName;
                }
            }
            if (!isset($app['templates']) && isset($app['fileroot'])) {
                $app['templates'] = $app['fileroot'] . '/templates';
            }
            if (!isset($app['jsuri']) && isset($app['webroot'])) {
                $app['jsuri'] = $app['webroot'] . '/js';
            }
            if (!isset($app['jsfs']) && isset($app['fileroot'])) {
                $app['jsfs'] = $app['fileroot'] . '/js';
            }
            if (!isset($app['themesuri']) && isset($app['webroot'])) {
                $app['themesuri'] = $app['webroot'] . '/themes';
            }
            if (!isset($app['themesfs']) && isset($app['fileroot'])) {
                $app['themesfs'] = $app['fileroot'] . '/themes';
            }
        }

#        /* Create the global Perms object. */
#        $GLOBALS['perms'] = &Perms::singleton();

#        /* Attach javascript notification listener. */
#        $notification = &Notification::singleton();
#        $notification->attach('javascript');

        /* Register access key logger for translators. */
        if (@$GLOBALS['conf']['log_accesskeys']) {
            register_shutdown_function(create_function('', 'Horde::getAccessKey(null, null, true);'));
        }
    }

    /**
     * Return a list of the installed and registered applications.
     *
     * @since Horde 2.2
     *
     * @access public
     *
     * @param array   $filter      (optional) An array of the statuses that
     *                             should be returned. Defaults to non-hidden.
     * @param boolean $assoc       (optional) Associative array with app names
     *                             as keys.
     * @param integer $permission  (optional) The permission level to check
     *                             for in the list. Defaults to PERMS_SHOW.
     *
     * @return array  List of apps registered with Horde. If no
     *                applications are defined returns an empty array.
     */
    function listApps($filter = null, $assoc = false, $permission = PERMS_SHOW)
    {
        $apps = array();
        if (is_null($filter)) {
            $filter = array('notoolbar', 'active');
        }

        foreach ($this->applications as $app => $params) {
            if (in_array($params['status'], $filter) &&
                (defined('AUTH_HANDLER') || $this->hasPermission($app, $permission))) {
                $assoc ? $apps[$app] = $app : $apps[] = $app;
            }
        }

        return $apps;
    }

    /**
     * Returns all available registry APIs.
     *
     * @access public
     *
     * @return array  The API list.
     */
    function listAPIs()
    {
        $apis = array();

        foreach (array_keys($this->_interfaces) as $interface) {
            @list($api, ) = explode('/', $interface);
            $apis[] = $api;
        }

        return array_unique($apis);
    }

    /**
     * Returns all of the available registry methods, or alternately
     * only those for a specified API.
     *
     * @access public
     *
     * @param optional string $api  Defines the API for which the methods
     *                              shall be returned.
     *
     * @return array  The method list.
     */
    function listMethods($api = null)
    {
        $methods = array();

        $this->_fillAPICache();

        foreach (array_keys($this->applications) as $app) {
            if (isset($this->applications[$app]['provides'])) {
                $provides = $this->applications[$app]['provides'];
                if (!is_array($provides)) {
                    $provides = array($provides);
                }
            } else {
                $provides = array();
            }

            foreach ($provides as $method) {
                if (strpos($method, '/') !== false) {
                    if (is_null($api) ||
                        (substr($method, 0, strlen($api)) == $api)) {
                        $methods[] = $method;
                    }
                } elseif (is_null($api) || ($method == $api)) {
                    if (isset($this->_apiCache[$app])) {
                        foreach (array_keys($this->_apiCache[$app]) as $service) {
                            $methods[] = $method . '/' . $service;
                        }
                    }
                }
            }
        }

        return array_unique($methods);
    }

    /**
     * Returns all of the available registry data types.
     *
     * @access public
     *
     * @return array  The data type list.
     */
    function listTypes()
    {
        $this->_fillAPICache();
        return $this->_typeCache;
    }

    /**
     * Returns a method's signature.
     *
     * @access public
     *
     * @param string $method  The full name of the method to check for.
     *
     * @return array  A two dimensional array. The first element contains an
     *                array with the parameter names, the second one the return
     *                type.
     */
    function getSignature($method)
    {
        if (!($app = $this->hasMethod($method))) {
            return;
        }

        $this->_fillAPICache();

        @list(, $function) = explode('/', $method);
        if (isset($this->_apiCache[$app][$function]['type']) &&
            isset($this->_apiCache[$app][$function]['args'])) {
            return array($this->_apiCache[$app][$function]['args'], $this->_apiCache[$app][$function]['type']);
        }
    }

    /**
     * Determine if an interface is implemented by an active
     * application.
     *
     * @access public
     *
     * @param string $interface  The interface to check for.
     *
     * @return mixed  The application implementing $interface if we have it,
     *                false if the interface is not implemented.
     */
    function hasInterface($interface)
    {
        return !empty($this->_interfaces[$interface]) ?
            $this->_interfaces[$interface] :
            false;
    }

    /**
     * Determine if a method has been registered with the registry.
     *
     * @access public
     *
     * @param string  $method      The full name of the method to check for.
     * @param string  $app         (optional) Only check this application.
     *
     * @return mixed  The application implementing $method if we have it,
     *                false if the method doesn't exist.
     */
    function hasMethod($method, $app = null)
    {
        if (is_null($app)) {
            @list($interface, $call) = explode('/', $method);
            if (!empty($this->_interfaces[$method])) {
                $app = $this->_interfaces[$method];
            } elseif (!empty($this->_interfaces[$interface])) {
                $app = $this->_interfaces[$interface];
            } else {
                return false;
            }
        } else {
            $call = $method;
        }

        $this->_fillAPICache();

        return !empty($this->_apiCache[$app][$call]) ? $app : false;
    }

    /**
     * Return the hook corresponding to the default package that
     * provides the functionality requested by the $method
     * parameter. $method is a string consisting of
     * "packagetype/methodname".
     *
     * @access public
     *
     * @param string $method        The method to call.
     * @param optional array $args  Arguments to the method.
     *
     * @return  TODO
     *          Returns PEAR_Error on error.
     */
    function call($method, $args = array())
    {
        @list($interface, $call) = explode('/', $method);

        if (!empty($this->_interfaces[$method])) {
            $app = $this->_interfaces[$method];
        } elseif (!empty($this->_interfaces[$interface])) {
            $app = $this->_interfaces[$interface];
        } else {
            return PEAR::raiseError('The method "' . $method . '" is not defined in the Horde Registry.');
        }

        return $this->callByPackage($app, $call, $args);
    }

    /**
     * Output the hook corresponding to the specific package named.
     *
     * @access public
     *
     * @param string $app           The application being called.
     * @param string $call          The method to call.
     * @param optional array $args  Arguments to the method.
     *
     * @return  TODO
     *          Returns PEAR_Error on error.
     */
    function callByPackage($app, $call, $args = array())
    {
        /* Note: calling hasMethod() makes sure that we've cached
         * $app's services and included the API file, so we don't try
         * to do it again explicitly in this method. */
        if (!$this->hasMethod($call, $app)) {
            return PEAR::raiseError(sprintf('The method "%s" is not defined in the API for %s.', $call, $app));
        }

        /* Make sure that the function actually exists. */
        $function = '_' . $app . '_' . $call;
        if (!function_exists($function)) {
            return PEAR::raiseError('The function implementing ' . $call . ' (' . $function . ') is not defined in ' . $app . '\'s API.');
        }

        $checkPerms = isset($this->_apiCache[$app][$call]['checkperms']) ?
                      $this->_apiCache[$app][$call]['checkperms'] : true;

        /* Switch application contexts now, if necessary, before
         * including any files which might do it for us. Return an
         * error immediately if pushApp() fails. */
        $pushed = $this->pushApp($app, $checkPerms);
        if (is_a($pushed, 'PEAR_Error')) {
            return $pushed;
        }

        $res = call_user_func_array($function, $args);

        /* If we changed application context in the course of this
         * call, undo that change now. */
        if ($pushed === true) {
            $this->popApp();
        }

        return $res;
    }

    /**
     * Return the hook corresponding to the default package that
     * provides the functionality requested by the $method
     * parameter. $method is a string consisting of
     * "packagetype/methodname".
     *
     * @access public
     *
     * @param string $method         The method to link to.
     * @param optional array $args   Arguments to the method.
     * @param optional mixed $extra  Extra, non-standard arguments to the
     *                               method.
     *
     * @return  TODO
     *          Returns PEAR_Error on error.
     */
    function link($method, $args = array(), $extra = '')
    {
        @list($interface, $call) = explode('/', $method);

        if (!empty($this->_interfaces[$method])) {
            $app = $this->_interfaces[$method];
        } elseif (!empty($this->_interfaces[$interface])) {
            $app = $this->_interfaces[$interface];
        } else {
            return PEAR::raiseError('The method "' . $method . '" is not defined in the Horde Registry.');
        }

        return $this->linkByPackage($app, $call, $args, $extra);
    }

    /**
     * Output the hook corresponding to the specific package named.
     *
     * @access public
     *
     * @param string $app            The application being called.
     * @param string $call           The method to link to.
     * @param optional array $args   Arguments to the method.
     * @param optional mixed $extra  Extra, non-standard arguments to the
     *                               method.
     *
     * @return  TODO
     *          Returns PEAR_Error on error.
     */
    function linkByPackage($app, $call, $args = array(), $extra = '')
    {
        /* Note: calling hasMethod makes sure that we've cached $app's
         * services and included the API file, so we don't try to do
         * it it again explicitly in this method. */
        if (!$this->hasMethod($call, $app)) {
            return PEAR::raiseError('The method "' . $call . '" is not defined in ' . $app . '\'s API.');
        }

        /* Make sure the link is defined. */
        if (empty($this->_apiCache[$app][$call]['link'])) {
            return PEAR::raiseError('The link ' . $call . ' is not defined in ' . $app . '\'s API.');
        }

        /* Initial link value. */
        $link = $this->_apiCache[$app][$call]['link'];

        /* Fill in html-encoded arguments. */
        foreach ($args as $key => $val) {
            $link = str_replace('%' . $key . '%', htmlentities($val), $link);
        }
        if (isset($this->applications[$app]['webroot'])) {
            $link = str_replace('%application%', $this->get('webroot', $app), $link);
        }

        /* Replace htmlencoded arguments that haven't been specified with
           an empty string (this is where the default would be substituted
           in a stricter registry implementation). */
        $link = preg_replace('|%.+%|U', '', $link);

        /* Fill in urlencoded arguments. */
        foreach ($args as $key => $val) {
            $link = str_replace('|' . String::lower($key) . '|', urlencode($val), $link);
        }

        /* Append any extra, non-standard arguments. */
        if (is_array($extra)) {
            $extra_args = '';
            foreach ($extra as $key => $val) {
                $extra_args .- '&' . urlencode($key) . '=' . urlencode($val);
            }
        } else {
            $extra_args = $extra;
        }
        $link = str_replace('|extra|', $extra_args, $link);

        /* Replace html-encoded arguments that haven't been specified with
           an empty string (this is where the default would be substituted
           in a stricter registry implementation). */
        $link = preg_replace('|\|.+\||U', '', $link);

        return $link;
    }

    /**
     * Replace any %application% strings with the filesystem path to
     * the application.
     *
     * @access public
     *
     * @param string $path          The application string.
     * @param optional string $app  The application being called.
     *
     * @return  TODO
     *          Returns PEAR_Error on error.
     */
    function applicationFilePath($path, $app = null)
    {
        if (is_null($app)) {
            $app = $this->_currentApp;
        }

        if (!isset($this->applications[$app])) {
            return PEAR::raiseError(sprintf(_("'%s' is not configured in the Horde Registry."), $app));
        }

        return str_replace('%application%', $this->applications[$app]['fileroot'], $path);
    }

    /**
     * Replace any %application% strings with the web path to the
     * application.
     *
     * @access public
     *
     * @param string $path          The application string.
     * @param optional string $app  The application being called.
     *
     * @return  TODO
     *          Returns PEAR_Error on error.
     */
    function applicationWebPath($path, $app = null)
    {
        if (!isset($app)) {
            $app = $this->_currentApp;
        }

        return str_replace('%application%', $this->applications[$app]['webroot'], $path);
    }

    /**
     * Set the current application, adding it to the top of the Horde
     * application stack. If this is the first application to be
     * pushed, retrieve session information as well.
     *
     * pushApp() also reads the application's configuration file and
     * sets up its global $conf hash.
     *
     * @access public
     *
     * @param string  $app         The name of the application to push.
     * @param boolean $checkPerms  (optional) Make sure that the current user
     *                             has permissions to the application being
     *                             loaded. Defaults to true. Should ONLY
     *                             be disabled by system scripts (cron jobs,
     *                             etc.) and scripts that handle login.
     *
     * @return boolean  Whether or not the _appStack was modified.
     *                  Return PEAR_Error on error.
     */
    function pushApp($app, $checkPerms = true)
    {
        if ($app == $this->_currentApp) {
            return false;
        }

        /* Bail out if application is not present or inactive. */
        if (!isset($this->applications[$app]) ||
            $this->applications[$app]['status'] == 'inactive' ||
            ($this->applications[$app]['status'] == 'admin' && !Auth::isAdmin())) {
            Horde::fatal($app . ' is not activated', __FILE__, __LINE__);
        }

        /* If permissions checking is requested, return an error if
         * the current user does not have read perms to the
         * application being loaded. We allow access:
         *
         *  - To all admins.
         *  - To all authenticated users if no permission is set on $app.
         *  - To anyone who is allowed by an explicit ACL on $app. */
        if ($checkPerms && !$this->hasPermission($app)) {
            Horde::logMessage(sprintf('User %s does not have READ permission for %s', Auth::getAuth(), $app), __FILE__, __LINE__, PEAR_LOG_DEBUG);
            return PEAR::raiseError(sprintf(_("User %s is not authorised for %s."), Auth::getAuth(), $this->applications[$app]['name']), 'permission_denied');
        }

        /* Import this application's configuration values. */
        $success = $this->importConfig($app);
        if (is_a($success, 'PEAR_Error')) {
            return $success;
        }

        /* Load preferences after the configuration has been loaded to
         * make sure the prefs file has all the information it needs. */
        $this->loadPrefs($app);

        /* Reset the language in case there is a different one
         * selected in the preferences. */
        $language = '';
        if (isset($this->_prefsCache[$app]) &&
            isset($this->_prefsCache[$app]->_prefs['language'])) {
            $language = $this->_prefsCache[$app]->getValue('language');
        }
        NLS::setLang($language);
        NLS::setTextdomain($app, $this->applications[$app]['fileroot'] . '/locale', NLS::getCharset());
        String::setDefaultCharset(NLS::getCharset());

        /* Once we know everything succeeded and is in a consistent
         * state again, push the new application onto the stack. */
        array_push($this->_appStack, $app);
        $this->_currentApp = $app;

        return true;
    }

    /**
     * Remove the current app from the application stack, setting the
     * current app to whichever app was current before this one took
     * over.
     *
     * @access public
     *
     * @return string  The name of the application that was popped.
     */
    function popApp()
    {
        /* Pop the current application off of the stack. */
        $previous = array_pop($this->_appStack);

        /* Import the new active application's configuration values
         * and set the gettext domain and the preferred language. */
        $this->_currentApp = count($this->_appStack) ? end($this->_appStack) : null;
        if ($this->_currentApp) {
            $this->importConfig($this->_currentApp);
            $this->loadPrefs($this->_currentApp);
            #$language = $GLOBALS['prefs']->getValue('language');
            #if (isset($language)) {
            #    NLS::setLang($language);
            #}
            NLS::setTextdomain($this->_currentApp, $this->applications[$this->_currentApp]['fileroot'] . '/locale', NLS::getCharset());
            String::setDefaultCharset(NLS::getCharset());
        }

        return $previous;
    }

    /**
     * Return the current application - the app at the top of the
     * application stack.
     *
     * @access public
     *
     * @return string  The current application.
     */
    function getApp()
    {
        return $this->_currentApp;
    }

    /**
     * Check permissions on an application.
     *
     * @access public
     *
     * @return boolean  Whether or not access is allowed.
     */
    function hasPermission($app, $permission = PERMS_READ)
    {
    	return true;
        #return Auth::isAdmin() || ($GLOBALS['perms']->exists($app) ?
        #                           $GLOBALS['perms']->hasPermission($app, Auth::getAuth(), $permission) :
        #                           (bool)Auth::getAuth());
    }

    /**
     * Reads the configuration values for the given application and
     * imports them into the global $conf variable.
     *
     * @access public
     *
     * @param string $app  The name of the application.
     *
     * @return boolean  True on success, PEAR_Error on error.
     */
    function importConfig($app)
    {
        /* Don't make config files global $registry themselves. */
        global $registry;

        /* Cache config values so that we don't re-read files on every
         * popApp() call. */
        if (!isset($this->_confCache[$app])) {
            if (!isset($this->_confCache['horde'])) {
                $conf = array();
                ob_start();
                $success = include HORDE_BASE . '/config/conf.php';
                $errors = ob_get_contents();
                ob_end_clean();
                if (!empty($errors)) {
                    return PEAR::raiseError(sprintf('Failed to import Horde configuration: %s', strip_tags($errors)));
                }
                if (!$success) {
                    return PEAR::raiseError('Failed to import Horde configuration.');
                }

                /* Initial Horde-wide settings. */

                /* Set the error reporting level in accordance with
                 * the config settings. */
                error_reporting($conf['debug_level']);

                /* Set the maximum execution time in accordance with
                 * the config settings. */
                @set_time_limit($conf['max_exec_time']);

                /* Set the umask according to config settings. */
                if (isset($conf['umask'])) {
                    umask($conf['umask']);
                }
            } else {
                $conf = $this->_confCache['horde'];
            }

            if ($app !== 'horde') {
                $success = @include $this->applications[$app]['fileroot'] . '/config/conf.php';
                if (!$success) {
                    return PEAR::raiseError('Failed to import application configuration for ' . $app);
                }
            }

            $this->_confCache[$app] = &$conf;
        }

        $GLOBALS['conf'] = &$this->_confCache[$app];
        return true;
    }

    /**
     * Loads the preferences for the current user for the current
     * application and imports them into the global $prefs variable.
     *
     * @access public
     *
     * @param string $app  The name of the application.
     */
    function loadPrefs($app = null)
    {
    	return array();
    	
        static $prefs_default = false;

        require_once 'Horde/Prefs.php';

        if ($app === null) {
            $app = $this->_currentApp;
        }

        /* If there is no logged in user, return an empty Prefs::
         * object with just default preferences. */
#        if (!Auth::getAuth()) {
#            $prefs = &Prefs::factory('none', $app, '', '', null, false);
#            $prefs->retrieve();
#            $this->_prefsCache[$app] = &$prefs;
#            $GLOBALS['prefs'] = &$this->_prefsCache[$app];
#            $prefs_default = true;
#            return;
#        }

        /* Cache prefs objects so that we don't re-load them on every
         * popApp() call. */
#        if (!isset($this->_prefsCache[$app]) ||
#            !empty($prefs_default)) {
#            $prefs = &Prefs::factory($GLOBALS['conf']['prefs']['driver'], $app,
#                                     Auth::getAuth(), Auth::getCredential('password'));
#            $prefs->retrieve();
#            $this->_prefsCache[$app] = &$prefs;
#       }

        $GLOBALS['prefs'] = &$this->_prefsCache[$app];
    }

    /**
     * Unload preferences from an application or (if no application is
     * specified) from ALL applications. Useful when a user has logged
     * out but you need to continue on the same page, etc.
     *
     * After unloading, if there is an application on the app stack to
     * load preferences from, then we reload a fresh set.
     *
     * @access public
     *
     * @param string $app  (optional) The application to unload prefrences for.
     *                     If null, ALL preferences are reset.
     */
    function unloadPrefs($app = null)
    {
        if ($app === null) {
            $this->_prefsCache = array();
        } elseif (isset($this->_prefsCache[$app])) {
            unset($this->_prefsCache[$app]);
        } else {
            return;
        }

        if ($this->_currentApp) {
            $this->loadPrefs();
        }
    }

    /**
     * Return the requested configuration parameter for the specified
     * application. If no application is specified, the value of
     * $this->_currentApp (the current application) is used. However,
     * if the parameter is not present for that application, the
     * Horde-wide value is used instead. If that is not present, we
     * return null.
     *
     * @access public
     *
     * @param string $parameter     The configuration value to retrieve.
     * @param optional string $app  The application to get the value for.
     *
     * @return string  The requested parameter, or null if it is not set.
     */
    function get($parameter, $app = null)
    {
        if (is_null($app)) {
            $app = $this->_currentApp;
        }

        if (isset($this->applications[$app][$parameter])) {
            $pval = $this->applications[$app][$parameter];
        } else {
            if ($parameter == 'icon') {
                $pval = $this->_getIcon($app);
            } else {
                $pval = isset($this->applications['horde'][$parameter]) ? $this->applications['horde'][$parameter] : null;
            }
        }

        if ($parameter == 'name') {
            return _($pval);
        } else {
            return $pval;
        }
    }

    /**
     * Function to work out an application's graphics URI, taking into
     * account any themes directories that may be set up.
     *
     * @access public
     *
     * @param optional string $app  The application for which to get the
     *                              image directory. If blank will default
     *                              to current application.
     *
     * @return string  The image directory uri path.
     */
    function getImageDir($app = null)
    {
        if (empty($app)) {
            $app = $this->_currentApp;
        }

        static $img_dir = array();
        if (isset($img_dir[$app])) {
            return $img_dir[$app];
        }

        /* This is the default location for the graphics. */
        $img_dir[$app] = $this->get('themesuri', $app) . '/graphics';

        /* Figure out if this is going to be overridden by any theme
         * settings. */
        if (isset($GLOBALS['prefs']) && ($theme = $GLOBALS['prefs']->getValue('theme')) &&
            (@include $this->get('themesfs', 'horde') . '/' . $theme . '/info.php') &&
            isset($theme_icons) &&
            in_array($app, $theme_icons)) {
            $img_dir[$app] = $this->get('themesuri', $app) . '/' . $theme . '/graphics';
        }

        return $img_dir[$app];
    }

    /**
     * Returns the path to an application's icon, respecting whether the
     * current theme has its own icons.
     *
     * @access private
     *
     * @param string $app  The application for which to get the icon.
     */
    function _getIcon($app)
    {
        return $this->getImageDir($app) . '/' . $app . '.png';
    }

    /**
     * Query the initial page for an application - the webroot, if
     * there is no initial_page set, and the initial_page, if it is
     * set.
     *
     * @access public
     *
     * @param optional string $app  The name of the application.
     *
     * @return string  URL pointing to the inital page of the application.
     *                 Returns PEAR_Error on error.
     */
    function getInitialPage($app = null)
    {
        if (is_null($app)) {
            $app = $this->_currentApp;
        }

        if (!isset($this->applications[$app])) {
            return PEAR::raiseError(sprintf(_("'%s' is not configured in the Horde Registry."), $app));
        }
        return $this->applications[$app]['webroot'] . '/' . (isset($this->applications[$app]['initial_page']) ? $this->applications[$app]['initial_page'] : '');
    }

    /**
     * Fills the registry's API cache with the available services.
     *
     * @access private
     */
    function _fillAPICache()
    {
        if (!empty($this->_apiCache)) {
            return;
        }

        $status = array('active', 'notoolbar', 'hidden');
#        if (Auth::isAdmin()) {
#            $status[] = 'admin';
#        }
        $apps = $this->listApps($status);
        foreach ($apps as $app) {
            $_services = $_types = null;
            $api = $this->get('fileroot', $app) . '/lib/api.php';
            if (is_readable($api)) {
                include_once $api;
            }
            if (!isset($_services)) {
                $this->_apiCache[$app] = array();
            } else {
                $this->_apiCache[$app] = $_services;
            }
            if (isset($_types)) {
                foreach ($_types as $type => $params) {
                    /* Prefix non-Horde types with the application
                     * name. */
                    $prefix = $app == 'horde' ? '' : "${app}_";
                    $this->_typeCache[$prefix . $type] = $params;
                }
            }
        }
    }

}
