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
 * Prints a particular instance of paypal
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_paypal
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace paypal with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // ... paypal instance ID - it should be named as the first character of the module.

if ($id) {
    $cm         = get_coursemodule_from_id('paypal', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $paypal  = $DB->get_record('paypal', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $paypal  = $DB->get_record('paypal', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $paypal->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('paypal', $paypal->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

require_capability('mod/paypal:view', $PAGE->context);

$event = \mod_paypal\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $paypal);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/paypal/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($paypal->name));
$PAGE->set_heading(format_string($course->fullname));

/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('paypal-'.$somevar);
 */

// Output starts here.
echo $OUTPUT->header();

// Replace the following lines with you own code.
echo $OUTPUT->heading($paypal->name);

// Conditions to show the intro can change to look for own settings or whatever.
if ($paypal->intro) {
    echo $OUTPUT->box(format_module_intro('paypal', $paypal, $cm->id), 'generalbox mod_introbox', 'paypalintro');
}

if ($paymenttnx = $DB->get_record('paypal_transactions',
                                   array('userid' => $USER->id,
                                         'instanceid' => $paypal->id))) {

    if ($payment_tnx->payment_status == 'Completed') {
        // should double-check with paypal everytime ?
        echo get_string('paymentcompleted', 'paypal');
    } else if ($payment_tnx->payment_status == 'Pending') {
        echo get_string('paymentpending', 'paypal');
    }

} else {

    // Calculate localised and "." cost, make sure we send PayPal the same value,
    // please note PayPal expects amount with 2 decimal places and "." separator.
    $localisedcost = format_float($paypal->cost, 2, true);
    $cost = format_float($paypal->cost, 2, false);

    if (isguestuser()) { // force login only for guest user, not real users with guest role
        if (empty($CFG->loginhttps)) {
            $wwwroot = $CFG->wwwroot;
        } else {
            // This actually is not so secure ;-), 'cause we're
            // in unencrypted connection...
            $wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
        }
        echo '<div class="mdl-align"><p>'.get_string('paymentrequired', 'paypal').'</p>';
        echo '<div class="mdl-align"><p>'.get_string('paymentwaitremider', 'paypal').'</p>';
        echo '<p><b>'.get_string('cost').": $instance->currency $localisedcost".'</b></p>';
        echo '<p><a href="'.$wwwroot.'/login/">'.get_string('loginsite').'</a></p>';
        echo '</div>';
    } else {
        //Sanitise some fields before building the PayPal form
        $coursefullname  = format_string($course->fullname, true, array('context' => $PAGE->context));
        $courseshortname = $course->shortname;
        $userfullname    = fullname($USER);
        $userfirstname   = $USER->firstname;
        $userlastname    = $USER->lastname;
        $useraddress     = $USER->address;
        $usercity        = $USER->city;
        $instancename    = $paypal->name;
?>
        <p><?php print_string("paymentrequired", 'paypal') ?></p>
        <p><b><?php echo get_string("cost").": {$paypal->currency} {$localisedcost}"; ?></b></p>
        <p><img alt="<?php print_string('paypalaccepted', 'paypal') ?>"
        title="<?php print_string('paypalaccepted', 'paypal') ?>"
        src="https://www.paypal.com/en_US/i/logo/PayPal_mark_60x38.gif" /></p>
        <p><?php print_string("paymentinstant", 'paypal') ?></p>
        <?php
            $paypalurl = empty($CFG->usepaypalsandbox) ? 'https://www.paypal.com/cgi-bin/webscr' : 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        ?>
        <form action="<?php echo $paypalurl ?>" method="post">

            <input type="hidden" name="cmd" value="_xclick" />
            <input type="hidden" name="charset" value="utf-8" />
            <input type="hidden" name="business" value="<?php p($paypal->businessemail)?>" />
            <input type="hidden" name="item_name" value="<?php p($paypal->itemname) ?>" />
            <input type="hidden" name="item_number" value="<?php p($paypal->itemnumber) ?>" />
            <input type="hidden" name="quantity" value="1" />
            <input type="hidden" name="on0" value="<?php print_string("user") ?>" />
            <input type="hidden" name="os0" value="<?php p($userfullname) ?>" />
            <input type="hidden" name="custom" value="<?php echo "{$USER->id}-{$course->id}-{$paypal->id}" ?>" />

            <input type="hidden" name="currency_code" value="<?php p($paypal->currency) ?>" />
            <input type="hidden" name="amount" value="<?php p($cost) ?>" />

            <input type="hidden" name="for_auction" value="false" />
            <input type="hidden" name="no_note" value="1" />
            <input type="hidden" name="no_shipping" value="1" />
            <input type="hidden" name="notify_url" value="<?php echo "{$CFG->wwwroot}/mod/paypal/ipn.php" ?>" />
            <input type="hidden" name="return" value="<?php echo "{$CFG->wwwroot}/mod/paypal/view.php?id={$id}" ?>" />
            <input type="hidden" name="cancel_return" value="<?php echo "{$CFG->wwwroot}/mod/paypal/view.php?id={$id}" ?>" />
            <input type="hidden" name="rm" value="2" />
            <input type="hidden" name="cbt" value="<?php print_string("continuetocourse") ?>" />

            <input type="hidden" name="first_name" value="<?php p($userfirstname) ?>" />
            <input type="hidden" name="last_name" value="<?php p($userlastname) ?>" />
            <input type="hidden" name="address" value="<?php p($useraddress) ?>" />
            <input type="hidden" name="city" value="<?php p($usercity) ?>" />
            <input type="hidden" name="email" value="<?php p($USER->email) ?>" />
            <input type="hidden" name="country" value="<?php p($USER->country) ?>" />

            <input type="submit" value="<?php print_string("sendpaymentbutton", "paypal") ?>" />

        </form>
<?php
    }
}
// Finish the page.
echo $OUTPUT->footer();
