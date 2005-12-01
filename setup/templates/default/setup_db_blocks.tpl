<!-- begin setup_db_blocks.tpl -->

&nbsp; <!-- ================================== --> &nbsp; 

<!-- BEGIN B_db_stage_1 -->
<tr>
	<td align="center">
		<img src="{img_incomplete}" alt="{notcomplete}" border="0" />
	</td>
	<td>
		<form action="index.php" method="post">
			<p>{lang_system_charset}<br />{system_charset}</p>
	    	<p>{dbnotexist}<br />{makesure}.</p>
			<p>{instr}</p>
			<p>{createdb}<br />
		    DB root username: <input type="text" name="db_root" value="root" /><br />
		    DB root password: <input type="password" name="db_pass" /><br />
		    <input type="hidden" name="action" value="Create Database" />
		    <input type="submit" name="label" value="{create_database}" /></p>
		</form>
		<form method="post" action="index.php"> <br />
		<input type="submit" value="Re-Check my database" />
		</form>
	</td>
</tr>
<!-- END B_db_stage_1 -->

<!-- BEGIN B_db_stage_1a -->
<tr>
	<td align="center">
		<img src="{img_incomplete}" alt="{notcomplete}" border="0" />
	</td>
	<td>
    	<p>{dbnotexist}<br />{makesure}.</p>
		<p>{instr}</p>
		<form method="post" action="index.php">
		<input type="submit" value="Re-Check my database" />
		</form><br />
	</td>
</tr>
<!-- END B_db_stage_1a -->

&nbsp; <!-- ================================== --> &nbsp; 

<!-- BEGIN B_db_stage_2 -->

<tr>
	<td align="center">
		<img src="{img_incomplete}" alt="{notcomplete}" border="0" />
	</td>
	<td>
	{prebeta}
	</td>
</tr>
<!-- END B_db_stage_2 -->

&nbsp; <!-- ================================== --> &nbsp; 

<!-- BEGIN B_db_stage_3 -->
<tr>
	<td align="center">
		<img src="{img_incomplete}" alt="{Complete}" border="0" />
	</td>
	<td>
		<form action="index.php" method="post"  enctype="multipart/form-data">
		<input type="hidden" name="oldversion" value="new" />

		<p>{dbexists}</p>
        <input type="hidden" name="action" value="Install" />
		<p>{lang_system_charset}<br />{system_charset}</p>
		<input type="checkbox" name="debug" value="1" /> {lang_debug}<br />
		<input type="submit" name="label" value="{install}" /> {coreapps}
		<hr />
		{lang_restore}<br />
		{upload}<br />
		{convert_checkbox} {lang_convert_charset}
		</form>
	</td>
</tr>
<!-- END B_db_stage_3 -->

&nbsp; <!-- ================================== --> &nbsp; 

<!-- BEGIN B_db_stage_4 -->
<tr>
	<td align="center">
		<img src="{img_incomplete}" alt="not complete" border="0" />
	</td>
	<td>
		{oldver}.<br />
		{automatic}
		{backupwarn}<br />
		<form method="post" action="index.php">
		<input type="hidden" name="oldversion" value="{oldver}" />
		<input type="hidden" name="useglobalconfigsettings" />
		<input type="hidden" name="action" value="Upgrade" />
		<input type="checkbox" name="backup" value="1" checked="checked" /> {lang_backup}<br />
		<input type="checkbox" name="debug" value="1" /> {lang_debug}<br />
		<input type="submit" name="label" value="{upgrade}" /><br />
		</form>
		<hr />
		<form method="post" action="index.php">
		<input type="hidden" name="oldversion" value="{oldver}" />
		<input type="hidden" name="useglobalconfigsettings" />
		<input type="hidden" name="action" value="Uninstall all applications" />
		<input type="submit" name="label" value="{uninstall_all_applications}" /><br />({dropwarn})
		</form>
		<hr />
		{dont_touch_my_data}.&nbsp;&nbsp;{goto}:
		<form method="post" action="config.php">
        <input type="hidden" name="action" value="Dont touch my data" />
		<input type="submit" name="label" value="{configuration}" />
        </form>
		<form method="post" action="admin_account.php">
        <input type="hidden" name="action" value="Dont touch my data" />
		<input type="submit" name="label" value="{admin_account}" />
        </form>
		<form method="post" action="lang.php">
        <input type="hidden" name="action" value="Dont touch my data" />
		<input type="submit" name="label" value="{language_management}" />
        </form>
		<form method="post" action="applications.php">
        <input type="hidden" name="action" value="Dont touch my data" />
		<input type="submit" name="label" value="{applications}" />
		</form>
		<form method="post" action="db_backup.php">
        <input type="hidden" name="action" value="Dont touch my data" />
		<input type="submit" name="label" value="{db_backup}" />
		</form>
	</td>
