<?php
/**
 ***********************************************************************************************
 * DevTools - entry file.
 *
 * Tools for developers, reviewers and translators of Admidio and its plugins: snapshots of files and
 * database, hook inspector and trace, log and exception viewer, CLI task runner, mail catcher,
 * preferences diff, organization and language switch, plugin scaffolder.
 *
 * The plugin only does something on a development installation, i.e. with $gDebug = true in
 * adm_my_files/config.php.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use AdmidioPlugin\DevTools\DevTools;

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit('This page may not be called directly!');
}

DevTools::register();
