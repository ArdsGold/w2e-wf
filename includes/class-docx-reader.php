<?php
if (!defined('ABSPATH')) exit;

class WFEBPG_DOCX_Reader {
    public static function read($file, $allow_zip_free_fallback = false) {
        if (!class_exists('ZipArchive')) {
            if (!$allow_zip_free_fallback) {
                throw new Exception('PHP ZipArchive is unavailable. Enable the zip-free DOCX fallback in Page Generation to continue.');
            }
            $xml = self::read_document_xml_without_ziparchive($file);
        } else {
            $zip = new ZipArchive();
            if ($zip->open($file) !== true) {
                throw new Exception('Unable to open DOCX.');
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
        }

        if (!$xml) {
            throw new Exception('DOCX document.xml not found.');
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new Exception('Unable to parse DOCX document.xml.');
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $items = [];

        foreach ($xp->query('//w:body/w:p') as $p) {
            // Use all descendant runs, not only direct child runs. Word may
            // place text inside hyperlinks or other run containers.
            $text = self::paragraph_text($xp, $p);
            $text = trim(preg_replace('/[ \t\r\n]+/u', ' ', $text));
            if ($text === '') continue;

            $style_name = '';
            $style = $xp->query('./w:pPr/w:pStyle', $p)->item(0);
            if ($style) {
                $style_name = (string) $style->getAttribute('w:val');
            }

            $is_heading = preg_match('/heading/i', $style_name) === 1;
            $heading_level = 0;
            if ($is_heading && preg_match('/heading\s*([1-9][0-9]*)/i', $style_name, $hm)) {
                $heading_level = (int) $hm[1];
            }
            $yellow = false;

            // Check every run in the paragraph. This catches yellow formatting
            // whether the heading text is split across multiple runs or nested
            // inside a hyperlink.
            foreach ($xp->query('.//w:r', $p) as $run) {
                if (self::run_is_yellow($xp, $run)) {
                    $yellow = true;
                    break;
                }
            }

            $items[] = [
                'text' => $text,
                'yellow' => $yellow,
                'heading' => $is_heading,
                'heading_level' => $heading_level,
                'repeatable' => $yellow && $is_heading,
            ];
        }

        // Build ordered non-repeat blocks separately from repeatable items.
        // Each non-yellow heading owns the following non-heading paragraphs
        // until the next non-yellow heading. This preserves the document's
        // semantic structure so the Elementor template can map heading + body
        // pairs correctly (for example Icon Boxes, process steps, and FAQs).
        $nonrepeat_blocks = [];
        $current_block = null;
        $inside_repeatable = false;

        foreach ($items as $item) {
            if ($item['repeatable']) {
                if ($current_block !== null) {
                    $nonrepeat_blocks[] = $current_block;
                    $current_block = null;
                }
                $inside_repeatable = true;
                continue;
            }

            if ($inside_repeatable) {
                // Ignore body paragraphs belonging to the yellow repeatable
                // heading until the next non-yellow heading starts.
                if (!$item['heading']) continue;
                $inside_repeatable = false;
            }

            if ($item['heading']) {
                if ($current_block !== null) $nonrepeat_blocks[] = $current_block;
                $current_block = [
                    'heading' => $item['text'],
                    'heading_level' => (int) ($item['heading_level'] ?? 0),
                    'content' => [],
                ];
            } elseif ($current_block !== null) {
                $current_block['content'][] = $item['text'];
            }
        }
        if ($current_block !== null) $nonrepeat_blocks[] = $current_block;

        // A yellow heading starts one repeatable item. Its following body
        // paragraphs belong to that item until the next yellow heading or a
        // new non-yellow heading.
        $sections = [];
        $repeatables = [];
        $current_repeatable = null;

        foreach ($items as $item) {
            if ($item['repeatable']) {
                if ($current_repeatable !== null) {
                    $repeatables[] = $current_repeatable;
                }

                $current_repeatable = [
                    'heading' => $item['text'],
                    'content' => [],
                    'repeatable' => true,
                ];
                continue;
            }

            if ($current_repeatable !== null) {
                if ($item['heading']) {
                    $repeatables[] = $current_repeatable;
                    $current_repeatable = null;
                    $sections[] = $item;
                } else {
                    $current_repeatable['content'][] = $item['text'];
                }
            } else {
                $sections[] = $item;
            }
        }

        if ($current_repeatable !== null) {
            $repeatables[] = $current_repeatable;
        }

        return [
            'items' => $items,
            'sections' => $sections,
            'nonrepeat_blocks' => $nonrepeat_blocks,
            'repeatables' => $repeatables,
            'repeatable_count' => count($repeatables),
        ];
    }

    /**
     * Read the DOCX document part from a ZIP file without the ZipArchive
     * extension. DOCX uses ordinary ZIP central-directory entries; zlib is
     * used only for the standard DEFLATE compression method.
     */
    private static function read_document_xml_without_ziparchive($file) {
        if (!function_exists('gzinflate')) {
            throw new Exception('The zip-free DOCX fallback requires PHP zlib.');
        }
        if (!is_file($file) || !is_readable($file)) {
            throw new Exception('Unable to read DOCX file.');
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new Exception('Unable to open DOCX.');
        }

        try {
            $stat = fstat($handle);
            $file_size = (int) ($stat['size'] ?? 0);
            if ($file_size < 22) {
                throw new Exception('DOCX file is too small to contain a valid ZIP directory.');
            }

            $tail_length = min($file_size, 65557);
            if (fseek($handle, $file_size - $tail_length) !== 0) {
                throw new Exception('Unable to seek within DOCX ZIP directory.');
            }
            $tail = fread($handle, $tail_length);
            $eocd_position = strrpos($tail, "PK\x05\x06");
            if ($eocd_position === false || strlen($tail) - $eocd_position < 22) {
                throw new Exception('DOCX ZIP directory is missing its end record.');
            }

            $eocd = unpack(
                'vdisk/vcentral_disk/ventries_on_disk/ventries_total/Vdirectory_size/Vdirectory_offset/vcomment_length',
                substr($tail, $eocd_position + 4, 18)
            );
            if (!$eocd || $eocd['disk'] !== 0 || $eocd['central_disk'] !== 0
                || $eocd['entries_on_disk'] !== $eocd['entries_total']
                || $eocd['entries_total'] === 0xFFFF
                || $eocd['directory_offset'] === 0xFFFFFFFF
                || $eocd['directory_size'] === 0xFFFFFFFF) {
                throw new Exception('This DOCX ZIP layout is not supported by the zip-free fallback.');
            }

            $directory_offset = (int) $eocd['directory_offset'];
            $directory_end = $directory_offset + (int) $eocd['directory_size'];
            if ($directory_offset < 0 || $directory_end > $file_size) {
                throw new Exception('DOCX ZIP directory contains invalid offsets.');
            }

            $position = $directory_offset;
            $document_entry = null;
            for ($entry_index = 0; $entry_index < (int) $eocd['entries_total']; $entry_index++) {
                if ($position + 46 > $directory_end || fseek($handle, $position) !== 0) {
                    throw new Exception('DOCX ZIP directory is truncated.');
                }
                $header = fread($handle, 46);
                if (strlen($header) !== 46 || substr($header, 0, 4) !== "PK\x01\x02") {
                    throw new Exception('DOCX ZIP directory contains an invalid entry.');
                }

                $entry = unpack(
                    'vversion_made/vversion_needed/vflags/vmethod/vmodified_time/vmodified_date/Vcrc/Vcompressed_size/Vuncompressed_size/vname_length/vextra_length/vcomment_length/vdisk/vinternal_attributes/Vexternal_attributes/Vlocal_offset',
                    substr($header, 4)
                );
                if (!$entry) {
                    throw new Exception('Unable to read DOCX ZIP entry metadata.');
                }

                $record_length = 46 + $entry['name_length'] + $entry['extra_length'] + $entry['comment_length'];
                if ($position + $record_length > $directory_end) {
                    throw new Exception('DOCX ZIP directory entry exceeds its bounds.');
                }
                $name = fread($handle, $entry['name_length']);
                if ($name === false || strlen($name) !== $entry['name_length']) {
                    throw new Exception('Unable to read DOCX ZIP entry name.');
                }

                if ($name === 'word/document.xml') {
                    $document_entry = $entry;
                    break;
                }
                $position += $record_length;
            }

            if (!$document_entry) {
                throw new Exception('DOCX document.xml not found.');
            }
            if (($document_entry['flags'] & 1) !== 0) {
                throw new Exception('Encrypted DOCX files are not supported by the zip-free fallback.');
            }
            if ($document_entry['disk'] !== 0
                || $document_entry['compressed_size'] === 0xFFFFFFFF
                || $document_entry['uncompressed_size'] === 0xFFFFFFFF
                || $document_entry['local_offset'] === 0xFFFFFFFF) {
                throw new Exception('This DOCX ZIP entry layout is not supported by the zip-free fallback.');
            }
            if ($document_entry['uncompressed_size'] > 33554432) {
                throw new Exception('DOCX document.xml exceeds the 32 MB zip-free fallback limit.');
            }

            $local_offset = (int) $document_entry['local_offset'];
            if ($local_offset + 30 > $file_size || fseek($handle, $local_offset) !== 0) {
                throw new Exception('DOCX ZIP entry has an invalid local-header offset.');
            }
            $local_header = fread($handle, 30);
            if (strlen($local_header) !== 30 || substr($local_header, 0, 4) !== "PK\x03\x04") {
                throw new Exception('DOCX ZIP entry has an invalid local header.');
            }
            $local = unpack(
                'vversion/vflags/vmethod/vmodified_time/vmodified_date/Vcrc/Vcompressed_size/Vuncompressed_size/vname_length/vextra_length',
                substr($local_header, 4)
            );
            if (!$local || $local['method'] !== $document_entry['method']
                || ($local['flags'] & 1) !== 0) {
                throw new Exception('DOCX ZIP local header does not match its directory entry.');
            }

            $data_offset = $local_offset + 30 + $local['name_length'] + $local['extra_length'];
            $compressed_size = (int) $document_entry['compressed_size'];
            if ($data_offset < 0 || $data_offset + $compressed_size > $file_size
                || fseek($handle, $data_offset) !== 0) {
                throw new Exception('DOCX ZIP entry data is outside the file bounds.');
            }
            $compressed = $compressed_size > 0 ? fread($handle, $compressed_size) : '';
            if ($compressed === false || strlen($compressed) !== $compressed_size) {
                throw new Exception('Unable to read DOCX document.xml ZIP data.');
            }

            if ($document_entry['method'] === 0) {
                $xml = $compressed;
            } elseif ($document_entry['method'] === 8) {
                $xml = gzinflate($compressed, 33554432);
            } else {
                throw new Exception('DOCX uses an unsupported ZIP compression method.');
            }
            if ($xml === false || strlen($xml) !== (int) $document_entry['uncompressed_size']) {
                throw new Exception('Unable to decompress DOCX document.xml.');
            }

            return $xml;
        } finally {
            fclose($handle);
        }
    }

    private static function paragraph_text($xp, $paragraph) {
        $text = '';

        foreach ($xp->query('.//w:r', $paragraph) as $run) {
            foreach ($xp->query('.//w:t', $run) as $t) {
                $text .= $t->nodeValue;
            }

            if ($xp->query('.//w:tab', $run)->length) {
                $text .= "\t";
            }

            if ($xp->query('.//w:br|.//w:cr', $run)->length) {
                $text .= "\n";
            }
        }

        return $text;
    }

    private static function run_is_yellow($xp, $run) {
        foreach ($xp->query('./w:rPr/w:color', $run) as $color) {
            $value = strtolower(trim((string) $color->getAttribute('w:val')));
            if (self::is_yellow_value($value)) return true;
        }

        foreach ($xp->query('./w:rPr/w:highlight', $run) as $highlight) {
            $value = strtolower(trim((string) $highlight->getAttribute('w:val')));
            if (in_array($value, ['yellow', 'ffff00'], true)) return true;
        }

        return false;
    }

    private static function is_yellow_value($value) {
        $value = ltrim($value, '#');

        if (in_array($value, ['yellow', 'ff0', 'fff200', 'ffff00'], true)) {
            return true;
        }

        if (strlen($value) === 6 && ctype_xdigit($value)) {
            $r = hexdec(substr($value, 0, 2));
            $g = hexdec(substr($value, 2, 2));
            $b = hexdec(substr($value, 4, 2));
            return $r >= 220 && $g >= 180 && $b <= 80;
        }

        return false;
    }
}
