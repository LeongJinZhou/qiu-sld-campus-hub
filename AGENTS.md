# Project Rules

- **Turn 1 Sync**: Read `AGENTS.md` and check `git status` first to maintain cross-tool continuity (Claude Code, Antigravity, Codex).
- **Surgical & Native-First (Karpathy/Ponytail)**: Touch ONLY requested files with minimal diffs. Prefer native browser/PHP features over new packages; zero unrequested refactoring or dead helpers.
- **Architecture & Quality**: Enforce SOLID, DRY, KISS, YAGNI, Separation of Concerns, Loose Coupling, and High Cohesion.
- **Concise Output (Caveman)**: Omit conversational filler. Output code diffs, file paths, and terminal commands directly.
- **Dual-Stack Auto-Detection**: If PHP (`*.php`/`composer.json`), use PHP 8+ PSR-12 and 100% parameterized PDO (`sqlite` local fallback / `mysql` Exabytes). If Next.js/TS (`package.json`), use strict TypeScript (no `any`), Tailwind, Zod validation, and Supabase.
- **QA & GitHub Hygiene**: Run `php -l <file>` (PHP) or `npm run build` (TS/JS) before finishing. Keep repo portfolio-ready: enforce `.gitignore` (never commit `.env`, `node_modules`, `*.sqlite`), use Conventional Commits (`feat:`, `fix:`, `docs:`, `chore:`), and keep `README.md` updated.
