<?php
// Toujours démarrer la session une seule fois
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// S'assurer que la connexion PDO ($pdo) est disponible
require_once('../conf/connecte_bd.php');

// // Accès réservé à l'admin (adapter si besoin)
// if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
//     header('Location: ../index/login_page.php');
//     exit;
// }

$successMsg = '';
$errorMsg   = '';

// ================== TRAITEMENT DES FORMULAIRES ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type   = $_POST['type']   ?? '';
    $action = $_POST['action'] ?? '';

    // ----- GESTION DES CATEGORIES -----
    if ($type === 'categorie') {

        // Ajout
        if ($action === 'add') {
            $nom = trim($_POST['categorie'] ?? '');
            if ($nom === '') {
                $errorMsg = "Veuillez saisir un nom de catégorie.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO categories (categorie) VALUES (:nom)");
                    $stmt->execute([':nom' => $nom]);
                    $successMsg = "Catégorie ajoutée avec succès.";
                } catch (PDOException $e) {
                    $errorMsg = "Erreur lors de l'ajout de la catégorie : " . $e->getMessage();
                }
            }
        }

        // Modification
        elseif ($action === 'edit') {
            $id  = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $nom = trim($_POST['categorie'] ?? '');
            if ($id <= 0 || $nom === '') {
                $errorMsg = "Données invalides pour la modification de la catégorie.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE categories SET categorie = :nom WHERE id = :id");
                    $stmt->execute([':nom' => $nom, ':id' => $id]);
                    $successMsg = "Catégorie modifiée avec succès.";
                } catch (PDOException $e) {
                    $errorMsg = "Erreur lors de la modification de la catégorie : " . $e->getMessage();
                }
            }
        }

        // Suppression
        elseif ($action === 'delete') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) {
                $errorMsg = "Catégorie invalide pour la suppression.";
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $successMsg = "Catégorie supprimée avec succès.";
                } catch (PDOException $e) {
                    // Contrainte de clé étrangère possible
                    $errorMsg = "Impossible de supprimer cette catégorie (elle est peut-être utilisée dans des réclamations ou des gestionnaires).";
                }
            }
        }
    }

    // ----- GESTION DES STATUTS -----
    elseif ($type === 'statut') {

        // Ajout
        if ($action === 'add') {
            $nom = trim($_POST['statut'] ?? '');
            if ($nom === '') {
                $errorMsg = "Veuillez saisir un libellé de statut.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO statuts (statut) VALUES (:nom)");
                    $stmt->execute([':nom' => $nom]);
                    $successMsg = "Statut ajouté avec succès.";
                } catch (PDOException $e) {
                    $errorMsg = "Erreur lors de l'ajout du statut : " . $e->getMessage();
                }
            }
        }

        // Modification
        elseif ($action === 'edit') {
            $id  = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $nom = trim($_POST['statut'] ?? '');
            if ($id <= 0 || $nom === '') {
                $errorMsg = "Données invalides pour la modification du statut.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE statuts SET statut = :nom WHERE id = :id");
                    $stmt->execute([':nom' => $nom, ':id' => $id]);
                    $successMsg = "Statut modifié avec succès.";
                } catch (PDOException $e) {
                    $errorMsg = "Erreur lors de la modification du statut : " . $e->getMessage();
                }
            }
        }

        // Suppression
        elseif ($action === 'delete') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) {
                $errorMsg = "Statut invalide pour la suppression.";
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM statuts WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $successMsg = "Statut supprimé avec succès.";
                } catch (PDOException $e) {
                    // Dans ta BD, la suppression peut entraîner un ON DELETE CASCADE sur les réclamations
                    $errorMsg = "Impossible de supprimer ce statut (il est peut-être utilisé dans des réclamations).";
                }
            }
        }
    }
}

// ================== RECUPERATION DES DONNEES ==================

// Liste des catégories
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, categorie FROM categories ORDER BY id ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = "Erreur lors du chargement des catégories : " . $e->getMessage();
}

// Liste des statuts
$statuts = [];
try {
    $stmt = $pdo->query("SELECT id, statut FROM statuts ORDER BY id ASC");
    $statuts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = "Erreur lors du chargement des statuts : " . $e->getMessage();
}

// Enregistrement en cours d'édition (GET)
$editCat    = null;
$editStatut = null;

