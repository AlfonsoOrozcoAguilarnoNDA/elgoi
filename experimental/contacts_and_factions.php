<?php
/**
 * EVE Online Pilot Contacts & Faction Standings Dashboard
 * 
 * PHP Procedural | Bootstrap 6.4.x | Font Awesome 5.15.4 (jsDelivr)
 * DataTables | License: GPL
 * 
 * Reads ALL pilots from the PILOTS table automatically.
 * Requires: config.php with $link (mysqli connection)
 */

// Prevent direct access if config is missing
define('APP_RUNNING', true);

// Include database configuration
if (!file_exists('config.php')) {
    die('<div class="alert alert-danger m-4">Error: config.php not found. Please create it with your database credentials.</div>');
}
include '../config.php';

// Verify $link exists and is valid
if (!isset($link) || !($link instanceof mysqli)) {
    die('<div class="alert alert-danger m-4">Error: $link is not a valid mysqli connection. Check your config.php</div>');
}

// ============================================================================
// EVE FACTION NAMES MAP (for display instead of raw IDs)
// ============================================================================
$FACTION_NAMES = [
    500001 => 'Caldari State',
    500002 => 'Minmatar Republic',
    500003 => 'Amarr Empire',
    500004 => 'Gallente Federation',
    500005 => 'Jove Empire',
    500006 => 'CONCORD Assembly',
    500007 => 'Ammatar Mandate',
    500008 => 'Khanid Kingdom',
    500009 => 'The Syndicate',
    500010 => 'Guristas Pirates',
    500011 => "Angel Cartel",
    500012 => "Blood Raider Covenant",
    500013 => "The InterBus",
    500014 => "ORE",
    500015 => "Thukker Tribe",
    500016 => "Servant Sisters of EVE",
    500017 => "The Society",
    500018 => "Mordu's Legion Command",
    500019 => "Sansha's Nation",
    500020 => "Serpentis",
    500021 => "Unknown",
    500022 => "Unknown",
    500023 => "Unknown",
    500024 => "Unknown",
    500025 => "Unknown",
    500026 => "Unknown",
    500027 => "Unknown",
    500028 => "Unknown",
    500029 => "Unknown",
    500030 => "Unknown",
    500031 => "Unknown",
    500032 => "Unknown",
    500033 => "Unknown",
    500034 => "Unknown",
    500035 => "Unknown",
    500036 => "Unknown",
    500037 => "Unknown",
    500038 => "Unknown",
    500039 => "Unknown",
    500040 => "Unknown",
];

// ============================================================================
// CONFIGURABLE PILOT GROUPS (Modify these as needed - use toon_name strings)
// ============================================================================
$GROUP_1_PILOTS = [
    // Add pilot names here as strings, e.g.:
    // 'Lady Experiment',
    // 'Aridam',
];

$GROUP_2_PILOTS = [
    // Add pilot names here as strings, e.g.:
    // 'Pilot Alpha',
    // 'Pilot Beta',
];

// ============================================================================
// EVE IMAGE URL BUILDER
// ============================================================================
function getPilotPortraitUrl($toon_number, $size = 128) {
    return 'https://images.evetech.net/characters/' . intval($toon_number) . '/portrait?size=' . intval($size);
}

// ============================================================================
// GET FACTION DISPLAY NAME
// ============================================================================
function getFactionName($faction_id, $default = null) {
    global $FACTION_NAMES;
    if ($default !== null) return $default;
    return $FACTION_NAMES[intval($faction_id)] ?? ('Faction ' . $faction_id);
}

// ============================================================================
// FETCH ALL PILOTS FROM DATABASE (ordered by DOB)
// ============================================================================
function fetchAllPilots($link) {
    $sql = "SELECT toon_number, toon_name, parent_toon_number, corporation_name, 
                   tradefield, pocket6, gf, DOB, contacts, standings 
            FROM PILOTS 
            ORDER BY DOB ASC";

    $result = $link->query($sql);
    if (!$result) {
        error_log("Query failed: " . $link->error);
        return [];
    }

    $pilots = [];
    while ($row = $result->fetch_assoc()) {
        $pilots[] = $row;
    }
    return $pilots;
}

