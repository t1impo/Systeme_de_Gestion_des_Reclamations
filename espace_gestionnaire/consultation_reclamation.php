<?php 

    if(!isset($_SESSION['user_id'])){
        header('Location: ../index/login_page.php');
        exit;
    }
        
    // --- TRAITEMENT DU FORMULAIRE DE MISE À JOUR (POST) ---
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['button-enregister'])) {
        
        $id_rec = $_POST['reclamation_id'];
        $nouveau_statut = $_POST['nouveau_statut'];
        $commentaire_text = trim($_POST['commentaire']);
        $statut_actuel = $_POST['statut_actuel_hidden'];
        
        // A. Mise à jour du statut SEULEMENT SI il a changé
        if($nouveau_statut !== $statut_actuel) {
            // R.2: Le code de recherche de l'ID statut est fonctionnel, mais attention aux requêtes multiples.
            // Il est fortement recommandé d'utiliser des IDs numériques partout pour la fiabilité et les performances (comme suggéré dans ma réponse précédente).
            $id_statut = $pdo->prepare("SELECT id FROM statuts WHERE statut = :statut");
            $id_statut->execute([':statut' => $nouveau_statut]);
            $id_s = $id_statut->fetchColumn(); 

            $sql_update = "UPDATE reclamations SET statut_id = :id_st WHERE id = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([':id_st' => $id_s, ':id' => $id_rec]);
        }

        // B. Insertion du commentaire SEULEMENT SI non vide
        if (!empty($commentaire_text)) {
            // on précise les colonnes, y compris lu
            $sql_comment = "
                INSERT INTO commentaires
                    (reclamation_id, user_id, message, date_commentaire, lu)
                VALUES
                    (:id_rec, :user_id, :contenu, NOW(), 0)
            ";
            $stmt_comment = $pdo->prepare($sql_comment);
            $stmt_comment->execute([
                ':id_rec'  => $id_rec,
                ':user_id' => $_SESSION['user_id'],  // gestionnaire
                ':contenu' => $commentaire_text
            ]);
        }


        // R.3: Après l'enregistrement, on redirige pour revenir sur la page "Consultation"
        // Ceci est une bonne pratique (Post/Redirect/Get)
        header('Location: ?page=Consultation');
        exit;
    }

    // --- 1. Construction de la requête avec filtres dynamiques ---
    // $sql = "SELECT r.id, r.objet, r.description, s.statut, r.date_soumission, c.categorie 
    //         FROM reclamations r, categories c, statuts s
    //         WHERE r.categorie_id = c.id AND r.statut_id = s.id";
    $sql = "SELECT r.id, r.objet, r.description, s.statut, r.date_soumission, c.categorie
        FROM reclamations r
        JOIN categories c ON r.categorie_id = c.id
        JOIN statuts s ON r.statut_id = s.id
        JOIN gestionnaires g ON g.categorie_id = c.id
        WHERE g.user_id = :user_id";
    $params = [':user_id' => $_SESSION['user_id']];

    // Filtre par Statut
    if (!empty($_GET['statut'])) {
        $sql .= " AND s.statut = :statut";
        $params[':statut'] = $_GET['statut'];
    }

    // Filtre par Date
    if (!empty($_GET['date'])) {
        $sql .= " AND DATE(r.date_soumission) = :date";
        $params[':date'] = $_GET['date'];
    }

    // Tri par date décroissante
    $sql .= " ORDER BY r.date_soumission DESC";

    $req_data = $pdo->prepare($sql);
    $req_data->execute($params);
    $reclamations = $req_data->fetchAll();

    // afficher les statuts :
    $sql_statuts ="SELECT statut FROM statuts";
    $req_statuts = $pdo->prepare($sql_statuts);
    $req_statuts->execute();
    $status_list = $req_statuts->fetchAll();

?>
<style>
        body {
            background-color: #f5f7fb; /* même couleur que le dashboard */
        }
