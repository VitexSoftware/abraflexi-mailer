<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
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
     * @var string path to template file
     */
    public $template;

    /**
     * Template
     *
     * @param \AbraFlexi\RO $document to send
     * @param string $template .ftl file path
     */
    public function __construct(\AbraFlexi\RO $document, $template)
    {
        $this->template = $template;
        $this->document = $document;
        parent::__construct($this->process(file_get_contents($template)));
    }

    /**
     * Compile Mail Body
     *
     * @param string $templateBody
     *
     * @return type
     */
    public function process($templateBody)
    {

        if (preg_match_all('/\$\{(.*?)\}/', $templateBody, $vars)) {
            foreach ($vars[1] as $pos => $var) {
                $base = self::variableBase($var);
                $prop = self::variableProperty($var);
                $key  = $vars[0][$pos];
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
                            \Ease\Functions::cfg('APP_NAME'),
                            $templateBody
                        );
                        break;
                    case 'nazevFirmy':
                    case 'company':
                        $company      = new \AbraFlexi\Company($this->document->company);
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
                        $this->addStatusMessage(sprintf(
                            _('Unknown template %s variable: %s'),
                            $this->template,
                            $key
                        ), 'debug');
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
