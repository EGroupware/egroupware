<?php
/*
    function show_icon(&$tpl, $td_width, $appname, $description = "")
    {
       global $phpgw_info, $colspan, $phpgw;

       if ($appname && (($appname=="home" || $appname=="logout" || $appname == "print" || $appname == "preferences" || $appname == "about")
           || ($phpgw_info["user"]["apps"][$appname]))) {

          if (isset($phpgw_info["flags"]["navbar_target"]) && $phpgw_info["flags"]["navbar_target"]) {
             $target = ' target="' . $phpgw_info["flags"]["navbar_target"] . '"';
             if ($appname == "logout") {
                $target = ' target="_top"';
             }
          } else {
             $target = "";
          }

        if (isset($colspan) && $colspan) {
          $colspan++;
        } else {
          $colspan = 1;
        }

        if (!isset($description) || !$description) {
             $description = $phpgw_info["apps"][$appname]["title"];
          }

        $urlbasename = $phpgw_info["server"]["webserver_url"];

        if ($appname == "home") {
             $output_text = "<A href=\"" . $phpgw->link($urlbasename."/index.php");
        } elseif ($appname == "logout") {
             $output_text = "<A href=\"" . $phpgw->link($urlbasename."/logout.php");
        } elseif ($appname == "about") {
           if ($phpgw_info["flags"]["currentapp"] != "home" && $phpgw_info["flags"]["currentapp"] != "preferences" && $phpgw_info["flags"]["currentapp"] != "about") {
              $about_app = "app=" . $phpgw_info["flags"]["currentapp"];
           }
             $output_text = "<A href=\"" . $phpgw->link($urlbasename."/about.php",$about_app);
          } elseif ($appname == "print") {
             $output_text = "<A href=\"javascript:window.print();\"";
// Changed by Skeeter 03 Dec 00 2000 GMT
// This is to allow for the calendar app to have a default page view.
        } elseif ($appname == "calendar") {
              if (isset($phpgw_info["user"]["preferences"]["calendar"]["defaultcalendar"])) {
                $view = $phpgw_info["user"]["preferences"]["calendar"]["defaultcalendar"];
              } else {
                $view = "index.php";
             }
             $output_text = "<A href=\"" . $phpgw->link($urlbasename."/$appname/".$view);
// end change
        } else {
           $output_text = "<A href=\"" . $phpgw->link($urlbasename."/$appname/index.php");
        }
        $output_text .= "\"$target>";
          if ($phpgw_info["user"]["preferences"]["common"]["navbar_format"] != "text") {
             if ($appname != "home" && $appname != "logout" && $appname != "print" && $appname != "about") {
                $output_text .= '<img src="' . $this->get_image_path($appname) . '/navbar.gif" border=0 alt="' . lang($description) . '" title="' . lang($description) . '">';
            } else {
              $output_text .= '<img src="' . $phpgw_info["server"]["images_dir"] .'/' . $appname . '.gif" border="0" alt="' . lang($description) . '" title="' . lang($description) . '">';
            }
          }
        if (ereg("text",$phpgw_info["user"]["preferences"]["common"]["navbar_format"])) {
              $output_text .= "<br><font size=\"-2\">" . lang($description) . "</font>";
        }
          $output_text .= "</A>";
          $tpl->set_var("td_align","center");
          $tpl->set_var("td_width",$td_width);
          $tpl->set_var("colspan",1);
          $tpl->set_var("value",$output_text);
          $tpl->parse("navbar_columns","navbar_column",True);
       }
    }






    function navbar($force = False)
    {
       global $cd,$phpgw,$phpgw_info,$colspan,$PHP_SELF;

       if (($phpgw_info["user"]["preferences"]["common"]["useframes"] && $phpgw_info["server"]["useframes"] == "allowed")
          || ($phpgw_info["server"]["useframes"] == "always")) {
          if (! $force) {
             return False;          
          }
       }

       $tpl = new Template($phpgw_info["server"]["template_dir"]);
       $tpl->set_file(array("navbar"        => "navbar.tpl",
                            "navbar_row"    => "navbar_row.tpl",
                            "navbar_column" => "navbar_column.tpl"
                           ));
 
       $urlbasename = $phpgw_info["server"]["webserver_url"];
 
       if (ereg("text",$phpgw_info["user"]["preferences"]["common"]["navbar_format"])) {
          $td_width = "7%";
        } else {
          $td_width = "3%";
        }

       // This is hardcoded for right now
       if ($phpgw_info["user"]["preferences"]["common"]["navbar_format"] == "text") {
          $tpl->set_var("tr_color","FFFFFF");
       } else {
          $tpl->set_var("tr_color",$phpgw_info["theme"]["navbar_bg"]);      
       }

       $tpl->set_var("td_align","left");
       $tpl->set_var("td_width","");
       $tpl->set_var("value","&nbsp;" . $phpgw_info["user"]["fullname"] . " - "
                           . lang($phpgw->common->show_date(time(),"l")) . " "
                           . lang($phpgw->common->show_date(time(),"F")) . " "
                          . $phpgw->common->show_date(time(),"d, Y"));
       $tpl->parse("navbar_columns","navbar_column",True);
       
       if ($phpgw_info["user"]["preferences"]["common"]["navbar_format"] == "text") {
          $tabs[1]["label"] = "home";
          $tabs[1]["link"]  = $phpgw->link($phpgw_info["server"]["webserver_url"] . "/index.php");

          if ($PHP_SELF == $phpgw_info["server"]["webserver_url"] . "/index.php") {
             $selected = 1;
          }

          $i = 2;
          
          while ($permission = each($phpgw_info["user"]["apps"])) {
             if ($phpgw_info["apps"][$permission[0]]["status"] != 2) {
                $tabs[$i]["label"] = $permission[0];
                $tabs[$i]["link"]  = $phpgw->link($phpgw_info["server"]["webserver_url"] . "/" . $permission[0] . "/index.php");
                if (ereg($permission[0],$PHP_SELF)) {
                   $selected = $i;
                }
                $i++;
             }
          }

          $tabs[$i]["label"] = "preferences";
          $tabs[$i]["link"]  = $phpgw->link($phpgw_info["server"]["webserver_url"] . "/preferences/index.php");
          if ($PHP_SELF == $phpgw_info["server"]["webserver_url"] . "/preferences/index.php") {
             $selected = $i;
          }
          $i++;

          $tabs[$i]["label"] = "logout";
          $tabs[$i]["link"]  = $phpgw->link($phpgw_info["server"]["webserver_url"] . "/logout.php");

          $tpl->set_var("td_align","center");
          $tpl->set_var("td_width",$td_width);
          $tpl->set_var("colspan",1);
          $tpl->set_var("value",$this->create_tabs($tabs,$selected,-1));
          $tpl->parse("navbar_columns","navbar_column",True);

       } else {
       
          $this->show_icon(&$tpl,$td_width,"home","home");
          $this->show_icon(&$tpl,$td_width,"print","print");
    
          while ($permission = each($phpgw_info["user"]["apps"])) {
             if ($phpgw_info["apps"][$permission[0]]["status"] != 2) {
                $this->show_icon(&$tpl,$td_width,$permission[0]);
             }
          }
    
          $this->show_icon(&$tpl,$td_width,"preferences","Preferences");
          if ($phpgw_info["flags"]["currentapp"] == "home" || $phpgw_info["flags"]["currentapp"] == "preferences" || $phpgw_info["flags"]["currentapp"] == "about") {
             $app = "phpGroupWare";
          } else {
             $app = $phpgw_info["flags"]["currentapp"];
          }
          $this->show_icon(&$tpl,$td_width,"about","About $app");
          $this->show_icon(&$tpl,$td_width,"logout","Logout");
   
       } // end else
 
       $tpl->parse("navbar_rows","navbar_row",True); 
 
       if (isset($phpgw_info["user"]["apps"]["admin"]) && isset($phpgw_info["user"]["preferences"]["common"]["show_currentusers"])) {
          if ($phpgw_info["server"]["showpoweredbyon"] != "top") {
            $phpgw->db->query("select count(*) from phpgw_sessions");
            $phpgw->db->next_record();
             $tpl->set_var("td_align","right");
             $tpl->set_var("td_width","");
             $tpl->set_var("tr_color",$phpgw_info["theme"]["bg_color"]);
             $tpl->set_var("value",'<a href="' . $phpgw->link($urlbasename."/admin/currentusers.php")
                                 . '">&nbsp;' . lang("Current users") . ': ' . $phpgw->db->f(0) . '</a>');
             $tpl->set_var("colspan",($colspan+1));
             $tpl->parse("navbar_columns","navbar_column");
             $tpl->parse("navbar_rows","navbar_row",True);
          }
       }
 
       if ($phpgw_info["server"]["showpoweredbyon"] == "top") {
          if ( ! $phpgw_info["user"]["preferences"]["common"]["show_currentusers"]) {
             $tpl->set_var("td_align","left");
             $tpl->set_var("td_width","");
             $tpl->set_var("tr_color",$phpgw_info["theme"]["bg_color"]);
             $tpl->set_var("value",lang("Powered by phpGroupWare version x",$phpgw_info["server"]["versions"]["phpgwapi"]));
             $tpl->set_var("colspan",$colspan);
             $tpl->parse("navbar_columns","navbar_column");
             $tpl->parse("navbar_rows","navbar_row",True);
          }
       }
 
       if ($phpgw_info["server"]["showpoweredbyon"] == "top"
          && isset($phpgw_info["user"]["apps"]["admin"])
          && isset($phpgw_info["user"]["preferences"]["common"]["show_currentusers"])) {
 
          $tpl->set_var("td_align","left");
          $tpl->set_var("td_width","");
          $tpl->set_var("tr_color",$phpgw_info["theme"]["bg_color"]);
          $tpl->set_var("value",lang("Powered by phpGroupWare version x",$phpgw_info["server"]["versions"]["phpgwapi"]));
          $tpl->set_var("colspan",1);
          $tpl->parse("navbar_columns","navbar_column");
 
        $phpgw->db->query("select count(*) from phpgw_sessions");
        $phpgw->db->next_record();
          $tpl->set_var("td_align","right");
          $tpl->set_var("td_width","");
          $tpl->set_var("value",'<a href="' . $phpgw->link($urlbasename."/admin/currentusers.php")
                              . '">&nbsp;' . lang("Current users") . ': ' . $phpgw->db->f(0) . '</a>');
          $tpl->set_var("colspan",($colspan--));
          $tpl->parse("navbar_columns","navbar_column",True);
          $tpl->parse("navbar_rows","navbar_row",True);
       }
 
       $tpl->pparse("out","navbar");
 
       // Make the wording a little more user friendly
       if ($phpgw_info["user"]["lastpasswd_change"] == 0) {
          echo "<br><center>" . lang("You are required to change your password "
           . "during your first login");
           echo "<br> Click this image on the navbar: <img src=\"".$phpgw_info["server"]["webserver_url"]."/preferences/templates/".$phpgw_info["server"]["template_set"]."/images/navbar.gif\">";
           echo "</center>";
       } else if ($phpgw_info["user"]["lastpasswd_change"] < time() - (86400*30)) {
        echo "<br><CENTER>" . lang("it has been more then x days since you "
           . "changed your password",30) . "</CENTER>";
       }
       if (isset($cd) && $cd) {
          echo "<center>" . check_code($cd) . "</center>";
       }

       unset($colspan);

    } */

  function parse_navbar($force = False)
  {
     global $phpgw_info, $phpgw;

     $tpl = new Template($phpgw_info["server"]["template_dir"]);
     $tpl->set_unknowns("remove");

     $tpl->set_file(array("navbar"        => "navbar.tpl",
                          "navbar_app"    => "navbar_app.tpl"
                         ));

     $tpl->set_var("navbar_color",$phpgw_info["theme"]["navbar_bg"]);

     if ($phpgw_info["flags"]["navbar_target"]) {
        $target = ' target="' . $phpgw_info["flags"]["navbar_target"] . '"';
     }

     while ($app = each($phpgw_info["navbar"])) {
        $title = '<img src="' . $app[1]["icon"] . '" alt="' . $app[1]["title"] . '" title="'
                . $app[1]["title"] . '" border="0">';
        if ($phpgw_info["user"]["preferences"]["common"]["navbar_format"] == "icons_and_text") {
           $title .= "<br>" . $app[1]["title"];
           $tpl->set_var("width","7%");
        } else {
           $tpl->set_var("width","3%");
        }

        $tpl->set_var("value",'<a href="' . $app[1]["url"] . '"' . $target . '>' . $title . '</a>');
        $tpl->parse("applications","navbar_app",True);
     }

     if ($phpgw_info["server"]["showpoweredbyon"] == "top") {
        $tpl->set_var("powered_by",lang("Powered by phpGroupWare version x",$phpgw_info["server"]["versions"]["phpgwapi"]));
     }
     if (isset($phpgw_info["navbar"]["admin"]) && isset($phpgw_info["user"]["preferences"]["common"]["show_currentusers"])) {
        $db  = $phpgw->db;
        $db->query("select count(*) from phpgw_sessions");
        $db->next_record();
        $tpl->set_var("current_users",'<a href="' . $phpgw->link("/admin/currentusers.php") . '">&nbsp;'
                                    . lang("Current users") . ': ' . $db->f(0) . '</a>');
     }
     $tpl->set_var("user_info",$phpgw->common->display_fullname() . " - "
                             . lang($phpgw->common->show_date(time(),"l")) . " "
                             . lang($phpgw->common->show_date(time(),"F")) . " "
                             . $phpgw->common->show_date(time(),"d, Y"));

     // Maybe we should create a common function in the phpgw_accounts_shared.inc.php file
     // to get rid of duplicate code.
     if ($phpgw_info["user"]["lastpasswd_change"] == 0) {
        $api_messages = lang("You are required to change your password during your first login")
                      . '<br> Click this image on the navbar: <img src="'
                      . $phpgw_info["server"]["webserver_url"] . '/preferences/templates/'
                      . $phpgw_info["server"]["template_set"] . '/images/navbar.gif">';
     } else if ($phpgw_info["user"]["lastpasswd_change"] < time() - (86400*30)) {
        $api_messages = lang("it has been more then x days since you changed your password",30);
     }
 
     // This is gonna change
     if (isset($cd)) {
        $tpl->set_var("messages",$api_messages . "<br>" . checkcode($cd));
     }

     // If the application has a header include, we now include it
     if ($phpgw_info["flags"]["noheader"] && ! $phpgw_info["flags"]["noappheader"]) {

     }
     return $tpl->finish($tpl->parse("out","navbar"));
  }
