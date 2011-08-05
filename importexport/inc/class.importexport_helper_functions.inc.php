<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

/**
 * class importexport_helper_functions (only static methods)
 * use importexport_helper_functions::method
 */
class importexport_helper_functions {

	/**
	 * Plugins are scanned and cached for all instances using this source path for given time (in seconds)
	 */
	const CACHE_EXPIRATION = 3600;

	/**
	* Files known to cause problems, and will be skipped in a plugin scan
	* If you put appname => true, the whole app will be skipped.
	*/
	protected static $blacklist_files = array(
		'news_admin' => array(
			'class.news_admin_import.inc.php',
		),
	);

	/**
	* Class used to provide extra conversion functions
	*
	* Passed in as a param to conversion()
	*/
	protected static $cclass = null;

	/**
	 * nothing to construct here, only static functions!
	 */

	/**
	 * Converts a custom time string to to unix timestamp
	 * The format of the time string is given by the argument $_format
	 * which takes the same parameters as the php date() function.
	 *
	 * @abstract supportet formatstrings: d,m,y,Y,H,h,i,s,O,a,A
	 * If timestring is empty, php strtotime is used.
	 * @param string $_string time string to convert
	 * @param string $_format format of time string e.g.: d.m.Y H:i
	 * @param int $_is_dst is day light saving time? 0 = no, 1 = yes, -1 = system default
	 */
	public static function custom_strtotime( $_string, $_format='', $_is_dst = -1) {
		if ( empty( $_format ) ) return strtotime( $_string );
		$fparams = explode( ',', chunk_split( $_format, 1, ',' ) );
		$spos = 0;
		foreach ( $fparams as $fparam ) {

			switch ( $fparam ) {
				case 'd': (int)$day = substr( $_string, $spos, 2 ); $spos += 2; break;
				case 'm': (int)$mon = substr( $_string, $spos, 2 ); $spos += 2; break;
				case 'y': (int)$year = substr( $_string, $spos, 2 ); $spos += 2; break;
				case 'Y': (int)$year = substr( $_string, $spos, 4 ); $spos += 4; break;
				case 'H': (int)$hour = substr( $_string, $spos, 2 ); $spos += 2; break;
				case 'h': (int)$hour = substr( $_string, $spos, 2 ); $spos += 2; break;
				case 'i': (int)$min =  substr( $_string, $spos, 2 ); $spos += 2; break;
				case 's': (int)$sec =  substr( $_string, $spos, 2 ); $spos += 2; break;
				case 'O': (int)$offset = $year = substr( $_string, $spos, 5 ); $spos += 5; break;
				case 'a': (int)$hour = $fparam == 'am' ? $hour : $hour + 12; break;
				case 'A': (int)$hour = $fparam == 'AM' ? $hour : $hour + 12; break;
				default: $spos++; // seperator
			}
		}

		print_debug("hour:$hour; min:$min; sec:$sec; mon:$mon; day:$day; year:$year;\n");
		$timestamp = mktime($hour, $min, $sec, $mon, $day, $year, $_is_dst);

		// offset given?
		if ( isset( $offset ) && strlen( $offset == 5 ) ) {
			$operator = $offset{0};
			$ohour = 60 * 60 * (int)substr( $offset, 1, 2 );
			$omin = 60 * (int)substr( $offset, 3, 2 );
			if ( $operator == '+' ) $timestamp += $ohour + $omin;
			else $timestamp -= $ohour + $omin;
		}
		return $timestamp;
	}
	/**
	 * converts accound_lid to account_id
	 *
	 * @param mixed $_account_lid comma seperated list or array with lids
	 * @return mixed comma seperated list or array with ids
	 */
	public static function account_name2id( $_account_lids ) {
		$account_lids = is_array( $_account_lids ) ? $_account_lids : explode( ',', $_account_lids );
		$skip = false;
		foreach ( $account_lids as $key => $account_lid ) {
			if($skip) {
				$skip = false;
				continue;
			}
			$account_lid = trim($account_lid);

			// Handle any IDs that slip in
			if(is_numeric($account_lid) && $GLOBALS['egw']->accounts->id2name($account_lid)) {
				$account_ids[] = (int)$account_lid;
				continue;
			}
			// Handle users listed as Lastname, Firstname instead of login ID
			// Do this first, in case their first name matches a username
			if ( $account_lids[$key+1][0] == ' ' && $account_id = $GLOBALS['egw']->accounts->name2id( trim($account_lids[$key+1]).' ' .$account_lid, 'account_fullname')) {
				$account_ids[] = $account_id;
				$skip = true; // Skip the next one, it's the first name
				continue ;
			}
			if ( $account_id = $GLOBALS['egw']->accounts->name2id( $account_lid )) {
				$account_ids[] = $account_id;
				continue;
			}
			if ( $account_id = $GLOBALS['egw']->accounts->name2id( $account_lid, 'account_fullname' )) {
				$account_ids[] = $account_id;
				continue;
			}
			
			// Handle groups listed as Group, <name>
			if ( $account_lid[0] == ' ' && $account_id = $GLOBALS['egw']->accounts->name2id( trim($account_lid))) {
				$account_ids[] = $account_id;
			}
		}
		return is_array( $_account_lids ) ? $account_ids : implode( ',', (array)$account_ids );

	} // end of member function account_lid2id

