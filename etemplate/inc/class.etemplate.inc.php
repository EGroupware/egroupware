<?php
/**
 * EGroupware - eTemplate serverside
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-13 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/**
 * New eTemplate serverside contains:
 * - main server methods like read, exec
 * -
 *
 * Not longer available methods:
 * - set_(row|column)_attributes modifies template on run-time, was only used internally by etemplate itself
 * - disable_(row|column) dto.
 *
 * @ToDo supported customized templates stored in DB, currently we only support xet files stored in filesystem
 */
//class etemplate extends etemplate_old {};
//class etemplate_new extends etemplate_widget_template
class etemplate extends etemplate_widget_template
{
	/**
	 * Are we running as sitemgr module or not
	 *
	 * @public boolean
	 */
	public $sitemgr=false;

	/**
	 * Tell egw framework it's ok to call this
	 */
	public $public_functions = array(
		'process_exec' => true
	);

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

		// generate new etemplate request object, if not already existing
		if(!self::$request)
		{
			self::$request = etemplate_request::read();
			self::$request->content = array();
		}
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
	function exec($method,array $content,array $sel_options=null,array $readonlys=null,array $preserv=null,$output_mode=0,$ignore_validation='',array $changes=null)
	{
		// Include the etemplate2 javascript code
		egw_framework::validate_file('.', 'etemplate2', 'etemplate');

		if (!$this->rel_path) throw new egw_exception_assertion_failed("No (valid) template '$this->name' found!");

		self::$request->output_mode = $output_mode;	// let extensions "know" they are run eg. in a popup
		self::$request->content = $content;
		self::$request->changes = $changes;
		self::$request->sel_options = $sel_options ? $sel_options : array();
		self::$request->readonlys = $readonlys ? $readonlys : array();
		self::$request->preserv = $preserv ? $preserv : array();
		self::$request->method = $method;
		self::$request->ignore_validation = $ignore_validation;
		self::$request->app_header = $GLOBALS['egw_info']['flags']['app_header'];
		if (self::$request->output_mode == -1) self::$request->output_mode = 0;
		self::$request->template = $this->as_array();

		// instanciate template to fill self::$request->sel_options for select-* widgets
		// not sure if we want to handle it this way, thought otherwise we will have a few ajax request for each dialog fetching predefined selectboxes
		$template = etemplate_widget_template::instance($this->name, $this->template_set, $this->version, $this->laod_via);
		$template->run('beforeSendToClient', array('', array('cont'=>$content)));

		$data = array(
			'etemplate_exec_id' => self::$request->id(),
			'app_header' => self::$request->app_header,
			'content' => self::$request->content,
			'sel_options' => self::$request->sel_options,
			'readonlys' => self::$request->readonlys,
			'modifications' => self::$request->modifications,
			'validation_errors' => self::$validation_errors,
		);
		if (self::$response)	// call is within an ajax event / form submit
		{
			self::$response->generic('et2_load', array(
				'name' => $this->name,
				'url' => $GLOBALS['egw_info']['server']['webserver_url'].$this->rel_path,
				'data' => $data,
			));
		}
		else	// first call
		{
			// missing dependency, thought egw:uses jquery.jquery.tools does NOT work, maybe we should rename it to jquery-tools
			// egw_framework::validate_file('jquery','jquery.tools.min');

			egw_framework::includeCSS('/etemplate/templates/default/etemplate2.css');

			// Include the jQuery-UI CSS - many more complex widgets use it
			$theme = 'redmond';
			egw_framework::includeCSS("/phpgwapi/js/jquery/jquery-ui/$theme/jquery-ui-1.8.21.custom.css");

			// check if application of template has a app.js file --> load it
			list($app) = explode('.',$this->name);
			if (file_exists(EGW_SERVER_ROOT.'/'.$app.'/js/app.js'))
			{
				egw_framework::validate_file('.','app',$app,false);
			}

			common::egw_header();
			if ($output_mode != 2)
			{
				parse_navbar();
			}
			// load translations
			translation::add_app('etemplate');
			$langRequire = array();
			foreach(translation::$loaded_apps as $app => $lang)
			{
				$langRequire[] = array('app' => $app, 'lang' => $lang);
			}

			echo '
		<div id="container"></div>
		<script>
			egw.langRequire(window, '.json_encode($langRequire).');
			egw(window).ready(function() {
				var et2 = new etemplate2(document.getElementById("container"), "etemplate::ajax_process_content");
				et2.load("'.$this->name.'","'.$GLOBALS['egw_info']['server']['webserver_url'].$this->rel_path.'",'.json_encode($data).');
			}, null, true);
		</script>
';
			common::egw_footer();
		}
	}

