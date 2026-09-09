# Calendar recurrence: RFC 5545 standards gap + test harness

## Goal / motivation

Two follow-on features are wanted eventually:

1. Store a real, standards-conformant RRULE and use an existing library to interpret it.
2. Support creating/updating **recurring** events via the REST (JSCalendar) API.

Both are blocked by the current recurrence implementation not being fully RFC 5545-conformant,
which is why iCal import/export already contain workarounds for it. This doc maps exactly what
the current implementation does (and doesn't) support, and points at the test harness that pins
it down, so a later redesign has a concrete contract to check itself against.

**Status: mapping + harness done (this doc + the 3 test files below). No behavior changes, no
schema changes, no REST write support yet - those are future phases, not started.**

## Architecture map

Everything funnels through **`calendar/inc/class.calendar_rrule.inc.php`** (`calendar_rrule`), used
identically by:
- the UI recurrence description string (`__toString()`),
- DB-backed occurrence expansion/storage,
- iCal import/export (`calendar/inc/class.calendar_ical.inc.php`),
- the JMAP/JSCalendar REST read path (`api/src/CalDAV/JsCalendar.php`).

**Schema** (`calendar/setup/tables_current.inc.php`):
- `egw_cal_repeats`: one row per series - `recur_type` (enum), `recur_interval`, `recur_data`
  (bitmask of weekdays for WEEKLY/MONTHLY_WDAY; unused for other types).
- `egw_cal.range_end` doubles as RRULE `UNTIL`; `egw_cal.cal_recurrence`/`cal_reference` link
  exception-instance rows back to the series master.
- `egw_cal_dates`: a materialized cache of expanded occurrence start/end, generated *from* the
  above via `calendar_rrule` - not an independent, richer store.
- `recur_type` enum values (`class.calendar_so.inc.php`): NONE=0, DAILY=1, WEEKLY=2,
  MONTHLY_MDAY=3, MONTHLY_WDAY=4, YEARLY=5, **SECONDLY=6 (defined as `MCAL_RECUR_SECONDLY` but
  calendar_rrule has no corresponding case - would hit `next_no_exception()`'s `default: throw
  AssertionFailed`)**, MINUTELY=7, HOURLY=8, RDATE=9.

## Gap vs. RFC 5545 (confirmed in source + by test)

| # | Gap | Where | Confirmed by |
|---|-----|-------|--------------|
| 1 | `RDATE` (explicit date list) is mutually exclusive with any `RRULE`-shaped type - one `recur_type` enum, never "RRULE + extra RDATEs" | schema + `class.calendar_ical.inc.php` RDATE-property handler | `IcalRruleRoundtripTest::testRruleAndRdateTogetherIsOrderDependent` |
| 2 | `MONTHLY_WDAY`/`YEARLY` store exactly **one** implicit BYDAY (weekday+ordinal) derived from DTSTART; `MONTHLY_MDAY` exactly **one** implicit BYMONTHDAY. Never explicit, never multi-value. | `calendar_rrule::__construct()`, `parseRrule()` | `RruleTest::testParseRrule` ("multi-value collapses" cases), `IcalRruleRoundtripTest::testMultiByDayCollapsesToSingleDayFromDtstart` |
| 3 | No `BYMONTH`, `BYYEARDAY`, `BYWEEKNO`, `BYSETPOS`, `BYHOUR`/`BYMINUTE`/`BYSECOND` anywhere | whole class | `RruleTest::testParseRrule` ("bysetpos is silently ignored", "yearly by-weekday+bymonth" cases), `JsCalendarRecurrenceTest::testRecurrenceRulesReadShape` |
| 4 | `YEARLY` + `BYDAY` from an imported RRULE is hack-converted to `MONTHLY` × (12 × interval); one-directional, never regenerated as `YEARLY`+`BYDAY` on export; `BYMONTH` is silently dropped in the same case | `parseRrule()` | `RruleTest::testParseRrule`, `RruleTest::testGenerateRruleYearlyHasNoByMonth` |
| 5 | `COUNT` is parsed but immediately, irreversibly converted to a concrete `UNTIL` via `count2date()` and discarded; re-export never emits `COUNT=` again | `class.calendar_ical.inc.php` ~3385-3400 | `RruleTest::testCountToDate`, `IcalRruleRoundtripTest::testCountConvertedToUntilIrreversibly` |
| 6 | `WKST` isn't persisted per event - read live from the *current viewing user's* `weekdaystarts` preference at iteration time, not a fixed rule property | `calendar_rrule::__construct()` ~line 264 | `RruleTest::testWeeklyIntervalTwoInvariantAcrossWeekdaystartsPreference` - **empirically the 3 supported preference values produced IDENTICAL occurrence dates** for the WEEKLY-interval>1 cases tested (the interval-jump logic crosses exactly one "last day of week" pivot per step regardless of which weekday that is). This is a property of the current single-pivot-per-step scan, not a guarantee, and is still architecturally wrong (WKST should be a fixed rule property, and correct `BYWEEKNO` support - unimplemented anyway - would need real WKST semantics) - but do NOT assume it silently produces wrong dates without a fresh test; the earlier assumption that it demonstrably did was wrong. |
| 7 | `EXRULE` isn't recognized anywhere | whole class | not yet covered by a dedicated test (grep-confirmed absent) |
| 8 | Only one RRULE per event is representable | schema (single `egw_cal_repeats` row) | implicit in all of the above |
| 9 | REST/JSCalendar write path explicitly blocks recurrence: `JsCalendar::parseJsEvent()` throws for `recurrenceRules`/`recurrenceOverrides`/`excludedRecurrenceRules` | `api/src/CalDAV/JsCalendar.php` ~173-177 | `JsCalendarRecurrenceTest::testWritingRecurrenceRulesIsBlocked` |
| 10 | JSCalendar's `byDay` is emitted as a **bare NDay object**, not a JSON array as RFC 8984 requires (`$rule['byDay'] = array_filter([...])`, no outer `[...]`) - so even the one day it does support isn't spec-shaped JSON | `api/src/CalDAV/JsCalendar.php` ~980-984 | `JsCalendarRecurrenceTest::testRecurrenceRulesReadShape` |

## Concrete bugs found while building the harness (not fixed here - out of scope for this step)

- **Crash on import**: a VEVENT with both `RRULE` (carrying `UNTIL`) and `RDATE`, in that property
  order, throws an uncaught `TypeError: clone(): Argument #1 ($object) must be of type object, null
  given` instead of importing (even lossily). Mechanism: the `RDATE` property handler
  unconditionally overwrites `recur_type` to `RDATE` (see gap #1) even though `recur_enddate` is
  still the RRULE's `UNTIL`; `calendar_rrule::normalize_enddate()`'s `while ($this->current <
  $this->enddate)` loop doesn't handle `$this->current` becoming `null` after the RDATE-type
  iterator is exhausted (PHP treats `null < $enddate` as true, so it loops once more and crashes on
  `clone $this->current`). Reproduced by
  `IcalRruleRoundtripTest::testRruleWithUntilThenRdateCrashesOnImport()`. Likely reachable by any
  RDATE-type event whose `recur_enddate` ends up later than its last RDATE, not just this exact
  path.
- **Order-dependent semantic loss**: the same RRULE+RDATE combination *without* an UNTIL doesn't
  crash, but silently produces a different `recur_type` depending purely on which property comes
  first in the source `.ics` text (RRULE-then-RDATE loses the whole rule down to just the one extra
  date; RDATE-then-RRULE keeps the rule and silently drops the extra date instead). See
  `IcalRruleRoundtripTest::testRruleAndRdateTogetherIsOrderDependent()`.
- **Type quirk**: `calendar_rrule::$monthly_byday_num` is documented `int` but the non-"last week"
  branch computes it via `1 + floor(...)`, yielding a `float` (e.g. `2.0`, not `2`). The `-1`
  ("last week") branch assigns a literal int. See `RruleTest::testMonthlyWdaySecondWeekdayOfMonth`.
- **Leap-day drift**: a `YEARLY` series starting on Feb 29 drifts to Mar 1 in every non-leap year
  and never re-aligns back to Feb 29 in the next leap year (plain PHP `DateTime::modify('+1
  year')` behavior, nothing in `calendar_rrule` corrects it). See
  `RruleTest::testYearlyLeapDayStart`.
- **Minor**: `calendar_rrule::count2date()` references `$backup` without initializing it when
  `$this->current` was never set (fresh iterator, no `rewind()` yet) - produces an "undefined
  variable" warning under strict PHP error reporting, harmless in the observed case since the
  fallback branch is simply skipped.

## Test harness (this step)

- **`calendar/tests/RruleTest.php`** - pure-logic unit tests of `calendar_rrule` directly (no DB):
  occurrence generation per type incl. edge cases (month-end, last-weekday-of-month, WEEKLY
  multi-day+interval>1, leap-day YEARLY, HOURLY/MINUTELY, RDATE, EXDATE), `generate_rrule()`
  output, `count2date()`, the WKST-preference-invariance investigation, and a `parseRrule()` data-
  provider battery of real-world-shaped RRULE strings documenting exactly which parts survive vs.
  get silently collapsed.
- **`calendar/tests/IcalRruleRoundtripTest.php`** - the same gaps at the full "real `.ics` text in,
  real `.ics` text (or event array) out" integration level via `calendar_ical::icaltoegw()`/
  `exportVCal()` (no DB writes needed - `icaltoegw()` is a pure parse, `exportVCal()` accepts an
  in-memory event array with `id=-1`, same pattern as the sibling `IcalDateRoundtripTest.php`).
  This is where the COUNT-loss, multi-BYDAY-collapse, and RRULE+RDATE bugs above were found.
- **`calendar/tests/JsCalendarRecurrenceTest.php`** - read-path shape of `recurrenceRules`/
  `recurrenceOverrides` for a real recurring event (via `calendar_boupdate` + `JsCalendar::JsEvent()`
  directly, no HTTP needed), plus a regression test confirming `JsCalendar::parseJsEvent()` still
  throws for any recurrence-related key - the exact contract future write support must replace
  deliberately.

All three were run individually and together against the sibling recurrence tests
(`RecurrenceExceptionTest.php`, `IcalDateRoundtripTest.php`, `IcalImportTest.php`) with no
regressions. (`RecurrenceExceptionTest.php` has 5 pre-existing, unrelated failures + 1 error in
this environment - "bad login or password" / "failed sanity check" account-creation issues,
reproduced identically with none of the new files present; not caused by or related to this work.)

## Prior art (untracked, local-only - not touched by this work)

`calendar/inc/rrule_test.php` (untracked scratch file in this checkout, not committed) already
experiments with `\RRule\RRule`/`\RRule\RSet` - i.e. the **`rlanvin/php-rrule`** library - as a
drop-in replacement iteration engine. It is **not** a composer dependency currently (no
`vendor`/`composer.lock` entry). Worth reusing as the already-scoped library candidate for feature
1, not acted on here.

## Future phases (not started)

- **Phase 2**: design a schema that can hold a real RFC 5545 RRULE (or a serialized rule string)
  alongside/instead of the current `recur_type`/`recur_interval`/`recur_data` columns, migration
  path for existing events, and decide whether `rlanvin/php-rrule` (or another library) replaces
  `calendar_rrule`'s iteration entirely or is used only for parsing/generation.
- **Phase 3**: REST/JSCalendar write support for `recurrenceRules`/`recurrenceOverrides`/
  `excludedRecurrenceRules` in `JsCalendar::parseJsEvent()`, once the schema can actually hold what
  a real client will send.
- Neither phase should proceed without re-running (and extending) the 3 test files above against
  the new implementation - that's their whole purpose.