</tr>
<!-- END B_db_stage_4 -->

<!-- BEGIN B_db_stage_5 -->
<tr>
	<td>&nbsp;</td><td align="left">{are_you_sure}</td>
</tr>
<tr>
	<td align="center">
		<img src="{img_incomplete}" alt="{Complete}" border="0" />
	</td>
	<td>
		<form action="index.php" method="post">
		<input type="hidden" name="oldversion" value="new" />
        <input type="hidden" name="action" value="REALLY Uninstall all applications" />
		<input type="submit" name="label" value="{really_uninstall_all_applications}" /> {dropwarn}
		</form>
		<form action="index.php" method="post">
		<input type="submit" name="cancel" value="{cancel}" />
		</form>
	</td>
</tr>
<!-- END B_db_stage_5 -->

&nbsp; <!-- ================================== --> &nbsp; 

<!-- BEGIN B_db_stage_6_pre -->
<tr>
	<td align="center">
		<img src="{img_incomplete}" alt="{notcomplete}" border="0" />
	</td>
	<td>
		<table width="100%">
		<tr bgcolor="#486591">
			<td>
				<font color="#fefefe">&nbsp;<b>{subtitle}</b></font>
			</td>
		</tr>
		<tr bgcolor="#e6e6e6">
			<td>
				{submsg}
			</td>
		</tr>
<!--
		<tr bgcolor="#486591">
			<td>
				<font color="#fefefe">&nbsp;<b>{tblchange}</b></font>
			</td>
		</tr>
-->
<!-- END B_db_stage_6_pre -->

&nbsp; <!-- ================================== --> &nbsp; 

<!-- BEGIN B_db_stage_6_post -->
		<tr bgcolor="#486591">
			<td>
				<font color="#fefefe">&nbsp;<b>{status}</b></font>
			</td>
		</tr>
		<tr bgcolor="#e6e6e6">
			<td>{tableshave} {subaction}</td>
		</tr>
		</table>

		<form method="post" action="index.php"> <br />
		<input type="hidden" name="system_charset" value="{system_charset}" />
		<input type="submit" value="{re-check_my_installation}" />
		</form>
	</td>
</tr>
<!-- END B_db_stage_6_post -->

&nbsp; <!-- ================================== --> &nbsp; 

<!-- BEGIN B_db_stage_10 -->
<tr>
	<td align="center">
		<img src="{img_completed}" alt="completed" border="0" />
	</td>
	<td>
		{tablescurrent}
		<form method="post" action="index.php">
		<input type="hidden" name="oldversion" value="new" />
        <input type="hidden" name="action" value="Uninstall all applications" />
		<input type="submit" name="label" value="{uninstall_all_applications}" /><br />({dropwarn})
		</form>
	</td>
</tr>
<!-- END B_db_stage_10 -->

&nbsp; <!-- ================================== --> &nbsp; 

<!-- BEGIN B_db_stage_default -->
<tr>
	<td align="center">
		<img src="{img_incomplete}" alt="not complete" border="0" />
	</td>
	<td>
		<form action="index.php" method="post">
		{dbnotexist}.<br />
		<input type="submit" value="{create_one_now}" />
		</form>
	</td>
</tr>
<!-- END B_db_stage_default -->

&nbsp; <!-- ================================== --> &nbsp; 


<!-- end setup_db_blocks.tpl -->
