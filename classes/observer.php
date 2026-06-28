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

namespace filter_embedpoll;

/**
 * Event observers for filter_embedpoll.
 *
 * Removes orphaned poll and vote rows when the embedding activity or course is
 * deleted. Moodle's database foreign keys are advisory only, so cleaning up this
 * data is the plugin's responsibility.
 *
 * @package    filter_embedpoll
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Delete polls (and their votes) embedded in a deleted activity.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;
        $pollids = $DB->get_fieldset_select(
            'filter_embedpoll_poll',
            'id',
            'contextid = :contextid',
            ['contextid' => $event->contextid]
        );
        self::delete_polls($pollids);
    }

    /**
     * Delete every poll (and its votes) belonging to a deleted course.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $pollids = $DB->get_fieldset_select(
            'filter_embedpoll_poll',
            'id',
            'courseid = :courseid',
            ['courseid' => $event->objectid]
        );
        self::delete_polls($pollids);
    }

    /**
     * Delete the given polls together with any votes cast in them.
     *
     * @param int[] $pollids
     */
    private static function delete_polls(array $pollids): void {
        global $DB;
        if (!$pollids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($pollids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('filter_embedpoll_vote', "pollid $insql", $params);
        $DB->delete_records_list('filter_embedpoll_poll', 'id', $pollids);
    }
}
