<?php

use StarCore\Service\HyperHooks;
use Masterminds\HTML5;

// Page HTML data filter on Frontend controller
HyperHooks::getInstance()->register(hook('PagingSystemBackend.controller:frontend:index:pagehtml'), function ($pageHtml) {

    // Return if no HTML is provided
    if (empty($pageHtml)) {
        return $pageHtml; // No HTML to process
    }

    // Initialize HTML5 parser
    $html5 = new HTML5([
        'disable_html_ns' => true, // Better for modern HTML
        'preserve_whitespace' => true,
    ]);

    // Load HTML
    $dom = $html5->loadHTML($pageHtml);

    // Ensure document structure exists
    $head = $dom->getElementsByTagName('head')->item(0);
    $body = $dom->getElementsByTagName('body')->item(0);

    if (!$head) {
        $head = $dom->createElement('head');
        $htmlElement = $dom->getElementsByTagName('html')->item(0) ?: $dom->appendChild($dom->createElement('html'));
        $htmlElement->insertBefore($head, $body ?? null);
    }

    if (!$body) {
        $body = $dom->createElement('body');
        $dom->documentElement->appendChild($body);
    }

    // Phase 1: Collect all elements in their original order
    $allNodes = [];
    $xpath = new DOMXPath($dom);

    // Get all meta and script nodes in document order
    foreach ($xpath->query('//meta') as $node) {
        $allNodes[] = [
            'node' => $node,
            'is_meta' => true,
            'is_head_script' => false
        ];
    }

    // Get all script nodes
    foreach ($xpath->query('//script') as $node) {
        $allNodes[] = [
            'node' => $node,
            'is_meta' => false,
            'is_head_script' => ($node->parentNode->nodeName === 'head')
        ];
    }

    // Phase 2: Reorganize nodes
    foreach ($allNodes as $item) {
        $node = $item['node'];

        if ($item['is_meta']) {
            // Move meta to head if not already there
            if ($node->parentNode !== $head) {
                $node->parentNode->removeChild($node);
                $head->appendChild($node);
            }
        } elseif ($item['is_head_script']) {
            // Keep head scripts in head
            continue;
        } else {
            // Move other scripts to end of body
            if ($node->parentNode !== $body) {
                $node->parentNode->removeChild($node);
                $body->appendChild($node);
            }
        }
    }

    // Save the modified HTML
    $modifiedHtml = $html5->saveHTML($dom);

    return $modifiedHtml;
});
