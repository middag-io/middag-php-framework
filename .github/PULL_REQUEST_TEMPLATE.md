<!--
Thanks for contributing to middag-io/framework!
Please fill in the sections below. PRs target the `main` branch.
-->

## Summary

<!-- What does this PR change, and why? Keep it focused on a single concern. -->

## Related issues

<!-- Link issues this PR addresses. Use "Closes #123" to auto-close on merge. -->

Closes #

## Type of change

<!-- Check all that apply. These mirror the Conventional Commit type used in your commit messages. -->

- [ ] `feat` — a new feature
- [ ] `fix` — a bug fix
- [ ] `refactor` — a code change that neither fixes a bug nor adds a feature
- [ ] `docs` — documentation only
- [ ] `test` — adding or correcting tests
- [ ] `chore` — tooling, build, or maintenance changes
- [ ] `perf` — a performance improvement
- [ ] `style` — formatting only (no behavior change)

## Breaking change

<!--
Does this PR change public API or behavior in a backward-incompatible way?
If so, describe the break and the migration below.

On the 1.x line, do NOT use `!` or a `BREAKING CHANGE:` footer: release-please
would cut 2.0.0 in this lib alone, and the family window check
(`php-version-family-check / check`) refuses both outside a family MAJOR
window. Until 2026-11-30 a break ships as `feat:`/`fix:` with the break and the
migration in the commit body; from 2026-12-01 no break lands on 1.x.

From 2.0.0, `feat:` waits for a family MINOR window and a break for a family
MAJOR window, both opened by the product owner through the organization
variable `PHP_LIBS_OPEN_RELEASE`. Keep a waiting PR current by merging `main`
into it (never rebase or force-push). See API-STABILITY.md.
-->

- [ ] This PR introduces a breaking change (described in the commit body and below)
- [ ] The family window check passes

## Checklist

- [ ] Targets the `main` branch
- [ ] `composer check` passes locally
- [ ] `composer test` passes locally
- [ ] Commits follow Conventional Commits
- [ ] No `Co-Authored-By` trailers in any commit
- [ ] Docs / `CHANGELOG.md` updated when relevant
- [ ] New behavior is covered by tests