	/**
	 * converts account_ids to account_lids
	 *
	 * @param mixed $_account_ids comma seperated list or array with ids
	 * @return mixed comma seperated list or array with lids
	 */
	public static function account_id2name( $_account_id ) {
		$account_ids = is_array( $_account_id ) ? $_account_id : explode( ',', $_account_id );
		foreach ( $account_ids as $account_id ) {
			if ( $account_lid = $GLOBALS['egw']->accounts->id2name( $account_id )) {
				$account_lids[] = $account_lid;
			}
		}
		return is_array( $_account_id ) ? $account_lids : implode( ',', (array)$account_lids );
	} // end of member function account_id2lid

	/**
	 * converts cat_id to a cat_name
	 *
	 * @param mixed _cat_ids comma seperated list or array
	 * @return mixed comma seperated list or array with cat_names
	 */
	public static function cat_id2name( $_cat_ids ) {
		$cat_ids = is_array( $_cat_ids ) ? $_cat_ids : explode( ',', $_cat_ids );
		foreach ( $cat_ids as $cat_id ) {
			$cat_names[] = categories::id2name( (int)$cat_id );
		}
		return is_array( $_cat_ids ) ? $cat_names : implode(',',(array)$cat_names);
	} // end of member function category_id2name

	/**
	 * converts cat_name to a cat_id.
	 * If a cat isn't found, it will be created.
	 *
	 * @param mixed $_cat_names comma seperated list or array.
	 * @param int $parent Optional parent ID to use for new categories
	 * @return mixed comma seperated list or array with cat_ids
	 */
	public static function cat_name2id( $_cat_names, $parent = 0 ) {
		$cats = new categories();	// uses current user and app (egw_info[flags][currentapp])

		$cat_names = is_array( $_cat_names ) ? $_cat_names : explode( ',', $_cat_names );
		foreach ( $cat_names as $cat_name ) {
			$cat_name = trim($cat_name);
			if ( $cat_name == '' ) continue;
			// Handle any IDs that slip in
			if ( is_numeric($cat_name) && categories::id2name((int)$cat_name)) {
				$cat_ids[] = (int)$cat_name;
				continue;
			}
			if ( ( $cat_id = $cats->name2id( $cat_name ) ) == 0 ) {
				$cat_id = $cats->add( array(
					'name' => $cat_name,
					'parent' => $parent,
					'access' => 'public',
					'descr' => $cat_name. ' ('. lang('Automatically created by importexport'). ')'
				));
			}
			$cat_ids[] = $cat_id;
		}
		return is_array( $_cat_names ) ? $cat_ids : implode( ',', (array)$cat_ids );

	} // end of member function category_name2id

