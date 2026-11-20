# Document merge: placeholders

EGroupware can insert the data of an entry (a contact, an InfoLog, a timesheet, an event, ...) into a document you
prepared: a letter, an invoice, a list or an email. The document holds *placeholders*, field names in double curly
brackets like `{{n_fn}}`, which are replaced with the entry's data.

## Setting up documents

Each application has preferences for document merge:

* **Directory with documents to insert entries**: every document in this directory shows up in the context menu of
  the application's list, under *Insert in document*.
* **Default document to insert entries**: one document that gets an extra icon on every entry.
* **Directory for storing merged documents**: where merged documents are saved, your home directory if empty.
* **Merged document filename**: how merged documents are named.

Select one or more entries, right-click and pick a document under *Insert in document*. Merging several entries into a
single document needs `{{pagerepeat}}` (see below).

Supported documents: OpenDocument text, spreadsheets and presentations (LibreOffice, Collabora), Microsoft Word and
Excel (`.docx`, `.xlsx`, also macro-enabled), Word and Excel 2003 XML, RTF, HTML, plain text and emails (`.eml`).

## Finding placeholders

When editing a document in Collabora, *Insert placeholder* lists every placeholder of every application, grouped,
and previews what it merges to for an entry you pick. The preferences above link the full list of placeholder names
of the application.

Custom fields are placeholders too, with a `#` in front of their name: `{{#contract_end}}`.

## Dates and times

### The current date and time

| Placeholder    | Merges to                                                   | Example (`m-d-Y`, 12 hour) |
|----------------|-------------------------------------------------------------|----------------------------|
| `{{date}}`     | today's date, in your date format                           | `05-19-2026`               |
| `{{time}}`     | the current time, in your time format                       | `02:00 pm`                 |
| `{{datetime}}` | today's date and the current time, separated by a comma     | `05-19-2026, 02:00 pm`     |

Your date and time format are the ones set in *Preferences > Common preferences*, as is the time zone all dates and
times are shown in.

### Date fields

Fields holding a date or a date and time (start, due date, created, modified, birthday, custom fields of type date
or date-time, ...) can be merged in three ways:

| Placeholder              | Merges to                                               | Example                            |
|--------------------------|---------------------------------------------------------|------------------------------------|
| `{{field}}`              | the field as the application formats it, usually in your date and time format | `05-19-2026 02:00:00 pm` |
| `{{field/date}}`         | only the date, in your date format                      | `05-19-2026`                       |
| `{{field/time}}`         | only the time, in your time format                      | `02:00 pm`                         |
| `{{field:FORMAT}}`       | the date and/or time in a format of your own            | `{{info_startdate:d.m.Y}}` → `19.05.2026` |

All of these work for custom fields as well: `{{#contract_end/date}}`, `{{#contract_end:F Y}}`.

If the field is empty, all of them merge to nothing.

### Your own format: `{{field:FORMAT}}`

