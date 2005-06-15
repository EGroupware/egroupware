
// For translators: please use UTF-8 if possible.  We strongly believe that
// Unicode is the answer to a real internationalized world.  Also please
// include your contact information in the header, as can be seen above.

// full day names
Calendar._DN = new Array
("Sonntag",
 "Montag",
 "Dienstag",
 "Mittwoch",
 "Donnerstag",
 "Freitag",
 "Samstag",
 "Sonntag");

// Please note that the following array of short day names (and the same goes
// for short month names, _SMN) isn't absolutely necessary.  We give it here
// for exemplification on how one can customize the short day names, but if
// they are simply the first N letters of the full name you can simply say:
//
Calendar._SDN_len = 2; // short day name length
//   Calendar._SMN_len = N; // short month name length
//
// If N = 3 then this is not needed either since we assume a value of 3 if not
// present, to be compatible with translation files that were written before
// this feature.

// short day names
Calendar._SDN = new Array
("So",
 "Mo",
 "Di",
 "Mi",
 "Do",
 "Fr",
 "Sa",
 "So");

// full month names
Calendar._MN = new Array
("Januar",
 "Februar",
 "März",
 "April",
 "Mai",
 "Juni",
 "Juli",
 "August",
 "September",
 "Oktober",
 "November",
 "Dezember");

// short month names
Calendar._SMN = new Array
("Jan",
 "Feb",
 "Mär",
 "Apr",
 "Mai",
 "Jun",
 "Jul",
 "Aug",
 "Sep",
 "Okt",
 "Nov",
 "Dez");

// tooltips
Calendar._TT = {};
Calendar._TT["INFO"] = "Über den Kalender";

Calendar._TT["ABOUT"] =
"DHTML Datum/Zeit Tool\n" +
"(c) dynarch.com 2002-2003\n" + // don't translate this this ;-)
"Updates finden sie unter: http://dynarch.com/mishoo/calendar.epl\n" +
"Lizenziert unter der GNU LGPL: http://gnu.org/licenses/lgpl.html für Details." +
"\n\n" +
"Datum Auswahl:\n" +
"- Jahr Auswahl über \xab, \xbb Buttons\n" +
"- Monatsauswahl über den " + String.fromCharCode(0x2039) + " und " + String.fromCharCode(0x203a) + " Button\n" +
"- Für eine schnellere Auswahl halten sie die o.g. Buttons gedrückt.";
Calendar._TT["ABOUT_TIME"] = "\n\n" +
"Zeit Auswahl:\n" +
"- Klicken für Zeiterhöhung\n" +
"- oder Shift-Klick um zu verringern\n" +
"- oder klicken und loslassen für schnellere Auswahl.";

Calendar._TT["PREV_YEAR"] = "Vorheriges Jahr (halten für Menü)";
Calendar._TT["PREV_MONTH"] = "Vorheriger Monat (halten für Menü)";
Calendar._TT["GO_TODAY"] = "Heute";
Calendar._TT["NEXT_MONTH"] = "Nächster Monat (halten für Menü)";
Calendar._TT["NEXT_YEAR"] = "Nächstes Jahr (halten für Menü)";
Calendar._TT["SEL_DATE"] = "Auswahl Datum";
Calendar._TT["DRAG_TO_MOVE"] = "";
Calendar._TT["PART_TODAY"] = " (heute)";

// the following is to inform that "%s" is to be the first day of week
// %s will be replaced with the day name.
Calendar._TT["DAY_FIRST"] = "Zeigt %s als ersten Tag";

// This may be locale-dependent.  It specifies the week-end days, as an array
// of comma-separated numbers.  The numbers are from 0 to 6: 0 means Sunday, 1
// means Monday, etc.
Calendar._TT["WEEKEND"] = "0,6";

Calendar._TT["CLOSE"] = "Kalender schließen";
Calendar._TT["TODAY"] = "Heute";
Calendar._TT["TIME_PART"] = "(Shift-)Klick oder ziehem um den Wert zu ändern";

// date formats
Calendar._TT["DEF_DATE_FORMAT"] = "%d.%m.%Y";
Calendar._TT["TT_DATE_FORMAT"] = "%a, %b %e";

Calendar._TT["WK"] = "KW";
Calendar._TT["TIME"] = "Zeit:";