	/**
	 * Process via Ajax submitted content
	 */
	static public function ajax_process_content($etemplate_exec_id, array $content)
	{
		error_log(__METHOD__."(".array2string($etemplate_exec_id).', '.array2string($content).")");

		self::$request = etemplate_request::read($etemplate_exec_id);
		error_log('request='.array2string(self::$request));

		self::$response = egw_json_response::get();

		if (!($template = self::instance(self::$request->template['name'], self::$request->template['template_set'],
			self::$request->template['version'], self::$request->template['load_via'])))
		{
			throw new egw_exception_wrong_parameter('Can NOT read template '.array2string(self::$request->template));
		}

		// Set current app for validation
		list($app) = explode('.',self::$request->method);
		if(!$app) list($app) = explode('::',self::$request->method);
		if($app) $GLOBALS['egw_info']['flags']['currentapp'] = $app;

		$validated = array();
		$expand = array(
			'cont' => &self::$request->content,
		);
		$template->run('validate', array('', $expand, $content, &$validated), true);	// $respect_disabled=true: do NOT validate disabled widgets and children

		if (self::validation_errors(self::$request->ignore_validation))
		{
			error_log(__METHOD__."(,".array2string($content).') validation_errors='.array2string(self::$validation_errors));
			self::$response->generic('et2_validation_error', self::$validation_errors);
			exit;
		}
		error_log(__METHOD__."(,".array2string($content).')');
		error_log(' validated='.array2string($validated));

		return ExecMethod(self::$request->method, self::complete_array_merge(self::$request->preserv, $validated));
	}

	/**
	 * Process via POST submitted content
	 */
	static public function process_exec()
	{
		$etemplate_exec_id = $_POST['etemplate_exec_id'];
		$content = json_decode($_POST['value'],true);
		if($content == null && $_POST['exec'])
		{
			// Old etemplate submit
			error_log("Old etemplate submitted");
			return ExecMethod('etemplate.etemplate_old.process_exec');
		}
		error_log(__METHOD__."(".array2string($content).")");

		self::$request = etemplate_request::read($_POST['etemplate_exec_id']);
		if (get_magic_quotes_gpc()) $_POST['value'] = stripslashes($_POST['value']);
		$content = json_decode($_POST['value'],true);

		if (!($template = self::instance(self::$request->template['name'], self::$request->template['template_set'],
			self::$request->template['version'], self::$request->template['load_via'])))
		{
			throw new egw_exception_wrong_parameter('Can NOT read template '.array2string(self::$request->template));
		}
		$validated = array();
		$expand = array(
			'cont' => &self::$request->content,
		);
		$template->run('validate', array('', $expand, $content, &$validated), true);	// $respect_disabled=true: do NOT validate disabled widgets and children
		if (self::validation_errors(self::$request->ignore_validation))
		{
			error_log(__METHOD__."(,".array2string($content).') validation_errors='.array2string(self::$validation_errors));
			exit;
		}
		error_log(__METHOD__."(,".array2string($content).')');
		error_log(' validated='.array2string($validated));

		return ExecMethod(self::$request->method, self::complete_array_merge(self::$request->preserv, $validated));
	}

	/**
	 * Path of template relative to EGW_SERVER_ROOT
	 *
	 * @var string
	 */
	public $rel_path;

	public $name;
	public $template_set;
	public $version;
	public $laod_via;

