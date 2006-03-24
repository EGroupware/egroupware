<!-- BEGIN admin_line -->
  <tr class="{tr_color}">
   <td>{num}:</td>
   <td>{val_valid}</td>
   <td>{val_trans}</td>
   <td>{val_ip}</td>
  </tr>
<!-- END admin_line -->

<!-- BEGIN info_admin -->
<br>
<form action="{action_url}" method="POST">
 <table border="0">
  <tr class="th">
   <td colspan="4"><b>{lang_responsible_rights}</b></td>
  </tr>
  <tr class="row_off">
   <td colspan="3">{lang_implicit_rights}</td>
   <td>{implicit_rights}</td>
  </tr>
  <tr class="row_on">
   <td colspan="3">{lang_responsible_edit}</td>
   <td>{responsible_edit}</td>
  </tr>
  <tr class="row_off">
   <td colspan="4">&nbsp;</td>
  </tr>
  <tr class="th">
   <td colspan="4">{text}</td>
  </tr>
  <tr class="th">
   <td>#</td>
   <td>{lang_valid}</td>
   <td>{lang_trans}</td>
   <td>{lang_ip}</td>
  </tr>
  {admin_lines}
  <tr>
   <td colspan="4" align="left">
    {save_button} &nbsp; {apply_button} &nbsp; {cancel_button}
   </td>
  </tr>
 </table>
</form>
<!-- END info_admin -->
