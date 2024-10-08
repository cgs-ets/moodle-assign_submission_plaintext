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
 * Strings for component 'assignsubmission_plaintext', language 'en'
 *
 * @package   assignsubmission_plaintext
 * @copyright 2024 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['allowplaintextsubmissions'] = 'Enabled';
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this submission method will be enabled by default for all new assignments.';
$string['enabled'] = 'Plain text';
$string['enabled_help'] = 'If enabled, students are able to type plain text directly into a text area field for their submission.';
$string['eventassessableuploaded'] = 'A plain text has been uploaded.';
$string['nosubmission'] = 'Nothing has been submitted for this assignment';
$string['plaintext'] = 'Plain text';
$string['plaintextfilename'] = 'plaintext.html';
$string['plaintextsubmission'] = 'Allow plain text submission';
$string['numwords'] = '({$a} words)';
$string['pluginname'] = 'Plain text submissions';
$string['privacy:metadata:assignmentid'] = 'Assignment ID';
$string['privacy:metadata:filepurpose'] = 'Files that are embedded in the text submission.';
$string['privacy:metadata:submissionpurpose'] = 'The submission ID that links to submissions for the user.';
$string['privacy:metadata:tablepurpose'] = 'Stores the text submission for each attempt.';
$string['privacy:metadata:textpurpose'] = 'The actual text submitted for this attempt of the assignment.';
$string['privacy:path'] = 'Submission Text';
$string['wordlimit'] = 'Word limit';
$string['wordlimit_help'] = 'If plain text submissions are enabled, this is the maximum number ' .
        'of words that each student will be allowed to submit.';
$string['wordlimitexceeded'] = 'The word limit for this assignment is {$a->limit} words and you ' .
        'are attempting to submit {$a->count} words. Please review your submission and try again.';
$string['cron_copy_to_plaincomment'] = 'Copy plain text submission to plain comment feedback';
$string['ptcourseid'] = 'Course ID';
$string['ptcourseid_help'] = 'Course ID where the text in the plain text submission will be copied to plain comment feedback. If 0 it will run for all assignments';
$string['gcategory'] = 'Grade category';
$string['gcategory_help'] = 'Grade category the assessment has to be part of to allow the copy from plain text submission to plain comment feedback';
// Deprecated since Moodle 4.3.
$string['numwordsforlog'] = 'Submission word count: {$a} words';
