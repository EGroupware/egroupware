<!-- $Id$ -->
<!-- BEGIN nntp_header -->
<script>
function check_all()
{
  for (i=0; i<document.allow.elements.length; i++) {
     if (document.allow.elements[i].type == "checkbox") {
       if (document.allow.elements[i].checked) {
          document.allow.elements[i].checked = false;
       } else {
          document.allow.elements[i].checked = true;
       }
     }	
  }
}
</script>
<p><center>{title}<br>
  <table border="0" width="70%">
    <tr>
      <td width="40%">
      <div align="center">
      <form method="POST" action="{action_url}">
        {common_hidden_vars}
        <input type="text" name="query" value="{search_value}">
            <input type="submit" name="search" value="{search}">
        <input type="submit" name="next" value="{next}">
      </form>
      </div>
      </td>
    </tr>
    <tr>{nml}</tr>
    <tr>{nmr}</tr>
  </table>
  <form name="allow" action="{action_url}" method="POST">
  {common_hidden_vars}
  <table border="0" width="70%">
    <tr class="th">
      <td align="center"><font size="2" face="{th_font}">{sort_con}</font></td>
      <td><font size="2" face="{th_font}">{sort_group}</font></td>
      <td align="center"><font size="2" face="{th_font}">{sort_active}</font></td>
    </tr>
<!-- END nntp_header -->

  {output} 

<!-- BEGIN nntp_list -->

    <tr class="{tr_color}">
      <td align="center"><font face="{th_font}">{con}</font></td>
      <td><font face="{th_font}">{group}</font></td>
      <td align="center"><font face="{th_font}">{active}</font></td>
    </tr>

<!-- END nntp_list -->

<!-- BEGIN nntp_footer -->

    <tr class="th">
      <td>&nbsp;</td>
      <td align="center"><input type="submit" name="submit" value="{lang_update}"></td>
      <td align=center>
        <a href="javascript:check_all()"><img src="{checkmark}" border="0" height="16" width="21"></a>
      </td>
    </tr>
  </table>
  </form>
</center>
<!-- END nntp_footer -->