	/**
	 * conversion
	 *
	 * Conversions enable you to change / adapt the content of each _record field for your needs.
	 * General syntax is: pattern1 |> replacement1 || ... || patternN |> replacementN
	 * If the pattern-part of a pair is ommited it will match everything ('^.*$'), which
	 * is only usefull for the last pair, as they are worked from left to right.
	 * Example: 1|>private||public
	 * This will translate a '1' in the _record field to 'privat' and everything else to 'public'.
	 *
	 * In addintion to the fields assign by the pattern of the reg.exp.
	 * you can use all other _record fields, with the syntax |[FIELDINDEX].
	 * Example:
	 * Your record is:
	 * 		array( 0 => Company, 1 => NFamily, 2 => NGiven
	 * Your conversion string for field 0 (Company):
	 * 		.+|>|[0]: |[1], |[2]|||[1], |[2]
	 * This constructs something like
	 * 		Company: FamilyName, GivenName or FamilyName, GivenName if 'Company' is empty.
	 *
	 * Moreover the following helper functions can be used:
	 * cat(Cat1,...,CatN) returns a (','-separated) list with the cat_id's. If a
	 * category isn't found, it will be automaticaly added.
	 *
	 * account(name) returns an account ID, if found in the system
	 * list(sep, data, index) lets you explode a field on sep, then select just one part (index)
	 *
	 * Patterns as well as the replacement can be regular expressions (the replacement is done
	 * via str_replace).
	 *
	 * @param array _record reference with record to do the conversion with
	 * @param array _conversion array with conversion description
	 * @param object &$cclass calling class to process the '@ evals'
	 * @return bool
	 */
	public static function conversion( &$_record,  $_conversion, &$_cclass = null ) {
		if (empty( $_conversion ) ) return $_record;

		self::$cclass =& $_cclass;

		$PSep = '||'; // Pattern-Separator, separats the pattern-replacement-pairs in conversion
		$ASep = '|>'; // Assignment-Separator, separats pattern and replacesment
		$CPre = '|['; $CPos = ']';  // |[_record-idx] is expanded to the corespondig value
		$TPre = '|T{'; $TPos = '}'; // |{_record-idx} is trimmed
		$CntlPre = '|TC{';		    // Filter all cntl-chars \x01-\x1f and trim
		$CntlnCLPre  = '|TCnCL{';   // Like |C{ but allowes CR and LF
		$INE = '|INE{';             // Only insert if stuff in ^^ is not empty

		foreach ( $_conversion as $idx => $conversion_string ) {
			if ( empty( $conversion_string ) ) continue;

			// fetch patterns ($rvalues)
			$pat_reps = explode( $PSep, stripslashes( $conversion_string ) );
			foreach( $pat_reps as $k => $pat_rep ) {
				list( $pattern, $replace ) = explode( $ASep, $pat_rep, 2 );
				if( $replace == '' ) {
					$replace = $pattern; $pattern = '^.*$';
				}
				$rvalues[$pattern] = $replace;	// replace two with only one, added by the form
			}

			// conversion list may be longer than $_record aka (no_csv)
			$val = array_key_exists( $idx, $_record ) ? $_record[$idx] : '';

			$c_functions = array('cat', 'account', 'strtotime', 'list');
			if($_cclass) {
				// Add in additional methods
				$reflection = new ReflectionClass(get_class($_cclass));
				$methods = $reflection->getMethods(ReflectionMethod::IS_STATIC);
				foreach($methods as $method) {
					$c_functions[] = $method->name;
				}
			}
			$c_functions = implode('|', $c_functions);
			foreach ( $rvalues as $pattern => $replace ) {
				if( preg_match('/'. (string)$pattern.'/', $val) ) {

					$val = preg_replace( '/'.(string)$pattern.'/', $replace, (string)$val );

					$reg = '/\|\[([a-zA-Z_0-9]+)\]/';
					while( preg_match( $reg, $val, $vars ) ) {
						// expand all _record fields
						$val = str_replace(
							$CPre . $vars[1] . $CPos,
							$_record[array_search($vars[1], array_keys($_record))],
							$val
						);
					}
					$val = preg_replace_callback( "/($c_functions)\(([^)]*)\)/i", array( self, 'c2_dispatcher') , $val );
					break;
				}
			}
			// clean each field
			$val = preg_replace_callback("/(\|T\{|\|TC\{|\|TCnCL\{|\|INE\{)(.*)\}/", array( self, 'strclean'), $val );

			$_record[$idx] = $val;
		}
		return $_record;
	} // end of member function conversion

