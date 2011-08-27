<?php
/**
 * EGroupware: Class which manages including js files and modules
 * (lateron this might be extended to css)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Andreas Stöckel
 * @copyright (c) 2011 Stylite
 * @version $Id$
 */

/**
 * Syntax for including JS files form others
 * -----------------------------------------
 *
 * Write a comment starting with "/*egw:uses". A linebreak has to follow.
 * Then write all files which have to be included seperated by ";". A JS file
 * include may have the following syntax:
 *
 * 1) File in the same directory as the current file. Simply write the filename
 *    without ".js". Example:
 *    	egw_action;
 * 2) Files in a certain application and package. The syntax is
 *    	[$app.]$package.$file
 *    The first "$app" part is optional. It defaults to phpgwapi. Examples:
 *    	jquery.jquery-ui; // Loads /phpgwapi/js/jquery/jquery-ui.js
 *    	stylite.filemanager.filemanager; // Loads /stylite/filemanager/filemanager.js
 * 3) Absolute file paths starting with "/". Example:
 *    	/phpgwapi/js/jquery/jquery-ui.js;
*
 * Comments can be started with "//".
 *
 * Complete example of such an uses-clause:
 * 	/*egw:uses
 * 		egw_action_common;
 * 		egw_action;
 * 		jquery.jquery; // Includes jquery.js from package jquery in phpgwapi
 * 		/phpgwapi/js/jquery/jquery-ui.js; // Includes jquery-ui.js
 */

class egw_include_mgr
{

	static private $DEBUG_MODE = true;

	/**
	 * The parsed_files array holds all files which have already been processed
	 * by this class.
	 */
	private $parsed_files = array();

	/**
	 * The included files array holds all files which will really be included
	 * as a result of the current request.
	 */
	private $included_files = array();

	/**
	 * Set to the file which is currently processed, in order to get usable
	 * debug messages.
	 */
	private $debug_processing_file = false;

	/**
	 * Parses the js file for includes and returns all required files
	 */
	private function parse_file($file)
	{
		// Mark the file as parsed
		$this->parsed_files[$file] = true;

		// Try to open the given file
		$f = fopen(EGW_SERVER_ROOT.$file, "r");
		if ($f !== false)
		{

			// Only read a maximum of 32 lines until the comment occurs.
			$cnt = 0;
			$in_uses = false;
			$uses = "";

			// Read a line
			$line = fgets($f);
			while ($cnt < 32 && $line !== false)
			{
				// Remove everything behind "//"
				$pos = strpos($line, "//");
				if ($pos !== false)
				{
					$line = substr($line, 0, $pos);
				}

				if (!$in_uses)
				{
					$cnt++;
					$in_uses = strpos($line, "/*egw:uses") !== false;
				}
				else
				{
					// Check whether we are at the end of the comment
					$pos = strpos($line, "*/");

					if ($pos === false)
					{
						$uses .= $line;
					}
					else
					{
						$uses .= substr($line, 0, $pos);
						break;
					}
				}

				$line = fgets($f);
			}

			// Close the file again
			fclose($f);

			// Split the "require" string at ";"
			$modules = explode(";", $uses);
			$modules2 = array();

			// Split all modules at "." and remove entries with empty parts
			foreach ($modules as $mod)
			{
				// Remove trailing space characters
				$mod = trim($mod);

				if ($mod)
				{
					// Split the given module string at the dot, if this isn't
					// an absolute path (initialized by "/").
					if ($mod[0] != '/')
					{
						$mod = explode(".", $mod);
					}
					$empty = false;

					// Remove all space characters
					foreach ($mod as &$entry)
					{
						$entry = trim($entry);
						$empty = $empty || !$entry;
					}

					if (!$empty)
					{
						$modules2[] = $mod;
					}
				}
			}

			return $modules2;
		}

		return false;
	}

	private function file_processed($file)
	{
		return (array_key_exists($file, $this->included_files) ||
			    array_key_exists($file, $this->parsed_files));
	}

