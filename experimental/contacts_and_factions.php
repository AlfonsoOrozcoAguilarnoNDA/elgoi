<?php
/**
 * EVE Online Pilot Contacts & Faction Standings Dashboard
 * 
 * PHP Procedural | Bootstrap 4.6.x | Font Awesome 5.15.4 (jsDelivr)
 * License: GPL
 * 
 * Reads ALL pilots from the PILOTS table automatically.
 * Requires: config.php with $link (mysqli connection)
 */

// Prevent direct access if config is missing
define('APP_RUNNING', true);

// Include database configuration
if (!file_exists('../config.php')) {
    die('<div class="alert alert-danger m-4">Error: config.php not found. Please create it with your database credentials.</div>');
}
include '../config.php';
check_authorization();

// Verify $link exists and is valid
if (!isset($link) || !($link instanceof mysqli)) {
    die('<div class="alert alert-danger m-4">Error: $link is not a valid mysqli connection. Check your config.php</div>');
}

// ============================================================================
// EVE FACTION NAMES MAP (for display instead of raw IDs)
// https://evemissioneer.com/factions# can check them
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
    500021 => "Unknown 21",
    500022 => "Unknown 22",
    500023 => "Unknown 23",
    500024 => "Unknown 24",
    500025 => "Rogue Drones",
    500026 => "Unknown 26",
    500027 => "Unknown 27",
    500028 => "Unknown 28",
    500029 => "Deathless Circle",
    500030 => "Unknown 30",
    500031 => "Unknown 31",
    500032 => "Unknown 32",
    500033 => "Unknown 33",
    500034 => "Unknown 34",
    500035 => "Unknown 35",
    500036 => "Unknown 36",
    500037 => "Unknown 37",
    500038 => "Unknown 38",
    500039 => "Unknown 39",
    500040 => "Unknown 40",
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
// EVEWHO LINK BUILDER
// ============================================================================
function evewhoLink($id, $type, $label = null) {
    $id = intval($id);
    $type = strtolower($type);
    $valid_types = ['character', 'corporation', 'alliance'];

    if (!in_array($type, $valid_types)) {
        return htmlspecialchars($label ?? $id);
    }

    $display = htmlspecialchars($label ?? $id);
    return '<a href="https://evewho.com/' . $type . '/' . $id . '" target="_blank" rel="noopener noreferrer" class="evewho-link">' . $display . ' <i class="fas fa-external-link-alt fa-xs"></i></a>';
}

// ============================================================================
// FETCH ALL PILOTS FROM DATABASE (ordered by DOB)
// ============================================================================
function fetchAllPilots($link) {
    $sql = "SELECT toon_number, toon_name, parent_toon_number, corporation_name, 
                   tradefield, pocket6, gf, DOB, contacts, standings, corpID, allianceID
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
    $corpID = intval($pilot['corpID'] ?? 0);
    $allianceID = intval($pilot['allianceID'] ?? 0);

    // GF flag: red if 1, gray if 0
    if ($gf === 1) {
        $gf_display = '<span class="badge badge-danger gf-flag" title="GF Flag Active"><i class="fas fa-flag"></i></span>';
    } else {
        $gf_display = '<span class="badge badge-secondary gf-flag" title="GF Flag Inactive"><i class="fas fa-flag"></i></span>';
    }

    // Build corp/alliance text (NO evewho links in header)
    $corp_display = $corp;
    $alliance_display = $allianceID > 0 ? 'Alliance: ' . $allianceID : '';

    return '
        <div class="text-center">
            <img src="' . $portrait . '" alt="' . $name . '" class="rounded mb-2 pilot-header-img" style="width:128px;height:128px;object-fit:cover;" loading="lazy">
            <h5 class="mb-1">' . $name . ' ' . $gf_display . '</h5>
            <div class="small corp-name">' . htmlspecialchars($corp_display) . '</div>
            ' . ($alliance_display ? '<div class="small alliance-name">' . htmlspecialchars($alliance_display) . '</div>' : '') . '
            <div class="small trade-field">' . htmlspecialchars($trade) . '</div>
            <div class="small pocket-badge">' . htmlspecialchars($pocket) . '</div>
        </div>';
}

