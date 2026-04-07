<?php 
 
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once('../conf/connecte_bd.php');

    // Démarrage de la session et connexion à la base de données
    // session_start();
    // require("../conf/connecte_bd.php"); // Assurez-vous que $pdo est défini ici

    // // Redirection si l'utilisateur n'est pas connecté
    // if(!isset($_SESSION["user_id"])){
    //     header('Location: ../index/login_page.php');
    //     exit;
    // }

    $message = ""; // Variable pour stocker les messages d'alerte

    // --- TRAITEMENT DU FORMULAIRE (POST) pour AJOUT, SUPPRESSION, MODIFICATION ---
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
        
        // 1. AJOUTER
        if($_POST['action'] === "Enregistrer"){
            if(!empty($_POST['nom']) && !empty($_POST['email']) && !empty($_POST['role']) && $_POST['mot_de_passe'] === $_POST['V_mot_de_passe']){
                
                // Préparer les données utilisateur
                $nom = $_POST['nom'];
                $email = $_POST['email'];
                $role = $_POST['role'];
                $mot_de_passe_hash = password_hash($_POST['mot_de_passe'], PASSWORD_BCRYPT);
                $agent_categorie = $_POST['agent_categorie'] ?? null;

                // Insertion de l'utilisateur
                $sql_insert = "INSERT INTO users (nom, email, role, mot_de_passe) VALUES (:nom, :email, :role, :mot_de_passe)"; 
                $req = $pdo->prepare($sql_insert);
                $req->execute([
                    ':nom' => $nom,
                    ':email' => $email,
                    ':role' => $role,
                    ':mot_de_passe' => $mot_de_passe_hash
                ]);

                // Si l'utilisateur est un agent, ajouter à la table gestionnaires
                if(!empty($agent_categorie) && $role === 'agent'){
                    $id_user = $pdo->lastInsertId(); // Récupérer l'ID inséré

                    $sql_gestionnaire = 'INSERT INTO gestionnaires(user_id,categorie_id) VALUES(:user_id,:categorie_id)';
                    $req_G = $pdo->prepare($sql_gestionnaire);
                    $req_G->execute([':user_id' => $id_user , 'categorie_id' => $agent_categorie]);
                }
                $message = "<div class='alert alert-success text-center'>Utilisateur ajouté avec succès !</div>";
            } else { 
                $message = "<div class='alert alert-danger text-center'>Erreur lors de l'ajout. Veuillez vérifier les champs et les mots de passe.</div>";
            }
        }

        // 2. SUPPRIMER
        if($_POST['action'] === "supprimer"){
            if(isset($_POST['user_id_sup'])){
                $sql_supprimer = "DELETE FROM users WHERE id = :id";
                $req1 = $pdo->prepare($sql_supprimer);
                $req1->execute([':id' => $_POST['user_id_sup']]);
                $message = "<div class='alert alert-warning text-center'>Utilisateur supprimé.</div>";
            }
        }

        // 3. MODIFIER
        if($_POST['action'] === "modifier"){
            $user_id_modif = $_POST['user_id_modif'] ?? null;
            $role_modif = $_POST['role'] ?? null;
            $agent_categorie_modifier = $_POST['agent_categorie_modifier'] ?? null;

            if ($user_id_modif && $role_modif) {
                
                // === CORRECTION DE LA LOGIQUE DE MODIFICATION DE LA CATÉGORIE ===
                if($role_modif === 'agent'){
                    if (!empty($agent_categorie_modifier)) {
                        
                        // 1. Vérifier si l'entrée gestionnaire existe déjà
                        $sql_check = 'SELECT COUNT(*) FROM gestionnaires WHERE user_id = :user_id';
                        $req_check = $pdo->prepare($sql_check);
                        $req_check->execute([':user_id' => $user_id_modif]);
                        $exists = $req_check->fetchColumn();

                        if ($exists > 0) {
                            // Mettre à jour la catégorie pour l'agent existant
                            $sql_agent = 'UPDATE gestionnaires SET categorie_id = :id_categorie WHERE user_id = :user_id';
                            $requet = $pdo->prepare($sql_agent);
                            $requet->execute([':id_categorie'=>$agent_categorie_modifier, ':user_id' => $user_id_modif]);
                        } else {
                            // Insérer une nouvelle ligne (si le rôle a été changé en "agent" maintenant)
                            $sql_gestionnaire = 'INSERT INTO gestionnaires(user_id,categorie_id) VALUES(:user_id,:categorie_id)';
                            $req_G = $pdo->prepare($sql_gestionnaire);
                            $req_G->execute([':user_id' => $user_id_modif , ':categorie_id' => $agent_categorie_modifier]);
                        }
                    }
                } else {
                    // Optionnel : Si l'utilisateur n'est plus "agent", supprimer son entrée dans gestionnaires
                    $sql_delete_gestionnaire = 'DELETE FROM gestionnaires WHERE user_id = :user_id';
                    $req_delete_G = $pdo->prepare($sql_delete_gestionnaire);
                    $req_delete_G->execute([':user_id' => $user_id_modif]);
                }
                // ===================================================================

                // Mise à jour des informations utilisateur dans la table 'users'
                if (!empty($_POST['mot_de_passe'])) {
                    // Si le champ mot de passe est rempli, on le met à jour
                    $sql_update = 'UPDATE users SET nom=:nom, email=:email, role=:role, mot_de_passe=:mot_de_passe WHERE id=:id';
                    $params = [
                        ':nom' => $_POST['nom'],
                        ':email' => $_POST['email'],
                        ':role' => $role_modif,
                        ':mot_de_passe' => password_hash($_POST['mot_de_passe'], PASSWORD_BCRYPT),
                        ':id' => $user_id_modif
                    ];
                } else {
                    // Sinon, on met à jour sans changer le mot de passe
                    $sql_update = 'UPDATE users SET nom=:nom, email=:email, role=:role WHERE id=:id';
                    $params = [
                        ':nom' => $_POST['nom'],
                        ':email' => $_POST['email'],
                        ':role' => $role_modif,
                        ':id' => $user_id_modif
                    ];
                }
                
                $req_update = $pdo->prepare($sql_update);
                $req_update->execute($params);
                $message = "<div class='alert alert-info text-center'>Utilisateur modifié avec succès.</div>";
            }
        }
    }

    // --- LOGIQUE DE RECHERCHE (GET) ---
    $search_query = "";
    $search_column = "nom"; // Colonne par défaut pour la recherche
    $where_clause = "";
    $params = [];
    $allowed_columns = ['id', 'nom', 'email', 'role'];

    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $search_query = trim($_GET['search']);
        $search_column = isset($_GET['column']) && in_array($_GET['column'], $allowed_columns) ? $_GET['column'] : 'nom';

        // Construction de la clause WHERE avec LIKE
        $where_clause = " WHERE {$search_column} LIKE :search_term";
        $params[':search_term'] = '%' . $search_query . '%';
    }

    // --- CHARGEMENT DES DONNEES (avec ou sans filtre) ---
    $sql_affiche = "SELECT * FROM users {$where_clause} ORDER BY id DESC";
    $affiche = $pdo->prepare($sql_affiche);
    $affiche->execute($params);
    $users = $affiche->fetchAll(PDO::FETCH_ASSOC);

    // Définir la colonne par défaut pour le formulaire (pour qu'elle soit "sticky")
    $current_column = isset($_GET['column']) && in_array($_GET['column'], $allowed_columns) ? $_GET['column'] : 'nom';

    //recuperer les categorie :
    $sql_categorie = "SELECT id ,categorie FROM categories";
    $a = $pdo->prepare($sql_categorie);
    $a->execute();
    $categorie = $a->fetchAll(PDO::FETCH_ASSOC); // Fetch en mode associatif pour plus de clarté