// ============================================================================
// FETCH PILOTS BY NAMES (for custom groups)
// ============================================================================
function fetchPilotsByNames($link, $pilot_names) {
    if (empty($pilot_names)) return [];

    $placeholders = implode(',', array_fill(0, count($pilot_names), '?'));
    $types = str_repeat('s', count($pilot_names));

    $sql = "SELECT toon_number, toon_name, parent_toon_number, corporation_name, 
                   tradefield, pocket6, gf, DOB, contacts, standings 
            FROM PILOTS 
            WHERE toon_name IN ($placeholders) 
            ORDER BY DOB ASC";

    $stmt = $link->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $link->error);
        return [];
    }

    $stmt->bind_param($types, ...$pilot_names);
    $stmt->execute();
    $result = $stmt->get_result();

    $pilots = [];
    while ($row = $result->fetch_assoc()) {
        $pilots[] = $row;
    }
    $stmt->close();
    return $pilots;
}

// ============================================================================
// PARSE CONTACTS (ignore standing == 0)
// ============================================================================
function parseContacts($contacts_json) {
    if (empty($contacts_json)) return [];

    $clean = stripslashes($contacts_json);
    $data = json_decode($clean, true);
    if (!is_array($data)) return [];

    $filtered = [];
    foreach ($data as $item) {
        if (isset($item['standing']) && floatval($item['standing']) != 0) {
            $filtered[] = $item;
        }
    }

    // Sort by standing DESC
    usort($filtered, function($a, $b) {
        return floatval($b['standing']) <=> floatval($a['standing']);
    });

    return $filtered;
}

// ============================================================================
// PARSE FACTION STANDINGS (only from_type == 'faction', show zeros)
// ============================================================================
function parseFactionStandings($standings_json) {
    if (empty($standings_json)) return [];

    $clean = stripslashes($standings_json);
    $data = json_decode($clean, true);
    if (!is_array($data)) return [];

    $filtered = [];
    foreach ($data as $item) {
        if (isset($item['from_type']) && $item['from_type'] === 'faction') {
            $filtered[] = $item;
        }
    }

    // Sort by standing DESC
    usort($filtered, function($a, $b) {
        return floatval($b['standing']) <=> floatval($a['standing']);
    });

    return $filtered;
}

// ============================================================================
// RENDER PILOT CARD HEADER
// ============================================================================
function renderPilotHeader($pilot) {
    $portrait = getPilotPortraitUrl($pilot['toon_number'], 128);
    $name = htmlspecialchars($pilot['toon_name']);
    $corp = htmlspecialchars($pilot['corporation_name'] ?? 'N/A');
    $pocket = htmlspecialchars($pilot['pocket6'] ?? 'CLEAN');
    $trade = htmlspecialchars($pilot['tradefield'] ?? 'n/a');
    $gf = intval($pilot['gf'] ?? 0);
    $dob = $pilot['DOB'] ? date('Y-m-d', strtotime($pilot['DOB'])) : 'Unknown';
    $parent = intval($pilot['parent_toon_number'] ?? 0);

    $gf_badge = $gf > 0 
        ? '<span class="badge bg-danger ms-1" title="GF Flag"><i class="fas fa-flag"></i> ' . $gf . '</span>' 
        : '';

    $pocket_badge = $pocket !== 'CLEAN' 
        ? '<span class="badge bg-warning text-dark ms-1">' . $pocket . '</span>' 
        : '';

    $parent_note = $parent > 0 
        ? '<div class="small text-info"><i class="fas fa-link fa-xs"></i> Alt of ' . $parent . '</div>' 
        : '';

    return '
        <div class="text-center">
            <img src="' . $portrait . '" alt="' . $name . '" class="rounded mb-2" style="width:128px;height:128px;object-fit:cover;" loading="lazy">
            <h5 class="mb-1">' . $name . $gf_badge . '</h5>
            <div class="small text-muted">' . $corp . '</div>
            <div class="small">' . $trade . $pocket_badge . '</div>
            <div class="small text-secondary">DOB: ' . $dob . '</div>
            ' . $parent_note . '
        </div>';
}

