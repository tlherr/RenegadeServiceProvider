<?php

namespace Renegade\VendorServices;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface RenegadeServiceInterface
 *
 * This interface defines the methods that all services must implement to be valid.
 * So far these tasks are build, screenshot, pdf and package
 *
 * @package renegade
 */
interface RenegadeServiceInterface {
    public function build(InputInterface $input, OutputInterface $output);
    public function screenshot(InputInterface $input, OutputInterface $output);
    public function pdf(InputInterface $input, OutputInterface $output);
    public function package(InputInterface $input, OutputInterface $output);
} 