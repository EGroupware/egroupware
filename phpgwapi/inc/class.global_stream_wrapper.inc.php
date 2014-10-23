<?php
/**
 * eGroupWare API: VFS - stream wrapper for global variables
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @version $Id$
 */

/**
 * eGroupWare API: stream wrapper for global variables. It makes variables available as streams.
 *
 * Original from an expample on php.net:
 * @see http://de.php.net/manual/en/function.stream-wrapper-register.php
 *
 * Use as global://varname (please note global://host/varname does NOT work, as parse_url() would return with a leading slash!)
 *
 * Streamwrapper is now mbstring.func_overload save.
 *
 * @todo: allow to use path to access arrays: global://varname/key1/key2 to access $string from $GLOBALS['varname'] = array('key1'=>array('key2'=>$string));
 */
class global_stream_wrapper
{
    private $pos;
    private $stream;
    private $name;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->stream = &$GLOBALS[$this->name=egw_vfs::parse_url($path,PHP_URL_HOST)];
        $this->pos = 0;
        if (!is_string($this->stream)) return false;
        return true;
    }

    public function stream_read($count)
    {
        $ret = self::_cut_bytes($this->stream, $this->pos, $count);
    	//error_log(__METHOD__."($count) this->pos=$this->pos, self::_bytes(this->stream)=".self::_bytes($this->stream)." self::_bytes(ret)=".self::_bytes($ret));
        $this->pos += self::_bytes($ret);
        return $ret;
    }

    public function stream_write($data)
    {
        $l=self::_bytes($data);
        $this->stream =
            self::_cut_bytes($this->stream, 0, $this->pos) .
            $data .
            self::_cut_bytes($this->stream, $this->pos += $l);
        return $l;
    }

    public function stream_tell()
    {
        return $this->pos;
    }

    public function stream_eof()
    {
        return $this->pos >= self::_bytes($this->stream);
    }

    public function stream_seek($offset, $whence)
    {
        $l=self::_bytes($this->stream);
        switch ($whence)
        {
            case SEEK_SET: $newPos = $offset; break;
            case SEEK_CUR: $newPos = $this->pos + $offset; break;
            case SEEK_END: $newPos = $l + $offset; break;
            default: return false;
        }
        $ret = ($newPos >=0 && $newPos <=$l);
        if ($ret) $this->pos=$newPos;
        return $ret;
    }

    public function stream_stat()
    {
    	if (!isset($this->stream))
    	{
    		return false;
    	}
    	return array(
			'ino'   => md5($this->name),
			'name'  => $this->name,
			'mode'  => 0100000,
			'size'  => bytes($this->stream),
			'uid'   => 0,
			'gid'   => 0,
			'mtime' => 0,
			'ctime' => 0,
			'nlink' => 1,
		);
    }

	/**
	 * are the string functions overloaded by their mbstring variants
	 *
	 * @var boolean
	 */
	private static $mbstring_func_overload;

	/**
	 * mbstring.func_overload safe strlen
	 *
	 * @param string &$data
	 * @return int
	 */
	private static function _bytes(&$data)
	{
		return self::$mbstring_func_overload ? mb_strlen($data,'ascii') : strlen($data);
	}

	/**
	 * mbstring.func_overload safe substr
	 *
	 * @param string &$data
	 * @param int $offset
	 * @param int $len
	 * @return string
	 */
	private static function _cut_bytes(&$data,$offset,$len=null)
	{
		if (is_null($len))
		{
			return self::$mbstring_func_overload ? mb_substr($data,$offset,self::_bytes($data),'ascii') : substr($data,$offset);
		}
		return self::$mbstring_func_overload ? mb_substr($data,$offset,$len,'ascii') : substr($data,$offset,$len);
	}

	/**
	 * Init the (static parts) of the stream-wrapper
	 *
	 */
	public static function init()
	{
		self::$mbstring_func_overload = @extension_loaded('mbstring') && (ini_get('mbstring.func_overload') & 2);

		stream_wrapper_register('global', 'global_stream_wrapper');
	}
}
global_stream_wrapper::init();
