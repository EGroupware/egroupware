<?php
/**
 * eGroupWare API: Find Iterator - classes to iterate over large directory trees
 * This is a rewrite of the original egw_vfs::find function, original author
 * Ralf Becker <RalfBecker-AT-outdoor-training.de>
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Andreas Stoeckel
 * @copyright (c) 2010 by Andreas Stoeckel
 * @version $Id$
 */

define("FILTER_NOFOLLOW", 2); //Display the element but do not follow
define("FILTER_OK", 1); //The filter has passed
define("FILTER_DISPLAY", -1); //The element filtered should not be returned to the caller of find, it should not be displayed
define("FILTER_ALL", -2); //The element didn't pass the filter

function translate_path($path, $isurl) {
	return $isurl ? $path : egw_vfs::PREFIX.parse_url($path, PHP_URL_PATH);
}

class egw_find_file
{
	private $isurl = false;
	private $need_mime = false;
	private $base_path = "";
	
	private $stat = null;
	private $mime = null;
	private $props = null;
	private $result = null;
	
	public $filename = ''; //< The filename including the file path, e.g. /home/user/example.file
	public $path = ''; //< The filename, e.g. example.file
	public $name = ''; //< The file path, e.g. /home/user/
	public $isdot = false;
	public $filterstate = FILTER_ALL;

	public function __construct($path, $isurl, $need_mime)
	{
		//Copy the given parameters
		$this->isurl = $isurl;
		$this->need_mime = $need_mime;
		$this->pair_result = $pair_result;
		$this->base_path = $path;
		
		//Set filename, path and path + name as this information will most likely
		//be needed
		$paths = $this->isurl ? explode('/', $path) : explode('/', parse_url($path, PHP_URL_PATH));
		$this->name = implode('/',$paths);
		$this->filename = array_pop($paths);
		$this->path = implode('/',$paths).'/';
		$this->is_dot = ($this->filename == '.') || ($this->filename == '..');
	}
	
	public function get_stat()
	{
		//Check whether the stat data has already been created, if not, create it
		if ($this->stat === null)
		{
			$this->stat = $this->isurl ? lstat($this->base_path) :
				egw_vfs::url_stat($this->base_path, STREAM_URL_STAT_LINK);
			
			//Remove numerical indices 0-12
			$this->stat = array_slice($this->stat, 13);
		};
		
		return $this->stat;
	}
	
	public function get_mime()
	{
		//Check if the mime data has already been read, if not, read it
		if ($this->mime === null)
			$res['mime'] = egw_vfs::mime_content_type($this->base_path);
			
		return $this->mime;
	}
	
	public function get_props()
	{
		//Check whether props has already been read, if not, read it
		if ($this->props === null)
		{
			$this->props = array(
				"isfile" => is_file($this->base_path),
				"isdir" => is_dir($this->base_path),
				"islink" => is_link($this->base_path),
			);
			
			if ($this->props['islink'])
				$this->props['linktarget'] = readlink($this->base_path);
		}
		
		return $this->props;
	}
	
	public function get_result($pair_result)
	{
		//Check whether the result has already been produced, if not, produce it
		if ($this->result === null)
		{
			//Check whether only the file name/path should be returned or the complete
			//resultset
			if ($pair_result)
			{
				//Copy the state and add some custom information
				$this->result = $this->get_stat();
				$this->result['path'] = $this->name;
				$this->result['name'] = $this->filename;
				if ($this->need_mime)
					$this->result['mime'] = $this->get_mime();
			}
			else
			{
				$this->result = $this->name;
			}
		}
		
		return $this->result;
	}
	
	public function translate_path()
	{
	  return translate_path($this->name, $this->isurl);
	}
	
}

/*
 * egw_directory_iterator_filter filters files based on the options set supplied during
 * construction. It is used by the RecursiveDirectoryIteratorWrapper and
 * FindIterator classes. Filtering is performed by calling the "filter" function
 * and passing an url_state array.
 */
class egw_directory_iterator_filter
{
	//Parsed options
	private $hidden = false;
	private $type = null;
	private $name_preg = null;
	private $path_preg = null;
	private $curkey = 0;
	private $lcur = null;
	private $uid = null;
	private $gid = null;
	private $ctime = null;
	private $mtime = null;
	private $cmin = null;
	private $mmin = null;
	private $size = null;
	private $empty = true;
	private $follow = false;
	private $mime = null;
	