	/**
	 * callback for preg_replace_callback from self::conversion.
	 * This function gets called when 2nd level conversions are made,
	 * like the cat() and account() statements in the conversions.
	 *
	 * @param array $_matches
	 */
	private static function c2_dispatcher( $_matches ) {
		$action = &$_matches[1]; // cat or account ...
		$data = &$_matches[2];   // datas for action

		switch ( $action ) {
			case 'strtotime' :
				list( $string, $format ) = explode( ',', $data );
				return self::custom_strtotime( trim( $string ), trim( $format ) );
			case 'list':
				list( $split, $data, $index) = explode(',',$data);
				$exploded = explode($split, $data);
				// 1 based indexing for user ease
				return $exploded[$index - 1];
			default :
				if(self::$cclass && method_exists(self::$cclass, $action)) {
					$class = get_class(self::$cclass);
					return call_user_func("$class::$action", $data);
				}
				$method = (string)$action. ( is_int( $data ) ? '_id2name' : '_name2id' );
				if(self::$cclass && method_exists(self::$cclass, $method)) {
					$class = get_class(self::$cclass);
					return call_user_func("$class::$action", $data);
				} else {
					return self::$method( $data );
				}
		}
	}

	private static function strclean( $_matches ) {
		switch( $_matches[1] ) {
			case '|T{' : return trim( $_matches[2] );
			case '|TC{' : return trim( preg_replace( '/[\x01-\x1F]+/', '', $_matches[2] ) );
			case '|TCnCL{' : return trim( preg_replace( '/[\x01-\x09\x11\x12\x14-\x1F]+/', '', $_matches[2] ) );
			case '|INE{' : return preg_match( '/\^.+\^/', $_matches[2] ) ? $_matches[2] : '';
			default:
				throw new Exception('Error in conversion string! "'. substr( $_matches[1], 0, -1 ). '" is not valid!');
		}
	}

	/**
	 * returns a list of importexport plugins
	 *
	 * @param string $_tpye {import | export | all}
	 * @param string $_appname {<appname> | all}
	 * @return array(<appname> => array( <type> => array(<plugin> => <title>)))
	 */
	public static function get_plugins( $_appname = 'all', $_type = 'all' ) {
		$plugins = egw_cache::getTree(
			__CLASS__,
			'plugins',
			array('importexport_helper_functions','_get_plugins'),
			array(array_keys($GLOBALS['egw_info']['apps']), array('import', 'export')),
			self::CACHE_EXPIRATION
		);
		$appnames = $_appname == 'all' ? array_keys($GLOBALS['egw_info']['apps']) : (array)$_appname;
		$types = $_type == 'all' ? array('import','export') : (array)$_type;

		// Testing: comment out egw_cache call, use this
		//$plugins = self::_get_plugins($appnames, $types);
		foreach($plugins as $appname => $_types) {
			if(!in_array($appname, $appnames)) unset($plugins[$appname]);
		}
		foreach($plugins as $appname => $types) {
			$plugins[$appname] = array_intersect_key($plugins[$appname], $types);
		}
		return $plugins;
	}

