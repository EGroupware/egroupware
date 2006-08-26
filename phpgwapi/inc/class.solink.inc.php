<?php
/**
 * API - Interapplicaton links BO layer
 *
 * Links have two ends each pointing to an entry, each entry is a double:
 * 	 - app   app-name or directory-name of an egw application, eg. 'infolog'
 * 	 - id    this is the id, eg. an integer or a tupple like '0:INBOX:1234'
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage link
 * @version $Id$
 */

/**
 * generalized linking between entries of eGroupware apps - SO layer
 *
 * All vars passed to this class get correct escaped to prevent query insertion.
 */
class solink
{
	/**
	 * Instance of the db-class
	 *
	 * @var egw_db
	 */
	var $db;
	/**
	 * Current user
	 *
	 * @var int
	 */
	var $user;
	/**
	 * Name of the links table
	 *
	 * @var string
	 */
	var $link_table = 'egw_links';
	/**
	 * Turns on debug-messages
	 *
	 * @var boolean
	 */
	var $debug;

	/**
	 * constructor
	 */
	function solink( )
	{
		$this->db     = clone($GLOBALS['egw']->db);
		$this->db->set_app('phpgwapi');
		$this->user   = $GLOBALS['egw_info']['user']['account_id'];
	}

	/**
	 * creats a link between $app1,$id1 and $app2,$id2
	 *
	 * @param string $app1 appname of 1. endpoint of the link
	 * @param string $id1 id in $app1
	 * @param string $app2 appname of 2. endpoint of the link
	 * @param string $id2 id in $app2
	 * @param string $remark='' Remark to be saved with the link (defaults to '')
	 * @param int $owner=0 Owner of the link (defaults to user)
	 * @return boolean/int False (for db or param-error) or link_id for success
	 */
	function link( $app1,$id1,$app2,$id2,$remark='',$owner=0,$lastmod=0 )
	{
		if ($this->debug)
		{
			echo "<p>solink.link('$app1',$id1,'$app2',$id2,'$remark',$owner)</p>\n";
		}
		if ($app1 == $app2 && $id1 == $id2 ||
		    $id1 == '' || $id2 == '' || $app1 == '' || $app2 == '')
		{
			return False;	// dont link to self or other nosense
		}
		if ($link = $this->get_link($app1,$id1,$app2,$id2))
		{
			if ($link['link_remark'] != $remark)
			{
				$this->update_remark($link['link_id'],$remark);
			}
			return $link['link_id'];	// link alread exist
		}
		if (!$owner)
		{
			$owner = $this->user;
		}
		return $this->db->insert($this->link_table,array(
				'link_app1'		=> $app1,
				'link_id1'		=> $id1,
				'link_app2'		=> $app2,
				'link_id2'		=> $id2,
				'link_remark'	=> $remark,
				'link_lastmod'	=> $lastmod ? $lastmod : time(),
				'link_owner'	=> $owner,
			),False,__LINE__,__FILE__) ? $this->db->get_last_insert_id($this->link_table,'link_id') : false;
	}
	
	/**
	 * update the remark of a link
	 *
	 * @param int $link_id link to update
	 * @param string $remark new text for the remark
	 * @return boolean true on success, else false
	 */
	function update_remark($link_id,$remark)
	{
		return $this->db->update($this->link_table,array(
				'link_remark'	=> $remark,
				'link_lastmod'	=> time(),
			),array(
				'link_id'	=> $link_id,
			),__LINE__,__FILE__);
	}

