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

    protected $input;
    protected $output;
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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $twig
     * @param \Symfony\Component\Filesystem\Filesystem $filesystem
     * @param \JonnyW\PhantomJs\Client $phantomJS
     * @param \TCPDF $tcpdf
     * @param $config
     * @param $directory                string      Application Path (console.php location)
     * @internal param $phantomJS
     */
    public function __construct(InputInterface $input, OutputInterface $output, $twig, Filesystem $filesystem, Client $phantomJS, \TCPDF $tcpdf, $config, $directory) {
        $this->input = $input;
        $this->output = $output;
        $this->twig = $twig;
        $this->filesystem = $filesystem;
        $this->phantomJS = $phantomJS;
        $this->tcpdf = $tcpdf;
        $this->config = $config;
        $this->directory = $directory;

        $this->archiveHelper = new ArchiveHelper();
        $this->directoryHelper = new DirectoryHelper($filesystem, $config);
        $this->messagesHelper = new MessagesHelper();
        $this->networkHelper = new NetworkHelper();
    }

    public function build() {
        $this->directoryHelper->init($this->input->getOption('lang'), 'build');

        $this->output->writeln($this->messagesHelper->success_message(sprintf("* Scanning for template files matching language type: %s", $this->input->getOption('lang'))));
        $directory = new RecursiveDirectoryIterator(sprintf('%s/views/%s/pages', $this->directory, $this->input->getOption('lang')));
        $iterator = new RecursiveIteratorIterator($directory);
        /**
         * @var $iterator RecursiveIteratorIterator
         */
        $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);

        foreach($iterator as $template_file) {
            /**
             * @var $template_file DirectoryIterator
             */

            $sub_dir = sprintf('%s/%s', $this->directoryHelper->get_localized_dir('build', $this->input->getOption('lang')), $template_file->getBasename('.html.twig'));
            $this->filesystem->mkdir($sub_dir);

            $this->directoryHelper->save_page(
                $this->twig->render(sprintf('/%s/pages/%s', $this->input->getOption('lang'), $template_file->getFileInfo()->getFilename()), array(
                        'is_relative' => $this->input->getOption('assets_relative'),
                        'page_number' => preg_match('/slide([\d]+)/', $template_file->getFileInfo()->getFilename()),
                        'prefix' => sprintf('%s_remicade_derm_indication_slide', strtolower($this->input->getOption('lang')))
                    )
                ),
                $sub_dir,
                $template_file->getBasename('.twig')
            );


            $this->output->writeln($this->messagesHelper->success_message(sprintf('Searching document: [%s] for resources', $template_file->getFilename())));
            $doc = new \DOMDocument();
            $doc->loadHTMLFile(sprintf('%s/%s', $sub_dir, $template_file->getBasename('.twig')));

            /**
             * Copy CSS
             */
            $this->filesystem->mkdir(sprintf('%s/%s', $sub_dir, 'css'));
            $linkTags = $doc->getElementsByTagName('link');
            $this->output->writeln($this->messagesHelper->notification_message(sprintf('Found %s css resources', count($linkTags))));
            foreach($linkTags as $tag) {
                /**
                 * @var $tag DOMElement
                 */
                $this->output->writeln($this->messagesHelper->notification_message(sprintf('Moving %s from %s to %s/%s', $tag->getAttribute('href'), $this->directory, $sub_dir, $tag->getAttribute('href'))));
                $this->filesystem->copy(sprintf('%s/%s', $this->directory, $tag->getAttribute('href')), sprintf('%s/%s', $sub_dir, $tag->getAttribute('href')));
            }

            /**
             * Copy JS
             */
            $this->filesystem->mkdir(sprintf('%s/%s', $sub_dir, 'js'));
            $scriptTags = $doc->getElementsByTagName('script');
            $this->output->writeln($this->messagesHelper->notification_message(sprintf('Found %s js resources', count($scriptTags))));

            foreach($scriptTags as $tag) {
                /**
                 * @var $tag DOMElement
                 */
                $this->output->writeln($this->messagesHelper->notification_message(sprintf('Moving %s from %s to %s/%s', $tag->getAttribute('src'), $this->directory, $sub_dir, $tag->getAttribute('src'))));
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
            $this->output->writeln($this->messagesHelper->notification_message(sprintf('Found %s img resources', count($imageTags))));

            foreach($imageTags as $tag) {
                /**
                 * @var $tag DOMElement
                 */
                $this->output->writeln($this->messagesHelper->notification_message(sprintf('Moving %s from %s to %s/%s', $tag->getAttribute('src'), $this->directory, $sub_dir, $tag->getAttribute('src'))));
                $this->filesystem->copy(sprintf('%s/%s', $this->directory, $tag->getAttribute('src')), sprintf('%s/%s', $sub_dir, $tag->getAttribute('src')));
            }

            $this->filesystem->copy(sprintf('%s/img/header.jpg', $this->directory), sprintf('%s/img/header.jpg', $sub_dir));
            $this->filesystem->copy(sprintf('%s/img/footer.png', $this->directory), sprintf('%s/img/footer.png', $sub_dir));
        }

        $this->output->writeln($this->messagesHelper->success_message('Build Completed'));
    }

    public function screenshot() {
        $this->directoryHelper->init($this->input->getOption('lang'), 'screenshots');

        $directory = new RecursiveDirectoryIterator(sprintf('%s/views/%s/pages', $this->directory, $this->input->getOption('lang')));
        $iterator = new RecursiveIteratorIterator($directory);
        $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);

        if(!$this->networkHelper->isDomainAvailible(sprintf( 'http://%s', $this->config['url']))) {
            throw new \Exception('URL Unavailable to screenshot');
        }

        foreach($iterator as $fileInfo) {
            /**
             * @var $fileInfo DirectoryIterator
             */
            $client = $this->phantomJS;
            $client->setPhantomJs($this->config['phantomjs_bin']);
            $request = $client->getMessageFactory()->createRequest('GET', sprintf( 'http://%s/screenshot/%s/%s', $this->config['url'], $this->input->getOption('lang'), $fileInfo->getFilename()));
            $response = $client->getMessageFactory()->createResponse();
            $client->send($request, $response, sprintf('%s/%s.png', $this->directoryHelper->get_localized_dir('screenshots', $this->input->getOption('lang')), $fileInfo->getBasename('.html')));
        }
        $this->output->writeln($this->messagesHelper->success_message('Operation Complete'));
    }

    public function pdf() {
        $this->directoryHelper->init($this->input->getOption('lang'), 'pdf');

        $file = sprintf('%s/%s-%s-pdf.pdf', $this->directoryHelper->get_localized_dir('pdf', $this->input->getOption('lang')), $this->input->getOption('lang'), time());
        $directory = $this->directoryHelper->get_localized_dir('screenshots', $this->input->getOption('lang'));
        $iterator = new DirectoryIterator($directory);

        /**
         * @var $pdf \TCPDF
         */
        $pdf = $this->tcpdf('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Renegade Digital Media Inc.');
        $pdf->SetAuthor('Renegade Digital Media Inc.');
        $pdf->SetTitle(sprintf('%s-%s', $this->input->getOption('lang'), time()));
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

                $this->output->writeln($this->messagesHelper->success_message(sprintf('  Image: %s added to pdf!', $fileInfo->getFilename())));
            }
        }

        $this->output->writeln($this->messagesHelper->success_message(sprintf('Saving PDF to file %s', $file)));
        $pdf->Output($file , 'F');

        if(filesize($file)) {
            $this->output->writeln($this->messagesHelper->success_message('Operation Successful!'));
        }
    }

    public function package() {
        $this->directoryHelper->init($this->input->getOption('lang'), 'dist');

        $this->output->writeln($this->messagesHelper->success_message(sprintf("* Scanning for template files matching language type: %s", $this->input->getOption('lang'))));
        $iterator = new RecursiveDirectoryIterator($this->directoryHelper->get_localized_dir('build', $this->input->getOption('lang')));
        $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);

        $zip = new ArchiveHelper();

        foreach($iterator as $directory) {
            /**
             * @var $directory DirectoryIterator
             */
            $zip->open(sprintf('%s/%s.zip', $this->directoryHelper->get_localized_dir('dist', $this->input->getOption('lang')), $directory->getFilename()), ArchiveHelper::CREATE);
            $zip->folderToZip($directory->getRealPath(), $zip);
            $zip->close();
        }
        $this->output->writeln($this->messagesHelper->success_message('Operation Successful!'));
    }

} 