Paypal Activity plugin for Moodle
================================

The following steps should get you up and running with
this module template code.

* DO NOT PANIC!
* Unzip the archive and read this file
* This code must be on directory mod/paypal inside your moodle installation
* With the code on the right place, go to your browser log in to your Moodle as admin
* The update screen should be shown, if not, go to the "Notifications" link
* Follow the steps


Features
========

This version allows teachers (or anyone with capabilities to add activities to course)
to add paypal activities, setting a business email addres, a cost and a currency.

When the students access the course module, some instructions and a button to pay are displayed.

When you click the button you are redirected to paypal.
Paypal then will notify the plugin in an asynchronous way when the payment is completed.
When this notification occurs, it is recorded on the database and the completion status of the activity
is updated for that user.

If there is a transaction for the activity and the current user, instead of display the button to pay,
a message telling that the payment was made and activity is completed is shown.

There is a way to verify with paypal if the transaction recorded is really valid, but it is not being done yet.

Funding
=======

The development of this plugin was funded by TRREE - TRAINING AND RESOURCES IN RESEARCH ETHICS EVALUATION - http://www.trree.org/
