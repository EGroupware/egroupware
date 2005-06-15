<!-- BEGIN jscript -->
<script type="text/javascript">
var appUrls = '{appUrls}';
var appNames = '{appNames}';

var arrUrl = appUrls.split(',');
var arrNames = appNames.split(',');
function showImage()
{
	imgOb = document.getElementById("app");
	sel = document.getElementById("select");
	//alert(sel.value);
	for(i=0;i < arrNames.length;i++)
	{
		if (sel.value == arrNames[i])
		{
		//	alert(i);
		//	alert(arrUrl[i]);
			imgOb.src = arrUrl[i];
		}
	}
	document.forms['addshortcut'].elements['hitTop'].value = parent.hitTop;
	document.forms['addshortcut'].elements['hitLeft'].value = parent.hitLeft;
}


</script>
<!-- END jscript -->

<!-- BEGIN formposted -->
<script type="text/javascript">

parent.showShortcut('{title}', '{url}', '{img}', {hitTop}, {hitLeft} , '{type}');
			
parent.xDT.deleteWindow('short');


</script>
<!-- END formposted -->



<!-- BEGIN css -->

<style>
#select
{
	margin-top: 10px;
	margin-left: 5px;
	clear: none;
	float: left;
}

img#app
{
	float: left;
	margin-top: 25px;
	margin-left: 40px;
	clear: right;
}
#submit
{
	clear: left;
	display: block;
	margin-top: 0px;
	margin-left: 182px;
	margin-bottom: 5px;
	margin-right: 5px;
}
#lblAppBox
{
	margin-left: 5px;
	margin-top: 10px;
	float: left;
	clear: none;
}
</style>

<!-- END css -->

<!-- BEGIN jscript_xdesktop -->

<!-- END jcsrtip_xdesktop-->

<!-- BEGIN selstart -->

<form action="add_shortcut.php" method="POST" name="addshortcut">
	<label id="lblAppBox" >{selName}</label>
	<select onchange="showImage()" id='select' name='select'>
<!-- END selstart -->

<!-- BEGIN shortcut -->
		<option value="{name}">{item}</option>
<!-- END shortcut -->

<!-- BEGIN selend -->
	</select>

<!-- END selend -->

<!-- BEGIN img -->
<img src="{starturl}" id="app">
<input type="hidden" name="hitTop" value="">
<input type="hidden" name="hitLeft" value="">
<br><br><br>
<input type="submit" name="submit" value="{buttonName}" id ="submit">
</form>

<script type="text/javascript">
	showImage();
</script>
<!-- END img -->