// ============================================================================
// RENDER NESTED TABLE FOR CONTACTS
// ============================================================================
function renderContactsTable($contacts) {
    if (empty($contacts)) {
        return '<div class="text-muted small fst-italic p-2">No contacts with non-zero standing</div>';
    }

    $html = '<table class="table table-sm table-bordered table-striped mb-0" style="font-size:0.78rem;">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Type</th>
                <th>Standing</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($contacts as $c) {
        $standing = floatval($c['standing']);
        $standing_class = $standing > 0 ? 'text-success' : 'text-danger';
        $standing_icon = $standing > 0 ? 'fa-arrow-up' : 'fa-arrow-down';

        $html .= '<tr>
            <td>' . htmlspecialchars($c['contact_id'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($c['contact_type'] ?? 'N/A') . '</td>
            <td class="' . $standing_class . '"><i class="fas ' . $standing_icon . ' fa-xs"></i> ' . number_format($standing, 2) . '</td>
        </tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}

// ============================================================================
// RENDER NESTED TABLE FOR FACTION STANDINGS
// ============================================================================
function renderFactionStandingsTable($standings) {
    if (empty($standings)) {
        return '<div class="text-muted small fst-italic p-2">No faction standings</div>';
    }

    $html = '<table class="table table-sm table-bordered table-striped mb-0" style="font-size:0.78rem;">
        <thead class="table-dark">
            <tr>
                <th>Faction</th>
                <th>Standing</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($standings as $s) {
        $standing = floatval($s['standing']);
        $standing_class = $standing > 0 ? 'text-success' : ($standing < 0 ? 'text-danger' : 'text-muted');
        $standing_icon = $standing > 0 ? 'fa-arrow-up' : ($standing < 0 ? 'fa-arrow-down' : 'fa-minus');
        $faction_name = getFactionName($s['from_id'] ?? 0);

        $html .= '<tr>
            <td>' . htmlspecialchars($faction_name) . '</td>
            <td class="' . $standing_class . '"><i class="fas ' . $standing_icon . ' fa-xs"></i> ' . number_format($standing, 2) . '</td>
        </tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}

// ============================================================================
// BUILD PILOT DATA FOR DISPLAY
// ============================================================================
function buildPilotData($pilots, $mode = 'contacts') {
    $result = [];
    foreach ($pilots as $p) {
        if ($mode === 'contacts') {
            $parsed = parseContacts($p['contacts']);
            if (!empty($parsed)) {
                $p['_parsed'] = $parsed;
                $p['_count'] = count($parsed);
                $result[] = $p;
            }
        } else {
            $parsed = parseFactionStandings($p['standings']);
            if (!empty($parsed)) {
                $p['_parsed'] = $parsed;
                $p['_count'] = count($parsed);
                $result[] = $p;
            }
        }
    }
    return $result;
}

// ============================================================================
// FETCH DATA - AUTO MODE: reads ALL pilots from DB
// ============================================================================
$all_pilots = fetchAllPilots($link);

// Build data for both tabs from ALL pilots
$contacts_pilots = buildPilotData($all_pilots, 'contacts');
$standings_pilots = buildPilotData($all_pilots, 'standings');

// ============================================================================
// HTML OUTPUT
// ============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Pilot Contacts & Standings Dashboard</title>

    <!-- Bootstrap 6.4.x CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@6.4.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <style>
        :root {
            --eve-dark: #0d1117;
            --eve-card: #161b22;
            --eve-border: #30363d;
            --eve-text: #c9d1d9;
            --eve-text-muted: #8b949e;
            --eve-accent: #58a6ff;
            --eve-success: #3fb950;
            --eve-danger: #f85149;
        }
        body { 
            background-color: var(--eve-dark); 
            color: var(--eve-text); 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .card { 
            background-color: var(--eve-card); 
            border-color: var(--eve-border); 
        }
        .nav-tabs { 
            border-bottom-color: var(--eve-border); 
        }
        .nav-tabs .nav-link { 
            color: var(--eve-text-muted); 
            border: none; 
            padding: 0.75rem 1.25rem;
            font-weight: 500;
        }
        .nav-tabs .nav-link:hover { 
            color: var(--eve-text); 
            border-color: var(--eve-border); 
            background-color: rgba(48, 54, 61, 0.3);
        }
        .nav-tabs .nav-link.active { 
            background-color: var(--eve-card); 
            color: var(--eve-accent); 
            border-color: var(--eve-border) var(--eve-border) var(--eve-card); 
            font-weight: 600;
        }
        .table { 
            color: var(--eve-text); 
            margin-bottom: 0;
        }
        .table thead th { 
            background-color: #21262d; 
            color: #f0f6fc; 
            border-color: var(--eve-border); 
            font-size: 0.8rem;
            white-space: nowrap;
        }
        .table td { 
            border-color: var(--eve-border); 
            padding: 0.5rem;
        }
        .table-striped > tbody > tr:nth-of-type(odd) > * { 
            background-color: rgba(48, 54, 61, 0.3); 
        }
        .table-hover > tbody > tr:hover > * { 
            background-color: rgba(48, 54, 61, 0.5); 
        }
        .pilot-col { 
            min-width: 240px; 
            max-width: 320px;
            vertical-align: top !important; 
        }
        .nested-table-container { 
            max-height: 500px; 
            overflow-y: auto;
            border-radius: 0.375rem;
        }
        .nested-table-container::-webkit-scrollbar {
            width: 6px;
        }
        .nested-table-container::-webkit-scrollbar-track {
            background: var(--eve-dark);
        }
        .nested-table-container::-webkit-scrollbar-thumb {
            background: var(--eve-border);
            border-radius: 3px;
        }
        .badge { 
            font-size: 0.7rem; 
            font-weight: 600;
        }
        .dt-info, .dt-length, .dt-paging { 
            color: var(--eve-text-muted) !important; 
        }
        .dt-input { 
            background-color: #21262d !important; 
            color: var(--eve-text) !important; 
            border-color: var(--eve-border) !important; 
        }
        .dt-paging .dt-paging-button { 
            color: var(--eve-text) !important; 
        }
        .dt-paging .dt-paging-button.current { 
            background-color: #21262d !important; 
            border-color: var(--eve-border) !important; 
        }
        .dt-paging .dt-paging-button:hover { 
            background-color: var(--eve-border) !important; 
        }
        h2.section-note { 
            color: var(--eve-accent); 
            font-size: 1.1rem; 
            margin-bottom: 1rem; 
            padding: 0.75rem 1rem; 
            background: #21262d; 
            border-radius: 0.5rem; 
            border-left: 4px solid var(--eve-accent); 
        }
        .tab-content {
            padding: 1rem 0;
        }
        .pilot-header-img {
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            transition: transform 0.2s ease;
        }
        .pilot-header-img:hover {
            transform: scale(1.05);
        }
        .count-badge {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }
        .text-success { color: var(--eve-success) !important; }
        .text-danger { color: var(--eve-danger) !important; }

        /* Responsive */
        @media (max-width: 768px) {
            .pilot-col { 
                min-width: 200px; 
                max-width: 260px;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <h1 class="mb-4 text-center">
        <i class="fas fa-space-shuttle me-2"></i>EVE Pilot Dashboard
        <div class="small text-muted mt-2">Contacts & Faction Standings Overview</div>
    </h1>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-3" id="pilotTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts-pane" type="button" role="tab">
                <i class="fas fa-address-book me-1"></i> Contacts 
                <span class="badge bg-primary ms-1"><?php echo count($contacts_pilots); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="standings-tab" data-bs-toggle="tab" data-bs-target="#standings-pane" type="button" role="tab">
                <i class="fas fa-flag me-1"></i> Faction Standings 
                <span class="badge bg-primary ms-1"><?php echo count($standings_pilots); ?></span>
            </button>
        </li>
    </ul>

    <!-- Tabs Content -->
    <div class="tab-content" id="pilotTabsContent">

        <!-- ==================== TAB 1: CONTACTS ==================== -->
        <div class="tab-pane fade show active" id="contacts-pane" role="tabpanel">
            <h2 class="section-note">
                <i class="fas fa-users me-2"></i>Contacts Breakdown — All Pilots with Non-Zero Standings (sorted by DOB)
            </h2>

            <?php if (empty($contacts_pilots)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>No pilots with contacts found in the database.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="contactsTable">
                        <thead>
                            <tr>
                                <?php foreach ($contacts_pilots as $pilot): ?>
                                    <th class="pilot-col">
                                        <?php echo renderPilotHeader($pilot); ?>
                                        <div class="text-center mt-2">
                                            <span class="badge bg-info count-badge">
                                                <i class="fas fa-address-book me-1"></i><?php echo $pilot['_count']; ?> contacts
                                            </span>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php foreach ($contacts_pilots as $pilot): ?>
                                    <td class="pilot-col">
                                        <div class="nested-table-container">
                                            <?php echo renderContactsTable($pilot['_parsed']); ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==================== TAB 2: FACTION STANDINGS ==================== -->
        <div class="tab-pane fade" id="standings-pane" role="tabpanel">
            <h2 class="section-note">
                <i class="fas fa-flag me-2"></i>Faction Standings Distribution Comparison — All Pilots (sorted by DOB)
            </h2>

            <?php if (empty($standings_pilots)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>No pilots with faction standings found in the database.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="standingsTable">
                        <thead>
                            <tr>
                                <?php foreach ($standings_pilots as $pilot): ?>
                                    <th class="pilot-col">
                                        <?php echo renderPilotHeader($pilot); ?>
                                        <div class="text-center mt-2">
                                            <span class="badge bg-info count-badge">
                                                <i class="fas fa-flag me-1"></i><?php echo $pilot['_count']; ?> factions
                                            </span>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php foreach ($standings_pilots as $pilot): ?>
                                    <td class="pilot-col">
                                        <div class="nested-table-container">
                                            <?php echo renderFactionStandingsTable($pilot['_parsed']); ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Footer -->
    <footer class="mt-5 text-center text-muted small">
        <p><i class="fas fa-code me-1"></i> EVE Pilot Dashboard &mdash; Licensed under GPL</p>
        <p class="text-secondary" style="font-size:0.75rem;">Auto-loaded <?php echo count($all_pilots); ?> pilots from database</p>
    </footer>
</div>

<!-- Bootstrap 6.4.x JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@6.4.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery (required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTables for Contacts
    if ($('#contactsTable').length && $('#contactsTable th').length > 0) {
        $('#contactsTable').DataTable({
            paging: false,
            searching: true,
            info: true,
            ordering: false,
            scrollX: true,
            autoWidth: false,
            language: {
                emptyTable: "No contact data available",
                info: "Showing _TOTAL_ pilots",
                infoEmpty: "No pilots to show",
                search: "<i class='fas fa-search'></i> Search:"
            }
        });
    }

    // Initialize DataTables for Standings
    if ($('#standingsTable').length && $('#standingsTable th').length > 0) {
        $('#standingsTable').DataTable({
            paging: false,
            searching: true,
            info: true,
            ordering: false,
            scrollX: true,
            autoWidth: false,
            language: {
                emptyTable: "No standings data available",
                info: "Showing _TOTAL_ pilots",
                infoEmpty: "No pilots to show",
                search: "<i class='fas fa-search'></i> Search:"
            }
        });
    }
});
</script>

</body>
</html>
