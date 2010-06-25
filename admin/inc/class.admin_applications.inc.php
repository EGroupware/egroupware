<?php
/**
 *  Shows a list of all applications and reorder them.
 *
 * @link http://www.egroupware.org
 * @author Christian Füller <cf@stylite.de>
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.admin_register_hooks.inc.php 29103 2010-05-25 15:30:25Z füller $
 */

class admin_applications
{
	var $public_functions = array(
		'index'	=> True,
	);

	/**
	 * Our storage object
	 *
	 * @var so_sql
	 */
	protected $so;

	/**
	 * Name of our table
	 */
	const TABLE = 'egw_applications';
	
	/**
	 * Name of app the table is registered
	 */
	const APP = 'phpgwapi';

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		$this->so = new so_sql(self::APP,self::TABLE,null,'',true);
	}

	/**
	 * query rows for the nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl, not use here, maybe in a derived class
	 * @return int total number of rows
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		//$query['col_filter'][] = array('app_enabled' => array(1,4));
		$total = $this->so->get_rows($query,$rows,$readonlys);

		foreach($rows as $i => &$row)
		{
			//_debug_array($GLOBALS['egw_info']['apps'][$app]);
			$row['image'] = (isset($row['app_icon_app'])?$row['app_icon_app']:$row['app_name']).'/'.
							(isset($row['app_icon'])?$row['app_icon']:'navbar');
			//_debug_array($row);
			if($i == 0)
				$readonlys['up['.$row['app_id'].']'] = true;
			if($i == count($rows) - 1)
				$readonlys['down['.$row['app_id'].']'] = true;
		}

		return $total;
	}

	/**
	 * Display the applications
	 *
	 * @param array $content
	 */
	function index(array $content=null)
	{
		//_debug_array($content);
		if(!isset($content))
		{
			$content['nm'] = array(
				'get_rows'       =>	'admin.admin_applications.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
				'no_filter'      => True,	// I  disable the 1. filter
				'col_filter'     =>	array('app_enabled' => array(1,4),"app_name not in ('admin','manual')"),	// =All	// IO filter, if not 'no_filter' => True
				'no_filter2'     => True,	// I  disable the 2. filter (params are the same as for filter)
				'no_cat'         => True,	// I  disable the cat-selectbox
				'header_left'    =>	false,	// I  template to show left of the range-value, left-aligned (optional)
				'header_right'   =>	false,	// I  template to show right of the range-value, right-aligned (optional)
				'never_hide'     => True,	// I  never hide the nextmatch-line if less then maxmatch entries
				'lettersearch'   => false,	// I  show a lettersearch
				'start'          =>	0,		// IO position in list
				'order'          =>	'app_order',	// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'ASC',	// IO direction of the sort: 'ASC' or 'DESC'
				//'default_cols'   => 	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
				'csv_fields'     =>	false,	// I  false=disable csv export, true or unset=enable it with auto-detected fieldnames,
								//or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
			);
		}
		elseif(isset($content['nm']['rows']['up']))
		{
			list($app) = each($content['nm']['rows']['up']);
			unset($content['nm']['rows']['up']);
			$this->move_app(-1, $app);
			egw::invalidate_session_cache();
			egw::redirect_link('/index.php',array('menuaction'=>'admin.admin_applications.index'));
			exit;
		}
		elseif(isset($content['nm']['rows']['down']))
		{
			list($app) = each($content['nm']['rows']['down']);
			unset($content['nm']['rows']['down']);
			$this->move_app(1, $app);
			egw::invalidate_session_cache();
			egw::redirect_link('/index.php',array('menuaction'=>'admin.admin_applications.index'));
			exit;
		}
		elseif(isset($content['number']))
		{
			// number apps serially
			$order = array();
			foreach($GLOBALS['egw_info']['apps'] as $app)
			{
				//_debug_array(array($app['name'],$app['status'],$app['order']));
				if(($app['status'] == 1 || $app['status'] == 4) && $app['name']!='admin' && $app['name']!='manual')
				{
					$order[$app['id']] = $app['order'];
				}
			}
			asort($order);
			$app_ids = array_keys($order);
			//_debug_array($app_ids);
			foreach($app_ids as $pos => $app)
			{
				$app_name = $GLOBALS['egw']->applications->id2name($app);
				//echo "set $app_name ($app) to ".($pos + 1)."<br>";
				$newpos= ($pos+1)*5;
				$GLOBALS['egw']->db->update(self::TABLE,array('app_order' => $newpos),array('app_id' => $app),__LINE__,__FILE__);
				// set the egw_info->apps array as well
				if(isset($GLOBALS['egw_info']['apps'][$app_name]))
				{
					$GLOBALS['egw_info']['apps'][$app_name]['order'] = $newpos;
				}
			}
			egw::invalidate_session_cache();
			egw::redirect_link('/index.php',array('menuaction'=>'admin.admin_applications.index'));
			exit;
		}

		$tmpl = new etemplate('admin.applications');
		$tmpl->exec('admin.admin_applications.index',$content,$sel_options,$readonlys,array(
			'nm' => $content['nm'],
		));
	}

	/**
	 * Moves an application
	 *
	 * @param int $dir the direction to move: -1 up, 1 down
	 * @param int $app id of the app to move
	 */
	function move_app($dir,$app)
	{
		//echo "move_app($dir,$app,$order,$move_only)<br>";
		$order = array();
		foreach($GLOBALS['egw_info']['apps'] as $_app)
		{
			if(($_app['status'] == 1 || $_app['status'] == 4 ) && $app['name']!='admin' && $_app['name']!='manual')
			{
				$order[$_app['id']] = $_app['order'];
				//echo '<center>#'.$_app['id'].': '.$_app['title'].'->'.$_app['order'].'</center><br>';
			}
		}
		asort($order);
		//_debug_array($order);

		// switch positions
		$old_pos = $order[$app];
		$next_app = $this->find_next_key($order,$app,$dir == -1);
		$new_pos = $order[$next_app];
		if ($new_pos == $old_pos) $new_pos= $new_pos + $dir;
		$GLOBALS['egw']->db->update(self::TABLE,array('app_order' => $new_pos),array('app_id' => $app),__LINE__,__FILE__);
		$order[$app] = $new_pos;
		$GLOBALS['egw']->db->update(self::TABLE,array('app_order' => $old_pos),array('app_id' => $next_app),__LINE__,__FILE__);
		$order[$next_app] = $old_pos;
	}

	function find_next_key($array,$old_key,$reverse)
	{
		//error_log("find_next_key($old_key ".$GLOBALS['egw']->applications->id2name($old_key)." position:".$array[$old_key].",".var_dump($reverse).")");
		$next_key = false;
		$first = true;
		$neworder = $array[$old_key];
		foreach($array as $key => $value)
		{
			// remember the first entry
			if ($first === true)
			{
				//echo $key.' first app in order ('.$value.')<br>';
				$first = $key;
			}
			if(!$reverse)
			{
				//error_log( "down $value $value < $neworder && $value > ".$array[$old_key]);
				if(((int)$value < (int)$neworder && (int)$value > (int)$array[$old_key] && $key != $old_key) || ((int)$value > (int)$array[$old_key] && $next_key === false))
				{
					//error_log( "matching down $value");
					$next_key = $key;
					$neworder = $value;
					//echo "-match: $next_key ".$GLOBALS['egw']->applications->id2name($next_key)."<br>";
				}	
			}
			else
			{
				//error_log( "up $value $value > $neworder && $value < ".$array[$old_key]);
				if(($value > $neworder && $value < $array[$old_key] && $key != $old_key) || ($value < $array[$old_key] && $next_key === false))
				{
					//error_log( "matching up $value");
					$next_key = $key;
					$neworder = $value;
					//echo "-rmatch: $next_key ->".$GLOBALS['egw']->applications->id2name($next_key)."<br>";
				}	
			}
		}
		//error_log($next_key."->".$GLOBALS['egw']->applications->id2name($next_key).' as next key position:'.$array[$next_key]);
		return ($next_key === false ? $first : $next_key);
	}
}

