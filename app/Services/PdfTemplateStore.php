<?php

namespace App\Services;

/**
 * Stores admin-uploaded form templates (FORM-AST-36513, FORM-AST-36749) so they can be
 * replaced when a new controlled-document version is issued.
 *
 * IMPORTANT: Veeva Vault PDFs use compressed object/xref streams (PDF 1.7 /ObjStm) which
 * FPDI's free parser cannot read. We flatten the upload with qpdf (preferred) or Ghostscript
 * so the overlay still works. If neither tool is on the server, we store the file as-is and
 * report that it may need manual flattening.
 */
class PdfTemplateStore
{
    /** Allowed logical template keys -> stored filename. */
    public const TEMPLATES = [
        'attendance' => 'FORM-AST-36513.pdf',
        'approval'   => 'FORM-AST-36749.pdf',
    ];

    public function dir(): string
    {
        $dir = storage_path('app/pdf-templates');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public function pathFor(string $key): ?string
    {
        $name = self::TEMPLATES[$key] ?? null;
        return $name ? $this->dir() . '/' . $name : null;
    }

    public function hasUploaded(string $key): bool
    {
        $p = $this->pathFor($key);
        return $p && is_file($p);
    }

    /**
     * Store an uploaded PDF for the given key, flattening it for the FPDI free parser.
     * @return array{ok:bool, flattened:bool, message:string}
     */
    public function store(string $key, string $sourcePath): array
    {
        $dest = $this->pathFor($key);
        if (! $dest) {
            return ['ok' => false, 'flattened' => false, 'message' => 'Unknown template.'];
        }

        $flattened = $this->flatten($sourcePath, $dest);
        if ($flattened) {
            return ['ok' => true, 'flattened' => true, 'message' => 'Uploaded and flattened for use.'];
        }

        // Could not flatten: store as-is so it is at least replaced, but warn loudly.
        if (! @copy($sourcePath, $dest)) {
            return ['ok' => false, 'flattened' => false, 'message' => 'Could not save the uploaded file.'];
        }
        return [
            'ok' => true,
            'flattened' => false,
            'message' => 'Uploaded, but it could not be auto-flattened (qpdf/Ghostscript not found on the server). '
                . 'If the form fails to generate, flatten it with: qpdf --object-streams=disable --stream-data=uncompress --force-version=1.4 in.pdf out.pdf',
        ];
    }

    public function delete(string $key): void
    {
        $p = $this->pathFor($key);
        if ($p && is_file($p)) @unlink($p);
    }

    /** Try qpdf, then Ghostscript. Returns true if a flattened file was written to $dest. */
    protected function flatten(string $src, string $dest): bool
    {
        $tmp = $dest . '.tmp.pdf';

        // qpdf: disable object streams, uncompress, downgrade to 1.4 (what FPDI free can read)
        if ($this->commandExists('qpdf')) {
            $cmd = sprintf(
                'qpdf --object-streams=disable --stream-data=uncompress --force-version=1.4 %s %s 2>/dev/null',
                escapeshellarg($src), escapeshellarg($tmp)
            );
            @exec($cmd, $out, $code);
            if ($code === 0 && is_file($tmp) && filesize($tmp) > 0) {
                @rename($tmp, $dest);
                return true;
            }
            @is_file($tmp) && @unlink($tmp);
        }

        // Ghostscript fallback: re-distill, which also removes object streams
        if ($this->commandExists('gs')) {
            $cmd = sprintf(
                'gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dDetectDuplicateImages=false -sOutputFile=%s %s 2>/dev/null',
                escapeshellarg($tmp), escapeshellarg($src)
            );
            @exec($cmd, $out2, $code2);
            if ($code2 === 0 && is_file($tmp) && filesize($tmp) > 0) {
                @rename($tmp, $dest);
                return true;
            }
            @is_file($tmp) && @unlink($tmp);
        }

        return false;
    }

    protected function commandExists(string $bin): bool
    {
        if (! function_exists('exec')) return false;
        @exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null', $out, $code);
        return $code === 0 && ! empty($out);
    }
}