if (isset($_GET['edit_cat'])) {
    $id = (int) $_GET['edit_cat'];
    foreach ($categories as $c) {
        if ((int)$c['id'] === $id) {
            $editCat = $c;
            break;
        }
    }
}

if (isset($_GET['edit_statut'])) {
    $id = (int) $_GET['edit_statut'];
    foreach ($statuts as $s) {
        if ((int)$s['id'] === $id) {
            $editStatut = $s;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include("../conf/head.php"); ?>
    <title>Paramétrage - Catégories et statuts</title>
    <style>
        body {
            background-color: #f5f7fb; /* même couleur que le dashboard */
        }
    </style>
</head>
<body>

<div class="container py-4">
    <h3 class="mb-4">Paramétrage</h3>

    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($successMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($errorMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- GESTION DES CATEGORIES -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong>Gestion des catégories</strong>
                </div>
                <div class="card-body">
                    <!-- Formulaire ajout / modification catégorie -->
                    <?php if ($editCat): ?>
                        <h6>Modifier la catégorie #<?php echo (int)$editCat['id']; ?></h6>
                        <form method="post" class="row g-2 mb-3">
                            <input type="hidden" name="type" value="categorie">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?php echo (int)$editCat['id']; ?>">
                            <div class="col-12">
                                <label class="form-label">Nom de la catégorie</label>
                                <input type="text"
                                       name="categorie"
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($editCat['categorie']); ?>"
                                       required>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                                <a href="menu_admin.php" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                        <hr>
                    <?php else: ?>
                        <h6>Ajouter une catégorie</h6>
                        <form method="post" class="row g-2 mb-3">
                            <input type="hidden" name="type" value="categorie">
                            <input type="hidden" name="action" value="add">
                            <div class="col-12">
                                <label class="form-label">Nom de la catégorie</label>
                                <input type="text"
                                       name="categorie"
                                       class="form-control"
                                       required>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">Ajouter</button>
                            </div>
                        </form>
                        <hr>
                    <?php endif; ?>

                    <!-- Tableau des catégories -->
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th style="width: 10%;">ID</th>
                                <th>Catégorie</th>
                                <th style="width: 25%;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">
                                        Aucune catégorie enregistrée.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><?php echo (int)$cat['id']; ?></td>
                                        <td><?php echo htmlspecialchars($cat['categorie']); ?></td>
                                        <td class="text-center">
                                            <a href="menu_admin.php?edit_cat=<?php echo (int)$cat['id']; ?>"
                                               class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="type" value="categorie">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                                <button type="submit"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Supprimer cette catégorie ?');">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
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

        <!-- GESTION DES STATUTS -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong>Gestion des statuts</strong>
                </div>
                <div class="card-body">
                    <!-- Formulaire ajout / modification statut -->
                    <?php if ($editStatut): ?>
                        <h6>Modifier le statut #<?php echo (int)$editStatut['id']; ?></h6>
                        <form method="post" class="row g-2 mb-3">
                            <input type="hidden" name="type" value="statut">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?php echo (int)$editStatut['id']; ?>">
                            <div class="col-12">
                                <label class="form-label">Libellé du statut</label>
                                <input type="text"
                                       name="statut"
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($editStatut['statut']); ?>"
                                       required>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                                <a href="menu_admin.php" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                        <hr>
                    <?php else: ?>
                        <h6>Ajouter un statut</h6>
                        <form method="post" class="row g-2 mb-3">
                            <input type="hidden" name="type" value="statut">
                            <input type="hidden" name="action" value="add">
                            <div class="col-12">
                                <label class="form-label">Libellé du statut</label>
                                <input type="text"
                                       name="statut"
                                       class="form-control"
                                       required>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">Ajouter</button>
                            </div>
                        </form>
                        <hr>
                    <?php endif; ?>

                    <!-- Tableau des statuts -->
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th style="width: 10%;">ID</th>
                                <th>Statut</th>
                                <th style="width: 25%;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($statuts)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">
                                        Aucun statut enregistré.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($statuts as $st): ?>
                                    <tr>
                                        <td><?php echo (int)$st['id']; ?></td>
                                        <td><?php echo htmlspecialchars($st['statut']); ?></td>
                                        <td class="text-center">
                                            <a href="menu_admin.php?edit_statut=<?php echo (int)$st['id']; ?>"
                                               class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="type" value="statut">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$st['id']; ?>">
                                                <button type="submit"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Supprimer ce statut ?');">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
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

</div>

</body>
</html>
