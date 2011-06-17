<!-- HELLO -->
<table width="100%" border="0" align="center">
	<tr>
		{left_next_matchs}
		<td align="center"><b>{description}</b></td>
		{right_next_matchs}
	</tr>
</table>

<table width="100%" border="0" cellspacing="1" cellpading="1">
	<thead>
<!-- BEGIN header_row -->
		<tr bgcolor="{th_bg}">
			{header_row_data}
		</tr>
<!-- END header_row -->
	</thead>
	<tbody id="nextMatchBody">
<!-- BEGIN row_list -->
		<tr id="{row_id}" bgcolor="{row_color}">
			{row_data}
		</tr>
<!-- END row_list -->
	</tbody>
</table>