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
class etemplate_new extends etemplate_widget_template
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
		// we do NOT call parent consturctor, as we only want to enherit it's (static) methods
		if (false) parent::__construct ($name);	// satisfy IDE, as we dont call parent constructor

		$this->sitemgr = isset($GLOBALS['Common_BO']) && is_object($GLOBALS['Common_BO']);

		if ($name) $this->read($name,$template='default','default',0,'',$load_via);

		// generate new etemplate request object, if not already existing
		if(!isset(self::$request)) self::$request = etemplate_request::read();
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
		self::$request->content = self::$cont = $content;
		self::$request->changes = $changes;
		self::$request->sel_options = is_array($sel_options) ? self::fix_sel_options($sel_options) : array();
		self::$request->readonlys = $readonlys ? $readonlys : array();
		self::$request->preserv = $preserv ? $preserv : array();
		self::$request->method = $method;
		self::$request->ignore_validation = $ignore_validation;
		if (self::$request->output_mode == -1) self::$request->output_mode = 0;
		self::$request->template = $this->as_array();

		if (empty($this->name)) throw new egw_exception_assertion_failed("Template  name is not set '$this->name' !");
		// instanciate template to fill self::$request->sel_options for select-* widgets
		// not sure if we want to handle it this way, thought otherwise we will have a few ajax request for each dialog fetching predefined selectboxes
		$template = etemplate_widget_template::instance($this->name, $this->template_set, $this->version, $this->laod_via);
		if (!$template) throw new egw_exception_assertion_failed("Template $this->name not instanciable! Maybe you forgot to rename template id.");
		$template->run('beforeSendToClient', array('', array('cont'=>$content)));

		// some apps (eg. InfoLog) set app_header only in get_rows depending on filter settings
		self::$request->app_header = $GLOBALS['egw_info']['flags']['app_header'];

		// compile required translations translations
		translation::add_app('etemplate');
		$currentapp = $GLOBALS['egw_info']['flags']['currentapp'];
		$langRequire = array('common' => array(), 'etemplate' => array());	// keep that order
		foreach(translation::$loaded_apps as $l_app => $lang)
		{
			if (!in_array($l_app, array($currentapp, 'custom')))
			{
				$langRequire[$l_app] = array('app' => $l_app, 'lang' => $lang, 'etag' => translation::etag($l_app, $lang));
			}
		}
		foreach(array($currentapp, 'custom') as $l_app)
		{
			if (isset(translation::$loaded_apps[$l_app]))
			{
				$langRequire[$l_app] = array('app' => $l_app, 'lang' => translation::$loaded_apps[$l_app], 'etag' => translation::etag($l_app, translation::$loaded_apps[$l_app]));
			}
		}

		$data = array(
			'etemplate_exec_id' => self::$request->id(),
			'app_header' => self::$request->app_header,
			'content' => self::$request->content,
			'sel_options' => self::$request->sel_options,
			'readonlys' => self::$request->readonlys,
			'modifications' => self::$request->modifications,
			'validation_errors' => self::$validation_errors,
			'langRequire' => array_values($langRequire),
			'currentapp' => $currentapp,
		);

		// Info required to load the etemplate client-side
		$dom_id = str_replace('.','-',$this->dom_id);
		$load_array = array(
			'name' => $this->name,
			'url' => etemplate_widget_template::rel2url($this->rel_path),
			'data' => $data,
			'DOMNodeID' => $dom_id,
		);
		if (self::$response)	// call is within an ajax event / form submit
		{
			//error_log("Ajax " . __LINE__);
			self::$response->generic('et2_load', $load_array+egw_framework::get_extra());
			egw_framework::clear_extra();	// to not send/set it twice for multiple etemplates (eg. CRM view)
		}
		else	// first call
		{
			// missing dependency, thought egw:uses jquery.jquery.tools does NOT work, maybe we should rename it to jquery-tools
			// egw_framework::validate_file('jquery','jquery.tools.min');

			// Include the jQuery-UI CSS - many more complex widgets use it
			$theme = 'redmond';
			egw_framework::includeCSS("/phpgwapi/js/jquery/jquery-ui/$theme/jquery-ui-1.10.3.custom.css");
			// Load our CSS after jQuery-UI, so we can override it
			egw_framework::includeCSS('/etemplate/templates/default/etemplate2.css');

			// check if application of template has a app.js file --> load it
			list($app) = explode('.',$this->name);
			if (file_exists(EGW_SERVER_ROOT.'/'.$app.'/js/app.js'))
			{
				egw_framework::validate_file('.','app',$app,false);
			}

			// check if we are in an ajax-exec call from jdots template (or future other tabbed templates)
			if (isset($GLOBALS['egw']->framework->response))
			{
				$content = '<div id="'.$dom_id.'" class="et2_container"></div>';
				// add server-side page-generation times
				if($GLOBALS['egw_info']['user']['preferences']['common']['show_generation_time'])
				{
					$vars = $GLOBALS['egw']->framework->_get_footer();
					$content .= "\n".$vars['page_generation_time'];
				}
				$GLOBALS['egw']->framework->response->generic("data", array($content));
				$GLOBALS['egw']->framework->response->generic('et2_load',$load_array+egw_framework::get_extra());
				egw_framework::clear_extra();	// to not send/set it twice for multiple etemplates (eg. CRM view)
				self::$request = null;
				return;
			}
			// let framework know, if we are a popup or not ('popup' not true, which is allways used by index.php!)
			if (!isset($GLOBALS['egw_info']['flags']['nonavbar']) || is_bool($GLOBALS['egw_info']['flags']['nonavbar']))
			{
				$GLOBALS['egw_info']['flags']['nonavbar'] = $output_mode == 2 ? 'popup' : false;
			}
			echo $GLOBALS['egw']->framework->header();
			if ($output_mode != 2 && !$GLOBALS['egw_info']['flags']['nonavbar'])
			{
				parse_navbar();
			}
			else	// mark popups as such, by enclosing everything in div#popupMainDiv
			{
				echo '<div id="popupMainDiv">'."\n";
			}
			// Send any accumulated json responses - after flush to avoid sending the buffer as a response
			if(egw_json_response::isJSONResponse())
			{
				$load_array['response'] = egw_json_response::get()->returnResult();
			}
			// <iframe> and <form> tags added only to get browser autocomplete handling working again
			echo '<iframe name="egw_iframe_autocomplete_helper" src="about:blank" style="display:none;"></iframe><form id="egw_form_autocomplete_helper" target="egw_iframe_autocomplete_helper" action="about:blank" method="get"><div id="'.$dom_id.'" class="et2_container" data-etemplate="'.html::htmlspecialchars(egw_json_response::json_encode($load_array), true).'"></div></form>';

			if ($output_mode == 2)
			{
				echo "\n</div>\n";
				echo $GLOBALS['egw']->framework->footer();
			}
			ob_flush();
		}
		self::$request = null;
	}

	/**
	 * Fix all sel_options, as etemplate_widget_menupopup::beforeSendToClient is not run for auto-repeated stuff not understood by server
	 *
	 * @param array $sel_options
	 * @return array
	 */
	static protected function fix_sel_options(array $sel_options)
	{
		foreach($sel_options as &$options)
		{
			foreach($options as $key => $value)
			{
				if (is_numeric($key) && (!is_array($value) || !isset($value['value'])))
				{
					etemplate_widget_menupopup::fix_encoded_options($options, true);
					break;
				}
			}
		}
		return $sel_options;
	}

	/**
	 * Process via Ajax submitted content
	 *
	 * @param string $etemplate_exec_id
	 * @param array $content
	 * @param boolean $no_validation
	 * @throws egw_exception_wrong_parameter
	 */
	static public function ajax_process_content($etemplate_exec_id, array $content, $no_validation)
	{
		//error_log(__METHOD__."(".array2string($etemplate_exec_id).', '.array2string($content).")");

		self::$request = etemplate_request::read($etemplate_exec_id);
		//error_log('request='.array2string(self::$request));

		self::$response = egw_json_response::get();

		if (!($template = self::instance(self::$request->template['name'], self::$request->template['template_set'],
			self::$request->template['version'], self::$request->template['load_via'])))
		{
			throw new egw_exception_wrong_parameter('Can NOT read template '.array2string(self::$request->template));
		}

		// Set current app for validation
		list($app) = explode('.',self::$request->method);
		if(!$app) list($app) = explode('::',self::$request->method);
		if($app)
		{
			translation::add_app($app);
			$GLOBALS['egw_info']['flags']['currentapp'] = $app;
		}
		$validated = array();
		$expand = array(
			'cont' => &self::$request->content,
		);
		$template->run('validate', array('', $expand, $content, &$validated), true);	// $respect_disabled=true: do NOT validate disabled widgets and children

		if ($no_validation)
		{
			self::$validation_errors = array();
		}
		elseif (self::validation_errors(self::$request->ignore_validation))
		{
			error_log(__METHOD__."(,".array2string($content).') validation_errors='.array2string(self::$validation_errors));
			self::$response->generic('et2_validation_error', self::$validation_errors);
			exit;
		}

		// tell request call to remove request, if it is not modified eg. by call to exec in callback
		self::$request->remove_if_not_modified();

		//error_log(__METHOD__."(,".array2string($content).')');
		//error_log(' validated='.array2string($validated));
		$content = ExecMethod(self::$request->method, self::complete_array_merge(self::$request->preserv, $validated));

		if (isset($GLOBALS['egw_info']['flags']['java_script']))
		{
			// Strip out any script tags
			$GLOBALS['egw_info']['flags']['java_script'] = preg_replace(array('/(<script[^>]*>)([^<]*)/is','/<\/script>/'),array('$2',''),$GLOBALS['egw_info']['flags']['java_script']);
			self::$response->script($GLOBALS['egw_info']['flags']['java_script']);
			//error_log($app .' added javascript to $GLOBALS[egw_info][flags][java_script] - use egw_json_response->script() instead.');
		}

		return $content;
	}

	/**
	 * Notify server that eT session/request is no longer needed, because user closed window
	 *
	 * @param string $_exec_id
	 */
	static public function ajax_destroy_session($_exec_id)
	{
		//error_log(__METHOD__."('$_exec_id')");
		if (($request = etemplate_request::read($_exec_id)))
		{
			$request->remove_if_not_modified();
			unset($request);
		}
	}

	/**
	 * Process via POST submitted content
	 */
	static public function process_exec()
	{
		if (get_magic_quotes_gpc()) $_POST['value'] = stripslashes($_POST['value']);
		$content = json_decode($_POST['value'],true);
		if($content == null && $_POST['exec'])
		{
			// Old etemplate submit
			error_log("Old etemplate submitted");
			return ExecMethod('etemplate.etemplate.process_exec');
		}
		error_log(__METHOD__."(".array2string($content).")");

		self::$request = etemplate_request::read($_POST['etemplate_exec_id']);

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
	 *
	 * @var string If the template needs a div named other than the template name, this is it
	 */
	protected $dom_id;

	/**
	 * Reads an eTemplate from filesystem or DB (not yet supported)
	 *
	 * @param string $name name of the eTemplate or array with the values for all keys
	 * @param string $template_set=null default try template-set from user and if not found "default"
	 * @param string $lang language, '' loads the pref. lang of the user, 'default' loads the default one '' in the db
	 * @param int $group id of the (primary) group of the user or 0 for none, not used at the moment !!!
	 * @param string $version version of the eTemplate
	 * @param mixed $load_via name/array of keys of etemplate to load in order to get $name (only as second try!)
	 * @return boolean True if a fitting template is found, else False
	 *
	 * @ToDo supported customized templates stored in DB
	 */
	public function read($name,$template_set=null,$lang='default',$group=0,$version='',$load_via='')
	{
		unset($lang); unset($group);	// not used, but in old signature
		$this->rel_path = self::relPath($this->name=$name, $this->template_set=$template_set,
			$this->version=$version, $this->laod_via = $load_via);
		//error_log(__METHOD__."('$name', '$template_set', '$lang', $group, '$version', '$load_via') rel_path=".array2string($this->rel_path));

		$this->dom_id = $name;

		return (boolean)$this->rel_path;
	}

	/**
	 * Set the DOM ID for the etemplate div.  If not set, it will be generated from the template name.
	 *
	 * @param string $new_id
	 */
	public function set_dom_id($new_id)
	{
		$this->dom_id = $new_id;
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
					isset($v[0]) && !is_array($v[0]) && isset($v[count($v)-1])	|| // or no associative array, eg. selecting multiple accounts
					is_array($v) && count($v) == 0 && is_array($old[$k])) // Empty array replacing non-empty
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
		static $dec_separator=null,$thousands_separator=null;
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

// Use hook to load custom widgets from other apps
$widgets = $GLOBALS['egw']->hooks->process('etemplate2_register_widgets');
foreach($widgets as $app => $list)
{
	foreach($list as $class)
	{
		try
		{
			class_exists($class);	// trigger autoloader
		}
		catch(Exception $e)
		{
			error_log($e->getMessage());
		}
	}
}
