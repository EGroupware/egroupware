<!-- $Id$ -->
<!-- BEGIN custom_fields -->
{lang_error}<br>
<form action="{action_url}" method="post">
 {hidden_vars}
 <center>
  <table border="0" width="90%">
   <tr class="th">
    <th align="left" width="30%">{lang_name}</th>
    <th>{lang_length}</th>
    <th>{lang_shown}</th>
    <th>{lang_order}</th>
    <th>{lang_title}</th>
    <th>{lang_disabled}</th>
    <th>&nbsp;</th>
   </tr>
   {rows}
   <tr class="th">
    <td>{name}</td>
    <td align="center">{length}</td>
    <td align="center">{shown}</td>
    <td align="center">{order}</td>
    <td align="center">{title}</td>
    <td align="center">{disabled}</td>
    <td align="center">{button}</td>
   </tr>
   <tr>
    <td>{save_button} &nbsp; {cancel_button}</td>
   </tr>
  </table>
 </center>
</form>
<br>
<!-- END custom_fields -->

<!-- BEGIN row -->
   <tr bgcolor="{tr_color}">
    <td>{name}</td>
    <td align="center">{length}</td>
    <td align="center">{shown}</td>
    <td align="center">{order}</td>
    <td align="center">{title}</td>
    <td align="center">{disabled}</td>
    <td align="center">{button}</td>
   </tr>
<!-- END row -->
