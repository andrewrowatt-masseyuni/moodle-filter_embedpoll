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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use core_external\external_api;
use filter_embedpoll\poll;

/**
 * Tests for the Embed poll vote external function.
 *
 * @package    filter_embedpoll
 * @category   test
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_embedpoll\external\vote
 */
final class vote_test extends \externallib_advanced_testcase {
    public function test_vote_records_and_reveals_results(): void {
        $this->resetAfterTest();
        [, , $student, $poll] = $this->setup_poll();
        $this->setUser($student);

        $result = $this->call_vote((int) $poll->id, 1);

        $this->assertSame((int) $poll->id, $result['pollid']);
        $this->assertTrue($result['hasvoted']);
        $this->assertTrue($result['showresults']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('1 vote', $result['totaltext']);
        $this->assertTrue($result['items'][1]['selected']);
        $this->assertSame(100, $result['items'][1]['percent']);
    }

    public function test_revote_replaces_previous_choice(): void {
        global $DB;
        $this->resetAfterTest();
        [, , $student, $poll] = $this->setup_poll();
        $this->setUser($student);

        $this->call_vote((int) $poll->id, 0);
        $result = $this->call_vote((int) $poll->id, 2);

        $this->assertSame(1, $DB->count_records('filter_embedpoll_vote', ['pollid' => $poll->id]));
        $this->assertSame(1, $result['total']);
        $this->assertTrue($result['items'][2]['selected']);
        $this->assertFalse($result['items'][0]['selected']);
    }

    public function test_out_of_range_choice_is_rejected(): void {
        $this->resetAfterTest();
        [, , $student, $poll] = $this->setup_poll(['Red', 'Green']);
        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        $this->call_vote((int) $poll->id, 5);
    }

    public function test_vote_requires_capability(): void {
        global $DB;
        $this->resetAfterTest();
        [, $context, $student, $poll] = $this->setup_poll();
        $this->setUser($student);

        // Take the vote capability away from the student in this context.
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        assign_capability(poll::CAP_VOTE, CAP_PROHIBIT, $studentrole->id, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(\core\exception\required_capability_exception::class);
        $this->call_vote((int) $poll->id, 0);
    }

    public function test_missing_poll_is_rejected(): void {
        $this->resetAfterTest();
        [, , $student] = $this->setup_poll();
        $this->setUser($student);

        $this->expectException(\dml_missing_record_exception::class);
        $this->call_vote(-1, 0);
    }

    /**
     * Invoke the external function and validate the return value against its definition.
     *
     * @param int $pollid
     * @param int $choice
     * @return array The cleaned return value.
     */
    private function call_vote(int $pollid, int $choice): array {
        $result = vote::execute($pollid, $choice);
        return external_api::clean_returnvalue(vote::execute_returns(), $result);
    }

    /**
     * Create a course module context with an enrolled student and a poll.
     *
     * @param string[] $items Poll item labels.
     * @return array{0: \stdClass, 1: \context_module, 2: \stdClass, 3: \stdClass}
     */
    private function setup_poll(array $items = ['Red', 'Green', 'Blue']): array {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $poll = poll::get_or_create($context->id, 0, $items, 0, $course->id);
        return [$course, $context, $student, $poll];
    }
}
