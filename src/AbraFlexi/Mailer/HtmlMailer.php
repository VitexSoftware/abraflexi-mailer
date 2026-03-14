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

use Ease\Document;
use Ease\Html\BodyTag;
use Ease\Html\HtmlTag;
use Ease\Html\SimpleHeadTag;
use Ease\Html\TitleTag;
use Ease\Sand;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Build & Send email using Symfony Mailer.
 */
class HtmlMailer extends Sand
{
    /**
     * Sender's email address.
     */
    public string $emailAddress = '';

    /**
     * Subject of email.
     */
    public string $emailSubject = '';

    /**
     * Sender's email address.
     */
    public string $fromEmailAddress = '';

    /**
     * Show user information about sending a message?
     */
    public bool $notify = true;

    /**
     * Has the message already been sent?
     */
    public ?bool $sendResult = false;

    /**
     * Page object for rendering to email.
     */
    public $htmlDocument;
    public ?SimpleHeadTag $htmlHead = null;

    /**
     * Pointer to the BODY html document.
     */
    public $htmlBody;
    public array $mailHeaders = [];
    public bool $finalized = false;
    private Email $email;
    private Mailer $mailer;

    /**
     * @param string $emailAddress  address
     * @param string $mailSubject   subject
     * @param string $emailContents body
     * @param array  $headers       override Mail Headers
     */
    public function __construct(
        string $emailAddress,
        string $mailSubject,
        string $emailContents = '',
        array $headers = [],
    ) {
        if (\is_array($emailAddress)) {
            $emailAddress = current($emailAddress).' <'.key($emailAddress).'>';
        }

        $this->fromEmailAddress = \Ease\Shared::cfg('MAIL_FROM', \Ease\Shared::cfg('EMAIL_FROM', 'noreply@example.com'));

        $this->setMailHeaders(
            array_merge([
                'To' => $emailAddress,
                'From' => $this->fromEmailAddress,
                'Reply-To' => $this->fromEmailAddress,
                'Subject' => $mailSubject,
                'Content-Type' => 'text/html; charset=utf-8',
                'Content-Transfer-Encoding' => '8bit',
            ], $headers),
        );

        $this->htmlDocument = new HtmlTag();
        $this->htmlHead = $this->htmlDocument->addItem(new SimpleHeadTag(new TitleTag($this->emailSubject)));
        $this->htmlBody = $this->htmlDocument->addItem(new BodyTag($emailContents));

        $dsn = \Ease\Shared::cfg('MAIL_DSN', '');

        if (empty($dsn)) {
            $dsn = 'sendmail://default';
        }

        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
        $this->email = new Email();
    }

    /**
     * Returns the contents of the mail header.
     *
     * @param string $headername header name
     *
     * @return string
     */
    public function getMailHeader(string $headername)
    {
        return \array_key_exists($headername, $this->mailHeaders) ? $this->mailHeaders[$headername] : '';
    }

    /**
     * Sets mail headers.
     *
     * @param array $mailHeaders associative array of headers
     *
     * @return bool true if the headers have been set
     */
    public function setMailHeaders(array $mailHeaders): bool
    {
        $this->mailHeaders = array_merge($this->mailHeaders, $mailHeaders);

        if (isset($this->mailHeaders['To'])) {
            $this->emailAddress = $this->mailHeaders['To'];
        }

        if (isset($this->mailHeaders['From'])) {
            $this->fromEmailAddress = $this->mailHeaders['From'];
        }

        if (isset($this->mailHeaders['Subject'])) {
            $this->emailSubject = $this->mailHeaders['Subject'];

            // Remove base64 encoding prefix applied by Ease\HtmlMailer compatibility if we ever get it
            if (str_starts_with($this->emailSubject, '=?UTF-8?B?')) {
                $this->emailSubject = base64_decode(substr($this->emailSubject, 10, -2), true);
            }
        }

        $this->finalized = false;

        return true;
    }

    /**
     * Adds an item to the body of the mail.
     *
     * @param mixed $item EaseObject or anything with the draw (); method
     *
     * @return mixed pointer to the inserted content
     */
    public function &addItem($item)
    {
        $added = $this->htmlBody->addItem($item);

        return $added;
    }

    /**
     * Gives you current Body.
     *
     * @return BodyTag
     */
    public function getContents()
    {
        return $this->htmlBody;
    }

