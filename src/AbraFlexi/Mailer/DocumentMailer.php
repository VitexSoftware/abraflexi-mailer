<?php

declare(strict_types=1);

/**
 * This file is part of the Mailer for AbraFlexi package
 *
 * https://github.com/VitexSoftware/abraflexi-mailer
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AbraFlexi\Mailer;

use AbraFlexi\Document;
use AbraFlexi\Formats;
use AbraFlexi\Priloha;
use AbraFlexi\SablonaMail;
use Ease\Html\BodyTag;
use Ease\Html\DivTag;
use Ease\Html\HtmlTag;
use Ease\Html\ImgTag;
use Ease\Html\SimpleHeadTag;
use Ease\Html\TableTag;
use Ease\Html\TdTag;
use Ease\Html\TitleTag;
use Ease\Html\TrTag;
use Ease\Shared;

/**
 * AbraFlexi Mailer class.
 *
 * @author     Vítězslav Dvořák <info@vitexsofware.cz>
 * @copyright  (G) 2021-2026 Vitex Software
 */
class DocumentMailer extends HtmlMailer
{
    /**
     * @var string additional css
     */
    public static string $styles = '';

    /**
     * Obtained Templates cache.
     */
    public array $templates = [];
    public bool $muted = false;

    /**
     * @var \AbraFlexi\Document Mostly invoice
     */
    private Document $document;

    /**
     * @var array attachment's temporary files to delete
     */
    private array $cleanup = [];

    /**
     * Private temporary directory of this message (downloaded PDF, ISDOC and attachments).
     */
    private string $workDir = '';

    /**
     * Where to look for templates.
     */
    private string $templateDir = '../templates';
    private ?SablonaMail $templater = null;

    /**
     * Send Document by mail.
     *
     * @param Document $document AbraFlexi document object
     * @param string   $sendTo   recipient
     */
    public function __construct(
        Document $document,
        ?string $sendTo = null,
        ?string $subject = null,
    ) {
        $this->document = $document;
        $this->fromEmailAddress = Shared::cfg('MAIL_FROM');
        $this->setObjectName();

        if (Config::bool('MUTE')) {
            $sendTo = Shared::cfg('EASE_MAILTO', '');
            $this->muted = true;
            $this->addStatusMessage(sprintf(_('Mute mode: SendTo forced: %s'), $sendTo), 'warning');
        } else {
            if (empty($sendTo) && method_exists($this->document, 'getEmail')) {
                $sendTo = $this->document->getRecipients();
            } else {
                $this->addStatusMessage(sprintf(_('%s does not have getEmail method'), \Ease\Logger\Message::getCallerName($this->document)), 'warning');
            }
        }

        if (empty($subject)) {
            $subject = $this->document->getEvidence().' '.\AbraFlexi\Functions::uncode((string) $document->getRecordCode());
        }

        parent::__construct($sendTo, $subject);

        if (Shared::cfg('MAIL_CC')) {
            $this->setMailHeaders(['Bcc' => Shared::cfg('MAIL_CC')]);
        }

        $abraFlexiTemplate = $this->getAbraFlexiTemplate($document);

        if ($abraFlexiTemplate) {
            $this->htmlDocument = new Templater($abraFlexiTemplate['textSablona'], $document);
        } elseif (file_exists($this->templateFile())) {
            $this->htmlDocument = new Templater(file_get_contents($this->templateFile()), $document);
            //            $this->htmlBody = $this->htmlDocument->body;
        } else {
            $this->htmlDocument = new Body(new HtmlTag(new SimpleHeadTag([
                new TitleTag($this->emailSubject),
                '<style>'.self::$styles.'</style>'])));
            $this->htmlBody = $this->htmlDocument->addItem(new BodyTag());

            if (\array_key_exists('poznam', $this->document->getColumnsInfo())) {
                preg_match_all(
                    '/cc:[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/i',
                    $document->getDataValue('poznam'),
                    $ccs,
                );

                if (!empty($ccs[0])) {
                    $this->setMailHeaders(['Cc' => str_replace(
                        'cc:',
                        '',
                        implode(',', $ccs[0]),
                    )]);
                }
            }
        }

        if (\array_key_exists('popis', $document->getColumnsInfo())) {
            $this->addItem(new \Ease\Html\PTag($document->getDataValue('popis')));
        }

        // __destruct() is not called when a constructor throws: clean up here
        $this->workDir = self::createWorkDir();

        try {
            $this->addInvoice();
        } catch (\Throwable $exc) {
            $this->cleanup();

            throw $exc;
        }

        $this->addItem(new \Ease\Html\HrTag());
        $this->addItem(new \Ease\Html\PTag(_('Send by').' '.\Ease\Shared::appName().' '.\Ease\Shared::appVersion()));
    }

