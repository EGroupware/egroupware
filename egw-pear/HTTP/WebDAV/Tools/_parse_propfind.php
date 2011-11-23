<?php
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Hartmut Holzgraefe <hholzgra@php.net>                       |
// |          Christian Stocker <chregu@bitflux.ch>                       |
// +----------------------------------------------------------------------+
//
// $Id: _parse_propfind.php,v 1.4 2006/10/10 11:53:17 hholzgra Exp $
//

/**
 * helper class for parsing PROPFIND request bodies
 *
 * @package HTTP_WebDAV_Server
 * @author Hartmut Holzgraefe <hholzgra@php.net>
 * @version @package-version@
 */
class _parse_propfind
{
    /**
     * success state flag
     *
     * @var bool
     * @access public
     */
    var $success = false;

    /**
     * found properties are collected here
     *
     * @var array
     * @access public
     */
    var $props = false;

    /**
     * found (CalDAV) filters are collected here
     *
     * @var array
     * @access public
     */
    var $filters = false;

    /**
     * found other tags, eg. CalDAV calendar-multiget href's
     *
     * @var array
     * @access public
     */
    var $other = false;

    /**
     * what we are currently parsing: props or filters
     *
     * @var array
     * @access private
     */
    var $use = 'props';

    /**
     * Root tag, usually 'propfind' for PROPFIND, but can be eg. 'calendar-query' or 'calendar-multiget' for CalDAV REPORT
     *
     * @var array with keys 'name' and 'ns'
     */
    var $root;

    /**
     * internal tag nesting depth counter
     *
     * @var int
     * @access private
     */
    var $depth = 0;

    /**
     * On return whole request, if $store_request == true was specified in constructor
     *
     * @var string
     */
    var $request;

    /**
     * constructor
     *
     * @access public
     * @param string $path
     * @param boolean $store_request=false if true whole request data will be made available in $this->request
     */
    function _parse_propfind($path, $store_request=false)
    {
        // success state flag
        $this->success = true;

        // property storage array
        $this->props = array();

        // internal tag depth counter
        $this->depth = 0;

        // remember if any input was parsed
        $had_input = false;

        // open input stream
        $f_in = fopen($path, "r");
        if (!$f_in) {
            $this->success = false;
            return;
        }

        // create XML parser
        $xml_parser = xml_parser_create_ns("UTF-8", " ");

        // set tag and data handlers
        xml_set_element_handler($xml_parser,
                                array(&$this, "_startElement"),
                                array(&$this, "_endElement"));

		xml_set_character_data_handler($xml_parser,
            array(&$this,'_charData')
        );

        // we want a case sensitive parser
        xml_parser_set_option($xml_parser,
                              XML_OPTION_CASE_FOLDING, false);

        // parse input
        while ($this->success && !feof($f_in)) {
            $line = fgets($f_in);
            if ($store_request) $this->request .= $line;
            if (is_string($line)) {
                $had_input = true;
                $this->success &= xml_parse($xml_parser, $line, false);
            }
        }

        // finish parsing
        if ($had_input) {
            $this->success &= xml_parse($xml_parser, "", true);
        }

        // free parser
        xml_parser_free($xml_parser);

        // close input stream
        fclose($f_in);

        // if no input was parsed it was a request
        if(!count($this->props)) $this->props = "all"; // default
    }


    /**
     * start tag handler
     *
     * @access private
     * @param  resource  parser
     * @param  string    tag name
     * @param  array     tag attributes
     */
    function _startElement($parser, $name, $attrs)
    {
        // name space handling
        if (strstr($name, " ")) {
            list($ns, $tag) = explode(" ", $name);
            if ($ns == "")
                $this->success = false;
        } else {
            $ns  = "";
            $tag = $name;
        }

        // record root tag
        if ($this->depth == 0) {
        	$this->root = array('name' => $tag, 'xmlns' => $ns);
        }

        // special tags at level 1: <allprop> and <propname>
        if ($this->depth == 1) {
         	$this->use = 'props';
            switch ($tag)
            {
            	case "allprop":
                	$this->props = "all";
					break;
            	case "propname":
               		$this->props = "names";
               		break;
            	case 'prop':
           			break;
            	case 'filter':
            		$this->use = 'filters';
            		break;
            	default:
            		$this->use = 'other';
            		break;
            }
        }

        // requested properties are found at level 2
        // CalDAV filters can be at deeper levels too and we need the attrs, same for other tags (eg. multiget href's)
        if ($this->depth == 2 || $this->use == 'filters' && $this->depth >= 2 || $this->use == 'other') {
            $prop = array("name" => $tag);
            if ($ns)
                $prop["xmlns"] = $ns;
            if ($this->use != 'props') {
            	$prop['attrs'] = $attrs;
            	$prop['depth'] = $this->depth;
            }
         	// this can happen if we have allprop and prop in one propfind:
        	// <allprop /><prop><blah /></prop>, eg. blah is not automatic returned by allprop
            if (!is_array($this->{$this->use}) && $this->{$this->use}) $this->{$this->use} = array($this->{$this->use});
            $this->{$this->use}[] = $prop;
        }

        // increment depth count
        $this->depth++;
    }


    /**
     * end tag handler
     *
     * @access private
     * @param  resource  parser
     * @param  string    tag name
     */
    function _endElement($parser, $name)
    {
        // here we only need to decrement the depth count
        $this->depth--;
    }


    /**
     * char data handler for non prop tags, eg. href's in CalDAV multiget, or filter contents
     *
     * @access private
     * @param  resource  parser
     * @param  string    character data
     */
    function _charData($parser, $data)
    {
        if ($this->use != 'props' && ($n = count($this->{$this->use})) && ($data = trim($data))) {
        	$this->{$this->use}[$n-1]['data'] = $data;
        }
    }
}
