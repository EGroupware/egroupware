<?php
  /**************************************************************************\
  * phpGroupWare API -                                                       *
  * http://www.phpgroupware.org/api                                          *
  * ------------------------------------------------------------------------ *
  * This file is not part of the phpGroupWare API                            *
  * ------------------------------------------------------------------------ *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

/*
 *      rssparse.php3
 *
 *      Copyright (C) 2000 Jeremey Barrett
 *      j@nwow.org
 *      http://nwow.org
 *
 *      Version 0.4
 *
 *      This program is free software; you can redistribute it and/or modify
 *      it under the terms of the GNU General Public License as published by
 *      the Free Software Foundation; either version 2 of the License, or
 *      (at your option) any later version.
 *      
 *      This program is distributed in the hope that it will be useful,
 *      but WITHOUT ANY WARRANTY; without even the implied warranty of
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *      GNU General Public License for more details.
 *      
 *      You should have received a copy of the GNU General Public License
 *      along with this program; if not, write to the Free Software
 *      Foundation,Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 *
 *      rssparse.php3 is a small PHP script for parsing RDF/RSS XML data. It has been
 *      tested with a number of popular web news and information sites such as 
 *      slashdot.org, lwn.net, and freshmeat.net. This is not meant to be exhaustive 
 *      but merely to provide the basic necessities. It will grow in capabilities with 
 *      time, I'm sure.
 *
 *      This is code I wrote for Nerds WithOut Wires, http://nwow.org.
 *
 *
 *      USAGE:
 *      In your PHP script, simply do something akin to this:
 *
 *      include("rssparse.php3");
 *      $fp = fopen("file", "r");
 *      $rss = rssparse($fp);
 *
 *      if (!$rss) {
 *          ERROR;
 *      }
 *
 *      while (list(,$item) = each($rss->items)) {
 *          printf("Title: %s\n", $item["title"]);
 *          printf("Link: %s\n", $item["link"]);
 *          printf("Description: %s\n", $item["desc"]);
 *      }
 *
 *      printf("Channel Title: %s\n", $rss->title);
 *      printf("Channel Description: %s\n", $rss->desc);
 *      printf("Channel Link: %s\n", $rss->link);
 *
 *      printf("Image Title: %s\n", $rss->image["title"]);
 *      printf("Image URL: %s\n", $rss->image["url"]);
 *      printf("Image Description: %s\n", $rss->image["desc"]);
 *      printf("Image Link: %s\n", $rss->image["link"]);
 *
 *
 *      CHANGES:
 *      0.4 - rssparse.php3 now supports the channel image tag and correctly supports
 *            RSS-style channels.
 *
 *
 *      BUGS:
 *      Width and height tags in image not supported, some other tags not supported
 *      yet.
 *
 * 
 *      IMPORTANT NOTE:
 *      This requires PHP's XML routines. You must configure PHP with --with-xml.
 */

 /* $Id$ */

function _rssparse_start_elem ($parser, $elem, $attrs) {
  global $_rss;

  if ($elem == "CHANNEL") {
    $_rss->depth++;
    $_rss->state[$_rss->depth] = "channel";
    $_rss->tmptitle[$_rss->depth] = "";
    $_rss->tmplink[$_rss->depth] = "";
    $_rss->tmpdesc[$_rss->depth] = "";
  }
  else if ($elem == "IMAGE") {
    $_rss->depth++;
    $_rss->state[$_rss->depth] = "image";
    $_rss->tmptitle[$_rss->depth] = "";
    $_rss->tmplink[$_rss->depth] = "";
    $_rss->tmpdesc[$_rss->depth] = "";
    $_rss->tmpurl[$_rss->depth] = "";
  }
  else if ($elem == "ITEM") {
    $_rss->depth++;
    $_rss->state[$_rss->depth] = "item";
    $_rss->tmptitle[$_rss->depth] = "";
    $_rss->tmplink[$_rss->depth] = "";
    $_rss->tmpdesc[$_rss->depth] = "";
  }
  else if ($elem == "TITLE") {
    $_rss->depth++;
    $_rss->state[$_rss->depth] = "title";
  }
  else if ($elem == "LINK") {
    $_rss->depth++;
    $_rss->state[$_rss->depth] = "link";
  }
  else if ($elem == "DESCRIPTION") {
    $_rss->depth++;
    $_rss->state[$_rss->depth] = "desc";
  }
  else if ($elem == "URL") {
    $_rss->depth++;
    $_rss->state[$_rss->depth] = "url";
  }
}
  
