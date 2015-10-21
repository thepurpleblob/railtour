<?php

unset($CFG);
$CFG = new stdClass;

// Database stuff
$CFG->dsn = "mysql:host=localhost;dbname=santa";
$CFG->dbuser = '';
$CFG->dbpass = '';

// paths
$CFG->www = '';

$CFG->dirroot = '';

// defaults for limits
$CFG->default_limit = 100;
$CFG->default_party = 8;

// number of people limit in each select
$CFG->select_limit = 8;

// Sagepay stuff
$CFG->sage_url = '';
$CFG->sage_vendor = '';
$CFG->sage_encrypt = '';
$CFG->sage_email = ''; // multiple separate with colons
$CFG->sage_message = ''; // message inserted into sage email (up to 700 chars)
$CFG->sage_prefix = 'TS'; // prefix for booking codes (MUST be unique for installation)

// Email stuff
$CFG->smtpd_host = '';
$CFG->backup_email = '';

// Contact number
$CFG->help_number = '01506 825855';
