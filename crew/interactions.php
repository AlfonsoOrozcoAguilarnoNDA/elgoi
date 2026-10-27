<?php
/*
 * EVE Online - CREW Diplomacy (Reputation + Contacts & Faction Standings)
 * License: GPL
 * Author: Alfonso Orozco Aguilar
 * Merged version, three tabs: Contacts, Faction Standings and NPC Standings
 * (the former standalone "Reputation" tab), in one tabbed page.
 * Same look & tab structure, so more tabs can be added later.
 * Stack: PHP 8.x Procedural, MariaDB, Bootstrap 4.6.2, Font Awesome 5.15.4
 */

// ── HEADERS & INIT ─────────────────────────────────────────────────────────────
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require "../config.php";
include_once '../ui_functions.php';

check_authorization();
date_default_timezone_set('America/Mexico_City');

mysqli_set_charset($link, "utf8mb4");

$self       = basename(__FILE__);
$active_tab = $_GET['tab'] ?? 'contacts';

// ══════════════════════════════════════════════════════════════════════════════
// EVE FACTION NAMES MAP (display names instead of raw IDs)
// https://evemissioneer.com/factions# can check them
// ══════════════════════════════════════════════════════════════════════════════
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
    500024 => "Drifters",
    500025 => "Rogue Drones",
    500026 => "Triglavian Collective",
    500027 => "EDENCOM",
    500028 => "Association for Interdisciplinary Research",
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

// ══════════════════════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════════════════════
function get_pocket_color($val) {
    return match(strtoupper(trim($val ?? ''))) {
        'EXPER' => '#28a745', 'CLEAN' => '#0078d7', 'SANGO' => '#ffc107',
        'LUCKY' => '#6f42c1', 'NOKIA' => '#e81123', 'YENN'  => '#cccccc',
        'OTHER' => '#fd7e14', default => '#444444'
    };
}
function get_pocket_text($val) {
    return in_array(strtoupper(trim($val ?? '')), ['SANGO','YENN']) ? '#111' : '#fff';
}

// Pilot portrait URL (EVE image server)
function get_pilot_portrait_url($toon_number, $size = 128) {
    return 'https://images.evetech.net/characters/' . intval($toon_number) . '/portrait?size=' . intval($size);
}

// Faction display name from its ID
function get_faction_name($faction_id, $default = null) {
    global $FACTION_NAMES;
    if ($default !== null) return $default;
    return $FACTION_NAMES[intval($faction_id)] ?? ('Faction ' . $faction_id);
}

// EVEWho link builder (character / corporation / alliance)
function evewho_link($id, $type, $label = null) {
    $id = intval($id);
    $type = strtolower($type);
    $valid_types = ['character', 'corporation', 'alliance'];

    if (!in_array($type, $valid_types)) {
        return htmlspecialchars($label ?? $id);
    }

    $display = htmlspecialchars($label ?? $id);
    return '<a href="https://evewho.com/' . $type . '/' . $id . '" target="_blank" rel="noopener noreferrer" class="evewho-link">' . $display . ' <i class="fas fa-external-link-alt fa-xs"></i></a>';
}

// Fetch ALL pilots from the database (ordered by DOB)
function fetch_all_pilots($link) {
    $sql = "SELECT toon_number, toon_name, parent_toon_number, corporation_name,
                   tradefield, pocket6, gf, DOB, contacts, standings, corpID, allianceID
            FROM PILOTS
            ORDER BY DOB ASC";

    $result = mysqli_query($link, $sql);
    if (!$result) {
        error_log("Query failed: " . mysqli_error($link));
        return [];
    }

    $pilots = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $pilots[] = $row;
    }
    return $pilots;
}

// Parse contacts JSON (ignore standing == 0), sorted by standing DESC
function parse_contacts($contacts_json) {
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

    usort($filtered, function($a, $b) {
        return floatval($b['standing']) <=> floatval($a['standing']);
    });

    return $filtered;
}

// Parse faction standings JSON (only from_type == 'faction', zeros are shown), sorted by standing DESC
function parse_faction_standings($standings_json) {
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

    usort($filtered, function($a, $b) {
        return floatval($b['standing']) <=> floatval($a['standing']);
    });

    return $filtered;
}

// Keep only pilots that have parsed data for the given mode ('contacts' or 'standings')
function build_pilot_data($pilots, $mode = 'contacts') {
    $result = [];
    foreach ($pilots as $p) {
        $parsed = ($mode === 'contacts')
            ? parse_contacts($p['contacts'])
            : parse_faction_standings($p['standings']);
        if (!empty($parsed)) {
            $p['_parsed'] = $parsed;
            $p['_count']  = count($parsed);
            $result[] = $p;
        }
    }
    return $result;
}

