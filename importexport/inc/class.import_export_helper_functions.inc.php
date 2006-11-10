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
 * class import_export_helper_functions (only static methods)
 * use import_export_helper_functions::method
 */
class import_export_helper_functions
{

	/** Aggregations: */

	/** Compositions: */

	/*** Attributes: ***/

	/**
	 * nothing to construct here, only static functions!
	 *
	 * @return bool false
	 */
	public function __construct() {
		return false;
	}
	/**
	 * converts accound_lid to account_id
	 *
	 * @param string _account_lid comma seperated list
	 * @return string comma seperated list
	 * @static
	 * @access public
	 */
	public static function account_lid2id( $_account_lids ) {
		$account_lids = explode( ',', $_account_lids );
		foreach ( $account_lids as $account_lid ) {
			if ( $account_id = $GLOBALS['egw']->accounts->name2id( $account_lid )) {
				$account_ids[] = $account_id;
			}
		}
		return implode( ',', $account_ids );
		
	} // end of member function account_lid2id

	/**
	 * converts account_ids to account_lids
	 *
	 * @param int _account_ids comma seperated list
	 * @return string comma seperated list
	 * @static
	 * @access public
	 */
	public static function account_id2lid( $_account_id ) {
		$account_ids = explode( ',', $_account_id );
		foreach ( $account_ids as $account_id ) {
			if ( $account_lid = $GLOBALS['egw']->accounts->id2name( $account_id )) {
				$account_lids[] = $account_lid;
			}
		}
		return implode( ',', $account_lids );
	} // end of member function account_id2lid

	/**
	 * converts cat_id to a cat_name
	 *
	 * @param int _cat_ids comma seperated list
	 * @return mixed string cat_name
	 * @static 
	 * @access public
	 */
	public static function cat_id2name( $_cat_ids ) {
		if ( !is_object($GLOBALS['egw']->categories) ) {
			$GLOBALS['egw']->categories =& CreateObject('phpgwapi.categories');
		}
		$cat_ids = explode( ',', $_cat_id );
		foreach ( $cat_ids as $cat_id ) {
			$cat_names[] = $GLOBALS['egw']->categories->id2name( (int)$cat_id );
		}
		return implode(',',$cat_names);
	} // end of member function category_id2name

