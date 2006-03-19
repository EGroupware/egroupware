<cfsetting enablecfoutputonly="yes">
<cfparam name="attributes.width" default="100%">
<cfparam name="attributes.height" default="100%">
<cfparam name="attributes.name" default="t#left(replace(CreateUUID(),'-','','All'),15)#">
<cfparam name="attributes.JSPath" default="js/">
<cfparam name="attributes.CSSPath" default="css/">
<cfparam name="attributes.iconspath" default="imgs/">
<cfparam name="attributes.xmldoc" default="">
<cfparam name="attributes.checkboxes" default="none"> <!--- [ Scand:  none, twoState, threeState  ] --->
<cfparam name="attributes.dragndrop" default="false">
<cfparam name="attributes.style" default="background-color:whitesmoke;border:1px solid blue;">
<cfparam name="attributes.onSelect" default="">
<cfparam name="attributes.onDrop" default="">
<cfparam name="attributes.onCheck" default="">
<cfparam name="attributes.xmlFile" default="">

<cfparam name="attributes.im1" default="">
<cfparam name="attributes.im2" default="">
<cfparam name="attributes.im3" default="">

<cfif not ThisTag.HasEndTag>
   <cfabort showerror="You need to supply a closing &lt;CF_dhtmlXTree&gt; tag.">
</cfif>

<cfif ThisTag.ExecutionMode is "End">
	<cfsavecontent variable="treeOutput">
		<cfoutput>
		<cfif not isDefined("request.dhtmlXTreeScriptsInserted")>
			<link rel="STYLESHEET" type="text/css" href="#attributes.CSSPath#dhtmlXTree.css">
			<script  src="#attributes.JSPath#dhtmlXCommon.js"></script>
			<script  src="#attributes.JSPath#dhtmlXTree.js"></script>	
			<cfset request.dhtmlXTreeScriptsInserted=1>
		</cfif>
		<div id="treebox_#attributes.name#" style="width:#attributes.width#; height:#attributes.height#; overflow:auto; #attributes.style#"></div>
		<script>
			function drawTree#attributes.name#(){
			#attributes.name#=new dhtmlXTreeGridObject('treebox_#attributes.name#',"100%","100%",0);
			#attributes.name#.tree.setImagePath("#attributes.iconspath#");
				<cfswitch expression="#attributes.checkboxes#">
			<cfcase value="twoState">
				#attributes.name#.tree.enableCheckBoxes(true)
				#attributes.name#.tree.enableThreeStateCheckboxes(false);
			</cfcase>
			<cfcase value="threeState">
				#attributes.name#.tree.enableCheckBoxes(true)
				#attributes.name#.tree.enableThreeStateCheckboxes(true);
			</cfcase>
			<cfdefaultcase>
				#attributes.name#.tree.enableCheckBoxes(false)
				#attributes.name#.tree.enableThreeStateCheckboxes(false);
			</cfdefaultcase>
				</cfswitch>
			<cfif len(attributes.onSelect)>
				#attributes.name#.tree.setOnClickHandler("#attributes.onSelect#");
			</cfif>
			<cfif len(attributes.onCheck)>
				#attributes.name#.tree.setOnCheckHandler("#attributes.onCheck#");
			</cfif>
			<cfif len(attributes.onDrop)>
				#attributes.name#.tree.setDragHandler("#attributes.onDrop#");
			</cfif>					
				#attributes.name#.tree.enableDragAndDrop(#attributes.dragndrop#)
			<cfif (len(attributes.im1) or len(attributes.im2)) or len(attributes.im3)>
				#attributes.name#.tree.setStdImages("#attributes.im1#","#attributes.im2#","#attributes.im3#");
			</cfif>
			<cfif len(attributes.xmlFile)>
				#attributes.name#.setXMLAutoLoading("#attributes.xmlFile#");
				#attributes.name#.loadXML("#attributes.xmlFile#")
			</cfif>
			<cfif Len(Trim(ThisTag.GeneratedContent))>
				#attributes.name#.loadXMLString("<?xml version='1.0'?><tree id='0'>#replace(replace(ThisTag.GeneratedContent,'"',"'","ALL"),"#chr(13)##chr(10)#","","ALL")#</tree>")
			</cfif>		
			};
			window.setTimeout("drawTree#attributes.name#()",100);	
		</script>
		</cfoutput>
		
	</cfsavecontent>

    <cfset ThisTag.GeneratedContent = treeOutput>
</cfif>
<cfsetting enablecfoutputonly="no">