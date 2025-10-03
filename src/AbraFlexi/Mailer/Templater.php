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

/**
 * AbraFlexi Mailer Template processor class.
 *
 * @author     Vítězslav Dvořák <info@vitexsofware.cz>
 * @copyright  (G) 2021-2025 Vitex Software
 */
class Templater extends \Ease\Document implements mailbody
{
    /**
     * @var string template file content
     */
    public string $template;
    private \AbraFlexi\RO $document;

    /**
     * Sender Company.
     */
    private ?\AbraFlexi\Nastaveni $myCompany = null;

    /**
     * Template.
     *
     * @param string        $template          .ftl file content
     * @param \AbraFlexi\RO $abraflexiDocument AbraFlexi Document
     */
    public function __construct($template, ?\AbraFlexi\RO $abraflexiDocument = null)
    {
        $this->template = $template;
        parent::__construct();

        if ($abraflexiDocument) {
            $this->populate($abraflexiDocument);
        }
    }

    /**
     * Populate Template with data.
     */
    public function populate(\AbraFlexi\RO $abraflexiDocument): void
    {
        $this->emptyContents();
        $this->document = $abraflexiDocument;

        try {
            $this->myCompany = new \AbraFlexi\Nastaveni(1);
            $companyData = $this->myCompany->getData();
        } catch (\AbraFlexi\Exception $e) {
            $this->addStatusMessage(_('Failed to load company settings').':'.$e->getMessage(), 'warning');
            $companyData = [
                'nazev' => \Ease\Shared::cfg('COMPANY_NAME', ''),
                'email' => \Ease\Shared::cfg('COMPANY_EMAIL', ''),
                'signature' => \Ease\Shared::cfg('COMPANY_SIGNATURE', ''),
            ];
            $this->myCompany = null;
            $this->addStatusMessage(_('Using default company settings. Please set COMPANY_NAME, COMPANY_EMAIL, and COMPANY_SIGNATURE in the configuration.'), 'warning');
        }

        $templateData = [
            'company' => $companyData,
            'object' => $this->document->getData(),
        ];

        file_put_contents('/tmp/templatedata.json', json_encode($templateData));
        $this->addItem($this->process($this->template));
    }

    /**
     * Compile Mail Body.
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
                                (string) $this->document->getDataValue($prop),
                                $templateBody,
                            );
                        }

                        break;
                    case 'user':
                    case 'uzivatelJmeno':
                    case 'uzivatelPrijmeni':
                    case 'titulJmenoPrijmeni':
                        $user = new \AbraFlexi\RO(
                            \AbraFlexi\Code::ensure($this->document->user),
                            ['evidence' => 'uzivatel', 'autoload' => true],
                        );

                        if ($prop && $user->getDataValue($prop)) {
                            $templateBody = str_replace(
                                $key,
                                $user->getDataValue($prop),
                                $templateBody,
                            );
                        } elseif (($base === 'uzivatelJmeno') && $user->getDataValue('jmeno')) {
                            $templateBody = str_replace(
                                $key,
                                $user->getDataValue('jmeno'),
                                $templateBody,
                            );
                        } elseif (($base === 'uzivatelPrijmeni') && $user->getDataValue('prijmeni')) {
                            $templateBody = str_replace(
                                $key,
                                $user->getDataValue('prijmeni'),
                                $templateBody,
                            );
                        } elseif ($base === 'titulJmenoPrijmeni') {
                            $templateBody = str_replace(
                                $key,
                                trim($user->getDataValue('titul').' '.$user->getDataValue('jmeno').' '.$user->getDataValue('prijmeni').' '.$user->getDataValue('titulZa')),
                                $templateBody,
                            );
                        }

                        break;
                    case 'application':
                        $templateBody = str_replace(
                            $key,
                            \Ease\Shared::appName().' '.\Ease\Shared::appVersion(),
                            $templateBody,
                        );

                        break;
                    case 'nazevFirmy':
                    case 'company':
                        if ($this->myCompany) {
                            if ($prop) {
                                $templateBody = str_replace(
                                    $key,
                                    (string) $this->myCompany->getDataValue($prop),
                                    $templateBody,
                                );
                            } else {
                                $templateBody = str_replace(
                                    $key,
                                    (string) $this->myCompany->getDataValue('nazFirmy'),
                                    $templateBody,
                                );
                            }
                        } else {
                            // Fallback to config values if myCompany is not available
                            if ($prop) {
                                $templateBody = str_replace(
                                    $key,
                                    (string) \Ease\Shared::cfg('COMPANY_' . strtoupper($prop), ''),
                                    $templateBody,
                                );
                            } else {
                                $templateBody = str_replace(
                                    $key,
                                    (string) \Ease\Shared::cfg('COMPANY_NAME', ''),
                                    $templateBody,
                                );
                            }
                        }
                        break;
                    case 'object':
                        $objectData = $this->document->getData();

                        if ($prop === '') {
                            $templateBody = str_replace($key, $this->document->getRecordCode(), $templateBody);
                        } elseif (\array_key_exists($prop, $objectData)) {
                            $templateBody = str_replace($key, \AbraFlexi\Functions::uncode((string) $this->document->getDataValue($prop)), $templateBody);
                        } else {
                            $this->addStatusMessage(sprintf(_('Cannot find %s in %s'), $key, $this->document), 'warning');
                        }

                        break;

                    default:
                        if (\array_key_exists($base, $this->document->getData())) {
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
     * @param string $key
     *
     * @return string
     */
    public static function variableBase($key)
    {
        if (strstr($key, '.')) {
            [$base, $property] = explode('.', $key);
        } else {
            $base = $key;
        }

        return $base;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public static function variableProperty($key)
    {
        $property = '';

        if (strstr($key, '.')) {
            [$base, $property] = explode('.', $key);
        }

        return $property;
    }

    /**
     * @param string $var
     *
     * @return string
     */
    public static function stripMarkup($var)
    {
        return substr($var, 2, \strlen($var) - 3);
    }
}