    /**
     * Template File name.
     *
     * @return string
     */
    public function templateFile()
    {
        return $this->templateDir.'/'.$this->document->getEvidence().'.ftl';
    }

    /**
     * Přidá položku do těla mailu.
     *
     * @param mixed      $item         EaseObjekt nebo cokoliv s metodou draw();
     * @param null|mixed $pageItemName
     *
     * @return null|Ease\pointer ukazatel na vložený obsah
     */
    public function &addItem($item, $pageItemName = null)
    {
        if (null === $this->htmlBody) {
            $this->htmlBody = new BodyTag();
        }

        if (!\is_object($item)) {
            $item = \is_array($item) ? implode("\n", $item) : (string) $item;
        }

        $added = $this->htmlBody->addItem($item, $pageItemName);

        return $added;
    }

    public function getCss(): void
    {
    }

    /**
     * Attach PDF and IsDOCx.
     *
     * @throws DocumentIncompleteException when the PDF cannot be obtained
     */
    public function addInvoice(): void
    {
        $pdf = $this->document->downloadInFormat('pdf', $this->workDir.'/');

        // downloadInFormat() saves the response body even for an error response,
        // so verify we really got a PDF before it can reach a customer
        if (!$this->isDownloaded($pdf, '%PDF') || !$this->addFile($pdf, Formats::$formats['PDF']['content-type'])) {
            throw new DocumentIncompleteException(sprintf(_('Cannot obtain PDF of %s'), \AbraFlexi\Functions::uncode((string) $this->document->getRecordCode())));
        }

        $isdoc = $this->document->downloadInFormat('isdocx', $this->workDir.'/');

        if (!$this->isDownloaded($isdoc, 'PK') || !$this->addFile($isdoc, Formats::$formats['ISDOCx']['content-type'])) {
            $this->addStatusMessage(sprintf(_('Cannot obtain ISDOC of %s'), \AbraFlexi\Functions::uncode((string) $this->document->getRecordCode())), 'warning');
        }

        $heading = new DivTag($this->document->getEvidence().' '.\AbraFlexi\Functions::uncode((string) $this->document->getRecordCode()));

        if (Config::bool('ADD_LOGO')) {
            $this->addCompanyLogo($heading);
        } else {
            $this->addItem($heading);
        }
    }

    /**
     * Add Company Logo into mail.
     *
     * @param string $heading
     * @param int    $width
     */
    public function addCompanyLogo($heading, $width = 200): void
    {
        $headingTableRow = new TrTag();
        $headingTableRow->addItem(new TdTag($heading));
        $logo = new CompanyLogo(['align' => 'right',
            'id' => 'companylogo',
            'height' => '50', 'title' => _('Company logo')]);
        $headingTableRow->addItem(new TdTag(
            $logo,
            ['width' => $width.'px'],
        ));
        $headingTable = new TableTag(
            $headingTableRow,
            ['width' => '100%'],
        );
        $this->addItem($headingTable);
    }

    /**
     * Add QR Payment image.
     *
     * @param int $size
     */
    public function addQrCode($size = 200): void
    {
        try {
            $this->addItem(new ImgTag(
                $this->document->getQrCodeBase64(200),
                _('QR Payment'),
                ['width' => $size, 'height' => $size, 'title' => $this->document->getRecordCode()],
            ));
        } catch (\AbraFlexi\Exception $exc) {
            $this->addStatusMessage(_('Error adding QR Code'), 'error');
        }
    }

    /**
     * @return array
     */
    public function addAttachments()
    {
        $attached = [];
        $code = \AbraFlexi\Functions::uncode((string) $this->document->getRecordCode());

        try {
            $attachments = Priloha::getAttachmentsList($this->document);
        } catch (\AbraFlexi\Exception $exc) {
            throw new DocumentIncompleteException(sprintf(_('Cannot obtain attachments of %s: %s'), $code, $exc->getMessage()), 0, $exc);
        }

        foreach ($attachments as $attachmentID => $attachment) {
            $fileName = basename((string) $attachment['nazSoub']);
            $tmpfile = $this->workDir.'/'.$fileName;

            try {
                $saved = Priloha::saveToFile((int) $attachmentID, $tmpfile);
            } catch (\AbraFlexi\Exception $exc) {
                throw new DocumentIncompleteException(sprintf(_('Cannot download attachment %s of %s: %s'), $fileName, $code, $exc->getMessage()), 0, $exc);
            }

            if (!$saved || !$this->addFile($tmpfile, $attachment['contentType'])) {
                throw new DocumentIncompleteException(sprintf(_('Cannot download attachment %s of %s'), $fileName, $code));
            }

            $attached[$attachmentID] = $fileName;
        }

        return $attached;
    }

