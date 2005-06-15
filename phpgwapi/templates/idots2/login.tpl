<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset={charset}">
<meta name="author" content="eGroupWare http://www.phpgroupware.org">
<meta name="description" content="eGroupWare login screen">
<meta name="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
<meta name="keywords" content="eGroupWare login screen">
<link rel="icon" href="phpgwapi/templates/idots/images/favicon.ico" type="image/x-ico">
<link rel="shortcut icon" href="phpgwapi/templates/idots/images/favicon.ico">
<link href="phpgwapi/templates/idots/css/idots.css" type="text/css" rel="StyleSheet" />
<title>{website_title} - Login</title>
<style type="text/css">

/*body 
{
	height:100%;
}
*/
html, body {
	margin: 0;
	padding: 0;

}
input, select {
	clear: right;
	float: left;
	border: 1px solid #000;
	margin-left: 3px;
	margin-bottom: 1px;
	margin-top: 1px;	
}
label {
	float: left;
	text-align: right;
	clear: left;
	width: 170px;
	font-weight: 900;
	margin-bottom: 1px;
	margin-top: 1px;	
}
h1 {
	width: 100%;
	display: block;
	background-image: url('phpgwapi/templates/idots2/js/x-desktop/xDT/skins/IDOTS2/menu_back.png');
	border-bottom: 1px solid #7e7e7e;
	font-size: 12px;
	height: 26px;
	padding-top: 2px;
	margin: 0;

}
h1 div {
	clear: none;
	float: left;
	display: block;
	margin: 0;
	padding: 0;
	border: 0;
}
h1 span {
	clear: none;
	float: left;
	height: 25px;
	display: block;
	padding-left: 20px;
	padding-right: 20px;
	padding-top: 3px;
	background-image: url('phpgwapi/templates/idots2/js/x-desktop/xDT/skins/IDOTS2/btn_white_middle.png');
	background-repeat: repeat-x; 
	margin: 0;
}
#divMain
{
	height:85%;
}
#divPoweredBy {
        width: 600px;
        height: 25px;
        position: absolute;
        bottom: 25px;	
        left: 50%;		
        margin: 50px 0 0 -300px;
}
*{
	z-index:10;
}
img#key {
	position: absolute;
	top: 100px;
	right: 20px;
}

img#back{
	z-index:1;
	height:100%;
	width:100%;
	top:0;
	left:0;
	right:0;
	bottom:0;
}
#containerDiv
{
	position: absolute;
	top:50%;
	left: 50%;
	height: 220px;
	width: 400px;
	margin-left: -200px;
	margin-top: -150px;
	background-color: #E7E7E7;
	border: 2px outset #FFF;
}

div#bkground
{
	width:100%;
	height:100%;
	top:0;
	left:0;
	padding:0;
}
div#bkground img {
	width:100%;
	height:100%;
	top:0;
	left:0;
	padding:0;

}
</style>

<!-- this solves the internet explorer png-transparency bug, but only for ie 5.5 and higher --> 
<!--[if gte ie 5.5000]>
<script src="./phpgwapi/templates/idots2/js/pngfix.js" type=text/javascript>
</script>
<![endif]-->

</head>
<body bgcolor="#ffffff">
<div id="bkground"><img src="phpgwapi/templates/idots2/js/x-desktop/xDT/skins/IDOTS2/achtergrond.png"></div>
<div id="divLogo"><a href="{logo_url}" target="_blank"><img src="{logo_file}" border="0" alt="{logo_title}" title="{logo_title}"/></a></div>

<div id="containerDiv" >
<h1>
<div><img src="phpgwapi/templates/idots2/js/x-desktop/xDT/skins/IDOTS2/btn_white_left.png"/></div>
<span>Login</span>
<div><img src="phpgwapi/templates/idots2/js/x-desktop/xDT/skins/IDOTS2/btn_white_right.png"/></div><img src="phpgwapi/templates/idots2/js/x-desktop/xDT/skins/IDOTS2/winclose_over.png" style="float: right"></h1>
<div align="center">{lang_message}</div>
<div align="center">{cd}</div>
<p>&nbsp;</p>
<form name="login_form" method="post" action="{login_url}">



			{register_link}
				<input type="hidden" name="passwd_type" value="text">
				<input type="hidden" name="account_type" value="u">

				

<!-- BEGIN language_select -->
<label>{lang_language}:</label>
{select_language}

<!-- END language_select -->
<div>
	<label>{lang_username}:</label><input name="login" value="{cookie}" style="width: 100px; border: 1px solid silver;">
	<br>
</div>{select_domain}

	<label>{lang_password}:</label><input name="passwd" type="password" onChange="this.form.submit()" style="width: 100px; border: 1px solid silver;">
		<br><br>	<br><br>
		<label>&nbsp;</label><input type="submit" value="{lang_login}" name="submitit" style="border: 1px solid silver;">


<br><br><img src="phpgwapi/templates/{template_set}/images/password.png" id="key">

<p>&nbsp;</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
</form>
<script language="javascript1.2" type="text/javascript">
<!--
// position cursor in top form field
document.login_form.login.focus();
//-->
</script>




</div>
<div style="bottom:10px;left:10px;position:absolute;visibility:hidden;">
<img src="phpgwapi/templates/{template_set}/images/valid-html401.png" border="0" alt="Valid HTML 4.01">
<img src="phpgwapi/templates/{template_set}/images/vcss.png" border="0" alt="Valid CSS">
</div>
<div id="divPoweredBy" align="center">
<br/>
<a href="http://www.egroupware.org" target="_blank">eGroupWare</a> {version}</div>
</body>
</html>
