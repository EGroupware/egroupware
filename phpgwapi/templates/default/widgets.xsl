<xsl:template name="WIDGET_NEXTMATCHES">
	<xsl:param name="WIDGET_NEXTMATCHES_PARAMS"/>
	<xsl:variable name="cur_record"><xsl:value-of select="{$WIDGET_NEXTMATCHES_PARAMS/CUR_RECORD}"/></xsl:variable>
	<xsl:variable name="record_limit"><xsl:value-of select="{$WIDGET_NEXTMATCHES_PARAMS/RECORD_LIMIT}"/></xsl:variable>
	<xsl:variable name="num_records"><xsl:value-of select="{$WIDGET_NEXTMATCHES_PARAMS/NUM_RECORDS}"/></xsl:variable>
	<xsl:variable name="all_records"><xsl:value-of select="{$WIDGET_NEXTMATCHES_PARAMS/ALL_RECORDS}"/></xsl:variable>
	<xsl:variable name="link_url"><xsl:value-of select="{$WIDGET_NEXTMATCHES_PARAMS/LINK_URL}"/></xsl:variable>
	<tr>
		<td height="30">
			<table width="192" border="0" cellspacing="0" cellpadding="0" class="inter-body">
				<tr>
					<td width="11">&#160;</td>
					<xsl:choose>
						<xsl:when test="number($cur_record) > number(1)">
							<xsl:variable name="first"><xsl:value-of select="{$link_url}"/>&amp;currow=1</xsl:variable>
							<td width="25"><a href="{$first}" target="content"><img src="images/arrow_left.gif" width="16" height="16" border="0"/></a></td>
						</xsl:when>
						<xsl:otherwise>
							<td width="25"><img src="images/arrow_left.gif" width="16" height="16"/></td>
						</xsl:otherwise>
					</xsl:choose>
					<xsl:choose>
						<xsl:when test="number($cur_record) > number(1)">
							<xsl:variable name="prev_num"><xsl:value-of select="number($cur_record) - number($record_limit)"/></xsl:variable>
							<xsl:choose>
								<xsl:when test="number($prev_num)+number(1) >= number(1)">
									<xsl:choose>
										<xsl:when test="number($cur_record) - number($record_limit) > number(0)">
											<xsl:variable name="prev_number"><xsl:value-of select="number($cur_record) - number($record_limit)"/></xsl:variable>
											<xsl:variable name="prev"><xsl:value-of select="{$link_url}"/>&amp;currow=<xsl:value-of select="$prev_number"/></xsl:variable>
											<td width="25"><a href="{$prev}" target="content"><img src="images/arrow_left1.gif" width="16" height="16" border="0"/></a></td>
										</xsl:when>
										<xsl:otherwise>
											<xsl:variable name="prev"><xsl:value-of select="{$link_url}"/>&amp;currow=1</xsl:variable>
											<td width="25"><a href="{$prev}" target="content"><img src="images/arrow_left1.gif" width="16" height="16" border="0"/></a></td>
										</xsl:otherwise>
									</xsl:choose>
								</xsl:when>
								<xsl:otherwise>
									<td width="25"><img src="images/arrow_left1.gif" width="16" height="16"/></td>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:when>
						<xsl:otherwise>
							<td width="25"><img src="images/arrow_left1.gif" width="16" height="16"/></td>
						</xsl:otherwise>
					</xsl:choose>
					
					<xsl:choose>
						<xsl:when test="number($num_records) = number(0)">
							<td nowrap="nowrap" align="center">0 - 0 of 0&#160;</td>
						</xsl:when>
						<xsl:otherwise>
							<xsl:choose>
								<xsl:when test="number($cur_record) + number($record_limit) > number($num_records)">
									<xsl:variable name="of_num"><xsl:value-of select="$num_records"/></xsl:variable>
									<td nowrap="nowrap" align="center"><xsl:value-of select="CUR_RECORD"/> - <xsl:value-of select="$of_num"/> of <xsl:value-of select="NUM_RECORDS"/>&#160;</td>
								</xsl:when>
								<xsl:otherwise>
									<xsl:variable name="of_num"><xsl:value-of select="number($cur_record) + number($record_limit) - number(1)"/></xsl:variable>
									<td nowrap="nowrap" align="center"><xsl:value-of select="CUR_RECORD"/> - <xsl:value-of select="$of_num"/> of <xsl:value-of select="NUM_RECORDS"/>&#160;</td>
								</xsl:otherwise>
							</xsl:choose>							
						</xsl:otherwise>
					</xsl:choose>
						<xsl:choose>
						<xsl:when test="number($num_records) > number($record_limit)">
							<xsl:variable name="next_num"><xsl:value-of select="number($cur_record) + number($record_limit)"/></xsl:variable>
							<xsl:choose>
								<xsl:when test="number($num_records) > number($next_num)-number(1)">
									<xsl:variable name="next"><xsl:value-of select="{$link_url}"/>&amp;currow=<xsl:value-of select="$next_num"/></xsl:variable>
									<td width="25"><a href="{$next}" target="content"><img src="images/arrow_right1.gif" width="16" height="16" border="0"/></a></td>
								</xsl:when>
								<xsl:otherwise>
									<td width="25"><img src="images/arrow_right1.gif" width="16" height="16"/></td>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:when>
						<xsl:otherwise>
							<td width="25"><img src="images/arrow_right1.gif" width="16" height="16"/></td>
						</xsl:otherwise>
					</xsl:choose>
						<xsl:choose>
						<xsl:when test="number($num_records) > number($record_limit)">
							<xsl:variable name="last_num"><xsl:value-of select="number($num_records)-number($record_limit)+number(1)"/></xsl:variable>
							<xsl:choose>
								<xsl:when test="number($last_num) > number($cur_record)">
									<xsl:variable name="last"><xsl:value-of select="{$link_url}"/>&amp;currow=<xsl:value-of select="$last_num"/></xsl:variable>
									<td width="25"><a href="{$last}" target="content"><img src="images/arrow_right.gif" width="16" height="16" border="0"/></a></td>
								</xsl:when>
								<xsl:otherwise>
									<td width="25"><img src="images/arrow_right.gif" width="16" height="16"/></td>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:when>
						<xsl:otherwise>
							<td width="25"><img src="images/arrow_right.gif" width="16" height="16"/></td>
						</xsl:otherwise>
					</xsl:choose>
					
					<xsl:choose>
						<xsl:when test="number($all_records) =1">
							<xsl:variable name="all"><xsl:value-of select="{$link_url}"/>&amp;currow=1</xsl:variable>
							<td width="21"><a href="{$all}" target="content"><img src="images/arrow_down.gif" width="16" height="16" border="0"/></a></td>
						</xsl:when>
						<xsl:otherwise>
							<xsl:variable name="all"><xsl:value-of select="{$link_url}"/>&amp;allrows=1</xsl:variable>
							<td width="21"><a href="{$all}" target="content"><img src="images/arrow_down.gif" width="16" height="16" border="0"/></a></td>
						</xsl:otherwise>
					</xsl:choose>
					</tr>
			</table>
		</td>
	</tr>
</xsl:template>