	/*
	 * check_num is internally used to compare strings/numerical values.
	 * @param(value is an integer parameter)
	 * @param(argument may be a string or an integer. If argument is an integer
	 * the result gets true if argument equals value. If argument is a string, the
	 * first char, which may be '+' or '-' defines whether the result gets true if
	 * value is greater than argument (+) or when it is smaller than argument (-))	
	 */
	private static function check_num($value, $argument)
	{
		if (is_int($argument) && $argument >= 0 || $argument[0] != '-' && $argument[0] != '+')
			return $value == $argument;
		
		if ($argument < 0)
			return $value < abs($argument);
		
		return $value > (int) substr($argument,1);
	}
	
	public function __construct($options)
	{
		//Preprocess the parameters		
		$this->hidden = isset($options['hidden']) ? $options['hidden'] : false;
		$this->type = isset($options['type']) ? $options['type'] : null;
		$this->name_preg = isset($options['name_preg']) ? $options['name_preg'] : null;
		$this->path_preg = isset($options['path_preg']) ? $options['path_preg'] : null;
		
		$this->cmin = isset($options['cmin']) ? $options['cmin'] : null;
		$this->mmin = isset($options['mmin']) ? $options['mmin'] : null;
		$this->ctime = isset($options['ctime']) ? $options['ctime'] : null;
		$this->mtime = isset($options['mtime']) ? $options['mtime'] : null;
		
		$this->empty = isset($options['empty']) ? $options['empty'] : true;
		$this->size = isset($options['size']) ? $options['size'] : null;
		$this->follow = isset($options['follow']) ? $options['follow'] : false;
		
		$this->mime = isset($options['mime']) ? $options['mime'] : null;
		
		//Convert the (probably) given path/name filters to regular expressions
		if (isset($options['name']) && !isset($options['name_preg'])) // change from simple *,? wildcards to preg regular expression once
			$this->name_preg = '/^'.str_replace(array('\\?','\\*'),
				array('.{1}','.*'), preg_quote($options['name'])).'$/i';
		
		if (isset($options['path']) && !isset($options['preg_path'])) // change from simple *,? wildcards to preg regular expression once
			$this->path_preg = '/^'.str_replace(array('\\?','\\*'),
				array('.{1}','.*'), preg_quote($options['path'])).'$/i';
		
		//Translate username to uid
		if (!isset($options['uid']))
		{
			if (isset($options['user']))
			{
				$this->uid = $GLOBALS['egw']->accounts->name2id($options['user'],'account_lid','u');
			}
			elseif (isset($options['nouser']))
			{
				$this->uid = 0;
			}
		}
		else
		{
			$this->uid = $options['uid'];
		}
		
		//Translate groupid to gid
		if (!isset($options['gid']))
		{
			if (isset($options['group']))
			{
				$this->gid = abs($GLOBALS['egw']->accounts->name2id($options['group'],'account_lid','g'));
			}
			elseif (isset($options['nogroup']))
			{
				$this->gid = 0;
			}
		}
		else
		{
			$this->gid = $options['group'];
		}
	}
	
