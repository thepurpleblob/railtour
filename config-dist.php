<?php

unset($CFG);
$CFG = new stdClass;

// Database stuff
$CFG->dsn = "mysql:host=localhost;dbname=santa";
$CFG->dbuser = '';
$CFG->dbpass = '';

// Project name
// (Needs to match composer autoloader \\thepurpleblob\\projectname\\)
$CFG->projectname = 'railtour';

// Title for header
$CFG->titleheader = 'SRPS Railtours Booking';

// Routes (default if none specified)
// NO leading slash please
$CFG->defaultroute = 'admin/main';
$CFG->logoutpath = 'service/index';

// paths
$CFG->www = 'http://localhost/railtour';

$CFG->dirroot = '/var/www/railtour';

// Enable booking
$CFG->enablebooking = true;

// defaults for limits
$CFG->default_limit = 100;
$CFG->default_party = 8;

// number of people limit in each select
$CFG->select_limit = 8;

// Sagepay stuff
$CFG->sage_url = 'https://test.sagepay.com/gateway/service/vspserver-register.vsp'; // Test server
// $CFG->sage_url = 'https://live.sagepay.com/gateway/service/vspserver-register.vsp'; // Live server
$CFG->sage_vendor = 'srpsrailtours';
$CFG->sage_email = ''; // multiple separate with colons
$CFG->sage_message = ''; // message inserted into sage email (up to 700 chars)
$CFG->sage_prefix = 'TS'; // prefix for booking codes (MUST be unique for installation)

// Email stuff
$CFG->smtpd_host = 'mailx.e-learndesign.co.uk';
$CFG->backup_email = '';

// Contact number
$CFG->help_number = '01506 825855';

// Debugging
$CFG->debugging = 1;