	/**
	 * Reads an eTemplate from filesystem or DB (not yet supported)
	 *
	 * @param string $name name of the eTemplate or array with the values for all keys
	 * @param string $template_set template-set, '' loads the prefered template of the user, 'default' loads the  default one '' in the db
	 * @param string $lang language, '' loads the pref. lang of the user, 'default' loads the default one '' in the db
	 * @param int $group id of the (primary) group of the user or 0 for none, not used at the moment !!!
	 * @param string $version version of the eTemplate
	 * @param mixed $load_via name/array of keys of etemplate to load in order to get $name (only as second try!)
	 * @return boolean True if a fitting template is found, else False
	 *
	 * @ToDo supported customized templates stored in DB
	 */
	public function read($name,$template_set='default',$lang='default',$group=0,$version='',$load_via='')
	{
		$this->rel_path = self::relPath($this->name=$name, $this->template_set=$template_set,
			$this->version=$version, $this->laod_via = $load_via);
		//error_log(__METHOD__."('$name', '$template_set', '$lang', $group, '$version', '$load_via') rel_path=".array2string($this->rel_path));

		return (boolean)$this->rel_path;
	}

	/**
	 * Get template data as array
	 *
	 * @return array
	 */
	public function as_array()
	{
		return array(
			'name' => $this->name,
			'template_set' => $this->template_set,
			'version' => $this->version,
			'load_via' => $this->load_via,
		);
	}

	/**
	 * Returns reference to an attribute in a named cell
	 *
	 * Currently we always return a reference to an not set value, unless it was set before.
	 * We do not return a reference to the actual cell, as it get's contructed on client-side!
	 *
	 * @param string $name cell-name
	 * @param string $attr attribute-name
	 * @return mixed reference to attribute, usually NULL
	 * @deprecated use getElementAttribute($name, $attr)
	 */
	public function &get_cell_attribute($name,$attr)
	{
		return self::getElementAttribute($name, $attr);
	}

	/**
	 * set an attribute in a named cell if val is not NULL else return the attribute
	 *
	 * @param string $name cell-name
	 * @param string $attr attribute-name
	 * @param mixed $val if not NULL sets attribute else returns it
	 * @return reference to attribute
	 * @deprecated use setElementAttribute($name, $attr, $val)
	 */
	public function &set_cell_attribute($name,$attr,$val)
	{
		return self::setElementAttribute($name, $attr, $val);
	}

	/**
	 *  disables all cells with name == $name
	 *
	 * @param sting $name cell-name
	 * @param boolean $disabled=true disable or enable a cell, default true=disable
	 * @return reference to attribute
	 * @deprecated use disableElement($name, $disabled=true)
	 */
	public function disable_cells($name,$disabled=True)
	{
		return self::disableElement($name, $disabled);
	}

	/**
	 * merges $old and $new, content of $new has precedence over $old
	 *
	 * THIS IS NOT THE SAME AS PHP's functions:
	 * - array_merge, as it calls itself recursive for values which are arrays.
	 * - array_merge_recursive accumulates values with the same index and $new does NOT overwrite $old
	 *
	 * @param array $old
	 * @param array $new
	 * @return array the merged array
	 */
	public static function complete_array_merge($old,$new)
	{
		if (is_array($new))
		{
			if (!is_array($old)) $old = (array) $old;

			foreach($new as $k => $v)
			{
				if (!is_array($v) || !isset($old[$k]) || 	// no array or a new array
					isset($v[0]) && !is_array($v[0]) && isset($v[count($v)-1]))	// or no associative array, eg. selecting multiple accounts
				{
					$old[$k] = $v;
				}
				else
				{
					$old[$k] = self::complete_array_merge($old[$k],$v);
				}
			}
		}
		return $old;
	}

	/**
	 * Debug callback just outputting content
	 *
	 * @param array $content=null
	 */
	public function debug(array $content=null)
	{
		common::egw_header();
		_debug_array($content);
		common::egw_footer();
	}

