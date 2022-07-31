<?php
/**
 * EGroupware - eTemplate request object storing request-data directly in the form itself
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate;

use EGroupware\Api;

/**
 * Class to represent the persitent information of an eTemplate request
 *
 * Current default for etemplate_request is to store request-data in egw_cache by
 * setting a not set etemplate_request::$request_class to 'etemplate_request_cache'.
 *
 * This class stores the request-data direct in a hidden var in the form.
 * As this would allow an evil user to manipulate it and therefore compromise the security
 * of an EGroupware instance, this class should only be used, if mcrypt is available
 * to encrypt that data. The factory method etemplate_request::read() ensures that,
 * by using etemplate_request_session instead.
 *
 * The key used to encrypt the request can be set in header.inc.php by setting
 *
 *		$GLOBALS['egw_info']['server']['etemplate_form_key'] = 'something secret';
 *
 * if this var is not set, the db_pass and EGW_SERVER_ROOT is used instead.
 *
 * The request object should be instancated only via the factory method etemplate_request::read($id=null)
 *
 * $request = Api\Etemplate\Request::read();
 *
 * // add request data
 *
 * $id = $request->id();
 *
 * b) open or modify an existing request:
 *
 * if (!($request = Api\Etemplate\Request::read($id)))
 * {
 * 		// request not found
 * }
 *
 * Ajax requests can use this object to open the original request by using the id, they have to transmitt back,
 * and register further variables, modify the registered ones or delete them AND then update the id, if it changed:
 *
 * eTemplate2:
 *
 *	if (($new_id = $request->id()) != $exec_id)
 *	{
 *		Api\Json\Response::get()->generic('assign', array(
 *			'etemplate_exec_id' => $id,
 *			'id' => '',
 *			'key' => 'etemplate_exec_id',
 *			'value' => $new_id,
 *		));
 *	}
 *
 * old eTemplate:
 *
 *	if (($new_id = $request->id()) != $exec_id)
 *	{
 *		Api\Json\Response::get()->assign('etemplate_exec_id','value',$new_id);
 *	}
 *
 * For an example look in link_widget::ajax_search()
 *
 * @property-read boolean $data_modified true if data was modified and therefore needs saving
 * @property int $output_mode
 * @property array $content
 * @property array $changes
 * @property array $sel_options
 * @property array $readonlys
 * @property array $preserv
 * @property string $method
 * @property array $ignore_validation
 * @property array $template
 * @property string $app_header
 */
class Request
{
	/**
	 * here is the request data stored
	 *
	 * @var array
	 */
	protected $data=[
		'content' => [],
		'readonlys' => [],
	];
	/**
	 * Flag if data has been modified and therefor need to be stored again in the session
	 *
	 * @var boolean
	 */
	protected $data_modified=false;
	/**
	 * Flag that stored data should be removed by destructor, if not modified.
	 *
	 * @var boolean
	 */
	protected $remove_if_not_modified=false;
	/**
	 * mcrypt resource
	 *
	 * @var resource
	 */
	static protected $mcrypt;

	/**
	 * See gzcompress, set it to 0 to not compress
	 *
	 * @var int
	 */
	static public $compression_level = 6;

	/**
	 * Name of request class used
	 *
	 * Can be set here to force a certain class, otherwise the factory method chooses one
	 *
	 * @var string
	 */
	static public $request_class; // = 'EGroupware\Api\Etemplate\Request\Session';

