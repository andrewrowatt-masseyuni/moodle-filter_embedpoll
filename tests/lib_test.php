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
 * Tests for the Embed poll data/presentation helper.
 *
 * @package    filter_embedpoll
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_embedpoll\poll
 */
final class lib_test extends \advanced_testcase {
    public function test_plugin_installed(): void {
        $this->assertNotEmpty(get_config('filter_embedpoll', 'version'));
    }

    public function test_hash_depends_on_items_and_order(): void {
        $this->assertSame(poll::hash_items(['A', 'B']), poll::hash_items(['A', 'B']));
        $this->assertNotSame(poll::hash_items(['A', 'B']), poll::hash_items(['B', 'A']));
        $this->assertNotSame(poll::hash_items(['A', 'B']), poll::hash_items(['A', 'C']));
    }

    public function test_get_or_create_keys_on_context_chapter_hash_ordinal(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $items = ['Red', 'Green', 'Blue'];

        $first = poll::get_or_create($context->id, 0, $items, 0, $course->id);
        $again = poll::get_or_create($context->id, 0, $items, 0, $course->id);
        $this->assertEquals($first->id, $again->id, 'Same key must return the same poll.');

        // A duplicate poll (identical items) at ordinal 1 is a distinct record.
        $duplicate = poll::get_or_create($context->id, 0, $items, 1, $course->id);
        $this->assertNotEquals($first->id, $duplicate->id);

        // A different chapter is a distinct poll even with identical items/ordinal.
        $otherchapter = poll::get_or_create($context->id, 99, $items, 0, $course->id);
        $this->assertNotEquals($first->id, $otherchapter->id);
        $this->assertSame(99, (int) $otherchapter->chapterid);
    }

    public function test_results_hidden_until_student_votes(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $context, $student] = $this->module_with_student();
        $items = ['Red', 'Green', 'Blue'];
        $poll = poll::get_or_create($context->id, 0, $items, 0, $course->id);

        $before = poll::export_for_template($poll, $items, $context, (int) $student->id);
        $this->assertTrue($before['canvote']);
        $this->assertFalse($before['canviewresults']);
        $this->assertFalse($before['showresults']);
        $this->assertSame(0, $before['total']);

        poll::record_vote($poll, (int) $student->id, 1);

        $after = poll::export_for_template($poll, $items, $context, (int) $student->id);
        $this->assertTrue($after['showresults']);
        $this->assertSame(1, $after['total']);
        $this->assertTrue($after['items'][1]['selected']);
        $this->assertSame(100, $after['items'][1]['percent']);
        $this->assertSame(1, $DB->count_records('filter_embedpoll_vote', ['pollid' => $poll->id]));
    }

    public function test_changing_vote_replaces_the_previous_one(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $context, $student] = $this->module_with_student();
        $items = ['Red', 'Green', 'Blue'];
        $poll = poll::get_or_create($context->id, 0, $items, 0, $course->id);

        poll::record_vote($poll, (int) $student->id, 0);
        poll::record_vote($poll, (int) $student->id, 2);

        $this->assertSame(1, $DB->count_records(
            'filter_embedpoll_vote',
            ['pollid' => $poll->id, 'userid' => $student->id]
        ));
        $data = poll::export_for_template($poll, $items, $context, (int) $student->id);
        $this->assertTrue($data['items'][2]['selected']);
        $this->assertFalse($data['items'][0]['selected']);
        $this->assertSame(1, $data['total']);
    }

    public function test_teacher_always_sees_results_without_voting(): void {
        $this->resetAfterTest();
        [$course, $context, $student] = $this->module_with_student();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $items = ['Red', 'Green', 'Blue'];
        $poll = poll::get_or_create($context->id, 0, $items, 0, $course->id);
        poll::record_vote($poll, (int) $student->id, 0);

        $data = poll::export_for_template($poll, $items, $context, (int) $teacher->id);
        $this->assertTrue($data['canviewresults']);
        $this->assertFalse($data['canvote']);
        $this->assertTrue($data['showresults']);
        $this->assertSame(1, $data['total']);
        $this->assertSame(100, $data['items'][0]['percent']);
    }

    public function test_invalid_choice_is_rejected(): void {
        $this->resetAfterTest();
        [$course, $context, $student] = $this->module_with_student();
        $items = ['Red', 'Green'];
        $poll = poll::get_or_create($context->id, 0, $items, 0, $course->id);

        $this->expectException(\moodle_exception::class);
        poll::record_vote($poll, (int) $student->id, 5);
    }

    /**
     * Create a course with a module context and an enrolled student.
     *
     * @return array{0: \stdClass, 1: \context_module, 2: \stdClass}
     */
    private function module_with_student(): array {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        return [$course, $context, $student];
    }
}
