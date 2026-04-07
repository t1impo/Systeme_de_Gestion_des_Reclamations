<?php 
session_start();
require ('../conf/connecte_bd.php');

// sécurité : si l'utilisateur n'est pas connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index/login_page.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];

// 1) Si on vient d'une notification : marquer le commentaire comme LU
if (isset($_GET['notif_comment_id'])) {
    $notifId = (int) $_GET['notif_comment_id'];

    if ($notifId > 0) {
        $sqlMark = "
            UPDATE commentaires c
            INNER JOIN reclamations r ON c.reclamation_id = r.id
            SET c.lu = 1
            WHERE c.id = :comment_id
              AND r.user_id = :user_id
        ";
        $stmtMark = $pdo->prepare($sqlMark);
        $stmtMark->execute([
            ':comment_id' => $notifId,
            ':user_id'    => $currentUserId
        ]);
    }
}

// 2) Charger les notifications pour la cloche
$sqlNotif = "
    SELECT 
        c.id           AS comment_id,
        c.reclamation_id,
        c.message,
        c.date_commentaire,
        r.objet,
        s.statut,
        u.nom  AS auteur_nom,
        u.role AS auteur_role
    FROM commentaires c
    INNER JOIN reclamations r ON c.reclamation_id = r.id
    INNER JOIN users u        ON c.user_id = u.id
    INNER JOIN statuts s      ON r.statut_id = s.id
    WHERE r.user_id = :user_id
      AND u.role <> 'Réclamant'        -- commentaire écrit par gestionnaire / admin
      AND s.statut LIKE 'En attente%'  -- seulement les réclamations En attente d’informations
      AND c.lu = 0                     -- non lus
    ORDER BY c.date_commentaire DESC
    LIMIT 10
";
$stmtNotif = $pdo->prepare($sqlNotif);
$stmtNotif->execute([':user_id' => $currentUserId]);
$notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
$notifCount = is_array($notifications) ? count($notifications) : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <?php include("../conf/head.php"); ?>

    <meta charset="UTF-8">
    <title>Espace Réclamant</title>
    <style>
        :root {
            --primary-teal: #fafafa;
            --hover-teal: #002a36;
        }

        body {
            background-color: #ffff;
            font-family: 'Segoe UI', Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
        }

        .navbar-custom {
            background-color: var(--primary-teal);
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            padding-top: 0.8rem;
            padding-bottom: 0.8rem;
        }

        .navbar-brand {
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .nav-btn-group .btn {
            font-weight: bold;
            border: none;
        }
        
        .nav-btn-group .btn:hover {
            background-color: #003f51;
        }
        .btn-notification {
            color: #003f51;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 50%;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
            background: transparent;
        }

        .btn-notification:hover, .btn-notification.show {
            background-color: #003f51;
            color: #fff;
            border-color: transparent;
        }

        .badge-dot {
            position: absolute;
            top: 10px;
            right: 12px;
            width: 8px;
            height: 8px;
            background-color: #ff5b5b;
            border-radius: 50%;
            border: 1px solid var(--primary-teal);
        }
        .cercle {
            width: 40px;       
            height: 40px;
            background-color: white;
            border: 1px solid #003f51; 
            border-radius: 50%;    
            display: flex;
            align-items: center;
            justify-content: center;
            color: #003f51;           
            font-size: 10px;
            font-weight: bold;

        }
        .cercle:hover {
             background-color: #003f51;
             color: white; 
        }

    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-light navbar-custom sticky-top">
        <div class="container">
            <form method="post" class="d-inline">
                <button type="submit" name="nouvelle_reclamation" class="btn p-0 border-0 bg-transparent">
                    <span class="navbar-brand">
                        <img src="..\image\fichier.png">
                        Espace Réclamant
                    </span>
                </button>
            </form>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <div class="d-flex align-items-center gap-3">
                    
                    <!-- Dropdown Notifications -->
                    <div class="dropdown">
                        <button class="btn btn-notification"
                                id="notifDropdown"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
                            <?php if ($notifCount > 0): ?>
                                <!-- Cloche pleine + point rouge -->
                                <i class="bi bi-bell-fill" style="font-size: 1.1rem;"></i>
                                <span class="badge-dot"></span> 
                            <?php else: ?>
                                <!-- Cloche vide -->
                                <i class="bi bi-bell" style="font-size: 1.1rem;"></i>
                            <?php endif; ?>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 notif-dropdown"
                            aria-labelledby="notifDropdown"
                            style="width: 320px;">
                            <li class="dropdown-header text-uppercase text-muted small fw-bold">
                                Notifications
                            </li>
                            <li><hr class="dropdown-divider"></li>

                            <?php if ($notifCount === 0): ?>
                                <li class="px-3 py-2 text-muted small">
                                    Aucune nouvelle notification.
                                </li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <li>
                                        <a class="dropdown-item py-2 d-flex align-items-start"
                                        href="?view=encours&status_tab=en_attente&open_rec_id=<?php 
                                                    echo (int)$notif['reclamation_id']; 
                                                ?>&notif_comment_id=<?php 
                                                    echo (int)$notif['comment_id']; 
                                                ?>">

                                            <div class="me-2 mt-1">
                                                <i class="bi bi-chat-left-text-fill"></i>
                                            </div>

                                            <!-- TOUT LE TEXTE DANS UNE COLONNE -->
                                            <div class="d-flex flex-column">
                                                <span class="fw-semibold small mb-1">
                                                    Réclamation n° <?php echo (int)$notif['reclamation_id']; ?>
                                                </span>

                                                <span class="small text-muted notif-comment mb-1">
                                                    <?php echo nl2br(htmlspecialchars($notif['message'])); ?>
                                                </span>

                                                <span class="small text-muted">
                                                    <?php echo htmlspecialchars($notif['date_commentaire']); ?>
                                                </span>
                                            </div>

                                        </a>
                                    </li>
                                <?php endforeach; ?>

                            <?php endif; ?>
                        </ul>
                    </div>



                    <div class="cercle"><?php echo  strtoupper(htmlspecialchars($_SESSION['user_nom'])[0])?></div>
                    <div class="vr" style="height: 35px; background: #003f51;"></div>

                    <div class="d-flex align-items-center nav-btn-group">
                       
                        <form action="../index/login_page.php">
                            <button type="submit" 
                                class="btn btn-outline-dark" 
                                name="Déconnecter">
                            <i class="bi bi-box-arrow-right me-1"></i>Déconnecter
                        </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-11">
                <!-- <div class="card main-card"> -->
                    <?php include("old_final_historique_reclamations.php")?>
                <!-- </div> -->
            </div>
        </div>
    </div>
</body>
</html>