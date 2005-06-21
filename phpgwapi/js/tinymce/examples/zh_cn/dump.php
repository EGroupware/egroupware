<html>
<head>
<title>显示TinyMCE的输出</title>
<meta http-equiv="Content-Type" content="text/html; charset=gb2312">
</head>
<body>

<h2>post过来的HTML输出</h2>

<table border="1" width="100%">
	<tr bgcolor="#CCCCCC"><td width="1%" nowrap="nowrap"><strong>表单组件</strong></td><td><strong>HTML输出</strong></td></tr>
	<? foreach ($_POST as $name => $value) { ?>
		<tr><td width="1%" nowrap="nowrap"><?=$name?></td><td><?=stripslashes($value)?></td></tr>
	<? } ?>
</table>

<h2>post过来的源文件</h2>

<table border="1" width="100%">
	<tr bgcolor="#CCCCCC"><td width="1%" nowrap="nowrap"><strong>表单组件</td><td><strong>Source输出</strong></td></tr>
	<? foreach ($_POST as $name => $value) { ?>
		<tr><td width="1%" nowrap="nowrap"><?=$name?></td><td><?=htmlentities(stripslashes($value))?></td></tr>
	<? } ?>
</table>

</body>
</html>

