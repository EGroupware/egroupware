<!-- $Id$ -->

<xsl:template name="nextmatchs">
<!--	<xsl:param name="nextmatchs_params"/> -->
	<xsl:variable name="cur_record"><xsl:value-of select="cur_record"/></xsl:variable>
	<xsl:variable name="record_limit"><xsl:value-of select="record_limit"/></xsl:variable>
	<xsl:variable name="num_records"><xsl:value-of select="num_records"/></xsl:variable>
	<xsl:variable name="all_records"><xsl:value-of select="all_records"/></xsl:variable>
	<xsl:variable name="link_url"><xsl:value-of select="link_url"/></xsl:variable>
	<xsl:variable name="img_path"><xsl:value-of select="img_path"/></xsl:variable>

	<table border="0" width="100%">
		<tr>
			<xsl:choose>
				<xsl:when test="number($cur_record) > number(1)">
				<xsl:variable name="first"><xsl:value-of select="nextmatchs_params/link_url"/>&amp;currow=1</xsl:variable>
					<td width="25">
						<a href="{$first}"><img src="{$img_path}/first-grey.png" border="2" width="12" height="12"/></a>
					</td>
				</xsl:when>
				<xsl:otherwise>
					<td width="25">
						<img src="{$img_path}/first-grey.png" border="2" width="12" height="12"/>
					</td>
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
								<xsl:variable name="prev"><xsl:value-of select="nextmatchs_params/link_url"/>&amp;currow=<xsl:value-of select="$prev_number"/></xsl:variable>
									<td width="25">
										<a href="{$prev}"><img src="{$img_path}/left-grey.png" border="2" width="12" height="12"/></a>
									</td>
								</xsl:when>
								<xsl:otherwise>
								<xsl:variable name="prev"><xsl:value-of select="nextmatchs_params/link_url"/>&amp;currow=1</xsl:variable>
									<td width="25">
										<a href="{$prev}"><img src="{$img_path}/left-grey.png" border="2" width="12" height="12"/></a>
									</td>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:when>
						<xsl:otherwise>
							<td width="25">
								<img src="{$img_path}/left-grey.png" border="2" width="12" height="12"/>
							</td>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:otherwise>
					<td width="25">
						<img src="{$img_path}/left-grey.png" border="2" width="12" height="12"/>
					</td>
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
							<td nowrap="nowrap" align="center">
								<xsl:value-of select="$cur_record"/> - <xsl:value-of select="$of_num"/> of <xsl:value-of select="$num_records"/>&#160;
							</td>
						</xsl:when>
						<xsl:otherwise>
						<xsl:variable name="of_num"><xsl:value-of select="number($cur_record) + number($record_limit) - number(1)"/></xsl:variable>
							<td nowrap="nowrap" align="center">
								<xsl:value-of select="$cur_record"/> - <xsl:value-of select="$of_num"/> of <xsl:value-of select="$num_records"/>&#160;
							</td>
						</xsl:otherwise>
					</xsl:choose>							
				</xsl:otherwise>
			</xsl:choose>
			
			<xsl:choose>
				<xsl:when test="number($num_records) > number($record_limit)">
				<xsl:variable name="next_num"><xsl:value-of select="number($cur_record) + number($record_limit)"/></xsl:variable>
					<xsl:choose>
						<xsl:when test="number($num_records) > number($next_num)-number(1)">
						<xsl:variable name="next"><xsl:value-of select="nextmatchs_params/link_url"/>&amp;currow=<xsl:value-of select="$next_num"/></xsl:variable>
							<td width="25" align="right">
								<a href="{$next}"><img src="{$img_path}/right-grey.png" border="2" width="12" height="12"/></a>
							</td>
						</xsl:when>
						<xsl:otherwise>
							<td width="25" align="right">
								<img src="{$img_path}/right-grey.png" border="2" width="12" height="12"/>
							</td>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:otherwise>
					<td width="25" align="right">
						<img src="{$img_path}/right-grey.png" border="2" width="12" height="12"/>
					</td>
				</xsl:otherwise>
			</xsl:choose>

			<xsl:choose>
				<xsl:when test="number($num_records) > number($record_limit)">
				<xsl:variable name="last_num"><xsl:value-of select="number($num_records)-number($record_limit)+number(1)"/></xsl:variable>
					<xsl:choose>
						<xsl:when test="number($last_num) > number($cur_record)">
						<xsl:variable name="last"><xsl:value-of select="nextmatchs_params/link_url"/>&amp;currow=<xsl:value-of select="$last_num"/></xsl:variable>
							<td width="25" align="right">
								<a href="{$last}"><img src="{$img_path}/last-grey.png" border="2" width="12" height="12"/></a>
							</td>
						</xsl:when>
						<xsl:otherwise>
							<td width="25" align="right">
								<img src="{$img_path}/last-grey.png" border="2" width="12" height="12"/>
							</td>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:when>
				<xsl:otherwise>
					<td width="25" align="right">
						<img src="{$img_path}/last-grey.png" border="2" width="12" height="12"/>
					</td>
				</xsl:otherwise>
			</xsl:choose>
		
			<xsl:choose>
				<xsl:when test="number($all_records) =1">
				<xsl:variable name="all"><xsl:value-of select="nextmatchs_params/link_url"/>&amp;currow=1</xsl:variable>
					<td width="25" align="right">
						<a href="{$all}"><img src="{$img_path}/arrow_down.png" border="2" width="12" height="12"/></a>
					</td>
				</xsl:when>
				<xsl:otherwise>
				<xsl:variable name="all"><xsl:value-of select="nextmatchs_params/link_url"/>&amp;allrows=1</xsl:variable>
					<td width="25" align="right">
						<a href="{$all}"><img src="{$img_path}/arrow_down.png" border="2" width="12" height="12"/></a>
					</td>
				</xsl:otherwise>
			</xsl:choose>
		</tr>
	</table>
</xsl:template>
