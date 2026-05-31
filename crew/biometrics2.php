<?php
/*
 * EVE Online - Unified Audit Tool
 * License: MIT
 * Author: Alfonso Orozco Aguilar
 * Tabs: Graduation | Reputation | Biometrics | Evermarks
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
mysqli_query($link, "UPDATE PILOTS SET finishqueue = NULL WHERE finishqueue = '0000-00-00 00:00:00'");

$self       = basename(__FILE__);
$active_tab = $_GET['tab'] ?? 'graduation';

// ══════════════════════════════════════════════════════════════════════════════
// SHARED: TRADEFIELD UPDATE (skill catalog maintenance — runs always)
// ══════════════════════════════════════════════════════════════════════════════
function getSkillCategoryNameFromESI($skillID) {
    $ctx      = stream_context_create(['http' => ['timeout' => 2]]);
    $typeJson = @file_get_contents("https://esi.evetech.net/latest/universe/types/{$skillID}/?datasource=tranquility", false, $ctx);
    if (!$typeJson) return null;
    $typeData = json_decode($typeJson, true);
    if (!isset($typeData['group_id'])) return null;
    $groupJson = @file_get_contents("https://esi.evetech.net/latest/universe/groups/{$typeData['group_id']}/?datasource=tranquility", false, $ctx);
    if (!$groupJson) return null;
    $g = json_decode($groupJson, true);
    return $g['name'] ?? null;
}

// Update skill catalog for any skills missing group_name
$resPending = mysqli_query($link, "SELECT DISTINCT typeID, Description FROM EVE_CHARSKILLS WHERE group_name IS NULL OR group_name = ''");
if ($resPending && mysqli_num_rows($resPending) > 0) {
    while ($rowSkill = mysqli_fetch_assoc($resPending)) {
        $tid  = $rowSkill['typeID'];
        $desc = mysqli_real_escape_string($link, $rowSkill['Description']);
        $res  = mysqli_query($link, "SELECT group_name FROM cat_typeofskill WHERE typeID = $tid LIMIT 1");
        $gn   = '';
        if (mysqli_num_rows($res) > 0) {
            $gn = mysqli_fetch_assoc($res)['group_name'];
        } else {
            $esi = getSkillCategoryNameFromESI($tid);
            if ($esi) {
                $gn  = $esi;
                $sGN = mysqli_real_escape_string($link, $esi);
                mysqli_query($link, "INSERT INTO cat_typeofskill (typeID, Description, group_name) VALUES ($tid, '$desc', '$sGN')");
            }
        }
        if (!empty($gn)) {
            $sGN = mysqli_real_escape_string($link, $gn);
            mysqli_query($link, "UPDATE EVE_CHARSKILLS SET group_name = '$sGN' WHERE typeID = $tid");
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// TAB 1 — GRADUATION: tradefield + queue audit
// ══════════════════════════════════════════════════════════════════════════════
function obtenerOficio($p) {
    global $link;
    $name = mysqli_real_escape_string($link, $p['toon_name']);
    $res  = mysqli_query($link, "SELECT group_name, SUM(skillpoints) as total_group_sp
                                  FROM EVE_CHARSKILLS
                                  WHERE toon_name = '$name'
                                  GROUP BY group_name
                                  ORDER BY total_group_sp DESC
                                  LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        return mysqli_fetch_assoc($res)['group_name'];
    }
    $sp = (int)($p['skillpoints'] / 1000000);
    if ($sp < 10) return "n/a";
    return "Independent Specialist";
}

function getPanelStatus($expiration) {
    if (empty($expiration) || $expiration == '0000-00-00')
        return '<span style="color:#ff4d4d;font-weight:bold;">NO PANEL</span>';
    $hoy   = new DateTime(date('Y-m-d'));
    $vence = new DateTime($expiration);
    if ($vence < $hoy) return '<span class="text-secondary">n/a</span>';
    return '<span class="text-white font-weight-bold">' . $hoy->diff($vence)->format('%a') . ' D</span>';
}

function getBirreteStyle($finishQueue, $planets) {
    $ahora = date('Y-m-d H:i:s');
    if (!empty($finishQueue) && $finishQueue > $ahora) return 'text-success';
    if (empty($planets) || $planets == '[]')          return 'text-secondary';
    return 'text-warning';
}

function renderTabGraduation() {
    global $link;
    $sqlMaster = "SELECT P.*,
                  ((P.skillpoints + IFNULL(P.unalloc,0)) / 1000000) as TotalSP_M,
                  PAN.manualExpiration
                  FROM PILOTS P
                  LEFT JOIN PANELS PAN ON (P.toon_name = PAN.name_1 OR P.toon_name = PAN.name_2 OR P.toon_name = PAN.name_3)
                  ORDER BY P.finishqueue DESC, P.toon_name ASC";
    $res    = mysqli_query($link, $sqlMaster);
    $ahora  = new DateTime();
    $cnt    = 1;
    ob_start();
    ?>
    <div class="table-responsive">
        <table id="tbl-graduation" class="table table-eve table-hover table-dark">
            <thead>
                <tr>
                    <th class="text-center">#</th>
                    <th>Pilot</th>
                    <th class="text-right">SP (Millions)</th>
                    <th class="text-center">Acc</th>
                    <th class="text-center">Trade Field</th>
                    <th>Queue End</th>
                    <th class="text-center">Queue Days</th>
                    <th class="text-center col-panel">Panel Days</th>
                    <th class="text-center">Status Icons</th>
                    <th>Pocket</th>
                    <th class="text-right">Evermarks</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($p = mysqli_fetch_assoc($res)):
                $oficio    = obtenerOficio($p);
                $safeTrade = mysqli_real_escape_string($link, $oficio);
                mysqli_query($link, "UPDATE PILOTS SET tradefield = '$safeTrade' WHERE toon_name = '".mysqli_real_escape_string($link, $p['toon_name'])."'");
                $diasCola  = "---";
                if (!empty($p['finishqueue'])) {
                    $fT       = new DateTime($p['finishqueue']);
                    $diasCola = ($fT > $ahora) ? $ahora->diff($fT)->format('%a d') : '<span class="text-danger">0 d</span>';
                }
            ?>
            <tr>
                <td class="text-center text-muted"><?php echo $cnt++; ?></td>
                <td>
                    <div class="d-flex align-items-center">
                        <img src="https://images.evetech.net/characters/<?php echo $p['toon_number']; ?>/portrait?size=64" class="portrait-mini mr-2">
                        <strong><?php echo htmlspecialchars($p['toon_name']); ?></strong>
                    </div>
                </td>
                <td class="text-right text-sp"><?php echo number_format($p['TotalSP_M'], 2); ?> M</td>
                <td class="text-center">
                    <?php echo (strtolower($p['acctype']) == 'omega')
                        ? '<i class="fas fa-crown text-warning"></i>'
                        : '<i class="fas fa-rocket text-secondary"></i>'; ?>
                </td>
                <td class="text-center trade-tag"><?php echo htmlspecialchars($oficio); ?></td>
                <td><?php echo !empty($p['finishqueue'])
                        ? date('d/m H:i', strtotime($p['finishqueue']))
                        : '<span class="text-muted">N/A</span>'; ?></td>
                <td class="text-center font-weight-bold"><?php echo $diasCola; ?></td>
                <td class="text-center col-panel"><?php echo getPanelStatus($p['manualExpiration']); ?></td>
                <td class="text-center" style="font-size:1.1rem;">
                    <i class="fas fa-globe-asia mx-1 <?php echo ($p['planets'] != '[]') ? 'text-success' : 'text-dark'; ?>"></i>
                    <i class="fas fa-tools     mx-1 <?php echo ($p['jobs']    != '[]') ? 'text-warning' : 'text-dark'; ?>"></i>
                    <i class="fas fa-graduation-cap mx-1 <?php echo getBirreteStyle($p['finishqueue'], $p['planets']); ?>"></i>
                </td>
                <td>
                    <?php
                    $p6v = $p['pocket6'] ?? 'N/A';
                    echo '<span class="pocket6-badge" style="background-color:' . get_pocket_color($p6v) . ';color:' . get_pocket_text($p6v) . ';">' . htmlspecialchars($p6v) . '</span>';
                    ?>
                </td>
                <td class="text-right"><?php echo number_format($p['evermarks']); ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

// ══════════════════════════════════════════════════════════════════════════════
// TAB 2 — REPUTATION: diplomatic control
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

function renderTabReputation() {
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

    $sql_main   = "SELECT D.*, P.pocket6, P.tradefield, P.toon_number
                   FROM DIPLOMATIC D
                   LEFT JOIN PILOTS P ON D.pilot_name = P.toon_name
                   $where
                   ORDER BY D.reputation DESC, D.id DESC";
    $res_main   = mysqli_query($link, $sql_main);
    $total_rows = mysqli_num_rows($res_main);
    ob_start();
    ?>
    <!-- Filter bar -->
    <form action="<?php echo $self; ?>?tab=reputation" method="POST" class="form-row align-items-end mb-4 p-3 rounded" style="background:#16191c;border-bottom:2px solid #007bff;">
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
            <a href="<?php echo $self; ?>?tab=reputation" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sync-alt mr-1"></i> Clear</a>
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
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $cnt = 1;
            if ($total_rows > 0):
                while ($row = mysqli_fetch_assoc($res_main)):
                    $rep       = (float)$row['reputation'];
                    $rep_class = ($rep > 0) ? 'rep-pos' : (($rep < 0) ? 'rep-neg' : 'rep-neu');
                    $p6_val    = $row['pocket6'] ?? 'N/A';
                    $tradefield = $row['tradefield'] ?? '';
            ?>
            <tr>
                <td class="text-center row-num"><?php echo $cnt++; ?></td>
                <td><strong class="text-white"><?php echo htmlspecialchars($row['pilot_name']); ?></strong></td>
                <td>
                    <?php if (!empty($tradefield) && $tradefield !== 'n/a'): ?>
                        <span class="trade-pill"><?php echo htmlspecialchars($tradefield); ?></span>
                    <?php else: ?><small class="text-muted">-</small><?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($row['target_description']); ?></td>
                <td class="text-right reputation-num <?php echo $rep_class; ?>"><?php echo number_format($rep, 2); ?></td>
                <td class="text-center">
                    <span class="pocket-badge-dip" style="background-color:<?php echo get_pocket_color($p6_val); ?>;color:<?php echo get_pocket_text($p6_val); ?>;">
                        <?php echo htmlspecialchars($p6_val); ?>
                    </span>
                </td>
                <td class="text-center"><?php echo geticons($row['toon_number']); ?></td>
            </tr>
            <?php endwhile;
            else: ?>
            <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-satellite mr-2"></i>No data available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

// ══════════════════════════════════════════════════════════════════════════════
// TAB 3 — BIOMETRICS: pilot cards with skill charts
// ══════════════════════════════════════════════════════════════════════════════
function getPilotSkillGraph($toon_name) {
    global $link;
    $safe  = mysqli_real_escape_string($link, $toon_name);
    $res   = mysqli_query($link, "SELECT group_name, SUM(skillpoints) as total_group_sp
                                   FROM EVE_CHARSKILLS WHERE toon_name = '$safe'
                                   GROUP BY group_name ORDER BY total_group_sp DESC");
    $data  = [];
    $total = 0;
    while ($row = mysqli_fetch_assoc($res)) { $data[] = $row; $total += $row['total_group_sp']; }
    if ($total == 0) return "<small class='text-muted'>No skill data</small>";

    $html     = '<div class="skill-stats">';
    $labels   = [];
    $values   = [];
    foreach (array_slice($data, 0, 4) as $item) {
        $pct      = ($item['total_group_sp'] / $total) * 100;
        $labels[] = $item['group_name'];
        $values[] = $item['total_group_sp'];
        $html    .= '<div class="d-flex justify-content-between border-bottom border-secondary mb-1">'
                  . '<span>' . htmlspecialchars($item['group_name']) . '</span>'
                  . '<span class="text-info">' . number_format($pct, 1) . '%</span>'
                  . '</div>';
    }
    $html .= '</div>';
    $cid   = "chart_" . md5($toon_name);
    $GLOBALS['chart_data'][$cid] = ['labels' => $labels, 'values' => $values];
    return '<canvas id="' . $cid . '" width="100" height="100"></canvas>' . $html;
}

function renderTabBiometrics() {
    global $link;
    $GLOBALS['chart_data'] = [];

    $filterTrade = $_GET['filter_trade'] ?? 'ALL';
    $filterCorp  = $_GET['filter_corp']  ?? 'ALL';

    $resTrades = mysqli_query($link, "SELECT DISTINCT tradefield FROM PILOTS WHERE tradefield IS NOT NULL AND tradefield <> '' ORDER BY tradefield ASC");
    $resCorp   = mysqli_query($link, "SELECT DISTINCT corporation_name FROM PILOTS WHERE corporation_name IS NOT NULL AND corporation_name <> '' ORDER BY corporation_name ASC");

    $where = ["toon_name NOT LIKE 'VPS%'"];
    if ($filterTrade !== 'ALL') { $where[] = "tradefield = '" . mysqli_real_escape_string($link, $filterTrade) . "'"; }
    if ($filterCorp  !== 'ALL') { $where[] = "corporation_name = '" . mysqli_real_escape_string($link, $filterCorp) . "'"; }
    $whereClause = "WHERE " . implode(" AND ", $where);

    $res          = mysqli_query($link, "SELECT toon_number as character_id, toon_name, pocket6, acctype, skillpoints, unalloc, tradefield, corporation_name, gf,
                                         ((skillpoints + IFNULL(unalloc,0))/1000000) as TotalSP_M
                                         FROM PILOTS $whereClause ORDER BY (skillpoints + IFNULL(unalloc,0)) DESC");
    $totalPilotos = $res ? mysqli_num_rows($res) : 0;
    ob_start();
    ?>
    <!-- Filter bar -->
    <form method="GET" class="form-inline flex-wrap mb-4 p-3 rounded" style="background:#16191c;border-bottom:2px solid #007bff;gap:10px;">
        <input type="hidden" name="tab" value="biometrics">
        <label class="text-light mr-2"><i class="fas fa-tag mr-1"></i> Trade:</label>
        <select name="filter_trade" class="form-control form-control-sm mr-3 eve-select">
            <option value="ALL">-- All --</option>
            <?php while ($t = mysqli_fetch_assoc($resTrades)):
                $sel = ($filterTrade === $t['tradefield']) ? 'selected' : ''; ?>
                <option value="<?php echo htmlspecialchars($t['tradefield']); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($t['tradefield']); ?></option>
            <?php endwhile; ?>
        </select>
        <label class="text-light mr-2"><i class="fas fa-building mr-1"></i> Corp:</label>
        <select name="filter_corp" class="form-control form-control-sm mr-3 eve-select">
            <option value="ALL">-- All --</option>
            <?php while ($c = mysqli_fetch_assoc($resCorp)):
                $sel = ($filterCorp === $c['corporation_name']) ? 'selected' : ''; ?>
                <option value="<?php echo htmlspecialchars($c['corporation_name']); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c['corporation_name']); ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary mr-3"><i class="fas fa-filter mr-1"></i> Filter</button>
        <?php if ($filterTrade !== 'ALL' || $filterCorp !== 'ALL'): ?>
            <a href="?tab=biometrics" class="btn btn-sm btn-outline-secondary mr-3"><i class="fas fa-times mr-1"></i> Clear</a>
        <?php endif; ?>
        <span class="text-muted small ml-auto"><i class="fas fa-users mr-1"></i><?php echo $totalPilotos; ?> pilots</span>
        <button type="button" class="btn btn-sm btn-secondary ml-2" disabled title="Coming soon">
            <i class="fas fa-sync-alt mr-1"></i> UPDATE
        </button>
    </form>

    <div class="row">
    <?php while ($pilot = mysqli_fetch_assoc($res)):
        $charID      = !empty($pilot['character_id']) ? $pilot['character_id'] : '1';
        $formattedSP = number_format($pilot['TotalSP_M'], 2, '.', ',');
        $accIcon  = 'fa-question'; $accColor = 'text-muted';
        if (strtolower($pilot['acctype']) == 'omega') { $accIcon = 'fa-crown';  $accColor = 'text-warning'; }
        elseif (strtolower($pilot['acctype']) == 'alpha') { $accIcon = 'fa-rocket'; $accColor = 'text-info'; }
    ?>
    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 col-12 d-flex align-items-stretch">
        <div class="card card-eve w-100 position-relative">
            <div class="gf-badge" title="GF: <?php echo (int)($pilot['gf'] ?? 0); ?>">
                <i class="fas fa-flag" style="color:<?php echo ($pilot['gf'] > 0) ? '#dc3545' : '#495057'; ?>;"></i>
            </div>
            <div class="acc-type-badge" title="<?php echo htmlspecialchars($pilot['acctype']); ?>">
                <i class="fas <?php echo $accIcon; ?> <?php echo $accColor; ?>"></i>
            </div>
            <img src="https://images.evetech.net/characters/<?php echo $charID; ?>/portrait?size=256" class="card-img-top pilot-portrait">
            <div class="card-body d-flex flex-column p-3">
                <h5 class="card-title text-truncate text-center mb-1" title="<?php echo htmlspecialchars($pilot['toon_name']); ?>">
                    <?php echo htmlspecialchars($pilot['toon_name']); ?>
                </h5>
                <div class="sp-total mb-2"><?php echo $formattedSP; ?> <small class="text-muted">SP</small></div>
                <?php if (!empty($pilot['tradefield'])): ?>
                <div class="trade-tag text-truncate mb-1"><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($pilot['tradefield']); ?></div>
                <?php endif; ?>
                <?php if (!empty($pilot['corporation_name'])): ?>
                <div class="corp-tag text-truncate mb-2"><i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($pilot['corporation_name']); ?></div>
                <?php endif; ?>
                <div class="pocket-info mt-auto mb-2"><i class="fas fa-folder mr-1"></i> Pocket: <strong><?php echo htmlspecialchars($pilot['pocket6'] ?? 'N/A'); ?></strong></div>
                <div class="grafica-container p-2" style="min-height:150px;">
                    <?php echo getPilotSkillGraph($pilot['toon_name']); ?>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ══════════════════════════════════════════════════════════════════════════════
// TAB 4 — EVERMARKS: fleet audit with evermarks update
// ══════════════════════════════════════════════════════════════════════════════
function getAccTypeStyle($type) {
    $t = strtolower($type);
    if ($t == 'omega') return ['icon' => 'fa-crown',           'color' => '#f1c40f', 'label' => 'OMEGA'];
    if ($t == 'alpha') return ['icon' => 'fa-rocket',          'color' => '#95a5a6', 'label' => 'ALPHA'];
    return                    ['icon' => 'fa-question-circle', 'color' => '#6c757d', 'label' => 'N/A'];
}

function renderTabEvermarks() {
    global $link, $self;
    $mensaje_exito = "";

    if (isset($_POST['update_evermarks'])) {
        $target_toon = mysqli_real_escape_string($link, $_POST['toon_name']);
        $new_val     = (int)$_POST['evermarks_val'];
        if (mysqli_query($link, "UPDATE PILOTS SET evermarks = $new_val, lastdateevermark = NOW() WHERE toon_name = '$target_toon'")) {
            $mensaje_exito = "Evermarks updated for pilot: <strong>" . htmlspecialchars($target_toon) . "</strong>";
        }
    }

    $filterCorp = $_POST['filter_corp_em'] ?? 'ALL';
    $filterEver = $_POST['filter_ever']    ?? '500';
    $where      = ["1=1"];
    if ($filterCorp !== 'ALL') $where[] = "P.corporation_name = '" . mysqli_real_escape_string($link, $filterCorp) . "'";
    if ($filterEver === '500') $where[] = "P.evermarks > 500";
    $whereClause = "WHERE " . implode(" AND ", $where);

    $resList   = mysqli_query($link, "SELECT DISTINCT corporation_name FROM PILOTS WHERE corporation_name IS NOT NULL ORDER BY corporation_name ASC");
    $resPilots = mysqli_query($link, "SELECT P.*, ((P.skillpoints + IFNULL(P.unalloc,0))/1000000) as TotalSP_M, (IFNULL(P.wallet,0)/1000000) as Wallet_M
                                       FROM PILOTS P $whereClause ORDER BY P.evermarks DESC, TotalSP_M DESC");
    $hoy = date('Y-m-d');
    ob_start();
    ?>
    <!-- Filter bar -->
    <form method="POST" action="<?php echo $self; ?>?tab=evermarks" class="form-inline flex-wrap mb-4 p-3 rounded" style="background:#16191c;border-bottom:2px solid #007bff;gap:6px;row-gap:8px;">
        <label class="mr-2 text-light">Corporation:</label>
        <select name="filter_corp_em" class="form-control form-control-sm mr-3 eve-select">
            <option value="ALL">-- ALL --</option>
            <?php while ($c = mysqli_fetch_assoc($resList)): ?>
                <option value="<?php echo htmlspecialchars($c['corporation_name']); ?>" <?php echo ($filterCorp == $c['corporation_name']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['corporation_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <label class="mr-2 text-light">Filter:</label>
        <select name="filter_ever" class="form-control form-control-sm mr-3 eve-select">
            <option value="ALL" <?php echo ($filterEver == 'ALL') ? 'selected' : ''; ?>>All</option>
            <option value="500" <?php echo ($filterEver == '500') ? 'selected' : ''; ?>>Greater than 500</option>
        </select>
        <button type="submit" class="btn btn-sm btn-primary px-4"><i class="fas fa-sync-alt mr-1"></i> Refresh View</button>
        <div class="mini-toolbar">
            <a href="#" class="btn-tool btn-tool-dark"  title="Action A"><i class="fas fa-star"></i> A</a>
            <a href="#" class="btn-tool btn-tool-dark"  title="Action B"><i class="fas fa-bolt"></i> B</a>
            <a href="#" class="btn-tool btn-tool-dark"  title="Action C"><i class="fas fa-flag"></i> C</a>
            <a href="#" class="btn-tool btn-tool-dark"  title="Action D"><i class="fas fa-cog"></i>  D</a>
            <a href="#" class="btn-tool btn-tool-white" title="Action E"><i class="fas fa-bell"></i> E</a>
        </div>
    </form>

    <?php if ($mensaje_exito): ?>
    <div class="alert alert-success alert-dismissible fade show bg-dark text-success border-success" role="alert">
        <i class="fas fa-check-circle mr-2"></i> <?php echo $mensaje_exito; ?>
        <button type="button" class="close text-white" data-dismiss="alert">&times;</button>
    </div>
    <?php endif; ?>

    <div class="row">
    <?php while ($p = mysqli_fetch_assoc($resPilots)):
        $fechaAuditoria = (!empty($p['lastdateevermark'])) ? date('Y-m-d', strtotime($p['lastdateevermark'])) : '';
        $esHoy  = ($fechaAuditoria === $hoy);
        $acc    = getAccTypeStyle($p['acctype']);
        $pocket = !empty($p['pocket6']) ? htmlspecialchars($p['pocket6']) : 'N/A';
        $tradefield = (!empty($p['tradefield'])) ? htmlspecialchars($p['tradefield']) : '-';
    ?>
    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
        <div class="card card-eve">
            <div class="card-body p-3">
                <!-- Account type badge -->
                <div class="acctype-corner">
                    <i class="fas <?php echo $acc['icon']; ?>" style="color:<?php echo $acc['color']; ?>;" title="<?php echo $acc['label']; ?>"></i>
                </div>

                <!-- Portrait + info -->
                <div class="d-flex align-items-center mb-3" style="padding-right:26px;">
                    <img src="https://images.evetech.net/characters/<?php echo $p['toon_number']; ?>/portrait?size=128" class="portrait mr-3">
                    <div class="flex-grow-1 overflow-hidden">
                        <h6 class="text-white text-truncate mb-0"><?php echo htmlspecialchars($p['toon_name']); ?></h6>
                        <small class="corp-tag d-block text-truncate mb-2"><i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($p['corporation_name'] ?? 'N/A'); ?></small>
                        <div class="val-sp"><i class="fas fa-microchip mr-1"></i><?php echo number_format($p['TotalSP_M'], 2); ?> <small>M SP</small></div>
                        <div class="trade-tag mt-1"><i class="fas fa-briefcase mr-1"></i><?php echo $tradefield; ?></div>
                    </div>
                </div>

                <!-- Icons + Pocket + Evermarks badge (compact bottom row) -->
                <div class="d-flex justify-content-between align-items-center border-top border-secondary pt-2 mt-2">
                    <?php echo geticons($p['toon_number']); ?>
                    <span class="pocket-badge"><?php echo $pocket; ?></span>
                    <span class="badge-em-compact <?php echo $esHoy ? 'bg-hoy' : 'bg-pendiente'; ?>" title="Audited: <?php echo $p['lastdateevermark']; ?>">
                        <i class="fas fa-shield-alt mr-1"></i><?php echo number_format($p['evermarks']); ?>
                    </span>
                </div>

                <!-- Evermarks update form -->
                <form method="POST" action="<?php echo $self; ?>?tab=evermarks" class="mt-3">
                    <input type="hidden" name="toon_name" value="<?php echo htmlspecialchars($p['toon_name']); ?>">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-dark text-secondary border-secondary">EM</span>
                        </div>
                        <input type="number" name="evermarks_val" class="form-control input-ever" value="<?php echo $p['evermarks']; ?>" required min="0">
                        <div class="input-group-append">
                            <button class="btn btn-success" type="submit" name="update_evermarks"><i class="fas fa-save"></i></button>
                        </div>
                    </div>
                </form>

                <!-- Wallet -->
                <div class="wallet-bar">
                    <small class="text-secondary">WALLET BALANCE</small>
                    <span class="val-wallet"><i class="fas fa-wallet mr-1"></i><?php echo number_format($p['Wallet_M'], 2); ?> M ISK</span>
                </div>

            </div>
        </div>
    </div>
    <?php endwhile; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ══════════════════════════════════════════════════════════════════════════════
// OUTPUT
// ══════════════════════════════════════════════════════════════════════════════
echo ui_header("EVE Online - Unified Audit");
echo crew_navbar();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>EVE Online - Unified Audit</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ── BASE ── */
        body {
            background-color: #0b0c0e;
            color: #ced4da;
            font-family: 'Segoe UI', sans-serif;
            padding-top: 120px; /* navbar (~60px) + eve-tabs (~56px) */
            padding-bottom: 70px;
        }

        /* ── TABS ── */
        .eve-tabs {
            background-color: #0d0f11;
            border-bottom: 2px solid #007bff;
            padding: 0 20px;
            position: sticky;
            top: 0;
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
        .table-eve thead th { background-color: #212529; border-bottom: 2px solid #007bff; position: sticky; top: 120px; z-index: 10; color: #adb5bd !important; }
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

        /* ── POCKET6 COLORED BADGE (shared graduation + reputation) ── */
        .pocket6-badge {
            display: inline-block; padding: 2px 9px; font-size: 0.72rem;
            font-weight: 700; text-transform: uppercase; border-radius: 2px;
            min-width: 62px; text-align: center;
        }
        .portrait-mini { width: 32px; height: 32px; border: 1px solid #444; }
        .text-sp { color: #5dade2; font-family: 'Courier New', monospace; font-weight: bold; }
        .trade-tag { color: #bb86fc; font-weight: 500; letter-spacing: 0.5px; }
        .col-panel { background-color: rgba(255,255,255,0.02); border-left: 1px solid #333; }

        /* ── REPUTATION ── */
        .reputation-num { font-family: 'Consolas', monospace; font-weight: bold; font-size: 0.95rem; }
        .rep-pos { color: #28a745; } .rep-neg { color: #dc3545; } .rep-neu { color: #adb5bd; }
        .pocket-badge-dip { display: inline-block; padding: 2px 10px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; border-radius: 2px; min-width: 70px; text-align: center; }
        .trade-pill { background-color: #2d3748; color: #f39c12; padding: 1px 8px; border-radius: 10px; font-size: 0.75rem; white-space: nowrap; }
        .row-num { color: #6c757d; font-size: 0.78rem; }
        .industry-icons { display: inline-flex; gap: 9px; align-items: center; font-size: 1.05rem; }
        .industry-icons i { cursor: default; }
        .total-badge { background-color: #0d0f11; border: 1px solid #007bff; color: #007bff; font-size: 0.8rem; padding: 3px 10px; border-radius: 3px; }

        /* ── FILTER SHARED ── */
        .filter-label { color: #adb5bd; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; display: block; }
        .eve-select, .eve-select:focus { background-color: #1e2126; border-color: #495057; color: #e0e0e0; box-shadow: none; }

        /* ── BIOMETRICS CARDS ── */
        .card-eve { background-color: #1a1d21; border: 1px solid #343a40; border-radius: 0; margin-bottom: 20px; transition: border-color 0.2s, box-shadow 0.2s; position: relative; }
        .card-eve:hover { border-color: #007bff; box-shadow: 0 0 12px rgba(0,123,255,0.2); }
        .pilot-portrait { border-bottom: 2px solid #444; width: 100%; height: auto; }
        .sp-total { font-size: 1.25rem; color: #f8f9fa; font-weight: bold; text-align: center; }
        .acc-type-badge { position: absolute; top: 10px; right: 10px; background-color: rgba(0,0,0,0.7); padding: 5px 10px; border-radius: 4px; border: 1px solid #6c757d; }
        .gf-badge { position: absolute; top: 10px; left: 10px; background-color: rgba(0,0,0,0.7); padding: 5px 10px; border-radius: 4px; border: 1px solid #6c757d; z-index: 2; }
        .pocket-info { color: #a7aeb5; font-size: 0.85rem; }
        .corp-tag { color: #5dade2; font-size: 0.8rem; }
        .grafica-container { background-color: #1a1d21; padding: 8px; }
        .skill-stats { font-size: 0.75rem; text-align: left; margin-top: 5px; }
        .skill-stats div { padding: 2px 0; }

        /* ── EVERMARKS CARDS ── */
        .acctype-corner { position: absolute; top: 10px; right: 12px; font-size: 1.15rem; line-height: 1; }
        .portrait { width: 100px; height: 100px; border: 1px solid #444; }
        .val-sp { color: #5dade2; font-weight: bold; }
        .data-label { font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 1px; }
        /* Compact evermarks badge — sits inline in bottom row */
        .badge-em-compact { font-size: 0.82rem; padding: 3px 9px; font-weight: bold; border-radius: 2px; display: inline-flex; align-items: center; white-space: nowrap; }
        .bg-hoy       { background-color: #28a745; color: white; }
        .bg-pendiente { background-color: #dc3545; color: white; }
        .pocket-badge { background-color: #007bff; color: white; padding: 2px 10px; font-weight: bold; font-size: 0.78rem; text-transform: uppercase; border-radius: 2px; white-space: nowrap; }
        .input-ever { background-color: #000; color: #00ff00; border: 1px solid #444; width: 90px; text-align: center; }
        .wallet-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding-top: 9px; border-top: 1px solid #343a40; }
        .val-wallet { color: #f39c12; font-family: 'Courier New', monospace; font-weight: bold; }

        /* Mini toolbar */
        .mini-toolbar { display: inline-flex; gap: 3px; margin-left: 14px; vertical-align: middle; }
        .btn-tool { display: inline-flex; align-items: center; gap: 4px; padding: 4px 9px; font-size: 0.76rem; font-weight: 600; border-radius: 3px; border: 1px solid #555; cursor: pointer; text-decoration: none; transition: opacity 0.15s, transform 0.1s; line-height: 1.5; white-space: nowrap; }
        .btn-tool:hover { opacity: 0.72; transform: translateY(-1px); text-decoration: none; }
        .btn-tool-dark  { background-color: #1c1c1c; color: #bbb; border-color: #444; }
        .btn-tool-white { background-color: #f0f0f0; color: #111; border-color: #ccc; }

        /* ── TAB CONTENT ── */
        .tab-content-eve { padding: 25px 20px; }
    </style>
</head>
<body>

<!-- ── TABS NAV ── -->
<div class="eve-tabs">
    <ul class="nav">
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab == 'graduation') ? 'active' : ''; ?>" href="?tab=graduation">
                <i class="fas fa-graduation-cap"></i> Graduation
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab == 'reputation') ? 'active' : ''; ?>" href="?tab=reputation">
                <i class="fas fa-passport"></i> Reputation
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab == 'biometrics') ? 'active' : ''; ?>" href="?tab=biometrics">
                <i class="fas fa-fingerprint"></i> Biometrics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab == 'evermarks') ? 'active' : ''; ?>" href="?tab=evermarks">
                <i class="fas fa-medal"></i> Evermarks
            </a>
        </li>
    </ul>
</div>

<!-- ── TAB CONTENT ── -->
<div class="tab-content-eve">
    <?php
    switch ($active_tab) {
        case 'graduation': echo renderTabGraduation(); break;
        case 'reputation': echo renderTabReputation(); break;
        case 'biometrics': echo renderTabBiometrics(); break;
        case 'evermarks':  echo renderTabEvermarks();  break;
        default:           echo renderTabGraduation();
    }
    ?>
</div>

<!-- Chart.js init for Biometrics tab -->
<?php if ($active_tab === 'biometrics'): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const chartData = <?php echo json_encode($GLOBALS['chart_data'] ?? []); ?>;
    const colors = ['#007bff','#28a745','#ffc107','#dc3545','#17a2b8','#6610f2'];
    for (const [id, data] of Object.entries(chartData)) {
        const canvas = document.getElementById(id);
        if (!canvas) continue;
        new Chart(canvas.getContext('2d'), {
            type: 'pie',
            data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: colors, borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: true, legend: { display: false }, plugins: { tooltip: { enabled: true } } }
        });
    }
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#tbl-graduation').length) {
        $('#tbl-graduation').DataTable({
            pageLength: 50,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
            order: [],
            language: { search: "Search:", lengthMenu: "Show _MENU_ pilots" },
            columnDefs: [{ orderable: false, targets: [8] }]
        });
    }
    if ($('#tbl-reputation').length) {
        $('#tbl-reputation').DataTable({
            pageLength: 50,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
            order: [],
            language: { search: "Search:", lengthMenu: "Show _MENU_ records" },
            columnDefs: [{ orderable: false, targets: [6] }]
        });
    }
});
</script>
<?php echo ui_footer(); ?>
</body>
</html>
