# Code Review Checklist for AI Agents

Use this checklist when reviewing changes in this repository.
It supplements `AGENTS.md` and avoids restating baseline repo policy.

## Review posture

* Prioritize correctness, regressions, security, and compatibility over style nits.
* Prefer evidence-based findings tied to concrete files and lines.
* Keep scope focused on the requested change; avoid unrelated refactor demands.
* If no significant issues are found, explicitly say so and mention residual risks.

## 1) Understand scope before judging

* Read the task/request and identify intended behaviour.
* Inspect surrounding code and existing patterns in the same app, other apps, and related shared code.
* Check for touched areas in shared `api/` or `setup/` that may increase blast radius.

## 2) Validate correctness and regressions

* Does the implementation satisfy the requested behaviour?
* Are edge cases handled consistently with nearby code?
* Are error paths, null/empty inputs, and type assumptions handled safely?
* Are there hidden behavioural changes for existing callers?
* For bug fixes, is there a regression test or clear reason why not?
* For any line the change DELETES or weakens, find the commit that added it (`git log -L
  <start>,<end>:<file>`) before accepting that it was dead weight. A green suite is not
  evidence: a fix that removed an "obviously redundant" `requestUpdate()` from
  `Et2Datagrid._markRowHeightUnstable()` passed all 2631 api tests while re-introducing the
  stranded-rows bug `f822c00d3f` had fixed - the guard that depended on it said so only in a
  comment.

## 3) Check compatibility risks

* Identify which existing installations, clients, and integrations may be affected.
* Check whether public APIs, method signatures, hooks, or config semantics changed unintentionally.
* For setup/upgrade-related changes, verify both new-install and upgrade-path behaviour.
* Confirm preference-related changes include safe defaults and migration handling.

## 4) Security and permission risks

* Validate input handling, escaping, and output encoding in changed paths.
* Ensure authorization and permission checks remain correct along all changed paths.
* Check CSRF-sensitive flows for existing protections.
* Flag changes that could expand access, bypass checks, or leak sensitive data.

## 5) Data and schema safety

* Check migration idempotency and failure behaviour.
* Confirm data transformations preserve existing data semantics.
* Flag missing rollback/repair considerations where relevant.

## 6) Cross-app and shared-code impact

* If `api/` is touched, search for callers and assess likely impact across apps.
* Confirm behaviour remains consistent with patterns used in other first-party apps.
* Call out cross-module coupling or hidden dependencies introduced by the change.

## 7) Tests and verification

* Confirm the most relevant tests were run (targeted first, broader when needed).
* Verify test claims match commands/results provided.
* If tests were not run, require a stated reason and risk assessment.
* Check whether missing tests create regression risk in changed behaviour.

## 8) Code quality and maintainability

* Prefer best practices and readability over clever or quick solutions.  
* Confirm complexity is justified by the problem and matches local patterns.
* Check for hidden coupling, brittle assumptions, and hard-to-test logic.
* Prefer concrete simplifications when they reduce risk without changing behaviour.

## 9) Frontend-specific checks (when applicable)

* UI changes should follow existing EGroupware frontend patterns and components.
* Verify accessibility basics: labels, focus behaviour, keyboard usage, and readable states.
* Ensure no layout breakage from long text, localization, or responsive constraints.
* Confirm no unnecessary visual churn outside requested scope.

### Widgets that can appear in a nextmatch/datagrid row

`Et2Datagrid` renders a row by handing `rowElement.outerHTML` to lit's `unsafeHTML()`, which
replaces the whole row node whenever that string changes - re-running every widget constructor
in the row.

* A widget usable in a row template must not change its OWN attributes after first render.
  In particular, do not `reflect: true` a property whose value is resolved asynchronously: the
  late write lands in the row's serialized HTML and tears the row down.
* Do not write `async willUpdate()` or `async updated()`. Lit does not await them, so everything
  after the first `await` runs once the update has already committed; assigning a reactive
  property there schedules a further update cycle per instance.
* A `requestUpdate()` inside a timer or observer callback must be conditional on something having
  actually changed. An unconditional one re-renders, which re-observes, which fires the observer
  again - a settled grid then re-renders forever with no user input.

### Tests that assert an ABSENCE of work

* Et2Nextmatch measures row height and the virtualizer range from `ResizeObserver` and `rAF`. A
  BACKGROUNDED tab stops both, so the grid does nothing - and "the grid did no work" is exactly
  what such a test asserts. It would pass while the defect is present, and checking that rows
  rendered does not catch it (with both APIs stubbed, 24 rows still render).
* Headless is NOT the problem and needs no special handling: this runner's headless tabs report
  `visibilityState: "visible"` with both APIs firing. The protection is `concurrency: 1` on the
  Playwright launchers in `web-test-runner.config.mjs` - read the comment there before changing
  it.

## 10) Review output format

When writing the review:

* List findings first, ordered by severity:
    * `High`: likely bug, security risk, data-loss risk, major regression
    * `Medium`: correctness risk, compatibility gap, missing required test coverage
    * `Low`: maintainability concern or minor inconsistency
* Each finding should include:
    * file + line reference
    * why it matters (impact)
    * concrete recommendation
* After findings, include:
    * open questions/assumptions
    * brief change summary
* If no findings, state: no significant issues found, plus any remaining test gaps/risk.
