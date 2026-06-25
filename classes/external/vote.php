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

namespace filter_embedpoll\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use filter_embedpoll\poll;

/**
 * External function to cast or change a vote in an embedded poll.
 *
 * @package    filter_embedpoll
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vote extends external_api {
    /**
     * Parameters definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pollid' => new external_value(PARAM_INT, 'Poll id'),
            'choice' => new external_value(PARAM_INT, '0-based index of the chosen item'),
        ]);
    }

    /**
     * Record the vote and return the now-revealed results for the current user.
     *
     * @param int $pollid
     * @param int $choice
     * @return array
     */
    public static function execute(int $pollid, int $choice): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'pollid' => $pollid,
            'choice' => $choice,
        ]);

        $poll = $DB->get_record('filter_embedpoll_poll', ['id' => $params['pollid']], '*', MUST_EXIST);

        $context = \context::instance_by_id($poll->contextid);
        self::validate_context($context);
        require_capability(poll::CAP_VOTE, $context);

        poll::record_vote($poll, (int) $USER->id, $params['choice']);

        return poll::export_for_template($poll, poll::get_items($poll), $context, (int) $USER->id);
    }

    /**
     * Return value definition. Mirrors filter_embedpoll\poll::export_for_template().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pollid' => new external_value(PARAM_INT, 'Poll id'),
            'contextid' => new external_value(PARAM_INT, 'Context id'),
            'canvote' => new external_value(PARAM_BOOL, 'Whether the user may vote'),
            'canviewresults' => new external_value(PARAM_BOOL, 'Whether the user always sees results'),
            'hasvoted' => new external_value(PARAM_BOOL, 'Whether the user has voted'),
            'showresults' => new external_value(PARAM_BOOL, 'Whether results are revealed to the user'),
            'total' => new external_value(PARAM_INT, 'Total number of votes'),
            'totaltext' => new external_value(PARAM_TEXT, 'Localised, pluralised total-votes label'),
            'hasvotes' => new external_value(PARAM_BOOL, 'Whether there is at least one vote'),
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'index' => new external_value(PARAM_INT, '0-based item index'),
                    'label' => new external_value(PARAM_RAW, 'Item label'),
                    'count' => new external_value(PARAM_INT, 'Votes for this item'),
                    'percent' => new external_value(PARAM_INT, 'Percentage of votes for this item'),
                    'selected' => new external_value(PARAM_BOOL, 'Whether this is the user\'s choice'),
                ])
            ),
        ]);
    }
}
