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
 * This file contains the definition for the library class for plaintext submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package assignsubmission_plaintext
 * @copyright 2024 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();
// File area for online text submission assignment.
define('ASSIGNSUBMISSION_PLAINTEXT_FILEAREA', 'submissions_plaintext');

/**
 * library class for plaintext submission plugin extending submission plugin base class
 *
 * @package assignsubmission_plaintext
 * @copyright 2024 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_plaintext extends assign_submission_plugin {

    /**
     * Get the name of the online text submission plugin
     * @return string
     */
    public function get_name() {
        return get_string('plaintext', 'assignsubmission_plaintext');
    }


    /**
     * Get plaintext submission information from the database
     *
     * @param  int $submissionid
     * @return mixed
     */
    private function get_plaintext_submission($submissionid) {
        global $DB;

        return $DB->get_record('assignsubmission_plaintext', array('submission'=>$submissionid));
    }

    /**
     * Remove a submission.
     *
     * @param stdClass $submission The submission
     * @return boolean
     */
    public function remove(stdClass $submission) {
        global $DB;

        $submissionid = $submission ? $submission->id : 0;
        if ($submissionid) {
            $DB->delete_records('assignsubmission_plaintext', array('submission' => $submissionid));
        }
        return true;
    }

    /**
     * Get the settings for plaintext submission plugin
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $COURSE;

        $defaultwordlimit = $this->get_config('wordlimit') == 0 ? '' : $this->get_config('wordlimit');
        $defaultwordlimitenabled = $this->get_config('wordlimitenabled');

        $options = array('size' => '6', 'maxlength' => '6');
        $name = get_string('wordlimit', 'assignsubmission_plaintext');

        // Create a text box that can be enabled/disabled for plaintext word limit.
        $wordlimitgrp = array();
        $wordlimitgrp[] = $mform->createElement('text', 'assignsubmission_plaintext_wordlimit', '', $options);
        $wordlimitgrp[] = $mform->createElement('checkbox', 'assignsubmission_plaintext_wordlimit_enabled',
                '', get_string('enable'));
        $mform->addGroup($wordlimitgrp, 'assignsubmission_plaintext_wordlimit_group', $name, ' ', false);
        $mform->addHelpButton('assignsubmission_plaintext_wordlimit_group',
                              'wordlimit',
                              'assignsubmission_plaintext');
        $mform->disabledIf('assignsubmission_plaintext_wordlimit',
                           'assignsubmission_plaintext_wordlimit_enabled',
                           'notchecked');
        $mform->hideIf('assignsubmission_plaintext_wordlimit',
                       'assignsubmission_plaintext_enabled',
                       'notchecked');

        // Add numeric rule to text field.
        $wordlimitgrprules = array();
        $wordlimitgrprules['assignsubmission_plaintext_wordlimit'][] = array(null, 'numeric', null, 'client');
        $mform->addGroupRule('assignsubmission_plaintext_wordlimit_group', $wordlimitgrprules);

        // Rest of group setup.
        $mform->setDefault('assignsubmission_plaintext_wordlimit', $defaultwordlimit);
        $mform->setDefault('assignsubmission_plaintext_wordlimit_enabled', $defaultwordlimitenabled);
        $mform->setType('assignsubmission_plaintext_wordlimit', PARAM_INT);
        $mform->hideIf('assignsubmission_plaintext_wordlimit_group',
                       'assignsubmission_plaintext_enabled',
                       'notchecked');
    }

    /**
     * Save the settings for plaintext submission plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        if (empty($data->assignsubmission_plaintext_wordlimit) || empty($data->assignsubmission_plaintext_wordlimit_enabled)) {
            $wordlimit = 0;
            $wordlimitenabled = 0;
        } else {
            $wordlimit = $data->assignsubmission_plaintext_wordlimit;
            $wordlimitenabled = 1;
        }

        $this->set_config('wordlimit', $wordlimit);
        $this->set_config('wordlimitenabled', $wordlimitenabled);

        return true;
    }

    /**
     * Add form elements for settings
     *
     * @param mixed $submission can be null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return true if elements were added to the form
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        $elements = array();

        $submissionid = $submission ? $submission->id : 0;

        if (!isset($data->plaintext)) {
            $data->plaintext = '';
        }

        if ($submission) {
            $plaintextsubmission = $this->get_plaintext_submission($submission->id);
            if ($plaintextsubmission) {
                $data->plaintext = $plaintextsubmission->plaintext;
            }

        }

        $mform->addElement('textarea', 'plaintext_textarea', $this->get_name(), 'wrap="virtual" rows="10" cols="50"');

        return true;
    }

    /**
     * Save data to the database and trigger plagiarism plugin,
     * if enabled, to scan the uploaded content via events trigger
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $USER, $DB;

        $plaintextsubmission = $this->get_plaintext_submission($submission->id);

        // Check word count before submitting anything.
        $exceeded = $this->check_word_count(trim($data->plaintext_textarea));
        if ($exceeded) {
            $this->set_error($exceeded);
            return false;
        }

        $params = array(
            'context' => context_module::instance($this->assignment->get_course_module()->id),
            'courseid' => $this->assignment->get_course()->id,
            'objectid' => $submission->id,
            'other' => array(
                'content' => trim($data->plaintext_textarea),
                'pathnamehashes' => array_keys([]),
            )
        );
        if (!empty($submission->userid) && ($submission->userid != $USER->id)) {
            $params['relateduserid'] = $submission->userid;
        }
        if ($this->assignment->is_blind_marking()) {
            $params['anonymous'] = 1;
        }
        $event = \assignsubmission_plaintext\event\assessable_uploaded::create($params);
        $event->trigger();

        $groupname = null;
        $groupid = 0;
        // Get the group name as other fields are not transcribed in the logs and this information is important.
        if (empty($submission->userid) && !empty($submission->groupid)) {
            $groupname = $DB->get_field('groups', 'name', array('id' => $submission->groupid), MUST_EXIST);
            $groupid = $submission->groupid;
        } else {
            $params['relateduserid'] = $submission->userid;
        }

        $count = count_words($data->plaintext_textarea);

        // Unset the objectid and other field from params for use in submission events.
        unset($params['objectid']);
        unset($params['other']);
        $params['other'] = array(
            'submissionid' => $submission->id,
            'submissionattempt' => $submission->attemptnumber,
            'submissionstatus' => $submission->status,
            'plaintextwordcount' => $count,
            'groupid' => $groupid,
            'groupname' => $groupname
        );

        if ($plaintextsubmission) {
            $plaintextsubmission->plaintext = $data->plaintext_textarea;
            $params['objectid'] = $plaintextsubmission->id;
            $updatestatus = $DB->update_record('assignsubmission_plaintext', $plaintextsubmission);
            $event = \assignsubmission_plaintext\event\submission_updated::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();
            return $updatestatus;
        } else {
            $plaintextsubmission = new stdClass();
            $plaintextsubmission->plaintext = $data->plaintext_textarea;
            $plaintextsubmission->submission = $submission->id;
            $plaintextsubmission->assignment = $this->assignment->get_instance()->id;
            $plaintextsubmission->id = $DB->insert_record('assignsubmission_plaintext', $plaintextsubmission);
            $params['objectid'] = $plaintextsubmission->id;
            $event = \assignsubmission_plaintext\event\submission_created::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();
            return $plaintextsubmission->id > 0;
        }
    }


     /**
      * Display plaintext word count in the submission status table
      *
      * @param stdClass $submission
      * @param bool $showviewlink - If the summary has been truncated set this to true
      * @return string
      */
    public function view_summary(stdClass $submission, & $showviewlink) {
        global $CFG;

        $plaintextsubmission = $this->get_plaintext_submission($submission->id);
        // Always show the view link.
        $showviewlink = true;

        if ($plaintextsubmission) {
            // The actual submission text.
            $plaintext = trim($plaintextsubmission->plaintext);
            // The shortened version of the submission text.
            $shorttext = shorten_text($plaintext, 140);

            $plagiarismlinks = '';

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir . '/plagiarismlib.php');

                $plagiarismlinks .= plagiarism_get_links(array('userid' => $submission->userid,
                    'content' => $plaintext,
                    'cmid' => $this->assignment->get_course_module()->id,
                    'course' => $this->assignment->get_course()->id,
                    'assignment' => $submission->assignment));
            }
            // We compare the actual text submission and the shortened version. If they are not equal, we show the word count.
            if ($plaintext != $shorttext) {
                $wordcount = get_string('numwords', 'assignsubmission_plaintext', count_words($plaintext));

                return $plagiarismlinks . $wordcount . $shorttext;
            } else {
                return $plagiarismlinks . $shorttext;
            }
        }
        return '';
    }

    /**
     * Produce a list of files suitable for export that represent this submission.
     *
     * @param stdClass $submission - For this is the submission data
     * @param stdClass $user - This is the user record for this submission
     * @return array - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission, stdClass $user) {
        global $DB;

        $files = array();
        $plaintextsubmission = $this->get_plaintext_submission($submission->id);

        // Note that this check is the same logic as the result from the is_empty function but we do
        // not call it directly because we already have the submission record.
        if ($plaintextsubmission) {
            // Do not pass the text through format_text. The result may not be displayed in Moodle and
            // may be passed to external services such as document conversion or portfolios.
            $formattedtext = $this->assignment->download_rewrite_pluginfile_urls($plaintextsubmission->plaintext, $user, $this);
            $head = '<head><meta charset="UTF-8"></head>';
            $submissioncontent = '<!DOCTYPE html><html>' . $head . '<body>'. $formattedtext . '</body></html>';

            $filename = get_string('plaintextfilename', 'assignsubmission_plaintext');
            $files[$filename] = array($submissioncontent);

            $fs = get_file_storage();

            $fsfiles = $fs->get_area_files($this->assignment->get_context()->id,
                                           'assignsubmission_plaintext',
                                           ASSIGNSUBMISSION_PLAINTEXT_FILEAREA,
                                           $submission->id,
                                           'timemodified',
                                           false);

            foreach ($fsfiles as $file) {
                $files[$file->get_filename()] = $file;
            }
        }

        return $files;
    }

    /**
     * Display the saved text content from the editor in the view table
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        global $CFG;
        $result = '';
        $plagiarismlinks = '';

        $plaintextsubmission = $this->get_plaintext_submission($submission->id);

        if ($plaintextsubmission) {

            // Render for portfolio API.
            $result .= $plaintextsubmission->plaintext;

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir . '/plagiarismlib.php');

                $plagiarismlinks .= plagiarism_get_links(array('userid' => $submission->userid,
                    'content' => trim($plaintextsubmission->plaintext),
                    'cmid' => $this->assignment->get_course_module()->id,
                    'course' => $this->assignment->get_course()->id,
                    'assignment' => $submission->assignment));
            }
        }

        return $plagiarismlinks . $result;
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        $DB->delete_records('assignsubmission_plaintext',
                            array('assignment'=>$this->assignment->get_instance()->id));

        return true;
    }

    /**
     * No text is set for this plugin
     *
     * @param stdClass $submission
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        $plaintextsubmission = $this->get_plaintext_submission($submission->id);
        $wordcount = 0;
        $hasinsertedresources = false;

        if (isset($plaintextsubmission->plaintext)) {
            $wordcount = count_words(trim($plaintextsubmission->plaintext));
            // Check if the online text submission contains video, audio or image elements
            // that can be ignored and stripped by count_words().
            $hasinsertedresources = preg_match('/<\s*((video|audio)[^>]*>(.*?)<\s*\/\s*(video|audio)>)|(img[^>]*>(.*?))/',
                    trim($plaintextsubmission->plaintext));
        }

        return $wordcount == 0 && !$hasinsertedresources;
    }

    /**
     * Determine if a submission is empty
     *
     * This is distinct from is_empty in that it is intended to be used to
     * determine if a submission made before saving is empty.
     *
     * @param stdClass $data The submission data
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        if (!isset($data->plaintext_textarea)) {
            return true;
        }
        $wordcount = 0;
        $hasinsertedresources = false;

        if (isset($data->plaintext_textarea)) {
            $wordcount = count_words(trim((string)$data->plaintext_textarea));
        }

        return $wordcount == 0;
    }

    /**
     * Copy the student's submission from a previous submission. Used when a student opts to base their resubmission
     * on the last submission.
     * @param stdClass $sourcesubmission
     * @param stdClass $destsubmission
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;

        // Copy the assignsubmission_plaintext record.
        $plaintextsubmission = $this->get_plaintext_submission($sourcesubmission->id);
        if ($plaintextsubmission) {
            unset($plaintextsubmission->id);
            $plaintextsubmission->submission = $destsubmission->id;
            $DB->insert_record('assignsubmission_plaintext', $plaintextsubmission);
        }
        return true;
    }

    /**
     * Return a description of external params suitable for uploading an plaintext submission from a webservice.
     *
     * @return \core_external\external_description|null
     */
    public function get_external_parameters() {
        return array(
            'plaintext_textarea' => new external_value(PARAM_RAW, 'The text for this submission.')
        );
    }

    /**
     * Compare word count of plaintext submission to word limit, and return result.
     *
     * @param string $submissiontext PLAINTEXT submission text from editor
     * @return string Error message if limit is enabled and exceeded, otherwise null
     */
    public function check_word_count($submissiontext) {
        global $OUTPUT;

        $wordlimitenabled = $this->get_config('wordlimitenabled');
        $wordlimit = $this->get_config('wordlimit');

        if ($wordlimitenabled == 0) {
            return null;
        }

        // Count words and compare to limit.
        $wordcount = count_words($submissiontext);
        if ($wordcount <= $wordlimit) {
            return null;
        } else {
            $errormsg = get_string('wordlimitexceeded', 'assignsubmission_plaintext',
                    array('limit' => $wordlimit, 'count' => $wordcount));
            return $OUTPUT->error_text($errormsg);
        }
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of settings
     * @since Moodle 3.2
     */
    public function get_config_for_external() {
        return (array) $this->get_config();
    }
}