	/**
	 * Parses the given files and adds all dependencies to the passed modules array
	 */
	private function parse_deps($path, array &$module)
	{
		$this->debug_processing_file = $path;

		// Parse the given file for dependencies
		$uses = $this->parse_file($path);

		foreach ($uses as $entry)
		{
			$uses_path = false;

			// Check whether just a filename was given - if yes, check whether
			// a file with the given name exists inside the directory of the
			// base file
			if (count($entry) == 1)
			{
				// Assemble a filename
				$filename = dirname($path).'/'.$entry[0].'.js';

				if (is_readable(EGW_SERVER_ROOT.($filename)))
				{
					if (!$this->file_processed($filename))
					{
						$uses_path = $filename;
					}
				}
				else
				{
					$uses_path = $this->translate_params($entry[0], null, 'phpgwapi');
				}
			}
			else if (count($entry) == 2)
			{
				$uses_path = $this->translate_params($entry[0], $entry[1], 'phpgwapi');
			}
			else if (count($entry) == 3)
			{
				$uses_path = $this->translate_params($entry[1], $entry[2], $entry[0]);
			}
			else
			{
				error_log(__METHOD__." invalid egw:require in js_file -> ".print_r($entry, true));
			}

			if ($uses_path)
			{
				$this->parse_deps($uses_path, $module);
			}
		}

		// Add the file to the top of the list
		array_push($module, $path);

		$this->debug_processing_file = false;
	}

	/**
	 * Includes the given module files - this function will have the task to
	 * cache/shrink/concatenate the files in the future.
	 */
	private function include_module(array $module)
	{
		if (self::$DEBUG_MODE)
		{
			foreach ($module as $path)
			{
				$this->included_files[$path] = true;
			}
		}
		else
		{
			// TODO
		}
	}

	/**
	 * Translates the given parameters into a path and checks it for validity.
	 *
	 * Example call syntax:
	 * a) egw_framework::validate_file('jscalendar','calendar')
	 *    --> /phpgwapi/js/jscalendar/calendar.js
	 * b) egw_framework::validate_file('/phpgwapi/inc/calendar-setup.js',array('lang'=>'de'))
	 *    --> /phpgwapi/inc/calendar-setup.js?lang=de
	 *
	 * @param string $package package or complete path (relative to EGW_SERVER_ROOT) to be included
	 * @param string|array $file=null file to be included - no ".js" on the end or array with get params
	 * @param string $app='phpgwapi' application directory to search - default = phpgwapi
	 * @param boolean $append=true should the file be added
	 *
	 * @returns the correct path on the server if the file is found or false, if the
	 *  file is not found or no further processing is needed.
	 */
	private function translate_params($package, $file, $app)
	{
		if ($package[0] == '/' && is_readable(EGW_SERVER_ROOT.(parse_url($path = $package, PHP_URL_PATH))) ||
			is_readable(EGW_SERVER_ROOT.($path="/$app/js/$package/$file.js")) ||
			$app != 'phpgwapi' && is_readable(EGW_SERVER_ROOT.($path="/phpgwapi/js/$package/$file.js")))
		{

			// Handle the special case, that the file is an url - in this case
			// we will do no further processing but just include the file
			// XXX: Is this case still used? If yes, it will not work with
			// 	adding the ctime to all js files...
			if (is_array($file))
			{
				foreach($file as $name => $val)
				{
					$args .= (empty($args) ? '?' : '&').$name.'='.urlencode($val);
				}
				$path .= $args;

				$this->included_files[$path] = true;
				return false;
			}

			// Return false if the file is already included or parsed
			if ($this->file_processed($path))
			{
				return false;
			}
			else
			{
				return $path;
			}
		}

		if (self::$DEBUG_MODE) // DEBUG_MODE is currently ALWAYS true. Comment this code out if you don't want error messages.
		{
			error_log(__METHOD__."($package,$file,$app) $path NOT found".($this->debug_processing_file ? " while processing file '{$this->debug_processing_file}'." : "!").' '.function_backtrace());
		}

		return false;
	}

	public function include_js_file($package, $file = null, $app = 'phpgwapi')
	{
		// Translate the given parameters into a valid path - false is returned
		// if the file is not found or the file is already included/has already
		// been parsed by this class.
		$path = $this->translate_params($package, $file, $app);

		if ($path !== false)
		{
			// TODO: Check whether the given path is cached, if yes, include the
			// cached module file and add all files this module contains
			// to the "parsed_files" array

			// Collect the possible list of dependant modules of this file in
			// the "module" array.
			$module = array();
			$this->parse_deps($path, $module);

			$this->include_module($module);
		}
	}

	public function include_files(array $files)
	{
		foreach ($files as $file)
		{
			$this->include_js_file($file);
		}
	}

	public function get_included_files()
	{
		return array_keys($this->included_files);
	}

	public function __construct($files = null)
	{
		if (isset($files) && is_array($files))
		{
			$this->include_files($files);
		}
	}

}

/*
// Small test for this class

define("EGW_SERVER_ROOT", "/home/andreas/www/egroupware/trunk/egroupware");

$mgr = new egw_include_mgr();
$mgr->include_js_file('filemanager', 'filemanager', 'stylite');
$files = $mgr->get_included_files();

foreach ($files as $file)
{
	echo($file."<br>\n");
}
*/
