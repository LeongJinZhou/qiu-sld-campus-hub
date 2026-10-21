# QA & GitHub Rules
- Run `php -l <file>` for PHP or `npm run build` for JS/TS before finishing.
- Verify `git diff` contains zero leftover debug logs (`var_dump`, `console.log`) or untouched file edits.
- Never stage `.env`, `*.sqlite`, `node_modules/`, or `.DS_Store`; use clean Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`).
