<?php

namespace App\Services;

use ZipArchive;

/** .docx (Word) fayldan oddiy matn ajratadi. Ichidagi rasmlar va chiroyli formatlash e'tiborga olinmaydi. */
final class DocxText
{
    public static function extract(string $bytes): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'docx');

        if ($tmp === false) {
            return null;
        }

        try {
            file_put_contents($tmp, $bytes);

            $zip = new ZipArchive;

            if ($zip->open($tmp) !== true) {
                return null;
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
        } finally {
            @unlink($tmp);
        }

        if (! is_string($xml) || $xml === '') {
            return null;
        }

        $xml = preg_replace(
            ['/<w:tab\b[^>]*\/>/', '/<w:br\b[^>]*\/>/', '/<\/w:p>/', '/<\/w:tr>/'],
            ["\t", "\n", "\n", "\n"],
            $xml,
        );

        $text = html_entity_decode(strip_tags((string) $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = (string) preg_replace('/[ \t]+\n/', "\n", $text);
        $text = trim((string) preg_replace('/\n{3,}/', "\n\n", $text));

        return $text !== '' ? $text : null;
    }
}
