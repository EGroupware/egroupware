<?php
/***********************************************************************
** Title.........:    Insert File Dialog, File Manager
** Version.......:    1.1
** Authors.......:    Al Rashid <alrashid@klokan.sk>
**                    Xiang Wei ZHUO <wei@zhuo.org>
** Filename......:    insert_file.php
** URL...........:    http://alrashid.klokan.sk/insFile/
** Last changed..:    23 July 2004
***********************************************************************/
require('config.inc.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<title>Insert File</title>
<?php
	echo '<META HTTP-EQUIV="Pragma" CONTENT="no-cache">'."\n";
	echo '<META HTTP-EQUIV="Cache-Control" CONTENT="no-cache">'."\n";
	echo '<META HTTP-EQUIV="Expires" CONTENT="Fri, Oct 24 1976 00:00:00 GMT">'."\n";
	echo '<meta http-equiv="content-language" content="'.$MY_LANG.'" />'."\n";
	echo '<meta http-equiv="Content-Type" content="text/html; charset='.$MY_CHARSET.'" />'."\n";
	echo '<meta name="author" content="AlRashid, www: http://alrashid.klokan.sk; mailto:alrashid@klokan.sk" />'."\n";
?>
<script type="text/javascript" src="js/popup.js"></script>
<script type="text/javascript" src="js/dialog.js"></script>
<script language="javascript" src="../../../tiny_mce_popup.js"></script>
<style type="text/css">
	body { padding: 5px; }
	table {
		font: 11px Tahoma,Verdana,sans-serif;
	}
	form p {
		margin-top: 5px;
		margin-bottom: 5px;
	}
	fieldset { padding: 0px 10px 5px 5px; }
	select, input, button { font: 11px Tahoma,Verdana,sans-serif; }
	button { width: 70px; }

	.title { background: #ddf; color: #000; font-weight: bold; font-size: 120%; padding: 3px 10px; margin-bottom: 10px;
	border-bottom: 1px solid black; letter-spacing: 2px;
	}
	form { padding: 0px; margin: 0px; }
	a { padding: 2px; border: 1px solid ButtonFace;        }
	a img        { border: 0px; vertical-align:bottom; }
	a:hover { border-color: ButtonHighlight ButtonShadow ButtonShadow ButtonHighlight; }
</style>

<script language="JavaScript" type="text/JavaScript">
/*<![CDATA[*/
var preview_window = null;
var resize_iframe_constant = 150;
<?php
if (is_array($MY_DENY_EXTENSIONS)) {
	echo 'var DenyExtensions = [';
	foreach($MY_DENY_EXTENSIONS as $value) echo '"'.$value.'", ';
	echo '""];
	';
}
if (is_array($MY_ALLOW_EXTENSIONS)) {
	echo 'var AllowExtensions = [';
	foreach($MY_ALLOW_EXTENSIONS as $value) echo '"'.$value.'", ';
	echo '""];
	';
}
?>

function Init() {
};

function onOK() {
	if (window.opener) {
		var myPath = fileManager.document.getElementById('form2').elements["path"].value;
		if(fileManager.stb) {
			var fileItems = fileManager.stb.getSelectedItems();
		}
		else { // in icon mode, only one file could be selected at onece
			var fileItems = '1';
		}
		var returnFiles = new Array();
		var base_path = '<?php echo $MY_BASE_URL; ?>';
		var path = base_path+myPath;
		var editor_url = tinyMCE.baseURL;
		var plugin_url = "/plugins/filemanager/InsertFile/";

		var output = "";
		for (var i=0; i<fileItems.length; i++) {
				var param = new Object();
				
				if(fileItems != 1) {
					var strId = fileItems[i].getAttribute("id").toString();
					var trId = parseInt(strId.substring(1, strId.length));
					param['f_icon'] = editor_url+plugin_url+fileManager.fileJSArray[trId][0];
					param['f_size'] = fileManager.fileJSArray[trId][2];
					param['f_date'] = fileManager.fileJSArray[trId][3];
				}

				// if only one file is selected, we take the parameters out of the input fields
				if(fileItems.length == 1) {
					var fields = ["f_url", "f_alt", "f_caption", "f_align", "f_border", "f_horiz", "f_vert", "f_width", "f_height", "f_ext"];
					for (var i in fields) {
						param[fields[i]] = (MM_findObj(fields[i])).value;
					}
					if(param['f_url'].length < 1){
						alert("You must enter the URL");
						(MM_findObj('f_url')).focus;
						return false;
					}
				}
				// otherwise we need to generate some usefull values
				else {
				
				}
				
				if((MM_findObj("f_action")).value == "f_action_filelink"){
					var icon = "";
					var caption = "";
					var formObj = document.forms[0];
					if (formObj.f_addicon.checked==true) icon = '<img src="' + param['f_icon'] + '" alt="' + param['f_caption'] + '">&nbsp;';
					if (formObj.f_addsize.checked==true || formObj.f_adddate.checked==true) caption = caption + ' (<span style="font-size:80%">';
					if (formObj.f_addsize.checked==true) caption = caption + param['f_size'];
					if (formObj.f_adddate.checked==true) caption = caption + ' ' + param['f_date'];
					if (formObj.f_addsize.checked==true || formObj.f_adddate.checked==true) caption = caption + '</span>) ';
					output = output + icon + '<a href="' + param['f_url'] + '">' + param['f_caption'] + '</a>' + caption;
				}
				if((MM_findObj("f_action")).value == "f_action_inline"){
					if(param['f_ext'] == 'jpg' || param['f_ext'] == 'jpeg' || param['f_ext'] == 'gif' || param['f_ext'] == 'png'){
						output = output + '<img src="' + '/' + param['f_url'] + '"';
					}
					else
					{
						var inlineobj = true;
						output = output + '<object src="' + '/' + param['f_url'] + '"';
					}
					if(param['f_alt'] > 0) output = output + 'alt="' + param['f_alt'] + '"';
					if(param['f_align'] > 0) output = output + 'align="' + param['f_align'] + '"';
					if(param['f_border'] > 0) output = output + 'border="' + param['f_border'] + '"';
					if(param['f_width'] > 0) output = output + 'width="' + param['f_width'] + '"';
					if(param['f_height'] > 0) output = output + 'height="' + param['f_height'] + '"';
					output = output + '>';
					if(inlineobj == true) output = output + '</object>';
				}
		}
		tinyMCE.execCommand("mceInsertContent",true,output);
		top.close();
	}

};

function onCancel() {
	top.close();
	return false;
};

function changeDir(selection) {
	changeLoadingStatus('load');
	var newDir = selection.options[selection.selectedIndex].value;
	var postForm2 = fileManager.document.getElementById('form2');
	postForm2.elements["action"].value="changeDir";
	postForm2.elements["path"].value=newDir;
	postForm2.submit();
}

function goUpDir() {
	var selection = document.forms[0].path;
	var dir = selection.options[selection.selectedIndex].value;
	if(dir != '/'){
		changeLoadingStatus('load');
		var postForm2 = fileManager.document.getElementById('form2');
		postForm2.elements["action"].value="changeDir";
		postForm2.elements["path"].value=postForm2.elements["uppath"].value;
		postForm2.submit();
	}
}

function newFolder() {
	var selection = document.forms[0].path;
	var path = selection.options[selection.selectedIndex].value;
	var folder = prompt('<?php echo $MY_MESSAGES['newfolder']; ?>','');
	if (folder) {
		changeLoadingStatus('load');
		var postForm2 = fileManager.document.getElementById('form2');
		postForm2.elements["action"].value="createFolder";
		postForm2.elements["file"].value=folder;
		postForm2.submit();
	}
	return false
}

function deleteFile() {
	var folderItems = fileManager.sta.getSelectedItems();
	var folderItemsLength = folderItems.length;
	var fileItems = fileManager.stb.getSelectedItems();
	var fileItemsLength = fileItems.length;
	var message = "<?php echo $MY_MESSAGES['delete']; ?>";
	if ((folderItemsLength == 0) && (fileItemsLength == 0)) return false;
	if (folderItemsLength > 0) {
		message = message + " " + folderItemsLength + " " + "<?php echo $MY_MESSAGES['folders']; ?>";
	}
	if (fileItemsLength > 0) {
		message = message + " " + fileItemsLength + " " + "<?php echo $MY_MESSAGES['files']; ?>";
	}
	if (confirm(message+" ?")) {
		var postForm2 = fileManager.document.getElementById('form2');
		for (var i=0; i<folderItemsLength; i++) {
			var strId = folderItems[i].getAttribute("id").toString();
			var trId = parseInt(strId.substring(1, strId.length));
				var i_field = fileManager.document.createElement('INPUT');
			i_field.type = 'hidden';
			i_field.name = 'folders[' + i.toString() + ']';
				i_field.value = fileManager.folderJSArray[trId][1];
			postForm2.appendChild(i_field);
		}
		for (var i=0; i<fileItemsLength; i++) {
			var strId = fileItems[i].getAttribute("id").toString();
			var trId = parseInt(strId.substring(1, strId.length));
				var i_field = fileManager.document.createElement('INPUT');
			i_field.type = 'hidden';
			i_field.name = 'files[' + i.toString() + ']';
				i_field.value = fileManager.fileJSArray[trId][1];
			postForm2.appendChild(i_field);
		}
		changeLoadingStatus('load');
		postForm2.elements["action"].value="delete";
		postForm2.submit();
	}
}

function renameFile() {
	var folderItems = fileManager.sta.getSelectedItems();
	var folderItemsLength = folderItems.length;
	var fileItems = fileManager.stb.getSelectedItems();
	var fileItemsLength = fileItems.length;
	var postForm2 = fileManager.document.getElementById('form2');
	if ((folderItemsLength == 0) && (fileItemsLength == 0)) return false;
	if (!confirm('<?php echo $MY_MESSAGES['renamewarning']; ?>')) return false;
	for (var i=0; i<folderItemsLength; i++) {
		var strId = folderItems[i].getAttribute("id").toString();
		var trId = parseInt(strId.substring(1, strId.length));
		var newname = prompt('<?php echo $MY_MESSAGES['renamefolder']; ?>', fileManager.folderJSArray[trId][1]);
		if (!newname) continue;
		if (!newname == fileManager.folderJSArray[trId][1]) continue;
		var i_field = fileManager.document.createElement('INPUT');
		i_field.type = 'hidden';
		i_field.name = 'folders[' + i.toString() + '][oldname]';
			i_field.value = fileManager.folderJSArray[trId][1];
		postForm2.appendChild(i_field);
		var ii_field = fileManager.document.createElement('INPUT');
		ii_field.type = 'hidden';
		ii_field.name = 'folders[' + i.toString() + '][newname]';
			ii_field.value = newname;
		postForm2.appendChild(ii_field);
	}
	for (var i=0; i<fileItemsLength; i++) {
		var strId = fileItems[i].getAttribute("id").toString();
		var trId = parseInt(strId.substring(1, strId.length));
		var        newname = getNewFileName(fileManager.fileJSArray[trId][1]);
		if (!newname) continue;
		if (newname == fileManager.fileJSArray[trId][1]) continue;
			var i_field = fileManager.document.createElement('INPUT');
		i_field.type = 'hidden';
		i_field.name = 'files[' + i.toString() + '][oldname]';
			i_field.value = fileManager.fileJSArray[trId][1];
		postForm2.appendChild(i_field);
		var ii_field = fileManager.document.createElement('INPUT');
		ii_field.type = 'hidden';
		ii_field.name = 'files[' + i.toString() + '][newname]';
			ii_field.value = newname;
		postForm2.appendChild(ii_field);
	}
	changeLoadingStatus('load');
	postForm2.elements["action"].value="rename";
	postForm2.submit();
	}

function moveFile() {
	var folderItems = fileManager.sta.getSelectedItems();
	var folderItemsLength = folderItems.length;
	var fileItems = fileManager.stb.getSelectedItems();
	var fileItemsLength = fileItems.length;
	var postForm2 = fileManager.document.getElementById('form2');
	if ((folderItemsLength == 0) && (fileItemsLength == 0)) return false;
	if (!confirm('<?php echo $MY_MESSAGES['renamewarning']; ?>')) return false;
	var postForm2 = fileManager.document.getElementById('form2');
	Dialog("move.php", function(param) {
		if (!param) // user must have pressed Cancel
			return false;
		else {
			postForm2.elements["newpath"].value=param['newpath'];
			moveFiles();
		}
	}, null);
}

function changeview(view){
	if(view.length > 1){
		var postForm2 = fileManager.document.getElementById('form2');
		postForm2.elements['view'].value=view;
		postForm2.submit();
	}
	
}

function moveFiles() {
	var folderItems = fileManager.sta.getSelectedItems();
	var folderItemsLength = folderItems.length;
	var fileItems = fileManager.stb.getSelectedItems();
	var fileItemsLength = fileItems.length;
	var postForm2 = fileManager.document.getElementById('form2');
	for (var i=0; i<folderItemsLength; i++) {
		var strId = folderItems[i].getAttribute("id").toString();
		var trId = parseInt(strId.substring(1, strId.length));
			var i_field = fileManager.document.createElement('INPUT');
		i_field.type = 'hidden';
		i_field.name = 'folders[' + i.toString() + ']';
			i_field.value = fileManager.folderJSArray[trId][1];
		postForm2.appendChild(i_field);
	}
	for (var i=0; i<fileItemsLength; i++) {
		var strId = fileItems[i].getAttribute("id").toString();
		var trId = parseInt(strId.substring(1, strId.length));
		var i_field = fileManager.document.createElement('INPUT');
		i_field.type = 'hidden';
		i_field.name = 'files[' + i.toString() + ']';
			i_field.value = fileManager.fileJSArray[trId][1];
		postForm2.appendChild(i_field);
	}
	changeLoadingStatus('load');
	postForm2.elements["action"].value="move";
	postForm2.submit();
}

function openFile() {
	var urlPrefix = "<?php echo '/'. $MY_URL_TO_OPEN_FILE; ?>";
	var myPath = fileManager.document.getElementById('form2').elements["path"].value;
	var folderItems = fileManager.sta.getSelectedItems();
	var folderItemsLength = folderItems.length;
	var fileItems = fileManager.stb.getSelectedItems();
	var fileItemsLength = fileItems.length;

	for (var i=0; i<folderItemsLength; i++) {
		var strId = folderItems[i].getAttribute("id").toString();
		var trId = parseInt(strId.substring(1, strId.length));
		window.open(urlPrefix+myPath+fileManager.folderJSArray[trId][1],'','');
		}
	for (var i=0; i<fileItemsLength; i++) {
		var strId = fileItems[i].getAttribute("id").toString();
		var trId = parseInt(strId.substring(1, strId.length));
			window.open(urlPrefix+myPath+fileManager.fileJSArray[trId][1],'','');
	}
}

function doUpload() {
	var isOK = 1;
	var fileObj = document.forms[0].uploadFile;
	if (fileObj == null) return false;

	newname = fileObj.value;
	isOK = checkExtension(newname);
	if (isOK == -2) {
			alert('<?php echo $MY_MESSAGES['extnotallowed']; ?>');
			return false;
	}
	if (isOK == -1) {
		alert('<?php echo $MY_MESSAGES['extmissing']; ?>');
		return false;
	}
	changeLoadingStatus('upload');
}

function checkExtension(name) {
	var regexp = /\/|\\/;
	var parts = name.split(regexp);
	var filename = parts[parts.length-1].split(".");
	if (filename.length <= 1) {
		return(-1);
	}
	var ext = filename[filename.length-1].toLowerCase();

	for (i=0; i<DenyExtensions.length; i++) {
		if (ext == DenyExtensions[i]) return(-2);
	}
	for (i=0; i<AllowExtensions.length; i++) {
		if (ext == AllowExtensions[i])        return(1);
	}
	return(-2);
}

function getNewFileName(name) {
	var isOK = 1;
	var newname='';
	do {
		newname = prompt('<?php echo $MY_MESSAGES['renamefile']; ?>', name);
		if (!newname) return false;
		isOK = checkExtension(newname);
		if (isOK == -2) alert('<?php echo $MY_MESSAGES['extnotallowed']; ?>');
		if (isOK == -1) alert('<?php echo $MY_MESSAGES['extmissing']; ?>');
	} while (isOK != 1);
		return(newname);
}

function selectFolder() {
	Dialog("move.php", function(param) {
		if (!param) // user must have pressed Cancel
			return false;
		else {
			var postForm2 = fileManager.document.getElementById('form2');
			postForm2.elements["newpath"].value=param['newpath'];
		}
	}, null);

}

function refreshPath(){
	var selection = document.forms[0].path;
	changeDir(selection);
}

function winH() {
	if (window.innerHeight)
	return window.innerHeight;
	else if
	(document.documentElement &&
	document.documentElement.clientHeight)
	return document.documentElement.clientHeight;
	else if
	(document.body && document.body.clientHeight)
	return document.body.clientHeight;
	else
	return null;
}

function resize_iframe() {
	document.getElementById("fileManager").height=winH()-resize_iframe_constant;//resize the iframe according to the size of the window
}

function MM_findObj(n, d) { //v4.01
	var p,i,x;  if(!d) d=document; if((p=n.indexOf("?"))>0&&parent.frames.length) {
	d=parent.frames[n.substring(p+1)].document; n=n.substring(0,p);}
	if(!(x=d[n])&&d.all) x=d.all[n]; for (i=0;!x&&i<d.forms.length;i++) x=d.forms[i][n];
	for(i=0;!x&&d.layers&&i<d.layers.length;i++) x=MM_findObj(n,d.layers[i].document);
	if(!x && d.getElementById) x=d.getElementById(n); return x;
}

function MM_showHideLayers() { //v6.0
	var i,p,v,obj,args=MM_showHideLayers.arguments;
	for (i=0; i<(args.length-2); i+=3) if ((obj=MM_findObj(args[i]))!=null) { v=args[i+2];
	if (obj.style) { obj=obj.style; v=(v=='show')?'visible':(v=='hide')?'hidden':v; }
	obj.visibility=v; }
}

function changeLoadingStatus(state) {
	var statusText = null;
	if(state == 'load') {
		statusText = '<?php echo $MY_MESSAGES['loading']; ?> ';
	}
	else if(state == 'upload') {
		statusText = '<?php echo $MY_MESSAGES['uploading']; ?>';
	}
	if(statusText != null) {
		var obj = MM_findObj('loadingStatus');
		if (obj != null && obj.innerHTML != null)
			obj.innerHTML = statusText;
		MM_showHideLayers('loading','','show');
	}
}

function toggleConstrains(constrains) 
{
	if(constrains.checked) 
	{
		document.locked_img.src = "ImageManager/locked.gif";	
		checkConstrains('width') 
	}
else
	{
		document.locked_img.src = "ImageManager/unlocked.gif";	
	}
}

function checkConstrains(changed) 
{
	//alert(document.form1.constrain_prop);
	var constrained = document.form1.constrain_prop.checked;

	if(constrained) 
	{
		var orginal_width = parseInt(document.form1.orginal_width.value);
		var orginal_height = parseInt(document.form1.orginal_height.value);

		var width = parseInt(document.form1.f_width.value);
		var height = parseInt(document.form1.f_height.value);

		if(orginal_width > 0 && orginal_height > 0) 
		{
			if(changed == 'width' && width > 0) {
					document.form1.f_height.value = parseInt((width/orginal_width)*orginal_height);
			}

			if(changed == 'height' && height > 0) {
					document.form1.f_width.value = parseInt((height/orginal_height)*orginal_width);
			}
		}
	}

}

function P7_Snap() //v2.62 by PVII
{
	var x,y,ox,bx,oy,p,tx,a,b,k,d,da,e,el,args=P7_Snap.arguments;a=parseInt(a);
	for (k=0; k<(args.length-3); k+=4)
	if ((g=MM_findObj(args[k]))!=null)
	{
		el=eval(MM_findObj(args[k+1]));
		a=parseInt(args[k+2]);b=parseInt(args[k+3]);
		x=0;y=0;ox=0;oy=0;p="";tx=1;da="document.all['"+args[k]+"']";
		if(document.getElementById) 
		{
			d="document.getElementsByName('"+args[k]+"')[0]";
			if(!eval(d)) 
			{
				d="document.getElementById('"+args[k]+"')";
				if(!eval(d)) 
				{
					d=da;
				}
			}
		}
		else if(document.all) 
		{
			d=da;
		}
		if (document.all || document.getElementById) 
		{
			while (tx==1) 
			{
				p+=".offsetParent";
				if(eval(d+p)) 
				{
					x+=parseInt(eval(d+p+".offsetLeft"));
					y+=parseInt(eval(d+p+".offsetTop"));
				}
				else
				{
					tx=0;
				}
			}
			ox=parseInt(g.offsetLeft);
			oy=parseInt(g.offsetTop);
			var tw=x+ox+y+oy;
			if(tw==0 || (navigator.appVersion.indexOf("MSIE 4")>-1 && navigator.appVersion.indexOf("Mac")>-1))
			{
				ox=0;oy=0;if(g.style.left){x=parseInt(g.style.left);y=parseInt(g.style.top);
				}else{var w1=parseInt(el.style.width);bx=(a<0)?-5-w1:-10;
				a=(Math.abs(a)<1000)?0:a;b=(Math.abs(b)<1000)?0:b;
				x=document.body.scrollLeft + event.clientX + bx;
				y=document.body.scrollTop + event.clientY;}
			}
		}
		else if (document.layers)
		{
			x=g.x;y=g.y;var q0=document.layers,dd="";
			for(var s=0;s<q0.length;s++) 
			{
				dd='document.'+q0[s].name;
				if(eval(dd+'.document.'+args[k])) 
				{
					x+=eval(dd+'.left');
					y+=eval(dd+'.top');
					break;
				}
			}
		}
		if(el)
		{
			e=(document.layers)?el:el.style;
			var xx=parseInt(x+ox+a),yy=parseInt(y+oy+b);
			if(navigator.appName=="Netscape" && parseInt(navigator.appVersion)>4){xx+="px";yy+="px";}
			if(navigator.appVersion.indexOf("MSIE 5")>-1 && navigator.appVersion.indexOf("Mac")>-1)
			{
				xx+=parseInt(document.body.leftMargin);
				yy+=parseInt(document.body.topMargin);
				xx+="px";yy+="px";
			}
			e.left=xx;e.top=yy;
		}
	}
}

function refresh()
{
	var selection = document.forms[0].dirPath;
	updateDir(selection);
}

function showAction(action) 
{
	MM_showHideLayers('f_action_inline_values','','hide');
	MM_showHideLayers('f_action_filelink_values','','hide');
	MM_showHideLayers('f_action_upload_values','','hide');
	MM_showHideLayers(action + '_values','','show');
}
/*]]>*/
        </script>
</head>
<body onload="Init();">
                <div class="title"><img src="../images/filemanager.png" border="0" align="absmiddle">
                        <?php echo $MY_MESSAGES['insertfile']; ?>
                </div>
                <form action="files.php?dialogname=<?php echo $MY_NAME; ?>" name="form1" method="post" target="fileManager" enctype="multipart/form-data">
                        <div id="loading" style="position:absolute; left:200px; top:130px; width:184px; height:48px; z-index:1" class="statusLayer">
                                <div id= "loadingStatus" align="center" style="font-size:large;font-weight:bold;color:#CCCCCC;font-family: Helvetica, sans-serif; z-index:2;  ">
                                <?php echo $MY_MESSAGES['loading']; ?>
                                </div>
                        </div>
                          <fieldset>
                                <legend>
                                        <?php
                                        echo $MY_MESSAGES['filemanager'];
//                                         echo '<span style="font-size:x-small; "> - '.$MY_MESSAGES['ctrlshift'].'</span>';
                                        ?>
                                </legend>
                                <div style="margin:5px;">
                                        <label for="path">
                                                <?php echo $MY_MESSAGES['directory']; ?>
                                        </label>
                                          <select name="path" id="path" style="width:30em" onChange="changeDir(this)">
                                                  <option value="/">/</option>
                                        </select>

                                        <?php
                                                echo '<a href="#" onClick="javascript:goUpDir();"><img src="img/up.png" width="18" height="18" border="0" title="'.$MY_MESSAGES['up'].'" /></a>';
                                                if ($MY_ALLOW_CREATE) {
                                                        echo '<a href="#" onClick="javascript:newFolder();"><img src="img/folder_new.png"  width="18" height="18" border="0" title="'.$MY_MESSAGES['newfolder'].'" /></a>';
                                                }
                                                if ($MY_ALLOW_DELETE) {
                                                        echo '<a href="#" onClick="javascript:deleteFile();"><img src="img/remove.png" width="18" height="18" border="0" title="'.$MY_MESSAGES['delete'].'" /></a>';
                                                }
                                                if ($MY_ALLOW_RENAME) {
                                                        echo '<a href="#" onClick="javascript:renameFile();"><img src="img/revert.png" width="18" height="18" border="0" title="'.$MY_MESSAGES['rename'].'" /></a>';
                                                }
                                                if ($MY_ALLOW_MOVE) {
                                                        echo '<a href="#" onClick="javascript:moveFile();"><img src="img/move.png" width="18" height="18" border="0" title="'.$MY_MESSAGES['move'].'" /></a>';
                                                }
                                                echo '<a href="#" onClick="javascript:openFile();"><img src="img/thumbnail.png"  width="18" height="18" border="0" title="'.$MY_MESSAGES['openfile'].'" /></a>';
                                                echo '|';
                                                echo '<a href="#" onClick="javascript:changeview(\'text\');"><img src="img/view_text.png" width="18" height="18" border="0" title="'.$MY_MESSAGES['textline'].'" /></a>';
                                                echo '<a href="#" onClick="javascript:changeview(\'icon\');"><img src="img/view_icon.png" width="18" height="18" border="0" title="'.$MY_MESSAGES['thumbnails'].'" /></a>';

                                     ?>

                                                        <input id="sortby" type="hidden" value="0" />
                                </div>

<div style="margin:5px;">
<iframe src="files.php?dialogname=<?php echo $MY_NAME; ?>&amp;refresh=1" name="fileManager" id="fileManager" background="Window" marginwidth="0" marginheight="0" valign:"top" scrolling="yes" frameborder="0" hspace="0" vspace="0" width="600px" height="250px" style="background-color: Window; margin:0px; padding:0px; border:0px; vertical-align:top;"></iframe>
</div>
                    </fieldset>
                    <fieldset style="min-height:20mm;"><legend></legend>

<div style="margin:5px;">Action:&nbsp;
  <select id="f_action" name="f_action" onChange="showAction(this.value)">
    <option value="f_action_inline">Display file</option>
    <option value="f_action_filelink">Insert file link</option>
    <option value="f_action_upload">Upload file</option>
  </select>
</div>

<div id="f_action_inline_values" style="visibility:visible;">
<table border="0" align="center" cellpadding="2" cellspacing="2">
 <tr>
  <td nowrap><div align="right">URL </div></td>
  <td><input name="url" id="f_url" type="text" style="width:20em" size="30"></td>
  <td rowspan="3">&nbsp;</td>
  <td><div align="right">Width </div></td>
  <td><input name="width" id="f_width" type="text" size="5" style="width:4em" onChange="javascript:checkConstrains('width');"></td>
  <td rowspan="2"><img src="ImageManager/locked.gif" name="locked_img" width="25" height="32" id="locked_img" alt="Locked"></td>
  <td rowspan="3">&nbsp;</td>
  <td><div align="right">V Space</div></td>
  <td><input name="vert" id="f_vert" type="text" size="5" style="width:4em"></td>
 </tr>
 <tr>
  <td nowrap><div align="right">Alt </div></td>
  <td><input type="text" style="width:20em" name="alt" id="f_alt"></td>
  <td><div align="right">Height </div></td>
  <td><input name="height" id="f_height" type="text" size="5" style="width:4em" onChange="javascript:checkConstrains('height');"></td>
  <td><div align="right">H Space</div></td>
  <td><input name="horiz" id="f_horiz" type="text" size="5" style="width:4em"></td>
 </tr>
 <tr>
  <td><div align="right">Align</div></td>
  <td colspan="2"><select name="align" ID="f_align" style="width:7em">
   <OPTION id="optNotSet" value=""> Not set </OPTION>
   <OPTION id="optLeft" value="left"> Left </OPTION>
   <OPTION id="optRight" value="right"> Right </OPTION>
   <OPTION id="optTexttop" value="textTop"> Texttop </OPTION>
   <OPTION id="optAbsMiddle" value="absMiddle"> Absmiddle </OPTION>
   <OPTION id="optBaseline" value="baseline" SELECTED> Baseline </OPTION>
   <OPTION id="optAbsBottom" value="absBottom"> Absbottom </OPTION>
   <OPTION id="optBottom" value="bottom"> Bottom </OPTION>
   <OPTION id="optMiddle" value="middle"> Middle </OPTION>
   <OPTION id="optTop" value="top"> Top </OPTION></select>
  </td>
  <td colspan="3"><div align="right">
   <input type="hidden" name="orginal_width" id="orginal_width">
   <input type="hidden" name="orginal_height" id="orginal_height">
   <input type="hidden" name="f_ext" id="f_ext">
<!--   <input type="checkbox" name="constrain_prop" id="constrain_prop" checked onClick="javascript:toggleConstrains(this);"></div>
  </td>
  <td>Constrain Proportions</td> -->
  <td><div align="right">Border</div></td>
  <td><input name="border" id="f_border" type="text" size="5" style="width:4em"></td>
 </tr>
</table>
</div>

<div id="f_action_filelink_values" style="position:absolute; top:380px; width:600px; visibility:hidden;">
      <table border="0" align="center" cellpadding="2" cellspacing="2">
          <tr>
            <td nowrap><div align="right">URL</div></td>
            <td><input name="url2" id="f_url2" type="text" style="width:20em" size="30"></td>
            <td nowrap><div align="right">Caption</div></td>
            <td><input name="caption" id="f_caption" type="text" style="width:20em" size="30"></td>
          </tr>
      </table>
      <table border="0" align="center" cellpadding="2" cellspacing="2">
          <tr>
            <td>
                               <input id="f_addicon" value="f_addicon" type="checkbox">
            </td><td>
                               <div align="left">Insert filetype icon</div>
            </td><td>
                               <input id="f_addsize" value="f_addsize" type="checkbox">
            </td><td>
                               <div align="left">Insert file size</div>
            </td><td>
                               <input id="f_adddate" value="f_adddate" type="checkbox">
            </td><td>
                               <div align="left">Insert file modification date</div>
            </td>
          </tr>
      </table>
</div>

<div id="f_action_upload_values" style="position:absolute; top:380px; visibility:hidden;">
                                <div style="text-align:center; padding:2px;">
                    <?php
                                if ($MY_ALLOW_UPLOAD) {
                        ?>
                                        <label for="uploadFile">
                                        <?php echo $MY_MESSAGES['upload']; ?>
                                        </label>
                                           <input name="uploadFile" type="file" id="uploadFile" size="52" />
                            <input type="submit" style="width:5em" value="<?php echo $MY_MESSAGES['upload']; ?>" onClick="javascript:return doUpload();" />
                    <?php
                                 }
                        ?>
                                </div>
</div>
                    </fieldset>

                         <div style="text-align: right; margin-top:5px;">
                                  <input type="button" name="refresh" value="Refresh" onclick="return refreshPath();">
                                  <input type="button" name="cancel" value="Cancel" onclick="return onCancel();">
                                  <input type="reset" name="reset" value="Reset">
                                  <input type="button" name="ok" value="OK" onclick="return onOK();">
                     </div>
                     <div style="position:absolute; bottom:-5px; right:-3px;">
                                 <img src="img/btn_Corner.gif" width="14" height="14" border="0" alt="" />
                           </div>
                </form>
        </body>
</html>
