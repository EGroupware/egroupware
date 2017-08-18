<?php

/**
 * Run before PHPUnit starts - common stuff for _all_ tests, like getting
 * the autoloader.
 * This file is automatically run once before starting.
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// autoloader & check_load_extension
require_once realpath(__DIR__.'/../api/src/loader/common.php');

// backward compatibility with PHPunit 5.7
if (!class_exists('\PHPUnit\Framework\TestCase') && class_exists('\PHPUnit_Framework_TestCase'))
{
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
    class_alias('\PHPUnit_Framework_ExpectationFailedException', '\PHPUnit\Framework\ExpectationFailedException');
}

// Needed to let Cache work
$GLOBALS['egw_info']['server']['temp_dir'] = '/tmp';
$GLOBALS['egw_info']['server']['install_id'] = 'PHPUnit test';