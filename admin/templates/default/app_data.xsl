<!-- $Id$ -->

	<xsl:template name="app_data">
		<xsl:choose>
			<xsl:when test="list">
				<xsl:apply-templates select="list"/>
			</xsl:when>
			<xsl:when test="cat_list">
				<xsl:apply-templates select="cat_list"/>
			</xsl:when>
			<xsl:otherwise>
				<xsl:apply-templates select="list"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

<!-- BEGIN mainscreen -->

	<xsl:template match="list">
		<table width="75%" border="0" cellspacing="0" cellpadding="0">
			<xsl:choose>
				<xsl:when test="app_row_icon">
					<xsl:apply-templates select="app_row_icon"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:apply-templates select="app_row_noicon"/>
				</xsl:otherwise>
			</xsl:choose>
		</table>
	</xsl:template>

	<xsl:template match="app_row_icon">
		<xsl:variable name="app_icon" select="app_icon"/>
		<xsl:variable name="app_title" select="app_title"/>
		<xsl:variable name="app_name" select="app_name"/>
		<tr class="th">
			<td width="5%" valign="middle"><img src="{$app_icon}" alt="{$app_title}" name="{$app_title}"/></td>
			<td width="95%" valign="middle" class="th_text"><xsl:value-of select="app_title"/></td>
		</tr>
		<xsl:apply-templates select="link_row"/>
		<tr>
			<td colspan="2">&nbsp;</td>
		</tr>
	</xsl:template>

	<xsl:template match="app_row_noicon">
		<tr class="th">
			<td height="20" colspan="2" width="100%" valign="bottom" class="th_text">&nbsp;<xsl:value-of select="app_title"/></td>
		</tr>
		<xsl:apply-templates select="link_row"/>
		<tr>
			<td colspan="2">&nbsp;</td>
		</tr>
	</xsl:template>

	<xsl:template match="link_row">
		<xsl:variable name="pref_link" select="pref_link"/>
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
			<td colspan="2">&nbsp;&#8226;&nbsp;<a href="{$pref_link}"><xsl:value-of select="pref_text"/></a></td>
		</tr>
	</xsl:template>

<!-- BEGIN cat_list -->

	<xsl:template match="cat_list">
		<center>
		<table border="0" cellspacing="2" cellpadding="2">
			<tr>
				<td colspan="5" width="100%">
					<xsl:call-template name="nextmatchs"/>
				</td>
			</tr>
			<tr>
				<td colspan="5" width="100%" align="right">
					<xsl:call-template name="search_field"/>
				</td>
			</tr>
				<xsl:apply-templates select="cat_header"/>
				<xsl:apply-templates select="cat_data"/>
				<xsl:apply-templates select="cat_add"/>
		</table>
		</center>
	</xsl:template>

<!-- BEGIN cat_header -->

	<xsl:template match="cat_header">
		<xsl:variable name="sort_name" select="sort_name"/>
		<xsl:variable name="sort_descr" select="sort_descr"/>
		<xsl:variable name="lang_sort_statustext" select="lang_sort_statustext"/>
		<tr class="th">
			<td width="20%"><a href="{$sort_name}" onMouseover="window.status='{$lang_sort_statustext}';return true;" onMouseout="window.status='';return true;" class="th_text"><xsl:value-of select="lang_name"/></a></td>
			<td width="32%"><a href="{$sort_descr}" onMouseover="window.status='{$lang_sort_statustext}';return true;" onMouseout="window.status='';return true;" class="th_text"><xsl:value-of select="lang_descr"/></a></td>
			<td width="8%" align="center"><xsl:value-of select="lang_add_sub"/></td>
			<td width="8%" align="center"><xsl:value-of select="lang_edit"/></td>
			<td width="8%" align="center"><xsl:value-of select="lang_delete"/></td>
		</tr>
	</xsl:template>

<!-- BEGIN cat_data -->

	<xsl:template match="cat_data">
		<xsl:variable name="lang_add_sub_statustext"><xsl:value-of select="lang_add_sub_statustext"/></xsl:variable>
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
			<xsl:choose>
				<xsl:when test="main != ''">
					<td class="alarm"><b><xsl:value-of disable-output-escaping="yes" select="name"/></b></td>
					<td class="alarm"><b><xsl:value-of select="descr"/></b></td>
				</xsl:when>
				<xsl:otherwise>
					<td><xsl:value-of disable-output-escaping="yes" select="name"/></td>
					<td><xsl:value-of select="descr"/></td>
				</xsl:otherwise>
			</xsl:choose>
			<td align="center">
				<xsl:variable name="add_sub_url" select="add_sub_url"/>
				<a href="{$add_sub_url}" onMouseover="window.status='{$lang_add_sub_statustext}';return true;" onMouseout="window.status='';return true;" class="th_text"><xsl:value-of select="lang_add_sub"/></a>
			</td>
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

	<xsl:template match="cat_add">
			<tr>
				<td height="50" valign="bottom">
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
