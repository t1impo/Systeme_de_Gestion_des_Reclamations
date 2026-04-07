<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<link href="../bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet" type="text/css">
<script src="../bootstrap-5.3.8-dist/js/bootstrap.min.js"></script>
<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
/* Dropdown de notifications : hauteur max + scroll si trop de contenu */
.notif-dropdown {
    max-height: 400px;      /* ajuste la hauteur max comme tu veux */
    overflow-y: auto;       /* barre de scroll verticale si trop de notifications */
}

/* Texte du commentaire dans la notification */
.notif-comment {
    white-space: normal;         /* retour à la ligne normal */
    overflow-wrap: break-word;   /* coupe les mots trop longs s'il le faut */
    word-wrap: break-word;       /* compatibilité anciens navigateurs */
}


</style>