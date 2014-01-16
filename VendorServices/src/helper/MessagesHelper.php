<?php

namespace Renegade\VendorServices\Helper;

class MessagesHelper {

    /**
     * Output Green Text
     *
     * @param $message
     * @return string
     */
    public function success_message($message) {
        return "<info>".$message."</info>";
    }

    /**
     * Output White Text on Red Background
     *
     * @param $message
     * @return string
     */
    public function error_message($message) {
        return "<error>".$message."</error>";
    }

    /**
     * Output Yellow Text
     *
     * @param $message
     * @return string
     */
    public function notification_message($message) {
        return "<comment>".$message."</comment>";
    }
} 