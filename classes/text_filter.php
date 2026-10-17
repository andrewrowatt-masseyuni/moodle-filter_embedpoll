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

use local_embedpoll\output\widget;
use local_embedpoll\poll;
use local_embedpoll\token;

/**
 * Replaces embedded poll tokens with the poll.
 *
 * A token is an empty <div data-embedpoll="KEY"> that carries nothing of the poll; the options and
 * votes live in local_embedpoll, and tiny_embedpoll is how teachers add and edit them.
 *
 * Output depends on who is looking - whether they have voted, whether they may see results - which
 * is safe because Moodle 4.5 has no text cache: filters run per request, per user.
 *
 * Documentation: {@link https://moodledev.io/docs/apis/plugintypes/filter}
 *
 * @package    filter_embedpoll
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {
    /**
     * Set up the page, loading the voting JS once per page.
     *
     * @param \moodle_page $page
     * @param \context $context
     */
    public function setup($page, $context) {
        if (!get_config('local_embedpoll', 'version')) {
            return;
        }

        if ($page->requires->should_create_one_time_item_now('filter_embedpoll_js')) {
            $page->requires->js_call_amd('local_embedpoll/poll', 'init');
        }
    }

    /**
     * Filter some text.
     *
     * @param string $text The text.
     * @param array $options Filter options.
     * @return string
     */
    public function filter($text, array $options = []) {
        // Cheap enough for every piece of text on every page; nothing below runs without a token.
        if (!is_string($text) || stripos($text, 'data-embedpoll') === false) {
            return $text;
        }

        // The plugin that holds the polls has gone. The tokens carry nothing, so leaving them is
        // harmless, and without local_embedpoll there is nothing to render them with.
        if (!get_config('local_embedpoll', 'version') || !class_exists(token::class)) {
            return $text;
        }

        return token::replace($text, fn(string $key) => $this->replace($key));
    }

    /**
     * What one token becomes.
     *
     * @param string $key The token's key.
     * @return string HTML, or ''.
     */
    protected function replace(string $key): string {
        // Anywhere a poll cannot belong: nothing. A token there was copied in from somewhere else.
        $polls = $this->polls();
        if ($polls === null) {
            return '';
        }

        $row = $polls[$key] ?? null;
        if (!$row) {
            // Tokens are scoped to their activity, or a section's to its course, so one pasted in
            // from elsewhere does not resolve. Worth telling someone who can fix it; everyone else
            // sees nothing.
            if (!has_capability(poll::CAP_MANAGE, $this->context)) {
                return '';
            }
            return \html_writer::div(
                get_string('notfound', 'filter_embedpoll'),
                'alert alert-warning embedpoll-notfound',
                ['role' => 'note']
            );
        }

        return widget::render_row($row);
    }

    /**
     * The polls text in this context can show, keyed by key, or null where no poll can belong.
     *
     * An activity's text shows that activity's polls. Text in a course context is a section
     * summary - or the course summary - and shows any of the course's section polls: a filter is
     * told the context but not which section, so a section's key is scoped to its course.
     * local_embedpoll keeps each section's copy of a poll its own.
     *
     * @return array<string, \stdClass>|null
     */
    protected function polls(): ?array {
        if ($this->context instanceof \context_module) {
            $courseid = (int)$this->context->get_course_context()->instanceid;
            return poll::for_cm($courseid, (int)$this->context->instanceid);
        }

        if ($this->context instanceof \context_course && (int)$this->context->instanceid !== (int)SITEID) {
            return poll::for_sections((int)$this->context->instanceid);
        }

        return null;
    }
}
