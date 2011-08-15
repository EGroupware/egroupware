<?php
/**
 * EGroupware - eTemplate serverside
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-11 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/**
 * New eTemplate serverside contains:
 * - main server methods like read, exec
 * -
 *
 * @ToDo supported customized templates stored in DB, currently we only support xet files stored in filesystem
 */
class etemplate_new
{
	/**
	 * Are we running as sitemgr module or not
	 *
	 * @public boolean
	 */
	public $sitemgr=false;

	/**
	 * constructor of etemplate class, reads an eTemplate if $name is given
	 *
	 * @param string $name of etemplate or array with name and other keys
	 * @param string|array $load_via with keys of other etemplate to load in order to get $name
	 */
	function __construct($name='',$load_via='')
	{
		$this->sitemgr = isset($GLOBALS['Common_BO']) && is_object($GLOBALS['Common_BO']);

		if ($name) $this->read($name,$template='default',$lang='default',$group=0,$version='',$load_via);
	}

	/**
	 * Abstracts a html-location-header call
	 *
	 * In other UI's than html this needs to call the methode, defined by menuaction or
	 * open a browser-window for any other links.
	 *
	 * @param string|array $params url or array with get-params incl. menuaction
	 */
	static function location($params='')
	{
		egw::redirect_link(is_array($params) ? '/index.php' : $params,
			is_array($params) ? $params : '');
	}

