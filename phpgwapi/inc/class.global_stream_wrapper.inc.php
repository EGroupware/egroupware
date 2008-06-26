<?php
/**
 * eGroupWare API: VFS - stream wrapper interface
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @version $Id$
 */

/**
 * eGroupWare API: stream wrapper for global variables,
 *
 * Original from an expample on php.net:
 * @see http://de.php.net/manual/en/function.stream-wrapper-register.php
 *
 * Use as global://varname
 *
 * @todo: allow to use path to access arrays: global://varname/key1/key2 to access $string from $GLOBALS['varname'] = array('key1'=>array('key2'=>$string));
 */
class global_stream_wrapper
{
    private $pos;
    private $stream;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->stream = &$GLOBALS[parse_url($path,PHP_URL_HOST)];
        $this->pos = 0;
        if (!is_string($this->stream)) return false;
        return true;
    }

    public function stream_read($count)
    {
        $p=&$this->pos;
        $ret = substr($this->stream, $this->pos, $count);
        $this->pos += strlen($ret);
        return $ret;
    }

    public function stream_write($data)
    {
        $l=strlen($data);
        $this->stream =
            substr($this->stream, 0, $this->pos) .
            $data .
            substr($this->stream, $this->pos += $l);
        return $l;
    }

    public function stream_tell()
    {
        return $this->pos;
    }

    public function stream_eof()
    {
        return $this->pos >= strlen($this->stream);
    }

    public function stream_seek($offset, $whence)
    {
        $l=strlen($this->stream);
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
}
stream_wrapper_register('global', 'global_stream_wrapper');