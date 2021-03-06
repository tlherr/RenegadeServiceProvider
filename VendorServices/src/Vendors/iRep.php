<?php

namespace Renegade\VendorServices\Vendors;

use JonnyW\PhantomJs\Client;
use Renegade\VendorServices\Helper\ArchiveHelper;
use Renegade\VendorServices\Helper\DirectoryHelper;
use Renegade\VendorServices\Helper\MessagesHelper;
use Renegade\VendorServices\Helper\NetworkHelper;
use Renegade\VendorServices\RenegadeServiceInterface;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use DOMDocument;
use DOMElement;
use Symfony\Component\Filesystem\Filesystem;

class iRep implements RenegadeServiceInterface {

    protected $console;
    protected $filesystem;
    protected $phantomJS;
    protected $tcpdf;
    protected $config;
    protected $directory;
    protected $twig;

    public $archiveHelper;
    public $directoryHelper;
    public $messagesHelper;
    public $networkHelper;

    /**
     * @param $console
     * @param $twig
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem
     * @param \JonnyW\PhantomJs\Client $phantomJS
     * @param \TCPDF $tcpdf
     * @param $config
     * @param $directory                string      Application Path (console.php location)
     */
    public function __construct($console, $twig, Filesystem $filesystem, Client $phantomJS, \TCPDF $tcpdf, $config, $directory) {
        $this->console = $console;
        $this->twig = $twig;
        $this->filesystem = $filesystem;
        $this->phantomJS = $phantomJS;
        $this->tcpdf = $tcpdf;
        $this->config = $config;
        $this->directory = $directory;

        $this->archiveHelper = new ArchiveHelper();
        $this->directoryHelper = new DirectoryHelper($filesystem, $config, $directory);
        $this->messagesHelper = new MessagesHelper();
        $this->networkHelper = new NetworkHelper();
    }

    public function build(InputInterface $input, OutputInterface $output) {
        $this->directoryHelper->init($input->getOption('lang'), 'build');

        $output->writeln($this->messagesHelper->success_message(sprintf("* Scanning for template files matching language type: %s", $input->getOption('lang'))));
        $directory = new RecursiveDirectoryIterator(sprintf('%s/views/%s/pages', $this->directory, $input->getOption('lang')));
        $iterator = new RecursiveIteratorIterator($directory);
        /**
         * @var $iterator RecursiveIteratorIterator
         */
        $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);

        /**
         * @var $progress ProgressHelper
         */
        $progress = $this->console->getHelperSet()->get('progress');
        $progress->setFormat(ProgressHelper::FORMAT_VERBOSE_NOMAX);
        $progress->start($output);