// Pilot card header (portrait, name, GF flag, corp, alliance, trade field, pocket)
function render_pilot_header($pilot) {
    $portrait   = get_pilot_portrait_url($pilot['toon_number'], 128);
    $name       = htmlspecialchars($pilot['toon_name']);
    $corp       = htmlspecialchars($pilot['corporation_name'] ?? 'N/A');
    $pocket     = $pilot['pocket6'] ?? 'CLEAN';
    $trade      = $pilot['tradefield'] ?? 'n/a';
    $gf         = intval($pilot['gf'] ?? 0);
    $allianceID = intval($pilot['allianceID'] ?? 0);

    // GF flag: red if 1, gray if 0
    if ($gf === 1) {
        $gf_display = '<span class="badge badge-danger gf-flag" title="GF Flag Active"><i class="fas fa-flag"></i></span>';
    } else {
        $gf_display = '<span class="badge badge-secondary gf-flag" title="GF Flag Inactive"><i class="fas fa-flag"></i></span>';
    }

    $trade_display = (!empty($trade) && $trade !== 'n/a')
        ? '<span class="trade-tag">' . htmlspecialchars($trade) . '</span>'
        : '<small class="text-muted">-</small>';

    // No evewho links in the header
    $alliance_display = $allianceID > 0
        ? '<div class="small alliance-name">Alliance: ' . $allianceID . '</div>'
        : '';

    return '
        <div class="text-center">
            <img src="' . $portrait . '" alt="' . $name . '" class="rounded mb-2 pilot-header-img" loading="lazy">
            <h5 class="mb-1 text-white">' . $name . ' ' . $gf_display . '</h5>
            <div class="small corp-name">' . $corp . '</div>
            ' . $alliance_display . '
            <div class="small mb-1">' . $trade_display . '</div>
            <span class="pocket-badge-dip" style="background-color:' . get_pocket_color($pocket) . ';color:' . get_pocket_text($pocket) . ';">' . htmlspecialchars($pocket) . '</span>
        </div>';
}

