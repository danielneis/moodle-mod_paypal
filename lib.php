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
 * Library of interface functions and constants for module paypal
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the paypal specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_paypal
 * @copyright  2015 Daniel Neis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function paypal_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the paypal into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $paypal Submitted data from the form in mod_form.php
 * @param mod_paypal_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted paypal record
 */
function paypal_add_instance(stdClass $paypal, mod_paypal_mod_form $mform = null) {
    global $DB;

    $paypal->timecreated = time();

    // You may have to add extra stuff in here.

    $paypal->id = $DB->insert_record('paypal', $paypal);

    paypal_grade_item_update($paypal);

    return $paypal->id;
}

/**
 * Updates an instance of the paypal in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $paypal An object from the form in mod_form.php
 * @param mod_paypal_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function paypal_update_instance(stdClass $paypal, mod_paypal_mod_form $mform = null) {
    global $DB;

    $paypal->timemodified = time();
    $paypal->id = $paypal->instance;

    // You may have to add extra stuff in here.

    $result = $DB->update_record('paypal', $paypal);

    paypal_grade_item_update($paypal);

    return $result;
}

/**
 * Removes an instance of the paypal from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function paypal_delete_instance($id) {
    global $DB;

    if (! $paypal = $DB->get_record('paypal', array('id' => $id))) {
        return false;
    }

    // Delete any dependent records here.

    $DB->delete_records('paypal', array('id' => $paypal->id));

    paypal_grade_item_delete($paypal);

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $paypal The paypal instance record
 * @return stdClass|null
 */
function paypal_user_outline($course, $user, $mod, $paypal) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $paypal the module instance record
 */
function paypal_user_complete($course, $user, $mod, $paypal) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in paypal activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function paypal_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link paypal_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function paypal_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@link paypal_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function paypal_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function paypal_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function paypal_get_extra_capabilities() {
    return array();
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of paypal?
 *
 * This function returns if a scale is being used by one paypal
 * if it has support for grading and scales.
 *
 * @param int $paypalid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given paypal instance
 */
function paypal_scale_used($paypalid, $scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('paypal', array('id' => $paypalid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of paypal.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any paypal instance
 */
function paypal_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('paypal', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given paypal instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $paypal instance object with extra cmidnumber and modname property
 * @param bool $reset reset grades in the gradebook
 * @return void
 */
function paypal_grade_item_update(stdClass $paypal, $reset=false) {
    /*
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($paypal->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($paypal->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $paypal->grade;
        $item['grademin']  = 0;
    } else if ($paypal->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$paypal->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($reset) {
        $item['reset'] = true;
    }

    grade_update('mod/paypal', $paypal->course, 'mod', 'paypal',
            $paypal->id, 0, null, $item);
    */
}

/**
 * Delete grade item for given paypal instance
 *
 * @param stdClass $paypal instance object
 * @return grade_item
 */
function paypal_grade_item_delete($paypal) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/paypal', $paypal->course, 'mod', 'paypal',
            $paypal->id, 0, null, array('deleted' => 1));
}

/**
 * Update paypal grades in the gradebook
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $paypal instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 */
function paypal_update_grades(stdClass $paypal, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    $grades = array();

    grade_update('mod/paypal', $paypal->course, 'mod', 'paypal', $paypal->id, 0, $grades);
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function paypal_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for paypal file areas
 *
 * @package mod_paypal
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function paypal_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the paypal file areas
 *
 * @package mod_paypal
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the paypal's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function paypal_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

/**
 * Obtains the automatic completion state for this forum based on any conditions
 * in forum settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function paypal_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // Get forum details
    if (!$paypal = $DB->get_record('paypal',array('id'=>$cm->instance))) {
        throw new Exception("Can't find paypal {$cm->instance}");
    }

    // If completion option is enabled, evaluate it and return true/false 
    if ($paypal->paymentcompletionenabled) {
        // should double-check with paypal everytime ?
        return $DB->record_exists('paypal_transactions',
                                  array('userid' => $userid,
                                        'instanceid' => $paypal->id,
                                        'payment_status' => 'Completed'));
    } else {
        // Completion option is not enabled so just return $type .
        return $type;
    }
}
