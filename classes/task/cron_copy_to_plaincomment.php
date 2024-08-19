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
 * Copy the plain text submission to the plaincomment feedback
 *
 * @package   assignsubmission_plaintext\task
 * @copyright 2024 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_plaintext\task;

use stdClass;

defined('MOODLE_INTERNAL') || die();

class cron_copy_to_plaincomment extends \core\task\scheduled_task {


    use \core\task\logging_trait;
      /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_copy_to_plaincomment', 'assignsubmission_plaintext');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;
        $this->log('Starting cron_copy_to_plaincomment task');

        // Get last run.
        $lastrun = $DB->get_field('config', 'value', ['name' => 'assignsubmission_plaintext_copytoplaincomment_lastrun']);

        if ($lastrun === false) {
            // First run ever.
            $DB->insert_record('config', ['name' => 'assignsubmission_plaintext_copytoplaincomment_lastrun', 'value' => time()]);
            $lastrun = time();
        }

        // Immediately update last run time.
        $DB->execute("UPDATE {config} SET value = ? WHERE name = 'assignsubmission_plaintext_copytoplaincomment_lastrun'", [time()]);

        // Get submissions with submission type plaintext
        // that have been updated since the last time the job ran.
        // and that the feedback type is plaincomment
        $this->log("Looking for submissions since last run: $lastrun");

        $sql = "SELECT u.id as userid, sub.id as submissionid, 
                pt.plaintext, sub.assignment, sub.timemodified
                FROM {user}  u 
                JOIN {assign_submission}  sub ON u.id = sub.userid
                JOIN {assignsubmission_plaintext} pt ON pt.submission = sub.id
                JOIN mdl_assign_plugin_config pg ON pg.assignment = sub.assignment
                WHERE sub.status = 'submitted' AND sub.timemodified >= $lastrun
                AND  pg.plugin = 'plaincomment' and   pg.value = 1";
        
        $submissions = $DB->get_records_sql($sql);

        // Insert a new row in the mdl_assignfeedback_plaincomment and in the mdl_assign_grades 

        foreach($submissions as $submission) {
            $this->log("Processing submission $submission->submissionid for user $submission->userid", 1);

            // Insert record in mdl_assign_grades 
            $dataobject = new stdClass();
            $dataobject->assignment = $submission->assignment;
            $dataobject->userid = $submission->userid;
            $dataobject->timecreated = time(); 
            $dataobject->timemodified = time(); 

            $grade = $DB->insert_record('assign_grades',  $dataobject, true);
           
            $this->log("Record inserted in assign_grades, ID: $grade  ", 1);

            // Insert in assignfeedback_plaincomment;

            $data = new stdClass();
            $data->assignment = $submission->assignment;
            $data->grade = $grade;
            $data->plaincomment = $submission->plaintext;
            $rid = $DB->insert_record('assignfeedback_plaincomment', $data, true);

            $this->log("Record inserted in assignfeedback_plaincomment, ID: $rid  ", 1);

        }
        $this->log('Finishing cron_copy_to_plaincomment task');
        return true;
    }

    public function can_run(): bool {
        return true;
    }
}