<!-- $Id$ -->
<form action="{form_link}" method="post" name="{form_name}form">
<td width="{form_width}%" align="center" valign="top" style="padding-top:16px">
	<span style="font-size:10px"><b>{title}:</b>
    {hidden_vars}    
    <select name="{form_name}" onchange="document.{form_name}form.submit()">
		{form_options}
	</select>
    <noscript>
    	<input type="submit" value="{button_value}">
    </noscript>
	</span>
</td>
</form>