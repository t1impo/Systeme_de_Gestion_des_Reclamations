<?php 
    // CORRECTION ICI : On active le tampon de sortie pour éviter l'erreur "headers already sent"
    ob_start();

    // Démarrage de session (doit être la première chose)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require ('../conf/connecte_bd.php');
    
    // R.1: On récupère la page active depuis l'URL (GET), ou 'Dashboard' par défaut
    $page_active = isset($_GET['page']) ? $_GET['page'] : 'Dashboard'; 
?>
<!DOCTYPE html>
<html>
<head>
    <?php 
      include("../conf/head.php"); 
    ?>
    <meta charset="UTF-8">
    <title>Espace Gestionnaire</title>
    <link rel="stylesheet" href="../conf/file.css">
    <style>
        /* R.2: J'ai laissé votre CSS car il est correct */
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
            /* R.3: Ajout du style pour le bouton actif si navigation par <a> */
            color: #003f51; /* Couleur par défaut */
            background-color: transparent;
        }
        
        .nav-btn-group .btn:hover,
        .nav-btn-group .btn.active { /* 'active' pour indiquer la page courante */
            background-color: #003f51;
            color: #fff;
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
            
            <a href="?page=Dashboard" class="btn p-0 border-0 bg-transparent">
                <span class="navbar-brand">
                    <img src="..\image\maintenance.png">
                    Espace Gestionnaire
                </span>
            </a>
            

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <div class="d-flex align-items-center gap-3">
                    
                    <div class="d-flex nav-btn-group">
                         <a href="?page=Dashboard" 
                                 class="btn btn-outline-dark <?php echo ($page_active == 'Dashboard') ? 'active' : ''; ?>">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                         <a href="?page=Consultation" 
                                 class="btn btn-outline-dark <?php echo ($page_active == 'Consultation') ? 'active' : ''; ?>">
                            <i class="bi bi-eye"></i> Consultation
                        </a>
                    </div>
                    
                    <div class="cercle"><?php echo isset($_SESSION['user_nom']) ? strtoupper(htmlspecialchars($_SESSION['user_nom'])[0]) : 'U'; ?></div>
                    <div class="vr" style="height: 35px; background: #003f51;"></div>

                    <div class="d-flex align-items-center nav-btn-group">
                        
                        <a href="../index/login_page.php" 
                            class="btn btn-outline-dark">
                            <i class="bi bi-box-arrow-right me-1"></i>Déconnecter
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                     <div id="contenu-dynamique">

                         <?php
                             // R.5: Inclusion basée sur le paramètre GET
                             if ($page_active === 'Consultation') {
                                     include("consultation_reclamation.php");
                             }
                             else {
                                 // Page par défaut (Dashboard)
                                 include("dashboard.php");
                             }
                         ?>
                     </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php 
// Fin du tampon de sortie
ob_end_flush(); 
?>