<?php
session_start();
require('../conf/connecte_bd.php'); 

$login_erreur = false ;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $adresse = $_POST["email"];
    $password = $_POST["mot_de_passe"];

    if($adresse === '' || $password === ''){

        echo "<h3>Veuillez remplir tous les champs.</h3>";

    } else {

        $requet = $pdo->prepare("SELECT * from users where email=:email");
        $requet->execute([
            ':email' => $adresse
        ]);

        $user = $requet->fetch();

        // utilisation de $user &&.... dans if car que email ne pas trouver $user==false
        if(
            $user &&
            $adresse == $user["email"] &&
            password_verify($password,$user['mot_de_passe'])
        ){

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_role'] = $user['role'];

            if($user['role'] === 'Réclamant'){

                header('Location: ../espace_reclament/espace_reclamation.php');
                exit;

            }
            elseif($user['role'] === 'admin') {

                header('Location: ../espace_admin/menu_admin.php');
                exit;

            }
            elseif($user['role'] === 'agent'){

                header('Location: ../espace_gestionnaire/menu_gestionnaire.php');
                exit;

            }
            else {

                echo "<h3>Vous n'avez pas accès à l'espace réclamant.</h3>";
            }

        } else {

            $login_erreur = true;
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <?php include("../conf/head.php"); ?>

    <title>Login</title>

    <style>

        body{
            background-color: #f4f7fa;
        }

        .login-btn{
            background: linear-gradient(135deg, #003f51, #0a6c86);
            color: white;
            border: none;
            padding: 12px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 12px;
            transition: 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 63, 81, 0.3);
        }

        .login-btn:hover{
            transform: translateY(-2px);
            background: linear-gradient(135deg, #0a6c86, #003f51);
            color: white;
        }

        .login-btn:active{
            transform: scale(0.98);
        }

        @media (max-width: 768px) {

            .image-col img {
                width: 60%;
                height: auto;
                margin: 0 auto 20px auto;
            }
        }

    </style>

</head>

<body>

<div class="container-fluid">

    <div class="row align-items-center" style="height: 100vh;">

        <!-- Image -->
        <div class="col-md-7 d-flex justify-content-center image-col">

            <img src="../image/image.png" class="img-fluid" alt="login">

        </div>

        <!-- Formulaire -->
        <div class="col-md-5 d-flex justify-content-center">

            <div style="width: 80%">

                <h1 style="color:#003f51; font-weight:bold;">
                    Connexion
                </h1>

                <form method="post" action="login_page.php">

                    <!-- Email -->
                    <label for="email" class="mt-2">
                        Adresse email
                    </label>

                    <input 
                        type="email"
                        class="form-control <?php if($login_erreur == true) echo 'is-invalid' ?>"
                        name="email"
                        id="email"
                        style="color:#003f51;"
                        placeholder="email"
                        required
                    >

                    <!-- Password -->
                    <label for="mot_de_passe" class="mt-3">
                        Mot de passe
                    </label>

                    <input 
                        type="password"
                        class="form-control <?php if($login_erreur == true) echo 'is-invalid' ?>"
                        name="mot_de_passe"
                        id="mot_de_passe"
                        style="color:#003f51;"
                        placeholder="mot de passe"
                        required
                    >

                    <!-- Erreur -->
                    <?php if($login_erreur === true) : ?>

                        <div class="invalid-feedback">
                            Email ou mot de passe incorrect
                        </div>

                    <?php endif; ?>

                    <!-- Button -->
                    <div class="d-grid gap-2">

                        <button 
                            type="submit"
                            class="btn mt-4 login-btn">

                            Se connecter

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

</body>
</html>