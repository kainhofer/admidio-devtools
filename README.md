# Admidio DevTools

Tools for developers, reviewers and translators of Admidio 6 and its plugins.

**Only for development and test installations.** The plugin does nothing unless
`$gDebug = true;` is set in `adm_my_files/config.php` – the same switch that allows pre-release
versions in Admidio's plugin and core updater. All pages are for administrators only. While the
plugin is active, the header bar shows a *DevTools* badge.

Requires Admidio 6.0 or later and PHP 8.2 or later.

## Installation

Install it from the Admidio plugin directory, or upload the ZIP archive of a release in
*Administration → Extensions → Add plugin*. Then install and enable it, and set `$gDebug = true;`
in `adm_my_files/config.php`. The menu entry *DevTools* appears below *Administration*.

## Features

| Page | What it does |
|---|---|
| Snapshots | Take a snapshot of the files and the database; delete snapshots; show the restore key. |
| Restore page | Restore a snapshot – works without Admidio, even when an update broke the installation. |
| Hook inspector | All registered hook callbacks with owner and source location, all dispatch sites in core and plugins, and the problems both show together (hooks nobody dispatches, plugin hooks without prefix, filters several plugins change). |
| Hook traces | The hook callbacks of the last 50 requests in order, with duration, arguments, and for filters the value before and after. Switch on *Hook trace* in the settings. |
| Logs & errors | The Admidio, CLI and core-update logs and the PHP error log, filtered by level and text; every exception that ended a request, with stack trace. |
| CLI tasks | All commands and maintenance jobs of the `admidio` command line; run them from the browser. |
| Mail catcher | *Catch* (default): no mail leaves the installation, every mail is kept with HTML preview, text and `.eml`. *Redirect*: every mail goes to one address. |
| Preferences diff | The preferences of an organization compared with their defaults: changed, missing and unknown. |
| Organizations | Switch to another organization without logging out. |
| Language switcher | A menu in the header bar that changes the language of your own session only. |
| New plugin | Create the skeleton of a new plugin (also `admidio devtools:make-plugin`). |

## Snapshots and restore

A snapshot contains

* the Admidio directory, without `plugins/devtools/` (so the restore page can never be overwritten),
  without `.git` directories, and without `adm_my_files/` – except `config.php` and `cli-config.php`;
* optionally `adm_my_files/` as well (*Include adm_my_files*), still without the snapshots, the data of
  this plugin, `temp/` and the compiled templates;
* every table with the table prefix of the installation.

Files are stored once by their SHA-256, so a second snapshot only costs the changed files and the
database dump. MySQL/MariaDB are dumped with PDO; PostgreSQL needs `pg_dump` and `psql`.

The snapshots are kept in `adm_my_files/snapshots/`. Another directory – ideally outside the web
root, writable by the web server – can be set in `adm_my_files/config.php`:

```php
$gDevToolsSnapshotPath = '/var/lib/admidio-snapshots';
```

**Restoring** happens on `plugins/devtools/modules/restore.php`. The page does not use Admidio at
all – only `config.php`, PDO and the snapshot classes of this plugin – so it works when the
installation is broken, and restoring another snapshot is always possible. It asks for the
*restore key*, which the snapshot page shows (and which is stored in `restore-key.php` in the
snapshot directory); bookmark the page. A restore replaces the tables, writes the files of the
snapshot, deletes files the snapshot does not have (within its scope), clears the compiled
templates and OPcache. The current logins (sessions and "remember me") are kept by default, so you
stay logged in – as long as your user exists in the snapshot; untick *Keep the current logins* (or
pass `--log-out`) to get the logins of the snapshot instead, which logs everybody out.

On the command line:

```
php plugins/devtools/modules/restore.php [--host=HOST] --list
php plugins/devtools/modules/restore.php [--host=HOST] --snapshot=NAME [--log-out]
php admidio devtools:snapshot-create --label=baseline [--include-data]
php admidio devtools:snapshot-list
php admidio devtools:snapshot-delete NAME --yes
```

A typical pull-request test: take a snapshot *baseline*, update to PR A with the core updater,
test, restore *baseline*, update to PR B, test, …

## Settings

*Administration → Preferences → System → DevTools*: mail catcher mode and redirect address, hook
trace, exception catcher, language switcher, path of the PHP command-line binary (empty: found
automatically).

## Limits

* The hook inspector reads Admidio's hook registry by reflection; the hook trace sees only hooks
  that have a callback and callbacks registered before `plugins_loaded`.
* The mail catcher works on the `email_recipients` filter, which every mail of Admidio passes.
* A snapshot cannot be restored into another database system or another table prefix.

## Development

```
vendor/bin/phpunit plugins/devtools/tests
```

The integration test of the snapshots needs the MariaDB test database of the Admidio test suite
(`.env.test`).

## License

GNU General Public License v2.0 only, see `LICENSE`.
