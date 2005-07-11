<!-- BEGIN header -->
<script type="text/javascript">
<!--
function check_all(which)
{
  for (i=0; i<document.apps.elements.length; i++)
  {
    if (document.apps.elements[i].type == "checkbox" && document.apps.elements[i].name.substring(0,which.length) == which)
    {
      if (document.apps.elements[i].checked)
      {
        document.apps.elements[i].checked = false;
      }
      else
      {
        document.apps.elements[i].checked = true;
      }
    } 
  }
}
// -->
</script>

<br />
<div align="center">
<table border="0" width="100%" cellspacing="0" cellpadding="2">
  <tr>
    <td align="center">{description}</td>
  </tr>
</table>
<form name="apps" method="post" action="{action_url}">
<table width="90%" cellspacing="0" cellpadding="2">
<!-- END header -->

<!-- BEGIN app_header -->
  <tr class="th">
    <td colspan="5" align="center">{appdata}</td>
    <td colspan="4" align="center">{actions}</td>
  </tr>
  <tr bgcolor="#99cccc">
    <td colspan="2">{app_info}</td>
    <td align="center">{app_title}</td>
    <td align="center">{app_currentver}</td>
    <td align="center">{app_version}</td>
    <td align="center">{app_install}</td>
    <td align="center">{app_upgrade}</td>
    <td align="center">{app_resolve}</td>
    <td align="center">{app_remove}</td>
  </tr>
  <tr>
    <td bgcolor="{bg_color}" colspan="5">&nbsp;</td>
    <td bgcolor="{bg_color}" align="center">
     <a href="javascript:check_all('install')"><img src="templates/default/images/{check}" border="0" height="16" width="21" alt="{install_all}" /></a>
    </td>
    <td bgcolor="{bg_color}" align="center">
     <a href="javascript:check_all('upgrade')"><img src="templates/default/images/{check}" border="0" height="16" width="21" alt="{upgrade_all}" /></a>
    </td>
    <td bgcolor="{bg_color}">&nbsp;</td>
    <td bgcolor="{bg_color}" align="center">
      <a href="javascript:check_all('remove')"><img src="templates/default/images/{check}" border="0" height="16" width="21" alt="{remove_all}" /></a>
    </td>
  </tr>
<!-- END app_header -->

<!-- BEGIN apps -->
  <tr bgcolor="{bg_color}">
    <td><a href="applications.php?detail={appname}"><img src="templates/default/images/{instimg}" alt="{instalt}" title="{instalt}" border="0" /></a></td>
    <td>{appinfo}&nbsp;</td>
    <td>{apptitle}&nbsp;</td>
    <td>{currentver}&nbsp;</td>
    <td>{version}&nbsp;</td>
    <td bgcolor="#CCFFCC" align="center">{install}</td>
    <td bgcolor="#CCCCFF" align="center">{upgrade}</td>
    <td align="center">{resolution}&nbsp;</td>
    <td bgcolor="#CCAAAA" align="center">{remove}</td>
  </tr>
<!-- END apps -->

<!-- BEGIN detail -->
  <tr bgcolor="{bg_color}">
    <td>{name}&nbsp;</td>
    <td>{details}&nbsp;</td>
  </tr>
<!-- END detail -->

<!-- BEGIN table -->
  <tr bgcolor="{bg_color}">
    <td>{tables}</td>
  </tr>
<!-- END table -->

<!-- BEGIN hook -->
  <tr bgcolor="{bg_color}">
    <td>{hooks}</td>
  </tr>
<!-- END hook -->

<!-- BEGIN dep -->
  <tr bgcolor="{bg_color}">
    <td>{deps}</td>
  </tr>
<!-- END dep -->

<!-- BEGIN resolve -->
  <tr bgcolor="{bg_color}">
    <td>{resolution}</td>
  </tr>
<!-- END resolve -->

<!-- BEGIN submit -->
{goback}
<!-- END submit -->

<!-- BEGIN app_footer -->
  <tr>
    <td bgcolor="{bg_color}" colspan="5">{debug} {lang_debug}</td>
    <td bgcolor="{bg_color}" align="center">
     <a href="javascript:check_all('install')"><img src="templates/default/images/{check}" border="0" height="16" width="21" alt="{install_all}" /></a>
    </td>
    <td bgcolor="{bg_color}" align="center">
     <a href="javascript:check_all('upgrade')"><img src="templates/default/images/{check}" border="0" height="16" width="21" alt="{upgrade_all}" /></a>
    </td>
    <td bgcolor="{bg_color}">&nbsp;</td>
    <td bgcolor="{bg_color}" align="center">
      <a href="javascript:check_all('remove')"><img src="templates/default/images/{check}" border="0" height="16" width="21" alt="{remove_all}" /></a>
    </td>
  </tr>
</table>
<table border="0" width="70%" cellspacing="0" cellpadding="2">
  <tr>
    <td colspan="2" align="center">
     <input type="submit" name="submit" value="{submit}" />
     <input type="submit" name="cancel" value="{cancel}" />
    </td>
  </tr>
</table>
</form>
<!-- END app_footer -->

<!-- BEGIN footer -->
<br />
<table width="100%" cellspacing="0">
  <tr class="banner">
    <td>&nbsp;</td>
  </tr>
</table>
</div>
<!-- END footer -->
