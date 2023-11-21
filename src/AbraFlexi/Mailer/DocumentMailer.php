<?php

namespace AbraFlexi\Mailer;

use AbraFlexi\Formats;
use AbraFlexi\Priloha;
use AbraFlexi\RO;
use AbraFlexi\ui\CompanyLogo;
use Ease\Shared;
use Ease\Html\BodyTag;
use Ease\Html\DivTag;
use Ease\Html\HtmlTag;
use Ease\Html\ImgTag;
use Ease\Html\SimpleHeadTag;
use Ease\Html\TableTag;
use Ease\Html\TdTag;
use Ease\Html\TitleTag;
use Ease\Html\TrTag;
use Ease\HtmlMailer;

/**
 * AbraFlexi Mailer class
 *
 * @author     Vítězslav Dvořák <info@vitexsofware.cz>
 * @copyright  (G) 2021-2023 Vitex Software
 */
class DocumentMailer extends HtmlMailer
{
    /**
     *
     * @var \AbraFlexi\FakturaVydana Mostly invoice
     */
    private $document;

    /**
     *
     * @var array attachment's temporary files to delete
     */
    private $cleanup = [];

    /**
     *
     * @var string additional css
     */
    public static $styles = '';

    /**
     * Where to look for templates
     * @var string
     */
    private $templateDir = '../templates';

    /**
     *
     * @var \AbraFlexi\SablonaMail
     */
    private $templater = null;

    /**
     * Obtained Templates cache
     * @var array
     */
    public $templates = [];

    /**
     * Send Document by mail
     *
     * @param RO     $document AbraFlexi document object
     * @param string $sendTo recipient
     * @param string $subject
     */
    public function __construct(
        RO $document,
        string $sendTo = null,
        string $subject = null
    ) {
        $this->document = $document;
        $this->fromEmailAddress = Shared::cfg('MAIL_FROM');
        if (boolval(Shared::cfg('MUTE'))) {
            $sendTo = Shared::cfg('EASE_MAILTO');
            $this->addStatusMessage(sprintf(_('Mute mode: SendTo forced: %s'), $sendTo), 'debug');
        } else {
            if (empty($sendTo) && method_exists($this->document, 'getEmail')) {
                $sendTo = $this->document->getRecipients();
            } else {
                $this->addStatusMessage(\Ease\Logger\Message::getCallerName($this->document) . ' does not have getEmail method', 'warning');
            }
        }

        if (empty($subject)) {
            $subject = $this->document->getEvidence() . ' ' . \AbraFlexi\RO::uncode($document->getRecordCode());
        }

        parent::__construct($sendTo, $subject);
        if (Shared::cfg('MAIL_CC')) {
            $this->setMailHeaders(['Bcc' => Shared::cfg('MAIL_CC')]);
        }

        $abraFlexiTemplate = $this->getAbraFlexiTemplate($document);
        if ($abraFlexiTemplate) {
            $this->htmlDocument = new Templater($abraFlexiTemplate, $document);
        } elseif (file_exists($this->templateFile())) {
            $this->htmlDocument = new Templater(file_get_contents($this->templateFile()), $document);
//            $this->htmlBody = $this->htmlDocument->body;
        } else {
            $this->htmlDocument = new HtmlTag(new SimpleHeadTag([
                        new TitleTag($this->emailSubject),
                        '<style>' . DocumentMailer::$styles . '</style>']));
            $this->htmlBody = $this->htmlDocument->addItem(new BodyTag());
            if (array_key_exists('poznam', $this->document->getColumnsInfo())) {
                preg_match_all(
                    '/cc:[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/i',
                    $document->getDataValue('poznam'),
                    $ccs
                );
                if (!empty($ccs[0])) {
                    $this->setMailHeaders(['Cc' => str_replace(
                        'cc:',
                        '',
                        implode(',', $ccs[0])
                    )]);
                }
            }
        }
        if (array_key_exists('popis', $document->getColumnsInfo())) {
            $this->addItem(new \Ease\Html\PTag($document->getDataValue('popis')));
        }

        $this->addInvoice();
    }

