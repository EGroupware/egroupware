<!-- BEGIN submit_button -->
	<input tabindex="{button_tabindex}" type="submit" value="{button_value}" name="{button_name}" class="blacktext">&nbsp;
<!-- END submit_button -->

<!-- BEGIN border_top -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
	<HEAD>
		<META http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
		<META name="AUTHOR" content="phpGroupWare inline documentation parser http://www.phpgroupware.org">
		<META NAME="description" CONTENT="phpGroupWare documentation">
		<META NAME="keywords" CONTENT="phpGroupWare documentation">
		<TITLE>Local setup - Login</TITLE>
	</HEAD>

	<BODY bgcolor="#FFFFFF">
<!-- END border_top -->

<!-- BEGIN group -->
		<H1>{group_name}</H1>
		{group_contents}
		<P>
<!-- END group -->

<!-- BEGIN object -->
		<H2><A href="{PHP_SELF}?object={object_id}">{object_name}</A></H2>
		{object_contents}
<!-- END object -->

<!-- BEGIN abstract -->
	<B>Abstract:</B> {abstract}<BR>
<!-- END abstract -->

<!-- BEGIN generic -->
	<B>{generic_name}:</B> {generic_value}<BR>
<!-- END generic -->

<!-- BEGIN generic_para -->
	<P><B>{generic_name}:</B> {generic_value}</P>
<!-- END generic_para -->

<!-- BEGIN generic_pre -->
		<B>{generic_name}:</B>
		<PRE>
		{generic_value}
		</PRE>
<!-- END generic_pre -->

<!-- BEGIN params -->
	<TABLE border="1">
		<TR>
			<TD>Name</TD>
			<TD>Details</TD>
		</TR>
		{param_entry}
	</TABLE>
<!-- END params -->

<!-- BEGIN param_entry -->
		<tr>
			<td>{name}</td>
			<td>{details}</td>
		</tr>
<!-- END param_entry -->

<!-- BEGIN border_bottom -->
	</BODY>
</HTML>
<!-- END border_bottom -->
