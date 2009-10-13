<?php
/**
 * eGroupWare API - Browser detect functions
 *
 * This file written by Miles Lott <milosch@groupwhere.org>
 * Majority of code borrowed from Sourceforge 2.5
 * Copyright 1999-2000 (c) The SourceForge Crew - http://sourceforge.net
 *
 * @link http://www.egroupware.org
 * @author Miles Lott <milosch@groupwhere.org>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage html
 * @version $Id$
 */

/**
 * Dusty old browser detection functions (all static now)
 *
 * @deprecated use html::content_header(), html::$user_agent and html::$ua_version
 */
class browser
{
	public static $agent;
	public static $version;
	public static $platform;
	public static $br;
	public static $p;

	/**
	 * Init our static properties
	 */
	public static function init_static()
	{
		$HTTP_USER_AGENT = $_SERVER['HTTP_USER_AGENT'];
		/*
			Determine browser and version
		*/
		if(preg_match('/MSIE ([0-9].[0-9]{1,2})/',$HTTP_USER_AGENT,$log_version))
		{
			self::$version = $log_version[1];
			self::$agent = 'IE';
		}
		elseif(preg_match('/Opera ([0-9].[0-9]{1,2})/',$HTTP_USER_AGENT,$log_version) ||
			preg_match('/Opera\\/([0-9].[0-9]{1,2})/',$HTTP_USER_AGENT,$log_version))
		{
			self::$version   = $log_version[1];
			self::$agent = 'OPERA';
		}
		elseif(preg_match('/iCab ([0-9].[0-9a-zA-Z]{1,4})/i',$HTTP_USER_AGENT,$log_version) ||
			preg_match('/iCab\\/([0-9].[0-9a-zA-Z]{1,4})/i',$HTTP_USER_AGENT,$log_version))
		{
			self::$version   = $log_version[1];
			self::$agent = 'iCab';
		}
		elseif(strpos($HTTP_USER_AGENT,'Gecko') !== false)
		{
			self::$version   = $log_version[1];
			self::$agent = 'MOZILLA';
		}
		elseif(preg_match('/Konqueror\\/([0-9].[0-9].[0-9]{1,2})/',$HTTP_USER_AGENT,$log_version) ||
			preg_match('/Konqueror\\/([0-9].[0-9]{1,2})/',$HTTP_USER_AGENT,$log_version))
		{
			self::$version=$log_version[1];
			self::$agent='Konqueror';
		}
		else
		{
			self::$version=0;
			self::$agent='OTHER';
		}

		/*
			Determine platform
		*/
		if(strpos($HTTP_USER_AGENT,'Win') !== false)
		{
			self::$platform='Win';
		}
		elseif(strpos($HTTP_USER_AGENT,'Mac') !== false)
		{
			self::$platform='Mac';
		}
		elseif(strpos($HTTP_USER_AGENT,'Linux') !== false)
		{
			self::$platform='Linux';
		}
		elseif(strpos($HTTP_USER_AGENT,'Unix') !== false)
		{
			self::$platform='Unix';
		}
		elseif(strpos($HTTP_USER_AGENT,'Beos') !== false)
		{
			self::$platform='Beos';
		}
		else
		{
			self::$platform='Other';
		}

		/*
		echo "\n\nAgent: $HTTP_USER_AGENT";
		echo "\nIE: ".browser_is_ie();
		echo "\nMac: ".browser_is_mac();
		echo "\nWindows: ".browser_is_windows();
		echo "\nPlatform: ".browser_get_platform();
		echo "\nVersion: ".browser_get_version();
		echo "\nAgent: ".browser_get_agent();
		*/

		// The br and p functions are supposed to return the correct
		// value for tags that do not need to be closed.  This is
		// per the xhmtl spec, so we need to fix this to include
		// all compliant browsers we know of.
		if(self::$agent == 'IE')
		{
			self::$br = '<br/>';
			self::$p = '<p/>';
		}
		else
		{
			self::$br = '<br>';
			self::$p = '<p>';
		}
		//error_log(__METHOD__."() $_SERVER[HTTP_USER_AGENT] --> agent=".self::$agent.', version='.self::$version.', platform='.self::$platform);
	}

	public static function return_array()
	{
		return array(
			'agent'    => self::$agent,
			'version'  => self::$version,
			'platform' => self::$platform,
		);
	}

	public static function get_agent()
	{
		return self::$agent;
	}

	public static function get_version()
	{
		return self::$version;
	}

	public static function get_platform()
	{
		return self::$platform;
	}

	public static function is_linux()
	{
		return $platform == 'Linux';
	}

	public static function is_unix()
	{
		return $platform() == 'Unix';
	}

	public static function is_beos()
	{
		return $platform == 'Beos';
	}

	public static function is_mac()
	{
		return $platform == 'Mac';
	}

	public static function is_windows()
	{
		return $platform == 'Win';
	}

	public static function is_ie()
	{
		return $agent == 'IE';
	}

	public static function is_netscape()
	{
		return $agent == 'MOZILLA';
	}

	public static function is_opera()
	{
		return $agent == 'OPERA';
	}

	/**
	 * Output content headers for file downloads
	 *
	 * @param string $fn filename
	 * @param string $mime='' mimetype or '' (default) to detect it from filename, using mime_magic::filename2mime()
	 * @param int $length=0 content length, default 0 = skip that header
	 * @param boolean $nocache=true send headers to disallow browser/proxies to cache the download
	 * @deprecated use html::content_header() direct
	 */
	public static function content_header($fn,$mime='',$length=0,$nocache=True)
	{
		html::content_header($fn,$mime,$length,$nocache);
	}
}
browser::init_static();