# AI Agent Instructions

This file is the canonical instruction source for AI coding agents working on this repository.

## Core principles

- Make focused, minimal diffs.
- Preserve existing architecture and coding style.
- Inspect nearby code and follow established patterns.
- Prefer modern standards and APIs where they fit the existing codebase.
- Do not rewrite whole files unless necessary.
- Do not introduce unrelated formatting churn.
- Present a plan and ask before making broad architectural changes.
- When uncertain, ask for clarification before making changes.

## Project context

## EGroupware project context

EGroupware is a large PHP/TypeScript/JavaScript web groupware application. The main repo contains many first-party apps
such as `api`, `admin`, `calendar`, `addressbook`, `mail`, `filemanager`, `infolog`, `timesheet`, `resources`, `setup`,
and others. Do not assume changes are isolated to one app without checking shared `api` and `setup` code.

### Repository shape

- Backend code is primarily PHP.
- Frontend code includes TypeScript, CSS, HTML, and build tooling.
- Shared backend framework code lives under `api/`.
- Frontend framework code lives under `kdots/` and `api/js/etemplate`.
- Database setup and upgrade logic lives under `setup/` and app-specific setup directories.
- Individual applications live in top-level directories such as `calendar/`, `addressbook/`, `mail/`, `infolog/`, and
  `timesheet/`.

Primary expectations:

- Respect EGroupware conventions.
- Maintain backwards compatibility unless the task explicitly says otherwise.
- Prefer incremental, reviewable changes.
- Avoid speculative abstractions.
- Develop a plan before making changes.
- Keep UI, API, and database changes aligned with existing patterns, but mention improvements to match modern best
  practices.

## Code change rules

- Read the relevant files first.
- Search for existing implementations before adding new ones.
- Identify the smallest safe change.
- Keep diffs small and app-scoped when possible.
- Before modifying app behaviour, check whether the pattern is implemented in another EGroupware app. Suggest
  refactoring when appropriate.
- Avoid changing shared `api/` behaviour unless the task requires it.
- For schema or setup changes, check app setup files and update paths.
- Preserve backwards compatibility for existing installations.
- Do not remove legacy compatibility code without explicit approval.
- Check whether tests, migrations, translations, or documentation need updates.
- Do not make commits without explicit instructions.
- Do not modify generated JavaScript files, they're automatically built.

## Coding standards

See `doc/ai/coding-standards.md`.

## Testing

See `doc/ai/testing.md`.

Before finalizing:

- Run the most relevant available tests when practical.
- If tests cannot be run, state why.
- Mention any untested risk areas.

## Reviews

For code review behaviour, follow `doc/ai/review-checklist.md`.

## Security and data handling

- Do not commit secrets, tokens, credentials, private keys, or production data.
- Do not weaken authentication, authorization, validation, escaping, or CSRF protections.
- Treat user input as unsafe.
- Preserve existing permission checks.

## Output expectations

When reporting work:

- Summarize what changed.
- Mention tests run.
- Mention known limitations or follow-up risks.