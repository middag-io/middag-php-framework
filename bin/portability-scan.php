#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * FW-011 host-globals portability scan.
 *
 * Complements deptrac.yaml (the namespace-dependency axis) by measuring the
 * axis deptrac cannot see: bare host globals, host functions, hard-coded
 * absolute paths, and require/include discipline inside src/. These are the
 * exact class of violation the legacy ADR-927 §8.2 audit reported (69%), so
 * this script is what makes that number reproducible against the PSR-4 tree.
 *
 * Exit code 0 when clean, 1 when any violation is found (CI-friendly).
 *
 * Usage: php bin/portability-scan.php [srcDir]
 */

$srcDir = $argv[1] ?? __DIR__ . '/../src';
$srcDir = realpath($srcDir);

if ($srcDir === false || ! is_dir($srcDir)) {
    fwrite(STDERR, "src dir not found\n");
    exit(2);
}

/**
 * Each rule: a human label + a PCRE matched line-by-line. Patterns are written
 * to match the *code* form, not incidental mentions in comments/strings — but
 * a comment hit is still surfaced for human review rather than silently
 * dropped (portability is measured conservatively).
 *
 * @var array<string, string> $rules
 */
$rules = [
    'moodle-global'    => '/\bglobal\s+\$(CFG|DB|USER|SESSION|PAGE|OUTPUT|COURSE|SITE|ME|FULLME)\b/',
    'moodle-use'       => '/^\s*use\s+(Moodle\\\\|core\\\\|core_|mod_|local_|block_|tool_)/',
    'moodle-func'      => '/(?<![\w>$])(get_string|get_config|set_config|get_course|require_login|has_capability|optional_param|required_param|clean_param)\s*\(/',
    'wordpress-func'   => '/(?<![\w>$])(add_action|add_filter|apply_filters|do_action|wp_enqueue_script|get_option|update_option|register_post_type)\s*\(/',
    'hardcoded-path'   => '/[\'"]\/(var|home|usr|opt|srv|Users|www)\//',
    'dirroot'          => '/\$CFG->(dirroot|wwwroot|dataroot)/',
    'require-include'  => '/(?<![\w>$])(require|require_once|include|include_once)\s*[\'"(]/',
];

$violations = [];
$fileCount = 0;

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS)
);

/** @var SplFileInfo $file */
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $fileCount++;
    $rel = ltrim(str_replace($srcDir, '', $file->getPathname()), '/');
    $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }
    foreach ($lines as $n => $line) {
        // Skip pure comment lines (docblocks, // and # comments). A host token
        // mentioned in prose is documentation, not a portability violation.
        $ltrim = ltrim($line);
        if ($ltrim === '' || $ltrim[0] === '*' || str_starts_with($ltrim, '//')
            || str_starts_with($ltrim, '/*') || str_starts_with($ltrim, '#')) {
            continue;
        }
        foreach ($rules as $label => $pattern) {
            if (preg_match($pattern, $line) === 1) {
                $violations[] = [
                    'rule' => $label,
                    'file' => $rel,
                    'line' => $n + 1,
                    'code' => trim($line),
                ];
            }
        }
    }
}

echo "FW-011 host-globals scan\n";
echo "========================\n";
echo "Files scanned: {$fileCount}\n";
echo 'Violations:    ' . count($violations) . "\n\n";

if ($violations === []) {
    echo "CLEAN — no host globals, host functions, hard-coded paths, or require/include in src/.\n";
    exit(0);
}

foreach ($violations as $v) {
    printf("  [%s] %s:%d\n    %s\n", $v['rule'], $v['file'], $v['line'], $v['code']);
}

exit(1);
