<?php

namespace Media\Controllers;

use App\Data\Files;
use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Hands out a file of the library at `/media/{id}/{name}`.
 *
 * The file is found by its id, so a link survives a rename or a move, and the name in the link is only for readers. The
 * type comes from the row, which was made from the content of the file, and never from the extension in the link. Nothing
 * is served with a type a browser would run: a file that is not media is sent as a download, and everything but a PDF is
 * sent with a policy that stops scripts, so an uploaded SVG cannot act as part of the site.
 */
class Serve extends Controller
{
    /**
     * The most one answer to a range request carries. A player asks for the rest of the file again.
     */
    private const RANGE_LIMIT = 8_388_608;

    private const POLICY = "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; sandbox";

    public function file($id = '', $name = ''): ResponseInterface
    {
        /** @var Files $files */
        $files = service('files');
        $row   = $files->find((int) $id);
        $full  = $row === null ? null : $files->absolute($row);
        if ($row === null || $full === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $mime = (string) $row['mime'];
        $size = (int) filesize($full);
        $etag = '"' . md5($row['id'] . ':' . $size . ':' . filemtime($full)) . '"';
        $type = str_starts_with($mime, 'text/') ? $mime . '; charset=UTF-8' : $mime;

        $disposition = (Files::isInline($mime) ? 'inline' : 'attachment')
            . '; filename="' . str_replace(['"', '\\'], '', (string) preg_replace('/[^\x20-\x7e]/', '_', (string) $row['name'])) . '"'
            . "; filename*=UTF-8''" . rawurlencode((string) $row['name']);

        $headers = [
            'Content-Type'            => $type,
            'Content-Disposition'     => $disposition,
            'X-Content-Type-Options'  => 'nosniff',
            'Cache-Control'           => 'public, max-age=3600',
            'ETag'                    => $etag,
            'Accept-Ranges'           => 'bytes',
        ];
        if ($mime !== 'application/pdf') {
            // A browser opens a PDF in its own viewer, which a sandbox would block, and a PDF cannot reach the page.
            $headers['Content-Security-Policy'] = self::POLICY;
        }

        if ($this->request->getHeaderLine('If-None-Match') === $etag) {
            return $this->with($this->response->setStatusCode(304), $headers, ['Content-Type', 'Content-Disposition']);
        }

        if (preg_match('/^bytes=(\d*)-(\d*)$/', $this->request->getHeaderLine('Range'), $m) === 1 && ($m[1] !== '' || $m[2] !== '')) {
            return $this->slice($this->with($this->response, $headers), $full, $size, $m[1], $m[2]);
        }

        // The download response streams the file in chunks, so a large file does not fill the memory.
        $download = $this->response->download($full, null);

        return $this->with($download, $headers + ['Content-Length' => (string) $size]);
    }

    /**
     * @param array<string, string> $headers
     * @param list<string>          $except
     */
    private function with(ResponseInterface $response, array $headers, array $except = []): ResponseInterface
    {
        foreach ($headers as $name => $value) {
            if (! in_array($name, $except, true)) {
                // A download response starts with headers that forbid caching, which would be joined to ours.
                $response->removeHeader($name);
                $response->setHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * Answer a range request with one part of the file.
     */
    private function slice(ResponseInterface $response, string $full, int $size, string $from, string $to): ResponseInterface
    {
        if ($from === '') {
            // `bytes=-500` is the last 500 bytes.
            $start = max(0, $size - (int) $to);
            $end   = $size - 1;
        } else {
            $start = (int) $from;
            $end   = $to === '' ? $size - 1 : min((int) $to, $size - 1);
        }

        if ($start >= $size || $start > $end) {
            return $response->setStatusCode(416)->setHeader('Content-Range', 'bytes */' . $size);
        }

        $end    = min($end, $start + self::RANGE_LIMIT - 1);
        $handle = fopen($full, 'rb');
        fseek($handle, $start);
        $body = (string) fread($handle, $end - $start + 1);
        fclose($handle);

        return $response->setStatusCode(206)
            ->setHeader('Content-Range', 'bytes ' . $start . '-' . $end . '/' . $size)
            ->setBody($body);
    }
}
