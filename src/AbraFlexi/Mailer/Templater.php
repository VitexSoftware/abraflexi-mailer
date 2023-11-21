<?php

/**
 * AbraFlexi Mailer Template class
 *
 * @author     Vítězslav Dvořák <info@vitexsofware.cz>
 * @copyright  (G) 2021-2023 Vitex Software
 */

namespace AbraFlexi\Mailer;

use PHPHtmlParser\Dom;

/**
 * AbraFlexi Mailer Template processor class
 *
 * @author     Vítězslav Dvořák <info@vitexsofware.cz>
 * @copyright  (G) 2021-2023 Vitex Software
 */
class Templater extends \Ease\Document
{
    /**
     *
     * @var \AbraFlexi\RO
     */
    private $document;

    /**
     *
     * @var string template file content
     */
    public $template;

    /**
     * Template
     *
     * @param string       $template          .ftl file content
     * @param \AbraFlexiRO $abraflexiDocument AbraFlexi Document
     */
    public function __construct($template, \AbraFlexi\RO $abraflexiDocument = null)
    {
        $this->template = $template;
        parent::__construct();
        if ($abraflexiDocument) {
            $this->populate($abraflexiDocument);
        }
    }


    /**
     * Populate Template with data
     *
     * @param \AbraFlexi\RO $abraflexiDocument
     */
    public function populate(\AbraFlexi\RO $abraflexiDocument)
    {
        $this->emptyContents();
        $this->document = $abraflexiDocument;
        $this->addItem($this->process($this->template));
    }

    /**
     * Compile Mail Body
     *
     * @param string $templateBody
     *
     * @return string
     */
    public function process($templateBody)
    {
        if (preg_match_all('/\$\{(.*?)\}/', $templateBody, $vars)) {
            foreach ($vars[1] as $pos => $var) {
                $base = self::variableBase($var);
                $prop = self::variableProperty($var);
                $key = $vars[0][$pos];
                switch ($base) {
                    case 'doklad':
                        if ($prop) {
                            $templateBody = str_replace(
                                $key,
                                $this->document->getDataValue($prop),
                                $templateBody
                            );
                        }
                        break;

                    case 'user':
                    case 'uzivatelJmeno':
                    case 'uzivatelPrijmeni':
                    case 'titulJmenoPrijmeni':
                        $user = new \AbraFlexi\RO(
                            \AbraFlexi\RO::code($this->document->user),
                            ['evidence' => 'uzivatel', 'autoload' => true]
                        );
                        if ($prop) {
                            $templateBody = str_replace(
                                $key,
                                $user->getDataValue($prop),
                                $templateBody
                            );
                        } elseif ($base == 'uzivatelJmeno') {
                            $templateBody = str_replace(
                                $key,
                                $user->getDataValue('jmeno'),
                                $templateBody
                            );
                        } elseif ($base == 'uzivatelPrijmeni') {
                            $templateBody = str_replace(
                                $key,
                                $user->getDataValue('prijmeni'),
                                $templateBody
                            );
                        } elseif ($base == 'titulJmenoPrijmeni') {
                            $templateBody = str_replace(
                                $key,
                                trim($user->getDataValue('titul') . ' ' . $user->getDataValue('jmeno') . ' ' . $user->getDataValue('prijmeni') . ' ' . $user->getDataValue('titulZa')),
                                $templateBody
                            );
                        }
                        break;
                    case 'application':
                        $templateBody = str_replace(
                            $key,
                            \Ease\Shared::appName() . ' ' . \Ease\Shared::appVersion(),
                            $templateBody
                        );
                        break;
                    case 'nazevFirmy':
                    case 'company':
                        $company = new \AbraFlexi\Company($this->document->company);
                        if ($prop) {
                            $templateBody = str_replace(
                                $key,
                                $company->getDataValue($prop),
                                $templateBody
                            );
                        } else {
                            $templateBody = str_replace(
                                $key,
                                $company->getDataValue('nazev'),
                                $templateBody
                            );
                        }
                        break;
                    default:
                        if (array_key_exists($base, $this->document->getData())) {
                            $templateBody = str_replace($key, $this->document->getDataValue($base), $templateBody);
                        } else {
                            $this->addStatusMessage(sprintf(_('Unknown template\'s variable: %s'), $key), 'warning');
                        }
                        break;
                }
            }
        }
        return $templateBody;
    }

    /**
     *
     * @param string $key
     *
     * @return string
     */
    public static function variableBase($key)
    {
        if (strstr($key, '.')) {
            list($base, $property) = explode('.', $key);
        } else {
            $base = $key;
        }
        return $base;
    }

    public static function variableProperty($key)
    {
        $property = '';
        if (strstr($key, '.')) {
            list($base, $property) = explode('.', $key);
        }
        return $property;
    }

    public static function stripMarkup($var)
    {
        return substr($var, 2, strlen($var) - 3);
    }
}
