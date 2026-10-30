<?php

namespace App\Services;

use App\Models\EvaluationCycle;
use App\Models\HistoricalPcr;
use App\Models\HistoricalPcrRevision;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

class HistoricalPcrExport
{
    public const TITLES = ['opcr' => 'Office', 'dpcr' => 'Division', 'upcr' => 'Unit', 'ipcr' => 'Individual'];

    public const LABELS = ['objective' => 'Objectives', 'activity' => 'Program / Project / Activity', 'indicator' => 'Success Indicators (Targets + Measures)',
        'budget' => 'Allotted Budget', 'accountable' => 'Division / Individuals Accountable', 'accomplishment' => 'Actual Accomplishments',
        'q' => 'Q', 'e' => 'E', 't' => 'T', 'a' => 'A', 'remarks' => 'Remarks / Source'];

    public function data(HistoricalPcr $pcr, ?HistoricalPcrRevision $revision = null): array
    {
        $revision ??= $pcr->revision;
        $fields = match ($pcr->type) {
            'ipcr' => ['indicator', 'budget', 'accomplishment', 'q', 'e', 't', 'a', 'remarks'],
            'upcr' => ['activity', 'indicator', 'budget', 'accountable', 'accomplishment', 'q', 'e', 't', 'a', 'remarks'],
            default => ['objective', 'activity', 'indicator', 'budget', 'accountable', 'accomplishment', 'q', 'e', 't', 'a', 'remarks'],
        };
        $weights = ['objective' => 10, 'activity' => 12, 'indicator' => 20, 'budget' => 6, 'accountable' => 9, 'accomplishment' => 28, 'q' => 3, 'e' => 3, 't' => 3, 'a' => 3, 'remarks' => 10];
        $sum = array_sum(array_intersect_key($weights, array_flip($fields)));
        $columns = [];
        foreach ($fields as $key) {
            $columns[$key] = ['label' => self::LABELS[$key], 'width' => $weights[$key] * 100 / $sum];
        }
        $snapshot = $revision->snapshot;
        $rows = array_map(function ($row) {
            $row['remarks'] = trim(($row['remarks'] ?? '')."\nSource p. ".$row['page']."\n".($row['review_note'] ?? ''));

            return $row;
        }, $snapshot['rows']);
        $evidence = [];
        for ($page = $pcr->form_pages + 1; $page <= $pcr->source_pages; $page++) {
            $path = app(HistoricalPcrArchive::class)->privatePath($pcr->image_directory.'/'.$page.'.jpg');
            [$w, $h] = getimagesize($path);
            $evidence[] = ['page' => $page, 'path' => $path, 'width' => $w, 'height' => $h];
        }

        return compact('pcr', 'revision', 'snapshot', 'columns', 'rows', 'evidence') + [
            'title' => self::TITLES[$pcr->type].' Performance Commitment and Review',
            'status' => $revision->verified_at ? 'Verified transcription' : 'DRAFT - Transcription review pending',
            'period' => ($pcr->cycle->semester === EvaluationCycle::FIRST_SEMESTER ? 'January - June ' : 'July - December ').$pcr->cycle->year,
        ];
    }

    public function file(HistoricalPcr $pcr, string $format, ?HistoricalPcrRevision $revision = null): string
    {
        abort_unless(in_array($format, ['docx', 'xlsx', 'pdf'], true), 404);
        $data = $this->data($pcr, $revision);
        $relative = dirname($pcr->source_path).'/exports/'.$data['revision']->id.'-v2.'.$format;
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($relative));

