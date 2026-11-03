# SPMS Codex Commands Guide

## Purpose

This file is the canonical slash-command contract for SPMS, adapted from the user's Doc-Ease catalog. These are instructions for Codex in this repository; they do not add application endpoints or register native commands in the desktop app.

When the user enters a recognized slash command, CODEX should execute the command's purpose directly, stay grounded in the real codebase, and respond in a concise engineering style.

## Core Rules

### 1) Execute recognized commands directly

Treat recognized slash commands as high-priority intent signals.

Do not ask unnecessary clarification questions when the command already defines the task clearly.

### 2) Parse arguments after `:`

If a command contains a colon, treat the text after `:` as the command argument.

Examples:

- `/smoke-page:commitment-planning`
- `/explain:app/Services/CommitmentPlanning.php`
- `/trace:target allocation`
- `/fix:texts not clear on commitment planning`

### 3) Support multiple targets when practical

If an argument contains multiple comma-separated targets, analyze all of them when the command semantics allow it.

### 4) Prefer practical engineering output

When relevant, structure responses around:

1. Summary
2. Relevant files
3. Logic flow
4. Problems or risks found
5. Recommended fix or next step

### 5) Stay grounded in the codebase

Do not guess when the repository does not support a claim. State uncertainty explicitly.

### 6) `/help` must always work

`/help` should list all supported commands, their purpose, and a short usage example.

### 7) Credential safety

When a command touches credentials, deployment config, or secrets:

- mask values by default
- do not print full tokens, passwords, webhook secrets, or private keys
- only reveal a full plaintext value if the user explicitly asks for that exact value and it is stored locally in accessible plaintext

## Output Style

Prefer these sections when useful:

- **Summary**
- **Relevant Files**
- **Logic Flow**
- **Issues Found**
- **Likely Cause**
- **Recommended Fix**
- **Risk Level**
- **Test Scenarios**
- **Impact**

Keep answers concise and actionable.

## Supported Commands

### `/help`

Show all supported commands.

Example:

```text
/help
```

### `/understand`

Study the codebase and summarize architecture, main modules, data flow, and coding conventions.

Example:

```text
/understand
```

### `/map`

Create a quick map of the project structure and explain what each major folder does.

Example:

```text
/map
```

### `/explain:{file|function}`

Explain one file, class, or function in plain language.

Examples:

```text
/explain:app/Services/CommitmentPlanning.php
/explain:app/Http/Controllers/CommitmentPlanningController.php
```

### `/trace:{feature}`

Trace how a feature works end-to-end across frontend, backend, API, DB, and state changes.

Example:

```text
/trace:target allocation
```

### `/impact:{file|feature}`

Check what parts of the app may break if a file or feature is changed.

Example:

```text
/impact:resources/views/planning/show.blade.php
```

### `/find-bugs`

Scan the codebase for bugs, logic errors, edge cases, broken imports, failing flows, and likely runtime issues. Report findings only.

Example:

```text
/find-bugs
```

### `/fix-bugs`

Find bugs, fix them safely, explain each fix, and list changed files.

Example:

```text
/fix-bugs
```

### `/fix:{issue}`

Fix one specific reported issue instead of scanning the whole codebase.

Example:

```text
/fix:texts not clear on commitment planning
```

### `/review`

Review the current branch or diff for bugs, code quality issues, security problems, and missed edge cases.

Example:

```text
/review
```

### `/refactor:{target}`

Refactor a specific file or module without changing behavior.

Example:

```text
/refactor:app/Services/CommitmentPlanning.php
```

### `/test`

Run or write relevant tests for the current changes.

Example:

```text
/test
```

### `/test-page:{name}`

Run focused UI and interaction checks for one page.

Example:

```text
/test-page:commitment-planning
```

### `/smoke-all`

Using Playwright, visit every major page, detect console errors, broken UI, failed requests, and navigation issues, then summarize results.

Example:

```text
/smoke-all
```

### `/smoke-page:{name}`

Using Playwright, test only the specified page, including load, buttons, forms, navigation, and visible errors.

Example:

```text
/smoke-page:commitment-planning
```

### `/repro:{issue}`

Try to reproduce a reported bug and identify the likely cause.

Example:

```text
/repro:planning form not opening
```

### `/lint-fix`

Run linting-related cleanup and fix safe style or code issues.

Example:

```text
/lint-fix
```

### `/type-fix`

Resolve TypeScript or typing errors only.

Example:

```text
/type-fix
```

### `/perf-check`

Look for slow queries, unnecessary rerenders, large bundles, and obvious performance bottlenecks.

Example:

```text
/perf-check
```

### `/security-check`

Check for auth issues, unsafe inputs, exposed secrets, weak validation, and risky endpoints.

Example:

```text
/security-check
```

### `/api-check`

Inspect API routes, request and response handling, validation, and error handling.

Example:

```text
/api-check
```

### `/db-check`

Inspect models, migrations, queries, indexing, and database-related bugs.

Example:

```text
/db-check
```

### `/logs-check`

Inspect logs or error output and summarize root causes.

Example:

```text
/logs-check
```

### `/docs-update`

Update README, setup steps, environment documentation, and usage docs based on recent code changes.

Example:

```text
/docs-update
```

### `/changelog`

Generate a clean summary of what changed for release notes or handoff.

Example:

```text
/changelog
```

### `/commit`

Prepare a clean commit message based on the current diff.

Example:

```text
/commit
```

### `/checkpoint`

Create a git checkpoint commit to save the current state of the workspace. If an argument is provided, it is appended to the checkpoint commit message.

