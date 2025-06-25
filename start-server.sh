#!/bin/bash

# Script pour lancer un serveur PHP standalone
# Usage: ./start-server.sh [port]

# Configuration par défaut
DEFAULT_PORT=8080
DEFAULT_HOST="localhost"

# Récupération du port depuis les arguments ou utilisation du port par défaut
PORT=${1:-$DEFAULT_PORT}

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Démarrage du serveur PHP standalone${NC}"
echo -e "${BLUE}======================================${NC}"

# Vérification que PHP est installé
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ Erreur: PHP n'est pas installé ou n'est pas dans le PATH${NC}"
    exit 1
fi

# Affichage de la version PHP
PHP_VERSION=$(php -v | head -n 1)
echo -e "${GREEN}✅ PHP détecté: ${PHP_VERSION}${NC}"


# Vérification que composer a été exécuté
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}⚠️  Le répertoire 'vendor' n'existe pas${NC}"
    echo -e "${YELLOW}🔧 Installation des dépendances Composer...${NC}"
    
    if ! command -v composer &> /dev/null; then
        echo -e "${RED}❌ Erreur: Composer n'est pas installé${NC}"
        echo -e "${YELLOW}💡 Installez Composer depuis https://getcomposer.org/${NC}"
        exit 1
    fi
    
    composer install
    
    if [ $? -ne 0 ]; then
        echo -e "${RED}❌ Erreur lors de l'installation des dépendances${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}✅ Dépendances installées avec succès${NC}"
fi

# Vérification que le port n'est pas déjà utilisé
if lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null 2>&1; then
    echo -e "${RED}❌ Erreur: Le port $PORT est déjà utilisé${NC}"
    echo -e "${YELLOW}💡 Essayez avec un autre port: ./start-server.sh 8081${NC}"
    exit 1
fi

# Création du fichier de log
LOG_FILE="server.log"
touch $LOG_FILE

echo -e "${GREEN}✅ Configuration validée${NC}"
echo ""
echo -e "${BLUE}📋 Informations du serveur:${NC}"
echo -e "   🌐 Host: ${DEFAULT_HOST}"
echo -e "   🔌 Port: ${PORT}"
echo -e "   📁 Document Root: $(pwd)"
echo -e "   📝 Log File: $(pwd)/${LOG_FILE}"
echo ""
echo -e "${GREEN}🌍 URLs d'accès:${NC}"
echo -e "   🏠 Accueil:           http://${DEFAULT_HOST}:${PORT}/"
echo ""
echo -e "${YELLOW}⚡ Démarrage du serveur...${NC}"
echo -e "${YELLOW}   Appuyez sur Ctrl+C pour arrêter le serveur${NC}"
echo ""

# Variable pour stocker le PID du serveur
SERVER_PID=""

# Fonction pour gérer l'arrêt propre du serveur
cleanup() {
    echo ""
    echo -e "${YELLOW}🛑 Arrêt du serveur en cours...${NC}"
    
    if [ ! -z "$SERVER_PID" ]; then
        # Tuer le processus serveur PHP
        kill $SERVER_PID 2>/dev/null
        
        # Attendre que le processus se termine
        wait $SERVER_PID 2>/dev/null
        
        echo -e "${GREEN}✅ Serveur PHP arrêté (PID: $SERVER_PID)${NC}"
    fi
    
    # Nettoyer le fichier de log si souhaité
    # rm -f $LOG_FILE
    
    echo -e "${GREEN}✅ Nettoyage terminé${NC}"
    exit 0
}

# Capture des signaux d'interruption (Ctrl+C) et de terminaison
trap cleanup SIGINT SIGTERM

# Démarrage du serveur PHP en arrière-plan
php -S ${DEFAULT_HOST}:${PORT} -t ./ > $LOG_FILE 2>&1 &
SERVER_PID=$!

# Attendre un peu pour vérifier que le serveur a démarré
sleep 2

# Vérification que le serveur fonctionne
if kill -0 $SERVER_PID 2>/dev/null; then
    echo -e "${GREEN}✅ Serveur démarré avec succès (PID: $SERVER_PID)${NC}"
    echo -e "${GREEN}🎉 Accédez à votre application sur: http://${DEFAULT_HOST}:${PORT}${NC}"
    echo ""
    echo -e "${BLUE}📝 Logs en temps réel:${NC}"
    echo -e "${YELLOW}   (Les logs sont aussi sauvegardés dans ${LOG_FILE})${NC}"
    echo -e "${YELLOW}   Appuyez sur Ctrl+C pour arrêter le serveur${NC}"
    echo ""
    
    # Affichage des logs en temps réel
    # Utiliser tail avec le PID pour s'arrêter quand le serveur s'arrête
    tail -f $LOG_FILE &
    TAIL_PID=$!
    
    # Attendre que le serveur se termine
    wait $SERVER_PID
    
    # Arrêter tail si le serveur s'est arrêté
    kill $TAIL_PID 2>/dev/null
    
else
    echo -e "${RED}❌ Erreur: Le serveur n'a pas pu démarrer${NC}"
    echo -e "${YELLOW}💡 Vérifiez les logs dans ${LOG_FILE}${NC}"
    exit 1
fi