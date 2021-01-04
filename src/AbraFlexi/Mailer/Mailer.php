<?php

namespace AbraFlexi\Mailer;

use AbraFlexi\Formats;
use AbraFlexi\Priloha;
use AbraFlexi\RO;
use AbraFlexi\ui\CompanyLogo;
use Ease\Container;
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
use Ease\Shared;

/**
 * AbraFlexi Mailer class
 *
 * @author     Vítězslav Dvořák <info@vitexsofware.cz>
 * @copyright  (G) 2021 Vitex Software
 */
class Mailer extends HtmlMailer {

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
     * Send Document by mail
     * 
     * @param RO $document AbraFlexi document object
     * @param string $sendTo recipient
     * @param string $subject
     */
    public function __construct(RO $document, string $sendTo = null, string $subject = null) {
        $this->document = $document;

        $this->fromEmailAddress = Functions::cfg('MAIL_FROM');

        if (Functions::cfg('MUTE') === true) {
            $sendTo = Functions::cfg('EASE_MAILTO');
        } else {
            if (empty($sendTo)) {
                $sendTo = $this->document->getEmail();
            }
        }

        if (empty($subject)) {
            $subject = $this->document->getEvidence() . ' ' . \AbraFlexi\RO::uncode($document->getRecordCode());
        }

        parent::__construct($sendTo, $subject);

        if (Functions::cfg('MAIL_CC')) {
            $this->setMailHeaders(['Bcc' => Functions::cfg('MAIL_CC')]);
        }


        $this->htmlDocument = new HtmlTag(new SimpleHeadTag([
                    new TitleTag($this->emailSubject),
                    '<style>' . Mailer::$styles . '</style>']));
        $this->htmlBody = $this->htmlDocument->addItem(new BodyTag());
    }

    /**
     * Přidá položku do těla mailu.
     *
     * @param mixed $item EaseObjekt nebo cokoliv s metodou draw();
     *
     * @return Ease\pointer|null ukazatel na vložený obsah
     */
    public function &addItem($item, $pageItemName = null) {
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

    public function getCss() {
        
    }

    /**
     * Count current mail size
     *
     * @return int Size in bytes
     */
    public function getCurrentMailSize() {
        $this->finalize();
        $this->finalized = false;
        if (function_exists('mb_internal_encoding') &&
                (((int) ini_get('mbstring.func_overload')) & 2)) {
            return mb_strlen($this->mailBody, '8bit');
        } else {
            return strlen($this->mailBody);
        }
    }

    public function addInvoice() {
        $this->addFile($this->document->downloadInFormat('pdf',
                        '/tmp/'),
                Formats::$formats['PDF']['content-type']);
        $this->addFile($this->document->downloadInFormat('isdocx',
                        '/tmp/'),
                Formats::$formats['ISDOCx']['content-type']);

        $heading = new DivTag($this->document->getEvidence() . ' ' . RO::uncode($unsentData['kod']));

        if (Functions::cfg('ADD_LOGO')) {
            $this->addCompanyLogo($heading);
        } else {
            $this->addItem($heading);
        }
    }

    public function addCompanyLogo($heading, $width = 200) {
        $headingTableRow = new TrTag();
        $headingTableRow->addItem(new TdTag($heading));
        $logo = new CompanyLogo(['align' => 'right',
            'id' => 'companylogo',
            'height' => '50', 'title' => _('Company logo')]);
        $headingTableRow->addItem(new TdTag($logo,
                        ['width' => $width . 'px']));
        $headingTable = new TableTag($headingTableRow,
                ['width' => '100%']);
        $this->addItem($headingTable);
    }

    /**
     * Add QR Payment image
     * 
     * @param int $size
     */
    public function addQrCode($size = 200) {
        $this->addItem(new ImgTag($this->document->getQrCodeBase64(200),
                        _('QR Payment'),
                        ['width' => $size, 'height' => $size, 'title' => $this->document->getRecordCode()]));
    }

    /**
     * 
     * @return array
     */
    public function addAttachments() {
        $attachments = Priloha::getAttachmentsList($this->document);
        $attached = [];
        if ($attachments) {
            foreach ($attachments as $attachmentID => $attachment) {
                if (Priloha::saveToFile($attachmentID, sys_get_temp_dir())) {
                    $tmpfile = sys_get_temp_dir() . '/' . $attachment['nazSoub'];
                    $this->addFile($tmpfile, $attachment['contentType']);
                    $this->cleanup[] = $tmpfile;
                    $attached[$attachmentID] = $attachment['nazSoub'];
                }
            }
        }
        return $attached;
    }

    public function send() {
        $result = parent::send();
        foreach ($this->cleanup as $tmp) {
            unlink($tmp);
        }
        return $result;
    }

}
