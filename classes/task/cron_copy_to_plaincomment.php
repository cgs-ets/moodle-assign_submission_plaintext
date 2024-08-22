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
            $config = get_config('assignsubmission_plaintext');
    
            if ( $config->ptcourseid != 0 ) {
    
                $this->log("Looking for submissions since last run: $lastrun for Course: $config->ptcourseid and grading category:  $config->gcategory");
                $submissions = $this->get_plaincomment_from_particular_course($config, $lastrun);
    
            } else {
    
                $this->log("Looking for submissions since last run: $lastrun");
                $submissions = $this->get_plaincomment_from_all_courses($lastrun);
            }
            
            $this->process_plaintextsubmission_to_plaintextcomment($submissions);
    
            $this->log('Finishing cron_copy_to_plaincomment task');

       
        return true;
    }

    public function can_run(): bool {
        return true;
    }

   

    /**
     * Looks for the plaintext submissions from a given course and in assignments with a
     * particular grade category (set in the configurations of the plugin)
     * 
     */
    private function get_plaincomment_from_particular_course($config, $lastrun){
        global $DB;

        $sql = "SELECT u.id AS userid, sub.id AS submissionid, pt.plaintext, 
                sub.assignment, sub.timemodified, pg.*, assign.name, assign.course, gi.*
                FROM {user}  u 
                JOIN {assign_submission}  sub ON u.id = sub.userid
                JOIN {assignsubmission_plaintext} pt ON pt.submission = sub.id
                JOIN {assign_plugin_config} pg ON pg.assignment = sub.assignment
                JOIN {assign} assign ON assign.id = sub.assignment
                JOIN {grade_items} gi ON assign.id = gi.iteminstance
                JOIN {grade_categories} gcat ON gcat.courseid = assign.course
                WHERE sub.status = :status   
                AND sub.timemodified >= :timemodified
                AND pg.plugin = :plugin 
                AND pg.value = :value 
                AND gcat.fullname = :fullname 
                AND gcat.courseid = :courseid
                AND gi.categoryid = gcat.id";

        $params = ['status'=> 'submitted',
                    'timemodified' => $lastrun,
                    'plugin' => 'plaincomment',
                    'value' => 1,
                    'fullname' => $config->gcategory,
                    'courseid' => $config->ptcourseid];

        $submissions = $DB->get_records_sql($sql, $params);
        

        return $submissions;
    }

    /**
     * Looks for the plaintext submissions from all course 
     */

    private function get_plaincomment_from_all_courses($lastrun) {
        global $DB;
      
        $sql = "SELECT u.id as userid, sub.id as submissionid, 
                pt.plaintext, sub.assignment, sub.timemodified
                FROM {user}  u 
                JOIN {assign_submission}  sub ON u.id = sub.userid
                JOIN {assignsubmission_plaintext} pt ON pt.submission = sub.id
                JOIN {assign_plugin_config} pg ON pg.assignment = sub.assignment
                WHERE sub.status = :status 
                AND sub.timemodified >= :timemodified
                AND  pg.plugin = :plugin 
                AND  pg.value = :value";

        $params = ['status'=> 'submitted',
                    'timemodified' => $lastrun,
                    'plugin' => 'plaincomment',
                    'value' => 1];

        $submissions = $DB->get_records_sql($sql, $params);

        return $submissions;
    }

    /**
     *  Insert a new row in the mdl_assignfeedback_plaincomment and in the mdl_assign_grades 
     */
    private function process_plaintextsubmission_to_plaintextcomment($submissions) {

        foreach($submissions as $submission) {

            $this->log("Processing submission $submission->submissionid for user $submission->userid", 1);

            // Check if its an update or an insert
            // Insert record in mdl_assign_grades 
            $grade = $this->update_assign_grades($submission->userid, $submission->assignment);
            $feedback = $this->is_in_assignfeedback_plaincomment($grade);

            // No grade we have to insert everything
            if ($grade == -1) {
               
                $rid = $this->insert_record($submission);
                $this->insert_record_in_assignfeedback_plaincomment($rid, $submission);

            } elseif(!$feedback) { // Just insert in the plaincommment table

                $this->insert_record_in_assignfeedback_plaincomment($submission, $grade);

            } else { // Update the plaincomment

                $rid = $this->update_record_in_assignfeedback_plaincomment($submission, $grade);
            }

        }
        
    }

     /**
     * Check if there is a submission in the grader already. If yes, then update the time modified in
     * mdl_assign_grades and the text in the assignfeedback_plaincomment
     */
    private function update_assign_grades($userid, $assignmentid) {

        global $DB;

        $this->log("Updating assign_grades for user ID $userid and Assigment ID: $assignmentid");
        
        $sql = "SELECT  * FROM {assign_grades} WHERE userid = :userid and assignment = :assignment";
        $params = ['userid' => $userid, 'assignment'=> $assignmentid];
        
        $result = $DB->get_record_sql($sql, $params);
        
        if(isset($result->id)) {
            
            $r = new stdClass();
            $r->id = $result->id;
            $r->timemodified = time();
            $DB->update_record('assign_grades', $result);

            $this->log("Finish update for assign_grades for user ID $userid and Assigment ID: $assignmentid");
           
            return $result->id;
        }

        return -1;
    }

    /**
     * If the submission was created before the assignment was given the category
     * it will not exist in the assignfeedback_plaincomment table. 
     */
    private function is_in_assignfeedback_plaincomment($grade){
        global $DB;
        
        $sql = 'SELECT * FROM {assignfeedback_plaincomment} WHERE grade = :grade';
        $feedback = $DB->get_record_sql($sql, ['grade' => $grade]);

        return $feedback;
    }

    /**
     * Insert record in the assign_grades table
    */
    private function insert_record($submission) {
        global $DB;

        $dataobject = new stdClass();
        $dataobject->assignment = $submission->assignment;
        $dataobject->userid = $submission->userid;
        $dataobject->timecreated = time(); 
        $dataobject->timemodified = time(); 

        $grade = $DB->insert_record('assign_grades',  $dataobject, true);
       
        $this->log("Record inserted in assign_grades, ID: $grade  ", 1);

    }

    private function insert_record_in_assignfeedback_plaincomment($submission, $grade){
        global $DB;

        // Insert in assignfeedback_plaincomment;

        $data = new stdClass();
        $data->assignment = $submission->assignment;
        $data->grade = $grade;
        $data->plaincomment = $submission->plaintext;
        $rid = $DB->insert_record('assignfeedback_plaincomment', $data, true);

        $this->log("Record inserted in assignfeedback_plaincomment, ID: $rid  ", 1);
    }

    /**
     * Update record in assignfeedback_plaincomment
     */
    private function update_record_in_assignfeedback_plaincomment($submission, $grade) {
        global $DB;

        $this->log("Updating assignfeedback_plaincomment, submission ID: $submission->id. Grade ID $grade");

        $sql = 'SELECT * FROM {assignfeedback_plaincomment} WHERE grade = :grade';
        $feedback = $DB->get_record_sql($sql, ['grade' => $grade]);

        $feedback->plaincomment = $submission->plaintext;
        
        if ($DB->update_record('assignfeedback_plaincomment',$feedback)) {
            $this->log("Finished updating assignfeedback_plaincomment, submission ID: $submission->id. Grade ID $grade");
        }

        return $feedback->id;
    }
}