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

use local_embedpoll\poll;
use local_embedpoll\token;

/**
 * Tests for the embedded poll filter.
 *
 * @package    filter_embedpoll
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_embedpoll\text_filter
 */
final class text_filter_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        poll::reset_cache();
    }

    /**
     * The generator.
     *
     * @return \local_embedpoll_generator
     */
    private function generator(): \local_embedpoll_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_embedpoll');
    }

    /**
     * A book holding one poll.
     *
     * @return array [course, module context, row]
     */
    private function make_book(): array {
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $row = $this->generator()->create_poll(['cmid' => $book->cmid, 'options' => ['Uniquered', 'Uniquegreen']]);

        return [$course, \context_module::instance($book->cmid), $row];
    }

    /**
     * Run the filter.
     *
     * @param \context $context The text's context.
     * @param string $text The text.
     * @return string
     */
    private function filter(\context $context, string $text): string {
        return (new text_filter($context, []))->filter($text);
    }

    /**
     * Installing the filter switches it on.
     */
    public function test_installed_and_on(): void {
        $this->assertNotEmpty(get_config('filter_embedpoll', 'version'));
        $this->assertSame(TEXTFILTER_ON, (int)filter_get_global_states()['embedpoll']->active);
    }

    /**
     * A student sees the poll where the token was, with results hidden until they vote.
     */
    public function test_student_sees_the_poll(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->filter($context, '<p>Before</p>' . token::html($row->embedkey) . '<p>After</p>');

        $this->assertMatchesRegularExpression('~Before.*Uniquered.*Uniquegreen.*After~s', $html);
        $this->assertStringContainsString('data-pollid="' . $row->id . '"', $html);
        $this->assertStringNotContainsString('progress-bar', $html);
        $this->assertStringNotContainsString(token::ATTRIBUTE, $html);
    }

    /**
     * A teacher sees the results without voting.
     */
    public function test_teacher_sees_results(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $html = $this->filter($context, token::html($row->embedkey));

        $this->assertStringContainsString('progress-bar', $html);
        $this->assertStringContainsString('No votes yet', $html);
    }

    /**
     * A token copied in from another activity: a notice for those who may manage polls, who can
     * fix it, and nothing for anyone else.
     */
    public function test_unknown_key(): void {
        $this->resetAfterTest();
        [$course, $context] = $this->make_book();
        $text = '<p>Text</p>' . token::html('0123456789abcdef');

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $this->assertStringContainsString('embedpoll-notfound', $this->filter($context, $text));

        foreach (['teacher', 'student'] as $role) {
            $this->setUser($this->getDataGenerator()->create_and_enrol($course, $role));
            $this->assertSame('<p>Text</p>', $this->filter($context, $text));
        }
    }

    /**
     * Keys are scoped to their activity, so a real key used in another activity does not resolve.
     */
    public function test_keys_are_scoped_to_their_activity(): void {
        $this->resetAfterTest();
        [$course, , $row] = $this->make_book();
        $other = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->filter(\context_module::instance($other->cmid), token::html($row->embedkey) . '<p>x</p>');

        $this->assertSame('<p>x</p>', $html);
    }

    /**
     * Where no poll can belong a token is simply removed, even for an administrator.
     */
    public function test_other_contexts_are_stripped(): void {
        $this->resetAfterTest();
        [$course, , $row] = $this->make_book();
        $this->setAdminUser();
        $text = '<p>Summary</p>' . token::html($row->embedkey);

        $this->assertSame('<p>Summary</p>', $this->filter(\context_coursecat::instance($course->category), $text));
        $this->assertSame('<p>Summary</p>', $this->filter(\context_course::instance(SITEID), $text));
        $this->assertSame('<p>Summary</p>', $this->filter(\context_system::instance(), $text));
    }

    /**
     * A section's poll shows in its course's context.
     */
    public function test_section_summary(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $row = $this->generator()->create_poll([
            'sectionid' => get_fast_modinfo($course)->get_section_info(1)->id,
            'options' => ['Uniquered', 'Uniquegreen'],
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->filter(\context_course::instance($course->id), '<p>Week one.</p>' . token::html($row->embedkey));

        $this->assertStringContainsString('Uniquered', $html);
        $this->assertStringContainsString('data-pollid="' . $row->id . '"', $html);
    }

    /**
     * An activity's key does not resolve in a section summary, nor a section's in an activity.
     */
    public function test_activity_and_section_keys_do_not_cross(): void {
        $this->resetAfterTest();
        [$course, $modulecontext, $activity] = $this->make_book();
        $section = $this->generator()->create_poll([
            'sectionid' => get_fast_modinfo($course)->get_section_info(1)->id,
            'options' => ['Sectionyes', 'Sectionno'],
        ]);
        $this->setAdminUser();

        $html = $this->filter(\context_course::instance($course->id), token::html($activity->embedkey));
        $this->assertStringContainsString('embedpoll-notfound', $html);
        $this->assertStringNotContainsString('Uniquered', $html);

        $html = $this->filter($modulecontext, token::html($section->embedkey));
        $this->assertStringContainsString('embedpoll-notfound', $html);
        $this->assertStringNotContainsString('Sectionyes', $html);
    }

    /**
     * Two polls in one text are two polls, even with the same options.
     */
    public function test_two_polls_in_one_text(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $first = $this->generator()->create_poll(['cmid' => $page->cmid, 'options' => ['Yes', 'No']]);
        $second = $this->generator()->create_poll(['cmid' => $page->cmid, 'options' => ['Yes', 'No']]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = $this->filter(
            \context_module::instance($page->cmid),
            token::html($first->embedkey) . '<p>Between</p>' . token::html($second->embedkey)
        );

        $this->assertMatchesRegularExpression(
            '~data-pollid="' . $first->id . '".*Between.*data-pollid="' . $second->id . '"~s',
            $html
        );
    }

    /**
     * Filtering already-filtered text changes nothing, as mod_lesson's double pass needs.
     */
    public function test_filtering_twice_is_a_no_op(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $once = $this->filter($context, token::html($row->embedkey));

        $this->assertSame($once, $this->filter($context, $once));
        $this->assertSame(1, substr_count($once, 'data-pollid='));
    }

    /**
     * Through format_text(), with the filter on as installed and noclean, as book chapters, pages,
     * lesson pages, activity descriptions and section summaries are all formatted.
     */
    public function test_end_to_end_through_format_text(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = format_text(token::html($row->embedkey), FORMAT_HTML, ['context' => $context, 'noclean' => true]);

        $this->assertStringContainsString('Uniquered', $html);
    }

    /**
     * Text that is cleaned before filtering loses the token - HTMLPurifier strips data-*
     * attributes - and shows no poll.
     */
    public function test_cleaned_text_shows_no_poll(): void {
        $this->resetAfterTest();
        [$course, $context, $row] = $this->make_book();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = format_text(token::html($row->embedkey), FORMAT_HTML, ['context' => $context]);

        $this->assertStringNotContainsString('Uniquered', $html);
        $this->assertStringNotContainsString($row->embedkey, $html);
    }
}
