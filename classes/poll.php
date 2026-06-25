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
 * Data and presentation helper for embedded polls.
 *
 * This is the single source of truth shared by the filter (initial server-side
 * render) and the vote web service (re-render after a vote), so results are
 * resolved identically in both places.
 *
 * @package    filter_embedpoll
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class poll {

    /** @var string Capability required to cast or change a vote. */
    public const CAP_VOTE = 'filter/embedpoll:vote';

    /** @var string Capability granting permanent visibility of results. */
    public const CAP_VIEWRESULTS = 'filter/embedpoll:viewresults';

    /**
     * Compute the stable hash that identifies a set of poll items.
     *
     * Item order is significant: a different order is a different poll.
     *
     * @param string[] $items Trimmed item labels.
     * @return string 40-character sha1 hash.
     */
    public static function hash_items(array $items): string {
        return sha1(json_encode(array_values($items)));
    }

    /**
     * Fetch the poll for the given key, creating ("initiating") it on first view.
     *
     * @param int $contextid Context where the poll is embedded.
     * @param int $chapterid Book chapter id, or 0 outside a book.
     * @param string[] $items Trimmed item labels in document order.
     * @param int $ordinal Occurrence index among same-hash polls in this context/chapter.
     * @param int $courseid Enclosing course id, or 0.
     * @return \stdClass The poll record.
     */
    public static function get_or_create(int $contextid, int $chapterid, array $items,
            int $ordinal, int $courseid): \stdClass {
        global $DB;

        $hash = self::hash_items($items);
        $key = [
            'contextid' => $contextid,
            'chapterid' => $chapterid,
            'itemshash' => $hash,
            'ordinal' => $ordinal,
        ];

        if ($existing = $DB->get_record('filter_embedpoll_poll', $key)) {
            return $existing;
        }

        $now = time();
        $record = (object) ($key + [
            'items' => json_encode(array_values($items)),
            'courseid' => $courseid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        try {
            $record->id = $DB->insert_record('filter_embedpoll_poll', $record);
        } catch (\dml_write_exception $e) {
            // Another request created the same poll first; reuse it.
            $existing = $DB->get_record('filter_embedpoll_poll', $key);
            if ($existing) {
                return $existing;
            }
            throw $e;
        }

        return $record;
    }

    /**
     * Decode the stored canonical items for a poll.
     *
     * @param \stdClass $poll
     * @return string[]
     */
    public static function get_items(\stdClass $poll): array {
        $items = json_decode($poll->items);
        return is_array($items) ? array_map('strval', $items) : [];
    }

    /**
     * Record (or change) the current user's vote.
     *
     * @param \stdClass $poll
     * @param int $userid
     * @param int $choice 0-based index of the chosen item.
     * @return void
     */
    public static function record_vote(\stdClass $poll, int $userid, int $choice): void {
        global $DB;

        $items = self::get_items($poll);
        if ($choice < 0 || $choice >= count($items)) {
            throw new \moodle_exception('error_invalidchoice', 'filter_embedpoll');
        }

        $now = time();
        $existing = $DB->get_record('filter_embedpoll_vote', [
            'pollid' => $poll->id,
            'userid' => $userid,
        ]);

        if ($existing) {
            if ((int) $existing->choice !== $choice) {
                $existing->choice = $choice;
                $existing->timemodified = $now;
                $DB->update_record('filter_embedpoll_vote', $existing);
            }
            return;
        }

        $DB->insert_record('filter_embedpoll_vote', (object) [
            'pollid' => $poll->id,
            'userid' => $userid,
            'choice' => $choice,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Build the mustache context for rendering a poll for one user.
     *
     * Results (counts/percentages) are only ever populated when they are allowed
     * to be revealed to this user, so a student who has not voted never receives
     * the tallies over the wire.
     *
     * @param \stdClass $poll
     * @param string[] $items Item labels to display (from the live embed code).
     * @param \context $context
     * @param int $userid
     * @return array Template context.
     */
    public static function export_for_template(\stdClass $poll, array $items,
            \context $context, int $userid): array {
        global $DB;

        $canvote = has_capability(self::CAP_VOTE, $context, $userid);
        $canviewresults = has_capability(self::CAP_VIEWRESULTS, $context, $userid);

        $voterecord = $DB->get_record('filter_embedpoll_vote', [
            'pollid' => $poll->id,
            'userid' => $userid,
        ]);
        $hasvoted = (bool) $voterecord;
        $myvote = $hasvoted ? (int) $voterecord->choice : -1;

        $showresults = $hasvoted || $canviewresults;

        // Aggregate the tally, ignoring any out-of-range choices defensively.
        $counts = array_fill(0, count($items), 0);
        $total = 0;
        if ($showresults) {
            $rows = $DB->get_records_sql(
                "SELECT choice, COUNT(1) AS num
                   FROM {filter_embedpoll_vote}
                  WHERE pollid = :pollid
               GROUP BY choice",
                ['pollid' => $poll->id]
            );
            foreach ($rows as $row) {
                $choice = (int) $row->choice;
                if ($choice >= 0 && $choice < count($items)) {
                    $counts[$choice] = (int) $row->num;
                    $total += (int) $row->num;
                }
            }
        }

        $itemsout = [];
        foreach ($items as $index => $label) {
            $count = $counts[$index];
            $percent = $total > 0 ? (int) round(($count / $total) * 100) : 0;
            $itemsout[] = [
                'index' => $index,
                'label' => $label,
                'count' => $count,
                'percent' => $percent,
                'selected' => $index === $myvote,
            ];
        }

        return [
            'pollid' => (int) $poll->id,
            'contextid' => (int) $poll->contextid,
            'canvote' => $canvote,
            'canviewresults' => $canviewresults,
            'hasvoted' => $hasvoted,
            'showresults' => $showresults,
            'total' => $total,
            'hasvotes' => $total > 0,
            'items' => $itemsout,
        ];
    }
}
