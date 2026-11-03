<?php

namespace App\Services;

class WordReport
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function generate(array $report): string
    {
        $body = $this->paragraph($report['title'], 'Title');
        $body .= $this->paragraph('Generated '.$report['generatedAt']->format('F d, Y h:i A'));
        foreach ($report['forms'] as $index => $form) {
            if ($index > 0) $body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
            $body .= $this->paragraph($form['heading'], 'Heading1');
            $summary = $report['rows'][$index] ?? [];
            foreach (array_chunk(array_keys($report['headers']), 3) as $indices) {
                $body .= $this->paragraph(implode('    |    ', array_map(fn ($i) => $report['headers'][$i].': '.($summary[$i] ?? ''), $indices)));
            }
            $body .= $this->paragraph($form['commitment']);
            if ($form['strategic_program'] ?? null) $body .= $this->paragraph('Strategic Program: '.$form['strategic_program']);
            if ($form['program_outcome'] ?? null) $body .= $this->paragraph('Program Outcome: '.$form['program_outcome']);
            if (empty($form['rows']) && ($form['success_indicators'] ?? null)) $body .= $this->paragraph('Success Indicators: '.$form['success_indicators']);
            $body .= $this->paragraph('Prepared By: '.$form['prepared_name'].' — '.$form['prepared_position']);
            if ($form['reviewed_by']) $body .= $this->paragraph('Reviewed By: '.$form['reviewed_by']);
            $body .= $this->paragraph('Approved By: '.$form['approved_by'].' — '.$form['approved_position']);
            $body .= $this->paragraph('Rating Scale: '.implode('; ', $form['rating_scale']));
            $body .= $this->table($report['formHeaders'], $form['rows']);
        }
        if (! $report['forms']) $body .= $this->paragraph('No form records available.');
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body
            .'<w:sectPr><w:pgSz w:w="15840" w:h="12240" w:orient="landscape"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360"/></w:sectPr></w:body></w:document>';
        return $this->package([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml' => $document,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'word/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="22"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="100"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style><w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/></w:pPr><w:rPr><w:b/><w:sz w:val="32"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="160" w:after="120"/></w:pPr><w:rPr><w:b/><w:sz w:val="26"/></w:rPr></w:style></w:styles>',
        ]);
    }

    private function paragraph(string $text, ?string $style = null, bool $cell = false): string
    {
        // Remove XML-invalid control characters; source text is always literal, never markup.
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';
        $runs = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $runs[] = '<w:r>'.($cell ? '<w:rPr><w:sz w:val="20"/></w:rPr>' : '').'<w:t xml:space="preserve">'.htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r>';
        }
        return '<w:p><w:pPr>'.($style ? '<w:pStyle w:val="'.$style.'"/>' : '').($cell ? '<w:spacing w:after="60"/>' : '').'</w:pPr>'.implode('<w:r><w:br/></w:r>', $runs).'</w:p>';
    }

    private function table(array $headers, array $rows): string
    {
        $factors = array_map(fn ($h) => in_array($h, ['Q', 'E', 'T', 'A']) ? 0.35 : (str_contains($h, 'Budget') ? 0.8 : 1.7), $headers);
        $widths = array_map(fn ($factor) => (int) floor(14400 * $factor / array_sum($factors)), $factors);
        $widths[0] += 14400 - array_sum($widths);
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="14400" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblBorders>';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $edge) $xml .= '<w:'.$edge.' w:val="single" w:sz="4" w:color="C0C0C0"/>';
        $xml .= '</w:tblBorders><w:tblCellMar><w:top w:w="100" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="100" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>';
        foreach ($widths as $width) $xml .= '<w:gridCol w:w="'.$width.'"/>';
        $xml .= '</w:tblGrid>';
        if (! $rows) $rows = [array_pad(['No performance targets encoded yet.'], count($headers), '')];
        foreach ([$headers, ...$rows] as $index => $row) {
            $xml .= '<w:tr>'.($index === 0 ? '<w:trPr><w:tblHeader/></w:trPr>' : '');
            foreach ($widths as $i => $width) {
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>'.($index === 0 ? '<w:shd w:fill="EEF2F6"/>' : '').'</w:tcPr>'.$this->paragraph((string) ($row[$i] ?? ''), null, true).'</w:tc>';
            }
            $xml .= '</w:tr>';
        }
        return $xml.'</w:tbl><w:p/>';
    }

    /** Uncompressed ZIP records avoid requiring an extra PHP extension on deployment. */
    private function package(array $files): string
    {
        $data = $directory = '';
        foreach ($files as $name => $contents) {
            $size = strlen($contents);
            $crc = crc32($contents);
            $offset = strlen($data);
            $data .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 33, $crc, $size, $size, strlen($name), 0).$name.$contents;
            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 33, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset).$name;
        }
        return $data.$directory.pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($directory), strlen($data), 0);
    }
}
