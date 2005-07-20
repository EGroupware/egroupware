<style>
	td.ex{ font-family:Tahoma,Arial; color:green; font-size:12px;}
</style>
<script>
	function onCheck(id){
		alert("Check id "+id);
	}
	function onClick(id){
		alert("Click id "+id);
	}
	function onDrag(id,id2){
		alert("Drag id "+id+","+id2);
		return true;
	}		
</script>

<table width="100%">
<tr><td height="200px" width="50%">
<cf_dhtmlXTree jsPath="../js/" cssPath="../css/" iconspath="../imgs/" im1="book.gif" im2="books_open.gif" im3="books_close.gif">
		<item text="Mystery &amp; Thrillers" id="mystery" open="yes" >
			<item text="Lawrence Block" id="lb" >
				<item text="All the Flowers Are Dying" id="lb_1" />
				<item text="The Burglar on the Prowl" id="lb_2" />
				<item text="The Plot Thickens" id="lb_3" />
				<item text="Grifters Game" id="lb_4" />
				<item text="The Burglar Who Thought He Was Bogart" id="lb_5" />
			</item>
			<item text="Robert Crais" id="rc" >
				<item text="The Forgotten Man" id="rc_1" />
				<item text="Stalking the Angel" id="rc_2" />
				<item text="Free Fall" id="rc_3" />
				<item text="Sunset Express" id="rc_4" />
				<item text="Hostage" id="rc_5" />
			</item>
			<item text="Ian Rankin" id="ir" ></item>
			<item text="James Patterson" id="jp" ></item>
			<item text="Nancy Atherton" id="na" ></item>
		</item>
</cf_dhtmlXTree>
</td><td class="ex">
	&lt;cf_dhtmlXTree  im1="book.gif" im2="books_open.gif" im3="books_close.gif" &gt;
	<xmp>
		<item text="Mystery " id="mystery"  open="yes" >
			<item text="Lawrence Block" id="lb" >
				<item text="All the Flowers Are Dying" id="lb_1" />
				<item text="The Burglar on the Prowl" id="lb_2" />
				<item text="The Plot Thickens" id="lb_3" />
				<item text="Grifters Game" id="lb_4" />
				<item text="The Burglar Who Thought He Was Bogart" id="lb_5" />
			</item>
			<item text="Robert Crais" id="rc" >
				<item text="The Forgotten Man" id="rc_1" />
				<item text="Stalking the Angel" id="rc_2" />
				<item text="Free Fall" id="rc_3" />
				<item text="Sunset Express" id="rc_4" />
				<item text="Hostage" id="rc_5" />
			</item>
			<item text="Ian Rankin" id="ir" ></item>
			<item text="James Patterson" id="jp" ></item>
			<item text="Nancy Atherton" id="na" ></item>
		</item>
	</xmp>
	&lt;/cf_dhtmlXTree&gt;
	
</td></td></tr>
<tr><td height="200px">
<cf_dhtmlXTree  checkboxes="threeState"  open="yes" xmlFile="tree4.xml" >
</cf_dhtmlXTree>
</td><td class="ex">
	&lt;cf_dhtmlXTree   checkboxes="threeState" xmlFile="tree4.xml"  &gt;
	&lt;/cf_dhtmlXTree&gt;
	
</td></td></tr>
<tr><td height="200px">
<cf_dhtmlXTree width="50%" dragndrop="true"  checkboxes="twoState" onSelect="onClick" onCheck="onCheck" onDrop="onDrag">
		<item text="Mystery  " id="mystery"  open="yes" >
			<item text="Lawrence Block" id="lb" >
				<item text="All the Flowers Are Dying" id="lb_1" />
				<item text="The Burglar on the Prowl" id="lb_2" />
				<item text="The Plot Thickens" id="lb_3" />
				<item text="Grifters Game" id="lb_4" />
				<item text="The Burglar Who Thought He Was Bogart" id="lb_5" />
			</item>
			<item text="Robert Crais" id="rc" >
				<item text="The Forgotten Man" id="rc_1" />
				<item text="Stalking the Angel" id="rc_2" />
				<item text="Free Fall" id="rc_3" />
				<item text="Sunset Express" id="rc_4" />
				<item text="Hostage" id="rc_5" />
			</item>
			<item text="Ian Rankin" id="ir" ></item>
			<item text="James Patterson" id="jp" ></item>
			<item text="Nancy Atherton" id="na" ></item>
		</item>
</cf_dhtmlXTree>
</td><td class="ex">
	&lt;cf_dhtmlXTree   width="50%" dragndrop="true"  checkboxes="twoState" onSelect="onClick" onCheck="onCheck" onDrop="onDrag" &gt;
	<xmp>
		<item text="Mystery " id="mystery"  open="yes" >
			<item text="Lawrence Block" id="lb" >
				<item text="All the Flowers Are Dying" id="lb_1" />
				<item text="The Burglar on the Prowl" id="lb_2" />
				<item text="The Plot Thickens" id="lb_3" />
				<item text="Grifters Game" id="lb_4" />
				<item text="The Burglar Who Thought He Was Bogart" id="lb_5" />
			</item>
			<item text="Robert Crais" id="rc" >
				<item text="The Forgotten Man" id="rc_1" />
				<item text="Stalking the Angel" id="rc_2" />
				<item text="Free Fall" id="rc_3" />
				<item text="Sunset Express" id="rc_4" />
				<item text="Hostage" id="rc_5" />
			</item>
			<item text="Ian Rankin" id="ir" ></item>
			<item text="James Patterson" id="jp" ></item>
			<item text="Nancy Atherton" id="na" ></item>
		</item>
	</xmp>
	&lt;/cf_dhtmlXTree&gt;
	
</td></td></tr>
</table>