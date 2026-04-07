<?php 
    // session_start();
    // require("../conf/connecte_bd.php");

    // if(!isset($_SESSION["user_id"])){
    //     header('Location: ../index/login_page.php');
    //     exit;
    // }

    // 1. Préparation de la requête de base
    $sql = "SELECT r.id, r.objet, r.description, s.statut, r.date_soumission, c.categorie 
             FROM reclamations r, categories c, statuts s
             WHERE r.categorie_id = c.id AND r.statut_id = s.id";

    $params = [];

    // 2. Logique de Recherche (Si le formulaire est envoyé)
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_term = "%" . $_GET['search'] . "%";
        $column = $_GET['column'];

        // On adapte la requête selon la colonne choisie
        if ($column == "id") {
            $sql .= " AND r.id LIKE ?";
        } elseif ($column == "statut") {
            $sql .= " AND s.statut LIKE ?";
        } elseif ($column == "catigorie") {
            $sql .= " AND c.categorie LIKE ?"; // On cherche dans le nom de la catégorie
        }
        
        $params[] = $search_term;
    }

    // 3. Exécution
    $req_data = $pdo->prepare($sql);
    $req_data->execute($params);
    $reclamation = $req_data->fetchAll(PDO::FETCH_ASSOC);
?>
<html>
    <head>
        <?php include('../conf/head.php') ?> 
        <title>admin_control</title>
        <style>
            body {
                background-color: #f5f7fb; /* même couleur que le dashboard */
            }
        </style>
        <link rel="stylesheet" href="../conf/file.css">
    </head>
    <body>
        <div class="container mt-4">
            <h3 class="text mb-4">Consultation des réclamations</h3>
            <div class="d-flex justify-content-between align-items-center my-3">
                <form class="d-flex w-50" role="search" method="GET">
                       <select class="form-select me-2" name="column" style="width: 180px;">
                             <option value="id" <?php if(isset($_GET['column']) && $_GET['column'] == 'id') echo 'selected'; ?>>Numéro</option>
                             <option value="statut" <?php if(isset($_GET['column']) && $_GET['column'] == 'statut') echo 'selected'; ?>>Statut</option>
                             <option value="catigorie" <?php if(isset($_GET['column']) && $_GET['column'] == 'catigorie') echo 'selected'; ?>>Catégorie</option>
                       </select>
                       <input class="form-control" type="search" placeholder="Recherche" aria-label="Recherche" name="search" value="<?php if(isset($_GET['search'])) echo htmlspecialchars($_GET['search']); ?>">
                       <button class="btn btn-outline-success ms-2" type="submit">Rechercher</button>
                       <?php if(isset($_GET['search'])): ?>
                           <a href="?" class="btn btn-outline-danger ms-2">Annuler</a>
                       <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light"> 
                            <tr>
                                <th>Numéro</th>
                                <th>Catégorie</th>
                                <th>Objet</th>
                                <th>Statut</th>
                                <th>Date soumission</th>
                                <th>Option</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reclamation as $row) { 
                                $status_color = "bg-secondary";
                                if($row['statut'] === "En cours de traitement"){$status_color = "bg-warning text-dark";}
                                if($row['statut'] === "Acceptée"){$status_color = "bg-success";}
                                if($row['statut'] === "Fermée"){$status_color = "bg-danger";}
                                ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']) ?></td>
                                <td><?php echo htmlspecialchars($row['categorie'])?></td>
                                <td><?php echo htmlspecialchars($row['objet'])?></td>
                                <td>
                                    <span class="badge status-badge <?php echo $status_color; ?>">
                                                <?php echo htmlspecialchars($row['statut']) ?>
                                    </span>
                                </td>
                                <td><i class="bi bi-calendar3 me-1"></i>
                                    <?php echo htmlspecialchars($row['date_soumission'])?></td>
                                <td>
                                    <button type="button" class="btn btn-light text-primary btn-action btn-sm shadow-sm border" data-bs-toggle="modal" data-bs-target="#Modal<?php echo $row['id']; ?>">
                                        <i class="bi bi-eye"></i> Détails
                                    </button>
                                </td>
                            </tr>

                            <div class="modal fade" id="Modal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title" style="color: #003f51">Détail Réclamation #<?php echo $row['id']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" ></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                    <div class="col-6">
                                                        <p class="text-muted small mb-1" style="color: #003f51" >CATEGORIE</p>
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
                                                    <div class="col-12 text-end mt-3">
                                                        <span class="badge <?php echo $status_color; ?> px-3 py-2 rounded-pill">
                                                            Statut actuel : <?php echo htmlspecialchars($row['statut']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                        </tbody>
                    </table>
            </div>
        </div>
    </body>
</html>