	public static function _get_plugins(Array $appnames, Array $types) {
		$plugins = array();
		foreach ($appnames as $appname) {
			if(array_key_exists($appname, self::$blacklist_files) && self::$blacklist_files[$appname] === true) continue;

			$appdir = EGW_INCLUDE_ROOT. "/$appname/inc";
			if(!is_dir($appdir)) continue;
			$d = dir($appdir);

			// step through each file in appdir
			while (false !== ($entry = $d->read())) {
				// Blacklisted?
				if(is_array(self::$blacklist_files[$appname]) && in_array($entry, self::$blacklist_files[$appname]))  continue;

				list( ,$classname, ,$extension) = explode('.',$entry);
				$file = $appdir. '/'. $entry;

				foreach ($types as $type) {
					if( !is_file($file) || strpos($entry, $type) === false || $extension != 'php' ) continue;
					require_once($file);
					$reflectionClass = new ReflectionClass($classname);
					if($reflectionClass->IsInstantiable() &&
							$reflectionClass->implementsInterface('importexport_iface_'.$type.'_plugin')) {
						try {
							$plugin_object = new $classname;
						}
						catch (Exception $exception) {
							continue;
						}
						$plugins[$appname][$type][$classname] = $plugin_object->get_name();
						unset ($plugin_object);
					}
				}
			}
			$d->close();

			$config = config::read('importexport');
			if($config['update'] == 'auto') {
				self::load_defaults($appname);
			}
		}
		//error_log(__CLASS__.__FUNCTION__.print_r($plugins,true));
		return $plugins;
	}

	/**
	 * returns list of apps which have plugins of given type.
	 *
	 * @param string $_type
	 * @return array $num => $appname
	 */
	public static function get_apps($_type, $ignore_acl = false) {
		$apps = array_keys(self::get_plugins('all',$_type));
		if($ignore_acl) return $apps;

		foreach($apps as $key => $app) {
			if(!self::has_definitions($app, $_type)) unset($apps[$key]);
		}
		return $apps;
	}

	public static function load_defaults($appname) {
		// Check for new definitions to import from $appname/setup/*.xml
		$appdir = EGW_INCLUDE_ROOT. "/$appname/setup";
		if(!is_dir($appdir)) continue;
		$d = dir($appdir);

		// step through each file in app's setup
		while (false !== ($entry = $d->read())) {
			$file = $appdir. '/'. $entry;
			list( $filename, $extension) = explode('.',$entry);
			if ( $extension != 'xml' ) continue;
			try {
				// import will skip invalid files
				importexport_definitions_bo::import( $file );
			} catch (Exception $e) {
				error_log(__CLASS__.__FUNCTION__. " import $appname definitions: " . $e->getMessage());
			}
		}
		$d->close();
	}

	public static function guess_filetype( $_file ) {

	}

	/**
	 * returns if the given app has importexport definitions for the current user
	 *
	 * @param string $_appname {<appname> | all}
	 * @param string $_type {import | export | all}
	 * @return boolean
	 */
	public static function has_definitions( $_appname = 'all', $_type = 'all' ) {
		$definitions = egw_cache::getSession(
			__CLASS__,
			'has_definitions',
			array('importexport_helper_functions','_has_definitions'),
			array(array_keys($GLOBALS['egw_info']['apps']), array('import', 'export')),
			self::CACHE_EXPIRATION
		);
		$appnames = $_appname == 'all' ? array_keys($GLOBALS['egw_info']['apps']) : (array)$_appname;
		$types = $_type == 'all' ? array('import','export') : (array)$_type;

		// Testing: Comment out cache call above, use this
		//$definitions = self::_has_definitions($appnames, $types);

		foreach($definitions as $appname => $_types) {
			if(!in_array($appname, $appnames)) unset($definitions[$appname]);
		}
		foreach($definitions as $appname => $_types) {
			$definitions[$appname] = array_intersect_key($definitions[$appname], array_flip($types));
		}
		return count($definitions[$appname]) > 0;
	}

	// egw_cache needs this public
	public static function _has_definitions(Array $appnames, Array $types) {
		$def = new importexport_definitions_bo(array('application'=>$appnames, 'type' => $types));
		$list = array();
		foreach((array)$def->get_definitions() as $id) {
			// Need to instanciate it to check, but if the user doesn't have permission, it throws an exception
			try {
				$definition = new importexport_definition($id);
				if($def->is_permitted($definition->get_record_array())) {
					$list[$definition->application][$definition->type][] = $id;
				}
			} catch (Exception $e) {
				// That one doesn't work, keep going
			}
			$definition = null;
		}
		return $list;
	}
} // end of importexport_helper_functions
