<?php
/*
 * EVE Online - Manufacturing & Planetary Industry Dashboard
 *
 * @author    Alfonso Orozco Aguilar
 * @license   GPL-3.0-or-later
 * @date      2026-06-01
 */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once '../config.php';
include_once '../ui_functions.php';

// Apply security
check_authorization();

function aValues319($Qx){
    global $link;
    $rsX = mysqli_query($link,$Qx);
    $Qx2=strtolower($Qx);
    if (left($Qx2,6)<>'select') return "";
    $aDataX = array();
    $rows=mysqli_num_rows($rsX);
    if ($rows==0) return array("","");

    $Campos = mysqli_num_fields($rsX);
    while ($regX = mysqli_fetch_array($rsX)) {
        for($iX=0; $iX<$Campos; $iX++){
           $finfo=mysqli_fetch_field_direct($rsX,$iX);
           $name=$finfo->name;
            $aDataX[] = $regX[ $name ];
        }
    }
    return $aDataX;
}

function left($str, $length) {
    return substr($str, 0, $length);
}

function right($str, $length) {
    return substr($str, -$length);
}

// ---------------------------------------------------------------------------
// POCKET6 COLOR FUNCTIONS
// ---------------------------------------------------------------------------
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

function render_pocket_cell($val) {
    $color = get_pocket_color($val);
    $text = get_pocket_text($val);
    return '<span class="pocket-badge-dip" style="background-color:' . $color . ';color:' . $text . ';">'
           . htmlspecialchars($val) . '</span>';
}

/**
 * Gets pilots with active jobs
 */
function getPilotsWithJobs() {
    global $link;

    $query = "SELECT toon_number, toon_name, pocket6, jobs, skillpoints
              FROM PILOTS
              WHERE jobs != '[]' AND jobs IS NOT NULL
              ORDER BY skillpoints DESC";

    $result = mysqli_query($link, $query);

    if (!$result) {
        die("Error in jobs query: " . mysqli_error($link));
    }

    $jobs_data = [];
    $total_jobs = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $data = stripslashes($row['jobs']);
        $jobs = json_decode($data, true);
        if (is_array($jobs) && count($jobs) > 0) {
            $total_jobs += count($jobs);
            $jobs_data[] = $row;
        }
    }

    mysqli_free_result($result);

    return ['data' => $jobs_data, 'total' => $total_jobs];
}

/**
 * Gets pilots with active planets
 */
function getPilotsWithPlanets() {
    global $link;

    $query = "SELECT toon_number, toon_name, pocket6, planets, skillpoints
              FROM PILOTS
              WHERE planets != '[]' AND planets IS NOT NULL
              ORDER BY skillpoints DESC";

    $result = mysqli_query($link, $query);

    if (!$result) {
        die("Error in planets query: " . mysqli_error($link));
    }

    $planets_data = [];
    $total_planets = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $data = stripslashes($row['planets']);
        $planets = json_decode($data, true);
        if (is_array($planets) && count($planets) > 0) {
            $total_planets += count($planets);
            $planets_data[] = $row;
        }
    }

    mysqli_free_result($result);

    return ['data' => $planets_data, 'total' => $total_planets];
}

function description($value){
    $sql = "select typeName as description from invTypes where typeID='$value'";
    list($pass) = aValues319($sql);
    if ($pass == '') $pass = $value;
    $pass = addslashes($pass);
    return $pass;
}

/**
 * Renders the Manufacturing Jobs table
 */