    /**
     * Template File name
     *
     * @return string
     */
    public function templateFile()
    {
        return $this->templateDir . '/' . $this->document->getEvidence() . '.ftl';
    }

    /**
     * Přidá položku do těla mailu.
     *
     * @param mixed $item EaseObjekt nebo cokoliv s metodou draw();
     *
     * @return Ease\pointer|null ukazatel na vložený obsah
     */
    public function &addItem($item, $pageItemName = null)
    {
        $mailBody = '';
        if (is_object($item)) {
            if (is_object($this->htmlDocument)) {
                if (is_null($this->htmlBody)) {
                    $this->htmlBody = new BodyTag();
                }
                $mailBody = $this->htmlBody->addItem($item, $pageItemName);
            } else {
                $mailBody = $this->htmlDocument;
            }
        } else {
            $this->textBody .= is_array($item) ? implode("\n", $item) : $item;
            $this->mimer->setTXTBody($this->textBody);
        }

        return $mailBody;
    }

    public function getCss()
    {
    }

    /**
     * Count current mail size
     *
     * @return int Size in bytes
     */
    public function getCurrentMailSize()
    {
        $this->finalize();
        $this->finalized = false;
        if (
                function_exists('mb_internal_encoding') &&
                (((int) ini_get('mbstring.func_overload')) & 2)
        ) {
            return mb_strlen($this->mailBody, '8bit');
        } else {
            return strlen($this->mailBody);
        }
    }

    /**
     * Attach PDF and IsDOCx
     */
    public function addInvoice()
    {
        $this->addFile(
            $this->document->downloadInFormat(
                'pdf',
                sys_get_temp_dir() . '/'
            ),
            Formats::$formats['PDF']['content-type']
        );
        $this->addFile(
            $this->document->downloadInFormat(
                'isdocx',
                sys_get_temp_dir() . '/'
            ),
            Formats::$formats['ISDOCx']['content-type']
        );
        $heading = new DivTag($this->document->getEvidence() . ' ' . RO::uncode($this->document->getRecordIdent()));
        if (Shared::cfg('ADD_LOGO')) {
            $this->addCompanyLogo($heading);
        } else {
            $this->addItem($heading);
        }
    }

    /**
     * Add Company Logo into mail
     *
     * @param string $heading
     * @param int $width
     */
    public function addCompanyLogo($heading, $width = 200)
    {
        $headingTableRow = new TrTag();
        $headingTableRow->addItem(new TdTag($heading));
        $logo = new CompanyLogo(['align' => 'right',
            'id' => 'companylogo',
            'height' => '50', 'title' => _('Company logo')]);
        $headingTableRow->addItem(new TdTag(
            $logo,
            ['width' => $width . 'px']
        ));
        $headingTable = new TableTag(
            $headingTableRow,
            ['width' => '100%']
        );
        $this->addItem($headingTable);
    }

    /**
     * Add QR Payment image
     *
     * @param int $size
     */
    public function addQrCode($size = 200)
    {
        try {
            $this->addItem(new ImgTag(
                $this->document->getQrCodeBase64(200),
                _('QR Payment'),
                ['width' => $size, 'height' => $size, 'title' => $this->document->getRecordCode()]
            ));
        } catch (\AbraFlexi\Exception $exc) {
            $this->addStatusMessage(_('Error adding QR Code'), 'error');
        }
    }

    /**
     *
     * @return array
     */
    public function addAttachments()
    {
        $attachments = Priloha::getAttachmentsList($this->document);
        $attached = [];
        if ($attachments) {
            foreach ($attachments as $attachmentID => $attachment) {
                if (Priloha::saveToFile($attachmentID, sys_get_temp_dir())) {
                    $tmpfile = sys_get_temp_dir() . '/' . $attachment['nazSoub'];
                    $this->addFile($tmpfile, $attachment['contentType']);
                    $attached[$attachmentID] = $attachment['nazSoub'];
                }
            }
        }
        return $attached;
    }

