<?php
/**
 * API - Categories
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @author Bettina Gille <ceb@phpgroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * Copyright (C) 2000, 2001 Joseph Engo, Bettina Gille
 * Copyright (C) 2002, 2003 Bettina Gille
 * Reworked 11/2005 by RalfBecker-AT-outdoor-training.de
 * Reworked 12/2008 by RalfBecker-AT-outdoor-training.de to operate only on a catergory cache, no longer the db direct
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage categories
 * @access public
 * @version $Id$
 */

use EGroupware\Api;

/**
 * class to manage categories in eGroupWare
 *
 * @deprecated use Api\Categories
 */
class categories extends Api\Categories
{
	/**
	 * @deprecated use categories::TABLE
	 * @var string
	 */
	public $table = self::TABLE;

	/**
	 * read a single category
	 *
	 * We use a shared cache together with id2name
	 *
	 * @deprecated use read($id) returning just the category array not an array with one element
	 * @param int $id id of category
	 * @return array|boolean array with one array of cat-data or false if cat not found
	 */
	static function return_single($id)
	{
		if (is_null(self::$cache)) self::init_cache();

		return isset(self::$cache[$id]) ? array(self::$cache[$id]) : false;
	}

	/**
	 * return into a select box, list or other formats
	 *
	 * @param string/array $format string 'select' or 'list', or array with all params
	 * @param string $type='' subs or mains
	 * @param int/array $selected - cat_id or array with cat_id values
	 * @param boolean $globals True or False, includes the global egroupware categories or not
	 * @deprecated use html class to create selectboxes
	 * @return string populated with categories
	 */
	function formatted_list($format,$type='',$selected = '',$globals = False,$site_link = 'site')
	{
		if(is_array($format))
		{
			$type = ($format['type']?$format['type']:'all');
			$selected = (isset($format['selected'])?$format['selected']:'');
			$self = (isset($format['self'])?$format['self']:'');
			$globals = (isset($format['globals'])?$format['globals']:True);
			$site_link = (isset($format['site_link'])?$format['site_link']:'site');
			$format = $format['format'] ? $format['format'] : 'select';
		}

		if (!is_array($selected))
		{
			$selected = explode(',',$selected);
		}

		if ($type != 'all')
		{
			$cats = $this->return_array($type,0,False,'','','',$globals);
		}
		else
		{
			$cats = $this->return_sorted_array(0,False,'','','',$globals);
		}

		if (!$cats) return '';

		if($self)
		{
			foreach($cats as $key => $cat)
			{
				if ($cat['id'] == $self)
				{
					unset($cats[$key]);
				}
			}
		}

		switch ($format)
		{
			case 'select':
				foreach($cats as $cat)
				{
					$s .= '<option value="' . $cat['id'] . '"';
					if (in_array($cat['id'],$selected))
					{
						$s .= ' selected="selected"';
					}
					$s .= '>'.str_repeat('&nbsp;',$cat['level']);
					$s .= $GLOBALS['egw']->strip_html($cat['name']);
					if (self::is_global($cat))
					{
						$s .= self::$global_marker;
					}
					$s .= '</option>' . "\n";
				}
				break;

			case 'list':
				$space = '&nbsp;&nbsp;';

				$s  = '<table border="0" cellpadding="2" cellspacing="2">' . "\n";

				foreach($cats as $cat)
				{
					$image_set = '&nbsp;';

					if (in_array($cat['id'],$selected))
					{
						$image_set = '<img src="' . EGW_IMAGES_DIR . '/roter_pfeil.gif">';
					}
					if (($cat['level'] == 0) && !in_array($cat['id'],$selected))
					{
						$image_set = '<img src="' . EGW_IMAGES_DIR . '/grauer_pfeil.gif">';
					}
					$space_set = str_repeat($space,$cat['level']);

					$s .= '<tr>' . "\n";
					$s .= '<td width="8">' . $image_set . '</td>' . "\n";
					$s .= '<td>' . $space_set . '<a href="' . $GLOBALS['egw']->link($site_link,'cat_id=' . $cat['id']) . '">'
						. $GLOBALS['egw']->strip_html($cat['name'])
						. '</a></td>' . "\n"
						. '</tr>' . "\n";
				}
				$s .= '</table>' . "\n";
				break;
		}
		return $s;
	}

	/**
	 * return category name for a given id
	 *
	 * @deprecated This is only a temp wrapper, use id2name() to keep things matching across the board. (jengo)
	 * @param int $cat_id
	 * @return string cat_name category name
	 */
	function return_name($cat_id)
	{
		return $this->id2name($cat_id);
	}
}
