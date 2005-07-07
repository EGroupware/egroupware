<!-- BEGIN submit_button -->
	<input tabindex="{button_tabindex}" type="submit" value="{button_value}" name="{button_name}" class="blacktext">&nbsp;
<!-- END submit_button -->

<!-- BEGIN border_top -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<meta name="AUTHOR" content="eGroupWare inline documentation parser http://www.egroupware.org" />
		<meta name="description" content="eGroupWare documentation" />
		<meta name="keywords" content="eGroupWare documentation" />
		<title>Local setup - Login</title>
	</head>

	<body bgcolor="#FFFFFF">
<!-- END border_top -->

<!-- BEGIN group -->
		<h1>{group_name}</h1>
		{group_contents}
		<p>
<!-- END group -->

<!-- BEGIN object -->
		<h2><a href="{PHP_SELF}?object={object_id}">{object_name}</a></h2>
		{object_contents}
<!-- END object -->

<!-- BEGIN abstract -->
	<b>Abstract:</b> {abstract}<br />
<!-- END abstract -->

<!-- BEGIN generic -->
	<b>{generic_name}:</b> {generic_value}<br />
<!-- END generic -->

<!-- BEGIN generic_para -->
	<p><b>{generic_name}:</b> {generic_value}</p>
<!-- END generic_para -->

<!-- BEGIN generic_pre -->
		<b>{generic_name}:</b>
		<pre>
		{generic_value}
		</pre>
<!-- END generic_pre -->

<!-- BEGIN params -->
	<table border="1">
		<tr>
			<td>Name</td>
			<td>Details</td>
		</tr>
		{param_entry}
	</table>
<!-- END params -->

<!-- BEGIN param_entry -->
		<tr>
			<td>{name}</td>
			<td>{details}</td>
		</tr>
<!-- END param_entry -->

<!-- BEGIN border_bottom -->
	</body>
</html>
<!-- END border_bottom -->
