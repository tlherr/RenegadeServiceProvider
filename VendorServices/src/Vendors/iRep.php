<?php

namespace Renegade\VendorServices\Vendors;

use Renegade\VendorServices\RenegadeServiceInterface;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class iRep implements RenegadeServiceInterface {

    protected $input;
    protected $output;

    public function __construct(InputInterface $input, OutputInterface $output) {
        $this->input = $input;
        $this->output = $output;
    }

    public function build() {
        init($app, $input->getOption('lang'), 'build');

        $this->output->writeln(success_message(sprintf("* Scanning for template files matching language type: %s", $input->getOption('lang'))));
        $directory = new \RecursiveDirectoryIterator(sprintf('%s/%s/pages', $app['twig.path'], $input->getOption('lang')));
        $iterator = new \RecursiveIteratorIterator($directory);
        $iterator->setFlags(\RecursiveDirectoryIterator::SKIP_DOTS);

        /**
         * @var $progress ProgressHelper
         */
        $progress = $console->getHelperSet()->get('progress');
        $progress->setFormat(ProgressHelper::FORMAT_VERBOSE_NOMAX);
        $progress->start($this->output);

        /**
         * @var $fs Filesystem
         */
        $fs = $app['filesystem'];

        foreach($iterator as $template_file) {
            /**
             * @var $template_file DirectoryIterator
             */

            $sub_dir = sprintf('%s/%s', get_localized_dir($app, 'build', $input->getOption('lang')), $template_file->getBasename('.html.twig'));
            $fs->mkdir($sub_dir);

            if(save_page(
                $app['twig']->render(sprintf('/%s/pages/%s', $input->getOption('lang'), $template_file->getFileInfo()->getFilename()), array(
                        'is_relative' => $input->getOption('assets_relative'),
                        'page_number' => preg_match('/slide([\d]+)/', $template_file->getFileInfo()->getFilename()),
                        'prefix' => sprintf('%s_remicade_derm_indication_slide', strtolower($input->getOption('lang')))
                    )
                ),
                $sub_dir,
                $template_file->getBasename('.twig'))
            ) {
                $progress->advance();
            }

            $this->output->writeln(PHP_EOL.success_message(sprintf('Searching document: [%s] for resources', $template_file->getFilename())));
            $doc = new DOMDocument();
            $doc->loadHTMLFile(sprintf('%s/%s', $sub_dir, $template_file->getBasename('.twig')));

            /**
             * Copy CSS
             */
            $fs->mkdir(sprintf('%s/%s', $sub_dir, 'css'));
            $linkTags = $doc->getElementsByTagName('link');
            $this->output->writeln(notification_message(sprintf('Found %s css resources', count($linkTags))));
            foreach($linkTags as $tag) {
                /**
                 * @var $tag DOMElement
                 */
                $this->output->writeln(notification_message(sprintf('Moving %s from %s to %s/%s', $tag->getAttribute('href'), __DIR__, $sub_dir, $tag->getAttribute('href'))));
                $fs->copy(sprintf('%s/%s', __DIR__, $tag->getAttribute('href')), sprintf('%s/%s', $sub_dir, $tag->getAttribute('href')));
            }

            /**
             * Copy JS
             */
            $fs->mkdir(sprintf('%s/%s', $sub_dir, 'js'));
            $scriptTags = $doc->getElementsByTagName('script');
            $this->output->writeln(notification_message(sprintf('Found %s js resources', count($scriptTags))));

            foreach($scriptTags as $tag) {
                /**
                 * @var $tag DOMElement
                 */
                $this->output->writeln(notification_message(sprintf('Moving %s from %s to %s/%s', $tag->getAttribute('src'), __DIR__, $sub_dir, $tag->getAttribute('src'))));
                $fs->copy(sprintf('%s/%s', __DIR__, $tag->getAttribute('src')), sprintf('%s/%s', $sub_dir, $tag->getAttribute('src')));
            }

            /**
             * Copy Fonts
             */
            $fs->mkdir(sprintf('%s/%s', $sub_dir, 'fonts'));
            $fs->mirror(sprintf('%s/%s', __DIR__, 'fonts'), sprintf('%s/%s', $sub_dir, 'fonts'));

            /**
             * Copy Images
             */
            $fs->mkdir(sprintf('%s/%s', $sub_dir, 'img'));
            $imageTags = $doc->getElementsByTagName('img');
            $this->output->writeln(notification_message(sprintf('Found %s img resources', count($imageTags))));

            foreach($imageTags as $tag) {
                /**
                 * @var $tag DOMElement
                 */
                $this->output->writeln(notification_message(sprintf('Moving %s from %s to %s/%s', $tag->getAttribute('src'), __DIR__, $sub_dir, $tag->getAttribute('src'))));
                $fs->copy(sprintf('%s/%s', __DIR__, $tag->getAttribute('src')), sprintf('%s/%s', $sub_dir, $tag->getAttribute('src')));
            }

            $fs->copy(sprintf('%s/img/header.jpg', __DIR__), sprintf('%s/img/header.jpg', $sub_dir));
            $fs->copy(sprintf('%s/img/footer.png', __DIR__), sprintf('%s/img/footer.png', $sub_dir));
        }

        $progress->finish();
        $this->output->writeln(success_message('Build Completed'));
    }

    public function screenshot() {
        init($app, $input->getOption('lang'), 'screenshots');

        $directory = new RecursiveDirectoryIterator(sprintf('%s/%s/pages', $app['twig.path'], $input->getOption('lang')));
        $iterator = new RecursiveIteratorIterator($directory);
        $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);

        $baseUrl =  $app['config']['application']['url'];
        /**
         * @var $progress ProgressHelper
         */
        $progress = $console->getHelperSet()->get('progress');
        $progress->setFormat(ProgressHelper::FORMAT_VERBOSE_NOMAX);
        $progress->start($this->output);

        if(!isDomainAvailible(sprintf( 'http://%s', $baseUrl))) {
            throw new Exception('URL Unavailable to screenshot');
        }

        foreach($iterator as $fileInfo) {
            /**
             * @var $fileInfo DirectoryIterator
             */
            $client = Client::getInstance();
            $client->setPhantomJs($app['config']['application']['phantomjs_bin']);

            /**
             * @see JonnyW\PhantomJs\Message\Request
             **/
            $request = $client->getMessageFactory()->createRequest('GET', sprintf( 'http://%s/screenshot/%s/%s', $baseUrl, $input->getOption('lang'), $fileInfo->getFilename()));

            /**
             * @see JonnyW\PhantomJs\Message\Response
             **/
            $response = $client->getMessageFactory()->createResponse();
            $client->send($request, $response, sprintf('%s/%s.png', get_localized_dir($app, 'screenshots', $input->getOption('lang')), $fileInfo->getBasename('.html')));
            $progress->advance();
        }
        $progress->finish();
    }

    public function pdf() {
        init($app, $input->getOption('lang'), 'pdf');

        $file = sprintf('%s/%s-%s-pdf.pdf', get_localized_dir($app, 'pdf', $input->getOption('lang')), $input->getOption('lang'), time());
        $directory = get_localized_dir($app, 'screenshots', $input->getOption('lang'));
        $iterator = new DirectoryIterator($directory);

        /**
         * @var $progress ProgressHelper
         */
        $progress = $console->getHelperSet()->get('progress');
        $progress->setFormat(ProgressHelper::FORMAT_VERBOSE_NOMAX);
        $progress->start($this->output);

        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Renegade Digital Media Inc.');
        $pdf->SetAuthor('Renegade Digital Media Inc.');
        $pdf->SetTitle(sprintf('%s-%s', $input->getOption('lang'), time()));
        $pdf->SetSubject(sprintf('%s-%s', APP_NAME, APP_VERSION));
        $pdf->SetKeywords(APP_NAME);

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

                $this->output->writeln(success_message(sprintf('  Image: %s added to pdf!', $fileInfo->getFilename())));
                $progress->advance();
            }
        }

        $progress->finish();
        $this->output->writeln(success_message(sprintf('Saving PDF to file %s', $file)));
        $pdf->Output($file , 'F');

        if(filesize($file)) {
            $this->output->writeln(success_message('Operation Successful!'));
        }
    }

    public function package() {
        init($app, $input->getOption('lang'), 'dist');

        $this->output->writeln(success_message(sprintf("* Scanning for template files matching language type: %s", $input->getOption('lang'))));
        $iterator = new RecursiveDirectoryIterator(get_localized_dir($app, 'build', $input->getOption('lang')));
        $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);

        /**
         * @var $progress ProgressHelper
         */
        $progress = $console->getHelperSet()->get('progress');
        $progress->setFormat(ProgressHelper::FORMAT_VERBOSE_NOMAX);
        $progress->start($this->output);

        $zip = new ZipArchive();

        foreach($iterator as $directory) {
            /**
             * @var $directory DirectoryIterator
             */
            $zip->open(sprintf('%s/%s.zip', get_localized_dir($app, 'dist', $input->getOption('lang')), $directory->getFilename()), ZipArchive::CREATE);
            folderToZip($directory->getRealPath(), $zip);
            $zip->close();

            $progress->advance();
        }
        $progress->finish();
    }

} 