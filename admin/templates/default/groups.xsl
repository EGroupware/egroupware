<!-- $Id$ -->

	<xsl:template name="groups">
		<xsl:choose>
			<xsl:when test="group_list">
				<xsl:apply-templates select="group_list"/>
			</xsl:when>
			<xsl:when test="group_edit">
				<xsl:apply-templates select="group_edit"/>
			</xsl:when>
		</xsl:choose>
	</xsl:template>

<!-- BEGIN group_list -->

	<xsl:template match="group_list">
		<center>
		<table border="0" cellspacing="2" cellpadding="2">
			<tr>
				<td colspan="3" width="100%">
					<xsl:call-template name="nextmatchs"/>
				</td>
			</tr>
			<tr>
				<td colspan="3" width="100%" align="right">
					<xsl:choose>
						<xsl:when test="search_access = 'yes'">
							<xsl:call-template name="search_field"/>
						</xsl:when>
					</xsl:choose>
				</td>
			</tr>
				<xsl:apply-templates select="group_header"/>
				<xsl:apply-templates select="group_data"/>
				<xsl:apply-templates select="group_add"/>
		</table>
		</center>
	</xsl:template>

<!-- BEGIN group_header -->

	<xsl:template match="group_header">
		<xsl:variable name="sort_name" select="sort_name"/>
		<xsl:variable name="lang_sort_statustext" select="lang_sort_statustext"/>
		<tr class="th">
			<td width="20%"><a href="{$sort_name}" onMouseover="window.status='{$lang_sort_statustext}';return true;" onMouseout="window.status='';return true;" class="th_text"><xsl:value-of select="lang_name"/></a></td>
			<td width="8%" align="center"><xsl:value-of select="lang_edit"/></td>
			<td width="8%" align="center"><xsl:value-of select="lang_delete"/></td>
		</tr>
	</xsl:template>

<!-- BEGIN group_data -->

	<xsl:template match="group_data">
		<xsl:variable name="lang_edit_statustext"><xsl:value-of select="lang_edit_statustext"/></xsl:variable>
		<xsl:variable name="lang_delete_statustext"><xsl:value-of select="lang_delete_statustext"/></xsl:variable>
		<tr>
			<xsl:attribute name="class">
				<xsl:choose>
					<xsl:when test="@class">
						<xsl:value-of select="@class"/>
					</xsl:when>
					<xsl:when test="position() mod 2 = 0">
						<xsl:text>row_off</xsl:text>
					</xsl:when>
					<xsl:otherwise>
						<xsl:text>row_on</xsl:text>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<td><xsl:value-of select="group_name"/></td>
			<td align="center">
				<xsl:variable name="edit_url" select="edit_url"/>
				<a href="{$edit_url}" onMouseover="window.status='{$lang_edit_statustext}';return true;" onMouseout="window.status='';return true;" class="th_text"><xsl:value-of select="lang_edit"/></a>
			</td>
			<td align="center">
				<xsl:variable name="delete_url" select="delete_url"/>
				<a href="{$delete_url}" onMouseover="window.status='{$lang_delete_statustext}';return true;" onMouseout="window.status='';return true;" class="th_text"><xsl:value-of select="lang_delete"/></a>
			</td>
		</tr>
	</xsl:template>

<!-- BEGIN group_add -->

	<xsl:template match="group_add">
			<tr>
				<td height="50" valign="bottom">
					<xsl:choose>
						<xsl:when test="add_access = 'yes'">
							<xsl:variable name="add_url"><xsl:value-of select="add_url"/></xsl:variable>
							<xsl:variable name="lang_add"><xsl:value-of select="lang_add"/></xsl:variable>
							<form method="post" action="{$add_url}">
								<input type="submit" name="add" value="{$lang_add}" onMouseout="window.status='';return true;">
									<xsl:attribute name="onMouseover">
										<xsl:text>window.status='</xsl:text>
										<xsl:value-of select="lang_add_statustext"/>
										<xsl:text>'; return true;</xsl:text>
									</xsl:attribute>
								</input>
							</form>
						</xsl:when>
					</xsl:choose>
				</td>
			</tr>
			<tr>
				<td height="50" valign="bottom">
					<xsl:variable name="done_url"><xsl:value-of select="done_url"/></xsl:variable>
					<xsl:variable name="lang_done"><xsl:value-of select="lang_done"/></xsl:variable>
					<form method="post" action="{$done_url}">
						<input type="submit" name="done" value="{$lang_done}" onMouseout="window.status='';return true;">
							<xsl:attribute name="onMouseover">
								<xsl:text>window.status='</xsl:text>
									<xsl:value-of select="lang_done_statustext"/>
								<xsl:text>'; return true;</xsl:text>
							</xsl:attribute>
						</input>
					</form>
				</td>
			</tr>
	</xsl:template>

