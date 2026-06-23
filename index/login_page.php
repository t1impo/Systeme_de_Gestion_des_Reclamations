<?php
session_start();
require('../conf/connecte_bd.php'); 
$login_erreur = false ;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $adresse = $_POST["email"] ;
        $password = $_POST["mot_de_passe"];
        if($adresse === '' || $password === ''){
            echo "<h3>Veuillez remplir tous les champs.</h3> " ;
        }
        else {
           $requet = $pdo->prepare("SELECT * from users where email=:email");
           $requet->execute([':email' => $adresse]);
           $user = $requet->fetch();
           //utilisation de $user &&.... dans if car que email ne pas trouver $user==false donc $adresse == $user["email"] alor incorrrect 
           if($user && $adresse == $user["email"] && password_verify($password,$user['mot_de_passe'])){
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nom'] = $user['nom'];
                    $_SESSION['user_role'] = $user['role'];
                  if($user['role'] === 'Réclamant'){
                        header('Location: ..\espace_reclament\espace_reclamation.php');
                        exit;
                  }
                  elseif($user['role'] === 'admin') {
                        header('Location: ..\espace_admin\menu_admin.php');
                        exit;
                    
                  }
                  elseif($user['role'] === 'agent'){
                        header('Location: ..\espace_gestionnaire\menu_gestionnaire.php');
                        exit;
                  }
                  else {
                     echo "<h3>Vous n'avez pas accès à l'espace réclamant.</h3>";
                  }
           }
           else {
              $login_erreur = true ;
             
           }
        }

}
?>
<!DOCTYPE html>
<html>
<head>
    <?php include("../conf/head.php") ;?>
    <title>Login</title>
    <style>
    @media (max-width: 768px) {
        .image-col img {
            width: 60%;      /* réduit la largeur à 80% */
            height: auto;    /* garde les proportions */
            margin: 0 auto 20px auto; /* centre l'image */
        }
    }
</style>
</head>
<body>
<div class="container-fluid">
        <div class="row align-items-center" style="height: 100vh;">
              <div class="col-md-7 d-flex justify-content-center image-col">
                
            <img src="../image/11235921_11104.jpg" class="img-fluid" alt="login">
        </div>
        <div class="col-md-5 d-flex justify-content-center">
            <div style="width: 80%">
                <h1>Connexion</h1>

                <form method="post" action="login_page.php">
                    <label for="email">Adresse email</label>
                    <input type="email" class="form-control <?php if($login_erreur == true) echo 'is-invalid' ?>" name="email" id="email" 
                            style="color:#003f51;" placeholder="email"
                            required>

                    <label for="mot_de_passe" class="mt-2">Mot de passe</label>
                    <input type="password" class="form-control <?php if($login_erreur == true) echo 'is-invalid' ?>" name="mot_de_passe" id="mot_de_passe" style="color:#003f51;" placeholder="mot de passe" required>

                    <?php if($login_erreur === true) : ?>
                    <div class="invalid-feedback">
                          Email ou mot de passe incorrect
                    </div>
                <?php endif;?>
                <div class="d-grid gap-2">
                      <button type="submit" class="btn btn-primary mt-3" style="background-color:#003f51; color: white;">Se connecter</button>
                </div>
                  
                </form>
            </div>
        </div>
    </div>
</div>


</body>
</html>