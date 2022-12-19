<?php

namespace AbraFlexi\Mailer;

use AbraFlexi\Formats;
use AbraFlexi\Priloha;
use AbraFlexi\RO;
use AbraFlexi\ui\CompanyLogo;
use Ease\Functions;
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
 * @copyright  (G) 2021 Vitex Software
 */
class Mailer extends HtmlMailer
{
    /**
     *
     * @var RO Mostly invoice
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
     * Send Document by mail
     *
     * @param RO $document AbraFlexi document object
     * @param string $sendTo recipient
     * @param string $subject
     */
    public function __construct(
        RO $document, string $sendTo = null, string $subject = null
    )
    {
        $this->document = $document;

        $this->fromEmailAddress = Functions::cfg('MAIL_FROM');

        if (boolval(Functions::cfg('MUTE'))) {
            $sendTo = Functions::cfg('EASE_MAILTO');
        } else {
            if (empty($sendTo)) {
                $sendTo = $this->document->getEmail();
            }
        }

        if (empty($subject)) {
            $subject = $this->document->getEvidence().' '.\AbraFlexi\RO::uncode($document->getRecordCode());
        }

        parent::__construct($sendTo, $subject);

        if (Functions::cfg('MAIL_CC')) {
            $this->setMailHeaders(['Bcc' => Functions::cfg('MAIL_CC')]);
        }

        if (file_exists($this->templateFile())) {
            $this->htmlDocument = new Templater($document, $this->templateFile());
//            $this->htmlBody = $this->htmlDocument->body;
        } else {
            $this->htmlDocument = new HtmlTag(new SimpleHeadTag([
                    new TitleTag($this->emailSubject),
                    '<style>'.Mailer::$styles.'</style>']));
            $this->htmlBody = $this->htmlDocument->addItem(new BodyTag());

            if (array_key_exists('poznam', $this->document->getColumnsInfo())) {
                preg_match_all(
                    '/cc:[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/i',
                    $document->getDataValue('poznam'), $ccs
                );
                if (!empty($ccs[0])) {
                    $this->setMailHeaders(['Cc' => str_replace(
                            'cc:', '', implode(',', $ccs[0])
                    )]);
                }
            }

            if (array_key_exists('popis', $document->getColumnsInfo())) {
                $this->addItem(new \Ease\Html\PTag($document->getDataValue('popis')));
            }

            $this->addInvoice();
        }
    }

    public function templateFile()
    {
        return $this->templateDir.'/'.$this->document->getEvidence().'.ftl';
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
                'pdf', sys_get_temp_dir().'/'
            ), Formats::$formats['PDF']['content-type']
        );
        $this->addFile(
            $this->document->downloadInFormat(
                'isdocx', sys_get_temp_dir().'/'
            ), Formats::$formats['ISDOCx']['content-type']
        );

        $heading = new DivTag($this->document->getEvidence().' '.RO::uncode($this->document->getRecordIdent()));

        if (Functions::cfg('ADD_LOGO')) {
            $this->addCompanyLogo($heading);
        } else {
            $this->addItem($heading);
        }
    }

    /**
     *
     * @param type $heading
     * @param type $width
     */
    public function addCompanyLogo($heading, $width = 200)
    {
        $headingTableRow = new TrTag();
        $headingTableRow->addItem(new TdTag($heading));
        $logo = new CompanyLogo(['align' => 'right',
            'id' => 'companylogo',
            'height' => '50', 'title' => _('Company logo')]);
        $headingTableRow->addItem(new TdTag(
                $logo, ['width' => $width.'px']
        ));
        $headingTable = new TableTag(
            $headingTableRow, ['width' => '100%']
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
                    $this->document->getQrCodeBase64(200), _('QR Payment'),
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
                    $tmpfile = sys_get_temp_dir().'/'.$attachment['nazSoub'];
                    $this->addFile($tmpfile, $attachment['contentType']);
                    $attached[$attachmentID] = $attachment['nazSoub'];
                }
            }
        }
        return $attached;
    }

    /**
     * 
     *
     * @param type $filename
     * @param type $mimeType
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
}