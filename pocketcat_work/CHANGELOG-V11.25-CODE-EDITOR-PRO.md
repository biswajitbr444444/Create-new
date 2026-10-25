# PocketCat V11.25 — Live Code Editor Pro integration

## Code Editor integration
- Restored the original Live Code Editor Pro control set and responsive mobile layout.
- Added working Night/Day mode with Prism theme switching.
- Added working Resize panel: Default, Medium, Half Screen, Desktop/Large and Full Screen.
- Added live Word Count statistics: lines, words, characters and characters without spaces.
- Added History panel with sorting, limits, pagination, previous/next navigation, version history, per-item delete and bulk delete.
- Added Advanced Search panel with title search, sort and result limits.
- Added Advanced Settings panel for tags, category, syntax, expiration, password and exposure.
- Added separate Share and Copy Link actions with clipboard fallback.
- Added Copy Code and Download actions with TXT/HTML/Python/C/C++/C#/CSS/JSON/Java/JavaScript/Lua/Markdown/PHP/Ruby/SQL/XML/Bash/Objective-C formats.
- Added read-only/edit mode behavior and a working Cancel Edit path.
- Added live syntax highlighting using the existing Prism Web Worker and synchronized line numbers/scrolling.
- Added Live Preview for HTML, CSS and JavaScript in a sandboxed iframe with the existing loop-safeguard approach.
- Preserved the existing PocketCat Workspace Pro multi-file area and updated its editor bridge to the restored editor textarea.
- Preserved Personal Firebase E2EE/BYOD controls.

## Backend fixes
- Added missing `2 Weeks` and `6 Months` expiration choices to the server-side expiry mapper.
- Fixed code update expiry handling so an explicit expiry selection correctly replaces/clears the previous expiration instead of relying on a precedence-sensitive expression.

## Validation
- PHP syntax checked for the modified code editor, API and code helper files.
- JavaScript syntax checked for the modified code editor and workspace bridge.