function renderJobsTable($jobs_info) {
    $jobs_data = $jobs_info['data'];

    if (count($jobs_data) == 0) {
        return '<tr><td colspan="12" class="text-center text-muted">
                    <i class="fas fa-info-circle"></i> No active jobs
                </td></tr>';
    }

    $html = '';
    $current_date = new DateTime('now', new DateTimeZone('UTC'));
    $csh = 0;

    foreach ($jobs_data as $row) {
        $data = stripslashes($row['jobs']);
        $jobs = json_decode($data, true);
        $pocket6 = $row['pocket6'] ?? '';

        if (is_array($jobs) && count($jobs) > 0) {
            foreach ($jobs as $job) {
                $csh++;
                $status_badge = $job['status'] == 'active' ? 'badge-success' : 'badge-info';
                $start_date = date('Y-m-d H:i', strtotime($job['start_date']));
                $end_date = date('Y-m-d H:i', strtotime($job['end_date']));
                $cost_formatted = number_format($job['cost'], 2);
                $skillpoints_formatted = number_format($row['skillpoints'] / 1000000, 2);

                // Calculate remaining days
                $end_datetime = new DateTime($job['end_date']);
                $diff = $current_date->diff($end_datetime);
                $days_remaining = $diff->invert == 0 ? $diff->days : -$diff->days;

                // Check if job is ready
                $is_ready = $end_datetime < $current_date;
                $row_class = $is_ready ? 'table-success font-weight-bold' : '';

                $where2 = $job['facility_id'];
                list($where) = aValues319("select itemName from invUniqueNames where itemID='$where2'");
                if ($where == "") $where = $where2;

                $html .= "<tr class='{$row_class}'>";
                $html .= "<td><strong>$csh</strong></td>";
                $html .= "<td><strong>{$row['toon_name']}</strong><br><small class='text-muted'>#{$row['toon_number']}</small></td>";
                $html .= "<td>" . render_pocket_cell($pocket6) . "</td>";
                $html .= "<td>{$skillpoints_formatted}M</td>";
                $html .= "<td>$where</td>";
                $html .= "<td>" . description($job['product_type_id']) . "</td>";
                $html .= "<td>{$job['runs']}</td>";
                $html .= "<td>{$cost_formatted}</td>";
                $html .= "<td><small>{$start_date}</small></td>";
                $html .= "<td><small>{$end_date}</small></td>";
                $html .= "<td><span class='badge {$status_badge}'>" . ucfirst($job['status']) . "</span></td>";
                $html .= "<td><strong>{$days_remaining}</strong></td>";
                $html .= "</tr>";
            }
        }
    }

    return $html;
}

/**
 * Renders the Planetary Industry table
 */
function renderPlanetsTable($planets_info) {
    $planets_data = $planets_info['data'];

    if (count($planets_data) == 0) {
        return '<tr><td colspan="11" class="text-center text-muted">
                    <i class="fas fa-info-circle"></i> No active planets
                </td></tr>';
    }

    $html = '';
    $current_date = new DateTime('now', new DateTimeZone('UTC'));

    $planet_types = [
        'temperate' => 'Temperate', 'ice' => 'Ice', 'oceanic' => 'Oceanic',
        'lava' => 'Lava', 'barren' => 'Barren', 'gas' => 'Gas',
        'storm' => 'Storm', 'plasma' => 'Plasma'
    ];

    $csh = 0;

    foreach ($planets_data as $row) {
        $data = stripslashes($row['planets']);
        $planets = json_decode($data, true);
        $pocket6 = $row['pocket6'] ?? '';

        if (is_array($planets) && count($planets) > 0) {
            foreach ($planets as $planet) {
                $csh++;
                $last_update = date('Y-m-d H:i', strtotime($planet['last_update']));
                $skillpoints_formatted = number_format($row['skillpoints'] / 1000000, 2);

                $last_update_datetime = new DateTime($planet['last_update']);
                $diff_days = $current_date->diff($last_update_datetime)->days;
                $is_outdated = $diff_days > 7;
                $row_class = $is_outdated ? 'table-danger font-weight-bold' : '';

                $planet_type = isset($planet_types[$planet['planet_type']])
                    ? $planet_types[$planet['planet_type']]
                    : ucfirst($planet['planet_type']);

                $where2 = $planet['planet_id'];
                list($where) = aValues319("select itemName from invUniqueNames where itemID='$where2'");
                if ($where == "") $where = $where2;

                $html .= "<tr class='{$row_class}'>";
                $html .= "<td><strong>$csh</strong></td>";
                $html .= "<td><strong>{$row['toon_name']}</strong><br><small class='text-muted'>#{$row['toon_number']}</small></td>";
                $html .= "<td>" . render_pocket_cell($pocket6) . "</td>";
                $html .= "<td>{$skillpoints_formatted}M</td>";
                $html .= "<td>$where</td>";
                $html .= "<td>{$planet_type}</td>";
                $html .= "<td>{$planet['solar_system_id']}</td>";
                $html .= "<td>{$planet['num_pins']}</td>";
                $html .= "<td>{$planet['upgrade_level']}</td>";
                $html .= "<td><small>{$last_update}</small></td>";
                $html .= "<td><strong>{$diff_days}</strong></td>";
                $html .= "</tr>";
            }
        }
    }

    return $html;
}

