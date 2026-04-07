<?php
// session_start();
// require('../conf/connecte_bd.php');

// // Sécurité : accès réservé aux agents (gestionnaires)
// if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'agent') {
//     header('Location: ../index/login_page.php');
//     exit;
// }

$userId  = (int)$_SESSION['user_id'];
$userNom = isset($_SESSION['user_nom']) ? $_SESSION['user_nom'] : 'Agent';

// ================== RÉCUPÉRER LES CATÉGORIES DE L'AGENT ==================
$agentCategories = [];
$categoryIds     = [];

try {
    $stmtCat = $pdo->prepare("
        SELECT g.categorie_id, c.categorie
        FROM gestionnaires g
        INNER JOIN categories c ON g.categorie_id = c.id
        WHERE g.user_id = :user_id
    ");
    $stmtCat->execute([':user_id' => $userId]);
    $agentCategories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    foreach ($agentCategories as $row) {
        $categoryIds[] = (int)$row['categorie_id'];
    }
} catch (PDOException $e) {
    $agentCategories = [];
    $categoryIds     = [];
}

// ================== STATISTIQUES PAR STATUT ==================
$statutCounts = [
    'total'                     => 0,
    'En cours de traitement'    => 0,
    'En attente d’informations' => 0,
    'Acceptée'                  => 0,
    'Fermée'                    => 0,
];

$recentReclamations = [];

if (!empty($categoryIds)) {
    // Préparer le IN (?, ?, ?)
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

    // ---- Compter par statut ----
    $sqlStats = "
        SELECT s.statut, COUNT(*) AS total
        FROM reclamations r
        INNER JOIN statuts s ON r.statut_id = s.id
        WHERE r.categorie_id IN ($placeholders)
        GROUP BY s.statut
    ";
    $stmtStats = $pdo->prepare($sqlStats);
    $stmtStats->execute($categoryIds);
    $rowsStats = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rowsStats as $row) {
        $libelle = $row['statut'];
        $nb      = (int)$row['total'];

        if (isset($statutCounts[$libelle])) {
            $statutCounts[$libelle] = $nb;
        }
        $statutCounts['total'] += $nb;
    }

    // ---- Dernières réclamations de ses catégories ----
    $sqlRecent = "
        SELECT r.id,
               u.nom        AS reclamant_nom,
               c.categorie  AS categorie_nom,
               s.statut,
               r.objet,
               r.date_soumission
        FROM reclamations r
        INNER JOIN users u      ON r.user_id = u.id
        INNER JOIN categories c ON r.categorie_id = c.id
        INNER JOIN statuts s    ON r.statut_id = s.id
        WHERE r.categorie_id IN ($placeholders)
        ORDER BY r.date_soumission DESC, r.id DESC
        LIMIT 6
    ";
    $stmtRecent = $pdo->prepare($sqlRecent);
    $stmtRecent->execute($categoryIds);
    $recentReclamations = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
}

// Pourcentages (éviter division par zéro)
$total = max(1, $statutCounts['total']);

$percentEnCours    = round($statutCounts['En cours de traitement']    * 100 / $total);
$percentEnAttente  = round($statutCounts['En attente d’informations'] * 100 / $total);
$percentAcceptee   = round($statutCounts['Acceptée']                  * 100 / $total);
$percentFermee     = round($statutCounts['Fermée']                    * 100 / $total);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include("../conf/head.php"); ?>
    <title>Tableau de bord - Espace gestionnaire</title>
    <style>
        body {
            background-color: #f5f7fb;
        }
        .card-stat {
            border-radius: 16px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .card-stat .icon-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #003f51;
            color: #fff;
            font-size: 1.25rem;
        }
        .section-title {
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #003f51;
        }
    </style>
