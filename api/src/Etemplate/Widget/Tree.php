<?php
/**
 * EGroupware - eTemplate serverside tree widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2012-18 Nathan Gray
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

/**
 * eTemplate tree widget
 *
 * Example initialisation of tree via $sel_options array:
 *
 *  use Api\Etemplate\Widget\Tree;
 *
 * 	$sel_options['tree'] = array(
 * 		Tree::ID => 0, Tree::CHILDREN => array(	// ID of root has to be 0!
 * 			array(
 *				Tree::ID => '/INBOX',
 *				Tree::LABEL => 'INBOX', Tree::TOOLTIP => 'Your inbox',
 *				Tree::OPEN => 1, Tree::IMAGE_FOLDER_OPEN => 'kfm_home.png', Tree::IMAGE_FOLDER_CLOSED => 'kfm_home.png',
 *				Tree::CHILDREN => array(
 *					array(Tree::ID => '/INBOX/sub', Tree::LABEL => 'sub', Tree::IMAGE_LEAF => 'folderClosed.gif'),
 *					array(Tree::ID => '/INBOX/sub2', Tree::LABEL => 'sub2', Tree::IMAGE_LEAF => 'folderClosed.gif'),
 *				),
 *				Tree::CHECKED => true,
 * 			),
 * 			array(
 *				Tree::ID => '/user',
 *				Tree::LABEL => 'user',
 *				Tree::CHILDREN => array(
 *	 				array(Tree::ID => '/user/birgit', Tree::LABEL => 'birgit', Tree::IMAGE_LEAF => 'folderClosed.gif'),
 *					array(Tree::ID => '/user/ralf', Tree::LABEL => 'ralf', Tree::AUTOLOAD_CHILDREN => 1),
 *				),
 *				Tree::NOCHECKBOX => true
 * 			),
 * 	));
 *
 * Please note:
 * - for more info see class constants below
 * - all images have to be under url specified in attribute "image_path", default $websererUrl/api/templates/default/image/dhtmlxtree
 * - you can use attribute "std_images" to supply different standard images from default
 *	[ "leaf.gif", "folderOpen.gif", "folderClosed.gif" ]
 * - images can also be specified as standard "app/image" string, client-side will convert them to url relativ to image_path
 * - json autoloading uses identical data-structur and should use Api\Etemplate\Widget\Tree::send_quote_json($data)
 *   to send data to client, as it takes care of html-encoding of node text
 * - if autoloading is enabled, you have to validate returned results yourself, as widget does not know (all) valid id's
 */
class Tree extends Etemplate\Widget
{
	/**
	 * key for id of node, has to be unique, eg. a path, nummerical id is allowed too
	 * if of root has to be 0!
	 */
	const ID = 'id';
	/**
	 * key for label of node
	 */
	const LABEL = 'text';
	/**
	 * key for tooltip / title of node
	 */
	const TOOLTIP = 'tooltip';
	/**
	 * key for array of children (not json object: numerical keys 0, 1, ...)
	 */
	const CHILDREN = 'item';
	/**
	 * key if children exist and should be autoloaded, set value to 1
	 */
	const AUTOLOAD_CHILDREN = 'child';
	/**
	 * key of relative url of leaf image or standard "app/image" string
	 * used if node has not [AUTOLOAD_]CHILDREN set
	 */
	const IMAGE_LEAF = 'im0';
	/**
	 * key of relative url of open folder image or standard "app/image" string
	 * used if node has [AUTOLOAD_]CHILDREN set AND is open
	 */
	const IMAGE_FOLDER_OPEN = 'im1';
	/**
	 * key of relative url of closed folder image or standard "app/image" string
	 * used if node has [AUTOLOAD_]CHILDREN set AND is closed
	 */
	const IMAGE_FOLDER_CLOSED = 'im2';
	/**
	 * key of flag if folder is open, default folder is closed
	 */
	const OPEN = 'open';

	/**
	 * key to check checkbox if exists (in case of three-state checkboxes values can be:0 unchecked- 1 - checked or -1 - unsure)
	 */
	const CHECKED = 'checked';

	/**
	 * key to instruct the component not to render checkbox for the related item, optional
	 */
	const NOCHECKBOX = 'nocheckbox';

    /**
     * key to add a custum class to the tree item
     */
    const CLASS_LIST = 'class';