	/**
	 * Factory method to get a new request object or the one for an existing request
	 *
	 * New default is to use egw_cache to store requests and no longer request or
	 * session documented below:
	 *
	 * If mcrypt AND gzcompress is available this factory method chooses etemplate_request,
	 * which stores the request data encrypted in a hidden var directly in the form,
	 * over etemplate_request_session, which stores the data in the session (and causing
	 * the sesison to constantly grow).
	 *
	 * @param string $id =null
	 * @param bool $handle_not_found =true true: handle not found by trying to redirect, false: just return null
	 * @return Request|null null if Request not found and $handle_not_found === false
	 */
	public static function read($id=null, $handle_not_found=true)
	{
		if (is_null(self::$request_class))
		{
			// new default to use egw_cache to store requests
			self::$request_class = __CLASS__.'\\Cache';
			/* old default to use request if mcrypt and gzcompress are available and session if not
			self::$request_class = check_load_extension('mcrypt') && function_exists('gzcompress') &&
				self::init_crypt() ? __CLASS__ : 'EGroupware\Api\Etemplate\Request\Session';
			 */
		}
		if (self::$request_class != __CLASS__)
		{
			$request = call_user_func(self::$request_class.'::read', $id);
		}
		else
		{
			$request = new Request();

			if (!is_null($id))
			{
				$id = base64_decode($id);

				// decrypt the data if available
				if (self::init_crypt())
				{
					$id = mdecrypt_generic(self::$mcrypt,$id);
				}
				// uncompress the data if available
				if (self::$compression_level && function_exists('gzcompress'))
				{
					//$len_compressed = bytes($id);
					//$time = microtime(true);
					$id = gzuncompress($id);
					//$time = number_format(1000.0 * (microtime(true) - $time),1);
					//$len_uncompressed = bytes($id);
					//error_log(__METHOD__."() uncompressed from $len_compressed to $len_uncompressed bytes $time ms");
				}
				$request->data = unserialize($id);

				if (!$request->data)
				{
					error_log(__METHOD__."() id not valid!");
					$request = false;
				}
				//error_log(__METHOD__."() size of request = ".bytes($id));
			}
		}
		if (!$request && $handle_not_found)	// eT2 request/session expired
		{
			list($app) = explode('.', $_GET['menuaction']);
			$global = false;
			if(isset($GLOBALS['egw_info']['apps'][$app]))
			{
				$index_url = isset($GLOBALS['egw_info']['apps'][$app]['index']) ?
					'/index.php?menuaction='.$GLOBALS['egw_info']['apps'][$app]['index'] : '/'.$app.'/index.php';
			}
			else
			{
				$index_url = '/index.php';
				$global = true;
				$app = null;
			}
			// add a unique token to redirect to avoid client-side framework tries refreshing via nextmatch
			$index_url .= (strpos($index_url, '?') ? '&' : '?').'redirect='.microtime(true);
			error_log(__METHOD__."('$id', ...) eT2 request not found / expired --> redirecting app $app to $index_url (_GET[menuaction]=$_GET[menuaction], isJSONRequest()=".array2string(Api\Json\Request::isJSONRequest()).')');
			if (Api\Json\Request::isJSONRequest())
			{
				// we must not redirect ajax_destroy_session calls, as they might originate from our own redirect!
				if (strpos($_GET['menuaction'], '.ajax_destroy_session.etemplate') === false)
				{
					$response = Api\Json\Response::get();
					$response->redirect(Api\Framework::link($index_url), $global, $app);
					exit;
				}
			}
			else
			{
				Api\Framework::redirect_link($index_url);
			}
		}
		return $request;
	}