	/**
	 * converts cat_name to a cat_id.
	 * If a cat isn't found, it will be created.
	 *
	 * @param string _cat_names comma seperated list.
	 * @return mixed int / string (comma seperated cat id's)
	 * @static
	 * @access public
	 */
	public static function cat_name2id( $_cat_names, $_create = true ) {
		if (!is_object($GLOBALS['egw']->categories)) {
			$GLOBALS['egw']->categories =& CreateObject( 'phpgwapi.categories' );
		}
		$cat_names = explode( ',', $_cat_names );
		foreach ( $cat_names as $cat_name ) {
			if ( $cat_id = $GLOBALS['egw']->categories->name2id( addslashes( $cat_name ))) { }
			elseif ($_create) $cat_id = $GLOBALS['egw']->categories->add( array( 'name' => $cat_name,'descr' => $cat_name ));
			else continue;
			$cat_ids[] = $cat_id;
		}
		return implode( ',', $cat_ids );
		
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
	 * you can use all other _record fields, with the syntax |[FIELDNAME].
	 * Example: 
	 * .+|>|[Company]: |[NFamily], |[NGiven]|||[NFamily], |[NGiven]
	 * It is used on the _record field 'Company' and constructs a something like 
	 * Company: FamilyName, GivenName or FamilyName, GivenName if 'Company' is empty.
	 * 
	 * Moreover the helper function of this class can be used using the '@' operator.
	 * @cat_name2id(Cat1,...,CatN) returns a (','-separated) list with the cat_id's. If a 
	 * category isn't found, it will be automaticaly added.
	 *
	 * Patterns as well as the replacement can be regular expressions (the replacement is done 
	 * via ereg_replace).
	 * 
	 * If, after all replacements, the value starts with an '@' the whole 
	 * value is eval()'ed, so you may use all php, phpgw plus your own functions. This is quiet 
	 * powerfull, but circumvents all ACL. Therefor this feature is only availible to 
	 * Adminstrators.
	 * 
	 * Example using regular expressions and '@'-eval(): 
	 * ||0?([0-9]+)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*)[ .:-]+0?([0-9]*).*|>@mktime(|#4,|#5,|#6,|#2,|#3,|#1)
	 * It will read a date of the form '2001-05-20 08:00:00.00000000000000000' (and many more, 
	 * see the regular expr.). The [ .:-]-separated fields are read and assigned in different 
	 * order to @mktime(). Please note to use |# insted of a backslash (I couldn't get backslash 
	 * through all the involved templates and forms.) plus the field-number of the pattern.
	 * 
	 * @param array _record reference with record to do the conversion with
	 * @param array _conversion array with conversion description
	 * @return bool
	 * @static
	 * @access public
	 */
	public static function conversion( $_record,  $_conversion ) {
		
		$PSep = '||'; // Pattern-Separator, separats the pattern-replacement-pairs in conversion
		$ASep = '|>'; // Assignment-Separator, separats pattern and replacesment
		$VPre = '|#'; // Value-Prefix, is expanded to \ for ereg_replace
		$CPre = '|['; $CPreReg = '\|\['; // |{_record-fieldname} is expanded to the value of the _record-field
		$CPos = ']';  $CPosReg = '\]';	// if used together with @ (replacement is eval-ed) value gets autom. quoted
		
		foreach ( $_record as $record_idx => $record_value ) {
			$pat_reps = explode($PSep,stripslashes($_conversion[$record_idx]));
			$replaces = ''; $rvalues = '';
			if($pat_reps[0] != '')
			{
				foreach($pat_reps as $k => $pat_rep)
				{
					list($pattern,$replace) = explode($ASep,$pat_rep,2);
					if($replace == '')
					{
						$replace = $pattern; $pattern = '^.*$';
					}
					$rvalues[$pattern] = $replace;	// replace two with only one, added by the form
					$replaces .= ($replaces != '' ? $PSep : '') . $pattern . $ASep . $replace;
				}
				//$_conversion[$record_idx] = $rvalues;
				$conv_record = $rvalues;
			}
			else
			{
				//unset($_conversion[$record_idx] );
			}
			
			if(!empty($_conversion[$record_idx]))
			{
				//$conv_record = $_conversion[$record_idx];
				while(list($pattern,$replace) = each($conv_record))
				{
					if(ereg((string) $pattern,$val))
					{
						$val = ereg_replace((string) $pattern,str_replace($VPre,'\\',$replace),(string) $val);

						$reg = $CPreReg.'([a-zA-Z_0-9]+)'.$CPosReg;
						while(ereg($reg,$val,$vars))
						{	// expand all _record fields
							$val = str_replace($CPre . $vars[1] . $CPos, $val[0] == '@' ? "'"
								. addslashes($fields[array_search($vars[1], array_keys($_record))])
								. "'" : $fields[array_search($vars[1], array_keys($_record))], $val);
						}
						if($val[0] == '@')
						{
							if (!$GLOBALS['egw_info']['user']['apps']['admin'])
							{
								error_log(__FILE__.__LINE__. lang('@-eval() is only availible to admins!!!'));
							}
							else
							{
								// removing the $ to close security hole of showing vars, which contain eg. passwords
								$val = substr(str_replace('$','',$val),1).';';
								$val = 'return '. (substr($val,0,6) == 'cat_id' ? '$this->'.$val : $val);
								// echo "<p>eval('$val')=";
								$val = eval($val);
								// echo "'$val'</p>";
							}
						}
						if($pattern[0] != '@' || $val)
						{
							break;
						}
					}
				}
			}
			$values[$record_idx] = $val;
		}
		return $values;
	} // end of member function conversion

	/**
	 * returns a list of importexport plugins 
	 *
	 * @param string $_tpye {import | export | all}
	 * @param string $_appname {<appname> | all}
	 * @return array(<appname> => array( <type> => array(<plugin> => <title>)))
	 */
	public static function get_plugins($_appname, $_type){
		$appnames = $_appname == 'all' ? array_keys($GLOBALS['egw_info']['apps']) : (array)$_appname;
		$types = $_types == 'all' ? array('import','export') : (array)$_type;
		$plugins = array();
		
		foreach ($appnames as $appname) {
			$appdir = EGW_INCLUDE_ROOT. "/$appname/inc";
			if(!is_dir($appdir)) continue;
			$d = dir($appdir);
			
			// step through each file in appdir
			while (false !== ($entry = $d->read())) {
				list( ,$classname, ,$extension) = explode('.',$entry);
				$file = $appdir. '/'. $entry;

				foreach ($types as $type) {
					if(!is_file($file) || substr($classname,0,7) != $type.'_' || $extension != 'php') continue;
					require_once($file);
					
					try {
						$plugin_object = @new $classname;
					}
					catch (Exception $exception) {
						continue;
					}
					if (is_a($plugin_object,'iface_'.$type.'_plugin')) {
						$plugins[$appname][$type][$classname] = $plugin_object->get_name();
					}
					unset ($plugin_object);
				}
			}
			$d->close();
		}
		return $plugins;	
	}
	
	/**
	 * returns list of apps which have plugins of given type.
	 *
	 * @param string $_type
	 * @return array $num => $appname
	 */
	public static function get_apps($_type) {
		return array_keys(self::get_plugins('all',$_type));
	}

} // end of import_export_helper_functions
?>
