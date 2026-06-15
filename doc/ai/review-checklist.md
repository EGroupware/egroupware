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

* Confirm complexity is justified by the problem and matches local patterns.
* Check for hidden coupling, brittle assumptions, and hard-to-test logic.
* Prefer concrete simplifications when they reduce risk without changing behaviour.

## 9) Frontend-specific checks (when applicable)

* UI changes should follow existing EGroupware frontend patterns and components.
* Verify accessibility basics: labels, focus behaviour, keyboard usage, and readable states.
* Ensure no layout breakage from long text, localization, or responsive constraints.
* Confirm no unnecessary visual churn outside requested scope.

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