function _rssparse_end_elem ($parser, $elem) {
  global $_rss;

  if ($elem == "CHANNEL") {
    $_rss->set_channel($_rss->tmptitle[$_rss->depth], $_rss->tmplink[$_rss->depth], $_rss->tmpdesc[$_rss->depth]);
    $_rss->depth--;
  }
  else if ($elem == "IMAGE") {
    $_rss->set_image($_rss->tmptitle[$_rss->depth], $_rss->tmplink[$_rss->depth], $_rss->tmpdesc[$_rss->depth], $_rss->tmpurl[$_rss->depth]);
    $_rss->depth--;
  }
  else if ($elem == "ITEM") {
    $_rss->add_item($_rss->tmptitle[$_rss->depth], $_rss->tmplink[$_rss->depth], $_rss->tmpdesc[$_rss->depth]);
    $_rss->depth--;
  }
  else if ($elem == "TITLE") {
    $_rss->depth--;
  }
  else if ($elem == "LINK") {
    $_rss->depth--;
  }
  else if ($elem == "DESCRIPTION") {
    $_rss->depth--;
  }
  else if ($elem == "URL") {
    $_rss->depth--;
  }
}
  
function _rssparse_elem_data ($parser, $data) {
  global $_rss;

  if ($_rss->state[$_rss->depth] == "title") {
    $_rss->tmptitle[($_rss->depth - 1)] .= $data;
  }
  else if ($_rss->state[$_rss->depth] == "link") {
    $_rss->tmplink[($_rss->depth - 1)] .= $data;
  }
  else if ($_rss->state[$_rss->depth] == "desc") {
    $_rss->tmpdesc[($_rss->depth - 1)] .= $data;
  }
  else if ($_rss->state[$_rss->depth] == "url") {
    $_rss->tmpurl[($_rss->depth - 1)] .= $data;
  }
}

class rssparser {
  var $title;
  var $link;
  var $desc;
  var $items = array();
  var $nitems;
  var $image = array();
  var $state = array();
  var $tmptitle = array();
  var $tmplink = array();
  var $tmpdesc = array();
  var $tmpurl = array();
  var $depth;

  function rssparser() {
    $this->nitems = 0;
    $this->depth = 0;
  }

  function set_channel($in_title, $in_link, $in_desc) {
    $this->title = $in_title;
    $this->link = $in_link;
    $this->desc = $in_desc;
  }

  function set_image($in_title, $in_link, $in_desc, $in_url) {
    $this->image["title"] = $in_title;
    $this->image["link"] = $in_link;
    $this->image["desc"] = $in_desc;
    $this->image["url"] = $in_url;
  }

  function add_item($in_title, $in_link, $in_desc) {
    $this->items[$this->nitems]["title"] = $in_title;
    $this->items[$this->nitems]["link"] = $in_link;
    $this->items[$this->nitems]["desc"] = $in_desc;
    $this->nitems++;
  }

  function parse($fp) {
    $xml_parser = xml_parser_create();
    
    xml_set_element_handler($xml_parser, "_rssparse_start_elem", "_rssparse_end_elem");
    xml_set_character_data_handler($xml_parser, "_rssparse_elem_data");
    
    while ($data = fread($fp, 4096)) {
      if (!xml_parse($xml_parser, $data, feof($fp))) {
	return 1;
      }
    }
    
    xml_parser_free($xml_parser);
    
    return 0;
  }
}

function rssparse ($fp) {
  global $_rss;

  $_rss = new rssparser();

  if ($_rss->parse($fp)) {
    return 0;
  }
  
  return $_rss;
}

  
