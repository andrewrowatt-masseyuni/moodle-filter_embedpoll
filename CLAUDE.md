# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Where this plugin sits

This directory is the `filter_embedpoll` plugin and **its own git repository** (remote
`git@github.com:andrewrowatt-masseyuni/moodle-filter_embedpoll.git`, branch `main`). It is one of
three plugins that make up embedded polls:

| Plugin | Path | Role |
| --- | --- | --- |
| `local_embedpoll` | `local/embedpoll` | Storage, votes, form, rendering, voting JS, backup. Everything except the two below. |
| `filter_embedpoll` (this one) | `filter/embedpoll` | Swaps each token in formatted text for the rendered poll. |
| `tiny_embedpoll` | `lib/editor/tiny/plugins/embedpoll` | The TinyMCE button that adds, edits, moves and previews polls. |

Each is its own repository. All three are checked out inside a Moodle 4.5 core checkout (branch
`MOODLE_405_STABLE`) at `/home/arowatt/moodle405_filter_embedpoll`. Commit from inside this
directory; never commit into the outer core repo.

The shared design lives in `local_embedpoll`, not here:

* `local/embedpoll/README.md` is the design record for all three plugins: why each non-obvious
  decision was made. Read the relevant section before changing behaviour, and update it in the
  same change.
* `local/embedpoll/CLAUDE.md` has the cross-plugin map and the full list of traps. Read it before
  any change that touches more than this filter.

The design copies `local_edguidance` (`/home/arowatt/moodle405_local_edguidance`).

## Commands

In the container, cwd `/var/www/html` (`docker exec moodle405_filter_embedpoll-webserver-1 ...`):

```bash
vendor/bin/phpunit --testsuite filter_embedpoll_testsuite
vendor/bin/phpunit --filter test_unknown_key filter/embedpoll/tests/text_filter_test.php   # one test
vendor/bin/phpunit privacy/tests/privacy/provider_test.php
php admin/tool/behat/cli/run.php --tags=@filter_embedpoll --format progress   # with -u www-data
php admin/tool/phpunit/cli/init.php && php admin/tool/behat/cli/init.php     # after db/ or version.php changes
php admin/cli/upgrade.php --non-interactive && php admin/cli/purge_caches.php
php local/moodlecheck/cli/moodlecheck.php -p=filter/embedpoll -f=text         # PHPDoc
```

For one feature file, or screenshots, run Behat directly with `-u www-data`:
`vendor/bin/behat --config /var/www/behatdata/behatrun/behat/behat.yml <feature>`, adding
`--format=moodle_screenshot`.

On the host, from the Moodle root:
`../moodle-plugin-ci/bin/moodle-plugin-ci phpcs --max-warnings 0 ./filter/embedpoll` (also
`phplint`, `phpmd`, `phpcbf`). Run `grunt --max-lint-warnings=0 gherkinlint` from this directory.
`.vscode/tasks.json` holds all of these as tasks.

CI (`.github/workflows/moodle-ci.yml`) also runs `validate`, `savepoints`, `phpdoc --max-warnings 0`
and `phpunit --fail-on-warning`. It fetches `local_embedpoll` from `main` with
`moodle-plugin-ci add-plugin`. So a change here that needs a change there only passes CI once the
`local_embedpoll` change has been pushed.

## How the filter works

All the logic is in `classes/text_filter.php`, and most of it calls `local_embedpoll`:

* `setup()` loads `local_embedpoll/poll` (the voting JS) once per page.
* `filter()` returns at once unless the text contains `data-embedpoll`. Otherwise it hands each
  token's key to `local_embedpoll\token::replace()`.
* `polls()` picks the polls this context can show:
  * a module context: `poll::for_cm()`, that activity's polls;
  * a course context other than the site: `poll::for_sections()`, any of the course's section polls;
  * anywhere else: `null`, so every token is removed.
* A key that does not resolve shows the `notfound` notice to anyone with `poll::CAP_MANAGE`, and
  nothing to anyone else.
* A key that resolves is rendered by `local_embedpoll\output\widget::render_row()`.

This plugin has no AMD, templates, CSS or tables of its own. Changes to how a poll looks or behaves
belong in `local_embedpoll`.

## Traps

* **Keep the `get_config('local_embedpoll', 'version')` checks** in `setup()` and `filter()`. With
  `local_embedpoll` missing, the filter must leave text alone rather than fail.
* **The rendered poll must never contain `data-embedpoll`.** `mod_lesson` filters some text twice.
  `test_filtering_twice_is_a_no_op` pins this.
* **Output is per user** (voted or not, may see results or not). That is safe only because Moodle
  4.5 has no text cache.
* **Tokens survive only in text formatted with `noclean`**, because HTMLPurifier strips `data-*`.
  `test_cleaned_text_shows_no_poll` pins that cleaned text shows no poll. Do not change core or a
  theme to work around it.
* **Section keys are scoped to the course**, because a filter is told the context but not the
  section. Activity keys and section keys never resolve in each other's context.
* **`SITEID` is a string.** Compare it as `(int)SITEID`.
* **`filter.php` is only a `class_alias` shim.** Keep the logic in `classes/text_filter.php`.
* **The filter is not switched on when it is installed**; an admin turns it on. Tests that go
  through `format_text()` switch it on themselves, and so do the Behat features in
  `local_embedpoll` and `tiny_embedpoll`.
* **Tests build polls with the `local_embedpoll` generator** (`create_poll` with `cmid` or
  `sectionid`, plus `options`), and call `poll::reset_cache()` in `setUp()`, because polls are
  cached per request.
* **This plugin's Behat feature only checks that it is installed.** The behaviour on real pages is
  covered by `local_embedpoll`'s features (`polls_in_label`, `polls_in_book`).
* **Keep `$plugin->dependencies` on `local_embedpoll` (2026101700) in step** with what the filter
  calls. `$plugin->supported` is `[405, 405]`.
* Lang keys are kept in alphabetical order.