	public function filter($findfile)
	{
		if ($findfile)
		{
			//Exclude directory and parent directory
			if ($findfile->isdot)
				return FILTER_ALL;
			
			//Exclude hidden files			 
			if (!$this->hidden && (($findfile->filename[0] == '.') || ($findfile->filename == 'Thumbs.db')))
				return FILTER_ALL;
			
			//Read the file properties (like (is link etc.))
			$props = $findfile->get_props();
			
			//If this is a directory, we probably need to keep this entry to crawl
			//for files inside this directory. That's what FILTER_DISPLAY is for
			if ($props['isdir'] && (!($props['islink']) || $this->follow))
				$dir_filter = FILTER_DISPLAY; 
			else
				$dir_filter = FILTER_ALL;
			
			//Exclude files/directories based on the given regular expression
			if ($this->name_preg && !preg_match($this->name_preg, $findfile->filename) || 
				$this->path_preg && !preg_match($this->path_preg, $findfile->path))
				return $dir_filter;
			
			//Exclude directories/symlinks/files			
			if ($this->type && ($this->type == 'd') == !($props['isdir'] && !$props['islink']) ||
				(($this->type == 'F') && ($props['isdir'] || $props['islink'])))
				return $dir_filter;
			
			if (($this->cmin !== null) || ($this->mmin !== null) || 
				($this->ctime !== null) || ($this->mtime !== null) ||
				($this->size !== null) || (!$this->empty) || 
				($this->gid !== null) || ($this->uid !== null))
			{
				//Check user/group
				if (($this->gid !== null) && $stat['gid'] != $options['gid'] ||
					($this->uid !== null) && $stat['uid'] != $options['uid'])
				{
					return FILTER_ALL; // wrong user or group
				}
				
				//Retrieve the file stat
				$stat = $this->get_stat();
				
				//Excludes files where the creation/last modification timestamps is greater than
				//the given time
				if ($this->cmin && !self::check_num(round((time()-$stat['ctime'])/60), $this->cmin) ||
					$this->mmin && !self::check_num(round((time()-$stat['mtime'])/60), $this->mmin) ||
					$this->ctime && !self::check_num(round((time()-$stat['ctime'])/86400), $this->ctime) ||
					$this->mtime && !self::check_num(round((time()-$stat['mtime'])/86400), $this->mtime))
				{
					return $dir_filter;
				}
				
				//Check filesize
				if (($this->size !== null) && !self::check_num($stat['size'], $this->size) ||
					(!$this->empty && $stat['size'] <= 0)) //TODO Check this
				{
					return $dir_filter;
				}
			}
			
			//Check the mime type and subtype. As loading the mime type takes the
			//longest time, it is postponed as long as possible
			if ($this->mime !== null) {
				$mime = $findfile->get_mime();
				if ($options['mime'] != $mime)
				{
					list($type, $subtype) = explode('/', $this->mime);
					
					// no subtype (eg. 'image') --> check only the main type
					if ($sub_type || substr($this->mime, 0, strlen($type)+1) != $type.'/')
						return $dir_filter; // wrong mime-type
				}
			}
			
			//Return FILTER_OK, if the filters have passed		 
			return FILTER_OK;
		}
		else
		{
			return FILTER_ALL;
		}
	}
}

class egw_recursive_directory_iterator_wrapper implements RecursiveIterator
{
	private $base = "";
	private $exec = null;
	private $exec_params = null;
	private $filter = null;

	private $elements = null;
	private $index = 0;
	private $iterator = null;	
	private $follow = false;
	private $sortfunc;
	private $sort = false;
	private $url = false;
	private $needmime = false;
	
	private function fillelemarray()
	{
		//Fill the "elements" array with all filestates
		$this->elements = array();
		
		//Loop through each element of the recursive directory iterator
		foreach($this->iterator as $key => $value)
		{
			//Create a new egw_find_file class for each entry and filter it
			$findfile = new egw_find_file( 
				$this->iterator->getPath().'/'.$this->iterator->getFilename(),
				$this->url, $this->needmime);
			
			//Filter the element
			$filterresult = $this->filter->filter($findfile);
			if ($filterresult >= FILTER_DISPLAY)
			{
				$findfile->filterstate = $filterresult;
				$this->elements[] = $findfile;
			}
		}
		
		//Sort the elements
		if ($this->sort)
		{
			usort($this->elements, $this->sortfunc);
		}
	}

	public function __construct($iterator, &$filter, $follow, $url, $needmime, 
		$sort, $sortfunc)
	{
		$this->filter = $filter;
		$this->iterator = $iterator;
		if ($this->iterator->valid())
		{
			$this->base = $this->iterator->getPath();
		}
		$this->follow = $follow;
		$this->sortfunc = $sortfunc;
		$this->sort = $sort;
		$this->url = $url;
		$this->needmime = $needmime;
		
		//Get the element array. As this array only contains the current directory,
		//and subdirectories are first loaded when they are accessed, a lot of memory
		//is saved.
		$this->fillelemarray();
	}

	public function valid()
	{
		return $this->index < count($this->elements);
	}
	
	public function next()
	{
		$this->index++;
	}
	
	public function current()
	{
		return $this->elements[$this->index];
	}
	
	public function key()
	{
		return $this->elements[$this->index]['name'];
	}

