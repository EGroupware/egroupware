<!-- BEGIN index -->
<table width="100%" border="1" cols="{colspan}">
 <tr>
  <td align="center" colspan="{colspan}">{error}
  </td>
 </tr>
 <tr>
  <td align="center" colspan="{colspan}">
   <form action="{form_action}" method="post">
   <table width="100%" border="1">
    <tr{tr_extras}>
     <td align="center">
      {img_up}
      {help_up}
     </td>
     <td align="center">
      {img_home}
      {dir}
      {help_home}
     </td>
    </tr>
   </table>
   </hr>
  </td>
 </tr>{col_row}
 <tr>
  <td align="center" colspan="{colspan}">
   </hr>{buttons}
   </form>
  </td>
 </tr>
 <tr>
  <td align="center" colspan="{colspan}">{info}
  </td>
 </tr>
 <tr>
  <td align="center" colspan="{colspan}">{uploads}
  </td>
 </tr>
</table>
<!-- END index -->
<!-- BEGIN column_rows -->
 <tr{tr_extras}>{col_headers}
 </tr>
<!-- END column_rows -->
<!-- BEGIN column_headers -->
  <td{td_extras}><font size="-2">{column_header}</font></td>
<!-- END column_headers -->
<!-- BEGIN column_headers_normal -->
  <td{td_extras}>{column_header}</td>
<!-- END column_headers_normal -->