	/**
	 * Container which can be opened/closed, but not selected
	 */
	const UNSELECTABLE = 'unselectable';

	/**
	 * Constructor
	 *
	 * @param string|\XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws Api\Exception\WrongParameter
	 */
	public function __construct($xml = '')
	{
		$this->bool_attr_default += array(
			'multiple' => false,
			'highlighting' => true,
		);

		if($xml) {
			parent::__construct($xml);
		}
	}

	/**
	 * Parse and set extra attributes from xml in template object
	 *
	 * Reimplemented to parse our different attributes
	 *
	 * @param string|\XMLReader $xml
	 * @param boolean $cloned =true true: object does NOT need to be cloned, false: to set attribute, set them in cloned object
	 * @return Etemplate\Widget current object or clone, if any attribute was set
	 */
	public function set_attrs($xml, $cloned=true)
	{
		$this->attrs['type'] = $xml->localName;
		parent::set_attrs($xml, $cloned);

		// adaption for <et2-tree(-cat)(-multiple) --> <tree(-cat) multiple="..."
		$parts = explode('-', $this->type);
		if (($key = array_search('multiple', $parts)))
		{
			$this->attrs['multiple'] = true;
			unset($parts[$key]);
		}
		if ($parts[0] === 'et2')
		{
			array_shift($parts);
		}
		$this->type = implode('-', $parts);
	}

	/**
	 * Send data as json back to tree
	 *
	 * Basically sends a Content-Type and echos json encoded $data and exit.
	 *
	 * As text parameter accepts html in tree, we htmlencode it here!
	 *
	 * @param array $data
	 */
	public static function send_quote_json(array $data)
	{
		// switch regular JSON response handling off
		Api\Json\Request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);

