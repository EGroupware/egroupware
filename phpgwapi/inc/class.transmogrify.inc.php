<?php
/**
 * Base clase for changing or copying one record type (eg Infolog) into another (eg Calendar)
 * 
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package phpgwapi
 * @copyright (c) 2011 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Transmogrify: to completely alter the form of
 * For changing an entry from one application (in array form) into another.  
 *
 * Uses hooks so other apps can specify how the conversion process is to happen from other applications,
 * so this class is mostly support functions.  In the future, it may support customization of the 
 * field mapping between applications but right now mappings between different fields are provided by the destination
 * application.
 *
 * Hook parameters:
 *	'location'	=> 'convert', // Required by hook system
 *	'from'		=> Original application.  If omitted, hook function should return a list of supported applications.
 *	'data'		=> Data from original application.  If omitted, return false.
 * The hook should return the ID for the newly created entry.
 *
 * For ease of use & reduction of duplication, hook functions can call transmogrify::check() as a 'parent' function.
 */
class transmogrify
{

	/**
	 * Checks to see if the conversion can happen
	 *
	 * @param from String appname
	 * @param to String appname
	 *
	 * @return boolean
	 */
	public static function isSupported($from, $to)
	{
		$args = array(
			'location'	=> 'convert'
		);
		return $GLOBALS['egw']->hooks->hook_exists('convert', $to) > 0 && in_array($from, $GLOBALS['egw']->hooks->single('convert',$to));
	}

	/**
	 * Get a list of applications that the given application can convert to
	 *
	 * @param from String appname
	 *
	 * @return array of appnames
	 */
	public static function getList($from)
	{
		return $GLOBALS['egw']->hooks->single('convert', $from);
	}
	
	/**
	 * Convert data from the specified record into another, as best as possible.
	 *
	 * @param data Array to be changed
	 * @param to String appname
	 * @param from String appname  Defaults to current app.
	 * @param from_id Entry ID.  If provided, a link will be created between entries.
	 *
	 * @return Array of data as 'to' app structures it, ready to be saved
	 */
	public static function convert(Array $data, $to, $from = null, $from_id = null)
	{
		if($from == null) $from = $GLOBALS['egw_info']['flags']['currentapp'];

		if(!self::isSupported($from, $to))
		{
			throw new egw_exception_wrong_parameter("$to does not know how to convert from $from");
		}

		$result = array();

		// Check for hook 
		// TODO: Maybe add in preference for conversion
		if($GLOBALS['egw']->hooks->hook_exists('convert',$to))
		{
			$args = array(
				'location'	=> 'convert',
				'from'		=> $from,
				'to'		=> $to,
				'data'		=> $data
			);
			$id = $GLOBALS['egw']->hooks->single($args, $to);
		}
		else
		{
			// TODO: Automagic conversion based on data types or names
			throw new egw_exception_wrong_parameter("$to does not know how to convert from $from");
		}

		// Try to link
		if($id && $from_id)
		{
			egw_link::link($from, $from_id, $to, $id);
		}

		// Try to edit
		egw_framework::set_onload("if(egw.open) { egw.open('$id', '$to', 'edit');}");

		return $id;
	}

	/**
	 * 'Parent' function for hook functions
	 * Put here to reduce duplication
	 */
	public static function check($to, $from, Array &$data, Array &$mapping)
	{
		if(!$from) return; // Get list of supported apps
		if(!$data) return false;

		// If we have some common stuff, it can go here
	}
}
?>