	/**
	 * Generates a Dialog from an eTemplate - abstract the UI-layer
	 *
	 * This is the only function an application should use, all other are INTERNAL and
	 * do NOT abstract the UI-layer, because they return HTML.
	 * Generates a webpage with a form from the template and puts process_exec in the
	 * form as submit-url to call process_show for the template before it
	 * ExecuteMethod's the given $method of the caller.
	 *
	 * @param string $method Methode (e.g. 'etemplate.editor.edit') to be called if form is submitted
	 * @param array $content with content to fill the input-fields of template, eg. the text-field
	 * 		with name 'name' gets its content from $content['name']
	 * @param $sel_options array or arrays with the options for each select-field, keys are the
	 * 		field-names, eg. array('name' => array(1 => 'one',2 => 'two')) set the
	 * 		options for field 'name'. ($content['options-name'] is possible too !!!)
	 * @param array $readonlys with field-names as keys for fields with should be readonly
	 * 		(eg. to implement ACL grants on field-level or to remove buttons not applicable)
	 * @param array $preserv with vars which should be transported to the $method-call (eg. an id) array('id' => $id) sets $_POST['id'] for the $method-call
	 * @param int $output_mode
	 *	 0 = echo incl. navbar
	 *	 1 = return html
	 *	-1 = first time return html, after use 0 (echo html incl. navbar), eg. for home
	 *	 2 = echo without navbar (eg. for popups)
	 *	 3 = return eGW independent html site
	 * @param string $ignore_validation if not empty regular expression for validation-errors to ignore
	 * @param array $changes change made in the last call if looping, only used internaly by process_exec
	 * @return string html for $output_mode == 1, else nothing
	 */
	function exec($method,$content,$sel_options='',$readonlys='',$preserv='',$output_mode=0,$ignore_validation='',$changes='')
	{
		if (!$this->rel_path) throw new egw_exception_assertion_failed('No (valid) template read!');

/*		if (self::$response)	// call is within an ajax event / form submit
		{

		}
		else	// first call
*/		{
			egw_framework::validate_file('.','et2_all','etemplate');

			egw_framework::includeCSS('/etemplate/js/test/test.css');
			common::egw_header();
			if ($output_mode != 2)
			{
				parse_navbar();
			}
			echo '
		<div id="container"></div>
		<script>
			var container = null;

			function open_xet(file, content) {
				et2_loadXMLFromURL(file,
					function(_xmldoc) {
						if (container != null)
						{
							container.destroy();
							container = null;
						}

						container = new et2_container(null);
						container.setParentDOMNode(document.getElementById("container"));
						container.setContentMgr(new et2_contentArrayMgr(content));
						container.loadFromXML(_xmldoc);
					});
			}
			open_xet("'.$GLOBALS['egw_info']['server']['webserver_url'].$this->rel_path.'",'.json_encode(array(
				'content' => $content,
				'sel_options' => $sel_options,
				'readonlys' => $readonlys,
				'modifications' => $this->modifications,
				'validation_errros' => self::$validation_errors,
			)).');
		</script>
';
			common::egw_footer();
		}
	}

	/**
	 * Path of template relative to EGW_SERVER_ROOT
	 *
	 * @var string
	 */
	public $rel_path;

	/**
	 * Reads an eTemplate from filesystem or DB (not yet supported)
	 *
	 * @param string $name name of the eTemplate or array with the values for all keys
	 * @param string $template template-set, '' loads the prefered template of the user, 'default' loads the  default one '' in the db
	 * @param string $lang language, '' loads the pref. lang of the user, 'default' loads the default one '' in the db
	 * @param int $group id of the (primary) group of the user or 0 for none, not used at the moment !!!
	 * @param string $version version of the eTemplate
	 * @param mixed $load_via name/array of keys of etemplate to load in order to get $name (only as second try!)
	 * @return boolean True if a fitting template is found, else False
	 *
	 * @ToDo supported customized templates stored in DB
	 */
	public function read($name,$template='default',$lang='default',$group=0,$version='',$load_via='')
	{
		list($app, $tpl_name) = explode('.', $name, 2);

		$this->rel_path = '/'.$app.'/templates/'.$template.'/'.$tpl_name.'.xet';
		if (!file_exists(EGW_SERVER_ROOT.$this->rel_path) && $template !== 'default')
		{
			$this->rel_path = '/'.$app.'/templates/default/'.$tpl_name.'.xet';
		}
		if (!file_exists(EGW_SERVER_ROOT.$this->rel_path))
		{
			$this->rel_path = null;
			error_log(__METHOD__."('$name',...,'$load_via') returning FALSE");
			return false;
		}
		//error_log(__METHOD__."('$name',...,'$load_via') this->rel_path=$this->rel_path returning TRUE");
		return true;
	}

	/**
	 * Validation errors from process_show and the extensions, should be set via etemplate::set_validation_error
	 *
	 * @public array form_name => message pairs
	 */
	static protected $validation_errors = array();

	/**
	 * Sets a validation error, to be displayed in the next exec
	 *
	 * @param string $name (complete) name of the widget causing the error
	 * @param string $error error-message already translated
	 * @param string $cname=null set it to '', if the name is already a form-name, defaults to self::$name_vars
	 */
	static function set_validation_error($name,$error,$cname=null)
	{
		if (is_null($cname)) $cname = self::$name_vars;
		//echo "<p>etemplate::set_validation_error('$name','$error','$cname');</p>\n";
		if ($cname) $name = self::form_name($cname,$name);

		if (self::$validation_errors[$name])
		{
			self::$validation_errors[$name] .= ', ';
		}
		self::$validation_errors[$name] .= $error;
	}

	/**
	* Check if we have not ignored validation errors
	*
	* @param string $ignore_validation='' if not empty regular expression for validation-errors to ignore
	* @param string $cname=null name-prefix, which need to be ignored, default self::$name_vars
	* @return boolean true if there are not ignored validation errors, false otherwise
	*/
	function validation_errors($ignore_validation='',$cname=null)
	{
		if (is_null($cname)) $cname = self::$name_vars;
		//echo "<p>uietemplate::validation_errors('$ignore_validation','$cname') validation_error="; _debug_array(self::$validation_errors);
		if (!$ignore_validation) return count(self::$validation_errors) > 0;

		foreach(self::$validation_errors as $name => $error)
		{
			if ($cname) $name = preg_replace('/^'.$cname.'\[([^\]]+)\](.*)$/','\\1\\2',$name);

			// treat $ignoare_validation only as regular expression, if it starts with a slash
			if ($ignore_validation[0] == '/' && !preg_match($ignore_validation,$name) ||
				$ignore_validation[0] != '/' && $ignore_validation != $name)
			{
				//echo "<p>uietemplate::validation_errors('$ignore_validation','$cname') name='$name' ($error) not ignored!!!</p>\n";
				return true;
			}
			//echo "<p>uietemplate::validation_errors('$ignore_validation','$cname') name='$name' ($error) ignored</p>\n";
		}
		return false;
	}

	/**
	 * Modifications on the instancated template
	 *
	 * Get collected here to be send to the server
	 */
	protected $modifications = array();

	/**
	 * Returns reference to an attribute in a named cell
	 *
	 * Currently we always return a reference to an not set value, unless it was set before.
	 * We do not return a reference to the actual cell, as it get's contructed on client-side!
	 *
	 * @param string $name cell-name
	 * @param string $attr attribute-name
	 * @return mixed reference to attribute, usually NULL
	 */
	function &get_cell_attribute($name,$attr)
	{
		error_log(__METHOD__."('$name', '$attr')");

		return $this->modifications[$name][$attr];
	}

	/**
	 * set an attribute in a named cell if val is not NULL else return the attribute
	 *
	 * @param string $name cell-name
	 * @param string $attr attribute-name
	 * @param mixed $val if not NULL sets attribute else returns it
	 * @return mixed number of changed cells or False, if none changed
	 */
	function &set_cell_attribute($name,$attr,$val)
	{
		error_log(__METHOD__."('$name', '$attr', ".array2string($val).')');

		$attr =& $this->get_cell_attribute($name, $attr);
		if (!is_null($val)) $attr = $val;

		return $attr;
	}

	/**
	 *  disables all cells with name == $name
	 *
	 * @param sting $name cell-name
	 * @param boolean $disabled=true disable or enable a cell, default true=disable
	 * @return mixed number of changed cells or False, if none changed
	 */
	function disable_cells($name,$disabled=True)
	{
		return $this->set_cell_attribute($name,'disabled',$disabled);
	}

	/**
	 * set one or more attibutes for row $n
	 *
	 * @param int $n numerical row-number starting with 1 (!)
	 * @param string $height percent or pixel or '' for no height
	 * @param string $class name of css class (without the leading '.') or '' for no class
	 * @param string $valign alignment (top,middle,bottom) or '' for none
	 * @param boolean $disabled True or expression or False to disable or enable the row (Only the number 0 means dont change the attribute !!!)
	 * @param string $path='/0' default is the first widget in the tree of children
	 * @return false if $path is no grid or array(height,class,valign,disabled) otherwise
	 */
	function set_row_attributes($n,$height=0,$class=0,$valign=0,$disabled=0,$path='/0')
	{
		throw new egw_exception_assertion_failed('Not yet implemented!');

		$grid =& $this->get_widget_by_path($path);
		if (is_null($grid) || $grid['type'] != 'grid') return false;
		$grid_attr =& $grid['data'][0];

		list($old_height,$old_disabled) = explode(',',$grid_attr["h$n"]);
		$disabled = $disabled !== 0 ? $disabled : $old_disabled;
		$grid_attr["h$n"] = ($height !== 0 ? $height : $old_height).
			($disabled ? ','.$disabled : '');
		list($old_class,$old_valign) = explode(',',$grid_attr["c$n"]);
		$valign = $valign !== 0 ? $valign : $old_valign;
		$grid_attr["c$n"] = ($class !== 0 ? $class : $old_class).
			($valign ? ','.$valign : '');

		list($height,$disabled) = explode(',',$grid_attr["h$n"]);
		list($class,$valign) = explode(',',$grid_attr["c$n"]);
		return array($height,$class,$valign,$disabled);
	}

	/**
	 * disables row $n
	 *
	 * @param int $n numerical row-number starting with 1 (!)
	 * @param boolean $enable=false can be used to re-enable a row if set to True
	 * @param string $path='/0' default is the first widget in the tree of children
	 */
	function disable_row($n,$enable=False,$path='/0')
	{
		$this->set_row_attributes($n,0,0,0,!$enable,$path);
	}

	/**
	 * set one or more attibutes for column $c
	 *
	 * @param int|string $c numerical column-number starting with 0 (!), or the char-code starting with 'A'
	 * @param string $width percent or pixel or '' for no height
	 * @param mixed $disabled=0 True or expression or False to disable or enable the column (Only the number 0 means dont change the attribute !!!)
	 * @param string $path='/0' default is the first widget in the tree of children
	 * @return false if $path specifies no grid or array(width,disabled) otherwise
	 */
	function set_column_attributes($c,$width=0,$disabled=0,$path='/0')
	{
		throw new egw_exception_assertion_failed('Not yet implemented!');

		if (is_numeric($c))
		{
			$c = $this->num2chrs($c);
		}
		$grid =& $this->get_widget_by_path($path);
		if (is_null($grid) || $grid['type'] != 'grid') return false;
		$grid_attr =& $grid['data'][0];

		list($old_width,$old_disabled) = explode(',',$grid_attr[$c]);
		$disabled = $disabled !== 0 ? $disabled : $old_disabled;
		$grid_attr[$c] = ($width !== 0 ? $width : $old_width).
			($disabled ? ','.$disabled : '');

		//echo "set_column_attributes('$c',,'$path'): ".$grid_attr[$c]."</p>\n"; _debug_array($grid_attr);
		return explode(',',$grid_attr[$c]);
	}

	/**
	 * disables column $c
	 *
	 * @param int|string $c numerical column-number starting with 0 (!), or the char-code starting with 'A'
	 * @param boolean $enable can be used to re-enable a column if set to True
	 * @param string $path='/0' default is the first widget in the tree of children
	 */
	function disable_column($c,$enable=False,$path='/0')
	{
		$this->set_column_attributes($c,0,!$enable,$path);
	}
}
