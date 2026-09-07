# Project Agent Instructions

## Default UI Workflow

For every UI, layout, styling, frontend, dashboard, table, form, or visual presentation change in this project, use the Impeccable skill workflow by default.

Preferred command/prompt:

```text
/impeccable polish <target-file-or-page> รักษา business logic เดิม ห้ามแก้ SQL query, session, permission, routing, form action, API endpoint, AJAX payload, status value หรือ sidebar ปรับเฉพาะ alignment, spacing, typography, responsive behavior และรายละเอียด UI
```

If slash commands are unavailable, use this equivalent instruction:

```text
Use the Impeccable skill to polish <target-file-or-page>. Keep all business logic, SQL queries, sessions, permissions, routing, form actions, API endpoints, AJAX payloads, status values, and sidebar behavior unchanged. Only improve alignment, spacing, typography, responsive behavior, and UI details.
```

## Safety

- Check `git status` before and after UI work.
- Do not commit or push unless the user explicitly asks.
- Do not edit synced or generated dependency folders unless required by the task.
- Start with the smallest relevant page/file when possible.