/**
 * Generates summary table of Build vs Upgrade by pilot
 */
function renderJobsSummary($jobs_info) {
    $jobs_data = $jobs_info['data'];

    if (count($jobs_data) == 0) {
        return '<tr><td colspan="6" class="text-center text-muted">No data to summarize</td></tr>';
    }

    $summary = [];
    $total_build = 0;
    $total_upgrade = 0;

    foreach ($jobs_data as $row) {
        $data = stripslashes($row['jobs']);
        $jobs = json_decode($data, true);
        $pilot = $row['toon_name'];
        $pocket6 = $row['pocket6'] ?? '';

        if (!isset($summary[$pilot])) {
            $summary[$pilot] = ['pocket6' => $pocket6, 'build' => 0, 'upgrade' => 0];
        }

        if (is_array($jobs)) {
            foreach ($jobs as $job) {
                $desc = description($job['product_type_id']);
                // If it contains "Blueprint" it's an upgrade, otherwise it's a build
                if (stripos($desc, 'Blueprint') !== false) {
                    $summary[$pilot]['upgrade']++;
                    $total_upgrade++;
                } else {
                    $summary[$pilot]['build']++;
                    $total_build++;
                }
            }
        }
    }

    $html = '';
    $row_num = 0;
    foreach ($summary as $pilot => $data) {
        $row_num++;
        $total_pilot = $data['build'] + $data['upgrade'];
        $html .= "<tr>";
        $html .= "<td><strong>$row_num</strong></td>";
        $html .= "<td><strong>" . htmlspecialchars($pilot) . "</strong></td>";
        $html .= "<td>" . render_pocket_cell($data['pocket6']) . "</td>";
        $html .= "<td class='text-center'>{$data['build']}</td>";
        $html .= "<td class='text-center'>{$data['upgrade']}</td>";
        $html .= "<td class='text-center font-weight-bold'>{$total_pilot}</td>";
        $html .= "</tr>";
    }

    // Total row
    $grand_total = $total_build + $total_upgrade;
    $html .= "<tr class='table-dark font-weight-bold'>";
    $html .= "<td colspan='3' class='text-right'>TOTAL ACCUMULATED</td>";
    $html .= "<td class='text-center'>{$total_build}</td>";
    $html .= "<td class='text-center'>{$total_upgrade}</td>";
    $html .= "<td class='text-center'>{$grand_total}</td>";
    $html .= "</tr>";

    return $html;
}

// Get data
$jobs_info = getPilotsWithJobs();
$planets_info = getPilotsWithPlanets();

// Display interface
echo ui_header("Manufacturing & Planetary Industry");
echo crew_navbar();
echo "<br />";
?>

