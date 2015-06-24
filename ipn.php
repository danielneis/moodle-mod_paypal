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
 * Listens for Instant Payment Notification from PayPal
 *
 * This script waits for Payment notification from PayPal,
 * then double checks that data by sending it back to PayPal.
 * If PayPal verifies this then sets the activity as completed.
 *
 * @package    mod_paypal
 * @copyright  2010 Eugene Venter
 * @copyright  2015 Daniel Neis
 * @author     Eugene Venter - based on code by others
 * @author     Daniel Neis - based on code by others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir . '/filelib.php');

// PayPal does not like when we return error messages here,
// the custom handler just logs exceptions and stops.
set_exception_handler('mod_paypal_ipn_exception_handler');

/// Keep out casual intruders
if (empty($_POST) or !empty($_GET)) {
    print_error("Sorry, you can not use the script that way.");
}

/// Read all the data from PayPal and get it ready for later;
/// we expect only valid UTF-8 encoding, it is the responsibility
/// of user to set it up properly in PayPal business account,
/// it is documented in docs wiki.

$req = 'cmd=_notify-validate';

$data = new stdClass();

foreach ($_POST as $key => $value) {
    $req .= "&$key=".urlencode($value);
    $data->$key = $value;
}

$custom = explode('-', $data->custom);
$data->userid           = (int)$custom[0];
$data->courseid         = (int)$custom[1];
$data->instanceid       = (int)$custom[2];
$data->payment_gross    = $data->mc_gross;
$data->payment_currency = $data->mc_currency;
$data->timeupdated      = time();

/// get the user and course records

if (! $user = $DB->get_record("user", array("id"=>$data->userid))) {
    paypal_message_error_to_admin("Not a valid user id", $data);
    die;
}

if (! $course = $DB->get_record("course", array("id"=>$data->courseid))) {
    paypal_message_error_to_admin("Not a valid course id", $data);
    die;
}

if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
    paypal_message_error_to_admin("Not a valid context id", $data);
    die;
}

if (! $plugin_instance = $DB->get_record("paypal", array("id"=>$data->instanceid))) {
    paypal_message_error_to_admin("Not a valid instance id", $data);
    die;
}

$cm = get_coursemodule_from_instance('paypal', $data->instanceid);

/// Open a connection back to PayPal to validate the data
$paypaladdr = empty($CFG->usepaypalsandbox) ? 'www.paypal.com' : 'www.sandbox.paypal.com';
$c = new curl();
$options = array(
    'returntransfer' => true,
    'httpheader' => array('application/x-www-form-urlencoded', "Host: $paypaladdr"),
    'timeout' => 30,
    'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
);
$location = "https://$paypaladdr/cgi-bin/webscr";
$result = $c->post($location, $req, $options);

if (!$result) {  /// Could not connect to PayPal - FAIL
    echo "<p>Error: could not access paypal.com</p>";
    paypal_message_error_to_admin("Could not access paypal.com to verify payment", $data);
    die;
}

/// Connection is OK, so now we post the data to validate it

/// Now read the response and check if everything is OK.

