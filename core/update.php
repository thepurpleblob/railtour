<?php
/**
 * Check for schema updates
 * User: howard
 * Date: 30/03/2016
 * Time: 21:35
 */


// check if config table needs creating
$db = ORM::get_db();
$db->exec("
        CREATE TABLE IF NOT EXISTS config (
            id INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name CHAR(20),
            value TEXT
    );"
);

// Try to find current version in database
$config = ORM::forTable('config')->where('name', 'version')->findOne();
if ($config) {
    $dbversion = $config->value;
} else {
    $config = ORM::forTable('config')->create();
    $dbversion = 0;
}

// Conversion to server method for SagePay needs extra fields
if ($dbversion < 2016033000) {
    $db->exec('ALTER TABLE purchase
        ADD securitykey VARCHAR(50),
        ADD regstatus VARCHAR(50)');
}

// More fields I seem to have forgotten about
if ($dbversion < 2016040300) {
    $db->exec('ALTER TABLE purchase
        ADD VPSTxId VARCHAR(40)');
}

// Make config version up to date
$config->name = 'version';
$config->value = $version;
$config->save();