    /**
     * Obtain item count.
     */
    public function getItemsCount(): int
    {
        return $this->htmlBody->getItemsCount();
    }

    /**
     * Is object empty ?
     */
    public function isEmpty(): bool
    {
        return $this->htmlBody->isEmpty();
    }

    /**
     * Empty container contents.
     */
    public function emptyContents(): void
    {
        $this->htmlBody->emptyContents();
    }

    /**
     * Attaches an attachment from a file to the mail.
     *
     * @param null|string $filename path / file name to attach
     * @param string      $mimeType MIME attachment type
     *
     * @return bool file attachment successful
     */
    public function addFile(?string $filename, string $mimeType = 'text/plain'): bool
    {
        if ($filename !== null && file_exists($filename)) {
            $this->email->attachFromPath($filename, basename($filename), $mimeType ?: null);
        }

        return true;
    }

    /**
     * Builds the body of the mail.
     */
    public function finalize(): void
    {
        $htmlBodyRendered = '';

        if (method_exists($this->htmlDocument, 'GetRendered')) {
            $htmlBodyRendered = $this->htmlDocument->getRendered();
        } else {
            $htmlBodyRendered = (string) $this->htmlDocument;
        }

        $this->email->html($htmlBodyRendered);

        if (!empty($this->fromEmailAddress)) {
            $this->email->from(Address::create($this->fromEmailAddress));
        }

        if (!empty($this->emailAddress)) {
            foreach (explode(',', $this->emailAddress) as $address) {
                if (trim($address) !== '') {
                    $this->email->addTo(Address::create(trim($address)));
                }
            }
        }

        if (!empty($this->emailSubject)) {
            $this->email->subject($this->emailSubject);
        }

        if (isset($this->mailHeaders['Cc'])) {
            foreach (explode(',', $this->mailHeaders['Cc']) as $address) {
                if (trim($address) !== '') {
                    $this->email->addCc(Address::create(trim($address)));
                }
            }
        }

        if (isset($this->mailHeaders['Bcc'])) {
            foreach (explode(',', $this->mailHeaders['Bcc']) as $address) {
                if (trim($address) !== '') {
                    $this->email->addBcc(Address::create(trim($address)));
                }
            }
        }

        if (isset($this->mailHeaders['Reply-To'])) {
            $this->email->replyTo(Address::create($this->mailHeaders['Reply-To']));
        }

        $headers = $this->email->getHeaders();

        foreach ($this->mailHeaders as $headerName => $headerValue) {
            if (!\in_array(strtolower($headerName), ['to', 'from', 'subject', 'cc', 'bcc', 'reply-to', 'content-type', 'content-transfer-encoding', 'date'], true)) {
                $headers->addTextHeader($headerName, $headerValue);
            }
        }

        $this->finalized = true;
    }

    /**
     * Do not draw mail included in page.
     */
    public function draw(): void
    {
        $this->drawStatus = true;
    }

    /**
     * Send mail.
     */
    public function send(): bool
    {
        if (!$this->finalized) {
            $this->finalize();
        }

        try {
            $this->mailer->send($this->email);
            $this->sendResult = true;
        } catch (\Exception $e) {
            $this->sendResult = false;

            if ($this->notify === true) {
                $mailStripped = str_replace(['<', '>'], '', $this->emailAddress);
                $this->addStatusMessage(sprintf(
                    _('Message %s, for %s was not sent because of %s'),
                    $this->emailSubject,
                    $mailStripped,
                    $e->getMessage(),
                ), 'warning');
            }

            return false;
        }

        if ($this->notify === true) {
            $mailStripped = str_replace(['<', '>'], '', $this->emailAddress);
            $this->addStatusMessage(sprintf(_('Message %s was sent to %s'), $this->emailSubject, $mailStripped), 'success');
        }

        return true;
    }

    /**
     * Sets the user notification flag.
     *
     * @param bool $notify required notification status
     */
    public function setUserNotification(bool $notify): void
    {
        $this->notify = $notify;
    }

    /**
     * Inserts another element after the existing one.
     *
     * @param mixed $pageItem value or EaseObject with draw () method
     *
     * @return Container A link to the embedded object
     */
    public function &addNextTo($pageItem)
    {
        $itemPointer = $this->htmlBody->parentObject->addItem($pageItem);

        return $itemPointer;
    }
}
