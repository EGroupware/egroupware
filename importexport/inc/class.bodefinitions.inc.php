<?php
/**
 * eGroupWare - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id:  $
 */

require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.definition.inc.php');
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.so_sql.inc.php');

/** bo to define {im|ex}ports
 *
 * @todo make this class an egw_record_pool!
 */
class bodefinitions {

	const _appname = 'importexport';
	const _defintion_talbe = 'egw_importexport_definitions';
	
	/**
	 * holds so_sql
	 *
	 * @var so_sql
	 */
	private $so_sql;
	private $definitions;
	
	public function __construct($_query=false)
	{
		$this->so_sql = new so_sql(self::_appname, self::_defintion_talbe );
		if ($_query) {
			$definitions = $this->so_sql->search($_query, true);
			foreach ((array)$definitions as $definition) {
				$this->definitions[] = $definition['definition_id'];
			}
		}
	}
	
	public function get_definitions() {
		return $this->definitions;
	}
	
	/**
	 * reads a definition from database
	 *
	 * @param mixed &$definition
	 * @return bool success or not
	 */
	public function read(&$definition)
	{
		if(is_int($definition)) $definition = array('definition_id' => $definition);
		elseif(is_string($definition)) $definition = array('name' => $definition);
		if(!$definition = $this->so_sql->read($definition)) return false;
		$definition += (array)unserialize($definition['plugin_options']);
		unset($definition['plugin_options']);
		return true;
	}
	
	public function save($content)
	{
		$plugin = $content['plugin'];
		if (!$plugin) return false;
		
		$definition = array_intersect_key($content,array_flip($this->so_sql->db_cols));
		
		if(is_object($this->plugin) && $this->plugin->plugin_info['plugin'] == $plugin)
		{
			$plugin_options = array_intersect_key($content,array_flip($this->plugin->plugin_options));
		}
		else 
		{
			// we come eg. from definition import
			$file = EGW_SERVER_ROOT . SEP . $definition['application'] . SEP . 'inc' . SEP . 'importexport'. SEP . 'class.'.$definition['plugin'].'.inc.php';	
			if (is_file($file))
			{
				@include_once($file);
				$obj = new $plugin;
				$plugin_options = array_intersect_key($content,array_flip($obj->plugin_options));
				unset($obj);
			}
			else 
			{
				foreach ($this->so_sql->db_cols as $col) unset($content[$col]);
				$plugin_options = $content;

			}
		}
		$definition['plugin_options'] = serialize($plugin_options);
		$this->so_sql->data = $definition;
		//print_r($definition);
		return $this->so_sql->save();
	}
	
	public function delete($keys)
	{
		$this->so_sql->delete(array('definition_id' => $keys));
	}
	
	/**
	 * checkes if user if permitted to access given definition
	 *
	 * @param array $_definition
	 * @return bool
	 */
	static public function is_permitted($_definition) {
		$allowed_user = explode(',',$_definition['allowed_users']);
		$this_user_id = $GLOBALS['egw_info']['user']['userid'];
		$this_membership = $GLOBALS['egw']->accounts->membership($this_user_id);
		$this_membership[] = array('account_id' => $this_user_id);
		//echo $this_user_id;
		//echo ' '.$this_membership;
		foreach ((array)$this_membership as $account)
		{
			$this_membership_array[] =  $account['account_id'];
		}
		$alluser = array_intersect($allowed_user,$this_membership_array);
		return in_array($this_user_id,$alluser) ? true : false;
	}
	
	/**
	 * searches and registers plugins from all apps
	 *
	 * @deprecated see import_export_helperfunctions::get_plugins
	 * @return array $info info about existing plugins
	 */
	static public function plugins()
	{
		if (!key_exists('apps',$GLOBALS['egw_info'])) return false;
		foreach (array_keys($GLOBALS['egw_info']['apps']) as $appname)
		{			
			$dir = EGW_INCLUDE_ROOT . "/$appname/inc";
			if(!$d = @opendir($dir)) continue;
			while (false !== ($file = readdir($d))) 
			{
				//echo $file."\n";
				$pnparts = explode('.',$file);
				if(!is_file($file = "$dir/$file") || substr($pnparts[1],0,7) != 'wizzard' || $pnparts[count($pnparts)-1] != 'php') continue;
				$plugin_classname = $pnparts[1];
				include_once($file);
				if (!is_object($GLOBALS['egw']->$plugin_classname))
					$GLOBALS['egw']->$plugin_classname = new $plugin_classname;
				$info[$appname][$GLOBALS['egw']->$plugin_classname->plugin_info['plugin']] = $GLOBALS['egw']->$plugin_classname->plugin_info;
			}
			closedir($d);
		}
		return $info;
	}


	/**
	 * exports definitions
	 *
	 * @param array $keys to export
	 */
	public function export($keys)
	{
		$export_data = array('metainfo' => array(
			'type' => 'importexport definitions',
			'charset' => 'bal',
			'entries' => count($keys),
		));
		
		foreach ($keys as $definition_id)
		{
			$definition = array('definition_id' => $definition_id);
			$this->read($definition);
			unset($definition['definition_id']);
			$export_data[$definition['name']] = $definition;
		}
		/* This is no fun as import -> export cycle in xmltools results in different datas :-(
		$xml =& CreateObject('etemplate.xmltool','root');
		$xml->import_var('importexport.definitions', $export_data);
		$xml_data = $xml->export_xml();
		
		we export serialised arrays in the meantime
		*/
		return serialize($export_data);
	}
	
	public function import($import_file)
	{
		if (!is_file($import_file['tmp_name'])) return false;
		$f = fopen($import_file['tmp_name'],'r');
		$data = fread($f,100000);
		fclose($f);
		if (($data = unserialize($data)) === false) return false;
		$metainfo = $data['metainfo'];
		unset($data['metainfo']);
		
		foreach ($data as $name => $definition)
		{
			error_log(print_r($definition,true));
			//if (($ext = $this->search(array('name' => $name),'definition_id')) !== false)
			//{
			//	error_log(print_r($ext,true));
			//	$definition['definition_id'] = $ext[0]['definition_id'];
			//}
			$this->save($definition);
		}
	}

}

