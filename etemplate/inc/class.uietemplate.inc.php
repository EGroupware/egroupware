<?php
/**
* eGroupWare - EditableTemplates - HTML User Interface
*
* @link http://www.egroupware.org
* @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
* @author Ralf Becker <RalfBecker@outdoor-training.de>
* @copyright 2002-8 by RalfBecker@outdoor-training.de
* @package etemplate
* @subpackage api
* @version $Id$
*/

/**
* creates dialogs / HTML-forms from eTemplate descriptions
*
* Usage example:
*<code>
* $tmpl = new etemplate('app.template.name');
* $tmpl->exec('app.class.callback',$content_to_show);
*</code>
* This creates a form from the eTemplate 'app.template.name' and takes care that
* the method / public function 'callback' in class 'class' of 'app' gets called
* if the user submits the form. For the complete param's see the description of exec.
*
* etemplate or uietemplate extends boetemplate, all vars and public functions are inherited
*/
class etemplate extends boetemplate
{
	/**
	* integer debug-level or template-name or cell-type or '' = off
	* 1=calls to show and process_show, 2=content after process_show,
	* 3=calls to show_cell and process_show_cell
	* 
	* @var int/string
	*/
	var $debug;
	var $xslt = false;	/* do we run in the xslt framework (true) or the regular eGW one (false) */
	var $class_conf = array('nmh' => 'th','nmr0' => 'row_on','nmr1' => 'row_off');
	var $public_functions = array('process_exec' => True);
	/**
	* Inner width of browser window
	* 
	* @var int
	*/
	var $innerWidth;
	/**
	* Reference to the content-param of the last call to show, for extensions to use
	* 
	* @var array
	*/
	var $content;
	/**
	* Reference to the sel_options-param of the last call to show, for extensions to use
	* 
	* @var array
	*/
	var $sel_options;
	/**
	* Name of the currently processed etemplate, reference to $GLOBALS['egw_info']['etemplate']['name_form']
	* 
	* @var string
	*/
	var $name_form;
	/**
	* Used form-names in this request, reference to $GLOBALS['egw_info']['etemplate']['name_forms']
	* 
	* @var array
	*/
	var $name_forms;
	/**
	* Basename of the variables (content) in $_POST and id's, usually 'exec', 
	* if there's not more then one eTemplate on the page (then it will be exec, exec2, exec3, ...
	*
	* @var string
	*/
	var $name_vars='exec';
	/**
	* Are we running as sitemgr module or not
	*
	* @var boolean
	*/
	var $sitemgr=false;
	/**
	 * Javascript to be called, when a widget get's double-clicked (used only by the editor)
	 * A '%p' gets replace with the colon ':' separated template-name, -version and path of the clicked widget.
	 *
	 * @var string
	 */
	var $onclick_handler;

	/**
	* constructor of etemplate class, reads an eTemplate if $name is given
	*
	* @param string $name of etemplate or array with name and other keys
	* @param string/array $load_via with keys of other etemplate to load in order to get $name
	*/
	function etemplate($name='',$load_via='')
	{
		if (!is_object($GLOBALS['egw']->template))
		{
			$GLOBALS['egw']->template =& CreateObject('phpgwapi.Template');
		}
		$this->boetemplate($name,$load_via);

		$this->xslt = is_object($GLOBALS['egw']->xslttpl);
		
		$this->sitemgr = is_object($GLOBALS['Common_BO']);
		
		if (($this->innerWidth = (int) $_POST['innerWidth']))
		{
			$GLOBALS['egw']->session->appsession('innerWidth','etemplate',$this->innerWidth);
		}
		elseif (!($this->innerWidth = (int) $GLOBALS['egw']->session->appsession('innerWidth','etemplate')))
		{
			$this->innerWidth = 1018;	// default width for an assumed screen-resolution of 1024x768
		}
		//echo "<p>_POST[innerWidth]='$_POST[innerWidth]', innerWidth=$this->innerWidth</p>\n";
		$this->name_form =& $GLOBALS['egw_info']['etemplate']['name_form'];
		$this->name_forms =& $GLOBALS['egw_info']['etemplate']['name_forms'];
		if (!is_array($this->name_forms)) $this->name_forms = array();
	}

	/**
	* Abstracts a html-location-header call
	*
	* In other UI's than html this needs to call the methode, defined by menuaction or
	* open a browser-window for any other links.
	* 
	* @param string/array $params url or array with get-params incl. menuaction
	*/
	static function location($params='')
	{
		$GLOBALS['egw']->redirect_link(is_array($params) ? '/index.php' : $params,
			is_array($params) ? $params : '');
	}

	/**
	* Generats a Dialog from an eTemplate - abstract the UI-layer
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
	* @param int $output_mode 0 = echo incl. navbar, 1 = return html, 2 = echo without navbar (eg. for popups)
	*	-1 = first time return html, after use 0 (echo html incl. navbar), eg. for home
	* @param string $ignore_validation if not empty regular expression for validation-errors to ignore
	* @param array $changes change made in the last call if looping, only used internaly by process_exec
	* @return string html for $output_mode == 1, else nothing
	*/
	function exec($method,$content,$sel_options='',$readonlys='',$preserv='',$output_mode=0,$ignore_validation='',$changes='')
	{
		//echo "<br>globals[java_script] = '".$GLOBALS['egw_info']['etemplate']['java_script']."', this->java_script() = '".$this->java_script()."'\n";
		if (!$sel_options)
		{
			$sel_options = array();
		}
		if (!$readonlys)
		{
			$readonlys = array();
		}
		if (!$preserv)
		{
			$preserv = array();
		}
		if (!$changes)
		{
			$changes = array();
		}
		if (isset($content['app_header']))
		{
			$GLOBALS['egw_info']['flags']['app_header'] = $content['app_header'];
		}
		if ($GLOBALS['egw_info']['flags']['currentapp'] != 'etemplate')
		{
			$GLOBALS['egw']->translation->add_app('etemplate');	// some extensions have own texts
		}
		$id = $this->appsession_id();
		//echo "<p>unsetting existing egw_info[etemplate] which had keys=".implode(',',array_keys($GLOBALS['egw_info']['etemplate']))."</p>\n";
		// initialise $GLOBALS['egw_info']['etemplate'], in case there are multiple eTemplates on a page
		$GLOBALS['egw_info']['etemplate'] = array(
			'name_forms' => $GLOBALS['egw_info']['etemplate']['name_forms'],
			'validation_errors' => $GLOBALS['egw_info']['etemplate']['validation_errors'],
			'hooked' => $GLOBALS['egw_info']['etemplate']['hooked'],
			'content' => $GLOBALS['egw_info']['etemplate']['content'],
			'hook_content' => $GLOBALS['egw_info']['etemplate']['hook_content'],
			'hook_app' => $GLOBALS['egw_info']['etemplate']['hook_app'],
			'extension_data' => $GLOBALS['egw_info']['etemplate']['extension_data'],
		);
		//echo "<p>hooked=".(int)!!$GLOBALS['egw_info']['etemplate']['hooked'].", content=".(int)!!$GLOBALS['egw_info']['etemplate']['content'].", hook_content=".(int)!!$GLOBALS['egw_info']['etemplate']['hook_content'].", hook_app={$GLOBALS['egw_info']['etemplate']['hook_app']}</p>\n";
		$this->name_form =& $GLOBALS['egw_info']['etemplate']['name_form'];
		$this->name_forms =& $GLOBALS['egw_info']['etemplate']['name_forms'];
		if (!is_array($this->name_forms)) $this->name_forms = array();

		// use different form-names to allows multiple eTemplates in one page, eg. addressbook-view
		$this->name_form = 'eTemplate';
		if (in_array($this->name_form,$this->name_forms))
		{
			$this->name_form .= 1+count($this->name_forms);
			$this->name_vars .= 1+count($this->name_forms);
		}
		$this->name_forms[] = $this->name_form;
		
		$GLOBALS['egw_info']['etemplate']['output_mode'] = $output_mode;	// let extensions "know" they are run eg. in a popup
		$GLOBALS['egw_info']['etemplate']['form_options'] = '';	// might be set in show
		$GLOBALS['egw_info']['etemplate']['to_process'] = array();
		
		$html = html::form($this->include_java_script(1).
				html::input_hidden(array(
					'submit_button' => '',
					'innerWidth'    => '',
				),'',False).
				$this->show($this->complete_array_merge($content,$changes),$sel_options,$readonlys,$this->name_vars),array(
					'etemplate_exec_id' => $id
				),$this->sitemgr ? '' : '/etemplate/process_exec.php?menuaction='.$method,
				'',$this->name_form,$GLOBALS['egw_info']['etemplate']['form_options'].
				// dont set the width of popups!
				($output_mode != 0 ? '' : ' onsubmit="this.innerWidth.value=window.innerWidth ? window.innerWidth : document.body.clientWidth;"'));
				//echo "to_process="; _debug_array($GLOBALS['egw_info']['etemplate']['to_process']); 
		
		if ($this->sitemgr)
		{
			$GLOBALS['egw_info']['flags']['java_script'] .= $this->include_java_script(2);
		}
		elseif (!$this->xslt)
		{
			$hooked = isset($GLOBALS['egw_info']['etemplate']['content']) ? $GLOBALS['egw_info']['etemplate']['content'] :
				$GLOBALS['egw']->template->get_var('phpgw_body');

			if (!@$GLOBALS['egw_info']['etemplate']['hooked'] && (int) $output_mode != 1 && (int) $output_mode != -1)	// not just returning the html
			{
				$GLOBALS['egw_info']['flags']['java_script'] .= $this->include_java_script(2);

				if ($GLOBALS['egw_info']['flags']['currentapp'] != 'etemplate')
				{
					$css_file = '/etemplate/templates/'.$GLOBALS['egw_info']['server']['template_set'].'/app.css';
					if (!file_exists(EGW_SERVER_ROOT.$css_file))
					{
						$css_file = '/etemplate/templates/default/app.css';
					}
					$GLOBALS['egw_info']['flags']['css'] .= "\n\t\t</style>\n\t\t".'<link href="'.$GLOBALS['egw_info']['server']['webserver_url'].
						$css_file.'?'.filemtime(EGW_SERVER_ROOT.$css_file).'" type="text/css" rel="StyleSheet" />'."\n\t\t<style>\n\t\t\t";
				}

				$GLOBALS['egw']->common->egw_header();
			}
			elseif (!isset($GLOBALS['egw_info']['etemplate']['content']))
			{
				$html = $this->include_java_script(2).$html;	// better than nothing
			}
			// saving the etemplate content for other hooked etemplate apps (atm. infolog hooked into addressbook)
			$GLOBALS['egw_info']['etemplate']['content'] =& $html;
		}
		else
		{
			$hooked = $GLOBALS['egw']->xslttpl->get_var('phpgw');
			$hooked = $hooked['body_data'];
			$GLOBALS['egw']->xslttpl->set_var('phpgw',array('java_script' => $GLOBALS['egw_info']['flags']['java_script'].$this->include_java_script(2)));
		}
		//echo "<p>uietemplate::exec($method,...) after show: sitemgr=$this->sitemgr, xslt=$this->xslt, hooked=$hooked, output_mode=$output_mode</p>\n";

		if (!$this->sitemgr && (int) $output_mode != 1 && (int) $output_mode != -1)	// NOT returning html
		{
			if (!$this->xslt)
			{
				if (!@$GLOBALS['egw_info']['etemplate']['hooked'])
				{
					if((int) $output_mode != 2)
					{
						echo parse_navbar();
					}
					else
					{
						echo '<div id="popupMainDiv">'."\n";
						if ($GLOBALS['egw_info']['user']['apps']['manual'])	// adding a manual icon to every popup
						{
							$manual =& new etemplate('etemplate.popup.manual');
							echo $manual->show(array());
							unset($manual);
							echo '<style type="text/css">.ajax-loader { position: absolute; right: 27px; top: 24px; display: none; }</style>'."\n";
							echo '<div class="ajax-loader">'.html::image('phpgwapi','ajax-loader') . '</div>';
						}
					}
				}
				echo $GLOBALS['egw_info']['etemplate']['hook_content'].$html;

				if (!$GLOBALS['egw_info']['etemplate']['hooked'] &&
						(!isset($_GET['menuaction']) || 
								strpos($_SERVER['PHP_SELF'],'process_exec.php')!==false))
				{
					if((int) $output_mode == 2)
					{
						echo "</div>\n";
					}
					$GLOBALS['egw']->common->egw_footer();
				}
			}
			else
			{
				// need to add some logic here to support popups (output_mode==2) for xslt, but who cares ...
				$GLOBALS['egw']->xslttpl->set_var('phpgw',array('body_data' => $html));
			}
		}
		$this->save_appsession($sess = $this->as_array(2) + array(
			'readonlys' => $readonlys,
			'content' => $content,
			'changes' => $changes,
			'sel_options' => $sel_options,
			'preserv' => $preserv,
			'extension_data' => $GLOBALS['egw_info']['etemplate']['extension_data'],
			'to_process' => $GLOBALS['egw_info']['etemplate']['to_process'],
			'java_script' => $GLOBALS['egw_info']['etemplate']['java_script'],
			'java_script_from_flags' => $GLOBALS['egw_info']['flags']['java_script'],
			'java_script_body_tags' => $GLOBALS['egw']->js->body,
			'java_script_files' => $GLOBALS['egw']->js->files,
			'include_xajax' => $GLOBALS['egw_info']['flags']['include_xajax'],
			'dom_enabled' => $GLOBALS['egw_info']['etemplate']['dom_enabled'],
			'hooked' => $hooked ? $hooked : $GLOBALS['egw_info']['etemplate']['hook_content'],
			'hook_app' => $hooked ? $GLOBALS['egw_info']['flags']['currentapp'] : $GLOBALS['egw_info']['etemplate']['hook_app'],
			'app_header' => $GLOBALS['egw_info']['flags']['app_header'],
			'output_mode' => $output_mode != -1 ? $output_mode : 0,
			'session_used' => 0,
			'ignore_validation' => $ignore_validation,
			'method' => $method,
			'name_vars' => $this->name_vars,
		),$id);
		//echo "<p>hooked=".(int)!!$hooked.", content=".(int)!!$GLOBALS['egw_info']['etemplate']['content'].", hook_content=".(int)!!$GLOBALS['egw_info']['etemplate']['hook_content'].", hook_app={$GLOBALS['egw_info']['etemplate']['hook_app']}</p>\n";
		//echo "<p>session: "; foreach($sess as $key => $val) echo "$key=$val, "; echo "</p>\n";
		/*
		echo "<p><b>total size session data = ".($total=strlen(serialize($sess)))."</b></p>\n";
		echo "<p>shares bigger then 1.0% percent of it:</p>\n";
		foreach($sess as $key => $val)
		{
			$len = strlen(is_array($val) ? serialize($val) : $val);
			$len .= ' ('.sprintf('%2.1lf',($percent = 100.0 * $len / $total)).'%)';
			if ($percent < 1.0) continue;
			echo "<p><b>$key</b>: strlen(\$val)=$len</p>\n";
			if (is_array($val) && $len > 2000)
			{
				foreach($val as $k => $v)
				{
					$l = strlen(is_array($v) ? serialize($v) : $v);
					$l .= ' ('.sprintf('%2.1lf',($p = 100.0 * $l / $total)).'%)';
					if ($p < 1.0) continue;
					echo "<p>&nbsp;- {$key}[$k]: strlen(\$v)=$l</p>\n";
				}
			}
		}
		*/
		if ($this->sitemgr || (int) $output_mode == 1 || (int) $output_mode == -1)	// return html
		{
			return $html;
		}
	}

