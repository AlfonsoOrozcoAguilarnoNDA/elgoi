<?php
/**
 * EVE Pilot Portrait Comparison
 * License: GPL
 * PHP 8.x Procedural | Bootstrap 4.6.x | Font Awesome 5.15.4
 * 
 * Displays all pilots from the PILOTS table (excluding those with "catalog" in name)
 * as large portrait cards for visual comparison. Cards can be hidden; F5 restores all.
 * 
 * Requires: ../config.php with $link (mysqli connection)
 */

include '../config.php';

// --- Fetch all pilots excluding "catalog" ---
$sql = "SELECT toon_number, toon_name, pocket6 FROM PILOTS 
        WHERE toon_name NOT LIKE '%catalog%' 
        ORDER BY toon_name ASC";

$result = $link->query($sql);
if (!$result) {
    die("Database query failed: " . $link->error);
}

$pilots = [];
while ($row = $result->fetch_assoc()) {
    $pilots[] = $row;
}
$result->free();

// Chunk into groups of 3 for the grid
$chunks = array_chunk($pilots, 3);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Pilot Portrait Comparison</title>

    <!-- Bootstrap 4.6.x CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">

    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        :root {
            --bg-dark: #0a0e14;
            --card-bg: #111820;
            --border-color: #1c2530;
            --text-primary: #e6edf3;
            --text-secondary: #7d8590;
            --accent: #2f81f7;
            --danger: #f85149;
            --success: #3fb950;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
        }

        .page-header {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1.25rem;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: var(--text-primary);
            font-weight: 700;
            letter-spacing: -0.5px;
            font-size: 1.75rem;
        }

        .info-banner {
            background: linear-gradient(135deg, rgba(47, 129, 247, 0.08) 0%, rgba(47, 129, 247, 0.03) 100%);
            border: 1px solid rgba(47, 129, 247, 0.15);
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2.5rem;
        }

        .info-banner .banner-title {
            color: var(--accent);
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.6rem;
            display: flex;
            align-items: center;
        }

        .info-banner .banner-title i {
            margin-right: 0.5rem;
        }

        .info-banner p {
            margin-bottom: 0.4rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .info-banner p:last-child {
            margin-bottom: 0;
        }

        .info-banner kbd {
            background-color: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            color: var(--text-primary);
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .pilot-card-wrapper {
            margin-bottom: 1.5rem;
        }

        .pilot-card-wrapper.hidden-card {
            display: none !important;
        }

        .pilot-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), 
                        box-shadow 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                        border-color 0.25s ease;
            position: relative;
            height: 100%;
        }

        .pilot-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(47, 129, 247, 0.1);
            border-color: rgba(47, 129, 247, 0.3);
        }

        .portrait-container {
            position: relative;
            width: 100%;
            padding-top: 100%;
            overflow: hidden;
            background: linear-gradient(145deg, #0d1117 0%, #161b22 100%);
        }

        .portrait-container img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .pilot-card:hover .portrait-container img {
            transform: scale(1.04);
        }

        .card-body {
            padding: 1rem 1.25rem 1.25rem;
        }

        .pilot-name {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.2rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pilot-id {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            opacity: 0.8;
        }

        .pocket-badge {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-top: 0.6rem;
        }

        .pocket-clean { 
            background-color: rgba(63, 185, 80, 0.12); 
            color: var(--success); 
            border: 1px solid rgba(63, 185, 80, 0.2);
        }
        .pocket-flagged { 
            background-color: rgba(248, 81, 73, 0.12); 
            color: var(--danger); 
            border: 1px solid rgba(248, 81, 73, 0.2);
        }
        .pocket-default { 
            background-color: rgba(125, 133, 144, 0.1); 
            color: var(--text-secondary); 
            border: 1px solid rgba(125, 133, 144, 0.15);
        }

        .hide-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: rgba(10, 14, 20, 0.9);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 10;
            font-size: 0.85rem;
            backdrop-filter: blur(4px);
        }

        .hide-btn:hover {
            background-color: var(--danger);
            color: #fff;
            border-color: var(--danger);
            transform: scale(1.1);
        }

        .refresh-hint {
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-align: center;
            margin-top: 3rem;
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .refresh-hint i {
            margin-right: 0.5rem;
            opacity: 0.7;
        }

        .empty-state {
            text-align: center;
            padding: 5rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 1.25rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 0.75rem 1rem;
            background-color: rgba(255,255,255,0.02);
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        .stats-bar span {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .stats-bar .count {
            color: var(--accent);
            font-weight: 600;
        }

        /* Responsive: ensure 3 per row on large, 2 on medium, 1 on small */
        @media (max-width: 991px) {
            .pilot-card-wrapper {
                margin-bottom: 1.25rem;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-3 px-md-4 px-lg-5">

    <!-- Page Header -->
    <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
        <div>
            <h1><i class="fas fa-portrait mr-2" style="color: var(--accent);"></i>EVE Pilot Portrait Comparison</h1>
            <small style="color: var(--text-secondary);">Visual aid for comparing character appearances across the entire roster</small>
        </div>
        <button class="btn btn-outline-secondary btn-sm mt-3 mt-md-0" onclick="location.reload()" style="border-color: var(--border-color); color: var(--text-secondary);">
            <i class="fas fa-sync-alt mr-1"></i> Refresh
        </button>
    </div>

    <!-- Info Banner -->
    <div class="info-banner">
        <div class="banner-title"><i class="fas fa-info-circle"></i>About This Tool</div>
        <p><strong>Note:</strong> Characters with "catalog" in their name are excluded from display for administrative purposes.</p>
        <p>This script is a visual aid to compare portraits of existing characters or to help create new ones with a similar appearance. Beyond that, it serves no other function.</p>
        <p class="mb-0"><i class="fas fa-mouse-pointer mr-1"></i> Click the <i class="fas fa-eye-slash" style="font-size: 0.8em;"></i> icon on any card to hide it. Press <kbd>F5</kbd> or click Refresh to restore all cards.</p>
    </div>

    <?php if (empty($pilots)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <h3>No Pilots Found</h3>
            <p>The PILOTS table appears to be empty, or all records contain "catalog" in their name.</p>
        </div>
    <?php else: ?>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <span>Total Pilots Displayed: <span class="count"><?php echo count($pilots); ?></span></span>
            <span style="font-size: 0.8rem; opacity: 0.6;"><i class="fas fa-database mr-1"></i>Source: PILOTS table</span>
        </div>

        <!-- Pilot Grid -->
        <?php foreach ($chunks as $chunk): ?>
            <div class="row">
                <?php foreach ($chunk as $pilot): ?>
                    <div class="col-12 col-md-6 col-lg-4 pilot-card-wrapper" id="card-<?php echo (int)$pilot['toon_number']; ?>">
                        <div class="pilot-card">
                            <button class="hide-btn" onclick="hideCard('card-<?php echo (int)$pilot['toon_number']; ?>')" title="Hide this card">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                            <div class="portrait-container">
                                <img src="https://images.evetech.net/characters/<?php echo (int)$pilot['toon_number']; ?>/portrait?size=512" 
                                     alt="Portrait of <?php echo htmlspecialchars($pilot['toon_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                     loading="lazy"
                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%25%22 height=%22100%25%22%3E%3Crect fill=%22%23111820%22 width=%22100%25%22 height=%22100%25%22/%3E%3Ctext fill=%22%237d8590%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-family=%22sans-serif%22 font-size=%2214%22%3EImage Unavailable%3C/text%3E%3C/svg%3E'">
                            </div>
                            <div class="card-body">
                                <div class="pilot-name" title="<?php echo htmlspecialchars($pilot['toon_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($pilot['toon_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="pilot-id">ID: <?php echo (int)$pilot['toon_number']; ?></div>
                                <?php 
                                $pocket = $pilot['pocket6'] ?? 'N/A';
                                $pocketClass = 'pocket-default';
                                if (strcasecmp($pocket, 'CLEAN') === 0) {
                                    $pocketClass = 'pocket-clean';
                                } elseif (strcasecmp($pocket, 'FLAGGED') === 0 || strcasecmp($pocket, 'SUSPECT') === 0) {
                                    $pocketClass = 'pocket-flagged';
                                }
                                ?>
                                <span class="pocket-badge <?php echo $pocketClass; ?>">
                                    <?php echo htmlspecialchars($pocket, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <!-- Footer Hint -->
    <div class="refresh-hint">
        <i class="fas fa-lightbulb"></i>
        Hidden cards will reappear when you refresh the page (<kbd>F5</kbd>).
    </div>

</div>

<!-- JavaScript -->
<script>
    /**
     * Hides a pilot card wrapper by its ID.
     * The card reappears on page refresh (F5) since no state is persisted.
     */
    function hideCard(cardId) {
        const wrapper = document.getElementById(cardId);
        if (wrapper) {
            wrapper.classList.add('hidden-card');
        }
    }
</script>

<!-- Bootstrap 4.6.x JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>

</body>
</html>
