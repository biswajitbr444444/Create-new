# PocketCat V11.26 — Search + Code Editor functional fix

## Global Search
- Expanded workspace search beyond tasks/notes/projects to include code snippets, uploaded files/media, short links, support messages, documentation, and administrator users/site pages.
- Added searchable aliases for Settings, AWS Bedrock, Support, Help, Documentation, Media, Files, Code Editor and other navigation areas.
- Added safe query highlighting and a 10-second visual result highlight.
- Added `Ctrl/Cmd + K` and `/` keyboard focus shortcuts on the global search page.
- Kept administrator-only search targets restricted to administrators.

## Users tab
- Added server-side user search by name, username, email, role, plan and status.
- Added matching-text highlighting and a 10-second result-row highlight.
- Added `Ctrl/Cmd + K`, `/`, and Escape keyboard behavior.

## Code Editor
- Restored the original Live Code Editor Pro "My New Code / My Edited Code" workflow as a dedicated My Code panel with All/New/Forks filtering.
- Added backend filtering for new snippets versus forked/edited snippets.
- Fixed `/code/?id=<id>` navigation so a search result can open the selected code snippet directly.
- Preserved Prism syntax highlighting, live preview, resize, word count, history, advanced search/settings, share/copy/download, version history and BYOD controls.

## Validation
- PHP syntax checks passed for all modified PHP files.
- JavaScript syntax check passed for the modified code editor script.