        return Cache::lock('historical-export-'.$data['revision']->id.'-'.$format, 300)->block(20, function () use ($relative, $format, $data, $disk) {
            // Verification changes the status without changing the revision ID.
            $stamp = $data['revision']->verified_at?->timestamp ?? 0;
            $relative = str_replace('-v2.', '-'.$stamp.'-v2.', $relative);
            if ($disk->exists($relative)) {
                return $disk->path($relative);
            }
            $temporary = $disk->path($relative.'.partial');
            try {
                match ($format) {
                    'docx' => $this->word($data, $temporary),
                    'xlsx' => $this->excel($data, $temporary),
                    'pdf' => $this->pdf($data, $temporary),
                };
                $disk->move($relative.'.partial', $relative);

                return $disk->path($relative);
            } finally {
                $disk->delete($relative.'.partial');
            }
        });
    }

    private function word(array $data, string $path): void
    {
        Settings::setOutputEscapingEnabled(true);
        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(8);
        $word->setDefaultParagraphStyle(['spaceAfter' => 30, 'spaceBefore' => 0]);
        $section = $word->addSection(['pageSizeW' => 18720, 'pageSizeH' => 12240, 'orientation' => 'landscape',
            'marginTop' => 480, 'marginBottom' => 480, 'marginLeft' => 480, 'marginRight' => 480]);
        $footer = $section->addFooter();
        $footer->addPreserveText(strtoupper($data['pcr']->type).' | '.$data['pcr']->owner_name.' | Revision '.$data['revision']->number.' | Page {PAGE}', ['size' => 7, 'color' => '526174']);
        $section->addText(strtoupper($data['title']), ['bold' => true, 'size' => 12], ['alignment' => 'center']);
        foreach ($this->intro($data) as $line) {
            $section->addText($line, ['size' => 8]);
        }
        $table = $section->addTable(['borderSize' => 4, 'borderColor' => 'A9B3BF', 'cellMargin' => 65, 'layout' => 'fixed', 'width' => 17760, 'unit' => 'dxa']);
        $table->addRow(null, ['tblHeader' => true]);
        foreach ($data['columns'] as $column) {
            $table->addCell((int) (17760 * $column['width'] / 100), ['bgColor' => 'E8EDF2'])->addText($column['label'], ['bold' => true, 'size' => 8]);
        }
        $last = null;
        foreach ($data['rows'] as $row) {
            if ($row['section'] !== $last) {
                $table->addRow();
                $table->addCell(17760, ['gridSpan' => count($data['columns']), 'bgColor' => 'EFF3F7'])->addText($row['section'], ['bold' => true]);
                $last = $row['section'];
            }
            $table->addRow(null, ['cantSplit' => true]);
            foreach ($data['columns'] as $key => $column) {
                $cell = $table->addCell((int) (17760 * $column['width'] / 100), ['valign' => 'top']);
                foreach (explode("\n", (string) ($row[$key] ?? '')) as $line) {
                    $cell->addText($line, ['size' => 8], ['alignment' => in_array($key, ['q', 'e', 't', 'a']) ? 'center' : 'left']);
                }
            }
        }
        $section->addTextBreak();
        foreach ($this->closing($data) as $line) {
            $section->addText($line);
        }
        foreach ($data['evidence'] as $item) {
            $section->addPageBreak();
            $section->addText('Evidence - original source page '.$item['page'], ['bold' => true]);
            $scale = min(880 / $item['width'], 510 / $item['height']);
            $section->addImage($item['path'], ['width' => $item['width'] * $scale, 'height' => $item['height'] * $scale, 'alignment' => 'center']);
        }
        IOFactory::createWriter($word, 'Word2007')->save($path);
    }

    private function excel(array $data, string $path): void
    {
        $book = new Spreadsheet;
        try {
            $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
            $sheet = $book->getActiveSheet()->setTitle(strtoupper($data['pcr']->type));
            $count = count($data['columns']);
            $end = Coordinate::stringFromColumnIndex($count);
            $rowNumber = 1;
            foreach ([strtoupper($data['title']), ...$this->intro($data)] as $line) {
                $sheet->mergeCells('A'.$rowNumber.':'.$end.$rowNumber);
                $sheet->setCellValueExplicit('A'.$rowNumber, $line, DataType::TYPE_STRING);
                $sheet->getRowDimension($rowNumber)->setRowHeight(max(18, 14 * ceil(mb_strlen($line) / 160)));
                $rowNumber++;
            }
            $header = $rowNumber;
            $col = 1;
            foreach ($data['columns'] as $column) {
                $letter = Coordinate::stringFromColumnIndex($col++);
                $sheet->getColumnDimension($letter)->setWidth(max(5, $column['width'] * 1.65));
                $sheet->setCellValueExplicit($letter.$rowNumber, $column['label'], DataType::TYPE_STRING);
            }
            $sheet->getRowDimension($rowNumber++)->setRowHeight(34);
            $last = null;
            foreach ($data['rows'] as $row) {
                if ($row['section'] !== $last) {
                    $sheet->mergeCells('A'.$rowNumber.':'.$end.$rowNumber);
                    $sheet->setCellValueExplicit('A'.$rowNumber, $row['section'], DataType::TYPE_STRING);
                    $sheet->getStyle('A'.$rowNumber.':'.$end.$rowNumber)->getFill()->setFillType('solid')->getStartColor()->setRGB('E8EDF2');
                    $sheet->getRowDimension($rowNumber++)->setRowHeight(28);
                    $last = $row['section'];
                }
                $col = 1;
                $lines = 1;
                foreach ($data['columns'] as $key => $column) {
                    $value = (string) ($row[$key] ?? '');
                    $letter = Coordinate::stringFromColumnIndex($col++);
                    // Historical values are literal source text, including strings beginning with =, +, - or @.
                    $sheet->setCellValueExplicit($letter.$rowNumber, $value, DataType::TYPE_STRING);
                    $width = max(4, $column['width'] * 1.55);
                    $lines = max($lines, array_sum(array_map(fn ($line) => max(1, ceil(mb_strlen($line) / $width)), explode("\n", $value))));
                }
                $sheet->getRowDimension($rowNumber++)->setRowHeight(min(409, max(32, $lines * 15 + 12)));
            }
            $tableEnd = $rowNumber - 1;
            foreach ($this->closing($data) as $line) {
                $sheet->mergeCells('A'.$rowNumber.':'.$end.$rowNumber);
                $sheet->setCellValueExplicit('A'.$rowNumber, $line, DataType::TYPE_STRING);
                $sheet->getRowDimension($rowNumber++)->setRowHeight(max(22, 15 * ceil(mb_strlen($line) / 140)));
            }
            $sheet->getStyle('A1:'.$end.$rowNumber)->getAlignment()->setWrapText(true)->setVertical('top');
            $sheet->getStyle('A'.$header.':'.$end.$tableEnd)->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setRGB('A9B3BF');
            $sheet->getStyle('A'.$header.':'.$end.$header)->getFont()->setBold(true);
            $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
            $sheet->freezePane('A'.($header + 1));
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_FOLIO)->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd($header, $header)->setPrintArea('A1:'.$end.($rowNumber - 1));
            foreach ($data['evidence'] as $item) {
                $page = $book->createSheet()->setTitle('Evidence '.$item['page']);
                $page->setCellValue('A1', 'Original source page '.$item['page'].' | '.$data['pcr']->source_name);
                $page->mergeCells('A1:L1');
                for ($i = 1; $i <= 12; $i++) {
                    $page->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(12);
                }
                $scale = min(950 / $item['width'], 660 / $item['height']);
                $drawing = new Drawing;
                $drawing->setName('Source page '.$item['page'])->setDescription('Preserved evidence page '.$item['page']);
                $drawing->setPath($item['path'])->setCoordinates('A3')->setWidth((int) ($item['width'] * $scale))->setWorksheet($page);
                $lastRow = (int) ceil($item['height'] * $scale / 20) + 4;
                $page->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_FOLIO)->setFitToWidth(1)->setFitToHeight(1)->setPrintArea('A1:L'.$lastRow);
            }
            $book->setActiveSheetIndex(0);
            (new Xlsx($book))->save($path);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function pdf(array $data, string $path): void
    {
        Pdf::loadView('historical-pcrs.exports.pdf', $data)
            ->setPaper([0, 0, 936, 612])->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])
            ->save($path);
    }

    public function intro(array $data): array
    {
        $s = $data['snapshot'];

        return [
            $data['pcr']->owner_name.' | '.($s['owner_position'] ?? ''),
            ($s['office'] ?? '').' | Reporting period: '.$data['period'],
            $data['status'].' | Revision '.$data['revision']->number.' | Generated '.now()->format('Y-m-d'),
            'Source: '.$data['pcr']->source_name.' | Form date: '.($s['form_date'] ?: 'Not recorded'),
            'Historical transcription. Original signed PDF retained. OPCR lineage not supplied.',
            ...array_map(fn ($note) => 'Review note: '.$note, $s['notes']),
        ];
    }

    public function closing(array $data): array
    {
        $s = $data['snapshot'];

        return ['Reported final average: '.($s['reported_overall'] ?: 'Not legible / not recorded').' | Adjectival rating: '.($s['adjectival_rating'] ?: 'Not recorded'),
            'Values are transcribed from the source; no new rating or institutional approval is calculated.',
            ...array_map(fn ($signatory) => $signatory['role'].': '.$signatory['name'].' | Source p. '.$signatory['page'].' | Date: '.($signatory['date'] ?: 'Not recorded'), $s['signatories']),
            'Original SHA-256: '.$data['pcr']->source_sha256];
    }
}
