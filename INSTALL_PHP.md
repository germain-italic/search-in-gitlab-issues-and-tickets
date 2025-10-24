# Installation de PHP pour le développement

## Pour Debian/Ubuntu (WSL)

Exécutez ces commandes dans votre terminal :

```bash
# Mettre à jour les paquets
sudo apt update

# Installer PHP et les extensions nécessaires
sudo apt install -y php php-cli php-curl php-mbstring php-xml

# Vérifier l'installation
php --version

# Installer Composer (si pas déjà installé)
sudo apt install -y composer

# OU télécharger Composer directement
# curl -sS https://getcomposer.org/installer | php
# sudo mv composer.phar /usr/local/bin/composer
```

## Installer les dépendances du projet

```bash
cd /home/germain/dev/search-in-gitlab-issues-and-tickets

# Installer les dépendances PHP
composer install
```

## Lancer le serveur de développement

```bash
# Méthode 1 : Serveur PHP intégré
php -S localhost:8080

# Méthode 2 : Utiliser le script fourni
./start-server.sh

# Méthode 3 : Sur un autre port si 8080 est occupé
php -S localhost:3000
```

Ensuite, ouvrez votre navigateur à l'adresse : http://localhost:8080

## Déboguer la recherche de commentaires

Pour tester la recherche de commentaires avec le script de débogage :

```bash
# Syntaxe : php debug_comments.php <project_id> <search_term>
php debug_comments.php 123 "exception"
```

Remplacez `123` par l'ID réel de votre projet GitLab.

## Vérifier les logs d'erreurs PHP

Si vous rencontrez des problèmes, vous pouvez activer les logs d'erreurs :

```bash
# Lancer le serveur avec affichage des erreurs
php -S localhost:8080 -d display_errors=1 -d error_reporting=E_ALL
```

## Problèmes courants

### "vendor/autoload.php" not found
```bash
composer install
```

### "Call to undefined function curl_init"
```bash
sudo apt install php-curl
```

### Port déjà utilisé
```bash
# Utiliser un autre port
php -S localhost:3000
```
