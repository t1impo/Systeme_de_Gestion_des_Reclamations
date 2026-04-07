<?php

$userId  = $_SESSION['user_id'];
$message = '';

// ================== FILTRES (GET) ==================
// Vue globale : encours | clos
$allowedViews = ['encours', 'clos'];
$view = $_GET['view'] ?? 'encours';
if (!in_array($view, $allowedViews, true)) {
    $view = 'encours';
}

// Onglet de statut (pour la vue encours uniquement) : en_cours | en_attente
$allowedStatusTabs = ['en_cours', 'en_attente'];
$statutTab = $_GET['status_tab'] ?? 'en_cours';
if (!in_array($statutTab, $allowedStatusTabs, true)) {
    $statutTab = 'en_cours';
}

// Champs et terme de recherche
$allowedSearchFields = ['numero', 'objet', 'categorie'];
$searchField = $_GET['search_field'] ?? 'numero';
if (!in_array($searchField, $allowedSearchFields, true)) {
    $searchField = 'numero';
}

$searchTerm      = trim($_GET['search_term'] ?? '');
$categorieFiltre = isset($_GET['categorie_id']) ? (int)$_GET['categorie_id'] : 0;

// ================== TRAITEMENT FORMULAIRES (POST) ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formType = $_POST['form_type'] ?? '';

    // ---------- CREATION D'UNE NOUVELLE RECLAMATION ----------
    if ($formType === 'create') {

        $categorie_id_new = (int)($_POST['categorie_id'] ?? 0);
        $objet_new        = trim($_POST['objet'] ?? '');
        $description_new  = trim($_POST['description'] ?? '');

        if ($categorie_id_new <= 0 || $objet_new === '' || $description_new === '') {
            $message = 'Veuillez remplir tous les champs obligatoires pour la nouvelle réclamation.';
        } else {
            try {

                // Récupérer l'ID du statut "En cours de traitement"
                $stmtStatut = $pdo->prepare("SELECT id FROM statuts WHERE statut = :statut_libelle LIMIT 1");
                $stmtStatut->execute([':statut_libelle' => 'En cours de traitement']);
                $statutRow = $stmtStatut->fetch(PDO::FETCH_ASSOC);
                // Si introuvable, on retombe sur l'id 3 par défaut (d'après les données de base)
                $statutId  = $statutRow ? (int)$statutRow['id'] : 3;

                $stmt = $pdo->prepare("
                    INSERT INTO reclamations
                        (user_id, categorie_id, objet, description, statut_id, date_soumission)
                    VALUES
                        (:user_id, :categorie_id, :objet, :description, :statut_id, NOW())
                ");
                $stmt->execute([
                    ':user_id'      => $userId,
                    ':categorie_id' => $categorie_id_new,
                    ':objet'        => $objet_new,
                    ':description'  => $description_new,
                    ':statut_id'    => $statutId,
                ]);

                $newRecId = (int)$pdo->lastInsertId();

                // Pièces jointes
                if (!empty($_FILES['pieces_jointes']['name'][0])) {
                    $files = $_FILES['pieces_jointes'];
                    $finfo = new finfo(FILEINFO_MIME_TYPE);

                    global $ALLOWED_MIME_TYPES, $ALLOWED_EXTENSIONS;

                    for ($i = 0; $i < count($files['name']); $i++) {
                        $tmpName      = $files['tmp_name'][$i];
                        $originalName = basename($files['name'][$i]);
                        $size         = (int)$files['size'][$i];

                        if ($size > MAX_FILE_SIZE) {
                            continue;
                        }

                        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                        if (!in_array($extension, $ALLOWED_EXTENSIONS)) {
                            continue;
                        }

                        $mimeType = $finfo->file($tmpName);
                        if (!in_array($mimeType, $ALLOWED_MIME_TYPES)) {
                            continue;
                        }

                        $newFileName = uniqid('pj_', true) . '.' . $extension;
                        $destination = UPLOAD_DIR . '/' . $newFileName;

                        if (move_uploaded_file($tmpName, $destination)) {
                            $stmtPj = $pdo->prepare("
                                INSERT INTO pieces_jointes (reclamation_id, chemin_fichier, type_mime, date_ajout)
                                VALUES (:reclamation_id, :chemin_fichier, :type_mime, NOW())
                            ");
                            $stmtPj->execute([
                                ':reclamation_id' => $newRecId,
                                ':chemin_fichier' => 'uploads/reclamations/' . $newFileName,
                                ':type_mime'      => $mimeType,
                            ]);
                        }
                    }
                }

                $message = 'Votre réclamation a été soumise avec succès.';

            } catch (PDOException $e) {
                $message = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }

    // ---------- MODIFICATION D'UNE RECLAMATION ----------
    } elseif ($formType === 'edit') {

        $reclamationId = (int)($_POST['reclamation_id'] ?? 0);
        $categorie_id  = (int)($_POST['categorie_id'] ?? 0);
        $objet         = trim($_POST['objet'] ?? '');
        $description   = trim($_POST['description'] ?? '');

        if ($reclamationId <= 0 || $categorie_id <= 0 || $objet === '' || $description === '') {
            $message = 'Veuillez remplir tous les champs obligatoires pour la modification.';
        } else {

            try {
                // On récupère aussi le statut (via la table statuts) pour vérifier le droit de modification
                $stmtCheck = $pdo->prepare("
                    SELECT r.id, s.statut
                    FROM reclamations r
                    INNER JOIN statuts s ON r.statut_id = s.id
                    WHERE r.id = :id AND r.user_id = :user_id
                ");
                $stmtCheck->execute([
                    ':id'      => $reclamationId,
                    ':user_id' => $userId
                ]);
                $recRow = $stmtCheck->fetch();

                if ($recRow) {

                    // Modification autorisée seulement si le statut commence par 'En attente'
                    if (strpos($recRow['statut'], 'En attente') !== 0) {
                        $message = "La réclamation ne peut être modifiée que lorsqu'elle est en attente d’informations.";
                    } else {

                        $stmt = $pdo->prepare("
                            UPDATE reclamations
                            SET categorie_id = :categorie_id,
                                objet        = :objet,
                                description  = :description
                            WHERE id = :id AND user_id = :user_id
                        ");
                        $stmt->execute([
                            ':categorie_id' => $categorie_id,
                            ':objet'        => $objet,
                            ':description'  => $description,
                            ':id'           => $reclamationId,
                            ':user_id'      => $userId
                        ]);

                        // ================== NOUVELLES PIECES JOINTES (OPTIONNEL) ==================
                        if (!empty($_FILES['pieces_jointes']['name'][0])) {
                            $files = $_FILES['pieces_jointes'];
                            $finfo = new finfo(FILEINFO_MIME_TYPE);

                            // On réutilise les mêmes constantes que pour la création
                            global $ALLOWED_MIME_TYPES, $ALLOWED_EXTENSIONS;

                            for ($i = 0; $i < count($files['name']); $i++) {
                                $tmpName      = $files['tmp_name'][$i];
                                $originalName = basename($files['name'][$i]);
                                $size         = (int)$files['size'][$i];

                                // Taille max
                                if ($size > MAX_FILE_SIZE) {
                                    continue;
                                }

                                // Extension autorisée
                                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                                if (!in_array($extension, $ALLOWED_EXTENSIONS)) {
                                    continue;
                                }

                                // MIME type autorisé
                                $mimeType = $finfo->file($tmpName);
                                if (!in_array($mimeType, $ALLOWED_MIME_TYPES)) {
                                    continue;
                                }

                                // Nouveau nom de fichier
                                $newFileName = uniqid('pj_', true) . '.' . $extension;
                                $destination = UPLOAD_DIR . '/' . $newFileName;

                                if (move_uploaded_file($tmpName, $destination)) {
                                    $stmtPj = $pdo->prepare("
                                        INSERT INTO pieces_jointes (reclamation_id, chemin_fichier, type_mime, date_ajout)
                                        VALUES (:reclamation_id, :chemin_fichier, :type_mime, NOW())
                                    ");
                                    $stmtPj->execute([
                                        ':reclamation_id' => $reclamationId, // <-- important : id existant
                                        ':chemin_fichier' => 'uploads/reclamations/' . $newFileName,
                                        ':type_mime'      => $mimeType,
                                    ]);
                                }
                            }
                        }

                        $message = 'Réclamation modifiée avec succès.';

                    }
                } else {
                    $message = 'Réclamation introuvable ou non autorisée.';
                }

            } catch (PDOException $e) {
                $message = "Erreur lors de la modification : " . $e->getMessage();
            }
        }
    }
}

// ================== DONNÉES POUR AFFICHAGE ==================

// Catégories (nouveau nom de colonne : categorie)
$stmtCat = $pdo->query("SELECT id, categorie AS nom FROM categories ORDER BY categorie");
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// Toutes les réclamations (compteurs + pièces jointes)
// On joint aussi la table statuts pour récupérer le libellé
$sqlAll = "
    SELECT r.id,
           r.categorie_id,
           r.objet,
           r.description,
           r.date_soumission,
           s.statut AS statut,
           c.categorie AS categorie_nom
    FROM reclamations r
    INNER JOIN categories c ON r.categorie_id = c.id
    INNER JOIN statuts s ON r.statut_id = s.id
    WHERE r.user_id = :user_id
    ORDER BY r.date_soumission DESC
";
$stmtAll = $pdo->prepare($sqlAll);
$stmtAll->execute([':user_id' => $userId]);
$allReclamations = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

// Compteurs
$statutCounts = [
    'en_cours'   => 0,
    'en_attente' => 0,
    'acceptee'   => 0,
    'fermee'     => 0,
];

foreach ($allReclamations as $rec) {
    $s = $rec['statut'];
    if (strpos($s, 'En cours de traitement') === 0) {
        $statutCounts['en_cours']++;
    } elseif (strpos($s, 'En attente') === 0) {
        $statutCounts['en_attente']++;
    } elseif (strpos($s, 'Accept') === 0) {
        $statutCounts['acceptee']++;
    } elseif (strpos($s, 'Ferm') === 0) {
        $statutCounts['fermee']++;
    }
}

$encoursTotal = $statutCounts['en_cours'] + $statutCounts['en_attente'];
$closTotal    = $statutCounts['acceptee'] + $statutCounts['fermee'];

// Pièces jointes groupées par réclamation
$pjByRec = [];
if (!empty($allReclamations)) {
    $ids = array_column($allReclamations, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sqlPj = "
        SELECT id, reclamation_id, chemin_fichier, type_mime, date_ajout
        FROM pieces_jointes
        WHERE reclamation_id IN ($placeholders)
        ORDER BY date_ajout DESC
    ";
    $stmtPjAll = $pdo->prepare($sqlPj);
    $stmtPjAll->execute($ids);
    $allPj = $stmtPjAll->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allPj as $pj) {
        $pjByRec[$pj['reclamation_id']][] = $pj;
    }
}

// Commentaires groupés par réclamation
$commentsByRec = [];
if (!empty($allReclamations)) {
    $ids = array_column($allReclamations, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sqlComments = "
        SELECT 
            c.id,
            c.reclamation_id,
            c.user_id,
            c.message,
            c.date_commentaire,
            u.nom  AS auteur_nom,
            u.role AS auteur_role
        FROM commentaires c
        INNER JOIN users u ON c.user_id = u.id
        WHERE c.reclamation_id IN ($placeholders)
        ORDER BY c.date_commentaire ASC
    ";
    $stmtComments = $pdo->prepare($sqlComments);
    $stmtComments->execute($ids);
    $allComments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allComments as $com) {
        $rid = (int)$com['reclamation_id'];
        if (!isset($commentsByRec[$rid])) {
            $commentsByRec[$rid] = [];
        }
        $commentsByRec[$rid][] = $com;
    }
}



// Réclamations filtrées pour le tableau (vue encours / clos)
// Jointure avec statuts et catégories, nouvelle structure
$sqlFiltered = "
    SELECT r.id,
           r.categorie_id,
           r.objet,
           r.description,
           r.date_soumission,
           s.statut AS statut,
           c.categorie AS categorie_nom
    FROM reclamations r
    INNER JOIN categories c ON r.categorie_id = c.id
    INNER JOIN statuts s ON r.statut_id = s.id
    WHERE r.user_id = :user_id
";
$paramsFiltered = [':user_id' => $userId];

// filtre statut selon la vue
if ($view === 'clos') {
    $sqlFiltered .= " AND (s.statut LIKE 'Accept%' OR s.statut LIKE 'Ferm%')";
} else {
    if ($statutTab === 'en_attente') {
        $sqlFiltered .= " AND s.statut LIKE 'En attente%'";
    } else {
        $sqlFiltered .= " AND s.statut LIKE 'En cours de traitement%'";
    }
}

// filtre catégorie
if ($categorieFiltre > 0) {
    $sqlFiltered .= " AND r.categorie_id = :categorie_filtre";
    $paramsFiltered[':categorie_filtre'] = $categorieFiltre;
}

// filtre recherche
if ($searchTerm !== '') {
    $sqlFiltered .= " AND (";
    $paramsFiltered[':search'] = '%' . $searchTerm . '%';

    if ($searchField === 'numero') {
        $sqlFiltered .= " r.id LIKE :search ";
    } elseif ($searchField === 'objet') {
        $sqlFiltered .= " r.objet LIKE :search ";
    } elseif ($searchField === 'categorie') {
        $sqlFiltered .= " c.categorie LIKE :search ";
    }

    $sqlFiltered .= ")";
}

$sqlFiltered .= " ORDER BY r.date_soumission DESC";

$stmtFiltered = $pdo->prepare($sqlFiltered);
$stmtFiltered->execute($paramsFiltered);
$reclamations = $stmtFiltered->fetchAll(PDO::FETCH_ASSOC);

// Query string commun pour conserver recherche/filtre
$queryCommon = [
    'search_field'  => $searchField,
    'search_term'   => $searchTerm,
    'categorie_id'  => $categorieFiltre,
];
$qsCommon        = http_build_query($queryCommon);
$qsCommonPrefix  = $qsCommon ? '&' . $qsCommon : '';

// ================== HTML / BOOTSTRAP ==================
?>

        <style>
        body {
            background-color: #f5f7fb;
        }
        .card-counter {
            cursor: pointer;
            border-radius: 0.5rem;
            transition: box-shadow .15s ease, transform .15s ease;
        }
        .card-counter:hover {
            transform: translateY(-2px);
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
        }
        .card-counter.active {
            border: 2px solid #0d6efd;
        }
        .card-counter .number {
            font-size: 2.5rem;
            font-weight: 600;
        }
        .card-counter .label {
            font-size: 0.95rem;
            color: #6c757d;
        }
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            color: #495057;
        }
        .nav-tabs .nav-link.active {
            border-bottom-color: #0d6efd;
            color: #0d6efd;
            font-weight: 500;
        }
        .table thead th {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .filter-bar {
            background-color: #ffffff;
        }
    </style>

<div class="container-fluid py-4">

    <!-- Titre -->
    <div class="row mb-3">
        <div class="col-12">
            <h4 class="mb-0">Consultation de l’historique des réclamations</h4>
            <p class="text-muted mb-0">Consultez et suivez l'état de vos réclamations.</p>
        </div>
    </div>

    <!-- Message global -->
    <?php if ($message): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-info py-2 mb-0">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Barre de recherche globale AU-DESSUS des cartes -->
    <form method="get" action="">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
        <input type="hidden" name="status_tab" value="<?php echo htmlspecialchars($statutTab); ?>">
        <input type="hidden" name="categorie_id" value="<?php echo $categorieFiltre ?: ''; ?>">

        <div class="row mb-4">
            <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                <div class="flex-grow-1">
                    <div class="input-group w-100">
                        <span class="input-group-text">
                            <select name="search_field" class="form-select form-select-sm border-0 bg-transparent">
                                <option value="numero"   <?= ($searchField === 'numero')   ? 'selected' : ''; ?>>Numéro de réclamation</option>
                                <option value="objet"    <?= ($searchField === 'objet')    ? 'selected' : ''; ?>>Objet</option>
                                <option value="categorie"<?= ($searchField === 'categorie')? 'selected' : ''; ?>>Catégorie</option>
                            </select>
                        </span>
                        <input type="text"
                               name="search_term"
                               class="form-control"
                               placeholder="Rechercher des réclamations"
                               value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>

                <button type="button"
                        class="btn btn-primary ms-auto"
                        data-bs-toggle="modal"
                        data-bs-target="#modalNouvelleReclamation">
                    Nouvelle réclamation
                </button>
            </div>
        </div>
    </form>

    <!-- Cartes cliquables -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <a href="?view=encours&status_tab=<?php echo htmlspecialchars($statutTab); ?><?php echo $qsCommonPrefix; ?>"
               class="text-decoration-none text-reset">
                <div class="card card-counter shadow-sm border-0 <?php echo ($view === 'encours') ? 'active' : ''; ?>">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div class="number"><?php echo (int)$encoursTotal; ?></div>
                        <div class="label">Réclamations en cours</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-3">
            <a href="?view=clos&status_tab=<?php echo htmlspecialchars($statutTab); ?><?php echo $qsCommonPrefix; ?>"
               class="text-decoration-none text-reset">
                <div class="card card-counter shadow-sm border-0 <?php echo ($view === 'clos') ? 'active' : ''; ?>">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div class="number"><?php echo (int)$closTotal; ?></div>
                        <div class="label">Réclamations clos</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- FORM FILTRE CATEGORIE + TABLEAU -->
    <form method="get" action="">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
        <input type="hidden" name="status_tab" value="<?php echo htmlspecialchars($statutTab); ?>">
        <input type="hidden" name="search_field" value="<?php echo htmlspecialchars($searchField); ?>">
        <input type="hidden" name="search_term" value="<?php echo htmlspecialchars($searchTerm); ?>">

    <!-- Bloc principal -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">

                        <?php if ($view === 'encours'): ?>
                            <!-- Onglets de statut uniquement pour les réclamations en cours -->
                            <ul class="nav nav-tabs px-3 pt-3">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($statutTab === 'en_cours') ? 'active' : ''; ?>"
                                       href="?view=encours&status_tab=en_cours<?php echo $qsCommonPrefix; ?>">
                                        En cours de traitement
                                        <span class="badge bg-secondary ms-1">
                                            <?php echo (int)$statutCounts['en_cours']; ?>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo ($statutTab === 'en_attente') ? 'active' : ''; ?>"
                                       href="?view=encours&status_tab=en_attente<?php echo $qsCommonPrefix; ?>">
                                        En attente d’informations
                                        <span class="badge bg-secondary ms-1">
                                            <?php echo (int)$statutCounts['en_attente']; ?>
                                        </span>
                                    </a>
                                </li>
                            </ul>
                        <?php endif; ?>

                        <!-- Barre filtre catégories -->
                        <div class="filter-bar px-3 py-2 border-top">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="text-muted small">Filtrer par :</span>
                                <select name="categorie_id" class="form-select form-select-sm" style="max-width:260px;">
                                    <option value="">Toutes les catégories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo (int)$cat['id']; ?>"
                                            <?php echo ($categorieFiltre === (int)$cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-secondary ms-2">
                                    Appliquer
                                </button>
                            </div>
                        </div>

                        <!-- Tableau -->
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th class="text-nowrap">Numéro de réclamation</th>
                                    <th>Catégorie</th>
                                    <th>Objet de la réclamation</th>
                                    <?php if ($view === 'clos'): ?>
                                        <th>Statut</th>
                                    <?php endif; ?>
                                    <th class="text-nowrap">Ouvert le</th>
                                    <th class="text-end">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($reclamations)): ?>
                                    <tr>
                                        <td colspan="<?php echo ($view === 'clos') ? 6 : 5; ?>" class="text-center py-4 text-muted">
                                            Aucune réclamation trouvée.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reclamations as $rec): ?>
                                        <?php
                                        $recId       = (int)$rec['id'];
                                        $dateObj     = new DateTime($rec['date_soumission']);
                                        $categorie   = $rec['categorie_nom'];
                                        $statut      = $rec['statut'];
                                        $peutModifier = (strpos($statut, 'En attente') === 0);
                                        ?>
                                        <tr>
                                            <td class="text-nowrap"><?php echo $recId; ?></td>
                                            <td><?php echo htmlspecialchars($categorie); ?></td>
                                            <td><?php echo htmlspecialchars($rec['objet']); ?></td>
                                            <?php if ($view === 'clos'): ?>
                                                <td><?php echo htmlspecialchars($statut); ?></td>
                                            <?php endif; ?>
                                            <td class="text-nowrap"><?php echo $dateObj->format('d/m/Y'); ?></td>
                                            <td class="text-end">
                                                <?php if ($view === 'clos'): ?>
                                                    <!-- Vue Réclamations clos : seulement bouton Afficher -->
                                                    <button type="button"
                                                            class="btn btn-link btn-sm p-0"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalViewRec<?php echo $recId; ?>">
                                                        Afficher
                                                    </button>
                                                <?php else: ?>
                                                    <?php if ($peutModifier): ?>
                                                        <!-- En attente d’informations : seulement Modifier -->
                                                        <button type="button"
                                                                class="btn btn-warning btn-sm me-1"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalEditRec<?php echo $recId; ?>">
                                                            <i class="bi bi-pencil-fill"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- En cours de traitement : seulement Afficher -->
                                                        <button type="button"
                                                                class="btn btn-link btn-sm p-0"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalViewRec<?php echo $recId; ?>">
                                                            Afficher
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </form>
</div>

<!-- ============ MODAL NOUVELLE RECLAMATION ============ -->
<div class="modal fade" id="modalNouvelleReclamation" tabindex="-1"
     aria-labelledby="modalNouvelleReclamationLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNouvelleReclamationLabel">Nouvelle réclamation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Fermer"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="form_type" value="create">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Catégorie *</label>
                            <select name="categorie_id" class="form-select" required>
                                <option value="">-- Sélectionnez une catégorie --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Objet de la réclamation *</label>
                            <input type="text" name="objet" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description détaillée *</label>
                            <textarea name="description" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Pièces jointes (optionnel)</label>
                            <input type="file" name="pieces_jointes[]" class="form-control" multiple>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        Envoyer la réclamation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============ MODALS AFFICHAGE + (MODIF UNIQUEMENT EN ATTENTE) ============ -->
<?php if (!empty($reclamations)): ?>
    <?php foreach ($reclamations as $rec): ?>
        <?php
        $recId   = (int)$rec['id'];
        $dateObj = new DateTime($rec['date_soumission']);
        $pjList  = $pjByRec[$recId] ?? [];
        $statut  = $rec['statut'];
        $peutModifier = (strpos($statut, 'En attente') === 0);
        ?>
        <!-- Affichage -->
        <div class="modal fade" id="modalViewRec<?php echo $recId; ?>" tabindex="-1"
             aria-labelledby="modalViewRecLabel<?php echo $recId; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalViewRecLabel<?php echo $recId; ?>">
                            Réclamation n° <?php echo $recId; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Catégorie</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($rec['categorie_nom']); ?></dd>

                            <dt class="col-sm-4">Objet</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($rec['objet']); ?></dd>

                            <dt class="col-sm-4">Description</dt>
                            <dd class="col-sm-8">
                                <pre class="mb-0" style="white-space: pre-wrap;"><?php
                                    echo htmlspecialchars($rec['description']);
                                    ?></pre>
                            </dd>

                            <?php
                                $recId       = (int)$rec['id'];
                                $recComments = $commentsByRec[$recId] ?? [];
                                if (!empty($recComments)):
                            ?>
                                <dt class="col-sm-4">Commentaires</dt>
                                <dd class="col-sm-8">
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($recComments as $com): ?>
                                            <li class="mb-2">
                                                <strong>
                                                    <?php echo htmlspecialchars($com['auteur_nom']); ?>
                                                    (<?php echo htmlspecialchars($com['auteur_role']); ?>)
                                                </strong>
                                                <span class="text-muted small">
                                                    - <?php echo htmlspecialchars($com['date_commentaire']); ?>
                                                </span><br>
                                                <span>
                                                    <?php echo nl2br(htmlspecialchars($com['message'])); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </dd>
                            <?php endif; ?>


                            <dt class="col-sm-4">Statut</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($rec['statut']); ?></dd>


                            <dt class="col-sm-4">Ouvert le</dt>
                            <dd class="col-sm-8"><?php echo $dateObj->format('d/m/Y'); ?></dd>

                            <?php if (!empty($pjList)): ?>
                                <dt class="col-sm-4">Pièces jointes</dt>
                                <dd class="col-sm-8">
                                    <ul class="list-unstyled mb-0 small">
                                        <?php foreach ($pjList as $pj): ?>
                                            <li class="mb-1">
                                                <a href="<?php echo htmlspecialchars($pj['chemin_fichier']); ?>"
                                                   target="_blank">
                                                    <?php echo htmlspecialchars(basename($pj['chemin_fichier'])); ?>
                                                </a>
                                                <span class="text-muted">
                                                    (<?php echo htmlspecialchars($pj['type_mime']); ?>,
                                                     <?php echo htmlspecialchars($pj['date_ajout']); ?>)
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                                data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal modification UNIQUEMENT si En attente (et vue encours) -->
        <?php if ($view === 'encours' && $peutModifier): ?>
            <div class="modal fade" id="modalEditRec<?php echo $recId; ?>" tabindex="-1"
                 aria-labelledby="modalEditRecLabel<?php echo $recId; ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalEditRecLabel<?php echo $recId; ?>">
                                Modifier la réclamation n° <?php echo $recId; ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Fermer"></button>
                        </div>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="form_type" value="edit">
                            <input type="hidden" name="reclamation_id" value="<?php echo $recId; ?>">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Catégorie *</label>
                                        <select name="categorie_id" class="form-select" required>
                                            <option value="">-- Sélectionnez une catégorie --</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>"
                                                    <?php echo ($rec['categorie_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['nom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Objet de la réclamation *</label>
                                        <input type="text" name="objet" class="form-control" required
                                               value="<?php echo htmlspecialchars($rec['objet']); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Description détaillée *</label>
                                        <textarea name="description" class="form-control" rows="4"
                                                  required><?php
                                            echo htmlspecialchars($rec['description']);
                                            ?></textarea>
                                    </div>

                                    <?php if (!empty($pjList)): ?>
                                        <div class="col-12">
                                            <label class="form-label">Pièces jointes existantes</label>
                                            <ul class="list-unstyled small mb-2">
                                                <?php foreach ($pjList as $pj): ?>
                                                    <li class="mb-1">
                                                        <a href="<?php echo htmlspecialchars($pj['chemin_fichier']); ?>"
                                                           target="_blank">
                                                            <?php echo htmlspecialchars(basename($pj['chemin_fichier'])); ?>
                                                        </a>
                                                        <span class="text-muted">
                                                            (<?php echo htmlspecialchars($pj['type_mime']); ?>,
                                                             <?php echo htmlspecialchars($pj['date_ajout']); ?>)
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <div class="col-12">
                                        <label class="form-label">
                                            Ajouter de nouvelles pièces jointes (optionnel)
                                        </label>
                                        <input type="file" name="pieces_jointes[]" class="form-control" multiple>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-primary">
                                    Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php
// Si on a un open_rec_id dans l'URL ET qu'on est en GET : ouvrir la modale de MODIFICATION
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['open_rec_id'])):
    $openRecId = (int) $_GET['open_rec_id'];
    if ($openRecId > 0):
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('modalEditRec<?php echo $openRecId; ?>');

    if (modalEl) {
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
});
</script>
<?php
    endif;
endif;
?>