Everything after the colon is the format. It uses the letters of PHP's
[`date()` function](https://www.php.net/manual/en/datetime.format.php); any other character is copied as it is. The
most useful letters:

| Letter | Meaning                               | Example for Tuesday, 19 May 2026, 14:05 |
|--------|---------------------------------------|-----------------------------------------|
| `d`    | day, 2 digits                         | `19`                                    |
| `j`    | day, no leading zero                  | `19` (`5` for the 5th)                  |
| `S`    | English ordinal suffix, after `j`     | `th`                                    |
| `D`    | weekday, first 3 letters              | `Tue`                                   |
| `l`    | weekday                               | `Tuesday`                               |
| `m`    | month, 2 digits                       | `05`                                    |
| `n`    | month, no leading zero                | `5`                                     |
| `M`    | month, first 3 letters                | `May`                                   |
| `F`    | month                                 | `May`                                   |
| `Y`    | year, 4 digits                        | `2026`                                  |
| `y`    | year, 2 digits                        | `26`                                    |
| `H`    | hour, 24 hour clock                   | `14`                                    |
| `h`    | hour, 12 hour clock                   | `02`                                    |
| `G`, `g` | hour without leading zero, 24 resp. 12 hour clock | `14`, `2`                 |
| `i`    | minutes                               | `05`                                    |
| `s`    | seconds                               | `00`                                    |
| `a`, `A` | am/pm, lower resp. upper case       | `pm`, `PM`                              |
| `W`    | ISO week number                       | `21`                                    |
| `N`    | ISO day of the week, 1 = Monday       | `2`                                     |

Examples:

| Placeholder                              | Merges to                     |
|------------------------------------------|-------------------------------|
| `{{info_startdate:m/d/Y}}`               | `05/19/2026`                  |
| `{{info_startdate:d.m.Y H:i}}`           | `19.05.2026 14:05`            |
| `{{info_startdate:H:i}}`                 | `14:05`                       |
| `{{info_startdate:g:i A}}`               | `2:05 PM`                     |
| `{{info_startdate:l, F j, Y}}`           | `Tuesday, May 19, 2026`       |
| `{{info_startdate:jS \o\f F Y}}`         | `19th of May 2026`            |
| `{{info_startdate:Y-m-d\TH:i:s}}`        | `2026-05-19T14:05:00`         |
| `{{info_startdate:W}}`                   | `21`                          |

Notes:

* To get a letter as it is, put a backslash in front of it: `\o\f` gives `of`, `\T` gives `T`.
* Names of weekdays and months (`l`, `D`, `F`, `M`) are in the default language of the document (OpenDocument and
  Word documents), or in your language if the document has none or EGroupware has no translation for it:
  `{{info_startdate:l, j. F Y}}` gives `Dienstag, 19. Mai 2026` in a German document. This also goes for the `d-M-Y` date format, and for `{{date}}`.
  The ordinal suffix `S` is always English.
* `$`, `<`, `>` and `&` can not be used in a format.
* A format can not be given for `{{date}}`, `{{time}}` or `{{datetime}}`.

### Spreadsheets

In LibreOffice / Collabora spreadsheets (`.ods`) and Excel 2003 XML spreadsheets, a date field placed **alone** in a
cell, eg. `{{info_startdate}}`, becomes a real date cell: you can calculate, sort and filter with it, and choose how
it is displayed with the cell's own number format (*Format > Cells*). `{{field/date}}`, `{{field/time}}` and
`{{field:FORMAT}}`, or a date field together with other text in a cell, stay text.

In Excel `.xlsx` spreadsheets, date fields stay text.

## Numbers

In OpenDocument and Microsoft Office documents, number fields (amounts, prices, quantities, ...) can be given the
number of decimal places and the separators:

| Placeholder        | Merges to                                                             |
|--------------------|-----------------------------------------------------------------------|
| `{{total:2}}`      | 2 decimal places                                                      |
| `{{total:2,.}}`    | 2 decimal places, `,` as decimal and `.` as thousands separator       |
| `{{total:}}`       | 2 decimal places, or 3 or 4 if the number has them                    |

A number field alone in a spreadsheet cell becomes a number cell.

## Commands

| Placeholder                                      | Does                                                                      |
|--------------------------------------------------|---------------------------------------------------------------------------|
| `{{IF n_prefix~Mr~Hello Mr.~Hello Ms.}}`         | if the field `n_prefix` contains `Mr`, writes `Hello Mr.`, else `Hello Ms.` |
| `{{IF url~EMPTY~~Website:}}`                     | if `url` is not empty, writes `Website:`                                  |
| `{{NELF role}}`                                  | if `role` is not empty, a new line followed by its value                  |
| `{{NENVLF role}}`                                | if `role` is not empty, a new line without the value                      |
| `{{LETTERPREFIX}}`                               | the letter prefix, without double spaces if eg. the title is empty        |
| `{{LETTERPREFIXCUSTOM n_prefix title n_family}}` | the given fields, eg. `Mr Dr. James Miller`                               |

## Several entries in one document

* **Serial letters**: to merge several entries into one document, put the part to repeat for every entry between two
  `{{pagerepeat}}` tags. What is in front of the first and after the last tag, eg. a letterhead and a closing, is
  written only once, and can only hold placeholders not depending on an entry, like `{{date}}` or the current user's.
  * In OpenDocument text and Word documents the entries follow each other directly, not each on a new page. Without
    the tags, merging more than one entry is refused with a message asking for them.
  * Plain text documents without the tags repeat the whole document for every entry.
* **Address labels**: put the content of one label between two `{{label}}` tags and mark the position of every label
  on the page with `{{labelplacement}}`. When there are more entries than labels on a page, the page is repeated, each
  on a new page. Around the labels, the page can hold `{{date}}`, `{{time}}` and placeholders of the current user.
