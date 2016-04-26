<?php
/**
 * EGroupware API - Applications
 *
 * @link http://www.egroupware.org
 * This file was originaly written by Dan Kuykendall and Joseph Engo
 * Copyright (C) 2000, 2001 Dan Kuykendall
 * Parts Copyright (C) 2003 Free Software Foundation
 * @author	RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage egw
 * @version $Id$
 */

namespace EGroupware\Api\Egw;

use EGroupware\Api;

// explicitly list old, non-namespaced classes
use common;	// get_tpl_dir

/**
 * Egw\Base object used in setup, does not instanciate anything by default
 *
 * Extending Egw\Base which uses now a getter method to create the usual subobject on demand,
 * to allow a quicker header include on sites not using php4-restore.
 * This also makes a lot of application code, like the following, unnecessary:
 * if (!is_object($GLOBALS['egw']->ldap)
 * {
 * 		$GLOBALS['egw']->ldap = Api\Ldap::factory();
 * }
 * You can now simply use $GLOBALS['egw']->ldap, and the egw class instanciates it for you on demand.
 */
class Base
{
	/**
	 * Instance of the db-object
	 *
	 * @var Api\Db
	 */
	var $db;
	/**
	 * Current app at the instancation of the class
	 *
	 * @var string
	 */
	var $currentapp;
	/**
	 * Global ADOdb object, need to be defined here, to not call magic __get method
	 *
	 * @var ADOConnection
	 */
	var $ADOdb;

	/**
	 * Classes which get instanciated in a different name
	 *
	 * @var array
	 */
	static $sub_objects = array(
		'log' => 'errorlog',
		'link' => 'bolink',		// depricated use static egw_link methods
		'datetime' => 'egw_datetime',
		'template' => 'Template',
		'session' => 'egw_session',	// otherwise $GLOBALS['egw']->session->appsession() fails
		// classes moved to new api dir
		'framework' => true,	// special handling in __get()
		'ldap' => true,
		'auth' => 'EGroupware\\Api\\Auth',
	);

	/**
	 * Magic function to check if a sub-object is set
	 *
	 * @param string $name
	 * @return boolean
	 */
	function __isset($name)
	{
		//error_log(__METHOD__."($name)");
		return isset($this->$name);
	}

	/**
	 * Magic function to return a sub-object
	 *
	 * @param string $name
	 * @return mixed
	 */
	function __get($name)
	{
		//error_log(__METHOD__."($name)".function_backtrace());

		if ($name == 'js') $name = 'framework';	// javascript class is integrated now into framework

		if (isset($this->$name))
		{
			return $this->$name;
		}

		if (!isset(self::$sub_objects[$name]) && !class_exists($name))
		{
			if ($name != 'ADOdb') error_log(__METHOD__.": There's NO $name object! ".function_backtrace());
			return null;
		}
		switch($name)
		{
			case 'framework':
				return $this->framework = Api\Framework::factory();
			case 'template':	// need to be instancated for the current app
				if (!($tpl_dir = common::get_tpl_dir($this->currentapp)))
				{
					return null;
				}
				return $this->template = new Api\Framework\Template($tpl_dir);
			case 'ldap':
				return $this->ldap = Api\Ldap::factory(false);
			default:
				$class = isset(self::$sub_objects[$name]) ? self::$sub_objects[$name] : $name;
				break;
		}
		return $this->$name = new $class();
	}
}