    /**
     * Add File attachment into mail.
     *
     * @param string $filename
     * @param string $mimeType
     *
     * @return success
     */
    public function addFile($filename, $mimeType = 'text/plain'): bool
    {
        $this->cleanup[] = $filename;

        return parent::addFile($filename, $mimeType);
    }

    /**
     * Send message.
     */
    public function send(): bool
    {
        $result = parent::send();
        $this->cleanup();

        return $result;
    }

    /**
     * Remove downloaded files and the private temporary directory.
     */
    public function cleanup(): void
    {
        foreach ($this->cleanup as $tmp) {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }

        $this->cleanup = [];

        if ($this->workDir !== '' && is_dir($this->workDir)) {
            foreach (glob($this->workDir.'/{,.}*', \GLOB_BRACE) ?: [] as $leftover) {
                if (is_file($leftover)) {
                    @unlink($leftover);
                }
            }

            @rmdir($this->workDir);
        }
    }

    public function __destruct()
    {
        // DRY_RUN or failed documents never reach send()
        $this->cleanup();
    }

    /**
     * Was the file downloaded successfully and does it start with the expected signature?
     */
    private function isDownloaded(?string $file, string $magic): bool
    {
        if (empty($file) || !is_file($file) || (int) $this->document->lastResponseCode !== 200) {
            if (!empty($file) && is_file($file)) {
                @unlink($file);
            }

            return false;
        }

        $handle = fopen($file, 'rb');
        $head = $handle ? (string) fread($handle, \strlen($magic)) : '';

        if ($handle) {
            fclose($handle);
        }

        if ($head !== $magic) {
            @unlink($file);

            return false;
        }

        return true;
    }

    /**
     * Create a private temporary directory, so concurrent runs or files left
     * by other users cannot collide with this message's downloads.
     */
    private static function createWorkDir(): string
    {
        $dir = sys_get_temp_dir().'/abraflexi-mailer-'.bin2hex(random_bytes(8));

        if (!mkdir($dir, 0700) && !is_dir($dir)) {
            throw new DocumentIncompleteException(sprintf(_('Cannot create temporary directory %s'), $dir));
        }

        return $dir;
    }

    /**
     * Get Template stored in AbraFlexi.
     *
     * @return string
     */
    public function getAbraFlexiTemplate(Document $document)
    {
        $template = [];
        $typDoklInfo = $document->getColumnInfo('typDokl');

        if (\array_key_exists('fkEvidencePath', $typDoklInfo)) {
            $typDokl = new \AbraFlexi\RW($document->getDataValue('typDokl'), ['evidence' => $typDoklInfo['fkEvidencePath']]);
            $myTemplate = $typDokl->getDataValue('sablonaMail');

            if (empty($myTemplate->value) === false) {
                $evidence = $document->getEvidence();

                if (\array_key_exists($evidence, \AbraFlexi\EvidenceList::$evidences)) {
                    if (\array_key_exists('beanKey', \AbraFlexi\EvidenceList::$evidences[$evidence])) {
                        $beanKey = \AbraFlexi\EvidenceList::$evidences[$evidence]['beanKey'];

                        if ((null === $this->templater) === true) {
                            $this->templater = new SablonaMail(null, ['ignore404' => true]);
                        }

                        if (\array_key_exists($beanKey, $this->templates) === false) {
                            $candidates = $this->templater->getColumnsFromAbraFlexi('*', ['beanKeys' => $beanKey], 'kod');

                            foreach ($candidates as $candidat) {
                                if (\array_key_exists($beanKey, $this->templates) === false) {
                                    $this->templates[$candidat['kod']] = $candidat;
                                }
                            }

                            if (\array_key_exists(\AbraFlexi\Functions::uncode((string) $myTemplate), $candidates)) {
                                $template = $candidates[\AbraFlexi\Functions::uncode((string) $myTemplate)];
                            }
                        }
                    }
                }
            }
        }

        return $template;
    }

    public function javaToApiMacros(): void
    {
        echo '${object.nazFirmy}',
        '${object}',
        '${object.bspBan.buc}',
        '${object.bspBan.smerKod}',
        '${object.mena.kod}',
        '${object.varSym}',
        '${object.nazFirmy}',
        '${object.sumCelkem}',
        '${object.mena.symbol}',
        '${object.datSplat}',
        '${object.varSym}',
        '${object.bspBan.buc}',
        '${object.bspBan.smerKod}';
    }

    public function isMuted(): bool
    {
        return $this->muted;
    }
}
