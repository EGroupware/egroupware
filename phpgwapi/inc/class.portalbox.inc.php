<?php
  /**************************************************************************\
  * phpGroupWare - API                                                       *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  class portalbox {
  
      //Set up the Object, reserving memory space for variables
  
      var $outerwidth;
      var $outerbordercolor;
      var $outerborderwidth;
      var $titlebgcolor;
      var $width;
      var $innerwidth;
      var $innerbgcolor;
  
      // Textual variables
      var $title;
  
      /*
          Use these functions to get and set the values of this
          object's variables. This is good OO practice, as it means
          that datatype checking can be completed and errors raised accordingly.
      */
      function setvar($var,$value="") {
        if ($value=="") {
  		global $$var;
  		$value = $$var;
        }
        $this->$var = $value;
  //      echo $var." = ".$this->$var."<br>\n";
      }
  
      function getvar($var="") {
  	  if ($var=="" || !isset($this->$var)) {
  		global $phpgw;
  		echo 'Programming Error: '.$this->classname().'->getvar('.$var.')!<br>\n';
  		$phpgw->common->phpgw_exit();
  	  }
  //echo "Var = ".$var."<br>\n";
  //echo $var." = ".$this->$var."<br>\n";
        return $this->$var;
      }
  
      /*
          This is the constructor for the object.
      */
      function portalbox($title="", $primary="", $secondary="", $tertiary="") {
          $this->setvar("title",$title);
  //        echo "After SetVar Title = ".$this->getvar("title")."<br>\n";
          $this->setvar("outerborderwidth",1);
          $this->setvar("titlebgcolor",$primary);
          $this->setvar("innerbgcolor",$secondary);
          $this->setvar("outerbordercolor",$tertiary);
      }
      // Methods 
  } 
