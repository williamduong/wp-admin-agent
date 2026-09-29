<?php

defined('ABSPATH') || exit;

/**
 * Safely downloads a remote resource (image, file) via URL.
 *
 * Returns metadata + a local temp path. Caller is responsible for
 * moving the file and cleaning up via wp_delete_file($result['path']).
 */
class WRADMIN_Resource_Fetcher {
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    private const ALLOWED_MIME = [
        'image/png', 'image/jpeg', 'image/gif',
        'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon',
        'image/svg+xml',
    ];

    /**
     * @throws RuntimeException on any validation or download failure
     * @return array{ path: string, mime: string, filename: string, size: int }
     */
    public function fetch_image(string $url): array {
        $validated_url = WRADMIN_Network_Guard::public_url($url);
        if (is_wp_error($validated_url)) {
            throw new RuntimeException(esc_html($validated_url->get_error_message()));
        }
        $url = esc_url_raw((string) $validated_url);

        // HEAD first — check content-type and size without downloading
        $head = wp_safe_remote_head($url, [
            'timeout'    => 10,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; WRADMIN-Bot',
            'redirection' => 3,
        ]);

        if (is_wp_error($head)) {
            throw new RuntimeException('HEAD request failed: ' . esc_html($head->get_error_message()));
        }

        $head_code = wp_remote_retrieve_response_code($head);
        if ($head_code !== 200) {
            // Some servers don't support HEAD — fall through to GET
        } else {
            $this->validate_headers(wp_remote_retrieve_headers($head));
        }

        if (!function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) {
            throw new RuntimeException('Download failed: ' . esc_html($tmp->get_error_message()));
        }

        $size = filesize($tmp);
        if ($size === false || $size > self::MAX_BYTES) {
            wp_delete_file($tmp);
            throw new RuntimeException('File exceeds the 5 MB size limit.');
        }

        $content_type_header = $head_code === 200
            ? (string) strtok(wp_remote_retrieve_header($head, 'content-type') ?: '', ';')
            : '';
        $mime = $this->detect_mime($tmp, $url, $content_type_header);

        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            wp_delete_file($tmp);
            throw new RuntimeException('File type ' . esc_html($mime) . ' is not allowed.');
        }

        if ($mime === 'image/svg+xml') {
            try {
                $this->sanitize_svg($tmp);
            } catch (Throwable $e) {
                wp_delete_file($tmp);
                throw $e;
            }
            $size = filesize($tmp);
        }

        $filename = $this->extract_filename($url, $mime);

        return [
            'path'     => $tmp,
            'mime'     => $mime,
            'filename' => $filename,
            'size'     => (int) $size,
        ];
    }

    private function detect_mime(string $path, string $url, string $header_mime): string {
        $ext = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        // SVG: trust header or extension + peek at content
        if ($header_mime === 'image/svg+xml' || $ext === 'svg') {
            $peek = file_get_contents($path, false, null, 0, 512) ?: '';
            if (str_contains($peek, '<svg') || str_contains($peek, '<?xml')) {
                return 'image/svg+xml';
            }
        }
        // mime_content_type is the primary detector
        $detected = function_exists('mime_content_type') ? (mime_content_type($path) ?: '') : '';
        // Remap XML variants → SVG when the extension says so
        if (in_array($detected, ['text/xml', 'application/xml', 'text/plain', 'text/html'], true) && $ext === 'svg') {
            return 'image/svg+xml';
        }
        // Fall back to response Content-Type header
        return $detected ?: $header_mime;
    }

    private function validate_headers(object $headers): void {
        $content_length = (int) ($headers['content-length'] ?? 0);
        if ($content_length > self::MAX_BYTES) {
            throw new RuntimeException(
                esc_html(sprintf('File too large (%d bytes). Limit: %d bytes.', $content_length, self::MAX_BYTES))
            );
        }

        $content_type = strtok($headers['content-type'] ?? '', ';');
        if ($content_type && !in_array($content_type, self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Content-Type ' . esc_html((string) $content_type) . ' not allowed.');
        }
    }

    private function sanitize_svg(string $path): void {
        $svg = file_get_contents($path);
        if (!is_string($svg)
            || stripos($svg, '<!DOCTYPE') !== false
            || stripos($svg, '<!ENTITY') !== false
            || !class_exists('DOMDocument')) {
            throw new RuntimeException('The SVG could not be safely processed.');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$document->documentElement || strtolower($document->documentElement->localName) !== 'svg') {
            throw new RuntimeException('The SVG is invalid.');
        }

        $allowed_elements = [
            'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon',
            'title', 'desc', 'defs', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask',
        ];
        $allowed_attributes = [
            'xmlns', 'viewbox', 'width', 'height', 'fill', 'stroke', 'stroke-width',
            'stroke-linecap', 'stroke-linejoin', 'd', 'x', 'y', 'x1', 'x2', 'y1', 'y2',
            'cx', 'cy', 'r', 'rx', 'ry', 'points', 'transform', 'opacity', 'fill-opacity',
            'stroke-opacity', 'offset', 'stop-color', 'stop-opacity', 'clip-path', 'mask',
            'role', 'aria-hidden', 'focusable',
        ];

        $nodes = iterator_to_array($document->getElementsByTagName('*'));
        foreach (array_reverse($nodes) as $node) {
            if (!in_array(strtolower($node->localName), $allowed_elements, true)) {
                $node->parentNode?->removeChild($node);
                continue;
            }
            foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = (string) $attribute->nodeValue;
                if (!in_array($name, $allowed_attributes, true)
                    || str_starts_with($name, 'on')
                    || preg_match('/(?:javascript:|data:|url\s*\()/i', $value)) {
                    $node->removeAttributeNode($attribute);
                }
            }
        }

        $clean = $document->saveXML($document->documentElement);
        if (!is_string($clean) || file_put_contents($path, $clean) === false) {
            throw new RuntimeException('The sanitized SVG could not be saved.');
        }
    }

    private function extract_filename(string $url, string $mime): string {
        $path = wp_parse_url($url, PHP_URL_PATH) ?? '';
        $name = sanitize_file_name(basename($path)) ?: 'image';

        // Strip query strings that may have crept in
        $name = preg_replace('/[?#].*/', '', $name);

        $ext_map = [
            'image/png'                    => 'png',
            'image/jpeg'                   => 'jpg',
            'image/gif'                    => 'gif',
            'image/webp'                   => 'webp',
            'image/x-icon'                 => 'ico',
            'image/vnd.microsoft.icon'     => 'ico',
            'image/svg+xml'                => 'svg',
        ];

        $ext      = $ext_map[$mime] ?? 'png';
        $has_ext  = pathinfo($name, PATHINFO_EXTENSION) !== '';

        return $has_ext ? $name : "$name.$ext";
    }
}