Example:

```text
/checkpoint: before login page updates
```

### `/pr-summary`

Write a pull request title and description from the current changes.

Example:

```text
/pr-summary
```

### `/deploy-update`

Review and prepare the intended SPMS changes, run relevant checks, commit the selected files, and push to the current verified GitHub branch. Continue to live deployment only when an SPMS-specific deployment path is configured and verified.

Example:

```text
/deploy-update
```

### `/live-check`

Verify the intended local commit, its matching GitHub branch, and any configured SPMS deployment and public URL. Resolve branch names from the repository; do not assume `main` (the current default is `master`). Verify server state and browser-visible behavior before claiming an update is live.

If the live host or release path is absent, report GitHub status separately and identify the missing configuration. A successful push is evidence of GitHub publication only.

Also accept `/live_check`.

Example:

```text
/live-check
```

### `/live-resolve`

Diagnose a stale or failed SPMS deployment using its verified GitHub branch, deployment logs, deployed commit marker or files, and public behavior. Apply an available SPMS-specific repair within the user's authorized scope. If no live path is configured, explain that blocker and identify the configuration required; never run another project's release script.

Also accept `/live_resolve`.

Example:

```text
/live-resolve
```

### `/serve_update`

Publish reviewed SPMS changes to the verified GitHub remote and current branch after relevant checks. The persistent default is `/serve_update: ON`, including new conversations. Publishing uses SPMS's own Git repository; Doc-Ease's `scripts/serve-update.ps1`, aaPanel webhook, Hostinger references, and public news file are not part of SPMS's verified setup.

Modes:

- `/serve_update: ON`: automatically inspect the intended diff, run relevant checks, commit only intended files, push, and verify the remote commit after each completed intentional repository change.
- `/serve_update: OFF`: keep changes local after verification unless the user explicitly requests publication.
- `/serve_update: AUTO`: use judgment for narrow changes; obtain any genuinely required review for sensitive changes. Existing user authorization still applies.
- `/serve_update` without an argument: publish now using the current mode and the user's explicit command. An explicit one-time publication request does not silently change the persistent mode.

Accept `/serrve_update: OFF` as the existing typo compatibility alias for `/serve_update: OFF`.

Before publishing:

1. Read repository instructions; inspect the branch, remote, index, and worktree.
2. Identify the exact intended files. Preserve unrelated changes and do not use broad staging or force pushes.
3. Run checks relevant to the diff. Do not report a targeted subset as the entire suite passing. The last recorded complete baseline is 168 passing and 36 failing tests; refresh that result when the full suite is run.
4. For documentation-only changes, check command coverage, local links, and whitespace. Runtime changes need their relevant tests/build; schema and allocation changes also need disposable PostgreSQL validation.
5. Commit intended files and push to the current verified branch, establishing tracking if needed. Verify the remote hash matches the intended commit. Follow a future SPMS release script if one is deliberately configured.
6. If checks fail, isolation is unclear, authentication fails, or the destination is unconfigured, report the exact blocker. Do not silently call the work published.

Do not merge into the default branch, rewrite history, apply migrations to institutional data, activate planning periods, or claim a live deployment solely because `ON` is enabled. Those actions require their own task scope and verified configuration.

Examples:

```text
/serve_update
/serve_update: ON
/serve_update: OFF
/serve_update: AUTO
```

## Academic Instructional Materials Commands

Preserve these Doc-Ease command names for compatibility when the user explicitly requests academic drafting. SPMS has no Academe Generator, class-record source, or academic command runner. Do not claim that a Doc-Ease application integration exists here or import its records automatically.

For a drafting request, use the user's supplied syllabus, modules, assessment specifications, and institutional templates. User instructions and the applicable instruction hierarchy take precedence. Missing course identity, module outcomes, or assessment details require a stated readiness gap or clarification; do not transfer Doc-Ease's module-specific assumptions to SPMS.

Supported academic commands:

- `/create_module: {module_no}`
- `/create_module: {module_no}, chapters={number}`
- `/create_activity: {module_no}, {activity_no}`
- `/create_activities: {module_no}, count={number}`
- `/create_lab: {module_no}`
- `/create_assignment: {module_no}, type={lecture|laboratory|both}`
- `/create_quiz: {module_no}, items={number}`
- `/create_exam: {term}, type={lecture|laboratory|both}, items={number}`
- `/create_tos_tqs: {term}`
- `/create_rubric: {assessment_type}`
- `/create_video_guide: {module_no}, {activity_no}`
- `/create_capstone_guide: {module_no}`
- `/create_documentation_template: {project_type}`
- `/revise_format: {file_name}`
- `/audit_alignment: {file_name}`
- `/generate_docx: {material_name}`
- `/create_all_for_module: {module_no}`

Create review drafts using the applicable document, PDF, spreadsheet, or presentation skills and the requested formats. Preserve the original command expectations: DOCX/PDF; XLSX companions for tables, rubrics, TOS/TQS, quizzes, exams, grading criteria, and answer keys; PPTX companions for module briefings, presentation/video guides, labs, demos, and capstone guides; ZIP bundles for package commands. Report unavailable tools or missing sources instead of pretending an export or integration succeeded.

## Command Style Guide

- use verb-first commands
- use lowercase
- use hyphen-separated names
- avoid abbreviations
- keep one command focused on one job
- use `:{arg}` for inputs
- keep underscore aliases only for legacy or user-requested compatibility

Examples:

- `/fix-bugs`
- `/trace:target allocation`
- `/smoke-page:login`
- `/explain:app/Services/CommitmentPlanning.php`
