<!-- BEGIN year -->
{print}
<center>
<table id="calendar_year_table" border="0" cellspacing="3" cellpadding="4" cols=4>
	<tr>
		<td align="center">
			{left_link}
		</td>
		<td align="center">
			<font face="{font}" size="+1">{year_text}</font>
		</td>
		<td align="center">
			{right_link}
		</td>
		{row}
	</tr>
</table>
</center>
<p>
<div class="calendar_link_print">
{printer_friendly}
</div>
<!-- END year -->

<!-- BEGIN month -->
<td valign="top">
	{mini_month}
</td>
<!-- END month -->

<!-- BEGIN month_sep -->
</tr>
<tr valign="top">
<!-- END month_sep -->

