<?php

declare(strict_types=1);

namespace AAB\LeaseFlow;

use RuntimeException;
use setasign\Fpdi\Fpdi;

final class PdfService
{
    public static function applySignature(
        string $sourcePdf,
        string $signaturePng,
        string $destinationPdf,
        int $signaturePage,
        float $xRatio,
        float $yRatio,
        float $widthRatio,
        float $heightRatio,
        string $signerName,
        string $signedAt
    ): void {
        if (!class_exists(Fpdi::class)) {
            throw new RuntimeException('FPDI ontbreekt. Voer eerst composer install uit.');
        }

        $pdf = new Fpdi();
        $pages = $pdf->setSourceFile($sourcePdf);

        if ($signaturePage < 1 || $signaturePage > $pages) {
            $signaturePage = $pages;
        }

        for ($page = 1; $page <= $pages; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            if ($page === $signaturePage) {
                $x = max(0, min(1, $xRatio)) * $size['width'];
                $y = max(0, min(1, $yRatio)) * $size['height'];
                $w = max(0.05, min(0.8, $widthRatio)) * $size['width'];
                $h = max(0.03, min(0.5, $heightRatio)) * $size['height'];

                // White-backed signature keeps handwritten ink readable on busy forms.
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Rect($x, $y, $w, $h, 'F');
                $pdf->Image($signaturePng, $x, $y, $w, $h, 'PNG');

                $pdf->SetFont('Arial', '', 6);
                $pdf->SetTextColor(70, 70, 70);
                $pdf->SetXY($x, min($size['height'] - 5, $y + $h + 1));
                $pdf->Cell($w, 3, self::latin1('Ondertekend door ' . $signerName . ' op ' . $signedAt), 0, 0, 'L');
            }
        }

        $pdf->Output('F', $destinationPdf);
    }

    private static function latin1(string $value): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $value) ?: $value;
    }
}
