<!-- BEGIN header -->
 <br>
<!-- END header -->

<!-- BEGIN postheader -->
 <table align="center">
  <tr class="th">
   <td colspan="5" align="center">{lang_application}:&nbsp;{app_title}</td>
  </tr>
  <tr class="th">
   <form method="post" action="{action_url}">
   <td align="left">{lang_remove}</td>
   <td align="left">{lang_appname}</td>
   <td align="left">{lang_original}</td>
  </tr>
<!-- END postheader -->

<!-- BEGIN detail -->
  <tr class="{tr_class}">
   <td align="center"><input type="checkbox" name="delete[{mess_id}]" checked></td>
   <td>{transapp}</td>
   <td>{source_content}</td>
  </tr>
<!-- END detail -->

<!-- BEGIN prefooter -->
</table>
<br>
<table width="90%" align="center">
<!-- END prefooter -->

<!-- BEGIN footer -->
</tr>
</table>
<table width="90%" align="center">
  <tr valign="top">
    <td align="center">
     <input name="app_name"  type="hidden" value="{app_name}">
     <input name="sourcelang"  type="hidden" value="{sourcelang}">
     <input name="targetlang"  type="hidden" value="{targetlang}">
     <input type="submit" name="update" value="{lang_update}">
    </form>
    </td>
   <td align="center">
   <form method="post" action="{view_link}">
    <input type="submit" name="edit" value="{lang_view}">
   </form>
   </td>
  </tr>
</table>
<!-- END footer -->
