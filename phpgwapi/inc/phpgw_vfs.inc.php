<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  class vfs
  {
  var $basedir;
  
    function sanitize($string) {
      global $phpgw, $phpgw_info;
      $sep = $phpgw_info["server"]["dir_separator"];
      return(ereg_replace( "^\.+", "",str_replace($sep, "",strrchr($string,$sep))));
    }

    function securitycheck($string) {
      if(substr($string,0,1) ==  "." || substr($string,0,1) ==  "\\" || strstr($string, "..") || strstr($string, "\\..") || strstr($string, ".\\.")) {
        return False;
      }else{
        return True;
      }
    }
    
    function rawname2array($string) {
      global $phpgw, $phpgw_info;
      $sep = $phpgw_info["server"]["dir_separator"];
      
      if(substr($string,0,1) == $sep) {
        // we have a starting slash...
        $sub = substr($string,1);  // take everything past the first slash... 
        $basedir = substr($sub,0,strpos($sub,$sep));  // ...to the second slash 
        if (!$basedir){
          $basedir = substr($sub,strpos($sub,$sep));  // it becomes the basedir 
        }else{
          $file = substr($sub,strpos($sub,$sep));  // take everything after the second slash. 
        }
      } else {
         // we have no starting slash... 
        $basedir = $phpgw->common->appsession();
        $file = $string;
      }
      
      // security check (might want to spin this into it's own function...) 
      if(substr($file,0,1) ==  "." || substr($file,0,1) ==  "\\" || strstr($file, "..") || strstr($file, "\\..") || strstr($file, ".\\.")) {
        return False;
      }
      return(array( "basedir" => $basedir,  "file" => $file));
    }
      
    function getabsolutepath($target = False) {
      global $phpgw, $phpgw_info;
      $sep = $phpgw_info["server"]["dir_separator"];
      $basedir = $phpgw_info["server"]["files_dir"].$sep."groups";
      $currentdir = $phpgw->common->appsession();
      if(!$this->securitycheck($target)) {
        return False;
      } else {
        $dir_array = explode($sep,$target);
        $dir_count = count($dir_array);
        if (substr ($target, 0, 1) != $sep){
          if (!empty($currentdir)){
            $basedir .= $currentdir;
          }
        }
        for ($diridx=0;$diridx<$dir_count;++$diridx) {
          if (!empty($dir_array[$diridx])){
            $basedir .= $sep.$dir_array[$diridx];
          }
        }
        return $basedir = ereg_replace ($phpgw_info["server"]["files_dir"].$sep."groups".$sep."home", $phpgw_info["server"]["files_dir"].$sep."users".$sep.$phpgw_info["user"]["userid"], $basedir);
      }
    }

    function ls($target = False, $checksubdirs = "No") {
      global $phpgw, $phpgw_info;
      $sep = $phpgw_info["server"]["dir_separator"];
      if(!$this->securitycheck($target)) {
        return False;
      } else {
        $basedir = $this->getabsolutepath($target);
        if ($basedir == $phpgw_info["server"]["files_dir"].$sep."groups".$sep || $target == "/"){
        //if ($basedir == $phpgw_info["server"]["files_dir"].$sep."groups".$sep){
          if ($checksubdirs == "Yes"){ //if its a dir, does that dir have subdirs?
            $path = $phpgw_info["server"]["files_dir"].$sep."users".$sep.$phpgw_info["user"]["userid"];
            $subdir = dir($path);
            while($subentry=$subdir->read()) {
              $subpath = $path.$sep.$subentry;
              $subtype = filetype($subpath);
              if ($subentry != "." && $subentry != ".." && $subtype == "dir"){
                $hassubdir = True;
              }
            }
          }
          $list[] = array("name" => "home", "type"  => "dir", "subdirs" => $hassubdir);
	        $hassubdir = False;
          $groups = $phpgw->accounts->read_group_names();
          if (!empty ($groups[0][1])) {
            $group_count = count($groups);
            for ($groupidx=0;$groupidx<$group_count;++$groupidx) {
              $this->verifydir("group",$groups[$groupidx][1]);
              if ($checksubdirs == "Yes"){ //if its a dir, does that dir have subdirs?
                $path = $phpgw_info["server"]["files_dir"].$sep."groups".$sep.$groups[$groupidx][1];
                $subdir = dir($path);
                while($subentry=$subdir->read()) {
                  $subpath = $path.$sep.$subentry;
                  $subtype = filetype($subpath);
                  if ($subentry != "." && $subentry != ".." && $subtype == "dir"){
                    $hassubdir = True;
                  }
                }
              }
              $list[] = array("name" => $groups[$groupidx][1], "type"  => "dir", "subdirs" => $hassubdir);
	            $hassubdir = False;
            }
          }
	        return $list;
        }elseif (is_dir($basedir)){ //if basedir is a directory, then we fill the array
          $dir = dir($basedir);
          while($entry=$dir->read()) {
            if ($entry != "." && $entry != ".." ){ //make sure we filter out . and ..
              $path = $basedir.$sep.$entry;
              if (filetype($path) == "dir"){
                $entrytype = "dir";
                if ($checksubdirs == "Yes"){ //if its a dir, does that dir have subdirs?
                  $subdir = dir($path);
                  while($subentry=$subdir->read()) {
                    $subpath = "$path$sep$subentry";
                    $subtype = filetype($subpath);
                    if ($subentry != "." && $subentry != ".." && $subtype == "dir"){
                      $hassubdir = True;
                    }
                  }
                }
                $list[] = array("name" => $entry, "type"  => $entrytype, "subdirs" => $hassubdir);
              }
              if (filetype($path) == "file"){
                $entrytype = "file";
                $entrysize = filesize($path);
                $entrymodifiedtime = filemtime($path);
                $list[] = array("name" => $entry, "type"  => $entrytype, "size" => $entrysize, "modified" =>$entrymodifiedtime);
              }
            }
          }
          $dir->close();
	        return $list;
	      }elseif(is_file($basedir)){
          $dir_array = explode($sep,$basedir);
          unset($basedir);
          $dir_count = count($dir_array);
          for ($diridx=0;$diridx<($dir_count-1);++$diridx) {
            if (!empty($dir_array[$diridx])){
              $basedir .= $sep.$dir_array[$diridx];
            }
          }
          if (!is_dir($basedir) && !is_file($basedir)){
            return False;
          }elseif (is_dir($basedir)) {
            $file = $dir_array[$dir_count];
          }
        }
      }
    }

    function dir($target, $checksubdirs) {
      return $this->ls($target, $checksubdirs);
    }
    
    function cd ($target = "/", $targettype = "relative"){
      global $phpgw, $phpgw_info;
      $sep = $phpgw_info["server"]["dir_separator"];
      if ($targettype == "relative"){
        $basedir = $this->getabsolutepath($target);
      }else{
        $basedir = $target;
      }
      $currentdir = $phpgw->common->appsession();
      if (substr ($target, 0, 1) != $sep){
        if (!empty($currentdir)){
          $appsession_dir = $currentdir.$sep.$target;
        }
      }else{
        $appsession_dir = $target;
      }
      if (!is_dir($basedir)){
        return False;
      }else{
        $var = chdir ($basedir);
        if ($var){
          $phpgw->common->appsession($appsession_dir);
          return True;
        }else{
          return False;
        }
      }
    }

    function pwd() {
      global $phpgw;
      $currentdir = $phpgw->common->appsession();
      if ($currentdir == ""){$currentdir = "/";}
      return $currentdir;
    }

    function read($file) {
      global $phpgw_info;
      $path = $this->getabsolutepath($file);
      if ($fp = fopen($path, "r")) {
        $contents = fread($fp, filesize($path));
        fclose($fp);
        return $contents;
      } else {
	      return False;
	    }
    }

    function write($file, $contents) {
      global $phpgw_info;
      $path = $this->getabsolutepath($file);
      umask(000);
      if ($fp = fopen($path, "w")) {
	      fputs($fp, $contents, strlen($contents));
	      fclose($fp);
	      return 1;
      } else {
	      return 0;
      }
    }

    function move_uploaded_file($fromfile, $tofile, $from_absolute = "") {
      global $phpgw_info;
      if ($from_absolute){
        $frompath = $fromfile;
      }else{
        $frompath = $this->getabsolutepath($fromfile);
      }
      $topath = $this->getabsolutepath($tofile);
      umask(000);
      //if (!$this->move_uploaded_file($fromfile, $topath)) {
      if (!$this->mv($fromfile, $topath)) {
        return False;
      }else{
        return True;
      }
    }

    function cp($fromfile, $tofile, $from_absolute = "") {
      global $phpgw_info;
      if ($from_absolute){
        $frompath = $fromfile;
      }else{
        $frompath = $this->getabsolutepath($fromfile);
      }
      $topath = $this->getabsolutepath($tofile);
      umask(000);
      if (!copy($frompath, $topath)) {
        return False;
      }else{
        return True;
      }
    }

    function copy($fromfile, $tofile) {
      umask(000);
      return $this->cp($fromfile, $tofile);
    }

    function mv($fromfile, $tofile) {
      global $phpgw_info;
      $frompath = $this->getabsolutepath($fromfile);
      $topath = $this->getabsolutepath($tofile);
      if (!copy($frompath, $topath)) {
        return False;
      }else{
        if (!unlink($frompath)) {
          return False;
        }else{
          return True;
        }
      }
    }

    function move($fromfile, $tofile) {
      umask(000);
      return $this->mv($fromfile, $tofile);
    }

    function rm($file) {
      global $phpgw_info;
      $path = $this->getabsolutepath($file);
      if (!unlink($path)) {
        return False;
      }else{
        return True;
      }
    }

    function delete($file) {
      return $this->rm($file);
    }

    function rmdir($dir) {
      global $phpgw_info;
      $path = $this->getabsolutepath($dir);
      if (!rmdir($path)) {
        return False;
      }else{
        return True;
      }
    }

    function mkdir($dir) {
      global $phpgw_info;
      $path = $this->getabsolutepath($dir);
      umask(000);
      if (!mkdir($path, 01707)) {
        return False;
      }else{
        return True;
      }
    }

    function verifydir($type = "user", $account = False) {
      global $phpgw_info;

      if (!$account){
        $path = "/home";
      }else{
        $path = "/".$account;
      }

      if (!is_dir ($this->getabsolutepath($path))) {
        if (!$this->mkdir ($path)) {
          $msg = "To correct this error you will need to properly set the "
	          . "permissions to the files/users directory.<br> "
            ."On *nix systems please type: chmod 707 ";

          if ($type = "user"){
	          $msg .= $phpgw_info["server"]["files_dir"] . "/users/";
	        }else{
	          $msg .= $phpgw_info["server"]["files_dir"] . "/groups/";
          }	        
          echo $msg;
          return False;
	      }else{
          return True;
        }
      }else{
        return True;
      }
    }



  }
