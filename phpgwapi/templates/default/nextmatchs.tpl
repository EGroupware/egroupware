<!-- BEGIN nextmatchs -->
<form method="POST" action="{form_action}">
 <input type="hidden" name="filter" value="{filter_value}">
 <input type="hidden" name="qfield" value="{qfield_value}">
 <input type="hidden" name="start" value="{start_value}">
 <input type="hidden" name="order" value="{order_value}">
 <input type="hidden" name="sort" value="{sort_value}">
 <input type="hidden" name="query" value="{query_value}">
   		
 <table width="{table_width}" height="50" border="0" bgcolor="{th_bg}" cellspacing="0" cellpadding="0">
  <tr>
{left}
{search}
{filter}
{right}
  </tr>
 </table>

<br>

</form>
<!-- END nextmatchs -->


<!-- BEGIN filter -->
<td>{select}<noscript><input type="submit" value="{lang_filter}"></noscript></td>
<!-- END filter -->


<!-- BEGIN form -->
    <td width="2%" align="{align}">
     <form method="POST" action="{action}">
{hidden}      <input type="image" src="{img}" border="{border}">
     <form>
    </td>
<!-- END form -->


<!-- BEGIN icon -->
<td width="2%" align="{align}">&nbsp;{_link}</td>
<!-- END icon -->


<!-- BEGIN link -->
   <td width="2%" align="{align}"><img src="{img}" border="{border}" width="12" height="12" alt="{label}"></td>
<!-- END link -->


<!-- BEGIN search -->
  <td>
    <input type="text" name="query" value="{query_value}">&nbsp;{searchby}<input type="submit" name="Search" value="{lang_search}">
  </td>
<!-- END search -->