	/**
	* Check if we have not ignored validation errors
	*
	* @param string $ignore_validation='' if not empty regular expression for validation-errors to ignore
	* @param string $cname=null name-prefix, which need to be ignored, default $this->name_vars
	* @return boolean true if there are not ignored validation errors, false otherwise
	*/
	function validation_errors($ignore_validation='',$cname=null)
	{
		if (is_null($cname)) $cname = $this->name_vars;
		//echo "<p>uietemplate::validation_errors('$ignore_validation','$cname') validation_error="; _debug_array($GLOBALS['egw_info']['etemplate']['validation_errors']);
		if (!$ignore_validation) return count($GLOBALS['egw_info']['etemplate']['validation_errors']) > 0;

		foreach($GLOBALS['egw_info']['etemplate']['validation_errors'] as $name => $error)
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
	* Makes the necessary adjustments to _POST before it calls the app's method
	*
	* This function is only to submit forms to, create with exec.
	* All eTemplates / forms executed with exec are submited to this function
	* via /etemplate/process_exec.php?menuaction=<callback>. We cant use the global index.php as
	* it would set some constants to etemplate instead of the calling app. 
	* process_exec then calls process_show for the eTemplate (to adjust the content of the _POST) and
	* ExecMethod's the given callback from the app with the content of the form as first argument.
	*
	* @return mixed false if no sessiondata and $this->sitemgr, else the returnvalue of exec of the method-calls
	*/
	function process_exec($etemplate_exec_id = null, $submit_button = null, $exec = null, $type = 'regular' )
	{
		if(!$etemplate_exec_id) $etemplate_exec_id = $_POST['etemplate_exec_id'];
		if(!$submit_button) $submit_button = $_POST['submit_button'];
		if(!$exec) $exec = $_POST;

		//echo "process_exec: _POST ="; _debug_array($_POST);
		$session_data = $this->get_appsession($etemplate_exec_id);
		//echo "<p>process_exec: session_data ="; _debug_array($session_data);

		if (!$etemplate_exec_id || !is_array($session_data) || count($session_data) < 10)
		{
			if ($this->sitemgr) return false;
			//echo "uitemplate::process_exec() id='$_POST[etemplate_exec_id]' invalid session-data !!!"; _debug_array($_SESSION);
			// this prevents an empty screen, if the sessiondata gets lost somehow
			$this->location(array('menuaction' => $_GET['menuaction'],'post_empty' => (int)!$_POST));
		}
		$this->name_vars = $session_data['name_vars'];
		if (isset($submit_button) && !empty($submit_button))
		{
			$this->set_array($exec,$submit_button,'pressed');
		}
		$content = $exec[$this->name_vars];
		if (!is_array($content))
		{
			$content = array();
		}
		$this->init($session_data);
		$GLOBALS['egw_info']['etemplate']['extension_data'] = $session_data['extension_data'];
		$GLOBALS['egw_info']['etemplate']['java_script'] = $session_data['java_script'] || $_POST['java_script'];
		$GLOBALS['egw_info']['etemplate']['dom_enabled'] = $session_data['dom_enabled'] || $_POST['dom_enabled'];
		//echo "globals[java_script] = '".$GLOBALS['egw_info']['etemplate']['java_script']."', session_data[java_script] = '".$session_data['java_script']."', _POST[java_script] = '".$_POST['java_script']."'\n";
		//echo "process_exec($this->name) content ="; _debug_array($content);
		if ($GLOBALS['egw_info']['flags']['currentapp'] != 'etemplate')
		{
			$GLOBALS['egw']->translation->add_app('etemplate');	// some extensions have own texts
		}
		$this->process_show($content,$session_data['to_process'],$this->name_vars,$type);

		$GLOBALS['egw_info']['etemplate']['loop'] |= !$this->canceled && $this->button_pressed &&
			$this->validation_errors($session_data['ignore_validation']);	// set by process_show

		// If a tab has an error on it, change to that tab
		foreach($GLOBALS['egw_info']['etemplate']['validation_errors'] as $form_name => $msg) 
		{
			$name = $this->template_name($form_name);
			if (!$this->get_widget_by_name($name))
			{
				foreach($this->get_widgets_by_type('tab') as $widget)
				{
					foreach(explode('|',$widget['name']) as $tab)
					{
						if (strpos('.',$tab) === false) $tab = $this->name.'.'.$tab;
						$tab_tpl = new etemplate($tab);
						if ($tab_tpl->get_widget_by_name($name))
						{
							$content[$widget['name']] = $tab;
							break 3;
						}
					}
				}
			}
		}

		//echo "process_exec($this->name) process_show(content) ="; _debug_array($content);
		//echo "process_exec($this->name) session_data[changes] ="; _debug_array($session_data['changes']);
		$content = $this->complete_array_merge($session_data['changes'],$content);
		//echo "process_exec($this->name) merge(changes,content) ="; _debug_array($content);

		if ($GLOBALS['egw_info']['etemplate']['loop'])
		{
			if ($session_data['hooked'] != '')	// set previous phpgw_body if we are called as hook
			{
				if (!$this->xslt)
				{
					$GLOBALS['egw_info']['etemplate']['hook_content'] = $session_data['hooked'];
					$GLOBALS['egw_info']['flags']['currentapp'] = $GLOBALS['egw_info']['etemplate']['hook_app'] = $session_data['hook_app'];
				}
				else
				{
					$GLOBALS['egw']->xslttpl->set_var('phpgw',array('body_data' => $session_data['hooked']));
				}
			}
			if($session_data['include_xajax']) $GLOBALS['egw_info']['flags']['include_xajax'] = true;

			if (!empty($session_data['app_header']))
			{
				$GLOBALS['egw_info']['flags']['app_header'] = $session_data['app_header'];
			}
			
			$GLOBALS['egw_info']['flags']['java_script'] .= $session_data['java_script_from_flags'];
			if (!empty($session_data['java_script_body_tags']))
			{
				if( !is_object($GLOBALS['egw']->js))
				{
					$GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
				}
				foreach ($session_data['java_script_body_tags'] as $tag => $code)
				{
					//error_log($GLOBALS['egw']->js->body[$tag]);
					$GLOBALS['egw']->js->body[$tag] .= $code;
				}
			}
			if (is_array($session_data['java_script_files']))
			{
				if( !is_object($GLOBALS['egw']->js))
				{
					$GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
				}
				$GLOBALS['egw']->js->files = !is_array($GLOBALS['egw']->js->files) ? $session_data['java_script_files'] :
					$this->complete_array_merge($GLOBALS['egw']->js->files,$session_data['java_script_files']);
			}
			
			//echo "<p>process_exec($this->name): <font color=red>loop is set</font>, content=</p>\n"; _debug_array($content);
			return $this->exec($session_data['method'],$session_data['content'],$session_data['sel_options'],
				$session_data['readonlys'],$session_data['preserv'],$session_data['output_mode'],
				$session_data['ignore_validation'],$content);
		}
		else
		{
			//echo "<p>process_exec($this->name): calling $session_data[method]</p>\n";
			return ExecMethod($session_data['method'],$this->complete_array_merge($session_data['preserv'],$content));
		}
	}

	/**
	* process the values transfered with the javascript function values2url
	*
	* The returned array contains the preserved values overwritten (only!) with the variables named in values2url
	*
	* @return array/boolean content array or false on error
	*/
	function process_values2url()
	{
		//echo "process_exec: _GET ="; _debug_array($_GET);
		$session_data = $this->get_appsession($_GET['etemplate_exec_id']);
		//echo "<p>process_exec: session_data ="; _debug_array($session_data);

		if (!$_GET['etemplate_exec_id'] || !is_array($session_data) || count($session_data) < 10)
		{
			return false;
		}
		$this->name_vars = $session_data['name_vars'];
		$GLOBALS['egw_info']['etemplate']['extension_data'] = $session_data['extension_data'];

		$content = $_GET[$this->name_vars];
		if (!is_array($content))
		{
			$content = array();
		}
		$this->process_show($content,$session_data['to_process'],$this->name_vars);

		return $this->complete_array_merge($session_data['preserv'],$content);
	}

	/**
	* creates HTML from an eTemplate
	*
	* This is done by calling show_cell for each cell in the form. show_cell itself
	* calls show recursivly for each included eTemplate.
	* You could use it in the UI-layer of an app, just make shure to call process_show !!!
	* This is intended as internal function and should NOT be called by new app's direct,
	* as it deals with HTML and is so UI-dependent, use exec instead.
	*
	* @internal
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
		if (!$sel_options)
		{
			$sel_options = array();
		}
		// make it globaly availible for show_cell and show_grid, or extensions
		$this->sel_options =& $sel_options;

		if (!$readonlys)
		{
			$readonlys = array();
		}
		if (++$this->already_showed > 1) return '';	// prefens infinit self-inclusion

		if (is_int($this->debug) && $this->debug >= 1 || $this->name && $this->debug == $this->name)
		{
			echo "<p>etemplate.show($this->name): $cname =\n"; _debug_array($content);
			echo "readonlys="; _debug_array($readonlys);
		}
		if (!is_array($content))
		{
			$content = array();	// happens if incl. template has no content
		}
		// make the content availible as class-var for extensions
		$this->content =& $content;
		
		$html = "\n\n<!-- BEGIN eTemplate $this->name -->\n<div id=\"$this->name\">\n\n";
		if (!$GLOBALS['egw_info']['etemplate']['styles_included'][$this->name])
		{
			$GLOBALS['egw_info']['etemplate']['styles_included'][$this->name] = True;
			$html .= html::style($this->style)."\n\n";
		}
		$path = '/';
		foreach ($this->children as $n => $child)
		{
			$h = $this->show_cell($child,$content,$readonlys,$cname,$show_c,$show_row,$nul,$class,$path.$n);
			$html .= $class || $child['align'] ? html::div($h,html::formatOptions(array(
				$class,
				$child['align'],
			),'class,align')) : $h;
		}
		return $html."\n</div>\n<!-- END eTemplate $this->name -->\n\n";
	}
	
	/**
	* Get the color of a category
	* 
	* For multiple cats, the first with a color is used
	*
	* @param int/string $cats multiple comma-separated cat_id's
	* @return string
	*/
	static function cats2color($cats)
	{
		static $cat2color;
		
		if (!$cats) return null;
		
		if (isset($cat2color[$cats]))
		{
			return $cat2color[$cats];
		}
		
		foreach(explode(',',$cats) as $cat)
		{
			if (isset($cat2color[$cat]))
			{
				return $cat2color[$cat];
			}
			if (!is_object($GLOBALS['egw']->categories))
			{
				$GLOBALS['egw']->categories = new categories();
			}
			$data = unserialize($GLOBALS['egw']->categories->id2name($cat,'data'));
			
			if (($color = $data['color']))
			{
				//echo "<p>cats2color($cats)=$color</p>\n";
				return $cat2color[$cats] = $cat2color[$cat] = $color;
			}
		}
		return null;
	}
		
	/**
	* creates HTML from an eTemplate
	*
	* This is done by calling show_cell for each cell in the form. show_cell itself
	* calls show recursivly for each included eTemplate.
	* You can use it in the UI-layer of an app, just make shure to call process_show !!!
	* This is intended as internal function and should NOT be called by new app's direct,
	* as it deals with HTML and is so UI-dependent, use exec instead.
	*
	* @internal
	* @param array $grid representing a grid
	* @param array $content with content for the cells, keys are the names given in the cells/form elements
	* @param array $readonlys with names of cells/form-elements to be not allowed to change
	* 		This is to facilitate complex ACL's which denies access on field-level !!!
	* @param string $cname basename of names for form-elements, means index in $_POST
	*		eg. $cname='cont', element-name = 'name' returned content in $_POST['cont']['name']
	* @param string $show_c name/index for name expansion
	* @param string $show_row name/index for name expansion
	* @param string $path path in the widget tree
	* @return string the generated HTML
	*/
	private function show_grid(&$grid,$content,$readonlys='',$cname='',$show_c=0,$show_row=0,$path='')
	{
		if (!$readonlys)
		{
			$readonlys = array();
		}
		if (is_int($this->debug) && $this->debug >= 2 || $grid['name'] && $this->debug == $grid['name'] ||
			$this->name && $this->debug == $this->name)
		{
			echo "<p>etemplate.show_grid($grid[name]): $cname =\n"; _debug_array($content);
		}
		if (!is_array($content))
		{
			$content = array();	// happens if incl. template has no content
		}
		$content += array(	// for var-expansion in names in show_cell
			'.c' => $show_c,
			'.col' => $this->num2chrs($show_c-1),
			'.row' => $show_row
		);
		$rows = array();

		$data = &$grid['data'];
		reset($data);
		if (isset($data[0]))
		{
			list(,$opts) = each($data);
		}
		else
		{
			$opts = array();
		}
		$max_cols = $grid['cols'];
		for ($r = 0; $row = 1+$r /*list($row,$cols) = each($data)*/; ++$r)
		{
			if (!(list($r_key) = each($data)))	// no further row
			{
				if (!(($this->autorepeat_idx($cols['A'],0,$r,$idx,$idx_cname,false,$content) && $idx_cname) ||
						(substr($cols['A']['type'],1) == 'box' && $this->autorepeat_idx($cols['A'][1],0,$r,$idx,$idx_cname,false,$content) && $idx_cname) ||
					($this->autorepeat_idx($cols['B'],1,$r,$idx,$idx_cname,false,$content) && $idx_cname)) ||
					!$this->isset_array($content,$idx_cname))
				{
					break;                     	// no auto-row-repeat
				}
			}
			else
			{
				$cols = &$data[$r_key];
				list($height,$disabled) = explode(',',$opts["h$row"]);
				$class = /*TEST-RB$no_table_tr ? $tr_class :*/ $opts["c$row"];
			}
			if ($disabled != '' && $this->check_disabled($disabled,$content))
			{
				continue;	// row is disabled
			}
			$rows[".$row"] .= html::formatOptions($height,'height');
			list($cl) = explode(',',$class);
			if ($cl == '@' || $cl && strpos($cl,'$') !== false)
			{
				$cl = $this->expand_name($cl,0,$r,$content['.c'],$content['.row'],$content);
				
				if (!$cl || preg_match('/^[0-9,]*$/',$cl))
				{
					if (($color = $this->cats2color($cl)))
					{
						$rows[".$row"] .= ' style="background-color: '.$color.';"';
					}
					$cl = 'row';
				}
			}
			if ($cl == 'nmr' || substr($cl,0,3) == 'row')	// allow to have further classes behind row
			{
				$cl = 'row_'.($nmr_alternate++ & 1 ? 'off' : 'on').substr($cl,3); // alternate color
			}
			$cl = isset($this->class_conf[$cl]) ? $this->class_conf[$cl] : $cl;
			$rows[".$row"] .= html::formatOptions($cl,'class');
			$rows[".$row"] .= html::formatOptions($class,',valign');
			reset ($cols);
			$row_data = array();
			for ($c = 0; True /*list($col,$cell) = each($cols)*/; ++$c)
			{
				$col = $this->num2chrs($c);
				if (!(list($c_key) = each($cols)))		// no further cols
				{
					// only check if the max. column-number reached so far is exeeded
					// otherwise the rows have a differen number of cells and it saved a lot checks
					if ($c >= $max_cols)
					{
						if (!$this->autorepeat_idx($cell,$c,$r,$idx,$idx_cname,True,$content) ||
							!$this->isset_array($content,$idx))
						{
							break;	// no auto-col-repeat
						}
						$max_cols = $c+1;
					}
				}
				else
				{
					$cell = $cols[$c_key];
					list($col_width,$col_disabled) = explode(',',$opts[$col]);

					if (!$cell['height'])	// if not set, cell-height = height of row
					{
						$cell['height'] = $height;
					}
					if (!$cell['width'])	// if not set, cell-width = width of column or table
					{
						list($col_span) = explode(',',$cell['span']);
						if ($col_span == 'all' && !$c)
						{
							list($cell['width']) = explode(',',$this->size);
						}
						else
						{
							$cell['width'] = $col_width;
						}
					}
				}
				/*TEST-RB
				if ($cell['type'] == 'template' && $cell['onchange'])
				{
					$cell['tr_class'] = $cl;
				}*/
				if ($col_disabled != '' && $this->check_disabled($col_disabled,$content))
				{
					continue;	// col is disabled
				}
				$row_data[$col] = $this->show_cell($cell,$content,$readonlys,$cname,$c,$r,$span,$cl,$path.'/'.$r_key.$c_key);

				if ($row_data[$col] == '' && $this->rows == 1)
				{
					unset($row_data[$col]);	// omit empty/disabled cells if only one row
					continue;
				}
				if (strlen($cell['onclick']) > 1)
				{
					$onclick = $cell['onclick'];
					if (strpos($onclick,'$') !== false || $onclick{0} == '@')
					{
						$onclick = $this->expand_name($onclick,$c,$r,$content['.c'],$content['.row'],$content);
					}
					$row_data[".$col"] .= ' onclick="'.$this->js_pseudo_funcs($onclick,$cname).'"' .
						($cell['id'] ? ' id="'.$cell['id'].'"' : '');
				}
				$colspan = $span == 'all' ? $this->cols-$c : 0+$span;
				if ($colspan > 1)
				{
					$row_data[".$col"] .= " colspan=\"$colspan\"";
					for ($i = 1; $i < $colspan; ++$i,++$c)
					{
						each($cols);	// skip next cell(s)
					}
				}
				else
				{
					list($width,$disable) = explode(',',$opts[$col]);
					if ($width)		// width only once for a non colspan cell
					{
						$row_data[".$col"] .= " width=\"$width\"";
						$opts[$col] = "0,$disable";
					}
				}
				$row_data[".$col"] .= html::formatOptions($cell['align']?$cell['align']:'left','align');
				// allow to set further attributes in the tablecell, beside the class
				if (is_array($cl))
				{
					foreach($cl as $attr => $val)
					{
						if ($attr != 'class' && $val)
						{
							$row_data['.'.$col] .= ' '.$attr.'="'.$val.'"';
						}
					}
					$cl = $cl['class'];
				}										
				$cl = $this->expand_name(isset($this->class_conf[$cl]) ? $this->class_conf[$cl] : $cl,
					$c,$r,$show_c,$show_row,$content);
				// else the class is set twice, in the table and the table-cell, which is not good for borders
				if ($cl && $cell['type'] != 'template' && $cell['type'] != 'grid')
				{
					$row_data[".$col"] .= html::formatOptions($cl,'class');
				}
			}
			$rows[$row] = $row_data;
		}
		if (!$rows) return '';

		list($width,$height,,,,,$overflow) = $options = explode(',',$grid['size']);
		if ($overflow && $height)
		{
			$options[1] = '';	// set height in div only
		}
		$html = html::table($rows,html::formatOptions($options,'width,height,border,class,cellspacing,cellpadding').
			html::formatOptions($grid['span'],',class').
			html::formatOptions($grid['name']?$this->form_name($cname,$grid['name']):'','id'));

		if (!empty($overflow)) {
			if (is_numeric($height)) $height .= 'px';
			if (is_numeric($width)) $width .= 'px';
			$div_style=' style="'.($width?"width: $width; ":'').($height ? "height: $height; ":'')."overflow: $overflow;\"";
			$html = html::div($html,$div_style);
		}
		return "\n\n<!-- BEGIN grid $grid[name] -->\n$html<!-- END grid $grid[name] -->\n\n";
	}
	
	/**
	* build the name of a form-element from a basename and name
	*
	* name and basename can contain sub-indices in square bracets, eg. basename="base[basesub1][basesub2]" 
	* and name = "name[sub]" gives "base[basesub1][basesub2][name][sub]"
	*
	* @param string $cname basename
	* @param string $name name
	* @return string complete form-name
	*/
	static function form_name($cname,$name)
	{
		if(is_object($name)) return '';

		$name_parts = explode('[',str_replace(']','',$name));
		if (!empty($cname))
		{
			array_unshift($name_parts,$cname);
		}
		$form_name = array_shift($name_parts);
		if (count($name_parts))
		{
			$form_name .= '['.implode('][',$name_parts).']';
		}
		return $form_name;
	}

	/**
	* strip the prefix of a form-element from a form_name
	* This function removes the prefix of form_name().  It takes a name like base[basesub1][basesub2][name][sub]
	* and gives basesub1[basesub2][name][sub]
	*
	* @param string form_name 
	* @return string name without prefix
	*/
	static private function template_name($form_name) 
	{
		$parts = explode('[',str_replace(']','',$form_name));

		array_shift($parts);	// remove exec

		$name = array_shift($parts);

		if ($parts) $name .= '['.implode('][',$parts).']';

		return $name;
	}

	/**
	* generates HTML for one widget (input-field / cell)
	*
	* calls show to generate included eTemplates. Again only an INTERMAL function.
	*
	* @internal
	* @param array $cell with data of the cell: name, type, ...
	* @param array $content with content for the cells, keys are the names given in the cells/form elements
	* @param array $readonlys with names of cells/form-elements to be not allowed to change
	* 		This is to facilitate complex ACL's which denies access on field-level !!!
	* @param string $cname basename of names for form-elements, means index in $_POST
	*		eg. $cname='cont', element-name = 'name' returned content in $_POST['cont']['name']
	* @param string $show_c name/index for name expansion
	* @param string $show_row name/index for name expansion
	* @param string &$span on return number of cells to span or 'all' for the rest (only used for grids)
	* @param string &$class on return the css class of the cell, to be set in the <td> tag
	* @param string $path path in the widget tree
	* @return string the generated HTML
	*/
	private function show_cell(&$cell,$content,$readonlys,$cname,$show_c,$show_row,&$span,&$class,$path='')
	{
		if ($this->debug && (is_int($this->debug) && $this->debug >= 3 || $this->debug == $cell['type']))
		{
			echo "<p>etemplate.show_cell($this->name,name='${cell['name']}',type='${cell['type']}',cname='$cname',...,'$path')</p>\n";
		}
		list($span) = explode(',',$cell['span']);	// evtl. overriten later for type template

		if ($cell['name']{0} == '@' && $cell['type'] != 'template')
		{
			$cell['name'] = $this->get_array($content,$this->expand_name(substr($cell['name'],1),
				$show_c,$show_row,$content['.c'],$content['.row'],$content));
		}
		$name = $this->expand_name($cell['name'],$show_c,$show_row,$content['.c'],$content['.row'],$content);

		$form_name = $this->form_name($cname,$name);

		$value = $this->get_array($content,$name);

		$options = '';
		if ($readonly = $cell['readonly'] || @$readonlys[$name] && !is_array($readonlys[$name]) || $readonlys['__ALL__'])
		{
			$options .= ' readonly="readonly"';
		}
		if ((int) $cell['tabindex']) $options .= ' tabindex="'.(int)$cell['tabindex'].'"';
		if ($cell['accesskey']) $options .= ' accesskey="'.html::htmlspecialchars($cell['accesskey']).'"';

		if (strchr($cell['size'],'$') || $cell['size']{0} == '@')	// expand cell['size'] for the button-disabled-check now
		{
			$cell['size'] = $this->expand_name($cell['size'],$show_c,$show_row,$content['.c'],$content['.row'],$content);
		}
		if ($cell['disabled'] && $readonlys[$name] !== false || $readonly && ($cell['type'] == 'button' || $cell['type'] == 'buttononly') &&  strpos($cell['size'],',')===false)
		{
			if ($this->rows == 1) {
				return '';	// if only one row omit cell
			}
			$cell = $this->empty_cell('label','',array('span' => $cell['span'])); // show nothing (keep the css class!)
			$value = '';
		}
		$extra_label = True;

		// the while loop allows to build extensions from other extensions
		// please note: only the first extension's post_process function is called !!!
		list($type,$sub_type) = explode('-',$cell['type']);
		while ((!$this->types[$cell['type']] || !empty($sub_type)) && $this->haveExtension($type,'pre_process'))
		{
			//echo "<p>pre_process($cell[name]/$cell[type])</p>\n";
			if (strchr($cell['size'],'$') || $cell['size']{0} == '@')
			{
				$cell['size'] = $this->expand_name($cell['size'],$show_c,$show_row,$content['.c'],$content['.row'],$content);
			}
			if (!$ext_type) $ext_type = $type;
			$extra_label = $this->extensionPreProcess($type,$form_name,$value,$cell,$readonlys[$name]);

			$readonly = $readonly || $cell['readonly'];	// might be set by extension
			$this->set_array($content,$name,$value);

			if ($cell['type'] == $type.'-'.$sub_type) break;	// stop if no further type-change

			list($type,$sub_type) = explode('-',$cell['type']);				
		}
		list(,$class) = explode(',',$cell['span']);	// might be set by extension
		if (strchr($class,'$') || $class{0} == '@')
		{
			$class = $this->expand_name($class,$show_c,$show_row,$content['.c'],$content['.row'],$content);
		}
		if ($cell['needed'] && !in_array($cell['type'],array('button','buttononly')))
		{
			$class .= ' inputRequired';
		}
		$cell_options = $cell['size'];
		if (strchr($cell_options,'$') || $cell_options{0} == '@')
		{
			$cell_options = $this->expand_name($cell_options,$show_c,$show_row,$content['.c'],$content['.row'],$content);
		}
		$label = $cell['label'];
		if (strchr($label,'$') || $label{0} == '@')
		{
			$label = $this->expand_name($label,$show_c,$show_row,$content['.c'],$content['.row'],$content);
		}
		$help = $cell['help'];
		if (strchr($help,'$') || $help{0} == '@')
		{
			$no_lang_on_help = true;
			$help = $this->expand_name($help,$show_c,$show_row,$content['.c'],$content['.row'],$content);
		}
		$blur = $cell['blur']{0} == '@' ? $this->get_array($content,substr($cell['blur'],1)) :
			(strlen($cell['blur']) <= 1 ? $cell['blur'] : lang($cell['blur']));

		if ($this->java_script())
		{
			if ($blur)
			{
				if (empty($value))
				{
					$value = $blur;
				}
				$onFocus .= "if(this.value=='".addslashes(html::htmlspecialchars($blur))."') this.value='';";
				$onBlur  .= "if(this.value=='') this.value='".addslashes(html::htmlspecialchars($blur))."';";
			}
			if ($help)
			{
				if ((int)$cell['no_lang'] < 2 && !$no_lang_on_help)
				{
					$help = lang($help);
				}
				if (($use_tooltip_for_help = strpos($help,'<') !== false && strip_tags($help) != $help))	// helptext is html => use a tooltip
				{
					$options .= html::tooltip($help);
				}
				else	// "regular" help-text in the statusline
				{
					$onFocus .= "self.status='".addslashes(html::htmlspecialchars($help))."'; return true;";
					$onBlur  .= "self.status=''; return true;";
					if (in_array($cell['type'],array('button','buttononly','file')))	// for button additionally when mouse over button
					{
						$options .= " onMouseOver=\"self.status='".addslashes(html::htmlspecialchars($help))."'; return true;\"";
						$options .= " onMouseOut=\"self.status=''; return true;\"";
					}
				}
			}
			if ($onBlur)
			{
				$options .= " onFocus=\"$onFocus\" onBlur=\"$onBlur\"";
			}
			if ($cell['onchange'] && !($cell['type'] == 'button' || $cell['type'] == 'buttononly'))
			{
				if (strchr($cell['onchange'],'$') || $cell['onchange']{0} == '@')
				{
					$cell['onchange'] = $this->expand_name($cell['onchange'],$show_c,$show_row,$content['.c'],$content['.row'],$content);
				}
				$options .= ' onChange="'.($cell['onchange'] == '1' ? 'this.form.submit();' : $this->js_pseudo_funcs($cell['onchange'],$cname)).'"';
			}
		}
		if ($form_name != '')
		{
			$options = 'id="'.($cell['id'] ? $cell['id'] : $form_name).'" '.$options;
		}

		switch ($type)
		{
			case 'label':	//  size: [b[old]][i[talic]],[link],[activate_links],[label_for],[link_target],[link_popup_size],[link_title]
				if (is_array($value))
					break;
				list($style,$extra_link,$activate_links,$label_for,$extra_link_target,$extra_link_popup,$extra_link_title) = explode(',',$cell_options,7);
				$value = strlen($value) > 1 && !$cell['no_lang'] ? lang($value) : $value;
				$value = nl2br(html::htmlspecialchars($value));
				if ($activate_links) $value = html::activate_links($value);
				if ($value != '' && $style && strpos($style,'b')!==false) $value = html::bold($value);
				if ($value != '' && $style && strpos($style,'i')!==false) $value = html::italic($value);
				// if the label has a name, use it as id in a span, to allow addressing it via javascript
				$html .= ($name ? '<span id="'.($cell['id']?$cell['id']:$name).'">' : '').$value.($name ? '</span>' : '');
				if ($help)
				{
					$class = array(
						'class'       => $class,
						'onmouseover' => "self.status='".addslashes(html::htmlspecialchars($help))."'; return true;",
						'onmouseout'  => "self.status=''; return true;",
					);
				}
				break;
			case 'html':	//  size: [link],[link_target],[link_popup_size],[link_title],[activate_links]
				list($extra_link,$extra_link_target,$extra_link_popup,$extra_link_title,$activate_links) = explode(',',$cell_options);
				if ($activate_links) $value = html::activate_links($value);
				$html .= $value;
				break;
			case 'int':		// size: [min],[max],[len],[precission/sprint format]
			case 'float':
				list($min,$max,$cell_options,$pre) = explode(',',$cell_options);
				if ($cell_options == '' && !$readonly)
				{
					$cell_options = $cell['type'] == 'int' ? 5 : 8;
				}
				if (($type == 'float' || !is_numeric($pre)) && $value && $pre)
				{
					$value = str_replace(array(' ',','),array('','.'),$value);
					$value = is_numeric($pre) ? round($value,$pre) : sprintf($pre,$value);
				}
				$cell_options .= ',,'.($cell['type'] == 'int' ? '/^-?[0-9]*$/' : '/^-?[0-9]*[,.]?[0-9]*$/');
				// fall-through
			case 'passwd' :
			case 'text':		// size: [length][,maxLength[,preg]]
				$cell_opts = explode(',',$cell_options,3);
				if ($readonly && (int)$cell_opts[0] >= 0)
				{
					$html .= strlen($value) ? html::bold(html::htmlspecialchars($value)) : '';
				}
				else
				{
					if ($cell_opts[0] < 0) $cell_opts[0] = abs($cell_opts[0]);
					$html .= html::input($form_name,$value,$type == 'passwd' ? 'password' : '',
						$options.html::formatOptions($cell_opts,'SIZE,MAXLENGTH'));

					if (!$readonly)
					{
						$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] =  array(
							'type'      => $cell['type'],
							'maxlength' => $cell_opts[1],
							'needed'    => $cell['needed'],
							'preg'      => $cell_opts[2],
							'min'       => $min,	// int and float only
							'max'       => $max,
						);
					}
				}
				unset($cell_opts);
				break;
			case 'textarea':	// Multiline Text Input, size: [rows][,cols]
				if ($readonly && !$cell_options)
				{
					$html .= '<div>'.nl2br(html::htmlspecialchars($value))."</div>\n";
				}
				else
				{
					$html .= html::textarea($form_name,$value,
						$options.html::formatOptions($cell_options,'ROWS,COLS'));
				}
				if (!$readonly)
				{
					$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] =  array(
						'type'      => $cell['type'],
						'needed'    => $cell['needed'],
					);
				}
				break;
			case 'htmlarea':	// Multiline formatted Text Input, size: {simple|extended|advanced},height,width,toolbar-expanded,upload-path
				list($mode,$height,$width,$toolbar,$baseref,$convertnl) = explode(',',$cell_options);
				
				if ($convertnl == 1) $value = nl2br($value);
				
				if (!$readonly)
				{
					$mode = $mode ? $mode : 'simple';
					$height = $height ? $height : '400px';
					$width = $width ? $width : '100%';
					$fckoptions = array(
						'toolbar_expanded' => $toolbar,
					);
					$html .= html::fckEditor($form_name,$value,$mode,$fckoptions,$height,$width,$baseref);
					
					$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] =  array(
						'type'      => $cell['type'],
						'needed'    => $cell['needed'],
					);
				}
				else
				{
					$html .= html::div(html::activate_links($value),'style="overflow: auto; width='. $width. '; height='. $height. '"');
				}
				break;
			case 'checkbox':
				$set_val = 1; $unset_val = 0;
				if (!empty($cell_options))
				{
					list($set_val,$unset_val,$ro_true,$ro_false) = explode(',',$cell_options);
					if (!$set_val && !$unset_val) $set_val = 1;
					$value = $value == $set_val;
				}
				if ($readonly)
				{
					if (count(explode(',',$cell_options)) < 3)
					{
						$ro_true = 'x';
						$ro_false = '';
					}
					if (!$value && $ro_false == 'disable') return '';

					$html .= $value ? html::bold($ro_true) : $ro_false;
				}
				else
				{
					if ($value) $options .= ' checked="checked"';

					if (($multiple = substr($cell['name'],-2) == '[]'))
					{
						// add the set_val to the id to make it unique
						$options = str_replace('id="'.$form_name,'id="'.substr($form_name,0,-2)."[$set_val]",$options);
					}
					$html .= html::input($form_name,$set_val,'checkbox',$options);
					
					if ($multiple) $form_name = $this->form_name($cname,substr($cell['name'],0,-2));

					if (!isset($GLOBALS['egw_info']['etemplate']['to_process'][$form_name]))
					{
						$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] = array(
							'type'        => $cell['type'],
							'unset_value' => $unset_val,
							'multiple'    => $multiple,
						);
					}
					$GLOBALS['egw_info']['etemplate']['to_process'][$form_name]['values'][] = $set_val;
					if (!$multiple) unset($set_val);	// otherwise it will be added to the label
				}
				break;
			case 'radio':		// size: value if checked, readonly set, readonly unset
				list($set_val,$ro_true,$ro_false) = explode(',',$cell_options);
				$set_val = $this->expand_name($set_val,$show_c,$show_row,$content['.c'],$content['.row'],$content);

				if ($value == $set_val)
				{
					$options .= ' checked="checked"';
				}
				// add the set_val to the id to make it unique
				$options = str_replace('id="'.$form_name,'id="'.$form_name."[$set_val]",$options);

				if ($readonly)
				{
					if (!$ro_true && !$ro_false) $ro_true = 'x';
					$html .= $value == $set_val ? html::bold($ro_true) : $ro_false;
				}
				else
				{
					$html .= html::input($form_name,$set_val,'RADIO',$options);
					$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] = $cell['type'];
				}
				break;
			case 'button':
			case 'buttononly':
			case 'cancel':	// cancel button
				list($app) = explode('.',$this->name);
				list($img,$ro_img) = explode(',',$cell_options);
				$title = strlen($label) <= 1 || $cell['no_lang'] ? $label : lang($label);
				if ($cell['onclick'] &&
					($onclick = $this->expand_name($cell['onclick'],$show_c,$show_row,$content['.c'],$content['.row'],$content)))
				{
					$onclick = $this->js_pseudo_funcs($onclick,$cname);
				}
				unset($cell['onclick']);	// otherwise the grid will handle it
				if ($this->java_script() && ($cell['onchange'] != '' || $img && !$readonly) && !$cell['needed']) // use a link instead of a button
				{
					$onclick = ($onclick ? preg_replace('/^return(.*);$/','if (\\1) ',$onclick) : '').
						(((string)$cell['onchange'] === '1' || $img) ? 
						"return submitit($this->name_form,'".addslashes($form_name)."');" : $cell['onchange']).'; return false;';
					
					if (!html::$netscape4 && substr($img,-1) == '%' && is_numeric($percent = substr($img,0,-1)))
					{
						$html .= html::progressbar($percent,$title,'onclick="'.$onclick.'" '.$options);
					}
					else
					{
						$html .= '<a href="" onClick="'.$onclick.'" '.$options.'>' .
							($img ? html::image($app,$img,$title,'border="0"') : $title) . '</a>';
					}
				}
				else
				{
					if (!empty($img))
					{
						$options .= ' title="'.$title.'"';
					}
					if ($cell['onchange'] && $cell['onchange'] != 1)
					{
						$onclick = ($onclick ? preg_replace('/^return(.*);$/','if (\\1) ',$onclick) : '').$cell['onchange'];
					}
					$html .= !$readonly ? html::submit_button($form_name,$label,$onclick,
						strlen($label) <= 1 || $cell['no_lang'],$options,$img,$app,$type == 'buttononly' ? 'button' : 'submit') :
						html::image($app,$ro_img);
				}
				$extra_label = False;
				if (!$readonly && $type != 'buttononly')	// input button, are never submitted back!
				{
					$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] = $cell['type'];
					if (strlen($name>0)) {
						if ($name == 'cancel' || stripos($name,'[cancel]') !== false)
						{
							$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] = 'cancel';
						}
					}
				}
				break;
			case 'hrule':
				$html .= html::hr($cell_options);
				break;
			case 'grid':
				if ($readonly && !$readonlys['__ALL__'])
				{
					if (!is_array($readonlys)) $readonlys = array();
					$set_readonlys_all = $readonlys['__ALL__'] = True;
				}
				if ($name != '')
				{
					$cname .= $cname == '' ? $name : '['.str_replace('[','][',str_replace(']','',$name)).']';
				}
				$html .= $this->show_grid($cell,$name ? $value : $content,$readonlys,$cname,$show_c,$show_row,$path);
				if ($set_readonlys_all) unset($readonlys['__ALL__']);
				break;
			case 'template':	// size: index in content-array (if not full content is past further on)
				if (is_object($cell['name']))
				{
					$cell['obj'] = &$cell['name'];
					unset($cell['name']);
					$cell['name'] = 'was Object';
					echo "<p>Object in Name in tpl '$this->name': "; _debug_array($grid);
				}
				$obj_read = 'already loaded';
				if (is_array($cell['obj']))
				{
					$obj =& new etemplate();
					$obj->init($cell['obj']);
					$cell['obj'] =& $obj;
					unset($obj);
				}
				if (!is_object($cell['obj']))
				{
					if ($cell['name']{0} == '@')
					{
						$cell['obj'] = $this->get_array($content,substr($cell['name'],1));
						$obj_read = is_object($cell['obj']) ? 'obj from content' : 'obj read, obj-name from content';
						if (!is_object($cell['obj']))
						{
							$cell['obj'] =& new etemplate($cell['obj'],$this->as_array());
						}
					}
					else
					{  $obj_read = 'obj read';
						$cell['obj'] =& new etemplate($name,$this->as_array());
					}
				}
				if (is_int($this->debug) && $this->debug >= 3 || $this->debug == $cell['type'])
				{
					echo "<p>show_cell::template(tpl=$this->name,name=$cell[name]): $obj_read, readonly=$readonly</p>\n";
				}
				if ($this->autorepeat_idx($cell,$show_c,$show_row,$idx,$idx_cname,false,$content) || $cell_options != '')
				{
					if ($span == '' && isset($content[$idx]['span']))
					{	// this allows a colspan in autorepeated cells like the editor
						list($span) = explode(',',$content[$idx]['span']);
						if ($span == 'all')
						{
							$span = 1 + $content['cols'] - $show_c;
						}
					}
					$readonlys = $this->get_array($readonlys,$idx);
					$content = $this->get_array($content,$idx);
					if ($idx_cname != '')
					{
						$cname .= $cname == '' ? $idx_cname : '['.str_replace('[','][',str_replace(']','',$idx_cname)).']';
					}
					//echo "<p>show_cell-autorepeat($name,$show_c,$show_row,cname='$cname',idx='$idx',idx_cname='$idx_cname',span='$span'): content ="; _debug_array($content);
				}
				if ($readonly && !$readonlys['__ALL__'])
				{
					if (!is_array($readonlys)) $readonlys = array();
					$set_readonlys_all = $readonlys['__ALL__'] = True;
				}
				// propagate our onclick handler to embeded templates, if they dont have their own
				if (!isset($cell['obj']->onclick_handler)) $cell['obj']->onclick_handler = $this->onclick_handler;
				if ($cell['obj']->no_onclick)
				{
					$cell['obj']->onclick_proxy = $this->onclick_proxy ? $this->onclick_proxy : $this->name.':'.$this->version.':'.$path;
				}
				// propagate the CSS class to the template
				if ($class)
				{
					$grid_size = array_pad(explode(',',$cell['obj']->size),4,'');
					$grid_size[3] = ($grid_size[3] ? $grid_size[3].' ' : '') . $class;
					$cell['obj']->size = implode(',',$grid_size);
				}
				$html = $cell['obj']->show($content,$this->sel_options,$readonlys,$cname,$show_c,$show_row);
				
				if ($set_readonlys_all) unset($readonlys['__ALL__']);
				break;
			case 'select':	// size:[linesOnMultiselect|emptyLabel,extraStyleMulitselect]
				$sels = array();
				list($multiple,$extraStyleMultiselect) = explode(',',$cell_options,2);
				if (!empty($multiple) && 0+$multiple <= 0)
				{
					$sels[''] = $multiple < 0 ? 'all' : $multiple;
					// extra-option: no_lang=0 gets translated later and no_lang=1 gets translated too (now), only no_lang>1 gets not translated
					if ((int)$cell['no_lang'] == 1)
					{
						$sels[''] = substr($sels[''],-3) == '...' ? lang(substr($sels[''],0,-3)).'...' : lang($sels['']);
					}
					$multiple = 0;
				}
				$sels += $this->_sel_options($cell,$name,$content);

				if ($multiple && !is_array($value)) $value = explode(',',$value);
				if ($readonly || $cell['noprint'])
				{
					foreach($multiple || is_array($value) ? $value : array($value) as $val)
					{
						if (is_array($sels[$val]))
						{
							$option_label = $sels[$val]['label'];
							$option_title = $sels[$val]['title'];
						}
						else
						{
							$option_label = $sels[$val];
							$option_title = '';
						}
						if (!$cell['no_lang']) $option_label = lang($option_label);
						
						if ($html) $html .= "<br>\n";

						if ($option_title)
						{
							$html .= '<span title="'.html::htmlspecialchars($option_title).'">'.html::htmlspecialchars($option_label).'</span>';
						}
						else
						{
							$html .= html::htmlspecialchars($option_label);
						}
					}
				}
				if (!$readonly)
				{
					if ($cell['noprint'])
					{
						$html = '<span class="onlyPrint">'.$html.'</span>';
						$options .= ' class="noPrint"';
					}
					if ($multiple && is_numeric($multiple))	// eg. "3+" would give a regular multiselectbox
					{
						$html .= html::checkbox_multiselect($form_name.($multiple > 1 ? '[]' : ''),$value,$sels,
							$cell['no_lang'],$options,$multiple,$multiple{0}!=='0',$extraStyleMultiselect);
					}
					else
					{
						$html .= html::select($form_name.($multiple > 1 ? '[]' : ''),$value,$sels,
							$cell['no_lang'],$options,$multiple);
					}
					if (!isset($GLOBALS['egw_info']['etemplate']['to_process'][$form_name]))
					{
						// fix for optgroup's
						$options=array();
						foreach($sels as $key => $val)
						{
							# we want the key anyway, even if this allowes more values than wanted (the name/key of the optgroup if there is one, 
							# the keys of the arrays in case you have key/value pair(s) as value for the value of your option ).
							$options[$key]=$key;
							if (is_array($val))
							{
								foreach(array_keys($val) as $key2)
								{
									$options[$key2]=$key2;
								}
							} 
						}
						$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] = array(
							'type'    => $cell['type'],
							'needed'  => $cell['needed'],
							'allowed' => array_keys($options),
							'multiple'=> $multiple,
						);
					}
				}
				break;
			case 'image':	// size: [link],[link_target],[imagemap],[link_popup],[id]
				$image = $value != '' ? $value : $name;
				list($app,$img) = explode('/',$image,2);
				if (!$app || !$img || !is_dir(EGW_SERVER_ROOT.'/'.$app) || strpos($img,'/')!==false)
				{
					$img = $image;
					list($app) = explode('.',$this->name);
				}
				if (!$readonly)
				{
					list($extra_link,$extra_link_target,$imagemap,$extra_link_popup,$id) = explode(',',$cell['size']);
				}
				$html .= html::image($app,$img,strlen($label) > 1 && !$cell['no_lang'] ? lang($label) : $label,
					'border="0"'.($imagemap?' usemap="'.html::htmlspecialchars($imagemap).'"':'').
					($id || $value ? ' id="'.($id ? $id : $name).'"' : ''));
				$extra_label = False;
				break;
			case 'file':	// size: size of the filename field
				if (!$readonly)
				{
					if ((int) $cell_options) $options .= ' size="'.(int)$cell_options.'"';
					$html .= html::input_hidden($path_name = str_replace($name,$name.'_path',$form_name),'.');
					$html .= html::input($form_name,'','file',$options);
					$GLOBALS['egw_info']['etemplate']['form_options'] =
						"enctype=\"multipart/form-data\" onsubmit=\"set_element2(this,'$path_name','$form_name')\"";
					$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] = $cell['type'];
				}
				break;
			case 'vbox':
			case 'hbox':
			case 'groupbox':
			case 'box':
				$rows = array();
				$box_row = 1;
				$box_col = 'A';
				$box_anz = 0;
				list($num,$orient,,,$keep_empty) = explode(',',$cell_options);
				if (!$orient) $orient = $type == 'hbox' ? 'horizontal' : ($type == 'box' ? false : 'vertical');
				for ($n = 1; $n <= (int) $num; ++$n)
				{
					$child = $cell[$n];	// first param is a var_param now!
					$h = $this->show_cell($child,$content,$readonlys,$cname,$show_c,$show_row,$nul,$cl,$path.'/'.$n);
					if ($h != '' && $h != '&nbsp;' || $keep_empty)
					{
						if ($orient != 'horizontal')
						{
							$box_row = $n;
						}
						else
						{
							$box_col = $this->num2chrs($n);
						}
						if (!$orient)
						{
							$html .= $cl ? html::div($h," class=\"$cl\"") : $h;
						}
						else
						{
							$rows[$box_row][$box_col] = $html = $h;
						}
						$box_anz++;
						if ($cell[$n]['align'])
						{
							$rows[$box_row]['.'.$box_col] = html::formatOptions($child['align'],'align');
							$sub_cell_has_align = true;
						}
						if (strlen($child['onclick']) > 1)
						{
							$rows[$box_row]['.'.$box_col] .= ' onclick="'.$this->js_pseudo_funcs($child['onclick'],$cname).'"'.
								($child['id'] ? ' id="'.$child['id'].'"' : '');
						}
						// allow to set further attributes in the tablecell, beside the class
						if (is_array($cl))
						{
							foreach($cl as $attr => $val)
							{
								if ($attr != 'class' && $val)
								{
									$rows[$box_row]['.'.$box_col] .= ' '.$attr.'="'.$val.'"';
								}
							}
							$cl = $cl['class'];
						}										
						$box_item_class = $this->expand_name(isset($this->class_conf[$cl]) ? $this->class_conf[$cl] : $cl,
							$show_c,$show_row,$content['.c'],$content['.row'],$content);
						$rows[$box_row]['.'.$box_col] .= html::formatOptions($box_item_class,'class');
					}
				}
				if ($box_anz > 1 && $orient)	// a single cell is NOT placed into a table
				{
					$html = html::table($rows,html::formatOptions($cell_options,',,cellpadding,cellspacing').
						($type != 'groupbox' ? html::formatOptions($class,'class').
							($cell['name'] ? ' id="'.$form_name.'"' : '') : '').
						($cell['align'] && $orient != 'horizontal' || $sub_cell_has_align ? ' width="100%"' : ''));	// alignment only works if table has full width
					if ($type != 'groupbox') $class = '';	// otherwise we create an extra div
				}
				// put the class of the box-cell, into the the class of this cell
				elseif ($box_item_class && $box_anz == 1)
				{
					$class = ($class ? $class . ' ' : '') . $box_item_class;
				}
				if ($type == 'groupbox')
				{
					if (strlen($label) > 1 && $cell['label'] == $label)
					{
						$label = lang($label);
					}
					$html = html::fieldset($html,$label,($cell['name'] ? ' id="'.$form_name.'"' : '').
						($class ? ' class="'.$class.'"' : ''));
					$class = '';	// otherwise we create an extra div
				}
				elseif (!$orient)
				{
					$html = html::div($html,html::formatOptions(array(
							$cell['height'],
							$cell['width'],
							$class,
							$cell['name'] ? $form_name : '',
						),'height,width,class,id')). ($html ? '' : '</div>');
					$class = '';	// otherwise we create an extra div
				}
				if ($box_anz > 1)	// small docu in the html-source
				{
					$html = "\n\n<!-- BEGIN $cell[type] -->\n\n".$html."\n\n<!-- END $cell[type] -->\n\n";
				}
				$extra_label = False;
				break;
			case 'deck':
				for ($n = 1; $n <= $cell_options && (empty($value) || $value != $cell[$n]['name']); ++$n) ;
				if ($n > $cell_options)
				{
					$value = $cell[1]['name'];
				}
				if ($s_width = $cell['width'])
				{
					$s_width = "width: $s_width".(substr($s_width,-1) != '%' ? 'px' : '').';';
				}
				if ($s_height = $cell['height'])
				{
					$s_height = "height: $s_height".(substr($s_height,-1) != '%' ? 'px' : '').';';
				}
				$html = html::input_hidden($form_name,$value);
				$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] =  $cell['type'];
						
				for ($n = 1; $n <= $cell_options; ++$n)
				{
					$child = $cell[$n];	// first param is a var_param now!
					$html .= html::div($this->show_cell($child,$content,$readonlys,$cname,$show_c,
						$show_row,$nul,$cl,$path.'/'.$n),html::formatOptions(array(
						'display: '.($value == $child['name'] ? 'inline' : 'none').';',
						$child['name']
					),'style,id'));
				}
				break;
			default:
				if ($ext_type && $this->haveExtension($ext_type,'render'))
				{
					$html .= $this->extensionRender($ext_type,$form_name,$value,$cell,$readonly);
				}
				else
				{
					$html .= "<i>unknown type '$cell[type]'</i>";
				}
				break;
		}
		// extension-processing need to be after all other and only with diff. name
		if ($ext_type && !$readonly && $this->haveExtension($ext_type,'post_process'))	
		{	// unset it first, if it is already set, to be after the other widgets of the ext.
			$to_process = 'ext-'.$ext_type;
			if (is_array($GLOBALS['egw_info']['etemplate']['to_process'][$form_name]))
			{
				$to_process = $GLOBALS['egw_info']['etemplate']['to_process'][$form_name];
				$to_process['type'] = 'ext-'.$ext_type;
			}
			unset($GLOBALS['egw_info']['etemplate']['to_process'][$form_name]);
			$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] = $to_process;
		}
		// save blur-value to strip it in process_exec
		if (!empty($blur) && isset($GLOBALS['egw_info']['etemplate']['to_process'][$form_name]))
		{
			if (!is_array($GLOBALS['egw_info']['etemplate']['to_process'][$form_name]))
			{
				$GLOBALS['egw_info']['etemplate']['to_process'][$form_name] = array(
					'type' => $GLOBALS['egw_info']['etemplate']['to_process'][$form_name]
				);
			}
			$GLOBALS['egw_info']['etemplate']['to_process'][$form_name]['blur'] = $blur;
		}
		if ($extra_label && ($label != '' || $html == ''))
		{
			if (strlen($label) > 1 && !($cell['no_lang'] && $cell['label'] != $label || (int)$cell['no_lang'] == 2))
			{
				$label = lang($label);
			}
			$accesskey = false;
			if (($accesskey = $label && strpos($label,'&')!==false) && $accesskey[1] != ' ' && $form_name != '' &&
					(($pos = strpos($accesskey,';')) === false || $pos > 5))
			{
				$label = str_replace('&'.$accesskey[1],'<u>'.$accesskey[1].'</u>',$label);
				$accesskey = $accesskey[1];
			}
			if ($label && !$readonly && ($accesskey || $label_for || $type != 'label' && $cell['name']))
			{
				$label = html::label($label,$label_for ? $this->form_name($cname,$label_for) : 
					$form_name.($set_val?"[$set_val]":''),$accesskey);
			}
			if ($type == 'radio' || $type == 'checkbox' || $label && strpos($label,'%s')!==false)	// default for radio is label after the button
			{
				$html = strpos($label,'%s')!==false ? str_replace('%s',$html,$label) : $html.' '.$label;
			}
			elseif (($html = $label . ' ' . $html) == ' ')
			{
				$html = '&nbsp;';
			}
		}
		if ($extra_link && (($extra_link = $this->expand_name($extra_link,$show_c,$show_row,$content['.c'],$content['.row'],$content))))
		{
			$options = $help ? ' onmouseover="self.status=\''.addslashes(html::htmlspecialchars($help)).'\'; return true;"' .
				' onmouseout="self.status=\'\'; return true;"' : '';

			if ($extra_link_target && (($extra_link_target = $this->expand_name($extra_link_target,$show_c,$show_row,$content['.c'],$content['.row'],$content))))
			{
				$options .= ' target="'.addslashes($extra_link_target).'"';
			}
			if ($extra_link_popup && (($extra_link_popup = $this->expand_name($extra_link_popup,$show_c,$show_row,$content['.c'],$content['.row'],$content))))
			{
				list($w,$h) = explode('x',$extra_link_popup);
				$options .= ' onclick="window.open(this,this.target,\'width='.(int)$w.',height='.(int)$h.',location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes\'); return false;"';
			}
			if ($extra_link_title)
			{
				$options .= ' title="'.addslashes($extra_link_title).'"';
			}
			return html::a_href($html,$extra_link,'',$options);
		}
		// if necessary show validation-error behind field
		if (isset($GLOBALS['egw_info']['etemplate']['validation_errors'][$form_name]))
		{
			$html .= ' <span style="color: red; white-space: nowrap;">'.$GLOBALS['egw_info']['etemplate']['validation_errors'][$form_name].'</span>';
		}
		// generate an extra div, if we have an onclick handler and NO children or it's an extension
		//echo "<p>$this->name($this->onclick_handler:$this->no_onclick:$this->onclick_proxy): $cell[type]/$cell[name]</p>\n";
		if ($this->onclick_handler && !isset(self::$widgets_with_children[$cell['type']]))
		{
			$handler = str_replace('%p',$this->no_onclick ? $this->onclick_proxy : $this->name.':'.$this->version.':'.$path,
				$this->onclick_handler);
			if ($type == 'button' || $type == 'buttononly' || !$label)	// add something to click on
			{
				$html = (substr($html,-1) == "\n" ? substr($html,0,-1) : $html).'&nbsp;';
			}
			return html::div($html,' ondblclick="'.$handler.'"','clickWidgetToEdit');
		}
		return $html;
	}

	/**
	* Retrive options for selectboxes and similar widgets (eg. the tree)
	*
	* @param array $cell
	* @param string $name
	* @param array $content=array();
	* @return array
	*/
	function _sel_options($cell,$name,$content=array())
	{
		$sels = array();

		if (!empty($cell['sel_options']))
		{
			if (!is_array($cell['sel_options']))
			{
				$opts = explode(',',$cell['sel_options']);
				while (list(,$opt) = each($opts))
				{
					list($k,$v) = explode('=',$opt);
					$sels[$k] = $v;
				}
			}
			else
			{
				$sels += $cell['sel_options'];
			}
		}
		if (isset($this->sel_options[$name]) && is_array($this->sel_options[$name]))
		{
			$sels += $this->sel_options[$name];
		}
		else
		{
			$name_parts = explode('[',str_replace(']','',$name));
			if (count($name_parts))
			{
				$org_name = $name_parts[count($name_parts)-1];
				if (isset($this->sel_options[$org_name]) && is_array($this->sel_options[$org_name]))
				{
					$sels += $this->sel_options[$org_name];
				}
				elseif (isset($this->sel_options[$name_parts[0]]) && is_array($this->sel_options[$name_parts[0]]))
				{
					$sels += $this->sel_options[$name_parts[0]];
				}
			}
		}
		if (isset($content["options-$name"]))
		{
			$sels += $content["options-$name"];
		}
		return $sels;
	}

	/**
	* Resolve javascript pseudo functions in onclick or onchange: 
	* - egw::link('$l','$p') calls $egw->link($l,$p)
	* - form::name('name') returns expanded name/id taking into account the name at that point of the template hierarchy
	* - egw::lang('Message ...') translate the message
	* - confirm('message') translates 'message' and adds a '?' if not present
	* - window.open() replaces it with egw_openWindowCentered2()
	* - xajax_doXMLHTTP('etemplate. replace ajax calls in widgets with special handler not requiring etemplate run rights
	* 
	* @param string $on onclick, onchange, ... action
	* @param string $cname name-prefix / name-space
	* @return string
	*/
	function js_pseudo_funcs($on,$cname)
	{
		if (strpos($on,'::') !== false)	// avoid the expensive regular expresions, for performance reasons
		{
			if (preg_match("/egw::link\\('([^']+)','(.+?)'\\)/",$on,$matches))	// the ? alters the expression to shortest match
			{                                                                 	// this way we can correctly parse ' in the 2. argument
				$url = $GLOBALS['egw']->link($matches[1],$matches[2]);
				$on = str_replace($matches[0],'\''.$url.'\'',$on);
			}
			
			if (preg_match_all("/form::name\\('([^']+)'\\)/",$on,$matches)) {
				foreach($matches[1] as $n => $matche_name) {
					$matches[1][$n] = '\''.$this->form_name($cname,$matche_name).'\'';
				}
				$on = str_replace($matches[0],$matches[1],$on);
			}
			// we need to search ungready (shortest possible match), to avoid catching to much
			if (preg_match('/egw::lang\(["\']{1}(.*)["\']{1}\)/U',$on,$matches)) {
				$str = lang($matches[1]);
				$on = str_replace($matches[0],'\''.addslashes($str).'\'',$on);
			}
			
			// inserts the styles of a named template
			if (preg_match('/template::styles\(["\']{1}(.*)["\']{1}\)/U',$on,$matches))
			{
				$tpl = $matches[1] == $this->name ? $this : new etemplate($matches[1]);
				$on = str_replace($matches[0],"'<style>".str_replace(array("\n","\r"),'',$tpl->style)."</style>'",$on);
			}
		}
		if (strpos($on,'confirm(') !== false && preg_match('/confirm\(["\']{1}(.*)["\']{1}\)/',$on,$matches)) {
			$question = lang($matches[1]).(substr($matches[1],-1) != '?' ? '?' : '');	// add ? if not there, saves extra phrase
			$on = str_replace($matches[0],'confirm(\''.addslashes($question).'\')',$on);
			//$on = preg_replace('/confirm\(["\']{1}(.*)["\']{1}\)/','confirm(\''.addslashes($question).'\')',$on);
		}
		
		if (strpos($on,'window.open(') !== false && preg_match("/window.open\('(.*)','(.*)','dependent=yes,width=(.*),height=(.*),scrollbars=yes,status=(.*)'\)/",$on,$matches)) {
			$on = str_replace($matches[0], "egw_openWindowCentered2('{$matches[1]}', '{$matches[2]}', '{$matches[3]}', '{$matches[4]}', '{$matches[5]}')", $on);
		}
		
		// replace xajax calls to code in widgets, with the "etemplate" handler,
		// this allows to call widgets with the current app, otherwise everyone would need etemplate run rights
		if (strpos($on,"xajax_doXMLHTTP('etemplate.") !== false) {
			$on = preg_replace("/^xajax_doXMLHTTP\('etemplate\.([a-z]+_widget\.[a-zA-Z0-9_]+)\'/",'xajax_doXMLHTTP(\''.$GLOBALS['egw_info']['flags']['currentapp'].'.\\1.etemplate\'',$on);
		}
		
		return $on;
	}

	/**
	* applies stripslashes recursivly on each element of an array
	* 
	* @param array &$var 
	* @return array
	*/
	static function array_stripslashes($var)
	{
		if (!is_array($var))
		{
			return stripslashes($var);
		}
		foreach($var as $key => $val)
		{
			$var[$key] = is_array($val) ? self::array_stripslashes($val) : stripslashes($val);
		}
		return $var;
	}

	/**
	* makes necessary adjustments on $_POST after a eTemplate / form gots submitted
	*
	* This is only an internal function, dont call it direct use only exec
	* Process_show uses a list of input-fields/widgets generated by show.
	*
	* @internal
	* @param array $content $_POST[$cname], on return the adjusted content
	* @param array $to_process list of widgets/form-fields to process
	* @param string $cname basename of our returnt content (same as in call to show)
	* @param string $type type of request
	* @return int number of validation errors (the adjusted content is returned by the var-param &$content !)
	*/
	private function process_show(&$content,$to_process,$cname='', $type = 'regular')
	{
		if (!isset($content) || !is_array($content) || !is_array($to_process))
		{
			return;
		}
		if (is_int($this->debug) && $this->debug >= 1 || $this->debug == $this->name && $this->name)
		{
			echo "<p>process_show($this->name) cname='$cname' start: content ="; _debug_array($content);
		}
		$content_in = $cname ? array($cname => $content) : $content;
		$content = array();
		if (get_magic_quotes_gpc())
		{
			$content_in = etemplate::array_stripslashes($content_in);
		}
		$GLOBALS['egw_info']['etemplate']['validation_errors'] = array();
		$this->canceled = $this->button_pressed = False;

		foreach($to_process as $form_name => $type)
		{
			if (is_array($type))
			{
				$attr = $type;
				$type = $attr['type'];
			}
			else
			{
				$attr = array();
			}
			$value = etemplate::get_array($content_in,$form_name,True,$GLOBALS['egw_info']['flags']['currentapp'] == 'etemplate' ? false : true );
			// The comment below does only aplay to normal posts, not for xajax. Files are not supported anyway by xajax atm.
			// not checked checboxes are not returned in HTML and file is in $_FILES and not in $content_in
			if($value === false && $type == 'xajaxResponse' /*!in_array($type,array('checkbox','file'))*/) continue;
			
			if (isset($attr['blur']) && $attr['blur'] == $value)
			{
				$value = '';	// blur-values is equal to emtpy
			}
			//echo "<p>process_show($this->name) loop was {$GLOBALS['egw_info']['etemplate']['loop']}, $type: $form_name = '$value'</p>\n";
			list($type,$sub) = explode('-',$type);
			switch ($type)
			{
				case 'ext':
					$_cont = &etemplate::get_array($content,$form_name,True);
					if (!$this->extensionPostProcess($sub,$form_name,$_cont,$value))
					{
						//echo "\n<p><b>unsetting content[$form_name] !!!</b></p>\n";
						$this->unset_array($content,$form_name);
					}
					// this else should NOT be unnecessary as $_cont is a reference to the index 
					// $form_name of $content, but under some circumstances a set/changed $_cont
					// does not result in a change in $content -- RalfBecker 2004/09/18
					// seems to depend on the number of (not existing) dimensions of the array -- -- RalfBecker 2005/04/06
					elseif (!etemplate::isset_array($content,$form_name))
					{
						//echo "<p>setting content[$form_name]='$_cont' because is was unset !!!</p>\n";
						$this->set_array($content,$form_name,$_cont);
					}
					if ($_cont === '' && $attr['needed'] && !$attr['blur'])
					{
						$this->set_validation_error($form_name,lang('Field must not be empty !!!'),'');
					}
					break;
				case 'htmlarea':
					$this->set_array($content,$form_name,$value);
					break;
				case 'int':
				case 'float':
				case 'passwd':
				case 'text':
				case 'textarea':
					if ($value === '' && $attr['needed'] && !$attr['blur'])
					{
						$this->set_validation_error($form_name,lang('Field must not be empty !!!'),'');
					}
					if ((int) $attr['maxlength'] > 0 && strlen($value) > (int) $attr['maxlength'])
					{
						$value = substr($value,0,(int) $attr['maxlength']);
					}
					if ($attr['preg'] && !preg_match($attr['preg'],$value))
					{
						switch($type)
						{
							case 'int':
								$this->set_validation_error($form_name,lang("'%1' is not a valid integer !!!",$value),'');
								break;
							case 'float':
								$this->set_validation_error($form_name,lang("'%1' is not a valid floatingpoint number !!!",$value),'');
								break;
							default:
								$this->set_validation_error($form_name,lang("'%1' has an invalid format !!!",$value),'');
								break;
						}
					}
					elseif ($type == 'int' || $type == 'float')	// cast int and float and check range
					{
						if ($value !== '' || $attr['needed'])	// empty values are Ok if needed is not set
						{
							$value = $type == 'int' ? (int) $value : (float) str_replace(',','.',$value);	// allow for german (and maybe other) format

							if (!empty($attr['min']) && $value < $attr['min'])
							{
								$this->set_validation_error($form_name,lang("Value has to be at least '%1' !!!",$attr['min']),'');
								$value = $type == 'int' ? (int) $attr['min'] : (float) $attr['min'];
							}
							if (!empty($attr['max']) && $value > $attr['max'])
							{
								$this->set_validation_error($form_name,lang("Value has to be at maximum '%1' !!!",$attr['max']),'');
								$value = $type == 'int' ? (int) $attr['max'] : (float) $attr['max'];
							}
						}
					}
					$this->set_array($content,$form_name,$value);
					break;
				case 'cancel':	// cancel button ==> dont care for validation errors
					if ($value)
					{
						$this->canceled = True;
						$this->set_array($content,$form_name,$value);
					}
					break;
				case 'button':
					if ($value)
					{
						$this->button_pressed = True;
						$this->set_array($content,$form_name,$value);
					}
					break;
				case 'select':
					if ($attr['allowed'])	// only check for $value is allowed, if allowed values are set
					{
						foreach(is_array($value) ? $value : array($value) as $val)
						{
							if (!($attr['multiple'] && !$val) && !in_array($val,$attr['allowed']))
							{
								$this->set_validation_error($form_name,lang("'%1' is NOT allowed ('%2')!",$val,implode("','",$attr['allowed'])),'');
								$value = '';
								break;
							}
						}
					}
					if (is_array($value)) $value = implode(',',$value);
					if ($value === '' && $attr['needed'])
					{
						$this->set_validation_error($form_name,lang('Field must not be empty !!!',$value),'');
					}
					$this->set_array($content,$form_name,$value);
					break;
				case 'checkbox':
					if ($value === false)	// get_array() returns false for not set
					{
						$this->set_array($content,$form_name,$attr['multiple'] ? array() : $attr['unset_value']);	// need to be reported too
					}
					else
					{
						$value = array_intersect(is_array($value) ? $value : array($value),$attr['values']); // return only allowed values
						$this->set_array($content,$form_name,$attr['multiple'] ? $value : $value[0]);
					}
					break;
				case 'file':
					$parts = explode('[',str_replace(']','',$form_name));
					$name = array_shift($parts);
					$index  = count($parts) ? '['.implode('][',$parts).']' : '';
					$value = array();
					foreach(array('tmp_name','type','size','name') as $part)
					{
						$value[$part] = is_array($_FILES[$name]) ? $this->get_array($_FILES[$name],$part.$index) : False;
					}
					$value['path'] = $this->get_array($content_in,substr($form_name,0,-1).'_path]');
					$value['ip'] = get_var('REMOTE_ADDR',Array('SERVER'));
					if (function_exists('is_uploaded_file') && !is_uploaded_file($value['tmp_name']))
					{
						$value = array();	// to be on the save side
					}
					//_debug_array($value);
					// fall-throught
				default:
					$this->set_array($content,$form_name,$value);
					break;
			}
		}
		if ($cname)
		{
			$content = $content[$cname];
		}
		if (is_int($this->debug) && $this->debug >= 2 || $this->debug == $this->name && $this->name)
		{
			echo "<p>process_show($this->name) end: content ="; _debug_array($content);
			if (count($GLOBALS['egw_info']['etemplate']['validation_errors']))
			{
				echo "<p>validation_errors = "; _debug_array($GLOBALS['egw_info']['etemplate']['validation_errors']);
			}
		}
		return count($GLOBALS['egw_info']['etemplate']['validation_errors']);
	}
	
	/**
	* Sets a validation error, to be displayed in the next exec
	*
	* @param string $name (complete) name of the widget causing the error
	* @param string $error error-message already translated
	* @param string $cname=null set it to '', if the name is already a form-name, defaults to $this->name_vars
	*/
	function set_validation_error($name,$error,$cname=null)
	{
		if (is_null($cname)) $cname = $this->name_vars;
		//echo "<p>etemplate::set_validation_error('$name','$error','$cname');</p>\n";
		if ($cname) $name = $this->form_name($cname,$name);
		
		if ($GLOBALS['egw_info']['etemplate']['validation_errors'][$name])
		{
			$GLOBALS['egw_info']['etemplate']['validation_errors'][$name] .= ', ';
		}
		$GLOBALS['egw_info']['etemplate']['validation_errors'][$name] .= $error;
	}

	/**
	* is javascript enabled?
	*
	* this should be tested by the api at login
	*
	* @return boolean true if javascript is enabled or not yet tested and $consider_not_tested_as_enabled 
	*/
	function java_script($consider_not_tested_as_enabled = True)
	{
		$ret = !!$GLOBALS['egw_info']['etemplate']['java_script'] ||
			$consider_not_tested_as_enabled && !isset($GLOBALS['egw_info']['etemplate']['java_script']);
		//echo "<p>java_script($consider_not_tested_as_enabled)='$ret', java_script='".$GLOBALS['egw_info']['etemplate']['java_script']."', isset(java_script)=".isset($GLOBALS['egw_info']['etemplate']['java_script'])."</p>\n";
		
		return $ret;
		return !!$GLOBALS['egw_info']['etemplate']['java_script'] ||
			$consider_not_tested_as_enabled &&
			(!isset($GLOBALS['egw_info']['etemplate']['java_script']) ||
			$GLOBALS['egw_info']['etemplate']['java_script'].'' == '');
	}

	/**
	* returns the javascript to be included by exec
	*
	* @param int $what &1 = returns the test, note: has to be included in the body, not the header, 
	*		&2 = returns the common functions, best to be included in the header
	* @return string javascript
	*/
	private function include_java_script($what = 3)
	{
		// this is to test if javascript is enabled
		if ($what & 1 && !isset($GLOBALS['egw_info']['etemplate']['java_script']))
		{
			$js = '<script language="javascript">
document.write(\''.str_replace("\n",'',html::input_hidden('java_script','1')).'\');
if (document.getElementById) {
	document.write(\''.str_replace("\n",'',html::input_hidden('dom_enabled','1')).'\');
}
</script>
';
		}
		// here are going all the necesarry functions if javascript is enabled
		if ($what & 2 && $this->java_script(True))
		{
			$lastmod = filectime(EGW_INCLUDE_ROOT. '/etemplate/js/etemplate.js');
			$js .= '<script type="text/javascript" src="'.
				$GLOBALS['egw_info']['server']['webserver_url'].'/etemplate/js/etemplate.js?'. $lastmod.'"></script>'."\n";
		}
		return $js;
	}
};
