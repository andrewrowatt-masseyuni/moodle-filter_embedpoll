<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps for the embedded poll filter.
 *
 * @package    filter_embedpoll
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the filter.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool
 */
function xmldb_filter_embedpoll_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026101700) {
        // Polls now live in local_embedpoll, embedded by token. The {poll:...} syntax these tables
        // served is no longer recognised, so its polls and votes have nothing left to show them.
        foreach (['filter_embedpoll_vote', 'filter_embedpoll_poll'] as $name) {
            $table = new xmldb_table($name);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }

        upgrade_plugin_savepoint(true, 2026101700, 'filter', 'embedpoll');
    }

    return true;
}
