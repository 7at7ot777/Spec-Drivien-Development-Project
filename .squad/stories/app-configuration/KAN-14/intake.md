> **Fetched from jira:** [KAN-14](https://mohamedhathout3.atlassian.net/browse/KAN-14)  
> *Fetched 2026-10-25T00:06:54.771Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** Set up Laravel project, MySQL, env config and repo structure  
**Type:** Task  
**Status:** To Do

### Description

Create the new repository and base Laravel application.

Acceptance criteria

	New Git repo with Laravel (latest stable) and MySQL connection

	.env.example documents every required variable (DB, mail, SMS, Fawry, storage)

	Folder conventions agreed (e.g. Actions/Services, API Resources, Form Requests)

	README with local setup steps

	App runs locally and migrations run on an empty DB

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/app-configuration/KAN-14/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `app-configuration`

## Tracker (metadata only)

- **Tracker type:** `jira`
- **Work item id:** `KAN-14` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `Task`
- **Status:** `To Do`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
Set up Laravel project, MySQL, env config and repo structure
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
Create the new repository and base Laravel application.

Acceptance criteria

	New Git repo with Laravel (latest stable) and MySQL connection

	.env.example documents every required variable (DB, mail, SMS, Fawry, storage)

	Folder conventions agreed (e.g. Actions/Services, API Resources, Form Requests)

	README with local setup steps

	App runs locally and migrations run on an empty DB
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```

```

---

## Attachments

Place files in `attachments/` next to this `intake.md`, then list them here so the planner knows what to open.

| File (relative to this folder) | What it is |
| ------------------------------ | ---------- |
| *(e.g. `attachments/flow.png`)* | *(e.g. UX flow)* |

*(Add rows per file. If none, write "None.")*

---

## Dependencies

- **Blocked by / related ids:** (tracker ids only; optional short note)
- **Depends on code areas or other stories:**

## Extra notes (optional)

- Anything not captured above (e.g. chat context) — keep short.
- Push the initial commit to GitHub: `https://github.com/7at7ot777/Spec-Drivien-Development-Project` (branch `main`, no force push).

## Technical hints (optional)

- APIs, screens, services already discussed. Repos/roots: `.`. Primary language: `php`.

## Out of scope

- What this story explicitly does **not** cover:
