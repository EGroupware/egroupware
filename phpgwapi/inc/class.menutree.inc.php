<?php  
  /**************************************************************************\
  * phpGroupWare API - MenuTree                                              *
  * This file based on PHP3 TreeMenu                                         *
  * (c)1999 Bjorge Dijkstra <bjorge@gmx.net>                                 *
  * ------------------------------------------------------------------------ *
  * This is not part of phpGroupWare, but is used by phpGroupWare.           * 
  * http://www.phpgroupware.org/                                             * 
  * ------------------------------------------------------------------------ *
  * This program is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU General Public License as published by the    *
  * Free Software Foundation; either version 2 of the License, or (at your   *
  * option) any later version.                                               *
  \**************************************************************************/

  /* $Id$ */

  /*********************************************/
  /*  Settings                                 */
  /*********************************************/
  /*                                           */      
  /*  $treefile variable needs to be set in    */
  /*  main file                                */
  /*                                           */ 
  /*********************************************/

class menutree {
  var $read_from_file;          // You can send the tree info from a string or file
  var $root_level_value;        // This is what the top level name or image will be

  function menutree()
  {
     $this->read_from_file = True;
  }

  function showtree($treefile, $expandlevels="", $num_menus = 50, $invisible_menus = Null){
    global $phpgw_info, $phpgw;
    
    $script       = $SCRIPT_NAME;
    
    $img_expand   = "templates/default/images/tree_expand.gif";
    $img_collapse = "templates/default/images/tree_collapse.gif";
    $img_line     = "templates/default/images/tree_vertline.gif";  
    $img_split	= "templates/default/images/tree_split.gif";
    $img_end      = "templates/default/images/tree_end.gif";
    $img_leaf     = "templates/default/images/tree_leaf.gif";
    $img_spc      = "templates/default/images/tree_space.gif";
    
    /*********************************************/
    /*  Read text file with tree structure       */
    /*********************************************/
    
    /*********************************************/
    /* read file to $tree array                  */
    /* tree[x][0] -> tree level                  */
    /* tree[x][1] -> item text                   */
    /* tree[x][2] -> item link                   */
    /* tree[x][3] -> link target                 */
    /* tree[x][4] -> last item in subtree        */
    /*********************************************/
  
    $maxlevel=0;
    $cnt=0;

    if ($this->read_from_file) {    
       $fd = fopen($treefile, "r");
       if ($fd==0) die("menutree.inc : Unable to open file ".$treefile);
       while ($buffer = fgets($fd, 4096)) {
          $tree[$cnt][0]=strspn($buffer,".");
          $tmp=rtrim(substr($buffer,$tree[$cnt][0]));
          $node=explode("|",$tmp); 
          $tree[$cnt][1]=$node[0];
          $tree[$cnt][2]=$node[1];
          $tree[$cnt][3]=$node[2];
          $tree[$cnt][4]=0;
          if ($tree[$cnt][0] > $maxlevel) $maxlevel=$tree[$cnt][0];    
          $cnt++;
       }
       fclose($fd);
    } else {
       $ta = explode("\n",$treefile);
       while (list($null,$buffer) = each($ta)) {
          $tree[$cnt][0]=strspn($buffer,".");
          $tmp=rtrim(substr($buffer,$tree[$cnt][0]));
          $node=explode("|",$tmp); 
          $tree[$cnt][1]=$node[0];
          $tree[$cnt][2]=$node[1];
          $tree[$cnt][3]=$node[2];
          $tree[$cnt][4]=0;
          if ($tree[$cnt][0] > $maxlevel) $maxlevel=$tree[$cnt][0];    
          $cnt++;
       }
    }
    for ($i=0; $i<count($tree); $i++) {
       $expand[$i]=0;
       $visible[$i]=0;
       $levels[$i]=0;
    }
  
    /*********************************************/
    /*  Get Node numbers to expand               */
    /*********************************************/
    
    if ($expandlevels!="") $explevels = explode("|",$expandlevels);
    
    $i=0;
    while($i<count($explevels)) {
      $expand[$explevels[$i]]=1;
      $i++;
    }
    
    /*********************************************/
    /*  Find last nodes of subtrees              */
    /*********************************************/
    
    $lastlevel=$maxlevel;
    for ($i=count($tree)-1; $i>=0; $i--) {
       if ( $tree[$i][0] < $lastlevel ) {
         for ($j=$tree[$i][0]+1; $j <= $maxlevel; $j++) {
            $levels[$j]=0;
         }
       }
       if ( $levels[$tree[$i][0]]==0 ) {
         $levels[$tree[$i][0]]=1;
         $tree[$i][4]=1;
       } else
         $tree[$i][4]=0;
       $lastlevel=$tree[$i][0];  
    }
    
    
    /*********************************************/
    /*  Determine visible nodes                  */
    /*********************************************/
  
  
    
    $visible[0]=1;   // root is always visible
    $visible[1]=1;   // root is always visible
    $visible[2]=1;   // root is always visible
    $visible[3]=1;   // root is always visible
    $visible[4]=1;   // root is always visible
    $visible[5]=1;   // root is always visible
    $visible[6]=1;   // root is always visible
    $visible[7]=1;   // root is always visible
    $visible[8]=1;   // root is always visible
    $visible[9]=1;   // root is always visible
    $visible[10]=1;   // root is always visible
    $visible[11]=1;   // root is always visible
    $visible[12]=1;   // root is always visible
    $visible[13]=1;   // root is always visible
    $visible[14]=1;   // root is always visible
    $visible[15]=1;   // root is always visible
    $visible[16]=1;   // root is always visible
    $visible[17]=1;   // root is always visible
    $visible[18]=1;   // root is always visible
    $visible[19]=1;   // root is always visible
    $visible[20]=1;   // root is always visible
    $visible[21]=1;   // root is always visible
    $visible[22]=1;   // root is always visible
    $visible[23]=1;   // root is always visible
    $visible[24]=1;   // root is always visible
    $visible[25]=1;   // root is always visible
    $visible[26]=1;   // root is always visible
    $visible[27]=1;   // root is always visible
    $visible[28]=1;   // root is always visible
  
  
    for ($i=0; $i<count($explevels); $i++) {
      $n=$explevels[$i];
      if ( ($visible[$n]==1) && ($expand[$n]==1) ) {
         $j=$n+1;
         while ( $tree[$j][0] > $tree[$n][0] ) {
           if ($tree[$j][0]==$tree[$n][0]+1) $visible[$j]=1;     
           $j++;
         }
      }
    }
  
  
    for ($i=0; $i<count($explevels); $i++) {
      $n=$explevels[$i];
      if ( ($visible[$n]==1) && ($expand[$n]==1) ) {
         $j=$n+1;
         while ( $tree[$j][0] == $tree[$n][0] + 1 ) {
           $visible[$j]=1;     
           $j++;
         }
      }
    }
    
    
    /*********************************************/
    /*  Output nicely formatted tree             */
    /*********************************************/
    
    for ($i=0; $i<$maxlevel; $i++) $levels[$i]=1;
  
    $maxlevel++;
    
    $cnt=0;
    while ($cnt<count($tree)) {
      if ($visible[$cnt]) {
        /****************************************/
        /* Create expand/collapse parameters    */
        /****************************************/
        $i=1; $params="p=";
        while($i<count($expand)) {
          if ( ($expand[$i]==1) && ($cnt!=$i) || ($expand[$i]==0 && $cnt==$i)) {
            $params=$params.$i;
            $params=$params."|";
          }
          $i++;
        }

        /****************************************/
        /* Always display the extreme top level */
        /****************************************/
        if($cnt==0) {
          $str = "<table cellspacing=0 cellpadding=0 border=0 cols=".($maxlevel+3)." width=".($maxlevel*16+100).">\n";
          $str .= '<a href="' . $phpgw->link("index.php",$params) . '" target="_parent">' . $this->root_level_value . '</a>';
          $str .= "\n<tr>";
          for ($i=0; $i<$maxlevel; $i++) $str .= "<td width=16></td>";
          $str .= "<td width=100></td></tr>\n";
        }

        /****************************************/
        /* start new row                        */
        /****************************************/      
        $str .= "<tr>";
        
        /****************************************/
        /* vertical lines from higher levels    */
        /****************************************/
        $i=0;
        while ($i<$tree[$cnt][0]-1) {
          if ($levels[$i]==1)
              $str .= '<td><img src="' . $img_line . '" alt="|"></td>';
          else
              $str .= '<td><img src="' . $img_spc . '" alt=" "></td>';
          $i++;
        }
        
        /****************************************/
        /* corner at end of subtree or t-split  */
        /****************************************/         
        if ($tree[$cnt][4]==1) {
          $str .= '<td><img src="' . $img_end . '" alt="\"></td>';
          $levels[$tree[$cnt][0]-1]=0;
        } else {
          $str .= '<td><img src="' . $img_split . '" alt="|-"></td>';
          $levels[$tree[$cnt][0]-1]=1;    
        } 
        
        /********************************************/
        /* Node (with subtree) or Leaf (no subtree) */
        /********************************************/
        if ($tree[$cnt+1][0]>$tree[$cnt][0]) {
          
          if ($expand[$cnt]==0)
              $str .= "<td><a href=\"".$phpgw->link($script,$params)."\"><img src=\"".$img_expand."\" border=no alt=\"+\"></a></td>";
          else
              $str .= "<td><a href=\"".$phpgw->link($script,$params)."\"><img src=\"".$img_collapse."\" border=no alt=\"-\"></a></td>";         
        } else {
          /*************************/
          /* Tree Leaf             */
          /*************************/
  
          $str .= '<td><img src="' . $img_leaf . '" alt="o"></td>';         
        }
        
        /****************************************/
        /* output item text                     */
        /****************************************/
        if ($tree[$cnt][2]=="")
            $str .= "<td colspan=".($maxlevel-$tree[$cnt][0]).">".$tree[$cnt][1]."<font face\=\"Arial, Helvetica, san-serif\" size=\"2\"></td>";
        else
            $str .= "<td colspan=".($maxlevel-$tree[$cnt][0])."><font face\=\"Arial, Helvetica, san-serif\" size=\"2\"><a href=\"".$phpgw->link($tree[$cnt][2],$params)."\" target=\"".$tree[$cnt][3]."\">".$tree[$cnt][1]."</a></td>";
            
        /****************************************/
        /* end row                              */
        /****************************************/
                
        $str .= "</tr>\n";      
      }
      $cnt++;    
    }
    $str .= "</table>\n";

    return $str;
  
    /***************************************************/
    /* Tree file format                                */
    /*                                                 */
    /*                                                 */
    /* The first line is always of format :            */
    /* .[rootname]                                     */
    /*                                                 */
    /* each line contains one item, the line starts    */ 
    /* with a series of dots(.). Each dot is one level */
    /* deeper. Only one level at a time once is allowed*/
    /* Next comes the come the item name, link and     */
    /* link target, seperated by a |.                  */
    /*                                                 */  
    /* example:                                        */
    /*                                                 */  
    /* .top                                            */
    /* ..category 1                                    */
    /* ...item 1.1|item11.htm|main                     */
    /* ...item 2.2|item12.htm|main                     */
    /* ..category 2|cat2overview.htm|main              */
    /* ...item 2.1|item21.htm|main                     */
    /* ...item 2.2|item22.htm|main                     */
    /* ...item 2.3|item23.htm|main                     */
    /*                                                 */  
    /***************************************************/
  }
}
