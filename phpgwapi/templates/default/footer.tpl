<!-- BEGIN footer -->
<P>
<table border="0" cellspacing="0" cellpading="0" width="100%" bgcolor="{table_bg_color}">
  <TR>
    <TD>
      <P><P>Powered by <a href=http://www.phpgroupware.org>phpGroupWare</a> version {version}<br>
    </TD>
  </TR>
</Table>

<!---
// If we can figure out how to do this, then we will use it. until then I am leaving it here, out of the way.
/*
  if ($phpgw_info["server"]["showpoweredbyon"] == "bottom" && $phpgw_info["server"]["showpoweredbyon"] != "top") {
     echo "<P>\n";
     echo "<Table Width=100% Border=0 CellPadding=0 CellSpacing=0 BGColor=".$phpgw_info["theme"]["navbar_bg"].">\n";
     echo " <TR><TD>";
     echo "<P><P>\n" . lang("Powered by phpGroupWare version x",
							$phpgw_info["server"]["versions"]["phpgwapi"]) . "<br>\n";
     echo "</TD>";
     if ($phpgw_info["flags"]["parent_page"])
       echo "<td align=\"right\"><a href=\"".$phpgw->link($phpgw_info["flags"]["parent_page"])."\">".lang("up")."</a></td>";
     echo "</TR>\n</Table>\n";
  }
*/
--->

<!-- END footer -->

