<?php

namespace thepurpleblob\railtour\library;

use Exception;

/**
 * Class Mail
 * @package thepurpleblob\railtour\library
 */
class Mail {

	protected $mailer;

    /**
     * Initialise email service
     */
    public function initialise() {

        // Create transport
        $transport = new Swift_SmtpTransport($CFG->smtpd_host);

        // Create mailer
        $this->mailer = new Swift_Mailer($transport);
    }
}