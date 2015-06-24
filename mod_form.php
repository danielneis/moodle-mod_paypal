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
 * The main paypal configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_paypal
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @package    mod_paypal
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_paypal_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('paypalname', 'paypal'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'paypalname', 'paypal');

        $this->standard_intro_elements();

        $mform->addElement('text', 'businessemail', get_string('businessemail', 'paypal'));
        $mform->setType('businessemail', PARAM_EMAIL);
        $mform->setDefault('businessemail', '');

        $mform->addElement('text', 'cost', get_string('cost', 'paypal'), array('size'=>4));
        $mform->setType('cost', PARAM_FLOAT);
        $mform->setDefault('cost', format_float(0, 2, true));

        $paypalcurrencies = $this->get_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'paypal'), $paypalcurrencies);
        $mform->setDefault('currency', 'BRL');

        $mform->addElement('text', 'itemname', get_string('itemname', 'paypal'));
        $mform->setType('itemname', PARAM_TEXT);
        $mform->setDefault('itemname', '');

        $mform->addElement('text', 'itemnumber', get_string('itemnumber', 'paypal'));
        $mform->setType('itemnumber', PARAM_TEXT);
        $mform->setDefault('itemnumber', '');

        $mform->addElement('checkbox', 'mailadmins', get_string('mailadmins', 'paypal'));
        $mform->addHelpButton('mailadmins', 'mailadmins', 'paypal');

        $mform->addElement('checkbox', 'mailstudents', get_string('mailstudents', 'paypal'));
        $mform->addHelpButton('mailstudents', 'mailstudents', 'paypal');

        $mform->addElement('checkbox', 'mailteachers', get_string('mailteachers', 'paypal'));
        $mform->addHelpButton('mailteachers', 'mailteachers', 'paypal');

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    function validation($data, $files) {
        $errors = array();
        if (empty($data['businessemail'])) {
            $errors['businessemail'] = get_string('emptybusinessemail', 'paypal');
        }
        if (empty($data['cost'])) {
            $errors['cost'] = get_string('emptycost', 'paypal');
        }
        if (empty($data['itemname'])) {
            $errors['itemname'] = get_string('emptyitemname', 'paypal');
        }
        if (empty($data['itemnumber'])) {
            $errors['itemnumber'] = get_string('emptyitemnumber', 'paypal');
        }
        return $errors;
    }

    function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox', 'paymentcompletionenabled', get_string('requirepayment', 'paypal'), get_string('paymentcompletionenabled','paypal'));
        $mform->addHelpButton('paymentcompletionenabled', 'requirepayment', 'paypal');

        return array('paymentcompletionenabled');
    }

    function completion_rule_enabled($data) {
        return $data['paymentcompletionenabled'];
    }

    public function get_currencies() {
        // See https://www.paypal.com/cgi-bin/webscr?cmd=p/sell/mc/mc_intro-outside,
        // 3-character ISO-4217: https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_currency_codes
        $codes = array(
            'AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY',
            'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RUB', 'SEK', 'SGD', 'THB', 'TRY', 'TWD', 'USD');
        $currencies = array();
        foreach ($codes as $c) {
            $currencies[$c] = new lang_string($c, 'core_currencies');
        }

        return $currencies;
    }
}