	/**
	 * CSRF check using an etemplate-exec-id
	 *
	 * If eTemplate request object could not be read, the function will NOT return,
	 * but send an Ajax error response and exit or die with the error-message!
	 *
	 * @param string $id etemplate-exec-id
	 * @param string $caller calling method to log
	 * @param array $args =[] arguments to log
	 * @throws Api\Json\Exception
	 */
	public static function csrfCheck($id, $caller, $args=[])
	{
		if (!self::read($id, false))		// false: do NOT handle not found, but return null
		{
			error_log(__METHOD__."('$id', $caller, ".json_encode($args).") called with invalid/expired etemplate_exec_id: possible CSRF detected from IP ".$_SERVER['REMOTE_ADDR'].' to '.$_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']);
			$msg = lang('Request could not be processed, please reload your window (press F5 or Cmd R)!');

			if (Api\Json\Request::isJSONRequest())
			{
				Api\Json\Response::get()->message($msg, 'error');
				exit;
			}
			die($msg);
		}
	}

	/**
	 * Private constructor to force the instancation of this class only via it's static factory method read
	 *
	 * @param string $id =null
	 */
	private function __construct($id=null)
	{
		unset($id);
	}

	/**
	 * return the id of this request
	 *
	 * @return string
	 */
	public function &id()
	{
		$this->cleanup();
		$data = serialize($this->data);

		// compress the data if available
		if (self::$compression_level && function_exists('gzcompress'))
		{
			//$len_uncompressed = bytes($id);
			//$time = microtime(true);
			$data = gzcompress($data, self::$compression_level);
			//$time = number_format(1000.0 * (microtime(true) - $time),1);
			//$len_compressed = bytes($id);
			//error_log(__METHOD__."() compressed from $len_uncompressed to $len_compressed bytes in $time ms");
		}
		// encrypt the data if available
		if (self::init_crypt())
		{
			$data = mcrypt_generic(self::$mcrypt, $data);
		}
		$id = base64_encode($data);

		//error_log(__METHOD__."() #$this->id: size of request = ".bytes($id));//.", id='$id'");
		//self::debug();
		return $id;
	}

	/**
	 * Clean up data before storing it: currently only removes "real" nextmatch rows
	 */
	protected function cleanup()
	{
		if (isset($this->data['content']['nm']))
		{
			if (is_array($this->data['content']['nm']['rows']))
			{
				foreach(array_keys($this->data['content']['nm']['rows']) as $n)
				{
					if (is_int($n))
					{
						unset($this->data['content']['nm']['rows'][$n]);
					}
				}
				//error_log(__METHOD__."() content[nm][rows]=".array2string($this->data['content']['nm']['rows']));
			}
			// do not store actions
			unset($this->data['content']['nm']['actions'], $this->data['content']['nm']['action_links']);
		}
	}

	/**
	 * Register a form-variable to be processed
	 *
	 * @param string $_form_name form-name
	 * @param string $type etemplate type
	 * @param array $data =array() optional extra data
	 */
	public function set_to_process($_form_name, $type, $data=array())
	{
		if (!$_form_name || !$type) return;

		//echo '<p>'.__METHOD__."($form_name,$type,".array2string($data).")</p>\n";
		$data['type'] = $type;

		// unquote single and double quotes, as this is how they get returned in $_POST
		$form_name = str_replace(array('\\\'','&quot;'), array('\'','"'), $_form_name);

		$this->data['to_process'][$form_name] = $data;
		$this->data_modified = true;
	}

	/**
	 * Set an attribute of a to-process record
	 *
	 * @param string $_form_name form-name
	 * @param string $attribute etemplate type
	 * @param array $value
	 * @param boolean $add_to_array =false should $value be added to the attribute array
	 */
	public function set_to_process_attribute($_form_name, $attribute, $value, $add_to_array=false)
	{
		//echo '<p>'.__METHOD__."($form_name,$attribute,$value,$add_to_array)</p>\n";
		if (!$_form_name) return;

		// unquote single and double quotes, as this is how they get returned in $_POST
		$form_name = str_replace(array('\\\'','&quot;'), array('\'','"'), $_form_name);

		if ($add_to_array)
		{
			$this->data['to_process'][$form_name][$attribute][] = $value;
		}
		else
		{
			$this->data['to_process'][$form_name][$attribute] = $value;
		}
		$this->data_modified = true;
	}

	/**
	 * Unregister a form-variable to be no longer processed
	 *
	 * @param string $form_name form-name
	 */
	public function unset_to_process($form_name)
	{
		//echo '<p>'.__METHOD__."($form_name) isset_to_process($form_name)=".$this->isset_to_process($form_name)."</p>\n";
		unset($this->data['to_process'][$form_name]);
		$this->data_modified = true;
	}

	/**
	 * return the data of a form-var to process or the whole array
	 *
	 * @param string $form_name =null
	 * @return array
	 */
	public function get_to_process($form_name=null)
	{
		//echo '<p>'.__METHOD__."($form_name)</p>\n";
		return $form_name ? $this->data['to_process'][$form_name] : $this->data['to_process'];
	}

	/**
	 * check if something set for a given $form_name
	 *
	 * @param string $form_name
	 * @return boolean
	 */
	public function isset_to_process($form_name)
	{
		//echo '<p>'.__METHOD__."($form_name) = ".array2string(isset($this->data['to_process'][$form_name]))."</p>\n";
		return isset($this->data['to_process'][$form_name]);
	}

	/**
	 * creates a new unique request-id
	 *
	 * @return string
	 * @throws \Exception if it was not possible to gather sufficient entropy.
	 */
	static function request_id()
	{
		// replace url-unsafe chars with _ to not run into url-encoding issues when used in a url
		$userID = preg_replace('/[^a-z0-9_\\.@-]/i', '_', $GLOBALS['egw_info']['user']['account_lid']);

		// replace + with _ to not run into url-encoding issues when used in a url
		$token = str_replace('+', '_', base64_encode(random_bytes(32)));

		return $GLOBALS['egw_info']['flags']['currentapp'].'_'.$userID.'_'.$token;
	}

	/**
	 * magic function to set all request-vars, used eg. as $request->method = 'app.class.method';
	 *
	 * @param string $var
	 * @param mixed $val
	 */
	public function __set($var, $val)
	{
		if (!isset($this->data[$var]) || $this->data[$var] !== $val)
		{
			$this->data[$var] = $val;
			//error_log(__METHOD__."('$var', ...) data of id=$this->id changed ...");
			$this->data_modified = true;
		}
	}

	/**
	 * magic function to access the request-vars, used eg. as $method = $request->method;
	 *
	 * @param string $var
	 * @return mixed
	 */
	public function &__get($var)
	{
		if ($var == 'data_modified') return $this->data_modified;

		return $this->data[$var];
	}


	/**
	 * magic function to see if a request-var has been set
	 *
	 * @param string $var
	 * @return boolean
	 */
	public function __isset($var)
	{
		return array_key_exists($var, $this->data);
	}

	/**
	 * Get the names / keys of existing variables
	 *
	 * @return array
	 */
	public function names()
	{
		return array_keys($this->data);
	}

	/**
	 * Output the size-wise important parts of a request
	 *
	 * @param double $min_share minimum share to be reported (in percent of the whole request)
	 * @param double $dump_share minimum share from which on a variable get output
	 */
	public function debug($min_share=1.0,$dump_share=25.0)
	{
		echo "<p><b>total size request data = ".($total=strlen(serialize($this->data)))."</b></p>\n";
		echo "<p>shares bigger then $min_share% percent of it:</p>\n";
		foreach($this->data as $key => $val)
		{
			$len = strlen(is_array($val) ? serialize($val) : $val);
			$len .= ' ('.sprintf('%2.1f',($percent = 100.0 * $len / $total)).'%)';
			if ($percent < $min_share) continue;
			echo "<p><b>$key</b>: strlen(\$val)=$len</p>\n";
			if ($percent >= $dump_share) _debug_array($val);
			if (is_array($val) && $len > 2000)
			{
				foreach($val as $k => $v)
				{
					$l = strlen(is_array($v) ? serialize($v) : $v);
					$l .= ' ('.sprintf('%2.1f',($p = 100.0 * $l / $total)).'%)';
					if ($p < $min_share) continue;
					echo "<p>&nbsp;- {$key}[$k]: strlen(\$v)=$l</p>\n";
				}
			}
		}
	}

	/**
	 * Check if session encryption is configured, possible and initialise it
	 *
	 * @param string $algo ='tripledes'
	 * @param string $mode ='ecb'
	 * @return boolean true if encryption is used, false otherwise
	 */
	static public function init_crypt($algo='tripledes',$mode='ecb')
	{
		if (is_null(self::$mcrypt))
		{
			if (isset($GLOBALS['egw_info']['server']['etemplate_form_key']))
			{
				$key = $GLOBALS['egw_info']['server']['etemplate_form_key'];
			}
			else
			{
				$key = $GLOBALS['egw_info']['server']['db_pass'].EGW_SERVER_ROOT;
			}
			if (!check_load_extension('mcrypt'))
			{
				error_log(__METHOD__."() required PHP extension mcrypt not loaded and can not be loaded, eTemplate requests get NOT encrypted!");
				return false;
			}
			if (!(self::$mcrypt = mcrypt_module_open($algo, '', $mode, '')))
			{
				error_log(__METHOD__."() could not mcrypt_module_open(algo='$algo','',mode='$mode',''), eTemplate requests get NOT encrypted!");
				return false;
			}
			$iv_size = mcrypt_enc_get_iv_size(self::$mcrypt);
			if (!isset($GLOBALS['egw_info']['server']['mcrypt_iv']) || strlen($GLOBALS['egw_info']['server']['mcrypt_iv']) < $iv_size)
			{
				if (!($iv = mcrypt_create_iv ($iv_size, MCRYPT_DEV_RANDOM)))
				{
					return self::$mcrypt = false;
				}
			}
			else
			{
				$iv = substr($GLOBALS['egw_info']['server']['mcrypt_iv'],0,$iv_size);
			}
			$key_size = mcrypt_enc_get_key_size(self::$mcrypt);
			if (bytes($key) > $key_size) $key = cut_bytes($key,0,$key_size-1);

			if (mcrypt_generic_init(self::$mcrypt,$key, $iv) < 0)
			{
				error_log(__METHOD__."() could not initialise mcrypt, sessions get NOT encrypted!");
				return self::$mcrypt = false;
			}
		}
		return is_resource(self::$mcrypt);
	}

	/**
	 * Destructor
	 */
	function __destruct()
	{
		if (self::$mcrypt)
		{
			mcrypt_generic_deinit(self::$mcrypt);
			self::$mcrypt = null;
		}
	}

	/**
	 * Mark request as to destroy, if it does not get modified before destructor is called
	 *
	 * If that function is called, request is removed from storage, further modification will work!
	 */
	public function remove_if_not_modified()
	{
		$this->remove_if_not_modified = true;
	}
}