        foreach($iterator as $template_file) {
            /**
             * @var $template_file DirectoryIterator
             */

            $sub_dir = sprintf('%s/%s', $this->directoryHelper->get_localized_dir('build', $input->getOption('lang')), $template_file->getBasename('.html.twig'));
            $this->filesystem->mkdir($sub_dir);

            $this->directoryHelper->save_page(
                $this->twig->render(sprintf('/%s/pages/%s', $input->getOption('lang'), $template_file->getFileInfo()->getFilename()), array(
                        'is_relative' => $input->getOption('assets_relative'),
                        'page_number' => preg_match('/slide([\d]+)/', $template_file->getFileInfo()->getFilename()),
                        'prefix' => sprintf('%s_remicade_derm_indication_slide', strtolower($input->getOption('lang')))
                    )
                ),
                $sub_dir,
                $template_file->getBasename('.twig')
            );


            $output->writeln($this->messagesHelper->success_message(sprintf('Searching document: [%s] for resources', $template_file->getFilename())));
            $doc = new \DOMDocument();
            $doc->loadHTMLFile(sprintf('%s/%s', $sub_dir, $template_file->getBasename('.twig')));

            /**
             * Copy CSS
             */
            $this->filesystem->mkdir(sprintf('%s/%s', $sub_dir, 'css'));
            $linkTags = $doc->getElementsByTagName('link');
            $output->writeln($this->messagesHelper->notification_message(sprintf('Found %s css resources', count($linkTags))));
            foreach($linkTags as $tag) {
                /**
                 * @var $tag DOMElement
                 */
                $output->writeln($this->messagesHelper->notification_message(sprintf('Moving %s from %s to %s/%s', $tag->getAttribute('href'), $this->directory, $sub_dir, $tag->getAttribute('href'))));
                $this->filesystem->copy(sprintf('%s/%s', $this->directory, $tag->getAttribute('href')), sprintf('%s/%s', $sub_dir, $tag->getAttribute('href')));
            }

            /**
             * Copy JS
             */
            $this->filesystem->mkdir(sprintf('%s/%s', $sub_dir, 'js'));
            $scriptTags = $doc->getElementsByTagName('script');
            $output->writeln($this->messagesHelper->notification_message(sprintf('Found %s js resources', count($scriptTags))));

            foreach($scriptTags as $tag) {
                /**
                 * @var $tag DOMElement
                 */
                $output->writeln($this->messagesHelper->notification_message(sprintf('Moving %s from %s to %s/%s', $tag->getAttribute('src'), $this->directory, $sub_dir, $tag->getAttribute('src'))));
                $this->filesystem->copy(sprintf('%s/%s', $this->directory, $tag->getAttribute('src')), sprintf('%s/%s', $sub_dir, $tag->getAttribute('src')));
            }

            /**
             * Copy Fonts
             */
            $this->filesystem->mkdir(sprintf('%s/%s', $sub_dir, 'fonts'));
            $this->filesystem->mirror(sprintf('%s/%s', $this->directory, 'fonts'), sprintf('%s/%s', $sub_dir, 'fonts'));

            /**
             * Copy Images
             */
            $this->filesystem->mkdir(sprintf('%s/%s', $sub_dir, 'img'));
            $imageTags = $doc->getElementsByTagName('img');
            $output->writeln($this->messagesHelper->notification_message(sprintf('Found %s img resources', count($imageTags))));

            foreach($imageTags as $tag) {
                /**
                 * @var $tag DOMElement
                 */
                $output->writeln($this->messagesHelper->notification_message(sprintf('Moving %s from %s to %s/%s', $tag->getAttribute('src'), $this->directory, $sub_dir, $tag->getAttribute('src'))));
                $this->filesystem->copy(sprintf('%s/%s', $this->directory, $tag->getAttribute('src')), sprintf('%s/%s', $sub_dir, $tag->getAttribute('src')));
            }

            $this->filesystem->copy(sprintf('%s/img/header.jpg', $this->directory), sprintf('%s/img/header.jpg', $sub_dir));
            $this->filesystem->copy(sprintf('%s/img/footer.png', $this->directory), sprintf('%s/img/footer.png', $sub_dir));

            $progress->advance();
        }
        $progress->finish();
        $output->writeln($this->messagesHelper->success_message('Build Completed'));
    }

    public function screenshot(InputInterface $input, OutputInterface $output) {
        $this->directoryHelper->init($input->getOption('lang'), 'screenshots');

        $directory = new RecursiveDirectoryIterator(sprintf('%s/views/%s/pages', $this->directory, $input->getOption('lang')));
        $iterator = new RecursiveIteratorIterator($directory);
        $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);

        if(!$this->networkHelper->isDomainAvailible(sprintf( 'http://%s', $this->config['application']['url']))) {
            throw new \Exception('URL Unavailable to screenshot');
        }

        foreach($iterator as $fileInfo) {
            /**
             * @var $fileInfo DirectoryIterator
             */
            $client = $this->phantomJS;
            $client->setPhantomJs($this->config['application']['phantomjs_bin']);
            $request = $client->getMessageFactory()->createRequest('GET', sprintf( 'http://%s/screenshot/%s/%s', $this->config['application']['url'], $input->getOption('lang'), $fileInfo->getFilename()));
            $response = $client->getMessageFactory()->createResponse();
            $client->send($request, $response, sprintf('%s/%s.png', $this->directoryHelper->get_localized_dir('screenshots', $input->getOption('lang')), $fileInfo->getBasename('.html')));
        }
        $output->writeln($this->messagesHelper->success_message('Operation Complete'));
    }

    public function pdf(InputInterface $input, OutputInterface $output) {
        $this->directoryHelper->init($input->getOption('lang'), 'pdf');

        $file = sprintf('%s/%s-%s-pdf.pdf', $this->directoryHelper->get_localized_dir('pdf', $input->getOption('lang')), $input->getOption('lang'), time());
        $directory = $this->directoryHelper->get_localized_dir('screenshots', $input->getOption('lang'));
        $iterator = new DirectoryIterator($directory);

        /**
         * @var $pdf \TCPDF
         */
        $pdf = new $this->tcpdf('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Renegade Digital Media Inc.');
        $pdf->SetAuthor('Renegade Digital Media Inc.');
        $pdf->SetTitle(sprintf('%s-%s', $input->getOption('lang'), time()));
        $pdf->setPrintFooter(false);
        $pdf->setPrintHeader(false);
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->setJPEGQuality(75);

        foreach($iterator as $fileInfo) {
            /**
             * @var $fileInfo DirectoryIterator
             */
            if($fileInfo->getExtension()=='png') {
                $pdf->AddPage();
                $pdf->SetAutoPageBreak(false, 0);
                $pdf->Image(sprintf('%s/%s', $fileInfo->getPathInfo()->getRealPath(), $fileInfo->getFilename()) , 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
                $pdf->SetAutoPageBreak($pdf->getAutoPageBreak(), $pdf->getBreakMargin());
                $pdf->setPageMark();
                $pdf->writeHTML('&nbsp;', true, false, true, false, '');

                $output->writeln($this->messagesHelper->success_message(sprintf('  Image: %s added to pdf!', $fileInfo->getFilename())));
            }
        }

        $output->writeln($this->messagesHelper->success_message(sprintf('Saving PDF to file %s', $file)));
        $pdf->Output($file , 'F');

        if(filesize($file)) {
            $output->writeln($this->messagesHelper->success_message('Operation Successful!'));
        }
    }

    public function package(InputInterface $input, OutputInterface $output) {
        $this->directoryHelper->init($input->getOption('lang'), 'dist');

        $output->writeln($this->messagesHelper->success_message(sprintf("* Scanning for template files matching language type: %s", $input->getOption('lang'))));
        $iterator = new RecursiveDirectoryIterator($this->directoryHelper->get_localized_dir('build', $input->getOption('lang')));
        $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);

        $zip = new ArchiveHelper();

        /**
         * @var $progress ProgressHelper
         */
        $progress = $this->console->getHelperSet()->get('progress');
        $progress->setFormat(ProgressHelper::FORMAT_VERBOSE_NOMAX);
        $progress->start($output);

        foreach($iterator as $directory) {
            /**
             * @var $directory DirectoryIterator
             */
            $zip->open(sprintf('%s/%s.zip', $this->directoryHelper->get_localized_dir('dist', $input->getOption('lang')), $directory->getFilename()), ArchiveHelper::CREATE);
            $zip->folderToZip($directory->getRealPath(), $zip);
            $zip->close();
            $progress->advance();
        }
        $progress->finish();
        $output->writeln($this->messagesHelper->success_message('Operation Successful!'));
    }

} 