if (strlen($result) > 0) {
    if (strcmp($result, "VERIFIED") == 0) {          // VALID PAYMENT!

        // Check the payment_status and payment_reason.

        // If status is not completed, just tell admin, transaction will be saved later.
        if ($data->payment_status != "Completed" and $data->payment_status != "Pending") {
            paypal_message_error_to_admin("Status not completed or pending. User payment status updated", $data);
        }

        // If currency is incorrectly set then someone maybe trying to cheat the system.
        if ($data->mc_currency != $plugin_instance->currency) {
            paypal_message_error_to_admin("Currency does not match course settings, received: ".$data->mc_currency, $data);
            die;
        }

        // If status is pending and reason is other than echeck,
        // then we are on hold until further notice.
        // Email user to let them know. Email admin.
        if ($data->payment_status == "Pending" and $data->pending_reason != "echeck") {
            $eventdata = new stdClass();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'mod_paypal';
            $eventdata->name              = 'paypal_mod';
            $eventdata->userfrom          = get_admin();
            $eventdata->userto            = $user;
            $eventdata->subject           = "Moodle: PayPal payment";
            $eventdata->fullmessage       = "Your PayPal payment is pending.";
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);

            paypal_message_error_to_admin("Payment pending", $data);
        }

        // If our status is not completed or not pending on an echeck clearance then ignore and die.
        // This check is redundant at present but may be useful if paypal extend the return codes in the future.
        if (! ( $data->payment_status == "Completed" or
               ($data->payment_status == "Pending" and $data->pending_reason == "echeck") ) ) {
            die;
        }

        // At this point we only proceed with a status of completed or pending with a reason of echeck.

        // Make sure this transaction doesn't exist already.
        if ($existing = $DB->get_record("paypal_transactions", array("txn_id"=>$data->txn_id))) {
            paypal_message_error_to_admin("Transaction $data->txn_id is being repeated!", $data);
            die;
        }

        // Check that the email is the one we want it to be.
        if (core_text::strtolower($data->business) !== core_text::strtolower($plugin_instance->businessemail)) {
            paypal_message_error_to_admin("Business email is {$data->business} (not ".
                                            $plugin_instance->businessemail.")", $data);
            die;
        }

        // Check that user exists.
        if (!$user = $DB->get_record('user', array('id'=>$data->userid))) {
            paypal_message_error_to_admin("User $data->userid doesn't exist", $data);
            die;
        }

        // Check that course exists.
        if (!$course = $DB->get_record('course', array('id'=>$data->courseid))) {
            paypal_message_error_to_admin("Course $data->courseid doesn't exist", $data);
            die;
        }

        $coursecontext = context_course::instance($course->id, IGNORE_MISSING);

        // Check that amount paid is the correct amount.
        if ( (float) $plugin_instance->cost < 0 ) {
            $cost = (float) 0;
        } else {
            $cost = (float) $plugin_instance->cost;
        }

        // Use the same rounding of floats as on the plugin form.
        $cost = format_float($cost, 2, false);

        if ($data->payment_gross < $cost) {
            paypal_message_error_to_admin("Amount paid is not enough ({$data->payment_gross} < {$cost}))", $data);
            die;
        }

        // All clear!

        $DB->insert_record("paypal_transactions", $data);

        // Update completion state.
        if ($data->payment_status == 'Completed') {
            $completion=new completion_info($course);
            if ($completion->is_enabled($cm) && $plugin_instance->paymentcompletionenabled ) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }

        // Pass $view=true to filter hidden caps if the user cannot see them.
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                             '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }

        $mailstudents = $plugin_instance->mailstudents;
        $mailteachers = $plugin_instance->mailteachers;
        $mailadmins   = $plugin_instance->mailadmins;
        $shortname = format_string($course->shortname, true, array('context' => $context));

        if (!empty($mailstudents)) {
            $a = new stdClass();
            $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
            $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

            $eventdata = new stdClass();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'mod_paypal';
            $eventdata->name              = 'paypal_payment';
            $eventdata->userfrom          = empty($teacher) ? core_user::get_support_user() : $teacher;
            $eventdata->userto            = $user;
            $eventdata->subject           = get_string("newpaypalpaymentsubject", 'paypal');
            $eventdata->fullmessage       = get_string('newpaypalpaymentmessage', 'paypal');
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }

        if (!empty($mailteachers) && !empty($teacher)) {
            $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
            $a->user = fullname($user);

            $eventdata = new stdClass();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'mod_paypal';
            $eventdata->name              = 'paypal_payment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $teacher;
            $eventdata->subject           = get_string("newpaypalpaymentsubject", 'paypal');
            $eventdata->fullmessage       = get_string('newpaypalpaymentmessage', 'paypal');
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }

        if (!empty($mailadmins)) {
            $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
            $a->user = fullname($user);
            $admins = get_admins();
            foreach ($admins as $admin) {
                $eventdata = new stdClass();
                $eventdata->modulename        = 'moodle';
                $eventdata->component         = 'mod_paypal';
                $eventdata->name              = 'paypal_payment';
                $eventdata->userfrom          = $user;
                $eventdata->userto            = $admin;
                $eventdata->subject           = get_string("newpaypalpaymentsubject", 'paypal');
                $eventdata->fullmessage       = get_string('newpaypalpaymentmessage', 'paypal');
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml   = '';
                $eventdata->smallmessage      = '';
                message_send($eventdata);
            }
        }

    } else if (strcmp ($result, "INVALID") == 0) { // ERROR
        $DB->insert_record("paypal_transactions", $data, false);
        paypal_message_error_to_admin("Received an invalid payment notification!! (Fake payment?)", $data);
    }
}

function paypal_message_error_to_admin($subject, $data) {
    echo $subject;
    $admin = get_admin();
    $site = get_site();

    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

    foreach ($data as $key => $value) {
        $message .= "$key => $value\n";
    }

    $eventdata = new stdClass();
    $eventdata->modulename        = 'moodle';
    $eventdata->component         = 'mod_paypal';
    $eventdata->name              = 'paypal_payment';
    $eventdata->userfrom          = $admin;
    $eventdata->userto            = $admin;
    $eventdata->subject           = "PAYPAL ERROR: ".$subject;
    $eventdata->fullmessage       = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = '';
    message_send($eventdata);
}

/**
 * Silent exception handler.
 *
 * @param Exception $ex
 * @return void - does not return. Terminates execution!
 */
function mod_paypal_ipn_exception_handler($ex) {
    $info = get_exception_info($ex);

    $logerrmsg = "mod_paypal IPN exception handler: ".$info->message;
    if (debugging('', DEBUG_NORMAL)) {
        $logerrmsg .= ' Debug: '.$info->debuginfo."\n".format_backtrace($info->backtrace, true);
    }
    error_log($logerrmsg);

    exit(0);
}
