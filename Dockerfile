# 1. Utiliser l'image officielle PHP avec Apache (vous pouvez changer 8.2 par votre version de PHP)
FROM php:8.2-apache

# 2. Activer le module mod_rewrite d'Apache (très utile si vous utilisez des URL propres ou un fichier .htaccess)
RUN a2enmod rewrite

# 3. Copier tous les fichiers de votre projet (PHP, CSS, HTML) dans le dossier par défaut d'Apache
COPY . /var/www/html/

# 4. Assurer que l'utilisateur Apache (www-data) a les bons droits sur les fichiers
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/

# 5. Exposer le port 80 pour le trafic web web
EXPOSE 80