<?php

$schema = [];

$schema[] = 'CREATE TABLE `config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` char(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';

$schema[] = 'CREATE TABLE `destination` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serviceid` int(11) NOT NULL,
  `name` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `crs` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  `bookinglimit` int(11) NOT NULL,
  `meala` tinyint(1) NOT NULL,
  `mealb` tinyint(1) NOT NULL,
  `mealc` tinyint(1) NOT NULL,
  `meald` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';

$schema[] = 'CREATE TABLE `joining` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serviceid` int(11) NOT NULL,
  `pricebandgroupid` int(11) NOT NULL,
  `station` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `crs` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `meala` tinyint(1) NOT NULL,
  `mealb` tinyint(1) NOT NULL,
  `mealc` tinyint(1) NOT NULL,
  `meald` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=331 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';

$schema[] = 'CREATE TABLE `limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serviceid` int(11) NOT NULL,
  `first` int(11) NOT NULL,
  `standard` int(11) NOT NULL,
  `firstsingles` int(11) NOT NULL,
  `meala` int(11) NOT NULL,
  `mealb` int(11) NOT NULL,
  `mealc` int(11) NOT NULL,
  `meald` int(11) NOT NULL,
  `maxparty` int(11) NOT NULL,
  `maxpartyfirst` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';

$schema[] = 'CREATE TABLE `priceband` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serviceid` int(11) NOT NULL,
  `destinationid` int(11) NOT NULL,
  `pricebandgroupid` int(11) NOT NULL,
  `first` decimal(10,2) NOT NULL,
  `standard` decimal(10,2) NOT NULL,
  `child` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';

$schema[] = 'CREATE TABLE `pricebandgroup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serviceid` int(11) NOT NULL,
  `name` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';

$schema[] = 'CREATE TABLE `purchase` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` int(11) NOT NULL,
  `serviceid` int(11) NOT NULL,
  `created` int(11) NOT NULL,
  `seskey` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(1) COLLATE utf8_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `bookingref` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `completed` tinyint(1) NOT NULL,
  `manual` tinyint(1) NOT NULL,
  `title` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `surname` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `firstname` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `address1` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `address2` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `city` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `county` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `postcode` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `joining` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `destination` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `class` varchar(1) COLLATE utf8_unicode_ci NOT NULL,
  `adults` int(11) NOT NULL,
  `children` int(11) NOT NULL,
  `meala` int(11) NOT NULL,
  `mealb` int(11) NOT NULL,
  `mealc` int(11) NOT NULL,
  `meald` int(11) NOT NULL,
  `payment` decimal(10,2) NOT NULL,
  `seatsupplement` tinyint(1) NOT NULL,
  `comment` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `status` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `statusdetail` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `cardtype` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `last4digits` int(11) NOT NULL,
  `bankauthcode` int(11) NOT NULL,
  `declinecode` int(11) NOT NULL,
  `emailsent` tinyint(1) NOT NULL,
  `eticket` tinyint(1) NOT NULL,
  `einfo` tinyint(1) NOT NULL,
  `securitykey` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `regstatus` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `VPSTxId` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `bookedby` varchar(25) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8772 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';

$schema[] = 'CREATE TABLE `service` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  `visible` tinyint(1) NOT NULL,
  `date` date NOT NULL,
  `mealaname` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `mealavisible` tinyint(1) NOT NULL,
  `mealaprice` decimal(10,2) NOT NULL,
  `mealbname` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `mealbvisible` tinyint(1) NOT NULL,
  `mealbprice` decimal(10,2) NOT NULL,
  `mealcname` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `mealcvisible` tinyint(1) NOT NULL,
  `mealcprice` decimal(10,2) NOT NULL,
  `mealdname` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `mealdvisible` tinyint(1) NOT NULL,
  `mealdprice` decimal(10,2) NOT NULL,
  `singlesupplement` decimal(10,2) NOT NULL,
  `commentbox` tinyint(1) NOT NULL,
  `maxparty` int(11) NOT NULL,
  `eticketenabled` tinyint(1) DEFAULT NULL,
  `eticketforce` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';

$schema[] = 'CREATE TABLE `srps_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `firstname` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `lastname` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `salt` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `role` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_7E080E4EF85E0677` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';

$schema[] = 'CREATE TABLE `station` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `crs` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';

$schema[] = 'CREATE TABLE `session` (
  `id` varchar(32) NOT NULL,
  `access` int(10) unsigned DEFAULT NULL,
  `data` text,
  `ip` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'';