	public function hasChildren()
	{
		$props = &$this->elements[$this->index]->get_props();
		
		//Only follow symlinks if we were ought to do so
		if ($props['isdir'] && !$props['isdot'] && (!$props['islink'] || $this->follow))
		{
			//Check whether the link points on a file
			if ($props['islink'])
			{
				$info = new SplFileInfo($props['linktarget']);
				//Only follow the symlink if the symlink points to a directory.
				//TODO: Recursion prevention?
				return ($info->isDir());
			}
			else
			{
				return true;
			}
		}
		return false;
	}

	public function getChildren()
	{
		$it = new RecursiveDirectoryIterator($this->elements[$this->index]->translate_path());
		return new egw_recursive_directory_iterator_wrapper($it, $this->filter,
			$this->follow, $this->url, $this->needmime, $this->sort,
			$this->sortfunc, false);
	}

	public function rewind()
	{
		$this->index = 0;
	}
}

class egw_find_iterator implements Iterator
{
	private $filter = null;
	private $follow = false;	
	private $iterator_stack = array();
	private $base = array();
	private $base_index = 0;
	private $current_cache = null;
	private $needs_next = false;
	private $rewind = false;
	private $pairs = false;

	private $numerical_sort = false;
	private $dirsontop = true;
	private $sort = 1;
	private $order = null; 

	private $index = 0;
	private $lower_limit = 0;
	private $upper_limit = null;
	private $mindepth = null;
	private $maxdepth = null;
	private $depth = false;
	private $url = false;
	private $needmime = false;

	private function push_root_iterator()
	{
		$root_iterator = new egw_recursive_directory_iterator_wrapper(
			new RecursiveDirectoryIterator($this->base[$this->base_index]),
				$this->filter, $this->follow, $this->url, $this->needmime,
				($this->order !== null) || $this->dirsontop, array($this, "sort"), true);
		array_push($this->iterator_stack, $root_iterator);
	}
	
	public function sort($a, $b)
	{
		//TODO restore, make it much faster :-)
		
		/*if ($this->dirsontop)
		{
			if (($a['isdir'] != $b['isdir']) && ($a['isdir'] || $b['isdir']))
				return $a['isdir'] ? -1:1;
		}
		
		if ($this->numerical_sort)
		{
			$res = $a[$this->order] == $b[$this->order] ? 0 : ($a[$this->order] > $b[$this->order] ? -1:1);
		}
		else
		{
			$res = strcasecmp($a[$this->order], $b[$this->order]);
		}
		
		//Always use name as second sort criteria
		if (($res == 0) && ($this->order != 'name'))
		{
			$res = strcasecmp($a['name'], $b['name']); 
		}*/
		
		return 0; //$res * $this->sort;
	}
	
	private function cur_iterator()
	{
		$s = count($this->iterator_stack);
		if ($s > 0)
		{
			return $this->iterator_stack[$s - 1];
		}
		else
		{
			return null;
		}
	}
	
	private function output_directory(&$findfile)
	{
		//If the element is visible, set $current_cache properly and return
		if ($findfile->filterstate >= FILTER_OK)
		{
			$this->index++;
			if (($this->index-1 >= $this->lower_limit) && 
				(($this->mindepth === null) || count($this->iterator_stack) >= $this->mindepth))
			{
				$this->current_cache = $findfile->get_result($this->pairs);
				return true;
			}
		}
		return false;
	}
	
