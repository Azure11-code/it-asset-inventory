<?php

namespace Tests\Feature;

use App\Models\AssetPermit;
use App\Models\AssetPermitItem;
use App\Models\IncidentReport;
use App\Models\Recommendation;
use App\Services\DocxExporter;
use DOMDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use ZipArchive;

/**
 * Word exports have to survive the characters people actually type.
 *
 * A .docx is a zip of XML files. PhpWord does not escape text by default, so a
 * single "&" in a subject or an asset description used to be written into
 * word/document.xml as-is — which is not valid XML, and Word refuses the whole
 * file with "We're sorry. We can't open <name>.docx because we found a problem
 * with its contents. Xml parsing error."
 *
 * These tests push the awkward characters through every exporter and then
 * actually parse the XML back, which is the check Word performs when opening.
 */
class DocxExportTest extends TestCase
{
    use DatabaseTransactions;

    /** The characters that break raw XML, plus a few that trip naive escaping. */
    private const HOSTILE = 'Ink & Print Head <Maintenance> "quoted" 100% > 50% & it\'s fine';

    public function test_recommendation_export_is_valid_word_xml(): void
    {
        $rec = Recommendation::create([
            'doc_no'               => 'REC-TEST-0001',
            'report_date'          => now(),
            'requestor_name'       => self::HOSTILE,
            'requestor_position'   => self::HOSTILE,
            'requestor_department' => self::HOSTILE,
            'thru'                 => self::HOSTILE,
            'subject'              => self::HOSTILE,
            'body'                 => "Line one with & ampersand\nLine two with <tags>",
            'quick_specs'          => [['label' => 'RAM & ROM', 'value' => '8GB', 'note' => 'slow & old']],
            'status'               => 'draft',
        ]);

        $this->assertDocxIsValid(app(DocxExporter::class)->recommendation($rec), 'Print Head');
    }

    public function test_incident_report_export_is_valid_word_xml(): void
    {
        $report = IncidentReport::create([
            'ir_no'            => 'IR-TEST-0001',
            'report_date'      => now(),
            'end_user_name'    => self::HOSTILE,
            'reported_problem' => self::HOSTILE,
            'action_taken'     => "Cleaned head & rollers\nReseated <cable>",
            'findings'         => self::HOSTILE,
            'recommendation'   => self::HOSTILE,
            'status'           => 'draft',
        ]);

        $this->assertDocxIsValid(app(DocxExporter::class)->incident($report), 'rollers');
    }

    public function test_permit_export_is_valid_word_xml(): void
    {
        $permit = AssetPermit::create([
            'permit_no'       => 'PTB-TEST-0001',
            'employee_name'   => self::HOSTILE,
            'position_text'   => self::HOSTILE,
            'department_text' => self::HOSTILE,
            'destination'     => self::HOSTILE,
            'purpose'         => self::HOSTILE,
            'date_borrow'     => now(),
            'date_return'     => now()->addDay(),
            'valid_from'      => now(),
            'valid_to'        => now()->addDay(),
            'status'          => 'draft',
        ]);

        AssetPermitItem::create([
            'asset_permit_id' => $permit->id,
            'qty'             => 1,
            'unit'            => 'pc',
            'description'     => self::HOSTILE,
            'serial_no'       => 'SN&123',
            'remarks'         => self::HOSTILE,
        ]);

        $this->assertDocxIsValid(app(DocxExporter::class)->permit($permit->fresh()), 'Print Head');
    }

    /**
     * Streams the response to a temp file, then parses every XML part the way
     * Word does. A failure here is a file that will not open.
     */
    private function assertDocxIsValid($response, string $expectedText): void
    {
        ob_start();
        $response->sendContent();
        $bytes = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($path, $bytes);

        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($path) === true, 'The export is not a readable zip.');

            $sawDocument = false;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!str_ends_with($name, '.xml') && !str_ends_with($name, '.rels')) {
                    continue;
                }

                $xml = $zip->getFromIndex($i);

                $previous = libxml_use_internal_errors(true);
                libxml_clear_errors();
                $parsed = (new DOMDocument())->loadXML($xml);
                $errors = libxml_get_errors();
                libxml_clear_errors();
                libxml_use_internal_errors($previous);

                $this->assertTrue($parsed, sprintf(
                    'Word would reject %s: %s',
                    $name,
                    $errors ? trim($errors[0]->message) : 'unknown parse error'
                ));

                if ($name === 'word/document.xml') {
                    $sawDocument = true;

                    // Escaping must not have swallowed the text itself.
                    $this->assertStringContainsString($expectedText, $xml,
                        'The text was lost on the way into the document.');
                    $this->assertStringContainsString('&amp;', $xml,
                        'The ampersand was not escaped.');
                    $this->assertSame(0, preg_match(
                        '/&(?!(amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/', $xml
                    ), 'A bare ampersand survived into document.xml.');
                }
            }

            $this->assertTrue($sawDocument, 'The export contains no word/document.xml.');
            $zip->close();
        } finally {
            @unlink($path);
        }
    }
}