    /**
     * Add File attachment into mail
     *
     * @param string $filename
     * @param string $mimeType
     *
     * @return success
     */
    public function addFile($filename, $mimeType = 'text/plain')
    {
        $this->cleanup[] = $filename;
        return parent::addFile($filename, $mimeType);
    }

    /**
     * Send message
     *
     * @return boolean
     */
    public function send()
    {
        $result = parent::send();
        foreach ($this->cleanup as $tmp) {
            unlink($tmp);
        }
        return $result;
    }

    /**
     * Get Teplate stored in AbraFlexi
     *
     * @param \AbraFlexi\RO $document
     *
     * @return string
     */
    public function getAbraFlexiTemplate($document)
    {
        $template = [];
        $typDoklInfo = $document->getColumnInfo('typDokl');
        if (array_key_exists('fkEvidencePath', $typDoklInfo)) {
            $typDokl = new \AbraFlexi\RW($document->getDataValue('typDokl'), ['evidence' => $typDoklInfo['fkEvidencePath']]);
            $myTemplate = $typDokl->getDataValue('sablonaMail');
            if (empty($myTemplate->value) === false) {
                $evidence = $document->getEvidence();
                if (array_key_exists($evidence, \AbraFlexi\EvidenceList::$evidences)) {
                    if (array_key_exists('beanKey', \AbraFlexi\EvidenceList::$evidences[$evidence])) {
                        $beanKey = \AbraFlexi\EvidenceList::$evidences[$evidence]['beanKey'];
                        if (is_null($this->templater) === true) {
                            $this->templater = new \AbraFlexi\SablonaMail(null, ['ignore404' => true]);
                        }

                        if (array_key_exists($beanKey, $this->templates) === false) {
                            $candidates = $this->templater->getColumnsFromAbraFlexi('*', ['beanKeys' => $beanKey], 'kod');
                            foreach ($candidates as $candidat) {
                                if (array_key_exists($beanKey, $this->templates) === false) {
                                    $this->templates[$candidat['kod']] = $candidat;
                                }
                            }
                            if (array_key_exists(\AbraFlexi\RO::uncode($myTemplate), $candidates)) {
                                $template = $candidates[\AbraFlexi\RO::uncode($myTemplate)];
                            }
                        }
                    }
                }
            }
        }
        return $template;
    }

    /**
     * Object To Contact Role
     *
     * @param \AbraFlexi\RO $document
     *
     * @return string Contact role Fak|Obj|Nab|Ppt|Skl|Pok or ''
     */
    public function docmentToRole($document)
    {
        switch (get_class($document)) {
            case 'AbraFlexi\\FakturaPrijata':
            case 'AbraFlexi\\FakturaPrijataPolozka':
            case 'AbraFlexi\\FakturaVydana':
            case 'AbraFlexi\\FakturaVydanaPolozka':
                $role = 'Fa';
                break;
            case 'AbraFlexi\\ObjednavkaPrijata':
            case 'AbraFlexi\\ObjednavkaPrijataPolozka':
            case 'AbraFlexi\\ObjednavkaVydana':
            case 'AbraFlexi\\ObjednavkaVydanaPolozka':
                $role = 'Obj';
                break;
            case 'AbraFlexi\\NabidkaVydana':
            case 'AbraFlexi\\NabidkaVydanaPolozka':
            case 'AbraFlexi\\NabidkaPrijata':
            case 'AbraFlexi\\NabidkaPrijataPolozka':
                $role = 'Nab';
                break;
            case 'AbraFlexi\\PoptavkaVydana':
            case 'AbraFlexi\\PoptavkaVydanaPolozka':
            case 'AbraFlexi\\PoptavkaPrijata':
            case 'AbraFlexi\\PoptavkaPrijataPolozka':
                $role = 'Ppt';
                break;
            case 'AbraFlexi\\SkladovaKarta':
            case 'AbraFlexi\\SkladovyPohyb':
                $role = 'Skl';
                break;
            case 'AbraFlexi\\Pokladna':
            case 'AbraFlexi\\PokladniPohyb':
                $role = 'Pok';
                break;
            default:
                $role = '';
                break;
        }
        return $role;
    }
}