// Nested table: contacts
function render_contacts_table($contacts) {
    if (empty($contacts)) {
        return '<div class="text-muted small font-italic p-2">No contacts with non-zero standing</div>';
    }

    $html = '<table class="table table-sm table-bordered dip-nested mb-0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Type</th>
                <th>Standing</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($contacts as $c) {
        $standing       = floatval($c['standing']);
        $standing_class = $standing > 0 ? 'rep-pos' : 'rep-neg';
        $standing_icon  = $standing > 0 ? 'fa-arrow-up' : 'fa-arrow-down';
        $contact_id     = intval($c['contact_id'] ?? 0);
        $contact_type   = $c['contact_type'] ?? 'character';

        $id_link = evewho_link($contact_id, $contact_type, $contact_id);

        $html .= '<tr>
            <td>' . $id_link . '</td>
            <td>' . htmlspecialchars($contact_type) . '</td>
            <td class="standing-num ' . $standing_class . '"><i class="fas ' . $standing_icon . ' fa-xs"></i> ' . number_format($standing, 2) . '</td>
        </tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}

// Nested table: faction standings
function render_faction_standings_table($standings) {
    if (empty($standings)) {
        return '<div class="text-muted small font-italic p-2">No faction standings</div>';
    }

    $html = '<table class="table table-sm table-bordered dip-nested mb-0">
        <thead>
            <tr>
                <th>Faction</th>
                <th>Standing</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($standings as $s) {
        $standing       = floatval($s['standing']);
        $standing_class = $standing > 0 ? 'rep-pos' : ($standing < 0 ? 'rep-neg' : 'rep-neu');
        $standing_icon  = $standing > 0 ? 'fa-arrow-up' : ($standing < 0 ? 'fa-arrow-down' : 'fa-minus');
        $faction_name   = get_faction_name(intval($s['from_id'] ?? 0));

        $html .= '<tr>
            <td>' . htmlspecialchars($faction_name) . '</td>
            <td class="standing-num ' . $standing_class . '"><i class="fas ' . $standing_icon . ' fa-xs"></i> ' . number_format($standing, 2) . '</td>
        </tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}

// ══════════════════════════════════════════════════════════════════════════════
// TAB — NPC STANDINGS: diplomatic control (NPC corps)
// ══════════════════════════════════════════════════════════════════════════════
function renderTabNpcStandings() {
    global $link, $self;
    $f_owner  = $_POST['owner_email']        ?? '';
    $f_pilot  = $_POST['pilot_name']         ?? '';
    $f_target = $_POST['target_description'] ?? '';
    $f_pocket = $_POST['pocket6_dip']        ?? '';

    $res_owners      = mysqli_query($link, "SELECT DISTINCT owner_email FROM DIPLOMATIC WHERE owner_email != '' ORDER BY owner_email ASC");
    $res_pilots_list = mysqli_query($link, "SELECT DISTINCT toon_name FROM PILOTS ORDER BY toon_name ASC");
    $res_corps       = mysqli_query($link, "SELECT DISTINCT target_description FROM DIPLOMATIC WHERE target_description != '' ORDER BY target_description ASC");
    $res_pockets     = mysqli_query($link, "SELECT DISTINCT pocket6 FROM PILOTS WHERE pocket6 != '' ORDER BY pocket6 ASC");

    $where = "WHERE 1=1";
    if (!empty($f_owner))  $where .= " AND D.owner_email = '"        . mysqli_real_escape_string($link, $f_owner)  . "'";
    if (!empty($f_pilot))  $where .= " AND D.pilot_name = '"         . mysqli_real_escape_string($link, $f_pilot)  . "'";
    if (!empty($f_target)) $where .= " AND D.target_description = '" . mysqli_real_escape_string($link, $f_target) . "'";
    if (!empty($f_pocket)) $where .= " AND P.pocket6 = '"            . mysqli_real_escape_string($link, $f_pocket) . "'";

    $sql_main   = "SELECT D.*, P.pocket6, P.tradefield
                   FROM DIPLOMATIC D
                   LEFT JOIN PILOTS P ON D.pilot_name = P.toon_name
                   $where
                   ORDER BY D.reputation DESC, D.id DESC";
    $res_main   = mysqli_query($link, $sql_main);
    $total_rows = mysqli_num_rows($res_main);
    ob_start();
    ?>
    <!-- Filter bar -->
    <form action="<?php echo $self; ?>?tab=npc" method="POST" class="form-row align-items-end mb-4 p-3 rounded" style="background:#16191c;border-bottom:2px solid #007bff;">
        <div class="form-group col-6 col-md-2 mb-2">
            <label class="filter-label"><i class="fas fa-user-tie mr-1"></i>Owner</label>
            <select name="owner_email" class="form-control form-control-sm eve-select">
                <option value="">-- All --</option>
                <?php while ($r = mysqli_fetch_assoc($res_owners)): ?>
                    <option value="<?php echo htmlspecialchars($r['owner_email']); ?>" <?php echo ($f_owner == $r['owner_email']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['owner_email']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group col-6 col-md-2 mb-2">
            <label class="filter-label"><i class="fas fa-user mr-1"></i>Pilot</label>
            <select name="pilot_name" class="form-control form-control-sm eve-select">
                <option value="">-- All --</option>
                <?php while ($r = mysqli_fetch_assoc($res_pilots_list)): ?>
                    <option value="<?php echo htmlspecialchars($r['toon_name']); ?>" <?php echo ($f_pilot == $r['toon_name']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['toon_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group col-6 col-md-3 mb-2">
            <label class="filter-label"><i class="fas fa-building mr-1"></i>Corp Target</label>
            <select name="target_description" class="form-control form-control-sm eve-select">
                <option value="">-- All --</option>
                <?php while ($r = mysqli_fetch_assoc($res_corps)): ?>
                    <option value="<?php echo htmlspecialchars($r['target_description']); ?>" <?php echo ($f_target == $r['target_description']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['target_description']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group col-6 col-md-2 mb-2">
            <label class="filter-label"><i class="fas fa-folder mr-1"></i>Pocket</label>
            <select name="pocket6_dip" class="form-control form-control-sm eve-select">
                <option value="">-- All --</option>
                <?php while ($r = mysqli_fetch_assoc($res_pockets)): ?>
                    <option value="<?php echo htmlspecialchars($r['pocket6']); ?>" <?php echo ($f_pocket == $r['pocket6']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['pocket6']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group col-12 col-md-3 mb-2 d-flex align-items-end" style="gap:8px;">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search mr-1"></i> Filter</button>
            <a href="<?php echo $self; ?>?tab=npc" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sync-alt mr-1"></i> Clear</a>
            <span class="total-badge ml-2"><i class="fas fa-list mr-1"></i><?php echo $total_rows; ?> records</span>
        </div>
    </form>

    <div class="table-responsive rounded shadow">
        <table id="tbl-reputation" class="table table-sm table-eve mb-0">
            <thead>
                <tr>
                    <th width="40" class="text-center">#</th>
                    <th>Pilot</th>
                    <th>Trade Field</th>
                    <th>Corp Target</th>
                    <th class="text-right">Reputation</th>
                    <th class="text-center">Pocket</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $cnt = 1;
            if ($total_rows > 0):
                while ($row = mysqli_fetch_assoc($res_main)):
                    $rep        = (float)$row['reputation'];
                    $rep_class  = ($rep > 0) ? 'rep-pos' : (($rep < 0) ? 'rep-neg' : 'rep-neu');
                    $p6_val     = $row['pocket6'] ?? 'N/A';
                    $tradefield = $row['tradefield'] ?? '';
            ?>
            <tr>
                <td class="text-center row-num"><?php echo $cnt++; ?></td>
                <td><strong class="text-white"><?php echo htmlspecialchars($row['pilot_name']); ?></strong></td>
                <td>
                    <?php if (!empty($tradefield) && $tradefield !== 'n/a'): ?>
                        <span class="trade-tag"><?php echo htmlspecialchars($tradefield); ?></span>
                    <?php else: ?><small class="text-muted">-</small><?php endif; ?>
                </td>
                <td style="color:#ffffff;"><?php echo htmlspecialchars($row['target_description']); ?></td>
                <td class="text-right reputation-num <?php echo $rep_class; ?>"><?php echo number_format($rep, 2); ?></td>
                <td class="text-center">
                    <span class="pocket-badge-dip" style="background-color:<?php echo get_pocket_color($p6_val); ?>;color:<?php echo get_pocket_text($p6_val); ?>;">
                        <?php echo htmlspecialchars($p6_val); ?>
                    </span>
                </td>
            </tr>
            <?php endwhile;
            else: ?>
            <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-satellite mr-2"></i>No data available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

// ══════════════════════════════════════════════════════════════════════════════
// TAB — CONTACTS: all pilots with non-zero contact standings
// ══════════════════════════════════════════════════════════════════════════════
function renderTabContacts() {
    global $link;
    $all_pilots      = fetch_all_pilots($link);
    $contacts_pilots = build_pilot_data($all_pilots, 'contacts');
    ob_start();
    ?>
    <h2 class="section-note">
        <i class="fas fa-users mr-2"></i>Contacts Breakdown — All Pilots with Non-Zero Standings (sorted by DOB)
        <span class="total-badge ml-2"><i class="fas fa-list mr-1"></i><?php echo count($contacts_pilots); ?> pilots</span>
    </h2>

    <?php if (empty($contacts_pilots)): ?>
        <div class="text-center py-5 text-muted"><i class="fas fa-satellite mr-2"></i>No pilots with contacts found in the database.</div>
    <?php else: ?>
        <div class="table-responsive rounded shadow">
            <table class="table table-bordered pilot-matrix" id="contactsTable">
                <thead>
                    <tr>
                        <?php foreach ($contacts_pilots as $pilot): ?>
                            <th class="pilot-col">
                                <?php echo render_pilot_header($pilot); ?>
                                <div class="text-center mt-2">
                                    <span class="total-badge">
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
                                    <?php echo render_contacts_table($pilot['_parsed']); ?>
                                </div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="mt-4 text-center text-muted small">
        <i class="fas fa-database mr-1"></i>Auto-loaded <?php echo count($all_pilots); ?> pilots from database
    </div>
    <?php
    return ob_get_clean();
}

// ══════════════════════════════════════════════════════════════════════════════
// TAB — FACTION STANDINGS: faction standings comparison across all pilots
// ══════════════════════════════════════════════════════════════════════════════
function renderTabFactions() {
    global $link;
    $all_pilots       = fetch_all_pilots($link);
    $standings_pilots = build_pilot_data($all_pilots, 'standings');
    ob_start();
    ?>
    <h2 class="section-note">
        <i class="fas fa-flag mr-2"></i>Faction Standings Distribution Comparison — All Pilots (sorted by DOB)
        <span class="total-badge ml-2"><i class="fas fa-list mr-1"></i><?php echo count($standings_pilots); ?> pilots</span>
    </h2>

    <?php if (empty($standings_pilots)): ?>
        <div class="text-center py-5 text-muted"><i class="fas fa-satellite mr-2"></i>No pilots with faction standings found in the database.</div>
    <?php else: ?>
        <div class="table-responsive rounded shadow">
            <table class="table table-bordered pilot-matrix" id="standingsTable">
                <thead>
                    <tr>
                        <?php $colIndex = 0; ?>
                        <?php foreach ($standings_pilots as $pilot): ?>
                            <th class="pilot-col standings-col" data-col-index="<?php echo $colIndex; ?>">
                                <?php echo render_pilot_header($pilot); ?>
                                <div class="text-center mt-2">
                                    <span class="total-badge">
                                        <i class="fas fa-flag mr-1"></i><?php echo $pilot['_count']; ?> factions
                                    </span>
                                </div>
                                <div class="text-center">
                                    <button type="button" class="hide-col-btn" onclick="hideStandingsColumn(<?php echo $colIndex; ?>)" title="Hide this column">
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
                                    <?php echo render_faction_standings_table($pilot['_parsed']); ?>
                                </div>
                            </td>
                            <?php $colIndex++; ?>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="mt-4 text-center text-muted small">
        <i class="fas fa-database mr-1"></i>Auto-loaded <?php echo count($all_pilots); ?> pilots from database
    </div>
    <?php
    return ob_get_clean();
}

// ══════════════════════════════════════════════════════════════════════════════
// OUTPUT
// ══════════════════════════════════════════════════════════════════════════════
echo ui_header("EVE Online - CREW Diplomacy");
echo crew_navbar();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>EVE Online - CREW Diplomacy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <style>
        /* ── BASE ── */
        body {
            background-color: #0b0c0e;
            color: #ced4da;
            font-family: 'Segoe UI', sans-serif;
            padding-top: 40px;
            padding-bottom: 70px;
        }

        /* ── TABS ── */
        .eve-tabs {
            background-color: #0d0f11;
            border-bottom: 2px solid #007bff;
            padding: 0 20px;
            position: sticky;
            top: 120;
            z-index: 100;
        }
        .eve-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            border-radius: 0;
            padding: 14px 20px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: color 0.2s, border-color 0.2s;
        }
        .eve-tabs .nav-link:hover { color: #adb5bd; text-decoration: none; }
        .eve-tabs .nav-link.active {
            color: #ffffff;
            border-bottom: 3px solid #007bff;
            background: transparent;
        }
        .eve-tabs .nav-link i { margin-right: 7px; }

        /* ── SHARED TABLE ── */
        .table-eve { background-color: #16191c; font-size: 0.82rem; border-collapse: separate; border-spacing: 0; }
        .table-eve thead th { background-color: #212529; border-bottom: 2px solid #007bff; position: sticky; top: 0px; z-index: 10; color: #adb5bd !important; }
        .table-eve td { vertical-align: middle; border-top: 1px solid #2d3238; }

        /* ── DATATABLES DARK OVERRIDES ── */
        .dataTables_wrapper { color: #ced4da; }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            background-color: #1e2126; border: 1px solid #495057; color: #e0e0e0;
            border-radius: 3px; padding: 2px 6px;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { color: #adb5bd; margin-bottom: 8px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #adb5bd !important; border: 1px solid #343a40 !important;
            background: #16191c !important; border-radius: 2px !important; margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #2a3040 !important; color: #fff !important; border-color: #007bff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #007bff !important; color: #fff !important; border-color: #007bff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover { opacity: 0.4; }

        /* ── TRADE FIELD (purple, same as Graduation tab) ── */
        .trade-tag { color: #bb86fc; font-weight: 500; letter-spacing: 0.5px; }

        /* ── REPUTATION ── */
        .reputation-num { font-family: 'Consolas', monospace; font-weight: bold; font-size: 0.95rem; }
        .rep-pos { color: #28a745; } .rep-neg { color: #dc3545; } .rep-neu { color: #adb5bd; }
        .pocket-badge-dip { display: inline-block; padding: 2px 10px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; border-radius: 2px; min-width: 70px; text-align: center; }
        .row-num { color: #6c757d; font-size: 0.78rem; }
        .total-badge { background-color: #0d0f11; border: 1px solid #007bff; color: #007bff; font-size: 0.8rem; padding: 3px 10px; border-radius: 3px; }

        /* ── FILTER ── */
        .filter-label { color: #adb5bd; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; display: block; }
        .eve-select, .eve-select:focus { background-color: #1e2126; border-color: #495057; color: #e0e0e0; box-shadow: none; }

        /* ── TAB CONTENT ── */
        .tab-content-eve { padding: 25px 20px; }

        /* ── CONTACTS / FACTION STANDINGS: SECTION HEADER ── */
        .section-note {
            color: #adb5bd;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding: 0.7rem 1rem;
            background: #16191c;
            border-left: 4px solid #007bff;
            border-radius: 3px;
        }

        /* ── CONTACTS / FACTION STANDINGS: PILOT MATRIX ── */
        .pilot-matrix { background-color: #16191c; margin-bottom: 0; color: #ced4da; }
        .pilot-matrix > thead > tr > th {
            background-color: #212529;
            border-color: #2d3238;
            border-bottom: 2px solid #007bff;
            padding: 14px 10px;
        }
        .pilot-matrix > tbody > tr > td { border-color: #2d3238; padding: 0.5rem; }
        .pilot-col { min-width: 240px; max-width: 320px; vertical-align: top !important; }

        .pilot-header-img {
            width: 128px; height: 128px; object-fit: cover;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            transition: transform 0.2s ease;
        }
        .pilot-header-img:hover { transform: scale(1.05); }
        .gf-flag { font-size: 0.65rem; padding: 0.25rem 0.4rem; vertical-align: middle; }
        .corp-name { color: #6c757d; margin-bottom: 0.2rem; }
        .alliance-name { color: #17a2b8; margin-bottom: 0.2rem; }

        /* Nested tables (contacts / faction standings) */
        .nested-table-container { max-height: 500px; overflow-y: auto; border-radius: 3px; }
        .nested-table-container::-webkit-scrollbar { width: 6px; }
        .nested-table-container::-webkit-scrollbar-track { background: #0b0c0e; }
        .nested-table-container::-webkit-scrollbar-thumb { background: #343a40; border-radius: 3px; }
        .dip-nested { background-color: #16191c; color: #ced4da; font-size: 0.78rem; }
        .dip-nested thead th {
            background-color: #212529;
            color: #adb5bd;
            border-color: #2d3238;
            border-bottom: 2px solid #007bff;
            white-space: nowrap;
            position: sticky; top: 0; z-index: 1;
        }
        .dip-nested td { border-color: #2d3238; padding: 0.4rem 0.5rem; vertical-align: middle; }
        .dip-nested tbody tr:nth-of-type(odd) { background-color: rgba(45, 50, 56, 0.3); }
        .standing-num { font-family: 'Consolas', monospace; font-weight: bold; }

        /* EVEWho links */
        .evewho-link { color: #5aa9ff; text-decoration: none; transition: color 0.2s ease; }
        .evewho-link:hover { color: #007bff; text-decoration: underline; }
        .evewho-link i { font-size: 0.6rem; opacity: 0.7; }

        /* Hide column button */
        .hide-col-btn {
            margin-top: 0.5rem;
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border: 1px solid #343a40;
            background: transparent;
            color: #6c757d;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .hide-col-btn:hover { background-color: #dc3545; color: #fff; border-color: #dc3545; }

        @media (max-width: 768px) {
            .pilot-col { min-width: 200px; max-width: 260px; }
        }
    </style>
</head>
<body>

<!-- ── TABS NAV (add more tabs here later) ── -->
<div class="eve-tabs">
    <ul class="nav">
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab == 'contacts') ? 'active' : ''; ?>" href="?tab=contacts">
                <i class="fas fa-address-book"></i> Contacts
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab == 'factions') ? 'active' : ''; ?>" href="?tab=factions">
                <i class="fas fa-flag"></i> Faction Standings
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab == 'npc') ? 'active' : ''; ?>" href="?tab=npc">
                <i class="fas fa-passport"></i> NPC Standings
            </a>
        </li>
    </ul>
</div>

<!-- ── TAB CONTENT ── -->
<div class="tab-content-eve">
    <?php
    switch ($active_tab) {
        case 'factions':
            echo renderTabFactions();
            break;
        case 'npc':
            echo renderTabNpcStandings();
            break;
        case 'contacts':
        default:
            echo renderTabContacts();
    }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#tbl-reputation').length) {
        $('#tbl-reputation').DataTable({
            pageLength: 50,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
            order: [],
            language: { search: "Search:", lengthMenu: "Show _MENU_ records" }
        });
    }
});

/**
 * Hide a column in the Faction Standings table by index.
 * Hides both the header (th) and the data cell (td) with the matching data-col-index.
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
