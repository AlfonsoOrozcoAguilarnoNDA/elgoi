<?php
/**
 * EVE Online - Ship Readiness Dashboard
 * PHP 8.4 Procedural | Bootstrap 5.3.3 | DataTables 2.x (BS5) | Font Awesome 5.15.4
 *
 * Requires: include "../config.php" providing $link (mysqli)
 *
 * Logic:
 * - Ready to Fly: quantity = 1 AND has children (fitted modules) in EVE_ASSETS
 * - Hangar/Transport: quantity > 1 (seller stack) OR quantity = 1 without children (empty hull)
 * - Excludes toon_number = 2114226800 (control character)
 * - Summary list: ships per pilot (sum of quantity), sorted descending, with update link
 */

// ─── CONFIG ─────────────────────────────────────────────────────────
include "../config.php";
check_authorization();

const CONTROL_TOON = 2114226800;

// ─── HELPERS ────────────────────────────────────────────────────────

/** Null-safe HTML escape (PHP 8.4 deprecates passing NULL to htmlspecialchars). */
function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Base URL (scheme + domain) of the current request. */
function get_base_url(): string {
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function get_pocket6_badge(?string $pocket6): string {
    $pocket6 = strtoupper(trim((string)$pocket6));
    $map = [
        'NOKIA' => ['class' => 'danger',  'icon' => 'fa-exclamation-triangle'],
        'EXPER' => ['class' => 'success', 'icon' => 'fa-check-circle'],
        'CLEAN' => ['class' => 'primary', 'icon' => 'fa-shield-alt'],
        'SANGO' => ['class' => 'warning', 'icon' => 'fa-exclamation-circle'],
    ];

    if (isset($map[$pocket6])) {
        $m = $map[$pocket6];
        return sprintf(
            '<span class="badge text-bg-%s"><i class="fas %s me-1"></i>%s</span>',
            $m['class'], $m['icon'], h($pocket6)
        );
    }

    return sprintf(
        '<span class="badge text-bg-secondary"><i class="fas fa-question-circle me-1"></i>%s</span>',
        h($pocket6 !== '' ? $pocket6 : 'UNKNOWN')
    );
}

function get_substate_badge(?string $substate): string {
    $map = [
        'stack' => ['class' => 'info',      'label' => 'Seller Stack',   'icon' => 'fa-layer-group'],
        'empty' => ['class' => 'secondary', 'label' => 'Empty / No Fit', 'icon' => 'fa-box-open'],
    ];
    $m = $map[$substate ?? '']
