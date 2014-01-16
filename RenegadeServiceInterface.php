<?php

namespace renegade;

/**
 * Interface RenegadeServiceInterface
 *
 * This interface defines the methods that all services must implement to be valid.
 * So far these tasks are build, screenshot, pdf and package
 *
 * @package renegade
 */
interface RenegadeServiceInterface {
    public function build();
    public function screenshot();
    public function pdf();
    public function package();
} 