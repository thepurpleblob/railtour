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

// Add fields for eTicket option and for user-not-present booking
if ($dbversion < 2016041900) {
    $db->exec('ALTER TABLE purchase
        ADD bookedby VARCHAR(25)');
}
if ($dbversion < 2016041901) {
    $db->exec('ALTER TABLE service
        ADD eticketenabled tinyint(1),
        ADD eticketforce tinyint(1)');
}
if ($dbversion < 2018012900) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `session` (
            `id` varchar(32) NOT NULL,
            `access` int(10) unsigned DEFAULT NULL,
            `data` text,
            `ip` varchar(32) DEFAULT NULL,
            PRIMARY KEY (`id`)
        )"
    );
}
if ($dbversion < 2018021200) {
    $db->exec('ALTER TABLE destination
        ADD meala tinyint(1) NOT NULL,
        ADD mealb tinyint(1) NOT NULL,
        ADD mealc tinyint(1) NOT NULL,
        ADD meald tinyint(1) NOT NULL');
}
if ($dbversion < 2018021201) {
    $db->exec('UPDATE destination
        SET meala=1, mealb=1, mealc=1, meald=1');
}

// Make config version up to date
$config->name = 'version';
$config->value = $version;
$config->save();