</head>
<body>

    <!-- CONTENU SANS MENU -->
    <div class="container py-4">

        <div class="mb-4">
            <h3 class="mb-1">Bonjour, <?php echo htmlspecialchars($userNom); ?></h3>
            <p class="text-muted mb-0">
                Voici un aperçu des réclamations de vos catégories.
            </p>
            <?php if (!empty($agentCategories)): ?>
                <small class="text-muted">
                    Catégories gérées :
                    <?php
                        $nomsCat = array_map(function($c) {
                            return htmlspecialchars($c['categorie']);
                        }, $agentCategories);
                        echo implode(', ', $nomsCat);
                    ?>
                </small>
            <?php else: ?>
                <small class="text-danger">
                    Aucune catégorie n'est encore associée à ce compte gestionnaire.
                </small>
            <?php endif; ?>
        </div>

        <!-- STATISTIQUES PRINCIPALES -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card card-stat h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small mb-1">Total réclamations</div>
                            <div class="h3 mb-0">
                                <?php echo $statutCounts['total']; ?>
                            </div>
                        </div>
                        <div class="icon-circle">
                            <i class="bi bi-list-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-stat h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small mb-1">En cours de traitement</div>
                            <div class="h4 mb-0">
                                <?php echo $statutCounts['En cours de traitement']; ?>
                            </div>
                            <small class="text-muted"><?php echo $percentEnCours; ?> %</small>
                        </div>
                        <div class="icon-circle">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-stat h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small mb-1">En attente d’informations</div>
                            <div class="h4 mb-0">
                                <?php echo $statutCounts['En attente d’informations']; ?>
                            </div>
                            <small class="text-muted"><?php echo $percentEnAttente; ?> %</small>
                        </div>
                        <div class="icon-circle">
                            <i class="bi bi-question-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-stat h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small mb-1">Clôturées (Acceptée + Fermée)</div>
                            <div class="h4 mb-0">
                                <?php echo $statutCounts['Acceptée'] + $statutCounts['Fermée']; ?>
                            </div>
                            <small class="text-muted">
                                <?php echo $percentAcceptee + $percentFermee; ?> %
                            </small>
                        </div>
                        <div class="icon-circle">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RÉPARTITION PAR STATUT + DERNIÈRES RÉCLAMATIONS -->
        <div class="row g-4">
            <!-- Répartition par statut -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h6 class="section-title mb-3">Répartition par statut</h6>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>En cours de traitement</span>
                                <span><?php echo $statutCounts['En cours de traitement']; ?> (<?php echo $percentEnCours; ?> %)</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar"
                                     style="width: <?php echo $percentEnCours; ?>%;"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>En attente d’informations</span>
                                <span><?php echo $statutCounts['En attente d’informations']; ?> (<?php echo $percentEnAttente; ?> %)</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar"
                                     style="width: <?php echo $percentEnAttente; ?>%;"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Acceptée</span>
                                <span><?php echo $statutCounts['Acceptée']; ?> (<?php echo $percentAcceptee; ?> %)</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar"
                                     style="width: <?php echo $percentAcceptee; ?>%;"></div>
                            </div>
                        </div>

                        <div class="mb-1">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Fermée</span>
                                <span><?php echo $statutCounts['Fermée']; ?> (<?php echo $percentFermee; ?> %)</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar"
                                     style="width: <?php echo $percentFermee; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dernières réclamations -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h6 class="section-title mb-3">Dernières réclamations</h6>

                        <?php if (empty($recentReclamations)): ?>
                            <p class="text-muted mb-0">
                                Aucune réclamation pour vos catégories pour le moment.
                            </p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Réclamant</th>
                                            <th>Catégorie</th>
                                            <th>Objet</th>
                                            <th>Statut</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentReclamations as $rec): ?>
                                            <tr>
                                                <td><?php echo (int)$rec['id']; ?></td>
                                                <td><?php echo htmlspecialchars($rec['reclamant_nom']); ?></td>
                                                <td><?php echo htmlspecialchars($rec['categorie_nom']); ?></td>
                                                <td><?php echo htmlspecialchars($rec['objet']); ?></td>
                                                <td>
                                                    <?php
                                                        $badgeClass = 'secondary';
                                                        if ($rec['statut'] === 'En cours de traitement') {
                                                            $badgeClass = 'warning';
                                                        } elseif ($rec['statut'] === 'En attente d’informations') {
                                                            $badgeClass = 'info';
                                                        } elseif ($rec['statut'] === 'Acceptée') {
                                                            $badgeClass = 'success';
                                                        } elseif ($rec['statut'] === 'Fermée') {
                                                            $badgeClass = 'dark';
                                                        }
                                                    ?>
                                                    <span class="badge bg-<?php echo $badgeClass; ?>">
                                                        <?php echo htmlspecialchars($rec['statut']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                        $d = $rec['date_soumission'];
                                                        echo $d ? date('d/m/Y', strtotime($d)) : '';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div> <!-- /row -->
    </div> <!-- /container -->

</body>
</html>