// ============================================================================
// RENDER NESTED TABLE FOR CONTACTS
// ============================================================================
function renderContactsTable($contacts) {
    if (empty($contacts)) {
        return '<div class="text-muted small font-italic p-2">No contacts with non-zero standing</div>';
    }

    $html = '<table class="table table-sm table-bordered table-striped mb-0" style="font-size:0.78rem;">
        <thead class="thead-dark">
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
        $contact_id = intval($c['contact_id'] ?? 0);
        $contact_type = $c['contact_type'] ?? 'character';

        $id_link = evewhoLink($contact_id, $contact_type, $contact_id);

        $html .= '<tr>
            <td>' . $id_link . '</td>
            <td>' . htmlspecialchars($contact_type) . '</td>
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
        return '<div class="text-muted small font-italic p-2">No faction standings</div>';
    }

    $html = '<table class="table table-sm table-bordered table-striped mb-0" style="font-size:0.78rem;">
        <thead class="thead-dark">
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
        $faction_id = intval($s['from_id'] ?? 0);
        $faction_name = getFactionName($faction_id);

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

    <!-- Bootstrap 4.6.x CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">

    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">

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
            --eve-link: #79c0ff;
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
        .table-striped tbody tr:nth-of-type(odd) { 
            background-color: rgba(48, 54, 61, 0.3); 
        }
        .table-hover tbody tr:hover { 
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

        /* EVEWHO Links */
        .evewho-link {
            color: var(--eve-link);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .evewho-link:hover {
            color: var(--eve-accent);
            text-decoration: underline;
        }
        .evewho-link i {
            font-size: 0.6rem;
            opacity: 0.7;
        }

        /* Pilot header styling */
        .gf-flag {
            font-size: 0.65rem;
            padding: 0.25rem 0.4rem;
            vertical-align: middle;
        }
        .corp-name {
            color: var(--eve-text-muted);
            margin-bottom: 0.2rem;
        }
        .alliance-name {
            color: #a371f7;
            margin-bottom: 0.2rem;
        }
        .trade-field {
            color: var(--eve-text-muted);
            font-style: italic;
            margin-bottom: 0.2rem;
        }
        .pocket-badge {
            color: #d29922;
            font-weight: 600;
            padding: 0.15rem 0.5rem;
            background: rgba(210, 153, 34, 0.1);
            border-radius: 0.25rem;
            display: inline-block;
            font-size: 0.8rem;
        }

        /* Bootstrap 4 overrides for dark theme */
        .alert-warning {
            background-color: rgba(210, 153, 34, 0.1);
            border-color: #d29922;
            color: #d29922;
        }
        .badge-danger {
            background-color: var(--eve-danger);
        }
        .badge-secondary {
            background-color: #6e7681;
        }
        .badge-info {
            background-color: var(--eve-accent);
        }
        .badge-primary {
            background-color: #1f6feb;
        }

        /* Hide column button */
        .hide-col-btn {
            margin-top: 0.5rem;
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border: 1px solid var(--eve-border);
            background: transparent;
            color: var(--eve-text-muted);
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .hide-col-btn:hover {
            background-color: var(--eve-danger);
            color: #fff;
            border-color: var(--eve-danger);
        }

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
        <i class="fas fa-space-shuttle mr-2"></i>EVE Pilot Dashboard
        <div class="small text-muted mt-2">Contacts & Faction Standings Overview</div>
    </h1>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-3" id="pilotTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="contacts-tab" data-toggle="tab" href="#contacts-pane" role="tab">
                <i class="fas fa-address-book mr-1"></i> Contacts 
                <span class="badge badge-primary ml-1"><?php echo count($contacts_pilots); ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="standings-tab" data-toggle="tab" href="#standings-pane" role="tab">
                <i class="fas fa-flag mr-1"></i> Faction Standings 
                <span class="badge badge-primary ml-1"><?php echo count($standings_pilots); ?></span>
            </a>
        </li>
    </ul>

    <!-- Tabs Content -->
    <div class="tab-content" id="pilotTabsContent">

        <!-- ==================== TAB 1: CONTACTS ==================== -->
        <div class="tab-pane fade show active" id="contacts-pane" role="tabpanel">
            <h2 class="section-note">
                <i class="fas fa-users mr-2"></i>Contacts Breakdown — All Pilots with Non-Zero Standings (sorted by DOB)
            </h2>

            <?php if (empty($contacts_pilots)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle mr-2"></i>No pilots with contacts found in the database.
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
                                            <span class="badge badge-info count-badge">
                                                <i class="fas fa-address-book mr-1"></i><?php echo $pilot['_count']; ?> contacts
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
                <i class="fas fa-flag mr-2"></i>Faction Standings Distribution Comparison — All Pilots (sorted by DOB)
            </h2>

            <?php if (empty($standings_pilots)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle mr-2"></i>No pilots with faction standings found in the database.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="standingsTable">
                        <thead>
                            <tr>
                                <?php $colIndex = 0; ?>
                                <?php foreach ($standings_pilots as $pilot): ?>
                                    <th class="pilot-col standings-col" data-col-index="<?php echo $colIndex; ?>">
                                        <?php echo renderPilotHeader($pilot); ?>
                                        <div class="text-center mt-2">
                                            <span class="badge badge-info count-badge">
                                                <i class="fas fa-flag mr-1"></i><?php echo $pilot['_count']; ?> factions
                                            </span>
                                        </div>
                                        <div class="text-center">
                                            <button class="hide-col-btn" onclick="hideStandingsColumn(<?php echo $colIndex; ?>)" title="Hide this column">
                                                <i class="fas fa-eye-slash mr-1"></i>Hide
                                            </button>
                                        </div>
                                    </th>
                                    <?php $colIndex++; ?>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php $colIndex = 0; ?>
                                <?php foreach ($standings_pilots as $pilot): ?>
                                    <td class="pilot-col standings-col" data-col-index="<?php echo $colIndex; ?>">
                                        <div class="nested-table-container">
                                            <?php echo renderFactionStandingsTable($pilot['_parsed']); ?>
                                        </div>
                                    </td>
                                    <?php $colIndex++; ?>
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
        <p><i class="fas fa-code mr-1"></i> EVE Pilot Dashboard &mdash; Licensed under GPL</p>
        <p class="text-secondary" style="font-size:0.75rem;">Auto-loaded <?php echo count($all_pilots); ?> pilots from database</p>
    </footer>
</div>

<!-- jQuery (required for Bootstrap 4) -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>

<!-- Bootstrap 4.6.x JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>

<script>
    /**
     * Hide a column in the Faction Standings table by index
     * Hides both the header (th) and data cell (td) with matching data-col-index
     */
    function hideStandingsColumn(colIndex) {
        var cells = document.querySelectorAll('#standingsTable .standings-col[data-col-index="' + colIndex + '"]');
        cells.forEach(function(cell) {
            cell.style.display = 'none';
        });
    }
</script>

</body>
</html>
