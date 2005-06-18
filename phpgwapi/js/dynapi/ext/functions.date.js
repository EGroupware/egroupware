/*
	DynAPI Distribution
	dynapi.functions.Date extension	
*/

var f = dynapi.functions;
f.Date = {}; // used by dynapi.library

// Date Functions --------------------------------------

f.dateAdd = function(interval,n,dt){
	if(!interval||!n||!dt) return;	
	var s=1,m=1,h=1,dd=1,i=interval;
	if(i=='month'||i=='year'){
		dt=new Date(dt);
		if(i=='month') dt.setMonth(dt.getMonth()+n);
		if(i=='year') dt.setFullYear(dt.getFullYear()+n);		
	}else if (i=='second'||i=='minute'||i=='hour'||i=='day'){
		dt=Date.parse(dt);
		if(isNaN(dt)) return;
		if(i=='second') s=n;
		if(i=='minute'){s=60;m=n}
		if(i=='hour'){s=60;m=60;h=n};
		if(i=='day'){s=60;m=60;h=24;dd=n};
		dt+=((((1000*s)*m)*h)*dd);
		dt=new Date(dt);
	}
	return dt;
};
f.dateDiff=function(interval,dt1,dt2){
	if(!interval||!dt1||!dt2) return;	
	var v,s=1,m=1,h=1,dd=1,i=interval;	
	if(i=='month'||i=='year'){
		dt1=new Date(dt1);
		dt2=new Date(dt2);
		years=dt2.getFullYear()-dt1.getFullYear();
		if (i=='year') v=years;
		else if(i=='month') {
			v=(dt2.getMonth()+1)-(dt1.getMonth()+1);
			if(years!=0) v+=(years*12);			
		}
	}else if (i=='second'||i=='minute'||i=='hour'||i=='day'){
		dt1=Date.parse(dt1);
		dt2=Date.parse(dt2);
		if(isNaN(dt1)||isNaN(dt2)) return;
		v=dt2-dt1;
		if(i=='second') s=1000;
		if(i=='minute') s=60000;
		if(i=='hour'){s=60000;m=60};
		if(i=='day'){s=60000;m=60;h=24;};
		v=((((v/s)/m)/h)/dd);
	}
	return v;
};
f.formatDate = function(date,format){
	if(!date) return '';
	var dt=new Date(date);
	var mm=dt.getMonth();
	var dd=dt.getDate();
	var day=dt.getDay();
	var yyyy=dt.getFullYear();
	var hh=dt.getHours();
	var nn=dt.getMinutes();
	var ss=dt.getSeconds();
	var ampm;

	var days=['Sunday','Monday','Teusday','Wednesday','Thursday','Friday','Saturday'];
	var months=['January','February','March','April','May','June','July','August','September','October','November','December'];

	format=(format)? (format+'').toLowerCase():'dddd, mmmm dd, yyyy hh:nn:ss ampm';
	format=format.replace('mmmm',months[mm]);
	format=format.replace('mmm',months[mm].substr(0,3));
	format=format.replace('mm',mm+1);
	format=format.replace('dddd',days[day]);
	format=format.replace('ddd',days[day].substr(0,3));
	format=format.replace('dd',dd);
	format=format.replace('yyyy',yyyy);
	if(format.indexOf('ampm')>0){
		if(hh>12) hh=hh-12;
		if(hh<12) ampm='AM';
		else ampm='PM';
		format=format.replace('ampm',ampm);
	}
	format=format.replace('hh',hh);
	format=format.replace('nn',nn);
	format=format.replace('ss',ss);

	return format;
};
f.getDayOfYear = function(dt){
	dt = new Date(dt);
	if(isNaN(dt)) dt = new Date();
	var yr = new Date(dt.getFullYear(),0,1);
	yr = yr.getTime() - (yr.getDay()-1)*(24*60*60*1000);
	return(Math.ceil((dt.getTime() - yr)/(24*60*60*1000)));
};
f.isDate = function(dt,format){
	if (!dt) return false;
	var dd,mm,yyyy;
	var isLeapYear,st=true,delim='/';
	dt+='';format=(format)? format+'':'';
	if(dt.indexOf('/')>=0) delim='/';
	else if(dt.indexOf('-')>=0) delim='-';
	else if(dt.indexOf(' ')>=0) delim=' ';
	dt=dt.split(delim);
	if(format) format=format.replace(/\W/g,'/');
	else {
		if (dt[0]>=1000) format='yyyy/mm/dd';
		else if (dt[0]>=12 && dt[1]<=12) format='dd/mm/yyyy';
		else if (dt[0]<=12 && dt[1]>=12) format='mm/dd/yyyy';
	};
	if(format=='yyyy/mm/dd'){yyyy=dt[0];mm=dt[1];dd=dt[2];}
	else if(format=='mm/dd/yyyy'){mm=dt[0];dd=dt[1];yyyy=dt[2];}
	else if(format=='dd/mm/yyyy'){dd=dt[0];mm=dt[1];yyyy=dt[2];}
	if(isNaN(dd)||isNaN(mm)||isNaN(yyyy)) st=false;
	else if(dd<1 || dd>31) st=false;
	else if(yyyy>9999) st=false;
	else if (mm < 1 || mm > 12) st=false;
	else if((mm==4 || mm==6 || mm==9 || mm==11) && dd==31) st=false;
	else if(mm==2) { // check for leap year and february 29th
		isLeapYear = (yyyy % 4 == 0 && (yyyy % 100 != 0 || yyyy % 400 == 0));
		if (dd > 29 || (dd==29 && !isLeapYear)) st=false;
	}
	return st;
};

