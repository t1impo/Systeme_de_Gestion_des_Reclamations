<?php 

session_start();
require ('../conf/connecte_bd.php');

// Sécurité : accès réservé à l'admin
if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index/login_page.php');
    exit;
}

// 1) Valeur par défaut ou valeur mémorisée en session
//    (comme ça, si on vient d'un formulaire de users_add.php ou admin_control.php,
//     on reste sur la même page)
$page_active = $_SESSION['admin_page_active'] ?? 'Paramétrage';

// 2) Clic sur les boutons du menu (POST)
if (isset($_POST['Consultation'])) {
    $page_active = 'Consultation';
} elseif (isset($_POST['Paramétrage'])) {
    $page_active = 'Paramétrage';
} elseif (isset($_POST['utilisateurs'])) {
    $page_active = 'utilisateurs';
}

// 3) Formulaires venant de parametrage.php (ajout / modif / suppression)
if (isset($_POST['type']) && in_array($_POST['type'], ['categorie', 'statut'], true)) {
    $page_active = 'Paramétrage';
}

// 4) Liens "Modifier" depuis parametrage.php (GET)
if (isset($_GET['edit_cat']) || isset($_GET['edit_statut'])) {
    $page_active = 'Paramétrage';
}

// 5) On mémorise la page active pour la prochaine requête
$_SESSION['admin_page_active'] = $page_active;

?>

<!DOCTYPE html>
<html>
<head>
    <?php 
     include("../conf/head.php"); 
    ?>
    <meta charset="UTF-8">
    <title>Espace Administrateur</title>
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

        /* Navbar Styling */
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

        /* Buttons Navigation */
        .nav-btn-group .btn {
            font-weight: bold;
            border: none;
        }
        
        .nav-btn-group .btn:hover {
            background-color: #003f51;
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
            <!-- <form method="post" class="d-inline"> -->
                <button type="submit" name="nouvelle_reclamation" class="btn p-0 border-0 bg-transparent">
                    <span class="navbar-brand">
                        <img src="..\image\shield-lock-fill.png">
                        Espace Administrateur
                    </span>
                </button>
                
            <!-- </form> -->

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <div class="d-flex align-items-center gap-3">
                    
                    <!-- Groupe de boutons de navigation-->
                    <form method="post" class="d-flex nav-btn-group">
                        <button type="submit" 
                                class="btn btn-outline-dark" 
                                name="Consultation">
                            <i class="bi bi-eye me-1"></i> Consultation
                        </button>
                        <button type="submit" 
                                class="btn btn-outline-dark" 
                                name="utilisateurs">
                            <i class="bi bi-people me-1"></i> Utilisateurs
                        </button>
                         <button type="submit" 
                                class="btn btn-outline-dark" 
                                name="Paramétrage">
                            <i class="bi bi-gear me-1"></i> Paramétrage
                        </button>
                    </form>
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
    
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                    <!-- Zone d'inclusion du contenu -->
                    <div id="contenu-dynamique">
                        
                        <?php
                             if ($page_active === 'Paramétrage') {
                                include("parametrage.php");
                            } elseif ($page_active === 'Consultation') {
                                include("admin_control.php");
                            } elseif ($page_active === 'utilisateurs') {
                                include("users_add.php");
                            }
                        ?>


                    </div>
            </div>
        </div>
    </div>
</body>
</html>