?>

<!DOCTYPE html>
<html>
    <head>
        <?php include("../conf/head.php"); ?>
        <title>Gestion Utilisateurs</title>
        <link rel="stylesheet" href="../conf/file.css">
        <style>
                body {
                    background-color: #f5f7fb; /* même couleur que le dashboard */
                }
        </style>
    </head>
    <body>
        <div class="container mt-4">
            
            <h3 class="text mb-4">Gestion des Utilisateurs</h3>
            
            <?php if(!empty($message)) echo $message; ?>

            <div class="d-flex justify-content-between align-items-center my-3">
                
                <form class="d-flex w-50" role="search" method="GET">
                    <select class="form-select me-2" name="column" style="width: 150px;">
                        <option value="id" <?= ($current_column === 'id') ? 'selected' : '' ?>>ID</option>
                        <option value="nom" <?= ($current_column === 'nom') ? 'selected' : '' ?>>Nom</option>
                        <option value="email" <?= ($current_column === 'email') ? 'selected' : '' ?>>Email</option>
                        <option value="role" <?= ($current_column === 'role') ? 'selected' : '' ?>>Rôle</option>
                    </select>
                    <input class="form-control me-2" type="search" placeholder="Recherche" aria-label="Recherche" name="search" value="<?= htmlspecialchars($search_query) ?>"/>
                    <button class="btn btn-outline-success" type="submit">Rechercher</button>
                    <?php if (!empty($search_query)): ?>
                        <a href="?" class="btn btn-outline-secondary ms-2">Annuler</a>
                    <?php endif; ?>
                </form>

                <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-person-plus"></i> Ajouter un utilisateur
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th >ID</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach($users as $row): ?>
                            
                                <?php 
                                    // LOGIQUE POUR LA CATÉGORIE ACTUELLE DE L'AGENT
                                    $current_category_id = null;
                                    if ($row['role'] == 'agent') {
                                        $sql_current_cat = 'SELECT categorie_id FROM gestionnaires WHERE user_id = :user_id LIMIT 1';
                                        $req_current_cat = $pdo->prepare($sql_current_cat);
                                        $req_current_cat->execute([':user_id' => $row['id']]);
                                        $cat_data = $req_current_cat->fetch(PDO::FETCH_ASSOC);
                                        if ($cat_data) {
                                            $current_category_id = $cat_data['categorie_id'];
                                        }
                                    }
                                ?>
                            
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['nom']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['role']) ?></td>
                                <td style="min-width: 180px;">
                                    <button type="button" class="btn btn-warning btn-sm me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editModal<?= $row['id'] ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>

                                    <form method="post" style="display:inline-block;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                        <input type="hidden" name="user_id_sup" value="<?= $row['id'] ?>">
                                        <button type="submit" name="action" value="supprimer" class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header bg-warning">
                                            <h5 class="modal-title">Modifier l'utilisateur</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="user_id_modif" value="<?= $row['id'] ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Nom</label>
                                                    <input class="form-control" type="text" name="nom" value="<?= htmlspecialchars($row['nom']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Email</label>
                                                    <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Rôle</label>
                                                    <select class="form-select" name="role">
                                                        <option value="admin" <?= $row['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                                        <option value="Réclamant" <?= $row['role'] == 'Réclamant' ? 'selected' : '' ?>>Réclamant</option>
                                                        <option value="agent" <?= $row['role'] == 'agent' ? 'selected' : '' ?>>Agent</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Catégorie d'Agent</label>
                                                    <select class="form-select" name="agent_categorie_modifier">
                                                            <?php foreach($categorie as $c){?>
                                                            <option value="<?= htmlspecialchars($c['id']) ?>"
                                                                <?= ($current_category_id == $c['id']) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($c['categorie']) ?>
                                                            </option>
                                                            <?php }?>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Nouveau mot de passe (laisser vide si inchangé)</label>
                                                    <input class="form-control" type="password" name="mot_de_passe" >
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="action" value="modifier" class="btn btn-primary" style="background: green;">Enregistrer les modifications</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">
                                    Aucun utilisateur trouvé.
                                    <?php if (!empty($search_query)): ?>
                                        <a href="users_add.php" class="d-block mt-2">Afficher tous les utilisateurs</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header" style="background: #003f51; color: white;">
                        <h5 class="modal-title">Ajouter un utilisateur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nom</label>
                                <input class="form-control" type="text" name="nom" placeholder="nom" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input class="form-control" type="email" name="email" placeholder="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rôle</label>
                                <select class="form-select" name="role" required>
                                    <option value="admin">Admin</option>
                                    <option value="Réclamant">Réclamant</option>
                                    <option value="agent">Agent</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Catégorie d'Agent (Pour le rôle Agent)</label>
                                <select class="form-select" name="agent_categorie">
                                    <?php foreach($categorie as $c){?>
                                    <option value="<?php echo htmlspecialchars($c['id'])?>"><?php echo htmlspecialchars($c['categorie'])?></option>
                                    <?php }?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mot de passe</label>
                                <input class="form-control" type="password" name="mot_de_passe" placeholder="mot_de_passe" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirmer mot de passe</label>
                                <input class="form-control" type="password" name="V_mot_de_passe" placeholder="mot_de_passe" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="action" value="Enregistrer" class="btn btn-secondary" style="background: green;">Ajouter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

     
    </body>
</html>