	private function prefetch_current()
	{
		//If needs_next is true, a new element has been requested by calling the
		//"next()" function
		if ($this->needs_next)
		{
			//Reset $current_cache
			$this->needs_next = false;
			$this->current_cache = null;
			$this->rewind = false;
			
			$cur = $this->cur_iterator();
			while (($cur != null) && (!$this->upper_limit || ($this->index <= $this->upper_limit)))
			{
				$skip = false;
				if ($cur->valid()) 
				{
					//Get a copy of the current file state
					$findfile = $cur->current();
					
					//Add the children elements, if some exist
					if ($cur->hasChildren() && !($findfile->filterstate == FILTER_NOFOLLOW))
					{
						if (($this->maxdepth === null) || count($this->iterator_stack) < $this->maxdepth)
						{
							array_push($this->iterator_stack, $cur->getChildren());
							$skip = $this->depth;
						}
					}
					
					//Step the current element one further and get the (probably) new iterator
					if (!$skip)	
						$cur->next();
					$cur = $this->cur_iterator();
					
					if (!$skip && $this->output_directory($findfile))
						break;
				}
				
				//If the current iterator doesn't contain any valid element, we're at the
				//end of the current directory. Switch to the last directory
				if (!$cur->valid())
				{
					array_pop($this->iterator_stack);
					$cur = $this->cur_iterator();
					//If cur = null we're at the end of the current directory tree.
					//Check whether there was another directory specified
					if ($cur === null)
					{
						//Increment the base dir array index
						$this->base_index++;
						if ($this->base_index < count($this->base))
						{
							$this->push_root_iterator();
							$cur = $this->cur_iterator();
						}
					}
					else
					{
						//If the contents of the directory were listed before the directory itself
						//output the directory name when it was popped from the iterator stack
						if ($this->depth && $cur && $cur->valid())
						{
							$findfile = $cur->current();
							$cur->next();
							if ($this->output_directory($findfile))
								break;
						}
					}
				}
			}
		}
	}
	
	private function parseoptions(&$options)
	{
		//If options is not set, make it an empty array
		if (!$options)
			$options = array();
		
		//Create the filter object (passed to other objects by reference) and
		//parse some other options relevant for this base class		
		$this->filter = new egw_directory_iterator_filter($options);
		$this->follow = isset($options['follow']) ? $options['follow'] : false;
		$this->mindepth = isset($options['mindepth']) ? $options['mindepth'] : null;
		$this->maxdepth = isset($options['maxdepth']) ? $options['maxdepth'] : null;
		$this->depth = isset($options['depth']) ? $options['depth'] : false;
		$this->dirsontop = isset($options['dirsontop']) ? (boolean)$options['dirsontop'] : 
			(isset($options['maxdepth']) && $options['maxdepth']>0);
		$this->sort = isset($options['sort']) ? (($options['sort'] == 'ASC') ? 1 : -1) : 1;
		$this->order = isset($options['order']) ? $options['order'] : null;
		
		$this->numerical_sort = ($this->order == 'size' || $this->order == 'uid' ||
			$this->order == 'gid' || $this->order == 'mode' || $this->order == 'ctime' ||
			$this->order == 'mtime');
		
		$this->url = isset($options['url']) ? (boolean)$options['url'] : false;
		
		$this->needmime = isset($options['need_mime']) ? $options['need_mime'] : false;
		$this->needmime = $this->needmime || isset($options['mime']) || ($this->order == 'mime');
		
		//Retrieve the limit parameters
		if (isset($options['limit']))
		{
			$args = explode(',', $options['limit']);
			$count = $args[0];
			if (isset($args[1]))
			{
				$this->lower_limit = $args[1];
			}
			$this->upper_limit = $this->lower_limit + $count;
		}
	}
	
	public function __construct($base, $options=null, $pairs = false)
	{
		//_debug_array($options);
		$this->pairs = $pairs;
		
		$this->parseoptions($options);
		
		//Make sure that base is an array with numeric keys
		if (is_array($base))
		{
			$this->base = array_values($base);
		}
		else
		{
			$this->base[] = $base;
		}
		
		//Add the default url prefix to every path if url is not set.
		foreach($this->base as $key => $path)
			$this->base[$key] = translate_path($path, $this->url);

		$this->rewind();
	}
	
	public function current()
	{
		$this->prefetch_current();
		return $this->current_cache;
	}
	
	public function key()
	{
		$this->prefetch_current();
		if ($this->pairs)
		{
			return $this->current_cache->path;
		}
		else
		{
			return $this->index - 1 - $this->lower_limit;
		}
	}
	
	public function next()
	{
		$this->needs_next = true;
		$this->prefetch_current();
	}
	
	public function valid()
	{
		if (!$this->upper_limit || ($this->index <= $this->upper_limit))
		{
			$this->prefetch_current();
			return $this->current_cache != null;
		}
		else
		{
			return false;
		}
	}
	
	public function rewind()
	{
		if (!$this->rewind)
		{
			//Empty the iterator stack
			$this->iterator_stack = array();
			$this->current_cache = null;
			$this->needs_next = true;
			$this->index = 0;
			$this->base_index = 0;
			$this->rewind = true;

			//Create the root directory iterator
			$this->push_root_iterator();
		}
	}
}

