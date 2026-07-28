# Cursor Kickoff Prompt — Sprint Planning

*Paste this into Cursor as the first prompt for this project. It deliberately asks Cursor to plan and ask questions before writing any code.*

---

```
TASK: Read the full design spec for a WordPress plugin and produce a sprint plan.
Do NOT write any implementation code in this task — planning only.

## READ FIRST — read all seven files in this exact order before doing anything else

1. Order-Management-Requirements.md — the "why": every scoping decision made,
   what's in/out of scope for v1, and the settled answers to the open questions
   raised during requirements review.
2. 01-Data-Model.md — the database schema (custom MySQL tables via $wpdb),
   entity relationships, and a short "Open items" section at the end.
3. 02-API-Integration.md — eBay Sell API and Etsy API v3 integration details:
   auth flows, endpoints, field mappings, and its own "Open items" section.
4. 03-Workflow-Engine.md — the configurable workflow/state-machine design
   (manual-confirm, timer, and script/API step types), plus "Open items".
5. 04-WordPress-Integration.md — the plugin architecture: file/folder structure,
   admin UI pages, internal REST API, cron jobs, security notes, "Open items".
6. 07-MCP-Integration.md — read-only AI/MCP querying for Cursor (local dev) and
   Claude (live site), built on WordPress's Abilities API + MCP Adapter, plus
   "Open items". Lowest-priority phase, but part of the full spec.
7. 05-Implementation-Roadmap.md — the intended phased build order and the
   front-end approach (plain PHP admin screens first, richer UI later if needed).

If any file is missing or unreadable, stop and report back before continuing.

## CONTEXT

This is a WordPress plugin (PHP + MySQL, no separate backend service) that
aggregates orders from eBay and Etsy into one admin dashboard, tracks each
order through a user-defined production workflow (with manual/timer/script
gates), tracks material stock, and pushes price/quantity updates back to
listings. It's a solo-developer side project — the person using this tool is
also the sole end user. n8n is already self-hosted and available as an
integration target for workflow steps.

For local WordPress development and testing, this project uses **`wp-env`**
(the official `@wordpress/env` tool, Docker-based) and **Local** (Local by
Flywheel/WP Engine) — both are the intended dev environments, not a choice
between them; establish how they're expected to be used together (e.g.
`wp-env` for fast, disposable/CI-style spin-up and automated test runs,
Local for day-to-day browsing/debugging of the admin UI) as part of your
environment setup below, and ask if the intended split isn't obvious.

The five design files above represent a settled v1.0 spec — the scoping
questions have already been asked and answered. However, each file also has
its own "Open items" section listing smaller decisions that were deliberately
left for build time rather than guessed at up front.

## YOUR TASK

1. **Produce a numbered list of every "Open item"** found across all design
   files (01 through 05, plus 07), grouped by which file they came from. For
   each one, note whether it blocks a specific phase from the roadmap
   (05-Implementation-Roadmap.md) or can be resolved later without blocking
   anything.

2. **Ask me any clarifying questions you have** before planning sprints —
   about the open items, about anything ambiguous or underspecified in the
   design docs, or about your own WordPress/PHP environment assumptions
   (PHP version, WordPress version, whether Composer/any package manager is
   already in use, existing plugins that might conflict, and how you want
   `wp-env` and Local to divide responsibilities — see Context above). Do
   not guess silently on anything that would change the shape of the code.

3. **Break the 11-phase roadmap (05-Implementation-Roadmap.md) into concrete
   sprints.** A sprint should be small enough to review and test in one
   sitting — likely 1-3 roadmap phases per sprint depending on size, but use
   your judgement; some phases (e.g. Phase 7, the workflow engine) may need
   splitting into multiple sprints on their own. For each sprint, list:
   - Sprint number and name
   - Which roadmap phase(s) it covers
   - The specific files it will create/modify
   - What "done" looks like for that sprint (a short, testable description —
     e.g. "can view a list of synced eBay/Etsy orders in wp-admin")
   - Any of the open items from step 1 that this sprint needs resolved first

4. **Set up the Cursor environment for this project**, aimed at getting the
   best possible results for a WordPress/PHP plugin build specifically:
   - Create whatever Cursor project-level rule file(s) are appropriate
     (e.g. `.cursor/rules/*.mdc` or `.cursorrules`, whichever is current for
     the version of Cursor in use — check rather than assume) encoding: WordPress
     coding standards (naming, `$wpdb` prepared-statement usage, hooks/filters
     conventions), the plugin's own architecture from 04-WordPress-Integration.md
     (folder structure, class naming, the local-action allowlist security
     rule from 03-Workflow-Engine.md), PHP version target, and a pointer back
     to the six design docs so future sessions in this project have them as
     standing context rather than needing to be re-fed them each time.
   - Set up `wp-env` config (`.wp-env.json`) for this plugin — PHP version,
     WordPress version, plugin auto-mounted, and any seed data/test fixtures
     worth pre-loading (e.g. dummy eBay/Etsy credentials for local testing,
     given real OAuth won't work against a throwaway local site).
   - Document how Local fits alongside `wp-env` — setup steps for pointing
     Local at this plugin, and when to use which tool (ask me if the split
     you'd assume isn't obvious rather than guessing).
   - Note any other setup that would meaningfully improve results (e.g. PHP
     linting/PHPCS with the WordPress Coding Standards ruleset, a `.editorconfig`,
     recommended Cursor settings for PHP).

5. **Write the full result to a new file called `Sprint-Plan.md`** in the
   project root, containing:
   - The consolidated open-items list from step 1
   - Your clarifying questions from step 2 (as a visible section, even after
     I've answered them in chat — keep a record)
   - The full sprint breakdown from step 3
   - A summary of the environment setup from step 4, with pointers to the
     actual rule/config files you created (don't duplicate their full content
     inline — link to them)
   - A short "Environment/setup assumptions" section documenting whatever
     you learn about my dev environment during this conversation, so future
     sprint prompts don't need to re-establish it

## RULES

- Do not write any plugin code in this task. This is planning only —
  environment/rule/config files from step 4 are the one exception, since
  they're tooling setup rather than application code.
- Do not silently resolve an "Open item" yourself — surface it as a question
  or, if you have a genuine recommendation, present it as a recommendation
  and ask me to confirm rather than assuming.
- Keep the sprint plan grounded in the roadmap's phased order and the
  "pause after Phase 4" checkpoint noted in 05-Implementation-Roadmap.md —
  don't reorder phases without flagging why.
- If you encounter a decision in the design docs that seems inconsistent
  or in conflict with another file, stop and report it rather than picking
  one interpretation silently.
- For the Cursor rule files themselves: keep them focused on this project's
  actual conventions (from 04-WordPress-Integration.md and the WordPress
  coding standards), not generic boilerplate — if you're not sure what
  belongs in a project rule file versus what Cursor already handles by
  default, ask.

If you have questions before you can complete steps 1-5, ask them now.
```