<!-- END group_list -->

<!-- BEGIN group_edit -->

	<xsl:template match="group_edit">
		<table border="0" cellpadding="2" cellspacing="2" align="center" width="79%">
			<tr>
				<td><xsl:value-of select="error"/></td>
			</tr>
			<tr>
				<td valign="top">
<!-- {rows} -->
				</td>
				<td valign="top">
					<table border="0" width="100%">
						<xsl:variable name="edit_url"><xsl:value-of select="edit_url"/></xsl:variable>
						<xsl:variable name="account_id" select="account_id"/>
						<xsl:variable name="select_size" select="select_size"/>
						<form action="{$edit_url}" method="POST">
						<input type="hidden" name="values[account_id]" value="{$account_id}"/>
						<tr>
							<td><xsl:value-of select="lang_account_name"/></td>
							<td><input name="values[account_name]">
								<xsl:attribute name="value">
									<xsl:value-of select="value_account_name"/>
								</xsl:attribute>
								</input>
							</td>
						</tr>
						<tr>
							<td><xsl:value-of select="lang_include_user"/></td>
							<td>
								<select name="account_user[]" multiple="multiple" size="{$select_size}">
									<xsl:apply-templates select="user_list"/>
								</select>
							</td>
						</tr>
						<tr>
							<td><xsl:value-of select="lang_file_space"/></td>
							<td>
<!-- {account_file_space}{account_file_space_select} -->
							</td>
						</tr>
						<tr>
							<td valign="top"><xsl:value-of select="lang_permissions"/></td>
							<td>
								<table width="100%" border="0" cellpadding="2" cellspacing="2">
									<tr class="th">
										<td><xsl:value-of select="lang_application"/></td>
										<td>&nbsp;</td>
										<td><xsl:value-of select="lang_acl"/></td>
									</tr>
										<xsl:apply-templates select="app_list"/>
								</table>
							</td>
						</tr>
						<tr>
							<td colspan="2" align="left">
							<xsl:variable name="lang_save"><xsl:value-of select="lang_save"/></xsl:variable>
								<input type="submit" name="values[save]" value="{$lang_save}"/>
							</td>
						</tr>
 						</form>
						<tr>
						<xsl:variable name="done_url"><xsl:value-of select="done_url"/></xsl:variable>
						<xsl:variable name="lang_done"><xsl:value-of select="lang_done"/></xsl:variable>
						<form method="POST" action="{$done_url}">
							<td align="left">
								<input type="submit" name="done" value="{$lang_done}"/>
							</td>
						</form>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</xsl:template>

	<xsl:template match="user_list">
		<xsl:variable name="account_id" select="account_id"/>
		<xsl:choose>
			<xsl:when test="selected != ''">
				<option value="{$account_id}" selected="selected"><xsl:value-of select="account_name"/></option>
			</xsl:when>
			<xsl:otherwise>
				<option value="{$account_id}"><xsl:value-of select="account_name"/></option>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template match="app_list">
		<xsl:variable name="checkbox_name" select="checkbox_name"/>
		<tr>
			<xsl:attribute name="class">
				<xsl:choose>
					<xsl:when test="@class">
						<xsl:value-of select="@class"/>
					</xsl:when>
					<xsl:when test="position() mod 2 = 0">
						<xsl:text>row_off</xsl:text>
					</xsl:when>
					<xsl:otherwise>
						<xsl:text>row_on</xsl:text>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<td width="40%"><xsl:value-of select="app_name"/></td>
			<td width="5%" align="center">
				<xsl:choose>
					<xsl:when test="checked != ''">
						<input type="checkbox" name="{$checkbox_name}" value="True" checked="checked"/>
					</xsl:when>
					<xsl:otherwise>
						<input type="checkbox" name="{$checkbox_name}" value="True"/>
					</xsl:otherwise>
				</xsl:choose>
			</td>
			<td width="5%" align="center">
				<xsl:choose>
					<xsl:when test="acl_url != ''">
						<xsl:variable name="acl_url" select="acl_url"/>
						<xsl:variable name="acl_img" select="acl_img"/>
						<xsl:variable name="img_name" select="img_name"/>
						<a href="{$acl_url}"><img src="{$acl_img}" border="0" hspace="3" align="absmiddle" alt="{$img_name}" name="{$img_name}"/></a>
					</xsl:when>
				</xsl:choose>
			</td>
		</tr>
	</xsl:template>