	/**
	 * Message containing the max Upload size from the current php.ini settings
	 *
	 * We have to take the smaler one of upload_max_filesize AND post_max_size-2800 into account.
	 * memory_limit does NOT matter any more, because of the stream-interface of the vfs.
	 *
	 * @param int &$max_upload=null on return max. upload size in byte
	 * @return string
	 */
	static function max_upload_size_message(&$max_upload=null)
	{
		$upload_max_filesize = ini_get('upload_max_filesize');
		$post_max_size = ini_get('post_max_size');
		$max_upload = min(self::km2int($upload_max_filesize),self::km2int($post_max_size)-2800);

		return lang('Maximum size for uploads').': '.egw_vfs::hsize($max_upload).
			" (php.ini: upload_max_filesize=$upload_max_filesize, post_max_size=$post_max_size)";
	}

	/**
	 * Format a number according to user prefs with decimal and thousands separator (later only for readonly)
	 *
	 * @param int|float|string $number
	 * @param int $num_decimal_places=2
	 * @param boolean $readonly=true
	 * @return string
	 */
	static public function number_format($number,$num_decimal_places=2,$readonly=true)
	{
		static $dec_separator,$thousands_separator;
		if (is_null($dec_separator))
		{
			$dec_separator = $GLOBALS['egw_info']['user']['preferences']['common']['number_format'][0];
			if (empty($dec_separator)) $dec_separator = '.';
			$thousands_separator = $GLOBALS['egw_info']['user']['preferences']['common']['number_format'][1];
		}
		if ((string)$number === '') return '';

		return number_format(str_replace(' ','',$number),$num_decimal_places,$dec_separator,$readonly ? $thousands_separator : '');
	}

	/**
	 * Convert numbers like '32M' or '512k' to integers
	 *
	 * @param string $size
	 * @return int
	 */
	private static function km2int($size)
	{
		if (!is_numeric($size))
		{
			switch(strtolower(substr($size,-1)))
			{
				case 'm':
					$size = 1024*1024*(int)$size;
					break;
				case 'k':
					$size = 1024*(int)$size;
					break;
			}
		}
		return (int)$size;
	}

	/**
	* creates HTML from an eTemplate
	*
	* Compatibility function for calendar, which uses etemplate::show to generate html --> use etemplate_old class
	*
	* This is done by calling show_cell for each cell in the form. show_cell itself
	* calls show recursivly for each included eTemplate.
	* You could use it in the UI-layer of an app, just make shure to call process_show !!!
	* This is intended as internal function and should NOT be called by new app's direct,
	* as it deals with HTML and is so UI-dependent, use exec instead.
	*
	* @param array $content with content for the cells, keys are the names given in the cells/form elements
	* @param array $sel_options with options for the selectboxes, keys are the name of the selectbox
	* @param array $readonlys with names of cells/form-elements to be not allowed to change
	* 		This is to facilitate complex ACL's which denies access on field-level !!!
	* @param string $cname basename of names for form-elements, means index in $_POST
	* 		eg. $cname='cont', element-name = 'name' returned content in $_POST['cont']['name']
	* @param string $show_c name/index for name expansion
	* @param string $show_row name/index for name expansion
	* @return string the generated HTML
	*/
	function show($content,$sel_options='',$readonlys='',$cname='',$show_c=0,$show_row=0)
	{
		$etemplate_old = new etemplate_old($this->name, $this->laod_via);

		return $etemplate_old->show($content,$sel_options,$readonlys,$cname,$show_c,$show_row);
	}

	/**
	 * Avoid fatal error from addressbook
	 */
	public static function cats2color($cats)
	{
		trigger_error('Calling etemplate_old', E_USER_NOTICE);
		return etemplate_old::cats2color($cats);
	}
}

// Try to discover all widgets, as names don't always match tags (eg: listbox is in menupopup)
$files = scandir(EGW_INCLUDE_ROOT . '/etemplate/inc');
foreach($files as $filename)
{
	if(strpos($filename, 'class.etemplate_widget') === 0)
	{
		try
		{
			include_once($filename);
		}
		catch(Exception $e)
		{
			error_log($e->getMessage());
		}
	}
}
