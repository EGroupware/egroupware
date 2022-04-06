<?php
/**
 * EGroupware - eTemplate serverside
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

namespace EGroupware\Api;

/**
 * New eTemplate serverside contains:
 * - main server methods like read, exec
 * -
 *
 * Not longer available methods:
 * - set_(row|column)_attributes modifies template on run-time, was only used internally by etemplate itself
 * - disable_(row|column) dto.
 */
class Etemplate extends Etemplate\Widget\Template
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
		if(!isset(self::$request)) self::$request = Etemplate\Request::read();
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
		Framework::redirect_link(is_array($params) ? '/index.php' : $params,
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
	 *	 4 = json response
	 *	 5 = return Request object
	 * @param string $ignore_validation if not empty regular expression for validation-errors to ignore
	 * @param array $changes change made in the last call if looping, only used internaly by process_exec
	 * @return string|Etemplate\Request html for $output_mode == 1, Etemplate\Request for $output_mode == 5, else nothing
	 */
	function exec($method,array $content,array $sel_options=null,array $readonlys=null,array $preserv=null,$output_mode=0,$ignore_validation='',array $changes=null)
	{
		$hook_data = Hooks::process(
			array('hook_location'   => 'etemplate2_before_exec') +
			array('location_name'   => $this->name) +
			array('location_object' => &$this) +
			array('sel_options'     => $sel_options) +
			$content
		);

		foreach($hook_data as $extras)
		{
			if (!$extras) continue;

			foreach(count(array_filter(array_keys($extras), 'is_int')) ? $extras : array($extras) as $extra)
			{
				if (!empty($extra['data']) && is_array($extra['data']))
				{
					$content = self::complete_array_merge($content, $extra['data']);
				}

				if (!empty($extra['preserve']) && is_array($extra['preserve']))
				{
					$preserv = self::complete_array_merge($preserv, $extra['preserve']);
				}

				if (!empty($extra['readonlys']) && is_array($extra['readonlys']))
				{
					$readonlys = self::complete_array_merge($readonlys, $extra['readonlys']);
				}

				if (!empty($extra['sel_options']) && is_array($extra['sel_options']))
				{
					$sel_options = self::complete_array_merge($sel_options, $extra['sel_options']);
				}
			}
		}
		unset($hook_data);

		// Include the etemplate2 javascript code
		//Framework::includeJS('etemplate', 'etemplate2', 'api');

		if (!$this->rel_path) throw new Exception\AssertionFailed("No (valid) template '$this->name' found!");

		if ($output_mode == 4)
		{
			$output_mode = 0;
			self::$response = Json\Response::get();
		}
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

		if (empty($this->name)) throw new Exception\AssertionFailed("Template  name is not set '$this->name' !");
		// instanciate template to fill self::$request->sel_options for select-* widgets
		// not sure if we want to handle it this way, thought otherwise we will have a few ajax request for each dialog fetching predefined selectboxes
		$template = self::instance($this->name, $this->template_set, $this->version, $this->load_via);
		if (!$template) throw new Exception\AssertionFailed("Template $this->name not instanciable! Maybe you forgot to rename template id.");
		$this->children = array($template);
		$template->run('beforeSendToClient', array('', array('cont'=>$content)));

		if ($output_mode == 5)
		{
			$request = self::$request;
			self::$request = null;
			return $request;
		}

		// some apps (eg. InfoLog) set app_header only in get_rows depending on filter settings
		self::$request->app_header = $GLOBALS['egw_info']['flags']['app_header'] ?? null;

		// compile required translations translations
		$currentapp = $GLOBALS['egw_info']['flags']['currentapp'];
		$langRequire = array('common' => array(), 'etemplate' => array());	// keep that order
		foreach(Translation::$loaded_apps as $l_app => $lang)
		{
			if (!in_array($l_app, array($currentapp, 'custom')))
			{
				$langRequire[$l_app] = array('app' => $l_app, 'lang' => $lang, 'etag' => Translation::etag($l_app, $lang));
			}
		}
		foreach(array($currentapp, 'custom') as $l_app)
		{
			if (isset(Translation::$loaded_apps[$l_app]))
			{
				$langRequire[$l_app] = array('app' => $l_app, 'lang' => Translation::$loaded_apps[$l_app], 'etag' => Translation::etag($l_app, Translation::$loaded_apps[$l_app]));
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
			'menuaction' => $method.(strpos($method, '::') !== false ? '::' : '.').'et2_process',
		);

		if (!empty($data['content']['nm']['rows']) && is_array($data['content']['nm']['rows']))
		{
			// Deep copy rows so we don't lose them when request is set to null
			// (some content by reference)
			$data['content']['nm'] = self::deep_copy($data['content']['nm']);
		}

		// Info required to load the etemplate client-side
		$dom_id = str_replace('.','-',$this->dom_id);
		$load_array = array(
			'name' => $this->name,
			'url' => self::rel2url($this->rel_path),
			'data' => $data,
			'DOMNodeID' => $dom_id,
		);
		if (self::$response)	// call is within an ajax event / form submit
		{
			//error_log("Ajax " . __LINE__);
			self::$response->generic('et2_load', $load_array+Framework::get_extra());
			Framework::clear_extra();	// to not send/set it twice for multiple etemplates (eg. CRM view)
		}
		else	// first call
		{
			// check if application of template has a app.js file --> load it, preferring local min file if there
			list($app) = explode('.',$this->name);
			if (file_exists(EGW_SERVER_ROOT.($path = '/'.$app.'/js/app.min.js')) ||
				file_exists(EGW_SERVER_ROOT.($path = '/'.$app.'/js/app.js')))
			{
				Framework::includeJS($path);
			}
			// if app has no app.ts/js, we need to load etemplate2.js, otherwise popups wont work!
			else
			{
				Framework::includeJS('/api/js/etemplate/etemplate2.js');
			}
			// Category styles
			Categories::css($app);

			// set action attribute for autocomplete form tag
			// as firefox complains on about:balnk action, thus we have to literaly submit the form to a blank html
			$form_action = "about:blank";
			if (in_array(Header\UserAgent::type(), array('firefox', 'safari')))
			{
				$form_action = $GLOBALS['egw_info']['server']['webserver_url'].'/api/templates/default/empty.html';
			}
			// check if we are in an ajax-exec call from jdots template (or future other tabbed templates)
			if (isset($GLOBALS['egw']->framework->response))
			{
				$content = '<form target="egw_iframe_autocomplete_helper" action="'.$form_action.'" id="'.$dom_id.'" class="et2_container"></form>'."\n".
					'<iframe name="egw_iframe_autocomplete_helper" style="width:0;height:0;position: absolute;visibility:hidden;"></iframe>';
				$GLOBALS['egw']->framework->response->generic("data", array($content));
				$GLOBALS['egw']->framework->response->generic('et2_load',$load_array+Framework::get_extra());
				Framework::clear_extra();	// to not send/set it twice for multiple etemplates (eg. CRM view)

				// Really important to run this or weird things happen
				// See https://help.egroupware.org/t/nextmatch-wert-im-header-ausgeben/73412/11
				self::$request=null;
				return;
			}
			// let framework know, if we are a popup or not ('popup' not true, which is allways used by index.php!)
			if (!isset($GLOBALS['egw_info']['flags']['nonavbar']) || is_bool($GLOBALS['egw_info']['flags']['nonavbar']))
			{
				$GLOBALS['egw_info']['flags']['nonavbar'] = $output_mode == 2 ? 'popup' : false;
			}
			if ($output_mode != 1)
			{
				// Turn compression off so Ajax::headers() does not send a compression header
				// echo (used below) is not compatible with browser expecting compressed content
				ini_set('zlib.output_compression', 0);

				echo $GLOBALS['egw']->framework->header();
				if ($output_mode != 2 && !$GLOBALS['egw_info']['flags']['nonavbar'])
				{
					$GLOBALS['egw']->framework->navbar();
				}
				else	// mark popups as such, by enclosing everything in div#popupMainDiv
				{
					echo '<div id="popupMainDiv" class="popupMainDiv">'."\n";
				}
			}
			// Send any accumulated json responses - after flush to avoid sending the buffer as a response
			if(Json\Response::isJSONResponse())
			{
				$load_array['response'] = Json\Response::get()->returnResult();
			}
			// <iframe> and <form> tags added only to get browser autocomplete handling working again
			$form = '<form target="egw_iframe_autocomplete_helper" action="'.$form_action.'" id="'.$dom_id.'" class="et2_container" data-etemplate="'.
				htmlspecialchars(Json\Response::json_encode($load_array), ENT_COMPAT, Translation::charset(), true).'"></form>'."\n".
				'<iframe name="egw_iframe_autocomplete_helper" style="width:0;height:0;position: absolute;visibility:hidden;"></iframe>';

			// output mode 1 - return html
			if ($output_mode == 1)
			{
				ob_end_clean();
				self::$request = null;
				return $form;
			}
			echo $form;

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
	 * Fix all sel_options, as Etemplate\Widget\Select::beforeSendToClient is not run for auto-repeated stuff not understood by server
	 *
	 * @param array $sel_options
	 * @return array
	 */
	static protected function fix_sel_options(array $sel_options)
	{
		foreach($sel_options as &$options)
		{
			if (!is_array($options)||empty($options)) continue;
			foreach($options as $key => $value)
			{
				if (is_numeric($key) && (!is_array($value) || !isset($value['value'])))
				{
					Etemplate\Widget\Select::fix_encoded_options($options, true);
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
	 * @param array $_content
	 * @param boolean $no_validation
	 * @throws Exception\WrongParameter
	 */
	static public function ajax_process_content($etemplate_exec_id, array $_content, $no_validation)
	{
		//error_log(__METHOD__."(".array2string($etemplate_exec_id).', '.array2string($_content).")");

		self::$request = Etemplate\Request::read($etemplate_exec_id);
		//error_log('request='.array2string(self::$request));

		self::$response = Json\Response::get();

		if (!($template = self::instance(self::$request->template['name'], self::$request->template['template_set'],
			self::$request->template['version'], self::$request->template['load_via'])))
		{
			throw new Exception\WrongParameter('Can NOT read template '.array2string(self::$request->template));
		}

		// let session class know, which is the app & method of this request
		$GLOBALS['egw']->session->set_action('Etemplate: '.self::$request->method);

		// Set current app for validation
		list($app) = explode('.',self::$request->method);
		if(!$app) list($app) = explode('::',self::$request->method);
		if($app)
		{
			Translation::add_app($app);
			$GLOBALS['egw_info']['flags']['currentapp'] = $app;
		}
		$validated = array();
		$expand = array(
			'cont' => &self::$request->content,
		);
		$template->run('validate', array('', $expand, $_content, &$validated), true);	// $respect_disabled=true: do NOT validate disabled widgets and children

		if ($no_validation)
		{
			self::$validation_errors = array();
		}
		elseif (self::validation_errors(self::$request->ignore_validation))
		{
			//error_log(__METHOD__."(,".array2string($_content).') validation_errors='.array2string(self::$validation_errors));
			self::$response->generic('et2_validation_error', self::$validation_errors);
			return;
		}

		// tell request call to remove request, if it is not modified eg. by call to exec in callback
		self::$request->remove_if_not_modified();

		foreach(Hooks::process(array(
			'hook_location'   => 'etemplate2_before_process',
			'location_name'   => $template->id,
		) + self::complete_array_merge(self::$request->preserv, $validated)) as $extras)
		{
			if (!$extras) continue;

			foreach(isset($extras[0]) ? $extras : array($extras) as $extra)
			{
				if ($extra['data'] && is_array($extra['data']))
				{
					$validated = array_merge($validated, $extra['data']);
				}
			}
		}

		//error_log(__METHOD__."(,".array2string($content).')');
		//error_log(' validated='.array2string($validated));
		if(is_callable(self::$request->method))
		{
			call_user_func(self::$request->method,self::complete_array_merge(self::$request->preserv, $validated));
		}
		else
		{
			// Deprecated, but may still be needed
			$content = ExecMethod(self::$request->method, self::complete_array_merge(self::$request->preserv, $validated));
		}

		$tcontent = is_array($content) ? $content :
			self::complete_array_merge(self::$request->preserv ?? [], $validated);

		$hook_data = Hooks::process(
			array(
				'hook_location'   => 'etemplate2_after_process',
				'location_name'   => $template->id
			) + $tcontent);

		unset($tcontent);

		if (is_array($content))
		{
			foreach($hook_data as $extras)
			{
				if (!$extras) continue;

				foreach(isset($extras[0]) ? $extras : array($extras) as $extra) {
					if ($extra['data'] && is_array($extra['data'])) {
						$content = array_merge($content, $extra['data']);
					}
				}
			}
		}
		unset($hook_data);

		if (isset($GLOBALS['egw_info']['flags']['java_script']))
		{
			// Strip out any script tags
			$GLOBALS['egw_info']['flags']['java_script'] = preg_replace(array('/(<script[^>]*>)([^<]*)/is','/<\/script>/'),array('$2',''),$GLOBALS['egw_info']['flags']['java_script']);
			self::$response->script($GLOBALS['egw_info']['flags']['java_script']);
			//error_log($app .' added javascript to $GLOBALS[egw_info][flags][java_script] - use Json\Response->script() instead.');
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
		if (($request = Etemplate\Request::read($_exec_id, false)))
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
		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) $_POST['value'] = stripslashes($_POST['value']);
		$content = json_decode($_POST['value'],true);
		//error_log(__METHOD__."(".array2string($content).")");

		self::$request = Etemplate\Request::read($_POST['etemplate_exec_id']);

		if (!($template = self::instance(self::$request->template['name'], self::$request->template['template_set'],
			self::$request->template['version'], self::$request->template['load_via'])))
		{
			throw new Exception\WrongParameter('Can NOT read template '.array2string(self::$request->template));
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
		//error_log(__METHOD__."(,".array2string($content).')');
		//error_log(' validated='.array2string($validated));

		ExecMethod(self::$request->method, self::complete_array_merge(self::$request->preserv, $validated));

		// run egw destructor now explicit, in case a (notification) email is send via Egw::on_shutdown(),
		// as stream-wrappers used by Horde Smtp fail when PHP is already in destruction
		$GLOBALS['egw']->__destruct();
	}

	public $name;
	public $template_set;
	public $version;
	public $load_via;

	/**
	 *
	 * @var string If the template needs a div named other than the template name, this is it
	 */
	protected $dom_id;

	/**
	 * Reads an eTemplate from filesystem or DB (not yet supported)
	 *
	 * @param string $name name of the eTemplate or array with the values for all keys
	 * @param string $template_set =null default try template-set from user and if not found "default"
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

		// For mobile experience try to load custom mobile templates
		if (Header\UserAgent::mobile())
		{
			$template_set = "mobile";
		}

		unset($lang); unset($group);	// not used, but in old signature
		$this->rel_path = self::relPath($this->name=$name, $this->template_set=$template_set,
			$this->version=$version, $this->load_via = $load_via);
		//error_log(__METHOD__."('$name', '$template_set', '$lang', $group, '$version', '$load_via') rel_path=".array2string($this->rel_path));

		$this->dom_id = isset($_GET['fw_target']) ?	$name.'-'.$_GET['fw_target'] : $name;

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
	 * Make sure there's a new request, in case of multiple Etemplates in one call.
	 * Normally this isn't a problem, but if you've got an etemplate in the sidebox,
	 * and are seeing problems submitting another etemplate, try this before executing
	 * the sidebox etemplate.
	 */
	public static function reset_request()
	{
		self::$request = Etemplate\Request::read();
		self::$request->content = array();
		self::$request->modifications = array();
		self::$request->readonlys = array();
		self::$cache = array();
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
	 * @param string $name cell-name
	 * @param boolean $disabled =true disable or enable a cell, default true=disable
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
					is_array($v) && count($v) == 0)			// Empty array replacing non-empty
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
	 * Deep copy array to make sure there are no references
	 *
	 * @param Array $source
	 * @return Array
	 */
	public static function deep_copy($source)
	{
		$arr = array();

		foreach ($source as $key => $element)
		{
			if (is_array($element))
			{
				$arr[$key] = static::deep_copy($element);
			}
			else if (is_object($element))
			{
				// make an object copy
				$arr[$key] = clone $element;
			}
			else
			{
				$arr[$key] = $element;
			}
		}
		return $arr;
	}


	/**
	 * Debug callback just outputting content
	 *
	 * @param array $content =null
	 */
	public function debug(array $content=null)
	{
		$GLOBALS['egw']->framework->render(print_r($content, true));
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

		return lang('Maximum size for uploads').': '.Vfs::hsize($max_upload).
			" (php.ini: upload_max_filesize=$upload_max_filesize, post_max_size=$post_max_size)";
	}

	/**
	 * Format a number according to user prefs with decimal and thousands separator (later only for readonly)
	 *
	 * @param int|float|string $number
	 * @param int $num_decimal_places =2
	 * @param boolean $readonly =true
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