		exit;
	}

	/**
	 * Check if given $id is one of given tree
	 *
	 * @param string $id needle
	 * @param array $item haystack with values for attributes 'id' and 'item' (children)
	 * @return boolean true if $id is contained in $item or it's children, false otherwise
	 */
	public static function in_tree($id, array $item)
	{
		if ((string)$id === (string)$item['id'])
		{
			return true;
		}
		foreach((array)$item['item'] as $child)
		{
			if (self::in_tree($id, $child)) return true;
		}
		return false;
	}

	/**
	 * Check if given $id is cat_id attribute of one of given array members
	 *
	 * @param int $id
	 * @param array $cats
	 * @return boolean
	 */
	public static function in_cats($id, array $cats)
	{
		return (boolean)array_filter($cats, function($cat) use($id)
		{
			return is_array($cat) && ($cat['value'] == $id || (
					!empty($cat['children']) && is_array($cat['children']) && static::in_cats($id, $cat['children'])
				));
		});
	}

	const UNAVAILABLE_CAT_POSTFIX = '-unavailable';

	/**
	 * Validate input
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);

		$ok = true;
		if (!$this->is_readonly($cname, $form_name))
		{
			$unavailable_name = $form_name . self::UNAVAILABLE_CAT_POSTFIX;
			$unavailable_values = (array)self::$request->preserv[$unavailable_name];
			$value = $value_in = self::get_array($content, $form_name);

			// we can not validate if autoloading is enabled
			if (!$this->attrs['autoloading'])
			{
				$options = empty($this->attrs['options']) ? [] : explode(',', $this->attrs['options']);
				$allowed = $this->attrs['multiple'] || empty($options[0]) || is_numeric($options[0]) ? [] : [[
					'id' => '',
					'label' => $options[0],
				]];
				$allowed += self::selOptions($form_name);
				foreach((array) $value as $val)
				{
					if(in_array($val, $unavailable_values))
					{
						continue;
					}
					if ($this->type == 'tree-cat' && !($this->attrs['multiple'] && !$val) && !self::in_cats($val, $allowed) ||
						$this->type == 'tree' && !self::in_tree($val, $allowed))
					{
						self::set_validation_error($form_name,lang("'%1' is NOT allowed%2)!", $val,
																   $this->type == 'tree-cat' ? " ('" . implode("','", array_column($allowed, 'value')) . ')' : ''
						),                         ''
						);
						$val = '';
						break;
					}
				}
			}
			// return values for cat-tree as string, but not for regular tree as it can have id's with comma!
			if (is_array($value) && $this->type == 'tree-cat')
			{
				// unavailable cats need to be merged in again
				if (isset(self::$request->preserv[$unavailable_name]))
				{
					if ($this->attrs['multiple'])
					{
						$value = array_merge($value, (array)self::$request->preserv[$unavailable_name]);
					}
					elseif(!$value)	// for single cat, we only restore unavailable one, if no other was selected
					{
						$value = self::$request->preserv[$unavailable_name];
					}
				}
				$value = implode(',',$value);
			}
			if ($ok && $value === '' && $this->required)
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!',$value),'');
			}
			$valid =& self::get_array($validated, $form_name, true);
			if (true) $valid = $value;
			//error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value).', allowed='.array2string($allowed));
		}
	}

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		$form_name = self::form_name($cname, $this->id);

		if (($templated_path = self::templateImagePath($this->attrs['imagePath'] ?? $this->attrs['image_path'] ?? null)) !== ($this->attrs['imagePath'] ?? $this->attrs['image_path'] ?? null))
		{
			self::setElementAttribute($form_name, 'imagePath', $this->attrs['imagePath'] = $templated_path);
			self::setElementAttribute($form_name, 'image_path', $this->attrs['image_path'] = $templated_path);
			//error_log(__METHOD__."() setting templated image-path for $form_name: $templated_path");
		}

		if (empty(self::$request->sel_options[$form_name])) self::$request->sel_options[$form_name] = [];
		if (!empty($this->attrs['type']))
		{
			// += to keep further options set by app code
			self::$request->sel_options[$form_name] += self::typeOptions($this->attrs['type'], $this->attrs['options'] ?? null,
				$no_lang, $this->attrs['readonly'] ?? null, self::get_array(self::$request->content, $form_name), $form_name);

			// if no_lang was modified, forward modification to the client
			if (!isset($this->attr['no_lang']) || $no_lang != $this->attr['no_lang'])
			{
				self::setElementAttribute($form_name, 'no_lang', $no_lang);
			}
		}
	}

	/**
	 * Get template specific image path
	 *
	 * @param string $image_path =null default path to use, or empty to use default of /api/templates/default/images/dhtmlxtree
	 * @return string templated url if available, otherwise default path
	 */
	public static function templateImagePath($image_path=null)
	{
		$webserver_url = $GLOBALS['egw_info']['server']['webserver_url'];
		if (empty($image_path))
		{
			$image_path = $webserver_url.'/api/templates/default/images/dhtmlxtree/';
		}
		// check if we have template-set specific image path
		if ($webserver_url && $webserver_url != '/')
		{
			list(,$image_path) = explode($webserver_url, $image_path, 2);
		}
		$templated_path = strtr($image_path, array(
			'/api/templates/default' => $GLOBALS['egw']->framework->template_dir,
			'/default/' => '/'.$GLOBALS['egw']->framework->template.'/',
		));
		if (file_exists(EGW_SERVER_ROOT.$templated_path))
		{
			return ($webserver_url != '/' ? $webserver_url : '').$templated_path;
			//error_log(__METHOD__."() setting templated image-path for $form_name: $templated_path");
		}
		return ($webserver_url != '/' ? $webserver_url : '').$image_path;
	}

	/**
	 * Return image relative to trees image-path
	 *
	 * @param string $image url of image, eg. from Api\Image::find($image, $app)
	 * @return string path relative to image-path, to use when returning tree data eg. via json
	 */
	public static function imagePath($image)
	{
		static $image_path=null;
		if (!isset($image_path)) $image_path = self::templateImagePath ();

		$parts = explode('/', $image_path);
		$image_parts   = explode('/', $image);

		// remove common parts
		while(isset($parts[0]) && $parts[0] === $image_parts[0])
		{
			array_shift($parts);
			array_shift($image_parts);
		}
		// add .. for different parts, except last image part
		$url = implode('/', array_merge(array_fill(0, count($parts)-1, '..'), $image_parts));

		//error_log(__METHOD__."('$image') image_path=$image_path returning $url");
		return $url;
	}

	/**
	 * Get options from $sel_options array for a given selectbox name
	 *
	 * @param string $name
	 * @param boolean $no_lang=false value of no_lang attribute
	 * @return array
	 */
	public static function selOptions($name)
	{
		$options = array();
		if (isset(self::$request->sel_options[$name]) && is_array(self::$request->sel_options[$name]))
		{
			$options += self::$request->sel_options[$name];
		}
		else
		{
			$name_parts = explode('[',str_replace(']','',$name));
			if (count($name_parts))
			{
				$org_name = $name_parts[count($name_parts)-1];
				if (isset(self::$request->sel_options[$org_name]) && is_array(self::$request->sel_options[$org_name]))
				{
					$options += self::$request->sel_options[$org_name];
				}
				elseif (isset(self::$request->sel_options[$name_parts[0]]) && is_array(self::$request->sel_options[$name_parts[0]]))
				{
					$options += self::$request->sel_options[$name_parts[0]];
				}
			}
		}
		if (isset(self::$request->content['options-'.$name]))
		{
			$options += self::$request->content['options-'.$name];
		}
		//error_log(__METHOD__."('$name') returning ".array2string($options));
		return $options;
	}

	/**
	 * Fetch options for certain tree types
	 *
	 * @param string $widget_type
	 * @param string $legacy_options options string of widget
	 * @param boolean $no_lang=false initial value of no_lang attribute (some types set it to true)
	 * @param boolean $readonly =false
	 * @param mixed $value =null value for readonly
	 * @param string $form_name form-name of widget, used to store unavailable cats
	 * @return array with value => label pairs
	 */
	public static function typeOptions($widget_type, $legacy_options, &$no_lang=false, $readonly=false, $value=null, $form_name=null)
	{
		list($rows,$type,$type2,$type3) = explode(',', $legacy_options)+[null,null,null,null];

		$no_lang = false;
		$options = array();
		switch ($widget_type)
		{
			case 'et2-tree-cat':
			case 'et2-tree-cat-multiple':
			case 'tree-cat':	// !$type == globals cats too, $type2: extraStyleMultiselect, $type3: application, if not current-app, $type4: parent-id, $type5=owner (-1=global),$type6=show missing
				if ($readonly)  // for readonly we dont need to fetch all cat's, nor do we need to indent them by level
				{
					$cell['no_lang'] = True;
					foreach(is_array($value) ? $value : (strpos($value,',') !== false ? explode(',',$value) : array($value)) as $id)
					{
						if ($id) $cell['sel_options'][$id] = stripslashes($GLOBALS['egw']->categories->id2name($id));
					}
					break;
				}
				if (!$type3 || $type3 === $GLOBALS['egw']->categories->app_name)
				{
					$categories =& $GLOBALS['egw']->categories;
				}
				else    // we need to instanciate a new cat object for the correct application
				{
					$categories = new Api\Categories('',$type3);
				}
				$cat2path=array();

				static::processCategory(0, $options, $categories, !$type, $cat2path);
				// change cat-ids to pathes and preserv unavailible cats (eg. private user-cats)
				if ($value)
				{
					$pathes = $unavailable = array();
					foreach(is_array($value) ? $value : explode(',',$value) as $cat)
					{
						if(in_array($cat, $cat2path))
						{
							$pathes[] = $cat;
						}
						else
						{
							$unavailable[] = $cat;
						}
					}
					// unavailable cats need to be stored, so we can merge them in again in validate
					if ($unavailable && $form_name)
					{
						// unavailable cats need to be merged in again
						$unavailable_name = $form_name.self::UNAVAILABLE_CAT_POSTFIX;
						self::$request->preserv[$unavailable_name] = $unavailable;
					}
					$value = $rows || substr($widget_type, -9) === '-multiple' ? $pathes : $pathes[0];
				}
				$cell['size'] = $rows.($type2 ? ','.$type2 : '');
				$no_lang = True;
				break;
		}

		//error_log(__METHOD__."('$widget_type', '$legacy_options', no_lang=".array2string($no_lang).', readonly='.array2string($readonly).", value=$value) returning ".array2string($options));
		return $options;
	}

	protected static function processCategory($cat_id, &$options, &$categories, $globals, &$cat_id_list)
	{
		foreach((array)$categories->return_array($cat_id ? 'subs' : 'mains', 0, false, '', 'ASC', 'name', $globals, $cat_id) as $cat)
		{
			$category = static::formatCategory($cat, $categories);
			$cat_id_list[] = $cat['id'];
			if(!empty($cat['children']))
			{
				unset($category['children']);
				static::processCategory($cat['id'], $category['children'], $categories, $globals, $cat_id_list);
			}
			$options[] = $category;
		}
	}


	public static function formatCategory($cat, &$categories_object)
	{
		$s = stripslashes($cat['name']);

		if($cat['app_name'] == 'phpgw' || $cat['owner'] == '-1')
		{
			$s .= ' ♦';
		}

		// 1D array
		$category = $cat + array(
				// Legacy
				'text'  => $s,
				'path'  => $categories_object->id2name($cat['id'], 'path'),

				//Client side search interface
				'value' => $cat['id'],
				'label' => $s,
				'icon'  => $cat['data']['icon'] ?? '',
				'title' => $cat['description'],
				'class' => "cat_${cat['id']}"
			);
		if(!empty($cat['children']))
		{
			$category['hasChildren'] = true;
		}
		return $category;
	}

	/**
	 * Fix userdata as understood by tree
	 *
	 * @param array $data
	 * @return array
	 */
	public static function fixUserdata(array $data)
	{
		// store link as userdata, maybe we should store everything not directly understood by tree this way ...
		foreach(array_diff_key($data, array_flip([
			self::ID, self::LABEL, self::TOOLTIP, self::IMAGE_LEAF, self::IMAGE_FOLDER_OPEN, self::IMAGE_FOLDER_CLOSED,
			self::CHILDREN, self::AUTOLOAD_CHILDREN, 'select', self::OPEN, 'call', self::UNSELECTABLE,
		])) as $name => $content)
		{
			$data['userdata'][] = array(
				'name' => $name,
				'content' => $content,
			);
			unset($data[$name]);
		}
		return $data;
	}


	/**
	 * Get list of all groups as tree, taking container into account, if enabled
	 *
	 * @param string $root root for building tree-IDs, "" for just using IDs, no path
	 * @param ?callable $check function to check if group should be added
	 * @return array[] with tree-children, groups have IDs $root/$account_id (independent of container!), while container use $root/md5($container_name)
	 */
	public static function groups(string $root='/groups', ?callable $check=null)
	{
		if ($root) $root = rtrim($root, '/').'/';

		$children = [];
		foreach(Api\Accounts::getInstance()->search(array(
			'type' => 'groups',
			'order' => 'account_lid',
			'sort' => 'ASC',
			'start' => false,   // to NOT limit number of returned groups
		)) as $group)
		{
			if ($check && !$check($group)) continue;
			if (($container_name = Api\Accounts::container($group)))
			{
				foreach($children as &$container)
				{
					if ($container[Tree::LABEL] === $container_name) break;
				}
				if ($container[Tree::LABEL] !== $container_name)
				{
					$children[] = self::fixUserdata([
						Tree::LABEL => $container_name,
						Tree::ID => $root.md5($container_name),
						Tree::IMAGE_FOLDER_OPEN => Api\Image::find('api', 'dhtmlxtree/folderOpen'),
						Tree::IMAGE_FOLDER_CLOSED => Api\Image::find('api', 'dhtmlxtree/folderClosed'),
						Tree::UNSELECTABLE => true,
						Tree::CHILDREN => [],
					]);
					$container =& $children[count($children)-1];
				}
				$container[Tree::CHILDREN][] = self::fixUserdata([
					Tree::LABEL => $group['account_lid'],
					Tree::TOOLTIP => $group['account_description'],
					Tree::ID => $root.$group['account_id'],
					Tree::IMAGE_LEAF => Api\Image::find('addressbook', 'group'),
				]);
			}
			else
			{
				$children[] = self::fixUserdata([
					Tree::LABEL => $group['account_lid'],
					Tree::TOOLTIP => $group['account_description'],
					Tree::ID => $root.$group['account_id'],
					Tree::IMAGE_LEAF => Api\Image::find('addressbook', 'group'),
				]);
			}
		}
		// we need to sort (again), otherwise the containers would not be alphabetic sorted (Groups are already)
		uasort($children, static function ($a, $b) {
			return strnatcasecmp($a[Tree::LABEL], $b[Tree::LABEL]);
		});
		return $children;
	}

	/**
	 * Attribute Tree::Children='item' has to be an array (keys: 0, 1, ...), not object/associate array
	 *
	 * @param array $items
	 */
	public static function stripChildrenKeys(array &$items)
	{
		$items = array_values($items);
		foreach($items as &$item)
		{
			if (is_array($item) && isset($item[self::CHILDREN]))
			{
				self::stripChildrenKeys($item[self::CHILDREN]);
			}
		}
	}
}