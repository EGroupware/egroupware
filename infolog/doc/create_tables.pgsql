
CREATE TABLE phpgw_infolog (
	info_id				serial,
	info_type			text check(info_type in('task','phone','note','confirm','reject','email','fax')) DEFAULT 'task' NOT NULL,
	info_addr_id		int DEFAULT '0' NOT NULL, 
	info_proj_id		int DEFAULT '0' NOT NULL,
	info_from			varchar(64),	
	info_addr			varchar(64),	
	info_subject		varchar(64) NOT NULL,
	info_des				text,													
	info_owner			int NOT NULL,	
	info_responsible	int DEFAULT '0' NOT NULL,	
	info_access			varchar(10) DEFAULT 'public',
	info_cat				int DEFAULT '0' NOT NULL,
	info_datecreated	int DEFAULT '0' NOT NULL,		
	info_startdate		int DEFAULT '0' NOT NULL,		
	info_enddate   	int DEFAULT '0' NOT NULL,			
	info_id_parent		int DEFAULT '0' NOT NULL,		
	info_pri			text check(info_pri in ('urgent','high','normal','low')) DEFAULT 'Normal' NOT NULL,
	info_time			int DEFAULT '0' NOT NULL,		
	info_bill_cat		int DEFAULT '0' NOT NULL,			
	info_status		text check (info_status in ('offer','ongoing','call','will-call','done','billed')) DEFAULT 'done' NOT NULL,
	info_confirm		text check (info_confirm in('not','accept','finish','both')) DEFAULT 'not' NOT NULL
);	
