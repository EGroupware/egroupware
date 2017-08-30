<?php

namespace EGroupware\Api\Mail\EWS;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\EWS\Lib;

class Attachment
{

	function __construct() {
	}
    function loadAttachment( array $params = array() ) {
            $this->type = $params['type'];
            $this->filename = $params['filename'];
            $this->contents = $params['attachment'];
    }
    function getType() {
        return $this->type;
    }
    function getDispositionParameter( $var ) {
        if ( $var == 'filename' )
            return $this->filename;

        return '';
    }
    function getContents( $options = array()) {
        if ( $options['stream'] )
            return $this->contents;
        else
            return $this->_readStream( $this->contents );
    }
    protected function _readStream($fp, $close = false)
    {
        $out = '';

        if (!is_resource($fp)) {
            return $out;
        }

        rewind($fp);
        while (!feof($fp)) {
            $out .= fread($fp, 8192);
        }

        if ($close) {
            fclose($fp);
        }

        return $out;
    }
    public function getBytes($approx = false)
    {
        $bytes = 0;
        if ($this->contents) {
            fseek($this->_contents, 0, SEEK_END);
            $bytes = ftell($this->_contents);
        } 

        return intval($bytes);
    }

}
