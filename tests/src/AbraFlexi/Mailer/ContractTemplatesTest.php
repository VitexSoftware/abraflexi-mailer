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

namespace Test\AbraFlexi\Mailer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The contract mail exists in every language and uses only variables the Templater resolves.
 */
class ContractTemplatesTest extends TestCase
{
    /**
     * Variables that need nothing but the contract and the company settings.
     */
    private const SUPPORTED = ['${object.kod}', '${object.nazev}', '${nazevFirmy}', '${company.email}', '${company.ic}'];

    /**
     * @return array<string, array{0: string}>
     */
    public static function languages(): array
    {
        return ['czech' => ['cs'], 'english' => ['en']];
    }

    #[DataProvider('languages')]
    public function testTemplateExistsAndUsesSupportedVariablesOnly(string $language): void
    {
        $file = __DIR__.'/../../../../templates/'.$language.'/smlouva.ftl';
        $this->assertFileExists($file);

        $template = (string) file_get_contents($file);
        preg_match_all('/\$\{.*?\}/', $template, $variables);

        $this->assertNotEmpty($variables[0]);
        $this->assertSame([], array_values(array_diff($variables[0], self::SUPPORTED)), 'unsupported variable in '.$language);
        $this->assertStringContainsString('${object.kod}', $template);
    }
}