	/**
	 * returns array of links to $app,$id
	 *
	 * @param string $app appname 
	 * @param string $id id in $app
	 * @param string $only_app if set return only links from $only_app (eg. only addressbook-entries) or NOT from if $only_app[0]=='!'
	 * @param string $order defaults to newest links first
	 * @return array of links (only_app: ids) or empty array if no matching links found
	 */
	function get_links( $app,$id,$only_app='',$order='link_lastmod DESC' )
	{
		if ($this->debug)
		{
			echo "<p>solink.get_links($app,$id,$only_app,$order)</p>\n";
		}
		$links = array();

		$this->db->select($this->link_table,'*',$this->db->expression($this->link_table,'(',array(
					'link_app1'	=> $app,
					'link_id1'	=> $id,
				),') OR (',array(
					'link_app2'	=> $app,
					'link_id2'	=> $id,
				),')'
			),__LINE__,__FILE__,False,$order ? " ORDER BY $order" : '');

		$this->db->query($sql,__LINE__,__FILE__);

		if ($not_only = $only_app[0] == '!')
		{
			$only_app = substr($only_app,1);
		}
		while ($this->db->next_record())
		{
			$row = $this->db->Record;

			if ($row['link_app1'] == $app AND $row['link_id1'] == $id)
			{
				$link = array(
					'app'  => $row['link_app2'],
					'id'   => $row['link_id2']
				);
			}
			else
			{
				$link = array(
					'app'  => $row['link_app1'],
					'id'   => $row['link_id1']
				);
			}
			if ($only_app && $not_only == ($link['app'] == $only_app) ||
				 !$GLOBALS['egw_info']['user']['apps'][$link['app']])
			{
				continue;
			}
			$link['remark']  = $row['link_remark'];
			$link['owner']   = $row['link_owner'];
			$link['lastmod'] = $row['link_lastmod'];
			$link['link_id'] = $row['link_id'];

			$links[$row['link_id']] = $only_app && !$not_only ? $link['id'] : $link;
		}
		return $links;
	}
	
	/**
	 * returns data of a link
	 *
	 * @param ing/string $app_link_id > 0 link_id of link or app-name of link
	 * @param string $id='' id in $app, if no integer link_id given in $app_link_id
	 * @param string $app2='' appname of 2. endpoint of the link, if no integer link_id given in $app_link_id
	 * @param string $id2='' id in $app2, if no integer link_id given in $app_link_id
	 * @return array with link-data or False
	 */
	function get_link($app_link_id,$id='',$app2='',$id2='')
	{
		if ($this->debug)
		{
			echo "<p>solink.get_link('$app_link_id',$id,'$app2','$id2')</p>\n";
		}
		if ((int) $app_link_id > 0)
		{
			$where = array('link_id' => $app_link_id);
		}
		else
		{
			if ($app_link_id == '' || $id == '' || $app2 == '' || $id2 == '')
			{
				return False;
			}
			$vars2addslashes = array('app_link_id','id','app2','id2');
			foreach ($vars2addslashes as $var)
			{
				$$var = $this->db->db_addslashes($$var);
			}
			$where = $this->db->expression($this->link_table,'(',array(
					'link_app1'	=> $app_link_id,
					'link_id1'	=> $id,
					'link_app2'	=> $app2,
					'link_id2'	=> $id2,
				),') OR (',array(
					'link_app2'	=> $app_link_id,
					'link_id2'	=> $id,
					'link_app1'	=> $app2,
					'link_id1'	=> $id2,
				),')');
		}
		$this->db->select($this->link_table,'*',$where,__LINE__,__FILE__);

		if ($this->db->next_record())
		{
			if ($this->debug)
			{
				_debug_array($this->db->Record);
			}
			return $this->db->Record;
		}
		return False;
	}

