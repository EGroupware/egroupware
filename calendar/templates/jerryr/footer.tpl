<!-- $Id$ -->
<!-- BEGIN footer_table -->
<!--
<tr>
    <td>1</td>
</tr>
-->

<table border="0" width="100%" cellpadding="0" cellspacing="0">
    <tr>
	{table_row}
    </tr>
</table>
<!--       <tr>-->
<!-- END footer_table -->


<!-- BEGIN footer_row -->

    <form action="{action_url}" method="post" name="{form_name}">
<td valign="top">
	<span style="font-size:9px; font-weight:bold;">{label}:</span>
	{hidden_vars}
	<select name="{form_label}" onchange="{form_onchange}">
	{row}
	</select>
	<noscript><input type="submit" value="{go}"></noscript>
</td>
    </form>

<!-- END footer_row -->

<!-- BEGIN blank_row -->
     <td>
    {b_row}
    </td>
<!-- END blank_row -->
<!-- <td> -->

