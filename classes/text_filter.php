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
 * Embed poll filter.
 *
 * Replaces embed codes such as {poll:"Item 1","Item 2","Item 3"} with an
 * interactive poll widget, rendered server-side for the current user.
 *
 * Documentation: {@link https://moodledev.io/docs/apis/plugintypes/filter}
 *
 * @package    filter_embedpoll
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {
    /** @var string Matches a single poll embed code, capturing its inner item list. */
    private const PATTERN = '/\{poll:(.*?)\}/';

    /**
     * Set up the page, loading the interaction JS once per page.
     *
     * @param \moodle_page $page
     * @param \context $context
     */
    public function setup($page, $context) {
        if ($page->requires->should_create_one_time_item_now('filter_embedpoll_js')) {
            $page->requires->js_call_amd('filter_embedpoll/poll', 'init');
        }
    }

    /**
     * Filter text, replacing poll embed codes with rendered widgets.
     *
     * @param string $text some HTML content to process.
     * @param array $options options passed to the filters
     * @return string the HTML content after the filtering has been applied.
     */
    public function filter($text, array $options = []) {
        global $OUTPUT, $USER;

        if (!is_string($text) || strpos($text, '{poll:') === false) {
            return $text;
        }

        $contextid = $this->context->id;
        $chapterid = $this->resolve_chapterid();
        $courseid = 0;
        if ($coursecontext = $this->context->get_course_context(false)) {
            $courseid = (int) $coursecontext->instanceid;
        }

        // Tracks how many times each item-set has been seen in this text, so
        // duplicate polls (identical items) get a stable disambiguating ordinal.
        $seen = [];

        return preg_replace_callback(self::PATTERN, function ($matches) use (
            $contextid,
            $chapterid,
            $courseid,
            &$seen,
            $OUTPUT,
            $USER
        ) {
            $items = $this->parse_items($matches[1]);
            if (count($items) < 2) {
                // Not a valid poll; leave the original text untouched.
                return $matches[0];
            }

            $hash = poll::hash_items($items);
            $ordinal = $seen[$hash] ?? 0;
            $seen[$hash] = $ordinal + 1;

            $poll = poll::get_or_create($contextid, $chapterid, $items, $ordinal, $courseid);
            $data = poll::export_for_template($poll, $items, $this->context, (int) $USER->id);

            return $OUTPUT->render_from_template('filter_embedpoll/poll', $data);
        }, $text);
    }

    /**
     * Resolve the book chapter id for the current page, or 0 when not in a book.
     *
     * @return int
     */
    private function resolve_chapterid(): int {
        global $DB, $PAGE;

        if ($this->context->contextlevel != CONTEXT_MODULE) {
            return 0;
        }

        $cm = $PAGE->cm;
        if (!$cm || $cm->modname !== 'book') {
            return 0;
        }

        $chapterid = optional_param('chapterid', 0, PARAM_INT);
        if (!$chapterid) {
            // No chapter in the URL: default to the first chapter of the book.
            $firstchapter = $DB->get_records(
                'book_chapters',
                ['bookid' => $cm->instance],
                'pagenum ASC',
                'id',
                0,
                1
            );
            $firstchapter = reset($firstchapter);
            if ($firstchapter) {
                $chapterid = (int) $firstchapter->id;
            }
        }

        return (int) $chapterid;
    }

    /**
     * Parse the inner of a poll token into a list of trimmed item labels.
     *
     * Items are double-quoted and comma separated. HTML entities (e.g. the
     * editor encoding " as &quot;) are decoded first so straight quotes work
     * regardless of how the editor stored them.
     *
     * @param string $inner The captured content between {poll: and }.
     * @return string[] The non-empty item labels, in order.
     */
    private function parse_items(string $inner): array {
        $inner = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match_all('/"([^"]*)"/', $inner, $matches)) {
            return [];
        }
        $items = [];
        foreach ($matches[1] as $label) {
            $label = trim($label);
            if ($label !== '') {
                $items[] = $label;
            }
        }
        return $items;
    }
}