	/**
	 * Remove link with $link_id or all links matching given params
	 *
	 * @param $link_id link-id to remove if > 0
	 * @param string $app='' app-name of links to remove
	 * @param string $id='' id in $app or '' remove all links from $app
	 * @param int $owner=0 account_id to delete all links of a given owner, or 0
	 * @param string $app2='' appname of 2. endpoint of the link
	 * @param string $id2='' id in $app2
	 * @return array with deleted links
	 */
	function &unlink($link_id,$app='',$id='',$owner=0,$app2='',$id2='')
	{
		if ($this->debug)
		{
			echo "<p>solink.unlink($link_id,$app,$id,$owner,$app2,$id2)</p>\n";
		}
		if ((int)$link_id > 0)
		{
			$where = array('link_id' => $link_id);
		}
		elseif ($app == '' AND $owner == '')
		{
			return 0;
		}
		else
		{
			if ($app != '' && $app2 == '')
			{
				$check1 = array('link_app1' => $app);
				$check2 = array('link_app2' => $app);
				if ($id != '')
				{
					$check1['link_id1'] = $id;
					$check2['link_id2'] = $id;
				}
				$where = $this->db->expression($this->link_table,'((',$check1,') OR (',$check2,'))');
			}
			elseif ($app != '' && $app2 != '')
			{
				$where = $this->db->expression($this->link_table,'(',array(
						'link_app1'	=> $app,
						'link_id1'	=> $id,
						'link_app2'	=> $app2,
						'link_id2'	=> $id2,
					),') OR (',array(
						'link_app1'	=> $app2,
						'link_id1'	=> $id2,
						'link_app2'	=> $app,
						'link_id2'	=> $id,
					),')');
			}
			if ($owner)
			{
				if ($app) $where = array($where);
				$where['link_owner'] = $owner;
			}
		}
		$this->db->select($this->link_table,'*',$where,__LINE__,__FILE__);
		$deleted = array();
		while (($row = $this->db->row(true)))
		{
			$deleted[] = $row;
		}			
		$this->db->delete($this->link_table,$where,__LINE__,__FILE__);

		return $deleted;
	}

	/**
	 * Changes ownership of all links from $owner to $new_owner
	 *
	 * This is needed when a user/account gets deleted
	 * Does NOT change the modification-time
	 *
	 * @param int $owner acount_id of owner to change
	 * @param int $new_owner account_id of new owner
	 * @return int number of links changed
	 */
	function chown($owner,$new_owner)
	{
		if ((int)$owner <= 0 || (int) $new_owner <= 0)
		{
			return 0;
		}
		$this->db->update($this->link_table,array('owner'=>$new_owner),array('owner'=>$owner),__LINE__,__FILE__);

		return $this->db->affected_rows();
	}
	
	/**
	 * Get all links from a given app's entries to an other app's entries, which both link to the same 3. app and id
	 *
	 * Example:
	 * I search all timesheet's linked to a given project and id(s), who are also linked to other entries,
	 * which link to the same project:
	 * 
	 * ($app='timesheet'/some id) <--a--> (other app/other id) <--b--> ($t_app='projectmanager'/$t_id=$pm_id)
	 *                  ^                                                                     ^   
	 *                  +---------------------------c-----------------------------------------+
	 * 
	 * bolink::get_3links('timesheet','projectmanager',$pm_id) returns the links (c) between the timesheet and the project, 
	 * plus the other app/id in the keys 'app3' and 'id3'
	 * 
	 * Please note / ToDo: 
	 * at the moment only those links are returned, who are initiated by $app1, means for whom link_app1=$app1
	 * 
	 * @param string $app app the returned links are linked on one side (atm. this must be link_app1!)
	 * @param string $target_app app the returned links other side link also to
	 * @param string/array $target_id=null id(s) the returned links other side link also to
	 * @return array with links from entries from $app to $target_app/$target_id plus the other (b) link_id/app/id in the keys 'link3'/'app3'/'id3'
	 */
	function get_3links($app,$target_app,$target_id=null)
	{
		$links = array();
		$this->db->select($this->link_table,'c.*,b.link_app1 AS app3,b.link_id1 AS id3,b.link_id AS link3',
			'a.link_app1='.$this->db->quote($app).' AND c.link_app2='.$this->db->quote($target_app).
			(!$target_id ? '' : $this->db->expression($this->link_table,' AND c.',array('link_id2' => $target_id))),
			__LINE__,__FILE__,false,'',false,0," a
			JOIN $this->link_table b ON a.link_id2=b.link_id1 AND a.link_app2=b.link_app1
			JOIN $this->link_table c ON a.link_id1=c.link_id1 AND a.link_app1=c.link_app1 AND a.link_id!=c.link_id AND c.link_app2=b.link_app2 AND c.link_id2=b.link_id2");
		while (($row = $this->db->row(true,'link_')))
		{
			$links[] = $row;
		}
		return $links;
	}
}