<style>
.section-title {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    margin-top: 20px;
    margin-bottom: 15px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.table-success {
    background-color: #d4edda !important;
}
.table-danger {
    background-color: #f8d7da !important;
}
.card {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.badge-success {
    background-color: #28a745;
}
.badge-info {
    background-color: #17a2b8;
}
.pocket-badge-dip {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
/* Custom DataTables search box styling */
.dataTables_filter input {
    border: 1px solid #764ba2;
    border-radius: 4px;
    padding: 5px 10px;
    margin-left: 5px;
}
.dataTables_filter label {
    font-weight: 600;
    color: #444;
}
</style>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<div class="container-fluid mt-4">

    <!-- MANUFACTURING JOBS TABLE -->
    <h3 class="section-title">
        <i class="fas fa-industry"></i> Manufacturing Jobs
        <span class="badge badge-light ml-2"><?php echo $jobs_info['total']; ?></span>
    </h3>

    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table id="jobsTable" class="table table-striped table-hover table-sm mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th><i class="fas fa-calculator"></i> #</th>
                            <th><i class="fas fa-user"></i> Pilot</th>
                            <th><i class="fas fa-wallet"></i> Pocket</th>
                            <th><i class="fas fa-trophy"></i> SP</th>
                            <th><i class="fas fa-list-ol"></i> Facility</th>
                            <th><i class="fas fa-cube"></i> Product</th>
                            <th><i class="fas fa-play"></i> Runs</th>
                            <th><i class="fas fa-dollar-sign"></i> Cost</th>
                            <th><i class="fas fa-calendar-alt"></i> Start</th>
                            <th><i class="fas fa-calendar-check"></i> End</th>
                            <th><i class="fas fa-info-circle"></i> Status</th>
                            <th><i class="fas fa-hourglass-end"></i> Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo renderJobsTable($jobs_info); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PLANETARY INDUSTRY TABLE -->
    <h3 class="section-title">
        <i class="fas fa-globe"></i> Planetary Industry
        <span class="badge badge-light ml-2"><?php echo $planets_info['total']; ?></span>
    </h3>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th><i class="fas fa-calculator"></i> #</th>
                            <th><i class="fas fa-user"></i> Pilot</th>
                            <th><i class="fas fa-wallet"></i> Pocket</th>
                            <th><i class="fas fa-trophy"></i> SP</th>
                            <th><i class="fas fa-globe"></i> Planet</th>
                            <th><i class="fas fa-leaf"></i> Type</th>
                            <th><i class="fas fa-map-marker-alt"></i> System</th>
                            <th><i class="fas fa-thumbtack"></i> Pins</th>
                            <th><i class="fas fa-arrow-up"></i> Lvl</th>
                            <th><i class="fas fa-clock"></i> Updated</th>
                            <th><i class="fas fa-calendar-times"></i> Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo renderPlanetsTable($planets_info); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- BUILD VS UPGRADE SUMMARY -->
    <h3 class="section-title mt-4">
        <i class="fas fa-chart-bar"></i> Summary: Build vs Upgrade
    </h3>

    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th><i class="fas fa-hashtag"></i> #</th>
                            <th><i class="fas fa-user"></i> Pilot</th>
                            <th><i class="fas fa-wallet"></i> Pocket</th>
                            <th><i class="fas fa-hammer"></i> Build</th>
                            <th><i class="fas fa-arrow-up"></i> Upgrade</th>
                            <th><i class="fas fa-calculator"></i> Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo renderJobsSummary($jobs_info); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mt-4 mb-4">
        <div class="col-md-6">
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Ready jobs:</strong> Rows highlighted in green
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Outdated planets:</strong> More than 7 days without update (in red)
            </div>
        </div>
    </div>
</div>

<!-- jQuery and DataTables -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#jobsTable').DataTable({
        pageLength: 200,
        lengthMenu: [[50, 100, 200, 500, -1], [50, 100, 200, 500, "All"]],
        ordering: false,
        language: {
            search: "Filter:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No entries to show",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });
});
</script>

<?php
echo ui_footer();
?>