</style>
<div class="container mt-4">
    <h3 class="text mb-4">Consultation des réclamations</h3>
    <div class="d-flex justify-content-between align-items-center my-3">
        <form class="d-flex w-75 align-items-center" role="search" method="GET">
            
            <input type="hidden" name="page" value="Consultation">
            
            <select class="form-select me-2" name="statut" style="width: 200px;">
                 <option value="">Tous les statuts</option>
                 <?php foreach($status_list as $i) {?>
                  <option value="<?php echo $i['statut']?>" <?php if(isset($_GET['statut']) && $_GET['statut'] == $i['statut']) echo 'selected'; ?>><?php echo $i['statut']?></option>
                 <?php }?>
                
            </select>

            <input class="form-control me-2" type="date" name="date" style="width: 180px;" value="<?php if(isset($_GET['date'])) echo htmlspecialchars($_GET['date']); ?>">

            <button class="btn btn-outline-primary" type="submit">
                <i class="bi bi-filter"></i> Filtrer
            </button>

            <?php 
                // Pour la réinitialisation, on redirige vers ?page=Consultation et non juste ?
                if(!empty($_GET['statut']) || !empty($_GET['date'])): 
            ?>
                 <a href="?page=Consultation" class="btn btn-outline-secondary ms-2">Réinitialiser</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>id</th>
                    <th>Catégorie</th>
                    <th>Objet</th>
                    <th>Statut</th>
                    <th>Date soumission</th>
                    <th>Option</th>
                </tr>  
            </thead>
            <tbody>
                <?php foreach($reclamations as $row) {
                    $status_color = "bg-secondary";
                    if($row['statut'] === "En cours de traitement"){$status_color = "bg-warning text-dark";}
                    if($row['statut'] === "Acceptée"){$status_color = "bg-success";}
                    if($row['statut'] === "Fermée"){$status_color = "bg-danger";}
                    ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id'])?></td>
                    <td><?php echo htmlspecialchars($row['categorie'])?></td>
                    <td><?php echo htmlspecialchars($row['objet'])?></td>
                    <td><span class="badge status-badge <?php echo $status_color; ?>">
                        <?php echo htmlspecialchars($row['statut'])?>
                    </span>
                    </td>
                    <td><i class="bi bi-calendar3 me-1"></i>
                        <?php echo htmlspecialchars($row['date_soumission'])?></td>
                    <td>
        
                        <button type="submit" class="btn btn-light text-primary btn-action btn-sm shadow-sm border" data-bs-toggle="modal" 
                                        data-bs-target="#Modal<?php echo $row['id']; ?>">
                                <i class="bi bi-eye"></i>
                                Détails
                        </button>
                    </td>
                </tr>
                <div class="modal fade" id="Modal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="header-title mb-0" style="color: #003f51">
                                                <i class="bi bi-file-earmark-text me-2"></i>Réclamation #<?php echo htmlspecialchars($row['id']) ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>

                                        <div class="modal-body">
                                            <div class="row g-3">
                                                
                                                <div class="col-6">
                                                    <p class="text-muted small mb-1" style="color: #003f51">CATÉGORIE</p>
                                                    <p class="fw-bold"><?php echo htmlspecialchars($row['categorie']) ?></p>
                                                </div>
                                                <div class="col-6">
                                                    <p class="text-muted small mb-1" style="color: #003f51">DATE</p>
                                                    <p class="fw-bold"><?php echo htmlspecialchars($row['date_soumission']) ?></p>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <div class="p-3 bg-light rounded-3">
                                                        <p class="text-muted small mb-1" style="color: #003f51">OBJET</p>
                                                        <p class="fw-bold mb-2"><?php echo htmlspecialchars($row['objet']) ?></p>
                                                        <hr class="my-2">
                                                        <p class="text-muted small mb-1" style="color: #003f51">DESCRIPTION</p>
                                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($row['description'])) ?></p>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                <div class="p-3 bg-light rounded-3">
                                                    <p class="text-muted small mb-1" style="color: #003f51">Pièces jointes</p>

                                                    <?php
                                                        // Récupérer les pièces jointes de cette réclamation
                                                        $sqlPj = "SELECT chemin_fichier, type_mime, date_ajout
                                                                FROM pieces_jointes
                                                                WHERE reclamation_id = :id_rec
                                                                ORDER BY date_ajout DESC";
                                                        $stmtPj = $pdo->prepare($sqlPj);
                                                        $stmtPj->execute([':id_rec' => $row['id']]);
                                                        $pjList = $stmtPj->fetchAll(PDO::FETCH_ASSOC);
                                                    ?>

                                                    <?php if (!empty($pjList)): ?>
                                                        <ul class="list-unstyled mb-0 small">
                                                            <?php foreach ($pjList as $pj): ?>
                                                                <li class="mb-1 d-flex align-items-center">
                                                                    <i class="bi bi-paperclip me-1"></i>
                                                                        <a href="<?php echo '/PROJET_WEB_ver5/espace_reclament/' . htmlspecialchars($pj['chemin_fichier']); ?>"
                                                                        target="_blank">
                                                                            <?php echo htmlspecialchars(basename($pj['chemin_fichier'])); ?>
                                                                        </a>
                                                                    <span class="text-muted ms-1">
                                                                        (<?php echo htmlspecialchars($pj['type_mime']); ?>,
                                                                        <?php echo htmlspecialchars($pj['date_ajout']); ?>)
                                                                    </span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <p class="text-muted mb-0">Aucune pièce jointe pour cette réclamation.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                                <form method="post">
                                                    <input type="hidden" name="reclamation_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                                    <input type="hidden" name="statut_actuel_hidden" value="<?php echo htmlspecialchars($row['statut']); ?>">
                                                
                                                <div class="col-8">
                                                    <div class="p-3 bg-light rounded-3">
                                                        <p class="text-muted small mb-1" style="color: #003f51">Ajouter un Commentaire</p>
                                                        <textarea class="form-control" name="commentaire" placeholder="Écrire un commentaire..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="p-3 bg-light rounded-3 h-100 d-flex flex-column justify-content-between">
                                                        <div>
                                                            <div class="mb-3 text-center">
                                                                <span class="badge <?php echo $status_color ?> px-3 py-2 rounded-pill">
                                                                    Statut actuel : <?php echo htmlspecialchars($row['statut']) ?>
                                                                </span>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="text-muted small mb-1" style="color: #003f51">Changer le statut :</label>
                                                                <select class="form-select" name="nouveau_statut">
                                                                    <?php foreach($status_list as $st) { 
                                                                        $selected = ($st['statut'] === $row['statut']) ? 'selected' : '';
                                                                    ?>
                                                                    <option value="<?php echo htmlspecialchars($st['statut'])?>" <?php echo $selected; ?>>
                                                                        <?php echo htmlspecialchars($st['statut'])?>
                                                                    </option>
                                                                    <?php }?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                        <div class="modal-footer">
                                            <button type="submit" name="button-enregister" value="Envoyer" class="btn btn-primary" style="background: #003f51; border-color: #003f51;">Enregistrer les modifications</button>
                                        </div>
                                        </form>
                                        
                                </div>
                            </div>
                        </div>
                <?php }?>
            </tbody>
        </table>
        
        <?php if(count($reclamations) == 0): ?>
            <div class="alert alert-info text-center">Aucune réclamation trouvée pour ces critères.</div>
        <?php endif; ?>
        
    </div>
</div>